# LLM Server Management

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This document specifies the multi-server LLM management system, supporting both **Ollama** and **llama.cpp** servers with flexible configuration for runtime model switching, port allocation, and unified API abstraction.

**Cross-References:**
- [AI Integration](./01-ai-integration.md) - Model selection and execution
- [LLM Live Logging](./06-llm-live-logging.md) - Multi-server log aggregation

---

## 28.1 Architecture

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                         Multi-Server LLM Architecture                         │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│   ┌─────────────────────────────────────────────────────────────────────────┐│
│   │                           Server Registry                                ││
│   ├─────────────────────────────────────────────────────────────────────────┤│
│   │                                                                          ││
│   │  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐                 ││
│   │  │   Ollama     │   │  llama.cpp   │   │ llama-swap   │                 ││
│   │  │   Server     │   │   Router     │   │    Proxy     │                 ││
│   │  │   :11434     │   │    :8080     │   │    :8090     │                 ││
│   │  └──────┬───────┘   └──────┬───────┘   └──────┬───────┘                 ││
│   │         │                   │                  │                         ││
│   │         ▼                   ▼                  ▼                         ││
│   │  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐                 ││
│   │  │ llama3       │   │ deepseek-r1  │   │ mixtral      │                 ││
│   │  │ mistral      │   │ whisper      │   │ codellama    │                 ││
│   │  │ (hot-swap)   │   │ (multi-slot) │   │ (proxy-mgd)  │                 ││
│   │  └──────────────┘   └──────────────┘   └──────────────┘                 ││
│   │                                                                          ││
│   └─────────────────────────────────────────────────────────────────────────┘│
│                                                                               │
│   ┌─────────────────────────────────────────────────────────────────────────┐│
│   │                      Unified API Abstraction Layer                       ││
│   ├─────────────────────────────────────────────────────────────────────────┤│
│   │  - OpenAI-compatible /v1/chat/completions                               ││
│   │  - Model routing based on configuration                                  ││
│   │  - Automatic failover to backup servers                                  ││
│   │  - Load balancing across multiple instances                              ││
│   └─────────────────────────────────────────────────────────────────────────┘│
│                                                                               │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 28.2 Server Types

### 28.2.1 Ollama Server

Ollama provides native multi-model support with easy model switching via API.

**Capabilities:**
- Hot-swap models without restart
- Concurrent model loading (up to `max_loaded_models`)
- Auto-unload with configurable `keep_alive`
- OpenAI-compatible API at `/v1/chat/completions`

**API Operations:**

| Operation | Endpoint | Method |
|-----------|----------|--------|
| List loaded | `/api/ps` | GET |
| List all | `/api/tags` | GET |
| Pull model | `/api/pull` | POST |
| Generate | `/v1/chat/completions` | POST |
| Unload | `/api/generate` with `keep_alive: 0` | POST |

### 28.2.2 llama.cpp Server (Router Mode)

Router mode enables runtime model management via API.

**Capabilities:**
- Dynamic model loading/unloading
- Multi-process model serving
- Model status tracking (loaded/loading/unloaded)
- Auto-routing based on `model` field in requests

**API Operations:**

| Operation | Endpoint | Method |
|-----------|----------|--------|
| List models | `/models` | GET |
| Load model | `/models/load` | POST |
| Unload model | `/models/unload` | POST |
| Generate | `/v1/chat/completions` | POST |

**Start Command:**
```bash
./llama-server --router --models-dir /path/to/models --port 8080
```

### 28.2.3 llama.cpp Server (Single Mode)

Traditional single-model mode for dedicated instances.

**Capabilities:**
- One model per process
- Lower overhead for dedicated workloads
- Multiple instances on different ports for multi-model

**Start Command:**
```bash
./llama-server --model /path/to/model.gguf --host 127.0.0.1 --port 8080
```

### 28.2.4 llama-swap Proxy

External proxy managing multiple llama-server instances.

**Capabilities:**
- Automatic instance start/stop
- Request queuing
- TTL-based model unloading
- Model warmup for popular models

**Config:**
```json
{
  "listen": "0.0.0.0:8090",
  "models_dir": "/path/to/models",
  "models": {
    "llama3": {
      "binary": "./llama-server",
      "args": ["--model-path", "llama3.gguf", "--ctx-size", "8192"]
    }
  }
}
```

---

## 28.3 Seeding Configuration

### Server Definitions

```json
{
  "config": [
    {
      "Key": "llm.servers",
      "Value": "[{\"id\":\"primary\",\"type\":\"ollama\",\"host\":\"127.0.0.1\",\"port\":11434,\"enabled\":true,\"maxLoadedModels\":3,\"keepAlive\":\"5m\"}]",
      "Description": "JSON array of server configurations"
    },
    {
      "Key": "llm.routing.defaultServerId",
      "Value": "primary",
      "Description": "Default server for model requests"
    },
    {
      "Key": "llm.routing.rules",
      "Value": "[]",
      "Description": "JSON array of routing rules"
    }
  ]
}
```

### Server Configuration Schema

```typescript
interface LLMServerConfig {
  // Common fields
  id: string;                    // Unique identifier
  type: "ollama" | "llama" | "llama-swap";
  host: string;                  // Bind address
  port: number;                  // Primary port
  enabled: boolean;              // Server enabled
  
  // Ollama specific
  maxLoadedModels?: number;      // Concurrent models
  keepAlive?: string;            // e.g., "5m", "1h"
  pullOnDemand?: boolean;        // Auto-pull missing models
  
  // llama.cpp specific
  mode?: "router" | "single";    // Router or single-model
  modelsDir?: string;            // Model directory for router
  executablePath?: string;       // Path to llama-server
  
  // llama-swap specific
  configPath?: string;           // Path to llama-swap config
  
  // Port range (for multi-slot or multi-instance)
  portRangeStart?: number;
  portRangeEnd?: number;
  
  // Model assignments
  models?: LLMModelAssignment[];
  
  // Health check
  healthCheckInterval?: number;  // Seconds
  startupTimeout?: number;       // Seconds
}

interface LLMModelAssignment {
  modelId: string;               // Unique model identifier
  fileName?: string;             // GGUF filename (llama.cpp)
  ollamaName?: string;           // Ollama model name
  category: "thinking" | "writing" | "voice" | "coding";  // Model category
  categoryOverride?: boolean;    // If true, category was manually set (not inferred)
  contextSize?: number;          // Context window override
  gpuLayers?: number;            // GPU offload override
  priority?: number;             // Loading priority (lower = higher priority)
  warmup?: boolean;              // Pre-load on startup
}
```

