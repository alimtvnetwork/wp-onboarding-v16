# Variable Resolution - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 1 - Foundation  

---

## Overview

End-to-end test specifications for the Variable Registry system, covering template syntax, scoped resolution, type coercion, and error handling.

**Cross-References:**
- [Variable Registry](./03-variable-registry.md)
- [I/O Binding](./06-io-binding.md)

---

## Test Environment Setup

```typescript
interface VariableResolutionFixtures {
  project: TestProject;
  pipeline: TestPipeline;
  blocks: TestBlock[];
  stages: TestStage[];
  globalVariables: Record<string, unknown>;
}

const setupVariableFixtures = async (): Promise<VariableResolutionFixtures> => {
  const project = await createTestProject();
  const pipeline = await createTestPipeline(project.id, {
    globalVariables: {
      projectName: 'Test Project',
      version: '1.0.0',
      config: { debug: true, maxRetries: 3 }
    }
  });
  
  const blocks = await createTestBlocks(pipeline.id, 3);
  const stages = await createTestStages(blocks, [
    { blockIndex: 0, type: 'PROMPT', outputVar: 'promptResult' },
    { blockIndex: 0, type: 'TRANSFORM', outputVar: 'transformedData' },
    { blockIndex: 1, type: 'SEARCH', outputVar: 'searchResults' },
    { blockIndex: 2, type: 'VALIDATION', outputVar: 'validationStatus' },
  ]);
  
  return { project, pipeline, blocks, stages, globalVariables: pipeline.globalVariables };
};
```

---

## Test Suites

### Suite 1: Basic Variable Syntax

#### TC-VAR-001: Resolve Simple Variable

**Priority:** Critical  
**Type:** Unit  

**Input:** `{{global.projectName}}`  
**Expected:** `"Test Project"`

```typescript
test('resolve simple global variable', async () => {
  const resolver = createVariableResolver(fixtures.pipeline.id);
  
  const result = await resolver.resolve('{{global.projectName}}');
  
  expect(result).toBe('Test Project');
});
```

---

#### TC-VAR-002: Resolve Nested Path

**Priority:** Critical  
**Type:** Unit  

**Input:** `{{global.config.maxRetries}}`  
**Expected:** `3`

```typescript
test('resolve nested object path', async () => {
  const resolver = createVariableResolver(fixtures.pipeline.id);
  
  const result = await resolver.resolve('{{global.config.maxRetries}}');
  
  expect(result).toBe(3);
});
```

---

#### TC-VAR-003: Resolve Array Index

**Priority:** High  
**Type:** Unit  

**Input:** `{{block.search.searchResults[0].title}}`  
**Expected:** First search result title

```typescript
test('resolve array index access', async () => {
  const context = createExecutionContext({
    'block.search.searchResults': [
      { title: 'First Result', url: 'https://example.com/1' },
      { title: 'Second Result', url: 'https://example.com/2' }
    ]
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context);
  const result = await resolver.resolve('{{block.search.searchResults[0].title}}');
  
  expect(result).toBe('First Result');
});
```

---

#### TC-VAR-004: Resolve Block.Stage.Output Pattern

**Priority:** Critical  
**Type:** Unit  

**Input:** `{{block1.prompt.output}}`  
**Expected:** Output from prompt stage in block1

```typescript
test('resolve block.stage.output pattern', async () => {
  const context = createExecutionContext({
    'block1.prompt.output': '<html><body>Generated content</body></html>'
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context);
  const result = await resolver.resolve('{{block1.prompt.output}}');
  
  expect(result).toBe('<html><body>Generated content</body></html>');
});
```

---

### Suite 2: Type Coercion

#### TC-VAR-010: Coerce String to Number

**Priority:** High  
**Type:** Unit  

```typescript
test('coerce string to number', async () => {
  const context = createExecutionContext({
    'stage.output': '42'
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context);
  const result = await resolver.resolve('{{stage.output | number}}');
  
  expect(result).toBe(42);
  expect(typeof result).toBe('number');
});
```

---

#### TC-VAR-011: Coerce to Boolean

**Priority:** Medium  
**Type:** Unit  

