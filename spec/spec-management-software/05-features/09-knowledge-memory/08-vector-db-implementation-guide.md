# Vector Database Implementation Guide

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This guide provides a step-by-step implementation path for the Vector Database Enhancement suite. Follow this order to ensure each component builds on stable foundations.

**Cross-References:**
- [Vector Database Plan](./04-vector-database-plan.md) - Overall strategy
- [Vector Search Service](./05-vector-search-service.md) - Phase 1 specification
- [Context Window Manager](./06-context-window-manager.md) - Phase 2 specification
- [Instruction Segmentation](../06-ai-integration/05-instruction-segmentation.md) - Phase 3 specification
- [Memory Compression](./07-memory-compression.md) - Phase 4 specification
- [Integration Tests](./tests/) - Test specifications

---

## Implementation Order

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      RECOMMENDED BUILD ORDER                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  Week 1          Week 2          Week 3          Week 4          Week 5      │
│  ──────          ──────          ──────          ──────          ──────      │
│                                                                               │
│  ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐   │
│  │ Phase 1 │───▶│ Phase 2 │───▶│ Phase 3 │───▶│ Phase 4 │───▶│  Tests  │   │
│  │ Vector  │    │ Context │    │ Segment │    │ Memory  │    │  E2E    │   │
│  │ Search  │    │ Window  │    │ Parser  │    │ Compress│    │ Pipeline│   │
│  └─────────┘    └─────────┘    └─────────┘    └─────────┘    └─────────┘   │
│       │              │              │              │              │          │
│       ▼              ▼              ▼              ▼              ▼          │
│  ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐   │
│  │ Models  │    │ Token   │    │ Depend  │    │ Prompt  │    │ Perf    │   │
│  │ + VSS   │    │ Counter │    │ Resolver│    │ Manager │    │ Bench   │   │
│  └─────────┘    └─────────┘    └─────────┘    └─────────┘    └─────────┘   │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Vector Search Service (Week 1)

### Step 1.1: Database Models

Create the GORM models first. All other components depend on these.

```go
// internal/models/vector_models.go
package models

import (
    "time"
    "gorm.io/gorm"
)

// VectorIndexMetadata tracks vector index state per project
type VectorIndexMetadata struct {
    Id            string    `gorm:"primaryKey;type:TEXT"`
    ProjectId     string    `gorm:"type:TEXT;not null;index"`
    TotalVectors  int       `gorm:"default:0"`
    Dimensions    int       `gorm:"default:768"`
    LastReindexAt time.Time
    IndexSizeBytes int64    `gorm:"default:0"`
    VssEnabled    bool      `gorm:"default:false"`
    Fts5Enabled   bool      `gorm:"default:true"`
    CreatedAt     time.Time
    UpdatedAt     time.Time
}

// Chunk represents a text chunk for RAG retrieval
type Chunk struct {
    Id            string `gorm:"primaryKey;type:TEXT"`
    ArtifactId    string `gorm:"type:TEXT;not null;index"`
    Content       string `gorm:"type:TEXT;not null"`
    ChunkIndex    int    `gorm:"not null"`
    StartOffset   int
    EndOffset     int
    SectionAnchor string `gorm:"type:TEXT"`
    TokenCount    int
    CreatedAt     time.Time

    // Relationships
    Artifact Artifact `gorm:"foreignKey:ArtifactId"`
}

// InstructionSegment represents a parsed segment for multi-turn execution
type InstructionSegment struct {
    Id              string    `gorm:"primaryKey;type:TEXT"`
    InstructionId   string    `gorm:"type:TEXT;not null;index"`
    SegmentIndex    int       `gorm:"not null"`
    Title           string    `gorm:"type:TEXT"`
    Content         string    `gorm:"type:TEXT;not null"`
    TokenCount      int
    Status          string    `gorm:"type:TEXT;default:'pending'"` // pending|queued|executing|completed|failed|skipped|summarized
    DependsOn       string    `gorm:"type:TEXT"` // JSON array of segment IDs
    ExecutionOutput string    `gorm:"type:TEXT"`
    RetryCount      int       `gorm:"default:0"`
    StartedAt       *time.Time
    CompletedAt     *time.Time
    CreatedAt       time.Time
}

// MemoryEntry stores compressed execution summaries
type MemoryEntry struct {
    Id               string    `gorm:"primaryKey;type:TEXT"`
    ProjectId        string    `gorm:"type:TEXT;not null;index"`
    SessionId        string    `gorm:"type:TEXT;index"`
    SegmentId        string    `gorm:"type:TEXT;index"`
    SummaryType      string    `gorm:"type:TEXT;not null"` // execution|conversation|artifact|incremental
    Summary          string    `gorm:"type:TEXT;not null"`
    OriginalTokens   int
    CompressedTokens int
    KeyDecisions     string    `gorm:"type:TEXT"` // JSON array
    ArtifactsCreated string    `gorm:"type:TEXT"` // JSON array
    OpenQuestions    string    `gorm:"type:TEXT"` // JSON array
    CreatedAt        time.Time
    ExpiresAt        *time.Time
}
```

### Step 1.2: Run Migration

```go
// internal/repository/migrations.go
func RunMigrations(db *gorm.DB) error {
    return db.AutoMigrate(
        // Existing models...
        &models.Project{},
        &models.Artifact{},

        // New vector models
        &models.VectorIndexMetadata{},
        &models.Chunk{},
        &models.InstructionSegment{},
        &models.MemoryEntry{},
    )
}
```

