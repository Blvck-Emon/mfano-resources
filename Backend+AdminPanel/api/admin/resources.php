<?php
/**
 * Backend+AdminPanel/api/admin/resources.php
 * Unified Admin API Endpoint for Creating, Updating, Retrieving, and Deleting Resources
 */

require_once __DIR__ . '/../../config/db.php';

// Dynamically include helper modules if available in ecosystem
if (file_exists(__DIR__ . '/../../includes/helpers.php')) {
    require_once __DIR__ . '/../../includes/helpers.php';
}
if (file_exists(__DIR__ . '/../../includes/auth.php')) {
    require_once __DIR__ . '/../../includes/auth.php';
}
if (file_exists(__DIR__ . '/../../includes/upload.php')) {
    require_once __DIR__ . '/../../includes/upload.php';
}

// -------------------------------------------------------------------------
// 1. CORS & HTTP Method Setup
// -------------------------------------------------------------------------
if (function_exists('applyCors')) {
    applyCors();
} else {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, X-Admin-Api-Key');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// -------------------------------------------------------------------------
// 2. Authentication Verification
// -------------------------------------------------------------------------
if (function_exists('requireAdminKey')) {
    requireAdminKey();
} else {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? ($_GET['api_key'] ?? '');
    $validKey = getenv('ADMIN_API_KEY') ?: 'admin123';
    
    if (empty($apiKey) || $apiKey !== $validKey) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid Admin API Key']);
        exit();
    }
}

// -------------------------------------------------------------------------
// 3. Database Connection & Request Router
// -------------------------------------------------------------------------
$pdo = function_exists('getDbConnection') ? getDbConnection() : ($pdo ?? null);
if (!$pdo && isset($db)) {
    $pdo = $db;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {
    case 'POST':
        handleCreateResource($pdo);
        break;
    case 'PUT':
        if (!$id) {
            respondJson(['success' => false, 'error' => 'Missing resource ID for update.'], 400);
        }
        handleUpdateResource($pdo, $id);
        break;
    case 'DELETE':
        if (!$id) {
            respondJson(['success' => false, 'error' => 'Missing resource ID for deletion.'], 400);
        }
        handleDeleteResource($pdo, $id);
        break;
    case 'GET':
        handleGetResources($pdo, $id);
        break;
    default:
        respondJson(['success' => false, 'error' => 'Method not allowed'], 405);
}

// -------------------------------------------------------------------------
// 4. Controller Functions
// -------------------------------------------------------------------------

/**
 * Handles Resource Creation (POST)
 */
function handleCreateResource(PDO $pdo): void
{
    $payload = getPayload();

    $subCategoryId = filter_var($payload['sub_category_id'] ?? null, FILTER_VALIDATE_INT);
    $title         = trim($payload['title'] ?? '');
    $description   = trim($payload['description'] ?? '');
    $fileUrl       = trim($payload['file_url'] ?? '');
    $isFeatured    = !empty($payload['is_featured']) && $payload['is_featured'] !== 'false' && $payload['is_featured'] !== '0' ? 1 : 0;

    if (!$subCategoryId || empty($title) || empty($description)) {
        respondJson(['success' => false, 'error' => 'Missing required fields: Title, Sub-Category, and Description are mandatory.'], 400);
    }

    $storageType = 'external';
    $filePath    = null;
    $checksum    = null;
    $fileSizeKb  = 0;

    // Direct PDF Upload Handling
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        if (function_exists('storeUploadedPdf')) {
            $stored      = storeUploadedPdf($_FILES['file']);
            $fileUrl     = $stored['file_url'] ?? $fileUrl;
            $storageType = 'local';
            $filePath    = $stored['stored_path'] ?? $stored['file_path'] ?? null;
            $checksum    = $stored['checksum_sha256'] ?? null;
            $fileSizeKb  = $stored['file_size_kb'] ?? 0;
        } else {
            $file = $_FILES['file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'pdf') {
                respondJson(['success' => false, 'error' => 'Only PDF files are allowed.'], 400);
            }

            // Establish Absolute Upload Directory
            $uploadDir = __DIR__ . '/../../uploads/resources/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = uniqid('res_', true) . '.pdf';
            $targetFile  = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                $storageType = 'local';
                $filePath    = 'uploads/resources/' . $newFileName;
                if (empty($fileUrl)) {
                    $fileUrl = $filePath;
                }
                $checksum   = hash_file('sha256', $targetFile);
                $fileSizeKb = (int) ceil(filesize($targetFile) / 1024);
            } else {
                respondJson(['success' => false, 'error' => 'Failed to save uploaded PDF to disk.'], 500);
            }
        }
    } elseif (!empty($fileUrl)) {
        $storageType = 'external';
        $fileSizeKb  = (int) ($payload['file_size_kb'] ?? 0);
    } else {
        respondJson(['success' => false, 'error' => 'Either a PDF file upload or an existing file URL must be provided.'], 400);
    }

    try {
        // Dual column population for maximum schema compatibility (file_path & stored_path)
        $sql = "INSERT INTO resources (
            sub_category_id, title, description, storage_type, 
            file_path, stored_path, file_url, checksum_sha256, 
            file_size_kb, is_featured, is_published, download_count, created_at
        ) VALUES (
            :sub_category_id, :title, :description, :storage_type, 
            :file_path, :stored_path, :file_url, :checksum_sha256, 
            :file_size_kb, :is_featured, 1, 0, CURRENT_TIMESTAMP
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sub_category_id' => $subCategoryId,
            ':title'           => $title,
            ':description'     => $description,
            ':storage_type'    => $storageType,
            ':file_path'       => $filePath,
            ':stored_path'     => $filePath,
            ':file_url'        => $fileUrl,
            ':checksum_sha256' => $checksum,
            ':file_size_kb'    => $fileSizeKb,
            ':is_featured'     => $isFeatured
        ]);

        $newId = (int) $pdo->lastInsertId();

        $fetchStmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
        $fetchStmt->execute([':id' => $newId]);
        $createdResource = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        respondJson([
            'success' => true,
            'id'      => $newId,
            'message' => 'Resource created successfully.',
            'data'    => $createdResource
        ], 201);

    } catch (PDOException $e) {
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 19) {
            respondJson(['success' => false, 'error' => 'Resource constraint violation or duplicate entry.'], 400);
        }
        respondJson(['success' => false, 'error' => 'Database insert error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        respondJson(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

/**
 * Handles Resource Updates (PUT)
 */
function handleUpdateResource(PDO $pdo, int $id): void
{
    $payload = getPayload();

    try {
        $fetchStmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
        $fetchStmt->execute([':id' => $id]);
        $existing = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            respondJson(['success' => false, 'error' => 'Resource not found'], 404);
        }

        $subCategoryId = filter_var($payload['sub_category_id'] ?? $existing['sub_category_id'], FILTER_VALIDATE_INT);
        $title         = trim($payload['title'] ?? $existing['title']);
        $description   = trim($payload['description'] ?? $existing['description']);
        $fileUrl       = trim($payload['file_url'] ?? $existing['file_url']);
        $isFeatured    = isset($payload['is_featured']) ? (!empty($payload['is_featured']) && $payload['is_featured'] !== 'false' ? 1 : 0) : $existing['is_featured'];
        $isPublished   = array_key_exists('is_published', $payload) ? (!empty($payload['is_published']) && $payload['is_published'] !== 'false' ? 1 : 0) : $existing['is_published'];

        $updateStmt = $pdo->prepare("
            UPDATE resources
            SET sub_category_id = :sub_category_id,
                title = :title,
                description = :description,
                file_url = :file_url,
                is_featured = :is_featured,
                is_published = :is_published
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':sub_category_id' => $subCategoryId,
            ':title'           => $title,
            ':description'     => $description,
            ':file_url'        => $fileUrl,
            ':is_featured'     => $isFeatured,
            ':is_published'    => $isPublished,
            ':id'              => $id
        ]);

        $rowStmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
        $rowStmt->execute([':id' => $id]);
        $updatedResource = $rowStmt->fetch(PDO::FETCH_ASSOC);

        respondJson([
            'success' => true,
            'message' => 'Resource updated successfully.',
            'data'    => $updatedResource
        ]);
    } catch (Exception $e) {
        respondJson(['success' => false, 'error' => 'Failed to update resource: ' . $e->getMessage()], 500);
    }
}

