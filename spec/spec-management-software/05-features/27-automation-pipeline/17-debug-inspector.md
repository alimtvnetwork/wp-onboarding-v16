# Debug Inspector

**Version:** 1.0.0  
**Status:** Draft  
**Created:** 2026-01-30  
**Updated:** 2026-01-30  

---

## Overview

The Debug Inspector provides deep inspection capabilities for pipeline execution, enabling variable exploration, breakpoint management, and error analysis.

**Cross-References:**
- [Live Execution View](./16-live-execution-view.md)
- [Stage Executor](./04-stage-executor.md)
- [Error Handlers](./15-error-handlers.md)

---

## 1. Inspector Architecture

### 1.1 Component Structure

```typescript
interface DebugInspectorProps {
  readonly executionId: string;
  readonly selectedStageId: string | null;
  readonly mode: InspectorMode;
  readonly onBreakpointToggle: (stageId: string) => void;
  readonly onWatchAdd: (expression: string) => void;
  readonly onStepAction: (action: StepAction) => void;
}

enum InspectorMode {
  VARIABLES = 'VARIABLES',
  BREAKPOINTS = 'BREAKPOINTS',
  CALL_STACK = 'CALL_STACK',
  WATCH = 'WATCH',
  ERRORS = 'ERRORS',
  PERFORMANCE = 'PERFORMANCE',
}

enum StepAction {
  CONTINUE = 'CONTINUE',
  STEP_OVER = 'STEP_OVER',
  STEP_INTO = 'STEP_INTO',
  STEP_OUT = 'STEP_OUT',
  RESTART = 'RESTART',
}
```

### 1.2 Inspector Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ Debug Inspector                                    [─] [□] [×] │
├─────────────────────────────────────────────────────────────────┤
│ ┌─────────┬─────────┬─────────┬─────────┬─────────┬───────────┐ │
│ │Variables│Breakpts │CallStack│  Watch  │ Errors  │Performance│ │
│ └─────────┴─────────┴─────────┴─────────┴─────────┴───────────┘ │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ▼ Local Variables                                              │
│    ├─ user: {id: "u-123", name: "John", ...}                   │
│    ├─ items: Array(5) [{...}, {...}, ...]                      │
│    └─ count: 42                                                 │
│                                                                 │
│  ▼ Block Variables                                              │
│    ├─ fetchUsers.output: {users: [...], total: 100}            │
│    └─ validateData.errors: []                                   │
│                                                                 │
│  ▼ Pipeline Context                                             │
│    ├─ execution.id: "exec-abc123"                              │
│    └─ execution.startedAt: "2026-01-30T10:23:00Z"              │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ ▶ Continue  ⏭ Step Over  ↓ Step Into  ↑ Step Out  ⟳ Restart   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Variable Inspector

### 2.1 Variable Tree

```typescript
interface VariableTreeProps {
  readonly variables: VariableNode[];
  readonly expandedPaths: Set<string>;
  readonly selectedPath: string | null;
  readonly onExpand: (path: string) => void;
  readonly onSelect: (path: string) => void;
  readonly onEdit: (path: string, value: unknown) => void;
  readonly onCopy: (path: string) => void;
}

interface VariableNode {
  readonly path: string;
  readonly name: string;
  readonly value: unknown;
  readonly type: VariableType;
  readonly children: readonly VariableNode[];
  readonly isExpandable: boolean;
  readonly isEditable: boolean;
  readonly metadata: VariableMetadata;
}

enum VariableType {
  STRING = 'STRING',
  NUMBER = 'NUMBER',
  BOOLEAN = 'BOOLEAN',
  NULL = 'NULL',
  UNDEFINED = 'UNDEFINED',
  OBJECT = 'OBJECT',
  ARRAY = 'ARRAY',
  DATE = 'DATE',
  FUNCTION = 'FUNCTION',
  SYMBOL = 'SYMBOL',
  BIGINT = 'BIGINT',
}

interface VariableMetadata {
  readonly source: VariableSource;
  readonly lastModified: Date | null;
  readonly modifiedBy: string | null;
  readonly size: number;                    // For strings/arrays
  readonly truncated: boolean;
}

enum VariableSource {
  INPUT = 'INPUT',
  OUTPUT = 'OUTPUT',
  LOOP = 'LOOP',
  CONTEXT = 'CONTEXT',
  COMPUTED = 'COMPUTED',
}
```

