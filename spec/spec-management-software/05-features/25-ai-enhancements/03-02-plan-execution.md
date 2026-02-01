# Phase 3.2: Plan Execution Engine

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [03-plan-mode.md](./03-plan-mode.md)

---

## Overview

Step execution engine with state machine, dependency resolution, retry logic, and real-time progress streaming.

---

## State Machine

```mermaid
stateDiagram-v2
    [*] --> draft: Plan Created
    
    draft --> approved: User Approves
    draft --> cancelled: User Cancels
    
    approved --> executing: Start Execution
    
    executing --> paused: User Pauses
    executing --> completed: All Steps Done
    executing --> failed: Step Failed (no retry)
    
    paused --> executing: User Resumes
    paused --> cancelled: User Cancels
    
    failed --> executing: User Retries
    failed --> cancelled: User Cancels
    
    completed --> [*]
    cancelled --> [*]
```

### Step State Machine

```mermaid
stateDiagram-v2
    [*] --> pending: Step Created
    
    pending --> blocked: Has Unmet Dependencies
    pending --> ready: No Dependencies
    
    blocked --> ready: Dependencies Met
    
    ready --> running: Execution Starts
    
    running --> completed: Success
    running --> failed: Error
    
    failed --> running: Retry
    failed --> skipped: User Skips
    
    completed --> [*]
    skipped --> [*]
```

---

## Execution Engine

