<?php

/**
 * Migration: Create Credentials Vault Table
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Service Credentials Vault Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS service_credentials (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_name VARCHAR(100) NOT NULL, -- e.g., 'SMTP', 'GoogleOAuth', 'S3Storage'
            credential_key VARCHAR(100) NOT NULL, -- e.g., 'SMTP_PASSWORD', 'CLIENT_SECRET'
            credential_value TEXT NOT NULL,       -- Stored as encrypted string
            is_active TINYINT(1) DEFAULT 0,       -- Toggle active status
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY service_credential (service_name, credential_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS service_credentials;");
    }
];
