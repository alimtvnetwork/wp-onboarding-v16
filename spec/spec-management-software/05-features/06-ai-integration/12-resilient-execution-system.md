# Resilient Execution System

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Target Success Rate:** 98%+

---

## Overview

The Resilient Execution System (RES) provides fault-tolerant, self-healing execution of AI-powered instruction tasks. It addresses the gap between current ~85% success rates and the target 98%+ through five integrated subsystems: Self-Correction Agent, Multi-Model Consensus, Checkpoint & Rollback, Adaptive Retry, and Human Escalation.

**Cross-References:**
- [Instruction System](./03-instruction-system.md) — Task execution pipeline
- [Instruction Segmentation](./05-instruction-segmentation.md) — Large instruction handling
- [LLM Server Management](./07-llm-server-management.md) — Model orchestration
- [AI Testing Strategy](./11-ai-testing.md) — Validation tests

---

## 12.1 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                        RESILIENT EXECUTION SYSTEM (RES)                              │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                       │
│  ┌─────────────────────────────────────────────────────────────────────────────┐     │
│  │                          Execution Orchestrator                              │     │
│  ├─────────────────────────────────────────────────────────────────────────────┤     │
│  │                                                                               │     │
│  │    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐            │     │
│  │    │Checkpoint│    │ Execute  │    │ Validate │    │ Commit/  │            │     │
│  │    │  State   │───▶│   Task   │───▶│  Output  │───▶│ Rollback │            │     │
│  │    └──────────┘    └──────────┘    └──────────┘    └──────────┘            │     │
│  │                          │               │               │                   │     │
│  │                          ▼               ▼               ▼                   │     │
│  │    ┌─────────────────────────────────────────────────────────────────┐      │     │
│  │    │                    Failure Recovery Pipeline                     │      │     │
│  │    ├─────────────────────────────────────────────────────────────────┤      │     │
│  │    │                                                                   │      │     │
│  │    │  ┌────────────┐   ┌────────────┐   ┌────────────┐   ┌────────┐  │      │     │
│  │    │  │  Analyze   │──▶│  Adaptive  │──▶│   Multi-   │──▶│ Human  │  │      │     │
│  │    │  │  Failure   │   │   Retry    │   │  Consensus │   │Escalate│  │      │     │
│  │    │  └────────────┘   └────────────┘   └────────────┘   └────────┘  │      │     │
│  │    │        │                │                │               │       │      │     │
│  │    │        └────────────────┴────────────────┴───────────────┘       │      │     │
│  │    │                              │                                    │      │     │
│  │    │                              ▼                                    │      │     │
│  │    │                    Self-Correction Agent                          │      │     │
│  │    │                                                                   │      │     │
│  │    └─────────────────────────────────────────────────────────────────┘      │     │
│  │                                                                               │     │
│  └─────────────────────────────────────────────────────────────────────────────┘     │
│                                                                                       │
│  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐          │
│  │  Checkpoint   │  │   Consensus   │  │   Escalation  │  │   Telemetry   │          │
│  │    Store      │  │    Engine     │  │     Queue     │  │   Collector   │          │
│  └───────────────┘  └───────────────┘  └───────────────┘  └───────────────┘          │
│                                                                                       │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 12.2 Success Rate Breakdown

### Current Failure Analysis (15% failure rate)

| Failure Category | Current Rate | Root Cause | Solution Component |
|------------------|--------------|------------|-------------------|
| Model hallucination | 4% | Single model, no verification | Multi-Model Consensus |
| Ambiguous instructions | 3% | No clarification loop | Human Escalation |
| Transient API errors | 2% | Static retry logic | Adaptive Retry |
| Logic errors | 3% | No self-analysis | Self-Correction Agent |
| Partial failures | 2% | No rollback mechanism | Checkpoint & Rollback |
| Context loss | 1% | Token window overflow | Enhanced Segmentation |

### Target Improvement

| Component | Expected Improvement | New Failure Rate |
|-----------|---------------------|------------------|
| Self-Correction Agent | +5% recovery | 10% |
| Multi-Model Consensus | +4% accuracy | 6% |
| Checkpoint & Rollback | +2% recovery | 4% |
| Adaptive Retry | +3% recovery | 1% |
| Human Escalation | +1% (edge cases) | <1% |

**Target: 98%+ success rate**

---

## 12.3 Database Schema

