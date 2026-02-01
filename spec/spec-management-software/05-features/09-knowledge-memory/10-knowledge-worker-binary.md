# 36. Knowledge Worker Binary Specification

> **Version:** 1.0.0  
> **Last Updated:** 2026-01-28  
> **Status:** Draft  
> **Dependencies:** 33-knowledge-memory-system.md, 09-seeding-configuration.md, 66-shared-constants.md (Error codes)  
> **Related:** 35-pattern-validator-tests.md, 33-knowledge-validator-tests.md

---

## 36.1 Overview

The Knowledge Worker is a standalone Go binary that handles heavy knowledge ingestion tasks (URL crawling, file processing, chunking, embedding generation) asynchronously. It communicates with the main application via SQLite status tables, enabling scalable processing without blocking the UI.

### 36.1.1 Architecture Position

```
┌─────────────────────────────────────────────────────────────────┐
│                     Main Application                             │
│  ┌─────────────┐    ┌──────────────┐    ┌───────────────────┐   │
│  │ REST API    │───▶│ JobScheduler │───▶│ WorkerSpawner     │   │
│  └─────────────┘    └──────────────┘    └─────────┬─────────┘   │
│                                                    │             │
│  ┌─────────────────────────────────────────────────┼───────────┐│
│  │                    SQLite                       │           ││
│  │  ┌──────────────────────┐  ┌──────────────────┐│           ││
│  │  │ KnowledgeWorkerJobs  │  │ KnowledgeSources ││           ││
│  │  │ (IPC Status Table)   │  │ (Source Config)  ││           ││
│  │  └──────────────────────┘  └──────────────────┘│           ││
│  └─────────────────────────────────────────────────┼───────────┘│
└────────────────────────────────────────────────────┼─────────────┘
                                                     │
                    ┌────────────────────────────────┼────────────┐
                    │         Worker Processes       │            │
                    │  ┌─────────┐  ┌─────────┐  ┌───▼─────┐      │
                    │  │Worker 1 │  │Worker 2 │  │Worker 3 │      │
                    │  └─────────┘  └─────────┘  └─────────┘      │
                    │       │            │            │           │
                    │       ▼            ▼            ▼           │
                    │  ┌─────────────────────────────────────┐    │
                    │  │  spec_knowledge.db / url_knowledge.db│   │
                    │  └─────────────────────────────────────┘    │
                    └─────────────────────────────────────────────┘
```

---

## 36.2 Command-Line Interface

### 36.2.1 Binary Name and Location

```
knowledge-worker          # Binary name
${workDirectory}/bin/     # Default installation path
```

### 36.2.2 Command Structure

```bash
knowledge-worker [global-flags] <command> [command-flags] [arguments]
```

### 36.2.3 Global Flags

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--config` | `-c` | string | `config.json` | Path to configuration file |
| `--db` | `-d` | string | (required) | Path to main SQLite database |
| `--log-level` | `-l` | string | `info` | Log level: debug, info, warn, error |
| `--log-format` | | string | `json` | Log format: json, text |
| `--log-file` | | string | `stdout` | Log output file path |
| `--version` | `-v` | bool | false | Print version and exit |
| `--help` | `-h` | bool | false | Print help and exit |

### 36.2.4 Commands

#### `process` - Process a Knowledge Source Job

Primary command for processing knowledge ingestion jobs.

```bash
knowledge-worker process --job-id <uuid> [flags]
```

**Flags:**

| Flag | Short | Type | Required | Description |
|------|-------|------|----------|-------------|
| `--job-id` | `-j` | string | Yes | UUID of the job to process |
| `--source-id` | `-s` | string | No | Source ID (auto-detected from job) |
| `--status-interval` | | duration | `5s` | Status update interval |
| `--checkpoint-interval` | | duration | `30s` | Checkpoint save interval |

**Example:**

```bash
knowledge-worker process \
  --db /path/to/main.db \
  --job-id 550e8400-e29b-41d4-a716-446655440000 \
  --status-interval 3s \
  --log-level debug
```

#### `validate` - Validate Configuration

Validate configuration without processing.

```bash
knowledge-worker validate [flags]
```

**Flags:**

| Flag | Type | Description |
|------|------|-------------|
| `--source-id` | string | Validate specific source configuration |
| `--check-connectivity` | bool | Test database and network connectivity |

**Example:**

```bash
knowledge-worker validate \
  --db /path/to/main.db \
  --check-connectivity
```

#### `cleanup` - Clean Orphaned Data

Remove orphaned chunks and reclaim space.

```bash
knowledge-worker cleanup [flags]
```

**Flags:**

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--dry-run` | bool | false | Show what would be deleted |
| `--vacuum` | bool | true | Run VACUUM after cleanup |
| `--older-than` | duration | `24h` | Clean data older than duration |

#### `version` - Print Version Information

```bash
knowledge-worker version [--json]
```

**Output:**

```json
{
  "version": "1.0.0",
  "commit": "abc123def",
  "buildDate": "2026-01-28T10:00:00Z",
  "goVersion": "go1.21.0",
  "platform": "linux/amd64"
}
```

---

## 36.3 Configuration Parsing

### 36.3.1 Configuration Sources (Priority Order)

1. **Command-line flags** (highest priority)
2. **Environment variables** (prefix: `KNOWLEDGE_WORKER_`)
3. **Configuration file** (JSON)
4. **Compiled defaults** (lowest priority)

### 36.3.2 Configuration File Structure

