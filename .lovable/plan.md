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

## ✅ COMPLETED — Phase 1: Autoloader Diagnostics & Fallback Loading (2026-02-19)

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

---

# 🆕 Go Backend Strict Guidelines — Phased Improvement Plan (2026-02-20)

## Current State (Already Implemented)

- 15-line function body limit (enforced via spec)
- `dbutil` generic wrappers: `Result[T]`, `ResultSet[T]`, `ExecResult` with `HasError()`, `IsEmpty()`, `IsSafe()`
- `AppError` with typed diagnostic setters (`WithStatusCode`, `WithMethod`, etc.)
- Domain typed constants (`StageStatusType`, `ActivationStatusType`, etc.)
- Zero `any` / type erasure policy
- `apperror.Wrap()` for stack traces

---

## Go Phase 1: Enhanced Error Architecture (`pkg/apperror`)

### 1.1 — `AppError` Stack Trace Guarantee

Every `AppError` must capture a stack trace at creation time — not optionally.

```go
// pkg/apperror/app_error.go
type AppError struct {
    Code       ErrorCode
    Message    string
    Cause      error
    Stack      StackTrace   // always populated at creation
    Diagnostic ErrorDiagnostic
}

// Constructor — stack trace captured automatically
func New(code ErrorCode, message string) *AppError

// Wrapping — preserves original stack if AppError, adds new context
func Wrap(err error, message string) *AppError

// WrapWithCode — wrap with explicit error code
func WrapWithCode(err error, code ErrorCode, message string) *AppError
```

### 1.2 — `StackTrace` Type

```go
// pkg/apperror/stack_trace.go
type StackFrame struct {
    Function string
    File     string
    Line     int
}

type StackTrace []StackFrame

func CaptureStack(skip int) StackTrace
func (s StackTrace) String() string       // full formatted trace
func (s StackTrace) CallerLine() string   // "file.go:42"
```

### 1.3 — `AppError` Display Methods

```go
func (e *AppError) Error() string          // message only (implements error)
func (e *AppError) FullString() string     // code + message + stack trace + cause chain
func (e *AppError) Unwrap() error          // standard unwrap for errors.Is/As
func (e *AppError) Is(target error) bool   // match by ErrorCode
```

### 1.4 — Generic `Result[T]` (Service-Level)

Extend beyond `dbutil` for all service returns:

```go
// pkg/apperror/result.go
type Result[T any] struct {
    value T
    err   *AppError
}

func Ok[T any](value T) Result[T]
func Fail[T any](err *AppError) Result[T]
func FailWrap[T any](err error, msg string) Result[T]

func (r Result[T]) HasError() bool
func (r Result[T]) IsSafe() bool           // !HasError
func (r Result[T]) Value() T               // panics if HasError
func (r Result[T]) ValueOr(fallback T) T
func (r Result[T]) Error() *AppError
func (r Result[T]) Unwrap() (T, error)     // bridge to (T, error) pattern
```

### Implementation Files

| File | Action |
|------|--------|
| `pkg/apperror/app_error.go` | Rewrite with mandatory StackTrace field |
| `pkg/apperror/stack_trace.go` | New — StackFrame, CaptureStack, String |
| `pkg/apperror/result.go` | New — generic Result[T] for services |
| `pkg/apperror/error_code.go` | Audit — ensure all codes are typed constants |

### Estimated Effort: 4 tasks

---

## Go Phase 2: File & Function Size Enforcement

### 2.1 — File Size Limit: 300 Lines Max

- Add lint script: `scripts/lint-file-size.sh`
- Scans all `.go` files excluding generated/vendor
- Fails CI if any file exceeds 300 lines
- Splitting pattern:
  - `entity.go` — struct + constructors
  - `entity_crud.go` — DB operations
  - `entity_validation.go` — validation logic
  - `entity_helpers.go` — private utilities

### 2.2 — Function Body Limit: 15 Lines Max

- Add lint script: `scripts/lint-func-size.sh`
- Counts lines between `func` opening `{` and closing `}`
- Excludes blank lines and comments from count
- Extraction patterns:
  - Multi-step → orchestrator + helpers
  - Complex conditions → named boolean variables
  - Switch/case → lookup maps or strategy pattern

### 2.3 — Cyclomatic Complexity: Max 1

- No nested `if` statements — early return mandatory
- Complex boolean expressions → named variables
- `if err != nil { return }` does not count as nesting

### Lint Scripts

| Script | Rule |
|--------|------|
| `scripts/lint-file-size.sh` | No `.go` file > 300 lines |
| `scripts/lint-func-size.sh` | No function body > 15 lines |

### Estimated Effort: 3 tasks (scripts + file splitting)

---

## Go Phase 3: Constants, Enums & DRY Enforcement

### 3.1 — Typed Constants Standard

All string literals in business logic must use typed constants:

```go
// domain/types/status_type.go
type StatusType string

const (
    StatusActive   StatusType = "active"
    StatusInactive StatusType = "inactive"
    StatusPending  StatusType = "pending"
)

func (s StatusType) String() string { return string(s) }
func (s StatusType) IsValid() bool  { /* lookup map */ }
func (s StatusType) IsOtherThan(other StatusType) bool { return s != other }
```

