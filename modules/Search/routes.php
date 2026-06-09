<?php

/** @var \Core\Router $router */

$router->get('/search', [\Modules\Search\Controllers\SearchController::class, 'index']);
