<?php
/**
 * api/admin/resources.php
 *
 * POST   /api/admin/resources.php             - create a resource
 *          multipart/form-data with a "file" field -> uploads and stores
 *          a PDF directly on this server (storage_type = 'local')
 *          application/json with "file_url"          -> links an already
 *          hosted PDF, e.g. S3/Cloudinary (storage_type = 'external')
 * PUT    /api/admin/resources.php?id=5         - update a resource
 * DELETE /api/admin/resources.php?id=5         - delete a resource
 *
 * Every request must include the header: X-Api-Key: <ADMIN_API_KEY>
 *
 * Refactor notes (Postgres -> SQLite):
 *   - `... RETURNING *` is dropped in favour of lastInsertId() + a follow-up
 *     SELECT, which works identically across PHP/SQLite builds regardless
 *     of whether the bundled SQLite supports the RETURNING clause.
 *   - is_featured / is_published now bind as 1/0 integers, not 'true'/'false'.
 *   - Postgres unique_violation code '23505' -> SQLite's is '23000' (PDO
 *     normalises both to a generic string; we check the driver-specific
 *     SQLSTATE via $e->errorInfo[1] === 19, SQLite's constraint-violation code).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload.php';

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
    $isMultipart = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
    $body = $isMultipart ? $_POST : getJsonBody();

    $subCategoryId = $body['sub_category_id'] ?? null;
    $title = $body['title'] ?? null;
    $description = $body['description'] ?? null;
    $isFeatured = !empty($body['is_featured']) && $body['is_featured'] !== 'false' ? 1 : 0;

    if (!$subCategoryId || !$title || !$description) {
        sendError('sub_category_id, title, and description are required.', 400);
    }

    // Path A: a PDF was attached directly -> store it on this server.
    if ($isMultipart && !empty($_FILES['file'])) {
        $stored = storeUploadedPdf($_FILES['file']);
        $fileUrl = $stored['file_url'];
        $storageType = 'local';
        $storedPath = $stored['stored_path'];
        $checksum = $stored['checksum_sha256'];
        $fileSizeKb = $stored['file_size_kb'];
    } else {
        // Path B: an already-hosted URL (S3/Cloudinary/etc), same as before.
        $fileUrl = $body['file_url'] ?? null;
        if (!$fileUrl) {
            sendError('Provide either a "file" upload or a "file_url".', 400);
        }
        $storageType = 'external';
        $storedPath = null;
        $checksum = null;
        $fileSizeKb = (int) ($body['file_size_kb'] ?? 0);
    }

    try {
        $insert = $pdo->prepare(
            'INSERT INTO resources
                (sub_category_id, title, description, file_url, storage_type,
                 stored_path, checksum_sha256, file_size_kb, is_featured)
             VALUES
                (:sub_category_id, :title, :description, :file_url, :storage_type,
                 :stored_path, :checksum_sha256, :file_size_kb, :is_featured)'
        );
        $insert->execute([
            'sub_category_id' => $subCategoryId,
            'title'           => $title,
            'description'     => $description,
            'file_url'        => $fileUrl,
            'storage_type'    => $storageType,
            'stored_path'     => $storedPath,
            'checksum_sha256' => $checksum,
            'file_size_kb'    => $fileSizeKb,
            'is_featured'     => $isFeatured,
        ]);

        $newId = (int) $pdo->lastInsertId();
        $row = $pdo->prepare('SELECT * FROM resources WHERE id = :id');
        $row->execute(['id' => $newId]);

        sendSuccess($row->fetch(), null, 201);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to create resource');
    }
}

function updateResource(PDO $pdo, int $id): void
{
    $body = getJsonBody();

    try {
        $update = $pdo->prepare(
            'UPDATE resources
             SET sub_category_id = :sub_category_id, title = :title, description = :description,
                 file_url = :file_url, is_featured = :is_featured, is_published = :is_published
             WHERE id = :id'
        );
        $update->execute([
            'sub_category_id' => $body['sub_category_id'] ?? null,
            'title'           => $body['title'] ?? null,
            'description'     => $body['description'] ?? null,
            'file_url'        => $body['file_url'] ?? null,
            'is_featured'     => !empty($body['is_featured']) ? 1 : 0,
            'is_published'    => array_key_exists('is_published', $body) && !$body['is_published'] ? 0 : 1,
            'id'              => $id,
        ]);

        $row = $pdo->prepare('SELECT * FROM resources WHERE id = :id');
        $row->execute(['id' => $id]);
        $result = $row->fetch();

        if (!$result) {
            sendError('Resource not found', 404);
        }

        sendSuccess($result);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to update resource');
    }
}

function deleteResource(PDO $pdo, int $id): void
{
    try {
        $find = $pdo->prepare('SELECT * FROM resources WHERE id = :id');
        $find->execute(['id' => $id]);
        $resource = $find->fetch();

        if (!$resource) {
            sendError('Resource not found', 404);
        }

        $delete = $pdo->prepare('DELETE FROM resources WHERE id = :id');
        $delete->execute(['id' => $id]);

        // Best-effort cleanup of the file on disk for locally uploaded PDFs.
        if ($resource['storage_type'] === 'local' && $resource['stored_path']) {
            $absolutePath = __DIR__ . '/../../' . ltrim($resource['stored_path'], '/');
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        sendJson(['success' => true, 'message' => 'Resource deleted successfully']);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        sendError('Failed to delete resource');
    }
}