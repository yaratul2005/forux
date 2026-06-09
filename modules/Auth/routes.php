<?php

/** @var \Core\Router $router */

$router->get('/register', [\Modules\Auth\Controllers\AuthController::class, 'showRegister']);
$router->post('/register', [\Modules\Auth\Controllers\AuthController::class, 'register']);
$router->get('/login', [\Modules\Auth\Controllers\AuthController::class, 'showLogin']);
$router->post('/login', [\Modules\Auth\Controllers\AuthController::class, 'login']);
$router->post('/logout', [\Modules\Auth\Controllers\AuthController::class, 'logout']);

// Password Recovery
$router->get('/password/reset', [\Modules\Auth\Controllers\AuthController::class, 'showForgot']);
$router->post('/password/reset', [\Modules\Auth\Controllers\AuthController::class, 'sendResetLink']);
$router->get('/password/reset/{token}', [\Modules\Auth\Controllers\AuthController::class, 'showReset']);
$router->post('/password/reset/{token}', [\Modules\Auth\Controllers\AuthController::class, 'resetPassword']);

// Email Verification
$router->get('/verify-email/{token}', [\Modules\Auth\Controllers\AuthController::class, 'verifyEmail']);
