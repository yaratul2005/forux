<?php
/**
 * Forux Forum Front Controller
 * 
 * All requests are routed through this file.
 */

define('FORUX_START', microtime(true));
define('ROOT_PATH', dirname(__DIR__));

// Load and register PSR-4 Autoloader
require_once ROOT_PATH . '/core/Autoloader.php';
\Core\Autoloader::register();
\Core\Autoloader::addNamespace('Core', ROOT_PATH . '/core');
\Core\Autoloader::addNamespace('App', ROOT_PATH . '/app');
\Core\Autoloader::addNamespace('Modules', ROOT_PATH . '/modules');

// ---------------------------------------------------------------
// Serve theme static assets (JS, CSS, images) from public/index.php
// when accessed via /themes/... URL in "root-deploy" mode.
// This only fires if the browser requested /themes/... but the
// webserver routed it here because the docroot is public/.
// ---------------------------------------------------------------
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$themeAssetPrefix = '/themes/';
if (strpos($requestUri, $themeAssetPrefix) === 0) {
    // Strip query string
    $assetPath = strtok($requestUri, '?');
    $fullPath  = ROOT_PATH . $assetPath; // e.g. d:/forux/themes/default/assets/theme.js
    if (file_exists($fullPath) && is_file($fullPath)) {
        // Determine MIME type
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mimeMap = [
            'js'   => 'application/javascript',
            'css'  => 'text/css',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
        ];
        if (isset($mimeMap[$ext]) && $ext !== 'php') {
            header('Content-Type: ' . $mimeMap[$ext]);
            header('Cache-Control: public, max-age=2592000'); // 30 days
            readfile($fullPath);
            exit;
        }
    }
    // Block access to PHP template files in themes
    if ($ext === 'php') {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Check for installation lock
$installed = file_exists(ROOT_PATH . '/storage/installed.lock');
if (!$installed && !isset($_GET['test'])) {
    if (file_exists(ROOT_PATH . '/install/index.php')) {
        require_once ROOT_PATH . '/install/index.php';
        exit;
    } else {
        die('Forux is not installed, and the installation wizard was not found. Please upload the install/ folder.');
    }
}

// Instantiate and handle the request
$kernel = new Core\Kernel();
$kernel->addMiddleware(\App\Middleware\RateLimitMiddleware::class);
$kernel->addMiddleware(\App\Middleware\CsrfMiddleware::class);
$kernel->handle();