### 3.2 — Enum Pattern (iota + String)

For non-string enums, use iota with `String()` method:

```go
type LogLevel int

const (
    LogDebug LogLevel = iota
    LogInfo
    LogWarn
    LogError
)

func (l LogLevel) String() string {
    return [...]string{"debug", "info", "warn", "error"}[l]
}
```

### 3.3 — Zero Magic Strings/Numbers

- Lint rule: no raw string literals in function bodies (except struct tags, test assertions)
- All HTTP status codes → `HttpStatusType` constants
- All error codes → `ErrorCode` constants
- All config keys → typed const block

### 3.4 — DRY Enforcement Patterns

- Repeated error handling → `apperror.Result[T]` or helper functions
- Repeated JSON key access → typed response structs
- Repeated validation → `Validate()` method on input structs
- Repeated DB patterns → `dbutil` generic wrappers (already done)

### Estimated Effort: 4 tasks (audit + migration)

---

## Go Phase 4: Positive Logic & Boolean Standards

### 4.1 — Positive Boolean Naming

```go
// ❌ Negative naming
func IsNotValid() bool
func HasNoPermission() bool
if !user.IsDisabled() { ... }

// ✅ Positive naming
func IsValid() bool
func HasPermission() bool
if user.IsActive() { ... }
```

### 4.2 — Negation Elimination

- Replace `!isX` with positive counterpart method
- `IsOtherThan(val)` instead of `!= val` (mirrors PHP enum pattern)
- Named boolean variables for compound conditions:

```go
// ❌ Inline negation
if !user.IsAdmin() && !request.IsInternal() { ... }

// ✅ Named positive logic
isExternalNonAdmin := user.IsRegular() && request.IsExternal()
if isExternalNonAdmin { ... }
```

### 4.3 — Lint Rule

- `scripts/lint-negative.sh` — flag `IsNot*`, `HasNo*` function names
- Manual review for `!` negation in boolean expressions

### Estimated Effort: 2 tasks

---

## Go Phase 5: Code Organization Standards

### 5.1 — Package Structure

```
pkg/
  apperror/          # Error types, Result[T], stack traces
    app_error.go     # AppError struct + constructors (<300 lines)
    result.go        # Result[T] generic
    stack_trace.go   # StackTrace capture + formatting
    error_code.go    # ErrorCode typed constants
    diagnostic.go    # ErrorDiagnostic struct + setters
  dbutil/            # Database wrappers (existing)
  httputil/          # HTTP helpers, response writers
  validate/          # Validation helpers

internal/
  domain/            # Domain models + typed constants
    types/           # Shared type definitions
  handler/           # HTTP handlers (thin, delegate to services)
    response_types.go
  service/           # Business logic
    {entity}/
      service.go           # Public interface + constructor
      {entity}_crud.go     # DB operations
      {entity}_helpers.go  # Private helpers
      broadcast_details.go # Event payload structs
```

### 5.2 — File Naming Conventions

- One primary type per file
- File name matches primary type: `app_error.go` → `AppError`
- Helpers suffixed: `_helpers.go`, `_crud.go`, `_validation.go`
- Test files: `_test.go` suffix (standard Go)

### 5.3 — Import Organization (3 groups, blank-line separated)

```go
import (
    // stdlib
    "context"
    "fmt"

    // internal packages
    "project/pkg/apperror"
    "project/internal/domain"

    // third-party
    "github.com/lib/pq"
)
```

### Estimated Effort: 3 tasks (restructure + rename)

---

## Go Phase 6: CI Lint Scripts & Integration

### 6.1 — Complete Lint Script Suite

| Script | Rule |
|--------|------|
| `scripts/lint-file-size.sh` | No `.go` file > 300 lines |
| `scripts/lint-func-size.sh` | No function body > 15 lines |
| `scripts/lint-ge.sh` | No `any`, `interface{}`, `map[string]any` in business logic |
| `scripts/lint-magic.sh` | No magic strings/numbers in function bodies |
| `scripts/lint-negative.sh` | No `IsNot*`, `HasNo*` function names |

### 6.2 — CI Pipeline Order

1. `go vet ./...`
2. `golangci-lint run`
3. Custom lint scripts (above)
4. `go test ./...`

### Estimated Effort: 2 tasks

---

## Execution Order & Dependencies

| Phase | Scope | Dependencies | Effort |
|-------|-------|-------------|--------|
| **Go Phase 1** | `pkg/apperror` rewrite — StackTrace, Result[T] | None — foundational | 4 tasks |
| **Go Phase 2** | Lint scripts + file splitting to ≤300 lines | Phase 1 (new error patterns) | 3 tasks |
| **Go Phase 3** | Constants audit + magic string migration | Phase 1 (error codes) | 4 tasks |
| **Go Phase 4** | Boolean logic cleanup, positive naming | Phase 3 (named types) | 2 tasks |
| **Go Phase 5** | Package restructuring | Phases 1–4 | 3 tasks |
| **Go Phase 6** | CI integration | All phases | 2 tasks |

**Total: ~18 tasks across 6 phases**

Each phase is independently deployable. **Go Phase 1 is the critical foundation** — all other phases reference `Result[T]` and `AppError`.

---