### Full Seeding Example

```json
{
  "Key": "llm.servers",
  "Value": [
    {
      "id": "ollama-primary",
      "type": "ollama",
      "host": "127.0.0.1",
      "port": 11434,
      "enabled": true,
      "maxLoadedModels": 3,
      "keepAlive": "5m",
      "pullOnDemand": true,
      "models": [
        {"modelId": "llama3", "ollamaName": "llama3:8b", "warmup": true},
        {"modelId": "mistral", "ollamaName": "mistral:7b"},
        {"modelId": "codellama", "ollamaName": "codellama:13b"}
      ]
    },
    {
      "id": "llama-reasoning",
      "type": "llama",
      "mode": "router",
      "host": "127.0.0.1",
      "port": 8080,
      "enabled": true,
      "modelsDir": "/models/reasoning",
      "executablePath": "/usr/local/bin/llama-server",
      "models": [
        {"modelId": "deepseek-r1", "fileName": "deepseek-r1.gguf", "contextSize": 32768, "warmup": true}
      ]
    },
    {
      "id": "llama-voice",
      "type": "llama",
      "mode": "single",
      "host": "127.0.0.1",
      "port": 8081,
      "enabled": true,
      "executablePath": "/usr/local/bin/llama-server",
      "models": [
        {"modelId": "whisper", "fileName": "/models/whisper-large-v3.gguf"}
      ]
    }
  ]
}
```

---

## 28.4 Server Registry Service

### Registry Implementation

```go
// internal/services/server_registry.go
package services

import (
    "context"
    "encoding/json"
    "sync"
    "time"
)

type ServerStatus string

const (
    ServerStatusOffline  ServerStatus = "offline"
    ServerStatusStarting ServerStatus = "starting"
    ServerStatusOnline   ServerStatus = "online"
    ServerStatusError    ServerStatus = "error"
)

type ServerInstance struct {
    Config      LLMServerConfig `json:"config"`
    Status      ServerStatus    `json:"status"`
    LastHealthy time.Time       `json:"lastHealthy"`
    Error       *string         `json:"error,omitempty"`
    LoadedModels []string       `json:"loadedModels"`
    Metrics     *ServerMetrics  `json:"metrics,omitempty"`
}

type ServerMetrics struct {
    RequestCount    int64   `json:"requestCount"`
    AvgLatencyMs    float64 `json:"avgLatencyMs"`
    ActiveRequests  int     `json:"activeRequests"`
    MemoryUsedMB    int64   `json:"memoryUsedMb"`
}

type ServerRegistry struct {
    db             *sql.DB
    configService  *ConfigService
    logManager     *LogStreamManager
    mutex          sync.RWMutex
    servers        map[string]*ServerInstance
    healthTicker   *time.Ticker
    stopChan       chan struct{}
}

func NewServerRegistry(
    db *sql.DB,
    configService *ConfigService,
    logManager *LogStreamManager,
) *ServerRegistry {
    return &ServerRegistry{
        db:            db,
        configService: configService,
        logManager:    logManager,
        servers:       make(map[string]*ServerInstance),
        stopChan:      make(chan struct{}),
    }
}

// Initialize loads server configs and starts health monitoring
func (r *ServerRegistry) Initialize(ctx context.Context) error {
    // Load server configurations
    configs, err := r.loadServerConfigs(ctx)
    if err != nil {
        return err
    }
    
    r.mutex.Lock()
    for _, cfg := range configs {
        r.servers[cfg.Id] = &ServerInstance{
            Config:       cfg,
            Status:       ServerStatusOffline,
            LoadedModels: []string{},
        }
    }
    r.mutex.Unlock()
    
    // Start health check loop
    r.healthTicker = time.NewTicker(30 * time.Second)
    go r.healthCheckLoop()
    
    // Start enabled servers
    for _, cfg := range configs {
        if cfg.Enabled {
            go r.startServer(ctx, cfg.Id)
        }
    }
    
    return nil
}

func (r *ServerRegistry) loadServerConfigs(ctx context.Context) ([]LLMServerConfig, error) {
    value, err := r.configService.GetConfig(ctx, "llm.servers")
    if err != nil {
        return nil, err
    }
    
    var configs []LLMServerConfig
    if err := json.Unmarshal([]byte(value), &configs); err != nil {
        return nil, err
    }
    
    return configs, nil
}

// GetServer returns a server instance by ID
func (r *ServerRegistry) GetServer(serverId string) (*ServerInstance, bool) {
    r.mutex.RLock()
    defer r.mutex.RUnlock()
    
    server, exists := r.servers[serverId]
    return server, exists
}

// GetOnlineServers returns all servers with online status
func (r *ServerRegistry) GetOnlineServers() []*ServerInstance {
    r.mutex.RLock()
    defer r.mutex.RUnlock()
    
    var online []*ServerInstance
    for _, s := range r.servers {
        if s.Status == ServerStatusOnline {
            online = append(online, s)
        }
    }
    return online
}

// GetServerForModel finds the best server for a given model
func (r *ServerRegistry) GetServerForModel(modelId string) (*ServerInstance, error) {
    r.mutex.RLock()
    defer r.mutex.RUnlock()
    
    // First: check servers with model already loaded
    for _, server := range r.servers {
        if server.Status != ServerStatusOnline {
            continue
        }
        for _, loaded := range server.LoadedModels {
            if loaded == modelId {
                return server, nil
            }
        }
    }
    
    // Second: check servers that can load the model
    for _, server := range r.servers {
        if server.Status != ServerStatusOnline {
            continue
        }
        for _, model := range server.Config.Models {
            if model.ModelId == modelId {
                return server, nil
            }
        }
    }
    
    return nil, fmt.Errorf("no server available for model: %s", modelId)
}

func (r *ServerRegistry) healthCheckLoop() {
    for {
        select {
        case <-r.stopChan:
            return
        case <-r.healthTicker.C:
            r.checkAllServers()
        }
    }
}

func (r *ServerRegistry) checkAllServers() {
    r.mutex.RLock()
    serverIds := make([]string, 0, len(r.servers))
    for id := range r.servers {
        serverIds = append(serverIds, id)
    }
    r.mutex.RUnlock()
    
    for _, id := range serverIds {
        go r.checkServerHealth(id)
    }
}

func (r *ServerRegistry) checkServerHealth(serverId string) {
    r.mutex.RLock()
    server, exists := r.servers[serverId]
    r.mutex.RUnlock()
    
    if !exists || !server.Config.Enabled {
        return
    }
    
    ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
    defer cancel()
    
    healthy, loadedModels, err := r.pingServer(ctx, server.Config)
    
    r.mutex.Lock()
    defer r.mutex.Unlock()
    
    if healthy {
        server.Status = ServerStatusOnline
        server.LastHealthy = time.Now()
        server.LoadedModels = loadedModels
        server.Error = nil
    } else {
        server.Status = ServerStatusError
        errStr := "health check failed"
        if err != nil {
            errStr = err.Error()
        }
        server.Error = &errStr
    }
}

func (r *ServerRegistry) pingServer(ctx context.Context, cfg LLMServerConfig) (bool, []string, error) {
    switch cfg.Type {
    case "ollama":
        return r.pingOllama(ctx, cfg)
    case "llama":
        return r.pingLlama(ctx, cfg)
    case "llama-swap":
        return r.pingLlamaSwap(ctx, cfg)
    default:
        return false, nil, fmt.Errorf("unknown server type: %s", cfg.Type)
    }
}

func (r *ServerRegistry) pingOllama(ctx context.Context, cfg LLMServerConfig) (bool, []string, error) {
    url := fmt.Sprintf("http://%s:%d/api/ps", cfg.Host, cfg.Port)
    
    resp, err := http.Get(url)
    if err != nil {
        return false, nil, err
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != http.StatusOK {
        return false, nil, fmt.Errorf("ollama returned status: %d", resp.StatusCode)
    }
    
    var result struct {
        Models []struct {
            Name string `json:"name"`
        } `json:"models"`
    }
    
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return true, nil, nil // Server up but can't parse
    }
    
    loadedModels := make([]string, len(result.Models))
    for i, m := range result.Models {
        loadedModels[i] = m.Name
    }
    
    return true, loadedModels, nil
}

func (r *ServerRegistry) pingLlama(ctx context.Context, cfg LLMServerConfig) (bool, []string, error) {
    url := fmt.Sprintf("http://%s:%d/models", cfg.Host, cfg.Port)
    
    resp, err := http.Get(url)
    if err != nil {
        return false, nil, err
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != http.StatusOK {
        return false, nil, fmt.Errorf("llama-server returned status: %d", resp.StatusCode)
    }
    
    var result struct {
        Data []struct {
            Id     string `json:"id"`
            Status string `json:"status"`
        } `json:"data"`
    }
    
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return true, nil, nil
    }
    
    var loadedModels []string
    for _, m := range result.Data {
        if m.Status == "loaded" {
            loadedModels = append(loadedModels, m.Id)
        }
    }
    
    return true, loadedModels, nil
}

func (r *ServerRegistry) pingLlamaSwap(ctx context.Context, cfg LLMServerConfig) (bool, []string, error) {
    url := fmt.Sprintf("http://%s:%d/v1/models", cfg.Host, cfg.Port)
    
    resp, err := http.Get(url)
    if err != nil {
        return false, nil, err
    }
    defer resp.Body.Close()
    
    return resp.StatusCode == http.StatusOK, nil, nil
}

// Shutdown stops all managed servers
func (r *ServerRegistry) Shutdown() {
    close(r.stopChan)
    if r.healthTicker != nil {
        r.healthTicker.Stop()
    }
}
```

