# Vector Search Service Implementation

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This specification defines the `VectorSearchService` implementation for sqlite-vss integration, hybrid scoring algorithms, and the complete Phase 1 vector search enhancement. This service provides semantic search capabilities alongside the existing FTS5 keyword search.

**Cross-References:**
- [Vector Database Plan](./04-vector-database-plan.md) - Overall enhancement strategy
- [RAG System](./01-rag-system.md) - Retrieval pipeline integration
- [Database Schema](../../07-database-design/01-schema.md) - GORM models
- [AI Integration](../06-ai-integration/01-ai-integration.md) - Embedding generation

---

## 21.1 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      VECTOR SEARCH SERVICE ARCHITECTURE                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐     │
│  │                         VectorSearchService                          │     │
│  ├─────────────────────────────────────────────────────────────────────┤     │
│  │                                                                       │     │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │     │
│  │  │ Initialize  │  │   Index     │  │   Search    │  │  Reindex    │ │     │
│  │  │ VssTable    │  │  Embedding  │  │  Similar    │  │  Project    │ │     │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘ │     │
│  │                                                                       │     │
│  └─────────────────────────────────────────────────────────────────────┘     │
│                                    │                                          │
│                    ┌───────────────┼───────────────┐                          │
│                    ▼               ▼               ▼                          │
│  ┌─────────────────────┐ ┌─────────────────┐ ┌─────────────────────────┐     │
│  │   sqlite-vss        │ │   FTS5 Index    │ │  Hybrid Fusion Layer    │     │
│  │   VssEmbedding      │ │   ChunkFts      │ │  (RRF Algorithm)        │     │
│  │   (Virtual Table)   │ │   (Virtual)     │ │                         │     │
│  └─────────────────────┘ └─────────────────┘ └─────────────────────────┘     │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 21.2 Service Interface

### Core Interface Definition

```go
package services

import (
    "context"
    "time"
)

// ChunkScore represents a scored search result
type ChunkScore struct {
    ChunkId       string  `json:"chunkId"`
    ArtifactId    string  `json:"artifactId"`
    Score         float64 `json:"score"`
    ScoreType     string  `json:"scoreType"` // "semantic" | "keyword" | "hybrid"
    Content       string  `json:"content"`
    SectionAnchor string  `json:"sectionAnchor"`
}

// VectorSearchConfig holds configuration for vector search
type VectorSearchConfig struct {
    Dimensions           int     // Embedding dimensions (default: 768)
    DefaultLimit         int     // Default top-K results (default: 10)
    MinSimilarityScore   float64 // Minimum cosine similarity threshold (default: 0.5)
    HybridSemanticWeight float64 // Weight for semantic scores in hybrid (default: 0.6)
    HybridKeywordWeight  float64 // Weight for keyword scores in hybrid (default: 0.4)
    RRFConstant          int     // RRF constant k (default: 60)
}

// VectorSearchService defines the vector search interface
type VectorSearchService interface {
    // Initialization
    Initialize(ctx context.Context) error
    InitializeForProject(ctx context.Context, projectId string) error
    
    // Indexing
    IndexEmbedding(ctx context.Context, chunkId string, embedding []float32) error
    IndexBatch(ctx context.Context, embeddings map[string][]float32) error
    RemoveEmbedding(ctx context.Context, chunkId string) error
    RemoveByArtifact(ctx context.Context, artifactId string) error
    
    // Searching
    SearchSemantic(ctx context.Context, queryEmbedding []float32, limit int) ([]ChunkScore, error)
    SearchKeyword(ctx context.Context, query string, limit int) ([]ChunkScore, error)
    SearchHybrid(ctx context.Context, queryEmbedding []float32, queryText string, limit int) ([]ChunkScore, error)
    
    // Maintenance
    ReindexProject(ctx context.Context, projectId string) error
    GetIndexStats(ctx context.Context, projectId string) (*VectorIndexStats, error)
    ClearCache(ctx context.Context, projectId string) error
    
    // Health
    HealthCheck(ctx context.Context) error
    IsVssAvailable() bool
}

// VectorIndexStats contains statistics about the vector index
type VectorIndexStats struct {
    ProjectId      string    `json:"projectId"`
    TotalVectors   int       `json:"totalVectors"`
    Dimensions     int       `json:"dimensions"`
    IndexType      string    `json:"indexType"`
    IndexSizeBytes int64     `json:"indexSizeBytes"`
    LastReindexAt  time.Time `json:"lastReindexAt"`
    VssEnabled     bool      `json:"vssEnabled"`
    Fts5Enabled    bool      `json:"fts5Enabled"`
}
```

