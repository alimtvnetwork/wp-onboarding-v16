# Sharing System - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 8 - Governance & Collaboration  

---

## Overview

End-to-end test specifications for pipeline sharing features including share links, invitations, visibility settings, and access controls.

**Cross-References:**
- [Sharing System](./23-sharing.md)
- [Permissions System](./22-permissions.md)
- [Testing Strategy](../20-testing/00-overview.md)

---

## Test Environment Setup

### Database Fixtures

```typescript
interface SharingTestFixtures {
  owner: TestUser;
  invitee: TestUser;
  anonymousUser: null;
  pipeline: TestPipeline;
  expiredLink: TestShareLink;
  validLink: TestShareLink;
  passwordLink: TestShareLink;
}

const setupSharingFixtures = async (): Promise<SharingTestFixtures> => {
  const owner = await createTestUser();
  const pipeline = await createTestPipeline(owner.id);
  
  return {
    owner,
    invitee: await createTestUser(),
    anonymousUser: null,
    pipeline,
    expiredLink: await createShareLink(pipeline.id, {
      expiresAt: new Date(Date.now() - 86400000), // Yesterday
    }),
    validLink: await createShareLink(pipeline.id, {
      expiresAt: new Date(Date.now() + 86400000), // Tomorrow
    }),
    passwordLink: await createShareLink(pipeline.id, {
      password: 'TestPassword123!',
    }),
  };
};
```

---

## Test Suites

### Suite 1: Share Link Generation

#### TC-SHARE-001: Create Public Share Link

**Priority:** High  
**Type:** E2E  

**Preconditions:**
- Owner authenticated
- Pipeline exists

**Steps:**
1. Open pipeline settings
2. Navigate to "Sharing" tab
3. Toggle "Enable Public Link"
4. Copy generated link

**Expected Results:**
- Unique token generated (32 chars)
- Link displayed in copyable format
- `ShareLink` record created with default settings
- Link format: `{base_url}/shared/{token}`

**Playwright Script:**
```typescript
test('create public share link', async ({ page }) => {
  await loginAs(fixtures.owner);
  await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  
  await page.click('[data-testid="sharing-tab"]');
  await page.click('[data-testid="enable-public-link"]');
  
  await expect(page.locator('[data-testid="share-link-input"]')).toBeVisible();
  
  const linkValue = await page.inputValue('[data-testid="share-link-input"]');
  expect(linkValue).toMatch(/\/shared\/[a-zA-Z0-9]{32}$/);
  
  // Verify copy button works
  await page.click('[data-testid="copy-link-btn"]');
  await expect(page.locator('.toast-success')).toContainText('Link copied');
});
```

---

#### TC-SHARE-002: Configure Link Settings

**Priority:** High  
**Type:** E2E  

**Test Matrix:**

| Setting | Values | Validation |
|---------|--------|------------|
| Expiration | None, 1h, 24h, 7d, 30d, Custom | Date picker for custom |
| Password | None, Custom | Min 8 chars requirement |
| Max Uses | Unlimited, 1, 10, 100, Custom | Numeric validation |
| Access Level | View, Execute, Edit | Role dropdown |

**Playwright Script:**
```typescript
test('configure share link with password and expiration', async ({ page }) => {
  await loginAs(fixtures.owner);
  await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  await page.click('[data-testid="sharing-tab"]');
  
  // Enable link if not enabled
  if (!(await page.isChecked('[data-testid="enable-public-link"]'))) {
    await page.click('[data-testid="enable-public-link"]');
  }
  
  // Set expiration
  await page.click('[data-testid="link-expiration-select"]');
  await page.click('[data-testid="expiration-7d"]');
  
  // Set password
  await page.click('[data-testid="enable-password"]');
  await page.fill('[data-testid="link-password"]', 'SecurePass123!');
  
  // Set max uses
  await page.fill('[data-testid="max-uses"]', '50');
  
  // Save
  await page.click('[data-testid="save-link-settings"]');
  
  await expect(page.locator('.toast-success')).toContainText('Link settings updated');
  
  // Verify database
  const link = await getShareLink(fixtures.pipeline.id);
  expect(link.password_hash).toBeTruthy();
  expect(link.max_uses).toBe(50);
  expect(link.expires_at).toBeTruthy();
});
```

---

#### TC-SHARE-003: Regenerate Link Token

**Priority:** Medium  
**Type:** E2E  

