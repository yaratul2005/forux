# Master Forum Build — Prompt Engineering Guide
### Written from the desk of a seasoned PHP developer
### For use with an AI coding assistant — shared hosting aware, open source, future-proof

---

> **How to use this guide:** You are not handing this to an AI and walking away. You are having a conversation with a builder. Feed the AI one section at a time, in order. Read its output, confirm it matches the intent described here, then move to the next. Never skip a phase. The AI is your junior developer — a brilliant one, but it needs your direction on scope and sequence.

---

## PREFACE — What We Are Actually Building

We are building a self-hosted, open source, PHP-driven community forum platform with a full-featured admin CMS backend. This is not a WordPress plugin. This is not a Laravel starter kit someone scaffolded in five minutes. This is a purpose-built forum system — clean, extensible, and light enough to run on shared hosting without choking.

The philosophy is simple: the core does one thing perfectly, and everything else plugs into it. Every feature that touches an external service — email delivery, AI moderation, OAuth login, storage providers — lives behind an interface that the admin can configure and activate from the CMS backend. No API keys hardcoded anywhere in the codebase. The admin unlocks those features themselves, through the panel, when they are ready. The software ships dormant. The admin brings it alive.

This must run comfortably on a shared Linux host like DreamHost. That means PHP is your engine, MySQL is your database, the filesystem is your only guaranteed persistent storage, and `.htaccess` with `mod_rewrite` is how you control routing. You do not need Redis. You do not need Node. You do not need Elasticsearch. You build as if those luxuries do not exist — and you build something that would embarrass platforms that require them.

---

## PHASE 0 — Ground Rules Before Any Code Is Written

Tell the AI the following, word for word, before you begin any build session:

---

*"You are a senior PHP developer building a production-grade forum from scratch. The target environment is shared Linux hosting — specifically a host like DreamHost — which gives us: PHP (any framework, no sudo required), MySQL, SQLite, Perl, Python, CGI scripts, Ruby via RVM, SSH access, Git, cURL, mod_rewrite via .htaccess, OpenSSL, JSON, SOAP, and HTTP/2. We do not have Redis, Elasticsearch, Docker, WebSockets, Node.js, or PostgreSQL natively available. We must architect around these constraints without apologizing for them. Every clever thing we do must be clever inside these boundaries.*

*This project is open source and must be structured so that a future developer — or a future version of you — can extend it without reading my mind. Directory structure is documentation. File names are contracts. Nothing is magic."*

---

This sets the mental model. Do not skip it.

---

## PHASE 1 — Directory Architecture

Before writing a single line of PHP, define the project structure. Instruct the AI to produce this and nothing else in the first session. Review it. Argue with it. Only lock it once you own it mentally.

The structure should follow this philosophy:

The **public web root** is the only directory Apache touches. Everything else — configuration, application logic, libraries, templates, uploads awaiting processing — lives **above** the web root and is unreachable from a browser. This is non-negotiable for security.

Walk the AI through this intent:

- There is a `public/` folder. This is `document_root`. Only `index.php`, compiled assets, and the upload-serving proxy live here. Direct file access to anything sensitive must be blocked.
- There is a `app/` folder. This is where all PHP lives: controllers, models, services, helpers, middleware.
- There is a `core/` folder. This is the kernel — the router, the dispatcher, the base classes, the hook/event system. The core never knows about the forum. The forum knows about the core.
- There is a `modules/` folder. Every major feature of the forum — threads, users, search, notifications, admin — lives in its own module. A module is a directory with a predictable internal structure. The application discovers modules; modules don't register themselves manually.
- There is a `storage/` folder. Cache files, session files if you use filesystem sessions, and the upload staging area live here.
- There is a `config/` folder. Environment-specific configuration. No credentials in this folder are ever committed to version control. There is a `config.example.php` that shows all required keys with placeholder values.
- There is a `themes/` folder. The default theme lives here. Every theme is self-contained — its own templates, its own CSS, its own JS. The core renders by asking the active theme for a template file. If the theme doesn't have it, it falls back to the default.
- There is an `install/` folder. This is the setup wizard. It self-destructs — or is locked by a flag file — after first-run installation is confirmed.

