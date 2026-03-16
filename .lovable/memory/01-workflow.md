# Workflow Guidelines

> **Location:** `.lovable/memory/01-workflow.md`  
> **Updated:** 2026-03-17

---

## Task Tracking

### Plan Files

The `.lovable/plan.md` is the **master roadmap and backlog** with prioritized phases (A–H), next task selection, and suggestion cross-references. All completed plans are archived in `.lovable/plan/completed/`.

**Status overview:** `.lovable/plan/active.md` — Cloud storage complete, pending backend integration  
**Pending tasks:** `.lovable/memory/workflow/pending-tasks.md` — deployment blockers + medium/low priority items

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

Completed suggestion details are in `.lovable/memory/suggestions/completed/01-completed-suggestions.md`.

**Current stats:** 9 open, 57 completed, 1 N/A, 1 rejected (68 total).

**Convention:**
- New suggestions get sequential ID (next: S-055)
- Move to completed table when done
- Update statistics count
- All in one file — do not create separate files per suggestion

---

## Specifications Index

All specs are indexed at `spec/readme.md`. Key spec folders:
- `spec/01-app/` — Application specs, formatting rules
- `spec/02-app-issues/` — 31 issue write-ups with root cause + prevention
- `spec/03-coding-guidelines/` — DRY principles
- `spec/04-typescript-standards/` — TypeScript standards
- `spec/05-golang-standards/` — Go standards
- `spec/06-php-standards/` — PHP standards
- `spec/07-error-manage/` — Error handling, modal, envelope
- `spec/08-wordpress-plugin/` — WP companion plugin features
- `spec/09-wordpress-plugin-development/` — Plugin dev workflow
- `spec/10-wp-plugin-publish/` — Publishing pipeline
- `spec/11-upload-scripts/` — PowerShell upload scripts V1–V3
- `spec/12-powershell-integration/` — PowerShell runner
- `spec/13-e2-activity-feed/` — Activity audit log
- `spec/14-generic-enforce/` — Cross-language type enforcement
- `spec/15-qupload-plugin/` — QUpload plugin
- `spec/16-user-management/` — User management (4 spec files)
- `spec/17-cloud-storage-providers/` — Cloud storage (10 spec files)

---

## Session Handoff

When ending a session or handing off to another AI:

1. Update `.lovable/plan.md` with status changes
2. Update `01-suggestions-tracker.md` if suggestions changed
3. Note any blockers or decisions made
4. Update `02-project-context.md` if major features added
5. Update `pending-tasks.md` if task status changed

---

## Spec Reading Order

### For New AI Sessions

1. **Read `.lovable/plan.md`** — Master roadmap with next task selection
2. **Read `.lovable/plan/active.md`** — Current status and completed phases
3. **Read `.lovable/memory/02-project-context.md`** — Project overview & architecture
4. **Read `spec/readme.md`** — Spec index
5. **Check `01-suggestions-tracker.md`** — 9 open suggestions
6. **Read `03-reliability-risk-report.md`** — Score: 92/100, failure map included
7. **Read `pending-tasks.md`** — Deployment blockers
8. **Ask user** what to implement next

### Before Implementing

1. Read the specific spec file for the feature
2. Check memory files in `.lovable/memory/architecture/` for established patterns
3. Review coding standards: `spec/05-golang-standards/`, `spec/04-typescript-standards/`, `spec/06-php-standards/`
4. Review related specs via cross-references

---

## Folder Structure

```
.lovable/
├── README.md                          # Entry point for AI
├── plan.md                            # Master roadmap & backlog (Phases A–H)
├── plan/
│   ├── README.md                      # Plan index
│   ├── active.md                      # Status overview — cloud storage complete
│   ├── technical-notes.md             # Root causes, architecture decisions
│   └── completed/                     # All completed plan files (7 archives)
├── memory/
│   ├── 01-conventions.md              # Coding conventions
│   ├── 01-workflow.md                 # This file
│   ├── 02-project-context.md          # Project overview
│   ├── 03-reliability-risk-report.md  # Reliability: 92/100
│   ├── PRD.md                         # Plugins Onboard PRD
│   ├── architecture/                  # Established patterns (11 subdirs)
│   ├── coding-standards/              # Standards documentation
│   ├── features/                      # Feature documentation (17 files)
│   ├── issues-fixed/                  # Bug fix history (15 write-ups)
│   ├── issues/                        # Active issues (8 files)
│   ├── suggestions/
│   │   ├── 01-suggestions-tracker.md  # Single tracking file (9 open, 57 completed)
│   │   └── completed/
│   │       └── 01-completed-suggestions.md
│   └── workflow/
│       └── pending-tasks.md           # Deployment blockers + pending items
spec/
├── readme.md                          # Spec index (start here)
├── 01-app/ through 17-cloud-storage-providers/  # 17 spec folders
├── dry-refactoring-summary.md         # 10-phase summary
└── licensing-strategy.md              # Licensing plan
```

---

*Follow these guidelines to maintain continuity across AI sessions.*
