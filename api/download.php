<?php
/**
 * api/download.php  (NEW)
 *
 * GET /api/download.php?id=5
 *
 * The single link the website and admin panel should use for every
 * "Download" button, instead of pointing straight at r.file_url. It:
 *   1. Looks the resource up (must be published).
 *   2. Writes one row to download_logs (ip, user agent, referrer, time).
 *   3. Either streams the file directly (storage_type = 'local', i.e.
 *      uploaded through the admin panel) or 302-redirects to the external
 *      URL (storage_type = 'external', e.g. an existing S3/Cloudinary link).
 *
 * Doing the logging and the serving in the same request guarantees every
 * real download gets logged exactly once — a plain <a href="file_url">
 * link can never be logged server-side, and a separate "ping-then-open"
 * call (see trackDownload() in resources.php) can be skipped by anyone
 * who requests file_url directly.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

applyCors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if (!$id) {
    sendError('id is required', 400);
}

$pdo = getDbConnection();

try {
    $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = :id AND is_published = 1');
    $stmt->execute(['id' => $id]);
    $resource = $stmt->fetch();

    if (!$resource) {
        sendError('Resource not found', 404);
    }

    $log = $pdo->prepare(
        'INSERT INTO download_logs (resource_id, ip_address, user_agent, referrer)
         VALUES (:resource_id, :ip_address, :user_agent, :referrer)'
    );
    $log->execute([
        'resource_id' => $id,
        'ip_address'  => getClientIp(),
        'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'referrer'    => $_SERVER['HTTP_REFERER'] ?? null,
    ]);

    if ($resource['storage_type'] === 'local' && $resource['stored_path']) {
        streamLocalFile($resource);
    } else {
        header('Location: ' . $resource['file_url'], true, 302);
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendError('Server Error');
}

function streamLocalFile(array $resource): void
{
    $absolutePath = __DIR__ . '/../' . ltrim($resource['stored_path'], '/');

    if (!is_file($absolutePath)) {
        error_log('Missing local file on disk: ' . $absolutePath);
        sendError('File is no longer available on the server.', 410);
    }

    $downloadName = preg_replace('/[^A-Za-z0-9\-_. ]/', '', $resource['title']) . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($absolutePath));
    header('X-Content-Type-Options: nosniff');
    readfile($absolutePath);
    exit;
}