```go
// internal/ai/planner/executor.go

package planner

import (
	"context"
	"fmt"
	"sync"
	"time"
	
	"specmgmt/internal/ai/llm"
	"specmgmt/internal/storage"
)

type ExecutionEngine struct {
	llm          *llm.Client
	plans        *storage.PlanStore
	stepHandlers map[string]StepHandler
	
	mu          sync.Mutex
	activePlans map[string]*executionState
}

type executionState struct {
	plan      *ExecutionPlan
	cancel    context.CancelFunc
	pauseCh   chan struct{}
	resumeCh  chan struct{}
	isPaused  bool
}

type StepHandler interface {
	Execute(ctx context.Context, step *PlanStep, plan *ExecutionPlan) (*StepResult, error)
	CanRetry(err error) bool
}

type StepResult struct {
	Success   bool                   `json:"success"`
	Outputs   map[string]interface{} `json:"outputs,omitempty"`
	Message   string                 `json:"message,omitempty"`
	Artifacts []Artifact             `json:"artifacts,omitempty"`
}

type Artifact struct {
	Type     string `json:"type"`     // "file", "diagram", "code"
	Path     string `json:"path"`
	Content  string `json:"content,omitempty"`
	MimeType string `json:"mime_type,omitempty"`
}

func NewExecutionEngine(llm *llm.Client, plans *storage.PlanStore) *ExecutionEngine {
	engine := &ExecutionEngine{
		llm:          llm,
		plans:        plans,
		stepHandlers: make(map[string]StepHandler),
		activePlans:  make(map[string]*executionState),
	}
	
	// Register default handlers
	engine.RegisterHandler("analyze", &AnalyzeHandler{llm: llm})
	engine.RegisterHandler("generate", &GenerateHandler{llm: llm})
	engine.RegisterHandler("modify", &ModifyHandler{llm: llm})
	engine.RegisterHandler("validate", &ValidateHandler{})
	engine.RegisterHandler("diagram", &DiagramHandler{llm: llm})
	engine.RegisterHandler("execute", &ExecuteHandler{})
	engine.RegisterHandler("wait", &WaitHandler{})
	engine.RegisterHandler("conditional", &ConditionalHandler{llm: llm})
	
	return engine
}

func (e *ExecutionEngine) RegisterHandler(stepType string, handler StepHandler) {
	e.stepHandlers[stepType] = handler
}

// ExecuteStep runs a single step
func (e *ExecutionEngine) ExecuteStep(ctx context.Context, planID string, stepIndex int) (*StepResult, error) {
	plan, err := e.plans.Get(ctx, planID)
	if err != nil {
		return nil, fmt.Errorf("plan not found: %w", err)
	}
	
	if stepIndex < 0 || stepIndex >= len(plan.Steps) {
		return nil, fmt.Errorf("invalid step index: %d", stepIndex)
	}
	
	step := &plan.Steps[stepIndex]
	
	// Check dependencies
	if err := e.checkDependencies(plan, step); err != nil {
		return nil, err
	}
	
	// Get handler
	handler, ok := e.stepHandlers[step.Type]
	if !ok {
		return nil, fmt.Errorf("unknown step type: %s", step.Type)
	}
	
	// Update step status
	step.Status = "running"
	step.StartedAt = timePtr(time.Now())
	plan.Status = "executing"
	plan.CurrentStepIndex = stepIndex
	
	if err := e.plans.Update(ctx, plan); err != nil {
		return nil, err
	}
	
	// Execute with retry logic
	var result *StepResult
	maxRetries := step.MaxRetries
	if maxRetries == 0 {
		maxRetries = 3 // Default
	}
	
	for attempt := 0; attempt <= maxRetries; attempt++ {
		step.RetryCount = attempt
		
		result, err = handler.Execute(ctx, step, plan)
		
		if err == nil {
			break
		}
		
		if !handler.CanRetry(err) || attempt == maxRetries {
			// Final failure
			step.Status = "failed"
			step.Error = err.Error()
			plan.Status = "failed"
			e.plans.Update(ctx, plan)
			
			// Record in history
			e.recordStepHistory(ctx, plan.ID, step, "failed", nil, err.Error())
			
			return nil, err
		}
		
		// Exponential backoff
		backoff := time.Duration(1<<attempt) * time.Second
		select {
		case <-ctx.Done():
			return nil, ctx.Err()
		case <-time.After(backoff):
			// Continue to retry
		}
	}
	
	// Success
	step.Status = "completed"
	step.CompletedAt = timePtr(time.Now())
	step.Outputs = result.Outputs
	
	// Check if all steps complete
	allComplete := true
	for _, s := range plan.Steps {
		if s.Status != "completed" && s.Status != "skipped" {
			allComplete = false
			break
		}
	}
	
	if allComplete {
		plan.Status = "completed"
		plan.CompletedAt = timePtr(time.Now())
	} else {
		// Update ready steps
		e.updateReadySteps(plan)
	}
	
	if err := e.plans.Update(ctx, plan); err != nil {
		return nil, err
	}
	
	// Record in history
	e.recordStepHistory(ctx, plan.ID, step, "completed", result.Outputs, "")
	
	return result, nil
}

// ExecuteAll runs all remaining steps
func (e *ExecutionEngine) ExecuteAll(ctx context.Context, planID string) error {
	e.mu.Lock()
	
	// Create cancellable context
	execCtx, cancel := context.WithCancel(ctx)
	state := &executionState{
		cancel:   cancel,
		pauseCh:  make(chan struct{}),
		resumeCh: make(chan struct{}),
	}
	e.activePlans[planID] = state
	e.mu.Unlock()
	
	defer func() {
		e.mu.Lock()
		delete(e.activePlans, planID)
		e.mu.Unlock()
	}()
	
	plan, err := e.plans.Get(ctx, planID)
	if err != nil {
		return err
	}
	state.plan = plan
	
	// Execute steps in order
	for i := plan.CurrentStepIndex; i < len(plan.Steps); i++ {
		// Check for pause
		select {
		case <-state.pauseCh:
			state.isPaused = true
			plan.Status = "paused"
			e.plans.Update(ctx, plan)
			
			// Wait for resume
			select {
			case <-state.resumeCh:
				state.isPaused = false
				plan.Status = "executing"
				e.plans.Update(ctx, plan)
			case <-execCtx.Done():
				return execCtx.Err()
			}
		default:
		}
		
		// Check for cancellation
		select {
		case <-execCtx.Done():
			plan.Status = "cancelled"
			plan.CancelledAt = timePtr(time.Now())
			e.plans.Update(ctx, plan)
			return execCtx.Err()
		default:
		}
		
		// Skip completed/skipped steps
		if plan.Steps[i].Status == "completed" || plan.Steps[i].Status == "skipped" {
			continue
		}
		
		_, err := e.ExecuteStep(execCtx, planID, i)
		if err != nil {
			return err
		}
		
		// Reload plan to get updated state
		plan, _ = e.plans.Get(ctx, planID)
		state.plan = plan
	}
	
	return nil
}

// Pause pauses execution
func (e *ExecutionEngine) Pause(planID string) error {
	e.mu.Lock()
	defer e.mu.Unlock()
	
	state, ok := e.activePlans[planID]
	if !ok {
		return fmt.Errorf("plan not executing")
	}
	
	if !state.isPaused {
		state.pauseCh <- struct{}{}
	}
	
	return nil
}

// Resume resumes execution
func (e *ExecutionEngine) Resume(planID string) error {
	e.mu.Lock()
	defer e.mu.Unlock()
	
	state, ok := e.activePlans[planID]
	if !ok {
		return fmt.Errorf("plan not executing")
	}
	
	if state.isPaused {
		state.resumeCh <- struct{}{}
	}
	
	return nil
}

// Cancel cancels execution
func (e *ExecutionEngine) Cancel(planID string) error {
	e.mu.Lock()
	defer e.mu.Unlock()
	
	state, ok := e.activePlans[planID]
	if ok {
		state.cancel()
		return nil
	}
	
	// Plan not actively executing, just update status
	ctx := context.Background()
	plan, err := e.plans.Get(ctx, planID)
	if err != nil {
		return err
	}
	
	plan.Status = "cancelled"
	plan.CancelledAt = timePtr(time.Now())
	return e.plans.Update(ctx, plan)
}

func (e *ExecutionEngine) checkDependencies(plan *ExecutionPlan, step *PlanStep) error {
	for _, depID := range step.Dependencies {
		for _, s := range plan.Steps {
			if s.ID == depID && s.Status != "completed" {
				return fmt.Errorf("dependency %s not completed", depID)
			}
		}
	}
	return nil
}

func (e *ExecutionEngine) updateReadySteps(plan *ExecutionPlan) {
	for i := range plan.Steps {
		step := &plan.Steps[i]
		if step.Status != "pending" && step.Status != "blocked" {
			continue
		}
		
		// Check if all dependencies are met
		allDepsComplete := true
		for _, depID := range step.Dependencies {
			for _, s := range plan.Steps {
				if s.ID == depID && s.Status != "completed" {
					allDepsComplete = false
					break
				}
			}
			if !allDepsComplete {
				break
			}
		}
		
		if allDepsComplete {
			step.Status = "ready"
		} else {
			step.Status = "blocked"
		}
	}
}

func (e *ExecutionEngine) recordStepHistory(ctx context.Context, planID string, step *PlanStep, status string, outputs map[string]interface{}, errorMsg string) {
	history := &StepHistory{
		ID:          generateID(),
		PlanID:      planID,
		StepID:      step.ID,
		StepIndex:   step.Index,
		Status:      status,
		StartedAt:   step.StartedAt,
		CompletedAt: step.CompletedAt,
		Outputs:     outputs,
		Error:       errorMsg,
		RetryCount:  step.RetryCount,
	}
	
	e.plans.RecordHistory(ctx, history)
}

func timePtr(t time.Time) *time.Time {
	return &t
}
```

