# AI Bridge: Configuration

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

AI Bridge uses YAML configuration with environment variable overrides and sensible defaults.

---

## Configuration File Locations

Search order (first found wins):
1. `--config` flag path
2. `./aibridge.yaml`
3. `~/.config/aibridge/config.yaml`
4. `/etc/aibridge/config.yaml`

---

## Full Configuration Schema

```yaml
# AI Bridge Configuration
# Version: 1.0.0

# ─────────────────────────────────────────────────────────────────────────────
# Backend Configuration
# ─────────────────────────────────────────────────────────────────────────────

backend:
  # Default backend to use: ollama | llama-cpp
  default: ollama
  
  # Ollama configuration
  ollama:
    baseUrl: http://localhost:11434
    timeout: 5m
    healthCheckInterval: 30s
    
  # llama.cpp configuration
  llamaCpp:
    baseUrl: http://localhost:8080
    serverPath: /usr/local/bin/llama-server
    timeout: 5m
    healthCheckInterval: 30s
    
    # llama-swap proxy (optional)
    llamaSwap:
      enabled: false
      baseUrl: http://localhost:8081
      autoLoadModels: true

# ─────────────────────────────────────────────────────────────────────────────
# Model Configuration
# ─────────────────────────────────────────────────────────────────────────────

models:
  # Directories to search for model files
  rootPaths:
    - /models
    - ~/.local/share/ollama/models
    
  # Default models by category
  defaults:
    thinking: qwen2.5-coder:32b
    writing: llama3.1:8b
    coding: deepseek-coder:6.7b
    voice: whisper:large-v3
    
  # Maximum concurrent models in memory
  maxConcurrent: 3
  
  # Auto-unload idle models after duration
  idleTimeout: 30m

# ─────────────────────────────────────────────────────────────────────────────
# Generation Defaults
# ─────────────────────────────────────────────────────────────────────────────

generation:
  temperature: 0.7
  maxTokens: 4096
  topP: 0.9
  contextSize: 8192
  
  # Retry configuration
  retry:
    maxAttempts: 3
    initialDelay: 500ms
    maxDelay: 10s
    backoffFactor: 2.0

# ─────────────────────────────────────────────────────────────────────────────
# Daemon Configuration
# ─────────────────────────────────────────────────────────────────────────────

daemon:
  host: 127.0.0.1
  port: 8089
  pidFile: /var/run/aibridge.pid
  logFile: /var/log/aibridge.log
  
  # TLS configuration
  tls:
    enabled: false
    certFile: ""
    keyFile: ""
    
  # Authentication
  auth:
    enabled: false
    apiKeys: []
    # - key: "sk_live_..."
    #   name: "Production"
    #   rateLimit: 100
    
  # Rate limiting
  rateLimit:
    enabled: true
    requestsPerMinute: 60
    burstSize: 10
    
  # WebSocket configuration
  websocket:
    enabled: true
    pingInterval: 30s
    writeTimeout: 10s
    maxMessageSize: 10MB
    
  # Graceful shutdown timeout
  shutdownTimeout: 30s
  
  # CORS configuration
  cors:
    enabled: true
    allowedOrigins:
      - "*"
    allowedMethods:
      - GET
      - POST
      - DELETE
      - OPTIONS
    allowedHeaders:
      - Authorization
      - Content-Type

# ─────────────────────────────────────────────────────────────────────────────
# Logging Configuration
# ─────────────────────────────────────────────────────────────────────────────

logging:
  level: info  # debug | info | warn | error
  format: json  # json | text
  
  # Include these fields in all log entries
  fields:
    service: aibridge
    version: 1.0.0
    
  # Request logging
  requests:
    enabled: true
    includeBody: false
    maxBodyLogSize: 1KB

# ─────────────────────────────────────────────────────────────────────────────
# Metrics Configuration
# ─────────────────────────────────────────────────────────────────────────────

metrics:
  enabled: true
  endpoint: /metrics
  
  # Prometheus metrics to export
  include:
    - request_duration_seconds
    - request_total
    - tokens_generated_total
    - model_load_duration_seconds
    - active_requests
    - backend_health
```

