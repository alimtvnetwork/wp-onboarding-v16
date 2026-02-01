# AI Bridge CLI Microservice

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-30  

---

## Overview

The AI Bridge CLI is a standalone Golang microservice that centralizes all AI/LLM operations for the Spec Management ecosystem. It provides a unified interface for model management, inference, and orchestration—allowing the main application to remain lightweight while delegating complex AI logic to this dedicated service.

**Binary Name:** `ai-bridge`  
**Module Path:** `github.com/user/ai-bridge`  
**Error Code Range:** `6xxx`  
**Default Port:** `8090`

---

## Cross-References

- [Main Project Overview](../../00-overview.md)
- [AI Integration Feature](../../05-features/06-ai-integration/00-overview.md)
- [Voice CLI Microservice](./10-voice-cli.md)
- [Nexus-Flow Standalone](./09-nexus-flow-standalone-architecture.md)
- [Error Management](../../06-error-management/00-overview.md)
- [Shared Pkg Modules](./02-shared-pkg-modules.md)

---

## Design Philosophy

### Separation of Concerns

The main Spec Management Software should NOT:
- Directly interact with LLM providers
- Manage model files or configurations
- Handle GPU/CPU inference logic
- Store conversation history for AI chats

Instead, it delegates ALL AI operations to the AI Bridge CLI via HTTP/WebSocket APIs.

### Standalone Capability

The AI Bridge CLI can:
- Run independently on any machine
- Serve multiple applications simultaneously
- Be deployed as a desktop app (Wails) or headless service
- Manage its own databases and model files

---

## Architecture

### High-Level Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Client Applications                          │
├─────────────────┬─────────────────┬─────────────────────────────────┤
│ Spec Management │   Nexus-Flow    │      External Apps              │
│    Software     │                 │                                 │
└────────┬────────┴────────┬────────┴─────────────┬───────────────────┘
         │                 │                      │
         │    HTTP/WebSocket (Port 8090)          │
         │                 │                      │
         ▼                 ▼                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        AI Bridge CLI                                 │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │   Router    │  │   Model     │  │  Inference  │  │   Memory    │ │
│  │   Layer     │  │   Manager   │  │   Engine    │  │   Manager   │ │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘ │
│         │                │                │                │        │
│         └────────────────┴────────────────┴────────────────┘        │
│                                   │                                  │
│  ┌────────────────────────────────┴────────────────────────────────┐│
│  │                     Provider Adapters                            ││
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌────────┐ ││
│  │  │ Ollama  │  │llama.cpp│  │ llama-  │  │ OpenAI  │  │ Local  │ ││
│  │  │         │  │         │  │  swap   │  │  API    │  │Whisper │ ││
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └────────┘ ││
│  └──────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Database Layer                                │
│  ┌─────────────┐  ┌─────────────────────────────────────────────┐   │
│  │  root.db    │  │          Application Databases              │   │
│  │  (Global)   │  │  ┌─────────────┐  ┌─────────────────────┐   │   │
│  │             │  │  │ {app-id}/   │  │ {app-id}/           │   │   │
│  │ - Settings  │  │  │ app.db      │  │ projects/           │   │   │
│  │ - AppIndex  │  │  │             │  │ ├─ {proj-id}.db     │   │   │
│  │ - Providers │  │  │ - AppMeta   │  │ └─ {proj-id}.db     │   │   │
│  │ - Models    │  │  │ - ProjIndex │  │                     │   │   │
│  └─────────────┘  │  └─────────────┘  └─────────────────────┘   │   │
│                   └─────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Database Architecture

### Multi-Tier SQLite System

#### Tier 1: Root Database (`root.db`)

Global configuration and application registry.

```
Location: {data-dir}/root.db
```

**Tables:**

| Table | Purpose |
|-------|---------|
| Settings | Global AI Bridge settings |
| ApplicationIndex | Registered client applications |
| ProviderRegistry | Available inference providers |
| ModelRegistry | All known models across providers |
| ModelPaths | Configured model storage locations |
| PortRegistry | Port allocations and firewall rules |
| SystemLogs | Aggregated system-level logs |

**Schema: Settings**

```sql
CREATE TABLE Settings (
    Key TEXT PRIMARY KEY,
    Value TEXT NOT NULL,
    ValueType TEXT NOT NULL CHECK (ValueType IN ('string', 'number', 'boolean', 'json')),
    Category TEXT NOT NULL,
    Description TEXT,
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Default settings
INSERT INTO Settings (Key, Value, ValueType, Category, Description) VALUES
('DefaultPort', '8090', 'number', 'server', 'Default HTTP server port'),
('EnableFirewall', 'true', 'boolean', 'security', 'Auto-manage firewall rules'),
('MaxConcurrentInference', '4', 'number', 'performance', 'Max parallel inference requests'),
('DefaultProvider', 'ollama', 'string', 'inference', 'Default LLM provider'),
('LogLevel', 'info', 'string', 'logging', 'Logging verbosity'),
('ModelBasePath', './models', 'string', 'storage', 'Root path for model files'),
('EnableGPU', 'true', 'boolean', 'hardware', 'Use GPU acceleration if available'),
('StackTraceDepth', '40', 'number', 'debugging', 'Error stack trace depth');
```

