# WordPress Plugin File Structure Standards

## Standard Directory Layout

```
my-plugin/
├── my-plugin.php              # Main entry point (same name as folder)
├── README.md                  # Documentation
├── CHANGELOG.md               # Version history
├── LICENSE                    # License file
│
├── includes/                  # Core PHP classes
│   ├── constants.php          # ALL constants - loaded FIRST
│   ├── class-file-logger.php  # Low-level file logging
│   ├── class-database.php     # Database management
│   ├── class-logger.php       # Application logging
│   ├── class-orm.php          # Database ORM
│   └── class-*.php            # Other classes
│
├── admin/                     # Admin-only code
│   ├── class-admin-ui.php     # Admin interface
│   └── views/                 # Admin templates
│       ├── dashboard.php
│       ├── settings.php
│       └── *.php
│
├── api/                       # REST API handlers (optional)
│   ├── class-api.php          # API class
│   └── class-permissions.php  # Permission handlers
│
├── assets/                    # Static assets
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
│
├── data/                      # Runtime data (git-ignored)
│   └── .gitkeep
│
└── languages/                 # Translations (optional)
    └── my-plugin.pot
```

## File Naming Conventions

### Class Files
- Prefix: `class-`
- Name: lowercase with hyphens
- Suffix: `.php`

```
class-file-logger.php    → class Riseup_File_Logger
class-database.php       → class Riseup_Database
class-post-manager.php   → class Riseup_Post_Manager
```

### Class Naming
- Prefix with plugin name
- Use underscores
- PascalCase after prefix

```php
class Riseup_File_Logger { }
class Riseup_Database { }
class Riseup_Post_Manager { }
```

### Main Plugin File

The main plugin file MUST:
1. Have the same name as the plugin folder
2. Contain the plugin header comment
3. Include all dependencies in correct order
4. Initialize the main plugin class

```php
<?php
/**
 * Plugin Name: Riseup Asia Uploader
 * Plugin URI: https://example.com/
 * Description: Remote plugin management and file sync
 * Version: 1.4.0
 * Author: MD ALIM UL KARIM
 * Author URI: https://rasia.pro/alim-r-profile-v1
 * Text Domain: riseup-asia-uploader
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin paths (safe at parse time)
define('RISEUP_PLUGIN_FILE', __FILE__);
define('RISEUP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Include files in dependency order
require_once RISEUP_PLUGIN_DIR . 'includes/constants.php';      // 1. Constants first
require_once RISEUP_PLUGIN_DIR . 'includes/class-file-logger.php'; // 2. No dependencies
require_once RISEUP_PLUGIN_DIR . 'includes/class-database.php';    // 3. Uses file-logger
require_once RISEUP_PLUGIN_DIR . 'includes/class-logger.php';      // 4. Uses database
require_once RISEUP_PLUGIN_DIR . 'includes/class-orm.php';         // 5. Uses database
// ... additional includes

// Main plugin class
class Riseup_Asia_Uploader {
    // ... implementation
}

// Start plugin
$riseup_uploader = new Riseup_Asia_Uploader();
```

## Include Order (Critical!)

Files MUST be included in dependency order:

```
1. constants.php           - No dependencies, defines all constants
2. class-file-logger.php   - Uses only PHP, no WP dependencies
3. class-database.php      - Uses file-logger
4. class-logger.php        - Uses database (lazy)
5. class-orm.php           - Uses database
6. Other classes           - May use any of the above
```

## Data Directory

The `data/` directory stores runtime files during development:

```
data/
├── .gitkeep               # Ensures folder is in git
└── (runtime files)        # Ignored by git
```

**.gitignore entry:**
```
/data/*
!/data/.gitkeep
```

**Note:** Production data should be stored in `wp-content/uploads/plugin-slug/`, not in the plugin directory.

## Assets Directory

```
assets/
├── css/
│   ├── admin.css          # Admin styles
│   └── public.css         # Frontend styles (if any)
└── js/
    ├── admin.js           # Admin scripts
    └── public.js          # Frontend scripts (if any)
```

### Enqueue Assets Properly

```php
public function enqueue_admin_assets($hook) {
    // Only load on our admin pages
    if (strpos($hook, 'riseup') === false) {
        return;
    }
    
    wp_enqueue_style(
        'riseup-admin',
        plugin_dir_url(RISEUP_PLUGIN_FILE) . 'assets/css/admin.css',
        [],
        RISEUP_VERSION
    );
    
    wp_enqueue_script(
        'riseup-admin',
        plugin_dir_url(RISEUP_PLUGIN_FILE) . 'assets/js/admin.js',
        ['jquery'],
        RISEUP_VERSION,
        true  // Load in footer
    );
}
```

## Admin Views

Keep HTML templates separate from PHP logic:

```php
// admin/class-admin-ui.php
class Riseup_Admin_UI {
    public function render_dashboard() {
        $data = $this->get_dashboard_data();
        include RISEUP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
}

// admin/views/dashboard.php
<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1><?php echo esc_html(RISEUP_PLUGIN_NAME); ?></h1>
    <p>Version: <?php echo esc_html(RISEUP_VERSION); ?></p>
    <!-- Dashboard content -->
</div>
```

## File Header Comments

Every PHP file should have a header:

```php
<?php
/**
 * File Logger
 * 
 * Handles low-level file logging without WordPress dependencies.
 * 
 * @package    Riseup_Asia_Uploader
 * @subpackage Includes
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Riseup_File_Logger {
    // ...
}
```

## Minimum Required Files

For a minimal working plugin:

```
my-plugin/
├── my-plugin.php           # Entry point + main class
├── includes/
│   ├── constants.php       # All constants
│   └── class-file-logger.php  # Logging
└── README.md               # Documentation
```

## Scaling Up

As the plugin grows, split classes by responsibility:

```
includes/
├── constants.php
├── class-file-logger.php
├── class-database.php
├── class-logger.php
├── class-orm.php
│
├── managers/               # Business logic
│   ├── class-plugin-manager.php
│   ├── class-post-manager.php
│   └── class-media-manager.php
│
├── handlers/               # Request handlers
│   ├── class-upload-handler.php
│   └── class-sync-handler.php
│
└── helpers/                # Utility functions
    ├── class-sanitizer.php
    └── class-validator.php
```
