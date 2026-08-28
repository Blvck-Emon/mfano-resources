<?php
/**
 * includes/auth.php
 *
 * Gate for every script under /api/admin/. Still independent of the
 * database layer for the core check, but now ALSO rate-limits repeated
 * failed attempts per IP using the admin_auth_attempts table, so the
 * single shared admin_api_key can't be brute-forced by hammering the
 * endpoint (there was previously no limit on attempts at all).
 *
 * Config knobs (see config/config.example.php):
 *   security.max_failed_attempts  (default 8)
 *   security.lockout_minutes      (default 15)
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

    $ip = getClientIp() ?? 'unknown';
    $maxAttempts = (int) ($config['security']['max_failed_attempts'] ?? 8);
    $lockoutMinutes = (int) ($config['security']['lockout_minutes'] ?? 15);

    enforceAdminRateLimit($ip, $maxAttempts, $lockoutMinutes);

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

    $isValid = $providedKey && hash_equals($expectedKey, $providedKey);
    recordAdminAuthAttempt($ip, $isValid);

    if (!$isValid) {
        sendError('Unauthorized: invalid or missing API key.', 401);
    }
}

/** Blocks the request early if this IP has too many recent failures. */
function enforceAdminRateLimit(string $ip, int $maxAttempts, int $lockoutMinutes): void
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS n
            FROM admin_auth_attempts
            WHERE ip_address = :ip
              AND success = 0
              AND attempted_at >= STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now', :window)
        ");
        $stmt->execute([
            'ip'     => $ip,
            'window' => '-' . $lockoutMinutes . ' minutes',
        ]);
        $failures = (int) $stmt->fetch()['n'];

        if ($failures >= $maxAttempts) {
            sendError(
                "Too many failed admin authentication attempts. Try again in about {$lockoutMinutes} minutes.",
                429
            );
        }
    } catch (Throwable $e) {
        // Rate limiting must never itself take the admin API down; log and continue.
        error_log('Admin rate-limit check failed: ' . $e->getMessage());
    }
}

/** Records every attempt (success or failure) for the sliding-window check above. */
function recordAdminAuthAttempt(string $ip, bool $success): void
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('INSERT INTO admin_auth_attempts (ip_address, success) VALUES (:ip, :success)');
        $stmt->execute(['ip' => $ip, 'success' => $success ? 1 : 0]);
    } catch (Throwable $e) {
        error_log('Failed to record admin auth attempt: ' . $e->getMessage());
    }
}