# Plan: Pending Work — Consolidated Backlog

> **Updated:** 2026-02-14  
> **Status:** Backlog — no active implementation

---

## Completed Work Summary

All completed plans are archived in `.lovable/memory/workflow/completed/`:

| # | Plan | Description |
|---|------|-------------|
| 02 | Golang Enum Migration | 8 typed string enums with IsEqual(), String(), domain helpers |
| 03 | File Size Remediation | Main plugin split from 5,604 → ~270 lines, 20 traits + 2 standalone files |
| 04 | Long Function Fix | Phases 1–16, ~100 functions refactored, ~170 helpers extracted |
| 05 | RISEUP_ Constant Migration | 3 files migrated, constants-compat.php removed |
| 06 | camelCase Method Migration | Phases 1–9, all core domains migrated |
| 07 | Nested-If Flattening | 8 phases, ~42 violations fixed |

Also completed (in `.lovable/plan/completed/`):
- DRY refactoring phases 1–10
- Error diagnostics v3
- Frontend pages (15 phases)
- Snapshot backup system (10 phases)
- Feature phases 1–14, 33–40

### Recently Verified as Complete (2026-02-14)

| Item | Evidence |
|------|----------|
| K1: Spec updates (README.md + enums.md) | camelCase methods, Type suffix, correct file naming all present |
| K2: PathConst → PathType migration | 4 domain enums exist, PathConst.php removed |
| K3: PathUtils safe_log bug fix | No safe_log/safeLog references remain in traits |
| K4: EndpointType route() helper | route() method exists on EndpointType |
| K5: Enum internal methods → camelCase | validValues(), isValid(), ajaxNopriv() all camelCase in code + spec |

---

## Pending Work

### 1. constants.php Enum Migration (J2–J7)

Migrate ~180 `define()` constants from `constants.php` into typed enums and const classes per the J1 audit.

| Phase | Target | Constants | Priority |
|-------|--------|-----------|----------|
| J2 | ActionType enum (42 cases) | 42 | HIGH |
| J3 | StatusType + PostStatusType enums | 5 | HIGH |
| J4a-c | Snapshot domain enums (6 enums) | 32 | MED |
| J5a | AgentStatusType + TriggerSourceType + SyncActionType | 10 | MED |
| J6a-d | Const classes (Endpoint, Table, Error, Http, Message, Default, Cron, Path, Plugin, Api, Option) | 110 | LOW |
| J7 | Remove migrated defines + backward-compat aliases | 9+ | FINAL |

**Source:** `03-j1-constants-audit.md`

---

### 2. Naming Convention — Remaining Phases

From `02-naming-convention-refactor-plan.md`:

| Phase | Description | Status |
|-------|-------------|--------|
| 3 | Class names: Underscore → PascalCase | Pending |
| 5 | Hook & path compliance: remaining string literals | Pending |
| 6 | Cleanup & final validation | Pending |

**Note:** Phase 1 (enums), Phase 2 (RISEUP_ prefix), Phase 4 (method naming) all complete.

---

### 3. Spec & Architecture Documentation

| Item | Status |
|------|--------|
| Create `spec/01-coding-guidelines/function-naming.md` | Pending |
| Create `spec/01-coding-guidelines/strict-typing.md` | Pending |
| Update cross-references in spec READMEs | Pending |

---

### 4. Go Backend Type-Safety Remediation

Replace ~1,800 instances of `map[string]interface{}` and ~1,000 instances of `interface{}` with typed structs/aliases per GE pattern.

---

### 5. PHP Log Level Magic String Migration

643 occurrences of log level magic strings identified during Feature H audit. Migrate to `LogLevelType` enum values.

---

### 6. HTTP Method Magic String Migration

Migrate remaining HTTP method string literals to `HttpMethodType` enum values.

---

## Next Task Selection

→ **J2** (ActionType enum migration — 42 constants, highest caller impact)  
→ **Phase 3** (Class names → PascalCase)  
→ **Spec documentation** (function-naming.md, strict-typing.md)

---

*Consolidated backlog v1.1.0 — 2026-02-14*