---

## Step Handlers

### Analyze Handler

```go
// internal/ai/planner/handlers/analyze.go

package handlers

type AnalyzeHandler struct {
	llm *llm.Client
}

func (h *AnalyzeHandler) Execute(ctx context.Context, step *PlanStep, plan *ExecutionPlan) (*StepResult, error) {
	// Get files to analyze from inputs
	files, _ := step.Inputs["files"].([]string)
	
	// Build analysis prompt
	prompt := fmt.Sprintf(`Analyze the following files and provide insights:

Request: %s

Files:
%s

Provide:
1. Summary of each file's purpose
2. Key patterns and structures
3. Potential issues or improvements
4. Relevance to the current task`, 
		step.Description,
		strings.Join(files, "\n"),
	)
	
	response, err := h.llm.Complete(ctx, llm.CompletionRequest{
		Model: "llama-3",
		Messages: []llm.Message{
			{Role: "system", Content: "You are a code analysis expert."},
			{Role: "user", Content: prompt},
		},
	})
	
	if err != nil {
		return nil, err
	}
	
	return &StepResult{
		Success: true,
		Message: "Analysis complete",
		Outputs: map[string]interface{}{
			"analysis": response.Content,
		},
	}, nil
}

func (h *AnalyzeHandler) CanRetry(err error) bool {
	return isRetryableError(err)
}
```

### Generate Handler

```go
// internal/ai/planner/handlers/generate.go

type GenerateHandler struct {
	llm *llm.Client
}

func (h *GenerateHandler) Execute(ctx context.Context, step *PlanStep, plan *ExecutionPlan) (*StepResult, error) {
	targetPath, _ := step.Inputs["target_path"].(string)
	template, _ := step.Inputs["template"].(string)
	
	prompt := fmt.Sprintf(`Generate content for: %s

Target file: %s
Template: %s

