# Context Window Manager Implementation

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This specification defines the `ContextWindowManager` service for managing LLM context window limitations. The service provides token counting, hierarchical context assembly, budget allocation, and overflow handling to ensure prompts never exceed model limits while maximizing relevant context inclusion.

**Cross-References:**
- [Vector Database Plan](./04-vector-database-plan.md) - Overall enhancement strategy (§20.3)
- [Vector Search Service](./05-vector-search-service.md) - Retrieval integration
- [RAG System](./01-rag-system.md) - Context retrieval pipeline
- [AI Integration](../06-ai-integration/01-ai-integration.md) - LLM invocation
- [Instruction System](../06-ai-integration/03-instruction-system.md) - Instruction execution

---

## 22.1 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      CONTEXT WINDOW MANAGER ARCHITECTURE                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐     │
│  │                     ContextWindowManager                             │     │
│  ├─────────────────────────────────────────────────────────────────────┤     │
│  │                                                                       │     │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │     │
│  │  │ TokenCounter │  │ BudgetAlloc  │  │ Hierarchical │               │     │
│  │  │   Service    │  │   Engine     │  │  Assembler   │               │     │
│  │  └──────────────┘  └──────────────┘  └──────────────┘               │     │
│  │         │                 │                 │                        │     │
│  │         └─────────────────┼─────────────────┘                        │     │
│  │                           ▼                                          │     │
│  │  ┌──────────────────────────────────────────────────────────────┐   │     │
│  │  │                   Context Assembly Pipeline                   │   │     │
│  │  ├──────────────────────────────────────────────────────────────┤   │     │
│  │  │  Layer 1 → Layer 2 → Layer 3 → Layer 4 → Layer 5 → Validate  │   │     │
│  │  └──────────────────────────────────────────────────────────────┘   │     │
│  │                                                                       │     │
│  └─────────────────────────────────────────────────────────────────────┘     │
│                                    │                                          │
│                    ┌───────────────┼───────────────┐                          │
│                    ▼               ▼               ▼                          │
│  ┌─────────────────────┐ ┌─────────────────┐ ┌─────────────────────────┐     │
│  │   Model Registry    │ │   RAG Service   │ │  Overflow Handler       │     │
│  │   (Context Sizes)   │ │   (Retrieval)   │ │  (Truncation/Split)     │     │
│  └─────────────────────┘ └─────────────────┘ └─────────────────────────┘     │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 22.2 Configuration

### ContextWindowConfig

```go
package services

import (
    "fmt"
)

// ContextWindowConfig defines budget allocation for context window
type ContextWindowConfig struct {
    // Model limits
    ModelContextSize int `json:"modelContextSize"` // Total model context window (tokens)
    ModelName        string `json:"modelName"`      // Model identifier for lookup
    
    // Fixed reservations (guaranteed allocation)
    SystemPromptReserve  int `json:"systemPromptReserve"`  // Layer 1: System prompt
    CriticalReserve      int `json:"criticalReserve"`      // Layer 2: Critical context
    ResponseReserve      int `json:"responseReserve"`      // Layer 5: Response buffer
    SafetyMargin         int `json:"safetyMargin"`         // Tokenization variance buffer
    
    // Dynamic allocation (flexible based on content)
    UserQueryMaxTokens     int `json:"userQueryMaxTokens"`     // Layer 3: Max for user query
    InstructionMaxTokens   int `json:"instructionMaxTokens"`   // Layer 3: Max for instruction
    RetrievedContextTokens int `json:"retrievedContextTokens"` // Layer 4: Retrieved chunks
    
    // Priority weights for overflow handling
    SemanticChunkWeight float64 `json:"semanticChunkWeight"` // Priority for semantic chunks
    KeywordChunkWeight  float64 `json:"keywordChunkWeight"`  // Priority for keyword chunks
    RecentArtifactWeight float64 `json:"recentArtifactWeight"` // Priority for recent items
    PinnedArtifactWeight float64 `json:"pinnedArtifactWeight"` // Priority for pinned items
    
    // Behavior flags
    AllowTruncation     bool `json:"allowTruncation"`     // Allow content truncation
    TruncationStrategy  string `json:"truncationStrategy"` // "tail" | "head" | "middle"
    EnableOverflowWarning bool `json:"enableOverflowWarning"` // Log warnings on overflow
}

// DefaultContextWindowConfig returns sensible defaults for LLaMA 3 8B
func DefaultContextWindowConfig() ContextWindowConfig {
    return ContextWindowConfig{
        ModelContextSize:     8192,
        ModelName:            "llama-3-8b",
        SystemPromptReserve:  500,
        CriticalReserve:      1000,
        ResponseReserve:      1500,
        SafetyMargin:         200,
        UserQueryMaxTokens:   500,
        InstructionMaxTokens: 1000,
        RetrievedContextTokens: 3500,
        SemanticChunkWeight:  0.4,
        KeywordChunkWeight:   0.3,
        RecentArtifactWeight: 0.2,
        PinnedArtifactWeight: 0.1,
        AllowTruncation:      true,
        TruncationStrategy:   "tail",
        EnableOverflowWarning: true,
    }
}

// AvailableForRetrieval calculates remaining tokens for dynamic content
func (c *ContextWindowConfig) AvailableForRetrieval() int {
    fixed := c.SystemPromptReserve + c.CriticalReserve + c.ResponseReserve + c.SafetyMargin
    return c.ModelContextSize - fixed
}

// AvailableForUserContent calculates tokens for query + instruction
func (c *ContextWindowConfig) AvailableForUserContent() int {
    return c.UserQueryMaxTokens + c.InstructionMaxTokens
}

// TotalFixedReservation returns sum of all fixed allocations
func (c *ContextWindowConfig) TotalFixedReservation() int {
    return c.SystemPromptReserve + c.CriticalReserve + c.ResponseReserve + c.SafetyMargin
}

// Validate checks configuration for logical errors
func (c *ContextWindowConfig) Validate() error {
    if c.ModelContextSize <= 0 {
        return fmt.Errorf("ModelContextSize must be positive")
    }
    
    fixed := c.TotalFixedReservation()
    if fixed >= c.ModelContextSize {
        return fmt.Errorf("fixed reservations (%d) exceed model context (%d)", 
            fixed, c.ModelContextSize)
    }
    
    if c.ResponseReserve < 100 {
        return fmt.Errorf("ResponseReserve too small (min 100)")
    }
    
    totalWeight := c.SemanticChunkWeight + c.KeywordChunkWeight + 
                   c.RecentArtifactWeight + c.PinnedArtifactWeight
    if totalWeight < 0.99 || totalWeight > 1.01 {
        return fmt.Errorf("priority weights must sum to 1.0 (got %.2f)", totalWeight)
    }
    
    return nil
}
```

