# Changelog

All notable changes to **Plugins Onboard** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.8] - 2025-01-12

### Added
- **SIMPLICITY FIRST** principle: Keep end-level code simple and concise
- `OnboardBooleanHelpers` class with truly positive boolean functions
- `CODING-GUIDELINES.md` - Complete documentation with simplicity & function size limits
- Positive boolean functions using positive words only:
  - `is_func_exists()` / `is_func_missing()`
  - `is_class_exists()` / `is_class_missing()`
  - `is_extension_loaded()` / `is_extension_missing()`
  - `is_dir_exists()` / `is_dir_missing()`
  - `is_dir_writable()` / `is_dir_readonly()` ✨ (not "not_writable")
  - `is_file_exists()` / `is_file_missing()`
  - `is_empty()` / `has_content()` ✨ (not "not_empty")
  - `is_null()` / `is_set()` ✨ (not "not_null")
  - `is_db_connected()` / `is_db_disconnected()`

### Changed
- **SIMPLICITY**: Removed unnecessary conditionals from initialization code
- **SIMPLICITY**: Push validation into classes, not into calling code
- **SIMPLICITY**: Let classes handle their own validation in constructors
- **CODE STYLE**: All conditionals use positive words only (no "not", "un-", "non-")
- **NO NEGATIONS**: Replaced `!function_exists()` with `is_func_missing()`
- **NO NEGATIONS**: Replaced `!class_exists()` with `is_class_missing()`
- **NO NEGATIONS**: Replaced `!is_dir()` with `is_dir_missing()`
- **NO NEGATIONS**: Replaced `!is_writable()` with `is_dir_readonly()`
- **NO NEGATIONS**: Replaced `!empty()` with `is_empty()` or `has_content()`
- **NO NEGATIONS**: Replaced `!== null` with `is_null()` or `is_set()`
- **FUNCTION SIZE**: Added 15-line maximum guideline for all functions
- **CLEANER CODE**: Reduced initialization code from ~90 lines to ~25 lines

### Documentation
- Added "Simplicity First" principle to guidelines
- Added "Avoid unnecessary conditionals" section
- Added "Push validation into classes" examples
- Added function size limit (15 lines max) to guidelines
- Added positive word pairs to avoid negative words in function names
- Updated PRD.md with complete coding standards
- Updated instructions.md with quick reference

## [1.0.7] - 2025-01-12

### Added
- `OnboardInitHelpers` class with reusable initialization functions
- `ensure_directories_exist()` - Reusable function to check/create directories (only runs once)
- `ensure_database_ready()` - Reusable function to check/initialize database (only runs once)

### Changed
- **CRITICAL**: Proper initialization order enforced
  - Directories are ALWAYS created FIRST before any file/database operations
  - Database is ALWAYS initialized AFTER directories are ready
  - Both activation and init() now use the same reusable helper functions
