<?php
// api/admin/kill_switch.php
// POST only. JSON body: {"confirm":"YES_I_CONFIRM_DELETE_ALL"}
// Header: X-Api-Key: <admin key>
//
// HARDENED:
//   - Now goes through the same requireAdminKey() gate as every other
//     admin endpoint, which means it benefits from the new IP rate
//     limiting in includes/auth.php instead of re-implementing its own
//     (weaker, unlimited-attempts) key check.
//   - Refuses to run at all unless config/config.php explicitly sets
//     security.kill_switch_enabled = true. This is a deliberately
//     irreversible action wiping every category, sub-category, resource
//     and download log, so it should be OFF by default in any
//     environment that isn't a disposable dev/demo instance.

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

applyCors();
requireAdminKey();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

$config = require __DIR__ . '/../../config/config.php';
$killSwitchEnabled = (bool) ($config['security']['kill_switch_enabled'] ?? false);

if (!$killSwitchEnabled) {
    sendError(
        'The kill switch is disabled on this deployment. Set security.kill_switch_enabled = true in config/config.php to allow it.',
        403
    );
}

// read JSON body
$body = file_get_contents('php://input');
$payload = json_decode($body ?: '{}', true);
if (!is_array($payload)) $payload = [];

// expected confirmation token
$expectedConfirmation = 'YES_I_CONFIRM_DELETE_ALL';
$provided = isset($payload['confirm']) ? trim((string) $payload['confirm']) : '';

// If client used form-encoded body rather than JSON, allow fallback parameter
if ($provided === '' && isset($_POST['confirm'])) {
    $provided = trim((string) $_POST['confirm']);
}

if ($provided !== $expectedConfirmation) {
    sendError('Missing or incorrect confirmation token. Provide JSON: { "confirm": "YES_I_CONFIRM_DELETE_ALL" }', 422);
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // gather local files to remove (storage_type = 'local')
    $stmt = $pdo->query("SELECT stored_path FROM resources WHERE storage_type = 'local' AND stored_path IS NOT NULL");
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // delete rows in order that respects FK constraints
    $pdo->exec('DELETE FROM download_logs;');
    $pdo->exec('DELETE FROM resources;');
    $pdo->exec('DELETE FROM sub_categories;');
    $pdo->exec('DELETE FROM categories;');

    $pdo->commit();

    error_log(sprintf(
        '[KILL SWITCH] Executed by IP %s at %s — %d local files queued for deletion.',
        getClientIp() ?? 'unknown',
        date('c'),
        count($files)
    ));

    $deleted = 0;
    foreach ($files as $rel) {
        if (!is_string($rel) || $rel === '') continue;
        $candidate = $rel;
        if (!preg_match('#^(/|[A-Za-z]:\\\\)#', $candidate)) {
            $candidate = realpath(__DIR__ . '/../../' . ltrim($rel, '/')) ?: (__DIR__ . '/../../' . ltrim($rel, '/'));
        }
        if (is_file($candidate)) {
            @unlink($candidate);
            $deleted++;
        }
    }

    try { $pdo->exec('VACUUM;'); } catch (Throwable $e) { /* ignore vacuum problems */ }

    sendJson([
        'success' => true,
        'message' => 'All sample data removed. Local stored files deleted where applicable.',
        'files_deleted' => $deleted,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Kill switch failure: ' . $e->getMessage());
    sendError('Server failed to wipe the database: ' . $e->getMessage(), 500);
}