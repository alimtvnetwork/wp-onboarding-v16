# AI Training: Feature Template

**Version:** 1.0.0  
**Updated:** 2026-01-29  
**Purpose:** Boilerplate for creating new feature specifications

---

## Creating a New Feature

### Step 1: Determine Next Number

Check `spec/spec-management-software/05-features/` for the highest existing number.
If `25-ai-enhancements/` exists, your new feature is `26-{feature-name}/`.

### Step 2: Create Folder Structure

```bash
mkdir spec/spec-management-software/05-features/26-new-feature
touch spec/spec-management-software/05-features/26-new-feature/00-overview.md
touch spec/spec-management-software/05-features/26-new-feature/01-core-spec.md
mkdir spec/spec-management-software/05-features/26-new-feature/tests
```

### Step 3: Create Overview (COPY THIS)

```markdown
# New Feature Name

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

[One paragraph describing what this feature does]

**Cross-References:**
- [Related Feature](../XX-related/00-overview.md)
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)

---

## Summary

| Metric | Value |
|--------|-------|
| Total Files | 3 |
| Status | Draft |

---

## File Index

| File | Description | Status |
|------|-------------|--------|
| [01-core-spec](./01-core-spec.md) | Core specification | Draft |

---

## Scope

### In Scope

- Feature capability 1
- Feature capability 2

### Out of Scope

- Excluded capability

---

## Dependencies

| Dependency | Type | Description |
|------------|------|-------------|
| [Authentication](../01-authentication/00-overview.md) | Required | User context |

---

## Related Specs

- [Master Index](../../00-master-index.md)
- [Features Overview](../00-overview.md)
```

### Step 4: Create Core Specification

```markdown
# Core Specification

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Overview](./00-overview.md)

---

## Purpose

[What this specification covers]

---

## Requirements

### Functional

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-001 | [Requirement] | High |

### Non-Functional

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-001 | Performance | <100ms |

---

## Technical Design

### Architecture

[Description or diagram]

### Data Model

| Field | Type | Description |
|-------|------|-------------|
| id | string | Unique ID |

---

## Implementation Notes

[Key considerations]

---

## Related Specs

- [Overview](./00-overview.md)
```

### Step 5: Update Indexes

1. Add to `05-features/00-overview.md`:
```markdown
| 26 | [New Feature](./26-new-feature/00-overview.md) | Draft | 3 | Description |
```

2. Add to `00-master-index.md` under appropriate section.

### Step 6: Run Consistency Check

Ensure all cross-references are valid and health score is 100%.

---

## Checklist

- [ ] Folder created with numeric prefix
- [ ] `00-overview.md` with frontmatter
- [ ] All files have version/status/updated
- [ ] Cross-references use relative paths
- [ ] Added to `05-features/00-overview.md`
- [ ] Added to master index
- [ ] Consistency check passed
