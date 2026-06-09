<?php

/** @var \Core\Router $router */
/** @var \Core\Container $container */

$config = $container->get('config');
$adminPath = $config['app']['admin_path'] ?? 'admin';

// Group all administration routes under the custom non-guessable admin path prefix
$router->group([
    'prefix' => '/' . trim($adminPath, '/'),
    'middleware' => [\App\Middleware\AdminMiddleware::class]
], function ($r) {
    
    // 1. Dashboard
    $r->get('/dashboard', [\Modules\Admin\Controllers\AdminController::class, 'dashboard']);

    // 2. Category Management
    $r->get('/categories', [\Modules\Admin\Controllers\AdminController::class, 'categories']);
    $r->post('/categories/create', [\Modules\Admin\Controllers\AdminController::class, 'createCategory']);
    $r->get('/categories/edit/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'editCategoryForm']);
    $r->post('/categories/edit/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'updateCategory']);
    $r->post('/categories/delete/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'deleteCategory']);
    $r->post('/categories/reorder', [\Modules\Admin\Controllers\AdminController::class, 'reorderCategories']);

    // 3. User Management
    $r->get('/users', [\Modules\Admin\Controllers\AdminController::class, 'users']);
    $r->post('/users/role/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'updateUserRole']);
    $r->post('/users/ban/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'banUser']);
    $r->post('/users/warn/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'warnUser']);

    // 4. Content moderation (Threads & Posts)
    $r->get('/content', [\Modules\Admin\Controllers\AdminController::class, 'content']);
    $r->post('/threads/move/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'moveThread']);
    $r->post('/threads/lock/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'lockThread']);
    $r->post('/posts/delete/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'deletePost']);
    $r->get('/posts/revisions/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'postRevisions']);

    // 5. Moderation Reports Queue
    $r->get('/reports', [\Modules\Admin\Controllers\AdminController::class, 'reports']);
    $r->post('/reports/resolve/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'resolveReport']);

    // 6. Module Manager
    $r->get('/modules', [\Modules\Admin\Controllers\AdminController::class, 'modules']);
    $r->post('/modules/toggle/{name:[a-zA-Z0-9_-]+}', [\Modules\Admin\Controllers\AdminController::class, 'toggleModule']);

    // 7. General Settings
    $r->get('/settings', [\Modules\Admin\Controllers\AdminController::class, 'settings']);
    $r->post('/settings/save', [\Modules\Admin\Controllers\AdminController::class, 'saveSettings']);

    // 8. Credentials Vault
    $r->get('/vault', [\Modules\Admin\Controllers\AdminController::class, 'vault']);
    $r->post('/vault/save', [\Modules\Admin\Controllers\AdminController::class, 'saveCredentials']);
    $r->post('/vault/test', [\Modules\Admin\Controllers\AdminController::class, 'testCredentials']);

    // 9. Static CMS Pages
    $r->get('/pages', [\Modules\Admin\Controllers\AdminController::class, 'pages']);
    $r->get('/pages/create', [\Modules\Admin\Controllers\AdminController::class, 'createPageForm']);
    $r->post('/pages/create', [\Modules\Admin\Controllers\AdminController::class, 'createPage']);
    $r->get('/pages/edit/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'editPageForm']);
    $r->post('/pages/edit/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'updatePage']);
    $r->post('/pages/delete/{id:\d+}', [\Modules\Admin\Controllers\AdminController::class, 'deletePage']);

    // 10. Utilities (Backup and Logs)
    $r->get('/utilities/logs', [\Modules\Admin\Controllers\AdminController::class, 'viewLogs']);
    $r->post('/utilities/logs/clear', [\Modules\Admin\Controllers\AdminController::class, 'clearLogs']);
    $r->get('/utilities/backup', [\Modules\Admin\Controllers\AdminController::class, 'triggerBackup']);
});