---

## 21.3 SQLite-VSS Implementation

### Virtual Table Creation

```go
package services

import (
    "context"
    "encoding/binary"
    "fmt"
    "math"

    "gorm.io/gorm"
)

// VectorSearchServiceImpl implements VectorSearchService using sqlite-vss
type VectorSearchServiceImpl struct {
    db        *gorm.DB
    config    VectorSearchConfig
    vssLoaded bool
}

// NewVectorSearchService creates a new vector search service
func NewVectorSearchService(db *gorm.DB, config VectorSearchConfig) *VectorSearchServiceImpl {
    if config.Dimensions == 0 {
        config.Dimensions = 768
    }
    if config.DefaultLimit == 0 {
        config.DefaultLimit = 10
    }
    if config.MinSimilarityScore == 0 {
        config.MinSimilarityScore = 0.5
    }
    if config.HybridSemanticWeight == 0 {
        config.HybridSemanticWeight = 0.6
    }
    if config.HybridKeywordWeight == 0 {
        config.HybridKeywordWeight = 0.4
    }
    if config.RRFConstant == 0 {
        config.RRFConstant = 60
    }
    
    return &VectorSearchServiceImpl{
        db:     db,
        config: config,
    }
}

// Initialize loads sqlite-vss extension and creates virtual tables
// NOTE: This is an authorized exception to the ORM-only policy for virtual tables
func (v *VectorSearchServiceImpl) Initialize(ctx context.Context) error {
    // 1. Load sqlite-vss extension
    if err := v.loadVssExtension(ctx); err != nil {
        // Log warning but continue - fallback to FTS5-only mode
        v.vssLoaded = false
        return nil // Graceful degradation
    }
    v.vssLoaded = true
    
    // 2. Create vss0 virtual table for embeddings
    createVssTable := fmt.Sprintf(`
        CREATE VIRTUAL TABLE IF NOT EXISTS VssEmbedding USING vss0(
            embedding(%d),
            chunk_id TEXT
        );
    `, v.config.Dimensions)
    
    if err := v.db.WithContext(ctx).Exec(createVssTable).Error; err != nil {
        return fmt.Errorf("failed to create VssEmbedding table: %w", err)
    }
    
    // 3. Ensure FTS5 table exists (already defined in 16-rag-system.md)
    createFtsTable := `
        CREATE VIRTUAL TABLE IF NOT EXISTS ChunkFts USING fts5(
            chunk_id UNINDEXED,
            content,
            section_anchor UNINDEXED,
            tokenize='porter unicode61'
        );
    `
    
    if err := v.db.WithContext(ctx).Exec(createFtsTable).Error; err != nil {
        return fmt.Errorf("failed to create ChunkFts table: %w", err)
    }
    
    return nil
}

// loadVssExtension attempts to load the sqlite-vss extension
func (v *VectorSearchServiceImpl) loadVssExtension(ctx context.Context) error {
    // Extension loading is platform-specific
    // The path should be configured in config table
    result := v.db.WithContext(ctx).Exec(`SELECT load_extension('vss0')`)
    return result.Error
}

// IsVssAvailable returns whether sqlite-vss is loaded
func (v *VectorSearchServiceImpl) IsVssAvailable() bool {
    return v.vssLoaded
}
```

---

## 21.4 Embedding Operations

### Indexing Embeddings