```json
{
  "worker": {
    "id": "worker-001",
    "statusUpdateIntervalMs": 5000,
    "checkpointIntervalMs": 30000,
    "maxRetries": 3,
    "retryBackoffMs": 1000
  },
  "database": {
    "mainDbPath": "/path/to/main.db",
    "specKnowledgeDbPath": "/path/to/spec_knowledge.db",
    "urlKnowledgeDbPath": "/path/to/url_knowledge.db",
    "connectionPoolSize": 5,
    "busyTimeoutMs": 30000,
    "walMode": true
  },
  "crawler": {
    "defaultMaxDepth": 3,
    "maxPages": 100,
    "requestDelayMs": 1000,
    "requestTimeoutMs": 30000,
    "respectRobotsTxt": true,
    "userAgent": "KnowledgeWorker/1.0",
    "maxConcurrentRequests": 5,
    "maxRedirects": 10,
    "retryCount": 3,
    "retryDelayMs": 2000
  },
  "chunker": {
    "maxChunkTokens": 512,
    "overlapTokens": 50,
    "minChunkTokens": 100,
    "preserveHeadings": true,
    "preserveCodeBlocks": true
  },
  "embedding": {
    "model": "all-MiniLM-L6-v2",
    "dimension": 384,
    "batchSize": 32,
    "serverUrl": "http://localhost:11434",
    "timeoutMs": 60000
  },
  "security": {
    "allowedSchemes": ["http", "https"],
    "blockedNetworks": [
      "10.0.0.0/8",
      "172.16.0.0/12",
      "192.168.0.0/16",
      "127.0.0.0/8",
      "169.254.0.0/16",
      "::1/128",
      "fc00::/7",
      "fe80::/10"
    ],
    "maxFileSizeBytes": 10485760,
    "allowedFileExtensions": [".md", ".txt", ".html", ".htm"]
  },
  "logging": {
    "level": "info",
    "format": "json",
    "file": "stdout",
    "includeStackTrace": true,
    "redactSecrets": true
  }
}
```

### 36.3.3 Environment Variable Mapping

Environment variables use `KNOWLEDGE_WORKER_` prefix with underscore-separated paths:

| Config Path | Environment Variable |
|-------------|---------------------|
| `worker.statusUpdateIntervalMs` | `KNOWLEDGE_WORKER_WORKER_STATUS_UPDATE_INTERVAL_MS` |
| `database.mainDbPath` | `KNOWLEDGE_WORKER_DATABASE_MAIN_DB_PATH` |
| `crawler.maxPages` | `KNOWLEDGE_WORKER_CRAWLER_MAX_PAGES` |
| `embedding.model` | `KNOWLEDGE_WORKER_EMBEDDING_MODEL` |
| `logging.level` | `KNOWLEDGE_WORKER_LOGGING_LEVEL` |

### 36.3.4 Configuration Loader Implementation

