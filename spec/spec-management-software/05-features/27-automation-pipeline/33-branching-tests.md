# Branching Logic - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 5 - Control Flow  

---

## Overview

End-to-end test specifications for conditional branching, covering IF_ELSE, SWITCH, GUARD, and GATE nodes with various condition expressions.

**Cross-References:**
- [Conditional Nodes](./13-conditional-nodes.md)
- [Error Handlers](./15-error-handlers.md)

---

## Test Environment Setup

```typescript
interface BranchingFixtures {
  project: TestProject;
  pipeline: TestPipeline;
  blocks: {
    start: TestBlock;
    branchA: TestBlock;
    branchB: TestBlock;
    branchDefault: TestBlock;
    end: TestBlock;
  };
}

const setupBranchingFixtures = async (): Promise<BranchingFixtures> => {
  const project = await createTestProject();
  const pipeline = await createTestPipeline(project.id);
  
  return {
    project,
    pipeline,
    blocks: {
      start: await createTestBlock(pipeline.id, 'Start'),
      branchA: await createTestBlock(pipeline.id, 'Branch A'),
      branchB: await createTestBlock(pipeline.id, 'Branch B'),
      branchDefault: await createTestBlock(pipeline.id, 'Default'),
      end: await createTestBlock(pipeline.id, 'End'),
    }
  };
};
```

---

## Test Suites

### Suite 1: IF_ELSE Conditions

#### TC-BRANCH-001: Simple Boolean Condition

**Priority:** Critical  
**Type:** E2E  

**Scenario:** Route based on boolean variable

```typescript
test('if_else routes on boolean condition', async ({ page }) => {
  await createBranchingPipeline(page, fixtures.pipeline.id, {
    type: 'IF_ELSE',
    condition: '{{stage.result.success}} == true',
    trueBranch: 'branchA',
    falseBranch: 'branchB'
  });
  
  // Test true path
  await executePipelineWithVariables(page, {
    'stage.result.success': true
  });
  
  let executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('Branch A');
  expect(executions.map(e => e.blockName)).not.toContain('Branch B');
  
  // Test false path
  await executePipelineWithVariables(page, {
    'stage.result.success': false
  });
  
  executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('Branch B');
});
```

---

#### TC-BRANCH-002: Numeric Comparison

**Priority:** High  
**Type:** E2E  

```typescript
test('if_else with numeric comparison', async ({ page }) => {
  await createBranchingPipeline(page, fixtures.pipeline.id, {
    type: 'IF_ELSE',
    condition: '{{stage.score}} >= 80',
    trueBranch: 'branchA',  // Pass
    falseBranch: 'branchB'  // Fail
  });
  
  // Score 85 - should pass
  await executePipelineWithVariables(page, { 'stage.score': 85 });
  let executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('Branch A');
  
  // Score 75 - should fail
  await executePipelineWithVariables(page, { 'stage.score': 75 });
  executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('Branch B');
  
  // Edge case: exactly 80
  await executePipelineWithVariables(page, { 'stage.score': 80 });
  executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('Branch A');
});
```

---

#### TC-BRANCH-003: String Comparison

**Priority:** High  
**Type:** E2E  

```typescript
test('if_else with string comparison', async ({ page }) => {
  await createBranchingPipeline(page, fixtures.pipeline.id, {
    type: 'IF_ELSE',
    condition: '{{stage.status}} == "approved"',
    trueBranch: 'branchA',
    falseBranch: 'branchB'
  });
  
  await executePipelineWithVariables(page, { 'stage.status': 'approved' });
  let executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('Branch A');
  
  await executePipelineWithVariables(page, { 'stage.status': 'rejected' });
  executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('Branch B');
});
```

---

#### TC-BRANCH-004: Compound Conditions (AND/OR)

**Priority:** High  
**Type:** E2E  

