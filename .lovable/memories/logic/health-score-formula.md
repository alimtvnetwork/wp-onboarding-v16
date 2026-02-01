# HealthScore Formula

> **Updated:** 2026-01-31  
> **Status:** Canonical (single source of truth)

---

## Formula

The system consistency HealthScore uses a **6-field weighted formula**:

```go
type HealthScore struct {
    CrossReference  float64 // 25% - Valid internal links
    SchemaAlignment float64 // 20% - DB schema matches interfaces
    Terminology     float64 // 15% - Consistent naming conventions
    Completeness    float64 // 15% - All required sections present
    Freshness       float64 // 10% - Recently updated specs
    RAGFormat       float64 // 15% - AI-friendly formatting
}

func (h HealthScore) Total() float64 {
    return (h.CrossReference * 0.25) +
           (h.SchemaAlignment * 0.20) +
           (h.Terminology * 0.15) +
           (h.Completeness * 0.15) +
           (h.Freshness * 0.10) +
           (h.RAGFormat * 0.15)
}
```

---

## Field Definitions

| Field | Weight | Description | Scoring |
|-------|--------|-------------|---------|
| CrossReference | 25% | All internal links resolve to existing files | 0-100 based on % valid links |
| SchemaAlignment | 20% | TypeScript interfaces match DB schema | 0-100 based on field coverage |
| Terminology | 15% | Consistent naming (PascalCase tables, camelCase fields) | 0-100 based on violations |
| Completeness | 15% | All required spec sections present | 0-100 based on section coverage |
| Freshness | 10% | Specs updated within last 30 days | 0-100 based on age |
| RAGFormat | 15% | Headers, code blocks, tables for AI parsing | 0-100 based on format compliance |

---

## Grading Scale

| Score | Grade | Status |
|-------|-------|--------|
| 95-100 | A+ | Excellent |
| 90-94 | A | Very Good |
| 85-89 | B+ | Good |
| 80-84 | B | Acceptable |
| 70-79 | C | Needs Improvement |
| <70 | D/F | Critical Issues |

---

## Historical Note

A duplicate 5-field version (without RAGFormat) existed in `01-consistency-checker.md` lines 266-282. This was deprecated on 2026-01-31 in favor of the 6-field version, which better supports AI-driven workflows.

---

## Related

- [Consistency Report](../../spec/spec-management-software/99-consistency-report.md)
- [Consistency Checker](../../spec/spec-management-software/05-features/19-consistency-checker/01-consistency-checker.md)
