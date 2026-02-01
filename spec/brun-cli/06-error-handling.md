# Error Handling

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Error capture, parsing, and reporting for build processes. Designed for AI-assisted error fixing workflows.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [Runtime Executors](./04-runtime-executors.md)
- [Integration API](./09-integration-api.md)
- [**Error Code Registry (Centralized)**](../../06-error-management/error-code-registry.md) — Authoritative source for all 7xxx error codes

---

## Error Capture Service

### Interface

```go
type ErrorCapture struct {
    logger      *LogService
    runID       string
    logDir      string
    stackParser *StackTraceParser
}

type BuildError struct {
    File       string `json:"file,omitempty"`
    Line       int    `json:"line,omitempty"`
    Column     int    `json:"column,omitempty"`
    Message    string `json:"message"`
    Severity   string `json:"severity"` // error, warning, info
    Code       string `json:"code,omitempty"` // TS2304, ESLint rule, etc.
    StackTrace string `json:"stackTrace,omitempty"`
    Context    string `json:"context,omitempty"` // Source code context
}

type ExecutionResult struct {
    RunID     string       `json:"runId"`
    Success   bool         `json:"success"`
    ExitCode  int          `json:"exitCode"`
    Stdout    string       `json:"stdout"`
    Stderr    string       `json:"stderr"`
    StartTime time.Time    `json:"startTime"`
    EndTime   time.Time    `json:"endTime"`
    Duration  time.Duration `json:"duration"`
    Errors    []BuildError `json:"errors,omitempty"`
    Warnings  []BuildError `json:"warnings,omitempty"`
    Port      int          `json:"port,omitempty"`
    LogPath   string       `json:"logPath,omitempty"`
}
```

---

## Error Parsing

### Pattern-Based Parsing

```go
type ErrorPattern struct {
    Name     string
    Regex    *regexp.Regexp
    Severity string
    Extract  func(matches []string) BuildError
}

type ErrorParser struct {
    patterns []ErrorPattern
}

func NewGoErrorParser() *ErrorParser {
    return &ErrorParser{
        patterns: []ErrorPattern{
            {
                Name:     "compile_error",
                Regex:    regexp.MustCompile(`^(.+\.go):(\d+):(\d+): (.+)$`),
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
            {
                Name:     "undefined",
                Regex:    regexp.MustCompile(`^(.+\.go):(\d+):(\d+): undefined: (.+)$`),
                Severity: "error",
            },
            {
                Name:     "import_error",
                Regex:    regexp.MustCompile(`^package (.+): cannot find package "(.+)"`),
                Severity: "error",
            },
            {
                Name:     "mod_error",
                Regex:    regexp.MustCompile(`^go: (.+)$`),
                Severity: "error",
            },
        },
    }
}

func (p *ErrorParser) Parse(output string) []BuildError {
    var errors []BuildError
    lines := strings.Split(output, "\n")
    
    for _, line := range lines {
        for _, pattern := range p.patterns {
            if matches := pattern.Regex.FindStringSubmatch(line); matches != nil {
                var err BuildError
                if pattern.Extract != nil {
                    err = pattern.Extract(matches)
                } else {
                    err = BuildError{
                        Message:  line,
                        Severity: pattern.Severity,
                    }
                }
                errors = append(errors, err)
                break
            }
        }
    }
    
    return errors
}
```

### Language-Specific Parsers

```go
// TypeScript/JavaScript
var tsPatterns = []ErrorPattern{
    {
        Regex: regexp.MustCompile(`^(.+)\((\d+),(\d+)\): error (TS\d+): (.+)$`),
        Extract: func(m []string) BuildError {
            return BuildError{
                File:    m[1],
                Line:    atoi(m[2]),
                Column:  atoi(m[3]),
                Code:    m[4],
                Message: m[5],
            }
        },
    },
}

// PowerShell
var psPatterns = []ErrorPattern{
    {
        Regex: regexp.MustCompile(`^At (.+):(\d+) char:(\d+)`),
    },
    {
        Regex: regexp.MustCompile(`^\+ (.+)$`), // Error line indicator
    },
}
```

---

## Stack Trace Parsing

