# Version Control

**Version:** 1.0.0  
**Status:** Specified  
**Updated:** 2026-01-30  
**Parent:** [Automation Pipeline](./00-overview.md)

---

## Overview

Git-inspired version control system for pipelines and templates, providing branching, merging, diff visualization, and rollback capabilities. Enables collaborative development, experimentation, and safe iteration on automation workflows.

---

## Database Schema

### PipelineVersion Table

```sql
CREATE TABLE PipelineVersion (
  Id              TEXT PRIMARY KEY,
  PipelineId      TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  -- Version info
  VersionNumber   INTEGER NOT NULL,         -- Auto-incrementing per pipeline
  VersionTag      TEXT,                      -- Optional semantic version (e.g., "v1.2.0")
  
  -- Snapshot
  SnapshotData    TEXT NOT NULL,            -- JSON: complete pipeline state
  
  -- Metadata
  Message         TEXT,                      -- Commit message
  Author          TEXT,
  ParentVersionId TEXT REFERENCES PipelineVersion(Id), -- Previous version
  
  -- Change summary
  ChangeSummary   TEXT,                      -- JSON: what changed
  
  -- Timestamps
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  
  UNIQUE(PipelineId, VersionNumber)
);

CREATE INDEX idx_version_pipeline ON PipelineVersion(PipelineId);
CREATE INDEX idx_version_tag ON PipelineVersion(VersionTag);
CREATE INDEX idx_version_parent ON PipelineVersion(ParentVersionId);
```

### PipelineBranch Table

```sql
CREATE TABLE PipelineBranch (
  Id              TEXT PRIMARY KEY,
  PipelineId      TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  Name            TEXT NOT NULL,             -- Branch name
  Description     TEXT,
  
  -- Branch state
  HeadVersionId   TEXT REFERENCES PipelineVersion(Id),
  BaseVersionId   TEXT REFERENCES PipelineVersion(Id), -- Where branch started
  
  -- Status
  IsDefault       INTEGER NOT NULL DEFAULT 0,
  IsProtected     INTEGER NOT NULL DEFAULT 0,
  IsMerged        INTEGER NOT NULL DEFAULT 0,
  MergedIntoId    TEXT REFERENCES PipelineBranch(Id),
  
  -- Timestamps
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  MergedAt        TEXT,
  
  UNIQUE(PipelineId, Name)
);

CREATE INDEX idx_branch_pipeline ON PipelineBranch(PipelineId);
CREATE INDEX idx_branch_head ON PipelineBranch(HeadVersionId);
```

### VersionChange Table

```sql
CREATE TABLE VersionChange (
  Id              TEXT PRIMARY KEY,
  VersionId       TEXT NOT NULL REFERENCES PipelineVersion(Id) ON DELETE CASCADE,
  
  -- Change details
  ChangeType      TEXT NOT NULL,             -- 'ADD', 'MODIFY', 'DELETE', 'MOVE'
  EntityType      TEXT NOT NULL,             -- 'BLOCK', 'STAGE', 'CONNECTION', 'VARIABLE'
  EntityId        TEXT NOT NULL,
  EntityName      TEXT,
  
  -- Before/after
  PreviousValue   TEXT,                      -- JSON: previous state
  NewValue        TEXT,                      -- JSON: new state
  
  -- Diff
  DiffData        TEXT,                      -- JSON: structured diff
  
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_change_version ON VersionChange(VersionId);
CREATE INDEX idx_change_entity ON VersionChange(EntityType, EntityId);
```

### MergeRequest Table

```sql
CREATE TABLE MergeRequest (
  Id              TEXT PRIMARY KEY,
  PipelineId      TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  -- Branches
  SourceBranchId  TEXT NOT NULL REFERENCES PipelineBranch(Id),
  TargetBranchId  TEXT NOT NULL REFERENCES PipelineBranch(Id),
  
  -- Status
  Status          TEXT NOT NULL DEFAULT 'OPEN', -- 'OPEN', 'MERGED', 'CLOSED', 'CONFLICTED'
  
  -- Metadata
  Title           TEXT NOT NULL,
  Description     TEXT,
  Author          TEXT,
  
  -- Conflict info
  HasConflicts    INTEGER NOT NULL DEFAULT 0,
  ConflictData    TEXT,                      -- JSON: conflict details
  
  -- Resolution
  MergeVersionId  TEXT REFERENCES PipelineVersion(Id),
  MergeStrategy   TEXT,                      -- 'FAST_FORWARD', 'MERGE_COMMIT', 'SQUASH'
  
  -- Timestamps
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  MergedAt        TEXT,
  ClosedAt        TEXT
);

CREATE INDEX idx_merge_pipeline ON MergeRequest(PipelineId);
CREATE INDEX idx_merge_status ON MergeRequest(Status);
```

