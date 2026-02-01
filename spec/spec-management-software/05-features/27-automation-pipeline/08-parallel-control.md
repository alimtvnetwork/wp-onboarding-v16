# Component: Parallel/Sequential Control

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Planned  
**Phase:** 3 - Block Orchestration  

---

## Summary

Orchestration system for executing blocks in parallel or sequential order. Manages execution phases, concurrency limits, resource allocation, and synchronization between parallel branches.

---

## User Stories

- As a user, I want to run independent blocks in parallel to speed up execution
- As a user, I want to control the maximum concurrency of parallel blocks
- As a user, I want to wait for all parallel blocks to complete before continuing
- As a user, I want parallel blocks to share results at synchronization points
- As a user, I want to visualize parallel execution in the pipeline editor

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                  Parallel/Sequential Controller                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                  Execution Planner                        │   │
│  │  • Build execution phases from block order/groups         │   │
│  │  • Optimize for parallelism                               │   │
│  │  • Estimate resource requirements                         │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                  Phase Orchestrator                       │   │
│  │  • Execute phases in order                                │   │
│  │  • Manage parallel execution within phase                 │   │
│  │  • Handle synchronization barriers                        │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│         ┌────────────────────┼────────────────────┐             │
│         ▼                    ▼                    ▼             │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐       │
│  │  Parallel   │     │ Sequential  │     │   Barrier   │       │
│  │  Executor   │     │  Executor   │     │  Manager    │       │
│  └─────────────┘     └─────────────┘     └─────────────┘       │
│         │                    │                    │             │
│         └────────────────────┼────────────────────┘             │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                  Resource Manager                         │   │
│  │  • Concurrency limiting                                   │   │
│  │  • Memory allocation                                      │   │
│  │  • Token budget tracking                                  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Execution Modes

### Pipeline Execution Mode

```typescript
enum PipelineExecutionMode {
  SEQUENTIAL = 'SEQUENTIAL',   // All blocks run one after another
  PARALLEL = 'PARALLEL',       // All blocks run concurrently
  HYBRID = 'HYBRID',           // Mix based on ParallelGroup
}
```

### Parallel Group Semantics

Blocks with the same `ParallelGroup` value execute concurrently:

```typescript
// Example: Blocks with parallelism
const blocks = [
  { Id: 'A', ExecutionOrder: 1, ParallelGroup: null },   // Phase 1: Sequential
  { Id: 'B', ExecutionOrder: 2, ParallelGroup: 1 },      // Phase 2: Parallel group 1
  { Id: 'C', ExecutionOrder: 3, ParallelGroup: 1 },      // Phase 2: Parallel group 1
  { Id: 'D', ExecutionOrder: 4, ParallelGroup: null },   // Phase 3: Sequential
  { Id: 'E', ExecutionOrder: 5, ParallelGroup: 2 },      // Phase 4: Parallel group 2
  { Id: 'F', ExecutionOrder: 6, ParallelGroup: 2 },      // Phase 4: Parallel group 2
  { Id: 'G', ExecutionOrder: 7, ParallelGroup: 2 },      // Phase 4: Parallel group 2
];

// Execution order:
// Phase 1: A (sequential)
// Phase 2: B, C (parallel)
// Phase 3: D (sequential)
// Phase 4: E, F, G (parallel)
```

### Visual Representation

```
    ┌───┐
    │ A │  ← Phase 1 (sequential)
    └─┬─┘
      │
   ┌──┴──┐
   ▼     ▼
┌───┐ ┌───┐
│ B │ │ C │  ← Phase 2 (parallel group 1)
└─┬─┘ └─┬─┘
  │     │
  └──┬──┘
     ▼
   ┌───┐
   │ D │  ← Phase 3 (sequential)
   └─┬─┘
     │
  ┌──┼──┐
  ▼  ▼  ▼
┌───┐┌───┐┌───┐
│ E ││ F ││ G │  ← Phase 4 (parallel group 2)
└───┘└───┘└───┘
```

