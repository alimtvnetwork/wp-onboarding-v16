# Configuration Manifest

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Overview

Complete configuration manifest for the AI-Powered Code Generation System with 80+ configurable keys covering server settings, database, LLM infrastructure, Git integration, security, and observability.

**Cross-References:**
- [System Architecture](./01-architecture.md)
- [Deployment Guide](./18-deployment-guide.md)
- [LLM Integration](../06-ai-integration/02-llm-integration.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)

---

## Configuration Architecture

### Hierarchy

```
┌─────────────────────────────────────────────────────────┐
│              Configuration Resolution Order              │
├─────────────────────────────────────────────────────────┤
│  1. Compiled Defaults (lowest priority)                 │
│       ↓                                                 │
│  2. config.json File                                    │
│       ↓                                                 │
│  3. Environment Variables (SPECMGR_* prefix)            │
│       ↓                                                 │
│  4. Command-line Flags (highest priority)               │
└─────────────────────────────────────────────────────────┘
```

### Key Naming Convention

All configuration keys use **dot.notation** format for hierarchical namespacing:

```
{domain}.{subdomain}.{setting}

Examples:
  server.http.port
  llm.models.thinking
  database.pool.maxOpen
```

### Environment Variable Mapping

Environment variables use the `SPECMGR_` prefix with underscores replacing dots:

```
server.http.port     → SPECMGR_SERVER_HTTP_PORT
llm.models.thinking  → SPECMGR_LLM_MODELS_THINKING
```

---

## Configuration Manifest

### 1. Server Configuration (10 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `server.http.host` | string | `"127.0.0.1"` | `SPECMGR_SERVER_HTTP_HOST` | HTTP server bind address |
| `server.http.port` | int | `8080` | `SPECMGR_SERVER_HTTP_PORT` | HTTP server port |
| `server.http.readTimeout` | duration | `"30s"` | `SPECMGR_SERVER_HTTP_READTIMEOUT` | Request read timeout |
| `server.http.writeTimeout` | duration | `"60s"` | `SPECMGR_SERVER_HTTP_WRITETIMEOUT` | Response write timeout |
| `server.http.idleTimeout` | duration | `"120s"` | `SPECMGR_SERVER_HTTP_IDLETIMEOUT` | Keep-alive idle timeout |
| `server.http.maxHeaderBytes` | int | `1048576` | `SPECMGR_SERVER_HTTP_MAXHEADERBYTES` | Max header size (1MB) |
| `server.mode` | string | `"development"` | `SPECMGR_SERVER_MODE` | `development`, `production`, `test` |
| `server.gracefulShutdown` | duration | `"30s"` | `SPECMGR_SERVER_GRACEFULSHUTDOWN` | Graceful shutdown timeout |
| `server.trustedProxies` | []string | `[]` | `SPECMGR_SERVER_TRUSTEDPROXIES` | Trusted proxy IPs (comma-separated) |
| `server.requestBodyLimit` | string | `"50MB"` | `SPECMGR_SERVER_REQUESTBODYLIMIT` | Max request body size |

### 2. Database Configuration (12 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `database.path` | string | `"./data/db"` | `SPECMGR_DATABASE_PATH` | SQLite database directory |
| `database.pool.maxOpen` | int | `25` | `SPECMGR_DATABASE_POOL_MAXOPEN` | Max open connections |
| `database.pool.maxIdle` | int | `5` | `SPECMGR_DATABASE_POOL_MAXIDLE` | Max idle connections |
| `database.pool.maxLifetime` | duration | `"30m"` | `SPECMGR_DATABASE_POOL_MAXLIFETIME` | Max connection lifetime |
| `database.pool.maxIdleTime` | duration | `"10m"` | `SPECMGR_DATABASE_POOL_MAXIDLETIME` | Max idle time before close |
| `database.pragma.journalMode` | string | `"WAL"` | `SPECMGR_DATABASE_PRAGMA_JOURNALMODE` | SQLite journal mode |
| `database.pragma.synchronous` | string | `"NORMAL"` | `SPECMGR_DATABASE_PRAGMA_SYNCHRONOUS` | Sync mode |
| `database.pragma.cacheSize` | int | `-64000` | `SPECMGR_DATABASE_PRAGMA_CACHESIZE` | Page cache size (KB) |
| `database.pragma.busyTimeout` | int | `5000` | `SPECMGR_DATABASE_PRAGMA_BUSYTIMEOUT` | Busy timeout (ms) |
| `database.migrations.autoRun` | bool | `true` | `SPECMGR_DATABASE_MIGRATIONS_AUTORUN` | Auto-run migrations on start |
| `database.migrations.path` | string | `"./migrations"` | `SPECMGR_DATABASE_MIGRATIONS_PATH` | Migrations directory |
| `database.backup.enabled` | bool | `false` | `SPECMGR_DATABASE_BACKUP_ENABLED` | Enable auto backups |