```go
type StackTraceParser struct {
    maxDepth int
}

type StackFrame struct {
    Function string `json:"function"`
    File     string `json:"file"`
    Line     int    `json:"line"`
    Column   int    `json:"column,omitempty"`
}

func (p *StackTraceParser) ParseGoStack(stack string) []StackFrame {
    var frames []StackFrame
    lines := strings.Split(stack, "\n")
    
    for i := 0; i < len(lines)-1 && len(frames) < p.maxDepth; i += 2 {
        funcLine := strings.TrimSpace(lines[i])
        locLine := strings.TrimSpace(lines[i+1])
        
        // Parse: goroutine 1 [running]:
        // main.someFunction()
        //     /path/to/file.go:123 +0x45
        
        if !strings.HasPrefix(locLine, "\t") && !strings.HasPrefix(locLine, "    ") {
            continue
        }
        
        frame := StackFrame{Function: funcLine}
        
        // Parse location
        locMatch := regexp.MustCompile(`\s*(.+):(\d+)`).FindStringSubmatch(locLine)
        if len(locMatch) > 0 {
            frame.File = locMatch[1]
            frame.Line, _ = strconv.Atoi(locMatch[2])
        }
        
        frames = append(frames, frame)
    }
    
    return frames
}
```

---

## File Logging

### Run Folder Structure

```
logs/
├── run_20260129_143052/
│   ├── log.txt          # stdout
│   ├── error.txt        # stderr + parsed errors
│   ├── combined.txt     # merged output
│   └── meta.json        # run metadata
├── run_20260129_143155/
│   └── ...
└── latest/              # symlink to most recent run
```

### Log Writer

```go
type FileLogger struct {
    baseDir     string
    runID       string
    runDir      string
    stdoutFile  *os.File
    stderrFile  *os.File
    combinedFile *os.File
}

func (l *FileLogger) Initialize() error {
    l.runID = fmt.Sprintf("run_%s", time.Now().Format("20060102_150405"))
    l.runDir = filepath.Join(l.baseDir, l.runID)
    
    // Create run directory
    if err := os.MkdirAll(l.runDir, 0755); err != nil {
        return err
    }
    
    // Open log files
    var err error
    l.stdoutFile, err = os.Create(filepath.Join(l.runDir, "log.txt"))
    if err != nil {
        return err
    }
    
    l.stderrFile, err = os.Create(filepath.Join(l.runDir, "error.txt"))
    if err != nil {
        return err
    }
    
    l.combinedFile, err = os.Create(filepath.Join(l.runDir, "combined.txt"))
    if err != nil {
        return err
    }
    
    // Update latest symlink
    latestPath := filepath.Join(l.baseDir, "latest")
    os.Remove(latestPath)
    os.Symlink(l.runDir, latestPath)
    
    return nil
}

func (l *FileLogger) WriteMetadata(result *ExecutionResult) error {
    meta := map[string]interface{}{
        "runId":     l.runID,
        "success":   result.Success,
        "exitCode":  result.ExitCode,
        "startTime": result.StartTime,
        "endTime":   result.EndTime,
        "duration":  result.Duration.String(),
        "errors":    len(result.Errors),
        "warnings":  len(result.Warnings),
    }
    
    data, err := json.MarshalIndent(meta, "", "  ")
    if err != nil {
        return err
    }
    
    return os.WriteFile(
        filepath.Join(l.runDir, "meta.json"),
        data,
        0644,
    )
}

func (l *FileLogger) Cleanup(keepRuns int) error {
    entries, err := os.ReadDir(l.baseDir)
    if err != nil {
        return err
    }
    
    // Sort by modification time
    var runs []os.DirEntry
    for _, e := range entries {
        if e.IsDir() && strings.HasPrefix(e.Name(), "run_") {
            runs = append(runs, e)
        }
    }
    
    // Remove old runs
    if len(runs) > keepRuns {
        for _, run := range runs[:len(runs)-keepRuns] {
            os.RemoveAll(filepath.Join(l.baseDir, run.Name()))
        }
    }
    
    return nil
}
```

---

## JSON Output Format

### Full Result

