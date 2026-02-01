# Loop Constructs

**Version:** 1.0.0  
**Status:** Draft  
**Created:** 2026-01-30  
**Updated:** 2026-01-30  

---

## Overview

Loop constructs enable iterative execution patterns within pipelines, supporting for-each, while, and do-until paradigms.

**Cross-References:**
- [Execution Blocks](./07-execution-blocks.md)
- [Conditional Nodes](./13-conditional-nodes.md)
- [Parallel Control](./08-parallel-control.md)

---

## 1. Loop Types

### 1.1 Type Definitions

```typescript
enum LoopType {
  FOR_EACH = 'FOR_EACH',
  FOR_COUNT = 'FOR_COUNT',
  WHILE = 'WHILE',
  DO_UNTIL = 'DO_UNTIL',
  INFINITE = 'INFINITE',        // Manual break required
}

interface LoopNode {
  readonly id: string;
  readonly type: LoopType;
  readonly config: LoopConfig;
  readonly body: readonly string[];     // Block IDs to execute
  readonly controls: LoopControls;
  readonly parallelization: LoopParallelConfig;
}

interface LoopConfig {
  readonly maxIterations: number;
  readonly timeoutMs: number;
  readonly continueOnError: boolean;
  readonly trackProgress: boolean;
}

interface LoopControls {
  readonly breakCondition: ConditionExpression | null;
  readonly continueCondition: ConditionExpression | null;
  readonly skipCondition: ConditionExpression | null;
}
```

### 1.2 Loop Type Behaviors

| Type | Evaluation | Iteration Source | Typical Use |
|------|------------|------------------|-------------|
| `FOR_EACH` | Pre-loop | Array/collection | Processing lists |
| `FOR_COUNT` | Pre-loop | Numeric range | Fixed iterations |
| `WHILE` | Pre-iteration | Condition | Conditional repeat |
| `DO_UNTIL` | Post-iteration | Condition | At-least-once |
| `INFINITE` | Never | Manual break | Event loops |

---

## 2. For-Each Loop

### 2.1 For-Each Configuration

```typescript
interface ForEachLoop extends LoopNode {
  readonly type: LoopType.FOR_EACH;
  readonly source: IterationSource;
  readonly itemVariable: string;
  readonly indexVariable: string;
  readonly batchConfig: BatchConfig | null;
}

interface IterationSource {
  readonly type: IterationSourceType;
  readonly reference: ValueReference;
  readonly filter: ConditionExpression | null;
  readonly transform: TransformOperation | null;
}

enum IterationSourceType {
  ARRAY = 'ARRAY',
  OBJECT_KEYS = 'OBJECT_KEYS',
  OBJECT_VALUES = 'OBJECT_VALUES',
  OBJECT_ENTRIES = 'OBJECT_ENTRIES',
  RANGE = 'RANGE',
  GENERATOR = 'GENERATOR',
}

interface BatchConfig {
  readonly batchSize: number;
  readonly batchVariable: string;
  readonly preserveOrder: boolean;
}

// Usage Example
const processUsers: ForEachLoop = {
  id: 'process-users-loop',
  type: LoopType.FOR_EACH,
  source: {
    type: IterationSourceType.ARRAY,
    reference: { type: 'VARIABLE', path: 'fetchUsers.output.users' },
    filter: {
      type: 'COMPARISON',
      left: { type: 'VARIABLE', path: 'item.status' },
      operator: ComparisonOperator.EQUALS,
      right: { type: 'LITERAL', path: 'active' },
    },
    transform: null,
  },
  itemVariable: 'user',
  indexVariable: 'userIndex',
  batchConfig: null,
  body: ['validate-user', 'enrich-user', 'save-user'],
  config: {
    maxIterations: 1000,
    timeoutMs: 300000,
    continueOnError: true,
    trackProgress: true,
  },
  controls: {
    breakCondition: null,
    continueCondition: null,
    skipCondition: {
      type: 'COMPARISON',
      left: { type: 'VARIABLE', path: 'user.processedAt' },
      operator: ComparisonOperator.NOT_EQUALS,
      right: { type: 'LITERAL', path: null },
    },
  },
  parallelization: { enabled: false, maxConcurrent: 1 },
};
```

### 2.2 For-Each Execution Flow