Requirements from the plan:
%s`,
		step.Title,
		targetPath,
		template,
		step.Description,
	)
	
	response, err := h.llm.Complete(ctx, llm.CompletionRequest{
		Model: "mistral", // Use coding model
		Messages: []llm.Message{
			{Role: "system", Content: "You are an expert developer. Generate clean, well-documented code."},
			{Role: "user", Content: prompt},
		},
	})
	
	if err != nil {
		return nil, err
	}
	
	// Parse and save generated content
	content := extractCodeBlock(response.Content)
	
	return &StepResult{
		Success: true,
		Message: fmt.Sprintf("Generated %s", targetPath),
		Outputs: map[string]interface{}{
			"content": content,
			"path":    targetPath,
		},
		Artifacts: []Artifact{
			{Type: "file", Path: targetPath, Content: content},
		},
	}, nil
}

func (h *GenerateHandler) CanRetry(err error) bool {
	return isRetryableError(err)
}
```

### Execute Handler (brun)

```go
// internal/ai/planner/handlers/execute.go

type ExecuteHandler struct{}

func (h *ExecuteHandler) Execute(ctx context.Context, step *PlanStep, plan *ExecutionPlan) (*StepResult, error) {
	command, _ := step.Inputs["command"].(string)
	args, _ := step.Inputs["args"].([]string)
	workDir, _ := step.Inputs["work_dir"].(string)
	
	// Build command
	cmd := exec.CommandContext(ctx, command, args...)
	if workDir != "" {
		cmd.Dir = workDir
	}
	
	// Capture output
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr
	
	err := cmd.Run()
	
	if err != nil {
		return &StepResult{
			Success: false,
			Message: err.Error(),
			Outputs: map[string]interface{}{
				"stdout": stdout.String(),
				"stderr": stderr.String(),
				"exit_code": cmd.ProcessState.ExitCode(),
			},
		}, err
	}
	
	return &StepResult{
		Success: true,
		Message: "Command executed successfully",
		Outputs: map[string]interface{}{
			"stdout": stdout.String(),
			"stderr": stderr.String(),
			"exit_code": 0,
		},
	}, nil
}

func (h *ExecuteHandler) CanRetry(err error) bool {
	// Don't retry command execution failures
	return false
}
```

---

## Progress Streaming

```go
// internal/ai/planner/streamer.go

package planner

import (
	"encoding/json"
	"net/http"
	"sync"
)

type ProgressStreamer struct {
	mu        sync.RWMutex
	listeners map[string][]chan ProgressEvent
}

type ProgressEvent struct {
	PlanID    string                 `json:"plan_id"`
	StepIndex int                    `json:"step_index"`
	Status    string                 `json:"status"`
	Message   string                 `json:"message,omitempty"`
	Progress  float64                `json:"progress,omitempty"` // 0-100
	Data      map[string]interface{} `json:"data,omitempty"`
}

func NewProgressStreamer() *ProgressStreamer {
	return &ProgressStreamer{
		listeners: make(map[string][]chan ProgressEvent),
	}
}

func (s *ProgressStreamer) Subscribe(planID string) <-chan ProgressEvent {
	s.mu.Lock()
	defer s.mu.Unlock()
	
	ch := make(chan ProgressEvent, 100)
	s.listeners[planID] = append(s.listeners[planID], ch)
	return ch
}

func (s *ProgressStreamer) Unsubscribe(planID string, ch <-chan ProgressEvent) {
	s.mu.Lock()
	defer s.mu.Unlock()
	
	listeners := s.listeners[planID]
	for i, listener := range listeners {
		if listener == ch {
			s.listeners[planID] = append(listeners[:i], listeners[i+1:]...)
			close(listener)
			break
		}
	}
}

func (s *ProgressStreamer) Emit(event ProgressEvent) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	
	for _, ch := range s.listeners[event.PlanID] {
		select {
		case ch <- event:
		default:
			// Drop if channel full
		}
	}
}

// HTTP SSE handler
func (s *ProgressStreamer) HandleSSE(w http.ResponseWriter, r *http.Request) {
	planID := r.URL.Query().Get("plan_id")
	if planID == "" {
		http.Error(w, "plan_id required", http.StatusBadRequest)
		return
	}
	
	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")
	
	flusher, ok := w.(http.Flusher)
	if !ok {
		http.Error(w, "streaming not supported", http.StatusInternalServerError)
		return
	}
	
	events := s.Subscribe(planID)
	defer s.Unsubscribe(planID, events)
	
	for {
		select {
		case event, ok := <-events:
			if !ok {
				return
			}
			
			data, _ := json.Marshal(event)
			fmt.Fprintf(w, "data: %s\n\n", data)
			flusher.Flush()
			
		case <-r.Context().Done():
			return
		}
	}
}
```