```sql
-- Execution checkpoint for rollback capability
CREATE TABLE ExecutionCheckpoint (
    Id TEXT PRIMARY KEY,
    InstructionId TEXT NOT NULL,
    TaskId TEXT NOT NULL,
    
    -- State snapshot
    StateJson TEXT NOT NULL,           -- Serialized state before execution
    FilesSnapshot TEXT,                -- JSON map of file paths → content hashes
    DatabaseSnapshot TEXT,             -- Relevant DB state if applicable
    
    -- Metadata
    CheckpointType TEXT NOT NULL CHECK (CheckpointType IN ('pre_execution', 'mid_execution', 'post_validation')),
    CreatedAt TEXT NOT NULL,
    ExpiresAt TEXT NOT NULL,           -- Auto-cleanup after retention period
    
    FOREIGN KEY (InstructionId) REFERENCES Instruction(Id) ON DELETE CASCADE,
    FOREIGN KEY (TaskId) REFERENCES InstructionTask(Id) ON DELETE CASCADE
);

CREATE INDEX IX_Checkpoint_Task ON ExecutionCheckpoint(TaskId);
CREATE INDEX IX_Checkpoint_Expires ON ExecutionCheckpoint(ExpiresAt);

-- Execution attempt tracking for adaptive retry
CREATE TABLE ExecutionAttempt (
    Id TEXT PRIMARY KEY,
    TaskId TEXT NOT NULL,
    AttemptNumber INTEGER NOT NULL,
    
    -- Execution details
    ModelUsed TEXT NOT NULL,           -- Model ID used for this attempt
    PromptStrategy TEXT NOT NULL,      -- Strategy name (e.g., 'default', 'verbose', 'step_by_step')
    TemperatureUsed REAL,              -- Temperature setting
    
    -- Results
    Status TEXT NOT NULL CHECK (Status IN ('running', 'success', 'failed', 'timeout', 'cancelled')),
    OutputJson TEXT,                   -- Raw model output
    ValidationResult TEXT,             -- JSON validation details
    ErrorType TEXT,                    -- Categorized error type
    ErrorMessage TEXT,                 -- Full error message
    
    -- Timing
    StartedAt TEXT NOT NULL,
    CompletedAt TEXT,
    DurationMs INTEGER,
    TokensUsed INTEGER,
    
    FOREIGN KEY (TaskId) REFERENCES InstructionTask(Id) ON DELETE CASCADE
);

CREATE INDEX IX_Attempt_Task ON ExecutionAttempt(TaskId);
CREATE INDEX IX_Attempt_Status ON ExecutionAttempt(Status);

-- Consensus voting for multi-model validation
CREATE TABLE ConsensusVote (
    Id TEXT PRIMARY KEY,
    TaskId TEXT NOT NULL,
    AttemptId TEXT NOT NULL,
    
    -- Voting model
    VoterModelId TEXT NOT NULL,        -- Model that cast this vote
    VoterCategory TEXT NOT NULL,       -- thinking, writing, coding
    
    -- Vote details
    Verdict TEXT NOT NULL CHECK (Verdict IN ('approve', 'reject', 'abstain', 'needs_revision')),
    ConfidenceScore REAL NOT NULL,     -- 0.0 - 1.0
    Reasoning TEXT,                    -- Why this verdict
    SuggestedFixes TEXT,               -- JSON array of suggested improvements
    
    CreatedAt TEXT NOT NULL,
    
    FOREIGN KEY (TaskId) REFERENCES InstructionTask(Id) ON DELETE CASCADE,
    FOREIGN KEY (AttemptId) REFERENCES ExecutionAttempt(Id) ON DELETE CASCADE
);

CREATE INDEX IX_Vote_Attempt ON ConsensusVote(AttemptId);

-- Human escalation queue
CREATE TABLE EscalationRequest (
    Id TEXT PRIMARY KEY,
    InstructionId TEXT NOT NULL,
    TaskId TEXT,                       -- Null if instruction-level escalation
    
    -- Escalation details
    EscalationType TEXT NOT NULL CHECK (EscalationType IN (
        'ambiguous_instruction', 'low_confidence', 'conflicting_consensus',
        'repeated_failure', 'destructive_action', 'permission_required'
    )),
    Priority TEXT NOT NULL CHECK (Priority IN ('low', 'medium', 'high', 'critical')),
    
    -- Context
    ContextJson TEXT NOT NULL,         -- All relevant context for decision
    OptionsJson TEXT,                  -- Possible choices for user
    
    -- Resolution
    Status TEXT NOT NULL CHECK (Status IN ('pending', 'viewed', 'resolved', 'expired', 'auto_resolved')),
    ResolvedById TEXT,
    Resolution TEXT,                   -- User's decision
    ResolutionNote TEXT,               -- User's explanation
    
    -- Timing
    CreatedAt TEXT NOT NULL,
    ExpiresAt TEXT,                    -- Auto-expire if not resolved
    ResolvedAt TEXT,
    
    FOREIGN KEY (InstructionId) REFERENCES Instruction(Id) ON DELETE CASCADE,
    FOREIGN KEY (TaskId) REFERENCES InstructionTask(Id) ON DELETE SET NULL,
    FOREIGN KEY (ResolvedById) REFERENCES User(Id) ON DELETE SET NULL
);

CREATE INDEX IX_Escalation_Status ON EscalationRequest(Status);
CREATE INDEX IX_Escalation_Priority ON EscalationRequest(Priority);

-- Telemetry for continuous improvement
CREATE TABLE ExecutionTelemetry (
    Id TEXT PRIMARY KEY,
    TaskId TEXT NOT NULL,
    
    -- Metrics
    TotalAttempts INTEGER NOT NULL,
    SuccessfulAttempt INTEGER,         -- Which attempt succeeded (null if all failed)
    RecoveryPath TEXT,                 -- JSON array of recovery steps taken
    FinalConfidence REAL,              -- Final confidence score
    
    -- Timing
    TotalDurationMs INTEGER NOT NULL,
    TimeToFirstSuccess INTEGER,        -- Ms to first successful attempt
    
    -- Learning signals
    FailurePatterns TEXT,              -- JSON array of detected patterns
    EffectiveStrategy TEXT,            -- What worked
    
    CreatedAt TEXT NOT NULL,
    
    FOREIGN KEY (TaskId) REFERENCES InstructionTask(Id) ON DELETE CASCADE
);

CREATE INDEX IX_Telemetry_Task ON ExecutionTelemetry(TaskId);
```

---

## 12.4 Self-Correction Agent

The Self-Correction Agent analyzes failed tasks, identifies root causes, and generates corrective strategies.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    SELF-CORRECTION AGENT                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐          │
│  │   Failure   │───▶│    Root     │───▶│  Strategy   │          │
│  │   Ingestion │    │   Cause     │    │  Generator  │          │
│  │             │    │   Analyzer  │    │             │          │
│  └─────────────┘    └─────────────┘    └─────────────┘          │
│         │                 │                   │                  │
│         ▼                 ▼                   ▼                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                  Correction Executor                     │    │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐    │    │
│  │  │ Prompt  │  │  Model  │  │ Context │  │ Output  │    │    │
│  │  │ Rewrite │  │  Switch │  │  Expand │  │  Refine │    │    │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘    │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Failure Classification

```go
type FailureCategory string

const (
    FailureHallucination   FailureCategory = "hallucination"      // Made up facts/code
    FailureIncomplete      FailureCategory = "incomplete"         // Missing required parts
    FailureMalformed       FailureCategory = "malformed"          // Bad syntax/structure
    FailureInconsistent    FailureCategory = "inconsistent"       // Contradicts context
    FailureOffTopic        FailureCategory = "off_topic"          // Doesn't address task
    FailureTimeout         FailureCategory = "timeout"            // Exceeded time limit
    FailureAPIError        FailureCategory = "api_error"          // External service failure
    FailureValidation      FailureCategory = "validation"         // Failed output validation
    FailureAmbiguous       FailureCategory = "ambiguous"          // Unclear requirements
)

type FailureAnalysis struct {
    Category      FailureCategory `json:"category"`
    Confidence    float64         `json:"confidence"`      // 0.0-1.0
    RootCause     string          `json:"rootCause"`       // Detailed explanation
    Evidence      []string        `json:"evidence"`        // Specific examples
    SuggestedFix  CorrectionStrategy `json:"suggestedFix"`
}
```

### Root Cause Analyzer

```go
type RootCauseAnalyzer struct {
    thinkingModel  ModelClient
    patternMatcher *PatternMatcher
    contextBuilder *ContextBuilder
}

const rootCausePrompt = `You are a failure analysis expert. Analyze why this AI task failed.

## Task Details
Title: {{.TaskTitle}}
Description: {{.TaskDescription}}
Target: {{.TargetFile}}

## Execution Context
Prompt Used:
{{.PromptUsed}}

## Model Output
{{.ModelOutput}}

## Validation Errors
{{.ValidationErrors}}

## Expected vs Actual
Expected: {{.ExpectedBehavior}}
Actual: {{.ActualBehavior}}

## Analysis Required
1. Categorize the failure type (hallucination, incomplete, malformed, inconsistent, off_topic, ambiguous)
2. Identify the root cause with specific evidence
3. Rate your confidence in this analysis (0.0-1.0)
4. Suggest a correction strategy

Respond in JSON:
{
  "category": "string",
  "confidence": 0.0-1.0,
  "rootCause": "detailed explanation",
  "evidence": ["specific example 1", "specific example 2"],
  "suggestedFix": {
    "strategy": "prompt_rewrite|model_switch|context_expand|decompose|escalate",
    "details": "specific instructions for the fix"
  }
}`

func (a *RootCauseAnalyzer) Analyze(ctx context.Context, failure *TaskFailure) (*FailureAnalysis, error) {
    // 1. Check pattern database for known failure signatures
    if pattern := a.patternMatcher.Match(failure); pattern != nil {
        return a.applyKnownPattern(pattern, failure)
    }
    
    // 2. Build analysis context
    analysisContext := a.contextBuilder.Build(failure)
    
    // 3. Call thinking model for deep analysis
    prompt := interpolate(rootCausePrompt, analysisContext)
    
    result, err := a.thinkingModel.GenerateStructured(ctx, GenerateRequest{
        SystemPrompt: "You are an expert at diagnosing AI system failures.",
        UserPrompt:   prompt,
        Temperature:  0.1, // Low temperature for analytical tasks
    })
    
    if err != nil {
        return nil, fmt.Errorf("root cause analysis failed: %w", err)
    }
    
    var analysis FailureAnalysis
    if err := json.Unmarshal([]byte(result.Json), &analysis); err != nil {
        return nil, fmt.Errorf("failed to parse analysis: %w", err)
    }
    
    // 4. Record pattern for future matching
    a.patternMatcher.RecordPattern(failure, &analysis)
    
    return &analysis, nil
}
```

