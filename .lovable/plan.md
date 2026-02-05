
# Comprehensive Audit Plan: Error Handling & Path Management

## Status: ✅ COMPLETE (2026-02-05)

All phases have been implemented. This plan addressed two critical areas:

1. **Error Handling Gaps** - Many places use `fmt.Errorf()` instead of structured `apperror.Wrap()` or `apperror.New()`, which means stack traces are not captured
2. **Path Management Issues** - Multiple services use raw `filepath.Join()` instead of the `pathutil` package

---

## Implementation Summary

### Phase 1: Core Infrastructure ✅
- `wordpress/client.go` - 15+ fmt.Errorf → apperror.Wrap
- `wordpress/uploader.go` - 20+ fmt.Errorf → apperror.Wrap

### Phase 2: Services ✅
- `services/backup/service.go` - Added pathutil + apperror
- `services/plugin/scanner.go` - filepath.Join → pathutil.MustJoin
- `services/git/service.go` - Replaced dirExists() with pathutil.IsDir()
- `services/watcher/service.go` - Added absolute path resolution
- `services/sync/service.go` - Added absolute path resolution

### Phase 3: Database Layer ✅
- `database/database.go` - Added pathutil + apperror
- `database/migrations.go` - fmt.Errorf → apperror.Wrap
- `database/splitdb/manager.go` - filepath.Join → pathutil.MustJoin
- `database/splitdb/export.go` - Added pathutil + apperror

### Phase 4: Other ✅
- `api/router.go` - filepath.Join → pathutil.MustJoin for SPA serving
- `services/site/service.go` - Added pathutil import
- `services/e2e/service.go` - fmt.Errorf → apperror.New
- `services/version/service.go` - fmt.Errorf → apperror.Wrap/New
- `wordpress/powershell.go` - fmt.Errorf → apperror.Wrap/New + pathutil
- `version/version.go` - filepath.Join → pathutil.MustJoin
- `pkg/apperror/codes.go` - Added E10xxx-E12xxx error codes

### Documentation ✅
- Updated `path-management.md` with prohibited patterns
- Updated `logging-requirements.md` with structured error requirements

**Total: 20 files modified, ~95 error handling changes, ~50 path management changes**

---

## Part 1: Path Management Audit

### Current State

The `pathutil` package provides Windows-aware path utilities:
- `ToAbsolute()` - Converts to absolute path with Windows long-path prefix for paths >240 chars
- `Join()` - Joins and converts to absolute
- `ForDisplay()` - Converts to forward slashes for log consistency

### Files Using Raw `filepath.Join` (Must Be Updated)

| File | Lines | Issue |
|------|-------|-------|
| `backend/internal/services/publish/service.go` | 722, 765, 821, 837 | ZIP creation and file walking |
| `backend/internal/services/backup/service.go` | 74, 274, 390 | Backup paths and ZIP entries |
| `backend/internal/services/plugin/scanner.go` | 32, 160, 229 | Detection file paths and PHP scanning |
| `backend/internal/services/git/service.go` | 138, 217, 397, 460, 513 | Git directory checks |
| `backend/internal/services/watcher/service.go` | 299, 337 | File cache population |
| `backend/internal/services/sync/service.go` | 333, 348, 371 | Local file scanning |
| `backend/internal/database/database.go` | 29, 98, 100, 104 | Database directory creation |
| `backend/internal/database/splitdb/manager.go` | 94, 230, 236, 348, 350 | Split DB paths |
| `backend/internal/database/splitdb/export.go` | 35, 137, 170, 235, 312 | Export paths |
| `backend/internal/api/router.go` | 182, 188, 189, 272, 276, 282 | SPA static file serving |
| `backend/internal/config/config.go` | (various) | Config paths |

### Implementation Steps

