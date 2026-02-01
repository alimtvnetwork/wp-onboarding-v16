# Component: Execution Blocks

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 2.0.0  
**Status:** Complete  
**Phase:** 3 - Block Orchestration  

---

## Summary

Execution blocks are containers that group related stages into logical units. Blocks provide scope isolation, error boundaries, parallel execution grouping, and serve as the primary unit for visual organization on the pipeline canvas.

---

## User Stories

- As a user, I want to group related stages into reusable blocks
- As a user, I want blocks to have their own error handling configuration
- As a user, I want to collapse/expand blocks in the visual editor
- As a user, I want to duplicate blocks with all their stages
- As a user, I want blocks to execute as atomic units

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Pipeline                                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    Block Manager                             ││
│  │  • Block lifecycle management                                ││
│  │  • Execution ordering                                        ││
│  │  • Parallel group coordination                               ││
│  └─────────────────────────────────────────────────────────────┘│
│                              │                                   │
│         ┌────────────────────┼────────────────────┐             │
│         ▼                    ▼                    ▼             │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐       │
│  │   Block A   │     │   Block B   │     │   Block C   │       │
│  │ ┌─────────┐ │     │ ┌─────────┐ │     │ ┌─────────┐ │       │
│  │ │ Stage 1 │ │     │ │ Stage 1 │ │     │ │ Stage 1 │ │       │
│  │ ├─────────┤ │     │ ├─────────┤ │     │ ├─────────┤ │       │
│  │ │ Stage 2 │ │     │ │ Stage 2 │ │     │ │ Stage 2 │ │       │
│  │ ├─────────┤ │     │ └─────────┘ │     │ ├─────────┤ │       │
│  │ │ Stage 3 │ │     │             │     │ │ Stage 3 │ │       │
│  │ └─────────┘ │     │             │     │ └─────────┘ │       │
│  └─────────────┘     └─────────────┘     └─────────────┘       │
│         │                    │                    │             │
│         └────────────────────┼────────────────────┘             │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                   Block Executor                          │   │
│  │  • Sequential stage execution within block                │   │
│  │  • Error handling and recovery                            │   │
│  │  • Checkpoint creation                                    │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Block Definition

### Database Schema (from 01-database-schema.md)

```typescript
interface ExecutionBlock {
  Id: string;                    // UUID
  PipelineId: string;            // Parent pipeline
  Name: string;                  // Display name
  Description?: string;          // Optional description
  ExecutionOrder: number;        // Sequential order (1-based)
  ParallelGroup?: number;        // Same value = parallel execution
  CanvasX?: number;              // Node X position
  CanvasY?: number;              // Node Y position
  CanvasWidth: number;           // Node width (default: 200)
  CanvasHeight: number;          // Node height (default: 150)
  IsCollapsed: boolean;          // UI collapse state
  CreatedAt: string;             // ISO timestamp
}
```

### Block Configuration

```typescript
interface BlockConfig {
  // Execution settings
  timeout?: number;              // Block-level timeout (ms)
  retryPolicy?: RetryPolicy;     // Block-level retry config
  
  // Error handling
  errorHandler?: ErrorHandlerConfig;
  continueOnStageFailure: boolean;
  
  // Resource limits
  maxParallelStages?: number;    // Limit concurrent stages
  memoryLimitMb?: number;        // Memory limit hint
  
  // Checkpoint settings
  createCheckpointOnStart: boolean;
  createCheckpointOnEnd: boolean;
  
  // Variable scope
  isolateVariables: boolean;     // Don't leak block variables
  exportVariables?: string[];    // Variables to export to pipeline scope
}

interface RetryPolicy {
  maxRetries: number;
  backoffBase: number;
  backoffMultiplier: number;
  retryableErrors: string[];
}

interface ErrorHandlerConfig {
  type: ErrorHandlerType;
  fallbackBlockId?: string;
  notifyOnError: boolean;
  customHandler?: string;        // Custom handler script ID
}

enum ErrorHandlerType {
  STOP = 'STOP',                 // Stop pipeline
  SKIP = 'SKIP',                 // Skip to next block
  RETRY = 'RETRY',               // Retry block
  FALLBACK = 'FALLBACK',         // Execute fallback block
  BRANCH = 'BRANCH',             // Conditional branch
}
```