```go
// config/loader.go

package config

import (
    "encoding/json"
    "os"
    "reflect"
    "strconv"
    "strings"
    "time"
)

// Config represents the complete worker configuration
type Config struct {
    Worker    WorkerConfig    `json:"worker"`
    Database  DatabaseConfig  `json:"database"`
    Crawler   CrawlerConfig   `json:"crawler"`
    Chunker   ChunkerConfig   `json:"chunker"`
    Embedding EmbeddingConfig `json:"embedding"`
    Security  SecurityConfig  `json:"security"`
    Logging   LoggingConfig   `json:"logging"`
}

// WorkerConfig contains worker-specific settings
type WorkerConfig struct {
    ID                     string        `json:"id" env:"ID"`
    StatusUpdateIntervalMs int           `json:"statusUpdateIntervalMs" env:"STATUS_UPDATE_INTERVAL_MS" default:"5000"`
    CheckpointIntervalMs   int           `json:"checkpointIntervalMs" env:"CHECKPOINT_INTERVAL_MS" default:"30000"`
    MaxRetries             int           `json:"maxRetries" env:"MAX_RETRIES" default:"3"`
    RetryBackoffMs         int           `json:"retryBackoffMs" env:"RETRY_BACKOFF_MS" default:"1000"`
}

// DatabaseConfig contains database connection settings
type DatabaseConfig struct {
    MainDbPath           string `json:"mainDbPath" env:"MAIN_DB_PATH" required:"true"`
    SpecKnowledgeDbPath  string `json:"specKnowledgeDbPath" env:"SPEC_KNOWLEDGE_DB_PATH"`
    UrlKnowledgeDbPath   string `json:"urlKnowledgeDbPath" env:"URL_KNOWLEDGE_DB_PATH"`
    ConnectionPoolSize   int    `json:"connectionPoolSize" env:"CONNECTION_POOL_SIZE" default:"5"`
    BusyTimeoutMs        int    `json:"busyTimeoutMs" env:"BUSY_TIMEOUT_MS" default:"30000"`
    WalMode              bool   `json:"walMode" env:"WAL_MODE" default:"true"`
}

// CrawlerConfig contains URL crawler settings
type CrawlerConfig struct {
    DefaultMaxDepth       int      `json:"defaultMaxDepth" env:"DEFAULT_MAX_DEPTH" default:"3"`
    MaxPages              int      `json:"maxPages" env:"MAX_PAGES" default:"100"`
    RequestDelayMs        int      `json:"requestDelayMs" env:"REQUEST_DELAY_MS" default:"1000"`
    RequestTimeoutMs      int      `json:"requestTimeoutMs" env:"REQUEST_TIMEOUT_MS" default:"30000"`
    RespectRobotsTxt      bool     `json:"respectRobotsTxt" env:"RESPECT_ROBOTS_TXT" default:"true"`
    UserAgent             string   `json:"userAgent" env:"USER_AGENT" default:"KnowledgeWorker/1.0"`
    MaxConcurrentRequests int      `json:"maxConcurrentRequests" env:"MAX_CONCURRENT_REQUESTS" default:"5"`
    MaxRedirects          int      `json:"maxRedirects" env:"MAX_REDIRECTS" default:"10"`
    RetryCount            int      `json:"retryCount" env:"RETRY_COUNT" default:"3"`
    RetryDelayMs          int      `json:"retryDelayMs" env:"RETRY_DELAY_MS" default:"2000"`
}

// ChunkerConfig contains semantic chunking settings
type ChunkerConfig struct {
    MaxChunkTokens     int  `json:"maxChunkTokens" env:"MAX_CHUNK_TOKENS" default:"512"`
    OverlapTokens      int  `json:"overlapTokens" env:"OVERLAP_TOKENS" default:"50"`
    MinChunkTokens     int  `json:"minChunkTokens" env:"MIN_CHUNK_TOKENS" default:"100"`
    PreserveHeadings   bool `json:"preserveHeadings" env:"PRESERVE_HEADINGS" default:"true"`
    PreserveCodeBlocks bool `json:"preserveCodeBlocks" env:"PRESERVE_CODE_BLOCKS" default:"true"`
}

// EmbeddingConfig contains embedding generation settings
type EmbeddingConfig struct {
    Model     string `json:"model" env:"MODEL" default:"all-MiniLM-L6-v2"`
    Dimension int    `json:"dimension" env:"DIMENSION" default:"384"`
    BatchSize int    `json:"batchSize" env:"BATCH_SIZE" default:"32"`
    ServerUrl string `json:"serverUrl" env:"SERVER_URL" default:"http://localhost:11434"`
    TimeoutMs int    `json:"timeoutMs" env:"TIMEOUT_MS" default:"60000"`
}

// SecurityConfig contains security validation settings
type SecurityConfig struct {
    AllowedSchemes        []string `json:"allowedSchemes" env:"ALLOWED_SCHEMES" default:"http,https"`
    BlockedNetworks       []string `json:"blockedNetworks"`
    MaxFileSizeBytes      int64    `json:"maxFileSizeBytes" env:"MAX_FILE_SIZE_BYTES" default:"10485760"`
    AllowedFileExtensions []string `json:"allowedFileExtensions" default:".md,.txt,.html,.htm"`
}

// LoggingConfig contains logging settings
type LoggingConfig struct {
    Level             string `json:"level" env:"LEVEL" default:"info"`
    Format            string `json:"format" env:"FORMAT" default:"json"`
    File              string `json:"file" env:"FILE" default:"stdout"`
    IncludeStackTrace bool   `json:"includeStackTrace" env:"INCLUDE_STACK_TRACE" default:"true"`
    RedactSecrets     bool   `json:"redactSecrets" env:"REDACT_SECRETS" default:"true"`
}

// LoadConfig loads configuration from file, environment, and defaults
func LoadConfig(configPath string, cliOverrides map[string]interface{}) (*Config, error) {
    cfg := &Config{}
    
    // Step 1: Apply compiled defaults
    applyDefaults(cfg)
    
    // Step 2: Load from configuration file
    if configPath != "" {
        if err := loadFromFile(cfg, configPath); err != nil {
            return nil, fmt.Errorf("loading config file: %w", err)
        }
    }
    
    // Step 3: Apply environment variables
    applyEnvironment(cfg, "KNOWLEDGE_WORKER")
    
    // Step 4: Apply CLI overrides
    if err := applyOverrides(cfg, cliOverrides); err != nil {
        return nil, fmt.Errorf("applying CLI overrides: %w", err)
    }
    
    // Step 5: Validate configuration
    if err := cfg.Validate(); err != nil {
        return nil, fmt.Errorf("validating config: %w", err)
    }
    
    return cfg, nil
}

// Validate checks configuration for required fields and valid values
func (c *Config) Validate() error {
    var errors []string
    
    // Required fields
    if c.Database.MainDbPath == "" {
        errors = append(errors, "database.mainDbPath is required")
    }
    
    // Value constraints
    if c.Worker.StatusUpdateIntervalMs < 1000 {
        errors = append(errors, "worker.statusUpdateIntervalMs must be >= 1000")
    }
    if c.Chunker.MaxChunkTokens < 100 {
        errors = append(errors, "chunker.maxChunkTokens must be >= 100")
    }
    if c.Chunker.OverlapTokens >= c.Chunker.MaxChunkTokens {
        errors = append(errors, "chunker.overlapTokens must be < maxChunkTokens")
    }
    if c.Embedding.Dimension < 1 {
        errors = append(errors, "embedding.dimension must be >= 1")
    }
    
    // Security validation
    if len(c.Security.AllowedSchemes) == 0 {
        errors = append(errors, "security.allowedSchemes cannot be empty")
    }
    
    if len(errors) > 0 {
        return fmt.Errorf("configuration errors: %s", strings.Join(errors, "; "))
    }
    
    return nil
}
```

### 36.3.5 Configuration Validation Rules

| Field | Rule | Error Code |
|-------|------|------------|
| `database.mainDbPath` | Required, must exist | `ERR_CONFIG_REQUIRED` |
| `worker.statusUpdateIntervalMs` | >= 1000 | `ERR_CONFIG_RANGE` |
| `crawler.requestDelayMs` | >= 100 | `ERR_CONFIG_RANGE` |
| `chunker.overlapTokens` | < maxChunkTokens | `ERR_CONFIG_DEPENDENCY` |
| `security.blockedNetworks` | Valid CIDR notation | `ERR_CONFIG_FORMAT` |
| `embedding.dimension` | Match model output | `ERR_CONFIG_MISMATCH` |

---

## 36.4 Graceful Shutdown Handling

### 36.4.1 Signal Handling

The worker responds to the following signals:

| Signal | Action |
|--------|--------|
| `SIGTERM` | Initiate graceful shutdown |
| `SIGINT` | Initiate graceful shutdown |
| `SIGQUIT` | Immediate shutdown with stack dump |
| `SIGHUP` | Reload configuration (hot reload) |

### 36.4.2 Shutdown Sequence