---

## TypeScript Interfaces

### Version Types

```typescript
interface PipelineVersion {
  readonly id: string;
  readonly pipelineId: string;
  readonly versionNumber: number;
  readonly versionTag: string | null;
  readonly snapshotData: PipelineSnapshot;
  readonly message: string | null;
  readonly author: string | null;
  readonly parentVersionId: string | null;
  readonly changeSummary: ChangeSummary;
  readonly createdAt: Date;
}

interface PipelineSnapshot {
  readonly pipeline: PipelineData;
  readonly blocks: readonly BlockData[];
  readonly stages: readonly StageData[];
  readonly connections: readonly ConnectionData[];
  readonly variables: readonly VariableData[];
  readonly validationScripts: readonly ScriptData[];
}

interface ChangeSummary {
  readonly blocksAdded: number;
  readonly blocksModified: number;
  readonly blocksRemoved: number;
  readonly stagesAdded: number;
  readonly stagesModified: number;
  readonly stagesRemoved: number;
  readonly connectionsAdded: number;
  readonly connectionsRemoved: number;
  readonly totalChanges: number;
}

interface PipelineBranch {
  readonly id: string;
  readonly pipelineId: string;
  readonly name: string;
  readonly description: string | null;
  readonly headVersionId: string | null;
  readonly baseVersionId: string | null;
  readonly isDefault: boolean;
  readonly isProtected: boolean;
  readonly isMerged: boolean;
  readonly mergedIntoId: string | null;
  readonly createdAt: Date;
  readonly updatedAt: Date;
  readonly mergedAt: Date | null;
}

interface VersionChange {
  readonly id: string;
  readonly versionId: string;
  readonly changeType: ChangeType;
  readonly entityType: EntityType;
  readonly entityId: string;
  readonly entityName: string | null;
  readonly previousValue: unknown | null;
  readonly newValue: unknown | null;
  readonly diffData: DiffData | null;
}

enum ChangeType {
  ADD = 'ADD',
  MODIFY = 'MODIFY',
  DELETE = 'DELETE',
  MOVE = 'MOVE'
}

enum EntityType {
  BLOCK = 'BLOCK',
  STAGE = 'STAGE',
  CONNECTION = 'CONNECTION',
  VARIABLE = 'VARIABLE',
  SCRIPT = 'SCRIPT'
}
```

### Merge Types

```typescript
interface MergeRequest {
  readonly id: string;
  readonly pipelineId: string;
  readonly sourceBranchId: string;
  readonly targetBranchId: string;
  readonly status: MergeStatus;
  readonly title: string;
  readonly description: string | null;
  readonly author: string | null;
  readonly hasConflicts: boolean;
  readonly conflictData: readonly MergeConflict[] | null;
  readonly mergeVersionId: string | null;
  readonly mergeStrategy: MergeStrategy | null;
  readonly createdAt: Date;
  readonly updatedAt: Date;
  readonly mergedAt: Date | null;
}

enum MergeStatus {
  OPEN = 'OPEN',
  MERGED = 'MERGED',
  CLOSED = 'CLOSED',
  CONFLICTED = 'CONFLICTED'
}

enum MergeStrategy {
  FAST_FORWARD = 'FAST_FORWARD',
  MERGE_COMMIT = 'MERGE_COMMIT',
  SQUASH = 'SQUASH'
}

interface MergeConflict {
  readonly entityType: EntityType;
  readonly entityId: string;
  readonly entityName: string;
  readonly conflictType: ConflictType;
  readonly sourceValue: unknown;
  readonly targetValue: unknown;
  readonly baseValue: unknown | null;       // Common ancestor
  readonly resolution: ConflictResolution | null;
}

enum ConflictType {
  BOTH_MODIFIED = 'BOTH_MODIFIED',
  MODIFY_DELETE = 'MODIFY_DELETE',
  DELETE_MODIFY = 'DELETE_MODIFY',
  ADD_ADD = 'ADD_ADD'
}

interface ConflictResolution {
  readonly resolvedValue: unknown;
  readonly strategy: 'USE_SOURCE' | 'USE_TARGET' | 'MANUAL' | 'MERGED';
  readonly resolvedBy: string;
  readonly resolvedAt: Date;
}
```