---

### Model-Specific Presets

```go
// ModelContextPresets provides defaults for known models
var ModelContextPresets = map[string]ContextWindowConfig{
    "llama-3-8b": {
        ModelContextSize:    8192,
        ModelName:           "llama-3-8b",
        SystemPromptReserve: 500,
        CriticalReserve:     1000,
        ResponseReserve:     1500,
        SafetyMargin:        200,
    },
    "llama-3-70b": {
        ModelContextSize:    8192,
        ModelName:           "llama-3-70b",
        SystemPromptReserve: 500,
        CriticalReserve:     1000,
        ResponseReserve:     2000,
        SafetyMargin:        200,
    },
    "gemini-pro": {
        ModelContextSize:    32768,
        ModelName:           "gemini-pro",
        SystemPromptReserve: 1000,
        CriticalReserve:     3000,
        ResponseReserve:     4000,
        SafetyMargin:        500,
    },
    "gemini-flash": {
        ModelContextSize:    1000000,
        ModelName:           "gemini-flash",
        SystemPromptReserve: 2000,
        CriticalReserve:     10000,
        ResponseReserve:     50000,
        SafetyMargin:        1000,
    },
}

// GetPresetForModel returns preset config or default
func GetPresetForModel(modelName string) ContextWindowConfig {
    if preset, ok := ModelContextPresets[modelName]; ok {
        return preset
    }
    return DefaultContextWindowConfig()
}
```

---

## 22.3 Token Counting Service

### Interface Definition

```go
// TokenCounter provides token counting for various content types
type TokenCounter interface {
    // Count returns token count for text
    Count(text string) (int, error)
    
    // CountBatch counts tokens for multiple texts
    CountBatch(texts []string) ([]int, error)
    
    // CountMessages counts tokens for chat message format
    CountMessages(messages []ChatMessage) (int, error)
    
    // EstimateFromChars provides fast approximation
    EstimateFromChars(charCount int) int
    
    // GetTokenizer returns tokenizer name
    GetTokenizer() string
}

// ChatMessage represents a message in chat format
type ChatMessage struct {
    Role    string `json:"role"`    // "system" | "user" | "assistant"
    Content string `json:"content"`
}
```

---

### Token Counter Implementation

```go
package services

import (
    "context"
    "fmt"
    "strings"
    "sync"
    "unicode/utf8"
)

// TokenCounterImpl implements TokenCounter using tiktoken-compatible counting
type TokenCounterImpl struct {
    tokenizer    string
    avgCharsPerToken float64
    cache        sync.Map // LRU cache for repeated strings
    cacheMaxSize int
}

// NewTokenCounter creates a new token counter
func NewTokenCounter(tokenizer string) *TokenCounterImpl {
    avgChars := 4.0 // Default for English text
    
    // Model-specific adjustments
    switch tokenizer {
    case "llama":
        avgChars = 3.8
    case "gpt-4":
        avgChars = 4.0
    case "gemini":
        avgChars = 3.5
    }
    
    return &TokenCounterImpl{
        tokenizer:        tokenizer,
        avgCharsPerToken: avgChars,
        cacheMaxSize:     1000,
    }
}

// Count returns exact token count for text
func (t *TokenCounterImpl) Count(text string) (int, error) {
    if text == "" {
        return 0, nil
    }
    
    // Check cache first
    if cached, ok := t.cache.Load(text); ok {
        return cached.(int), nil
    }
    
    // Calculate tokens
    // For production: integrate with actual tokenizer (tiktoken, sentencepiece)
    // For now: use heuristic based on word boundaries and special characters
    tokens := t.countTokensHeuristic(text)
    
    // Cache result (with size limit)
    t.cache.Store(text, tokens)
    
    return tokens, nil
}

// countTokensHeuristic provides reasonable approximation without external deps
func (t *TokenCounterImpl) countTokensHeuristic(text string) int {
    // Base: character count / average chars per token
    charCount := utf8.RuneCountInString(text)
    baseEstimate := float64(charCount) / t.avgCharsPerToken
    
    // Adjust for special patterns
    adjustments := 0.0
    
    // Code blocks tend to have more tokens per char
    codeBlockCount := strings.Count(text, "```")
    adjustments += float64(codeBlockCount) * 2.0
    
    // URLs and paths tokenize differently
    urlCount := strings.Count(text, "http")
    adjustments += float64(urlCount) * 5.0
    
    // Markdown headers add overhead
    headerCount := strings.Count(text, "\n#")
    adjustments += float64(headerCount) * 1.0
    
    // JSON/code syntax
    braceCount := strings.Count(text, "{") + strings.Count(text, "}")
    adjustments += float64(braceCount) * 0.5
    
    return int(baseEstimate + adjustments)
}

// CountBatch counts tokens for multiple texts efficiently
func (t *TokenCounterImpl) CountBatch(texts []string) ([]int, error) {
    results := make([]int, len(texts))
    
    for i, text := range texts {
        count, err := t.Count(text)
        if err != nil {
            return nil, fmt.Errorf("failed counting text %d: %w", i, err)
        }
        results[i] = count
    }
    
    return results, nil
}

