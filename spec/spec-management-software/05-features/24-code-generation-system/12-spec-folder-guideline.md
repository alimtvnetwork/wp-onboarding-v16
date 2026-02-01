# Spec Folder Guideline

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Purpose

This document is a **compact AI-training guide** that explains the specification folder structure, organization patterns, and conventions used in the Spec Management Software. Any AI model reading this document should understand how to navigate, interpret, and follow the specification structure.

**This file location:** `spec/spec-management-software/05-features/24-code-generation-system/12-spec-folder-guideline.md`

---

## Root Structure

```
spec/
├── general-spec/                    # Cross-project standards
│   └── 01-foundation/               # Foundational guidelines
│       └── 01-coding-standards-foundation.md
│
└── spec-management-software/        # Project-specific specs
    ├── 00-master-index.md           # ★ START HERE - Master navigation
    ├── 99-consistency-report.md     # Quality tracking
    │
    ├── 01-general-info/             # Project metadata
    ├── 02-project-management/       # Users, projects, settings
    ├── 03-project-overview/         # High-level architecture
    ├── 04-coding-guidelines/        # Code conventions
    ├── 05-features/                 # ★ MAIN FEATURES - All feature specs
    ├── 06-error-management/         # Error codes and handling
    ├── 07-database-design/          # Schema and migrations
    └── 08-roadmap-overview/         # Roadmap and phases
```

---

## Navigation Pattern

### Always Start At Master Index

```
00-master-index.md → Find feature → Navigate to 05-features/{folder}/00-overview.md
```

The **00-master-index.md** contains:
- Complete folder listing with descriptions
- Current implementation status per feature
- Cross-reference links to all major documents

### Feature Folder Structure

Each feature in `05-features/` follows this pattern:

```
05-features/
└── {NN}-{feature-name}/             # NN = 01-99
    ├── 00-overview.md               # ★ Feature entry point
    ├── 01-{first-topic}.md          # Core specification
    ├── 02-{second-topic}.md
    ├── ...
    ├── {NN}-{topic}.md
    └── 99-consistency-report.md     # Optional: feature-specific quality
```

---

## Folder Numbering Convention

| Range | Purpose | Examples |
|-------|---------|----------|
| 01-09 | Core Infrastructure | 01-file-system, 02-project-management |
| 10-19 | Feature Modules | 10-spec-editor, 11-template-system |
| 20-22 | Advanced Features | 20-history-tracking, 21-knowledge-memory |
| 23-24 | CLI Tools | 23-build-runner-cli, 24-code-generation-system |
| 06 | AI Integration | 06-ai-integration (special placement) |

### Current Feature Folders (05-features)

| Folder | Description | Status |
|--------|-------------|--------|
| 01-file-system | File operations and storage | Active |
| 02-project-management | Users, projects, settings | Active |
| 06-ai-integration | LLM models, RAG, instructions | Active |
| 10-spec-editor | Markdown editing and preview | Active |
| 20-history-tracking | Version history and snapshots | Active |
| 21-knowledge-memory | RAG knowledge base | Active |
| 22-golang-search-cli | gsearch CLI tool | Active |
| 23-build-runner-cli | brun CLI tool | Active |
| 24-code-generation-system | AI code generation | Draft |

---

## File Naming Conventions

### Prefix Numbering

```
00-overview.md          # Always first - entry point
01-architecture.md      # System design
02-data-models.md       # Database entities
03-api-endpoints.md     # REST API
...
99-consistency-report.md  # Quality tracking (if present)
```

### Alphanumeric Prefixes (Collision Prevention)

When files need sub-numbering:

```
02a-user-model.md       # First sub-file under 02
02b-project-model.md    # Second sub-file under 02
02c-settings-model.md   # Third sub-file under 02
```

---

## Document Structure

### Standard Header

Every specification file starts with:

```markdown
# {Document Title}

**Version:** 1.0.0  
**Status:** Draft | Active | Deprecated  
**Updated:** YYYY-MM-DD  

---

## Overview

{Brief description of what this document covers}

**Cross-References:**
- [Related Spec 1](./path-to-spec.md)
- [Related Spec 2](../other-folder/spec.md)

---
```

### Standard Sections

