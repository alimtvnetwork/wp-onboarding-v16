# Pipeline Creation - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 1-4 - Foundation through Canvas UI  

---

## Overview

End-to-end test specifications for pipeline creation workflow, covering canvas interactions, block/stage management, and pipeline persistence.

**Cross-References:**
- [React Flow Canvas](./10-react-flow-canvas.md)
- [Stage Node Components](./11-stage-nodes.md)
- [Database Schema](./01-database-schema.md)

---

## Test Environment Setup

```typescript
interface PipelineCreationFixtures {
  project: TestProject;
  promptTemplates: TestPrompt[];
  validationScripts: TestScript[];
}

const setupPipelineCreationFixtures = async (): Promise<PipelineCreationFixtures> => {
  const project = await createTestProject();
  
  return {
    project,
    promptTemplates: await createTestPrompts(project.id, 5),
    validationScripts: await createTestScripts(project.id, 3),
  };
};
```

---

## Test Suites

### Suite 1: Canvas Initialization

#### TC-CREATE-001: Open Empty Canvas

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Navigate to Pipelines
2. Click "Create Pipeline"
3. Observe canvas state

**Expected Results:**
- Empty canvas rendered
- Block palette visible
- Toolbar accessible
- "Untitled Pipeline" as default name

```typescript
test('open empty canvas', async ({ page }) => {
  await page.goto(`/projects/${fixtures.project.id}/pipelines`);
  await page.click('[data-testid="create-pipeline-btn"]');
  
  await expect(page.locator('[data-testid="pipeline-canvas"]')).toBeVisible();
  await expect(page.locator('[data-testid="block-palette"]')).toBeVisible();
  await expect(page.locator('[data-testid="canvas-toolbar"]')).toBeVisible();
  await expect(page.locator('[data-testid="pipeline-name-input"]')).toHaveValue('Untitled Pipeline');
});
```

---

#### TC-CREATE-002: Rename Pipeline

**Priority:** High  
**Type:** E2E  

```typescript
test('rename pipeline', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  await page.click('[data-testid="pipeline-name-input"]');
  await page.fill('[data-testid="pipeline-name-input"]', 'My Test Pipeline');
  await page.press('[data-testid="pipeline-name-input"]', 'Enter');
  
  await expect(page.locator('.toast-success')).toContainText('Pipeline renamed');
  
  // Verify persistence
  await page.reload();
  await expect(page.locator('[data-testid="pipeline-name-input"]')).toHaveValue('My Test Pipeline');
});
```

---

### Suite 2: Block Management

#### TC-CREATE-010: Add Block from Palette

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Open empty canvas
2. Drag block from palette to canvas
3. Observe block creation

**Expected Results:**
- Block appears at drop position
- Block has default name "Block 1"
- Block is selected
- Config panel opens

```typescript
test('add block from palette', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  const palette = page.locator('[data-testid="palette-block"]');
  const canvas = page.locator('[data-testid="pipeline-canvas"]');
  
  await palette.dragTo(canvas, { targetPosition: { x: 300, y: 200 } });
  
  await expect(page.locator('.react-flow__node')).toHaveCount(1);
  await expect(page.locator('[data-testid="block-name"]')).toContainText('Block 1');
  await expect(page.locator('[data-testid="block-config-panel"]')).toBeVisible();
});
```

---

#### TC-CREATE-011: Add Multiple Blocks

**Priority:** High  
**Type:** E2E  

```typescript
test('add multiple blocks with auto-numbering', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  // Add 3 blocks
  for (let i = 0; i < 3; i++) {
    await page.locator('[data-testid="palette-block"]').dragTo(
      page.locator('[data-testid="pipeline-canvas"]'),
      { targetPosition: { x: 200 + i * 250, y: 200 } }
    );
  }
  
  await expect(page.locator('.react-flow__node')).toHaveCount(3);
  await expect(page.locator('[data-testid="block-name"]').nth(0)).toContainText('Block 1');
  await expect(page.locator('[data-testid="block-name"]').nth(1)).toContainText('Block 2');
  await expect(page.locator('[data-testid="block-name"]').nth(2)).toContainText('Block 3');
});
```

---

#### TC-CREATE-012: Delete Block

**Priority:** High  
**Type:** E2E  

```typescript
test('delete block with confirmation', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockToCanvas(page);
  
  await page.click('.react-flow__node', { button: 'right' });
  await page.click('[data-testid="delete-block"]');
  
  await expect(page.locator('[data-testid="confirm-delete-dialog"]')).toBeVisible();
  await page.click('[data-testid="confirm-delete"]');
  
  await expect(page.locator('.react-flow__node')).toHaveCount(0);
});
```

---

#### TC-CREATE-013: Undo/Redo Block Operations

**Priority:** High  
**Type:** E2E  

```typescript
test('undo and redo block operations', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockToCanvas(page);
  
  await expect(page.locator('.react-flow__node')).toHaveCount(1);
  
  // Undo
  await page.keyboard.press('Control+z');
  await expect(page.locator('.react-flow__node')).toHaveCount(0);
  
  // Redo
  await page.keyboard.press('Control+Shift+z');
  await expect(page.locator('.react-flow__node')).toHaveCount(1);
});
```

