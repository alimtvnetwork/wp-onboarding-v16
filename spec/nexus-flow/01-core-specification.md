# Nexus-Flow Specification

**Service:** Nexus-Flow (Orchestration Engine)  
**Port:** 8085  
**CLI:** `nexus-flow`  
**Phase:** 7  
**Status:** Draft  
**Last Updated:** 2026-01-30

---

## 1. Overview

Nexus-Flow is the standalone orchestration engine for automation pipelines. It provides a CLI interface, WebSocket server for real-time execution, and a block-based execution architecture supporting 7 stage types with conditional branching and concurrency control.

### 1.1 Core Capabilities

| Capability | Description |
|------------|-------------|
| Pipeline Orchestration | DAG-based workflow execution |
| Block Execution | 7 stage types with isolated execution |
| WebSocket Server | Real-time bidirectional communication |
| CLI Interface | Command-line pipeline management |
| Visual Canvas | React Flow node-based editor integration |
| RES Integration | Fault-tolerant execution via Resilient Execution System |
| Concurrency Control | Throttled parallel execution |

### 1.2 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Nexus-Flow Service                          │
│                            (:8085)                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌───────────────┐  ┌───────────────┐  ┌───────────────────────┐   │
│  │   CLI Layer   │  │  HTTP API     │  │  WebSocket Server     │   │
│  │  nexus-flow   │  │  /api/v1/*    │  │  /ws/pipeline         │   │
│  └───────┬───────┘  └───────┬───────┘  └───────────┬───────────┘   │
│          │                  │                      │               │
│          ▼                  ▼                      ▼               │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    Pipeline Engine                           │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐    │   │
│  │  │ Scheduler │  │ Executor │  │ RES      │  │ State    │    │   │
│  │  │          │  │          │  │ Bridge   │  │ Manager  │    │   │
│  │  └──────────┘  └──────────┘  └──────────┘  └──────────┘    │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                              │                                     │
│  ┌───────────────────────────┴─────────────────────────────────┐   │
│  │                    Block Registry                            │   │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌──────────┐ ┌─────────┐  │   │
│  │  │ Prompt │ │ Search │ │CodeGen │ │Validation│ │Transform│  │   │
│  │  └────────┘ └────────┘ └────────┘ └──────────┘ └─────────┘  │   │
│  │  ┌────────┐ ┌────────┐                                       │   │
│  │  │  HTTP  │ │ FileOp │                                       │   │
│  │  └────────┘ └────────┘                                       │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
         │                    │                    │
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│   AI-Bridge     │  │     Scout       │  │   SpecManager   │
│    (:8082)      │  │    (:8084)      │  │    (:8081)      │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

---

## 2. Directory Structure

```
cmd/nexus-flow/
├── main.go                    # CLI entry point
├── cli/
│   ├── root.go                # Root command
│   ├── run.go                 # Pipeline execution
│   ├── serve.go               # WebSocket server
│   ├── validate.go            # Pipeline validation
│   ├── list.go                # List pipelines
│   └── export.go              # Export pipeline

internal/
├── engine/
│   ├── pipeline.go            # Pipeline execution engine
│   ├── scheduler.go           # DAG-based task scheduler
│   ├── executor.go            # Block execution coordinator
│   └── state.go               # Execution state management
├── block/
│   ├── registry.go            # Block type registry
│   ├── interface.go           # Block interface definition
│   ├── prompt.go              # Prompt block
│   ├── search.go              # Search block
│   ├── codegen.go             # CodeGen block
│   ├── validation.go          # Validation block
│   ├── transform.go           # Transform block
│   ├── http.go                # HTTP block
│   └── fileop.go              # FileOp block
├── control/
│   ├── branch.go              # Conditional branching
│   ├── loop.go                # Loop with concurrency throttle
│   └── parallel.go            # Parallel execution group
├── websocket/
│   ├── server.go              # WebSocket server
│   ├── handler.go             # Message handlers
│   ├── protocol.go            # Protocol definitions
│   └── session.go             # Session management
├── res/
│   ├── bridge.go              # RES integration bridge
│   ├── checkpoint.go          # Checkpoint management
│   └── recovery.go            # Error recovery strategies
├── handler/
│   ├── pipeline.go            # Pipeline HTTP handlers
│   ├── execution.go           # Execution HTTP handlers
│   └── health.go              # Health check handlers
├── repository/
│   ├── pipeline_repo.go       # Pipeline persistence
│   ├── execution_repo.go      # Execution history
│   └── checkpoint_repo.go     # Checkpoint storage
└── model/
    ├── pipeline.go            # Pipeline domain model
    ├── block.go               # Block domain model
    ├── execution.go           # Execution domain model
    └── message.go             # WebSocket message types

migrations/
└── nexus-flow/
    ├── 001_create_pipelines.sql
    ├── 002_create_executions.sql
    ├── 003_create_checkpoints.sql
    └── 004_create_telemetry.sql
```

---

## 3. CLI Design

### 3.1 Command Structure

```
nexus-flow
├── serve                      # Start WebSocket server
│   ├── --port, -p             # Server port (default: 8085)
│   ├── --host                 # Bind address (default: 0.0.0.0)
│   └── --config, -c           # Config file path
│
├── run                        # Execute pipeline
│   ├── <pipeline-id>          # Pipeline ID or file path
│   ├── --project, -p          # Project ID context
│   ├── --input, -i            # Input JSON/file
│   ├── --output, -o           # Output file path
│   ├── --async                # Run asynchronously
│   ├── --timeout              # Execution timeout
│   └── --dry-run              # Validate without execution
│
├── validate                   # Validate pipeline definition
│   ├── <pipeline-id|file>     # Pipeline to validate
│   └── --strict               # Strict validation mode
│
├── list                       # List pipelines
│   ├── --project, -p          # Filter by project
│   ├── --status               # Filter by status
│   └── --format               # Output format (table|json)
│
├── export                     # Export pipeline
│   ├── <pipeline-id>          # Pipeline to export
│   ├── --output, -o           # Output file
│   └── --format               # Export format (json|yaml)
│
├── import                     # Import pipeline
│   ├── <file>                 # Pipeline file to import
│   └── --project, -p          # Target project
│
├── history                    # Execution history
│   ├── <pipeline-id>          # Pipeline ID
│   ├── --limit, -n            # Number of entries
│   └── --status               # Filter by status
│
└── version                    # Show version info
```

### 3.2 CLI Implementation

```go
// cmd/nexus-flow/cli/root.go
package cli

import (
    "fmt"
    "os"
    "runtime"
    
    "github.com/spf13/cobra"
    "github.com/spf13/viper"
    
    "pkg/logging"
)

var (
    cfgFile string
    logger  *logging.Logger
)

// rootCmd represents the base command
var rootCmd = &cobra.Command{
    Use:   "nexus-flow",
    Short: "Nexus-Flow orchestration engine",
    Long: `Nexus-Flow is a standalone orchestration engine for automation pipelines.
    
It provides DAG-based workflow execution with 7 block types:
  - Prompt:     AI prompt execution
  - Search:     RAG-powered search
  - CodeGen:    Code generation
  - Validation: Output validation
  - Transform:  Data transformation
  - HTTP:       External API calls
  - FileOp:     File system operations`,
    PersistentPreRun: func(cmd *cobra.Command, args []string) {
        _, file, line, _ := runtime.Caller(0)
        logger.Debug("Command starting",
            "file", file,
            "line", line,
            "command", cmd.Name(),
            "args", args,
        )
    },
}

// Execute runs the CLI
func Execute() {
    _, file, line, _ := runtime.Caller(0)
    
    if err := rootCmd.Execute(); err != nil {
        logger.Error("Command failed",
            "file", file,
            "line", line,
            "error", err,
        )
        os.Exit(1)
    }
}

func init() {
    cobra.OnInitialize(initConfig)
    
    rootCmd.PersistentFlags().StringVar(&cfgFile, "config", "", "config file")
    rootCmd.PersistentFlags().String("log-level", "info", "log level")
    rootCmd.PersistentFlags().Bool("json-logs", false, "output logs as JSON")
    
    viper.BindPFlag("log.level", rootCmd.PersistentFlags().Lookup("log-level"))
    viper.BindPFlag("log.json", rootCmd.PersistentFlags().Lookup("json-logs"))
}

func initConfig() {
    _, file, line, _ := runtime.Caller(0)
    
    if cfgFile != "" {
        viper.SetConfigFile(cfgFile)
    } else {
        viper.SetConfigName("nexus-flow")
        viper.SetConfigType("yaml")
        viper.AddConfigPath(".")
        viper.AddConfigPath("./config")
        viper.AddConfigPath("/etc/nexus-flow")
    }
    
    viper.AutomaticEnv()
    viper.SetEnvPrefix("NEXUS")
    
    if err := viper.ReadInConfig(); err != nil {
        if _, ok := err.(viper.ConfigFileNotFoundError); !ok {
            fmt.Printf("[%s:%d] Error reading config: %v\n", file, line, err)
        }
    }
    
    // Initialize logger with AddSource: true
    logConfig := logging.Config{
        Level:     viper.GetString("log.level"),
        Format:    "json",
        AddSource: true, // MANDATORY: Include function names and line numbers
    }
    logger = logging.NewLogger(logConfig)
}
```

### 3.3 Run Command

```go
// cmd/nexus-flow/cli/run.go
package cli

import (
    "context"
    "encoding/json"
    "os"
    "runtime"
    "time"
    
    "github.com/spf13/cobra"
    
    "nexus-flow/internal/engine"
    "nexus-flow/internal/model"
    "pkg/errors"
    "pkg/types"
)

var runCmd = &cobra.Command{
    Use:   "run <pipeline-id>",
    Short: "Execute a pipeline",
    Args:  cobra.ExactArgs(1),
    RunE:  runPipeline,
}

func init() {
    rootCmd.AddCommand(runCmd)
    
    runCmd.Flags().StringP("project", "p", "", "Project ID context")
    runCmd.Flags().StringP("input", "i", "", "Input JSON or file path")
    runCmd.Flags().StringP("output", "o", "", "Output file path")
    runCmd.Flags().Bool("async", false, "Run asynchronously")
    runCmd.Flags().Duration("timeout", 30*time.Minute, "Execution timeout")
    runCmd.Flags().Bool("dry-run", false, "Validate without execution")
}

func runPipeline(cmd *cobra.Command, args []string) error {
    _, file, line, _ := runtime.Caller(0)
    
    pipelineID := args[0]
    projectID, _ := cmd.Flags().GetString("project")
    inputStr, _ := cmd.Flags().GetString("input")
    outputPath, _ := cmd.Flags().GetString("output")
    async, _ := cmd.Flags().GetBool("async")
    timeout, _ := cmd.Flags().GetDuration("timeout")
    dryRun, _ := cmd.Flags().GetBool("dry-run")
    
    logger.Info("Running pipeline",
        "file", file,
        "line", line,
        "pipelineId", pipelineID,
        "projectId", projectID,
        "async", async,
        "dryRun", dryRun,
    )
    
    // Parse input
    var input map[string]interface{}
    if inputStr != "" {
        if err := parseInput(inputStr, &input); err != nil {
            return errors.Wrap(err, errors.CodeValidationError,
                "Failed to parse input",
                "file", file,
                "line", line,
            )
        }
    }
    
    // Create execution context
    ctx, cancel := context.WithTimeout(context.Background(), timeout)
    defer cancel()
    
    // Initialize engine
    eng, err := engine.New(engine.Config{
        ProjectID: types.ProjectID(projectID),
    })
    if err != nil {
        return errors.Wrap(err, errors.CodeInternalError,
            "Failed to initialize engine",
            "file", file,
            "line", line,
        )
    }
    
    // Load pipeline
    pipeline, err := eng.LoadPipeline(ctx, pipelineID)
    if err != nil {
        return err
    }
    
    // Dry run - validate only
    if dryRun {
        if err := eng.ValidatePipeline(ctx, pipeline); err != nil {
            return err
        }
        logger.Info("Pipeline validation successful",
            "file", file,
            "line", line,
            "pipelineId", pipelineID,
        )
        return nil
    }
    
    // Execute pipeline
    execReq := model.ExecutionRequest{
        PipelineID: types.PipelineID(pipelineID),
        ProjectID:  types.ProjectID(projectID),
        Input:      input,
        Async:      async,
    }
    
    result, err := eng.Execute(ctx, execReq)
    if err != nil {
        return err
    }
    
    // Handle output
    if outputPath != "" {
        if err := writeOutput(outputPath, result); err != nil {
            return err
        }
    } else {
        // Print to stdout
        output, _ := json.MarshalIndent(result, "", "  ")
        fmt.Println(string(output))
    }
    
    return nil
}

func parseInput(input string, target *map[string]interface{}) error {
    // Try as file path first
    if _, err := os.Stat(input); err == nil {
        data, err := os.ReadFile(input)
        if err != nil {
            return err
        }
        return json.Unmarshal(data, target)
    }
    
    // Try as JSON string
    return json.Unmarshal([]byte(input), target)
}

func writeOutput(path string, result *model.ExecutionResult) error {
    data, err := json.MarshalIndent(result, "", "  ")
    if err != nil {
        return err
    }
    return os.WriteFile(path, data, 0644)
}
```

---

## 4. WebSocket Protocol

### 4.1 Connection Flow

```
┌──────────────────────────────────────────────────────────────────────┐
│                      WebSocket Connection Flow                        │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Client                                  Server                       │
│    │                                        │                         │
│    │  1. WS Connect /ws/pipeline            │                         │
│    │ ──────────────────────────────────────>│                         │
│    │                                        │                         │
│    │  2. session.created                    │                         │
│    │ <──────────────────────────────────────│                         │
│    │                                        │                         │
│    │  3. session.configure                  │                         │
│    │ ──────────────────────────────────────>│                         │
│    │                                        │                         │
│    │  4. session.configured                 │                         │
│    │ <──────────────────────────────────────│                         │
│    │                                        │                         │
│    │  5. pipeline.execute                   │                         │
│    │ ──────────────────────────────────────>│                         │
│    │                                        │                         │
│    │  6. execution.started                  │                         │
│    │ <──────────────────────────────────────│                         │
│    │                                        │                         │
│    │  7. block.started (per block)          │                         │
│    │ <──────────────────────────────────────│                         │
│    │                                        │                         │
│    │  8. block.progress (streaming)         │                         │
│    │ <──────────────────────────────────────│                         │
│    │                                        │                         │
│    │  9. block.completed                    │                         │
│    │ <──────────────────────────────────────│                         │
│    │                                        │                         │
│    │  10. execution.completed               │                         │
│    │ <──────────────────────────────────────│                         │
│    │                                        │                         │
└──────────────────────────────────────────────────────────────────────┘
```

### 4.2 Message Types

```go
// internal/websocket/protocol.go
package websocket

import (
    "time"
    
    "pkg/types"
)

// MessageType defines WebSocket message types
type MessageType string

const (
    // Client -> Server
    MsgSessionConfigure  MessageType = "session.configure"
    MsgPipelineExecute   MessageType = "pipeline.execute"
    MsgPipelineCancel    MessageType = "pipeline.cancel"
    MsgPipelinePause     MessageType = "pipeline.pause"
    MsgPipelineResume    MessageType = "pipeline.resume"
    MsgBlockRetry        MessageType = "block.retry"
    MsgBlockSkip         MessageType = "block.skip"
    MsgInputProvide      MessageType = "input.provide"
    MsgPing              MessageType = "ping"
    
    // Server -> Client
    MsgSessionCreated    MessageType = "session.created"
    MsgSessionConfigured MessageType = "session.configured"
    MsgExecutionStarted  MessageType = "execution.started"
    MsgExecutionProgress MessageType = "execution.progress"
    MsgExecutionCompleted MessageType = "execution.completed"
    MsgExecutionFailed   MessageType = "execution.failed"
    MsgExecutionCanceled MessageType = "execution.canceled"
    MsgBlockStarted      MessageType = "block.started"
    MsgBlockProgress     MessageType = "block.progress"
    MsgBlockCompleted    MessageType = "block.completed"
    MsgBlockFailed       MessageType = "block.failed"
    MsgBlockWaiting      MessageType = "block.waiting"
    MsgCheckpointCreated MessageType = "checkpoint.created"
    MsgEscalationRequired MessageType = "escalation.required"
    MsgPong              MessageType = "pong"
    MsgError             MessageType = "error"
)

// BaseMessage is the base structure for all messages
type BaseMessage struct {
    Type      MessageType `json:"type"`
    EventID   string      `json:"eventId"`
    Timestamp time.Time   `json:"timestamp"`
}

// SessionCreatedMessage sent when connection established
type SessionCreatedMessage struct {
    BaseMessage
    SessionID   string `json:"sessionId"`
    ServerInfo  ServerInfo `json:"serverInfo"`
}

type ServerInfo struct {
    Version     string   `json:"version"`
    BlockTypes  []string `json:"blockTypes"`
    MaxParallel int      `json:"maxParallel"`
}

// SessionConfigureMessage sent by client to configure session
type SessionConfigureMessage struct {
    BaseMessage
    ProjectID   types.ProjectID `json:"projectId"`
    Settings    SessionSettings `json:"settings"`
}

type SessionSettings struct {
    MaxParallel       int           `json:"maxParallel"`
    DefaultTimeout    time.Duration `json:"defaultTimeout"`
    EnableCheckpoints bool          `json:"enableCheckpoints"`
    EnableStreaming   bool          `json:"enableStreaming"`
}

// PipelineExecuteMessage sent by client to start execution
type PipelineExecuteMessage struct {
    BaseMessage
    PipelineID  types.PipelineID       `json:"pipelineId"`
    Input       map[string]interface{} `json:"input,omitempty"`
    Options     ExecutionOptions       `json:"options,omitempty"`
}

type ExecutionOptions struct {
    StartFromBlock string        `json:"startFromBlock,omitempty"`
    StopAtBlock    string        `json:"stopAtBlock,omitempty"`
    Timeout        time.Duration `json:"timeout,omitempty"`
    DryRun         bool          `json:"dryRun,omitempty"`
}

// ExecutionStartedMessage sent when execution begins
type ExecutionStartedMessage struct {
    BaseMessage
    ExecutionID  types.ExecutionID `json:"executionId"`
    PipelineID   types.PipelineID  `json:"pipelineId"`
    TotalBlocks  int               `json:"totalBlocks"`
}

// BlockStartedMessage sent when a block begins execution
type BlockStartedMessage struct {
    BaseMessage
    ExecutionID types.ExecutionID `json:"executionId"`
    BlockID     string            `json:"blockId"`
    BlockType   string            `json:"blockType"`
    BlockName   string            `json:"blockName"`
    Index       int               `json:"index"`
    TotalBlocks int               `json:"totalBlocks"`
}

// BlockProgressMessage sent during block execution (streaming)
type BlockProgressMessage struct {
    BaseMessage
    ExecutionID types.ExecutionID `json:"executionId"`
    BlockID     string            `json:"blockId"`
    Progress    float64           `json:"progress"`
    Delta       string            `json:"delta,omitempty"`
    Metadata    map[string]interface{} `json:"metadata,omitempty"`
}

// BlockCompletedMessage sent when block finishes
type BlockCompletedMessage struct {
    BaseMessage
    ExecutionID types.ExecutionID      `json:"executionId"`
    BlockID     string                 `json:"blockId"`
    Output      map[string]interface{} `json:"output"`
    DurationMs  int64                  `json:"durationMs"`
}

// BlockFailedMessage sent when block fails
type BlockFailedMessage struct {
    BaseMessage
    ExecutionID types.ExecutionID `json:"executionId"`
    BlockID     string            `json:"blockId"`
    Error       ErrorInfo         `json:"error"`
    Retryable   bool              `json:"retryable"`
    RetryCount  int               `json:"retryCount"`
}

type ErrorInfo struct {
    Code       int      `json:"code"`
    Message    string   `json:"message"`
    Details    string   `json:"details,omitempty"`
    StackTrace []string `json:"stackTrace,omitempty"`
}

// EscalationRequiredMessage sent when human input needed
type EscalationRequiredMessage struct {
    BaseMessage
    ExecutionID types.ExecutionID `json:"executionId"`
    BlockID     string            `json:"blockId"`
    Reason      string            `json:"reason"`
    Options     []EscalationOption `json:"options"`
    Timeout     time.Duration     `json:"timeout"`
}

type EscalationOption struct {
    ID          string `json:"id"`
    Label       string `json:"label"`
    Description string `json:"description,omitempty"`
}

// InputProvideMessage sent by client to provide escalation input
type InputProvideMessage struct {
    BaseMessage
    ExecutionID types.ExecutionID      `json:"executionId"`
    BlockID     string                 `json:"blockId"`
    OptionID    string                 `json:"optionId,omitempty"`
    Input       map[string]interface{} `json:"input,omitempty"`
}

// ExecutionCompletedMessage sent when execution finishes
type ExecutionCompletedMessage struct {
    BaseMessage
    ExecutionID types.ExecutionID      `json:"executionId"`
    Status      string                 `json:"status"`
    Output      map[string]interface{} `json:"output"`
    Stats       ExecutionStats         `json:"stats"`
}

type ExecutionStats struct {
    TotalBlocks     int   `json:"totalBlocks"`
    CompletedBlocks int   `json:"completedBlocks"`
    FailedBlocks    int   `json:"failedBlocks"`
    SkippedBlocks   int   `json:"skippedBlocks"`
    TotalDurationMs int64 `json:"totalDurationMs"`
    RetryCount      int   `json:"retryCount"`
}
```

### 4.3 WebSocket Server Implementation

```go
// internal/websocket/server.go
package websocket

import (
    "context"
    "encoding/json"
    "net/http"
    "runtime"
    "sync"
    "time"
    
    "github.com/gorilla/websocket"
    
    "nexus-flow/internal/engine"
    "pkg/errors"
    "pkg/logging"
)

var upgrader = websocket.Upgrader{
    ReadBufferSize:  1024,
    WriteBufferSize: 1024,
    CheckOrigin: func(r *http.Request) bool {
        return true // Configure appropriately for production
    },
}

// Server manages WebSocket connections
type Server struct {
    engine   *engine.Engine
    sessions map[string]*Session
    mu       sync.RWMutex
    logger   *logging.Logger
}

// NewServer creates a new WebSocket server
func NewServer(eng *engine.Engine, logger *logging.Logger) *Server {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing WebSocket server",
        "file", file,
        "line", line,
    )
    
    return &Server{
        engine:   eng,
        sessions: make(map[string]*Session),
        logger:   logger,
    }
}

// HandleConnection handles new WebSocket connections
func (s *Server) HandleConnection(w http.ResponseWriter, r *http.Request) {
    _, file, line, _ := runtime.Caller(0)
    
    conn, err := upgrader.Upgrade(w, r, nil)
    if err != nil {
        s.logger.Error("WebSocket upgrade failed",
            "file", file,
            "line", line,
            "error", err,
        )
        return
    }
    
    session := NewSession(conn, s.engine, s.logger)
    
    s.mu.Lock()
    s.sessions[session.ID] = session
    s.mu.Unlock()
    
    s.logger.Info("WebSocket session created",
        "file", file,
        "line", line,
        "sessionId", session.ID,
        "remoteAddr", r.RemoteAddr,
    )
    
    // Send session.created
    session.Send(SessionCreatedMessage{
        BaseMessage: BaseMessage{
            Type:      MsgSessionCreated,
            EventID:   generateEventID(),
            Timestamp: time.Now(),
        },
        SessionID: session.ID,
        ServerInfo: ServerInfo{
            Version:     "1.0.0",
            BlockTypes:  []string{"prompt", "search", "codegen", "validation", "transform", "http", "fileop"},
            MaxParallel: 10,
        },
    })
    
    // Start handling messages
    go session.ReadPump()
    go session.WritePump()
    
    // Cleanup on disconnect
    go func() {
        <-session.Done()
        s.mu.Lock()
        delete(s.sessions, session.ID)
        s.mu.Unlock()
        
        s.logger.Info("WebSocket session closed",
            "file", file,
            "line", line,
            "sessionId", session.ID,
        )
    }()
}

// Session represents a WebSocket session
type Session struct {
    ID       string
    conn     *websocket.Conn
    engine   *engine.Engine
    send     chan interface{}
    done     chan struct{}
    settings SessionSettings
    mu       sync.RWMutex
    logger   *logging.Logger
}

// NewSession creates a new session
func NewSession(conn *websocket.Conn, eng *engine.Engine, logger *logging.Logger) *Session {
    return &Session{
        ID:     generateSessionID(),
        conn:   conn,
        engine: eng,
        send:   make(chan interface{}, 256),
        done:   make(chan struct{}),
        logger: logger,
    }
}

// ReadPump reads messages from the connection
func (s *Session) ReadPump() {
    _, file, line, _ := runtime.Caller(0)
    defer close(s.done)
    
    s.conn.SetReadDeadline(time.Now().Add(60 * time.Second))
    s.conn.SetPongHandler(func(string) error {
        s.conn.SetReadDeadline(time.Now().Add(60 * time.Second))
        return nil
    })
    
    for {
        _, message, err := s.conn.ReadMessage()
        if err != nil {
            if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
                s.logger.Error("WebSocket read error",
                    "file", file,
                    "line", line,
                    "sessionId", s.ID,
                    "error", err,
                )
            }
            return
        }
        
        s.handleMessage(message)
    }
}

// WritePump writes messages to the connection
func (s *Session) WritePump() {
    _, file, line, _ := runtime.Caller(0)
    ticker := time.NewTicker(30 * time.Second)
    defer ticker.Stop()
    
    for {
        select {
        case message, ok := <-s.send:
            if !ok {
                s.conn.WriteMessage(websocket.CloseMessage, []byte{})
                return
            }
            
            data, err := json.Marshal(message)
            if err != nil {
                s.logger.Error("Message marshal failed",
                    "file", file,
                    "line", line,
                    "error", err,
                )
                continue
            }
            
            if err := s.conn.WriteMessage(websocket.TextMessage, data); err != nil {
                s.logger.Error("WebSocket write error",
                    "file", file,
                    "line", line,
                    "error", err,
                )
                return
            }
            
        case <-ticker.C:
            if err := s.conn.WriteMessage(websocket.PingMessage, nil); err != nil {
                return
            }
            
        case <-s.done:
            return
        }
    }
}

// Send sends a message to the client
func (s *Session) Send(msg interface{}) {
    select {
    case s.send <- msg:
    default:
        _, file, line, _ := runtime.Caller(0)
        s.logger.Warn("Send channel full, dropping message",
            "file", file,
            "line", line,
            "sessionId", s.ID,
        )
    }
}

// Done returns the done channel
func (s *Session) Done() <-chan struct{} {
    return s.done
}

// handleMessage processes incoming messages
func (s *Session) handleMessage(data []byte) {
    _, file, line, _ := runtime.Caller(0)
    
    var base BaseMessage
    if err := json.Unmarshal(data, &base); err != nil {
        s.logger.Error("Message unmarshal failed",
            "file", file,
            "line", line,
            "error", err,
        )
        return
    }
    
    s.logger.Debug("Received message",
        "file", file,
        "line", line,
        "sessionId", s.ID,
        "type", base.Type,
    )
    
    switch base.Type {
    case MsgSessionConfigure:
        s.handleConfigure(data)
    case MsgPipelineExecute:
        s.handleExecute(data)
    case MsgPipelineCancel:
        s.handleCancel(data)
    case MsgInputProvide:
        s.handleInput(data)
    case MsgPing:
        s.Send(BaseMessage{Type: MsgPong, Timestamp: time.Now()})
    default:
        s.logger.Warn("Unknown message type",
            "file", file,
            "line", line,
            "type", base.Type,
        )
    }
}

func (s *Session) handleExecute(data []byte) {
    _, file, line, _ := runtime.Caller(0)
    
    var msg PipelineExecuteMessage
    if err := json.Unmarshal(data, &msg); err != nil {
        s.sendError(errors.CodeValidationError, "Invalid execute message", err)
        return
    }
    
    s.logger.Info("Executing pipeline",
        "file", file,
        "line", line,
        "sessionId", s.ID,
        "pipelineId", msg.PipelineID,
    )
    
    // Create execution context with session for callbacks
    ctx := context.Background()
    
    // Execute with streaming callbacks
    go func() {
        result, err := s.engine.ExecuteWithCallbacks(ctx, msg.PipelineID, msg.Input, ExecutionCallbacks{
            OnBlockStart: func(block BlockInfo) {
                s.Send(BlockStartedMessage{
                    BaseMessage: BaseMessage{
                        Type:      MsgBlockStarted,
                        EventID:   generateEventID(),
                        Timestamp: time.Now(),
                    },
                    BlockID:   block.ID,
                    BlockType: block.Type,
                    BlockName: block.Name,
                })
            },
            OnBlockProgress: func(block BlockInfo, progress float64, delta string) {
                s.Send(BlockProgressMessage{
                    BaseMessage: BaseMessage{
                        Type:      MsgBlockProgress,
                        EventID:   generateEventID(),
                        Timestamp: time.Now(),
                    },
                    BlockID:  block.ID,
                    Progress: progress,
                    Delta:    delta,
                })
            },
            OnBlockComplete: func(block BlockInfo, output map[string]interface{}, duration time.Duration) {
                s.Send(BlockCompletedMessage{
                    BaseMessage: BaseMessage{
                        Type:      MsgBlockCompleted,
                        EventID:   generateEventID(),
                        Timestamp: time.Now(),
                    },
                    BlockID:    block.ID,
                    Output:     output,
                    DurationMs: duration.Milliseconds(),
                })
            },
            OnEscalation: func(block BlockInfo, reason string, options []EscalationOption) {
                s.Send(EscalationRequiredMessage{
                    BaseMessage: BaseMessage{
                        Type:      MsgEscalationRequired,
                        EventID:   generateEventID(),
                        Timestamp: time.Now(),
                    },
                    BlockID: block.ID,
                    Reason:  reason,
                    Options: options,
                    Timeout: 5 * time.Minute,
                })
            },
        })
        
        if err != nil {
            s.Send(ExecutionFailedMessage{
                BaseMessage: BaseMessage{
                    Type:      MsgExecutionFailed,
                    EventID:   generateEventID(),
                    Timestamp: time.Now(),
                },
                Error: ErrorInfo{
                    Code:    errors.GetCode(err),
                    Message: err.Error(),
                },
            })
            return
        }
        
        s.Send(ExecutionCompletedMessage{
            BaseMessage: BaseMessage{
                Type:      MsgExecutionCompleted,
                EventID:   generateEventID(),
                Timestamp: time.Now(),
            },
            ExecutionID: result.ExecutionID,
            Status:      "completed",
            Output:      result.Output,
            Stats:       result.Stats,
        })
    }()
}
```

---

## 5. Block Execution Architecture

### 5.1 Block Interface

```go
// internal/block/interface.go
package block

import (
    "context"
    
    "nexus-flow/internal/model"
)

// BlockType defines the 7 supported block types
type BlockType string

const (
    BlockTypePrompt     BlockType = "prompt"
    BlockTypeSearch     BlockType = "search"
    BlockTypeCodeGen    BlockType = "codegen"
    BlockTypeValidation BlockType = "validation"
    BlockTypeTransform  BlockType = "transform"
    BlockTypeHTTP       BlockType = "http"
    BlockTypeFileOp     BlockType = "fileop"
)

// Block is the interface all block types implement
type Block interface {
    // Type returns the block type
    Type() BlockType
    
    // Validate validates block configuration
    Validate() error
    
    // Execute runs the block
    Execute(ctx context.Context, input BlockInput) (*BlockOutput, error)
    
    // SupportsStreaming returns true if block supports streaming output
    SupportsStreaming() bool
    
    // ExecuteStreaming runs the block with streaming callbacks
    ExecuteStreaming(ctx context.Context, input BlockInput, callback StreamCallback) (*BlockOutput, error)
}

// BlockInput contains input data for block execution
type BlockInput struct {
    Data       map[string]interface{} // Input data from previous blocks
    Context    ExecutionContext       // Execution context
    Config     BlockConfig            // Block-specific configuration
}

// BlockOutput contains block execution results
type BlockOutput struct {
    Data       map[string]interface{} // Output data
    Metadata   OutputMetadata         // Execution metadata
}

// OutputMetadata contains execution metadata
type OutputMetadata struct {
    DurationMs   int64
    TokensUsed   int
    CacheHit     bool
    RetryCount   int
}

// ExecutionContext provides execution context
type ExecutionContext struct {
    ExecutionID types.ExecutionID
    ProjectID   types.ProjectID
    Variables   map[string]string
    Secrets     map[string]string
}

// BlockConfig contains block configuration
type BlockConfig struct {
    ID          string                 `json:"id"`
    Name        string                 `json:"name"`
    Type        BlockType              `json:"type"`
    Settings    map[string]interface{} `json:"settings"`
    Inputs      []ConnectionRef        `json:"inputs"`
    Outputs     []ConnectionRef        `json:"outputs"`
    Conditions  []Condition            `json:"conditions,omitempty"`
    RetryPolicy *RetryPolicy           `json:"retryPolicy,omitempty"`
}

// ConnectionRef references a connection between blocks
type ConnectionRef struct {
    BlockID  string `json:"blockId"`
    PortName string `json:"portName"`
}

// Condition for conditional branching
type Condition struct {
    Expression string `json:"expression"` // CEL expression
    TargetPort string `json:"targetPort"`
}

// RetryPolicy defines retry behavior
type RetryPolicy struct {
    MaxRetries     int           `json:"maxRetries"`
    InitialDelay   time.Duration `json:"initialDelay"`
    MaxDelay       time.Duration `json:"maxDelay"`
    BackoffFactor  float64       `json:"backoffFactor"`
    RetryableError []int         `json:"retryableErrors"` // Error codes
}

// StreamCallback for streaming execution
type StreamCallback func(delta StreamDelta)

// StreamDelta represents a streaming output chunk
type StreamDelta struct {
    Type    string `json:"type"`    // "text", "code", "progress"
    Content string `json:"content"`
    Done    bool   `json:"done"`
}
```

### 5.2 Block Registry

```go
// internal/block/registry.go
package block

import (
    "fmt"
    "runtime"
    "sync"
    
    "pkg/errors"
    "pkg/logging"
)

// Registry manages block type registrations
type Registry struct {
    blocks map[BlockType]BlockFactory
    mu     sync.RWMutex
    logger *logging.Logger
}

// BlockFactory creates new block instances
type BlockFactory func(config BlockConfig) (Block, error)

// NewRegistry creates a new block registry
func NewRegistry(logger *logging.Logger) *Registry {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing block registry",
        "file", file,
        "line", line,
    )
    
    r := &Registry{
        blocks: make(map[BlockType]BlockFactory),
        logger: logger,
    }
    
    // Register default block types
    r.Register(BlockTypePrompt, NewPromptBlock)
    r.Register(BlockTypeSearch, NewSearchBlock)
    r.Register(BlockTypeCodeGen, NewCodeGenBlock)
    r.Register(BlockTypeValidation, NewValidationBlock)
    r.Register(BlockTypeTransform, NewTransformBlock)
    r.Register(BlockTypeHTTP, NewHTTPBlock)
    r.Register(BlockTypeFileOp, NewFileOpBlock)
    
    return r
}

// Register registers a block factory
func (r *Registry) Register(blockType BlockType, factory BlockFactory) {
    _, file, line, _ := runtime.Caller(0)
    
    r.mu.Lock()
    defer r.mu.Unlock()
    
    r.blocks[blockType] = factory
    
    r.logger.Debug("Registered block type",
        "file", file,
        "line", line,
        "type", blockType,
    )
}

// Create creates a new block instance
func (r *Registry) Create(config BlockConfig) (Block, error) {
    _, file, line, _ := runtime.Caller(0)
    
    r.mu.RLock()
    factory, exists := r.blocks[config.Type]
    r.mu.RUnlock()
    
    if !exists {
        return nil, errors.New(errors.CodeValidationError,
            fmt.Sprintf("Unknown block type: %s", config.Type),
            "file", file,
            "line", line,
        )
    }
    
    block, err := factory(config)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "Failed to create block",
            "file", file,
            "line", line,
            "type", config.Type,
        )
    }
    
    return block, nil
}

// ListTypes returns all registered block types
func (r *Registry) ListTypes() []BlockType {
    r.mu.RLock()
    defer r.mu.RUnlock()
    
    types := make([]BlockType, 0, len(r.blocks))
    for t := range r.blocks {
        types = append(types, t)
    }
    return types
}
```

### 5.3 Block Implementations

#### 5.3.1 Prompt Block

```go
// internal/block/prompt.go
package block

import (
    "context"
    "fmt"
    "runtime"
    "text/template"
    "bytes"
    
    "nexus-flow/internal/client"
    "pkg/errors"
    "pkg/logging"
)

// PromptBlock executes AI prompts via AI-Bridge
type PromptBlock struct {
    config       BlockConfig
    aiBridge     *client.AIBridgeClient
    logger       *logging.Logger
    template     *template.Template
}

// PromptSettings defines prompt-specific settings
type PromptSettings struct {
    Model         string            `json:"model"`
    PromptTemplate string           `json:"promptTemplate"`
    SystemPrompt  string            `json:"systemPrompt,omitempty"`
    Temperature   float64           `json:"temperature"`
    MaxTokens     int               `json:"maxTokens"`
    Variables     map[string]string `json:"variables,omitempty"`
}

// NewPromptBlock creates a new prompt block
func NewPromptBlock(config BlockConfig) (Block, error) {
    _, file, line, _ := runtime.Caller(0)
    
    settings, err := parsePromptSettings(config.Settings)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeValidationError,
            "Invalid prompt settings",
            "file", file,
            "line", line,
        )
    }
    
    tmpl, err := template.New("prompt").Parse(settings.PromptTemplate)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeValidationError,
            "Invalid prompt template",
            "file", file,
            "line", line,
        )
    }
    
    return &PromptBlock{
        config:   config,
        template: tmpl,
        logger:   logging.Default(),
    }, nil
}

func (b *PromptBlock) Type() BlockType {
    return BlockTypePrompt
}

func (b *PromptBlock) Validate() error {
    settings, err := parsePromptSettings(b.config.Settings)
    if err != nil {
        return err
    }
    
    if settings.PromptTemplate == "" {
        _, file, line, _ := runtime.Caller(0)
        return errors.New(errors.CodeValidationError,
            "Prompt template is required",
            "file", file,
            "line", line,
        )
    }
    
    return nil
}

func (b *PromptBlock) SupportsStreaming() bool {
    return true
}

func (b *PromptBlock) Execute(ctx context.Context, input BlockInput) (*BlockOutput, error) {
    _, file, line, _ := runtime.Caller(0)
    
    settings, _ := parsePromptSettings(b.config.Settings)
    
    // Render prompt template
    var buf bytes.Buffer
    data := mergeData(input.Data, settings.Variables)
    if err := b.template.Execute(&buf, data); err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "Failed to render prompt template",
            "file", file,
            "line", line,
        )
    }
    
    prompt := buf.String()
    
    b.logger.Debug("Executing prompt",
        "file", file,
        "line", line,
        "blockId", b.config.ID,
        "model", settings.Model,
        "promptLength", len(prompt),
    )
    
    // Call AI-Bridge
    resp, err := b.aiBridge.Complete(ctx, client.CompletionRequest{
        Model:        settings.Model,
        Prompt:       prompt,
        SystemPrompt: settings.SystemPrompt,
        Temperature:  settings.Temperature,
        MaxTokens:    settings.MaxTokens,
    })
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeExternalServiceError,
            "AI completion failed",
            "file", file,
            "line", line,
            "model", settings.Model,
        )
    }
    
    return &BlockOutput{
        Data: map[string]interface{}{
            "response": resp.Content,
            "prompt":   prompt,
        },
        Metadata: OutputMetadata{
            TokensUsed: resp.TokensUsed,
        },
    }, nil
}

func (b *PromptBlock) ExecuteStreaming(ctx context.Context, input BlockInput, callback StreamCallback) (*BlockOutput, error) {
    _, file, line, _ := runtime.Caller(0)
    
    settings, _ := parsePromptSettings(b.config.Settings)
    
    // Render prompt template
    var buf bytes.Buffer
    data := mergeData(input.Data, settings.Variables)
    if err := b.template.Execute(&buf, data); err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "Failed to render prompt template",
            "file", file,
            "line", line,
        )
    }
    
    prompt := buf.String()
    
    var fullResponse bytes.Buffer
    tokensUsed := 0
    
    err := b.aiBridge.CompleteStreaming(ctx, client.CompletionRequest{
        Model:        settings.Model,
        Prompt:       prompt,
        SystemPrompt: settings.SystemPrompt,
        Temperature:  settings.Temperature,
        MaxTokens:    settings.MaxTokens,
    }, func(delta client.StreamDelta) {
        fullResponse.WriteString(delta.Content)
        tokensUsed = delta.TotalTokens
        
        callback(StreamDelta{
            Type:    "text",
            Content: delta.Content,
            Done:    delta.Done,
        })
    })
    
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeExternalServiceError,
            "Streaming completion failed",
            "file", file,
            "line", line,
        )
    }
    
    return &BlockOutput{
        Data: map[string]interface{}{
            "response": fullResponse.String(),
            "prompt":   prompt,
        },
        Metadata: OutputMetadata{
            TokensUsed: tokensUsed,
        },
    }, nil
}
```

#### 5.3.2 Search Block

```go
// internal/block/search.go
package block

import (
    "context"
    "runtime"
    
    "nexus-flow/internal/client"
    "pkg/errors"
    "pkg/logging"
)

// SearchBlock performs RAG-powered search via Scout
type SearchBlock struct {
    config   BlockConfig
    scout    *client.ScoutClient
    logger   *logging.Logger
}

// SearchSettings defines search-specific settings
type SearchSettings struct {
    Query       string   `json:"query"`
    SearchType  string   `json:"searchType"` // fts, vss, hybrid
    TopK        int      `json:"topK"`
    MinScore    float64  `json:"minScore"`
    FileTypes   []string `json:"fileTypes,omitempty"`
    IncludeRAG  bool     `json:"includeRag"`
}

func NewSearchBlock(config BlockConfig) (Block, error) {
    return &SearchBlock{
        config: config,
        logger: logging.Default(),
    }, nil
}

func (b *SearchBlock) Type() BlockType {
    return BlockTypeSearch
}

func (b *SearchBlock) Validate() error {
    settings, err := parseSearchSettings(b.config.Settings)
    if err != nil {
        return err
    }
    
    if settings.Query == "" {
        _, file, line, _ := runtime.Caller(0)
        return errors.New(errors.CodeValidationError,
            "Search query is required",
            "file", file,
            "line", line,
        )
    }
    
    return nil
}

func (b *SearchBlock) SupportsStreaming() bool {
    return false
}

func (b *SearchBlock) Execute(ctx context.Context, input BlockInput) (*BlockOutput, error) {
    _, file, line, _ := runtime.Caller(0)
    
    settings, _ := parseSearchSettings(b.config.Settings)
    
    // Interpolate query with input data
    query := interpolateString(settings.Query, input.Data)
    
    b.logger.Debug("Executing search",
        "file", file,
        "line", line,
        "blockId", b.config.ID,
        "query", query,
        "searchType", settings.SearchType,
    )
    
    resp, err := b.scout.Search(ctx, client.SearchRequest{
        Query:      query,
        ProjectID:  input.Context.ProjectID,
        SearchType: settings.SearchType,
        TopK:       settings.TopK,
        MinScore:   settings.MinScore,
        FileTypes:  settings.FileTypes,
    })
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeExternalServiceError,
            "Search failed",
            "file", file,
            "line", line,
        )
    }
    
    output := map[string]interface{}{
        "results":    resp.Results,
        "totalCount": resp.TotalCount,
        "query":      query,
    }
    
    // Include RAG context if requested
    if settings.IncludeRAG {
        ragResp, err := b.scout.GetRAGContext(ctx, client.RAGRequest{
            Query:     query,
            ProjectID: input.Context.ProjectID,
            TopK:      settings.TopK,
        })
        if err == nil {
            output["ragContext"] = ragResp.FormattedPrompt
            output["sources"] = ragResp.Sources
        }
    }
    
    return &BlockOutput{
        Data:     output,
        Metadata: OutputMetadata{},
    }, nil
}

func (b *SearchBlock) ExecuteStreaming(ctx context.Context, input BlockInput, callback StreamCallback) (*BlockOutput, error) {
    return b.Execute(ctx, input)
}
```

#### 5.3.3 CodeGen Block

```go
// internal/block/codegen.go
package block

import (
    "context"
    "runtime"
    
    "nexus-flow/internal/client"
    "pkg/errors"
    "pkg/logging"
)

// CodeGenBlock generates code via AI-Bridge with specialized models
type CodeGenBlock struct {
    config   BlockConfig
    aiBridge *client.AIBridgeClient
    logger   *logging.Logger
}

// CodeGenSettings defines codegen-specific settings
type CodeGenSettings struct {
    Model       string `json:"model"`       // codellama, deepseek-coder, etc.
    Language    string `json:"language"`    // go, typescript, python, etc.
    Task        string `json:"task"`        // generate, refactor, fix, explain
    Context     string `json:"context"`     // Surrounding code context
    Instruction string `json:"instruction"` // What to generate
    MaxTokens   int    `json:"maxTokens"`
}

func NewCodeGenBlock(config BlockConfig) (Block, error) {
    return &CodeGenBlock{
        config: config,
        logger: logging.Default(),
    }, nil
}

func (b *CodeGenBlock) Type() BlockType {
    return BlockTypeCodeGen
}

func (b *CodeGenBlock) Validate() error {
    settings, err := parseCodeGenSettings(b.config.Settings)
    if err != nil {
        return err
    }
    
    if settings.Instruction == "" {
        _, file, line, _ := runtime.Caller(0)
        return errors.New(errors.CodeValidationError,
            "Instruction is required",
            "file", file,
            "line", line,
        )
    }
    
    return nil
}

func (b *CodeGenBlock) SupportsStreaming() bool {
    return true
}

func (b *CodeGenBlock) Execute(ctx context.Context, input BlockInput) (*BlockOutput, error) {
    _, file, line, _ := runtime.Caller(0)
    
    settings, _ := parseCodeGenSettings(b.config.Settings)
    
    // Build code generation prompt
    prompt := buildCodeGenPrompt(settings, input.Data)
    
    b.logger.Debug("Executing codegen",
        "file", file,
        "line", line,
        "blockId", b.config.ID,
        "language", settings.Language,
        "task", settings.Task,
    )
    
    resp, err := b.aiBridge.Complete(ctx, client.CompletionRequest{
        Model:       settings.Model,
        Prompt:      prompt,
        MaxTokens:   settings.MaxTokens,
        Temperature: 0.2, // Lower temperature for code
    })
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeExternalServiceError,
            "Code generation failed",
            "file", file,
            "line", line,
        )
    }
    
    // Extract code from response
    code := extractCodeBlock(resp.Content, settings.Language)
    
    return &BlockOutput{
        Data: map[string]interface{}{
            "code":        code,
            "fullResponse": resp.Content,
            "language":    settings.Language,
        },
        Metadata: OutputMetadata{
            TokensUsed: resp.TokensUsed,
        },
    }, nil
}

func (b *CodeGenBlock) ExecuteStreaming(ctx context.Context, input BlockInput, callback StreamCallback) (*BlockOutput, error) {
    // Similar to Prompt block streaming
    return b.Execute(ctx, input)
}
```

#### 5.3.4 Additional Block Types (Transform, HTTP, FileOp, Validation)

```go
// internal/block/transform.go
package block

// TransformBlock applies data transformations using expressions
type TransformBlock struct {
    config BlockConfig
    logger *logging.Logger
}

// TransformSettings defines transform operations
type TransformSettings struct {
    Operations []TransformOp `json:"operations"`
}

type TransformOp struct {
    Type       string `json:"type"`       // map, filter, reduce, extract, merge
    Expression string `json:"expression"` // CEL expression
    TargetKey  string `json:"targetKey"`
}

// internal/block/http.go
package block

// HTTPBlock makes external HTTP requests
type HTTPBlock struct {
    config     BlockConfig
    httpClient *http.Client
    logger     *logging.Logger
}

// HTTPSettings defines HTTP request configuration
type HTTPSettings struct {
    Method      string            `json:"method"`
    URL         string            `json:"url"`
    Headers     map[string]string `json:"headers,omitempty"`
    Body        string            `json:"body,omitempty"`
    BodyType    string            `json:"bodyType"` // json, form, raw
    Timeout     time.Duration     `json:"timeout"`
    RetryOn     []int             `json:"retryOn"` // Status codes to retry
}

// internal/block/fileop.go
package block

// FileOpBlock performs file system operations
type FileOpBlock struct {
    config    BlockConfig
    validator *PathValidator
    logger    *logging.Logger
}

// FileOpSettings defines file operation configuration
type FileOpSettings struct {
    Operation string `json:"operation"` // read, write, append, delete, copy, move, list
    Path      string `json:"path"`
    Content   string `json:"content,omitempty"`
    Encoding  string `json:"encoding"` // utf8, base64
    CreateDir bool   `json:"createDir"`
}

// internal/block/validation.go
package block

// ValidationBlock validates block outputs
type ValidationBlock struct {
    config BlockConfig
    logger *logging.Logger
}

// ValidationSettings defines validation rules
type ValidationSettings struct {
    Rules    []ValidationRule `json:"rules"`
    FailFast bool             `json:"failFast"`
}

type ValidationRule struct {
    Name       string `json:"name"`
    Expression string `json:"expression"` // CEL expression
    Message    string `json:"message"`
    Severity   string `json:"severity"` // error, warning, info
}
```

---

## 6. Control Flow

### 6.1 Conditional Branching

```go
// internal/control/branch.go
package control

import (
    "context"
    "runtime"
    
    "github.com/google/cel-go/cel"
    
    "nexus-flow/internal/model"
    "pkg/errors"
    "pkg/logging"
)

// BranchController handles conditional branching
type BranchController struct {
    env    *cel.Env
    logger *logging.Logger
}

// BranchConfig defines branching configuration
type BranchConfig struct {
    Conditions []BranchCondition `json:"conditions"`
    Default    string            `json:"default,omitempty"` // Default branch ID
}

// BranchCondition defines a single branch condition
type BranchCondition struct {
    Expression string `json:"expression"` // CEL expression
    TargetID   string `json:"targetId"`   // Target block ID
    Priority   int    `json:"priority"`   // Evaluation order
}

// NewBranchController creates a new branch controller
func NewBranchController(logger *logging.Logger) (*BranchController, error) {
    _, file, line, _ := runtime.Caller(0)
    
    env, err := cel.NewEnv(
        cel.Declarations(
            // Add common declarations
        ),
    )
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "Failed to create CEL environment",
            "file", file,
            "line", line,
        )
    }
    
    logger.Info("Initializing BranchController",
        "file", file,
        "line", line,
    )
    
    return &BranchController{
        env:    env,
        logger: logger,
    }, nil
}

// Evaluate evaluates conditions and returns the target block ID
func (c *BranchController) Evaluate(ctx context.Context, config BranchConfig, data map[string]interface{}) (string, error) {
    _, file, line, _ := runtime.Caller(0)
    
    // Sort conditions by priority
    sortedConditions := sortByPriority(config.Conditions)
    
    for _, cond := range sortedConditions {
        ast, issues := c.env.Compile(cond.Expression)
        if issues != nil && issues.Err() != nil {
            c.logger.Warn("Invalid condition expression",
                "file", file,
                "line", line,
                "expression", cond.Expression,
                "error", issues.Err(),
            )
            continue
        }
        
        prg, err := c.env.Program(ast)
        if err != nil {
            continue
        }
        
        result, _, err := prg.Eval(data)
        if err != nil {
            c.logger.Warn("Condition evaluation failed",
                "file", file,
                "line", line,
                "expression", cond.Expression,
                "error", err,
            )
            continue
        }
        
        if result.Value() == true {
            c.logger.Debug("Branch condition matched",
                "file", file,
                "line", line,
                "expression", cond.Expression,
                "targetId", cond.TargetID,
            )
            return cond.TargetID, nil
        }
    }
    
    // Return default if no condition matched
    if config.Default != "" {
        return config.Default, nil
    }
    
    return "", errors.New(errors.CodeValidationError,
        "No branch condition matched and no default specified",
        "file", file,
        "line", line,
    )
}
```

### 6.2 Concurrency-Throttled Loops

```go
// internal/control/loop.go
package control

import (
    "context"
    "runtime"
    "sync"
    
    "golang.org/x/sync/semaphore"
    
    "nexus-flow/internal/block"
    "pkg/errors"
    "pkg/logging"
)

// LoopController handles loop execution with concurrency control
type LoopController struct {
    registry *block.Registry
    logger   *logging.Logger
}

// LoopConfig defines loop configuration
type LoopConfig struct {
    Type           LoopType `json:"type"`           // forEach, while, repeat
    MaxConcurrency int      `json:"maxConcurrency"` // Max parallel iterations
    MaxIterations  int      `json:"maxIterations"`  // Safety limit
    Collection     string   `json:"collection"`     // For forEach: data path
    Condition      string   `json:"condition"`      // For while: CEL expression
    RepeatCount    int      `json:"repeatCount"`    // For repeat: iteration count
    BlockIDs       []string `json:"blockIds"`       // Blocks to execute per iteration
}

// LoopType defines loop behavior
type LoopType string

const (
    LoopTypeForEach LoopType = "forEach"
    LoopTypeWhile   LoopType = "while"
    LoopTypeRepeat  LoopType = "repeat"
)

// NewLoopController creates a new loop controller
func NewLoopController(registry *block.Registry, logger *logging.Logger) *LoopController {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing LoopController",
        "file", file,
        "line", line,
    )
    
    return &LoopController{
        registry: registry,
        logger:   logger,
    }
}

// Execute runs a loop with concurrency throttling
func (c *LoopController) Execute(
    ctx context.Context,
    config LoopConfig,
    input block.BlockInput,
    callback block.StreamCallback,
) ([]map[string]interface{}, error) {
    _, file, line, _ := runtime.Caller(0)
    
    maxConcurrency := config.MaxConcurrency
    if maxConcurrency <= 0 {
        maxConcurrency = 1
    }
    
    c.logger.Info("Executing loop",
        "file", file,
        "line", line,
        "type", config.Type,
        "maxConcurrency", maxConcurrency,
    )
    
    switch config.Type {
    case LoopTypeForEach:
        return c.executeForEach(ctx, config, input, callback, maxConcurrency)
    case LoopTypeWhile:
        return c.executeWhile(ctx, config, input, callback)
    case LoopTypeRepeat:
        return c.executeRepeat(ctx, config, input, callback, maxConcurrency)
    default:
        return nil, errors.New(errors.CodeValidationError,
            "Unknown loop type",
            "file", file,
            "line", line,
            "type", config.Type,
        )
    }
}

// executeForEach runs forEach loop with parallel execution
func (c *LoopController) executeForEach(
    ctx context.Context,
    config LoopConfig,
    input block.BlockInput,
    callback block.StreamCallback,
    maxConcurrency int,
) ([]map[string]interface{}, error) {
    _, file, line, _ := runtime.Caller(0)
    
    // Get collection from input data
    collection, ok := getNestedValue(input.Data, config.Collection).([]interface{})
    if !ok {
        return nil, errors.New(errors.CodeValidationError,
            "Collection not found or not an array",
            "file", file,
            "line", line,
            "path", config.Collection,
        )
    }
    
    if len(collection) > config.MaxIterations {
        collection = collection[:config.MaxIterations]
        c.logger.Warn("Collection truncated to max iterations",
            "file", file,
            "line", line,
            "maxIterations", config.MaxIterations,
        )
    }
    
    // Use semaphore for concurrency control
    sem := semaphore.NewWeighted(int64(maxConcurrency))
    results := make([]map[string]interface{}, len(collection))
    errs := make([]error, len(collection))
    
    var wg sync.WaitGroup
    
    for i, item := range collection {
        wg.Add(1)
        
        go func(idx int, itemData interface{}) {
            defer wg.Done()
            
            if err := sem.Acquire(ctx, 1); err != nil {
                errs[idx] = err
                return
            }
            defer sem.Release(1)
            
            // Execute blocks for this iteration
            iterInput := input
            iterInput.Data = map[string]interface{}{
                "item":  itemData,
                "index": idx,
                "parent": input.Data,
            }
            
            result, err := c.executeBlocks(ctx, config.BlockIDs, iterInput, callback)
            if err != nil {
                errs[idx] = err
                return
            }
            
            results[idx] = result
        }(i, item)
    }
    
    wg.Wait()
    
    // Check for errors
    for i, err := range errs {
        if err != nil {
            return results, errors.Wrap(err, errors.CodeInternalError,
                "Loop iteration failed",
                "file", file,
                "line", line,
                "iteration", i,
            )
        }
    }
    
    return results, nil
}

// executeBlocks executes a sequence of blocks
func (c *LoopController) executeBlocks(
    ctx context.Context,
    blockIDs []string,
    input block.BlockInput,
    callback block.StreamCallback,
) (map[string]interface{}, error) {
    data := input.Data
    
    for _, blockID := range blockIDs {
        blk, err := c.registry.Create(block.BlockConfig{ID: blockID})
        if err != nil {
            return nil, err
        }
        
        blockInput := block.BlockInput{
            Data:    data,
            Context: input.Context,
        }
        
        output, err := blk.ExecuteStreaming(ctx, blockInput, callback)
        if err != nil {
            return nil, err
        }
        
        // Merge output into data for next block
        for k, v := range output.Data {
            data[k] = v
        }
    }
    
    return data, nil
}
```

---

## 7. RES Integration

### 7.1 RES Bridge

```go
// internal/res/bridge.go
package res

import (
    "context"
    "runtime"
    "time"
    
    "nexus-flow/internal/block"
    "nexus-flow/internal/model"
    "pkg/errors"
    "pkg/logging"
)

// Bridge integrates with the Resilient Execution System
type Bridge struct {
    checkpointRepo CheckpointRepository
    consensusRepo  ConsensusRepository
    escalationRepo EscalationRepository
    logger         *logging.Logger
}

// CheckpointRepository handles checkpoint persistence
type CheckpointRepository interface {
    Create(ctx context.Context, checkpoint model.Checkpoint) error
    GetLatest(ctx context.Context, executionID string) (*model.Checkpoint, error)
    List(ctx context.Context, executionID string) ([]model.Checkpoint, error)
}

// NewBridge creates a new RES bridge
func NewBridge(
    checkpointRepo CheckpointRepository,
    consensusRepo ConsensusRepository,
    escalationRepo EscalationRepository,
    logger *logging.Logger,
) *Bridge {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing RES Bridge",
        "file", file,
        "line", line,
    )
    
    return &Bridge{
        checkpointRepo: checkpointRepo,
        consensusRepo:  consensusRepo,
        escalationRepo: escalationRepo,
        logger:         logger,
    }
}

// ExecuteWithResilience wraps block execution with RES protections
func (b *Bridge) ExecuteWithResilience(
    ctx context.Context,
    blk block.Block,
    input block.BlockInput,
    config ResilienceConfig,
) (*block.BlockOutput, error) {
    _, file, line, _ := runtime.Caller(0)
    
    b.logger.Debug("Executing with resilience",
        "file", file,
        "line", line,
        "blockId", input.Config.ID,
        "blockType", blk.Type(),
    )
    
    var lastErr error
    var output *block.BlockOutput
    
    // Adaptive retry loop
    for attempt := 0; attempt <= config.MaxRetries; attempt++ {
        if attempt > 0 {
            // Apply backoff
            delay := calculateBackoff(attempt, config)
            select {
            case <-ctx.Done():
                return nil, ctx.Err()
            case <-time.After(delay):
            }
            
            b.logger.Debug("Retrying block execution",
                "file", file,
                "line", line,
                "attempt", attempt,
                "delay", delay,
            )
        }
        
        // Create checkpoint before execution
        if config.EnableCheckpoints {
            checkpoint := model.Checkpoint{
                ExecutionID: string(input.Context.ExecutionID),
                BlockID:     input.Config.ID,
                State:       input.Data,
                Attempt:     attempt,
                CreatedAt:   time.Now(),
            }
            
            if err := b.checkpointRepo.Create(ctx, checkpoint); err != nil {
                b.logger.Warn("Failed to create checkpoint",
                    "file", file,
                    "line", line,
                    "error", err,
                )
            }
        }
        
        // Execute block
        output, lastErr = blk.Execute(ctx, input)
        
        if lastErr == nil {
            return output, nil
        }
        
        // Check if error is retryable
        if !isRetryableError(lastErr, config) {
            b.logger.Warn("Non-retryable error",
                "file", file,
                "line", line,
                "error", lastErr,
            )
            break
        }
        
        // Self-correction: Try alternative approach
        if config.EnableSelfCorrection && attempt > 0 {
            corrected, corrErr := b.attemptSelfCorrection(ctx, blk, input, lastErr)
            if corrErr == nil {
                return corrected, nil
            }
        }
    }
    
    // Multi-model consensus for critical blocks
    if config.EnableConsensus && config.IsCritical {
        consensusOutput, err := b.executeWithConsensus(ctx, blk, input)
        if err == nil {
            return consensusOutput, nil
        }
    }
    
    // Escalate to human if configured
    if config.EnableEscalation {
        return b.escalateToHuman(ctx, input, lastErr)
    }
    
    return nil, errors.Wrap(lastErr, errors.CodeInternalError,
        "Block execution failed after retries",
        "file", file,
        "line", line,
        "attempts", config.MaxRetries+1,
    )
}

// executeWithConsensus uses multi-model voting
func (b *Bridge) executeWithConsensus(
    ctx context.Context,
    blk block.Block,
    input block.BlockInput,
) (*block.BlockOutput, error) {
    _, file, line, _ := runtime.Caller(0)
    
    b.logger.Info("Executing with multi-model consensus",
        "file", file,
        "line", line,
        "blockId", input.Config.ID,
    )
    
    // Execute with multiple models and vote
    // Implementation depends on AI-Bridge multi-model support
    
    return nil, errors.New(errors.CodeNotImplemented,
        "Consensus execution not implemented",
        "file", file,
        "line", line,
    )
}

// escalateToHuman triggers human escalation
func (b *Bridge) escalateToHuman(
    ctx context.Context,
    input block.BlockInput,
    originalErr error,
) (*block.BlockOutput, error) {
    _, file, line, _ := runtime.Caller(0)
    
    b.logger.Info("Escalating to human",
        "file", file,
        "line", line,
        "blockId", input.Config.ID,
        "error", originalErr,
    )
    
    escalation := model.EscalationRequest{
        ExecutionID: string(input.Context.ExecutionID),
        BlockID:     input.Config.ID,
        Reason:      originalErr.Error(),
        State:       input.Data,
        CreatedAt:   time.Now(),
    }
    
    if err := b.escalationRepo.Create(ctx, escalation); err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "Failed to create escalation request",
            "file", file,
            "line", line,
        )
    }
    
    // Return waiting status - execution pauses until human responds
    return nil, ErrAwaitingEscalation
}

// ResilienceConfig defines RES behavior
type ResilienceConfig struct {
    MaxRetries           int
    InitialDelay         time.Duration
    MaxDelay             time.Duration
    BackoffFactor        float64
    RetryableErrors      []int
    EnableCheckpoints    bool
    EnableSelfCorrection bool
    EnableConsensus      bool
    EnableEscalation     bool
    IsCritical           bool
}

// DefaultResilienceConfig returns default configuration
func DefaultResilienceConfig() ResilienceConfig {
    return ResilienceConfig{
        MaxRetries:           3,
        InitialDelay:         1 * time.Second,
        MaxDelay:             30 * time.Second,
        BackoffFactor:        2.0,
        EnableCheckpoints:    true,
        EnableSelfCorrection: true,
        EnableEscalation:     true,
    }
}
```

---

## 8. Database Schema

```sql
-- migrations/nexus-flow/001_create_pipelines.sql
CREATE TABLE IF NOT EXISTS Pipelines (
    ID          TEXT PRIMARY KEY,
    ProjectID   TEXT NOT NULL,
    Name        TEXT NOT NULL,
    Description TEXT,
    Definition  TEXT NOT NULL,  -- JSON pipeline definition
    Version     INTEGER NOT NULL DEFAULT 1,
    Status      TEXT NOT NULL DEFAULT 'draft',  -- draft, active, archived
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    
    UNIQUE(ProjectID, Name, Version)
);

CREATE INDEX idx_pipelines_project ON Pipelines(ProjectID);
CREATE INDEX idx_pipelines_status ON Pipelines(Status);

-- migrations/nexus-flow/002_create_executions.sql
CREATE TABLE IF NOT EXISTS Executions (
    ID          TEXT PRIMARY KEY,
    PipelineID  TEXT NOT NULL REFERENCES Pipelines(ID),
    ProjectID   TEXT NOT NULL,
    Status      TEXT NOT NULL DEFAULT 'pending',  -- pending, running, completed, failed, canceled
    Input       TEXT,           -- JSON input data
    Output      TEXT,           -- JSON output data
    Error       TEXT,           -- Error details if failed
    StartedAt   TEXT,
    CompletedAt TEXT,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (PipelineID) REFERENCES Pipelines(ID)
);

CREATE INDEX idx_executions_pipeline ON Executions(PipelineID);
CREATE INDEX idx_executions_status ON Executions(Status);
CREATE INDEX idx_executions_project ON Executions(ProjectID);

-- migrations/nexus-flow/003_create_checkpoints.sql
CREATE TABLE IF NOT EXISTS ExecutionCheckpoints (
    ID          TEXT PRIMARY KEY,
    ExecutionID TEXT NOT NULL REFERENCES Executions(ID),
    BlockID     TEXT NOT NULL,
    State       TEXT NOT NULL,  -- JSON state snapshot
    Attempt     INTEGER NOT NULL DEFAULT 0,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (ExecutionID) REFERENCES Executions(ID) ON DELETE CASCADE
);

CREATE INDEX idx_checkpoints_execution ON ExecutionCheckpoints(ExecutionID);

-- migrations/nexus-flow/004_create_telemetry.sql
CREATE TABLE IF NOT EXISTS ExecutionTelemetry (
    ID          TEXT PRIMARY KEY,
    ExecutionID TEXT NOT NULL REFERENCES Executions(ID),
    BlockID     TEXT NOT NULL,
    BlockType   TEXT NOT NULL,
    Status      TEXT NOT NULL,
    DurationMs  INTEGER,
    TokensUsed  INTEGER,
    RetryCount  INTEGER DEFAULT 0,
    Error       TEXT,
    Metadata    TEXT,  -- JSON metadata
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (ExecutionID) REFERENCES Executions(ID) ON DELETE CASCADE
);

CREATE INDEX idx_telemetry_execution ON ExecutionTelemetry(ExecutionID);
CREATE INDEX idx_telemetry_block ON ExecutionTelemetry(BlockID);

-- migrations/nexus-flow/005_create_escalations.sql
CREATE TABLE IF NOT EXISTS EscalationRequests (
    ID          TEXT PRIMARY KEY,
    ExecutionID TEXT NOT NULL REFERENCES Executions(ID),
    BlockID     TEXT NOT NULL,
    Reason      TEXT NOT NULL,
    State       TEXT NOT NULL,  -- JSON state at escalation
    Response    TEXT,           -- JSON human response
    Status      TEXT NOT NULL DEFAULT 'pending',  -- pending, responded, timeout
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    RespondedAt TEXT,
    
    FOREIGN KEY (ExecutionID) REFERENCES Executions(ID) ON DELETE CASCADE
);

CREATE INDEX idx_escalations_execution ON EscalationRequests(ExecutionID);
CREATE INDEX idx_escalations_status ON EscalationRequests(Status);
```

---

## 9. Error Codes

Nexus-Flow uses error code range **10xxx**:

| Code | Name | Description |
|------|------|-------------|
| 10001 | `ErrPipelineNotFound` | Pipeline not found |
| 10002 | `ErrPipelineInvalid` | Invalid pipeline definition |
| 10003 | `ErrBlockNotFound` | Block not found in pipeline |
| 10004 | `ErrBlockExecutionFailed` | Block execution failed |
| 10005 | `ErrConditionEvalFailed` | Branch condition evaluation failed |
| 10006 | `ErrLoopLimitExceeded` | Loop iteration limit exceeded |
| 10007 | `ErrCheckpointFailed` | Checkpoint creation failed |
| 10008 | `ErrEscalationTimeout` | Human escalation timed out |
| 10009 | `ErrExecutionCanceled` | Execution was canceled |
| 10010 | `ErrConcurrencyLimit` | Concurrency limit exceeded |

---

## 10. Configuration

```yaml
# config/nexus-flow.yaml
service:
  name: nexus-flow
  port: 8085
  host: "0.0.0.0"

websocket:
  readBufferSize: 1024
  writeBufferSize: 1024
  pingInterval: 30s
  pongTimeout: 60s
  maxMessageSize: 1048576  # 1MB

execution:
  defaultTimeout: 30m
  maxConcurrency: 10
  maxLoopIterations: 1000

resilience:
  maxRetries: 3
  initialDelay: 1s
  maxDelay: 30s
  backoffFactor: 2.0
  enableCheckpoints: true
  enableSelfCorrection: true
  enableEscalation: true

database:
  path: "./data/nexus-flow.db"
  maxOpenConns: 25

services:
  aibridge:
    url: "http://localhost:8082"
    timeout: 30s
  scout:
    url: "http://localhost:8084"
    timeout: 10s
  specmanager:
    url: "http://localhost:8081"
    timeout: 10s

logging:
  level: "info"
  format: "json"
  addSource: true  # MANDATORY: Include function names and line numbers
```

---

## 11. References

- Memory: `features/automation-pipeline` (Nexus-Flow core design)
- Memory: `features/resilient-execution-system` (RES integration)
- Memory: `features/ai-plan-mode` (Plan Mode workflow)
- Phase 5: AI-Bridge Specification (`04-ai-bridge.md`)
- Phase 6: Scout Specification (`05-scout.md`)
