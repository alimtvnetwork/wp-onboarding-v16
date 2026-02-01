# Memory Compression System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This specification defines the Memory Compression system for managing context continuity across multi-turn instruction execution. The system uses LLM-based summarization to compress execution outputs into compact memory entries, preserving key decisions, artifacts, and open questions while dramatically reducing token usage.

**Cross-References:**
- [Vector Database Plan](./04-vector-database-plan.md) - Overall enhancement strategy (§20.3.3)
- [Instruction Segmentation](../06-ai-integration/05-instruction-segmentation.md) - Multi-turn execution
- [Context Window Manager](./06-context-window-manager.md) - Token budgeting
- [Database Schema](../../07-database-design/01-schema.md) - MemoryEntry model
- [AI Integration](../06-ai-integration/01-ai-integration.md) - LLM invocation
- [Prompt Presets](../06-ai-integration/02-presets-guidelines.md) - Prompt management

---

## 24.1 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      MEMORY COMPRESSION ARCHITECTURE                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐     │
│  │                     MemoryCompressionService                         │     │
│  ├─────────────────────────────────────────────────────────────────────┤     │
│  │                                                                       │     │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │     │
│  │  │ Summarization│  │   Memory     │  │  Multi-Turn  │               │     │
│  │  │   Prompter   │  │   Store      │  │  Integrator  │               │     │
│  │  └──────────────┘  └──────────────┘  └──────────────┘               │     │
│  │         │                 │                 │                        │     │
│  │         └─────────────────┼─────────────────┘                        │     │
│  │                           ▼                                          │     │
│  │  ┌──────────────────────────────────────────────────────────────┐   │     │
│  │  │                   Compression Pipeline                        │   │     │
│  │  ├──────────────────────────────────────────────────────────────┤   │     │
│  │  │  Extract → Summarize → Validate → Store → Retrieve → Inject  │   │     │
│  │  └──────────────────────────────────────────────────────────────┘   │     │
│  │                                                                       │     │
│  └─────────────────────────────────────────────────────────────────────┘     │
│                                    │                                          │
│                    ┌───────────────┼───────────────┐                          │
│                    ▼               ▼               ▼                          │
│  ┌─────────────────────┐ ┌─────────────────┐ ┌─────────────────────────┐     │
│  │   AI Service        │ │   Memory        │ │  Instruction            │     │
│  │   (Summarization)   │ │   Repository    │ │  Segmentation           │     │
│  └─────────────────────┘ └─────────────────┘ └─────────────────────────┘     │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 24.2 Summarization Prompts

### Prompt Templates

```go
package services

// SummarizationPromptType defines the type of summarization
type SummarizationPromptType string

const (
    PromptTypeExecution    SummarizationPromptType = "execution"    // Segment execution output
    PromptTypeConversation SummarizationPromptType = "conversation" // Chat history
    PromptTypeArtifact     SummarizationPromptType = "artifact"     // Document/code summarization
    PromptTypeIncremental  SummarizationPromptType = "incremental"  // Add to existing summary
)

// SummarizationPrompt holds a summarization prompt template
type SummarizationPrompt struct {
    Type        SummarizationPromptType `json:"type"`
    Name        string                   `json:"name"`
    Template    string                   `json:"template"`
    MaxTokens   int                      `json:"maxTokens"`   // Target output tokens
    Preserves   []string                 `json:"preserves"`   // What to preserve
    Description string                   `json:"description"`
}

// DefaultSummarizationPrompts provides standard prompt templates
var DefaultSummarizationPrompts = map[SummarizationPromptType]SummarizationPrompt{
    PromptTypeExecution: {
        Type: PromptTypeExecution,
        Name: "Execution Output Summarization",
        Template: `Summarize the following execution output for use as context in the next execution turn.

## Requirements
You MUST preserve:
1. **Key Decisions**: Important choices made during execution
2. **Artifacts Created**: Files, models, services, or components created
3. **Dependencies Established**: Relationships between components
4. **Configuration Changes**: Settings or constants defined
5. **Open Questions**: Unresolved issues or pending decisions
6. **Error Context**: Any errors encountered and how they were handled

## Format
Provide a structured summary using this format:

### Decisions Made
- [List key decisions with brief rationale]

### Artifacts Created
- [List files/components with one-line descriptions]

### Dependencies
- [List component relationships]

### Pending Items
- [List open questions or next steps]

## Constraints
- Maximum length: {{.MaxTokens}} tokens
- Be concise but preserve critical context
- Use technical terminology accurately
- Do not include code snippets unless critical

## Content to Summarize
{{.Content}}`,
        MaxTokens:   500,
        Preserves:   []string{"decisions", "artifacts", "dependencies", "pending"},
        Description: "Compresses execution output for next segment context",
    },
    
    PromptTypeConversation: {
        Type: PromptTypeConversation,
        Name: "Conversation History Summarization",
        Template: `Summarize the following conversation history to preserve context for continued interaction.

## Requirements
Preserve:
1. **User Intent**: What the user is trying to achieve
2. **Agreed Decisions**: Choices confirmed by the user
3. **Rejected Options**: Alternatives that were considered and rejected
4. **Current State**: Where we are in the process
5. **Preferences**: User preferences or constraints mentioned

## Format
### User Goal
[One sentence describing the overall objective]

### Decisions Made
- [List of confirmed decisions]

### Rejected Alternatives
- [Options that were discussed but not chosen]

### Current Status
[Brief description of current state]

### User Preferences
- [Any stated preferences or constraints]

## Constraints
- Maximum length: {{.MaxTokens}} tokens
- Preserve user's voice and intent
- Focus on actionable information

