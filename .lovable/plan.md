# Plan: Pending Work — Consolidated Backlog

> **Updated:** 2026-02-14  
> **Status:** PHP hardening complete — remaining items are Go backend + naming audit

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

### Verified Complete (2026-02-14)

| Item | Evidence |
|------|----------|
| K1: Spec updates (README.md + enums.md) | camelCase, Type suffix, file naming all correct |
| K2: PathConst → PathType migration | 4 domain enums exist, PathConst.php removed |
| K3: PathUtils safe_log bug fix | No safe_log/safeLog references remain |
| K4: EndpointType route() helper | route() method exists on EndpointType |
| K5: Enum internal methods → camelCase | validValues(), isValid(), ajaxNopriv() camelCase |
| Phase 3: Class names → PascalCase | No `class Riseup_` found anywhere |
| Spec docs (function-naming.md, strict-typing.md) | Both files exist in spec/01-coding-guidelines/ |
| HTTP method migration | All routes use HttpMethodType::X->value |
| Log level migration | 987 usages of LogLevelType::X->value |
| J2-J5: Enum types created | 12 new enum files created, bootstrap + constants.php updated |
| J6-J7: define() alias caller migration | Zero STATUS_*, SNAPSHOT_STATUS_*, SNAPSHOT_PROVIDER_*, TRIGGERED_BY_* callers remain |
| J8: CRON_SNAPSHOT_* → HookType enum | 6 cases added to HookType, all callers migrated, defines removed |
| J9: SNAPSHOT_SCOPE/FREQ/PROVIDER/STATUS → enums | All callers migrated across 9 files, zero remaining define() callers |
| define() alias removal | Zero enum-related define() aliases in constants.php |
| PHP 8.2+ baseline | Both plugins updated to require PHP 8.2 minimum |
| Boot sequence enum fix | ResponseMessageType, HttpStatusType, SnapshotErrorType added to require_once in main plugin file |
| ABSPATH guard audit | 13 trait files missing `if (!defined('ABSPATH')) exit;` — all fixed |
| Cross-language ResponseMessageType | TypeScript `ResponseMessageType` const added to `src/lib/constants.ts` mirroring Go/PHP |

---

## Pending Work

### 1. Go Backend Type-Safety Remediation

Replace ~2,680 instances of `interface{}` across 58 Go files with typed structs/aliases per GE pattern. This is the largest remaining item and should be done in domain-scoped waves.

**Completed phases (2026-02-14):**
- Phase 1: Concrete typed structs ✅
- Phase 2: Generic envelope + broadcast ✅
- Phase 3: WithContext migration ✅
- Phase 4: Typed broadcast events ✅
- Phase 5: interface{} → any cleanup ✅

**Remaining:**
- Phase 6: Test files cleanup (optional — `envelope_test.go`)
- Phase 7: Service return types (`version.GetVersions` / `GetVersion` → typed)

---

### 2. Naming Convention — Phase 5 & 6

- Phase 5: Hook & path compliance — audit remaining string literals
- Phase 6: Cleanup & final validation

---

## Non-Enum Constants (Stay in constants.php)

These remain as plain `define()` — they don't qualify as enums:

| Group | Constants | Reason |
|-------|-----------|--------|
| Plugin identity | PLUGIN_VERSION, PLUGIN_SLUG, PLUGIN_NAME, MIN_WP/PHP_VERSION | Config values |
| API config | API_NAMESPACE, API_VERSION, API_FULL_NAMESPACE, LEGACY_NAMESPACE | Compose values |
| Path identity | UPLOADS_SUBDIR | Plugin slug |
| Database | DB_WAL_MODE | Boolean flag |
| Messages | MSG_* (14 constants) | Display strings |
| HTTP codes | HTTP_OK, HTTP_BAD_REQUEST, etc. (7 constants) | Numeric codes |
| Pagination | DEFAULT_LIMIT, MAX_LIMIT | Numeric config |
| Snapshot config | SNAPSHOT_BATCH_SIZE, MAX_SIZE_MB, RETENTION defaults, WORKER_POOL, STUCK_HOURS | Numeric config |
| Cron hooks | CRON_SNAPSHOT_* (6 constants) | WordPress-registered names |
| Error codes | ERR_* (10 constants) | String identifiers |
| Options | OPTION_SNAPSHOT_SETTINGS, OPTION_LOG_RETRIEVAL | WordPress option keys |
| Misc | IGNORE_FILENAME, LOG_PREFIX, UPDATE_CACHE_DAYS_DEFAULT, LOG_RETRIEVAL_MAX_LINES | Config values |

---

## Next Task Selection

→ **Go Phase 7:** Type `version.GetVersions` / `GetVersion` return values  
→ **Naming Phase 5:** Audit hook & path string literals  

---

*Consolidated backlog v1.3.0 — 2026-02-14*