---

## 28.5 Model Router Service

### Unified API Abstraction

```go
// internal/services/model_router.go
package services

import (
    "context"
    "fmt"
    "io"
    "net/http"
)

type ModelRouter struct {
    registry      *ServerRegistry
    configService *ConfigService
    logManager    *LogStreamManager
}

func NewModelRouter(
    registry *ServerRegistry,
    configService *ConfigService,
    logManager *LogStreamManager,
) *ModelRouter {
    return &ModelRouter{
        registry:      registry,
        configService: configService,
        logManager:    logManager,
    }
}

type ChatCompletionRequest struct {
    Model       string          `json:"model"`
    Messages    []ChatMessage   `json:"messages"`
    Stream      bool            `json:"stream"`
    MaxTokens   *int            `json:"max_tokens,omitempty"`
    Temperature *float64        `json:"temperature,omitempty"`
}

type ChatMessage struct {
    Role    string `json:"role"`
    Content string `json:"content"`
}

// Route sends a request to the appropriate server
func (r *ModelRouter) Route(ctx context.Context, req ChatCompletionRequest) (*http.Response, error) {
    // Resolve server for model
    server, err := r.registry.GetServerForModel(req.Model)
    if err != nil {
        return nil, err
    }
    
    // Log routing decision
    r.logManager.Log(LLMLogEntry{
        Level:   LLMLogLevelInfo,
        Source:  LLMLogSourceRequest,
        Message: fmt.Sprintf("Routing model %s to server %s", req.Model, server.Config.Id),
        Details: map[string]interface{}{
            "model":    req.Model,
            "serverId": server.Config.Id,
            "type":     server.Config.Type,
        },
    })
    
    // Build target URL
    targetURL := r.buildTargetURL(server.Config, req.Model)
    
    // Forward request
    return r.forwardRequest(ctx, targetURL, req)
}

func (r *ModelRouter) buildTargetURL(cfg LLMServerConfig, modelId string) string {
    switch cfg.Type {
    case "ollama":
        return fmt.Sprintf("http://%s:%d/v1/chat/completions", cfg.Host, cfg.Port)
    case "llama", "llama-swap":
        return fmt.Sprintf("http://%s:%d/v1/chat/completions", cfg.Host, cfg.Port)
    default:
        return ""
    }
}

func (r *ModelRouter) forwardRequest(ctx context.Context, url string, req ChatCompletionRequest) (*http.Response, error) {
    body, err := json.Marshal(req)
    if err != nil {
        return nil, err
    }
    
    httpReq, err := http.NewRequestWithContext(ctx, "POST", url, bytes.NewReader(body))
    if err != nil {
        return nil, err
    }
    
    httpReq.Header.Set("Content-Type", "application/json")
    
    return http.DefaultClient.Do(httpReq)
}

// EnsureModelLoaded loads a model on the appropriate server if not already loaded
func (r *ModelRouter) EnsureModelLoaded(ctx context.Context, modelId string) error {
    server, err := r.registry.GetServerForModel(modelId)
    if err != nil {
        return err
    }
    
    // Check if already loaded
    for _, loaded := range server.LoadedModels {
        if loaded == modelId {
            return nil // Already loaded
        }
    }
    
    // Load based on server type
    switch server.Config.Type {
    case "ollama":
        return r.loadOllamaModel(ctx, server.Config, modelId)
    case "llama":
        return r.loadLlamaModel(ctx, server.Config, modelId)
    default:
        return nil // llama-swap auto-loads
    }
}

func (r *ModelRouter) loadOllamaModel(ctx context.Context, cfg LLMServerConfig, modelId string) error {
    // Find the Ollama model name from config
    var ollamaName string
    for _, m := range cfg.Models {
        if m.ModelId == modelId {
            ollamaName = m.OllamaName
            break
        }
    }
    if ollamaName == "" {
        ollamaName = modelId
    }
    
    // Send a minimal request to trigger model load
    url := fmt.Sprintf("http://%s:%d/api/generate", cfg.Host, cfg.Port)
    body := fmt.Sprintf(`{"model": "%s", "prompt": "", "keep_alive": "5m"}`, ollamaName)
    
    resp, err := http.Post(url, "application/json", strings.NewReader(body))
    if err != nil {
        return err
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != http.StatusOK {
        return fmt.Errorf("failed to load model: %s", resp.Status)
    }
    
    return nil
}

func (r *ModelRouter) loadLlamaModel(ctx context.Context, cfg LLMServerConfig, modelId string) error {
    // Find the model file from config
    var modelPath string
    for _, m := range cfg.Models {
        if m.ModelId == modelId {
            modelPath = m.FileName
            break
        }
    }
    
    if cfg.Mode == "router" && modelPath != "" {
        url := fmt.Sprintf("http://%s:%d/models/load", cfg.Host, cfg.Port)
        body := fmt.Sprintf(`{"model": "%s"}`, modelPath)
        
        resp, err := http.Post(url, "application/json", strings.NewReader(body))
        if err != nil {
            return err
        }
        defer resp.Body.Close()
    }
    
    return nil
}

// UnloadModel unloads a model from memory
func (r *ModelRouter) UnloadModel(ctx context.Context, serverId string, modelId string) error {
    server, exists := r.registry.GetServer(serverId)
    if !exists {
        return fmt.Errorf("server not found: %s", serverId)
    }
    
    switch server.Config.Type {
    case "ollama":
        url := fmt.Sprintf("http://%s:%d/api/generate", server.Config.Host, server.Config.Port)
        body := fmt.Sprintf(`{"model": "%s", "keep_alive": 0}`, modelId)
        resp, err := http.Post(url, "application/json", strings.NewReader(body))
        if err != nil {
            return err
        }
        resp.Body.Close()
        
    case "llama":
        if server.Config.Mode == "router" {
            url := fmt.Sprintf("http://%s:%d/models/unload", server.Config.Host, server.Config.Port)
            body := fmt.Sprintf(`{"model": "%s"}`, modelId)
            resp, err := http.Post(url, "application/json", strings.NewReader(body))
            if err != nil {
                return err
            }
            resp.Body.Close()
        }
    }
    
    return nil
}
```

