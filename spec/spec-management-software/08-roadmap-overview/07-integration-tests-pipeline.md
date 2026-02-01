# Integration Tests: RAG Pipeline

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This specification defines comprehensive integration tests for the complete RAG enhancement pipeline: Vector Search → Context Window Management → Instruction Segmentation → Memory Compression. These tests verify end-to-end functionality, component interoperability, and performance characteristics.

**Cross-References:**
- [Vector Search Service](./21-vector-search-service.md) - Phase 1: sqlite-vss integration
- [Context Window Manager](./22-context-window-manager.md) - Phase 2: Token budgeting
- [Instruction Segmentation](./23-instruction-segmentation.md) - Phase 3: Multi-turn execution
- [Memory Compression](./24-memory-compression.md) - Phase 4: Summarization
- [Vector Database Plan](./20-vector-database-plan.md) - Overall enhancement strategy
- [Testing Standards](../../general-spec/03-quality/01-testing-standards-quality.md) - Testing patterns

---

## 25.1 Test Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    INTEGRATION TEST ARCHITECTURE                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐     │
│  │                       Test Orchestrator                              │     │
│  ├─────────────────────────────────────────────────────────────────────┤     │
│  │                                                                       │     │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │     │
│  │  │  Test Suite  │  │  Fixtures    │  │   Mocks &    │               │     │
│  │  │   Runner     │  │   Manager    │  │   Stubs      │               │     │
│  │  └──────────────┘  └──────────────┘  └──────────────┘               │     │
│  │                                                                       │     │
│  └─────────────────────────────────────────────────────────────────────┘     │
│                                    │                                          │
│              ┌─────────────────────┼─────────────────────┐                    │
│              ▼                     ▼                     ▼                    │
│  ┌───────────────────┐ ┌───────────────────┐ ┌───────────────────┐           │
│  │  Unit Integration │ │  Cross-Component  │ │  End-to-End       │           │
│  │  Tests (Per Phase)│ │  Tests (Adjacent) │ │  Pipeline Tests   │           │
│  └───────────────────┘ └───────────────────┘ └───────────────────┘           │
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐     │
│  │                         Pipeline Under Test                          │     │
│  ├─────────────────────────────────────────────────────────────────────┤     │
│  │                                                                       │     │
│  │  Vector    →    Context    →    Segmentation    →    Memory          │     │
│  │  Search         Window           Parser               Compression    │     │
│  │                                                                       │     │
│  └─────────────────────────────────────────────────────────────────────┘     │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 25.2 Test Categories

### Category Overview

| Category | Scope | Dependencies | Mocking Strategy |
|----------|-------|--------------|------------------|
| **Unit Integration** | Single component with DB | SQLite, GORM | Mock AI service |
| **Cross-Component** | Two adjacent components | SQLite, Token Counter | Mock downstream |
| **End-to-End** | Full pipeline | All services | Real or mock LLM |
| **Performance** | Latency, throughput | All services | Real embedding data |
| **Failure/Recovery** | Error handling | All services | Inject failures |

### Test Naming Convention

```go
func Test<Component>_<Scenario>_<ExpectedOutcome>(t *testing.T)

// Examples:
func TestVectorSearch_HybridQuery_ReturnsRankedResults(t *testing.T)
func TestContextWindow_ExceedsBudget_TruncatesLowPriority(t *testing.T)
func TestPipeline_FullInstruction_ExecutesAllSegments(t *testing.T)
```

---

## 25.3 Test Fixtures

### Test Data Factory

```go
package testutil

import (
    "context"
    "fmt"
    "math/rand"
    "strings"
    "time"

    "gorm.io/driver/sqlite"
    "gorm.io/gorm"
)

// TestFixtures provides reusable test data and setup
type TestFixtures struct {
    DB           *gorm.DB
    ProjectID    string
    TokenCounter *MockTokenCounter
    AIService    *MockAIService
}

// NewTestFixtures creates isolated test environment
func NewTestFixtures(t *testing.T) *TestFixtures {
    db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
    if err != nil {
        t.Fatalf("failed to create test db: %v", err)
    }
    
    // Run migrations
    db.AutoMigrate(
        &models.Project{},
        &models.Artifact{},
        &models.Chunk{},
        &models.InstructionSegment{},
        &models.MemoryEntry{},
    )
    
    // Seed test project
    projectID := fmt.Sprintf("test-project-%d", time.Now().UnixNano())
    db.Create(&models.Project{Id: projectID, Name: "Test Project"})
    
    return &TestFixtures{
        DB:           db,
        ProjectID:    projectID,
        TokenCounter: NewMockTokenCounter(),
        AIService:    NewMockAIService(),
    }
}

// CreateTestChunks generates sample chunks with embeddings
func (f *TestFixtures) CreateTestChunks(count int) []TestChunk {
    chunks := make([]TestChunk, count)
    
    topics := []string{"authentication", "database", "api", "frontend", "testing"}
    
    for i := 0; i < count; i++ {
        topic := topics[i%len(topics)]
        chunks[i] = TestChunk{
            ID:        fmt.Sprintf("chunk-%d", i),
            Content:   generateContent(topic, 200+rand.Intn(300)),
            Embedding: generateEmbedding(768),
            Keywords:  []string{topic, "service", "implementation"},
        }
        
        // Persist to DB
        f.DB.Create(&models.Chunk{
            Id:         chunks[i].ID,
            ArtifactId: fmt.Sprintf("artifact-%s", topic),
            Content:    chunks[i].Content,
            ChunkIndex: i,
        })
    }
    
    return chunks
}

// CreateTestInstruction generates a multi-section instruction
func (f *TestFixtures) CreateTestInstruction(sections int, tokensPerSection int) string {
    var builder strings.Builder
    
    sectionTitles := []string{
        "Database Schema Design",
        "API Endpoint Implementation", 
        "Authentication Flow",
        "Frontend Integration",
        "Testing Requirements",
        "Deployment Configuration",
        "Monitoring Setup",
        "Documentation Updates",
    }
    
    for i := 0; i < sections && i < len(sectionTitles); i++ {
        builder.WriteString(fmt.Sprintf("## Phase %d: %s\n\n", i+1, sectionTitles[i]))
        builder.WriteString(generateContent(sectionTitles[i], tokensPerSection*4)) // ~4 chars/token
        builder.WriteString("\n\n---\n\n")
    }
    
    return builder.String()
}

// TestChunk represents a test chunk with all required data
type TestChunk struct {
    ID        string
    Content   string
    Embedding []float32
    Keywords  []string
}

// generateEmbedding creates random normalized embedding
func generateEmbedding(dimensions int) []float32 {
    embedding := make([]float32, dimensions)
    var sum float32
    
    for i := range embedding {
        embedding[i] = rand.Float32()*2 - 1 // [-1, 1]
        sum += embedding[i] * embedding[i]
    }
    
    // Normalize
    norm := float32(math.Sqrt(float64(sum)))
    for i := range embedding {
        embedding[i] /= norm
    }
    
    return embedding
}

// generateContent creates realistic content for testing
func generateContent(topic string, length int) string {
    templates := map[string]string{
        "authentication": "The authentication system implements JWT tokens with bcrypt password hashing. Sessions are stored in SQLite with configurable TTL. Role-based access control (RBAC) restricts endpoint access based on user permissions.",
        "database": "The SQLite database uses GORM for all operations. Tables follow PascalCase naming convention with proper foreign key relationships. Indexes are defined for frequently queried columns.",
        "api": "REST API endpoints follow the standard envelope format with success, data, error, and meta fields. All endpoints require JWT authentication except public routes.",
        "frontend": "The React frontend uses TypeScript with strict mode enabled. Components follow atomic design principles. State management uses React Query for server state and Zustand for client state.",
        "testing": "Test coverage targets 80% for unit tests and 60% for integration tests. Mock services are used for external dependencies. Test fixtures provide repeatable data scenarios.",
    }
    
    base := templates[topic]
    if base == "" {
        base = templates["database"]
    }
    
    // Repeat and truncate to desired length
    repeated := strings.Repeat(base+" ", length/len(base)+1)
    if len(repeated) > length {
        repeated = repeated[:length]
    }
    
    return repeated
}
```

