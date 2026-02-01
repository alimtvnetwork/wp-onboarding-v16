# SM-010: Golang Backend Implementation Specification

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Status:** Ready for Implementation  
> **Priority:** 1 (Critical Path)

---

## Overview

This document specifies the implementation plan for the Golang backend service that powers the Spec Management Software.

---

## Technology Stack

| Component | Technology | Version |
|-----------|------------|---------|
| Language | Go | 1.21+ |
| Web Framework | Chi or Echo | Latest |
| Database | SQLite | 3.x |
| ORM | GORM | v2 |
| Auth | Argon2id + JWT | - |
| Config | Viper | v1.18+ |
| CLI | Cobra | v1.8+ |
| Logging | Zap | v1.27+ |
| Metrics | Prometheus client | v1.19+ |
| Tracing | OpenTelemetry | v1.24+ |

---

## Project Structure

```
cmd/
├── server/
│   └── main.go              # Main API server
├── gsearch/
│   └── main.go              # Search CLI
└── brun/
    └── main.go              # Build runner CLI

internal/
├── config/
│   ├── config.go            # Viper configuration
│   └── defaults.go          # Default values
├── database/
│   ├── sqlite.go            # SQLite connection
│   ├── migrations/          # SQL migrations
│   └── models/              # GORM models
├── auth/
│   ├── argon2.go            # Password hashing
│   ├── jwt.go               # Token management
│   ├── middleware.go        # Auth middleware
│   └── service.go           # Auth business logic
├── files/
│   ├── manager.go           # File operations
│   ├── path.go              # Path validation
│   └── sync.go              # Filesystem sync
├── history/
│   ├── git.go               # Git operations
│   ├── snapshot.go          # .history management
│   └── diff.go              # Diff generation
├── ai/
│   ├── provider.go          # LLM abstraction
│   ├── ollama.go            # Ollama client
│   ├── llamacpp.go          # llama.cpp client
│   ├── chain.go             # AI chain orchestration
│   └── models.go            # Model registry
├── rag/
│   ├── embedder.go          # Text embeddings
│   ├── vectordb.go          # Vector storage
│   ├── retriever.go         # Context retrieval
│   └── context.go           # Context assembly
├── pipeline/
│   ├── executor.go          # Pipeline runner
│   ├── triggers.go          # Event triggers
│   ├── steps.go             # Step definitions
│   └── state.go             # State machine
├── realtime/
│   ├── websocket.go         # WebSocket handler
│   ├── sse.go               # Server-sent events
│   └── pubsub.go            # Event distribution
├── api/
│   ├── router.go            # Route definitions
│   ├── middleware/          # HTTP middleware
│   ├── handlers/            # Request handlers
│   └── responses/           # Response types
└── errors/
    ├── codes.go             # Error code registry
    └── errors.go            # Error types

pkg/
├── validator/               # Input validation
├── crypto/                  # Encryption utilities
└── logger/                  # Structured logging

configs/
├── config.yaml              # Default config
└── config.example.yaml      # Example config

scripts/
├── migrate.sh               # Run migrations
└── seed.sh                  # Seed data

deployments/
├── Dockerfile
├── docker-compose.yaml
└── systemd/
    └── spec-mgmt.service
```

---

## Implementation Phases

### Phase 1: Foundation (Week 1-2)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 1.1 | Project structure setup | This document |
| 1.2 | Configuration system | `04-coding-guidelines/02-configuration-manifest.md` |
| 1.3 | Database connection & migrations | `07-database-design/01-schema.md` |
| 1.4 | Logging & observability setup | `05-features/17-monitoring/` |
| 1.5 | Error handling framework | `06-error-management/` |

### Phase 2: Authentication (Week 2-3)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 2.1 | User model & migrations | `01-authentication/01-authentication.md` |
| 2.2 | Argon2id password hashing | `01-authentication/01-authentication.md#7.2` |
| 2.3 | JWT token generation & validation | `01-authentication/01-authentication.md#7.5` |
| 2.4 | Auth middleware | `01-authentication/01-authentication.md#7.8` |
| 2.5 | Login/register endpoints | `01-authentication/01-authentication.md#7.3-7.4` |
| 2.6 | Brute force protection | `01-authentication/01-authentication.md#7.4.3` |

