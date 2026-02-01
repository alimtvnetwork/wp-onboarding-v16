# Build Runner CLI - Implementation Guide

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Overview

Step-by-step implementation guide for the Build Runner CLI (`brun`), providing dependency graphs, implementation order, and boilerplate code for each phase.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [CLI Interface](./02-cli-interface.md)
- [Configuration](./03-configuration.md)
- [Data Models](./10-data-models.md)
- [Testing Strategy](./13-testing-strategy.md)

---

## Dependency Graph

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          IMPLEMENTATION LAYERS                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Layer 5: CLI Commands                                                       │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐          │
│  │  build   │ │  check   │ │   run    │ │   port   │ │  config  │          │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘          │
│       │            │            │            │            │                  │
│       └────────────┴────────────┼────────────┴────────────┘                  │
│                                 │                                            │
│  Layer 4: Orchestration         ▼                                            │
│  ┌──────────────────────────────────────────────────────────────────┐       │
│  │                     Execution Engine                              │       │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐               │       │
│  │  │ProfileLoader│  │HealthChecker│  │ RunTracker  │               │       │
│  │  └─────────────┘  └─────────────┘  └─────────────┘               │       │
│  └────────────────────────────┬─────────────────────────────────────┘       │
│                               │                                              │
│  Layer 3: Runtime Executors   ▼                                              │
│  ┌──────────────────────────────────────────────────────────────────┐       │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐               │       │
│  │  │ PowerShell  │  │   Node.js   │  │   Golang    │               │       │
│  │  │  Executor   │  │  Executor   │  │  Executor   │               │       │
│  │  └─────────────┘  └─────────────┘  └─────────────┘               │       │
│  └────────────────────────────┬─────────────────────────────────────┘       │
│                               │                                              │
│  Layer 2: Support Services    ▼                                              │
│  ┌──────────────────────────────────────────────────────────────────┐       │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐               │       │
│  │  │PortManager  │  │ AssetCopier │  │ErrorCapture │               │       │
│  │  └─────────────┘  └─────────────┘  └─────────────┘               │       │
│  └────────────────────────────┬─────────────────────────────────────┘       │
│                               │                                              │
│  Layer 1: Foundation          ▼                                              │
│  ┌──────────────────────────────────────────────────────────────────┐       │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐               │       │
│  │  │   Config    │  │   Logger    │  │   Models    │               │       │
│  │  └─────────────┘  └─────────────┘  └─────────────┘               │       │
│  └──────────────────────────────────────────────────────────────────┘       │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Foundation Infrastructure (Days 1-3)

### 1.1 Project Scaffold

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 1.1.1 | Initialize Go module | `go.mod`, `go.sum` | `go mod init brun` succeeds |
| 1.1.2 | Create directory structure | See below | All directories exist |
| 1.1.3 | Add Cobra CLI framework | `internal/cli/root.go` | `./brun --help` works |
| 1.1.4 | Add Viper configuration | `internal/config/loader.go` | Config loads from file |

> **Module Path Convention:** The Go module is named `brun` (short, idiomatic). The binary/executable is also named `brun`. All import paths use `brun/...`.

**Directory Structure:**
```
brun/
├── cmd/
│   └── brun/
│       └── main.go           # Entry point
├── internal/
│   ├── cli/                  # CLI commands
│   │   ├── root.go
│   │   ├── build.go
│   │   ├── check.go
│   │   ├── run.go
│   │   ├── port.go
│   │   └── config.go
│   ├── config/               # Configuration
│   │   ├── loader.go
│   │   ├── types.go
│   │   ├── profile.go
│   │   └── validator.go
│   ├── executor/             # Runtime executors
│   │   ├── executor.go
│   │   ├── powershell.go
│   │   ├── nodejs.go
│   │   └── golang.go
│   ├── port/                 # Port management
│   │   ├── manager.go
│   │   ├── checker.go
│   │   └── firewall.go
│   ├── asset/                # Asset operations
│   │   ├── copier.go
│   │   └── watcher.go
│   ├── error/                # Error handling
│   │   ├── capture.go
│   │   ├── parser.go
│   │   └── codes.go
│   ├── health/               # Health checks
│   │   ├── checker.go
│   │   └── monitor.go
│   ├── output/               # Output formatting
│   │   ├── json.go
│   │   ├── file.go
│   │   └── console.go
│   └── database/             # Persistence (optional)
│       ├── connection.go
│       ├── repository.go
│       └── migrate.go
├── pkg/
│   ├── models/               # Data models
│   │   ├── run.go
│   │   ├── profile.go
│   │   ├── error.go
│   │   └── health.go
│   └── errors/               # Error definitions
│       └── codes.go
├── configs/
│   ├── config.json           # Default configuration
│   └── config.schema.json    # JSON schema for AI
├── tests/
│   └── integration/
├── go.mod
└── go.sum
```