---

## 28.6 Server Lifecycle Management

### Start/Stop Operations

```go
// internal/services/server_lifecycle.go
package services

import (
    "context"
    "fmt"
    "os"
    "os/exec"
)

type ServerLifecycle struct {
    registry   *ServerRegistry
    logManager *LogStreamManager
    processes  map[string]*exec.Cmd
}

func NewServerLifecycle(registry *ServerRegistry, logManager *LogStreamManager) *ServerLifecycle {
    return &ServerLifecycle{
        registry:   registry,
        logManager: logManager,
        processes:  make(map[string]*exec.Cmd),
    }
}

// StartServer starts a managed server process
func (s *ServerLifecycle) StartServer(ctx context.Context, serverId string) error {
    server, exists := s.registry.GetServer(serverId)
    if !exists {
        return fmt.Errorf("server not found: %s", serverId)
    }
    
    cfg := server.Config
    
    // Only start llama-type servers (Ollama is external)
    if cfg.Type == "ollama" {
        // Ollama is managed externally; just verify it's running
        return nil
    }
    
    var cmd *exec.Cmd
    
    switch cfg.Type {
    case "llama":
        cmd = s.buildLlamaCommand(cfg)
    case "llama-swap":
        cmd = s.buildLlamaSwapCommand(cfg)
    default:
        return fmt.Errorf("unsupported server type: %s", cfg.Type)
    }
    
    // Log shell command
    completeFn := s.logManager.LogShellCommand(ctx, cmd.String(), nil, nil)
    
    // Capture output
    s.logManager.CaptureProcessOutput(ctx, cmd, serverId, cfg.Type, cfg.Port)
    
    // Start process
    if err := cmd.Start(); err != nil {
        completeFn(1, err)
        return err
    }
    
    s.processes[serverId] = cmd
    
    // Wait for startup in background
    go func() {
        err := cmd.Wait()
        exitCode := 0
        if cmd.ProcessState != nil {
            exitCode = cmd.ProcessState.ExitCode()
        }
        completeFn(exitCode, err)
        delete(s.processes, serverId)
    }()
    
    return nil
}

func (s *ServerLifecycle) buildLlamaCommand(cfg LLMServerConfig) *exec.Cmd {
    execPath := cfg.ExecutablePath
    if execPath == "" {
        execPath = "/usr/local/bin/llama-server"
    }
    
    args := []string{
        "--host", cfg.Host,
        "--port", fmt.Sprintf("%d", cfg.Port),
    }
    
    if cfg.Mode == "router" {
        args = append(args, "--router")
        if cfg.ModelsDir != "" {
            args = append(args, "--models-dir", cfg.ModelsDir)
        }
    } else if len(cfg.Models) > 0 {
        // Single mode - use first model
        model := cfg.Models[0]
        args = append(args, "--model", model.FileName)
        if model.ContextSize > 0 {
            args = append(args, "--ctx-size", fmt.Sprintf("%d", model.ContextSize))
        }
        if model.GpuLayers > 0 {
            args = append(args, "--n-gpu-layers", fmt.Sprintf("%d", model.GpuLayers))
        }
    }
    
    return exec.Command(execPath, args...)
}

func (s *ServerLifecycle) buildLlamaSwapCommand(cfg LLMServerConfig) *exec.Cmd {
    execPath := cfg.ExecutablePath
    if execPath == "" {
        execPath = "/usr/local/bin/llama-swap"
    }
    
    args := []string{
        "--config", cfg.ConfigPath,
    }
    
    return exec.Command(execPath, args...)
}

// StopServer stops a managed server process
func (s *ServerLifecycle) StopServer(ctx context.Context, serverId string) error {
    cmd, exists := s.processes[serverId]
    if !exists {
        return nil // Not running or externally managed
    }
    
    if cmd.Process != nil {
        // Send SIGTERM
        if err := cmd.Process.Signal(os.Interrupt); err != nil {
            // Force kill if graceful shutdown fails
            cmd.Process.Kill()
        }
    }
    
    delete(s.processes, serverId)
    return nil
}

// RestartServer stops and starts a server
func (s *ServerLifecycle) RestartServer(ctx context.Context, serverId string) error {
    if err := s.StopServer(ctx, serverId); err != nil {
        return err
    }
    
    // Wait for process to fully stop
    time.Sleep(2 * time.Second)
    
    return s.StartServer(ctx, serverId)
}
```

