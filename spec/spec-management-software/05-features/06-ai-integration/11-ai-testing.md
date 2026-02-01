# 6.11 AI Testing Strategy

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Comprehensive testing strategy for AI integration components including LLM interactions, instruction pipeline, prompt presets, and knowledge retrieval systems.

**Cross-References:**
- [Testing Strategy](../20-testing/01-test-strategy.md) - General testing approach
- [AI Integration](./01-ai-integration.md) - AI system overview
- [Instruction System](./03-instruction-system.md) - Pipeline under test
- [LLM Live Logging](./06-llm-live-logging.md) - Streaming tests
- [Knowledge Memory](../09-knowledge-memory/00-overview.md) - RAG testing

---

## 6.11.1 AI Testing Pyramid

```
           ╱╲
          ╱  ╲
         ╱ E2E ╲           ~5%   (Full AI workflows)
        ╱────────╲
       ╱Integration╲       ~25%  (LLM API, RAG retrieval)
      ╱──────────────╲
     ╱   Unit Tests    ╲   ~70%  (Prompts, parsers, utilities)
    ╱────────────────────╲
```

---

## 6.11.2 Unit Testing

### Prompt Template Tests

```typescript
// prompts/promptBuilder.test.ts
import { describe, it, expect } from 'vitest';
import { buildPrompt, interpolateVariables } from './promptBuilder';

describe('promptBuilder', () => {
  describe('interpolateVariables', () => {
    it('replaces all template variables', () => {
      const template = 'Generate a {{type}} spec for {{projectName}}';
      const vars = { type: 'feature', projectName: 'MyApp' };
      
      expect(interpolateVariables(template, vars))
        .toBe('Generate a feature spec for MyApp');
    });

    it('throws on missing required variables', () => {
      const template = 'Hello {{name}}, your role is {{role}}';
      const vars = { name: 'Alice' };
      
      expect(() => interpolateVariables(template, vars, { strict: true }))
        .toThrow('Missing required variable: role');
    });

    it('preserves unmatched variables in non-strict mode', () => {
      const template = 'Hello {{name}}, your role is {{role}}';
      const vars = { name: 'Alice' };
      
      expect(interpolateVariables(template, vars, { strict: false }))
        .toBe('Hello Alice, your role is {{role}}');
    });
  });

  describe('buildPrompt', () => {
    it('combines system and user prompts', () => {
      const result = buildPrompt({
        systemPrompt: 'You are a spec writer.',
        userPrompt: 'Create a login feature spec.',
        context: { projectName: 'TestApp' },
      });
      
      expect(result.system).toContain('spec writer');
      expect(result.user).toContain('login feature');
    });
  });
});
```

### Response Parser Tests

```typescript
// parsers/llmResponseParser.test.ts
import { describe, it, expect } from 'vitest';
import { parseStructuredResponse, extractCodeBlocks } from './llmResponseParser';

describe('llmResponseParser', () => {
  describe('parseStructuredResponse', () => {
    it('extracts JSON from markdown code blocks', () => {
      const response = `
Here is the result:
\`\`\`json
{"tasks": [{"id": "1", "name": "Setup auth"}]}
\`\`\`
      `;
      
      const result = parseStructuredResponse(response);
      expect(result.tasks).toHaveLength(1);
      expect(result.tasks[0].name).toBe('Setup auth');
    });

    it('handles malformed JSON gracefully', () => {
      const response = '```json\n{invalid json}\n```';
      
      expect(() => parseStructuredResponse(response))
        .toThrow('Failed to parse LLM response as JSON');
    });
  });

  describe('extractCodeBlocks', () => {
    it('extracts multiple code blocks with languages', () => {
      const response = `
\`\`\`typescript
const x = 1;
\`\`\`
Some text
\`\`\`python
x = 1
\`\`\`
      `;
      
      const blocks = extractCodeBlocks(response);
      expect(blocks).toHaveLength(2);
      expect(blocks[0]).toEqual({ language: 'typescript', code: 'const x = 1;' });
      expect(blocks[1]).toEqual({ language: 'python', code: 'x = 1' });
    });
  });
});
```

### Instruction Task Tests

```typescript
// instructions/taskDependencyResolver.test.ts
import { describe, it, expect } from 'vitest';
import { resolveDependencyOrder, detectCycles } from './taskDependencyResolver';