**go.mod File:**
```go
module brun

go 1.22

require (
    github.com/spf13/cobra v1.8.0
    github.com/spf13/viper v1.18.0
    gorm.io/gorm v1.25.0
    gorm.io/driver/sqlite v1.5.0
    github.com/rs/zerolog v1.31.0
    github.com/google/uuid v1.5.0
    github.com/stretchr/testify v1.9.0
)
```

**Entry Point: `cmd/brun/main.go`**
```go
package main

import (
    "os"
    
    "brun/internal/cli"
    "brun/internal/config"
    
    "github.com/rs/zerolog"
    "github.com/rs/zerolog/log"
)

func main() {
    // Initialize logging
    log.Logger = zerolog.New(zerolog.ConsoleWriter{Out: os.Stderr}).
        With().Timestamp().Logger()
    
    // Load configuration
    cfg, err := config.Load()
    if err != nil {
        log.Error().Err(err).Msg("Failed to load configuration")
        os.Exit(1)
    }
    
    // Execute CLI
    if err := cli.Execute(cfg); err != nil {
        log.Error().Err(err).Msg("Command failed")
        os.Exit(1)
    }
}
```

**Dependencies to Install:**
```bash
go get github.com/spf13/cobra@v1.8.0
go get github.com/spf13/viper@v1.18.0
go get gorm.io/gorm@v1.25.0
go get gorm.io/driver/sqlite@v1.5.0
go get github.com/rs/zerolog@v1.31.0
go get github.com/google/uuid@v1.5.0
go get github.com/stretchr/testify@v1.9.0
```

### 1.2 Configuration System

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 1.2.1 | Define Config struct | `internal/config/types.go` | All fields match spec |
| 1.2.2 | Implement loader | `internal/config/loader.go` | Loads JSON with defaults |
| 1.2.3 | Add validation | `internal/config/validator.go` | Returns errors for invalid config |
| 1.2.4 | Create default config | `configs/config.json` | Valid JSON with all fields |

**Config Types: `internal/config/types.go`**
```go
package config

import "time"

type Config struct {
    Version    string            `json:"version" mapstructure:"version"`
    WorkDir    string            `json:"workDir" mapstructure:"workDir"`
    LogDir     string            `json:"logDir" mapstructure:"logDir"`
    Profiles   map[string]Profile `json:"profiles" mapstructure:"profiles"`
    Apps       map[string]AppDef  `json:"applications" mapstructure:"applications"`
    Defaults   Defaults          `json:"defaults" mapstructure:"defaults"`
}

type Profile struct {
    Name        string            `json:"name" mapstructure:"name"`
    Runtime     string            `json:"runtime" mapstructure:"runtime"`
    SourceDir   string            `json:"sourceDir" mapstructure:"sourceDir"`
    OutputDir   string            `json:"outputDir" mapstructure:"outputDir"`
    Command     string            `json:"command" mapstructure:"command"`
    Args        []string          `json:"args" mapstructure:"args"`
    Env         map[string]string `json:"env" mapstructure:"env"`
    PreCommands []string          `json:"preCommands" mapstructure:"preCommands"`
    Assets      AssetConfig       `json:"assets" mapstructure:"assets"`
    Timeout     time.Duration     `json:"timeout" mapstructure:"timeout"`
}

type AppDef struct {
    Name        string      `json:"name" mapstructure:"name"`
    Host        string      `json:"host" mapstructure:"host"`
    Port        int         `json:"port" mapstructure:"port"`
    HealthPath  string      `json:"healthPath" mapstructure:"healthPath"`
    HealthCheck HealthCheck `json:"healthCheck" mapstructure:"healthCheck"`
}

type HealthCheck struct {
    Enabled        bool          `json:"enabled" mapstructure:"enabled"`
    Interval       time.Duration `json:"interval" mapstructure:"interval"`
    Timeout        time.Duration `json:"timeout" mapstructure:"timeout"`
    Retries        int           `json:"retries" mapstructure:"retries"`
    ExpectedStatus int           `json:"expectedStatus" mapstructure:"expectedStatus"`
}

type AssetConfig struct {
    Mode   string   `json:"mode" mapstructure:"mode"` // clear, override, skip
    Source string   `json:"source" mapstructure:"source"`
    Target string   `json:"target" mapstructure:"target"`
    Ignore []string `json:"ignore" mapstructure:"ignore"`
}

type Defaults struct {
    Timeout     time.Duration `json:"timeout" mapstructure:"timeout"`
    JSONOutput  bool          `json:"jsonOutput" mapstructure:"jsonOutput"`
    LogToFile   bool          `json:"logToFile" mapstructure:"logToFile"`
}
```

