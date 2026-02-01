# Feature: Consistency Checker

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-28  

---

## Summary

Automated validation service that scans specifications for broken links, naming violations, duplicate definitions, and missing required sections.

---

## User Stories

- As a user, I want to detect broken links between specs
- As a user, I want to ensure all files follow naming conventions
- As a user, I want to find duplicate definitions
- As a user, I want a health score for my project

---

## Components

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 01 | [Consistency Checker](./01-consistency-checker.md) | Backend | Core validation service |
| 02 | [Implementation](./02-consistency-checker-implementation.md) | Backend | Validator code |
| 03 | [Consistency Dashboard](./03-consistency-dashboard.md) | Frontend | Health score UI |

---

## Key Features

- **Link Validation:** Broken link detection with Levenshtein suggestions
- **Naming Enforcement:** kebab-case, numbered prefixes
- **Section Validation:** Version, Status, Overview, Cross-References
- **Health Scoring:** 0-100 score with A-F grades
- **Auto-Fix:** Generated fixes with confidence levels

---

## Validators

| Validator | Checks |
|-----------|--------|
| Link Validator | Broken internal links, missing files |
| Naming Validator | File naming conventions |
| Section Validator | Required sections present |
| Duplicate Validator | Duplicate definitions |

---

## Dependencies

- [File Management](../02-file-management/00-overview.md)
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)

---

## E2E Tests

| # | Test | Priority |
|---|------|----------|
| 01 | [Full Scan](./tests/01-full-scan-e2e.md) | Critical |
| 02 | [Auto-Fix](./tests/02-auto-fix-e2e.md) | High |

---

## Related Specs

- [Consistency Implementation](./02-consistency-checker-implementation.md)
