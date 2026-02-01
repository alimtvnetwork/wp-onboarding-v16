# Integration API

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Communication protocol for integrating brun with the main Spec Management application, especially for AI-assisted error fixing loops.

**Cross-References:**
- [Error Handling](./06-error-handling.md)
- [CLI Interface](./02-cli-interface.md)
- [AI Integration](../06-ai-integration/00-overview.md)

---

## Integration Modes

### 1. CLI Subprocess Mode

Main application spawns brun as a subprocess and reads JSON output.

```go
// In main application
func (app *App) runBuildCheck(profile string) (*BuildResult, error) {
    cmd := exec.Command("brun", "check", 
        "--profile", profile,
        "--json",
        "--tidy", "run",
    )
    
    output, err := cmd.Output()
    if err != nil {
        // Parse stderr for error details
        var exitErr *exec.ExitError
        if errors.As(err, &exitErr) {
            output = exitErr.Stderr
        }
    }
    
    var result BuildResult
    if err := json.Unmarshal(output, &result); err != nil {
        return nil, fmt.Errorf("failed to parse brun output: %w", err)
    }
    
    return &result, nil
}
```

### 2. Library Import Mode (Future)

Import brun as a Go library.

```go
import "github.com/user/brun/pkg/runner"

func (app *App) runBuildCheck(profile string) (*runner.ExecutionResult, error) {
    r := runner.New(runner.Config{
        ConfigPath: "./config.json",
    })
    
    return r.ExecuteProfile(context.Background(), profile)
}
```

---

## Subprocess Call Patterns

> **Note:** brun contains NO AI capabilities. All AI processing occurs in the main Spec Management application. brun is invoked purely as a subprocess.

### BrunRunner Service

The main application wraps all brun interactions in a dedicated service:

```go
package brun

import (
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "os"
    "os/exec"
    "path/filepath"
    "time"
)

// BrunRunner manages subprocess calls to the brun CLI
type BrunRunner struct {
    binaryPath   string        // Path to brun executable
    configPath   string        // Path to config.json
    workDir      string        // Working directory for execution
    timeout      time.Duration // Default timeout for commands
    env          []string      // Additional environment variables
}

// NewBrunRunner creates a new runner instance
func NewBrunRunner(opts ...Option) *BrunRunner {
    r := &BrunRunner{
        binaryPath: "brun",           // Assumes brun is in PATH
        configPath: "./config.json",
        workDir:    ".",
        timeout:    5 * time.Minute,
        env:        os.Environ(),
    }
    for _, opt := range opts {
        opt(r)
    }
    return r
}

// Option pattern for configuration
type Option func(*BrunRunner)

func WithBinaryPath(path string) Option {
    return func(r *BrunRunner) { r.binaryPath = path }
}

func WithConfigPath(path string) Option {
    return func(r *BrunRunner) { r.configPath = path }
}

func WithWorkDir(dir string) Option {
    return func(r *BrunRunner) { r.workDir = dir }
}

func WithTimeout(d time.Duration) Option {
    return func(r *BrunRunner) { r.timeout = d }
}

func WithEnv(key, value string) Option {
    return func(r *BrunRunner) {
        r.env = append(r.env, fmt.Sprintf("%s=%s", key, value))
    }
}
```

### Core Execution Method