### 3. LLM Server Configuration (18 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `llm.backend` | string | `"llama-swap"` | `SPECMGR_LLM_BACKEND` | `llama-swap`, `ollama`, `llama.cpp` |
| `llm.server.path` | string | `"./bin/llama-server"` | `SPECMGR_LLM_SERVER_PATH` | LLM server executable path |
| `llm.server.host` | string | `"127.0.0.1"` | `SPECMGR_LLM_SERVER_HOST` | LLM server bind address |
| `llm.server.port` | int | `8081` | `SPECMGR_LLM_SERVER_PORT` | Primary LLM server port |
| `llm.server.portRange.start` | int | `8081` | `SPECMGR_LLM_SERVER_PORTRANGE_START` | Port range start |
| `llm.server.portRange.end` | int | `8089` | `SPECMGR_LLM_SERVER_PORTRANGE_END` | Port range end |
| `llm.server.maxSlots` | int | `4` | `SPECMGR_LLM_SERVER_MAXSLOTS` | Max concurrent model slots |
| `llm.server.healthCheckInterval` | duration | `"30s"` | `SPECMGR_LLM_SERVER_HEALTHCHECKINTERVAL` | Health check frequency |
| `llm.server.requestTimeout` | duration | `"300s"` | `SPECMGR_LLM_SERVER_REQUESTTIMEOUT` | LLM request timeout |
| `llm.server.idleTTL` | duration | `"10m"` | `SPECMGR_LLM_SERVER_IDLETTL` | Idle model unload time |
| `llm.models.root` | string | `"./models"` | `SPECMGR_LLM_MODELS_ROOT` | Models root directory |
| `llm.models.thinking` | string | `"thinking/deepseek-r1-8b.gguf"` | `SPECMGR_LLM_MODELS_THINKING` | Reasoning model path |
| `llm.models.writing` | string | `"writing/llama-3.1-8b.gguf"` | `SPECMGR_LLM_MODELS_WRITING` | Writing model path |
| `llm.models.coding` | string | `"coding/qwen-2.5-coder-7b.gguf"` | `SPECMGR_LLM_MODELS_CODING` | Coding model path |
| `llm.models.voice` | string | `"voice/whisper-large-v3.gguf"` | `SPECMGR_LLM_MODELS_VOICE` | Voice transcription model |
| `llm.defaults.temperature` | float | `0.7` | `SPECMGR_LLM_DEFAULTS_TEMPERATURE` | Default temperature |
| `llm.defaults.maxTokens` | int | `4096` | `SPECMGR_LLM_DEFAULTS_MAXTOKENS` | Default max output tokens |
| `llm.defaults.contextLength` | int | `32768` | `SPECMGR_LLM_DEFAULTS_CONTEXTLENGTH` | Default context window |

