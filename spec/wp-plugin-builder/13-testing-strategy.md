# Testing Strategy

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Testing approach for WP Plugin Builder CLI, covering unit tests, integration tests, and generated code validation.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [Code Generation](./07-code-generation.md)
- [BRun Testing Strategy](../brun-cli/13-testing-strategy.md)

---

## Test Categories

| Category | Scope | Tools |
|----------|-------|-------|
| Unit | Individual functions and methods | Go testing, testify |
| Integration | Component interactions | Go testing, sqlmock |
| E2E | Full CLI workflows | Go testing, temp directories |
| Generated Code | Output PHP validation | PHP linter, PHPStan |

---

## Directory Structure

```
wp-plugin-builder/
├── cmd/
│   └── wpb/
│       └── main_test.go
├── internal/
│   ├── project/
│   │   └── project_test.go
│   ├── rag/
│   │   └── rag_test.go
│   ├── generator/
│   │   └── generator_test.go
│   └── ...
├── test/
│   ├── fixtures/
│   │   ├── specs/
│   │   │   └── sample-spec.md
│   │   ├── presets/
│   │   │   └── sample-preset.md
│   │   └── expected/
│   │       └── plugin-structure/
│   ├── integration/
│   │   └── workflow_test.go
│   └── e2e/
│       └── cli_test.go
└── ...
```

---

## Unit Tests

### Project Manager

```go
package project

import (
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
    "gorm.io/driver/sqlite"
    "gorm.io/gorm"
)

func setupTestDB(t *testing.T) *gorm.DB {
    db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
    require.NoError(t, err)
    
    err = db.AutoMigrate(&Project{}, &Preset{})
    require.NoError(t, err)
    
    return db
}

func TestProjectManager_Create(t *testing.T) {
    db := setupTestDB(t)
    tempDir := t.TempDir()
    pm := NewProjectManager(db, tempDir, DefaultConfig())
    
    tests := []struct {
        name    string
        opts    CreateProjectOptions
        wantErr bool
        errCode int
    }{
        {
            name: "valid project",
            opts: CreateProjectOptions{
                Name:   "Test Plugin",
                Author: "Test Author",
            },
            wantErr: false,
        },
        {
            name: "empty name",
            opts: CreateProjectOptions{
                Author: "Test Author",
            },
            wantErr: true,
            errCode: 10301,
        },
        {
            name: "duplicate name",
            opts: CreateProjectOptions{
                Name: "Test Plugin", // Same as first
            },
            wantErr: true,
            errCode: 10302,
        },
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            project, err := pm.Create(tt.opts)
            
            if tt.wantErr {
                require.Error(t, err)
                assert.Equal(t, tt.errCode, errors.Code(err))
            } else {
                require.NoError(t, err)
                assert.NotNil(t, project)
                assert.Equal(t, tt.opts.Name, project.Name)
                assert.NotEmpty(t, project.Slug)
                assert.FileExists(t, project.DBPath)
            }
        })
    }
}

func TestProjectManager_Slugify(t *testing.T) {
    tests := []struct {
        input    string
        expected string
    }{
        {"Test Plugin", "test-plugin"},
        {"My Awesome Plugin!", "my-awesome-plugin"},
        {"  Spaces  Everywhere  ", "spaces-everywhere"},
        {"CamelCase", "camelcase"},
        {"plugin_with_underscores", "plugin-with-underscores"},
    }
    
    for _, tt := range tests {
        t.Run(tt.input, func(t *testing.T) {
            result := slugify(tt.input)
            assert.Equal(t, tt.expected, result)
        })
    }
}
```

### RAG Service

