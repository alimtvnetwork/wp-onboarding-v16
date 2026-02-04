# WordPress Plugin Initialization Patterns

## The Bootstrap Problem

WordPress plugins are loaded during PHP's compilation phase, before WordPress core is fully initialized. This means:

1. WordPress functions may not be available
2. Global objects may not exist
3. Hooks may not be registered
4. Database may not be connected

## Safe Initialization Sequence

### Phase 1: File Include (Immediate Execution)

When WordPress includes your main plugin file, this code runs immediately:

```php
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 */

// Only define constants and include files - NO function calls
define('MYPLUGIN_VERSION', '1.0.0');
define('MYPLUGIN_FILE', __FILE__);
define('MYPLUGIN_DIR', plugin_dir_path(__FILE__));

// Include constants first - they define ALL magic strings
require_once MYPLUGIN_DIR . 'includes/constants.php';

// Include class files - they just define classes, don't instantiate
require_once MYPLUGIN_DIR . 'includes/class-file-logger.php';
require_once MYPLUGIN_DIR . 'includes/class-database.php';
```

### Phase 2: Class Instantiation (Still Early)

Create instances, but constructors must NOT call WordPress functions:

```php
// ✅ SAFE - Constructor only sets defaults
class My_File_Logger {
    private $log_path = null;  // Will be set lazily
    private $initialized = false;
    
    public function __construct() {
        // Only set non-WP-dependent defaults
        $this->initialized = false;
    }
}
```

### Phase 3: Hook Registration (Safe Zone Begins)

Register hooks to defer work until WordPress is ready:

```php
// After class includes
$my_plugin = new My_Plugin();

// Register for later execution
add_action('plugins_loaded', [$my_plugin, 'on_plugins_loaded']);
add_action('init', [$my_plugin, 'on_init']);
add_action('rest_api_init', [$my_plugin, 'register_routes']);
```

### Phase 4: plugins_loaded Hook

First safe point for most WordPress function calls:

```php
public function on_plugins_loaded() {
    // NOW safe to call WordPress functions
    $this->init_database();
    $this->load_textdomain();
}
```

### Phase 5: init Hook

All WordPress core is ready:

```php
public function on_init() {
    // Register custom post types, taxonomies, etc.
    $this->register_post_types();
}
```

### Phase 6: rest_api_init Hook

REST API is ready:

```php
public function register_routes() {
    register_rest_route(
        MYPLUGIN_API_NAMESPACE,
        '/' . MYPLUGIN_ENDPOINT_HEALTH,
        [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true',
        ]
    );
}
```

## Lazy Initialization Pattern

The key pattern for avoiding early WordPress function calls:

```php
class My_Component {
    private $upload_dir = null;
    private $data_path = null;
    
    /**
     * Get WordPress upload directory - lazy loaded
     */
    private function get_upload_dir() {
        if ($this->upload_dir === null) {
            $this->upload_dir = wp_upload_dir();
        }
        return $this->upload_dir;
    }
    
    /**
     * Get data directory path - depends on upload dir
     */
    public function get_data_path() {
        if ($this->data_path === null) {
            $upload = $this->get_upload_dir();
            $this->data_path = $upload['basedir'] . '/' . MYPLUGIN_SLUG . '/data/';
        }
        return $this->data_path;
    }
    
    /**
     * Ensure directory exists - called when actually needed
     */
    private function ensure_directory($path) {
        if (!file_exists($path)) {
            // Use native PHP, not wp_mkdir_p during uncertain phase
            if (!@mkdir($path, 0755, true) && !is_dir($path)) {
                throw new Exception("Failed to create directory: {$path}");
            }
        }
        return $path;
    }
}
```

## Dependency Resolution Without Circular References

### Problem: Class A needs Class B, Class B needs Class A

```php
// ❌ CAUSES INFINITE LOOP
class Database {
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }
}

class Logger {
    public function __construct(Database $db) {
        $this->db = $db;  // Needs Database for DB logging
    }
}

// How to create either one?
$db = new Database(new Logger(new Database(...))); // Infinite!
```

### Solution: Lazy Dependency Loading

```php
// ✅ CORRECT - Lazy loading breaks the cycle
class Logger {
    private $db = null;
    
    public function __construct() {
        // Don't require Database here
    }
    
    private function get_db() {
        if ($this->db === null) {
            // Get it when actually needed
            $this->db = Riseup_Database::get_instance();
        }
        return $this->db;
    }
    
    public function log_to_db($message) {
        $db = $this->get_db();  // Now safe to get
        $db->insert_log($message);
    }
}
```

## Two-Tier Logging Strategy

For plugins that need to log during initialization (before Database is ready):

### Tier 1: File Logger (Always Available)

