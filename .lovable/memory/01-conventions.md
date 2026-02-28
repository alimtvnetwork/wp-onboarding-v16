# Conventions

> **Location:** `.lovable/memory/01-conventions.md`  
> **Updated:** 2026-02-25

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

### File Naming (ALL Languages — PascalCase)

File names MUST use PascalCase matching the primary definition name. This applies to **all languages** (Go, PHP, TypeScript, PowerShell).

| Language | Convention | Example |
|----------|-----------|---------|
| Go | `PascalCase.go` | `ClientApiCall.go`, `UploaderLifecycle.go` |
| PHP (namespaced) | `PascalCase.php` | `StatusType.php`, `SnapshotManager.php` |
| PHP (WP non-namespaced) | `class-{kebab-case}.php` | `class-exam-manager.php` |
| TypeScript | `PascalCase.ts(x)` | `RemotePluginFileBrowser.tsx` |

> **Go package directories** remain `snake_case` (e.g., `site_health/`). Only file names change.
> **Go enum package directories** use lowercase `type` suffix with no underscores (e.g., `httpmethodtype/`, `stagestatustype/`).

### Class Naming
| Type | Convention | Example |
|------|------------|---------|
| Main class | `{PluginName}` | `ExamManager` |
| Admin class | `{PluginName}_Admin` | `ExamManager_Admin` |
| Enum (PHP 8.1+) | PascalCase with `Type` suffix | `PluginConfigType`, `OptionNameType` |

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

## API & Endpoint Configuration

### Endpoint Resolution (`src/lib/endpoints.ts`)

All API and WebSocket URLs are resolved through a centralized module:

| Env Variable | Purpose | Example |
|--------------|---------|---------|
| `VITE_API_URL` | Backend origin | `http://localhost:8080` |
| `VITE_WS_URL` | WebSocket URL | `ws://localhost:8080/ws` |

**Rules:**
- Never hardcode `localhost:8080` in components
- Always use `resolveApiUrl()` or `resolveWsUrl()`
- Detect HTML-instead-of-JSON responses (error code `E9005`)

### Error Modal Requirements

**Mandatory:** Every user-visible error must display in the **Global Error Modal** with:
- Fully resolved request URL (`requestUrl`)
- Configured API base (`apiBase`)
- `VITE_API_URL` environment state

---

## Backend Logging

### Timestamp Configuration

- **Single source of truth:** `config.json` → `logging.timeFormat`
- **Default format:** `2006-01-02 03:04:05 PM` (12-hour clock)
- **Never hardcode** timestamp formats in logger code

---

*Keep this file updated when conventions change.*
