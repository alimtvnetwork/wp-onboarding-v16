# 30. WordPress Plugin Structure

> **Version**: 1.0.0  
> **Last Updated**: 2025-01-26  
> **Status**: PRODUCTION-READY  
> **Applies To**: WordPress Plugin Development

---

## 30.1 Overview

This document establishes standardized patterns for WordPress plugin architecture, lifecycle management, and file organization. All plugins MUST follow these conventions to ensure consistency, maintainability, and compatibility.

---

## 30.2 Plugin File Organization

### Directory Structure

```
plugin-slug/
│
├── plugin-slug.php                    # Main plugin file (bootstrap)
├── uninstall.php                      # Cleanup on uninstall
├── composer.json                      # PSR-4 autoloading
├── README.md
│
├── /config/
│   ├── defaults.json                  # Seed data (Single Source of Truth)
│   └── constants.php                  # Fallback constants
│
├── /src/                              # PSR-4 root namespace
│   ├── /Admin/
│   │   ├── AdminMenu.php              # Menu registration
│   │   ├── AdminAssets.php            # Script/style enqueueing
│   │   └── AdminController.php        # Admin AJAX handlers
│   │
│   ├── /API/
│   │   ├── RestController.php         # REST API base class
│   │   └── *Endpoints.php             # Feature-specific endpoints
│   │
│   ├── /Core/
│   │   ├── Plugin.php                 # Main plugin class (singleton)
│   │   ├── Activator.php              # Activation logic
│   │   ├── Deactivator.php            # Deactivation logic
│   │   └── Loader.php                 # Hook/filter registration
│   │
│   ├── /Database/
│   │   ├── Migrator.php               # Schema migrations
│   │   ├── Seeder.php                 # Data seeding
│   │   └── /Models/                   # Entity models
│   │
│   ├── /Services/
│   │   └── *Service.php               # Business logic services
│   │
│   └── /Utils/
│       ├── Logger.php                 # Logging utility
│       ├── Sanitizer.php              # Input sanitization
│       ├── BooleanHelpers.php         # Positive boolean checks
│       └── ConditionalHelpers.php     # If-Avoidance utilities
│
├── /assets/
│   ├── /css/
│   ├── /js/
│   └── /images/
│
├── /languages/
│   └── plugin-slug.pot                # Translation template
│
├── /logs/
│   ├── app.log                        # General events
│   └── error.log                      # Errors with stack traces
│
├── /templates/
│   └── /admin/                        # Admin view templates
│
└── /tests/
    ├── /Unit/
    └── /Integration/
```

### File Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `ExamService.php` |
| Interfaces | PascalCase + Interface suffix | `CacheableInterface.php` |
| Traits | PascalCase + Trait suffix | `LoggableTrait.php` |
| Config files | lowercase-hyphen | `defaults.json` |
| Assets | lowercase-hyphen | `admin-styles.css` |

---

## 30.3 Main Plugin File (Bootstrap)

### Required Header

```php
<?php
/**
 * Plugin Name: Plugin Display Name
 * Plugin URI:  https://example.com/plugin
 * Description: Brief description of the plugin functionality.
 * Version:     1.0.0
 * Author:      Author Name
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: plugin-slug
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
```

### Bootstrap Pattern

```php
<?php
// After header...

/**
 * Plugin constants
 */
define('PLUGIN_SLUG_VERSION', '1.0.0');
define('PLUGIN_SLUG_FILE', __FILE__);
define('PLUGIN_SLUG_PATH', plugin_dir_path(__FILE__));
define('PLUGIN_SLUG_URL', plugin_dir_url(__FILE__));
define('PLUGIN_SLUG_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader registration
 */
if (file_exists(PLUGIN_SLUG_PATH . 'vendor/autoload.php')) {
    require_once PLUGIN_SLUG_PATH . 'vendor/autoload.php';
}

/**
 * Activation hook
 * @see Activator::activate()
 */
register_activation_hook(__FILE__, function () {
    try {
        \PluginNamespace\Core\Activator::activate();
    } catch (\Throwable $e) {
        \PluginNamespace\Utils\Logger::error(
            'Activation failed',
            [
                'file' => __FILE__,
                'action' => 'register_activation_hook',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]
        );
        // Re-throw to prevent activation
        throw $e;
    }
});

/**
 * Deactivation hook
 * @see Deactivator::deactivate()
 */
register_deactivation_hook(__FILE__, function () {
    try {
        \PluginNamespace\Core\Deactivator::deactivate();
    } catch (\Throwable $e) {
        \PluginNamespace\Utils\Logger::error(
            'Deactivation failed',
            [
                'file' => __FILE__,
                'action' => 'register_deactivation_hook',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]
        );
    }
});

/**
 * Initialize plugin
 */
add_action('plugins_loaded', function () {
    try {
        \PluginNamespace\Core\Plugin::getInstance()->init();
    } catch (\Throwable $e) {
        \PluginNamespace\Utils\Logger::error(
            'Plugin initialization failed',
            [
                'file' => __FILE__,
                'action' => 'plugins_loaded',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]
        );
    }
});
```

