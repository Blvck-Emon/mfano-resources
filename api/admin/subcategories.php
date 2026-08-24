<?php
/**
 * api/admin/subcategories.php
 *
 * POST /api/admin/subcategories.php - create a new sub-category
 * Header required: X-Api-Key: <ADMIN_API_KEY>
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

applyCors();
requireAdminKey();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$pdo = getDbConnection();
$body = getJsonBody();

$categoryId = $body['category_id'] ?? null;
$name = $body['name'] ?? null;
$slug = $body['slug'] ?? null;
$description = $body['description'] ?? null;

if (!$categoryId || !$name || !$slug) {
    sendError('category_id, name and slug are required.', 400);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO sub_categories (category_id, name, slug, description)
         VALUES (:category_id, :name, :slug, :description) RETURNING *'
    );
    $stmt->execute([
        'category_id' => $categoryId,
        'name'        => $name,
        'slug'        => $slug,
        'description' => $description,
    ]);

    sendSuccess($stmt->fetch(), null, 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        sendError('A sub-category with that slug already exists.', 409);
    }
    error_log($e->getMessage());
    sendError('Failed to create sub-category');
}
