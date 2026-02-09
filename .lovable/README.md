# Lovable AI Memory

> **Purpose:** Guidelines for AI models to understand project structure and maintain consistent workflows.  
> **Updated:** 2026-02-09

---

## Folder Structure

```
.lovable/
├── README.md                    # This file - entry point for AI
├── plan.md                      # DRY Refactoring plan (complete)
├── plan/
│   ├── README.md                # Plan index
│   ├── active.md                # Status overview — all tracks complete
│   ├── technical-notes.md       # Root cause analyses, architecture decisions
│   └── completed/               # All completed plan files archived here
│       ├── 01-dry-refactoring-phases-1-6.md
│       ├── 02-dry-refactoring-phases-7-10.md
│       ├── 03-error-diagnostics-v3.md
│       ├── 04-frontend-pages.md
│       ├── 05-snapshot-backup-system.md
│       ├── 06-feature-phases-1-14.md
│       └── 07-feature-phases-33-40.md
└── memory/
    ├── 01-conventions.md        # Coding and file naming conventions
    ├── 01-workflow.md           # Task tracking and session handoff
    ├── 02-project-context.md    # Project overview and status
    ├── 03-reliability-risk-report.md  # Reliability assessment (95/100)
    ├── PRD.md                   # Plugins Onboard PRD (secondary project)
    ├── architecture/            # Established patterns (backend, frontend, WP plugin)
    ├── features/                # Feature documentation
    ├── issues-fixed/            # Bug fix history (15 entries)
    └── suggestions/
        ├── 01-suggestions-tracker.md  # All 18 suggestions (0 open, 18 completed)
        └── completed/
            └── 01-completed-suggestions.md  # Detailed completion archive
```

---

## Quick Reference

| Document | Purpose |
|----------|---------|
| `plan/active.md` | **START HERE** - Current status, all tracks complete |
| `memory/02-project-context.md` | Project overview, architecture, key files |
| `memory/03-reliability-risk-report.md` | Reliability score: 95/100 |
| `memory/suggestions/01-suggestions-tracker.md` | All 18 suggestions completed |
| `memory/01-conventions.md` | File naming, coding standards |
| `memory/01-workflow.md` | Session handoff, spec reading order |

---

## For New AI Sessions

1. **Read `plan/active.md`** — All tracks complete, open questions listed
2. **Read `memory/02-project-context.md`** — Full project status and architecture
3. **Read `memory/suggestions/01-suggestions-tracker.md`** — 0 open suggestions
4. **Follow conventions** in `memory/01-conventions.md`
5. **Ask user what to implement next**

---

## Current Project: WP Plugin Publish

**Status: FULLY IMPLEMENTED ✅**

- ✅ 28 specification documents complete
- ✅ All feature phases complete (1–14, 33–40)
- ✅ 10-phase DRY refactoring complete
- ✅ 18/18 improvement suggestions resolved
- ✅ Error diagnostics v3 (6 phases) complete
- ✅ Frontend pages (15 phases) complete
- ✅ Snapshot backup system (10 phases) complete

**Reliability Score:** 95/100 (Excellent — Production-ready)

---

## Open Suggestions Count

| Priority | Count |
|----------|-------|
| Open | 0 |
| Completed | 18 |
| Rejected | 0 |
| **Total** | **18** |

---

## Spec Folder Overview

```
spec/
├── README.md                    # Spec index (start here)
├── coding-guidelines/           # DRY principles
├── error-handling/              # Cross-stack error chain
├── error-modal/                 # Error Modal UI spec with diagrams
├── golang-standards/            # Go coding standards
├── typescript-standards/        # TypeScript coding standards
├── php-standards/               # PHP coding standards
├── powershell-integration/      # Build runner (generic, cross-project)
├── upload-scripts/              # Upload scripts V1-V3
├── response-envelope/           # JSON Schema v1.0.0 & samples
└── dry-refactoring-summary.md   # 10-phase summary
```

---

## Critical Patterns

### 1. Split Database Architecture
- Root DB as registry, child DBs for history/cache

### 2. Universal Response Envelope
- PascalCase keys (Go/PHP compat), envelope.schema.json v1.0.0
- Status/Attributes/Results/Navigation/Errors/MethodsStack

### 3. Error Handling
- Go: `apperror.Wrap()` — no `fmt.Errorf`
- PHP: `safe_execute` + `Throwable` catching
- Frontend: Global Zustand error store with factory

### 4. Suggestions Workflow
- All consolidated in `01-suggestions-tracker.md`
- Completed items archived in `completed/` folder

---

## Secondary Projects

| Project | Location | Status |
|---------|----------|--------|
| Plugins Onboard | `plugins-onboard/` | ✅ Complete (v1.0.5) |
| Spec Builder v3 | (Referenced in CONTEXT-FOR-AI.md) | 📝 Dormant |

---

*Updated 2026-02-09. All features, refactoring, and suggestions complete.*
