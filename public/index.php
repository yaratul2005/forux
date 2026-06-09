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
$kernel->handle();
