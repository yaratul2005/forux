<?php

/** @var \Core\Router $router */

$router->get('/messages', [\Modules\Messaging\Controllers\MessagingController::class, 'inbox']);
$router->get('/messages/new', [\Modules\Messaging\Controllers\MessagingController::class, 'newConversationForm']);
$router->post('/messages/new', [\Modules\Messaging\Controllers\MessagingController::class, 'createConversation']);
$router->get('/messages/{id:\d+}', [\Modules\Messaging\Controllers\MessagingController::class, 'showConversation']);
$router->post('/messages/{id:\d+}', [\Modules\Messaging\Controllers\MessagingController::class, 'reply']);