---

## Execution Planner

### Interface

```typescript
interface ExecutionPlanner {
  plan(pipeline: Pipeline): Promise<ExecutionPlan>;
  optimize(plan: ExecutionPlan): ExecutionPlan;
  estimate(plan: ExecutionPlan): ExecutionEstimate;
}

interface ExecutionPlan {
  pipelineId: string;
  phases: ExecutionPhase[];
  dependencies: Map<string, string[]>;  // blockId -> dependent blockIds
  criticalPath: string[];               // Block IDs on critical path
  estimatedDuration: number;
}

interface ExecutionPhase {
  index: number;
  type: PhaseType;
  blocks: ExecutionBlock[];
  parallelGroup?: number;
  barrierBefore: boolean;               // Wait for previous phase
  barrierAfter: boolean;                // Sync point after phase
  maxConcurrency?: number;              // Limit for this phase
}

enum PhaseType {
  SEQUENTIAL = 'SEQUENTIAL',
  PARALLEL = 'PARALLEL',
  BARRIER = 'BARRIER',                  // Pure synchronization point
}

interface ExecutionEstimate {
  totalDuration: number;                // Estimated ms
  parallelSpeedup: number;              // Factor vs sequential
  resourcePeak: ResourceUsage;          // Peak resource usage
  phases: PhaseEstimate[];
}

interface PhaseEstimate {
  phaseIndex: number;
  duration: number;
  tokens: number;
  memoryMb: number;
}
```

### Implementation

```typescript
class ExecutionPlannerImpl implements ExecutionPlanner {
  async plan(pipeline: Pipeline): Promise<ExecutionPlan> {
    const blocks = await this.blockManager.list(pipeline.Id);
    const phases: ExecutionPhase[] = [];
    
    // 1. Sort blocks by execution order
    blocks.sort((a, b) => a.ExecutionOrder - b.ExecutionOrder);
    
    // 2. Group into phases
    let currentPhase: ExecutionPhase | null = null;
    let phaseIndex = 0;
    
    for (const block of blocks) {
      const isParallel = block.ParallelGroup !== null;
      
      if (!currentPhase) {
        // Start new phase
        currentPhase = this.createPhase(phaseIndex++, block);
        phases.push(currentPhase);
      } else if (isParallel && currentPhase.parallelGroup === block.ParallelGroup) {
        // Add to current parallel phase
        currentPhase.blocks.push(block);
      } else {
        // Start new phase
        currentPhase = this.createPhase(phaseIndex++, block);
        phases.push(currentPhase);
      }
    }
    
    // 3. Build dependency graph
    const dependencies = await this.buildDependencyGraph(pipeline.Id, blocks);
    
    // 4. Calculate critical path
    const criticalPath = this.calculateCriticalPath(phases, dependencies);
    
    // 5. Estimate duration
    const estimate = this.estimate({ 
      pipelineId: pipeline.Id, 
      phases, 
      dependencies, 
      criticalPath,
      estimatedDuration: 0,
    });
    
    return {
      pipelineId: pipeline.Id,
      phases,
      dependencies,
      criticalPath,
      estimatedDuration: estimate.totalDuration,
    };
  }
  
  optimize(plan: ExecutionPlan): ExecutionPlan {
    // Optimization strategies:
    
    // 1. Merge adjacent sequential phases if no dependencies
    const mergedPhases = this.mergeSequentialPhases(plan.phases);
    
    // 2. Reorder independent blocks for better parallelism
    const reorderedPhases = this.optimizeParallelism(mergedPhases, plan.dependencies);
    
    // 3. Balance parallel phase sizes
    const balancedPhases = this.balanceParallelPhases(reorderedPhases);
    
    return {
      ...plan,
      phases: balancedPhases,
    };
  }
  
  private createPhase(index: number, block: ExecutionBlock): ExecutionPhase {
    const isParallel = block.ParallelGroup !== null;
    
    return {
      index,
      type: isParallel ? PhaseType.PARALLEL : PhaseType.SEQUENTIAL,
      blocks: [block],
      parallelGroup: block.ParallelGroup ?? undefined,
      barrierBefore: index > 0,  // Wait for previous phase
      barrierAfter: true,        // Sync after completion
    };
  }
  
  private calculateCriticalPath(
    phases: ExecutionPhase[],
    dependencies: Map<string, string[]>
  ): string[] {
    // Find the longest path through the execution graph
    const path: string[] = [];
    
    for (const phase of phases) {
      if (phase.type === PhaseType.SEQUENTIAL) {
        path.push(...phase.blocks.map(b => b.Id));
      } else {
        // For parallel phases, find the longest block
        const longestBlock = phase.blocks.reduce((longest, block) => {
          const blockDuration = this.estimateBlockDuration(block);
          const longestDuration = this.estimateBlockDuration(longest);
          return blockDuration > longestDuration ? block : longest;
        });
        path.push(longestBlock.Id);
      }
    }
    
    return path;
  }
}
```