---

## Frontend Hook

```typescript
// hooks/usePlanExecution.ts

import { useState, useCallback, useEffect } from 'react';
import { ExecutionPlan, PlanStep, StepStatus } from '@/types/plan';

interface ProgressEvent {
  plan_id: string;
  step_index: number;
  status: string;
  message?: string;
  progress?: number;
  data?: Record<string, unknown>;
}

export function usePlanExecution(planId: string | null) {
  const [plan, setPlan] = useState<ExecutionPlan | null>(null);
  const [isExecuting, setIsExecuting] = useState(false);
  const [currentProgress, setCurrentProgress] = useState<ProgressEvent | null>(null);
  
  // Subscribe to SSE progress
  useEffect(() => {
    if (!planId || !isExecuting) return;
    
    const eventSource = new EventSource(`/api/v1/plans/${planId}/progress`);
    
    eventSource.onmessage = (event) => {
      const progress: ProgressEvent = JSON.parse(event.data);
      setCurrentProgress(progress);
      
      // Update plan state based on progress
      setPlan(prev => {
        if (!prev) return prev;
        
        const steps = [...prev.steps];
        if (progress.step_index >= 0 && progress.step_index < steps.length) {
          steps[progress.step_index] = {
            ...steps[progress.step_index],
            status: progress.status as StepStatus,
          };
        }
        
        return { ...prev, steps };
      });
    };
    
    eventSource.onerror = () => {
      eventSource.close();
    };
    
    return () => eventSource.close();
  }, [planId, isExecuting]);
  
  const executeStep = useCallback(async (stepIndex: number) => {
    if (!planId) return;
    
    setIsExecuting(true);
    
    try {
      const response = await fetch(`/api/v1/plans/${planId}/steps/${stepIndex}/execute`, {
        method: 'POST',
      });
      
      if (!response.ok) {
        throw new Error('Step execution failed');
      }
      
      const result = await response.json();
      return result;
    } finally {
      setIsExecuting(false);
    }
  }, [planId]);
  
  const executeAll = useCallback(async () => {
    if (!planId) return;
    
    setIsExecuting(true);
    
    try {
      const response = await fetch(`/api/v1/plans/${planId}/execute-all`, {
        method: 'POST',
      });
      
      if (!response.ok) {
        throw new Error('Execution failed');
      }
    } finally {
      setIsExecuting(false);
    }
  }, [planId]);
  
  const pause = useCallback(async () => {
    if (!planId) return;
    await fetch(`/api/v1/plans/${planId}/pause`, { method: 'POST' });
  }, [planId]);
  
  const resume = useCallback(async () => {
    if (!planId) return;
    setIsExecuting(true);
    await fetch(`/api/v1/plans/${planId}/resume`, { method: 'POST' });
  }, [planId]);
  
  const cancel = useCallback(async () => {
    if (!planId) return;
    await fetch(`/api/v1/plans/${planId}/cancel`, { method: 'POST' });
    setIsExecuting(false);
  }, [planId]);
  
  return {
    plan,
    isExecuting,
    currentProgress,
    executeStep,
    executeAll,
    pause,
    resume,
    cancel,
  };
}
```

---

## Testing

| Test Case | Description | Expected Result |
|-----------|-------------|-----------------|
| Execute single step | Run one step | Step completes with outputs |
| Execute all steps | Run all steps sequentially | All steps complete |
| Dependency blocking | Step with unmet deps | Returns dependency error |
| Retry on failure | Retryable error occurs | Retries up to maxRetries |
| Non-retryable failure | Command execution fails | Fails immediately |
| Pause execution | Pause during executeAll | Plan status becomes paused |
| Resume execution | Resume after pause | Continues from current step |
| Cancel execution | Cancel during execution | Plan status becomes cancelled |
| Progress streaming | Subscribe to SSE | Receives progress events |

---

## Related Specs

- [03-plan-mode.md](./03-plan-mode.md) - Parent spec
- [03-01-plan-generation.md](./03-01-plan-generation.md) - Plan generation
- [03-03-approval-workflow.md](./03-03-approval-workflow.md) - Approval UI