### 4. Code Generation Configuration (12 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `codegen.parallel.maxWorkers` | int | `4` | `SPECMGR_CODEGEN_PARALLEL_MAXWORKERS` | Max parallel file writers |
| `codegen.parallel.batchSize` | int | `10` | `SPECMGR_CODEGEN_PARALLEL_BATCHSIZE` | Files per batch |
| `codegen.retry.maxAttempts` | int | `3` | `SPECMGR_CODEGEN_RETRY_MAXATTEMPTS` | Max retry attempts per file |
| `codegen.retry.backoffBase` | duration | `"1s"` | `SPECMGR_CODEGEN_RETRY_BACKOFFBASE` | Retry backoff base |
| `codegen.consistency.enabled` | bool | `true` | `SPECMGR_CODEGEN_CONSISTENCY_ENABLED` | Enable consistency check phase |
| `codegen.consistency.maxIterations` | int | `5` | `SPECMGR_CODEGEN_CONSISTENCY_MAXITERATIONS` | Max fix iterations |
| `codegen.build.enabled` | bool | `true` | `SPECMGR_CODEGEN_BUILD_ENABLED` | Enable build verification phase |
| `codegen.build.timeout` | duration | `"300s"` | `SPECMGR_CODEGEN_BUILD_TIMEOUT` | Build timeout |
| `codegen.build.fixLoop.maxTiers` | int | `3` | `SPECMGR_CODEGEN_BUILD_FIXLOOP_MAXTIERS` | Max AI fix tiers |
| `codegen.output.preserveExisting` | bool | `false` | `SPECMGR_CODEGEN_OUTPUT_PRESERVEEXISTING` | Preserve existing files |
| `codegen.output.cleanOnStart` | bool | `true` | `SPECMGR_CODEGEN_OUTPUT_CLEANONSTART` | Clean output dir on start |
| `codegen.tempDir` | string | `"./data/temp"` | `SPECMGR_CODEGEN_TEMPDIR` | Temp directory for generation |

### 5. Guidelines Configuration (8 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `guidelines.path.general` | string | `"./guidelines/general"` | `SPECMGR_GUIDELINES_PATH_GENERAL` | General guidelines path |
| `guidelines.path.language` | string | `"./guidelines/language"` | `SPECMGR_GUIDELINES_PATH_LANGUAGE` | Language-specific guidelines |
| `guidelines.path.user` | string | `"./guidelines/user"` | `SPECMGR_GUIDELINES_PATH_USER` | User-defined guidelines |
| `guidelines.path.project` | string | `"./.guidelines"` | `SPECMGR_GUIDELINES_PATH_PROJECT` | Project-level guidelines |
| `guidelines.merge.strategy` | string | `"extend"` | `SPECMGR_GUIDELINES_MERGE_STRATEGY` | `extend`, `override`, `replace` |
| `guidelines.cache.enabled` | bool | `true` | `SPECMGR_GUIDELINES_CACHE_ENABLED` | Cache merged guidelines |
| `guidelines.cache.ttl` | duration | `"1h"` | `SPECMGR_GUIDELINES_CACHE_TTL` | Cache TTL |
| `guidelines.validation.strict` | bool | `false` | `SPECMGR_GUIDELINES_VALIDATION_STRICT` | Strict YAML validation |

### 6. Git Integration Configuration (10 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `git.enabled` | bool | `true` | `SPECMGR_GIT_ENABLED` | Enable Git integration |
| `git.autoCommit` | bool | `true` | `SPECMGR_GIT_AUTOCOMMIT` | Auto-commit after generation |
| `git.autoPush` | bool | `false` | `SPECMGR_GIT_AUTOPUSH` | Auto-push to remote |
| `git.commitMessage.template` | string | `"[codegen] {phase}: {summary}"` | `SPECMGR_GIT_COMMITMESSAGE_TEMPLATE` | Commit message template |
| `git.commitMessage.includeSpec` | bool | `true` | `SPECMGR_GIT_COMMITMESSAGE_INCLUDESPEC` | Include spec references |
| `git.remote.type` | string | `"github"` | `SPECMGR_GIT_REMOTE_TYPE` | `github`, `gitlab`, `none` |
| `git.remote.defaultBranch` | string | `"main"` | `SPECMGR_GIT_REMOTE_DEFAULTBRANCH` | Default branch name |
| `git.hooks.preCommit` | string | `""` | `SPECMGR_GIT_HOOKS_PRECOMMIT` | Pre-commit hook script |
| `git.hooks.postCommit` | string | `""` | `SPECMGR_GIT_HOOKS_POSTCOMMIT` | Post-commit hook script |
| `git.ignore.patterns` | []string | `[".temp", "*.log"]` | `SPECMGR_GIT_IGNORE_PATTERNS` | Additional ignore patterns |

