# File Operations Specification

> **Version:** 2.0.0  
> **Last Updated:** 2026-01-31  
> **Status:** Complete  

---

## 4.1 Overview

This document specifies the file operations system for the Spec Management Software. It covers CRUD operations for spec files, path validation, content management, and directory operations.

**Key Responsibilities:**
- Create, read, update, delete spec files
- Validate file paths and names
- Manage directory structures
- Handle file content encoding
- Trigger snapshot creation on changes

**Cross-References:**
- [Database Schema](../../07-database-design/01-schema.md) - File table structure
- [API Endpoints](../03-api-design/01-api-endpoints.md) - REST interface
- [History System](../07-history-system/02-history-system.md) - Snapshot triggers
- [Git Integration](../07-history-system/01-git-integration.md) - Commit automation
- [PathManager](./02-path-manager.md) - Path validation and workDirectory management
- [RAG System](../09-knowledge-memory/01-rag-system.md) - Artifact indexing for ideas/instructions

---

## 4.2 File Path Conventions

### 4.2.1 Path Structure

All file paths are **relative to the project's workDirectory** as managed by the [PathManager](./02-path-manager.md).

> **IMPORTANT**: All paths stored in the database MUST be relative paths. The PathManager converts between absolute filesystem paths and relative database paths.

```
{workDirectory}/
└── {ProjectSlug}/
    ├── spec/
    │   ├── 00-overview.md
    │   ├── 01-feature/
    │   │   ├── 01-requirements.md
    │   │   └── 02-implementation.md
    │   └── 99-glossary.md
    ├── ideas/
    │   ├── README.md
    │   ├── 01-idea-feature-concept.md
    │   ├── 02-idea-api-design.md
    │   └── 03-idea-ux-improvements.md
    ├── instructions/
    │   ├── README.md
    │   ├── 01-instruction-feature-spec.md
    │   └── 02-instruction-api-endpoints.md
    └── .history/
        └── snapshots/
```

### 4.2.2 Ideas and Instructions Folders

The `ideas/` and `instructions/` folders are special directories used by the [RAG system](../09-knowledge-memory/01-rag-system.md):

| Folder | Purpose | Naming Pattern | Indexed |
|--------|---------|----------------|---------|
| `ideas/` | Raw ideas from voice/text input | `{nn}-idea-{slug}.md` | Yes |
| `instructions/` | Promoted, refined instructions | `{nn}-instruction-{slug}.md` | Yes |

**Idea Lifecycle:**
1. Voice/text input saved as idea in `ideas/` folder
2. Idea is indexed by RAG system for retrieval
3. When refined, idea is **promoted** to `instructions/` folder
4. Promotion creates new file, updates RAG index, and links via `PromotionEvent`

**File Naming for Ideas/Instructions:**

```go
// IdeaFilenamePattern matches idea files
var IdeaFilenamePattern = regexp.MustCompile(`^(\d{2})-idea-([a-z0-9]+(-[a-z0-9]+)*)\.md$`)

// InstructionFilenamePattern matches instruction files  
var InstructionFilenamePattern = regexp.MustCompile(`^(\d{2})-instruction-([a-z0-9]+(-[a-z0-9]+)*)\.md$`)

// Examples:
// Valid:   01-idea-api-design.md, 02-instruction-user-auth.md
// Invalid: idea-api-design.md, 01-my-idea.md
```

### 4.2.3 PathManager Integration

All file operations MUST use the PathManager for path handling:

```go
// PathManager usage in file operations
func (s *FileService) CreateFile(ctx context.Context, projectId, relativePath, content string) (*File, error) {
    // Validate relative path
    if err := s.pathManager.ValidateRelativePath(relativePath); err != nil {
        return nil, err
    }
    
    // Convert to absolute for filesystem write
    absPath, err := s.pathManager.ToAbsolute(projectId, relativePath)
    if err != nil {
        return nil, err
    }
    
    // Write to filesystem
    if err := os.WriteFile(absPath, []byte(content), 0644); err != nil {
        return nil, NewError(ERR_FILE_WRITE, err.Error())
    }
    
    // Store relative path in database
    file := &File{
        ProjectId: projectId,
        Path:      relativePath,  // Always relative in DB
        Name:      filepath.Base(relativePath),
    }
    
    return file, s.db.Create(file).Error
}
```

### 4.2.4 Path Validation Rules

