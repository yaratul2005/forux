#!/usr/bin/env php
<?php
/**
 * Forux CLI Runner
 */

if (php_sapi_name() !== 'cli') {
    die('This tool can only be run from the command line.');
}

define('ROOT_PATH', __DIR__);

echo "Forux Forum CLI Tool\n";
echo "====================\n";
echo "Usage: php cli.php [command]\n\n";
echo "Commands:\n";
echo "  migrate     Run pending database migrations\n";
echo "  cache:clear Clear application caches\n";
echo "\n";
