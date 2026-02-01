# Live Execution View

**Version:** 1.0.0  
**Status:** Draft  
**Created:** 2026-01-30  
**Updated:** 2026-01-30  

---

## Overview

The Live Execution View provides real-time visualization of pipeline execution, displaying active stages, data flow, and progress indicators.

**Cross-References:**
- [React Flow Canvas](./10-react-flow-canvas.md)
- [Stage Executor](./04-stage-executor.md)
- [Telemetry Integration](./18-telemetry-integration.md)

---

## 1. Execution View Architecture

### 1.1 Component Structure

```typescript
interface LiveExecutionViewProps {
  readonly executionId: string;
  readonly pipelineId: string;
  readonly mode: ViewMode;
  readonly onPause: () => void;
  readonly onResume: () => void;
  readonly onCancel: () => void;
  readonly onStepInto: (stageId: string) => void;
}

enum ViewMode {
  OVERVIEW = 'OVERVIEW',           // Full pipeline view
  FOCUSED = 'FOCUSED',             // Current block + neighbors
  TIMELINE = 'TIMELINE',           // Chronological view
  DATA_FLOW = 'DATA_FLOW',         // Variable propagation
}

interface ExecutionViewState {
  readonly execution: PipelineExecution;
  readonly activeNodes: readonly string[];
  readonly completedNodes: readonly string[];
  readonly failedNodes: readonly string[];
  readonly pendingNodes: readonly string[];
  readonly dataSnapshots: Map<string, DataSnapshot>;
  readonly liveMetrics: ExecutionMetrics;
}
```

### 1.2 View Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ Execution: ord-12345  │ ▶ Running │ 3/8 Blocks │ 00:02:34     │
├─────────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │                                                             │ │
│ │                    Pipeline Canvas                          │ │
│ │              (with execution overlays)                      │ │
│ │                                                             │ │
│ │    [✓]──────►[✓]──────►[▶]──────►[ ]──────►[ ]            │ │
│ │                         ↑                                   │ │
│ │                    Current                                  │ │
│ │                                                             │ │
│ └─────────────────────────────────────────────────────────────┘ │
├───────────────────────────────┬─────────────────────────────────┤
│ Stage Output                  │ Execution Log                   │
│ ┌───────────────────────────┐ │ 10:23:01 [INFO] Started block 3 │
│ │ {                         │ │ 10:23:02 [INFO] API call sent   │
│ │   "users": [...],         │ │ 10:23:03 [WARN] Retry attempt 1 │
│ │   "count": 42             │ │ 10:23:05 [INFO] API succeeded   │
│ │ }                         │ │                                 │
│ └───────────────────────────┘ │                                 │
└───────────────────────────────┴─────────────────────────────────┘
```

---

## 2. Real-Time Updates

### 2.1 WebSocket Event Stream

```typescript
interface ExecutionEventStream {
  subscribe(executionId: string): void;
  unsubscribe(executionId: string): void;
  onEvent(handler: (event: ExecutionEvent) => void): void;
}

type ExecutionEvent =
  | StageStartedEvent
  | StageCompletedEvent
  | StageFailedEvent
  | BlockTransitionEvent
  | VariableUpdatedEvent
  | ProgressUpdatedEvent
  | LogEntryEvent
  | MetricsUpdatedEvent;

interface StageStartedEvent {
  readonly type: 'STAGE_STARTED';
  readonly executionId: string;
  readonly stageId: string;
  readonly blockId: string;
  readonly timestamp: Date;
  readonly inputData: Record<string, unknown>;
}

interface StageCompletedEvent {
  readonly type: 'STAGE_COMPLETED';
  readonly executionId: string;
  readonly stageId: string;
  readonly blockId: string;
  readonly timestamp: Date;
  readonly outputData: Record<string, unknown>;
  readonly durationMs: number;
}

interface StageFailedEvent {
  readonly type: 'STAGE_FAILED';
  readonly executionId: string;
  readonly stageId: string;
  readonly blockId: string;
  readonly timestamp: Date;
  readonly error: PipelineError;
  readonly willRetry: boolean;
}

