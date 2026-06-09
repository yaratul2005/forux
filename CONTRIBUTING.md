# Contributing to Forux Forum

Welcome to the Forux Forum open-source codebase! We are glad you are interested in contributing.

## Shared Hosting Philosophy
Forux is designed to run efficiently on standard Linux shared hosting environments. To keep it future-proof and accessible:
- Use **pure PHP and MySQL** without external daemon dependencies (like Redis or WebSockets).
- Leverage the **filesystem** for cache/log files when Redis is unavailable.
- Do not introduce server-side node/npm build steps for runtime theme execution. Keep JavaScript vanilla or locally vendored.
- Avoid large frameworks; write custom, autowired classes that register with our DI Container.

## Directory Structure
- `/app` - Business services, global controllers, and middlewares.
- `/core` - Application kernel (Autoloader, Router, View engine, Hooks, Cache).
- `/modules` - Plug-and-play modules (each holding its own Controllers, Services, migrations, and routes).
- `/themes` - Visual template files and assets. Default is `/themes/default`.
- `/storage` - Log outputs, partial caches, and local upload staging.

## Coding Standards
- Follow standard PSR-12 coding guidelines.
- Use explicit Prepared Statements for all database queries to prevent SQL injections.
- Escape all output data using `htmlspecialchars()` inside views, or pass content through `Core\HtmlSanitizer`.
- Keep inputs raw inside the `Request` payload (escaping happens at output rendering, not input capture).

## Creating a Module
1. Create a directory `/modules/YourModuleName`.
2. Add a `module.php` manifest registering hooks and metadata:
   ```php
   return [
       'name' => 'Your Module',
       'version' => '1.0.0',
       'hooks' => [
           'forum.thread_created' => 'onThreadCreated'
       ]
   ];
   ```
3. Add a `routes.php` file to register route callbacks with the `Router`.
4. Drop-in migration files under `/modules/YourModuleName/migrations/` if needed.
