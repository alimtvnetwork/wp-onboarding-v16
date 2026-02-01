# Collaboration System - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 8 - Governance & Collaboration  

---

## Overview

End-to-end test specifications for real-time multi-user collaboration features including presence awareness, concurrent editing, and conflict resolution.

**Cross-References:**
- [Collaboration System](./24-collaboration.md)
- [Permissions System](./22-permissions.md)
- [Testing Strategy](../20-testing/00-overview.md)

---

## Test Environment Setup

### Multi-User Test Fixtures

```typescript
interface CollaborationTestFixtures {
  pipeline: TestPipeline;
  users: {
    editor1: TestUser;
    editor2: TestUser;
    editor3: TestUser;
    viewer: TestUser;
  };
  initialStages: TestStage[];
}

const setupCollaborationFixtures = async (): Promise<CollaborationTestFixtures> => {
  const pipeline = await createTestPipeline();
  
  return {
    pipeline,
    users: {
      editor1: await createUserWithRole(pipeline.id, 'EDITOR'),
      editor2: await createUserWithRole(pipeline.id, 'EDITOR'),
      editor3: await createUserWithRole(pipeline.id, 'EDITOR'),
      viewer: await createUserWithRole(pipeline.id, 'VIEWER'),
    },
    initialStages: await createTestStages(pipeline.id, 3),
  };
};
```

### WebSocket Test Utilities

```typescript
const waitForPresenceUpdate = async (page: Page, expectedCount: number) => {
  await page.waitForFunction(
    (count) => document.querySelectorAll('[data-testid="presence-avatar"]').length === count,
    expectedCount,
    { timeout: 5000 }
  );
};

const waitForSync = async (page: Page) => {
  await page.waitForSelector('[data-testid="sync-status"][data-synced="true"]');
};
```

---

## Test Suites

### Suite 1: Presence Awareness

#### TC-COLLAB-001: See Other Users Online

**Priority:** High  
**Type:** E2E  

**Preconditions:**
- Pipeline exists with multiple editors
- All users have edit permissions

**Steps:**
1. Editor1 opens pipeline
2. Editor2 opens same pipeline
3. Editor3 opens same pipeline
4. Verify presence indicators

**Expected Results:**
- Each user sees other users' avatars
- Presence count updates in real-time
- User names shown on hover
- Online status indicators visible

**Playwright Script:**
```typescript
test('presence awareness shows all online users', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  const page3 = await createAuthenticatedPage(browser, fixtures.users.editor3);
  
  // Editor1 joins first
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await waitForPresenceUpdate(page1, 1);
  
  // Editor2 joins
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  await waitForPresenceUpdate(page1, 2);
  await waitForPresenceUpdate(page2, 2);
  
  // Editor3 joins
  await page3.goto(`/pipelines/${fixtures.pipeline.id}`);
  await waitForPresenceUpdate(page1, 3);
  await waitForPresenceUpdate(page2, 3);
  await waitForPresenceUpdate(page3, 3);
  
  // Verify avatars visible
  await expect(page1.locator('[data-testid="presence-bar"]')).toContainText('3 online');
});
```

---

#### TC-COLLAB-002: User Leaves Updates Presence

**Priority:** High  
**Type:** E2E  

**Steps:**
1. Three editors have pipeline open
2. Editor3 closes browser/navigates away
3. Verify presence updates for remaining users

**Expected Results:**
- Presence count decreases
- Departed user's avatar removed
- "User left" indicator briefly shown

**Playwright Script:**
```typescript
test('user leaving updates presence for others', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  const page3 = await createAuthenticatedPage(browser, fixtures.users.editor3);
  
  // All join
  await Promise.all([
    page1.goto(`/pipelines/${fixtures.pipeline.id}`),
    page2.goto(`/pipelines/${fixtures.pipeline.id}`),
    page3.goto(`/pipelines/${fixtures.pipeline.id}`),
  ]);
  
  await waitForPresenceUpdate(page1, 3);
  
  // Editor3 leaves
  await page3.close();
  
  // Others see departure
  await waitForPresenceUpdate(page1, 2);
  await waitForPresenceUpdate(page2, 2);
  
  await expect(page1.locator('[data-testid="user-left-toast"]'))
    .toContainText(fixtures.users.editor3.name);
});
```

---

#### TC-COLLAB-003: Cursor Position Tracking

**Priority:** Medium  
**Type:** E2E  