1. **Overview** - What and why
2. **Data Models** - Database entities (Go structs with GORM tags)
3. **API Endpoints** - REST endpoints with request/response
4. **Error Codes** - Specific error codes for this feature
5. **Configuration** - Configuration keys (seeded in database)
6. **Related Specs** - Cross-references to other documents

---

## Cross-Reference Patterns

### Relative Path References

Always use relative paths:

```markdown
<!-- Same folder -->
[Overview](./00-overview.md)

<!-- Parent folder -->
[Master Index](../../00-master-index.md)

<!-- Different feature folder -->
[AI Integration](../06-ai-integration/00-overview.md)
```

### Standard Cross-Reference Format

```markdown
**Cross-References:**
- [Feature Overview](./00-overview.md)
- [Data Models](./02-data-models.md)
- [Error Management](../../06-error-management/02-error-code-registry.md)
```

---

## Error Code Ranges

| Range | Domain | Owner Folder |
|-------|--------|--------------|
| 1xxx | Validation | 06-error-management |
| 2xxx | Auth/Authorization | 06-error-management |
| 3xxx | Database | 07-database-design |
| 4xxx | External Services | 06-error-management |
| 5xxx | Business Logic | 06-error-management |
| 6xxx | File System | 01-file-system |
| 7xxx | Configuration/Build | 22-golang-search-cli, 23-build-runner-cli |
| 8xxx | Code Generation | 24-code-generation-system |
| 11xxx | Instruction System | 06-ai-integration |

---

## Data Model Format

### GORM Entity Standard

```go
type EntityName struct {
    ID        string    `gorm:"primaryKey;type:text"`
    FieldName string    `gorm:"type:text;not null;index"`
    CreatedAt time.Time
    UpdatedAt time.Time
    
    // Relationships
    RelatedEntity RelatedEntity `gorm:"foreignKey:EntityID"`
}
```

### Database Table Naming

- **Tables**: PascalCase (`UserSessions`, `SpecFiles`)
- **Columns**: camelCase as Go struct, snake_case in SQLite
- **Primary Keys**: `ID` as UUID string

---

## Configuration Key Format

All configuration uses dot notation and is stored in the database:

```
{domain}.{category}.{setting}

Examples:
- codegen.repo.rootDirectory
- codegen.parallel.maxWorkers
- credits.rate.aiRequestPerKTokens
- llm.models.coding
```

---

## AI Reading Instructions

### How to Navigate

1. **Start at `00-master-index.md`** - Understand the full project structure
2. **Find the relevant feature folder** in `05-features/`
3. **Read `00-overview.md`** of that feature for context
4. **Follow cross-references** to related specs as needed
5. **Check `99-consistency-report.md`** for quality status

### How to Understand Dependencies

- Check **Cross-References** section in each document
- Look for **Related Specs** at document end
- Follow **Error Code** references to error management

### How to Write Following This Structure

1. Use the **standard header** format
2. Include **Cross-References** section
3. Follow **GORM entity** format for data models
4. Use **relative paths** for all internal links
5. Add entries to **00-overview.md** document index
6. Update **00-master-index.md** if adding new folders

---

## Quick Reference Card

```
┌────────────────────────────────────────────────────────────────┐
│                    SPEC NAVIGATION CHEAT SHEET                  │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│  START: spec/spec-management-software/00-master-index.md       │
│                                                                 │
│  FEATURES: spec/spec-management-software/05-features/          │
│    └── {NN}-{feature}/00-overview.md                           │
│                                                                 │
│  ERRORS: spec/spec-management-software/06-error-management/    │
│    └── 02-error-code-registry.md                               │
│                                                                 │
│  DATABASE: spec/spec-management-software/07-database-design/   │
│    └── 02-entity-relationships.md                              │
│                                                                 │
│  NAMING:                                                        │
│    Files:     kebab-case (user-service.go)                     │
│    Tables:    PascalCase (UserSessions)                        │
│    Functions: camelCase (getUserById)                          │
│    Errors:    ERR_CATEGORY_NAME                                │
│                                                                 │
│  VERSIONS:                                                      │
│    Draft → Active → Deprecated                                  │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Related Specs

- [Master Index](../../00-master-index.md)
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)
- [Error Code Registry](../../06-error-management/02-error-code-registry.md)
- [Database Design](../../07-database-design/00-overview.md)