---

### Suite 3: Stage Management

#### TC-CREATE-020: Add Stage to Block

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Create block
2. Click "Add Stage" in block
3. Select stage type
4. Configure stage

**Expected Results:**
- Stage appears inside block
- Stage type icon displayed
- Config panel shows type-specific options

```typescript
test('add stage to block', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockToCanvas(page);
  
  await page.click('[data-testid="add-stage-btn"]');
  await page.click('[data-testid="stage-type-prompt"]');
  
  await expect(page.locator('[data-testid="stage-node"]')).toHaveCount(1);
  await expect(page.locator('[data-testid="stage-config-panel"]')).toBeVisible();
  await expect(page.locator('[data-testid="prompt-template-select"]')).toBeVisible();
});
```

---

#### TC-CREATE-021: Configure Prompt Stage

**Priority:** Critical  
**Type:** E2E  

```typescript
test('configure prompt stage with template', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockToCanvas(page);
  await addStageToBlock(page, 'PROMPT');
  
  // Select template
  await page.click('[data-testid="prompt-template-select"]');
  await page.click(`[data-testid="template-${fixtures.promptTemplates[0].id}"]`);
  
  // Configure model
  await page.selectOption('[data-testid="model-select"]', 'gemini-3-flash-preview');
  
  // Set output variable
  await page.fill('[data-testid="output-variable"]', 'generatedHTML');
  
  await page.click('[data-testid="save-stage"]');
  
  await expect(page.locator('.toast-success')).toContainText('Stage saved');
});
```

---

#### TC-CREATE-022: Configure All Stage Types

**Priority:** High  
**Type:** E2E  

**Test Matrix:**

| Stage Type | Key Configuration | Validation |
|------------|-------------------|------------|
| PROMPT | Template, Model, Temperature | Template required |
| CODE_GEN | Language, Output Path | Path format validated |
| SEARCH | Query, Max Results | Query required |
| VALIDATION | Script, Target Variable | Script required |
| TRANSFORM | Transform Type, Expression | Expression syntax |
| HTTP | URL, Method, Headers | Valid URL required |
| FILE_OP | Operation, Source/Dest Path | Paths required |

```typescript
test.describe('configure all stage types', () => {
  const stageTypes = ['PROMPT', 'CODE_GEN', 'SEARCH', 'VALIDATION', 'TRANSFORM', 'HTTP', 'FILE_OP'];
  
  for (const stageType of stageTypes) {
    test(`configure ${stageType} stage`, async ({ page }) => {
      await createNewPipeline(page, fixtures.project.id);
      await addBlockToCanvas(page);
      await addStageToBlock(page, stageType);
      
      await configureStage(page, stageType, getDefaultConfig(stageType));
      await page.click('[data-testid="save-stage"]');
      
      await expect(page.locator('.toast-success')).toBeVisible();
    });
  }
});
```

---

#### TC-CREATE-023: Reorder Stages Within Block

**Priority:** Medium  
**Type:** E2E  

```typescript
test('reorder stages via drag', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockToCanvas(page);
  
  // Add 3 stages
  await addStageToBlock(page, 'SEARCH');
  await addStageToBlock(page, 'PROMPT');
  await addStageToBlock(page, 'VALIDATION');
  
  // Drag stage 3 to position 1
  const stage3 = page.locator('[data-testid="stage-node"]').nth(2);
  const stage1 = page.locator('[data-testid="stage-node"]').nth(0);
  
  await stage3.dragTo(stage1);
  
  // Verify order
  const stages = await page.locator('[data-testid="stage-type"]').allTextContents();
  expect(stages).toEqual(['VALIDATION', 'SEARCH', 'PROMPT']);
});
```

---

### Suite 4: Connection Wiring

#### TC-CREATE-030: Connect Two Blocks

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Create two blocks
2. Drag from output handle of block 1
3. Drop on input handle of block 2

**Expected Results:**
- Edge created between blocks
- Connection animates on creation
- Edge is selectable

```typescript
test('connect two blocks', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockToCanvas(page, { x: 200, y: 200 });
  await addBlockToCanvas(page, { x: 500, y: 200 });
  
  const sourceHandle = page.locator('[data-testid="block-output-handle"]').first();
  const targetHandle = page.locator('[data-testid="block-input-handle"]').last();
  
  await sourceHandle.dragTo(targetHandle);
  
  await expect(page.locator('.react-flow__edge')).toHaveCount(1);
});
```

---

#### TC-CREATE-031: Prevent Cycle Creation

**Priority:** High  
**Type:** Validation  