```go
// IndexEmbedding inserts or updates a single embedding
func (v *VectorSearchServiceImpl) IndexEmbedding(ctx context.Context, chunkId string, embedding []float32) error {
    if !v.vssLoaded {
        return nil // Graceful degradation - skip if VSS not available
    }
    
    if len(embedding) != v.config.Dimensions {
        return fmt.Errorf("embedding dimension mismatch: expected %d, got %d", 
            v.config.Dimensions, len(embedding))
    }
    
    blob := v.embedToBlob(embedding)
    
    // Delete existing entry if any
    if err := v.db.WithContext(ctx).Exec(
        `DELETE FROM VssEmbedding WHERE chunk_id = ?`, chunkId,
    ).Error; err != nil {
        return fmt.Errorf("failed to remove existing embedding: %w", err)
    }
    
    // Insert new embedding
    if err := v.db.WithContext(ctx).Exec(
        `INSERT INTO VssEmbedding (embedding, chunk_id) VALUES (?, ?)`,
        blob, chunkId,
    ).Error; err != nil {
        return fmt.Errorf("failed to index embedding: %w", err)
    }
    
    return nil
}

// IndexBatch indexes multiple embeddings in a transaction
func (v *VectorSearchServiceImpl) IndexBatch(ctx context.Context, embeddings map[string][]float32) error {
    if !v.vssLoaded || len(embeddings) == 0 {
        return nil
    }
    
    return v.db.WithContext(ctx).Transaction(func(tx *gorm.DB) error {
        for chunkId, embedding := range embeddings {
            if len(embedding) != v.config.Dimensions {
                continue // Skip invalid dimensions
            }
            
            blob := v.embedToBlob(embedding)
            
            // Upsert pattern
            if err := tx.Exec(
                `DELETE FROM VssEmbedding WHERE chunk_id = ?`, chunkId,
            ).Error; err != nil {
                return err
            }
            
            if err := tx.Exec(
                `INSERT INTO VssEmbedding (embedding, chunk_id) VALUES (?, ?)`,
                blob, chunkId,
            ).Error; err != nil {
                return err
            }
        }
        return nil
    })
}

// RemoveEmbedding deletes an embedding by chunk ID
func (v *VectorSearchServiceImpl) RemoveEmbedding(ctx context.Context, chunkId string) error {
    if !v.vssLoaded {
        return nil
    }
    
    return v.db.WithContext(ctx).Exec(
        `DELETE FROM VssEmbedding WHERE chunk_id = ?`, chunkId,
    ).Error
}

// RemoveByArtifact removes all embeddings for an artifact
func (v *VectorSearchServiceImpl) RemoveByArtifact(ctx context.Context, artifactId string) error {
    if !v.vssLoaded {
        return nil
    }
    
    // Join with Chunk table to find all chunk IDs for this artifact
    return v.db.WithContext(ctx).Exec(`
        DELETE FROM VssEmbedding 
        WHERE chunk_id IN (
            SELECT Id FROM Chunk WHERE ArtifactId = ?
        )
    `, artifactId).Error
}

// embedToBlob converts float32 slice to little-endian byte blob
func (v *VectorSearchServiceImpl) embedToBlob(embedding []float32) []byte {
    blob := make([]byte, len(embedding)*4)
    for i, val := range embedding {
        bits := math.Float32bits(val)
        binary.LittleEndian.PutUint32(blob[i*4:], bits)
    }
    return blob
}

// blobToEmbed converts byte blob to float32 slice
func (v *VectorSearchServiceImpl) blobToEmbed(blob []byte) []float32 {
    embedding := make([]float32, len(blob)/4)
    for i := range embedding {
        bits := binary.LittleEndian.Uint32(blob[i*4:])
        embedding[i] = math.Float32frombits(bits)
    }
    return embedding
}
```

---

## 21.5 Search Operations

### Semantic Search (Vector Similarity)

