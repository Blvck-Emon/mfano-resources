<?php
// Backend+AdminPanel/database/migrate_add_resource_status_logs.php
// Adds 'status' column to resources (if missing) and creates resource_status_logs table.

declare(strict_types=1);
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) header('Content-Type: application/json; charset=utf-8');

$root = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
$configFile = $root . '/config/config.php';
if (file_exists($configFile)) {
    $config = require $configFile;
    $dbPath = $config['db']['path'] ?? ($root . '/database/mfano_bora.sqlite');
} else {
    $dbPath = __DIR__ . '/mfano_bora.sqlite';
}

if (!file_exists($dbPath)) {
    $msg = "Database file not found at: {$dbPath}";
    if ($isCli) { fwrite(STDERR, $msg . PHP_EOL); exit(1); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1) Add status column to resources if missing
    $cols = $pdo->query("PRAGMA table_info('resources')")->fetchAll(PDO::FETCH_ASSOC);
    $hasStatus = false;
    foreach ($cols as $c) {
        if (isset($c['name']) && $c['name'] === 'status') { $hasStatus = true; break; }
    }
    if (!$hasStatus) {
        // default 'pending'
        $pdo->exec("ALTER TABLE resources ADD COLUMN status TEXT DEFAULT 'pending'");
        $message1 = "Added 'status' column to resources.";
    } else {
        $message1 = "'status' column already exists in resources.";
    }

    // 2) Create resource_status_logs table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resource_status_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            resource_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            note TEXT,
            actor TEXT,
            created_at TEXT NOT NULL DEFAULT (STRFTIME('%Y-%m-%dT%H:%M:%fZ','now')),
            FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
        );
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_resource_status_logs_resource ON resource_status_logs(resource_id)");
    $message2 = "resource_status_logs table ensured (created if missing).";

    if ($isCli) {
        echo $message1 . " " . $message2 . PHP_EOL;
        exit(0);
    } else {
        echo json_encode(['success' => true, 'message' => $message1 . ' ' . $message2]);
        exit;
    }
} catch (Exception $e) {
    $err = 'Migration failed: ' . $e->getMessage();
    if ($isCli) { fwrite(STDERR, $err . PHP_EOL); exit(1); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}