```go
// ExecutionResult represents parsed brun JSON output
type ExecutionResult struct {
    RunID     string        `json:"runId"`
    Success   bool          `json:"success"`
    ExitCode  int           `json:"exitCode"`
    StartTime time.Time     `json:"startTime"`
    EndTime   time.Time     `json:"endTime"`
    Duration  string        `json:"duration"`
    Stdout    string        `json:"stdout"`
    Stderr    string        `json:"stderr"`
    Errors    []BuildError  `json:"errors"`
    Warnings  []BuildError  `json:"warnings"`
    Port      int           `json:"port,omitempty"`
    LogPath   string        `json:"logPath"`
}

// BuildError represents a single build error
type BuildError struct {
    File     string `json:"file"`
    Line     int    `json:"line"`
    Column   int    `json:"column"`
    Message  string `json:"message"`
    Severity string `json:"severity"`
    Context  string `json:"context"`
    Code     string `json:"code,omitempty"`     // Error code if available
    Source   string `json:"source,omitempty"`   // Source line content
}

// execute runs brun with given arguments and parses JSON output
func (r *BrunRunner) execute(ctx context.Context, args ...string) (*ExecutionResult, error) {
    // Always request JSON output
    args = append(args, "--json", "--config", r.configPath)
    
    // Create command with context for timeout
    ctx, cancel := context.WithTimeout(ctx, r.timeout)
    defer cancel()
    
    cmd := exec.CommandContext(ctx, r.binaryPath, args...)
    cmd.Dir = r.workDir
    cmd.Env = r.env
    
    // Capture both stdout and stderr
    var stdout, stderr bytes.Buffer
    cmd.Stdout = &stdout
    cmd.Stderr = &stderr
    
    // Execute command
    err := cmd.Run()
    
    // Parse JSON from stdout (brun writes JSON to stdout when --json is set)
    var result ExecutionResult
    if stdout.Len() > 0 {
        if parseErr := json.Unmarshal(stdout.Bytes(), &result); parseErr != nil {
            return nil, fmt.Errorf("failed to parse brun JSON output: %w\nraw output: %s", 
                parseErr, stdout.String())
        }
    }
    
    // Handle execution errors
    if err != nil {
        // Context deadline exceeded
        if ctx.Err() == context.DeadlineExceeded {
            return nil, fmt.Errorf("brun execution timed out after %v", r.timeout)
        }
        
        // Exit error - brun completed but with non-zero exit code
        var exitErr *exec.ExitError
        if errors.As(err, &exitErr) {
            // If we have parsed result, return it (contains error details)
            if result.RunID != "" {
                return &result, nil
            }
            // Otherwise, construct error from stderr
            return nil, fmt.Errorf("brun exited with code %d: %s", 
                exitErr.ExitCode(), stderr.String())
        }
        
        // Other errors (binary not found, permission denied, etc.)
        return nil, fmt.Errorf("failed to execute brun: %w", err)
    }
    
    return &result, nil
}
```

### Command-Specific Methods