```go
// SearchSemantic performs vector similarity search using sqlite-vss
func (v *VectorSearchServiceImpl) SearchSemantic(
    ctx context.Context, 
    queryEmbedding []float32, 
    limit int,
) ([]ChunkScore, error) {
    if !v.vssLoaded {
        return nil, fmt.Errorf("sqlite-vss not available")
    }
    
    if limit <= 0 {
        limit = v.config.DefaultLimit
    }
    
    blob := v.embedToBlob(queryEmbedding)
    
    var results []struct {
        ChunkId  string  `gorm:"column:chunk_id"`
        Distance float64 `gorm:"column:distance"`
    }
    
    // sqlite-vss uses distance (lower is better), convert to similarity
    err := v.db.WithContext(ctx).Raw(`
        SELECT 
            chunk_id,
            distance
        FROM VssEmbedding
        WHERE vss_search(embedding, ?)
        ORDER BY distance ASC
        LIMIT ?
    `, blob, limit).Scan(&results).Error
    
    if err != nil {
        return nil, fmt.Errorf("semantic search failed: %w", err)
    }
    
    // Convert to ChunkScore with normalized similarity
    scores := make([]ChunkScore, 0, len(results))
    for _, r := range results {
        // Convert L2 distance to cosine similarity approximation
        // similarity = 1 / (1 + distance)
        similarity := 1.0 / (1.0 + r.Distance)
        
        if similarity >= v.config.MinSimilarityScore {
            scores = append(scores, ChunkScore{
                ChunkId:   r.ChunkId,
                Score:     similarity,
                ScoreType: "semantic",
            })
        }
    }
    
    return scores, nil
}

// SearchKeyword performs FTS5 keyword search
func (v *VectorSearchServiceImpl) SearchKeyword(
    ctx context.Context,
    query string,
    limit int,
) ([]ChunkScore, error) {
    if limit <= 0 {
        limit = v.config.DefaultLimit
    }
    
    var results []struct {
        ChunkId       string  `gorm:"column:chunk_id"`
        SectionAnchor string  `gorm:"column:section_anchor"`
        Rank          float64 `gorm:"column:rank"`
    }
    
    // FTS5 bm25() returns negative scores, more negative = better match
    err := v.db.WithContext(ctx).Raw(`
        SELECT 
            chunk_id,
            section_anchor,
            bm25(ChunkFts) as rank
        FROM ChunkFts
        WHERE ChunkFts MATCH ?
        ORDER BY rank ASC
        LIMIT ?
    `, query, limit).Scan(&results).Error
    
    if err != nil {
        return nil, fmt.Errorf("keyword search failed: %w", err)
    }
    
    // Normalize BM25 scores to 0-1 range
    scores := make([]ChunkScore, 0, len(results))
    maxRank := 0.0
    if len(results) > 0 {
        maxRank = math.Abs(results[0].Rank)
    }
    
    for _, r := range results {
        // Normalize: convert negative to positive, then to 0-1 range
        normalizedScore := 0.0
        if maxRank > 0 {
            normalizedScore = math.Abs(r.Rank) / maxRank
        }
        
        scores = append(scores, ChunkScore{
            ChunkId:       r.ChunkId,
            SectionAnchor: r.SectionAnchor,
            Score:         normalizedScore,
            ScoreType:     "keyword",
        })
    }
    
    return scores, nil
}
```

---

## 21.6 Hybrid Scoring Algorithm (RRF)

### Reciprocal Rank Fusion

The hybrid search combines semantic and keyword results using the **Reciprocal Rank Fusion (RRF)** algorithm, which is robust to score distribution differences between retrieval methods.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      RECIPROCAL RANK FUSION (RRF)                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  Formula: RRF_score(d) = Σ  1 / (k + rank_i(d))                             │
│                          i                                                    │
│                                                                               │
│  Where:                                                                       │
│  - d = document (chunk)                                                       │
│  - k = constant (typically 60, reduces impact of high ranks)                 │
│  - rank_i(d) = rank of document d in result list i (1-indexed)              │
│                                                                               │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │  Example:                                                              │   │
│  │                                                                        │   │
│  │  Semantic Results: [A, B, C, D]  ranks: A=1, B=2, C=3, D=4            │   │
│  │  Keyword Results:  [C, A, E, B]  ranks: C=1, A=2, E=3, B=4            │   │
│  │                                                                        │   │
│  │  RRF(A) = 1/(60+1) + 1/(60+2) = 0.0164 + 0.0161 = 0.0325              │   │
│  │  RRF(B) = 1/(60+2) + 1/(60+4) = 0.0161 + 0.0156 = 0.0317              │   │
│  │  RRF(C) = 1/(60+3) + 1/(60+1) = 0.0159 + 0.0164 = 0.0323              │   │
│  │  RRF(D) = 1/(60+4) + 0        = 0.0156                                │   │
│  │  RRF(E) = 0        + 1/(60+3) = 0.0159                                │   │
│  │                                                                        │   │
│  │  Final Ranking: [A, C, B, E, D]                                       │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Implementation

