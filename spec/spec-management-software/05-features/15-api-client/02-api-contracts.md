# API Contracts Specification

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-28

---

## Overview

This document defines all REST API endpoints with OpenAPI-style request/response schemas for the Spec Management Software. All endpoints follow RESTful conventions and use JSON for request/response bodies.

**Cross-References:**
- [HTTP Client](./01-http-client.md) - Client configuration
- [Authentication](../01-authentication/01-authentication.md) - Auth flow
- [Error Management](../../06-error-management/00-overview.md) - Error codes

---

## 1. Common Structures

### 1.1 Response Envelope

All API responses use this standard envelope:

```typescript
interface ApiResponse<T> {
  success: boolean;
  data: T | null;
  error: ApiError | null;
  meta: ResponseMeta;
}

interface ApiError {
  code: string;          // e.g., "ERR_VALIDATION"
  message: string;       // Human-readable message
  details?: object;      // Additional context
}

interface ResponseMeta {
  requestId: string;     // Unique request identifier
  timestamp: string;     // ISO8601
  version: string;       // API version
}
```

### 1.2 Pagination

```typescript
interface PaginatedResponse<T> {
  items: T[];
  pagination: {
    page: number;
    pageSize: number;
    totalItems: number;
    totalPages: number;
    hasNext: boolean;
    hasPrev: boolean;
  };
}

// Query parameters for pagination
interface PaginationParams {
  page?: number;         // Default: 1
  pageSize?: number;     // Default: 20, Max: 100
  sortBy?: string;       // Field to sort by
  sortOrder?: 'asc' | 'desc';  // Default: 'desc'
}
```

### 1.3 Common Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Authorization` | Yes* | `Bearer {accessToken}` (*except auth endpoints) |
| `Content-Type` | Yes | `application/json` |
| `X-Request-Id` | No | Client-provided request ID for tracing |
| `Accept-Language` | No | Preferred response language |

---

## 2. Authentication Endpoints

### 2.1 POST /api/v1/auth/register

Create a new user account.

**Request:**
```typescript
interface RegisterRequest {
  username: string;      // 3-30 chars, alphanumeric + underscore
  email: string;         // Valid email format
  password: string;      // 8-128 chars, complexity required
  displayName?: string;  // 1-100 chars
}
```

**Response (201 Created):**
```typescript
interface RegisterResponse {
  user: {
    id: string;
    username: string;
    email: string;
    displayName: string | null;
    createdAt: string;
  };
  tokens: {
    accessToken: string;
    refreshToken: string;
    expiresIn: number;   // Seconds
    tokenType: 'Bearer';
  };
}
```

**Error Codes:**
| Code | HTTP | Description |
|------|------|-------------|
| ERR_USERNAME_TAKEN | 409 | Username already exists |
| ERR_EMAIL_TAKEN | 409 | Email already registered |
| ERR_PASSWORD_WEAK | 400 | Password doesn't meet requirements |
| ERR_VALIDATION | 400 | Invalid input format |

---

### 2.2 POST /api/v1/auth/login

Authenticate user and receive tokens.

**Request:**
```typescript
interface LoginRequest {
  identifier: string;    // Username or email
  password: string;
  deviceInfo?: {
    userAgent: string;
    platform: 'web' | 'desktop' | 'mobile';
  };
}
```

**Response (200 OK):**
```typescript
interface LoginResponse {
  user: {
    id: string;
    username: string;
    email: string;
    displayName: string | null;
    lastLoginAt: string;
  };
  tokens: {
    accessToken: string;
    refreshToken: string;
    expiresIn: number;
    tokenType: 'Bearer';
  };
}
```

**Error Codes:**
| Code | HTTP | Description |
|------|------|-------------|
| ERR_INVALID_CREDENTIALS | 401 | Wrong username/password |
| ERR_ACCOUNT_LOCKED | 423 | Too many failed attempts |
| ERR_ACCOUNT_DISABLED | 403 | Account deactivated |

---

### 2.3 POST /api/v1/auth/refresh

Refresh access token using refresh token.

**Request:**
```typescript
interface RefreshRequest {
  refreshToken: string;
}
```