**Schema: ApplicationIndex**

```sql
CREATE TABLE ApplicationIndex (
    ID TEXT PRIMARY KEY,                    -- UUID
    Name TEXT NOT NULL UNIQUE,              -- e.g., "spec-management-software"
    DisplayName TEXT NOT NULL,              -- Human-readable name
    Description TEXT,
    DataPath TEXT NOT NULL,                 -- Path to app's data directory
    APIKey TEXT,                            -- Optional API key for auth
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    LastAccessAt TEXT,
    IsActive INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX idx_app_name ON ApplicationIndex(Name);
CREATE INDEX idx_app_active ON ApplicationIndex(IsActive);
```

**Schema: ProviderRegistry**

```sql
CREATE TABLE ProviderRegistry (
    ID TEXT PRIMARY KEY,
    Name TEXT NOT NULL UNIQUE,              -- e.g., "ollama", "llama-cpp", "openai"
    Type TEXT NOT NULL CHECK (Type IN ('local', 'remote', 'hybrid')),
    BaseURL TEXT,                           -- API endpoint
    Status TEXT NOT NULL DEFAULT 'unknown' CHECK (Status IN ('online', 'offline', 'error', 'unknown')),
    Capabilities TEXT NOT NULL,             -- JSON array: ["chat", "completion", "embedding", "vision"]
    Priority INTEGER NOT NULL DEFAULT 100,  -- Lower = higher priority for failover
    Config TEXT,                            -- Provider-specific JSON config
    LastHealthCheck TEXT,
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);
```

**Schema: ModelRegistry**

```sql
CREATE TABLE ModelRegistry (
    ID TEXT PRIMARY KEY,
    ProviderID TEXT NOT NULL REFERENCES ProviderRegistry(ID),
    Name TEXT NOT NULL,                     -- Model identifier
    DisplayName TEXT,
    Category TEXT NOT NULL CHECK (Category IN (
        'thinking', 'writing', 'coding', 'voice', 
        'vision', 'image-gen', 'video-gen', 'embedding'
    )),
    Size TEXT,                              -- e.g., "7B", "13B", "70B"
    Quantization TEXT,                      -- e.g., "Q4_K_M", "Q8_0", "FP16"
    FilePath TEXT,                          -- Local path if downloaded
    FileSize INTEGER,                       -- Bytes
    IsDownloaded INTEGER NOT NULL DEFAULT 0,
    IsDefault INTEGER NOT NULL DEFAULT 0,
    Parameters TEXT,                        -- JSON: context_length, etc.
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(ProviderID, Name)
);

CREATE INDEX idx_model_category ON ModelRegistry(Category);
CREATE INDEX idx_model_provider ON ModelRegistry(ProviderID);
CREATE INDEX idx_model_default ON ModelRegistry(IsDefault);
```

**Schema: PortRegistry**

```sql
CREATE TABLE PortRegistry (
    Port INTEGER PRIMARY KEY,
    ServiceName TEXT NOT NULL,              -- e.g., "ai-bridge-main", "ai-bridge-ws"
    ApplicationID TEXT REFERENCES ApplicationIndex(ID),
    Protocol TEXT NOT NULL DEFAULT 'tcp' CHECK (Protocol IN ('tcp', 'udp')),
    FirewallRuleID TEXT,                    -- OS firewall rule identifier
    FirewallStatus TEXT CHECK (FirewallStatus IN ('added', 'pending', 'failed', 'removed')),
    IsActive INTEGER NOT NULL DEFAULT 1,
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);
```

#### Tier 2: Application Database (`{app-id}/app.db`)

Per-application configuration and project index.

```
Location: {data-dir}/applications/{app-id}/app.db
```

**Tables:**

| Table | Purpose |
|-------|---------|
| AppMeta | Application metadata and settings |
| ProjectIndex | Projects within this application |
| ModelOverrides | App-level model preferences |
| UsageStats | Aggregated usage statistics |

**Schema: AppMeta**

```sql
CREATE TABLE AppMeta (
    Key TEXT PRIMARY KEY,
    Value TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Required keys
INSERT INTO AppMeta (Key, Value) VALUES
('ApplicationID', '{app-id}'),
('ApplicationName', '{app-name}'),
('SchemaVersion', '1'),
('CreatedAt', datetime('now'));
```

**Schema: ProjectIndex**

```sql
CREATE TABLE ProjectIndex (
    ID TEXT PRIMARY KEY,                    -- UUID
    Name TEXT NOT NULL,
    DisplayName TEXT,
    Description TEXT,
    DataPath TEXT NOT NULL,                 -- Relative to app data directory
    DefaultModelThinking TEXT,
    DefaultModelWriting TEXT,
    DefaultModelCoding TEXT,
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    LastAccessAt TEXT,
    IsActive INTEGER NOT NULL DEFAULT 1,
    UNIQUE(Name)
);
```

#### Tier 3: Project Database (`{app-id}/projects/{project-id}.db`)

