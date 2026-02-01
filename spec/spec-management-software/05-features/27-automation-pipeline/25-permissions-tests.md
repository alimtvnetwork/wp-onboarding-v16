# Permissions System - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 8 - Governance & Collaboration  

---

## Overview

End-to-end test specifications for the RBAC-based permissions system, covering role assignments, access control enforcement, and privilege escalation prevention.

**Cross-References:**
- [Permissions System](./22-permissions.md)
- [Testing Strategy](../20-testing/00-overview.md)

---

## Test Environment Setup

### Database Fixtures

```typescript
interface PermissionsTestFixtures {
  users: {
    owner: TestUser;
    admin: TestUser;
    editor: TestUser;
    executor: TestUser;
    viewer: TestUser;
    unauthorized: TestUser;
  };
  pipeline: TestPipeline;
  workspace: TestWorkspace;
}

const setupPermissionsFixtures = async (): Promise<PermissionsTestFixtures> => {
  const workspace = await createTestWorkspace();
  const pipeline = await createTestPipeline(workspace.id);
  
  return {
    users: {
      owner: await createUserWithRole(pipeline.id, 'OWNER'),
      admin: await createUserWithRole(pipeline.id, 'ADMIN'),
      editor: await createUserWithRole(pipeline.id, 'EDITOR'),
      executor: await createUserWithRole(pipeline.id, 'EXECUTOR'),
      viewer: await createUserWithRole(pipeline.id, 'VIEWER'),
      unauthorized: await createTestUser(), // No role assigned
    },
    pipeline,
    workspace,
  };
};
```

---

## Test Suites

### Suite 1: Role Assignment

#### TC-PERM-001: Owner Assigns Admin Role

**Priority:** Critical  
**Type:** E2E  

**Preconditions:**
- Owner user authenticated
- Pipeline exists with owner permission

**Steps:**
1. Navigate to pipeline settings
2. Open "Permissions" tab
3. Click "Add Member"
4. Search for target user email
5. Select "Admin" role from dropdown
6. Click "Assign Role"

**Expected Results:**
- Success toast: "Role assigned successfully"
- User appears in members list with Admin badge
- `PipelinePermission` record created in database
- Audit log entry recorded

**Playwright Script:**
```typescript
test('owner can assign admin role', async ({ page }) => {
  await loginAs(fixtures.users.owner);
  await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  
  await page.click('[data-testid="permissions-tab"]');
  await page.click('[data-testid="add-member-btn"]');
  await page.fill('[data-testid="user-search"]', 'newadmin@test.com');
  await page.click('[data-testid="user-option-newadmin"]');
  await page.selectOption('[data-testid="role-select"]', 'ADMIN');
  await page.click('[data-testid="assign-role-btn"]');
  
  await expect(page.locator('.toast-success')).toContainText('Role assigned');
  await expect(page.locator('[data-testid="member-list"]'))
    .toContainText('newadmin@test.com');
});
```

---

#### TC-PERM-002: Non-Owner Cannot Assign Owner Role

**Priority:** Critical  
**Type:** Security  

**Preconditions:**
- Admin user authenticated
- Pipeline exists

**Steps:**
1. Navigate to pipeline permissions
2. Attempt to assign OWNER role to another user

**Expected Results:**
- "Owner" option not visible in role dropdown
- API returns 403 if role injection attempted
- No database changes occur

**Playwright Script:**
```typescript
test('admin cannot assign owner role', async ({ page }) => {
  await loginAs(fixtures.users.admin);
  await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  
  await page.click('[data-testid="permissions-tab"]');
  await page.click('[data-testid="add-member-btn"]');
  
  const roleOptions = await page.locator('[data-testid="role-select"] option').allTextContents();
  expect(roleOptions).not.toContain('Owner');
  
  // Attempt API bypass
  const response = await page.request.post(`/api/pipelines/${fixtures.pipeline.id}/permissions`, {
    data: { userId: 'target-id', role: 'OWNER' }
  });
  expect(response.status()).toBe(403);
});
```

---

#### TC-PERM-003: Role Hierarchy Enforcement

**Priority:** High  
**Type:** E2E  

**Test Matrix:**

| Actor Role | Target Role | Can Assign | Can Revoke |
|------------|-------------|------------|------------|
| OWNER | ADMIN | ✓ | ✓ |
| OWNER | EDITOR | ✓ | ✓ |
| ADMIN | EDITOR | ✓ | ✓ |
| ADMIN | ADMIN | ✗ | ✗ |
| EDITOR | VIEWER | ✗ | ✗ |
| VIEWER | Any | ✗ | ✗ |

