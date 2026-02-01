# 01. System Overview

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Purpose

Define the architecture and philosophy for AI-driven Golang code generation, replacing direct AI file manipulation with compiled, reviewable, and reusable CLI tools.

---

## Core Philosophy

### Why Generate Code Instead of Direct Manipulation?

| Approach | Direct AI Manipulation | Golang Code Generation |
|----------|------------------------|------------------------|
| Predictability | AI behavior varies | Compiled code is deterministic |
| Review | No preview possible | Full code review before execution |
| Debugging | AI errors are opaque | Clear compiler/runtime errors |
| Reusability | Each task is unique | Code patterns stored for reuse |
| History | Limited logging | Complete audit trail |
| Testing | Cannot test AI decisions | Unit test generated code |
| Rollback | Difficult | Git-based with clear commits |

### Design Principles

1. **Approval-First:** No code executes without explicit user approval
2. **Transparency:** All generated code is visible and reviewable
3. **Reusability:** Store patterns for intelligent retrieval
4. **History-First:** Every operation logged with checksums
5. **Fail-Safe:** Dry-run mode for verification
6. **Self-Healing:** AI corrects code on compilation errors

---

## Four-Layer Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      AGENT LAYER                             │
│  Intent understanding, planning, model selection             │
│  Components: Query Parser, Complexity Analyzer, Orchestrator │
└───────────────────────────────┬─────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────┐
│                    RETRIEVAL LAYER                           │
│  Information gathering from sources                          │
│  Components: Task Matcher, Tag Search, Vector DB (optional)  │
└───────────────────────────────┬─────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────┐
│                    EXECUTION LAYER                           │
│  Code generation and secure execution                        │
│  Components: Code Generator, Compiler, Sandbox, Approval UI  │
└───────────────────────────────┬─────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────┐
│                    SYNTHESIS LAYER                           │
│  Result compilation and history logging                      │
│  Components: History Logger, Result Formatter, Tag Generator │
└─────────────────────────────────────────────────────────────┘
```

---

## Process Pipeline

### Phase 1: Intent Analysis

```go
type IntentAnalysis struct {
    UserRequest     string
    ParsedIntent    Intent
    Complexity      ComplexityScore
    RequiredModels  []ModelType
    EstimatedSteps  int
}

type Intent struct {
    Action      ActionType  // Rename, Delete, Transform, etc.
    Targets     []string    // File patterns, directories
    Conditions  []Condition // Filters, constraints
    OutputSpec  OutputSpec  // Expected results
}
```

### Phase 2: Complexity Decision

Based on complexity score, route to:
- **Simple (score < 5):** Direct execution via existing tools
- **Complex (score >= 5):** Golang code generation path

### Phase 3: Reusability Check

Query `TempCodingTasks` table for similar existing code:
- Tag overlap scoring
- Semantic similarity (if vector DB enabled)
- Modification potential assessment

### Phase 4: Code Generation or Reuse

If reusable code found:
1. Present existing code for review
2. AI adapts if parameters differ
3. User approves

If no reusable code:
1. AI generates new Golang program
2. Validate syntax and compile
3. Present for user approval

### Phase 5: Approval Workflow

```
┌─────────────────────────────────────┐
│         APPROVAL INTERFACE           │
├─────────────────────────────────────┤
│ Task: Lowercase all filenames        │
│ Code Preview: [Syntax-highlighted]   │
│ Dry-Run Results: [Preview of changes]│
├─────────────────────────────────────┤
│ [Approve & Execute] [Edit] [Reject]  │
└─────────────────────────────────────┘
```

### Phase 6: Execution

1. Compile Golang code
2. Execute with dry-run first (if enabled)
3. User confirms dry-run results
4. Execute actual changes
5. Log all operations to history

### Phase 7: History & Storage

- Log each filesystem operation with checksums
- Store code in `TempCodingTasks` with tags
- Update execution statistics

---

## Multi-Model Architecture

Different LLM models optimized for different phases:

| Phase | Model Category | Purpose |
|-------|---------------|---------|
| Intent Analysis | Thinking | Understand request, plan approach |
| Complexity Decision | Thinking | Evaluate task complexity |
| Code Generation | Coding | Write Golang code |
| Error Correction | Coding | Fix compilation errors |
| Documentation | Writing | Generate comments, docs |
| Tag Generation | Writing | Create descriptive tags |

### Parallel Execution

For long-chain commands, execute across models simultaneously:

```go
type ParallelExecution struct {
    Tasks       []SubTask
    Models      map[string]ModelConfig
    Results     chan TaskResult
    MaxParallel int
}

func (pe *ParallelExecution) Execute() []TaskResult {
    var wg sync.WaitGroup
    semaphore := make(chan struct{}, pe.MaxParallel)
    
    for _, task := range pe.Tasks {
        wg.Add(1)
        go func(t SubTask) {
            defer wg.Done()
            semaphore <- struct{}{}
            defer func() { <-semaphore }()
            
            result := pe.executeWithModel(t)
            pe.Results <- result
        }(task)
    }
    
    wg.Wait()
    close(pe.Results)
    return collectResults(pe.Results)
}
```

---

## Directory Structure

```
project-folder/
├── .tmp/
│   └── golang/
│       ├── 01-lowercase-filenames/
│       │   ├── main.go           # Generated CLI
│       │   ├── go.mod            # Module definition
│       │   ├── go.sum            # Dependencies (if any)
│       │   ├── execution.log     # Execution output
│       │   └── history.json      # Operation history
│       ├── 02-rename-by-pattern/
│       │   └── ...
│       └── index.json            # Task index
```

### index.json Schema

```json
{
  "version": "1.0.0",
  "lastTaskNumber": 2,
  "tasks": [
    {
      "number": 1,
      "name": "lowercase-filenames",
      "path": "01-lowercase-filenames",
      "createdAt": "2026-01-29T10:30:00Z",
      "tags": ["filesystem", "rename", "batch-operation"],
      "reusable": true
    }
  ]
}
```

---

## Integration Points

### With AI Integration (Feature 06)

- LLM provider abstraction for model calls
- Model selection hierarchy
- Streaming response support

### With History System (Feature 07)

- Git integration for code versioning
- Snapshot management

### With Build Runner CLI (Feature 23)

- Execution patterns
- Error capture
- JSON output format

---

## Related Specs

- [02-complexity-decision.md](./02-complexity-decision.md) — Decision logic
- [03-code-generator.md](./03-code-generator.md) — Generation engine
- [07-approval-workflow.md](./07-approval-workflow.md) — User approval
- [08-history-logger.md](./08-history-logger.md) — Operation tracking