**Steps:**
1. Two editors have pipeline open
2. Editor1 hovers over Stage A
3. Verify Editor2 sees Editor1's cursor

**Expected Results:**
- Remote cursor visible on Editor2's screen
- Cursor labeled with Editor1's name
- Cursor position updates smoothly (60fps)

**Playwright Script:**
```typescript
test('cursor positions sync between users', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  await waitForPresenceUpdate(page1, 2);
  
  // Editor1 moves cursor to specific stage
  const stage = page1.locator(`[data-testid="stage-${fixtures.initialStages[0].id}"]`);
  await stage.hover();
  
  // Editor2 should see remote cursor
  const remoteCursor = page2.locator(`[data-testid="cursor-${fixtures.users.editor1.id}"]`);
  await expect(remoteCursor).toBeVisible();
  await expect(remoteCursor).toHaveAttribute('data-username', fixtures.users.editor1.name);
});
```

---

### Suite 2: Real-Time Editing

#### TC-COLLAB-010: Stage Addition Syncs Instantly

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Two editors viewing same pipeline
2. Editor1 adds new stage
3. Verify Editor2 sees new stage

**Expected Results:**
- New stage appears on both canvases
- Position synchronized correctly
- No page refresh required
- Sync indicator shows activity

**Playwright Script:**
```typescript
test('new stage syncs to all users', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  await waitForPresenceUpdate(page1, 2);
  
  const initialCount = await page2.locator('.react-flow__node').count();
  
  // Editor1 adds stage
  await page1.click('[data-testid="add-stage-btn"]');
  await page1.click('[data-testid="stage-type-prompt"]');
  await page1.fill('[data-testid="stage-name"]', 'Collaborative Stage');
  await page1.click('[data-testid="save-stage"]');
  
  // Editor2 should see it
  await page2.waitForSelector('[data-testid="stage-Collaborative Stage"]');
  const newCount = await page2.locator('.react-flow__node').count();
  expect(newCount).toBe(initialCount + 1);
});
```

---

#### TC-COLLAB-011: Stage Property Edit Syncs

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Both editors viewing same stage
2. Editor1 modifies stage prompt text
3. Verify Editor2 sees updated content

**Expected Results:**
- Text changes appear in real-time
- Character-by-character updates visible
- No conflict if Editor2 viewing same stage

**Playwright Script:**
```typescript
test('stage property changes sync in real-time', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  const stageId = fixtures.initialStages[0].id;
  
  // Both open same stage
  await page1.dblclick(`[data-testid="stage-${stageId}"]`);
  await page2.dblclick(`[data-testid="stage-${stageId}"]`);
  
  // Editor1 types in prompt field
  await page1.fill('[data-testid="prompt-input"]', 'Updated by Editor 1');
  
  // Editor2 sees the change
  await expect(page2.locator('[data-testid="prompt-input"]'))
    .toHaveValue('Updated by Editor 1');
});
```

---

#### TC-COLLAB-012: Stage Deletion Syncs

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Two editors viewing pipeline
2. Editor1 deletes a stage
3. Verify Editor2 sees deletion

**Expected Results:**
- Stage removed from both canvases
- Connected edges also removed
- Undo available for deleting user
- "Stage deleted by [user]" notification for others

**Playwright Script:**
```typescript
test('stage deletion syncs with notification', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  const stageId = fixtures.initialStages[1].id;
  const initialCount = await page2.locator('.react-flow__node').count();
  
  // Editor1 deletes stage
  await page1.click(`[data-testid="stage-${stageId}"]`, { button: 'right' });
  await page1.click('[data-testid="delete-stage"]');
  await page1.click('[data-testid="confirm-delete"]');
  
  // Editor2 sees deletion
  await page2.waitForSelector(`[data-testid="stage-${stageId}"]`, { state: 'hidden' });
  const newCount = await page2.locator('.react-flow__node').count();
  expect(newCount).toBe(initialCount - 1);
  
  // Notification shown
  await expect(page2.locator('[data-testid="collab-notification"]'))
    .toContainText(`${fixtures.users.editor1.name} deleted`);
});
```

---

### Suite 3: Conflict Resolution

#### TC-COLLAB-020: Concurrent Edit Same Field

**Priority:** Critical  
**Type:** E2E  

**Preconditions:**
- Both editors have same stage's prompt field focused

