# WordPress Plugin Database Patterns

## Overview

WordPress plugins can use:
1. **WordPress Database ($wpdb)** - MySQL via WordPress abstraction
2. **SQLite** - File-based database for plugin-specific data
3. **Custom Tables in WordPress DB** - Using `$wpdb` with custom tables

This guide focuses on SQLite for self-contained plugins, which is ideal for:
- Audit logging
- Plugin-specific transaction records
- Offline/local functionality
- Avoiding WordPress DB pollution

## SQLite Database Location

Store the database in the WordPress uploads directory:

```
wp-content/uploads/{plugin-slug}/{plugin-slug}.db
```

### Why This Location?

1. **Writable** - Guaranteed write permissions
2. **Survives updates** - Outside plugin directory
3. **Backupable** - Standard WordPress backup tools include uploads
4. **Securable** - Can add .htaccess protection

## Database Class Implementation

```php
<?php
/**
 * Database Manager with SQLite
 * 
 * Handles connection, migrations, and table management.
 */
class Riseup_Database {
    /** @var Riseup_Database Singleton instance */
    private static $instance = null;
    
    /** @var PDO|null Database connection */
    private $pdo = null;
    
    /** @var Riseup_File_Logger Logger instance */
    private $file_logger;
    
    /** @var string|null Database file path */
    private $db_path = null;
    
    /** @var bool Whether database is initialized */
    private $initialized = false;
    
    /** @var int Current schema version */
    const SCHEMA_VERSION = 1;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    
    /**
     * Constructor - minimal work, no WordPress dependencies
     */
    private function __construct() {
        $this->file_logger = new Riseup_File_Logger();
    }
    
    /**
     * Initialize database - call during plugins_loaded
     */
    public function init() {
        if ($this->initialized) {
            return;
        }
        
        $this->file_logger->log('Database init starting', __FILE__, __LINE__);
        
        try {
            $this->ensure_data_directory();
            $this->connect();
            $this->create_tables();
            $this->initialized = true;
            
            $this->file_logger->log('Database init complete', __FILE__, __LINE__);
        } catch (\Throwable $e) {
            $this->file_logger->error(
                'Database init failed: ' . $e->getMessage(),
                __FILE__,
                __LINE__
            );
            throw $e;
        }
    }
    
    /**
     * Get database file path (lazy)
     */
    private function get_db_path() {
        if ($this->db_path === null) {
            $upload_dir = wp_upload_dir();
            $this->db_path = $upload_dir['basedir'] . '/' . RISEUP_PLUGIN_SLUG . '/' . RISEUP_DB_FILENAME;
        }

        return $this->db_path;
    }
    
    /**
     * Ensure data directory exists with security
     */
    private function ensure_data_directory() {
        $db_path = $this->get_db_path();
        $data_dir = dirname($db_path);
        
        $this->file_logger->log(
            sprintf('Ensuring data directory: %s', $data_dir),
            __FILE__,
            __LINE__
        );
        
        if (!is_dir($data_dir) && !@mkdir($data_dir, 0755, true) && !is_dir($data_dir)) {
            throw new Exception("Failed to create data directory: {$data_dir}");
        }
        
        // Security: prevent direct access
        $htaccess = $data_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        
        $index = $data_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden\n");
        }
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        $db_path = $this->get_db_path();
        
        $this->file_logger->log(
            sprintf('Connecting to database: %s', $db_path),
            __FILE__,
            __LINE__
        );
        
        try {
            $this->pdo = new PDO('sqlite:' . $db_path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign keys
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            $this->file_logger->log('Database connection established', __FILE__, __LINE__);
        } catch (PDOException $e) {
            $this->file_logger->error(
                sprintf('Database connection failed: %s', $e->getMessage()),
                __FILE__,
                __LINE__
            );
            throw $e;
        }
    }
    
    /**
     * Get PDO instance
     */
    public function get_pdo() {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }
    
    /**
     * Check if database is ready
     */
    public function is_ready() {
        return $this->initialized && $this->pdo !== null;
    }
    
    /**
     * Get current schema version from database
     */
    private function get_schema_version() {
        try {
            // Check if schema_version table exists
            $stmt = $this->pdo->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='schema_version'"
            );
            
            if (!$stmt->fetch()) {
                // Table doesn't exist - create it
                $this->pdo->exec("
                    CREATE TABLE schema_version (
                        version INTEGER PRIMARY KEY,
                        applied_at TEXT NOT NULL
                    )
                ");
                $this->file_logger->log('Created schema_version table', __FILE__, __LINE__);

                return 0;
            }

            // Get current version
            $stmt = $this->pdo->query("SELECT MAX(version) as version FROM schema_version");
            $row = $stmt->fetch();

            return $row['version'] ?? 0;
        } catch (PDOException $e) {
            $this->file_logger->error(
                sprintf('Failed to get schema version: %s', $e->getMessage()),
                __FILE__,
                __LINE__
            );

            return 0;
        }
    }
    
    /**
     * Set schema version after migration
     */
    private function set_schema_version($version) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO schema_version (version, applied_at) VALUES (?, ?)"
        );
        $stmt->execute([$version, gmdate('Y-m-d H:i:s')]);
        
        $this->file_logger->log(
            sprintf('Schema version set to %d', $version),
            __FILE__,
            __LINE__
        );
    }
    
    /**
     * Create/update tables with migrations
     */
    private function create_tables() {
        $current_version = $this->get_schema_version();
        
        $this->file_logger->log(
            sprintf('Current schema version: %d, target: %d', $current_version, self::SCHEMA_VERSION),
            __FILE__,
            __LINE__
        );
        
        // Migration v1: Core tables
        if ($current_version < 1) {
            $this->file_logger->log('Running migration v1', __FILE__, __LINE__);
            
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS " . RISEUP_TABLE_TRANSACTIONS . " (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        type TEXT NOT NULL,
                        status TEXT NOT NULL DEFAULT 'pending',
                        details TEXT,
                        created_at TEXT NOT NULL,
                        updated_at TEXT NOT NULL
                    )
                ");
                
                $this->file_logger->log(
                    sprintf('Created table: %s', RISEUP_TABLE_TRANSACTIONS),
                    __FILE__,
                    __LINE__
                );
                
                // Create indexes
                $this->pdo->exec("
                    CREATE INDEX IF NOT EXISTS idx_transactions_type 
                    ON " . RISEUP_TABLE_TRANSACTIONS . "(type)
                ");
                
                $this->pdo->exec("
                    CREATE INDEX IF NOT EXISTS idx_transactions_created 
                    ON " . RISEUP_TABLE_TRANSACTIONS . "(created_at)
                ");
                
                $this->file_logger->log('Created indexes for transactions table', __FILE__, __LINE__);
                
                $this->set_schema_version(1);
                $this->file_logger->log('Migration v1 complete', __FILE__, __LINE__);
                
            } catch (PDOException $e) {
                $this->file_logger->error(
                    sprintf('Migration v1 failed: %s', $e->getMessage()),
                    __FILE__,
                    __LINE__
                );
                throw $e;
            }
        }
        
        // Future migrations go here
        // if ($current_version < 2) { ... }
    }
}
```

