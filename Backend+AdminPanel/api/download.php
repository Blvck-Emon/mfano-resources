<?php
/**
 * Backend+AdminPanel/api/download.php
 * Unified Public Download & View API Endpoint
 * Serves local files inline or as attachments, logs every view/download event,
 * increments download counters, and handles external redirects.
 */

declare(strict_types=1);

// -------------------------------------------------------------------------
// 1. System Setup, CORS & Configuration Loading
// -------------------------------------------------------------------------
$root = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Api-Key, X-Admin-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$configFile = $root . '/config/config.php';
$dbPath = null;

if (file_exists($configFile)) {
    $config = require $configFile;
    $dbPath = $config['db']['path'] ?? null;
}

if (!$dbPath || !file_exists($dbPath)) {
    $dbPath = $root . '/database/mfano_bora.sqlite';
}

if (!file_exists($dbPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Server misconfigured: SQLite database file missing.']);
    exit();
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'DB connection failed', 'error' => $e->getMessage()]);
    exit();
}

// -------------------------------------------------------------------------
// 2. Parameter Parsing & Validation
// -------------------------------------------------------------------------
$resourceId = isset($_GET['resource_id']) ? (int)$_GET['resource_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$fileParam  = isset($_GET['file']) ? trim($_GET['file']) : null;
$action     = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : (isset($_GET['disposition']) && $_GET['disposition'] === 'inline' ? 'view' : 'download');

if (!in_array($action, ['download', 'view'], true)) {
    $action = 'download';
}

if ($resourceId < 1 && !$fileParam) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'resource_id or valid file parameter required']);
    exit();
}

// -------------------------------------------------------------------------
// 3. Resource Database Query
// -------------------------------------------------------------------------
try {
    if ($resourceId > 0) {
        $stmt = $pdo->prepare('
            SELECT r.*, sc.name AS subcategory_name, c.name AS category_name
            FROM resources r
            LEFT JOIN sub_categories sc ON r.sub_category_id = sc.id
            LEFT JOIN categories c ON sc.category_id = c.id
            WHERE r.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $resourceId]);
    } else {
        $basename = basename($fileParam);
        $stmt = $pdo->prepare('
            SELECT r.*, sc.name AS subcategory_name, c.name AS category_name
            FROM resources r
            LEFT JOIN sub_categories sc ON r.sub_category_id = sc.id
            LEFT JOIN categories c ON sc.category_id = c.id
            WHERE r.file_url LIKE :bn OR r.stored_path LIKE :bn
            LIMIT 1
        ');
        $stmt->execute([':bn' => '%' . $basename . '%']);
    }

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    exit();
}

if (!$resource) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Resource not found']);
    exit();
}

$effectiveResourceId = (int)$resource['id'];

// Check publication status with admin key bypass option
if (isset($resource['is_published']) && (int)$resource['is_published'] === 0) {
    $hasAdminKey = !empty($_SERVER['HTTP_X_API_KEY']) || !empty($_SERVER['HTTP_X_ADMIN_API_KEY']);
    if (!$hasAdminKey) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Resource is not currently published']);
        exit();
    }
}

// -------------------------------------------------------------------------
// 4. Audit Logging & Download Counter Increment
// -------------------------------------------------------------------------
$ip        = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$userAgent = mb_strimwidth($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent', 0, 255, '');
$referrer  = mb_strimwidth($_SERVER['HTTP_REFERER'] ?? 'Direct Access', 0, 255, '');

try {
    $ins = $pdo->prepare('
        INSERT INTO download_logs (resource_id, ip_address, user_agent, referrer, action) 
        VALUES (:rid, :ip, :ua, :ref, :action)
    ');
    $ins->execute([
        ':rid'    => $effectiveResourceId,
        ':ip'     => $ip,
        ':ua'     => $userAgent,
        ':ref'    => $referrer,
        ':action' => $action
    ]);

    // Increment download/view count safely
    $pdo->prepare('UPDATE resources SET download_count = COALESCE(download_count, 0) + 1 WHERE id = :id')
        ->execute([':id' => $effectiveResourceId]);

} catch (Exception $e) {
    error_log('download.php: failed to insert download_logs or update counter: ' . $e->getMessage());
}

// -------------------------------------------------------------------------
// 5. Storage Type & Multi-Path Candidate Resolution
// -------------------------------------------------------------------------
$storageType = strtolower($resource['storage_type'] ?? 'external');
$storedPath  = $resource['stored_path'] ?? null;
$fileUrl     = $resource['file_url'] ?? '';

// Handle external storage redirect
if ($storageType === 'external' || (!empty($fileUrl) && empty($storedPath))) {
    if (!empty($fileUrl)) {
        header('Location: ' . $fileUrl, true, 302);
        exit();
    }
}

// Build candidates for local file path resolution
$candidates = array_filter([
    $storedPath,
    $root . '/' . ltrim($storedPath, '/'),
    $root . '/../' . ltrim($storedPath, '/'),
    $root . '/uploads/resources/' . basename((string)$storedPath),
    $root . '/uploads/' . basename((string)$storedPath),
    !empty($fileUrl) && !str_starts_with($fileUrl, 'http') ? $root . '/' . ltrim($fileUrl, '/') : null,
    isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim((string)$storedPath, '/') : null
]);

$foundPath = null;
foreach ($candidates as $candidate) {
    if (!$candidate) continue;
    $realPath = realpath($candidate);
    if ($realPath && is_file($realPath) && is_readable($realPath)) {
        $foundPath = $realPath;
        break;
    }
}

// Fallback to external redirect if local file is missing but valid URL exists
if (!$foundPath && !empty($fileUrl) && str_starts_with($fileUrl, 'http')) {
    header('Location: ' . $fileUrl, true, 302);
    exit();
}

if (!$foundPath) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'File not found on server']);
    exit();
}

// Auto-heal stored_path in DB if discovered via fallback candidate
try {
    if (empty($resource['stored_path']) || $resource['storage_type'] !== 'local') {
        $relativePath = str_replace($root . '/', '', $foundPath);
        $updateStmt = $pdo->prepare('UPDATE resources SET storage_type = "local", stored_path = :sp WHERE id = :id');
        $updateStmt->execute([':sp' => $relativePath, ':id' => $effectiveResourceId]);
    }
} catch (Exception $e) {
    error_log('Failed to auto-heal resource stored_path: ' . $e->getMessage());
}

// -------------------------------------------------------------------------
// 6. Binary Streaming Dispatch
// -------------------------------------------------------------------------
$cleanTitle   = preg_replace('/[^A-Za-z0-9\-_. ]/', '', $resource['title'] ?? '');
$downloadName = !empty($cleanTitle) ? trim($cleanTitle) . '.pdf' : basename($foundPath);
$mime         = mime_content_type($foundPath) ?: 'application/octet-stream';
$filesize     = filesize($foundPath);

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');

if ($action === 'view') {
    header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: public, max-age=3600');
} else {
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
}

readfile($foundPath);
exit();