| Rule ID | Rule | Example Valid | Example Invalid |
|---------|------|---------------|-----------------|
| PATH-01 | Max length 255 characters | `spec/feature/api.md` | `spec/.../very-long-path...` (>255) |
| PATH-02 | No double slashes | `spec/feature/` | `spec//feature/` |
| PATH-03 | No backslashes | `spec/feature/` | `spec\feature\` |
| PATH-04 | No path traversal | `spec/feature/` | `../outside/` |
| PATH-05 | Lowercase with hyphens | `01-feature-name.md` | `01_Feature_Name.md` |
| PATH-06 | Start with number prefix | `01-overview.md` | `overview.md` |
| PATH-07 | End with `.md` extension | `01-overview.md` | `01-overview.txt` |
| PATH-08 | No special characters | `01-api-spec.md` | `01-api@spec!.md` |
| PATH-09 | No spaces | `01-api-spec.md` | `01 api spec.md` |
| PATH-10 | Reserved names forbidden | `spec/overview.md` | `.git/config` |

### 4.2.5 Reserved Path Patterns

The following paths are system-reserved and cannot be modified by users:

```go
var ReservedPaths = []string{
    ".git",
    ".git/*",
    ".history",
    ".history/*",
    "node_modules",
    "node_modules/*",
}
```

### 4.2.6 Path Validation Function

```go
// ValidatePath checks if a file path is valid
// Returns nil if valid, error with code if invalid
func ValidatePath(path string) error {
    if len(path) > 255 {
        return NewError(ERR_PATH_TOO_LONG, "Path exceeds 255 characters")
    }
    
    if strings.Contains(path, "//") {
        return NewError(ERR_INVALID_PATH, "Double slashes not allowed")
    }
    
    if strings.Contains(path, "\\") {
        return NewError(ERR_INVALID_PATH, "Backslashes not allowed")
    }
    
    if strings.Contains(path, "..") {
        return NewError(ERR_PATH_TRAVERSAL, "Path traversal not allowed")
    }
    
    for _, reserved := range ReservedPaths {
        if matchesPattern(path, reserved) {
            return NewError(ERR_RESERVED_PATH, "Reserved path: "+reserved)
        }
    }
    
    if !isValidFilename(filepath.Base(path)) {
        return NewError(ERR_INVALID_FILENAME, "Invalid filename format")
    }
    
    return nil
}
```

---

## 4.3 File Name Conventions

### 4.3.1 Naming Pattern

All spec files follow this naming pattern:

```
{nn}-{topic-name}.md
```

| Component | Description | Example |
|-----------|-------------|---------|
| `{nn}` | Two-digit sequence number | `01`, `02`, `99` |
| `-` | Hyphen separator | `-` |
| `{topic-name}` | Lowercase, hyphen-separated topic | `api-endpoints`, `database-schema` |
| `.md` | Markdown extension | `.md` |

### 4.3.2 Special Files

| File Name | Purpose | Required |
|-----------|---------|----------|
| `00-overview.md` | Section overview | Yes (for directories) |
| `99-glossary.md` | Terminology definitions | Optional |
| `README.md` | Ideas folder readme | Yes (for ideas/) |

### 4.3.3 Filename Validation

```go
var filenameRegex = regexp.MustCompile(`^(\d{2})-([a-z0-9]+(-[a-z0-9]+)*)\.md$`)
var readmeRegex = regexp.MustCompile(`^README\.md$`)

func isValidFilename(filename string) bool {
    if readmeRegex.MatchString(filename) {
        return true
    }
    return filenameRegex.MatchString(filename)
}
```

---

## 4.4 CRUD Operations

### 4.4.1 Create File

**Endpoint:** `POST /api/v1/projects/{projectId}/files`

**Request:**
```json
{
    "path": "spec/01-feature/03-new-spec.md",
    "content": "# New Spec\n\n> **Version:** 1.0.0\n...",
    "createDirectories": true
}
```

**Validation Steps:**
1. Validate path format (PATH-01 to PATH-10)
2. Check file does not already exist
3. Validate content is valid UTF-8
4. Check user has write permission
5. Validate parent directory exists (or createDirectories=true)

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "id": "f1a2b3c4-d5e6-7890-abcd-ef1234567890",
        "projectId": "p1a2b3c4-d5e6-7890-abcd-ef1234567890",
        "path": "spec/01-feature/03-new-spec.md",
        "name": "03-new-spec.md",
        "contentHash": "sha256:abc123...",
        "sizeBytes": 1024,
        "createdAt": "2026-01-27T10:00:00Z",
        "updatedAt": "2026-01-27T10:00:00Z"
    },
    "error": null,
    "meta": {
        "requestId": "req_abc123",
        "timestamp": "2026-01-27T10:00:00Z",
        "version": "1.0.0"
    }
}
```

**Side Effects:**
- Creates file on disk
- Inserts record in `File` table
- Triggers auto-snapshot if enabled
- Schedules git commit if auto-commit enabled

---

### 4.4.2 Read File

**Endpoint:** `GET /api/v1/projects/{projectId}/files/{fileId}`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `includeContent` | boolean | true | Include file content in response |
| `version` | string | latest | Specific snapshot version |

