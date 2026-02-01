# Workflow Memory

> **Location:** `.lovable/memory/workflow/`  
> **Updated:** 2026-02-01

---

## Overview

This folder contains workflow status files and archived session logs.

---

## Structure

```
.lovable/memory/workflow/
├── 01-master-status.md         # Current master status
├── completed/                  # Archived session logs
│   ├── session-20260131-nexus-flow.md
│   └── link-manager-separation.md
└── README.md                   # This file
```

---

## File Naming Convention

Workflow files use numbered prefixes:
```
01-name-of-file.md
02-name-of-file.md
```

This keeps files ordered and the folder organized with fewer files.

---

## Quick Reference

| File | Purpose |
|------|---------|
| `01-master-status.md` | Current project status, completed/pending tasks |
| `completed/*.md` | Archived session logs and policy documents |

---

## For New AI Sessions

1. **Start here:** Read `01-master-status.md` for current state
2. **Check pending:** See Pending Tasks section for next actions
3. **Reference:** Use training packages at `.lovable/memories/training/05-training-package.md`
4. **Plan:** Check `plan.md` at project root for task selection

---

## Update Protocol

After each significant session:
1. Update `01-master-status.md` with new completions
2. Archive detailed session notes to `completed/`
3. Keep `01-master-status.md` as the authoritative current state

---

*Updated 2026-02-01 with revised structure convention.*