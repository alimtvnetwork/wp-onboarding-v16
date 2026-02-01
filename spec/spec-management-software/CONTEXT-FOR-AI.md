# Files for AI Context: Project Structure Understanding

**Version:** 1.0.0  
**Updated:** 2026-01-29  

---

## Purpose

This document lists the essential files another AI needs to understand the project structure, naming conventions, and how to create feature specifications ("features").

---

## Core Files to Provide

### 1. Master Index (Structure Overview)
**File:** `spec/spec-management-software/00-master-index.md`

This is the main entry point showing all folders, their purpose, and file listings. It provides:
- Complete navigation structure
- CLI tool documentation (gsearch, brun)
- All feature folder references
- Status tracking

### 2. Project Overview
**File:** `spec/spec-management-software/00-overview.md`

Short overview of the project philosophy and folder layout.

### 3. Features Overview (Formerly Split-Spec)
**File:** `spec/spec-management-software/05-features/00-overview.md`

Index of all feature folders (01-25) with:
- Feature naming conventions
- Folder structure pattern
- Component counts
- Status tracking

### 4. File Structure Conventions (Memory)
**File:** `.lovable/memories/spec-management/file-structure-conventions.md`

Compact memory file explaining:
- Numeric prefixes (00-32 for folder 24)
- `00-overview.md` index pattern
- `99-*` reserved for meta/reports
- Cross-reference patterns

### 5. Example Feature Folder
**Folder:** `spec/spec-management-software/05-features/24-code-generation-system/`

The most comprehensive example with 33 files showing:
- Sequential numbering (00-32)
- Consistency report format (99-*)
- Cross-reference patterns
- Complete API/data model specs

---

## Conventions Summary

### Folder Naming
```
NN-lowercase-hyphenated/
Examples: 01-authentication/, 22-golang-search-cli/
```

### File Naming
```
NN-lowercase-hyphenated.md
Examples: 00-overview.md, 01-architecture.md, 99-consistency-report.md
```

### Reserved Prefixes
| Prefix | Purpose |
|--------|---------|
| `00-` | Index/overview file |
| `01-32` | Content files (sequential) |
| `98-` | Test plans |
| `99-` | Meta files (reports, consistency) |

### Cross-References
- Same folder: `./file.md`
- Parent folder: `../folder/file.md`
- Two levels up: `../../folder/file.md`

### Required Sections in Each Spec File
```markdown
# Title

**Version:** X.Y.Z  
**Status:** Active | Planned | Draft  
**Updated:** YYYY-MM-DD  

---

## Overview
Brief description

---

## [Content Sections]

---

## Related Specs
- [Link](./path.md)
```

---

## Creating New Features

1. Create folder: `05-features/NN-feature-name/`
2. Add index: `00-overview.md` with feature summary
3. Add specs: `01-component.md`, `02-another.md`, etc.
4. Add tests folder if needed: `tests/`
5. Update parent index: `05-features/00-overview.md`
6. Update master index: `00-master-index.md`

---

## Minimal Context Package

For quick onboarding, provide these 4 files:

1. `00-master-index.md` (full structure)
2. `05-features/00-overview.md` (feature index)
3. `.lovable/memories/spec-management/file-structure-conventions.md` (conventions)
4. One example feature folder (e.g., `05-features/01-authentication/`)

---

## Full Context Package

For comprehensive understanding, add:

5. `00-overview.md` (project philosophy)
6. `03-project-overview/00-overview.md` (architecture)
7. `04-coding-guidelines/00-overview.md` (code standards)
8. `05-features/24-code-generation-system/` (complete example)
9. `08-roadmap-overview/00-overview.md` (implementation phases)

---

## Related Docs

- [Master Index](./00-master-index.md)
- [Features Overview](./05-features/00-overview.md)
- [Coding Guidelines](./04-coding-guidelines/00-overview.md)
