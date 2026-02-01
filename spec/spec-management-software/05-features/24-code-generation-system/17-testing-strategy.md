# Testing Strategy

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

This document defines the testing strategy for the Code Generation System, following the project's 60/30/10 integration-heavy test pyramid.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Implementation Guide](./14-implementation-guide.md)
- [Frontend Testing Strategy](../../05-features/testing/frontend-testing.md)

---

## Test Pyramid

```
                    ┌───────────────┐
                    │     E2E       │  10%
                    │  (Playwright) │
                    └───────────────┘
               ┌─────────────────────────┐
               │      Integration        │  30%
               │  (Mocked LLM, Git)      │
               └─────────────────────────┘
          ┌───────────────────────────────────┐
          │            Unit Tests             │  60%
          │  (Repositories, Services, Utils)  │
          └───────────────────────────────────┘
```

---

## Unit Tests (60%)

### Repository Tests

```go
// internal/codegen/repository/generation_run_test.go
package repository

import (
    "testing"
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestGenerationRunRepository_Create(t *testing.T) {
    db := setupTestDB(t)
    repo := NewGenerationRunRepository(db)
    
    run := &GenerationRun{
        ID:        "run-123",
        ProjectID: "proj-456",
        UserID:    "user-789",
        Status:    GenerationStatusPending,
    }
    
    err := repo.Create(run)
    require.NoError(t, err)
    
    // Verify
    found, err := repo.GetByID("run-123")
    require.NoError(t, err)
    assert.Equal(t, "proj-456", found.ProjectID)
    assert.Equal(t, GenerationStatusPending, found.Status)
}

func TestGenerationRunRepository_UpdateStatus(t *testing.T) {
    db := setupTestDB(t)
    repo := NewGenerationRunRepository(db)
    
    // Create run
    run := createTestRun(t, repo)
    
    // Update status
    err := repo.UpdateStatus(run.ID, GenerationStatusGenerating)
    require.NoError(t, err)
    
    // Verify
    found, _ := repo.GetByID(run.ID)
    assert.Equal(t, GenerationStatusGenerating, found.Status)
}

func TestGenerationRunRepository_ListByProject(t *testing.T) {
    db := setupTestDB(t)
    repo := NewGenerationRunRepository(db)
    
    // Create multiple runs
    createTestRunForProject(t, repo, "proj-1")
    createTestRunForProject(t, repo, "proj-1")
    createTestRunForProject(t, repo, "proj-2")
    
    // List
    runs, total, err := repo.ListByProject("proj-1", 1, 10)
    require.NoError(t, err)
    assert.Equal(t, 2, total)
    assert.Len(t, runs, 2)
}
```

### Guideline Resolver Tests

```go
// internal/codegen/guideline/resolver_test.go
package guideline

func TestResolver_Resolve_PriorityOverride(t *testing.T) {
    db := setupTestDB(t)
    resolver := NewResolver(db)
    
    // Seed guidelines at different levels
    createGuideline(t, db, CodingGuideline{
        Level:        GuidelineLevelGeneral,
        Content:      "## Naming\nUse camelCase",
    })
    createGuideline(t, db, CodingGuideline{
        Level:        GuidelineLevelLanguage,
        LanguageCode: "go",
        Content:      "## Naming\nUse snake_case for files",
    })
    createGuideline(t, db, CodingGuideline{
        Level:        GuidelineLevelProject,
        ProjectID:    "proj-1",
        Content:      "## Naming\nUse PascalCase for exports",
    })
    
    // Resolve
    result, err := resolver.Resolve("proj-1", "user-1", "go")
    require.NoError(t, err)
    
    // Project guideline should override language guideline
    assert.Contains(t, result.MergedContent, "PascalCase for exports")
    assert.NotContains(t, result.MergedContent, "snake_case")
    
    // Should track overrides
    assert.Len(t, result.Overrides, 1)
    assert.Equal(t, "Naming", result.Overrides[0].Section)
}

func TestResolver_Resolve_MergeNonConflicting(t *testing.T) {
    db := setupTestDB(t)
    resolver := NewResolver(db)
    
    // Create guidelines with different sections
    createGuideline(t, db, CodingGuideline{
        Level:   GuidelineLevelGeneral,
        Content: "## Error Handling\nHandle all errors",
    })
    createGuideline(t, db, CodingGuideline{
        Level:        GuidelineLevelLanguage,
        LanguageCode: "go",
        Content:      "## Testing\nWrite table-driven tests",
    })
    
    result, err := resolver.Resolve("proj-1", "user-1", "go")
    require.NoError(t, err)
    
    // Both sections should be present
    assert.Contains(t, result.MergedContent, "Handle all errors")
    assert.Contains(t, result.MergedContent, "table-driven tests")
    assert.Empty(t, result.Overrides)
}
```

