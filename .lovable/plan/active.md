# Active & Future Phases

**Updated: 2026-02-09**

---

## Current Status: All Major Tracks Complete ✅

No active implementation phases. All planned work is done.

---

## Completed Tracks

### PHP Plugin v1.36.1 — Circular Dependency Fix ✅ (2026-02-09)
Fixed fatal `Class "RiseupFileLogger" not found` circular dependency during bootstrap. Added `$bootstrapping` guard to `RiseupPathUtils`, native `error_log()` fallbacks, and decoupled logger from path utils during init. Fixed Go `buildWPClient` compilation error in `CheckRemotePluginExists`.

### Pre-flight Plugin Guard (S-010) ✅ (2026-02-09)
PHP `/plugins/exists` endpoint, Go proxy service method, React async frontend guard with server-side verification.

### 10-Phase DRY Refactoring ✅ (2026-02-09)
Go backend dedup, frontend error store/API client/hooks consolidation, PHP snapshot factory, PHP logger consolidation, GlobalErrorModal decomposition, cross-stack JSON Schema alignment.  
→ See: `.lovable/plan/completed/01-dry-refactoring-phases-1-6.md`, `.lovable/plan/completed/02-dry-refactoring-phases-7-10.md`

### PHP Plugin Refactoring Phases 1–5 ✅ (2026-02-07)
Boolean helpers, init helpers, path utils, dependency loader, coding guidelines.  
→ See: `.lovable/plan/completed-phases-1-14.md`

### Go Backend Phases 6–10 ✅ (2026-02-07)
Handler modularization, CRUD factory, service registry unification, config standardization, E2E tests.

### Feature Phases 1–14, 33–40 ✅ (2026-02-05 to 2026-02-06)
Session logging, quick publish, remote plugins, file browser, version tracking, auto-update, multi-site orchestration, publish retry/queue/scheduler/rollback, history dashboard, site health monitor.  
→ See: `.lovable/plan/completed-phases-1-14.md`, `.lovable/plan/completed-phases-33-40.md`

---

## Open Suggestions (from suggestions tracker)

| ID | Title | Priority |
|----|-------|----------|
| S-001 | Add WordPress API Error Examples to spec | high |
| S-004 | Document Error Recovery for Partial Publish | high |

→ See: `.lovable/memory/suggestions/01-suggestions-tracker.md`

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?

---

## Recent Bug Fixes (2026-02-09)

| Fix | Description |
|-----|-------------|
| PHP circular dependency | `RiseupPathUtils` → `RiseupFileLogger` loop during bootstrap caused fatal crash. Fixed with `$bootstrapping` guard and native fallbacks. Plugin v1.36.1. |
| Go `buildWPClient` undefined | `CheckRemotePluginExists` referenced non-existent method. Replaced with standard credential decrypt + `wpClientFactory` pattern. |

---

*No pending implementation phases. Next work should come from open suggestions or new feature requests.*
