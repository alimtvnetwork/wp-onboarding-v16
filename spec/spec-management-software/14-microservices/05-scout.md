# Scout Service Specification

**Service:** Scout (Search & RAG Engine)  
**Port:** 8084  
**Phase:** 6  
**Status:** Draft  
**Last Updated:** 2026-01-30

---

## 1. Overview

Scout is the dedicated search and retrieval service providing full-text search (FTS5), semantic vector search (sqlite-vss), and RAG (Retrieval-Augmented Generation) pipeline capabilities. It serves as the knowledge backbone for AI-powered features.

### 1.1 Responsibilities

| Responsibility | Description |
|----------------|-------------|
| Full-Text Search | FTS5-based text search across specifications |
| Semantic Search | Vector similarity via sqlite-vss embeddings |
| Hybrid Search | Combined FTS5 + VSS scoring with configurable weights |
| Embedding Generation | Text-to-vector conversion via AI-Bridge |
| RAG Pipeline | Context assembly for AI prompt augmentation |
| Index Management | Incremental indexing with stable chunk IDs |

### 1.2 Service Dependencies

```
┌─────────────────────────────────────────────────────────┐
│                     Main Gateway                         │
│                       (:8080)                            │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                    Scout Service                         │
│                       (:8084)                            │
├─────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │  FTS5       │  │  VSS        │  │  RAG        │     │
│  │  Engine     │  │  Engine     │  │  Pipeline   │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
│         │                │                │             │
│         ▼                ▼                ▼             │
│  ┌─────────────────────────────────────────────────┐   │
│  │              Hybrid Retriever                    │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
         │                                    │
         ▼                                    ▼
┌─────────────────┐                ┌─────────────────────┐
│   SpecManager   │                │     AI-Bridge       │
│    (:8081)      │                │      (:8082)        │
└─────────────────┘                └─────────────────────┘
```

---

## 2. Directory Structure

```
cmd/scout/
├── main.go                    # Entry point with graceful shutdown

internal/
├── handler/
│   ├── search.go              # Search endpoint handlers
│   ├── index.go               # Indexing endpoint handlers
│   ├── rag.go                 # RAG pipeline handlers
│   └── health.go              # Health check handlers
├── service/
│   ├── fts_engine.go          # FTS5 search implementation
│   ├── vss_engine.go          # Vector similarity search
│   ├── hybrid_retriever.go    # Combined FTS + VSS retrieval
│   ├── embedding_service.go   # Embedding generation via AI-Bridge
│   ├── rag_pipeline.go        # RAG context assembly
│   ├── chunker.go             # Content chunking with stable IDs
│   └── indexer.go             # Incremental index management
├── repository/
│   ├── chunk_repo.go          # Chunk storage operations
│   ├── embedding_repo.go      # Embedding vector storage
│   └── file_registry.go       # Tracked file metadata
├── model/
│   ├── chunk.go               # Chunk domain model
│   ├── embedding.go           # Embedding domain model
│   ├── search_result.go       # Search result types
│   └── rag_context.go         # RAG context types
└── client/
    ├── aibridge_client.go     # AI-Bridge service client
    └── specmanager_client.go  # SpecManager service client

migrations/
└── scout/
    ├── 001_create_chunks.sql
    ├── 002_create_embeddings.sql
    ├── 003_create_fts_index.sql
    └── 004_create_vss_index.sql
```

---

## 3. Database Schema

### 3.1 Chunks Table

```sql
-- migrations/scout/001_create_chunks.sql
CREATE TABLE IF NOT EXISTS Chunks (
    ID              TEXT PRIMARY KEY,           -- Stable chunk ID (content hash + position)
    FileID          TEXT NOT NULL,              -- Reference to source file
    ProjectID       TEXT NOT NULL,              -- Project scope
    Content         TEXT NOT NULL,              -- Chunk text content
    ContentHash     TEXT NOT NULL,              -- SHA-256 of content
    ChunkIndex      INTEGER NOT NULL,           -- Position within file
    StartLine       INTEGER NOT NULL,           -- Source line start
    EndLine         INTEGER NOT NULL,           -- Source line end
    Metadata        TEXT,                       -- JSON metadata
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    
    UNIQUE(FileID, ChunkIndex)
);

CREATE INDEX idx_chunks_project ON Chunks(ProjectID);
CREATE INDEX idx_chunks_file ON Chunks(FileID);
CREATE INDEX idx_chunks_hash ON Chunks(ContentHash);
```

### 3.2 Embeddings Table

```sql
-- migrations/scout/002_create_embeddings.sql
CREATE TABLE IF NOT EXISTS Embeddings (
    ID              TEXT PRIMARY KEY,
    ChunkID         TEXT NOT NULL REFERENCES Chunks(ID) ON DELETE CASCADE,
    ModelName       TEXT NOT NULL,              -- Model used for embedding
    Dimensions      INTEGER NOT NULL,           -- Vector dimensions
    Vector          BLOB NOT NULL,              -- Serialized float32 array
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    
    UNIQUE(ChunkID, ModelName)
);

CREATE INDEX idx_embeddings_chunk ON Embeddings(ChunkID);
CREATE INDEX idx_embeddings_model ON Embeddings(ModelName);
```

### 3.3 FTS5 Virtual Table

```sql
-- migrations/scout/003_create_fts_index.sql
CREATE VIRTUAL TABLE IF NOT EXISTS ChunksFTS USING fts5(
    Content,
    Metadata,
    content='Chunks',
    content_rowid='rowid',
    tokenize='porter unicode61'
);

-- Triggers to keep FTS in sync
CREATE TRIGGER chunks_ai AFTER INSERT ON Chunks BEGIN
    INSERT INTO ChunksFTS(rowid, Content, Metadata) 
    VALUES (new.rowid, new.Content, new.Metadata);
END;

CREATE TRIGGER chunks_ad AFTER DELETE ON Chunks BEGIN
    INSERT INTO ChunksFTS(ChunksFTS, rowid, Content, Metadata) 
    VALUES ('delete', old.rowid, old.Content, old.Metadata);
END;

CREATE TRIGGER chunks_au AFTER UPDATE ON Chunks BEGIN
    INSERT INTO ChunksFTS(ChunksFTS, rowid, Content, Metadata) 
    VALUES ('delete', old.rowid, old.Content, old.Metadata);
    INSERT INTO ChunksFTS(rowid, Content, Metadata) 
    VALUES (new.rowid, new.Content, new.Metadata);
END;
```

### 3.4 VSS Virtual Table

```sql
-- migrations/scout/004_create_vss_index.sql
-- Requires sqlite-vss extension
CREATE VIRTUAL TABLE IF NOT EXISTS ChunksVSS USING vss0(
    embedding(1536)  -- OpenAI ada-002 dimensions (configurable)
);

-- Note: VSS index populated via application code
-- INSERT INTO ChunksVSS(rowid, embedding) VALUES (?, ?);
```

### 3.5 File Registry Table

