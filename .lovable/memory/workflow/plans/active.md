# Active Development Plan

Updated: 2026-02-23

## Current Focus Areas

- **Code Quality & Standards**: Ongoing enforcement of formatting rules and code consistency.
- **Refactoring & Modernization**: Strategic refactoring for improved maintainability and adherence to architectural patterns.
- **Issue Documentation**: Every fix now requires a write-up under `/spec/02-app/issues/` per the post-fix workflow.

## Recently Completed

> Note: Items prefixed with `E-` were ad-hoc enum migration tasks tracked outside the suggestions tracker.

- **E-009**: `WpErrorCodeType` already had PascalCase custom values — verified complete.
- **E-010**: Migrated `FilterKeyType` query parameters to `camelCase`.
- **E-011**: Migrated `OptionNameType` keys to PascalCase with `wp_options` data migration routine.
- **E-016**: Scanned TypeScript/React files — zero R12/R13 violations found.
- **E-024**: Added `Hourly` case to `SnapshotFrequencyType` and wired into all consumers.
- **E-025**: Fixed R9c violation in `ManagerImportValidationTrait.php`.
- **S-021**: R12 formatting in Plugin.php, Admin.php, FileLogger.php — audit confirmed already compliant (2026-02-23).
- **S-029**: ABSPATH guards — audit confirmed all 53 enum files already compliant (2026-02-23).
- **S-030**: ABSPATH guards — audit confirmed all 13 Logging/ErrorHandling files already compliant (2026-02-23).
- **S-031**: ActivationHandler R12/R4/indentation — audit confirmed already resolved (2026-02-23).
- **S-032**: Dead `loadDependencies()` and redundant `class_exists` — audit confirmed already removed (2026-02-23).
- **Phase 8**: Plugin identity hardcoded strings replaced with `PluginConfigType` enum (2026-02-23).
- **Memory review**: Cross-references audited and fixed across all architecture docs (2026-02-23).

- **S-024**: Database pagination constants — audit confirmed already deduplicated (2026-02-23).

- **S-022**: Templates formatting — all 5 templates audited, fully compliant (2026-02-23).
- **S-023**: Root files formatting — both files audited, fully compliant (2026-02-23).

- **S-026**: TypeScript enum PascalCase — converted ActivityType, BackupOperation, NotificationType + all 8 consumer files (2026-02-23).

## Pending Tasks

**All 32 suggestions completed.** 🎉
