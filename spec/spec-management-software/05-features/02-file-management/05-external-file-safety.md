# External File Safety System

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Parent:** [File Management](./00-overview.md)

---

## Purpose

Define safety mechanisms requiring explicit user consent for any AI-driven file operations (rename, move, delete) performed **outside the project's root directory**. This prevents accidental data loss and ensures auditability for operations affecting external files.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                  EXTERNAL FILE SAFETY SYSTEM                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────┐   ┌──────────────────┐                    │
│  │  Path Classifier │──▶│ Consent Manager  │                    │
│  │                  │   │                  │                    │
│  │  • Is external?  │   │  • Consent popup │                    │
│  │  • Danger level  │   │  • Type-to-confirm│                   │
│  └──────────────────┘   └────────┬─────────┘                    │
│                                  │                               │
│                                  ▼                               │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │              DESTRUCTIVE ACTION CONFIRMATION              │   │
│  │                                                          │   │
│  │  ╭──────────────────────────────────────────────────────╮│   │
│  │  │ ⚠️ EXTERNAL FILE OPERATION                           ││   │
│  │  │                                                      ││   │
│  │  │ You are about to DELETE:                             ││   │
│  │  │ /home/user/documents/important-file.md               ││   │
│  │  │                                                      ││   │
│  │  │ This file is OUTSIDE your project directory.         ││   │
│  │  │                                                      ││   │
│  │  │ To confirm, type: DELETE                             ││   │
│  │  │ ┌────────────────────────────────────────────────┐   ││   │
│  │  │ │                                                │   ││   │
│  │  │ └────────────────────────────────────────────────┘   ││   │
│  │  │                                                      ││   │
│  │  │ [Cancel]                              [Confirm]      ││   │
│  │  ╰──────────────────────────────────────────────────────╯│   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Path Classification

### Internal vs External Detection

```go
type PathClassifier struct {
    projectRoot string
}

type PathClassification struct {
    Path        string
    IsExternal  bool
    DangerLevel DangerLevel
    Reason      string
}

type DangerLevel int

const (
    DangerLevelNone DangerLevel = iota
    DangerLevelLow              // Moving within Git repo
    DangerLevelMedium           // External but in safe location
    DangerLevelHigh             // External, potentially destructive
    DangerLevelCritical         // System paths, other projects
)

func (pc *PathClassifier) Classify(targetPath string) PathClassification {
    absTarget, err := filepath.Abs(targetPath)
    if err != nil {
        return PathClassification{
            Path:        targetPath,
            IsExternal:  true,
            DangerLevel: DangerLevelCritical,
            Reason:      "Unable to resolve absolute path",
        }
    }
    
    absRoot, _ := filepath.Abs(pc.projectRoot)
    
    // Check if path is inside project root
    if strings.HasPrefix(absTarget, absRoot+string(filepath.Separator)) {
        return PathClassification{
            Path:        targetPath,
            IsExternal:  false,
            DangerLevel: DangerLevelNone,
            Reason:      "Path is within project directory",
        }
    }
    
    // External path - determine danger level
    dangerLevel := pc.assessDangerLevel(absTarget)
    
    return PathClassification{
        Path:        absTarget,
        IsExternal:  true,
        DangerLevel: dangerLevel,
        Reason:      pc.getDangerReason(dangerLevel),
    }
}

func (pc *PathClassifier) assessDangerLevel(absPath string) DangerLevel {
    // Critical: System directories
    systemPaths := []string{"/usr", "/bin", "/sbin", "/etc", "/var", "/boot", "C:\\Windows", "C:\\Program Files"}
    for _, sp := range systemPaths {
        if strings.HasPrefix(absPath, sp) {
            return DangerLevelCritical
        }
    }
    
    // Critical: Other project directories
    if pc.isOtherProject(absPath) {
        return DangerLevelCritical
    }
    
    // High: User home directories
    homeDir, _ := os.UserHomeDir()
    if strings.HasPrefix(absPath, homeDir) {
        // But not in safe temp locations
        if strings.Contains(absPath, ".tmp") || strings.Contains(absPath, "tmp") {
            return DangerLevelMedium
        }
        return DangerLevelHigh
    }
    
    return DangerLevelHigh
}
```

---

## Consent Manager

### Consent Requirements by Operation

| Operation | Internal Path | External Path |
|-----------|--------------|---------------|
| Read | No consent | No consent |
| Create | No consent | Consent required |
| Update | No consent | Consent required |
| Rename | No consent | Consent required |
| Move | No consent | Consent required |
| Delete | Confirmation | Type-to-confirm |

### Type-to-Confirm Dialog

For destructive operations on external files, users must type a confirmation phrase:

