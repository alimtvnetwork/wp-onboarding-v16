# 03 - Plugin Structure

> **Phase:** Foundation  
> **Dependencies:** `01-coding-spec.md`, `02-error-management.md`  
> **Estimated Time:** 3-4 hours  
> **Last Updated:** 2026-01-31

---

## 📋 Scope

Set up the WordPress plugin skeleton with proper file structure, PSR-4 autoloading, lifecycle hooks, and directory creation.

---

## 📁 Directory Structure

```
link-manager/
│
├── link-manager.php                    # Main plugin file (bootstrap)
├── uninstall.php                       # Cleanup on uninstall
├── composer.json                       # PSR-4 autoloading
├── README.md
│
├── /config/
│   ├── defaults.json                   # Seed data (Single Source of Truth)
│   └── constants.php                   # Fallback constants
│
├── /src/                               # PSR-4 root namespace: LinkManager
│   ├── /Admin/
│   │   ├── AdminMenu.php               # Menu registration
│   │   ├── AdminAssets.php             # Script/style enqueueing
│   │   └── AdminController.php         # Admin AJAX handlers
│   │
│   ├── /API/
│   │   ├── RestController.php          # REST API base class
│   │   ├── ScanEndpoints.php           # Scan-related endpoints
│   │   ├── PostEndpoints.php           # Post/page endpoints
│   │   ├── LinkEndpoints.php           # Link modification endpoints
│   │   ├── HistoryEndpoints.php        # History/rollback endpoints
│   │   └── SnapshotEndpoints.php       # Snapshot endpoints
│   │
│   ├── /Core/
│   │   ├── Plugin.php                  # Main plugin class (singleton)
│   │   ├── Activator.php               # Activation logic
│   │   ├── Deactivator.php             # Deactivation logic
│   │   └── Loader.php                  # Hook/filter registration
│   │
│   ├── /Database/
│   │   ├── Connection.php              # Main DB connection
│   │   ├── HistoryConnection.php       # Per-content history DB connection
│   │   ├── Schema.php                  # Main schema definition
│   │   ├── HistorySchema.php           # History DB schema
│   │   ├── Migrator.php                # Schema migrations
│   │   ├── Seeder.php                  # Data seeding
│   │   └── /Models/
│   │       ├── Post.php
│   │       ├── Page.php
│   │       ├── Category.php
│   │       ├── Link.php
│   │       ├── ScanHistory.php
│   │       ├── Snapshot.php
│   │       └── Settings.php
│   │
│   ├── /Services/
│   │   ├── ScanService.php             # Link scanning orchestration
│   │   ├── LinkParser.php              # HTML/JSON-LD parsing
│   │   ├── ElementorParser.php         # Elementor content handling
│   │   ├── HistoryService.php          # Version history management
│   │   ├── SnapshotService.php         # Snapshot management
│   │   ├── ModificationService.php     # Link editing/removal
│   │   ├── CsvImportService.php        # CSV import handling
│   │   └── HttpChecker.php             # URL status checking
│   │
│   ├── /Cron/
│   │   ├── CronManager.php             # Cron job registration
│   │   ├── ScanJob.php                 # Background scan job
│   │   └── CleanupJob.php              # Log/history cleanup
│   │
│   ├── /Enums/
│   │   ├── LinkStatus.php
│   │   ├── LinkWordCount.php
│   │   ├── LinkWrapper.php
│   │   ├── ContentType.php
│   │   ├── ScanMode.php
│   │   ├── ScanStatus.php
│   │   ├── ModificationType.php
│   │   └── SnapshotType.php
│   │
│   └── /Utils/
│       ├── Logger.php                  # Logging utility
│       ├── Sanitizer.php               # Input sanitization
│       ├── FileManager.php             # File/folder operations
│       └── HtmlValidator.php           # HTML validation
│
├── /assets/
│   ├── /css/
│   │   └── admin.css
│   ├── /js/
│   │   ├── admin.js
│   │   └── components/                 # React components
│   └── /images/
│
├── /languages/
│   └── link-manager.pot                # Translation template
│
├── /logs/                              # Created at runtime in uploads folder
│
└── /tests/
    ├── /Unit/
    └── /Integration/
```

---

## 🚀 Main Plugin File

**File:** `link-manager.php`

