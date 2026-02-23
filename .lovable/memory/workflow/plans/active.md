# Active Development Plan

Updated: 2026-02-23

## Current Focus Areas

- **Code Quality & Standards**: Ongoing enforcement of formatting rules and code consistency.
- **Refactoring & Modernization**: Strategic refactoring for improved maintainability and adherence to architectural patterns.
- **Issue Documentation**: Every fix now requires a write-up under `/spec/02-app/issues/` per the post-fix workflow.

## Recently Completed

- **S-009**: `WpErrorCodeType` already had PascalCase custom values — verified complete, no changes needed.
- **S-010**: Migrated `FilterKeyType` query parameters to `camelCase`.
- **S-011**: Migrated `OptionNameType` keys to PascalCase with `wp_options` data migration routine.
- **S-016**: Scanned TypeScript/React files — zero R12/R13 violations found.
- **S-024**: Added `Hourly` case to `SnapshotFrequencyType` and wired into all consumers.
- **S-025**: Fixed R9c violation in `ManagerImportValidationTrait.php`.
- **S-029**: ABSPATH guards — audit confirmed all 53 enum files already compliant (2026-02-23).
- **S-030**: ABSPATH guards — audit confirmed all 13 Logging/ErrorHandling files already compliant (2026-02-23).
- **Phase 8**: Plugin identity hardcoded strings replaced with `PluginConfigType` enum (2026-02-23).
- **Memory review**: Cross-references audited and fixed across all architecture docs (2026-02-23).

## Pending Tasks (10 open suggestions)

| ID | Priority | Description |
|----|----------|-------------|
| S-021 | Medium | Fix R12 + formatting in Plugin.php, Admin.php, FileLogger.php |
| S-024 | Medium | Deduplicate Database DEFAULT_LIMIT/MAX_LIMIT with PaginationConfigType |
| S-031 | Medium | Fix ActivationHandler R12, R4, indentation |
| S-022 | Low | Fix formatting in Templates/*.php |
| S-023 | Low | Fix formatting in root files |
| S-025 | Low | Audit PHP hardcoded string comparisons vs old enum values |
| S-026 | Low | Update TypeScript enum string values to PascalCase |
| S-027 | Low | Fix admin-errors.php template magic strings |
| S-028 | Low | Update core-enum-inventory memory to include LogColumnType |
| S-032 | Low | Remove dead loadDependencies() + redundant class_exists |
