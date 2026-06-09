<?php
/**
 * Forux Forum Front Controller
 * 
 * All requests are routed through this file.
 */

define('FORUX_START', microtime(true));
define('ROOT_PATH', dirname(__DIR__));

// Check for installation lock
$installed = file_exists(ROOT_PATH . '/storage/installed.lock');

if (!$installed) {
    if (file_exists(ROOT_PATH . '/install/index.php')) {
        // Redirect to installation page
        header('Location: /install/index.php');
        exit;
    } else {
        die('Forux is not installed. Please upload the install/ folder and run the setup.');
    }
}

// Boot the application kernel
require_once ROOT_PATH . '/core/Kernel.php';

// Instantiate and handle the request
$kernel = new Core\Kernel();
$kernel->handle();
