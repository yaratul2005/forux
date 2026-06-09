<?php

/**
 * Migration: Create Identity and Access Tables
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Users Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            avatar_url VARCHAR(255) NULL,
            bio TEXT NULL,
            location VARCHAR(100) NULL,
            reputation_points INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active', -- 'active', 'suspended', 'banned'
            email_verified_at TIMESTAMP NULL,
            two_factor_secret VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Roles Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. Permissions Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4. Role Permissions Pivot Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
            role_id BIGINT UNSIGNED NOT NULL,
            permission_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 5. User Roles Pivot Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
            user_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 6. Password Reset Tokens
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            email VARCHAR(150) NOT NULL PRIMARY KEY,
            token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 7. Email Verification Tokens
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (
            email VARCHAR(150) NOT NULL PRIMARY KEY,
            token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 8. OAuth Accounts Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS oauth_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(50) NOT NULL, -- 'google', 'github', 'discord'
            provider_user_id VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY provider_user (provider, provider_user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 9. User Sessions Table (Database Sessions)
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            payload TEXT NOT NULL,
            last_activity INT UNSIGNED NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Seed Default Roles
        $roles = [
            ['Guest', 'Unauthenticated visitor'],
            ['Member', 'Registered standard forum user'],
            ['Trusted', 'Verified user with additional posting permissions'],
            ['Moderator', 'Category level contents moderator'],
            ['Admin', 'System administrator managing users and settings'],
            ['Super Admin', 'Full system owner']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=name");
        foreach ($roles as $role) {
            $stmt->execute($role);
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS user_sessions;");
        $pdo->exec("DROP TABLE IF EXISTS oauth_accounts;");
        $pdo->exec("DROP TABLE IF EXISTS email_verifications;");
        $pdo->exec("DROP TABLE IF EXISTS password_resets;");
        $pdo->exec("DROP TABLE IF EXISTS user_roles;");
        $pdo->exec("DROP TABLE IF EXISTS role_permissions;");
        $pdo->exec("DROP TABLE IF EXISTS permissions;");
        $pdo->exec("DROP TABLE IF EXISTS roles;");
        $pdo->exec("DROP TABLE IF EXISTS users;");
    }
];