---

## Phase Orchestrator

### Interface

```typescript
interface PhaseOrchestrator {
  execute(
    plan: ExecutionPlan,
    context: PipelineExecutionContext
  ): Promise<PipelineResult>;
  
  pause(): Promise<void>;
  resume(): Promise<void>;
  cancel(): Promise<void>;
}

interface PipelineExecutionContext {
  executionId: string;
  variables: VariableContext;
  eventEmitter: PipelineEventEmitter;
  config: PipelineConfig;
}

interface PipelineResult {
  pipelineId: string;
  status: PipelineExecutionStatus;
  phaseResults: PhaseResult[];
  blockResults: Map<string, BlockResult>;
  outputVariables: Record<string, unknown>;
  metrics: PipelineMetrics;
}

interface PhaseResult {
  phaseIndex: number;
  status: PhaseExecutionStatus;
  blockResults: BlockResult[];
  duration: number;
}

enum PhaseExecutionStatus {
  PENDING = 'PENDING',
  RUNNING = 'RUNNING',
  SUCCESS = 'SUCCESS',
  PARTIAL_SUCCESS = 'PARTIAL_SUCCESS',  // Some blocks failed
  FAILED = 'FAILED',
  CANCELLED = 'CANCELLED',
}
```

### Implementation

```typescript
class PhaseOrchestratorImpl implements PhaseOrchestrator {
  private isPaused = false;
  private isCancelled = false;
  
  async execute(
    plan: ExecutionPlan,
    context: PipelineExecutionContext
  ): Promise<PipelineResult> {
    const phaseResults: PhaseResult[] = [];
    const blockResults = new Map<string, BlockResult>();
    
    for (const phase of plan.phases) {
      // Check for pause/cancel
      if (this.isCancelled) {
        return this.buildCancelledResult(plan, phaseResults, blockResults);
      }
      
      while (this.isPaused) {
        await this.sleep(100);
        if (this.isCancelled) {
          return this.buildCancelledResult(plan, phaseResults, blockResults);
        }
      }
      
      // Execute phase
      const phaseResult = await this.executePhase(phase, context, blockResults);
      phaseResults.push(phaseResult);
      
      // Update block results
      for (const blockResult of phaseResult.blockResults) {
        blockResults.set(blockResult.blockId, blockResult);
      }
      
      // Check for fatal failure
      if (phaseResult.status === PhaseExecutionStatus.FAILED) {
        if (!context.config.continueOnPhaseFailure) {
          return this.buildFailedResult(plan, phaseResults, blockResults, phase);
        }
      }
    }
    
    return {
      pipelineId: plan.pipelineId,
      status: this.determineStatus(phaseResults),
      phaseResults,
      blockResults,
      outputVariables: context.variables.exportGlobal(),
      metrics: this.buildMetrics(phaseResults),
    };
  }
  
  private async executePhase(
    phase: ExecutionPhase,
    context: PipelineExecutionContext,
    previousResults: Map<string, BlockResult>
  ): Promise<PhaseResult> {
    const startTime = Date.now();
    
    context.eventEmitter.emit({
      type: PipelineEventType.PHASE_STARTED,
      phaseIndex: phase.index,
      timestamp: new Date().toISOString(),
      data: {
        type: phase.type,
        blockCount: phase.blocks.length,
      },
    });
    
    let blockResults: BlockResult[];
    
    if (phase.type === PhaseType.PARALLEL) {
      blockResults = await this.executeParallel(phase, context, previousResults);
    } else {
      blockResults = await this.executeSequential(phase, context, previousResults);
    }
    
    const duration = Date.now() - startTime;
    const status = this.determinePhaseStatus(blockResults);
    
    context.eventEmitter.emit({
      type: PipelineEventType.PHASE_COMPLETED,
      phaseIndex: phase.index,
      timestamp: new Date().toISOString(),
      data: { status, duration },
    });
    
    return {
      phaseIndex: phase.index,
      status,
      blockResults,
      duration,
    };
  }
  
  private async executeParallel(
    phase: ExecutionPhase,
    context: PipelineExecutionContext,
    previousResults: Map<string, BlockResult>
  ): Promise<BlockResult[]> {
    const concurrency = phase.maxConcurrency ?? 
      context.config.maxParallelBlocks ?? 
      10;
    
    // Use a semaphore for concurrency control
    const semaphore = new Semaphore(concurrency);
    
    const promises = phase.blocks.map(async (block) => {
      await semaphore.acquire();
      
      try {
        const blockContext = this.createBlockContext(block, context, previousResults);
        return await this.blockExecutor.execute(block, blockContext);
      } finally {
        semaphore.release();
      }
    });
    
    // Wait for all blocks (or handle fail-fast)
    if (context.config.failFastOnParallelError) {
      return await Promise.all(promises.map(p => 
        p.catch(e => this.createFailedBlockResult(e))
      ));
    }
    
    return await Promise.allSettled(promises).then(results =>
      results.map((r, i) => 
        r.status === 'fulfilled' 
          ? r.value 
          : this.createFailedBlockResult(r.reason, phase.blocks[i].Id)
      )
    );
  }
  
  private async executeSequential(
    phase: ExecutionPhase,
    context: PipelineExecutionContext,
    previousResults: Map<string, BlockResult>
  ): Promise<BlockResult[]> {
    const results: BlockResult[] = [];
    
    for (const block of phase.blocks) {
      const blockContext = this.createBlockContext(block, context, previousResults);
      const result = await this.blockExecutor.execute(block, blockContext);
      results.push(result);
      previousResults.set(block.Id, result);
      
      // Stop on failure if configured
      if (result.status === BlockExecutionStatus.FAILED) {
        if (!context.config.continueOnBlockFailure) {
          break;
        }
      }
    }
    
    return results;
  }
}
```