---

## 28.7 API Endpoints

### Server Management Endpoints

```go
// internal/handlers/server_handlers.go
package handlers

// GET /api/llm/servers - List all servers
func (h *LLMHandler) ListServers(w http.ResponseWriter, r *http.Request) {
    servers := h.registry.GetAllServers()
    writeJSON(w, servers)
}

// GET /api/llm/servers/:id - Get server details
func (h *LLMHandler) GetServer(w http.ResponseWriter, r *http.Request) {
    serverId := chi.URLParam(r, "id")
    server, exists := h.registry.GetServer(serverId)
    if !exists {
        writeError(w, http.StatusNotFound, "Server not found")
        return
    }
    writeJSON(w, server)
}

// POST /api/llm/servers/:id/start - Start server
func (h *LLMHandler) StartServer(w http.ResponseWriter, r *http.Request) {
    serverId := chi.URLParam(r, "id")
    if err := h.lifecycle.StartServer(r.Context(), serverId); err != nil {
        writeError(w, http.StatusInternalServerError, err.Error())
        return
    }
    writeJSON(w, map[string]string{"status": "starting"})
}

// POST /api/llm/servers/:id/stop - Stop server
func (h *LLMHandler) StopServer(w http.ResponseWriter, r *http.Request) {
    serverId := chi.URLParam(r, "id")
    if err := h.lifecycle.StopServer(r.Context(), serverId); err != nil {
        writeError(w, http.StatusInternalServerError, err.Error())
        return
    }
    writeJSON(w, map[string]string{"status": "stopped"})
}

// GET /api/llm/servers/:id/models - List models on server
func (h *LLMHandler) ListServerModels(w http.ResponseWriter, r *http.Request) {
    serverId := chi.URLParam(r, "id")
    server, exists := h.registry.GetServer(serverId)
    if !exists {
        writeError(w, http.StatusNotFound, "Server not found")
        return
    }
    writeJSON(w, map[string]interface{}{
        "configured": server.Config.Models,
        "loaded":     server.LoadedModels,
    })
}

// POST /api/llm/servers/:id/models/:modelId/load - Load model
func (h *LLMHandler) LoadModel(w http.ResponseWriter, r *http.Request) {
    modelId := chi.URLParam(r, "modelId")
    if err := h.router.EnsureModelLoaded(r.Context(), modelId); err != nil {
        writeError(w, http.StatusInternalServerError, err.Error())
        return
    }
    writeJSON(w, map[string]string{"status": "loaded"})
}

// POST /api/llm/servers/:id/models/:modelId/unload - Unload model
func (h *LLMHandler) UnloadModel(w http.ResponseWriter, r *http.Request) {
    serverId := chi.URLParam(r, "id")
    modelId := chi.URLParam(r, "modelId")
    if err := h.router.UnloadModel(r.Context(), serverId, modelId); err != nil {
        writeError(w, http.StatusInternalServerError, err.Error())
        return
    }
    writeJSON(w, map[string]string{"status": "unloaded"})
}

// POST /api/llm/chat/completions - Unified chat endpoint with routing
func (h *LLMHandler) ChatCompletions(w http.ResponseWriter, r *http.Request) {
    var req ChatCompletionRequest
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        writeError(w, http.StatusBadRequest, "Invalid request body")
        return
    }
    
    // Route to appropriate server
    resp, err := h.router.Route(r.Context(), req)
    if err != nil {
        writeError(w, http.StatusServiceUnavailable, err.Error())
        return
    }
    defer resp.Body.Close()
    
    // Forward response (including streaming)
    w.Header().Set("Content-Type", resp.Header.Get("Content-Type"))
    w.WriteHeader(resp.StatusCode)
    io.Copy(w, resp.Body)
}
```

---

## 28.8 WebSocket Events

### Server Status Broadcasting

```go
// WebSocket message types for server management
type ServerStatusMessage struct {
    Type     string         `json:"type"`     // "llm:server_status"
    ServerId string         `json:"serverId"`
    Status   ServerStatus   `json:"status"`
    Models   []string       `json:"loadedModels"`
    Error    *string        `json:"error,omitempty"`
}

type ModelLoadedMessage struct {
    Type     string `json:"type"`     // "llm:model_loaded"
    ServerId string `json:"serverId"`
    ModelId  string `json:"modelId"`
}

type ModelUnloadedMessage struct {
    Type     string `json:"type"`     // "llm:model_unloaded"
    ServerId string `json:"serverId"`
    ModelId  string `json:"modelId"`
}
```

---

## 28.9 Configuration Validation

### Validation Rules

