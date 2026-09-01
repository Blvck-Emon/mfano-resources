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
    $insert = $pdo->prepare(
        'INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)'
    );
    $insert->execute(['name' => $name, 'slug' => $slug, 'description' => $description]);

    $newId = (int) $pdo->lastInsertId();
    $row = $pdo->prepare('SELECT * FROM categories WHERE id = :id');
    $row->execute(['id' => $newId]);

    sendSuccess($row->fetch(), null, 201);
} catch (PDOException $e) {
    // SQLite's UNIQUE constraint violation surfaces as SQLSTATE 23000 /
    // driver code 19 (was Postgres' unique_violation, '23505').
    if (($e->errorInfo[1] ?? null) === 19) {
        sendError('A category with that name or slug already exists.', 409);
    }
    error_log($e->getMessage());
    sendError('Failed to create category');
}