**Response:**
```json
{
    "success": true,
    "data": {
        "id": "f1a2b3c4-d5e6-7890-abcd-ef1234567890",
        "projectId": "p1a2b3c4-d5e6-7890-abcd-ef1234567890",
        "path": "spec/01-feature/03-new-spec.md",
        "name": "03-new-spec.md",
        "content": "# New Spec\n\n> **Version:** 1.0.0\n...",
        "contentHash": "sha256:abc123...",
        "sizeBytes": 1024,
        "mimeType": "text/markdown",
        "createdAt": "2026-01-27T10:00:00Z",
        "updatedAt": "2026-01-27T10:00:00Z"
    },
    "error": null,
    "meta": {
        "requestId": "req_abc123",
        "timestamp": "2026-01-27T10:00:00Z",
        "version": "1.0.0"
    }
}
```

---

### 4.4.3 Update File

**Endpoint:** `PUT /api/v1/projects/{projectId}/files/{fileId}`

**Request:**
```json
{
    "content": "# Updated Spec\n\n> **Version:** 1.1.0\n...",
    "expectedHash": "sha256:abc123..."
}
```

**Optimistic Locking:**
The `expectedHash` field enables optimistic concurrency control:
- If provided and matches current hash: Update proceeds
- If provided and doesn't match: Returns `ERR_CONFLICT` (6003)
- If not provided: Update proceeds (overwrites)

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "id": "f1a2b3c4-d5e6-7890-abcd-ef1234567890",
        "path": "spec/01-feature/03-new-spec.md",
        "contentHash": "sha256:def456...",
        "previousHash": "sha256:abc123...",
        "sizeBytes": 1100,
        "updatedAt": "2026-01-27T10:30:00Z"
    },
    "error": null,
    "meta": {
        "requestId": "req_def456",
        "timestamp": "2026-01-27T10:30:00Z",
        "version": "1.0.0"
    }
}
```

**Side Effects:**
- Updates file on disk
- Updates `File` table record
- Creates snapshot entry in `FileSnapshot` table
- Triggers auto-snapshot if threshold met
- Schedules git commit

---

### 4.4.4 Delete File

**Endpoint:** `DELETE /api/v1/projects/{projectId}/files/{fileId}`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `permanent` | boolean | false | Skip trash, delete permanently |

**Soft Delete Behavior (default):**
1. Move file to `.trash/` directory
2. Set `DeletedAt` timestamp in database
3. File recoverable for 30 days

**Permanent Delete Behavior:**
1. Remove file from disk
2. Remove database record
3. Cascade delete snapshots

**Response:**
```json
{
    "success": true,
    "data": {
        "id": "f1a2b3c4-d5e6-7890-abcd-ef1234567890",
        "deletedAt": "2026-01-27T11:00:00Z",
        "permanent": false,
        "recoveryDeadline": "2026-02-26T11:00:00Z"
    },
    "error": null,
    "meta": {
        "requestId": "req_ghi789",
        "timestamp": "2026-01-27T11:00:00Z",
        "version": "1.0.0"
    }
}
```

---

### 4.4.5 Move/Rename File

**Endpoint:** `PATCH /api/v1/projects/{projectId}/files/{fileId}/move`

**Request:**
```json
{
    "newPath": "spec/02-backend/03-new-spec.md"
}
```

**Validation:**
- New path must be valid (PATH-01 to PATH-10)
- New path must not exist
- User must have write permission on source and destination

**Response:**
```json
{
    "success": true,
    "data": {
        "id": "f1a2b3c4-d5e6-7890-abcd-ef1234567890",
        "previousPath": "spec/01-feature/03-new-spec.md",
        "newPath": "spec/02-backend/03-new-spec.md",
        "movedAt": "2026-01-27T11:30:00Z"
    },
    "error": null,
    "meta": {
        "requestId": "req_jkl012",
        "timestamp": "2026-01-27T11:30:00Z",
        "version": "1.0.0"
    }
}
```

---

## 4.5 Directory Operations

### 4.5.1 List Directory

**Endpoint:** `GET /api/v1/projects/{projectId}/files`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `path` | string | `/` | Directory path to list |
| `recursive` | boolean | false | Include subdirectories |
| `includeDeleted` | boolean | false | Include soft-deleted files |

**Response:**
```json
{
    "success": true,
    "data": {
        "path": "spec/",
        "items": [
            {
                "type": "directory",
                "name": "01-feature",
                "path": "spec/01-feature/",
                "fileCount": 3
            },
            {
                "type": "file",
                "id": "f1a2b3c4...",
                "name": "00-overview.md",
                "path": "spec/00-overview.md",
                "sizeBytes": 2048,
                "updatedAt": "2026-01-27T09:00:00Z"
            }
        ],
        "totalFiles": 15,
        "totalDirectories": 4
    },
    "error": null,
    "meta": {
        "requestId": "req_mno345",
        "timestamp": "2026-01-27T12:00:00Z",
        "version": "1.0.0"
    }
}
```

---

### 4.5.2 Create Directory

**Endpoint:** `POST /api/v1/projects/{projectId}/directories`

**Request:**
```json
{
    "path": "spec/03-new-section/",
    "createOverview": true
}
```

**Behavior:**
- Creates directory on disk
- If `createOverview=true`, creates `00-overview.md` with template
- Does not create database record (directories are implicit)

---

### 4.5.3 Delete Directory

**Endpoint:** `DELETE /api/v1/projects/{projectId}/directories`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `path` | string | required | Directory path |
| `recursive` | boolean | false | Delete non-empty directories |
| `permanent` | boolean | false | Skip trash |

**Validation:**
- If `recursive=false` and directory is not empty: Returns `ERR_DIR_NOT_EMPTY` (6006)

---

## 4.6 Content Management

### 4.6.1 Content Encoding

All file content is stored and transmitted as UTF-8 encoded text.

```go
func ValidateContent(content []byte) error {
    if !utf8.Valid(content) {
        return NewError(ERR_INVALID_ENCODING, "Content must be valid UTF-8")
    }
    
    if len(content) > MaxFileSizeBytes {
        return NewError(ERR_FILE_TOO_LARGE, "File exceeds maximum size")
    }
    
    return nil
}
```

### 4.6.2 Content Size Limits

| Limit | Value | Error Code |
|-------|-------|------------|
| Max file size | 5 MB | ERR_FILE_TOO_LARGE (6004) |
| Max total project size | 500 MB | ERR_PROJECT_QUOTA (6005) |
| Max files per directory | 100 | ERR_DIR_LIMIT (6007) |
| Max directory depth | 10 | ERR_DEPTH_LIMIT (6008) |

### 4.6.3 Content Hashing

All content is hashed using SHA-256 for:
- Change detection
- Optimistic locking
- Deduplication

```go
func HashContent(content []byte) string {
    hash := sha256.Sum256(content)
    return "sha256:" + hex.EncodeToString(hash[:])
}
```

### 4.6.4 Auto-Save Behavior

The frontend implements auto-save with the following rules:

| Trigger | Delay | Action |
|---------|-------|--------|
| Content change | 2 seconds debounce | Save to server |
| Focus lost | Immediate | Save to server |
| Manual save (Ctrl+S) | Immediate | Save to server |

---

## 4.7 Error Codes

File operation errors use the 6xxx range as defined in [Error Management](../general-spec/01-foundation/02-error-management-foundation.md).

| Code | Constant | Description | HTTP Status |
|------|----------|-------------|-------------|
| 6001 | ERR_FILE_NOT_FOUND | File does not exist | 404 |
| 6002 | ERR_FILE_EXISTS | File already exists at path | 409 |
| 6003 | ERR_CONFLICT | Content hash mismatch (optimistic lock) | 409 |
| 6004 | ERR_FILE_TOO_LARGE | File exceeds size limit | 413 |
| 6005 | ERR_PROJECT_QUOTA | Project storage quota exceeded | 507 |
| 6006 | ERR_DIR_NOT_EMPTY | Cannot delete non-empty directory | 400 |
| 6007 | ERR_DIR_LIMIT | Directory file count exceeded | 400 |
| 6008 | ERR_DEPTH_LIMIT | Directory nesting too deep | 400 |
| 6009 | ERR_INVALID_PATH | Path format invalid | 400 |
| 6010 | ERR_PATH_TRAVERSAL | Path traversal attempt | 403 |
| 6011 | ERR_RESERVED_PATH | Attempting to modify reserved path | 403 |
| 6012 | ERR_PATH_TOO_LONG | Path exceeds 255 characters | 400 |
| 6013 | ERR_INVALID_FILENAME | Filename doesn't match pattern | 400 |
| 6014 | ERR_INVALID_ENCODING | Content is not valid UTF-8 | 400 |
| 6015 | ERR_READ_FAILED | Failed to read file from disk | 500 |
| 6016 | ERR_WRITE_FAILED | Failed to write file to disk | 500 |

---

## 4.8 Service Layer

### 4.8.1 FileService Interface

```go
type FileService interface {
    // CRUD operations
    Create(ctx context.Context, req CreateFileRequest) (*File, error)
    Read(ctx context.Context, projectId, fileId string) (*File, error)
    Update(ctx context.Context, req UpdateFileRequest) (*File, error)
    Delete(ctx context.Context, projectId, fileId string, permanent bool) error
    Move(ctx context.Context, req MoveFileRequest) (*File, error)
    
    // Directory operations
    ListDirectory(ctx context.Context, req ListDirRequest) (*DirectoryListing, error)
    CreateDirectory(ctx context.Context, req CreateDirRequest) error
    DeleteDirectory(ctx context.Context, req DeleteDirRequest) error
    
    // Content operations
    GetContent(ctx context.Context, projectId, fileId string) ([]byte, error)
    SetContent(ctx context.Context, projectId, fileId string, content []byte) error
    
    // Validation
    ValidatePath(path string) error
    ValidateContent(content []byte) error
}
```

### 4.8.2 Implementation Notes

```go
type fileService struct {
    db          *sql.DB
    projectRoot string
    snapshots   SnapshotService
    git         GitService
    config      ConfigService
}