### Step 1.3: Vector Search Service

```go
// internal/services/vector_search.go
package services

import (
    "context"
    "encoding/binary"
    "fmt"
    "math"
    "sort"

    "gorm.io/gorm"
)

// VectorSearchConfig holds configuration
type VectorSearchConfig struct {
    Dimensions           int
    DefaultLimit         int
    MinSimilarityScore   float64
    HybridSemanticWeight float64
    HybridKeywordWeight  float64
    RRFConstant          int
}

// DefaultVectorSearchConfig returns production defaults
func DefaultVectorSearchConfig() VectorSearchConfig {
    return VectorSearchConfig{
        Dimensions:           768,
        DefaultLimit:         10,
        MinSimilarityScore:   0.5,
        HybridSemanticWeight: 0.6,
        HybridKeywordWeight:  0.4,
        RRFConstant:          60,
    }
}

// ChunkScore represents a scored search result
type ChunkScore struct {
    ChunkId       string
    ArtifactId    string
    Score         float64
    ScoreType     string // semantic | keyword | hybrid
    Content       string
    SectionAnchor string
}

// VectorSearchService implements vector search
type VectorSearchService struct {
    db        *gorm.DB
    config    VectorSearchConfig
    vssLoaded bool
}

// NewVectorSearchService creates the service
func NewVectorSearchService(db *gorm.DB, config VectorSearchConfig) *VectorSearchService {
    return &VectorSearchService{db: db, config: config}
}

// Initialize loads sqlite-vss and creates virtual tables
func (v *VectorSearchService) Initialize(ctx context.Context) error {
    // Try to load VSS extension
    if err := v.loadVssExtension(ctx); err != nil {
        // Graceful degradation - continue without VSS
        v.vssLoaded = false
    } else {
        v.vssLoaded = true

        // Create VSS virtual table
        createVss := fmt.Sprintf(`
            CREATE VIRTUAL TABLE IF NOT EXISTS VssEmbedding USING vss0(
                embedding(%d),
                chunk_id TEXT
            );
        `, v.config.Dimensions)

        if err := v.db.WithContext(ctx).Exec(createVss).Error; err != nil {
            return fmt.Errorf("failed to create VssEmbedding: %w", err)
        }
    }

    // Create FTS5 table (always available)
    createFts := `
        CREATE VIRTUAL TABLE IF NOT EXISTS ChunkFts USING fts5(
            chunk_id UNINDEXED,
            content,
            section_anchor UNINDEXED,
            tokenize='porter unicode61'
        );
    `
    return v.db.WithContext(ctx).Exec(createFts).Error
}

func (v *VectorSearchService) loadVssExtension(ctx context.Context) error {
    return v.db.WithContext(ctx).Exec(`SELECT load_extension('vss0')`).Error
}

// IndexEmbedding adds a single embedding
func (v *VectorSearchService) IndexEmbedding(ctx context.Context, chunkId string, embedding []float32) error {
    if !v.vssLoaded {
        return nil // Skip if VSS not available
    }

    if len(embedding) != v.config.Dimensions {
        return fmt.Errorf("dimension mismatch: expected %d, got %d", v.config.Dimensions, len(embedding))
    }

    blob := v.embedToBlob(embedding)

    // Upsert pattern
    v.db.WithContext(ctx).Exec(`DELETE FROM VssEmbedding WHERE chunk_id = ?`, chunkId)
    return v.db.WithContext(ctx).Exec(
        `INSERT INTO VssEmbedding (embedding, chunk_id) VALUES (?, ?)`,
        blob, chunkId,
    ).Error
}

// SearchHybrid combines semantic and keyword search using RRF
func (v *VectorSearchService) SearchHybrid(
    ctx context.Context,
    queryEmbedding []float32,
    queryText string,
    limit int,
) ([]ChunkScore, error) {
    if limit <= 0 {
        limit = v.config.DefaultLimit
    }

    // Get semantic results
    semanticResults, _ := v.SearchSemantic(ctx, queryEmbedding, limit*2)

    // Get keyword results
    keywordResults, _ := v.SearchKeyword(ctx, queryText, limit*2)

    // Apply RRF fusion
    return v.applyRRF(semanticResults, keywordResults, limit), nil
}

// SearchSemantic performs vector similarity search
func (v *VectorSearchService) SearchSemantic(ctx context.Context, queryEmbed []float32, limit int) ([]ChunkScore, error) {
    if !v.vssLoaded {
        return nil, fmt.Errorf("VSS not available")
    }

    blob := v.embedToBlob(queryEmbed)

    var results []struct {
        ChunkId  string  `gorm:"column:chunk_id"`
        Distance float64 `gorm:"column:distance"`
    }

    err := v.db.WithContext(ctx).Raw(`
        SELECT chunk_id, distance
        FROM VssEmbedding
        WHERE vss_search(embedding, ?)
        ORDER BY distance ASC
        LIMIT ?
    `, blob, limit).Scan(&results).Error

    if err != nil {
        return nil, err
    }

    scores := make([]ChunkScore, 0, len(results))
    for _, r := range results {
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

// SearchKeyword performs FTS5 search
func (v *VectorSearchService) SearchKeyword(ctx context.Context, query string, limit int) ([]ChunkScore, error) {
    var results []struct {
        ChunkId       string  `gorm:"column:chunk_id"`
        SectionAnchor string  `gorm:"column:section_anchor"`
        Rank          float64 `gorm:"column:rank"`
    }

    err := v.db.WithContext(ctx).Raw(`
        SELECT chunk_id, section_anchor, bm25(ChunkFts) as rank
        FROM ChunkFts
        WHERE ChunkFts MATCH ?
        ORDER BY rank ASC
        LIMIT ?
    `, query, limit).Scan(&results).Error

    if err != nil {
        return nil, err
    }

    scores := make([]ChunkScore, 0, len(results))
    maxRank := 0.0
    if len(results) > 0 {
        maxRank = math.Abs(results[0].Rank)
    }

    for _, r := range results {
        normalized := 0.0
        if maxRank > 0 {
            normalized = math.Abs(r.Rank) / maxRank
        }
        scores = append(scores, ChunkScore{
            ChunkId:       r.ChunkId,
            SectionAnchor: r.SectionAnchor,
            Score:         normalized,
            ScoreType:     "keyword",
        })
    }
    return scores, nil
}

// applyRRF implements Reciprocal Rank Fusion
func (v *VectorSearchService) applyRRF(semantic, keyword []ChunkScore, limit int) []ChunkScore {
    k := float64(v.config.RRFConstant)
    scores := make(map[string]float64)
    chunks := make(map[string]ChunkScore)

    // Score semantic results
    for rank, chunk := range semantic {
        rrfScore := v.config.HybridSemanticWeight / (k + float64(rank+1))
        scores[chunk.ChunkId] += rrfScore
        chunks[chunk.ChunkId] = chunk
    }

    // Score keyword results
    for rank, chunk := range keyword {
        rrfScore := v.config.HybridKeywordWeight / (k + float64(rank+1))
        scores[chunk.ChunkId] += rrfScore
        if _, exists := chunks[chunk.ChunkId]; !exists {
            chunks[chunk.ChunkId] = chunk
        }
    }

    // Sort by combined score
    type scored struct {
        id    string
        score float64
    }
    ranked := make([]scored, 0, len(scores))
    for id, score := range scores {
        ranked = append(ranked, scored{id, score})
    }
    sort.Slice(ranked, func(i, j int) bool {
        return ranked[i].score > ranked[j].score
    })

    // Build result
    results := make([]ChunkScore, 0, limit)
    for i := 0; i < len(ranked) && i < limit; i++ {
        chunk := chunks[ranked[i].id]
        chunk.Score = ranked[i].score
        chunk.ScoreType = "hybrid"
        results = append(results, chunk)
    }

    return results
}

// Helper: convert float32 slice to blob
func (v *VectorSearchService) embedToBlob(embedding []float32) []byte {
    blob := make([]byte, len(embedding)*4)
    for i, val := range embedding {
        bits := math.Float32bits(val)
        binary.LittleEndian.PutUint32(blob[i*4:], bits)
    }
    return blob
}

// IsVssAvailable returns VSS status
func (v *VectorSearchService) IsVssAvailable() bool {
    return v.vssLoaded
}
```