**Steps:**
1. Editor1 types "Hello "
2. Editor2 types "World" simultaneously
3. Verify OT resolution

**Expected Results:**
- Both inputs merged correctly
- Final result: "Hello World" or "WorldHello " (OT dependent)
- No data loss
- Both users see same final state

**Playwright Script:**
```typescript
test('concurrent edits to same field merge via OT', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  const stageId = fixtures.initialStages[0].id;
  
  // Both open same stage
  await page1.dblclick(`[data-testid="stage-${stageId}"]`);
  await page2.dblclick(`[data-testid="stage-${stageId}"]`);
  
  // Clear existing content
  await page1.fill('[data-testid="prompt-input"]', '');
  await waitForSync(page2);
  
  // Simultaneous typing
  await Promise.all([
    page1.type('[data-testid="prompt-input"]', 'Hello '),
    page2.type('[data-testid="prompt-input"]', 'World'),
  ]);
  
  await waitForSync(page1);
  await waitForSync(page2);
  
  // Both should see merged result
  const value1 = await page1.inputValue('[data-testid="prompt-input"]');
  const value2 = await page2.inputValue('[data-testid="prompt-input"]');
  
  expect(value1).toBe(value2);
  expect(value1).toContain('Hello');
  expect(value1).toContain('World');
});
```

---

#### TC-COLLAB-021: Move Stage While Another Edits

**Priority:** High  
**Type:** E2E  

**Steps:**
1. Editor1 opens stage config panel
2. Editor2 drags the same stage to new position
3. Verify both operations succeed

**Expected Results:**
- Stage moves to new position
- Config panel remains open with correct data
- No conflicts or data corruption

**Playwright Script:**
```typescript
test('position change doesnt conflict with property edit', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  const stageId = fixtures.initialStages[0].id;
  const stage = page2.locator(`[data-testid="stage-${stageId}"]`);
  
  // Editor1 opens config
  await page1.dblclick(`[data-testid="stage-${stageId}"]`);
  await page1.fill('[data-testid="stage-name"]', 'Renamed Stage');
  
  // Editor2 drags stage simultaneously
  await stage.dragTo(page2.locator('[data-testid="canvas"]'), {
    targetPosition: { x: 500, y: 300 }
  });
  
  await waitForSync(page1);
  await waitForSync(page2);
  
  // Both changes should be preserved
  await expect(page2.locator(`[data-testid="stage-${stageId}"]`))
    .toContainText('Renamed Stage');
  
  // Position should be updated on both
  const pos1 = await page1.locator(`[data-testid="stage-${stageId}"]`).boundingBox();
  const pos2 = await page2.locator(`[data-testid="stage-${stageId}"]`).boundingBox();
  expect(pos1.x).toBeCloseTo(pos2.x, 10);
  expect(pos1.y).toBeCloseTo(pos2.y, 10);
});
```

---

#### TC-COLLAB-022: Delete Stage Being Edited

**Priority:** High  
**Type:** E2E  

**Steps:**
1. Editor1 has stage config panel open
2. Editor2 deletes that stage
3. Verify graceful handling

**Expected Results:**
- Config panel closes with warning
- "Stage was deleted by [user]" message
- No crash or undefined errors
- Editor1's pending changes discarded

**Playwright Script:**
```typescript
test('deleting stage closes remote config panel gracefully', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  const stageId = fixtures.initialStages[2].id;
  
  // Editor1 opens config
  await page1.dblclick(`[data-testid="stage-${stageId}"]`);
  await expect(page1.locator('[data-testid="stage-config-panel"]')).toBeVisible();
  
  // Editor2 deletes stage
  await page2.click(`[data-testid="stage-${stageId}"]`, { button: 'right' });
  await page2.click('[data-testid="delete-stage"]');
  await page2.click('[data-testid="confirm-delete"]');
  
  // Editor1's panel should close with warning
  await expect(page1.locator('[data-testid="stage-config-panel"]')).toBeHidden();
  await expect(page1.locator('[data-testid="stage-deleted-warning"]'))
    .toContainText('deleted by');
});
```

---

### Suite 4: Selection Awareness

#### TC-COLLAB-030: See Remote Selections

**Priority:** Medium  
**Type:** E2E  

**Steps:**
1. Two editors in same pipeline
2. Editor1 selects Stage A
3. Editor2 selects Stage B
4. Verify selection highlights visible to both