```go
// SearchHybrid combines semantic and keyword search using RRF
func (v *VectorSearchServiceImpl) SearchHybrid(
    ctx context.Context,
    queryEmbedding []float32,
    queryText string,
    limit int,
) ([]ChunkScore, error) {
    if limit <= 0 {
        limit = v.config.DefaultLimit
    }
    
    // Fetch 2x limit from each source to ensure good fusion
    fetchLimit := limit * 2
    
    // Parallel search
    type searchResult struct {
        scores []ChunkScore
        err    error
    }
    
    semanticCh := make(chan searchResult, 1)
    keywordCh := make(chan searchResult, 1)
    
    // Semantic search (if VSS available)
    go func() {
        if v.vssLoaded && len(queryEmbedding) > 0 {
            scores, err := v.SearchSemantic(ctx, queryEmbedding, fetchLimit)
            semanticCh <- searchResult{scores: scores, err: err}
        } else {
            semanticCh <- searchResult{scores: nil, err: nil}
        }
    }()
    
    // Keyword search
    go func() {
        if queryText != "" {
            scores, err := v.SearchKeyword(ctx, queryText, fetchLimit)
            keywordCh <- searchResult{scores: scores, err: err}
        } else {
            keywordCh <- searchResult{scores: nil, err: nil}
        }
    }()
    
    // Collect results
    semanticResult := <-semanticCh
    keywordResult := <-keywordCh
    
    // Log errors but continue with available results
    if semanticResult.err != nil {
        // Log warning: semantic search failed
    }
    if keywordResult.err != nil {
        // Log warning: keyword search failed
    }
    
    // Apply RRF fusion
    fused := v.fuseWithRRF(
        semanticResult.scores, 
        keywordResult.scores,
        v.config.RRFConstant,
    )
    
    // Trim to requested limit
    if len(fused) > limit {
        fused = fused[:limit]
    }
    
    return fused, nil
}

// fuseWithRRF applies Reciprocal Rank Fusion to combine result lists
func (v *VectorSearchServiceImpl) fuseWithRRF(
    semanticScores []ChunkScore,
    keywordScores []ChunkScore,
    k int,
) []ChunkScore {
    // Map to track RRF scores by chunk ID
    rrfScores := make(map[string]float64)
    chunkData := make(map[string]ChunkScore)
    
    // Add semantic scores with RRF
    for rank, score := range semanticScores {
        rrfScore := 1.0 / float64(k+rank+1) // rank is 0-indexed, add 1 for 1-indexed
        rrfScores[score.ChunkId] += rrfScore
        if _, exists := chunkData[score.ChunkId]; !exists {
            chunkData[score.ChunkId] = score
        }
    }
    
    // Add keyword scores with RRF
    for rank, score := range keywordScores {
        rrfScore := 1.0 / float64(k+rank+1)
        rrfScores[score.ChunkId] += rrfScore
        if _, exists := chunkData[score.ChunkId]; !exists {
            chunkData[score.ChunkId] = score
        }
    }
    
    // Convert to sorted slice
    type rrfEntry struct {
        chunkId string
        score   float64
    }
    
    entries := make([]rrfEntry, 0, len(rrfScores))
    for chunkId, score := range rrfScores {
        entries = append(entries, rrfEntry{chunkId: chunkId, score: score})
    }
    
    // Sort by RRF score descending
    sort.Slice(entries, func(i, j int) bool {
        return entries[i].score > entries[j].score
    })
    
    // Build result with hybrid scores
    results := make([]ChunkScore, 0, len(entries))
    for _, entry := range entries {
        data := chunkData[entry.chunkId]
        results = append(results, ChunkScore{
            ChunkId:       entry.chunkId,
            ArtifactId:    data.ArtifactId,
            Score:         entry.score,
            ScoreType:     "hybrid",
            Content:       data.Content,
            SectionAnchor: data.SectionAnchor,
        })
    }
    
    return results
}
```

---

## 21.7 Weighted Hybrid Alternative

For cases where raw score fusion is preferred over rank fusion:

