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

---

## Pending Work

### 1. Spec Updates (Phase K1)

Update specs to match current codebase conventions.

**Files:**
- `spec/04-php-standards/README.md` — Fix method naming (snake_case → camelCase), enum Type suffix, file naming examples, code examples
- `spec/04-php-standards/enums.md` — Fix camelCase in examples, update PathConst section, ErrorChecker examples

**Source:** plan.md (previous), naming-convention-refactor-plan.md

---

### 2. PathConst → PathType Migration (Phase K2)

Replace unused `PathConst.php` (final class) with domain-specific backed enums.

**Decision needed:** Single `PathType` enum vs. split into 4 (`PathSubdirType`, `PathDatabaseType`, `PathLogFileType`, `PathConfigType`).

**Tasks:**
1. Create new enum(s) in `includes/Enums/`
2. Add typed accessors to `RiseupPathUtils`
3. Migrate callers from legacy `define()` constants → new enum values
4. Remove legacy path constants from `constants.php`
5. Remove `PathConst.php`

**Source:** plan.md (previous)

---

### 3. PathUtils snake_case → camelCase (Phase K3)

**Files:** `PathUtilsFileTrait`, `PathUtilsDirTrait`

**Critical bug:** `PathUtilsFileTrait` calls `self::safe_log()` but `PathUtilsCoreTrait` defines `safeLog()`. Fix this mismatch.

**Tasks:**
1. Fix `safe_log` → `safeLog` call mismatch
2. Rename all snake_case methods in PathUtils traits to camelCase
3. Update all callers

**Source:** plan.md (previous)

---

### 4. EndpointType route() Helper (Phase K4)

Add `route(): string` method to `EndpointType` enum returning `'/' . $this->value`.

**Files affected:**
- `includes/Enums/EndpointType.php`
- `includes/Traits/Route/RouteRegistrationTrait.php`
- `includes/Traits/Plugin/PluginRouteRegistrationTrait.php`
- `includes/Traits/Snapshot/SnapshotRouteRegistrationTrait.php`

**Source:** plan.md (previous)

---

### 5. Enum Internal Methods → camelCase (Phase K5)

- `UploadSourceType`: `valid_values()` → `validValues()`, `is_valid()` → `isValid()`
- `HookType`: `ajax_nopriv()` → `ajaxNopriv()`
- Update all callers

**Source:** plan.md (previous)

---

### 6. constants.php Enum Migration (J2–J7)

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

### 7. Naming Convention Remaining Phases

From `02-naming-convention-refactor-plan.md` — Phase 1 (enums) is done. Remaining:

| Phase | Description | Status |
|-------|-------------|--------|
| 2 | Constants: Remove `RISEUP_` prefix | ✅ Done (via constant migration plan) |
| 3 | Class names: Underscore → PascalCase | Pending |
| 5 | Hook & path compliance: remaining string literals | Pending |
| 6 | Cleanup & final validation | Pending |

**Note:** Phase 4 (method names → snake_case) is **superseded** by camelCase migration.

**Source:** `02-naming-convention-refactor-plan.md`

---

### 8. Spec & Architecture Documentation

| Item | Status |
|------|--------|
| Create `spec/01-coding-guidelines/function-naming.md` | Pending |
| Create `spec/01-coding-guidelines/strict-typing.md` | Pending |
| Update cross-references in spec READMEs | Pending |

**Source:** plan.md (previous)

---

### 9. Go Backend Type-Safety Remediation

Replace ~1,800 instances of `map[string]interface{}` and ~1,000 instances of `interface{}` with typed structs/aliases per GE pattern.

**Source:** memory (type-safety-remediation)

---

### 10. PHP Log Level Magic String Migration

643 occurrences of log level magic strings identified during Feature H audit. Migrate to `LogLevelType` enum values.

**Source:** plan README

---

### 11. HTTP Method Magic String Migration

Migrate remaining HTTP method string literals to `HttpMethodType` enum values.

**Source:** plan README

---

## Next Task Selection

→ **Phase K3** (PathUtils snake_case fix) — Contains a critical bug (`safe_log` → `safeLog` mismatch) that should be fixed first.  
→ **Phase K1** (Spec updates) — No code changes, just documentation alignment.

---

*Consolidated backlog v1.0.0 — 2026-02-14*