```go
type ConsentRequest struct {
    OperationType   OperationType
    TargetPath      string
    FullPath        string          // Absolute path shown to user
    Classification  PathClassification
    ConfirmPhrase   string          // What user must type
    RequireFullPath bool            // Must type full path for dirs
}

type OperationType int

const (
    OpRename OperationType = iota
    OpMove
    OpDelete
    OpDeleteDirectory
)

func (cm *ConsentManager) RequireConsent(op OperationType, path string) (*ConsentRequest, error) {
    classification := cm.classifier.Classify(path)
    
    if !classification.IsExternal {
        return nil, nil // No consent needed for internal paths
    }
    
    request := &ConsentRequest{
        OperationType:  op,
        TargetPath:     path,
        FullPath:       classification.Path,
        Classification: classification,
    }
    
    switch op {
    case OpDelete:
        request.ConfirmPhrase = "DELETE"
        request.RequireFullPath = false
        
    case OpDeleteDirectory:
        // For directory deletion, require typing the full path
        request.ConfirmPhrase = classification.Path
        request.RequireFullPath = true
        
    case OpRename, OpMove:
        request.ConfirmPhrase = "CONFIRM"
        request.RequireFullPath = false
    }
    
    return request, nil
}
```

### Consent Validation

```go
func (cm *ConsentManager) ValidateConsent(request *ConsentRequest, userInput string) error {
    userInput = strings.TrimSpace(userInput)
    
    if request.RequireFullPath {
        // Must match the full path exactly
        if userInput != request.FullPath {
            return fmt.Errorf("ERR_CONSENT_INVALID: Path mismatch. Expected: %s", request.FullPath)
        }
    } else {
        // Must match the confirm phrase (case-insensitive for keywords)
        if !strings.EqualFold(userInput, request.ConfirmPhrase) {
            return fmt.Errorf("ERR_CONSENT_INVALID: Please type '%s' to confirm", request.ConfirmPhrase)
        }
    }
    
    return nil
}
```

---

## TypeScript Types

```typescript
enum DangerLevel {
  None = 0,
  Low = 1,
  Medium = 2,
  High = 3,
  Critical = 4,
}

enum OperationType {
  Rename = 0,
  Move = 1,
  Delete = 2,
  DeleteDirectory = 3,
}

interface PathClassification {
  readonly path: string;
  readonly isExternal: boolean;
  readonly dangerLevel: DangerLevel;
  readonly reason: string;
}

interface ConsentRequest {
  readonly operationType: OperationType;
  readonly targetPath: string;
  readonly fullPath: string;
  readonly classification: PathClassification;
  readonly confirmPhrase: string;
  readonly requireFullPath: boolean;
}

interface ConsentDialogProps {
  readonly request: ConsentRequest;
  readonly onConfirm: (userInput: string) => void;
  readonly onCancel: () => void;
}
```

---

## UI Component

### Consent Dialog

```tsx
import { useState } from 'react';
import { AlertTriangle, FileX2, FolderX, ArrowRightLeft, Pencil } from 'lucide-react';
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const OPERATION_LABELS: Record<OperationType, string> = {
  [OperationType.Rename]: 'RENAME',
  [OperationType.Move]: 'MOVE',
  [OperationType.Delete]: 'DELETE',
  [OperationType.DeleteDirectory]: 'DELETE DIRECTORY',
};

const OPERATION_ICONS: Record<OperationType, React.ComponentType> = {
  [OperationType.Rename]: Pencil,
  [OperationType.Move]: ArrowRightLeft,
  [OperationType.Delete]: FileX2,
  [OperationType.DeleteDirectory]: FolderX,
};

const DANGER_COLORS: Record<DangerLevel, string> = {
  [DangerLevel.None]: 'text-muted-foreground',
  [DangerLevel.Low]: 'text-yellow-600',
  [DangerLevel.Medium]: 'text-orange-500',
  [DangerLevel.High]: 'text-destructive',
  [DangerLevel.Critical]: 'text-destructive animate-pulse',
};

export function ExternalFileConsentDialog({
  request,
  onConfirm,
  onCancel,
}: ConsentDialogProps) {
  const [userInput, setUserInput] = useState('');
  const [error, setError] = useState<string | null>(null);
  
  const Icon = OPERATION_ICONS[request.operationType];
  const isValid = request.requireFullPath
    ? userInput === request.fullPath
    : userInput.toLowerCase() === request.confirmPhrase.toLowerCase();
  
  const handleConfirm = () => {
    if (!isValid) {
      setError(`Please type "${request.confirmPhrase}" to confirm`);
      return;
    }
    setError(null);
    onConfirm(userInput);
  };
  
  return (
    <AlertDialog open>
      <AlertDialogContent className="max-w-lg">
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-5 w-5" />
            External File Operation
          </AlertDialogTitle>
          
          <AlertDialogDescription className="space-y-4">
            <div className="flex items-center gap-2 text-lg font-medium">
              <Icon className="h-5 w-5" />
              You are about to {OPERATION_LABELS[request.operationType]}:
            </div>
            
            <div className="rounded-md bg-muted p-3 font-mono text-sm break-all">
              {request.fullPath}
            </div>
            
            <div className={cn(
              'flex items-center gap-2 font-medium',
              DANGER_COLORS[request.classification.dangerLevel]
            )}>
              <AlertTriangle className="h-4 w-4" />
              {request.classification.reason}
            </div>
            
            <div className="pt-2">
              <p className="mb-2 text-sm">
                To confirm, type: <code className="font-bold">{request.confirmPhrase}</code>
              </p>
              <Input
                value={userInput}
                onChange={(e) => {
                  setUserInput(e.target.value);
                  setError(null);
                }}
                placeholder={request.requireFullPath ? 'Type the full path' : `Type ${request.confirmPhrase}`}
                className={cn(error && 'border-destructive')}
                autoFocus
              />
              {error && (
                <p className="mt-1 text-sm text-destructive">{error}</p>
              )}
            </div>
          </AlertDialogDescription>
        </AlertDialogHeader>
        
        <AlertDialogFooter>
          <AlertDialogCancel onClick={onCancel}>Cancel</AlertDialogCancel>
          <Button
            variant="destructive"
            onClick={handleConfirm}
            disabled={!isValid}
          >
            Confirm {OPERATION_LABELS[request.operationType]}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
```