Per-project conversations, memory, and task history.

```
Location: {data-dir}/applications/{app-id}/projects/{project-id}.db
```

**Tables:**

| Table | Purpose |
|-------|---------|
| ProjectMeta | Project metadata |
| Conversation | Chat sessions |
| Message | Individual messages |
| Memory | RAG memory chunks |
| MemoryEmbedding | Vector embeddings |
| Task | AI task history |
| TaskStep | Task execution steps |
| FileContext | Files provided as context |

**Schema: Conversation**

```sql
CREATE TABLE Conversation (
    ID TEXT PRIMARY KEY,
    Title TEXT,
    SystemPrompt TEXT,
    ModelThinking TEXT,
    ModelWriting TEXT,
    ModelCoding TEXT,
    Status TEXT NOT NULL DEFAULT 'active' CHECK (Status IN ('active', 'archived', 'deleted')),
    MessageCount INTEGER NOT NULL DEFAULT 0,
    TokensUsed INTEGER NOT NULL DEFAULT 0,
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    LastMessageAt TEXT
);

CREATE INDEX idx_conv_status ON Conversation(Status);
CREATE INDEX idx_conv_updated ON Conversation(UpdatedAt DESC);
```

**Schema: Message**

```sql
CREATE TABLE Message (
    ID TEXT PRIMARY KEY,
    ConversationID TEXT NOT NULL REFERENCES Conversation(ID) ON DELETE CASCADE,
    Role TEXT NOT NULL CHECK (Role IN ('system', 'user', 'assistant', 'tool')),
    Content TEXT NOT NULL,
    ContentType TEXT NOT NULL DEFAULT 'text' CHECK (ContentType IN ('text', 'markdown', 'code', 'json', 'image')),
    ModelUsed TEXT,
    ProviderUsed TEXT,
    TokensInput INTEGER,
    TokensOutput INTEGER,
    LatencyMs INTEGER,
    ToolCalls TEXT,                         -- JSON array of tool calls
    ToolResults TEXT,                       -- JSON array of tool results
    Metadata TEXT,                          -- JSON for additional data
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    SequenceNum INTEGER NOT NULL
);

CREATE INDEX idx_msg_conv ON Message(ConversationID, SequenceNum);
CREATE INDEX idx_msg_role ON Message(Role);
```

**Schema: Memory (RAG)**

```sql
CREATE TABLE Memory (
    ID TEXT PRIMARY KEY,
    ConversationID TEXT REFERENCES Conversation(ID) ON DELETE SET NULL,
    SourceType TEXT NOT NULL CHECK (SourceType IN ('message', 'file', 'external', 'summary')),
    SourceID TEXT,                          -- Original source reference
    Content TEXT NOT NULL,
    ContentHash TEXT NOT NULL,              -- SHA256 for deduplication
    ChunkIndex INTEGER,
    ChunkTotal INTEGER,
    Metadata TEXT,                          -- JSON metadata
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    ExpiresAt TEXT,                         -- Optional TTL
    UNIQUE(ContentHash)
);

CREATE INDEX idx_memory_source ON Memory(SourceType, SourceID);
CREATE INDEX idx_memory_hash ON Memory(ContentHash);

-- FTS5 for full-text search
CREATE VIRTUAL TABLE MemoryFTS USING fts5(
    Content,
    content='Memory',
    content_rowid='rowid'
);

-- Sync triggers
CREATE TRIGGER memory_ai AFTER INSERT ON Memory BEGIN
    INSERT INTO MemoryFTS(rowid, Content) VALUES (new.rowid, new.Content);
END;

CREATE TRIGGER memory_ad AFTER DELETE ON Memory BEGIN
    INSERT INTO MemoryFTS(MemoryFTS, rowid, Content) VALUES ('delete', old.rowid, old.Content);
END;
```

**Schema: MemoryEmbedding**

```sql
CREATE TABLE MemoryEmbedding (
    MemoryID TEXT PRIMARY KEY REFERENCES Memory(ID) ON DELETE CASCADE,
    EmbeddingModel TEXT NOT NULL,
    Dimensions INTEGER NOT NULL,
    Vector BLOB NOT NULL,                   -- Float32 array as BLOB
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);
```

**Schema: Task**

```sql
CREATE TABLE Task (
    ID TEXT PRIMARY KEY,
    ConversationID TEXT REFERENCES Conversation(ID),
    Type TEXT NOT NULL CHECK (Type IN (
        'chat', 'completion', 'code-gen', 'code-review',
        'summarize', 'translate', 'embed', 'vision', 'transcribe'
    )),
    Status TEXT NOT NULL DEFAULT 'pending' CHECK (Status IN (
        'pending', 'running', 'completed', 'failed', 'cancelled'
    )),
    Priority INTEGER NOT NULL DEFAULT 100,
    Input TEXT NOT NULL,                    -- JSON input
    Output TEXT,                            -- JSON output
    ModelUsed TEXT,
    ProviderUsed TEXT,
    TokensInput INTEGER,
    TokensOutput INTEGER,
    LatencyMs INTEGER,
    RetryCount INTEGER NOT NULL DEFAULT 0,
    MaxRetries INTEGER NOT NULL DEFAULT 3,
    ErrorCode INTEGER,
    ErrorMessage TEXT,
    ErrorStack TEXT,                        -- Full stack trace
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    StartedAt TEXT,
    CompletedAt TEXT
);

CREATE INDEX idx_task_status ON Task(Status);
CREATE INDEX idx_task_type ON Task(Type);
CREATE INDEX idx_task_conv ON Task(ConversationID);
```