---

## Block Types

### Standard Block

Default execution block with sequential stages.

```typescript
interface StandardBlock extends ExecutionBlock {
  blockType: 'STANDARD';
  stages: Stage[];
}
```

### Loop Block

Block that iterates over a collection.

```typescript
interface LoopBlock extends ExecutionBlock {
  blockType: 'LOOP';
  loopConfig: LoopConfig;
  bodyBlockId: string;           // Block to execute per iteration
}

interface LoopConfig {
  loopType: LoopType;
  sourceVariable?: string;       // FOR_EACH: array to iterate
  condition?: string;            // WHILE: continue condition
  count?: number;                // FOR_COUNT: iteration count
  iteratorVariable: string;      // Variable for current item
  indexVariable?: string;        // Variable for current index
  maxIterations: number;         // Safety limit (default: 100)
  parallel?: boolean;            // Execute iterations in parallel
  batchSize?: number;            // Parallel batch size
}

enum LoopType {
  FOR_EACH = 'FOR_EACH',
  WHILE = 'WHILE',
  FOR_COUNT = 'FOR_COUNT',
}
```

### Conditional Block

Block with branching logic.

```typescript
interface ConditionalBlock extends ExecutionBlock {
  blockType: 'CONDITIONAL';
  conditions: ConditionalBranch[];
  defaultBlockId?: string;       // Else branch
}

interface ConditionalBranch {
  id: string;
  condition: string;             // Expression to evaluate
  targetBlockId: string;         // Block to execute if true
  priority: number;              // Evaluation order
}
```

### Try-Catch Block

Block with error handling structure.

```typescript
interface TryCatchBlock extends ExecutionBlock {
  blockType: 'TRY_CATCH';
  tryBlockId: string;
  catchBlockId?: string;
  finallyBlockId?: string;
  catchErrors?: string[];        // Error types to catch
}
```

### Parallel Block

Container for parallel sub-blocks.

```typescript
interface ParallelBlock extends ExecutionBlock {
  blockType: 'PARALLEL';
  childBlockIds: string[];
  waitForAll: boolean;           // Wait for all or first
  failFast: boolean;             // Stop on first failure
  maxConcurrent?: number;        // Limit concurrency
}
```

---

## Block Manager

### Interface

```typescript
interface BlockManager {
  // CRUD operations
  create(pipelineId: string, block: CreateBlockRequest): Promise<ExecutionBlock>;
  update(blockId: string, updates: UpdateBlockRequest): Promise<ExecutionBlock>;
  delete(blockId: string): Promise<void>;
  get(blockId: string): Promise<ExecutionBlock>;
  list(pipelineId: string): Promise<ExecutionBlock[]>;
  
  // Stage management
  addStage(blockId: string, stage: CreateStageRequest): Promise<Stage>;
  removeStage(stageId: string): Promise<void>;
  reorderStages(blockId: string, stageIds: string[]): Promise<void>;
  
  // Block operations
  duplicate(blockId: string, newName?: string): Promise<ExecutionBlock>;
  move(blockId: string, newOrder: number, newParallelGroup?: number): Promise<void>;
  
  // Execution order
  getExecutionOrder(pipelineId: string): Promise<BlockExecutionPlan>;
  
  // Validation
  validate(blockId: string): Promise<BlockValidationResult>;
}

interface CreateBlockRequest {
  name: string;
  description?: string;
  executionOrder?: number;       // Auto-assign if not provided
  parallelGroup?: number;
  blockType?: BlockType;
  config?: BlockConfig;
  canvasPosition?: { x: number; y: number };
}

interface BlockExecutionPlan {
  phases: ExecutionPhase[];
  totalBlocks: number;
  estimatedDuration?: number;
}

interface ExecutionPhase {
  phaseIndex: number;
  blocks: ExecutionBlock[];
  isParallel: boolean;
  parallelGroup?: number;
}
```

### Implementation