### 7. Security Configuration (10 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `security.jwt.secret` | string | `""` | `SPECMGR_SECURITY_JWT_SECRET` | JWT signing secret (required) |
| `security.jwt.issuer` | string | `"codegen-system"` | `SPECMGR_SECURITY_JWT_ISSUER` | JWT issuer claim |
| `security.jwt.expiry` | duration | `"24h"` | `SPECMGR_SECURITY_JWT_EXPIRY` | Token expiry duration |
| `security.jwt.refreshExpiry` | duration | `"168h"` | `SPECMGR_SECURITY_JWT_REFRESHEXPIRY` | Refresh token expiry (7d) |
| `security.cors.origins` | []string | `["http://localhost:5173"]` | `SPECMGR_SECURITY_CORS_ORIGINS` | Allowed CORS origins |
| `security.cors.methods` | []string | `["GET","POST","PUT","DELETE"]` | `SPECMGR_SECURITY_CORS_METHODS` | Allowed HTTP methods |
| `security.rateLimit.enabled` | bool | `true` | `SPECMGR_SECURITY_RATELIMIT_ENABLED` | Enable rate limiting |
| `security.rateLimit.requestsPerMinute` | int | `100` | `SPECMGR_SECURITY_RATELIMIT_REQUESTSPERMINUTE` | Requests per minute |
| `security.encryption.algorithm` | string | `"AES-256-GCM"` | `SPECMGR_SECURITY_ENCRYPTION_ALGORITHM` | Token encryption algorithm |
| `security.firewall.enabled` | bool | `true` | `SPECMGR_SECURITY_FIREWALL_ENABLED` | Enable firewall rules |

### 8. Logging Configuration (8 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `logging.level` | string | `"info"` | `SPECMGR_LOGGING_LEVEL` | `debug`, `info`, `warn`, `error` |
| `logging.format` | string | `"json"` | `SPECMGR_LOGGING_FORMAT` | `json`, `text`, `pretty` |
| `logging.output` | string | `"stdout"` | `SPECMGR_LOGGING_OUTPUT` | `stdout`, `file`, `both` |
| `logging.file.path` | string | `"./logs/server.log"` | `SPECMGR_LOGGING_FILE_PATH` | Log file path |
| `logging.file.maxSize` | string | `"100MB"` | `SPECMGR_LOGGING_FILE_MAXSIZE` | Max file size before rotation |
| `logging.file.maxAge` | int | `30` | `SPECMGR_LOGGING_FILE_MAXAGE` | Max days to retain logs |
| `logging.file.maxBackups` | int | `10` | `SPECMGR_LOGGING_FILE_MAXBACKUPS` | Max backup files |
| `logging.includeStackTrace` | bool | `true` | `SPECMGR_LOGGING_INCLUDESTACKTRACE` | Include stack traces in errors |

### 9. WebSocket Configuration (6 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `websocket.path` | string | `"/ws"` | `SPECMGR_WEBSOCKET_PATH` | WebSocket endpoint path |
| `websocket.pingInterval` | duration | `"30s"` | `SPECMGR_WEBSOCKET_PINGINTERVAL` | Heartbeat ping interval |
| `websocket.pongTimeout` | duration | `"10s"` | `SPECMGR_WEBSOCKET_PONGTIMEOUT` | Pong response timeout |
| `websocket.maxMessageSize` | int | `1048576` | `SPECMGR_WEBSOCKET_MAXMESSAGESIZE` | Max message size (1MB) |
| `websocket.writeBufferSize` | int | `4096` | `SPECMGR_WEBSOCKET_WRITEBUFFERSIZE` | Write buffer size |
| `websocket.readBufferSize` | int | `4096` | `SPECMGR_WEBSOCKET_READBUFFERSIZE` | Read buffer size |

