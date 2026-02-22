# Active & Future Phases

**Updated: 2026-02-22**

---

## Current Status: Deep Scan Complete — Formatting Sweep Continues 🔄

All major architectural work is complete. Boot chain deep scan confirmed **no load-breaking issues**. Remaining work is formatting compliance, ABSPATH guard additions, and dead code cleanup.

---

## In Progress

### Formatting Sweep (Rules 1, 4, 5, 9, 10, 11, 12, 13)

**Tracked in:** `.lovable/plans/rule-10-sweep.md`

| Directory | Status | Violations Fixed |
|-----------|--------|------------------|
| Snapshot/Traits/ | ✅ Done | 67+ across 5 sweeps |
| Database/Traits/ | ✅ Done | 15 |
| Admin/Traits/ | ✅ Done | 6 files |
| Logging/Traits/ | ✅ Done | 15 (R12 ×7, R10 ×5, R5 ×3) |
| Agent/Traits/ | ✅ Done | 3 (R10 ×2, double blank ×1) |
| Helpers/Traits/ | ✅ Done | 13 (R4 ×7, R5 ×3, R10 ×3) |
| Traits/Route/ | ✅ Done | Clean |
| Go Handlers | ✅ Done | 8 files |
| Go Services | ✅ Done | 27 calls |
| Database/*.php | ✅ Done | 3 (R12 ×3 in Database, Orm, RootDb) |
| ErrorHandling/*.php | ✅ Done | Clean (all 4 files compliant) |
| Core/Plugin.php | 🔵 Pending | R12 (empty line after class brace) |
| Admin/Admin.php | 🔵 Pending | Double blank line after traits |
| Logging/FileLogger.php | 🔵 Pending | R12 (empty line after class brace) |
| Activation/ActivationHandler.php | 🔵 Pending | R12 + R4 + indentation bug |
| Templates/*.php | 🔵 Pending | — |
| Root files | 🔵 Pending | — |

### ABSPATH Guard Sweep (New — from deep scan)

| Scope | Status | Files Affected |
|-------|--------|----------------|
| Enum files (16 files) | 🔵 Pending | SnapshotModeType, PluginConfigType, ResponseKeyType, etc. |
| Logging files (9 files) | 🔵 Pending | FileLogger, Logger, all Logging/Traits/ |
| ErrorHandling (2 files) | 🔵 Pending | ErrorResponse, FrameBuilder |

### Dead Code Cleanup (New — from deep scan)

| Item | Status |
|------|--------|
| ActivationHandler::loadDependencies() empty method | 🔵 Pending |
| ActivationHandler::ensureSecurity() redundant class_exists | 🔵 Pending |

---

## Deep Scan Results (2026-02-22) ✅

**Boot chain integrity verified.** No load-breaking issues found. Full analysis in `.lovable/memory/workflow/09-deep-scan-boot-chain.md`.

---

## Completed Tracks (Recent)

### Deep Scan — Boot Chain Integrity ✅ (2026-02-22)
Full scan of plugin boot sequence (autoloader → activation → plugins_loaded → Plugin/Admin init). All failure paths wrapped in try/catch with BootErrorCollector. No circular dependencies or missing imports in critical path.

### Formatting Sweep — ErrorHandling/*.php ✅ (2026-02-22)
All 4 files (BootErrorCollector, ErrorResponse, FatalErrorHandler, FrameBuilder) verified compliant.

### Formatting Sweep — Database/*.php ✅ (2026-02-22)
Fixed R12 violations (empty line after opening class brace) in Database.php, Orm.php, RootDb.php.

### Formatting Sweep — All Traits Directories ✅ (2026-02-21)
All 6 PHP trait directories fully swept for R4, R5, R9, R10, R12 violations.

### PHP–Go Consistency Audit ✅
All 5 phases complete. Route registration gaps fixed, endpoint parity achieved, HTTP status enum migration done.

### PascalCase Enum Labels — Cross-System ✅
24 PHP enums + 10 Go enums migrated. Settings migration helper created. DB V12/V14 migrations done.

### PascalCase Database Schema ✅
All 5 phases complete. Go SplitDB + E2E tables migrated. PHP Plugin SQLite V13 migration created.

### Go Backend Strict Guidelines ✅
Phases 1–3 + Phase E complete.

### Backend Standards Compliance ✅
Phases A–E complete.

### Template Magic String Elimination (Phase 7) ✅
Phases 7A–7G complete.

---

## Pending (Not Yet Started)

### PHP–Go Consistency Audit — Phase 1: Constant Deduplication
- Remove `Database::DEFAULT_LIMIT` / `MAX_LIMIT`, replace with `PaginationConfigType`
- Replace all hardcoded `$limit = 50` and `$perPage = 50`

### Phase 5: Licensing System Architecture
- License server + WP plugin client (8–10 tasks)

### Go Phase 4: Positive Logic & Boolean Standards
### Go Phase 5: Code Organization Standards
### Go Phase 6: CI Lint Scripts & Integration

### PascalCase Cross-System Remaining
- Phase 2.3: PHP hardcoded string comparisons audit
- Phase 2.4: WordPress database stored values upgrade routine
- Phase 3: TypeScript frontend enum value updates
- Phase 4: Spec documentation updates

### Template Phase 7D: admin-errors.php
- LogLevelType casing mismatch (`'ERROR'` stored vs `'Error'` enum)

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
4. **Licensing Build vs Buy**: Custom Go backend vs Keygen.sh vs LemonSqueezy?

---

*Master plan details in `plan.md`. Formatting sweep tracked in `.lovable/plans/rule-10-sweep.md`. Deep scan findings in `.lovable/memory/workflow/09-deep-scan-boot-chain.md`.*
