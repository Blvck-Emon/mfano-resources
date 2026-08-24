<?php
/**
 * includes/helpers.php
 *
 * Small shared helpers so every endpoint file stays short and consistent.
 */

function sendJson($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sendSuccess($data = null, ?int $count = null, int $statusCode = 200): void
{
    $payload = ['success' => true];
    if ($count !== null) {
        $payload['count'] = $count;
    }
    if ($data !== null) {
        $payload['data'] = $data;
    }
    sendJson($payload, $statusCode);
}

function sendError(string $message, int $statusCode = 500): void
{
    sendJson(['success' => false, 'error' => $message], $statusCode);
}

function applyCors(): void
{
    $config = require __DIR__ . '/../config/config.php';
    header('Access-Control-Allow-Origin: ' . $config['allowed_origin']);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

    // Preflight requests get an empty 200 response.
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Reads and JSON-decodes the raw request body (used for POST/PUT).
 * Returns an empty array if the body is missing or invalid.
 */
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
