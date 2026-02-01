# Component: Prompt Import System

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Planned  
**Phase:** 1 - Foundation  

---

## Summary

System for importing prompt templates from ZIP files containing Markdown files into the PromptTemplate table. Supports folder structures, metadata extraction, and batch operations.

---

## User Stories

- As a user, I want to import a ZIP of prompt files into my project
- As a user, I want folder structure preserved during import
- As a user, I want metadata automatically extracted from frontmatter
- As a user, I want to browse and search my imported prompts
- As a user, I want to update existing prompts by re-importing

---

## Import Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Upload    │────▶│   Extract   │────▶│   Parse     │────▶│   Store     │
│   ZIP File  │     │   Archive   │     │   Files     │     │   Database  │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
                           │                   │                   │
                           ▼                   ▼                   ▼
                    ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
                    │  Validate   │     │  Extract    │     │  Index for  │
                    │  Structure  │     │  Frontmatter│     │  Search     │
                    └─────────────┘     └─────────────┘     └─────────────┘
```

---

## Supported File Types

| Extension | Description | Processing |
|-----------|-------------|------------|
| `.md` | Markdown prompt | Parse frontmatter + content |
| `.txt` | Plain text prompt | Direct content, no metadata |
| `.prompt` | Custom prompt format | Parse as Markdown |
| `.json` | Structured prompt | Parse as JSON config |

---

## ZIP Structure Requirements

### Valid Structure

```
prompts.zip
├── html-generation/
│   ├── generate-page.md
│   ├── generate-component.md
│   └── templates/
│       ├── landing-page.md
│       └── dashboard.md
├── code-review/
│   ├── review-typescript.md
│   └── review-golang.md
└── content-writing/
    ├── blog-post.md
    └── documentation.md
```

### Constraints

- Max ZIP size: 50 MB
- Max files per ZIP: 500
- Max file size: 1 MB per file
- Max folder depth: 5 levels
- Encoding: UTF-8 required

---

## Frontmatter Schema

Prompts can include YAML frontmatter for metadata:

```markdown
---
name: Generate HTML Page
category: html-generation
version: 1.0.0
author: team@example.com
tags:
  - html
  - frontend
  - generation
model: llama-3
temperature: 0.7
description: Generates a complete HTML page from a specification
variables:
  - name: spec
    type: STRING
    required: true
  - name: style
    type: STRING
    default: modern
---

# HTML Page Generator

You are an expert HTML developer. Generate a complete, valid HTML page based on the following specification:

## Specification
{{spec}}

## Style Guidelines
Apply a {{style}} design approach with:
- Clean, semantic HTML5
- Accessible markup (ARIA labels)
- Mobile-first responsive design

## Output
Return only the HTML code, no explanations.
```

---

## Metadata Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | No | Display name (defaults to filename) |
| category | string | No | Classification category |
| version | string | No | Semantic version |
| author | string | No | Creator identifier |
| tags | string[] | No | Searchable tags |
| model | string | No | Preferred AI model |
| temperature | number | No | AI temperature setting |
| description | string | No | Brief description |
| variables | object[] | No | Expected input variables |

---

## API Endpoints

### POST /api/prompts/import

Import prompts from ZIP file.

**Request:**
```typescript
interface ImportRequest {
  file: File;                    // ZIP file
  projectId: string;             // Target project
  conflictResolution: ConflictResolution;
  basePath?: string;             // Prefix for folder paths
}

enum ConflictResolution {
  SKIP = 'SKIP',                 // Keep existing
  OVERWRITE = 'OVERWRITE',       // Replace existing
  RENAME = 'RENAME',             // Add suffix to new
}
```

**Response:**
```typescript
interface ImportResponse {
  success: boolean;
  imported: number;              // New prompts created
  updated: number;               // Existing prompts updated
  skipped: number;               // Skipped due to conflicts
  errors: ImportError[];
  prompts: PromptSummary[];      // Imported prompt details
}

interface ImportError {
  filePath: string;
  error: string;
  line?: number;
}