Instruct the AI: *"Give me the full proposed directory tree for this project with a one-sentence explanation for every top-level folder and every key subfolder. Do not write any PHP yet."*

---

## PHASE 2 — The Core Kernel

The kernel is the part of the application that has no idea it is a forum. It only knows how to receive a request, route it, dispatch it to a handler, and return a response. This separation is what makes future upgrades survivable.

The four things the kernel must do:

**Routing** — Pure `.htaccess` rewrites funnel every request to `public/index.php`. From there, a PHP router reads the URI, matches it against registered routes, and hands off to the correct controller method. The router must support named routes, route parameters, optional segments, and route groups with shared middleware. Because we are on shared hosting, we implement this entirely in PHP without any compiled binaries.

**Hooks and Events** — This is the most important architectural decision in the entire project. Every significant action in the application — a post is created, a user registers, a thread is locked — fires a named event. Other parts of the system, including modules and plugins installed later, can listen to these events and react without modifying core files. This is how the forum stays extensible without becoming a mess. The hook system is a simple observer pattern. It does not require a message queue. It runs synchronously. That is fine for shared hosting.

**Service Container** — A lightweight dependency injection container. Not a full framework DI system — a practical one. The application registers services (the database connection, the mailer, the cache driver, the session handler) and anything that needs them asks the container. This is how you swap a file-based cache for something better later without touching every file that uses caching.

**Middleware Pipeline** — Before a request reaches a controller, it passes through a pipeline: authentication check, CSRF validation, rate limiting, maintenance mode detection. Middleware is composable. Route groups can declare their own middleware stack.

Instruct the AI: *"Build the kernel in isolation. No forum features yet. Show me a working router, a hook dispatcher, a minimal service container, and a middleware pipeline. Write it so that none of these components knows the other exists — they are wired together only in the bootstrap file."*

---

## PHASE 3 — Database Design

The database is the hardest thing to change later. Design it properly now or pay for it forever.

The database design conversation with the AI should be driven by these principles:

Every table has `id`, `created_at`, and `updated_at` as a minimum. Soft deletion is implemented via a `deleted_at` nullable column — never physical deletes on content, because moderation requires history. Foreign keys are defined but enforcement behavior (CASCADE vs. RESTRICT) is explicit and deliberate per relationship.

Walk the AI through building these table groups in order:

**Identity and Access** — users, roles, permissions, role_permission pivot, user_role pivot, password_reset_tokens, email_verification_tokens, oauth_accounts (for when OAuth is unlocked later), user_sessions, two_factor_auth.

**Forum Structure** — categories, sub_categories (self-referencing or separate, discuss this with the AI), threads, posts, post_revisions (every edit saved), thread_tags, tag pivot.

**Interaction** — reactions (a single flexible table: `reactable_type`, `reactable_id`, `user_id`, `reaction_type`), bookmarks, thread_subscriptions, user_follows, user_blocks.

**Messaging** — private_conversations, private_messages, private_conversation_participants.

**Moderation** — reports (`reportable_type`, `reportable_id`), moderation_actions (full log), bans, warnings, ip_ban_list.

**Notifications** — notifications (polymorphic: `notifiable_type`, `notifiable_id`, `type`, `data` as JSON, `read_at`).

**Search** — Because we have no Elasticsearch, we will use MySQL FULLTEXT indexes on thread titles and post content. Design the schema with this in mind from the start.

**CMS and Configuration** — settings (key-value store for all admin-configurable values), pages (static CMS pages), navigation_menus, themes_config, installed_modules.

**Credentials Vault** — This is critical and unique to this project's philosophy. A `service_credentials` table stores third-party API keys, SMTP settings, OAuth client IDs and secrets — anything an admin configures in the backend. Columns: `service_name`, `credential_key`, `credential_value` (encrypted at rest using OpenSSL and a server-side key), `is_active`. The application checks this table to determine whether a feature is unlocked. No credentials live in config files. The admin sets them through the CMS.