---

## Environment Variable Overrides

All configuration values can be overridden with environment variables using the `AIBRIDGE_` prefix:

| Config Path | Environment Variable |
|-------------|---------------------|
| `backend.default` | `AIBRIDGE_BACKEND_DEFAULT` |
| `backend.ollama.baseUrl` | `AIBRIDGE_BACKEND_OLLAMA_BASEURL` |
| `daemon.port` | `AIBRIDGE_DAEMON_PORT` |
| `models.defaults.thinking` | `AIBRIDGE_MODELS_DEFAULTS_THINKING` |
| `logging.level` | `AIBRIDGE_LOGGING_LEVEL` |

---

## Minimal Configuration

For quick start, only specify backends:

```yaml
# Minimal config
backend:
  default: ollama
  ollama:
    baseUrl: http://localhost:11434
```

---

## Configuration Loading

```go
type Config struct {
    Backend    BackendConfig    `yaml:"backend"`
    Models     ModelsConfig     `yaml:"models"`
    Generation GenerationConfig `yaml:"generation"`
    Daemon     DaemonConfig     `yaml:"daemon"`
    Logging    LoggingConfig    `yaml:"logging"`
    Metrics    MetricsConfig    `yaml:"metrics"`
}

func Load(path string) (*Config, error) {
    // 1. Load defaults
    cfg := DefaultConfig()
    
    // 2. Find config file
    if path == "" {
        path = findConfigFile()
    }
    
    // 3. Load from file
    if path != "" {
        data, err := os.ReadFile(path)
        if err != nil {
            return nil, err
        }
        if err := yaml.Unmarshal(data, cfg); err != nil {
            return nil, NewError(ErrConfigInvalid, "invalid config: %v", err)
        }
    }
    
    // 4. Apply environment overrides
    cfg.applyEnvOverrides()
    
    // 5. Validate
    if err := cfg.Validate(); err != nil {
        return nil, err
    }
    
    return cfg, nil
}

func DefaultConfig() *Config {
    return &Config{
        Backend: BackendConfig{
            Default: "ollama",
            Ollama: OllamaConfig{
                BaseUrl: "http://localhost:11434",
                Timeout: 5 * time.Minute,
            },
        },
        Generation: GenerationConfig{
            Temperature: 0.7,
            MaxTokens:   4096,
            TopP:        0.9,
            Retry: RetryConfig{
                MaxAttempts:   3,
                InitialDelay:  500 * time.Millisecond,
                MaxDelay:      10 * time.Second,
                BackoffFactor: 2.0,
            },
        },
        Daemon: DaemonConfig{
            Host: "127.0.0.1",
            Port: 8089,
            RateLimit: RateLimitConfig{
                Enabled:           true,
                RequestsPerMinute: 60,
                BurstSize:         10,
            },
            ShutdownTimeout: 30 * time.Second,
        },
        Logging: LoggingConfig{
            Level:  "info",
            Format: "json",
        },
    }
}
```

---

## Validation Rules

```go
func (c *Config) Validate() error {
    // Backend validation
    if c.Backend.Default != "ollama" && c.Backend.Default != "llama-cpp" {
        return NewError(ErrConfigInvalid, "invalid backend: %s", c.Backend.Default)
    }
    
    // Port validation
    if c.Daemon.Port < 1 || c.Daemon.Port > 65535 {
        return NewError(ErrConfigInvalid, "invalid port: %d", c.Daemon.Port)
    }
    
    // Temperature validation
    if c.Generation.Temperature < 0 || c.Generation.Temperature > 2 {
        return NewError(ErrConfigInvalid, "temperature must be 0-2")
    }
    
    // TLS validation
    if c.Daemon.TLS.Enabled {
        if c.Daemon.TLS.CertFile == "" || c.Daemon.TLS.KeyFile == "" {
            return NewError(ErrConfigInvalid, "TLS requires certFile and keyFile")
        }
    }
    
    return nil
}
```

---

## See Also

- [Startup Modes](./03-startup-modes.md)
- [Error Codes](./05-error-codes.md)
