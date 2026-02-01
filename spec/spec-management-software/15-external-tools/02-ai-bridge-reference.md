# AI Bridge Reference

> **External Spec:** `spec/ai-bridge/`  
> **Version:** 1.0.0  
> **Error Range:** 9000-9999

---

## Summary

External AI adapter providing a unified interface for LLM communication, supporting multiple input formats and dual execution modes.

---

## Full Specification

📁 **Location:** [`spec/ai-bridge/`](../../../ai-bridge/)

---

## Key Components

| File | Description |
|------|-------------|
| `00-overview.md` | Architecture, input formats, execution modes |
| `01-architecture.md` | Core system design, adapters |
| `02-input-formats.md` | Markdown, JSON, YAML, CSV handlers |
| `03-startup-modes.md` | Binary vs daemon execution |
| `04-api-interface.md` | REST + WebSocket API |
| `05-error-codes.md` | Error code registry (9xxx) |
| `06-configuration.md` | Config schema and defaults |

---

## Integration Points

### Binary Mode

```bash
# Single prompt execution
aibridge run prompt.md

# With output format
aibridge run data.json --output response.json
```

### Daemon Mode

```bash
# Start daemon
aibridge daemon start --port 8089

# Query via REST
curl -X POST http://localhost:8089/api/prompt \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Summarize this text...", "model": "llama2"}'
```

---

## Input Formats

| Format | Extension | Use Case |
|--------|-----------|----------|
| Markdown | `.md` | Prompt templates with YAML frontmatter |
| JSON | `.json` | Structured requests, batch processing |
| YAML | `.yaml` | Complex prompts, multi-document |
| CSV | `.csv` | Bulk data, keyword lists |

---

## LLM Backends

| Backend | Adapter | Default Port |
|---------|---------|--------------|
| Ollama | OllamaAdapter | 11434 |
| llama.cpp | LlamaCppAdapter | 8080 |
| OpenAI-compatible | OpenAIAdapter | N/A |

---

## Error Codes

| Range | Category |
|-------|----------|
| 9000-9099 | General/Startup |
| 9100-9199 | Input parsing |
| 9200-9299 | Backend connection |
| 9300-9399 | Request processing |
| 9400-9499 | Response handling |

See: [`spec/ai-bridge/05-error-codes.md`](../../../ai-bridge/05-error-codes.md)

---

*Reference for spec-management-software integration*
