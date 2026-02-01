# Error Handling System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Comprehensive error handling system for frontend applications with localStorage persistence, error modal dialogs with copy functionality, and structured error logging for debugging. Handles API failures, WebSocket disconnections, and application errors with user-friendly feedback.

**Cross-References:**
- [Settings System](./23-settings-system.md) - Error logs viewer
- [Error Code Registry](../../04-coding-guidelines/01-error-codes.md) - Error code standards
- [WebSocket Protocol](../14-realtime/01-websocket-protocol.md) - Connection errors
- [API Client](../15-api-client/00-overview.md) - API error handling

---

## Error Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           ERROR HANDLING SYSTEM                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐      │
│  │  API Error   │   │  WebSocket   │   │   Runtime    │   │  Validation  │      │
│  │   Handler    │   │    Error     │   │    Error     │   │    Error     │      │
│  └──────┬───────┘   └──────┬───────┘   └──────┬───────┘   └──────┬───────┘      │
│         │                  │                   │                  │              │
│         └──────────────────┴─────────┬─────────┴──────────────────┘              │
│                                      │                                           │
│                                      ▼                                           │
│                        ┌──────────────────────────┐                             │
│                        │     ErrorManager         │                             │
│                        │   - Capture errors       │                             │
│                        │   - Classify severity    │                             │
│                        │   - Store to localStorage│                             │
│                        │   - Trigger notifications│                             │
│                        └────────────┬─────────────┘                             │
│                                     │                                            │
│                    ┌────────────────┼────────────────┐                          │
│                    │                │                │                          │
│                    ▼                ▼                ▼                          │
│         ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                   │
│         │ localStorage │  │  Error Modal │  │    Toast     │                   │
│         │   Storage    │  │   (Copy)     │  │ Notification │                   │
│         └──────────────┘  └──────────────┘  └──────────────┘                   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Error Types

```typescript
type ErrorSeverity = 'info' | 'warning' | 'error' | 'critical';

type ErrorCategory = 
  | 'api'           // API request failures
  | 'websocket'     // WebSocket connection issues
  | 'validation'    // Input/form validation
  | 'runtime'       // JavaScript runtime errors
  | 'network'       // Network connectivity
  | 'auth'          // Authentication/authorization
  | 'storage'       // localStorage/file system
  | 'unknown';      // Uncategorized

interface AppError {
  id: string;                    // Unique error ID (UUID)
  timestamp: Date;               // When error occurred
  
  // Classification
  code: number;                  // Error code from registry
  category: ErrorCategory;
  severity: ErrorSeverity;
  
  // Content
  message: string;               // User-friendly message
  technicalMessage?: string;     // Developer details
  stack?: string;                // Stack trace if available
  
  // Context
  url?: string;                  // URL where error occurred
  endpoint?: string;             // API endpoint if applicable
  httpStatus?: number;           // HTTP status code
  requestId?: string;            // Backend request ID
  
  // User context
  userId?: string;
  sessionId?: string;
  
  // Additional data
  metadata?: Record<string, any>;
  
  // State
  isRead: boolean;               // User has seen this
  isResolved: boolean;           // Error no longer active
}
```

---

## Error Manager Service