### Step 1.4: Unit Tests

```go
// internal/services/vector_search_test.go
func TestVectorSearch_HybridSearch_CombinesResults(t *testing.T) {
    db := setupTestDB(t)
    service := NewVectorSearchService(db, DefaultVectorSearchConfig())
    require.NoError(t, service.Initialize(context.Background()))

    // Index test data
    chunks := createTestChunks(t, db, 20)
    for _, c := range chunks {
        service.IndexEmbedding(context.Background(), c.ID, c.Embedding)
    }

    // Search
    results, err := service.SearchHybrid(
        context.Background(),
        chunks[0].Embedding,
        "authentication",
        5,
    )

    require.NoError(t, err)
    assert.NotEmpty(t, results)
    assert.Equal(t, "hybrid", results[0].ScoreType)
}
```

---

## Phase 2: Context Window Manager (Week 2)

### Step 2.1: Token Counter

```go
// internal/services/token_counter.go
package services

import (
    "strings"
    "sync"
    "unicode/utf8"
)

// TokenCounter provides token estimation
type TokenCounter struct {
    tokenizer        string
    avgCharsPerToken float64
    cache            sync.Map
}

// NewTokenCounter creates a counter for the specified model
func NewTokenCounter(tokenizer string) *TokenCounter {
    avgChars := 4.0
    switch tokenizer {
    case "llama":
        avgChars = 3.8
    case "gemini":
        avgChars = 3.5
    }
    return &TokenCounter{tokenizer: tokenizer, avgCharsPerToken: avgChars}
}

// Count returns token count using heuristics
func (t *TokenCounter) Count(text string) (int, error) {
    if text == "" {
        return 0, nil
    }

    // Check cache
    if cached, ok := t.cache.Load(text); ok {
        return cached.(int), nil
    }

    // Heuristic counting
    charCount := utf8.RuneCountInString(text)
    base := float64(charCount) / t.avgCharsPerToken

    // Adjustments for special patterns
    adjustments := 0.0
    adjustments += float64(strings.Count(text, "```")) * 2.0  // Code blocks
    adjustments += float64(strings.Count(text, "http")) * 5.0 // URLs
    adjustments += float64(strings.Count(text, "\n#")) * 1.0  // Headers

    tokens := int(base + adjustments)
    t.cache.Store(text, tokens)

    return tokens, nil
}