### Dependency Graph Tests

```go
// internal/codegen/plan/dependency_graph_test.go
package plan

func TestDependencyGraph_TopologicalSort(t *testing.T) {
    graph := NewDependencyGraph()
    
    // Create files with dependencies
    // A -> B -> C (C depends on B, B depends on A)
    graph.AddNode("A", "types.go", "go")
    graph.AddNode("B", "service.go", "go")
    graph.AddNode("C", "handler.go", "go")
    
    graph.AddEdge("B", "A")  // B depends on A
    graph.AddEdge("C", "B")  // C depends on B
    
    batches, err := graph.TopologicalSort()
    require.NoError(t, err)
    
    // Should have 3 batches
    assert.Len(t, batches, 3)
    assert.Contains(t, batches[0], "A")  // A first (no deps)
    assert.Contains(t, batches[1], "B")  // B second
    assert.Contains(t, batches[2], "C")  // C last
}

func TestDependencyGraph_ParallelBatch(t *testing.T) {
    graph := NewDependencyGraph()
    
    // Independent files can run in parallel
    graph.AddNode("A", "types.go", "go")
    graph.AddNode("B", "utils.go", "go")
    graph.AddNode("C", "config.go", "go")
    
    batches, err := graph.TopologicalSort()
    require.NoError(t, err)
    
    // All in first batch (parallel)
    assert.Len(t, batches, 1)
    assert.Len(t, batches[0], 3)
}

func TestDependencyGraph_CircularDependency(t *testing.T) {
    graph := NewDependencyGraph()
    
    graph.AddNode("A", "a.go", "go")
    graph.AddNode("B", "b.go", "go")
    
    graph.AddEdge("A", "B")
    graph.AddEdge("B", "A")  // Circular!
    
    _, err := graph.TopologicalSort()
    assert.Error(t, err)
    assert.Contains(t, err.Error(), "circular")
}
```

### Credit Tracker Tests

```go
// internal/codegen/credit/tracker_test.go
package credit

func TestCreditTracker_Consume(t *testing.T) {
    db := setupTestDB(t)
    tracker := NewCreditTracker(db, DefaultRates())
    
    // Create user with balance
    createUserCredits(t, db, "user-1", 100.0)
    
    // Consume credits
    err := tracker.Consume(CreditConsumption{
        UserID:    "user-1",
        ProjectID: "proj-1",
        Type:      CreditTypeFileGenerated,
        Amount:    5.0,
    })
    require.NoError(t, err)
    
    // Verify balance
    balance, _ := tracker.GetBalance("user-1")
    assert.Equal(t, 95.0, balance)
}

func TestCreditTracker_InsufficientCredits(t *testing.T) {
    db := setupTestDB(t)
    tracker := NewCreditTracker(db, DefaultRates())
    
    createUserCredits(t, db, "user-1", 5.0)
    
    err := tracker.Consume(CreditConsumption{
        UserID: "user-1",
        Amount: 10.0,
    })
    
    assert.ErrorIs(t, err, ErrCreditsInsufficient)
}

func TestCreditTracker_ConcurrentConsumption(t *testing.T) {
    db := setupTestDB(t)
    tracker := NewCreditTracker(db, DefaultRates())
    
    createUserCredits(t, db, "user-1", 100.0)
    
    // Concurrent consumption
    var wg sync.WaitGroup
    for i := 0; i < 10; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            tracker.Consume(CreditConsumption{
                UserID: "user-1",
                Amount: 5.0,
            })
        }()
    }
    wg.Wait()
    
    // Verify final balance (should be 50)
    balance, _ := tracker.GetBalance("user-1")
    assert.Equal(t, 50.0, balance)
}
```

---

## Integration Tests (30%)

### Code Generation Pipeline

```go
// internal/codegen/integration_test.go
package codegen

func TestGenerationPipeline_FullFlow(t *testing.T) {
    // Setup
    db := setupTestDB(t)
    mockLLM := NewMockLLMClient(t)
    mockGit := NewMockGitOperations(t)
    
    // Configure mock LLM responses
    mockLLM.On("Generate", mock.Anything, mock.Anything).
        Return(&LLMResponse{
            Content:    "```go\npackage main\n\nfunc main() {}\n```",
            TokensUsed: 100,
        }, nil)
    
    service := NewCodeGenerationService(
        db,
        mockLLM,
        mockGit,
        NewCreditTracker(db, DefaultRates()),
    )
    
    // Create test data
    project := createTestProject(t, db)
    user := createTestUser(t, db)
    createUserCredits(t, db, user.ID, 100.0)
    
    // Execute generation
    result, err := service.Generate(context.Background(), GenerationRequest{
        ProjectID:      project.ID,
        UserID:         user.ID,
        SpecReferences: []string{"spec/01-feature.md"},
    })
    
    require.NoError(t, err)
    assert.Equal(t, GenerationStatusCompleted, result.Status)
    assert.Greater(t, result.FilesGenerated, 0)
    
    // Verify credits consumed
    balance, _ := service.creditTracker.GetBalance(user.ID)
    assert.Less(t, balance, 100.0)
    
    // Verify git commit
    mockGit.AssertCalled(t, "Commit", mock.Anything, mock.Anything)
}
```

