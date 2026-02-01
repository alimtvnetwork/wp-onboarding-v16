# Code Generation System - Architecture

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

This document defines the system architecture for the AI-Powered Code Generation System, including component responsibilities, data flow, and integration points.

**Cross-References:**
- [Overview](./00-overview.md)
- [Parallel Code Generation](./03-parallel-code-generation.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)

---

## System Components

### 1. Guideline Resolver

**Responsibility:** Merge and resolve coding guidelines from all hierarchy levels using **merge/extend** semantics.

```go
type GuidelineResolver struct {
    db           *gorm.DB
    cache        *GuidelineCache
    mergePolicy  MergePolicy
}

type MergePolicy int

const (
    MergePolicyExtend   MergePolicy = iota // Merge/extend (default)
    MergePolicyOverride                     // Full replacement
    MergePolicyPriority                     // Per-field priority
)

type ResolvedGuideline struct {
    GeneralRules     []Rule
    LanguageRules    map[string][]Rule  // language -> rules
    UserPreferences  []Rule
    ProjectRules     []Rule
    MergedPrompt     string             // Final prompt for LLM
    Sources          []GuidelineSource  // Which files contributed
}

// Resolution order with MERGE/EXTEND semantics:
// Layer 1: General (base)
// Layer 2: Language-specific (extends Layer 1)
// Layer 3: User preferences (extends Layer 2)
// Layer 4: Project-specific (extends Layer 3)
func (r *GuidelineResolver) Resolve(
    projectID string,
    userID string,
    languageCode string,
) (*ResolvedGuideline, error)
```

### 2. Plan Generator

**Responsibility:** Analyze specifications and create a generation plan with dependency graph.

```go
type PlanGenerator struct {
    specReader      SpecReader
    dependencyGraph *DependencyGraph
    fileAnalyzer    FileAnalyzer
}

type GenerationPlan struct {
    ID              string
    ProjectID       string
    SpecReferences  []SpecReference   // Which specs are being implemented
    Files           []PlannedFile     // Files to generate
    DependencyGraph *DependencyGraph  // File dependencies
    Batches         []ExecutionBatch  // Parallel execution batches
    CreatedAt       time.Time
}

type PlannedFile struct {
    ID              string
    Path            string            // Relative path in repo
    Language        string            // go, tsx, css, etc.
    Purpose         string            // Brief description
    SpecReferences  []string          // Spec file paths this implements
    Dependencies    []string          // Other PlannedFile IDs this depends on
    EstimatedTokens int               // Estimated output tokens
    Priority        int               // Execution priority
}

type ExecutionBatch struct {
    BatchNumber int
    FileIDs     []string  // Files that can execute in parallel
}
```

### 3. Parallel Execution Engine

**Responsibility:** Execute code generation in parallel batches respecting dependencies.

```go
type ParallelExecutionEngine struct {
    workerPool    *WorkerPool
    taskQueue     *TaskQueue
    resultChannel chan GenerationResult
    creditTracker *CreditTracker
}

type WorkerPool struct {
    maxWorkers     int
    activeWorkers  int
    workers        []*Worker
    projectWorkers map[string]int  // Workers per project
}

type Worker struct {
    ID          string
    ProjectID   string
    ModelClient ModelClient
    Status      WorkerStatus  // idle, busy, error
}

// Per-project parallel execution
func (e *ParallelExecutionEngine) Execute(
    ctx context.Context,
    plan *GenerationPlan,
    guidelines *ResolvedGuidelines,
) (*GenerationResult, error)
```

### 4. Code Generator

**Responsibility:** Generate individual code files using the coding model.

```go
type CodeGenerator struct {
    modelSelector  ModelSelector
    promptBuilder  PromptBuilder
    fileWriter     FileWriter
}

type GenerationRequest struct {
    PlannedFile    PlannedFile
    Guidelines     *ResolvedGuidelines
    SpecContent    string            // Relevant spec content
    ContextFiles   []ContextFile     // Already generated files for context
    ProjectContext ProjectContext
}

type GenerationResult struct {
    FileID      string
    Path        string
    Content     string
    TokensUsed  int
    ModelUsed   string
    Duration    time.Duration
    Success     bool
    Error       string
}
```

### 5. Consistency Checker

**Responsibility:** Validate cross-file consistency after generation.

```go
type ConsistencyChecker struct {
    importValidator  ImportValidator
    referenceChecker ReferenceChecker
    namingValidator  NamingValidator
}

type ConsistencyReport struct {
    TotalFiles      int
    ChecksPassed    int
    ChecksFailed    int
    Issues          []ConsistencyIssue
}

type ConsistencyIssue struct {
    Severity    string  // error, warning, info
    FilePath    string
    Line        int
    Message     string
    Suggestion  string
}
```

### 6. Build Verifier

**Responsibility:** Execute build verification using brun CLI.

