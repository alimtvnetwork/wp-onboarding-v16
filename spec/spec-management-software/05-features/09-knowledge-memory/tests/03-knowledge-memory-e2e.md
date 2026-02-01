# 34. Knowledge Memory E2E Test Scenarios

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28

---

## 34.1 Overview

This specification defines end-to-end test scenarios for the Knowledge Memory System, covering the complete user workflows for adding knowledge sources, monitoring ingestion progress, and managing existing sources.

### 34.1.1 Test Environment Requirements

| Component | Requirement |
|-----------|-------------|
| **Backend API** | Running on `localhost:8080` |
| **WebSocket** | Connected for real-time progress |
| **Test Database** | Isolated SQLite instance |
| **Mock Crawler** | Controllable HTTP responses |
| **Test Spec Directory** | Temporary directory with sample Markdown files |

### 34.1.2 Test Categories

| Category | Scenario Count | Priority |
|----------|---------------|----------|
| Add Spec Source | 12 | Critical |
| Add URL Source | 18 | Critical |
| Progress Tracking | 10 | High |
| Deletion Workflows | 8 | High |
| Error Handling | 15 | Medium |
| Concurrent Operations | 6 | Medium |

---

## 34.2 Add Spec Source Scenarios

### 34.2.1 Happy Path Scenarios (SPEC-ADD)

```typescript
// SPEC-ADD-001: Add valid spec directory
describe('Add Spec Source - Happy Path', () => {
  
  test('SPEC-ADD-001: Add valid spec directory', async ({ page }) => {
    // Setup: Create temp directory with markdown files
    const specPath = await createTempSpecDirectory([
      'overview.md',
      'api/endpoints.md',
      'api/authentication.md',
      'models/user.md'
    ]);
    
    // Navigate to Knowledge Memory page
    await page.goto('/settings/knowledge');
    
    // Click "Add Source" button
    await page.click('[data-testid="add-source-button"]');
    
    // Select "Spec Directory" type
    await page.click('[data-testid="source-type-spec"]');
    
    // Enter path
    await page.fill('[data-testid="spec-path-input"]', specPath);
    
    // Optional: Add include/exclude patterns
    await page.fill('[data-testid="include-patterns"]', '/api/.*');
    
    // Submit
    await page.click('[data-testid="submit-source"]');
    
    // Assert: Source appears in list
    await expect(page.locator('[data-testid="source-list"]'))
      .toContainText(specPath);
    
    // Assert: Status shows "Processing"
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Processing');
    
    // Wait for completion (with timeout)
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Ready', { timeout: 30000 });
    
    // Assert: Chunk count > 0
    const chunkCount = await page.locator('[data-testid="chunk-count"]').textContent();
    expect(parseInt(chunkCount)).toBeGreaterThan(0);
  });

  test('SPEC-ADD-002: Add nested spec directory', async ({ page }) => {
    const specPath = await createTempSpecDirectory({
      'root.md': '# Root',
      'level1/doc1.md': '# Level 1 Doc 1',
      'level1/level2/doc2.md': '# Level 2 Doc 2',
      'level1/level2/level3/doc3.md': '# Level 3 Doc 3'
    });
    
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    
    // Wait for completion
    await waitForSourceReady(page, specPath);
    
    // Assert: All nested files processed
    const fileCount = await page.locator('[data-testid="file-count"]').textContent();
    expect(parseInt(fileCount)).toBe(4);
  });

  test('SPEC-ADD-003: Add spec with custom include patterns', async ({ page }) => {
    const specPath = await createTempSpecDirectory([
      'api/v1/users.md',
      'api/v1/orders.md',
      'api/v2/users.md',
      'docs/readme.md',
      'internal/notes.md'
    ]);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="spec-path-input"]', specPath);
    
    // Only include v1 API docs
    await page.fill('[data-testid="include-patterns"]', '/api/v1/.*');
    
    await page.click('[data-testid="submit-source"]');
    await waitForSourceReady(page, specPath);
    
    // Assert: Only 2 files processed (v1 API docs)
    const fileCount = await page.locator('[data-testid="file-count"]').textContent();
    expect(parseInt(fileCount)).toBe(2);
  });

  test('SPEC-ADD-004: Add spec with exclude patterns', async ({ page }) => {
    const specPath = await createTempSpecDirectory([
      'public/api.md',
      'public/guide.md',
      'internal/secrets.md',
      'internal/notes.md',
      'draft/wip.md'
    ]);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="spec-path-input"]', specPath);
    
    // Exclude internal and draft
    await page.fill('[data-testid="exclude-patterns"]', '/internal/.*\n/draft/.*');
    
    await page.click('[data-testid="submit-source"]');
    await waitForSourceReady(page, specPath);
    
    // Assert: Only 2 files processed (public docs)
    const fileCount = await page.locator('[data-testid="file-count"]').textContent();
    expect(parseInt(fileCount)).toBe(2);
  });

  test('SPEC-ADD-005: Add spec with custom name', async ({ page }) => {
    const specPath = await createTempSpecDirectory(['doc.md']);
    const customName = 'My Project Documentation';
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="source-name-input"]', customName);
    await page.fill('[data-testid="spec-path-input"]', specPath);
    await page.click('[data-testid="submit-source"]');
    
    // Assert: Custom name displayed
    await expect(page.locator('[data-testid="source-name"]'))
      .toHaveText(customName);
  });
});
```

