<?php
/**
 * api/admin/categories.php
 *
 * POST /api/admin/categories.php - create a new top-level category
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

$name = $body['name'] ?? null;
$slug = $body['slug'] ?? null;
$description = $body['description'] ?? null;

if (!$name || !$slug) {
    sendError('name and slug are required.', 400);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description) RETURNING *'
    );
    $stmt->execute(['name' => $name, 'slug' => $slug, 'description' => $description]);

    sendSuccess($stmt->fetch(), null, 201);
} catch (PDOException $e) {
    // Postgres unique_violation
    if ($e->getCode() === '23505') {
        sendError('A category with that name or slug already exists.', 409);
    }
    error_log($e->getMessage());
    sendError('Failed to create category');
}