## ✅ Go Phase 1 — Partially Complete (2026-02-21)

- AppError struct with mandatory StackTrace ✅ (already implemented)
- Result[T], ResultSlice[T], ResultMap[K,V] ✅ (already implemented)
- Error codes in `codes.go` ✅
- Typed diagnostic setters ✅
- Service Adapter pattern ✅

---

# 🆕 Backend Standards Compliance Plan (2026-02-21)

## Overview

Comprehensive plan to bring the Go backend into full compliance: byte-based enums, AppError JSON serialization, Go file naming conventions, config type safety, and remaining raw error audit.

---

## Phase A: Spec Updates (Must Complete First)

### A1 — Add Go File Naming & Organization Convention to Spec

**File:** `spec/03-golang-standards/readme.md`  
**Action:** Add new section covering:

| Rule | Convention | Example |
|------|-----------|---------|
| Package directory | `snake_case` | `site_health/` |
| File name | `snake_case`, maps to primary type | `server_config.go` → `ServerConfig` |
| One exported type per file | Each struct/interface gets its own file | Split `config.go` → `config.go`, `server_config.go`, etc. |
| Suffix convention | `_crud.go`, `_helpers.go`, `_validation.go` | `plugin_crud.go` |
| Max 300 lines target | Soft limit 400 | Split when exceeded |
| Functions | Group related funcs with their type's file | `StatusType` methods stay in `status_type.go` |

### A2 — Add AppError JSON Serialization to Spec

**File:** `spec/05-error-manage/06-apperror-package/readme.md`  
**Action:** Add section "§11 — JSON Serialization":

- `AppError` already has JSON tags ✅
- Add `MarshalJSON()` that includes `Cause` as string (currently `json:"-"`)
- Add `UnmarshalJSON()` that reconstructs `Cause` from string
- All sub-structs (`StackTrace`, `StackFrame`, `ErrorDiagnostic`) already have JSON tags ✅

### A3 — Make MarshalJSON/UnmarshalJSON Mandatory for Enums

**File:** `spec/03-golang-standards/01-enum-specification/02-required-methods.md`  
**Action:** Move from "Optional" to "Mandatory". All byte-based enums MUST implement JSON marshal/unmarshal.

### A4 — Add WP Plugin Publish to Enum Spec Inventory

**File:** `spec/03-golang-standards/01-enum-specification/00-overview.md`  
**Action:** Add to "Applies To" table with status "🔄 Migration In Progress".

---

## Phase B: Enum Migration (String → Byte-Based)

### Current State — 13 String-Based Types

All in `backend/internal/wordpress/` and `backend/internal/services/session/`:

| # | Type | File | Current Base | Variants |
|---|------|------|-------------|----------|
| 1 | `StatusType` | `status_type.go` | `string` | Success, Failed |
| 2 | `UploadSourceType` | `upload_source_type.go` | `string` | TBD |
| 3 | `HeaderType` | `header_type.go` | `string` | HTTP header names |
| 4 | `EndpointType` | `endpoint_type.go` | `string` | REST API paths |
| 5 | `ActionType` | `action_type.go` | `string` | Transaction actions |
| 6 | `ResponseMessageType` | `response_message_type.go` | `string` | API messages |
| 7 | `PluginStatusType` | `plugin_status_type.go` | `string` | WP plugin states |
| 8 | `PostStatusType` | `post_status_type.go` | `string` | WP post states |
| 9 | `SnapshotErrorType` | `snapshot_error_type.go` | `string` | Snapshot error codes |
| 10 | `ResponseKeyType` | `response_key_type.go` | `string` | JSON response keys |
| 11 | `HttpStatusType` | `http_status_type.go` | `int` | HTTP status codes |
| 12 | `SessionType` | `session/service.go` | `string` | Session types (inline) |

### Per-Enum Migration Steps

For each enum:
1. Change base type from `string`/`int` to `byte`
2. Add `Invalid` as zero value with `iota`
3. Add `variantStrings` and `variantLabels` lookup tables
4. Add 7 mandatory methods: `String`, `Label`, `Is{Value}`, `All`, `ByIndex`, `Parse`, `IsValid`
5. Add `MarshalJSON` / `UnmarshalJSON`
6. Update all call sites (replace `.String()` comparisons with `.Is{Value}()`)

### Special Considerations

| Type | Issue | Proposed Resolution |
|------|-------|-------------------|
| `HttpStatusType` | HTTP codes (200, 404) don't fit byte iota | **Decision needed** |
| `EndpointType` | String-valued paths, 30+ variants | Byte with string lookup works |
| `ResponseKeyType` | 45+ JSON key strings | Byte with string lookup works |
| `SessionType` | Inline in `service.go` | Extract to `session_type.go` |

### Location Decision

**Current:** All in `backend/internal/wordpress/`  
**Options:**
- **A:** Move to `backend/internal/enums/{category}/variant.go` (full spec compliance)
- **B:** Keep in current packages, convert to byte-based (pragmatic)

---

## Phase C: Config Refactoring

### C1 — Config JSON Tags Decision

**Current:** `json:"camelCase"` matching `config.json` file format  
**Spec says:** PascalCase for API response structs  