interface BlockTransitionEvent {
  readonly type: 'BLOCK_TRANSITION';
  readonly executionId: string;
  readonly fromBlockId: string | null;
  readonly toBlockId: string;
  readonly transitionType: TransitionType;
  readonly timestamp: Date;
}

enum TransitionType {
  SEQUENTIAL = 'SEQUENTIAL',
  CONDITIONAL = 'CONDITIONAL',
  LOOP_ITERATION = 'LOOP_ITERATION',
  PARALLEL_BRANCH = 'PARALLEL_BRANCH',
  ERROR_HANDLER = 'ERROR_HANDLER',
  FALLBACK = 'FALLBACK',
}

interface VariableUpdatedEvent {
  readonly type: 'VARIABLE_UPDATED';
  readonly executionId: string;
  readonly variablePath: string;
  readonly previousValue: unknown;
  readonly newValue: unknown;
  readonly sourceStageId: string;
  readonly timestamp: Date;
}

interface ProgressUpdatedEvent {
  readonly type: 'PROGRESS_UPDATED';
  readonly executionId: string;
  readonly completedStages: number;
  readonly totalStages: number;
  readonly completedBlocks: number;
  readonly totalBlocks: number;
  readonly estimatedRemainingMs: number;
}

interface LogEntryEvent {
  readonly type: 'LOG_ENTRY';
  readonly executionId: string;
  readonly level: LogLevel;
  readonly message: string;
  readonly sourceStageId: string | null;
  readonly metadata: Record<string, unknown>;
  readonly timestamp: Date;
}

interface MetricsUpdatedEvent {
  readonly type: 'METRICS_UPDATED';
  readonly executionId: string;
  readonly metrics: ExecutionMetrics;
  readonly timestamp: Date;
}
```

### 2.2 Event Hook

```typescript
interface UseExecutionStreamOptions {
  readonly executionId: string;
  readonly autoReconnect: boolean;
  readonly bufferSize: number;
  readonly throttleMs: number;
}

interface UseExecutionStreamResult {
  readonly isConnected: boolean;
  readonly events: readonly ExecutionEvent[];
  readonly latestState: ExecutionViewState;
  readonly error: Error | null;
  readonly reconnect: () => void;
}

function useExecutionStream(
  options: UseExecutionStreamOptions
): UseExecutionStreamResult;

// Usage
const { isConnected, latestState, events } = useExecutionStream({
  executionId: 'exec-123',
  autoReconnect: true,
  bufferSize: 1000,
  throttleMs: 100,
});
```

---

## 3. Node Execution Overlays

### 3.1 Overlay States

```typescript
enum NodeExecutionStatus {
  PENDING = 'PENDING',
  QUEUED = 'QUEUED',
  RUNNING = 'RUNNING',
  PAUSED = 'PAUSED',
  SUCCESS = 'SUCCESS',
  FAILED = 'FAILED',
  SKIPPED = 'SKIPPED',
  RETRYING = 'RETRYING',
}

interface NodeExecutionOverlay {
  readonly nodeId: string;
  readonly status: NodeExecutionStatus;
  readonly progress: number;              // 0-1 for stages with progress
  readonly duration: number;
  readonly attempt: number;
  readonly error: PipelineError | null;
}
```

### 3.2 Visual Styling

| Status | Border | Background | Animation | Icon |
|--------|--------|------------|-----------|------|
| `PENDING` | `--border` | `--muted/10` | None | Circle |
| `QUEUED` | `--muted-foreground` | `--muted/20` | None | Clock |
| `RUNNING` | `--accent` | `--accent/10` | Pulse | Loader |
| `PAUSED` | `--warning` | `--warning/10` | None | Pause |
| `SUCCESS` | `--success` | `--success/10` | None | CheckCircle |
| `FAILED` | `--destructive` | `--destructive/10` | None | XCircle |
| `SKIPPED` | `--muted` | `--muted/5` | None | SkipForward |
| `RETRYING` | `--warning` | `--warning/10` | Spin | RefreshCw |

### 3.3 Overlay Component

```typescript
interface ExecutionOverlayProps {
  readonly overlay: NodeExecutionOverlay;
  readonly showDetails: boolean;
  readonly onInspect: () => void;
}

