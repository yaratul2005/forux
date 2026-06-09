<?php

namespace Modules\Users\Controllers;

use Core\Response;
use Core\Request;
use Modules\Users\Services\UserService;
use Modules\Auth\Services\AuthService;
use Throwable;

/**
 * Controller handling user profiles and settings views using theme views.
 */
class UserController
{
    protected UserService $userService;
    protected AuthService $auth;
    protected Request $request;

    /**
     * Create a new UserController instance.
     */
    public function __construct(UserService $userService, AuthService $auth, Request $request)
    {
        $this->userService = $userService;
        $this->auth = $auth;
        $this->request = $request;
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

        return \Core\View::render('profile', [
            'user' => $user,
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
            return Response::redirect('login');
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
            return Response::redirect('login');
        }

        $user = $this->auth->user();
        $location = trim($this->request->input('location', ''));
        $bio = trim($this->request->input('bio', ''));
        $language = trim($this->request->input('language', 'en'));

        if (!in_array($language, ['en', 'es', 'fr'], true)) {
            $language = 'en';
        }

        try {
            $this->userService->updateProfile($user['id'], [
                'location' => $location,
                'bio' => $bio,
                'language' => $language
            ]);

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
