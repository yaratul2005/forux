<?php

/**
 * Migration: Add language column to users table for preferred locale persistence.
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'en'");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE users DROP COLUMN language");
    }
];
