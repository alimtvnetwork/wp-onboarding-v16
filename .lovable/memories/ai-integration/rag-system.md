# Memory: ai-integration/rag-system

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/06-ai-integration/`

---

## Overview

RAG system for AI context management via indexing and retrieval of Markdown artifacts.

---

## Pipeline

| Stage | Description |
|-------|-------------|
| Ingestion | Scan filesystem, chunk content |
| Retrieval | Semantic search for relevant chunks |
| Assembly | Combine top-K recent + retrieved chunks |

---

## Storage

- **FileRegistry:** Tracked files
- **ArtifactRegistry:** Ideas and instructions
- **ChunkRegistry:** Content chunks with embeddings
- **Database:** SQLite with stable chunk IDs

---

## Artifact Organization

```
project/
├── ideas/           # 01-idea-slug.md
└── instructions/    # 01-instruction-slug.md
```

Promotion logic moves refined ideas → instructions.

---

## APIs

- Voice-to-idea capture
- Idea promotion
- Context retrieval
- Manual re-indexing
