# Active & Future Phases

**Updated: 2026-02-21**

---

## Current Status: Formatting Sweep In Progress 🔄

All major architectural work (enum migration, PascalCase DB schema, error architecture, boot diagnostics, settings migration) is complete. Current focus is formatting compliance sweep across PHP trait files.

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
| Database/*.php | 🔵 Pending | — |
| ErrorHandling/*.php | 🔵 Pending | — |
| Core/*.php | 🔵 Pending | — |
| Templates/*.php | 🔵 Pending | — |
| Root files | 🔵 Pending | — |

---

## Completed Tracks (Recent — 2026-02-21)

### Formatting Sweep — All Traits Directories ✅
All 6 PHP trait directories (`Snapshot/`, `Database/`, `Admin/`, `Logging/`, `Agent/`, `Helpers/`) fully swept for R4, R5, R9, R10, R12 violations.

### PHP–Go Consistency Audit ✅
All 5 phases complete. Route registration gaps fixed, endpoint parity achieved, HTTP status enum migration done.

### PascalCase Enum Labels — Cross-System ✅
24 PHP enums + 10 Go enums migrated to PascalCase values. Settings migration helper created. DB V12/V14 migrations handle stored value updates.

### PascalCase Database Schema ✅
All 5 phases complete. Go SplitDB + E2E tables migrated. PHP Plugin SQLite V13 migration created. Root DB schema updated with backward compat via `RootDbCompatTrait`.

### Go Backend Strict Guidelines ✅
Phases 1–3 complete (ErrorCode typed alias, lint scripts, 3 new domain enums, magic string migration). Phase E raw error audit complete across all Go files.

### Backend Standards Compliance ✅
Phases A–E complete (spec updates, byte-based enum migration, config refactoring, AppError JSON, raw error audit).

### Template Magic String Elimination (Phase 7) ✅
Phases 7A–7G complete. All 5 admin templates cleaned. `SettingsMigrationHelper` created. `LogColumnType` enum created for admin-logs.php.

---

## Pending (Not Yet Started)

### PHP–Go Consistency Audit — Phase 1: Constant Deduplication
- Remove `Database::DEFAULT_LIMIT` / `MAX_LIMIT`, replace with `PaginationConfigType`
- Replace all hardcoded `$limit = 50` and `$perPage = 50`
- **Note**: PHP default params can't use enum values — documented as PHP constraint

### Phase 5: Licensing System Architecture
- License server + WP plugin client
- Full architecture documented in `plan.md`
- Estimated: 8–10 tasks

### Go Phase 4: Positive Logic & Boolean Standards
- Positive boolean naming (`IsValid` not `IsNotValid`)
- Negation elimination patterns
- Estimated: 2 tasks

### Go Phase 5: Code Organization Standards
- Package restructuring
- File naming conventions enforcement
- Estimated: 3 tasks

### Go Phase 6: CI Lint Scripts & Integration
- Complete lint script suite
- CI pipeline integration
- Estimated: 2 tasks

### PascalCase Cross-System Remaining
- Phase 2.3: PHP hardcoded string comparisons audit
- Phase 2.4: WordPress database stored values upgrade routine
- Phase 3: TypeScript frontend enum value updates
- Phase 4: Spec documentation updates

### Template Phase 7D: admin-errors.php
- LogLevelType casing mismatch (`'ERROR'` stored vs `'Error'` enum)
- FQCN imports cleanup

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
4. **Licensing Build vs Buy**: Custom Go backend vs Keygen.sh vs LemonSqueezy?

---

*Master plan details in `plan.md`. Formatting sweep tracked in `.lovable/plans/rule-10-sweep.md`.*
