# Active Development Plan

Updated: 2026-02-22

## Current Focus Areas

- **Code Quality & Standards**: Ongoing enforcement of formatting rules and code consistency.
- **Refactoring & Modernization**: Strategic refactoring for improved maintainability and adherence to architectural patterns.
- **Issue Documentation**: Every fix now requires a write-up under `/spec/02-app/issues/` per the post-fix workflow.

## Recently Completed

- **S-010**: Migrated `FilterKeyType` query parameters to `camelCase`.
- **S-011**: Migrated `OptionNameType` keys to PascalCase with `wp_options` data migration routine.
- **S-016**: Scanned TypeScript/React files — zero R12/R13 violations found.
- **S-024**: Added `Hourly` case to `SnapshotFrequencyType` and wired into all consumers.
- **S-025**: Fixed R9c violation in `ManagerImportValidationTrait.php`.
- **S-029**: Added ABSPATH guards to `DbResult.php` and `IncrementalDeltaTrait.php`.
- **S-030**: Fixed R10 violations in `ActivationHandler.php`.

## Pending Tasks

- **S-009**: Migrate `WpErrorCodeType` custom values to `PascalCase` (keeping `rest_forbidden` and `rest_disabled` as WordPress-bound exceptions) and update 11 consumer traits.
