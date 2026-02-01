# Feature: AI Bridge

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Summary

External AI adapter providing a unified interface for LLM communication, supporting multiple input formats (Markdown, JSON, YAML, CSV) and dual execution modes (local binary + background service).

---

## User Stories

- As a developer, I want to feed prompts via Markdown files with YAML frontmatter
- As a developer, I want to import structured data from JSON/CSV for batch AI processing
- As a developer, I want to run AI Bridge as a CLI tool or background daemon
- As a developer, I want AI Bridge to abstract different LLM backends (Ollama, llama.cpp, OpenAI-compatible)
- As a developer, I want consistent error handling across all input formats

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           AI BRIDGE ARCHITECTURE                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────────┐  │
│   │                         INPUT LAYER                                   │  │
│   │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐             │  │
│   │  │ Markdown │  │   JSON   │  │   YAML   │  │   CSV    │             │  │
│   │  │  Parser  │  │  Parser  │  │  Parser  │  │  Parser  │             │  │
│   │  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘             │  │
│   │       └──────────────┴──────────────┴──────────────┘                 │  │
│   │                              │                                        │  │
│   │                              ▼                                        │  │
│   │                    ┌──────────────────┐                              │  │
│   │                    │ Unified Request  │                              │  │
│   │                    │    Normalizer    │                              │  │
│   │                    └────────┬─────────┘                              │  │
│   └─────────────────────────────┼────────────────────────────────────────┘  │
│                                 │                                            │
│   ┌─────────────────────────────┼────────────────────────────────────────┐  │
│   │                         CORE LAYER                                    │  │
│   │                              ▼                                        │  │
│   │                    ┌──────────────────┐                              │  │
│   │                    │   Request Queue  │                              │  │
│   │                    │   & Rate Limiter │                              │  │
│   │                    └────────┬─────────┘                              │  │
│   │                             │                                         │  │
│   │              ┌──────────────┼──────────────┐                         │  │
│   │              ▼              ▼              ▼                         │  │
│   │       ┌──────────┐   ┌──────────┐   ┌──────────┐                    │  │
│   │       │  Ollama  │   │llama.cpp │   │ OpenAI   │                    │  │
│   │       │  Adapter │   │ Adapter  │   │ Adapter  │                    │  │
│   │       └──────────┘   └──────────┘   └──────────┘                    │  │
│   └─────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│   ┌─────────────────────────────────────────────────────────────────────┐  │
│   │                       EXECUTION MODES                                │  │
│   │  ┌────────────────────────┐    ┌────────────────────────┐          │  │
│   │  │     Local Binary       │    │   Background Daemon    │          │  │
│   │  │  ./aibridge run ...    │    │  ./aibridge daemon     │          │  │
│   │  │  (Single execution)    │    │  (REST API + WebSocket)│          │  │
│   │  └────────────────────────┘    └────────────────────────┘          │  │
│   └─────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Components

| # | Component | Type | Status | Description |
|---|-----------|------|--------|-------------|
| 01 | [Architecture](./01-architecture.md) | Backend | Complete | Core system design |
| 02 | [Input Formats](./02-input-formats.md) | Backend | Complete | Markdown, JSON, YAML, CSV handlers |
| 03 | [Startup Modes](./03-startup-modes.md) | Backend | Complete | Binary vs daemon execution |
| 04 | [API Interface](./04-api-interface.md) | Backend | Complete | REST + WebSocket API |
| 05 | [Error Codes](./05-error-codes.md) | Backend | Complete | Error code registry (9xxx) |
| 06 | [Configuration](./06-configuration.md) | Backend | Complete | Config schema and defaults |

---

## Input Formats

| Format | Extension | Use Case | Features |
|--------|-----------|----------|----------|
| **Markdown** | `.md` | Prompt templates, instructions | YAML frontmatter, variable injection |
| **JSON** | `.json` | Structured requests, schemas | Batch processing, validation |
| **YAML** | `.yaml`, `.yml` | Configuration, complex prompts | Multi-document, anchors |
| **CSV** | `.csv` | Bulk data, keyword lists | Column mapping, batch execution |

---

## Startup Modes

| Mode | Command | Use Case | Port |
|------|---------|----------|------|
| **Binary** | `aibridge run <file>` | Single execution, CI/CD | N/A |
| **Daemon** | `aibridge daemon start` | Long-running service | 8089 |

---

## Error Code Range

AI Bridge uses error codes **9000-9999**:

| Range | Category |
|-------|----------|
| 9000-9099 | General/Startup errors |
| 9100-9199 | Input parsing errors |
| 9200-9299 | Backend connection errors |
| 9300-9399 | Request processing errors |
| 9400-9499 | Response handling errors |

---

## Dependencies

- [AI Integration](../06-ai-integration/00-overview.md) — LLM provider abstraction
- [LLM Server Management](../06-ai-integration/07-llm-server-management.md) — Backend management
- [Resilient Execution](../06-ai-integration/12-resilient-execution-system.md) — Retry logic

---

## See Also

- [AI-HANDOFF-GUIDE.md](../../AI-HANDOFF-GUIDE.md) — Training AI models on this spec
