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
    public function showLogin(?string $error = null, ?string $success = null): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('settings'); // Redirect to profile settings if already logged in
        }

        return \Core\View::render('login', [
            'error' => $error,
            'success' => $success,
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

    /**
     * Render the forgot password form page.
     */
    public function showForgot(?string $error = null, ?string $success = null): Response
    {
        return \Core\View::render('forgot_password', [
            'error' => $error,
            'success' => $success,
            'title' => 'Recover Password - Forux'
        ]);
    }

    /**
     * Process sending reset link email.
     */
    public function sendResetLink(): Response
    {
        $email = trim($this->request->input('email', ''));

        if ($this->auth->sendPasswordResetLink($email)) {
            return $this->showForgot(null, 'A password reset link has been sent to your email.');
        }

        return $this->showForgot('Unable to send password reset link. Please check the email address.');
    }

    /**
     * Render the reset password form page.
     */
    public function showReset(string $token, ?string $error = null): Response
    {
        $email = $this->auth->validateResetToken($token);
        if (!$email) {
            return \Core\View::render('error', [
                'title' => 'Invalid Token',
                'message' => 'The password reset link is invalid or has expired.'
            ], 400);
        }

        return \Core\View::render('reset_password', [
            'token' => $token,
            'error' => $error,
            'title' => 'Reset Password - Forux'
        ]);
    }

    /**
     * Process actual password reset.
     */
    public function resetPassword(string $token): Response
    {
        $password = $this->request->input('password', '');
        $passwordConfirm = $this->request->input('password_confirm', '');

        if (strlen($password) < 8) {
            return $this->showReset($token, 'Password must be at least 8 characters long.');
        }

        if ($password !== $passwordConfirm) {
            return $this->showReset($token, 'Passwords do not match.');
        }

        if ($this->auth->resetPassword($token, $password)) {
            return $this->showLogin(null, 'Your password has been successfully reset. You can now log in.');
        }

        return $this->showReset($token, 'Unable to reset password. Please request a new link.');
    }

    /**
     * Process email verification.
     */
    public function verifyEmail(string $token): Response
    {
        if ($this->auth->verifyEmail($token)) {
            return $this->showLogin(null, 'Your email has been verified. You can now log in.');
        }

        return \Core\View::render('error', [
            'title' => 'Verification Failed',
            'message' => 'The verification link is invalid or has expired.'
        ], 400);
    }
}
