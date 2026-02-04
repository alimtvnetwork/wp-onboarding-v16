# Memory: features/seeding/automatic-mappings
Updated: 2026-02-04

---

## Overview

During database initialization, the seeding logic automatically maps all plugins to all available sites (all-to-all mapping) to ensure the development environment is fully configured and functional upon first run.

---

## Implementation Details

1. **Version Comparison**: Seeding only runs when `config.version` > `db.seed_version`
2. **All-to-All Mapping**: Each seeded plugin is mapped to ALL seeded sites
3. **dbops Package**: Uses `dbops.CreateMapping()` for proper logging and affected rows tracking
4. **Idempotent**: `INSERT OR IGNORE` prevents duplicate mapping errors
5. **Startup Verification**: `ensureMappingsExist()` runs on EVERY startup to catch missing mappings

---

## Logging Format

```
[v1.19.0 - 2026-02-04 05:30:00 PM] === SEEDING START === sites=1 plugins=3 (INFO config.go:268)
[v1.19.0 - 2026-02-04 05:30:00 PM] Processing site index=1 name=Atto Property Demo ... (INFO config.go:272)
[v1.19.0 - 2026-02-04 05:30:00 PM] Site CREATED name=Atto Property Demo id=1 (INFO config.go:295)
[v1.19.0 - 2026-02-04 05:30:00 PM] Mapping CREATED table=PluginMappings pluginId=1 siteId=1 (INFO dbops.go:195)
[v1.19.0 - 2026-02-04 05:30:00 PM] === SEEDING COMPLETE === sitesTotal=1 pluginsTotal=3 mappingsCreated=3 (INFO config.go:350)
```

---

## Files

- `backend/internal/config/config.go` - `seedSitesAndPlugins()`, `ensureMappingsExist()`
- `backend/internal/database/database.go` - `CreateSeedMapping()` with dbops
- `backend/internal/database/dbops/dbops.go` - Shared DB operation utilities

---

*Seeding runs on application startup via `config.SeedIfNeeded(db, cfg, log)`*