// CountMessages counts tokens for chat message format with overhead
func (t *TokenCounterImpl) CountMessages(messages []ChatMessage) (int, error) {
    total := 0
    
    // Each message has format overhead
    messageOverhead := 4 // <|role|>\n...<|end|>
    
    for _, msg := range messages {
        count, err := t.Count(msg.Content)
        if err != nil {
            return 0, err
        }
        total += count + messageOverhead
        
        // Role tokens
        roleTokens := 1
        if msg.Role == "assistant" {
            roleTokens = 2
        }
        total += roleTokens
    }
    
    // Conversation overhead
    total += 3 // <|begin|>...<|end|>
    
    return total, nil
}

// EstimateFromChars provides fast approximation without full tokenization
func (t *TokenCounterImpl) EstimateFromChars(charCount int) int {
    return int(float64(charCount) / t.avgCharsPerToken)
}

// GetTokenizer returns the tokenizer name
func (t *TokenCounterImpl) GetTokenizer() string {
    return t.tokenizer
}
```

---

## 22.4 Hierarchical Context Assembler

### Layer Definitions

```go
// ContextLayer represents a priority layer in context assembly
type ContextLayer int

const (
    LayerSystemPrompt   ContextLayer = 1 // Fixed, highest priority
    LayerCritical       ContextLayer = 2 // Project metadata, pinned artifacts
    LayerUserContent    ContextLayer = 3 // Query + instruction
    LayerRetrieved      ContextLayer = 4 // Dynamic RAG content
    LayerResponseBuffer ContextLayer = 5 // Reserved for output
)

// ContextBlock represents a block of content for a layer
type ContextBlock struct {
    Layer       ContextLayer `json:"layer"`
    Type        string       `json:"type"`        // "system" | "metadata" | "query" | "chunk" | etc
    Content     string       `json:"content"`
    TokenCount  int          `json:"tokenCount"`
    Priority    float64      `json:"priority"`    // 0.0-1.0, higher = keep first
    Source      string       `json:"source"`      // Origin identifier
    CanTruncate bool         `json:"canTruncate"` // Whether this block can be truncated
    MinTokens   int          `json:"minTokens"`   // Minimum tokens if truncated
}

// AssembledContext is the final assembled context ready for LLM
type AssembledContext struct {
    Messages       []ChatMessage   `json:"messages"`
    TotalTokens    int             `json:"totalTokens"`
    LayerBreakdown map[ContextLayer]int `json:"layerBreakdown"`
    Truncated      bool            `json:"truncated"`
    TruncationLog  []string        `json:"truncationLog"`
    SourceChunks   []string        `json:"sourceChunks"` // Chunk IDs included
}
```

---

### Context Assembler Implementation

```go
package services

import (
    "context"
    "fmt"
    "sort"
    "strings"
)

// ContextAssembler builds context within token limits
type ContextAssembler struct {
    config       ContextWindowConfig
    tokenCounter TokenCounter
}

// NewContextAssembler creates a new assembler
func NewContextAssembler(config ContextWindowConfig, counter TokenCounter) *ContextAssembler {
    return &ContextAssembler{
        config:       config,
        tokenCounter: counter,
    }
}

// AssembleRequest contains all inputs for context assembly
type AssembleRequest struct {
    SystemPrompt    string          `json:"systemPrompt"`
    ProjectMetadata string          `json:"projectMetadata"`
    PinnedArtifacts []ContextBlock  `json:"pinnedArtifacts"`
    UserQuery       string          `json:"userQuery"`
    Instruction     string          `json:"instruction"`
    RetrievedChunks []ContextBlock  `json:"retrievedChunks"`
    MemoryContext   string          `json:"memoryContext"` // Compressed previous turns
}

// Assemble builds the final context from request
func (a *ContextAssembler) Assemble(ctx context.Context, req AssembleRequest) (*AssembledContext, error) {
    result := &AssembledContext{
        Messages:       make([]ChatMessage, 0),
        LayerBreakdown: make(map[ContextLayer]int),
        SourceChunks:   make([]string, 0),
    }
    
    budget := &tokenBudget{
        total:     a.config.ModelContextSize,
        remaining: a.config.ModelContextSize - a.config.ResponseReserve - a.config.SafetyMargin,
    }
    
    // Layer 1: System Prompt (always included, never truncated)
    if err := a.addSystemPrompt(req.SystemPrompt, budget, result); err != nil {
        return nil, fmt.Errorf("system prompt exceeds budget: %w", err)
    }
    
    // Layer 2: Critical Context
    if err := a.addCriticalContext(req, budget, result); err != nil {
        return nil, fmt.Errorf("critical context assembly failed: %w", err)
    }
    
    // Layer 3: User Content
    if err := a.addUserContent(req, budget, result); err != nil {
        return nil, fmt.Errorf("user content assembly failed: %w", err)
    }
    
    // Layer 4: Retrieved Context (fill remaining budget)
    if err := a.addRetrievedContext(req.RetrievedChunks, budget, result); err != nil {
        return nil, fmt.Errorf("retrieved context assembly failed: %w", err)
    }
    
    // Calculate final totals
    result.TotalTokens = budget.total - budget.remaining - a.config.ResponseReserve - a.config.SafetyMargin
    
    return result, nil
}

type tokenBudget struct {
    total     int
    remaining int
}

func (b *tokenBudget) allocate(tokens int) bool {
    if tokens > b.remaining {
        return false
    }
    b.remaining -= tokens
    return true
}

// addSystemPrompt adds Layer 1 content
func (a *ContextAssembler) addSystemPrompt(prompt string, budget *tokenBudget, result *AssembledContext) error {
    tokens, err := a.tokenCounter.Count(prompt)
    if err != nil {
        return err
    }
    
    if tokens > a.config.SystemPromptReserve {
        return fmt.Errorf("system prompt (%d tokens) exceeds reserve (%d)", 
            tokens, a.config.SystemPromptReserve)
    }
    
    if !budget.allocate(tokens) {
        return fmt.Errorf("insufficient budget for system prompt")
    }
    
    result.Messages = append(result.Messages, ChatMessage{
        Role:    "system",
        Content: prompt,
    })
    result.LayerBreakdown[LayerSystemPrompt] = tokens
    
    return nil
}

