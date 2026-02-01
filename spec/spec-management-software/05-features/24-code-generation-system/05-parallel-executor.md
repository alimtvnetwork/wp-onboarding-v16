# Code Generation System - Parallel Executor

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Concurrent code generation engine with per-project worker pools and batch-based execution respecting topological order.

**Cross-References:**
- [Overview](./00-overview.md)
- [Plan Generator](./04-plan-generator.md)
- [LLM Server Management](../06-ai-integration/07-llm-server.md)

---

## Execution Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PARALLEL EXECUTION ENGINE                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                        PROJECT WORKER POOLS                              ││
│  │                                                                          ││
│  │  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐       ││
│  │  │  Project A Pool  │  │  Project B Pool  │  │  Project C Pool  │       ││
│  │  │  ┌────┐ ┌────┐   │  │  ┌────┐ ┌────┐   │  │  ┌────┐ ┌────┐   │       ││
│  │  │  │ W1 │ │ W2 │   │  │  │ W1 │ │ W2 │   │  │  │ W1 │ │ W2 │   │       ││
│  │  │  └────┘ └────┘   │  │  └────┘ └────┘   │  │  └────┘ └────┘   │       ││
│  │  │  ┌────┐ ┌────┐   │  │  ┌────┐ ┌────┐   │  │  ┌────┐ ┌────┐   │       ││
│  │  │  │ W3 │ │ W4 │   │  │  │ W3 │ │ W4 │   │  │  │ W3 │ │ W4 │   │       ││
│  │  │  └────┘ └────┘   │  │  └────┘ └────┘   │  │  └────┘ └────┘   │       ││
│  │  └──────────────────┘  └──────────────────┘  └──────────────────┘       ││
│  │                                                                          ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                          SHARED RESOURCES                                ││
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐  ││
│  │  │    Model     │  │    Credit    │  │     Git      │  │  WebSocket  │  ││
│  │  │   Selector   │  │   Tracker    │  │   Manager    │  │   Streamer  │  ││
│  │  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────┘  ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Data Models

### ExecutionSession

```go
type ExecutionSession struct {
    ID              uint            `gorm:"primaryKey"`
    UUID            string          `gorm:"uniqueIndex;size:36"`
    PlanID          uint            `gorm:"index"`
    Plan            *GenerationPlan `gorm:"foreignKey:PlanID"`
    
    Status          SessionStatus   `gorm:"default:0"`
    
    TotalFiles      int
    CompletedFiles  int
    FailedFiles     int
    SkippedFiles    int
    
    TotalTokens     int64
    TotalCredits    float64
    
    CurrentBatch    int
    WorkersActive   int
    
    StartedAt       time.Time
    CompletedAt     *time.Time
    
    ErrorLog        string          `gorm:"type:text"`  // JSON array of errors
}

type SessionStatus int

const (
    SessionStatusPending   SessionStatus = iota
    SessionStatusRunning
    SessionStatusPaused
    SessionStatusCompleted
    SessionStatusFailed
    SessionStatusCancelled
)
```

### GeneratedFile

```go
type GeneratedFile struct {
    ID              uint            `gorm:"primaryKey"`
    SessionID       uint            `gorm:"index"`
    PlannedFileID   uint            `gorm:"index"`
    
    FilePath        string          `gorm:"size:500"`
    Content         string          `gorm:"type:longtext"`
    ContentHash     string          `gorm:"size:64"`
    
    ModelUsed       string          `gorm:"size:100"`
    PromptTokens    int64
    CompletionTokens int64
    
    GenerationTime  int64           // milliseconds
    
    Status          GeneratedStatus `gorm:"default:0"`
    ErrorMessage    string          `gorm:"type:text"`
    
    CreatedAt       time.Time
}

type GeneratedStatus int

const (
    GeneratedStatusSuccess GeneratedStatus = iota
    GeneratedStatusError
    GeneratedStatusRetried
)
```

---

## Worker Pool Implementation