### Phase 3: File Management (Week 3-4)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 3.1 | Path validation & traversal prevention | `02-file-management/02-path-manager.md` |
| 3.2 | File CRUD operations | `02-file-management/01-file-operations.md` |
| 3.3 | Folder tree building | `02-file-management/03-folder-tree.md` |
| 3.4 | Filesystem sync | `02-file-management/04-folder-sync.md` |

### Phase 4: History System (Week 4-5)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 4.1 | Git integration | `07-history-system/01-git-integration.md` |
| 4.2 | Snapshot management | `07-history-system/02-history-system.md` |
| 4.3 | Diff generation | `07-history-system/04-file-history-comparison.md` |

### Phase 5: AI Integration (Week 5-7)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 5.1 | LLM provider abstraction | `06-ai-integration/01-ai-integration.md` |
| 5.2 | Ollama client | `06-ai-integration/07-llm-server-management.md` |
| 5.3 | llama.cpp client | `06-ai-integration/07-llm-server-management.md` |
| 5.4 | Model registry | `06-ai-integration/01-ai-integration.md` |
| 5.5 | Streaming responses | `06-ai-integration/06-llm-live-logging.md` |
| 5.6 | AI chain orchestration | `06-ai-integration/03-instruction-system.md` |

### Phase 6: RAG System (Week 7-8)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 6.1 | Text embedding service | `09-knowledge-memory/01-rag-system.md` |
| 6.2 | Vector database integration | `09-knowledge-memory/04-vector-database-plan.md` |
| 6.3 | Context retrieval | `09-knowledge-memory/05-vector-search-service.md` |
| 6.4 | Context window management | `09-knowledge-memory/06-context-window-manager.md` |

### Phase 7: Automation Pipeline (Week 8-10)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 7.1 | Pipeline executor | `27-automation-pipeline/` |
| 7.2 | Trigger system | `29-trigger-event-system/` |
| 7.3 | Step definitions | `27-automation-pipeline/` |
| 7.4 | State machine | `27-automation-pipeline/` |

### Phase 8: CLI Tools (Week 10-12)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 8.1 | gsearch CLI | `22-golang-search-cli/` |
| 8.2 | brun CLI | `23-build-runner-cli/` |

### Phase 9: Realtime & Polish (Week 12-14)

| Task | Description | Spec Reference |
|------|-------------|----------------|
| 9.1 | WebSocket handler | `18-realtime/` |
| 9.2 | Server-sent events | `18-realtime/` |
| 9.3 | Integration testing | `20-testing/` |
| 9.4 | Performance optimization | `19-performance/` |

---

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | User registration |
| POST | `/api/v1/auth/login` | User login |
| POST | `/api/v1/auth/logout` | User logout |
| POST | `/api/v1/auth/refresh` | Token refresh |
| GET | `/api/v1/auth/me` | Current user info |

