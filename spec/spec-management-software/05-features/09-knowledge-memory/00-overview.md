# Feature: Knowledge Memory

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Summary

Retrieval-Augmented Generation (RAG) system for learning from specifications and external URLs, providing semantic search and context injection for AI operations. Supports dual knowledge sources (local specs + URL crawling), hybrid search (vector + keyword), and intelligent context assembly with token budgeting.

---

## User Stories

- As a user, I want AI to learn from my existing specifications
- As a user, I want to ingest documentation from external URLs
- As a user, I want semantic search across all my knowledge
- As a user, I want relevant context automatically injected into AI prompts
- As a user, I want to pin important artifacts for priority retrieval
- As a user, I want to see what sources the AI used in its response

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      Knowledge Memory Architecture                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                          INGESTION LAYER                                 ││
│  ├─────────────────┬─────────────────┬─────────────────────────────────────┤│
│  │  File Watcher   │  URL Crawler    │  Manual Upload                      ││
│  │  (ideas/        │  (External      │  (Paste/Import)                     ││
│  │   instructions) │   docs)         │                                     ││
│  └────────┬────────┴────────┬────────┴───────────────────┬─────────────────┘│
│           │                 │                             │                  │
│           └─────────────────┼─────────────────────────────┘                  │
│                             ▼                                                │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                    PROCESSING LAYER (Knowledge Worker)                   ││
│  ├─────────────────────────────────────────────────────────────────────────┤│
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐                 ││
│  │  │  Parse   │─▶│  Chunk   │─▶│  Embed   │─▶│  Store   │                 ││
│  │  │  (MD)    │  │  (512t)  │  │  (vec)   │  │ (SQLite) │                 ││
│  │  └──────────┘  └──────────┘  └──────────┘  └──────────┘                 ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                             │                                                │
│                             ▼                                                │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                      STORAGE LAYER                                       ││
│  ├────────────────────┬────────────────────┬───────────────────────────────┤│
│  │  SQLite            │  sqlite-vss        │  FTS5                         ││
│  │  (Artifacts,       │  (Vector           │  (Keyword                     ││
│  │   Chunks)          │   Embeddings)      │   Search)                     ││
│  └────────────────────┴────────────────────┴───────────────────────────────┘│
│                             │                                                │
│                             ▼                                                │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                      RETRIEVAL LAYER                                     ││
│  ├─────────────────────────────────────────────────────────────────────────┤│
│  │  Query → Embed → [Vector Search | FTS5 Search | Top-K Recent]           ││
│  │         → Merge → Dedupe → Rerank → Token Budget → Context Assembly     ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Components

### Backend

| # | Component | Type | Status | Description |
|---|-----------|------|--------|-------------|
| 01 | [RAG System](./01-rag-system.md) | Backend | Complete | Core RAG pipeline |
| 02 | [RAG Spec Guidelines](./02-rag-spec-guidelines.md) | Backend | Complete | Artifact formatting |
| 03 | [RAG Integration Plan](./03-rag-integration-plan.md) | Backend | Complete | Implementation roadmap |
| 04 | [Vector Database Plan](./04-vector-database-plan.md) | Backend | Complete | sqlite-vss integration |
| 05 | [Vector Search Service](./05-vector-search-service.md) | Backend | Complete | Search implementation |
| 06 | [Context Window Manager](./06-context-window-manager.md) | Backend | Complete | Token budgeting |
| 07 | [Memory Compression](./07-memory-compression.md) | Backend | Complete | Multi-turn summarization |
| 08 | [Vector DB Implementation](./08-vector-db-implementation-guide.md) | Backend | Complete | Implementation guide |
| 09 | [Knowledge Memory System](./09-knowledge-memory-system.md) | Backend | Complete | Full system spec |
| 10 | [Knowledge Worker Binary](./10-knowledge-worker-binary.md) | Backend | Complete | External Go worker |

### Frontend

| # | Component | Type | Status | Description |
|---|-----------|------|--------|-------------|
| 11 | [Knowledge Memory UI](./11-knowledge-memory-ui.md) | Frontend | Complete | Knowledge management interface |

---

## Key Features

- **Dual Sources:** Spec projects + URL crawling
- **Vector Search:** sqlite-vss with RRF scoring
- **Hybrid Search:** Vector + FTS5 keyword parallel
- **Chunking:** 512 tokens max, 50 token overlap, header-aware splitting
- **Context Assembly:** Hierarchical with token budgets
- **Worker Binary:** External Go process for heavy lifting
- **Caching:** TTL-based with invalidation on changes

---

## TypeScript Interfaces

