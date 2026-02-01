# AI-Bridge Service Specification

> **Phase**: 5  
> **Service ID**: `ai-bridge`  
> **Default Port**: `:8084`  
> **Status**: Specification Complete  
> **Date**: 2026-01-30  
> **Dependencies**: pkg/errors, pkg/logging, pkg/config, pkg/types

---

## 1. Executive Summary

AI-Bridge is the **unified LLM abstraction layer** that provides a consistent API for interacting with multiple AI model backends. It handles provider routing, streaming responses, model lifecycle management, and failover between Ollama, llama.cpp (router mode), and llama-swap proxy configurations.

### 1.1 Core Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **Provider Abstraction** | Unified interface across Ollama, llama.cpp, llama-swap |
| **Model Routing** | Route requests to appropriate backend based on model ID |
| **Streaming** | Server-Sent Events (SSE) for real-time token streaming |
| **Load Management** | Model loading/unloading, warmup, TTL-based eviction |
| **Failover** | Automatic fallback to secondary providers on failure |
| **Rate Limiting** | Per-model and per-client rate limiting |
| **Metrics** | Token counts, latency tracking, error rates |

### 1.2 Design Principles

1. **Provider Agnostic**: Client code never knows which backend serves the request  
2. **Streaming First**: All completions stream by default; buffered mode is opt-in  
3. **Graceful Degradation**: Service continues with reduced capacity on partial failures  
4. **Observable**: Every request traced with correlation IDs, latency histograms

---

## 2. Architecture

### 2.1 Component Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                            AI-Bridge Service                            │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │
│  │   Router    │  │  Provider   │  │   Stream    │  │   Model     │    │
│  │  Middleware │──│   Manager   │──│   Handler   │──│   Registry  │    │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘    │
│         │                │                │                │            │
│         ▼                ▼                ▼                ▼            │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                      Provider Adapters                          │   │
│  ├─────────────────┬─────────────────┬─────────────────────────────┤   │
│  │  OllamaAdapter  │  LlamaAdapter   │  LlamaSwapAdapter           │   │
│  │  (Native API)   │  (Router Mode)  │  (Proxy Mode)               │   │
│  └─────────────────┴─────────────────┴─────────────────────────────┘   │
│                              │                                          │
└──────────────────────────────│──────────────────────────────────────────┘
                               ▼
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
        ▼                      ▼                      ▼
   ┌─────────┐           ┌─────────┐           ┌─────────┐
   │ Ollama  │           │ llama   │           │ llama   │
   │ :11434  │           │ -server │           │ -swap   │
   │         │           │ :8085   │           │ :8086   │
   └─────────┘           └─────────┘           └─────────┘
```

### 2.2 Package Structure

```
services/ai-bridge/
├── cmd/
│   └── ai-bridge/
│       └── main.go              # Entry point with graceful shutdown
├── internal/
│   ├── config/
│   │   └── config.go            # Service-specific configuration
│   ├── handler/
│   │   ├── completion.go        # /v1/chat/completions endpoint
│   │   ├── models.go            # /v1/models endpoint
│   │   ├── health.go            # Health and readiness probes
│   │   └── stream.go            # SSE streaming utilities
│   ├── provider/
│   │   ├── interface.go         # Provider contract definition
│   │   ├── ollama.go            # Ollama adapter implementation
│   │   ├── llama.go             # llama.cpp adapter (router mode)
│   │   ├── llamaswap.go         # llama-swap proxy adapter
│   │   └── registry.go          # Provider registry and routing
│   ├── model/
│   │   ├── manager.go           # Model lifecycle management
│   │   ├── slot.go              # Model slot allocation
│   │   └── warmup.go            # Model preloading logic
│   ├── middleware/
│   │   ├── ratelimit.go         # Token bucket rate limiting
│   │   ├── metrics.go           # Prometheus metrics collection
│   │   └── trace.go             # Request tracing propagation
│   └── service/
│       └── bridge.go            # Core service orchestration
├── api/
│   └── openapi.yaml             # OpenAPI 3.0 specification
└── Makefile
```

---

## 3. Provider Interface

### 3.1 Core Contract

```go
// File: internal/provider/interface.go
package provider

import (
    "context"
    "io"
    
    "spec-manager/pkg/errors"
    "spec-manager/pkg/types"
)

// Provider defines the contract all LLM backends must implement.
// Each method MUST propagate context for cancellation and tracing.
type Provider interface {
    // ID returns the unique provider identifier (e.g., "ollama", "llama-router")
    ID() string
    
    // Name returns human-readable provider name
    Name() string
    
    // Available checks if provider is reachable and ready
    Available(ctx context.Context) bool
    
    // Models returns list of available models on this provider
    Models(ctx context.Context) ([]ModelInfo, error)
    
    // LoadModel loads a model into memory (may be no-op for some providers)
    LoadModel(ctx context.Context, modelID string) error
    
    // UnloadModel removes model from memory
    UnloadModel(ctx context.Context, modelID string) error
    
    // ModelStatus returns current status of a model
    ModelStatus(ctx context.Context, modelID string) (ModelStatus, error)
    
    // Complete performs a chat completion (non-streaming)
    Complete(ctx context.Context, req CompletionRequest) (*CompletionResponse, error)
    
    // Stream performs a streaming chat completion
    // Returns a channel that emits tokens until completion or error
    Stream(ctx context.Context, req CompletionRequest) (<-chan StreamChunk, error)
    
    // Embeddings generates embeddings for input text (optional capability)
    Embeddings(ctx context.Context, req EmbeddingRequest) (*EmbeddingResponse, error)
}

// ModelInfo describes an available model
type ModelInfo struct {
    ID          string            `json:"id"`
    Name        string            `json:"name"`
    Provider    string            `json:"provider"`
    Size        int64             `json:"size_bytes"`
    Parameters  string            `json:"parameters"`      // e.g., "7B", "13B"
    Quantization string           `json:"quantization"`    // e.g., "Q4_K_M"
    ContextSize int               `json:"context_size"`
    Capabilities []string         `json:"capabilities"`    // ["chat", "embedding", "vision"]
    Metadata    map[string]string `json:"metadata"`
}

// ModelStatus represents current model state
type ModelStatus string

const (
    ModelStatusUnknown   ModelStatus = "unknown"
    ModelStatusUnloaded  ModelStatus = "unloaded"
    ModelStatusLoading   ModelStatus = "loading"
    ModelStatusLoaded    ModelStatus = "loaded"
    ModelStatusError     ModelStatus = "error"
)

// CompletionRequest mirrors OpenAI chat completion request format
type CompletionRequest struct {
    Model       string    `json:"model"`
    Messages    []Message `json:"messages"`
    Temperature float64   `json:"temperature,omitempty"`
    MaxTokens   int       `json:"max_tokens,omitempty"`
    TopP        float64   `json:"top_p,omitempty"`
    TopK        int       `json:"top_k,omitempty"`
    Stop        []string  `json:"stop,omitempty"`
    Stream      bool      `json:"stream,omitempty"`
    
    // Provider-specific options (passed through)
    Options     map[string]interface{} `json:"options,omitempty"`
    
    // Internal tracking
    RequestID   string    `json:"-"`
    ClientID    string    `json:"-"`
}

