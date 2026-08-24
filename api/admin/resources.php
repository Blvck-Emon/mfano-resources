<?php
/**
 * api/admin/resources.php
 *
 * POST   /api/admin/resources.php            - create a resource
 * PUT    /api/admin/resources.php?id=5        - update a resource
 * DELETE /api/admin/resources.php?id=5        - delete a resource
 *
 * Every request must include the header: X-Api-Key: <ADMIN_API_KEY>
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

applyCors();
requireAdminKey();

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($method === 'POST') {
    createResource($pdo);
} elseif ($method === 'PUT' && $id) {
    updateResource($pdo, $id);
} elseif ($method === 'DELETE' && $id) {
    deleteResource($pdo, $id);
} else {
    sendError('Method not allowed', 405);
}

function createResource(PDO $pdo): void
{
    $body = getJsonBody();
    $subCategoryId = $body['sub_category_id'] ?? null;
    $title = $body['title'] ?? null;
    $description = $body['description'] ?? null;
    $fileUrl = $body['file_url'] ?? null;
    $fileSizeKb = $body['file_size_kb'] ?? 0;
    $isFeatured = $body['is_featured'] ?? false;

    if (!$subCategoryId || !$title || !$description || !$fileUrl) {
        sendError('sub_category_id, title, description, and file_url are required.', 400);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO resources (sub_category_id, title, description, file_url, file_size_kb, is_featured)
             VALUES (:sub_category_id, :title, :description, :file_url, :file_size_kb, :is_featured)
             RETURNING *'
        );
        $stmt->execute([
            'sub_category_id' => $subCategoryId,
            'title'           => $title,
            'description'     => $description,
            'file_url'        => $fileUrl,
            'file_size_kb'    => $fileSizeKb ?: 0,
            'is_featured'     => $isFeatured ? 'true' : 'false',
        ]);

        sendSuccess($stmt->fetch(), null, 201);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to create resource');
    }
}

function updateResource(PDO $pdo, int $id): void
{
    $body = getJsonBody();

    try {
        $stmt = $pdo->prepare(
            'UPDATE resources
             SET sub_category_id = :sub_category_id, title = :title, description = :description,
                 file_url = :file_url, is_featured = :is_featured, is_published = :is_published,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
             RETURNING *'
        );
        $stmt->execute([
            'sub_category_id' => $body['sub_category_id'] ?? null,
            'title'           => $body['title'] ?? null,
            'description'     => $body['description'] ?? null,
            'file_url'        => $body['file_url'] ?? null,
            'is_featured'     => !empty($body['is_featured']) ? 'true' : 'false',
            'is_published'    => array_key_exists('is_published', $body) && !$body['is_published'] ? 'false' : 'true',
            'id'              => $id,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            sendError('Resource not found', 404);
        }

        sendSuccess($row);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to update resource');
    }
}

function deleteResource(PDO $pdo, int $id): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM resources WHERE id = :id RETURNING id');
        $stmt->execute(['id' => $id]);

        if (!$stmt->fetch()) {
            sendError('Resource not found', 404);
        }

        sendJson(['success' => true, 'message' => 'Resource deleted successfully']);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to delete resource');
    }
}