// EstimateFromChars provides fast approximation
func (t *TokenCounter) EstimateFromChars(charCount int) int {
    return int(float64(charCount) / t.avgCharsPerToken)
}
```

### Step 2.2: Context Assembler

```go
// internal/services/context_assembler.go
package services

import (
    "context"
    "fmt"
    "sort"
    "strings"
)

// ContextLayer priority levels
type ContextLayer int

const (
    LayerSystemPrompt   ContextLayer = 1
    LayerCritical       ContextLayer = 2
    LayerUserContent    ContextLayer = 3
    LayerRetrieved      ContextLayer = 4
    LayerResponseBuffer ContextLayer = 5
)

// ContextWindowConfig defines budget allocation
type ContextWindowConfig struct {
    ModelContextSize     int
    ModelName            string
    SystemPromptReserve  int
    CriticalReserve      int
    ResponseReserve      int
    SafetyMargin         int
    AllowTruncation      bool
    TruncationStrategy   string // tail | head | middle
}

// DefaultContextWindowConfig for LLaMA 3 8B
func DefaultContextWindowConfig() ContextWindowConfig {
    return ContextWindowConfig{
        ModelContextSize:    8192,
        ModelName:           "llama-3-8b",
        SystemPromptReserve: 500,
        CriticalReserve:     1000,
        ResponseReserve:     1500,
        SafetyMargin:        200,
        AllowTruncation:     true,
        TruncationStrategy:  "tail",
    }
}

// ContextBlock represents content for a layer
type ContextBlock struct {
    Layer       ContextLayer
    Type        string
    Content     string
    TokenCount  int
    Priority    float64
    CanTruncate bool
}

// AssembledContext is the final result
type AssembledContext struct {
    Messages       []ChatMessage
    TotalTokens    int
    LayerBreakdown map[ContextLayer]int
    Truncated      bool
    TruncationLog  []string
    SourceChunks   []string
}

// ChatMessage for LLM input
type ChatMessage struct {
    Role    string `json:"role"`
    Content string `json:"content"`
}

// ContextAssembler builds context within limits
type ContextAssembler struct {
    config       ContextWindowConfig
    tokenCounter *TokenCounter
}

// NewContextAssembler creates the assembler
func NewContextAssembler(config ContextWindowConfig, counter *TokenCounter) *ContextAssembler {
    return &ContextAssembler{config: config, tokenCounter: counter}
}

// Assemble builds context from blocks respecting token limits
func (a *ContextAssembler) Assemble(ctx context.Context, blocks []ContextBlock) (*AssembledContext, error) {
    result := &AssembledContext{
        LayerBreakdown: make(map[ContextLayer]int),
        SourceChunks:   make([]string, 0),
    }

    // Calculate available budget
    available := a.config.ModelContextSize - a.config.ResponseReserve - a.config.SafetyMargin

    // Sort blocks by layer priority
    sort.Slice(blocks, func(i, j int) bool {
        if blocks[i].Layer != blocks[j].Layer {
            return blocks[i].Layer < blocks[j].Layer
        }
        return blocks[i].Priority > blocks[j].Priority
    })

    // Assemble in priority order
    remaining := available
    var content strings.Builder

    for _, block := range blocks {
        if block.TokenCount <= remaining {
            // Fits completely
            content.WriteString(block.Content)
            content.WriteString("\n\n")
            remaining -= block.TokenCount
            result.LayerBreakdown[block.Layer] += block.TokenCount
        } else if block.CanTruncate && a.config.AllowTruncation && remaining > 100 {
            // Truncate to fit
            truncated := a.truncate(block.Content, remaining)
            content.WriteString(truncated)
            content.WriteString("\n\n")
            result.Truncated = true
            result.TruncationLog = append(result.TruncationLog,
                fmt.Sprintf("Truncated %s from %d to %d tokens", block.Type, block.TokenCount, remaining))
            remaining = 0
        } else if !a.config.AllowTruncation {
            return nil, fmt.Errorf("content exceeds model limit and truncation disabled")
        }
    }

    result.Messages = []ChatMessage{{Role: "user", Content: content.String()}}
    result.TotalTokens = available - remaining

    return result, nil
}

func (a *ContextAssembler) truncate(content string, maxTokens int) string {
    targetChars := int(float64(maxTokens) * a.tokenCounter.avgCharsPerToken)

    switch a.config.TruncationStrategy {
    case "head":
        if len(content) > targetChars {
            return "..." + content[len(content)-targetChars:]
        }
    case "middle":
        if len(content) > targetChars {
            half := targetChars / 2
            return content[:half] + "\n...[truncated]...\n" + content[len(content)-half:]
        }
    default: // tail
        if len(content) > targetChars {
            return content[:targetChars] + "..."
        }
    }
    return content
}
```

---

## Phase 3: Instruction Segmentation (Week 3)

### Step 3.1: Segmentation Parser

```go
// internal/services/segmentation_parser.go
package services

import (
    "context"
    "regexp"
    "strings"
)

// ParsedSection represents a detected section
type ParsedSection struct {
    Title      string
    Content    string
    StartLine  int
    EndLine    int
    TokenCount int
    Keywords   []string
}