```json
{
  "runId": "run_20260129_143052",
  "success": false,
  "exitCode": 1,
  "startTime": "2026-01-29T14:30:52Z",
  "endTime": "2026-01-29T14:30:55Z",
  "duration": "3.245s",
  "stdout": "Building...\n",
  "stderr": "main.go:15:10: undefined: SomeFunction\n",
  "errors": [
    {
      "file": "main.go",
      "line": 15,
      "column": 10,
      "message": "undefined: SomeFunction",
      "severity": "error",
      "stackTrace": null,
      "context": "    result := SomeFunction()"
    }
  ],
  "warnings": [],
  "logPath": "./logs/run_20260129_143052"
}
```

### Compact Error List (for AI)

```json
{
  "success": false,
  "errorCount": 2,
  "errors": [
    {
      "file": "main.go",
      "line": 15,
      "message": "undefined: SomeFunction"
    },
    {
      "file": "main.go",
      "line": 22,
      "message": "too many arguments to function"
    }
  ]
}
```

---

## Error Code Registry

Following the project's error registry standards, brun uses the **7xxx range** for CLI/Config errors.

> **📋 Canonical Reference:** The authoritative error code definitions are maintained in the [Central Error Code Registry](../../06-error-management/error-code-registry.md). This section provides a local reference; for the latest codes and cross-domain consistency, always consult the central registry.

### Error Code Ranges

| Range | Domain | Description |
|-------|--------|-------------|
| 7000-7099 | CLI General | Command-line parsing, flags, arguments |
| 7100-7199 | Configuration | Config file loading, validation, schema |
| 7200-7299 | Runtime Execution | PowerShell, Node.js, Go execution |
| 7300-7399 | Port Management | Port checking, firewall, network |
| 7400-7499 | Build Process | Compilation, linking, asset operations |
| 7500-7599 | Health Check | Application health monitoring |

### Complete Error Code Table

