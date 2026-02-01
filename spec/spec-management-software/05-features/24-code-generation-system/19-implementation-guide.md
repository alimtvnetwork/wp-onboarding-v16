# Implementation Guide

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

This document provides a phased implementation roadmap for the AI-Powered Code Generation System. Each phase is designed to be small and focused, enabling incremental development and testing.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [All Feature Specs](./00-overview.md)

---

## Implementation Phases

### Phase Summary

| Phase | Name | Duration | Dependencies |
|-------|------|----------|--------------|
| 1 | Foundation & Data Models | 3 days | None |
| 2 | Coding Guidelines Hierarchy | 2 days | Phase 1 |
| 3 | Plan Generator | 2 days | Phase 2 |
| 4 | Parallel Execution Engine | 3 days | Phase 3 |
| 5 | Git Integration | 3 days | Phase 1 |
| 6 | Build Verification | 2 days | Phase 4, brun CLI |
| 7 | Credit System | 2 days | Phase 4 |
| 8 | API & Frontend | 3 days | All previous |

**Total Duration:** 20 days

---

## Phase 1: Foundation & Data Models

**Duration:** 3 days  
**Dependencies:** None

### Objectives

- Set up folder structure for code generation module
- Implement core database entities
- Create base services and interfaces

### Tasks

#### Day 1: Project Setup

- [ ] Create `internal/codegen/` package structure
- [ ] Define interfaces in `internal/codegen/interfaces.go`
- [ ] Set up configuration keys in seeding

```go
// internal/codegen/interfaces.go
type CodeGenerationService interface {
    Generate(ctx context.Context, request *GenerationRequest) (*GenerationResult, error)
    GetStatus(runID string) (*GenerationStatus, error)
    Cancel(runID string) error
}

type PlanGenerator interface {
    CreatePlan(projectID string, specRefs []string) (*GenerationPlan, error)
}

type GuidelineResolver interface {
    Resolve(projectID, userID, language string) (*ResolvedGuidelines, error)
}
```

#### Day 2: Data Models

- [ ] Implement `CodingGuideline` entity
- [ ] Implement `GenerationRun` entity
- [ ] Implement `GeneratedFile` entity
- [ ] Run database migrations

```go
// internal/codegen/model/entities.go
type GenerationRun struct {
    ID            string           `gorm:"primaryKey;type:text"`
    ProjectID     string           `gorm:"type:text;not null;index"`
    UserID        string           `gorm:"type:text;not null;index"`
    Status        GenerationStatus `gorm:"type:text;not null"`
    PlanJSON      string           `gorm:"type:text"`
    FilesTotal    int              `gorm:"type:integer"`
    FilesComplete int              `gorm:"type:integer"`
    TokensUsed    int              `gorm:"type:integer"`
    CreditsUsed   float64          `gorm:"type:real"`
    ErrorMessage  string           `gorm:"type:text"`
    StartedAt     time.Time
    CompletedAt   *time.Time
    CreatedAt     time.Time
}

type GeneratedFile struct {
    ID              string    `gorm:"primaryKey;type:text"`
    GenerationRunID string    `gorm:"type:text;not null;index"`
    Path            string    `gorm:"type:text;not null"`
    Language        string    `gorm:"type:text"`
    Content         string    `gorm:"type:text"`
    TokensUsed      int       `gorm:"type:integer"`
    Status          string    `gorm:"type:text"`  // pending, generated, failed
    ErrorMessage    string    `gorm:"type:text"`
    CreatedAt       time.Time
}
```

#### Day 3: Repository & Base Services

- [ ] Implement `GenerationRunRepository`
- [ ] Implement `CodingGuidelineRepository`
- [ ] Create base `CodeGenerationService` shell
- [ ] Add unit tests for repositories

### Deliverables

- [ ] Database migrations for all entities
- [ ] Repository implementations with CRUD
- [ ] Configuration keys seeded
- [ ] Unit tests passing

---

## Phase 2: Coding Guidelines Hierarchy

**Duration:** 2 days  
**Dependencies:** Phase 1

### Objectives

- Implement 4-layer guideline system
- Build guideline resolution with priority override
- Seed default guidelines

### Tasks

#### Day 1: Guideline Store & Resolution

- [ ] Implement `GuidelineStore` for each level
- [ ] Implement `GuidelineResolver` with priority logic
- [ ] Add section parsing (H2 headers as sections)