```go
// SearchHybridWeighted uses weighted score combination instead of RRF
func (v *VectorSearchServiceImpl) SearchHybridWeighted(
    ctx context.Context,
    queryEmbedding []float32,
    queryText string,
    limit int,
) ([]ChunkScore, error) {
    fetchLimit := limit * 2
    
    semanticScores, _ := v.SearchSemantic(ctx, queryEmbedding, fetchLimit)
    keywordScores, _ := v.SearchKeyword(ctx, queryText, fetchLimit)
    
    // Collect all unique chunks with weighted scores
    combinedScores := make(map[string]struct {
        semantic float64
        keyword  float64
        data     ChunkScore
    })
    
    for _, s := range semanticScores {
        entry := combinedScores[s.ChunkId]
        entry.semantic = s.Score
        entry.data = s
        combinedScores[s.ChunkId] = entry
    }
    
    for _, s := range keywordScores {
        entry := combinedScores[s.ChunkId]
        entry.keyword = s.Score
        if entry.data.ChunkId == "" {
            entry.data = s
        }
        combinedScores[s.ChunkId] = entry
    }
    
    // Calculate weighted scores
    results := make([]ChunkScore, 0, len(combinedScores))
    for chunkId, entry := range combinedScores {
        weightedScore := entry.semantic*v.config.HybridSemanticWeight + 
                         entry.keyword*v.config.HybridKeywordWeight
        
        results = append(results, ChunkScore{
            ChunkId:       chunkId,
            ArtifactId:    entry.data.ArtifactId,
            Score:         weightedScore,
            ScoreType:     "hybrid_weighted",
            Content:       entry.data.Content,
            SectionAnchor: entry.data.SectionAnchor,
        })
    }
    
    // Sort by weighted score
    sort.Slice(results, func(i, j int) bool {
        return results[i].Score > results[j].Score
    })
    
    if len(results) > limit {
        results = results[:limit]
    }
    
    return results, nil
}
```

---

## 21.8 Maintenance Operations

### Reindexing

```go
// ReindexProject rebuilds the vector index for a project
func (v *VectorSearchServiceImpl) ReindexProject(ctx context.Context, projectId string) error {
    if !v.vssLoaded {
        return fmt.Errorf("sqlite-vss not available for reindexing")
    }
    
    // 1. Get all chunks for this project
    var chunks []struct {
        Id         string
        ArtifactId string
    }
    
    err := v.db.WithContext(ctx).Raw(`
        SELECT c.Id, c.ArtifactId
        FROM Chunk c
        JOIN Artifact a ON c.ArtifactId = a.Id
        WHERE a.ProjectId = ?
    `, projectId).Scan(&chunks).Error
    
    if err != nil {
        return fmt.Errorf("failed to fetch chunks: %w", err)
    }
    
    // 2. Get embeddings and reindex
    for _, chunk := range chunks {
        var embedding struct {
            Vector []byte `gorm:"column:Vector"`
        }
        
        err := v.db.WithContext(ctx).Table("Embedding").
            Where("ChunkId = ?", chunk.Id).
            First(&embedding).Error
        
        if err != nil {
            continue // Skip chunks without embeddings
        }
        
        embeddingVec := v.blobToEmbed(embedding.Vector)
        if err := v.IndexEmbedding(ctx, chunk.Id, embeddingVec); err != nil {
            // Log warning but continue
        }
    }
    
    // 3. Update metadata
    now := time.Now()
    return v.db.WithContext(ctx).Exec(`
        INSERT INTO VectorIndexMetadata (Id, ProjectId, TotalVectors, Dimensions, IndexType, LastReindexAt)
        VALUES (?, ?, ?, ?, 'vss', ?)
        ON CONFLICT(ProjectId) DO UPDATE SET
            TotalVectors = excluded.TotalVectors,
            LastReindexAt = excluded.LastReindexAt
    `, uuid.New().String(), projectId, len(chunks), v.config.Dimensions, now).Error
}

// GetIndexStats returns statistics about the vector index
func (v *VectorSearchServiceImpl) GetIndexStats(ctx context.Context, projectId string) (*VectorIndexStats, error) {
    var stats VectorIndexStats
    stats.ProjectId = projectId
    stats.Dimensions = v.config.Dimensions
    stats.VssEnabled = v.vssLoaded
    stats.Fts5Enabled = true // FTS5 always available
    
    if v.vssLoaded {
        var count int64
        if err := v.db.WithContext(ctx).Raw(`
            SELECT COUNT(*) FROM VssEmbedding
        `).Scan(&count).Error; err == nil {
            stats.TotalVectors = int(count)
        }
        stats.IndexType = "vss"
    } else {
        stats.IndexType = "fts5_only"
    }
    
    // Get last reindex time from metadata
    var metadata struct {
        LastReindexAt time.Time
    }
    v.db.WithContext(ctx).Table("VectorIndexMetadata").
        Where("ProjectId = ?", projectId).
        First(&metadata)
    stats.LastReindexAt = metadata.LastReindexAt
    
    return &stats, nil
}

// HealthCheck verifies the vector search service is operational
func (v *VectorSearchServiceImpl) HealthCheck(ctx context.Context) error {
    // Test basic query
    if v.vssLoaded {
        return v.db.WithContext(ctx).Exec(`SELECT 1 FROM VssEmbedding LIMIT 1`).Error
    }
    return v.db.WithContext(ctx).Exec(`SELECT 1 FROM ChunkFts LIMIT 1`).Error
}
```

