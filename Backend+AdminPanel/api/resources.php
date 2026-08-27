<?php
/**
 * api/resources.php
 *
 * GET  /api/resources.php                      - list, with flexible filters
 * GET  /api/resources.php?id=5                  - single resource details
 * POST /api/resources.php?id=5&action=download   - log a download event
 *
 * Query filters for list view:
 *   - category_id, category_slug (or category)
 *   - sub_category_id, sub_category_slug (or subcategory)
 *   - q (or search)
 *   - featured (or is_featured)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

applyCors();

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$action = $_GET['action'] ?? null;

if ($method === 'POST' && $id && $action === 'download') {
    trackDownload($pdo, $id);
} elseif ($method === 'GET' && $id) {
    getResourceById($pdo, $id);
} elseif ($method === 'GET') {
    getResources($pdo);
} else {
    sendError('Method not allowed', 405);
}

/* ---------------------------- PUBLIC API FUNCTIONS ---------------------------- */

function getResources(PDO $pdo): void
{
    // Filter extraction with backwards and alias parameter support
    $subCategoryId = filter_input(INPUT_GET, 'sub_category_id', FILTER_VALIDATE_INT) 
                     ?: filter_input(INPUT_GET, 'subcategory_id', FILTER_VALIDATE_INT);
    $subCategorySlug = $_GET['sub_category_slug'] ?? $_GET['subcategory'] ?? null;
    
    $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
    $categorySlug = $_GET['category_slug'] ?? $_GET['category'] ?? null;
    
    $search = $_GET['q'] ?? $_GET['search'] ?? null;
    $featured = $_GET['is_featured'] ?? $_GET['featured'] ?? null;

    $sql = "
        SELECT 
            r.id, 
            r.title, 
            r.description, 
            r.file_url, 
            r.storage_type, 
            r.file_size_kb,
            r.download_count, 
            r.is_featured, 
            r.publish_date, 
            r.created_at,
            sc.id AS sub_category_id, 
            sc.name AS sub_category_name, 
            sc.slug AS sub_category_slug,
            c.id AS category_id, 
            c.name AS category_name, 
            c.slug AS category_slug
        FROM resources r
        INNER JOIN sub_categories sc ON r.sub_category_id = sc.id
        INNER JOIN categories c ON sc.category_id = c.id
        WHERE r.is_published = 1
    ";

    $params = [];

    if ($subCategoryId) {
        $sql .= ' AND r.sub_category_id = :sub_category_id';
        $params['sub_category_id'] = $subCategoryId;
    } elseif ($subCategorySlug) {
        $sql .= ' AND sc.slug = :sub_category_slug';
        $params['sub_category_slug'] = $subCategorySlug;
    } elseif ($categoryId) {
        $sql .= ' AND c.id = :category_id';
        $params['category_id'] = $categoryId;
    } elseif ($categorySlug) {
        $sql .= ' AND c.slug = :category_slug';
        $params['category_slug'] = $categorySlug;
    }

    if ($featured) {
        $sql .= ' AND r.is_featured = 1';
    }

    if ($search) {
        // Attempt FTS5 virtual table match; fallback gracefully to LIKE if unconfigured
        try {
            $ftsIds = $pdo->prepare(
                "SELECT rowid FROM resources_fts WHERE resources_fts MATCH :q"
            );
            $ftsIds->execute(['q' => escapeFtsQuery($search)]);
            $ids = array_column($ftsIds->fetchAll(), 'rowid');

            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND r.id IN ($placeholders)";
                $params = array_merge(array_values($params), $ids);
            } else {
                $sql .= ' AND 1 = 0'; // Return zero results when search term has no FTS hits
            }
        } catch (PDOException $e) {
            $sql .= ' AND (r.title LIKE :search OR r.description LIKE :search2)';
            $params['search'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
        }
    }

    $sql .= ' ORDER BY r.is_featured DESC, r.created_at DESC';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendSuccess($rows, count($rows));
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Server Error');
    }
}

/** Escapes raw user search strings for safe execution inside FTS5 MATCH queries */
function escapeFtsQuery(string $term): string
{
    $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $term);
    $words = array_filter(explode(' ', trim($clean)));
    return implode(' ', array_map(fn($w) => '"' . $w . '"*', $words));
}

function getResourceById(PDO $pdo, int $id): void
{
    try {
        $stmt = $pdo->prepare(
            'SELECT r.*, 
                    sc.id AS sub_category_id, sc.name AS sub_category_name, sc.slug AS sub_category_slug,
                    c.id AS category_id, c.name AS category_name, c.slug AS category_slug
             FROM resources r
             INNER JOIN sub_categories sc ON r.sub_category_id = sc.id
             INNER JOIN categories c ON sc.category_id = c.id
             WHERE r.id = :id AND r.is_published = 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            sendError('Resource not found', 404);
        }

        sendSuccess($row);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Server Error');
    }
}

/** Records a download event into download_logs */
function trackDownload(PDO $pdo, int $id): void
{
    try {
        $exists = $pdo->prepare('SELECT id FROM resources WHERE id = :id AND is_published = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            sendError('Resource not found', 404);
        }

        $log = $pdo->prepare(
            'INSERT INTO download_logs (resource_id, ip_address, user_agent, referrer)
             VALUES (:resource_id, :ip_address, :user_agent, :referrer)'
        );
        $log->execute([
            'resource_id' => $id,
            'ip_address'  => getClientIp(),
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referrer'    => $_SERVER['HTTP_REFERER'] ?? null,
        ]);

        $count = $pdo->prepare('SELECT download_count FROM resources WHERE id = :id');
        $count->execute(['id' => $id]);

        sendJson(['success' => true, 'download_count' => (int) $count->fetchColumn()]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to record download');
    }
}