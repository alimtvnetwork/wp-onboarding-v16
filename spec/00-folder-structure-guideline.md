# Spec Folder Structure Guideline

> **Version:** 3.0.0  
> **Last Updated:** 2026-01-28  
> **Status:** PRODUCTION-READY

---

## Overview

This document defines the organizational structure for all specification files within the `spec/` directory. It ensures consistent naming, logical grouping, and easy navigation for both human developers and AI agents.

**This is the MASTER GUIDE** for how specifications are managed across all projects.

---

## Key Principles

1. **Consistency First** — All files follow strict naming conventions
2. **AI-Optimized** — Structure enables RAG indexing and retrieval
3. **Hierarchical Organization** — Nested folders for logical grouping
4. **Cross-Reference Rich** — All specs link to related specs
5. **Version Controlled** — All files include version, status, and date
6. **Pipeline-Aligned** — Folder structure mirrors the spec generation pipeline

---

## Standard Project Folder Structure (v3.1)

Every project MUST follow this structure with numbered folders:

```
{project-slug}/
├── 00-overview.md                    # Root overview (minimal, links to 03)
│
├── 01-ideas/                         # Raw ideas, verbatim voice transcriptions
│   ├── README.md                     # Guidelines for idea capture
│   ├── 01-{idea-slug}.md             # Numbered idea files
│   ├── 02-{idea-slug}.md
│   └── ...
│
├── 02-instructions/                  # Refined instructions (promoted from ideas)
│   ├── README.md                     # Instruction formatting guidelines
│   ├── 01-{instruction-slug}.md      # Numbered instruction files
│   ├── 02-{instruction-slug}.md
│   └── ...
│
├── 03-project-overview/              # Project overview & navigation index
│   ├── 00-overview.md                # Full project overview
│   └── ...
│
├── 04-coding-guidelines/             # Project-specific coding standards
│   ├── 00-overview.md                # Coding guidelines index
│   ├── 01-{language}-guidelines.md   # Language-specific guidelines
│   ├── 02-naming-conventions.md
│   └── ...
│
├── 05-split-spec/                    # Feature-based specifications
│   ├── 00-overview.md                # Split spec index
│   ├── 01-{feature-name}/            # Feature folder
│   │   ├── 00-overview.md            # Feature overview
│   │   ├── 01-{component}.md         # Feature components
│   │   ├── 02-{component}.md
│   │   └── tests/                    # E2E tests for this feature
│   │       ├── 01-{test-scenario}.md
│   │       └── ...
│   ├── 02-{feature-name}/
│   │   ├── 00-overview.md
│   │   └── ...
│   └── ...
│
├── 06-error-management/              # Error handling specifications
│   ├── 00-overview.md                # Error management index
│   ├── frontend/                     # Frontend error handling
│   │   ├── 01-error-codes.md
│   │   ├── 02-error-boundaries.md
│   │   └── ...
│   ├── backend/                      # Backend error handling
│   │   ├── 01-error-codes.md
│   │   ├── 02-recovery-strategies.md
│   │   └── ...
│   └── shared/                       # Shared error utilities
│       └── 01-error-constants.md
│
├── 07-database-design/               # Database and system architecture
│   ├── 00-overview.md                # Database design index
│   ├── 01-schema.md                  # Database schema
│   ├── 02-migrations.md              # Migration strategies
│   ├── 03-relationships.md           # Entity relationships
│   └── diagrams/                     # ERD and architecture diagrams
│       ├── 01-erd.md
│       └── 02-system-architecture.md
│
├── 08-roadmap-overview/              # Project roadmap and high-level docs
│   ├── 00-overview.md                # Roadmap index
│   ├── 01-roadmap.md                 # Implementation phases
│   ├── 02-summary.md                 # Quick reference summary
│   ├── 03-glossary.md                # Terms and definitions
│   └── 04-implementation-guidelines.md
│
├── 09-diagrams/                      # Cross-cutting workflow diagrams
│   ├── 00-overview.md
│   └── ...
│
├── 10-research/                      # Research notes and explorations
│   ├── 00-overview.md
│   └── ...
│
└── 99-consistency-report.md          # Auto-generated consistency report
```

---

## Pipeline Flow