---

## Synchronization Barriers

### Barrier Types

```typescript
enum BarrierType {
  FULL = 'FULL',           // Wait for all blocks in phase
  PARTIAL = 'PARTIAL',     // Wait for specified blocks
  TIMEOUT = 'TIMEOUT',     // Wait with timeout
  ANY = 'ANY',             // Continue when any block completes
}

interface SyncBarrier {
  type: BarrierType;
  blockIds?: string[];     // For PARTIAL barrier
  timeout?: number;        // For TIMEOUT barrier
  onTimeout?: 'continue' | 'fail';
}
```

### Barrier Manager

```typescript
interface BarrierManager {
  wait(barrier: SyncBarrier, blockResults: Map<string, Promise<BlockResult>>): Promise<void>;
  createBarrier(config: SyncBarrier): SyncBarrier;
}

class BarrierManagerImpl implements BarrierManager {
  async wait(
    barrier: SyncBarrier,
    blockResults: Map<string, Promise<BlockResult>>
  ): Promise<void> {
    switch (barrier.type) {
      case BarrierType.FULL:
        await Promise.all(blockResults.values());
        break;
      
      case BarrierType.PARTIAL:
        const partialPromises = barrier.blockIds!.map(id => blockResults.get(id)!);
        await Promise.all(partialPromises);
        break;
      
      case BarrierType.TIMEOUT:
        const timeoutPromise = new Promise<void>((resolve, reject) => {
          setTimeout(() => {
            if (barrier.onTimeout === 'fail') {
              reject(new Error('Barrier timeout'));
            } else {
              resolve();
            }
          }, barrier.timeout);
        });
        
        await Promise.race([
          Promise.all(blockResults.values()),
          timeoutPromise,
        ]);
        break;
      
      case BarrierType.ANY:
        await Promise.any(blockResults.values());
        break;
      
      default:
        exhaustiveCheck(barrier.type);
    }
  }
}
```

