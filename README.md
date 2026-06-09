# Forux Forum Management System

Forux is a lightweight, self-hosted, open-source, extensible forum platform built with modern PHP, specifically designed to run efficiently on shared hosting environments (like DreamHost, GoDaddy, Hostinger, etc.).

## 🚀 Key Philosophy

- **Zero-Dependency Core**: A decoupled framework core (router, dependency injection container, event hooks) handles request life cycles without requiring external frameworks.
- **Shared Hosting Friendly**: No root access, Docker, Redis, or WebSockets required. Everything runs on standard Linux servers with PHP and MySQL.
- **Extensible via Modules & Hooks**: Features are completely modular. Add or remove features by dropping a folder in `modules/`. Custom hooks allow plugins to interact with core events without changing system files.
- **Secure by Default**: Configuration and uploads live above the public web root. Sensitive settings (API keys, OAuth secrets, SMTP) are stored encrypted in the database credentials vault.

---

## 🛠️ Requirements

- **PHP**: 8.2 or higher
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **PHP Extensions**:
  - `PDO` & `pdo_mysql` (Database access)
  - `openssl` (Secure encryption)
  - `curl` (Remote API requests)
  - `gd` or `imagick` (Image processing & avatar resizing)
  - `mbstring` (Multi-byte string handling)
  - `json` & `xml`
- **Web Server**: Apache with `mod_rewrite` enabled (via `.htaccess`)

---

## 📂 Directory Structure

```
forux/
├── app/                      # Shared Controllers, Middleware, and Services
├── config/                   # Configuration templates (e.g., config.example.php)
├── core/                     # Decoupled framework kernel (routing, hooks, DI)
├── install/                  # Installation wizard
├── modules/                  # Plug-and-play modules (Forum, Auth, Users, etc.)
├── public/                   # Public web root (only directory exposed to the internet)
├── storage/                  # Cache, session, logs, and upload files (writable)
└── themes/                   # Theme templates (HTML, CSS, JS)
```

---

## ⚙️ Installation

1. Clone or upload the repository to your host.
2. Ensure the `storage/` directory is writable by the web server.
3. Configure your domain to point to the `public/` directory (or use a rewrite root rule).
4. Navigate to your site's URL; you will be redirected to the `/install/` wizard to configure the database and administrative account.

---

## 📝 License

Distributed under the MIT License. See `LICENSE` for more information.
