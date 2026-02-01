# LLM Live Logging System

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

The LLM Live Logging system captures real-time output from LLM model processes (shell commands, stdout/stderr) and broadcasts them via WebSocket for frontend monitoring. This enables visibility into model execution for debugging and performance analysis.

**Cross-References:**
- [AI Integration](./01-ai-integration.md) - Model execution and slot management
- [Realtime](../18-realtime/00-overview.md) - WebSocket protocols

---

## 27.1 Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       LLM Live Logging Architecture                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────┐     ┌──────────────────┐     ┌─────────────────────────┐  │
│   │ LLM Process │────▶│ LogStreamManager │────▶│ WebSocket Broadcaster   │  │
│   │ (llama.cpp) │     │ (stdout/stderr)  │     │ (llm:log messages)      │  │
│   └─────────────┘     └──────────────────┘     └─────────────────────────┘  │
│         │                      │                          │                  │
│         │                      ▼                          ▼                  │
│         │              ┌──────────────┐           ┌─────────────────┐       │
│         │              │ Ring Buffer  │           │ Frontend UI     │       │
│         │              │ (in-memory)  │           │ Log Dashboard   │       │
│         │              └──────────────┘           └─────────────────┘       │
│         │                      │                                             │
│         ▼                      ▼                                             │
│   ┌─────────────┐     ┌──────────────┐                                      │
│   │ Shell Cmd   │     │ Persistent   │                                      │
│   │ Tracking    │     │ Log Storage  │                                      │
│   └─────────────┘     │ (optional)   │                                      │
│                       └──────────────┘                                      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 27.2 Log Entry Structure

### LogEntry Type

```go
// internal/models/llm_log_entry.go
package models

import "time"

type LLMLogLevel string

const (
    LLMLogLevelDebug   LLMLogLevel = "debug"
    LLMLogLevelInfo    LLMLogLevel = "info"
    LLMLogLevelWarning LLMLogLevel = "warning"
    LLMLogLevelError   LLMLogLevel = "error"
    LLMLogLevelStdout  LLMLogLevel = "stdout"
    LLMLogLevelStderr  LLMLogLevel = "stderr"
)

type LLMLogSource string

const (
    LLMLogSourceProcess  LLMLogSource = "process"   // stdout/stderr from LLM process
    LLMLogSourceShell    LLMLogSource = "shell"     // shell command execution
    LLMLogSourceSlotMgr  LLMLogSource = "slot_mgr"  // slot manager operations
    LLMLogSourceRequest  LLMLogSource = "request"   // API request/response logging
)

type LLMLogEntry struct {
    Id          string       `json:"id"`
    Timestamp   time.Time    `json:"timestamp"`
    Level       LLMLogLevel  `json:"level"`
    Source      LLMLogSource `json:"source"`
    ModelId     *string      `json:"modelId,omitempty"`      // Model being used
    ModelName   *string      `json:"modelName,omitempty"`    // Human-readable model name
    SlotIndex   *int         `json:"slotIndex,omitempty"`    // Which slot (port)
    RequestId   *string      `json:"requestId,omitempty"`    // Correlation ID
    Message     string       `json:"message"`
    Details     interface{}  `json:"details,omitempty"`      // Structured data
    
    // Shell command specific
    Command     *string      `json:"command,omitempty"`      // Shell command executed
    ExitCode    *int         `json:"exitCode,omitempty"`     // Exit code if completed
    DurationMs  *int64       `json:"durationMs,omitempty"`   // Execution time
    
    // Error specific
    ErrorCode   *string      `json:"errorCode,omitempty"`    // Error classification
    StackTrace  *string      `json:"stackTrace,omitempty"`   // Full stack trace
    FilePath    *string      `json:"filePath,omitempty"`     // Source file
    LineNumber  *int         `json:"lineNumber,omitempty"`   // Source line
}
```

### WebSocket Message Format

```go
// llm:log WebSocket message type
type LLMLogMessage struct {
    Type  string      `json:"type"`  // "llm:log"
    Entry LLMLogEntry `json:"entry"`
}

// llm:log_batch for bulk streaming
type LLMLogBatchMessage struct {
    Type    string        `json:"type"`    // "llm:log_batch"
    Entries []LLMLogEntry `json:"entries"`
}
```

---

## 27.3 LogStreamManager Service

### Core Service