---

## Version Control Engine

### VersionController

```typescript
interface VersionController {
  // Versioning
  createVersion(
    pipelineId: string,
    message: string,
    options?: CreateVersionOptions
  ): Promise<PipelineVersion>;
  
  getVersionHistory(
    pipelineId: string,
    options?: HistoryOptions
  ): Promise<readonly PipelineVersion[]>;
  
  getVersion(versionId: string): Promise<PipelineVersion>;
  
  restoreVersion(versionId: string): Promise<Pipeline>;
  
  // Branching
  createBranch(
    pipelineId: string,
    name: string,
    options?: CreateBranchOptions
  ): Promise<PipelineBranch>;
  
  switchBranch(pipelineId: string, branchId: string): Promise<void>;
  
  deleteBranch(branchId: string): Promise<void>;
  
  // Comparison
  compare(
    fromVersionId: string,
    toVersionId: string
  ): Promise<VersionComparison>;
  
  // Merging
  createMergeRequest(
    sourceBranchId: string,
    targetBranchId: string,
    options: MergeRequestOptions
  ): Promise<MergeRequest>;
  
  executeMerge(
    mergeRequestId: string,
    strategy: MergeStrategy,
    resolutions?: readonly ConflictResolution[]
  ): Promise<MergeResult>;
}

interface CreateVersionOptions {
  readonly versionTag?: string;
  readonly branchId?: string;
}

interface CreateBranchOptions {
  readonly fromVersionId?: string;          // Default: current head
  readonly description?: string;
}

interface HistoryOptions {
  readonly branchId?: string;
  readonly limit?: number;
  readonly offset?: number;
  readonly includeChanges?: boolean;
}

interface MergeRequestOptions {
  readonly title: string;
  readonly description?: string;
}

interface MergeResult {
  readonly success: boolean;
  readonly mergedVersion: PipelineVersion | null;
  readonly conflicts: readonly MergeConflict[];
}
```

### DiffEngine

```typescript
interface DiffEngine {
  // Compute differences
  computeDiff(
    oldSnapshot: PipelineSnapshot,
    newSnapshot: PipelineSnapshot
  ): VersionDiff;
  
  // Three-way diff for merging
  computeThreeWayDiff(
    base: PipelineSnapshot,
    source: PipelineSnapshot,
    target: PipelineSnapshot
  ): ThreeWayDiff;
  
  // Visual diff data
  generateVisualDiff(diff: VersionDiff): VisualDiff;
}

interface VersionDiff {
  readonly changes: readonly VersionChange[];
  readonly statistics: DiffStatistics;
}

interface DiffStatistics {
  readonly additions: number;
  readonly modifications: number;
  readonly deletions: number;
  readonly moves: number;
}

interface ThreeWayDiff {
  readonly autoMergeable: readonly AutoMerge[];
  readonly conflicts: readonly MergeConflict[];
  readonly unchanged: readonly string[];
}

interface AutoMerge {
  readonly entityType: EntityType;
  readonly entityId: string;
  readonly mergedValue: unknown;
  readonly source: 'BASE' | 'SOURCE' | 'TARGET';
}

interface VisualDiff {
  readonly blocks: readonly BlockDiff[];
  readonly connections: readonly ConnectionDiff[];
  readonly canvasChanges: CanvasDiff;
}

interface BlockDiff {
  readonly blockId: string;
  readonly changeType: ChangeType;
  readonly stageDiffs: readonly StageDiff[];
  readonly positionChange?: PositionChange;
}

interface StageDiff {
  readonly stageId: string;
  readonly changeType: ChangeType;
  readonly configChanges: readonly ConfigChange[];
}

interface ConfigChange {
  readonly path: string;
  readonly oldValue: unknown;
  readonly newValue: unknown;
}
```

---

## React Components

### VersionHistoryPanel