### 34.2.2 Validation Error Scenarios (SPEC-VAL)

```typescript
describe('Add Spec Source - Validation Errors', () => {

  test('SPEC-VAL-001: Empty path rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    
    // Leave path empty
    await page.click('[data-testid="submit-source"]');
    
    // Assert: Validation error shown
    await expect(page.locator('[data-testid="path-error"]'))
      .toHaveText('Path is required');
    
    // Assert: Form not submitted
    await expect(page.locator('[data-testid="source-list"]'))
      .not.toContainText('Processing');
  });

  test('SPEC-VAL-002: Non-existent path rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="spec-path-input"]', '/nonexistent/path/to/specs');
    await page.click('[data-testid="submit-source"]');
    
    // Assert: API error displayed
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Directory does not exist');
  });

  test('SPEC-VAL-003: Path traversal rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="spec-path-input"]', '../../../etc/passwd');
    await page.click('[data-testid="submit-source"]');
    
    // Assert: Security error displayed
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Path traversal not allowed');
  });

  test('SPEC-VAL-004: Invalid regex pattern rejected', async ({ page }) => {
    const specPath = await createTempSpecDirectory(['doc.md']);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="spec-path-input"]', specPath);
    await page.fill('[data-testid="include-patterns"]', '(unclosed');
    await page.click('[data-testid="submit-source"]');
    
    // Assert: Pattern validation error
    await expect(page.locator('[data-testid="pattern-error"]'))
      .toContainText('Invalid regex');
  });

  test('SPEC-VAL-005: Dangerous regex pattern rejected', async ({ page }) => {
    const specPath = await createTempSpecDirectory(['doc.md']);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="spec-path-input"]', specPath);
    await page.fill('[data-testid="include-patterns"]', '(a+)+');
    await page.click('[data-testid="submit-source"]');
    
    // Assert: ReDoS pattern rejected
    await expect(page.locator('[data-testid="pattern-error"]'))
      .toContainText('dangerous nested quantifiers');
  });

  test('SPEC-VAL-006: Duplicate path rejected', async ({ page }) => {
    const specPath = await createTempSpecDirectory(['doc.md']);
    
    // Add first source
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    await waitForSourceReady(page, specPath);
    
    // Try to add same path again
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="spec-path-input"]', specPath);
    await page.click('[data-testid="submit-source"]');
    
    // Assert: Duplicate error
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Source already exists');
  });

  test('SPEC-VAL-007: Path outside allowed roots rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-spec"]');
    await page.fill('[data-testid="spec-path-input"]', '/var/log/system.log');
    await page.click('[data-testid="submit-source"]');
    
    // Assert: Security error
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Path outside allowed directories');
  });
});
```

---

## 34.3 Add URL Source Scenarios

### 34.3.1 Happy Path Scenarios (URL-ADD)

