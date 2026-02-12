# Path Handling Standards

**Version:** 1.0.0  
**Created:** 2026-02-06  
**Applies To:** PHP (WordPress Plugin), Go (Backend)

---

## 1. Core Principles

### 1.1 Constants-Based Path Origins

All base paths MUST originate from constants. Never hardcode directory names inline.

**PHP Constants** (in `includes/constants.php`):
- `RISEUP_UPLOADS_SUBDIR` - Plugin's uploads subfolder
- `RISEUP_LOGS_SUBDIR` - Logs subfolder
- `RISEUP_TEMP_SUBDIR` - Temporary files
- `RISEUP_SNAPSHOTS_SUBDIR` - Database snapshots

**Go Constants** (in `internal/wordpress/constants.go` or `internal/pathutil/`):
- Data directories, temp paths, etc.

### 1.2 Centralized Path Joining

Never use raw `path.join()` or string concatenation for paths. Always use a centralized path utility method that:
1. Joins path segments safely
2. Validates the result
3. Optionally creates missing directories
4. Logs any errors

### 1.3 Validate Before Use

Before any file operation (read, write, list), validate that:
1. The directory exists (or create it)
2. The directory is writable (for write operations)
3. Log any failures with full context

---

## 2. PHP Implementation

### 2.1 Path Utility Class

Located at: `wp-plugins/riseup-asia-uploader/includes/class-path-utils.php`

```php
class Riseup_Path_Utils {
    
    /**
     * Join path segments safely.
     * 
     * @param string ...$segments Path segments to join.
     * @return string Joined path with forward slashes.
     */
    public static function join(...$segments): string;
    
    /**
     * Ensure a directory exists, creating it if necessary.
     * 
     * @param string $path      Directory path.
     * @param bool   $secure    Add .htaccess and index.php for security.
     * @return bool True if directory exists/was created.
     */
    public static function ensure_dir($path, $secure = false): bool;
    
    /**
     * Join and ensure directory exists in one call.
     * 
     * @param bool   $secure    Add security files.
     * @param string ...$segments Path segments.
     * @return string|false Path if successful, false on failure.
     */
    public static function ensure_path($secure, ...$segments);
    
    /**
     * Get the plugin's base uploads directory.
     * 
     * @return string Base path (wp-content/uploads/riseup-asia-uploader).
     */
    public static function get_base_dir(): string;
    
    /**
     * Validate a path is within allowed boundaries.
     * 
     * @param string $path       Path to validate.
     * @param string $base_path  Allowed base path.
     * @return bool True if path is safe.
     */
    public static function is_safe_path($path, $base_path): bool;
}
```

### 2.2 Usage Pattern

```php
// CORRECT: Guard with isDirMissing — single semantic call
$snapshots_dir = RiseupPathUtils::getSnapshotsDir();

if (RiseupPathUtils::isDirMissing($snapshots_dir, true)) {
    $this->logger->error('Failed to create snapshots directory');

    return false;
}

$snapshot_file = RiseupPathUtils::join($snapshots_dir, $filename);

// INCORRECT: Verbose two-helper composition
if (RiseupBooleanHelpers::is_falsy(RiseupInitHelpers::ensureDir($dir, true))) { ... }

// INCORRECT: Raw concatenation
$bad_path = WP_CONTENT_DIR . '/uploads/riseup-asia-uploader/snapshots/' . $filename;
```

### 2.3 Error Logging Requirements

Every path operation failure MUST log:
- The operation attempted (create, read, write, delete)
- The full path involved
- The error message from PHP
- Any relevant context (permissions, disk space, etc.)

```php
if (!@mkdir($path, 0755, true)) {
    $error = error_get_last();
    $this->logger->error('Directory creation failed', array(
        'path' => $path,
        'error' => $error ? $error['message'] : 'Unknown error',
        'operation' => 'mkdir',
        'permissions' => decoct(fileperms(dirname($path)) & 0777),
    ));

    return false;
}
```

---

## 3. Go Implementation

### 3.1 Path Utility Package

Located at: `backend/internal/pathutil/pathutil.go`

Already exists with:
- `ToAbsolute()` - Resolve and normalize paths
- `ForDisplay()` - Format for logging
- Windows long path support (`\\?\` prefix)

### 3.2 Additional Requirements

For Go, ensure:
1. All paths go through `pathutil` package
2. Use `os.MkdirAll()` with explicit permissions
3. Log failures with structured context

```go
func EnsureDir(path string, log *logger.Logger) error {
    absPath, err := pathutil.ToAbsolute(path)
    if err != nil {
        log.Error("path resolution failed",
            "path", path,
            "error", err)
        return err
    }
    
    if err := os.MkdirAll(absPath, 0755); err != nil {
        log.Error("directory creation failed",
            "path", absPath,
            "error", err)
        return err
    }
    
    log.Debug("directory ensured", "path", absPath)
    return nil
}
```

---

## 4. Security Considerations

### 4.1 Path Traversal Prevention

Always validate paths don't escape their intended boundaries:

```php
public static function is_safe_path($path, $base_path): bool {
    $real_base = realpath($base_path);
    $real_path = realpath($path);

    // For non-existent paths, check the parent
    if ($real_path === false) {
        $real_path = realpath(dirname($path));
    }

    if ($real_path === false) {
        return false;
    }

    return strpos($real_path, $real_base) === 0;
}
```

### 4.2 Protected Directories

Directories containing sensitive data must have:
- `.htaccess` with `Deny from all`
- `index.php` with `// Silence is golden`
- Proper file permissions (0755 for dirs, 0644 for files)

---

## 5. Logging Format

Path-related log entries must follow this format:

```
[TIMESTAMP] [LEVEL] [PATH] Operation: {op}, Path: {path}, Result: {result}
```

Examples:
```
[2026-02-06 14:30:22] [INFO] [PATH] Directory created: /snapshots, secure: true
[2026-02-06 14:30:22] [ERROR] [PATH] mkdir failed: /snapshots, error: Permission denied
[2026-02-06 14:30:22] [DEBUG] [PATH] Path validated: /snapshots/001.sqlite, safe: true
```

---

## 6. Checklist for Path Operations

Before any path operation:

- [ ] Base path comes from a constant
- [ ] Path is joined using utility method
- [ ] Directory existence is validated
- [ ] Directory is created if missing
- [ ] Security files added (if sensitive)
- [ ] Failure is logged with context
- [ ] Path traversal is prevented

---

*Specification Version: 1.0.0*  
*Last Updated: 2026-02-06*