// Message represents a chat message
type Message struct {
    Role    string `json:"role"`    // "system", "user", "assistant"
    Content string `json:"content"`
}

// CompletionResponse for non-streaming completions
type CompletionResponse struct {
    ID      string   `json:"id"`
    Model   string   `json:"model"`
    Created int64    `json:"created"`
    Choices []Choice `json:"choices"`
    Usage   Usage    `json:"usage"`
}

// Choice represents a completion choice
type Choice struct {
    Index        int     `json:"index"`
    Message      Message `json:"message"`
    FinishReason string  `json:"finish_reason"` // "stop", "length", "error"
}

// Usage tracks token consumption
type Usage struct {
    PromptTokens     int `json:"prompt_tokens"`
    CompletionTokens int `json:"completion_tokens"`
    TotalTokens      int `json:"total_tokens"`
}

// StreamChunk represents a single streaming token
type StreamChunk struct {
    ID      string       `json:"id"`
    Model   string       `json:"model"`
    Created int64        `json:"created"`
    Delta   *DeltaChoice `json:"delta,omitempty"`
    
    // Error is set if streaming encountered an error
    Error   error        `json:"-"`
    
    // Done signals stream completion
    Done    bool         `json:"done"`
    
    // Final usage stats (only on last chunk)
    Usage   *Usage       `json:"usage,omitempty"`
}

// DeltaChoice for streaming deltas
type DeltaChoice struct {
    Index        int    `json:"index"`
    Delta        Delta  `json:"delta"`
    FinishReason string `json:"finish_reason,omitempty"`
}

// Delta contains the incremental content
type Delta struct {
    Role    string `json:"role,omitempty"`
    Content string `json:"content,omitempty"`
}

// EmbeddingRequest for generating embeddings
type EmbeddingRequest struct {
    Model string   `json:"model"`
    Input []string `json:"input"`
}

// EmbeddingResponse contains generated embeddings
type EmbeddingResponse struct {
    Model      string      `json:"model"`
    Embeddings [][]float32 `json:"embeddings"`
    Usage      Usage       `json:"usage"`
}
```

### 3.2 Provider Errors

```go
// Provider-specific error codes (8xxx range per pkg/errors spec)
const (
    // 8100-8199: Provider connectivity errors
    ErrProviderUnavailable    = 8100  // Provider not reachable
    ErrProviderTimeout        = 8101  // Request timed out
    ErrProviderRejected       = 8102  // Provider rejected request
    
    // 8200-8299: Model errors
    ErrModelNotFound          = 8200  // Model not available on provider
    ErrModelLoadFailed        = 8201  // Failed to load model
    ErrModelBusy              = 8202  // Model is busy/loading
    ErrModelContextExceeded   = 8203  // Input exceeds context window
    
    // 8300-8399: Streaming errors
    ErrStreamInitFailed       = 8300  // Failed to initialize stream
    ErrStreamInterrupted      = 8301  // Stream was interrupted
    ErrStreamTimeout          = 8302  // Stream timed out
    
    // 8400-8499: Rate limiting
    ErrRateLimitExceeded      = 8400  // Too many requests
    ErrQuotaExceeded          = 8401  // Token quota exceeded
    
    // 8500-8599: Routing errors
    ErrNoProviderAvailable    = 8500  // No providers can serve request
    ErrRoutingFailed          = 8501  // Failed to route to provider
    ErrFailoverExhausted      = 8502  // All failover attempts failed
)
```

---

## 4. Provider Implementations

### 4.1 Ollama Adapter

```go
// File: internal/provider/ollama.go
package provider

import (
    "bufio"
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "io"
    "net/http"
    "runtime"
    "time"
    
    "spec-manager/pkg/errors"
    "spec-manager/pkg/logging"
)

// OllamaAdapter implements Provider for Ollama server
type OllamaAdapter struct {
    baseURL    string
    httpClient *http.Client
    logger     *logging.Logger
}

// OllamaConfig for adapter initialization
type OllamaConfig struct {
    Host           string        `mapstructure:"host"`
    Port           int           `mapstructure:"port"`
    Timeout        time.Duration `mapstructure:"timeout"`
    MaxRetries     int           `mapstructure:"max_retries"`
    KeepAlive      string        `mapstructure:"keep_alive"`
}

// NewOllamaAdapter creates a new Ollama provider adapter
// MUST log function entry with file:line for debugging
func NewOllamaAdapter(cfg OllamaConfig, logger *logging.Logger) *OllamaAdapter {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("creating Ollama adapter",
        "func", "NewOllamaAdapter",
        "file", fmt.Sprintf("%s:%d", file, line),
        "host", cfg.Host,
        "port", cfg.Port,
    )
    
    return &OllamaAdapter{
        baseURL: fmt.Sprintf("http://%s:%d", cfg.Host, cfg.Port),
        httpClient: &http.Client{
            Timeout: cfg.Timeout,
        },
        logger: logger,
    }
}

func (o *OllamaAdapter) ID() string   { return "ollama" }
func (o *OllamaAdapter) Name() string { return "Ollama Server" }

// Available checks if Ollama server is reachable
func (o *OllamaAdapter) Available(ctx context.Context) bool {
    _, file, line, _ := runtime.Caller(0)
    o.logger.Debug("checking Ollama availability",
        "func", "Available",
        "file", fmt.Sprintf("%s:%d", file, line),
    )
    
    req, err := http.NewRequestWithContext(ctx, "GET", o.baseURL+"/api/tags", nil)
    if err != nil {
        return false
    }
    
    resp, err := o.httpClient.Do(req)
    if err != nil {
        o.logger.Warn("Ollama unavailable", "error", err)
        return false
    }
    defer resp.Body.Close()
    
    return resp.StatusCode == http.StatusOK
}

// Models returns all models available on Ollama
func (o *OllamaAdapter) Models(ctx context.Context) ([]ModelInfo, error) {
    _, file, line, _ := runtime.Caller(0)
    o.logger.Debug("fetching Ollama models",
        "func", "Models",
        "file", fmt.Sprintf("%s:%d", file, line),
    )
    
    req, err := http.NewRequestWithContext(ctx, "GET", o.baseURL+"/api/tags", nil)
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to create request", err)
    }
    
    resp, err := o.httpClient.Do(req)
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to fetch models", err)
    }
    defer resp.Body.Close()
    
    var result struct {
        Models []struct {
            Name       string `json:"name"`
            Size       int64  `json:"size"`
            Digest     string `json:"digest"`
            ModifiedAt string `json:"modified_at"`
            Details    struct {
                Format       string `json:"format"`
                Family       string `json:"family"`
                ParameterSize string `json:"parameter_size"`
                QuantizationLevel string `json:"quantization_level"`
            } `json:"details"`
        } `json:"models"`
    }
    
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to decode response", err)
    }
    
    models := make([]ModelInfo, len(result.Models))
    for i, m := range result.Models {
        models[i] = ModelInfo{
            ID:          m.Name,
            Name:        m.Name,
            Provider:    o.ID(),
            Size:        m.Size,
            Parameters:  m.Details.ParameterSize,
            Quantization: m.Details.QuantizationLevel,
        }
    }
    
    return models, nil
}