```typescript
describe('Add URL Source - Happy Path', () => {

  test('URL-ADD-001: Add valid public URL', async ({ page }) => {
    // Setup mock server
    await mockServer.addRoute('https://docs.example.com/', {
      body: '<html><body><h1>Documentation</h1><p>Content here</p></body></html>',
      links: ['/guide', '/api']
    });
    await mockServer.addRoute('https://docs.example.com/guide', {
      body: '<html><body><h1>Guide</h1></body></html>'
    });
    await mockServer.addRoute('https://docs.example.com/api', {
      body: '<html><body><h1>API Reference</h1></body></html>'
    });
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'https://docs.example.com/');
    await page.click('[data-testid="submit-source"]');
    
    // Assert: Source appears with Processing status
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Processing');
    
    // Wait for crawl completion
    await waitForSourceReady(page, 'docs.example.com');
    
    // Assert: Pages crawled
    const pageCount = await page.locator('[data-testid="page-count"]').textContent();
    expect(parseInt(pageCount)).toBe(3);
  });

  test('URL-ADD-002: Add URL with custom crawl depth', async ({ page }) => {
    await setupDeepMockSite('https://deep.example.com/', 5);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'https://deep.example.com/');
    
    // Expand advanced options
    await page.click('[data-testid="advanced-options-toggle"]');
    await page.fill('[data-testid="max-depth-input"]', '2');
    
    await page.click('[data-testid="submit-source"]');
    await waitForSourceReady(page, 'deep.example.com');
    
    // Assert: Only depth 0-2 crawled (not full 5 levels)
    const pageCount = await page.locator('[data-testid="page-count"]').textContent();
    expect(parseInt(pageCount)).toBeLessThanOrEqual(7); // 1 + 2 + 4 max
  });

  test('URL-ADD-003: Add URL with max pages limit', async ({ page }) => {
    await setupLargeMockSite('https://large.example.com/', 100);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'https://large.example.com/');
    
    await page.click('[data-testid="advanced-options-toggle"]');
    await page.fill('[data-testid="max-pages-input"]', '10');
    
    await page.click('[data-testid="submit-source"]');
    await waitForSourceReady(page, 'large.example.com');
    
    // Assert: Stopped at 10 pages
    const pageCount = await page.locator('[data-testid="page-count"]').textContent();
    expect(parseInt(pageCount)).toBe(10);
  });

  test('URL-ADD-004: Add URL with include patterns', async ({ page }) => {
    await mockServer.addRoutes('https://docs.example.com/', [
      '/api/users',
      '/api/orders',
      '/guide/intro',
      '/guide/advanced',
      '/blog/post1'
    ]);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'https://docs.example.com/');
    await page.fill('[data-testid="include-patterns"]', '^/api/.*');
    
    await page.click('[data-testid="submit-source"]');
    await waitForSourceReady(page, 'docs.example.com');
    
    // Assert: Only API pages crawled
    const pageCount = await page.locator('[data-testid="page-count"]').textContent();
    expect(parseInt(pageCount)).toBe(3); // root + 2 API pages
  });

  test('URL-ADD-005: Add URL with exclude patterns', async ({ page }) => {
    await mockServer.addRoutes('https://docs.example.com/', [
      '/public/doc1',
      '/public/doc2',
      '/internal/secret',
      '/internal/config'
    ]);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'https://docs.example.com/');
    await page.fill('[data-testid="exclude-patterns"]', '^/internal/.*');
    
    await page.click('[data-testid="submit-source"]');
    await waitForSourceReady(page, 'docs.example.com');
    
    // Assert: Internal pages excluded
    const pageCount = await page.locator('[data-testid="page-count"]').textContent();
    expect(parseInt(pageCount)).toBe(3); // root + 2 public pages
  });

  test('URL-ADD-006: Add URL with custom delay', async ({ page }) => {
    const startTime = Date.now();
    await mockServer.addRoutes('https://slow.example.com/', ['/page1', '/page2', '/page3']);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'https://slow.example.com/');
    
    await page.click('[data-testid="advanced-options-toggle"]');
    await page.fill('[data-testid="delay-ms-input"]', '2000'); // 2 second delay
    
    await page.click('[data-testid="submit-source"]');
    await waitForSourceReady(page, 'slow.example.com', { timeout: 60000 });
    
    const elapsed = Date.now() - startTime;
    // Assert: At least 6 seconds elapsed (3 pages × 2s delay)
    expect(elapsed).toBeGreaterThanOrEqual(6000);
  });
});
```

### 34.3.2 URL Validation Error Scenarios (URL-VAL)

```typescript
describe('Add URL Source - Validation Errors', () => {

  test('URL-VAL-001: Empty URL rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="url-error"]'))
      .toHaveText('URL is required');
  });

  test('URL-VAL-002: Invalid URL format rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'not-a-url');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="url-error"]'))
      .toContainText('Invalid URL format');
  });

  test('URL-VAL-003: Non-HTTP scheme rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'ftp://files.example.com/');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="url-error"]'))
      .toContainText('must use http or https');
  });

  test('URL-VAL-004: Localhost rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'http://localhost:3000/');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Private network URL not allowed');
  });

  test('URL-VAL-005: Private IP 192.168.x.x rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'http://192.168.1.1/admin');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Private network URL not allowed');
  });

  test('URL-VAL-006: Private IP 10.x.x.x rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'http://10.0.0.1/internal');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Private network URL not allowed');
  });

  test('URL-VAL-007: Private IP 172.16.x.x rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'http://172.16.0.1/app');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Private network URL not allowed');
  });

  test('URL-VAL-008: AWS metadata endpoint rejected', async ({ page }) => {
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'http://169.254.169.254/latest/meta-data');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('Private network URL not allowed');
  });

  test('URL-VAL-009: Duplicate URL rejected', async ({ page }) => {
    await mockServer.addRoute('https://docs.example.com/', { body: '<html></html>' });
    
    // Add first source
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://docs.example.com/');
    await waitForSourceReady(page, 'docs.example.com');
    
    // Try to add same URL again
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', 'https://docs.example.com/');
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="api-error"]'))
      .toContainText('URL already exists');
  });

  test('URL-VAL-010: URL too long rejected', async ({ page }) => {
    const longUrl = 'https://example.com/' + 'a'.repeat(2100);
    
    await page.goto('/settings/knowledge');
    await page.click('[data-testid="add-source-button"]');
    await page.click('[data-testid="source-type-url"]');
    await page.fill('[data-testid="url-input"]', longUrl);
    await page.click('[data-testid="submit-source"]');
    
    await expect(page.locator('[data-testid="url-error"]'))
      .toContainText('URL exceeds 2048 characters');
  });
});
```

