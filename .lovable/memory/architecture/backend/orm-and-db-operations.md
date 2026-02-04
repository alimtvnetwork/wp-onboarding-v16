# Memory: architecture/backend/orm-and-db-operations
Updated: 2026-02-04

---

## Overview

The backend uses the `modernc.org/sqlite` pure-Go driver with a shared `dbops` utilities package for all database operations. This package provides standardized logging with stack traces, table names, affected rows tracking, and error context.

---

## CRITICAL RULES

### 1. Raw SQL is Forbidden (99% of cases)
- **NEVER** write raw SQL queries inline in services
- **ONLY** use raw SQL for:
  - Complex JOINs that cannot be expressed through views
  - Migration scripts
- For JOINs: Create database VIEWS instead of inline queries

### 2. NULL Handling for DateTime Fields
SQLite with `modernc.org/sqlite` returns datetime as strings. **ALWAYS** use `sql.NullString` for nullable datetime columns:

```go
// WRONG - will crash on NULL values:
var lastSyncAt, createdAt string
row.Scan(&lastSyncAt, &createdAt)

// CORRECT - handles NULL safely:
var lastSyncAt, createdAt sql.NullString
row.Scan(&lastSyncAt, &createdAt)
mapping.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
mapping.CreatedAt = dbops.ParseDateTime(createdAt.String)
```

### 3. Table Name Constants (Use Model Reflection)
- All table names MUST come from a centralized constant or model reflection
- Never hardcode table names in SQL strings

```go
// Define in models or constants package:
const (
    TablePluginMappings = "PluginMappings"
    TableSites          = "Sites"
    TablePlugins        = "Plugins"
)

// Use in queries:
query := fmt.Sprintf("SELECT * FROM %s WHERE Id = ?", models.TablePluginMappings)
```

### 4. Database Views for JOINs
Create views on startup for frequently used JOINs:

```sql
-- Create view for mapping with site info
CREATE VIEW IF NOT EXISTS ViewPluginMappingsWithSite AS
SELECT 
    pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
    pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
    s.Name AS SiteName, s.Url AS SiteUrl
FROM PluginMappings pm
JOIN Sites s ON pm.SiteId = s.Id;
```

---

## dbops Package Functions

| Function | Purpose |
|----------|---------|
| `ParseDateTime(s string)` | Parse SQLite datetime string to time.Time |
| `ParseNullTime(ns sql.NullString)` | Parse nullable datetime to *time.Time |
| `ExecWithLog(db, query, args...)` | Execute with table name logging and affected rows |

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

## Error Handling Pattern

Every SQL error MUST:
1. Include the table name
2. Include the full stack trace
3. Be captured in the error modal with Copy capability

---

## Files

- `backend/internal/database/dbops/dbops.go` - Shared utilities package with ParseDateTime, ParseNullTime
- `backend/internal/database/database.go` - Uses dbops for CreateSeedMapping
- `backend/internal/config/config.go` - Seeding logic with enhanced logging
- `backend/internal/services/publish/service.go` - Uses sql.NullString for nullable fields
- `backend/internal/services/plugin/mappings.go` - Uses dbops.ParseNullTime for all datetime parsing

---

*All database operations MUST use the dbops package for datetime parsing and follow the NULL-safe patterns above.*