```sql
-- migrations/scout/005_create_file_registry.sql
CREATE TABLE IF NOT EXISTS FileRegistry (
    ID              TEXT PRIMARY KEY,
    ProjectID       TEXT NOT NULL,
    FilePath        TEXT NOT NULL,
    ContentHash     TEXT NOT NULL,              -- For change detection
    LastIndexedAt   TEXT NOT NULL,
    ChunkCount      INTEGER NOT NULL DEFAULT 0,
    Status          TEXT NOT NULL DEFAULT 'pending',  -- pending, indexed, error
    ErrorMessage    TEXT,
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    
    UNIQUE(ProjectID, FilePath)
);

CREATE INDEX idx_file_registry_project ON FileRegistry(ProjectID);
CREATE INDEX idx_file_registry_status ON FileRegistry(Status);
```

---

## 4. Domain Models

### 4.1 Chunk Model

```go
// internal/model/chunk.go
package model

import (
    "crypto/sha256"
    "encoding/hex"
    "fmt"
    "runtime"
    "time"
    
    "pkg/types"
)

// ChunkStatus represents the processing state of a chunk
type ChunkStatus string

const (
    ChunkStatusPending   ChunkStatus = "pending"
    ChunkStatusEmbedded  ChunkStatus = "embedded"
    ChunkStatusError     ChunkStatus = "error"
)

// Chunk represents a content chunk for indexing
type Chunk struct {
    ID          types.ChunkID
    FileID      types.FileID
    ProjectID   types.ProjectID
    Content     string
    ContentHash string
    ChunkIndex  int
    StartLine   int
    EndLine     int
    Metadata    ChunkMetadata
    Status      ChunkStatus
    CreatedAt   time.Time
    UpdatedAt   time.Time
}

// ChunkMetadata contains additional chunk information
type ChunkMetadata struct {
    FileName    string   `json:"fileName"`
    FileType    string   `json:"fileType"`
    Section     string   `json:"section,omitempty"`
    Headers     []string `json:"headers,omitempty"`
    Tags        []string `json:"tags,omitempty"`
}

// GenerateStableID creates a deterministic chunk ID
// MANDATORY: Includes caller info for debugging
func GenerateStableID(fileID types.FileID, content string, index int) types.ChunkID {
    _, file, line, _ := runtime.Caller(0)
    
    hasher := sha256.New()
    hasher.Write([]byte(fmt.Sprintf("%s:%d:%s", fileID.String(), index, content)))
    hash := hex.EncodeToString(hasher.Sum(nil))[:16]
    
    // Log generation for traceability
    _ = fmt.Sprintf("[%s:%d] GenerateStableID: fileID=%s index=%d hash=%s", 
        file, line, fileID, index, hash)
    
    return types.ChunkID(fmt.Sprintf("chunk_%s_%d_%s", fileID.String()[:8], index, hash))
}

// ComputeContentHash generates SHA-256 hash of content
func ComputeContentHash(content string) string {
    hasher := sha256.New()
    hasher.Write([]byte(content))
    return hex.EncodeToString(hasher.Sum(nil))
}
```

### 4.2 Embedding Model

```go
// internal/model/embedding.go
package model

import (
    "encoding/binary"
    "math"
    "time"
    
    "pkg/types"
)

// Embedding represents a vector embedding
type Embedding struct {
    ID         types.EmbeddingID
    ChunkID    types.ChunkID
    ModelName  string
    Dimensions int
    Vector     []float32
    CreatedAt  time.Time
}

// SerializeVector converts float32 slice to bytes
func SerializeVector(vector []float32) []byte {
    buf := make([]byte, len(vector)*4)
    for i, v := range vector {
        binary.LittleEndian.PutUint32(buf[i*4:], math.Float32bits(v))
    }
    return buf
}

// DeserializeVector converts bytes to float32 slice
func DeserializeVector(data []byte) []float32 {
    vector := make([]float32, len(data)/4)
    for i := range vector {
        vector[i] = math.Float32frombits(binary.LittleEndian.Uint32(data[i*4:]))
    }
    return vector
}

// CosineSimilarity computes similarity between two vectors
func CosineSimilarity(a, b []float32) float32 {
    if len(a) != len(b) {
        return 0
    }
    
    var dotProduct, normA, normB float32
    for i := range a {
        dotProduct += a[i] * b[i]
        normA += a[i] * a[i]
        normB += b[i] * b[i]
    }
    
    if normA == 0 || normB == 0 {
        return 0
    }
    
    return dotProduct / (float32(math.Sqrt(float64(normA))) * float32(math.Sqrt(float64(normB))))
}
```

### 4.3 Search Result Model

```go
// internal/model/search_result.go
package model

import "pkg/types"

// SearchResult represents a search hit
type SearchResult struct {
    ChunkID     types.ChunkID  `json:"chunkId"`
    FileID      types.FileID   `json:"fileId"`
    ProjectID   types.ProjectID `json:"projectId"`
    Content     string         `json:"content"`
    Score       float64        `json:"score"`
    FTSScore    float64        `json:"ftsScore,omitempty"`
    VSSScore    float64        `json:"vssScore,omitempty"`
    Highlights  []string       `json:"highlights,omitempty"`
    Metadata    ChunkMetadata  `json:"metadata"`
}

// SearchRequest represents a search query
type SearchRequest struct {
    Query       string           `json:"query"`
    ProjectID   types.ProjectID  `json:"projectId"`
    SearchType  SearchType       `json:"searchType"`
    TopK        int              `json:"topK"`
    MinScore    float64          `json:"minScore,omitempty"`
    Filters     SearchFilters    `json:"filters,omitempty"`
}

// SearchType defines the search strategy
type SearchType string

const (
    SearchTypeFTS    SearchType = "fts"      // Full-text only
    SearchTypeVSS    SearchType = "vss"      // Vector only
    SearchTypeHybrid SearchType = "hybrid"   // Combined FTS + VSS
)

// SearchFilters for result filtering
type SearchFilters struct {
    FileTypes   []string `json:"fileTypes,omitempty"`
    FilePaths   []string `json:"filePaths,omitempty"`
    Tags        []string `json:"tags,omitempty"`
    DateFrom    string   `json:"dateFrom,omitempty"`
    DateTo      string   `json:"dateTo,omitempty"`
}

// SearchResponse contains search results
type SearchResponse struct {
    Results     []SearchResult `json:"results"`
    TotalCount  int            `json:"totalCount"`
    QueryTimeMs int64          `json:"queryTimeMs"`
    SearchType  SearchType     `json:"searchType"`
}
```

### 4.4 RAG Context Model

```go
// internal/model/rag_context.go
package model

import "pkg/types"

// RAGRequest represents a context retrieval request
type RAGRequest struct {
    Query           string          `json:"query"`
    ProjectID       types.ProjectID `json:"projectId"`
    ConversationID  string          `json:"conversationId,omitempty"`
    TopK            int             `json:"topK"`
    MaxTokens       int             `json:"maxTokens"`
    IncludeRecent   bool            `json:"includeRecent"`
    RecentCount     int             `json:"recentCount,omitempty"`
}

// RAGContext represents assembled context for AI
type RAGContext struct {
    Chunks          []ContextChunk  `json:"chunks"`
    TotalTokens     int             `json:"totalTokens"`
    RetrievalTimeMs int64           `json:"retrievalTimeMs"`
    Sources         []SourceInfo    `json:"sources"`
}

// ContextChunk is a chunk selected for RAG context
type ContextChunk struct {
    ChunkID   types.ChunkID `json:"chunkId"`
    Content   string        `json:"content"`
    Score     float64       `json:"score"`
    TokenCount int          `json:"tokenCount"`
    Source    SourceInfo    `json:"source"`
}

// SourceInfo tracks chunk provenance
type SourceInfo struct {
    FileID    types.FileID `json:"fileId"`
    FilePath  string       `json:"filePath"`
    StartLine int          `json:"startLine"`
    EndLine   int          `json:"endLine"`
}

// RAGResponse is the assembled context response
type RAGResponse struct {
    Context     RAGContext `json:"context"`
    FormattedPrompt string `json:"formattedPrompt,omitempty"`
}
```