describe('taskDependencyResolver', () => {
  it('returns tasks in topological order', () => {
    const tasks = [
      { id: 'A', dependsOn: [] },
      { id: 'B', dependsOn: ['A'] },
      { id: 'C', dependsOn: ['A', 'B'] },
    ];
    
    const order = resolveDependencyOrder(tasks);
    expect(order.map(t => t.id)).toEqual(['A', 'B', 'C']);
  });

  it('detects circular dependencies', () => {
    const tasks = [
      { id: 'A', dependsOn: ['C'] },
      { id: 'B', dependsOn: ['A'] },
      { id: 'C', dependsOn: ['B'] },
    ];
    
    expect(() => detectCycles(tasks))
      .toThrow('Circular dependency detected: A → C → B → A');
  });

  it('handles parallel-eligible tasks', () => {
    const tasks = [
      { id: 'A', dependsOn: [] },
      { id: 'B', dependsOn: [] },
      { id: 'C', dependsOn: ['A', 'B'] },
    ];
    
    const order = resolveDependencyOrder(tasks);
    // A and B can be parallel, C must come after both
    const cIndex = order.findIndex(t => t.id === 'C');
    expect(cIndex).toBe(2);
  });
});
```

---

## 6.11.3 Integration Testing

### LLM API Mock Setup

```typescript
// mocks/llmHandlers.ts
import { http, HttpResponse, delay } from 'msw';

export const llmHandlers = [
  // Mock completion endpoint
  http.post('/api/ai/complete', async ({ request }) => {
    const body = await request.json();
    
    await delay(100); // Simulate latency
    
    return HttpResponse.json({
      id: 'completion-123',
      content: `Generated response for: ${body.prompt.substring(0, 50)}...`,
      model: body.model || 'thinking-model',
      usage: {
        promptTokens: 150,
        completionTokens: 200,
        totalTokens: 350,
      },
    });
  }),

  // Mock streaming endpoint
  http.post('/api/ai/stream', async ({ request }) => {
    const body = await request.json();
    const encoder = new TextEncoder();
    
    const stream = new ReadableStream({
      async start(controller) {
        const chunks = ['Hello', ' ', 'World', '!'];
        for (const chunk of chunks) {
          await delay(50);
          controller.enqueue(encoder.encode(`data: ${JSON.stringify({ content: chunk })}\n\n`));
        }
        controller.enqueue(encoder.encode('data: [DONE]\n\n'));
        controller.close();
      },
    });
    
    return new HttpResponse(stream, {
      headers: { 'Content-Type': 'text/event-stream' },
    });
  }),

  // Mock instruction generation
  http.post('/api/instructions/generate', async ({ request }) => {
    const body = await request.json();
    
    await delay(200);
    
    return HttpResponse.json({
      instructionId: 'instr-456',
      tasks: [
        { id: 'task-1', name: 'Analyze requirements', status: 'pending' },
        { id: 'task-2', name: 'Generate spec outline', status: 'pending', dependsOn: ['task-1'] },
        { id: 'task-3', name: 'Write detailed spec', status: 'pending', dependsOn: ['task-2'] },
      ],
      estimatedDuration: 30,
    });
  }),
];
```

### Instruction Pipeline Tests

```typescript
// instructions/instructionPipeline.integration.test.ts
import { describe, it, expect, beforeAll, afterAll, afterEach } from 'vitest';
import { setupServer } from 'msw/node';
import { llmHandlers } from '../mocks/llmHandlers';
import { InstructionPipeline } from './InstructionPipeline';

const server = setupServer(...llmHandlers);

