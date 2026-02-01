# 07. Approval Workflow

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [Overview](./00-overview.md)

---

## Purpose

Define the user approval workflow that ensures all generated Golang code is reviewed and explicitly approved before execution. This provides safety, transparency, and user control over AI-driven file operations.

---

## Workflow States

```
┌─────────────────────────────────────────────────────────────┐
│                    APPROVAL WORKFLOW                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────┐    ┌──────────┐    ┌──────────┐              │
│  │ PENDING  │───▶│ PREVIEW  │───▶│ APPROVED │              │
│  │          │    │          │    │          │              │
│  └──────────┘    └────┬─────┘    └────┬─────┘              │
│                       │               │                     │
│                       ▼               ▼                     │
│                  ┌──────────┐    ┌──────────┐              │
│                  │ REJECTED │    │ EXECUTED │              │
│                  │          │    │          │              │
│                  └──────────┘    └──────────┘              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## State Definitions

```go
type ApprovalStatus string

const (
    StatusPending   ApprovalStatus = "pending"
    StatusPreview   ApprovalStatus = "preview"    // Dry-run completed
    StatusApproved  ApprovalStatus = "approved"   // User approved
    StatusRejected  ApprovalStatus = "rejected"   // User rejected
    StatusExecuted  ApprovalStatus = "executed"   // Successfully executed
    StatusFailed    ApprovalStatus = "failed"     // Execution failed
)

type ApprovalRequest struct {
    Id              string         `json:"id"`
    TaskId          uint           `json:"taskId"`
    TaskName        string         `json:"taskName"`
    Description     string         `json:"description"`
    GolangCode      string         `json:"golangCode"`
    Status          ApprovalStatus `json:"status"`
    DryRunResult    *DryRunResult  `json:"dryRunResult,omitempty"`
    CreatedAt       time.Time      `json:"createdAt"`
    ApprovedBy      *string        `json:"approvedBy,omitempty"`
    ApprovedAt      *time.Time     `json:"approvedAt,omitempty"`
    RejectionReason *string        `json:"rejectionReason,omitempty"`
    ExecutionResult *ExecutionResult `json:"executionResult,omitempty"`
}
```

---

## Approval Flow

### Phase 1: Code Generation

```go
func (aw *ApprovalWorkflow) CreateApprovalRequest(
    taskName string,
    description string,
    code string,
) (*ApprovalRequest, error) {
    request := &ApprovalRequest{
        Id:          uuid.New().String(),
        TaskName:    taskName,
        Description: description,
        GolangCode:  code,
        Status:      StatusPending,
        CreatedAt:   time.Now(),
    }
    
    // Store in database
    if err := aw.db.Create(request).Error; err != nil {
        return nil, err
    }
    
    return request, nil
}
```

### Phase 2: Dry-Run Preview

```go
type DryRunResult struct {
    Success         bool               `json:"success"`
    AffectedFiles   []AffectedFile     `json:"affectedFiles"`
    Operations      []PlannedOperation `json:"operations"`
    Warnings        []string           `json:"warnings"`
    EstimatedTime   time.Duration      `json:"estimatedTime"`
    CompilationOk   bool               `json:"compilationOk"`
    CompileErrors   []string           `json:"compileErrors,omitempty"`
}

type AffectedFile struct {
    Path      string `json:"path"`
    Operation string `json:"operation"`
    SizeBefore int64 `json:"sizeBefore,omitempty"`
    SizeAfter  int64 `json:"sizeAfter,omitempty"`
}

type PlannedOperation struct {
    Order       int    `json:"order"`
    Description string `json:"description"`
    OldPath     string `json:"oldPath,omitempty"`
    NewPath     string `json:"newPath,omitempty"`
    Reversible  bool   `json:"reversible"`
}

