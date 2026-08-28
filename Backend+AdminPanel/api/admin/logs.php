<?php
/**
 * Backend+AdminPanel/api/admin/logs.php
 * Combined Admin API Endpoint for Audit Activity Logs & Analytics
 */

declare(strict_types=1);

// Load local database configuration
$root = realpath(__DIR__ . '/../..') ?: __DIR__ . '/../..';

if (file_exists($root . '/config/db.php')) {
    require_once $root . '/config/db.php';
}
if (file_exists($root . '/includes/helpers.php')) {
    require_once $root . '/includes/helpers.php';
}
if (file_exists($root . '/includes/auth.php')) {
    require_once $root . '/includes/auth.php';
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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit();
    }
}

// -------------------------------------------------------------------------
// 2. Multi-Key Admin Authentication
// -------------------------------------------------------------------------
if (function_exists('requireAdminKey')) {
    requireAdminKey();
} else {
    $configFile = $root . '/config/config.php';
    $config = file_exists($configFile) ? require $configFile : [];
    
    $expectedKey = $config['admin_api_key'] ?? (getenv('ADMIN_API_KEY') ?: 'admin123');
    $sentKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? ($_GET['api_key'] ?? '');

    if (!empty($expectedKey) && $sentKey !== $expectedKey) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid Admin API Key']);
        exit();
    }
}

// -------------------------------------------------------------------------
// 3. Database Connection Initialization
// -------------------------------------------------------------------------
try {
    $pdo = function_exists('getDbConnection') ? getDbConnection() : ($pdo ?? null);
    
    if (!$pdo) {
        $dbPath = $config['db']['path'] ?? ($root . '/database/mfano_bora.sqlite');
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Parameters
    $resourceId = isset($_GET['resource_id']) && is_numeric($_GET['resource_id']) ? (int)$_GET['resource_id'] : null;
    $limit = isset($_GET['limit']) ? max(10, min((int)$_GET['limit'], 2000)) : 200;

    // -------------------------------------------------------------------------
    // 4. Activity Logs Query Execution
    // -------------------------------------------------------------------------
    $sql = "
        SELECT 
            dl.id, 
            dl.resource_id, 
            COALESCE(r.title, 'Deleted / Unknown Resource') AS resource_title,
            dl.ip_address, 
            dl.user_agent, 
            dl.referrer, 
            COALESCE(dl.action, 'download') AS action,
            COALESCE(dl.downloaded_at, dl.created_at, CURRENT_TIMESTAMP) AS downloaded_at,
            COALESCE(dl.created_at, dl.downloaded_at, CURRENT_TIMESTAMP) AS created_at
        FROM download_logs dl
        LEFT JOIN resources r ON dl.resource_id = r.id
    ";

    $params = [];
    if ($resourceId !== null) {
        $sql .= " WHERE dl.resource_id = :resource_id";
        $params['resource_id'] = $resourceId;
    }

    $sql .= " ORDER BY dl.id DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Aggregate Analytics Metrics
    $totalRow = $pdo->query("SELECT COUNT(*) AS n FROM download_logs")->fetch(PDO::FETCH_ASSOC);
    $total = (int)($totalRow['n'] ?? 0);

    $oneDayAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $last24hStmt = $pdo->prepare("
        SELECT COUNT(*) AS n 
        FROM download_logs 
        WHERE (created_at >= :one_day_ago OR downloaded_at >= :one_day_ago)
    ");
    $last24hStmt->execute(['one_day_ago' => $oneDayAgo]);
    $last24hRow = $last24hStmt->fetch(PDO::FETCH_ASSOC);
    $last24h = (int)($last24hRow['n'] ?? 0);

    $topStmt = $pdo->query("
        SELECT 
            r.id, 
            r.title, 
            COUNT(dl.id) AS downloads
        FROM download_logs dl
        JOIN resources r ON dl.resource_id = r.id
        GROUP BY r.id, r.title
        ORDER BY downloads DESC
        LIMIT 5
    ");
    $topResources = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // Dual key format response supporting legacy UI schemas
    $response = [
        'success'       => true,
        'count'         => count($logs),
        'total'         => $total,
        'last_24h'      => $last24h,
        'data'          => $logs,
        'logs'          => $logs,
        'rows'          => $logs,
        'top_resources' => $topResources
    ];

    if (function_exists('sendJson')) {
        sendJson($response);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit();
    }

} catch (Exception $e) {
    if (function_exists('sendError')) {
        sendError('Failed to load activity logs: ' . $e->getMessage(), 500);
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Database query error: ' . $e->getMessage()]);
        exit();
    }
}