---

### Mock Services

```go
package testutil

import (
    "context"
    "strings"
    "sync"
)

// MockTokenCounter provides predictable token counting
type MockTokenCounter struct {
    CharsPerToken float64
    CountCalls    []string
    mu            sync.Mutex
}

func NewMockTokenCounter() *MockTokenCounter {
    return &MockTokenCounter{CharsPerToken: 4.0}
}

func (m *MockTokenCounter) Count(text string) (int, error) {
    m.mu.Lock()
    m.CountCalls = append(m.CountCalls, text)
    m.mu.Unlock()
    return int(float64(len(text)) / m.CharsPerToken), nil
}

func (m *MockTokenCounter) CountBatch(texts []string) ([]int, error) {
    results := make([]int, len(texts))
    for i, text := range texts {
        count, _ := m.Count(text)
        results[i] = count
    }
    return results, nil
}

func (m *MockTokenCounter) CountMessages(messages []ChatMessage) (int, error) {
    total := 0
    for _, msg := range messages {
        count, _ := m.Count(msg.Content)
        total += count + 4 // Message overhead
    }
    return total + 3, nil // Conversation overhead
}

func (m *MockTokenCounter) EstimateFromChars(charCount int) int {
    return int(float64(charCount) / m.CharsPerToken)
}

func (m *MockTokenCounter) GetTokenizer() string {
    return "mock"
}

// MockAIService provides predictable AI responses
type MockAIService struct {
    GenerateResponses map[string]string
    SummarizeFunc     func(content string, maxTokens int) string
    EmbedFunc         func(text string) []float32
    CallCount         map[string]int
    mu                sync.Mutex
}

func NewMockAIService() *MockAIService {
    return &MockAIService{
        GenerateResponses: make(map[string]string),
        CallCount:         make(map[string]int),
    }
}

func (m *MockAIService) Generate(ctx context.Context, prompt string, options ...AIOption) (string, error) {
    m.mu.Lock()
    m.CallCount["generate"]++
    m.mu.Unlock()
    
    // Return canned response if configured
    for pattern, response := range m.GenerateResponses {
        if strings.Contains(prompt, pattern) {
            return response, nil
        }
    }
    
    return "Default AI response for testing.", nil
}

func (m *MockAIService) Summarize(ctx context.Context, content string, maxTokens int) (string, error) {
    m.mu.Lock()
    m.CallCount["summarize"]++
    m.mu.Unlock()
    
    if m.SummarizeFunc != nil {
        return m.SummarizeFunc(content, maxTokens), nil
    }
    
    // Default: return truncated content with summary header
    words := strings.Fields(content)
    targetWords := maxTokens / 2 // Rough approximation
    if len(words) > targetWords {
        words = words[:targetWords]
    }
    
    return "### Summary\n" + strings.Join(words, " "), nil
}

func (m *MockAIService) Embed(ctx context.Context, text string) ([]float32, error) {
    m.mu.Lock()
    m.CallCount["embed"]++
    m.mu.Unlock()
    
    if m.EmbedFunc != nil {
        return m.EmbedFunc(text), nil
    }
    
    // Default: generate deterministic embedding based on content hash
    return generateEmbeddingFromContent(text, 768), nil
}

// generateEmbeddingFromContent creates semi-deterministic embedding
func generateEmbeddingFromContent(content string, dims int) []float32 {
    embedding := make([]float32, dims)
    
    // Use content hash to seed random values
    seed := int64(0)
    for _, c := range content[:min(100, len(content))] {
        seed += int64(c)
    }
    
    rand.Seed(seed)
    var sum float32
    for i := range embedding {
        embedding[i] = rand.Float32()*2 - 1
        sum += embedding[i] * embedding[i]
    }
    
    // Normalize
    norm := float32(math.Sqrt(float64(sum)))
    for i := range embedding {
        embedding[i] /= norm
    }
    
    return embedding
}
```

