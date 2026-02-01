# LLM Server Multi-Model Research

> **Status**: Research Complete  
> **Date**: 2026-01-28  
> **Purpose**: Document multi-model runtime configuration for llama.cpp and Ollama servers

---

## Executive Summary

Two primary LLM server options exist for local model hosting:
1. **llama.cpp (`llama-server`)** - C++ based, requires router mode or proxy for multi-model
2. **Ollama** - Go-based, native multi-model switching via API

Both can be configured via seeding config to run on different ports with multiple models.

---

## 1. llama.cpp Server

### 1.1 Native Limitations

Standard `llama-server` loads **one model** via `-m model.gguf`; switching requires restart or multiple ports/instances.

### 1.2 Router Mode (Built-in Dynamic Support)

Recent llama.cpp adds **router mode** for runtime model management (multi-process).

**Start Command:**
```bash
./llama-server --router --models-dir /path/to/models --port 8080
```

**API Operations:**
| Operation | Command |
|-----------|---------|
| List models | `curl http://localhost:8080/models` |
| Load model | `curl -X POST http://localhost:8080/models/load -d '{"model": "path/to/model.gguf"}'` |
| Use model | Specify `"model"` in `/chat/completions`; auto-routes/loads |
| Unload model | `curl -X POST http://localhost:8080/models/unload -d '{"model": "model.gguf"}'` |

**Model Status Values:** `loaded`, `loading`, `unloaded`

### 1.3 llama-swap Proxy

Single binary proxy that configures multiple llama-server instances with auto-load/swap on request.

**config.json:**
```json
{
  "listen": "0.0.0.0:8080",
  "models_dir": "/path/to/models",
  "seed_value": 42,
  "models": {
    "llama3": {
      "args": ["--model-path", "{{models_dir}}/llama3.gguf", "--ctx-size", "8192", "--seed", "{{seed_value}}"]
    },
    "mistral": {
      "args": ["--model-path", "{{models_dir}}/mistral.gguf", "--ctx-size", "8192", "--seed", "{{seed_value}}"]
    }
  }
}
```

**Features:**
- Handles request queuing
- Keeps popular models warm (TTL-based unload)
- Auto-swaps based on `"model"` field in API request

---

## 2. Ollama Server

### 2.1 Native Multi-Model Support

Ollama provides **super easy switching**—one server, specify model in API. Loads multiple models simultaneously (up to configured limit).

### 2.2 Configuration

**Environment Variables:**
```bash
OLLAMA_HOST=0.0.0.0:11434
OLLAMA_MAX_LOADED_MODELS=3
OLLAMA_KEEP_ALIVE=5m
```

**config.json (conceptual seeding):**
```json
{
  "host": "0.0.0.0",
  "port": 11434,
  "max_loaded_models": 3,
  "keep_alive": "5m",
  "seed_value": 42,
  "models": ["llama3", "mistral"]
}
```

### 2.3 API Operations

| Operation | Command |
|-----------|---------|
| Pull model | `ollama pull llama3` |
| List loaded | `curl http://localhost:11434/api/ps` |
| List all | `curl http://localhost:11434/api/tags` |
| Switch/Run | `curl http://localhost:11434/v1/chat/completions -d '{"model": "llama3", ...}'` |

**Key Advantage:** Changing `"model"` field auto-loads/switches instantly if RAM allows.

---

## 3. Configuration Strategy for Spec

### 3.1 Seeding Config Structure

Support both server types with unified configuration:

```yaml
llm:
  servers:
    - id: "primary"
      type: "ollama"  # or "llama" or "llama-swap"
      host: "127.0.0.1"
      port: 11434
      max_loaded_models: 3
      keep_alive: "5m"
      models:
        - name: "llama3"
          default: true
        - name: "mistral"
          
    - id: "reasoning"
      type: "llama"
      mode: "router"  # or "single" or "swap"
      host: "127.0.0.1"
      port: 8080
      models_dir: "/path/to/models"
      models:
        - name: "deepseek-r1"
          file: "deepseek-r1.gguf"
          ctx_size: 32768
          
    - id: "voice"
      type: "llama"
      mode: "single"
      host: "127.0.0.1"
      port: 8081
      model:
        file: "whisper.gguf"
```

### 3.2 Port Allocation Strategies

1. **Fixed Ports**: Each server gets a specific port from config
2. **Port Range**: Allocate from range (e.g., 8080-8089) with ModelSlot pool
3. **Dynamic**: System assigns available ports, stores in registry

### 3.3 Model Routing

```typescript
interface ModelRoute {
  modelId: string;
  serverId: string;
  priority: number;
  fallbackServerId?: string;
}
```

---

## 4. References

- [llama.cpp GitHub](https://github.com/ggml-org/llama.cpp)
- [llama.cpp Model Management](https://huggingface.co/blog/ggml-org/model-management-in-llamacpp)
- [llama-swap Proxy](https://github.com/mostlygeek/llama-swap)
- [Ollama API Docs](https://docs.ollama.com/api/introduction)
- [Ollama OpenAI Compatibility](https://docs.ollama.com/api/openai-compatibility)