```
┌─────────────────────────────────────────────────────────────────┐
│                    Graceful Shutdown Flow                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  SIGTERM/SIGINT received                                         │
│         │                                                        │
│         ▼                                                        │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Phase 1: Stop Accepting New Work (immediate)             │   │
│  │  - Set shutdown flag                                      │   │
│  │  - Stop processing queue                                  │   │
│  │  - Cancel pending HTTP requests                           │   │
│  └──────────────────────────────────────────────────────────┘   │
│         │                                                        │
│         ▼                                                        │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Phase 2: Complete In-Flight Operations (up to 30s)       │   │
│  │  - Finish current chunk processing                        │   │
│  │  - Complete pending embedding batches                     │   │
│  │  - Flush write buffers                                    │   │
│  └──────────────────────────────────────────────────────────┘   │
│         │                                                        │
│         ▼                                                        │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Phase 3: Save Checkpoint (up to 10s)                     │   │
│  │  - Write progress to KnowledgeWorkerJobs                  │   │
│  │  - Save partial results                                   │   │
│  │  - Update status to 'interrupted'                         │   │
│  └──────────────────────────────────────────────────────────┘   │
│         │                                                        │
│         ▼                                                        │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Phase 4: Cleanup Resources (up to 5s)                    │   │
│  │  - Close database connections                             │   │
│  │  - Release file handles                                   │   │
│  │  - Flush logs                                             │   │
│  └──────────────────────────────────────────────────────────┘   │
│         │                                                        │
│         ▼                                                        │
│  Exit with code 0 (graceful) or 130 (interrupted)               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 36.4.3 Shutdown Manager Implementation

```go
// shutdown/manager.go

package shutdown

import (
    "context"
    "os"
    "os/signal"
    "sync"
    "syscall"
    "time"
    
    "go.uber.org/zap"
)

// ShutdownPhase represents a shutdown phase
type ShutdownPhase int

const (
    PhaseRunning ShutdownPhase = iota
    PhaseStopAccepting
    PhaseCompleteInFlight
    PhaseSaveCheckpoint
    PhaseCleanup
    PhaseTerminated
)

// ShutdownConfig contains shutdown timing configuration
type ShutdownConfig struct {
    StopAcceptingTimeout   time.Duration
    CompleteInFlightTimeout time.Duration
    SaveCheckpointTimeout  time.Duration
    CleanupTimeout         time.Duration
    ForceKillTimeout       time.Duration
}

// DefaultShutdownConfig returns sensible defaults
func DefaultShutdownConfig() ShutdownConfig {
    return ShutdownConfig{
        StopAcceptingTimeout:   5 * time.Second,
        CompleteInFlightTimeout: 30 * time.Second,
        SaveCheckpointTimeout:  10 * time.Second,
        CleanupTimeout:         5 * time.Second,
        ForceKillTimeout:       60 * time.Second,
    }
}

// ShutdownHandler is a function called during shutdown
type ShutdownHandler func(ctx context.Context) error

// Manager coordinates graceful shutdown
type Manager struct {
    config     ShutdownConfig
    logger     *zap.Logger
    phase      ShutdownPhase
    phaseMu    sync.RWMutex
    handlers   map[ShutdownPhase][]ShutdownHandler
    handlersMu sync.RWMutex
    done       chan struct{}
    
    // Cancellation
    rootCtx    context.Context
    rootCancel context.CancelFunc
}

// NewManager creates a new shutdown manager
func NewManager(logger *zap.Logger, config ShutdownConfig) *Manager {
    ctx, cancel := context.WithCancel(context.Background())
    
    return &Manager{
        config:     config,
        logger:     logger,
        phase:      PhaseRunning,
        handlers:   make(map[ShutdownPhase][]ShutdownHandler),
        done:       make(chan struct{}),
        rootCtx:    ctx,
        rootCancel: cancel,
    }
}

// RegisterHandler registers a handler for a specific shutdown phase
func (m *Manager) RegisterHandler(phase ShutdownPhase, handler ShutdownHandler) {
    m.handlersMu.Lock()
    defer m.handlersMu.Unlock()
    m.handlers[phase] = append(m.handlers[phase], handler)
}

// Context returns the root context that will be cancelled on shutdown
func (m *Manager) Context() context.Context {
    return m.rootCtx
}

// IsShuttingDown returns true if shutdown has been initiated
func (m *Manager) IsShuttingDown() bool {
    m.phaseMu.RLock()
    defer m.phaseMu.RUnlock()
    return m.phase != PhaseRunning
}

// CurrentPhase returns the current shutdown phase
func (m *Manager) CurrentPhase() ShutdownPhase {
    m.phaseMu.RLock()
    defer m.phaseMu.RUnlock()
    return m.phase
}

// ListenForSignals starts listening for shutdown signals
func (m *Manager) ListenForSignals() {
    sigChan := make(chan os.Signal, 1)
    signal.Notify(sigChan, syscall.SIGTERM, syscall.SIGINT, syscall.SIGQUIT, syscall.SIGHUP)
    
    go func() {
        for sig := range sigChan {
            switch sig {
            case syscall.SIGTERM, syscall.SIGINT:
                m.logger.Info("Received shutdown signal", zap.String("signal", sig.String()))
                go m.Shutdown(false)
            case syscall.SIGQUIT:
                m.logger.Warn("Received SIGQUIT, performing immediate shutdown")
                go m.Shutdown(true)
            case syscall.SIGHUP:
                m.logger.Info("Received SIGHUP, reloading configuration")
                // Emit reload event (implementation depends on config manager)
            }
        }
    }()
}

// Shutdown initiates the shutdown sequence
func (m *Manager) Shutdown(immediate bool) {
    m.phaseMu.Lock()
    if m.phase != PhaseRunning {
        m.phaseMu.Unlock()
        return // Already shutting down
    }
    m.phase = PhaseStopAccepting
    m.phaseMu.Unlock()
    
    defer close(m.done)
    
    if immediate {
        m.logger.Warn("Performing immediate shutdown")
        m.rootCancel()
        m.runPhase(PhaseCleanup, m.config.CleanupTimeout)
        return
    }
    
    // Phase 1: Stop accepting new work
    m.logger.Info("Phase 1: Stopping new work acceptance")
    m.rootCancel() // Cancel root context
    m.runPhase(PhaseStopAccepting, m.config.StopAcceptingTimeout)
    
    // Phase 2: Complete in-flight operations
    m.setPhase(PhaseCompleteInFlight)
    m.logger.Info("Phase 2: Completing in-flight operations")
    m.runPhase(PhaseCompleteInFlight, m.config.CompleteInFlightTimeout)
    
    // Phase 3: Save checkpoint
    m.setPhase(PhaseSaveCheckpoint)
    m.logger.Info("Phase 3: Saving checkpoint")
    m.runPhase(PhaseSaveCheckpoint, m.config.SaveCheckpointTimeout)
    
    // Phase 4: Cleanup resources
    m.setPhase(PhaseCleanup)
    m.logger.Info("Phase 4: Cleaning up resources")
    m.runPhase(PhaseCleanup, m.config.CleanupTimeout)
    
    m.setPhase(PhaseTerminated)
    m.logger.Info("Shutdown complete")
}