```go
// internal/codegen/guideline/resolver.go
func (r *Resolver) Resolve(projectID, userID, language string) (*ResolvedGuidelines, error) {
    // 1. Load guidelines in priority order
    general := r.loadGeneral()
    lang := r.loadLanguage(language)
    user := r.loadUser(userID)
    project := r.loadProject(projectID)
    
    // 2. Merge with override tracking
    merged, overrides := r.mergeWithOverrides(general, lang, user, project)
    
    return &ResolvedGuidelines{
        MergedContent: merged,
        Overrides:     overrides,
        Sources:       r.getSources(general, lang, user, project),
    }, nil
}
```

#### Day 2: Seeding & Testing

- [ ] Create default general guidelines
- [ ] Create Go and React language guidelines
- [ ] Seed guidelines on first run
- [ ] Write integration tests for resolution

### Deliverables

- [ ] Guideline resolution working
- [ ] Default guidelines seeded
- [ ] Override tracking functional
- [ ] Integration tests passing

---

## Phase 3: Plan Generator

**Duration:** 2 days  
**Dependencies:** Phase 2

### Objectives

- Implement specification analysis
- Build dependency graph with topological sort
- Generate execution batches

### Tasks

#### Day 1: Spec Analysis & File Planning

- [ ] Implement `SpecAnalyzer` to extract file requirements
- [ ] Create `PlannedFile` generation from specs
- [ ] Build initial dependency detection

```go
// internal/codegen/plan/analyzer.go
type SpecAnalyzer struct {
    specReader SpecReader
}

func (a *SpecAnalyzer) Analyze(specRefs []string) ([]PlannedFile, error) {
    var files []PlannedFile
    
    for _, ref := range specRefs {
        spec, err := a.specReader.Read(ref)
        if err != nil {
            return nil, err
        }
        
        // Extract file requirements from spec
        extracted := a.extractFileRequirements(spec)
        files = append(files, extracted...)
    }
    
    return files, nil
}
```

#### Day 2: Dependency Graph & Batching

- [ ] Implement `DependencyGraph` with topological sort
- [ ] Create batch generation algorithm
- [ ] Handle circular dependency detection
- [ ] Write tests for graph operations

### Deliverables

- [ ] Plan generation from specs
- [ ] Dependency graph working
- [ ] Batch creation functional
- [ ] Circular dependency detection

---

## Phase 4: Parallel Execution Engine

**Duration:** 3 days  
**Dependencies:** Phase 3

### Objectives

- Implement worker pool
- Build parallel batch execution
- Create code generator with LLM integration

### Tasks

#### Day 1: Worker Pool

- [ ] Implement `WorkerPool` with per-project allocation
- [ ] Create `Worker` goroutine lifecycle
- [ ] Add task queue and result channels

#### Day 2: Code Generator

- [ ] Implement `CodeGenerator` with prompt building
- [ ] Integrate with LLM model selector
- [ ] Add context file management
- [ ] Create file writer with path validation

```go
// internal/codegen/generator/code_generator.go
func (g *CodeGenerator) Generate(ctx context.Context, task *GenerationTask) (*GenerationResult, error) {
    // 1. Build prompt
    prompt := g.promptBuilder.Build(task.Guidelines, task.File, task.Context)
    
    // 2. Select model
    model, err := g.modelSelector.SelectModel(task.File.Language, "normal")
    if err != nil {
        return nil, err
    }
    
    // 3. Generate code
    response, err := model.Generate(ctx, prompt)
    if err != nil {
        return nil, err
    }
    
    // 4. Extract and validate code
    code := g.extractCode(response, task.File.Language)
    
    return &GenerationResult{
        Path:       task.File.Path,
        Content:    code,
        TokensUsed: response.TokensUsed,
    }, nil
}
```

#### Day 3: Batch Execution & Integration

- [ ] Implement `ParallelExecutionEngine`
- [ ] Add progress tracking
- [ ] Implement cancellation support
- [ ] Write integration tests

### Deliverables

- [ ] Worker pool functional
- [ ] Parallel generation working
- [ ] Progress tracking
- [ ] Integration tests passing

---

## Phase 5: Git Integration

**Duration:** 3 days  
**Dependencies:** Phase 1

### Objectives

- Implement local git operations
- Add GitHub/GitLab OAuth
- Build commit and push workflows

### Tasks

#### Day 1: Local Git Operations

- [ ] Implement `LocalGitOperations` (init, add, commit, status)
- [ ] Create repository initialization with structure
- [ ] Add commit message builder

#### Day 2: OAuth Integration

- [ ] Implement `GitHubOAuthClient`
- [ ] Implement `GitLabOAuthClient`
- [ ] Create token storage with encryption
- [ ] Add OAuth callback handlers

#### Day 3: Remote Operations