```text
Step 1: Update imports in all affected files
        Add: "wp-plugin-publish/pkg/pathutil"

Step 2: Replace filepath.Join with pathutil.Join or pathutil.MustJoin
        - Use pathutil.Join() when error handling is needed
        - Use pathutil.MustJoin() for paths guaranteed to be valid

Step 3: Replace filepath.Abs with pathutil.ToAbsolute
        - Especially critical for paths passed to external systems

Step 4: Use pathutil.ForDisplay() for all log output paths
        - Ensures consistent forward-slash format in logs
```

---

## Part 2: Error Handling Audit

### Current State

The `apperror` package provides structured errors with:
- Error codes (E1xxx - E9xxx)
- Automatic stack trace capture
- File/line/function context
- Context key-value pairs

### Files Using `fmt.Errorf` (Must Be Updated)

| File | Count | Critical Areas |
|------|-------|----------------|
| `backend/internal/wordpress/client.go` | ~16 | Request creation, JSON marshaling, auth failures |
| `backend/internal/wordpress/uploader.go` | ~12 | Path resolution, file reading, upload errors |
| `backend/internal/services/site/service.go` | ~8 | Uploader ZIP creation, validation |
| `backend/internal/database/database.go` | ~6 | Directory creation, DB opening |
| `backend/internal/database/migrations.go` | ~8 | Migration table creation, SQL execution |
| `backend/internal/database/splitdb/manager.go` | ~10 | Data dir creation, DB configuration |
| `backend/internal/database/splitdb/export.go` | ~6 | Project directory, file operations |
| `backend/internal/config/config.go` | ~4 | Config loading |
| `backend/internal/services/e2e/service.go` | ~2 | Active run check |

### Implementation Steps

```text
Step 1: Define new error codes if needed
        - E4xxx for file system errors (some already exist)
        - E2xxx for database errors (some already exist)
        - Verify all used codes are defined in codes.go

Step 2: Replace fmt.Errorf with appropriate apperror function
        - apperror.New(code, message) for new errors
        - apperror.Wrap(err, code, message) for wrapping existing errors

Step 3: Add context where helpful
        - .WithContext("path", path)
        - .WithContext("endpoint", endpoint)
        - .WithContext("statusCode", status)
```

---

## Part 3: Specific File Changes

### File: backend/internal/wordpress/client.go

**Before:**
```text
return nil, fmt.Errorf("failed to marshal request body: %w", err)
return nil, fmt.Errorf("failed to create request: %w", err)
return nil, fmt.Errorf("cannot reach site: %w", err)
```

**After:**
```text
return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to marshal request body")
return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create HTTP request")
return nil, apperror.Wrap(err, apperror.ErrWPConnection, "cannot reach WordPress site").
    WithContext("url", c.baseURL)
```

### File: backend/internal/wordpress/uploader.go

**Before:**
```text
return nil, fmt.Errorf("resolve zip path: %w", err)
return nil, fmt.Errorf("read zip file at %s: %w", absZipPath, err)
```

**After:**
```text
return nil, apperror.Wrap(err, apperror.ErrFSRead, "resolve zip path").
    WithContext("path", zipPath)
return nil, apperror.Wrap(err, apperror.ErrFSRead, "read zip file").
    WithContext("path", absZipPath)
```

### File: backend/internal/database/database.go

**Before:**
```text
return nil, fmt.Errorf("failed to create database directory: %w", err)
return nil, fmt.Errorf("failed to open database: %w", err)
```

**After:**
```text
return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "create database directory").
    WithContext("path", dir)
return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "open database connection").
    WithContext("path", path)
```

### File: backend/internal/services/backup/service.go

**Before:**
```text
backupPath := filepath.Join(s.backupDir, filename)
```

**After:**
```text
backupPath, err := pathutil.Join(s.backupDir, filename)
if err != nil {
    return nil, apperror.Wrap(err, apperror.ErrFSWrite, "resolve backup path")
}
```

### File: backend/internal/services/git/service.go

**Before:**
```text
gitDir := filepath.Join(p.Path, ".git")
if !dirExists(gitDir) {
```

**After:**
```text
gitDir := pathutil.MustJoin(p.Path, ".git")
if !pathutil.IsDir(gitDir) {
```