| Code | Constant | Exit | HTTP | Description | Retryable |
|------|----------|------|------|-------------|-----------|
| **CLI General (7000-7099)** |
| 7001 | `ERR_BRUN_INVALID_COMMAND` | 126 | 400 | Unknown or invalid command | No |
| 7002 | `ERR_BRUN_INVALID_FLAG` | 126 | 400 | Unknown or invalid flag | No |
| 7003 | `ERR_BRUN_MISSING_ARGUMENT` | 126 | 400 | Required argument not provided | No |
| 7004 | `ERR_BRUN_CONFLICTING_FLAGS` | 126 | 400 | Mutually exclusive flags specified | No |
| 7005 | `ERR_BRUN_BINARY_NOT_FOUND` | 127 | 500 | brun executable not in PATH | No |
| 7006 | `ERR_BRUN_VERSION_MISMATCH` | 126 | 400 | Config version incompatible with binary | No |
| **Configuration (7100-7199)** |
| 7101 | `ERR_BRUN_CONFIG_NOT_FOUND` | 2 | 404 | config.json not found at path | No |
| 7102 | `ERR_BRUN_CONFIG_PARSE_ERROR` | 2 | 400 | Invalid JSON in config file | No |
| 7103 | `ERR_BRUN_CONFIG_SCHEMA_INVALID` | 2 | 400 | Config does not match JSON schema | No |
| 7104 | `ERR_BRUN_CONFIG_PROFILE_NOT_FOUND` | 2 | 404 | Named profile not defined in config | No |
| 7105 | `ERR_BRUN_CONFIG_APP_NOT_FOUND` | 2 | 404 | Named application not defined in config | No |
| 7106 | `ERR_BRUN_CONFIG_RUNTIME_INVALID` | 2 | 400 | Invalid runtime type specified | No |
| 7107 | `ERR_BRUN_CONFIG_PATH_INVALID` | 2 | 400 | Invalid path in configuration | No |
| 7108 | `ERR_BRUN_CONFIG_WRITE_FAILED` | 2 | 500 | Failed to write config file | No |
| 7109 | `ERR_BRUN_CONFIG_PERMISSION` | 6 | 403 | Permission denied reading/writing config | No |
| **Runtime Execution (7200-7299)** |
| 7201 | `ERR_BRUN_RUNTIME_NOT_FOUND` | 3 | 500 | Runtime executable not found (go, node, pwsh) | No |
| 7202 | `ERR_BRUN_RUNTIME_VERSION` | 3 | 500 | Runtime version not supported | No |
| 7203 | `ERR_BRUN_RUNTIME_CRASHED` | 1 | 500 | Runtime process crashed unexpectedly | Yes |
| 7204 | `ERR_BRUN_RUNTIME_TIMEOUT` | 5 | 408 | Runtime execution exceeded timeout | Yes |
| 7205 | `ERR_BRUN_RUNTIME_PERMISSION` | 6 | 403 | Permission denied executing runtime | No |
| 7206 | `ERR_BRUN_RUNTIME_SIGNALED` | 130 | 500 | Runtime killed by signal (SIGINT/SIGTERM) | No |
| 7210 | `ERR_BRUN_GO_BUILD_FAILED` | 1 | 422 | Go compilation failed | No |
| 7211 | `ERR_BRUN_GO_MOD_TIDY_FAILED` | 8 | 422 | go mod tidy failed | No |
| 7212 | `ERR_BRUN_GO_UNDEFINED_SYMBOL` | 1 | 422 | Undefined variable/function in Go code | No |
| 7213 | `ERR_BRUN_GO_IMPORT_ERROR` | 1 | 422 | Go import/package not found | No |
| 7220 | `ERR_BRUN_NODE_BUILD_FAILED` | 1 | 422 | Node.js/npm build failed | No |
| 7221 | `ERR_BRUN_NODE_PACKAGE_MISSING` | 1 | 422 | npm package not installed | No |
| 7222 | `ERR_BRUN_NODE_SCRIPT_NOT_FOUND` | 7 | 404 | npm script not defined in package.json | No |
| 7223 | `ERR_BRUN_TS_COMPILE_ERROR` | 1 | 422 | TypeScript compilation error | No |
| 7230 | `ERR_BRUN_PS_SCRIPT_ERROR` | 1 | 422 | PowerShell script execution error | No |
| 7231 | `ERR_BRUN_PS_SYNTAX_ERROR` | 1 | 422 | PowerShell syntax error | No |
| 7232 | `ERR_BRUN_PS_CMDLET_NOT_FOUND` | 1 | 422 | PowerShell cmdlet not found | No |
| **Port Management (7300-7399)** |
| 7301 | `ERR_BRUN_PORT_UNAVAILABLE` | 4 | 409 | Requested port in use, no fallback available | Yes |
| 7302 | `ERR_BRUN_PORT_PERMISSION` | 6 | 403 | Permission denied binding to port (<1024) | No |
| 7303 | `ERR_BRUN_PORT_INVALID` | 4 | 400 | Invalid port number (0, >65535) | No |
| 7304 | `ERR_BRUN_FIREWALL_FAILED` | 10 | 500 | Firewall rule creation/deletion failed | No |
| 7305 | `ERR_BRUN_FIREWALL_PERMISSION` | 6 | 403 | Insufficient privileges for firewall ops | No |
| 7306 | `ERR_BRUN_FIREWALL_NOT_FOUND` | 10 | 404 | Firewall rule not found for deletion | No |
| 7307 | `ERR_BRUN_NETWORK_UNREACHABLE` | 4 | 503 | Network interface not available | Yes |
| **Build Process (7400-7499)** |
| 7401 | `ERR_BRUN_BUILD_FAILED` | 1 | 422 | General build failure | No |
| 7402 | `ERR_BRUN_SOURCE_NOT_FOUND` | 7 | 404 | Source path does not exist | No |
| 7403 | `ERR_BRUN_OUTPUT_DIR_FAILED` | 7 | 500 | Cannot create output directory | No |
| 7404 | `ERR_BRUN_ASSET_COPY_FAILED` | 9 | 500 | Asset copy operation failed | No |
| 7405 | `ERR_BRUN_ASSET_CLEAR_FAILED` | 9 | 500 | Asset clear operation failed | No |
| 7406 | `ERR_BRUN_ASSET_SOURCE_MISSING` | 7 | 404 | Asset source path not found | No |
| 7407 | `ERR_BRUN_WORKDIR_NOT_FOUND` | 7 | 404 | Working directory does not exist | No |
| 7408 | `ERR_BRUN_WORKDIR_PERMISSION` | 6 | 403 | Working directory not accessible | No |
| 7409 | `ERR_BRUN_EXTERNAL_DIR_BLOCKED` | 6 | 403 | External directory access denied (allowExternalDirs=false) | No |
| 7410 | `ERR_BRUN_PATH_TRAVERSAL` | 6 | 403 | Path traversal attempt blocked | No |
| **Health Check (7500-7599)** |
| 7501 | `ERR_BRUN_HEALTH_TIMEOUT` | 5 | 408 | Health check did not pass in time | Yes |
| 7502 | `ERR_BRUN_HEALTH_FAILED` | 1 | 503 | Health check endpoint returned error | Yes |
| 7503 | `ERR_BRUN_HEALTH_UNREACHABLE` | 1 | 503 | Health check endpoint unreachable | Yes |
| 7504 | `ERR_BRUN_HEALTH_STATUS_MISMATCH` | 1 | 422 | Unexpected HTTP status from health endpoint | No |
| 7505 | `ERR_BRUN_HEALTH_BODY_MISMATCH` | 1 | 422 | Health response body did not match expected | No |

