<?php
/**
 * Forux Cron Job Runner
 *
 * This file should be called by the system cron daemon once per minute.
 * Example cron entry:
 * * * * * * php /path/to/forux/cron.php > /dev/null 2>&1
 */

define('FORUX_START', microtime(true));
define('ROOT_PATH', __DIR__);

// Check if installation is complete
if (!file_exists(ROOT_PATH . '/storage/installed.lock')) {
    die('Forux is not installed yet. Cron aborted.');
}

// Load and register PSR-4 Autoloader
require_once ROOT_PATH . '/core/Autoloader.php';
\Core\Autoloader::register();
\Core\Autoloader::addNamespace('Core', ROOT_PATH . '/core');
\Core\Autoloader::addNamespace('App', ROOT_PATH . '/app');
\Core\Autoloader::addNamespace('Modules', ROOT_PATH . '/modules');

// Instantiate Kernel to bootstrap configuration, database, container, modules, and hooks
$kernel = new Core\Kernel();
$container = $kernel->getContainer();

echo "Forux Cron Execution Start: " . date('Y-m-d H:i:s') . "\n";

try {
    // Resolve Hook service and fire cron.minute hook using doAction
    $hook = $container->get(\Core\Hook::class);
    $hook->doAction('cron.minute');
    echo "✔ Hook 'cron.minute' dispatched successfully.\n";
} catch (\Throwable $e) {
    echo "✘ Hook dispatch failed: " . $e->getMessage() . "\n";
}

echo "Forux Cron Execution Completed.\n";
