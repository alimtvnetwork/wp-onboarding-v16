# Memory: architecture/backend/migration-logging
Updated: 2026-02-04

The backend implements comprehensive logging for database migrations and seeding operations. 

## Log Format

All backend logs follow the format:
```
[vX.X.X - TIMESTAMP] message key=value... (LEVEL file:line)
```

Example:
```
[v1.18.0 - 2026-02-04 05:30:00 PM] Starting database migrations (INFO migrations.go:34)
[v1.18.0 - 2026-02-04 05:30:00 PM] Applying migration version=3 description=Add Category field (INFO migrations.go:50)
[v1.18.0 - 2026-02-04 05:30:00 PM] Migration completed version=3 (INFO migrations.go:65)
```

## Migration Logging

The `Migrate()` function in `database/migrations.go` accepts a logger and logs:
- Migration start and current version
- Each migration being applied (version + description)
- Success/failure for each migration
- Final summary with applied count

## Seeding Logging

The `SeedIfNeeded()` function in `config/config.go` accepts a logger and logs:
- Current vs config version comparison
- Each site being processed (create vs exists)
- Each plugin being processed (create vs exists)
- Each mapping creation (success vs duplicate vs failure)
- Final summary with counts

## Error Tracking

Failed operations are logged with:
- `WARN` level for non-fatal issues (duplicate mappings, missing plugins)
- `ERROR` level for fatal issues that stop the process
- Full error message and context in structured key=value format

## Integration

Both `Migrate()` and `SeedIfNeeded()` receive the logger instance from `main.go` to ensure consistent formatting and the version prefix appears in all startup logs.
