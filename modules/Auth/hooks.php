<?php

/** @var \Core\Hook $hook */

$hook->addAction('auth.registered', function (array $user) {
    // Log event or prepare notification (Phase 5 continued)
});

$hook->addAction('auth.logged_in', function (array $user) {
    // Audit action or update last login state (Phase 5 continued)
});