**Playwright Script:**
```typescript
test.describe('role hierarchy enforcement', () => {
  const testCases = [
    { actor: 'admin', target: 'EDITOR', canAssign: true },
    { actor: 'admin', target: 'ADMIN', canAssign: false },
    { actor: 'editor', target: 'VIEWER', canAssign: false },
  ];
  
  for (const tc of testCases) {
    test(`${tc.actor} ${tc.canAssign ? 'can' : 'cannot'} assign ${tc.target}`, async ({ page }) => {
      await loginAs(fixtures.users[tc.actor]);
      await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
      
      if (tc.canAssign) {
        await expect(page.locator('[data-testid="add-member-btn"]')).toBeVisible();
      } else {
        await expect(page.locator('[data-testid="add-member-btn"]')).toBeHidden();
      }
    });
  }
});
```

---

### Suite 2: Access Control Enforcement

#### TC-PERM-010: Viewer Cannot Edit Pipeline

**Priority:** Critical  
**Type:** Security  

**Preconditions:**
- Viewer user authenticated
- Pipeline with stages exists

**Steps:**
1. Navigate to pipeline editor
2. Attempt to drag a new stage onto canvas
3. Attempt to modify stage configuration
4. Attempt to delete existing stage

**Expected Results:**
- Canvas in read-only mode
- Drag operations disabled
- Edit controls hidden or disabled
- Context menu shows only "View Details"

**Playwright Script:**
```typescript
test('viewer cannot edit pipeline', async ({ page }) => {
  await loginAs(fixtures.users.viewer);
  await page.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  // Verify read-only mode indicator
  await expect(page.locator('[data-testid="readonly-badge"]')).toBeVisible();
  
  // Attempt drag (should not work)
  const stage = page.locator('[data-testid="stage-prompt"]');
  const canvas = page.locator('[data-testid="pipeline-canvas"]');
  await stage.dragTo(canvas);
  
  // Verify no new node created
  const nodes = await page.locator('.react-flow__node').count();
  expect(nodes).toBe(fixtures.pipeline.stageCount);
  
  // Verify edit button hidden
  await page.click('[data-testid="existing-stage"]', { button: 'right' });
  await expect(page.locator('[data-testid="edit-stage"]')).toBeHidden();
  await expect(page.locator('[data-testid="delete-stage"]')).toBeHidden();
});
```

---

#### TC-PERM-011: Executor Can Run But Not Edit

**Priority:** High  
**Type:** E2E  

**Preconditions:**
- Executor user authenticated
- Runnable pipeline exists

**Steps:**
1. Navigate to pipeline
2. Verify "Run" button is enabled
3. Execute pipeline
4. Attempt to modify stage
5. Verify modification blocked

**Expected Results:**
- Run button visible and functional
- Execution completes successfully
- Edit operations blocked
- "Upgrade to Editor" prompt shown on edit attempt

**Playwright Script:**
```typescript
test('executor can run but not edit', async ({ page }) => {
  await loginAs(fixtures.users.executor);
  await page.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  // Can run
  await expect(page.locator('[data-testid="run-pipeline-btn"]')).toBeEnabled();
  await page.click('[data-testid="run-pipeline-btn"]');
  await expect(page.locator('[data-testid="execution-status"]')).toContainText('Running');
  
  // Cannot edit
  await page.dblclick('[data-testid="existing-stage"]');
  await expect(page.locator('[data-testid="upgrade-prompt"]'))
    .toContainText('Editor access required');
});
```

---

#### TC-PERM-012: Unauthorized User Access Denied

**Priority:** Critical  
**Type:** Security  

**Preconditions:**
- User authenticated but has no pipeline permissions
- Private pipeline exists

**Steps:**
1. Attempt direct URL navigation to pipeline
2. Attempt API access to pipeline data

**Expected Results:**
- 403 Forbidden page displayed
- No pipeline data leaked in response
- Access attempt logged