**Response (200 OK):**
```typescript
interface RefreshResponse {
  tokens: {
    accessToken: string;
    refreshToken: string;
    expiresIn: number;
    tokenType: 'Bearer';
  };
}
```

---

### 2.4 POST /api/v1/auth/logout

Invalidate current session.

**Request:**
```typescript
interface LogoutRequest {
  refreshToken: string;
  allDevices?: boolean;  // Logout from all sessions
}
```

**Response (200 OK):**
```typescript
interface LogoutResponse {
  loggedOut: boolean;
  sessionsRevoked: number;
}
```

---

### 2.5 GET /api/v1/auth/me

Get current authenticated user.

**Response (200 OK):**
```typescript
interface MeResponse {
  id: string;
  username: string;
  email: string;
  displayName: string | null;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
  lastLoginAt: string;
  sessions: {
    id: string;
    deviceInfo: string;
    lastActiveAt: string;
    isCurrent: boolean;
  }[];
}
```

---

## 3. Project Endpoints

### 3.1 GET /api/v1/projects

List all projects accessible to user.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | number | 1 | Page number |
| pageSize | number | 20 | Items per page |
| search | string | - | Search in name/description |
| sortBy | string | 'updatedAt' | Sort field |
| sortOrder | string | 'desc' | Sort direction |

**Response (200 OK):**
```typescript
interface ProjectListResponse extends PaginatedResponse<Project> {}

interface Project {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  ownerId: string;
  workDirectory: string;
  healthScore: number;
  healthGrade: 'A' | 'B' | 'C' | 'D' | 'F';
  lastConsistencyCheck: string | null;
  createdAt: string;
  updatedAt: string;
  stats: {
    fileCount: number;
    ideaCount: number;
    instructionCount: number;
  };
}
```

---

### 3.2 POST /api/v1/projects

Create a new project.

**Request:**
```typescript
interface CreateProjectRequest {
  name: string;           // 1-100 chars
  slug?: string;          // Auto-generated if not provided
  description?: string;   // Max 500 chars
  workDirectory?: string; // Custom work directory path
  settings?: {
    autoSnapshot: boolean;
    autoCommit: boolean;
    executionMode: 'automatic' | 'approval';
  };
}
```

**Response (201 Created):**
```typescript
interface CreateProjectResponse {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  workDirectory: string;
  createdAt: string;
}
```

---

### 3.3 GET /api/v1/projects/{projectId}

Get project details.

**Response (200 OK):**
```typescript
interface ProjectDetailResponse extends Project {
  settings: {
    autoSnapshot: boolean;
    autoCommit: boolean;
    executionMode: 'automatic' | 'approval';
    thinkingModelId: string | null;
    writingModelId: string | null;
    voiceModelId: string | null;
  };
  recentActivity: {
    type: 'file_updated' | 'instruction_created' | 'snapshot_created';
    description: string;
    timestamp: string;
  }[];
}
```

---

### 3.4 PUT /api/v1/projects/{projectId}

Update project.

**Request:**
```typescript
interface UpdateProjectRequest {
  name?: string;
  description?: string;
  settings?: Partial<ProjectSettings>;
}
```

**Response (200 OK):** Same as ProjectDetailResponse

---

### 3.5 DELETE /api/v1/projects/{projectId}

Delete project.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| permanent | boolean | false | Permanently delete |
| deleteFiles | boolean | false | Also delete project files |

**Response (200 OK):**
```typescript
interface DeleteProjectResponse {
  id: string;
  deletedAt: string;
  permanent: boolean;
  filesDeleted: boolean;
}
```

---

## 4. File Endpoints

### 4.1 GET /api/v1/projects/{projectId}/files

List files in project.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| path | string | '/' | Directory path |
| recursive | boolean | false | Include subdirectories |
| includeDeleted | boolean | false | Include soft-deleted |
| type | string | - | Filter: 'file', 'directory' |