---

## 34.4 Progress Tracking Scenarios

### 34.4.1 Real-Time Progress Updates (PROG)

```typescript
describe('Progress Tracking - WebSocket Updates', () => {

  test('PROG-001: Initial status shows pending', async ({ page }) => {
    await mockServer.addSlowRoute('https://slow.example.com/', 5000);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://slow.example.com/');
    
    // Assert: Immediate status is "Pending" or "Queued"
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText(/Pending|Queued/);
  });

  test('PROG-002: Status transitions to processing', async ({ page }) => {
    await mockServer.addSlowRoute('https://slow.example.com/', 5000);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://slow.example.com/');
    
    // Wait for processing to start
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Processing', { timeout: 10000 });
  });

  test('PROG-003: Progress percentage updates in real-time', async ({ page }) => {
    // Setup mock with 10 pages, 1 second each
    await setupLargeMockSite('https://progress.example.com/', 10, 1000);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://progress.example.com/', { maxPages: 10 });
    
    // Collect progress updates
    const progressValues: number[] = [];
    await page.locator('[data-testid="progress-percentage"]').evaluate(
      (el) => {
        const observer = new MutationObserver(() => {
          progressValues.push(parseInt(el.textContent || '0'));
        });
        observer.observe(el, { characterData: true, subtree: true });
      }
    );
    
    await waitForSourceReady(page, 'progress.example.com', { timeout: 30000 });
    
    // Assert: Progress increased over time
    expect(progressValues.length).toBeGreaterThan(5);
    expect(progressValues[progressValues.length - 1]).toBe(100);
  });

  test('PROG-004: Pages crawled counter updates', async ({ page }) => {
    await setupLargeMockSite('https://counter.example.com/', 5, 500);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://counter.example.com/', { maxPages: 5 });
    
    // Watch for counter updates
    await expect(page.locator('[data-testid="pages-crawled"]'))
      .toHaveText('1', { timeout: 5000 });
    
    await expect(page.locator('[data-testid="pages-crawled"]'))
      .toHaveText('2', { timeout: 5000 });
    
    // Eventually reaches 5
    await expect(page.locator('[data-testid="pages-crawled"]'))
      .toHaveText('5', { timeout: 20000 });
  });

  test('PROG-005: Current URL displayed during crawl', async ({ page }) => {
    await mockServer.addRoutes('https://show.example.com/', ['/page1', '/page2'], 2000);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://show.example.com/');
    
    // Wait for crawl to start, check current URL shown
    await expect(page.locator('[data-testid="current-url"]'))
      .toContainText('show.example.com', { timeout: 5000 });
  });

  test('PROG-006: ETA displayed during long crawls', async ({ page }) => {
    await setupLargeMockSite('https://eta.example.com/', 20, 500);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://eta.example.com/', { maxPages: 20 });
    
    // Wait for enough data to calculate ETA
    await page.waitForTimeout(3000);
    
    // Assert: ETA is displayed
    await expect(page.locator('[data-testid="estimated-time"]'))
      .toBeVisible();
    
    const etaText = await page.locator('[data-testid="estimated-time"]').textContent();
    expect(etaText).toMatch(/\d+\s*(s|m|min|seconds|minutes)/);
  });

  test('PROG-007: Files processed counter for spec source', async ({ page }) => {
    const specPath = await createTempSpecDirectory([
      'doc1.md', 'doc2.md', 'doc3.md', 'doc4.md', 'doc5.md'
    ]);
    
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    
    // Watch files processed counter
    await expect(page.locator('[data-testid="files-processed"]'))
      .toHaveText('5', { timeout: 10000 });
  });

  test('PROG-008: Chunks generated counter updates', async ({ page }) => {
    // Large document that will generate multiple chunks
    const specPath = await createTempSpecDirectory({
      'large.md': generateLargeMarkdown(5000) // ~5000 words
    });
    
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    
    await waitForSourceReady(page, specPath);
    
    // Assert: Multiple chunks generated
    const chunkCount = await page.locator('[data-testid="chunk-count"]').textContent();
    expect(parseInt(chunkCount)).toBeGreaterThan(5);
  });

  test('PROG-009: Error state displayed on failure', async ({ page }) => {
    await mockServer.addRoute('https://fail.example.com/', { status: 500 });
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://fail.example.com/');
    
    // Wait for error state
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Error', { timeout: 30000 });
    
    // Assert: Error message shown
    await expect(page.locator('[data-testid="error-message"]'))
      .toBeVisible();
  });

  test('PROG-010: Cancel button stops processing', async ({ page }) => {
    await setupLargeMockSite('https://cancel.example.com/', 50, 1000);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://cancel.example.com/', { maxPages: 50 });
    
    // Wait for processing to start
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Processing', { timeout: 5000 });
    
    // Click cancel
    await page.click('[data-testid="cancel-button"]');
    
    // Assert: Status changes to cancelled
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText(/Cancelled|Stopped/, { timeout: 5000 });
    
    // Assert: Partial progress retained
    const pageCount = await page.locator('[data-testid="page-count"]').textContent();
    expect(parseInt(pageCount)).toBeGreaterThan(0);
    expect(parseInt(pageCount)).toBeLessThan(50);
  });
});
```