### Correction Strategies

```go
type CorrectionStrategy struct {
    Strategy    string            `json:"strategy"`
    Details     string            `json:"details"`
    Parameters  map[string]string `json:"parameters,omitempty"`
}

type StrategyExecutor struct {
    promptRewriter   *PromptRewriter
    modelSelector    *AdaptiveModelSelector
    contextExpander  *ContextExpander
    taskDecomposer   *TaskDecomposer
}

func (e *StrategyExecutor) Execute(ctx context.Context, strategy CorrectionStrategy, task *InstructionTask) (*CorrectedTask, error) {
    switch strategy.Strategy {
    case "prompt_rewrite":
        return e.promptRewriter.Rewrite(ctx, task, strategy.Details)
        
    case "model_switch":
        return e.modelSelector.SelectAlternative(ctx, task, strategy.Parameters["preferred_category"])
        
    case "context_expand":
        return e.contextExpander.Expand(ctx, task, strategy.Parameters["missing_context"])
        
    case "decompose":
        return e.taskDecomposer.Decompose(ctx, task, strategy.Details)
        
    case "escalate":
        return nil, ErrEscalationRequired
        
    default:
        return nil, fmt.Errorf("unknown strategy: %s", strategy.Strategy)
    }
}
```

### Prompt Rewriter

```go
type PromptRewriter struct {
    writingModel ModelClient
}

const rewritePrompt = `Rewrite this prompt to fix the identified issue.

## Original Prompt
{{.OriginalPrompt}}

## Failure Analysis
Category: {{.FailureCategory}}
Root Cause: {{.RootCause}}
Suggested Fix: {{.SuggestedFix}}

## Rewrite Guidelines
- Address the root cause directly
- Add explicit constraints to prevent the failure
- Include examples if the task was misunderstood
- Add validation criteria the model should check

Return ONLY the rewritten prompt, no explanations.`

func (r *PromptRewriter) Rewrite(ctx context.Context, task *InstructionTask, guidance string) (*CorrectedTask, error) {
    prompt := interpolate(rewritePrompt, map[string]interface{}{
        "OriginalPrompt":  task.OriginalPrompt,
        "FailureCategory": task.LastFailure.Category,
        "RootCause":       task.LastFailure.RootCause,
        "SuggestedFix":    guidance,
    })
    
    result, err := r.writingModel.Generate(ctx, GenerateRequest{
        UserPrompt:  prompt,
        Temperature: 0.3,
    })
    
    if err != nil {
        return nil, err
    }
    
    return &CorrectedTask{
        Task:           task,
        CorrectedPrompt: result.Text,
        Strategy:       "prompt_rewrite",
    }, nil
}
```

---

## 12.5 Multi-Model Consensus

Execute critical tasks with multiple models and reach consensus through voting.

### Consensus Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    MULTI-MODEL CONSENSUS                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Task Distribution                       │    │
│  │                         │                                  │    │
│  │         ┌───────────────┼───────────────┐                 │    │
│  │         ▼               ▼               ▼                 │    │
│  │  ┌───────────┐   ┌───────────┐   ┌───────────┐           │    │
│  │  │  Model A  │   │  Model B  │   │  Model C  │           │    │
│  │  │ (Primary) │   │(Secondary)│   │ (Verifier)│           │    │
│  │  └───────────┘   └───────────┘   └───────────┘           │    │
│  │         │               │               │                 │    │
│  │         └───────────────┼───────────────┘                 │    │
│  │                         ▼                                  │    │
│  └─────────────────────────────────────────────────────────┘    │
│                            │                                      │
│                            ▼                                      │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Consensus Engine                        │    │
│  ├─────────────────────────────────────────────────────────┤    │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐    │    │
│  │  │ Compare │  │  Vote   │  │  Merge  │  │ Resolve │    │    │
│  │  │ Outputs │  │ Collect │  │ Results │  │Conflicts│    │    │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘    │    │
│  └─────────────────────────────────────────────────────────┘    │
│                            │                                      │
│                            ▼                                      │
│                   ┌─────────────────┐                            │
│                   │ Final Consensus │                            │
│                   │     Output      │                            │
│                   └─────────────────┘                            │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Consensus Configuration

```go
type ConsensusConfig struct {
    Enabled              bool    `json:"enabled"`
    MinModels            int     `json:"minModels"`            // Minimum models required (default: 2)
    RequiredAgreement    float64 `json:"requiredAgreement"`    // 0.0-1.0 (default: 0.66 = 2/3)
    VerifierRequired     bool    `json:"verifierRequired"`     // Require thinking model verification
    ParallelExecution    bool    `json:"parallelExecution"`    // Execute models in parallel
    TimeoutMs            int     `json:"timeoutMs"`            // Per-model timeout
    ConflictResolution   string  `json:"conflictResolution"`   // merge, vote, escalate
}

var DefaultConsensusConfig = ConsensusConfig{
    Enabled:            true,
    MinModels:          2,
    RequiredAgreement:  0.66,
    VerifierRequired:   true,
    ParallelExecution:  true,
    TimeoutMs:          30000,
    ConflictResolution: "merge",
}

// Task criticality determines consensus requirements
type TaskCriticality string

const (
    CriticalityLow      TaskCriticality = "low"      // Single model OK
    CriticalityMedium   TaskCriticality = "medium"   // 2 models required
    CriticalityHigh     TaskCriticality = "high"     // 3 models + verifier
    CriticalityCritical TaskCriticality = "critical" // 3 models + verifier + human review
)

func GetCriticalityForTask(task *InstructionTask) TaskCriticality {
    // Destructive operations = critical
    if task.TaskType == "delete" || strings.Contains(task.Description, "remove") {
        return CriticalityCritical
    }
    
    // Schema changes = high
    if strings.Contains(task.TargetFilePath, "schema") || 
       strings.Contains(task.TargetFilePath, "migration") {
        return CriticalityHigh
    }
    
    // Code generation = medium
    if task.ModelCategory == "coding" {
        return CriticalityMedium
    }
    
    // Documentation = low
    return CriticalityLow
}
```

### Consensus Engine

