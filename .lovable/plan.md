
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

## 🔴 CURRENT — Part G: Plugin Load Safety Audit (2026-02-17)

### Phase 1 — 🔴 FATAL: Undefined `PLUGIN_VERSION` Constant
**Will crash admin pages.** `PLUGIN_VERSION` is used in `templates/admin-logs.php` (L54, L265) and `templates/admin-snapshots.php` (L17) but was never defined — the legacy `constants.php` was removed during PSR-4 migration. Replace with `\RiseupAsia\Enums\PluginConfigType::Version->value`.

### Phase 2 — 🟠 HIGH: Autoloader Silent Failures
`Autoloader.php` L31-33 silently returns when a class file isn't found. No `error_log()`, no diagnostic. Add native `error_log()` when a `RiseupAsia\` class is requested but the file doesn't exist on disk.

### Phase 3 — 🟠 HIGH: `SchedulerCronTrait` Raw `require_once`
L105 does `require_once dirname(__FILE__) . '/../SnapshotFactory.php'` — bypasses the autoloader and uses fragile path resolution. Remove and rely on PSR-4 autoloading with a `use` import.

### Phase 4 — 🟡 MEDIUM: P3 Violations (Raw Negation)
| File | Lines | Pattern |
|------|-------|---------|
| `PluginLifecycleHelpersTrait.php` | L86, L90 | `!function_exists()` |
| `InitDirTrait.php` | L43-44 | `!@mkdir()`, `!wp_mkdir_p()` |
| `LoggerPathTrait.php` | L35, L41 | `!InitHelpers::makeDirectoryNative()` |

### Phase 5 — 🟢 LOW: Boot-Time Logging Breadcrumbs
Add logging to: `Admin::__construct()`, `ActivationHandler::activate()` (per-step), and autoloader miss events. Plugin.php already logs ✅.

### Phase 6 — 🔵 INFO: Template `use` Statement Verification
Verify `use RiseupAsia\Helpers\BooleanHelpers;` in template files works at runtime (global namespace context).

### Execution Order
```
Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6
```