// Wait blocks until shutdown is complete
func (m *Manager) Wait() {
    <-m.done
}

func (m *Manager) setPhase(phase ShutdownPhase) {
    m.phaseMu.Lock()
    defer m.phaseMu.Unlock()
    m.phase = phase
}

func (m *Manager) runPhase(phase ShutdownPhase, timeout time.Duration) {
    m.handlersMu.RLock()
    handlers := m.handlers[phase]
    m.handlersMu.RUnlock()
    
    if len(handlers) == 0 {
        return
    }
    
    ctx, cancel := context.WithTimeout(context.Background(), timeout)
    defer cancel()
    
    var wg sync.WaitGroup
    for i, handler := range handlers {
        wg.Add(1)
        go func(idx int, h ShutdownHandler) {
            defer wg.Done()
            if err := h(ctx); err != nil {
                m.logger.Error("Shutdown handler failed",
                    zap.Int("phase", int(phase)),
                    zap.Int("handler", idx),
                    zap.Error(err))
            }
        }(i, handler)
    }
    
    // Wait for handlers or timeout
    done := make(chan struct{})
    go func() {
        wg.Wait()
        close(done)
    }()
    
    select {
    case <-done:
        m.logger.Debug("Phase completed successfully", zap.Int("phase", int(phase)))
    case <-ctx.Done():
        m.logger.Warn("Phase timed out", 
            zap.Int("phase", int(phase)),
            zap.Duration("timeout", timeout))
    }
}
```

### 36.4.4 Checkpoint Data Structure

```go
// checkpoint/types.go

package checkpoint

import "time"

// Checkpoint represents the worker's progress state
type Checkpoint struct {
    JobID           string                 `json:"jobId"`
    SourceID        string                 `json:"sourceId"`
    WorkerID        string                 `json:"workerId"`
    
    // Progress tracking
    ProcessedItems  int                    `json:"processedItems"`
    TotalItems      int                    `json:"totalItems"`
    ProcessedBytes  int64                  `json:"processedBytes"`
    
    // Resume information
    LastProcessedURL  string               `json:"lastProcessedUrl,omitempty"`
    LastProcessedFile string               `json:"lastProcessedFile,omitempty"`
    PendingQueue      []string             `json:"pendingQueue,omitempty"`
    
    // Timing
    StartedAt         time.Time            `json:"startedAt"`
    LastUpdatedAt     time.Time            `json:"lastUpdatedAt"`
    EstimatedETA      *time.Time           `json:"estimatedEta,omitempty"`
    
    // State
    Phase             string               `json:"phase"`
    Status            string               `json:"status"`
    ErrorMessage      string               `json:"errorMessage,omitempty"`
    
    // Metrics
    Metrics           map[string]float64   `json:"metrics,omitempty"`
}

// CheckpointManager handles checkpoint persistence
type CheckpointManager interface {
    Save(ctx context.Context, cp *Checkpoint) error
    Load(ctx context.Context, jobID string) (*Checkpoint, error)
    Delete(ctx context.Context, jobID string) error
}
```

### 36.4.5 Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success - job completed normally |
| 1 | General error |
| 2 | Configuration error |
| 3 | Database connection error |
| 4 | Job not found |
| 5 | Source configuration invalid |
| 64 | Usage error (invalid arguments) |
| 130 | Interrupted (SIGINT) |
| 143 | Terminated (SIGTERM) |

### 36.4.6 Error Handling

#### Error Categories

Errors are categorized following the project error code standards (see 66-shared-constants.md):

| Category | Code Range | Description |
|----------|------------|-------------|
| Configuration | 7xxx | Invalid config, missing required fields |
| Database | 3xxx | Connection failures, query errors, lock contention |
| Network | 4xxx | Crawler timeouts, DNS failures, connection refused |
| Validation | 1xxx | Invalid URLs, pattern errors, path traversal |
| Security | 8xxx | SSRF attempts, blocked networks, unauthorized access |
| Processing | 5xxx | Chunking failures, embedding errors, parsing errors |
| System | 9xxx | Memory exhaustion, disk full, unexpected panics |

#### Error Codes Reference

```go
// errors/codes.go

package errors

// Configuration errors (7xxx)
const (
    ERR_CONFIG_REQUIRED    = 7001 // Required configuration field missing
    ERR_CONFIG_RANGE       = 7002 // Value outside valid range
    ERR_CONFIG_FORMAT      = 7003 // Invalid format (e.g., CIDR notation)
    ERR_CONFIG_DEPENDENCY  = 7004 // Cross-field dependency violation
    ERR_CONFIG_MISMATCH    = 7005 // Value doesn't match expected (e.g., embedding dimension)
    ERR_CONFIG_FILE_READ   = 7006 // Cannot read configuration file
    ERR_CONFIG_PARSE       = 7007 // JSON parse error in config
)

// Database errors (3xxx)
const (
    ERR_DB_CONNECTION      = 3001 // Cannot connect to database
    ERR_DB_LOCKED          = 3002 // Database is locked
    ERR_DB_QUERY           = 3003 // Query execution failed
    ERR_DB_TRANSACTION     = 3004 // Transaction failed
    ERR_DB_CHECKPOINT_SAVE = 3005 // Cannot save checkpoint
    ERR_DB_CHECKPOINT_LOAD = 3006 // Cannot load checkpoint
    ERR_DB_SCHEMA          = 3007 // Schema mismatch or missing tables
)