```go
// ValidateServerConfig validates server configuration
func ValidateServerConfig(cfg LLMServerConfig) []string {
    var errors []string
    
    if cfg.Id == "" {
        errors = append(errors, "server id is required")
    }
    
    if cfg.Type == "" {
        errors = append(errors, "server type is required")
    } else if cfg.Type != "ollama" && cfg.Type != "llama" && cfg.Type != "llama-swap" {
        errors = append(errors, "invalid server type: must be ollama, llama, or llama-swap")
    }
    
    if cfg.Port < 1 || cfg.Port > 65535 {
        errors = append(errors, "port must be between 1 and 65535")
    }
    
    if cfg.Type == "llama" && cfg.Mode == "" {
        errors = append(errors, "llama server requires mode: router or single")
    }
    
    if cfg.Type == "llama" && cfg.Mode == "router" && cfg.ModelsDir == "" {
        errors = append(errors, "router mode requires modelsDir")
    }
    
    if cfg.Type == "llama-swap" && cfg.ConfigPath == "" {
        errors = append(errors, "llama-swap requires configPath")
    }
    
    // Check for port conflicts
    // ... additional validation
    
    return errors
}
```

---

## 28.10 llama-swap Configuration Generator

The llama-swap proxy requires a `config.yaml` file. This generator creates the config from the seeding configuration.

### Config Schema

```yaml
# llama-swap config.yaml structure
listen: ":8090"
healthCheckTimeout: 30
logFormat: "text"  # or "json"
logLevel: "info"

models:
  model-alias:
    cmd: /path/to/llama-server
    args:
      - "--model"
      - "/path/to/model.gguf"
      - "--host"
      - "127.0.0.1"
      - "--port"
      - "{{.Port}}"
      - "--ctx-size"
      - "8192"
    env:
      - "CUDA_VISIBLE_DEVICES=0"
    checkEndpoint: "/health"
    proxy: "http://127.0.0.1:{{.Port}}"
    ttl: 300
    unlockLoad: true
```

### Generator Service

```go
// internal/services/llama_swap_config_generator.go
package services

import (
    "context"
    "fmt"
    "os"
    "path/filepath"
    
    "gopkg.in/yaml.v3"
)

type LlamaSwapConfigGenerator struct {
    configService  *ConfigService
    serverRegistry *ServerRegistry
}

func NewLlamaSwapConfigGenerator(
    configService *ConfigService,
    serverRegistry *ServerRegistry,
) *LlamaSwapConfigGenerator {
    return &LlamaSwapConfigGenerator{
        configService:  configService,
        serverRegistry: serverRegistry,
    }
}

// LlamaSwapConfig represents the llama-swap config.yaml structure
type LlamaSwapConfig struct {
    Listen             string                      `yaml:"listen"`
    HealthCheckTimeout int                         `yaml:"healthCheckTimeout"`
    LogFormat          string                      `yaml:"logFormat"`
    LogLevel           string                      `yaml:"logLevel"`
    Models             map[string]LlamaSwapModel   `yaml:"models"`
}

type LlamaSwapModel struct {
    Cmd           string            `yaml:"cmd"`
    Args          []string          `yaml:"args"`
    Env           []string          `yaml:"env,omitempty"`
    CheckEndpoint string            `yaml:"checkEndpoint"`
    Proxy         string            `yaml:"proxy"`
    TTL           int               `yaml:"ttl"`              // Seconds before unload
    UnlockLoad    bool              `yaml:"unlockLoad"`       // Allow loading while another model loads
    Aliases       []string          `yaml:"aliases,omitempty"`
}

// GenerateFromServerConfig creates llama-swap config from LLMServerConfig
func (g *LlamaSwapConfigGenerator) GenerateFromServerConfig(
    ctx context.Context,
    serverConfig LLMServerConfig,
) (*LlamaSwapConfig, error) {
    if serverConfig.Type != "llama-swap" {
        return nil, fmt.Errorf("server type must be llama-swap, got: %s", serverConfig.Type)
    }

    // Get defaults from seeding config
    defaultContextSize, _ := g.configService.GetConfigAsInt(ctx, "llm.defaults.contextSize")
    if defaultContextSize == 0 {
        defaultContextSize = 8192
    }
    
    defaultGpuLayers, _ := g.configService.GetConfigAsInt(ctx, "llm.defaults.gpuLayers")
    if defaultGpuLayers == 0 {
        defaultGpuLayers = 35
    }
    
    defaultTTL, _ := g.configService.GetConfigAsInt(ctx, "llm.llamaSwap.defaultTTL")
    if defaultTTL == 0 {
        defaultTTL = 300 // 5 minutes
    }
    
    llamaServerPath, _ := g.configService.GetConfig(ctx, "llama.server.executablePath")
    if llamaServerPath == "" {
        llamaServerPath = "/usr/local/bin/llama-server"
    }
    
    logLevel, _ := g.configService.GetConfig(ctx, "ai.logging.level")
    if logLevel == "" {
        logLevel = "info"
    }

    config := &LlamaSwapConfig{
        Listen:             fmt.Sprintf("%s:%d", serverConfig.Host, serverConfig.Port),
        HealthCheckTimeout: 30,
        LogFormat:          "text",
        LogLevel:           logLevel,
        Models:             make(map[string]LlamaSwapModel),
    }

    // Generate model entries
    basePort := serverConfig.Port + 1
    for i, model := range serverConfig.Models {
        modelPort := basePort + i
        
        // Build args
        contextSize := defaultContextSize
        if model.ContextSize > 0 {
            contextSize = model.ContextSize
        }
        
        gpuLayers := defaultGpuLayers
        if model.GpuLayers > 0 {
            gpuLayers = model.GpuLayers
        }
        
        modelPath := model.FileName
        if !filepath.IsAbs(modelPath) && serverConfig.ModelsDir != "" {
            modelPath = filepath.Join(serverConfig.ModelsDir, model.FileName)
        }
        
        args := []string{
            "--model", modelPath,
            "--host", "127.0.0.1",
            "--port", fmt.Sprintf("%d", modelPort),
            "--ctx-size", fmt.Sprintf("%d", contextSize),
            "--n-gpu-layers", fmt.Sprintf("%d", gpuLayers),
        }
        
        // Add optional args from model config
        if model.ExtraArgs != nil {
            args = append(args, model.ExtraArgs...)
        }

        ttl := defaultTTL
        if model.TTL > 0 {
            ttl = model.TTL
        }

        swapModel := LlamaSwapModel{
            Cmd:           llamaServerPath,
            Args:          args,
            CheckEndpoint: "/health",
            Proxy:         fmt.Sprintf("http://127.0.0.1:%d", modelPort),
            TTL:           ttl,
            UnlockLoad:    true,
        }
        
        // Add aliases if specified
        if model.Aliases != nil {
            swapModel.Aliases = model.Aliases
        }
        
        // Add environment variables if specified
        if model.Env != nil {
            swapModel.Env = model.Env
        }
        
        config.Models[model.ModelId] = swapModel
    }

    return config, nil
}

// GenerateConfigFile creates and writes the config.yaml file
func (g *LlamaSwapConfigGenerator) GenerateConfigFile(
    ctx context.Context,
    serverId string,
    outputPath string,
) error {
    server, exists := g.serverRegistry.GetServer(serverId)
    if !exists {
        return fmt.Errorf("server not found: %s", serverId)
    }

    config, err := g.GenerateFromServerConfig(ctx, server.Config)
    if err != nil {
        return err
    }

    // Marshal to YAML
    data, err := yaml.Marshal(config)
    if err != nil {
        return fmt.Errorf("failed to marshal config: %w", err)
    }

    // Ensure directory exists
    dir := filepath.Dir(outputPath)
    if err := os.MkdirAll(dir, 0755); err != nil {
        return fmt.Errorf("failed to create directory: %w", err)
    }

    // Write file
    if err := os.WriteFile(outputPath, data, 0644); err != nil {
        return fmt.Errorf("failed to write config file: %w", err)
    }

    return nil
}

// PreviewConfig returns the YAML string without writing to file
func (g *LlamaSwapConfigGenerator) PreviewConfig(
    ctx context.Context,
    serverId string,
) (string, error) {
    server, exists := g.serverRegistry.GetServer(serverId)
    if !exists {
        return "", fmt.Errorf("server not found: %s", serverId)
    }

    config, err := g.GenerateFromServerConfig(ctx, server.Config)
    if err != nil {
        return "", err
    }

    data, err := yaml.Marshal(config)
    if err != nil {
        return "", err
    }

    return string(data), nil
}

// ValidateGeneratedConfig checks if the generated config is valid
func (g *LlamaSwapConfigGenerator) ValidateGeneratedConfig(config *LlamaSwapConfig) []string {
    var errors []string
    
    if config.Listen == "" {
        errors = append(errors, "listen address is required")
    }
    
    if len(config.Models) == 0 {
        errors = append(errors, "at least one model is required")
    }
    
    seenPorts := make(map[string]string)
    for modelId, model := range config.Models {
        if model.Cmd == "" {
            errors = append(errors, fmt.Sprintf("model %s: cmd is required", modelId))
        }
        
        if model.Proxy == "" {
            errors = append(errors, fmt.Sprintf("model %s: proxy is required", modelId))
        } else {
            // Check for port conflicts
            if existing, exists := seenPorts[model.Proxy]; exists {
                errors = append(errors, fmt.Sprintf("model %s: proxy port conflicts with %s", modelId, existing))
            }
            seenPorts[model.Proxy] = modelId
        }
        
        // Validate model path exists
        if len(model.Args) >= 2 {
            modelPath := model.Args[1]
            if _, err := os.Stat(modelPath); os.IsNotExist(err) {
                errors = append(errors, fmt.Sprintf("model %s: model file not found: %s", modelId, modelPath))
            }
        }
    }
    
    return errors
}
```

