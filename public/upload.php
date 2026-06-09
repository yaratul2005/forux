<?php
/**
 * Forux Upload Proxy
 * Serves files securely from storage/uploads/ preventing directory traversal.
 */

define('ROOT_PATH', dirname(__DIR__));

$path = $_GET['path'] ?? '';
if (empty($path)) {
    header("HTTP/1.1 400 Bad Request");
    echo "Bad Request: No path specified.";
    exit;
}

// 1. Sanitize the path to prevent basic traversal attempts
$path = ltrim($path, '/\\');
$path = str_replace(['..', './', '.\\'], '', $path);
$path = preg_replace('#[\\/]+#', DIRECTORY_SEPARATOR, $path);

$uploadDir = ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
$fullPath = $uploadDir . DIRECTORY_SEPARATOR . $path;

// 2. Ensure the upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$realUploadDir = realpath($uploadDir);
$realPath = realpath($fullPath);

// 3. Strict directory boundary traversal check
if ($realPath === false || !str_starts_with($realPath, $realUploadDir)) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access Denied: Path traversal detected.";
    exit;
}

// 4. Ensure it's a file
if (!is_file($realPath)) {
    header("HTTP/1.1 404 Not Found");
    echo "File Not Found.";
    exit;
}

// 5. Detect MIME type safely
$mimeType = 'application/octet-stream';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($realPath);
    if ($detectedMime) {
        $mimeType = $detectedMime;
    }
}

// 6. Set headers for caching and content delivery
header("Content-Type: " . $mimeType);
header("Content-Length: " . filesize($realPath));
header("Cache-Control: public, max-age=31536000"); // 1 year caching
header("Pragma: public");

// 7. Output file in chunks to prevent memory limits
$handle = fopen($realPath, 'rb');
if ($handle !== false) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
} else {
    readfile($realPath);
}
exit;
