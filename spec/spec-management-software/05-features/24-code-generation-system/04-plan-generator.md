# Code Generation System - Plan Generator

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Analyzes specifications and creates a topologically-sorted file generation plan. Files are grouped into execution batches based on dependencies.

**Cross-References:**
- [Overview](./00-overview.md)
- [Architecture](./01-architecture.md)
- [Parallel Executor](./05-parallel-executor.md)

---

## Plan Generation Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          PLAN GENERATION PIPELINE                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                   │
│  │  Parse Specs │───▶│ Extract File │───▶│   Analyze    │                   │
│  │              │    │  References  │    │ Dependencies │                   │
│  └──────────────┘    └──────────────┘    └──────────────┘                   │
│                                                 │                            │
│         ┌───────────────────────────────────────┘                            │
│         ▼                                                                    │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                   │
│  │  Topological │───▶│   Create     │───▶│   Estimate   │                   │
│  │     Sort     │    │   Batches    │    │    Tokens    │                   │
│  └──────────────┘    └──────────────┘    └──────────────┘                   │
│                                                 │                            │
│                                                 ▼                            │
│                                          ┌──────────────┐                    │
│                                          │ Save Plan to │                    │
│                                          │   Database   │                    │
│                                          └──────────────┘                    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Data Models

### GenerationPlan

```go
type GenerationPlan struct {
    ID              uint            `gorm:"primaryKey"`
    UUID            string          `gorm:"uniqueIndex;size:36"`
    ProjectID       string          `gorm:"index;size:36"`
    Name            string          `gorm:"size:200"`
    Status          PlanStatus      `gorm:"default:0"`
    TotalFiles      int
    TotalBatches    int
    EstimatedTokens int64
    ActualTokens    int64
    SpecSnapshot    string          `gorm:"type:text"`  // JSON snapshot of specs
    CreatedAt       time.Time
    UpdatedAt       time.Time
    StartedAt       *time.Time
    CompletedAt     *time.Time
}

type PlanStatus int

const (
    PlanStatusDraft      PlanStatus = iota  // 0: Being created
    PlanStatusReady                          // 1: Ready to execute
    PlanStatusRunning                        // 2: Execution in progress
    PlanStatusPaused                         // 3: Paused by user
    PlanStatusCompleted                      // 4: All files generated
    PlanStatusFailed                         // 5: Critical failure
    PlanStatusCancelled                      // 6: Cancelled by user
)
```

### PlannedFile

```go
type PlannedFile struct {
    ID              uint            `gorm:"primaryKey"`
    PlanID          uint            `gorm:"index"`
    Plan            *GenerationPlan `gorm:"foreignKey:PlanID"`
    
    FilePath        string          `gorm:"size:500"`
    Language        string          `gorm:"size:50"`
    FileType        FileType        `gorm:"default:0"`
    
    SpecReferences  string          `gorm:"type:text"`  // JSON array of spec paths
    Description     string          `gorm:"type:text"`
    
    BatchIndex      int             `gorm:"index"`
    Priority        int             `gorm:"default:0"`
    
    Dependencies    string          `gorm:"type:text"`  // JSON array of file paths
    DependencyCount int
    
    EstimatedTokens int64
    ActualTokens    int64
    
    Status          FileStatus      `gorm:"default:0"`
    ErrorMessage    string          `gorm:"type:text"`
    
    CreatedAt       time.Time
    UpdatedAt       time.Time
    GeneratedAt     *time.Time
}

type FileType int

const (
    FileTypeSource      FileType = iota  // 0: Source code
    FileTypeConfig                        // 1: Configuration
    FileTypeTest                          // 2: Test file
    FileTypeDocumentation                 // 3: Documentation
    FileTypeMigration                     // 4: Database migration
)

type FileStatus int

const (
    FileStatusPending    FileStatus = iota  // 0: Not started
    FileStatusQueued                         // 1: In queue
    FileStatusGenerating                     // 2: Being generated
    FileStatusGenerated                      // 3: Successfully generated
    FileStatusFailed                         // 4: Generation failed
    FileStatusSkipped                        // 5: Skipped (already exists)
)
```

### ExecutionBatch

```go
type ExecutionBatch struct {
    ID              uint            `gorm:"primaryKey"`
    PlanID          uint            `gorm:"index"`
    Plan            *GenerationPlan `gorm:"foreignKey:PlanID"`
    
    BatchIndex      int             `gorm:"index"`
    FileCount       int
    
    DependsOnBatches string         `gorm:"type:text"`  // JSON array of batch indices
    
    Status          BatchStatus     `gorm:"default:0"`
    
    StartedAt       *time.Time
    CompletedAt     *time.Time
}

type BatchStatus int

const (
    BatchStatusPending   BatchStatus = iota
    BatchStatusReady
    BatchStatusRunning
    BatchStatusCompleted
    BatchStatusFailed
)
```

---

## Topological Sort Algorithm

```go
type DependencyGraph struct {
    nodes    map[string]*FileNode
    edges    map[string][]string  // file -> dependencies
    inDegree map[string]int
}

type FileNode struct {
    FilePath     string
    Dependencies []string
    Dependents   []string
}

func (g *DependencyGraph) TopologicalSort() ([][]string, error) {
    batches := [][]string{}
    remaining := make(map[string]bool)
    
    // Initialize remaining files
    for path := range g.nodes {
        remaining[path] = true
    }
    
    for len(remaining) > 0 {
        // Find all files with no unresolved dependencies
        batch := []string{}
        for path := range remaining {
            if g.allDependenciesResolved(path, remaining) {
                batch = append(batch, path)
            }
        }
        
        if len(batch) == 0 {
            return nil, fmt.Errorf("circular dependency detected")
        }
        
        // Remove batch from remaining
        for _, path := range batch {
            delete(remaining, path)
        }
        
        batches = append(batches, batch)
    }
    
    return batches, nil
}

func (g *DependencyGraph) allDependenciesResolved(path string, remaining map[string]bool) bool {
    for _, dep := range g.edges[path] {
        if remaining[dep] {
            return false
        }
    }
    return true
}
```