```
┌─────────────────────┐
│ Resolve Source      │
│ {{block.output}}    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Apply Filter        │
│ (if configured)     │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Apply Transform     │
│ (if configured)     │
└──────────┬──────────┘
           │
           ▼
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐ ┌─────────┐
│ Item 1  │ │ Item 2  │ ...
└────┬────┘ └────┬────┘
     │           │
     ▼           ▼
┌─────────────────────┐
│ Set Variables       │
│ {{user}}, {{index}} │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Check Skip          │
│ Condition           │
└──────────┬──────────┘
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐ ┌─────────┐
│  Skip   │ │ Execute │
│         │ │  Body   │
└─────────┘ └────┬────┘
                 │
                 ▼
┌─────────────────────┐
│ Check Break         │
│ Condition           │
└─────────────────────┘
```

---

## 3. For-Count Loop

### 3.1 For-Count Configuration

```typescript
interface ForCountLoop extends LoopNode {
  readonly type: LoopType.FOR_COUNT;
  readonly range: CountRange;
  readonly counterVariable: string;
}

interface CountRange {
  readonly start: ValueReference;
  readonly end: ValueReference;
  readonly step: ValueReference;
  readonly inclusive: boolean;
}

// Usage Example
const retryLoop: ForCountLoop = {
  id: 'retry-loop',
  type: LoopType.FOR_COUNT,
  range: {
    start: { type: 'LITERAL', path: '1' },
    end: { type: 'LITERAL', path: '5' },
    step: { type: 'LITERAL', path: '1' },
    inclusive: true,
  },
  counterVariable: 'attempt',
  body: ['make-request', 'check-response'],
  config: {
    maxIterations: 5,
    timeoutMs: 60000,
    continueOnError: false,
    trackProgress: true,
  },
  controls: {
    breakCondition: {
      type: 'COMPARISON',
      left: { type: 'VARIABLE', path: 'check-response.output.success' },
      operator: ComparisonOperator.EQUALS,
      right: { type: 'LITERAL', path: true },
    },
    continueCondition: null,
    skipCondition: null,
  },
  parallelization: { enabled: false, maxConcurrent: 1 },
};
```

---

## 4. While Loop

### 4.1 While Configuration

```typescript
interface WhileLoop extends LoopNode {
  readonly type: LoopType.WHILE;
  readonly condition: ConditionExpression;
  readonly evaluationTiming: EvaluationTiming;
}

enum EvaluationTiming {
  PRE_ITERATION = 'PRE_ITERATION',     // Standard while
  POST_ITERATION = 'POST_ITERATION',   // Do-while
}

// Usage Example
const pollLoop: WhileLoop = {
  id: 'poll-status-loop',
  type: LoopType.WHILE,
  condition: {
    type: 'COMPARISON',
    left: { type: 'VARIABLE', path: 'status' },
    operator: ComparisonOperator.NOT_EQUALS,
    right: { type: 'LITERAL', path: 'completed' },
  },
  evaluationTiming: EvaluationTiming.PRE_ITERATION,
  body: ['fetch-status', 'wait-delay'],
  config: {
    maxIterations: 100,
    timeoutMs: 600000,
    continueOnError: false,
    trackProgress: true,
  },
  controls: {
    breakCondition: {
      type: 'COMPARISON',
      left: { type: 'VARIABLE', path: 'status' },
      operator: ComparisonOperator.EQUALS,
      right: { type: 'LITERAL', path: 'failed' },
    },
    continueCondition: null,
    skipCondition: null,
  },
  parallelization: { enabled: false, maxConcurrent: 1 },
};
```

### 4.2 While Execution Flow

```
┌─────────────────────┐
│ Initialize Loop     │
│ iteration = 0       │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐◄────────────────┐
│ Evaluate Condition  │                 │
└──────────┬──────────┘                 │
     ┌─────┴─────┐                      │
     │           │                      │
     ▼           ▼                      │
┌─────────┐ ┌─────────┐                 │
│  false  │ │  true   │                 │
│  (exit) │ │         │                 │
└─────────┘ └────┬────┘                 │
                 │                      │
                 ▼                      │
┌─────────────────────┐                 │
│ Check Max Iterations│                 │
└──────────┬──────────┘                 │
     ┌─────┴─────┐                      │
     │           │                      │
     ▼           ▼                      │
┌─────────┐ ┌─────────┐                 │
│ Exceeded│ │   OK    │                 │
│  (exit) │ │         │                 │
└─────────┘ └────┬────┘                 │
                 │                      │
                 ▼                      │
┌─────────────────────┐                 │
│ Execute Body        │                 │
└──────────┬──────────┘                 │
           │                            │
           ▼                            │
┌─────────────────────┐                 │
│ Check Break         │                 │
└──────────┬──────────┘                 │
     ┌─────┴─────┐                      │
     │           │                      │
     ▼           ▼                      │
┌─────────┐ ┌─────────┐                 │
│  break  │ │continue │─────────────────┘
│  (exit) │ │         │
└─────────┘ └─────────┘
```