```go
// Check runs build verification without producing artifacts
func (r *BrunRunner) Check(ctx context.Context, opts CheckOptions) (*ExecutionResult, error) {
    args := []string{"check"}
    
    if opts.Profile != "" {
        args = append(args, "--profile", opts.Profile)
    }
    if opts.GoPath != "" {
        args = append(args, "--go", opts.GoPath)
    }
    if opts.NodePath != "" {
        args = append(args, "--node", opts.NodePath)
    }
    if opts.PSScript != "" {
        args = append(args, "--ps", opts.PSScript)
    }
    if opts.Tidy != "" {
        args = append(args, "--tidy", opts.Tidy)
    }
    if opts.Port > 0 {
        args = append(args, "--port", fmt.Sprintf("%d", opts.Port))
    }
    
    return r.execute(ctx, args...)
}

type CheckOptions struct {
    Profile  string
    GoPath   string
    NodePath string
    PSScript string
    Tidy     string // "skip", "run", "force"
    Port     int
}

// Build executes a full build using a profile
func (r *BrunRunner) Build(ctx context.Context, opts BuildOptions) (*ExecutionResult, error) {
    args := []string{"build"}
    
    if opts.Profile != "" {
        args = append(args, "--profile", opts.Profile)
    }
    if opts.Clean {
        args = append(args, "--clean")
    }
    if opts.CopyAssets {
        args = append(args, "--copy-assets")
    }
    if opts.Output != "" {
        args = append(args, "--output", opts.Output)
    }
    
    return r.execute(ctx, args...)
}

type BuildOptions struct {
    Profile    string
    Clean      bool
    CopyAssets bool
    Output     string
}

// Run executes application with health check waiting
func (r *BrunRunner) Run(ctx context.Context, opts RunOptions) (*ExecutionResult, error) {
    args := []string{"run"}
    
    if opts.App != "" {
        args = append(args, "--app", opts.App)
    }
    if opts.GoPath != "" {
        args = append(args, "--go", opts.GoPath)
    }
    if opts.Port > 0 {
        args = append(args, "--port", fmt.Sprintf("%d", opts.Port))
    }
    if opts.HealthCheck != "" {
        args = append(args, "--health-check", opts.HealthCheck)
    }
    if opts.HealthTimeout > 0 {
        args = append(args, "--health-timeout", opts.HealthTimeout.String())
    }
    if opts.Detach {
        args = append(args, "--detach")
    }
    if opts.WorkDir != "" {
        args = append(args, "--workdir", opts.WorkDir)
    }
    for k, v := range opts.Env {
        args = append(args, "--env", fmt.Sprintf("%s=%s", k, v))
    }
    
    return r.execute(ctx, args...)
}

type RunOptions struct {
    App           string
    GoPath        string
    NodeCmd       string
    PSScript      string
    Port          int
    HealthCheck   string
    HealthTimeout time.Duration
    Detach        bool
    WorkDir       string
    Env           map[string]string
}

// Port checks port availability
func (r *BrunRunner) Port(ctx context.Context, opts PortOptions) (*PortResult, error) {
    args := []string{"port"}
    
    if opts.Check > 0 {
        args = append(args, "--check", fmt.Sprintf("%d", opts.Check))
    }
    if len(opts.Fallback) > 0 {
        ports := make([]string, len(opts.Fallback))
        for i, p := range opts.Fallback {
            ports[i] = fmt.Sprintf("%d", p)
        }
        args = append(args, "--fallback", strings.Join(ports, ","))
    }
    if opts.Enable > 0 {
        args = append(args, "--enable", fmt.Sprintf("%d", opts.Enable))
    }
    if opts.RuleName != "" {
        args = append(args, "--name", opts.RuleName)
    }
    
    result, err := r.execute(ctx, args...)
    if err != nil {
        return nil, err
    }
    
    // Parse port-specific result
    var portResult PortResult
    // Re-parse from the raw JSON for port-specific fields
    // (In practice, you'd have a unified response or specific endpoint)
    portResult.RequestedPort = opts.Check
    portResult.Available = result.Success
    
    return &portResult, nil
}

type PortOptions struct {
    Check    int
    Fallback []int
    Enable   int
    Disable  int
    RuleName string
}

type PortResult struct {
    RequestedPort  int           `json:"requestedPort"`
    AvailablePort  int           `json:"availablePort"`
    Available      bool          `json:"available"`
    CheckedPorts   []PortStatus  `json:"checkedPorts"`
}

type PortStatus struct {
    Port      int    `json:"port"`
    Available bool   `json:"available"`
    Reason    string `json:"reason,omitempty"`
}

// ValidateConfig validates a configuration file
func (r *BrunRunner) ValidateConfig(ctx context.Context, configPath string) (*ExecutionResult, error) {
    args := []string{"config", "validate"}
    if configPath != "" {
        args = append(args, "--config", configPath)
    }
    return r.execute(ctx, args...)
}

// Health checks brun availability and runtime status
func (r *BrunRunner) Health(ctx context.Context) (*HealthResult, error) {
    args := []string{"--health"}
    
    result, err := r.execute(ctx, args...)
    if err != nil {
        return nil, err
    }
    
    // Parse health-specific response
    // (Simplified - actual implementation would parse full health JSON)
    return &HealthResult{
        Status:  "healthy",
        Version: "1.0.0",
    }, nil
}

type HealthResult struct {
    Status   string                    `json:"status"`
    Version  string                    `json:"version"`
    Runtimes map[string]RuntimeStatus  `json:"runtimes"`
    Config   ConfigStatus              `json:"config"`
}

type RuntimeStatus struct {
    Available bool   `json:"available"`
    Version   string `json:"version"`
}

type ConfigStatus struct {
    Loaded bool   `json:"loaded"`
    Path   string `json:"path"`
}
```

### JSON Parsing Utilities

