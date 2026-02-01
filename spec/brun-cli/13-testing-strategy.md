# Build Runner CLI - Testing Strategy

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Summary

Integration-focused testing strategy for the Build Runner CLI (`brun`). Tests validate runtime executor behavior, error capture accuracy, subprocess handling, and cross-platform compatibility without requiring actual build environments.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [Runtime Executors](./04-runtime-executors.md)
- [Error Handling](./06-error-handling.md)
- [Implementation Guide](./14-implementation-guide.md)

---

## Testing Approach

```
┌─────────────────────────────────────────────────────────────────┐
│                      TEST PYRAMID                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              E2E Tests (10%)                             │    │
│  │  - Full CLI command execution                            │    │
│  │  - Cross-platform validation                             │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │           Integration Tests (60%)                        │    │
│  │  - Executor + Error Parser workflows                     │    │
│  │  - Config + Profile loading                              │    │
│  │  - Database persistence                                  │    │
│  │  - Mock subprocess execution                             │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              Unit Tests (30%)                            │    │
│  │  - Error parsing regex patterns                          │    │
│  │  - Port availability checking                            │    │
│  │  - Asset copy operations                                 │    │
│  │  - JSON output formatting                                │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

**Philosophy:** Validate executor behavior through mock subprocess responses. Integration tests cover complete build workflows with simulated runtime outputs.

---

## Directory Structure

```
brun/
├── cmd/
│   └── brun/
│       └── main_test.go        # Entry point tests
├── internal/
│   ├── cli/
│   │   ├── build_test.go       # Build command tests
│   │   ├── check_test.go       # Check command tests
│   │   ├── run_test.go         # Run command tests
│   │   └── port_test.go        # Port command tests
│   ├── executor/
│   │   ├── executor_test.go    # Interface tests
│   │   ├── powershell_test.go  # PS executor tests
│   │   ├── nodejs_test.go      # Node executor tests
│   │   └── golang_test.go      # Go executor tests
│   ├── error/
│   │   ├── parser_test.go      # Error parsing tests
│   │   └── capture_test.go     # Capture tests
│   ├── port/
│   │   ├── checker_test.go     # Port check tests
│   │   └── firewall_test.go    # Firewall tests
│   ├── asset/
│   │   └── copier_test.go      # Asset copy tests
│   ├── health/
│   │   └── checker_test.go     # Health check tests
│   └── config/
│       ├── loader_test.go      # Config load tests
│       └── validator_test.go   # Validation tests
├── tests/
│   └── integration/
│       ├── testenv.go          # Test environment setup
│       ├── build_flow_test.go  # Complete build workflows
│       ├── check_flow_test.go  # Error check workflows
│       ├── profile_test.go     # Profile execution tests
│       └── health_test.go      # Health check workflows
└── testdata/
    ├── fixtures/
    │   ├── golang/
    │   │   ├── build_success.txt
    │   │   ├── build_error.txt
    │   │   ├── build_multiple_errors.txt
    │   │   ├── mod_tidy_error.txt
    │   │   └── test_failure.txt
    │   ├── nodejs/
    │   │   ├── npm_build_success.txt
    │   │   ├── npm_build_error.txt
    │   │   ├── yarn_error.txt
    │   │   ├── typescript_error.txt
    │   │   └── eslint_warning.txt
    │   ├── powershell/
    │   │   ├── script_success.txt
    │   │   ├── script_error.txt
    │   │   ├── script_warning.txt
    │   │   └── terminating_error.txt
    │   └── configs/
    │       ├── valid_config.json
    │       ├── invalid_config.json
    │       ├── minimal_config.json
    │       └── full_config.json
    ├── projects/
    │   ├── go-sample/
    │   │   ├── main.go
    │   │   └── go.mod
    │   ├── node-sample/
    │   │   ├── index.js
    │   │   └── package.json
    │   └── ps-sample/
    │       └── script.ps1
    └── metadata.json
```

---

## Testing Framework

### Dependencies

```go
// go.mod
require (
    github.com/stretchr/testify v1.9.0
)
```

### Test Environment Setup

```go
// tests/integration/testenv.go
package integration

import (
    "os"
    "path/filepath"
    "testing"
    
    "gorm.io/driver/sqlite"
    "gorm.io/gorm"
    
    "brun/internal/config"
    "brun/pkg/models"
)

type TestEnv struct {
    DB         *gorm.DB
    DBPath     string
    ConfigPath string
    TempDir    string
    LogDir     string
}

func NewTestEnv(t *testing.T) *TestEnv {
    t.Helper()
    
    tempDir := t.TempDir()
    dbPath := filepath.Join(tempDir, "test.db")
    logDir := filepath.Join(tempDir, "logs")
    
    os.MkdirAll(logDir, 0755)
    
    db, err := gorm.Open(sqlite.Open(dbPath), &gorm.Config{})
    if err != nil {
        t.Fatalf("open db: %v", err)
    }
    
    // Run migrations
    db.AutoMigrate(&models.Run{}, &models.BuildError{})
    
    return &TestEnv{
        DB:         db,
        DBPath:     dbPath,
        ConfigPath: filepath.Join(tempDir, "config.json"),
        TempDir:    tempDir,
        LogDir:     logDir,
    }
}

