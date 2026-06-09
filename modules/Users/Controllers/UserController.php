<?php

namespace Modules\Users\Controllers;

use Core\Response;
use Core\Request;
use Modules\Users\Services\UserService;
use Modules\Auth\Services\AuthService;
use Throwable;

/**
 * Controller handling user profiles and settings views
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
            return Response::html($this->renderLayout('User Not Found', "<div class='card' style='text-align:center;'><h2>404 User Not Found</h2><p>The user @{$username} does not exist.</p></div>"), 404);
        }

        $avatar = $user['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp&s=120';
        $bio = $user['bio'] ? htmlspecialchars($user['bio']) : "<em>No bio provided yet.</em>";
        $location = $user['location'] ? htmlspecialchars($user['location']) : "<em>Unknown</em>";

        $html = "
        <div class='card profile-card'>
            <div style='text-align:center; margin-bottom:1.5rem;'>
                <img src='{$avatar}' alt='Avatar' style='width:96px; height:96px; border-radius:50%; border:3px solid var(--primary); background:#1f2937;'>
                <h2 style='margin-bottom:0.25rem; font-size:1.5rem;'>{$user['username']}</h2>
                <div style='font-size:0.85rem; color:var(--text-muted);'>Member since " . date('M Y', strtotime($user['created_at'])) . "</div>
            </div>
            
            <div style='border-top:1px solid var(--card-border); padding-top:1rem; margin-top:1rem;'>
                <div style='margin-bottom:0.75rem;'>
                    <strong style='font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:0.25rem;'>REPUTATION POINTS</strong>
                    <span style='font-size:1.2rem; font-weight:700; color:var(--primary);'>★ {$user['reputation_points']}</span>
                </div>
                <div style='margin-bottom:0.75rem;'>
                    <strong style='font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:0.25rem;'>LOCATION</strong>
                    <span>{$location}</span>
                </div>
                <div>
                    <strong style='font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:0.25rem;'>ABOUT ME</strong>
                    <div style='line-height:1.4; color:#d1d5db; font-size:0.9rem;'>{$bio}</div>
                </div>
            </div>
            
            <div style='margin-top:2rem; text-align:center;'>
                <a href='../settings' style='font-size:0.85rem; color:var(--primary); text-decoration:none; font-weight:600;'>Go to Settings</a>
            </div>
        </div>";

        return Response::html($this->renderLayout("@{$user['username']} - Forux Profile", $html));
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
        $bio = htmlspecialchars($user['bio'] ?? '');
        $location = htmlspecialchars($user['location'] ?? '');

        $alertHtml = '';
        if ($error) {
            $alertHtml = "<div class='alert error'>{$error}</div>";
        } elseif ($success) {
            $alertHtml = "<div class='alert success'>{$success}</div>";
        }

        $html = "
        <div class='card'>
            <h2>Profile Settings</h2>
            {$alertHtml}
            <div style='display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; background:#0f172a; padding:0.75rem; border-radius:8px; border:1px solid var(--card-border);'>
                <img src='https://www.gravatar.com/avatar/" . md5(strtolower(trim($user['email']))) . "?d=mp&s=60' alt='Avatar' style='border-radius:50%; width:48px; height:48px; border:1px solid var(--card-border);'>
                <div>
                    <div style='font-weight:700;'>{$user['username']}</div>
                    <div style='font-size:0.8rem; color:var(--text-muted);'>{$user['email']}</div>
                </div>
            </div>
            
            <form action='settings' method='POST'>
                <div class='form-group'>
                    <label>Location</label>
                    <input type='text' name='location' class='form-control' value='{$location}' placeholder='e.g. London, UK'>
                </div>
                <div class='form-group'>
                    <label>About Me (Bio)</label>
                    <textarea name='bio' class='form-control' rows='4' placeholder='Tell the community about yourself...'>{$bio}</textarea>
                </div>
                <button type='submit' class='btn'>Save Profile</button>
            </form>
            
            <div style='display:flex; justify-content:space-between; margin-top:2rem; font-size:0.85rem; border-top:1px solid var(--card-border); padding-top:1rem;'>
                <a href='user/{$user['username']}' style='color:var(--primary); text-decoration:none;'>View Public Profile</a>
                <form action='logout' method='POST' style='display:inline;'>
                    <button type='submit' style='background:none; border:none; color:#ef4444; cursor:pointer; font-size:0.85rem; font-family:inherit; padding:0;'>Log Out</button>
                </form>
            </div>
        </div>";

        return Response::html($this->renderLayout('Profile Settings - Forux', $html));
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

        try {
            $this->userService->updateProfile($user['id'], [
                'location' => $location,
                'bio' => $bio
            ]);
            return $this->settings(null, 'Profile successfully updated.');
        } catch (Throwable $e) {
            return $this->settings($e->getMessage());
        }
    }

    /**
     * Render page layout.
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
                    --success: #10b981;
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
                    border-radius: 8px;
                    padding: 0.75rem 1rem;
                    font-size: 0.85rem;
                    margin-bottom: 1.25rem;
                    line-height: 1.4;
                }
                .alert.error {
                    background: rgba(239, 68, 68, 0.1);
                    border: 1px solid var(--error);
                    color: #f87171;
                }
                .alert.success {
                    background: rgba(16, 185, 129, 0.1);
                    border: 1px solid var(--success);
                    color: #34d399;
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
                    font-family: inherit;
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