```typescript
class BlockManagerImpl implements BlockManager {
  constructor(
    private readonly db: Database,
    private readonly stageManager: StageManager,
    private readonly validator: BlockValidator
  ) {}
  
  async create(
    pipelineId: string,
    request: CreateBlockRequest
  ): Promise<ExecutionBlock> {
    // 1. Determine execution order
    const order = request.executionOrder ?? 
      await this.getNextOrder(pipelineId);
    
    // 2. Generate canvas position if not provided
    const position = request.canvasPosition ?? 
      await this.calculateNextPosition(pipelineId);
    
    // 3. Create block
    const block: ExecutionBlock = {
      Id: crypto.randomUUID(),
      PipelineId: pipelineId,
      Name: request.name,
      Description: request.description,
      ExecutionOrder: order,
      ParallelGroup: request.parallelGroup,
      CanvasX: position.x,
      CanvasY: position.y,
      CanvasWidth: 200,
      CanvasHeight: 150,
      IsCollapsed: false,
      CreatedAt: new Date().toISOString(),
    };
    
    await this.db.insert('ExecutionBlock', block);
    
    // 4. Store config in separate table if provided
    if (request.config) {
      await this.saveBlockConfig(block.Id, request.config);
    }
    
    return block;
  }
  
  async getExecutionOrder(pipelineId: string): Promise<BlockExecutionPlan> {
    const blocks = await this.list(pipelineId);
    
    // Group by parallel group
    const groups = new Map<number | null, ExecutionBlock[]>();
    
    for (const block of blocks) {
      const group = block.ParallelGroup ?? null;
      if (!groups.has(group)) {
        groups.set(group, []);
      }
      groups.get(group)!.push(block);
    }
    
    // Build execution phases
    const phases: ExecutionPhase[] = [];
    let phaseIndex = 0;
    
    // Sequential blocks (no parallel group) each get their own phase
    const sequentialBlocks = groups.get(null) ?? [];
    sequentialBlocks.sort((a, b) => a.ExecutionOrder - b.ExecutionOrder);
    
    // Interleave sequential and parallel groups based on execution order
    const allItems: Array<{ order: number; blocks: ExecutionBlock[]; isParallel: boolean; group?: number }> = [];
    
    for (const block of sequentialBlocks) {
      allItems.push({
        order: block.ExecutionOrder,
        blocks: [block],
        isParallel: false,
      });
    }
    
    for (const [groupId, groupBlocks] of groups) {
      if (groupId !== null) {
        const minOrder = Math.min(...groupBlocks.map(b => b.ExecutionOrder));
        allItems.push({
          order: minOrder,
          blocks: groupBlocks,
          isParallel: true,
          group: groupId,
        });
      }
    }
    
    // Sort by execution order
    allItems.sort((a, b) => a.order - b.order);
    
    // Build phases
    for (const item of allItems) {
      phases.push({
        phaseIndex: phaseIndex++,
        blocks: item.blocks,
        isParallel: item.isParallel,
        parallelGroup: item.group,
      });
    }
    
    return {
      phases,
      totalBlocks: blocks.length,
    };
  }
  
  async duplicate(blockId: string, newName?: string): Promise<ExecutionBlock> {
    const original = await this.get(blockId);
    const stages = await this.stageManager.listByBlock(blockId);
    
    // Create new block
    const newBlock = await this.create(original.PipelineId, {
      name: newName ?? `${original.Name} (copy)`,
      description: original.Description,
      config: await this.getBlockConfig(blockId),
      canvasPosition: {
        x: (original.CanvasX ?? 0) + 50,
        y: (original.CanvasY ?? 0) + 50,
      },
    });
    
    // Duplicate all stages
    for (const stage of stages) {
      await this.stageManager.duplicate(stage.Id, newBlock.Id);
    }
    
    return newBlock;
  }
  
  private async calculateNextPosition(pipelineId: string): Promise<{ x: number; y: number }> {
    const blocks = await this.list(pipelineId);
    
    if (blocks.length === 0) {
      return { x: 100, y: 100 };
    }
    
    // Find rightmost block and position new one to the right
    const maxX = Math.max(...blocks.map(b => (b.CanvasX ?? 0) + (b.CanvasWidth ?? 200)));
    const avgY = blocks.reduce((sum, b) => sum + (b.CanvasY ?? 0), 0) / blocks.length;
    
    return { x: maxX + 100, y: avgY };
  }
}
```

---

## Block Executor

### Interface