The folder structure mirrors the spec generation pipeline:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        SPEC GENERATION PIPELINE                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Voice/Text Input                                                        │
│       ↓                                                                  │
│  ┌─────────────────┐                                                     │
│  │  01-ideas/      │  ← Raw ideas, verbatim transcriptions               │
│  └────────┬────────┘                                                     │
│           ↓                                                              │
│  ┌─────────────────┐                                                     │
│  │ 02-instructions/│  ← Refined, actionable instructions                 │
│  └────────┬────────┘                                                     │
│           ↓                                                              │
│  ┌─────────────────┐                                                     │
│  │04-coding-guide/ │  ← Coding standards applied                         │
│  └────────┬────────┘                                                     │
│           ↓                                                              │
│  ┌─────────────────┐                                                     │
│  │06-error-mgmt/   │  ← Error handling defined                           │
│  └────────┬────────┘                                                     │
│           ↓                                                              │
│  ┌─────────────────┐                                                     │
│  │ 05-split-spec/  │  ← Feature specs with E2E tests                     │
│  └────────┬────────┘                                                     │
│           ↓                                                              │
│  ┌─────────────────┐                                                     │
│  │07-database-design│ ← Schema, architecture, ERD                        │
│  └────────┬────────┘                                                     │
│           ↓                                                              │
│  ┌─────────────────┐                                                     │
│  │08-roadmap-over/ │  ← Roadmap, glossary, guidelines                    │
│  └─────────────────┘                                                     │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Folder Descriptions

### 01-ideas/

**Purpose:** Capture raw ideas exactly as spoken or typed, preserving original intent.

**Contents:**
- Verbatim voice transcriptions
- Raw text input before refinement
- Brainstorming notes
- Feature proposals in their initial form

**File Format:**
```markdown
# Idea: {Title}

**ID:** idea_{uuid}  
**Status:** draft | refined | promoted | archived  
**Source:** voice | text  
**Created:** {ISO8601}  

---

## Raw Content

{Verbatim transcription or original text}

---

## Notes

{Optional clarifications}
```

---

### 02-instructions/

**Purpose:** Refined, actionable instructions promoted from ideas.

**Contents:**
- Clarified and proofread ideas
- Step-by-step implementation guidance
- Acceptance criteria
- Promoted content ready for spec generation

**File Format:**
```markdown
# Instruction: {Title}

**ID:** instruction_{uuid}  
**Status:** pending | in-progress | completed  
**Source Idea:** [01-{idea-slug}.md](../01-ideas/01-{idea-slug}.md)  
**Created:** {ISO8601}  

---

## Summary

{One paragraph description}

---

## Steps

1. {Step 1}
2. {Step 2}
3. ...

---

## Acceptance Criteria

- [ ] Criterion 1
- [ ] Criterion 2
```

---

### 03-project-overview/

**Purpose:** Full project overview and navigation index.

**Contents:**
- Project summary and architecture diagram
- Complete folder navigation index
- Feature status table
- Pipeline flow visualization
- Quick links to all major sections

---

### 04-coding-guidelines/

**Purpose:** Project-specific coding standards that complement the general-spec.

**Contents:**
- Language-specific conventions
- Naming conventions for this project
- Architecture patterns to follow
- Code review checklist

**Note:** References `../general-spec/` for universal standards.

---

### 06-error-management/

**Purpose:** Comprehensive error handling for both frontend and backend.

**Structure:**
```
06-error-management/
├── 00-overview.md           # Error philosophy and cross-references
├── frontend/                # React error handling
│   ├── 01-error-codes.md    # Frontend error code ranges
│   ├── 02-error-boundaries.md
│   └── 03-user-messaging.md
├── backend/                 # Go/API error handling
│   ├── 01-error-codes.md    # Backend error code ranges
│   ├── 02-recovery-strategies.md
│   └── 03-logging-patterns.md
└── shared/
    └── 01-error-constants.md  # Shared codes used by both
```

**Error Code Ranges:**
| Range | Category | Owner |
|-------|----------|-------|
| 1xxx | Validation | Shared |
| 2xxx | Auth/Authorization | Backend |
| 3xxx | Database | Backend |
| 4xxx | External Services | Backend |
| 5xxx | Business Logic | Shared |
| 6xxx | File System | Backend |
| 7xxx | Configuration | Backend |
| 8xxx | Security/SSRF | Backend |
| 9xxx | System | Backend |