**Playwright Script:**
```typescript
test('unauthorized user cannot access private pipeline', async ({ page }) => {
  await loginAs(fixtures.users.unauthorized);
  
  // Direct navigation blocked
  await page.goto(`/pipelines/${fixtures.pipeline.id}`);
  await expect(page.locator('[data-testid="access-denied"]')).toBeVisible();
  await expect(page.locator('[data-testid="pipeline-canvas"]')).toBeHidden();
  
  // API access blocked
  const response = await page.request.get(`/api/pipelines/${fixtures.pipeline.id}`);
  expect(response.status()).toBe(403);
  
  const body = await response.json();
  expect(body.stages).toBeUndefined();
  expect(body.error).toBe('ACCESS_DENIED');
});
```

---

### Suite 3: Privilege Escalation Prevention

#### TC-PERM-020: Cannot Self-Elevate Role

**Priority:** Critical  
**Type:** Security  

**Steps:**
1. Authenticate as Editor
2. Attempt to modify own permission record via API
3. Attempt SQL injection in role parameter

**Expected Results:**
- API rejects self-modification
- Role remains unchanged
- Security event logged

**Playwright Script:**
```typescript
test('user cannot self-elevate role', async ({ page }) => {
  await loginAs(fixtures.users.editor);
  
  // Direct API attempt
  const response = await page.request.patch(
    `/api/pipelines/${fixtures.pipeline.id}/permissions/${fixtures.users.editor.permissionId}`,
    { data: { role: 'OWNER' } }
  );
  expect(response.status()).toBe(403);
  
  // Verify role unchanged
  const permission = await getPermission(fixtures.users.editor.permissionId);
  expect(permission.role).toBe('EDITOR');
});
```

---

#### TC-PERM-021: RLS Prevents Direct Database Bypass

**Priority:** Critical  
**Type:** Security  

**Test Method:** Direct Supabase client test

```typescript
test('RLS blocks direct permission manipulation', async () => {
  const editorClient = createSupabaseClient(fixtures.users.editor.jwt);
  
  // Attempt direct insert
  const { error: insertError } = await editorClient
    .from('pipeline_permissions')
    .insert({
      pipeline_id: fixtures.pipeline.id,
      user_id: fixtures.users.editor.id,
      role: 'OWNER'
    });
  
  expect(insertError?.code).toBe('42501'); // RLS violation
  
  // Attempt direct update
  const { error: updateError } = await editorClient
    .from('pipeline_permissions')
    .update({ role: 'ADMIN' })
    .eq('user_id', fixtures.users.editor.id);
  
  expect(updateError?.code).toBe('42501');
});
```

---

### Suite 4: Role Transitions

#### TC-PERM-030: Graceful Permission Downgrade

**Priority:** High  
**Type:** E2E  

**Preconditions:**
- User has Editor role
- User has pipeline open in editor

**Steps:**
1. Owner downgrades user to Viewer (in separate session)
2. Original user performs edit action

**Expected Results:**
- Real-time notification of permission change
- UI transitions to read-only mode
- Pending changes prompted for save/discard
- Subsequent edits blocked

**Playwright Script:**
```typescript
test('graceful permission downgrade', async ({ browser }) => {
  const editorContext = await browser.newContext();
  const ownerContext = await browser.newContext();
  
  const editorPage = await editorContext.newPage();
  const ownerPage = await ownerContext.newPage();
  
  // Editor opens pipeline
  await loginAs(fixtures.users.editor, editorPage);
  await editorPage.goto(`/pipelines/${fixtures.pipeline.id}`);
  
  // Owner downgrades
  await loginAs(fixtures.users.owner, ownerPage);
  await ownerPage.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  await ownerPage.click(`[data-testid="member-${fixtures.users.editor.id}"]`);
  await ownerPage.selectOption('[data-testid="role-select"]', 'VIEWER');
  await ownerPage.click('[data-testid="save-role-btn"]');
  
  // Editor receives notification
  await expect(editorPage.locator('[data-testid="permission-change-toast"]'))
    .toContainText('Your access has been changed to Viewer');
  
  // Editor UI becomes read-only
  await expect(editorPage.locator('[data-testid="readonly-badge"]')).toBeVisible();
});
```

---

## Performance Benchmarks

| Operation | Target | Measurement |
|-----------|--------|-------------|
| Permission check (has_role) | <5ms | Database query time |
| Role assignment | <200ms | API response time |
| Permission list load | <100ms | 100 members |
| Real-time permission sync | <500ms | WebSocket propagation |

---

## Security Checklist

- [ ] No role stored in JWT claims (always query database)
- [ ] SECURITY DEFINER functions use explicit search_path
- [ ] Audit log captures all permission changes
- [ ] Rate limiting on permission modification APIs
- [ ] No permission data in client-side storage