---

## Model Categories

| Category | Purpose | Example Models |
|----------|---------|----------------|
| `thinking` | Complex reasoning, planning, analysis | DeepSeek-R1, Qwen-Thinking, O1 |
| `writing` | Content generation, creative writing | LLaMA-3, Mistral, Claude |
| `coding` | Code generation and review | CodeLLaMA, DeepSeek-Coder, StarCoder |
| `voice` | Speech-to-text transcription | Whisper-Large-v3, Whisper-Turbo |
| `vision` | Image understanding, OCR | LLaVA, Qwen-VL, GPT-4V |
| `image-gen` | Image generation | Stable Diffusion, DALL-E, Flux |
| `video-gen` | Video generation | Runway, Pika, Sora |
| `embedding` | Vector embeddings for RAG | text-embedding-3-small, BGE |

---

## CLI Commands

### Core Commands

```bash
# Start the server
ai-bridge serve [--port 8090] [--host 0.0.0.0] [--config config.json]

# Start with desktop UI (Wails)
ai-bridge ui [--port 8090]

# Health check
ai-bridge health [--url http://localhost:8090]

# Version info
ai-bridge version [--json]
```

### Application Management

```bash
# Register a new application
ai-bridge app register \
    --name "spec-management-software" \
    --display-name "Spec Management Software" \
    [--api-key <key>]

# List registered applications
ai-bridge app list [--json] [--active-only]

# Show application details
ai-bridge app info <app-name>

# Remove application
ai-bridge app remove <app-name> [--force]
```

### Project Management

```bash
# Create project within an application
ai-bridge project create \
    --app "spec-management-software" \
    --name "my-project" \
    [--display-name "My Project"]

# List projects
ai-bridge project list --app <app-name> [--json]

# Show project info
ai-bridge project info --app <app-name> --project <project-name>
```

### Model Management

```bash
# List available models
ai-bridge model list [--category thinking] [--provider ollama] [--json]

# Pull/download a model
ai-bridge model pull <model-name> [--provider ollama]

# Set default model for category
ai-bridge model default --category thinking --model "deepseek-r1:14b"

# Show model info
ai-bridge model info <model-name>

# Remove model
ai-bridge model remove <model-name> [--force]

# Configure model paths
ai-bridge model path add <path> [--category all]
ai-bridge model path list
ai-bridge model path remove <path>
```

### Provider Management

```bash
# List providers
ai-bridge provider list [--json]

# Add provider
ai-bridge provider add \
    --name "ollama-remote" \
    --type local \
    --url "http://192.168.1.100:11434" \
    [--priority 50]

# Test provider connection
ai-bridge provider test <provider-name>

# Set provider priority
ai-bridge provider priority <provider-name> <priority>

# Remove provider
ai-bridge provider remove <provider-name>
```

### Inference Commands

```bash
# Single completion (for testing)
ai-bridge complete \
    --app <app-name> \
    --project <project-name> \
    --model <model-name> \
    --prompt "Your prompt here" \
    [--system "System prompt"] \
    [--json]

# Chat mode (interactive)
ai-bridge chat \
    --app <app-name> \
    --project <project-name> \
    [--model <model-name>] \
    [--conversation <conv-id>]

# Embed text
ai-bridge embed \
    --model "text-embedding-3-small" \
    --input "Text to embed" \
    [--json]
```

### Port & Firewall Management

```bash
# Check port availability
ai-bridge port check <port>

# Add firewall exception
ai-bridge port firewall add <port> --name "AI Bridge"

# Remove firewall exception
ai-bridge port firewall remove <port>

# List managed ports
ai-bridge port list
```

### Database Commands

```bash
# Show database info
ai-bridge db info

# Backup all databases
ai-bridge db backup --output ./backup/

# Restore from backup
ai-bridge db restore --input ./backup/

# Vacuum/optimize databases
ai-bridge db vacuum [--app <app-name>]

# Export conversation
ai-bridge db export conversation \
    --app <app-name> \
    --project <project-name> \
    --conversation <conv-id> \
    --format json \
    --output ./export.json
```

---

## HTTP API Specification

### Base Configuration

```yaml
Base URL: http://localhost:8090/api/v1
Content-Type: application/json
Authentication: Bearer token or X-API-Key header
```