```go
type ConsensusEngine struct {
    models          map[string]ModelClient
    thinkingModel   ModelClient
    config          ConsensusConfig
    diffEngine      *SemanticDiffEngine
}

type ConsensusResult struct {
    Achieved        bool              `json:"achieved"`
    FinalOutput     string            `json:"finalOutput"`
    Confidence      float64           `json:"confidence"`
    ModelOutputs    []ModelOutput     `json:"modelOutputs"`
    Votes           []ConsensusVote   `json:"votes"`
    Conflicts       []ConflictDetail  `json:"conflicts,omitempty"`
    Resolution      string            `json:"resolution"`
}

type ModelOutput struct {
    ModelId     string  `json:"modelId"`
    Output      string  `json:"output"`
    Confidence  float64 `json:"confidence"`
    DurationMs  int     `json:"durationMs"`
}

func (e *ConsensusEngine) Execute(ctx context.Context, task *InstructionTask) (*ConsensusResult, error) {
    criticality := GetCriticalityForTask(task)
    config := e.getConfigForCriticality(criticality)
    
    // 1. Select models based on criticality
    selectedModels := e.selectModels(task, config.MinModels)
    
    // 2. Execute in parallel or sequential
    var outputs []ModelOutput
    if config.ParallelExecution {
        outputs = e.executeParallel(ctx, task, selectedModels, config.TimeoutMs)
    } else {
        outputs = e.executeSequential(ctx, task, selectedModels, config.TimeoutMs)
    }
    
    // 3. Compare outputs semantically
    comparison := e.diffEngine.Compare(outputs)
    
    // 4. Collect votes from verifier
    votes := e.collectVotes(ctx, task, outputs, comparison)
    
    // 5. Determine consensus
    return e.resolveConsensus(ctx, outputs, votes, comparison, config)
}

func (e *ConsensusEngine) executeParallel(
    ctx context.Context, 
    task *InstructionTask, 
    models []ModelClient, 
    timeoutMs int,
) []ModelOutput {
    results := make(chan ModelOutput, len(models))
    timeout := time.Duration(timeoutMs) * time.Millisecond
    
    for _, model := range models {
        go func(m ModelClient) {
            ctx, cancel := context.WithTimeout(ctx, timeout)
            defer cancel()
            
            start := time.Now()
            result, err := m.Generate(ctx, task.BuildPrompt())
            
            output := ModelOutput{
                ModelId:    m.Id(),
                DurationMs: int(time.Since(start).Milliseconds()),
            }
            
            if err == nil {
                output.Output = result.Text
                output.Confidence = result.Confidence
            }
            
            results <- output
        }(model)
    }
    
    outputs := make([]ModelOutput, 0, len(models))
    for i := 0; i < len(models); i++ {
        outputs = append(outputs, <-results)
    }
    
    return outputs
}
```

### Semantic Diff Engine

```go
type SemanticDiffEngine struct {
    embeddingModel ModelClient
}

type ComparisonResult struct {
    SimilarityMatrix  [][]float64     `json:"similarityMatrix"`
    Clusters          []OutputCluster `json:"clusters"`
    MajorityOutput    int             `json:"majorityOutput"`  // Index of majority cluster
    HasConflicts      bool            `json:"hasConflicts"`
    ConflictRegions   []ConflictRegion `json:"conflictRegions"`
}

type OutputCluster struct {
    Indices     []int   `json:"indices"`      // Which outputs are in this cluster
    Similarity  float64 `json:"similarity"`   // Internal similarity
    Centroid    string  `json:"centroid"`     // Representative output
}

type ConflictRegion struct {
    Section     string   `json:"section"`      // Which part conflicts
    Variants    []string `json:"variants"`     // Different versions
    Severity    string   `json:"severity"`     // minor, major, critical
}

func (d *SemanticDiffEngine) Compare(outputs []ModelOutput) *ComparisonResult {
    n := len(outputs)
    matrix := make([][]float64, n)
    
    // 1. Build similarity matrix
    for i := 0; i < n; i++ {
        matrix[i] = make([]float64, n)
        for j := 0; j < n; j++ {
            if i == j {
                matrix[i][j] = 1.0
            } else if j > i {
                sim := d.computeSimilarity(outputs[i].Output, outputs[j].Output)
                matrix[i][j] = sim
                matrix[j][i] = sim
            }
        }
    }
    
    // 2. Cluster similar outputs (threshold: 0.85)
    clusters := d.clusterOutputs(outputs, matrix, 0.85)
    
    // 3. Find majority cluster
    majorityIdx := 0
    maxSize := 0
    for i, c := range clusters {
        if len(c.Indices) > maxSize {
            maxSize = len(c.Indices)
            majorityIdx = i
        }
    }
    
    // 4. Detect conflict regions
    conflicts := d.detectConflicts(outputs, clusters)
    
    return &ComparisonResult{
        SimilarityMatrix: matrix,
        Clusters:         clusters,
        MajorityOutput:   majorityIdx,
        HasConflicts:     len(conflicts) > 0,
        ConflictRegions:  conflicts,
    }
}
```

### Vote Collection

```go
const verifierPrompt = `You are a verification expert. Review these AI outputs for a task.

## Task
{{.TaskDescription}}

## Outputs to Review
{{range $i, $out := .Outputs}}
### Output {{$i}} (Model: {{$out.ModelId}})
{{$out.Output}}

{{end}}

## Comparison Analysis
Similarity: {{.Comparison.SimilarityMatrix}}
Conflicts: {{.Comparison.ConflictRegions}}

## Your Task
1. Evaluate each output for correctness
2. Identify the best output or synthesize the best parts
3. Flag any concerning issues

Respond in JSON:
{
  "votes": [
    {"outputIndex": 0, "verdict": "approve|reject|needs_revision", "confidence": 0.9, "issues": []}
  ],
  "recommendation": "use_output_N|merge|reject_all|escalate",
  "mergeInstructions": "how to merge if applicable",
  "concerns": ["any critical issues"]
}`

func (e *ConsensusEngine) collectVotes(
    ctx context.Context,
    task *InstructionTask,
    outputs []ModelOutput,
    comparison *ComparisonResult,
) []ConsensusVote {
    prompt := interpolate(verifierPrompt, map[string]interface{}{
        "TaskDescription": task.Description,
        "Outputs":         outputs,
        "Comparison":      comparison,
    })
    
    result, err := e.thinkingModel.GenerateStructured(ctx, GenerateRequest{
        SystemPrompt: "You are an expert at evaluating AI outputs for quality and correctness.",
        UserPrompt:   prompt,
        Temperature:  0.1,
    })
    
    if err != nil {
        // Fallback to majority voting
        return e.majorityVote(outputs, comparison)
    }
    
    var verifierResult struct {
        Votes            []VoteDetail `json:"votes"`
        Recommendation   string       `json:"recommendation"`
        MergeInstructions string      `json:"mergeInstructions"`
        Concerns         []string     `json:"concerns"`
    }
    json.Unmarshal([]byte(result.Json), &verifierResult)
    
    return e.convertToConsensusVotes(task.Id, verifierResult)
}
```

---

## 12.6 Checkpoint & Rollback

Enables atomic execution with rollback capability on failure.

### Checkpoint Manager