### 1.3 Logger Service

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 1.3.1 | Create logger service | `internal/output/logger.go` | Dual console/file logging |
| 1.3.2 | Add run ID generation | `internal/output/runid.go` | Unique run IDs per execution |
| 1.3.3 | Implement log rotation | `internal/output/rotate.go` | Old logs cleaned up |

**Logger Service: `internal/output/logger.go`**
```go
package output

import (
    "fmt"
    "os"
    "path/filepath"
    "time"
    
    "github.com/google/uuid"
    "github.com/rs/zerolog"
)

type LogService struct {
    runID      string
    logDir     string
    consoleLog zerolog.Logger
    fileLog    zerolog.Logger
}

func NewLogService(logDir string) (*LogService, error) {
    runID := generateRunID()
    runDir := filepath.Join(logDir, runID)
    
    if err := os.MkdirAll(runDir, 0755); err != nil {
        return nil, fmt.Errorf("create log dir: %w", err)
    }
    
    logFile, err := os.Create(filepath.Join(runDir, "log.txt"))
    if err != nil {
        return nil, fmt.Errorf("create log file: %w", err)
    }
    
    return &LogService{
        runID:      runID,
        logDir:     runDir,
        consoleLog: zerolog.New(zerolog.ConsoleWriter{Out: os.Stderr}).With().Timestamp().Logger(),
        fileLog:    zerolog.New(logFile).With().Timestamp().Logger(),
    }, nil
}

func generateRunID() string {
    return fmt.Sprintf("%s-%s", 
        time.Now().Format("20060102-150405"),
        uuid.New().String()[:8])
}

func (l *LogService) RunID() string {
    return l.runID
}

func (l *LogService) LogDir() string {
    return l.logDir
}
```

### 1.4 Data Models

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 1.4.1 | Define Run model | `pkg/models/run.go` | GORM tags correct |
| 1.4.2 | Define BuildError model | `pkg/models/error.go` | All fields present |
| 1.4.3 | Define HealthStatus model | `pkg/models/health.go` | Status enum defined |

**Run Model: `pkg/models/run.go`**
```go
package models

import (
    "time"
    
    "gorm.io/gorm"
)

type Run struct {
    gorm.Model
    RunID       string        `gorm:"uniqueIndex;size:50" json:"runId"`
    ProfileName string        `gorm:"size:100" json:"profileName"`
    Runtime     string        `gorm:"size:20" json:"runtime"`
    Command     string        `gorm:"type:text" json:"command"`
    WorkDir     string        `gorm:"size:500" json:"workDir"`
    Status      RunStatus     `gorm:"size:20" json:"status"`
    ExitCode    int           `json:"exitCode"`
    StartTime   time.Time     `json:"startTime"`
    EndTime     *time.Time    `json:"endTime,omitempty"`
    Duration    time.Duration `json:"duration"`
    Errors      []BuildError  `gorm:"foreignKey:RunID" json:"errors,omitempty"`
}

type RunStatus string

const (
    StatusPending  RunStatus = "pending"
    StatusRunning  RunStatus = "running"
    StatusSuccess  RunStatus = "success"
    StatusFailed   RunStatus = "failed"
    StatusTimeout  RunStatus = "timeout"
    StatusCanceled RunStatus = "canceled"
)
```