### 10. Observability Configuration (8 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `observability.metrics.enabled` | bool | `true` | `SPECMGR_OBSERVABILITY_METRICS_ENABLED` | Enable Prometheus metrics |
| `observability.metrics.path` | string | `"/metrics"` | `SPECMGR_OBSERVABILITY_METRICS_PATH` | Metrics endpoint |
| `observability.tracing.enabled` | bool | `false` | `SPECMGR_OBSERVABILITY_TRACING_ENABLED` | Enable OTEL tracing |
| `observability.tracing.endpoint` | string | `""` | `SPECMGR_OBSERVABILITY_TRACING_ENDPOINT` | OTEL collector endpoint |
| `observability.tracing.sampleRate` | float | `0.1` | `SPECMGR_OBSERVABILITY_TRACING_SAMPLERATE` | Trace sample rate (0-1) |
| `observability.health.path` | string | `"/health"` | `SPECMGR_OBSERVABILITY_HEALTH_PATH` | Health check endpoint |
| `observability.health.includeDetails` | bool | `true` | `SPECMGR_OBSERVABILITY_HEALTH_INCLUDEDETAILS` | Include dependency status |
| `observability.profiling.enabled` | bool | `false` | `SPECMGR_OBSERVABILITY_PROFILING_ENABLED` | Enable pprof endpoints |

### 11. Credit System Configuration (6 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `credits.enabled` | bool | `true` | `SPECMGR_CREDITS_ENABLED` | Enable credit tracking |
| `credits.rates.inputTokens` | float | `0.001` | `SPECMGR_CREDITS_RATES_INPUTTOKENS` | Cost per input token |
| `credits.rates.outputTokens` | float | `0.003` | `SPECMGR_CREDITS_RATES_OUTPUTTOKENS` | Cost per output token |
| `credits.limits.daily` | int | `100000` | `SPECMGR_CREDITS_LIMITS_DAILY` | Daily credit limit |
| `credits.limits.perSession` | int | `10000` | `SPECMGR_CREDITS_LIMITS_PERSESSION` | Per-session credit limit |
| `credits.alertThreshold` | float | `0.8` | `SPECMGR_CREDITS_ALERTTHRESHOLD` | Alert at % of limit |

### 12. Knowledge/RAG Configuration (8 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `knowledge.worker.path` | string | `"./bin/knowledge-worker"` | `SPECMGR_KNOWLEDGE_WORKER_PATH` | Worker binary path |
| `knowledge.worker.timeout` | duration | `"300s"` | `SPECMGR_KNOWLEDGE_WORKER_TIMEOUT` | Worker job timeout |
| `knowledge.crawler.maxDepth` | int | `3` | `SPECMGR_KNOWLEDGE_CRAWLER_MAXDEPTH` | Max crawl depth |
| `knowledge.crawler.maxPages` | int | `100` | `SPECMGR_KNOWLEDGE_CRAWLER_MAXPAGES` | Max pages per domain |
| `knowledge.crawler.rateLimit` | duration | `"1s"` | `SPECMGR_KNOWLEDGE_CRAWLER_RATELIMIT` | Request rate limit |
| `knowledge.embedding.model` | string | `"all-minilm-l6-v2"` | `SPECMGR_KNOWLEDGE_EMBEDDING_MODEL` | Embedding model |
| `knowledge.embedding.dimensions` | int | `384` | `SPECMGR_KNOWLEDGE_EMBEDDING_DIMENSIONS` | Embedding dimensions |
| `knowledge.retrieval.topK` | int | `10` | `SPECMGR_KNOWLEDGE_RETRIEVAL_TOPK` | Top-K results |

### 13. File Operations Configuration (6 keys)

| Key | Type | Default | Env Override | Description |
|-----|------|---------|--------------|-------------|
| `files.maxPathLength` | int | `255` | `SPECMGR_FILES_MAXPATHLENGTH` | Max file path length |
| `files.allowedExtensions` | []string | `[".go",".ts",".tsx",".md"]` | `SPECMGR_FILES_ALLOWEDEXTENSIONS` | Allowed file extensions |
| `files.maxFileSize` | string | `"10MB"` | `SPECMGR_FILES_MAXFILESIZE` | Max single file size |
| `files.encoding` | string | `"utf-8"` | `SPECMGR_FILES_ENCODING` | Default file encoding |
| `files.lineEnding` | string | `"lf"` | `SPECMGR_FILES_LINEENDING` | `lf`, `crlf`, `auto` |
| `files.backup.enabled` | bool | `true` | `SPECMGR_FILES_BACKUP_ENABLED` | Backup before overwrite |

---

## Validation Rules

### Type Validation

| Type | Format | Example |
|------|--------|---------|
| `string` | UTF-8 text | `"value"` |
| `int` | 32-bit integer | `8080` |
| `float` | 64-bit float | `0.7` |
| `bool` | Boolean | `true`, `false` |
| `duration` | Go duration | `"30s"`, `"5m"`, `"1h"` |
| `[]string` | JSON array | `["a", "b"]` |