func (s *fileService) Create(ctx context.Context, req CreateFileRequest) (*File, error) {
    // 1. Validate path
    if err := s.ValidatePath(req.Path); isNotEmpty(err) {
        return nil, err
    }
    
    // 2. Check file doesn't exist
    if s.fileExists(req.ProjectId, req.Path) {
        return nil, NewError(ERR_FILE_EXISTS, "File already exists")
    }
    
    // 3. Validate content
    if err := s.ValidateContent([]byte(req.Content)); isNotEmpty(err) {
        return nil, err
    }
    
    // 4. Create parent directories if needed
    if req.CreateDirectories {
        if err := s.ensureDirectories(req.ProjectId, req.Path); isNotEmpty(err) {
            return nil, err
        }
    }
    
    // 5. Write to disk
    fullPath := s.getFullPath(req.ProjectId, req.Path)
    if err := os.WriteFile(fullPath, []byte(req.Content), 0644); isNotEmpty(err) {
        return nil, NewError(ERR_WRITE_FAILED, err.Error())
    }
    
    // 6. Create database record
    file := &File{
        Id:          uuid.New().String(),
        ProjectId:   req.ProjectId,
        Path:        req.Path,
        Name:        filepath.Base(req.Path),
        ContentHash: HashContent([]byte(req.Content)),
        SizeBytes:   len(req.Content),
        CreatedAt:   time.Now(),
        UpdatedAt:   time.Now(),
    }
    
    if err := s.insertFile(ctx, file); isNotEmpty(err) {
        // Rollback: delete file from disk
        os.Remove(fullPath)
        return nil, err
    }
    
    // 7. Trigger snapshot if configured
    s.snapshots.TriggerIfNeeded(ctx, req.ProjectId)
    
    // 8. Schedule git commit if configured
    s.git.ScheduleCommit(ctx, req.ProjectId, "Created "+req.Path)
    
    return file, nil
}
```

---

## 4.9 Bulk Operations

### 4.9.1 Bulk Create

**Endpoint:** `POST /api/v1/projects/{projectId}/files/bulk`

**Request:**
```json
{
    "files": [
        {
            "path": "spec/01-feature/01-overview.md",
            "content": "# Overview\n..."
        },
        {
            "path": "spec/01-feature/02-requirements.md",
            "content": "# Requirements\n..."
        }
    ],
    "createDirectories": true,
    "atomicOperation": true
}
```

**Behavior:**
- If `atomicOperation=true`: All files created or none (transaction)
- If `atomicOperation=false`: Best-effort, returns partial results

---

### 4.9.2 Bulk Delete

**Endpoint:** `DELETE /api/v1/projects/{projectId}/files/bulk`

**Request:**
```json
{
    "fileIds": [
        "f1a2b3c4-d5e6-7890-abcd-ef1234567890",
        "f2b3c4d5-e6f7-8901-bcde-f23456789012"
    ],
    "permanent": false
}
```

---

## 4.10 File Templates

### 4.10.1 Overview Template

When creating a new directory with `createOverview=true`:

```markdown
# {Section Name}

