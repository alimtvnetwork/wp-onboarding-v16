# Microservices Architecture

**Version:** 2.0.0  
**Status:** Active  
**Updated:** 2026-01-30  

---

## Overview

This folder contains specifications for all SpecBuilder Pro microservices. Each service is a standalone Go application that communicates via HTTP/JSON APIs.

**Cross-References:**
- [Shared Packages](../13-shared-packages/00-overview.md)
- [Error Management](../06-error-management/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)

---

## Service Registry

| Phase | Service | Port | Description | Spec |
|-------|---------|------|-------------|------|
| 2 | Gateway | 8080 | API gateway, routing, auth | [01-gateway.md](./01-gateway.md) |
| 3 | SpecManager | 8081 | Spec CRUD, file operations | [02-specmanager.md](./02-specmanager.md) |
| 4 | Chronicle | 8083 | Git operations, history | [03-chronicle.md](./03-chronicle.md) |
| 5 | AI-Bridge | 8082 | LLM abstraction | [04-ai-bridge.md](./04-ai-bridge.md) |
| 6 | Scout | 8093 | Search, RAG | [05-scout.md](./05-scout.md) |
| 7 | Nexus-Flow | 9000 | Execution engine, CLI | [06-nexus-flow.md](./06-nexus-flow.md) |
| 8 | Voice-CLI | 8084 | Voice recording, transcription | [10-voice-cli.md](./10-voice-cli.md) |

---

## OpenAPI Specifications

Complete REST API and WebSocket protocol documentation for each service:

| Service | Port | OpenAPI Spec | Error Range |
|---------|------|--------------|-------------|
| Gateway | 8080 | [07-gateway-openapi.md](./07-gateway-openapi.md) | 2xxx |
| SpecManager | 8081 | [20-specmanager-openapi.md](./20-specmanager-openapi.md) | 3xxx |
| AI-Bridge | 8082 | [13-ai-bridge-openapi.md](./13-ai-bridge-openapi.md) | 6xxx |
| Chronicle | 8083 | [19-chronicle-openapi.md](./19-chronicle-openapi.md) | 4xxx |
| Voice-CLI | 8084 | [16-voice-cli-openapi.md](./16-voice-cli-openapi.md) | 11xxx |
| Scout | 8093 | [18-scout-openapi.md](./18-scout-openapi.md) | 5xxx |
| Nexus-Flow | 9000 | [17-nexus-flow-openapi.md](./17-nexus-flow-openapi.md) | 10xxx |