- [ ] Implement push workflow with credential setup
- [ ] Add pull and conflict detection
- [ ] Create repository connection flow
- [ ] Write integration tests

### Deliverables

- [ ] Local git operations working
- [ ] OAuth flow functional
- [ ] Push/pull working
- [ ] README auto-update on connect

---

## Phase 6: Build Verification

**Duration:** 2 days  
**Dependencies:** Phase 4, brun CLI

### Objectives

- Integrate with brun CLI
- Implement build verification after generation
- Add AI fix loop for build errors

### Tasks

#### Day 1: brun Integration

- [ ] Implement `BrunRunner` wrapper
- [ ] Create `BuildVerifier` service
- [ ] Add error parsing for build failures

```go
// internal/codegen/build/verifier.go
func (v *BuildVerifier) Verify(repoPath string, languages []string) (*BuildResult, error) {
    results := make(map[string]*LanguageBuildResult)
    
    for _, lang := range languages {
        dir := v.getLanguageDir(repoPath, lang)
        
        result, err := v.brunRunner.Check(CheckOptions{
            WorkDir:  dir,
            Language: lang,
            JSON:     true,
        })
        
        results[lang] = &LanguageBuildResult{
            Success: result.ExitCode == 0,
            Errors:  v.parseErrors(result.Output),
        }
    }
    
    return &BuildResult{Results: results}, nil
}
```

#### Day 2: AI Fix Loop

- [ ] Implement error-to-prompt conversion
- [ ] Create fix generation workflow
- [ ] Add retry limit and circuit breaker
- [ ] Write tests for fix loop

### Deliverables

- [ ] brun integration working
- [ ] Build verification after generation
- [ ] AI fix loop functional (with limits)
- [ ] Tests passing

---

## Phase 7: Credit System

**Duration:** 2 days  
**Dependencies:** Phase 4

### Objectives

- Implement credit tracking
- Add consumption at each trigger point
- Build usage dashboard API

### Tasks

#### Day 1: Credit Tracker

- [ ] Implement `CreditTracker` service
- [ ] Create `UserCredits` entity and repository
- [ ] Add atomic consumption with transactions
- [ ] Implement pre-generation credit check

#### Day 2: Plans & Dashboard

- [ ] Seed default credit plans
- [ ] Implement monthly free credit reset
- [ ] Create usage API endpoints
- [ ] Add credit estimation endpoint

### Deliverables

- [ ] Credit consumption tracking
- [ ] Balance management
- [ ] Usage API endpoints
- [ ] Credit estimation working

---

## Phase 8: API & Frontend

**Duration:** 3 days  
**Dependencies:** All previous

### Objectives

- Create REST API for code generation
- Build frontend components
- End-to-end testing

### Tasks

#### Day 1: REST API

- [ ] Implement `/api/v1/codegen/generate` endpoint
- [ ] Implement `/api/v1/codegen/{runId}/status` endpoint
- [ ] Implement `/api/v1/guidelines` endpoints
- [ ] Add WebSocket for progress updates

#### Day 2: Frontend Components

- [ ] Create `GenerationWizard` component
- [ ] Build `GuidelineEditor` component
- [ ] Implement `GenerationProgress` component
- [ ] Add `CreditDashboard` component

#### Day 3: Integration & E2E

- [ ] Full flow integration testing
- [ ] E2E tests with Playwright
- [ ] Performance testing
- [ ] Documentation

### Deliverables

- [ ] Full API functional
- [ ] Frontend components complete
- [ ] E2E tests passing
- [ ] Documentation complete

---

## Testing Strategy

### Unit Tests (60%)

- Repository CRUD operations
- Guideline resolution logic
- Dependency graph algorithms
- Credit calculation

### Integration Tests (30%)

- Full generation pipeline (mocked LLM)
- Git operations with test repositories
- Credit consumption flows

### E2E Tests (10%)

- Complete generation wizard flow
- OAuth connection flow
- Credit purchase and usage

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| LLM response quality | Add retry with different prompts |
| Circular dependencies | Detect early, fail fast |
| Git conflicts | Pre-pull with stash |
| Credit abuse | Rate limiting, daily caps |
| Long generation times | Progress updates, cancellation |

---

## Definition of Done

Each phase is complete when:

- [ ] All tasks checked off
- [ ] Unit tests passing (>80% coverage)
- [ ] Integration tests passing
- [ ] Code reviewed
- [ ] Documentation updated
- [ ] No critical bugs

---

## Related Specs

- [Architecture](./01-architecture.md)
- [All Feature Documents](./00-overview.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