---

## 25.4 Vector Search Tests

### Semantic Search Tests

```go
package integration_test

import (
    "context"
    "testing"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestVectorSearch_SemanticSearch_ReturnsSimilarChunks(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    chunks := fixtures.CreateTestChunks(20)
    
    service := services.NewVectorSearchService(fixtures.DB, services.VectorSearchConfig{
        Dimensions:         768,
        DefaultLimit:       5,
        MinSimilarityScore: 0.3,
    })
    require.NoError(t, service.Initialize(context.Background()))
    
    // Index all embeddings
    embeddings := make(map[string][]float32)
    for _, chunk := range chunks {
        embeddings[chunk.ID] = chunk.Embedding
    }
    require.NoError(t, service.IndexBatch(context.Background(), embeddings))
    
    // Act: Search with the first chunk's embedding (should find itself)
    results, err := service.SearchSemantic(context.Background(), chunks[0].Embedding, 5)
    
    // Assert
    require.NoError(t, err)
    assert.NotEmpty(t, results)
    assert.Equal(t, chunks[0].ID, results[0].ChunkId, "First result should be exact match")
    assert.InDelta(t, 1.0, results[0].Score, 0.1, "Self-similarity should be ~1.0")
}

func TestVectorSearch_KeywordSearch_UseFTS5Ranking(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    
    // Create chunks with specific keywords
    fixtures.DB.Exec(`INSERT INTO ChunkFts (chunk_id, content, section_anchor) VALUES 
        ('chunk-1', 'authentication jwt tokens bcrypt password', 'auth-section'),
        ('chunk-2', 'database schema sqlite gorm migrations', 'db-section'),
        ('chunk-3', 'authentication oauth2 session management', 'oauth-section')`)
    
    service := services.NewVectorSearchService(fixtures.DB, services.DefaultVectorSearchConfig())
    
    // Act
    results, err := service.SearchKeyword(context.Background(), "authentication", 10)
    
    // Assert
    require.NoError(t, err)
    assert.Len(t, results, 2, "Should find 2 authentication-related chunks")
    assert.Equal(t, "keyword", results[0].ScoreType)
}

func TestVectorSearch_HybridSearch_CombinesRRF(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    chunks := fixtures.CreateTestChunks(10)
    
    service := services.NewVectorSearchService(fixtures.DB, services.VectorSearchConfig{
        Dimensions:           768,
        HybridSemanticWeight: 0.6,
        HybridKeywordWeight:  0.4,
        RRFConstant:          60,
    })
    require.NoError(t, service.Initialize(context.Background()))
    
    // Index embeddings
    embeddings := make(map[string][]float32)
    for _, chunk := range chunks {
        embeddings[chunk.ID] = chunk.Embedding
    }
    require.NoError(t, service.IndexBatch(context.Background(), embeddings))
    
    // Index FTS
    for _, chunk := range chunks {
        fixtures.DB.Exec(`INSERT INTO ChunkFts (chunk_id, content) VALUES (?, ?)`,
            chunk.ID, chunk.Content)
    }
    
    // Act
    results, err := service.SearchHybrid(
        context.Background(),
        chunks[0].Embedding,
        "authentication database",
        5,
    )
    
    // Assert
    require.NoError(t, err)
    assert.NotEmpty(t, results)
    assert.Equal(t, "hybrid", results[0].ScoreType)
    assert.True(t, results[0].Score > results[len(results)-1].Score, "Results should be ranked")
}

func TestVectorSearch_GracefulDegradation_VSSUnavailable(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    
    service := services.NewVectorSearchService(fixtures.DB, services.DefaultVectorSearchConfig())
    // Don't initialize VSS - simulates unavailable extension
    
    // Act
    err := service.IndexEmbedding(context.Background(), "test-chunk", make([]float32, 768))
    
    // Assert
    assert.NoError(t, err, "Should not error when VSS unavailable")
    assert.False(t, service.IsVssAvailable())
}
```

---

## 25.5 Context Window Tests

### Budget Allocation Tests

```go
func TestContextWindow_BudgetAllocation_RespectsLimits(t *testing.T) {
    // Arrange
    config := services.ContextWindowConfig{
        ModelContextSize:       8192,
        SystemPromptReserve:    500,
        CriticalReserve:        1000,
        ResponseReserve:        1500,
        SafetyMargin:           200,
        RetrievedContextTokens: 3500,
    }
    
    assembler := services.NewContextAssembler(config, testutil.NewMockTokenCounter())
    
    // Act
    budget, err := assembler.CalculateBudget()
    
    // Assert
    require.NoError(t, err)
    assert.Equal(t, 5008, budget.Available, "Available = 8192 - 500 - 1000 - 1500 - 200 + slight adjustments")
    assert.Equal(t, 3200, budget.FixedReservation)
}

func TestContextWindow_Assemble_PrioritizesLayers(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    config := services.ContextWindowConfig{
        ModelContextSize:    4096,
        SystemPromptReserve: 200,
        CriticalReserve:     400,
        ResponseReserve:     500,
        SafetyMargin:        100,
    }
    
    assembler := services.NewContextAssembler(config, fixtures.TokenCounter)
    
    blocks := []services.ContextBlock{
        {Layer: services.LayerSystemPrompt, Content: "You are a helpful assistant.", TokenCount: 50},
        {Layer: services.LayerCritical, Content: "Project: Test. Language: Go.", TokenCount: 100},
        {Layer: services.LayerUserContent, Content: "Implement authentication", TokenCount: 80},
        {Layer: services.LayerRetrieved, Content: strings.Repeat("x", 10000), TokenCount: 2500, CanTruncate: true},
    }
    
    // Act
    result, err := assembler.Assemble(context.Background(), blocks)
    
    // Assert
    require.NoError(t, err)
    assert.True(t, result.Truncated, "Should truncate retrieved content")
    assert.Less(t, result.TotalTokens, config.ModelContextSize)
    assert.Contains(t, result.TruncationLog, "LayerRetrieved")
}

func TestContextWindow_Overflow_AppliesStrategy(t *testing.T) {
    testCases := []struct {
        name     string
        strategy string
        expected string
    }{
        {"Truncate Tail", "tail", "First part remains"},
        {"Truncate Head", "head", "Last part remains"},
        {"Truncate Middle", "middle", "Start...End"},
    }
    
    for _, tc := range testCases {
        t.Run(tc.name, func(t *testing.T) {
            config := services.ContextWindowConfig{
                ModelContextSize:   1000,
                TruncationStrategy: tc.strategy,
                AllowTruncation:    true,
            }
            
            handler := services.NewOverflowHandler(config, testutil.NewMockTokenCounter())
            
            content := strings.Repeat("word ", 500) // ~2000 tokens
            result, truncated := handler.Handle(content, 500)
            
            assert.True(t, truncated)
            assert.LessOrEqual(t, len(result), 2500) // ~500 tokens worth of chars
        })
    }
}
```