**Preconditions:**
- Active share link exists

**Steps:**
1. Open sharing settings
2. Click "Regenerate Link"
3. Confirm in dialog

**Expected Results:**
- New token generated
- Old token invalidated immediately
- Usage count reset to 0
- Warning shown about old link breaking

**Playwright Script:**
```typescript
test('regenerate share link invalidates old token', async ({ page }) => {
  await loginAs(fixtures.owner);
  await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  await page.click('[data-testid="sharing-tab"]');
  
  const oldLink = await page.inputValue('[data-testid="share-link-input"]');
  
  await page.click('[data-testid="regenerate-link-btn"]');
  await page.click('[data-testid="confirm-regenerate"]');
  
  const newLink = await page.inputValue('[data-testid="share-link-input"]');
  expect(newLink).not.toBe(oldLink);
  
  // Verify old link no longer works
  await page.goto(oldLink);
  await expect(page.locator('[data-testid="link-invalid"]')).toBeVisible();
});
```

---

### Suite 2: Share Link Access

#### TC-SHARE-010: Access Valid Public Link

**Priority:** Critical  
**Type:** E2E  

**Preconditions:**
- Valid share link exists
- User not authenticated

**Steps:**
1. Navigate to share link URL
2. View shared pipeline

**Expected Results:**
- Pipeline canvas renders
- Read-only mode active
- User info shows "Anonymous Viewer"
- Usage count incremented

**Playwright Script:**
```typescript
test('anonymous user can access valid share link', async ({ page }) => {
  // No login - anonymous access
  await page.goto(`/shared/${fixtures.validLink.token}`);
  
  await expect(page.locator('[data-testid="pipeline-canvas"]')).toBeVisible();
  await expect(page.locator('[data-testid="readonly-badge"]')).toBeVisible();
  await expect(page.locator('[data-testid="user-avatar"]'))
    .toHaveAttribute('data-anonymous', 'true');
  
  // Verify usage count incremented
  const link = await getShareLink(fixtures.validLink.id);
  expect(link.use_count).toBeGreaterThan(fixtures.validLink.use_count);
});
```

---

#### TC-SHARE-011: Expired Link Rejected

**Priority:** Critical  
**Type:** Security  

**Preconditions:**
- Expired share link exists

**Steps:**
1. Navigate to expired link URL

**Expected Results:**
- "Link Expired" message displayed
- No pipeline data visible
- Option to request new access

**Playwright Script:**
```typescript
test('expired link shows expiration message', async ({ page }) => {
  await page.goto(`/shared/${fixtures.expiredLink.token}`);
  
  await expect(page.locator('[data-testid="link-expired"]')).toBeVisible();
  await expect(page.locator('[data-testid="pipeline-canvas"]')).toBeHidden();
  await expect(page.locator('[data-testid="request-access-btn"]')).toBeVisible();
});
```

---

#### TC-SHARE-012: Password-Protected Link

**Priority:** High  
**Type:** E2E  

**Preconditions:**
- Password-protected share link exists

**Steps:**
1. Navigate to password link URL
2. Enter incorrect password
3. Enter correct password

**Expected Results:**
- Password prompt displayed first
- Incorrect password shows error, rate limited after 5 attempts
- Correct password grants access
- Session remembers authentication for 24h

**Playwright Script:**
```typescript
test('password protected link requires authentication', async ({ page }) => {
  await page.goto(`/shared/${fixtures.passwordLink.token}`);
  
  // Password prompt shown
  await expect(page.locator('[data-testid="password-prompt"]')).toBeVisible();
  
  // Wrong password
  await page.fill('[data-testid="link-password-input"]', 'wrong');
  await page.click('[data-testid="submit-password"]');
  await expect(page.locator('[data-testid="password-error"]')).toBeVisible();
  
  // Correct password
  await page.fill('[data-testid="link-password-input"]', 'TestPassword123!');
  await page.click('[data-testid="submit-password"]');
  
  await expect(page.locator('[data-testid="pipeline-canvas"]')).toBeVisible();
  
  // Refresh retains access
  await page.reload();
  await expect(page.locator('[data-testid="password-prompt"]')).toBeHidden();
  await expect(page.locator('[data-testid="pipeline-canvas"]')).toBeVisible();
});
```

---

#### TC-SHARE-013: Max Uses Enforcement

**Priority:** High  
**Type:** E2E  

**Preconditions:**
- Share link with max_uses = 2
- Link used once