```typescript
interface VersionHistoryPanelProps {
  readonly pipelineId: string;
  readonly currentVersionId?: string;
  readonly onVersionSelect: (version: PipelineVersion) => void;
  readonly onRestore: (version: PipelineVersion) => void;
}

const VersionHistoryPanel: React.FC<VersionHistoryPanelProps> = ({
  pipelineId,
  currentVersionId,
  onVersionSelect,
  onRestore
}) => {
  const [selectedBranch, setSelectedBranch] = useState<string | null>(null);
  
  // Query branches
  const { data: branches } = useQuery({
    queryKey: ['pipeline-branches', pipelineId],
    queryFn: () => fetchBranches(pipelineId)
  });
  
  // Query version history
  const { data: versions } = useQuery({
    queryKey: ['version-history', pipelineId, selectedBranch],
    queryFn: () => fetchVersionHistory(pipelineId, { branchId: selectedBranch })
  });
  
  return (
    <div className="flex flex-col h-full">
      {/* Branch selector */}
      <div className="p-3 border-b">
        <Select value={selectedBranch ?? ''} onValueChange={setSelectedBranch}>
          <SelectTrigger>
            <SelectValue placeholder="All branches" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">All branches</SelectItem>
            {branches?.map(branch => (
              <SelectItem key={branch.id} value={branch.id}>
                <div className="flex items-center gap-2">
                  <GitBranch className="h-3 w-3" />
                  {branch.name}
                  {branch.isDefault && (
                    <Badge variant="secondary" className="text-xs">default</Badge>
                  )}
                </div>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      
      {/* Version timeline */}
      <ScrollArea className="flex-1">
        <div className="p-3 space-y-2">
          {versions?.map((version, index) => (
            <VersionCard
              key={version.id}
              version={version}
              isCurrentt={version.id === currentVersionId}
              isLatest={index === 0}
              onSelect={() => onVersionSelect(version)}
              onRestore={() => onRestore(version)}
            />
          ))}
        </div>
      </ScrollArea>
    </div>
  );
};
```

### VersionCard

```typescript
interface VersionCardProps {
  readonly version: PipelineVersion;
  readonly isCurrent: boolean;
  readonly isLatest: boolean;
  readonly onSelect: () => void;
  readonly onRestore: () => void;
}

const VersionCard: React.FC<VersionCardProps> = ({
  version,
  isCurrent,
  isLatest,
  onSelect,
  onRestore
}) => {
  return (
    <Card
      className={cn(
        "cursor-pointer transition-colors",
        isCurrent && "border-primary bg-primary/5"
      )}
      onClick={onSelect}
    >
      <CardContent className="p-3">
        <div className="flex items-start justify-between">
          <div className="flex-1 min-w-0">
            {/* Version identifier */}
            <div className="flex items-center gap-2">
              <span className="font-mono text-sm font-medium">
                v{version.versionNumber}
              </span>
              {version.versionTag && (
                <Badge variant="outline" className="text-xs">
                  {version.versionTag}
                </Badge>
              )}
              {isLatest && (
                <Badge className="text-xs">Latest</Badge>
              )}
            </div>
            
            {/* Message */}
            {version.message && (
              <p className="text-sm text-muted-foreground mt-1 truncate">
                {version.message}
              </p>
            )}
            
            {/* Change summary */}
            <div className="flex items-center gap-3 mt-2 text-xs text-muted-foreground">
              {version.changeSummary.blocksAdded > 0 && (
                <span className="text-success">
                  +{version.changeSummary.blocksAdded} blocks
                </span>
              )}
              {version.changeSummary.blocksRemoved > 0 && (
                <span className="text-destructive">
                  -{version.changeSummary.blocksRemoved} blocks
                </span>
              )}
              {version.changeSummary.stagesModified > 0 && (
                <span className="text-warning">
                  ~{version.changeSummary.stagesModified} stages
                </span>
              )}
            </div>
            
            {/* Timestamp */}
            <div className="flex items-center gap-2 mt-2 text-xs text-muted-foreground">
              <Clock className="h-3 w-3" />
              {formatRelativeTime(version.createdAt)}
              {version.author && (
                <>
                  <span>•</span>
                  {version.author}
                </>
              )}
            </div>
          </div>
          
          {/* Actions */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild onClick={(e) => e.stopPropagation()}>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={onSelect}>
                <Eye className="h-4 w-4 mr-2" />
                View Changes
              </DropdownMenuItem>
              <DropdownMenuItem onClick={onRestore}>
                <RotateCcw className="h-4 w-4 mr-2" />
                Restore
              </DropdownMenuItem>
              <DropdownMenuItem>
                <Copy className="h-4 w-4 mr-2" />
                Create Branch
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardContent>
    </Card>
  );
};
```