func (aw *ApprovalWorkflow) RunDryRun(requestId string) (*DryRunResult, error) {
    request, err := aw.getRequest(requestId)
    if err != nil {
        return nil, err
    }
    
    // Create temp directory for compilation
    tempDir, err := os.MkdirTemp("", "codegen-dryrun-*")
    if err != nil {
        return nil, err
    }
    defer os.RemoveAll(tempDir)
    
    result := &DryRunResult{}
    
    // Step 1: Write code to temp directory
    mainPath := filepath.Join(tempDir, "main.go")
    if err := os.WriteFile(mainPath, []byte(request.GolangCode), 0644); err != nil {
        return nil, err
    }
    
    // Step 2: Create go.mod
    modContent := "module task\n\ngo 1.21\n"
    modPath := filepath.Join(tempDir, "go.mod")
    if err := os.WriteFile(modPath, []byte(modContent), 0644); err != nil {
        return nil, err
    }
    
    // Step 3: Compile
    cmd := exec.Command("go", "build", "-o", "task", ".")
    cmd.Dir = tempDir
    output, err := cmd.CombinedOutput()
    if err != nil {
        result.CompilationOk = false
        result.CompileErrors = parseCompileErrors(string(output))
        result.Success = false
        return result, nil
    }
    result.CompilationOk = true
    
    // Step 4: Execute with --dry-run
    execCmd := exec.Command("./task", "--dry-run", "--json")
    execCmd.Dir = tempDir
    execOutput, err := execCmd.CombinedOutput()
    if err != nil {
        result.Success = false
        result.Warnings = append(result.Warnings, string(execOutput))
        return result, nil
    }
    
    // Step 5: Parse dry-run output
    if err := json.Unmarshal(execOutput, &result); err != nil {
        result.Warnings = append(result.Warnings, "Failed to parse dry-run output")
    }
    
    result.Success = true
    
    // Update request status
    request.Status = StatusPreview
    request.DryRunResult = result
    aw.db.Save(request)
    
    return result, nil
}
```

### Phase 3: User Approval

```go
type ApprovalAction string

const (
    ActionApprove ApprovalAction = "approve"
    ActionReject  ApprovalAction = "reject"
    ActionEdit    ApprovalAction = "edit"
)

type ApprovalDecision struct {
    RequestId string         `json:"requestId"`
    Action    ApprovalAction `json:"action"`
    UserId    string         `json:"userId"`
    Reason    string         `json:"reason,omitempty"`
    EditedCode string        `json:"editedCode,omitempty"`
}

func (aw *ApprovalWorkflow) ProcessDecision(decision ApprovalDecision) error {
    request, err := aw.getRequest(decision.RequestId)
    if err != nil {
        return err
    }
    
    switch decision.Action {
    case ActionApprove:
        request.Status = StatusApproved
        request.ApprovedBy = &decision.UserId
        now := time.Now()
        request.ApprovedAt = &now
        
    case ActionReject:
        request.Status = StatusRejected
        request.RejectionReason = &decision.Reason
        
    case ActionEdit:
        // User modified the code - need new dry-run
        request.GolangCode = decision.EditedCode
        request.Status = StatusPending
        request.DryRunResult = nil
    }
    
    return aw.db.Save(request).Error
}
```

### Phase 4: Execution

```go
func (aw *ApprovalWorkflow) Execute(requestId string) (*ExecutionResult, error) {
    request, err := aw.getRequest(requestId)
    if err != nil {
        return nil, err
    }
    
    if request.Status != StatusApproved {
        return nil, fmt.Errorf("request not approved: status=%s", request.Status)
    }
    
    // Execute the code
    result, err := aw.executor.Execute(request.GolangCode, ExecuteConfig{
        DryRun: false,
    })
    
    if err != nil {
        request.Status = StatusFailed
        request.ExecutionResult = &ExecutionResult{
            Success:      false,
            ErrorMessage: err.Error(),
        }
    } else {
        request.Status = StatusExecuted
        request.ExecutionResult = result
    }
    
    return result, aw.db.Save(request).Error
}
```

---

## UI Interface

### Approval Dialog Structure

```
┌─────────────────────────────────────────────────────────────┐
│  🔍 Code Review: Lowercase All Filenames                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  📋 TASK DESCRIPTION                                        │
│  Rename all files in the spec folder to lowercase           │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  💻 GENERATED CODE                               [Copy] [Edit]│
│  ┌─────────────────────────────────────────────────────────┐│
│  │ // Generated by AI Agent on 2026-01-29T10:30:00Z       ││
│  │ // Task: lowercase-filenames                            ││
│  │ package main                                            ││
│  │                                                         ││
│  │ import (                                                ││
│  │     "os"                                                ││
│  │     "path/filepath"                                     ││
│  │     "strings"                                           ││
│  │ )                                                       ││
│  │ ...                                                     ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  📊 DRY-RUN RESULTS                                         │
│                                                              │
│  ✅ Compilation: Success                                    │
│  📁 Files affected: 12                                      │
│  ⏱️  Estimated time: <1 second                              │
│                                                              │
│  PLANNED OPERATIONS:                                        │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ 1. RENAME: README.md → readme.md                        ││
│  │ 2. RENAME: CHANGELOG.md → changelog.md                  ││
│  │ 3. RENAME: 00-Overview.md → 00-overview.md              ││
│  │ ...                                                     ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  ⚠️ WARNINGS:                                               │
│  • This operation cannot be automatically undone             │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  [Cancel]            [Edit Code]    [✅ Approve & Execute]  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## TypeScript Types