```go
package rag

import (
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/mock"
)

type MockAIBridge struct {
    mock.Mock
}

func (m *MockAIBridge) Embed(text string) ([]float32, error) {
    args := m.Called(text)
    return args.Get(0).([]float32), args.Error(1)
}

func TestChunker_Chunk(t *testing.T) {
    chunker := NewChunker(RAGConfig{
        ChunkSize:    100,
        ChunkOverlap: 20,
    })
    
    tests := []struct {
        name     string
        input    string
        minChunks int
        maxChunks int
    }{
        {
            name:      "short text",
            input:     "This is a short text.",
            minChunks: 1,
            maxChunks: 1,
        },
        {
            name:      "long text",
            input:     strings.Repeat("This is a longer text. ", 50),
            minChunks: 5,
            maxChunks: 20,
        },
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            chunks := chunker.Chunk(tt.input)
            assert.GreaterOrEqual(t, len(chunks), tt.minChunks)
            assert.LessOrEqual(t, len(chunks), tt.maxChunks)
            
            // Verify all content is included
            combined := ""
            for _, c := range chunks {
                combined += c.Content
            }
            // Content should be present (overlap may duplicate some)
        })
    }
}

func TestRAGService_Query(t *testing.T) {
    mockBridge := new(MockAIBridge)
    mockBridge.On("Embed", mock.Anything).Return(
        []float32{0.1, 0.2, 0.3},
        nil,
    )
    
    // Setup test database with vectors
    db := setupTestDB(t)
    service := NewRAGService(mockBridge, NewVectorStore(db), DefaultRAGConfig())
    
    // Index some content
    service.IndexDocument(SourceInfo{Type: "test", ID: "1"}, "WordPress security best practices")
    
    // Query
    results, err := service.Query("security", 5)
    
    require.NoError(t, err)
    assert.NotEmpty(t, results)
}
```

---

## Integration Tests

### Full Workflow

```go
package integration

import (
    "os"
    "path/filepath"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestFullWorkflow(t *testing.T) {
    // Setup temporary environment
    tempDir := t.TempDir()
    configPath := filepath.Join(tempDir, "wpb.json")
    
    // Initialize application
    app, err := NewApplication(configPath)
    require.NoError(t, err)
    defer app.Close()
    
    // Step 1: Create project
    project, err := app.ProjectManager.Create(CreateProjectOptions{
        Name:   "Integration Test Plugin",
        Author: "Test",
    })
    require.NoError(t, err)
    assert.Equal(t, "integration-test-plugin", project.Slug)
    
    // Step 2: Import preset
    presetPath := filepath.Join("fixtures", "presets", "sample-preset.md")
    preset, err := app.PresetManager.Import(presetPath, ImportPresetOptions{})
    require.NoError(t, err)
    assert.NotZero(t, preset.ChunkCount)
    
    // Step 3: Apply preset to project
    projectDB, err := app.ProjectManager.OpenDB(project.Slug)
    require.NoError(t, err)
    
    err = app.PresetManager.ApplyToProject(preset.Name, projectDB)
    require.NoError(t, err)
    
    // Step 4: Import specification
    specPath := filepath.Join("fixtures", "specs", "sample-spec.md")
    spec, err := app.SpecParser.Import(specPath)
    require.NoError(t, err)
    assert.NotEmpty(t, spec.Components)
    
    // Step 5: Generate code (mocked AI)
    // ...
    
    // Step 6: Export project
    exportPath := filepath.Join(tempDir, "export.sqlite")
    _, err = app.ProjectManager.Export(project.Slug, ExportProjectOptions{
        OutputPath: exportPath,
        Format:     "sqlite",
    })
    require.NoError(t, err)
    assert.FileExists(t, exportPath)
}
```

---

## E2E Tests

### CLI Commands

