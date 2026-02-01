# Instructions Folder

> **Purpose:** Store refined, actionable instructions promoted from ideas.

---

## What Goes Here

- **Promoted ideas** — Refined and clarified from `01-ideas/`
- **Step-by-step guidance** — Clear implementation instructions
- **Acceptance criteria** — Measurable completion requirements
- **Action items** — Ready for spec generation

---

## File Naming

```
{nn}-{instruction-slug}.md
```

Examples:
- `01-implement-voice-input.md`
- `02-add-user-authentication.md`
- `03-create-file-manager.md`

---

## Template

```markdown
# Instruction: {Title}

**ID:** instruction_{uuid}  
**Status:** pending | in-progress | completed  
**Priority:** low | medium | high | critical  
**Source Idea:** [01-{idea-slug}.md](../01-ideas/01-{idea-slug}.md)  
**Created:** {ISO8601}  
**Updated:** {ISO8601}  

---

## Summary

{One paragraph description of what this instruction accomplishes}

---

## Background

{Context and rationale - why is this needed?}

---

## Steps

1. {Step 1 - specific and actionable}
2. {Step 2}
3. {Step 3}
...

---

## Acceptance Criteria

- [ ] Criterion 1 - measurable outcome
- [ ] Criterion 2 - testable requirement
- [ ] Criterion 3

---

## Dependencies

- [Dependency 1](../path-to-dependency.md)
- [Dependency 2](../path-to-dependency.md)

---

## Notes

{Implementation notes, edge cases, considerations}
```

---

## Lifecycle

```
1. RECEIVE  → Instruction created from promoted idea
2. REFINE   → Add steps, acceptance criteria
3. GENERATE → Use to create specs in 05-features/
4. COMPLETE → Mark as completed when implemented
```

---

## Status Definitions

| Status | Meaning |
|--------|---------|
| `pending` | Waiting to be worked on |
| `in-progress` | Currently being implemented |
| `completed` | Fully implemented and verified |

---

## Related

- [Ideas Folder](../01-ideas/README.md) — Source of promoted ideas
- [Features Folder](../05-features/00-overview.md) — Where specs are generated
- [Folder Structure Guideline](../../00-folder-structure-guideline.md) — Master organization guide
