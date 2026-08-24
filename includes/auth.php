<?php
/**
 * includes/auth.php
 *
 * Equivalent of the Node middleware/auth.js `requireAdminKey` function.
 * Call requireAdminKey() at the very top of any script under /api/admin/
 * before touching the database.
 */

require_once __DIR__ . '/helpers.php';

function requireAdminKey(): void
{
    $config = require __DIR__ . '/../config/config.php';
    $expectedKey = $config['admin_api_key'] ?? null;

    if (!$expectedKey || $expectedKey === 'replace-with-a-long-random-string') {
        error_log('ADMIN_API_KEY is not configured in config/config.php');
        sendError('Server misconfiguration: admin key not set.', 500);
    }

    // Headers arrive as HTTP_X_API_KEY under PHP's built-in server / most
    // Apache setups; getallheaders() covers the rest.
    $providedKey = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'x-api-key') {
                $providedKey = $value;
                break;
            }
        }
    }
    if ($providedKey === null && isset($_SERVER['HTTP_X_API_KEY'])) {
        $providedKey = $_SERVER['HTTP_X_API_KEY'];
    }

    if (!$providedKey || !hash_equals($expectedKey, $providedKey)) {
        sendError('Unauthorized: invalid or missing API key.', 401);
    }
}