### OpenAPI Coverage Summary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        OpenAPI Specification Matrix                          │
├──────────────┬───────────┬───────────┬───────────┬──────────────────────────┤
│   Service    │   REST    │ WebSocket │    SSE    │     Key Features         │
├──────────────┼───────────┼───────────┼───────────┼──────────────────────────┤
│ Gateway      │    ✓      │     -     │     -     │ Auth, routing, proxy     │
│ SpecManager  │    ✓      │     -     │     -     │ CRUD, files, templates   │
│ AI-Bridge    │    ✓      │     -     │     ✓     │ LLM streaming, providers │
│ Chronicle    │    ✓      │     -     │     -     │ Commits, diffs, rollback │
│ Voice-CLI    │    ✓      │     ✓     │     -     │ PCM16 audio, VAD         │
│ Scout        │    ✓      │     -     │     -     │ Hybrid search, RAG       │
│ Nexus-Flow   │    ✓      │     ✓     │     -     │ Stage events, HiL        │
└──────────────┴───────────┴───────────┴───────────┴──────────────────────────┘
```

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              SpecBuilder Pro                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌───────────────────────────────────────────────────────────────────────┐ │
│   │                         Frontend (React)                               │ │
│   │                         localhost:5173                                 │ │
│   └───────────────────────────────────────────────────────────────────────┘ │
│                                    │                                         │
│                                    ▼                                         │
│   ┌───────────────────────────────────────────────────────────────────────┐ │
│   │                        Gateway :8080                                   │ │
│   │   ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────────────┐ │ │
│   │   │Recovery │→│RequestID│→│ Logging │→│  CORS   │→│   Rate Limit    │ │ │
│   │   └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────────────┘ │ │
│   │                                │                                       │ │
│   │                        ┌───────┴───────┐                               │ │
│   │                        │ Authentication│                               │ │
│   │                        └───────┬───────┘                               │ │
│   │                                │                                       │ │
│   │                        ┌───────┴───────┐                               │ │
│   │                        │    Router     │                               │ │
│   │                        └───────┬───────┘                               │ │
│   └────────────────────────────────┼───────────────────────────────────────┘ │
│                                    │                                         │
│   ┌────────────┬────────────┬──────┴──────┬────────────┬────────────┐       │
│   │            │            │             │            │            │       │
│   ▼            ▼            ▼             ▼            ▼            ▼       │
│ ┌────────┐ ┌────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │
│ │SpecMgr│ │Chronice│ │AI-Bridge │ │  Scout   │ │Voice-CLI │ │Nexus-Flow│  │
│ │ :8081 │ │ :8083  │ │  :8082   │ │  :8093   │ │  :8084   │ │  :9000   │  │
│ │       │ │        │ │          │ │          │ │          │ │          │  │
│ │• CRUD │ │• Git   │ │• LLaMA   │ │• FTS5    │ │• Record  │ │• Blocks  │  │
│ │• Files│ │• Hist  │ │• Ollama  │ │• RAG     │ │• VAD     │ │• WebSock │  │
│ │• Valid│ │• Diff  │ │• SSE     │ │• Embed   │ │• STT     │ │• CLI     │  │
│ └───┬────┘ └───┬────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘  │
│     │          │           │            │            │            │        │
│     └──────────┴───────────┴────────────┴────────────┴────────────┘        │
│                                    │                                        │
│   ┌────────────────────────────────┴────────────────────────────────────┐   │
│   │                     Shared Packages (pkg/)                          │   │
│   │   ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐      │   │
│   │   │ errors  │ │  types  │ │ logging │ │ config  │ │database │      │   │
│   │   └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘      │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
│                                    │                                         │
│   ┌────────────────────────────────┴────────────────────────────────────┐   │
│   │                          SQLite Databases                            │   │
│   │  ┌─────────────┐ ┌──────────────┐ ┌───────────────┐ ┌─────────────┐ │   │
│   │  │settings.db  │ │ projects.db  │ │{project-id}.db│ │{conv-id}.db │ │   │
│   │  │  (Global)   │ │   (Global)   │ │ (Per-Project) │ │  (Per-Conv) │ │   │
│   │  └─────────────┘ └──────────────┘ └───────────────┘ └─────────────┘ │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## Error Code Ranges

Each service has a dedicated error code range for clear error attribution:

| Service | Range | Example Errors |
|---------|-------|----------------|
| Gateway | 2xxx | Auth failures, rate limiting, proxy errors |
| SpecManager | 3xxx | Validation, CRUD, file operations |
| Chronicle | 4xxx | Git operations, diff, history |
| Scout | 5xxx | Search, indexing, embedding |
| AI-Bridge | 6xxx | Provider errors, streaming, model |
| Nexus-Flow | 10xxx | Execution, stages, connections |
| Voice-CLI | 11xxx | Recording, VAD, transcription |

---

## Critical Requirements

### 1. Logging Requirements (MANDATORY)

All services MUST configure logging with source information:

```go
logger := logging.New(
    logging.WithLevel(cfg.Logging.Level),
    logging.WithFormat(cfg.Logging.Format),
    logging.WithSource(true), // MANDATORY: function name, file, line
    logging.WithService("servicename", "1.0.0"),
)
```

**Every log entry MUST include:**
- `timestamp` - ISO 8601 format
- `level` - DEBUG, INFO, WARN, ERROR
- `msg` - Log message
- `source.function` - Full function name (e.g., `specmgr.(*SpecService).CreateSpec`)
- `source.file` - Full file path
- `source.line` - Line number

Example log entry:
```json
{
  "timestamp": "2026-01-30T10:00:00Z",
  "level": "INFO",
  "msg": "spec created",
  "source": {
    "function": "specmgr.(*SpecService).CreateSpec",
    "file": "/app/internal/specmgr/service.go",
    "line": 142
  },
  "service": "specmgr",
  "version": "1.0.0",
  "request_id": "abc-123",
  "spec_id": "def-456"
}
```

### 2. Error Requirements (MANDATORY)

All errors MUST capture stack traces at creation time:

```go
// Every error factory captures stack trace automatically
err := errors.New(errors.ErrValidationRequired, "email is required")
// err.StackTrace contains full call stack