---

## Part 4: New Error Codes to Add

Add these to `backend/pkg/apperror/codes.go` if not already present:

```text
// Connection errors (E3xxx range)
const (
    ErrWPConnection  = "E3010" // WordPress connection failed
    ErrWPAuth        = "E3011" // WordPress authentication failed
    ErrWPPermission  = "E3012" // WordPress permission denied
)

// File system errors (E4xxx range) - verify these exist
const (
    ErrFSRead       = "E4001" // File read failed
    ErrFSWrite      = "E4002" // File write failed  
    ErrDirCreate    = "E4003" // Directory creation failed
    ErrDirRead      = "E4004" // Directory read failed
    ErrPathResolve  = "E4005" // Path resolution failed
)

// Database errors (E2xxx range) - verify these exist
const (
    ErrDatabaseConnect = "E2001" // Database connection failed
    ErrDatabaseMigrate = "E2002" // Database migration failed
)
```

---

## Part 5: Testing Verification

After implementing changes:

1. **Path Resolution Test**
   - Create a path longer than 260 characters on Windows
   - Verify the `\\?\` prefix is correctly added
   - Verify logs show absolute paths with forward slashes

2. **Error Stack Trace Test**
   - Trigger an upload error (wrong credentials)
   - Verify the error includes:
     - Error code (E3xxx)
     - Full stack trace with file:line
     - Context (endpoint, URL, response body)

3. **Log Format Verification**
   - All logs should follow: `[vX.X.X YYYY-MM-DD HH:MM:SS] [package] Message [LEVEL] [file:line]`
   - Error logs should include stack traces

---

## Implementation Order

### Phase 1: Core Infrastructure (Priority: High)
1. Verify all error codes exist in `codes.go`
2. Update `pathutil` package if any missing functions needed
3. Update `wordpress/client.go` - most critical for external API calls
4. Update `wordpress/uploader.go` - upload failures need full context

### Phase 2: Services (Priority: Medium)
5. Update `services/publish/service.go` - ZIP creation paths
6. Update `services/backup/service.go` - backup paths
7. Update `services/plugin/scanner.go` - directory scanning
8. Update `services/git/service.go` - git directory checks
9. Update `services/watcher/service.go` - file watching
10. Update `services/sync/service.go` - file comparison

### Phase 3: Database Layer (Priority: Medium)
11. Update `database/database.go`
12. Update `database/migrations.go`
13. Update `database/splitdb/manager.go`
14. Update `database/splitdb/export.go`

### Phase 4: Other (Priority: Low)
15. Update `api/router.go` - static file serving
16. Update `config/config.go` - config paths
17. Update `services/site/service.go` - uploader ZIP creation
18. Update `services/e2e/service.go` - test service

---

## Memory/Documentation Updates

Update these memory files after implementation:

1. **`.lovable/memory/architecture/backend/path-management.md`**
   - Add: "All file paths passed to file operations, uploads, or logs MUST use pathutil functions"
   - Add: "filepath.Join is PROHIBITED for paths that leave the local service"

2. **`.lovable/memory/architecture/backend/logging-requirements.md`**
   - Add: "All paths in logs must use pathutil.ForDisplay() for consistency"
   - Add: "External API errors MUST use APIError or apperror.Wrap, never fmt.Errorf"

3. **`.lovable/memory/architecture/backend/wordpress-api-error-standard.md`**
   - Add: "Request creation errors must use apperror.Wrap with ErrInternal"
   - Add: "All fmt.Errorf usage in wordpress package is prohibited"

---

## Files to Modify Summary

| Category | File Count | Estimated Changes |
|----------|------------|-------------------|
| WordPress package | 2 | ~30 replacements |
| Services | 7 | ~50 replacements |
| Database | 4 | ~25 replacements |
| API/Config | 2 | ~10 replacements |
| Error codes | 1 | ~5 additions |
| Memory docs | 3 | Documentation updates |
| **Total** | **19 files** | **~120 changes** |
