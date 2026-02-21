# Memory: architecture/database/pascal-case-naming-convention
Updated: 2026-02-21

Custom database tables and columns must follow the PascalCase naming convention across Go and PHP. A 5-phase migration plan (documented in '.lovable/plan.md') manages the transition for existing data in custom tables. WordPress core tables (e.g., wp_posts, wp_options) are strictly exempt and maintain their native snake_case naming.

## Canonical Spec

The single source of truth is `spec/01-coding-guidelines/database-naming.md`, which defines:
- PascalCase for all custom SQLite table and column names
- `Idx` prefix for index names (e.g., `IdxTransactions_CreatedAt`)
- Abbreviation casing: only first letter capitalized (`Id`, `Url`, `Md5`, not `ID`, `URL`, `MD5`)
- WordPress core tables are exempt (snake_case preserved)

## Migration Phases

| Phase | Scope | Status |
|-------|-------|--------|
| Phase 1 | Spec & standard updates | ✅ Completed |
| Phase 2 | Go backend (SplitDB + E2E) | Pending |
| Phase 3 | PHP plugin (12 tables, migration v13) | Pending |
| Phase 4 | PHP Root DB (5 per-snapshot tables) | Pending |
| Phase 5 | Code sweep & validation | Pending |

## Key Rules

- `TableType` enum values must match PascalCase table names (e.g., `'Transactions'` not `'transactions'`)
- Go struct `db` and `json` tags use PascalCase
- All SQL queries across PHP and Go must reference PascalCase column names
- Root DB backward compatibility required: detect and handle old snake_case snapshots
