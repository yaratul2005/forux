<?php

namespace Modules\Auth\Controllers;

use Core\Response;
use Core\Request;
use Modules\Auth\Services\AuthService;
use Throwable;

/**
 * Controller handling user authentication requests
 */
class AuthController
{
    protected AuthService $auth;
    protected Request $request;

    /**
     * Create a new AuthController.
     */
    public function __construct(AuthService $auth, Request $request)
    {
        $this->auth = $auth;
        $this->request = $request;
    }

    /**
     * Render the login form page.
     */
    public function showLogin(?string $error = null): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('settings'); // Redirect to profile settings if already logged in
        }

        return \Core\View::render('login', [
            'error' => $error,
            'title' => 'Login - Forux'
        ]);
    }

    /**
     * Process user login request.
     */
    public function login(): Response
    {
        $email = trim($this->request->input('email', ''));
        $password = $this->request->input('password', '');

        try {
            $this->auth->login($email, $password);
            // On success, redirect to profile settings
            return Response::redirect('settings');
        } catch (Throwable $e) {
            return $this->showLogin($e->getMessage());
        }
    }

    /**
     * Render the registration form page.
     */
    public function showRegister(?string $error = null): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('settings');
        }

        return \Core\View::render('register', [
            'error' => $error,
            'title' => 'Register - Forux'
        ]);
    }

    /**
     * Process user registration.
     */
    public function register(): Response
    {
        $username = trim($this->request->input('username', ''));
        $email = trim($this->request->input('email', ''));
        $password = $this->request->input('password', '');

        try {
            if (strlen($password) < 8) {
                throw new \Exception("Password must be at least 8 characters long.");
            }
            if (strlen($username) < 3) {
                throw new \Exception("Username must be at least 3 characters long.");
            }

            $this->auth->register($username, $email, $password);
            
            // Auto-login after registration
            $this->auth->login($email, $password);
            return Response::redirect('settings');
        } catch (Throwable $e) {
            return $this->showRegister($e->getMessage());
        }
    }

    /**
     * Log out the user.
     */
    public function logout(): Response
    {
        $this->auth->logout();
        return Response::redirect('login');
    }
}