### Required Keys

The following keys must be set before startup:

```
security.jwt.secret  # Must be non-empty, min 32 characters
database.path        # Must be valid writable path
llm.server.path      # Must exist if backend != "ollama"
```

### Conditional Requirements

```yaml
# If git.enabled == true
git.remote.type: required

# If logging.output contains "file"
logging.file.path: required

# If observability.tracing.enabled == true
observability.tracing.endpoint: required
```

---

## Configuration File Examples

### Minimal Development Config

```json
{
  "server.mode": "development",
  "database.path": "./data/db",
  "security.jwt.secret": "dev-secret-change-in-production",
  "llm.backend": "ollama"
}
```

### Full Production Config

```json
{
  "server.http.host": "0.0.0.0",
  "server.http.port": 8080,
  "server.mode": "production",
  "server.gracefulShutdown": "30s",
  
  "database.path": "/var/lib/codegen/db",
  "database.pool.maxOpen": 50,
  "database.pool.maxIdle": 10,
  "database.migrations.autoRun": true,
  
  "llm.backend": "llama-swap",
  "llm.server.host": "127.0.0.1",
  "llm.server.port": 8081,
  "llm.server.portRange.start": 8081,
  "llm.server.portRange.end": 8089,
  "llm.models.root": "/opt/codegen/models",
  "llm.models.thinking": "thinking/deepseek-r1-8b.gguf",
  "llm.models.writing": "writing/llama-3.1-8b.gguf",
  "llm.models.coding": "coding/qwen-2.5-coder-7b.gguf",
  
  "codegen.parallel.maxWorkers": 8,
  "codegen.consistency.enabled": true,
  "codegen.build.enabled": true,
  
  "git.enabled": true,
  "git.autoCommit": true,
  "git.autoPush": true,
  "git.remote.type": "github",
  
  "security.jwt.secret": "${SPECMGR_SECURITY_JWT_SECRET}",
  "security.cors.origins": ["https://app.example.com"],
  "security.rateLimit.enabled": true,
  
  "logging.level": "info",
  "logging.format": "json",
  "logging.output": "file",
  "logging.file.path": "/var/log/codegen/server.log",
  
  "observability.metrics.enabled": true,
  "observability.tracing.enabled": true,
  "observability.tracing.endpoint": "http://localhost:4317"
}
```

---

## Go Implementation

### Config Struct

```go
// internal/config/config.go

package config

import (
    "time"
)

type Config struct {
    Server        ServerConfig        `mapstructure:"server"`
    Database      DatabaseConfig      `mapstructure:"database"`
    LLM           LLMConfig           `mapstructure:"llm"`
    CodeGen       CodeGenConfig       `mapstructure:"codegen"`
    Guidelines    GuidelinesConfig    `mapstructure:"guidelines"`
    Git           GitConfig           `mapstructure:"git"`
    Security      SecurityConfig      `mapstructure:"security"`
    Logging       LoggingConfig       `mapstructure:"logging"`
    WebSocket     WebSocketConfig     `mapstructure:"websocket"`
    Observability ObservabilityConfig `mapstructure:"observability"`
    Credits       CreditsConfig       `mapstructure:"credits"`
    Knowledge     KnowledgeConfig     `mapstructure:"knowledge"`
    Files         FilesConfig         `mapstructure:"files"`
}

type ServerConfig struct {
    HTTP             HTTPConfig `mapstructure:"http"`
    Mode             string     `mapstructure:"mode"`
    GracefulShutdown Duration   `mapstructure:"gracefulShutdown"`
    TrustedProxies   []string   `mapstructure:"trustedProxies"`
    RequestBodyLimit string     `mapstructure:"requestBodyLimit"`
}

type HTTPConfig struct {
    Host           string   `mapstructure:"host"`
    Port           int      `mapstructure:"port"`
    ReadTimeout    Duration `mapstructure:"readTimeout"`
    WriteTimeout   Duration `mapstructure:"writeTimeout"`
    IdleTimeout    Duration `mapstructure:"idleTimeout"`
    MaxHeaderBytes int      `mapstructure:"maxHeaderBytes"`
}

type LLMConfig struct {
    Backend  string           `mapstructure:"backend"`
    Server   LLMServerConfig  `mapstructure:"server"`
    Models   LLMModelsConfig  `mapstructure:"models"`
    Defaults LLMDefaultConfig `mapstructure:"defaults"`
}

type LLMServerConfig struct {
    Path                string        `mapstructure:"path"`
    Host                string        `mapstructure:"host"`
    Port                int           `mapstructure:"port"`
    PortRange           PortRange     `mapstructure:"portRange"`
    MaxSlots            int           `mapstructure:"maxSlots"`
    HealthCheckInterval Duration      `mapstructure:"healthCheckInterval"`
    RequestTimeout      Duration      `mapstructure:"requestTimeout"`
    IdleTTL             Duration      `mapstructure:"idleTTL"`
}

// Additional config structs...
```