// Overlay renders on top of base node
// Shows: status icon, duration badge, progress ring (if applicable)
// Hover: expanded details tooltip
// Click: open in Debug Inspector
```

---

## 4. Data Flow Visualization

### 4.1 Data Flow Animation

```typescript
interface DataFlowConfig {
  readonly enabled: boolean;
  readonly speed: FlowSpeed;
  readonly showValues: boolean;
  readonly highlightChanges: boolean;
}

enum FlowSpeed {
  SLOW = 0.5,
  NORMAL = 1,
  FAST = 2,
  INSTANT = 0,
}

interface DataFlowParticle {
  readonly id: string;
  readonly edgeId: string;
  readonly data: unknown;
  readonly progress: number;        // 0-1 along edge
  readonly highlighted: boolean;
}
```

### 4.2 Edge Animation

```
Source Node                              Target Node
    │                                        │
    │    ○───○───○──────────────────►        │
    │         ↑                              │
    │    Animated particles                  │
    │    representing data flow              │
    │                                        │
```

### 4.3 Data Preview on Edges

```typescript
interface EdgeDataPreviewProps {
  readonly edgeId: string;
  readonly data: unknown;
  readonly position: Position;
  readonly maxPreviewLength: number;
}

// Hovering over edge shows data being transferred
// Truncated with "..." for large payloads
// Click to expand in modal
```

---

## 5. Progress Indicators

### 5.1 Global Progress Bar

```typescript
interface GlobalProgressProps {
  readonly completedBlocks: number;
  readonly totalBlocks: number;
  readonly completedStages: number;
  readonly totalStages: number;
  readonly elapsedMs: number;
  readonly estimatedRemainingMs: number;
}

// Visual:
// ████████████░░░░░░░░░░░░  45% Complete
// 4/9 Blocks │ 12/27 Stages │ ETA: 2m 34s
```

### 5.2 Block Progress

```typescript
interface BlockProgressProps {
  readonly blockId: string;
  readonly completedStages: number;
  readonly totalStages: number;
  readonly status: NodeExecutionStatus;
}

// Mini progress ring around block node
// Shows stage completion within block
```

### 5.3 Loop Progress

```typescript
interface LoopProgressProps {
  readonly loopId: string;
  readonly currentIteration: number;
  readonly totalIterations: number | null;
  readonly successCount: number;
  readonly failureCount: number;
}

// Iteration counter with success/failure breakdown
// ⟳ 45/100 │ ✓ 43 │ ✗ 2
```

---

## 6. Execution Controls

### 6.1 Control Panel

```typescript
interface ExecutionControlsProps {
  readonly execution: PipelineExecution;
  readonly onPause: () => void;
  readonly onResume: () => void;
  readonly onCancel: () => void;
  readonly onRestart: () => void;
  readonly onStepMode: (enabled: boolean) => void;
}

interface ExecutionControlState {
  readonly canPause: boolean;
  readonly canResume: boolean;
  readonly canCancel: boolean;
  readonly canRestart: boolean;
  readonly isStepMode: boolean;
}
```

### 6.2 Control Actions

| Action | Icon | Hotkey | Availability |
|--------|------|--------|--------------|
| Pause | Pause | `Space` | Running |
| Resume | Play | `Space` | Paused |
| Cancel | Square | `Esc` | Running, Paused |
| Restart | RotateCcw | `Ctrl+R` | Completed, Failed |
| Step | StepForward | `F10` | Step Mode |
| Step Into | ArrowDownRight | `F11` | Step Mode |
| Step Out | ArrowUpRight | `Shift+F11` | Step Mode |

### 6.3 Step Mode Execution

```typescript
interface StepModeController {
  enable(): void;
  disable(): void;
  stepNext(): Promise<void>;
  stepInto(): Promise<void>;
  stepOut(): Promise<void>;
  runToBreakpoint(): Promise<void>;
}

interface Breakpoint {
  readonly id: string;
  readonly stageId: string;
  readonly condition: ConditionExpression | null;
  readonly enabled: boolean;
  readonly hitCount: number;
}
```

---

## 7. Execution Timeline

### 7.1 Timeline View

```typescript
interface TimelineViewProps {
  readonly execution: PipelineExecution;
  readonly events: readonly ExecutionEvent[];
  readonly zoomLevel: number;
  readonly focusedEventId: string | null;
}

