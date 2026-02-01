# Configuration Manifest

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28

---

## Overview

Complete manifest of all configuration keys with types, defaults, and validation rules. All keys use dot.notation format for hierarchical organization.

---

## Configuration Categories

| Category | Prefix | Description |
|----------|--------|-------------|
| Server | `server.*` | HTTP server settings |
| Database | `db.*` | SQLite connection settings |
| LLM Server | `llm.server.*` | LLM binary and port management |
| LLM Models | `llm.models.*` | Model paths and categories |
| AI Processing | `ai.*` | AI pipeline settings |
| Knowledge/RAG | `knowledge.*` | RAG system configuration |
| Tasks | `task.*` | Task execution settings |
| UI | `ui.*` | Frontend preferences |

---

## Server Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `server.host` | string | `"127.0.0.1"` | Yes | Server bind address |
| `server.port` | number | `8080` | Yes | Server port |
| `server.cors.enabled` | boolean | `true` | No | Enable CORS |
| `server.cors.origins` | string[] | `["http://localhost:*"]` | No | Allowed origins |
| `server.timeout.read` | number | `30` | No | Read timeout (seconds) |
| `server.timeout.write` | number | `60` | No | Write timeout (seconds) |

---

## Database Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `db.path` | string | `"./data/spec.db"` | Yes | SQLite database path |
| `db.maxConnections` | number | `10` | No | Max connection pool size |
| `db.enableWAL` | boolean | `true` | No | Enable Write-Ahead Logging |
| `db.busyTimeout` | number | `5000` | No | Busy timeout (ms) |

---

## LLM Server Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `llm.server.path` | string | `""` | Yes | Path to llama.cpp/llama-server binary |
| `llm.server.portRange.start` | number | `8081` | No | Start of port range for models |
| `llm.server.portRange.end` | number | `8089` | No | End of port range for models |
| `llm.server.bindAddress` | string | `"127.0.0.1"` | No | LLM server bind address |
| `llm.server.maxSlots` | number | `4` | No | Max concurrent model slots |
| `llm.server.healthCheckInterval` | number | `30` | No | Health check interval (seconds) |
| `llm.server.startupTimeout` | number | `120` | No | Model startup timeout (seconds) |
| `llm.server.idleUnloadTime` | number | `300` | No | Unload idle model after (seconds) |

---

## LLM Model Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `llm.models.rootPath` | string | `"./models"` | Yes | Root directory for model files |
| `llm.models.foldersByCategory.thinking` | string | `"./models/thinking"` | No | Reasoning models path |
| `llm.models.foldersByCategory.writing` | string | `"./models/writing"` | No | Writing models path |
| `llm.models.foldersByCategory.voice` | string | `"./models/voice"` | No | Voice/transcription models path |
| `llm.models.foldersByCategory.coding` | string | `"./models/coding"` | No | Code generation models path |
| `llm.models.defaultByCategory.thinking` | string | `""` | No | Default thinking model filename |
| `llm.models.defaultByCategory.writing` | string | `""` | No | Default writing model filename |
| `llm.models.defaultByCategory.voice` | string | `""` | No | Default voice model filename |
| `llm.models.defaultByCategory.coding` | string | `""` | No | Default coding model filename |
| `llm.models.contextSize.default` | number | `4096` | No | Default context window size |
| `llm.models.gpuLayers.default` | number | `-1` | No | GPU layers (-1 = auto) |

---

## AI Processing Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `ai.transport.format` | string | `"json"` | No | Data format: json, yaml, markdown |
| `ai.pipeline.maxRetries` | number | `3` | No | Max retries for LLM calls |
| `ai.pipeline.retryDelay` | number | `1000` | No | Retry delay (ms) |
| `ai.pipeline.timeout` | number | `300` | No | Pipeline timeout (seconds) |
| `ai.logging.enabled` | boolean | `true` | No | Enable AI logging |
| `ai.logging.shellCommands` | boolean | `false` | No | Log shell commands |
| `ai.logging.streamToWebSocket` | boolean | `true` | No | Stream logs via WebSocket |
| `ai.output.artifactPath` | string | `"./artifacts"` | No | Path for AI-generated artifacts |
| `ai.output.preserveHistory` | boolean | `true` | No | Keep artifact history |

---

## Instruction System Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `instruction.autoExecute` | boolean | `false` | No | Auto-execute after generation |
| `instruction.requireApproval` | boolean | `true` | No | Require user approval |
| `instruction.maxTasksPerInstruction` | number | `20` | No | Max tasks per instruction |
| `instruction.taskTimeout` | number | `120` | No | Per-task timeout (seconds) |

---

## Task Execution Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `task.maxParallelism` | number | `4` | No | Max parallel task execution |
| `task.queueSize` | number | `100` | No | Task queue size |
| `task.retryOnFailure` | boolean | `true` | No | Retry failed tasks |
| `task.maxRetries` | number | `2` | No | Max task retries |