```typescript
test('if_else with compound AND condition', async ({ page }) => {
  await createBranchingPipeline(page, fixtures.pipeline.id, {
    type: 'IF_ELSE',
    condition: '{{stage.score}} >= 80 AND {{stage.verified}} == true',
    trueBranch: 'branchA',
    falseBranch: 'branchB'
  });
  
  // Both conditions true
  await executePipelineWithVariables(page, { 
    'stage.score': 85, 
    'stage.verified': true 
  });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('Branch A');
  
  // Only one condition true
  await executePipelineWithVariables(page, { 
    'stage.score': 85, 
    'stage.verified': false 
  });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('Branch B');
});

test('if_else with compound OR condition', async ({ page }) => {
  await createBranchingPipeline(page, fixtures.pipeline.id, {
    type: 'IF_ELSE',
    condition: '{{stage.priority}} == "high" OR {{stage.urgent}} == true',
    trueBranch: 'branchA',
    falseBranch: 'branchB'
  });
  
  // First condition true
  await executePipelineWithVariables(page, { 
    'stage.priority': 'high', 
    'stage.urgent': false 
  });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('Branch A');
  
  // Second condition true
  await executePipelineWithVariables(page, { 
    'stage.priority': 'low', 
    'stage.urgent': true 
  });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('Branch A');
  
  // Neither true
  await executePipelineWithVariables(page, { 
    'stage.priority': 'low', 
    'stage.urgent': false 
  });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('Branch B');
});
```

---

### Suite 2: SWITCH Conditions

#### TC-BRANCH-010: Switch with Multiple Cases

**Priority:** High  
**Type:** E2E  

```typescript
test('switch routes to matching case', async ({ page }) => {
  await createBranchingPipeline(page, fixtures.pipeline.id, {
    type: 'SWITCH',
    expression: '{{stage.category}}',
    cases: [
      { value: 'electronics', target: 'electronics-handler' },
      { value: 'clothing', target: 'clothing-handler' },
      { value: 'food', target: 'food-handler' }
    ],
    defaultTarget: 'default-handler'
  });
  
  await executePipelineWithVariables(page, { 'stage.category': 'clothing' });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('clothing-handler');
  
  await executePipelineWithVariables(page, { 'stage.category': 'electronics' });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('electronics-handler');
});
```

---

#### TC-BRANCH-011: Switch Default Case

**Priority:** High  
**Type:** E2E  

```typescript
test('switch falls through to default', async ({ page }) => {
  await createBranchingPipeline(page, fixtures.pipeline.id, {
    type: 'SWITCH',
    expression: '{{stage.type}}',
    cases: [
      { value: 'A', target: 'handler-a' },
      { value: 'B', target: 'handler-b' }
    ],
    defaultTarget: 'handler-default'
  });
  
  await executePipelineWithVariables(page, { 'stage.type': 'C' });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('handler-default');
});
```

---

#### TC-BRANCH-012: Switch with Numeric Values

**Priority:** Medium  
**Type:** E2E  

```typescript
test('switch with numeric case values', async ({ page }) => {
  await createBranchingPipeline(page, fixtures.pipeline.id, {
    type: 'SWITCH',
    expression: '{{stage.errorCode}}',
    cases: [
      { value: 400, target: 'bad-request' },
      { value: 401, target: 'unauthorized' },
      { value: 404, target: 'not-found' },
      { value: 500, target: 'server-error' }
    ],
    defaultTarget: 'unknown-error'
  });
  
  await executePipelineWithVariables(page, { 'stage.errorCode': 404 });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('not-found');
});
```

---

### Suite 3: GUARD Conditions

#### TC-BRANCH-020: Guard Blocks Execution

**Priority:** High  
**Type:** E2E  

**Scenario:** Guard prevents downstream execution if condition fails

