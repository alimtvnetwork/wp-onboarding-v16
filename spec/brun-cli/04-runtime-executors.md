# Runtime Executors

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Specification for runtime executors that handle command execution across PowerShell, Node.js, and Go environments.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [CLI Interface](./02-cli-interface.md)
- [Error Handling](./06-error-handling.md)

---

## Executor Interface

```go
type Executor interface {
    Execute(ctx context.Context, cmd *Command) (*ExecutionResult, error)
    Validate() error
    GetVersion() (string, error)
    ParseErrors(output string) []BuildError
}

type Command struct {
    Type        RuntimeType
    Script      string              // Script path or inline command
    Args        []string            // Additional arguments
    WorkDir     string              // Working directory
    Env         map[string]string   // Environment variables
    Timeout     time.Duration       // Execution timeout
    PreCommands []string            // Commands before main execution
}
```

---

## 1. PowerShell Executor

### Supported Platforms
- Windows: `pwsh.exe` (PowerShell Core) or `powershell.exe` (Windows PowerShell)
- Linux/macOS: `pwsh` (PowerShell Core)

### Implementation

```go
type PowerShellExecutor struct {
    path    string
    args    []string
    logger  *LogService
}

func (e *PowerShellExecutor) Execute(ctx context.Context, cmd *Command) (*ExecutionResult, error) {
    // Build command
    args := append(e.args, "-Command", cmd.Script)
    if cmd.Script != "" && isFilePath(cmd.Script) {
        args = append(e.args, "-File", cmd.Script)
        args = append(args, cmd.Args...)
    }
    
    // Execute with timeout
    execCmd := exec.CommandContext(ctx, e.path, args...)
    execCmd.Dir = cmd.WorkDir
    execCmd.Env = mergeEnv(os.Environ(), cmd.Env)
    
    // Capture output
    var stdout, stderr bytes.Buffer
    execCmd.Stdout = &stdout
    execCmd.Stderr = &stderr
    
    startTime := time.Now()
    err := execCmd.Run()
    endTime := time.Now()
    
    return &ExecutionResult{
        Success:   err == nil,
        ExitCode:  getExitCode(err),
        Stdout:    stdout.String(),
        Stderr:    stderr.String(),
        StartTime: startTime,
        EndTime:   endTime,
        Duration:  endTime.Sub(startTime),
        Errors:    e.ParseErrors(stderr.String()),
    }, nil
}
```

### Error Patterns

```go
var psErrorPatterns = []ErrorPattern{
    {
        Regex:    regexp.MustCompile(`(?m)^(.+):(\d+):\d+: (.+)$`),
        Severity: "error",
        Extract:  func(m []string) BuildError { /* parse file, line, message */ },
    },
    {
        Regex:    regexp.MustCompile(`(?m)^At (.+):(\d+) char:(\d+)`),
        Severity: "error",
    },
    {
        Regex:    regexp.MustCompile(`(?m)^.*FullyQualifiedErrorId : (.+)$`),
        Severity: "error",
    },
}
```

---

## 2. Node.js Executor

### Supported Package Managers
- npm (default)
- yarn
- bun

### Implementation

```go
type NodeJSExecutor struct {
    nodePath       string
    packageManager string  // npm, yarn, bun
    logger         *LogService
}

func (e *NodeJSExecutor) Execute(ctx context.Context, cmd *Command) (*ExecutionResult, error) {
    var execPath string
    var args []string
    
    switch e.packageManager {
    case "npm":
        execPath = "npm"
        args = []string{"run", cmd.Script}
    case "yarn":
        execPath = "yarn"
        args = []string{cmd.Script}
    case "bun":
        execPath = "bun"
        args = []string{"run", cmd.Script}
    }
    
    args = append(args, cmd.Args...)
    
    execCmd := exec.CommandContext(ctx, execPath, args...)
    execCmd.Dir = cmd.WorkDir
    execCmd.Env = mergeEnv(os.Environ(), cmd.Env)
    
    // ... execution logic
}

func (e *NodeJSExecutor) Validate() error {
    // Check if package manager is installed
    _, err := exec.LookPath(e.packageManager)
    if err != nil {
        return fmt.Errorf("%s not found in PATH", e.packageManager)
    }
    
    // Check if package.json exists in workdir
    return nil
}
```

### Error Patterns

```go
var nodeErrorPatterns = []ErrorPattern{
    // TypeScript errors
    {
        Regex:    regexp.MustCompile(`(?m)^(.+)\((\d+),(\d+)\): error TS\d+: (.+)$`),
        Severity: "error",
    },
    // ESLint errors
    {
        Regex:    regexp.MustCompile(`(?m)^\s*(\d+):(\d+)\s+error\s+(.+)$`),
        Severity: "error",
    },
    // Vite/webpack build errors
    {
        Regex:    regexp.MustCompile(`(?m)^ERROR in (.+)$`),
        Severity: "error",
    },
    // npm ERR!
    {
        Regex:    regexp.MustCompile(`(?m)^npm ERR! (.+)$`),
        Severity: "error",
    },
}
```

---

## 3. Golang Executor

### Features
- `go build` with customizable flags
- `go mod tidy` support (skip/run/force)
- Cross-compilation support
- CGO handling

### Implementation