### Error Code Implementation

```go
package errors

// Error code constants for brun CLI
const (
    // CLI General (7000-7099)
    ErrBrunInvalidCommand    = 7001
    ErrBrunInvalidFlag       = 7002
    ErrBrunMissingArgument   = 7003
    ErrBrunConflictingFlags  = 7004
    ErrBrunBinaryNotFound    = 7005
    ErrBrunVersionMismatch   = 7006
    
    // Configuration (7100-7199)
    ErrBrunConfigNotFound       = 7101
    ErrBrunConfigParseError     = 7102
    ErrBrunConfigSchemaInvalid  = 7103
    ErrBrunConfigProfileNotFound = 7104
    ErrBrunConfigAppNotFound    = 7105
    ErrBrunConfigRuntimeInvalid = 7106
    ErrBrunConfigPathInvalid    = 7107
    ErrBrunConfigWriteFailed    = 7108
    ErrBrunConfigPermission     = 7109
    
    // Runtime Execution (7200-7299)
    ErrBrunRuntimeNotFound   = 7201
    ErrBrunRuntimeVersion    = 7202
    ErrBrunRuntimeCrashed    = 7203
    ErrBrunRuntimeTimeout    = 7204
    ErrBrunRuntimePermission = 7205
    ErrBrunRuntimeSignaled   = 7206
    ErrBrunGoBuildFailed     = 7210
    ErrBrunGoModTidyFailed   = 7211
    ErrBrunGoUndefinedSymbol = 7212
    ErrBrunGoImportError     = 7213
    ErrBrunNodeBuildFailed   = 7220
    ErrBrunNodePackageMissing = 7221
    ErrBrunNodeScriptNotFound = 7222
    ErrBrunTsCompileError    = 7223
    ErrBrunPsScriptError     = 7230
    ErrBrunPsSyntaxError     = 7231
    ErrBrunPsCmdletNotFound  = 7232
    
    // Port Management (7300-7399)
    ErrBrunPortUnavailable     = 7301
    ErrBrunPortPermission      = 7302
    ErrBrunPortInvalid         = 7303
    ErrBrunFirewallFailed      = 7304
    ErrBrunFirewallPermission  = 7305
    ErrBrunFirewallNotFound    = 7306
    ErrBrunNetworkUnreachable  = 7307
    
    // Build Process (7400-7499)
    ErrBrunBuildFailed        = 7401
    ErrBrunSourceNotFound     = 7402
    ErrBrunOutputDirFailed    = 7403
    ErrBrunAssetCopyFailed    = 7404
    ErrBrunAssetClearFailed   = 7405
    ErrBrunAssetSourceMissing = 7406
    ErrBrunWorkdirNotFound    = 7407
    ErrBrunWorkdirPermission  = 7408
    ErrBrunExternalDirBlocked = 7409
    ErrBrunPathTraversal      = 7410
    
    // Health Check (7500-7599)
    ErrBrunHealthTimeout       = 7501
    ErrBrunHealthFailed        = 7502
    ErrBrunHealthUnreachable   = 7503
    ErrBrunHealthStatusMismatch = 7504
    ErrBrunHealthBodyMismatch  = 7505
)

// BrunError represents a structured error with code
type BrunError struct {
    Code       int    `json:"code"`
    Constant   string `json:"constant"`
    Message    string `json:"message"`
    Details    string `json:"details,omitempty"`
    Retryable  bool   `json:"retryable"`
    ExitCode   int    `json:"exitCode"`
}

func (e *BrunError) Error() string {
    return fmt.Sprintf("[%d] %s: %s", e.Code, e.Constant, e.Message)
}

// Error constructors
func NewConfigNotFoundError(path string) *BrunError {
    return &BrunError{
        Code:      ErrBrunConfigNotFound,
        Constant:  "ERR_BRUN_CONFIG_NOT_FOUND",
        Message:   "Configuration file not found",
        Details:   fmt.Sprintf("path: %s", path),
        Retryable: false,
        ExitCode:  2,
    }
}

func NewPortUnavailableError(port int, fallbackTried []int) *BrunError {
    return &BrunError{
        Code:      ErrBrunPortUnavailable,
        Constant:  "ERR_BRUN_PORT_UNAVAILABLE",
        Message:   fmt.Sprintf("Port %d unavailable, all fallbacks exhausted", port),
        Details:   fmt.Sprintf("tried ports: %v", fallbackTried),
        Retryable: true,
        ExitCode:  4,
    }
}

func NewHealthTimeoutError(url string, timeout time.Duration) *BrunError {
    return &BrunError{
        Code:      ErrBrunHealthTimeout,
        Constant:  "ERR_BRUN_HEALTH_TIMEOUT",
        Message:   "Health check did not pass within timeout",
        Details:   fmt.Sprintf("url: %s, timeout: %s", url, timeout),
        Retryable: true,
        ExitCode:  5,
    }
}

func NewGoBuildError(file string, line int, message string) *BrunError {
    return &BrunError{
        Code:      ErrBrunGoBuildFailed,
        Constant:  "ERR_BRUN_GO_BUILD_FAILED",
        Message:   message,
        Details:   fmt.Sprintf("file: %s, line: %d", file, line),
        Retryable: false,
        ExitCode:  1,
    }
}
```