```typescript
// Core artifact types
interface Artifact {
  id: string;
  projectId: string;
  fileId: string;
  artifactType: 'idea' | 'instruction' | 'spec';
  title: string;
  summary?: string;
  status: 'draft' | 'refined' | 'promoted' | 'archived';
  relativePath: string;
  contentHash: string;
  isPinned: boolean;
  createdAt: string;
  updatedAt: string;
  indexedAt?: string;
}

interface Chunk {
  id: string;
  artifactId: string;
  chunkIndex: number;
  content: string;
  tokenCount: number;
  sectionAnchor?: string;
  startOffset: number;
  endOffset: number;
  createdAt: string;
}

interface Embedding {
  chunkId: string;
  modelId: string;
  dimensions: number;
  vector: Float32Array;
  createdAt: string;
}

// Retrieval types
interface RetrieveRequest {
  projectId: string;
  queryText: string;
  options?: RetrieveOptions;
}

interface RetrieveOptions {
  includeRecentIdeas?: boolean;
  includeRecentInstructions?: boolean;
  maxChunks?: number;
  forceRefresh?: boolean;
  pinnedOnly?: boolean;
}

interface RetrieveResponse {
  query: string;
  context: RetrievalContext;
  assembledPrompt: string;
  tokenCount: number;
  cacheHit: boolean;
  latencyMs: number;
}

interface RetrievalContext {
  recentIdeas: ArtifactSummary[];
  recentInstructions: ArtifactSummary[];
  semanticChunks: ChunkResult[];
  keywordChunks: ChunkResult[];
}

interface ChunkResult {
  chunkId: string;
  content: string;
  score: number;
  sourcePath: string;
  sectionAnchor?: string;
  sourceType: 'semantic' | 'keyword' | 'topk';
}

// Configuration types
interface RAGConfig {
  chunking: ChunkConfig;
  topK: TopKConfig;
  cache: CacheConfig;
  embedding: EmbeddingConfig;
}

interface ChunkConfig {
  maxSize: number;      // Default: 512
  overlap: number;      // Default: 50
  minSize: number;      // Default: 100
  separator: string;    // Default: '\n##|\n---|\n\n'
}

interface TopKConfig {
  recentIdeas: number;        // Default: 3
  recentInstructions: number; // Default: 2
  semanticChunks: number;     // Default: 10
  keywordChunks: number;      // Default: 5
  pinnedFirst: boolean;       // Default: true
}
```

---

## Security

| Security Concern | Mitigation |
|------------------|------------|
| Path Traversal | PathManager validates all paths, rejects `../` patterns |
| SSRF (URL Crawling) | Blocklist for private IP ranges, localhost, internal domains |
| ReDoS | Pattern validator limits URL regex complexity |
| Data Leakage | Project-scoped isolation, no cross-project retrieval |
| Token Injection | Content sanitization before prompt assembly |
| Rate Limiting | Configurable limits per user/project |

---

## Dependencies

- [AI Integration](../06-ai-integration/00-overview.md)
- [Database Design](../../07-database-design/00-overview.md)
- [File Management](../02-file-management/00-overview.md)

---

## E2E Tests

| # | Test | Priority | Status |
|---|------|----------|--------|
| 01 | [URL Normalizer Tests](./tests/01-url-normalizer-tests.md) | High | Spec'd |
| 02 | [Knowledge Validator Tests](./tests/02-knowledge-validator-tests.md) | High | Spec'd |
| 03 | [Knowledge Memory E2E](./tests/03-knowledge-memory-e2e.md) | Critical | Spec'd |
| 04 | [Pattern Validator Tests](./tests/04-pattern-validator-tests.md) | High | Spec'd |

---

## Configuration Keys

| Key | Default | Description |
|-----|---------|-------------|
| `rag.chunking.maxSize` | `512` | Maximum tokens per chunk |
| `rag.chunking.overlap` | `50` | Token overlap between chunks |
| `rag.chunking.minSize` | `100` | Minimum tokens per chunk |
| `rag.topk.recentIdeas` | `3` | Recent ideas always included |
| `rag.topk.recentInstructions` | `2` | Recent instructions always included |
| `rag.topk.semanticChunks` | `10` | Semantic search result count |
| `rag.topk.keywordChunks` | `5` | Keyword search result count |
| `rag.cache.enabled` | `true` | Enable retrieval caching |
| `rag.cache.ttlSeconds` | `300` | Cache TTL in seconds |
| `rag.embedding.enabled` | `true` | Use embeddings (false = FTS5 only) |
| `rag.embedding.modelId` | `` | Embedding model to use |
| `rag.embedding.dimensions` | `768` | Embedding vector dimensions |

---

## Related Specs

- [RAG System](./01-rag-system.md)
- [Vector Search Service](./05-vector-search-service.md)
- [AI Integration](../06-ai-integration/00-overview.md)