> **Version:** 1.0.0  
> **Last Updated:** {Date}  
> **Status:** Draft  

---

## Overview

{Description}

---

## Document Index

- [01-first-topic.md](./01-first-topic.md) - {Description}

---

## Cross-References

- [Parent Overview](../00-overview.md)
```

### 4.10.2 Spec File Template

Default template for new spec files:

```markdown
# {Topic Name}

> **Version:** 1.0.0  
> **Last Updated:** {Date}  
> **Status:** Draft  

---

## Overview

{Description}

---

## Details

{Content}

---

## Acceptance Criteria

- [ ] Criterion 1
- [ ] Criterion 2

---

## Cross-References

- [Section Overview](./00-overview.md)
```

---

## 4.11 Project Metadata File (spec.project.json)

### 4.11.1 Overview

Each project has a `spec.project.json` file at its root that stores extended metadata. This file enables bidirectional synchronization between the filesystem and database.

```
{ProjectId}/
├── spec.project.json    ← Project metadata file
├── spec/
│   └── ...
├── ideas/
│   └── ...
└── .history/
```

### 4.11.2 Schema Definition

```typescript
interface ProjectMetadataJson {
  // Core identifiers
  projectName: string;        // Display name
  projectSlug: string;        // URL-safe identifier
  version: string;            // Semantic version (e.g., "1.0.0")
  
  // Descriptive fields
  summary: string;            // Brief one-line description
  description: string;        // Extended description (optional)
  
  // Ownership and contacts
  authorName: string;         // Primary author
  authorEmail?: string;       // Author email (optional)
  designerName?: string;      // UI/UX designer (optional)
  responsiblePerson: {        // Project owner/manager
    name: string;
    email: string;
  };
  