// SegmentationConfig holds parser settings
type SegmentationConfig struct {
    MaxTokensPerSegment int
    MinTokensPerSegment int
    MergeSmallSections  bool
    ExtractKeywords     bool
}

// DefaultSegmentationConfig returns production defaults
func DefaultSegmentationConfig() SegmentationConfig {
    return SegmentationConfig{
        MaxTokensPerSegment: 4000,
        MinTokensPerSegment: 500,
        MergeSmallSections:  true,
        ExtractKeywords:     true,
    }
}

// Section detection patterns
var sectionPatterns = []*regexp.Regexp{
    regexp.MustCompile(`(?m)^#\s+(.+)$`),                           // # H1
    regexp.MustCompile(`(?m)^##\s+(.+)$`),                          // ## H2
    regexp.MustCompile(`(?mi)^(?:phase|step|stage)\s+\d+[:\s]+(.+)$`), // Phase N:
    regexp.MustCompile(`(?m)^\d+\.\s+(.+)$`),                       // 1. Numbered
    regexp.MustCompile(`(?m)^---+$`),                               // Horizontal rule
}

// Keyword extraction patterns
var keywordPatterns = []*regexp.Regexp{
    regexp.MustCompile(`(?i)\b(api|service|model|controller|repository|handler)\b`),
    regexp.MustCompile(`(?i)\b(authentication|authorization|jwt|session|rbac)\b`),
    regexp.MustCompile(`(?i)\b(database|table|schema|migration|query)\b`),
    regexp.MustCompile(`(?i)\b(create|update|delete|read|list)\b`),
}

// SegmentationParser parses instructions into segments
type SegmentationParser struct {
    tokenCounter *TokenCounter
    config       SegmentationConfig
}

// NewSegmentationParser creates the parser
func NewSegmentationParser(counter *TokenCounter, config SegmentationConfig) *SegmentationParser {
    return &SegmentationParser{tokenCounter: counter, config: config}
}

// Parse splits instruction into sections
func (p *SegmentationParser) Parse(ctx context.Context, content string) ([]ParsedSection, error) {
    lines := strings.Split(content, "\n")
    sections := make([]ParsedSection, 0)

    current := ParsedSection{Title: "Introduction", StartLine: 0}
    var builder strings.Builder

    for lineNum, line := range lines {
        isBoundary := false

        for _, pattern := range sectionPatterns[:4] { // H1, H2, Phase, Numbered
            if pattern.MatchString(line) {
                // Close current section
                if builder.Len() > 0 {
                    current.Content = strings.TrimSpace(builder.String())
                    current.EndLine = lineNum - 1
                    current.TokenCount, _ = p.tokenCounter.Count(current.Content)
                    if p.config.ExtractKeywords {
                        current.Keywords = p.extractKeywords(current.Content)
                    }
                    sections = append(sections, current)
                }

                // Start new section
                matches := pattern.FindStringSubmatch(line)
                title := line
                if len(matches) > 1 {
                    title = matches[1]
                }
                current = ParsedSection{Title: strings.TrimSpace(title), StartLine: lineNum}
                builder.Reset()
                isBoundary = true
                break
            }
        }

        if !isBoundary {
            builder.WriteString(line)
            builder.WriteString("\n")
        }
    }

    // Close final section
    if builder.Len() > 0 {
        current.Content = strings.TrimSpace(builder.String())
        current.EndLine = len(lines) - 1
        current.TokenCount, _ = p.tokenCounter.Count(current.Content)
        if p.config.ExtractKeywords {
            current.Keywords = p.extractKeywords(current.Content)
        }
        sections = append(sections, current)
    }

    // Merge small sections if configured
    if p.config.MergeSmallSections {
        sections = p.mergeSmall(sections)
    }

    return sections, nil
}

func (p *SegmentationParser) extractKeywords(content string) []string {
    seen := make(map[string]bool)
    keywords := make([]string, 0)

    for _, pattern := range keywordPatterns {
        for _, match := range pattern.FindAllString(content, -1) {
            lower := strings.ToLower(match)
            if !seen[lower] {
                keywords = append(keywords, lower)
                seen[lower] = true
            }
        }
    }
    return keywords
}

func (p *SegmentationParser) mergeSmall(sections []ParsedSection) []ParsedSection {
    if len(sections) <= 1 {
        return sections
    }

    merged := make([]ParsedSection, 0)
    acc := sections[0]

    for i := 1; i < len(sections); i++ {
        combined := acc.TokenCount + sections[i].TokenCount

        if acc.TokenCount < p.config.MinTokensPerSegment && combined <= p.config.MaxTokensPerSegment {
            acc.Content += "\n\n" + sections[i].Title + "\n\n" + sections[i].Content
            acc.EndLine = sections[i].EndLine
            acc.TokenCount = combined
            acc.Keywords = append(acc.Keywords, sections[i].Keywords...)
        } else {
            merged = append(merged, acc)
            acc = sections[i]
        }
    }
    merged = append(merged, acc)

    return merged
}
```

### Step 3.2: Dependency Resolver

```go
// internal/services/dependency_resolver.go
package services

import "fmt"

// DependencyType classifies relationships
type DependencyType string

const (
    DependencyStrict    DependencyType = "strict"
    DependencyPreferred DependencyType = "preferred"
)

// KeywordRule defines dependency based on keywords
type KeywordRule struct {
    IfContains      []string
    RequiresKeyword []string
    Type            DependencyType
}