---

## 5. Core Services

### 5.1 FTS Engine

```go
// internal/service/fts_engine.go
package service

import (
    "context"
    "database/sql"
    "fmt"
    "runtime"
    "strings"
    "time"
    
    "scout/internal/model"
    "pkg/errors"
    "pkg/logging"
    "pkg/types"
)

// FTSEngine provides full-text search capabilities
type FTSEngine struct {
    db     *sql.DB
    logger *logging.Logger
}

// NewFTSEngine creates a new FTS engine
func NewFTSEngine(db *sql.DB, logger *logging.Logger) *FTSEngine {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing FTSEngine",
        "file", file,
        "line", line,
    )
    
    return &FTSEngine{
        db:     db,
        logger: logger,
    }
}

// Search performs FTS5 search
func (e *FTSEngine) Search(ctx context.Context, req model.SearchRequest) ([]model.SearchResult, error) {
    _, file, line, _ := runtime.Caller(0)
    start := time.Now()
    
    e.logger.Debug("FTSEngine.Search starting",
        "file", file,
        "line", line,
        "query", req.Query,
        "projectId", req.ProjectID,
        "topK", req.TopK,
    )
    
    // Escape FTS5 special characters
    safeQuery := escapeFTS5Query(req.Query)
    
    query := `
        SELECT 
            c.ID,
            c.FileID,
            c.ProjectID,
            c.Content,
            c.Metadata,
            bm25(ChunksFTS) as score,
            snippet(ChunksFTS, 0, '<mark>', '</mark>', '...', 32) as highlight
        FROM ChunksFTS fts
        JOIN Chunks c ON c.rowid = fts.rowid
        WHERE ChunksFTS MATCH ?
          AND c.ProjectID = ?
        ORDER BY score
        LIMIT ?
    `
    
    rows, err := e.db.QueryContext(ctx, query, safeQuery, req.ProjectID.String(), req.TopK)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeDatabaseError,
            "FTSEngine.Search query failed",
            "file", file,
            "line", line,
            "query", req.Query,
        )
    }
    defer rows.Close()
    
    var results []model.SearchResult
    for rows.Next() {
        var result model.SearchResult
        var metadataJSON string
        var highlight string
        
        if err := rows.Scan(
            &result.ChunkID,
            &result.FileID,
            &result.ProjectID,
            &result.Content,
            &metadataJSON,
            &result.FTSScore,
            &highlight,
        ); err != nil {
            e.logger.Error("FTSEngine.Search row scan failed",
                "file", file,
                "line", line,
                "error", err,
            )
            continue
        }
        
        result.Score = result.FTSScore
        result.Highlights = []string{highlight}
        results = append(results, result)
    }
    
    e.logger.Info("FTSEngine.Search completed",
        "file", file,
        "line", line,
        "resultCount", len(results),
        "durationMs", time.Since(start).Milliseconds(),
    )
    
    return results, nil
}

// escapeFTS5Query escapes special FTS5 characters
func escapeFTS5Query(query string) string {
    // Escape special characters: " * - OR AND NOT
    replacer := strings.NewReplacer(
        `"`, `""`,
        `*`, `\*`,
        `-`, `\-`,
    )
    return replacer.Replace(query)
}
```

### 5.2 VSS Engine

```go
// internal/service/vss_engine.go
package service

import (
    "context"
    "database/sql"
    "fmt"
    "runtime"
    "time"
    
    "scout/internal/model"
    "pkg/errors"
    "pkg/logging"
    "pkg/types"
)

// VSSEngine provides vector similarity search
type VSSEngine struct {
    db              *sql.DB
    embeddingClient *EmbeddingService
    logger          *logging.Logger
    dimensions      int
}

// NewVSSEngine creates a new VSS engine
func NewVSSEngine(db *sql.DB, embeddingClient *EmbeddingService, logger *logging.Logger, dimensions int) *VSSEngine {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing VSSEngine",
        "file", file,
        "line", line,
        "dimensions", dimensions,
    )
    
    return &VSSEngine{
        db:              db,
        embeddingClient: embeddingClient,
        logger:          logger,
        dimensions:      dimensions,
    }
}

// Search performs vector similarity search
func (e *VSSEngine) Search(ctx context.Context, req model.SearchRequest) ([]model.SearchResult, error) {
    _, file, line, _ := runtime.Caller(0)
    start := time.Now()
    
    e.logger.Debug("VSSEngine.Search starting",
        "file", file,
        "line", line,
        "query", req.Query,
        "projectId", req.ProjectID,
        "topK", req.TopK,
    )
    
    // Generate query embedding
    queryVector, err := e.embeddingClient.GenerateEmbedding(ctx, req.Query)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeExternalServiceError,
            "VSSEngine.Search embedding generation failed",
            "file", file,
            "line", line,
        )
    }
    
    // Serialize query vector
    queryBlob := model.SerializeVector(queryVector)
    
    // VSS search query
    query := `
        SELECT 
            c.ID,
            c.FileID,
            c.ProjectID,
            c.Content,
            c.Metadata,
            vss.distance as score
        FROM ChunksVSS vss
        JOIN Chunks c ON c.rowid = vss.rowid
        JOIN Embeddings emb ON emb.ChunkID = c.ID
        WHERE vss_search(vss.embedding, ?)
          AND c.ProjectID = ?
        ORDER BY vss.distance ASC
        LIMIT ?
    `
    
    rows, err := e.db.QueryContext(ctx, query, queryBlob, req.ProjectID.String(), req.TopK)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeDatabaseError,
            "VSSEngine.Search query failed",
            "file", file,
            "line", line,
        )
    }
    defer rows.Close()
    
    var results []model.SearchResult
    for rows.Next() {
        var result model.SearchResult
        var metadataJSON string
        var distance float64
        
        if err := rows.Scan(
            &result.ChunkID,
            &result.FileID,
            &result.ProjectID,
            &result.Content,
            &metadataJSON,
            &distance,
        ); err != nil {
            e.logger.Error("VSSEngine.Search row scan failed",
                "file", file,
                "line", line,
                "error", err,
            )
            continue
        }
        
        // Convert distance to similarity score (1 - distance for cosine)
        result.VSSScore = 1.0 - distance
        result.Score = result.VSSScore
        results = append(results, result)
    }
    
    e.logger.Info("VSSEngine.Search completed",
        "file", file,
        "line", line,
        "resultCount", len(results),
        "durationMs", time.Since(start).Milliseconds(),
    )
    
    return results, nil
}
```

### 5.3 Hybrid Retriever

```go
// internal/service/hybrid_retriever.go
package service

import (
    "context"
    "runtime"
    "sort"
    "time"
    
    "scout/internal/model"
    "pkg/logging"
)

