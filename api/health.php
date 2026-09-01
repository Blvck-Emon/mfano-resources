<?php
/**
 * api/health.php
 *
 * GET /api/health.php - uptime/config check for QA and deployment.
 * Extended to also confirm the SQLite file is reachable, since there's
 * no separate DB server process to check the way there was with Postgres.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

applyCors();

try {
    $pdo = getDbConnection();
    $resourceCount = (int) $pdo->query('SELECT COUNT(*) AS n FROM resources')->fetch()['n'];

    sendJson([
        'success'   => true,
        'message'   => 'Mfano Bora Resources API is running.',
        'database'  => 'sqlite',
        'resources' => $resourceCount,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendJson(['success' => false, 'message' => 'API is running but the database is unreachable.'], 500);
}