### Extended Model Assignment Schema

```typescript
interface LLMModelAssignment {
  // ... existing fields ...
  
  // llama-swap specific
  ttl?: number;           // Override default TTL (seconds)
  aliases?: string[];     // Alternative model names
  env?: string[];         // Environment variables (e.g., "CUDA_VISIBLE_DEVICES=0")
  extraArgs?: string[];   // Additional llama-server args
}
```

### Additional Seeding Config Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `llm.llamaSwap.defaultTTL` | integer | `300` | Default seconds before model unload |
| `llm.llamaSwap.logFormat` | enum | `text` | Log format: `text` or `json` |
| `llm.llamaSwap.healthCheckTimeout` | integer | `30` | Health check timeout in seconds |
| `llm.llamaSwap.unlockLoad` | boolean | `true` | Allow concurrent model loading |

### API Endpoints

```go
// internal/handlers/llama_swap_handlers.go

// GET /api/llm/servers/:id/llama-swap-config - Preview generated config
func (h *LLMHandler) PreviewLlamaSwapConfig(w http.ResponseWriter, r *http.Request) {
    serverId := chi.URLParam(r, "id")
    
    config, err := h.configGenerator.PreviewConfig(r.Context(), serverId)
    if err != nil {
        writeError(w, http.StatusBadRequest, err.Error())
        return
    }
    
    w.Header().Set("Content-Type", "text/yaml")
    w.Write([]byte(config))
}

// POST /api/llm/servers/:id/llama-swap-config/generate - Generate and save config
func (h *LLMHandler) GenerateLlamaSwapConfig(w http.ResponseWriter, r *http.Request) {
    serverId := chi.URLParam(r, "id")
    
    var req struct {
        OutputPath string `json:"outputPath"`
    }
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        writeError(w, http.StatusBadRequest, "Invalid request body")
        return
    }
    
    // Use default path if not specified
    outputPath := req.OutputPath
    if outputPath == "" {
        outputPath = fmt.Sprintf("./data/llama-swap/%s/config.yaml", serverId)
    }
    
    if err := h.configGenerator.GenerateConfigFile(r.Context(), serverId, outputPath); err != nil {
        writeError(w, http.StatusInternalServerError, err.Error())
        return
    }
    
    writeJSON(w, map[string]string{
        "status": "generated",
        "path":   outputPath,
    })
}

// POST /api/llm/servers/:id/llama-swap-config/validate - Validate config
func (h *LLMHandler) ValidateLlamaSwapConfig(w http.ResponseWriter, r *http.Request) {
    serverId := chi.URLParam(r, "id")
    
    server, exists := h.registry.GetServer(serverId)
    if !exists {
        writeError(w, http.StatusNotFound, "Server not found")
        return
    }
    
    config, err := h.configGenerator.GenerateFromServerConfig(r.Context(), server.Config)
    if err != nil {
        writeError(w, http.StatusBadRequest, err.Error())
        return
    }
    
    errors := h.configGenerator.ValidateGeneratedConfig(config)
    
    writeJSON(w, map[string]interface{}{
        "valid":  len(errors) == 0,
        "errors": errors,
    })
}
```

### Example Generated Config