describe('InstructionPipeline Integration', () => {
  beforeAll(() => server.listen());
  afterEach(() => server.resetHandlers());
  afterAll(() => server.close());

  it('executes full instruction generation pipeline', async () => {
    const pipeline = new InstructionPipeline();
    
    const result = await pipeline.generate({
      input: 'Create a user authentication system',
      projectId: 'proj-123',
      presetId: 'feature',
    });
    
    expect(result.instructionId).toBeDefined();
    expect(result.tasks).toHaveLength(3);
    expect(result.tasks[0].name).toBe('Analyze requirements');
  });

  it('handles LLM timeout gracefully', async () => {
    server.use(
      http.post('/api/ai/complete', async () => {
        await delay(10000); // Exceed timeout
        return HttpResponse.json({});
      })
    );
    
    const pipeline = new InstructionPipeline({ timeout: 1000 });
    
    await expect(pipeline.generate({ input: 'test', projectId: 'proj-123' }))
      .rejects.toThrow('LLM request timed out');
  });

  it('retries on transient failures', async () => {
    let attempts = 0;
    server.use(
      http.post('/api/ai/complete', () => {
        attempts++;
        if (attempts < 3) {
          return HttpResponse.json({ error: 'Service unavailable' }, { status: 503 });
        }
        return HttpResponse.json({ content: 'Success after retry' });
      })
    );
    
    const pipeline = new InstructionPipeline({ maxRetries: 3 });
    const result = await pipeline.generate({ input: 'test', projectId: 'proj-123' });
    
    expect(attempts).toBe(3);
    expect(result).toBeDefined();
  });
});
```

### Knowledge Retrieval Tests

```typescript
// knowledge/knowledgeRetriever.integration.test.ts
import { describe, it, expect, beforeEach } from 'vitest';
import { KnowledgeRetriever } from './KnowledgeRetriever';
import { createTestVectorStore } from '../test/factories/vectorStore';

describe('KnowledgeRetriever Integration', () => {
  let retriever: KnowledgeRetriever;
  let vectorStore: TestVectorStore;

  beforeEach(async () => {
    vectorStore = await createTestVectorStore();
    
    // Seed test data
    await vectorStore.addDocuments([
      { id: 'doc-1', content: 'Authentication uses JWT tokens', source: 'auth-spec.md' },
      { id: 'doc-2', content: 'Database uses PostgreSQL', source: 'db-spec.md' },
      { id: 'doc-3', content: 'API follows REST conventions', source: 'api-spec.md' },
    ]);
    
    retriever = new KnowledgeRetriever(vectorStore);
  });

  it('retrieves relevant documents for query', async () => {
    const results = await retriever.search('How does authentication work?');
    
    expect(results).toHaveLength(3);
    expect(results[0].source).toBe('auth-spec.md');
    expect(results[0].score).toBeGreaterThan(0.8);
  });

  it('respects topK limit', async () => {
    const results = await retriever.search('system architecture', { topK: 2 });
    
    expect(results).toHaveLength(2);
  });

  it('filters by source type', async () => {
    const results = await retriever.search('configuration', {
      filter: { sourceType: 'spec' },
    });
    
    results.forEach(r => {
      expect(r.source).toMatch(/\.md$/);
    });
  });

  it('performs hybrid search with keyword boost', async () => {
    const results = await retriever.search('JWT authentication tokens', {
      hybridWeight: 0.7, // 70% semantic, 30% keyword
    });
    
    expect(results[0].content).toContain('JWT');
  });
});
```

---

## 6.11.4 Streaming Tests

```typescript
// streaming/llmStream.test.ts
import { describe, it, expect, vi } from 'vitest';
import { LLMStreamHandler } from './LLMStreamHandler';
import { createMockWebSocket } from '../test/mocks/websocket';

