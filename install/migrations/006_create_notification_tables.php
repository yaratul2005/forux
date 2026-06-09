<?php

/**
 * Migration: Create Notification Tables
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Polymorphic Notifications Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL, -- Recipient
            type VARCHAR(100) NOT NULL,       -- Notification class type (e.g. 'ReplyNotification')
            notifiable_type VARCHAR(50) NOT NULL, -- Target subject type (e.g. 'post')
            notifiable_id BIGINT UNSIGNED NOT NULL, -- Target subject ID
            data JSON NOT NULL,               -- Payload data (JSON formatted)
            read_at TIMESTAMP NULL,           -- Read flag/timestamp
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS notifications;");
    }
];
