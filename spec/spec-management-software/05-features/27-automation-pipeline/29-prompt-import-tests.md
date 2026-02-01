# Prompt Import - E2E Test Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Phase:** 1 - Foundation  

---

## Overview

End-to-end test specifications for the Prompt Import System, covering ZIP ingestion, YAML frontmatter parsing, conflict resolution, and folder organization.

**Cross-References:**
- [Prompt Import System](./02-prompt-import-system.md)
- [Database Schema](./01-database-schema.md)

---

## Test Environment Setup

```typescript
interface PromptImportFixtures {
  project: TestProject;
  validZipFile: TestFile;
  invalidZipFile: TestFile;
  duplicateZipFile: TestFile;
  largeZipFile: TestFile;
  existingPrompts: TestPrompt[];
}

const setupPromptImportFixtures = async (): Promise<PromptImportFixtures> => {
  const project = await createTestProject();
  
  return {
    project,
    validZipFile: await createTestZip([
      { path: 'prompts/html/generate-page.md', content: '---\ncategory: html\ntags: [generation]\n---\nGenerate HTML...' },
      { path: 'prompts/html/validate-structure.md', content: '---\ncategory: html\ntags: [validation]\n---\nValidate HTML...' },
      { path: 'prompts/api/fetch-data.md', content: '---\ncategory: api\n---\nFetch data from...' },
    ]),
    invalidZipFile: await createTestZip([
      { path: 'invalid.txt', content: 'Not a markdown file' },
      { path: 'corrupted.md', content: '---\ninvalid yaml: [' },
    ]),
    duplicateZipFile: await createTestZip([
      { path: 'prompts/html/generate-page.md', content: '---\ncategory: html\nversion: 2.0\n---\nUpdated prompt...' },
    ]),
    largeZipFile: await createLargeTestZip(500), // 500 prompt files
    existingPrompts: await createTestPrompts(project.id, 3),
  };
};
```

---

## Test Suites

### Suite 1: ZIP File Upload

#### TC-IMPORT-001: Upload Valid ZIP File

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Navigate to Prompt Library
2. Click "Import Prompts"
3. Select valid ZIP file
4. Confirm import

**Expected Results:**
- Progress indicator shows during upload
- All prompts extracted and parsed
- Folder structure preserved in database
- Success toast with import count

```typescript
test('upload valid zip file', async ({ page }) => {
  await page.goto(`/projects/${fixtures.project.id}/prompts`);
  
  await page.click('[data-testid="import-prompts-btn"]');
  
  const fileInput = page.locator('input[type="file"]');
  await fileInput.setInputFiles(fixtures.validZipFile.path);
  
  await expect(page.locator('[data-testid="import-progress"]')).toBeVisible();
  await page.click('[data-testid="confirm-import"]');
  
  await expect(page.locator('.toast-success')).toContainText('3 prompts imported');
  
  // Verify folder structure
  await expect(page.locator('[data-testid="folder-html"]')).toBeVisible();
  await expect(page.locator('[data-testid="folder-api"]')).toBeVisible();
});
```

---

#### TC-IMPORT-002: Reject Invalid File Types

**Priority:** High  
**Type:** Validation  

**Steps:**
1. Attempt to upload non-ZIP file (e.g., .txt, .pdf)

**Expected Results:**
- Error message: "Only ZIP files are supported"
- No import dialog opened
- File not processed

```typescript
test('reject non-zip files', async ({ page }) => {
  await page.goto(`/projects/${fixtures.project.id}/prompts`);
  await page.click('[data-testid="import-prompts-btn"]');
  
  const fileInput = page.locator('input[type="file"]');
  await fileInput.setInputFiles('test-file.txt');
  
  await expect(page.locator('[data-testid="file-error"]'))
    .toContainText('Only ZIP files are supported');
  await expect(page.locator('[data-testid="confirm-import"]')).toBeDisabled();
});
```

---

#### TC-IMPORT-003: Handle Corrupted ZIP

**Priority:** High  
**Type:** Error Handling  

**Steps:**
1. Upload ZIP with corrupted markdown files
2. Observe error handling

**Expected Results:**
- Partial import with warnings
- Valid files imported
- Error log shows failed files
- User can review and retry

```typescript
test('handle corrupted zip gracefully', async ({ page }) => {
  await page.goto(`/projects/${fixtures.project.id}/prompts`);
  await page.click('[data-testid="import-prompts-btn"]');
  
  const fileInput = page.locator('input[type="file"]');
  await fileInput.setInputFiles(fixtures.invalidZipFile.path);
  
  await page.click('[data-testid="confirm-import"]');
  
  await expect(page.locator('[data-testid="import-warnings"]')).toBeVisible();
  await expect(page.locator('[data-testid="failed-files"]')).toContainText('corrupted.md');
});
```

---

### Suite 2: YAML Frontmatter Parsing

#### TC-IMPORT-010: Parse Valid Frontmatter

**Priority:** Critical  
**Type:** E2E  

**Steps:**
1. Import prompt with complete frontmatter
2. View prompt details

**Expected Results:**
- Category extracted correctly
- Tags parsed as array
- Version number captured
- Author metadata stored

```typescript
test('parse yaml frontmatter correctly', async ({ page }) => {
  await importTestZip(page, fixtures.validZipFile);
  
  await page.click('[data-testid="prompt-generate-page"]');
  
  await expect(page.locator('[data-testid="prompt-category"]')).toContainText('html');
  await expect(page.locator('[data-testid="prompt-tags"]')).toContainText('generation');
});
```

---

#### TC-IMPORT-011: Handle Missing Frontmatter

**Priority:** Medium  
**Type:** Edge Case  