```go
type CheckpointManager struct {
    store       CheckpointStore
    fileSystem  FileSystemClient
    retention   time.Duration
}

type Checkpoint struct {
    Id             string            `json:"id"`
    TaskId         string            `json:"taskId"`
    Type           string            `json:"type"`
    StateSnapshot  StateSnapshot     `json:"stateSnapshot"`
    CreatedAt      time.Time         `json:"createdAt"`
}

type StateSnapshot struct {
    Files          map[string]FileState  `json:"files"`
    DatabaseRows   map[string][]byte     `json:"databaseRows,omitempty"`
    InstructionState InstructionState    `json:"instructionState"`
}

type FileState struct {
    Path         string `json:"path"`
    ContentHash  string `json:"contentHash"`
    Content      string `json:"content,omitempty"`  // Full content for small files
    ContentRef   string `json:"contentRef,omitempty"` // Reference for large files
}

func (m *CheckpointManager) CreateCheckpoint(ctx context.Context, task *InstructionTask) (*Checkpoint, error) {
    // 1. Identify files that will be modified
    affectedFiles := m.predictAffectedFiles(task)
    
    // 2. Snapshot current state
    files := make(map[string]FileState)
    for _, path := range affectedFiles {
        content, err := m.fileSystem.Read(path)
        if err != nil && !os.IsNotExist(err) {
            return nil, err
        }
        
        files[path] = FileState{
            Path:        path,
            ContentHash: hash(content),
            Content:     content,
        }
    }
    
    // 3. Create checkpoint
    checkpoint := &Checkpoint{
        Id:     uuid.New().String(),
        TaskId: task.Id,
        Type:   "pre_execution",
        StateSnapshot: StateSnapshot{
            Files: files,
            InstructionState: m.captureInstructionState(task),
        },
        CreatedAt: time.Now(),
    }
    
    // 4. Store checkpoint
    if err := m.store.Save(ctx, checkpoint); err != nil {
        return nil, err
    }
    
    return checkpoint, nil
}

func (m *CheckpointManager) Rollback(ctx context.Context, checkpointId string) error {
    checkpoint, err := m.store.Get(ctx, checkpointId)
    if err != nil {
        return err
    }
    
    // 1. Restore files
    for path, state := range checkpoint.StateSnapshot.Files {
        if state.Content == "" && state.ContentRef != "" {
            // Fetch from content store
            state.Content, _ = m.store.GetContent(ctx, state.ContentRef)
        }
        
        if state.Content == "" {
            // File didn't exist, delete it
            m.fileSystem.Delete(path)
        } else {
            // Restore content
            m.fileSystem.Write(path, state.Content)
        }
    }
    
    // 2. Restore instruction state
    m.restoreInstructionState(ctx, checkpoint.StateSnapshot.InstructionState)
    
    return nil
}
```

### Transaction Wrapper

```go
type TransactionalExecutor struct {
    checkpointMgr  *CheckpointManager
    taskExecutor   *TaskExecutor
}

func (e *TransactionalExecutor) ExecuteWithRollback(ctx context.Context, task *InstructionTask) (*TaskResult, error) {
    // 1. Create pre-execution checkpoint
    checkpoint, err := e.checkpointMgr.CreateCheckpoint(ctx, task)
    if err != nil {
        return nil, fmt.Errorf("checkpoint creation failed: %w", err)
    }
    
    // 2. Execute task
    result, err := e.taskExecutor.Execute(ctx, task)
    
    // 3. Handle failure
    if err != nil {
        rollbackErr := e.checkpointMgr.Rollback(ctx, checkpoint.Id)
        if rollbackErr != nil {
            // Critical: rollback failed, log for manual intervention
            log.Error("rollback failed", "checkpoint", checkpoint.Id, "error", rollbackErr)
            return nil, fmt.Errorf("execution failed and rollback failed: %w (rollback: %v)", err, rollbackErr)
        }
        return nil, fmt.Errorf("execution failed, rolled back: %w", err)
    }
    
    // 4. Validate result
    if valid, validationErr := e.validate(ctx, task, result); !valid {
        e.checkpointMgr.Rollback(ctx, checkpoint.Id)
        return nil, fmt.Errorf("validation failed, rolled back: %w", validationErr)
    }
    
    // 5. Commit (mark checkpoint as superseded)
    e.checkpointMgr.Commit(ctx, checkpoint.Id)
    
    return result, nil
}
```

---

## 12.7 Adaptive Retry

Intelligently varies retry strategy based on failure analysis.

### Retry Strategy Matrix

| Attempt | Temperature | Prompt Strategy | Model Category | Context | Timeout |
|---------|-------------|-----------------|----------------|---------|---------|
| 1 | 0.7 | default | assigned | normal | 30s |
| 2 | 0.3 | explicit | assigned | expanded | 45s |
| 3 | 0.1 | step_by_step | thinking | full | 60s |
| 4 | 0.5 | few_shot | alternative | full | 90s |
| 5 | 0.0 | deterministic | best_available | maximum | 120s |

### Adaptive Retry Engine

```go
type AdaptiveRetryEngine struct {
    strategies       []RetryStrategy
    modelSelector    *ModelSelector
    contextBuilder   *ContextBuilder
    selfCorrection   *SelfCorrectionAgent
    maxAttempts      int
}

type RetryStrategy struct {
    Name           string  `json:"name"`
    Temperature    float64 `json:"temperature"`
    PromptStyle    string  `json:"promptStyle"`
    ModelOverride  string  `json:"modelOverride,omitempty"`
    ContextLevel   string  `json:"contextLevel"` // normal, expanded, full, maximum
    TimeoutMultiplier float64 `json:"timeoutMultiplier"`
}

var DefaultRetryStrategies = []RetryStrategy{
    {Name: "default", Temperature: 0.7, PromptStyle: "default", ContextLevel: "normal", TimeoutMultiplier: 1.0},
    {Name: "explicit", Temperature: 0.3, PromptStyle: "explicit", ContextLevel: "expanded", TimeoutMultiplier: 1.5},
    {Name: "step_by_step", Temperature: 0.1, PromptStyle: "step_by_step", ContextLevel: "full", TimeoutMultiplier: 2.0},
    {Name: "few_shot", Temperature: 0.5, PromptStyle: "few_shot", ContextLevel: "full", TimeoutMultiplier: 3.0},
    {Name: "deterministic", Temperature: 0.0, PromptStyle: "deterministic", ContextLevel: "maximum", TimeoutMultiplier: 4.0},
}

func (e *AdaptiveRetryEngine) ExecuteWithRetry(ctx context.Context, task *InstructionTask) (*TaskResult, error) {
    var lastError error
    var failureHistory []FailureAnalysis
    
    for attempt := 0; attempt < e.maxAttempts; attempt++ {
        // 1. Select strategy based on attempt and failure history
        strategy := e.selectStrategy(attempt, failureHistory)
        
        // 2. Apply strategy modifications
        modifiedTask := e.applyStrategy(task, strategy, failureHistory)
        
        // 3. Record attempt
        attemptRecord := e.recordAttemptStart(task.Id, attempt, strategy)
        
        // 4. Execute
        result, err := e.executeWithStrategy(ctx, modifiedTask, strategy)
        
        // 5. Record completion
        e.recordAttemptComplete(attemptRecord, result, err)
        
        if err == nil {
            return result, nil
        }
        
        lastError = err
        
        // 6. Analyze failure for next attempt
        if attempt < e.maxAttempts-1 {
            analysis, _ := e.selfCorrection.Analyze(ctx, &TaskFailure{
                Task:    task,
                Error:   err,
                Attempt: attempt,
                Strategy: strategy,
            })
            if analysis != nil {
                failureHistory = append(failureHistory, *analysis)
            }
        }
    }
    
    return nil, fmt.Errorf("all %d attempts failed: %w", e.maxAttempts, lastError)
}

func (e *AdaptiveRetryEngine) selectStrategy(attempt int, failures []FailureAnalysis) RetryStrategy {
    if attempt < len(DefaultRetryStrategies) {
        strategy := DefaultRetryStrategies[attempt]
        
        // Adapt based on failure patterns
        if len(failures) > 0 {
            lastFailure := failures[len(failures)-1]
            strategy = e.adaptStrategyToFailure(strategy, lastFailure)
        }
        
        return strategy
    }
    
    // Beyond default strategies, use most conservative
    return DefaultRetryStrategies[len(DefaultRetryStrategies)-1]
}

func (e *AdaptiveRetryEngine) adaptStrategyToFailure(strategy RetryStrategy, failure FailureAnalysis) RetryStrategy {
    switch failure.Category {
    case FailureHallucination:
        // Lower temperature, add constraints
        strategy.Temperature = min(strategy.Temperature, 0.1)
        strategy.PromptStyle = "constrained"
        
    case FailureIncomplete:
        // More context, step-by-step
        strategy.ContextLevel = "maximum"
        strategy.PromptStyle = "step_by_step"
        
    case FailureAmbiguous:
        // Switch to thinking model
        strategy.ModelOverride = "thinking"
        strategy.PromptStyle = "clarifying"
        
    case FailureTimeout:
        // Increase timeout, simplify
        strategy.TimeoutMultiplier *= 2
        strategy.ContextLevel = "minimal"
    }
    
    return strategy
}
```

