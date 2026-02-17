

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

## 🔵 Next Priority

All plan items (Parts A–E) are complete. Potential next steps:
- Autoloader class refactored — verify plugin bootstrap works end-to-end
- Audit remaining Traits/ subdirectories (Agent, Sync, Status, FileSystem) for C3
- Review `spec/07-wordpress-plugin-development/` specs for unenforced standards