**Recommendation:** Config structs are **file contracts**, not API responses. Keep camelCase JSON tags for config. Only API response structs use PascalCase.

### C2 — Replace Config String Fields with Enums

| Config Field | Current | Target Enum |
|-------------|---------|-------------|
| `LoggingConfig.Level` | `string` | `log_level.Variant` |
| `SnapshotConfig.Mode` | `string` | `snapshot_mode.Variant` |
| `SnapshotConfig.BackupType` | `string` | `backup_type.Variant` |
| `SnapshotConfig.PluginSelection` | `string` | `plugin_selection.Variant` |

### C3 — Split Config File (535 Lines → ≤300)

| New File | Content | ~Lines |
|----------|---------|--------|
| `config.go` | `Config` struct + `Load()` + `DefaultConfig()` | ~120 |
| `config_structs.go` | All sub-config structs | ~100 |
| `config_seed.go` | `SeedIfNeeded()`, `seedFromConfig()`, `seedSitesAndPlugins()` | ~180 |
| `config_helpers.go` | `ensureMappingsExist()`, `normalizeUrl()`, `compareVersions()` | ~80 |

### C4 — Replace Comments with Self-Documenting Code

Remove inline comments from config structs where enums or descriptive field names make intent clear.

### C5 — Remove Redundant JSON Tags

If Go field name matches desired JSON key (only applicable if PascalCase), drop the tag. Keep `omitempty` tags.

---

## Phase D: AppError JSON Serialization

### D1 — Add MarshalJSON to AppError

```go
// error_json.go
func (e *AppError) MarshalJSON() ([]byte, error) {
    type alias AppError
    return json.Marshal(&struct {
        *alias
        CauseMessage string `json:"cause,omitempty"`
    }{
        alias:        (*alias)(e),
        CauseMessage: causeMessage(e),
    })
}

func causeMessage(e *AppError) string {
    if e.Cause == nil {
        return ""
    }
    return e.Cause.Error()
}
```

### D2 — Add UnmarshalJSON to AppError

```go
func (e *AppError) UnmarshalJSON(data []byte) error {
    type alias AppError
    aux := &struct {
        *alias
        CauseMessage string `json:"cause,omitempty"`
    }{alias: (*alias)(e)}
    if err := json.Unmarshal(data, aux); err != nil {
        return err
    }
    if aux.CauseMessage != "" {
        e.Cause = errors.New(aux.CauseMessage)
    }
    return nil
}
```

### D3 — New File: `backend/pkg/apperror/error_json.go`

---

## Phase E: Remaining Raw Error Audit

### E1 — `requestsession/store.go`

Convert `fmt.Errorf` → `apperror.Wrap`/`apperror.New`.

### E2 — `session/service.go`

Convert `fmt.Errorf` → `apperror.Wrap`/`apperror.New`.

### E3 — Config Load/Seed Errors

`config.go` has bare `return err` in `Load()`, `SeedIfNeeded()`, and all seed functions. Wrap with `apperror.Wrap(err, apperror.ErrConfigLoad/ErrConfigSeed, ...)`.

### E4 — Full Backend Sweep

Run: `grep -rn "fmt.Errorf\|errors.New\|return err$" backend/internal/ backend/pkg/`

---

## Execution Order

```
Phase A (Specs)  ──► Phase B (Enums)    ──► Phase C (Config)
                 ──► Phase D (AppError)  ──► Phase E (Error Audit)
```

Phase A must complete first. B and D can proceed in parallel. C depends on B (enum types for config fields). E depends on D.

---

## ✅ Decisions Resolved (2026-02-21)

| Question | Decision |
|----------|----------|
| Enum location | `internal/enums/{category}/variant.go` — full spec compliance |
| HttpStatusType | Keep as `int` — exempt from byte conversion, add required methods |
| Config JSON tags | **Convert to PascalCase** — update both struct tags AND `config.json` keys |
| String-valued enums | **Convert to byte** — use `variantStrings` lookup tables |

---

## Next Steps

Start with **Phase A** (spec updates), then proceed to **Phase B** (enum migration) and **Phase D** (AppError serialization) in parallel.

---

# 🆕 PascalCase Enum Labels — Cross-System Remediation Plan (2026-02-21)

## Summary

All Go identifier-style enum `variantLabels` updated from snake_case/lowercase to PascalCase (matching Go constant names). This affects serialization, parsing, config files, and cross-system communication with PHP and TypeScript.

## ✅ Completed (Go Backend)

| Change | Files | Status |
|--------|-------|--------|
| PascalCase labels for 10 identifier enums | `action`, `backup_type`, `log_level`, `plugin_selection`, `plugin_status`, `post_status`, `snapshot_error`, `snapshot_mode`, `status`, `upload_source` | ✅ Done |
| Case-insensitive `Parse()` via `strings.EqualFold` | All 10 enums above | ✅ Done |
| Config JSON values updated | `config.json`, `config.example.json` | ✅ Done |

### Exempt Enums (Functional Labels — NOT Changed)

| Enum | Reason |
|------|--------|
| `content_type` | MIME type strings (`application/json`, `multipart/form-data`) |
| `endpoint` | URL path strings (`/status`, `/plugins`, `/upload`) |
| `header` | HTTP header names (`Authorization`, `Content-Type`) |
| `response_key` | JSON envelope keys (`success`, `message`, `data`) — API contract |
| `response_message` | Human-readable strings (`Operation completed successfully`) |