**Response (200 OK):**
```typescript
interface FileListResponse {
  path: string;
  items: (FileItem | DirectoryItem)[];
  totalFiles: number;
  totalDirectories: number;
}

interface FileItem {
  type: 'file';
  id: string;
  name: string;
  path: string;
  sizeBytes: number;
  contentHash: string;
  mimeType: string;
  createdAt: string;
  updatedAt: string;
  deletedAt: string | null;
}

interface DirectoryItem {
  type: 'directory';
  name: string;
  path: string;
  fileCount: number;
}
```

---

### 4.2 POST /api/v1/projects/{projectId}/files

Create a new file.

**Request:**
```typescript
interface CreateFileRequest {
  path: string;           // Relative path including filename
  content: string;        // File content (UTF-8)
  createDirectories?: boolean;  // Create parent dirs if missing
}
```

**Response (201 Created):**
```typescript
interface CreateFileResponse {
  id: string;
  projectId: string;
  path: string;
  name: string;
  contentHash: string;
  sizeBytes: number;
  createdAt: string;
  updatedAt: string;
}
```

**Error Codes:**
| Code | HTTP | Description |
|------|------|-------------|
| ERR_FILE_EXISTS | 409 | File already exists at path |
| ERR_INVALID_PATH | 400 | Path validation failed |
| ERR_PATH_TRAVERSAL | 400 | Path contains '..' |
| ERR_RESERVED_PATH | 400 | Path is system-reserved |

---

### 4.3 GET /api/v1/projects/{projectId}/files/{fileId}

Get file details and content.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| includeContent | boolean | true | Include file content |
| version | string | 'latest' | Specific snapshot version |

**Response (200 OK):**
```typescript
interface FileDetailResponse {
  id: string;
  projectId: string;
  path: string;
  name: string;
  content?: string;       // If includeContent=true
  contentHash: string;
  sizeBytes: number;
  mimeType: string;
  createdAt: string;
  updatedAt: string;
  versions: {
    version: string;
    createdAt: string;
    sizeBytes: number;
  }[];
}
```

---

### 4.4 PUT /api/v1/projects/{projectId}/files/{fileId}

Update file content.

**Request:**
```typescript
interface UpdateFileRequest {
  content: string;
  expectedHash?: string;  // For optimistic locking
}
```

**Response (200 OK):**
```typescript
interface UpdateFileResponse {
  id: string;
  path: string;
  contentHash: string;
  previousHash: string;
  sizeBytes: number;
  updatedAt: string;
}
```

**Error Codes:**
| Code | HTTP | Description |
|------|------|-------------|
| ERR_CONFLICT | 409 | Hash mismatch (concurrent edit) |
| ERR_FILE_NOT_FOUND | 404 | File doesn't exist |

---

### 4.5 DELETE /api/v1/projects/{projectId}/files/{fileId}

Delete file.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| permanent | boolean | false | Skip trash |

**Response (200 OK):**
```typescript
interface DeleteFileResponse {
  id: string;
  deletedAt: string;
  permanent: boolean;
  recoveryDeadline: string | null;  // If soft-deleted
}
```

---

### 4.6 PATCH /api/v1/projects/{projectId}/files/{fileId}/move

Move or rename file.

**Request:**
```typescript
interface MoveFileRequest {
  newPath: string;
}
```

**Response (200 OK):**
```typescript
interface MoveFileResponse {
  id: string;
  previousPath: string;
  newPath: string;
  movedAt: string;
}
```

---

### 4.7 POST /api/v1/projects/{projectId}/files/bulk

Bulk file operations.

**Request:**
```typescript
interface BulkFileRequest {
  operation: 'create' | 'update' | 'delete' | 'move';
  files: {
    path?: string;
    content?: string;
    newPath?: string;
    fileId?: string;
  }[];
  options?: {
    stopOnError: boolean;   // Default: false
    createDirectories: boolean;
  };
}
```

**Response (200 OK):**
```typescript
interface BulkFileResponse {
  succeeded: number;
  failed: number;
  results: {
    path: string;
    success: boolean;
    error?: string;
    fileId?: string;
  }[];
}
```

---

## 5. Instruction Endpoints

### 5.1 GET /api/v1/projects/{projectId}/instructions

List instructions.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| status | string | - | Filter by status |
| scope | string | - | Filter by scope |
| page | number | 1 | Page number |
| pageSize | number | 20 | Items per page |