---

## 25.6 Segmentation Tests

### Parser Tests

```go
func TestSegmentation_Parse_DetectsMarkdownSections(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    parser := services.NewSegmentationParser(fixtures.TokenCounter, services.DefaultSegmentationConfig())
    
    instruction := `# Phase 1: Setup

Install dependencies and configure the project.

## Step 1.1: Dependencies
Run npm install to get all packages.

# Phase 2: Implementation

Write the core business logic.

## Step 2.1: Models
Define the data models first.

---

# Phase 3: Testing

Add unit and integration tests.`
    
    // Act
    sections, err := parser.Parse(context.Background(), instruction)
    
    // Assert
    require.NoError(t, err)
    assert.GreaterOrEqual(t, len(sections), 3, "Should detect at least 3 major sections")
    assert.Equal(t, "Phase 1: Setup", sections[0].Title)
}

func TestSegmentation_Parse_ExtractsKeywords(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    config := services.SegmentationConfig{
        ExtractKeywords:     true,
        MaxTokensPerSegment: 4000,
    }
    parser := services.NewSegmentationParser(fixtures.TokenCounter, config)
    
    instruction := `## Authentication Service

Implement JWT token generation and validation.
Use bcrypt for password hashing.
Integrate with the user repository for data access.`
    
    // Act
    sections, err := parser.Parse(context.Background(), instruction)
    
    // Assert
    require.NoError(t, err)
    require.Len(t, sections, 1)
    assert.Contains(t, sections[0].Keywords, "authentication")
    assert.Contains(t, sections[0].Keywords, "service")
    assert.Contains(t, sections[0].Keywords, "token")
}

func TestSegmentation_Dependency_DetectsOrder(t *testing.T) {
    // Arrange
    resolver := services.NewDependencyResolver(services.DefaultKeywordRules)
    
    sections := []services.ParsedSection{
        {Title: "RBAC Implementation", Keywords: []string{"rbac", "permission", "role"}},
        {Title: "Authentication Setup", Keywords: []string{"authentication", "user", "session"}},
        {Title: "Audit Logging", Keywords: []string{"audit", "logging"}},
    }
    
    // Act
    graph := resolver.BuildGraph(sections)
    order, err := resolver.TopologicalSort(graph)
    
    // Assert
    require.NoError(t, err)
    
    // Auth should come before RBAC
    authIndex := -1
    rbacIndex := -1
    for i, idx := range order {
        if idx == 1 { authIndex = i }
        if idx == 0 { rbacIndex = i }
    }
    assert.Less(t, authIndex, rbacIndex, "Authentication should be ordered before RBAC")
}

func TestSegmentation_Execution_AdvancesStates(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    fixtures.AIService.GenerateResponses["Implement"] = "Implementation complete. Created user service."
    
    engine := services.NewSegmentExecutionEngine(
        fixtures.AIService,
        fixtures.TokenCounter,
        services.NewMemoryCompressionService(fixtures.AIService, fixtures.TokenCounter, fixtures.DB, services.DefaultMemoryCompressionConfig()),
        services.DefaultExecutionConfig(),
    )
    
    segments := []services.InstructionSegment{
        {Id: "seg-1", Title: "Setup", Status: services.SegmentStatusPending},
        {Id: "seg-2", Title: "Implement", Status: services.SegmentStatusPending, DependsOn: []string{"seg-1"}},
    }
    
    // Act
    plan := engine.CreatePlan(segments)
    result, err := engine.ExecuteNext(context.Background(), plan)
    
    // Assert
    require.NoError(t, err)
    assert.Equal(t, services.SegmentStatusCompleted, result.Status)
    assert.Equal(t, "seg-1", result.SegmentId)
    assert.NotEmpty(t, result.Output)
}
```

---

## 25.7 Memory Compression Tests

### Summarization Tests

```go
func TestMemoryCompression_Compress_ReducesTokens(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    fixtures.AIService.SummarizeFunc = func(content string, maxTokens int) string {
        words := strings.Fields(content)
        if len(words) > maxTokens/2 {
            words = words[:maxTokens/2]
        }
        return "### Summary\n- " + strings.Join(words[:min(10, len(words))], " ")
    }
    
    service := services.NewMemoryCompressionService(
        fixtures.AIService,
        fixtures.TokenCounter,
        fixtures.DB,
        services.MemoryCompressionConfig{
            DefaultMaxTokens:    200,
            MinCompressionRatio: 0.5,
            EnableValidation:    true,
        },
    )
    
    originalContent := strings.Repeat("This is a test sentence for compression. ", 100)
    
    // Act
    result, err := service.CompressWithDetails(
        context.Background(),
        originalContent,
        200,
        services.PromptTypeExecution,
    )
    
    // Assert
    require.NoError(t, err)
    assert.Less(t, result.CompressedTokens, result.OriginalTokens)
    assert.Greater(t, result.CompressionRatio, 0.5)
    assert.NotEmpty(t, result.Summary)
}

func TestMemoryCompression_Incremental_MergesSummaries(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    service := services.NewMemoryCompressionService(
        fixtures.AIService,
        fixtures.TokenCounter,
        fixtures.DB,
        services.DefaultMemoryCompressionConfig(),
    )
    
    existingSummary := `### Decisions Made