**Expected Results:**
- Editor1's selection highlighted in their color for Editor2
- Editor2's selection highlighted in their color for Editor1
- Selection labels show user names

**Playwright Script:**
```typescript
test('remote selections visible with user colors', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  await waitForPresenceUpdate(page1, 2);
  
  // Get assigned colors
  const editor1Color = await page1.getAttribute('[data-testid="my-presence"]', 'data-color');
  const editor2Color = await page2.getAttribute('[data-testid="my-presence"]', 'data-color');
  
  // Editor1 selects stage 0
  await page1.click(`[data-testid="stage-${fixtures.initialStages[0].id}"]`);
  
  // Editor2 selects stage 1
  await page2.click(`[data-testid="stage-${fixtures.initialStages[1].id}"]`);
  
  // Editor1 sees Editor2's selection
  const remoteSelection1 = page1.locator(`[data-testid="remote-selection-${fixtures.users.editor2.id}"]`);
  await expect(remoteSelection1).toBeVisible();
  await expect(remoteSelection1).toHaveCSS('border-color', editor2Color);
  
  // Editor2 sees Editor1's selection
  const remoteSelection2 = page2.locator(`[data-testid="remote-selection-${fixtures.users.editor1.id}"]`);
  await expect(remoteSelection2).toBeVisible();
});
```

---

#### TC-COLLAB-031: Multi-Select Sync

**Priority:** Medium  
**Type:** E2E  

**Steps:**
1. Editor1 box-selects multiple stages
2. Verify Editor2 sees multi-selection highlight

**Expected Results:**
- All selected stages highlighted for remote users
- Selection count indicator shows number selected

**Playwright Script:**
```typescript
test('multi-selection syncs to remote users', async ({ browser }) => {
  const page1 = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const page2 = await createAuthenticatedPage(browser, fixtures.users.editor2);
  
  await page1.goto(`/pipelines/${fixtures.pipeline.id}`);
  await page2.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  // Editor1 box-selects
  await page1.locator('[data-testid="canvas"]').click({ position: { x: 50, y: 50 } });
  await page1.mouse.down();
  await page1.mouse.move(800, 600);
  await page1.mouse.up();
  
  // Editor2 should see multiple remote selections
  const remoteSelections = page2.locator(`[data-testid^="remote-selection-${fixtures.users.editor1.id}"]`);
  await expect(remoteSelections).toHaveCount(fixtures.initialStages.length);
});
```

---

### Suite 5: Session Management

#### TC-COLLAB-040: Reconnection After Disconnect

**Priority:** High  
**Type:** E2E  

**Steps:**
1. Editor connected and editing
2. Simulate network disconnect
3. Make offline changes
4. Reconnect
5. Verify sync recovery

**Expected Results:**
- Offline indicator shown during disconnect
- Pending changes queued locally
- On reconnect, changes synced to server
- Conflicts resolved if any

**Playwright Script:**
```typescript
test('offline changes sync after reconnection', async ({ browser, context }) => {
  const page = await createAuthenticatedPage(browser, fixtures.users.editor1);
  await page.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  await waitForSync(page);
  
  // Simulate offline
  await context.setOffline(true);
  await expect(page.locator('[data-testid="offline-indicator"]')).toBeVisible();
  
  // Make changes while offline
  await page.dblclick(`[data-testid="stage-${fixtures.initialStages[0].id}"]`);
  await page.fill('[data-testid="stage-name"]', 'Offline Edit');
  await page.click('[data-testid="save-stage"]');
  
  // Pending indicator
  await expect(page.locator('[data-testid="pending-changes"]')).toContainText('1 pending');
  
  // Reconnect
  await context.setOffline(false);
  
  // Wait for sync
  await waitForSync(page);
  await expect(page.locator('[data-testid="offline-indicator"]')).toBeHidden();
  await expect(page.locator('[data-testid="pending-changes"]')).toBeHidden();
  
  // Verify change persisted
  await page.reload();
  await expect(page.locator(`[data-testid="stage-${fixtures.initialStages[0].id}"]`))
    .toContainText('Offline Edit');
});
```

---

#### TC-COLLAB-041: Session Timeout Handling

**Priority:** Medium  
**Type:** E2E  

**Steps:**
1. Editor idle for extended period
2. Session expires
3. Attempt edit

**Expected Results:**
- Session timeout warning at 25 min
- Auto-disconnect at 30 min idle
- Re-authentication prompt on edit attempt
- State preserved after re-auth

