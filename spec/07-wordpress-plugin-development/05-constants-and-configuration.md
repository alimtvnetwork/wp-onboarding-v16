# Constants and Configuration Management

## The Golden Rule

> **NEVER use magic strings in code. ALWAYS use constants.**

Every string that represents a name, path, key, or identifier must be defined as a constant in a centralized file.

## Constants File Structure

Create `includes/constants.php` as the FIRST file included in your plugin:

```php
<?php
/**
 * Plugin Constants
 * 
 * ALL magic strings, keys, and identifiers are defined here.
 * This file is included FIRST and contains NO function calls.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// PLUGIN IDENTITY
// ============================================================================

/** Plugin slug - used for directories, options, etc. */
define('RISEUP_PLUGIN_SLUG', 'riseup-asia-uploader');

/** Plugin display name */
define('RISEUP_PLUGIN_NAME', 'Riseup Asia Uploader');

/** Plugin version - update with each release */
define('RISEUP_VERSION', '1.4.0');

// ============================================================================
// API CONFIGURATION
// ============================================================================

/** REST API namespace (without version) */
define('RISEUP_API_BASE', 'riseup-asia-uploader');

/** REST API version */
define('RISEUP_API_VERSION', 'v1');

/** Full REST API namespace */
define('RISEUP_API_NAMESPACE', RISEUP_API_BASE . '/' . RISEUP_API_VERSION);

// ============================================================================
// API ENDPOINTS
// ============================================================================

/** Health check endpoint */
define('RISEUP_ENDPOINT_HEALTH', 'health');

/** File upload endpoint */
define('RISEUP_ENDPOINT_UPLOAD', 'upload');

/** Status check endpoint */
define('RISEUP_ENDPOINT_STATUS', 'status');

/** Plugin management endpoints */
define('RISEUP_ENDPOINT_PLUGINS', 'plugins');
define('RISEUP_ENDPOINT_PLUGIN_ENABLE', 'plugin-enable');
define('RISEUP_ENDPOINT_PLUGIN_DISABLE', 'plugin-disable');
define('RISEUP_ENDPOINT_PLUGIN_DELETE', 'plugin-delete');

/** Post management endpoints */
define('RISEUP_ENDPOINT_POSTS', 'posts');
define('RISEUP_ENDPOINT_POST_CREATE', 'post-create');
define('RISEUP_ENDPOINT_POST_UPDATE', 'post-update');

/** Category endpoints */
define('RISEUP_ENDPOINT_CATEGORIES', 'categories');

/** Media endpoints */
define('RISEUP_ENDPOINT_MEDIA', 'media');

// ============================================================================
// DATABASE
// ============================================================================

/** SQLite database filename */
define('RISEUP_DB_FILENAME', 'riseup-asia-uploader.db');

/** Transactions table name */
define('RISEUP_TABLE_TRANSACTIONS', 'riseup_transactions');

/** Logs table name */
define('RISEUP_TABLE_LOGS', 'riseup_logs');

/** Schema version table */
define('RISEUP_TABLE_SCHEMA_VERSION', 'schema_version');

// ============================================================================
// OPTIONS (WordPress wp_options keys)
// ============================================================================

/** API token option key */
define('RISEUP_OPTION_API_TOKEN', 'riseup_api_token');

/** Settings option key */
define('RISEUP_OPTION_SETTINGS', 'riseup_settings');

/** Last sync timestamp */
define('RISEUP_OPTION_LAST_SYNC', 'riseup_last_sync');

// ============================================================================
// DIRECTORIES
// ============================================================================

/** Logs subdirectory name */
define('RISEUP_DIR_LOGS', 'logs');

/** Data subdirectory name */
define('RISEUP_DIR_DATA', 'data');

/** Uploads subdirectory name */
define('RISEUP_DIR_UPLOADS', 'uploads');

// ============================================================================
// FILES
// ============================================================================

/** Main log file name */
define('RISEUP_FILE_LOG', 'log.txt');

/** Error log file name */
define('RISEUP_FILE_ERROR_LOG', 'error.txt');

/** Upload ignore file name */
define('RISEUP_FILE_UPLOADIGNORE', '.uploadignore');

// ============================================================================
// TRANSIENT KEYS (for caching)
// ============================================================================

/** Rate limit prefix */
define('RISEUP_TRANSIENT_RATE_LIMIT', 'riseup_rate_');

/** Cache prefix */
define('RISEUP_TRANSIENT_CACHE', 'riseup_cache_');

// ============================================================================
// HOOK NAMES
// ============================================================================

/** Plugin activated hook */
define('RISEUP_HOOK_ACTIVATED', 'riseup_plugin_activated');

/** Plugin deactivated hook */
define('RISEUP_HOOK_DEACTIVATED', 'riseup_plugin_deactivated');

/** Before upload hook */
define('RISEUP_HOOK_BEFORE_UPLOAD', 'riseup_before_upload');

/** After upload hook */
define('RISEUP_HOOK_AFTER_UPLOAD', 'riseup_after_upload');

// ============================================================================
// DEFAULTS
// ============================================================================

/** Default rate limit (requests per minute) */
define('RISEUP_DEFAULT_RATE_LIMIT', 60);

/** Default log retention (days) */
define('RISEUP_DEFAULT_LOG_RETENTION', 30);

/** Maximum upload size (bytes) - 50MB */
define('RISEUP_MAX_UPLOAD_SIZE', 52428800);
```