// Stream performs streaming chat completion via Ollama
func (o *OllamaAdapter) Stream(ctx context.Context, req CompletionRequest) (<-chan StreamChunk, error) {
    _, file, line, _ := runtime.Caller(0)
    o.logger.Info("starting Ollama stream",
        "func", "Stream",
        "file", fmt.Sprintf("%s:%d", file, line),
        "model", req.Model,
        "request_id", req.RequestID,
    )
    
    // Convert to Ollama format
    ollamaReq := o.toOllamaRequest(req)
    ollamaReq["stream"] = true
    
    body, err := json.Marshal(ollamaReq)
    if err != nil {
        return nil, errors.New(ErrStreamInitFailed, "failed to marshal request", err)
    }
    
    httpReq, err := http.NewRequestWithContext(
        ctx,
        "POST",
        o.baseURL+"/api/chat",
        bytes.NewReader(body),
    )
    if err != nil {
        return nil, errors.New(ErrStreamInitFailed, "failed to create request", err)
    }
    httpReq.Header.Set("Content-Type", "application/json")
    
    resp, err := o.httpClient.Do(httpReq)
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to connect", err)
    }
    
    if resp.StatusCode != http.StatusOK {
        resp.Body.Close()
        return nil, errors.New(ErrProviderRejected,
            fmt.Sprintf("Ollama returned status %d", resp.StatusCode), nil)
    }
    
    chunks := make(chan StreamChunk, 100)
    
    go func() {
        defer close(chunks)
        defer resp.Body.Close()
        
        scanner := bufio.NewScanner(resp.Body)
        // Increase buffer for large responses
        scanner.Buffer(make([]byte, 64*1024), 1024*1024)
        
        for scanner.Scan() {
            select {
            case <-ctx.Done():
                chunks <- StreamChunk{Error: ctx.Err(), Done: true}
                return
            default:
            }
            
            line := scanner.Bytes()
            if len(line) == 0 {
                continue
            }
            
            var ollamaResp struct {
                Model     string `json:"model"`
                CreatedAt string `json:"created_at"`
                Message   struct {
                    Role    string `json:"role"`
                    Content string `json:"content"`
                } `json:"message"`
                Done bool `json:"done"`
                TotalDuration   int64 `json:"total_duration"`
                PromptEvalCount int   `json:"prompt_eval_count"`
                EvalCount       int   `json:"eval_count"`
            }
            
            if err := json.Unmarshal(line, &ollamaResp); err != nil {
                o.logger.Warn("failed to parse stream chunk",
                    "error", err,
                    "line", string(line),
                )
                continue
            }
            
            chunk := StreamChunk{
                ID:      req.RequestID,
                Model:   ollamaResp.Model,
                Created: time.Now().Unix(),
                Delta: &DeltaChoice{
                    Index: 0,
                    Delta: Delta{
                        Content: ollamaResp.Message.Content,
                    },
                },
                Done: ollamaResp.Done,
            }
            
            if ollamaResp.Done {
                chunk.Usage = &Usage{
                    PromptTokens:     ollamaResp.PromptEvalCount,
                    CompletionTokens: ollamaResp.EvalCount,
                    TotalTokens:      ollamaResp.PromptEvalCount + ollamaResp.EvalCount,
                }
                chunk.Delta.FinishReason = "stop"
            }
            
            chunks <- chunk
        }
        
        if err := scanner.Err(); err != nil {
            o.logger.Error("stream scanner error",
                "func", "Stream",
                "error", err,
            )
            chunks <- StreamChunk{Error: err, Done: true}
        }
    }()
    
    return chunks, nil
}

// toOllamaRequest converts CompletionRequest to Ollama format
func (o *OllamaAdapter) toOllamaRequest(req CompletionRequest) map[string]interface{} {
    messages := make([]map[string]string, len(req.Messages))
    for i, m := range req.Messages {
        messages[i] = map[string]string{
            "role":    m.Role,
            "content": m.Content,
        }
    }
    
    result := map[string]interface{}{
        "model":    req.Model,
        "messages": messages,
    }
    
    options := make(map[string]interface{})
    if req.Temperature > 0 {
        options["temperature"] = req.Temperature
    }
    if req.MaxTokens > 0 {
        options["num_predict"] = req.MaxTokens
    }
    if req.TopP > 0 {
        options["top_p"] = req.TopP
    }
    if req.TopK > 0 {
        options["top_k"] = req.TopK
    }
    if len(req.Stop) > 0 {
        options["stop"] = req.Stop
    }
    
    // Merge provider-specific options
    for k, v := range req.Options {
        options[k] = v
    }
    
    if len(options) > 0 {
        result["options"] = options
    }
    
    return result
}

// LoadModel triggers model loading in Ollama
func (o *OllamaAdapter) LoadModel(ctx context.Context, modelID string) error {
    _, file, line, _ := runtime.Caller(0)
    o.logger.Info("loading model in Ollama",
        "func", "LoadModel",
        "file", fmt.Sprintf("%s:%d", file, line),
        "model", modelID,
    )
    
    // Ollama auto-loads on first request, but we can prime it
    body, _ := json.Marshal(map[string]interface{}{
        "model": modelID,
        "messages": []map[string]string{
            {"role": "user", "content": "hi"},
        },
        "options": map[string]interface{}{
            "num_predict": 1,
        },
    })
    
    req, err := http.NewRequestWithContext(ctx, "POST", o.baseURL+"/api/chat", bytes.NewReader(body))
    if err != nil {
        return errors.New(ErrModelLoadFailed, "failed to create request", err)
    }
    req.Header.Set("Content-Type", "application/json")
    
    resp, err := o.httpClient.Do(req)
    if err != nil {
        return errors.New(ErrModelLoadFailed, "failed to load model", err)
    }
    defer resp.Body.Close()
    
    // Drain response
    io.Copy(io.Discard, resp.Body)
    
    if resp.StatusCode != http.StatusOK {
        return errors.New(ErrModelLoadFailed,
            fmt.Sprintf("Ollama returned status %d", resp.StatusCode), nil)
    }
    
    return nil
}