---

## Knowledge/RAG Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `knowledge.specKnowledgeDbPath` | string | `"./data/spec_knowledge.db"` | No | Spec knowledge database |
| `knowledge.urlKnowledgeDbPath` | string | `"./data/url_knowledge.db"` | No | URL knowledge database |
| `knowledge.workerBinaryPath` | string | `""` | No | External worker binary path |
| `knowledge.maxConcurrentWorkers` | number | `3` | No | Max concurrent workers |
| `knowledge.statusUpdateIntervalMs` | number | `1000` | No | Status update interval |
| `knowledge.chunker.maxChunkTokens` | number | `512` | No | Max tokens per chunk |
| `knowledge.chunker.overlapTokens` | number | `50` | No | Overlap between chunks |
| `knowledge.embedding.model` | string | `"all-MiniLM-L6-v2"` | No | Embedding model name |
| `knowledge.embedding.dimension` | number | `384` | No | Embedding vector dimension |
| `knowledge.retrieval.topK` | number | `10` | No | Top-K results to retrieve |
| `knowledge.retrieval.hybridWeight` | number | `0.7` | No | Semantic vs keyword weight |
| `knowledge.retrieval.minScore` | number | `0.5` | No | Minimum relevance score |
| `knowledge.retrieval.cacheTTL` | number | `300` | No | Cache TTL (seconds) |

---

## Crawler Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `knowledge.crawler.maxDepth` | number | `3` | No | Max crawl depth |
| `knowledge.crawler.maxPages` | number | `100` | No | Max pages per domain |
| `knowledge.crawler.requestDelayMs` | number | `500` | No | Delay between requests |
| `knowledge.crawler.respectRobotsTxt` | boolean | `true` | No | Respect robots.txt |
| `knowledge.crawler.userAgent` | string | `"SpecManager/1.0"` | No | Crawler user agent |
| `knowledge.crawler.timeout` | number | `30` | No | Request timeout (seconds) |

---

## Prompt Preset Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `prompts.basePath` | string | `"./Prompts"` | No | Base path for prompt templates |
| `prompts.categories` | string[] | `["idea","feature","task","codingGuideline","instruction"]` | No | Valid content types |

---

## UI Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `ui.theme.default` | string | `"light"` | No | Default theme |
| `ui.sidebar.defaultWidth` | number | `280` | No | Sidebar width (px) |
| `ui.sidebar.collapsedWidth` | number | `56` | No | Collapsed width (px) |
| `ui.editor.fontSize` | number | `14` | No | Editor font size |
| `ui.editor.tabSize` | number | `2` | No | Tab size |
| `ui.editor.wordWrap` | boolean | `true` | No | Enable word wrap |

---

## History/Snapshot Configuration

| Key | Type | Default | Required | Description |
|-----|------|---------|----------|-------------|
| `history.snapshotPath` | string | `"./snapshots"` | No | Snapshot storage path |
| `history.maxSnapshotsPerProject` | number | `50` | No | Max snapshots per project |
| `history.autoSnapshot.enabled` | boolean | `false` | No | Auto-snapshot on changes |
| `history.autoSnapshot.intervalMinutes` | number | `30` | No | Auto-snapshot interval |

---

## Validation Rules

### Path Validation
```go
// All paths must:
// - Not contain ".." (path traversal)
// - Be within allowed directories
// - Max length: 255 characters
```

### Port Validation
```go
// Ports must be:
// - Between 1024 and 65535
// - Not conflict with reserved ports
// - portRange.end > portRange.start
```

### Numeric Ranges
```go
// task.maxParallelism: 1-16
// knowledge.chunker.maxChunkTokens: 128-2048
// knowledge.retrieval.hybridWeight: 0.0-1.0
// knowledge.retrieval.minScore: 0.0-1.0
```

---

## Environment Variable Override

All config keys can be overridden via environment variables:

```bash
# Format: SPECMGR_<KEY_WITH_UNDERSCORES>
# Example:
SPECMGR_LLM_SERVER_PATH=/usr/local/bin/llama-server
SPECMGR_DB_PATH=/var/data/spec.db
SPECMGR_TASK_MAX_PARALLELISM=8
```

---

## Configuration Load Order

1. **Defaults** (hardcoded in code)
2. **Config file** (`config.yaml` or `config.json`)
3. **Database** (Config table, source="user")
4. **Environment variables** (highest priority)

---

## Related Specs

- [Seeding Configuration](../05-features/06-ai-integration/09-seeding-configuration.md)
- [LLM Server Management](../05-features/06-ai-integration/07-llm-server-management.md)
- [Knowledge Memory System](../05-features/09-knowledge-memory/09-knowledge-memory-system.md)