## 🔄 Phase 1: Go Backend Consumers

### 1.1 Database Stored Values
- [ ] Audit all database columns storing enum string values (action logs, status fields)
- [ ] Create migration to UPDATE stored values from snake_case → PascalCase
- [ ] `Parse()` already handles legacy values (case-insensitive via `EqualFold`)

### 1.2 WebSocket Broadcasts
- [ ] Verify enum values in broadcast payloads now emit PascalCase
- [ ] Update any hardcoded string comparisons in ws handlers

### 1.3 Go Hardcoded Strings
- [ ] Search for `== "upload_active"`, `== "per_table"`, etc.
- [ ] Replace with enum constant checks (e.g., `action.IsUploadActive()`)

## ✅ COMPLETED — Phase 2: PHP Plugin Updates (2026-02-21)

### 2.1 Enum Case Values ✅
Updated 24 internal-domain PHP enum `->value` properties to PascalCase:

| PHP Enum | Old Values | New Values |
|----------|-----------|------------|
| `ActionType` | `upload`, `upload_active`, `enable`, ... | `Upload`, `UploadActive`, `Enable`, ... |
| `UploadSourceType` | `upload_script`, `rest_api`, `admin_ui`, `wp_cli` | `Script`, `RestAPI`, `AdminUI`, `WPCLI` |
| `StatusType` | `success`, `failed` | `Success`, `Failed` |
| `SnapshotErrorType` | `SNAPSHOT_LOCK_EXISTS`, ... | `LockExists`, ... |
| `SnapshotModeType` | `full`, `incremental` | `Full`, `Incremental` |
| `LogLevelType` | `DEBUG`, `INFO`, `WARN`, `ERROR` | `Debug`, `Info`, `Warn`, `Error` |
| `PluginSelectionType` | `all`, `active`, `none` | `All`, `Active`, `None` |
| `SnapshotWorkerModeType` | `per_table`, `single`, `legacy` | `PerTable`, `Single`, `Legacy` |
| `StorageModeType` | `per-table`, `single` | `PerTable`, `Single` |
| `RestoreModeType` | `full`, `selective`, `incremental` | `Full`, `Selective`, `Incremental` |
| `RestoreStrategyType` | `truncate`, `merge` | `Truncate`, `Merge` |
| `SyncActionType` | `replace`, `delete` | `Replace`, `Delete` |
| `SyncEntryStatusType` | `success`, `error`, `ignored`, `skipped` | `Success`, `Error`, `Ignored`, `Skipped` |
| `LogCategoryType` | `snapshot`, `agent`, ... | `Snapshot`, `Agent`, ... |
| `SnapshotStatusType` | `pending`, `running`, ... | `Pending`, `Running`, ... |
| `SnapshotJobStatusType` | `queued`, `processing`, ... | `Queued`, `Processing`, ... |
| `SnapshotTriggerType` | `manual`, `scheduled`, ... | `Manual`, `Scheduled`, ... |
| `SnapshotFrequencyType` | `manual`, `daily`, ... | `Manual`, `Daily`, ... |
| `SnapshotProviderType` | `wp_reset`, `updraft`, `native`, `auto` | `WpReset`, `Updraft`, `Native`, `Auto` |
| `SnapshotScopeType` | `all`, `wordpress`, ... | `All`, `WordPress`, ... |
| `SnapshotExportStatusType` | `valid`, `expired`, `building` | `Valid`, `Expired`, `Building` |
| `TriggerSourceType` | `api`, `dashboard`, `agent_push`, ... | `Api`, `Dashboard`, `AgentPush`, ... |
| `AgentStatusType` | `pending`, `connected`, `error` | `Pending`, `Connected`, `Error` |
| `RetentionType` | `days`, `count`, `none` | `Days`, `Count`, `None` |

**Exempted** (WP API contracts / protocol values — values unchanged):
- `PostStatusType` — WP post status strings (`publish`, `draft`, `pending`)
- `CapabilityType` — WP capability strings (`manage_options`, etc.)
- `WpErrorCodeType` — WP REST error codes (`rest_forbidden`, etc.)
- `FilterKeyType` — query parameter keys (`status`, `action`, etc.)
- `EndpointType` — REST route paths (`status`, `upload`, etc.)
- `HookType` — WP hook names (`init`, `rest_api_init`, etc.)
- `ResponseKeyType` — JSON envelope keys (`success`, `data`, etc.)
- `ResponseMessageType` — human-readable strings
- `OptionNameType` — WP option names (`riseup_snapshot_settings`, etc.)
- `AgentFieldType` — array keys (`name`, `url`, etc.)
- `PathConfigType`, `PathDatabaseType`, `PathLogFileType`, `PathSubdirType` — file paths
- `PluginConfigType` — plugin identity strings
- `TableType` — SQLite table names
- `HttpMethodType` — HTTP method strings (`GET`, `POST`, etc.)
- `HttpStatusType` — HTTP status codes (int-backed)
- `HttpConfigType` — HTTP config values (int-backed)
- `PaginationConfigType`, `UpdateConfigType`, `SnapshotConfigType` — numeric config (int-backed)

