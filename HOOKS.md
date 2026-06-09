# Forux Forum Hook System

Forux utilizes a lightweight synchronous observer pattern to allow core features and plug-and-play modules to communicate without direct file modifications.

## Registering Hooks

To hook into an event from a module, declare the mapping inside the module manifest (`module.php`):
```php
return [
    'name' => 'My Plugin',
    'hooks' => [
        'auth.registered' => 'onUserRegistered',
        'forum.thread_created' => 'onThreadCreated'
    ]
];
```

## Available Actions

Actions are fired to notify listeners that an operation has completed. Callbacks do not return values.

### `cron.minute`
- **Trigger**: Fired every minute by the background cron script (`cron.php`).
- **Payload**: None.
- **Usage**: Trigger automated cleanups, email spool queues, or analytics aggregation.

### `auth.registered`
- **Trigger**: Fired immediately after a new user registers successfully.
- **Payload**: `array ['id' => int, 'username' => string, 'email' => string]`
- **Usage**: Trigger welcome emails, create default user profiles, or seed notifications.

### `auth.logged_in`
- **Trigger**: Fired immediately upon successful password authentication.
- **Payload**: `array` user profile details.
- **Usage**: Update last login activity or clear IP lockout attempts.

### `forum.thread_created`
- **Trigger**: Fired inside a transaction immediately after a thread and its first post are written.
- **Payload**: `array ['thread_id' => int, 'category_id' => int, 'user_id' => int, 'title' => string, 'slug' => string]`
- **Usage**: Trigger slack/webhook integrations, notification broadcasts, or AI moderation scan.

### `forum.reply_created`
- **Trigger**: Fired immediately after a reply post is created.
- **Payload**: `array ['post_id' => int, 'thread_id' => int, 'user_id' => int, 'body' => string]`
- **Usage**: Alert thread subscribers or check AI spam filters.

### `forum.post_updated`
- **Trigger**: Fired immediately after a post body is updated and old revision logged.
- **Payload**: `array ['post_id' => int, 'user_id' => int, 'body' => string]`
- **Usage**: Audit logs or search re-indexing.

### `forum.post_deleted`
- **Trigger**: Fired immediately after a post is soft-deleted.
- **Payload**: `array ['post_id' => int]`
- **Usage**: Retract reputation points or delete notifications associated with the post.
