# Suggestions Workflow

> **Location:** `.lovable/memory/suggestions/`  
> **Updated:** 2026-02-01

---

## Overview

This folder contains a consolidated suggestion tracker and archived completed suggestions.

---

## Structure

```
.lovable/memory/suggestions/
├── 01-suggestions-tracker.md   # Main tracker (consolidated)
├── completed/                  # Archived completed suggestions
│   ├── C-001-workflow-convention.md
│   ├── C-002-lm003-entity-models.md
│   ├── C-003-sm003-rag-system.md
│   ├── C-004-reliability-95-percent.md
│   ├── C-005-lm-cleanup-guide.md
│   ├── C-006-report-refresh.md
│   ├── C-007-lm-extracted.md
│   ├── C-008-fresh-analysis.md
│   └── C-009-ai-bridge-implemented.md
└── README.md                   # This file
```

---

## Filesystem Convention

### File Naming

For individual suggestion files (optional), use:
```
YYYYMMDD-HHMMSS-suggestion-<slug>.md
```

Example: `20260201-143022-suggestion-register-9xxx-errors.md`

### Suggestion File Content

Each suggestion should include:

| Field | Description |
|-------|-------------|
| suggestionId | Unique identifier (P-XXX for pending) |
| createdAt | ISO date when suggestion was created |
| source | Always "Lovable" for AI suggestions |
| affectedProject | Which project this applies to |
| description | What the suggestion is |
| rationale | Why this change is needed |
| proposed change | Specific changes to make |
| acceptance criteria | How to verify completion |
| status | open, inProgress, or done |
| completion notes | Filled when done |

---

## Workflow

### Adding New Suggestions

1. **Preferred:** Add directly to `01-suggestions-tracker.md` in Pending section
2. **Alternative:** Create individual file with naming convention above

### Updating Suggestions

1. **Work begins:** Update status to `inProgress`
2. **Progress notes:** Add notes in the suggestion entry
3. **Work complete:** Update status to `done`, fill completion notes

### Completing Suggestions

1. Update status to `done` in tracker
2. Fill in completion notes
3. Move summary to Completed section
4. Create archive file: `completed/C-XXX-<slug>.md`

---

## Quick Reference

| File | Purpose |
|------|---------|
| `01-suggestions-tracker.md` | Main tracker with pending/completed summaries |
| `completed/C-XXX-*.md` | Detailed archives of completed suggestions |

---

## For New AI Sessions

1. **Read:** `01-suggestions-tracker.md` for current suggestions
2. **Check pending:** See Pending section for active work
3. **Add new:** Follow workflow above to add suggestions
4. **Complete:** Archive to `completed/` when done

---

*Updated 2026-02-01 with revised filesystem convention.*