# Riseup Asia Uploader

A WordPress plugin providing a secure REST API for remote plugin management, database snapshots, delta file synchronization, user management, blog post publishing, and comprehensive audit logging.

**Current Version:** `2.28.3` · **Requires PHP:** 8.2+ · **Requires WordPress:** 5.6+

> 📋 See [CHANGELOG.md](./CHANGELOG.md) for the full release history.

---

## Plugin Information

| Property | Value |
|----------|-------|
| **Plugin Name** | Riseup Asia Uploader |
| **Plugin URI** | https://rasia.pro/alim-r-profile-v1 |
| **Author** | MD ALIM UL KARIM |
| **License** | GPL v2 or later |
| **Requires PHP** | 8.2+ |
| **Requires WordPress** | 5.6+ |
| **REST API Namespace** | `riseup-asia-api/v1` |
| **Authentication** | WordPress Application Passwords (HTTP Basic Auth) |

---

## Architecture

The plugin follows a fully **PSR-4 namespaced** architecture under the `RiseupAsia\` namespace.

| Aspect | Detail |
|--------|--------|
| **Namespace** | `RiseupAsia\` → `includes/` |
| **Autoloader** | `includes/Autoloader.php` (self-contained, zero dependencies) |
| **Entry point** | `riseup-asia-uploader.php` — registers autoloader + activation hook + `Plugin` class only |
| **Main class** | `RiseupAsia\Core\Plugin` (singleton) |
| **Enums** | 39 backed enums in `RiseupAsia\Enums\` (PascalCase, `Type` suffix) |
| **Total files** | ~252 namespaced (38 classes, 175 traits, 39 enums) |
| **Global classes** | 0 |

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
- **User management** — Full CRUD with Yoast SEO fields, app passwords, bulk CSV/SQLite import/export
- **Blog post management** — Create and update posts, categories, media uploads
- **Cloud storage** — GitHub, Google Drive, Dropbox integration for remote backups
- **Audit logging** — SQLite-based transaction log for all administrative actions
- **Error handling** — `Throwable` catch with structured stack traces, fatal error handler
- **Machine authorization** — Approve specific machine names for REST API access
- **Self-export** — Download the plugin itself as a ZIP for redeployment

---

## REST API Endpoints

All endpoints require HTTP Basic Auth using WordPress Application Passwords.

**Base:** `/wp-json/riseup-asia-api/v1`

### System

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/status` | Plugin health, version, registered routes, feature flags |
| GET | `/openapi` | OpenAPI 3.0 specification |
| POST | `/opcache-reset` | Reset PHP OPcache |
| GET | `/export-self` | Export this plugin as ZIP |

### Plugin Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/plugins` | List all installed plugins |
| POST | `/plugins/info` | Get plugin info by slug |
| POST | `/upload` | Upload plugin (ZIP or base64) |
| POST | `/upload-active` | Upload and force-activate |
| POST | `/plugins/enable` | Activate a plugin |
| POST | `/plugins/disable` | Deactivate a plugin |
| POST | `/plugins/delete` | Delete a plugin |
| POST | `/plugins/exists` | Check if plugin exists |
| POST | `/plugins/files` | List plugin files with MD5 hashes |
| POST | `/plugins/file` | Get raw file content |
| POST | `/plugins/export` | Export plugin as base64 ZIP |
| POST | `/plugins/backup` | Create plugin backup |
| POST | `/plugins/backup-restore` | Restore from backup |
| GET | `/plugins/backup-list` | List available backups |
| POST | `/plugins/backup-delete` | Delete a backup |

### Delta Sync

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/plugins/sync-manifest` | Get cached file manifest for diff |
| POST | `/plugins/sync` | Push file changes (replacements + deletions) |

### Content

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/posts` | List or create blog posts |
| GET/POST | `/categories` | List or create categories |
| POST | `/media` | Upload to Media Library |

### User Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/users` | List or create users |
| GET/PUT/DELETE | `/users/{id}` | Single user CRUD |
| POST/DELETE | `/users/app-password` | Manage app passwords |
| GET | `/users/export` | Export users as CSV |
| POST | `/users/import` | Import users from CSV |
| GET | `/users/export-sqlite` | Export users as SQLite ZIP |
| POST | `/users/import-sqlite` | Import users from SQLite ZIP |

### Diagnostics

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/logs` | Query transaction logs |
| GET | `/logs/stats` | Log statistics |
| GET | `/logs/status` | Log file status |
| GET | `/logs/rotation-status` | Log rotation status |
| GET | `/logs/retrieve` | Retrieve raw log content |
| DELETE | `/logs/clear` | Clear logs (two-step) |
| POST | `/logs/clear/confirm` | Confirm log clearing |
| POST | `/logs/email` | Email log report |
| GET | `/error-logs` | PHP error logs |
| GET | `/error-sessions` | Error sessions |

### Machine Authorization

| Method | Endpoint | Description |
|--------|----------|-------------|
| PUT | `/machines/approve` | Approve a machine name |

### Agents

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/agents` | List agent sites |
| POST | `/agents/add` | Register agent |
| POST | `/agents/remove` | Remove agent |
| POST | `/agents/test` | Test agent connectivity |
| POST | `/agents/sync` | Sync plugins to agent |
| POST | `/agents/plugins` | List plugins on agent |
| POST | `/agents/action` | Execute action on agent |
| GET | `/agents/history` | Agent action history |