```typescript
test('coerce various values to boolean', async () => {
  const resolver = createVariableResolver(fixtures.pipeline.id);
  
  expect(await resolver.resolve('{{truthy | boolean}}', { truthy: 'yes' })).toBe(true);
  expect(await resolver.resolve('{{truthy | boolean}}', { truthy: 1 })).toBe(true);
  expect(await resolver.resolve('{{falsy | boolean}}', { falsy: '' })).toBe(false);
  expect(await resolver.resolve('{{falsy | boolean}}', { falsy: 0 })).toBe(false);
  expect(await resolver.resolve('{{falsy | boolean}}', { falsy: null })).toBe(false);
});
```

---

#### TC-VAR-012: Coerce Object to JSON String

**Priority:** High  
**Type:** Unit  

```typescript
test('coerce object to JSON string', async () => {
  const context = createExecutionContext({
    'stage.data': { name: 'Test', count: 5 }
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context);
  const result = await resolver.resolve('{{stage.data | json}}');
  
  expect(result).toBe('{"name":"Test","count":5}');
});
```

---

#### TC-VAR-013: Parse JSON String to Object

**Priority:** High  
**Type:** Unit  

```typescript
test('parse JSON string to object', async () => {
  const context = createExecutionContext({
    'stage.jsonString': '{"key": "value", "num": 123}'
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context);
  const result = await resolver.resolve('{{stage.jsonString | parse}}');
  
  expect(result).toEqual({ key: 'value', num: 123 });
});
```

---

### Suite 3: Scope Resolution

#### TC-VAR-020: Stage Scope Takes Precedence

**Priority:** Critical  
**Type:** Unit  

```typescript
test('stage scope overrides block scope', async () => {
  const context = createExecutionContext({
    'block.value': 'block-level',
    'stage.value': 'stage-level'
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context, {
    currentScope: 'stage'
  });
  
  // Unqualified reference should resolve to current scope
  const result = await resolver.resolve('{{value}}');
  
  expect(result).toBe('stage-level');
});
```

---

#### TC-VAR-021: Explicit Scope Override

**Priority:** High  
**Type:** Unit  

```typescript
test('explicit scope prefix overrides current scope', async () => {
  const context = createExecutionContext({
    'global.value': 'global-level',
    'block.value': 'block-level',
    'stage.value': 'stage-level'
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context, {
    currentScope: 'stage'
  });
  
  expect(await resolver.resolve('{{global.value}}')).toBe('global-level');
  expect(await resolver.resolve('{{block.value}}')).toBe('block-level');
  expect(await resolver.resolve('{{stage.value}}')).toBe('stage-level');
});
```

---

#### TC-VAR-022: Cross-Block Variable Access

**Priority:** Critical  
**Type:** E2E  

```typescript
test('access variable from previous block', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  // Block 1: Generate content
  await addBlockWithStage(page, 'PROMPT', {
    name: 'generator',
    outputVar: 'generatedContent'
  });
  
  // Block 2: Use content from Block 1
  await addBlockWithStage(page, 'VALIDATION', {
    name: 'validator',
    inputBinding: '{{generator.prompt.generatedContent}}'
  });
  
  await connectBlocks(page, 'generator', 'validator');
  
  // Execute and verify
  await page.click('[data-testid="run-pipeline"]');
  
  await expect(page.locator('[data-testid="stage-validator-input"]'))
    .toContainText('generatedContent');
});
```

---

### Suite 4: Loop Variables

#### TC-VAR-030: Loop Item Variable

**Priority:** High  
**Type:** E2E  

```typescript
test('resolve loop.item in FOR_EACH', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  // Create loop over array
  await addLoopBlock(page, {
    type: 'FOR_EACH',
    source: '{{global.items}}',
    itemVar: 'currentItem'
  });
  
  // Add stage using loop variable
  await addStageToBlock(page, 'PROMPT', {
    template: 'Process item: {{loop.item.name}}'
  });
  
  await executePipeline(page, {
    'global.items': [
      { name: 'Item A' },
      { name: 'Item B' },
      { name: 'Item C' }
    ]
  });
  
  // Verify each iteration used correct item
  const executions = await getStageExecutions(page);
  expect(executions).toHaveLength(3);
  expect(executions[0].resolvedPrompt).toContain('Item A');
  expect(executions[1].resolvedPrompt).toContain('Item B');
  expect(executions[2].resolvedPrompt).toContain('Item C');
});
```

---

#### TC-VAR-031: Loop Index Variable

**Priority:** Medium  
**Type:** E2E  

