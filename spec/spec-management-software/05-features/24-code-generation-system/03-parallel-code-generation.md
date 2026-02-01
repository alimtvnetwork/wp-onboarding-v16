# Parallel Code Generation

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

The Parallel Code Generation engine enables simultaneous file generation across multiple workers, with topological dependency sorting to ensure correct execution order. This document details the execution model, dependency analysis, and worker pool management.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Build Verification](./06-build-verification.md)
- [Task Execution](../06-ai-integration/14-task-execution.md)

---

## Three-Phase Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    CODE GENERATION WORKFLOW                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ╔═══════════════════════════════════════════════════════════════╗  │
│  ║  PHASE 1: CODE WRITING                                        ║  │
│  ║  ───────────────────────                                      ║  │
│  ║  1. Read specifications and guidelines                        ║  │
│  ║  2. Create file plan with dependency graph                    ║  │
│  ║  3. Sort into execution batches (topological order)           ║  │
│  ║  4. Execute batches in parallel                               ║  │
│  ║  5. Write all files to repository                             ║  │
│  ╚═══════════════════════════════════════════════════════════════╝  │
│                              │                                       │
│                              ▼                                       │
│  ╔═══════════════════════════════════════════════════════════════╗  │
│  ║  PHASE 2: CONSISTENCY CHECK                                   ║  │
│  ║  ──────────────────────────                                   ║  │
│  ║  1. Validate cross-file imports                               ║  │
│  ║  2. Check type references                                     ║  │
│  ║  3. Verify naming conventions                                 ║  │
│  ║  4. Detect missing dependencies                               ║  │
│  ║  5. Generate consistency report                               ║  │
│  ╚═══════════════════════════════════════════════════════════════╝  │
│                              │                                       │
│                              ▼                                       │
│  ╔═══════════════════════════════════════════════════════════════╗  │
│  ║  PHASE 3: BUILD VERIFICATION                                  ║  │
│  ║  ───────────────────────────                                  ║  │
│  ║  1. Run brun check per language (Go, React)                   ║  │
│  ║  2. Collect build errors                                      ║  │
│  ║  3. If errors: trigger AI fix loop                            ║  │
│  ║  4. If success: proceed to git commit                         ║  │
│  ╚═══════════════════════════════════════════════════════════════╝  │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Dependency Graph

### Topological Sort Algorithm

```go
type DependencyGraph struct {
    nodes map[string]*FileNode
    edges map[string][]string  // file -> dependencies
}

type FileNode struct {
    ID           string
    Path         string
    Language     string
    Dependencies []string      // IDs of files this depends on
    Dependents   []string      // IDs of files that depend on this
    InDegree     int           // Number of incoming edges
    Batch        int           // Assigned batch number
}

func (g *DependencyGraph) TopologicalSort() ([][]string, error) {
    batches := [][]string{}
    inDegree := make(map[string]int)
    
    // Initialize in-degrees
    for id, node := range g.nodes {
        inDegree[id] = node.InDegree
    }
    
    remaining := len(g.nodes)
    
    for remaining > 0 {
        // Find all nodes with in-degree 0 (no dependencies)
        batch := []string{}
        for id, degree := range inDegree {
            if degree == 0 {
                batch = append(batch, id)
            }
        }
        
        if len(batch) == 0 && remaining > 0 {
            return nil, errors.New("circular dependency detected")
        }
        
        // Remove batch nodes and update in-degrees
        for _, id := range batch {
            delete(inDegree, id)
            remaining--
            
            // Reduce in-degree of dependents
            for _, depID := range g.nodes[id].Dependents {
                inDegree[depID]--
            }
        }
        
        batches = append(batches, batch)
    }
    
    return batches, nil
}
```

### Dependency Detection

```go
type DependencyAnalyzer struct {
    patterns map[string][]regexp.Regexp  // Language -> import patterns
}

// Go import detection
var goImportPattern = regexp.MustCompile(`import\s+\(([^)]+)\)|import\s+"([^"]+)"`)

// TypeScript import detection
var tsImportPattern = regexp.MustCompile(`import\s+.*\s+from\s+['"]([^'"]+)['"]`)

