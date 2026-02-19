# Plan: Doc Updates + Three New Code Style Rules + PHP Enforcement

## ✅ COMPLETED — Part A: Spec/Doc Files — Rename PathUtils to PathHelper

All 12+ doc/spec files updated: `RiseupPathUtils` → `PathHelper`, `CleanerUtilsTrait` → `CleanerHelperTrait`, `RestoreUtilsTrait` → `RestoreHelperTrait`.

## ✅ COMPLETED — Part B: Three New/Updated Code Style Rules

- **B1**: Rule 4 updated to include `throw` alongside `return`
- **B2**: Rule 8 added — No leading backslash on `Throwable`
- **B3**: Rule 9 added — Multi-line parameters when >2 params with trailing comma

All three rules added to `spec/01-coding-guidelines/code-style.md` and cross-referenced in PHP specs.

## ✅ COMPLETED — Part C: PHP Code Enforcement

All three sub-tasks fully enforced across ~70 PHP files in `riseup-asia-uploader` as of 2026-02-17:

- **C1**: `\Throwable`, `\PDO`, `\PDOException`, `\WP_Error`, `\WP_REST_Response`, `\wpdb`, `\ZipArchive`, `\Exception` → unqualified with `use` imports. Re-swept and verified zero violations on 2026-02-17 (16 files fixed in final pass).
- **C2**: All functions with >2 params reformatted to one-per-line with trailing comma. Re-swept and verified zero violations on 2026-02-17 (51 signatures across 33 files fixed in final pass).
- **C3**: Blank line before `return`/`throw` enforced in all multi-statement blocks.

Additionally: `!PathHelper::dirExists()` → `PathHelper::isDirMissing()` and `!PathHelper::isSafePath()` → `PathHelper::isPathMissing()` guard conversions applied.

`plugins-onboard` confirmed out of scope per companion-plugin-scope.md.

---

## 📂 Spec Directory Structure (audited 2026-02-17)

```
spec/
├── 01-coding-guidelines/     — code-style.md, function-naming.md
├── 02-typescript-standards/   — TypeScript-specific rules
├── 03-golang-standards/       — Go-specific rules
├── 04-php-standards/          — PHP-specific rules + README
├── 05-error-manage/           — Error handling, logging, response envelopes
├── 06-api-documentation/      — API doc standards
├── 07-wordpress-plugin-development/ — 11 specs (Logging, DB, API, Hooks, etc.)
├── 08-deployment/             — Deployment procedures
├── 09-testing/                — Testing standards
├── 10-security/               — Security guidelines
├── 11-activity-feed/          — E2E activity feed specs
├── 12-generic-enforce/        — Cross-language enforcement patterns
```

## ✅ COMPLETED — Part D: Spec Modernization Sweep

Full grep across all `spec/` files verified zero remaining legacy patterns (2026-02-17):

- `class Riseup_` / `new Riseup_` → **0 matches**
- `\Throwable`, `\PDO`, `\WP_Error`, `\Exception` (backslash-prefixed) → **0 matches**
- `HookEnum`, `PathUtils`, `CapabilityEnum`, `HttpMethodEnum` → **0 matches**
- `$this->snake_case` internal methods → **0 matches**
- `define('RISEUP_'` → 1 match, intentional `❌ WRONG` example
- `function riseup_asia_init()` → 3 matches, correct (WP-registered global hooks stay snake_case)

## ✅ COMPLETED — Part E: Manual C3 Audit

Deep manual audit of ~30 high-traffic PHP files (2026-02-17). Fixed **22 C3 violations** (missing blank line before `return`/`throw`) across **12 files**:

- `WorkerExecuteTrait` (1), `SnapshotExporter` (1), `SnapshotManager` (1)
- `RestoreTableTrait` (1), `OrchestratorBackupTrait` (5), `CleanerHelperTrait` (1)
- `ExporterPublicApiTrait` (4), `WorkerSetupTrait` (3), `WorkerBatchProcessTrait` (1)
- `DetectorProviderTrait` (2), `SchedulerExecutorTrait` (2), `NativeSnapshotCreateTrait` (1)
- `InitHelpers` (2), `ResponseTrait` (1)

## ✅ COMPLETED — Part F: Documentation Modernization (Stale References)

Swept all `spec/` files for stale legacy references (2026-02-17):