---

## 30.4 Activation Logic

### Activator Class

```php
<?php
namespace PluginNamespace\Core;

use PluginNamespace\Database\Migrator;
use PluginNamespace\Database\Seeder;
use PluginNamespace\Utils\Logger;

class Activator
{
    /**
     * Plugin activation handler
     * 
     * Execution Order:
     * 1. Check PHP version compatibility
     * 2. Check WordPress version compatibility
     * 3. Run database migrations
     * 4. Seed default data (version-triggered)
     * 5. Flush rewrite rules
     * 6. Log activation
     */
    public static function activate(): void
    {
        // 1. PHP Version Check
        self::checkPhpVersion();
        
        // 2. WordPress Version Check
        self::checkWpVersion();
        
        // 3. Database Migrations
        self::runMigrations();
        
        // 4. Seed Data (if version changed or first install)
        self::seedIfRequired();
        
        // 5. Flush Rewrite Rules
        flush_rewrite_rules();
        
        // 6. Log Success
        Logger::info('Plugin activated', [
            'version' => PLUGIN_SLUG_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ]);
    }
    
    /**
     * Check PHP version compatibility
     * @throws \RuntimeException If PHP version is incompatible
     */
    private static function checkPhpVersion(): void
    {
        $minPhp = '8.0';
        $isCompatible = version_compare(PHP_VERSION, $minPhp, '>=');
        
        if (!$isCompatible) {
            $message = sprintf(
                'Plugin requires PHP %s or higher. Current version: %s',
                $minPhp,
                PHP_VERSION
            );
            throw new \RuntimeException($message);
        }
    }
    
    /**
     * Check WordPress version compatibility
     * @throws \RuntimeException If WP version is incompatible
     */
    private static function checkWpVersion(): void
    {
        $minWp = '6.0';
        $currentWp = get_bloginfo('version');
        $isCompatible = version_compare($currentWp, $minWp, '>=');
        
        if (!$isCompatible) {
            $message = sprintf(
                'Plugin requires WordPress %s or higher. Current version: %s',
                $minWp,
                $currentWp
            );
            throw new \RuntimeException($message);
        }
    }
    
    /**
     * Run database migrations with error handling
     */
    private static function runMigrations(): void
    {
        try {
            Migrator::migrate();
        } catch (\Throwable $e) {
            Logger::error('Migration failed during activation', [
                'file' => __FILE__,
                'action' => 'runMigrations',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Seed data if version changed or first install
     * 
     * Trigger Conditions:
     * | Condition           | Action                              |
     * |---------------------|-------------------------------------|
     * | First Install       | Full seed of all keys               |
     * | Version Change      | Incremental seed (new keys only)    |
     * | Plugin Reactivation | Check version, seed if needed       |
     */
    private static function seedIfRequired(): void
    {
        $storedVersion = get_option('plugin_slug_version', '0.0.0');
        $currentVersion = PLUGIN_SLUG_VERSION;
        
        $isFirstInstall = ($storedVersion === '0.0.0');
        $isVersionChange = version_compare($storedVersion, $currentVersion, '<');
        
        if ($isFirstInstall || $isVersionChange) {
            try {
                Seeder::seed($isFirstInstall);
                update_option('plugin_slug_version', $currentVersion);
                
                Logger::info('Seeding completed', [
                    'previous_version' => $storedVersion,
                    'new_version' => $currentVersion,
                    'type' => $isFirstInstall ? 'full' : 'incremental'
                ]);
            } catch (\Throwable $e) {
                Logger::error('Seeding failed during activation', [
                    'file' => __FILE__,
                    'action' => 'seedIfRequired',
                    'previous_version' => $storedVersion,
                    'new_version' => $currentVersion,
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
    }
}
```

