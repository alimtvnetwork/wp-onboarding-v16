# Lovable AI Memory

> **Purpose:** Guidelines for AI models to understand project structure and maintain consistent workflows.  
> **Updated:** 2026-02-02

---

## Folder Structure

```
.lovable/
├── README.md                    # This file - entry point for AI
├── plan.md                      # Active roadmap with prioritized backlog
└── memory/
    ├── 01-conventions.md        # Coding and file naming conventions
    ├── 01-workflow.md           # Task tracking guidelines
    ├── 02-project-context.md    # Project overview and status
    ├── 03-reliability-risk-report.md  # AI handoff reliability assessment
    ├── features/
    │   ├── e2e-testing.md       # E2E test framework details
    │   └── error-handling-and-debugging.md  # Error modal details
    └── suggestions/
        ├── 01-suggestions-tracker.md  # Active suggestions (consolidated)
        └── completed/
            └── 01-completed-suggestions.md  # Archived completed items
```

---

## Quick Reference

| Document | Purpose |
|----------|---------|
| `plan.md` | **START HERE** - Prioritized backlog, current status |
| `memory/02-project-context.md` | Project overview, what's done/pending |
| `memory/03-reliability-risk-report.md` | Risk assessment, success probability |
| `memory/suggestions/01-suggestions-tracker.md` | Open improvement suggestions |
| `memory/01-conventions.md` | File naming, coding standards |
| `memory/01-workflow.md` | Task tracking guidelines |

---

## For New AI Sessions

1. **Read `plan.md`** - Current priorities and next tasks
2. **Read `memory/02-project-context.md`** - Project status
3. **Read `memory/03-reliability-risk-report.md`** - Risk assessment
4. **Check open suggestions** in `memory/suggestions/01-suggestions-tracker.md`
5. **Follow conventions** in `memory/01-conventions.md`
6. **Ask user which task to implement**

---

## Current Project: WP Plugin Publish

**Status Summary:**
- ✅ 28 specification documents complete
- ✅ Phases 1, 2, 5 complete (Plugin, Sync, Git services)
- ✅ E2E Testing Framework complete (20 test cases)
- ✅ Error Detail Modal complete
- 📋 Phase 3 (Site Service) - **NEXT**
- 📋 Phase 4 (Publish Service) - Pending
- 📋 Phases 6-8 - Pending

**Reliability Score:** 82/100 (Good - Continue implementation)

**Priority:** Verify scaffolds compile, then implement Site Service (Phase 3)

---

## Open Suggestions Count

| Priority | Count |
|----------|-------|
| Critical | 2 (S-006, S-007 - verify builds) |
| High | 4 (S-001, S-004, S-008, S-009) |
| Medium | 2 (S-005, S-010) |
| **Total Open** | **8** |

---

## Spec Folder Overview

```
spec/wp-plugin-publish/
├── 00-overview.md               # Start here - master index
├── 01-backend/                  # 16 backend service specs
├── 02-frontend/                 # 6 frontend UI specs
├── 03-implementation/           # 6 implementation guides
├── 04-testing/                  # E2E test specification
├── 66-shared-constants.md       # Single source of truth (SSOT)
└── 99-consistency-report.md     # Cross-reference validation
```

---

## Critical Patterns

### 1. Split Database Architecture
- Root DB as registry, child DBs for history/cache
- See `memory/features/e2e-testing.md` for test data isolation

### 2. Error Codes
- E1xxx: Validation errors
- E2xxx: Database/Storage errors
- E3xxx: Network/API errors
- E4xxx: File system errors
- E5xxx: Sync/Publish errors
- E9xxx: Internal errors

### 3. File Watcher
- Event-driven, not polling
- Triggers on Git pull or manual refresh

### 4. Suggestions Workflow
- All suggestions consolidated in `01-suggestions-tracker.md`
- Status: open → in-progress → done
- Completed items archived in `completed/` folder

---

## Secondary Projects

| Project | Location | Status |
|---------|----------|--------|
| Plugins Onboard | `plugins-onboard/` | ✅ Complete (v1.0.5) |
| Spec Builder v3 | (Referenced in CONTEXT-FOR-AI.md) | 📝 Dormant |

---

*Updated 2026-02-02 with comprehensive project context and reliability assessment.*