// Network/External service errors (4xxx)
const (
    ERR_NET_TIMEOUT        = 4001 // Request timed out
    ERR_NET_DNS            = 4002 // DNS resolution failed
    ERR_NET_CONNECTION     = 4003 // Connection refused/reset
    ERR_NET_TLS            = 4004 // TLS/SSL handshake failed
    ERR_NET_HTTP           = 4005 // HTTP error (4xx/5xx response)
    ERR_NET_REDIRECT_LOOP  = 4006 // Too many redirects
    ERR_NET_ROBOTS_TXT     = 4007 // Blocked by robots.txt
    ERR_EMBEDDING_SERVICE  = 4008 // Embedding service unavailable
)

// Validation errors (1xxx)
const (
    ERR_VAL_URL_INVALID    = 1001 // Malformed URL
    ERR_VAL_URL_SCHEME     = 1002 // Invalid URL scheme
    ERR_VAL_PATH_TRAVERSAL = 1003 // Path traversal attempt detected
    ERR_VAL_PATTERN_SYNTAX = 1004 // Invalid regex syntax
    ERR_VAL_PATTERN_REDOS  = 1005 // Catastrophic backtracking detected
    ERR_VAL_FILE_TYPE      = 1006 // Unsupported file type
    ERR_VAL_FILE_SIZE      = 1007 // File exceeds size limit
)

// Security errors (8xxx)
const (
    ERR_SEC_SSRF           = 8001 // SSRF attempt - private network
    ERR_SEC_BLOCKED_IP     = 8002 // IP address is blocked
    ERR_SEC_METADATA       = 8003 // Cloud metadata endpoint blocked
    ERR_SEC_LOCALHOST      = 8004 // Localhost access blocked
    ERR_SEC_SYMLINK        = 8005 // Symlink outside allowed path
)

// Processing errors (5xxx)
const (
    ERR_PROC_PARSE_HTML    = 5001 // HTML parsing failed
    ERR_PROC_PARSE_MD      = 5002 // Markdown parsing failed
    ERR_PROC_CHUNK         = 5003 // Chunking algorithm failed
    ERR_PROC_EMBED         = 5004 // Embedding generation failed
    ERR_PROC_EXTRACT       = 5005 // Content extraction failed
    ERR_PROC_ENCODING      = 5006 // Character encoding error
)

// System errors (9xxx)
const (
    ERR_SYS_MEMORY         = 9001 // Out of memory
    ERR_SYS_DISK           = 9002 // Disk full or I/O error
    ERR_SYS_PANIC          = 9003 // Unexpected panic recovered
    ERR_SYS_SIGNAL         = 9004 // Unexpected signal received
)
```

#### Error Wrapping and Context

```go
// errors/wrapped.go

package errors

import (
    "fmt"
)

// WorkerError represents a categorized worker error
type WorkerError struct {
    Code       int               // Error code from constants
    Message    string            // Human-readable message
    Category   string            // Error category name
    Cause      error             // Underlying error
    Context    map[string]string // Additional context
    Retryable  bool              // Whether operation can be retried
    RetryAfter int               // Suggested retry delay in seconds
}

func (e *WorkerError) Error() string {
    if e.Cause != nil {
        return fmt.Sprintf("[%d] %s: %v", e.Code, e.Message, e.Cause)
    }
    return fmt.Sprintf("[%d] %s", e.Code, e.Message)
}

func (e *WorkerError) Unwrap() error {
    return e.Cause
}

// NewWorkerError creates a new categorized error
func NewWorkerError(code int, message string, cause error) *WorkerError {
    return &WorkerError{
        Code:      code,
        Message:   message,
        Category:  categoryFromCode(code),
        Cause:     cause,
        Context:   make(map[string]string),
        Retryable: isRetryable(code),
    }
}

// WithContext adds context to the error
func (e *WorkerError) WithContext(key, value string) *WorkerError {
    e.Context[key] = value
    return e
}

// WithRetryAfter sets retry delay for retryable errors
func (e *WorkerError) WithRetryAfter(seconds int) *WorkerError {
    e.RetryAfter = seconds
    return e
}

func categoryFromCode(code int) string {
    switch code / 1000 {
    case 1: return "validation"
    case 3: return "database"
    case 4: return "network"
    case 5: return "processing"
    case 7: return "configuration"
    case 8: return "security"
    case 9: return "system"
    default: return "unknown"
    }
}

func isRetryable(code int) bool {
    // Network and some database errors are retryable
    switch code {
    case ERR_NET_TIMEOUT, ERR_NET_CONNECTION, ERR_DB_LOCKED,
         ERR_EMBEDDING_SERVICE:
        return true
    default:
        return false
    }
}
```

#### Error Recovery Strategies

| Error Type | Recovery Strategy |
|------------|-------------------|
| `ERR_DB_LOCKED` | Exponential backoff (100ms → 200ms → 400ms), max 5 retries |
| `ERR_NET_TIMEOUT` | Retry with increased timeout, max 3 retries |
| `ERR_NET_CONNECTION` | Retry after delay, skip URL after 3 failures |
| `ERR_EMBEDDING_SERVICE` | Queue for retry, continue with other items |
| `ERR_PROC_*` | Log and skip item, continue processing |
| `ERR_SEC_*` | Log security event, reject immediately, no retry |
| `ERR_SYS_MEMORY` | Reduce batch size, trigger garbage collection |

#### Error Logging Format

```go
// All errors logged with structured fields
logger.Error("Processing failed",
    zap.Int("code", err.Code),
    zap.String("category", err.Category),
    zap.String("message", err.Message),
    zap.Bool("retryable", err.Retryable),
    zap.Any("context", err.Context),
    zap.String("jobId", jobID),
    zap.String("sourceId", sourceID),
    zap.String("currentItem", currentURL),
    zap.Error(err.Cause),
)
```

#### Error Aggregation

Errors are aggregated per job for reporting:

```go
// errors/aggregator.go

type ErrorSummary struct {
    TotalErrors    int            `json:"totalErrors"`
    ByCategory     map[string]int `json:"byCategory"`
    ByCode         map[int]int    `json:"byCode"`
    RetryableCount int            `json:"retryableCount"`
    FatalCount     int            `json:"fatalCount"`
    SampleErrors   []WorkerError  `json:"sampleErrors"` // First 10 unique errors
}
```

---

## 36.5 Job Processing Pipeline

### 36.5.1 Pipeline Stages

```go
// pipeline/processor.go