```typescript
test('prevent cycle creation', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  // Create A -> B -> C chain
  await addBlockToCanvas(page, { x: 200, y: 200, name: 'A' });
  await addBlockToCanvas(page, { x: 400, y: 200, name: 'B' });
  await addBlockToCanvas(page, { x: 600, y: 200, name: 'C' });
  
  await connectBlocks(page, 'A', 'B');
  await connectBlocks(page, 'B', 'C');
  
  // Attempt to create C -> A (cycle)
  const sourceHandle = page.locator('[data-testid="block-C"] [data-testid="block-output-handle"]');
  const targetHandle = page.locator('[data-testid="block-A"] [data-testid="block-input-handle"]');
  
  await sourceHandle.dragTo(targetHandle);
  
  await expect(page.locator('[data-testid="cycle-error"]')).toContainText('would create a cycle');
  await expect(page.locator('.react-flow__edge')).toHaveCount(2); // No new edge
});
```

---

#### TC-CREATE-032: Configure Connection Mapping

**Priority:** High  
**Type:** E2E  

```typescript
test('configure output mapping on connection', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockWithStage(page, 'PROMPT', { x: 200, y: 200, outputVar: 'result' });
  await addBlockWithStage(page, 'VALIDATION', { x: 500, y: 200 });
  
  await connectBlocks(page, 'Block 1', 'Block 2');
  
  // Open mapping editor
  await page.click('.react-flow__edge');
  await page.click('[data-testid="edit-mapping"]');
  
  await page.fill('[data-testid="source-variable"]', 'result');
  await page.fill('[data-testid="target-variable"]', 'inputContent');
  await page.click('[data-testid="save-mapping"]');
  
  await expect(page.locator('[data-testid="mapping-badge"]')).toContainText('1 mapping');
});
```

---

### Suite 5: Pipeline Persistence

#### TC-CREATE-040: Auto-Save Pipeline

**Priority:** Critical  
**Type:** E2E  

```typescript
test('auto-save pipeline changes', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockToCanvas(page);
  
  // Wait for auto-save
  await page.waitForSelector('[data-testid="save-status-saved"]', { timeout: 3000 });
  
  // Reload and verify
  await page.reload();
  await expect(page.locator('.react-flow__node')).toHaveCount(1);
});
```

---

#### TC-CREATE-041: Manual Save with Keyboard

**Priority:** Medium  
**Type:** E2E  

```typescript
test('manual save with Ctrl+S', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  await addBlockToCanvas(page);
  
  await page.keyboard.press('Control+s');
  
  await expect(page.locator('[data-testid="save-status-saved"]')).toBeVisible();
  await expect(page.locator('.toast-success')).toContainText('Pipeline saved');
});
```

---

#### TC-CREATE-042: Load Existing Pipeline

**Priority:** Critical  
**Type:** E2E  

```typescript
test('load existing pipeline preserves all state', async ({ page }) => {
  // Create and save pipeline
  const pipelineId = await createComplexPipeline(page, fixtures.project.id);
  
  // Navigate away and back
  await page.goto(`/projects/${fixtures.project.id}/pipelines`);
  await page.click(`[data-testid="pipeline-${pipelineId}"]`);
  
  // Verify state restored
  await expect(page.locator('.react-flow__node')).toHaveCount(3);
  await expect(page.locator('.react-flow__edge')).toHaveCount(2);
  await expect(page.locator('[data-testid="stage-node"]')).toHaveCount(5);
});
```

---

### Suite 6: Canvas Interactions

#### TC-CREATE-050: Pan and Zoom

**Priority:** Medium  
**Type:** E2E  

```typescript
test('pan and zoom canvas', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  // Zoom in
  await page.click('[data-testid="zoom-in-btn"]');
  const zoomLevel = await page.locator('[data-testid="zoom-level"]').textContent();
  expect(parseFloat(zoomLevel!)).toBeGreaterThan(100);
  
  // Zoom out
  await page.click('[data-testid="zoom-out-btn"]');
  
  // Fit view
  await page.click('[data-testid="fit-view-btn"]');
});
```

---

#### TC-CREATE-051: Multi-Select and Bulk Delete

**Priority:** Medium  
**Type:** E2E  

```typescript
test('multi-select and bulk delete', async ({ page }) => {
  await createNewPipeline(page, fixtures.project.id);
  
  // Add 3 blocks
  for (let i = 0; i < 3; i++) {
    await addBlockToCanvas(page, { x: 200 + i * 200, y: 200 });
  }
  
  // Box select all
  await page.locator('[data-testid="pipeline-canvas"]').click({ position: { x: 50, y: 50 } });
  await page.mouse.down();
  await page.mouse.move(800, 400);
  await page.mouse.up();
  
  // Delete all
  await page.keyboard.press('Delete');
  await page.click('[data-testid="confirm-delete"]');
  
  await expect(page.locator('.react-flow__node')).toHaveCount(0);
});
```

---

## Performance Benchmarks

| Operation | Target | Max |
|-----------|--------|-----|
| Canvas initial load | <500ms | 1s |
| Add block | <100ms | 200ms |
| Add stage | <100ms | 200ms |
| Create connection | <50ms | 100ms |
| Auto-save | <500ms | 1s |
| Load 50-node pipeline | <2s | 5s |

---

## Accessibility Tests

| Test | Requirement |
|------|-------------|
| Keyboard navigation | All actions via keyboard |
| Screen reader | Nodes announce type/name |
| Focus management | Visible focus indicators |
| Color contrast | WCAG AA compliant |
