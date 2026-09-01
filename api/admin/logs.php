<?php
/**
 * api/admin/logs.php  (NEW)
 *
 * GET /api/admin/logs.php                 - recent downloads + summary stats
 * GET /api/admin/logs.php?resource_id=5   - recent downloads for one resource
 * Header required: X-Api-Key: <ADMIN_API_KEY>
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

applyCors();
requireAdminKey();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$pdo = getDbConnection();
$resourceId = isset($_GET['resource_id']) ? (int) $_GET['resource_id'] : null;
$limit = min((int) ($_GET['limit'] ?? 100), 500);

try {
    $sql = "
        SELECT l.id, l.resource_id, r.title AS resource_title,
               l.ip_address, l.user_agent, l.referrer, l.downloaded_at
        FROM download_logs l
        JOIN resources r ON r.id = l.resource_id
    ";
    $params = [];
    if ($resourceId) {
        $sql .= ' WHERE l.resource_id = :resource_id';
        $params['resource_id'] = $resourceId;
    }
    $sql .= ' ORDER BY l.downloaded_at DESC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $totalRow = $pdo->query('SELECT COUNT(*) AS n FROM download_logs')->fetch();

    $last24hRow = $pdo->query(
        "SELECT COUNT(*) AS n FROM download_logs
         WHERE downloaded_at >= STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now', '-1 day')"
    )->fetch();

    $topResources = $pdo->query(
        "SELECT r.id, r.title, COUNT(l.id) AS downloads
         FROM download_logs l
         JOIN resources r ON r.id = l.resource_id
         GROUP BY r.id
         ORDER BY downloads DESC
         LIMIT 5"
    )->fetchAll();

    sendJson([
        'success'       => true,
        'logs'          => $logs,
        'total'         => (int) $totalRow['n'],
        'last_24h'      => (int) $last24hRow['n'],
        'top_resources' => $topResources,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendError('Failed to load download logs');
}