func (a *DependencyAnalyzer) AnalyzePlan(plan *GenerationPlan) *DependencyGraph {
    graph := NewDependencyGraph()
    
    // Add all files as nodes
    for _, file := range plan.Files {
        graph.AddNode(file.ID, file.Path, file.Language)
    }
    
    // Analyze dependencies based on spec references and planned imports
    for _, file := range plan.Files {
        deps := a.detectDependencies(file, plan.Files)
        for _, depID := range deps {
            graph.AddEdge(file.ID, depID)
        }
    }
    
    return graph
}
```

---

## Worker Pool Management

### Per-Project Parallel Execution

```go
type WorkerPool struct {
    maxGlobalWorkers    int
    workers             map[string]*Worker
    projectAllocations  map[string]*ProjectAllocation
    taskQueue           chan *GenerationTask
    resultQueue         chan *GenerationResult
    mutex               sync.RWMutex
}

type ProjectAllocation struct {
    ProjectID      string
    MaxWorkers     int
    ActiveWorkers  int
    Queue          []*GenerationTask
    Priority       int
}

type Worker struct {
    ID          string
    ProjectID   string
    ModelClient ModelClient
    Status      WorkerStatus
    CurrentTask *GenerationTask
    StartedAt   time.Time
}

type WorkerStatus string

const (
    WorkerStatusIdle    WorkerStatus = "idle"
    WorkerStatusBusy    WorkerStatus = "busy"
    WorkerStatusError   WorkerStatus = "error"
)

func NewWorkerPool(maxWorkers int) *WorkerPool {
    pool := &WorkerPool{
        maxGlobalWorkers:   maxWorkers,
        workers:            make(map[string]*Worker),
        projectAllocations: make(map[string]*ProjectAllocation),
        taskQueue:          make(chan *GenerationTask, 1000),
        resultQueue:        make(chan *GenerationResult, 1000),
    }
    
    // Start worker dispatcher
    go pool.dispatcher()
    
    return pool
}

func (p *WorkerPool) AllocateForProject(projectID string, requested int) int {
    p.mutex.Lock()
    defer p.mutex.Unlock()
    
    // Calculate available workers
    totalActive := 0
    for _, alloc := range p.projectAllocations {
        totalActive += alloc.ActiveWorkers
    }
    
    available := p.maxGlobalWorkers - totalActive
    allocated := min(requested, available)
    
    // Create or update allocation
    if _, exists := p.projectAllocations[projectID]; !exists {
        p.projectAllocations[projectID] = &ProjectAllocation{
            ProjectID:  projectID,
            MaxWorkers: allocated,
        }
    }
    p.projectAllocations[projectID].ActiveWorkers = allocated
    
    // Spawn workers
    for i := 0; i < allocated; i++ {
        workerID := fmt.Sprintf("%s-worker-%d", projectID, i)
        p.workers[workerID] = &Worker{
            ID:        workerID,
            ProjectID: projectID,
            Status:    WorkerStatusIdle,
        }
    }
    
    return allocated
}
```

---

## Execution Engine

### Batch Executor

```go
type ParallelExecutionEngine struct {
    workerPool    *WorkerPool
    creditTracker *CreditTracker
    fileWriter    *FileWriter
    modelSelector *ModelSelector
}

type ExecutionContext struct {
    ProjectID     string
    Plan          *GenerationPlan
    Guidelines    *ResolvedGuidelines
    GeneratedFiles map[string]string  // path -> content (for context)
    Errors        []GenerationError
}

func (e *ParallelExecutionEngine) Execute(
    ctx context.Context,
    plan *GenerationPlan,
    guidelines *ResolvedGuidelines,
) (*ExecutionResult, error) {
    
    execCtx := &ExecutionContext{
        ProjectID:      plan.ProjectID,
        Plan:           plan,
        Guidelines:     guidelines,
        GeneratedFiles: make(map[string]string),
        Errors:         []GenerationError{},
    }
    
    // Allocate workers for this project
    workers := e.workerPool.AllocateForProject(plan.ProjectID, 
        getProjectWorkerLimit(plan.ProjectID))
    
    if workers == 0 {
        return nil, errors.New("no workers available")
    }
    
    defer e.workerPool.ReleaseProject(plan.ProjectID)
    
    // Execute batches sequentially, files within batch in parallel
    for batchNum, batch := range plan.Batches {
        log.Printf("Executing batch %d with %d files", batchNum, len(batch.FileIDs))
        
        batchResult := e.executeBatch(ctx, execCtx, batch)
        
        if batchResult.HasCriticalErrors {
            return &ExecutionResult{
                Success:   false,
                Errors:    execCtx.Errors,
                StoppedAt: batchNum,
            }, nil
        }
        
        // Update context with generated files for next batch
        for path, content := range batchResult.GeneratedFiles {
            execCtx.GeneratedFiles[path] = content
        }
    }
    
    return &ExecutionResult{
        Success:        true,
        GeneratedFiles: execCtx.GeneratedFiles,
        TotalTokens:    e.calculateTotalTokens(execCtx),
    }, nil
}