  // Classification
  language?: string;          // Primary language (e.g., "typescript", "php")
  framework?: string;         // Framework (e.g., "react", "wordpress")
  tags?: string[];            // Custom tags for organization
  
  // Timestamps
  createdAt: string;          // ISO8601 timestamp
  updatedAt: string;          // ISO8601 timestamp
  
  // Guidelines override
  guidelineOverrides?: {      // Per-project guideline customization
    excluded: string[];       // Guideline slugs to exclude
    added: string[];          // Additional guideline slugs
  };
  
  // AI settings
  aiSettings?: {
    defaultReasoningModelId?: string;  // Override system default
    defaultVoiceModelId?: string;      // Override system default
    instructionMode?: 'automatic' | 'approval';
  };
  
  // Custom metadata
  custom?: Record<string, string | number | boolean>;
}
```

### 4.11.3 Example File

```json
{
  "projectName": "Exam Manager Plugin",
  "projectSlug": "exam-manager",
  "version": "2.1.0",
  "summary": "WordPress plugin for managing examinations and participant progress",
  "description": "A comprehensive exam management system with support for deadlines, extensions, secret keys, and progress tracking.",
  "authorName": "John Doe",
  "authorEmail": "john@example.com",
  "designerName": "Jane Smith",
  "responsiblePerson": {
    "name": "Project Manager",
    "email": "pm@example.com"
  },
  "language": "php",
  "framework": "wordpress",
  "tags": ["education", "exam", "plugin"],
  "createdAt": "2025-06-15T10:00:00Z",
  "updatedAt": "2026-01-27T14:30:00Z",
  "guidelineOverrides": {
    "excluded": [],
    "added": ["custom-wp-hooks"]
  },
  "aiSettings": {
    "instructionMode": "approval"
  },
  "custom": {
    "clientName": "University of Example",
    "contractId": "EDU-2025-001"
  }
}
```

### 4.11.4 Bidirectional Sync Logic

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    BIDIRECTIONAL SYNC FLOW                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────┐                          ┌──────────────┐            │
│  │   Database   │  ←── Sync Direction ──→  │  JSON File   │            │
│  │  (Project +  │                          │ spec.project │            │
│  │   Metadata)  │                          │    .json     │            │
│  └──────────────┘                          └──────────────┘            │
│         │                                         │                    │
│         ▼                                         ▼                    │
│  ┌──────────────┐                          ┌──────────────┐            │
│  │ Last Updated │                          │ File ModTime │            │
│  │  Timestamp   │                          │  + Hash      │            │
│  └──────────────┘                          └──────────────┘            │
│                                                                         │
│  CONFLICT RESOLUTION:                                                   │
│  • If DB newer → Write JSON                                            │
│  • If JSON newer → Update DB                                           │
│  • If same time → DB wins (source of truth)                            │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 4.11.5 Sync Service Interface

```go
type ProjectMetadataService interface {
    // Read metadata from JSON file
    ReadFromFile(projectId string) (*ProjectMetadata, error)
    
    // Write metadata to JSON file
    WriteToFile(projectId string, metadata *ProjectMetadata) error
    
    // Sync database ↔ filesystem
    SyncToFile(projectId string) error   // DB → JSON
    SyncFromFile(projectId string) error // JSON → DB
    
    // Full bidirectional sync
    Sync(projectId string) (*SyncResult, error)
    
    // Watch for external changes
    WatchFile(projectId string, onChange func()) error
    StopWatching(projectId string) error
}
```

### 4.11.6 Sync Implementation

```go
type SyncResult struct {
    Direction    SyncDirection // "db_to_file", "file_to_db", "none", "conflict"
    FieldsUpdated []string
    Timestamp    time.Time
    Error        error
}

type SyncDirection string

const (
    SyncNone       SyncDirection = "none"
    SyncDbToFile   SyncDirection = "db_to_file"
    SyncFileToDb   SyncDirection = "file_to_db"
    SyncConflict   SyncDirection = "conflict"
)

func (s *metadataService) Sync(projectId string) (*SyncResult, error) {
    // 1. Get DB record with timestamp
    dbRecord, err := s.getFromDb(projectId)
    if isNotEmpty(err) {
        return nil, err
    }
    
    // 2. Get file info with modification time
    filePath := s.getMetadataFilePath(projectId)
    fileInfo, err := os.Stat(filePath)
    
    // 3. Handle file not existing
    if os.IsNotExist(err) {
        // File doesn't exist: create from DB
        return s.syncDbToFile(projectId, dbRecord)
    }
    
    // 4. Read file content and parse
    fileContent, err := os.ReadFile(filePath)
    if isNotEmpty(err) {
        return nil, NewError(ERR_READ_FAILED, "Failed to read metadata file")
    }
    
    var fileRecord ProjectMetadata
    if err := json.Unmarshal(fileContent, &fileRecord); isNotEmpty(err) {
        return nil, NewError(ERR_INVALID_JSON, "Invalid JSON in metadata file")
    }
    
    // 5. Compare timestamps
    dbUpdated := dbRecord.UpdatedAt
    fileUpdated := fileRecord.UpdatedAt
    fileModTime := fileInfo.ModTime()
    
    // 6. Determine sync direction
    if dbUpdated.After(fileUpdated) {
        // DB is newer: write to file
        return s.syncDbToFile(projectId, dbRecord)
    } else if fileUpdated.After(dbUpdated) || fileModTime.After(dbUpdated) {
        // File is newer: update DB
        return s.syncFileToDb(projectId, &fileRecord)
    }
    
    // 7. No sync needed
    return &SyncResult{
        Direction: SyncNone,
        Timestamp: time.Now(),
    }, nil
}

