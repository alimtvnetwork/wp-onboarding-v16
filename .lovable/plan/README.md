# WP Plugin Publish - Plan Index

**Updated: 2026-02-14**

## Structure

| File | Description |
|------|-------------|
| `../plan.md` | Consolidated pending backlog — 11 pending items |
| `active.md` | Status overview — all major tracks complete |
| `technical-notes.md` | Root cause analyses, architecture decisions |
| `completed/` | All completed plan files archived |

## Completed Plans (in `completed/`)

| File | Description |
|------|-------------|
| `01-dry-refactoring-phases-1-6.md` | DRY refactoring phases 1–6 |
| `02-dry-refactoring-phases-7-10.md` | DRY refactoring phases 7–10 |
| `03-error-diagnostics-v3.md` | Error diagnostics v3 (6 phases) |
| `04-frontend-pages.md` | Frontend pages (15 phases) |
| `05-snapshot-backup-system.md` | Snapshot backup system (10 phases) |
| `06-feature-phases-1-14.md` | Feature phases 1–14 |
| `07-feature-phases-33-40.md` | Feature phases 33–40 |

## Completed Workflow Plans (in `.lovable/memory/workflow/completed/`)

| File | Description |
|------|-------------|
| `02-golang-enum-migration-plan.md` | Golang enum migration — 8 typed string enums |
| `03-file-size-remediation-plan.md` | Main plugin file split (5,604 → ~270 lines) |
| `04-long-function-fix-plan.md` | Long function fix phases 1–16 |
| `05-riseup-constant-migration-plan.md` | RISEUP_ constant prefix removal |
| `06-camelcase-method-migration-plan.md` | camelCase method migration phases 1–9 |
| `07-nested-if-fix-plan.md` | Nested-if flattening 8 phases |

## Remaining Workflow Files (still active)

| File | Description |
|------|-------------|
| `02-naming-convention-refactor-plan.md` | Phases 3, 5, 6 still pending |
| `03-j1-constants-audit.md` | J2–J7 enum migration phases pending |

## Current Focus

**Backlog audit complete.** 11 pending items consolidated in `plan.md`. Priority items:
1. K3: PathUtils snake_case fix (contains critical bug)
2. K1: Spec documentation updates
3. J2–J7: constants.php enum migration