```typescript
interface BlockExecutor {
  execute(
    block: ExecutionBlock,
    context: BlockExecutionContext
  ): Promise<BlockResult>;
  
  cancel(blockExecutionId: string): Promise<void>;
  
  pause(blockExecutionId: string): Promise<void>;
  
  resume(blockExecutionId: string): Promise<void>;
}

interface BlockExecutionContext {
  pipelineExecutionId: string;
  variables: VariableContext;
  eventEmitter: BlockEventEmitter;
  config: BlockConfig;
  previousBlockResult?: BlockResult;
}

interface BlockResult {
  blockId: string;
  status: BlockExecutionStatus;
  stageResults: StageResult[];
  outputVariables: Record<string, unknown>;
  metrics: BlockMetrics;
  error?: BlockError;
  checkpointId?: string;
}

enum BlockExecutionStatus {
  PENDING = 'PENDING',
  RUNNING = 'RUNNING',
  SUCCESS = 'SUCCESS',
  FAILED = 'FAILED',
  CANCELLED = 'CANCELLED',
  PAUSED = 'PAUSED',
  SKIPPED = 'SKIPPED',
}

interface BlockMetrics {
  startedAt: string;
  completedAt: string;
  durationMs: number;
  stagesTotal: number;
  stagesCompleted: number;
  stagesFailed: number;
  totalTokensUsed: number;
}
```

### Implementation

