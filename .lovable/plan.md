# Plan: DRY Refactoring — Phase-by-Phase

> Audit date: 2026-02-09  
> Goal: Eliminate duplication, improve maintainability across Go backend, React frontend, and PHP WordPress plugin — without breaking anything.

---

## Status Summary

| Phase | Layer | Status | Description |
|-------|-------|--------|-------------|
| 1 | Go | ✅ Done | Uploader lifecycle method dedup + stdlib usage |
| 2 | Go | ✅ Done | Envelope unwrap helper + PHP stack extraction |
| 3 | Frontend | ✅ Done | API error diagnostic context dedup |
| 4 | Frontend | ✅ Done | Error store capture dedup (`buildCapturedError`) |
| 5 | Frontend | ✅ Done | api.ts split into `src/lib/api/` modules |
| 6 | Frontend | ✅ Done | `useApiQuery` factory hook |
| 7 | PHP | ✅ Done | Snapshot class factory (`RiseupSnapshotFactory`) |
| 8 | PHP | 📋 Pending | Logger context consolidation (`prepare_context`) |
| 9 | Frontend | 📋 Pending | GlobalErrorModal decomposition |
| 10 | Cross | 📋 Pending | Envelope JSON schema alignment |

**Completed:** 7/10 phases  
**Remaining:** 3 phases (8–10)

---

## Pending Phases

### Phase 7 — PHP Plugin: Snapshot Class Initialization

**Problem:** `class-admin.php` has 5+ instances of requiring + instantiating `RiseupSnapshotDetector`. Similarly, `class-snapshot-scheduler.php` has 4 instances of constructing `RiseupSnapshotCleaner`.

**Fix:** Create `RiseupSnapshotFactory` class with lazy-loading singletons:
```php
class RiseupSnapshotFactory {
    public static function detector() { ... }
    public static function scheduler() { ... }
    public static function cleaner() { ... }
}
```

**Files:** `wp-plugins/riseup-asia-uploader/includes/class-admin.php`, `class-snapshot-scheduler.php`, new `class-snapshot-factory.php`  
**Risk:** Low — construction logic only.

---

### Phase 8 — PHP Plugin: Logger Auto-Context Consolidation

**Problem:** The logger's `warn()`, `error()`, `log_exception()`, and `log_at()` methods each independently call `enrich_context_with_request()` and build invocation chains. The enrichment pattern is duplicated across 4 methods.

**Fix:** Move all context enrichment into a single `prepare_context($context, $include_backtrace = false)` method that all log methods call.

**Files:** `wp-plugins/riseup-asia-uploader/includes/class-file-logger.php`  
**Risk:** Low — logging internals.

---

### Phase 9 — Frontend: GlobalErrorModal Decomposition

**Problem:** `GlobalErrorModal.tsx` is ~2,164 lines — the largest file in the frontend. Contains rendering logic for 8+ tabs, markdown report generation, copy logic, and session diagnostics.

**Fix:** Extract into focused sub-components:
- `ErrorModalOverview.tsx` — Summary/overview tab
- `ErrorModalStackTab.tsx` — Stack trace visualization
- `ErrorModalRequestTab.tsx` — Request chain visualization
- `ErrorModalTraversalTab.tsx` — Method traversal
- `ErrorModalReportGenerator.ts` — Markdown report logic (pure function)
- Keep `GlobalErrorModal.tsx` as shell with tabs + state

**Files:** `src/components/errors/GlobalErrorModal.tsx` → extract into `src/components/errors/` subdirectory  
**Risk:** Medium — complex UI, needs visual verification after split.

---

### Phase 10 — Cross-Stack: Envelope Type Alignment

**Problem:** The Universal Response Envelope types are defined independently in 3 places (Go, TypeScript, PHP). They can drift silently.

**Fix:** Add `spec/response-envelope/envelope.schema.json` (JSON Schema) as single source of truth. Add schema version comments in each implementation.

**Files:** `spec/response-envelope/`, Go/TS/PHP envelope files (add schema version comments)  
**Risk:** Very low — documentation + comments only.

---

## Recommended Execution Order

Phase 7 → Phase 8 → Phase 9 → Phase 10

Phase 9 is the largest remaining effort. Phase 5 (api.ts split) was recommended before Phase 9 and is now complete.

---

*Updated: 2026-02-09. Phases 1–6 completed. See `.lovable/plan/completed/01-dry-refactoring-phases-1-6.md` for history.*
