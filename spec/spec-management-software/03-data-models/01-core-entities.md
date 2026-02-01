# Core Entities

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## User

```typescript
interface User {
  readonly id: string;
  email: string;
  name: string;
  avatarUrl: string | null;
  role: UserRole;
  preferences: UserPreferences;
  createdAt: string;
  updatedAt: string;
  lastActiveAt: string | null;
}

type UserRole = 'admin' | 'editor' | 'viewer';

interface UserPreferences {
  theme: 'light' | 'dark' | 'system';
  editorFontSize: number;
  sidebarWidth: number;
  keyboardShortcuts: boolean;
  notifications: NotificationPreferences;
}

interface NotificationPreferences {
  email: boolean;
  inApp: boolean;
  digest: 'daily' | 'weekly' | 'none';
}
```

---

## Project

```typescript
interface Project {
  readonly id: string;
  name: string;
  slug: string;
  description: string | null;
  workDirectory: string;
  status: ProjectStatus;
  settings: ProjectSettings;
  stats: ProjectStats;
  createdAt: string;
  updatedAt: string;
  createdBy: string;
}

type ProjectStatus = 'active' | 'archived' | 'deleted';

interface ProjectSettings {
  autoSave: boolean;
  autoCommit: boolean;
  autoSnapshot: boolean;
  snapshotInterval: 'hourly' | 'daily' | 'weekly';
  consistencyCheckOnSave: boolean;
}

interface ProjectStats {
  fileCount: number;
  totalSize: number;
  lastModified: string;
  specCount: number;
  completionPercentage: number;
}
```

---

## File

```typescript
interface File {
  readonly id: string;
  projectId: string;
  path: string;
  name: string;
  content: string | null;      // Only populated on detail view
  contentHash: string;
  sizeBytes: number;
  mimeType: string;
  status: FileStatus;
  metadata: FileMetadata;
  createdAt: string;
  updatedAt: string;
  deletedAt: string | null;
}

type FileStatus = 'active' | 'deleted' | 'archived';

interface FileMetadata {
  version: string | null;
  status: SpecStatus | null;
  wordCount: number;
  lastEditor: string | null;
  tags: string[];
}

type SpecStatus = 
  | 'draft' 
  | 'planned' 
  | 'in-progress' 
  | 'complete' 
  | 'deprecated';
```

---

## Directory

```typescript
interface Directory {
  path: string;
  name: string;
  fileCount: number;
  children: (Directory | FileInfo)[];
}

interface FileInfo {
  id: string;
  name: string;
  path: string;
  sizeBytes: number;
  updatedAt: string;
  status: SpecStatus | null;
}
```

---

## Spec (Parsed File)

```typescript
interface Spec {
  readonly id: string;
  fileId: string;
  title: string;
  version: string;
  status: SpecStatus;
  updatedAt: string;
  
  frontmatter: SpecFrontmatter;
  sections: SpecSection[];
  references: SpecReference[];
  todos: SpecTodo[];
}

interface SpecFrontmatter {
  version: string;
  status: SpecStatus;
  updated: string;
  author?: string;
  tags?: string[];
  dependencies?: string[];
}

interface SpecSection {
  id: string;
  level: 1 | 2 | 3 | 4 | 5 | 6;
  title: string;
  anchor: string;
  content: string;
  startLine: number;
  endLine: number;
  children: SpecSection[];
}

interface SpecReference {
  type: 'internal' | 'external';
  text: string;
  target: string;
  line: number;
  valid: boolean;
  resolvedPath?: string;
}

interface SpecTodo {
  id: string;
  text: string;
  line: number;
  completed: boolean;
}
```

---

## API Response Wrapper

```typescript
interface ApiResponse<T> {
  success: boolean;
  data: T;
  error: ApiError | null;
  meta: ApiMeta;
}

interface ApiError {
  code: number;
  message: string;
  details?: Record<string, unknown>;
  stack?: string;
}

interface ApiMeta {
  requestId: string;
  timestamp: string;
  version: string;
  pagination?: Pagination;
}

interface Pagination {
  page: number;
  pageSize: number;
  totalItems: number;
  totalPages: number;
  hasNext: boolean;
  hasPrevious: boolean;
}
```

---

## CRUD Operations

```typescript
interface CreateFileRequest {
  path: string;
  content: string;
  createDirectories?: boolean;
}

interface UpdateFileRequest {
  content: string;
  expectedHash?: string;  // Optimistic locking
}

interface MoveFileRequest {
  newPath: string;
}

interface CreateProjectRequest {
  name: string;
  slug?: string;
  description?: string;
  workDirectory: string;
}

interface UpdateProjectRequest {
  name?: string;
  description?: string;
  settings?: Partial<ProjectSettings>;
}
```