// HybridRetrieverConfig contains retriever configuration
type HybridRetrieverConfig struct {
    FTSWeight    float64 // Weight for FTS scores (0-1)
    VSSWeight    float64 // Weight for VSS scores (0-1)
    MMRLambda    float64 // MMR diversity parameter (0-1)
    MinScore     float64 // Minimum score threshold
}

// DefaultHybridConfig returns default configuration
func DefaultHybridConfig() HybridRetrieverConfig {
    return HybridRetrieverConfig{
        FTSWeight:  0.3,
        VSSWeight:  0.7,
        MMRLambda:  0.7,  // From memory: agentic-search-system uses 0.7
        MinScore:   0.1,
    }
}

// HybridRetriever combines FTS and VSS search
type HybridRetriever struct {
    ftsEngine *FTSEngine
    vssEngine *VSSEngine
    config    HybridRetrieverConfig
    logger    *logging.Logger
}

// NewHybridRetriever creates a new hybrid retriever
func NewHybridRetriever(
    ftsEngine *FTSEngine,
    vssEngine *VSSEngine,
    config HybridRetrieverConfig,
    logger *logging.Logger,
) *HybridRetriever {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing HybridRetriever",
        "file", file,
        "line", line,
        "ftsWeight", config.FTSWeight,
        "vssWeight", config.VSSWeight,
        "mmrLambda", config.MMRLambda,
    )
    
    return &HybridRetriever{
        ftsEngine: ftsEngine,
        vssEngine: vssEngine,
        config:    config,
        logger:    logger,
    }
}

// Search performs hybrid FTS + VSS search with MMR reranking
func (r *HybridRetriever) Search(ctx context.Context, req model.SearchRequest) (*model.SearchResponse, error) {
    _, file, line, _ := runtime.Caller(0)
    start := time.Now()
    
    r.logger.Debug("HybridRetriever.Search starting",
        "file", file,
        "line", line,
        "query", req.Query,
        "projectId", req.ProjectID,
        "searchType", req.SearchType,
    )
    
    var results []model.SearchResult
    
    switch req.SearchType {
    case model.SearchTypeFTS:
        ftsResults, err := r.ftsEngine.Search(ctx, req)
        if err != nil {
            return nil, err
        }
        results = ftsResults
        
    case model.SearchTypeVSS:
        vssResults, err := r.vssEngine.Search(ctx, req)
        if err != nil {
            return nil, err
        }
        results = vssResults
        
    case model.SearchTypeHybrid:
        // Parallel execution of both searches
        ftsReq := req
        ftsReq.TopK = req.TopK * 2 // Over-fetch for reranking
        
        vssReq := req
        vssReq.TopK = req.TopK * 2
        
        // Execute in parallel
        type searchResult struct {
            results []model.SearchResult
            err     error
        }
        
        ftsChan := make(chan searchResult, 1)
        vssChan := make(chan searchResult, 1)
        
        go func() {
            res, err := r.ftsEngine.Search(ctx, ftsReq)
            ftsChan <- searchResult{res, err}
        }()
        
        go func() {
            res, err := r.vssEngine.Search(ctx, vssReq)
            vssChan <- searchResult{res, err}
        }()
        
        ftsRes := <-ftsChan
        vssRes := <-vssChan
        
        if ftsRes.err != nil {
            r.logger.Warn("FTS search failed, using VSS only",
                "file", file,
                "line", line,
                "error", ftsRes.err,
            )
        }
        
        if vssRes.err != nil {
            r.logger.Warn("VSS search failed, using FTS only",
                "file", file,
                "line", line,
                "error", vssRes.err,
            )
        }
        
        // Merge and rerank results
        results = r.mergeAndRerank(ftsRes.results, vssRes.results, req.TopK)
    
    default:
        // Default to hybrid
        req.SearchType = model.SearchTypeHybrid
        return r.Search(ctx, req)
    }
    
    // Apply minimum score filter
    filtered := make([]model.SearchResult, 0, len(results))
    for _, result := range results {
        if result.Score >= req.MinScore {
            filtered = append(filtered, result)
        }
    }
    
    // Limit to TopK
    if len(filtered) > req.TopK {
        filtered = filtered[:req.TopK]
    }
    
    response := &model.SearchResponse{
        Results:     filtered,
        TotalCount:  len(filtered),
        QueryTimeMs: time.Since(start).Milliseconds(),
        SearchType:  req.SearchType,
    }
    
    r.logger.Info("HybridRetriever.Search completed",
        "file", file,
        "line", line,
        "resultCount", len(filtered),
        "queryTimeMs", response.QueryTimeMs,
    )
    
    return response, nil
}

// mergeAndRerank combines FTS and VSS results using weighted scoring and MMR
func (r *HybridRetriever) mergeAndRerank(ftsResults, vssResults []model.SearchResult, topK int) []model.SearchResult {
    _, file, line, _ := runtime.Caller(0)
    
    // Build chunk ID to result map
    resultMap := make(map[string]*model.SearchResult)
    
    // Normalize FTS scores (BM25 can be > 1)
    maxFTS := 0.0
    for _, res := range ftsResults {
        if res.FTSScore > maxFTS {
            maxFTS = res.FTSScore
        }
    }
    
    // Add FTS results
    for _, res := range ftsResults {
        key := string(res.ChunkID)
        normalizedFTS := res.FTSScore
        if maxFTS > 0 {
            normalizedFTS = res.FTSScore / maxFTS
        }
        
        if existing, ok := resultMap[key]; ok {
            existing.FTSScore = normalizedFTS
        } else {
            resCopy := res
            resCopy.FTSScore = normalizedFTS
            resultMap[key] = &resCopy
        }
    }
    
    // Add VSS results
    for _, res := range vssResults {
        key := string(res.ChunkID)
        if existing, ok := resultMap[key]; ok {
            existing.VSSScore = res.VSSScore
        } else {
            resultMap[key] = &res
        }
    }
    
    // Calculate combined scores
    results := make([]model.SearchResult, 0, len(resultMap))
    for _, res := range resultMap {
        res.Score = r.config.FTSWeight*res.FTSScore + r.config.VSSWeight*res.VSSScore
        results = append(results, *res)
    }
    
    // Sort by combined score
    sort.Slice(results, func(i, j int) bool {
        return results[i].Score > results[j].Score
    })
    
    // Apply MMR for diversity (simplified implementation)
    selected := r.applyMMR(results, topK)
    
    r.logger.Debug("mergeAndRerank completed",
        "file", file,
        "line", line,
        "ftsCount", len(ftsResults),
        "vssCount", len(vssResults),
        "mergedCount", len(results),
        "selectedCount", len(selected),
    )
    
    return selected
}

