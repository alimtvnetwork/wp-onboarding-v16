# Memory: architecture/backend/migration-logging
Updated: 2026-02-04

---

## Overview

The logger is explicitly injected into database migration and seeding services. This ensures that every operation—including site/plugin creation and many-to-many mapping attempts—is logged with detailed error context (such as UNIQUE constraint failures) to provide full traceability during system initialization.

---

## Log Format

All backend logs use the standardized format:
```
[vX.X.X - TIMESTAMP] MESSAGE key=value ... (LEVEL file:line)
```

Example:
```
[v1.19.0 - 2026-02-04 05:30:00 PM] === SEEDING START === sites=1 plugins=3 (INFO config.go:268)
[v1.19.0 - 2026-02-04 05:30:00 PM] Mapping CREATED table=PluginMappings pluginId=1 siteId=1 (INFO dbops.go:195)
```

---

## dbops Package Integration

Since v1.19.0, all database operations that require traceability use the `dbops` package which provides:

1. **Stack Traces**: Captured on all errors
2. **Table Names**: Logged with every operation
3. **Affected Rows**: Validated and logged
4. **Caller Info**: File and line number of the calling code

---

## Files

- `backend/internal/logger/logger.go` - Simplified prefix format `[vX.X.X - TIME]`
- `backend/internal/database/migrations.go` - Migration logging
- `backend/internal/config/config.go` - Seeding and mapping logging
- `backend/internal/database/dbops/dbops.go` - Shared utilities with stack traces

---

*Logger is injected via `database.Migrate(db, log)` and `config.SeedIfNeeded(db, cfg, log)`*
