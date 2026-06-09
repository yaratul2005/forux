# Changelog

All notable changes to the Forux Forum project will be documented in this file.

## [1.0.0] - 2026-06-09

### Added
- **Core Kernel**: Custom Autoloader, dependency injection Container, Hook/Event dispatcher, Request/Response wrappers, regex Router, and middleware pipelines.
- **Database Schema**: Full database schema migrations covering users, sessions, roles, categories, threads, posts, revisions, reactions, reports, private messaging, notifications, and dynamic settings.
- **Web Installer**: Dynamic first-run web installation wizard with system checks, database seeding, config file writer, and dynamic settings caching.
- **Module Subsystem**: Modular architecture for drop-in modules, bootstrapping Auth, Users, Forum, Notifications, Private Messages, Pages, and Admin Panel.
- **Credentials Vault**: Encrypted Vault (`EncryptionService` with AES-256-CBC) to store API keys and settings in database columns, with dormant interfaces.
- **Service Integration Drivers**: Swappable interfaces/drivers for:
  - Mail: SMTP Socket, SendGrid API, Local Mail spooling fallback.
  - Storage: AWS S3/Cloudflare R2 storage, Local Storage with secure serving proxy.
  - Search: Db FULLTEXT, Meilisearch.
  - OAuth: Google, GitHub, Discord.
  - Moderation: Regex blocker, AI Moderation (Gemini/OpenAI).
- **Admin CMS Backend**: Custom hidden-path admin console managing dashboard stats, users, categories, thread moderation, vault integrations, SQL database backup exporter, and error/security logs.
- **Theme Engine**: Theme view renderer with a responsive HSL-colored glassmorphic dark-slate default layout.
- **JavaScript Toolbar**: Custom rich WYSIWYG formatting buttons, autocomplete user @mentions, paste-to-upload screenshot attachments, and notifications polling.
- **Performance & Caching**: Flat-file cache helpers for sidebar stats and categories trees, automatic GD image downscaling, cron dispatcher, and Apache `.htaccess` compression/expiry rules.
- **Security Auditing**: Strict database-backed CSRF protection middleware, flat-file IP rate limiting, administrative failed-login IP lockout blocks, raw inputs retention, and database prepared statements query auditing.
- **i18n & Accessibility**: Global translation helper `__()` supporting English/Spanish, user preferred language profiles, and WCAG AA accessibility audited templates with focus outlines and ARIA tags.