Instruct the AI: *"Write the complete database migration scripts for all table groups above. Use procedural PHP with raw PDO, not an ORM-dependent migration runner. Each migration file is standalone and includes both an `up()` and `down()` function. Number them sequentially."*

---

## PHASE 4 — The Configuration and Install System

Before any feature is built, the installation wizard and configuration system must exist, because everything else depends on them.

The install wizard runs once. It:
- Checks PHP version, required extensions (PDO, PDO_MySQL, cURL, OpenSSL, JSON, mbstring), and directory write permissions
- Accepts database credentials and verifies connection
- Runs all migrations in order
- Creates the first admin account
- Writes a `config/config.php` from a template
- Creates a `storage/installed.lock` file
- Redirects to the forum homepage

After the lock file exists, the install directory returns a 403. The `.htaccess` enforces this.

The configuration system works like this: there are two layers. `config/config.php` holds environment-level constants that never change at runtime — database host, base URL, encryption key salt, debug mode. The `settings` database table holds everything an admin can change at runtime — site name, registration mode, posts per page, which theme is active, which modules are enabled, mail server settings, and so on. The application reads config file values once at boot. Settings table values are cached to a flat PHP file in `storage/cache/settings.php` and regenerated whenever an admin saves changes. This means zero database queries per request just to load site settings.

---

## PHASE 5 — Module Architecture

This is where the forum's personality comes from, but the structure must be established before any module is actually built.

Every module lives in `modules/{ModuleName}/` and follows this internal layout:

- `module.php` — The manifest. Declares the module's name, version, description, author, dependencies, and the list of hooks it listens to.
- `Controllers/` — HTTP handlers for this module's routes.
- `Models/` — Data access layer for this module's tables.
- `Services/` — Business logic that is independent of HTTP and database implementation.
- `Views/` — Module-specific templates. Overridable by the active theme.
- `routes.php` — Route definitions that the core router imports when the module is active.
- `migrations/` — Database migrations owned by this module.
- `hooks.php` — Hook listener registrations.
- `lang/` — Translation strings.

The module loader, sitting in the core, scans the `modules/` directory, reads each `module.php`, checks if that module is enabled in the settings table, and if so, bootstraps it: loads its routes, registers its hooks, and makes its services available in the container.

This means adding a feature to the forum is as simple as dropping a folder into `modules/`. Removing a feature is disabling it in the admin panel. No file surgery required.

Build the following modules, in this order, because each depends on the last:

1. **Auth** — Registration, login, logout, password reset, email verification, session management
2. **Users** — Profiles, avatars, reputation, follow/block
3. **Forum** — Categories, threads, posts, reactions, quoting, rich text
4. **Search** — MySQL FULLTEXT-based search with filters
5. **Notifications** — In-app notification system, mark as read, preference management
6. **Messaging** — Private conversations
7. **Moderation** — Reports, moderation queue, bans, warnings, audit log
8. **Admin** — The entire CMS backend (see Phase 7)
9. **Pages** — Static CMS pages
10. **API** — RESTful endpoints for each resource (for future mobile or headless use)

---

## PHASE 6 — The Service Architecture and the Credentials Vault

This is the section that describes your "admin unlocks features" philosophy in technical terms.

For every feature that requires an external service, build an interface first. Then build a null/fallback implementation that ships active by default, and the real implementation that activates only when the admin has configured credentials.

**Mail** — The system needs to send emails for registration, password resets, and notifications. The mail service interface defines `send(to, subject, body, headers)`. The default implementation writes emails to a `storage/mail_queue/` directory as `.eml` files and uses PHP's native `mail()` function as a fallback — this works on shared hosting out of the box. When an admin enters SMTP credentials in the admin panel, the system switches to the SMTP implementation using PHP's socket functions via cURL. When the admin later enters a SendGrid or Mailgun API key through the credentials vault, the system upgrades to that. The forum never goes offline during this transition. It just gets better.

