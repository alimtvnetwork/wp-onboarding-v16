# Memory: architecture/backend/orm-and-db-operations
Updated: 2026-02-04

---

## Overview

The backend uses the `modernc.org/sqlite` pure-Go driver with a shared `dbops` utilities package for all database operations. This package provides standardized logging with stack traces, table names, affected rows tracking, and error context.

---

## Key Principles

1. **Use dbops Package**: All INSERT/UPDATE/DELETE operations should use the `dbops` package functions instead of raw `db.Exec()` calls
2. **Log Table Names**: Every database operation must include the table name in logs
3. **Track Affected Rows**: Use `RowsAffected()` to verify operations actually modified data
4. **Stack Traces on Error**: Errors automatically capture call stack for debugging
5. **Model-First Approach**: Work with model structs, use FindOrCreate patterns

---

## dbops Package Functions

| Function | Purpose |
|----------|---------|
| `ExecInsert(db, ctx, query, args...)` | Insert with affected rows + last insert ID logging |
| `ExecUpdate(db, ctx, query, args...)` | Update with affected rows tracking |
| `ExecDelete(db, ctx, query, args...)` | Delete with affected rows tracking |
| `FindOrCreate(db, ctx, selectQuery, selectArgs, insertQuery, insertArgs)` | Find existing or create new |
| `CreateMapping(db, ctx, query, args...)` | Many-to-many relationship creation with duplicate handling |

---

## Context Structure

```go
ctx := dbops.Context{
    Table:  "PluginMappings",         // Required: table name for logs
    Logger: log,                       // Logger instance
    Fields: map[string]interface{}{    // Additional context fields
        "pluginId": pluginID,
        "siteId":   siteID,
    },
}
```

---

## Example Usage

```go
// Instead of raw SQL:
// _, err := db.Exec("INSERT INTO PluginMappings ...")

// Use dbops:
created, err := dbops.CreateMapping(db.DB, dbops.Context{
    Table:  "PluginMappings",
    Logger: log,
    Fields: map[string]interface{}{
        "pluginId": pluginID,
        "siteId":   siteID,
    },
}, `INSERT OR IGNORE INTO PluginMappings ...`, pluginID, siteID, remoteSlug)
```

---

## Expected Log Output

Success:
```
[v1.19.0 - 2026-02-04 05:30:00 PM] Mapping CREATED table=PluginMappings pluginId=1 siteId=1 created=true (INFO dbops.go:195)
```

Duplicate (not an error):
```
[v1.19.0 - 2026-02-04 05:30:00 PM] Mapping EXISTS table=PluginMappings pluginId=1 siteId=1 exists=true (DEBUG dbops.go:210)
```

Error with stack trace:
```
[v1.19.0 - 2026-02-04 05:30:00 PM] DB INSERT FAILED on PluginMappings table=PluginMappings error="foreign key constraint failed" stackTrace="  at dbops.CreateMapping (dbops.go:165)\n  at database.CreateSeedMapping (database.go:260)\n..." (ERROR dbops.go:240)
```

---

## Files

- `backend/internal/database/dbops/dbops.go` - Shared utilities package
- `backend/internal/database/database.go` - Uses dbops for CreateSeedMapping
- `backend/internal/config/config.go` - Seeding logic with enhanced logging

---

*All new database operations should use the dbops package for consistency and traceability.*