package pipeline

import (
    "context"
)

// Stage represents a processing stage
type Stage interface {
    Name() string
    Process(ctx context.Context, input <-chan Item, output chan<- Item) error
}

// Pipeline orchestrates the processing stages
type Pipeline struct {
    stages   []Stage
    shutdown *shutdown.Manager
    logger   *zap.Logger
}

// Stages for URL source processing:
// 1. URLFetcher     - Fetch and validate URLs
// 2. ContentExtractor - Extract text from HTML/documents  
// 3. Chunker        - Split into semantic chunks
// 4. Embedder       - Generate embeddings
// 5. StorageWriter  - Persist to vector database

// Stages for Spec source processing:
// 1. FileScanner    - Discover and filter files
// 2. ContentReader  - Read file contents
// 3. Chunker        - Split into semantic chunks
// 4. Embedder       - Generate embeddings
// 5. StorageWriter  - Persist to vector database
```

### 36.5.2 Progress Reporting

```go
// progress/reporter.go

package progress

import (
    "context"
    "sync"
    "time"
)

// Reporter handles progress updates to the database
type Reporter struct {
    db              *sql.DB
    jobID           string
    updateInterval  time.Duration
    
    mu              sync.Mutex
    processed       int
    total           int
    currentPhase    string
    lastError       error
    
    done            chan struct{}
}

// NewReporter creates a progress reporter
func NewReporter(db *sql.DB, jobID string, interval time.Duration) *Reporter {
    return &Reporter{
        db:             db,
        jobID:          jobID,
        updateInterval: interval,
        done:           make(chan struct{}),
    }
}

// Start begins periodic progress updates
func (r *Reporter) Start(ctx context.Context) {
    ticker := time.NewTicker(r.updateInterval)
    defer ticker.Stop()
    
    for {
        select {
        case <-ticker.C:
            r.flush(ctx)
        case <-ctx.Done():
            r.flush(context.Background()) // Final flush
            close(r.done)
            return
        }
    }
}

// IncrementProcessed atomically increments the processed count
func (r *Reporter) IncrementProcessed(delta int) {
    r.mu.Lock()
    r.processed += delta
    r.mu.Unlock()
}

// SetTotal sets the total item count
func (r *Reporter) SetTotal(total int) {
    r.mu.Lock()
    r.total = total
    r.mu.Unlock()
}

// SetPhase updates the current processing phase
func (r *Reporter) SetPhase(phase string) {
    r.mu.Lock()
    r.currentPhase = phase
    r.mu.Unlock()
}

// SetError records an error
func (r *Reporter) SetError(err error) {
    r.mu.Lock()
    r.lastError = err
    r.mu.Unlock()
}

func (r *Reporter) flush(ctx context.Context) error {
    r.mu.Lock()
    processed := r.processed
    total := r.total
    phase := r.currentPhase
    lastErr := r.lastError
    r.mu.Unlock()
    
    progress := float64(0)
    if total > 0 {
        progress = float64(processed) / float64(total) * 100
    }
    
    status := "processing"
    errMsg := ""
    if lastErr != nil {
        status = "error"
        errMsg = lastErr.Error()
    }
    
    query := `
        UPDATE KnowledgeWorkerJobs 
        SET 
            ProcessedItems = ?,
            TotalItems = ?,
            Progress = ?,
            Status = ?,
            Phase = ?,
            ErrorMessage = ?,
            UpdatedAt = ?
        WHERE ID = ?
    `
    
    _, err := r.db.ExecContext(ctx, query,
        processed, total, progress, status, phase, errMsg, time.Now(), r.jobID)
    
    return err
}

// Wait blocks until the reporter has stopped
func (r *Reporter) Wait() {
    <-r.done
}
```

---

## 36.6 Main Entry Point

### 36.6.1 Main Function Structure

```go
// main.go

package main

import (
    "fmt"
    "os"
    
    "github.com/spf13/cobra"
    "go.uber.org/zap"
    
    "knowledge-worker/config"
    "knowledge-worker/shutdown"
)

var (
    version   = "dev"
    commit    = "unknown"
    buildDate = "unknown"
)

func main() {
    // Initialize logger early
    logger, _ := zap.NewProduction()
    defer logger.Sync()
    
    // Build command tree
    rootCmd := buildRootCommand(logger)
    
    if err := rootCmd.Execute(); err != nil {
        logger.Error("Command execution failed", zap.Error(err))
        os.Exit(1)
    }
}

func buildRootCommand(logger *zap.Logger) *cobra.Command {
    var configPath string
    var dbPath string
    var logLevel string
    var logFormat string
    
    rootCmd := &cobra.Command{
        Use:   "knowledge-worker",
        Short: "Knowledge Memory System worker binary",
        Long: `A standalone worker that processes knowledge ingestion jobs
including URL crawling, file processing, chunking, and embedding generation.`,
        SilenceUsage: true,
    }
    
    // Global flags
    rootCmd.PersistentFlags().StringVarP(&configPath, "config", "c", "", "Configuration file path")
    rootCmd.PersistentFlags().StringVarP(&dbPath, "db", "d", "", "Main database path (required)")
    rootCmd.PersistentFlags().StringVarP(&logLevel, "log-level", "l", "info", "Log level")
    rootCmd.PersistentFlags().StringVar(&logFormat, "log-format", "json", "Log format (json|text)")
    
    // Commands
    rootCmd.AddCommand(buildProcessCommand(logger))
    rootCmd.AddCommand(buildValidateCommand(logger))
    rootCmd.AddCommand(buildCleanupCommand(logger))
    rootCmd.AddCommand(buildVersionCommand())
    
    return rootCmd
}