**Steps:**
1. Import prompt without frontmatter

**Expected Results:**
- Prompt imported with default metadata
- Category set to "uncategorized"
- Warning logged but import succeeds

```typescript
test('handle missing frontmatter', async ({ page }) => {
  const noFrontmatterZip = await createTestZip([
    { path: 'prompts/simple.md', content: 'Just content, no frontmatter' }
  ]);
  
  await importTestZip(page, noFrontmatterZip);
  
  await page.click('[data-testid="prompt-simple"]');
  await expect(page.locator('[data-testid="prompt-category"]')).toContainText('uncategorized');
});
```

---

### Suite 3: Conflict Resolution

#### TC-IMPORT-020: Detect Duplicate Prompts

**Priority:** High  
**Type:** E2E  

**Steps:**
1. Import ZIP with prompts
2. Import another ZIP with same file paths
3. Observe conflict detection

**Expected Results:**
- Conflict dialog appears
- Shows existing vs. new content diff
- Offers resolution options

```typescript
test('detect duplicate prompts', async ({ page }) => {
  // First import
  await importTestZip(page, fixtures.validZipFile);
  
  // Second import with duplicates
  await page.click('[data-testid="import-prompts-btn"]');
  await page.locator('input[type="file"]').setInputFiles(fixtures.duplicateZipFile.path);
  
  await expect(page.locator('[data-testid="conflict-dialog"]')).toBeVisible();
  await expect(page.locator('[data-testid="conflict-count"]')).toContainText('1 conflict');
});
```

---

#### TC-IMPORT-021: Resolve with Skip Strategy

**Priority:** High  
**Type:** E2E  

**Steps:**
1. Trigger conflict
2. Select "Skip" resolution
3. Confirm import

**Expected Results:**
- Existing prompt unchanged
- New prompt not imported
- Other non-conflicting prompts imported

```typescript
test('skip strategy preserves existing', async ({ page }) => {
  await importTestZip(page, fixtures.validZipFile);
  const originalContent = await getPromptContent(fixtures.project.id, 'generate-page');
  
  await page.click('[data-testid="import-prompts-btn"]');
  await page.locator('input[type="file"]').setInputFiles(fixtures.duplicateZipFile.path);
  
  await page.click('[data-testid="conflict-strategy-skip"]');
  await page.click('[data-testid="confirm-import"]');
  
  const newContent = await getPromptContent(fixtures.project.id, 'generate-page');
  expect(newContent).toBe(originalContent);
});
```

---

#### TC-IMPORT-022: Resolve with Replace Strategy

**Priority:** High  
**Type:** E2E  

```typescript
test('replace strategy overwrites existing', async ({ page }) => {
  await importTestZip(page, fixtures.validZipFile);
  
  await page.click('[data-testid="import-prompts-btn"]');
  await page.locator('input[type="file"]').setInputFiles(fixtures.duplicateZipFile.path);
  
  await page.click('[data-testid="conflict-strategy-replace"]');
  await page.click('[data-testid="confirm-import"]');
  
  await page.click('[data-testid="prompt-generate-page"]');
  await expect(page.locator('[data-testid="prompt-content"]')).toContainText('Updated prompt');
});
```

---

#### TC-IMPORT-023: Resolve with Rename Strategy

**Priority:** Medium  
**Type:** E2E  

```typescript
test('rename strategy creates new version', async ({ page }) => {
  await importTestZip(page, fixtures.validZipFile);
  
  await page.click('[data-testid="import-prompts-btn"]');
  await page.locator('input[type="file"]').setInputFiles(fixtures.duplicateZipFile.path);
  
  await page.click('[data-testid="conflict-strategy-rename"]');
  await page.click('[data-testid="confirm-import"]');
  
  // Both versions should exist
  await expect(page.locator('[data-testid="prompt-generate-page"]')).toBeVisible();
  await expect(page.locator('[data-testid="prompt-generate-page-1"]')).toBeVisible();
});
```

---

### Suite 4: Large File Handling

#### TC-IMPORT-030: Import Large ZIP (500+ files)

**Priority:** High  
**Type:** Performance  

**Steps:**
1. Upload ZIP with 500 prompt files
2. Monitor progress

**Expected Results:**
- Progress shows percentage complete
- Import completes within 30 seconds
- All prompts accessible after import
- No memory issues

```typescript
test('handle large zip file', async ({ page }) => {
  await page.goto(`/projects/${fixtures.project.id}/prompts`);
  await page.click('[data-testid="import-prompts-btn"]');
  
  const startTime = Date.now();
  
  await page.locator('input[type="file"]').setInputFiles(fixtures.largeZipFile.path);
  await page.click('[data-testid="confirm-import"]');
  
  // Wait for completion
  await expect(page.locator('.toast-success')).toContainText('500 prompts imported', {
    timeout: 30000
  });
  
  const duration = Date.now() - startTime;
  expect(duration).toBeLessThan(30000);
});
```

---

## Performance Benchmarks

| Operation | Target | Max |
|-----------|--------|-----|
| Small ZIP (10 files) | <2s | 5s |
| Medium ZIP (100 files) | <10s | 20s |
| Large ZIP (500 files) | <30s | 60s |
| Conflict detection | <500ms | 1s |
| Frontmatter parsing | <10ms/file | 50ms |

---

## Error Scenarios

| Scenario | Expected Behavior |
|----------|-------------------|
| Network timeout during upload | Retry with resume capability |
| Disk space exhausted | Clear error, no partial state |
| Invalid YAML syntax | Skip file, log warning |
| Duplicate file paths in ZIP | Use last occurrence |
| Unsupported encoding | Convert or skip with warning |