interface PromptSummary {
  id: string;
  folderPath: string;
  fileName: string;
  name: string;
  category?: string;
}
```

---

### GET /api/prompts

List prompts with filtering.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| projectId | string | Required: Project filter |
| folderPath | string | Filter by folder |
| category | string | Filter by category |
| tags | string | Comma-separated tags |
| search | string | Full-text search |
| page | number | Pagination (default: 1) |
| limit | number | Items per page (default: 50) |

**Response:**
```typescript
interface PromptListResponse {
  prompts: PromptTemplate[];
  total: number;
  page: number;
  totalPages: number;
}
```

---

### GET /api/prompts/:id

Get single prompt with full content.

---

### PUT /api/prompts/:id

Update prompt content or metadata.

**Request:**
```typescript
interface UpdatePromptRequest {
  content?: string;
  metadata?: Record<string, unknown>;
  folderPath?: string;
  fileName?: string;
}
```

---

### DELETE /api/prompts/:id

Delete a prompt template.

---

### POST /api/prompts/export

Export prompts to ZIP file.

**Request:**
```typescript
interface ExportRequest {
  projectId: string;
  promptIds?: string[];          // Specific prompts, or all if empty
  includeFrontmatter: boolean;   // Include metadata as YAML
}
```

---

## UI Components

### Import Dialog

```typescript
interface ImportDialogProps {
  projectId: string;
  onImportComplete: (result: ImportResponse) => void;
  onCancel: () => void;
}
```

**Features:**
- Drag-and-drop ZIP upload
- Conflict resolution selector
- Progress indicator
- Import summary with errors
- Preview of files to import

---

### Prompt Library Browser

```typescript
interface PromptLibraryProps {
  projectId: string;
  onSelectPrompt: (prompt: PromptTemplate) => void;
  selectedPromptId?: string;
}
```

**Features:**
- Folder tree navigation
- Search and filter
- Grid/list view toggle
- Prompt preview panel
- Tag filtering
- Category grouping

---

### Prompt Editor

```typescript
interface PromptEditorProps {
  promptId: string;
  onSave: (prompt: PromptTemplate) => void;
  onDelete: () => void;
}
```

**Features:**
- Monaco editor for content
- Frontmatter form editor
- Variable highlighting
- Preview with sample data
- Version history

---

## Processing Pipeline

### Step 1: ZIP Extraction

```typescript
interface ExtractedFile {
  path: string;                  // Relative path in ZIP
  content: Buffer;
  size: number;
}

async function extractZip(file: File): Promise<ExtractedFile[]> {
  // 1. Validate ZIP signature
  // 2. Check total size < 50MB
  // 3. Extract files, skip directories
  // 4. Validate file count < 500
  // 5. Return file list
}
```

### Step 2: File Parsing

```typescript
interface ParsedPrompt {
  folderPath: string;
  fileName: string;
  content: string;               // Content after frontmatter
  metadata: PromptMetadata;
  rawContent: string;            // Original full content
}

