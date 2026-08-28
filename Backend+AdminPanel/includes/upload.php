<?php
/**
 * Backend+AdminPanel/includes/upload.php
 * Combined Authoritative Uploader and Status Logger Endpoint
 */

declare(strict_types=1);

// -------------------------------------------------------------------------
// 1. Headers, CORS & HTTP Method Validation
// -------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, X-Admin-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// -------------------------------------------------------------------------
// 2. Configuration & Database Initialization
// -------------------------------------------------------------------------
$root = realpath(__DIR__ . '/..') ?: realpath(__DIR__ . '/../..');
$configFile = $root . '/config/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server misconfigured: config missing']);
    exit();
}

$config = require $configFile;
$dbPath = $config['db']['path'] ?? ($root . '/database/mfano_bora.sqlite');

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed', 'error' => $e->getMessage()]);
    exit();
}

// -------------------------------------------------------------------------
// 3. Input Parsing & Basic Validation
// -------------------------------------------------------------------------
$resourceId    = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;
$title         = trim($_POST['title'] ?? '');
$description   = trim($_POST['description'] ?? '');
$subCategoryId = isset($_POST['sub_category_id']) && is_numeric($_POST['sub_category_id']) ? intval($_POST['sub_category_id']) : null;

if ($title === '' && $resourceId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required for a new resource']);
    exit();
}

if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid file uploaded (error code ' . $errCode . ')']);
    exit();
}

$file = $_FILES['file'];

// -------------------------------------------------------------------------
// 4. File Security, Size, & MIME Validation
// -------------------------------------------------------------------------
$maxBytes = $config['uploads']['max_bytes'] ?? (25 * 1024 * 1024); // Default 25MB limit
if ($file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File exceeds the ' . round($maxBytes / 1024 / 1024) . 'MB size limit.']);
    exit();
}

// Inspect actual file content using finfo to prevent spoofed MIME types
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']);
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Enforce PDF validation (or adjust if other document types are supported)
if ($detectedMime !== 'application/pdf' || $extension !== 'pdf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Only valid PDF files are accepted.']);
    exit();
}

$uploadsDir = $config['uploads']['dir'] ?? ($root . '/uploads/resources');
$urlPrefix  = rtrim($config['uploads']['url_prefix'] ?? '/uploads/resources', '/');

if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server could not prepare storage directory for upload.']);
    exit();
}

// Generate secure random filename to avoid collisions and path traversal attacks
$storedName = bin2hex(random_bytes(16)) . '.pdf';
$targetPath = rtrim($uploadsDir, '/') . '/' . $storedName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file to storage destination.']);
    exit();
}

// Compute file metadata
$checksum      = hash_file('sha256', $targetPath);
$filesizeKb    = max(1, intval(round($file['size'] / 1024)));
$storedPathRel = 'uploads/resources/' . $storedName;
$fileUrl       = $urlPrefix . '/' . $storedName;

// -------------------------------------------------------------------------
// 5. Database Transaction (Insert/Update Resource & Log Status)
// -------------------------------------------------------------------------
try {
    $pdo->beginTransaction();

    if ($resourceId > 0) {
        // Update existing resource record
        $upd = $pdo->prepare('
            UPDATE resources 
            SET title = :title, 
                description = :desc, 
                sub_category_id = :sub, 
                storage_type = :stype, 
                stored_path = :spath, 
                file_url = :furl,
                checksum_sha256 = :cs, 
                file_size_kb = :fs, 
                updated_at = STRFTIME(\'%Y-%m-%dT%H:%M:%fZ\', \'now\') 
            WHERE id = :id
        ');
        $upd->execute([
            ':title' => $title ?: null,
            ':desc'  => $description ?: null,
            ':sub'   => $subCategoryId,
            ':stype' => 'local',
            ':spath' => $storedPathRel,
            ':furl'  => $fileUrl,
            ':cs'    => $checksum,
            ':fs'    => $filesizeKb,
            ':id'    => $resourceId
        ]);
        $savedId = $resourceId;
        $action = 'updated';
    } else {
        // Insert new resource record with initial status 'uploaded'
        $ins = $pdo->prepare('
            INSERT INTO resources (
                title, description, sub_category_id, storage_type, stored_path, 
                file_url, checksum_sha256, file_size_kb, download_count, 
                is_featured, is_published, status, created_at, updated_at
            ) VALUES (
                :title, :desc, :sub, :stype, :spath, :furl, :cs, :fs, 0, 
                0, 0, \'uploaded\', STRFTIME(\'%Y-%m-%dT%H:%M:%fZ\', \'now\'), 
                STRFTIME(\'%Y-%m-%dT%H:%M:%fZ\', \'now\')
            )
        ');
        $ins->execute([
            ':title' => $title,
            ':desc'  => $description,
            ':sub'   => $subCategoryId,
            ':stype' => 'local',
            ':spath' => $storedPathRel,
            ':furl'  => $fileUrl,
            ':cs'    => $checksum,
            ':fs'    => $filesizeKb
        ]);
        $savedId = intval($pdo->lastInsertId());
        $action = 'uploaded';
    }

    // Insert corresponding status audit log entry
    $slog = $pdo->prepare('
        INSERT INTO resource_status_logs (resource_id, status, note, actor, created_at) 
        VALUES (:rid, :status, :note, :actor, STRFTIME(\'%Y-%m-%dT%H:%M:%fZ\', \'now\'))
    ');
    $note  = "File uploaded securely: " . $storedName;
    $actor = $_SERVER['REMOTE_USER'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'system');
    
    $slog->execute([
        ':rid'    => $savedId,
        ':status' => ($action === 'uploaded' ? 'uploaded' : 'updated'),
        ':note'   => $note,
        ':actor'  => $actor
    ]);

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Clean up orphaned file if database transaction fails
    if (file_exists($targetPath)) {
        @unlink($targetPath);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error during save', 'error' => $e->getMessage()]);
    exit();
}

// -------------------------------------------------------------------------
// 6. Return Fully Joined Resource Record for Client UI Refresh
// -------------------------------------------------------------------------
$stmt = $pdo->prepare('
    SELECT r.*, sc.name AS subcategory_name, c.name AS category_name 
    FROM resources r 
    LEFT JOIN sub_categories sc ON r.sub_category_id = sc.id 
    LEFT JOIN categories c ON sc.category_id = c.id 
    WHERE r.id = :id 
    LIMIT 1
');
$stmt->execute([':id' => $savedId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success'  => true,
    'message'  => ucfirst($action) . ' successfully',
    'resource' => $row,
    'action'   => $action
]);
exit();