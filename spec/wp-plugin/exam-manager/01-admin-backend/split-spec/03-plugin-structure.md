# 01 - Plugin Structure & Setup

> **Phase:** Foundation  
> **Dependencies:** None  
> **Estimated Time:** 2-4 hours

---

## 📋 Scope

Set up the WordPress plugin skeleton with proper file structure, autoloading, and directory creation.

---

## 📁 Directory Structure

Create the following structure:

```
/wp-content/plugins/exam-questions-manager/
├── exam-questions-manager.php          # Main plugin file
├── uninstall.php                        # Cleanup on uninstall
├── composer.json                        # PHP dependencies
├── package.json                         # JS dependencies (for admin React app)
│
├── /src/
│   ├── /Admin/
│   │   ├── AdminMenu.php               # WordPress admin menu registration
│   │   ├── AdminAssets.php             # Enqueue scripts/styles
│   │   └── AdminController.php         # Admin AJAX handlers
│   │
│   ├── /API/
│   │   ├── RestController.php          # REST API base class
│   │   ├── ExamEndpoints.php
│   │   ├── WikiEndpoints.php
│   │   ├── ParticipantEndpoints.php
│   │   ├── SecretKeyEndpoints.php
│   │   └── PublicEndpoints.php
│   │
│   ├── /Database/
│   │   ├── Schema.php                  # SQLite schema definitions
│   │   ├── Migrations.php              # Schema migrations
│   │   ├── Connection.php              # PDO wrapper
│   │   └── Seeder.php                  # Default data seeding
│   │
│   ├── /ORM/
│   │   ├── Model.php                   # Base model class
│   │   ├── Repository.php              # Base repository class
│   │   └── /Models/
│   │       └── (entity files - created in 06)
│   │
│   ├── /Services/
│   │   └── (service files - created later)
│   │
│   ├── /Cron/
│   │   └── (cron files - created in 33)
│   │
│   ├── /Enums/
│   │   └── (enum files - created in 04)
│   │
│   └── /Utils/
│       ├── Logger.php                  # Logging utility
│       ├── Validator.php               # Input validation
│       ├── Sanitizer.php               # Data sanitization
│       └── FileHandler.php             # File operations
│
├── /admin/                             # React admin app (built separately)
│   ├── /src/
│   └── /build/
│
├── /public/                            # Frontend participant interface
│   ├── /src/
│   └── /build/
│
└── /assets/
    ├── /css/
    └── /images/
```

---

## 📁 Uploads Directory Structure

Create on activation:

```
/wp-content/uploads/exam-questions-manager/
├── /questions/                         # Markdown exam files
├── /extensions/                        # Extension request attachments
├── /seeding/
│   └── /email-templates/               # Default email template HTML files
├── /db/
│   └── exam-questions.sqlite           # SQLite database file
└── /logs/
    ├── plugin.log                      # General logs
    └── error.txt                       # Error logs with stack traces
```

---

## 🔧 Main Plugin File

**File:** `exam-questions-manager.php`