---

## Phase 2: Support Services (Days 4-6)

### 2.1 Error Capture & Parsing

**Duration:** 1.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 2.1.1 | Create error code registry | `pkg/errors/codes.go` | All 71xx codes defined |
| 2.1.2 | Implement error parser | `internal/error/parser.go` | Parses Go/Node/PS errors |
| 2.1.3 | Add stack trace capture | `internal/error/capture.go` | Captures full traces |
| 2.1.4 | Implement regex patterns | `internal/error/patterns.go` | Runtime-specific patterns |

**Error Parser: `internal/error/parser.go`**
```go
package error

import (
    "regexp"
    "strconv"
    "strings"
    
    "brun/pkg/models"
)

type Parser struct {
    patterns map[string][]*regexp.Regexp
}

func NewParser() *Parser {
    return &Parser{
        patterns: map[string][]*regexp.Regexp{
            "golang": {
                regexp.MustCompile(`^(.+):(\d+):(\d+): (.+)$`),
                regexp.MustCompile(`^# (.+)$`),
            },
            "nodejs": {
                regexp.MustCompile(`^\s+at .+ \((.+):(\d+):(\d+)\)$`),
                regexp.MustCompile(`^(.+):(\d+)$`),
                regexp.MustCompile(`^(TypeError|ReferenceError|SyntaxError): (.+)$`),
            },
            "powershell": {
                regexp.MustCompile(`At (.+):(\d+) char:(\d+)`),
                regexp.MustCompile(`^\+\s+(.+)$`),
            },
        },
    }
}

func (p *Parser) Parse(runtime, output string) []models.BuildError {
    var errors []models.BuildError
    lines := strings.Split(output, "\n")
    patterns := p.patterns[runtime]
    
    for _, line := range lines {
        for _, pattern := range patterns {
            if matches := pattern.FindStringSubmatch(line); matches != nil {
                err := p.extractError(runtime, matches)
                if err != nil {
                    errors = append(errors, *err)
                }
                break
            }
        }
    }
    
    return errors
}

func (p *Parser) extractError(runtime string, matches []string) *models.BuildError {
    switch runtime {
    case "golang":
        if len(matches) >= 5 {
            line, _ := strconv.Atoi(matches[2])
            col, _ := strconv.Atoi(matches[3])
            return &models.BuildError{
                File:     matches[1],
                Line:     line,
                Column:   col,
                Message:  matches[4],
                Severity: "error",
            }
        }
    case "nodejs":
        // Similar extraction logic
    case "powershell":
        // Similar extraction logic
    }
    return nil
}
```

### 2.2 Port Management

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 2.2.1 | Implement port checker | `internal/port/checker.go` | Cross-platform port check |
| 2.2.2 | Add fallback logic | `internal/port/fallback.go` | Tries alternative ports |
| 2.2.3 | Create firewall manager | `internal/port/firewall.go` | Windows/Linux/macOS support |

**Port Checker: `internal/port/checker.go`**
```go
package port

import (
    "context"
    "fmt"
    "net"
    "time"
)

type Manager struct {
    timeout time.Duration
}

func NewManager(timeout time.Duration) *Manager {
    return &Manager{timeout: timeout}
}

func (m *Manager) IsAvailable(port int) bool {
    address := fmt.Sprintf(":%d", port)
    listener, err := net.Listen("tcp", address)
    if err != nil {
        return false
    }
    listener.Close()
    return true
}

func (m *Manager) FindAvailable(preferred int, fallbacks []int) (int, error) {
    if m.IsAvailable(preferred) {
        return preferred, nil
    }
    
    for _, port := range fallbacks {
        if m.IsAvailable(port) {
            return port, nil
        }
    }
    
    return 0, fmt.Errorf("no available port found")
}

