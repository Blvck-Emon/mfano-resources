<?php
// Backend+AdminPanel/api/admin/resource_status_logs.php
header('Content-Type: application/json; charset=utf-8');
$root = realpath(__DIR__ . '/..');
$config = require $root . '/config/config.php';
$expectedKey = $config['admin_api_key'] ?? '';
$sentKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? '';

if ($expectedKey && $sentKey !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized (admin key required)']);
    exit;
}

$dbPath = $config['db']['path'] ?? $root . '/database/mfano_bora.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rid = isset($_GET['resource_id']) ? intval($_GET['resource_id']) : 0;
if ($rid < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'resource_id required']);
    exit;
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 200;
$limit = min(max($limit, 10), 2000);

$stmt = $pdo->prepare('SELECT id, status, note, actor, created_at FROM resource_status_logs WHERE resource_id = :rid ORDER BY created_at DESC LIMIT :lim');
$stmt->bindValue(':rid', $rid, PDO::PARAM_INT);
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $rows]);
exit;