---

## Spec Parsing

### Spec Reference Extraction

```go
type SpecParser struct {
    fileScanner *FileScanner
}

type ParsedSpec struct {
    Path            string
    Title           string
    Version         string
    FilesToGenerate []FileDefinition
    Dependencies    []string        // Other specs this depends on
}

type FileDefinition struct {
    Path            string
    Language        string
    Type            FileType
    Description     string
    Imports         []string        // Expected imports (dependencies)
    ExternalDeps    []string        // External spec references
}

func (p *SpecParser) ParseSpec(specPath string) (*ParsedSpec, error) {
    content, err := os.ReadFile(specPath)
    if err != nil {
        return nil, err
    }
    
    parsed := &ParsedSpec{
        Path: specPath,
    }
    
    // Extract YAML frontmatter
    frontmatter := p.extractFrontmatter(content)
    parsed.Title = frontmatter.Title
    parsed.Version = frontmatter.Version
    
    // Extract file definitions from spec content
    parsed.FilesToGenerate = p.extractFileDefinitions(content)
    
    // Extract cross-references
    parsed.Dependencies = p.extractCrossReferences(content)
    
    return parsed, nil
}
```

### Dependency Detection

```go
func (p *SpecParser) detectFileDependencies(file *FileDefinition) []string {
    deps := []string{}
    
    // Analyze expected imports
    for _, imp := range file.Imports {
        if resolved := p.resolveImportToFile(imp); resolved != "" {
            deps = append(deps, resolved)
        }
    }
    
    // Analyze type references
    typeRefs := p.extractTypeReferences(file.Description)
    for _, ref := range typeRefs {
        if resolved := p.resolveTypeToFile(ref); resolved != "" {
            deps = append(deps, resolved)
        }
    }
    
    return deps
}
```

---

## Token Estimation

```go
type TokenEstimator struct {
    baseTokens    map[FileType]int64
    languageMulti map[string]float64
}

func NewTokenEstimator() *TokenEstimator {
    return &TokenEstimator{
        baseTokens: map[FileType]int64{
            FileTypeSource:        2000,
            FileTypeConfig:        500,
            FileTypeTest:          1500,
            FileTypeDocumentation: 1000,
            FileTypeMigration:     800,
        },
        languageMulti: map[string]float64{
            "go":     1.0,
            "react":  1.2,
            "php":    1.1,
            "python": 0.9,
        },
    }
}

func (e *TokenEstimator) EstimateFile(file *PlannedFile) int64 {
    base := e.baseTokens[file.FileType]
    multi := e.languageMulti[file.Language]
    if multi == 0 {
        multi = 1.0
    }
    
    // Add complexity factor based on dependencies
    complexityFactor := 1.0 + (float64(file.DependencyCount) * 0.1)
    
    return int64(float64(base) * multi * complexityFactor)
}
```

---

## API Endpoints

### POST /api/v1/plans/generate

Generate a new execution plan from specs.

**Request Body:**
```json
{
  "projectId": "uuid",
  "name": "Initial Backend Generation",
  "specPaths": [
    "spec/05-features/03-api-design/",
    "spec/05-features/07-database-design/"
  ],
  "languages": ["go", "react"],
  "options": {
    "includeTests": true,
    "includeMigrations": true
  }
}
```

**Response:**
```json
{
  "planId": "uuid",
  "status": "ready",
  "totalFiles": 45,
  "totalBatches": 8,
  "estimatedTokens": 125000,
  "batches": [
    {
      "index": 0,
      "files": ["internal/models/user.go", "internal/models/project.go"],
      "dependsOn": []
    },
    {
      "index": 1,
      "files": ["internal/repository/user_repo.go"],
      "dependsOn": [0]
    }
  ]
}
```

### GET /api/v1/plans/{planId}

Get plan details.

### GET /api/v1/plans/{planId}/files

Get all planned files with dependencies.

### DELETE /api/v1/plans/{planId}

Cancel and delete a plan.

---

## Batch Visualization

```
Batch 0 (No dependencies - can run immediately)
├── internal/models/user.go
├── internal/models/project.go
├── internal/models/file.go
└── internal/errors/codes.go

Batch 1 (Depends on Batch 0)
├── internal/repository/user_repo.go      [depends: models/user.go]
├── internal/repository/project_repo.go   [depends: models/project.go]
└── internal/repository/file_repo.go      [depends: models/file.go]

Batch 2 (Depends on Batch 1)
├── internal/service/user_service.go      [depends: repository/user_repo.go]
├── internal/service/project_service.go   [depends: repository/project_repo.go]
└── internal/service/file_service.go      [depends: repository/file_repo.go]

Batch 3 (Depends on Batch 2)
├── internal/handler/user_handler.go      [depends: service/user_service.go]
├── internal/handler/project_handler.go   [depends: service/project_service.go]
└── internal/handler/file_handler.go      [depends: service/file_service.go]
```

---

## Error Handling

| Error Code | Description |
|------------|-------------|
| 12200 | Plan generation failed |
| 12201 | Spec file not found |
| 12202 | Invalid spec format |
| 12203 | Circular dependency detected |
| 12204 | Unsupported language |
| 12205 | Plan already exists |
| 12206 | Plan not found |
| 12207 | Invalid batch configuration |
