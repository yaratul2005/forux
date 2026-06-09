<?php

/** @var \Core\Router $router */

$router->get('/', [\Modules\Forum\Controllers\ForumController::class, 'index']);
$router->get('/category/{slug}', [\Modules\Forum\Controllers\ForumController::class, 'showCategory']);
$router->get('/thread/{slug}', [\Modules\Forum\Controllers\ForumController::class, 'showThread']);

$router->get('/thread/new/{category_id:\d+}', [\Modules\Forum\Controllers\ForumController::class, 'createThreadForm']);
$router->post('/thread/new/{category_id:\d+}', [\Modules\Forum\Controllers\ForumController::class, 'createThread']);

$router->post('/post/reply/{thread_id:\d+}', [\Modules\Forum\Controllers\ForumController::class, 'createReply']);

$router->get('/post/edit/{id:\d+}', [\Modules\Forum\Controllers\ForumController::class, 'editPostForm']);
$router->post('/post/edit/{id:\d+}', [\Modules\Forum\Controllers\ForumController::class, 'updatePost']);

$router->post('/post/delete/{id:\d+}', [\Modules\Forum\Controllers\ForumController::class, 'deletePost']);
$router->post('/post/react/{id:\d+}', [\Modules\Forum\Controllers\ForumController::class, 'react']);

// API endpoints
$router->post('/api/upload', [\App\Controllers\UploadController::class, 'upload']);
