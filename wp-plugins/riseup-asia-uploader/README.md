# Riseup Asia Uploader

A WordPress plugin providing a secure REST API for remote plugin management, database snapshots, delta file synchronization, blog post publishing, and comprehensive audit logging.

## Plugin Information

| Property | Value |
|----------|-------|
| **Plugin Name** | Riseup Asia Uploader |
| **Plugin URI** | https://rasia.pro/alim-r-profile-v1 |
| **Author** | MD ALIM UL KARIM |
| **Author URI** | https://rasia.pro/alim-r-profile-v1 |
| **License** | GPL v2 or later |
| **Requires PHP** | 8.2+ |
| **Requires WordPress** | 5.6+ |

> **Note:** Version is managed via `PluginConfigType::Current->value` in `includes/Enums/PluginConfigType.php` and the plugin header in `riseup-asia-uploader.php`. Both must be bumped on every change.

---

## Architecture

The plugin follows a fully **PSR-4 namespaced** architecture under the `RiseupAsia\` namespace.

| Aspect | Detail |
|--------|--------|
| **Namespace** | `RiseupAsia\` → `includes/` |
| **Autoloader** | `includes/Autoloader.php` (self-contained, zero dependencies) |
| **Entry point** | `riseup-asia-uploader.php` — registers autoloader + activation hook + `Plugin` class only |
| **Main class** | `RiseupAsia\Core\Plugin` (singleton) |
| **Global classes** | 0 |
| **Legacy aliases** | 0 (`class_alias()` shims fully removed) |
| **Enums** | 39 backed enums in `RiseupAsia\Enums\` (PascalCase, `Type` suffix) |
| **Total files** | ~252 namespaced (38 classes, 175 traits, 39 enums) |

### Key Namespaces

| Namespace | Purpose |
|-----------|---------|
| `RiseupAsia\Core` | Plugin shell (singleton) |
| `RiseupAsia\Activation` | Plugin activation hook handler |
| `RiseupAsia\Admin` | Admin UI and AJAX handlers |
| `RiseupAsia\Agent` | Agent connection management |
| `RiseupAsia\Database` | SQLite ORM, file cache, root DB |
| `RiseupAsia\Enums` | All 39 backed enums |
| `RiseupAsia\ErrorHandling` | ErrorResponse, FatalErrorHandler, FrameBuilder |
| `RiseupAsia\Helpers` | BooleanHelpers, PathHelper, InitHelpers, EnvelopeBuilder |
| `RiseupAsia\Logging` | FileLogger, Logger |
| `RiseupAsia\Post` | Blog post management |
| `RiseupAsia\Snapshot` | Database snapshot system (18 classes + 66 traits) |
| `RiseupAsia\Update` | Auto-update and version detection |
| `RiseupAsia\Upload` | Upload handling and `.uploadignore` |

---

## Features

- **Plugin management** — Upload (ZIP multipart/base64), enable, disable, delete plugins remotely
- **Delta sync** — File-level synchronization with `.uploadignore` support
- **Database snapshots** — Full and incremental backups with async worker pool, restore, export as ZIP
- **Blog post management** — Create and update posts, categories, media uploads
- **Audit logging** — SQLite-based transaction log for all administrative actions
- **Error handling** — `Throwable` catch with structured stack traces, fatal error handler
- **Self-export** — Download the plugin itself as a ZIP for redeployment

---

## REST API Endpoints

All endpoints require Basic Authentication using WordPress Application Passwords.

**Base Namespace:** `riseup-asia-uploader/v1`

### Plugin Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/status` | Check plugin availability (public) |
| POST | `/upload` | Upload and install plugin |
| GET | `/plugins` | List all installed plugins |
| GET | `/plugins/{slug}` | Get single plugin info |
| POST | `/plugins/{slug}/enable` | Activate a plugin |
| POST | `/plugins/{slug}/disable` | Deactivate a plugin |
| DELETE | `/plugins/{slug}/delete` | Delete a plugin |
| GET/POST/DELETE | `/plugins/{slug}/files` | File operations |
| POST | `/plugins/{slug}/sync` | Delta synchronization |
| GET | `/export-self` | Export this plugin as ZIP |

### Blog Post Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/posts` | List posts |
| POST | `/posts` | Create new post |
| GET | `/posts/{id}` | Get single post |
| PUT | `/posts/{id}` | Update post |

### Categories & Media

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/categories` | List categories |
| POST | `/categories` | Create category |
| POST | `/media` | Upload to Media Library |

### Transaction Logs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/logs` | Query transaction logs |
| GET | `/logs/stats` | Get log statistics |
| GET | `/logs/{id}` | Get single log entry |

### Snapshots

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/snapshots/backup` | Create database snapshot |
| GET | `/snapshots` | List snapshots |
| POST | `/snapshots/restore` | Restore from snapshot |
| GET | `/snapshots/{id}/export` | Export snapshot as ZIP |
| DELETE | `/snapshots/{id}` | Delete snapshot |

---

## Storage Layout

All persistent data lives under `wp-content/uploads/riseup-asia-uploader/`:

```
riseup-asia-uploader/
├── riseup-asia-uploader.db   (SQLite database)
├── logs/
│   ├── log.txt               (general activity)
│   └── error.txt             (error logs)
├── snapshots/                 (backup snapshots)
│   └── incremental/           (incremental backups)
├── exports/                   (cached ZIP exports)
└── temp/                      (temporary files)
```

Each directory is secured with `.htaccess` (`Deny from all`) and `index.php` (silence) files.

---

## Required Capabilities

| Capability | Endpoints |
|------------|-----------|
| `activate_plugins` | Plugin management |
| `publish_posts` | Post management |
| `upload_files` | Media uploads |
| `manage_options` | Logs, snapshots, settings |

---

## Documentation

Full development standards, coding guidelines, and architectural specifications are maintained in the project's `spec/` directory:

| Document | Path |
|----------|------|
| **Coding Guidelines** | [`spec/09-wordpress-plugin-development/11-coding-guidelines.md`](../../spec/09-wordpress-plugin-development/11-coding-guidelines.md) |
| **Phase 7 Completion Report** | [`spec/09-wordpress-plugin-development/12-phase-7-completion-report.md`](../../spec/09-wordpress-plugin-development/12-phase-7-completion-report.md) |
| **Plugin Development Spec** | [`spec/09-wordpress-plugin-development/00-overview.md`](../../spec/09-wordpress-plugin-development/00-overview.md) |
| **PHP Standards** | [`spec/06-php-standards/readme.md`](../../spec/06-php-standards/readme.md) |
| **Error Handling** | [`spec/07-error-manage/01-error-handling/readme.md`](../../spec/07-error-manage/01-error-handling/readme.md) |

---

## Author

**MD ALIM UL KARIM**

- Profile: https://rasia.pro/alim-r-profile-v1
- Company: Riseup Asia

## License

GPL v2 or later
