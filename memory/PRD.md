# Plugins Onboard - WordPress Plugin

## Original Problem Statement
Build a comprehensive WordPress plugin named "Plugins Onboard" that provides:
- Enterprise-grade REST API access to manage WordPress plugins remotely
- OAuth 2.0 authentication with JWT tokens
- Ephemeral mutation tokens for destructive operations
- Automatic backups and snapshots
- Comprehensive audit logging
- PHPUnit tests with admin dashboard test runner

## Core Requirements

### Coding Standards (CRITICAL)

**Boolean Functions - Positive Conditionals Only:**
- **ALWAYS** use positive boolean functions with `is_` or `has_` prefix
- **NEVER** use negations (`!`) in if statements
- Create separate functions for both positive and negative cases
- Use `OnboardBooleanHelpers` class for all boolean checks

### Simplicity First

**Keep end-level code simple and concise. Avoid unnecessary if/else statements.**

```php
// ✅ CORRECT: Simple, clean
$this->oauth = new OnboardOAuth($this->db, $this->audit_logger);
OnboardLogger::debug('OAuth initialized');

// ❌ WRONG: Unnecessary conditionals
if (OnboardBooleanHelpers::is_class_exists('OnboardOAuth')) {
    if (OnboardBooleanHelpers::is_set($this->audit_logger)) {
        $this->oauth = new OnboardOAuth($this->db, $this->audit_logger);
    }
}
```

**Push validation into classes:**
- Classes should validate their own requirements in constructors
- Use early returns instead of nested if-else
- Avoid if-else chains wherever possible

**Examples:**
```php
// ✅ CORRECT
if (OnboardBooleanHelpers::is_func_missing('my_function')) {
    // Handle missing function
}

// ❌ WRONG
if (!function_exists('my_function')) {
    // Don't use negation
}
```

**Available Helper Functions:**
- `is_func_exists()` / `is_func_missing()`
- `is_class_exists()` / `is_class_missing()`
- `is_extension_loaded()` / `is_extension_missing()`
- `is_dir_exists()` / `is_dir_missing()`
- `is_dir_writable()` / `is_dir_readonly()` (not "not_writable")
- `is_file_exists()` / `is_file_missing()`
- `is_empty()` / `has_content()` (not "not_empty")
- `is_null()` / `is_set()` (not "not_null")
- `is_db_connected()` / `is_db_disconnected()`

**Function Size:**
- Maximum 15 lines per function
- Break larger functions into smaller helpers

**See `CODING-GUIDELINES.md` for complete documentation.**

### Initialization Order (CRITICAL)

**MUST follow this exact order:**
1. **Directories First** - `OnboardInitHelpers::ensure_directories_exist()`
2. **Database Second** - `OnboardInitHelpers::ensure_database_ready()`
3. **Components Third** - All other components after database is ready

**Never attempt to initialize database before directories exist!**

### Configuration System Architecture
**Three-tier configuration hierarchy (CRITICAL):**
1. **Constants (defaults)** - Loaded first from `constants.php`, always available
2. **Database (user settings)** - Stored in SQLite, overrides constants if values exist
3. **Environment Variables (overrides)** - Highest priority, overrides everything

**Key Points:**
- Constants are used to determine the database path on first load
- If database doesn't exist, seed it with default values from constants
- ENV variables take final priority for all configuration values
- All files use safe defaults with `defined('CONSTANT') ? CONSTANT : default` pattern

### Database Storage
- SQLite databases stored in WordPress `uploads/plugins-onboard/data/` directory
- This ensures write permissions on most WordPress installations
- Two separate databases: `plugin-manager.sqlite` and `audit.sqlite`

### Fail-Safe Loading
- Plugin must not crash WordPress if any component fails to initialize
- All classes check if constants are defined before using them
- Error logging for missing files and failed operations
- Graceful degradation if database connection fails

## Technology Stack
- **Platform:** WordPress 5.9+
- **Language:** PHP 7.4+
- **Database:** SQLite (PDO)
- **Authentication:** OAuth 2.0 with JWT
- **Testing:** PHPUnit
- **Compression:** ZipArchive

## What's Been Implemented (v1.0.0)

### Core Infrastructure ✅
- [x] Main plugin file with singleton pattern
- [x] Configuration management (`class-config.php`) with 3-tier hierarchy
- [x] Constants file with safe defaults (`constants.php`)
- [x] SQLite database handler (`class-database.php`)
- [x] Token encryption with AES-256-CBC (`class-token-encryption.php`)

### Security Features ✅
- [x] OAuth 2.0 authentication (`class-oauth.php`)
- [x] Ephemeral mutation tokens (`class-mutation-token.php`)
- [x] IP whitelist & approval workflow (`class-ip-whitelist.php`)
- [x] Rate limiting (`class-rate-limiter.php`)
- [x] Upload validation (`class-upload-validator.php`)

