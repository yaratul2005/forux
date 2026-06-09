<?php

/**
 * Migration: Create CMS and Configuration Tables
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Settings Table (Runtime configuration key-value store)
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Static Pages Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            body MEDIUMTEXT NOT NULL, -- HTML content
            is_published TINYINT(1) DEFAULT 0,
            meta_description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. Navigation Menus
        $pdo->exec("CREATE TABLE IF NOT EXISTS navigation_menus (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NULL,
            label VARCHAR(100) NOT NULL,
            url VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0,
            FOREIGN KEY (parent_id) REFERENCES navigation_menus(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Seed some basic default settings
        $settings = [
            ['site_name', 'Forux Forum'],
            ['site_description', 'A lightweight, open-source forum community.'],
            ['registration_mode', 'open'], // 'open', 'invite', 'closed'
            ['active_theme', 'default'],
            ['posts_per_page', '15'],
            ['threads_per_page', '20'],
            ['maintenance_mode', '0'],
            ['maintenance_message', 'The community is undergoing brief maintenance. Please check back soon.']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_key=setting_key");
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS navigation_menus;");
        $pdo->exec("DROP TABLE IF EXISTS pages;");
        $pdo->exec("DROP TABLE IF EXISTS settings;");
    }
];