// applyMMR applies Maximal Marginal Relevance for diversity
func (r *HybridRetriever) applyMMR(candidates []model.SearchResult, topK int) []model.SearchResult {
    if len(candidates) <= topK {
        return candidates
    }
    
    selected := make([]model.SearchResult, 0, topK)
    remaining := make([]model.SearchResult, len(candidates))
    copy(remaining, candidates)
    
    // Always select the top result first
    selected = append(selected, remaining[0])
    remaining = remaining[1:]
    
    // Iteratively select remaining results
    for len(selected) < topK && len(remaining) > 0 {
        bestIdx := 0
        bestMMR := -1.0
        
        for i, candidate := range remaining {
            // Calculate max similarity to already selected results
            maxSim := 0.0
            for _, sel := range selected {
                // Use content overlap as proxy for similarity
                sim := r.contentSimilarity(candidate.Content, sel.Content)
                if sim > maxSim {
                    maxSim = sim
                }
            }
            
            // MMR score
            mmr := r.config.MMRLambda*candidate.Score - (1-r.config.MMRLambda)*maxSim
            
            if mmr > bestMMR {
                bestMMR = mmr
                bestIdx = i
            }
        }
        
        selected = append(selected, remaining[bestIdx])
        remaining = append(remaining[:bestIdx], remaining[bestIdx+1:]...)
    }
    
    return selected
}

// contentSimilarity computes simple content overlap (Jaccard-like)
func (r *HybridRetriever) contentSimilarity(a, b string) float64 {
    // Simplified: use character overlap
    if len(a) == 0 || len(b) == 0 {
        return 0
    }
    
    // Count common words
    wordsA := make(map[string]bool)
    for _, word := range splitWords(a) {
        wordsA[word] = true
    }
    
    common := 0
    wordsB := splitWords(b)
    for _, word := range wordsB {
        if wordsA[word] {
            common++
        }
    }
    
    union := len(wordsA) + len(wordsB) - common
    if union == 0 {
        return 0
    }
    
    return float64(common) / float64(union)
}

// splitWords splits text into lowercase words
func splitWords(text string) []string {
    // Simplified word splitting
    words := make([]string, 0)
    current := ""
    for _, r := range text {
        if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') {
            current += string(r)
        } else if current != "" {
            words = append(words, current)
            current = ""
        }
    }
    if current != "" {
        words = append(words, current)
    }
    return words
}
```

### 5.4 Embedding Service

```go
// internal/service/embedding_service.go
package service

import (
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "io"
    "net/http"
    "runtime"
    "time"
    
    "scout/internal/model"
    "pkg/errors"
    "pkg/logging"
)

// EmbeddingService generates embeddings via AI-Bridge
type EmbeddingService struct {
    aiBridgeURL string
    httpClient  *http.Client
    modelName   string
    dimensions  int
    logger      *logging.Logger
}

// EmbeddingRequest for AI-Bridge
type EmbeddingRequest struct {
    Model string   `json:"model"`
    Input []string `json:"input"`
}

// EmbeddingResponse from AI-Bridge
type EmbeddingResponse struct {
    Embeddings []struct {
        Index     int       `json:"index"`
        Embedding []float32 `json:"embedding"`
    } `json:"embeddings"`
    Model string `json:"model"`
    Usage struct {
        PromptTokens int `json:"prompt_tokens"`
        TotalTokens  int `json:"total_tokens"`
    } `json:"usage"`
}

// NewEmbeddingService creates a new embedding service
func NewEmbeddingService(aiBridgeURL, modelName string, dimensions int, logger *logging.Logger) *EmbeddingService {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing EmbeddingService",
        "file", file,
        "line", line,
        "aiBridgeURL", aiBridgeURL,
        "modelName", modelName,
        "dimensions", dimensions,
    )
    
    return &EmbeddingService{
        aiBridgeURL: aiBridgeURL,
        httpClient: &http.Client{
            Timeout: 30 * time.Second,
        },
        modelName:  modelName,
        dimensions: dimensions,
        logger:     logger,
    }
}

// GenerateEmbedding generates embedding for a single text
func (s *EmbeddingService) GenerateEmbedding(ctx context.Context, text string) ([]float32, error) {
    embeddings, err := s.GenerateEmbeddings(ctx, []string{text})
    if err != nil {
        return nil, err
    }
    if len(embeddings) == 0 {
        _, file, line, _ := runtime.Caller(0)
        return nil, errors.New(errors.CodeExternalServiceError,
            "EmbeddingService: no embeddings returned",
            "file", file,
            "line", line,
        )
    }
    return embeddings[0], nil
}

// GenerateEmbeddings generates embeddings for multiple texts (batch)
func (s *EmbeddingService) GenerateEmbeddings(ctx context.Context, texts []string) ([][]float32, error) {
    _, file, line, _ := runtime.Caller(0)
    start := time.Now()
    
    s.logger.Debug("EmbeddingService.GenerateEmbeddings starting",
        "file", file,
        "line", line,
        "textCount", len(texts),
    )
    
    reqBody := EmbeddingRequest{
        Model: s.modelName,
        Input: texts,
    }
    
    jsonBody, err := json.Marshal(reqBody)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "EmbeddingService: failed to marshal request",
            "file", file,
            "line", line,
        )
    }
    
    url := fmt.Sprintf("%s/v1/embeddings", s.aiBridgeURL)
    req, err := http.NewRequestWithContext(ctx, http.MethodPost, url, bytes.NewReader(jsonBody))
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "EmbeddingService: failed to create request",
            "file", file,
            "line", line,
        )
    }
    
    req.Header.Set("Content-Type", "application/json")
    
    resp, err := s.httpClient.Do(req)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeExternalServiceError,
            "EmbeddingService: request failed",
            "file", file,
            "line", line,
            "url", url,
        )
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != http.StatusOK {
        body, _ := io.ReadAll(resp.Body)
        return nil, errors.New(errors.CodeExternalServiceError,
            "EmbeddingService: non-OK response",
            "file", file,
            "line", line,
            "status", resp.StatusCode,
            "body", string(body),
        )
    }
    
    var embResp EmbeddingResponse
    if err := json.NewDecoder(resp.Body).Decode(&embResp); err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "EmbeddingService: failed to decode response",
            "file", file,
            "line", line,
        )
    }
    
    // Extract embeddings in order
    embeddings := make([][]float32, len(texts))
    for _, emb := range embResp.Embeddings {
        if emb.Index < len(embeddings) {
            embeddings[emb.Index] = emb.Embedding
        }
    }
    
    s.logger.Info("EmbeddingService.GenerateEmbeddings completed",
        "file", file,
        "line", line,
        "textCount", len(texts),
        "tokensUsed", embResp.Usage.TotalTokens,
        "durationMs", time.Since(start).Milliseconds(),
    )
    
    return embeddings, nil
}
```

### 5.5 RAG Pipeline

```go
// internal/service/rag_pipeline.go
package service

import (
    "context"
    "fmt"
    "runtime"
    "strings"
    "time"
    
    "scout/internal/model"
    "pkg/errors"
    "pkg/logging"
    "pkg/types"
)

// RAGPipelineConfig contains pipeline configuration
type RAGPipelineConfig struct {
    DefaultTopK      int
    DefaultMaxTokens int
    RecentChunkCount int
    TokensPerChunk   int  // Estimated tokens per chunk
    ContextTemplate  string
}

// DefaultRAGConfig returns default configuration
func DefaultRAGConfig() RAGPipelineConfig {
    return RAGPipelineConfig{
        DefaultTopK:      10,
        DefaultMaxTokens: 4000,
        RecentChunkCount: 3,
        TokensPerChunk:   200,
        ContextTemplate:  "### Context from %s (lines %d-%d):\n%s\n",
    }
}

// RAGPipeline assembles context for AI prompts
type RAGPipeline struct {
    retriever    *HybridRetriever
    chunkRepo    ChunkRepository
    config       RAGPipelineConfig
    logger       *logging.Logger
}