func (e *TestEnv) Cleanup() {
    sqlDB, _ := e.DB.DB()
    sqlDB.Close()
}

func (e *TestEnv) WriteConfig(t *testing.T, cfg *config.Config) {
    t.Helper()
    data, _ := json.Marshal(cfg)
    os.WriteFile(e.ConfigPath, data, 0644)
}

func (e *TestEnv) CreateTestProject(t *testing.T, runtime, name string) string {
    t.Helper()
    projectDir := filepath.Join(e.TempDir, "projects", name)
    os.MkdirAll(projectDir, 0755)
    
    switch runtime {
    case "golang":
        os.WriteFile(filepath.Join(projectDir, "main.go"),
            []byte("package main\nfunc main() {}"), 0644)
        os.WriteFile(filepath.Join(projectDir, "go.mod"),
            []byte("module test\ngo 1.22"), 0644)
    case "nodejs":
        os.WriteFile(filepath.Join(projectDir, "package.json"),
            []byte(`{"name":"test","scripts":{"build":"echo built"}}`), 0644)
    case "powershell":
        os.WriteFile(filepath.Join(projectDir, "script.ps1"),
            []byte("Write-Output 'Hello'"), 0644)
    }
    
    return projectDir
}
```

---

## Mock Executor Framework

### Mock Subprocess Runner

The key to testing executors without actual runtimes is mocking the subprocess execution layer.

```go
// internal/executor/mock_runner.go
package executor

import (
    "context"
    "time"
)

// MockRunner simulates subprocess execution for testing
type MockRunner struct {
    responses map[string]*MockResponse
    calls     []MockCall
}

type MockResponse struct {
    Stdout   string
    Stderr   string
    ExitCode int
    Delay    time.Duration
    Error    error
}

type MockCall struct {
    Command string
    Args    []string
    WorkDir string
}

func NewMockRunner() *MockRunner {
    return &MockRunner{
        responses: make(map[string]*MockResponse),
        calls:     make([]MockCall, 0),
    }
}

func (m *MockRunner) Register(cmdPattern string, response *MockResponse) {
    m.responses[cmdPattern] = response
}

func (m *MockRunner) Execute(ctx context.Context, cmd string, args []string, workDir string) (*RunOutput, error) {
    m.calls = append(m.calls, MockCall{
        Command: cmd,
        Args:    args,
        WorkDir: workDir,
    })
    
    // Find matching response
    for pattern, resp := range m.responses {
        if matchesPattern(cmd, pattern) {
            if resp.Delay > 0 {
                select {
                case <-time.After(resp.Delay):
                case <-ctx.Done():
                    return nil, ctx.Err()
                }
            }
            
            if resp.Error != nil {
                return nil, resp.Error
            }
            
            return &RunOutput{
                Stdout:   resp.Stdout,
                Stderr:   resp.Stderr,
                ExitCode: resp.ExitCode,
            }, nil
        }
    }
    
    return &RunOutput{ExitCode: 0}, nil
}

func (m *MockRunner) GetCalls() []MockCall {
    return m.calls
}

func (m *MockRunner) Reset() {
    m.calls = make([]MockCall, 0)
}
```

### Mock Executor Implementation

```go
// internal/executor/mock_executor.go
package executor

import (
    "context"
    
    "brun/pkg/models"
)

// MockExecutor is a test double for any runtime executor
type MockExecutor struct {
    RuntimeName    string
    ValidateError  error
    Version        string
    ExecuteResults map[string]*Result
    ExecuteCalls   []ExecuteCall
}

type ExecuteCall struct {
    Command *Command
}

func NewMockExecutor(runtime string) *MockExecutor {
    return &MockExecutor{
        RuntimeName:    runtime,
        Version:        "1.0.0-mock",
        ExecuteResults: make(map[string]*Result),
        ExecuteCalls:   make([]ExecuteCall, 0),
    }
}

func (m *MockExecutor) RuntimeType() string {
    return m.RuntimeName
}

func (m *MockExecutor) Validate() error {
    return m.ValidateError
}

func (m *MockExecutor) GetVersion() (string, error) {
    if m.ValidateError != nil {
        return "", m.ValidateError
    }
    return m.Version, nil
}

func (m *MockExecutor) Execute(ctx context.Context, cmd *Command) (*Result, error) {
    m.ExecuteCalls = append(m.ExecuteCalls, ExecuteCall{Command: cmd})
    
    if result, ok := m.ExecuteResults[cmd.Script]; ok {
        return result, nil
    }
    
    // Default success response
    return &Result{
        ExitCode: 0,
        Stdout:   "mock success",
        Stderr:   "",
        Errors:   nil,
    }, nil
}

func (m *MockExecutor) SetResult(script string, result *Result) {
    m.ExecuteResults[script] = result
}