// addCriticalContext adds Layer 2 content
func (a *ContextAssembler) addCriticalContext(req AssembleRequest, budget *tokenBudget, result *AssembledContext) error {
    var criticalContent strings.Builder
    criticalTokens := 0
    
    // Project metadata (always include)
    if req.ProjectMetadata != "" {
        tokens, _ := a.tokenCounter.Count(req.ProjectMetadata)
        if budget.allocate(tokens) {
            criticalContent.WriteString("## Project Context\n\n")
            criticalContent.WriteString(req.ProjectMetadata)
            criticalContent.WriteString("\n\n")
            criticalTokens += tokens
        }
    }
    
    // Memory context from previous turns
    if req.MemoryContext != "" {
        tokens, _ := a.tokenCounter.Count(req.MemoryContext)
        if budget.allocate(tokens) {
            criticalContent.WriteString("## Previous Context\n\n")
            criticalContent.WriteString(req.MemoryContext)
            criticalContent.WriteString("\n\n")
            criticalTokens += tokens
        }
    }
    
    // Pinned artifacts (highest priority)
    for _, artifact := range req.PinnedArtifacts {
        if budget.allocate(artifact.TokenCount) {
            criticalContent.WriteString(fmt.Sprintf("## Pinned: %s\n\n", artifact.Source))
            criticalContent.WriteString(artifact.Content)
            criticalContent.WriteString("\n\n")
            criticalTokens += artifact.TokenCount
            result.SourceChunks = append(result.SourceChunks, artifact.Source)
        } else {
            result.Truncated = true
            result.TruncationLog = append(result.TruncationLog, 
                fmt.Sprintf("Pinned artifact '%s' excluded (insufficient budget)", artifact.Source))
        }
    }
    
    if criticalContent.Len() > 0 {
        result.Messages = append(result.Messages, ChatMessage{
            Role:    "system",
            Content: criticalContent.String(),
        })
    }
    result.LayerBreakdown[LayerCritical] = criticalTokens
    
    return nil
}

// addUserContent adds Layer 3 content
func (a *ContextAssembler) addUserContent(req AssembleRequest, budget *tokenBudget, result *AssembledContext) error {
    var userContent strings.Builder
    userTokens := 0
    
    // User query
    if req.UserQuery != "" {
        queryTokens, _ := a.tokenCounter.Count(req.UserQuery)
        
        if queryTokens > a.config.UserQueryMaxTokens && a.config.AllowTruncation {
            // Truncate query
            truncated := a.truncateToTokens(req.UserQuery, a.config.UserQueryMaxTokens)
            queryTokens, _ = a.tokenCounter.Count(truncated)
            userContent.WriteString(truncated)
            result.Truncated = true
            result.TruncationLog = append(result.TruncationLog, "User query truncated")
        } else {
            userContent.WriteString(req.UserQuery)
        }
        
        budget.allocate(queryTokens)
        userTokens += queryTokens
    }
    
    // Instruction
    if req.Instruction != "" {
        userContent.WriteString("\n\n---\n\n## Instruction\n\n")
        
        instrTokens, _ := a.tokenCounter.Count(req.Instruction)
        
        if instrTokens > a.config.InstructionMaxTokens && a.config.AllowTruncation {
            truncated := a.truncateToTokens(req.Instruction, a.config.InstructionMaxTokens)
            instrTokens, _ = a.tokenCounter.Count(truncated)
            userContent.WriteString(truncated)
            result.Truncated = true
            result.TruncationLog = append(result.TruncationLog, "Instruction truncated")
        } else {
            userContent.WriteString(req.Instruction)
        }
        
        budget.allocate(instrTokens)
        userTokens += instrTokens
    }
    
    if userContent.Len() > 0 {
        result.Messages = append(result.Messages, ChatMessage{
            Role:    "user",
            Content: userContent.String(),
        })
    }
    result.LayerBreakdown[LayerUserContent] = userTokens
    
    return nil
}

// addRetrievedContext adds Layer 4 content (fill remaining budget)
func (a *ContextAssembler) addRetrievedContext(chunks []ContextBlock, budget *tokenBudget, result *AssembledContext) error {
    if len(chunks) == 0 {
        return nil
    }
    
    // Sort by priority (descending)
    sortedChunks := make([]ContextBlock, len(chunks))
    copy(sortedChunks, chunks)
    sort.Slice(sortedChunks, func(i, j int) bool {
        return sortedChunks[i].Priority > sortedChunks[j].Priority
    })
    
    var contextContent strings.Builder
    contextContent.WriteString("## Retrieved Context\n\n")
    contextTokens := 0
    includedCount := 0
    
    headerTokens, _ := a.tokenCounter.Count("## Retrieved Context\n\n")
    budget.allocate(headerTokens)
    
    for _, chunk := range sortedChunks {
        if chunk.TokenCount <= budget.remaining {
            // Full chunk fits
            contextContent.WriteString(fmt.Sprintf("### Source: %s\n\n", chunk.Source))
            contextContent.WriteString(chunk.Content)
            contextContent.WriteString("\n\n---\n\n")
            
            budget.allocate(chunk.TokenCount + 10) // +10 for headers/separators
            contextTokens += chunk.TokenCount
            includedCount++
            result.SourceChunks = append(result.SourceChunks, chunk.Source)
            
        } else if chunk.CanTruncate && chunk.MinTokens < budget.remaining {
            // Partial chunk with truncation
            truncated := a.truncateToTokens(chunk.Content, budget.remaining-10)
            truncatedTokens, _ := a.tokenCounter.Count(truncated)
            
            contextContent.WriteString(fmt.Sprintf("### Source: %s (truncated)\n\n", chunk.Source))
            contextContent.WriteString(truncated)
            contextContent.WriteString("\n\n---\n\n")
            
            budget.allocate(truncatedTokens + 10)
            contextTokens += truncatedTokens
            includedCount++
            result.SourceChunks = append(result.SourceChunks, chunk.Source)
            result.Truncated = true
            result.TruncationLog = append(result.TruncationLog, 
                fmt.Sprintf("Chunk '%s' truncated from %d to %d tokens", 
                    chunk.Source, chunk.TokenCount, truncatedTokens))
            
        } else {
            // Skip chunk
            result.TruncationLog = append(result.TruncationLog,
                fmt.Sprintf("Chunk '%s' excluded (%d tokens, %d remaining)", 
                    chunk.Source, chunk.TokenCount, budget.remaining))
        }
    }
    
    if includedCount > 0 {
        result.Messages = append(result.Messages, ChatMessage{
            Role:    "system",
            Content: contextContent.String(),
        })
    }
    result.LayerBreakdown[LayerRetrieved] = contextTokens
    
    return nil
}