### Prompt Strategies

```go
type PromptStyler struct {
    templates map[string]PromptTemplate
}

type PromptTemplate struct {
    SystemPrefix string
    UserPrefix   string
    UserSuffix   string
    Examples     []Example
}

var PromptStyles = map[string]PromptTemplate{
    "default": {
        SystemPrefix: "",
        UserPrefix:   "",
        UserSuffix:   "",
    },
    "explicit": {
        SystemPrefix: "Follow instructions EXACTLY. Do not add anything not requested. Do not skip any steps.",
        UserPrefix:   "EXPLICIT INSTRUCTIONS:\n",
        UserSuffix:   "\n\nCHECKLIST - Verify you have:\n- Addressed every point\n- Not added extra content\n- Followed the exact format requested",
    },
    "step_by_step": {
        SystemPrefix: "Think through each step carefully before responding. Show your reasoning.",
        UserPrefix:   "Complete this task step by step:\n\n",
        UserSuffix:   "\n\nSteps:\n1. First, analyze what is being asked\n2. Identify all requirements\n3. Plan your approach\n4. Execute each part\n5. Verify your output",
    },
    "few_shot": {
        SystemPrefix: "Learn from these examples and follow the same pattern.",
        UserPrefix:   "Here are examples of similar tasks:\n\n{{.Examples}}\n\nNow complete this task:\n",
        UserSuffix:   "",
    },
    "constrained": {
        SystemPrefix: "CRITICAL: Only use information explicitly provided. Do not invent, assume, or hallucinate any facts, names, or details.",
        UserPrefix:   "Using ONLY the following verified information:\n{{.Context}}\n\nTask:\n",
        UserSuffix:   "\n\nReminder: Every claim must be directly supported by the provided information.",
    },
    "deterministic": {
        SystemPrefix: "Provide a single, definitive answer. No alternatives, no hedging, no 'it depends'.",
        UserPrefix:   "",
        UserSuffix:   "\n\nProvide your final answer only. No explanations unless specifically requested.",
    },
}
```

---

## 12.8 Human Escalation

Graceful escalation for edge cases that exceed AI capability.

### Escalation Triggers

```go
type EscalationTrigger string

const (
    TriggerLowConfidence       EscalationTrigger = "low_confidence"        // Confidence < 0.6
    TriggerConflictingConsensus EscalationTrigger = "conflicting_consensus" // Models disagree
    TriggerRepeatedFailure     EscalationTrigger = "repeated_failure"      // 3+ failures
    TriggerDestructiveAction   EscalationTrigger = "destructive_action"    // Delete/modify critical
    TriggerAmbiguousInstruction EscalationTrigger = "ambiguous_instruction" // Can't determine intent
    TriggerPermissionRequired  EscalationTrigger = "permission_required"   // Needs explicit approval
    TriggerNoValidStrategy     EscalationTrigger = "no_valid_strategy"     // All strategies exhausted
)

type EscalationManager struct {
    queue           EscalationQueue
    notifier        NotificationService
    timeoutDuration time.Duration
    autoResolvers   map[EscalationTrigger]AutoResolver
}

type EscalationContext struct {
    Trigger         EscalationTrigger `json:"trigger"`
    Task            *InstructionTask  `json:"task"`
    FailureHistory  []FailureAnalysis `json:"failureHistory"`
    ConsensusResult *ConsensusResult  `json:"consensusResult,omitempty"`
    Options         []EscalationOption `json:"options"`
    Recommendation  string            `json:"recommendation"`
}

type EscalationOption struct {
    Id          string `json:"id"`
    Label       string `json:"label"`
    Description string `json:"description"`
    Risk        string `json:"risk"` // low, medium, high
    Action      string `json:"action"`
}

func (m *EscalationManager) Escalate(ctx context.Context, trigger EscalationTrigger, context EscalationContext) (*EscalationRequest, error) {
    // 1. Check for auto-resolver
    if resolver, ok := m.autoResolvers[trigger]; ok {
        if resolution, err := resolver.TryResolve(ctx, context); err == nil && resolution != nil {
            return m.createAutoResolvedRequest(context, resolution)
        }
    }
    
    // 2. Build escalation request
    request := &EscalationRequest{
        Id:             uuid.New().String(),
        InstructionId:  context.Task.InstructionId,
        TaskId:         &context.Task.Id,
        EscalationType: string(trigger),
        Priority:       m.determinePriority(trigger, context),
        ContextJson:    toJson(context),
        OptionsJson:    toJson(context.Options),
        Status:         "pending",
        CreatedAt:      time.Now(),
        ExpiresAt:      time.Now().Add(m.timeoutDuration),
    }
    
    // 3. Save to queue
    if err := m.queue.Enqueue(ctx, request); err != nil {
        return nil, err
    }
    
    // 4. Notify user
    m.notifier.NotifyEscalation(ctx, request)
    
    return request, nil
}
```

### Escalation UI Contract