**Response (200 OK):**
```typescript
interface InstructionListResponse extends PaginatedResponse<Instruction> {}

interface Instruction {
  id: string;
  projectId: string;
  rawTranscription: string | null;
  proofreadText: string;
  instructionText: string;
  scope: 'global' | 'backend' | 'frontend' | 'file';
  targetFilePath: string | null;
  status: InstructionStatus;
  executionMode: 'automatic' | 'approval';
  planMarkdown: string | null;
  taskCount: number;
  completedTaskCount: number;
  createdAt: string;
  updatedAt: string;
  completedAt: string | null;
}

type InstructionStatus = 
  | 'transcribed' | 'proofreading' | 'proofread'
  | 'planning' | 'planned' | 'reviewing'
  | 'ready' | 'executing' | 'completed' | 'failed' | 'cancelled';
```

---

### 5.2 POST /api/v1/projects/{projectId}/instructions

Create instruction from text.

**Request:**
```typescript
interface CreateInstructionRequest {
  text: string;
  scope?: 'global' | 'backend' | 'frontend' | 'file';
  targetFilePath?: string;
  executionMode?: 'automatic' | 'approval';
  modelPreferences?: {
    thinkingModelId?: string;
    writingModelId?: string;
  };
}
```

**Response (201 Created):**
```typescript
interface CreateInstructionResponse {
  id: string;
  status: 'transcribed';
  proofreadText: string;
  scope: string;
  createdAt: string;
}
```

---

### 5.3 POST /api/v1/projects/{projectId}/instructions/voice

Create instruction from voice.

**Request:** `multipart/form-data`
| Field | Type | Description |
|-------|------|-------------|
| audio | File | Audio file (WAV, MP3, WebM) |
| scope | string | Target scope |
| targetFilePath | string | For file-scoped |

**Response (201 Created):** Same as CreateInstructionResponse

---

### 5.4 GET /api/v1/projects/{projectId}/instructions/{instructionId}

Get instruction details.

**Response (200 OK):**
```typescript
interface InstructionDetailResponse extends Instruction {
  tasks: InstructionTask[];
  modelInfo: {
    thinkingModelId: string | null;
    writingModelId: string | null;
    voiceModelId: string | null;
  };
  metrics: {
    planningTokensUsed: number;
    planningDurationMs: number;
    totalExecutionMs: number;
  };
}

interface InstructionTask {
  id: string;
  parentTaskId: string | null;
  title: string;
  description: string | null;
  taskType: 'create' | 'update' | 'delete' | 'refactor' | 'review' | 'verify';
  targetFilePath: string | null;
  dependsOn: string[];
  sortOrder: number;
  status: 'pending' | 'blocked' | 'ready' | 'in_progress' | 'completed' | 'failed' | 'skipped';
  resultMarkdown: string | null;
  errorMessage: string | null;
  executionDurationMs: number | null;
  createdAt: string;
  completedAt: string | null;
}
```

---

### 5.5 POST /api/v1/projects/{projectId}/instructions/{instructionId}/approve

Approve instruction for execution.

**Response (200 OK):**
```typescript
interface ApproveInstructionResponse {
  id: string;
  status: 'ready';
  approvedAt: string;
  approvedById: string;
}
```

---

### 5.6 POST /api/v1/projects/{projectId}/instructions/{instructionId}/execute

Start instruction execution.

**Response (200 OK):**
```typescript
interface ExecuteInstructionResponse {
  id: string;
  status: 'executing';
  startedAt: string;
  estimatedDurationMs: number;
}
```

---

### 5.7 POST /api/v1/projects/{projectId}/instructions/{instructionId}/cancel

Cancel instruction execution.

**Response (200 OK):**
```typescript
interface CancelInstructionResponse {
  id: string;
  status: 'cancelled';
  cancelledAt: string;
  tasksCompleted: number;
  tasksCancelled: number;
}
```

---

## 6. RAG System Endpoints

### 6.1 POST /api/v1/projects/{projectId}/rag/index

Trigger re-indexing of project artifacts.

**Request:**
```typescript
interface IndexRequest {
  mode: 'full' | 'incremental';
  paths?: string[];       // Specific paths, or all if empty
}
```