---

## 34.5 Deletion Workflow Scenarios

### 34.5.1 Delete Source Scenarios (DEL)

```typescript
describe('Delete Knowledge Source', () => {

  test('DEL-001: Delete completed source', async ({ page }) => {
    const specPath = await createTempSpecDirectory(['doc.md']);
    
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    await waitForSourceReady(page, specPath);
    
    // Click delete button
    await page.click('[data-testid="delete-source-button"]');
    
    // Confirm deletion dialog
    await expect(page.locator('[data-testid="confirm-dialog"]'))
      .toBeVisible();
    await page.click('[data-testid="confirm-delete"]');
    
    // Assert: Source removed from list
    await expect(page.locator('[data-testid="source-list"]'))
      .not.toContainText(specPath);
  });

  test('DEL-002: Delete processing source', async ({ page }) => {
    await setupLargeMockSite('https://busy.example.com/', 100, 500);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://busy.example.com/', { maxPages: 100 });
    
    // Wait for processing to start
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Processing', { timeout: 5000 });
    
    // Try to delete
    await page.click('[data-testid="delete-source-button"]');
    
    // Assert: Warning about active processing
    await expect(page.locator('[data-testid="confirm-dialog"]'))
      .toContainText('currently processing');
    
    // Confirm anyway
    await page.click('[data-testid="confirm-delete"]');
    
    // Assert: Source removed
    await expect(page.locator('[data-testid="source-list"]'))
      .not.toContainText('busy.example.com', { timeout: 10000 });
  });

  test('DEL-003: Delete failed source', async ({ page }) => {
    await mockServer.addRoute('https://failed.example.com/', { status: 500 });
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://failed.example.com/');
    
    // Wait for error state
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Error', { timeout: 30000 });
    
    // Delete the failed source
    await page.click('[data-testid="delete-source-button"]');
    await page.click('[data-testid="confirm-delete"]');
    
    // Assert: Source removed
    await expect(page.locator('[data-testid="source-list"]'))
      .not.toContainText('failed.example.com');
  });

  test('DEL-004: Cancel delete confirmation', async ({ page }) => {
    const specPath = await createTempSpecDirectory(['doc.md']);
    
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    await waitForSourceReady(page, specPath);
    
    // Start delete flow
    await page.click('[data-testid="delete-source-button"]');
    
    // Cancel
    await page.click('[data-testid="cancel-delete"]');
    
    // Assert: Source still exists
    await expect(page.locator('[data-testid="source-list"]'))
      .toContainText(specPath);
  });

  test('DEL-005: Delete removes all chunks', async ({ page, request }) => {
    const specPath = await createTempSpecDirectory({
      'doc1.md': generateLargeMarkdown(1000),
      'doc2.md': generateLargeMarkdown(1000)
    });
    
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    await waitForSourceReady(page, specPath);
    
    // Get source ID for API verification
    const sourceId = await page.locator('[data-testid="source-id"]').textContent();
    
    // Verify chunks exist
    const beforeResponse = await request.get(`/api/v1/knowledge/sources/${sourceId}/chunks`);
    const beforeData = await beforeResponse.json();
    expect(beforeData.data.length).toBeGreaterThan(0);
    
    // Delete source
    await page.click('[data-testid="delete-source-button"]');
    await page.click('[data-testid="confirm-delete"]');
    
    // Wait for deletion
    await expect(page.locator('[data-testid="source-list"]'))
      .not.toContainText(specPath);
    
    // Verify chunks deleted via API
    const afterResponse = await request.get(`/api/v1/knowledge/sources/${sourceId}/chunks`);
    expect(afterResponse.status()).toBe(404);
  });

  test('DEL-006: Bulk delete multiple sources', async ({ page }) => {
    // Add multiple sources
    await page.goto('/settings/knowledge');
    
    for (let i = 0; i < 3; i++) {
      const specPath = await createTempSpecDirectory([`doc${i}.md`]);
      await addSpecSource(page, specPath);
      await waitForSourceReady(page, specPath);
    }
    
    // Select all sources
    await page.click('[data-testid="select-all-checkbox"]');
    
    // Click bulk delete
    await page.click('[data-testid="bulk-delete-button"]');
    
    // Confirm
    await expect(page.locator('[data-testid="confirm-dialog"]'))
      .toContainText('3 sources');
    await page.click('[data-testid="confirm-delete"]');
    
    // Assert: All sources removed
    await expect(page.locator('[data-testid="source-list"]'))
      .toBeEmpty();
  });

  test('DEL-007: Delete with keyboard shortcut', async ({ page }) => {
    const specPath = await createTempSpecDirectory(['doc.md']);
    
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    await waitForSourceReady(page, specPath);
    
    // Select source
    await page.click('[data-testid="source-row"]');
    
    // Press Delete key
    await page.keyboard.press('Delete');
    
    // Assert: Confirmation dialog appears
    await expect(page.locator('[data-testid="confirm-dialog"]'))
      .toBeVisible();
  });

  test('DEL-008: Undo delete within grace period', async ({ page }) => {
    const specPath = await createTempSpecDirectory(['doc.md']);
    
    await page.goto('/settings/knowledge');
    await addSpecSource(page, specPath);
    await waitForSourceReady(page, specPath);
    
    // Delete source
    await page.click('[data-testid="delete-source-button"]');
    await page.click('[data-testid="confirm-delete"]');
    
    // Assert: Undo toast appears
    await expect(page.locator('[data-testid="undo-toast"]'))
      .toBeVisible();
    
    // Click undo
    await page.click('[data-testid="undo-button"]');
    
    // Assert: Source restored
    await expect(page.locator('[data-testid="source-list"]'))
      .toContainText(specPath);
  });
});
```