```typescript
test('guard blocks execution when condition fails', async ({ page }) => {
  await createPipelineWithGuard(page, fixtures.pipeline.id, {
    guardCondition: '{{global.isAuthorized}} == true',
    guardedBlock: 'sensitive-operation',
    failureAction: 'STOP'
  });
  
  // Authorized - should execute
  await executePipelineWithVariables(page, { 'global.isAuthorized': true });
  let executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('sensitive-operation');
  
  // Not authorized - should stop
  await executePipelineWithVariables(page, { 'global.isAuthorized': false });
  executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).not.toContain('sensitive-operation');
  expect(executions.find(e => e.status === 'STOPPED')).toBeDefined();
});
```

---

#### TC-BRANCH-021: Guard with Alternative Path

**Priority:** Medium  
**Type:** E2E  

```typescript
test('guard redirects to alternative on failure', async ({ page }) => {
  await createPipelineWithGuard(page, fixtures.pipeline.id, {
    guardCondition: '{{stage.data}} != null',
    guardedBlock: 'process-data',
    failureAction: 'BRANCH',
    failureBranch: 'handle-missing-data'
  });
  
  await executePipelineWithVariables(page, { 'stage.data': null });
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.map(e => e.blockName)).toContain('handle-missing-data');
  expect(executions.map(e => e.blockName)).not.toContain('process-data');
});
```

---

### Suite 4: GATE Conditions

#### TC-BRANCH-030: Gate Waits for All Inputs

**Priority:** High  
**Type:** E2E  

**Scenario:** Gate block waits for all parallel branches before continuing

```typescript
test('gate waits for all parallel inputs', async ({ page }) => {
  await createParallelPipelineWithGate(page, fixtures.pipeline.id, {
    parallelBlocks: ['fast-task', 'slow-task', 'medium-task'],
    gateBlock: 'aggregator'
  });
  
  await page.click('[data-testid="run-pipeline"]');
  
  // Gate should wait for slowest task
  await waitForExecution(page);
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  const aggregatorExec = executions.find(e => e.blockName === 'aggregator');
  
  // All parallel blocks should complete before aggregator starts
  const parallelCompleteTimes = executions
    .filter(e => ['fast-task', 'slow-task', 'medium-task'].includes(e.blockName))
    .map(e => new Date(e.completedAt).getTime());
  
  const aggregatorStartTime = new Date(aggregatorExec.startedAt).getTime();
  
  parallelCompleteTimes.forEach(t => {
    expect(t).toBeLessThan(aggregatorStartTime);
  });
});
```

---

#### TC-BRANCH-031: Gate with Partial Completion

**Priority:** Medium  
**Type:** E2E  

```typescript
test('gate continues after timeout with partial results', async ({ page }) => {
  await createParallelPipelineWithGate(page, fixtures.pipeline.id, {
    parallelBlocks: ['fast-task', 'hanging-task'],
    gateBlock: 'aggregator',
    gateConfig: {
      waitMode: 'TIMEOUT',
      timeoutSeconds: 5,
      minCompletions: 1
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  const aggregatorExec = executions.find(e => e.blockName === 'aggregator');
  
  expect(aggregatorExec.status).toBe('SUCCESS');
  expect(aggregatorExec.input.completedBranches).toContain('fast-task');
  expect(aggregatorExec.input.timedOutBranches).toContain('hanging-task');
});
```

---

### Suite 5: Condition Expressions

#### TC-BRANCH-040: Contains Operator

**Priority:** Medium  
**Type:** Unit  

```typescript
test('contains operator for string matching', async () => {
  const evaluator = createConditionEvaluator();
  
  expect(await evaluator.evaluate(
    '{{stage.message}} CONTAINS "error"',
    { 'stage.message': 'An error occurred' }
  )).toBe(true);
  
  expect(await evaluator.evaluate(
    '{{stage.message}} CONTAINS "error"',
    { 'stage.message': 'All good!' }
  )).toBe(false);
});
```

---

#### TC-BRANCH-041: Regex Match Operator

**Priority:** Medium  
**Type:** Unit  