```go
type BuildVerifier struct {
    brunRunner  *BrunRunner
    errorParser ErrorParser
}

type BuildResult struct {
    Language    string
    Success     bool
    ExitCode    int
    Errors      []BuildError
    Warnings    []BuildWarning
    Duration    time.Duration
}

// Runs brun check for each language in the generated repo
func (v *BuildVerifier) Verify(
    repoPath string,
    languages []string,
) (*BuildVerificationResult, error)
```

### 7. Git Manager

**Responsibility:** Handle all Git operations for generated repositories.

```go
type GitManager struct {
    localOps     LocalGitOperations
    remoteOps    RemoteGitOperations
    oauthManager OAuthManager
}

type LocalGitOperations interface {
    Init(repoPath string) error
    Add(repoPath string, files []string) error
    Commit(repoPath string, message string) error
    Status(repoPath string) (*GitStatus, error)
}

type RemoteGitOperations interface {
    AddRemote(repoPath, name, url string) error
    Pull(repoPath, remote, branch string) error
    Push(repoPath, remote, branch string) error
    Clone(url, destPath string) error
}
```

### 8. Credit Tracker

**Responsibility:** Track per-AI-request token consumption and manage credit balance.

```go
type CreditTracker struct {
    db           *gorm.DB
    pricingCache map[string]*ModelPricing
    mutex        sync.RWMutex
}

type ModelPricing struct {
    ID          uint    `gorm:"primaryKey"`
    ModelID     string  `gorm:"uniqueIndex;size:100"`
    ModelName   string  `gorm:"size:200"`
    InputRate   float64 // Cost per input token
    OutputRate  float64 // Cost per output token
    Category    string  `gorm:"size:50"`
    IsActive    bool    `gorm:"default:true"`
}

type UsageRecord struct {
    UserID       string
    SessionID    uint
    RequestType  string  // "code_generation", "fix_attempt"
    ModelID      string
    TokensInput  int64
    TokensOutput int64
}

// Credit calculation: (inputTokens × inputRate) + (outputTokens × outputRate)
func (c *CreditTracker) CalculateCost(inputTokens, outputTokens int64, modelID string) float64
func (c *CreditTracker) RecordUsage(usage *UsageRecord) (*CreditTransaction, error)
func (c *CreditTracker) GetBalance(userID string) (*CreditBalance, error)
func (c *CreditTracker) CheckBalance(userID string, estimatedTokens int64, modelID string) error
```

---

## Data Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Code Generation Pipeline                         │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────┐     ┌──────────────────┐     ┌──────────────────┐
│   User   │────▶│  API: Generate   │────▶│ Credit Check     │
│  Request │     │     Code         │     │ (HasSufficient?) │
└──────────┘     └──────────────────┘     └────────┬─────────┘
                                                    │
                                          ┌─────────▼─────────┐
                                          │ Guideline Resolver │
                                          │ (Merge 4 layers)  │
                                          └─────────┬─────────┘
                                                    │
                                          ┌─────────▼─────────┐
                                          │  Spec Reader      │
                                          │ (Load relevant    │
                                          │  specifications)  │
                                          └─────────┬─────────┘
                                                    │
                                          ┌─────────▼─────────┐
                                          │  Plan Generator   │
                                          │ (Create file plan │
                                          │  + dependencies)  │
                                          └─────────┬─────────┘
                                                    │
                        ┌───────────────────────────┼───────────────────────────┐
                        │                           │                           │
              ┌─────────▼─────────┐       ┌─────────▼─────────┐       ┌─────────▼─────────┐
              │   Git Manager     │       │ Parallel Execution │       │   Credit Tracker  │
              │ (Init local repo) │       │     Engine         │       │ (Track per-file)  │
              └─────────┬─────────┘       └─────────┬─────────┘       └───────────────────┘
                        │                           │
                        │                 ┌─────────▼─────────┐
                        │                 │   Batch 1 (||)    │
                        │                 │ ┌─────┐ ┌─────┐   │
                        │                 │ │ W1  │ │ W2  │   │
                        │                 │ └─────┘ └─────┘   │
                        │                 └─────────┬─────────┘
                        │                           │
                        │                 ┌─────────▼─────────┐
                        │                 │   Batch 2 (||)    │
                        │                 │ ┌─────┐ ┌─────┐   │
                        │                 │ │ W3  │ │ W4  │   │
                        │                 │ └─────┘ └─────┘   │
                        │                 └─────────┬─────────┘
                        │                           │
                        │                 ┌─────────▼─────────┐
                        │                 │ Consistency Check │
                        │                 └─────────┬─────────┘
                        │                           │
                        │                 ┌─────────▼─────────┐
                        │                 │  Build Verifier   │
                        │                 │   (brun check)    │
                        │                 └─────────┬─────────┘
                        │                           │
                        └───────────────────────────┤
                                                    │
                                          ┌─────────▼─────────┐
                                          │   Git Manager     │
                                          │ (Commit + Push)   │
                                          └─────────┬─────────┘
                                                    │
                                          ┌─────────▼─────────┐
                                          │   Response        │
                                          │ (Generation Report)│
                                          └───────────────────┘