func (e *ParallelExecutionEngine) executeBatch(
    ctx context.Context,
    execCtx *ExecutionContext,
    batch ExecutionBatch,
) *BatchResult {
    
    var wg sync.WaitGroup
    results := make(chan *FileGenerationResult, len(batch.FileIDs))
    
    for _, fileID := range batch.FileIDs {
        wg.Add(1)
        
        go func(fID string) {
            defer wg.Done()
            
            file := execCtx.Plan.GetFile(fID)
            result := e.generateFile(ctx, execCtx, file)
            results <- result
        }(fileID)
    }
    
    // Wait for all files in batch to complete
    go func() {
        wg.Wait()
        close(results)
    }()
    
    // Collect results
    batchResult := &BatchResult{
        GeneratedFiles: make(map[string]string),
    }
    
    for result := range results {
        if result.Success {
            batchResult.GeneratedFiles[result.Path] = result.Content
            
            // Track credits
            e.creditTracker.Consume(CreditConsumption{
                Type:     CreditTypeFileGenerated,
                Amount:   getFileCredit(),
                Metadata: map[string]interface{}{"path": result.Path},
            })
            e.creditTracker.Consume(CreditConsumption{
                Type:     CreditTypeAIRequest,
                Amount:   calculateTokenCredit(result.TokensUsed),
                Metadata: map[string]interface{}{"tokens": result.TokensUsed},
            })
        } else {
            batchResult.HasCriticalErrors = batchResult.HasCriticalErrors || result.Critical
            execCtx.Errors = append(execCtx.Errors, result.Error)
        }
    }
    
    return batchResult
}
```

### File Generator

```go
func (e *ParallelExecutionEngine) generateFile(
    ctx context.Context,
    execCtx *ExecutionContext,
    file PlannedFile,
) *FileGenerationResult {
    
    // Select appropriate model for language
    model, err := e.modelSelector.SelectModel(file.Language, "normal")
    if err != nil {
        return &FileGenerationResult{
            Success: false,
            Error:   GenerationError{Code: 8300, Message: err.Error()},
        }
    }
    
    // Build prompt with guidelines and context
    prompt := e.buildPrompt(execCtx, file)
    
    // Call coding model
    response, err := model.Generate(ctx, prompt)
    if err != nil {
        return &FileGenerationResult{
            Success: false,
            Error:   GenerationError{Code: 8301, Message: err.Error()},
        }
    }
    
    // Extract code from response
    code := extractCodeFromResponse(response, file.Language)
    
    // Write to repository
    repoPath := getRepoPath(execCtx.ProjectID)
    fullPath := filepath.Join(repoPath, file.Path)
    
    if err := e.fileWriter.Write(fullPath, code); err != nil {
        return &FileGenerationResult{
            Success: false,
            Error:   GenerationError{Code: 8302, Message: err.Error()},
        }
    }
    
    return &FileGenerationResult{
        Success:    true,
        Path:       file.Path,
        Content:    code,
        TokensUsed: response.TokensUsed,
        ModelUsed:  model.Name,
    }
}
```

---

## Prompt Building

### Generation Prompt Template

```go
type PromptBuilder struct {
    templateEngine *template.Template
}

const fileGenerationPrompt = `
# Code Generation Task

## Guidelines
You MUST follow these coding guidelines:

{{.Guidelines}}

## Specification Reference
This file implements the following specification:

{{range .SpecReferences}}
### {{.Path}}
{{.Content}}
{{end}}

## File to Generate
- **Path:** {{.FilePath}}
- **Language:** {{.Language}}
- **Purpose:** {{.Purpose}}

## Context (Already Generated Files)
{{if .ContextFiles}}
The following files have already been generated. Use them for imports and type references:

{{range .ContextFiles}}
### {{.Path}}
` + "```" + `{{.Language}}
{{.Content}}
` + "```" + `
{{end}}
{{else}}
No context files available yet.
{{end}}

## Instructions
1. Generate ONLY the code for the specified file
2. Follow all guidelines exactly
3. Use proper imports based on the context files
4. Include necessary comments and documentation
5. Ensure the code is complete and production-ready

## Output Format
Respond with ONLY the code wrapped in a code block. No explanations.
`

