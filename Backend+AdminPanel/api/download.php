<?php
/**
 * Backend+AdminPanel/api/download.php
 * Public Download & View API Endpoint with Multi-Path Resolution, Audit Logging, & Counter Increments
 */

require_once __DIR__ . '/../config/db.php';

// Dynamically load helpers if available in ecosystem
if (file_exists(__DIR__ . '/../includes/helpers.php')) {
    require_once __DIR__ . '/../includes/helpers.php';
}

// -------------------------------------------------------------------------
// 1. CORS & Preflight Handling
// -------------------------------------------------------------------------
if (function_exists('applyCors')) {
    applyCors();
} else {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Api-Key, X-Admin-Api-Key');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondDownloadError('Method not allowed', 405);
}

// -------------------------------------------------------------------------
// 2. Parameter Parsing & Request Validation
// -------------------------------------------------------------------------
$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$disposition = filter_input(INPUT_GET, 'disposition', FILTER_DEFAULT) ?? 'inline'; // 'inline' (view) or 'attachment' (download)

if (!$resourceId) {
    respondDownloadError('Invalid or missing resource ID.', 400);
}

try {
    $db = function_exists('getDbConnection') ? getDbConnection() : ($pdo ?? null);
    if (!$db && isset($GLOBALS['pdo'])) {
        $db = $GLOBALS['pdo'];
    }

    // 1. Fetch resource record
    $stmt = $db->prepare('SELECT * FROM resources WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        respondDownloadError('Resource requested was not found.', 404);
    }

    // Check publication status (allow override if admin key is present)
    $hasAdminKey = !empty($_SERVER['HTTP_X_API_KEY']) || !empty($_SERVER['HTTP_X_ADMIN_API_KEY']);
    if (isset($resource['is_published']) && (int)$resource['is_published'] === 0 && !$hasAdminKey) {
        respondDownloadError('Resource is not currently published.', 403);
    }

    $storageType = $resource['storage_type'] ?? 'local';
    $filePath    = $resource['file_path'] ?? '';
    $storedPath  = $resource['stored_path'] ?? '';
    $fileUrl     = $resource['file_url'] ?? '';

    // Primary target path string from either schema column
    $primaryPath = !empty($filePath) ? $filePath : $storedPath;

    // 2. Handle External / URL Storage
    if ($storageType === 'external' || $storageType === 'url' || (empty($primaryPath) && !empty($fileUrl))) {
        recordDownloadLog($db, $resourceId);
        incrementDownloadCount($db, $resourceId);
        
        header('Location: ' . $fileUrl, true, 302);
        exit();
    }

    // 3. Robust Multi-Path Resolution Matrix for Local Files
    $resolvedPath = resolveLocalFilePath($primaryPath, $fileUrl);

    if (!$resolvedPath) {
        // Fallback to external redirect if local file missing on disk but URL exists
        if (!empty($fileUrl)) {
            recordDownloadLog($db, $resourceId);
            incrementDownloadCount($db, $resourceId);
            header('Location: ' . $fileUrl, true, 302);
            exit();
        }

        respondDownloadError('File is no longer available on the server.', 410, ['debug_searched_path' => $primaryPath]);
    }

    // 4. Record Audit Log & Increment Counters
    recordDownloadLog($db, $resourceId);
    incrementDownloadCount($db, $resourceId);

    // 5. Construct Optimized Filename
    $cleanTitle = preg_replace('/[^A-Za-z0-9\-_. ]/', '', $resource['title'] ?? '');
    if (!empty($cleanTitle)) {
        $downloadName = trim($cleanTitle) . '.pdf';
    } else {
        $downloadName = basename($resolvedPath);
    }

    $mimeType = mime_content_type($resolvedPath) ?: 'application/pdf';

    // Clear output buffer to prevent stream corruption
    if (ob_get_level()) {
        ob_end_clean();
    }

    // 6. Serve Binary File Stream
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: ' . ($disposition === 'attachment' ? 'attachment' : 'inline') . '; filename="' . rawurlencode($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Content-Length: ' . filesize($resolvedPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');

    readfile($resolvedPath);
    exit();

} catch (Exception $e) {
    error_log('Download API error: ' . $e->getMessage());
    respondDownloadError('Server error processing file stream: ' . $e->getMessage(), 500);
}

// -------------------------------------------------------------------------
// Helper Functions
// -------------------------------------------------------------------------

/**
 * Searches across multiple prospective directory paths to locate the file on disk.
 */
function resolveLocalFilePath(string $primaryPath, string $fileUrl = ''): ?string
{
    $candidates = array_filter([
        $primaryPath,
        !empty($fileUrl) && !str_starts_with($fileUrl, 'http') ? $fileUrl : null,
        __DIR__ . '/' . $primaryPath,
        __DIR__ . '/../' . ltrim($primaryPath, '/'),
        __DIR__ . '/../../' . ltrim($primaryPath, '/'),
        __DIR__ . '/../uploads/' . basename($primaryPath),
        __DIR__ . '/../uploads/resources/' . basename($primaryPath),
        __DIR__ . '/../../uploads/' . basename($primaryPath),
        __DIR__ . '/../../uploads/resources/' . basename($primaryPath),
        isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($primaryPath, '/') : null
    ]);

    foreach ($candidates as $candidate) {
        if (!empty($candidate) && file_exists($candidate) && is_file($candidate)) {
            return realpath($candidate);
        }
    }

    return null;
}

/**
 * Records an entry into download_logs table safely without interrupting streaming.
 */
function recordDownloadLog(PDO $db, int $resourceId): void
{
    try {
        $ipAddress = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent';
        $referrer  = $_SERVER['HTTP_REFERER'] ?? 'Direct Access';

        $stmt = $db->prepare("
            INSERT INTO download_logs (resource_id, ip_address, user_agent, referrer, created_at, downloaded_at)
            VALUES (:resource_id, :ip_address, :user_agent, :referrer, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':resource_id' => $resourceId,
            ':ip_address'   => $ipAddress,
            ':user_agent'   => mb_strimwidth($userAgent, 0, 255, ''),
            ':referrer'     => mb_strimwidth($referrer, 0, 255, '')
        ]);
    } catch (Exception $ex) {
        error_log("Failed to log download activity: " . $ex->getMessage());
    }
}

/**
 * Increments resource download counter (safe fallback for environments without DB triggers).
 */
function incrementDownloadCount(PDO $db, int $resourceId): void
{
    try {
        $stmt = $db->prepare("
            UPDATE resources 
            SET download_count = COALESCE(download_count, 0) + 1 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $resourceId]);
    } catch (Exception $ex) {
        error_log("Failed to increment download count: " . $ex->getMessage());
    }
}

/**
 * Standardized error output handler.
 */
function respondDownloadError(string $message, int $statusCode = 400, array $extra = []): void
{
    if (function_exists('sendError')) {
        sendError($message, $statusCode);
        return;
    }

    http_response_code($statusCode);
    header('Content-Type: application/json');
    $payload = array_merge(['success' => false, 'error' => $message], $extra);
    echo json_encode($payload);
    exit();
}