**Steps:**
1. Access link (use #2)
2. Access link again (use #3)

**Expected Results:**
- Second access succeeds
- Third access shows "Link limit reached"

**Playwright Script:**
```typescript
test('share link enforces max uses', async ({ browser }) => {
  const limitedLink = await createShareLink(fixtures.pipeline.id, { maxUses: 2 });
  
  // First use (already counted in setup)
  const page1 = await browser.newPage();
  await page1.goto(`/shared/${limitedLink.token}`);
  await expect(page1.locator('[data-testid="pipeline-canvas"]')).toBeVisible();
  
  // Second use - should work
  const page2 = await browser.newPage();
  await page2.goto(`/shared/${limitedLink.token}`);
  await expect(page2.locator('[data-testid="pipeline-canvas"]')).toBeVisible();
  
  // Third use - should fail
  const page3 = await browser.newPage();
  await page3.goto(`/shared/${limitedLink.token}`);
  await expect(page3.locator('[data-testid="link-limit-reached"]')).toBeVisible();
});
```

---

### Suite 3: Email Invitations

#### TC-SHARE-020: Send Invitation Email

**Priority:** High  
**Type:** E2E  

**Preconditions:**
- Owner authenticated
- Target email not already invited

**Steps:**
1. Open sharing settings
2. Enter invitee email
3. Select role
4. Add optional message
5. Send invitation

**Expected Results:**
- Success toast displayed
- `ShareInvitation` record created with PENDING status
- Email sent with unique invitation link
- Invitation appears in pending list

**Playwright Script:**
```typescript
test('send invitation email', async ({ page }) => {
  await loginAs(fixtures.owner);
  await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  await page.click('[data-testid="sharing-tab"]');
  
  await page.fill('[data-testid="invite-email"]', 'newuser@test.com');
  await page.selectOption('[data-testid="invite-role"]', 'EDITOR');
  await page.fill('[data-testid="invite-message"]', 'Please join my pipeline!');
  await page.click('[data-testid="send-invite-btn"]');
  
  await expect(page.locator('.toast-success')).toContainText('Invitation sent');
  await expect(page.locator('[data-testid="pending-invites"]'))
    .toContainText('newuser@test.com');
});
```

---

#### TC-SHARE-021: Accept Invitation

**Priority:** High  
**Type:** E2E  

**Preconditions:**
- Pending invitation exists
- Invitee has account

**Steps:**
1. Click invitation link from email
2. Login if not authenticated
3. Review invitation details
4. Accept invitation

**Expected Results:**
- Invitation details displayed
- On accept, `PipelinePermission` created
- Invitation status updated to ACCEPTED
- Redirected to pipeline

**Playwright Script:**
```typescript
test('accept invitation grants access', async ({ page }) => {
  const invitation = await createInvitation(fixtures.pipeline.id, fixtures.invitee.email, 'EDITOR');
  
  await page.goto(`/invitations/${invitation.token}`);
  
  // Login prompt if not authenticated
  await loginAs(fixtures.invitee, page);
  
  // Review invitation
  await expect(page.locator('[data-testid="invitation-details"]'))
    .toContainText(fixtures.pipeline.name);
  await expect(page.locator('[data-testid="invitation-role"]'))
    .toContainText('Editor');
  
  await page.click('[data-testid="accept-invite-btn"]');
  
  // Redirected to pipeline
  await expect(page).toHaveURL(new RegExp(`/pipelines/${fixtures.pipeline.id}`));
  
  // Verify permission created
  const permission = await getPermission(fixtures.invitee.id, fixtures.pipeline.id);
  expect(permission.role).toBe('EDITOR');
});
```

---

#### TC-SHARE-022: Decline Invitation

**Priority:** Medium  
**Type:** E2E  

**Steps:**
1. Access invitation link
2. Click "Decline"
3. Optionally provide reason

**Expected Results:**
- Invitation status updated to DECLINED
- No permission created
- Owner notified of decline

**Playwright Script:**
```typescript
test('decline invitation', async ({ page }) => {
  const invitation = await createInvitation(fixtures.pipeline.id, fixtures.invitee.email, 'EDITOR');
  
  await loginAs(fixtures.invitee);
  await page.goto(`/invitations/${invitation.token}`);
  
  await page.click('[data-testid="decline-invite-btn"]');
  await page.fill('[data-testid="decline-reason"]', 'Not interested at this time');
  await page.click('[data-testid="confirm-decline"]');
  
  await expect(page.locator('[data-testid="invite-declined-message"]')).toBeVisible();
  
  // Verify no permission
  const permission = await getPermission(fixtures.invitee.id, fixtures.pipeline.id);
  expect(permission).toBeNull();
});
```

---

### Suite 4: Visibility Settings

#### TC-SHARE-030: Set Pipeline to Public

**Priority:** High  
**Type:** E2E  

**Steps:**
1. Open pipeline settings
2. Change visibility to "Public"
3. Confirm warning about public access

**Expected Results:**
- Pipeline appears in public gallery
- Anyone can view without link
- "Public" badge shown on pipeline

**Playwright Script:**
```typescript
test('set pipeline visibility to public', async ({ page }) => {
  await loginAs(fixtures.owner);
  await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  await page.click('[data-testid="sharing-tab"]');
  
  await page.selectOption('[data-testid="visibility-select"]', 'PUBLIC');
  
  // Warning dialog
  await expect(page.locator('[data-testid="public-warning"]')).toBeVisible();
  await page.click('[data-testid="confirm-public"]');
  
  await expect(page.locator('.toast-success')).toContainText('Visibility updated');
  
  // Verify in public gallery
  await page.goto('/explore');
  await expect(page.locator(`[data-testid="pipeline-${fixtures.pipeline.id}"]`)).toBeVisible();
});
```

---

#### TC-SHARE-031: Unlisted Pipeline Access

**Priority:** Medium  
**Type:** E2E  

**Preconditions:**
- Pipeline visibility set to UNLISTED

**Steps:**
1. Search for pipeline in public gallery (should not appear)
2. Access via direct link (should work)

**Expected Results:**
- Not discoverable in search/browse
- Direct link access works
- "Unlisted" badge shown

**Playwright Script:**
```typescript
test('unlisted pipeline not in gallery but accessible via link', async ({ page }) => {
  await setVisibility(fixtures.pipeline.id, 'UNLISTED');
  
  // Not in gallery
  await page.goto('/explore');
  await page.fill('[data-testid="search-input"]', fixtures.pipeline.name);
  await expect(page.locator(`[data-testid="pipeline-${fixtures.pipeline.id}"]`)).toBeHidden();
  
  // Direct access works
  await page.goto(`/pipelines/${fixtures.pipeline.id}`);
  await expect(page.locator('[data-testid="pipeline-canvas"]')).toBeVisible();
  await expect(page.locator('[data-testid="unlisted-badge"]')).toBeVisible();
});
```

---

## Analytics & Tracking

### TC-SHARE-040: Link Usage Analytics

**Priority:** Medium  
**Type:** E2E  

**Steps:**
1. Access pipeline via share link multiple times
2. View link analytics

**Expected Results:**
- Total views tracked
- Unique visitors counted
- Geographic distribution shown
- Access timeline displayed

**Playwright Script:**
```typescript
test('share link analytics tracked', async ({ page, browser }) => {
  // Generate traffic
  for (let i = 0; i < 5; i++) {
    const ctx = await browser.newContext();
    const p = await ctx.newPage();
    await p.goto(`/shared/${fixtures.validLink.token}`);
    await p.waitForSelector('[data-testid="pipeline-canvas"]');
    await ctx.close();
  }
  
  // Check analytics
  await loginAs(fixtures.owner);
  await page.goto(`/pipelines/${fixtures.pipeline.id}/settings`);
  await page.click('[data-testid="sharing-tab"]');
  await page.click('[data-testid="link-analytics-btn"]');
  
  await expect(page.locator('[data-testid="total-views"]')).toContainText('5');
  await expect(page.locator('[data-testid="access-chart"]')).toBeVisible();
});
```

---

## Performance Benchmarks

| Operation | Target | Measurement |
|-----------|--------|-------------|
| Share link validation | <50ms | Token lookup + auth |
| Invitation send | <500ms | Including email dispatch |
| Link generation | <100ms | Token creation + DB insert |
| Analytics query | <200ms | 30-day aggregation |

---

## Security Checklist

- [ ] Share tokens use cryptographically secure random generation
- [ ] Password hashes use bcrypt with cost factor ≥12
- [ ] Rate limiting on password attempts (5/min)
- [ ] Invitation tokens expire after 7 days
- [ ] No pipeline data in share link URL (token only)
- [ ] HTTPS enforced for all share URLs
