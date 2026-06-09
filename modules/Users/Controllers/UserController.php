<?php

namespace Modules\Users\Controllers;

use Core\Response;
use Core\Request;
use Modules\Users\Services\UserService;
use Modules\Auth\Services\AuthService;
use App\Services\Storage\StorageServiceInterface;
use Throwable;

/**
 * Controller handling user profiles and settings views using theme views.
 */
class UserController
{
    protected UserService $userService;
    protected AuthService $auth;
    protected Request $request;
    protected StorageServiceInterface $storageService;

    /**
     * Create a new UserController instance.
     */
    public function __construct(UserService $userService, AuthService $auth, Request $request, StorageServiceInterface $storageService)
    {
        $this->userService = $userService;
        $this->auth = $auth;
        $this->request = $request;
        $this->storageService = $storageService;
    }

    /**
     * Render public user profile.
     */
    public function profile(string $username): Response
    {
        $user = $this->userService->findByUsername($username);

        if (!$user) {
            return \Core\View::render('error', [
                'title' => 'User Not Found',
                'message' => "The user @{$username} does not exist."
            ], 404);
        }

        $isFollowing = false;
        $isBlocked = false;
        $currentUser = $this->auth->user();
        if ($currentUser) {
            $isFollowing = $this->userService->isFollowing($currentUser['id'], $user['id']);
            $isBlocked = $this->userService->isBlocked($currentUser['id'], $user['id']);
        }
        $followCounts = $this->userService->getFollowCounts($user['id']);

        return \Core\View::render('profile', [
            'user' => $user,
            'isFollowing' => $isFollowing,
            'isBlocked' => $isBlocked,
            'followersCount' => $followCounts['followers'],
            'followingCount' => $followCounts['following'],
            'title' => "@{$user['username']} - Forux Profile"
        ]);
    }

    /**
     * Render the profile settings edit page.
     */
    public function settings(?string $error = null, ?string $success = null): Response
    {
        // Require Authentication
        if (!$this->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->auth->user();

        return \Core\View::render('settings', [
            'user' => $user,
            'error' => $error,
            'success' => $success,
            'title' => 'Profile Settings - Forux'
        ]);
    }

    /**
     * Process profile settings update.
     */
    public function updateSettings(): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->auth->user();
        $location = trim($this->request->input('location', ''));
        $bio = trim($this->request->input('bio', ''));
        $language = trim($this->request->input('language', 'en'));

        if (!in_array($language, ['en', 'es', 'fr'], true)) {
            $language = 'en';
        }

        try {
            $avatarUrl = null;
            // Handle avatar upload if provided
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowedTypes, true)) {
                    throw new \Exception('Invalid image type. Allowed: JPEG, PNG, GIF, WEBP.');
                }

                if ($file['size'] > 2 * 1024 * 1024) {
                    throw new \Exception('File size exceeds 2MB limit.');
                }

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $targetPath = 'avatars/user_' . $user['id'] . '_' . time() . '.' . $ext;

                if ($this->storageService->store($file['tmp_name'], $targetPath)) {
                    $avatarUrl = $this->storageService->url($targetPath);
                }
            }

            $this->userService->updateProfile($user['id'], [
                'location' => $location,
                'bio' => $bio,
                'language' => $language
            ]);

            if ($avatarUrl) {
                $this->userService->updateAvatar($user['id'], $avatarUrl);
            }

            // Refresh user cache
            $this->auth->refreshUser();

            // Set language cookie and update runtime locale
            if (!headers_sent()) {
                setcookie('forux_lang', $language, time() + 86400 * 365, '/', '', false, true);
            }
            $langManager = \Core\Language::getInstance();
            if ($langManager) {
                $langManager->setLocale($language);
            }

            return $this->settings(null, __('common.profile_updated', ['default' => 'Profile successfully updated.']));
        } catch (Throwable $e) {
            return $this->settings($e->getMessage());
        }
    }

    /**
     * Follow a user.
     */
    public function follow(string $username): Response
    {
        if (!$this->auth->check()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $currentUser = $this->auth->user();
        $targetUser = $this->userService->findByUsername($username);

        if (!$targetUser) {
            return Response::json(['error' => 'User not found'], 404);
        }

        if ($this->userService->follow($currentUser['id'], $targetUser['id'])) {
            return Response::json(['success' => true]);
        }

        return Response::json(['error' => 'Unable to follow user'], 400);
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(string $username): Response
    {
        if (!$this->auth->check()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $currentUser = $this->auth->user();
        $targetUser = $this->userService->findByUsername($username);

        if (!$targetUser) {
            return Response::json(['error' => 'User not found'], 404);
        }

        if ($this->userService->unfollow($currentUser['id'], $targetUser['id'])) {
            return Response::json(['success' => true]);
        }

        return Response::json(['error' => 'Unable to unfollow user'], 400);
    }

    /**
     * Block a user.
     */
    public function block(string $username): Response
    {
        if (!$this->auth->check()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $currentUser = $this->auth->user();
        $targetUser = $this->userService->findByUsername($username);

        if (!$targetUser) {
            return Response::json(['error' => 'User not found'], 404);
        }

        if ($this->userService->block($currentUser['id'], $targetUser['id'])) {
            return Response::json(['success' => true]);
        }

        return Response::json(['error' => 'Unable to block user'], 400);
    }

    /**
     * Unblock a user.
     */
    public function unblock(string $username): Response
    {
        if (!$this->auth->check()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $currentUser = $this->auth->user();
        $targetUser = $this->userService->findByUsername($username);

        if (!$targetUser) {
            return Response::json(['error' => 'User not found'], 404);
        }

        if ($this->userService->unblock($currentUser['id'], $targetUser['id'])) {
            return Response::json(['success' => true]);
        }

        return Response::json(['error' => 'Unable to unblock user'], 400);
    }

    /**
     * Search users for autocomplete mentions.
     */
    public function search(): Response
    {
        if (!$this->auth->check()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $q = trim($this->request->input('q', ''));
        if (strlen($q) < 1) {
            return Response::json([]);
        }

        $users = $this->userService->searchUsers($q);
        
        $result = [];
        foreach ($users as $user) {
            $avatar = $user['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp&s=40';
            $result[] = [
                'username' => $user['username'],
                'avatar' => $avatar
            ];
        }

        return Response::json($result);
    }
}