### 2.2 New PHP Enum Methods ✅
- Added `isAnyOf(self ...$others): bool` to **all 46 backed enums** (string and int)
- Refactored multi-case comparisons to use `isAnyOf`:
  - `ActionType::isLifecycle()`, `LogLevelType::isErrorOrWarn()`
  - `SnapshotStatusType::isActive()`, `SnapshotErrorType::isExport()/isRestore()`
  - `HttpStatusType::isRetryable()`, `WpErrorCodeType::isAuthError()/isValidationError()/isNetworkError()`
  - `ResponseMessageType::isFailure()`

### 2.3 PHP Hardcoded Comparisons
- [ ] Search PHP codebase for hardcoded string comparisons against old values
- [ ] Replace with enum method calls

### 2.4 WordPress Database Values
- [ ] Audit `wp_options`, `wp_postmeta`, and custom tables for stored enum strings
- [ ] Create upgrade routine to update stored values on plugin update

## 🔄 Phase 3: TypeScript Frontend Updates

### 3.1 Constants File
- [ ] Update `src/lib/constants.ts` enum string values to PascalCase
- [ ] Audit all component/service files that compare against old values

### 3.2 API Response Handling
- [ ] Verify frontend correctly handles PascalCase values in API responses
- [ ] Update any switch/if-else blocks comparing enum strings

## 🔄 Phase 4: Spec Updates

### 4.1 Enum Specification
- [ ] Update `02-required-methods.md` examples to PascalCase labels
- [ ] Update complete example in same file
- [ ] Document PascalCase label convention as mandatory rule

### 4.2 PHP Standards
- [ ] Update PHP enum spec to reflect PascalCase `->value` properties
- [ ] Update `response-key-type-inventory.md` if any response keys changed

### 4.3 Cross-Language Audit
- [ ] Update `php-go-consistency-audit.md` to reflect new enum value format

## Migration Safety

- **Go `Parse()` is case-insensitive** (`strings.EqualFold`) — accepts both old and new values
- **PHP migration** should update `tryFrom()` to handle both formats during transition
- **Database migration** should be idempotent (`UPDATE WHERE value = old_value`)
- **Frontend** can be updated independently since it reads from API responses

---

# 🆕 PascalCase Database Schema Migration Plan (2026-02-21)

> **Scope:** All custom SQLite tables and columns across PHP plugin and Go backend databases.
> **Excluded:** WordPress core tables (`wp_posts`, `wp_options`, etc.) — managed by WP core.
> **Timeline:** Synchronized — PHP and Go migrate in the same phase.

## Current State Inventory

### Go Main DB (`migrations.go`) — ✅ Already PascalCase
Tables: `Sites`, `Plugins`, `PluginMappings`, `FileChanges`, `SyncRecords`, `Backups`, `ErrorLogs`, `AppConfig`, `PluginGitConfig`, `PluginVersions`, `RemotePluginsCache`, `ErrorHistory`, `PublishHistory`, `SiteHealthChecks`
Columns: `Id`, `Name`, `Url`, `PluginId`, `SiteId`, `CreatedAt`, `UpdatedAt`, etc.

### Go SplitDB (`splitdb/manager.go`) — ❌ snake_case
Tables: `projects`, `databases`, `database_stats`
Columns: `id`, `project_id`, `slug`, `display_name`, `size_bytes`, `record_count`, `created_at`, etc.

### Go E2E Service (`e2e/service.go`) — ❌ snake_case
Tables: `test_suites`, `test_cases`, `test_runs`, `test_results`
Columns: `suite_id`, `case_id`, `run_id`, `started_at`, `duration_ms`, etc.

### PHP Plugin SQLite — ❌ snake_case
Tables (via `TableType` enum): `transactions`, `agent_sites`, `agent_actions`, `snapshots`, `snapshot_progress`, `snapshot_jobs`, `snapshot_settings`, `snapshot_exports`, `file_cache`, `remote_plugins_cache`, `error_sessions`, `flash_state`
Columns: `id`, `plugin_slug`, `agent_site_id`, `created_at`, `status`, `error_msg`, etc.

### PHP Root DB (`RootDbSchemaTrait`) — ❌ snake_case
Tables: `snapshot_meta`, `snapshot_tables`, `table_dependencies`, `incremental_backups`, `plugin_snapshots`
Columns: `table_name`, `row_count`, `sqlite_file`, `file_size_bytes`, `checksum_md5`, etc.

---

## ✅ COMPLETED — Phase 1: Spec & Standard Updates (2026-02-21)

| # | Task | File | Status |
|---|------|------|--------|
| 1.1 | Created `database-naming.md` as single source of truth for DB naming | `spec/01-coding-guidelines/database-naming.md` | ✅ |
| 1.2 | Updated WP plugin DB spec with PascalCase section + cross-reference | `spec/07-wordpress-plugin-development/03-database-patterns.md` | ✅ |
| 1.3 | Updated Go standards with PascalCase DB naming section + cross-reference | `spec/03-golang-standards/readme.md` | ✅ |
| 1.4 | Created/updated memory files for DB naming conventions | `.lovable/memory/architecture/database/` | ✅ |
| 1.5 | Created `pascal-case-naming-convention.md` memory with migration phase tracking | Memory | ✅ |
| 1.6 | Updated `enum-storage-format.md` memory with DB naming relationship | Memory | ✅ |