```go
type WorkerPool struct {
    projectID     string
    maxWorkers    int
    workers       []*Worker
    taskQueue     chan *GenerationTask
    resultChan    chan *GenerationResult
    ctx           context.Context
    cancel        context.CancelFunc
    wg            sync.WaitGroup
    metrics       *PoolMetrics
}

func NewWorkerPool(projectID string, maxWorkers int) *WorkerPool {
    ctx, cancel := context.WithCancel(context.Background())
    
    pool := &WorkerPool{
        projectID:  projectID,
        maxWorkers: maxWorkers,
        taskQueue:  make(chan *GenerationTask, 100),
        resultChan: make(chan *GenerationResult, 100),
        ctx:        ctx,
        cancel:     cancel,
        metrics:    &PoolMetrics{},
    }
    
    // Start workers
    for i := 0; i < maxWorkers; i++ {
        worker := NewWorker(i, pool)
        pool.workers = append(pool.workers, worker)
        pool.wg.Add(1)
        go worker.Run()
    }
    
    return pool
}

func (p *WorkerPool) Submit(task *GenerationTask) error {
    select {
    case p.taskQueue <- task:
        return nil
    case <-p.ctx.Done():
        return ErrPoolShutdown
    default:
        return ErrQueueFull
    }
}

func (p *WorkerPool) Shutdown() {
    p.cancel()
    close(p.taskQueue)
    p.wg.Wait()
    close(p.resultChan)
}
```

### Worker Implementation

```go
type Worker struct {
    id           int
    pool         *WorkerPool
    modelClient  *ModelClient
    codeWriter   *CodeWriter
}

func (w *Worker) Run() {
    defer w.pool.wg.Done()
    
    for {
        select {
        case task, ok := <-w.pool.taskQueue:
            if !ok {
                return
            }
            result := w.process(task)
            w.pool.resultChan <- result
            
        case <-w.pool.ctx.Done():
            return
        }
    }
}

func (w *Worker) process(task *GenerationTask) *GenerationResult {
    start := time.Now()
    result := &GenerationResult{
        Task: task,
    }
    
    // Build prompt from guidelines and spec
    prompt := w.buildPrompt(task)
    
    // Call LLM
    response, err := w.modelClient.Generate(task.Model, prompt)
    if err != nil {
        result.Error = err
        return result
    }
    
    result.GeneratedCode = response.Content
    result.TokensUsed = response.TokensInput + response.TokensOutput
    result.Duration = time.Since(start)
    
    return result
}
```

---

## Batch Execution Flow

```go
type BatchExecutor struct {
    pool          *WorkerPool
    batchQueue    []*ExecutionBatch
    currentBatch  int
    results       map[string]*GenerationResult
    mutex         sync.Mutex
}

func (e *BatchExecutor) ExecutePlan(plan *GenerationPlan) error {
    batches, err := e.loadBatches(plan.ID)
    if err != nil {
        return err
    }
    
    for _, batch := range batches {
        // Wait for dependent batches to complete
        if err := e.waitForDependencies(batch); err != nil {
            return err
        }
        
        // Submit all files in batch (they run in parallel)
        for _, file := range batch.Files {
            task := e.createTask(file, plan)
            if err := e.pool.Submit(task); err != nil {
                return err
            }
        }
        
        // Wait for all files in batch to complete
        if err := e.waitForBatch(batch); err != nil {
            return err
        }
        
        // Write files to disk
        if err := e.writeFiles(batch); err != nil {
            return err
        }
        
        e.currentBatch++
    }
    
    return nil
}

func (e *BatchExecutor) waitForBatch(batch *ExecutionBatch) error {
    completed := 0
    expected := len(batch.Files)
    
    for completed < expected {
        select {
        case result := <-e.pool.resultChan:
            e.mutex.Lock()
            e.results[result.Task.File.FilePath] = result
            e.mutex.Unlock()
            
            if result.Error != nil {
                // Handle error but continue
                log.Error("File generation failed",
                    "file", result.Task.File.FilePath,
                    "error", result.Error)
            }
            completed++
            
        case <-time.After(30 * time.Minute):
            return ErrBatchTimeout
        }
    }
    
    return nil
}
```

---

## Code Writer