---

## 30.5 Deactivation Logic

### Deactivator Class

```php
<?php
namespace PluginNamespace\Core;

use PluginNamespace\Utils\Logger;

class Deactivator
{
    /**
     * Plugin deactivation handler
     * 
     * IMPORTANT: Deactivation should NOT delete data.
     * Data deletion happens only in uninstall.php
     * 
     * Execution Order:
     * 1. Clear scheduled cron jobs
     * 2. Flush rewrite rules
     * 3. Clear transients (optional)
     * 4. Log deactivation
     */
    public static function deactivate(): void
    {
        // 1. Clear Cron Jobs
        self::clearCronJobs();
        
        // 2. Flush Rewrite Rules
        flush_rewrite_rules();
        
        // 3. Clear Transients (optional)
        self::clearTransients();
        
        // 4. Log Deactivation
        Logger::info('Plugin deactivated', [
            'version' => PLUGIN_SLUG_VERSION
        ]);
    }
    
    /**
     * Clear all scheduled cron jobs
     */
    private static function clearCronJobs(): void
    {
        $cronHooks = [
            'plugin_slug_daily_cron',
            'plugin_slug_hourly_cron',
            'plugin_slug_cleanup_cron'
        ];
        
        foreach ($cronHooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            $hasScheduledJob = ($timestamp !== false);
            
            if ($hasScheduledJob) {
                wp_clear_scheduled_hook($hook);
                Logger::info('Cron job cleared', ['hook' => $hook]);
            }
        }
    }
    
    /**
     * Clear plugin transients
     */
    private static function clearTransients(): void
    {
        global $wpdb;
        
        try {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE %s 
                     OR option_name LIKE %s",
                    '_transient_plugin_slug_%',
                    '_transient_timeout_plugin_slug_%'
                )
            );
        } catch (\Throwable $e) {
            Logger::error('Failed to clear transients', [
                'file' => __FILE__,
                'action' => 'clearTransients',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            // Non-critical, don't throw
        }
    }
}
```

---

## 30.6 Uninstall Logic

### uninstall.php

```php
<?php
/**
 * Uninstall handler - runs when plugin is deleted
 * 
 * CRITICAL: This is the ONLY place where data should be deleted
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load autoloader for cleanup classes
require_once __DIR__ . '/vendor/autoload.php';

use PluginNamespace\Utils\Logger;

/**
 * Cleanup all plugin data
 */
function plugin_slug_uninstall(): void
{
    global $wpdb;
    
    try {
        // 1. Delete custom tables
        $tables = [
            $wpdb->prefix . 'plugin_table_1',
            $wpdb->prefix . 'plugin_table_2'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        // 2. Delete options
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE 'plugin_slug_%'"
        );
        
        // 3. Delete user meta
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'plugin_slug_%'"
        );
        
        // 4. Delete transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_plugin_slug_%' 
             OR option_name LIKE '_transient_timeout_plugin_slug_%'"
        );
        
        // 5. Delete uploaded files (if applicable)
        $uploadDir = wp_upload_dir();
        $pluginUploads = $uploadDir['basedir'] . '/plugin-slug';
        
        if (is_dir($pluginUploads)) {
            plugin_slug_delete_directory($pluginUploads);
        }
        
        Logger::info('Plugin uninstalled - all data removed');
        
    } catch (\Throwable $e) {
        Logger::error('Uninstall cleanup failed', [
            'file' => __FILE__,
            'action' => 'plugin_slug_uninstall',
            'error' => $e->getMessage(),
            'stack_trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Recursively delete directory
 */
function plugin_slug_delete_directory(string $dir): bool
{
    $isDirectory = is_dir($dir);
    
    if (!$isDirectory) {
        return false;
    }
    
    $items = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $isSubDirectory = is_dir($path);
        
        if ($isSubDirectory) {
            plugin_slug_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

// Execute uninstall
plugin_slug_uninstall();
```

