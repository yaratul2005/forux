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

        $errorHtml = $error ? "<div class='alert'>{$error}</div>" : '';
        
        $html = "
        <div class='card'>
            <h2>Login to Forux</h2>
            {$errorHtml}
            <form action='login' method='POST'>
                <div class='form-group'>
                    <label>Email Address</label>
                    <input type='email' name='email' class='form-control' required autofocus>
                </div>
                <div class='form-group'>
                    <label>Password</label>
                    <input type='password' name='password' class='form-control' required>
                </div>
                <button type='submit' class='btn'>Log In</button>
            </form>
            <p style='text-align:center; font-size:0.85rem; margin-top:1.5rem; color:#9ca3af;'>
                Don't have an account? <a href='register' style='color:#10b981; text-decoration:none; font-weight:600;'>Register here</a>
            </p>
        </div>";

        return Response::html($this->renderLayout('Login - Forux', $html));
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

        $errorHtml = $error ? "<div class='alert'>{$error}</div>" : '';

        $html = "
        <div class='card'>
            <h2>Create an Account</h2>
            {$errorHtml}
            <form action='register' method='POST'>
                <div class='form-group'>
                    <label>Username</label>
                    <input type='text' name='username' class='form-control' required autofocus>
                </div>
                <div class='form-group'>
                    <label>Email Address</label>
                    <input type='email' name='email' class='form-control' required>
                </div>
                <div class='form-group'>
                    <label>Password (Min. 8 chars)</label>
                    <input type='password' name='password' class='form-control' required>
                </div>
                <button type='submit' class='btn'>Register Account</button>
            </form>
            <p style='text-align:center; font-size:0.85rem; margin-top:1.5rem; color:#9ca3af;'>
                Already have an account? <a href='login' style='color:#10b981; text-decoration:none; font-weight:600;'>Login here</a>
            </p>
        </div>";

        return Response::html($this->renderLayout('Register - Forux', $html));
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
     * Render the page layout wrapper.
     */
    protected function renderLayout(string $title, string $content): string
    {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                :root {
                    --bg-color: #0b0f19;
                    --card-bg: #111827;
                    --card-border: #1f2937;
                    --text-main: #f3f4f6;
                    --text-muted: #9ca3af;
                    --primary: #10b981;
                    --primary-hover: #059669;
                    --error: #ef4444;
                }
                body {
                    background-color: var(--bg-color);
                    color: var(--text-main);
                    font-family: system-ui, -apple-system, sans-serif;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 1rem;
                    box-sizing: border-box;
                }
                .card {
                    width: 100%;
                    max-width: 440px;
                    background: var(--card-bg);
                    border: 1px solid var(--card-border);
                    border-radius: 16px;
                    padding: 2.5rem;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4);
                    box-sizing: border-box;
                }
                h2 {
                    margin: 0 0 1.5rem 0;
                    font-size: 1.75rem;
                    font-weight: 800;
                    background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    letter-spacing: -0.05em;
                    text-align: center;
                }
                .alert {
                    background: rgba(239, 68, 68, 0.1);
                    border: 1px solid var(--error);
                    border-radius: 8px;
                    color: #f87171;
                    padding: 0.75rem 1rem;
                    font-size: 0.85rem;
                    margin-bottom: 1.25rem;
                    line-height: 1.4;
                }
                .form-group {
                    margin-bottom: 1.25rem;
                }
                .form-group label {
                    display: block;
                    margin-bottom: 0.5rem;
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: #d1d5db;
                }
                .form-control {
                    width: 100%;
                    background: #0f172a;
                    border: 1px solid var(--card-border);
                    border-radius: 8px;
                    padding: 0.75rem 1rem;
                    color: var(--text-main);
                    font-size: 0.95rem;
                    box-sizing: border-box;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }
                .form-control:focus {
                    outline: none;
                    border-color: var(--primary);
                    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
                }
                .btn {
                    width: 100%;
                    background: var(--primary);
                    color: #fff;
                    border: none;
                    border-radius: 8px;
                    padding: 0.85rem;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background-color 0.2s;
                    margin-top: 0.5rem;
                }
                .btn:hover {
                    background-color: var(--primary-hover);
                }
            </style>
        </head>
        <body>
            {$content}
        </body>
        </html>";
    }
}
