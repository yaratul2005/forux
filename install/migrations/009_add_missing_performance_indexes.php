<?php

/**
 * Migration: Add Missing Performance Indexes
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE threads ADD INDEX idx_threads_slug(slug);");
        $pdo->exec("ALTER TABLE threads ADD INDEX idx_threads_category_deleted(category_id, deleted_at);");
        $pdo->exec("ALTER TABLE posts ADD INDEX idx_posts_thread_deleted(thread_id, deleted_at);");
        $pdo->exec("ALTER TABLE reactions ADD INDEX idx_reactions_reactable(reactable_type, reactable_id);");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE threads DROP INDEX idx_threads_slug;");
        $pdo->exec("ALTER TABLE threads DROP INDEX idx_threads_category_deleted;");
        $pdo->exec("ALTER TABLE posts DROP INDEX idx_posts_thread_deleted;");
        $pdo->exec("ALTER TABLE reactions DROP INDEX idx_reactions_reactable;");
    }
];
