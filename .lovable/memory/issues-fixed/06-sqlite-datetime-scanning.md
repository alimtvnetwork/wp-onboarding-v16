# Issue: SQLite Datetime Scanning Error with modernc.org/sqlite

> **Category:** Backend/Database  
> **Severity:** Blocking  
> **Fixed:** 2026-02-04

---

## Symptoms

- API returns error when fetching or creating plugins
- Error message: `sql: Scan error on column index 7, name "CreatedAt": unsupported Scan, storing driver.Value type string into type *time.Time`
- Same error for `UpdatedAt` and other datetime columns

---

## Root Cause

The pure-Go SQLite driver (`modernc.org/sqlite`) stores `datetime('now')` values as **strings** in the format `"YYYY-MM-DD HH:MM:SS"`, not as native `time.Time` values.

When using the cgo-based `mattn/go-sqlite3` driver, datetime values are automatically parsed. However, `modernc.org/sqlite` returns them as raw strings, causing a type mismatch when scanning directly into `time.Time` struct fields.

---

## Solution

### 1. Scan datetime columns as `sql.NullString`

Instead of scanning directly into `time.Time`:

```go
// ❌ WRONG - will fail with modernc.org/sqlite
var createdAt time.Time
row.Scan(&createdAt)

// ✅ CORRECT - scan as string, then parse
var createdAtStr sql.NullString
row.Scan(&createdAtStr)
createdAt := parseDateTime(createdAtStr.String)
```

### 2. Create a helper function to parse SQLite datetime strings

```go
// parseDateTime parses SQLite datetime strings into time.Time
// SQLite stores datetime() as "YYYY-MM-DD HH:MM:SS" format
func parseDateTime(s string) time.Time {
    if s == "" {
        return time.Time{}
    }
    // Try SQLite datetime format first
    if t, err := time.Parse("2006-01-02 15:04:05", s); err == nil {
        return t
    }
    // Fallback to RFC3339
    if t, err := time.Parse(time.RFC3339, s); err == nil {
        return t
    }
    return time.Time{}
}
```

### 3. Apply to all services that scan datetime columns

Services affected:
- `plugin/crud.go` - `CreatedAt`, `UpdatedAt`, `LastScannedAt`
- `site/service.go` - `CreatedAt`, `UpdatedAt`, `LastTestedAt`, `LastSyncAt`
- Any other service scanning datetime columns

---

## Why Not Use Connection String Flags?

Some suggest using `?_time_format=sqlite` in the connection string, but:
1. This is **not supported** by `modernc.org/sqlite`
2. Only `mattn/go-sqlite3` (cgo) supports automatic time parsing
3. Manual string parsing is the reliable cross-driver approach

---

## Key Design Decisions

1. **Helper function in each service**: Keeps parsing logic local and explicit
2. **Dual format support**: Tries SQLite format first, then RFC3339 for flexibility
3. **Zero value for empty strings**: Prevents panics on NULL columns
4. **Use `sql.NullString`**: Properly handles NULL datetime values

---

## Verification

1. Restart the backend: `.\run.ps1 -r`
2. Navigate to Plugins page
3. Register a new plugin with the same path
4. Should succeed and show the existing plugin (idempotent behavior)

---

## Related Files

- `backend/internal/services/plugin/crud.go` - Plugin datetime parsing
- `backend/internal/services/site/service.go` - Site datetime parsing (apply same pattern)
- `backend/internal/database/database.go` - Database connection setup