```typescript
test('regex operator for pattern matching', async () => {
  const evaluator = createConditionEvaluator();
  
  expect(await evaluator.evaluate(
    '{{stage.email}} MATCHES "^[a-z]+@[a-z]+\\.[a-z]+$"',
    { 'stage.email': 'test@example.com' }
  )).toBe(true);
  
  expect(await evaluator.evaluate(
    '{{stage.email}} MATCHES "^[a-z]+@[a-z]+\\.[a-z]+$"',
    { 'stage.email': 'invalid-email' }
  )).toBe(false);
});
```

---

#### TC-BRANCH-042: Existence Check

**Priority:** High  
**Type:** Unit  

```typescript
test('existence operators', async () => {
  const evaluator = createConditionEvaluator();
  
  expect(await evaluator.evaluate(
    '{{stage.data}} EXISTS',
    { 'stage.data': { key: 'value' } }
  )).toBe(true);
  
  expect(await evaluator.evaluate(
    '{{stage.data}} EXISTS',
    { 'stage.other': 'value' }
  )).toBe(false);
  
  expect(await evaluator.evaluate(
    '{{stage.empty}} IS_EMPTY',
    { 'stage.empty': '' }
  )).toBe(true);
  
  expect(await evaluator.evaluate(
    '{{stage.empty}} IS_EMPTY',
    { 'stage.empty': 'not empty' }
  )).toBe(false);
});
```

---

### Suite 6: Branch Router Strategies

#### TC-BRANCH-050: Priority Strategy

**Priority:** High  
**Type:** E2E  

```typescript
test('priority strategy takes first matching condition', async ({ page }) => {
  await createMultiConditionBranch(page, fixtures.pipeline.id, {
    strategy: 'PRIORITY',
    conditions: [
      { condition: '{{score}} >= 90', target: 'excellent', priority: 1 },
      { condition: '{{score}} >= 80', target: 'good', priority: 2 },
      { condition: '{{score}} >= 70', target: 'average', priority: 3 }
    ]
  });
  
  // Score 95 matches both first and second, should take first (highest priority)
  await executePipelineWithVariables(page, { score: 95 });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('excellent');
  
  // Score 85 matches second and third, should take second
  await executePipelineWithVariables(page, { score: 85 });
  expect(await getExecutedBranch(fixtures.pipeline.id)).toBe('good');
});
```

---

#### TC-BRANCH-051: Parallel Strategy

**Priority:** Medium  
**Type:** E2E  

```typescript
test('parallel strategy executes all matching branches', async ({ page }) => {
  await createMultiConditionBranch(page, fixtures.pipeline.id, {
    strategy: 'PARALLEL',
    conditions: [
      { condition: '{{item.needsValidation}} == true', target: 'validate' },
      { condition: '{{item.needsEnrichment}} == true', target: 'enrich' },
      { condition: '{{item.needsNotification}} == true', target: 'notify' }
    ]
  });
  
  await executePipelineWithVariables(page, {
    'item.needsValidation': true,
    'item.needsEnrichment': true,
    'item.needsNotification': false
  });
  
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  const executedBlocks = executions.map(e => e.blockName);
  
  expect(executedBlocks).toContain('validate');
  expect(executedBlocks).toContain('enrich');
  expect(executedBlocks).not.toContain('notify');
});
```

---

## Performance Benchmarks

| Operation | Target | Max |
|-----------|--------|-----|
| Simple condition evaluation | <1ms | 5ms |
| Compound condition (5 clauses) | <5ms | 20ms |
| Regex evaluation | <2ms | 10ms |
| Switch with 20 cases | <2ms | 10ms |
| Gate synchronization | <100ms | 500ms |

---

## Edge Cases

| Scenario | Expected Behavior |
|----------|-------------------|
| Null in comparison | Null-safe operators |
| Undefined variable | Throw or use default |
| Type mismatch | Attempt coercion first |
| Circular branch | Detect and error |
| No matching case | Use default or error |