interface TimelineEntry {
  readonly id: string;
  readonly type: TimelineEntryType;
  readonly startTime: Date;
  readonly endTime: Date | null;
  readonly duration: number;
  readonly stageId: string | null;
  readonly blockId: string | null;
  readonly status: NodeExecutionStatus;
  readonly details: Record<string, unknown>;
}

enum TimelineEntryType {
  STAGE_EXECUTION = 'STAGE_EXECUTION',
  BLOCK_EXECUTION = 'BLOCK_EXECUTION',
  WAIT_DELAY = 'WAIT_DELAY',
  RETRY_DELAY = 'RETRY_DELAY',
  ERROR_HANDLING = 'ERROR_HANDLING',
  USER_INTERACTION = 'USER_INTERACTION',
}
```

### 7.2 Timeline Visualization

```
Time ─────────────────────────────────────────────────────────────►
     0s        5s        10s       15s       20s       25s

     ┌──────────┐
     │ Block 1  │
     └──────────┘
                 ┌──────────────────────┐
                 │      Block 2         │
                 │  ┌────┐ ┌────┐ ┌───┐ │
                 │  │ S1 │ │ S2 │ │S3 │ │
                 │  └────┘ └────┘ └───┘ │
                 └──────────────────────┘
                                         ┌─────────┐
                                         │ Block 3 │ ◄─ Current
                                         └─────────┘
```

### 7.3 Parallel Execution View

```
Time ─────────────────────────────────────────────────────────────►

     ┌─────────────────────────────────┐
     │           Block 1               │
     └─────────────────────────────────┘
                │
     ┌──────────┴──────────┐
     │                     │
     ▼                     ▼
┌──────────┐        ┌──────────┐
│ Block 2a │        │ Block 2b │
└──────────┘        └──────────┘
     │                     │
     └──────────┬──────────┘
                │
                ▼
     ┌─────────────────────────────────┐
     │           Block 3               │
     └─────────────────────────────────┘
```

---

## 8. Live Log Panel

### 8.1 Log Configuration

```typescript
interface LogPanelConfig {
  readonly maxEntries: number;
  readonly autoScroll: boolean;
  readonly levelFilter: readonly LogLevel[];
  readonly sourceFilter: readonly string[];
  readonly searchQuery: string;
  readonly timestampFormat: TimestampFormat;
}

enum TimestampFormat {
  ABSOLUTE = 'ABSOLUTE',      // 10:23:45.123
  RELATIVE = 'RELATIVE',      // +2.5s
  ELAPSED = 'ELAPSED',        // 00:02:34
}
```

### 8.2 Log Entry Component

```typescript
interface LogEntryProps {
  readonly entry: LogEntry;
  readonly highlighted: boolean;
  readonly onSourceClick: (stageId: string) => void;
}

interface LogEntry {
  readonly id: string;
  readonly timestamp: Date;
  readonly level: LogLevel;
  readonly message: string;
  readonly sourceStageId: string | null;
  readonly sourceStageName: string | null;
  readonly metadata: Record<string, unknown>;
  readonly expandable: boolean;
}

// Visual:
// 10:23:45 [INFO ] fetch-users    Fetched 42 users successfully
// 10:23:46 [WARN ] validate-data  ▶ 3 records failed validation
//                                    └─ Click to expand details
```

### 8.3 Log Level Styling

| Level | Color | Icon |
|-------|-------|------|
| `DEBUG` | `--muted-foreground` | Bug |
| `INFO` | `--foreground` | Info |
| `WARN` | `--warning` | AlertTriangle |
| `ERROR` | `--destructive` | XCircle |

---

## 9. Output Inspector

### 9.1 Stage Output Panel

```typescript
interface OutputInspectorProps {
  readonly stageId: string;
  readonly output: unknown;
  readonly schema: JsonSchema | null;
  readonly viewMode: OutputViewMode;
  readonly onCopy: () => void;
  readonly onExport: () => void;
}

