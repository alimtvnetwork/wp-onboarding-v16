# Memory: architecture/coding-standards/path-handling
Updated: 2026-02-06

## Overview

All file path operations must use centralized utility methods. Never use raw string concatenation or direct language path functions without going through the project's path utilities.

## Core Rules

1. **Constants First**: All base paths originate from constants (RISEUP_*_SUBDIR in PHP, pathutil in Go)
2. **Centralized Joining**: Use `Riseup_Path_Utils::join()` in PHP, `pathutil.ToAbsolute()` in Go
3. **Validate Before Use**: Always check directory exists, create if missing
4. **Log All Failures**: Every path operation failure logs full context

## PHP Path Utility

Located at `wp-plugins/riseup-asia-uploader/includes/class-path-utils.php`:

```php
// Join path segments
$path = Riseup_Path_Utils::join($base, $subdir, $filename);

// Ensure directory exists (with optional security)
$dir = Riseup_Path_Utils::ensure_path(true, $base, RISEUP_SNAPSHOTS_SUBDIR);

// Get plugin base directory
$base = Riseup_Path_Utils::get_base_dir();

// Validate path is safe (no traversal)
$safe = Riseup_Path_Utils::is_safe_path($path, $base);
```

## Go Path Utility

Located at `backend/internal/pathutil/pathutil.go`:

```go
// Resolve and normalize
absPath, err := pathutil.ToAbsolute(path)

// Format for logging
displayPath := pathutil.ForDisplay(path)
```

## Error Logging Requirements

Every path failure logs:
- Operation type (create, read, write, delete)
- Full path involved
- Error message
- Context (permissions, disk space)

## Security

Sensitive directories get:
- `.htaccess` with `Deny from all`
- `index.php` with silence comment
- 0755 permissions (dirs) / 0644 (files)

## Related Files

- Spec: `spec/wordpress-plugin-development/08-path-handling.md`
- PHP: `wp-plugins/riseup-asia-uploader/includes/class-path-utils.php`
- Go: `backend/internal/pathutil/pathutil.go`
