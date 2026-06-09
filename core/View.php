<?php

namespace Core;

use App\Services\Auth\AuthService; // Just in case, let's keep it flexible
use Core\Response;
use Core\Container;
use Core\Settings;

/**
 * View Rendering Engine
 */
class View
{
    /**
     * Render a template and return a Response.
     *
     * @param string $template
     * @param array $data
     * @param int $status
     * @return Response
     */
    public static function render(string $template, array $data = [], int $status = 200): Response
    {
        $container = Container::getInstance();
        $settings = $container->has(Settings::class) ? $container->get(Settings::class) : null;
        
        $activeTheme = 'default';
        if ($settings) {
            $activeTheme = $settings->get('active_theme', 'default');
        }

        // Helper to resolve template path with fallback to default
        $resolvePath = function(string $tplName) use ($activeTheme) {
            $themePath = ROOT_PATH . "/themes/{$activeTheme}/templates/{$tplName}.php";
            if (file_exists($themePath)) {
                return $themePath;
            }
            // Fallback to default theme
            $defaultPath = ROOT_PATH . "/themes/default/templates/{$tplName}.php";
            if (file_exists($defaultPath)) {
                return $defaultPath;
            }
            return null;
        };

        $templatePath = $resolvePath($template);
        if (!$templatePath) {
            throw new \Exception("Template [{$template}] not found in theme [{$activeTheme}] or [default].");
        }

        // Resolve common view data/helpers
        $auth = null;
        $currentUser = null;
        $isAdmin = false;
        // In Forux, AuthService class name might be different (e.g. Modules\Auth\Services\AuthService)
        // Let's resolve it dynamically by searching container bindings/instances
        foreach (['Modules\\Auth\\Services\\AuthService', 'App\\Services\\AuthService', 'AuthService'] as $authClass) {
            if ($container->has($authClass)) {
                $auth = $container->get($authClass);
                $currentUser = $auth->user();
                break;
            }
        }

        if ($currentUser && $container->has(\PDO::class)) {
            try {
                $pdo = $container->get(\PDO::class);
                $stmt = $pdo->prepare("
                    SELECT r.name FROM user_roles ur
                    JOIN roles r ON ur.role_id = r.id
                    WHERE ur.user_id = ?
                ");
                $stmt->execute([$currentUser['id']]);
                $roles = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                $isAdmin = in_array('Admin', $roles) || in_array('Super Admin', $roles);
            } catch (\Throwable $e) {
                // Ignore DB errors in view
            }
        }
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        $basePath = str_replace('\\', '/', $basePath);
        if ($basePath === '/') {
            $basePath = '';
        }
        $baseUrl = $basePath;

        $siteName = $settings ? $settings->get('site_name', 'Forux') : 'Forux';
        $accentColor = $settings ? $settings->get('accent_color', '#10b981') : '#10b981';
        $accentHover = $settings ? $settings->get('accent_color_hover', '#059669') : '#059669';
        $adminPath = $settings ? $settings->get('admin_path', 'admin') : 'admin';

        $csrfToken = '';
        if ($auth && method_exists($auth, 'getCsrfToken')) {
            $csrfToken = $auth->getCsrfToken();
        }

        // Check if there are unread notifications
        $unreadNotificationsCount = 0;
        if ($currentUser && $container->has(\PDO::class)) {
            try {
                $pdo = $container->get(\PDO::class);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE notifiable_id = ? AND read_at IS NULL");
                $stmt->execute([$currentUser['id']]);
                $unreadNotificationsCount = (int)$stmt->fetchColumn();
            } catch (\Throwable $e) {
                // Ignore DB errors in view
            }
        }

        // Merge default variables with user-provided data
        $mergedData = array_merge([
            'siteName' => $siteName,
            'accentColor' => $accentColor,
            'accentHover' => $accentHover,
            'adminPath' => $adminPath,
            'currentUser' => $currentUser,
            'isAdmin' => $isAdmin,
            'auth' => $auth,
            'csrfToken' => $csrfToken,
            'unreadNotificationsCount' => $unreadNotificationsCount,
            'title' => $siteName,
            'baseUrl' => $baseUrl,
            'layout' => true, // wrap in layout by default
        ], $data);

        // Render main template
        $content = self::renderFile($templatePath, $mergedData);

        // Wrap in layout if required
        if ($mergedData['layout']) {
            $layoutPath = $resolvePath('layout');
            if ($layoutPath) {
                $layoutData = array_merge($mergedData, ['content' => $content]);
                $content = self::renderFile($layoutPath, $layoutData);
            }
        }

        return Response::html($content, $status);
    }

    /**
     * Render a single PHP file with data.
     *
     * @param string $filePath
     * @param array $data
     * @return string
     */
    protected static function renderFile(string $filePath, array $data): string
    {
        extract($data);
        ob_start();
        try {
            include $filePath;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }
}
