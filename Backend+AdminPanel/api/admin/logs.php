<?php
/**
 * Backend+AdminPanel/api/admin/logs.php
 * Admin API Endpoint for Retrieving Download Activity Audit Logs & Analytics
 */

require_once __DIR__ . '/../../config/db.php';

// Include helper and auth files if present in ecosystem
if (file_exists(__DIR__ . '/../../includes/helpers.php')) {
    require_once __DIR__ . '/../../includes/helpers.php';
}
if (file_exists(__DIR__ . '/../../includes/auth.php')) {
    require_once __DIR__ . '/../../includes/auth.php';
}

// -------------------------------------------------------------------------
// 1. CORS & HTTP Method Validation
// -------------------------------------------------------------------------
if (function_exists('applyCors')) {
    applyCors();
} else {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, X-Admin-Api-Key');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    if (function_exists('sendError')) {
        sendError('Method not allowed', 405);
    } else {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit();
    }
}

// -------------------------------------------------------------------------
// 2. Authentication Verification
// -------------------------------------------------------------------------
if (function_exists('requireAdminKey')) {
    requireAdminKey();
} else {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? ($_GET['api_key'] ?? '');
    $validKey = getenv('ADMIN_API_KEY') ?: 'admin123';
    
    if (empty($apiKey) || $apiKey !== $validKey) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
        exit();
    }
}

// -------------------------------------------------------------------------
// 3. Data Retrieval & Analytics Engine
// -------------------------------------------------------------------------
try {
    $pdo = function_exists('getDbConnection') ? getDbConnection() : ($pdo ?? null);
    if (!$pdo && isset($db)) {
        $pdo = $db;
    }

    // Input parameters
    $resourceId = isset($_GET['resource_id']) && is_numeric($_GET['resource_id']) ? (int) $_GET['resource_id'] : null;
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 500)) : 100;

    // Build log query using LEFT JOIN to retain logs if associated resource is deleted
    $sql = "
        SELECT 
            l.id, 
            l.resource_id, 
            COALESCE(r.title, 'Deleted / Unknown Resource') AS resource_title,
            l.ip_address, 
            l.user_agent, 
            l.referrer, 
            COALESCE(l.created_at, l.downloaded_at) AS created_at,
            COALESCE(l.downloaded_at, l.created_at) AS downloaded_at
        FROM download_logs l
        LEFT JOIN resources r ON l.resource_id = r.id
    ";

    $params = [];
    if ($resourceId !== null) {
        $sql .= " WHERE l.resource_id = :resource_id";
        $params['resource_id'] = $resourceId;
    }

    $sql .= " ORDER BY l.id DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total download log count
    $totalRow = $pdo->query("SELECT COUNT(*) AS n FROM download_logs")->fetch(PDO::FETCH_ASSOC);
    $total = (int) ($totalRow['n'] ?? 0);

    // Last 24-hour download count (Database agnostic via PHP timestamp)
    $oneDayAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $last24hStmt = $pdo->prepare("
        SELECT COUNT(*) AS n 
        FROM download_logs 
        WHERE (created_at >= :one_day_ago OR downloaded_at >= :one_day_ago)
    ");
    $last24hStmt->execute(['one_day_ago' => $oneDayAgo]);
    $last24hRow = $last24hStmt->fetch(PDO::FETCH_ASSOC);
    $last24h = (int) ($last24hRow['n'] ?? 0);

    // Top 5 downloaded resources aggregate
    $topStmt = $pdo->query("
        SELECT 
            r.id, 
            r.title, 
            COUNT(l.id) AS downloads
        FROM download_logs l
        JOIN resources r ON l.resource_id = r.id
        GROUP BY r.id, r.title
        ORDER BY downloads DESC
        LIMIT 5
    ");
    $topResources = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // Standardized payload satisfying both front-end data conventions
    $response = [
        'success'       => true,
        'count'         => count($logs),
        'total'         => $total,
        'last_24h'      => $last24h,
        'data'          => $logs,
        'logs'          => $logs,
        'top_resources' => $topResources
    ];

    if (function_exists('sendJson')) {
        sendJson($response);
    } else {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

} catch (Exception $e) {
    if (function_exists('sendError')) {
        sendError('Failed to load download logs: ' . $e->getMessage(), 500);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database query error: ' . $e->getMessage()]);
        exit();
    }
}