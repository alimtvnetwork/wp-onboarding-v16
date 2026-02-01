# AI Training: Conventions

**Version:** 1.0.0  
**Updated:** 2026-01-29  
**Purpose:** Naming and structure rules for spec authoring

---

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

---

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

---

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

---

## Versioning

Every spec file MUST include frontmatter:

```markdown
# Feature Name

**Version:** 1.0.0  
**Status:** Draft | Planned | Active | Complete  
**Updated:** YYYY-MM-DD  
```

---

## Special Prefixes

| Prefix | Purpose |
|--------|---------|
| `00-` | Overview/index file |
| `98-` | Test plans |
| `99-` | Metadata, consistency reports |

---

## Single Source of Truth

- ONE master index at `spec/{project}/00-master-index.md`
- NO duplicate documentation folders
- Memories consolidate in `.lovable/memories/`
