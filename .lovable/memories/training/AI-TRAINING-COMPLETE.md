# AI Training: Complete Guide

**Version:** 1.0.0  
**Updated:** 2026-01-29  
**Purpose:** Single-file training package for external AI agents

---

# PART 1: ONBOARDING

## Project Identity

**Spec Management Software** is a local-first specification authoring and validation system.

- **Backend:** Golang + SQLite (GORM)
- **Frontend:** React + TypeScript + Tailwind CSS
- **Architecture:** Local-first with hybrid storage

## Core Principles

1. **100% Health Score Target** — All specs must pass consistency checks
2. **Dual-Format Artifacts** — Markdown for humans, JSON for machines
3. **Bidirectional Cross-References** — All links must work both ways
4. **Iterative Quality Loops** — Auto-fix until 99%+ target reached

## Key Capabilities

| Capability | Description |
|------------|-------------|
| Spec Authoring | Markdown templates with YAML frontmatter |
| AI Drafting | Voice-to-text → Proofread → Plan → Execute |
| Consistency Checking | Link validation, naming enforcement |
| Code Generation | Three-phase pipeline (Writing → Consistency → Build) |
| CLI Tools | `gsearch` (search), `brun` (build runner) |

## AI Authorization

AI models are explicitly authorized to:
- Access, read, write, and rewrite files
- Follow the established folder conventions
- Maintain cross-reference integrity
- Generate new specs following patterns

---

# PART 2: CONVENTIONS

## Naming Rules

### Files

| Rule | Example |
|------|---------|
| Lowercase hyphenated | `01-authentication.md` ✅ |
| No camelCase | `authSystem.md` ❌ |
| Numeric prefix (2-digit) | `00-overview.md`, `24-code-generation-system.md` |

### Folders

| Rule | Example |
|------|---------|
| Numeric prefix | `05-features/`, `24-code-generation-system/` |
| Lowercase hyphenated | `07-history-system/` ✅ |
| No underscores | `history_system/` ❌ |

## Required Files

Every feature folder MUST contain:

```
{nn}-{feature-name}/
├── 00-overview.md          # MANDATORY: Feature index
├── 01-{first-spec}.md      # First specification
├── 02-{second-spec}.md     # Second specification
├── ...
├── tests/                  # E2E test specifications
│   └── 01-{test-name}.md
└── 99-consistency-report.md  # Health score tracking
```

## Cross-Reference Format

### Internal Links (Same Folder)
```markdown
See [Architecture](./01-architecture.md)
```

### External Links (Different Folder)
```markdown
See [Error Codes](../06-error-management/00-overview.md)
```

### Anchor Links (Same File)
```markdown
Jump to [Configuration](#configuration-section)
```

## Versioning

Every spec file MUST include frontmatter:

```markdown
# Feature Name

**Version:** 1.0.0  
**Status:** Draft | Planned | Active | Complete  
**Updated:** YYYY-MM-DD  
```

## Special Prefixes

| Prefix | Purpose |
|--------|---------|
| `00-` | Overview/index file |
| `98-` | Test plans |
| `99-` | Metadata, consistency reports |

## Single Source of Truth

- ONE master index at `spec/{project}/00-master-index.md`
- NO duplicate documentation folders
- Memories consolidate in `.lovable/memories/`

---

# PART 3: FOLDER STRUCTURE

## Root Structure

```
spec/spec-management-software/
├── 00-master-index.md           # Project navigation hub
├── CHANGELOG.md                 # Version history
├── 01-ideas/                    # Raw concepts (8 files)
├── 02-instructions/             # Refined directives (1 file)
├── 03-project-overview/         # Architecture docs (2 files)
├── 04-coding-guidelines/        # Standards (3 files)
├── 05-features/                 # Feature specs (140+ files)
│   ├── 00-overview.md           # Feature index
│   ├── 01-authentication/       # Auth system
│   ├── 02-file-management/      # File ops
│   ├── ...
│   ├── 24-code-generation-system/  # AI code gen (34 files)
│   ├── 25-ai-enhancements/      # Advanced AI (33 files)
│   └── 26-ai-code-generation/   # On-the-fly Golang code generation
├── 06-error-management/         # Error codes (5 files)
├── 07-database-design/          # Schema (7 files)
├── 08-roadmap-overview/         # Timeline (10 files)
├── 09-diagrams/                 # Visual flows (10 files)
├── 10-research/                 # Investigations (6 files)
├── 11-skipped-features/         # Deferred items
├── 12-prompts/                  # AI presets (21 files)
└── 99-consistency-report.md     # Overall health
```

## TypeScript Strict Guidelines

> ⚠️ **CRITICAL:** All TypeScript code MUST follow these rules:

| Rule | Requirement |
|------|-------------|
| No `any` | ❌ Never use `any` type |
| No `unknown` | ⚠️ Only in type guards |
| `const` by default | Use `const` unless reassignment needed |
| Enums for switches | All switch statements must use enum types |
| Explicit types | All object shapes must have interfaces |
| `readonly` | Use for immutable properties |
| Return types | Explicit return types on all functions |

### Example

```typescript
// ❌ INCORRECT
function handleAction(action: string) {
  switch (action) {
    case "create": return create();
  }
}

// ✅ CORRECT
enum TaskAction {
  Create = "create",
  Update = "update",
}

interface TaskResult {
  readonly success: boolean;
  readonly message: string;
}

function handleAction(action: TaskAction): TaskResult {
  switch (action) {
    case TaskAction.Create:
      return create();
    case TaskAction.Update:
      return update();
    default:
      const _exhaustive: never = action;
      throw new Error(`Unhandled: ${_exhaustive}`);
  }
}
```

## Feature Folder Pattern

Each feature folder follows this structure:

```
{nn}-{feature-name}/
├── 00-overview.md              # Feature summary + navigation
├── 01-{component-a}.md         # First component spec
├── 02-{component-b}.md         # Second component spec
├── ...
├── {nn}-api-endpoints.md       # API specs (if applicable)
├── {nn}-data-models.md         # Data structures
├── {nn}-error-codes.md         # Feature-specific errors
├── tests/
│   ├── 01-{test-scenario}.md
│   └── 02-{test-scenario}.md
└── 99-consistency-report.md    # Feature health score
```

## Example: Code Generation System (34 files)

```
24-code-generation-system/
├── 00-overview.md
├── 01-architecture.md
├── 02-guideline-hierarchy.md
├── 03-parallel-code-generation.md
├── 04-plan-generator.md
├── 05-parallel-executor.md
├── 06-build-verification.md
├── 07-git-integration.md
├── 08-configuration.md
├── ...
├── 32-url-context-system.md
└── 99-consistency-report.md
```

## Navigation Rules

1. Start at `00-master-index.md` for project-wide navigation
2. Use `05-features/00-overview.md` for feature discovery
3. Each folder's `00-overview.md` provides local navigation
4. Cross-references link related concepts bidirectionally

---

# PART 4: SPEC PATTERNS

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
\`\`\`json
{
  "name": "string",
  "type": "string"
}
\`\`\`

**Response (201):**
\`\`\`json
{
  "id": "string",
  "name": "string",
  "createdAt": "ISO8601"
}
\`\`\`

**Errors:**

| Code | Status | Description |
|------|--------|-------------|
| 4001 | 400 | Invalid input |
| 4002 | 409 | Already exists |
```

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

---

# PART 5: FEATURE TEMPLATE

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

---

# END OF TRAINING DOCUMENT

**Total Sections:** 5  
**Word Count:** ~1,500  
**Ready for:** Copy-paste to external AI tools (ChatGPT, Claude, etc.)