---

## Resource Management

### Concurrency Limiter

```typescript
class Semaphore {
  private permits: number;
  private queue: Array<() => void> = [];
  
  constructor(permits: number) {
    this.permits = permits;
  }
  
  async acquire(): Promise<void> {
    if (this.permits > 0) {
      this.permits--;
      return;
    }
    
    return new Promise<void>(resolve => {
      this.queue.push(resolve);
    });
  }
  
  release(): void {
    if (this.queue.length > 0) {
      const next = this.queue.shift()!;
      next();
    } else {
      this.permits++;
    }
  }
}
```

### Resource Tracker

```typescript
interface ResourceTracker {
  allocate(blockId: string, resources: ResourceRequest): Promise<void>;
  release(blockId: string): Promise<void>;
  getUsage(): ResourceUsage;
  canAllocate(resources: ResourceRequest): boolean;
}

interface ResourceRequest {
  tokens?: number;         // AI tokens budget
  memoryMb?: number;       // Memory limit
  cpuWeight?: number;      // CPU priority
}

interface ResourceUsage {
  tokensUsed: number;
  tokensRemaining: number;
  memoryUsedMb: number;
  memoryLimitMb: number;
  activeBlocks: number;
  queuedBlocks: number;
}

class ResourceTrackerImpl implements ResourceTracker {
  private allocations = new Map<string, ResourceRequest>();
  private readonly limits: ResourceLimits;
  
  constructor(limits: ResourceLimits) {
    this.limits = limits;
  }
  
  async allocate(blockId: string, resources: ResourceRequest): Promise<void> {
    // Wait if resources not available
    while (!this.canAllocate(resources)) {
      await this.sleep(100);
    }
    
    this.allocations.set(blockId, resources);
  }
  
  release(blockId: string): Promise<void> {
    this.allocations.delete(blockId);
    return Promise.resolve();
  }
  
  canAllocate(resources: ResourceRequest): boolean {
    const usage = this.getUsage();
    
    if (resources.memoryMb && 
        usage.memoryUsedMb + resources.memoryMb > this.limits.maxMemoryMb) {
      return false;
    }
    
    if (resources.tokens && 
        usage.tokensUsed + resources.tokens > this.limits.maxTokens) {
      return false;
    }
    
    return true;
  }
  
  getUsage(): ResourceUsage {
    let tokensUsed = 0;
    let memoryUsedMb = 0;
    
    for (const alloc of this.allocations.values()) {
      tokensUsed += alloc.tokens ?? 0;
      memoryUsedMb += alloc.memoryMb ?? 0;
    }
    
    return {
      tokensUsed,
      tokensRemaining: this.limits.maxTokens - tokensUsed,
      memoryUsedMb,
      memoryLimitMb: this.limits.maxMemoryMb,
      activeBlocks: this.allocations.size,
      queuedBlocks: 0,  // Tracked separately
    };
  }
}
```

