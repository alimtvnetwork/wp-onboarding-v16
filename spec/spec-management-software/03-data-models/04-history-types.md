# History Types

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Version

```typescript
interface Version {
  readonly id: string;          // Git commit SHA
  fileId: string;
  message: string;
  author: string;
  authorEmail: string;
  timestamp: string;
  parentId: string | null;
  size: number;
  hash: string;                 // Content SHA-256
}

interface VersionListResponse {
  versions: Version[];
  totalCount: number;
  hasMore: boolean;
  cursor: string | null;
}

interface VersionContent {
  version: Version;
  content: string;
  mimeType: string;
}
```

---

## Snapshot

```typescript
interface Snapshot {
  readonly id: string;
  projectId: string;
  name: string;
  description: string | null;
  tag: string;                  // Git tag (V{nn}-{YYYY-MM-DD})
  commitSha: string;
  fileCount: number;
  totalSize: number;
  createdAt: string;
  createdBy: string;
  metadata: SnapshotMetadata;
}

interface SnapshotMetadata {
  trigger: SnapshotTrigger;
  previousSnapshotId: string | null;
  changesSinceLastSnapshot: number;
  autoExpireAt: string | null;
}

type SnapshotTrigger = 
  | 'manual'
  | 'scheduled'
  | 'before_restore'
  | 'before_merge'
  | 'milestone';

interface CreateSnapshotRequest {
  name: string;
  description?: string;
}

interface RestoreSnapshotRequest {
  snapshotId: string;
  createBackup: boolean;
}
```

---

## Diff

```typescript
interface DiffResult {
  fileId: string;
  fromVersion: string;          // SHA or 'working'
  toVersion: string;            // SHA or 'working'
  hunks: DiffHunk[];
  stats: DiffStats;
  binary: boolean;
}

interface DiffHunk {
  oldStart: number;
  oldLines: number;
  newStart: number;
  newLines: number;
  header: string;               // @@ line
  lines: DiffLine[];
}

interface DiffLine {
  type: DiffLineType;
  content: string;
  oldLineNumber: number | null;
  newLineNumber: number | null;
}

type DiffLineType = 'context' | 'add' | 'delete';

interface DiffStats {
  additions: number;
  deletions: number;
  changes: number;
}

interface DiffRequest {
  fileId: string;
  fromVersion?: string;         // Default: previous version
  toVersion?: string;           // Default: working copy
}
```

---

## Undo/Redo

```typescript
interface UndoStack {
  canUndo: boolean;
  canRedo: boolean;
  undoDescription: string | null;
  redoDescription: string | null;
  pendingOperations: number;
}

interface UndoOperation {
  id: string;
  type: OperationType;
  fileId: string;
  timestamp: string;
  description: string;
  patches: Patch[];
}

type OperationType = 
  | 'edit'
  | 'create'
  | 'delete'
  | 'rename'
  | 'move';

interface Patch {
  offset: number;
  length: number;
  text: string;
}

interface UndoResult {
  success: boolean;
  operation: UndoOperation;
  newContent: string;
}
```

---

## Merge

```typescript
interface MergeRequest {
  sourceVersion: string;
  targetVersion: string;
  strategy: MergeStrategy;
}

type MergeStrategy = 
  | 'ours'
  | 'theirs'
  | 'union'
  | 'manual';

interface MergeResult {
  success: boolean;
  content: string;
  conflicts: MergeConflict[];
  stats: MergeStats;
}

interface MergeConflict {
  id: string;
  startLine: number;
  endLine: number;
  ours: string;
  theirs: string;
  base: string | null;
  resolved: boolean;
  resolution?: string;
}

interface MergeStats {
  autoMerged: number;
  conflicts: number;
  additions: number;
  deletions: number;
}

interface ResolveConflictRequest {
  conflictId: string;
  resolution: 'ours' | 'theirs' | 'custom';
  customContent?: string;
}
```

---

## Restore

```typescript
interface RestoreRequest {
  fileId: string;
  versionId: string;
  createBackup: boolean;
}

interface RestoreResult {
  success: boolean;
  fileId: string;
  restoredVersion: string;
  backupVersion?: string;
  newContent: string;
}
```

---

## Git Operations

```typescript
interface GitCommit {
  sha: string;
  message: string;
  author: GitAuthor;
  committer: GitAuthor;
  timestamp: string;
  parentShas: string[];
  tree: string;
}

interface GitAuthor {
  name: string;
  email: string;
  timestamp: string;
}

interface GitTag {
  name: string;
  sha: string;
  message: string;
  tagger: GitAuthor;
  targetSha: string;
}

interface GitBranch {
  name: string;
  sha: string;
  isDefault: boolean;
  isProtected: boolean;
  aheadBehind: {
    ahead: number;
    behind: number;
  };
}

interface GitStatus {
  staged: GitFileStatus[];
  unstaged: GitFileStatus[];
  untracked: string[];
}

interface GitFileStatus {
  path: string;
  status: 'added' | 'modified' | 'deleted' | 'renamed';
  oldPath?: string;
}
```
