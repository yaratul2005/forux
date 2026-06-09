<?php

/**
 * Migration: Create Moderation Tables
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Reports Table (Polymorphic: reports on threads, posts, or users)
        $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL, -- Reporter
            reportable_type VARCHAR(50) NOT NULL, -- 'thread', 'post', 'user'
            reportable_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'open', -- 'open', 'reviewed', 'resolved'
            moderator_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Moderation Actions Audit Log
        $pdo->exec("CREATE TABLE IF NOT EXISTS moderation_actions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            moderator_id BIGINT UNSIGNED NOT NULL,
            action_type VARCHAR(50) NOT NULL, -- 'warn', 'suspend', 'ban', 'delete_post', 'lock_thread', 'move_thread'
            target_type VARCHAR(50) NOT NULL, -- 'user', 'thread', 'post', 'ip'
            target_id BIGINT UNSIGNED NULL,    -- Nullable if target is IP or non-numeric
            reason VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. User Bans Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS bans (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            banned_by BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NULL, -- Null if permanent
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4. User Warnings Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS warnings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            warned_by BIGINT UNSIGNED NOT NULL,
            points INT DEFAULT 1,
            reason VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (warned_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 5. IP Ban List Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS ip_ban_list (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL UNIQUE,
            banned_by BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS ip_ban_list;");
        $pdo->exec("DROP TABLE IF EXISTS warnings;");
        $pdo->exec("DROP TABLE IF EXISTS bans;");
        $pdo->exec("DROP TABLE IF EXISTS moderation_actions;");
        $pdo->exec("DROP TABLE IF EXISTS reports;");
    }
];
