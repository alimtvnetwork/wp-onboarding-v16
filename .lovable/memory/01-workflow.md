# Workflow Guidelines

> **Location:** `.lovable/memory/01-workflow.md`  
> **Updated:** 2026-02-01

---

## Task Tracking

### Plan File

The `plan.md` at `.lovable/plan.md` serves as the primary roadmap:

```markdown
# Plan

## Phase 1: Current
| Task | Status | Notes |
|------|--------|-------|
| Task name | todo/in-progress/done | Brief notes |

## Phase 2: Next
...

## Backlog
...
```

**Statuses:**
- `todo` - Not started
- `in-progress` - Currently being worked on
- `done` - Completed

---

## Suggestions Tracking

When Lovable or AI suggests improvements:

1. Add to `plan.md` backlog section
2. Include: description, rationale, priority
3. Update status when addressed
4. Remove or mark done when completed

**Format in plan.md:**
```markdown
## Suggestions
| ID | Suggestion | Priority | Status |
|----|------------|----------|--------|
| S-001 | Description | high/medium/low | open/done |
```

---

## Session Handoff

When ending a session or handing off to another AI:

1. Update `plan.md` with current progress
2. Note any blockers or decisions made
3. List next recommended actions

---

## Spec Updates

When modifying specifications:

1. Update the relevant spec file
2. Note the change date in file header
3. If breaking change, note in `plan.md`

---

*Follow these guidelines to maintain continuity across AI sessions.*