// truncateToTokens truncates text to approximately target tokens
func (a *ContextAssembler) truncateToTokens(text string, targetTokens int) string {
    currentTokens, _ := a.tokenCounter.Count(text)
    if currentTokens <= targetTokens {
        return text
    }
    
    // Estimate character position
    ratio := float64(targetTokens) / float64(currentTokens)
    targetChars := int(float64(len(text)) * ratio)
    
    switch a.config.TruncationStrategy {
    case "head":
        // Keep end, truncate beginning
        if targetChars < len(text) {
            return "... " + text[len(text)-targetChars:]
        }
    case "middle":
        // Keep beginning and end, remove middle
        if targetChars < len(text) {
            half := targetChars / 2
            return text[:half] + "\n\n... [content truncated] ...\n\n" + text[len(text)-half:]
        }
    default: // "tail"
        // Keep beginning, truncate end
        if targetChars < len(text) {
            return text[:targetChars] + " ..."
        }
    }
    
    return text
}
```

---

## 22.5 Budget Allocation Engine

### Dynamic Budget Calculator

```go
// BudgetAllocation represents token allocation across layers
type BudgetAllocation struct {
    SystemPrompt    int `json:"systemPrompt"`
    CriticalContext int `json:"criticalContext"`
    UserContent     int `json:"userContent"`
    RetrievedContext int `json:"retrievedContext"`
    ResponseBuffer  int `json:"responseBuffer"`
    SafetyMargin    int `json:"safetyMargin"`
    TotalAllocated  int `json:"totalAllocated"`
    Remaining       int `json:"remaining"`
}

// BudgetAllocator calculates optimal token allocation
type BudgetAllocator struct {
    config       ContextWindowConfig
    tokenCounter TokenCounter
}

// NewBudgetAllocator creates a budget allocator
func NewBudgetAllocator(config ContextWindowConfig, counter TokenCounter) *BudgetAllocator {
    return &BudgetAllocator{
        config:       config,
        tokenCounter: counter,
    }
}

// CalculateBudget determines allocation based on actual content
func (b *BudgetAllocator) CalculateBudget(
    ctx context.Context,
    systemPrompt string,
    criticalContent string,
    userQuery string,
    instruction string,
) (*BudgetAllocation, error) {
    allocation := &BudgetAllocation{
        ResponseBuffer: b.config.ResponseReserve,
        SafetyMargin:   b.config.SafetyMargin,
    }
    
    // Count actual sizes
    systemTokens, _ := b.tokenCounter.Count(systemPrompt)
    criticalTokens, _ := b.tokenCounter.Count(criticalContent)
    queryTokens, _ := b.tokenCounter.Count(userQuery)
    instrTokens, _ := b.tokenCounter.Count(instruction)
    
    // Validate system prompt fits
    if systemTokens > b.config.SystemPromptReserve {
        return nil, fmt.Errorf("system prompt (%d) exceeds reserve (%d)", 
            systemTokens, b.config.SystemPromptReserve)
    }
    allocation.SystemPrompt = systemTokens
    
    // Allocate critical context (up to reserve)
    if criticalTokens > b.config.CriticalReserve {
        allocation.CriticalContext = b.config.CriticalReserve
    } else {
        allocation.CriticalContext = criticalTokens
    }
    
    // Allocate user content
    userContentTotal := queryTokens + instrTokens
    maxUserContent := b.config.UserQueryMaxTokens + b.config.InstructionMaxTokens
    if userContentTotal > maxUserContent {
        allocation.UserContent = maxUserContent
    } else {
        allocation.UserContent = userContentTotal
    }
    
    // Calculate remaining for retrieval
    allocated := allocation.SystemPrompt + 
                 allocation.CriticalContext + 
                 allocation.UserContent + 
                 allocation.ResponseBuffer + 
                 allocation.SafetyMargin
    
    allocation.RetrievedContext = b.config.ModelContextSize - allocated
    if allocation.RetrievedContext < 0 {
        allocation.RetrievedContext = 0
    }
    
    allocation.TotalAllocated = allocated + allocation.RetrievedContext
    allocation.Remaining = b.config.ModelContextSize - allocation.TotalAllocated
    
    return allocation, nil
}

// OptimizeBudget redistributes unused allocations
func (b *BudgetAllocator) OptimizeBudget(allocation *BudgetAllocation, actualUsage map[string]int) *BudgetAllocation {
    optimized := *allocation
    
    // Reclaim unused system prompt allocation
    if used, ok := actualUsage["systemPrompt"]; ok && used < allocation.SystemPrompt {
        reclaimed := allocation.SystemPrompt - used
        optimized.SystemPrompt = used
        optimized.RetrievedContext += reclaimed
    }
    
    // Reclaim unused critical context
    if used, ok := actualUsage["criticalContext"]; ok && used < allocation.CriticalContext {
        reclaimed := allocation.CriticalContext - used
        optimized.CriticalContext = used
        optimized.RetrievedContext += reclaimed
    }
    
    // Reclaim unused user content
    if used, ok := actualUsage["userContent"]; ok && used < allocation.UserContent {
        reclaimed := allocation.UserContent - used
        optimized.UserContent = used
        optimized.RetrievedContext += reclaimed
    }
    
    return &optimized
}
```

---

## 22.6 Overflow Handling

### Overflow Strategies

```go
// OverflowStrategy defines how to handle context overflow
type OverflowStrategy string

const (
    OverflowTruncate    OverflowStrategy = "truncate"    // Trim content to fit
    OverflowPrioritize  OverflowStrategy = "prioritize"  // Keep high-priority only
    OverflowSummarize   OverflowStrategy = "summarize"   // Compress via summarization
    OverflowSegment     OverflowStrategy = "segment"     // Split into multiple turns
    OverflowReject      OverflowStrategy = "reject"      // Reject with error
)

