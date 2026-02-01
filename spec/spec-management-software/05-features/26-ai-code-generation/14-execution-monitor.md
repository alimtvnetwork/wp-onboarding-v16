# 14. Execution Monitor

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [Overview](./00-overview.md)

---

## Purpose

Define the real-time execution monitoring interface that displays progress, output, and status during Golang code execution.

---

## UI Layout

### Execution Monitor Panel

```
┌─────────────────────────────────────────────────────────────────┐
│  ⚡ Executing: lowercase-filenames                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Status: Running ●                                               │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│  Progress: 8/12 files (67%)                                      │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  📝 Live Output                                    [Auto-scroll] │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ [10:30:01] Starting task: lowercase-filenames               ││
│  │ [10:30:01] Target directory: ./spec                         ││
│  │ [10:30:01] Files to process: 12                             ││
│  │ [10:30:01] ✓ Renamed: README.md → readme.md                 ││
│  │ [10:30:01] ✓ Renamed: CHANGELOG.md → changelog.md           ││
│  │ [10:30:02] ✓ Renamed: 00-Overview.md → 00-overview.md       ││
│  │ [10:30:02] ✓ Renamed: 01-Auth.md → 01-auth.md               ││
│  │ [10:30:02] ✓ Renamed: 02-Files.md → 02-files.md             ││
│  │ [10:30:02] ✓ Renamed: 03-Project.md → 03-project.md         ││
│  │ [10:30:02] ✓ Renamed: 04-Editor.md → 04-editor.md           ││
│  │ [10:30:02] ✓ Renamed: 05-Voice.md → 05-voice.md             ││
│  │ [10:30:03] Processing: 06-AI.md...                          ││
│  │ █                                                           ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  ⏱️ Elapsed: 00:02  │  📁 Completed: 8  │  ⚠️ Errors: 0         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  [Cancel Execution]                                              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Completed State

```
┌─────────────────────────────────────────────────────────────────┐
│  ✅ Execution Complete: lowercase-filenames                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Status: Success ✓                                               │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│  Completed: 12/12 files (100%)                                   │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  📊 Summary                                                      │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Total files processed: 12                                   ││
│  │ Successful operations: 12                                   ││
│  │ Failed operations: 0                                        ││
│  │ Total duration: 3.2 seconds                                 ││
│  │                                                              ││
│  │ Operations by type:                                          ││
│  │   • Rename: 12                                              ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  📝 Output Log                               [Copy] [Download]   │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ [10:30:01] Starting task: lowercase-filenames               ││
│  │ ...                                                         ││
│  │ [10:30:04] ✓ Task completed successfully                    ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  [View History]                                    [Close]       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Error State

```
┌─────────────────────────────────────────────────────────────────┐
│  ❌ Execution Failed: lowercase-filenames                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Status: Failed ✗                                                │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│  Completed: 5/12 files (42%)                                     │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  ❌ Error Details                                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Error: permission denied: ./spec/protected/file.md          ││
│  │                                                              ││
│  │ Stack trace:                                                 ││
│  │   main.processFile() at main.go:45                          ││
│  │   main.run() at main.go:32                                  ││
│  │   main.main() at main.go:18                                 ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  [Retry]        [Edit Code]        [View History]      [Close]   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Components

### Monitor Component

```tsx
interface ExecutionMonitorProps {
  readonly taskId: string;
  readonly onComplete: (result: ExecutionResult) => void;
  readonly onCancel: () => void;
}

export function ExecutionMonitor({
  taskId,
  onComplete,
  onCancel,
}: ExecutionMonitorProps): JSX.Element {
  const [status, setStatus] = useState<ExecutionStatus>(ExecutionStatus.Running);
  const [progress, setProgress] = useState<ExecutionProgress>({
    current: 0,
    total: 0,
    percentage: 0,
  });
  const [logs, setLogs] = useState<LogLine[]>([]);
  const [result, setResult] = useState<ExecutionResult | null>(null);
  const logEndRef = useRef<HTMLDivElement>(null);
  const [autoScroll, setAutoScroll] = useState(true);

  // Subscribe to execution updates
  useEffect(() => {
    const eventSource = new EventSource(
      `/api/v1/code-generation/execute/${taskId}/stream`
    );

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      
      switch (data.type) {
        case "progress":
          setProgress(data.progress);
          break;
        case "log":
          setLogs((prev) => [...prev, data.log]);
          break;
        case "complete":
          setStatus(ExecutionStatus.Success);
          setResult(data.result);
          onComplete(data.result);
          eventSource.close();
          break;
        case "error":
          setStatus(ExecutionStatus.Failed);
          setResult(data.result);
          eventSource.close();
          break;
        default:
          break;
      }
    };

    eventSource.onerror = () => {
      setStatus(ExecutionStatus.Failed);
      eventSource.close();
    };

    return () => eventSource.close();
  }, [taskId, onComplete]);

  // Auto-scroll logs
  useEffect(() => {
    if (autoScroll && logEndRef.current) {
      logEndRef.current.scrollIntoView({ behavior: "smooth" });
    }
  }, [logs, autoScroll]);

  return (
    <div className="space-y-4">
      {/* Status header */}
      <ExecutionHeader status={status} taskId={taskId} />
      
      {/* Progress bar */}
      <ProgressBar progress={progress} status={status} />
      
      {/* Live output */}
      <LogViewer
        logs={logs}
        autoScroll={autoScroll}
        onToggleAutoScroll={() => setAutoScroll(!autoScroll)}
        logEndRef={logEndRef}
      />
      
      {/* Summary (when complete) */}
      {result && <ExecutionSummary result={result} />}
      
      {/* Actions */}
      <ExecutionActions
        status={status}
        onCancel={onCancel}
        onRetry={() => {/* retry logic */}}
      />
    </div>
  );
}
```

### Progress Bar Component

```tsx
interface ProgressBarProps {
  readonly progress: ExecutionProgress;
  readonly status: ExecutionStatus;
}