### Endpoints Overview

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Health check |
| GET | `/health/ready` | Readiness probe |
| GET | `/health/live` | Liveness probe |
| POST | `/apps` | Register application |
| GET | `/apps` | List applications |
| GET | `/apps/{appId}` | Get application |
| DELETE | `/apps/{appId}` | Remove application |
| POST | `/apps/{appId}/projects` | Create project |
| GET | `/apps/{appId}/projects` | List projects |
| GET | `/apps/{appId}/projects/{projectId}` | Get project |
| POST | `/apps/{appId}/projects/{projectId}/conversations` | Create conversation |
| GET | `/apps/{appId}/projects/{projectId}/conversations` | List conversations |
| GET | `/apps/{appId}/projects/{projectId}/conversations/{convId}` | Get conversation |
| POST | `/apps/{appId}/projects/{projectId}/conversations/{convId}/messages` | Send message |
| GET | `/apps/{appId}/projects/{projectId}/conversations/{convId}/messages` | Get messages |
| GET | `/providers` | List providers |
| POST | `/providers` | Add provider |
| GET | `/providers/{providerId}/health` | Provider health |
| GET | `/models` | List models |
| POST | `/models/pull` | Pull model |
| DELETE | `/models/{modelId}` | Remove model |
| POST | `/inference/complete` | Single completion |
| POST | `/inference/chat` | Chat completion |
| POST | `/inference/embed` | Generate embedding |
| POST | `/memory/add` | Add to memory |
| POST | `/memory/search` | Search memory |
| DELETE | `/memory/{memoryId}` | Remove memory |

### Request/Response Examples

#### Register Application

```http
POST /api/v1/apps
Content-Type: application/json

{
    "name": "spec-management-software",
    "displayName": "Spec Management Software",
    "description": "Main specification management application",
    "apiKey": "optional-api-key-for-auth"
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": "app_01HXYZ...",
        "name": "spec-management-software",
        "displayName": "Spec Management Software",
        "dataPath": "./data/applications/app_01HXYZ.../",
        "createdAt": "2026-01-30T10:00:00Z"
    }
}
```

#### Create Conversation

```http
POST /api/v1/apps/{appId}/projects/{projectId}/conversations
Content-Type: application/json

{
    "title": "Feature Planning",
    "systemPrompt": "You are a helpful software architect...",
    "modelThinking": "deepseek-r1:14b",
    "modelWriting": "llama3:8b",
    "modelCoding": "codellama:13b"
}
```

#### Send Message (Streaming)

```http
POST /api/v1/apps/{appId}/projects/{projectId}/conversations/{convId}/messages
Content-Type: application/json
Accept: text/event-stream

{
    "role": "user",
    "content": "Explain the repository pattern",
    "modelCategory": "writing",
    "stream": true,
    "includeContext": true,
    "contextLimit": 10
}
```

**SSE Response:**

```
event: message_start
data: {"messageId": "msg_01ABC...", "model": "llama3:8b"}

event: content_delta
data: {"delta": "The repository pattern"}

event: content_delta  
data: {"delta": " is a design pattern..."}

event: message_done
data: {"tokensInput": 150, "tokensOutput": 423, "latencyMs": 2340}
```

---

## WebSocket Protocol

### Connection

```
URL: ws://localhost:8090/ws
Headers:
  - Authorization: Bearer <token>
  - X-App-ID: spec-management-software
  - X-Project-ID: my-project
```

### Message Types

#### Client → Server

```typescript
// Session configuration
{
    "type": "session.configure",
    "conversationId": "conv_01XYZ...",
    "systemPrompt": "You are a helpful assistant...",
    "models": {
        "thinking": "deepseek-r1:14b",
        "writing": "llama3:8b",
        "coding": "codellama:13b"
    }
}

// Chat message
{
    "type": "message.send",
    "content": "Explain microservices architecture",
    "modelCategory": "writing",
    "includeMemory": true,
    "memoryLimit": 5
}

// Cancel generation
{
    "type": "generation.cancel"
}

// Memory operation
{
    "type": "memory.add",
    "content": "Important context information...",
    "sourceType": "external",
    "metadata": { "source": "documentation" }
}

// Memory search
{
    "type": "memory.search",
    "query": "repository pattern",
    "limit": 5,
    "threshold": 0.7
}
```

#### Server → Client

```typescript
// Session ready
{
    "type": "session.ready",
    "sessionId": "sess_01ABC...",
    "conversationId": "conv_01XYZ..."
}

// Generation started
{
    "type": "generation.started",
    "messageId": "msg_01DEF...",
    "model": "llama3:8b",
    "provider": "ollama"
}

// Content streaming
{
    "type": "content.delta",
    "delta": "partial content..."
}

// Thinking indicator (for reasoning models)
{
    "type": "thinking.started"
}

{
    "type": "thinking.delta",
    "delta": "reasoning step..."
}

{
    "type": "thinking.done"
}

// Generation complete
{
    "type": "generation.done",
    "messageId": "msg_01DEF...",
    "tokensInput": 234,
    "tokensOutput": 567,
    "latencyMs": 3400
}

// Error
{
    "type": "error",
    "code": 6001,
    "constant": "ERR_AI_PROVIDER_UNAVAILABLE",
    "message": "Ollama server is not responding",
    "details": { "provider": "ollama", "url": "http://localhost:11434" },
    "retryable": true
}

// Memory results
{
    "type": "memory.results",
    "results": [
        {
            "id": "mem_01GHI...",
            "content": "Relevant memory content...",
            "score": 0.89,
            "sourceType": "message"
        }
    ]
}
```