### Files

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/files` | List files |
| GET | `/api/v1/files/*path` | Get file content |
| POST | `/api/v1/files/*path` | Create file |
| PUT | `/api/v1/files/*path` | Update file |
| DELETE | `/api/v1/files/*path` | Delete file |
| GET | `/api/v1/tree` | Get folder tree |

### History

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/history/:path` | Get file history |
| GET | `/api/v1/history/:path/:version` | Get specific version |
| POST | `/api/v1/history/:path/restore/:version` | Restore version |

### AI

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/ai/chain` | Execute AI chain |
| POST | `/api/v1/ai/transcribe` | Transcribe audio |
| GET | `/api/v1/ai/models` | List available models |
| POST | `/api/v1/ai/stream` | Stream AI response |

### RAG

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/rag/index` | Index content |
| POST | `/api/v1/rag/search` | Search knowledge base |
| DELETE | `/api/v1/rag/index/:id` | Remove from index |

### Pipeline

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/pipeline/execute` | Execute pipeline |
| GET | `/api/v1/pipeline/status/:id` | Get execution status |
| POST | `/api/v1/pipeline/cancel/:id` | Cancel execution |

---

## Configuration Schema

```yaml
# config.yaml
server:
  host: "0.0.0.0"
  port: 8080
  readTimeout: 30s
  writeTimeout: 30s

database:
  path: "./data/spec-mgmt.db"
  maxOpenConns: 25
  maxIdleConns: 5

auth:
  jwtSecret: "${JWT_SECRET}"
  accessTokenTTL: 15m
  refreshTokenTTL: 168h  # 7 days
  bcryptCost: 12

ai:
  backend: "ollama"  # or "llama-cpp"
  ollama:
    baseUrl: "http://localhost:11434"
  llama:
    serverPath: "/usr/local/bin/llama-server"
  models:
    rootPaths:
      - "/models"
  defaults:
    thinkingModelId: ""
    writingModelId: ""
    voiceModelId: ""
    codingModelId: ""
  maxConcurrentModels: 3
  contextSize: 8192
  gpuLayers: 35

rag:
  embeddingModel: "nomic-embed-text"
  chunkSize: 512
  chunkOverlap: 50
  topK: 10

files:
  workDirectory: "./workspace"
  maxFileSize: 10485760  # 10 MB
  allowedExtensions:
    - ".md"
    - ".json"
    - ".yaml"
    - ".txt"

history:
  snapshotDir: ".history"
  maxSnapshots: 100
  autoCommit: true

logging:
  level: "info"
  format: "json"
  output: "stdout"

metrics:
  enabled: true
  port: 9090
```

---

## Error Codes

| Range | Module |
|-------|--------|
| 1000-1999 | General/System |
| 2000-2999 | Authentication |
| 3000-3999 | File Management |
| 4000-4999 | AI Integration |
| 5000-5999 | RAG/Knowledge |
| 6000-6999 | gsearch CLI |
| 7000-7999 | brun CLI |
| 8000-8999 | Automation Pipeline |
| 9000-9999 | Realtime |

See `06-error-management/` for complete error code definitions.

---

## Testing Strategy

| Test Type | Coverage Target | Framework |
|-----------|-----------------|-----------|
| Unit tests | 80% | `testing` + `testify` |
| Integration tests | Key flows | `testing` + `testcontainers` |
| E2E tests | Critical paths | Postman/Newman |

Test pyramid: 60% integration, 30% unit, 10% E2E

---

## Deployment

### Docker

```dockerfile
FROM golang:1.21-alpine AS builder
WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=1 go build -o server ./cmd/server

FROM alpine:3.19
RUN apk add --no-cache sqlite-libs
COPY --from=builder /app/server /usr/local/bin/
COPY configs/config.yaml /etc/spec-mgmt/
EXPOSE 8080
CMD ["server", "--config", "/etc/spec-mgmt/config.yaml"]
```

### Health Check

```
GET /health
{
  "status": "healthy",
  "version": "1.0.0",
  "database": "connected",
  "ai": "available"
}
```

---

## Dependencies

| Dependency | Purpose |
|------------|---------|
| SM-001 | Authentication spec |
| SM-002 | AI Integration spec |
| SM-003 | RAG System spec |
| SM-004 | Automation Pipeline spec |
| SM-005 | File Management spec |
| SM-006 | History System spec |
| SM-007 | State Management spec |
| SM-008 | Realtime spec |

All specs are complete. ✅

---

## Acceptance Criteria

- [ ] All endpoints return correct HTTP status codes
- [ ] Authentication works with JWT tokens
- [ ] File operations respect path security
- [ ] AI chains execute with model selection
- [ ] RAG retrieval returns relevant context
- [ ] Pipelines execute with state tracking
- [ ] CLI tools work independently
- [ ] Metrics exposed on /metrics
- [ ] Health check returns status
- [ ] All tests pass with 80%+ coverage

---

*Ready for implementation handoff to Go developer or AI model.*
