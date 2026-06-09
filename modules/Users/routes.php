<?php

/** @var \Core\Router $router */

$router->get('/user/{username}', [\Modules\Users\Controllers\UserController::class, 'profile']);
$router->get('/settings', [\Modules\Users\Controllers\UserController::class, 'settings']);
$router->post('/settings', [\Modules\Users\Controllers\UserController::class, 'updateSettings']);