enum OutputViewMode {
  TREE = 'TREE',               // Expandable tree view
  JSON = 'JSON',               // Raw JSON with syntax highlighting
  TABLE = 'TABLE',             // For arrays of objects
  PREVIEW = 'PREVIEW',         // Rendered preview (HTML, Markdown)
}
```

### 9.2 Variable Diff View

```typescript
interface VariableDiffProps {
  readonly variablePath: string;
  readonly previousValue: unknown;
  readonly currentValue: unknown;
  readonly changeType: ChangeType;
}

enum ChangeType {
  ADDED = 'ADDED',
  MODIFIED = 'MODIFIED',
  REMOVED = 'REMOVED',
  UNCHANGED = 'UNCHANGED',
}

// Visual diff with highlighted changes
// Shows before/after for modified values
```

---

## 10. Execution History Snapshots

### 10.1 Snapshot System

```typescript
interface ExecutionSnapshot {
  readonly id: string;
  readonly executionId: string;
  readonly timestamp: Date;
  readonly stageId: string;
  readonly variables: Record<string, unknown>;
  readonly stageOutputs: Map<string, unknown>;
  readonly executionState: ExecutionViewState;
}

interface SnapshotNavigator {
  readonly snapshots: readonly ExecutionSnapshot[];
  readonly currentIndex: number;
  goToSnapshot(index: number): void;
  goToStage(stageId: string): void;
  compareSnapshots(indexA: number, indexB: number): SnapshotDiff;
}
```

### 10.2 Time Travel Debugging

```typescript
interface TimeTravelControls {
  readonly canGoBack: boolean;
  readonly canGoForward: boolean;
  readonly currentPosition: number;
  readonly totalPositions: number;
  goBack(): void;
  goForward(): void;
  goToPosition(position: number): void;
  replay(speed: number): void;
}

// Slider control to scrub through execution history
// View state at any point in time
```

---

## 11. Database Schema

### 11.1 Execution View Tables

```sql
-- Execution Events (stored in project.db)
CREATE TABLE ExecutionEvent (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  Type TEXT NOT NULL,
  StageId TEXT,
  BlockId TEXT,
  PayloadJson TEXT NOT NULL,
  Timestamp TEXT NOT NULL DEFAULT (datetime('now')),
  Sequence INTEGER NOT NULL
);

-- Execution Snapshots
CREATE TABLE ExecutionSnapshot (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  StageId TEXT NOT NULL,
  VariablesJson TEXT NOT NULL,
  StageOutputsJson TEXT NOT NULL,
  StateJson TEXT NOT NULL,
  Timestamp TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Execution Logs
CREATE TABLE ExecutionLog (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  Level TEXT NOT NULL,
  Message TEXT NOT NULL,
  SourceStageId TEXT,
  MetadataJson TEXT,
  Timestamp TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_event_execution ON ExecutionEvent(ExecutionId);
CREATE INDEX idx_event_timestamp ON ExecutionEvent(Timestamp);
CREATE INDEX idx_snapshot_execution ON ExecutionSnapshot(ExecutionId);
CREATE INDEX idx_log_execution ON ExecutionLog(ExecutionId);
CREATE INDEX idx_log_level ON ExecutionLog(Level);
```

---

## 12. Performance Optimization

### 12.1 Rendering Optimization

```typescript
interface RenderingConfig {
  readonly virtualization: boolean;         // For large pipelines
  readonly throttleUpdates: number;         // ms between renders
  readonly batchEvents: boolean;            // Batch rapid events
  readonly offscreenRendering: boolean;     // Render off-screen nodes
}

// Only render visible nodes + 1 level neighbors
// Batch WebSocket events every 100ms
// Use React.memo for node components
```

### 12.2 Event Buffering

```typescript
interface EventBuffer {
  add(event: ExecutionEvent): void;
  flush(): readonly ExecutionEvent[];
  setFlushInterval(ms: number): void;
  clear(): void;
}

// Buffer events during high-frequency updates
// Flush in batches for smooth rendering
```

---

## Related Specs

- [Debug Inspector](./17-debug-inspector.md)
- [Telemetry Integration](./18-telemetry-integration.md)
- [React Flow Canvas](./10-react-flow-canvas.md)