func (m *MockExecutor) SetError(script string, exitCode int, stderr string, errors []models.BuildError) {
    m.ExecuteResults[script] = &Result{
        ExitCode: exitCode,
        Stdout:   "",
        Stderr:   stderr,
        Errors:   errors,
    }
}
```

---

## Unit Tests

### 1. Error Parser Tests

```go
// internal/error/parser_test.go
package error

import (
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestGolangErrorParsing(t *testing.T) {
    parser := NewParser()
    
    tests := []struct {
        name     string
        output   string
        expected int
        file     string
        line     int
        message  string
    }{
        {
            name:     "single error",
            output:   "./main.go:15:2: undefined: fmt.Printl",
            expected: 1,
            file:     "./main.go",
            line:     15,
            message:  "undefined: fmt.Printl",
        },
        {
            name: "multiple errors",
            output: `./main.go:10:5: undefined: x
./main.go:15:2: syntax error: unexpected }
./utils.go:22:10: cannot use string as int`,
            expected: 3,
        },
        {
            name:     "no errors",
            output:   "build successful",
            expected: 0,
        },
        {
            name: "package error",
            output: `# mypackage
./main.go:5:2: imported and not used: "fmt"`,
            expected: 1,
        },
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            errors := parser.Parse("golang", tt.output)
            assert.Len(t, errors, tt.expected)
            
            if tt.expected > 0 && tt.file != "" {
                assert.Equal(t, tt.file, errors[0].File)
                assert.Equal(t, tt.line, errors[0].Line)
                assert.Equal(t, tt.message, errors[0].Message)
            }
        })
    }
}

func TestNodeJSErrorParsing(t *testing.T) {
    parser := NewParser()
    
    tests := []struct {
        name     string
        output   string
        expected int
    }{
        {
            name: "typescript error",
            output: `src/index.ts:10:5 - error TS2304: Cannot find name 'foo'.

10     foo.bar()
       ~~~`,
            expected: 1,
        },
        {
            name: "stack trace",
            output: `TypeError: Cannot read property 'map' of undefined
    at processData (/app/src/utils.js:25:10)
    at Object.<anonymous> (/app/src/index.js:42:5)`,
            expected: 2,
        },
        {
            name: "eslint warning",
            output: `/app/src/index.js
  10:5  warning  'x' is defined but never used  no-unused-vars`,
            expected: 1,
        },
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            errors := parser.Parse("nodejs", tt.output)
            assert.Len(t, errors, tt.expected)
        })
    }
}

func TestPowerShellErrorParsing(t *testing.T) {
    parser := NewParser()
    
    tests := []struct {
        name     string
        output   string
        expected int
    }{
        {
            name: "terminating error",
            output: `At C:\scripts\deploy.ps1:25 char:1
+ throw "Deployment failed"
+ ~~~~~~~~~~~~~~~~~~~~~~~~~~
    + CategoryInfo          : OperationStopped: (Deployment failed:String) [], RuntimeException`,
            expected: 1,
        },
        {
            name: "cmdlet error",
            output: `Get-Item : Cannot find path 'C:\missing' because it does not exist.
At C:\scripts\check.ps1:10 char:5`,
            expected: 1,
        },
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            errors := parser.Parse("powershell", tt.output)
            assert.Len(t, errors, tt.expected)
        })
    }
}
```

### 2. Port Checker Tests

```go
// internal/port/checker_test.go
package port

