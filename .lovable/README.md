# Lovable AI Memory

> **Purpose:** Guidelines for AI models to understand project structure and maintain consistent workflows.

---

## Folder Structure

```
.lovable/
├── README.md                 # This file - entry point for AI
├── memory/
│   ├── 01-conventions.md     # Coding and file naming conventions
│   └── 01-workflow.md        # Task tracking guidelines
└── plan.md                   # Active roadmap (at .lovable/plan.md)
```

---

## Quick Reference

| Document | Purpose |
|----------|---------|
| `memory/01-conventions.md` | File naming, folder structure rules |
| `memory/01-workflow.md` | How to track tasks, suggestions, completions |
| `plan.md` | Prioritized backlog for implementation |

---

## For New AI Sessions

1. **Read this README** first
2. **Check `plan.md`** for current priorities
3. **Follow conventions** in `memory/01-conventions.md`
4. **Track work** per `memory/01-workflow.md`

---

## Spec Folder Overview

The `spec/` folder contains project specifications:

```
spec/
├── powershell-integration/   # PowerShell build scripts
├── wp-plugin-builder/        # WordPress plugin builder CLI
└── wp-plugin/                # WordPress plugin specs
```

Each spec folder contains markdown files with requirements, data models, and acceptance criteria.

---

*Updated: 2026-02-01*