## Conversation
{{.Content}}`,
        MaxTokens:   400,
        Preserves:   []string{"intent", "decisions", "rejected", "state", "preferences"},
        Description: "Compresses conversation history for continued chat",
    },
    
    PromptTypeArtifact: {
        Type: PromptTypeArtifact,
        Name: "Artifact Content Summarization",
        Template: `Summarize the following document/code artifact for quick reference.

## Requirements
Extract:
1. **Purpose**: What this artifact does/defines
2. **Key Components**: Main elements or sections
3. **Dependencies**: What it depends on or provides
4. **API Surface**: Public interfaces if applicable
5. **Notable Patterns**: Design patterns or conventions used

## Format
### Purpose
[One paragraph describing the artifact's role]

### Key Components
- [List main components with brief descriptions]

### Dependencies
- **Requires**: [What this artifact needs]
- **Provides**: [What this artifact exposes]

### Usage Notes
[Any important usage considerations]

## Constraints
- Maximum length: {{.MaxTokens}} tokens
- Focus on interface over implementation
- Preserve technical accuracy

## Artifact Content
{{.Content}}`,
        MaxTokens:   300,
        Preserves:   []string{"purpose", "components", "dependencies", "api"},
        Description: "Compresses artifact content for RAG context",
    },
    
    PromptTypeIncremental: {
        Type: PromptTypeIncremental,
        Name: "Incremental Summary Update",
        Template: `Update the existing summary with new information from the latest turn.

## Existing Summary
{{.ExistingSummary}}

## New Information
{{.NewContent}}

## Requirements
1. Merge new information into existing summary
2. Update any decisions that have changed
3. Add new artifacts to the list
4. Update pending items (remove completed, add new)
5. Maintain chronological context

## Format
Use the same format as the existing summary, updating sections as needed.

## Constraints
- Maximum length: {{.MaxTokens}} tokens
- Do not repeat information already in summary
- Mark any contradictions or changes explicitly
- Preserve all critical context from both sources`,
        MaxTokens:   600,
        Preserves:   []string{"decisions", "artifacts", "pending", "changes"},
        Description: "Incrementally updates existing summary with new turn",
    },
}
```

---

### Prompt Manager

```go
// SummarizationPrompter manages summarization prompts
type SummarizationPrompter struct {
    prompts      map[SummarizationPromptType]SummarizationPrompt
    tokenCounter TokenCounter
}

// NewSummarizationPrompter creates a new prompter
func NewSummarizationPrompter(tokenCounter TokenCounter) *SummarizationPrompter {
    return &SummarizationPrompter{
        prompts:      DefaultSummarizationPrompts,
        tokenCounter: tokenCounter,
    }
}

// SummarizationRequest holds parameters for summarization
type SummarizationRequest struct {
    Type            SummarizationPromptType `json:"type"`
    Content         string                   `json:"content"`
    MaxTokens       int                      `json:"maxTokens"`       // Override default
    ExistingSummary string                   `json:"existingSummary"` // For incremental
    Context         map[string]string        `json:"context"`         // Additional context
}

// BuildPrompt constructs the summarization prompt
func (p *SummarizationPrompter) BuildPrompt(req SummarizationRequest) (string, error) {
    prompt, ok := p.prompts[req.Type]
    if !ok {
        return "", fmt.Errorf("unknown prompt type: %s", req.Type)
    }
    
    maxTokens := req.MaxTokens
    if maxTokens == 0 {
        maxTokens = prompt.MaxTokens
    }
    
    // Build template context
    data := map[string]interface{}{
        "Content":         req.Content,
        "MaxTokens":       maxTokens,
        "ExistingSummary": req.ExistingSummary,
    }
    
    // Add custom context
    for k, v := range req.Context {
        data[k] = v
    }
    
    // Execute template
    tmpl, err := template.New("prompt").Parse(prompt.Template)
    if err != nil {
        return "", fmt.Errorf("template parse error: %w", err)
    }
    
    var buf bytes.Buffer
    if err := tmpl.Execute(&buf, data); err != nil {
        return "", fmt.Errorf("template execution error: %w", err)
    }
    
    return buf.String(), nil
}

// GetPrompt returns a prompt by type
func (p *SummarizationPrompter) GetPrompt(promptType SummarizationPromptType) (SummarizationPrompt, bool) {
    prompt, ok := p.prompts[promptType]
    return prompt, ok
}

// RegisterPrompt adds or updates a prompt
func (p *SummarizationPrompter) RegisterPrompt(prompt SummarizationPrompt) {
    p.prompts[prompt.Type] = prompt
}

// EstimateInputTokens estimates tokens for content
func (p *SummarizationPrompter) EstimateInputTokens(content string) (int, error) {
    return p.tokenCounter.Count(content)
}
```

---

## 24.3 Memory Compression Service

### Core Service Implementation

```go
// CompressionResult holds the result of compression
type CompressionResult struct {
    OriginalTokens   int      `json:"originalTokens"`
    CompressedTokens int      `json:"compressedTokens"`
    CompressionRatio float64  `json:"compressionRatio"`
    Summary          string   `json:"summary"`
    KeyDecisions     []string `json:"keyDecisions"`
    ArtifactsCreated []string `json:"artifactsCreated"`
    OpenQuestions    []string `json:"openQuestions"`
    ProcessingTimeMs int64    `json:"processingTimeMs"`
}

// MemoryCompressionConfig holds service configuration
type MemoryCompressionConfig struct {
    DefaultMaxTokens      int     `json:"defaultMaxTokens"`      // Default target tokens
    MinCompressionRatio   float64 `json:"minCompressionRatio"`   // Minimum 50% reduction
    MaxRetries            int     `json:"maxRetries"`            // Retry on failure
    EnableValidation      bool    `json:"enableValidation"`      // Validate output quality
    ExtractStructuredData bool    `json:"extractStructuredData"` // Parse decisions/artifacts
    CacheEnabled          bool    `json:"cacheEnabled"`          // Cache identical inputs
    CacheTTLMinutes       int     `json:"cacheTTLMinutes"`       // Cache expiry
}

// DefaultMemoryCompressionConfig returns sensible defaults
func DefaultMemoryCompressionConfig() MemoryCompressionConfig {
    return MemoryCompressionConfig{
        DefaultMaxTokens:      500,
        MinCompressionRatio:   0.5,
        MaxRetries:            2,
        EnableValidation:      true,
        ExtractStructuredData: true,
        CacheEnabled:          true,
        CacheTTLMinutes:       30,
    }
}

// MemoryCompressionService handles memory compression
type MemoryCompressionService struct {
    prompter     *SummarizationPrompter
    aiService    AIService
    tokenCounter TokenCounter
    db           *gorm.DB
    config       MemoryCompressionConfig
    cache        sync.Map
}

// NewMemoryCompressionService creates a new service
func NewMemoryCompressionService(
    aiService AIService,
    tokenCounter TokenCounter,
    db *gorm.DB,
    config MemoryCompressionConfig,
) *MemoryCompressionService {
    return &MemoryCompressionService{
        prompter:     NewSummarizationPrompter(tokenCounter),
        aiService:    aiService,
        tokenCounter: tokenCounter,
        db:           db,
        config:       config,
    }
}

// Compress compresses content to target token count
func (s *MemoryCompressionService) Compress(
    ctx context.Context,
    content string,
    targetTokens int,
) (string, error) {
    result, err := s.CompressWithDetails(ctx, content, targetTokens, PromptTypeExecution)
    if err != nil {
        return "", err
    }
    return result.Summary, nil
}

// CompressWithDetails returns full compression result
func (s *MemoryCompressionService) CompressWithDetails(
    ctx context.Context,
    content string,
    targetTokens int,
    promptType SummarizationPromptType,
) (*CompressionResult, error) {
    startTime := time.Now()
    
    if targetTokens == 0 {
        targetTokens = s.config.DefaultMaxTokens
    }
    
    // Check cache
    cacheKey := s.computeCacheKey(content, targetTokens, promptType)
    if s.config.CacheEnabled {
        if cached, ok := s.cache.Load(cacheKey); ok {
            return cached.(*CompressionResult), nil
        }
    }
    
    // Count original tokens
    originalTokens, err := s.tokenCounter.Count(content)
    if err != nil {
        return nil, fmt.Errorf("token counting failed: %w", err)
    }
    
    // If already under target, return as-is
    if originalTokens <= targetTokens {
        return &CompressionResult{
            OriginalTokens:   originalTokens,
            CompressedTokens: originalTokens,
            CompressionRatio: 0,
            Summary:          content,
            ProcessingTimeMs: time.Since(startTime).Milliseconds(),
        }, nil
    }
    
    // Build summarization prompt
    prompt, err := s.prompter.BuildPrompt(SummarizationRequest{
        Type:      promptType,
        Content:   content,
        MaxTokens: targetTokens,
    })
    if err != nil {
        return nil, fmt.Errorf("prompt building failed: %w", err)
    }
    
    // Execute summarization with retries
    var summary string
    var lastErr error
    
    for attempt := 1; attempt <= s.config.MaxRetries; attempt++ {
        summary, lastErr = s.aiService.Generate(ctx, []ChatMessage{
            {Role: "system", Content: "You are a precise summarization assistant. Follow the instructions exactly."},
            {Role: "user", Content: prompt},
        })
        
        if lastErr == nil {
            break
        }
        
        if attempt < s.config.MaxRetries {
            time.Sleep(time.Duration(attempt) * time.Second)
        }
    }
    
    if lastErr != nil {
        return nil, fmt.Errorf("summarization failed after %d attempts: %w", s.config.MaxRetries, lastErr)
    }
    
    // Count compressed tokens
    compressedTokens, _ := s.tokenCounter.Count(summary)
    
    result := &CompressionResult{
        OriginalTokens:   originalTokens,
        CompressedTokens: compressedTokens,
        CompressionRatio: 1.0 - (float64(compressedTokens) / float64(originalTokens)),
        Summary:          summary,
        ProcessingTimeMs: time.Since(startTime).Milliseconds(),
    }
    
    // Validate compression
    if s.config.EnableValidation {
        if err := s.validateCompression(result, targetTokens); err != nil {
            // Try once more with stricter prompt
            return s.recompress(ctx, content, targetTokens, promptType, result)
        }
    }
    
    // Extract structured data
    if s.config.ExtractStructuredData {
        s.extractStructuredData(result)
    }
    
    // Cache result
    if s.config.CacheEnabled {
        s.cache.Store(cacheKey, result)
    }
    
    return result, nil
}

// validateCompression checks if compression meets requirements
func (s *MemoryCompressionService) validateCompression(result *CompressionResult, targetTokens int) error {
    if result.CompressedTokens > targetTokens {
        return fmt.Errorf("compressed tokens (%d) exceeds target (%d)", 
            result.CompressedTokens, targetTokens)
    }
    
    if result.CompressionRatio < s.config.MinCompressionRatio {
        return fmt.Errorf("compression ratio (%.2f) below minimum (%.2f)", 
            result.CompressionRatio, s.config.MinCompressionRatio)
    }
    
    if len(result.Summary) < 50 {
        return fmt.Errorf("summary too short (%d chars)", len(result.Summary))
    }
    
    return nil
}

// recompress attempts compression with stricter parameters
func (s *MemoryCompressionService) recompress(
    ctx context.Context,
    content string,
    targetTokens int,
    promptType SummarizationPromptType,
    previousResult *CompressionResult,
) (*CompressionResult, error) {
    // Use half the target to ensure we hit it
    stricterTarget := targetTokens / 2
    if stricterTarget < 100 {
        stricterTarget = 100
    }
    
    prompt, _ := s.prompter.BuildPrompt(SummarizationRequest{
        Type:      promptType,
        Content:   content,
        MaxTokens: stricterTarget,
        Context: map[string]string{
            "PreviousAttempt": "true",
            "StrictMode":      "true",
        },
    })
    
    summary, err := s.aiService.Generate(ctx, []ChatMessage{
        {Role: "system", Content: "You are a precise summarization assistant. Be extremely concise. Follow the token limit strictly."},
        {Role: "user", Content: prompt},
    })
    
    if err != nil {
        // Return previous result if recompression fails
        return previousResult, nil
    }
    
    compressedTokens, _ := s.tokenCounter.Count(summary)
    
    return &CompressionResult{
        OriginalTokens:   previousResult.OriginalTokens,
        CompressedTokens: compressedTokens,
        CompressionRatio: 1.0 - (float64(compressedTokens) / float64(previousResult.OriginalTokens)),
        Summary:          summary,
        ProcessingTimeMs: previousResult.ProcessingTimeMs,
    }, nil
}

// extractStructuredData parses decisions and artifacts from summary
func (s *MemoryCompressionService) extractStructuredData(result *CompressionResult) {
    // Extract decisions
    decisionsPattern := regexp.MustCompile(`(?m)^[-*]\s+(.+)$`)
    if strings.Contains(strings.ToLower(result.Summary), "decision") {
        matches := decisionsPattern.FindAllStringSubmatch(result.Summary, -1)
        for _, match := range matches {
            if len(match) > 1 {
                result.KeyDecisions = append(result.KeyDecisions, strings.TrimSpace(match[1]))
            }
        }
    }
    
    // Extract artifacts (file paths)
    artifactPattern := regexp.MustCompile(`[\w/.-]+\.(go|ts|tsx|js|md|json|yaml|yml)`)
    matches := artifactPattern.FindAllString(result.Summary, -1)
    seen := make(map[string]bool)
    for _, match := range matches {
        if !seen[match] {
            result.ArtifactsCreated = append(result.ArtifactsCreated, match)
            seen[match] = true
        }
    }
    
    // Extract open questions
    questionPattern := regexp.MustCompile(`(?mi)(?:pending|todo|open|question|next)[:\s]+(.+)$`)
    matches2 := questionPattern.FindAllStringSubmatch(result.Summary, -1)
    for _, match := range matches2 {
        if len(match) > 1 {
            result.OpenQuestions = append(result.OpenQuestions, strings.TrimSpace(match[1]))
        }
    }
}

// computeCacheKey generates cache key for content
func (s *MemoryCompressionService) computeCacheKey(content string, targetTokens int, promptType SummarizationPromptType) string {
    hash := sha256.Sum256([]byte(content))
    return fmt.Sprintf("%x:%d:%s", hash[:8], targetTokens, promptType)
}
```

---

## 24.4 Memory Store

### Memory Entry Management

```go
// MemoryStore manages MemoryEntry persistence
type MemoryStore struct {
    db *gorm.DB
}

// NewMemoryStore creates a new memory store
func NewMemoryStore(db *gorm.DB) *MemoryStore {
    return &MemoryStore{db: db}
}

// SaveMemoryEntry persists a memory entry
func (s *MemoryStore) SaveMemoryEntry(ctx context.Context, entry *models.MemoryEntry) error {
    return s.db.WithContext(ctx).Create(entry).Error
}

// GetMemoryEntries retrieves all memory entries for an instruction
func (s *MemoryStore) GetMemoryEntries(
    ctx context.Context,
    instructionId string,
) ([]models.MemoryEntry, error) {
    var entries []models.MemoryEntry
    err := s.db.WithContext(ctx).
        Where("instruction_id = ?", instructionId).
        Order("turn_index ASC").
        Find(&entries).Error
    return entries, err
}

// GetLatestMemory retrieves the most recent memory entry
func (s *MemoryStore) GetLatestMemory(
    ctx context.Context,
    instructionId string,
) (*models.MemoryEntry, error) {
    var entry models.MemoryEntry
    err := s.db.WithContext(ctx).
        Where("instruction_id = ?", instructionId).
        Order("turn_index DESC").
        First(&entry).Error
    
    if err == gorm.ErrRecordNotFound {
        return nil, nil
    }
    return &entry, err
}

// GetMemoryForSession retrieves memory entries for a specific session
func (s *MemoryStore) GetMemoryForSession(
    ctx context.Context,
    sessionId string,
) ([]models.MemoryEntry, error) {
    var entries []models.MemoryEntry
    err := s.db.WithContext(ctx).
        Where("session_id = ?", sessionId).
        Order("turn_index ASC").
        Find(&entries).Error
    return entries, err
}

// GetCombinedMemory concatenates all memory summaries for an instruction
func (s *MemoryStore) GetCombinedMemory(
    ctx context.Context,
    instructionId string,
    maxTokens int,
    tokenCounter TokenCounter,
) (string, error) {
    entries, err := s.GetMemoryEntries(ctx, instructionId)
    if err != nil {
        return "", err
    }
    
    if len(entries) == 0 {
        return "", nil
    }
    
    var combined strings.Builder
    totalTokens := 0
    
    // Start from most recent, work backwards
    for i := len(entries) - 1; i >= 0; i-- {
        entry := entries[i]
        entryTokens, _ := tokenCounter.Count(entry.Summary)
        
        if totalTokens+entryTokens > maxTokens {
            break
        }
        
        // Prepend (so order is chronological)
        header := fmt.Sprintf("### Turn %d Memory\n\n", entry.TurnIndex)
        combined.WriteString(header)
        combined.WriteString(entry.Summary)
        combined.WriteString("\n\n---\n\n")
        totalTokens += entryTokens + 20 // +20 for headers
    }
    
    return combined.String(), nil
}

// UpdateMemoryEntry updates an existing entry
func (s *MemoryStore) UpdateMemoryEntry(ctx context.Context, entry *models.MemoryEntry) error {
    return s.db.WithContext(ctx).Save(entry).Error
}

// DeleteMemoryEntries removes all entries for an instruction
func (s *MemoryStore) DeleteMemoryEntries(ctx context.Context, instructionId string) error {
    return s.db.WithContext(ctx).
        Where("instruction_id = ?", instructionId).
        Delete(&models.MemoryEntry{}).Error
}

// GetCompressionStats returns compression statistics for an instruction
func (s *MemoryStore) GetCompressionStats(
    ctx context.Context,
    instructionId string,
) (*CompressionStats, error) {
    var entries []models.MemoryEntry
    err := s.db.WithContext(ctx).
        Where("instruction_id = ?", instructionId).
        Find(&entries).Error
    
    if err != nil {
        return nil, err
    }
    
    stats := &CompressionStats{
        TurnCount: len(entries),
    }
    
    for _, entry := range entries {
        stats.TotalOriginalTokens += entry.OriginalTokens
        stats.TotalCompressedTokens += entry.CompressedTokens
    }
    
    if stats.TotalOriginalTokens > 0 {
        stats.OverallCompressionRatio = 1.0 - (float64(stats.TotalCompressedTokens) / float64(stats.TotalOriginalTokens))
    }
    
    stats.TokensSaved = stats.TotalOriginalTokens - stats.TotalCompressedTokens
    
    return stats, nil
}

// CompressionStats holds aggregate compression statistics
type CompressionStats struct {
    TurnCount               int     `json:"turnCount"`
    TotalOriginalTokens     int     `json:"totalOriginalTokens"`
    TotalCompressedTokens   int     `json:"totalCompressedTokens"`
    TokensSaved             int     `json:"tokensSaved"`
    OverallCompressionRatio float64 `json:"overallCompressionRatio"`
}
```

---

## 24.5 Multi-Turn Execution Integration

### Integration with Instruction Segmentation

```go
// MultiTurnExecutor integrates memory with segmentation
type MultiTurnExecutor struct {
    segmentationService InstructionSegmentationService
    memoryService       *MemoryCompressionService
    memoryStore         *MemoryStore
    contextManager      ContextWindowManager
    tokenCounter        TokenCounter
    config              MultiTurnConfig
}

// MultiTurnConfig holds execution configuration
type MultiTurnConfig struct {
    EnableMemoryCompression  bool `json:"enableMemoryCompression"`
    MemoryTokenBudget        int  `json:"memoryTokenBudget"`       // Max tokens for memory context
    CompressAfterEachTurn    bool `json:"compressAfterEachTurn"`
    MaxMemoryEntries         int  `json:"maxMemoryEntries"`        // Max entries to keep
    IncrementalSummarization bool `json:"incrementalSummarization"` // Update vs replace summary
}

// DefaultMultiTurnConfig returns sensible defaults
func DefaultMultiTurnConfig() MultiTurnConfig {
    return MultiTurnConfig{
        EnableMemoryCompression:  true,
        MemoryTokenBudget:        1000,
        CompressAfterEachTurn:    true,
        MaxMemoryEntries:         10,
        IncrementalSummarization: true,
    }
}

// NewMultiTurnExecutor creates a new executor
func NewMultiTurnExecutor(
    segmentationService InstructionSegmentationService,
    memoryService *MemoryCompressionService,
    memoryStore *MemoryStore,
    contextManager ContextWindowManager,
    tokenCounter TokenCounter,
    config MultiTurnConfig,
) *MultiTurnExecutor {
    return &MultiTurnExecutor{
        segmentationService: segmentationService,
        memoryService:       memoryService,
        memoryStore:         memoryStore,
        contextManager:      contextManager,
        tokenCounter:        tokenCounter,
        config:              config,
    }
}

// ExecuteWithMemory runs instruction with memory continuity
func (e *MultiTurnExecutor) ExecuteWithMemory(
    ctx context.Context,
    instructionId string,
    content string,
    sessionId string,
) error {
    // Check if segmentation needed
    needsSegmentation, tokenCount, err := e.segmentationService.NeedsSegmentation(
        ctx, content, e.contextManager.GetConfig().AvailableForRetrieval())
    
    if err != nil {
        return fmt.Errorf("segmentation check failed: %w", err)
    }
    
    if !needsSegmentation {
        // Execute as single turn
        return e.executeSingleTurn(ctx, instructionId, content, sessionId, 1)
    }
    
    // Segment and execute
    plan, err := e.segmentationService.Segment(ctx, instructionId, content)
    if err != nil {
        return fmt.Errorf("segmentation failed: %w", err)
    }
    
    // Execute each segment with memory
    for turnIndex, segmentIndex := range plan.ExecutionOrder {
        segment := &plan.Segments[segmentIndex]
        
        // Build context with memory
        memoryContext, err := e.buildMemoryContext(ctx, instructionId, turnIndex)
        if err != nil {
            return fmt.Errorf("memory context failed for turn %d: %w", turnIndex, err)
        }
        
        // Execute segment
        output, err := e.executeSegmentWithMemory(ctx, segment, memoryContext)
        if err != nil {
            return fmt.Errorf("segment %d execution failed: %w", segmentIndex, err)
        }
        
        // Compress and store memory
        if e.config.EnableMemoryCompression {
            if err := e.compressAndStoreMemory(ctx, instructionId, sessionId, turnIndex+1, output); err != nil {
                // Non-fatal, log and continue
                fmt.Printf("Warning: memory compression failed for turn %d: %v\n", turnIndex+1, err)
            }
        }
    }
    
    return nil
}

// buildMemoryContext retrieves and formats memory for context injection
func (e *MultiTurnExecutor) buildMemoryContext(
    ctx context.Context,
    instructionId string,
    currentTurn int,
) (string, error) {
    if !e.config.EnableMemoryCompression {
        return "", nil
    }
    
    // Get combined memory within budget
    memory, err := e.memoryStore.GetCombinedMemory(
        ctx, instructionId, e.config.MemoryTokenBudget, e.tokenCounter)
    
    if err != nil {
        return "", err
    }
    
    if memory == "" {
        return "", nil
    }
    
    // Format for context injection
    return fmt.Sprintf(`## Previous Execution Context

The following is a summary of previous execution turns. Use this context to maintain continuity.

%s

---

## Current Turn: %d

Continue from where the previous turns left off.
`, memory, currentTurn+1), nil
}

// executeSegmentWithMemory executes a segment with memory context
func (e *MultiTurnExecutor) executeSegmentWithMemory(
    ctx context.Context,
    segment *ExecutionSegment,
    memoryContext string,
) (string, error) {
    // Build context request
    req := AssembleRequest{
        SystemPrompt: fmt.Sprintf(
            "You are executing a multi-turn instruction. This is segment %d. "+
            "Previous context is provided to maintain continuity.",
            segment.SegmentIndex+1,
        ),
        MemoryContext: memoryContext,
        Instruction:   segment.Content,
        UserQuery:     fmt.Sprintf("Execute: %s", segment.Title),
    }
    
    assembled, err := e.contextManager.Assemble(ctx, req)
    if err != nil {
        return "", fmt.Errorf("context assembly failed: %w", err)
    }
    
    // Execute via AI service (assumed to be available on segment engine)
    // This would normally call the AI service directly
    return segment.Output, nil
}

// executeSingleTurn executes instruction as single turn
func (e *MultiTurnExecutor) executeSingleTurn(
    ctx context.Context,
    instructionId string,
    content string,
    sessionId string,
    turnIndex int,
) error {
    // Get any existing memory context
    memoryContext, _ := e.buildMemoryContext(ctx, instructionId, turnIndex-1)
    
    req := AssembleRequest{
        SystemPrompt:  "You are executing an instruction.",
        MemoryContext: memoryContext,
        Instruction:   content,
    }
    
    _, err := e.contextManager.Assemble(ctx, req)
    // Execute would happen here
    
    return err
}

// compressAndStoreMemory compresses output and stores as memory entry
func (e *MultiTurnExecutor) compressAndStoreMemory(
    ctx context.Context,
    instructionId string,
    sessionId string,
    turnIndex int,
    output string,
) error {
    // Compress output
    result, err := e.memoryService.CompressWithDetails(
        ctx, output, e.config.MemoryTokenBudget/2, PromptTypeExecution)
    
    if err != nil {
        return err
    }
    
    // Build memory entry
    entry := &models.MemoryEntry{
        InstructionId:    instructionId,
        SessionId:        sessionId,
        TurnIndex:        turnIndex,
        OriginalTokens:   result.OriginalTokens,
        CompressedTokens: result.CompressedTokens,
        Summary:          result.Summary,
    }
    
    // Serialize structured data
    if len(result.KeyDecisions) > 0 {
        decisionsJson, _ := json.Marshal(result.KeyDecisions)
        entry.KeyDecisions = string(decisionsJson)
    }
    
    if len(result.ArtifactsCreated) > 0 {
        artifactsJson, _ := json.Marshal(result.ArtifactsCreated)
        entry.ArtifactsCreated = string(artifactsJson)
    }
    
    if len(result.OpenQuestions) > 0 {
        questionsJson, _ := json.Marshal(result.OpenQuestions)
        entry.OpenQuestions = string(questionsJson)
    }
    
    // Check incremental mode
    if e.config.IncrementalSummarization {
        existing, _ := e.memoryStore.GetLatestMemory(ctx, instructionId)
        if existing != nil {
            // Merge with existing summary
            mergedSummary, err := e.memoryService.CompressWithDetails(
                ctx,
                fmt.Sprintf("Previous Summary:\n%s\n\nNew Turn Output:\n%s", existing.Summary, output),
                e.config.MemoryTokenBudget,
                PromptTypeIncremental,
            )
            if err == nil {
                entry.Summary = mergedSummary.Summary
            }
        }
    }
    
    // Enforce max entries
    entries, _ := e.memoryStore.GetMemoryEntries(ctx, instructionId)
    if len(entries) >= e.config.MaxMemoryEntries {
        // Delete oldest entry
        if len(entries) > 0 {
            e.memoryStore.db.Delete(&entries[0])
        }
    }
    
    return e.memoryStore.SaveMemoryEntry(ctx, entry)
}
```

---

## 24.6 Service Interface

### Main Interface

```go
// MemoryCompressionServiceInterface defines the public API
type MemoryCompressionServiceInterface interface {
    // Compression
    Compress(ctx context.Context, content string, targetTokens int) (string, error)
    CompressWithDetails(ctx context.Context, content string, targetTokens int, promptType SummarizationPromptType) (*CompressionResult, error)
    
    // Memory Management
    StoreMemory(ctx context.Context, instructionId, sessionId string, turnIndex int, output string) error
    GetMemory(ctx context.Context, instructionId string) ([]models.MemoryEntry, error)
    GetCombinedMemory(ctx context.Context, instructionId string, maxTokens int) (string, error)
    GetCompressionStats(ctx context.Context, instructionId string) (*CompressionStats, error)
    
    // Prompts
    GetPrompt(promptType SummarizationPromptType) (SummarizationPrompt, bool)
    RegisterPrompt(prompt SummarizationPrompt) error
    
    // Cache
    ClearCache() error
}

// Ensure implementation satisfies interface
var _ MemoryCompressionServiceInterface = (*FullMemoryCompressionService)(nil)

// FullMemoryCompressionService combines compression and storage
type FullMemoryCompressionService struct {
    compression *MemoryCompressionService
    store       *MemoryStore
    tokenCounter TokenCounter
}

// NewFullMemoryCompressionService creates the full service
func NewFullMemoryCompressionService(
    aiService AIService,
    tokenCounter TokenCounter,
    db *gorm.DB,
) *FullMemoryCompressionService {
    config := DefaultMemoryCompressionConfig()
    
    return &FullMemoryCompressionService{
        compression:  NewMemoryCompressionService(aiService, tokenCounter, db, config),
        store:        NewMemoryStore(db),
        tokenCounter: tokenCounter,
    }
}

// Compress delegates to compression service
func (s *FullMemoryCompressionService) Compress(ctx context.Context, content string, targetTokens int) (string, error) {
    return s.compression.Compress(ctx, content, targetTokens)
}

// CompressWithDetails delegates to compression service
func (s *FullMemoryCompressionService) CompressWithDetails(ctx context.Context, content string, targetTokens int, promptType SummarizationPromptType) (*CompressionResult, error) {
    return s.compression.CompressWithDetails(ctx, content, targetTokens, promptType)
}

// StoreMemory compresses and stores memory entry
func (s *FullMemoryCompressionService) StoreMemory(ctx context.Context, instructionId, sessionId string, turnIndex int, output string) error {
    result, err := s.compression.CompressWithDetails(ctx, output, 500, PromptTypeExecution)
    if err != nil {
        return err
    }
    
    entry := &models.MemoryEntry{
        InstructionId:    instructionId,
        SessionId:        sessionId,
        TurnIndex:        turnIndex,
        OriginalTokens:   result.OriginalTokens,
        CompressedTokens: result.CompressedTokens,
        Summary:          result.Summary,
    }
    
    return s.store.SaveMemoryEntry(ctx, entry)
}

// GetMemory retrieves memory entries
func (s *FullMemoryCompressionService) GetMemory(ctx context.Context, instructionId string) ([]models.MemoryEntry, error) {
    return s.store.GetMemoryEntries(ctx, instructionId)
}

// GetCombinedMemory retrieves combined memory context
func (s *FullMemoryCompressionService) GetCombinedMemory(ctx context.Context, instructionId string, maxTokens int) (string, error) {
    return s.store.GetCombinedMemory(ctx, instructionId, maxTokens, s.tokenCounter)
}

// GetCompressionStats retrieves statistics
func (s *FullMemoryCompressionService) GetCompressionStats(ctx context.Context, instructionId string) (*CompressionStats, error) {
    return s.store.GetCompressionStats(ctx, instructionId)
}

// GetPrompt retrieves a prompt template
func (s *FullMemoryCompressionService) GetPrompt(promptType SummarizationPromptType) (SummarizationPrompt, bool) {
    return s.compression.prompter.GetPrompt(promptType)
}

// RegisterPrompt adds a custom prompt
func (s *FullMemoryCompressionService) RegisterPrompt(prompt SummarizationPrompt) error {
    s.compression.prompter.RegisterPrompt(prompt)
    return nil
}

// ClearCache clears the compression cache
func (s *FullMemoryCompressionService) ClearCache() error {
    s.compression.cache = sync.Map{}
    return nil
}
```

---

## 24.7 Configuration Keys

Add to `09-seeding-configuration.md`:

```json
{
    "Key": "memory.compressionEnabled",
    "Value": "true",
    "Description": "Enable memory compression for multi-turn execution"
},
{
    "Key": "memory.defaultTargetTokens",
    "Value": "500",
    "Description": "Default target tokens for compressed summaries"
},
{
    "Key": "memory.minCompressionRatio",
    "Value": "0.5",
    "Description": "Minimum required compression ratio (0.0-1.0)"
},
{
    "Key": "memory.maxRetries",
    "Value": "2",
    "Description": "Maximum retries for failed compression"
},
{
    "Key": "memory.extractStructuredData",
    "Value": "true",
    "Description": "Extract decisions/artifacts from summaries"
},
{
    "Key": "memory.cacheEnabled",
    "Value": "true",
    "Description": "Cache compression results"
},
{
    "Key": "memory.cacheTTLMinutes",
    "Value": "30",
    "Description": "Cache expiry time in minutes"
},
{
    "Key": "memory.tokenBudget",
    "Value": "1000",
    "Description": "Maximum tokens for memory context"
},
{
    "Key": "memory.maxEntries",
    "Value": "10",
    "Description": "Maximum memory entries per instruction"
},
{
    "Key": "memory.incrementalSummarization",
    "Value": "true",
    "Description": "Use incremental summarization vs replace"
}
```

---

## 24.8 API Endpoints

Add to `03-api-endpoints.md`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/memory/compress` | Compress content to target tokens |
| GET | `/api/v1/instructions/{id}/memory` | Get memory entries for instruction |
| GET | `/api/v1/instructions/{id}/memory/combined` | Get combined memory context |
| GET | `/api/v1/instructions/{id}/memory/stats` | Get compression statistics |
| POST | `/api/v1/instructions/{id}/memory` | Store new memory entry |
| DELETE | `/api/v1/instructions/{id}/memory` | Clear memory for instruction |
| GET | `/api/v1/memory/prompts` | List available summarization prompts |
| POST | `/api/v1/memory/prompts` | Register custom prompt |

---

## 24.9 Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 5080 | ERR_COMPRESSION_FAILED | Memory compression failed |
| 5081 | ERR_COMPRESSION_RATIO_LOW | Compression ratio below minimum |
| 5082 | ERR_COMPRESSION_EXCEEDS_TARGET | Compressed output exceeds target |
| 5083 | ERR_MEMORY_STORE_FAILED | Failed to store memory entry |
| 5084 | ERR_MEMORY_RETRIEVE_FAILED | Failed to retrieve memory entries |
| 5085 | ERR_PROMPT_NOT_FOUND | Summarization prompt not found |
| 5086 | ERR_INVALID_PROMPT_TYPE | Invalid summarization prompt type |

---

## 24.10 Unit Test Requirements

| Test Case | Priority |
|-----------|----------|
| Compress reduces token count | HIGH |
| Compress meets target tokens | HIGH |
| CompressWithDetails extracts decisions | HIGH |
| CompressWithDetails extracts artifacts | HIGH |
| Incremental summarization merges correctly | HIGH |
| Cache returns identical results | MEDIUM |
| Recompression stricter on failure | MEDIUM |
| MemoryStore saves and retrieves correctly | HIGH |
| GetCombinedMemory respects token limit | HIGH |
| MultiTurnExecutor passes memory between segments | HIGH |

---

## 24.11 Acceptance Criteria

### Compression Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CO-001 | Compress() reduces content by at least 50% | Critical | Compression ratio test |
| CO-002 | Compressed output ≤ target tokens | Critical | Target token test |
| CO-003 | Recompression with stricter limit on overage | High | Retry compression test |
| CO-004 | Empty content returns empty summary | Medium | Empty input test |
| CO-005 | Processing time tracked accurately | Medium | Timing test |

### Structured Extraction (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SE-001 | Key decisions extracted from summaries | Critical | Decision extraction test |
| SE-002 | Artifacts created listed correctly | Critical | Artifact extraction test |
| SE-003 | Open questions identified | High | Question extraction test |
| SE-004 | Dependencies captured | High | Dependency extraction test |
| SE-005 | Extraction works for all prompt types | High | Multi-type extraction test |

### Prompt Types (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PT-001 | PromptTypeExecution compresses execution output | Critical | Execution prompt test |
| PT-002 | PromptTypeConversation compresses chat history | Critical | Conversation prompt test |
| PT-003 | PromptTypeArtifact compresses documents | High | Artifact prompt test |
| PT-004 | PromptTypeIncremental merges with existing | High | Incremental prompt test |
| PT-005 | BuildPrompt() expands template variables | High | Template expansion test |
| PT-006 | RegisterPrompt() adds custom prompts | Medium | Custom prompt test |

### Memory Store (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MS-001 | SaveMemory stores entry with metadata | Critical | Store test |
| MS-002 | GetMemoriesForInstruction retrieves entries | Critical | Retrieval test |
| MS-003 | GetCombinedMemory respects token budget | Critical | Budget limit test |
| MS-004 | Entries ordered by segment index | High | Ordering test |
| MS-005 | MemoryEntry.SourceType set correctly | Medium | Source type test |

### Caching (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CA-001 | Cache enabled returns identical results | High | Cache hit test |
| CA-002 | Cache key includes content + target + type | High | Cache key test |
| CA-003 | Cache TTL expires entries correctly | Medium | TTL test |
| CA-004 | Cache disabled skips caching | Medium | Disable test |

### Multi-Turn Integration (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MT-001 | Memory passed between execution turns | Critical | Multi-turn test |
| MT-002 | Incremental summarization preserves history | High | History preservation test |
| MT-003 | Combined memory fits in context window | Critical | Context fit test |
| MT-004 | Segment execution receives correct context | High | Context injection test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | ERR_COMPRESSION_FAILED (5080) for failure | Critical | Error code test |
| EH-002 | ERR_COMPRESSION_RATIO_LOW (5081) for low ratio | High | Error code test |
| EH-003 | ERR_COMPRESSION_EXCEEDS_TARGET (5082) for overage | High | Error code test |
| EH-004 | ERR_MEMORY_STORE_FAILED (5083) for store failure | High | Error code test |
| EH-005 | ERR_PROMPT_NOT_FOUND (5085) for unknown prompt | High | Error code test |
| EH-006 | All errors include instruction and segment IDs | High | Error context test |

---

## Related Specifications

- [Vector Database Plan](./04-vector-database-plan.md)
- [Instruction Segmentation](../06-ai-integration/05-instruction-segmentation.md)
- [Context Window Manager](./06-context-window-manager.md)
- [Database Schema](../../07-database-design/01-schema.md)
- [AI Integration](../06-ai-integration/01-ai-integration.md)
- [Prompt Presets](../06-ai-integration/02-presets-guidelines.md)