```go
// internal/services/log_stream_manager.go
package services

import (
    "bufio"
    "context"
    "io"
    "os/exec"
    "sync"
    "time"
    
    "github.com/google/uuid"
)

type LogStreamManager struct {
    db              *sql.DB
    configService   *ConfigService
    wsHub           *WebSocketHub
    ringBuffer      *RingBuffer[LLMLogEntry]
    mutex           sync.RWMutex
    subscribers     map[string]chan LLMLogEntry
    
    // Configuration
    bufferSize      int
    streamEnabled   bool
    shellTracking   bool
    persistLogs     bool
}

func NewLogStreamManager(
    db *sql.DB,
    configService *ConfigService,
    wsHub *WebSocketHub,
) *LogStreamManager {
    // Load configuration
    bufferSize, _ := configService.GetConfigAsInt(context.Background(), "ai.logging.bufferSize")
    if bufferSize == 0 {
        bufferSize = 10000 // Default 10k entries
    }
    
    streamEnabled, _ := configService.GetConfigAsBool(context.Background(), "ai.logging.streamToWebSocket")
    shellTracking, _ := configService.GetConfigAsBool(context.Background(), "ai.logging.shellCommands")
    persistLogs, _ := configService.GetConfigAsBool(context.Background(), "ai.logging.persistToDatabase")
    
    return &LogStreamManager{
        db:            db,
        configService: configService,
        wsHub:         wsHub,
        ringBuffer:    NewRingBuffer[LLMLogEntry](bufferSize),
        subscribers:   make(map[string]chan LLMLogEntry),
        bufferSize:    bufferSize,
        streamEnabled: streamEnabled,
        shellTracking: shellTracking,
        persistLogs:   persistLogs,
    }
}

// Log adds an entry to the buffer and broadcasts
func (m *LogStreamManager) Log(entry LLMLogEntry) {
    // Ensure ID and timestamp
    if entry.Id == "" {
        entry.Id = uuid.NewString()
    }
    if entry.Timestamp.IsZero() {
        entry.Timestamp = time.Now().UTC()
    }
    
    // Add to ring buffer
    m.ringBuffer.Add(entry)
    
    // Persist if enabled
    if m.persistLogs {
        go m.persistEntry(entry)
    }
    
    // Broadcast via WebSocket
    if m.streamEnabled {
        m.broadcast(entry)
    }
    
    // Notify local subscribers
    m.notifySubscribers(entry)
}

// LogShellCommand logs a shell command execution
func (m *LogStreamManager) LogShellCommand(
    ctx context.Context,
    command string,
    modelId *string,
    slotIndex *int,
) func(exitCode int, err error) {
    if !m.shellTracking {
        return func(int, error) {}
    }
    
    startTime := time.Now()
    requestId := getRequestIdFromContext(ctx)
    
    m.Log(LLMLogEntry{
        Level:     LLMLogLevelInfo,
        Source:    LLMLogSourceShell,
        ModelId:   modelId,
        SlotIndex: slotIndex,
        RequestId: requestId,
        Message:   "Executing shell command",
        Command:   &command,
    })
    
    // Return completion callback
    return func(exitCode int, err error) {
        duration := time.Since(startTime).Milliseconds()
        level := LLMLogLevelInfo
        message := "Shell command completed"
        
        if err != nil {
            level = LLMLogLevelError
            message = "Shell command failed"
        }
        
        entry := LLMLogEntry{
            Level:      level,
            Source:     LLMLogSourceShell,
            ModelId:    modelId,
            SlotIndex:  slotIndex,
            RequestId:  requestId,
            Message:    message,
            Command:    &command,
            ExitCode:   &exitCode,
            DurationMs: &duration,
        }
        
        if err != nil {
            errStr := err.Error()
            entry.Details = map[string]interface{}{"error": errStr}
        }
        
        m.Log(entry)
    }
}

// CaptureProcessOutput captures stdout/stderr from an exec.Cmd
func (m *LogStreamManager) CaptureProcessOutput(
    ctx context.Context,
    cmd *exec.Cmd,
    modelId string,
    modelName string,
    slotIndex int,
) error {
    requestId := getRequestIdFromContext(ctx)
    
    // Capture stdout
    stdout, err := cmd.StdoutPipe()
    if err != nil {
        return err
    }
    
    // Capture stderr
    stderr, err := cmd.StderrPipe()
    if err != nil {
        return err
    }
    
    // Start the process
    if err := cmd.Start(); err != nil {
        return err
    }
    
    // Stream stdout
    go m.streamPipe(ctx, stdout, LLMLogLevelStdout, modelId, modelName, slotIndex, requestId)
    
    // Stream stderr
    go m.streamPipe(ctx, stderr, LLMLogLevelStderr, modelId, modelName, slotIndex, requestId)
    
    return nil
}

func (m *LogStreamManager) streamPipe(
    ctx context.Context,
    pipe io.ReadCloser,
    level LLMLogLevel,
    modelId string,
    modelName string,
    slotIndex int,
    requestId *string,
) {
    scanner := bufio.NewScanner(pipe)
    scanner.Buffer(make([]byte, 64*1024), 1024*1024) // 1MB max line
    
    for scanner.Scan() {
        select {
        case <-ctx.Done():
            return
        default:
            line := scanner.Text()
            if line == "" {
                continue
            }
            
            m.Log(LLMLogEntry{
                Level:     level,
                Source:    LLMLogSourceProcess,
                ModelId:   &modelId,
                ModelName: &modelName,
                SlotIndex: &slotIndex,
                RequestId: requestId,
                Message:   line,
            })
        }
    }
    
    if err := scanner.Err(); err != nil {
        m.Log(LLMLogEntry{
            Level:     LLMLogLevelError,
            Source:    LLMLogSourceProcess,
            ModelId:   &modelId,
            ModelName: &modelName,
            SlotIndex: &slotIndex,
            RequestId: requestId,
            Message:   "Error reading process output",
            Details:   map[string]interface{}{"error": err.Error()},
        })
    }
}

func (m *LogStreamManager) broadcast(entry LLMLogEntry) {
    msg := LLMLogMessage{
        Type:  "llm:log",
        Entry: entry,
    }
    m.wsHub.BroadcastToTopic("llm_logs", msg)
}

func (m *LogStreamManager) notifySubscribers(entry LLMLogEntry) {
    m.mutex.RLock()
    defer m.mutex.RUnlock()
    
    for _, ch := range m.subscribers {
        select {
        case ch <- entry:
        default:
            // Channel full, skip
        }
    }
}

// Subscribe creates a local subscription for log entries
func (m *LogStreamManager) Subscribe() (string, <-chan LLMLogEntry, func()) {
    m.mutex.Lock()
    defer m.mutex.Unlock()
    
    id := uuid.NewString()
    ch := make(chan LLMLogEntry, 100)
    m.subscribers[id] = ch
    
    unsubscribe := func() {
        m.mutex.Lock()
        defer m.mutex.Unlock()
        delete(m.subscribers, id)
        close(ch)
    }
    
    return id, ch, unsubscribe
}

// GetRecentLogs returns entries from the ring buffer
func (m *LogStreamManager) GetRecentLogs(limit int, filter *LogFilter) []LLMLogEntry {
    entries := m.ringBuffer.GetAll()
    
    if filter != nil {
        entries = m.applyFilter(entries, filter)
    }
    
    if limit > 0 && len(entries) > limit {
        entries = entries[len(entries)-limit:]
    }
    
    return entries
}

func (m *LogStreamManager) persistEntry(entry LLMLogEntry) {
    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()
    
    _, err := m.db.ExecContext(ctx, `
        INSERT INTO LLMLog (
            Id, Timestamp, Level, Source, ModelId, ModelName, SlotIndex,
            RequestId, Message, Details, Command, ExitCode, DurationMs,
            ErrorCode, StackTrace, FilePath, LineNumber, CreatedAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    `,
        entry.Id, entry.Timestamp, entry.Level, entry.Source,
        entry.ModelId, entry.ModelName, entry.SlotIndex,
        entry.RequestId, entry.Message, toJSON(entry.Details),
        entry.Command, entry.ExitCode, entry.DurationMs,
        entry.ErrorCode, entry.StackTrace, entry.FilePath, entry.LineNumber,
    )
    
    if err != nil {
        // Don't recurse - just write to stderr
        fmt.Fprintf(os.Stderr, "[LogStreamManager] Failed to persist log: %v\n", err)
    }
}
```

---

## 27.4 Ring Buffer Implementation

```go
// internal/utils/ring_buffer.go
package utils

import "sync"

type RingBuffer[T any] struct {
    buffer []T
    size   int
    head   int
    count  int
    mutex  sync.RWMutex
}

func NewRingBuffer[T any](size int) *RingBuffer[T] {
    return &RingBuffer[T]{
        buffer: make([]T, size),
        size:   size,
    }
}

func (rb *RingBuffer[T]) Add(item T) {
    rb.mutex.Lock()
    defer rb.mutex.Unlock()
    
    rb.buffer[rb.head] = item
    rb.head = (rb.head + 1) % rb.size
    
    if rb.count < rb.size {
        rb.count++
    }
}

func (rb *RingBuffer[T]) GetAll() []T {
    rb.mutex.RLock()
    defer rb.mutex.RUnlock()
    
    if rb.count == 0 {
        return nil
    }
    
    result := make([]T, rb.count)
    
    if rb.count < rb.size {
        // Buffer not full, start from 0
        copy(result, rb.buffer[:rb.count])
    } else {
        // Buffer full, wrap around
        start := rb.head
        for i := 0; i < rb.count; i++ {
            result[i] = rb.buffer[(start+i)%rb.size]
        }
    }
    
    return result
}

func (rb *RingBuffer[T]) GetLast(n int) []T {
    rb.mutex.RLock()
    defer rb.mutex.RUnlock()
    
    if n > rb.count {
        n = rb.count
    }
    
    if n == 0 {
        return nil
    }
    
    result := make([]T, n)
    start := (rb.head - n + rb.size) % rb.size
    
    for i := 0; i < n; i++ {
        result[i] = rb.buffer[(start+i)%rb.size]
    }
    
    return result
}

func (rb *RingBuffer[T]) Clear() {
    rb.mutex.Lock()
    defer rb.mutex.Unlock()
    
    rb.head = 0
    rb.count = 0
}

func (rb *RingBuffer[T]) Count() int {
    rb.mutex.RLock()
    defer rb.mutex.RUnlock()
    return rb.count
}
```

---

## 27.5 Log Filtering

```go
// internal/models/log_filter.go
package models

type LogFilter struct {
    Levels    []LLMLogLevel  `json:"levels,omitempty"`
    Sources   []LLMLogSource `json:"sources,omitempty"`
    ModelId   *string        `json:"modelId,omitempty"`
    SlotIndex *int           `json:"slotIndex,omitempty"`
    RequestId *string        `json:"requestId,omitempty"`
    Search    *string        `json:"search,omitempty"`
    Since     *time.Time     `json:"since,omitempty"`
    Until     *time.Time     `json:"until,omitempty"`
}

// ApplyFilter filters log entries based on criteria
func (m *LogStreamManager) applyFilter(entries []LLMLogEntry, filter *LogFilter) []LLMLogEntry {
    result := make([]LLMLogEntry, 0, len(entries))
    
    for _, entry := range entries {
        if !m.matchesFilter(entry, filter) {
            continue
        }
        result = append(result, entry)
    }
    
    return result
}

func (m *LogStreamManager) matchesFilter(entry LLMLogEntry, filter *LogFilter) bool {
    // Filter by levels
    if len(filter.Levels) > 0 {
        found := false
        for _, level := range filter.Levels {
            if entry.Level == level {
                found = true
                break
            }
        }
        if !found {
            return false
        }
    }
    
    // Filter by sources
    if len(filter.Sources) > 0 {
        found := false
        for _, source := range filter.Sources {
            if entry.Source == source {
                found = true
                break
            }
        }
        if !found {
            return false
        }
    }
    
    // Filter by model
    if filter.ModelId != nil && (entry.ModelId == nil || *entry.ModelId != *filter.ModelId) {
        return false
    }
    
    // Filter by slot
    if filter.SlotIndex != nil && (entry.SlotIndex == nil || *entry.SlotIndex != *filter.SlotIndex) {
        return false
    }
    
    // Filter by request
    if filter.RequestId != nil && (entry.RequestId == nil || *entry.RequestId != *filter.RequestId) {
        return false
    }
    
    // Filter by time range
    if filter.Since != nil && entry.Timestamp.Before(*filter.Since) {
        return false
    }
    if filter.Until != nil && entry.Timestamp.After(*filter.Until) {
        return false
    }
    
    // Filter by search term
    if filter.Search != nil && *filter.Search != "" {
        searchLower := strings.ToLower(*filter.Search)
        if !strings.Contains(strings.ToLower(entry.Message), searchLower) {
            if entry.Command == nil || !strings.Contains(strings.ToLower(*entry.Command), searchLower) {
                return false
            }
        }
    }
    
    return true
}
```

---

## 27.6 Database Schema

```sql
-- LLM Log table for persistence (optional)
CREATE TABLE IF NOT EXISTS LLMLog (
    Id TEXT PRIMARY KEY,
    Timestamp DATETIME NOT NULL,
    Level TEXT NOT NULL CHECK(Level IN ('debug', 'info', 'warning', 'error', 'stdout', 'stderr')),
    Source TEXT NOT NULL CHECK(Source IN ('process', 'shell', 'slot_mgr', 'request')),
    ModelId TEXT,
    ModelName TEXT,
    SlotIndex INTEGER,
    RequestId TEXT,
    Message TEXT NOT NULL,
    Details TEXT,          -- JSON
    Command TEXT,
    ExitCode INTEGER,
    DurationMs INTEGER,
    ErrorCode TEXT,
    StackTrace TEXT,
    FilePath TEXT,
    LineNumber INTEGER,
    CreatedAt DATETIME DEFAULT (datetime('now')),
    
    FOREIGN KEY (ModelId) REFERENCES ModelRegistry(Id) ON DELETE SET NULL
);

-- Indexes for efficient querying
CREATE INDEX idx_llmlog_timestamp ON LLMLog(Timestamp DESC);
CREATE INDEX idx_llmlog_level ON LLMLog(Level);
CREATE INDEX idx_llmlog_source ON LLMLog(Source);
CREATE INDEX idx_llmlog_model ON LLMLog(ModelId);
CREATE INDEX idx_llmlog_request ON LLMLog(RequestId);

-- Error-only view for quick access
CREATE VIEW LLMLogErrors AS
SELECT * FROM LLMLog WHERE Level IN ('error', 'stderr') ORDER BY Timestamp DESC;
```

---

## 27.7 REST API Endpoints

### Get Recent Logs

```
GET /api/llm/logs
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `limit` | int | Max entries to return (default: 100) |
| `levels` | string | Comma-separated levels to include |
| `sources` | string | Comma-separated sources to include |
| `modelId` | string | Filter by model ID |
| `slotIndex` | int | Filter by slot index |
| `requestId` | string | Filter by request ID |
| `search` | string | Text search in message/command |
| `since` | string | ISO timestamp, logs after this time |
| `until` | string | ISO timestamp, logs before this time |

**Response:**
```json
{
  "success": true,
  "data": {
    "entries": [
      {
        "id": "log_abc123",
        "timestamp": "2026-01-28T10:30:00Z",
        "level": "stdout",
        "source": "process",
        "modelId": "model_xyz",
        "modelName": "llama-3-8b",
        "slotIndex": 0,
        "message": "Loaded model with 8192 context"
      }
    ],
    "total": 150,
    "hasMore": true
  }
}
```

### Stream Logs via WebSocket

Clients subscribe to the `llm_logs` topic to receive real-time log entries.

```typescript
// Subscribe to LLM logs
wsManager.send('subscribe', { topic: 'llm_logs' });

// Receive log entries
wsManager.on('llm:log', (entry: LLMLogEntry) => {
    console.log(`[${entry.level}] ${entry.message}`);
});

// Optional: Subscribe with filter
wsManager.send('subscribe', { 
    topic: 'llm_logs',
    filter: {
        levels: ['error', 'stderr'],
        modelId: 'model_xyz'
    }
});
```

### Clear Log Buffer

```
POST /api/llm/logs/clear
```

**Response:**
```json
{
  "success": true,
  "message": "Log buffer cleared"
}
```

---

## 27.8 SlotManager Integration

Update the SlotManager to use LogStreamManager for process output:

```go
// internal/services/slot_manager.go (updated)

func (sm *SlotManager) startModel(ctx context.Context, slot *ModelSlot, model *ModelInfo) error {
    config, _ := sm.configService.GetLLaMAConfig(ctx)
    
    cmd := exec.CommandContext(ctx,
        config.ServerPath,
        "--model", model.ModelPath,
        "--port", fmt.Sprintf("%d", slot.Port),
        "--host", config.BindAddress,
        "--ctx-size", fmt.Sprintf("%d", config.ContextSize),
        "--n-gpu-layers", fmt.Sprintf("%d", config.GPULayers),
    )
    
    // Log the shell command
    commandStr := strings.Join(cmd.Args, " ")
    done := sm.logManager.LogShellCommand(ctx, commandStr, &model.Id, &slot.SlotIndex)
    
    // Capture and stream process output
    if err := sm.logManager.CaptureProcessOutput(ctx, cmd, model.Id, model.DisplayName, slot.SlotIndex); err != nil {
        done(1, err)
        return fmt.Errorf("failed to capture process output: %w", err)
    }
    
    // Wait for process (in background)
    go func() {
        err := cmd.Wait()
        exitCode := 0
        if err != nil {
            if exitErr, ok := err.(*exec.ExitError); ok {
                exitCode = exitErr.ExitCode()
            } else {
                exitCode = 1
            }
        }
        done(exitCode, err)
    }()
    
    // Wait for server to be ready
    if err := sm.waitForReady(ctx, slot.Port); err != nil {
        return err
    }
    
    sm.processes[slot.SlotIndex] = cmd
    return nil
}
```

---

## 27.9 Configuration Keys

All configuration keys use dot.notation format (see [Seeding Configuration](./09-seeding-configuration.md)):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `ai.logging.shellCommands` | bool | `true` | Track shell command execution |
| `ai.logging.streamToWebSocket` | bool | `true` | Broadcast logs via WebSocket |
| `ai.logging.persistToDatabase` | bool | `false` | Persist logs to database |
| `ai.logging.bufferSize` | int | `10000` | Ring buffer capacity |
| `ai.logging.retentionHours` | int | `168` | DB log retention (hours) |

---

## 27.10 Error Logging Integration

Enhanced error entries capture complete context for debugging:

```go
// LogError creates a comprehensive error log entry
func (m *LogStreamManager) LogError(
    ctx context.Context,
    err error,
    message string,
    modelId *string,
    slotIndex *int,
) {
    requestId := getRequestIdFromContext(ctx)
    
    // Capture stack trace
    stackBuf := make([]byte, 4096)
    n := runtime.Stack(stackBuf, false)
    stackTrace := string(stackBuf[:n])
    
    // Get caller info
    _, file, line, ok := runtime.Caller(1)
    var filePath *string
    var lineNumber *int
    if ok {
        filePath = &file
        lineNumber = &line
    }
    
    // Classify error
    errorCode := classifyError(err)
    
    entry := LLMLogEntry{
        Level:      LLMLogLevelError,
        Source:     LLMLogSourceProcess,
        ModelId:    modelId,
        SlotIndex:  slotIndex,
        RequestId:  requestId,
        Message:    message,
        ErrorCode:  &errorCode,
        StackTrace: &stackTrace,
        FilePath:   filePath,
        LineNumber: lineNumber,
        Details:    map[string]interface{}{"error": err.Error()},
    }
    
    m.Log(entry)
}

func classifyError(err error) string {
    errMsg := err.Error()
    
    switch {
    case strings.Contains(errMsg, "connection refused"):
        return "ERR_LLM_CONNECTION"
    case strings.Contains(errMsg, "out of memory"):
        return "ERR_LLM_OOM"
    case strings.Contains(errMsg, "context canceled"):
        return "ERR_LLM_CANCELED"
    case strings.Contains(errMsg, "timeout"):
        return "ERR_LLM_TIMEOUT"
    case strings.Contains(errMsg, "model not found"):
        return "ERR_LLM_MODEL_NOT_FOUND"
    default:
        return "ERR_LLM_UNKNOWN"
    }
}
```

---

## 27.11 Testing

```go
// internal/services/log_stream_manager_test.go
package services_test

func TestLogStreamManager_Log(t *testing.T) {
    manager := setupTestLogManager()
    
    entry := LLMLogEntry{
        Level:   LLMLogLevelInfo,
        Source:  LLMLogSourceProcess,
        Message: "Test log entry",
    }
    
    manager.Log(entry)
    
    logs := manager.GetRecentLogs(10, nil)
    assert.Len(t, logs, 1)
    assert.Equal(t, "Test log entry", logs[0].Message)
    assert.NotEmpty(t, logs[0].Id)
    assert.False(t, logs[0].Timestamp.IsZero())
}

func TestLogStreamManager_Filter(t *testing.T) {
    manager := setupTestLogManager()
    
    // Add mixed entries
    manager.Log(LLMLogEntry{Level: LLMLogLevelInfo, Source: LLMLogSourceProcess, Message: "Info 1"})
    manager.Log(LLMLogEntry{Level: LLMLogLevelError, Source: LLMLogSourceProcess, Message: "Error 1"})
    manager.Log(LLMLogEntry{Level: LLMLogLevelInfo, Source: LLMLogSourceShell, Message: "Info 2"})
    
    // Filter by level
    filter := &LogFilter{Levels: []LLMLogLevel{LLMLogLevelError}}
    logs := manager.GetRecentLogs(10, filter)
    assert.Len(t, logs, 1)
    assert.Equal(t, "Error 1", logs[0].Message)
    
    // Filter by source
    filter = &LogFilter{Sources: []LLMLogSource{LLMLogSourceShell}}
    logs = manager.GetRecentLogs(10, filter)
    assert.Len(t, logs, 1)
    assert.Equal(t, "Info 2", logs[0].Message)
}

func TestLogStreamManager_RingBuffer(t *testing.T) {
    // Create manager with small buffer
    manager := &LogStreamManager{
        ringBuffer: NewRingBuffer[LLMLogEntry](3),
    }
    
    // Add 5 entries
    for i := 0; i < 5; i++ {
        manager.Log(LLMLogEntry{Message: fmt.Sprintf("Entry %d", i)})
    }
    
    // Should only have last 3
    logs := manager.GetRecentLogs(10, nil)
    assert.Len(t, logs, 3)
    assert.Equal(t, "Entry 2", logs[0].Message)
    assert.Equal(t, "Entry 4", logs[2].Message)
}

func TestLogStreamManager_ShellCommandTracking(t *testing.T) {
    manager := setupTestLogManager()
    manager.shellTracking = true
    
    ctx := context.Background()
    done := manager.LogShellCommand(ctx, "llama-server --model test.gguf", nil, nil)
    
    // Simulate completion
    done(0, nil)
    
    logs := manager.GetRecentLogs(10, nil)
    assert.Len(t, logs, 2) // Start + complete
    
    // Check completion entry
    assert.Equal(t, LLMLogSourceShell, logs[1].Source)
    assert.NotNil(t, logs[1].ExitCode)
    assert.Equal(t, 0, *logs[1].ExitCode)
}
```

---

## 27.12 Multi-Server Log Aggregation

With the multi-server LLM architecture (Ollama + llama.cpp), logs must be aggregated from multiple sources.

### Extended Log Entry Structure

```go
type LLMLogEntry struct {
    // ... existing fields ...
    
    // Multi-server fields
    ServerId   *string `json:"serverId,omitempty"`   // Server identifier from config
    ServerType *string `json:"serverType,omitempty"` // "ollama", "llama", "llama-swap"
    ServerPort *int    `json:"serverPort,omitempty"` // Port the server is running on
}
```

### Multi-Server Log Aggregation

```go
// internal/services/multi_server_log_manager.go
package services

type MultiServerLogManager struct {
    logManager     *LogStreamManager
    serverRegistry *ServerRegistry
}

func NewMultiServerLogManager(
    logManager *LogStreamManager,
    serverRegistry *ServerRegistry,
) *MultiServerLogManager {
    return &MultiServerLogManager{
        logManager:     logManager,
        serverRegistry: serverRegistry,
    }
}

// LogFromServer adds server context to log entries
func (m *MultiServerLogManager) LogFromServer(
    serverId string,
    entry LLMLogEntry,
) {
    server, exists := m.serverRegistry.GetServer(serverId)
    if exists {
        entry.ServerId = &serverId
        serverType := string(server.Config.Type)
        entry.ServerType = &serverType
        entry.ServerPort = &server.Config.Port
    }
    
    m.logManager.Log(entry)
}

// GetLogsByServer filters logs by server ID
func (m *MultiServerLogManager) GetLogsByServer(
    serverId string,
    limit int,
) []LLMLogEntry {
    filter := &LogFilter{
        ServerId: &serverId,
    }
    return m.logManager.GetRecentLogs(limit, filter)
}

// GetServerLogStats returns log statistics per server
func (m *MultiServerLogManager) GetServerLogStats() map[string]LogStats {
    stats := make(map[string]LogStats)
    
    logs := m.logManager.GetRecentLogs(0, nil) // Get all
    
    for _, entry := range logs {
        serverId := "unknown"
        if entry.ServerId != nil {
            serverId = *entry.ServerId
        }
        
        s, exists := stats[serverId]
        if !exists {
            s = LogStats{}
        }
        
        s.Total++
        switch entry.Level {
        case LLMLogLevelError, LLMLogLevelStderr:
            s.Errors++
        case LLMLogLevelWarning:
            s.Warnings++
        case LLMLogLevelInfo, LLMLogLevelStdout:
            s.Info++
        }
        
        stats[serverId] = s
    }
    
    return stats
}

type LogStats struct {
    Total    int `json:"total"`
    Errors   int `json:"errors"`
    Warnings int `json:"warnings"`
    Info     int `json:"info"`
}
```

### Extended Log Filter

```go
type LogFilter struct {
    // ... existing fields ...
    
    // Multi-server filters
    ServerId   *string   `json:"serverId,omitempty"`
    ServerType *string   `json:"serverType,omitempty"` // "ollama", "llama", "llama-swap"
    ServerIds  []string  `json:"serverIds,omitempty"`  // Multiple servers
}

func (m *LogStreamManager) matchesFilter(entry LLMLogEntry, filter *LogFilter) bool {
    // ... existing filter logic ...
    
    // Filter by server ID
    if filter.ServerId != nil && 
       (entry.ServerId == nil || *entry.ServerId != *filter.ServerId) {
        return false
    }
    
    // Filter by server type
    if filter.ServerType != nil && 
       (entry.ServerType == nil || *entry.ServerType != *filter.ServerType) {
        return false
    }
    
    // Filter by multiple server IDs
    if len(filter.ServerIds) > 0 {
        found := false
        for _, id := range filter.ServerIds {
            if entry.ServerId != nil && *entry.ServerId == id {
                found = true
                break
            }
        }
        if !found {
            return false
        }
    }
    
    return true
}
```

### WebSocket Message Types for Multi-Server

```go
// Server-specific log message
type ServerLogMessage struct {
    Type     string      `json:"type"`     // "llm:server_log"
    ServerId string      `json:"serverId"`
    Entry    LLMLogEntry `json:"entry"`
}

// Aggregated stats message
type LogStatsMessage struct {
    Type       string              `json:"type"` // "llm:log_stats"
    ByServer   map[string]LogStats `json:"byServer"`
    TotalLogs  int                 `json:"totalLogs"`
    ErrorCount int                 `json:"errorCount"`
}
```

### API Endpoints for Multi-Server Logs

```
GET /api/llm/logs?serverId={id}
GET /api/llm/logs?serverType=ollama
GET /api/llm/servers/{serverId}/logs
GET /api/llm/logs/stats
```

---

## 27.13 Cross-Reference Updates

This specification integrates with:

1. **AI Integration** (`08-ai-integration.md`): LogStreamManager captures model process output
2. **Seeding Configuration** (`09-seeding-configuration.md`): Configuration keys for logging behavior
3. **LLM Server Management** (`28-llm-server-management.md`): ServerRegistry for multi-server context
4. **Real-Time Communication** (`23-realtime-communication.md`): WebSocket `llm:log` message type
5. **Database Schema** (`02-database-schema.md`): LLMLog table for persistence
6. **Error Management** (`../general-spec/02-systems/01-logging-system-systems.md`): Follows dual-file strategy

---

## 27.14 Acceptance Criteria

### Ring Buffer & Storage (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| RB-001 | RingBuffer maintains exactly `bufferSize` entries (default 10,000) | Critical | Unit test |
| RB-002 | Oldest entries evicted when buffer full (FIFO) | Critical | Unit test |
| RB-003 | GetAll() returns entries in chronological order | Critical | Unit test |
| RB-004 | GetLast(n) returns most recent n entries | High | Unit test |
| RB-005 | Concurrent Add/GetAll operations are thread-safe | Critical | Concurrency test |
| RB-006 | Buffer operations complete in O(1) time | High | Benchmark test |
| RB-007 | persistLogs=true writes entries to LLMLog table | High | Integration test |
| RB-008 | Database persistence is async (non-blocking) | High | Performance test |
| RB-009 | Persistence failures logged to stderr (no recursion) | Critical | Error test |

### Process Output Capture (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PO-001 | CaptureProcessOutput captures stdout as LLMLogLevelStdout | Critical | Integration test |
| PO-002 | CaptureProcessOutput captures stderr as LLMLogLevelStderr | Critical | Integration test |
| PO-003 | Lines up to 1MB captured without truncation | High | Unit test |
| PO-004 | Empty lines filtered from output | Medium | Unit test |
| PO-005 | Scanner errors logged with LLMLogLevelError | High | Error test |
| PO-006 | Context cancellation stops pipe streaming | Critical | Cancellation test |
| PO-007 | ModelId, ModelName, SlotIndex attached to all entries | High | Schema test |
| PO-008 | RequestId correlation across related entries | High | Integration test |

### Shell Command Tracking (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SC-001 | LogShellCommand logs start entry with command text | High | Unit test |
| SC-002 | Completion callback logs exitCode and duration | High | Unit test |
| SC-003 | Failed commands logged with LLMLogLevelError | Critical | Error test |
| SC-004 | shellTracking=false disables all shell logging | High | Config test |
| SC-005 | Duration measured from start to completion callback | High | Timing test |
| SC-006 | Error details captured in entry.Details field | High | Schema test |

### WebSocket Broadcasting (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| WS-001 | streamEnabled=true broadcasts via llm:log message type | Critical | WebSocket test |
| WS-002 | streamEnabled=false suppresses all broadcasting | High | Config test |
| WS-003 | llm:log_batch batches multiple entries efficiently | High | Performance test |
| WS-004 | BroadcastToTopic("llm_logs") targets correct subscribers | Critical | Integration test |
| WS-005 | Broadcast failures do not block Log() method | Critical | Error test |
| WS-006 | Server-specific logs use llm:server_log message type | High | WebSocket test |
| WS-007 | LogStatsMessage aggregates by server ID | High | Integration test |

### Local Subscriptions (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| LS-001 | Subscribe() returns unique subscription ID | High | Unit test |
| LS-002 | Subscriber channel receives all new entries | Critical | Integration test |
| LS-003 | Unsubscribe callback removes subscriber and closes channel | Critical | Resource test |
| LS-004 | Full subscriber channels skip entries (non-blocking) | High | Backpressure test |
| LS-005 | Multiple simultaneous subscribers supported | High | Concurrency test |

### Filtering & Retrieval (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| FR-001 | Filter by Level (debug, info, warning, error, stdout, stderr) | High | Unit test |
| FR-002 | Filter by Source (process, shell, slot_mgr, request) | High | Unit test |
| FR-003 | Filter by ModelId returns only matching entries | High | Unit test |
| FR-004 | Filter by SlotIndex returns only matching entries | High | Unit test |
| FR-005 | Filter by ServerId for multi-server environments | High | Unit test |
| FR-006 | Filter by ServerType (ollama, llama, llama-swap) | High | Unit test |
| FR-007 | Multiple ServerIds filter returns union of matches | High | Unit test |
| FR-008 | Limit parameter caps returned entries | High | Unit test |
| FR-009 | Filters combinable (AND logic) | High | Integration test |

### API Endpoints (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| API-001 | GET /api/llm/logs returns recent entries | Critical | API test |
| API-002 | GET /api/llm/logs?level=error filters by level | High | API test |
| API-003 | GET /api/llm/logs?modelId={id} filters by model | High | API test |
| API-004 | GET /api/llm/logs?serverId={id} filters by server | High | API test |
| API-005 | GET /api/llm/servers/{id}/logs returns server-specific | High | API test |
| API-006 | GET /api/llm/logs/stats returns LogStats by server | High | API test |
| API-007 | Query parameters validate against LogFilter schema | High | Validation test |

### Error Classification (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EC-001 | Connection refused → ERR_LLM_CONNECTION | Critical | Error mapping test |
| EC-002 | Out of memory → ERR_LLM_OOM | Critical | Error mapping test |
| EC-003 | Context canceled → ERR_LLM_CANCELED | Critical | Error mapping test |
| EC-004 | Timeout → ERR_LLM_TIMEOUT | Critical | Error mapping test |
| EC-005 | Model not found → ERR_LLM_MODEL_NOT_FOUND | Critical | Error mapping test |
| EC-006 | Unknown errors → ERR_LLM_UNKNOWN | High | Error mapping test |

### Frontend Dashboard (UI Requirements)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| UI-001 | Live log dashboard displays streaming entries | Critical | E2E test |
| UI-002 | Model-specific tabs filter by modelId | High | E2E test |
| UI-003 | Server-specific tabs filter by serverId | High | E2E test |
| UI-004 | Level filter toggles (error/warning/info) functional | High | E2E test |
| UI-005 | Search/filter by message content | Medium | E2E test |
| UI-006 | Pause/resume streaming controls | High | E2E test |
| UI-007 | Auto-scroll to latest entry (toggleable) | Medium | E2E test |
| UI-008 | Entry expansion shows full details/stack trace | High | E2E test |
| UI-009 | Copy entry to clipboard | Medium | E2E test |
| UI-010 | Export logs as JSON/CSV | Low | E2E test |

### Configuration (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CF-001 | ai.logging.bufferSize configures ring buffer size | High | Config test |
| CF-002 | ai.logging.streamToWebSocket enables/disables broadcast | High | Config test |
| CF-003 | ai.logging.shellCommands enables/disables shell tracking | High | Config test |
| CF-004 | ai.logging.persistToDatabase enables/disables persistence | High | Config test |
| CF-005 | Configuration changes apply without restart | Medium | Hot-reload test |

### Performance (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PF-001 | Log() completes in < 1ms under normal load | Critical | Benchmark test |
| PF-002 | 1000 entries/second sustained without backpressure | High | Load test |
| PF-003 | Memory usage stable under continuous logging | Critical | Memory test |
| PF-004 | WebSocket broadcast adds < 5ms latency | High | Latency test |
| PF-005 | GetRecentLogs(1000) completes in < 10ms | High | Benchmark test |