- Using JWT for authentication
- bcrypt for password hashing

### Artifacts Created
- user_service.go`
    
    newContent := `Completed database schema implementation.
Created migrations for User, Session, and Token tables.
Decision: Use snake_case for database columns per team convention.`
    
    // Act
    merged, err := service.IncrementalMerge(context.Background(), existingSummary, newContent, 400)
    
    // Assert
    require.NoError(t, err)
    assert.Contains(t, merged, "JWT")  // Preserved from existing
    assert.Contains(t, merged, "database") // Added from new content
}

func TestMemoryCompression_ExtractsStructuredData(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    fixtures.AIService.SummarizeFunc = func(content string, maxTokens int) string {
        return `### Decisions Made
- Chose GORM over raw SQL
- Using bcrypt with cost factor 12

### Artifacts Created
- models/user.go
- services/auth_service.go

### Pending Items
- Need to implement token refresh`
    }
    
    service := services.NewMemoryCompressionService(
        fixtures.AIService,
        fixtures.TokenCounter,
        fixtures.DB,
        services.MemoryCompressionConfig{ExtractStructuredData: true},
    )
    
    // Act
    result, err := service.CompressWithDetails(
        context.Background(),
        "Implementation output...",
        300,
        services.PromptTypeExecution,
    )
    
    // Assert
    require.NoError(t, err)
    assert.Len(t, result.KeyDecisions, 2)
    assert.Contains(t, result.KeyDecisions[0], "GORM")
    assert.Len(t, result.ArtifactsCreated, 2)
    assert.Len(t, result.OpenQuestions, 1)
}
```

---

## 25.8 End-to-End Pipeline Tests

### Full Pipeline Test

```go
func TestPipeline_FullExecution_VectorToMemory(t *testing.T) {
    // Arrange
    ctx := context.Background()
    fixtures := testutil.NewTestFixtures(t)
    
    // Initialize all services
    vectorSearch := services.NewVectorSearchService(fixtures.DB, services.DefaultVectorSearchConfig())
    require.NoError(t, vectorSearch.Initialize(ctx))
    
    contextManager := services.NewContextAssembler(
        services.GetPresetForModel("llama-3-8b"),
        fixtures.TokenCounter,
    )
    
    segmentParser := services.NewSegmentationParser(
        fixtures.TokenCounter,
        services.DefaultSegmentationConfig(),
    )
    
    memoryService := services.NewMemoryCompressionService(
        fixtures.AIService,
        fixtures.TokenCounter,
        fixtures.DB,
        services.DefaultMemoryCompressionConfig(),
    )
    
    executionEngine := services.NewSegmentExecutionEngine(
        fixtures.AIService,
        fixtures.TokenCounter,
        memoryService,
        services.DefaultExecutionConfig(),
    )
    
    // Create test data
    chunks := fixtures.CreateTestChunks(50)
    embeddings := make(map[string][]float32)
    for _, chunk := range chunks {
        embeddings[chunk.ID] = chunk.Embedding
    }
    require.NoError(t, vectorSearch.IndexBatch(ctx, embeddings))
    
    instruction := fixtures.CreateTestInstruction(4, 1000)
    
    // Act: Phase 1 - Vector Search
    queryEmbedding := fixtures.AIService.Embed(ctx, "authentication implementation")
    searchResults, err := vectorSearch.SearchHybrid(ctx, queryEmbedding, "authentication", 10)
    require.NoError(t, err)
    t.Logf("Phase 1: Retrieved %d chunks", len(searchResults))
    
    // Act: Phase 2 - Context Assembly
    blocks := make([]services.ContextBlock, 0)
    blocks = append(blocks, services.ContextBlock{
        Layer: services.LayerSystemPrompt,
        Content: "You are a code generator.",
        TokenCount: 50,
    })
    for _, chunk := range searchResults {
        blocks = append(blocks, services.ContextBlock{
            Layer:       services.LayerRetrieved,
            Content:     chunk.Content,
            TokenCount:  fixtures.TokenCounter.EstimateFromChars(len(chunk.Content)),
            CanTruncate: true,
            Priority:    chunk.Score,
        })
    }
    assembled, err := contextManager.Assemble(ctx, blocks)
    require.NoError(t, err)
    t.Logf("Phase 2: Assembled %d tokens (truncated: %v)", assembled.TotalTokens, assembled.Truncated)
    
    // Act: Phase 3 - Segmentation
    sections, err := segmentParser.Parse(ctx, instruction)
    require.NoError(t, err)
    t.Logf("Phase 3: Parsed %d sections", len(sections))
    
    segments := make([]services.InstructionSegment, len(sections))
    for i, section := range sections {
        segments[i] = services.InstructionSegment{
            Id:      fmt.Sprintf("seg-%d", i),
            Title:   section.Title,
            Content: section.Content,
            Status:  services.SegmentStatusPending,
        }
    }
    
    resolver := services.NewDependencyResolver(services.DefaultKeywordRules)
    graph := resolver.BuildGraph(sections)
    order, err := resolver.TopologicalSort(graph)
    require.NoError(t, err)
    t.Logf("Phase 3: Execution order: %v", order)
    
    // Act: Phase 4 - Execute with Memory Compression
    plan := executionEngine.CreatePlan(segments)
    var memorySummary string
    
    for i := 0; i < len(segments); i++ {
        result, err := executionEngine.ExecuteNextWithMemory(ctx, plan, memorySummary)
        if err != nil {
            t.Logf("Segment %d execution error: %v", i, err)
            break
        }
        
        // Compress output for next iteration
        compressed, err := memoryService.Compress(ctx, result.Output, 200)
        require.NoError(t, err)
        memorySummary = compressed
        
        t.Logf("Phase 4: Segment %d completed, memory size: %d chars", i, len(memorySummary))
    }
    
    // Assert: Full pipeline completed
    assert.NotEmpty(t, searchResults, "Vector search should return results")
    assert.True(t, assembled.TotalTokens < 8192, "Context should fit in window")
    assert.GreaterOrEqual(t, len(sections), 3, "Should parse multiple sections")
    assert.NotEmpty(t, memorySummary, "Should have compressed memory")
}

func TestPipeline_LargeInstruction_MultiTurnExecution(t *testing.T) {
    // Arrange
    ctx := context.Background()
    fixtures := testutil.NewTestFixtures(t)
    
    // Create a large instruction that requires segmentation
    instruction := fixtures.CreateTestInstruction(10, 2000) // 10 sections, ~2000 tokens each
    
    segmentParser := services.NewSegmentationParser(
        fixtures.TokenCounter,
        services.SegmentationConfig{
            MaxTokensPerSegment: 4000,
            MinTokensPerSegment: 500,
            MergeSmallSections:  true,
        },
    )
    
    memoryService := services.NewMemoryCompressionService(
        fixtures.AIService,
        fixtures.TokenCounter,
        fixtures.DB,
        services.DefaultMemoryCompressionConfig(),
    )
    
    multiTurn := services.NewMultiTurnExecutor(
        fixtures.AIService,
        segmentParser,
        services.NewDependencyResolver(nil),
        memoryService,
        services.DefaultExecutionConfig(),
    )
    
    // Act
    result, err := multiTurn.Execute(ctx, instruction)
    
    // Assert
    require.NoError(t, err)
    assert.True(t, result.Completed)
    assert.GreaterOrEqual(t, result.TurnsExecuted, 3, "Large instruction should require multiple turns")
    assert.NotEmpty(t, result.FinalSummary)
    assert.Empty(t, result.FailedSegments)
}
```