---

## Error Management

### Error Code Range: 6xxx

| Code | Constant | Description |
|------|----------|-------------|
| 6000 | ERR_AI_GENERAL | General AI Bridge error |
| 6001 | ERR_AI_PROVIDER_UNAVAILABLE | Provider not responding |
| 6002 | ERR_AI_MODEL_NOT_FOUND | Requested model not found |
| 6003 | ERR_AI_MODEL_LOADING | Model failed to load |
| 6004 | ERR_AI_INFERENCE_FAILED | Inference request failed |
| 6005 | ERR_AI_CONTEXT_TOO_LONG | Context exceeds model limit |
| 6006 | ERR_AI_RATE_LIMITED | Provider rate limit hit |
| 6007 | ERR_AI_TIMEOUT | Request timeout |
| 6010 | ERR_AI_APP_NOT_FOUND | Application not registered |
| 6011 | ERR_AI_APP_ALREADY_EXISTS | Application name already taken |
| 6012 | ERR_AI_PROJECT_NOT_FOUND | Project not found |
| 6013 | ERR_AI_CONVERSATION_NOT_FOUND | Conversation not found |
| 6020 | ERR_AI_MEMORY_ADD_FAILED | Failed to add memory |
| 6021 | ERR_AI_MEMORY_SEARCH_FAILED | Memory search failed |
| 6022 | ERR_AI_EMBEDDING_FAILED | Embedding generation failed |
| 6030 | ERR_AI_DB_CONNECTION | Database connection error |
| 6031 | ERR_AI_DB_QUERY | Database query failed |
| 6032 | ERR_AI_DB_MIGRATION | Migration failed |
| 6040 | ERR_AI_PORT_IN_USE | Port already in use |
| 6041 | ERR_AI_FIREWALL_FAILED | Firewall rule operation failed |
| 6050 | ERR_AI_WS_CONNECTION | WebSocket connection error |
| 6051 | ERR_AI_WS_MESSAGE_INVALID | Invalid WebSocket message |
| 6060 | ERR_AI_CONFIG_INVALID | Invalid configuration |
| 6061 | ERR_AI_CONFIG_MISSING | Required config missing |

### Error Response Format

```json
{
    "success": false,
    "error": {
        "code": 6001,
        "constant": "ERR_AI_PROVIDER_UNAVAILABLE",
        "message": "Ollama server is not responding",
        "details": {
            "provider": "ollama",
            "url": "http://localhost:11434",
            "lastAttempt": "2026-01-30T10:00:00Z"
        },
        "retryable": true,
        "stack": [
            "github.com/user/ai-bridge/internal/provider.(*OllamaProvider).HealthCheck:45",
            "github.com/user/ai-bridge/internal/service.(*ProviderService).CheckHealth:123",
            "github.com/user/ai-bridge/internal/api/handler.(*ProviderHandler).GetHealth:67",
            "..."
        ]
    }
}
```

### Stack Trace Capture (Go Implementation)

```go
package errors

import (
    "fmt"
    "runtime"
    "strings"
)

const MaxStackDepth = 40

type StackFrame struct {
    Function string `json:"function"`
    File     string `json:"file"`
    Line     int    `json:"line"`
}

type AppError struct {
    Code       int                    `json:"code"`
    Constant   string                 `json:"constant"`
    Message    string                 `json:"message"`
    Details    map[string]interface{} `json:"details,omitempty"`
    Retryable  bool                   `json:"retryable"`
    Cause      error                  `json:"-"`
    Stack      []StackFrame           `json:"stack,omitempty"`
    StackStr   []string               `json:"stackStr,omitempty"` // Compact format
}

func NewAppError(code int, constant, message string) *AppError {
    err := &AppError{
        Code:     code,
        Constant: constant,
        Message:  message,
        Details:  make(map[string]interface{}),
    }
    err.captureStack()
    return err
}

func (e *AppError) captureStack() {
    pcs := make([]uintptr, MaxStackDepth)
    n := runtime.Callers(3, pcs) // Skip Callers, captureStack, NewAppError
    
    frames := runtime.CallersFrames(pcs[:n])
    e.Stack = make([]StackFrame, 0, n)
    e.StackStr = make([]string, 0, n)
    
    for {
        frame, more := frames.Next()
        
        // Skip runtime internals
        if strings.Contains(frame.Function, "runtime.") {
            if !more {
                break
            }
            continue
        }
        
        sf := StackFrame{
            Function: frame.Function,
            File:     frame.File,
            Line:     frame.Line,
        }
        e.Stack = append(e.Stack, sf)
        
        // Compact format: "package.Function:line"
        shortFunc := frame.Function
        if idx := strings.LastIndex(shortFunc, "/"); idx != -1 {
            shortFunc = shortFunc[idx+1:]
        }
        e.StackStr = append(e.StackStr, fmt.Sprintf("%s:%d", shortFunc, frame.Line))
        
        if !more || len(e.Stack) >= MaxStackDepth {
            break
        }
    }
}

func (e *AppError) Error() string {
    return fmt.Sprintf("[%s] %s", e.Constant, e.Message)
}

func (e *AppError) WithDetails(details map[string]interface{}) *AppError {
    for k, v := range details {
        e.Details[k] = v
    }
    return e
}

func (e *AppError) WithCause(cause error) *AppError {
    e.Cause = cause
    return e
}

func (e *AppError) SetRetryable(retryable bool) *AppError {
    e.Retryable = retryable
    return e
}
```

