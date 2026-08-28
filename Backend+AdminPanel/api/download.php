<?php
/**
 * api/download.php
 *
 * GET /api/download.php?id=5
 *
 * Serves local PDF files directly with forced download headers or redirects
 * to external URLs while registering a single download event in download_logs.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

applyCors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resourceId) {
    sendError('Invalid or missing resource ID.', 400);
}

try {
    $db = getDbConnection();

    // 1. Fetch resource details
    $stmt = $db->prepare('SELECT * FROM resources WHERE id = :id AND is_published = 1');
    $stmt->execute([':id' => $resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        sendError('Resource not found or is not currently published.', 404);
    }

    // 2. Log download event (trg_download_logs_increment trigger updates download_count)
    $logStmt = $db->prepare("
        INSERT INTO download_logs (resource_id, ip_address, user_agent, referrer)
        VALUES (:resource_id, :ip_address, :user_agent, :referrer)
    ");
    $logStmt->execute([
        ':resource_id' => $resourceId,
        ':ip_address'   => getClientIp(),
        ':user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ':referrer'     => $_SERVER['HTTP_REFERER'] ?? ''
    ]);

    // 3. Serve local storage file or redirect to external URL
    if ($resource['storage_type'] === 'local' && !empty($resource['stored_path'])) {
        streamLocalFile($resource);
    }

    // Fallback or external link redirect
    header('Location: ' . $resource['file_url'], true, 302);
    exit;

} catch (Exception $e) {
    error_log($e->getMessage());
    sendError('Error processing file download.', 500);
}

/**
 * Validates, prepares, and streams a local file to the browser.
 */
function streamLocalFile(array $resource): void
{
    // Resolve relative or absolute path
    $storedPath = $resource['stored_path'];
    $absolutePath = (strpos($storedPath, '/') === 0 || strpos($storedPath, ':\\') !== false)
        ? realpath($storedPath)
        : realpath(__DIR__ . '/../' . ltrim($storedPath, '/'));

    if (!$absolutePath || !is_file($absolutePath)) {
        error_log('Missing local file on disk: ' . $storedPath);
        sendError('File is no longer available on the server.', 410);
    }

    // Determine download file name
    $fallbackName = !empty($resource['file_url']) ? basename($resource['file_url']) : 'document.pdf';
    $cleanTitle = preg_replace('/[^A-Za-z0-9\-_. ]/', '', $resource['title']);
    $downloadName = !empty($cleanTitle) ? trim($cleanTitle) . '.pdf' : $fallbackName;

    // Send streaming headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($absolutePath));
    header('X-Content-Type-Options: nosniff');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Clear output buffer if active to prevent corrupted files
    if (ob_get_level()) {
        ob_end_clean();
    }

    readfile($absolutePath);
    exit;
}