---

## 21.9 Integration with RAG Pipeline

### Updated RAG Retrieval

```go
// RAGService integrates VectorSearchService
type RAGService struct {
    vectorSearch VectorSearchService
    embeddingGen EmbeddingGenerator
    db           *gorm.DB
}

// RetrieveContext fetches relevant context for a query
func (r *RAGService) RetrieveContext(
    ctx context.Context,
    projectId string,
    query string,
    limit int,
) ([]RetrievedChunk, error) {
    // 1. Generate query embedding
    queryEmbedding, err := r.embeddingGen.Generate(ctx, query)
    if err != nil {
        // Fallback to keyword-only search
        return r.fallbackKeywordSearch(ctx, query, limit)
    }
    
    // 2. Perform hybrid search
    scores, err := r.vectorSearch.SearchHybrid(ctx, queryEmbedding, query, limit)
    if err != nil {
        return nil, fmt.Errorf("hybrid search failed: %w", err)
    }
    
    // 3. Hydrate chunk data
    chunks := make([]RetrievedChunk, 0, len(scores))
    for _, score := range scores {
        var chunk Chunk
        if err := r.db.WithContext(ctx).
            Preload("Artifact").
            First(&chunk, "Id = ?", score.ChunkId).Error; err != nil {
            continue
        }
        
        chunks = append(chunks, RetrievedChunk{
            ChunkId:       chunk.Id,
            ArtifactId:    chunk.ArtifactId,
            Content:       chunk.Content,
            SectionAnchor: chunk.SectionAnchor,
            Score:         score.Score,
            ScoreType:     score.ScoreType,
            FilePath:      chunk.Artifact.FilePath,
        })
    }
    
    return chunks, nil
}
```

---

## 21.10 Configuration

Add to seeding configuration:

```json
{
    "Key": "vector.engine",
    "Value": "sqlite-vss",
    "Description": "Vector search engine: sqlite-vss | fts5_only"
},
{
    "Key": "vector.dimensions",
    "Value": "768",
    "Description": "Embedding vector dimensions"
},
{
    "Key": "vector.minSimilarity",
    "Value": "0.5",
    "Description": "Minimum similarity threshold (0-1)"
},
{
    "Key": "vector.hybridSemanticWeight",
    "Value": "0.6",
    "Description": "Weight for semantic scores in hybrid search"
},
{
    "Key": "vector.hybridKeywordWeight",
    "Value": "0.4",
    "Description": "Weight for keyword scores in hybrid search"
},
{
    "Key": "vector.rrfConstant",
    "Value": "60",
    "Description": "RRF algorithm constant (k)"
},
{
    "Key": "vector.defaultLimit",
    "Value": "10",
    "Description": "Default top-K results"
}
```

---

## 21.11 Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 3050 | ERR_VSS_INIT_FAILED | sqlite-vss extension load failed |
| 3051 | ERR_VSS_INDEX_FAILED | Vector indexing operation failed |
| 3052 | ERR_VSS_SEARCH_FAILED | Vector search query failed |
| 3053 | ERR_VSS_DIMENSION_MISMATCH | Embedding dimension mismatch |
| 3054 | ERR_VSS_NOT_AVAILABLE | sqlite-vss not loaded |
| 3055 | ERR_HYBRID_SEARCH_FAILED | Hybrid search fusion failed |

---

## 21.12 Unit Test Requirements

### Test Coverage

| Test Case | Priority |
|-----------|----------|
| Initialize creates virtual tables | HIGH |
| IndexEmbedding stores correctly | HIGH |
| IndexBatch handles transactions | HIGH |
| SearchSemantic returns ranked results | HIGH |
| SearchKeyword uses FTS5 correctly | HIGH |
| SearchHybrid fuses results with RRF | HIGH |
| RRF fusion handles disjoint sets | MEDIUM |
| Graceful degradation without VSS | MEDIUM |
| ReindexProject updates all chunks | MEDIUM |
| HealthCheck validates connection | LOW |