```go
type GolangExecutor struct {
    goPath     string
    buildFlags []string
    modTidy    string  // skip, run, force
    logger     *LogService
}

func (e *GolangExecutor) Execute(ctx context.Context, cmd *Command) (*ExecutionResult, error) {
    results := &ExecutionResult{
        StartTime: time.Now(),
    }
    
    // Step 1: go mod tidy (if configured)
    if e.modTidy != "skip" {
        tidyResult := e.runModTidy(ctx, cmd)
        if tidyResult.ExitCode != 0 && e.modTidy == "force" {
            return tidyResult, fmt.Errorf("go mod tidy failed")
        }
        results.Stdout += tidyResult.Stdout
        results.Stderr += tidyResult.Stderr
    }
    
    // Step 2: go build
    args := []string{"build"}
    args = append(args, e.buildFlags...)
    args = append(args, cmd.Args...)
    args = append(args, cmd.Script)
    
    execCmd := exec.CommandContext(ctx, e.goPath, args...)
    execCmd.Dir = cmd.WorkDir
    execCmd.Env = mergeEnv(os.Environ(), cmd.Env)
    
    var stdout, stderr bytes.Buffer
    execCmd.Stdout = &stdout
    execCmd.Stderr = &stderr
    
    err := execCmd.Run()
    
    results.EndTime = time.Now()
    results.Duration = results.EndTime.Sub(results.StartTime)
    results.ExitCode = getExitCode(err)
    results.Success = err == nil
    results.Stdout += stdout.String()
    results.Stderr += stderr.String()
    results.Errors = e.ParseErrors(stderr.String())
    
    return results, nil
}

func (e *GolangExecutor) runModTidy(ctx context.Context, cmd *Command) *ExecutionResult {
    execCmd := exec.CommandContext(ctx, e.goPath, "mod", "tidy")
    execCmd.Dir = cmd.WorkDir
    
    var stdout, stderr bytes.Buffer
    execCmd.Stdout = &stdout
    execCmd.Stderr = &stderr
    
    err := execCmd.Run()
    
    return &ExecutionResult{
        ExitCode: getExitCode(err),
        Stdout:   stdout.String(),
        Stderr:   stderr.String(),
    }
}
```

### Error Patterns

```go
var goErrorPatterns = []ErrorPattern{
    // Standard Go error: file.go:10:5: error message
    {
        Regex:    regexp.MustCompile(`(?m)^(.+\.go):(\d+):(\d+): (.+)$`),
        Severity: "error",
        Extract: func(m []string) BuildError {
            line, _ := strconv.Atoi(m[2])
            col, _ := strconv.Atoi(m[3])
            return BuildError{
                File:     m[1],
                Line:     line,
                Column:   col,
                Message:  m[4],
                Severity: "error",
            }
        },
    },
    // Package error
    {
        Regex:    regexp.MustCompile(`(?m)^package (.+): (.+)$`),
        Severity: "error",
    },
    // Import error
    {
        Regex:    regexp.MustCompile(`(?m)^(.+\.go):(\d+):(\d+): could not import (.+)$`),
        Severity: "error",
    },
    // Undefined error
    {
        Regex:    regexp.MustCompile(`(?m)^(.+\.go):(\d+):(\d+): undefined: (.+)$`),
        Severity: "error",
    },
}
```

---

## Executor Factory

```go
type ExecutorFactory struct {
    config *Config
    logger *LogService
}

func (f *ExecutorFactory) Create(runtime RuntimeType) (Executor, error) {
    switch runtime {
    case RuntimePowerShell:
        return &PowerShellExecutor{
            path:   f.config.Runtimes.PowerShell.Path,
            args:   f.config.Runtimes.PowerShell.Args,
            logger: f.logger,
        }, nil
        
    case RuntimeNodeJS:
        return &NodeJSExecutor{
            nodePath:       f.config.Runtimes.NodeJS.Path,
            packageManager: f.config.Runtimes.NodeJS.PackageManager,
            logger:         f.logger,
        }, nil
        
    case RuntimeGolang:
        return &GolangExecutor{
            goPath:     f.config.Runtimes.Golang.Path,
            buildFlags: f.config.Runtimes.Golang.BuildFlags,
            modTidy:    f.config.Runtimes.Golang.ModTidy,
            logger:     f.logger,
        }, nil
        
    default:
        return nil, fmt.Errorf("unknown runtime: %s", runtime)
    }
}
```

---

## Runtime Version Detection

```go
func (e *GolangExecutor) GetVersion() (string, error) {
    cmd := exec.Command(e.goPath, "version")
    output, err := cmd.Output()
    if err != nil {
        return "", err
    }
    // Parse: "go version go1.21.0 linux/amd64"
    return parseGoVersion(string(output)), nil
}

func (e *NodeJSExecutor) GetVersion() (string, error) {
    cmd := exec.Command(e.nodePath, "--version")
    output, err := cmd.Output()
    if err != nil {
        return "", err
    }
    return strings.TrimSpace(string(output)), nil
}

func (e *PowerShellExecutor) GetVersion() (string, error) {
    cmd := exec.Command(e.path, "-Command", "$PSVersionTable.PSVersion.ToString()")
    output, err := cmd.Output()
    if err != nil {
        return "", err
    }
    return strings.TrimSpace(string(output)), nil
}
```

---

## See Also

- [CLI Interface](./02-cli-interface.md)
- [Error Handling](./06-error-handling.md)
- [Integration API](./09-integration-api.md)
