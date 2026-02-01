# RAG System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Retrieval-Augmented Generation (RAG) system for context-aware WordPress plugin code generation. Stores vector embeddings in SQLite for portable, per-project knowledge bases.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [Database Schema](./04-database-schema.md)
- [AI Bridge](../ai-bridge/00-overview.md)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           RAG PIPELINE                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                │
│   │   Ingest    │───▶│   Chunk     │───▶│   Embed     │                │
│   │   Source    │    │   Text      │    │  (AI Bridge)│                │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                │
│                                                 │                        │
│                                                 ▼                        │
│                                          ┌─────────────┐                │
│                                          │   Store     │                │
│                                          │  (SQLite)   │                │
│                                          └─────────────┘                │
│                                                 │                        │
│                                                 ▼                        │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                │
│   │   Query     │───▶│   Embed     │───▶│   Search    │                │
│   │   Prompt    │    │  (AI Bridge)│    │  (Cosine)   │                │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                │
│                                                 │                        │
│                                                 ▼                        │
│                                          ┌─────────────┐                │
│                                          │  Retrieve   │                │
│                                          │   Top-K     │                │
│                                          └─────────────┘                │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Components

### RAG Service

```go
type RAGService struct {
    aiBridge    *AIBridgeClient
    vectorStore *VectorStore
    config      RAGConfig
}

type RAGConfig struct {
    ChunkSize      int     // Default: 1000 characters
    ChunkOverlap   int     // Default: 200 characters
    TopK           int     // Default: 5 results
    MinSimilarity  float64 // Default: 0.7
    EmbeddingModel string  // Empty = AI Bridge default
}

type RAGResult struct {
    Content    string
    Score      float64
    SourceType string
    SourceID   string
    ChunkIndex int
    Metadata   map[string]any
}
```

---

## Text Chunking

### Strategy

Uses semantic chunking with overlap to preserve context across boundaries.

```go
type Chunker struct {
    chunkSize   int
    overlap     int
    separators  []string
}

func NewChunker(cfg RAGConfig) *Chunker {
    return &Chunker{
        chunkSize: cfg.ChunkSize,
        overlap:   cfg.ChunkOverlap,
        separators: []string{
            "\n## ",     // Markdown H2
            "\n### ",    // Markdown H3
            "\n\n",      // Paragraph
            "\n",        // Line
            ". ",        // Sentence
            " ",         // Word
        },
    }
}

func (c *Chunker) Chunk(text string) []Chunk {
    var chunks []Chunk
    
    // Split by separators in order of priority
    segments := c.splitBySeparators(text)
    
    // Combine small segments, split large ones
    var current string
    for _, seg := range segments {
        if len(current)+len(seg) > c.chunkSize {
            if current != "" {
                chunks = append(chunks, Chunk{
                    Content: strings.TrimSpace(current),
                    Index:   len(chunks),
                })
            }
            // Add overlap from previous chunk
            if len(chunks) > 0 && c.overlap > 0 {
                prev := chunks[len(chunks)-1].Content
                overlapText := extractOverlap(prev, c.overlap)
                current = overlapText + seg
            } else {
                current = seg
            }
        } else {
            current += seg
        }
    }
    
    // Add remaining
    if current != "" {
        chunks = append(chunks, Chunk{
            Content: strings.TrimSpace(current),
            Index:   len(chunks),
        })
    }
    
    return chunks
}
```

---

## Embedding

### Via AI Bridge

```go
func (r *RAGService) Embed(text string) ([]float32, error) {
    resp, err := r.aiBridge.Embed(AIEmbedRequest{
        Input: text,
        Model: r.config.EmbeddingModel,
    })
    if err != nil {
        return nil, errors.Wrap(err, 10401, "embedding failed")
    }
    return resp.Embedding, nil
}
```

### Batch Embedding

```go
func (r *RAGService) EmbedBatch(texts []string) ([][]float32, error) {
    // Batch up to 20 texts per request
    const batchSize = 20
    var results [][]float32
    
    for i := 0; i < len(texts); i += batchSize {
        end := min(i+batchSize, len(texts))
        batch := texts[i:end]
        
        resp, err := r.aiBridge.EmbedBatch(batch)
        if err != nil {
            return nil, errors.Wrap(err, 10402, "batch embedding failed").
                WithField("batch_start", i)
        }
        results = append(results, resp.Embeddings...)
    }
    
    return results, nil
}
```

---

## Vector Storage

### SQLite with sqlite-vec

```go
type VectorStore struct {
    db         *gorm.DB
    dimensions int // e.g., 1536 for OpenAI embeddings
}

func (v *VectorStore) Insert(vec RAGVector) error {
    // Serialize embedding to BLOB
    vec.Embedding = serializeFloat32(vec.EmbeddingData)
    return v.db.Create(&vec).Error
}

func (v *VectorStore) Search(query []float32, topK int, minScore float64) ([]RAGResult, error) {
    queryBlob := serializeFloat32(query)
    
    // Use sqlite-vec for cosine similarity
    var results []struct {
        RAGVector
        Score float64 `gorm:"column:score"`
    }
    
    err := v.db.Raw(`
        SELECT rv.*, 
               (1 - vec_distance_cosine(rv.embedding, ?)) as score
        FROM rag_vectors rv
        WHERE score >= ?
        ORDER BY score DESC
        LIMIT ?
    `, queryBlob, minScore, topK).Scan(&results).Error
    
    if err != nil {
        return nil, errors.Wrap(err, 10403, "vector search failed")
    }
    
    // Convert to RAGResult
    var ragResults []RAGResult
    for _, r := range results {
        ragResults = append(ragResults, RAGResult{
            Content:    r.Content,
            Score:      r.Score,
            SourceType: r.SourceType,
            SourceID:   r.SourceID,
            ChunkIndex: r.ChunkIndex,
            Metadata:   r.Metadata,
        })
    }
    
    return ragResults, nil
}
```

