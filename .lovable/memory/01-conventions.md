# Conventions

> **Location:** `.lovable/memory/01-conventions.md`  
> **Updated:** 2026-02-01

---

## File Naming

### Numbered Prefix Pattern

All files in memory/spec folders use numbered prefixes:

```
01-name-of-file.md
02-another-file.md
```

**Rules:**
- Two-digit prefix (01, 02, 03...)
- Hyphen-separated words
- Lowercase only
- `.md` extension for documentation

---

## Folder Organization

### Principle: Fewer Files, More Consolidation

- Keep folder file count low
- Consolidate related content into single files
- Use sections within files rather than creating new files
- Archive completed items by updating status, not moving files

---

## Spec Folder Structure

Each project spec follows this pattern:

```
spec/<project-name>/
├── 00-overview.md           # Project summary, document index
├── 01-core-architecture.md  # System design
├── 02-cli-interface.md      # CLI commands (if applicable)
├── ...                      # Feature-specific specs
├── 66-shared-constants.md   # SSOT for enums, error codes, constants
├── 99-consistency-report.md # Cross-reference validation
└── ideas/                   # Feature proposals (numbered)
```

**Rules:**
- Numbered prefixes indicate reading order
- `00-` for overview/entry point
- `66-` for shared constants (SSOT)
- `99-` for validation/consistency reports

---

## WordPress Plugin Spec Structure

For WordPress plugins, follow this enhanced pattern:

```
spec/wp-plugin/<plugin-name>/
├── 00-overview.md                    # Master index
├── 01-admin-backend/
│   ├── 00-overview.md                # Backend summary
│   └── split-spec/                   # Individual backend specs
│       ├── 01-coding-spec.md
│       ├── 02-error-management.md
│       └── ...
├── 02-frontend/
│   ├── 00-overview.md                # Frontend summary
│   └── split-spec/                   # Individual frontend specs
├── diagrams/                         # Mermaid/visual diagrams
├── ideas/                            # Feature proposals
├── 60-ai-implementation-checklist.md # Critical algorithms
├── 61-common-implementation-pitfalls.md # Anti-patterns
├── 66-shared-constants.md            # SSOT
└── 99-*-report.md                    # Consistency/audit reports
```

---

## PHP/WordPress Coding Standards

### Database Columns
- **SQL columns:** PascalCase (`UserId`, `CreatedAt`, `IsEnabled`)
- **ORM properties:** camelCase (`userId`, `createdAt`, `isEnabled`)

### File Naming
| Type | Convention | Example |
|------|------------|---------|
| Class file | `class-{class-name}.php` | `class-exam-manager.php` |
| Template | `{template-name}.php` | `single-exam.php` |

### Class Naming
| Type | Convention | Example |
|------|------------|---------|
| Main class | `{Plugin_Name}` | `Exam_Manager` |
| Admin class | `{Plugin_Name}_Admin` | `Exam_Manager_Admin` |

### Security Requirements
- Always use nonces for forms
- Always check capabilities
- Always sanitize input, escape output
- Always use prepared statements with `$wpdb`

---

## React/TypeScript Conventions

- Use functional components with hooks
- Prefer named exports
- Use semantic Tailwind tokens from design system
- Keep components focused and small (<300 lines)
- No hardcoded colors—use CSS variables

### File Organization

```
src/
├── components/    # Reusable UI components
├── pages/         # Route pages
├── hooks/         # Custom React hooks
├── lib/           # Utilities and helpers
└── types/         # TypeScript type definitions
```

---

*Keep this file updated when conventions change.*
