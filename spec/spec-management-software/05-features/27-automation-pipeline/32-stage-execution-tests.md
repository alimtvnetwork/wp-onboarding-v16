# Stage Execution - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 2 - Stage Engine  

---

## Overview

End-to-end test specifications for stage execution, covering all 7 stage types, lifecycle management, timeout/retry behavior, and validation runtime.

**Cross-References:**
- [Stage Executor](./04-stage-executor.md)
- [Validation Runtime](./05-validation-runtime.md)
- [I/O Binding](./06-io-binding.md)

---

## Test Environment Setup

```typescript
interface StageExecutionFixtures {
  project: TestProject;
  pipeline: TestPipeline;
  promptTemplate: TestPrompt;
  validationScripts: {
    golang: TestScript;
    python: TestScript;
    typescript: TestScript;
  };
  mockHttpServer: MockServer;
}

const setupStageExecutionFixtures = async (): Promise<StageExecutionFixtures> => {
  const project = await createTestProject();
  const pipeline = await createTestPipeline(project.id);
  
  return {
    project,
    pipeline,
    promptTemplate: await createTestPrompt(project.id, 'Generate HTML page'),
    validationScripts: {
      golang: await createTestScript(project.id, 'GOLANG', validateHTMLScript),
      python: await createTestScript(project.id, 'PYTHON', extractDataScript),
      typescript: await createTestScript(project.id, 'TYPESCRIPT', transformScript),
    },
    mockHttpServer: await createMockServer(),
  };
};
```

---

## Test Suites

### Suite 1: PROMPT Stage

#### TC-EXEC-001: Execute Prompt Stage

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Create pipeline with PROMPT stage
2. Configure with template and model
3. Execute stage

**Expected Results:**
- AI model called with resolved prompt
- Response stored in output variable
- Execution logged with token count

```typescript
test('execute prompt stage successfully', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'PROMPT',
    config: {
      promptTemplateId: fixtures.promptTemplate.id,
      model: 'gemini-3-flash-preview',
      temperature: 0.7,
      outputVariable: 'htmlOutput'
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  await expect(page.locator('[data-testid="stage-status"]')).toHaveText('SUCCESS');
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(execution.output).toBeTruthy();
  expect(execution.tokensUsed).toBeGreaterThan(0);
  expect(execution.model).toBe('gemini-3-flash-preview');
});
```

---

#### TC-EXEC-002: Prompt Stage with Variable Interpolation

**Priority:** Critical  
**Type:** E2E  

```typescript
test('prompt stage resolves input variables', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'PROMPT',
    config: {
      promptTemplateId: fixtures.promptTemplate.id,
      inputBindings: {
        topic: '{{global.topic}}',
        style: '{{global.style}}'
      }
    }
  });
  
  await executePipelineWithVariables(page, {
    'global.topic': 'React Components',
    'global.style': 'modern, minimal'
  });
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(execution.resolvedPrompt).toContain('React Components');
  expect(execution.resolvedPrompt).toContain('modern, minimal');
});
```

---

### Suite 2: SEARCH Stage

#### TC-EXEC-010: Execute Search Stage

**Priority:** High  
**Type:** E2E  

```typescript
test('execute search stage', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'SEARCH',
    config: {
      query: 'React best practices 2026',
      maxResults: 5,
      minConfidence: 0.7,
      outputVariable: 'searchResults'
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  await expect(page.locator('[data-testid="stage-status"]')).toHaveText('SUCCESS');
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  const results = JSON.parse(execution.output);
  
  expect(results).toBeInstanceOf(Array);
  expect(results.length).toBeLessThanOrEqual(5);
  results.forEach(r => {
    expect(r.confidence).toBeGreaterThanOrEqual(0.7);
    expect(r.title).toBeTruthy();
    expect(r.url).toBeTruthy();
  });
});
```

---

#### TC-EXEC-011: Search with Domain Filtering

**Priority:** Medium  
**Type:** E2E  

```typescript
test('search respects domain filters', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'SEARCH',
    config: {
      query: 'TypeScript tutorials',
      sources: ['github.com', 'stackoverflow.com'],
      excludeDomains: ['w3schools.com'],
      outputVariable: 'results'
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  const results = JSON.parse(execution.output);
  
  results.forEach(r => {
    expect(r.url).toMatch(/github\.com|stackoverflow\.com/);
    expect(r.url).not.toContain('w3schools.com');
  });
});
```

---

### Suite 3: VALIDATION Stage

#### TC-EXEC-020: Execute Golang Validation

**Priority:** Critical  
**Type:** E2E  

```typescript
test('execute golang validation script', async ({ page }) => {
  await createPipelineWithStages(page, fixtures.pipeline.id, [
    {
      type: 'PROMPT',
      config: { outputVariable: 'htmlContent' }
    },
    {
      type: 'VALIDATION',
      config: {
        scriptId: fixtures.validationScripts.golang.id,
        targetVariable: '{{prev.htmlContent}}',
        onFailure: 'STOP'
      }
    }
  ]);
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(execution.validationResult).toBeDefined();
  expect(execution.validationResult.passed).toBe(true);
});
```

