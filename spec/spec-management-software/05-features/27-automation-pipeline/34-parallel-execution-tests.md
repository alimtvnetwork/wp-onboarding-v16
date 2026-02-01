# Parallel Execution - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 3 & 5 - Block Orchestration & Control Flow  

---

## Overview

End-to-end test specifications for parallel execution, covering concurrent block execution, loop parallelization, worker pools, and result aggregation.

**Cross-References:**
- [Parallel Sequential Control](./08-parallel-control.md)
- [Loop Constructs](./14-loop-constructs.md)
- [Execution Blocks](./07-execution-blocks.md)

---

## Test Environment Setup

```typescript
interface ParallelExecutionFixtures {
  project: TestProject;
  pipeline: TestPipeline;
  mockEndpoints: {
    fast: string;    // 100ms response
    medium: string;  // 500ms response
    slow: string;    // 2000ms response
  };
}

const setupParallelFixtures = async (): Promise<ParallelExecutionFixtures> => {
  const project = await createTestProject();
  const pipeline = await createTestPipeline(project.id);
  const mockServer = await createMockServer();
  
  mockServer.get('/fast', async (req, res) => {
    await delay(100);
    res.json({ result: 'fast', time: 100 });
  });
  
  mockServer.get('/medium', async (req, res) => {
    await delay(500);
    res.json({ result: 'medium', time: 500 });
  });
  
  mockServer.get('/slow', async (req, res) => {
    await delay(2000);
    res.json({ result: 'slow', time: 2000 });
  });
  
  return {
    project,
    pipeline,
    mockEndpoints: {
      fast: `${mockServer.url}/fast`,
      medium: `${mockServer.url}/medium`,
      slow: `${mockServer.url}/slow`
    }
  };
};
```

---

## Test Suites

### Suite 1: Parallel Block Execution

#### TC-PARALLEL-001: Execute Blocks in Parallel

**Priority:** Critical  
**Type:** E2E  

**Scenario:** Three blocks with same parallel group execute concurrently

```typescript
test('blocks in same parallel group execute concurrently', async ({ page }) => {
  await createParallelPipeline(page, fixtures.pipeline.id, {
    blocks: [
      { name: 'fast', endpoint: fixtures.mockEndpoints.fast, parallelGroup: 1 },
      { name: 'medium', endpoint: fixtures.mockEndpoints.medium, parallelGroup: 1 },
      { name: 'slow', endpoint: fixtures.mockEndpoints.slow, parallelGroup: 1 }
    ]
  });
  
  const startTime = Date.now();
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  const endTime = Date.now();
  
  const totalDuration = endTime - startTime;
  
  // If sequential, would take 100 + 500 + 2000 = 2600ms
  // If parallel, should take ~2000ms (slowest block)
  expect(totalDuration).toBeLessThan(2500);
  expect(totalDuration).toBeGreaterThan(1900);
  
  // Verify all completed
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.filter(e => e.status === 'SUCCESS')).toHaveLength(3);
});
```

---

#### TC-PARALLEL-002: Sequential Blocks Execute in Order

**Priority:** Critical  
**Type:** E2E  

```typescript
test('blocks without parallel group execute sequentially', async ({ page }) => {
  await createSequentialPipeline(page, fixtures.pipeline.id, {
    blocks: [
      { name: 'first', endpoint: fixtures.mockEndpoints.fast, order: 1 },
      { name: 'second', endpoint: fixtures.mockEndpoints.fast, order: 2 },
      { name: 'third', endpoint: fixtures.mockEndpoints.fast, order: 3 }
    ]
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  
  // Verify order
  const times = executions.map(e => ({
    name: e.blockName,
    start: new Date(e.startedAt).getTime()
  })).sort((a, b) => a.start - b.start);
  
  expect(times[0].name).toBe('first');
  expect(times[1].name).toBe('second');
  expect(times[2].name).toBe('third');
  
  // Each should start after previous completes
  expect(times[1].start).toBeGreaterThan(times[0].start + 90);
  expect(times[2].start).toBeGreaterThan(times[1].start + 90);
});
```

---

#### TC-PARALLEL-003: Mixed Parallel and Sequential

**Priority:** High  
**Type:** E2E  

