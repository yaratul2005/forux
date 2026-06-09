<?php

/** @var \Core\Router $router */

$router->get('/register', [\Modules\Auth\Controllers\AuthController::class, 'showRegister']);
$router->post('/register', [\Modules\Auth\Controllers\AuthController::class, 'register']);
$router->get('/login', [\Modules\Auth\Controllers\AuthController::class, 'showLogin']);
$router->post('/login', [\Modules\Auth\Controllers\AuthController::class, 'login']);
$router->post('/logout', [\Modules\Auth\Controllers\AuthController::class, 'logout']);