```typescript
class BlockExecutorImpl implements BlockExecutor {
  constructor(
    private readonly stageExecutor: StageExecutor,
    private readonly checkpointManager: CheckpointManager,
    private readonly variableRegistry: VariableRegistry
  ) {}
  
  async execute(
    block: ExecutionBlock,
    context: BlockExecutionContext
  ): Promise<BlockResult> {
    const startTime = Date.now();
    const stageResults: StageResult[] = [];
    let checkpointId: string | undefined;
    
    // 1. Create checkpoint if configured
    if (context.config.createCheckpointOnStart) {
      checkpointId = await this.checkpointManager.create({
        pipelineExecutionId: context.pipelineExecutionId,
        blockId: block.Id,
        type: CheckpointType.BLOCK_START,
        variables: context.variables.snapshot(),
      });
    }
    
    // 2. Emit started event
    context.eventEmitter.emit({
      type: BlockEventType.STARTED,
      blockId: block.Id,
      timestamp: new Date().toISOString(),
      data: { message: `Starting block: ${block.Name}` },
    });
    
    try {
      // 3. Get stages in order
      const stages = await this.getStagesInOrder(block.Id);
      
      // 4. Execute stages sequentially
      let previousResult: StageResult | undefined;
      
      for (let i = 0; i < stages.length; i++) {
        const stage = stages[i];
        
        // Skip disabled stages
        if (!stage.IsEnabled) {
          stageResults.push({
            stageId: stage.Id,
            status: StageExecutionStatus.SKIPPED,
            output: { type: OutputType.NONE },
            metrics: this.createSkippedMetrics(),
          });
          continue;
        }
        
        // Build stage context
        const stageContext: ExecutionContext = {
          pipelineExecutionId: context.pipelineExecutionId,
          blockId: block.Id,
          variables: context.variables,
          previousStageResult: previousResult,
          config: context.config,
          eventEmitter: context.eventEmitter.forStage(stage.Id),
        };
        
        // Execute stage
        const result = await this.stageExecutor.execute(stage, stageContext);
        stageResults.push(result);
        previousResult = result;
        
        // Register output variable
        if (stage.OutputVariable && result.status === StageExecutionStatus.SUCCESS) {
          context.variables.set(
            `${this.normalizeBlockName(block.Name)}.${this.normalizeStageName(stage.Name)}.output`,
            result.output.text ?? result.output.json
          );
        }
        
        // Handle failure
        if (result.status === StageExecutionStatus.FAILED) {
          if (!context.config.continueOnStageFailure) {
            throw new BlockExecutionError({
              message: `Stage ${stage.Name} failed`,
              stageId: stage.Id,
              stageError: result.error,
            });
          }
        }
        
        // Emit progress
        context.eventEmitter.emit({
          type: BlockEventType.PROGRESS,
          blockId: block.Id,
          timestamp: new Date().toISOString(),
          data: {
            progress: ((i + 1) / stages.length) * 100,
            currentStage: stage.Name,
            completedStages: i + 1,
            totalStages: stages.length,
          },
        });
      }
      
      // 5. Create end checkpoint if configured
      if (context.config.createCheckpointOnEnd) {
        await this.checkpointManager.create({
          pipelineExecutionId: context.pipelineExecutionId,
          blockId: block.Id,
          type: CheckpointType.BLOCK_END,
          variables: context.variables.snapshot(),
        });
      }
      
      // 6. Build result
      const metrics = this.buildMetrics(startTime, stageResults);
      
      context.eventEmitter.emit({
        type: BlockEventType.COMPLETED,
        blockId: block.Id,
        timestamp: new Date().toISOString(),
        data: { metrics },
      });
      
      return {
        blockId: block.Id,
        status: BlockExecutionStatus.SUCCESS,
        stageResults,
        outputVariables: this.extractOutputVariables(context.variables, block),
        metrics,
        checkpointId,
      };
      
    } catch (error) {
      const blockError = this.toBlockError(error);
      
      context.eventEmitter.emit({
        type: BlockEventType.FAILED,
        blockId: block.Id,
        timestamp: new Date().toISOString(),
        data: { error: blockError },
      });
      
      // Handle error based on config
      return await this.handleBlockError(
        block,
        context,
        stageResults,
        blockError,
        startTime,
        checkpointId
      );
    }
  }
  
  private async handleBlockError(
    block: ExecutionBlock,
    context: BlockExecutionContext,
    stageResults: StageResult[],
    error: BlockError,
    startTime: number,
    checkpointId?: string
  ): Promise<BlockResult> {
    const config = context.config;
    
    switch (config.errorHandler?.type) {
      case ErrorHandlerType.SKIP:
        return {
          blockId: block.Id,
          status: BlockExecutionStatus.SKIPPED,
          stageResults,
          outputVariables: {},
          metrics: this.buildMetrics(startTime, stageResults),
          error,
          checkpointId,
        };
      
      case ErrorHandlerType.RETRY:
        if (error.retryCount < (config.retryPolicy?.maxRetries ?? 3)) {
          // Rollback to checkpoint if available
          if (checkpointId) {
            await this.checkpointManager.rollback(checkpointId);
          }
          
          // Retry with incremented count
          error.retryCount++;
          return this.execute(block, context);
        }
        break;
      
      case ErrorHandlerType.FALLBACK:
        if (config.errorHandler.fallbackBlockId) {
          const fallbackBlock = await this.getBlock(config.errorHandler.fallbackBlockId);
          return this.execute(fallbackBlock, context);
        }
        break;
    }
    
    // Default: return failed result
    return {
      blockId: block.Id,
      status: BlockExecutionStatus.FAILED,
      stageResults,
      outputVariables: {},
      metrics: this.buildMetrics(startTime, stageResults),
      error,
      checkpointId,
    };
  }
  
  private normalizeBlockName(name: string): string {
    return name
      .replace(/[^a-zA-Z0-9\s]/g, '')
      .split(/\s+/)
      .map((word, i) => i === 0 
        ? word.toLowerCase() 
        : word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
      )
      .join('');
  }
}
```

---

## Block Events

### Event Types

```typescript
enum BlockEventType {
  STARTED = 'BLOCK_STARTED',
  PROGRESS = 'BLOCK_PROGRESS',
  STAGE_STARTED = 'STAGE_STARTED',
  STAGE_COMPLETED = 'STAGE_COMPLETED',
  STAGE_FAILED = 'STAGE_FAILED',
  COMPLETED = 'BLOCK_COMPLETED',
  FAILED = 'BLOCK_FAILED',
  PAUSED = 'BLOCK_PAUSED',
  RESUMED = 'BLOCK_RESUMED',
  CANCELLED = 'BLOCK_CANCELLED',
  CHECKPOINT_CREATED = 'CHECKPOINT_CREATED',
  ROLLBACK_INITIATED = 'ROLLBACK_INITIATED',
}

interface BlockEvent {
  type: BlockEventType;
  blockId: string;
  stageId?: string;
  timestamp: string;
  data: BlockEventData;
}

interface BlockEventData {
  message?: string;
  progress?: number;
  currentStage?: string;
  completedStages?: number;
  totalStages?: number;
  metrics?: Partial<BlockMetrics>;
  error?: BlockError;
  checkpointId?: string;
}
```