import (
    "context"
    "net"
    "testing"
    "time"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestPortAvailability(t *testing.T) {
    manager := NewManager(time.Second)
    
    // Find an available port
    listener, err := net.Listen("tcp", ":0")
    require.NoError(t, err)
    port := listener.Addr().(*net.TCPAddr).Port
    listener.Close()
    
    // Port should be available after closing
    assert.True(t, manager.IsAvailable(port))
}

func TestPortInUse(t *testing.T) {
    manager := NewManager(time.Second)
    
    // Occupy a port
    listener, err := net.Listen("tcp", ":0")
    require.NoError(t, err)
    defer listener.Close()
    
    port := listener.Addr().(*net.TCPAddr).Port
    
    // Port should not be available
    assert.False(t, manager.IsAvailable(port))
}

func TestFindAvailablePort(t *testing.T) {
    manager := NewManager(time.Second)
    
    // Occupy preferred port
    listener, err := net.Listen("tcp", ":0")
    require.NoError(t, err)
    defer listener.Close()
    
    occupiedPort := listener.Addr().(*net.TCPAddr).Port
    fallbackPort := occupiedPort + 1
    
    // Should fall back to next available
    found, err := manager.FindAvailable(occupiedPort, []int{fallbackPort})
    require.NoError(t, err)
    assert.Equal(t, fallbackPort, found)
}

func TestWaitForPort(t *testing.T) {
    manager := NewManager(time.Second)
    
    // Start a listener after a short delay
    port := 0
    go func() {
        time.Sleep(100 * time.Millisecond)
        listener, _ := net.Listen("tcp", ":0")
        port = listener.Addr().(*net.TCPAddr).Port
        defer listener.Close()
        time.Sleep(2 * time.Second) // Keep open
    }()
    
    // Wait for actual port to be known
    time.Sleep(150 * time.Millisecond)
    
    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()
    
    err := manager.WaitForPort(ctx, port)
    assert.NoError(t, err)
}

func TestWaitForPortTimeout(t *testing.T) {
    manager := NewManager(100 * time.Millisecond)
    
    ctx, cancel := context.WithTimeout(context.Background(), 500*time.Millisecond)
    defer cancel()
    
    // Wait for port that never opens
    err := manager.WaitForPort(ctx, 59999)
    assert.Error(t, err)
}
```

### 3. Asset Copier Tests

```go
// internal/asset/copier_test.go
package asset

import (
    "os"
    "path/filepath"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestCopyModeClear(t *testing.T) {
    tempDir := t.TempDir()
    src := filepath.Join(tempDir, "src")
    dst := filepath.Join(tempDir, "dst")
    
    // Create source files
    os.MkdirAll(src, 0755)
    os.WriteFile(filepath.Join(src, "file1.txt"), []byte("content1"), 0644)
    os.WriteFile(filepath.Join(src, "file2.txt"), []byte("content2"), 0644)
    
    // Create pre-existing destination
    os.MkdirAll(dst, 0755)
    os.WriteFile(filepath.Join(dst, "old.txt"), []byte("old"), 0644)
    
    copier := NewCopier(ModeClear, nil)
    err := copier.Copy(src, dst)
    require.NoError(t, err)
    
    // Old file should be gone
    _, err = os.Stat(filepath.Join(dst, "old.txt"))
    assert.True(t, os.IsNotExist(err))
    
    // New files should exist
    _, err = os.Stat(filepath.Join(dst, "file1.txt"))
    assert.NoError(t, err)
}

func TestCopyModeOverride(t *testing.T) {
    tempDir := t.TempDir()
    src := filepath.Join(tempDir, "src")
    dst := filepath.Join(tempDir, "dst")
    
    // Create source
    os.MkdirAll(src, 0755)
    os.WriteFile(filepath.Join(src, "file.txt"), []byte("new"), 0644)
    
    // Create destination with same file
    os.MkdirAll(dst, 0755)
    os.WriteFile(filepath.Join(dst, "file.txt"), []byte("old"), 0644)
    os.WriteFile(filepath.Join(dst, "keep.txt"), []byte("keep"), 0644)
    
    copier := NewCopier(ModeOverride, nil)
    err := copier.Copy(src, dst)
    require.NoError(t, err)
    
    // File should be overwritten
    content, _ := os.ReadFile(filepath.Join(dst, "file.txt"))
    assert.Equal(t, "new", string(content))
    
    // Other files should remain
    _, err = os.Stat(filepath.Join(dst, "keep.txt"))
    assert.NoError(t, err)
}

func TestCopyModeSkip(t *testing.T) {
    tempDir := t.TempDir()
    src := filepath.Join(tempDir, "src")
    dst := filepath.Join(tempDir, "dst")
    
    os.MkdirAll(src, 0755)
    os.WriteFile(filepath.Join(src, "file.txt"), []byte("new"), 0644)
    
    os.MkdirAll(dst, 0755)
    os.WriteFile(filepath.Join(dst, "file.txt"), []byte("old"), 0644)
    
    copier := NewCopier(ModeSkip, nil)
    err := copier.Copy(src, dst)
    require.NoError(t, err)
    
    // File should NOT be overwritten
    content, _ := os.ReadFile(filepath.Join(dst, "file.txt"))
    assert.Equal(t, "old", string(content))
}

func TestCopyWithIgnorePatterns(t *testing.T) {
    tempDir := t.TempDir()
    src := filepath.Join(tempDir, "src")
    dst := filepath.Join(tempDir, "dst")
    
    os.MkdirAll(filepath.Join(src, "node_modules", "pkg"), 0755)
    os.WriteFile(filepath.Join(src, "index.js"), []byte("code"), 0644)
    os.WriteFile(filepath.Join(src, "node_modules", "pkg", "lib.js"), []byte("lib"), 0644)
    
    copier := NewCopier(ModeClear, []string{"node_modules"})
    err := copier.Copy(src, dst)
    require.NoError(t, err)
    
    // index.js should exist
    _, err = os.Stat(filepath.Join(dst, "index.js"))
    assert.NoError(t, err)
    
    // node_modules should be ignored
    _, err = os.Stat(filepath.Join(dst, "node_modules"))
    assert.True(t, os.IsNotExist(err))
}
```

### 4. Config Loader Tests

```go
// internal/config/loader_test.go
package config

import (
    "os"
    "path/filepath"
    "testing"
    "time"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestLoadValidConfig(t *testing.T) {
    tempDir := t.TempDir()
    configPath := filepath.Join(tempDir, "config.json")
    
    configJSON := `{
        "version": "1.0.0",
        "workDir": "./projects",
        "logDir": "./logs",
        "profiles": {
            "backend": {
                "name": "backend",
                "runtime": "golang",
                "sourceDir": "./cmd/api",
                "command": "go build",
                "timeout": "5m"
            }
        },
        "defaults": {
            "timeout": "10m",
            "jsonOutput": false,
            "logToFile": true
        }
    }`
    
    os.WriteFile(configPath, []byte(configJSON), 0644)
    
    cfg, err := LoadFromFile(configPath)
    require.NoError(t, err)
    
    assert.Equal(t, "1.0.0", cfg.Version)
    assert.Equal(t, "./projects", cfg.WorkDir)
    assert.Len(t, cfg.Profiles, 1)
    assert.Equal(t, "golang", cfg.Profiles["backend"].Runtime)
    assert.Equal(t, 10*time.Minute, cfg.Defaults.Timeout)
}

func TestLoadConfigWithDefaults(t *testing.T) {
    tempDir := t.TempDir()
    configPath := filepath.Join(tempDir, "config.json")
    
    // Minimal config
    os.WriteFile(configPath, []byte(`{"version": "1.0.0"}`), 0644)
    
    cfg, err := LoadFromFile(configPath)
    require.NoError(t, err)
    
    // Check defaults applied
    assert.Equal(t, ".", cfg.WorkDir)
    assert.Equal(t, "./logs", cfg.LogDir)
    assert.Equal(t, 5*time.Minute, cfg.Defaults.Timeout)
}

func TestLoadInvalidConfig(t *testing.T) {
    tempDir := t.TempDir()
    configPath := filepath.Join(tempDir, "config.json")
    
    // Invalid JSON
    os.WriteFile(configPath, []byte(`{invalid json`), 0644)
    
    _, err := LoadFromFile(configPath)
    assert.Error(t, err)
}

func TestConfigValidation(t *testing.T) {
    tests := []struct {
        name    string
        config  *Config
        wantErr bool
    }{
        {
            name: "valid config",
            config: &Config{
                Version: "1.0.0",
                Profiles: map[string]Profile{
                    "test": {Runtime: "golang"},
                },
            },
            wantErr: false,
        },
        {
            name: "invalid runtime",
            config: &Config{
                Version: "1.0.0",
                Profiles: map[string]Profile{
                    "test": {Runtime: "invalid"},
                },
            },
            wantErr: true,
        },
        {
            name: "empty profile name",
            config: &Config{
                Version: "1.0.0",
                Profiles: map[string]Profile{
                    "": {Runtime: "golang"},
                },
            },
            wantErr: true,
        },
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := tt.config.Validate()
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

---

## Integration Tests

### 1. Build Flow Tests

```go
// tests/integration/build_flow_test.go
package integration

import (
    "context"
    "testing"
    "time"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
    
    "brun/internal/config"
    "brun/internal/engine"
    "brun/internal/executor"
    "brun/pkg/models"
)

func TestBuildFlowSuccess(t *testing.T) {
    env := NewTestEnv(t)
    defer env.Cleanup()
    
    // Create mock executor
    mockExec := executor.NewMockExecutor("golang")
    mockExec.SetResult("go build ./...", &executor.Result{
        ExitCode: 0,
        Stdout:   "build successful",
        Stderr:   "",
    })
    
    // Create engine with mock
    cfg := &config.Config{
        Profiles: map[string]config.Profile{
            "backend": {
                Name:    "backend",
                Runtime: "golang",
                Command: "go build ./...",
                Timeout: 5 * time.Minute,
            },
        },
    }
    
    eng := engine.NewWithExecutors(cfg, map[string]executor.Executor{
        "golang": mockExec,
    })
    
    ctx := context.Background()
    result, err := eng.ExecuteProfile(ctx, "backend")
    
    require.NoError(t, err)
    assert.Equal(t, models.StatusSuccess, result.Status)
    assert.Equal(t, 0, result.ExitCode)
    assert.Empty(t, result.Errors)
}

func TestBuildFlowWithErrors(t *testing.T) {
    env := NewTestEnv(t)
    defer env.Cleanup()
    
    mockExec := executor.NewMockExecutor("golang")
    mockExec.SetError("go build ./...", 1, `./main.go:15:2: undefined: fmt.Printl
./utils.go:22:10: cannot use string as int`, []models.BuildError{
        {File: "./main.go", Line: 15, Column: 2, Message: "undefined: fmt.Printl"},
        {File: "./utils.go", Line: 22, Column: 10, Message: "cannot use string as int"},
    })
    
    cfg := &config.Config{
        Profiles: map[string]config.Profile{
            "backend": {
                Runtime: "golang",
                Command: "go build ./...",
            },
        },
    }
    
    eng := engine.NewWithExecutors(cfg, map[string]executor.Executor{
        "golang": mockExec,
    })
    
    ctx := context.Background()
    result, err := eng.ExecuteProfile(ctx, "backend")
    
    require.NoError(t, err) // Engine doesn't error, just captures result
    assert.Equal(t, models.StatusFailed, result.Status)
    assert.Equal(t, 1, result.ExitCode)
    assert.Len(t, result.Errors, 2)
}

func TestBuildFlowWithTimeout(t *testing.T) {
    env := NewTestEnv(t)
    defer env.Cleanup()
    
    // Mock that takes too long
    mockExec := executor.NewMockExecutor("golang")
    mockExec.ExecuteResults["go build ./..."] = nil // Will use default delay
    
    cfg := &config.Config{
        Profiles: map[string]config.Profile{
            "backend": {
                Runtime: "golang",
                Command: "go build ./...",
                Timeout: 100 * time.Millisecond,
            },
        },
    }
    
    eng := engine.NewWithExecutors(cfg, map[string]executor.Executor{
        "golang": mockExec,
    })
    
    ctx := context.Background()
    result, err := eng.ExecuteProfile(ctx, "backend")
    
    assert.Error(t, err)
    assert.Equal(t, models.StatusTimeout, result.Status)
}

func TestBuildFlowWithPreCommands(t *testing.T) {
    env := NewTestEnv(t)
    defer env.Cleanup()
    
    mockExec := executor.NewMockExecutor("golang")
    mockExec.SetResult("go mod tidy", &executor.Result{ExitCode: 0})
    mockExec.SetResult("go build ./...", &executor.Result{ExitCode: 0})
    
    cfg := &config.Config{
        Profiles: map[string]config.Profile{
            "backend": {
                Runtime:     "golang",
                Command:     "go build ./...",
                PreCommands: []string{"go mod tidy"},
            },
        },
    }
    
    eng := engine.NewWithExecutors(cfg, map[string]executor.Executor{
        "golang": mockExec,
    })
    
    ctx := context.Background()
    _, err := eng.ExecuteProfile(ctx, "backend")
    
    require.NoError(t, err)
    
    // Verify both commands were called
    calls := mockExec.ExecuteCalls
    assert.Len(t, calls, 2)
}
```

### 2. Check Flow Tests

```go
// tests/integration/check_flow_test.go
package integration

import (
    "context"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
    
    "brun/internal/executor"
    "brun/internal/check"
)

func TestCheckGoErrors(t *testing.T) {
    env := NewTestEnv(t)
    defer env.Cleanup()
    
    mockExec := executor.NewMockExecutor("golang")
    mockExec.SetError("go build ./...", 1, 
        `./main.go:10:5: undefined: nonexistent`,
        nil) // Let parser extract errors
    
    checker := check.NewChecker(mockExec)
    
    ctx := context.Background()
    result, err := checker.Check(ctx, "./cmd/app")
    
    require.NoError(t, err)
    assert.False(t, result.Success)
    assert.NotEmpty(t, result.Errors)
}

func TestCheckNodeErrors(t *testing.T) {
    env := NewTestEnv(t)
    defer env.Cleanup()
    
    mockExec := executor.NewMockExecutor("nodejs")
    mockExec.SetError("npm run build", 1,
        `src/index.ts:10:5 - error TS2304: Cannot find name 'foo'.`,
        nil)
    
    checker := check.NewChecker(mockExec)
    
    ctx := context.Background()
    result, err := checker.Check(ctx, "./frontend")
    
    require.NoError(t, err)
    assert.False(t, result.Success)
}

func TestCheckNoErrors(t *testing.T) {
    env := NewTestEnv(t)
    defer env.Cleanup()
    
    mockExec := executor.NewMockExecutor("golang")
    mockExec.SetResult("go build ./...", &executor.Result{
        ExitCode: 0,
        Stdout:   "",
        Stderr:   "",
    })
    
    checker := check.NewChecker(mockExec)
    
    ctx := context.Background()
    result, err := checker.Check(ctx, "./cmd/app")
    
    require.NoError(t, err)
    assert.True(t, result.Success)
    assert.Empty(t, result.Errors)
}
```

### 3. Health Check Tests

```go
// tests/integration/health_test.go
package integration

import (
    "context"
    "net/http"
    "net/http/httptest"
    "testing"
    "time"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
    
    "brun/internal/config"
    "brun/internal/health"
)

func TestHealthCheckSuccess(t *testing.T) {
    // Create mock server
    server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        if r.URL.Path == "/health" {
            w.WriteHeader(http.StatusOK)
            w.Write([]byte(`{"status":"ok"}`))
        }
    }))
    defer server.Close()
    
    checker := health.NewChecker(time.Second)
    
    app := config.AppDef{
        Name:       "test-app",
        Host:       server.Listener.Addr().String(),
        Port:       0, // Using server's port
        HealthPath: "/health",
        HealthCheck: config.HealthCheck{
            Enabled:        true,
            ExpectedStatus: 200,
        },
    }
    
    // Override to use test server URL
    ctx := context.Background()
    err := checker.CheckURL(ctx, server.URL+"/health", 200)
    
    assert.NoError(t, err)
}

func TestHealthCheckFailure(t *testing.T) {
    server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.WriteHeader(http.StatusServiceUnavailable)
    }))
    defer server.Close()
    
    checker := health.NewChecker(time.Second)
    
    ctx := context.Background()
    err := checker.CheckURL(ctx, server.URL+"/health", 200)
    
    assert.Error(t, err)
}

func TestWaitForHealthy(t *testing.T) {
    attempts := 0
    server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        attempts++
        if attempts < 3 {
            w.WriteHeader(http.StatusServiceUnavailable)
            return
        }
        w.WriteHeader(http.StatusOK)
    }))
    defer server.Close()
    
    checker := health.NewChecker(100 * time.Millisecond)
    
    app := config.AppDef{
        HealthCheck: config.HealthCheck{
            Enabled:        true,
            Interval:       50 * time.Millisecond,
            Retries:        5,
            ExpectedStatus: 200,
        },
    }
    
    ctx := context.Background()
    err := checker.WaitForHealthyURL(ctx, server.URL+"/health", app.HealthCheck)
    
    assert.NoError(t, err)
    assert.Equal(t, 3, attempts)
}
```

### 4. JSON Output Tests

```go
// tests/integration/json_output_test.go
package integration

import (
    "bytes"
    "encoding/json"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
    
    "brun/internal/output"
    "brun/pkg/models"
)

func TestJSONOutputFormat(t *testing.T) {
    result := &models.RunResult{
        RunID:    "20260129-120000-abc12345",
        Status:   models.StatusFailed,
        ExitCode: 1,
        Errors: []models.BuildError{
            {
                File:    "./main.go",
                Line:    15,
                Column:  2,
                Message: "undefined: fmt.Printl",
            },
        },
    }
    
    var buf bytes.Buffer
    formatter := output.NewJSONFormatter(&buf)
    
    err := formatter.Write(result)
    require.NoError(t, err)
    
    // Parse output
    var parsed map[string]interface{}
    err = json.Unmarshal(buf.Bytes(), &parsed)
    require.NoError(t, err)
    
    assert.Equal(t, "20260129-120000-abc12345", parsed["runId"])
    assert.Equal(t, "failed", parsed["status"])
    assert.Equal(t, float64(1), parsed["exitCode"])
    
    errors := parsed["errors"].([]interface{})
    assert.Len(t, errors, 1)
}

func TestJSONOutputValidSchema(t *testing.T) {
    result := &models.RunResult{
        RunID:    "test-run",
        Status:   models.StatusSuccess,
        ExitCode: 0,
    }
    
    var buf bytes.Buffer
    formatter := output.NewJSONFormatter(&buf)
    formatter.Write(result)
    
    // Validate JSON is well-formed
    assert.True(t, json.Valid(buf.Bytes()))
}
```

---

## CLI Command Tests

### Build Command Tests

```go
// internal/cli/build_test.go
package cli

import (
    "bytes"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestBuildCommand_ValidProfile(t *testing.T) {
    env := setupTestCLI(t)
    defer env.Cleanup()
    
    var stdout, stderr bytes.Buffer
    rootCmd := NewRootCmd(env.Config)
    rootCmd.SetOut(&stdout)
    rootCmd.SetErr(&stderr)
    rootCmd.SetArgs([]string{"build", "--profile", "backend"})
    
    err := rootCmd.Execute()
    require.NoError(t, err)
}

func TestBuildCommand_MissingProfile(t *testing.T) {
    env := setupTestCLI(t)
    defer env.Cleanup()
    
    var stderr bytes.Buffer
    rootCmd := NewRootCmd(env.Config)
    rootCmd.SetErr(&stderr)
    rootCmd.SetArgs([]string{"build", "--profile", "nonexistent"})
    
    err := rootCmd.Execute()
    assert.Error(t, err)
    assert.Contains(t, stderr.String(), "profile not found")
}

func TestBuildCommand_JSONOutput(t *testing.T) {
    env := setupTestCLI(t)
    defer env.Cleanup()
    
    var stdout bytes.Buffer
    rootCmd := NewRootCmd(env.Config)
    rootCmd.SetOut(&stdout)
    rootCmd.SetArgs([]string{"build", "--profile", "backend", "--json"})
    
    rootCmd.Execute()
    
    // Verify JSON output
    assert.Contains(t, stdout.String(), `"runId"`)
    assert.Contains(t, stdout.String(), `"status"`)
}
```

---

## Test Fixtures

### Golang Build Output Fixtures

```
// testdata/fixtures/golang/build_success.txt
```

```
// testdata/fixtures/golang/build_error.txt
# mypackage
./main.go:15:2: undefined: fmt.Printl
./main.go:22:10: cannot use string as int in assignment
```

```
// testdata/fixtures/golang/build_multiple_errors.txt
# mypackage/internal/api
./handler.go:45:3: undefined: ctx
./handler.go:52:15: too few arguments in call to db.Query
./handler.go:60:2: missing return at end of function
# mypackage/internal/models
./user.go:12:2: imported and not used: "fmt"
```

### Node.js Build Output Fixtures

```
// testdata/fixtures/nodejs/typescript_error.txt
src/components/Button.tsx:25:3 - error TS2322: Type 'string' is not assignable to type 'number'.

25   const count: number = "five";
     ~~~~~

src/utils/helpers.ts:10:5 - error TS2304: Cannot find name 'undefined_var'.

10     undefined_var.map(x => x);
       ~~~~~~~~~~~~~
```

### PowerShell Output Fixtures

```
// testdata/fixtures/powershell/script_error.txt
At C:\scripts\deploy.ps1:25 char:1
+ throw "Deployment failed: connection timeout"
+ ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    + CategoryInfo          : OperationStopped: (Deployment failed: connection timeout:String) [], RuntimeException
    + FullyQualifiedErrorId : Deployment failed: connection timeout
```

---

## CI/CD Configuration

### GitHub Actions Workflow

```yaml
# .github/workflows/test.yml
name: Test

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    strategy:
      matrix:
        os: [ubuntu-latest, windows-latest, macos-latest]
        go-version: ['1.22']
    
    runs-on: ${{ matrix.os }}
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: ${{ matrix.go-version }}
      
      - name: Install dependencies
        run: go mod download
      
      - name: Run unit tests
        run: go test -v -race -coverprofile=coverage.txt ./internal/...
      
      - name: Run integration tests
        run: go test -v -race ./tests/integration/...
      
      - name: Upload coverage
        uses: codecov/codecov-action@v4
        with:
          file: ./coverage.txt
          fail_ci_if_error: false

  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: '1.22'
      
      - name: golangci-lint
        uses: golangci/golangci-lint-action@v4
        with:
          version: latest

  build:
    needs: [test, lint]
    strategy:
      matrix:
        include:
          - os: ubuntu-latest
            goos: linux
            goarch: amd64
          - os: ubuntu-latest
            goos: linux
            goarch: arm64
          - os: windows-latest
            goos: windows
            goarch: amd64
          - os: macos-latest
            goos: darwin
            goarch: amd64
          - os: macos-latest
            goos: darwin
            goarch: arm64
    
    runs-on: ${{ matrix.os }}
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: '1.22'
      
      - name: Build binary
        env:
          GOOS: ${{ matrix.goos }}
          GOARCH: ${{ matrix.goarch }}
        run: |
          go build -ldflags="-s -w" -o brun-${{ matrix.goos }}-${{ matrix.goarch }}${{ matrix.goos == 'windows' && '.exe' || '' }} ./cmd/brun
      
      - name: Upload artifact
        uses: actions/upload-artifact@v4
        with:
          name: brun-${{ matrix.goos }}-${{ matrix.goarch }}
          path: brun-*
```

### Makefile

```makefile
# Makefile
.PHONY: test test-unit test-integration lint build clean

# Run all tests
test: test-unit test-integration

# Run unit tests only
test-unit:
	go test -v -race -coverprofile=coverage.txt ./internal/...

# Run integration tests only
test-integration:
	go test -v -race ./tests/integration/...

# Run tests with coverage report
test-coverage:
	go test -v -race -coverprofile=coverage.txt ./...
	go tool cover -html=coverage.txt -o coverage.html

# Run linter
lint:
	golangci-lint run ./...

# Build binary
build:
	go build -ldflags="-s -w" -o brun ./cmd/brun

# Build for all platforms
build-all:
	GOOS=linux GOARCH=amd64 go build -o dist/brun-linux-amd64 ./cmd/brun
	GOOS=linux GOARCH=arm64 go build -o dist/brun-linux-arm64 ./cmd/brun
	GOOS=windows GOARCH=amd64 go build -o dist/brun-windows-amd64.exe ./cmd/brun
	GOOS=darwin GOARCH=amd64 go build -o dist/brun-darwin-amd64 ./cmd/brun
	GOOS=darwin GOARCH=arm64 go build -o dist/brun-darwin-arm64 ./cmd/brun

# Clean build artifacts
clean:
	rm -rf dist/
	rm -f brun
	rm -f coverage.txt coverage.html
```

---

## Test Coverage Requirements

| Component       | Min Coverage | Priority |
|-----------------|--------------|----------|
| Error Parser    | 90%          | P0       |
| Config Loader   | 85%          | P0       |
| Executors (mock)| 80%          | P0       |
| Port Manager    | 80%          | P1       |
| Asset Copier    | 75%          | P1       |
| Health Checker  | 75%          | P1       |
| CLI Commands    | 70%          | P2       |

---

## Cross-Platform Test Matrix

| Test Category  | Windows | Linux | macOS |
|----------------|---------|-------|-------|
| Error Parsing  | ✅      | ✅    | ✅    |
| Port Checking  | ✅      | ✅    | ✅    |
| Asset Copy     | ✅ (paths) | ✅  | ✅    |
| Config Loading | ✅      | ✅    | ✅    |
| Health Checks  | ✅      | ✅    | ✅    |
| File Logging   | ✅ (paths) | ✅  | ✅    |

**Path Handling Notes:**
- Windows tests must handle `\` vs `/` path separators
- Use `filepath.Join()` consistently
- Test fixtures use OS-agnostic paths where possible

---

## See Also

- [Core Architecture](./01-core-architecture.md) — System design
- [Runtime Executors](./04-runtime-executors.md) — Executor specifications
- [Error Handling](./06-error-handling.md) — Error code definitions
- [Implementation Guide](./14-implementation-guide.md) — Build order
- [gsearch Testing Strategy](../22-golang-search-cli/12-testing-strategy.md) — Reference template
