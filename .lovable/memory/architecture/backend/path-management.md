# Backend Path Management

**Updated:** 2026-02-05

## Critical Rule: Always Use Absolute Paths

When providing file paths to:
- External systems (upload APIs, WordPress endpoints)
- Logging (error messages, progress broadcasts)
- File operations that may be passed downstream

**Always resolve to absolute paths first** using `pathutil.ToAbsolute()`.

## Path Utility Package

Location: `backend/pkg/pathutil/pathutil.go`

### Functions

| Function | Description |
|----------|-------------|
| `ToAbsolute(path)` | Resolves any path to absolute, handles Windows long paths (>260 chars) |
| `MustAbsolute(path)` | Like ToAbsolute but panics on error |
| `Join(elem...)` | Joins and returns absolute path |
| `MustJoin(elem...)` | Like Join but panics on error |
| `ForDisplay(path)` | Returns absolute path with forward slashes for logs |
| `Exists(path)` | Checks if resolved path exists |
| `IsDir(path)` | Checks if resolved path is a directory |

### Windows Long Path Handling

On Windows, paths exceeding 260 characters require the `\\?\` prefix. The `ToAbsolute` function automatically adds this prefix for paths over 240 characters (leaving headroom for additional segments).

## Pre-Upload Validation

Before uploading files to external systems:

1. **Resolve path to absolute** – Never pass relative paths like `.temp\file.zip`
2. **Verify file exists** – Use `pathutil.Exists()` 
3. **Check endpoint status** – Call the status endpoint before upload
4. **Log full URLs** – Always include absolute URLs in logs, not just endpoints

## Example Pattern

```go
// WRONG - relative path, no status check
result, err := client.Upload(".temp/plugin.zip")

// CORRECT - absolute path, status check first
absPath, err := pathutil.ToAbsolute(".temp/plugin.zip")
if err != nil {
    return err
}

// Check status first
status, err := client.GetUploaderStatus()
if err != nil {
    return fmt.Errorf("pre-upload status check failed: %w", err)
}

// Now upload with absolute path
result, err := client.Upload(absPath)
```

## Logging Requirements

All file-related logs MUST include:
- **Absolute path** (not relative)
- **Full URL** for remote operations (not just endpoint)
- **File size** when available

```go
s.broadcastDetailedLog(pluginID, siteID, "info", "upload", 
    fmt.Sprintf("Uploading to %s", fullURL), 
    map[string]interface{}{
        "zipPath": absZipPath,      // Absolute path
        "url":     fullURL,          // Full URL
        "size":    fileInfo.Size(),  // Size in bytes
    })
```

---

*Last Updated: 2026-02-05*

## Prohibited Patterns

The following patterns are **PROHIBITED** in Go backend code:

| Pattern | Replacement |
|---------|-------------|
| `filepath.Join()` for external paths | `pathutil.Join()` or `pathutil.MustJoin()` |
| `filepath.Abs()` | `pathutil.ToAbsolute()` |
| Raw paths in logs | `pathutil.ForDisplay()` |

**Exception:** `filepath.Join()` may be used for relative path calculations within a function (e.g., inside `filepath.Walk`) but the final result MUST be converted to absolute before passing to external systems.

## Files Updated (Phase 1-3)

All files in these packages have been refactored to use `pathutil`:
- `backend/internal/wordpress/` - client.go, uploader.go
- `backend/internal/services/publish/` - service.go
- `backend/internal/services/backup/` - service.go
- `backend/internal/services/plugin/` - scanner.go
- `backend/internal/services/git/` - service.go
- `backend/internal/services/watcher/` - service.go
- `backend/internal/services/sync/` - service.go
- `backend/internal/database/` - database.go, migrations.go
- `backend/internal/database/splitdb/` - manager.go