---

## Block Validation

```typescript
interface BlockValidator {
  validate(block: ExecutionBlock): Promise<BlockValidationResult>;
  validatePipeline(pipelineId: string): Promise<PipelineValidationResult>;
}

interface BlockValidationResult {
  valid: boolean;
  errors: ValidationError[];
  warnings: ValidationWarning[];
}

interface PipelineValidationResult {
  valid: boolean;
  blockResults: Map<string, BlockValidationResult>;
  pipelineErrors: ValidationError[];
  pipelineWarnings: ValidationWarning[];
}

class BlockValidatorImpl implements BlockValidator {
  async validate(block: ExecutionBlock): Promise<BlockValidationResult> {
    const errors: ValidationError[] = [];
    const warnings: ValidationWarning[] = [];
    
    // 1. Check block has at least one stage
    const stages = await this.getStages(block.Id);
    if (stages.length === 0) {
      warnings.push({
        code: 'EMPTY_BLOCK',
        message: `Block "${block.Name}" has no stages`,
      });
    }
    
    // 2. Validate each stage
    for (const stage of stages) {
      const stageValidation = await this.stageValidator.validate(stage);
      errors.push(...stageValidation.errors.map(e => ({
        ...e,
        context: `Stage: ${stage.Name}`,
      })));
      warnings.push(...stageValidation.warnings);
    }
    
    // 3. Check for circular dependencies within block
    const hasCircular = this.checkCircularDependencies(stages);
    if (hasCircular) {
      errors.push({
        code: 'CIRCULAR_DEPENDENCY',
        message: 'Circular dependency detected between stages',
      });
    }
    
    // 4. Validate error handler configuration
    const config = await this.getBlockConfig(block.Id);
    if (config?.errorHandler?.type === ErrorHandlerType.FALLBACK) {
      if (!config.errorHandler.fallbackBlockId) {
        errors.push({
          code: 'MISSING_FALLBACK',
          message: 'Fallback error handler requires a fallback block',
        });
      }
    }
    
    return {
      valid: errors.length === 0,
      errors,
      warnings,
    };
  }
}
```

---

## UI Components

### Block Node

```typescript
interface BlockNodeProps {
  block: ExecutionBlock;
  stages: Stage[];
  isSelected: boolean;
  isExecuting: boolean;
  executionStatus?: BlockExecutionStatus;
  progress?: number;
  onSelect: () => void;
  onStageAdd: () => void;
  onStageRemove: (stageId: string) => void;
  onCollapse: () => void;
  onDuplicate: () => void;
  onDelete: () => void;
  onConfigure: () => void;
}
```

**Visual States:**
- Default (idle)
- Selected (highlighted border)
- Executing (pulsing animation)
- Success (green indicator)
- Failed (red indicator)
- Collapsed (compact view)

### Block Configuration Panel

```typescript
interface BlockConfigPanelProps {
  block: ExecutionBlock;
  config: BlockConfig;
  onSave: (config: BlockConfig) => void;
  onCancel: () => void;
}
```

**Sections:**
- General (name, description)
- Execution (timeout, retry policy)
- Error Handling (handler type, fallback)
- Checkpoints (start/end options)
- Variables (isolation, exports)

### Stage List (within Block)

```typescript
interface StageListProps {
  blockId: string;
  stages: Stage[];
  onReorder: (stageIds: string[]) => void;
  onStageClick: (stageId: string) => void;
  onStageEdit: (stageId: string) => void;
  onStageDelete: (stageId: string) => void;
  isCollapsed: boolean;
}
```

**Features:**
- Drag-drop reordering
- Stage type icons
- Quick status indicators
- Inline enable/disable toggle

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Block creation | < 50ms |
| Stage addition | < 30ms |
| Block duplication (10 stages) | < 200ms |
| Execution order calculation | < 20ms |
| Block validation | < 100ms |
| Canvas position calculation | < 10ms |

---

## Related Specs

- [Database Schema](./01-database-schema.md)
- [Stage Executor](./04-stage-executor.md)
- [Parallel/Sequential Control](./08-parallel-control.md)
- [Block Chaining](./09-block-chaining.md)