// ModelStatus checks if model is loaded in Ollama
func (o *OllamaAdapter) ModelStatus(ctx context.Context, modelID string) (ModelStatus, error) {
    req, err := http.NewRequestWithContext(ctx, "GET", o.baseURL+"/api/ps", nil)
    if err != nil {
        return ModelStatusUnknown, errors.New(ErrProviderUnavailable, "failed to create request", err)
    }
    
    resp, err := o.httpClient.Do(req)
    if err != nil {
        return ModelStatusUnknown, errors.New(ErrProviderUnavailable, "failed to check status", err)
    }
    defer resp.Body.Close()
    
    var result struct {
        Models []struct {
            Name string `json:"name"`
        } `json:"models"`
    }
    
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return ModelStatusUnknown, errors.New(ErrProviderUnavailable, "failed to decode response", err)
    }
    
    for _, m := range result.Models {
        if m.Name == modelID {
            return ModelStatusLoaded, nil
        }
    }
    
    return ModelStatusUnloaded, nil
}

// Complete performs non-streaming completion
func (o *OllamaAdapter) Complete(ctx context.Context, req CompletionRequest) (*CompletionResponse, error) {
    _, file, line, _ := runtime.Caller(0)
    o.logger.Info("Ollama completion request",
        "func", "Complete",
        "file", fmt.Sprintf("%s:%d", file, line),
        "model", req.Model,
        "request_id", req.RequestID,
    )
    
    ollamaReq := o.toOllamaRequest(req)
    ollamaReq["stream"] = false
    
    body, err := json.Marshal(ollamaReq)
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to marshal request", err)
    }
    
    httpReq, err := http.NewRequestWithContext(ctx, "POST", o.baseURL+"/api/chat", bytes.NewReader(body))
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to create request", err)
    }
    httpReq.Header.Set("Content-Type", "application/json")
    
    resp, err := o.httpClient.Do(httpReq)
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to connect", err)
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != http.StatusOK {
        bodyBytes, _ := io.ReadAll(resp.Body)
        return nil, errors.New(ErrProviderRejected,
            fmt.Sprintf("Ollama returned %d: %s", resp.StatusCode, string(bodyBytes)), nil)
    }
    
    var ollamaResp struct {
        Model     string `json:"model"`
        CreatedAt string `json:"created_at"`
        Message   struct {
            Role    string `json:"role"`
            Content string `json:"content"`
        } `json:"message"`
        PromptEvalCount int `json:"prompt_eval_count"`
        EvalCount       int `json:"eval_count"`
    }
    
    if err := json.NewDecoder(resp.Body).Decode(&ollamaResp); err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to decode response", err)
    }
    
    return &CompletionResponse{
        ID:      req.RequestID,
        Model:   ollamaResp.Model,
        Created: time.Now().Unix(),
        Choices: []Choice{
            {
                Index: 0,
                Message: Message{
                    Role:    ollamaResp.Message.Role,
                    Content: ollamaResp.Message.Content,
                },
                FinishReason: "stop",
            },
        },
        Usage: Usage{
            PromptTokens:     ollamaResp.PromptEvalCount,
            CompletionTokens: ollamaResp.EvalCount,
            TotalTokens:      ollamaResp.PromptEvalCount + ollamaResp.EvalCount,
        },
    }, nil
}

// UnloadModel is a no-op for Ollama (managed by keep_alive)
func (o *OllamaAdapter) UnloadModel(ctx context.Context, modelID string) error {
    o.logger.Info("Ollama unload requested (managed by keep_alive)",
        "model", modelID,
    )
    return nil
}

// Embeddings generates embeddings via Ollama
func (o *OllamaAdapter) Embeddings(ctx context.Context, req EmbeddingRequest) (*EmbeddingResponse, error) {
    _, file, line, _ := runtime.Caller(0)
    o.logger.Info("Ollama embedding request",
        "func", "Embeddings",
        "file", fmt.Sprintf("%s:%d", file, line),
        "model", req.Model,
        "input_count", len(req.Input),
    )
    
    embeddings := make([][]float32, len(req.Input))
    totalTokens := 0
    
    for i, input := range req.Input {
        body, _ := json.Marshal(map[string]interface{}{
            "model":  req.Model,
            "prompt": input,
        })
        
        httpReq, err := http.NewRequestWithContext(ctx, "POST", o.baseURL+"/api/embeddings", bytes.NewReader(body))
        if err != nil {
            return nil, errors.New(ErrProviderUnavailable, "failed to create request", err)
        }
        httpReq.Header.Set("Content-Type", "application/json")
        
        resp, err := o.httpClient.Do(httpReq)
        if err != nil {
            return nil, errors.New(ErrProviderUnavailable, "failed to get embedding", err)
        }
        
        var embResp struct {
            Embedding []float32 `json:"embedding"`
        }
        json.NewDecoder(resp.Body).Decode(&embResp)
        resp.Body.Close()
        
        embeddings[i] = embResp.Embedding
        totalTokens += len(input) / 4 // Rough estimate
    }
    
    return &EmbeddingResponse{
        Model:      req.Model,
        Embeddings: embeddings,
        Usage: Usage{
            PromptTokens: totalTokens,
            TotalTokens:  totalTokens,
        },
    }, nil
}
```

### 4.2 llama.cpp Router Mode Adapter

```go
// File: internal/provider/llama.go
package provider

import (
    "bufio"
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "net/http"
    "runtime"
    "strings"
    "time"
    
    "spec-manager/pkg/errors"
    "spec-manager/pkg/logging"
)

// LlamaAdapter implements Provider for llama.cpp server in router mode
type LlamaAdapter struct {
    baseURL    string
    modelsDir  string
    httpClient *http.Client
    logger     *logging.Logger
}

// LlamaConfig for llama.cpp adapter
type LlamaConfig struct {
    Host       string        `mapstructure:"host"`
    Port       int           `mapstructure:"port"`
    ModelsDir  string        `mapstructure:"models_dir"`
    RouterMode bool          `mapstructure:"router_mode"`
    Timeout    time.Duration `mapstructure:"timeout"`
}

// NewLlamaAdapter creates a new llama.cpp provider adapter
func NewLlamaAdapter(cfg LlamaConfig, logger *logging.Logger) *LlamaAdapter {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("creating llama.cpp adapter",
        "func", "NewLlamaAdapter",
        "file", fmt.Sprintf("%s:%d", file, line),
        "host", cfg.Host,
        "port", cfg.Port,
        "router_mode", cfg.RouterMode,
    )
    
    return &LlamaAdapter{
        baseURL:   fmt.Sprintf("http://%s:%d", cfg.Host, cfg.Port),
        modelsDir: cfg.ModelsDir,
        httpClient: &http.Client{
            Timeout: cfg.Timeout,
        },
        logger: logger,
    }
}

func (l *LlamaAdapter) ID() string   { return "llama-router" }
func (l *LlamaAdapter) Name() string { return "llama.cpp Router" }

// Available checks if llama-server is reachable
func (l *LlamaAdapter) Available(ctx context.Context) bool {
    req, err := http.NewRequestWithContext(ctx, "GET", l.baseURL+"/health", nil)
    if err != nil {
        return false
    }
    
    resp, err := l.httpClient.Do(req)
    if err != nil {
        l.logger.Warn("llama-server unavailable", "error", err)
        return false
    }
    defer resp.Body.Close()
    
    return resp.StatusCode == http.StatusOK
}