// ChunkRepository interface for chunk access
type ChunkRepository interface {
    GetRecentChunks(ctx context.Context, projectID types.ProjectID, limit int) ([]model.Chunk, error)
    GetChunkByID(ctx context.Context, chunkID types.ChunkID) (*model.Chunk, error)
}

// NewRAGPipeline creates a new RAG pipeline
func NewRAGPipeline(
    retriever *HybridRetriever,
    chunkRepo ChunkRepository,
    config RAGPipelineConfig,
    logger *logging.Logger,
) *RAGPipeline {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing RAGPipeline",
        "file", file,
        "line", line,
        "defaultTopK", config.DefaultTopK,
        "defaultMaxTokens", config.DefaultMaxTokens,
    )
    
    return &RAGPipeline{
        retriever: retriever,
        chunkRepo: chunkRepo,
        config:    config,
        logger:    logger,
    }
}

// RetrieveContext assembles RAG context for a query
func (p *RAGPipeline) RetrieveContext(ctx context.Context, req model.RAGRequest) (*model.RAGResponse, error) {
    _, file, line, _ := runtime.Caller(0)
    start := time.Now()
    
    p.logger.Debug("RAGPipeline.RetrieveContext starting",
        "file", file,
        "line", line,
        "query", req.Query,
        "projectId", req.ProjectID,
        "topK", req.TopK,
    )
    
    // Apply defaults
    if req.TopK == 0 {
        req.TopK = p.config.DefaultTopK
    }
    if req.MaxTokens == 0 {
        req.MaxTokens = p.config.DefaultMaxTokens
    }
    
    var contextChunks []model.ContextChunk
    totalTokens := 0
    sources := make(map[string]model.SourceInfo)
    
    // Step 1: Get recent chunks if requested
    if req.IncludeRecent {
        recentCount := req.RecentCount
        if recentCount == 0 {
            recentCount = p.config.RecentChunkCount
        }
        
        recentChunks, err := p.chunkRepo.GetRecentChunks(ctx, req.ProjectID, recentCount)
        if err != nil {
            p.logger.Warn("Failed to get recent chunks",
                "file", file,
                "line", line,
                "error", err,
            )
        } else {
            for _, chunk := range recentChunks {
                tokenCount := p.estimateTokens(chunk.Content)
                if totalTokens+tokenCount > req.MaxTokens {
                    break
                }
                
                source := model.SourceInfo{
                    FileID:    chunk.FileID,
                    FilePath:  chunk.Metadata.FileName,
                    StartLine: chunk.StartLine,
                    EndLine:   chunk.EndLine,
                }
                
                contextChunks = append(contextChunks, model.ContextChunk{
                    ChunkID:    chunk.ID,
                    Content:    chunk.Content,
                    Score:      1.0, // Recent chunks get max score
                    TokenCount: tokenCount,
                    Source:     source,
                })
                
                totalTokens += tokenCount
                sources[string(chunk.FileID)] = source
            }
        }
    }
    
    // Step 2: Semantic search for relevant chunks
    searchReq := model.SearchRequest{
        Query:      req.Query,
        ProjectID:  req.ProjectID,
        SearchType: model.SearchTypeHybrid,
        TopK:       req.TopK,
        MinScore:   0.3,
    }
    
    searchResp, err := p.retriever.Search(ctx, searchReq)
    if err != nil {
        return nil, errors.Wrap(err, errors.CodeInternalError,
            "RAGPipeline: search failed",
            "file", file,
            "line", line,
        )
    }
    
    // Step 3: Add search results respecting token limit
    for _, result := range searchResp.Results {
        tokenCount := p.estimateTokens(result.Content)
        if totalTokens+tokenCount > req.MaxTokens {
            break
        }
        
        // Skip if already included from recent
        alreadyIncluded := false
        for _, existing := range contextChunks {
            if existing.ChunkID == result.ChunkID {
                alreadyIncluded = true
                break
            }
        }
        if alreadyIncluded {
            continue
        }
        
        source := model.SourceInfo{
            FileID:    result.FileID,
            FilePath:  result.Metadata.FileName,
            StartLine: 0, // Would need to join with chunk data
            EndLine:   0,
        }
        
        contextChunks = append(contextChunks, model.ContextChunk{
            ChunkID:    result.ChunkID,
            Content:    result.Content,
            Score:      result.Score,
            TokenCount: tokenCount,
            Source:     source,
        })
        
        totalTokens += tokenCount
        sources[string(result.FileID)] = source
    }
    
    // Build sources list
    sourceList := make([]model.SourceInfo, 0, len(sources))
    for _, source := range sources {
        sourceList = append(sourceList, source)
    }
    
    ragContext := model.RAGContext{
        Chunks:          contextChunks,
        TotalTokens:     totalTokens,
        RetrievalTimeMs: time.Since(start).Milliseconds(),
        Sources:         sourceList,
    }
    
    // Format prompt if chunks available
    formattedPrompt := p.formatPrompt(contextChunks)
    
    p.logger.Info("RAGPipeline.RetrieveContext completed",
        "file", file,
        "line", line,
        "chunkCount", len(contextChunks),
        "totalTokens", totalTokens,
        "sourceCount", len(sourceList),
        "durationMs", ragContext.RetrievalTimeMs,
    )
    
    return &model.RAGResponse{
        Context:         ragContext,
        FormattedPrompt: formattedPrompt,
    }, nil
}

// estimateTokens estimates token count for text
func (p *RAGPipeline) estimateTokens(text string) int {
    // Rough estimate: ~4 characters per token for English
    return len(text) / 4
}

// formatPrompt formats chunks into a prompt string
func (p *RAGPipeline) formatPrompt(chunks []model.ContextChunk) string {
    if len(chunks) == 0 {
        return ""
    }
    
    var sb strings.Builder
    sb.WriteString("## Relevant Context\n\n")
    
    for i, chunk := range chunks {
        sb.WriteString(fmt.Sprintf(p.config.ContextTemplate,
            chunk.Source.FilePath,
            chunk.Source.StartLine,
            chunk.Source.EndLine,
            chunk.Content,
        ))
        
        if i < len(chunks)-1 {
            sb.WriteString("\n---\n\n")
        }
    }
    
    return sb.String()
}
```

### 5.6 Chunker Service

```go
// internal/service/chunker.go
package service

import (
    "crypto/sha256"
    "encoding/hex"
    "regexp"
    "runtime"
    "strings"
    
    "scout/internal/model"
    "pkg/logging"
    "pkg/types"
)

// ChunkerConfig contains chunking configuration
type ChunkerConfig struct {
    MaxChunkSize     int      // Maximum characters per chunk
    MinChunkSize     int      // Minimum characters per chunk
    OverlapSize      int      // Character overlap between chunks
    SplitPatterns    []string // Regex patterns for split points
}

// DefaultChunkerConfig returns default configuration
func DefaultChunkerConfig() ChunkerConfig {
    return ChunkerConfig{
        MaxChunkSize:  1500,
        MinChunkSize:  200,
        OverlapSize:   100,
        SplitPatterns: []string{
            `(?m)^#{1,6}\s`,    // Markdown headers
            `(?m)^---\s*$`,     // Horizontal rules
            `(?m)^\s*$\n\s*$`,  // Double newlines
            `\n\n`,             // Paragraph breaks
        },
    }
}