```typescript
test('hybrid execution mode', async ({ page }) => {
  await createHybridPipeline(page, fixtures.pipeline.id, {
    blocks: [
      // Sequential first
      { name: 'init', order: 1 },
      // Parallel group
      { name: 'worker-1', order: 2, parallelGroup: 1 },
      { name: 'worker-2', order: 2, parallelGroup: 1 },
      { name: 'worker-3', order: 2, parallelGroup: 1 },
      // Sequential after parallel completes
      { name: 'aggregate', order: 3 }
    ]
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  
  const initEnd = new Date(executions.find(e => e.blockName === 'init').completedAt).getTime();
  const workerStarts = executions
    .filter(e => e.blockName.startsWith('worker'))
    .map(e => new Date(e.startedAt).getTime());
  const aggregateStart = new Date(executions.find(e => e.blockName === 'aggregate').startedAt).getTime();
  
  // All workers start after init completes
  workerStarts.forEach(t => expect(t).toBeGreaterThanOrEqual(initEnd));
  
  // Aggregate starts after all workers complete
  const workerEnds = executions
    .filter(e => e.blockName.startsWith('worker'))
    .map(e => new Date(e.completedAt).getTime());
  
  expect(aggregateStart).toBeGreaterThanOrEqual(Math.max(...workerEnds));
});
```

---

### Suite 2: FOR_EACH Parallelization

#### TC-PARALLEL-010: Parallel FOR_EACH Iteration

**Priority:** Critical  
**Type:** E2E  

```typescript
test('for_each executes iterations in parallel', async ({ page }) => {
  await createLoopPipeline(page, fixtures.pipeline.id, {
    type: 'FOR_EACH',
    source: '{{global.items}}',
    parallel: true,
    maxConcurrency: 5,
    body: {
      type: 'HTTP',
      config: { url: fixtures.mockEndpoints.medium }
    }
  });
  
  const items = Array.from({ length: 5 }, (_, i) => ({ id: i + 1 }));
  
  const startTime = Date.now();
  await executePipelineWithVariables(page, { 'global.items': items });
  const endTime = Date.now();
  
  const totalDuration = endTime - startTime;
  
  // If sequential: 5 * 500ms = 2500ms
  // If parallel (5 concurrent): ~500ms
  expect(totalDuration).toBeLessThan(800);
});
```

---

#### TC-PARALLEL-011: Concurrency Throttling

**Priority:** High  
**Type:** E2E  

```typescript
test('for_each respects maxConcurrency limit', async ({ page }) => {
  let concurrentRequests = 0;
  let maxConcurrent = 0;
  
  fixtures.mockServer.get('/track', async (req, res) => {
    concurrentRequests++;
    maxConcurrent = Math.max(maxConcurrent, concurrentRequests);
    await delay(200);
    concurrentRequests--;
    res.json({ ok: true });
  });
  
  await createLoopPipeline(page, fixtures.pipeline.id, {
    type: 'FOR_EACH',
    source: '{{global.items}}',
    parallel: true,
    maxConcurrency: 3,  // Limit to 3 concurrent
    body: {
      type: 'HTTP',
      config: { url: `${fixtures.mockServer.url}/track` }
    }
  });
  
  const items = Array.from({ length: 10 }, (_, i) => ({ id: i }));
  await executePipelineWithVariables(page, { 'global.items': items });
  
  expect(maxConcurrent).toBeLessThanOrEqual(3);
});
```

---

#### TC-PARALLEL-012: Sequential FOR_EACH

**Priority:** High  
**Type:** E2E  

```typescript
test('for_each executes sequentially when parallel=false', async ({ page }) => {
  const callOrder: number[] = [];
  
  fixtures.mockServer.get('/order/:id', async (req, res) => {
    callOrder.push(parseInt(req.params.id));
    await delay(50);
    res.json({ id: req.params.id });
  });
  
  await createLoopPipeline(page, fixtures.pipeline.id, {
    type: 'FOR_EACH',
    source: '{{global.items}}',
    parallel: false,
    body: {
      type: 'HTTP',
      config: { url: `${fixtures.mockServer.url}/order/{{loop.index}}` }
    }
  });
  
  const items = [{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }, { id: 5 }];
  await executePipelineWithVariables(page, { 'global.items': items });
  
  expect(callOrder).toEqual([0, 1, 2, 3, 4]);
});
```

---

### Suite 3: Result Aggregation

#### TC-PARALLEL-020: Collect All Results

**Priority:** Critical  
**Type:** E2E  

```typescript
test('aggregate results from parallel blocks', async ({ page }) => {
  await createParallelPipelineWithAggregation(page, fixtures.pipeline.id, {
    parallelBlocks: [
      { name: 'fetch-users', outputVar: 'users' },
      { name: 'fetch-products', outputVar: 'products' },
      { name: 'fetch-orders', outputVar: 'orders' }
    ],
    aggregator: {
      name: 'combine',
      inputBindings: {
        users: '{{fetch-users.output}}',
        products: '{{fetch-products.output}}',
        orders: '{{fetch-orders.output}}'
      }
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const aggregatorExec = await getBlockExecution(fixtures.pipeline.id, 'combine');
  
  expect(aggregatorExec.input).toHaveProperty('users');
  expect(aggregatorExec.input).toHaveProperty('products');
  expect(aggregatorExec.input).toHaveProperty('orders');
});
```