---

## 5. Do-Until Loop

### 5.1 Do-Until Configuration

```typescript
interface DoUntilLoop extends LoopNode {
  readonly type: LoopType.DO_UNTIL;
  readonly untilCondition: ConditionExpression;
}

// Executes body at least once, then checks condition
const waitForApproval: DoUntilLoop = {
  id: 'wait-approval-loop',
  type: LoopType.DO_UNTIL,
  untilCondition: {
    type: 'LOGICAL',
    operator: LogicalOperator.OR,
    operands: [
      {
        type: 'COMPARISON',
        left: { type: 'VARIABLE', path: 'approval.status' },
        operator: ComparisonOperator.EQUALS,
        right: { type: 'LITERAL', path: 'approved' },
      },
      {
        type: 'COMPARISON',
        left: { type: 'VARIABLE', path: 'approval.status' },
        operator: ComparisonOperator.EQUALS,
        right: { type: 'LITERAL', path: 'rejected' },
      },
    ],
  },
  body: ['check-approval', 'notify-pending', 'wait-interval'],
  config: {
    maxIterations: 288,    // 24 hours at 5-min intervals
    timeoutMs: 86400000,
    continueOnError: false,
    trackProgress: true,
  },
  controls: {
    breakCondition: null,
    continueCondition: null,
    skipCondition: null,
  },
  parallelization: { enabled: false, maxConcurrent: 1 },
};
```

---

## 6. Parallel Loop Execution

### 6.1 Parallel Configuration

```typescript
interface LoopParallelConfig {
  readonly enabled: boolean;
  readonly maxConcurrent: number;
  readonly strategy: ParallelStrategy;
  readonly errorHandling: ParallelErrorHandling;
  readonly resultAggregation: ResultAggregation;
}

enum ParallelStrategy {
  EAGER = 'EAGER',                 // Start all immediately
  THROTTLED = 'THROTTLED',         // Respect maxConcurrent
  ADAPTIVE = 'ADAPTIVE',           // Adjust based on success rate
}

enum ParallelErrorHandling {
  FAIL_FAST = 'FAIL_FAST',         // Stop all on first error
  CONTINUE = 'CONTINUE',           // Continue remaining
  COLLECT = 'COLLECT',             // Collect all errors, report at end
}

interface ResultAggregation {
  readonly mode: AggregationMode;
  readonly orderPreservation: boolean;
  readonly mergeStrategy: MergeStrategy;
}

enum AggregationMode {
  ARRAY = 'ARRAY',                 // Collect all results
  FIRST = 'FIRST',                 // Return first success
  REDUCE = 'REDUCE',               // Apply reducer function
  MAP = 'MAP',                     // Maintain input-output mapping
}
```

### 6.2 Parallel Execution Visualization

```
┌─────────────────────────────────────────────┐
│              Parallel For-Each              │
│         maxConcurrent: 3                    │
└─────────────────────────────────────────────┘
                      │
    ┌─────────────────┼─────────────────┐
    │                 │                 │
    ▼                 ▼                 ▼
┌─────────┐     ┌─────────┐     ┌─────────┐
│ Item 1  │     │ Item 2  │     │ Item 3  │
│ ██████░░│     │ ████░░░░│     │ █████░░░│
└─────────┘     └─────────┘     └─────────┘
    │                 │                 │
    ▼                 │                 │
┌─────────┐           │                 │
│ Item 4  │◄──────────┘                 │
│ ░░░░░░░░│                             │
└─────────┘                             │
    │                                   │
    ▼                                   ▼
┌─────────┐                       ┌─────────┐
│ Item 5  │◄──────────────────────│ Item 6  │
│ ░░░░░░░░│                       │ ░░░░░░░░│
└─────────┘                       └─────────┘
```

### 6.3 Parallel Executor

```typescript
interface ParallelLoopExecutor {
  execute(
    loop: LoopNode,
    items: readonly unknown[],
    context: ExecutionContext
  ): Promise<ParallelExecutionResult>;
  
  pause(): void;
  resume(): void;
  cancel(): void;
}

interface ParallelExecutionResult {
  readonly results: readonly IterationResult[];
  readonly errors: readonly IterationError[];
  readonly statistics: ParallelStatistics;
}

interface ParallelStatistics {
  readonly totalItems: number;
  readonly completed: number;
  readonly failed: number;
  readonly skipped: number;
  readonly averageDurationMs: number;
  readonly peakConcurrency: number;
}
```