```php
<?php
/**
 * Plugin Name: Link Manager
 * Plugin URI:  https://example.com/link-manager
 * Description: Comprehensive link management for WordPress posts, pages, and categories with full history and rollback support.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: link-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin constants
 */
define('LM_VERSION', '1.0.0');
define('LM_DB_VERSION', '1.0.0');
define('LM_FILE', __FILE__);
define('LM_PATH', plugin_dir_path(__FILE__));
define('LM_URL', plugin_dir_url(__FILE__));
define('LM_BASENAME', plugin_basename(__FILE__));
define('LM_SLUG', 'link-manager');

// Data folder in uploads
$uploadDir = wp_upload_dir();
define('LM_DATA_PATH', $uploadDir['basedir'] . '/link-manager/');
define('LM_DB_PATH', LM_DATA_PATH . 'link-manager.db');
define('LM_LOG_PATH', LM_DATA_PATH . 'logs/');
define('LM_HISTORY_PATH', LM_DATA_PATH . 'history-manage/');
define('LM_SNAPSHOT_PATH', LM_DATA_PATH . 'snapshots/');

/**
 * Autoloader registration
 */
if (file_exists(LM_PATH . 'vendor/autoload.php')) {
    require_once LM_PATH . 'vendor/autoload.php';
}

/**
 * Activation hook
 * @see Activator::activate()
 */
register_activation_hook(__FILE__, function () {
    try {
        \LinkManager\Core\Activator::activate();
    } catch (\Throwable $e) {
        \LinkManager\Utils\Logger::error(
            'Activation failed',
            [
                'file' => __FILE__,
                'action' => 'register_activation_hook',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]
        );
        throw $e;
    }
});

/**
 * Deactivation hook
 * @see Deactivator::deactivate()
 */
register_deactivation_hook(__FILE__, function () {
    try {
        \LinkManager\Core\Deactivator::deactivate();
    } catch (\Throwable $e) {
        \LinkManager\Utils\Logger::error(
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
        \LinkManager\Core\Plugin::getInstance()->init();
    } catch (\Throwable $e) {
        \LinkManager\Utils\Logger::error(
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

## 🔧 Activator Class

**File:** `src/Core/Activator.php`

```php
<?php
namespace LinkManager\Core;

use LinkManager\Database\Schema;
use LinkManager\Database\Seeder;
use LinkManager\Utils\Logger;
use LinkManager\Utils\FileManager;

class Activator
{
    /**
     * Plugin activation handler
     * 
     * Execution Order:
     * 1. Check PHP version compatibility
     * 2. Check WordPress version compatibility
     * 3. Create data folder structure
     * 4. Run database migrations
     * 5. Seed default data
     * 6. Register cron jobs
     * 7. Flush rewrite rules
     * 8. Log activation
     */
    public static function activate(): void
    {
        self::checkPhpVersion();
        self::checkWpVersion();
        self::createDataFolders();
        self::runMigrations();
        self::seedIfRequired();
        self::registerCronJobs();
        flush_rewrite_rules();
        
        Logger::info('Plugin activated', [
            'version' => LM_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ]);
    }
    
    /**
     * Check PHP version compatibility
     */
    private static function checkPhpVersion(): void
    {
        $minPhp = '8.0';
        $isCompatible = version_compare(PHP_VERSION, $minPhp, '>=');
        
        if (!$isCompatible) {
            throw new \RuntimeException(sprintf(
                'Link Manager requires PHP %s or higher. Current version: %s',
                $minPhp,
                PHP_VERSION
            ));
        }
    }
    
    /**
     * Check WordPress version compatibility
     */
    private static function checkWpVersion(): void
    {
        $minWp = '6.0';
        $currentWp = get_bloginfo('version');
        $isCompatible = version_compare($currentWp, $minWp, '>=');
        
        if (!$isCompatible) {
            throw new \RuntimeException(sprintf(
                'Link Manager requires WordPress %s or higher. Current version: %s',
                $minWp,
                $currentWp
            ));
        }
    }
    
    /**
     * Create required data folder structure
     */
    private static function createDataFolders(): void
    {
        $folders = [
            LM_DATA_PATH,
            LM_LOG_PATH,
            LM_HISTORY_PATH,
            LM_HISTORY_PATH . 'posts/',
            LM_HISTORY_PATH . 'pages/',
            LM_HISTORY_PATH . 'categories/',
            LM_SNAPSHOT_PATH,
            LM_DATA_PATH . 'imports/',
            LM_DATA_PATH . 'exports/',
        ];
        
        foreach ($folders as $folder) {
            FileManager::ensureDirectory($folder);
        }
        
        // Create .htaccess to protect data folder
        FileManager::protectDirectory(LM_DATA_PATH);
        
        Logger::info('Data folders created', ['path' => LM_DATA_PATH]);
    }
    
