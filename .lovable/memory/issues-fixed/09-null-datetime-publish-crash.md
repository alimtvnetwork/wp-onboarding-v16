# Issue Fixed: NULL DateTime Crash in Publish Service

**Fixed in:** v1.19.1  
**Date:** 2026-02-04

---

## Problem

Publishing a plugin failed with the error:
```
[E9001] mapping not found: sql: Scan error on column index 5, name "LastSyncAt": converting NULL to string is unsupported
```

## Root Cause

In `backend/internal/services/publish/service.go`, the `getMapping()` and `getSiteCredentials()` functions were scanning nullable datetime columns (`LastSyncAt`, `LastBackupAt`, `LastTestedAt`, `CreatedAt`, `UpdatedAt`) directly into `string` variables instead of `sql.NullString`.

When a mapping has never been synced, `LastSyncAt` is NULL in the database. The SQLite driver (`modernc.org/sqlite`) cannot convert NULL to a plain string, causing a scan failure.

## Solution

1. Changed all datetime variable declarations from `string` to `sql.NullString`
2. Used `dbops.ParseNullTime()` and `dbops.ParseDateTime()` to safely convert to `time.Time` and `*time.Time`

### Before (broken):
```go
var lastSyncAt, lastBackupAt, createdAt, updatedAt string
err := row.Scan(..., &lastSyncAt, &lastBackupAt, ...)
```

### After (fixed):
```go
var lastSyncAt, lastBackupAt, createdAt, updatedAt sql.NullString
err := row.Scan(..., &lastSyncAt, &lastBackupAt, ...)
mapping.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
mapping.CreatedAt = dbops.ParseDateTime(createdAt.String)
```

## Files Modified

- `backend/internal/services/publish/service.go` - Fixed `getMapping()` and `getSiteCredentials()`
- `backend/internal/services/plugin/mappings.go` - Already fixed in previous commit
- `.lovable/memory/architecture/backend/orm-and-db-operations.md` - Added mandatory NULL handling rules

## Prevention

The ORM documentation now explicitly forbids scanning datetime columns into plain strings. All nullable datetime columns MUST use `sql.NullString` + `dbops.ParseNullTime()`.

---

*All future datetime scanning must follow this pattern to prevent NULL-related crashes.*
