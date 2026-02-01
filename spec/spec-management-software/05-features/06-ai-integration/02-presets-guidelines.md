# Presets & Guidelines System

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

The Presets & Guidelines system provides layered project templates and configuration modules that enable consistent project creation and customizable spec standards. Presets define initial project structure, while Guidelines provide reusable configuration modules with inheritance.

---

## 10.1 Core Concepts

### Presets

A **Preset** is a project template that defines:
- Initial folder structure
- Default files with boilerplate content
- Pre-assigned guideline modules
- Technology stack metadata

### Guidelines

A **Guideline** is a configuration module that defines:
- Standards for a specific concern (coding, logging, errors, etc.)
- Scope level for inheritance (global → category → language → project)
- Content in both Markdown (human) and JSON (machine) formats

### Inheritance Model

```
┌─────────────────────────────────────────────────────────────────────────┐
│  GLOBAL GUIDELINES (apply to all projects)                              │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │  CATEGORY GUIDELINES (apply to category and children)               ││
│  │  ┌─────────────────────────────────────────────────────────────────┐││
│  │  │  LANGUAGE GUIDELINES (apply to language-specific projects)      │││
│  │  │  ┌─────────────────────────────────────────────────────────────┐│││
│  │  │  │  PROJECT GUIDELINES (override for specific project)         ││││
│  │  │  └─────────────────────────────────────────────────────────────┘│││
│  │  └─────────────────────────────────────────────────────────────────┘││
│  └─────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 10.2 Preset Types

### Built-in Presets

| Preset ID | Name | Description | Guidelines Included |
|-----------|------|-------------|---------------------|
| `general` | General Spec | Basic project structure | coding, file-formatting, logging |
| `typescript` | TypeScript Project | TS/Node.js conventions | coding, file-formatting, logging, error-handling |
| `php-wordpress` | WordPress Plugin | WP plugin structure | wp-coding, wp-hooks, wp-security, logging |
| `golang` | Go Backend | Go service structure | go-coding, error-handling, logging |
| `python` | Python Project | Python conventions | py-coding, py-testing, logging |
| `blank` | Blank Project | Empty project | (none) |

### Preset Definition Schema

```typescript
interface Preset {
  id: string;                    // UUID
  slug: string;                  // unique identifier (e.g., 'typescript')
  name: string;                  // display name
  description: string;           // brief description
  category: string;              // grouping (e.g., 'Language-Specific', 'Framework')
  icon: string;                  // icon identifier (e.g., 'typescript', 'wordpress')
  isBuiltIn: boolean;            // true = system preset, false = user-created
  isEnabled: boolean;            // available for use
  folderStructure: FolderNode[]; // initial folder/file tree
  guidelineIds: string[];        // default guidelines to assign
  metadata: Record<string, string>; // additional key-value pairs
  createdAt: string;             // ISO8601
  updatedAt: string;             // ISO8601
}

interface FolderNode {
  name: string;                  // folder or file name
  type: 'folder' | 'file';       // node type
  children?: FolderNode[];       // nested items (folders only)
  templateContent?: string;      // boilerplate content (files only)
}
```

### Example Preset: TypeScript

```json
{
  "id": "uuid-preset-typescript",
  "slug": "typescript",
  "name": "TypeScript Project",
  "description": "Full TypeScript project with Node.js conventions",
  "category": "Language-Specific",
  "icon": "typescript",
  "isBuiltIn": true,
  "isEnabled": true,
  "folderStructure": [
    {
      "name": "00-overview.md",
      "type": "file",
      "templateContent": "# {{projectName}}\n\n**Version:** 0.1.0\n**Status:** Draft\n\n---\n\n## Summary\n\n{{projectDescription}}\n"
    },
    {
      "name": "01-architecture",
      "type": "folder",
      "children": [
        {
          "name": "01-system-design.md",
          "type": "file",
          "templateContent": "# System Design\n\n## Overview\n\n(TBD)\n"
        }
      ]
    },
    {
      "name": "02-components",
      "type": "folder",
      "children": []
    },
    {
      "name": "03-testing",
      "type": "folder",
      "children": []
    }
  ],
  "guidelineIds": ["coding-ts", "file-formatting", "logging", "error-handling"],
  "metadata": {
    "language": "typescript",
    "runtime": "node"
  }
}
```

---

## 10.3 Guideline Modules

### Built-in Guideline Modules

| Module Slug | Name | Scope | Description |
|-------------|------|-------|-------------|
| `coding-general` | Coding Standards | global | Naming, structure, best practices |
| `coding-ts` | TypeScript Coding | language | TS-specific conventions |
| `coding-php` | PHP Coding | language | PHP-specific conventions |
| `coding-go` | Go Coding | language | Go-specific conventions |
| `coding-py` | Python Coding | language | Python-specific conventions |
| `file-formatting` | File Formatting | global | Header format, line length, sections |
| `error-handling` | Error Management | global | Error codes, exception patterns |
| `logging` | Logging System | global | Log levels, structured logging |
| `testing` | Testing Standards | global | Test organization, coverage |
| `acceptance-criteria` | Acceptance Criteria | global | Feature validation format |
| `wp-hooks` | WordPress Hooks | category | WP hook registration patterns |
| `wp-security` | WordPress Security | category | WP sanitization, nonces, capabilities |

### Guideline Definition Schema

```typescript
interface Guideline {
  id: string;                    // UUID
  slug: string;                  // unique identifier
  name: string;                  // display name
  description: string;           // brief description
  scope: GuidelineScope;         // inheritance level
  scopeTargetId: string | null;  // target project/category ID (null for global)
  isBuiltIn: boolean;            // true = system, false = user-created
  isEnabled: boolean;            // active for inheritance
  priority: number;              // conflict resolution (higher wins)
  contentMarkdown: string;       // human-readable spec
  contentJson: string;           // machine-parseable rules (optional)
  version: string;               // semantic version
  createdAt: string;             // ISO8601
  updatedAt: string;             // ISO8601
}