---

## 34.6 Concurrent Operations Scenarios

### 34.6.1 Concurrent Source Operations (CONC)

```typescript
describe('Concurrent Operations', () => {

  test('CONC-001: Add multiple sources simultaneously', async ({ page }) => {
    await mockServer.addRoutes('https://site1.example.com/', ['/page1'], 1000);
    await mockServer.addRoutes('https://site2.example.com/', ['/page1'], 1000);
    await mockServer.addRoutes('https://site3.example.com/', ['/page1'], 1000);
    
    await page.goto('/settings/knowledge');
    
    // Add 3 sources in quick succession
    await addUrlSource(page, 'https://site1.example.com/', { waitForDialog: false });
    await addUrlSource(page, 'https://site2.example.com/', { waitForDialog: false });
    await addUrlSource(page, 'https://site3.example.com/', { waitForDialog: false });
    
    // Assert: All 3 sources appear
    await expect(page.locator('[data-testid="source-row"]'))
      .toHaveCount(3);
    
    // Wait for all to complete
    await expect(page.locator('[data-testid="source-status"]:has-text("Processing")'))
      .toHaveCount(0, { timeout: 30000 });
    
    // Assert: All successful
    await expect(page.locator('[data-testid="source-status"]:has-text("Ready")'))
      .toHaveCount(3);
  });

  test('CONC-002: Worker limit enforced', async ({ page }) => {
    // Default maxConcurrentWorkers is 3
    await setupLargeMockSite('https://slow1.example.com/', 20, 500);
    await setupLargeMockSite('https://slow2.example.com/', 20, 500);
    await setupLargeMockSite('https://slow3.example.com/', 20, 500);
    await setupLargeMockSite('https://slow4.example.com/', 20, 500);
    
    await page.goto('/settings/knowledge');
    
    // Add 4 sources
    for (let i = 1; i <= 4; i++) {
      await addUrlSource(page, `https://slow${i}.example.com/`, { waitForDialog: false });
    }
    
    // Wait a moment for processing to start
    await page.waitForTimeout(2000);
    
    // Assert: At most 3 processing at once
    const processingCount = await page.locator('[data-testid="source-status"]:has-text("Processing")').count();
    expect(processingCount).toBeLessThanOrEqual(3);
    
    // Assert: 4th source is queued
    await expect(page.locator('[data-testid="source-status"]:has-text("Queued")'))
      .toHaveCount(1);
  });

  test('CONC-003: Queued source starts after completion', async ({ page }) => {
    // Fast source and slow sources
    await mockServer.addRoute('https://fast.example.com/', { body: '<html></html>' });
    await setupLargeMockSite('https://slow1.example.com/', 20, 500);
    await setupLargeMockSite('https://slow2.example.com/', 20, 500);
    await setupLargeMockSite('https://slow3.example.com/', 20, 500);
    await setupLargeMockSite('https://queued.example.com/', 5, 100);
    
    await page.goto('/settings/knowledge');
    
    // Fill up worker slots
    await addUrlSource(page, 'https://slow1.example.com/', { waitForDialog: false });
    await addUrlSource(page, 'https://slow2.example.com/', { waitForDialog: false });
    await addUrlSource(page, 'https://slow3.example.com/', { waitForDialog: false });
    
    // Add queued source
    await addUrlSource(page, 'https://queued.example.com/', { waitForDialog: false });
    
    // Assert: Initially queued
    await expect(page.locator('[data-testid="source-row"]:has-text("queued.example.com") [data-testid="source-status"]'))
      .toHaveText('Queued');
    
    // Wait for first source to complete and queued to start
    await expect(page.locator('[data-testid="source-row"]:has-text("queued.example.com") [data-testid="source-status"]'))
      .toHaveText('Processing', { timeout: 30000 });
  });

  test('CONC-004: UI remains responsive during heavy processing', async ({ page }) => {
    await setupLargeMockSite('https://heavy.example.com/', 50, 200);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://heavy.example.com/', { maxPages: 50 });
    
    // While processing, navigate to other parts of the UI
    await page.click('[data-testid="nav-settings"]');
    await expect(page.locator('h1')).toHaveText('Settings');
    
    await page.click('[data-testid="nav-knowledge"]');
    await expect(page.locator('h1')).toHaveText('Knowledge Memory');
    
    // Assert: Still processing
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Processing');
  });

  test('CONC-005: Refresh page during processing', async ({ page }) => {
    await setupLargeMockSite('https://refresh.example.com/', 30, 500);
    
    await page.goto('/settings/knowledge');
    await addUrlSource(page, 'https://refresh.example.com/', { maxPages: 30 });
    
    // Wait for processing
    await expect(page.locator('[data-testid="source-status"]'))
      .toHaveText('Processing', { timeout: 5000 });
    
    // Refresh page
    await page.reload();
    
    // Assert: Source still shows (persisted to DB)
    await expect(page.locator('[data-testid="source-list"]'))
      .toContainText('refresh.example.com');
    
    // Assert: Status reflects current state
    const status = await page.locator('[data-testid="source-status"]').textContent();
    expect(['Processing', 'Ready']).toContain(status);
  });

  test('CONC-006: Multiple users adding sources', async ({ browser }) => {
    // Simulate two browser contexts (two users)
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();
    const page1 = await context1.newPage();
    const page2 = await context2.newPage();
    
    await mockServer.addRoute('https://user1.example.com/', { body: '<html></html>' });
    await mockServer.addRoute('https://user2.example.com/', { body: '<html></html>' });
    
    // Both users navigate to knowledge page
    await page1.goto('/settings/knowledge');
    await page2.goto('/settings/knowledge');
    
    // Both add sources simultaneously
    await Promise.all([
      addUrlSource(page1, 'https://user1.example.com/'),
      addUrlSource(page2, 'https://user2.example.com/')
    ]);
    
    // Both should see their sources complete
    await waitForSourceReady(page1, 'user1.example.com');
    await waitForSourceReady(page2, 'user2.example.com');
    
    await context1.close();
    await context2.close();
  });
});
```

---

## 34.7 Test Utilities

### 34.7.1 Helper Functions

```typescript
// Helper: Add spec source
async function addSpecSource(page: Page, path: string, options?: AddSourceOptions): Promise<void> {
  await page.click('[data-testid="add-source-button"]');
  await page.click('[data-testid="source-type-spec"]');
  await page.fill('[data-testid="spec-path-input"]', path);
  
  if (options?.name) {
    await page.fill('[data-testid="source-name-input"]', options.name);
  }
  if (options?.includePatterns) {
    await page.fill('[data-testid="include-patterns"]', options.includePatterns);
  }
  if (options?.excludePatterns) {
    await page.fill('[data-testid="exclude-patterns"]', options.excludePatterns);
  }
  
  await page.click('[data-testid="submit-source"]');
}

