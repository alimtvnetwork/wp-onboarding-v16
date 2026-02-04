# PHP and WordPress Compatibility

## PHP Version Requirements

### Minimum: PHP 7.4

WordPress 5.6+ requires PHP 7.4. Target this as the minimum:

```php
/**
 * Plugin Name: My Plugin
 * Requires PHP: 7.4
 */
```

### Version Check in Plugin

```php
// At the top of main plugin file, after headers
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error">';
        echo '<p><strong>My Plugin</strong> requires PHP 7.4 or higher. ';
        echo 'Current version: ' . PHP_VERSION . '</p>';
        echo '</div>';
    });
    return;  // Stop plugin execution
}
```

## PHP 7.4 Specific Considerations

### Available Features (Use These)

```php
// Typed properties
class MyClass {
    private string $name;
    private ?int $id = null;
}

// Arrow functions
$filtered = array_filter($items, fn($item) => $item->active);

// Null coalescing assignment
$config['option'] ??= 'default';

// Spread operator in arrays
$merged = [...$array1, ...$array2];
```

### Not Available (PHP 8.0+)

```php
// ❌ Named arguments (PHP 8.0)
str_contains(haystack: $str, needle: 'test');

// ❌ Constructor property promotion (PHP 8.0)
public function __construct(private string $name) {}

// ❌ Match expression (PHP 8.0)
$result = match($value) { 1 => 'one', 2 => 'two' };

// ❌ Nullsafe operator (PHP 8.0)
$result = $obj?->method();
```

### Replacements for PHP 8+ Features

```php
// Instead of nullsafe operator
$result = $obj !== null ? $obj->method() : null;

// Instead of named arguments
str_contains($str, 'test');

// Instead of match expression
switch ($value) {
    case 1: $result = 'one'; break;
    case 2: $result = 'two'; break;
}

// Instead of constructor promotion
class MyClass {
    private string $name;
    
    public function __construct(string $name) {
        $this->name = $name;
    }
}
```

## Date/Time Formatting

### PHP 7.4 Compatible Milliseconds

```php
// ❌ PHP 8.0+ format
$timestamp = date('Y-m-d\TH:i:s.v\Z');  // 'v' requires PHP 8.0

// ✅ PHP 7.4 compatible
$now = microtime(true);
$seconds = floor($now);
$milliseconds = round(($now - $seconds) * 1000);
$timestamp = gmdate('Y-m-d\TH:i:s', $seconds) . sprintf('.%03dZ', $milliseconds);
// Result: 2026-02-04T11:32:15.847Z
```

## WordPress Version Requirements

### Minimum: WordPress 5.6

```php
/**
 * Plugin Name: My Plugin
 * Requires at least: 5.6
 */

// Version check
global $wp_version;
if (version_compare($wp_version, '5.6', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error">';
        echo '<p><strong>My Plugin</strong> requires WordPress 5.6 or higher.</p>';
        echo '</div>';
    });
    return;
}
```

### WordPress 5.6+ Features

- **Application Passwords** - Native REST API authentication
- **PHP 8.0 compatibility** - Core WordPress is compatible
- **Block Editor improvements**
- **Auto-updates for plugins/themes**

## Function Availability

### Check Before Using

Some functions may not exist in older versions:

```php
// Safe usage of newer functions
if (function_exists('wp_is_application_passwords_available')) {
    $available = wp_is_application_passwords_available();
} else {
    $available = false;
}
```

### Common Functions by Version

| Function | Available From |
|----------|---------------|
| `wp_upload_dir()` | WP 2.0 |
| `register_rest_route()` | WP 4.4 |
| `wp_json_encode()` | WP 4.1 |
| `wp_remote_get()` | WP 2.7 |
| `wp_mkdir_p()` | WP 2.0 |
| `wp_hash()` | WP 2.0.3 |

## Early Loading Compatibility

### Functions NOT Available During Plugin Load

These functions require WordPress to be fully loaded:

```php
// ❌ NOT AVAILABLE during plugin file include
wp_upload_dir()        // Needs WordPress fully loaded
get_option()           // Needs database connected
is_admin()             // May not be set yet
current_user_can()     // User not authenticated yet
plugin_dir_url()       // May fail on some servers

// ✅ AVAILABLE during plugin file include
plugin_dir_path()      // Safe - just path manipulation
defined('ABSPATH')     // Constants are set
```

### Safe Alternatives During Load

```php
// Instead of wp_upload_dir() in constructor
class MyClass {
    private $upload_dir = null;
    
    private function get_upload_dir() {
        if ($this->upload_dir === null) {
            $this->upload_dir = wp_upload_dir();
        }
        return $this->upload_dir;
    }
}

// Instead of plugin_dir_url() as constant
function myplugin_get_url() {
    static $url = null;
    if ($url === null) {
        $url = plugin_dir_url(MYPLUGIN_FILE);
    }
    return $url;
}
```

## Filesystem Compatibility

### Cross-Platform Paths

```php
// Use WordPress constants
$wp_content = WP_CONTENT_DIR;  // Absolute path to wp-content
$plugins = WP_PLUGIN_DIR;       // Absolute path to plugins

// Use PHP's directory separator
$path = RISEUP_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . 'class-file.php';

// Or just use forward slashes (works everywhere)
$path = RISEUP_PLUGIN_DIR . 'includes/class-file.php';
```

### Use Native PHP for Critical Operations

During plugin initialization, prefer native PHP over WordPress wrappers:

```php
// Directory creation
if (!is_dir($path)) {
    @mkdir($path, 0755, true);  // Native PHP - always works
}

// Vs WordPress wrapper (may have dependencies)
// wp_mkdir_p($path);

// File writing
@file_put_contents($path, $content, LOCK_EX);  // Native PHP

// Vs WordPress
// Use native for logging during init, WordPress functions later
```

## SQLite Compatibility

### PDO SQLite

Available on most PHP installations:

```php
// Check availability
if (!extension_loaded('pdo_sqlite')) {
    throw new Exception('PDO SQLite extension is required');
}

// Use with caution
try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Handle missing SQLite support
}
```

### DateTime Handling in SQLite

SQLite doesn't have a native DATETIME type - store as TEXT:

```php
// Store as ISO 8601
$created_at = gmdate('Y-m-d H:i:s');  // UTC

// Query with string comparison (works for ISO format)
"SELECT * FROM logs WHERE created_at > '2026-02-04 00:00:00'"
```

## Testing Across Versions

### Local Development

Use Docker or local environments to test multiple PHP versions:

```bash
# Test on PHP 7.4
docker run -v $(pwd):/app -w /app php:7.4-cli php -l my-plugin.php

# Test on PHP 8.0
docker run -v $(pwd):/app -w /app php:8.0-cli php -l my-plugin.php
```

### Minimum Viable Testing

At minimum, test:
- PHP 7.4 + WordPress 5.6 (minimum supported)
- PHP 8.0 + WordPress 6.0 (common production)
- PHP 8.2 + Latest WordPress (bleeding edge)

## Deprecation Handling

### Check for Deprecated Functions

```php
// Before using a function that might be deprecated
if (!function_exists('some_old_function')) {
    // Use replacement
    function some_old_function() {
        return new_function();
    }
}
```

### Silence Deprecation Warnings in Production

```php
// Only during initialization if needed
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    error_reporting(E_ALL & ~E_DEPRECATED);
}
```
