# Workflow Guidelines

> **Location:** `.lovable/memory/01-workflow.md`  
> **Updated:** 2026-02-09

---

## Task Tracking

### Plan File

The `plan.md` at `.lovable/plan.md` serves as the primary roadmap. **All planned phases are now complete.**

**Active plan:** `.lovable/plan.md` — DRY Refactoring (10/10 phases complete)  
**Active status:** `.lovable/plan/active.md` — All tracks complete, 3 open suggestions remaining  
**Completed work:**
- `.lovable/plan/completed-phases-1-14.md` — Feature phases 1–14
- `.lovable/plan/completed-phases-33-40.md` — Feature phases 33–40
- `.lovable/plan/completed/01-dry-refactoring-phases-1-6.md` — DRY phases 1–6
- `.lovable/plan/completed/02-dry-refactoring-phases-7-10.md` — DRY phases 7–10

**Statuses:**
- `todo` / `📋 Pending` - Not started
- `in-progress` / `🔄` - Currently being worked on
- `done` / `✅` - Completed

---

## Suggestions Tracking

All suggestions are tracked in a single file: `.lovable/memory/suggestions/01-suggestions-tracker.md`

Completed suggestions are summarized there and detailed in `.lovable/memory/suggestions/completed/01-completed-suggestions.md`.

**Current stats:** 3 open, 13 completed, 0 rejected.

---

## Specifications Index

All specs are indexed at `spec/README.md`. Key spec folders:
- `spec/error-modal/` — Error Modal UI specification with visual diagrams
- `spec/upload-scripts/` — PowerShell upload scripts V1-V3
- `spec/coding-guidelines/` — DRY principles
- `spec/golang-standards/`, `spec/typescript-standards/`, `spec/php-standards/` — Language standards
- `spec/error-handling/` — Cross-stack error chain
- `spec/response-envelope/` — Universal Response Envelope JSON Schema
- `spec/powershell-integration/` — Build runner

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
3. **Read `spec/README.md`** — Spec index
4. **Check `01-suggestions-tracker.md`** — Open suggestions
5. **Read relevant spec** for the feature being implemented

### Before Implementing

1. Read the specific spec file for the feature
2. Check memory files in `.lovable/memory/architecture/` for established patterns
3. Review coding standards: `spec/golang-standards/`, `spec/typescript-standards/`, `spec/php-standards/`
4. Review related specs via cross-references

---

## Folder Structure

```
.lovable/
├── plan.md                          # DRY Refactoring (complete)
├── plan/
│   ├── active.md                    # Status overview — all tracks complete
│   ├── completed-phases-1-14.md     # Feature phases history
│   ├── completed-phases-33-40.md    # Feature phases history
│   ├── completed/
│   │   ├── 01-dry-refactoring-phases-1-6.md
│   │   └── 02-dry-refactoring-phases-7-10.md
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
│       ├── 01-suggestions-tracker.md  # Single tracking file (3 open, 13 completed)
│       └── completed/
spec/
├── README.md                        # Spec index (start here)
├── coding-guidelines/               # DRY principles
├── error-handling/                  # Cross-stack error chain
├── error-modal/                     # Error Modal UI spec with diagrams
├── golang-standards/                # Go coding standards
├── typescript-standards/            # TypeScript coding standards
├── php-standards/                   # PHP coding standards
├── powershell-integration/          # Build runner
├── upload-scripts/                  # Upload scripts V1-V3
├── response-envelope/               # JSON Schema & samples
└── dry-refactoring-summary.md       # 10-phase summary
```

---

*Follow these guidelines to maintain continuity across AI sessions.*