/**
 * Handles Resource Deletion (DELETE)
 */
function handleDeleteResource(PDO $pdo, int $id): void
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resource) {
            respondJson(['success' => false, 'error' => 'Resource not found.'], 404);
        }

        // Unlink local file from disk if present
        $pathToCheck = !empty($resource['file_path']) ? $resource['file_path'] : ($resource['stored_path'] ?? null);
        if ($pathToCheck) {
            $fileOnDisk = __DIR__ . '/../../' . ltrim($pathToCheck, '/');
            if (file_exists($fileOnDisk) && is_file($fileOnDisk)) {
                @unlink($fileOnDisk);
            }
        }

        $delStmt = $pdo->prepare("DELETE FROM resources WHERE id = :id");
        $delStmt->execute([':id' => $id]);

        respondJson([
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ]);
    } catch (Exception $e) {
        respondJson(['success' => false, 'error' => 'Failed to delete resource: ' . $e->getMessage()], 500);
    }
}

/**
 * Handles Resource Retrieval (GET)
 */
function handleGetResources(PDO $pdo, ?int $id): void
{
    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $resource = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$resource) {
                respondJson(['success' => false, 'error' => 'Resource not found'], 404);
            }
            respondJson(['success' => true, 'data' => $resource]);
        } else {
            $stmt = $pdo->query("SELECT * FROM resources ORDER BY id DESC");
            $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            respondJson(['success' => true, 'count' => count($resources), 'data' => $resources]);
        }
    } catch (Exception $e) {
        respondJson(['success' => false, 'error' => 'Failed to fetch resources: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------------------
// 5. Shared Utility Helpers
// -------------------------------------------------------------------------

/**
 * Sends a standardized JSON response
 */
function respondJson($data, int $statusCode = 200): void
{
    if (function_exists('sendSuccess') && isset($data['success']) && $data['success'] && $statusCode === 200) {
        sendSuccess($data['data'] ?? $data, $data['message'] ?? null, $statusCode);
        return;
    }
    if (function_exists('sendError') && isset($data['success']) && !$data['success']) {
        sendError($data['error'] ?? 'An error occurred', $statusCode);
        return;
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Parses request payload regardless of content type (Form-Data or JSON)
 */
function getPayload(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_starts_with($contentType, 'multipart/form-data')) {
        return $_POST;
    }
    if (function_exists('getJsonBody')) {
        return getJsonBody() ?: [];
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}