### Guideline Resolution Pipeline

```go
func TestGuidelineResolution_Integration(t *testing.T) {
    db := setupTestDB(t)
    
    // Seed all levels of guidelines
    seedDefaultGuidelines(t, db)
    
    project := createTestProject(t, db)
    user := createTestUser(t, db)
    
    // Add user preference
    createUserGuideline(t, db, user.ID, "go", "## Custom\nUser preference rule")
    
    // Add project override
    createProjectGuideline(t, db, project.ID, "go", "## Custom\nProject override rule")
    
    resolver := NewResolver(db)
    result, err := resolver.Resolve(project.ID, user.ID, "go")
    
    require.NoError(t, err)
    
    // Project override should win
    assert.Contains(t, result.MergedContent, "Project override rule")
    assert.NotContains(t, result.MergedContent, "User preference rule")
    
    // Should include non-conflicting sections from all levels
    assert.Contains(t, result.MergedContent, "Error Handling")  // From general
}
```

### Git Integration

```go
func TestGitIntegration_LocalOperations(t *testing.T) {
    // Create temp directory
    tmpDir := t.TempDir()
    
    gitManager := NewGitManager(tmpDir)
    
    // Initialize
    err := gitManager.InitRepository("proj-1", "Test Project")
    require.NoError(t, err)
    
    // Verify structure
    assert.DirExists(t, filepath.Join(tmpDir, "proj-1", ".git"))
    assert.FileExists(t, filepath.Join(tmpDir, "proj-1", "README.md"))
    assert.DirExists(t, filepath.Join(tmpDir, "proj-1", "BE"))
    assert.DirExists(t, filepath.Join(tmpDir, "proj-1", "FE"))
    
    // Add file and commit
    testFile := filepath.Join(tmpDir, "proj-1", "BE", "main.go")
    os.WriteFile(testFile, []byte("package main"), 0644)
    
    err = gitManager.Commit("proj-1", "Add main.go", nil)
    require.NoError(t, err)
    
    // Verify commit exists
    cmd := exec.Command("git", "log", "--oneline")
    cmd.Dir = filepath.Join(tmpDir, "proj-1")
    output, _ := cmd.Output()
    assert.Contains(t, string(output), "Add main.go")
}
```

### Build Verification with Mock brun

```go
func TestBuildVerification_WithMockBrun(t *testing.T) {
    mockBrun := NewMockBrunRunner(t)
    mockAIFix := NewMockAIFixService(t)
    
    // First check fails
    mockBrun.On("Check", mock.Anything, CheckOptions{Language: "go"}).
        Return(`{"exit_code": 1, "errors": [{"file": "main.go", "line": 10, "message": "undefined: foo"}]}`, nil).
        Once()
    
    // After fix, check succeeds
    mockBrun.On("Check", mock.Anything, CheckOptions{Language: "go"}).
        Return(`{"exit_code": 0, "errors": []}`, nil).
        Once()
    
    mockAIFix.On("AttemptFix", mock.Anything, mock.Anything, mock.Anything, mock.Anything).
        Return(&FixAttempt{Success: true, FilesFixed: []string{"main.go"}}, nil)
    
    verifier := NewBuildVerifier(mockBrun, mockAIFix, nil, nil)
    
    result, err := verifier.Verify(context.Background(), "/repo", []string{"go"}, "run-1")
    
    require.NoError(t, err)
    assert.True(t, result.Success)
    assert.Equal(t, 1, result.FixAttempts)
}
```

---

## E2E Tests (10%)

### Generation Wizard Flow

