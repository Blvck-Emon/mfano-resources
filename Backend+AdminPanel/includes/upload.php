<?php
/**
 * includes/upload.php  (NEW)
 *
 * Handles direct PDF uploads for the admin panel, as an alternative to
 * pasting an already-hosted (S3/Cloudinary) file_url. Validates the file
 * is really a PDF, stores it under uploads/resources with a random name
 * (never trusts the original filename), and returns everything
 * api/admin/resources.php needs to write the resources row.
 */

require_once __DIR__ . '/helpers.php';

/**
 * @return array{file_url:string, stored_path:string, file_size_kb:int, checksum_sha256:string}
 */
function storeUploadedPdf(array $file): array
{
    $config = require __DIR__ . '/../config/config.php';
    $uploadsDir = $config['uploads']['dir'];
    $urlPrefix  = rtrim($config['uploads']['url_prefix'], '/');
    $maxBytes   = $config['uploads']['max_bytes'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        sendError('File upload failed (error code ' . $file['error'] . ').', 400);
    }

    if ($file['size'] > $maxBytes) {
        sendError('File exceeds the ' . round($maxBytes / 1024 / 1024) . 'MB limit.', 400);
    }

    // Validate actual content, not just the client-supplied name/mime type.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($detectedMime !== 'application/pdf' || $extension !== 'pdf') {
        sendError('Only PDF files are accepted.', 400);
    }

    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
        error_log("Failed to create uploads directory: $uploadsDir");
        sendError('Server could not prepare storage for the upload.', 500);
    }

    $checksum = hash_file('sha256', $file['tmp_name']);
    $storedName = bin2hex(random_bytes(16)) . '.pdf';
    $destination = rtrim($uploadsDir, '/') . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log("Failed to move uploaded file to $destination");
        sendError('Server could not save the uploaded file.', 500);
    }

    return [
        'file_url'        => $urlPrefix . '/' . $storedName,
        'stored_path'     => 'uploads/resources/' . $storedName,
        'file_size_kb'    => (int) round($file['size'] / 1024),
        'checksum_sha256' => $checksum,
    ];
}