// Default rules
var DefaultKeywordRules = []KeywordRule{
    {[]string{"rbac", "permission"}, []string{"authentication", "user"}, DependencyStrict},
    {[]string{"controller", "handler"}, []string{"service", "repository"}, DependencyPreferred},
    {[]string{"test"}, []string{"service", "model"}, DependencyStrict},
    {[]string{"migration"}, []string{"schema", "model"}, DependencyStrict},
}

// DependencyResolver resolves segment ordering
type DependencyResolver struct {
    rules []KeywordRule
}

// NewDependencyResolver creates the resolver
func NewDependencyResolver(rules []KeywordRule) *DependencyResolver {
    if rules == nil {
        rules = DefaultKeywordRules
    }
    return &DependencyResolver{rules: rules}
}

// TopologicalSort returns execution order
func (r *DependencyResolver) TopologicalSort(sections []ParsedSection) ([]int, error) {
    n := len(sections)
    adjacency := make(map[int][]int)
    inDegree := make([]int, n)

    // Build dependency graph from keywords
    for i, section := range sections {
        for j, other := range sections {
            if i != j && r.hasDependency(section.Keywords, other.Keywords) {
                adjacency[j] = append(adjacency[j], i)
                inDegree[i]++
            }
        }
    }

    // Kahn's algorithm
    queue := make([]int, 0)
    for i := 0; i < n; i++ {
        if inDegree[i] == 0 {
            queue = append(queue, i)
        }
    }

    order := make([]int, 0, n)
    for len(queue) > 0 {
        curr := queue[0]
        queue = queue[1:]
        order = append(order, curr)

        for _, next := range adjacency[curr] {
            inDegree[next]--
            if inDegree[next] == 0 {
                queue = append(queue, next)
            }
        }
    }

    if len(order) != n {
        return nil, fmt.Errorf("cycle detected in dependencies")
    }

    return order, nil
}

func (r *DependencyResolver) hasDependency(dependent, required []string) bool {
    for _, rule := range r.rules {
        if r.containsAny(dependent, rule.IfContains) &&
            r.containsAny(required, rule.RequiresKeyword) {
            return true
        }
    }
    return false
}

func (r *DependencyResolver) containsAny(haystack, needles []string) bool {
    for _, h := range haystack {
        for _, n := range needles {
            if h == n {
                return true
            }
        }
    }
    return false
}
```

---

## Phase 4: Memory Compression (Week 4)

### Step 4.1: Summarization Prompts

```go
// internal/services/summarization_prompts.go
package services

// SummarizationPromptType defines prompt categories
type SummarizationPromptType string

const (
    PromptTypeExecution    SummarizationPromptType = "execution"
    PromptTypeConversation SummarizationPromptType = "conversation"
    PromptTypeIncremental  SummarizationPromptType = "incremental"
)

// Prompt templates
var SummarizationPrompts = map[SummarizationPromptType]string{
    PromptTypeExecution: `Summarize the following execution output for context in the next turn.

PRESERVE:
1. Key Decisions made
2. Artifacts Created (files, components)
3. Dependencies Established
4. Open Questions or pending items

FORMAT:
### Decisions Made
- [decisions with rationale]

### Artifacts Created
- [files with descriptions]

### Pending Items
- [open questions]

Maximum: {{.MaxTokens}} tokens. Be concise.

CONTENT:
{{.Content}}`,

    PromptTypeConversation: `Summarize conversation history for continued interaction.

PRESERVE:
1. User's Goal
2. Agreed Decisions
3. Current Status
4. User Preferences

Maximum: {{.MaxTokens}} tokens.

CONVERSATION:
{{.Content}}`,

    PromptTypeIncremental: `Update existing summary with new information.

EXISTING SUMMARY:
{{.ExistingSummary}}

NEW INFORMATION:
{{.NewContent}}

Merge new info, update changed decisions, maintain format. Maximum: {{.MaxTokens}} tokens.`,
}
```

### Step 4.2: Memory Compression Service

```go
// internal/services/memory_compression.go
package services

import (
    "bytes"
    "context"
    "fmt"
    "regexp"
    "strings"
    "sync"
    "text/template"
    "time"

    "gorm.io/gorm"
)

// CompressionResult holds compression output
type CompressionResult struct {
    OriginalTokens   int
    CompressedTokens int
    CompressionRatio float64
    Summary          string
    KeyDecisions     []string
    ArtifactsCreated []string
    OpenQuestions    []string
}

// MemoryCompressionConfig holds settings
type MemoryCompressionConfig struct {
    DefaultMaxTokens      int
    MinCompressionRatio   float64
    MaxRetries            int
    ExtractStructuredData bool
    CacheEnabled          bool
}

// DefaultMemoryCompressionConfig returns production defaults
func DefaultMemoryCompressionConfig() MemoryCompressionConfig {
    return MemoryCompressionConfig{
        DefaultMaxTokens:      500,
        MinCompressionRatio:   0.5,
        MaxRetries:            2,
        ExtractStructuredData: true,
        CacheEnabled:          true,
    }
}

// AIService interface for LLM calls
type AIService interface {
    Generate(ctx context.Context, prompt string) (string, error)
}

// MemoryCompressionService handles compression
type MemoryCompressionService struct {
    aiService    AIService
    tokenCounter *TokenCounter
    db           *gorm.DB
    config       MemoryCompressionConfig
    cache        sync.Map
}