```typescript
test('resolve loop.index in FOR_EACH', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  await addLoopBlock(page, {
    type: 'FOR_EACH',
    source: '{{global.items}}',
    indexVar: 'i'
  });
  
  await addStageToBlock(page, 'TRANSFORM', {
    expression: '"Item " + {{loop.index}} + " of " + {{loop.total}}'
  });
  
  await executePipeline(page, {
    'global.items': ['a', 'b', 'c']
  });
  
  const executions = await getStageExecutions(page);
  expect(executions[0].output).toBe('Item 0 of 3');
  expect(executions[1].output).toBe('Item 1 of 3');
  expect(executions[2].output).toBe('Item 2 of 3');
});
```

---

### Suite 5: Error Handling

#### TC-VAR-040: Undefined Variable

**Priority:** Critical  
**Type:** Unit  

```typescript
test('undefined variable throws descriptive error', async () => {
  const resolver = createVariableResolver(fixtures.pipeline.id);
  
  await expect(resolver.resolve('{{nonexistent.variable}}'))
    .rejects.toThrow('Variable not found: nonexistent.variable');
});
```

---

#### TC-VAR-041: Undefined Variable with Default

**Priority:** High  
**Type:** Unit  

```typescript
test('undefined variable uses default value', async () => {
  const resolver = createVariableResolver(fixtures.pipeline.id);
  
  const result = await resolver.resolve('{{missing.value | default:"fallback"}}');
  
  expect(result).toBe('fallback');
});
```

---

#### TC-VAR-042: Invalid JSONPath

**Priority:** Medium  
**Type:** Unit  

```typescript
test('invalid path syntax throws parse error', async () => {
  const resolver = createVariableResolver(fixtures.pipeline.id);
  
  await expect(resolver.resolve('{{block.[invalid.path}}'))
    .rejects.toThrow('Invalid variable syntax');
});
```

---

#### TC-VAR-043: Type Coercion Failure

**Priority:** Medium  
**Type:** Unit  

```typescript
test('failed type coercion throws clear error', async () => {
  const context = createExecutionContext({
    'stage.output': 'not-a-number'
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context);
  
  await expect(resolver.resolve('{{stage.output | number}}'))
    .rejects.toThrow('Cannot coerce "not-a-number" to number');
});
```

---

### Suite 6: Template Interpolation

#### TC-VAR-050: Multiple Variables in Template

**Priority:** Critical  
**Type:** Unit  

```typescript
test('resolve multiple variables in template', async () => {
  const context = createExecutionContext({
    'global.name': 'John',
    'global.role': 'Developer'
  });
  
  const resolver = createVariableResolver(fixtures.pipeline.id, context);
  const template = 'Hello {{global.name}}, you are a {{global.role}}!';
  
  const result = await resolver.resolveTemplate(template);
  
  expect(result).toBe('Hello John, you are a Developer!');
});
```

---

#### TC-VAR-051: Preserve Non-Variable Text

**Priority:** High  
**Type:** Unit  

```typescript
test('preserve text without variables', async () => {
  const resolver = createVariableResolver(fixtures.pipeline.id);
  const template = 'This has no variables, just {curly} braces and {{incomplete';
  
  const result = await resolver.resolveTemplate(template);
  
  expect(result).toBe(template);
});
```

---

#### TC-VAR-052: Escape Variable Syntax

**Priority:** Low  
**Type:** Unit  

```typescript
test('escaped braces not treated as variables', async () => {
  const resolver = createVariableResolver(fixtures.pipeline.id);
  const template = 'Show literal: \\{{not.a.variable}}';
  
  const result = await resolver.resolveTemplate(template);
  
  expect(result).toBe('Show literal: {{not.a.variable}}');
});
```

---

## Performance Benchmarks

| Operation | Target | Max |
|-----------|--------|-----|
| Simple variable resolution | <1ms | 5ms |
| Nested path (5 levels) | <2ms | 10ms |
| Template with 10 variables | <5ms | 20ms |
| Array iteration (100 items) | <50ms | 200ms |
| Full pipeline variable graph | <100ms | 500ms |

---

## Edge Cases

| Scenario | Expected Behavior |
|----------|-------------------|
| Circular reference | Detect and throw error |
| Very deep nesting (100+) | Max depth limit (20) |
| Large array (10k items) | Paginated access warning |
| Unicode in variable names | Support UTF-8 identifiers |
| Empty string value | Distinguish from undefined |
