<?php
// api/admin/export_csv.php
// Produces single canonical CSV files for resources, categories, sub_categories, download_logs.
// Old timestamped files matching those prefixes are removed to avoid duplication/space bloat.
//
// Usage: same as before (X-Api-Key header). Returns the resources CSV as the download attachment.

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// HARDENED: previously this endpoint re-implemented its own (unlimited-
// attempts, no CORS) auth check instead of reusing includes/auth.php like
// every other /api/admin/*.php endpoint. It now goes through the same
// rate-limited requireAdminKey() gate as the rest of the admin API.
applyCors();
requireAdminKey();

try {
    $pdo = getDbConnection();

    // Export directory (canonical files will be written here)
    $exportDir = __DIR__ . '/../../database/exports';
    @mkdir($exportDir, 0750, true);

    //
    // 1) Export resources (joined with category & subcategory) to canonical filename
    //
    $resourcesCols = [
        'resource_id','category_id','category_name','subcategory_id','subcategory_name',
        'title','description','file_url','storage_type','stored_path','checksum_sha256',
        'file_size_kb','download_count','is_featured','is_published','publish_date','created_at','updated_at'
    ];
    $resourcesSql = "SELECT
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
    $stmt = $pdo->query($resourcesSql);
    $resourcesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // write canonical resources CSV file
    $resourcesFilename = 'resources_export.csv';
    $resourcesPath = $exportDir . '/' . $resourcesFilename;
    $tmp = fopen('php://temp', 'r+');
    if ($tmp === false) {
        throw new Exception('Failed to open memory stream for CSV generation.');
    }
    // header
    fputcsv($tmp, $resourcesCols);
    foreach ($resourcesRows as $row) {
        $line = [];
        foreach ($resourcesCols as $c) {
            $line[] = $row[$c] ?? '';
        }
        fputcsv($tmp, $line);
    }
    rewind($tmp);
    $contents = stream_get_contents($tmp);
    if ($contents === false) $contents = '';
    if (file_put_contents($resourcesPath, $contents) === false) {
        throw new Exception("Failed to write resources CSV to: {$resourcesPath}");
    }
    fclose($tmp);

    //
    // 2) Export categories -> categories.csv
    //
    $catsStmt = $pdo->query("SELECT * FROM categories ORDER BY id;");
    $catsRows = $catsStmt->fetchAll(PDO::FETCH_ASSOC);
    $catsPath = $exportDir . '/categories.csv';
    $tmp = fopen('php://temp','r+');
    if ($tmp === false) throw new Exception('Failed to open temp stream for categories CSV.');
    if (!empty($catsRows)) {
        fputcsv($tmp, array_keys($catsRows[0])); // header
        foreach ($catsRows as $r) fputcsv($tmp, $r);
    } else {
        // write header matching table columns as fallback
        fputcsv($tmp, ['id','name','slug','description','created_at']);
    }
    rewind($tmp);
    file_put_contents($catsPath, stream_get_contents($tmp));
    fclose($tmp);

    //
    // 3) Export sub_categories -> sub_categories.csv
    //
    $subStmt = $pdo->query("SELECT * FROM sub_categories ORDER BY id;");
    $subRows = $subStmt->fetchAll(PDO::FETCH_ASSOC);
    $subPath = $exportDir . '/sub_categories.csv';
    $tmp = fopen('php://temp','r+');
    if ($tmp === false) throw new Exception('Failed to open temp stream for sub_categories CSV.');
    if (!empty($subRows)) {
        fputcsv($tmp, array_keys($subRows[0]));
        foreach ($subRows as $r) fputcsv($tmp, $r);
    } else {
        fputcsv($tmp, ['id','category_id','name','slug','description','created_at']);
    }
    rewind($tmp);
    file_put_contents($subPath, stream_get_contents($tmp));
    fclose($tmp);

    //
    // 4) Export download_logs -> download_logs.csv
    //
    $logStmt = $pdo->query("SELECT * FROM download_logs ORDER BY downloaded_at DESC LIMIT 10000;");
    $logRows = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    $logsPath = $exportDir . '/download_logs.csv';
    $tmp = fopen('php://temp','r+');
    if ($tmp === false) throw new Exception('Failed to open temp stream for download_logs CSV.');
    if (!empty($logRows)) {
        fputcsv($tmp, array_keys($logRows[0]));
        foreach ($logRows as $r) fputcsv($tmp, $r);
    } else {
        fputcsv($tmp, ['id','resource_id','ip_address','user_agent','referrer','downloaded_at']);
    }
    rewind($tmp);
    file_put_contents($logsPath, stream_get_contents($tmp));
    fclose($tmp);

    //
    // 5) Cleanup old timestamped files for the same prefixes to prevent multiple versions
    //
    $prefixes = [
        'resources_export' => $resourcesFilename,
        'categories'       => 'categories.csv',
        'sub_categories'   => 'sub_categories.csv',
        'download_logs'    => 'download_logs.csv',
    ];

    $allFiles = glob($exportDir . '/*');
    foreach ($allFiles as $f) {
        $base = basename($f);
        // skip the canonical files we just wrote
        if (in_array($base, array_values($prefixes), true)) continue;

        // if file looks like one of the timestamped forms, remove it
        foreach (array_keys($prefixes) as $pfx) {
            // matches e.g. resources_export_YYYYMMDDT....csv or categories_YYYY...
            if (stripos($base, $pfx . '_') === 0 && preg_match('/\.(csv)$/i', $base)) {
                @unlink($f);
                break;
            }
        }
    }

    //
    // 6) Return the resources CSV as the download attachment (backwards-compatible)
    //
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . basename($resourcesPath) . '"');
    readfile($resourcesPath);
    exit;

} catch (Exception $e) {
    error_log('Export CSV failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Export failed', 'message' => $e->getMessage()]);
    exit;
}