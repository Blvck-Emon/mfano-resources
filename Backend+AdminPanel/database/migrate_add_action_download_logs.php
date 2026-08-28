<?php
/**
 * Backend+AdminPanel/database/migrate_add_action_download_logs.php
 * Safe & Conservative Database Migration
 * Adds the 'action' column to download_logs (if missing) and creates indexing.
 * Supports execution via both CLI command line and Web Browser requests.
 */

declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

// -------------------------------------------------------------------------
// 1. Path Resolution & Configuration Loading
// -------------------------------------------------------------------------
$root = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
$configFile = $root . '/config/config.php';

if (file_exists($configFile)) {
    $config = require $configFile;
    $dbPath = $config['db']['path'] ?? ($root . '/database/mfano_bora.sqlite');
} else {
    $dbPath = __DIR__ . '/mfano_bora.sqlite';
}

if (!file_exists($dbPath)) {
    if ($isCli) {
        fwrite(STDERR, "Error: Database file not found at: {$dbPath}\n");
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Database file not found at: {$dbPath}"]);
        exit();
    }
}

// -------------------------------------------------------------------------
// 2. Migration Execution
// -------------------------------------------------------------------------
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Inspect existing columns in download_logs
    $stmt = $pdo->query("PRAGMA table_info('download_logs')");
    $columnsAll = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasAction = false;
    foreach ($columnsAll as $col) {
        if (isset($col['name']) && $col['name'] === 'action') {
            $hasAction = true;
            break;
        }
    }

    if (!$hasAction) {
        // Alter table to add action column with default 'download'
        $pdo->exec("ALTER TABLE download_logs ADD COLUMN action TEXT DEFAULT 'download'");
        $message = "Migration successful: 'action' column added to download_logs.";
    } else {
        $message = "Migration skipped: 'action' column already exists in download_logs.";
    }

    // Performance indexing for log aggregation queries
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_download_logs_resource ON download_logs(resource_id)");

    // Dispatch output based on environment
    if ($isCli) {
        echo $message . "\n";
        exit(0);
    } else {
        echo json_encode(['success' => true, 'message' => $message]);
        exit();
    }

} catch (Exception $e) {
    $errorMsg = 'Migration failed: ' . $e->getMessage();
    
    if ($isCli) {
        fwrite(STDERR, $errorMsg . "\n");
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit();
    }
}