---

## 25.9 Performance Tests

### Latency Tests

```go
func TestPerformance_VectorSearch_Under50ms(t *testing.T) {
    if testing.Short() {
        t.Skip("Skipping performance test in short mode")
    }
    
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    chunks := fixtures.CreateTestChunks(1000) // Large dataset
    
    service := services.NewVectorSearchService(fixtures.DB, services.DefaultVectorSearchConfig())
    require.NoError(t, service.Initialize(context.Background()))
    
    embeddings := make(map[string][]float32)
    for _, chunk := range chunks {
        embeddings[chunk.ID] = chunk.Embedding
    }
    require.NoError(t, service.IndexBatch(context.Background(), embeddings))
    
    queryEmbedding := generateEmbedding(768)
    
    // Act
    start := time.Now()
    _, err := service.SearchSemantic(context.Background(), queryEmbedding, 10)
    elapsed := time.Since(start)
    
    // Assert
    require.NoError(t, err)
    assert.Less(t, elapsed, 50*time.Millisecond, "Semantic search should complete under 50ms")
    t.Logf("Search latency: %v", elapsed)
}

func TestPerformance_ContextAssembly_Under20ms(t *testing.T) {
    if testing.Short() {
        t.Skip("Skipping performance test in short mode")
    }
    
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    assembler := services.NewContextAssembler(
        services.GetPresetForModel("gemini-flash"),
        fixtures.TokenCounter,
    )
    
    // Create 100 content blocks
    blocks := make([]services.ContextBlock, 100)
    for i := range blocks {
        blocks[i] = services.ContextBlock{
            Layer:       services.LayerRetrieved,
            Content:     generateContent("database", 500),
            TokenCount:  125,
            CanTruncate: true,
            Priority:    float64(100-i) / 100.0,
        }
    }
    
    // Act
    start := time.Now()
    _, err := assembler.Assemble(context.Background(), blocks)
    elapsed := time.Since(start)
    
    // Assert
    require.NoError(t, err)
    assert.Less(t, elapsed, 20*time.Millisecond, "Context assembly should complete under 20ms")
    t.Logf("Assembly latency: %v", elapsed)
}

func TestPerformance_Segmentation_Under100ms(t *testing.T) {
    if testing.Short() {
        t.Skip("Skipping performance test in short mode")
    }
    
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    parser := services.NewSegmentationParser(fixtures.TokenCounter, services.DefaultSegmentationConfig())
    
    // Create large instruction (~50K tokens)
    instruction := fixtures.CreateTestInstruction(20, 2500)
    
    // Act
    start := time.Now()
    sections, err := parser.Parse(context.Background(), instruction)
    elapsed := time.Since(start)
    
    // Assert
    require.NoError(t, err)
    assert.NotEmpty(t, sections)
    assert.Less(t, elapsed, 100*time.Millisecond, "Parsing should complete under 100ms")
    t.Logf("Parse latency: %v for %d sections", elapsed, len(sections))
}
```

---

### Throughput Tests