- Database class now assumes directories exist (doesn't try to create them)
- Removed duplicate code for directory creation and protection
- All initialization steps now clearly numbered and logged
- Better error messages showing exact step where initialization failed

### Fixed
- Initialization order issues that prevented plugin activation
- Directory permission checks now happen before database operations

## [1.0.6] - 2025-01-12

### Added
- Comprehensive logging system (`OnboardLogger` class)
- Debug logging with execution trace (controlled by `ONBOARD_DEBUG_LOGGING` constant)
- Error logging with stack traces (controlled by `ONBOARD_ERROR_LOGGING` constant)
- Log files: `debug.log` and `error.log` in `wp-content/uploads/plugins-onboard/logs/`
- Detailed trace logging throughout initialization process
- Database connection step-by-step logging
- Component initialization logging
- Memory usage tracking in logs
- DEBUGGING.md guide for troubleshooting

### Changed
- All cron task scheduling removed (no automated cleanup tasks)
- Enhanced error handling with try-catch blocks throughout
- Improved error messages with context

### Fixed
- Better error reporting during plugin activation
- More graceful failure handling

## [1.0.5] - 2025-01-11

### Fixed
- Critical syntax error in `security-utils.php` that prevented plugin activation
- All utility functions now properly wrapped with `if (!function_exists())` checks

## [1.0.4] - 2025-01-11

### Changed
- Renamed all classes to PSR-4 PascalCase style:
  - `Onboard_Paths` → `OnboardPaths`
  - `Onboard_Config` → `OnboardConfig`
  - `Onboard_Database` → `OnboardDatabase`
  - `Onboard_OAuth` → `OnboardOAuth`
  - `Onboard_Mutation_Token` → `OnboardMutationToken`
  - `Onboard_Token_Encryption` → `OnboardTokenEncryption`
  - `Onboard_Rate_Limiter` → `OnboardRateLimiter`
  - `Onboard_Audit_Logger` → `OnboardAuditLogger`
  - `Onboard_IP_Whitelist` → `OnboardIPWhitelist`
  - `Onboard_Snapshot` → `OnboardSnapshot`
  - `Onboard_Backup_Manager` → `OnboardBackupManager`
  - `Onboard_Plugin_Manager` → `OnboardPluginManager`
  - `Onboard_Upload_Validator` → `OnboardUploadValidator`
  - `Onboard_Debug_Maintenance` → `OnboardDebugMaintenance`
  - `Onboard_Cleanup` → `OnboardCleanup`
  - `Onboard_Admin_UI` → `OnboardAdminUI`
  - `Onboard_Test_Runner` → `OnboardTestRunner`
  - `Onboard_API` → `OnboardAPI`
  - `Onboard_Permissions` → `OnboardPermissions`
  - `Plugins_Onboard` → `PluginsOnboard`

## [1.0.3] - 2025-01-11

### Changed
- Replaced `get_required_directories()` method with static `REQUIRED_DIRECTORIES` constant
- Avoids unnecessary array creation on each call

## [1.0.2] - 2025-01-11

### Changed
- Renamed `Onboard_Paths` constants to use meaningful, descriptive names:
  - `DIR_PLUGIN_DATA` - SQLite databases and settings storage
  - `DIR_PLUGIN_SNAPSHOTS` - Plugin backup snapshots
  - `DIR_TEMP_UPLOADS` - Temporary upload files
  - `DIR_SECURITY_LOGS` - Security and audit logs
  - `FILE_MAIN_DATABASE` - Main plugin database file
  - `FILE_AUDIT_DATABASE` - Audit log database file
- Renamed methods to follow proper conventions:
  - `ensure_dir()` → `ensure_directory_exists()`
  - `is_writable()` → `is_directory_writable()`
  - `get_all_dirs()` → `get_required_directories()`
- Added `create_all_directories()` convenience method
- Added `file_exists()` and `get_file_size()` utility methods

## [1.0.1] - 2025-01-11

### Changed
- Refactored path management to use centralized `Onboard_Paths` class with constants
- Replaced string-based function checks with proper class constants (e.g., `Onboard_Paths::SNAPSHOTS`)
- Improved code maintainability by eliminating scattered string function names

### Fixed
- Plugin header Author field (removed slash character that could cause issues)
- Added Plugin URI for "View Details" link in WordPress admin
- Path resolution now uses lazy loading to avoid early WordPress function calls

## [1.0.0] - 2025-01-10

### Added

#### Core Infrastructure
- **OAuth 2.0 Authentication System**
  - JWT-based access tokens with configurable TTL
  - Refresh token support for seamless re-authentication
  - Authorization code flow implementation
  - Client credentials management (ID & Secret)
  - Automatic token cleanup for expired tokens

- **Ephemeral Mutation Token System**
  - One-time use tokens for destructive operations
  - IP-scoped validation (token bound to requester IP)
  - Action-specific tokens (enable, disable, delete, upload, restore)
  - Configurable TTL (default: 20 minutes)
  - Automatic invalidation after use

- **SQLite Database Storage**
  - Separate databases for plugin data and audit logs
  - WAL journal mode for better concurrency
  - Automatic table creation on activation
  - Database stored in WordPress uploads directory for reliable write permissions

- **Configuration Management**
  - Three-tier configuration hierarchy: Constants → Database → ENV Variables
  - Runtime configuration via ENV variables (highest priority)
  - Database-persisted settings (admin configurable)
  - Sensible defaults via PHP constants
  - `onboard_config()` helper function for easy access

#### Plugin Management API
- **REST API Endpoints**
  - `GET /plugins/list` - List all installed plugins with status
  - `POST /mutations/{token}/plugins/{slug}/enable` - Enable a plugin
  - `POST /mutations/{token}/plugins/{slug}/disable` - Disable a plugin
  - `POST /mutations/{token}/plugins/{slug}/delete` - Delete a plugin
  - `POST /mutations/{token}/plugins/upload` - Upload and install plugin ZIP
  - `POST /mutations/{token}/plugins/{slug}/restore` - Restore from snapshot

- **Automatic Pre-Action Snapshots**
  - Configurable backup triggers (upload, enable, disable, delete)
  - ZIP-based snapshots with SHA-256 checksums
  - Version and timestamp tracking
  - Retention policy enforcement

#### Security Features
- **IP Whitelist & Approval Workflow**
  - Per-application IP whitelisting
  - Admin approval required for new IPs (configurable)
  - Email notifications for approval requests
  - Approval/rejection via admin panel or email link

- **Rate Limiting**
  - Configurable limits for auth requests (default: 5/hour)
  - Configurable limits for mutation requests (default: 10/hour)
  - Per-IP and per-application tracking
  - `X-RateLimit-*` headers in responses

- **Token Encryption**
  - AES-256-CBC encryption for stored tokens
  - WordPress AUTH_KEY-based encryption keys
  - Bcrypt hashing for client secrets

- **Upload Validation**
  - ZIP magic bytes verification
  - MIME type validation
  - Plugin header detection
  - Malicious code pattern scanning
  - Path traversal prevention

#### WordPress Admin Panel
- **Dashboard**
  - Overview cards (plugins, snapshots, actions today)
  - System status indicators
  - Recent activity feed
  - Quick action links

- **Plugins Management**
  - List all installed plugins with snapshot counts
  - View plugins uploaded via API
  - Direct links to snapshot management

- **Backups & Restore**
  - Browse all snapshots by plugin
  - One-click restore functionality
  - Bulk download options (all plugins, active only, snapshots)
  - Snapshot deletion with confirmation

- **Applications Management**
  - Create new OAuth applications
  - View client credentials (secret shown once)
  - Pending IP approval queue
  - Application deletion

- **Settings**
  - General settings (admin email)
  - Backup configuration (triggers, retention)
  - Security settings (HTTPS, IP whitelist)
  - Token TTL display (read-only constants)

- **Audit Logs**
  - Searchable/filterable log viewer
  - Action, status, and date filters
  - Pagination support
  - Log clearing functionality

- **Test Runner**
  - Built-in PHPUnit test execution
  - Unit and integration test suites
  - Visual pass/fail indicators
  - Security checklist display

- **Help & Documentation**
  - Quick start guide
  - API endpoint reference
  - Authentication flow examples
  - Error code documentation

#### Additional Features
- **Debug & Maintenance Mode API**
  - Toggle WP_DEBUG via API
  - Toggle maintenance mode via API
  - Debug log retrieval and clearing

- **Database Management**
  - Database info display (size, location)
  - Database export/download
  - Temp file management
  - Manual cleanup trigger

- **Automated Cleanup**
  - Daily cron for expired tokens
  - Old temp file removal
  - Snapshot retention enforcement
  - Audit log rotation

### Security
- All endpoints require authentication (Bearer token or mutation token)
- CSRF protection on all admin forms
- Nonce verification for admin actions
- Input sanitization and output escaping
- Prepared statements for all database queries

### Technical
- Requires WordPress 5.9+
- Requires PHP 7.4+
- Requires PDO SQLite extension
- Requires ZipArchive extension
- PSR-4 compatible class structure
- Comprehensive error logging
- Fail-safe initialization (plugin won't crash on errors)

---

## [Unreleased]

### Planned
- Webhook notifications for plugin events
- Multi-site support
- Plugin dependency tracking
- Scheduled plugin updates
- API rate limit customization per application
- Two-factor authentication for admin actions
- Plugin health monitoring
- Automated rollback on activation failure

---

[1.0.8]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.8
[1.0.7]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.7
[1.0.6]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.6
[1.0.5]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.5
[1.0.4]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.4
[1.0.3]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.3
[1.0.2]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.2
[1.0.1]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.1
[1.0.0]: https://github.com/riseup-asia/plugins-onboard/releases/tag/v1.0.0
[Unreleased]: https://github.com/riseup-asia/plugins-onboard/compare/v1.0.8...HEAD