```php
<?php
/**
 * Plugin Name: Exam Questions Manager
 * Plugin URI: https://example.com/exam-questions-manager
 * Description: Manage hierarchical markdown-based exams with participant tracking.
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: exam-questions-manager
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('EQM_VERSION', '2.0.0');
define('EQM_PLUGIN_FILE', __FILE__);
define('EQM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EQM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EQM_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/exam-questions-manager/');
define('EQM_DB_PATH', EQM_UPLOADS_DIR . 'db/exam-questions.sqlite');

// Require Composer autoloader
if (file_exists(EQM_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once EQM_PLUGIN_DIR . 'vendor/autoload.php';
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'ExamQuestionsManager\\';
    $base_dir = EQM_PLUGIN_DIR . 'src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Plugin activation hook
 */
function eqm_activate() {
    // Create upload directories
    $directories = [
        EQM_UPLOADS_DIR,
        EQM_UPLOADS_DIR . 'questions/',
        EQM_UPLOADS_DIR . 'extensions/',
        EQM_UPLOADS_DIR . 'seeding/',
        EQM_UPLOADS_DIR . 'seeding/email-templates/',
        EQM_UPLOADS_DIR . 'db/',
        EQM_UPLOADS_DIR . 'logs/',
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Add .htaccess protection for sensitive directories
    $htaccess_content = "Order deny,allow\nDeny from all";
    $protected_dirs = [
        EQM_UPLOADS_DIR . 'db/',
        EQM_UPLOADS_DIR . 'logs/',
    ];
    
    foreach ($protected_dirs as $dir) {
        $htaccess_file = $dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    // Initialize database (will be implemented in 02)
    // \ExamQuestionsManager\Database\Schema::initialize();
    
    // Seed email templates (will be implemented in 31)
    // \ExamQuestionsManager\Database\Seeder::seedEmailTemplates();
    
    // Seed default admin role (will be implemented in 08)
    // \ExamQuestionsManager\Services\RoleService::seedDefaultAdmin();
    
    // Log activation
    // \ExamQuestionsManager\Utils\Logger::info('Plugin activated');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'eqm_activate');

/**
 * Plugin deactivation hook
 */
function eqm_deactivate() {
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('eqm_daily_cron');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'eqm_deactivate');

/**
 * Initialize plugin
 */
function eqm_init() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Exam Questions Manager requires PHP 8.0 or higher.</p></div>';
        });
        return;
    }
    
    // Initialize admin menu (will be implemented)
    // new \ExamQuestionsManager\Admin\AdminMenu();
    
    // Initialize REST API (will be implemented)
    // add_action('rest_api_init', [\ExamQuestionsManager\API\RestController::class, 'registerRoutes']);
    
    // Initialize cron jobs (will be implemented)
    // new \ExamQuestionsManager\Cron\CronManager();
}
add_action('plugins_loaded', 'eqm_init');

/**
 * Register admin menu
 */
function eqm_admin_menu() {
    add_menu_page(
        'Exam Questions Manager',      // Page title
        'Exam Manager',                 // Menu title
        'manage_options',               // Capability
        'exam-questions-manager',       // Menu slug
        'eqm_admin_page',               // Callback
        'dashicons-welcome-learn-more', // Icon
        30                              // Position
    );
    
    // Submenus
    add_submenu_page(
        'exam-questions-manager',
        'All Exams',
        'All Exams',
        'manage_options',
        'exam-questions-manager',
        'eqm_admin_page'
    );
    
    add_submenu_page(
        'exam-questions-manager',
        'Add New Exam',
        'Add New',
        'manage_options',
        'eqm-add-exam',
        'eqm_admin_page'
    );
    
    add_submenu_page(
        'exam-questions-manager',
        'Participants',
        'Participants',
        'manage_options',
        'eqm-participants',
        'eqm_admin_page'
    );
    
    add_submenu_page(
        'exam-questions-manager',
        'Wiki',
        'Wiki',
        'manage_options',
        'eqm-wiki',
        'eqm_admin_page'
    );
    
    add_submenu_page(
        'exam-questions-manager',
        'Extensions',
        'Extensions',
        'manage_options',
        'eqm-extensions',
        'eqm_admin_page'
    );
    
    add_submenu_page(
        'exam-questions-manager',
        'Email Templates',
        'Email Templates',
        'manage_options',
        'eqm-email-templates',
        'eqm_admin_page'
    );
    
    add_submenu_page(
        'exam-questions-manager',
        'Roles',
        'Roles',
        'manage_options',
        'eqm-roles',
        'eqm_admin_page'
    );
    
    add_submenu_page(
        'exam-questions-manager',
        'Settings',
        'Settings',
        'manage_options',
        'eqm-settings',
        'eqm_admin_page'
    );
}
add_action('admin_menu', 'eqm_admin_menu');

/**
 * Admin page callback - renders React app container
 */
function eqm_admin_page() {
    echo '<div id="eqm-admin-app"></div>';
}

/**
 * Enqueue admin scripts and styles
 */
function eqm_admin_assets($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'exam-questions-manager') === false && 
        strpos($hook, 'eqm-') === false) {
        return;
    }
    
    // Enqueue React app (built version)
    $admin_js = EQM_PLUGIN_DIR . 'admin/build/index.js';
    $admin_css = EQM_PLUGIN_DIR . 'admin/build/index.css';
    
    if (file_exists($admin_js)) {
        wp_enqueue_script(
            'eqm-admin-app',
            EQM_PLUGIN_URL . 'admin/build/index.js',
            ['wp-element'],
            EQM_VERSION,
            true
        );
        
        // Pass data to React app
        wp_localize_script('eqm-admin-app', 'eqmAdmin', [
            'apiUrl' => rest_url('eqm/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'currentPage' => isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '',
        ]);
    }
    
    if (file_exists($admin_css)) {
        wp_enqueue_style(
            'eqm-admin-styles',
            EQM_PLUGIN_URL . 'admin/build/index.css',
            [],
            EQM_VERSION
        );
    }
}
add_action('admin_enqueue_scripts', 'eqm_admin_assets');
```