```go
func TestThroughput_VectorIndexing_BatchPerformance(t *testing.T) {
    if testing.Short() {
        t.Skip("Skipping throughput test in short mode")
    }
    
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    service := services.NewVectorSearchService(fixtures.DB, services.DefaultVectorSearchConfig())
    require.NoError(t, service.Initialize(context.Background()))
    
    batchSizes := []int{100, 500, 1000}
    
    for _, batchSize := range batchSizes {
        t.Run(fmt.Sprintf("BatchSize_%d", batchSize), func(t *testing.T) {
            embeddings := make(map[string][]float32)
            for i := 0; i < batchSize; i++ {
                embeddings[fmt.Sprintf("throughput-chunk-%d", i)] = generateEmbedding(768)
            }
            
            start := time.Now()
            err := service.IndexBatch(context.Background(), embeddings)
            elapsed := time.Since(start)
            
            require.NoError(t, err)
            
            rate := float64(batchSize) / elapsed.Seconds()
            t.Logf("Indexed %d embeddings in %v (%.0f/sec)", batchSize, elapsed, rate)
            
            assert.Greater(t, rate, 100.0, "Should index at least 100 embeddings/sec")
        })
    }
}

func TestThroughput_MemoryCompression_ConcurrentRequests(t *testing.T) {
    if testing.Short() {
        t.Skip("Skipping throughput test in short mode")
    }
    
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    service := services.NewMemoryCompressionService(
        fixtures.AIService,
        fixtures.TokenCounter,
        fixtures.DB,
        services.MemoryCompressionConfig{CacheEnabled: true},
    )
    
    content := strings.Repeat("Test content for compression benchmark. ", 50)
    concurrency := 10
    iterations := 50
    
    // Act
    start := time.Now()
    var wg sync.WaitGroup
    errors := make(chan error, concurrency*iterations)
    
    for i := 0; i < concurrency; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            for j := 0; j < iterations; j++ {
                _, err := service.Compress(context.Background(), content, 100)
                if err != nil {
                    errors <- err
                }
            }
        }()
    }
    
    wg.Wait()
    close(errors)
    elapsed := time.Since(start)
    
    // Assert
    errorCount := 0
    for range errors {
        errorCount++
    }
    
    totalRequests := concurrency * iterations
    rate := float64(totalRequests) / elapsed.Seconds()
    
    t.Logf("Completed %d compression requests in %v (%.0f/sec), %d errors",
        totalRequests, elapsed, rate, errorCount)
    
    assert.Zero(t, errorCount, "Should have no errors")
    assert.Greater(t, rate, 50.0, "Should handle at least 50 compressions/sec")
}
```

---

## 25.10 Failure & Recovery Tests

### Error Handling Tests

```go
func TestFailure_VectorSearch_InvalidDimensions(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    service := services.NewVectorSearchService(fixtures.DB, services.VectorSearchConfig{
        Dimensions: 768,
    })
    require.NoError(t, service.Initialize(context.Background()))
    
    // Act: Try to index wrong dimension embedding
    wrongEmbed := make([]float32, 512) // Wrong dimensions
    err := service.IndexEmbedding(context.Background(), "test", wrongEmbed)
    
    // Assert
    assert.Error(t, err)
    assert.Contains(t, err.Error(), "dimension mismatch")
}

func TestFailure_ContextWindow_ExceedsModelLimit(t *testing.T) {
    // Arrange
    config := services.ContextWindowConfig{
        ModelContextSize: 1000,
        AllowTruncation:  false, // Strict mode
    }
    
    assembler := services.NewContextAssembler(config, testutil.NewMockTokenCounter())
    
    blocks := []services.ContextBlock{
        {Layer: services.LayerUserContent, Content: strings.Repeat("x", 5000), TokenCount: 1250},
    }
    
    // Act
    _, err := assembler.Assemble(context.Background(), blocks)
    
    // Assert
    assert.Error(t, err)
    assert.Contains(t, err.Error(), "exceeds model limit")
}

func TestFailure_Segmentation_CyclicDependency(t *testing.T) {
    // Arrange
    resolver := services.NewDependencyResolver(nil)
    
    sections := []services.ParsedSection{
        {Title: "A", Keywords: []string{"depends_on_b"}},
        {Title: "B", Keywords: []string{"depends_on_c"}},
        {Title: "C", Keywords: []string{"depends_on_a"}},
    }
    
    // Mock cyclic dependencies
    graph := &services.DependencyGraph{
        Segments: sections,
        Adjacency: map[int][]int{
            0: {1}, // A depends on B
            1: {2}, // B depends on C
            2: {0}, // C depends on A (cycle!)
        },
    }
    
    // Act
    _, err := resolver.TopologicalSort(graph)
    
    // Assert
    assert.Error(t, err)
    assert.Contains(t, err.Error(), "cycle detected")
}

func TestFailure_MemoryCompression_AIServiceUnavailable(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    fixtures.AIService.GenerateResponses["*"] = "" // Will cause error
    
    // Create failing AI service
    failingAI := &testutil.FailingAIService{Error: fmt.Errorf("AI service unavailable")}
    
    service := services.NewMemoryCompressionService(
        failingAI,
        fixtures.TokenCounter,
        fixtures.DB,
        services.MemoryCompressionConfig{MaxRetries: 2},
    )
    
    // Act
    _, err := service.Compress(context.Background(), "test content", 100)
    
    // Assert
    assert.Error(t, err)
    assert.Equal(t, 3, failingAI.CallCount, "Should retry 2 times + initial")
}

func TestRecovery_SegmentExecution_RetryOnFailure(t *testing.T) {
    // Arrange
    fixtures := testutil.NewTestFixtures(t)
    
    callCount := 0
    fixtures.AIService.GenerateResponses["Implement"] = ""
    fixtures.AIService.GenerateFunc = func(prompt string) (string, error) {
        callCount++
        if callCount < 3 {
            return "", fmt.Errorf("transient error")
        }
        return "Success after retry", nil
    }
    
    engine := services.NewSegmentExecutionEngine(
        fixtures.AIService,
        fixtures.TokenCounter,
        nil,
        services.ExecutionConfig{MaxRetries: 3},
    )
    
    segment := services.InstructionSegment{
        Id:      "retry-test",
        Title:   "Test Segment",
        Content: "Implement something",
        Status:  services.SegmentStatusPending,
    }
    
    // Act
    result, err := engine.ExecuteSegment(context.Background(), segment, "")
    
    // Assert
    require.NoError(t, err)
    assert.Equal(t, services.SegmentStatusCompleted, result.Status)
    assert.Equal(t, 3, callCount, "Should succeed on third attempt")
}
```

---

## 25.11 Test Configuration

### Test Runner Configuration