```typescript
enum ApprovalStatus {
  Pending = "pending",
  Preview = "preview",
  Approved = "approved",
  Rejected = "rejected",
  Executed = "executed",
  Failed = "failed",
}

enum ApprovalAction {
  Approve = "approve",
  Reject = "reject",
  Edit = "edit",
}

interface ApprovalRequest {
  readonly id: string;
  readonly taskId: number;
  readonly taskName: string;
  readonly description: string;
  readonly golangCode: string;
  readonly status: ApprovalStatus;
  readonly dryRunResult: DryRunResult | null;
  readonly createdAt: Date;
  readonly approvedBy: string | null;
  readonly approvedAt: Date | null;
  readonly rejectionReason: string | null;
  readonly executionResult: ExecutionResult | null;
}

interface DryRunResult {
  readonly success: boolean;
  readonly affectedFiles: readonly AffectedFile[];
  readonly operations: readonly PlannedOperation[];
  readonly warnings: readonly string[];
  readonly estimatedTime: number;
  readonly compilationOk: boolean;
  readonly compileErrors: readonly string[];
}

interface AffectedFile {
  readonly path: string;
  readonly operation: OperationType;
  readonly sizeBefore: number | null;
  readonly sizeAfter: number | null;
}

interface PlannedOperation {
  readonly order: number;
  readonly description: string;
  readonly oldPath: string | null;
  readonly newPath: string | null;
  readonly reversible: boolean;
}

interface ApprovalDecision {
  readonly requestId: string;
  readonly action: ApprovalAction;
  readonly userId: string;
  readonly reason: string | null;
  readonly editedCode: string | null;
}
```

---

## API Endpoints

### POST /api/v1/code-generation/approve

Create or update approval decision.

**Request:**
```json
{
  "requestId": "uuid",
  "action": "approve",
  "userId": "user-123"
}
```

**Response (200):**
```json
{
  "success": true,
  "request": {
    "id": "uuid",
    "status": "approved",
    "approvedBy": "user-123",
    "approvedAt": "2026-01-29T10:30:00Z"
  }
}
```

### GET /api/v1/code-generation/dry-run/{requestId}

Execute dry-run for a pending request.

**Response (200):**
```json
{
  "success": true,
  "compilationOk": true,
  "affectedFiles": [
    {"path": "README.md", "operation": "rename"}
  ],
  "operations": [
    {"order": 1, "description": "Rename README.md to readme.md"}
  ],
  "warnings": []
}
```

---

## Security Considerations

1. **User Authentication:** All approval actions require authenticated user
2. **Audit Trail:** Every decision is logged with user ID and timestamp
3. **Code Sandboxing:** Dry-run executes in isolated temp directory
4. **Path Validation:** Generated code cannot access paths outside target
5. **Timeout Limits:** Execution has configurable timeout (default: 60s)

---

## Related Specs

- [06-execution-engine.md](./06-execution-engine.md) — Execution after approval
- [08-history-logger.md](./08-history-logger.md) — Audit trail logging
- [13-code-review-ui.md](./13-code-review-ui.md) — Frontend interface