### Exit Code Mapping

| Exit Code | Error Range | Description |
|-----------|-------------|-------------|
| 0 | - | Success |
| 1 | 7210-7232, 7401, 7501-7505 | Build/execution failed |
| 2 | 7101-7109 | Configuration error |
| 3 | 7201-7202 | Runtime not found |
| 4 | 7301, 7303, 7307 | Port/network error |
| 5 | 7204, 7501 | Timeout |
| 6 | 7109, 7205, 7302, 7305, 7408-7410 | Permission denied |
| 7 | 7402-7403, 7406-7407, 7222 | Path not found |
| 8 | 7211 | go mod tidy failed |
| 9 | 7404-7405 | Asset operation failed |
| 10 | 7304, 7306 | Firewall error |
| 126 | 7001-7006 | Command/flag error |
| 127 | 7005 | Binary not found |
| 130 | 7206 | Killed by SIGINT |

### JSON Error Response

When `--json` flag is used, errors are returned in structured format:

```json
{
  "runId": "run_20260129_143052",
  "success": false,
  "exitCode": 1,
  "error": {
    "code": 7210,
    "constant": "ERR_BRUN_GO_BUILD_FAILED",
    "message": "Go compilation failed",
    "details": "file: main.go, line: 15",
    "retryable": false,
    "exitCode": 1
  },
  "errors": [
    {
      "file": "main.go",
      "line": 15,
      "column": 10,
      "message": "undefined: SomeFunction",
      "severity": "error",
      "code": "7212"
    }
  ],
  "logPath": "./logs/run_20260129_143052"
}
```

---

## See Also

- [Runtime Executors](./04-runtime-executors.md)
- [Integration API](./09-integration-api.md)
- [Acceptance Criteria](./11-acceptance-criteria.md)
