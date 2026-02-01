# Template Manager Component

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-28  

---

## Overview

Spec templates and snippet library for quick insertion of common specification structures, with support for custom user templates.

**Cross-References:**
- [Spec Editor Overview](./00-overview.md)
- [Markdown Editor](./01-markdown-editor.md)
- [Prompt Presets](../06-ai-integration/00-overview.md)

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Built-in Templates | Pre-defined spec templates (feature, component, API) | High |
| Snippet Library | Quick-insert code snippets and sections | High |
| Custom Templates | User-created and saved templates | Medium |
| Template Variables | Placeholder substitution (date, author, title) | Medium |
| Template Categories | Organized by type (spec, component, test, etc.) | Low |
| Import/Export | Share templates between projects | Low |

---

## Component Structure

```
TemplateManager/
├── TemplateManager.tsx       # Main template panel
├── TemplateList.tsx          # Categorized template browser
├── TemplatePreview.tsx       # Preview before insertion
├── TemplateEditor.tsx        # Create/edit custom templates
├── SnippetPalette.tsx        # Quick snippet command palette
├── useTemplates.ts           # Template state management
└── defaultTemplates.ts       # Built-in template definitions
```

---

## Props Interface

```typescript
interface TemplateManagerProps {
  /** Callback when template selected */
  onInsert: (content: string) => void;
  /** Current cursor position for context */
  cursorContext?: 'document' | 'heading' | 'list' | 'code';
  /** Filter by category */
  category?: TemplateCategory;
  /** Show as modal or sidebar panel */
  displayMode?: 'modal' | 'panel' | 'palette';
}

type TemplateCategory = 
  | 'feature-spec'
  | 'component-spec'
  | 'api-spec'
  | 'test-spec'
  | 'snippet'
  | 'custom';
```

---

## Template Schema

```typescript
interface Template {
  id: string;
  name: string;
  description: string;
  category: TemplateCategory;
  content: string;
  variables: TemplateVariable[];
  isBuiltIn: boolean;
  createdAt: Date;
  updatedAt: Date;
}

interface TemplateVariable {
  key: string;           // e.g., "{{TITLE}}"
  label: string;         // e.g., "Feature Title"
  type: 'text' | 'date' | 'select' | 'auto';
  defaultValue?: string;
  options?: string[];    // For select type
}
```

---

## Built-in Templates

### Feature Spec Template

```markdown
# {{TITLE}}

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** {{DATE}}  

---

## Overview

{{DESCRIPTION}}

**Cross-References:**
- [Related Feature](./related.md)

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Feature 1 | Description | High |

---

## User Stories

1. **Story Title** — User action and expected outcome

---

## Technical Requirements

- Requirement 1
- Requirement 2

---

## Related Specs

- [Related Spec](./related.md)
```

### Component Spec Template

```markdown
# {{COMPONENT_NAME}} Component

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** {{DATE}}  

---

## Overview

{{DESCRIPTION}}

---

## Props Interface

\`\`\`typescript
interface {{COMPONENT_NAME}}Props {
  // Props here
}
\`\`\`

---

## Component Structure

\`\`\`
{{COMPONENT_NAME}}/
├── {{COMPONENT_NAME}}.tsx
├── use{{COMPONENT_NAME}}.ts
└── {{COMPONENT_NAME}}.test.tsx
\`\`\`

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Feature 1 | Description | High |

---

## Related Specs

- [Related Component](./related.md)
```

### API Endpoint Template