func (b *PromptBuilder) Build(
    execCtx *ExecutionContext,
    file PlannedFile,
) string {
    
    data := struct {
        Guidelines     string
        SpecReferences []SpecContent
        FilePath       string
        Language       string
        Purpose        string
        ContextFiles   []ContextFile
    }{
        Guidelines:     execCtx.Guidelines.MergedContent,
        SpecReferences: loadSpecContent(file.SpecReferences),
        FilePath:       file.Path,
        Language:       file.Language,
        Purpose:        file.Purpose,
        ContextFiles:   getRelevantContext(execCtx, file),
    }
    
    var buf bytes.Buffer
    b.templateEngine.Execute(&buf, data)
    return buf.String()
}
```

---

## Consistency Checker

### Post-Generation Validation

```go
type ConsistencyChecker struct {
    importValidator  *ImportValidator
    typeChecker      *TypeChecker
    namingValidator  *NamingValidator
}

type ConsistencyReport struct {
    TotalFiles      int
    TotalChecks     int
    PassedChecks    int
    FailedChecks    int
    Issues          []ConsistencyIssue
    GeneratedAt     time.Time
}

type ConsistencyIssue struct {
    Severity    IssueSeverity  // error, warning, info
    FilePath    string
    Line        int
    Column      int
    Rule        string
    Message     string
    Suggestion  string
    AutoFixable bool
}

func (c *ConsistencyChecker) Check(
    repoPath string,
    generatedFiles map[string]string,
) (*ConsistencyReport, error) {
    
    report := &ConsistencyReport{
        TotalFiles: len(generatedFiles),
        Issues:     []ConsistencyIssue{},
    }
    
    for path, content := range generatedFiles {
        // Check imports
        importIssues := c.importValidator.Validate(path, content, generatedFiles)
        report.Issues = append(report.Issues, importIssues...)
        
        // Check type references
        typeIssues := c.typeChecker.Validate(path, content, generatedFiles)
        report.Issues = append(report.Issues, typeIssues...)
        
        // Check naming conventions
        namingIssues := c.namingValidator.Validate(path, content)
        report.Issues = append(report.Issues, namingIssues...)
    }
    
    // Calculate statistics
    for _, issue := range report.Issues {
        report.TotalChecks++
        if issue.Severity != IssueSeverityError {
            report.PassedChecks++
        } else {
            report.FailedChecks++
        }
    }
    
    report.GeneratedAt = time.Now()
    return report, nil
}
```

---

## Configuration

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `codegen.parallel.maxGlobalWorkers` | int | 16 | Maximum workers across all projects |
| `codegen.parallel.maxProjectWorkers` | int | 4 | Maximum workers per project |
| `codegen.parallel.batchSize` | int | 10 | Maximum files per batch |
| `codegen.parallel.taskTimeout` | duration | 5m | Timeout per file generation |
| `codegen.parallel.batchTimeout` | duration | 30m | Timeout per batch |
| `codegen.consistency.enabled` | bool | true | Run consistency check after generation |
| `codegen.consistency.failOnError` | bool | true | Fail if consistency errors found |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 8300 | ERR_CODEGEN_MODEL_SELECT_FAILED | Failed to select coding model |
| 8301 | ERR_CODEGEN_GENERATION_FAILED | Code generation failed |
| 8302 | ERR_CODEGEN_WRITE_FAILED | Failed to write generated file |
| 8303 | ERR_CODEGEN_BATCH_TIMEOUT | Batch execution timeout |
| 8304 | ERR_CODEGEN_CIRCULAR_DEPENDENCY | Circular dependency in file graph |
| 8305 | ERR_CODEGEN_NO_WORKERS | No workers available |
| 8306 | ERR_CODEGEN_CONTEXT_TOO_LARGE | Context exceeds model limit |

---

## Related Specs

- [Architecture](./01-architecture.md)
- [Build Verification](./06-build-verification.md)
- [Credit System](./07-credit-system.md)