// OverflowHandler manages context overflow scenarios
type OverflowHandler struct {
    config       ContextWindowConfig
    tokenCounter TokenCounter
    summarizer   ContentSummarizer // Optional, for summarize strategy
}

// OverflowResult describes what happened during overflow handling
type OverflowResult struct {
    Strategy        OverflowStrategy `json:"strategy"`
    OriginalTokens  int              `json:"originalTokens"`
    FinalTokens     int              `json:"finalTokens"`
    ItemsRemoved    int              `json:"itemsRemoved"`
    ItemsTruncated  int              `json:"itemsTruncated"`
    SegmentsCreated int              `json:"segmentsCreated"`
    Warnings        []string         `json:"warnings"`
}

// HandleOverflow processes context that exceeds limits
func (h *OverflowHandler) HandleOverflow(
    ctx context.Context,
    blocks []ContextBlock,
    availableTokens int,
    strategy OverflowStrategy,
) ([]ContextBlock, *OverflowResult, error) {
    result := &OverflowResult{
        Strategy:       strategy,
        OriginalTokens: h.totalTokens(blocks),
    }
    
    if result.OriginalTokens <= availableTokens {
        result.FinalTokens = result.OriginalTokens
        return blocks, result, nil
    }
    
    switch strategy {
    case OverflowTruncate:
        return h.handleTruncate(blocks, availableTokens, result)
    case OverflowPrioritize:
        return h.handlePrioritize(blocks, availableTokens, result)
    case OverflowSummarize:
        return h.handleSummarize(ctx, blocks, availableTokens, result)
    case OverflowSegment:
        return h.handleSegment(blocks, availableTokens, result)
    case OverflowReject:
        return nil, result, fmt.Errorf("context overflow: %d tokens exceeds limit of %d",
            result.OriginalTokens, availableTokens)
    default:
        return h.handlePrioritize(blocks, availableTokens, result)
    }
}

func (h *OverflowHandler) totalTokens(blocks []ContextBlock) int {
    total := 0
    for _, b := range blocks {
        total += b.TokenCount
    }
    return total
}

// handleTruncate trims blocks to fit
func (h *OverflowHandler) handleTruncate(
    blocks []ContextBlock,
    availableTokens int,
    result *OverflowResult,
) ([]ContextBlock, *OverflowResult, error) {
    output := make([]ContextBlock, 0, len(blocks))
    remaining := availableTokens
    
    for _, block := range blocks {
        if block.TokenCount <= remaining {
            output = append(output, block)
            remaining -= block.TokenCount
        } else if block.CanTruncate && block.MinTokens <= remaining {
            // Truncate this block
            ratio := float64(remaining) / float64(block.TokenCount)
            targetChars := int(float64(len(block.Content)) * ratio)
            
            truncated := block
            truncated.Content = block.Content[:targetChars] + "..."
            truncated.TokenCount = remaining
            output = append(output, truncated)
            result.ItemsTruncated++
            remaining = 0
            break
        } else {
            result.ItemsRemoved++
        }
    }
    
    result.FinalTokens = availableTokens - remaining
    return output, result, nil
}

// handlePrioritize keeps only highest priority blocks
func (h *OverflowHandler) handlePrioritize(
    blocks []ContextBlock,
    availableTokens int,
    result *OverflowResult,
) ([]ContextBlock, *OverflowResult, error) {
    // Sort by priority descending
    sorted := make([]ContextBlock, len(blocks))
    copy(sorted, blocks)
    sort.Slice(sorted, func(i, j int) bool {
        return sorted[i].Priority > sorted[j].Priority
    })
    
    output := make([]ContextBlock, 0)
    remaining := availableTokens
    
    for _, block := range sorted {
        if block.TokenCount <= remaining {
            output = append(output, block)
            remaining -= block.TokenCount
        } else {
            result.ItemsRemoved++
        }
    }
    
    result.FinalTokens = availableTokens - remaining
    return output, result, nil
}

// handleSummarize compresses content via LLM summarization
func (h *OverflowHandler) handleSummarize(
    ctx context.Context,
    blocks []ContextBlock,
    availableTokens int,
    result *OverflowResult,
) ([]ContextBlock, *OverflowResult, error) {
    if h.summarizer == nil {
        // Fall back to prioritize
        result.Warnings = append(result.Warnings, "Summarizer not available, using prioritize")
        return h.handlePrioritize(blocks, availableTokens, result)
    }
    
    // Group blocks by type for batch summarization
    summarizable := make([]ContextBlock, 0)
    preserved := make([]ContextBlock, 0)
    
    for _, block := range blocks {
        if block.CanTruncate {
            summarizable = append(summarizable, block)
        } else {
            preserved = append(preserved, block)
        }
    }
    
    preservedTokens := h.totalTokens(preserved)
    targetSummarizedTokens := availableTokens - preservedTokens
    
    if targetSummarizedTokens <= 0 {
        // Not enough room even for preserved content
        return h.handlePrioritize(preserved, availableTokens, result)
    }
    
    // Summarize
    summarized, err := h.summarizer.Summarize(ctx, summarizable, targetSummarizedTokens)
    if err != nil {
        result.Warnings = append(result.Warnings, "Summarization failed: "+err.Error())
        return h.handlePrioritize(blocks, availableTokens, result)
    }
    
    output := append(preserved, summarized...)
    result.FinalTokens = h.totalTokens(output)
    
    return output, result, nil
}

// handleSegment creates multiple execution segments
func (h *OverflowHandler) handleSegment(
    blocks []ContextBlock,
    availableTokens int,
    result *OverflowResult,
) ([]ContextBlock, *OverflowResult, error) {
    // Return first segment, mark remaining for later execution
    output := make([]ContextBlock, 0)
    remaining := availableTokens
    segmentCount := 1
    
    for _, block := range blocks {
        if block.TokenCount <= remaining {
            output = append(output, block)
            remaining -= block.TokenCount
        } else {
            // Start new segment
            segmentCount++
        }
    }
    
    result.SegmentsCreated = segmentCount
    result.FinalTokens = availableTokens - remaining
    result.Warnings = append(result.Warnings, 
        fmt.Sprintf("Content split into %d segments", segmentCount))
    
    return output, result, nil
}