**Response (202 Accepted):**
```typescript
interface IndexResponse {
  jobId: string;
  status: 'queued';
  estimatedDurationMs: number;
}
```

---

### 6.2 GET /api/v1/projects/{projectId}/rag/status

Get RAG index status.

**Response (200 OK):**
```typescript
interface RAGStatusResponse {
  projectId: string;
  indexedFiles: number;
  totalChunks: number;
  embeddingsCount: number;
  lastIndexedAt: string;
  indexHealth: 'healthy' | 'stale' | 'rebuilding';
  pendingFiles: string[];
}
```

---

### 6.3 POST /api/v1/projects/{projectId}/rag/query

Query RAG for relevant context.

**Request:**
```typescript
interface RAGQueryRequest {
  query: string;
  topK?: number;          // Default: 10
  filters?: {
    fileTypes?: ('idea' | 'instruction' | 'spec')[];
    paths?: string[];
    minScore?: number;
  };
  includeRecent?: boolean; // Include recent artifacts
}
```

**Response (200 OK):**
```typescript
interface RAGQueryResponse {
  query: string;
  results: {
    chunkId: string;
    filePath: string;
    content: string;
    score: number;
    metadata: {
      fileType: string;
      section: string | null;
      createdAt: string;
    };
  }[];
  totalMatches: number;
  queryEmbeddingMs: number;
  searchMs: number;
}
```

---

### 6.4 POST /api/v1/projects/{projectId}/ideas

Create a new idea.

**Request:**
```typescript
interface CreateIdeaRequest {
  title: string;
  content: string;
  sourceType: 'voice' | 'text';
  rawTranscription?: string;
  priority?: 'low' | 'medium' | 'high' | 'critical';
  tags?: string[];
}
```

**Response (201 Created):**
```typescript
interface CreateIdeaResponse {
  id: string;
  filePath: string;
  title: string;
  status: 'draft';
  createdAt: string;
}
```

---

### 6.5 POST /api/v1/projects/{projectId}/ideas/{ideaId}/promote

Promote idea to instruction.

**Request:**
```typescript
interface PromoteIdeaRequest {
  scope?: 'global' | 'backend' | 'frontend' | 'file';
  targetFilePath?: string;
  additionalContext?: string;
}
```

**Response (200 OK):**
```typescript
interface PromoteIdeaResponse {
  ideaId: string;
  instructionId: string;
  instructionFilePath: string;
  ideaStatus: 'promoted';
  promotedAt: string;
}
```

---

## 7. Consistency Checker Endpoints

### 7.1 POST /api/v1/projects/{projectId}/consistency/run

Generate consistency report.

**Request:**
```typescript
interface RunConsistencyRequest {
  reportType: 'cross-reference' | 'schema-api' | 'terminology' | 'completeness' | 'full-health';
  includeAutoFixes?: boolean;
}
```

**Response (200 OK):**
```typescript
interface ConsistencyReportResponse {
  reportId: string;
  projectId: string;
  reportType: string;
  generatedAt: string;
  durationMs: number;
  score: number;
  grade: 'A' | 'B' | 'C' | 'D' | 'F';
  summary: {
    totalFilesScanned: number;
    totalLinksChecked: number;
    validLinks: number;
    brokenLinks: number;
    warningsCount: number;
    errorsCount: number;
  };
  findings: Finding[];
  recommendations: Recommendation[];
}

interface Finding {
  id: string;
  severity: 'error' | 'warning' | 'info';
  category: string;
  filePath: string;
  line?: number;
  message: string;
  suggestion?: string;
  autoFixable: boolean;
}

interface Recommendation {
  priority: 'high' | 'medium' | 'low';
  category: string;
  description: string;
  affectedFiles: string[];
  estimatedEffort: string;
}
```

---

### 7.2 GET /api/v1/projects/{projectId}/consistency/latest

Get most recent report.

**Response (200 OK):** Same as ConsistencyReportResponse

---

### 7.3 GET /api/v1/projects/{projectId}/consistency/history

List past reports.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| limit | number | 10 | Number of reports |
| reportType | string | - | Filter by type |