---

#### TC-PARALLEL-021: FOR_EACH Result Array

**Priority:** High  
**Type:** E2E  

```typescript
test('for_each collects results into array', async ({ page }) => {
  await createLoopPipeline(page, fixtures.pipeline.id, {
    type: 'FOR_EACH',
    source: '{{global.ids}}',
    parallel: true,
    outputVariable: 'processedItems',
    aggregation: 'ARRAY',
    body: {
      type: 'TRANSFORM',
      config: {
        expression: '{ id: {{loop.item}}, processed: true }'
      }
    }
  });
  
  await executePipelineWithVariables(page, { 'global.ids': [1, 2, 3, 4, 5] });
  
  const execution = await getLastPipelineExecution(fixtures.pipeline.id);
  const results = execution.outputVariables.processedItems;
  
  expect(results).toHaveLength(5);
  results.forEach((r, i) => {
    expect(r.processed).toBe(true);
  });
});
```

---

#### TC-PARALLEL-022: Deep Merge Aggregation

**Priority:** Medium  
**Type:** E2E  

```typescript
test('deep merge aggregation combines nested objects', async ({ page }) => {
  await createLoopPipeline(page, fixtures.pipeline.id, {
    type: 'FOR_EACH',
    source: '{{global.configs}}',
    parallel: true,
    outputVariable: 'mergedConfig',
    aggregation: 'DEEP_MERGE',
    body: {
      type: 'TRANSFORM',
      config: { expression: '{{loop.item}}' }
    }
  });
  
  await executePipelineWithVariables(page, {
    'global.configs': [
      { database: { host: 'localhost' } },
      { database: { port: 5432 } },
      { cache: { enabled: true } }
    ]
  });
  
  const execution = await getLastPipelineExecution(fixtures.pipeline.id);
  const merged = execution.outputVariables.mergedConfig;
  
  expect(merged).toEqual({
    database: { host: 'localhost', port: 5432 },
    cache: { enabled: true }
  });
});
```

---

### Suite 4: Error Handling in Parallel

#### TC-PARALLEL-030: Single Failure Doesn't Stop Others

**Priority:** Critical  
**Type:** E2E  

```typescript
test('parallel blocks continue when one fails (fail-fast=false)', async ({ page }) => {
  await createParallelPipeline(page, fixtures.pipeline.id, {
    failFast: false,
    blocks: [
      { name: 'success-1', willSucceed: true },
      { name: 'failure', willFail: true },
      { name: 'success-2', willSucceed: true }
    ]
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page, { expectPartialFailure: true });
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  
  expect(executions.find(e => e.blockName === 'success-1').status).toBe('SUCCESS');
  expect(executions.find(e => e.blockName === 'failure').status).toBe('FAILED');
  expect(executions.find(e => e.blockName === 'success-2').status).toBe('SUCCESS');
});
```

---

#### TC-PARALLEL-031: Fail-Fast Cancels Pending

**Priority:** High  
**Type:** E2E  

```typescript
test('fail-fast cancels pending parallel blocks', async ({ page }) => {
  await createParallelPipeline(page, fixtures.pipeline.id, {
    failFast: true,
    blocks: [
      { name: 'slow-success', endpoint: fixtures.mockEndpoints.slow },
      { name: 'fast-failure', willFail: true, delay: 100 }
    ]
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page, { expectFailure: true });
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  
  expect(executions.find(e => e.blockName === 'fast-failure').status).toBe('FAILED');
  expect(executions.find(e => e.blockName === 'slow-success').status).toBe('CANCELLED');
});
```

---

#### TC-PARALLEL-032: FOR_EACH Partial Failure Handling

**Priority:** High  
**Type:** E2E  

```typescript
test('for_each handles partial iteration failures', async ({ page }) => {
  let callCount = 0;
  fixtures.mockServer.get('/maybe-fail', (req, res) => {
    callCount++;
    if (callCount === 3) {
      res.status(500).json({ error: 'Failed' });
    } else {
      res.json({ success: true, call: callCount });
    }
  });
  
  await createLoopPipeline(page, fixtures.pipeline.id, {
    type: 'FOR_EACH',
    source: '{{global.items}}',
    parallel: true,
    continueOnError: true,
    body: {
      type: 'HTTP',
      config: { url: `${fixtures.mockServer.url}/maybe-fail` }
    }
  });
  
  const items = Array.from({ length: 5 }, (_, i) => ({ id: i }));
  await executePipelineWithVariables(page, { 'global.items': items });
  
  const execution = await getLastPipelineExecution(fixtures.pipeline.id);
  
  expect(execution.status).toBe('PARTIAL_SUCCESS');
  expect(execution.completedIterations).toBe(4);
  expect(execution.failedIterations).toBe(1);
});
```