func (s *metadataService) syncDbToFile(projectId string, record *ProjectMetadata) (*SyncResult, error) {
    filePath := s.getMetadataFilePath(projectId)
    
    content, err := json.MarshalIndent(record, "", "  ")
    if isNotEmpty(err) {
        return nil, err
    }
    
    if err := os.WriteFile(filePath, content, 0644); isNotEmpty(err) {
        return nil, NewError(ERR_WRITE_FAILED, "Failed to write metadata file")
    }
    
    return &SyncResult{
        Direction:     SyncDbToFile,
        FieldsUpdated: []string{"all"},
        Timestamp:     time.Now(),
    }, nil
}

func (s *metadataService) syncFileToDb(projectId string, record *ProjectMetadata) (*SyncResult, error) {
    // Validate required fields
    if record.ProjectName == "" || record.ProjectSlug == "" {
        return nil, NewError(ERR_VALIDATION_FAILED, "Missing required fields in metadata")
    }
    
    // Update database
    err := s.updateProjectMetadata(projectId, record)
    if isNotEmpty(err) {
        return nil, err
    }
    
    return &SyncResult{
        Direction:     SyncFileToDb,
        FieldsUpdated: s.getChangedFields(projectId, record),
        Timestamp:     time.Now(),
    }, nil
}
```

### 4.11.7 File Watcher Integration

```go
func (s *metadataService) WatchFile(projectId string) error {
    filePath := s.getMetadataFilePath(projectId)
    
    watcher, err := fsnotify.NewWatcher()
    if isNotEmpty(err) {
        return err
    }
    
    go func() {
        for {
            select {
            case event, ok := <-watcher.Events:
                if !ok {
                    return
                }
                if event.Op&fsnotify.Write == fsnotify.Write {
                    // File was modified externally
                    s.handleExternalChange(projectId)
                }
            case err, ok := <-watcher.Errors:
                if !ok {
                    return
                }
                s.logger.Error("Watch error", "projectId", projectId, "error", err)
            }
        }
    }()
    
    return watcher.Add(filePath)
}

func (s *metadataService) handleExternalChange(projectId string) {
    // Debounce to avoid rapid-fire updates
    s.debouncer.Do(projectId, 500*time.Millisecond, func() {
        result, err := s.Sync(projectId)
        if isNotEmpty(err) {
            s.logger.Error("Sync failed after external change", 
                "projectId", projectId, "error", err)
            return
        }
        
        if result.Direction == SyncFileToDb {
            s.eventBus.Publish("project.metadata.updated", projectId)
        }
    })
}
```

### 4.11.8 API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/projects/{projectId}/metadata` | Get project metadata |
| PUT | `/api/v1/projects/{projectId}/metadata` | Update project metadata |
| POST | `/api/v1/projects/{projectId}/metadata/sync` | Force bidirectional sync |
| GET | `/api/v1/projects/{projectId}/metadata/diff` | Compare DB vs file |

### 4.11.9 Sync Trigger Points

| Trigger | Action |
|---------|--------|
| Project opened | Sync on load |
| File watcher event | Sync from file |
| Metadata edited in UI | Sync to file |
| Git pull detected | Sync from file |
| Export project | Sync to file first |

### 4.11.10 Error Codes (Project Metadata)

| Code | Constant | Description |
|------|----------|-------------|
| 6020 | ERR_METADATA_NOT_FOUND | spec.project.json does not exist |
| 6021 | ERR_METADATA_INVALID_JSON | JSON parsing failed |
| 6022 | ERR_METADATA_VALIDATION | Required field missing or invalid |
| 6023 | ERR_METADATA_SYNC_FAILED | Bidirectional sync failed |
| 6024 | ERR_METADATA_CONFLICT | Unresolvable conflict detected |

---

## 4.12 Acceptance Criteria