---

## Configuration

### Pipeline Config

```typescript
interface ParallelControlConfig {
  // Concurrency
  maxParallelBlocks: number;           // Default: 10
  maxParallelStagesPerBlock: number;   // Default: 1 (sequential within block)
  
  // Behavior
  failFastOnParallelError: boolean;    // Default: false
  continueOnBlockFailure: boolean;     // Default: false
  continueOnPhaseFailure: boolean;     // Default: false
  
  // Timeouts
  phaseTimeout?: number;               // Max time per phase (ms)
  parallelBarrierTimeout?: number;     // Max wait at barrier (ms)
  
  // Resources
  tokenBudget?: number;                // Max AI tokens per execution
  memoryLimitMb?: number;              // Max memory usage
  
  // Optimization
  enableAutoParallelization: boolean;  // Detect parallelizable blocks
  rebalanceParallelGroups: boolean;    // Optimize group sizes
}
```

### UI Settings

```typescript
interface ParallelControlUISettings {
  showParallelLanes: boolean;          // Visual lanes for parallel groups
  animateExecution: boolean;           // Show execution progress
  highlightCriticalPath: boolean;      // Highlight slowest path
  showResourceUsage: boolean;          // Display resource meters
  showEstimatedTime: boolean;          // Show duration estimates
}
```

---

## Events

```typescript
enum ParallelControlEventType {
  PHASE_STARTED = 'PHASE_STARTED',
  PHASE_COMPLETED = 'PHASE_COMPLETED',
  PARALLEL_BLOCK_STARTED = 'PARALLEL_BLOCK_STARTED',
  PARALLEL_BLOCK_COMPLETED = 'PARALLEL_BLOCK_COMPLETED',
  BARRIER_WAITING = 'BARRIER_WAITING',
  BARRIER_RELEASED = 'BARRIER_RELEASED',
  CONCURRENCY_LIMITED = 'CONCURRENCY_LIMITED',
  RESOURCE_EXHAUSTED = 'RESOURCE_EXHAUSTED',
}

interface ParallelControlEvent {
  type: ParallelControlEventType;
  timestamp: string;
  data: {
    phaseIndex?: number;
    blockId?: string;
    parallelGroup?: number;
    activeBlocks?: number;
    waitingBlocks?: number;
    resourceUsage?: ResourceUsage;
  };
}
```

---

## UI Components

### Parallel Lane Visualizer

```typescript
interface ParallelLaneVisualizerProps {
  plan: ExecutionPlan;
  executionState?: PipelineExecutionState;
  onBlockClick: (blockId: string) => void;
}
```

**Features:**
- Horizontal swim lanes for parallel groups
- Animated execution progress
- Color-coded status indicators
- Duration annotations
- Dependency arrows

### Execution Timeline

```typescript
interface ExecutionTimelineProps {
  results: PipelineResult;
  showParallel: boolean;
}
```

**Features:**
- Gantt-style timeline
- Parallel blocks shown as overlapping bars
- Phase markers
- Duration labels
- Click to inspect block

### Resource Monitor

```typescript
interface ResourceMonitorProps {
  tracker: ResourceTracker;
  refreshInterval: number;
}
```

**Features:**
- Real-time resource gauges
- Token usage graph
- Memory usage graph
- Active/queued block count
- Warning thresholds

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Execution plan generation | < 50ms |
| Phase startup overhead | < 10ms |
| Barrier synchronization | < 5ms |
| Resource allocation check | < 1ms |
| Event emission | < 1ms |
| Concurrent block limit | 50 blocks |

---

## Related Specs

- [Execution Blocks](./07-execution-blocks.md)
- [Block Chaining](./09-block-chaining.md)
- [Stage Executor](./04-stage-executor.md)
- [Resilient Execution System](../06-ai-integration/12-resilient-execution-system.md)
