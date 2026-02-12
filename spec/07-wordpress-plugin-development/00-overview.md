# WordPress Plugin Development Specification

> Comprehensive guidelines for building robust, production-ready WordPress plugins.

## Document Structure

| File | Description |
|------|-------------|
| [01-initialization-patterns.md](./01-initialization-patterns.md) | Safe loading order, dependency management, and bootstrap patterns |
| [02-logging-standards.md](./02-logging-standards.md) | Logging infrastructure with file paths, line numbers, and error handling |
| [03-database-patterns.md](./03-database-patterns.md) | SQLite/MySQL patterns, migrations, and schema versioning |
| [04-api-design.md](./04-api-design.md) | REST API design, endpoint registration, and authentication |
| [05-constants-and-configuration.md](./05-constants-and-configuration.md) | Centralized constants and configuration management |
| [06-file-structure.md](./06-file-structure.md) | Directory layout and file organization standards |
| [07-error-handling.md](./07-error-handling.md) | Try-catch patterns, error logging, and graceful degradation |
| [08-compatibility.md](./08-compatibility.md) | PHP version compatibility and WordPress version requirements |
| [09-security.md](./09-security.md) | Authentication, sanitization, and security best practices |
| [10-testing.md](./10-testing.md) | Manual and automated testing strategies |

## Critical Lessons Learned

### 1. Never Call WordPress Functions During Early Plugin Load

**Problem**: Calling functions like `wp_upload_dir()`, `get_option()`, or database functions in class constructors or during file include causes fatal errors because WordPress core may not be fully initialized.

**Solution**: Use lazy initialization patterns - resolve paths and dependencies only when first needed, not at class instantiation.

```php
// ❌ WRONG - Causes crash during plugin load
class My_Logger {
    private $log_path;
    
    public function __construct() {
        $upload_dir = wp_upload_dir(); // FATAL: WordPress not ready!
        $this->log_path = $upload_dir['basedir'] . '/my-plugin/logs/';
    }
}

// ✅ CORRECT - Lazy initialization
class My_Logger {
    private $log_path = null;
    
    private function get_log_path() {
        if ($this->log_path === null) {
            $upload_dir = wp_upload_dir();
            $this->log_path = $upload_dir['basedir'] . '/my-plugin/logs/';
        }
        return $this->log_path;
    }
    
    public function log($message) {
        $path = $this->get_log_path(); // Safe: called during runtime
        // ... write log
    }
}
```

### 2. Avoid Circular Dependencies

**Problem**: Class A requires Class B in constructor, and Class B requires Class A, causing infinite loop or undefined behavior.

**Solution**: Use lazy loading via getter methods instead of constructor injection.

```php
// ❌ WRONG - Circular dependency
class Logger {
    private $db;
    public function __construct(Database $db) {
        $this->db = $db; // Database might need Logger!
    }
}

// ✅ CORRECT - Lazy loading
class Logger {
    private $db = null;
    
    private function get_db() {
        if ($this->db === null) {
            $this->db = Riseup_Database::get_instance();
        }
        return $this->db;
    }
}
```

### 3. Always Use Centralized Constants

**Problem**: Hardcoded strings scattered throughout code lead to typos, inconsistencies, and maintenance nightmares.

**Solution**: Define ALL strings (endpoints, table names, option keys) as constants in a single file.

```php
// includes/constants.php
define('MYPLUGIN_API_NAMESPACE', 'myplugin/v1');
define('MYPLUGIN_TABLE_TRANSACTIONS', 'myplugin_transactions');
define('MYPLUGIN_ENDPOINT_UPLOAD', 'upload');

// Usage throughout plugin
$namespace = MYPLUGIN_API_NAMESPACE; // Never 'myplugin/v1' directly
```

### 4. Eager Database Migrations with Schema Versioning

**Problem**: Database tables don't exist when needed, or migrations run multiple times.

**Solution**: Run migrations immediately on plugin load with version tracking.

```php
public function init() {
    $this->file_logger->log('Database init starting', __FILE__, __LINE__);
    $this->ensure_data_directory();
    $this->create_tables();
}

private function create_tables() {
    $current_version = $this->get_schema_version();
    
    if ($current_version < 1) {
        // Migration v1
        $this->pdo->exec($create_table_sql);
        $this->set_schema_version(1);
        $this->file_logger->log('Migration v1 complete', __FILE__, __LINE__);
    }
}
```

### 5. Comprehensive Logging with Context

**Problem**: Errors occur but there's no way to trace what happened or where.

**Solution**: Log every significant operation with file path, line number, and context.

```php
$this->logger->log(
    sprintf('Creating table %s', MYPLUGIN_TABLE_NAME),
    __FILE__,
    __LINE__
);
```

## Quick Reference: Plugin Load Order

```
1. WordPress core loads
2. Plugin file included (constants.php loaded here)
3. Main plugin class instantiated
4. 'plugins_loaded' hook fires
5. 'init' hook fires (safe to use most WP functions)
6. 'rest_api_init' hook fires (register REST routes here)
```

## File Structure Template

```
my-plugin/
├── my-plugin.php              # Main entry point
├── includes/
│   ├── constants.php          # ALL constants defined here
│   ├── class-file-logger.php  # Low-level file logging
│   ├── class-database.php     # Database management
│   ├── class-logger.php       # Application logging (uses DB)
│   └── class-*.php            # Other classes
├── data/
│   └── .gitkeep               # Runtime data (ignored in git)
└── README.md
```

## Author

**MD ALIM UL KARIM**  
https://rasia.pro/alim-r-profile-v1
