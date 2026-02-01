# Memory: features/code-generation-system-architecture

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/24-code-generation-system/`

---

## Overview

AI-Powered Code Generation System enabling automated, specification-driven code creation. Uses **33 specification files** with sequential 00-32 numbering scheme and 12xxx error code range.

---

## Three-Phase Pipeline

| Phase | Name | Description |
|-------|------|-------------|
| 1 | Code Writing | Parallel batch execution with topological sorting |
| 2 | Consistency Check | Cross-file validation |
| 3 | Build Verification | Via `brun` CLI with 3-tier AI fix loop |

---

## Key Architecture

- **Guideline Hierarchy:** 4-layer merge/extend (General → Language → User → Project)
- **Dependency Handling:** Topological sort for parallel batch creation
- **Credit System:** Per AI Request - `(inputTokens × inputRate) + (outputTokens × outputRate)`
- **Concurrency:** Per-project parallel with isolated worker pools

---

## Core Components

| Component | Responsibility |
|-----------|----------------|
| GuidelineResolver | Merge 4-layer prompts |
| PlanGenerator | Create execution plan |
| ParallelExecutor | Batch file processing |
| CodeWriter | Generate file content |
| ConsistencyChecker | Cross-file validation |
| BuildVerifier | Build testing |
| GitManager | Version control |
| CreditTracker | Token accounting |

---

## API Surface

- **46 REST endpoints** across 6 domains
- **35+ WebSocket events** across 8 categories
- **Error codes:** 12xxx range (12000-12799)

| Domain | Endpoints |
|--------|-----------|
| Guidelines | 12 |
| Plans | 6 |
| Sessions | 7 |
| Git | 10 |
| Build | 4 |
| Credits | 7 |

---

## Error Code Ranges

| Range | Domain |
|-------|--------|
| 120xx | General |
| 121xx | Guidelines |
| 122xx | Planning |
| 123xx | Execution |
| 124xx | Git |
| 125xx | Build |
| 126xx | Credits |
| 127xx | WebSocket |

---

## Integration

- **Git:** GitHub/GitLab OAuth, auto-commit, auto-push
- **Repository:** `{root}/{project}/spec/`, `BE/`, `FE/`, `README.md`
- **Database:** 21 GORM models across 7 domains
- **Testing:** 60/30/10 integration-heavy pyramid

---

## Related Specs

- [00-overview.md](spec/spec-management-software/05-features/24-code-generation-system/00-overview.md)
- [01-architecture.md](spec/spec-management-software/05-features/24-code-generation-system/01-architecture.md)
