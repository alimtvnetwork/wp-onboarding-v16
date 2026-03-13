# Plugins-Onboard Coding Standards Audit

**Date**: 2026-03-13  
**Scope**: snake_case filenames, missing PHPDoc headers, `error_log()` usage, magic strings  
**Status**: Audit complete — violations catalogued below

---

## 1. Snake_Case Filenames (PascalCase Mandate Violation)

All 28 PHP files use WordPress-legacy `class-` / `trait-` snake_case naming instead of PascalCase.

### includes/ (16 files)
| Current | Expected |
|---|---|
| `class-audit-logger.php` | `AuditLogger.php` |
| `class-backup-manager.php` | `BackupManager.php` |
| `class-boolean-helpers.php` | `BooleanHelpers.php` |
| `class-cleanup.php` | `Cleanup.php` |
| `class-config.php` | `Config.php` |
| `class-database.php` | `Database.php` |
| `class-debug-maintenance.php` | `DebugMaintenance.php` |
| `class-error-log.php` | `ErrorLog.php` |
| `class-filesystem-utils.php` | `FilesystemUtils.php` |
| `class-include-files.php` | `IncludeFiles.php` |
| `class-init-helpers.php` | `InitHelpers.php` |
| `class-ip-whitelist.php` | `IpWhitelist.php` |
| `class-logger.php` | `Logger.php` |
| `class-mutation-token.php` | `MutationToken.php` |
| `class-oauth.php` | `OAuth.php` |
| `class-paths.php` | `Paths.php` |
| `class-plugin-manager.php` | `PluginManager.php` |
| `class-rate-limiter.php` | `RateLimiter.php` |
| `class-snapshot.php` | `Snapshot.php` |
| `class-token-encryption.php` | `TokenEncryption.php` |
| `class-upload-validator.php` | `UploadValidator.php` |
| `constants.php` | `Constants.php` |
| `security-utils.php` | `SecurityUtils.php` |

### includes/traits/ (6 files)
| Current | Expected |
|---|---|
| `trait-database-schema.php` | `DatabaseSchemaTrait.php` |
| `trait-database-settings.php` | `DatabaseSettingsTrait.php` |
| `trait-plugin-manager-filesystem.php` | `PluginManagerFilesystemTrait.php` |
| `trait-plugin-manager-upload.php` | `PluginManagerUploadTrait.php` |
| `trait-snapshot-query.php` | `SnapshotQueryTrait.php` |
| `trait-snapshot-restore.php` | `SnapshotRestoreTrait.php` |

### admin/ (1 file)
| Current | Expected |
|---|---|
| `class-admin-ui.php` | `AdminUi.php` |

### api/ (2 files)
| Current | Expected |
|---|---|
| `class-api.php` | `Api.php` |
| `class-permissions.php` | `Permissions.php` |

### admin/views/ (9 files — template files, lower priority)
| Current | Expected |
|---|---|
| `applications.php` | `Applications.php` |
| `audit-logs.php` | `AuditLogs.php` |
| `backups.php` | `Backups.php` |
| `dashboard.php` | `Dashboard.php` |
| `database.php` | `Database.php` |
| `help.php` | `Help.php` |
| `plugins.php` | `Plugins.php` |
| `settings.php` | `Settings.php` |
| `tests.php` | `Tests.php` |

**Total**: 37 files need renaming (28 PHP classes/traits + 9 templates)

> ⚠️ **Impact**: Renaming requires updating all `require`/`include` paths in `class-include-files.php` and `plugins-onboard.php` autoloader logic.

---

## 2. `error_log()` Usage (Should Use Logger)

Direct `error_log()` calls found in **6 files** (8 call sites):