### BranchManager

```typescript
interface BranchManagerProps {
  readonly pipelineId: string;
  readonly currentBranchId: string;
  readonly onBranchSwitch: (branchId: string) => void;
}

const BranchManager: React.FC<BranchManagerProps> = ({
  pipelineId,
  currentBranchId,
  onBranchSwitch
}) => {
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [isMergeOpen, setIsMergeOpen] = useState(false);
  
  // Query branches
  const { data: branches } = useQuery({
    queryKey: ['pipeline-branches', pipelineId],
    queryFn: () => fetchBranches(pipelineId)
  });
  
  const currentBranch = branches?.find(b => b.id === currentBranchId);
  
  return (
    <div className="flex items-center gap-2">
      {/* Current branch indicator */}
      <Popover>
        <PopoverTrigger asChild>
          <Button variant="outline" className="gap-2">
            <GitBranch className="h-4 w-4" />
            {currentBranch?.name ?? 'main'}
            <ChevronDown className="h-4 w-4 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-64 p-0" align="start">
          <Command>
            <CommandInput placeholder="Search branches..." />
            <CommandList>
              <CommandEmpty>No branches found.</CommandEmpty>
              <CommandGroup>
                {branches?.map(branch => (
                  <CommandItem
                    key={branch.id}
                    value={branch.name}
                    onSelect={() => onBranchSwitch(branch.id)}
                  >
                    <div className="flex items-center gap-2 flex-1">
                      <GitBranch className="h-4 w-4" />
                      <span className="flex-1">{branch.name}</span>
                      {branch.id === currentBranchId && (
                        <Check className="h-4 w-4" />
                      )}
                      {branch.isDefault && (
                        <Badge variant="secondary" className="text-xs">
                          default
                        </Badge>
                      )}
                    </div>
                  </CommandItem>
                ))}
              </CommandGroup>
            </CommandList>
          </Command>
          <Separator />
          <div className="p-2">
            <Button
              variant="ghost"
              className="w-full justify-start gap-2"
              onClick={() => setIsCreateOpen(true)}
            >
              <Plus className="h-4 w-4" />
              Create Branch
            </Button>
          </div>
        </PopoverContent>
      </Popover>
      
      {/* Merge button */}
      {currentBranch && !currentBranch.isDefault && (
        <Button
          variant="outline"
          size="sm"
          onClick={() => setIsMergeOpen(true)}
        >
          <GitMerge className="h-4 w-4 mr-2" />
          Merge
        </Button>
      )}
      
      {/* Create branch dialog */}
      <CreateBranchDialog
        pipelineId={pipelineId}
        open={isCreateOpen}
        onOpenChange={setIsCreateOpen}
        onCreated={(branch) => {
          onBranchSwitch(branch.id);
          setIsCreateOpen(false);
        }}
      />
      
      {/* Merge dialog */}
      <MergeDialog
        pipelineId={pipelineId}
        sourceBranchId={currentBranchId}
        open={isMergeOpen}
        onOpenChange={setIsMergeOpen}
      />
    </div>
  );
};
```

### VisualDiffViewer