// Chunker splits content into indexable chunks
type Chunker struct {
    config     ChunkerConfig
    patterns   []*regexp.Regexp
    logger     *logging.Logger
}

// NewChunker creates a new chunker
func NewChunker(config ChunkerConfig, logger *logging.Logger) *Chunker {
    _, file, line, _ := runtime.Caller(0)
    logger.Info("Initializing Chunker",
        "file", file,
        "line", line,
        "maxChunkSize", config.MaxChunkSize,
        "overlapSize", config.OverlapSize,
    )
    
    patterns := make([]*regexp.Regexp, 0, len(config.SplitPatterns))
    for _, pattern := range config.SplitPatterns {
        if re, err := regexp.Compile(pattern); err == nil {
            patterns = append(patterns, re)
        }
    }
    
    return &Chunker{
        config:   config,
        patterns: patterns,
        logger:   logger,
    }
}

// ChunkResult contains chunking output
type ChunkResult struct {
    Chunks      []model.Chunk
    ContentHash string
}

// Chunk splits content into chunks with stable IDs
func (c *Chunker) Chunk(fileID types.FileID, projectID types.ProjectID, content string, metadata model.ChunkMetadata) *ChunkResult {
    _, file, line, _ := runtime.Caller(0)
    
    c.logger.Debug("Chunker.Chunk starting",
        "file", file,
        "line", line,
        "fileId", fileID,
        "contentLength", len(content),
    )
    
    // Compute content hash for change detection
    hasher := sha256.New()
    hasher.Write([]byte(content))
    contentHash := hex.EncodeToString(hasher.Sum(nil))
    
    // Split content into raw segments
    segments := c.splitContent(content)
    
    // Convert segments to chunks
    chunks := make([]model.Chunk, 0, len(segments))
    currentLine := 1
    
    for i, segment := range segments {
        if len(strings.TrimSpace(segment.Text)) < c.config.MinChunkSize {
            continue
        }
        
        endLine := currentLine + strings.Count(segment.Text, "\n")
        
        chunk := model.Chunk{
            ID:          model.GenerateStableID(fileID, segment.Text, i),
            FileID:      fileID,
            ProjectID:   projectID,
            Content:     segment.Text,
            ContentHash: model.ComputeContentHash(segment.Text),
            ChunkIndex:  i,
            StartLine:   currentLine,
            EndLine:     endLine,
            Metadata:    metadata,
            Status:      model.ChunkStatusPending,
        }
        
        chunks = append(chunks, chunk)
        currentLine = endLine
    }
    
    c.logger.Info("Chunker.Chunk completed",
        "file", file,
        "line", line,
        "fileId", fileID,
        "chunkCount", len(chunks),
        "contentHash", contentHash[:16],
    )
    
    return &ChunkResult{
        Chunks:      chunks,
        ContentHash: contentHash,
    }
}

// segment represents a text segment
type segment struct {
    Text       string
    StartIndex int
}

// splitContent splits content using configured patterns
func (c *Chunker) splitContent(content string) []segment {
    // Find all split points
    type splitPoint struct {
        index int
        size  int
    }
    
    var points []splitPoint
    
    for _, pattern := range c.patterns {
        matches := pattern.FindAllStringIndex(content, -1)
        for _, match := range matches {
            points = append(points, splitPoint{match[0], match[1] - match[0]})
        }
    }
    
    // Sort split points by index
    for i := 0; i < len(points); i++ {
        for j := i + 1; j < len(points); j++ {
            if points[j].index < points[i].index {
                points[i], points[j] = points[j], points[i]
            }
        }
    }
    
    // Create segments
    segments := make([]segment, 0)
    lastEnd := 0
    
    for _, point := range points {
        if point.index > lastEnd {
            text := content[lastEnd:point.index]
            if len(text) > 0 {
                // Handle oversized segments
                if len(text) > c.config.MaxChunkSize {
                    subSegments := c.splitLargeSegment(text, lastEnd)
                    segments = append(segments, subSegments...)
                } else {
                    segments = append(segments, segment{
                        Text:       text,
                        StartIndex: lastEnd,
                    })
                }
            }
        }
        lastEnd = point.index + point.size
    }
    
    // Add remaining content
    if lastEnd < len(content) {
        text := content[lastEnd:]
        if len(text) > c.config.MaxChunkSize {
            subSegments := c.splitLargeSegment(text, lastEnd)
            segments = append(segments, subSegments...)
        } else if len(text) > 0 {
            segments = append(segments, segment{
                Text:       text,
                StartIndex: lastEnd,
            })
        }
    }
    
    return segments
}

// splitLargeSegment splits a segment that exceeds max size
func (c *Chunker) splitLargeSegment(text string, startIndex int) []segment {
    segments := make([]segment, 0)
    
    for i := 0; i < len(text); i += c.config.MaxChunkSize - c.config.OverlapSize {
        end := i + c.config.MaxChunkSize
        if end > len(text) {
            end = len(text)
        }
        
        segments = append(segments, segment{
            Text:       text[i:end],
            StartIndex: startIndex + i,
        })
        
        if end == len(text) {
            break
        }
    }
    
    return segments
}
```

---

## 6. API Endpoints

### 6.1 Search Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/search` | Perform search |
| POST | `/api/v1/search/fts` | Full-text search only |
| POST | `/api/v1/search/vss` | Vector search only |
| POST | `/api/v1/search/hybrid` | Hybrid search |

### 6.2 Index Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/index` | Index a file |
| POST | `/api/v1/index/batch` | Batch index files |
| DELETE | `/api/v1/index/{fileId}` | Remove file from index |
| POST | `/api/v1/index/reindex` | Reindex project |
| GET | `/api/v1/index/status` | Get indexing status |

### 6.3 RAG Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/rag/context` | Retrieve RAG context |
| POST | `/api/v1/rag/chunks` | Get specific chunks |

### 6.4 Health Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Health check |
| GET | `/health/ready` | Readiness check |

---

## 7. Request/Response Examples

### 7.1 Hybrid Search

**Request:**
```json
POST /api/v1/search
{
    "query": "authentication middleware implementation",
    "projectId": "proj_abc123",
    "searchType": "hybrid",
    "topK": 10,
    "minScore": 0.3,
    "filters": {
        "fileTypes": ["go", "md"],
        "tags": ["security"]
    }
}
```

**Response:**
```json
{
    "results": [
        {
            "chunkId": "chunk_a1b2c3_0_xyz789",
            "fileId": "file_def456",
            "projectId": "proj_abc123",
            "content": "// AuthMiddleware validates JWT tokens...",
            "score": 0.87,
            "ftsScore": 0.65,
            "vssScore": 0.92,
            "highlights": ["<mark>authentication</mark> <mark>middleware</mark>..."],
            "metadata": {
                "fileName": "auth_middleware.go",
                "fileType": "go",
                "section": "Middleware"
            }
        }
    ],
    "totalCount": 1,
    "queryTimeMs": 45,
    "searchType": "hybrid"
}
```

### 7.2 RAG Context Retrieval

**Request:**
```json
POST /api/v1/rag/context
{
    "query": "How is user authentication implemented?",
    "projectId": "proj_abc123",
    "topK": 5,
    "maxTokens": 4000,
    "includeRecent": true,
    "recentCount": 2
}
```