    /**
     * Run database migrations
     */
    private static function runMigrations(): void
    {
        try {
            Schema::initialize();
            Logger::info('Database schema initialized');
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
     */
    private static function seedIfRequired(): void
    {
        $storedVersion = get_option('lm_version', '0.0.0');
        $currentVersion = LM_VERSION;
        
        $isFirstInstall = ($storedVersion === '0.0.0');
        $isVersionChange = version_compare($storedVersion, $currentVersion, '<');
        
        if ($isFirstInstall || $isVersionChange) {
            try {
                Seeder::seed($isFirstInstall);
                update_option('lm_version', $currentVersion);
                
                Logger::info('Seeding completed', [
                    'previous_version' => $storedVersion,
                    'new_version' => $currentVersion,
                    'type' => $isFirstInstall ? 'full' : 'incremental'
                ]);
            } catch (\Throwable $e) {
                Logger::error('Seeding failed during activation', [
                    'file' => __FILE__,
                    'action' => 'seedIfRequired',
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
    }
    
    /**
     * Register cron jobs
     */
    private static function registerCronJobs(): void
    {
        \LinkManager\Cron\CronManager::registerAll();
    }
}
```

---

## 🔌 Plugin Class (Singleton)

**File:** `src/Core/Plugin.php`

```php
<?php
namespace LinkManager\Core;

use LinkManager\Admin\AdminMenu;
use LinkManager\Admin\AdminAssets;
use LinkManager\API\RestController;
use LinkManager\Cron\CronManager;
use LinkManager\Utils\Logger;

class Plugin
{
    private static ?Plugin $instance = null;
    private bool $initialized = false;
    
    private function __construct() {}
    
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize plugin components
     */
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }
        
        $this->initialized = true;
        
        // Load text domain
        $this->loadTextDomain();
        
        // Admin only
        if (is_admin()) {
            $this->initAdmin();
        }
        
        // REST API
        $this->initRestApi();
        
        // Cron callbacks
        CronManager::registerCallbacks();
        
        Logger::debug('Plugin initialized');
    }
    
    /**
     * Load plugin text domain
     */
    private function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'link-manager',
            false,
            dirname(LM_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Initialize admin components
     */
    private function initAdmin(): void
    {
        AdminMenu::register();
        AdminAssets::register();
    }
    
    /**
     * Initialize REST API
     */
    private function initRestApi(): void
    {
        add_action('rest_api_init', function () {
            RestController::registerRoutes();
        });
    }
}
```

---

## 📦 Composer Configuration

**File:** `composer.json`

```json
{
    "name": "your-vendor/link-manager",
    "description": "WordPress plugin for comprehensive link management",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "squizlabs/php_codesniffer": "^3.7"
    },
    "autoload": {
        "psr-4": {
            "LinkManager\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "LinkManager\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "lint": "phpcs --standard=WordPress src/"
    }
}
```

---

## ✅ Acceptance Criteria

### Directory Structure
- [ ] All folders created as specified
- [ ] PSR-4 autoloading works correctly
- [ ] Namespace `LinkManager` resolves to `src/`

### Activation
- [ ] Data folders created in uploads
- [ ] .htaccess protection file created
- [ ] SQLite database initialized
- [ ] Default settings seeded
- [ ] Cron jobs registered
- [ ] Version stored in options

### Deactivation
- [ ] Cron jobs cleared
- [ ] Transients cleared
- [ ] Data NOT deleted (only on uninstall)

### Uninstall
- [ ] All plugin options removed
- [ ] All data folders removed
- [ ] Database file removed

### WordPress Integration
- [ ] Plugin appears in plugins list
- [ ] Activation without errors
- [ ] Deactivation without errors
- [ ] Admin menu visible after activation

---

## 📝 Related Specifications

- `04-database-schema.md` - Database tables
- `05-data-folder-structure.md` - Data organization
- `07-logging-system.md` - Logging setup
- `66-shared-constants.md` - All constants

---

*Follow WordPress plugin patterns from `spec/general-spec/10-wordpress/01-plugin-structure-wordpress.md`*