### Path Validation (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PV-001 | Path length ≤255 characters enforced | Critical | Length test |
| PV-002 | Double slashes (//) rejected | Critical | Regex test |
| PV-003 | Backslashes (\) rejected | Critical | Regex test |
| PV-004 | Path traversal (..) rejected | Critical | Security test |
| PV-005 | Lowercase with hyphens enforced | High | Format test |
| PV-006 | Two-digit prefix required (01-, 02-, etc.) | High | Prefix test |
| PV-007 | .md extension required | High | Extension test |
| PV-008 | Special characters rejected | High | Char test |
| PV-009 | Spaces rejected | High | Space test |
| PV-010 | Reserved paths (.git, .history, node_modules) blocked | Critical | Reserved test |

### CRUD Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CR-001 | POST /files creates file on disk and in database | Critical | Create test |
| CR-002 | GET /files/{id} returns file content and metadata | Critical | Read test |
| CR-003 | PUT /files/{id} updates file with optimistic locking | Critical | Update test |
| CR-004 | DELETE /files/{id} soft-deletes to .trash/ | Critical | Delete test |
| CR-005 | DELETE with permanent=true removes permanently | Critical | Permanent delete test |
| CR-006 | PATCH /files/{id}/move renames/moves file | Critical | Move test |
| CR-007 | expectedHash mismatch returns ERR_CONFLICT (6003) | Critical | Conflict test |
| CR-008 | File content validated as UTF-8 | High | Encoding test |
| CR-009 | File size ≤10MB enforced | High | Size limit test |
| CR-010 | createDirectories=true creates parent folders | Medium | Auto-create test |

### Directory Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| DO-001 | GET /files?path=spec/ lists directory contents | Critical | List test |
| DO-002 | recursive=true includes subdirectories | High | Recursive test |
| DO-003 | POST /directories creates folder with 00-overview.md | Critical | Create dir test |
| DO-004 | DELETE directory works for empty directories | High | Delete empty test |
| DO-005 | DELETE with force=true removes non-empty directories | High | Force delete test |
| DO-006 | Directory paths end with / | Medium | Trailing slash test |

### Ideas & Instructions Folders (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| II-001 | ideas/ folder accepts `{nn}-idea-{slug}.md` files | Critical | Idea naming test |
| II-002 | instructions/ folder accepts `{nn}-instruction-{slug}.md` files | Critical | Instruction naming test |
| II-003 | README.md allowed in ideas/ and instructions/ | High | Readme test |
| II-004 | RAG indexing triggered on file changes in these folders | High | Index trigger test |
| II-005 | Next sequence number derived from MAX(existing) + 1 | Medium | Sequence test |

### Snapshots & History (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SH-001 | File update creates snapshot entry | Critical | Snapshot create test |
| SH-002 | Auto-save triggers snapshot at threshold | High | Auto-save test |
| SH-003 | Git commit scheduled on file changes | High | Git schedule test |
| SH-004 | version query param retrieves historical content | Medium | Version retrieval test |

### Metadata Sync (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MS-001 | spec.project.json created for new projects | Critical | Auto-create test |
| MS-002 | DB → file sync on metadata update | Critical | DB-to-file test |
| MS-003 | File → DB sync on external file change | Critical | File-to-DB test |
| MS-004 | File watcher detects spec.project.json changes | High | Watcher test |
| MS-005 | Conflict resolution: DB wins on tie | High | Conflict test |
| MS-006 | Invalid JSON returns ERR_METADATA_INVALID_JSON (6021) | High | Validation test |

### Bulk Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| BO-001 | POST /files/bulk creates multiple files atomically | Critical | Bulk create test |
| BO-002 | DELETE /files/bulk deletes multiple files atomically | Critical | Bulk delete test |
| BO-003 | Partial failure rolls back entire transaction | High | Rollback test |
| BO-004 | Bulk operations limited to 100 files per request | Medium | Limit test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | ERR_FILE_NOT_FOUND (6001) for missing files | Critical | Error code test |
| EH-002 | ERR_FILE_EXISTS (6002) for duplicate creation | Critical | Error code test |
| EH-003 | ERR_CONFLICT (6003) for hash mismatch | Critical | Error code test |
| EH-004 | ERR_PATH_TOO_LONG (6004) for >255 char paths | Critical | Error code test |
| EH-005 | ERR_PATH_TRAVERSAL (6005) for .. patterns | Critical | Error code test |
| EH-006 | ERR_RESERVED_PATH (6006) for .git, etc. | Critical | Error code test |
| EH-007 | All errors include filePath for debugging | High | Error context test |

---

## Cross-References

- [Database Schema: File Table](../../07-database-design/01-schema.md#file-table)
- [Database Schema: Project Metadata](../../07-database-design/01-schema.md#projectmetadata)
- [Database Schema: PromotionEvent](../../07-database-design/01-schema.md#promotionevent)
- [API Endpoints: File Routes](../03-api-design/01-api-endpoints.md#file-endpoints)
- [History System: Snapshot Triggers](../07-history-system/02-history-system.md)
- [Git Integration: Auto-Commit](../07-history-system/01-git-integration.md)
- [PathManager: Path Validation](./02-path-manager.md)
- [RAG System: Artifact Indexing](../09-knowledge-memory/01-rag-system.md)
- [General Spec: Error Management](../../general-spec/01-foundation/02-error-management-foundation.md)