**File Storage** — Uploaded images and attachments default to local filesystem storage in a controlled `storage/uploads/` directory. Files are served through a PHP proxy script that checks permissions before sending bytes — never direct public folder access. When the admin configures an S3-compatible endpoint (DreamObjects is available on DreamHost), the storage driver switches to remote. The upload code never changes. Only the storage driver does.

**Search** — Defaults to MySQL FULLTEXT. If the admin later migrates to a VPS and sets up Meilisearch, they enter the API URL and key in the credentials vault, and the search service silently upgrades.

**OAuth Login** — The OAuth module ships installed but all providers are dormant. The admin goes to Settings → Authentication → OAuth Providers, enters a Google Client ID and Secret, and flips the toggle. The login page gains a "Sign in with Google" button. Same for GitHub, Discord, and any other provider the module supports. No code changes. No redeploy.

**AI-Assisted Moderation** — A spam detection service interface exists. The default implementation runs a simple keyword filter configured in the admin panel. If the admin enters an API key for an AI provider through the credentials vault, the system passes new posts through that service before they go live. The moderators just see fewer reports. They don't need to know what changed.

Instruct the AI: *"For each service described above, define the PHP interface, the default implementation, and the structure of the credentials vault entry that activates the premium implementation. Do not build the admin UI yet — just the service layer and the activation logic."*

---

## PHASE 7 — The Admin CMS Backend

The admin panel is itself a module — the `Admin` module. It has its own router, its own middleware (admin authentication, role checks), its own layout template, and its own controllers for every management domain.

The admin panel must never be reached through a guessable URL. During installation, the admin chooses a custom admin path. This is stored in settings and enforced by the router. There is no `/admin` by default.

The admin panel is organized around these sections, each a controller group:

**Dashboard** — Aggregated stats pulled from the database: total users, posts today, threads this week, active sessions, recent reports, recent registrations. No external analytics required. All built from the data you already have.

**Category Manager** — Create, edit, delete, reorder (drag-and-drop with a pure JS sortable that saves order via a simple POST), set permissions per category, assign moderators per category.

**User Manager** — Full user table with search, filter by role/status, bulk actions, inline edit for role assignment, full activity view, suspend/ban controls with duration and reason.

**Thread and Post Manager** — Browse all content, filter by category or user, move threads, merge threads, soft-delete posts, view edit history.

**Moderation Queue** — All pending reports in one place. One-click actions. Notes field. Resolution status. Full audit trail.

**Module Manager** — Lists all discovered modules, shows active/inactive status, enable/disable toggle, version info, dependency warnings.

**Theme Manager** — Lists installed themes, preview, activate, and a simple template override system where admins can edit individual theme files through the browser without FTP.

**Settings** — Organized into logical tabs: General, Registration, Email, Security, Performance, SEO, Integrations. Every value here writes to the `settings` table. Changes flush the settings cache file immediately.

**Credentials Vault** — A dedicated section for entering API keys, OAuth credentials, SMTP configuration, and storage provider settings. Each entry shows which feature it unlocks, whether it is currently active, and a "Test Connection" button that validates the credentials before saving. No key is ever displayed in plaintext after being saved — only masked. Editing requires re-entry.

**Pages** — A list of static CMS pages with a rich text editor. Each page has a URL slug, a title, a body, publish/draft status, and SEO fields.

**Backup and Maintenance** — Trigger a database export, view PHP error logs (parsed into readable summaries), toggle maintenance mode with a custom message, clear the application cache.

---

## PHASE 8 — Frontend Architecture

The forum's frontend is driven by server-rendered PHP templates with JavaScript added only where it improves the experience and cannot be replicated with a page load. There is no frontend build step required to run the forum. Themes can optionally use a build process if they want one, but the default theme must work by simply loading files.

The default theme uses a design philosophy of: dark mode first, clean typography, wide content areas, and a sidebar that collapses gracefully on mobile. Color and spacing are controlled by CSS custom properties at the `:root` level — this is how the admin's theme color picker works without compiling anything.