// ContentSummarizer interface for LLM-based summarization
type ContentSummarizer interface {
    Summarize(ctx context.Context, blocks []ContextBlock, targetTokens int) ([]ContextBlock, error)
}
```

---

## 22.7 Context Window Manager Service

### Main Service Interface

```go
// ContextWindowManager is the main service interface
type ContextWindowManager interface {
    // Configuration
    GetConfig() ContextWindowConfig
    SetConfig(config ContextWindowConfig) error
    GetPresetForModel(modelName string) ContextWindowConfig
    
    // Token counting
    CountTokens(text string) (int, error)
    CountMessages(messages []ChatMessage) (int, error)
    EstimateTokens(charCount int) int
    
    // Budget allocation
    CalculateBudget(ctx context.Context, req AssembleRequest) (*BudgetAllocation, error)
    
    // Context assembly
    Assemble(ctx context.Context, req AssembleRequest) (*AssembledContext, error)
    
    // Overflow handling
    HandleOverflow(ctx context.Context, blocks []ContextBlock, limit int, strategy OverflowStrategy) ([]ContextBlock, *OverflowResult, error)
    
    // Validation
    ValidateContext(assembled *AssembledContext) error
    WillExceedLimit(tokens int) bool
}

// ContextWindowManagerImpl implements ContextWindowManager
type ContextWindowManagerImpl struct {
    config          ContextWindowConfig
    tokenCounter    TokenCounter
    assembler       *ContextAssembler
    budgetAllocator *BudgetAllocator
    overflowHandler *OverflowHandler
}

// NewContextWindowManager creates the manager
func NewContextWindowManager(config ContextWindowConfig) (*ContextWindowManagerImpl, error) {
    if err := config.Validate(); err != nil {
        return nil, fmt.Errorf("invalid config: %w", err)
    }
    
    tokenCounter := NewTokenCounter("llama")
    
    return &ContextWindowManagerImpl{
        config:          config,
        tokenCounter:    tokenCounter,
        assembler:       NewContextAssembler(config, tokenCounter),
        budgetAllocator: NewBudgetAllocator(config, tokenCounter),
        overflowHandler: &OverflowHandler{config: config, tokenCounter: tokenCounter},
    }, nil
}

// GetConfig returns current configuration
func (m *ContextWindowManagerImpl) GetConfig() ContextWindowConfig {
    return m.config
}

// SetConfig updates configuration
func (m *ContextWindowManagerImpl) SetConfig(config ContextWindowConfig) error {
    if err := config.Validate(); err != nil {
        return err
    }
    m.config = config
    m.assembler = NewContextAssembler(config, m.tokenCounter)
    m.budgetAllocator = NewBudgetAllocator(config, m.tokenCounter)
    return nil
}

// GetPresetForModel returns model-specific config
func (m *ContextWindowManagerImpl) GetPresetForModel(modelName string) ContextWindowConfig {
    return GetPresetForModel(modelName)
}

// CountTokens returns token count for text
func (m *ContextWindowManagerImpl) CountTokens(text string) (int, error) {
    return m.tokenCounter.Count(text)
}

// CountMessages returns token count for message array
func (m *ContextWindowManagerImpl) CountMessages(messages []ChatMessage) (int, error) {
    return m.tokenCounter.CountMessages(messages)
}

// EstimateTokens provides fast approximation
func (m *ContextWindowManagerImpl) EstimateTokens(charCount int) int {
    return m.tokenCounter.EstimateFromChars(charCount)
}

// CalculateBudget determines optimal allocation
func (m *ContextWindowManagerImpl) CalculateBudget(ctx context.Context, req AssembleRequest) (*BudgetAllocation, error) {
    criticalContent := req.ProjectMetadata + req.MemoryContext
    for _, artifact := range req.PinnedArtifacts {
        criticalContent += artifact.Content
    }
    
    return m.budgetAllocator.CalculateBudget(
        ctx,
        req.SystemPrompt,
        criticalContent,
        req.UserQuery,
        req.Instruction,
    )
}

// Assemble builds complete context
func (m *ContextWindowManagerImpl) Assemble(ctx context.Context, req AssembleRequest) (*AssembledContext, error) {
    return m.assembler.Assemble(ctx, req)
}

// HandleOverflow manages context overflow
func (m *ContextWindowManagerImpl) HandleOverflow(
    ctx context.Context,
    blocks []ContextBlock,
    limit int,
    strategy OverflowStrategy,
) ([]ContextBlock, *OverflowResult, error) {
    return m.overflowHandler.HandleOverflow(ctx, blocks, limit, strategy)
}

// ValidateContext checks assembled context
func (m *ContextWindowManagerImpl) ValidateContext(assembled *AssembledContext) error {
    maxAllowed := m.config.ModelContextSize - m.config.ResponseReserve
    
    if assembled.TotalTokens > maxAllowed {
        return fmt.Errorf("assembled context (%d tokens) exceeds limit (%d)",
            assembled.TotalTokens, maxAllowed)
    }
    
    if len(assembled.Messages) == 0 {
        return fmt.Errorf("assembled context has no messages")
    }
    
    return nil
}

