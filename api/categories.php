<?php
/**
 * api/categories.php
 *
 * GET /api/categories.php
 * Public endpoint. Returns the full category -> sub-category tree,
 * used for navigation and the sidebar filter.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

applyCors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    $pdo = getDbConnection();

    $categories = $pdo->query('SELECT * FROM categories ORDER BY id ASC')->fetchAll();
    $subCategories = $pdo->query('SELECT * FROM sub_categories ORDER BY id ASC')->fetchAll();

    foreach ($categories as &$category) {
        $category['subcategories'] = array_values(array_filter(
            $subCategories,
            fn($sub) => (int) $sub['category_id'] === (int) $category['id']
        ));
    }
    unset($category);

    sendSuccess($categories);
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendError('Server Error');
}
