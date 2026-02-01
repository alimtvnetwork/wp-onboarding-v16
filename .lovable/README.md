# Lovable AI Memory

> **Purpose:** Guidelines for AI models to understand project structure and maintain consistent workflows.  
> **Updated:** 2026-02-01

---

## Folder Structure

```
.lovable/
├── README.md                    # This file - entry point for AI
├── memory/
│   ├── 01-conventions.md        # Coding and file naming conventions
│   ├── 01-workflow.md           # Task tracking guidelines
│   └── 02-project-context.md    # Project overview and learning materials
└── plan.md                      # Active roadmap
```

---

## Quick Reference

| Document | Purpose |
|----------|---------|
| `memory/01-conventions.md` | File naming, folder structure, coding standards |
| `memory/01-workflow.md` | How to track tasks, suggestions, handoffs |
| `memory/02-project-context.md` | Project summaries, learning materials |
| `plan.md` | Prioritized backlog for implementation |

---

## For New AI Sessions

1. **Read this README** first
2. **Read `memory/02-project-context.md`** for project understanding
3. **Check `plan.md`** for current priorities
4. **Follow conventions** in `memory/01-conventions.md`
5. **Track work** per `memory/01-workflow.md`

---

## Spec Folder Overview

The `spec/` folder contains project specifications:

```
spec/
├── powershell-integration/      # PowerShell build scripts
├── wp-plugin-builder/           # WordPress plugin builder CLI (Go)
│   ├── 00-overview.md           # Start here - 15 spec files
│   ├── 12-coding-guidelines.md  # PHP/WP coding standards
│   └── 14-implementation-guide.md # Build order
└── wp-plugin/
    ├── exam-manager/            # Exam management plugin - 112 spec files
    │   ├── 00-overview.md       # Start here
    │   ├── 60-ai-implementation-checklist.md  # CRITICAL
    │   ├── 61-common-implementation-pitfalls.md  # MUST READ
    │   └── 66-shared-constants.md  # SSOT
    └── link-manager/            # Link management plugin - 30 spec files
        ├── 00-overview.md       # Start here
        └── 66-shared-constants.md  # SSOT
```

---

## Learning Path

### To Understand WordPress Plugin Specs

1. Read `spec/wp-plugin/exam-manager/00-overview.md` (master index)
2. Read `spec/wp-plugin/exam-manager/60-ai-implementation-checklist.md` (critical algorithms)
3. Read `spec/wp-plugin/exam-manager/61-common-implementation-pitfalls.md` (50+ anti-patterns)
4. Review `spec/wp-plugin-builder/12-coding-guidelines.md` (PHP/WP standards)

### To Understand WP Plugin Builder

1. Read `spec/wp-plugin-builder/00-overview.md` (overview)
2. Read `spec/wp-plugin-builder/01-core-architecture.md` (system design)
3. Read `spec/wp-plugin-builder/14-implementation-guide.md` (build order)

---

## Critical Patterns to Remember

### 1. Database Columns
- **SQL:** PascalCase (`UserId`, `CreatedAt`)
- **ORM:** camelCase (`userId`, `createdAt`)

### 2. Progress Calculation
- Use `floor()` not `round()` - never show 100% unless truly complete

### 3. Deadline Extensions
- Always calculate from ORIGINAL deadline, not current

### 4. Cookies
- Always exam-scoped: `eqm_session_{examSlug}`

### 5. No Magic Strings
- Use enums and constants from `66-shared-constants.md`

---

*Updated 2026-02-01 with comprehensive project context.*
