# Implementation Guide

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Build order and dependency graph for implementing WP Plugin Builder CLI.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [Testing Strategy](./13-testing-strategy.md)
- [BRun Implementation Guide](../brun-cli/14-implementation-guide.md)

---

## Implementation Phases

### Phase 1: Foundation

**Goal:** Core CLI structure, configuration, and root database.

| Task | Description | Dependencies | Est. Hours |
|------|-------------|--------------|------------|
| 1.1 | Project scaffolding (Go modules, directory structure) | None | 2 |
| 1.2 | CLI framework setup (Cobra, Viper) | 1.1 | 4 |
| 1.3 | Configuration system with seeding | 1.2 | 6 |
| 1.4 | Root database schema and migrations | 1.1 | 4 |
| 1.5 | Shared error package | 1.1 | 4 |
| 1.6 | Logging system | 1.5 | 3 |

**Deliverables:**
- `wpb --version` works
- `wpb config init` creates config file
- Root database created and migrated
- Errors include stack traces

---

### Phase 2: Project Management

**Goal:** Complete project lifecycle operations.

| Task | Description | Dependencies | Est. Hours |
|------|-------------|--------------|------------|
| 2.1 | Project creation | Phase 1 | 6 |
| 2.2 | Project database setup | 2.1 | 4 |
| 2.3 | Project list/open/delete | 2.1 | 4 |
| 2.4 | Project clone | 2.2 | 4 |
| 2.5 | Project export (SQLite) | 2.2 | 3 |
| 2.6 | Project export (Zip) | 2.5 | 3 |
| 2.7 | Project import | 2.6 | 4 |

**Deliverables:**
- `wpb project create/list/delete/clone` work
- `wpb project export/import` work
- Project databases isolated and portable

---

### Phase 3: AI Bridge Integration

**Goal:** Connect to AI Bridge for embeddings and generation.

| Task | Description | Dependencies | Est. Hours |
|------|-------------|--------------|------------|
| 3.1 | AI Bridge client | Phase 1 | 4 |
| 3.2 | Embedding API integration | 3.1 | 4 |
| 3.3 | Generation API integration | 3.1 | 4 |
| 3.4 | Streaming support | 3.3 | 4 |
| 3.5 | Retry logic and error handling | 3.1 | 3 |

**Deliverables:**
- AI Bridge client with embedding/generation
- Configurable endpoint and timeout
- Retry on transient failures

---

### Phase 4: RAG System

**Goal:** Vector storage, indexing, and retrieval.

| Task | Description | Dependencies | Est. Hours |
|------|-------------|--------------|------------|
| 4.1 | Text chunking | Phase 1 | 4 |
| 4.2 | Vector storage (sqlite-vec) | Phase 2 | 6 |
| 4.3 | Embedding pipeline | 3.2, 4.1 | 4 |
| 4.4 | Vector search | 4.2 | 4 |
| 4.5 | Context building | 4.4 | 3 |

**Deliverables:**
- Documents chunked and embedded
- Vector similarity search working
- Context retrieved for prompts

---

### Phase 5: Preset Learning

**Goal:** Import and manage learning presets.

| Task | Description | Dependencies | Est. Hours |
|------|-------------|--------------|------------|
| 5.1 | Preset markdown parser | Phase 1 | 4 |
| 5.2 | Preset import/storage | 5.1, Phase 4 | 4 |
| 5.3 | Preset list/export | 5.2 | 3 |
| 5.4 | Apply preset to project | 5.2 | 4 |
| 5.5 | Built-in presets | 5.2 | 4 |
| 5.6 | Auto-load configuration | 5.4 | 2 |

**Deliverables:**
- `wpb preset import/list/apply` work
- Built-in WordPress presets
- Presets copied to projects

---

### Phase 6: Spec Processing

**Goal:** Import and parse specifications.

| Task | Description | Dependencies | Est. Hours |
|------|-------------|--------------|------------|
| 6.1 | Markdown spec parser | Phase 1 | 6 |
| 6.2 | Component extraction | 6.1 | 4 |
| 6.3 | Folder/zip support | 6.1 | 4 |
| 6.4 | Spec storage and indexing | 6.1, Phase 4 | 4 |
| 6.5 | PRD processing | 6.1 | 4 |

**Deliverables:**
- `wpb spec import` works
- Components extracted from specs
- Specs indexed for RAG

---

### Phase 7: Code Generation

**Goal:** AI-powered PHP code generation.

| Task | Description | Dependencies | Est. Hours |
|------|-------------|--------------|------------|
| 7.1 | System prompt builder | Phase 1 | 4 |
| 7.2 | User prompt builder | 7.1, Phase 6 | 4 |
| 7.3 | Response parser | 7.2 | 4 |
| 7.4 | File writer | 7.3 | 4 |
| 7.5 | PHP syntax validation | 7.3 | 3 |
| 7.6 | WordPress standards check | 7.3 | 4 |
| 7.7 | Streaming generation | 7.3, 3.4 | 4 |

**Deliverables:**
- `wpb generate` produces PHP files
- Generated code passes syntax check
- Streaming output works

---

### Phase 8: Server Mode