## Schema Versioning

### Why Version Your Schema?

1. **Idempotent migrations** - Safe to run multiple times
2. **Rollback capability** - Track what was applied
3. **Multi-environment** - Different sites may be at different versions
4. **Team development** - Multiple developers adding migrations

### Migration Pattern

```php
// Each migration is a version number
// Check if current < target, then apply

if ($current_version < 1) {
    // Initial tables
    $this->run_migration_v1();
    $this->set_schema_version(1);
}

if ($current_version < 2) {
    // Add new column
    $this->run_migration_v2();
    $this->set_schema_version(2);
}

if ($current_version < 3) {
    // Create new table
    $this->run_migration_v3();
    $this->set_schema_version(3);
}
```

## ORM Pattern (Micro-ORM)

For simple CRUD operations without a full ORM:

```php
<?php
class Riseup_ORM {
    private $db;
    private $file_logger;
    private $table;
    
    public function __construct($table_name) {
        $this->db = Riseup_Database::get_instance();
        $this->file_logger = new Riseup_File_Logger();
        $this->table = $table_name;
    }
    
    /**
     * Insert a new record
     */
    public function insert($data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $this->file_logger->log(
            sprintf('ORM insert into %s', $this->table),
            __FILE__,
            __LINE__
        );
        
        try {
            $pdo = $this->db->get_pdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->file_logger->error(
                sprintf('ORM insert failed: %s', $e->getMessage()),
                __FILE__,
                __LINE__
            );
            throw $e;
        }
    }
    
    /**
     * Find record by ID
     */
    public function find($id) {
        $sql = sprintf("SELECT * FROM %s WHERE id = ?", $this->table);
        
        try {
            $pdo = $this->db->get_pdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->file_logger->error(
                sprintf('ORM find failed: %s', $e->getMessage()),
                __FILE__,
                __LINE__
            );
            return null;
        }
    }
    
    /**
     * Find all records with optional conditions
     */
    public function find_all($conditions = [], $order_by = null, $limit = null) {
        $sql = sprintf("SELECT * FROM %s", $this->table);
        $params = [];
        
        if (!empty($conditions)) {
            $where_parts = [];
            foreach ($conditions as $column => $value) {
                $where_parts[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where_parts);
        }
        
        if ($order_by) {
            $sql .= " ORDER BY " . $order_by;
        }
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        try {
            $pdo = $this->db->get_pdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->file_logger->error(
                sprintf('ORM find_all failed: %s', $e->getMessage()),
                __FILE__,
                __LINE__
            );
            return [];
        }
    }
    
    /**
     * Update record by ID
     */
    public function update($id, $data) {
        $set_parts = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $set_parts[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $id;
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE id = ?",
            $this->table,
            implode(', ', $set_parts)
        );
        
        try {
            $pdo = $this->db->get_pdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->file_logger->error(
                sprintf('ORM update failed: %s', $e->getMessage()),
                __FILE__,
                __LINE__
            );
            throw $e;
        }
    }
    
    /**
     * Delete record by ID
     */
    public function delete($id) {
        $sql = sprintf("DELETE FROM %s WHERE id = ?", $this->table);
        
        try {
            $pdo = $this->db->get_pdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->file_logger->error(
                sprintf('ORM delete failed: %s', $e->getMessage()),
                __FILE__,
                __LINE__
            );
            throw $e;
        }
    }
}
```

## Best Practices

### 1. Always Use Transactions for Multiple Operations

```php
public function batch_insert($records) {
    $pdo = $this->db->get_pdo();
    
    try {
        $pdo->beginTransaction();
        
        foreach ($records as $record) {
            $this->insert($record);
        }
        
        $pdo->commit();
        $this->file_logger->log('Batch insert committed', __FILE__, __LINE__);
        
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $this->file_logger->error('Batch insert rolled back', __FILE__, __LINE__);
        throw $e;
    }
}
```

### 2. Use Prepared Statements Always

```php
// ❌ NEVER DO THIS - SQL injection vulnerability
$sql = "SELECT * FROM users WHERE name = '{$user_input}'";

// ✅ ALWAYS use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE name = ?");
$stmt->execute([$user_input]);
```

### 3. Handle DateTime Correctly

```php
// Store as ISO 8601 UTC
$created_at = gmdate('Y-m-d H:i:s');

// When displaying, convert to local timezone
$local_time = get_date_from_gmt($created_at, 'Y-m-d H:i:s');
```

### 4. Create Indexes for Queried Columns

```php
// Index columns used in WHERE, ORDER BY, or JOIN
$this->pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_transactions_type 
    ON transactions(type)
");
```
