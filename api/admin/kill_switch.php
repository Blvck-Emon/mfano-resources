<?php
// api/admin/kill_switch.php
// POST only. JSON body: {"confirm":"YES_I_CONFIRM_DELETE_ALL"}
// Header: X-Api-Key: <admin key>

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Read incoming headers robustly and normalize to lower-case keys.
 * getallheaders() may present header names in different cases depending
 * on SAPI / server environment, so normalise them.
 */
function get_normalized_headers(): array {
    $raw = [];
    if (function_exists('getallheaders')) {
        $raw = getallheaders();
    } else {
        // fallback for some SAPIs
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', str_replace('_', ' ', substr($k, 5)));
                $raw[$name] = $v;
            }
        }
    }

    $out = [];
    foreach ($raw as $k => $v) {
        $out[strtolower($k)] = $v;
    }
    return $out;
}

function send_json($data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    send_json(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$headers = get_normalized_headers();
$apiKey = $headers['x-api-key'] ?? null;

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    send_json(['success' => false, 'error' => 'Server misconfigured: config missing.'], 500);
}
$config = require $configPath;
$expectedKey = $config['admin_api_key'] ?? null;

if (!$expectedKey || !is_string($expectedKey)) {
    send_json(['success' => false, 'error' => 'Server misconfigured: admin key not set.'], 500);
}

if (!$apiKey || !hash_equals((string)$expectedKey, (string)$apiKey)) {
    send_json(['success' => false, 'error' => 'Unauthorized: invalid admin API key.'], 401);
}

// read JSON body
$body = file_get_contents('php://input');
$payload = json_decode($body ?: '{}', true);
if (!is_array($payload)) $payload = [];

// expected confirmation token
$expectedConfirmation = 'YES_I_CONFIRM_DELETE_ALL';
$provided = isset($payload['confirm']) ? trim((string)$payload['confirm']) : '';

// If client used form-encoded body rather than JSON, allow fallback parameter
if ($provided === '' && isset($_POST['confirm'])) {
    $provided = trim((string)$_POST['confirm']);
}

if ($provided !== $expectedConfirmation) {
    send_json([
        'success' => false,
        'error' => 'Missing or incorrect confirmation token. Provide JSON: { "confirm": "YES_I_CONFIRM_DELETE_ALL" }'
    ], 422);
}

try {
    $pdo = getDbConnection();
    // wrap in transaction for safety
    $pdo->beginTransaction();

    // gather local files to remove (storage_type = 'local')
    $stmt = $pdo->query("SELECT stored_path FROM resources WHERE storage_type = 'local' AND stored_path IS NOT NULL");
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // delete rows in order that respects FK constraints
    $pdo->exec('DELETE FROM download_logs;');
    $pdo->exec('DELETE FROM resources;');
    $pdo->exec('DELETE FROM sub_categories;');
    $pdo->exec('DELETE FROM categories;');

    // persist DB changes
    $pdo->commit();

    // attempt file deletion after DB commit (non-critical)
    $deleted = 0;
    foreach ($files as $rel) {
        if (!is_string($rel) || $rel === '') continue;
        // If stored_path is absolute, respect it; otherwise assume project root relative
        $candidate = $rel;
        if (!preg_match('#^(/|[A-Za-z]:\\\\)#', $candidate)) {
            $candidate = realpath(__DIR__ . '/../../' . ltrim($rel, '/')) ?: (__DIR__ . '/../../' . ltrim($rel, '/'));
        }
        if (is_file($candidate)) {
            @unlink($candidate);
            $deleted++;
        }
    }

    // optional VACUUM to reduce file size (best-effort)
    try { $pdo->exec('VACUUM;'); } catch (Throwable $e) { /* ignore vacuum problems */ }

    send_json([
        'success' => true,
        'message' => 'All sample data removed. Local stored files deleted where applicable.',
        'files_deleted' => $deleted
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Kill switch failure: ' . $e->getMessage());
    send_json(['success' => false, 'error' => 'Server failed to wipe the database: ' . $e->getMessage()], 500);
}