describe('LLMStreamHandler', () => {
  it('emits tokens as they arrive', async () => {
    const onToken = vi.fn();
    const handler = new LLMStreamHandler({ onToken });
    
    const mockWs = createMockWebSocket();
    handler.connect(mockWs);
    
    // Simulate incoming stream
    mockWs.emit('message', JSON.stringify({ type: 'token', content: 'Hello' }));
    mockWs.emit('message', JSON.stringify({ type: 'token', content: ' World' }));
    mockWs.emit('message', JSON.stringify({ type: 'complete' }));
    
    expect(onToken).toHaveBeenCalledTimes(2);
    expect(onToken).toHaveBeenNthCalledWith(1, 'Hello');
    expect(onToken).toHaveBeenNthCalledWith(2, ' World');
  });

  it('accumulates full response', async () => {
    const handler = new LLMStreamHandler();
    const mockWs = createMockWebSocket();
    handler.connect(mockWs);
    
    mockWs.emit('message', JSON.stringify({ type: 'token', content: 'Hello' }));
    mockWs.emit('message', JSON.stringify({ type: 'token', content: ' World' }));
    mockWs.emit('message', JSON.stringify({ type: 'complete' }));
    
    expect(handler.getFullResponse()).toBe('Hello World');
  });

  it('handles connection errors', async () => {
    const onError = vi.fn();
    const handler = new LLMStreamHandler({ onError });
    
    const mockWs = createMockWebSocket();
    handler.connect(mockWs);
    
    mockWs.emit('error', new Error('Connection lost'));
    
    expect(onError).toHaveBeenCalledWith(expect.any(Error));
    expect(handler.getStatus()).toBe('error');
  });

  it('supports cancellation', async () => {
    const handler = new LLMStreamHandler();
    const mockWs = createMockWebSocket();
    handler.connect(mockWs);
    
    mockWs.emit('message', JSON.stringify({ type: 'token', content: 'Hello' }));
    handler.cancel();
    mockWs.emit('message', JSON.stringify({ type: 'token', content: ' World' }));
    
    expect(handler.getFullResponse()).toBe('Hello');
    expect(handler.getStatus()).toBe('cancelled');
  });
});
```

---

## 6.11.5 E2E AI Workflow Tests

```typescript
// e2e/ai-workflow.spec.ts
import { test, expect } from '@playwright/test';

test.describe('AI Instruction Generation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.click('text=Test Project');
  });

  test('generates instruction from text input', async ({ page }) => {
    // Open AI panel
    await page.click('[data-testid="ai-panel-toggle"]');
    
    // Enter instruction
    await page.fill('[data-testid="instruction-input"]', 
      'Create a user registration feature with email verification');
    
    // Select preset
    await page.click('[data-testid="preset-selector"]');
    await page.click('text=Feature Spec');
    
    // Generate
    await page.click('[data-testid="generate-button"]');
    
    // Wait for generation (with timeout)
    await expect(page.locator('[data-testid="generation-status"]'))
      .toHaveText('Complete', { timeout: 60000 });
    
    // Verify output
    await expect(page.locator('[data-testid="generated-tasks"]'))
      .toContainText('registration');
    
    // Check artifact was created
    await page.click('text=View Artifacts');
    await expect(page.locator('.artifact-list'))
      .toContainText('user-registration');
  });

  test('handles clarification questions', async ({ page }) => {
    await page.click('[data-testid="ai-panel-toggle"]');
    await page.fill('[data-testid="instruction-input"]', 
      'Add authentication'); // Ambiguous input
    await page.click('[data-testid="generate-button"]');
    
    // Should show clarification questions
    await expect(page.locator('[data-testid="clarification-dialog"]'))
      .toBeVisible({ timeout: 30000 });
    
    // Answer questions
    await page.click('text=Email/Password');
    await page.click('text=Yes, include 2FA');
    await page.click('[data-testid="submit-answers"]');
    
    // Should continue generation
    await expect(page.locator('[data-testid="generation-status"]'))
      .toHaveText('Complete', { timeout: 60000 });
  });

  test('displays streaming LLM output', async ({ page }) => {
    await page.click('[data-testid="ai-panel-toggle"]');
    await page.click('[data-testid="show-live-log"]');
    
    await page.fill('[data-testid="instruction-input"]', 'Create a simple API');
    await page.click('[data-testid="generate-button"]');
    
    // Verify streaming content appears
    const logPanel = page.locator('[data-testid="llm-log-panel"]');
    await expect(logPanel).toContainText('Analyzing', { timeout: 10000 });
    
    // Content should grow over time
    const initialLength = await logPanel.textContent();
    await page.waitForTimeout(2000);
    const laterLength = await logPanel.textContent();
    
    expect(laterLength!.length).toBeGreaterThan(initialLength!.length);
  });
});
```

---

## 6.11.6 Test Data Factories

```typescript
// test/factories/aiFactories.ts
import { faker } from '@faker-js/faker';