```go
package brun

import (
    "encoding/json"
    "io"
    "os"
)

// ParseResultFromFile reads a brun result from a log file
func ParseResultFromFile(path string) (*ExecutionResult, error) {
    f, err := os.Open(path)
    if err != nil {
        return nil, err
    }
    defer f.Close()
    
    return ParseResultFromReader(f)
}

// ParseResultFromReader parses brun JSON from any reader
func ParseResultFromReader(r io.Reader) (*ExecutionResult, error) {
    var result ExecutionResult
    if err := json.NewDecoder(r).Decode(&result); err != nil {
        return nil, err
    }
    return &result, nil
}

// ParseResultFromBytes parses brun JSON from byte slice
func ParseResultFromBytes(data []byte) (*ExecutionResult, error) {
    var result ExecutionResult
    if err := json.Unmarshal(data, &result); err != nil {
        return nil, err
    }
    return &result, nil
}

// ErrorsToMap converts build errors to a file-grouped map for AI processing
func ErrorsToMap(errors []BuildError) map[string][]BuildError {
    result := make(map[string][]BuildError)
    for _, err := range errors {
        result[err.File] = append(result[err.File], err)
    }
    return result
}

// ErrorsToMarkdown formats errors as markdown for AI prompts
func ErrorsToMarkdown(errors []BuildError) string {
    var sb strings.Builder
    
    grouped := ErrorsToMap(errors)
    
    for file, fileErrors := range grouped {
        sb.WriteString(fmt.Sprintf("### File: `%s`\n\n", file))
        for _, err := range fileErrors {
            sb.WriteString(fmt.Sprintf("- **Line %d, Col %d**: %s\n", 
                err.Line, err.Column, err.Message))
            if err.Context != "" {
                sb.WriteString(fmt.Sprintf("  ```\n  %s\n  ```\n", err.Context))
            }
        }
        sb.WriteString("\n")
    }
    
    return sb.String()
}
```

### Error Handling Patterns

```go
package brun

import (
    "errors"
    "fmt"
)

// Error constants matching brun exit codes
const (
    ExitSuccess          = 0
    ExitBuildFailed      = 1
    ExitConfigError      = 2
    ExitRuntimeMissing   = 3
    ExitPortUnavailable  = 4
    ExitTimeout          = 5
    ExitPermissionDenied = 6
    ExitPathNotFound     = 7
)

// BrunError wraps brun execution errors with context
type BrunError struct {
    ExitCode int
    Message  string
    Result   *ExecutionResult
}

func (e *BrunError) Error() string {
    return fmt.Sprintf("brun error (exit %d): %s", e.ExitCode, e.Message)
}

// IsRetryable returns true if the error might succeed on retry
func (e *BrunError) IsRetryable() bool {
    switch e.ExitCode {
    case ExitBuildFailed:
        return true  // AI can fix code errors
    case ExitPortUnavailable:
        return true  // Port might free up
    case ExitTimeout:
        return true  // Transient issue
    default:
        return false
    }
}

// RequiresAIFix returns true if AI code fixing might help
func (e *BrunError) RequiresAIFix() bool {
    return e.ExitCode == ExitBuildFailed && e.Result != nil && len(e.Result.Errors) > 0
}

// NewBrunError creates error from execution result
func NewBrunError(result *ExecutionResult) *BrunError {
    msg := "build failed"
    if len(result.Errors) > 0 {
        msg = result.Errors[0].Message
    }
    return &BrunError{
        ExitCode: result.ExitCode,
        Message:  msg,
        Result:   result,
    }
}

// HandleResult processes execution result and returns appropriate error
func HandleResult(result *ExecutionResult) error {
    if result.Success {
        return nil
    }
    return NewBrunError(result)
}
```

### Usage in AI Error Fixing Loop

