# Workflow Guidelines

> **Location:** `.lovable/memory/01-workflow.md`  
> **Updated:** 2026-04-01

---

## Task Tracking

### Plan Files

The root `plan.md` is the **master roadmap and backlog** with prioritized phases (A–M), next task selection, and suggestion cross-references. All completed plans are archived in `.lovable/plan/completed/`.

**Status overview:** `.lovable/plan/active.md` — Dashboard UX fix complete, H-3 scaffolded  
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

**Current stats:** 9 open, 57 completed, 1 N/A, 1 rejected (68 total). Next ID: **S-055**.

**Convention:**
- New suggestions get sequential ID (next: S-055)
- Move to completed table when done
- Update statistics count
- All in one file — do not create separate files per suggestion

---

## Specifications Index

All specs are indexed at `spec/readme.md`. Key spec folders:
- `spec/00-ai-handoff-complete-context.md` — Complete AI handoff document (706 lines)
- `spec/01-app/` — Application specs, formatting rules
- `spec/02-app-issues/` — 42 issue write-ups with root cause + prevention
- `spec/04-coding-guidelines/` — DRY principles
- `spec/05-typescript-standards/` — TypeScript standards
- `spec/06-golang-standards/` — Go standards
- `spec/07-php-standards/` — PHP standards
- `spec/08-error-manage/` — Error handling, modal, envelope
- `spec/09-wordpress/` — All WordPress plugin specs (features, dev, publishing, QUpload, cloud storage, log retrieval)
- `spec/10-features/` — Feature specifications
- `spec/12-feedback-report-feature/` — Feedback report
- `spec/13-powershell-integration/` — PowerShell runner
- `spec/14-e2-activity-feed/` — Activity audit log
- `spec/15-generic-enforce/` — Cross-language type enforcement
- `spec/16-user-management/` — User management (4 spec files)
- `spec/17-parallel-powershell-scripts/` — Parallel PowerShell scripts

---

## Critical Anti-Patterns (Do NOT Repeat)

1. **Never use `is_array()`, `is_string()`, etc. in PHP** — blocked by QUpload validator. Use `gettype($var) === PhpNativeType::*->value`
2. **Never use `array()` constructor** — use `[]` short syntax
3. **Never use magic strings** with `gettype()` — use `PhpNativeType` enum
4. **Never hard-depend on helper classes in response paths** — use `class_exists()` guard
5. **Never log `$e->getMessage()` alone** — stack trace is most important
6. **Never use `fmt.Errorf` in Go** for errors leaving service boundary — use `apperror.Wrap()`
7. **Never hardcode `localhost:8080`** in React — use `resolveApiUrl()`
8. **Never call WordPress functions in PHP constructors** — use lazy init
9. **Never use raw comparisons in ternary conditions** — extract to named boolean
10. **Never use negations (`!`) in PHP if statements** — use positive boolean helpers
11. **Never use `any`/`interface{}` in production Go** — use typed alternatives
12. **Never create silent catch blocks** — every catch must log with trace
13. **Boot-time catch blocks must re-throw** — use `logCriticalException()` or `errorLogAndThrow()`
14. **Never modify `.release/` folder** — read-only

---

## Session Handoff

When ending a session or handing off to another AI:

1. Update root `plan.md` with status changes
2. Update `01-suggestions-tracker.md` if suggestions changed
3. Note any blockers or decisions made
4. Update `02-project-context.md` if major features added
5. Update `pending-tasks.md` if task status changed
6. Follow post-fix issue writeup workflow for every bug fix

---

## Spec Reading Order

### For New AI Sessions

1. **Read `spec/00-ai-handoff-complete-context.md`** — Complete project knowledge transfer
2. **Read root `plan.md`** — Master roadmap with next task selection
3. **Read `.lovable/plan/active.md`** — Current status and completed phases
4. **Read `.lovable/memory/02-project-context.md`** — Project overview & architecture
5. **Read `spec/readme.md`** — Spec index
6. **Check `01-suggestions-tracker.md`** — 9 open suggestions
7. **Read `03-reliability-risk-report.md`** — Score: 93/100
8. **Read `pending-tasks.md`** — Deployment blockers
9. **Ask user** what to implement next

### Before Implementing

1. Read the specific spec file for the feature
2. Check memory files in `.lovable/memory/architecture/` for established patterns
3. Review coding standards in `.lovable/memory/coding-standards/`
4. Review related specs via cross-references

---

## Folder Structure

```
.lovable/
├── README.md                          # Entry point for AI
├── plan.md                            # DRY refactoring plan (legacy)
├── plan/
│   ├── README.md                      # Plan index
│   ├── active.md                      # Current sprint status
│   ├── technical-notes.md             # Architecture decisions
│   └── completed/                     # 7 archived plan files
├── memory/
│   ├── 01-conventions.md              # Coding conventions
│   ├── 01-workflow.md                 # This file
│   ├── 02-project-context.md          # Project overview
│   ├── 03-reliability-risk-report.md  # Reliability: 93/100
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

plan.md (repo root)                    # Master roadmap — Phases A–M, next task selection

spec/
├── 00-ai-handoff-complete-context.md  # Complete AI handoff (706 lines)
├── readme.md                          # Spec index (start here)
├── 01-app/ through 17-*/              # 17 spec folders
├── dry-refactoring-summary.md         # 10-phase summary
└── licensing-strategy.md              # Licensing plan
```

---

*Follow these guidelines to maintain continuity across AI sessions.*