```typescript
interface VisualDiffViewerProps {
  readonly fromVersion: PipelineVersion;
  readonly toVersion: PipelineVersion;
}

const VisualDiffViewer: React.FC<VisualDiffViewerProps> = ({
  fromVersion,
  toVersion
}) => {
  const [viewMode, setViewMode] = useState<'split' | 'unified'>('split');
  
  // Compute diff
  const { data: diff } = useQuery({
    queryKey: ['version-diff', fromVersion.id, toVersion.id],
    queryFn: () => computeVersionDiff(fromVersion.id, toVersion.id)
  });
  
  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between p-3 border-b">
        <div className="flex items-center gap-4 text-sm">
          <span className="text-muted-foreground">
            Comparing v{fromVersion.versionNumber} → v{toVersion.versionNumber}
          </span>
          {diff && (
            <div className="flex items-center gap-3">
              <span className="text-success">+{diff.statistics.additions}</span>
              <span className="text-destructive">-{diff.statistics.deletions}</span>
              <span className="text-warning">~{diff.statistics.modifications}</span>
            </div>
          )}
        </div>
        
        <Tabs value={viewMode} onValueChange={(v) => setViewMode(v as 'split' | 'unified')}>
          <TabsList className="h-8">
            <TabsTrigger value="split" className="text-xs">Split</TabsTrigger>
            <TabsTrigger value="unified" className="text-xs">Unified</TabsTrigger>
          </TabsList>
        </Tabs>
      </div>
      
      {/* Diff content */}
      <div className="flex-1 overflow-hidden">
        {viewMode === 'split' ? (
          <SplitDiffView
            fromSnapshot={fromVersion.snapshotData}
            toSnapshot={toVersion.snapshotData}
            diff={diff}
          />
        ) : (
          <UnifiedDiffView
            fromSnapshot={fromVersion.snapshotData}
            toSnapshot={toVersion.snapshotData}
            diff={diff}
          />
        )}
      </div>
    </div>
  );
};
```

---

## Auto-Versioning

### AutoVersionConfig

```typescript
interface AutoVersionConfig {
  readonly enabled: boolean;
  readonly triggers: readonly AutoVersionTrigger[];
  readonly maxVersions: number;           // Max versions to keep
  readonly pruneStrategy: PruneStrategy;
}

enum AutoVersionTrigger {
  ON_SAVE = 'ON_SAVE',                    // Create version on every save
  ON_BLOCK_CHANGE = 'ON_BLOCK_CHANGE',    // When blocks added/removed
  ON_EXECUTION = 'ON_EXECUTION',          // Before each execution
  INTERVAL = 'INTERVAL'                   // Time-based (e.g., hourly)
}

enum PruneStrategy {
  KEEP_TAGGED = 'KEEP_TAGGED',            // Keep all tagged versions
  KEEP_DAILY = 'KEEP_DAILY',              // Keep one per day
  KEEP_MILESTONES = 'KEEP_MILESTONES'     // Keep significant changes only
}
```

### Version Pruning

```typescript
interface VersionPruner {
  // Identify versions to prune
  identifyPrunableVersions(
    pipelineId: string,
    config: AutoVersionConfig
  ): Promise<readonly PipelineVersion[]>;
  
  // Execute pruning
  pruneVersions(versionIds: readonly string[]): Promise<PruneResult>;
  
  // Compact version history (squash minor versions)
  compactHistory(
    pipelineId: string,
    options: CompactOptions
  ): Promise<CompactResult>;
}

interface CompactOptions {
  readonly keepTagged: boolean;
  readonly keepInterval: 'HOURLY' | 'DAILY' | 'WEEKLY';
  readonly dryRun: boolean;
}

interface CompactResult {
  readonly originalCount: number;
  readonly newCount: number;
  readonly removedVersions: readonly string[];
}
```

---

## API Endpoints

```typescript
// Versions
GET    /api/pipelines/:id/versions           // List version history
POST   /api/pipelines/:id/versions           // Create new version
GET    /api/versions/:id                     // Get version details
POST   /api/versions/:id/restore             // Restore version
GET    /api/versions/:from/diff/:to          // Compare versions

// Branches
GET    /api/pipelines/:id/branches           // List branches
POST   /api/pipelines/:id/branches           // Create branch
PUT    /api/branches/:id                     // Update branch
DELETE /api/branches/:id                     // Delete branch
POST   /api/branches/:id/switch              // Switch to branch

// Merge requests
GET    /api/pipelines/:id/merge-requests     // List merge requests
POST   /api/merge-requests                   // Create merge request
GET    /api/merge-requests/:id               // Get merge request details
POST   /api/merge-requests/:id/merge         // Execute merge
POST   /api/merge-requests/:id/close         // Close merge request
PUT    /api/merge-requests/:id/conflicts     // Resolve conflicts

// Maintenance
POST   /api/pipelines/:id/versions/prune     // Prune old versions
POST   /api/pipelines/:id/versions/compact   // Compact history
```

---

## See Also

- [Pipeline Templates](./19-pipeline-templates.md) — Template system
- [Import Export](./20-import-export.md) — Import/export functionality
- [Database Schema](./01-database-schema.md) — Core schema
- [React Flow Canvas](./10-react-flow-canvas.md) — Undo/redo integration
