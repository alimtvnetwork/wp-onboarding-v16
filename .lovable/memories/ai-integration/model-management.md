# Memory: ai-integration/model-management

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/06-ai-integration/`

---

## Overview

LLM model management with multiple backends and category-based selection.

---

## Model Categories

| Category | Purpose |
|----------|---------|
| thinking | Reasoning, planning |
| writing | Content generation |
| voice | Transcription |
| coding | Code generation |

---

## Backends

| Backend | Features |
|---------|----------|
| Ollama | Dynamic model loading |
| llama.cpp | Router mode, llama-swap proxy |

All support runtime switching without restart.

---

## Resolution Hierarchy

```
Override → Project → User → System
```

---

## File Authorization

AI models have explicit authorization to:
- Access, read, write, rewrite files
- Integrate with history/snapshot tracking
- Complete audit trail of changes
