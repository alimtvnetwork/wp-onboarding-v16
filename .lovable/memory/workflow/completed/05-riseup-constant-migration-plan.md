# RISEUP_* Constant Migration Plan
Updated: 2026-02-13

## Summary

Scanned all 41 PHP files in `wp-plugins/riseup-asia-uploader/`. Found **~20 actual RISEUP_ uppercase constant usages** remaining in **3 consumer files** that need migration to unprefixed equivalents.

---

## ✅ Already Complete / Not Migration Candidates

| Category | Count | Status |
|----------|-------|--------|
| `constants-compat.php` alias definitions | ~330 | ✅ Expected — delete file after consumer migration |
| `riseup_` lowercase function names (`riseup_fatal_error_handler`, `riseup_asia_activate`, etc.) | ~8 | ✅ WordPress global function naming — NOT constants |
| `riseup_` lowercase AJAX action slugs (`riseup_test_update_connection`, etc.) in Admin.php + templates | ~15 | ✅ WordPress public API identifiers — changing breaks admin UI |
| `riseup_` lowercase cron hook VALUES in constants.php (`'riseup_snapshot_scheduled'`, etc.) | ~6 | ✅ WordPress-registered names — constant NAMES already unprefixed |
| `riseup_` lowercase option key VALUES (`'riseup_snapshot_settings'`, etc.) | ~3 | ✅ Stored in DB — changing loses existing settings |
| `HTTP_X_RISEUP_SOURCE_MACHINE` header in Logger.php | 1 | ✅ External API contract — not a constant migration |
| `'riseup_snapshot_download_'` nonce prefix in SnapshotExporter.php | 2 | ✅ WordPress nonce action string — not a constant |
| `riseup_admin_nonce` in templates | ~6 | ✅ WordPress nonce name — not a constant |
| `riseup_asia_settings_group` in templates | 1 | ✅ WordPress settings group slug |
| CSS classes (`riseup-admin`, `riseup-error-log`, etc.) in templates | many | ✅ CSS naming — not constants |

---

## ✅ Migration Complete — 3 Files, ~20 References

### Phase 1: `PostManager.php` (~10 refs) ✅ Done
Migrated 9 constants: `POST_STATUS_*`, `ACTION_POST_*`, `ACTION_CATEGORY_CREATE`, `STATUS_FAILED`, `DEFAULT_LIMIT`, `MAX_LIMIT`.

### Phase 2: `SnapshotManager.php` (~8 refs) ✅ Done
Migrated 7 constants: `TABLE_SNAPSHOTS`, `SNAPSHOT_PROVIDER_NATIVE`, `SNAPSHOT_STATUS_COMPLETE`. Log levels migrated to `LogLevel` enum (`LogLevel::Debug->value`, etc.) with `use RiseupAsia\Enums\LogLevel;` import.

### Phase 3: `IncrementalBackup.php` (~2 refs) ✅ Done
Migrated 2 constants: `TABLE_SNAPSHOTS`, `SNAPSHOT_STATUS_COMPLETE`.

### Phase 4: Delete `constants-compat.php`

After Phases 1-3 are complete and verified:
1. Delete `includes/constants-compat.php`
2. Remove `require_once` from `riseup-asia-uploader.php` line 285

---

## Recommended: Migrate LOG_LEVEL_* to LogLevel enum

The `RISEUP_LOG_LEVEL_*` constants in SnapshotManager.php should migrate directly to the `LogLevel` enum (`RiseupAsia\Enums\LogLevel`) rather than the unprefixed `LOG_LEVEL_*` define aliases, since the enum already exists:

```php
// Before
case RISEUP_LOG_LEVEL_DEBUG:

// After  
case LogLevel::Debug->value:
```
