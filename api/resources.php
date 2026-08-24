<?php
/**
 * api/resources.php
 *
 * GET  /api/resources.php                          - list, with filters
 * GET  /api/resources.php?id=5                      - single resource
 * POST /api/resources.php?id=5&action=download       - increment download_count
 *
 * Query filters for the list view: category, subcategory, search, featured
 * (same query params the Node version used).
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
        SELECT r.id, r.title, r.description, r.file_url, r.file_size_kb,
               r.download_count, r.is_featured, r.publish_date,
               sc.name AS sub_category_name, sc.slug AS sub_category_slug,
               c.name AS category_name, c.slug AS category_slug
        FROM resources r
        JOIN sub_categories sc ON r.sub_category_id = sc.id
        JOIN categories c ON sc.category_id = c.id
        WHERE r.is_published = TRUE
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
        $sql .= ' AND r.is_featured = TRUE';
    }

    if ($search) {
        $sql .= ' AND (r.title ILIKE :search OR r.description ILIKE :search2)';
        $params['search'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
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

function getResourceById(PDO $pdo, int $id): void
{
    try {
        $stmt = $pdo->prepare(
            'SELECT r.*, sc.name AS sub_category_name, c.name AS category_name
             FROM resources r
             JOIN sub_categories sc ON r.sub_category_id = sc.id
             JOIN categories c ON sc.category_id = c.id
             WHERE r.id = :id AND r.is_published = TRUE'
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

function trackDownload(PDO $pdo, int $id): void
{
    try {
        $stmt = $pdo->prepare(
            'UPDATE resources SET download_count = download_count + 1 WHERE id = :id RETURNING download_count'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            sendError('Resource not found', 404);
        }

        sendJson(['success' => true, 'download_count' => (int) $row['download_count']]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to record download');
    }
}
