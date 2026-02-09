# Workflow Guidelines

> **Location:** `.lovable/memory/01-workflow.md`  
> **Updated:** 2026-02-09

---

## Task Tracking

### Plan File

The `plan.md` at `.lovable/plan.md` serves as the primary roadmap for the current active track (DRY Refactoring).

**Active plan:** `.lovable/plan.md` — DRY Refactoring Phases 7–10 pending  
**Active phases:** `.lovable/plan/active.md` — Status overview  
**Completed work:** `.lovable/plan/completed-phases-1-14.md`, `completed-phases-33-40.md`, `completed/01-dry-refactoring-phases-1-6.md`

**Statuses:**
- `todo` / `📋 Pending` - Not started
- `in-progress` / `🔄` - Currently being worked on
- `done` / `✅` - Completed

---

## Suggestions Tracking

All suggestions are tracked in a single file: `.lovable/memory/suggestions/01-suggestions-tracker.md`

Completed suggestions are summarized there and detailed in `.lovable/memory/suggestions/completed/01-completed-suggestions.md`.

**Current stats:** 7 open, 9 completed, 0 rejected.

---

## Session Handoff

When ending a session or handing off to another AI:

1. Update `.lovable/plan.md` with current progress
2. Update `.lovable/plan/active.md` with status changes
3. Update `01-suggestions-tracker.md` if suggestions changed
4. Note any blockers or decisions made

---

## Spec Reading Order

### For New AI Sessions

1. **Read `.lovable/memory/02-project-context.md`** — Project overview & current state
2. **Read `.lovable/plan/active.md`** — What's pending
3. **Read `.lovable/plan.md`** — Detailed pending phase descriptions
4. **Check `01-suggestions-tracker.md`** — Open suggestions
5. **Read relevant spec** for the feature being implemented

### Before Implementing

1. Read the specific spec file for the feature
2. Check memory files in `.lovable/memory/architecture/` for established patterns
3. Review related specs via cross-references

---

## Folder Structure

```
.lovable/
├── plan.md                          # Current active plan (DRY Refactoring)
├── plan/
│   ├── active.md                    # Status overview
│   ├── completed-phases-1-14.md     # Feature phases history
│   ├── completed-phases-33-40.md    # Feature phases history
│   ├── completed/
│   │   └── 01-dry-refactoring-phases-1-6.md
│   ├── technical-notes.md
│   └── README.md
├── memory/
│   ├── 01-conventions.md            # Coding conventions
│   ├── 01-workflow.md               # This file
│   ├── 02-project-context.md        # Project overview
│   ├── 03-reliability-risk-report.md
│   ├── PRD.md
│   ├── architecture/               # Established patterns
│   ├── features/                   # Feature documentation
│   ├── issues-fixed/               # Bug fix history
│   └── suggestions/
│       ├── 01-suggestions-tracker.md  # Single tracking file
│       └── completed/
└── specs/
```

---

*Follow these guidelines to maintain continuity across AI sessions.*