```typescript
// Maximum errors to keep in localStorage
const MAX_STORED_ERRORS = 100;
const STORAGE_KEY = 'app_error_log';

class ErrorManager {
  private errors: AppError[] = [];
  private listeners: Set<ErrorListener> = new Set();
  
  constructor() {
    this.loadFromStorage();
    this.setupGlobalHandlers();
  }
  
  // Global error handlers
  private setupGlobalHandlers() {
    // Unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
      this.captureError({
        category: 'runtime',
        severity: 'error',
        message: 'Unhandled Promise Rejection',
        technicalMessage: event.reason?.message || String(event.reason),
        stack: event.reason?.stack,
      });
    });
    
    // Global JavaScript errors
    window.addEventListener('error', (event) => {
      this.captureError({
        category: 'runtime',
        severity: 'error',
        message: 'JavaScript Error',
        technicalMessage: event.message,
        stack: event.error?.stack,
        metadata: {
          filename: event.filename,
          lineno: event.lineno,
          colno: event.colno,
        },
      });
    });
  }
  
  // Capture and store error
  captureError(input: CaptureErrorInput): AppError {
    const error: AppError = {
      id: crypto.randomUUID(),
      timestamp: new Date(),
      code: input.code || this.inferErrorCode(input),
      category: input.category,
      severity: input.severity,
      message: input.message,
      technicalMessage: input.technicalMessage,
      stack: input.stack,
      url: window.location.href,
      endpoint: input.endpoint,
      httpStatus: input.httpStatus,
      requestId: input.requestId,
      userId: this.getCurrentUserId(),
      sessionId: this.getSessionId(),
      metadata: input.metadata,
      isRead: false,
      isResolved: false,
    };
    
    // Add to in-memory store
    this.errors.unshift(error);
    
    // Trim if over limit
    if (this.errors.length > MAX_STORED_ERRORS) {
      this.errors = this.errors.slice(0, MAX_STORED_ERRORS);
    }
    
    // Persist to localStorage
    this.saveToStorage();
    
    // Notify listeners
    this.notifyListeners(error);
    
    // Show notification based on severity
    this.showNotification(error);
    
    return error;
  }
  
  // API error helper
  captureApiError(response: Response, body?: any): AppError {
    return this.captureError({
      category: 'api',
      severity: response.status >= 500 ? 'error' : 'warning',
      code: body?.error?.code,
      message: body?.error?.message || `API Error: ${response.status}`,
      technicalMessage: JSON.stringify(body, null, 2),
      httpStatus: response.status,
      endpoint: response.url,
      requestId: response.headers.get('X-Request-ID') || undefined,
    });
  }
  
  // WebSocket error helper
  captureWebSocketError(event: CloseEvent | Event, context?: string): AppError {
    const isCloseEvent = 'code' in event;
    return this.captureError({
      category: 'websocket',
      severity: 'error',
      code: isCloseEvent ? 14000 + (event as CloseEvent).code : 14001,
      message: context || 'WebSocket connection error',
      technicalMessage: isCloseEvent 
        ? `Close code: ${(event as CloseEvent).code}, reason: ${(event as CloseEvent).reason}`
        : 'Connection failed',
      metadata: {
        closeCode: isCloseEvent ? (event as CloseEvent).code : undefined,
        closeReason: isCloseEvent ? (event as CloseEvent).reason : undefined,
      },
    });
  }
  
  // Get all errors
  getErrors(options?: GetErrorsOptions): AppError[] {
    let result = [...this.errors];
    
    if (options?.category) {
      result = result.filter(e => e.category === options.category);
    }
    if (options?.severity) {
      result = result.filter(e => e.severity === options.severity);
    }
    if (options?.unreadOnly) {
      result = result.filter(e => !e.isRead);
    }
    
    return result;
  }
  
  // Get unread count (for badge)
  getUnreadCount(): number {
    return this.errors.filter(e => !e.isRead && e.severity !== 'info').length;
  }
  
  // Mark error as read
  markAsRead(errorId: string) {
    const error = this.errors.find(e => e.id === errorId);
    if (error) {
      error.isRead = true;
      this.saveToStorage();
    }
  }
  
  // Mark all as read
  markAllAsRead() {
    this.errors.forEach(e => e.isRead = true);
    this.saveToStorage();
  }
  
  // Clear all errors
  clearAll() {
    this.errors = [];
    this.saveToStorage();
  }
  
  // Generate copyable error report
  generateErrorReport(error: AppError): string {
    return `
═══════════════════════════════════════════════════════════════
ERROR REPORT
═══════════════════════════════════════════════════════════════

ID:        ${error.id}
Timestamp: ${error.timestamp.toISOString()}
Category:  ${error.category}
Severity:  ${error.severity}
Code:      ${error.code}

MESSAGE:
${error.message}

TECHNICAL DETAILS:
${error.technicalMessage || 'N/A'}

STACK TRACE:
${error.stack || 'N/A'}

CONTEXT:
- URL: ${error.url || 'N/A'}
- Endpoint: ${error.endpoint || 'N/A'}
- HTTP Status: ${error.httpStatus || 'N/A'}
- Request ID: ${error.requestId || 'N/A'}

METADATA:
${error.metadata ? JSON.stringify(error.metadata, null, 2) : 'N/A'}