```go
package main

import (
    "context"
    "log"
    "time"
    
    "specmgr/internal/ai"
    "specmgr/internal/brun"
)

type BuildService struct {
    runner    *brun.BrunRunner
    aiService *ai.Service
    maxRetry  int
}

func NewBuildService(brunPath, configPath string, aiSvc *ai.Service) *BuildService {
    return &BuildService{
        runner: brun.NewBrunRunner(
            brun.WithBinaryPath(brunPath),
            brun.WithConfigPath(configPath),
            brun.WithTimeout(10*time.Minute),
        ),
        aiService: aiSvc,
        maxRetry:  5,
    }
}

// BuildWithAutoFix attempts to build, using AI to fix errors automatically
func (s *BuildService) BuildWithAutoFix(ctx context.Context, profile string) error {
    for attempt := 1; attempt <= s.maxRetry; attempt++ {
        log.Printf("Build attempt %d/%d for profile: %s", attempt, s.maxRetry, profile)
        
        // Step 1: Run brun check
        result, err := s.runner.Check(ctx, brun.CheckOptions{
            Profile: profile,
            Tidy:    "run",
        })
        if err != nil {
            return fmt.Errorf("brun execution failed: %w", err)
        }
        
        // Step 2: Check success
        if result.Success {
            log.Printf("Build succeeded on attempt %d", attempt)
            return nil
        }
        
        // Step 3: No errors to fix?
        if len(result.Errors) == 0 {
            return fmt.Errorf("build failed but no errors captured (exit code: %d)", 
                result.ExitCode)
        }
        
        log.Printf("Found %d build errors, requesting AI fix...", len(result.Errors))
        
        // Step 4: Format errors for AI
        errorMarkdown := brun.ErrorsToMarkdown(result.Errors)
        
        // Step 5: Get AI fix (AI runs in main app, NOT in brun)
        fixes, err := s.aiService.GenerateCodeFixes(ctx, ai.FixRequest{
            Errors:      errorMarkdown,
            ProjectPath: s.runner.WorkDir(),
            Language:    detectLanguage(profile),
        })
        if err != nil {
            return fmt.Errorf("AI fix generation failed: %w", err)
        }
        
        // Step 6: Apply fixes to filesystem
        for _, fix := range fixes {
            if err := applyFix(fix); err != nil {
                return fmt.Errorf("failed to apply fix to %s: %w", fix.File, err)
            }
            log.Printf("Applied fix to %s", fix.File)
        }
        
        // Step 7: Loop back for retry
        log.Printf("Fixes applied, retrying build...")
    }
    
    return fmt.Errorf("max retry attempts (%d) exceeded", s.maxRetry)
}

func detectLanguage(profile string) string {
    // Logic to determine language from profile
    return "go" // Simplified
}

func applyFix(fix ai.CodeFix) error {
    // Write fix.Content to fix.File
    return os.WriteFile(fix.File, []byte(fix.Content), 0644)
}
```

## AI Error Fixing Loop

### Workflow

```
┌─────────────────┐
│  Main App       │
│  (AI Orchestrator)│
└────────┬────────┘
         │ 1. Trigger build check
         ▼
┌─────────────────┐
│  brun check     │
│  --json --go    │
└────────┬────────┘
         │ 2. Return JSON with errors
         ▼
┌─────────────────┐
│  Error Parser   │
│  (Main App)     │
└────────┬────────┘
         │ 3. Format errors for AI
         ▼
┌─────────────────┐
│  AI Model       │
│  (Code Fixer)   │
└────────┬────────┘
         │ 4. Generate fix
         ▼
┌─────────────────┐
│  File Writer    │
│  (Main App)     │
└────────┬────────┘
         │ 5. Apply fix to source
         ▼
    ┌────┴────┐
    │ Retry?  │──── No ──► Done
    └────┬────┘
         │ Yes
         └──────────► Back to step 1
```

### Implementation

```go
type AIErrorFixer struct {
    maxRetries int
    runner     *BrunRunner
    ai         *AIService
    fileWriter *FileWriter
}

func (f *AIErrorFixer) FixBuildErrors(ctx context.Context, profile string) error {
    for attempt := 0; attempt < f.maxRetries; attempt++ {
        // Run build check
        result, err := f.runner.Check(ctx, profile)
        if err != nil {
            return fmt.Errorf("build check failed: %w", err)
        }
        
        // Success - no errors
        if result.Success {
            f.log.Info("Build successful", "attempts", attempt+1)
            return nil
        }
        
        // No errors but failed (shouldn't happen)
        if len(result.Errors) == 0 {
            return fmt.Errorf("build failed but no errors captured")
        }
        
        // Format errors for AI
        prompt := f.formatErrorsForAI(result.Errors)
        
        // Get AI fix
        fix, err := f.ai.GenerateFix(ctx, prompt)
        if err != nil {
            return fmt.Errorf("AI fix generation failed: %w", err)
        }
        
        // Apply fix
        if err := f.fileWriter.ApplyFix(fix); err != nil {
            return fmt.Errorf("failed to apply fix: %w", err)
        }
        
        f.log.Info("Applied AI fix", "attempt", attempt+1, "errors", len(result.Errors))
    }
    
    return fmt.Errorf("max retries (%d) exceeded", f.maxRetries)
}

func (f *AIErrorFixer) formatErrorsForAI(errors []BuildError) string {
    var sb strings.Builder
    sb.WriteString("The following build errors occurred:\n\n")
    
    for i, err := range errors {
        sb.WriteString(fmt.Sprintf("%d. File: %s (line %d)\n", i+1, err.File, err.Line))
        sb.WriteString(fmt.Sprintf("   Error: %s\n", err.Message))
        if err.Context != "" {
            sb.WriteString(fmt.Sprintf("   Context: %s\n", err.Context))
        }
        sb.WriteString("\n")
    }
    
    sb.WriteString("Please provide fixes for these errors.\n")
    return sb.String()
}
```