export function ProgressBar({ progress, status }: ProgressBarProps): JSX.Element {
  const getStatusColor = (): string => {
    switch (status) {
      case ExecutionStatus.Running:
        return "bg-blue-500";
      case ExecutionStatus.Success:
        return "bg-green-500";
      case ExecutionStatus.Failed:
        return "bg-red-500";
      default:
        return "bg-gray-500";
    }
  };

  return (
    <div className="space-y-2">
      <div className="flex justify-between text-sm">
        <span>
          Progress: {progress.current}/{progress.total} files
        </span>
        <span>{progress.percentage}%</span>
      </div>
      <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
        <div
          className={`h-full transition-all duration-300 ${getStatusColor()}`}
          style={{ width: `${progress.percentage}%` }}
        />
      </div>
    </div>
  );
}
```

### Log Viewer Component

```tsx
interface LogViewerProps {
  readonly logs: readonly LogLine[];
  readonly autoScroll: boolean;
  readonly onToggleAutoScroll: () => void;
  readonly logEndRef: React.RefObject<HTMLDivElement>;
}

interface LogLine {
  readonly timestamp: Date;
  readonly level: "info" | "success" | "warning" | "error";
  readonly message: string;
}

export function LogViewer({
  logs,
  autoScroll,
  onToggleAutoScroll,
  logEndRef,
}: LogViewerProps): JSX.Element {
  const getLogIcon = (level: LogLine["level"]): JSX.Element => {
    switch (level) {
      case "success":
        return <Check className="h-4 w-4 text-green-500" />;
      case "warning":
        return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
      case "error":
        return <XCircle className="h-4 w-4 text-red-500" />;
      default:
        return <Info className="h-4 w-4 text-blue-500" />;
    }
  };

  return (
    <div className="border rounded-lg">
      <div className="flex justify-between items-center p-2 border-b bg-muted">
        <span className="text-sm font-medium">Live Output</span>
        <Button
          variant="ghost"
          size="sm"
          onClick={onToggleAutoScroll}
          className={autoScroll ? "text-primary" : "text-muted-foreground"}
        >
          Auto-scroll {autoScroll ? "ON" : "OFF"}
        </Button>
      </div>
      <ScrollArea className="h-64">
        <div className="p-2 font-mono text-sm space-y-1">
          {logs.map((log, i) => (
            <div key={i} className="flex items-start gap-2">
              <span className="text-muted-foreground whitespace-nowrap">
                [{format(log.timestamp, "HH:mm:ss")}]
              </span>
              {getLogIcon(log.level)}
              <span>{log.message}</span>
            </div>
          ))}
          <div ref={logEndRef} />
        </div>
      </ScrollArea>
    </div>
  );
}
```

---

## Types

```typescript
enum ExecutionStatus {
  Pending = "pending",
  Running = "running",
  Success = "success",
  Failed = "failed",
  Cancelled = "cancelled",
}

interface ExecutionProgress {
  readonly current: number;
  readonly total: number;
  readonly percentage: number;
}

interface LogLine {
  readonly timestamp: Date;
  readonly level: "info" | "success" | "warning" | "error";
  readonly message: string;
}

interface StreamEvent {
  readonly type: "progress" | "log" | "complete" | "error";
  readonly progress?: ExecutionProgress;
  readonly log?: LogLine;
  readonly result?: ExecutionResult;
}
```

---

## Real-time Updates

### Server-Sent Events (SSE)

```go
func (h *Handler) StreamExecution(w http.ResponseWriter, r *http.Request) {
    taskId := chi.URLParam(r, "taskId")
    
    // Set SSE headers
    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")
    w.Header().Set("Connection", "keep-alive")
    
    flusher, ok := w.(http.Flusher)
    if !ok {
        http.Error(w, "SSE not supported", http.StatusInternalServerError)
        return
    }
    
    // Subscribe to execution events
    events := h.executor.Subscribe(taskId)
    defer h.executor.Unsubscribe(taskId)
    
    for {
        select {
        case event := <-events:
            data, _ := json.Marshal(event)
            fmt.Fprintf(w, "data: %s\n\n", data)
            flusher.Flush()
            
            if event.Type == "complete" || event.Type == "error" {
                return
            }
            
        case <-r.Context().Done():
            return
        }
    }
}
```

---

## Related Specs

- [06-execution-engine.md](./06-execution-engine.md) — Backend execution
- [07-approval-workflow.md](./07-approval-workflow.md) — Triggers execution
- [13-code-review-ui.md](./13-code-review-ui.md) — Pre-execution review