export const createMockInstruction = (overrides = {}) => ({
  id: faker.string.uuid(),
  input: faker.lorem.sentence(),
  projectId: faker.string.uuid(),
  presetId: faker.helpers.arrayElement(['idea', 'feature', 'task', 'instruction']),
  status: 'pending',
  createdAt: faker.date.recent(),
  ...overrides,
});

export const createMockTask = (overrides = {}) => ({
  id: faker.string.uuid(),
  instructionId: faker.string.uuid(),
  name: faker.lorem.words(3),
  description: faker.lorem.sentence(),
  status: faker.helpers.arrayElement(['pending', 'running', 'complete', 'failed']),
  dependsOn: [],
  output: null,
  ...overrides,
});

export const createMockLLMResponse = (overrides = {}) => ({
  id: faker.string.uuid(),
  content: faker.lorem.paragraphs(2),
  model: 'thinking-model',
  usage: {
    promptTokens: faker.number.int({ min: 100, max: 500 }),
    completionTokens: faker.number.int({ min: 50, max: 300 }),
    totalTokens: faker.number.int({ min: 150, max: 800 }),
  },
  finishReason: 'stop',
  ...overrides,
});

export const createMockKnowledgeChunk = (overrides = {}) => ({
  id: faker.string.uuid(),
  content: faker.lorem.paragraph(),
  source: `${faker.system.fileName()}.md`,
  sourceType: faker.helpers.arrayElement(['spec', 'url']),
  embedding: Array.from({ length: 384 }, () => faker.number.float({ min: -1, max: 1 })),
  metadata: {
    title: faker.lorem.words(3),
    section: faker.lorem.word(),
  },
  ...overrides,
});
```

---

## 6.11.7 Coverage Targets

| Component | Target | Minimum |
|-----------|--------|---------|
| Prompt utilities | 95% | 90% |
| Response parsers | 95% | 90% |
| Task dependency resolver | 90% | 85% |
| Instruction pipeline | 80% | 70% |
| Knowledge retriever | 80% | 70% |
| Streaming handlers | 75% | 65% |
| E2E workflows | 100% critical paths | 80% |

---

## 6.11.8 CI/CD Integration

```yaml
# .github/workflows/ai-tests.yml
name: AI Integration Tests

on: [push, pull_request]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: oven-sh/setup-bun@v1
      - run: bun install
      - run: bun test src/ai --coverage
      - uses: codecov/codecov-action@v3

  integration-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: oven-sh/setup-bun@v1
      - run: bun install
      - run: bun test src/ai/**/*.integration.test.ts
    timeout-minutes: 10

  e2e-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: oven-sh/setup-bun@v1
      - run: bun install
      - run: bunx playwright install --with-deps
      - run: bun run build
      - run: bunx playwright test e2e/ai-*.spec.ts
    timeout-minutes: 15
```

---

## Related Specs

- [Testing Strategy](../20-testing/01-test-strategy.md)
- [AI Integration Overview](./01-ai-integration.md)
- [Instruction System](./03-instruction-system.md)
- [LLM Live Logging](./06-llm-live-logging.md)
- [Knowledge Memory System](../09-knowledge-memory/09-knowledge-memory-system.md)
