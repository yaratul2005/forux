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

    /**
     * Follow a user.
     */
    public function follow(int $followerId, int $followedId): bool
    {
        if ($followerId === $followedId) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_follows (follower_id, followed_id) VALUES (?, ?)");
            return $stmt->execute([$followerId, $followedId]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(int $followerId, int $followedId): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?");
            return $stmt->execute([$followerId, $followedId]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Block a user.
     */
    public function block(int $userId, int $blockedUserId): bool
    {
        if ($userId === $blockedUserId) {
            return false;
        }
        try {
            // Automatically unfollow if blocking
            $this->unfollow($userId, $blockedUserId);
            $this->unfollow($blockedUserId, $userId);

            $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_blocks (user_id, blocked_user_id) VALUES (?, ?)");
            return $stmt->execute([$userId, $blockedUserId]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Unblock a user.
     */
    public function unblock(int $userId, int $blockedUserId): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?");
            return $stmt->execute([$userId, $blockedUserId]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if a user is following another user.
     */
    public function isFollowing(int $followerId, int $followedId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ? AND followed_id = ?");
            $stmt->execute([$followerId, $followedId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if a user has blocked another user.
     */
    public function isBlocked(int $userId, int $blockedUserId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?");
            $stmt->execute([$userId, $blockedUserId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get follow counts for a user.
     * Returns ['followers' => int, 'following' => int]
     */
    public function getFollowCounts(int $userId): array
    {
        try {
            $stmt1 = $this->pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE followed_id = ?");
            $stmt1->execute([$userId]);
            $followers = (int)$stmt1->fetchColumn();

            $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
            $stmt2->execute([$userId]);
            $following = (int)$stmt2->fetchColumn();

            return ['followers' => $followers, 'following' => $following];
        } catch (\Throwable $e) {
            return ['followers' => 0, 'following' => 0];
        }
    }

    /**
     * Update user avatar URL.
     */
    public function updateAvatar(int $userId, string $avatarUrl): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            return $stmt->execute([$avatarUrl, $userId]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
