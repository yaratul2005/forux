<?php

/** @var \Core\Router $router */

$router->get('/page/{slug}', [\Modules\Pages\Controllers\PagesController::class, 'show']);