type GuidelineScope = 'global' | 'category' | 'language' | 'project';
```

### Guideline JSON Schema

The `contentJson` field supports structured rules for AI processing:

```json
{
  "rules": [
    {
      "id": "naming-functions",
      "type": "naming",
      "pattern": "^[a-z][a-zA-Z0-9]*$",
      "applies_to": "functions",
      "severity": "error",
      "message": "Function names must be camelCase"
    },
    {
      "id": "max-line-length",
      "type": "formatting",
      "value": 100,
      "severity": "warning",
      "message": "Lines should not exceed 100 characters"
    }
  ],
  "extends": ["coding-general"]
}
```

---

## 10.4 Inheritance Resolution

### Resolution Algorithm

```go
func ResolveGuidelines(projectId string) []Guideline {
    // 1. Get project info (path, language, category)
    // 2. Collect applicable guidelines in order:
    //    a. Global scope (scopeTargetId = NULL)
    //    b. Category scope (scopeTargetId = project's category ID)
    //    c. Language scope (scopeTargetId = NULL, matches language tag)
    //    d. Project scope (scopeTargetId = projectId)
    // 3. For conflicting rules (same module slug):
    //    - Higher priority value wins
    //    - More specific scope wins (project > language > category > global)
    // 4. Return merged guideline list
}
```

### Conflict Resolution Example

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Module: coding-general (global, priority: 10)                          │
│    Rule: max-line-length = 120                                          │
├─────────────────────────────────────────────────────────────────────────┤
│  Module: coding-ts (language, priority: 20)                             │
│    Rule: max-line-length = 100 (overrides global)                       │
├─────────────────────────────────────────────────────────────────────────┤
│  Module: custom-coding (project, priority: 30)                          │
│    Rule: max-line-length = 80 (overrides language and global)           │
└─────────────────────────────────────────────────────────────────────────┘

Result: max-line-length = 80 (project-level wins)
```

---

## 10.5 API Endpoints

### Preset Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/presets` | List all available presets |
| GET | `/api/presets/:id` | Get preset details |
| POST | `/api/presets` | Create custom preset |
| PUT | `/api/presets/:id` | Update preset |
| DELETE | `/api/presets/:id` | Delete custom preset |
| GET | `/api/presets/:id/preview` | Preview folder structure |

### Guideline Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/guidelines` | List all guidelines |
| GET | `/api/guidelines/:id` | Get guideline details |
| POST | `/api/guidelines` | Create custom guideline |
| PUT | `/api/guidelines/:id` | Update guideline |
| DELETE | `/api/guidelines/:id` | Delete custom guideline |
| GET | `/api/guidelines/resolve/:projectId` | Get resolved guidelines for project |

### Project Creation with Preset

```http
POST /api/projects
Content-Type: application/json

{
  "name": "My New Plugin",
  "slug": "my-new-plugin",
  "description": "A WordPress plugin for...",
  "parentId": null,
  "presetId": "uuid-preset-php-wordpress",
  "additionalGuidelineIds": ["custom-logging"],
  "templateVariables": {
    "projectName": "My New Plugin",
    "projectDescription": "A WordPress plugin for managing custom data.",
    "authorName": "John Doe"
  }
}
```

---

## 10.6 Request/Response Schemas

### List Presets Response

```typescript
interface ListPresetsResponse {
  presets: PresetSummary[];
  totalCount: number;
}

interface PresetSummary {
  id: string;
  slug: string;
  name: string;
  description: string;
  category: string;
  icon: string;
  isBuiltIn: boolean;
  guidelineCount: number;
}
```

### Preset Preview Response

```typescript
interface PresetPreviewResponse {
  preset: PresetSummary;
  folderTree: FolderNode[];
  guidelines: GuidelineSummary[];
  templateVariables: string[]; // Variables used in templates (e.g., "projectName")
}
```

### Resolved Guidelines Response