**Response (200 OK):**
```typescript
interface ConsistencyHistoryResponse {
  reports: {
    reportId: string;
    reportType: string;
    generatedAt: string;
    score: number;
    grade: string;
    findingsCount: number;
  }[];
}
```

---

### 7.4 POST /api/v1/consistency/reports/{reportId}/preview-fixes

Preview auto-fixes for a report.

**Response (200 OK):**
```typescript
interface PreviewFixesResponse {
  reportId: string;
  fixes: {
    findingId: string;
    filePath: string;
    lineNumber: number;
    oldContent: string;
    newContent: string;
    confidence: number;
  }[];
  totalFixable: number;
  totalHighConfidence: number;
}
```

---

### 7.5 POST /api/v1/consistency/reports/{reportId}/apply-fixes

Apply selected auto-fixes.

**Request:**
```typescript
interface ApplyFixesRequest {
  findingIds?: string[];   // Specific fixes, or all if empty
  minConfidence?: number;  // Default: 0.9
  dryRun?: boolean;        // Preview only
}
```

**Response (200 OK):**
```typescript
interface ApplyFixesResponse {
  applied: number;
  skipped: number;
  failed: number;
  results: {
    findingId: string;
    status: 'applied' | 'skipped' | 'failed';
    error?: string;
  }[];
}
```

---

## 8. History & Snapshot Endpoints

### 8.1 GET /api/v1/projects/{projectId}/snapshots

List snapshots.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | number | 1 | Page number |
| pageSize | number | 20 | Items per page |
| type | string | - | Filter: 'manual', 'auto' |

**Response (200 OK):**
```typescript
interface SnapshotListResponse extends PaginatedResponse<Snapshot> {}

interface Snapshot {
  id: string;
  projectId: string;
  name: string;
  description: string | null;
  type: 'manual' | 'auto';
  filesChanged: number;
  sizeBytes: number;
  gitCommitHash: string | null;
  createdAt: string;
  createdById: string;
}
```

---

### 8.2 POST /api/v1/projects/{projectId}/snapshots

Create manual snapshot.

**Request:**
```typescript
interface CreateSnapshotRequest {
  name: string;
  description?: string;
  commitToGit?: boolean;
}
```

**Response (201 Created):**
```typescript
interface CreateSnapshotResponse {
  id: string;
  name: string;
  filesIncluded: number;
  sizeBytes: number;
  gitCommitHash: string | null;
  createdAt: string;
}
```

---

### 8.3 GET /api/v1/projects/{projectId}/snapshots/{snapshotId}

Get snapshot details.

**Response (200 OK):**
```typescript
interface SnapshotDetailResponse extends Snapshot {
  files: {
    path: string;
    action: 'created' | 'modified' | 'deleted';
    sizeBytes: number;
    contentHash: string;
  }[];
}
```

---

### 8.4 POST /api/v1/projects/{projectId}/snapshots/{snapshotId}/restore

Restore project to snapshot.

**Request:**
```typescript
interface RestoreSnapshotRequest {
  createBackupFirst?: boolean;  // Default: true
  paths?: string[];             // Specific files, or all if empty
}
```

**Response (200 OK):**
```typescript
interface RestoreSnapshotResponse {
  restoredFiles: number;
  backupSnapshotId: string | null;
  restoredAt: string;
}
```

---

### 8.5 GET /api/v1/projects/{projectId}/files/{fileId}/history

Get file version history.

**Response (200 OK):**
```typescript
interface FileHistoryResponse {
  fileId: string;
  filePath: string;
  versions: {
    version: number;
    snapshotId: string;
    contentHash: string;
    sizeBytes: number;
    changedAt: string;
    changedById: string;
  }[];
}
```

---

### 8.6 GET /api/v1/projects/{projectId}/files/{fileId}/diff

Compare file versions.

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| fromVersion | number | Yes | Base version |
| toVersion | number | Yes | Target version |

**Response (200 OK):**
```typescript
interface FileDiffResponse {
  fileId: string;
  fromVersion: number;
  toVersion: number;
  diff: {
    type: 'unified' | 'side-by-side';
    content: string;
    linesAdded: number;
    linesRemoved: number;
  };
}
```