---

## JSON Response Schema

### Success Response

```json
{
  "runId": "run_20260129_143052",
  "success": true,
  "exitCode": 0,
  "startTime": "2026-01-29T14:30:52Z",
  "endTime": "2026-01-29T14:30:55Z",
  "duration": "3.245s",
  "stdout": "Build succeeded.\n",
  "stderr": "",
  "errors": [],
  "warnings": [],
  "port": 8080,
  "logPath": "./logs/run_20260129_143052"
}
```

### Error Response

```json
{
  "runId": "run_20260129_143155",
  "success": false,
  "exitCode": 1,
  "startTime": "2026-01-29T14:31:55Z",
  "endTime": "2026-01-29T14:31:57Z",
  "duration": "2.102s",
  "stdout": "",
  "stderr": "cmd/api/main.go:15:10: undefined: handlers.NewRouter\ncmd/api/main.go:22:5: too many arguments in call to db.Connect\n",
  "errors": [
    {
      "file": "cmd/api/main.go",
      "line": 15,
      "column": 10,
      "message": "undefined: handlers.NewRouter",
      "severity": "error",
      "context": "\trouter := handlers.NewRouter()"
    },
    {
      "file": "cmd/api/main.go",
      "line": 22,
      "column": 5,
      "message": "too many arguments in call to db.Connect",
      "severity": "error",
      "context": "\tconn := db.Connect(cfg.Database, cfg.Debug, cfg.Timeout)"
    }
  ],
  "warnings": [],
  "logPath": "./logs/run_20260129_143155"
}
```

---

## Exit Code Reference

| Code | Constant | Description | Action |
|------|----------|-------------|--------|
| 0 | `EXIT_SUCCESS` | Build/check successful | Continue |
| 1 | `EXIT_BUILD_FAILED` | Build failed with errors | Parse errors, retry with AI |
| 2 | `EXIT_CONFIG_ERROR` | Configuration invalid | Fix config, retry |
| 3 | `EXIT_RUNTIME_MISSING` | Runtime not found | Install runtime |
| 4 | `EXIT_PORT_UNAVAILABLE` | No port available | Wait or change ports |
| 5 | `EXIT_TIMEOUT` | Execution timeout | Increase timeout |
| 6 | `EXIT_PERMISSION_DENIED` | Permission error | Check permissions |
| 7 | `EXIT_PATH_NOT_FOUND` | Source not found | Check paths |

---

## Environment Variables

brun respects environment variables set by the parent process:

| Variable | Description |
|----------|-------------|
| `BRUN_CONFIG` | Override config file path |
| `BRUN_LOG_DIR` | Override log directory |
| `BRUN_JSON_OUTPUT` | Force JSON output (true/false) |
| `BRUN_VERBOSE` | Enable verbose logging |
| `BRUN_NO_COLOR` | Disable colored output |
| `BRUN_TIMEOUT` | Override default timeout |

---

## Streaming Output (Future)

For long-running builds, stream output via JSON lines:

```bash
brun build --profile backend --stream
```

```json
{"type":"start","runId":"run_20260129_143052","timestamp":"2026-01-29T14:30:52Z"}
{"type":"stdout","line":"Compiling main.go..."}
{"type":"stdout","line":"Compiling handlers/..."}
{"type":"stderr","line":"warning: unused variable 'x'"}
{"type":"progress","percent":75,"message":"Linking..."}
{"type":"complete","success":true,"exitCode":0,"duration":"12.5s"}
```

---

## Health Check

```bash
brun --health
```

```json
{
  "status": "healthy",
  "version": "1.0.0",
  "runtimes": {
    "powershell": {"available": true, "version": "7.4.0"},
    "nodejs": {"available": true, "version": "20.10.0"},
    "golang": {"available": true, "version": "1.21.0"}
  },
  "config": {"loaded": true, "path": "./config.json"},
  "ports": {"default": 8080, "fallbackCount": 5}
}
```

---

## See Also

- [Error Handling](./06-error-handling.md)
- [CLI Interface](./02-cli-interface.md)
- [AI Integration](../06-ai-integration/00-overview.md)