Rich text editing uses a library that outputs clean HTML and can be lightly sanitized server-side. The output is stored as HTML in the database. There is no Markdown-to-HTML compilation step at render time.

JavaScript responsibilities: real-time character counter in post editor, @mention autocomplete (queries a lightweight endpoint), image paste-to-upload in the editor, infinite scroll on thread lists, reaction button animation, notification badge live update via polling (not WebSockets, because shared hosting), and the admin panel's drag-and-drop category sorter.

All JavaScript is vanilla or uses a micro-library small enough to be vendored locally. No CDN dependencies for runtime functionality. If the CDN goes down, the forum must still work.

---

## PHASE 9 — Performance on Shared Hosting

Shared hosting has resource limits. These are not excuses. They are constraints that produce better software.

**Query discipline** — Every page load must have a predictable, capped number of database queries. Thread list pages load in two queries maximum: one for the thread list, one for the associated user data (joined or eagerly loaded). Never query inside a loop. Never.

**Output buffering and partial caching** — Expensive rendering operations — category trees, navigation menus, sidebar widgets — are cached as HTML fragments to `storage/cache/partials/`. A simple time-to-live system regenerates them on a schedule controlled by a cron job or a lazy-expiry check. PHP's `file_put_contents` and `file_get_contents` are the entire caching system. They are faster than you think.

**Database indexes** — Every foreign key column has an index. Every column used in a WHERE clause has an index. FULLTEXT indexes are on the search columns. Review `EXPLAIN` output before calling any query architecture complete.

**Image handling** — Uploaded images are resized server-side using PHP's GD library (universally available on shared hosting) before being saved. The forum stores three sizes: thumbnail, display, and original. Thumbnails are served directly. Display sizes are served through the proxy. Originals are never publicly accessible.

**Cron jobs** — Shared hosting supports cron. Use it. A single `cron.php` entry point handles: queued email delivery, notification digest generation, spam detection sweeps, session cleanup, cache warming, and anything else that should not happen inside a web request. One cron entry, every minute, running a dispatcher that fans out to registered cron tasks.

**`.htaccess` tuning** — Browser cache headers for static assets, GZIP compression where available, `X-Robots-Tag` on admin and internal paths, redirect loops prevented, and `mod_rewrite` rules that route cleanly without trailing slash confusion.

---

## PHASE 10 — Security Architecture

Security is not a feature you add at the end. Each of these must be designed in from the beginning.

**Authentication** — Passwords hashed with `password_hash(PASSWORD_BCRYPT)`, minimum cost factor 12. Sessions use a randomly generated token stored in a `HttpOnly; Secure; SameSite=Strict` cookie. Session ID rotated on privilege escalation. Session data stored in the database, not the filesystem, so it is auditable and revocable.

**CSRF** — Every state-changing form and AJAX request carries a token tied to the session. The middleware validates this before the controller runs.

**SQL Injection** — Strictly PDO with prepared statements everywhere. No string concatenation into queries. The AI must be instructed to refuse to write any query that builds SQL from user input without parameterization.

**XSS** — All user-generated content is escaped at the point of output, not at the point of input. The post body passes through a whitelist HTML sanitizer before storage — not just escaping, actual tag filtering. The sanitizer is configured in the admin panel (what HTML tags and attributes are permitted).

**Rate Limiting** — The rate limiter stores request counts in flat files per IP per endpoint, reset on a rolling window. No Redis required. Sensitive endpoints — login, register, password reset, post submission — have aggressive limits. Rate limit state is logged and the admin can see it.

**File Upload Security** — Uploaded files are validated by MIME type detection (not file extension), stored outside the web root, served only through the proxy, renamed to a random hash on disk, and virus-scanned if the admin has configured an optional scanning service via the credentials vault.

**Admin Path Obscurity** — As mentioned, the admin panel has a non-guessable path configured at install time. Failed admin login attempts are logged with IP and timestamp. After a configurable number of failures, that IP is temporarily blocked.

---