// Models returns models available in router mode
func (l *LlamaAdapter) Models(ctx context.Context) ([]ModelInfo, error) {
    _, file, line, _ := runtime.Caller(0)
    l.logger.Debug("fetching llama.cpp models",
        "func", "Models",
        "file", fmt.Sprintf("%s:%d", file, line),
    )
    
    req, err := http.NewRequestWithContext(ctx, "GET", l.baseURL+"/models", nil)
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to create request", err)
    }
    
    resp, err := l.httpClient.Do(req)
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to fetch models", err)
    }
    defer resp.Body.Close()
    
    var result struct {
        Data []struct {
            ID     string `json:"id"`
            Object string `json:"object"`
        } `json:"data"`
    }
    
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to decode response", err)
    }
    
    models := make([]ModelInfo, len(result.Data))
    for i, m := range result.Data {
        models[i] = ModelInfo{
            ID:       m.ID,
            Name:     m.ID,
            Provider: l.ID(),
        }
    }
    
    return models, nil
}

// LoadModel loads a model in router mode
func (l *LlamaAdapter) LoadModel(ctx context.Context, modelID string) error {
    _, file, line, _ := runtime.Caller(0)
    l.logger.Info("loading model in llama.cpp",
        "func", "LoadModel",
        "file", fmt.Sprintf("%s:%d", file, line),
        "model", modelID,
    )
    
    // In router mode, POST to /models/load
    body, _ := json.Marshal(map[string]string{
        "model": l.modelsDir + "/" + modelID,
    })
    
    req, err := http.NewRequestWithContext(ctx, "POST", l.baseURL+"/models/load", bytes.NewReader(body))
    if err != nil {
        return errors.New(ErrModelLoadFailed, "failed to create request", err)
    }
    req.Header.Set("Content-Type", "application/json")
    
    resp, err := l.httpClient.Do(req)
    if err != nil {
        return errors.New(ErrModelLoadFailed, "failed to load model", err)
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != http.StatusOK {
        return errors.New(ErrModelLoadFailed,
            fmt.Sprintf("llama-server returned %d", resp.StatusCode), nil)
    }
    
    return nil
}

// Stream performs streaming completion via llama.cpp OpenAI-compatible endpoint
func (l *LlamaAdapter) Stream(ctx context.Context, req CompletionRequest) (<-chan StreamChunk, error) {
    _, file, line, _ := runtime.Caller(0)
    l.logger.Info("starting llama.cpp stream",
        "func", "Stream",
        "file", fmt.Sprintf("%s:%d", file, line),
        "model", req.Model,
        "request_id", req.RequestID,
    )
    
    // Use OpenAI-compatible endpoint
    openaiReq := map[string]interface{}{
        "model":    req.Model,
        "messages": req.Messages,
        "stream":   true,
    }
    
    if req.Temperature > 0 {
        openaiReq["temperature"] = req.Temperature
    }
    if req.MaxTokens > 0 {
        openaiReq["max_tokens"] = req.MaxTokens
    }
    if req.TopP > 0 {
        openaiReq["top_p"] = req.TopP
    }
    if len(req.Stop) > 0 {
        openaiReq["stop"] = req.Stop
    }
    
    body, err := json.Marshal(openaiReq)
    if err != nil {
        return nil, errors.New(ErrStreamInitFailed, "failed to marshal request", err)
    }
    
    httpReq, err := http.NewRequestWithContext(ctx, "POST", l.baseURL+"/v1/chat/completions", bytes.NewReader(body))
    if err != nil {
        return nil, errors.New(ErrStreamInitFailed, "failed to create request", err)
    }
    httpReq.Header.Set("Content-Type", "application/json")
    httpReq.Header.Set("Accept", "text/event-stream")
    
    resp, err := l.httpClient.Do(httpReq)
    if err != nil {
        return nil, errors.New(ErrProviderUnavailable, "failed to connect", err)
    }
    
    if resp.StatusCode != http.StatusOK {
        resp.Body.Close()
        return nil, errors.New(ErrProviderRejected,
            fmt.Sprintf("llama-server returned %d", resp.StatusCode), nil)
    }
    
    chunks := make(chan StreamChunk, 100)
    
    go func() {
        defer close(chunks)
        defer resp.Body.Close()
        
        scanner := bufio.NewScanner(resp.Body)
        
        for scanner.Scan() {
            select {
            case <-ctx.Done():
                chunks <- StreamChunk{Error: ctx.Err(), Done: true}
                return
            default:
            }
            
            line := scanner.Text()
            
            // SSE format: "data: {...}"
            if !strings.HasPrefix(line, "data: ") {
                continue
            }
            
            data := strings.TrimPrefix(line, "data: ")
            if data == "[DONE]" {
                chunks <- StreamChunk{Done: true}
                return
            }
            
            var sseResp struct {
                ID      string `json:"id"`
                Model   string `json:"model"`
                Created int64  `json:"created"`
                Choices []struct {
                    Index int `json:"index"`
                    Delta struct {
                        Role    string `json:"role"`
                        Content string `json:"content"`
                    } `json:"delta"`
                    FinishReason string `json:"finish_reason"`
                } `json:"choices"`
            }
            
            if err := json.Unmarshal([]byte(data), &sseResp); err != nil {
                l.logger.Warn("failed to parse SSE chunk", "error", err)
                continue
            }
            
            if len(sseResp.Choices) > 0 {
                choice := sseResp.Choices[0]
                chunks <- StreamChunk{
                    ID:      sseResp.ID,
                    Model:   sseResp.Model,
                    Created: sseResp.Created,
                    Delta: &DeltaChoice{
                        Index: choice.Index,
                        Delta: Delta{
                            Role:    choice.Delta.Role,
                            Content: choice.Delta.Content,
                        },
                        FinishReason: choice.FinishReason,
                    },
                    Done: choice.FinishReason != "",
                }
            }
        }
        
        if err := scanner.Err(); err != nil {
            l.logger.Error("SSE scanner error", "error", err)
            chunks <- StreamChunk{Error: err, Done: true}
        }
    }()
    
    return chunks, nil
}

// Complete, UnloadModel, ModelStatus, Embeddings implementations follow same pattern...
// (Abbreviated for specification - full implementation mirrors Ollama adapter structure)

func (l *LlamaAdapter) Complete(ctx context.Context, req CompletionRequest) (*CompletionResponse, error) {
    // Implementation using /v1/chat/completions with stream: false
    // Returns parsed CompletionResponse
    return nil, errors.New(ErrProviderUnavailable, "not implemented", nil)
}

func (l *LlamaAdapter) UnloadModel(ctx context.Context, modelID string) error {
    body, _ := json.Marshal(map[string]string{"model": modelID})
    req, _ := http.NewRequestWithContext(ctx, "POST", l.baseURL+"/models/unload", bytes.NewReader(body))
    req.Header.Set("Content-Type", "application/json")
    resp, err := l.httpClient.Do(req)
    if err != nil {
        return errors.New(ErrModelLoadFailed, "failed to unload", err)
    }
    resp.Body.Close()
    return nil
}

func (l *LlamaAdapter) ModelStatus(ctx context.Context, modelID string) (ModelStatus, error) {
    return ModelStatusUnknown, nil
}

func (l *LlamaAdapter) Embeddings(ctx context.Context, req EmbeddingRequest) (*EmbeddingResponse, error) {
    return nil, errors.New(ErrProviderUnavailable, "embeddings not supported", nil)
}
```

### 4.3 llama-swap Proxy Adapter

```go
// File: internal/provider/llamaswap.go
package provider

import (
    "context"
    "fmt"
    "runtime"
    "time"
    
    "spec-manager/pkg/logging"
)

// LlamaSwapAdapter implements Provider for llama-swap proxy
// llama-swap automatically handles model loading/swapping based on request
type LlamaSwapAdapter struct {
    *LlamaAdapter  // Embed LlamaAdapter - same OpenAI-compatible API
    swapConfig     LlamaSwapConfig
}

// LlamaSwapConfig for llama-swap proxy
type LlamaSwapConfig struct {
    Host          string            `mapstructure:"host"`
    Port          int               `mapstructure:"port"`
    Timeout       time.Duration     `mapstructure:"timeout"`
    Models        map[string]string `mapstructure:"models"`  // model alias -> file
    DefaultModel  string            `mapstructure:"default_model"`
}

// NewLlamaSwapAdapter creates adapter for llama-swap proxy
func NewLlamaSwapAdapter(cfg LlamaSwapConfig, logger *logging.Logger) *LlamaSwapAdapter {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("creating llama-swap adapter",
        "func", "NewLlamaSwapAdapter",
        "file", fmt.Sprintf("%s:%d", file, line),
        "host", cfg.Host,
        "port", cfg.Port,
        "models_count", len(cfg.Models),
    )
    
    // llama-swap uses same API as llama-server
    llamaAdapter := &LlamaAdapter{
        baseURL: fmt.Sprintf("http://%s:%d", cfg.Host, cfg.Port),
        httpClient: &http.Client{
            Timeout: cfg.Timeout,
        },
        logger: logger,
    }
    
    return &LlamaSwapAdapter{
        LlamaAdapter: llamaAdapter,
        swapConfig:   cfg,
    }
}

func (ls *LlamaSwapAdapter) ID() string   { return "llama-swap" }
func (ls *LlamaSwapAdapter) Name() string { return "llama-swap Proxy" }

// LoadModel is a no-op - llama-swap auto-loads on request
func (ls *LlamaSwapAdapter) LoadModel(ctx context.Context, modelID string) error {
    ls.logger.Info("llama-swap auto-loads on request",
        "model", modelID,
    )
    return nil
}

// UnloadModel triggers TTL-based unload in llama-swap
func (ls *LlamaSwapAdapter) UnloadModel(ctx context.Context, modelID string) error {
    ls.logger.Info("llama-swap manages unload via TTL",
        "model", modelID,
    )
    return nil
}

// Models returns configured models from swap config
func (ls *LlamaSwapAdapter) Models(ctx context.Context) ([]ModelInfo, error) {
    models := make([]ModelInfo, 0, len(ls.swapConfig.Models))
    for alias := range ls.swapConfig.Models {
        models = append(models, ModelInfo{
            ID:       alias,
            Name:     alias,
            Provider: ls.ID(),
        })
    }
    return models, nil
}
```

---

## 5. Provider Registry and Routing

### 5.1 Registry Implementation

```go
// File: internal/provider/registry.go
package provider

import (
    "context"
    "fmt"
    "runtime"
    "sync"
    
    "spec-manager/pkg/errors"
    "spec-manager/pkg/logging"
)

// Registry manages provider instances and routes requests
type Registry struct {
    mu          sync.RWMutex
    providers   map[string]Provider
    modelRoutes map[string][]ModelRoute  // model -> ordered providers
    logger      *logging.Logger
}

// ModelRoute defines routing priority for a model
type ModelRoute struct {
    ProviderID string
    Priority   int
    Fallback   bool
}

// NewRegistry creates a provider registry
func NewRegistry(logger *logging.Logger) *Registry {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("creating provider registry",
        "func", "NewRegistry",
        "file", fmt.Sprintf("%s:%d", file, line),
    )
    
    return &Registry{
        providers:   make(map[string]Provider),
        modelRoutes: make(map[string][]ModelRoute),
        logger:      logger,
    }
}

// Register adds a provider to the registry
func (r *Registry) Register(p Provider) error {
    r.mu.Lock()
    defer r.mu.Unlock()
    
    _, file, line, _ := runtime.Caller(0)
    r.logger.Info("registering provider",
        "func", "Register",
        "file", fmt.Sprintf("%s:%d", file, line),
        "provider_id", p.ID(),
        "provider_name", p.Name(),
    )
    
    if _, exists := r.providers[p.ID()]; exists {
        return errors.New(ErrRoutingFailed,
            fmt.Sprintf("provider %s already registered", p.ID()), nil)
    }
    
    r.providers[p.ID()] = p
    return nil
}

// AddRoute configures routing for a model
func (r *Registry) AddRoute(modelID string, route ModelRoute) {
    r.mu.Lock()
    defer r.mu.Unlock()
    
    r.modelRoutes[modelID] = append(r.modelRoutes[modelID], route)
    
    // Sort by priority (lower = higher priority)
    routes := r.modelRoutes[modelID]
    for i := 0; i < len(routes)-1; i++ {
        for j := i + 1; j < len(routes); j++ {
            if routes[j].Priority < routes[i].Priority {
                routes[i], routes[j] = routes[j], routes[i]
            }
        }
    }
}

// GetProvider returns provider for a model with failover support
func (r *Registry) GetProvider(ctx context.Context, modelID string) (Provider, error) {
    r.mu.RLock()
    defer r.mu.RUnlock()
    
    _, file, line, _ := runtime.Caller(0)
    
    routes, exists := r.modelRoutes[modelID]
    if !exists || len(routes) == 0 {
        // Try to find any provider that has this model
        return r.findProviderWithModel(ctx, modelID)
    }
    
    // Try providers in priority order
    var lastErr error
    for _, route := range routes {
        provider, ok := r.providers[route.ProviderID]
        if !ok {
            continue
        }
        
        if provider.Available(ctx) {
            r.logger.Debug("routing to provider",
                "func", "GetProvider",
                "file", fmt.Sprintf("%s:%d", file, line),
                "model", modelID,
                "provider", route.ProviderID,
            )
            return provider, nil
        }
        
        lastErr = errors.New(ErrProviderUnavailable,
            fmt.Sprintf("provider %s unavailable", route.ProviderID), nil)
    }
    
    if lastErr != nil {
        return nil, errors.New(ErrFailoverExhausted,
            "all providers for model unavailable", lastErr)
    }
    
    return nil, errors.New(ErrNoProviderAvailable,
        fmt.Sprintf("no provider configured for model %s", modelID), nil)
}

// findProviderWithModel searches all providers for a model
func (r *Registry) findProviderWithModel(ctx context.Context, modelID string) (Provider, error) {
    for _, p := range r.providers {
        if !p.Available(ctx) {
            continue
        }
        
        models, err := p.Models(ctx)
        if err != nil {
            continue
        }
        
        for _, m := range models {
            if m.ID == modelID {
                return p, nil
            }
        }
    }
    
    return nil, errors.New(ErrModelNotFound,
        fmt.Sprintf("model %s not found on any provider", modelID), nil)
}

// AllModels returns models from all providers
func (r *Registry) AllModels(ctx context.Context) ([]ModelInfo, error) {
    r.mu.RLock()
    defer r.mu.RUnlock()
    
    var allModels []ModelInfo
    seen := make(map[string]bool)
    
    for _, p := range r.providers {
        if !p.Available(ctx) {
            continue
        }
        
        models, err := p.Models(ctx)
        if err != nil {
            r.logger.Warn("failed to get models from provider",
                "provider", p.ID(),
                "error", err,
            )
            continue
        }
        
        for _, m := range models {
            if !seen[m.ID] {
                seen[m.ID] = true
                allModels = append(allModels, m)
            }
        }
    }
    
    return allModels, nil
}

// HealthCheck returns status of all providers
func (r *Registry) HealthCheck(ctx context.Context) map[string]bool {
    r.mu.RLock()
    defer r.mu.RUnlock()
    
    status := make(map[string]bool)
    for id, p := range r.providers {
        status[id] = p.Available(ctx)
    }
    return status
}
```

---

## 6. Streaming Handler

### 6.1 SSE Stream Handler

```go
// File: internal/handler/stream.go
package handler

import (
    "context"
    "encoding/json"
    "fmt"
    "net/http"
    "runtime"
    "time"
    
    "spec-manager/pkg/errors"
    "spec-manager/pkg/logging"
    "spec-manager/services/ai-bridge/internal/provider"
)

// StreamHandler manages SSE streaming responses
type StreamHandler struct {
    registry *provider.Registry
    logger   *logging.Logger
}

// NewStreamHandler creates a stream handler
func NewStreamHandler(registry *provider.Registry, logger *logging.Logger) *StreamHandler {
    return &StreamHandler{
        registry: registry,
        logger:   logger,
    }
}

// HandleStream processes a streaming completion request
func (h *StreamHandler) HandleStream(w http.ResponseWriter, r *http.Request) {
    _, file, line, _ := runtime.Caller(0)
    ctx := r.Context()
    requestID := ctx.Value("request_id").(string)
    
    h.logger.Info("handling stream request",
        "func", "HandleStream",
        "file", fmt.Sprintf("%s:%d", file, line),
        "request_id", requestID,
    )
    
    // Parse request
    var req provider.CompletionRequest
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        h.writeError(w, errors.New(errors.ErrValidation, "invalid request body", err))
        return
    }
    req.RequestID = requestID
    req.Stream = true
    
    // Get provider for model
    p, err := h.registry.GetProvider(ctx, req.Model)
    if err != nil {
        h.writeError(w, err)
        return
    }
    
    // Set SSE headers
    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")
    w.Header().Set("Connection", "keep-alive")
    w.Header().Set("X-Request-ID", requestID)
    
    flusher, ok := w.(http.Flusher)
    if !ok {
        h.writeError(w, errors.New(provider.ErrStreamInitFailed, "streaming not supported", nil))
        return
    }
    
    // Start streaming
    chunks, err := p.Stream(ctx, req)
    if err != nil {
        h.writeError(w, err)
        return
    }
    
    // Stream chunks to client
    for chunk := range chunks {
        if chunk.Error != nil {
            h.logger.Error("stream error",
                "func", "HandleStream",
                "request_id", requestID,
                "error", chunk.Error,
            )
            // Send error event
            h.writeSSE(w, "error", map[string]string{
                "message": chunk.Error.Error(),
            })
            flusher.Flush()
            return
        }
        
        // Format as OpenAI-compatible SSE
        data := map[string]interface{}{
            "id":      chunk.ID,
            "object":  "chat.completion.chunk",
            "created": chunk.Created,
            "model":   chunk.Model,
            "choices": []map[string]interface{}{
                {
                    "index": 0,
                    "delta": map[string]interface{}{
                        "content": chunk.Delta.Delta.Content,
                    },
                    "finish_reason": nil,
                },
            },
        }
        
        if chunk.Done {
            data["choices"].([]map[string]interface{})[0]["finish_reason"] = "stop"
            if chunk.Usage != nil {
                data["usage"] = chunk.Usage
            }
        }
        
        h.writeSSE(w, "data", data)
        flusher.Flush()
        
        if chunk.Done {
            // Send [DONE] marker
            fmt.Fprintf(w, "data: [DONE]\n\n")
            flusher.Flush()
            return
        }
    }
}