---

## Integration with AI Code Generation

When the AI Code Generation system (Feature 26) attempts operations on external files:

```go
func (ee *ExecutionEngine) ExecuteWithSafetyCheck(
    ctx context.Context,
    operation Operation,
) (*ExecutionResult, error) {
    
    // Check each target path
    for _, path := range operation.TargetPaths {
        classification := ee.pathClassifier.Classify(path)
        
        if classification.IsExternal {
            // Request consent through UI
            consent, err := ee.consentManager.RequestConsent(ctx, operation.Type, path)
            if err != nil {
                return nil, fmt.Errorf("consent required for external path: %s", path)
            }
            
            // Block until user provides consent
            if !consent.Granted {
                return nil, fmt.Errorf("operation cancelled: user denied consent for %s", path)
            }
            
            // Log the consent for audit
            ee.auditLogger.LogConsent(consent)
        }
    }
    
    // Proceed with operation
    return ee.execute(ctx, operation)
}
```

---

## Audit Logging

All external file operations are logged for audit:

```go
type ExternalOperationLog struct {
    ID            string         `json:"id"`
    Timestamp     time.Time      `json:"timestamp"`
    OperationType OperationType  `json:"operationType"`
    FullPath      string         `json:"fullPath"`
    DangerLevel   DangerLevel    `json:"dangerLevel"`
    ConsentGiven  bool           `json:"consentGiven"`
    ConsentPhrase string         `json:"consentPhrase"`
    UserID        string         `json:"userId"`
    AITaskID      string         `json:"aiTaskId,omitempty"`
    Result        string         `json:"result"`
    ErrorMessage  string         `json:"errorMessage,omitempty"`
}
```

---

## Acceptance Criteria

| ID | Criterion | Priority | Validation |
|----|-----------|----------|------------|
| EFS-01 | External paths trigger consent dialog | MUST | E2E test |
| EFS-02 | Delete requires typing "DELETE" | MUST | Unit test |
| EFS-03 | Directory delete requires typing full path | MUST | E2E test |
| EFS-04 | Rename/Move requires typing "CONFIRM" | MUST | Unit test |
| EFS-05 | Internal paths bypass consent | MUST | Unit test |
| EFS-06 | Danger level colors reflect severity | SHOULD | Visual test |
| EFS-07 | All external operations are audit logged | MUST | Integration test |
| EFS-08 | Cancel aborts operation | MUST | E2E test |
| EFS-09 | Invalid input shows clear error | SHOULD | E2E test |
| EFS-10 | System paths marked as Critical | MUST | Unit test |

---

## Related Specs

- [01-file-operations.md](./01-file-operations.md) — CRUD operations
- [06-trash-system.md](./06-trash-system.md) — Soft delete to trash
- [26-ai-code-generation/06-execution-engine.md](../26-ai-code-generation/06-execution-engine.md) — AI execution
- [07-history-system/01-git-integration.md](../07-history-system/01-git-integration.md) — Git commits for moves
