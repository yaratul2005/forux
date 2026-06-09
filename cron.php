<?php
/**
 * Forux Cron Job Runner
 *
 * This file should be called by the system cron daemon once per minute.
 * Example cron entry:
 * * * * * * php /path/to/forux/cron.php > /dev/null 2>&1
 */

define('ROOT_PATH', __DIR__);

// Check if installation is complete
if (!file_exists(ROOT_PATH . '/storage/installed.lock')) {
    die('Forux is not installed yet. Cron aborted.');
}

// Bootstrapper for background tasks
// In the future, this will dispatch to Registered Cron Jobs (Email queue, cache clearing, log rotation)
echo "Forux Cron Execution Start: " . date('Y-m-d H:i:s') . "\n";
// TODO: Hook/event cron dispatcher execution
echo "Forux Cron Execution Completed.\n";