// writeSSE writes an SSE event
func (h *StreamHandler) writeSSE(w http.ResponseWriter, event string, data interface{}) {
    jsonData, err := json.Marshal(data)
    if err != nil {
        h.logger.Error("failed to marshal SSE data", "error", err)
        return
    }
    
    if event != "data" {
        fmt.Fprintf(w, "event: %s\n", event)
    }
    fmt.Fprintf(w, "data: %s\n\n", jsonData)
}

// writeError writes an error response
func (h *StreamHandler) writeError(w http.ResponseWriter, err error) {
    w.Header().Set("Content-Type", "application/json")
    
    appErr, ok := err.(*errors.AppError)
    if !ok {
        appErr = errors.New(errors.ErrInternal, err.Error(), err)
    }
    
    status := http.StatusInternalServerError
    switch {
    case appErr.Code >= 8100 && appErr.Code < 8200:
        status = http.StatusServiceUnavailable
    case appErr.Code >= 8200 && appErr.Code < 8300:
        status = http.StatusNotFound
    case appErr.Code >= 8400 && appErr.Code < 8500:
        status = http.StatusTooManyRequests
    }
    
    w.WriteHeader(status)
    json.NewEncoder(w).Encode(map[string]interface{}{
        "error": map[string]interface{}{
            "code":    appErr.Code,
            "message": appErr.Message,
            "stack":   appErr.Stack,  // Include stack trace
        },
    })
}
```

---

## 7. Configuration

### 7.1 Service Configuration

```yaml
# config/ai-bridge.yaml
service:
  id: "ai-bridge"
  host: "127.0.0.1"
  port: 8084
  
