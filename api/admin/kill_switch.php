<?php
// api/admin/kill_switch.php
// POST only. JSON body: {"confirm":"YES_I_CONFIRM_DELETE_ALL"}
// Header: X-Api-Key: <admin key>

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

function sendError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    sendError('Method not allowed', 405);
}

// auth
$headers = getallheaders();
$apiKey = $headers['X-Api-Key'] ?? $headers['X-API-KEY'] ?? null;
$config = require __DIR__ . '/../../config/config.php';
$expected = $config['admin_api_key'] ?? null;

if (!$apiKey || !$expected || !hash_equals((string)$expected, (string)$apiKey)) {
    sendError('Unauthorized', 401);
}

// parse JSON body (safe)
$body = file_get_contents('php://input');
$payload = json_decode($body, true) ?? [];

if (!isset($payload['confirm']) || $payload['confirm'] !== 'YES_I_CONFIRM_DELETE_ALL') {
    // explicit failure message encouraging safe use
    sendError('Missing or incorrect confirmation token. To perform a full wipe POST JSON: {"confirm":"YES_I_CONFIRM_DELETE_ALL"}', 422);
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // remove local files for any resources where storage_type = 'local'
    $stmt = $pdo->query("SELECT stored_path FROM resources WHERE storage_type = 'local' AND stored_path IS NOT NULL");
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($files as $relPath) {
        // normalize and unlink (project root is two levels up from this script)
        $abs = realpath(__DIR__ . '/../../' . ltrim($relPath, '/'));
        if ($abs && is_file($abs)) {
            @unlink($abs);
        }
    }

    // Clear tables in correct order to satisfy FK constraints
    $pdo->exec('DELETE FROM download_logs;');
    $pdo->exec('DELETE FROM resources;');
    $pdo->exec('DELETE FROM sub_categories;');
    $pdo->exec('DELETE FROM categories;');

    // Vacuum to clean database file (optional)
    $pdo->exec('VACUUM;');

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'All sample data removed. Local stored files deleted where applicable.']);
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Kill switch failed: ' . $e->getMessage());
    sendError('Server failed to wipe the database', 500);
}