---

### Suite 5: Worker Pool Management

#### TC-PARALLEL-040: Worker Pool Limits

**Priority:** High  
**Type:** E2E  

```typescript
test('respects global worker pool limit', async ({ page }) => {
  let activeTasks = 0;
  let maxActive = 0;
  
  fixtures.mockServer.get('/task', async (req, res) => {
    activeTasks++;
    maxActive = Math.max(maxActive, activeTasks);
    await delay(100);
    activeTasks--;
    res.json({ ok: true });
  });
  
  // Create pipeline with many parallel blocks
  await createParallelPipeline(page, fixtures.pipeline.id, {
    workerPoolSize: 5,
    blocks: Array.from({ length: 20 }, (_, i) => ({
      name: `task-${i}`,
      endpoint: `${fixtures.mockServer.url}/task`,
      parallelGroup: 1
    }))
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  expect(maxActive).toBeLessThanOrEqual(5);
});
```

---

#### TC-PARALLEL-041: Dynamic Concurrency Adjustment

**Priority:** Medium  
**Type:** E2E  

```typescript
test('concurrency adjusts based on system load', async ({ page }) => {
  // Enable adaptive concurrency
  await setPipelineConfig(page, fixtures.pipeline.id, {
    adaptiveConcurrency: true,
    minConcurrency: 2,
    maxConcurrency: 10
  });
  
  // Create CPU-intensive tasks
  await createLoopPipeline(page, fixtures.pipeline.id, {
    type: 'FOR_EACH',
    source: '{{global.items}}',
    parallel: true,
    body: {
      type: 'VALIDATION',
      config: { script: cpuIntensiveScript }
    }
  });
  
  const items = Array.from({ length: 50 }, (_, i) => ({ id: i }));
  await executePipelineWithVariables(page, { 'global.items': items });
  
  // Verify concurrency was adjusted
  const metrics = await getExecutionMetrics(fixtures.pipeline.id);
  expect(metrics.actualConcurrency).toBeGreaterThanOrEqual(2);
  expect(metrics.actualConcurrency).toBeLessThanOrEqual(10);
});
```

---

### Suite 6: Dependency Graph

#### TC-PARALLEL-050: Respect Dependencies Between Parallel Blocks

**Priority:** High  
**Type:** E2E  

```typescript
test('parallel blocks respect explicit dependencies', async ({ page }) => {
  await createDependencyPipeline(page, fixtures.pipeline.id, {
    blocks: [
      { name: 'A', parallelGroup: 1 },
      { name: 'B', parallelGroup: 1, dependsOn: ['A'] },
      { name: 'C', parallelGroup: 1 },
      { name: 'D', parallelGroup: 1, dependsOn: ['B', 'C'] }
    ]
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  const times = executions.reduce((acc, e) => {
    acc[e.blockName] = {
      start: new Date(e.startedAt).getTime(),
      end: new Date(e.completedAt).getTime()
    };
    return acc;
  }, {});
  
  // A can start immediately
  // B must wait for A
  expect(times.B.start).toBeGreaterThanOrEqual(times.A.end);
  
  // C can run parallel to A
  // D must wait for both B and C
  expect(times.D.start).toBeGreaterThanOrEqual(times.B.end);
  expect(times.D.start).toBeGreaterThanOrEqual(times.C.end);
});
```

---

## Performance Benchmarks

| Scenario | Target | Max |
|----------|--------|-----|
| 10 parallel blocks | <(slowest + 100ms) | slowest + 500ms |
| 100-item FOR_EACH (10 concurrent) | <(10 * slowest / 10) + 200ms | — |
| Worker pool scheduling | <10ms overhead | 50ms |
| Result aggregation (1000 items) | <100ms | 500ms |
| Dependency resolution | <5ms | 20ms |

---

## Edge Cases

| Scenario | Expected Behavior |
|----------|-------------------|
| Empty parallel group | Skip, continue to next |
| All parallel blocks fail | Pipeline fails, all errors collected |
| Circular dependencies | Detect at build time, reject |
| Memory pressure | Reduce concurrency dynamically |
| Network saturation | Throttle HTTP stages |