func buildProcessCommand(logger *zap.Logger) *cobra.Command {
    var jobID string
    var statusInterval string
    
    cmd := &cobra.Command{
        Use:   "process",
        Short: "Process a knowledge source job",
        RunE: func(cmd *cobra.Command, args []string) error {
            // Load configuration
            cfg, err := config.LoadConfig(
                cmd.Flag("config").Value.String(),
                map[string]interface{}{
                    "database.mainDbPath": cmd.Flag("db").Value.String(),
                },
            )
            if err != nil {
                return fmt.Errorf("loading configuration: %w", err)
            }
            
            // Initialize shutdown manager
            shutdownMgr := shutdown.NewManager(logger, shutdown.DefaultShutdownConfig())
            shutdownMgr.ListenForSignals()
            
            // Create and run processor
            processor, err := NewProcessor(cfg, logger, shutdownMgr)
            if err != nil {
                return fmt.Errorf("creating processor: %w", err)
            }
            
            // Register shutdown handlers
            shutdownMgr.RegisterHandler(shutdown.PhaseCompleteInFlight, processor.CompleteInFlight)
            shutdownMgr.RegisterHandler(shutdown.PhaseSaveCheckpoint, processor.SaveCheckpoint)
            shutdownMgr.RegisterHandler(shutdown.PhaseCleanup, processor.Cleanup)
            
            // Process job
            if err := processor.ProcessJob(shutdownMgr.Context(), jobID); err != nil {
                return fmt.Errorf("processing job: %w", err)
            }
            
            return nil
        },
    }
    
    cmd.Flags().StringVarP(&jobID, "job-id", "j", "", "Job ID to process (required)")
    cmd.Flags().StringVar(&statusInterval, "status-interval", "5s", "Status update interval")
    cmd.MarkFlagRequired("job-id")
    
    return cmd
}

func buildVersionCommand() *cobra.Command {
    var jsonOutput bool
    
    cmd := &cobra.Command{
        Use:   "version",
        Short: "Print version information",
        Run: func(cmd *cobra.Command, args []string) {
            if jsonOutput {
                fmt.Printf(`{"version":"%s","commit":"%s","buildDate":"%s"}`+"\n",
                    version, commit, buildDate)
            } else {
                fmt.Printf("knowledge-worker %s (commit: %s, built: %s)\n",
                    version, commit, buildDate)
            }
        },
    }
    
    cmd.Flags().BoolVar(&jsonOutput, "json", false, "Output as JSON")
    
    return cmd
}
```

---

## 36.7 Testing Requirements

### 36.7.1 Unit Tests

| Component | Coverage Target | Key Scenarios |
|-----------|----------------|---------------|
| Config Loader | 95% | Priority merging, env vars, validation |
| Shutdown Manager | 90% | Signal handling, phase timeouts, handlers |
| Progress Reporter | 85% | Concurrent updates, flush timing |
| Checkpoint Manager | 90% | Save/load/resume, corruption handling |

### 36.7.2 Integration Tests

```go
// tests/integration/shutdown_test.go

func TestGracefulShutdown_SavesCheckpoint(t *testing.T) {
    // Start worker with test job
    // Send SIGTERM
    // Verify checkpoint saved within timeout
    // Verify exit code 143
}

func TestShutdown_CompletesInFlightBatch(t *testing.T) {
    // Start worker processing batch
    // Send SIGTERM mid-batch
    // Verify batch completes
    // Verify no partial chunks
}

func TestConfigReload_SIGHUP(t *testing.T) {
    // Start worker
    // Modify config file
    // Send SIGHUP
    // Verify new config applied
}
```

---

## 36.8 Build and Distribution

### 36.8.1 Build Commands

```makefile
# Makefile

VERSION ?= $(shell git describe --tags --always)
COMMIT  ?= $(shell git rev-parse --short HEAD)
DATE    ?= $(shell date -u +"%Y-%m-%dT%H:%M:%SZ")

LDFLAGS := -ldflags "-X main.version=$(VERSION) -X main.commit=$(COMMIT) -X main.buildDate=$(DATE)"

.PHONY: build
build:
	go build $(LDFLAGS) -o bin/knowledge-worker ./cmd/worker

.PHONY: build-all
build-all:
	GOOS=linux GOARCH=amd64 go build $(LDFLAGS) -o bin/knowledge-worker-linux-amd64 ./cmd/worker
	GOOS=darwin GOARCH=amd64 go build $(LDFLAGS) -o bin/knowledge-worker-darwin-amd64 ./cmd/worker
	GOOS=darwin GOARCH=arm64 go build $(LDFLAGS) -o bin/knowledge-worker-darwin-arm64 ./cmd/worker
	GOOS=windows GOARCH=amd64 go build $(LDFLAGS) -o bin/knowledge-worker-windows-amd64.exe ./cmd/worker

.PHONY: test
test:
	go test -v -race -cover ./...
```

### 36.8.2 Docker Image

```dockerfile
# Dockerfile
FROM golang:1.21-alpine AS builder

WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=1 go build -ldflags="-s -w" -o /knowledge-worker ./cmd/worker

FROM alpine:3.19
RUN apk add --no-cache ca-certificates sqlite
COPY --from=builder /knowledge-worker /usr/local/bin/
ENTRYPOINT ["knowledge-worker"]
```

---

## 36.9 Appendix: Complete CLI Reference

```
knowledge-worker - Knowledge Memory System Worker

USAGE:
  knowledge-worker [global-flags] <command> [command-flags]

GLOBAL FLAGS:
  -c, --config string      Configuration file path
  -d, --db string          Main database path (required for most commands)
  -l, --log-level string   Log level: debug, info, warn, error (default "info")
      --log-format string  Log format: json, text (default "json")
      --log-file string    Log output file path (default "stdout")
  -v, --version            Print version and exit
  -h, --help               Print help

COMMANDS:
  process    Process a knowledge source job
  validate   Validate configuration
  cleanup    Clean orphaned data
  version    Print version information

EXAMPLES:
  # Process a job
  knowledge-worker process --db ./data/main.db --job-id abc-123

  # Validate configuration
  knowledge-worker validate --db ./data/main.db --check-connectivity

  # Clean up orphaned data (dry run)
  knowledge-worker cleanup --db ./data/main.db --dry-run

  # Show version
  knowledge-worker version --json
```