---

#### TC-EXEC-021: Execute Python Validation

**Priority:** High  
**Type:** E2E  

```typescript
test('execute python validation script', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'VALIDATION',
    config: {
      scriptId: fixtures.validationScripts.python.id,
      targetVariable: '{{global.dataToValidate}}',
      outputVariable: 'validationResult'
    }
  });
  
  await executePipelineWithVariables(page, {
    'global.dataToValidate': { items: [1, 2, 3], valid: true }
  });
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(execution.status).toBe('SUCCESS');
});
```

---

#### TC-EXEC-022: Execute TypeScript/Bun Validation

**Priority:** High  
**Type:** E2E  

```typescript
test('execute typescript validation script', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'VALIDATION',
    config: {
      scriptId: fixtures.validationScripts.typescript.id,
      targetVariable: '{{prev.output}}'
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  await expect(page.locator('[data-testid="stage-status"]')).toHaveText('SUCCESS');
});
```

---

#### TC-EXEC-023: Validation Failure Handling

**Priority:** Critical  
**Type:** E2E  

```typescript
test('validation failure triggers configured action', async ({ page }) => {
  const failingScript = await createTestScript(fixtures.project.id, 'GOLANG', `
    func validate(input string) (bool, string) {
      return false, "Content does not meet requirements"
    }
  `);
  
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'VALIDATION',
    config: {
      scriptId: failingScript.id,
      onFailure: 'BRANCH',
      failureBranchId: 'error-handler-block'
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  // Verify branched to error handler
  const executions = await getBlockExecutions(fixtures.pipeline.id);
  expect(executions.find(e => e.blockId === 'error-handler-block')).toBeDefined();
});
```

---

### Suite 4: TRANSFORM Stage

#### TC-EXEC-030: JSON Parse Transform

**Priority:** High  
**Type:** E2E  

```typescript
test('transform stage parses JSON', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'TRANSFORM',
    config: {
      transformType: 'JSON_PARSE',
      inputVariable: '{{prev.jsonString}}',
      outputVariable: 'parsedData'
    }
  });
  
  await executePipelineWithVariables(page, {
    'prev.jsonString': '{"name": "Test", "count": 42}'
  });
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(JSON.parse(execution.output)).toEqual({ name: 'Test', count: 42 });
});
```

---

#### TC-EXEC-031: JSONPath Extraction

**Priority:** High  
**Type:** E2E  

```typescript
test('transform stage extracts with JSONPath', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'TRANSFORM',
    config: {
      transformType: 'REGEX_EXTRACT',
      expression: '$.data.items[*].name',
      inputVariable: '{{prev.response}}',
      outputVariable: 'names'
    }
  });
  
  await executePipelineWithVariables(page, {
    'prev.response': {
      data: {
        items: [
          { name: 'Item A', id: 1 },
          { name: 'Item B', id: 2 }
        ]
      }
    }
  });
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(JSON.parse(execution.output)).toEqual(['Item A', 'Item B']);
});
```

---

### Suite 5: HTTP Stage

#### TC-EXEC-040: HTTP GET Request

**Priority:** High  
**Type:** E2E  

```typescript
test('http stage makes GET request', async ({ page }) => {
  fixtures.mockHttpServer.get('/api/data', (req, res) => {
    res.json({ success: true, data: [1, 2, 3] });
  });
  
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'HTTP',
    config: {
      url: `${fixtures.mockHttpServer.url}/api/data`,
      method: 'GET',
      expectedStatus: [200],
      outputVariable: 'apiResponse'
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(JSON.parse(execution.output)).toEqual({ success: true, data: [1, 2, 3] });
});
```

---

#### TC-EXEC-041: HTTP POST with Body

**Priority:** High  
**Type:** E2E  

```typescript
test('http stage sends POST with body', async ({ page }) => {
  let receivedBody: unknown;
  fixtures.mockHttpServer.post('/api/submit', (req, res) => {
    receivedBody = req.body;
    res.status(201).json({ id: 'new-123' });
  });
  
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'HTTP',
    config: {
      url: `${fixtures.mockHttpServer.url}/api/submit`,
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: '{{prev.dataToSubmit}}',
      expectedStatus: [201]
    }
  });
  
  await executePipelineWithVariables(page, {
    'prev.dataToSubmit': { name: 'Test Item', value: 100 }
  });
  
  expect(receivedBody).toEqual({ name: 'Test Item', value: 100 });
});
```

---

#### TC-EXEC-042: HTTP with Authentication Header

**Priority:** High  
**Type:** E2E  

```typescript
test('http stage includes auth header from secrets', async ({ page }) => {
  let receivedAuth: string | undefined;
  fixtures.mockHttpServer.get('/api/secure', (req, res) => {
    receivedAuth = req.headers.authorization;
    res.json({ authorized: true });
  });
  
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'HTTP',
    config: {
      url: `${fixtures.mockHttpServer.url}/api/secure`,
      method: 'GET',
      headers: { 'Authorization': 'Bearer {{secrets.api_key}}' }
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  expect(receivedAuth).toBe('Bearer test-api-key-123');
});
```