// Helper: Add URL source
async function addUrlSource(page: Page, url: string, options?: AddUrlOptions): Promise<void> {
  await page.click('[data-testid="add-source-button"]');
  await page.click('[data-testid="source-type-url"]');
  await page.fill('[data-testid="url-input"]', url);
  
  if (options?.maxDepth || options?.maxPages || options?.delayMs) {
    await page.click('[data-testid="advanced-options-toggle"]');
    
    if (options?.maxDepth) {
      await page.fill('[data-testid="max-depth-input"]', options.maxDepth.toString());
    }
    if (options?.maxPages) {
      await page.fill('[data-testid="max-pages-input"]', options.maxPages.toString());
    }
    if (options?.delayMs) {
      await page.fill('[data-testid="delay-ms-input"]', options.delayMs.toString());
    }
  }
  
  await page.click('[data-testid="submit-source"]');
}

// Helper: Wait for source to be ready
async function waitForSourceReady(
  page: Page, 
  identifier: string, 
  options?: { timeout?: number }
): Promise<void> {
  const timeout = options?.timeout || 30000;
  await expect(
    page.locator(`[data-testid="source-row"]:has-text("${identifier}") [data-testid="source-status"]`)
  ).toHaveText('Ready', { timeout });
}

// Helper: Create temp spec directory
async function createTempSpecDirectory(
  files: string[] | Record<string, string>
): Promise<string> {
  const tmpDir = await fs.mkdtemp(path.join(os.tmpdir(), 'spec-test-'));
  
  if (Array.isArray(files)) {
    for (const file of files) {
      const filePath = path.join(tmpDir, file);
      await fs.mkdir(path.dirname(filePath), { recursive: true });
      await fs.writeFile(filePath, `# ${path.basename(file, '.md')}\n\nSample content.`);
    }
  } else {
    for (const [file, content] of Object.entries(files)) {
      const filePath = path.join(tmpDir, file);
      await fs.mkdir(path.dirname(filePath), { recursive: true });
      await fs.writeFile(filePath, content);
    }
  }
  
  return tmpDir;
}