### 2.2 Variable Scopes

```typescript
interface VariableScope {
  readonly name: string;
  readonly type: ScopeType;
  readonly variables: readonly VariableNode[];
  readonly parentScope: VariableScope | null;
}

enum ScopeType {
  STAGE = 'STAGE',
  BLOCK = 'BLOCK',
  LOOP = 'LOOP',
  PIPELINE = 'PIPELINE',
  GLOBAL = 'GLOBAL',
}

// Scope hierarchy:
// Global → Pipeline → Block → Loop (if in loop) → Stage
```

### 2.3 Variable Display Formatting

| Type | Display Format | Example |
|------|----------------|---------|
| `STRING` | Quoted, truncated at 100 | `"Hello world..."` |
| `NUMBER` | Formatted with locale | `1,234.56` |
| `BOOLEAN` | Colored keyword | `true` / `false` |
| `NULL` | Italic keyword | *null* |
| `OBJECT` | `{...}` with property count | `Object {5}` |
| `ARRAY` | `[...]` with length | `Array(42)` |
| `DATE` | ISO format | `2026-01-30T10:23:00Z` |
| `FUNCTION` | Function signature | `ƒ validate(data)` |

### 2.4 Variable Editing

```typescript
interface VariableEditor {
  startEdit(path: string): void;
  validateValue(path: string, newValue: string): ValidationResult;
  commitEdit(path: string, newValue: unknown): Promise<void>;
  cancelEdit(): void;
}

interface ValidationResult {
  readonly valid: boolean;
  readonly parsedValue: unknown | null;
  readonly error: string | null;
  readonly typeCoercion: TypeCoercion | null;
}

interface TypeCoercion {
  readonly fromType: VariableType;
  readonly toType: VariableType;
  readonly warning: string | null;
}
```

---

## 3. Breakpoint Management

### 3.1 Breakpoint Types

```typescript
enum BreakpointType {
  STAGE = 'STAGE',                 // Break before stage executes
  STAGE_EXIT = 'STAGE_EXIT',       // Break after stage completes
  CONDITIONAL = 'CONDITIONAL',     // Break when condition is true
  ERROR = 'ERROR',                 // Break on any error
  DATA = 'DATA',                   // Break when variable changes
  LOG = 'LOG',                     // Log but don't pause
}

interface Breakpoint {
  readonly id: string;
  readonly type: BreakpointType;
  readonly stageId: string | null;
  readonly condition: ConditionExpression | null;
  readonly hitCondition: HitCondition | null;
  readonly logMessage: string | null;
  readonly enabled: boolean;
  readonly hitCount: number;
}

interface HitCondition {
  readonly type: HitConditionType;
  readonly value: number;
}

enum HitConditionType {
  EQUALS = 'EQUALS',               // Break when hitCount == value
  GREATER_THAN = 'GREATER_THAN',   // Break when hitCount > value
  MULTIPLE_OF = 'MULTIPLE_OF',     // Break when hitCount % value == 0
}
```

### 3.2 Breakpoint Configuration UI

```typescript
interface BreakpointConfigProps {
  readonly breakpoint: Breakpoint;
  readonly availableVariables: readonly VariableInfo[];
  readonly onSave: (breakpoint: Breakpoint) => void;
  readonly onDelete: () => void;
}

// UI allows:
// - Enable/disable toggle
// - Condition expression builder
// - Hit count configuration
// - Log message template with variable interpolation
```

### 3.3 Breakpoint List Panel