---

## 30.7 Version Management

### Version Constants Pattern

```php
<?php
// In main plugin file
define('PLUGIN_SLUG_VERSION', '1.0.0');

// In config/constants.php (fallback)
class Consts
{
    public const VERSION = '1.0.0';
    public const DB_VERSION = '1.0.0';
    public const MIN_PHP_VERSION = '8.0';
    public const MIN_WP_VERSION = '6.0';
}
```

### Version Comparison Helper

```php
<?php
namespace PluginNamespace\Utils;

class VersionHelper
{
    /**
     * Check if update is needed
     */
    public static function needsUpdate(): bool
    {
        $stored = get_option('plugin_slug_version', '0.0.0');
        $current = PLUGIN_SLUG_VERSION;
        
        return version_compare($stored, $current, '<');
    }
    
    /**
     * Check if specific version migration needed
     */
    public static function needsMigrationFrom(string $fromVersion): bool
    {
        $stored = get_option('plugin_slug_version', '0.0.0');
        
        $isPastFromVersion = version_compare($stored, $fromVersion, '>=');
        
        return !$isPastFromVersion;
    }
}
```

---

## 30.8 PSR-4 Autoloading

### composer.json

```json
{
    "name": "vendor/plugin-slug",
    "description": "Plugin description",
    "type": "wordpress-plugin",
    "license": "GPL-2.0+",
    "autoload": {
        "psr-4": {
            "PluginNamespace\\": "src/"
        }
    },
    "require": {
        "php": ">=8.0"
    }
}
```

### Manual Autoloader (Alternative)

```php
<?php
/**
 * Simple PSR-4 autoloader for environments without Composer
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'PluginNamespace\\';
    $baseDir = PLUGIN_SLUG_PATH . 'src/';
    
    $len = strlen($prefix);
    $startsWithPrefix = strncmp($prefix, $class, $len) === 0;
    
    if (!$startsWithPrefix) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    $fileExists = file_exists($file);
    
    if ($fileExists) {
        try {
            require $file;
        } catch (\Throwable $e) {
            \PluginNamespace\Utils\Logger::error('Autoload failed', [
                'file' => $file,
                'class' => $class,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
});
```

---

## 30.9 Checklist

### Plugin Structure
- [ ] Main plugin file has required header with all metadata
- [ ] PSR-4 autoloading configured via Composer or manual loader
- [ ] Directory structure follows standardized layout
- [ ] Constants defined for version, paths, and URLs

### Activation
- [ ] PHP version compatibility check with clear error
- [ ] WordPress version compatibility check
- [ ] Database migrations run with try-catch and logging
- [ ] Seeding triggered on version change
- [ ] Rewrite rules flushed
- [ ] Activation logged with version info

### Deactivation
- [ ] All cron jobs cleared
- [ ] Rewrite rules flushed
- [ ] Transients optionally cleared
- [ ] NO data deleted (reserved for uninstall)
- [ ] Deactivation logged

### Uninstall
- [ ] `uninstall.php` exists at plugin root
- [ ] Checks `WP_UNINSTALL_PLUGIN` constant
- [ ] Deletes all custom tables
- [ ] Deletes all options
- [ ] Deletes all user meta
- [ ] Deletes all transients
- [ ] Removes uploaded files
- [ ] Full cleanup logged

### Error Handling
- [ ] All lifecycle hooks wrapped in try-catch
- [ ] Errors logged with file, action, message, and stack trace
- [ ] Critical errors re-thrown to prevent partial states

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - Naming conventions
- [02-error-management-foundation.md](../01-foundation/02-error-management-foundation.md) - Error handling patterns
- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Logging standards
- [02-configuration-hierarchy-systems.md](../02-systems/02-configuration-hierarchy-systems.md) - 3-tier config pattern
- [03-conditional-helpers-systems.md](../02-systems/03-conditional-helpers-systems.md) - If-Avoidance patterns
- [03-cron-system-wordpress.md](./03-cron-system-wordpress.md) - Cron job patterns
