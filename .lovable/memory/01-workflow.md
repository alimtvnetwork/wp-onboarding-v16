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

## Spec Reading Order

### For New AI Sessions

1. **Read `.lovable/README.md`** - Entry point
2. **Read `.lovable/memory/02-project-context.md`** - Understand projects
3. **Read `.lovable/plan.md`** - Current priorities
4. **Read relevant spec `00-overview.md`** - Project details
5. **Read `66-shared-constants.md`** - Single source of truth
6. **Read `60-ai-implementation-checklist.md`** - Critical algorithms

### Before Implementing

1. Read the specific spec file for the feature
2. Check `61-common-implementation-pitfalls.md` for anti-patterns
3. Review related specs via cross-references
4. Check diagrams if available

---

## Spec Updates

When modifying specifications:

1. Update the relevant spec file
2. Update the `Updated:` date in file header
3. If breaking change, note in `plan.md`
4. Update `66-shared-constants.md` if constants change
5. Update `98/99-*-report.md` if cross-references change

---

## Ideas Workflow

Feature proposals go in `ideas/` folder:

1. Create file: `{nn}-{idea-title}.md`
2. Use template from `ideas/README.md`
3. Set status to `Draft`
4. Update status as idea progresses
5. When implemented, link to actual spec files

---

*Follow these guidelines to maintain continuity across AI sessions.*
