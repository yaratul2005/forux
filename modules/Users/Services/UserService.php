<?php

namespace Modules\Users\Services;

use PDO;

/**
 * Service for Managing User Data and Profiles
 */
class UserService
{
    protected PDO $pdo;

    /**
     * Create a new UserService instance.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find a user by their username.
     *
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, avatar_url, bio, location, reputation_points, status, language, created_at 
                FROM users 
                WHERE username = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Find a user by their ID.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, avatar_url, bio, location, reputation_points, status, language, created_at 
                FROM users 
                WHERE id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Update user profile settings.
     *
     * @param int $userId
     * @param array $data ['bio' => ..., 'location' => ...]
     * @return bool
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $bio = $data['bio'] ?? null;
        $location = $data['location'] ?? null;
        $language = $data['language'] ?? 'en';

        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET bio = ?, location = ?, language = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND deleted_at IS NULL
            ");
            return $stmt->execute([$bio, $location, $language, $userId]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Search users by prefix query for autocompletes.
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function searchUsers(string $query, int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, avatar_url 
                FROM users 
                WHERE username LIKE ? AND deleted_at IS NULL
                LIMIT ?
            ");
            $like = $query . '%';
            $stmt->bindParam(1, $like, PDO::PARAM_STR);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
