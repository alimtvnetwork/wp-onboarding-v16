# Workflow Guidelines

> **Location:** `.lovable/memory/01-workflow.md`  
> **Updated:** 2026-02-24

---

## Task Tracking

### Plan Files

The `.lovable/plan.md` served as the DRY refactoring roadmap (complete). The root `plan.md` is the future work roadmap (Deliverable 3, created 2026-02-24). All completed plans are archived in `.lovable/plan/completed/`.

**Status overview:** `.lovable/plan/active.md` — All tracks complete, 0 open suggestions  

**Completed plans (in `.lovable/plan/completed/`):**
- `01-dry-refactoring-phases-1-6.md` — DRY phases 1–6
- `02-dry-refactoring-phases-7-10.md` — DRY phases 7–10
- `03-error-diagnostics-v3.md` — Error diagnostics enhancement (6 phases)
- `04-frontend-pages.md` — Frontend pages (15 phases)
- `05-snapshot-backup-system.md` — Snapshot backup system (10 phases)
- `06-feature-phases-1-14.md` — Feature phases 1–14
- `07-feature-phases-33-40.md` — Feature phases 33–40

**Statuses:**
- `todo` / `📋 Pending` - Not started
- `in-progress` / `🔄` - Currently being worked on
- `done` / `✅` - Completed

---

## Suggestions Tracking

All suggestions are tracked in a single file: `.lovable/memory/suggestions/01-suggestions-tracker.md`

Completed suggestions are detailed in `.lovable/memory/suggestions/completed/01-completed-suggestions.md`.

**Current stats:** 0 open, 39 completed, 1 N/A, 0 rejected.

---

## Specifications Index

All specs are indexed at `spec/README.md`. Key spec folders:
- `spec/error-modal/` — Error Modal UI specification with visual diagrams
- `spec/upload-scripts/` — PowerShell upload scripts V1-V3
- `spec/coding-guidelines/` — DRY principles
- `spec/golang-standards/`, `spec/typescript-standards/`, `spec/php-standards/` — Language standards
- `spec/error-handling/` — Cross-stack error chain
- `spec/response-envelope/` — Universal Response Envelope JSON Schema
- `spec/powershell-integration/` — Build runner (generic, cross-project)

---

## Session Handoff

When ending a session or handing off to another AI:

1. Update `.lovable/plan/active.md` with status changes
2. Update `01-suggestions-tracker.md` if suggestions changed
3. Note any blockers or decisions made
4. Update `02-project-context.md` if major features added

---

## Spec Reading Order

### For New AI Sessions

1. **Read `.lovable/plan/active.md`** — Current status and open questions
2. **Read `.lovable/memory/02-project-context.md`** — Project overview & architecture
3. **Read `spec/README.md`** — Spec index
4. **Check `01-suggestions-tracker.md`** — Open suggestions (currently 0)
5. **Ask user** what to implement next

### Before Implementing

1. Read the specific spec file for the feature
2. Check memory files in `.lovable/memory/architecture/` for established patterns
3. Review coding standards: `spec/golang-standards/`, `spec/typescript-standards/`, `spec/php-standards/`
4. Review related specs via cross-references

---

## Folder Structure

```
.lovable/
├── README.md                          # Entry point for AI
├── plan.md                            # DRY Refactoring (complete)
├── plan/
│   ├── README.md                      # Plan index
│   ├── active.md                      # Status overview — all tracks complete
│   ├── technical-notes.md             # Root causes, architecture decisions
│   └── completed/                     # All completed plan files
│       ├── 01-dry-refactoring-phases-1-6.md
│       ├── 02-dry-refactoring-phases-7-10.md
│       ├── 03-error-diagnostics-v3.md
│       ├── 04-frontend-pages.md
│       ├── 05-snapshot-backup-system.md
│       ├── 06-feature-phases-1-14.md
│       └── 07-feature-phases-33-40.md
├── memory/
│   ├── 01-conventions.md              # Coding conventions
│   ├── 01-workflow.md                 # This file
│   ├── 02-project-context.md          # Project overview
│   ├── 03-reliability-risk-report.md  # Reliability: 95/100
│   ├── PRD.md                         # Plugins Onboard PRD
│   ├── architecture/                  # Established patterns
│   ├── features/                      # Feature documentation
│   ├── issues-fixed/                  # Bug fix history
│   └── suggestions/
│       ├── 01-suggestions-tracker.md  # Single tracking file (0 open, 18 completed)
│       └── completed/
│           └── 01-completed-suggestions.md
spec/
├── README.md                          # Spec index (start here)
├── coding-guidelines/                 # DRY principles
├── error-handling/                    # Cross-stack error chain
├── error-modal/                       # Error Modal UI spec with diagrams
├── golang-standards/                  # Go coding standards
├── typescript-standards/              # TypeScript coding standards
├── php-standards/                     # PHP coding standards
├── powershell-integration/            # Build runner (generic, cross-project)
├── upload-scripts/                    # Upload scripts V1-V3
├── response-envelope/                 # JSON Schema & samples
└── dry-refactoring-summary.md         # 10-phase summary
```

---

*Follow these guidelines to maintain continuity across AI sessions.*