---

## 9. LLM Server Management Endpoints

### 9.1 GET /api/v1/llm/models

List available models.

**Response (200 OK):**
```typescript
interface ModelListResponse {
  models: {
    id: string;
    name: string;
    category: 'thinking' | 'writing' | 'voice' | 'coding';
    backend: 'ollama' | 'llama-cpp' | 'llama-swap';
    status: 'available' | 'loading' | 'loaded' | 'error';
    sizeBytes: number;
    memoryRequired: number;
    isDefault: boolean;
  }[];
}
```

---

### 9.2 POST /api/v1/llm/models/{modelId}/load

Load model into memory.

**Response (200 OK):**
```typescript
interface LoadModelResponse {
  modelId: string;
  status: 'loading';
  estimatedLoadTimeMs: number;
  port: number;
}
```

---

### 9.3 POST /api/v1/llm/models/{modelId}/unload

Unload model from memory.

**Response (200 OK):**
```typescript
interface UnloadModelResponse {
  modelId: string;
  status: 'unloaded';
  memoryFreed: number;
}
```

---

### 9.4 GET /api/v1/llm/status

Get LLM server status.

**Response (200 OK):**
```typescript
interface LLMStatusResponse {
  serverStatus: 'running' | 'stopped' | 'error';
  backend: string;
  loadedModels: {
    modelId: string;
    port: number;
    memoryUsed: number;
    requestsProcessed: number;
    lastUsedAt: string;
  }[];
  availableMemory: number;
  totalMemory: number;
  uptime: number;
}
```

---

### 9.5 POST /api/v1/llm/generate

Generate text with LLM.

**Request:**
```typescript
interface GenerateRequest {
  modelId?: string;        // Uses default if not specified
  systemPrompt?: string;
  userPrompt: string;
  maxTokens?: number;
  temperature?: number;
  topP?: number;
  stream?: boolean;
}
```

**Response (200 OK):**
```typescript
interface GenerateResponse {
  text: string;
  modelId: string;
  tokensUsed: {
    prompt: number;
    completion: number;
    total: number;
  };
  durationMs: number;
  finishReason: 'stop' | 'length' | 'error';
}
```

---

## 10. Knowledge Memory Endpoints

### 10.1 GET /api/v1/projects/{projectId}/knowledge/sources

List knowledge sources.

**Response (200 OK):**
```typescript
interface KnowledgeSourcesResponse {
  sources: {
    id: string;
    type: 'url' | 'file' | 'crawler';
    name: string;
    url?: string;
    filePath?: string;
    status: 'active' | 'syncing' | 'error';
    lastSyncAt: string;
    itemCount: number;
  }[];
}
```

---

### 10.2 POST /api/v1/projects/{projectId}/knowledge/sources

Add knowledge source.

**Request:**
```typescript
interface AddKnowledgeSourceRequest {
  type: 'url' | 'file' | 'crawler';
  name: string;
  url?: string;
  filePath?: string;
  crawlerConfig?: {
    maxDepth: number;
    allowedPatterns: string[];
    excludePatterns: string[];
  };
}
```

**Response (201 Created):**
```typescript
interface AddKnowledgeSourceResponse {
  id: string;
  type: string;
  name: string;
  status: 'pending';
  createdAt: string;
}
```

---

### 10.3 POST /api/v1/projects/{projectId}/knowledge/sources/{sourceId}/sync

Sync knowledge source.

**Response (202 Accepted):**
```typescript
interface SyncKnowledgeResponse {
  sourceId: string;
  jobId: string;
  status: 'syncing';
  estimatedDurationMs: number;
}
```

---

## 11. Error Code Reference

### 11.1 Error Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| 1xxx | Validation | Input validation errors |
| 2xxx | Authentication | Auth and authorization errors |
| 3xxx | Database | Database operation errors |
| 4xxx | External Services | Third-party service errors |
| 5xxx | Business Logic | Domain-specific errors |
| 6xxx | File System | File operation errors |
| 7xxx | LLM/AI | AI service errors |
| 8xxx | RAG/Knowledge | RAG system errors |
| 9xxx | System | Internal system errors |

