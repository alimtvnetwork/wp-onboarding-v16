# Plan: Future Work Roadmap

> **Updated:** 2026-02-09  
> **Purpose:** Prioritized backlog for AI handoff and implementation planning

---

## Status Summary

| Track | Status | Description |
|-------|--------|-------------|
| WP Plugin Publish (Core) | ✅ Done | All 14+ feature phases, 10 DRY phases, 18 suggestions |
| Plugins Onboard | ✅ Done | v1.0.5 — WordPress remote plugin management |
| Spec Builder v3 | 📝 Dormant | Referenced in CONTEXT-FOR-AI.md only |

---

## Phase 1: Open Questions (Decision Required)

These 3 items from the original implementation need user decisions before implementation:

### Q-001: Remote Plugin Backups Storage
- **Objective:** Decide whether remote plugin backups are stored on the WP site or downloaded locally
- **Dependencies:** Backup service, storage architecture
- **Expected outputs:** Updated `09-backup-service.md`, new storage endpoints if remote
- **Acceptance criteria:** Clear storage strategy documented and implemented

### Q-002: Bulk Quick Publish
- **Objective:** Add "Quick Publish Selected" for multiple plugins simultaneously  
- **Dependencies:** `useBulkQuickPublish.ts` hook (exists), UI flow design
- **Expected outputs:** Updated `27-quick-publish.md`, multi-select UI component
- **Acceptance criteria:** User can select multiple plugins and quick-publish in parallel

### Q-003: True Diff Comparison
- **Objective:** Compare local files with actual remote file contents (not just metadata)
- **Dependencies:** Sync service, WP file hash endpoint
- **Expected outputs:** Updated `07-sync-service.md`, accurate modified/deleted counts
- **Acceptance criteria:** Diff shows byte-accurate file differences

---

## Phase 2: Plugins Onboard — Future Enhancements (Backlog)

From `PRD.md` — these are enhancement ideas for the Plugins Onboard WordPress plugin:

| ID | Task | Priority | Dependencies | Expected Outputs |
|----|------|----------|--------------|------------------|
| PO-001 | Webhook notifications for plugin events | Medium | Audit logger | New webhook endpoint, event subscription UI |
| PO-002 | Multi-site support | Medium | Core architecture | Network-aware plugin management |
| PO-003 | Plugin dependency tracking | Low | Plugin manager | Dependency graph, conflict detection |
| PO-004 | Scheduled plugin updates | Medium | Cron system | Schedule UI, auto-update runner |
| PO-005 | API rate limit customization per application | Low | Rate limiter | Per-app config UI, admin override |
| PO-006 | Two-factor authentication for admin actions | Medium | OAuth system | TOTP/SMS integration |
| PO-007 | Plugin health monitoring | Medium | Remote API | Health check endpoints, dashboard |
| PO-008 | Automated rollback on activation failure | High | Snapshot system | Auto-detect failure, restore previous version |

---

## Phase 3: Spec Builder v3 (Dormant)

Referenced in `CONTEXT-FOR-AI.md` but no implementation exists. Architecture concepts documented:
- Split database system (4-tier SQLite)
- Seedable configuration pattern
- Resilient execution system

**Status:** Needs full spec creation before implementation.

---

## Next Task Selection

> **Pick one of these to implement next:**

### Ready Now (no blockers):
1. **PO-008:** Automated rollback on activation failure — highest-value Plugins Onboard enhancement
2. **Q-002:** Bulk Quick Publish UI — hook already exists, needs UI flow
3. **Any new feature** — all specs are complete, codebase is clean

### Needs Decision First:
4. **Q-001:** Remote backup storage strategy (ask user)
5. **Q-003:** True diff comparison scope (ask user)

### Needs Full Spec:
6. **Spec Builder v3** — requires PRD and spec creation from scratch

---

## Completed Tracks (Archive Reference)

All completed plans are archived in `.lovable/plan/completed/`:

| File | Content |
|------|---------|
| `01-dry-refactoring-phases-1-6.md` | DRY phases 1–6 |
| `02-dry-refactoring-phases-7-10.md` | DRY phases 7–10 |
| `03-error-diagnostics-v3.md` | Error diagnostics enhancement (6 phases) |
| `04-frontend-pages.md` | Frontend pages (15 phases) |
| `05-snapshot-backup-system.md` | Snapshot backup system (10 phases) |
| `06-feature-phases-1-14.md` | Feature phases 1–14 |
| `07-feature-phases-33-40.md` | Feature phases 33–40 |

---

*No active implementation phases. Ask user which task to implement next.*