```typescript
interface BreakpointListProps {
  readonly breakpoints: readonly Breakpoint[];
  readonly activeBreakpointId: string | null;
  readonly onToggle: (id: string) => void;
  readonly onEdit: (id: string) => void;
  readonly onDelete: (id: string) => void;
  readonly onDeleteAll: () => void;
}

// Visual list with:
// [✓] fetch-users       (stage breakpoint)
// [✓] validate-data     when: errors.length > 0
// [ ] process-item      hit count: 10
// [✓] On Error          (error breakpoint)
```

---

## 4. Call Stack View

### 4.1 Stack Frame Definition

```typescript
interface StackFrame {
  readonly id: string;
  readonly type: FrameType;
  readonly name: string;
  readonly stageId: string | null;
  readonly blockId: string | null;
  readonly loopContext: LoopStackContext | null;
  readonly variables: readonly VariableNode[];
  readonly sourceLocation: SourceLocation | null;
}

enum FrameType {
  PIPELINE = 'PIPELINE',
  BLOCK = 'BLOCK',
  STAGE = 'STAGE',
  LOOP_ITERATION = 'LOOP_ITERATION',
  ERROR_HANDLER = 'ERROR_HANDLER',
  CALLBACK = 'CALLBACK',
}

interface LoopStackContext {
  readonly loopId: string;
  readonly iteration: number;
  readonly totalIterations: number | null;
  readonly itemValue: unknown;
}

interface SourceLocation {
  readonly stageType: string;
  readonly configPath: string;
}
```

### 4.2 Call Stack UI

```
┌─────────────────────────────────────────────────────────────────┐
│ Call Stack                                                      │
├─────────────────────────────────────────────────────────────────┤
│  ▶ validate-user              [STAGE]                          │
│    └─ process-users-loop      [LOOP] iteration 3/10            │
│       └─ process-block        [BLOCK]                          │
│          └─ main-pipeline     [PIPELINE]                        │
└─────────────────────────────────────────────────────────────────┘

Clicking a frame:
- Highlights corresponding node in canvas
- Shows variables in that scope
- Updates source location indicator
```

### 4.3 Stack Navigation

```typescript
interface StackNavigator {
  readonly frames: readonly StackFrame[];
  readonly selectedFrameIndex: number;
  selectFrame(index: number): void;
  navigateToFrame(frameId: string): void;
}
```

---

## 5. Watch Expressions

### 5.1 Watch Configuration

```typescript
interface WatchExpression {
  readonly id: string;
  readonly expression: string;
  readonly currentValue: unknown;
  readonly error: string | null;
  readonly lastEvaluated: Date;
  readonly evaluationCount: number;
}

interface WatchPanelProps {
  readonly watches: readonly WatchExpression[];
  readonly onAdd: (expression: string) => void;
  readonly onRemove: (id: string) => void;
  readonly onEdit: (id: string, expression: string) => void;
  readonly onRefresh: (id: string) => void;
}
```

### 5.2 Expression Language

```typescript
// Supported watch expressions:

// Simple variable access
"user.name"
"items[0].price"
"{{fetchUsers.output.count}}"

// Property access
"user?.profile?.avatar"

// Array operations
"items.length"
"items.filter(i => i.active).length"
"items.map(i => i.name)"

// Comparisons (returns boolean)
"user.role === 'admin'"
"items.length > 10"

// Aggregations
"items.reduce((sum, i) => sum + i.price, 0)"

// Type checks
"typeof user.id"
"Array.isArray(items)"
```

### 5.3 Watch UI