logging:
  level: "info"
  format: "json"
  add_source: true  # REQUIRED: log func name and file:line

providers:
  ollama:
    enabled: true
    host: "127.0.0.1"
    port: 11434
    timeout: "120s"
    keep_alive: "5m"
    max_loaded_models: 3
    
  llama:
    enabled: true
    mode: "router"  # "single", "router", or "swap"
    host: "127.0.0.1"
    port: 8085
    models_dir: "/models"
    timeout: "120s"
    
  llama_swap:
    enabled: false
    host: "127.0.0.1"
    port: 8086
    timeout: "120s"
    models:
      llama3: "llama3.gguf"
      mistral: "mistral.gguf"
      deepseek: "deepseek-r1.gguf"

routing:
  default_provider: "ollama"
  models:
    - id: "llama3"
      providers:
        - id: "ollama"
          priority: 1
        - id: "llama-router"
          priority: 2
          fallback: true
    - id: "deepseek-r1"
      providers:
        - id: "llama-router"
          priority: 1
    - id: "whisper"
      providers:
        - id: "llama-swap"
          priority: 1

rate_limit:
  enabled: true
  requests_per_minute: 60
  tokens_per_minute: 100000
  
metrics:
  enabled: true
  endpoint: "/metrics"
```

---

## 8. API Endpoints

### 8.1 OpenAPI Specification

```yaml
# api/openapi.yaml
openapi: 3.0.3
info:
  title: AI-Bridge Service API
  version: 1.0.0
  description: Unified LLM abstraction layer