---

### Suite 6: FILE_OP Stage

#### TC-EXEC-050: File Write Operation

**Priority:** High  
**Type:** E2E  

```typescript
test('file_op stage writes file', async ({ page }) => {
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'FILE_OP',
    config: {
      operation: 'WRITE',
      destinationPath: 'output/result.txt',
      content: '{{prev.generatedContent}}',
      encoding: 'utf-8'
    }
  });
  
  await executePipelineWithVariables(page, {
    'prev.generatedContent': 'Hello, World!'
  });
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(execution.status).toBe('SUCCESS');
  
  const fileContent = await readProjectFile(fixtures.project.id, 'output/result.txt');
  expect(fileContent).toBe('Hello, World!');
});
```

---

#### TC-EXEC-051: File Read Operation

**Priority:** High  
**Type:** E2E  

```typescript
test('file_op stage reads file', async ({ page }) => {
  await writeProjectFile(fixtures.project.id, 'input/data.json', '{"key": "value"}');
  
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'FILE_OP',
    config: {
      operation: 'READ',
      sourcePath: 'input/data.json',
      outputVariable: 'fileContent'
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(execution.output).toBe('{"key": "value"}');
});
```

---

### Suite 7: Timeout and Retry

#### TC-EXEC-060: Stage Timeout

**Priority:** Critical  
**Type:** E2E  

```typescript
test('stage times out after configured duration', async ({ page }) => {
  fixtures.mockHttpServer.get('/api/slow', async (req, res) => {
    await delay(10000); // 10 second delay
    res.json({ data: 'finally' });
  });
  
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'HTTP',
    config: {
      url: `${fixtures.mockHttpServer.url}/api/slow`,
      method: 'GET'
    },
    timeoutSeconds: 2
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page, { expectFailure: true });
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(execution.status).toBe('FAILED');
  expect(execution.errorCode).toBe('TIMEOUT');
});
```

---

#### TC-EXEC-061: Automatic Retry on Failure

**Priority:** Critical  
**Type:** E2E  

```typescript
test('stage retries on transient failure', async ({ page }) => {
  let callCount = 0;
  fixtures.mockHttpServer.get('/api/flaky', (req, res) => {
    callCount++;
    if (callCount < 3) {
      res.status(500).json({ error: 'Server Error' });
    } else {
      res.json({ success: true });
    }
  });
  
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'HTTP',
    config: {
      url: `${fixtures.mockHttpServer.url}/api/flaky`,
      method: 'GET',
      expectedStatus: [200]
    },
    retryConfig: {
      maxRetries: 3,
      backoffMs: 100,
      retryOn: ['500']
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page);
  
  expect(callCount).toBe(3);
  
  const execution = await getLastStageExecution(fixtures.pipeline.id);
  expect(execution.status).toBe('SUCCESS');
  expect(execution.attemptNumber).toBe(3);
});
```

---

#### TC-EXEC-062: Retry with Exponential Backoff

**Priority:** High  
**Type:** E2E  

```typescript
test('retry uses exponential backoff', async ({ page }) => {
  const callTimes: number[] = [];
  fixtures.mockHttpServer.get('/api/fail', (req, res) => {
    callTimes.push(Date.now());
    res.status(500).json({ error: 'Error' });
  });
  
  await createPipelineWithStage(page, fixtures.pipeline.id, {
    type: 'HTTP',
    config: { url: `${fixtures.mockHttpServer.url}/api/fail` },
    retryConfig: {
      maxRetries: 3,
      backoffMs: 100,
      backoffMultiplier: 2
    }
  });
  
  await page.click('[data-testid="run-pipeline"]');
  await waitForExecution(page, { expectFailure: true });
  
  // Verify exponential delays: 100ms, 200ms, 400ms
  const delays = callTimes.slice(1).map((t, i) => t - callTimes[i]);
  expect(delays[0]).toBeGreaterThanOrEqual(90);
  expect(delays[1]).toBeGreaterThanOrEqual(180);
});
```

---

## Performance Benchmarks

| Stage Type | Target | Max |
|------------|--------|-----|
| PROMPT (small) | <2s | 10s |
| SEARCH | <3s | 10s |
| VALIDATION (Go) | <500ms | 2s |
| VALIDATION (Python) | <1s | 3s |
| VALIDATION (TS) | <500ms | 2s |
| TRANSFORM | <100ms | 500ms |
| HTTP (local) | <200ms | 1s |
| FILE_OP | <100ms | 500ms |

---

## Error Codes

| Code | Description | Retryable |
|------|-------------|-----------|
| TIMEOUT | Stage exceeded timeout | Yes |
| VALIDATION_FAILED | Validation script returned false | Configurable |
| MODEL_ERROR | AI model returned error | Yes |
| NETWORK_ERROR | HTTP request failed | Yes |
| SCRIPT_ERROR | Validation script crashed | No |
| PARSE_ERROR | Output parsing failed | No |
| PERMISSION_DENIED | File access denied | No |