═══════════════════════════════════════════════════════════════
Generated at: ${new Date().toISOString()}
═══════════════════════════════════════════════════════════════
`.trim();
  }
  
  // Storage operations
  private loadFromStorage() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        this.errors = parsed.map((e: any) => ({
          ...e,
          timestamp: new Date(e.timestamp),
        }));
      }
    } catch {
      console.warn('Failed to load error log from localStorage');
      this.errors = [];
    }
  }
  
  private saveToStorage() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.errors));
    } catch {
      console.warn('Failed to save error log to localStorage');
    }
  }
  
  // Notification logic
  private showNotification(error: AppError) {
    switch (error.severity) {
      case 'critical':
        // Show blocking modal for critical errors
        this.showErrorModal(error);
        break;
      case 'error':
        // Show toast notification
        toast.error(error.message, {
          action: {
            label: 'Details',
            onClick: () => this.showErrorModal(error),
          },
        });
        break;
      case 'warning':
        toast.warning(error.message);
        break;
      case 'info':
        // Usually silent, but can show toast
        break;
    }
  }
}

// Singleton instance
export const errorManager = new ErrorManager();
```

---

## Error Modal Component

### Modal Design

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                           [×]   │
│                                                                                  │
│           ⚠️  Connection Error                                                   │
│                                                                                  │
│  Unable to connect to the WebSocket server. Real-time updates are disabled.    │
│                                                                                  │
│  ─────────────────────────────────────────────────────────────────────────────  │
│                                                                                  │
│  ▼ Technical Details                                                            │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │ Error Code: 14001                                                           ││
│  │ Category: websocket                                                          ││
│  │ Timestamp: 2026-01-29T10:30:45.123Z                                         ││
│  │                                                                              ││
│  │ Close code: 1006                                                             ││
│  │ Reason: Abnormal closure                                                     ││
│  │                                                                              ││
│  │ Stack trace:                                                                 ││
│  │   at WebSocket.onclose (websocket.ts:45)                                    ││
│  │   at handleError (error-manager.ts:123)                                     ││
│  │   ...                                                                        ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
│            [Copy Error Report]              [Retry Connection]    [Dismiss]     │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Modal Component

```typescript
interface ErrorModalProps {
  error: AppError | null;
  isOpen: boolean;
  onClose: () => void;
  onRetry?: () => void;
}

const ErrorModal: React.FC<ErrorModalProps> = ({
  error,
  isOpen,
  onClose,
  onRetry,
}) => {
  const [showDetails, setShowDetails] = useState(false);
  const [copied, setCopied] = useState(false);
  
  const handleCopy = async () => {
    if (!error) return;
    
    const report = errorManager.generateErrorReport(error);
    await navigator.clipboard.writeText(report);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
    toast.success('Error report copied to clipboard');
  };
  
  const severityIcon = {
    info: <Info className="h-12 w-12 text-blue-500" />,
    warning: <AlertTriangle className="h-12 w-12 text-yellow-500" />,
    error: <XCircle className="h-12 w-12 text-red-500" />,
    critical: <AlertOctagon className="h-12 w-12 text-red-600" />,
  };
  
  if (!error) return null;
  
  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <div className="flex items-center gap-4">
            {severityIcon[error.severity]}
            <div>
              <DialogTitle>{getCategoryLabel(error.category)} Error</DialogTitle>
              <DialogDescription className="text-base mt-1">
                {error.message}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>
        
        <Collapsible open={showDetails} onOpenChange={setShowDetails}>
          <CollapsibleTrigger className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
            {showDetails ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
            Technical Details
          </CollapsibleTrigger>
          
          <CollapsibleContent>
            <div className="mt-4 rounded-lg bg-muted p-4 font-mono text-xs overflow-auto max-h-[300px]">
              <div className="space-y-2">
                <div><span className="text-muted-foreground">Error Code:</span> {error.code}</div>
                <div><span className="text-muted-foreground">Category:</span> {error.category}</div>
                <div><span className="text-muted-foreground">Timestamp:</span> {error.timestamp.toISOString()}</div>
                {error.httpStatus && (
                  <div><span className="text-muted-foreground">HTTP Status:</span> {error.httpStatus}</div>
                )}
                {error.requestId && (
                  <div><span className="text-muted-foreground">Request ID:</span> {error.requestId}</div>
                )}
                {error.technicalMessage && (
                  <>
                    <div className="text-muted-foreground mt-4">Technical Message:</div>
                    <pre className="whitespace-pre-wrap">{error.technicalMessage}</pre>
                  </>
                )}
                {error.stack && (
                  <>
                    <div className="text-muted-foreground mt-4">Stack Trace:</div>
                    <pre className="whitespace-pre-wrap">{error.stack}</pre>
                  </>
                )}
              </div>
            </div>
          </CollapsibleContent>
        </Collapsible>
        
        <DialogFooter className="gap-2 sm:gap-0">
          <Button
            variant="outline"
            onClick={handleCopy}
            className="flex items-center gap-2"
          >
            {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
            {copied ? 'Copied!' : 'Copy Error Report'}
          </Button>
          
          {onRetry && (
            <Button variant="secondary" onClick={onRetry}>
              <RotateCcw className="h-4 w-4 mr-2" />
              Retry
            </Button>
          )}
          
          <Button onClick={onClose}>Dismiss</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
```