```typescript
interface EscalationRequest {
  id: string;
  taskId: string;
  taskTitle: string;
  escalationType: EscalationTrigger;
  priority: 'low' | 'medium' | 'high' | 'critical';
  
  // Context for decision-making
  context: {
    originalInstruction: string;
    attemptCount: number;
    failureReasons: string[];
    aiRecommendation: string;
  };
  
  // Options for user
  options: EscalationOption[];
  
  // Timing
  createdAt: string;
  expiresAt: string;
  
  // Status
  status: 'pending' | 'viewed' | 'resolved' | 'expired';
}

interface EscalationOption {
  id: string;
  label: string;
  description: string;
  risk: 'low' | 'medium' | 'high';
  icon: string; // lucide icon name
}

// Example escalation for ambiguous instruction
const exampleEscalation: EscalationRequest = {
  id: "esc_123",
  taskId: "task_456",
  taskTitle: "Add authentication",
  escalationType: "ambiguous_instruction",
  priority: "medium",
  context: {
    originalInstruction: "Add authentication",
    attemptCount: 2,
    failureReasons: [
      "Unclear whether to use JWT or session-based auth",
      "No specified login provider (email, OAuth, etc.)"
    ],
    aiRecommendation: "Recommend clarifying authentication method before proceeding"
  },
  options: [
    {
      id: "jwt_email",
      label: "JWT with Email/Password",
      description: "Standard JWT authentication with email and password login",
      risk: "low",
      icon: "key"
    },
    {
      id: "oauth_google",
      label: "OAuth with Google",
      description: "OAuth 2.0 authentication using Google as provider",
      risk: "low",
      icon: "chrome"
    },
    {
      id: "session_email",
      label: "Session-based with Email",
      description: "Traditional session authentication with email login",
      risk: "low",
      icon: "cookie"
    },
    {
      id: "skip",
      label: "Skip this task",
      description: "Skip authentication for now, can add later",
      risk: "medium",
      icon: "skip-forward"
    },
    {
      id: "custom",
      label: "Provide custom instructions",
      description: "Write your own clarification",
      risk: "low",
      icon: "edit"
    }
  ],
  createdAt: "2026-01-30T10:00:00Z",
  expiresAt: "2026-01-30T11:00:00Z",
  status: "pending"
};
```

### Resolution Handler

```go
func (m *EscalationManager) Resolve(ctx context.Context, requestId string, resolution Resolution) error {
    request, err := m.queue.Get(ctx, requestId)
    if err != nil {
        return err
    }
    
    if request.Status != "pending" && request.Status != "viewed" {
        return ErrEscalationAlreadyResolved
    }
    
    // 1. Update request
    request.Status = "resolved"
    request.Resolution = resolution.OptionId
    request.ResolutionNote = resolution.Note
    request.ResolvedById = resolution.UserId
    request.ResolvedAt = time.Now()
    
    if err := m.queue.Update(ctx, request); err != nil {
        return err
    }
    
    // 2. Resume task execution with resolution
    return m.resumeExecution(ctx, request, resolution)
}

func (m *EscalationManager) resumeExecution(ctx context.Context, request *EscalationRequest, resolution Resolution) error {
    var context EscalationContext
    json.Unmarshal([]byte(request.ContextJson), &context)
    
    // Find selected option
    var selectedOption *EscalationOption
    for _, opt := range context.Options {
        if opt.Id == resolution.OptionId {
            selectedOption = &opt
            break
        }
    }
    
    if selectedOption == nil {
        return ErrInvalidOption
    }
    
    // Apply resolution to task
    switch selectedOption.Action {
    case "clarify":
        // Re-run with clarified instructions
        return m.taskService.RerunWithClarification(ctx, context.Task.Id, resolution.Note)
        
    case "skip":
        // Mark task as skipped
        return m.taskService.SkipTask(ctx, context.Task.Id, resolution.Note)
        
    case "approve":
        // Proceed with AI recommendation
        return m.taskService.ApproveRecommendation(ctx, context.Task.Id)
        
    case "custom":
        // Use custom instructions
        return m.taskService.RerunWithCustomInstructions(ctx, context.Task.Id, resolution.Note)
        
    default:
        // Option contains specific action
        return m.taskService.ApplyOption(ctx, context.Task.Id, selectedOption)
    }
}
```

---

## 12.9 Telemetry & Continuous Learning

### Telemetry Collection

```go
type TelemetryCollector struct {
    store      TelemetryStore
    analyzer   *PatternAnalyzer
    improver   *StrategyImprover
}

func (c *TelemetryCollector) RecordExecution(ctx context.Context, task *InstructionTask, result *ExecutionResult) {
    telemetry := &ExecutionTelemetry{
        Id:              uuid.New().String(),
        TaskId:          task.Id,
        TotalAttempts:   result.TotalAttempts,
        SuccessfulAttempt: result.SuccessfulAttempt,
        RecoveryPath:    toJson(result.RecoveryPath),
        FinalConfidence: result.Confidence,
        TotalDurationMs: result.TotalDurationMs,
        FailurePatterns: toJson(result.FailurePatterns),
        EffectiveStrategy: result.EffectiveStrategy,
        CreatedAt:       time.Now(),
    }
    
    c.store.Save(ctx, telemetry)
    
    // Async: analyze for pattern improvements
    go c.analyzer.AnalyzeNewData(telemetry)
}
```

### Success Rate Tracking

```go
type SuccessRateTracker struct {
    store       TelemetryStore
    window      time.Duration
    threshold   float64 // Alert if below this
}

type SuccessMetrics struct {
    Period          string  `json:"period"`
    TotalTasks      int     `json:"totalTasks"`
    SuccessfulTasks int     `json:"successfulTasks"`
    SuccessRate     float64 `json:"successRate"`
    AverageAttempts float64 `json:"averageAttempts"`
    EscalationRate  float64 `json:"escalationRate"`
    
    ByCategory map[string]CategoryMetrics `json:"byCategory"`
}

type CategoryMetrics struct {
    Category       string  `json:"category"`
    SuccessRate    float64 `json:"successRate"`
    CommonFailures []string `json:"commonFailures"`
}

func (t *SuccessRateTracker) GetCurrentMetrics(ctx context.Context) (*SuccessMetrics, error) {
    since := time.Now().Add(-t.window)
    
    telemetry, err := t.store.QuerySince(ctx, since)
    if err != nil {
        return nil, err
    }
    
    total := len(telemetry)
    successful := 0
    totalAttempts := 0
    escalated := 0
    
    categoryStats := make(map[string]*categoryAccumulator)
    
    for _, t := range telemetry {
        if t.SuccessfulAttempt != nil {
            successful++
        }
        totalAttempts += t.TotalAttempts
        
        // Check for escalation in recovery path
        var path []string
        json.Unmarshal([]byte(t.RecoveryPath), &path)
        for _, step := range path {
            if strings.Contains(step, "escalate") {
                escalated++
                break
            }
        }
    }
    
    return &SuccessMetrics{
        Period:          t.window.String(),
        TotalTasks:      total,
        SuccessfulTasks: successful,
        SuccessRate:     float64(successful) / float64(total),
        AverageAttempts: float64(totalAttempts) / float64(total),
        EscalationRate:  float64(escalated) / float64(total),
    }, nil
}
```

---

## 12.10 Integration with Instruction System

### Enhanced Task Execution

```go
// Modified from 03-instruction-system.md
func (e *TaskExecutor) executeTaskResilient(ctx context.Context, task *InstructionTask) error {
    // 1. Initialize resilient execution system
    res := NewResilientExecutor(
        e.checkpointMgr,
        e.consensusEngine,
        e.adaptiveRetry,
        e.selfCorrection,
        e.escalationMgr,
        e.telemetry,
    )
    
    // 2. Determine execution mode based on criticality
    criticality := GetCriticalityForTask(task)
    
    // 3. Execute with full resilience
    result, err := res.Execute(ctx, task, ResilientConfig{
        Criticality:       criticality,
        MaxAttempts:       5,
        ConsensusEnabled:  criticality >= CriticalityMedium,
        CheckpointEnabled: true,
        EscalationEnabled: true,
    })
    
    if err != nil {
        return e.handleFinalFailure(ctx, task, err)
    }
    
    // 4. Apply result
    return e.applyTaskResult(ctx, task, result)
}
```