### Snapshots

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/snapshots/list` | List all snapshots |
| POST | `/snapshots/schedule` | Create/schedule snapshot |
| POST | `/snapshots/info` | Snapshot details |
| POST | `/snapshots/delete` | Delete snapshot |
| POST | `/snapshots/restore` | Restore from snapshot |
| POST | `/snapshots/export` | Export as ZIP |
| POST | `/snapshots/import` | Import from ZIP |
| GET/POST/PUT | `/snapshots/settings` | Snapshot settings |
| GET | `/snapshots/providers` | Available providers |
| GET | `/snapshots/tables` | Available DB tables |
| POST | `/snapshots/dependencies` | Table dependency analysis |
| POST | `/snapshots/full-backup` | Full backup |
| POST | `/snapshots/incremental` | Incremental backup |
| POST | `/snapshots/cleanup` | Cleanup old snapshots |
| POST | `/snapshots/progress` | Poll job progress |
| POST | `/snapshots/download` | Prepare download |
| GET | `/snapshots/download-file` | Download file (nonce) |

### Cloud Storage

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/cloud-storage/accounts` | List or create storage accounts |
| GET/PUT/DELETE | `/cloud-storage/accounts/{id}` | Single account CRUD |
| POST | `/cloud-storage/accounts/test` | Test connection |
| GET | `/cloud-storage/settings` | Storage settings |
| PUT | `/cloud-storage/settings/{provider}` | Update provider settings |
| POST | `/cloud-storage/upload` | Upload to cloud |
| GET | `/cloud-storage/files` | List cloud files |
| DELETE | `/cloud-storage/delete` | Delete cloud file |
| POST | `/cloud-storage/oauth/initiate` | Start OAuth flow |
| GET | `/cloud-storage/oauth/callback` | OAuth callback |
| GET | `/cloud-storage/repos` | List GitHub repos |
| GET | `/cloud-storage/branches` | List branches |
| GET | `/cloud-storage/backup-history` | Backup history |
| GET/DELETE | `/cloud-storage/backup-history/{id}` | Single backup entry |
| POST | `/cloud-storage/restore` | Restore from cloud |

---

## Storage Layout

```
wp-content/uploads/riseup-asia-uploader/
├── riseup-asia-uploader.db   (SQLite database)
├── logs/
│   ├── info.txt              (general activity)
│   ├── error.txt             (error logs)
│   └── stacktrace.txt        (stack traces)
├── snapshots/                (backup snapshots)
│   └── incremental/          (incremental backups)
├── exports/                  (cached ZIP exports)
└── temp/                     (temporary files)
```

Each directory is secured with `.htaccess` (`Deny from all`) and `index.php` (silence) files.

---

## Coding Guidelines

| Rule | Enforcement |
|------|-------------|
| **Max file size** | 500 lines (trait decomposition required) |
| **Max function size** | 20 lines |
| **PHP version** | 8.2+ with strict types |
| **Naming** | PascalCase enums, camelCase methods, `Type` suffix for enums |
| **Imports** | PSR-4, grouped by namespace origin |
| **Error handling** | `Throwable`-first, no silent catches, structured stack traces |
| **Static analysis** | PHPStan level 6 — blocks deployment on failure |
| **Booleans** | Positive naming only (`$isValid`, not `$isNotValid`) |
| **Constants** | Backed enums only — no `define()` or `const` |

> Full standards: [`spec/07-php-standards/`](../../spec/07-php-standards/)

---

## Deployment

```powershell
# Upload this plugin to all sites
.\run.ps1 -uas

# Upload this plugin only
.\run.ps1 -u

# Check remote status
.\run.ps1 -pas

# Version bump
.\wp-plugins\scripts\bump-version.ps1 -Target plugin -Bump patch
```

> Full CLI reference: [`wp-plugins/scripts/README.md`](../scripts/README.md)

---

## Documentation

| Document | Path |
|----------|------|
| **Changelog** | [`CHANGELOG.md`](./CHANGELOG.md) |
| **CLI Reference** | [`wp-plugins/scripts/README.md`](../scripts/README.md) |
| **Coding Guidelines** | [`spec/07-php-standards/`](../../spec/07-php-standards/) |
| **WordPress Plugin Spec** | [`spec/09-wordpress/`](../../spec/09-wordpress/) |
| **PowerShell Integration** | [`spec/13-powershell-integration/`](../../spec/13-powershell-integration/) |

---

## Author

**MD ALIM UL KARIM**

- Profile: [rasia.pro](https://rasia.pro/alim-r-profile-v1)
- Company: Riseup Asia

## License

GPL v2 or later