### Example Test

```go
func TestSearchHybrid_RRFFusion(t *testing.T) {
    db := setupTestDB(t)
    svc := NewVectorSearchService(db, VectorSearchConfig{
        Dimensions:  768,
        RRFConstant: 60,
    })
    
    // Setup: Index test embeddings
    setupTestEmbeddings(t, db)
    
    // Generate test query
    queryEmbed := generateTestEmbedding(768)
    
    // Execute hybrid search
    results, err := svc.SearchHybrid(context.Background(), queryEmbed, "test query", 5)
    
    require.NoError(t, err)
    require.LessOrEqual(t, len(results), 5)
    
    // Verify RRF scoring
    for i := 1; i < len(results); i++ {
        assert.GreaterOrEqual(t, results[i-1].Score, results[i].Score,
            "Results should be sorted by RRF score descending")
    }
    
    // Verify score type
    for _, r := range results {
        assert.Equal(t, "hybrid", r.ScoreType)
    }
}
```

---

## 21.13 Acceptance Criteria

### Initialization (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| IN-001 | sqlite-vss extension loads on startup | Critical | Extension load test |
| IN-002 | VssEmbedding virtual table created | Critical | Table existence test |
| IN-003 | ChunkFts FTS5 table created | Critical | FTS5 table test |
| IN-004 | Graceful fallback to FTS5-only when VSS unavailable | Critical | Fallback test |
| IN-005 | IsVssAvailable() returns correct status | High | Status check test |

### Embedding Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EO-001 | IndexEmbedding stores embedding with correct dimensions | Critical | Dimension test |
| EO-002 | IndexEmbedding replaces existing entry on update | Critical | Upsert test |
| EO-003 | IndexBatch processes multiple embeddings atomically | Critical | Batch test |
| EO-004 | RemoveEmbedding deletes by chunk ID | Critical | Delete test |
| EO-005 | RemoveByArtifact deletes all chunks for artifact | High | Cascade delete test |
| EO-006 | Embedding blob format: little-endian float32 | High | Format test |

### Semantic Search (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SS-001 | SearchSemantic returns ranked results by similarity | Critical | Ranking test |
| SS-002 | Similarity scores normalized to 0-1 range | Critical | Score normalization test |
| SS-003 | MinSimilarityScore filters low-quality results | High | Threshold test |
| SS-004 | Results limited to requested count | High | Limit test |
| SS-005 | Search latency <100ms for 10K vectors | Medium | Performance test |

### Keyword Search (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| KS-001 | SearchKeyword uses FTS5 MATCH | Critical | FTS5 query test |
| KS-002 | BM25 scores normalized to 0-1 range | High | BM25 normalization test |
| KS-003 | Porter stemming applied via tokenizer | Medium | Tokenizer test |
| KS-004 | Section anchors included in results | Medium | Anchor test |

### Hybrid Search - RRF (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| HS-001 | SearchHybrid combines semantic and keyword results | Critical | Hybrid test |
| HS-002 | RRF formula: 1/(k + rank) applied correctly | Critical | RRF calculation test |
| HS-003 | Results deduplicated by chunk ID | High | Dedup test |
| HS-004 | Chunks in only one source still included | High | Single-source test |
| HS-005 | RRF constant k configurable (default 60) | Medium | Config test |
| HS-006 | ScoreType set to "hybrid" for combined results | Medium | Score type test |

### Maintenance Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MO-001 | ReindexProject rebuilds all embeddings | Critical | Reindex test |
| MO-002 | GetIndexStats returns accurate counts | High | Stats test |
| MO-003 | ClearCache invalidates retrieval cache | Medium | Cache clear test |
| MO-004 | HealthCheck validates VSS and FTS5 status | High | Health check test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | Dimension mismatch returns clear error | Critical | Dimension error test |
| EH-002 | VSS unavailable does not crash service | Critical | Graceful degradation test |
| EH-003 | Failed search returns error with context | High | Error context test |

---

## Related Specifications

- [Vector Database Plan](./04-vector-database-plan.md)
- [RAG System](./01-rag-system.md)
- [Database Schema](../../07-database-design/01-schema.md)