servers:
  - url: http://localhost:8084

paths:
  /v1/chat/completions:
    post:
      summary: Create chat completion
      description: Streams or returns chat completion from configured LLM provider
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CompletionRequest'
      responses:
        '200':
          description: Completion response (streaming or buffered)
          content:
            text/event-stream:
              schema:
                type: string
            application/json:
              schema:
                $ref: '#/components/schemas/CompletionResponse'
        '404':
          description: Model not found
        '429':
          description: Rate limit exceeded
        '503':
          description: No provider available
          
  /v1/models:
    get:
      summary: List available models
      responses:
        '200':
          description: List of models
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/ModelInfo'
                      
  /v1/models/{model_id}/load:
    post:
      summary: Preload a model
      parameters:
        - name: model_id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Model loaded
        '404':
          description: Model not found
          
  /v1/models/{model_id}/unload:
    post:
      summary: Unload a model
      parameters:
        - name: model_id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Model unloaded
          
  /v1/embeddings:
    post:
      summary: Generate embeddings
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/EmbeddingRequest'
      responses:
        '200':
          description: Embeddings generated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/EmbeddingResponse'
                
  /health:
    get:
      summary: Health check
      responses:
        '200':
          description: Service healthy
          content:
            application/json:
              schema:
                type: object
                properties:
                  status:
                    type: string
                  providers:
                    type: object
                    additionalProperties:
                      type: boolean

components:
  schemas:
    CompletionRequest:
      type: object
      required:
        - model
        - messages
      properties:
        model:
          type: string
        messages:
          type: array
          items:
            $ref: '#/components/schemas/Message'
        temperature:
          type: number
        max_tokens:
          type: integer
        stream:
          type: boolean
          default: true
          
    Message:
      type: object
      required:
        - role
        - content
      properties:
        role:
          type: string
          enum: [system, user, assistant]
        content:
          type: string
          
    CompletionResponse:
      type: object
      properties:
        id:
          type: string
        model:
          type: string
        choices:
          type: array
          items:
            type: object
        usage:
          $ref: '#/components/schemas/Usage'
          
    Usage:
      type: object
      properties:
        prompt_tokens:
          type: integer
        completion_tokens:
          type: integer
        total_tokens:
          type: integer
          
    ModelInfo:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
        provider:
          type: string
        size_bytes:
          type: integer
        context_size:
          type: integer
          
    EmbeddingRequest:
      type: object
      required:
        - model
        - input
      properties:
        model:
          type: string
        input:
          type: array
          items:
            type: string
            
    EmbeddingResponse:
      type: object
      properties:
        model:
          type: string
        embeddings:
          type: array
          items:
            type: array
            items:
              type: number
```

---

## 9. Error Handling

### 9.1 Error Response Format

All errors include stack traces when available:

```json
{
  "error": {
    "code": 8200,
    "message": "model 'nonexistent' not found on any provider",
    "stack": [
      "provider.(*Registry).findProviderWithModel (registry.go:142)",
      "provider.(*Registry).GetProvider (registry.go:98)",
      "handler.(*StreamHandler).HandleStream (stream.go:45)",
      "http.HandlerFunc.ServeHTTP (server.go:2166)"
    ]
  }
}
```

### 9.2 Error Code Mapping

| Code | HTTP Status | Description |
|------|-------------|-------------|
| 8100 | 503 | Provider unavailable |
| 8101 | 504 | Provider timeout |
| 8102 | 502 | Provider rejected request |
| 8200 | 404 | Model not found |
| 8201 | 500 | Model load failed |
| 8202 | 503 | Model busy/loading |
| 8203 | 400 | Context exceeded |
| 8300 | 500 | Stream init failed |
| 8301 | 500 | Stream interrupted |
| 8400 | 429 | Rate limit exceeded |
| 8500 | 503 | No provider available |
| 8502 | 503 | Failover exhausted |

---

## 10. Metrics

### 10.1 Prometheus Metrics

```go
// Metrics exposed at /metrics
var (
    requestsTotal = prometheus.NewCounterVec(
        prometheus.CounterOpts{
            Name: "aibridge_requests_total",
            Help: "Total number of requests",
        },
        []string{"model", "provider", "status"},
    )
    
    requestDuration = prometheus.NewHistogramVec(
        prometheus.HistogramOpts{
            Name:    "aibridge_request_duration_seconds",
            Help:    "Request duration in seconds",
            Buckets: []float64{0.1, 0.5, 1, 2, 5, 10, 30, 60, 120},
        },
        []string{"model", "provider"},
    )
    
    tokensTotal = prometheus.NewCounterVec(
        prometheus.CounterOpts{
            Name: "aibridge_tokens_total",
            Help: "Total tokens processed",
        },
        []string{"model", "provider", "type"}, // type: prompt, completion
    )
    
    providerStatus = prometheus.NewGaugeVec(
        prometheus.GaugeOpts{
            Name: "aibridge_provider_available",
            Help: "Provider availability (1=up, 0=down)",
        },
        []string{"provider"},
    )
    
    modelLoadedGauge = prometheus.NewGaugeVec(
        prometheus.GaugeOpts{
            Name: "aibridge_model_loaded",
            Help: "Model loaded status (1=loaded, 0=unloaded)",
        },
        []string{"model", "provider"},
    )
)
```

---

## 11. References

- [LLM Server Multi-Model Research](../10-research/01b-llm-server-multi-model.md)
- [pkg/errors Specification](../13-shared-packages/02-pkg-errors.md)
- [pkg/logging Specification](../13-shared-packages/04-pkg-logging.md)
- [Gateway Service Specification](./01-gateway.md)
- [Ollama API Documentation](https://docs.ollama.com/api/introduction)
- [llama.cpp Server Documentation](https://github.com/ggml-org/llama.cpp)