```typescript
interface ResolvedGuidelinesResponse {
  projectId: string;
  guidelines: ResolvedGuideline[];
  inheritanceChain: InheritanceNode[];
}

interface ResolvedGuideline {
  id: string;
  slug: string;
  name: string;
  sourceScope: GuidelineScope;
  sourceId: string | null;
  priority: number;
  contentMarkdown: string;
}

interface InheritanceNode {
  scope: GuidelineScope;
  targetId: string | null;
  targetName: string;
  guidelineSlugs: string[];
}
```

---

## 10.7 Template Variable Substitution

### Supported Variables

| Variable | Source | Example |
|----------|--------|---------|
| `{{projectName}}` | User input | "My WordPress Plugin" |
| `{{projectSlug}}` | User input | "my-wordpress-plugin" |
| `{{projectDescription}}` | User input | "A plugin for..." |
| `{{authorName}}` | User profile or input | "John Doe" |
| `{{createdDate}}` | System | "2026-01-27" |
| `{{currentYear}}` | System | "2026" |

### Substitution Logic

```go
func ApplyTemplateVariables(content string, vars map[string]string) string {
    result := content
    for key, value := range vars {
        placeholder := "{{" + key + "}}"
        result = strings.ReplaceAll(result, placeholder, value)
    }
    return result
}
```

---

## 10.8 Project Assignment Table

### ProjectGuideline Junction

Tracks which guidelines are assigned to which projects:

```sql
CREATE TABLE ProjectGuideline (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL,
    GuidelineId TEXT NOT NULL,
    AssignedAt TEXT NOT NULL,
    IsExcluded INTEGER DEFAULT 0,  -- 1 = explicitly excluded
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE,
    FOREIGN KEY (GuidelineId) REFERENCES Guideline(Id) ON DELETE CASCADE,
    UNIQUE(ProjectId, GuidelineId)
);
```

This allows:
- Adding extra guidelines beyond preset defaults
- Excluding inherited guidelines for specific projects
- Tracking when guidelines were assigned

---

## 10.9 Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 10001 | ERR_PRESET_NOT_FOUND | Preset ID does not exist |
| 10002 | ERR_PRESET_BUILTIN_READONLY | Cannot modify built-in preset |
| 10003 | ERR_PRESET_SLUG_EXISTS | Preset slug already taken |
| 10004 | ERR_GUIDELINE_NOT_FOUND | Guideline ID does not exist |
| 10005 | ERR_GUIDELINE_BUILTIN_READONLY | Cannot modify built-in guideline |
| 10006 | ERR_GUIDELINE_SLUG_EXISTS | Guideline slug already taken |
| 10007 | ERR_TEMPLATE_VARIABLE_MISSING | Required template variable not provided |
| 10008 | ERR_INVALID_FOLDER_STRUCTURE | Preset folder structure is malformed |
| 10009 | ERR_CIRCULAR_GUIDELINE_EXTENDS | Guideline extends itself (circular) |
| 10010 | ERR_SCOPE_TARGET_NOT_FOUND | Scope target project/category not found |

---

## 10.10 Acceptance Criteria

### Preset Management

- [ ] User can view list of available presets grouped by category
- [ ] User can preview folder structure before creating project
- [ ] User can create project from any enabled preset
- [ ] Template variables are substituted in all generated files
- [ ] User can create custom presets from existing projects
- [ ] Built-in presets cannot be modified or deleted

### Guideline Inheritance

- [ ] Global guidelines apply to all projects
- [ ] Category guidelines apply to projects in that category tree
- [ ] Language guidelines apply to projects with matching language
- [ ] Project guidelines override inherited ones
- [ ] Higher priority guidelines win conflicts
- [ ] User can exclude inherited guidelines per project

### API Behavior

- [ ] GET `/api/presets` returns all enabled presets
- [ ] GET `/api/guidelines/resolve/:projectId` returns merged guidelines
- [ ] POST `/api/projects` with `presetId` creates folder structure
- [ ] Missing template variables return 400 with clear error

---

## 10.11 Implementation Notes

### Built-in Seeding

On first run, seed built-in presets and guidelines from embedded JSON:

```go
//go:embed presets/*.json
var builtInPresets embed.FS

//go:embed guidelines/*.json
var builtInGuidelines embed.FS

func SeedBuiltIns(db *sql.DB) error {
    // 1. Load embedded JSON files
    // 2. Insert with isBuiltIn = true
    // 3. Skip if slug already exists (for upgrades)
}
```

### Upgrade Path

When adding new built-in presets/guidelines in future versions:
1. Check if slug exists
2. If exists and isBuiltIn, update content (user can't modify)
3. If exists and !isBuiltIn, skip (user customized)
4. If not exists, insert

---

## Related Specs

- [Database Schema](../../07-database-design/01-schema.md)
- [AI Integration Overview](./00-overview.md)
- [Dashboard](../11-dashboard/00-overview.md)