```
┌─────────────────────────────────────────────────────────────────┐
│ Watch                                                     [+]   │
├─────────────────────────────────────────────────────────────────┤
│  user.name                           "John Doe"                 │
│  items.length                        42                         │
│  items.filter(i => i.active).length  38                         │
│  totalPrice                          Error: undefined           │
│                                                                 │
│  [+ Add Expression...]                                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## 6. Error Analysis

### 6.1 Error Panel

```typescript
interface ErrorPanelProps {
  readonly errors: readonly PipelineError[];
  readonly selectedErrorId: string | null;
  readonly onSelect: (errorId: string) => void;
  readonly onNavigateToSource: (stageId: string) => void;
  readonly onRetry: (errorId: string) => void;
}

interface ErrorDetails {
  readonly error: PipelineError;
  readonly stackTrace: readonly StackTraceEntry[];
  readonly relatedVariables: readonly VariableNode[];
  readonly suggestedFixes: readonly SuggestedFix[];
  readonly similarErrors: readonly SimilarError[];
}

interface StackTraceEntry {
  readonly stageId: string;
  readonly stageName: string;
  readonly blockId: string;
  readonly blockName: string;
  readonly timestamp: Date;
}

interface SuggestedFix {
  readonly id: string;
  readonly description: string;
  readonly confidence: number;
  readonly action: FixAction;
}

enum FixAction {
  RETRY = 'RETRY',
  SKIP = 'SKIP',
  MODIFY_INPUT = 'MODIFY_INPUT',
  USE_FALLBACK = 'USE_FALLBACK',
  ESCALATE = 'ESCALATE',
}

interface SimilarError {
  readonly executionId: string;
  readonly timestamp: Date;
  readonly resolution: string | null;
}
```

### 6.2 Error Details View

```
┌─────────────────────────────────────────────────────────────────┐
│ Error Details                                                   │
├─────────────────────────────────────────────────────────────────┤
│ ✗ NETWORK_TIMEOUT                               [High Severity] │
│                                                                 │
│ Request to https://api.example.com/users timed out after 30s   │
│                                                                 │
│ ─────────────────────────────────────────────────────────────── │
│ Stack Trace:                                                    │
│   → fetch-users (http-request stage)                           │
│     → data-fetch-block                                          │
│       → main-pipeline                                           │
│                                                                 │
│ ─────────────────────────────────────────────────────────────── │
│ Related Variables:                                              │
│   request.url: "https://api.example.com/users"                 │
│   request.timeout: 30000                                        │
│   request.retryCount: 3                                         │
│                                                                 │
│ ─────────────────────────────────────────────────────────────── │
│ Suggested Fixes:                                                │
│   [Retry with Longer Timeout]  [Use Cached Data]  [Escalate]   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 7. Performance Profiler

### 7.1 Performance Metrics

```typescript
interface PerformanceMetrics {
  readonly stageId: string;
  readonly stageName: string;
  readonly executionCount: number;
  readonly totalDurationMs: number;
  readonly averageDurationMs: number;
  readonly minDurationMs: number;
  readonly maxDurationMs: number;
  readonly percentile95Ms: number;
  readonly cpuTime: number;
  readonly memoryUsed: number;
  readonly networkCalls: number;
  readonly networkBytes: number;
}

interface PerformanceProfilerProps {
  readonly executionId: string;
  readonly metrics: readonly PerformanceMetrics[];
  readonly sortBy: SortField;
  readonly onSort: (field: SortField) => void;
  readonly onStageClick: (stageId: string) => void;
}

enum SortField {
  NAME = 'NAME',
  TOTAL_DURATION = 'TOTAL_DURATION',
  AVERAGE_DURATION = 'AVERAGE_DURATION',
  EXECUTION_COUNT = 'EXECUTION_COUNT',
  MEMORY_USED = 'MEMORY_USED',
}
```

### 7.2 Performance Table