---

### 05-split-spec/

**Purpose:** Feature-based specifications organized for focused development.

**Structure:**
```
05-split-spec/
├── 00-overview.md                    # Feature index and status
├── 01-authentication/                # Feature: Authentication
│   ├── 00-overview.md                # Feature summary
│   ├── 01-login-flow.md              # Specific component
│   ├── 02-session-management.md
│   ├── 03-password-reset.md
│   └── tests/                        # E2E tests only
│       ├── 01-login-e2e.md
│       └── 02-password-reset-e2e.md
├── 02-file-management/               # Feature: File Management
│   ├── 00-overview.md
│   ├── 01-upload.md
│   ├── 02-download.md
│   └── tests/
│       └── 01-file-ops-e2e.md
└── ...
```

**Key Rules:**
- Each feature is a numbered folder
- Tests are E2E only (no unit tests in specs)
- Each feature folder has its own `00-overview.md`
- Related specs stay together for tight cohesion

---

### 08-roadmap-overview/

**Purpose:** High-level project planning and reference documentation.

**Contents:**
- Implementation roadmap with phases
- Project summary and quick reference
- Glossary of terms
- Implementation guidelines and checklists

---

### 09-diagrams/

**Purpose:** Cross-cutting workflow diagrams.

**Contents:**
- System architecture overview
- Workflow diagrams
- Data flow visualizations

---

### 10-research/

**Purpose:** Research notes and technical explorations.

**Contents:**
- Technology investigations
- Proof of concept notes
- Reference materials

---

### 07-database-design/

**Purpose:** Database architecture and system design.

**Contents:**
- Schema definitions with tables and columns
- Migration strategies and versioning
- Entity relationships
- ERD diagrams
- System architecture diagrams

**Structure:**
```
07-database-design/
├── 00-overview.md            # Database design index
├── 01-schema.md              # Complete schema definition
├── 02-migrations.md          # Migration patterns
├── 03-relationships.md       # FK constraints, indexes
└── diagrams/
    ├── 01-erd.md             # Entity-relationship diagram
    └── 02-system-architecture.md
```

---

## Legacy Folder Support

Projects may retain legacy folders during migration:

```
{project-slug}/
├── 01-backend/               # LEGACY: Backend specs (pre-v3.0)
├── 02-frontend/              # LEGACY: Frontend specs (pre-v3.0)
└── ...new v3.0 structure...
```

**Migration Path:**
1. Create new v3.0 folder structure
2. Move infrastructure specs to appropriate locations
3. Group feature specs into `05-split-spec/{feature}/`
4. Archive or delete legacy folders once migration complete

---

## Global Spec Directory

```
spec/
├── 00-folder-structure-guideline.md    # THIS FILE - Master structure guide
├── spec-cheatsheet.md                  # Quick reference for common patterns
│
├── general-spec/                        # Architecture-wide standards (language-agnostic)
│   ├── 00-overview.md                   # Master index for general-spec
│   ├── 01-foundation/                   # Core coding principles
│   ├── 02-systems/                      # Cross-cutting systems
│   ├── 03-quality/                      # Testing, organization, API standards
│   ├── 04-advanced/                     # Security, caching, database
│   ├── 05-ux/                           # i18n, accessibility, performance
│   ├── 06-devops/                       # Documentation, CI/CD
│   ├── 07-observability/                # Monitoring, incidents
│   ├── 08-data-governance/              # Classification, retention
│   ├── 09-api-integration/              # GraphQL, WebSocket, MQ
│   ├── 10-wordpress/                    # WordPress-specific
│   └── 99-meta/                         # AI readability, cheatsheets
│
├── powershell-integration/              # ⭐ CROSS-PROJECT: Build & Run scripts
│   ├── 00-overview.md                   # Overview and quick start
│   ├── 01-configuration-schema.md       # JSON config format
│   ├── 02-script-reference.md           # CLI flags and functions
│   ├── 03-integration-guide.md          # How to add to any project
│   ├── 04-error-codes.md                # Exit codes (9500-9599)
│   ├── 05-firewall-rules.md             # Windows firewall setup
│   ├── schemas/                         # JSON Schema for validation
│   ├── templates/                       # run.ps1, powershell.json
│   └── examples/                        # Sample configs for different layouts
│
├── spec-management-software/            # Example project using v3.1 structure
│   ├── 00-overview.md
│   ├── 01-ideas/
│   ├── 02-instructions/
│   ├── 03-project-overview/
│   ├── 04-coding-guidelines/
│   ├── 05-features/                     # Feature specs (29+ modules)
│   ├── 06-error-management/
│   ├── 07-database-design/
│   ├── 08-roadmap-overview/
│   ├── 09-diagrams/
│   ├── 10-research/
│   └── 99-consistency-report.md
│
└── wp-plugin/                           # WordPress plugins (uses v3.0 structure)
    └── {plugin-slug}/
        └── ...v3.0 structure...
```