// Stack trace is included in JSON serialization
{
  "code": 1001,
  "constant": "ERR_VALIDATION_REQUIRED",
  "message": "email is required",
  "stackTrace": [
    {"function": "specmgr.CreateSpec", "file": "/app/handler.go", "line": 42},
    {"function": "http.HandlerFunc.ServeHTTP", "file": "/go/net/http/server.go", "line": 2136}
  ]
}
```

### 3. Context Propagation

All services MUST propagate context for:
- Request cancellation
- Timeout enforcement
- Trace correlation (request_id, correlation_id)

---

## Service Communication

### HTTP Headers

All inter-service requests include:

| Header | Description |
|--------|-------------|
| `X-Request-ID` | Unique request identifier |
| `X-Correlation-ID` | Trace across services |
| `X-Forwarded-For` | Original client IP |
| `X-Forwarded-Host` | Original host |
| `Authorization` | Forwarded auth token |

### Error Responses

All services return consistent error format:

```json
{
  "success": false,
  "error": {
    "code": 3006,
    "constant": "ERR_DATABASE_NOT_FOUND",
    "message": "Spec not found: abc-123",
    "details": {
      "resource": "Spec",
      "identifier": "abc-123"
    },
    "retryable": false,
    "stackTrace": [...]
  }
}
```

---

## Deployment

### Development

```bash
# Start all services
make dev

# Or individually
go run ./cmd/gateway &
go run ./cmd/specmgr &
go run ./cmd/chronicle &
go run ./cmd/aibridge &
go run ./cmd/scout &
go run ./cmd/nexusflow &
go run ./cmd/voicecli &
```

### Production

```bash
# Build all
make build

# Run with config
./bin/gateway --config /etc/specbuilder/gateway.yaml
```

---

## Service Specifications

### Core Services

1. [Gateway](./01-gateway.md) - API gateway, routing, authentication
2. [SpecManager](./02-specmanager.md) - Spec CRUD operations
3. [Chronicle](./03-chronicle.md) - Git and history management
4. [AI-Bridge](./04-ai-bridge.md) - LLM abstraction layer
5. [Scout](./05-scout.md) - Search and RAG
6. [Nexus-Flow](./06-nexus-flow.md) - Execution engine
7. [Voice-CLI](./10-voice-cli.md) - Voice recording and transcription

### OpenAPI Specifications

7. [Gateway OpenAPI](./07-gateway-openapi.md) - REST API documentation
13. [AI-Bridge OpenAPI](./13-ai-bridge-openapi.md) - LLM API with SSE streaming
16. [Voice-CLI OpenAPI](./16-voice-cli-openapi.md) - WebSocket audio protocol
17. [Nexus-Flow OpenAPI](./17-nexus-flow-openapi.md) - Execution API with WebSocket
18. [Scout OpenAPI](./18-scout-openapi.md) - Hybrid search and RAG API
19. [Chronicle OpenAPI](./19-chronicle-openapi.md) - Version control and audit API
20. [SpecManager OpenAPI](./20-specmanager-openapi.md) - Spec CRUD and file operations API

### Testing & Quality

21. [Integration Tests](./21-integration-tests.md) - End-to-end microservice communication tests

---

## See Also

- [Shared Packages](../13-shared-packages/00-overview.md) - Common Go modules
- [Error Management](../06-error-management/00-overview.md) - Error handling patterns
- [Database Design](../07-database-design/00-overview.md) - SQLite schemas
- [Testing Strategy](../05-features/20-testing/01-test-strategy.md) - Test pyramid and coverage
