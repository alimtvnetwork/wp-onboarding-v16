# AI Training: Spec Patterns

**Version:** 1.0.0  
**Updated:** 2026-01-29  
**Purpose:** Example specifications demonstrating conventions

---

## Pattern 1: Overview File (00-overview.md)

```markdown
# Feature Name

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Overview

Brief description of feature purpose and scope.

**Cross-References:**
- [Related Feature](../XX-related/00-overview.md)
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)

---

## Summary Statistics

| Metric | Value |
|--------|-------|
| Files | 5 |
| Components | 3 |
| Status | Planned |

---

## File Index

| File | Description | Status |
|------|-------------|--------|
| [01-component-a](./01-component-a.md) | First component | Planned |
| [02-component-b](./02-component-b.md) | Second component | Planned |

---

## Related Specs

- [Master Index](../../00-master-index.md)
- [Feature Overview](../00-overview.md)
```

---

## Pattern 2: Component Specification

```markdown
# Component Name

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [Feature Overview](./00-overview.md)

---

## Purpose

What this component does and why it exists.

---

## Requirements

### Functional Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-001 | Description | High |
| FR-002 | Description | Medium |

### Non-Functional Requirements

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-001 | Response time | <100ms |

---

## Interface

### Input

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | string | Yes | Unique identifier |

### Output

| Field | Type | Description |
|-------|------|-------------|
| success | boolean | Operation result |

---

## Implementation Notes

Key considerations for implementation.

---

## Related Specs

- [Overview](./00-overview.md)
- [Related Component](./02-related.md)
```

---

## Pattern 3: API Endpoint Specification

```markdown
# API Endpoints

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Endpoints

### POST /api/v1/resource

**Description:** Create a new resource.

**Request:**
```json
{
  "name": "string",
  "type": "string"
}
```

**Response (201):**
```json
{
  "id": "string",
  "name": "string",
  "createdAt": "ISO8601"
}
```

**Errors:**

| Code | Status | Description |
|------|--------|-------------|
| 4001 | 400 | Invalid input |
| 4002 | 409 | Already exists |
```

---

## Pattern 4: Consistency Report (99-consistency-report.md)

```markdown
# Consistency Report

**Version:** 1.0.0  
**Updated:** 2026-01-29  
**Health Score:** 100/100

---

## Validation Results

| Check | Status | Details |
|-------|--------|---------|
| File naming | ✅ | All files follow conventions |
| Cross-references | ✅ | All links valid |
| Frontmatter | ✅ | All files have metadata |
| Index coverage | ✅ | All files indexed |

---

## Issues

None.

---

## History

| Date | Score | Changes |
|------|-------|---------|
| 2026-01-29 | 100/100 | Initial validation |
```