---

## Naming Conventions

### File Naming Pattern

```
{nn}-{topic-name}.md
```

| Component | Rule | Example |
|-----------|------|---------|
| `{nn}` | Two-digit sequence number (00-99) | `01`, `42`, `99` |
| `{topic-name}` | Lowercase, hyphen-separated | `login-flow`, `error-codes` |
| Extension | Always `.md` | `.md` |

### Folder Naming Pattern

```
{nn}-{folder-name}/
```

| Component | Rule | Example |
|-----------|------|---------|
| `{nn}` | Two-digit sequence number | `01`, `05`, `07` |
| `{folder-name}` | Lowercase, hyphen-separated | `ideas`, `split-spec` |

### Examples

✅ **Correct:**
- `01-ideas/01-voice-input-feature.md`
- `05-split-spec/02-authentication/01-login-flow.md`
- `07-database-design/diagrams/01-erd.md`

❌ **Incorrect:**
- `Ideas/` (not numbered, PascalCase)
- `split_spec/` (underscores)
- `1-authentication/` (single digit)

---

## Cross-Reference Rules

### Internal References

Always use **relative paths** from the current file location:

```markdown
<!-- From 05-split-spec/01-auth/01-login.md to 07-database-design/01-schema.md -->
See [Database Schema](../../07-database-design/01-schema.md)

<!-- From 05-split-spec/01-auth/tests/01-login-e2e.md to parent feature -->
See [Login Flow](../01-login-flow.md)
```

### Standard Reference Targets

| Reference | From split-spec feature | Path |
|-----------|------------------------|------|
| Error Codes | `05-split-spec/01-auth/` | `../../06-error-management/backend/01-error-codes.md` |
| Coding Guidelines | `05-split-spec/01-auth/` | `../../04-coding-guidelines/01-go-guidelines.md` |
| Database Schema | `05-split-spec/01-auth/` | `../../07-database-design/01-schema.md` |
| General Spec | `05-split-spec/01-auth/` | `../../../general-spec/01-foundation/...` |

---

## Consistency Checks

Before committing spec changes:

- [ ] File follows naming convention `{nn}-{topic-name}.md`
- [ ] Folder follows naming convention `{nn}-{folder-name}/`
- [ ] Has version, status, and date in header
- [ ] Includes Overview with summary
- [ ] Has Cross-References section
- [ ] Uses numbered section headers (N.1, N.2)
- [ ] Code blocks have language tags
- [ ] Tables use consistent column format
- [ ] Mermaid diagrams render correctly
- [ ] Acceptance criteria included where applicable
- [ ] Links use relative paths
- [ ] Tests are E2E only (in `tests/` folders)
- [ ] Feature specs grouped in `05-split-spec/`

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-27 | Initial version with basic structure |
| 2.0.0 | 2026-01-28 | Added RAG artifacts structure, path management |
| 3.0.0 | 2026-01-28 | **Major restructure:** New 7-folder project structure, feature-based split-spec, E2E tests only policy |
| 3.1.0 | 2026-01-28 | **Renumbered folders:** 03-project-overview, 04-coding-guidelines, 05-split-spec, 06-error-management, 07-database-design, 08-roadmap-overview, 09-diagrams, 10-research |

---

## See Also

- [General Spec Overview](general-spec/00-overview.md)
- [AI Readability Review](general-spec/99-meta/01-ai-readability-review-meta.md)
- [Cheatsheet](general-spec/99-meta/02-cheatsheet-meta.md)