```

---

## Integration Points

### 1. Build Runner CLI (brun)

```go
// Integration via BrunRunner wrapper
type BrunIntegration struct {
    runner *BrunRunner
}

func (b *BrunIntegration) VerifyBuild(repoPath string, lang string) (*BuildResult, error) {
    result, err := b.runner.Check(CheckOptions{
        WorkDir:  filepath.Join(repoPath, languageDir(lang)),
        Language: lang,
        JSON:     true,
    })
    // Parse structured output for AI fix loop if needed
    return parseBuildResult(result), err
}
```

### 2. LLM Model Selection

```go
// Coding model categories
const (
    ModelCategoryCoding1 = "coding1"  // Primary coding model
    ModelCategoryCoding2 = "coding2"  // Secondary/specialized
    ModelCategoryGolang  = "coding_go"
    ModelCategoryReact   = "coding_react"
    ModelCategoryPHP     = "coding_php"
)

type ModelSelector struct {
    modelConfig ModelConfiguration
}

func (s *ModelSelector) SelectModel(language string, complexity string) (*Model, error) {
    // Check language-specific model first
    if langModel := s.modelConfig.GetLanguageModel(language); langModel != nil {
        return langModel, nil
    }
    // Fall back to general coding model
    return s.modelConfig.GetCodingModel(complexity)
}
```

### 3. OAuth for GitHub/GitLab

```go
type OAuthManager struct {
    githubClient  *GitHubOAuthClient
    gitlabClient  *GitLabOAuthClient
    tokenStore    TokenStore
}

type OAuthConnection struct {
    ID           string
    UserID       string
    Provider     string    // github, gitlab
    AccessToken  string    // Encrypted
    RefreshToken string    // Encrypted
    ExpiresAt    time.Time
    Scopes       []string
    Connected    bool
}
```

---

## Concurrency Model

### Per-Project Parallel Execution

```go
// Each project gets its own worker allocation
type ProjectExecution struct {
    ProjectID     string
    MaxWorkers    int              // From project settings
    ActiveWorkers int
    Queue         *TaskQueue
    Mutex         sync.RWMutex
}

// Global execution manager
type ExecutionManager struct {
    projectExecutions map[string]*ProjectExecution
    totalWorkerLimit  int          // Global limit
    activeTotal       int
    mutex             sync.RWMutex
}

func (m *ExecutionManager) AllocateWorkers(projectID string, requested int) int {
    m.mutex.Lock()
    defer m.mutex.Unlock()
    
    available := m.totalWorkerLimit - m.activeTotal
    allocated := min(requested, available)
    
    m.projectExecutions[projectID].ActiveWorkers = allocated
    m.activeTotal += allocated
    
    return allocated
}
```

---

## State Management

### Generation Session States

```
                    ┌──────────────┐
                    │   PENDING    │
                    └──────┬───────┘
                           │ Start
                    ┌──────▼───────┐
                    │  RESOLVING   │ ← Guideline resolution
                    └──────┬───────┘
                           │
                    ┌──────▼───────┐
                    │   PLANNING   │ ← Plan generation
                    └──────┬───────┘
                           │
                    ┌──────▼───────┐
                    │  GENERATING  │ ← Parallel code generation
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
       ┌──────▼───────┐    │     ┌──────▼───────┐
       │   CHECKING   │    │     │   PAUSED     │
       └──────┬───────┘    │     └──────────────┘
              │            │
       ┌──────▼───────┐    │
       │  VERIFYING   │ ← brun check
       └──────┬───────┘    │
              │            │
       ┌──────▼───────┐    │
       │  COMMITTING  │ ← Git operations
       └──────┬───────┘    │
              │            │
       ┌──────▼───────┐    │     ┌──────────────┐
       │  COMPLETED   │    └────▶│    FAILED    │
       └──────────────┘          └──────────────┘
```

---

## Security Considerations

### 1. OAuth Token Storage
- Tokens encrypted at rest using AES-256-GCM
- Refresh tokens stored separately
- Automatic token refresh before expiration

### 2. Code Generation Sandbox
- Generated code written to isolated directories
- Path traversal prevention via PathManager
- No execution of generated code during generation

### 3. Credit System Protection
- Atomic credit deduction before generation
- Rollback on generation failure
- Rate limiting per user/project

---

## Related Specs

- [Parallel Code Generation](./03-parallel-code-generation.md)
- [Git Integration](./04-git-integration.md)
- [Build Verification](./06-build-verification.md)
- [Credit System](./07-credit-system.md)
