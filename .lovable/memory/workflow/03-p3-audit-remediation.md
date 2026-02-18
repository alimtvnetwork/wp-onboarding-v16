# P3 Audit — Raw Negation Elimination
Updated: 2026-02-18

## Summary

Full codebase sweep of `wp-plugins/riseup-asia-uploader/includes/` to eliminate all non-exempt `!` (raw negation) usage in `if`, `while`, and `return` statements per Boolean Principle P3.

## Results

- **~230+ violations fixed** across ~50 files in 3 sweeps (B1–B8 batch, null-guard batch, misc batch).
- **0 remaining** `if (!$var)`, `return !...`, `while (!...)`, `if (!empty(...))`, `if (!isset(...))` patterns.

## Remaining Violations (~10)

| # | Pattern | File | Line | Suggested Fix |
|---|---------|------|------|---------------|
| 1 | `if (!PathHelper::fileExists(...))` | `RootDbRegistrationTrait.php` | 66 | `PathHelper::isFileMissing(...)` |
| 2 | `if (!PathHelper::makeDirectory(...))` | `SnapshotImport.php` | 47 | Named bool `$isDirCreationFailed` |
| 3 | `if (!PathHelper::fileExists(...))` | `SnapshotImport.php` | 55 | `PathHelper::isFileMissing(...)` |
| 4 | `if (!PathHelper::makeDirectory(...))` | `NativeSnapshotRecordTrait.php` | 36 | Named bool `$isDirCreationFailed` |
| 5 | `if (!PathHelper::makeDirectory(...))` | `IncrementalCoreTrait.php` | 34 | Named bool `$isDirCreationFailed` |
| 6 | `if (!is_plugin_active(...))` | `PluginLifecycleEnableTrait.php` | 59 | Named bool `$isPluginInactive` |
| 7 | `empty($files) \|\| !is_array($files)` | `SyncPushTrait.php` | 40 | P6 extract named bool |
| 8 | `empty($line) \|\| !preg_match(...)` | `ErrorSessionHandlerTrait.php` | 152 | P6 extract named bool |
| 9 | `&& !empty(...)` | `CleanerRetentionTrait.php` | 32 | `BooleanHelpers::hasValue()` |
| 10 | `&& !empty(...)` | `CleanerRetentionTrait.php` | 40 | `BooleanHelpers::hasValue()` |

## Helpers Added During Sweep

Added to `BooleanDomainTrait.php`:
- `isWpScheduleMissing(string $hook): bool`
- `isCapabilityMissing(string $capability): bool`
- `isPropertyMissing(object $obj, string $prop): bool`

## Exempt Patterns (not violations)

- `if (!defined('ABSPATH'))` — WordPress security guards (~1,115 occurrences)
- Internal negations inside `BooleanDomainTrait.php` and `PathHelper*.php` — helper implementations
