<?php

/** @var \Core\Router $router */

$router->get('/api/v1/categories', [\Modules\API\Controllers\ApiController::class, 'categories']);
$router->get('/api/v1/threads', [\Modules\API\Controllers\ApiController::class, 'threads']);