## Usage Examples

### In REST Route Registration

```php
// ❌ WRONG - Magic strings everywhere
register_rest_route(
    'riseup-asia-uploader/v1',  // Magic string!
    '/upload',                   // Magic string!
    [...]
);

// ✅ CORRECT - Using constants
register_rest_route(
    RISEUP_API_NAMESPACE,
    '/' . RISEUP_ENDPOINT_UPLOAD,
    [...]
);
```

### In Database Operations

```php
// ❌ WRONG
$this->pdo->exec("CREATE TABLE riseup_transactions (...)");

// ✅ CORRECT
$this->pdo->exec("CREATE TABLE " . RISEUP_TABLE_TRANSACTIONS . " (...)");
```

### In WordPress Options

```php
// ❌ WRONG
update_option('riseup_api_token', $token);

// ✅ CORRECT
update_option(RISEUP_OPTION_API_TOKEN, $token);
```

### In File Paths

```php
// ❌ WRONG
$log_path = $upload_dir . '/riseup-asia-uploader/logs/log.txt';

// ✅ CORRECT
$log_path = $upload_dir . '/' . RISEUP_PLUGIN_SLUG . '/' . RISEUP_DIR_LOGS . '/' . RISEUP_FILE_LOG;
```

## Configuration Options

For runtime configuration (user settings), use WordPress options:

```php
class Riseup_Config {
    private static $defaults = [
        'rate_limit' => RISEUP_DEFAULT_RATE_LIMIT,
        'log_retention_days' => RISEUP_DEFAULT_LOG_RETENTION,
        'allowed_ips' => [],
        'debug_mode' => false,
    ];
    
    public static function get($key, $default = null) {
        $options = get_option(RISEUP_OPTION_SETTINGS, []);
        
        if (isset($options[$key])) {
            return $options[$key];
        }
        
        if (isset(self::$defaults[$key])) {
            return self::$defaults[$key];
        }
        
        return $default;
    }
    
    public static function set($key, $value) {
        $options = get_option(RISEUP_OPTION_SETTINGS, []);
        $options[$key] = $value;
        update_option(RISEUP_OPTION_SETTINGS, $options);
    }
    
    public static function get_all() {
        $options = get_option(RISEUP_OPTION_SETTINGS, []);

        return array_merge(self::$defaults, $options);
    }
}
```

## Environment-Specific Configuration

For sensitive or environment-specific values, check wp-config.php first:

```php
// In wp-config.php (not committed to git)
define('RISEUP_DEBUG', true);
define('RISEUP_ALLOWED_IPS', '192.168.1.1,10.0.0.1');

// In plugin code
class Riseup_Config {
    public static function is_debug() {
        return defined('RISEUP_DEBUG') && RISEUP_DEBUG;
    }
    
    public static function get_allowed_ips() {
        if (defined('RISEUP_ALLOWED_IPS')) {
            return array_map('trim', explode(',', RISEUP_ALLOWED_IPS));
        }

        return self::get('allowed_ips', []);
    }
}
```

## Benefits of This Approach

### 1. **Find and Replace**
When you need to change a value, change it in ONE place:
```php
// Change from 'v1' to 'v2'
define('RISEUP_API_VERSION', 'v2');  // All routes updated!
```

### 2. **IDE Autocomplete**
Constants are recognized by IDEs, providing autocomplete and preventing typos.

### 3. **Documentation**
Constants serve as self-documenting code:
```php
RISEUP_ENDPOINT_PLUGIN_ENABLE  // Clear what this does
'plugin-enable'                 // What is this string for?
```

### 4. **Refactoring Safety**
Renaming a constant will cause errors if any usage is missed. Magic strings fail silently.

### 5. **Testing**
Constants can be easily mocked or overridden in tests:
```php
// In test setup
if (!defined('RISEUP_API_NAMESPACE')) {
    define('RISEUP_API_NAMESPACE', 'test-namespace/v1');
}
```

## Common Mistakes to Avoid

### 1. Concatenating Constants Incorrectly
```php
// ❌ WRONG - Creates undefined constant warning
define('RISEUP_FULL_PATH', RISEUP_DIR_LOGS . RISEUP_FILE_LOG);

// ✅ CORRECT - Use at runtime
$path = RISEUP_DIR_LOGS . '/' . RISEUP_FILE_LOG;
```

### 2. Using Constants Before Definition
```php
// ❌ WRONG - constants.php must be included first
require_once 'class-database.php';  // Uses constants
require_once 'constants.php';       // Too late!

// ✅ CORRECT
require_once 'constants.php';       // ALWAYS first
require_once 'class-database.php';
```

### 3. Defining Constants Conditionally Without Check
```php
// ❌ WRONG - Will error if already defined
define('MY_CONSTANT', 'value');

// ✅ CORRECT - Safe to include multiple times
if (!defined('MY_CONSTANT')) {
    define('MY_CONSTANT', 'value');
}
```