```php
class File_Logger {
    // Uses only native PHP - no WordPress dependencies
    private $log_path = null;
    
    private function ensure_paths() {
        if ($this->log_path === null) {
            // Lazy but still uses wp_upload_dir
            $upload = wp_upload_dir();
            $base = $upload['basedir'] . '/' . MYPLUGIN_SLUG . '/logs/';
            
            // Create with native PHP
            if (!is_dir($base)) {
                @mkdir($base, 0755, true);
            }
            
            $this->log_path = $base . 'log.txt';
        }
        return $this->log_path;
    }
    
    public function log($message, $file = '', $line = 0) {
        $path = $this->ensure_paths();
        $timestamp = gmdate('Y-m-d\TH:i:s.') . sprintf('%03d', (microtime(true) * 1000) % 1000) . 'Z';
        $context = $file ? basename($file) . ':' . $line : '';
        $entry = "[{$timestamp}] {$message}" . ($context ? " ({$context})" : "") . "\n";
        @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }
}
```

### Tier 2: Database Logger (Available After Init)

```php
class DB_Logger {
    private $file_logger;
    private $db = null;
    
    public function __construct(File_Logger $file_logger) {
        $this->file_logger = $file_logger;
    }
    
    public function log($message, $level = 'INFO') {
        // Always write to file first (reliable)
        $this->file_logger->log("[{$level}] {$message}", __FILE__, __LINE__);
        
        // Try database if available
        try {
            $db = $this->get_db();
            if ($db && $db->is_ready()) {
                $db->insert_log($message, $level);
            }
        } catch (Exception $e) {
            $this->file_logger->log("DB log failed: " . $e->getMessage(), __FILE__, __LINE__);
        }
    }
}
```

## Complete Bootstrap Template

```php
<?php
/**
 * Plugin Name: My Plugin
 * Plugin URI: https://example.com
 * Description: Description here
 * Version: 1.0.0
 * Author: Author Name
 * Author URI: https://author.com
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Phase 1: Define constants (no function calls)
define('MYPLUGIN_VERSION', '1.0.0');
define('MYPLUGIN_FILE', __FILE__);
define('MYPLUGIN_DIR', plugin_dir_path(__FILE__));

// Include all class definitions
require_once MYPLUGIN_DIR . 'includes/constants.php';
require_once MYPLUGIN_DIR . 'includes/class-file-logger.php';
require_once MYPLUGIN_DIR . 'includes/class-database.php';
require_once MYPLUGIN_DIR . 'includes/class-logger.php';

// Phase 2: Main plugin class with safe constructor
class My_Plugin {
    private static $instance = null;
    private $file_logger;
    private $db;
    private $logger;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Only create objects - no WP function calls
        $this->file_logger = new File_Logger();
        // DB and Logger are created later
    }
    
    public function init() {
        // Phase 3: Register hooks
        add_action('plugins_loaded', [$this, 'on_plugins_loaded'], 5);
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function on_plugins_loaded() {
        // Phase 4: NOW safe for WordPress functions
        $this->file_logger->log('Plugin loading', __FILE__, __LINE__);
        
        try {
            $this->db = My_Database::get_instance();
            $this->db->init();  // Run migrations
            
            $this->logger = new My_Logger($this->file_logger);
            
            $this->file_logger->log('Plugin loaded successfully', __FILE__, __LINE__);
        } catch (Exception $e) {
            $this->file_logger->error('Plugin init failed: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }
    
    public function register_routes() {
        // Phase 6: Register REST endpoints
        $this->file_logger->log('Registering REST routes', __FILE__, __LINE__);
        
        register_rest_route(MYPLUGIN_API_NAMESPACE, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true',
        ]);
    }
}

// Start the plugin
$plugin = My_Plugin::get_instance();
$plugin->init();
```

## Common Pitfalls

### 1. Using `plugin_dir_url()` in Constants

```php
// ❌ WRONG - Function call at parse time
define('MYPLUGIN_URL', plugin_dir_url(__FILE__));

// ✅ CORRECT - Function call is safe for paths
define('MYPLUGIN_DIR', plugin_dir_path(__FILE__));

// ✅ CORRECT - URL determined when needed
function myplugin_get_url() {
    static $url = null;
    if ($url === null) {
        $url = plugin_dir_url(MYPLUGIN_FILE);
    }
    return $url;
}
```

### 2. Database Connection in Constructor

```php
// ❌ WRONG
public function __construct() {
    $this->pdo = new PDO('sqlite:' . $this->get_db_path()); // Calls WP function!
}

// ✅ CORRECT
public function __construct() {
    $this->pdo = null;
}

private function get_pdo() {
    if ($this->pdo === null) {
        $this->pdo = new PDO('sqlite:' . $this->get_db_path());
    }
    return $this->pdo;
}
```

### 3. Calling `is_admin()` Too Early

```php
// ❌ WRONG - is_admin() may not work during plugin load
if (is_admin()) {
    require_once 'admin/class-admin.php';
}

// ✅ CORRECT - Defer to appropriate hook
add_action('admin_init', function() {
    require_once MYPLUGIN_DIR . 'admin/class-admin.php';
});
```