// WillExceedLimit checks if token count would overflow
func (m *ContextWindowManagerImpl) WillExceedLimit(tokens int) bool {
    return tokens > (m.config.ModelContextSize - m.config.ResponseReserve - m.config.SafetyMargin)
}
```

---

## 22.8 Configuration Keys

Add to `09-seeding-configuration.md`:

```json
{
    "Key": "context.defaultModel",
    "Value": "llama-3-8b",
    "Description": "Default model for context window sizing"
},
{
    "Key": "context.systemPromptReserve",
    "Value": "500",
    "Description": "Reserved tokens for system prompt"
},
{
    "Key": "context.criticalReserve",
    "Value": "1000",
    "Description": "Reserved tokens for critical context (metadata, pinned)"
},
{
    "Key": "context.responseReserve",
    "Value": "1500",
    "Description": "Reserved tokens for response output"
},
{
    "Key": "context.safetyMargin",
    "Value": "200",
    "Description": "Safety buffer for tokenization variance"
},
{
    "Key": "context.userQueryMaxTokens",
    "Value": "500",
    "Description": "Maximum tokens for user query"
},
{
    "Key": "context.instructionMaxTokens",
    "Value": "1000",
    "Description": "Maximum tokens for instruction content"
},
{
    "Key": "context.allowTruncation",
    "Value": "true",
    "Description": "Allow content truncation on overflow"
},
{
    "Key": "context.truncationStrategy",
    "Value": "tail",
    "Description": "Truncation strategy: tail | head | middle"
},
{
    "Key": "context.overflowStrategy",
    "Value": "prioritize",
    "Description": "Overflow strategy: truncate | prioritize | summarize | segment | reject"
}
```

---

## 22.9 Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 5060 | ERR_CONTEXT_OVERFLOW | Context exceeds model limit |
| 5061 | ERR_BUDGET_EXCEEDED | Token budget allocation exceeded |
| 5062 | ERR_SYSTEM_PROMPT_TOO_LARGE | System prompt exceeds reserve |
| 5063 | ERR_INVALID_CONFIG | Context window configuration invalid |
| 5064 | ERR_TOKEN_COUNT_FAILED | Token counting operation failed |
| 5065 | ERR_ASSEMBLY_FAILED | Context assembly failed |
| 5066 | ERR_TRUNCATION_FAILED | Content truncation failed |

---

## 22.10 Unit Test Requirements

| Test Case | Priority |
|-----------|----------|
| DefaultContextWindowConfig valid | HIGH |
| ContextWindowConfig.Validate catches errors | HIGH |
| TokenCounter.Count returns accurate counts | HIGH |
| TokenCounter handles empty/unicode strings | HIGH |
| Assembler respects layer priorities | HIGH |
| Assembler enforces budget limits | HIGH |
| BudgetAllocator calculates correct allocation | HIGH |
| OverflowHandler.Truncate works correctly | MEDIUM |
| OverflowHandler.Prioritize sorts by priority | MEDIUM |
| Model presets return valid configs | MEDIUM |
| WillExceedLimit accurate prediction | LOW |

---

## 22.11 Acceptance Criteria

### Configuration (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CF-001 | ContextWindowConfig.Validate() rejects invalid config | Critical | Validation test |
| CF-002 | Fixed reservations cannot exceed model context | Critical | Reservation limit test |
| CF-003 | Priority weights must sum to 1.0 | High | Weight validation test |
| CF-004 | ResponseReserve minimum 100 tokens | High | Minimum reserve test |
| CF-005 | AvailableForRetrieval() calculates correctly | High | Calculation test |

### Model Presets (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MP-001 | llama-3-8b preset: 8192 context | Critical | Preset test |
| MP-002 | llama-3-70b preset: 8192 context | High | Preset test |
| MP-003 | gemini-pro preset: 32768 context | High | Preset test |
| MP-004 | gemini-flash preset: 1000000 context | High | Preset test |
| MP-005 | GetPresetForModel returns default for unknown | Medium | Fallback test |

### Token Counting (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| TC-001 | Count() returns token count for text | Critical | Count test |
| TC-002 | Count() accurate within 10% of actual | High | Accuracy test |
| TC-003 | CountBatch() counts multiple texts efficiently | High | Batch test |
| TC-004 | CountMessages() includes format overhead | High | Message overhead test |
| TC-005 | EstimateFromChars() provides fast approximation | Medium | Estimation test |
| TC-006 | Empty string returns 0 tokens | Medium | Empty input test |
| TC-007 | Unicode strings handled correctly | Medium | Unicode test |

### Hierarchical Assembly (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| HA-001 | Layer 1 (system prompt) always included | Critical | Layer priority test |
| HA-002 | Layer 2 (critical) included before Layer 4 | Critical | Priority order test |
| HA-003 | Layer 3 (user content) respects max tokens | High | User content limit test |
| HA-004 | Layer 4 (retrieved) fills remaining budget | High | Dynamic allocation test |
| HA-005 | Layer 5 (response) always reserved | Critical | Response reserve test |
| HA-006 | ContextBlocks assembled in priority order | High | Assembly order test |

### Budget Allocation (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| BA-001 | Total tokens never exceed ModelContextSize | Critical | Budget limit test |
| BA-002 | CalculateBudget returns per-layer allocation | High | Budget calculation test |
| BA-003 | WillExceedLimit predicts overflow correctly | High | Prediction test |
| BA-004 | SafetyMargin reserved for tokenization variance | Medium | Safety margin test |

### Overflow Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| OH-001 | Truncation strategies: tail, head, middle work | Critical | Truncation test |
| OH-002 | Highest-priority content preserved on overflow | Critical | Priority preservation test |
| OH-003 | CanTruncate=false blocks prevent truncation | High | Block protection test |
| OH-004 | MinTokens respected during truncation | High | Min tokens test |
| OH-005 | TruncationLog records what was removed | Medium | Audit log test |
| OH-006 | Warning logged when EnableOverflowWarning=true | Medium | Warning log test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | ERR_CONTEXT_OVERFLOW (5060) for exceeded limit | Critical | Error code test |
| EH-002 | ERR_BUDGET_EXCEEDED (5061) for allocation exceeded | Critical | Error code test |
| EH-003 | ERR_SYSTEM_PROMPT_TOO_LARGE (5062) for oversized system | High | Error code test |
| EH-004 | ERR_INVALID_CONFIG (5063) for bad configuration | High | Error code test |
| EH-005 | All errors include context size and limit | High | Error context test |

---

## Related Specifications

- [Vector Database Plan](./04-vector-database-plan.md)
- [Vector Search Service](./05-vector-search-service.md)
- [RAG System](./01-rag-system.md)
- [AI Integration](../06-ai-integration/01-ai-integration.md)
- [Instruction System](../06-ai-integration/03-instruction-system.md)