```markdown
# {{ENDPOINT_NAME}} API

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** {{DATE}}  

---

## Overview

{{DESCRIPTION}}

---

## Endpoint

\`\`\`
{{METHOD}} /api/v1/{{PATH}}
\`\`\`

---

## Request

### Headers

| Header | Required | Description |
|--------|----------|-------------|
| Authorization | Yes | Bearer token |

### Body

\`\`\`json
{
  "field": "value"
}
\`\`\`

---

## Response

### Success (200)

\`\`\`json
{
  "data": {}
}
\`\`\`

### Errors

| Code | Description |
|------|-------------|
| 400 | Bad request |
| 401 | Unauthorized |

---

## Related Specs

- [Spec Editor Overview](./00-overview.md)

---

## Snippets

Quick-insert snippets accessible via command palette (Ctrl+Shift+T):

| Snippet | Trigger | Output |
|---------|---------|--------|
| Table | `/table` | Markdown table template |
| Code Block | `/code` | Fenced code block |
| Link | `/link` | `[text](url)` |
| Cross-ref | `/ref` | `[Title](./path.md)` |
| Mermaid | `/mermaid` | Mermaid diagram block |
| Checkbox | `/check` | `- [ ] Item` |
| Frontmatter | `/front` | YAML frontmatter block |
| Version Badge | `/version` | Version/status header |

---

## Variable Substitution

Auto-replaced variables during insertion:

| Variable | Description | Example |
|----------|-------------|---------|
| `{{DATE}}` | Current date (YYYY-MM-DD) | 2026-01-28 |
| `{{DATETIME}}` | Current datetime (ISO) | 2026-01-28T10:30:00Z |
| `{{AUTHOR}}` | Current user name | John Doe |
| `{{PROJECT}}` | Current project name | Spec Management |
| `{{FILENAME}}` | Current file name | 01-feature.md |
| `{{TITLE}}` | Prompt user for input | (user input) |

---

## Storage

Templates stored in filesystem following prompt preset pattern:

```
Templates/
├── feature-spec/
│   ├── default-feature.md
│   └── detailed-feature.md
├── component-spec/
│   ├── react-component.md
│   └── hook-component.md
├── api-spec/
│   └── rest-endpoint.md
└── custom/
    └── user-template-1.md
```

### Template File Format

```markdown
---
name: Feature Specification
description: Standard feature spec with user stories
category: feature-spec
isDefault: true
variables:
  - key: TITLE
    label: Feature Title
    type: text
  - key: DESCRIPTION
    label: Brief Description
    type: text
---

# {{TITLE}}

**Version:** 1.0.0
...
```

---

## UI Components

### Template Browser (Modal)

```
┌─────────────────────────────────────────┐
│ Insert Template                      ✕  │
├─────────────────────────────────────────┤
│ Categories        │ Templates           │
│ ┌───────────────┐ │ ┌─────────────────┐ │
│ │ ▸ Feature Spec│ │ │ ○ Default       │ │
│ │   Component   │ │ │ ○ Detailed      │ │
│ │   API Spec    │ │ │ ○ Minimal       │ │
│ │   Test Spec   │ │ └─────────────────┘ │
│ │   Snippets    │ │                     │
│ │ ▸ Custom      │ │ Preview:            │
│ └───────────────┘ │ ┌─────────────────┐ │
│                   │ │ # {{TITLE}}     │ │
│ [+ New Template]  │ │ **Version:**... │ │
│                   │ └─────────────────┘ │
├─────────────────────────────────────────┤
│                      [Cancel] [Insert]  │
└─────────────────────────────────────────┘
```

### Snippet Palette (Command Palette)

```
┌─────────────────────────────────────────┐
│ 🔍 Search templates and snippets...     │
├─────────────────────────────────────────┤
│ /table    Insert markdown table         │
│ /code     Insert code block             │
│ /mermaid  Insert mermaid diagram        │
│ /feature  Feature spec template         │
└─────────────────────────────────────────┘
```

---

## Keyboard Shortcuts

| Action | Shortcut |
|--------|----------|
| Open template browser | Ctrl+Shift+T |
| Open snippet palette | Ctrl+/ |
| Insert last used template | Ctrl+Shift+L |

---

## API Integration

```typescript
// Template service endpoints
GET    /api/v1/templates              // List all templates
GET    /api/v1/templates/:id          // Get template by ID
POST   /api/v1/templates              // Create custom template
PUT    /api/v1/templates/:id          // Update template
DELETE /api/v1/templates/:id          // Delete custom template
POST   /api/v1/templates/:id/render   // Render with variables
```

---

## Related Specs

- [Markdown Editor](./01-markdown-editor.md)
- [Preview Renderer](./02-preview-renderer.md)
- [Prompt Presets](../06-ai-integration/00-overview.md)
