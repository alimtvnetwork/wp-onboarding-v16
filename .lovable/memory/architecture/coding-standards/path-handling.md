# Memory: architecture/coding-standards/path-handling
Updated: 2026-02-07

## Overview

All file path operations must use centralized utility methods. Never use raw string concatenation or direct language path functions without going through the project's path utilities.

## PHP Class Naming Convention

**IMPORTANT**: All new PHP classes must use PascalCase without underscores.

| ❌ Old Style | ✅ New Style |
|--------------|--------------|
| `Riseup_Path_Utils` | `RiseupPathUtils` |
| `Riseup_Snapshot_Provider` | `RiseupSnapshotProvider` |
| `get_base_dir()` | `getBaseDir()` |

Method names should use camelCase: `makeDirectory()`, `isSafePath()`, `formatBytes()`.

**Note**: Legacy classes (`Riseup_File_Logger`, `Riseup_Database`, etc.) still use underscore style for backward compatibility. New classes must use PascalCase.

## Core Rules

1. **Constants First**: All base paths originate from constants (RISEUP_*_SUBDIR in PHP, pathutil in Go)
2. **Centralized Joining**: Use `RiseupPathUtils::join()` in PHP, `pathutil.ToAbsolute()` in Go
3. **Typed Directory Methods**: Use `RiseupPathUtils::getLogsDir()`, `getSnapshotsDir()`, `getTempDir()`, `getDbPath()` instead of manual joins
4. **Validate Before Use**: Always check directory exists, create if missing
5. **Log All Failures**: Every path operation failure logs full context

## PHP Path Utility

Located at `wp-plugins/riseup-asia-uploader/includes/class-path-utils.php`:

```php
// Join path segments
$path = RiseupPathUtils::join($base, $subdir, $filename);

// Typed directory accessors (preferred over manual joins)
$base = RiseupPathUtils::getBaseDir();
$logs = RiseupPathUtils::getLogsDir();
$snaps = RiseupPathUtils::getSnapshotsDir();
$temp = RiseupPathUtils::getTempDir();
$db = RiseupPathUtils::getDbPath();

// Ensure directory exists (with optional security)
$dir = RiseupPathUtils::ensurePath(true, $base, RISEUP_SNAPSHOTS_SUBDIR);

// Validate path is safe (no traversal)
$safe = RiseupPathUtils::isSafePath($path, $base);

// File/directory existence checks
$exists = RiseupPathUtils::fileExists($path);
$exists = RiseupPathUtils::dirExists($path);

// Safe deletion
RiseupPathUtils::deleteFile($path);
RiseupPathUtils::deleteDir($path);

// Format bytes for display
$size = RiseupPathUtils::formatBytes($bytes);
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

- Spec: `spec/09-wordpress-plugin-development/08-path-handling.md`
- PHP: `wp-plugins/riseup-asia-uploader/includes/class-path-utils.php`
- Go: `backend/internal/pathutil/pathutil.go`
