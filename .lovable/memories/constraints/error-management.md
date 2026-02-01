# Memory: constraints/error-management

**Updated:** 2026-01-30  
**Purpose:** Centralized error handling patterns and code registry

---

## Error Code Registry

**Total: 347 error codes** across 13 ranges.  
**Reference:** [Error Code Registry](../../../spec/spec-management-software/06-error-management/error-code-registry.md)

### Range Allocation

| Range | Category | Owner | Description |
|-------|----------|-------|-------------|
| 1xxx | Validation | Shared | Input validation, format errors |
| 2xxx | Authentication | Backend | Auth, tokens, sessions (Gateway) |
| 3xxx | Database | Backend | SQLite, queries, transactions (SpecManager) |
| 4xxx | External Services | Backend | Network, HTTP, third-party APIs (Chronicle) |
| 5xxx | Business Logic | Shared | Domain rules, state, processing (Scout) |
| 6xxx | File System/Git | Backend | Files, paths, Git operations (AI-Bridge) |
| 7xxx | LLM/Config/CLI | Backend | LLM server, models, config, brun CLI |
| 8xxx | RAG/Knowledge | Backend | RAG, embeddings, knowledge |
| 9xxx | System/Consistency | Backend | System errors, consistency checks |
| 10xxx | Context Window | Backend | Token budgeting, context assembly (Nexus-Flow) |
| 11xxx | Instructions | Backend | Instruction system, tasks (Voice-CLI) |
| 12xxx | Code Generation | Backend | AI code generation, Git, credits |
| 13xxx | Project Editor | Frontend | Input persistence, drafts, sync |

---

## Go Backend Patterns

### AppError Structure (CRITICAL)

Every application error must capture a full 40-frame stack trace:

```go
type AppError struct {
    Code       int           `json:"code"`
    Message    string        `json:"message"`
    Details    string        `json:"details,omitempty"`
    Stack      []StackFrame  `json:"stack,omitempty"`
    Retryable  bool          `json:"retryable"`
    Timestamp  time.Time     `json:"timestamp"`
}

func NewAppError(code int, message string) *AppError {
    return &AppError{
        Code:      code,
        Message:   message,
        Stack:     captureStack(40), // 40 frames mandatory
        Timestamp: time.Now().UTC(),
    }
}
```

### Logging Requirements

All logs must include function names and line numbers:

```go
logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
    AddSource: true, // MANDATORY
    Level:     slog.LevelInfo,
}))
```

### Stack Trace Capture

```go
func captureStack(depth int) []StackFrame {
    frames := make([]StackFrame, 0, depth)
    for i := 2; i < depth+2; i++ {
        pc, file, line, ok := runtime.Caller(i)
        if !ok {
            break
        }
        fn := runtime.FuncForPC(pc)
        frames = append(frames, StackFrame{
            Function: fn.Name(),
            File:     file,
            Line:     line,
        })
    }
    return frames
}
```

---

## TypeScript Frontend Patterns

### Error Code Enum Pattern

```typescript
export enum ProjectEditorErrorCode {
  // General (13000-13099)
  INIT_FAILED = 13000,
  MODULE_UNAVAILABLE = 13001,
  
  // Input Persistence (13100-13199)
  STORAGE_UNAVAILABLE = 13100,
  STORAGE_QUOTA_EXCEEDED = 13101,
  
  // Draft Recovery (13200-13299)
  RECOVERY_DETECTION_FAILED = 13200,
  RECOVERY_RESTORE_FAILED = 13201,
}
```

### Error Handling Hook

```typescript
interface UseErrorHandlerReturn {
  readonly handleError: (code: number, context?: string) => void;
  readonly lastError: AppError | null;
  readonly clearError: () => void;
}

export function useErrorHandler(): UseErrorHandlerReturn {
  const [lastError, setLastError] = useState<AppError | null>(null);
  
  const handleError = useCallback((code: number, context?: string) => {
    const error = createAppError(code, context);
    setLastError(error);
    logError(error);
  }, []);
  
  return { handleError, lastError, clearError: () => setLastError(null) };
}
```

### React Query Error Handling

```typescript
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: (failureCount, error) => {
        const appError = error as AppError;
        return appError.retryable && failureCount < 3;
      },
    },
    mutations: {
      onError: (error) => {
        const appError = error as AppError;
        toast.error(getErrorMessage(appError.code));
      },
    },
  },
});
```

---

## HTTP Status Code Mapping

| Error Range | HTTP Status | Meaning |
|-------------|-------------|---------|
| 1xxx | 400 | Bad Request (validation) |
| 2xxx | 401/403 | Unauthorized/Forbidden |
| 3xxx | 404/500/503 | Database errors |
| 4xxx | 502/503/504 | External service errors |
| 5xxx | 400/409/500 | Business logic errors |
| 6xxx | 403/404/500 | File system errors |
| 7xxx | 400/500/503 | LLM/Config errors |
| 13xxx | 400/500 | Frontend editor errors |

---

## Retryable Error Guidelines

| Category | Retryable | Example |
|----------|-----------|---------|
| Validation | ❌ No | Invalid email format |
| Auth expired | ✅ After refresh | Token expired |
| DB locked | ✅ Yes | SQLite busy |
| Network timeout | ✅ Yes | Request timed out |
| Rate limited | ✅ After delay | 429 response |
| Business conflict | ❌ No | Duplicate key |

---

## Cross-References

- [Error Code Registry](../../../spec/spec-management-software/06-error-management/error-code-registry.md)
- [Backend Error Codes](../../../spec/spec-management-software/06-error-management/backend/01-error-codes.md)
- [Frontend Error Codes](../../../spec/spec-management-software/06-error-management/frontend/01-error-codes.md)
- [Project Editor Errors](../../../spec/spec-management-software/05-features/28-project-editor/05-error-codes.md)