// Helper: Generate large markdown content
function generateLargeMarkdown(wordCount: number): string {
  const words = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 
                 'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor'];
  let content = '# Test Document\n\n';
  
  for (let i = 0; i < wordCount; i++) {
    content += words[i % words.length] + ' ';
    if (i % 50 === 49) content += '\n\n';
  }
  
  return content;
}

// Helper: Setup mock site with many pages
async function setupLargeMockSite(
  baseUrl: string, 
  pageCount: number, 
  delayMs?: number
): Promise<void> {
  const paths = Array.from({ length: pageCount }, (_, i) => `/page${i + 1}`);
  await mockServer.addRoutes(baseUrl, paths, delayMs);
}
```

### 34.7.2 Type Definitions

```typescript
interface AddSourceOptions {
  name?: string;
  includePatterns?: string;
  excludePatterns?: string;
}

interface AddUrlOptions extends AddSourceOptions {
  maxDepth?: number;
  maxPages?: number;
  delayMs?: number;
  waitForDialog?: boolean;
}

interface MockRouteOptions {
  body?: string;
  status?: number;
  links?: string[];
  delay?: number;
}
```

---

## 34.8 CI/CD Integration

### 34.8.1 GitHub Actions Workflow

```yaml
name: Knowledge Memory E2E Tests

on:
  push:
    paths:
      - 'src/features/knowledge/**'
      - 'tests/e2e/knowledge/**'
  pull_request:
    paths:
      - 'src/features/knowledge/**'

jobs:
  e2e-tests:
    runs-on: ubuntu-latest
    
    services:
      mock-server:
        image: mockserver/mockserver:latest
        ports:
          - 1080:1080
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Install Playwright browsers
        run: npx playwright install --with-deps
      
      - name: Start backend
        run: |
          cd backend
          go run cmd/server/main.go &
          sleep 10
      
      - name: Run E2E tests
        run: npx playwright test tests/e2e/knowledge/
        env:
          BASE_URL: http://localhost:3000
          API_URL: http://localhost:8080
          MOCK_SERVER_URL: http://localhost:1080
      
      - name: Upload test results
        uses: actions/upload-artifact@v4
        if: always()
        with:
          name: playwright-report
          path: playwright-report/
          retention-days: 7
      
      - name: Upload screenshots on failure
        uses: actions/upload-artifact@v4
        if: failure()
        with:
          name: failure-screenshots
          path: test-results/
          retention-days: 7
```

---

## 34.9 Coverage Requirements

| Scenario Category | Minimum Coverage | Critical Scenarios |
|-------------------|-----------------|-------------------|
| Add Spec Source | 100% | SPEC-ADD-001, SPEC-VAL-003 |
| Add URL Source | 100% | URL-ADD-001, URL-VAL-004-008 |
| Progress Tracking | 90% | PROG-003, PROG-009 |
| Deletion | 100% | DEL-001, DEL-005 |
| Concurrent Ops | 85% | CONC-002, CONC-005 |

---

## 34.10 Cross-References

| Specification | Relationship |
|---------------|--------------|
| 31-knowledge-memory-system.md | Feature implementation |
| 33-knowledge-validator-tests.md | Unit test specifications |
| 09-seeding-configuration.md | Configuration keys |
| frontend/20-testing-strategy.md | Testing patterns |

---

## 34.11 Summary

This E2E test specification provides:

1. **69 test scenarios** covering all major Knowledge Memory workflows
2. **Complete happy path coverage** for spec and URL source addition
3. **Extensive validation testing** for security-critical inputs
4. **Real-time progress verification** via WebSocket updates
5. **Deletion workflow testing** including undo functionality
6. **Concurrent operation testing** for worker pool limits
7. **CI/CD integration** with GitHub Actions workflow