// NewMemoryCompressionService creates the service
func NewMemoryCompressionService(
    ai AIService,
    counter *TokenCounter,
    db *gorm.DB,
    config MemoryCompressionConfig,
) *MemoryCompressionService {
    return &MemoryCompressionService{
        aiService:    ai,
        tokenCounter: counter,
        db:           db,
        config:       config,
    }
}

// Compress compresses content to target token count
func (s *MemoryCompressionService) Compress(
    ctx context.Context,
    content string,
    targetTokens int,
) (*CompressionResult, error) {
    if targetTokens == 0 {
        targetTokens = s.config.DefaultMaxTokens
    }

    originalTokens, _ := s.tokenCounter.Count(content)

    // Skip if already under target
    if originalTokens <= targetTokens {
        return &CompressionResult{
            OriginalTokens:   originalTokens,
            CompressedTokens: originalTokens,
            CompressionRatio: 1.0,
            Summary:          content,
        }, nil
    }

    // Build prompt
    prompt, err := s.buildPrompt(PromptTypeExecution, content, targetTokens, "")
    if err != nil {
        return nil, err
    }

    // Call AI with retries
    var summary string
    for attempt := 0; attempt <= s.config.MaxRetries; attempt++ {
        summary, err = s.aiService.Generate(ctx, prompt)
        if err == nil {
            break
        }
        time.Sleep(time.Duration(attempt+1) * 500 * time.Millisecond)
    }
    if err != nil {
        return nil, fmt.Errorf("compression failed after retries: %w", err)
    }

    compressedTokens, _ := s.tokenCounter.Count(summary)

    result := &CompressionResult{
        OriginalTokens:   originalTokens,
        CompressedTokens: compressedTokens,
        CompressionRatio: float64(compressedTokens) / float64(originalTokens),
        Summary:          summary,
    }

    // Extract structured data
    if s.config.ExtractStructuredData {
        result.KeyDecisions = s.extractSection(summary, "Decisions Made")
        result.ArtifactsCreated = s.extractSection(summary, "Artifacts Created")
        result.OpenQuestions = s.extractSection(summary, "Pending Items")
    }

    return result, nil
}

// IncrementalMerge merges new content into existing summary
func (s *MemoryCompressionService) IncrementalMerge(
    ctx context.Context,
    existing, newContent string,
    targetTokens int,
) (string, error) {
    prompt, err := s.buildPrompt(PromptTypeIncremental, newContent, targetTokens, existing)
    if err != nil {
        return "", err
    }

    return s.aiService.Generate(ctx, prompt)
}

func (s *MemoryCompressionService) buildPrompt(
    promptType SummarizationPromptType,
    content string,
    maxTokens int,
    existingSummary string,
) (string, error) {
    tmplStr := SummarizationPrompts[promptType]
    tmpl, err := template.New("prompt").Parse(tmplStr)
    if err != nil {
        return "", err
    }

    data := map[string]interface{}{
        "Content":         content,
        "MaxTokens":       maxTokens,
        "ExistingSummary": existingSummary,
        "NewContent":      content,
    }

    var buf bytes.Buffer
    if err := tmpl.Execute(&buf, data); err != nil {
        return "", err
    }

    return buf.String(), nil
}

func (s *MemoryCompressionService) extractSection(summary, sectionName string) []string {
    pattern := regexp.MustCompile(`(?m)###\s+` + sectionName + `\n((?:[-*]\s+.+\n?)+)`)
    match := pattern.FindStringSubmatch(summary)
    if len(match) < 2 {
        return nil
    }

    items := make([]string, 0)
    lines := strings.Split(match[1], "\n")
    for _, line := range lines {
        line = strings.TrimSpace(line)
        if strings.HasPrefix(line, "-") || strings.HasPrefix(line, "*") {
            items = append(items, strings.TrimSpace(line[1:]))
        }
    }
    return items
}
```

---

## Phase 5: Pipeline Integration (Week 5)

### Step 5.1: Full Pipeline Orchestrator

```go
// internal/services/rag_pipeline.go
package services

import (
    "context"
    "fmt"
)

// RAGPipeline orchestrates the complete flow
type RAGPipeline struct {
    vectorSearch   *VectorSearchService
    contextMgr     *ContextAssembler
    segmentParser  *SegmentationParser
    depResolver    *DependencyResolver
    memoryCompress *MemoryCompressionService
    aiService      AIService
}

// NewRAGPipeline creates the orchestrator
func NewRAGPipeline(
    vs *VectorSearchService,
    ctx *ContextAssembler,
    parser *SegmentationParser,
    resolver *DependencyResolver,
    memory *MemoryCompressionService,
    ai AIService,
) *RAGPipeline {
    return &RAGPipeline{
        vectorSearch:   vs,
        contextMgr:     ctx,
        segmentParser:  parser,
        depResolver:    resolver,
        memoryCompress: memory,
        aiService:      ai,
    }
}

