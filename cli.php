#!/usr/bin/env php
<?php

/**
 * Forux CLI Runner
 */

if (php_sapi_name() !== 'cli') {
    die("This tool can only be run from the command line.\n");
}

define('FORUX_START', microtime(true));
define('ROOT_PATH', __DIR__);

// Load Autoloader
require_once ROOT_PATH . '/core/Autoloader.php';
\Core\Autoloader::register();
\Core\Autoloader::addNamespace('Core', ROOT_PATH . '/core');

$args = $_SERVER['argv'] ?? [];
$command = $args[1] ?? 'help';

switch ($command) {
    case 'migrate':
        runMigrations();
        break;
    case 'migrate:rollback':
        rollbackMigrations();
        break;
    case 'cache:clear':
        clearCache();
        break;
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * Display help instructions.
 */
function showHelp(): void
{
    echo "Forux Forum CLI Tool\n";
    echo "====================\n";
    echo "Usage: php cli.php [command]\n\n";
    echo "Commands:\n";
    echo "  migrate           Run all pending database migrations\n";
    echo "  migrate:rollback  Roll back the last migration step\n";
    echo "  cache:clear       Clear application caching folder\n";
    echo "  help              Show this help menu\n\n";
}

/**
 * Get DB connection from config file.
 */
function getPDOConnection(): PDO
{
    $configPath = ROOT_PATH . '/config/config.php';
    if (!file_exists($configPath)) {
        throw new Exception("Configuration file config.php not found. Run installer or copy config.example.php.");
    }
    
    $config = require $configPath;
    $db = $config['db'] ?? [];
    
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    return $pdo;
}

/**
 * Run migrations.
 */
function runMigrations(): void
{
    echo "Running migrations...\n";
    
    try {
        $pdo = getPDOConnection();
        
        // Create tracking table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Scan migration files
        $files = glob(ROOT_PATH . '/install/migrations/*.php');
        sort($files); // Sort by filename to ensure sequential order
        
        // Get already executed migrations
        $stmt = $pdo->query("SELECT migration FROM migrations");
        $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!in_array($name, $executed)) {
                $pending[] = $file;
            }
        }
        
        if (empty($pending)) {
            echo "Nothing to migrate. Database is up to date.\n";
            return;
        }
        
        foreach ($pending as $file) {
            $name = basename($file);
            echo "Migrating: {$name} ... ";
            
            $migration = require $file;
            
            try {
                $migration['up']($pdo);
                
                // Track execution
                $insert = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
                $insert->execute([$name]);
                
                echo "✔ Done\n";
            } catch (Throwable $e) {
                echo "✘ Failed!\n";
                throw $e;
            }
        }
        
        echo "\nMigrations completed successfully!\n";
        
    } catch (Throwable $e) {
        echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Rollback last migration step.
 */
function rollbackMigrations(): void
{
    echo "Rolling back last migrations...\n";
    
    try {
        $pdo = getPDOConnection();
        
        // Get the last executed migration
        $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetchColumn();
        
        if (!$last) {
            echo "Nothing to rollback.\n";
            return;
        }
        
        $file = ROOT_PATH . '/install/migrations/' . $last;
        if (!file_exists($file)) {
            throw new Exception("Migration file not found: {$last}");
        }
        
        echo "Rolling back: {$last} ... ";
        $migration = require $file;
        
        try {
            $migration['down']($pdo);
            
            // Remove tracking
            $delete = $pdo->prepare("DELETE FROM migrations WHERE migration = ?");
            $delete->execute([$last]);
            
            echo "✔ Done\n";
        } catch (Throwable $e) {
            echo "✘ Failed!\n";
            throw $e;
        }
        
    } catch (Throwable $e) {
        echo "\n[ERROR] Rollback failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Clear cache directories.
 */
function clearCache(): void
{
    echo "Clearing cache...\n";
    $cacheDirs = [
        ROOT_PATH . '/storage/cache',
        ROOT_PATH . '/storage/cache/partials'
    ];
    
    $count = 0;
    foreach ($cacheDirs as $cacheDir) {
        if (!is_dir($cacheDir)) {
            continue;
        }
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (basename($file) !== '.gitkeep') {
                if (is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
        }
    }
    
    echo "✔ Cleaned {$count} cache files.\n";
}