- **F1**: `RiseupBooleanHelpers` → `BooleanHelpers` in `spec/04-php-standards/readme.md`
- **F2**: `interface{}` struct fields → `json.RawMessage` in Go delegation/logging specs (`spec/05-error-manage/`)
- **F3**: Legacy `extractPHPStackTrace`/`extractLogHint` helpers → concrete `DelegatedResponseBody` struct

- **F4**: `RiseupSnapshotFactory` → `SnapshotFactory` in `dry-principles.md`, `dry-refactoring-summary.md`, `changelog.md`
- **F5**: `RiseupEnvelopeBuilder` → `EnvelopeBuilder` in `readme.md`, `adr.md`, `changelog.md`
- **F6**: Legacy `class-*.php` file paths → PSR-4 PascalCase paths across all 5 spec files
- **F7**: Updated `spec/04-php-standards/readme.md` naming table and file naming convention to PSR-4

---

## ✅ COMPLETED — Part G: Plugin Load Safety Audit (2026-02-17)

All 6 phases completed:

- **Phase 1** ✅ Replaced undefined `PLUGIN_VERSION` with `PluginConfigType::Version->value` in `admin-logs.php` and `admin-snapshots.php`.
- **Phase 2** ✅ Added `error_log()` diagnostic to `Autoloader.php` when a `RiseupAsia\` class file is not found on disk.
- **Phase 3** ✅ Removed raw `require_once` in `SchedulerCronTrait.php` — PSR-4 autoloading handles `SnapshotFactory`.
- **Phase 4** ✅ Replaced all P3 raw negation violations:
  - `PluginLifecycleHelpersTrait.php`: `!function_exists()` → `BooleanHelpers::isFuncMissing()`
  - `InitDirTrait.php`: `!@mkdir()`, `!wp_mkdir_p()` → named `$isMkdirFailed`, `$isWpFallbackFailed` booleans; `!self::makeDirectory()` → `$isBaseDirFailed`, `$isSubDirFailed`
  - `LoggerPathTrait.php`: `!InitHelpers::makeDirectoryNative()` → `$isBaseDirFailed`, `$isLogsDirFailed`
- **Phase 5** ✅ Added boot-time `error_log()` breadcrumbs to `Admin::__construct()` and `ActivationHandler::activate()` (4 breadcrumbs across load → dirs → complete).
- **Phase 6** ✅ Verified: PHP `use` is file-scoped, so template `use` statements work correctly regardless of include context. No changes needed.

---

# 🆕 Comprehensive Improvement Plan (2026-02-19)

## Table of Contents

1. [Phase 1: Autoloader Diagnostics & Fallback Loading](#phase-1-autoloader-diagnostics--fallback-loading)
2. [Phase 2: Snapshot Subsystem Audit & Fixes](#phase-2-snapshot-subsystem-audit--fixes)
3. [Phase 3: Boot-Time Error Email Notification](#phase-3-boot-time-error-email-notification)
4. [Phase 4: Self-Update Mechanism Hardening](#phase-4-self-update-mechanism-hardening)
5. [Phase 5: Licensing System Architecture](#phase-5-licensing-system-architecture)
6. [Appendix A: Remaining Magic Strings](#appendix-a-remaining-magic-strings)

---

## Phase 1: Autoloader Diagnostics & Fallback Loading

### Current State

The `RiseupAsiaAutoloader` (includes/Autoloader.php) uses `spl_autoload_register` with a single `load()` method. If `require_once` fails (syntax error, missing dependency inside the file), PHP throws a fatal error with no structured diagnostics.

### Problems Identified

| # | Issue | Severity |
|---|-------|----------|
| 1.1 | A **syntax error** inside any class file causes an unhandled fatal error — no structured report of *which* file failed | Critical |
| 1.2 | No **manifest validation** — if a new class is added but the file isn't deployed, the error log message doesn't distinguish "file missing" from "file exists but has a parse error" | Medium |
| 1.3 | No **boot health report** — on first activation there's no summary of "X classes loaded successfully, Y failed" | Low |

### Proposed Solution

#### 1A — Diagnostic Require Mode

Add a static method `RiseupAsiaAutoloader::runDiagnostics(): array` that:

1. Scans `includes/` recursively for all `.php` files.
2. For each file, runs `php_check_syntax()` / `token_get_all()` to detect parse errors **without** executing the file.
3. Attempts `require_once` in a `try/catch` with a shutdown handler for fatal errors.
4. Returns a structured result: `['loaded' => [...], 'failed' => ['file' => '...', 'error' => '...']]`.

> **Constraint**: This method must remain self-contained (no Enums, no PathHelper) since it runs before those classes are available.

#### 1B — Activation Health Check

In `ActivationHandler::activate()`, call `RiseupAsiaAutoloader::runDiagnostics()` and:

- Store the result in a transient (`riseup_boot_diagnostics`).
- If any file failed, display an admin notice on the plugins page.
- Log failures via native `error_log()` (since FileLogger may not be available).

#### 1C — Runtime Fallback with Error Capture

Enhance the existing `load()` method:

```php
private static array $failedClasses = [];