// ExecuteInstruction runs the full pipeline
func (p *RAGPipeline) ExecuteInstruction(
    ctx context.Context,
    instruction string,
    queryEmbedding []float32,
) (*PipelineResult, error) {
    result := &PipelineResult{
        SegmentResults: make([]SegmentResult, 0),
    }

    // Phase 1: Vector Search
    chunks, err := p.vectorSearch.SearchHybrid(ctx, queryEmbedding, instruction[:100], 10)
    if err != nil {
        return nil, fmt.Errorf("vector search failed: %w", err)
    }
    result.RetrievedChunks = len(chunks)

    // Phase 2: Context Assembly
    blocks := make([]ContextBlock, 0)
    blocks = append(blocks, ContextBlock{
        Layer:   LayerSystemPrompt,
        Content: "You are a code generation assistant.",
    })
    for _, chunk := range chunks {
        blocks = append(blocks, ContextBlock{
            Layer:       LayerRetrieved,
            Content:     chunk.Content,
            Priority:    chunk.Score,
            CanTruncate: true,
        })
    }
    assembled, err := p.contextMgr.Assemble(ctx, blocks)
    if err != nil {
        return nil, fmt.Errorf("context assembly failed: %w", err)
    }
    result.ContextTokens = assembled.TotalTokens
    result.Truncated = assembled.Truncated

    // Phase 3: Segmentation
    sections, err := p.segmentParser.Parse(ctx, instruction)
    if err != nil {
        return nil, fmt.Errorf("segmentation failed: %w", err)
    }

    order, err := p.depResolver.TopologicalSort(sections)
    if err != nil {
        return nil, fmt.Errorf("dependency resolution failed: %w", err)
    }
    result.SegmentCount = len(sections)
    result.ExecutionOrder = order

    // Phase 4: Execute segments with memory
    var memorySummary string
    for _, idx := range order {
        section := sections[idx]

        // Build execution prompt with memory
        execPrompt := fmt.Sprintf("Previous context:\n%s\n\nExecute:\n%s\n%s",
            memorySummary, section.Title, section.Content)

        output, err := p.aiService.Generate(ctx, execPrompt)
        if err != nil {
            result.SegmentResults = append(result.SegmentResults, SegmentResult{
                Index:  idx,
                Title:  section.Title,
                Status: "failed",
                Error:  err.Error(),
            })
            continue
        }

        // Compress output for next iteration
        compressed, err := p.memoryCompress.Compress(ctx, output, 300)
        if err == nil {
            memorySummary = compressed.Summary
        }

        result.SegmentResults = append(result.SegmentResults, SegmentResult{
            Index:            idx,
            Title:            section.Title,
            Status:           "completed",
            OutputTokens:     len(output) / 4,
            CompressedTokens: compressed.CompressedTokens,
        })
    }

    result.FinalMemory = memorySummary
    result.Success = true

    return result, nil
}

// PipelineResult holds execution results
type PipelineResult struct {
    Success         bool
    RetrievedChunks int
    ContextTokens   int
    Truncated       bool
    SegmentCount    int
    ExecutionOrder  []int
    SegmentResults  []SegmentResult
    FinalMemory     string
}

// SegmentResult holds per-segment results
type SegmentResult struct {
    Index            int
    Title            string
    Status           string
    Error            string
    OutputTokens     int
    CompressedTokens int
}
```

---

## Verification Checklist

### Per-Phase Verification

| Phase | Component | Verification Steps |
|-------|-----------|-------------------|
| 1 | Models | `go test ./internal/models/...` passes |
| 1 | VectorSearch | Hybrid search returns ranked results |
| 1 | VSS | Graceful degradation when unavailable |
| 2 | TokenCounter | Counts match ±20% of tiktoken |
| 2 | ContextAssembler | Respects token limits |
| 2 | Truncation | All strategies work correctly |
| 3 | Parser | Detects H1, H2, Phase markers |
| 3 | DependencyResolver | Topological sort succeeds |
| 3 | CycleDetection | Returns error on cycles |
| 4 | Compression | Achieves ≥50% reduction |
| 4 | Extraction | Parses decisions/artifacts |
| 4 | IncrementalMerge | Preserves existing context |
| 5 | Pipeline | End-to-end execution succeeds |

### Performance Targets

| Operation | Target | P99 |
|-----------|--------|-----|
| Semantic Search (1K vectors) | <50ms | <100ms |
| Hybrid Search | <75ms | <150ms |
| Context Assembly (100 blocks) | <20ms | <50ms |
| Segmentation (10K tokens) | <50ms | <100ms |
| Compression | <500ms | <1s |
| Full Pipeline (1 segment) | <2s | <5s |

---

## Quick Reference

### Import Order

```go
import (
    // Standard library
    "context"
    "fmt"

    // Third-party
    "gorm.io/gorm"

    // Internal
    "project/internal/models"
    "project/internal/services"
)
```

### Error Handling Pattern

```go
result, err := service.Operation(ctx, params)
if err != nil {
    return nil, fmt.Errorf("operation failed: %w", err)
}
```

### Logging Pattern

```go
log.Printf("[%s] %s: %s", "INFO", "VectorSearch", "Indexed 100 embeddings")
log.Printf("[%s] %s: %s - %v", "ERROR", "ContextAssembler", "Truncation failed", err)
```

---

## Cross-References

- [Vector Search Service](./05-vector-search-service.md) - Full specification
- [Context Window Manager](./06-context-window-manager.md) - Full specification
- [Instruction Segmentation](../06-ai-integration/05-instruction-segmentation.md) - Full specification
- [Memory Compression](./07-memory-compression.md) - Full specification
- [Database Schema](../../07-database-design/01-schema.md) - Model definitions