```go
type CodeWriter struct {
    rootPath    string
    pathManager *PathManager
    gitManager  *GitManager
}

func (w *CodeWriter) WriteGeneratedFiles(results []*GenerationResult) error {
    for _, result := range results {
        if result.Error != nil {
            continue
        }
        
        fullPath := filepath.Join(w.rootPath, result.Task.File.FilePath)
        
        // Create directory if needed
        dir := filepath.Dir(fullPath)
        if err := os.MkdirAll(dir, 0755); err != nil {
            return fmt.Errorf("create dir: %w", err)
        }
        
        // Write file
        if err := os.WriteFile(fullPath, []byte(result.GeneratedCode), 0644); err != nil {
            return fmt.Errorf("write file: %w", err)
        }
        
        // Format file based on language
        if err := w.formatFile(fullPath, result.Task.File.Language); err != nil {
            log.Warn("Format failed", "file", fullPath, "error", err)
        }
    }
    
    return nil
}

func (w *CodeWriter) formatFile(path, language string) error {
    switch language {
    case "go":
        return exec.Command("gofmt", "-w", path).Run()
    case "react", "typescript":
        return exec.Command("npx", "prettier", "--write", path).Run()
    default:
        return nil
    }
}
```

---

## Prompt Building

```go
type PromptBuilder struct {
    guidelineResolver *GuidelineResolver
    templateEngine    *template.Template
}

func (b *PromptBuilder) BuildPrompt(task *GenerationTask) (string, error) {
    // Get merged guidelines
    guidelines, err := b.guidelineResolver.Resolve(
        task.ProjectID,
        task.UserID,
        task.File.Language,
    )
    if err != nil {
        return "", err
    }
    
    // Build template context
    ctx := map[string]interface{}{
        "Guidelines":     guidelines.MergedPrompt,
        "FilePath":       task.File.FilePath,
        "FileType":       task.File.FileType,
        "Language":       task.File.Language,
        "Description":    task.File.Description,
        "SpecContent":    task.SpecContent,
        "Dependencies":   task.DependencyContext,
        "ProjectContext": task.ProjectContext,
    }
    
    var buf bytes.Buffer
    if err := b.templateEngine.Execute(&buf, ctx); err != nil {
        return "", err
    }
    
    return buf.String(), nil
}
```

### Prompt Template

```
You are an expert {{.Language}} developer. Generate code following these guidelines:

## Coding Guidelines
{{.Guidelines}}

## File to Generate
- Path: {{.FilePath}}
- Type: {{.FileType}}
- Description: {{.Description}}

## Specification
{{.SpecContent}}

## Dependency Context
{{range .Dependencies}}
### {{.FilePath}}
{{.Summary}}
{{end}}

## Instructions
1. Generate only the code for the specified file
2. Follow all coding guidelines strictly
3. Use proper imports based on dependencies
4. Include comprehensive documentation
5. Handle errors according to guidelines

Generate the complete, production-ready code:
```

---

## Progress Streaming

```go
type ProgressStreamer struct {
    wsHub       *WebSocketHub
    sessionID   string
}

func (s *ProgressStreamer) StreamProgress(event *ProgressEvent) {
    s.wsHub.Broadcast(s.sessionID, &WSMessage{
        Type: "codegen:progress",
        Payload: event,
    })
}

type ProgressEvent struct {
    SessionID      string  `json:"sessionId"`
    CurrentBatch   int     `json:"currentBatch"`
    TotalBatches   int     `json:"totalBatches"`
    CurrentFile    string  `json:"currentFile"`
    CompletedFiles int     `json:"completedFiles"`
    TotalFiles     int     `json:"totalFiles"`
    FailedFiles    int     `json:"failedFiles"`
    TokensUsed     int64   `json:"tokensUsed"`
    ElapsedTime    int64   `json:"elapsedTimeMs"`
    Status         string  `json:"status"`
}
```

---

## Configuration

```json
{
  "parallelExecution": {
    "maxWorkersPerProject": 4,
    "maxConcurrentProjects": 3,
    "taskQueueSize": 100,
    "batchTimeout": "30m",
    "fileTimeout": "5m",
    "retryAttempts": 2,
    "retryDelay": "5s"
  }
}
```

---

## Error Handling

| Error Code | Description |
|------------|-------------|
| 12300 | Execution session failed |
| 12301 | Worker pool exhausted |
| 12302 | Task queue full |
| 12303 | Batch timeout |
| 12304 | File generation timeout |
| 12305 | Model unavailable |
| 12306 | Insufficient credits |
| 12307 | Dependency resolution failed |