```
┌──────────────────┬───────┬──────────┬─────────┬────────┬────────┐
│ Stage            │ Count │ Total    │ Avg     │ P95    │ Memory │
├──────────────────┼───────┼──────────┼─────────┼────────┼────────┤
│ fetch-users      │ 1     │ 2,340ms  │ 2,340ms │ -      │ 12MB   │
│ validate-data    │ 100   │ 1,200ms  │ 12ms    │ 45ms   │ 2MB    │
│ transform-item   │ 100   │ 500ms    │ 5ms     │ 12ms   │ 1MB    │
│ save-to-db       │ 100   │ 3,400ms  │ 34ms    │ 120ms  │ 4MB    │
├──────────────────┼───────┼──────────┼─────────┼────────┼────────┤
│ TOTAL            │ 301   │ 7,440ms  │ -       │ -      │ 19MB   │
└──────────────────┴───────┴──────────┴─────────┴────────┴────────┘
```

### 7.3 Flame Graph

```typescript
interface FlameGraphProps {
  readonly executionId: string;
  readonly samples: readonly FlameGraphSample[];
  readonly zoomLevel: number;
  readonly focusedNodeId: string | null;
  readonly onNodeClick: (nodeId: string) => void;
  readonly onZoom: (level: number) => void;
}

interface FlameGraphSample {
  readonly id: string;
  readonly name: string;
  readonly parentId: string | null;
  readonly startTime: number;
  readonly duration: number;
  readonly type: FrameType;
  readonly color: string;
}

// Visual flame graph showing execution time distribution
// Wider bars = longer execution time
// Click to zoom into specific section
```

---

## 8. Data Comparison

### 8.1 Diff Viewer

```typescript
interface DiffViewerProps {
  readonly leftValue: unknown;
  readonly rightValue: unknown;
  readonly leftLabel: string;
  readonly rightLabel: string;
  readonly viewMode: DiffViewMode;
}

enum DiffViewMode {
  SIDE_BY_SIDE = 'SIDE_BY_SIDE',
  INLINE = 'INLINE',
  UNIFIED = 'UNIFIED',
}

interface DiffResult {
  readonly changes: readonly DiffChange[];
  readonly addedCount: number;
  readonly removedCount: number;
  readonly modifiedCount: number;
}

interface DiffChange {
  readonly path: string;
  readonly type: ChangeType;
  readonly leftValue: unknown;
  readonly rightValue: unknown;
}
```

### 8.2 Snapshot Comparison

```typescript
interface SnapshotComparisonProps {
  readonly snapshotA: ExecutionSnapshot;
  readonly snapshotB: ExecutionSnapshot;
  readonly onNavigateToDiff: (path: string) => void;
}

// Compare variables between two points in execution
// Highlight added/removed/modified values
```

---

## 9. Debug Commands

### 9.1 Command Palette

```typescript
interface DebugCommand {
  readonly id: string;
  readonly name: string;
  readonly shortcut: string;
  readonly category: CommandCategory;
  readonly action: () => void;
  readonly enabled: () => boolean;
}

enum CommandCategory {
  NAVIGATION = 'NAVIGATION',
  EXECUTION = 'EXECUTION',
  INSPECTION = 'INSPECTION',
  BREAKPOINTS = 'BREAKPOINTS',
}

const DEBUG_COMMANDS: readonly DebugCommand[] = [
  { id: 'continue', name: 'Continue', shortcut: 'F5', category: CommandCategory.EXECUTION, action: () => {}, enabled: () => true },
  { id: 'step-over', name: 'Step Over', shortcut: 'F10', category: CommandCategory.EXECUTION, action: () => {}, enabled: () => true },
  { id: 'step-into', name: 'Step Into', shortcut: 'F11', category: CommandCategory.EXECUTION, action: () => {}, enabled: () => true },
  { id: 'step-out', name: 'Step Out', shortcut: 'Shift+F11', category: CommandCategory.EXECUTION, action: () => {}, enabled: () => true },
  { id: 'restart', name: 'Restart', shortcut: 'Ctrl+Shift+F5', category: CommandCategory.EXECUTION, action: () => {}, enabled: () => true },
  { id: 'toggle-breakpoint', name: 'Toggle Breakpoint', shortcut: 'F9', category: CommandCategory.BREAKPOINTS, action: () => {}, enabled: () => true },
  { id: 'add-watch', name: 'Add to Watch', shortcut: 'Ctrl+Shift+W', category: CommandCategory.INSPECTION, action: () => {}, enabled: () => true },
  { id: 'go-to-stage', name: 'Go to Stage', shortcut: 'Ctrl+G', category: CommandCategory.NAVIGATION, action: () => {}, enabled: () => true },
];
```