func (m *Manager) WaitForPort(ctx context.Context, port int) error {
    ticker := time.NewTicker(500 * time.Millisecond)
    defer ticker.Stop()
    
    for {
        select {
        case <-ctx.Done():
            return ctx.Err()
        case <-ticker.C:
            conn, err := net.DialTimeout("tcp", 
                fmt.Sprintf("localhost:%d", port), m.timeout)
            if err == nil {
                conn.Close()
                return nil
            }
        }
    }
}
```

### 2.3 Asset Copier

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 2.3.1 | Implement copy operations | `internal/asset/copier.go` | Clear/override/skip modes |
| 2.3.2 | Add ignore patterns | `internal/asset/ignore.go` | Glob pattern matching |

**Asset Copier: `internal/asset/copier.go`**
```go
package asset

import (
    "fmt"
    "io"
    "os"
    "path/filepath"
)

type CopyMode string

const (
    ModeClear    CopyMode = "clear"
    ModeOverride CopyMode = "override"
    ModeSkip     CopyMode = "skip"
)

type Copier struct {
    mode    CopyMode
    ignore  []string
}

func NewCopier(mode CopyMode, ignore []string) *Copier {
    return &Copier{mode: mode, ignore: ignore}
}

func (c *Copier) Copy(src, dst string) error {
    switch c.mode {
    case ModeClear:
        if err := os.RemoveAll(dst); err != nil {
            return fmt.Errorf("clear destination: %w", err)
        }
    case ModeSkip:
        if _, err := os.Stat(dst); err == nil {
            return nil // Already exists, skip
        }
    }
    
    return c.copyDir(src, dst)
}

func (c *Copier) copyDir(src, dst string) error {
    return filepath.Walk(src, func(path string, info os.FileInfo, err error) error {
        if err != nil {
            return err
        }
        
        // Check ignore patterns
        for _, pattern := range c.ignore {
            if matched, _ := filepath.Match(pattern, info.Name()); matched {
                if info.IsDir() {
                    return filepath.SkipDir
                }
                return nil
            }
        }
        
        relPath, _ := filepath.Rel(src, path)
        dstPath := filepath.Join(dst, relPath)
        
        if info.IsDir() {
            return os.MkdirAll(dstPath, info.Mode())
        }
        
        return c.copyFile(path, dstPath)
    })
}

func (c *Copier) copyFile(src, dst string) error {
    srcFile, err := os.Open(src)
    if err != nil {
        return err
    }
    defer srcFile.Close()
    
    dstFile, err := os.Create(dst)
    if err != nil {
        return err
    }
    defer dstFile.Close()
    
    _, err = io.Copy(dstFile, srcFile)
    return err
}
```

---

## Phase 3: Runtime Executors (Days 7-10)

### 3.1 Executor Interface

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 3.1.1 | Define Executor interface | `internal/executor/executor.go` | Common interface |
| 3.1.2 | Create base executor | `internal/executor/base.go` | Shared logic |

**Executor Interface: `internal/executor/executor.go`**
```go
package executor

import (
    "context"
    "time"
    
    "brun/pkg/models"
)

type Executor interface {
    Execute(ctx context.Context, cmd *Command) (*Result, error)
    Validate() error
    GetVersion() (string, error)
    RuntimeType() string
}

type Command struct {
    Script      string
    Args        []string
    WorkDir     string
    Env         map[string]string
    Timeout     time.Duration
    PreCommands []string
}

type Result struct {
    ExitCode   int
    Stdout     string
    Stderr     string
    StartTime  time.Time
    EndTime    time.Time
    Duration   time.Duration
    Errors     []models.BuildError
}
```

### 3.2 PowerShell Executor

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 3.2.1 | Implement PS executor | `internal/executor/powershell.go` | Scripts execute |
| 3.2.2 | Add error parsing | integrated | PS errors captured |
| 3.2.3 | Handle cross-platform | integrated | pwsh works on all OS |

**PowerShell Executor: `internal/executor/powershell.go`**
```go
package executor

