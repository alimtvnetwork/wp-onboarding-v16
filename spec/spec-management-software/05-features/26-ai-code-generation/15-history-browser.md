# 15. History Browser

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [Overview](./00-overview.md)

---

## Purpose

Define the UI for browsing task execution history, viewing operation details, and analyzing past code generation activities.

---

## UI Layout

### History List View

```
┌─────────────────────────────────────────────────────────────────┐
│  📜 Execution History                                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  🔍 Search tasks...                          [Filter ▼] [Sort ▼]│
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                                                              ││
│  │  ✅ lowercase-filenames                          2 min ago  ││
│  │  Renamed 12 files in spec/ directory                        ││
│  │  [rename] [multi-file] [markdown]                           ││
│  │  Duration: 3.2s  │  Files: 12  │  Success: 100%             ││
│  │  ─────────────────────────────────────────────────────────  ││
│  │                                                              ││
│  │  ✅ update-cross-references                     15 min ago  ││
│  │  Updated links in 8 overview files                          ││
│  │  [update] [cross-reference] [index-management]              ││
│  │  Duration: 5.1s  │  Files: 8   │  Success: 100%             ││
│  │  ─────────────────────────────────────────────────────────  ││
│  │                                                              ││
│  │  ❌ batch-format-json                           1 hour ago  ││
│  │  Format all JSON files (failed)                             ││
│  │  [update] [json] [batch-operation]                          ││
│  │  Duration: 1.2s  │  Files: 3/15  │  Error: permission denied││
│  │  ─────────────────────────────────────────────────────────  ││
│  │                                                              ││
│  │  ✅ generate-overview-files                     2 hours ago ││
│  │  Created 00-overview.md for 5 new features                  ││
│  │  [create] [markdown] [index-management]                     ││
│  │  Duration: 8.3s  │  Files: 5   │  Success: 100%             ││
│  │                                                              ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  Showing 4 of 28 tasks                    [Load More]           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Task Detail View

```
┌─────────────────────────────────────────────────────────────────┐
│  📋 Task: lowercase-filenames                            [×]    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  OVERVIEW                                                 │  │
│  ├───────────────────────────────────────────────────────────┤  │
│  │  Status:     ✅ Success                                   │  │
│  │  Created:    2026-01-29 10:28:00                          │  │
│  │  Executed:   2026-01-29 10:30:00                          │  │
│  │  Duration:   3.2 seconds                                  │  │
│  │  Approved by: user@example.com                            │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  [Overview] [Code] [Operations] [Logs]                           │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                                                  │
│  📁 Operations (12)                                              │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  # │ Operation │ Old Path            │ New Path            ││
│  │ ───┼───────────┼─────────────────────┼──────────────────── ││
│  │  1 │ RENAME    │ README.md           │ readme.md           ││
│  │  2 │ RENAME    │ CHANGELOG.md        │ changelog.md        ││
│  │  3 │ RENAME    │ 00-Overview.md      │ 00-overview.md      ││
│  │  4 │ RENAME    │ 01-Authentication.md│ 01-authentication.md││
│  │  5 │ RENAME    │ 02-FileManagement.md│ 02-file-management.md│
│  │  6 │ RENAME    │ 03-Project.md       │ 03-project.md       ││
│  │ ...│ ...       │ ...                 │ ...                 ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  📊 Statistics                                                   │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  Total Operations: 12                                       ││
│  │  Successful: 12 (100%)                                      ││
│  │  Failed: 0 (0%)                                             ││
│  │                                                              ││
│  │  By Type:                                                    ││
│  │    • Rename: 12                                             ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  [View Code]  [Download Log]  [Reuse Task]           [Close]    │
└─────────────────────────────────────────────────────────────────┘
```

### Operation Detail

```
┌─────────────────────────────────────────────────────────────────┐
│  📄 Operation Details                                    [×]    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Operation:    RENAME                                            │
│  Timestamp:    2026-01-29 10:30:01.234                          │
│  Status:       ✅ Success                                        │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  📂 Paths                                                        │
│  Old: ./spec/05-features/README.md                               │
│  New: ./spec/05-features/readme.md                               │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  🔐 Checksums (SHA-256)                                          │
│  Before: a1b2c3d4e5f6...                                        │
│  After:  a1b2c3d4e5f6... (unchanged - rename only)              │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  📏 File Size                                                    │
│  Size: 2,456 bytes                                               │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                        [Close]   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Components

### History List Component

```tsx
interface HistoryListProps {
  readonly onSelectTask: (taskId: number) => void;
}

export function HistoryList({ onSelectTask }: HistoryListProps): JSX.Element {
  const [tasks, setTasks] = useState<TempCodingTask[]>([]);
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState<HistoryFilter>({
    status: "all",
    dateRange: "all",
  });
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);

  useEffect(() => {
    loadTasks();
  }, [search, filter, page]);

  const loadTasks = async (): Promise<void> => {
    const result = await fetchTasks({
      search,
      filter,
      page,
      limit: 10,
    });
    
    if (page === 1) {
      setTasks(result.tasks);
    } else {
      setTasks((prev) => [...prev, ...result.tasks]);
    }
    
    setHasMore(result.hasMore);
  };

  return (
    <div className="space-y-4">
      {/* Search and filters */}
      <div className="flex gap-2">
        <Input
          placeholder="Search tasks..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="flex-1"
        />
        <FilterDropdown filter={filter} onChange={setFilter} />
        <SortDropdown />
      </div>

      {/* Task list */}
      <div className="space-y-2">
        {tasks.map((task) => (
          <TaskCard
            key={task.id}
            task={task}
            onClick={() => onSelectTask(task.id)}
          />
        ))}
      </div>

      {/* Load more */}
      {hasMore && (
        <Button
          variant="outline"
          className="w-full"
          onClick={() => setPage((p) => p + 1)}
        >
          Load More
        </Button>
      )}
    </div>
  );
}
```