function parsePromptFile(file: ExtractedFile): ParsedPrompt {
  // 1. Decode UTF-8
  // 2. Extract YAML frontmatter (between ---)
  // 3. Parse frontmatter as YAML
  // 4. Validate metadata schema
  // 5. Return parsed structure
}
```

### Step 3: Database Storage

```typescript
async function storePrompts(
  projectId: string,
  prompts: ParsedPrompt[],
  conflictResolution: ConflictResolution
): Promise<ImportResponse> {
  // 1. Begin transaction
  // 2. For each prompt:
  //    a. Check for existing by projectId + folderPath + fileName
  //    b. Apply conflict resolution
  //    c. Insert or update
  // 3. Commit transaction
  // 4. Return summary
}
```

---

## Validation Rules

### File Validation

| Rule | Check | Error Message |
|------|-------|---------------|
| Size | file.size ≤ 1MB | File exceeds 1MB limit |
| Encoding | Valid UTF-8 | Invalid character encoding |
| Extension | Allowed list | Unsupported file type |
| Content | Non-empty | File is empty |

### Frontmatter Validation

| Rule | Check | Error Message |
|------|-------|---------------|
| YAML | Valid YAML syntax | Invalid YAML frontmatter |
| Version | Semver format | Invalid version format |
| Temperature | 0.0 - 2.0 | Temperature out of range |
| Variables | Valid schema | Invalid variable definition |

### Path Validation

| Rule | Check | Error Message |
|------|-------|---------------|
| Depth | ≤ 5 levels | Folder depth exceeds limit |
| Characters | Alphanumeric, -, _ | Invalid path characters |
| Length | ≤ 255 chars | Path too long |

---

## Variable Extraction

The system auto-detects variables in prompt content:

```typescript
const VARIABLE_PATTERNS = [
  /\{\{(\w+(?:\.\w+)*)\}\}/g,     // {{variable}} or {{obj.prop}}
  /\$\{(\w+)\}/g,                  // ${variable}
  /<(\w+)>/g,                      // <variable> (template style)
];

interface ExtractedVariable {
  name: string;
  pattern: string;
  occurrences: number;
  positions: number[];
}

function extractVariables(content: string): ExtractedVariable[] {
  // 1. Apply all patterns
  // 2. Deduplicate by name
  // 3. Count occurrences
  // 4. Return sorted by first occurrence
}
```

---

## Search Indexing

Prompts are indexed for full-text search:

```sql
-- Virtual table for FTS5
CREATE VIRTUAL TABLE IF NOT EXISTS PromptSearch USING fts5(
    Id,
    Name,
    Content,
    Tags,
    Category,
    FolderPath,
    content=PromptTemplate,
    content_rowid=rowid
);

-- Triggers to keep index updated
CREATE TRIGGER prompt_ai AFTER INSERT ON PromptTemplate BEGIN
    INSERT INTO PromptSearch(rowid, Id, Name, Content, Tags, Category, FolderPath)
    VALUES (new.rowid, new.Id, json_extract(new.Metadata, '$.name'), 
            new.Content, json_extract(new.Metadata, '$.tags'),
            json_extract(new.Metadata, '$.category'), new.FolderPath);
END;
```

---

## Error Handling

### Import Errors

```typescript
enum ImportErrorCode {
  INVALID_ZIP = 'INVALID_ZIP',
  FILE_TOO_LARGE = 'FILE_TOO_LARGE',
  TOO_MANY_FILES = 'TOO_MANY_FILES',
  INVALID_ENCODING = 'INVALID_ENCODING',
  INVALID_FRONTMATTER = 'INVALID_FRONTMATTER',
  DUPLICATE_PATH = 'DUPLICATE_PATH',
  PATH_TOO_DEEP = 'PATH_TOO_DEEP',
  DATABASE_ERROR = 'DATABASE_ERROR',
}

interface ImportError {
  code: ImportErrorCode;
  filePath: string;
  message: string;
  details?: Record<string, unknown>;
}
```

### Recovery

- Partial imports: Successfully imported files are kept
- Transaction rollback: On critical errors, all changes reverted
- Error log: All errors recorded for user review

---

## Performance Considerations

| Metric | Target |
|--------|--------|
| ZIP extraction | < 2s for 50MB |
| File parsing | < 10ms per file |
| Database insert | < 500ms for 500 files |
| Search query | < 50ms |

### Optimizations

- Stream ZIP extraction (no full memory load)
- Batch database inserts (50 files per transaction)
- Lazy content loading in UI
- Indexed search with FTS5

---

## Security

- **Zip Slip Prevention:** Validate all paths are within extraction directory
- **Content Sanitization:** Strip potential XSS from content preview
- **Size Limits:** Enforce limits to prevent DoS
- **Path Traversal:** Reject paths with `..` or absolute paths

---

## Related Specs

- [Database Schema](./01-database-schema.md)
- [Variable Registry](./03-variable-registry.md)
