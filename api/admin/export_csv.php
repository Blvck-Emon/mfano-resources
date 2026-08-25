<?php
// api/admin/export_csv.php
// GET or POST allowed. Returns CSV for resources joined with categories+sub_categories.
// Header: X-Api-Key: <admin key>

require_once __DIR__ . '/../../config/db.php';

$config = require __DIR__ . '/../../config/config.php';
$headers = getallheaders();
$apiKey = $headers['X-Api-Key'] ?? $headers['X-API-KEY'] ?? null;
$expected = $config['admin_api_key'] ?? null;

if (!$apiKey || !$expected || !hash_equals((string)$expected, (string)$apiKey)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDbConnection();

    $sql = "SELECT
        r.id AS resource_id,
        c.id AS category_id,
        c.name AS category_name,
        s.id AS subcategory_id,
        s.name AS subcategory_name,
        r.title,
        r.description,
        r.file_url,
        r.storage_type,
        r.stored_path,
        r.checksum_sha256,
        r.file_size_kb,
        r.download_count,
        r.is_featured,
        r.is_published,
        r.publish_date,
        r.created_at,
        r.updated_at
    FROM resources r
    LEFT JOIN sub_categories s ON r.sub_category_id = s.id
    LEFT JOIN categories c ON s.category_id = c.id
    ORDER BY r.id;";

    $stmt = $pdo->query($sql);

    // build CSV in memory (stream)
    $ts = gmdate('Ymd\THis\Z');
    $exportDir = __DIR__ . '/../../database/exports';
    @mkdir($exportDir, 0750, true);
    $filename = "resources_export_{$ts}.csv";
    $filepath = $exportDir . '/' . $filename;

    $out = fopen('php://temp', 'r+');

    // write header
    $cols = [
        'resource_id','category_id','category_name','subcategory_id','subcategory_name',
        'title','description','file_url','storage_type','stored_path','checksum_sha256',
        'file_size_kb','download_count','is_featured','is_published','publish_date','created_at','updated_at'
    ];
    fputcsv($out, $cols);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Ensure consistent order
        $line = [];
        foreach ($cols as $c) {
            $line[] = isset($row[$c]) ? $row[$c] : '';
        }
        fputcsv($out, $line);
    }

    // persist to disk
    rewind($out);
    $contents = stream_get_contents($out);
    file_put_contents($filepath, $contents);

    // return CSV as download attachment
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $contents;
    fclose($out);
    exit;
} catch (Exception $e) {
    error_log('Export CSV failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Export failed']);
    exit;
}