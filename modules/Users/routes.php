<?php

/** @var \Core\Router $router */

$router->get('/user/{username}', [\Modules\Users\Controllers\UserController::class, 'profile']);
$router->post('/user/{username}/follow', [\Modules\Users\Controllers\UserController::class, 'follow']);
$router->post('/user/{username}/unfollow', [\Modules\Users\Controllers\UserController::class, 'unfollow']);
$router->post('/user/{username}/block', [\Modules\Users\Controllers\UserController::class, 'block']);
$router->post('/user/{username}/unblock', [\Modules\Users\Controllers\UserController::class, 'unblock']);
$router->get('/settings', [\Modules\Users\Controllers\UserController::class, 'settings']);
$router->post('/settings', [\Modules\Users\Controllers\UserController::class, 'updateSettings']);

// API endpoints
$router->get('/api/users/search', [\Modules\Users\Controllers\UserController::class, 'search']);