```go
// testconfig.go
package testutil

import (
    "os"
    "strconv"
    "time"
)

// TestConfig holds test execution configuration
type TestConfig struct {
    // Timeouts
    DefaultTimeout     time.Duration
    LongTimeout        time.Duration
    
    // Performance thresholds
    VectorSearchMaxMs  int
    ContextAssemblyMaxMs int
    CompressionMaxMs   int
    
    // Feature flags
    RunPerformanceTests bool
    RunStressTests      bool
    VerboseLogging      bool
    
    // Resources
    MaxConcurrency     int
    TestDBPath         string
}

// DefaultTestConfig returns standard test configuration
func DefaultTestConfig() TestConfig {
    return TestConfig{
        DefaultTimeout:       30 * time.Second,
        LongTimeout:          2 * time.Minute,
        VectorSearchMaxMs:    50,
        ContextAssemblyMaxMs: 20,
        CompressionMaxMs:     200,
        RunPerformanceTests:  os.Getenv("RUN_PERF_TESTS") == "true",
        RunStressTests:       os.Getenv("RUN_STRESS_TESTS") == "true",
        VerboseLogging:       os.Getenv("VERBOSE_TESTS") == "true",
        MaxConcurrency:       parseEnvInt("TEST_CONCURRENCY", 10),
        TestDBPath:           ":memory:",
    }
}

func parseEnvInt(key string, defaultVal int) int {
    if val := os.Getenv(key); val != "" {
        if i, err := strconv.Atoi(val); err == nil {
            return i
        }
    }
    return defaultVal
}
```

---

### CI/CD Integration

```yaml
# .github/workflows/integration-tests.yml
name: RAG Pipeline Integration Tests

on:
  push:
    branches: [main, develop]
    paths:
      - 'internal/services/**'
      - 'internal/models/**'
      - 'tests/integration/**'
  pull_request:
    branches: [main]

jobs:
  integration-tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: '1.22'
      
      - name: Install sqlite-vss
        run: |
          # Install sqlite-vss extension for vector search tests
          wget https://github.com/asg017/sqlite-vss/releases/download/v0.1.2/sqlite-vss-v0.1.2-linux-x86_64.tar.gz
          tar -xzf sqlite-vss-v0.1.2-linux-x86_64.tar.gz
          sudo mv vss0.so /usr/lib/
      
      - name: Run Unit Integration Tests
        run: go test -v -race -coverprofile=coverage.txt ./tests/integration/...
        env:
          CGO_ENABLED: 1
      
      - name: Run Performance Tests
        if: github.event_name == 'push' && github.ref == 'refs/heads/main'
        run: go test -v -run TestPerformance ./tests/integration/...
        env:
          RUN_PERF_TESTS: true
      
      - name: Upload Coverage
        uses: codecov/codecov-action@v4
        with:
          file: coverage.txt
          flags: integration
```

---

## 25.12 Test Data Requirements

### Minimum Dataset Sizes

| Test Category | Chunks | Instructions | Memory Entries |
|---------------|--------|--------------|----------------|
| Unit Integration | 10-50 | 1-2 | 0-5 |
| Cross-Component | 50-200 | 2-5 | 5-20 |
| End-to-End | 200-1000 | 5-10 | 20-100 |
| Performance | 1000-10000 | 10-50 | 100-500 |
| Stress | 10000+ | 100+ | 1000+ |

### Required Test Artifacts

```
tests/
├── integration/
│   ├── fixtures/
│   │   ├── sample_instructions/
│   │   │   ├── simple_3_sections.md
│   │   │   ├── complex_10_sections.md
│   │   │   └── with_dependencies.md
│   │   ├── sample_chunks/
│   │   │   ├── auth_chunks.json
│   │   │   ├── database_chunks.json
│   │   │   └── mixed_chunks.json
│   │   └── sample_embeddings/
│   │       └── precomputed_768d.bin
│   ├── vector_search_test.go
│   ├── context_window_test.go
│   ├── segmentation_test.go
│   ├── memory_compression_test.go
│   ├── pipeline_test.go
│   ├── performance_test.go
│   └── failure_test.go
├── testutil/
│   ├── fixtures.go
│   ├── mocks.go
│   ├── generators.go
│   └── config.go
└── README.md
```

---

## 25.13 Acceptance Criteria

### Test Coverage Requirements

| Component | Line Coverage | Branch Coverage |
|-----------|---------------|-----------------|
| VectorSearchService | ≥80% | ≥70% |
| ContextAssembler | ≥85% | ≥75% |
| SegmentationParser | ≥80% | ≥70% |
| DependencyResolver | ≥90% | ≥80% |
| MemoryCompressionService | ≥80% | ≥70% |
| Pipeline Integration | ≥75% | ≥65% |

### Performance Requirements

| Metric | Target | P99 Limit |
|--------|--------|-----------|
| Semantic Search (1K vectors) | <50ms | <100ms |
| Hybrid Search (1K vectors) | <75ms | <150ms |
| Context Assembly (100 blocks) | <20ms | <50ms |
| Instruction Parsing (10K tokens) | <50ms | <100ms |
| Memory Compression | <500ms | <1000ms |
| Full Pipeline (1 segment) | <2s | <5s |

### Pass/Fail Criteria

- [ ] All unit integration tests pass
- [ ] All cross-component tests pass
- [ ] End-to-end pipeline test completes successfully
- [ ] Performance tests meet latency targets
- [ ] No memory leaks detected in stress tests
- [ ] Coverage meets minimum thresholds
- [ ] No race conditions detected (go test -race)

---

## Cross-References

- [Vector Search Service](./21-vector-search-service.md) - Component under test
- [Context Window Manager](./22-context-window-manager.md) - Component under test
- [Instruction Segmentation](./23-instruction-segmentation.md) - Component under test
- [Memory Compression](./24-memory-compression.md) - Component under test
- [Testing Standards](../../general-spec/03-quality/01-testing-standards-quality.md) - Testing patterns
- [Database Schema](../07-database-design/01-schema.md) - Data models
