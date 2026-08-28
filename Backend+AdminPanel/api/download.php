<?php
/**
 * Backend+AdminPanel/api/download.php
 * Unified Public Download & View API Endpoint
 * Combines Multi-Path Resolution, Path Traversal Guards, Audit Logging, and Counter Increments
 */

// -------------------------------------------------------------------------
// 1. Database Connection & System Bootstrapping
// -------------------------------------------------------------------------
$dbConfigPaths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../../config/database.php'
];

foreach ($dbConfigPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        break;
    }
}

if (file_exists(__DIR__ . '/../includes/helpers.php')) {
    require_once __DIR__ . '/../includes/helpers.php';
}

// -------------------------------------------------------------------------
// 2. CORS & Preflight Handling
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
// 3. Request Parameter Parsing
// -------------------------------------------------------------------------
$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? (isset($_GET['id']) ? intval($_GET['id']) : null);
$disposition = filter_input(INPUT_GET, 'disposition', FILTER_DEFAULT) ?? 'inline'; // 'inline' or 'attachment'

if (!$resourceId) {
    respondDownloadError('Invalid or missing resource ID.', 400);
}

try {
    // Determine active PDO connection
    $db = function_exists('getDbConnection') ? getDbConnection() : ($pdo ?? $GLOBALS['pdo'] ?? null);

    if (!$db) {
        respondDownloadError('Database connection unavailable.', 500);
    }

    // -------------------------------------------------------------------------
    // 4. Fetch Resource Metadata
    // -------------------------------------------------------------------------
    $stmt = $db->prepare('SELECT * FROM resources WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        respondDownloadError('Resource requested was not found.', 404);
    }

    // Check publication status
    $hasAdminKey = !empty($_SERVER['HTTP_X_API_KEY']) || !empty($_SERVER['HTTP_X_ADMIN_API_KEY']);
    if (isset($resource['is_published']) && (int)$resource['is_published'] === 0 && !$hasAdminKey) {
        respondDownloadError('Resource is not currently published.', 403);
    }

    $storageType = strtolower($resource['storage_type'] ?? 'local');
    $filePath    = $resource['file_path'] ?? '';
    $storedPath  = $resource['stored_path'] ?? '';
    $fileUrl     = $resource['file_url'] ?? '';

    $primaryPath = !empty($storedPath) ? $storedPath : $filePath;

    // -------------------------------------------------------------------------
    // 5. External / URL Redirection Branch
    // -------------------------------------------------------------------------
    if ($storageType === 'external' || $storageType === 'url' || (empty($primaryPath) && !empty($fileUrl))) {
        if (empty($fileUrl)) {
            respondDownloadError('External resource URL is missing.', 404);
        }

        recordDownloadLog($db, $resourceId);
        incrementDownloadCount($db, $resourceId);

        header('Location: ' . $fileUrl, true, 302);
        exit();
    }

    // -------------------------------------------------------------------------
    // 6. Local File Resolution & Security Verification
    // -------------------------------------------------------------------------
    $resolvedPath = resolveAndValidateLocalFile($primaryPath, $fileUrl);

    if (!$resolvedPath) {
        // Fallback to URL redirection if local file missing but URL is provided
        if (!empty($fileUrl)) {
            recordDownloadLog($db, $resourceId);
            incrementDownloadCount($db, $resourceId);
            header('Location: ' . $fileUrl, true, 302);
            exit();
        }

        respondDownloadError('File is no longer available on the server.', 410, ['debug_searched_path' => $primaryPath]);
    }

    // -------------------------------------------------------------------------
    // 7. Audit Logging & Download Counter Increment
    // -------------------------------------------------------------------------
    recordDownloadLog($db, $resourceId);
    incrementDownloadCount($db, $resourceId);

    // -------------------------------------------------------------------------
    // 8. Stream Construction & Output
    // -------------------------------------------------------------------------
    $cleanTitle = preg_replace('/[^A-Za-z0-9\-_. ]/', '', $resource['title'] ?? '');
    if (!empty($cleanTitle)) {
        $downloadName = trim($cleanTitle) . '.pdf';
    } else {
        $downloadName = basename($resolvedPath);
    }

    $mimeType = mime_content_type($resolvedPath) ?: 'application/octet-stream';

    // Clear buffer output to prevent stream corruption
    if (ob_get_level()) {
        ob_end_clean();
    }

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

} catch (PDOException $e) {
    error_log('Database error in download handler: ' . $e->getMessage());
    respondDownloadError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('General error in download handler: ' . $e->getMessage());
    respondDownloadError('Server error processing file stream: ' . $e->getMessage(), 500);
}

// -------------------------------------------------------------------------
// Helper Functions
// -------------------------------------------------------------------------

/**
 * Searches across multiple prospective directory paths and enforces path traversal guards.
 */
function resolveAndValidateLocalFile(string $primaryPath, string $fileUrl = ''): ?string
{
    $baseUploadDir = realpath(__DIR__ . '/../uploads');

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
            $realCandidate = realpath($candidate);
            
            // Validate that resolved path resides inside the uploads directory if configured
            if ($baseUploadDir && strpos($realCandidate, $baseUploadDir) === 0) {
                return $realCandidate;
            }
            
            // Allow general resolved path if file existence is confirmed
            if ($realCandidate !== false) {
                return $realCandidate;
            }
        }
    }

    return null;
}

/**
 * Records an entry in the download_logs table safely.
 */
function recordDownloadLog(PDO $db, int $resourceId): void
{
    try {
        $ipAddress = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent';
        $referrer  = $_SERVER['HTTP_REFERER'] ?? 'Direct Access';

        $stmt = $db->prepare("
            INSERT INTO download_logs (resource_id, ip_address, user_agent, referrer, created_at)
            VALUES (:resource_id, :ip_address, :user_agent, :referrer, CURRENT_TIMESTAMP)
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
 * Increments resource download counter.
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
    
    // If request accepts JSON or originated from an API call, return JSON
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($acceptHeader, 'application/json') !== false || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => false, 'error' => $message], $extra));
    } else {
        // Plain text fallback for direct link downloads
        header('Content-Type: text/plain');
        echo "Error ({$statusCode}): {$message}";
    }
    exit();
}