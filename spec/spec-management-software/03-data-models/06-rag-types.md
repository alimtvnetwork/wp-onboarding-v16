# RAG Types

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Artifact

```typescript
interface Artifact {
  readonly id: string;
  projectId: string;
  fileId: string;
  type: ArtifactType;
  path: string;
  title: string;
  content: string;
  contentHash: string;
  chunks: Chunk[];
  metadata: ArtifactMetadata;
  indexedAt: string;
  updatedAt: string;
}

type ArtifactType = 
  | 'spec'
  | 'idea'
  | 'instruction'
  | 'code'
  | 'documentation';

interface ArtifactMetadata {
  version: string | null;
  status: string | null;
  author: string | null;
  tags: string[];
  wordCount: number;
  sectionCount: number;
}
```

---

## Chunk

```typescript
interface Chunk {
  readonly id: string;
  artifactId: string;
  index: number;
  content: string;
  embedding: number[];          // Float32 vector
  tokens: number;
  metadata: ChunkMetadata;
}

interface ChunkMetadata {
  sectionTitle: string | null;
  sectionPath: string[];        // Breadcrumb path
  startLine: number;
  endLine: number;
  type: ChunkType;
}

type ChunkType = 
  | 'heading'
  | 'paragraph'
  | 'code_block'
  | 'list'
  | 'table'
  | 'frontmatter';
```

---

## Embedding

```typescript
interface EmbeddingRequest {
  texts: string[];
  model?: string;               // Default: text-embedding-3-small
}

interface EmbeddingResponse {
  embeddings: number[][];
  model: string;
  usage: EmbeddingUsage;
}

interface EmbeddingUsage {
  promptTokens: number;
  totalTokens: number;
}

interface EmbeddingConfig {
  model: string;
  dimensions: number;           // 384, 768, 1536, etc.
  maxTokensPerChunk: number;
  overlapTokens: number;
  batchSize: number;
}
```

---

## Retrieval

```typescript
interface RetrieveRequest {
  query: string;
  projectId: string;
  topK: number;                 // Default: 5
  threshold: number;            // Default: 0.7
  filters?: RetrievalFilters;
  rerank?: boolean;
}

interface RetrievalFilters {
  artifactTypes?: ArtifactType[];
  paths?: string[];
  tags?: string[];
  dateRange?: DateRange;
}

interface DateRange {
  from: string;
  to: string;
}

interface RetrieveResponse {
  results: RetrievalResult[];
  query: string;
  searchTime: number;
  totalMatches: number;
}

interface RetrievalResult {
  chunk: Chunk;
  artifact: ArtifactSummary;
  score: number;
  rerankScore?: number;
  highlights: string[];
}

interface ArtifactSummary {
  id: string;
  type: ArtifactType;
  path: string;
  title: string;
}
```

---

## Context Building

```typescript
interface ContextRequest {
  query: string;
  projectId: string;
  maxTokens: number;            // Default: 4000
  strategy: ContextStrategy;
}

type ContextStrategy = 
  | 'similarity'
  | 'recency'
  | 'importance'
  | 'hybrid';

interface RetrievalContext {
  chunks: ContextChunk[];
  totalTokens: number;
  sources: ContextSource[];
  metadata: ContextMetadata;
}

interface ContextChunk {
  id: string;
  content: string;
  tokens: number;
  source: string;
  score: number;
}

interface ContextSource {
  artifactId: string;
  path: string;
  title: string;
  chunkCount: number;
}

interface ContextMetadata {
  strategy: ContextStrategy;
  searchTime: number;
  chunksConsidered: number;
  chunksIncluded: number;
}
```

---

## Indexing

```typescript
interface IndexRequest {
  projectId: string;
  paths?: string[];             // Empty = full reindex
  force?: boolean;
}

interface IndexStatus {
  projectId: string;
  status: IndexingStatus;
  progress: IndexProgress;
  startedAt: string;
  completedAt: string | null;
  error: string | null;
}

type IndexingStatus = 
  | 'idle'
  | 'queued'
  | 'running'
  | 'completed'
  | 'failed';

interface IndexProgress {
  total: number;
  processed: number;
  chunked: number;
  embedded: number;
  percentage: number;
}

interface IndexStats {
  projectId: string;
  artifactCount: number;
  chunkCount: number;
  totalTokens: number;
  lastIndexedAt: string;
  indexSize: number;            // Bytes
}
```

---

## Vector Store

```typescript
interface VectorQuery {
  embedding: number[];
  topK: number;
  filters?: VectorFilters;
  includeMetadata: boolean;
  includeVectors: boolean;
}

interface VectorFilters {
  projectId?: string;
  artifactType?: ArtifactType[];
  minScore?: number;
}

interface VectorResult {
  id: string;
  score: number;
  metadata?: Record<string, unknown>;
  vector?: number[];
}

interface VectorUpsert {
  id: string;
  vector: number[];
  metadata: Record<string, unknown>;
}

interface VectorStore {
  upsert(vectors: VectorUpsert[]): Promise<void>;
  query(query: VectorQuery): Promise<VectorResult[]>;
  delete(ids: string[]): Promise<void>;
  deleteByFilter(filter: VectorFilters): Promise<number>;
  count(filter?: VectorFilters): Promise<number>;
}
```

---

## RAG Pipeline

```typescript
interface RAGConfig {
  embedding: EmbeddingConfig;
  chunking: ChunkingConfig;
  retrieval: RetrievalConfig;
  reranking: RerankingConfig;
}

interface ChunkingConfig {
  strategy: ChunkingStrategy;
  maxTokens: number;
  overlap: number;
  separators: string[];
}

type ChunkingStrategy = 
  | 'fixed'
  | 'semantic'
  | 'markdown';

interface RetrievalConfig {
  topK: number;
  threshold: number;
  maxTokens: number;
  includeMetadata: boolean;
}

interface RerankingConfig {
  enabled: boolean;
  model: string;
  topN: number;
}

interface RAGResult {
  context: RetrievalContext;
  augmentedPrompt: string;
  sources: ContextSource[];
}
```