import (
    "bytes"
    "context"
    "fmt"
    "os/exec"
    "runtime"
    "time"
    
    brunError "brun/internal/error"
    "brun/pkg/models"
)

type PowerShellExecutor struct {
    parser *brunError.Parser
}

func NewPowerShellExecutor() *PowerShellExecutor {
    return &PowerShellExecutor{
        parser: brunError.NewParser(),
    }
}

func (e *PowerShellExecutor) RuntimeType() string {
    return "powershell"
}

func (e *PowerShellExecutor) Validate() error {
    _, err := e.GetVersion()
    return err
}

func (e *PowerShellExecutor) GetVersion() (string, error) {
    cmd := exec.Command(e.executable(), "-Version")
    output, err := cmd.Output()
    if err != nil {
        return "", fmt.Errorf("powershell not found: %w", err)
    }
    return string(output), nil
}

func (e *PowerShellExecutor) executable() string {
    if runtime.GOOS == "windows" {
        return "pwsh.exe"
    }
    return "pwsh"
}

func (e *PowerShellExecutor) Execute(ctx context.Context, cmd *Command) (*Result, error) {
    startTime := time.Now()
    
    // Build command
    args := []string{"-NoProfile", "-NonInteractive", "-File", cmd.Script}
    args = append(args, cmd.Args...)
    
    execCmd := exec.CommandContext(ctx, e.executable(), args...)
    execCmd.Dir = cmd.WorkDir
    
    // Set environment
    for k, v := range cmd.Env {
        execCmd.Env = append(execCmd.Env, fmt.Sprintf("%s=%s", k, v))
    }
    
    var stdout, stderr bytes.Buffer
    execCmd.Stdout = &stdout
    execCmd.Stderr = &stderr
    
    err := execCmd.Run()
    endTime := time.Now()
    
    result := &Result{
        ExitCode:  0,
        Stdout:    stdout.String(),
        Stderr:    stderr.String(),
        StartTime: startTime,
        EndTime:   endTime,
        Duration:  endTime.Sub(startTime),
    }
    
    if exitErr, ok := err.(*exec.ExitError); ok {
        result.ExitCode = exitErr.ExitCode()
    }
    
    // Parse errors from output
    result.Errors = e.parser.Parse("powershell", stderr.String())
    
    return result, nil
}
```

### 3.3 Node.js Executor

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 3.3.1 | Implement Node executor | `internal/executor/nodejs.go` | npm/yarn/bun support |
| 3.3.2 | Add package manager detection | integrated | Auto-detect pm |
| 3.3.3 | Handle npm scripts | integrated | Run package.json scripts |

### 3.4 Golang Executor

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 3.4.1 | Implement Go executor | `internal/executor/golang.go` | go build/run works |
| 3.4.2 | Add go mod tidy support | integrated | Pre-command support |
| 3.4.3 | Parse go build errors | integrated | Errors captured |

### 3.5 Executor Registry

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 3.5.1 | Create executor registry | `internal/executor/registry.go` | All executors registered |
| 3.5.2 | Add factory pattern | integrated | Dynamic executor creation |

---

## Phase 4: Orchestration Layer (Days 11-13)

### 4.1 Execution Engine

**Duration:** 1.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 4.1.1 | Create execution engine | `internal/engine/engine.go` | Orchestrates execution |
| 4.1.2 | Implement run tracking | `internal/engine/tracker.go` | Tracks run status |
| 4.1.3 | Add timeout handling | integrated | Graceful timeout |

### 4.2 Profile Manager

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 4.2.1 | Implement profile loading | `internal/config/profile.go` | Loads from config |
| 4.2.2 | Add profile validation | integrated | Validates all fields |

### 4.3 Health Checker

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 4.3.1 | Implement health checker | `internal/health/checker.go` | HTTP health checks |
| 4.3.2 | Add retry logic | integrated | Configurable retries |
| 4.3.3 | Create health monitor | `internal/health/monitor.go` | Continuous monitoring |

**Health Checker: `internal/health/checker.go`**
```go
package health