---

## 7. Loop State Management

### 7.1 Loop Context

```typescript
interface LoopContext {
  readonly loopId: string;
  readonly currentIteration: number;
  readonly totalIterations: number | null;   // null for while/infinite
  readonly startTime: Date;
  readonly iterationResults: readonly IterationResult[];
  readonly variables: LoopVariables;
}

interface LoopVariables {
  readonly item: unknown;              // Current item (for-each)
  readonly index: number;              // 0-based index
  readonly iteration: number;          // 1-based count
  readonly isFirst: boolean;
  readonly isLast: boolean;
  readonly remaining: number | null;
  readonly progress: number;           // 0-1
  readonly previousResult: unknown;
  readonly accumulatedResults: unknown[];
}

interface IterationResult {
  readonly iteration: number;
  readonly item: unknown;
  readonly output: Record<string, unknown>;
  readonly durationMs: number;
  readonly status: IterationStatus;
  readonly error: Error | null;
}

enum IterationStatus {
  SUCCESS = 'SUCCESS',
  FAILED = 'FAILED',
  SKIPPED = 'SKIPPED',
  ABORTED = 'ABORTED',
}
```

### 7.2 Loop Variable Injection

```typescript
interface LoopVariableInjector {
  inject(
    context: LoopContext,
    registry: VariableRegistry
  ): void;
  
  getAvailableVariables(
    loop: LoopNode
  ): readonly VariableDefinition[];
}

// Injected variables for for-each
const FOREACH_VARIABLES: readonly VariableDefinition[] = [
  { name: '{{loop.item}}', type: 'any', description: 'Current item' },
  { name: '{{loop.index}}', type: 'number', description: '0-based index' },
  { name: '{{loop.iteration}}', type: 'number', description: '1-based count' },
  { name: '{{loop.isFirst}}', type: 'boolean', description: 'First iteration' },
  { name: '{{loop.isLast}}', type: 'boolean', description: 'Last iteration' },
  { name: '{{loop.length}}', type: 'number', description: 'Total items' },
  { name: '{{loop.progress}}', type: 'number', description: 'Progress 0-1' },
  { name: '{{loop.previous}}', type: 'any', description: 'Previous result' },
  { name: '{{loop.accumulated}}', type: 'array', description: 'All results' },
];
```

---

## 8. Loop Control Statements

### 8.1 Control Actions

```typescript
enum LoopControlAction {
  BREAK = 'BREAK',
  CONTINUE = 'CONTINUE',
  SKIP = 'SKIP',
  RETRY = 'RETRY',
  PAUSE = 'PAUSE',
}

interface LoopControlNode {
  readonly id: string;
  readonly action: LoopControlAction;
  readonly condition: ConditionExpression | null;
  readonly targetLoopId: string | null;    // For nested loops
  readonly metadata: ControlMetadata;
}

interface ControlMetadata {
  readonly reason: string;
  readonly logLevel: LogLevel;
  readonly emitEvent: boolean;
}
```

### 8.2 Nested Loop Control

```typescript
interface NestedLoopController {
  break(loopId: string): void;
  breakAll(): void;
  continue(loopId: string): void;
  getLoopStack(): readonly LoopContext[];
}

// Example: Break outer loop from inner
const breakOuterCondition: LoopControlNode = {
  id: 'break-outer',
  action: LoopControlAction.BREAK,
  condition: {
    type: 'COMPARISON',
    left: { type: 'VARIABLE', path: 'criticalError' },
    operator: ComparisonOperator.EQUALS,
    right: { type: 'LITERAL', path: true },
  },
  targetLoopId: 'outer-loop',    // Named loop reference
  metadata: {
    reason: 'Critical error encountered',
    logLevel: LogLevel.ERROR,
    emitEvent: true,
  },
};
```

---

## 9. Visual Components

### 9.1 Loop Node Component

```typescript
interface LoopNodeProps {
  readonly node: LoopNode;
  readonly executionState: LoopExecutionState;
  readonly children: React.ReactNode;
  readonly onConfigEdit: (config: LoopConfig) => void;
  readonly onBodyEdit: (blockIds: readonly string[]) => void;
}

interface LoopExecutionState {
  readonly status: NodeExecutionStatus;
  readonly currentIteration: number;
  readonly totalIterations: number | null;
  readonly progress: number;
  readonly iterationResults: readonly IterationSummary[];
}

interface IterationSummary {
  readonly iteration: number;
  readonly status: IterationStatus;
  readonly durationMs: number;
}
```

