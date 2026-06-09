<?php

/**
 * Migration: Create User Interaction Tables
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Polymorphic Reactions Table (can react to threads or posts)
        $pdo->exec("CREATE TABLE IF NOT EXISTS reactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            reactable_type VARCHAR(50) NOT NULL, -- 'thread', 'post'
            reactable_id BIGINT UNSIGNED NOT NULL,
            reaction_type VARCHAR(20) NOT NULL, -- 'like', 'love', 'haha', 'sad', 'angry'
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY user_reactable (user_id, reactable_type, reactable_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Bookmarks Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS bookmarks (
            user_id BIGINT UNSIGNED NOT NULL,
            thread_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, thread_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. Thread Subscriptions (Watching threads)
        $pdo->exec("CREATE TABLE IF NOT EXISTS thread_subscriptions (
            user_id BIGINT UNSIGNED NOT NULL,
            thread_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, thread_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4. User Follows
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_follows (
            follower_id BIGINT UNSIGNED NOT NULL,
            followed_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (follower_id, followed_id),
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 5. User Blocks
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
            user_id BIGINT UNSIGNED NOT NULL,
            blocked_user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, blocked_user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS user_blocks;");
        $pdo->exec("DROP TABLE IF EXISTS user_follows;");
        $pdo->exec("DROP TABLE IF EXISTS thread_subscriptions;");
        $pdo->exec("DROP TABLE IF EXISTS bookmarks;");
        $pdo->exec("DROP TABLE IF EXISTS reactions;");
    }
];