---

## Phase 2: Go Backend Migration

### ✅ COMPLETED — Phase 2A: SplitDB Manager (3 tables) (2026-02-21)

| Old Table | New Table |
|-----------|-----------|
| `projects` | `Projects` |
| `databases` | `Databases` |
| `database_stats` | `DatabaseStats` |

**Column renames** (all 3 tables): `id` → `Id`, `project_id` → `ProjectId`, `slug` → `Slug`, `display_name` → `DisplayName`, `path` → `Path`, `created_at` → `CreatedAt`, `updated_at` → `UpdatedAt`, `status` → `Status`, `type` → `Type`, `entity_id` → `EntityId`, `size_bytes` → `SizeBytes`, `record_count` → `RecordCount`, `last_accessed_at` → `LastAccessedAt`, `database_id` → `DatabaseId`, `recorded_at` → `RecordedAt`, `query_count` → `QueryCount`, `avg_query_ms` → `AvgQueryMs`

**Implementation:** ✅ All completed:
1. ✅ Added `migrateToPascalCase()` to `splitdb/manager.go` — detects legacy `projects` table and renames all tables + columns
2. ✅ Updated `initRootSchema()` to create tables with PascalCase names
3. ✅ Updated all 12 SQL queries across `manager.go` and `export.go` to PascalCase

### Phase 2B: E2E Service (4 tables)

| Old Table | New Table |
|-----------|-----------|
| `test_suites` | `TestSuites` |
| `test_cases` | `TestCases` |
| `test_runs` | `TestRuns` |
| `test_results` | `TestResults` |

**Column renames:** `suite_id` → `SuiteId`, `case_id` → `CaseId`, `run_id` → `RunId`, `case_name` → `CaseName`, `started_at` → `StartedAt`, `completed_at` → `CompletedAt`, `duration_ms` → `DurationMs`, `error_message` → `ErrorMessage`, `error_details` → `ErrorDetails`, `request_data` → `RequestData`, `response_data` → `ResponseData`, `timeout_seconds` → `TimeoutSeconds`, `order_index` → `OrderIndex`, `total_tests` → `TotalTests`, `passed_tests` → `PassedTests`, `failed_tests` → `FailedTests`, `skipped_tests` → `SkippedTests`, `preconditions` → `Preconditions`, `expected_result` → `ExpectedResult`