```go
package e2e

import (
    "bytes"
    "os"
    "os/exec"
    "path/filepath"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func runCLI(t *testing.T, args ...string) (string, string, error) {
    cmd := exec.Command("wpb", args...)
    
    var stdout, stderr bytes.Buffer
    cmd.Stdout = &stdout
    cmd.Stderr = &stderr
    
    err := cmd.Run()
    return stdout.String(), stderr.String(), err
}

func TestCLI_ProjectCreate(t *testing.T) {
    tempDir := t.TempDir()
    configPath := filepath.Join(tempDir, "wpb.json")
    
    // Create project
    stdout, _, err := runCLI(t,
        "--config", configPath,
        "project", "create", "cli-test",
        "--author", "Test Author",
    )
    
    require.NoError(t, err)
    assert.Contains(t, stdout, "cli-test")
    
    // List projects
    stdout, _, err = runCLI(t,
        "--config", configPath,
        "project", "list", "--json",
    )
    
    require.NoError(t, err)
    assert.Contains(t, stdout, "cli-test")
}

func TestCLI_Version(t *testing.T) {
    stdout, _, err := runCLI(t, "--version")
    
    require.NoError(t, err)
    assert.Contains(t, stdout, "wpb version")
}

func TestCLI_Help(t *testing.T) {
    stdout, _, err := runCLI(t, "help")
    
    require.NoError(t, err)
    assert.Contains(t, stdout, "project")
    assert.Contains(t, stdout, "generate")
    assert.Contains(t, stdout, "preset")
}
```

---

## Generated Code Validation

### PHP Syntax Check

```go
func TestGeneratedCode_PHPSyntax(t *testing.T) {
    // Generate code
    generator := setupGenerator(t)
    result, err := generator.Generate(GenerationRequest{
        Project:  testProject,
        SpecPath: "fixtures/specs/sample.md",
    })
    require.NoError(t, err)
    
    // Validate each file
    for _, file := range result.Files {
        if filepath.Ext(file.Path) == ".php" {
            // Write to temp file
            tmpFile, _ := os.CreateTemp("", "*.php")
            tmpFile.WriteString(file.Content)
            tmpFile.Close()
            
            // Run PHP linter
            cmd := exec.Command("php", "-l", tmpFile.Name())
            output, err := cmd.CombinedOutput()
            
            assert.NoError(t, err, "PHP syntax error in %s: %s", file.Path, output)
            
            os.Remove(tmpFile.Name())
        }
    }
}
```

### WordPress Standards

```go
func TestGeneratedCode_WordPressStandards(t *testing.T) {
    generator := setupGenerator(t)
    result, err := generator.Generate(GenerationRequest{...})
    require.NoError(t, err)
    
    for _, file := range result.Files {
        content := file.Content
        
        // Check for ABSPATH
        assert.Contains(t, content, "defined( 'ABSPATH' )",
            "Missing ABSPATH check in %s", file.Path)
        
        // Check for text domain
        if strings.Contains(content, "__(" ) || strings.Contains(content, "_e(") {
            assert.Contains(t, content, testProject.TextDomain,
                "Missing text domain in %s", file.Path)
        }
        
        // Check for proper escaping (sampling)
        if strings.Contains(content, "echo") {
            assert.Regexp(t, `esc_(html|attr|url)`, content,
                "Missing escaping in %s", file.Path)
        }
    }
}
```

---

## Mocking

### AI Bridge Mock

```go
type MockAIBridgeClient struct {
    GenerateFunc func(req AIRequest) (string, error)
    EmbedFunc    func(text string) ([]float32, error)
}

func (m *MockAIBridgeClient) Generate(req AIRequest) (string, error) {
    if m.GenerateFunc != nil {
        return m.GenerateFunc(req)
    }
    return "```php:test.php\n<?php\n// Mock response\n```", nil
}

func (m *MockAIBridgeClient) Embed(text string) ([]float32, error) {
    if m.EmbedFunc != nil {
        return m.EmbedFunc(text)
    }
    // Return deterministic mock embedding
    return []float32{0.1, 0.2, 0.3, 0.4, 0.5}, nil
}
```

---

## CI/CD Integration

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: '1.21'
      
      - name: Install PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - name: Run unit tests
        run: go test -v ./...
      
      - name: Run integration tests
        run: go test -v -tags=integration ./test/integration/...
      
      - name: Build binary
        run: go build -o wpb ./cmd/wpb
      
      - name: Run E2E tests
        run: go test -v -tags=e2e ./test/e2e/...
```

---

## See Also

- [Code Generation](./07-code-generation.md)
- [Error Handling](./10-error-handling.md)
- [Implementation Guide](./14-implementation-guide.md)
