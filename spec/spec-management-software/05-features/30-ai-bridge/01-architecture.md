# AI Bridge: Architecture

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

AI Bridge is a standalone adapter that normalizes input from multiple formats and routes requests to configured LLM backends with unified error handling and response streaming.

---

## Core Components

### 1. Input Router

Routes incoming requests to the appropriate parser based on file extension or content-type.

```go
type InputRouter struct {
    parsers map[string]InputParser
}

type InputParser interface {
    Parse(input []byte) (*NormalizedRequest, error)
    SupportedExtensions() []string
    ContentType() string
}

func (r *InputRouter) Route(filename string, content []byte) (*NormalizedRequest, error) {
    ext := filepath.Ext(filename)
    parser, ok := r.parsers[ext]
    if !ok {
        return nil, NewError(ErrUnsupportedFormat, "unsupported format: %s", ext)
    }
    return parser.Parse(content)
}
```

### 2. Normalized Request

All input formats are normalized to this structure:

```go
type NormalizedRequest struct {
    // Core fields
    ID            string            `json:"id"`
    SystemPrompt  string            `json:"systemPrompt"`
    UserPrompt    string            `json:"userPrompt"`
    
    // Model selection
    ModelCategory ModelCategory     `json:"modelCategory"` // thinking, writing, coding, voice
    ModelID       string            `json:"modelId,omitempty"` // Specific model override
    
    // Parameters
    Temperature   float64           `json:"temperature,omitempty"`
    MaxTokens     int               `json:"maxTokens,omitempty"`
    TopP          float64           `json:"topP,omitempty"`
    
    // Context
    Variables     map[string]string `json:"variables,omitempty"`
    Context       []ContextItem     `json:"context,omitempty"`
    
    // Execution
    Stream        bool              `json:"stream"`
    OutputFormat  OutputFormat      `json:"outputFormat"` // text, json, markdown
    BatchMode     bool              `json:"batchMode"`
    BatchItems    []BatchItem       `json:"batchItems,omitempty"`
    
    // Metadata
    Source        InputSource       `json:"source"`
    CreatedAt     time.Time         `json:"createdAt"`
}

type InputSource struct {
    Format   string `json:"format"` // markdown, json, yaml, csv
    FilePath string `json:"filePath,omitempty"`
    LineNo   int    `json:"lineNo,omitempty"`
}

type ContextItem struct {
    Role    string `json:"role"` // system, user, assistant
    Content string `json:"content"`
}

type BatchItem struct {
    ID        string            `json:"id"`
    Variables map[string]string `json:"variables"`
}
```

### 3. Backend Adapter Interface

```go
type BackendAdapter interface {
    Name() string
    IsAvailable(ctx context.Context) bool
    
    // Synchronous generation
    Generate(ctx context.Context, req *NormalizedRequest) (*Response, error)
    
    // Streaming generation
    GenerateStream(ctx context.Context, req *NormalizedRequest) (<-chan StreamChunk, error)
    
    // Model management
    ListModels(ctx context.Context) ([]ModelInfo, error)
    LoadModel(ctx context.Context, modelID string) error
    UnloadModel(ctx context.Context, modelID string) error
}

type Response struct {
    ID            string    `json:"id"`
    Content       string    `json:"content"`
    FinishReason  string    `json:"finishReason"`
    TokensUsed    TokenUsage `json:"tokensUsed"`
    DurationMs    int64     `json:"durationMs"`
    ModelUsed     string    `json:"modelUsed"`
}

type StreamChunk struct {
    Delta        string `json:"delta"`
    FinishReason string `json:"finishReason,omitempty"`
    Error        error  `json:"-"`
}
```

---

## Request Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          REQUEST PROCESSING FLOW                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   1. INPUT               2. PARSE              3. VALIDATE                  │
│   ─────────────────      ─────────────────     ─────────────────            │
│   File/API request  ───▶  Format-specific  ───▶ Schema validation           │
│   (MD/JSON/YAML/CSV)     parser                 + variable resolution        │
│                                                                              │
│   4. NORMALIZE           5. ROUTE              6. EXECUTE                   │
│   ─────────────────      ─────────────────     ─────────────────            │
│   Convert to         ───▶ Select backend   ───▶ Call LLM with               │
│   NormalizedRequest      (Ollama/llama.cpp)    retry logic                  │
│                                                                              │
│   7. TRANSFORM           8. OUTPUT                                          │
│   ─────────────────      ─────────────────                                  │
│   Format response    ───▶ Return via CLI                                    │
│   (text/json/md)         stdout or API                                      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Backend Priority

When multiple backends are configured, AI Bridge uses this priority:

1. **Explicit override** — `--backend ollama` CLI flag
2. **Model availability** — Check which backend has the requested model loaded
3. **Health status** — Use the healthiest backend
4. **Config default** — Fall back to `ai.backend` config value

```go
func (m *BackendManager) SelectBackend(ctx context.Context, req *NormalizedRequest) (BackendAdapter, error) {
    // 1. Explicit override
    if req.BackendOverride != "" {
        if backend, ok := m.backends[req.BackendOverride]; ok {
            return backend, nil
        }
    }
    
    // 2. Check model availability
    for _, backend := range m.backends {
        if backend.HasModel(req.ModelID) && backend.IsAvailable(ctx) {
            return backend, nil
        }
    }
    
    // 3. Health-based selection
    healthiest := m.getHealthiestBackend()
    if healthiest != nil {
        return healthiest, nil
    }
    
    // 4. Config default
    return m.backends[m.config.DefaultBackend], nil
}
```

---

## Retry & Failover

```go
type RetryConfig struct {
    MaxAttempts     int           `yaml:"maxAttempts"`
    InitialDelay    time.Duration `yaml:"initialDelay"`
    MaxDelay        time.Duration `yaml:"maxDelay"`
    BackoffFactor   float64       `yaml:"backoffFactor"`
    RetryableErrors []int         `yaml:"retryableErrors"` // Error codes to retry
}

var DefaultRetryConfig = RetryConfig{
    MaxAttempts:     3,
    InitialDelay:    500 * time.Millisecond,
    MaxDelay:        10 * time.Second,
    BackoffFactor:   2.0,
    RetryableErrors: []int{9200, 9201, 9202}, // Backend connection errors
}
```

---

## Directory Structure

```
cmd/
├── aibridge/
│   └── main.go              # CLI entrypoint
internal/
├── bridge/
│   ├── router.go            # Input router
│   ├── normalizer.go        # Request normalizer
│   └── executor.go          # Execution engine
├── parser/
│   ├── parser.go            # Parser interface
│   ├── markdown.go          # Markdown parser
│   ├── json.go              # JSON parser
│   ├── yaml.go              # YAML parser
│   └── csv.go               # CSV parser
├── backend/
│   ├── adapter.go           # Backend interface
│   ├── ollama.go            # Ollama adapter
│   ├── llamacpp.go          # llama.cpp adapter
│   └── manager.go           # Backend manager
├── daemon/
│   ├── server.go            # HTTP/WebSocket server
│   └── handler.go           # Request handlers
└── config/
    └── config.go            # Configuration
```

---

## See Also

- [Input Formats](./02-input-formats.md)
- [Startup Modes](./03-startup-modes.md)
- [Error Codes](./05-error-codes.md)