### 11.2 Common Error Codes

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 1001 | ERR_VALIDATION | 400 | Generic validation error |
| 1002 | ERR_REQUIRED_FIELD | 400 | Required field missing |
| 1003 | ERR_INVALID_FORMAT | 400 | Invalid data format |
| 2001 | ERR_UNAUTHORIZED | 401 | Not authenticated |
| 2002 | ERR_FORBIDDEN | 403 | Not authorized |
| 2003 | ERR_TOKEN_EXPIRED | 401 | Access token expired |
| 2004 | ERR_INVALID_CREDENTIALS | 401 | Wrong username/password |
| 2005 | ERR_ACCOUNT_LOCKED | 423 | Account temporarily locked |
| 3001 | ERR_NOT_FOUND | 404 | Resource not found |
| 3002 | ERR_CONFLICT | 409 | Resource conflict |
| 3003 | ERR_DATABASE | 500 | Database error |
| 6001 | ERR_FILE_NOT_FOUND | 404 | File doesn't exist |
| 6002 | ERR_FILE_EXISTS | 409 | File already exists |
| 6003 | ERR_HASH_MISMATCH | 409 | Optimistic lock failed |
| 6004 | ERR_INVALID_PATH | 400 | Invalid file path |
| 6005 | ERR_PATH_TRAVERSAL | 400 | Path traversal attempt |
| 7001 | ERR_MODEL_NOT_FOUND | 404 | LLM model not available |
| 7002 | ERR_MODEL_BUSY | 503 | Model is loading |
| 7003 | ERR_GENERATION_FAILED | 500 | Text generation failed |
| 8001 | ERR_RAG_INDEX_FAILED | 500 | Indexing failed |
| 8002 | ERR_RAG_QUERY_FAILED | 500 | Query failed |
| 9001 | ERR_INTERNAL | 500 | Internal server error |
| 9002 | ERR_SERVICE_UNAVAILABLE | 503 | Service temporarily unavailable |

---

## 12. Rate Limiting

### 12.1 Rate Limit Headers

All responses include rate limit headers:

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | Requests allowed per window |
| `X-RateLimit-Remaining` | Requests remaining |
| `X-RateLimit-Reset` | Unix timestamp when window resets |
| `Retry-After` | Seconds to wait (when 429) |

### 12.2 Rate Limits by Endpoint Category

| Category | Limit | Window |
|----------|-------|--------|
| Auth endpoints | 10 | 1 minute |
| Read endpoints | 100 | 1 minute |
| Write endpoints | 30 | 1 minute |
| LLM generation | 10 | 1 minute |
| Bulk operations | 5 | 1 minute |

---

## 13. Versioning

### 13.1 API Version

Current version: `v1`

Base URL: `/api/v1/`

### 13.2 Version Header

Clients can specify version via header:
```
Accept: application/vnd.specmgmt.v1+json
```

---

## Related Specs

- [HTTP Client](./01-http-client.md) - Client configuration
- [Authentication](../01-authentication/01-authentication.md) - Auth details
- [File Operations](../02-file-management/01-file-operations.md) - File handling
- [Instruction System](../06-ai-integration/03-instruction-system.md) - Instruction pipeline
- [RAG System](../09-knowledge-memory/01-rag-system.md) - RAG details
- [Consistency Checker](../08-consistency-checker/01-consistency-checker.md) - Validation

---

## Acceptance Criteria

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AC-001 | All endpoints return standard response envelope | Critical | Schema validation |
| AC-002 | Authentication required for all non-auth endpoints | Critical | Integration test |
| AC-003 | Pagination supported on all list endpoints | High | API test |
| AC-004 | Error codes follow defined ranges | High | Code review |
| AC-005 | Rate limiting applied per category | High | Load test |
| AC-006 | All request/response schemas validated | Critical | Contract test |
| AC-007 | OpenAPI spec matches implementation | High | Schema validation |
| AC-008 | All endpoints documented with examples | Medium | Documentation review |
| AC-009 | Versioning header supported | Medium | Integration test |
| AC-010 | CORS headers properly configured | High | Security test |
