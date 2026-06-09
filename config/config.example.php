<?php
/**
 * Forux Forum Configuration Template
 * Rename this file to config.php and fill in your details.
 */

return [
    'app' => [
        'name' => 'Forux Forum',
        'env' => 'production', // 'development' or 'production'
        'debug' => false,
        'url' => 'http://localhost',
        'admin_path' => 'admin_secret_path', // Custom admin path to avoid guessing
        'timezone' => 'UTC',
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'forux_db',
        'username' => 'db_user',
        'password' => 'db_password',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        // CRITICAL: Change this salt to a random 32-character string.
        // It is used to encrypt API keys and credentials in the database vault.
        'encryption_key' => 'generate-a-random-32-char-string-here',
    ],

    'session' => [
        'name' => 'forux_session',
        'lifetime' => 86400, // 24 hours in seconds
        'secure' => false, // Set to true if running over HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ],
];