### Task Card Component

```tsx
interface TaskCardProps {
  readonly task: TempCodingTask;
  readonly onClick: () => void;
}

export function TaskCard({ task, onClick }: TaskCardProps): JSX.Element {
  const successRate = task.executionCount > 0
    ? (task.successCount / task.executionCount) * 100
    : 0;

  return (
    <Card
      className="cursor-pointer hover:bg-accent transition-colors"
      onClick={onClick}
    >
      <CardContent className="p-4">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-2">
            <StatusIcon status={task.lastExecutedAt ? "success" : "pending"} />
            <span className="font-medium">{task.taskName}</span>
          </div>
          <span className="text-sm text-muted-foreground">
            {task.lastExecutedAt
              ? formatDistanceToNow(task.lastExecutedAt, { addSuffix: true })
              : "Never executed"}
          </span>
        </div>

        <p className="text-sm text-muted-foreground mt-1">
          {task.description || "No description"}
        </p>

        {/* Tags */}
        <div className="flex flex-wrap gap-1 mt-2">
          {task.tags?.slice(0, 5).map((tag) => (
            <Badge key={tag.tagName} variant="secondary" className="text-xs">
              {tag.tagName}
            </Badge>
          ))}
          {task.tags && task.tags.length > 5 && (
            <Badge variant="outline" className="text-xs">
              +{task.tags.length - 5}
            </Badge>
          )}
        </div>

        {/* Stats */}
        <div className="flex gap-4 mt-3 text-sm text-muted-foreground">
          <span>Duration: {formatDuration(task.avgDurationMs)}</span>
          <span>Executions: {task.executionCount}</span>
          <span>Success: {successRate.toFixed(0)}%</span>
        </div>
      </CardContent>
    </Card>
  );
}
```

### Operations Table Component

```tsx
interface OperationsTableProps {
  readonly operations: readonly FilesystemHistoryEntry[];
  readonly onSelectOperation: (op: FilesystemHistoryEntry) => void;
}

export function OperationsTable({
  operations,
  onSelectOperation,
}: OperationsTableProps): JSX.Element {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-12">#</TableHead>
          <TableHead>Operation</TableHead>
          <TableHead>Old Path</TableHead>
          <TableHead>New Path</TableHead>
          <TableHead>Status</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {operations.map((op, index) => (
          <TableRow
            key={op.id}
            className="cursor-pointer hover:bg-accent"
            onClick={() => onSelectOperation(op)}
          >
            <TableCell>{index + 1}</TableCell>
            <TableCell>
              <Badge variant={getOperationVariant(op.operationType)}>
                {op.operationType.toUpperCase()}
              </Badge>
            </TableCell>
            <TableCell className="font-mono text-sm">
              {op.oldPath || "-"}
            </TableCell>
            <TableCell className="font-mono text-sm">
              {op.newPath || "-"}
            </TableCell>
            <TableCell>
              {op.isSuccess ? (
                <Check className="h-4 w-4 text-green-500" />
              ) : (
                <X className="h-4 w-4 text-red-500" />
              )}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}
```

---

## Filters

```typescript
interface HistoryFilter {
  readonly status: "all" | "success" | "failed" | "pending";
  readonly dateRange: "all" | "today" | "week" | "month";
  readonly tags?: readonly string[];
  readonly search?: string;
}

enum SortOption {
  Newest = "newest",
  Oldest = "oldest",
  MostUsed = "most_used",
  HighestSuccess = "highest_success",
}
```

---

## API Endpoints

### GET /api/v1/code-generation/history

```typescript
interface HistoryRequest {
  readonly page: number;
  readonly limit: number;
  readonly filter: HistoryFilter;
  readonly sort: SortOption;
}

interface HistoryResponse {
  readonly tasks: readonly TempCodingTask[];
  readonly total: number;
  readonly page: number;
  readonly hasMore: boolean;
}
```

### GET /api/v1/code-generation/tasks/{taskId}/operations

```typescript
interface OperationsResponse {
  readonly operations: readonly FilesystemHistoryEntry[];
  readonly summary: OperationsSummary;
}

interface OperationsSummary {
  readonly total: number;
  readonly successful: number;
  readonly failed: number;
  readonly byType: Record<OperationType, number>;
}
```

---

## Export Options

### Download Formats

| Format | Description |
|--------|-------------|
| JSON | Complete task data with operations |
| CSV | Tabular operation list |
| Markdown | Human-readable report |
| Code | Original Golang source |

```tsx
function ExportDropdown({ taskId }: { taskId: number }): JSX.Element {
  const exportFormats = [
    { label: "JSON", value: "json", icon: FileJson },
    { label: "CSV", value: "csv", icon: FileSpreadsheet },
    { label: "Markdown", value: "md", icon: FileText },
    { label: "Go Source", value: "go", icon: FileCode },
  ];

  const handleExport = async (format: string): Promise<void> => {
    const response = await fetch(
      `/api/v1/code-generation/tasks/${taskId}/export?format=${format}`
    );
    const blob = await response.blob();
    downloadBlob(blob, `task-${taskId}.${format}`);
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline">
          <Download className="h-4 w-4 mr-2" />
          Export
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent>
        {exportFormats.map(({ label, value, icon: Icon }) => (
          <DropdownMenuItem key={value} onClick={() => handleExport(value)}>
            <Icon className="h-4 w-4 mr-2" />
            {label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
```

---

## Related Specs

- [08-history-logger.md](./08-history-logger.md) — Backend logging
- [11-database-schema.md](./11-database-schema.md) — Data storage
- [13-code-review-ui.md](./13-code-review-ui.md) — Review interface