import (
    "context"
    "fmt"
    "net/http"
    "time"
    
    "brun/internal/config"
)

type Checker struct {
    client *http.Client
}

func NewChecker(timeout time.Duration) *Checker {
    return &Checker{
        client: &http.Client{Timeout: timeout},
    }
}

func (c *Checker) Check(ctx context.Context, app config.AppDef) error {
    url := fmt.Sprintf("http://%s:%d%s", app.Host, app.Port, app.HealthPath)
    
    req, err := http.NewRequestWithContext(ctx, "GET", url, nil)
    if err != nil {
        return err
    }
    
    resp, err := c.client.Do(req)
    if err != nil {
        return err
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != app.HealthCheck.ExpectedStatus {
        return fmt.Errorf("unexpected status: %d", resp.StatusCode)
    }
    
    return nil
}

func (c *Checker) WaitForHealthy(ctx context.Context, app config.AppDef) error {
    hc := app.HealthCheck
    
    for i := 0; i < hc.Retries; i++ {
        if err := c.Check(ctx, app); err == nil {
            return nil
        }
        
        select {
        case <-ctx.Done():
            return ctx.Err()
        case <-time.After(hc.Interval):
            continue
        }
    }
    
    return fmt.Errorf("health check failed after %d retries", hc.Retries)
}
```

---

## Phase 5: CLI Commands (Days 14-16)

### 5.1 Root Command

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.1.1 | Implement root command | `internal/cli/root.go` | Global flags work |
| 5.1.2 | Add version command | integrated | Shows version |

### 5.2 Build Command

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.2.1 | Implement build command | `internal/cli/build.go` | --profile flag works |
| 5.2.2 | Add JSON output | integrated | --json flag works |

**Build Command: `internal/cli/build.go`**
```go
package cli

import (
    "encoding/json"
    "fmt"
    
    "brun/internal/config"
    "brun/internal/engine"
    
    "github.com/spf13/cobra"
)

func NewBuildCmd(cfg *config.Config) *cobra.Command {
    var profileName string
    var jsonOutput bool
    
    cmd := &cobra.Command{
        Use:   "build",
        Short: "Run a saved build profile",
        RunE: func(cmd *cobra.Command, args []string) error {
            profile, ok := cfg.Profiles[profileName]
            if !ok {
                return fmt.Errorf("profile not found: %s", profileName)
            }
            
            eng := engine.New(cfg)
            result, err := eng.Execute(cmd.Context(), profile)
            if err != nil {
                return err
            }
            
            if jsonOutput {
                return json.NewEncoder(cmd.OutOrStdout()).Encode(result)
            }
            
            // Console output
            fmt.Fprintf(cmd.OutOrStdout(), "Build %s: %s\n", 
                result.Status, result.RunID)
            return nil
        },
    }
    
    cmd.Flags().StringVarP(&profileName, "profile", "p", "", "Build profile name")
    cmd.Flags().BoolVar(&jsonOutput, "json", false, "Output JSON format")
    cmd.MarkFlagRequired("profile")
    
    return cmd
}
```

### 5.3 Check Command

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.3.1 | Implement check command | `internal/cli/check.go` | --go/--node/--ps flags |
| 5.3.2 | Add error-only mode | integrated | Only shows errors |

### 5.4 Run Command

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.4.1 | Implement run command | `internal/cli/run.go` | Direct command execution |
| 5.4.2 | Add -ps flag support | integrated | PowerShell scripts |

### 5.5 Port Command

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.5.1 | Implement port command | `internal/cli/port.go` | --check/--fallback flags |

### 5.6 Config Command

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.6.1 | Implement config command | `internal/cli/config.go` | View/validate config |

---

## Phase 6: Output & Persistence (Days 17-18)

### 6.1 JSON Output

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 6.1.1 | Implement JSON formatter | `internal/output/json.go` | Valid JSON output |

### 6.2 File Logger

**Duration:** 0.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 6.2.1 | Implement file logger | `internal/output/file.go` | log.txt/error.txt created |
| 6.2.2 | Add run directory structure | integrated | logs/{runId}/ folders |

### 6.3 Database Persistence (Optional)

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 6.3.1 | Implement SQLite connection | `internal/database/connection.go` | Connection works |
| 6.3.2 | Add run repository | `internal/database/repository.go` | CRUD operations |
| 6.3.3 | Create migrations | `internal/database/migrate.go` | Tables auto-created |

---

## Phase 7: Integration & Testing (Days 19-20)

### 7.1 Integration Testing

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 7.1.1 | Create test fixtures | `tests/fixtures/` | Sample projects |
| 7.1.2 | Write integration tests | `tests/integration/` | All commands tested |

### 7.2 Cross-Platform Testing

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 7.2.1 | Test on Windows | - | All executors work |
| 7.2.2 | Test on Linux | - | All executors work |
| 7.2.3 | Test on macOS | - | All executors work |

---

## Implementation Order Summary

```
Week 1 (Days 1-5):
├── Phase 1: Foundation
│   ├── Project scaffold
│   ├── Configuration system
│   ├── Logger service
│   └── Data models
└── Phase 2: Support Services (partial)
    ├── Error capture & parsing
    └── Port management

Week 2 (Days 6-10):
├── Phase 2: Support Services (complete)
│   └── Asset copier
└── Phase 3: Runtime Executors
    ├── Executor interface
    ├── PowerShell executor
    ├── Node.js executor
    ├── Golang executor
    └── Executor registry

Week 3 (Days 11-15):
├── Phase 4: Orchestration
│   ├── Execution engine
│   ├── Profile manager
│   └── Health checker
└── Phase 5: CLI Commands
    ├── Root command
    ├── Build command
    ├── Check command
    ├── Run command
    ├── Port command
    └── Config command

Week 4 (Days 16-20):
├── Phase 6: Output & Persistence
│   ├── JSON output
│   ├── File logger
│   └── Database persistence
└── Phase 7: Integration & Testing
    ├── Integration tests
    └── Cross-platform testing
```

---

## Critical Path Dependencies

```
┌─────────────────────────────────────────────────────────────────┐
│                     CRITICAL PATH                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Config ──► Executor Interface ──► Executors ──► Engine         │
│     │              │                   │            │            │
│     └──────────────┼───────────────────┼────────────┤            │
│                    │                   │            │            │
│               ErrorParser ◄────────────┘            │            │
│                    │                                │            │
│                    └────────────────────────────────┤            │
│                                                     │            │
│                                              CLI Commands        │
│                                                     │            │
│                                              Integration Tests   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

**Blocking Dependencies:**
1. Config must be complete before any executor
2. Executor interface must be defined before implementations
3. Error parser must be ready before executors can capture errors
4. Engine requires all executors to be complete
5. CLI commands require engine to be functional

---

## Acceptance Criteria Checklist

| Phase | Criteria | Priority |
|-------|----------|----------|
| 1 | `brun --help` displays usage | P0 |
| 1 | Config loads from `config.json` | P0 |
| 1 | Unique run IDs generated | P0 |
| 2 | Go/Node/PS errors parsed correctly | P0 |
| 2 | Port availability checked | P1 |
| 2 | Asset copy modes work | P1 |
| 3 | PowerShell scripts execute | P0 |
| 3 | Node.js builds work | P0 |
| 3 | Go builds work | P0 |
| 4 | Build profiles execute | P0 |
| 4 | Health checks pass | P1 |
| 5 | `brun build --profile x` works | P0 |
| 5 | `brun check --go ./cmd` works | P0 |
| 5 | `brun -ps script.ps1` works | P0 |
| 6 | JSON output is valid | P0 |
| 6 | Logs written to files | P1 |
| 7 | All tests pass | P0 |

---

## See Also

- [Core Architecture](./01-core-architecture.md) — System design
- [CLI Interface](./02-cli-interface.md) — Command definitions
- [Configuration](./03-configuration.md) — Config schema
- [Error Handling](./06-error-handling.md) — Error codes
- [Testing Strategy](./13-testing-strategy.md) — Test patterns
- [gsearch Implementation Guide](../22-golang-search-cli/13-implementation-guide.md) — Reference template
