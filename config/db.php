<?php
/**
 * config/db.php
 *
 * Returns a shared PDO connection to SQLite (was: Postgres via pgsql DSN).
 * Every endpoint requires this file instead of opening its own connection.
 *
 * On first run (database file does not exist yet) this automatically
 * applies database/schema.sql and database/seed.sql, so a fresh checkout
 * "just works" with `php -S localhost:8000` and no separate DB server
 * or install step.
 */

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $dbPath = $config['db']['path'];
    $isFreshDb = !file_exists($dbPath);

    try {
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        if ($isFreshDb) {
            bootstrapDatabase($pdo);
        }
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    return $pdo;
}

/** Applies schema.sql then seed.sql against a brand-new SQLite file. */
function bootstrapDatabase(PDO $pdo): void
{
    $schemaPath = __DIR__ . '/../database/schema.sql';
    $seedPath   = __DIR__ . '/../database/seed.sql';

    if (file_exists($schemaPath)) {
        $pdo->exec(file_get_contents($schemaPath));
    }
    if (file_exists($seedPath)) {
        $pdo->exec(file_get_contents($seedPath));
    }
}