```typescript
// tests/e2e/code-generation.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Code Generation', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.fill('[name="email"]', 'test@example.com');
        await page.fill('[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/dashboard');
    });

    test('should complete full generation flow', async ({ page }) => {
        // Navigate to project
        await page.click('[data-testid="project-card-1"]');
        
        // Open generation wizard
        await page.click('[data-testid="generate-code-btn"]');
        
        // Select specifications
        await page.click('[data-testid="spec-checkbox-feature-1"]');
        await page.click('[data-testid="spec-checkbox-feature-2"]');
        
        // Review estimate
        await page.click('[data-testid="next-step-btn"]');
        await expect(page.locator('[data-testid="credit-estimate"]')).toBeVisible();
        
        // Start generation
        await page.click('[data-testid="start-generation-btn"]');
        
        // Wait for progress
        await expect(page.locator('[data-testid="generation-progress"]')).toBeVisible();
        
        // Wait for completion (with timeout)
        await expect(page.locator('[data-testid="generation-complete"]'))
            .toBeVisible({ timeout: 60000 });
        
        // Verify files generated
        await expect(page.locator('[data-testid="files-generated-count"]'))
            .not.toHaveText('0');
    });

    test('should handle insufficient credits', async ({ page }) => {
        // Reduce credits first (via API or test setup)
        
        await page.click('[data-testid="project-card-1"]');
        await page.click('[data-testid="generate-code-btn"]');
        await page.click('[data-testid="spec-checkbox-feature-1"]');
        await page.click('[data-testid="next-step-btn"]');
        
        // Should show warning
        await expect(page.locator('[data-testid="insufficient-credits-warning"]'))
            .toBeVisible();
        
        // Start button should be disabled
        await expect(page.locator('[data-testid="start-generation-btn"]'))
            .toBeDisabled();
    });
});
```

### Git Connection Flow

```typescript
test.describe('Git Integration', () => {
    test('should connect to GitHub', async ({ page }) => {
        await page.goto('/projects/1/settings');
        
        await page.click('[data-testid="git-integration-tab"]');
        await page.click('[data-testid="connect-github-btn"]');
        
        // OAuth popup handling
        const [popup] = await Promise.all([
            page.waitForEvent('popup'),
            page.click('[data-testid="authorize-github-btn"]'),
        ]);
        
        // Mock OAuth callback
        await popup.waitForURL(/github\.com\/login/);
        
        // After successful auth
        await expect(page.locator('[data-testid="github-connected"]'))
            .toBeVisible();
    });
});
```

---

## Test Data Factories

```go
// internal/codegen/testutil/factories.go
package testutil

import (
    "github.com/go-faker/faker/v4"
)

func NewTestProject(t *testing.T, db *gorm.DB) *Project {
    project := &Project{
        ID:   faker.UUIDHyphenated(),
        Name: faker.Word(),
    }
    require.NoError(t, db.Create(project).Error)
    return project
}

func NewTestGenerationRun(t *testing.T, db *gorm.DB, projectID, userID string) *GenerationRun {
    run := &GenerationRun{
        ID:        faker.UUIDHyphenated(),
        ProjectID: projectID,
        UserID:    userID,
        Status:    GenerationStatusPending,
    }
    require.NoError(t, db.Create(run).Error)
    return run
}

func NewTestGuideline(t *testing.T, db *gorm.DB, level GuidelineLevel) *CodingGuideline {
    guideline := &CodingGuideline{
        ID:      faker.UUIDHyphenated(),
        Level:   level,
        Name:    faker.Sentence(),
        Content: "## " + faker.Word() + "\n" + faker.Paragraph(),
    }
    require.NoError(t, db.Create(guideline).Error)
    return guideline
}
```

---

## Mock Implementations

### Mock LLM Client

```go
// internal/codegen/testutil/mock_llm.go
package testutil

type MockLLMClient struct {
    mock.Mock
}

func (m *MockLLMClient) Generate(ctx context.Context, prompt string) (*LLMResponse, error) {
    args := m.Called(ctx, prompt)
    if args.Get(0) == nil {
        return nil, args.Error(1)
    }
    return args.Get(0).(*LLMResponse), args.Error(1)
}

func NewMockLLMWithDefaultResponse(t *testing.T) *MockLLMClient {
    m := &MockLLMClient{}
    m.On("Generate", mock.Anything, mock.Anything).Return(&LLMResponse{
        Content:    "```go\npackage main\n```",
        TokensUsed: 50,
    }, nil)
    return m
}
```

---

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/codegen-tests.yml
name: Code Generation Tests

on:
  push:
    paths:
      - 'internal/codegen/**'
      - 'tests/e2e/code-generation.spec.ts'

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-go@v5
        with:
          go-version: '1.21'
      - run: go test -v -race -coverprofile=coverage.out ./internal/codegen/...
      - uses: codecov/codecov-action@v4

  integration-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-go@v5
      - run: go test -v -tags=integration ./internal/codegen/...

  e2e-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
      - run: npm ci
      - run: npx playwright install
      - run: npx playwright test tests/e2e/code-generation.spec.ts
```

---

## Related Specs

- [Architecture](./01-architecture.md)
- [Implementation Guide](./14-implementation-guide.md)
- [E2E Integration Scenarios](../testing/e2e-integration-scenarios.md)