### 9.2 Loop Progress Visualization

```typescript
interface LoopProgressProps {
  readonly current: number;
  readonly total: number | null;
  readonly successes: number;
  readonly failures: number;
  readonly skipped: number;
}

// Visual representation
// ████████████░░░░░░░░  60% (120/200)
// ✓ 110  ✗ 5  ⊘ 5
```

### 9.3 Loop Styling

| Loop State | Border Color | Animation |
|------------|--------------|-----------|
| Idle | `--border` | None |
| Running | `--accent` | Pulse |
| Paused | `--warning` | None |
| Completed | `--success` | None |
| Failed | `--destructive` | None |

---

## 10. Database Schema

### 10.1 Loop Tables

```sql
-- Loop Nodes
CREATE TABLE LoopNode (
  Id TEXT PRIMARY KEY,
  BlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id),
  Type TEXT NOT NULL CHECK (Type IN ('FOR_EACH', 'FOR_COUNT', 'WHILE', 'DO_UNTIL', 'INFINITE')),
  ConfigJson TEXT NOT NULL,
  ControlsJson TEXT NOT NULL,
  ParallelizationJson TEXT NOT NULL,
  BodyBlockIds TEXT NOT NULL,           -- JSON array
  CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- For-Each Sources
CREATE TABLE ForEachSource (
  Id TEXT PRIMARY KEY,
  LoopId TEXT NOT NULL REFERENCES LoopNode(Id),
  SourceType TEXT NOT NULL,
  ReferenceJson TEXT NOT NULL,
  FilterJson TEXT,
  TransformJson TEXT,
  ItemVariable TEXT NOT NULL DEFAULT 'item',
  IndexVariable TEXT NOT NULL DEFAULT 'index',
  BatchSize INTEGER
);

-- Loop Executions
CREATE TABLE LoopExecution (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  LoopId TEXT NOT NULL REFERENCES LoopNode(Id),
  TotalIterations INTEGER,
  CompletedIterations INTEGER NOT NULL DEFAULT 0,
  FailedIterations INTEGER NOT NULL DEFAULT 0,
  SkippedIterations INTEGER NOT NULL DEFAULT 0,
  Status TEXT NOT NULL DEFAULT 'PENDING',
  StartedAt TEXT,
  CompletedAt TEXT
);

-- Iteration Results
CREATE TABLE LoopIteration (
  Id TEXT PRIMARY KEY,
  LoopExecutionId TEXT NOT NULL REFERENCES LoopExecution(Id),
  Iteration INTEGER NOT NULL,
  ItemJson TEXT,
  OutputJson TEXT,
  Status TEXT NOT NULL,
  ErrorMessage TEXT,
  DurationMs INTEGER NOT NULL,
  ExecutedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_loop_iteration_execution ON LoopIteration(LoopExecutionId);
CREATE INDEX idx_loop_iteration_status ON LoopIteration(Status);
```

---

## 11. Performance Optimization

### 11.1 Optimization Strategies

```typescript
interface LoopOptimizer {
  analyze(loop: LoopNode): OptimizationRecommendation[];
  
  applyOptimization(
    loop: LoopNode,
    optimization: Optimization
  ): LoopNode;
}

enum Optimization {
  ENABLE_PARALLELIZATION = 'ENABLE_PARALLELIZATION',
  ADD_BATCHING = 'ADD_BATCHING',
  LAZY_EVALUATION = 'LAZY_EVALUATION',
  EARLY_TERMINATION = 'EARLY_TERMINATION',
  RESULT_STREAMING = 'RESULT_STREAMING',
  CHECKPOINT_ITERATIONS = 'CHECKPOINT_ITERATIONS',
}

interface OptimizationRecommendation {
  readonly optimization: Optimization;
  readonly reason: string;
  readonly estimatedImprovement: string;
  readonly tradeoffs: readonly string[];
}
```

### 11.2 Iteration Checkpointing

```typescript
interface IterationCheckpointer {
  checkpoint(
    loopId: string,
    iteration: number,
    state: IterationState
  ): Promise<void>;
  
  restore(loopId: string): Promise<IterationState | null>;
  
  getLastCheckpoint(loopId: string): Promise<number>;
}

interface IterationState {
  readonly completedIterations: number;
  readonly accumulatedResults: unknown[];
  readonly variableSnapshot: Record<string, unknown>;
  readonly timestamp: Date;
}
```

---

## Related Specs

- [Conditional Nodes](./13-conditional-nodes.md)
- [Error Handlers](./15-error-handlers.md)
- [Parallel Control](./08-parallel-control.md)