---

## Logging Standards

### Structured Logging with Source Info

```go
package logging

import (
    "context"
    "log/slog"
    "os"
    "runtime"
    "path/filepath"
)

var logger *slog.Logger

func Init(level slog.Level) {
    handler := slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
        Level:     level,
        AddSource: true, // CRITICAL: Always include source
    })
    logger = slog.New(handler)
}

// Log with explicit source capture (when AddSource isn't enough)
func LogWithSource(ctx context.Context, level slog.Level, msg string, args ...any) {
    pc, file, line, ok := runtime.Caller(1)
    if ok {
        fn := runtime.FuncForPC(pc)
        funcName := ""
        if fn != nil {
            funcName = filepath.Base(fn.Name())
        }
        
        // Prepend source info
        sourceArgs := []any{
            "func", funcName,
            "file", filepath.Base(file),
            "line", line,
        }
        args = append(sourceArgs, args...)
    }
    
    logger.Log(ctx, level, msg, args...)
}

// Convenience methods
func Info(ctx context.Context, msg string, args ...any) {
    LogWithSource(ctx, slog.LevelInfo, msg, args...)
}

func Error(ctx context.Context, msg string, err error, args ...any) {
    if appErr, ok := err.(*AppError); ok {
        args = append(args, 
            "error_code", appErr.Code,
            "error_constant", appErr.Constant,
            "stack", appErr.StackStr,
        )
    }
    args = append(args, "error", err)
    LogWithSource(ctx, slog.LevelError, msg, args...)
}
```

### Log Output Example

```json
{
    "time": "2026-01-30T10:15:30.123Z",
    "level": "ERROR",
    "msg": "Failed to connect to Ollama",
    "func": "OllamaProvider.Connect",
    "file": "ollama.go",
    "line": 87,
    "error_code": 6001,
    "error_constant": "ERR_AI_PROVIDER_UNAVAILABLE",
    "stack": [
        "provider.(*OllamaProvider).Connect:87",
        "service.(*ProviderService).Initialize:45",
        "main.startServer:123"
    ],
    "provider": "ollama",
    "url": "http://localhost:11434"
}
```

---

## Desktop UI (Wails)

### Technology Stack

- **Framework:** Wails 2.x (Go + React)
- **UI Library:** React 18 + TypeScript
- **Styling:** Tailwind CSS + shadcn/ui
- **State:** Zustand

### UI Sections

```
┌─────────────────────────────────────────────────────────────────┐
│  AI Bridge                                          [─] [□] [×] │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────┐                                                   │
│  │ Dashboard│  ┌─────────────────────────────────────────────┐  │
│  ├──────────┤  │                                             │  │
│  │ Models   │  │     Dashboard / Models / Providers /        │  │
│  ├──────────┤  │     Applications / Settings / Logs          │  │
│  │ Providers│  │                                             │  │
│  ├──────────┤  │                                             │  │
│  │ Apps     │  │     [Content Area]                          │  │
│  ├──────────┤  │                                             │  │
│  │ Settings │  │                                             │  │
│  ├──────────┤  │                                             │  │
│  │ Logs     │  │                                             │  │
│  └──────────┘  └─────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────┤
│  Status: ● Online | Port: 8090 | Models: 12 | GPU: NVIDIA RTX  │
└─────────────────────────────────────────────────────────────────┘
```

### Settings Panel Features

1. **Model Management**
   - Browse available models by category
   - Download/remove models
   - Set default models per category
   - Configure model paths

2. **Provider Configuration**
   - Add/edit/remove providers
   - Test connections
   - Set failover priorities

3. **Server Settings**
   - Port configuration
   - Firewall management
   - Concurrent request limits
   - GPU/CPU preferences

4. **Application Management**
   - View registered apps
   - Per-app model overrides
   - Usage statistics

5. **Logging & Debugging**
   - Real-time log viewer
   - Log level configuration
   - Error investigation

---

## Health Check System

### Endpoints

```http
GET /health
{
    "status": "healthy",
    "version": "1.0.0",
    "uptime": "4h23m",
    "checks": {
        "database": "ok",
        "providers": {
            "ollama": "online",
            "llama-cpp": "offline"
        },
        "memory": {
            "used": "2.3GB",
            "available": "13.7GB"
        },
        "gpu": {
            "available": true,
            "name": "NVIDIA RTX 4090",
            "memory": "24GB"
        }
    }
}

GET /health/ready
{
    "ready": true,
    "database": true,
    "atLeastOneProvider": true
}

GET /health/live
{
    "alive": true
}
```

### Provider Health Monitoring