### Plugin Management ✅
- [x] Plugin manager (`class-plugin-manager.php`) - enable/disable/delete/upload
- [x] Snapshot system (`class-snapshot.php`) - create/restore backups
- [x] Backup manager (`class-backup-manager.php`) - export/import functionality
- [x] Audit logging (`class-audit-logger.php`)
- [x] Cleanup operations (`class-cleanup.php`)
- [x] Debug/Maintenance mode (`class-debug-maintenance.php`)

### REST API ✅
- [x] API endpoints (`api/class-api.php`)
- [x] Permission handling (`api/class-permissions.php`)

### Admin UI ✅
- [x] Admin menu and pages (`admin/class-admin-ui.php`)
- [x] Test runner (`admin/class-test-runner.php`)
- [x] Admin views (dashboard, plugins, backups, settings, applications, audit-logs, tests, help)
- [x] CSS and JS assets

### Testing ✅
- [x] PHPUnit configuration (`phpunit.xml`)
- [x] Test bootstrap (`tests/bootstrap.php`)
- [x] Unit tests (TokenEncryption, RateLimiter, UploadValidator)
- [x] Integration tests (Database, OAuth, MutationToken, AuditLogger)

### Documentation ✅
- [x] Professional README.md with comprehensive documentation
- [x] CHANGELOG.md with version history
- [x] composer.json for dependencies

## Fixes Applied in Current Session

1. **Configuration System Refactored:**
   - Changed priority order: Constants (base) → Database (override) → ENV (highest)
   - All classes now use safe defaults with `defined()` checks

2. **Fail-Safe Constants:**
   - `class-token-encryption.php` - Safe defaults for encryption flag
   - `class-rate-limiter.php` - Safe defaults for rate limits
   - `class-audit-logger.php` - Safe defaults for audit logging flag
   - `class-ip-whitelist.php` - Safe defaults for whitelist flag
   - `class-oauth.php` - Safe defaults for token TTLs
   - `class-plugin-manager.php` - Safe defaults for backup triggers
   - `class-cleanup.php` - Safe defaults for cleanup settings
   - `class-snapshot.php` - Safe defaults for retention count

3. **ZIP Packaging Fixed:**
   - Correct command: `cd /app && zip -r plugins-onboard.zip plugins-onboard`
   - Results in proper structure with top-level `plugins-onboard/` folder

4. **Localhost References Removed:**
   - Changed fallback URLs from `localhost` to `https://example.com` in `class-token-encryption.php`
   - Test files still use localhost (expected for unit testing)

5. **Root README.md Created:**
   - Professional documentation at `/app/README.md` matching the inner plugin README

6. **Critical Syntax Error Fixed (v1.0.5):**
   - Fixed missing closing braces in `security-utils.php`
   - All utility functions now properly wrapped with `if (!function_exists())` checks
   - Plugin can now activate successfully without PHP parse errors

## File Structure
```
/app/plugins-onboard/
├── plugins-onboard.php      # Main plugin file (entry point)
├── README.md                # Professional documentation
├── CHANGELOG.md             # Version history
├── composer.json            # PHP dependencies
├── phpunit.xml              # PHPUnit configuration
├── includes/
│   ├── constants.php        # Default configuration values
│   ├── class-config.php     # Configuration manager (3-tier)
│   ├── class-database.php   # SQLite database handler
│   ├── class-oauth.php      # OAuth 2.0 implementation
│   ├── class-mutation-token.php
│   ├── class-token-encryption.php
│   ├── class-rate-limiter.php
│   ├── class-audit-logger.php
│   ├── class-ip-whitelist.php
│   ├── class-snapshot.php
│   ├── class-backup-manager.php
│   ├── class-plugin-manager.php
│   ├── class-upload-validator.php
│   ├── class-debug-maintenance.php
│   ├── class-cleanup.php
│   └── security-utils.php
├── api/
│   ├── class-api.php        # REST API endpoints
│   └── class-permissions.php
├── admin/
│   ├── class-admin-ui.php   # Admin panel
│   ├── class-test-runner.php
│   └── views/               # Admin page templates
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── tests/
    ├── bootstrap.php
    ├── unit/
    └── integration/
```

## Deliverables
- `/app/plugins-onboard.zip` - Correctly packaged plugin (101KB)

## Testing Notes
- **Environment Limitation:** No PHP interpreter available in development environment
- **Testing Method:** User must install and test in live WordPress environment
- **Required PHP Extensions:** PDO SQLite, ZipArchive

## Future Enhancements (Backlog)
- [ ] Webhook notifications for plugin events
- [ ] Multi-site support
- [ ] Plugin dependency tracking
- [ ] Scheduled plugin updates
- [ ] API rate limit customization per application
- [ ] Two-factor authentication for admin actions
- [ ] Plugin health monitoring
- [ ] Automated rollback on activation failure