**Playwright Script:**
```typescript
test('session timeout prompts re-authentication', async ({ page }) => {
  await loginAs(fixtures.users.editor1, page);
  await page.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  // Fast-forward time (mock)
  await page.evaluate(() => {
    window.__mockIdleTime = 25 * 60 * 1000; // 25 minutes
  });
  
  // Trigger idle check
  await page.evaluate(() => window.dispatchEvent(new Event('idle-check')));
  
  // Warning shown
  await expect(page.locator('[data-testid="session-timeout-warning"]')).toBeVisible();
  
  // User takes action to stay connected
  await page.click('[data-testid="stay-connected-btn"]');
  await expect(page.locator('[data-testid="session-timeout-warning"]')).toBeHidden();
});
```

---

### Suite 6: Viewer Collaboration

#### TC-COLLAB-050: Viewer Sees Real-Time Updates

**Priority:** High  
**Type:** E2E  

**Steps:**
1. Viewer has pipeline open
2. Editor makes changes
3. Verify Viewer sees changes

**Expected Results:**
- All changes visible to Viewer in real-time
- Viewer cannot interact/edit
- Viewer sees editor cursors and selections

**Playwright Script:**
```typescript
test('viewer receives real-time updates from editors', async ({ browser }) => {
  const editorPage = await createAuthenticatedPage(browser, fixtures.users.editor1);
  const viewerPage = await createAuthenticatedPage(browser, fixtures.users.viewer);
  
  await editorPage.goto(`/pipelines/${fixtures.pipeline.id}`);
  await viewerPage.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  // Viewer is read-only
  await expect(viewerPage.locator('[data-testid="readonly-badge"]')).toBeVisible();
  
  // Editor adds stage
  await editorPage.click('[data-testid="add-stage-btn"]');
  await editorPage.click('[data-testid="stage-type-search"]');
  await editorPage.fill('[data-testid="stage-name"]', 'Viewer Test Stage');
  await editorPage.click('[data-testid="save-stage"]');
  
  // Viewer sees it
  await expect(viewerPage.locator('[data-testid="stage-Viewer Test Stage"]')).toBeVisible();
  
  // Viewer sees editor's cursor
  await expect(viewerPage.locator(`[data-testid="cursor-${fixtures.users.editor1.id}"]`))
    .toBeVisible();
});
```

---

## Performance Benchmarks

| Metric | Target | Test Method |
|--------|--------|-------------|
| Presence update latency | <100ms | WebSocket round-trip |
| Operation broadcast | <50ms | Edit to remote display |
| Cursor sync rate | 60fps | Smooth movement |
| Reconnection time | <3s | Network recovery |
| Max concurrent users | 50 | Load test |
| Memory per session | <5MB | Heap snapshot |

---

## Load Testing

### TC-COLLAB-100: 10 Concurrent Editors

**Priority:** High  
**Type:** Performance  

```typescript
test('10 concurrent editors maintain sync', async ({ browser }) => {
  const pages: Page[] = [];
  
  for (let i = 0; i < 10; i++) {
    const user = await createUserWithRole(fixtures.pipeline.id, 'EDITOR');
    const page = await createAuthenticatedPage(browser, user);
    await page.goto(`/pipelines/${fixtures.pipeline.id}`);
    pages.push(page);
  }
  
  // Wait for all to connect
  for (const page of pages) {
    await waitForPresenceUpdate(page, 10);
  }
  
  // Each makes a change
  for (let i = 0; i < pages.length; i++) {
    await pages[i].click('[data-testid="add-stage-btn"]');
    await pages[i].click('[data-testid="stage-type-prompt"]');
    await pages[i].fill('[data-testid="stage-name"]', `Stage from User ${i}`);
    await pages[i].click('[data-testid="save-stage"]');
  }
  
  // All should see all stages
  for (const page of pages) {
    await waitForSync(page);
    const stageCount = await page.locator('.react-flow__node').count();
    expect(stageCount).toBe(fixtures.initialStages.length + 10);
  }
});
```

---

## Security Checklist

- [ ] WebSocket connections authenticated via JWT
- [ ] Operations validated against user permissions server-side
- [ ] Rate limiting on operation broadcasts (100 ops/sec/user)
- [ ] Session tokens expire and require refresh
- [ ] No sensitive data in presence payloads
- [ ] OT operations validated for integrity