### Resilient Executor Orchestrator

```go
type ResilientExecutor struct {
    checkpointMgr   *CheckpointManager
    consensusEngine *ConsensusEngine
    adaptiveRetry   *AdaptiveRetryEngine
    selfCorrection  *SelfCorrectionAgent
    escalationMgr   *EscalationManager
    telemetry       *TelemetryCollector
}

type ResilientConfig struct {
    Criticality       TaskCriticality
    MaxAttempts       int
    ConsensusEnabled  bool
    CheckpointEnabled bool
    EscalationEnabled bool
}

func (r *ResilientExecutor) Execute(ctx context.Context, task *InstructionTask, config ResilientConfig) (*TaskResult, error) {
    startTime := time.Now()
    var recoveryPath []string
    var failurePatterns []FailureAnalysis
    
    // 1. Create checkpoint if enabled
    var checkpoint *Checkpoint
    if config.CheckpointEnabled {
        var err error
        checkpoint, err = r.checkpointMgr.CreateCheckpoint(ctx, task)
        if err != nil {
            return nil, fmt.Errorf("checkpoint failed: %w", err)
        }
        recoveryPath = append(recoveryPath, "checkpoint_created")
    }
    
    // 2. Execute with adaptive retry
    result, err := r.adaptiveRetry.ExecuteWithRetry(ctx, task)
    
    if err != nil {
        recoveryPath = append(recoveryPath, "retry_exhausted")
        
        // 3. Try consensus if enabled
        if config.ConsensusEnabled {
            recoveryPath = append(recoveryPath, "trying_consensus")
            consensusResult, consensusErr := r.consensusEngine.Execute(ctx, task)
            
            if consensusErr == nil && consensusResult.Achieved {
                result = &TaskResult{
                    Markdown: consensusResult.FinalOutput,
                    Strategy: "consensus",
                }
                err = nil
                recoveryPath = append(recoveryPath, "consensus_achieved")
            } else if consensusResult != nil && consensusResult.HasConflicts {
                failurePatterns = append(failurePatterns, FailureAnalysis{
                    Category: FailureInconsistent,
                    RootCause: "Models produced conflicting outputs",
                })
            }
        }
    }
    
    // 4. Self-correction attempt
    if err != nil {
        recoveryPath = append(recoveryPath, "trying_self_correction")
        analysis, _ := r.selfCorrection.Analyze(ctx, &TaskFailure{Task: task, Error: err})
        
        if analysis != nil && analysis.SuggestedFix.Strategy != "escalate" {
            correctedTask, corrErr := r.selfCorrection.ApplyCorrection(ctx, task, analysis.SuggestedFix)
            if corrErr == nil {
                result, err = r.adaptiveRetry.ExecuteWithRetry(ctx, correctedTask)
                if err == nil {
                    recoveryPath = append(recoveryPath, "self_correction_succeeded")
                }
            }
        }
    }
    
    // 5. Escalate if still failing
    if err != nil && config.EscalationEnabled {
        recoveryPath = append(recoveryPath, "escalating")
        
        trigger := r.determineEscalationTrigger(failurePatterns)
        escalation, escErr := r.escalationMgr.Escalate(ctx, trigger, EscalationContext{
            Trigger:        trigger,
            Task:           task,
            FailureHistory: failurePatterns,
        })
        
        if escErr == nil {
            // Wait for resolution or timeout
            resolution, waitErr := r.escalationMgr.WaitForResolution(ctx, escalation.Id)
            if waitErr == nil {
                return r.handleEscalationResolution(ctx, task, resolution)
            }
        }
    }
    
    // 6. Record telemetry
    r.telemetry.RecordExecution(ctx, task, &ExecutionResult{
        Success:         err == nil,
        TotalAttempts:   len(failurePatterns) + 1,
        RecoveryPath:    recoveryPath,
        FailurePatterns: failurePatterns,
        TotalDurationMs: int(time.Since(startTime).Milliseconds()),
    })
    
    // 7. Rollback if failed and checkpoint exists
    if err != nil && checkpoint != nil {
        r.checkpointMgr.Rollback(ctx, checkpoint.Id)
        recoveryPath = append(recoveryPath, "rolled_back")
    }
    
    return result, err
}
```

---

## 12.11 Configuration

### System Configuration

```yaml
# resilient_execution.yaml
resilient_execution:
  enabled: true
  
  # Self-Correction
  self_correction:
    enabled: true
    max_correction_attempts: 2
    pattern_learning: true
    
  # Multi-Model Consensus
  consensus:
    enabled: true
    min_models: 2
    required_agreement: 0.66
    verifier_required: true
    parallel_execution: true
    timeout_ms: 30000
    
  # Checkpoint & Rollback
  checkpoint:
    enabled: true
    retention_hours: 24
    auto_cleanup: true
    
  # Adaptive Retry
  retry:
    max_attempts: 5
    strategies:
      - name: default
        temperature: 0.7
        timeout_multiplier: 1.0
      - name: explicit
        temperature: 0.3
        timeout_multiplier: 1.5
      - name: step_by_step
        temperature: 0.1
        timeout_multiplier: 2.0
      - name: few_shot
        temperature: 0.5
        timeout_multiplier: 3.0
      - name: deterministic
        temperature: 0.0
        timeout_multiplier: 4.0
        
  # Human Escalation
  escalation:
    enabled: true
    timeout_minutes: 60
    auto_expire: true
    notification_channels:
      - in_app
      - email
    
  # Telemetry
  telemetry:
    enabled: true
    success_rate_window_hours: 24
    alert_threshold: 0.95
```

---

## 12.12 Acceptance Criteria

### Self-Correction Agent
- [ ] Failure analysis completes within 5 seconds
- [ ] Root cause classification accuracy > 90%
- [ ] Correction strategies generate valid alternative prompts
- [ ] Pattern database grows and improves over time

### Multi-Model Consensus
- [ ] Parallel execution reduces total time vs sequential
- [ ] Semantic diff correctly identifies conflicts
- [ ] Voting produces consistent verdicts
- [ ] Merge logic handles partial agreements

### Checkpoint & Rollback
- [ ] Checkpoints capture complete file state
- [ ] Rollback restores exact previous state
- [ ] Cleanup removes expired checkpoints
- [ ] Transaction wrapper handles nested tasks

### Adaptive Retry
- [ ] Strategy selection adapts to failure type
- [ ] Each attempt uses different approach
- [ ] Timeout increases appropriately
- [ ] All 5 strategies are utilized

### Human Escalation
- [ ] Escalation queue notifies users
- [ ] Options are clear and actionable
- [ ] Resolution resumes task correctly
- [ ] Expired escalations handled gracefully

### Overall
- [ ] Success rate >= 98% measured over 1000+ tasks
- [ ] Average attempts per task < 1.5
- [ ] Escalation rate < 2%
- [ ] No data loss on failures

---

## 12.13 Related Specifications

- [Instruction System](./03-instruction-system.md) — Core pipeline
- [Instruction Segmentation](./05-instruction-segmentation.md) — Large instruction handling
- [LLM Server Management](./07-llm-server-management.md) — Model orchestration
- [AI Testing Strategy](./11-ai-testing.md) — Test coverage