---

## 🔧 Uninstall File

**File:** `uninstall.php`

```php
<?php
/**
 * Uninstall script - runs when plugin is deleted
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only delete data if user chose to (can be controlled via option)
$delete_data = get_option('eqm_delete_data_on_uninstall', false);

if ($delete_data) {
    // Delete database file
    $db_path = wp_upload_dir()['basedir'] . '/exam-questions-manager/db/exam-questions.sqlite';
    if (file_exists($db_path)) {
        unlink($db_path);
    }
    
    // Delete upload directory (optional - user may want to keep files)
    // Uncomment if you want to delete all uploaded files
    // $upload_dir = wp_upload_dir()['basedir'] . '/exam-questions-manager/';
    // if (is_dir($upload_dir)) {
    //     recursive_rmdir($upload_dir);
    // }
    
    // Delete options
    delete_option('eqm_settings');
    delete_option('eqm_delete_data_on_uninstall');
}

// Clear cron jobs
wp_clear_scheduled_hook('eqm_daily_cron');
```

---

## 🔧 Composer Configuration

**File:** `composer.json`

```json
{
    "name": "your-vendor/exam-questions-manager",
    "description": "WordPress plugin for managing markdown-based exams",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.0",
        "erusev/parsedown": "^1.7"
    },
    "autoload": {
        "psr-4": {
            "ExamQuestionsManager\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true
    }
}
```

---

## ✅ Acceptance Criteria

### Plugin Setup
- [ ] Plugin file created with proper header comments
- [ ] Constants defined: `EQM_VERSION`, `EQM_PLUGIN_FILE`, `EQM_PLUGIN_DIR`, `EQM_PLUGIN_URL`, `EQM_UPLOADS_DIR`, `EQM_DB_PATH`
- [ ] PSR-4 autoloader registered and working
- [ ] Composer autoloader included if vendor folder exists

### Directory Creation
- [ ] All upload directories created on activation
- [ ] `.htaccess` protection added to `/db/` and `/logs/` directories
- [ ] Directories have proper permissions (755)

### WordPress Integration
- [ ] Plugin appears in WordPress plugins list
- [ ] Plugin can be activated without errors
- [ ] Plugin can be deactivated without errors
- [ ] Admin menu appears with all submenus after activation
- [ ] Admin pages render React container div

### PHP Version Check
- [ ] Error notice shown if PHP < 8.0
- [ ] Plugin gracefully fails on unsupported PHP versions

### Cleanup
- [ ] Cron jobs cleared on deactivation
- [ ] Uninstall.php deletes data only when option is set

---

## 📝 Notes

- The admin React app and public frontend will be built separately
- Database initialization (Schema::initialize) will be implemented in `02-database-schema.md`
- Logger will be implemented in `05-logging-system.md`
- Role seeding will be implemented in `08-rbac-system.md`

---

*Next: `02-database-schema.md`*