## PHASE 11 — Internationalisation and Accessibility

**i18n** — Every string in the application that a user sees goes through a translation function. There is a `lang/en/` directory in each module with a PHP file returning an associative array of translation keys. The language loader assembles these at boot. The admin can install additional language packs by dropping them into `lang/`. Language detection uses the user's saved preference or the browser `Accept-Language` header as fallback.

**Accessibility** — Semantic HTML throughout. ARIA attributes on dynamic components. Keyboard navigation for the editor, modals, and dropdowns. Focus management on page transitions. Color contrast ratios that meet WCAG AA at minimum. This is not optional — it is part of the definition of a quality build.

---

## PHASE 12 — Open Source, Upgrade Path, and Extension Points

The forum ships with a `CHANGELOG.md`, a `CONTRIBUTING.md`, and a `LICENSE` file. These are not afterthoughts.

**Database migrations are versioned** and the admin panel shows the current schema version. Running `php cli.php migrate` brings the database up to date. Migrations are forward-only in production. The AI should build a simple CLI runner for this.

**The hook system is documented** — a `HOOKS.md` file lists every event fired by the core and each module, what data it passes, and what the expected return behavior is for filters versus actions. Any developer wanting to extend the forum reads this file and writes a listener. They never touch core files.

**The module system is the plugin system.** There is no separate concept of a plugin. Third-party developers drop a module folder into `modules/`, the admin enables it, and it works. The module can add new routes, new admin pages, new hooks, new database tables, and override existing templates.

**The credentials vault is the integration layer.** Any future service the forum needs to talk to — payment processors for subscriptions, push notification services, new OAuth providers, AI features — plugs in through the same pattern: a service interface, a default fallback, and an admin-configured activation via the vault.

---

## HOW TO FEED THIS TO AN AI — SESSION STRUCTURE

Do not paste this entire document at once. Structure your build sessions like this:

**Session 1:** Ground rules (Phase 0) + Directory architecture (Phase 1). Output: a file tree you agree on.

**Session 2:** Kernel — router, hooks, container, middleware (Phase 2). Output: working, testable kernel code.

**Session 3:** Database schema design (Phase 3). Output: complete migration files.

**Session 4:** Install wizard and configuration system (Phase 4). Output: working installer.

**Session 5:** Module scaffold — the module loader and the first two modules: Auth and Users (Phase 5).

**Session 6:** Forum module — categories, threads, posts (Phase 5 continued).

**Session 7:** Service architecture — mail, storage, search, OAuth as dormant services (Phase 6).

**Session 8:** Admin CMS backend (Phase 7). This is a large session — break it into sub-sessions by admin section.

**Session 9:** Frontend and theme system (Phase 8).

**Session 10:** Performance review (Phase 9) — audit the code already written for query efficiency and caching.

**Session 11:** Security audit (Phase 10) — walk through every user-input touchpoint.

**Session 12:** i18n pass, accessibility audit, open source documentation (Phases 11 and 12).

At the start of every session, paste this reminder:

> *"We are building a shared-hosting PHP forum. No Docker, no Redis, no Elasticsearch, no WebSockets, no Node. Constraints are: PHP + MySQL + filesystem + mod_rewrite + cron. Every external service integration is dormant by default and activated only via the admin credentials vault. Do not break this architecture. Continue from where we left off."*

---

## CLOSING NOTE FROM THE DEVELOPER

The instinct when building something ambitious is to reach for the biggest tools available. Resist that instinct. Shared hosting is not a limitation to apologize for — it is a constraint that forces clarity. A PHP application that runs well on a $5 shared host will run magnificently on anything better. The reverse is not guaranteed.

Build the seams first. The router, the hooks, the module loader, the service container — these are the seams. Content fills in between them. If the seams are clean, the content is easy. If the seams are a mess, every new feature costs twice what it should.

This forum should be something a single developer can understand entirely, contribute to cleanly, and deploy confidently. That is what makes it worth open sourcing. That is what makes it last.

---

*End of prompt engineering guide. Begin building.*