**Goal:** REST API for all operations.

| Task | Description | Dependencies | Est. Hours |
|------|-------------|--------------|------------|
| 8.1 | HTTP server setup | Phase 1 | 4 |
| 8.2 | Project endpoints | 8.1, Phase 2 | 4 |
| 8.3 | Preset endpoints | 8.1, Phase 5 | 3 |
| 8.4 | Spec endpoints | 8.1, Phase 6 | 3 |
| 8.5 | Generation endpoints | 8.1, Phase 7 | 4 |
| 8.6 | SSE streaming | 8.5 | 4 |
| 8.7 | Rate limiting | 8.1 | 3 |
| 8.8 | CORS support | 8.1 | 2 |

**Deliverables:**
- `wpb server start` works
- All CLI operations available via REST
- Streaming generation via SSE

---

## Dependency Graph

```
                        ┌──────────────────┐
                        │  Phase 1         │
                        │  Foundation      │
                        └────────┬─────────┘
                                 │
              ┌──────────────────┼──────────────────┐
              ▼                  ▼                  ▼
     ┌────────────────┐ ┌────────────────┐ ┌────────────────┐
     │   Phase 2      │ │   Phase 3      │ │                │
     │   Project      │ │   AI Bridge    │ │                │
     │   Management   │ │   Integration  │ │                │
     └───────┬────────┘ └───────┬────────┘ │                │
             │                  │          │                │
             └────────┬─────────┘          │                │
                      ▼                    │                │
             ┌────────────────┐            │                │
             │   Phase 4      │            │                │
             │   RAG System   │            │                │
             └───────┬────────┘            │                │
                     │                     │                │
       ┌─────────────┼─────────────┐       │                │
       ▼             ▼             ▼       │                │
┌────────────┐ ┌────────────┐ ┌────────────┘                │
│  Phase 5   │ │  Phase 6   │                               │
│  Preset    │ │  Spec      │                               │
│  Learning  │ │  Processing│                               │
└─────┬──────┘ └─────┬──────┘                               │
      │              │                                      │
      └──────┬───────┘                                      │
             ▼                                              │
      ┌────────────────┐                                    │
      │   Phase 7      │                                    │
      │   Code         │                                    │
      │   Generation   │                                    │
      └───────┬────────┘                                    │
              │                                             │
              └─────────────────────────────────────────────┘
                                    │
                                    ▼
                           ┌────────────────┐
                           │   Phase 8      │
                           │   Server Mode  │
                           └────────────────┘
```

---

## Package Structure

```
wp-plugin-builder/
├── cmd/
│   └── wpb/
│       └── main.go
├── internal/
│   ├── config/
│   │   ├── config.go
│   │   └── seed.go
│   ├── database/
│   │   ├── root.go
│   │   └── project.go
│   ├── project/
│   │   ├── manager.go
│   │   ├── export.go
│   │   └── import.go
│   ├── aibridge/
│   │   ├── client.go
│   │   └── stream.go
│   ├── rag/
│   │   ├── service.go
│   │   ├── chunker.go
│   │   └── vector.go
│   ├── preset/
│   │   ├── manager.go
│   │   ├── parser.go
│   │   └── builtin.go
│   ├── spec/
│   │   ├── parser.go
│   │   ├── markdown.go
│   │   └── zip.go
│   ├── generator/
│   │   ├── generator.go
│   │   ├── prompt.go
│   │   ├── parser.go
│   │   └── validator.go
│   ├── server/
│   │   ├── server.go
│   │   ├── handlers.go
│   │   └── middleware.go
│   └── errors/
│       ├── errors.go
│       └── codes.go
├── pkg/
│   └── guidelines/
│       └── wordpress.go
├── test/
│   ├── fixtures/
│   ├── integration/
│   └── e2e/
├── go.mod
├── go.sum
└── Makefile
```

---

## Build Commands

```makefile
.PHONY: build test lint

VERSION := $(shell git describe --tags --always)

build:
	go build -ldflags "-X main.version=$(VERSION)" -o bin/wpb ./cmd/wpb

test:
	go test -v ./...

test-integration:
	go test -v -tags=integration ./test/integration/...

test-e2e:
	go test -v -tags=e2e ./test/e2e/...

lint:
	golangci-lint run

install:
	go install ./cmd/wpb

clean:
	rm -rf bin/
```

---

## Estimated Timeline

| Phase | Duration | Cumulative |
|-------|----------|------------|
| Phase 1: Foundation | 3 days | 3 days |
| Phase 2: Project Management | 4 days | 7 days |
| Phase 3: AI Bridge | 3 days | 10 days |
| Phase 4: RAG System | 3 days | 13 days |
| Phase 5: Preset Learning | 3 days | 16 days |
| Phase 6: Spec Processing | 3 days | 19 days |
| Phase 7: Code Generation | 4 days | 23 days |
| Phase 8: Server Mode | 4 days | 27 days |

**Total Estimated:** ~5-6 weeks

---

## See Also

- [Core Architecture](./01-core-architecture.md)
- [Testing Strategy](./13-testing-strategy.md)
- [Error Handling](./10-error-handling.md)