---

## Indexing Pipeline

### Index Document

```go
func (r *RAGService) IndexDocument(source SourceInfo, content string) error {
    // 1. Chunk the content
    chunks := r.chunker.Chunk(content)
    
    // 2. Extract text for embedding
    texts := make([]string, len(chunks))
    for i, c := range chunks {
        texts[i] = c.Content
    }
    
    // 3. Get embeddings in batch
    embeddings, err := r.EmbedBatch(texts)
    if err != nil {
        return err
    }
    
    // 4. Store vectors
    for i, chunk := range chunks {
        vec := RAGVector{
            SourceType:    source.Type,
            SourceID:      source.ID,
            ChunkIndex:    chunk.Index,
            Content:       chunk.Content,
            EmbeddingData: embeddings[i],
            Metadata: map[string]any{
                "source_name": source.Name,
                "indexed_at":  time.Now(),
            },
        }
        if err := r.vectorStore.Insert(vec); err != nil {
            return errors.Wrap(err, 10404, "vector insert failed").
                WithField("chunk", i)
        }
    }
    
    return nil
}
```

### Index Preset

```go
func (r *RAGService) ImportPreset(path string) (*Preset, error) {
    // 1. Read markdown file
    content, err := os.ReadFile(path)
    if err != nil {
        return nil, errors.Wrap(err, 10405, "preset file read failed")
    }
    
    // 2. Parse metadata from frontmatter
    meta, body := parseFrontmatter(string(content))
    
    // 3. Create preset record
    preset := &Preset{
        Name:        meta.Name,
        Category:    meta.Category,
        Description: meta.Description,
        SourcePath:  path,
        ContentHash: hash(content),
    }
    
    // 4. Index content
    source := SourceInfo{
        Type: "preset",
        ID:   preset.Name,
        Name: preset.Name,
    }
    if err := r.IndexDocument(source, body); err != nil {
        return nil, err
    }
    
    // 5. Update chunk count
    preset.ChunkCount = r.countChunks(preset.Name)
    
    return preset, r.db.Create(preset).Error
}
```

---

## Query Pipeline

### Context Retrieval

```go
func (r *RAGService) Query(prompt string, topK int) ([]RAGResult, error) {
    // 1. Embed the query
    queryVec, err := r.Embed(prompt)
    if err != nil {
        return nil, err
    }
    
    // 2. Search vector store
    results, err := r.vectorStore.Search(
        queryVec,
        topK,
        r.config.MinSimilarity,
    )
    if err != nil {
        return nil, err
    }
    
    return results, nil
}
```

### Build Context for Generation

```go
func (r *RAGService) BuildContext(prompt string, opts ContextOptions) (string, error) {
    // 1. Get relevant chunks
    results, err := r.Query(prompt, opts.TopK)
    if err != nil {
        return "", err
    }
    
    // 2. Format as context
    var context strings.Builder
    context.WriteString("## Relevant Context\n\n")
    
    for i, result := range results {
        context.WriteString(fmt.Sprintf(
            "### Source %d (Score: %.2f)\n",
            i+1, result.Score,
        ))
        context.WriteString(result.Content)
        context.WriteString("\n\n")
    }
    
    return context.String(), nil
}
```

---

## Source Types

| Type | Description | Indexed When |
|------|-------------|--------------|
| `preset` | Global learning material | Preset import |
| `spec` | Project specifications | Spec import |
| `file` | Project source files | File creation/update |
| `generated` | AI-generated code | Generation complete |

---

## Reindexing

```go
func (r *RAGService) Reindex(sourceType, sourceID string) error {
    // 1. Delete existing vectors
    if err := r.vectorStore.DeleteBySource(sourceType, sourceID); err != nil {
        return err
    }
    
    // 2. Get source content
    content, err := r.getSourceContent(sourceType, sourceID)
    if err != nil {
        return err
    }
    
    // 3. Re-index
    return r.IndexDocument(SourceInfo{
        Type: sourceType,
        ID:   sourceID,
    }, content)
}
```

---

## Configuration

```go
type RAGConfig struct {
    ChunkSize      int     `json:"chunkSize"`      // 1000
    ChunkOverlap   int     `json:"chunkOverlap"`   // 200
    TopK           int     `json:"topK"`           // 5
    MinSimilarity  float64 `json:"minSimilarity"`  // 0.7
    EmbeddingModel string  `json:"embeddingModel"` // "" = default
}
```

---

## See Also

- [Database Schema](./04-database-schema.md)
- [Code Generation](./07-code-generation.md)
- [AI Bridge Input Formats](../ai-bridge/02-input-formats.md)
