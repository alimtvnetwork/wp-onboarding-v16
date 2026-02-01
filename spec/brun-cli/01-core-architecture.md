# Core Architecture

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

System architecture for the Build Runner CLI (`brun`), covering component structure, execution flow, and integration points.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [Configuration](./03-configuration.md)
- [Integration API](./09-integration-api.md)

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        brun CLI                                  │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │   Cobra     │  │   Viper     │  │   Logger    │              │
│  │   Commands  │  │   Config    │  │   Service   │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
│         │                │                │                      │
│  ┌──────▼────────────────▼────────────────▼──────┐              │
│  │              Command Router                    │              │
│  └──────────────────────┬────────────────────────┘              │
│                         │                                        │
│  ┌──────────────────────▼────────────────────────┐              │
│  │            Execution Engine                    │              │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐         │              │
│  │  │PowerShell│ │ Node.js │ │ Golang  │         │              │
│  │  │ Executor │ │ Executor│ │ Executor│         │              │
│  │  └─────────┘ └─────────┘ └─────────┘         │              │
│  └──────────────────────┬────────────────────────┘              │
│                         │                                        │
│  ┌──────────────────────▼────────────────────────┐              │
│  │              Support Services                  │              │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐         │              │
│  │  │  Port   │ │  Asset  │ │  Error  │         │              │
│  │  │ Manager │ │ Copier  │ │ Capture │         │              │
│  │  └─────────┘ └─────────┘ └─────────┘         │              │
│  └──────────────────────┬────────────────────────┘              │
│                         │                                        │
│  ┌──────────────────────▼────────────────────────┐              │
│  │              Output Layer                      │              │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐         │              │
│  │  │  JSON   │ │  File   │ │ Console │         │              │
│  │  │ Output  │ │ Logger  │ │ Output  │         │              │
│  │  └─────────┘ └─────────┘ └─────────┘         │              │
│  └───────────────────────────────────────────────┘              │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Components

### 1. Command Router

Routes CLI commands to appropriate handlers.

```go
type CommandRouter struct {
    config    *Config
    executors map[RuntimeType]Executor
    logger    *LogService
}

type RuntimeType string

const (
    RuntimePowerShell RuntimeType = "powershell"
    RuntimeNodeJS     RuntimeType = "nodejs"
    RuntimeGolang     RuntimeType = "golang"
)
```

### 2. Execution Engine

Manages runtime executors and orchestrates build processes.

```go
type ExecutionEngine struct {
    router       *CommandRouter
    portManager  *PortManager
    assetCopier  *AssetCopier
    errorCapture *ErrorCapture
}

type ExecutionResult struct {
    RunID       string         `json:"runId"`
    Success     bool           `json:"success"`
    ExitCode    int            `json:"exitCode"`
    Stdout      string         `json:"stdout"`
    Stderr      string         `json:"stderr"`
    StartTime   time.Time      `json:"startTime"`
    EndTime     time.Time      `json:"endTime"`
    Duration    time.Duration  `json:"duration"`
    Errors      []BuildError   `json:"errors,omitempty"`
}

type BuildError struct {
    File       string `json:"file,omitempty"`
    Line       int    `json:"line,omitempty"`
    Column     int    `json:"column,omitempty"`
    Message    string `json:"message"`
    Severity   string `json:"severity"`
    StackTrace string `json:"stackTrace,omitempty"`
}
```

### 3. Executor Interface

Common interface for all runtime executors.

```go
type Executor interface {
    // Execute runs a command and returns the result
    Execute(ctx context.Context, cmd *Command) (*ExecutionResult, error)
    
    // Validate checks if the runtime is available
    Validate() error
    
    // GetVersion returns the runtime version
    GetVersion() (string, error)
}

type Command struct {
    Type       RuntimeType
    Script     string            // Script path or inline command
    Args       []string          // Additional arguments
    WorkDir    string            // Working directory
    Env        map[string]string // Environment variables
    Timeout    time.Duration     // Execution timeout
    PreCommands []string         // Commands to run before (e.g., go mod tidy)
}
```

---

## Execution Flow

### Standard Build Flow

```
1. Parse CLI arguments
2. Load configuration (config.json)
3. Resolve build profile (if specified)
4. Pre-execution checks:
   a. Validate runtime availability
   b. Check port availability
   c. Prepare asset directories
5. Execute pre-commands (e.g., go mod tidy)
6. Run main build command
7. Capture stdout/stderr
8. Parse errors from output
9. Generate output:
   a. JSON to stdout (if --json flag)
   b. Write log files (if configured)
10. Return exit code
```

### Error Check Flow (Concurrent Mode)

```
1. Parse CLI arguments
2. Load configuration
3. Start build process (non-blocking)
4. Concurrently monitor:
   a. Port availability
   b. Error output stream
   c. Process health
5. On completion or error:
   a. Capture final state
   b. Generate error report
   c. Output JSON/files
6. Return status
```

---

## Service Dependencies

```
┌─────────────────┐
│   ConfigLoader  │◄──────── config.json
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌─────────────────┐
│  ProfileManager │────►│   AssetCopier   │
└────────┬────────┘     └─────────────────┘
         │
         ▼
┌─────────────────┐     ┌─────────────────┐
│ ExecutionEngine │────►│  PortManager    │
└────────┬────────┘     └─────────────────┘
         │
         ▼
┌─────────────────┐     ┌─────────────────┐
│  ErrorCapture   │────►│  OutputService  │
└─────────────────┘     └─────────────────┘
```

---

## Cross-Platform Support

| Feature | Windows | Linux | macOS |
|---------|---------|-------|-------|
| PowerShell Executor | ✅ pwsh.exe | ✅ pwsh | ✅ pwsh |
| Node.js Executor | ✅ | ✅ | ✅ |
| Go Executor | ✅ | ✅ | ✅ |
| Port Checking | ✅ netstat | ✅ ss/netstat | ✅ lsof |
| Firewall Rules | ✅ netsh | ✅ iptables/ufw | ✅ pfctl |

---

## Directory Structure

```
brun/
├── cmd/
│   └── brun/
│       └── main.go           # Entry point
├── internal/
│   ├── cli/
│   │   ├── root.go           # Root command
│   │   ├── build.go          # Build command
│   │   ├── check.go          # Check command
│   │   ├── run.go            # Run command
│   │   ├── port.go           # Port command
│   │   └── config.go         # Config command
│   ├── executor/
│   │   ├── executor.go       # Interface
│   │   ├── powershell.go     # PowerShell executor
│   │   ├── nodejs.go         # Node.js executor
│   │   └── golang.go         # Go executor
│   ├── port/
│   │   ├── manager.go        # Port management
│   │   └── firewall.go       # Firewall operations
│   ├── asset/
│   │   └── copier.go         # Asset copy operations
│   ├── error/
│   │   ├── capture.go        # Error capture
│   │   └── parser.go         # Error parsing
│   ├── output/
│   │   ├── json.go           # JSON output
│   │   └── file.go           # File logging
│   └── config/
│       ├── loader.go         # Config loading
│       └── profile.go        # Build profiles
├── configs/
│   ├── config.json           # Default config
│   └── config.schema.json    # JSON schema
├── go.mod
└── go.sum
```

---

## See Also

- [CLI Interface](./02-cli-interface.md)
- [Runtime Executors](./04-runtime-executors.md)
- [Error Handling](./06-error-handling.md)