**Implementation:**
1. Add migration function to `e2e/service.go`
2. Recreate schema with PascalCase names (SQLite doesn't support column rename before v3.25)
3. Update all SQL queries and model references in `e2e/` package

---

## Phase 3: PHP Plugin SQLite Migration (v13)

### Phase 3A: Update `TableType` Enum Values

| Old Value | New Value |
|-----------|-----------|
| `transactions` | `Transactions` |
| `agent_sites` | `AgentSites` |
| `agent_actions` | `AgentActions` |
| `snapshots` | `Snapshots` |
| `snapshot_progress` | `SnapshotProgress` |
| `snapshot_jobs` | `SnapshotJobs` |
| `snapshot_settings` | `SnapshotSettings` |
| `snapshot_exports` | `SnapshotExports` |
| `file_cache` | `FileCache` |

### Phase 3B: Migration v13 — Table Renames

For each table, use `ALTER TABLE old_name RENAME TO NewName` (supported in SQLite ≥ 3.2).

### Phase 3C: Migration v13 — Column Renames

SQLite doesn't support `ALTER TABLE ... RENAME COLUMN` before v3.25 (PHP typically bundles SQLite 3.35+, so this should be safe). For each table, rename all columns to PascalCase:

**`Transactions` columns:** `id` → `Id`, `action` → `Action`, `plugin_slug` → `PluginSlug`, `post_id` → `PostId`, `user_login` → `UserLogin`, `user_id` → `UserId`, `ip_address` → `IpAddress`, `details` → `Details`, `status` → `Status`, `error_msg` → `ErrorMsg`, `created_at` → `CreatedAt`, `plugin_file` → `PluginFile`, `was_active` → `WasActive`, `triggered_by` → `TriggeredBy`, `agent_site_id` → `AgentSiteId`, `source_machine` → `SourceMachine`, `plugin_version` → `PluginVersion`, `upload_source` → `UploadSource`

**`AgentSites` columns:** `id` → `Id`, `name` → `Name`, `url` → `Url`, `username` → `Username`, `app_password_encrypted` → `AppPasswordEncrypted`, `redirect_url` → `RedirectUrl`, `redirect_resolved` → `RedirectResolved`, `redirect_resolved_at` → `RedirectResolvedAt`, `status` → `Status`, `last_sync` → `LastSync`, `last_error` → `LastError`, `created_at` → `CreatedAt`, `updated_at` → `UpdatedAt`

**`AgentActions` columns:** `id` → `Id`, `agent_site_id` → `AgentSiteId`, `action` → `Action`, `target_plugin` → `TargetPlugin`, `status` → `Status`, `details` → `Details`, `error_msg` → `ErrorMsg`, `created_at` → `CreatedAt`

**`Snapshots` columns:** `id` → `Id`, `sequence` → `Sequence`, `filename` → `Filename`, `filepath` → `Filepath`, `created_at` → `CreatedAt`, `completed_at` → `CompletedAt`, `status` → `Status`, `provider` → `Provider`, `scope` → `Scope`, `tables_json` → `TablesJson`, `table_counts_json` → `TableCountsJson`, `total_rows` → `TotalRows`, `file_size` → `FileSize`, `duration_ms` → `DurationMs`, `triggered_by` → `TriggeredBy`, `error_message` → `ErrorMessage`, `metadata_json` → `MetadataJson`

**`SnapshotProgress` columns:** `id` → `Id`, `snapshot_id` → `SnapshotId`, `table_name` → `TableName`, `status` → `Status`, `rows_total` → `RowsTotal`, `rows_exported` → `RowsExported`, `started_at` → `StartedAt`, `completed_at` → `CompletedAt`, `error_message` → `ErrorMessage`

**`SnapshotSettings` columns:** `key` → `Key`, `value` → `Value`, `type` → `Type`, `updated_at` → `UpdatedAt`

**`SnapshotExports` columns:** `id` → `Id`, `snapshot_id` → `SnapshotId`, `zip_filename` → `ZipFilename`, `zip_path` → `ZipPath`, `zip_size` → `ZipSize`, `included_ids` → `IncludedIds`, `incremental_count` → `IncrementalCount`, `created_at` → `CreatedAt`, `expires_at` → `ExpiresAt`, `status` → `Status`

**`FileCache` columns:** `plugin_slug` → `PluginSlug`, `relative_path` → `RelativePath`, `md5_hash` → `Md5Hash`, `modified_at` → `ModifiedAt`, `file_size` → `FileSize`, `cached_at` → `CachedAt`

**`remote_plugins_cache` → `RemotePluginsCache`** columns: `id` → `Id`, `site_id` → `SiteId`, `data_json` → `DataJson`, `fetched_at` → `FetchedAt`, `expires_at` → `ExpiresAt`

**`error_sessions` → `ErrorSessions`** columns: `id` → `Id`, `level` → `Level`, `message` → `Message`, `file` → `File`, `line` → `Line`, `context_json` → `ContextJson`, `stack_trace` → `StackTrace`, `created_at` → `CreatedAt`

**`flash_state` → `FlashState`** columns: `key` → `Key`, `value` → `Value`, `updated_at` → `UpdatedAt`

### Phase 3D: Update All PHP Code References

1. Update `TableType` enum values
2. Update all SQL queries across traits, services, and helpers
3. Update `Orm` class column references
4. Update `FileCacheStoreTrait`, `FileCacheScanTrait` column references
5. Update all `DatabaseQuery*Trait` column references
6. Update `AdminErrorAjaxTrait` column references
7. Update `RootDbRegistrationTrait` and `RootDbSchemaTrait`

---

## Phase 4: PHP Root DB Schema Migration

Root DB (`a-root.db`) tables created per-snapshot — can't ALTER existing files, so:

1. Update `RootDbSchemaTrait::createSchema()` to use PascalCase for new snapshots
2. Update `RootDbRegistrationTrait` SQL queries
3. Update `populateMetadata()`, `populateDependencies()` column references
4. Update restore traits that read from root DB
5. Add compatibility layer: when reading old snapshots, detect schema version and map columns

| Old Table | New Table |
|-----------|-----------|
| `snapshot_meta` | `SnapshotMeta` |
| `snapshot_tables` | `SnapshotTables` |
| `table_dependencies` | `TableDependencies` |
| `incremental_backups` | `IncrementalBackups` |
| `plugin_snapshots` | `PluginSnapshots` |

---

## Phase 5: Code Sweep & Validation

| # | Task |
|---|------|
| 5.1 | Grep all `.php` files for snake_case column references in SQL queries |
| 5.2 | Grep all `.go` files for snake_case column references in SQL queries |
| 5.3 | Run full test suite to verify migrations |
| 5.4 | Test snapshot create → restore cycle with new schema |
| 5.5 | Test backward compatibility: restore old snake_case snapshot with new code |

---

## Migration Safety Rules

- All table renames use `ALTER TABLE ... RENAME TO ...` (idempotent with IF EXISTS)
- All column renames use `ALTER TABLE ... RENAME COLUMN ... TO ...` (SQLite ≥ 3.25)
- PHP v13 migration runs inside a transaction with rollback on failure
- Go migrations are version-tracked and idempotent
- Root DB backward compatibility: new code must detect and handle old snake_case snapshots
- `TableType` enum values update simultaneously with migration execution
- All index names should also be updated to match PascalCase tables

## Estimated Effort

| Phase | Tasks | Estimated |
|-------|-------|-----------|
| Phase 1: Specs | 5 | 1 session |
| Phase 2: Go Backend | 6 | 2 sessions |
| Phase 3: PHP Plugin | 8 | 3 sessions |
| Phase 4: Root DB | 5 | 1 session |
| Phase 5: Validation | 5 | 1 session |
| **Total** | **29** | **8 sessions** |