private static function load(string $class): void {
    // ... existing namespace check ...
    
    if (file_exists($file)) {
        try {
            require_once $file;
        } catch (Throwable $e) {
            error_log('[Riseup Asia] Autoloader: failed to load "' . $class . '" — ' . $e->getMessage());
            self::$failedClasses[] = ['class' => $class, 'file' => $file, 'error' => $e->getMessage()];
        }
    } else {
        error_log('[Riseup Asia] Autoloader: class file not found for "' . $class . '"');
        self::$failedClasses[] = ['class' => $class, 'file' => $file, 'error' => 'File not found'];
    }
}

public static function getFailedClasses(): array { return self::$failedClasses; }
```

#### Implementation Files

| File | Action |
|------|--------|
| `includes/Autoloader.php` | Add `$failedClasses`, `getFailedClasses()`, `runDiagnostics()` |
| `includes/Activation/ActivationHandler.php` | Call diagnostics on activation |
| `includes/Admin/Traits/AdminNoticesTrait.php` | Show boot failure notices |

#### Estimated Effort: 2–3 tasks

---

## ✅ COMPLETED — Phase 2: Snapshot Subsystem Audit & Fixes (2026-02-19)

All 13 issues (2.1–2.13) resolved across 10 files:

- **2A** ✅ Replaced all snapshot magic strings with enum values:
  - `AnalyzerQueryTrait.php`: Documented `'all'` default params as matching `SnapshotScopeType::All->value`
  - `SnapshotCrudCreateTrait.php`: `'all'` → `SnapshotScopeType::All->value`
  - `SnapshotCrudRestoreTrait.php`: `'full'` → `RestoreModeType::Full->value`, `'a-root.db'` → `SnapshotConfigType::RootDbFilename`
  - `RootDbSchemaTrait.php`: `'Untitled Snapshot'` → `SnapshotConfigType::UntitledTitle`
  - `OrchestratorBackupTrait.php`: `'all'` → `PluginSelectionType::All->value`
  - `DatabaseMigrationsV6V8Trait.php`: Documented migration defaults with enum references (literals required by PHP)
- **2B** ✅ Added orphaned directory cleanup in `WorkerExecuteTrait::execute()` and `executeSynchronous()` catch blocks + job creation failure
- **2C** ✅ Added `validatePreSnapshotSize()` — checks `information_schema.TABLES` against `SnapshotConfigType::MaxSizeMb` before both async and sync snapshots
- **2D** ✅ Enhanced `SnapshotProviderLockTrait`:
  - Replaced hardcoded `1800` with `SnapshotConfigType::LockTimeoutSeconds->value`
  - Added PID-based stale lock detection via `posix_kill($pid, 0)`
- **2E** ✅ Refactored `isMasterSnapshot()` to use `SnapshotModeType::tryFrom()` + `->isFull()` — removed brittle `strpos($filename, '_full_')` check
- **2F** ✅ Added `zip_failed` flag to `executeZipPhase()` and `finalizeSyncExport()` return arrays

New enum: `SnapshotConfigType::LockTimeoutSeconds` (1800), `SnapshotConfigType::UntitledTitle` ('Untitled Snapshot')

---

## ✅ COMPLETED — Phase 3: Boot-Time Error Email Notification (2026-02-19)

All 4 sub-tasks (3A–3D) implemented across 6 files:

- **3A** ✅ `BootErrorCollector` singleton created at `ErrorHandling/BootErrorCollector.php`:
  - `addError(context, message)` collects errors with timestamps
  - Registers `register_shutdown_function` on first error (once)
  - `flush()` sends via `AdminMailer` then clears errors
- **3B** ✅ `AdminMailer` created at `Notification/AdminMailer.php`:
  - `sendBootErrorReport()` sends plain-text email via `wp_mail()`
  - Throttled via `riseup_last_error_email` transient (default 60 minutes)
  - Body includes: site URL, plugin version, PHP version, WP version, numbered error list
  - Subject: `[Riseup Asia] Plugin Boot Errors on {site_name}`
- **3C** ✅ Integration points wired:
  - `Autoloader::load()` failures → `reportToBootCollector('autoloader', ...)`
  - `riseup_asia_init()` wraps `Plugin::getInstance()` in try/catch → `BootErrorCollector::addError('plugin_init', ...)`
  - `riseup_asia_init()` wraps `Admin::getInstance()` in try/catch → `BootErrorCollector::addError('admin_init', ...)`
  - Shutdown hook auto-registered by BootErrorCollector
- **3D** ✅ `OptionNameType::ErrorNotification` added (`riseup_error_notification_settings`)

---

## ✅ COMPLETED — Phase 4: Self-Update Mechanism Hardening (2026-02-19)

All 4 sub-tasks (4A–4D) implemented across 7 files:

- **4A** ✅ Removed deprecated `OPTION_NAME` and `DEFAULT_CACHE_DAYS` constants from `UpdateResolver.php`
  - Replaced hardcoded `$maxRedirects = 5` with `?int $maxRedirects = null` + `UpdateConfigType::MaxRedirects->value` in `UpdateResolverUrlTrait`
- **4B** ✅ Created `UpdateResolverIntegrityTrait`:
  - `verifyChecksum($filePath, $expectedHash)` — SHA-256 verification via `hash_file()` + `hash_equals()`
  - `downloadAndVerify($packageUrl, $expectedHash)` — `download_url()` + checksum gate; auto-deletes on mismatch
  - `parseUpdateResponseBody()` now extracts `sha256` field from update JSON
- **4C** ✅ Created `UpdateResolverBackupTrait`:
  - `createPreUpdateBackup()` — copies plugin dir to `wp-content/upgrade/{slug}-backup-{timestamp}/`
  - `rollbackFromBackup($backupDir)` — deletes failed update, restores from backup, cleans up
  - `cleanupBackup($backupDir)` — removes backup after successful update
  - Uses `recursiveCopy()` and `recursiveDelete()` private helpers
- **4D** ✅ Added new `WpErrorCodeType` cases: `FileNotFound`, `ChecksumMismatch`, `BackupFailed`, `RollbackFailed`
- **4E** — License header deferred to Phase 5

---

## Phase 5: Licensing System Architecture

### How Major WordPress Products Handle Licensing

#### Elementor Pro Model
- **License Key**: A unique string (e.g., `ELEM-XXXX-XXXX-XXXX`) issued per purchase.
- **Activation**: User enters the key in WP admin → plugin sends `POST` to Elementor's licensing server with `license_key + site_url`.
- **Server Response**: `{ "valid": true, "expires": "2027-01-01", "sites_limit": 3, "sites_used": 1 }`.
- **Site Limit**: Each activation registers the `site_url`; deactivating on one site frees a slot.
- **Update Gate**: The update server checks `license_key` before returning the download URL. Expired/invalid licenses get no updates.
- **Uses**: EDD (Easy Digital Downloads) Software Licensing add-on as the backend.

#### Starter Templates / Starter Sites Model
- Similar to Elementor but uses the Starter Templates API.
- License key tied to a purchase; validated via API.
- Site count tracked server-side.

#### WooCommerce Extensions Model
- License keys generated on `woocommerce.com`.
- Uses a `wc-am` (WooCommerce API Manager) protocol.
- Each key has a `max_activations` count.
- Updates served only to active, licensed sites.

### Proposed Licensing Architecture for Riseup Asia

#### Two-Part System

##### Part A: License Server (Separate Service)

This is a standalone API (could be Go, Node, PHP, or SaaS) that:

1. **Generates Licenses**: Creates keys in format `RASIA-XXXX-XXXX-XXXX-XXXX`.
2. **Stores License Records**:
   ```
   licenses table:
   - id, license_key, customer_email, plan (starter|pro|agency)
   - max_sites, created_at, expires_at, is_active
   
   activations table:
   - id, license_id, site_url, activated_at, deactivated_at, is_active
   ```
3. **API Endpoints**:
   - `POST /api/v1/license/activate` — `{key, site_url}` → validates key, checks site count, registers activation.
   - `POST /api/v1/license/deactivate` — `{key, site_url}` → frees a site slot.
   - `POST /api/v1/license/validate` — `{key, site_url}` → checks if still valid.
   - `GET /api/v1/update/check` — `{key, site_url, current_version}` → returns latest version + download URL if licensed.

4. **Plans**:
   | Plan | Sites | Price Model |
   |------|-------|-------------|
   | Starter | 1 site | One-time or annual |
   | Pro | 5 sites | Annual |
   | Agency | Unlimited | Annual |

##### Part B: WordPress Plugin Client

1. **License Settings Page** — New admin tab with key input, activate/deactivate, status display.
2. **License Manager Class** — `RiseupAsia\License\LicenseManager`:
   - `activate(string $key): array`
   - `deactivate(): array`
   - `validate(): array` (daily cron)
   - `isLicensed(): bool` (cached)
3. **Update Integration** — `UpdateResolver` includes license key in headers:
   ```php
   'headers' => ['X-License-Key' => $licenseKey, 'X-Site-Url' => site_url()]
   ```
4. **Feature Gating** — Restrict features (snapshots, agents, exports) to licensed users.
5. **New Enums**: `LicensePlanType`, `LicenseStatusType`.

#### Build vs. Buy Decision

| Option | Pros | Cons |
|--------|------|------|
| **Build custom** (Go backend) | Full control, integrates with existing Go backend | Maintenance, security responsibility |
| **Keygen.sh** | Purpose-built, handles edge cases | Monthly cost ($49+/mo) |
| **LemonSqueezy** | Payment + licensing in one | Less control |
| **EDD + Software Licensing** | WordPress-native, proven | Requires separate WP site |
| **WooCommerce + WC-AM** | Familiar ecosystem | Complex setup |

#### Recommendation

Build a custom license module in the existing Go backend for zero external dependencies, full control, and direct integration with the update server.

#### Estimated Effort: 8–10 tasks (split across server and plugin)

---

## ✅ COMPLETED — Appendix A: Remaining Magic Strings (2026-02-19)

All magic strings from the original audit resolved:

- **AgentRemoteActionTrait.php**: Already uses `AgentStatusType::Error->value`, `AgentStatusType::Connected->value` ✅
- **AgentHandlerActionTrait.php**: Already uses `ActionType::Enable->value`, etc. ✅
- **PostQueryTrait.php**: Already uses `PostStatusType::validValues()` ✅
- **AnalyzerQueryTrait.php**: Default params documented as matching `SnapshotScopeType::All->value` (PHP constraint) ✅
- **WorkerSetupTrait.php**: Already uses `SnapshotScopeType`, `SnapshotModeType`, `SnapshotConfigType` enums ✅
- **OrchestratorBackupTrait.php**: Already uses `SnapshotModeType::Full->value` ✅
- **CleanerRetentionTrait.php**: Already uses `SnapshotModeType::tryFrom()` + `->isFull()` ✅
- **SnapshotCrudCreateTrait.php**: Fixed in Phase 2A ✅
- **UpdateResolver.php**: Fixed in Phase 4A ✅
- **IncrementalRegistrationTrait.php**: Already uses `SnapshotModeType::Incremental->value` ✅
- **SyncPushTrait.php**: Created `SyncEntryStatusType` enum (Success, Error, Ignored, Skipped) and replaced all 11 magic status strings ✅

New enum: `SyncEntryStatusType` at `includes/Enums/SyncEntryStatusType.php`

---

## Implementation Priority

| Priority | Phase | Description | Depends On |
|----------|-------|-------------|------------|
| 🔴 P0 | 2A | Fix all snapshot magic strings | ✅ Done |
| 🔴 P0 | Appendix A | Fix all remaining magic strings | ✅ Done |
| 🟡 P1 | 1A–1C | Autoloader diagnostics | None |
| 🟡 P1 | 3A–3D | Boot error email notification | Phase 1 |
| 🟡 P1 | 4A–4B | Update mechanism magic strings + checksum | None |
| 🟢 P2 | 2B–2F | Snapshot robustness (transactions, locks, size) | Phase 2A |
| 🟢 P2 | 4C–4D | Update rollback mechanism | Phase 4A |
| 🔵 P3 | 5 | Licensing system | Phase 4 |

---

## Next Steps

Please review and confirm:

1. **Which phase(s) to start with?**
2. **Phase 5**: Build license server in Go (existing backend) or use a third-party service?
3. **Phase 4**: Update URL ready, or build infrastructure first?
4. **Any phases to skip or defer?**