| File | Line(s) | Context |
|---|---|---|
| `class-database.php` | 121, 143, 153 | Duplicates `OnboardLogger::error()` — redundant |
| `class-error-log.php` | 26 | `OnboardErrorLog::log()` itself calls `error_log()` — should delegate to `OnboardLogger` |
| `class-include-files.php` | 109, 147 | Autoloader error logging — should use `OnboardLogger::error()` |
| `class-logger.php` | 135 | `@error_log($log_entry, 3, $log_file)` — **legitimate** file-write (type 3), this is the logger's own write mechanism |
| `security-utils.php` | 179 | `error_log($message, 3, $log_file)` — **legitimate** file-write (type 3) for security log |
| `plugins-onboard.php` | 631 | Duplicates `OnboardErrorLog::log()` on the line above — redundant |

**Action items**:
- Remove 4 redundant `error_log()` calls in `class-database.php` (×3) and `plugins-onboard.php` (×1)
- Refactor `class-error-log.php` to delegate to `OnboardLogger` instead of raw `error_log()`
- Refactor `class-include-files.php` (×2) to use `OnboardLogger::error()`
- Keep `class-logger.php:135` and `security-utils.php:179` (type 3 file-writes are correct)

---

## 3. Magic Strings

### 3a. Capability strings — not centralized
`'manage_options'` appears as a raw string in **3 files** (~15 call sites):
- `admin/class-admin-ui.php` — menu registration + 8 handler methods
- `api/class-permissions.php` — 6 permission check methods
- `includes/security-utils.php` — `onboard_user_can()` default parameter

**Fix**: Create a `CapabilityType` enum (mirroring qupload/riseup-asia pattern).

### 3b. Nonce action strings — not centralized
Raw nonce strings scattered across `class-admin-ui.php`:
- `'delete_app'`, `'clear_logs'`, `'clear_temp'`, `'run_cleanup'`, `'create_app'`, `'save_settings'`, `'onboard_admin'`

**Fix**: Create a `NonceType` enum.

### 3c. Admin page slugs — not centralized
Raw slug strings in `class-admin-ui.php`:
- `'plugins-onboard'`, `'plugins-onboard-database'`, `'plugins-onboard-settings'`, `'plugins-onboard-applications'`, `'plugins-onboard-audit-logs'`, `'plugins-onboard-tests'`

**Fix**: Create an `AdminPageType` enum.

### 3d. Hook names — not centralized
Raw hook strings in `plugins-onboard.php`:
- `'plugins_loaded'`, `'rest_api_init'`, `'admin_notices'`, `'admin_menu'`, `'admin_enqueue_scripts'`, `'admin_init'`, `'rest_post_dispatch'`

**Fix**: Create a `HookType` enum (standard WP hooks).

### 3e. i18n text domain
`'plugins-onboard'` used as raw string across all files. Should use `PluginConfigType::Slug->value`.

**Fix**: Create a `PluginConfigType` enum.

---

## 4. PHPDoc Headers

All files have `/**` PHPDoc file headers ✅. However, some are minimal:
- `security-utils.php`: Header says only "Security utilities." — should include `@since` tag
- `constants.php`: Has a good header but missing `@since` tag
- View templates (`admin/views/*.php`): No file-level PHPDoc headers (template files — lower priority)

---

## Summary

| Category | Violations | Severity |
|---|---|---|
| Snake_case filenames | 37 files | 🔴 High — blocks consistency checker |
| `error_log()` misuse | 6 redundant calls | 🟡 Medium — dual logging, noise |
| Magic strings | ~40+ raw strings across 5 categories | 🟡 Medium — no centralized enums |
| PHPDoc gaps | Minor — missing `@since` tags | 🟢 Low |

### Recommended Fix Order
1. **Create Enums first** (`CapabilityType`, `NonceType`, `AdminPageType`, `HookType`, `PluginConfigType`) — unblocks magic string fixes
2. **Remove redundant `error_log()` calls** — quick wins
3. **Rename files to PascalCase** — highest impact, requires autoloader update
4. **Add missing `@since` tags** — cosmetic