---

## Error Logs Page

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  Error Logs                        [Clear All] [Export] [Mark All Read]         │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Filter: [All ▼]  Category: [All ▼]  Severity: [All ▼]  [Show unread only ☐]   │
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │                                                                              ││
│  │  ● 10:30:45  WebSocket  Error    Connection closed unexpectedly       [▶]  ││
│  │  ● 10:28:12  API        Warning  Rate limit warning (80%)             [▶]  ││
│  │  ○ 10:15:00  API        Error    Failed to save file: timeout         [▶]  ││
│  │  ○ 09:45:30  Runtime    Error    Uncaught TypeError                   [▶]  ││
│  │  ○ 09:30:00  Validation Warning  Invalid JSON in config               [▶]  ││
│  │                                                                              ││
│  │  ● = Unread   ○ = Read                                                      ││
│  │                                                                              ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
│  Showing 5 of 23 errors                                           [Load More]   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## LocalStorage Schema

```typescript
// Key: 'app_error_log'
interface StoredErrorLog {
  version: number;          // Schema version for migrations
  errors: StoredError[];
}

interface StoredError {
  id: string;
  timestamp: string;        // ISO date string
  code: number;
  category: string;
  severity: string;
  message: string;
  technicalMessage?: string;
  stack?: string;
  url?: string;
  endpoint?: string;
  httpStatus?: number;
  requestId?: string;
  userId?: string;
  sessionId?: string;
  metadata?: Record<string, any>;
  isRead: boolean;
  isResolved: boolean;
}

// Storage limit management
const STORAGE_LIMITS = {
  maxErrors: 100,
  maxErrorAge: 7 * 24 * 60 * 60 * 1000,  // 7 days
  maxStorageSize: 1024 * 1024,            // 1MB
};
```

---

## React Hooks

```typescript
// useErrorManager hook
function useErrorManager() {
  const [errors, setErrors] = useState<AppError[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  
  useEffect(() => {
    const update = () => {
      setErrors(errorManager.getErrors());
      setUnreadCount(errorManager.getUnreadCount());
    };
    
    errorManager.subscribe(update);
    update();
    
    return () => errorManager.unsubscribe(update);
  }, []);
  
  return {
    errors,
    unreadCount,
    captureError: errorManager.captureError.bind(errorManager),
    clearAll: errorManager.clearAll.bind(errorManager),
    markAsRead: errorManager.markAsRead.bind(errorManager),
    markAllAsRead: errorManager.markAllAsRead.bind(errorManager),
    generateReport: errorManager.generateErrorReport.bind(errorManager),
  };
}

// useErrorModal hook
function useErrorModal() {
  const [error, setError] = useState<AppError | null>(null);
  const [isOpen, setIsOpen] = useState(false);
  
  const showError = useCallback((e: AppError) => {
    setError(e);
    setIsOpen(true);
  }, []);
  
  const hideError = useCallback(() => {
    setIsOpen(false);
    setTimeout(() => setError(null), 200); // Wait for animation
  }, []);
  
  return { error, isOpen, showError, hideError };
}
```

---

## Error Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| 1xxx | Validation | Input validation errors |
| 2xxx | Auth | Authentication/authorization |
| 3xxx | Database | Database operations |
| 4xxx | External | Third-party services |
| 5xxx | Business | Business logic errors |
| 6xxx | File System | File/Git operations |
| 7xxx | CLI | Build runner CLI |
| 8xxx | RAG | Search/security |
| 9xxx | System | Internal system errors |
| 11xxx | Instruction | Instruction system |
| 12xxx | Code Gen | Code generation |
| 14xxx | Realtime | WebSocket/SSE |

---

## Related Specifications

- [Settings System](./23-settings-system.md)
- [Error Code Registry](../../04-coding-guidelines/01-error-codes.md)
- [API Client](../15-api-client/00-overview.md)
- [WebSocket Protocol](../14-realtime/01-websocket-protocol.md)