### 9.2 Debug Console

```typescript
interface DebugConsoleProps {
  readonly executionId: string;
  readonly history: readonly ConsoleEntry[];
  readonly onExecute: (command: string) => void;
}

interface ConsoleEntry {
  readonly id: string;
  readonly type: ConsoleEntryType;
  readonly input: string;
  readonly output: unknown;
  readonly error: string | null;
  readonly timestamp: Date;
}

enum ConsoleEntryType {
  EXPRESSION = 'EXPRESSION',
  COMMAND = 'COMMAND',
  OUTPUT = 'OUTPUT',
  ERROR = 'ERROR',
}

// Interactive console for evaluating expressions
// > user.name
// ← "John Doe"
// > items.filter(i => i.price > 100)
// ← [{...}, {...}]
// > $retry("fetch-users")
// ← Retrying stage...
```

---

## 10. Database Schema

### 10.1 Debug Tables

```sql
-- Breakpoints (stored in project.db)
CREATE TABLE DebugBreakpoint (
  Id TEXT PRIMARY KEY,
  PipelineId TEXT NOT NULL REFERENCES Pipeline(Id),
  Type TEXT NOT NULL,
  StageId TEXT,
  ConditionJson TEXT,
  HitConditionJson TEXT,
  LogMessage TEXT,
  Enabled INTEGER NOT NULL DEFAULT 1,
  HitCount INTEGER NOT NULL DEFAULT 0,
  CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Watch Expressions
CREATE TABLE DebugWatch (
  Id TEXT PRIMARY KEY,
  PipelineId TEXT NOT NULL REFERENCES Pipeline(Id),
  Expression TEXT NOT NULL,
  SortOrder INTEGER NOT NULL DEFAULT 0,
  CreatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Debug Sessions
CREATE TABLE DebugSession (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  StartedAt TEXT NOT NULL DEFAULT (datetime('now')),
  EndedAt TEXT,
  BreakpointHits INTEGER NOT NULL DEFAULT 0,
  StepCount INTEGER NOT NULL DEFAULT 0,
  ConsoleEntriesJson TEXT DEFAULT '[]'
);

CREATE INDEX idx_breakpoint_pipeline ON DebugBreakpoint(PipelineId);
CREATE INDEX idx_watch_pipeline ON DebugWatch(PipelineId);
CREATE INDEX idx_session_execution ON DebugSession(ExecutionId);
```

---

## 11. Inspector Theming

### 11.1 Theme Variables

```css
/* Debug Inspector specific tokens */
:root {
  --inspector-bg: hsl(var(--card));
  --inspector-border: hsl(var(--border));
  --inspector-header: hsl(var(--muted));
  
  /* Variable types */
  --var-string: hsl(142 71% 45%);      /* Green */
  --var-number: hsl(221 83% 53%);      /* Blue */
  --var-boolean: hsl(280 65% 60%);     /* Purple */
  --var-null: hsl(var(--muted-foreground));
  --var-object: hsl(32 95% 44%);       /* Orange */
  --var-array: hsl(32 95% 44%);        /* Orange */
  
  /* Diff colors */
  --diff-added: hsl(142 71% 45% / 0.2);
  --diff-removed: hsl(0 84% 60% / 0.2);
  --diff-modified: hsl(45 93% 47% / 0.2);
}
```

---

## Related Specs

- [Live Execution View](./16-live-execution-view.md)
- [Telemetry Integration](./18-telemetry-integration.md)
- [Stage Executor](./04-stage-executor.md)