```go
type HealthMonitor struct {
    checkInterval time.Duration
    providers     map[string]*ProviderHealth
    mu            sync.RWMutex
}

type ProviderHealth struct {
    Name          string
    Status        string // online, offline, degraded
    LastCheck     time.Time
    LastSuccess   time.Time
    ResponseTime  time.Duration
    ErrorCount    int
    ConsecutiveFails int
}

func (m *HealthMonitor) StartMonitoring(ctx context.Context) {
    ticker := time.NewTicker(m.checkInterval)
    defer ticker.Stop()
    
    for {
        select {
        case <-ctx.Done():
            return
        case <-ticker.C:
            m.checkAllProviders(ctx)
        }
    }
}
```

---

## Port & Firewall Management

### Cross-Platform Implementation

```go
package firewall

import (
    "context"
    "fmt"
    "os/exec"
    "runtime"
)

type FirewallManager interface {
    AddException(ctx context.Context, port int, name string) error
    RemoveException(ctx context.Context, port int) error
    CheckException(ctx context.Context, port int) (bool, error)
}

// Windows implementation
type WindowsFirewall struct{}

func (w *WindowsFirewall) AddException(ctx context.Context, port int, name string) error {
    cmd := exec.CommandContext(ctx, "netsh", "advfirewall", "firewall", "add", "rule",
        fmt.Sprintf("name=%s", name),
        "dir=in",
        "action=allow",
        "protocol=tcp",
        fmt.Sprintf("localport=%d", port),
    )
    return cmd.Run()
}

func (w *WindowsFirewall) RemoveException(ctx context.Context, port int) error {
    // Find and remove rule by port
    cmd := exec.CommandContext(ctx, "netsh", "advfirewall", "firewall", "delete", "rule",
        fmt.Sprintf("localport=%d", port),
        "protocol=tcp",
    )
    return cmd.Run()
}

// Linux implementation using iptables or ufw
type LinuxFirewall struct {
    useUFW bool
}

func (l *LinuxFirewall) AddException(ctx context.Context, port int, name string) error {
    if l.useUFW {
        cmd := exec.CommandContext(ctx, "ufw", "allow", fmt.Sprintf("%d/tcp", port))
        return cmd.Run()
    }
    // iptables fallback
    cmd := exec.CommandContext(ctx, "iptables", "-A", "INPUT", "-p", "tcp",
        "--dport", fmt.Sprintf("%d", port), "-j", "ACCEPT")
    return cmd.Run()
}

// Factory
func NewFirewallManager() FirewallManager {
    switch runtime.GOOS {
    case "windows":
        return &WindowsFirewall{}
    case "linux":
        return &LinuxFirewall{useUFW: hasUFW()}
    default:
        return &NoOpFirewall{} // macOS, etc.
    }
}
```

---

## Project Structure

```
ai-bridge/
├── cmd/
│   └── ai-bridge/
│       └── main.go              # Entry point
├── internal/
│   ├── api/
│   │   ├── handler/             # HTTP handlers
│   │   ├── middleware/          # Auth, logging, recovery
│   │   ├── router.go
│   │   └── websocket/           # WebSocket handlers
│   ├── cli/                     # Cobra commands
│   ├── config/                  # Configuration loading
│   ├── database/
│   │   ├── migrations/          # SQL migrations
│   │   ├── repository/          # Data access layer
│   │   └── sqlite.go            # Connection management
│   ├── errors/                  # Error types and codes
│   ├── firewall/                # Port/firewall management
│   ├── inference/               # Inference orchestration
│   ├── logging/                 # Structured logging
│   ├── memory/                  # RAG memory management
│   ├── model/                   # Domain models
│   ├── provider/                # LLM provider adapters
│   │   ├── ollama.go
│   │   ├── llamacpp.go
│   │   ├── llamaswap.go
│   │   └── openai.go
│   └── service/                 # Business logic
├── frontend/                    # Wails React frontend
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   └── lib/
│   └── package.json
├── migrations/                  # Database migrations
├── config/
│   └── default.json            # Default configuration
├── go.mod
├── go.sum
├── wails.json                  # Wails configuration
└── README.md
```

---

## End-to-End Tests

### Test Scenarios

| # | Scenario | Priority |
|---|----------|----------|
| 1 | Application registration and project creation | Critical |
| 2 | Conversation with streaming response | Critical |
| 3 | Model switching mid-conversation | High |
| 4 | Provider failover on error | High |
| 5 | Memory/RAG context injection | High |
| 6 | WebSocket session lifecycle | High |
| 7 | Concurrent inference requests | Medium |
| 8 | Database backup and restore | Medium |
| 9 | Firewall rule management | Medium |
| 10 | Health check under load | Medium |

---

## Related Specifications

- [Voice CLI Microservice](./10-voice-cli.md)
- [Nexus-Flow Standalone](./09-nexus-flow-standalone-architecture.md)
- [AI Integration Feature](../../05-features/06-ai-integration/00-overview.md)
- [Error Management](../../06-error-management/00-overview.md)
- [Database Design](../../07-database-design/00-overview.md)
