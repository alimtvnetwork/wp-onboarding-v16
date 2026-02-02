# Lovable AI Memory

> **Purpose:** Guidelines for AI models to understand project structure and maintain consistent workflows.  
> **Updated:** 2026-02-02

---

## Folder Structure

```
.lovable/
├── README.md                    # This file - entry point for AI
├── memory/
│   ├── 01-conventions.md        # Coding and file naming conventions
│   ├── 01-workflow.md           # Task tracking guidelines
│   ├── 02-project-context.md    # Project overview and status
│   ├── 03-reliability-risk-report.md  # AI handoff reliability assessment
│   ├── features/
│   │   ├── e2e-testing.md       # E2E test framework details
│   │   └── error-handling-and-debugging.md  # Error modal details
│   └── suggestions/
│       ├── 01-suggestions-tracker.md  # Active suggestions
│       └── completed/
│           └── 01-completed-suggestions.md  # Archived completed items
└── plan.md                      # Active roadmap with prioritized backlog
```

---

## Quick Reference

| Document | Purpose |
|----------|---------|
| `plan.md` | **START HERE** - Prioritized backlog, current status |
| `memory/02-project-context.md` | Project overview, what's done/pending |
| `memory/suggestions/01-suggestions-tracker.md` | Open improvement suggestions |
| `memory/01-conventions.md` | File naming, coding standards |
| `memory/01-workflow.md` | Task tracking guidelines |

---

## For New AI Sessions

1. **Read `plan.md`** - Current priorities and next tasks
2. **Read `memory/02-project-context.md`** - Project status
3. **Check open suggestions** in `memory/suggestions/01-suggestions-tracker.md`
4. **Follow conventions** in `memory/01-conventions.md`

---

## Current Project: WP Plugin Publish

**Status Summary:**
- ✅ Phases 1, 2, 5 complete (Plugin, Sync, Git services)
- ✅ E2E Testing Framework complete
- ✅ Error Detail Modal complete
- 📋 Phase 3 (Site Service) - NEXT
- 📋 Phase 4 (Publish Service) - Pending
- 📋 Phases 6-8 - Pending

**Priority:** Implement Site Service (Phase 3)

---

## Spec Folder Overview

```
spec/wp-plugin-publish/
├── 00-overview.md               # Start here
├── 01-backend/                  # 16 backend specs
├── 02-frontend/                 # 6 frontend specs
├── 03-implementation/           # 6 implementation guides
├── 04-testing/                  # E2E test spec
└── 66-shared-constants.md       # Single source of truth
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

### 3. File Watcher
- Event-driven, not polling
- Triggers on Git pull or manual refresh

---

*Updated 2026-02-02 with comprehensive project context.*