From seeding config:
```json
{
  "id": "swap-multi",
  "type": "llama-swap",
  "host": "0.0.0.0",
  "port": 8090,
  "modelsDir": "/models",
  "models": [
    {"modelId": "llama3", "fileName": "llama3-8b.gguf", "contextSize": 8192},
    {"modelId": "codellama", "fileName": "codellama-13b.gguf", "contextSize": 16384, "gpuLayers": 40},
    {"modelId": "mistral", "fileName": "mistral-7b.gguf", "aliases": ["default"]}
  ]
}
```

Generated `config.yaml`:
```yaml
listen: "0.0.0.0:8090"
healthCheckTimeout: 30
logFormat: text
logLevel: info
models:
  llama3:
    cmd: /usr/local/bin/llama-server
    args:
      - "--model"
      - "/models/llama3-8b.gguf"
      - "--host"
      - "127.0.0.1"
      - "--port"
      - "8091"
      - "--ctx-size"
      - "8192"
      - "--n-gpu-layers"
      - "35"
    checkEndpoint: /health
    proxy: http://127.0.0.1:8091
    ttl: 300
    unlockLoad: true
  codellama:
    cmd: /usr/local/bin/llama-server
    args:
      - "--model"
      - "/models/codellama-13b.gguf"
      - "--host"
      - "127.0.0.1"
      - "--port"
      - "8092"
      - "--ctx-size"
      - "16384"
      - "--n-gpu-layers"
      - "40"
    checkEndpoint: /health
    proxy: http://127.0.0.1:8092
    ttl: 300
    unlockLoad: true
  mistral:
    cmd: /usr/local/bin/llama-server
    args:
      - "--model"
      - "/models/mistral-7b.gguf"
      - "--host"
      - "127.0.0.1"
      - "--port"
      - "8093"
      - "--ctx-size"
      - "8192"
      - "--n-gpu-layers"
      - "35"
    checkEndpoint: /health
    proxy: http://127.0.0.1:8093
    ttl: 300
    unlockLoad: true
    aliases:
      - default
```

---

## 28.11 Acceptance Criteria

### Server Lifecycle (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SL-001 | Server registry initializes from seeding config on startup | Critical | Startup test |
| SL-002 | Enabled servers start automatically on init | Critical | Auto-start test |
| SL-003 | Health check loop runs at configurable interval (default 30s) | Critical | Health loop test |
| SL-004 | Unhealthy servers marked with ServerStatusError | Critical | Status update test |
| SL-005 | Server recovery detected and status restored to Online | High | Recovery test |
| SL-006 | Server stop gracefully unloads all models first | High | Graceful stop test |
| SL-007 | Server restart preserves loaded model state | Medium | Restart test |
| SL-008 | Server metrics (request count, latency, memory) tracked | Medium | Metrics capture test |

### Multi-Backend Support (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MB-001 | Ollama server type connects to /api/ps for health | Critical | Ollama ping test |
| MB-002 | llama.cpp Router mode uses /models endpoint | Critical | Llama router test |
| MB-003 | llama.cpp Single mode uses /health endpoint | Critical | Llama single test |
| MB-004 | llama-swap proxy routes via config YAML | Critical | Swap proxy test |
| MB-005 | Backend type auto-detected from config | High | Type detection test |
| MB-006 | Multiple backends can run simultaneously | High | Multi-backend test |
| MB-007 | Backend-specific configuration honored | High | Config parsing test |

### Model Loading & Routing (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| ML-001 | Model loads on first request if not loaded | Critical | Lazy load test |
| ML-002 | Model warmup loads specified models on startup | Critical | Warmup test |
| ML-003 | LRU eviction unloads least-recently-used model when at capacity | Critical | Eviction test |
| ML-004 | GetServerForModel returns server with model already loaded first | High | Routing priority test |
| ML-005 | GetServerForModel falls back to server that can load model | High | Fallback routing test |
| ML-006 | Model category (thinking/writing/voice/coding) routes correctly | High | Category routing test |
| ML-007 | TTL-based unload respects keepAlive config | Medium | TTL test |
| ML-008 | Concurrent requests to same model share single load | Medium | Load dedup test |

### Request Processing (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| RP-001 | OpenAI-compatible /v1/chat/completions endpoint works | Critical | API compatibility test |
| RP-002 | Streaming responses supported via SSE | Critical | Streaming test |
| RP-003 | Request timeout configurable per model | High | Timeout test |
| RP-004 | Request queuing when server at capacity | High | Queue test |
| RP-005 | Request correlation ID passed through pipeline | Medium | Correlation test |
| RP-006 | Token usage tracked per request | Medium | Token tracking test |

### Configuration Management (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CM-001 | llm.servers config loads array of LLMServerConfig | Critical | Config parse test |
| CM-002 | llm.routing.defaultServerId respected | Critical | Default server test |
| CM-003 | Per-server port range honored (portRangeStart/End) | High | Port allocation test |
| CM-004 | executablePath validated on startup | High | Path validation test |
| CM-005 | modelsDir validated on startup | High | Dir validation test |
| CM-006 | Invalid config prevents server start with clear error | High | Validation error test |
| CM-007 | Runtime config reload supported (without restart) | Medium | Hot reload test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | Server offline returns ERR_LLM_SERVER_OFFLINE (7001) | Critical | Error code test |
| EH-002 | Model not found returns ERR_MODEL_NOT_FOUND (7002) | Critical | Error code test |
| EH-003 | Model load failure returns ERR_MODEL_LOAD_FAILED (7003) | Critical | Error code test |
| EH-004 | Request timeout returns ERR_LLM_TIMEOUT (7004) | Critical | Error code test |
| EH-005 | Automatic failover to backup server on primary failure | High | Failover test |
| EH-006 | All errors include serverId and modelId for debugging | High | Error context test |

### llama-swap Specific (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| LS-001 | YAML config auto-generated from seeding settings | Critical | Config gen test |
| LS-002 | Model aliases resolve correctly | High | Alias test |
| LS-003 | Instance start/stop managed automatically | High | Instance mgmt test |
| LS-004 | Request queuing works across instances | Medium | Cross-instance queue test |

---

## 28.12 Glossary

| Term | Definition |
|------|------------|
| Ollama | Go-based LLM server with native multi-model hot-swap |
| llama.cpp | C++ LLM inference engine |
| Router Mode | llama.cpp mode enabling runtime model management |
| llama-swap | Proxy managing multiple llama-server instances |
| Model Slot | Port/process allocation for a loaded model |
| Hot-swap | Switching models without server restart |
| TTL | Time-To-Live; seconds before idle model is unloaded |
