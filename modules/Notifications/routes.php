<?php

/** @var \Core\Router $router */

$router->get('/api/notifications/unread', [\Modules\Notifications\Controllers\NotificationController::class, 'getUnreadCount']);
