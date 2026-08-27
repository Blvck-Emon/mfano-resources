<?php
/**
 * api/resources.php
 *
 * GET  /api/resources.php                     - list, with filters
 * GET  /api/resources.php?id=5                 - single resource
 * POST /api/resources.php?id=5&action=download  - log a download + bump counter
 *
 * Query filters for the list view: category, subcategory, search, featured.
 *
 * Refactor notes (Postgres -> SQLite):
 *   - ILIKE -> LIKE (SQLite's LIKE is already case-insensitive for ASCII;
 *     for full Unicode-safe search use the resources_fts virtual table,
 *     see searchResources() below).
 *   - TRUE/FALSE literals -> 1
 *   - trackDownload now INSERTs into download_logs (the resources.
 *     download_count column is kept in sync automatically by the
 *     trg_download_logs_increment trigger, so we no longer UPDATE it here).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

applyCors();

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
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

/* ---------------------------- PUBLIC ---------------------------- */

function getResources(PDO $pdo): void
{
    $category = $_GET['category'] ?? null;
    $subcategory = $_GET['subcategory'] ?? null;
    $search = $_GET['search'] ?? null;
    $featured = $_GET['featured'] ?? null;

    $sql = "
        SELECT r.id, r.title, r.description, r.file_url, r.storage_type, r.file_size_kb,
               r.download_count, r.is_featured, r.publish_date,
               sc.name AS sub_category_name, sc.slug AS sub_category_slug,
               c.name AS category_name, c.slug AS category_slug
        FROM resources r
        JOIN sub_categories sc ON r.sub_category_id = sc.id
        JOIN categories c ON sc.category_id = c.id
        WHERE r.is_published = 1
    ";

    $params = [];

    if ($category) {
        $sql .= ' AND c.slug = :category';
        $params['category'] = $category;
    }

    if ($subcategory) {
        $sql .= ' AND sc.slug = :subcategory';
        $params['subcategory'] = $subcategory;
    }

    if ($featured) {
        $sql .= ' AND r.is_featured = 1';
    }

    if ($search) {
        // FTS5 gives better ranking/relevance than LIKE on longer descriptions;
        // fall back to LIKE automatically if the FTS table is unavailable.
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
                $sql .= ' AND 1 = 0'; // no FTS matches
            }
        } catch (PDOException $e) {
            $sql .= ' AND (r.title LIKE :search OR r.description LIKE :search2)';
            $params['search'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
        }
    }

    $sql .= ' ORDER BY r.publish_date DESC';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        sendSuccess($rows, count($rows));
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Server Error');
    }
}

/** Escapes a raw search string for safe use inside an FTS5 MATCH query. */
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
            'SELECT r.*, sc.name AS sub_category_name, c.name AS category_name
             FROM resources r
             JOIN sub_categories sc ON r.sub_category_id = sc.id
             JOIN categories c ON sc.category_id = c.id
             WHERE r.id = :id AND r.is_published = 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            sendError('Resource not found', 404);
        }

        sendSuccess($row);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Server Error');
    }
}

/**
 * Records a download event. Prefer api/download.php for real links (it
 * logs AND serves/redirects the file in one request); this endpoint stays
 * for widgets that only need to fire-and-forget a tracking ping.
 */
function trackDownload(PDO $pdo, int $id): void
{
    try {
        $exists = $pdo->prepare('SELECT id FROM resources WHERE id = :id');
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

        sendJson(['success' => true, 'download_count' => (int) $count->fetch()['download_count']]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to record download');
    }
}