**Response:**
```json
{
    "context": {
        "chunks": [
            {
                "chunkId": "chunk_a1b2c3_0_xyz789",
                "content": "// AuthMiddleware validates JWT tokens...",
                "score": 0.92,
                "tokenCount": 180,
                "source": {
                    "fileId": "file_def456",
                    "filePath": "internal/middleware/auth.go",
                    "startLine": 15,
                    "endLine": 45
                }
            }
        ],
        "totalTokens": 1250,
        "retrievalTimeMs": 78,
        "sources": [
            {
                "fileId": "file_def456",
                "filePath": "internal/middleware/auth.go",
                "startLine": 15,
                "endLine": 45
            }
        ]
    },
    "formattedPrompt": "## Relevant Context\n\n### Context from internal/middleware/auth.go..."
}
```

---

## 8. Error Codes

Scout uses error code range **9xxx**:

| Code | Name | Description |
|------|------|-------------|
| 9001 | `ErrSearchFailed` | Search query execution failed |
| 9002 | `ErrIndexFailed` | Indexing operation failed |
| 9003 | `ErrEmbeddingFailed` | Embedding generation failed |
| 9004 | `ErrChunkingFailed` | Content chunking failed |
| 9005 | `ErrRAGFailed` | RAG context assembly failed |
| 9006 | `ErrVSSNotAvailable` | VSS extension not loaded |
| 9007 | `ErrProjectNotIndexed` | Project has no indexed content |
| 9008 | `ErrInvalidSearchType` | Unknown search type specified |

### 8.1 Error Implementation

```go
// internal/errors.go
package internal

import (
    "runtime"
    
    "pkg/errors"
)

// Scout-specific error codes (9xxx range)
const (
    ErrSearchFailed      = 9001
    ErrIndexFailed       = 9002
    ErrEmbeddingFailed   = 9003
    ErrChunkingFailed    = 9004
    ErrRAGFailed         = 9005
    ErrVSSNotAvailable   = 9006
    ErrProjectNotIndexed = 9007
    ErrInvalidSearchType = 9008
)

// NewSearchError creates a search error with stack trace
// MANDATORY: All errors capture stack trace and caller info
func NewSearchError(message string, details ...interface{}) *errors.AppError {
    _, file, line, _ := runtime.Caller(1)
    
    allDetails := append([]interface{}{
        "file", file,
        "line", line,
    }, details...)
    
    return errors.NewWithStack(ErrSearchFailed, message, allDetails...)
}

// NewIndexError creates an indexing error with stack trace
func NewIndexError(message string, details ...interface{}) *errors.AppError {
    _, file, line, _ := runtime.Caller(1)
    
    allDetails := append([]interface{}{
        "file", file,
        "line", line,
    }, details...)
    
    return errors.NewWithStack(ErrIndexFailed, message, allDetails...)
}

// NewEmbeddingError creates an embedding error with stack trace
func NewEmbeddingError(message string, details ...interface{}) *errors.AppError {
    _, file, line, _ := runtime.Caller(1)
    
    allDetails := append([]interface{}{
        "file", file,
        "line", line,
    }, details...)
    
    return errors.NewWithStack(ErrEmbeddingFailed, message, allDetails...)
}

// NewRAGError creates a RAG error with stack trace
func NewRAGError(message string, details ...interface{}) *errors.AppError {
    _, file, line, _ := runtime.Caller(1)
    
    allDetails := append([]interface{}{
        "file", file,
        "line", line,
    }, details...)
    
    return errors.NewWithStack(ErrRAGFailed, message, allDetails...)
}
```

---

## 9. Configuration

```yaml
# config/scout.yaml
service:
  name: scout
  port: 8084
  host: "0.0.0.0"

database:
  path: "./data/scout.db"
  maxOpenConns: 25
  maxIdleConns: 5
  connMaxLifetime: "5m"

fts:
  tokenizer: "porter unicode61"
  
vss:
  enabled: true
  dimensions: 1536
  distanceMetric: "cosine"

embedding:
  model: "nomic-embed-text"
  dimensions: 1536
  batchSize: 32
  timeout: "30s"

chunking:
  maxChunkSize: 1500
  minChunkSize: 200
  overlapSize: 100

retrieval:
  ftsWeight: 0.3
  vssWeight: 0.7
  mmrLambda: 0.7
  defaultTopK: 10
  minScore: 0.1

rag:
  defaultTopK: 10
  defaultMaxTokens: 4000
  recentChunkCount: 3
  tokensPerChunk: 200

aibridge:
  url: "http://localhost:8082"
  timeout: "30s"

logging:
  level: "info"
  format: "json"
  addSource: true  # MANDATORY: Include function names and line numbers
```

---

## 10. Metrics

Scout exposes Prometheus metrics:

| Metric | Type | Description |
|--------|------|-------------|
| `scout_search_requests_total` | Counter | Total search requests by type |
| `scout_search_duration_seconds` | Histogram | Search latency |
| `scout_index_operations_total` | Counter | Total indexing operations |
| `scout_chunks_total` | Gauge | Total indexed chunks |
| `scout_embeddings_total` | Gauge | Total embeddings |
| `scout_rag_context_tokens` | Histogram | RAG context token counts |
| `scout_embedding_latency_seconds` | Histogram | Embedding generation latency |

---

## 11. Integration Points

### 11.1 AI-Bridge Integration

Scout calls AI-Bridge for embedding generation:

```
Scout                           AI-Bridge
  │                                │
  │  POST /v1/embeddings           │
  │  {model, input[]}              │
  │ ──────────────────────────────>│
  │                                │
  │  {embeddings[], usage}         │
  │ <──────────────────────────────│
  │                                │
```

### 11.2 SpecManager Integration

Scout fetches file content from SpecManager:

```
Scout                          SpecManager
  │                                │
  │  GET /api/v1/specs/{id}/content│
  │ ──────────────────────────────>│
  │                                │
  │  {content, metadata}           │
  │ <──────────────────────────────│
  │                                │
```

---

## 12. Performance Targets

| Metric | Target |
|--------|--------|
| FTS search latency | < 50ms for 10K chunks |
| VSS search latency | < 100ms for 10K chunks |
| Hybrid search latency | < 150ms for 10K chunks |
| Embedding generation | < 500ms per batch (32 texts) |
| RAG context assembly | < 200ms |
| Indexing throughput | > 100 files/minute |

---

## 13. Security Considerations

1. **Input Sanitization**: All search queries are sanitized to prevent FTS5 injection
2. **Project Isolation**: All queries are scoped to a specific ProjectID
3. **Rate Limiting**: Search endpoints are rate-limited per project
4. **Embedding Privacy**: Embeddings are generated locally via AI-Bridge

---

## 14. References

- [FTS5 Documentation](https://www.sqlite.org/fts5.html)
- [sqlite-vss Extension](https://github.com/asg017/sqlite-vss)
- [RAG Best Practices](https://arxiv.org/abs/2312.10997)
- Phase 5: AI-Bridge Specification (`04-ai-bridge.md`)
- Memory: `features/agentic-search-system` (MMR algorithm, scoring)
- Memory: `features/golang-search-cli` (FTS5, VSS, hybrid scoring)