### Loading Configuration

```go
// internal/config/loader.go

package config

import (
    "strings"
    
    "github.com/spf13/viper"
)

func Load(configPath string) (*Config, error) {
    v := viper.New()
    
    // Set defaults
    setDefaults(v)
    
    // Load config file
    v.SetConfigFile(configPath)
    v.SetConfigType("json")
    
    if err := v.ReadInConfig(); err != nil {
        // Config file is optional, continue with defaults
        if _, ok := err.(viper.ConfigFileNotFoundError); !ok {
            return nil, err
        }
    }
    
    // Environment variable overrides
    v.SetEnvPrefix("SPECMGR")
    v.SetEnvKeyReplacer(strings.NewReplacer(".", "_"))
    v.AutomaticEnv()
    
    var config Config
    if err := v.Unmarshal(&config); err != nil {
        return nil, err
    }
    
    // Validate required fields
    if err := config.Validate(); err != nil {
        return nil, err
    }
    
    return &config, nil
}

func setDefaults(v *viper.Viper) {
    // Server defaults
    v.SetDefault("server.http.host", "127.0.0.1")
    v.SetDefault("server.http.port", 8080)
    v.SetDefault("server.http.readTimeout", "30s")
    v.SetDefault("server.http.writeTimeout", "60s")
    v.SetDefault("server.mode", "development")
    
    // Database defaults
    v.SetDefault("database.path", "./data/db")
    v.SetDefault("database.pool.maxOpen", 25)
    v.SetDefault("database.pool.maxIdle", 5)
    
    // LLM defaults
    v.SetDefault("llm.backend", "llama-swap")
    v.SetDefault("llm.server.host", "127.0.0.1")
    v.SetDefault("llm.server.port", 8081)
    v.SetDefault("llm.server.maxSlots", 4)
    
    // ... additional defaults
}
```

---

## Database Seeding

### Seeding Table Schema

```sql
CREATE TABLE IF NOT EXISTS config_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT NOT NULL,
    type TEXT NOT NULL,
    description TEXT,
    is_secret BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_config_key ON config_settings(key);
```

### Seeding on First Run

```go
func SeedDefaults(db *gorm.DB) error {
    defaults := []ConfigSetting{
        {Key: "server.http.port", Value: "8080", Type: "int", Description: "HTTP server port"},
        {Key: "server.mode", Value: "development", Type: "string", Description: "Server mode"},
        // ... 80+ keys
    }
    
    for _, setting := range defaults {
        result := db.Where("key = ?", setting.Key).FirstOrCreate(&setting)
        if result.Error != nil {
            return result.Error
        }
    }
    
    return nil
}
```

---

## Related Specs

- [System Architecture](./01-system-architecture.md)
- [Deployment Guide](./13-deployment-guide.md)
- [LLM Integration](../06-ai-integration/02-llm-integration.md)
- [Error Handling](./07-error-handling.md)

---

## Implementation Checklist

- [ ] Config struct definitions
- [ ] Viper-based loader with env overrides
- [ ] Validation rules implementation
- [ ] Database seeding for 80+ keys
- [ ] Hot-reload support for non-critical settings
- [ ] Config diff/migration tooling
- [ ] Config export/import CLI commands
- [ ] Sensitive value encryption
- [ ] Configuration documentation generator
