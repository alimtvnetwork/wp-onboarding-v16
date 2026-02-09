# Active & Future Phases

**Updated: 2026-02-09**

---

## Current Track: DRY Refactoring (Phases 7–10 remaining)

| Phase | Layer | Status | Description |
|-------|-------|--------|-------------|
| 7 | PHP | 📋 Pending | `RiseupSnapshotFactory` — lazy singleton construction |
| 8 | PHP | 📋 Pending | Logger `prepare_context()` — consolidate 4 methods |
| 9 | Frontend | 📋 Pending | GlobalErrorModal decomposition into sub-components |
| 10 | Cross | 📋 Pending | Envelope JSON schema as single source of truth |

**See:** `.lovable/plan.md` for full details on each pending phase.

---

## Previously Completed Tracks

### DRY Refactoring Phases 1–6 ✅ (2026-02-09)
Go backend dedup, frontend error store/API client/hooks consolidation.  
→ See: `.lovable/plan/completed/01-dry-refactoring-phases-1-6.md`

### PHP Plugin Refactoring Phases 1–5 ✅ (2026-02-07)
Boolean helpers, init helpers, path utils, dependency loader, coding guidelines.  
→ See: `.lovable/plan/completed-phases-1-14.md` (items within)

### Go Backend Phases 6–10 ✅ (2026-02-07)
Handler modularization, CRUD factory, service registry unification, config standardization, E2E tests.  
→ See: `.lovable/plan/active.md` (previous version archived)

### Feature Phases 1–14, 33–40 ✅ (2026-02-05 to 2026-02-06)
Session logging, quick publish, remote plugins, file browser, version tracking, auto-update, multi-site orchestration, publish retry/queue/scheduler/rollback, history dashboard, site health monitor.  
→ See: `.lovable/plan/completed-phases-1-14.md`, `.lovable/plan/completed-phases-33-40.md`

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?

---

*Next recommended: Phase 7 (PHP snapshot factory) or Phase 9 (GlobalErrorModal decomposition).*
