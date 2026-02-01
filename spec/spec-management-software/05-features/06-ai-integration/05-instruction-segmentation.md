# Instruction Segmentation System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This specification defines the Instruction Segmentation system for handling large instructions that exceed LLM context window limits. The system parses complex instructions into ordered segments, tracks dependencies between segments, and orchestrates multi-turn execution with context continuity through memory summaries.

**Cross-References:**
- [Vector Database Plan](../09-knowledge-memory/04-vector-database-plan.md) - Overall enhancement strategy (§20.3.2)
- [Context Window Manager](../09-knowledge-memory/06-context-window-manager.md) - Token budgeting
- [Database Schema](../../07-database-design/01-schema.md) - InstructionSegment model
- [Instruction System](./03-instruction-system.md) - Instruction lifecycle
- [AI Integration](./01-ai-integration.md) - LLM invocation

---

## 23.1 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    INSTRUCTION SEGMENTATION ARCHITECTURE                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐     │
│  │                     InstructionSegmentationService                   │     │
│  ├─────────────────────────────────────────────────────────────────────┤     │
│  │                                                                       │     │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │     │
│  │  │ Segmentation │  │  Dependency  │  │  Execution   │               │     │
│  │  │    Parser    │  │   Resolver   │  │    Engine    │               │     │
│  │  └──────────────┘  └──────────────┘  └──────────────┘               │     │
│  │         │                 │                 │                        │     │
│  │         └─────────────────┼─────────────────┘                        │     │
│  │                           ▼                                          │     │
│  │  ┌──────────────────────────────────────────────────────────────┐   │     │
│  │  │                   Segment Orchestrator                        │   │     │
│  │  ├──────────────────────────────────────────────────────────────┤   │     │
│  │  │  Parse → Order → Execute → Summarize → Continue → Complete   │   │     │
│  │  └──────────────────────────────────────────────────────────────┘   │     │
│  │                                                                       │     │
│  └─────────────────────────────────────────────────────────────────────┘     │
│                                    │                                          │
│                    ┌───────────────┼───────────────┐                          │
│                    ▼               ▼               ▼                          │
│  ┌─────────────────────┐ ┌─────────────────┐ ┌─────────────────────────┐     │
│  │  Context Window     │ │   Memory        │ │  Instruction            │     │
│  │  Manager            │ │   Compression   │ │  Repository             │     │
│  └─────────────────────┘ └─────────────────┘ └─────────────────────────┘     │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 23.2 Segmentation Parser

### Section Detection Patterns

The parser identifies logical sections within instructions using structural and semantic patterns.

```go
package services

import (
    "context"
    "regexp"
    "strings"
)

// SectionPattern defines a pattern for detecting logical sections
type SectionPattern struct {
    Name        string         // Pattern identifier
    Pattern     *regexp.Regexp // Regex pattern
    Priority    int            // Higher = matched first
    IsBoundary  bool           // Creates segment boundary
    IsSubsection bool          // Nested within parent section
}

// DefaultSectionPatterns provides standard patterns for instruction parsing
var DefaultSectionPatterns = []SectionPattern{
    {
        Name:       "markdown_h1",
        Pattern:    regexp.MustCompile(`(?m)^#\s+(.+)$`),
        Priority:   100,
        IsBoundary: true,
    },
    {
        Name:       "markdown_h2",
        Pattern:    regexp.MustCompile(`(?m)^##\s+(.+)$`),
        Priority:   90,
        IsBoundary: true,
    },
    {
        Name:       "markdown_h3",
        Pattern:    regexp.MustCompile(`(?m)^###\s+(.+)$`),
        Priority:   80,
        IsBoundary: false,
        IsSubsection: true,
    },
    {
        Name:       "numbered_section",
        Pattern:    regexp.MustCompile(`(?m)^\d+\.\s+(.+)$`),
        Priority:   70,
        IsBoundary: true,
    },
    {
        Name:       "phase_marker",
        Pattern:    regexp.MustCompile(`(?mi)^(?:phase|step|stage)\s+\d+[:\s]+(.+)$`),
        Priority:   95,
        IsBoundary: true,
    },
    {
        Name:       "component_marker",
        Pattern:    regexp.MustCompile(`(?mi)^(?:component|module|service|feature)[:\s]+(.+)$`),
        Priority:   85,
        IsBoundary: true,
    },
    {
        Name:       "horizontal_rule",
        Pattern:    regexp.MustCompile(`(?m)^---+$`),
        Priority:   60,
        IsBoundary: true,
    },
}

// ParsedSection represents a detected section in the instruction
type ParsedSection struct {
    Title       string   `json:"title"`
    Content     string   `json:"content"`
    StartLine   int      `json:"startLine"`
    EndLine     int      `json:"endLine"`
    TokenCount  int      `json:"tokenCount"`
    Pattern     string   `json:"pattern"`       // Pattern that detected this section
    Subsections []ParsedSection `json:"subsections,omitempty"`
    Keywords    []string `json:"keywords"`      // Extracted keywords for dependency detection
}

// SegmentationParser parses instructions into sections
type SegmentationParser struct {
    patterns     []SectionPattern
    tokenCounter TokenCounter
    config       SegmentationConfig
}

// SegmentationConfig holds parser configuration
type SegmentationConfig struct {
    MaxTokensPerSegment   int     `json:"maxTokensPerSegment"`   // Default: 4000
    MinTokensPerSegment   int     `json:"minTokensPerSegment"`   // Default: 500
    OverlapTokens         int     `json:"overlapTokens"`         // Context overlap between segments
    MergeSmallSections    bool    `json:"mergeSmallSections"`    // Combine tiny sections
    PreserveCodeBlocks    bool    `json:"preserveCodeBlocks"`    // Don't split code blocks
    ExtractKeywords       bool    `json:"extractKeywords"`       // Extract keywords for dependencies
}

// DefaultSegmentationConfig returns sensible defaults
func DefaultSegmentationConfig() SegmentationConfig {
    return SegmentationConfig{
        MaxTokensPerSegment: 4000,
        MinTokensPerSegment: 500,
        OverlapTokens:       100,
        MergeSmallSections:  true,
        PreserveCodeBlocks:  true,
        ExtractKeywords:     true,
    }
}

// NewSegmentationParser creates a new parser
func NewSegmentationParser(tokenCounter TokenCounter, config SegmentationConfig) *SegmentationParser {
    return &SegmentationParser{
        patterns:     DefaultSectionPatterns,
        tokenCounter: tokenCounter,
        config:       config,
    }
}

// Parse splits instruction content into sections
func (p *SegmentationParser) Parse(ctx context.Context, content string) ([]ParsedSection, error) {
    lines := strings.Split(content, "\n")
    sections := make([]ParsedSection, 0)
    
    currentSection := ParsedSection{
        Title:     "Introduction",
        StartLine: 0,
    }
    var contentBuilder strings.Builder
    
    for lineNum, line := range lines {
        matched := false
        
        // Check each pattern
        for _, pattern := range p.patterns {
            if pattern.IsBoundary && pattern.Pattern.MatchString(line) {
                // Close current section
                if contentBuilder.Len() > 0 || lineNum > currentSection.StartLine {
                    currentSection.Content = strings.TrimSpace(contentBuilder.String())
                    currentSection.EndLine = lineNum - 1
                    currentSection.TokenCount, _ = p.tokenCounter.Count(currentSection.Content)
                    
                    if p.config.ExtractKeywords {
                        currentSection.Keywords = p.extractKeywords(currentSection.Content)
                    }
                    
                    sections = append(sections, currentSection)
                }
                
                // Start new section
                matches := pattern.Pattern.FindStringSubmatch(line)
                title := line
                if len(matches) > 1 {
                    title = matches[1]
                }
                
                currentSection = ParsedSection{
                    Title:     strings.TrimSpace(title),
                    StartLine: lineNum,
                    Pattern:   pattern.Name,
                }
                contentBuilder.Reset()
                matched = true
                break
            }
        }
        
        if !matched {
            contentBuilder.WriteString(line)
            contentBuilder.WriteString("\n")
        }
    }
    
    // Close final section
    if contentBuilder.Len() > 0 {
        currentSection.Content = strings.TrimSpace(contentBuilder.String())
        currentSection.EndLine = len(lines) - 1
        currentSection.TokenCount, _ = p.tokenCounter.Count(currentSection.Content)
        if p.config.ExtractKeywords {
            currentSection.Keywords = p.extractKeywords(currentSection.Content)
        }
        sections = append(sections, currentSection)
    }
    
    // Post-process: merge small sections if configured
    if p.config.MergeSmallSections {
        sections = p.mergeSmallSections(sections)
    }
    
    return sections, nil
}

// extractKeywords extracts relevant keywords for dependency detection
func (p *SegmentationParser) extractKeywords(content string) []string {
    keywords := make([]string, 0)
    
    // Technical term patterns
    patterns := []*regexp.Regexp{
        regexp.MustCompile(`(?i)\b(api|service|model|controller|repository|handler|middleware)\b`),
        regexp.MustCompile(`(?i)\b(authentication|authorization|oauth|jwt|session|rbac)\b`),
        regexp.MustCompile(`(?i)\b(database|table|schema|migration|query)\b`),
        regexp.MustCompile(`(?i)\b(user|role|permission|access|token)\b`),
        regexp.MustCompile(`(?i)\b(create|update|delete|read|list|get|set)\b`),
        regexp.MustCompile(`(?i)\b(validate|sanitize|encrypt|hash|verify)\b`),
    }
    
    seen := make(map[string]bool)
    for _, pattern := range patterns {
        matches := pattern.FindAllString(content, -1)
        for _, match := range matches {
            lower := strings.ToLower(match)
            if !seen[lower] {
                keywords = append(keywords, lower)
                seen[lower] = true
            }
        }
    }
    
    return keywords
}

// mergeSmallSections combines sections below minimum token threshold
func (p *SegmentationParser) mergeSmallSections(sections []ParsedSection) []ParsedSection {
    if len(sections) <= 1 {
        return sections
    }
    
    merged := make([]ParsedSection, 0)
    var accumulator *ParsedSection
    
    for _, section := range sections {
        if accumulator == nil {
            accumulator = &ParsedSection{
                Title:      section.Title,
                Content:    section.Content,
                StartLine:  section.StartLine,
                EndLine:    section.EndLine,
                TokenCount: section.TokenCount,
                Keywords:   section.Keywords,
            }
            continue
        }
        
        combined := accumulator.TokenCount + section.TokenCount
        
        if accumulator.TokenCount < p.config.MinTokensPerSegment && 
           combined <= p.config.MaxTokensPerSegment {
            // Merge into accumulator
            accumulator.Content += "\n\n" + section.Title + "\n\n" + section.Content
            accumulator.EndLine = section.EndLine
            accumulator.TokenCount = combined
            accumulator.Keywords = append(accumulator.Keywords, section.Keywords...)
        } else {
            // Save accumulator, start new
            merged = append(merged, *accumulator)
            accumulator = &ParsedSection{
                Title:      section.Title,
                Content:    section.Content,
                StartLine:  section.StartLine,
                EndLine:    section.EndLine,
                TokenCount: section.TokenCount,
                Keywords:   section.Keywords,
            }
        }
    }
    
    if accumulator != nil {
        merged = append(merged, *accumulator)
    }
    
    return merged
}
```

---

## 23.3 Dependency Resolution

### Dependency Graph

```go
// DependencyType classifies the relationship between segments
type DependencyType string

const (
    DependencyStrict   DependencyType = "strict"   // Must complete before
    DependencyPreferred DependencyType = "preferred" // Should complete before, but can proceed
    DependencyOptional DependencyType = "optional"  // Nice to have context
)

// SegmentDependency represents a dependency between segments
type SegmentDependency struct {
    FromSegmentIndex int            `json:"fromSegmentIndex"`
    ToSegmentIndex   int            `json:"toSegmentIndex"`
    Type             DependencyType `json:"type"`
    Reason           string         `json:"reason"`
    Confidence       float64        `json:"confidence"` // 0.0-1.0
}

// DependencyGraph manages segment ordering
type DependencyGraph struct {
    segments     []ParsedSection
    dependencies []SegmentDependency
    adjacency    map[int][]int // adjacency list
}

// DependencyResolver analyzes and resolves segment dependencies
type DependencyResolver struct {
    keywordRules []KeywordDependencyRule
}

// KeywordDependencyRule defines dependency based on keyword presence
type KeywordDependencyRule struct {
    IfContains      []string       // Keywords in dependent segment
    RequiresKeyword []string       // Keywords in required segment
    Type            DependencyType
    Reason          string
}

// DefaultKeywordRules provides standard dependency rules
var DefaultKeywordRules = []KeywordDependencyRule{
    {
        IfContains:      []string{"rbac", "permission", "role"},
        RequiresKeyword: []string{"authentication", "user", "session"},
        Type:            DependencyStrict,
        Reason:          "RBAC requires authentication to be in place",
    },
    {
        IfContains:      []string{"audit", "logging"},
        RequiresKeyword: []string{"model", "service", "handler"},
        Type:            DependencyPreferred,
        Reason:          "Audit logging works better after core services exist",
    },
    {
        IfContains:      []string{"migration"},
        RequiresKeyword: []string{"schema", "model"},
        Type:            DependencyStrict,
        Reason:          "Migrations require schema definitions",
    },
    {
        IfContains:      []string{"controller", "handler"},
        RequiresKeyword: []string{"service", "repository"},
        Type:            DependencyPreferred,
        Reason:          "Controllers typically call services",
    },
    {
        IfContains:      []string{"test"},
        RequiresKeyword: []string{"service", "model", "handler"},
        Type:            DependencyStrict,
        Reason:          "Tests require implementation to exist",
    },
}

// NewDependencyResolver creates a resolver
func NewDependencyResolver() *DependencyResolver {
    return &DependencyResolver{
        keywordRules: DefaultKeywordRules,
    }
}

// Resolve analyzes sections and builds dependency graph
func (r *DependencyResolver) Resolve(sections []ParsedSection) (*DependencyGraph, error) {
    graph := &DependencyGraph{
        segments:     sections,
        dependencies: make([]SegmentDependency, 0),
        adjacency:    make(map[int][]int),
    }
    
    // Analyze each pair of sections
    for i, section := range sections {
        for j, other := range sections {
            if i == j {
                continue
            }
            
            // Check keyword-based dependencies
            for _, rule := range r.keywordRules {
                if r.matchesKeywords(section.Keywords, rule.IfContains) &&
                   r.matchesKeywords(other.Keywords, rule.RequiresKeyword) {
                    
                    // Calculate confidence based on keyword overlap
                    confidence := r.calculateConfidence(section.Keywords, other.Keywords, rule)
                    
                    if confidence > 0.5 {
                        dep := SegmentDependency{
                            FromSegmentIndex: i,
                            ToSegmentIndex:   j,
                            Type:             rule.Type,
                            Reason:           rule.Reason,
                            Confidence:       confidence,
                        }
                        graph.dependencies = append(graph.dependencies, dep)
                        graph.adjacency[i] = append(graph.adjacency[i], j)
                    }
                }
            }
        }
    }
    
    // Check for cycles
    if r.hasCycle(graph) {
        // Remove lowest confidence edges to break cycles
        graph = r.breakCycles(graph)
    }
    
    return graph, nil
}

// matchesKeywords checks if keywords contain any required keywords
func (r *DependencyResolver) matchesKeywords(keywords, required []string) bool {
    for _, req := range required {
        for _, kw := range keywords {
            if strings.Contains(kw, req) || strings.Contains(req, kw) {
                return true
            }
        }
    }
    return false
}

// calculateConfidence computes dependency confidence
func (r *DependencyResolver) calculateConfidence(
    sectionKeywords, otherKeywords []string, 
    rule KeywordDependencyRule,
) float64 {
    matchCount := 0
    totalRequired := len(rule.IfContains) + len(rule.RequiresKeyword)
    
    for _, kw := range rule.IfContains {
        for _, sk := range sectionKeywords {
            if strings.EqualFold(kw, sk) {
                matchCount++
                break
            }
        }
    }
    
    for _, kw := range rule.RequiresKeyword {
        for _, ok := range otherKeywords {
            if strings.EqualFold(kw, ok) {
                matchCount++
                break
            }
        }
    }
    
    return float64(matchCount) / float64(totalRequired)
}

// hasCycle detects cycles using DFS
func (r *DependencyResolver) hasCycle(graph *DependencyGraph) bool {
    visited := make(map[int]bool)
    recStack := make(map[int]bool)
    
    var dfs func(node int) bool
    dfs = func(node int) bool {
        visited[node] = true
        recStack[node] = true
        
        for _, neighbor := range graph.adjacency[node] {
            if !visited[neighbor] {
                if dfs(neighbor) {
                    return true
                }
            } else if recStack[neighbor] {
                return true
            }
        }
        
        recStack[node] = false
        return false
    }
    
    for i := range graph.segments {
        if !visited[i] {
            if dfs(i) {
                return true
            }
        }
    }
    
    return false
}

// breakCycles removes lowest-confidence edges to eliminate cycles
func (r *DependencyResolver) breakCycles(graph *DependencyGraph) *DependencyGraph {
    // Sort dependencies by confidence (ascending)
    sort.Slice(graph.dependencies, func(i, j int) bool {
        return graph.dependencies[i].Confidence < graph.dependencies[j].Confidence
    })
    
    // Rebuild graph, skipping edges that create cycles
    newGraph := &DependencyGraph{
        segments:     graph.segments,
        dependencies: make([]SegmentDependency, 0),
        adjacency:    make(map[int][]int),
    }
    
    for _, dep := range graph.dependencies {
        // Temporarily add edge
        newGraph.adjacency[dep.FromSegmentIndex] = append(
            newGraph.adjacency[dep.FromSegmentIndex], 
            dep.ToSegmentIndex,
        )
        
        // Check for cycle
        if r.hasCycle(newGraph) {
            // Remove edge
            edges := newGraph.adjacency[dep.FromSegmentIndex]
            newGraph.adjacency[dep.FromSegmentIndex] = edges[:len(edges)-1]
        } else {
            newGraph.dependencies = append(newGraph.dependencies, dep)
        }
    }
    
    return newGraph
}

// TopologicalSort returns execution order respecting dependencies
func (g *DependencyGraph) TopologicalSort() ([]int, error) {
    inDegree := make(map[int]int)
    for i := range g.segments {
        inDegree[i] = 0
    }
    
    for _, deps := range g.adjacency {
        for _, dep := range deps {
            inDegree[dep]++
        }
    }
    
    // Start with nodes having no incoming edges
    queue := make([]int, 0)
    for i := range g.segments {
        if inDegree[i] == 0 {
            queue = append(queue, i)
        }
    }
    
    result := make([]int, 0, len(g.segments))
    
    for len(queue) > 0 {
        node := queue[0]
        queue = queue[1:]
        result = append(result, node)
        
        for _, neighbor := range g.adjacency[node] {
            inDegree[neighbor]--
            if inDegree[neighbor] == 0 {
                queue = append(queue, neighbor)
            }
        }
    }
    
    if len(result) != len(g.segments) {
        return nil, fmt.Errorf("dependency cycle detected, could not complete sort")
    }
    
    return result, nil
}
```

---

## 23.4 Segment Execution Engine

### Execution State Machine

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      SEGMENT EXECUTION STATE MACHINE                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│     ┌──────────┐                                                             │
│     │ PENDING  │◄────────────────────────────────────────────┐               │
│     └────┬─────┘                                             │               │
│          │ start()                                           │               │
│          ▼                                                   │               │
│     ┌──────────┐      dependencies_ready()      ┌──────────┐ │               │
│     │ WAITING  │ ──────────────────────────────▶│ READY    │ │               │
│     └────┬─────┘                                └────┬─────┘ │               │
│          │ dependencies_failed()                     │       │               │
│          │                                           │ execute()             │
│          ▼                                           ▼       │               │
│     ┌──────────┐                               ┌──────────┐  │               │
│     │ BLOCKED  │                               │EXECUTING │  │               │
│     └──────────┘                               └────┬─────┘  │               │
│                                                     │        │               │
│                          ┌──────────────────────────┼────────┤               │
│                          │                          │        │               │
│                          ▼                          ▼        │ retry()       │
│                     ┌──────────┐               ┌──────────┐  │               │
│                     │COMPLETED │               │  FAILED  │──┘               │
│                     └────┬─────┘               └──────────┘                  │
│                          │                                                    │
│                          │ summarize()                                        │
│                          ▼                                                    │
│                     ┌──────────┐                                             │
│                     │SUMMARIZED│                                             │
│                     └──────────┘                                             │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Execution Engine Implementation

```go
// SegmentStatus represents execution state
type SegmentStatus string

const (
    SegmentStatusPending    SegmentStatus = "pending"
    SegmentStatusWaiting    SegmentStatus = "waiting"
    SegmentStatusReady      SegmentStatus = "ready"
    SegmentStatusExecuting  SegmentStatus = "executing"
    SegmentStatusCompleted  SegmentStatus = "completed"
    SegmentStatusSummarized SegmentStatus = "summarized"
    SegmentStatusFailed     SegmentStatus = "failed"
    SegmentStatusBlocked    SegmentStatus = "blocked"
    SegmentStatusSkipped    SegmentStatus = "skipped"
)

// ExecutionSegment extends InstructionSegment with runtime state
type ExecutionSegment struct {
    models.InstructionSegment
    
    // Runtime state
    DependencyIds   []string         `json:"dependencyIds"`
    ExecutionOrder  int              `json:"executionOrder"`
    Attempts        int              `json:"attempts"`
    MaxAttempts     int              `json:"maxAttempts"`
    LastError       string           `json:"lastError"`
    Output          string           `json:"output"`
    ArtifactsCreated []string        `json:"artifactsCreated"`
}

// SegmentExecutionEngine orchestrates segment execution
type SegmentExecutionEngine struct {
    db              *gorm.DB
    contextManager  ContextWindowManager
    aiService       AIService
    memoryService   MemoryCompressionService
    config          ExecutionConfig
}

// ExecutionConfig holds engine configuration
type ExecutionConfig struct {
    MaxRetries              int           `json:"maxRetries"`
    RetryDelaySeconds       int           `json:"retryDelaySeconds"`
    SummarizeAfterComplete  bool          `json:"summarizeAfterComplete"`
    ContinueOnFailure       bool          `json:"continueOnFailure"`
    ParallelIndependent     bool          `json:"parallelIndependent"`
    MaxParallelSegments     int           `json:"maxParallelSegments"`
    TimeoutPerSegment       time.Duration `json:"timeoutPerSegment"`
}

// DefaultExecutionConfig returns sensible defaults
func DefaultExecutionConfig() ExecutionConfig {
    return ExecutionConfig{
        MaxRetries:             3,
        RetryDelaySeconds:      5,
        SummarizeAfterComplete: true,
        ContinueOnFailure:      false,
        ParallelIndependent:    false,
        MaxParallelSegments:    2,
        TimeoutPerSegment:      5 * time.Minute,
    }
}

// NewSegmentExecutionEngine creates a new engine
func NewSegmentExecutionEngine(
    db *gorm.DB,
    contextManager ContextWindowManager,
    aiService AIService,
    memoryService MemoryCompressionService,
    config ExecutionConfig,
) *SegmentExecutionEngine {
    return &SegmentExecutionEngine{
        db:             db,
        contextManager: contextManager,
        aiService:      aiService,
        memoryService:  memoryService,
        config:         config,
    }
}

// ExecutionPlan holds the complete execution plan
type ExecutionPlan struct {
    InstructionId  string              `json:"instructionId"`
    Segments       []ExecutionSegment  `json:"segments"`
    ExecutionOrder []int               `json:"executionOrder"`
    Dependencies   []SegmentDependency `json:"dependencies"`
    Status         string              `json:"status"` // "pending" | "running" | "completed" | "failed"
    CurrentIndex   int                 `json:"currentIndex"`
    StartedAt      *time.Time          `json:"startedAt"`
    CompletedAt    *time.Time          `json:"completedAt"`
}

// CreateExecutionPlan builds plan from parsed sections
func (e *SegmentExecutionEngine) CreateExecutionPlan(
    ctx context.Context,
    instructionId string,
    sections []ParsedSection,
    graph *DependencyGraph,
) (*ExecutionPlan, error) {
    order, err := graph.TopologicalSort()
    if err != nil {
        return nil, fmt.Errorf("failed to determine execution order: %w", err)
    }
    
    segments := make([]ExecutionSegment, len(sections))
    
    for i, section := range sections {
        // Find dependencies for this segment
        deps := make([]string, 0)
        for _, d := range graph.dependencies {
            if d.FromSegmentIndex == i {
                deps = append(deps, fmt.Sprintf("segment_%d", d.ToSegmentIndex))
            }
        }
        
        segment := ExecutionSegment{
            InstructionSegment: models.InstructionSegment{
                InstructionId: instructionId,
                SegmentIndex:  i,
                Title:         section.Title,
                Content:       section.Content,
                TokenCount:    section.TokenCount,
                Status:        string(SegmentStatusPending),
            },
            DependencyIds:  deps,
            ExecutionOrder: indexOf(order, i),
            MaxAttempts:    e.config.MaxRetries,
        }
        
        // Serialize dependencies
        if len(deps) > 0 {
            depsJson, _ := json.Marshal(deps)
            segment.DependsOnSegments = string(depsJson)
        }
        
        segments[i] = segment
    }
    
    // Save segments to database
    for i := range segments {
        if err := e.db.WithContext(ctx).Create(&segments[i].InstructionSegment).Error; err != nil {
            return nil, fmt.Errorf("failed to save segment %d: %w", i, err)
        }
    }
    
    return &ExecutionPlan{
        InstructionId:  instructionId,
        Segments:       segments,
        ExecutionOrder: order,
        Dependencies:   graph.dependencies,
        Status:         "pending",
    }, nil
}

// Execute runs the execution plan
func (e *SegmentExecutionEngine) Execute(ctx context.Context, plan *ExecutionPlan) error {
    now := time.Now()
    plan.StartedAt = &now
    plan.Status = "running"
    
    // Execute in topological order
    for _, segmentIndex := range plan.ExecutionOrder {
        segment := &plan.Segments[segmentIndex]
        plan.CurrentIndex = segmentIndex
        
        // Check dependencies
        if err := e.checkDependencies(ctx, segment, plan); err != nil {
            if e.config.ContinueOnFailure {
                segment.Status = string(SegmentStatusBlocked)
                segment.LastError = err.Error()
                continue
            }
            return fmt.Errorf("dependency check failed for segment %d: %w", segmentIndex, err)
        }
        
        // Execute segment
        if err := e.executeSegment(ctx, segment, plan); err != nil {
            if e.config.ContinueOnFailure {
                segment.Status = string(SegmentStatusFailed)
                segment.LastError = err.Error()
                e.updateSegmentStatus(ctx, segment)
                continue
            }
            plan.Status = "failed"
            return fmt.Errorf("segment %d execution failed: %w", segmentIndex, err)
        }
        
        // Summarize for next segment
        if e.config.SummarizeAfterComplete && segmentIndex < len(plan.Segments)-1 {
            if err := e.summarizeSegment(ctx, segment); err != nil {
                // Non-fatal, log and continue
                segment.LastError = "summarization failed: " + err.Error()
            }
        }
        
        e.updateSegmentStatus(ctx, segment)
    }
    
    completed := time.Now()
    plan.CompletedAt = &completed
    plan.Status = "completed"
    
    return nil
}

// checkDependencies verifies all dependencies are satisfied
func (e *SegmentExecutionEngine) checkDependencies(
    ctx context.Context,
    segment *ExecutionSegment,
    plan *ExecutionPlan,
) error {
    for _, depId := range segment.DependencyIds {
        // Parse segment index from ID
        var depIndex int
        fmt.Sscanf(depId, "segment_%d", &depIndex)
        
        depSegment := &plan.Segments[depIndex]
        status := SegmentStatus(depSegment.Status)
        
        switch status {
        case SegmentStatusCompleted, SegmentStatusSummarized:
            continue // OK
        case SegmentStatusFailed:
            return fmt.Errorf("dependency %s failed", depId)
        case SegmentStatusBlocked:
            return fmt.Errorf("dependency %s is blocked", depId)
        default:
            return fmt.Errorf("dependency %s not completed (status: %s)", depId, status)
        }
    }
    
    return nil
}

// executeSegment runs a single segment
func (e *SegmentExecutionEngine) executeSegment(
    ctx context.Context,
    segment *ExecutionSegment,
    plan *ExecutionPlan,
) error {
    segment.Status = string(SegmentStatusExecuting)
    e.updateSegmentStatus(ctx, segment)
    
    for attempt := 1; attempt <= segment.MaxAttempts; attempt++ {
        segment.Attempts = attempt
        
        // Build context with memory from previous segments
        contextReq := e.buildSegmentContext(segment, plan)
        
        assembled, err := e.contextManager.Assemble(ctx, contextReq)
        if err != nil {
            segment.LastError = "context assembly failed: " + err.Error()
            continue
        }
        
        // Execute with timeout
        execCtx, cancel := context.WithTimeout(ctx, e.config.TimeoutPerSegment)
        defer cancel()
        
        output, err := e.aiService.Generate(execCtx, assembled.Messages)
        if err != nil {
            segment.LastError = err.Error()
            if attempt < segment.MaxAttempts {
                time.Sleep(time.Duration(e.config.RetryDelaySeconds) * time.Second)
                continue
            }
            return fmt.Errorf("all %d attempts failed: %w", segment.MaxAttempts, err)
        }
        
        segment.Output = output
        segment.Status = string(SegmentStatusCompleted)
        executedAt := time.Now()
        segment.ExecutedAt = &executedAt
        
        return nil
    }
    
    return fmt.Errorf("execution failed after %d attempts", segment.MaxAttempts)
}

// buildSegmentContext creates context request with previous segment summaries
func (e *SegmentExecutionEngine) buildSegmentContext(
    segment *ExecutionSegment,
    plan *ExecutionPlan,
) AssembleRequest {
    var memoryBuilder strings.Builder
    
    // Include summaries from completed segments
    for i, seg := range plan.Segments {
        if i >= segment.SegmentIndex {
            break
        }
        if seg.SummaryForNext != nil && *seg.SummaryForNext != "" {
            memoryBuilder.WriteString(fmt.Sprintf("### Segment %d: %s\n\n", i+1, seg.Title))
            memoryBuilder.WriteString(*seg.SummaryForNext)
            memoryBuilder.WriteString("\n\n")
        }
    }
    
    return AssembleRequest{
        SystemPrompt: fmt.Sprintf(
            "You are executing segment %d of %d for this instruction. "+
            "Focus only on this segment's requirements. "+
            "Previous segment context is provided below.",
            segment.SegmentIndex+1, len(plan.Segments),
        ),
        MemoryContext: memoryBuilder.String(),
        Instruction:   segment.Content,
        UserQuery:     fmt.Sprintf("Execute segment: %s", segment.Title),
    }
}

// summarizeSegment compresses output for next segment's context
func (e *SegmentExecutionEngine) summarizeSegment(
    ctx context.Context,
    segment *ExecutionSegment,
) error {
    if e.memoryService == nil {
        return nil
    }
    
    summary, err := e.memoryService.Compress(ctx, segment.Output, 500) // 500 token target
    if err != nil {
        return err
    }
    
    segment.SummaryForNext = &summary
    segment.Status = string(SegmentStatusSummarized)
    
    return nil
}

// updateSegmentStatus persists segment state to database
func (e *SegmentExecutionEngine) updateSegmentStatus(ctx context.Context, segment *ExecutionSegment) {
    e.db.WithContext(ctx).Model(&segment.InstructionSegment).Updates(map[string]interface{}{
        "Status":         segment.Status,
        "SummaryForNext": segment.SummaryForNext,
        "ExecutedAt":     segment.ExecutedAt,
        "ErrorMessage":   segment.LastError,
    })
}

// Helper function
func indexOf(slice []int, val int) int {
    for i, v := range slice {
        if v == val {
            return i
        }
    }
    return -1
}
```

---

## 23.5 Segmentation Service Interface

### Main Service

```go
// InstructionSegmentationService is the main interface
type InstructionSegmentationService interface {
    // Analysis
    NeedsSegmentation(ctx context.Context, instruction string, maxTokens int) (bool, int, error)
    
    // Segmentation
    Segment(ctx context.Context, instructionId, content string) (*ExecutionPlan, error)
    
    // Execution
    Execute(ctx context.Context, plan *ExecutionPlan) error
    ExecuteAsync(ctx context.Context, plan *ExecutionPlan) (string, error) // Returns job ID
    
    // Status
    GetPlanStatus(ctx context.Context, instructionId string) (*ExecutionPlan, error)
    GetSegmentStatus(ctx context.Context, segmentId string) (*ExecutionSegment, error)
    
    // Control
    PausePlan(ctx context.Context, instructionId string) error
    ResumePlan(ctx context.Context, instructionId string) error
    RetrySegment(ctx context.Context, segmentId string) error
    SkipSegment(ctx context.Context, segmentId string) error
}

// InstructionSegmentationServiceImpl implements the service
type InstructionSegmentationServiceImpl struct {
    parser          *SegmentationParser
    resolver        *DependencyResolver
    engine          *SegmentExecutionEngine
    tokenCounter    TokenCounter
    db              *gorm.DB
    config          SegmentationConfig
}

// NewInstructionSegmentationService creates the service
func NewInstructionSegmentationService(
    db *gorm.DB,
    tokenCounter TokenCounter,
    contextManager ContextWindowManager,
    aiService AIService,
    memoryService MemoryCompressionService,
) *InstructionSegmentationServiceImpl {
    config := DefaultSegmentationConfig()
    
    return &InstructionSegmentationServiceImpl{
        parser:       NewSegmentationParser(tokenCounter, config),
        resolver:     NewDependencyResolver(),
        engine:       NewSegmentExecutionEngine(db, contextManager, aiService, memoryService, DefaultExecutionConfig()),
        tokenCounter: tokenCounter,
        db:           db,
        config:       config,
    }
}

// NeedsSegmentation checks if instruction exceeds context limits
func (s *InstructionSegmentationServiceImpl) NeedsSegmentation(
    ctx context.Context,
    instruction string,
    maxTokens int,
) (bool, int, error) {
    tokens, err := s.tokenCounter.Count(instruction)
    if err != nil {
        return false, 0, err
    }
    
    return tokens > maxTokens, tokens, nil
}

// Segment parses and plans execution for large instruction
func (s *InstructionSegmentationServiceImpl) Segment(
    ctx context.Context,
    instructionId string,
    content string,
) (*ExecutionPlan, error) {
    // Parse into sections
    sections, err := s.parser.Parse(ctx, content)
    if err != nil {
        return nil, fmt.Errorf("parsing failed: %w", err)
    }
    
    if len(sections) == 0 {
        return nil, fmt.Errorf("no sections detected in instruction")
    }
    
    // Resolve dependencies
    graph, err := s.resolver.Resolve(sections)
    if err != nil {
        return nil, fmt.Errorf("dependency resolution failed: %w", err)
    }
    
    // Create execution plan
    plan, err := s.engine.CreateExecutionPlan(ctx, instructionId, sections, graph)
    if err != nil {
        return nil, fmt.Errorf("plan creation failed: %w", err)
    }
    
    return plan, nil
}

// Execute runs the plan synchronously
func (s *InstructionSegmentationServiceImpl) Execute(
    ctx context.Context,
    plan *ExecutionPlan,
) error {
    return s.engine.Execute(ctx, plan)
}

// GetPlanStatus retrieves current plan state
func (s *InstructionSegmentationServiceImpl) GetPlanStatus(
    ctx context.Context,
    instructionId string,
) (*ExecutionPlan, error) {
    var segments []models.InstructionSegment
    err := s.db.WithContext(ctx).
        Where("instruction_id = ?", instructionId).
        Order("segment_index ASC").
        Find(&segments).Error
    
    if err != nil {
        return nil, err
    }
    
    execSegments := make([]ExecutionSegment, len(segments))
    for i, seg := range segments {
        execSegments[i] = ExecutionSegment{
            InstructionSegment: seg,
        }
    }
    
    // Determine overall status
    status := "completed"
    for _, seg := range execSegments {
        if seg.Status == string(SegmentStatusFailed) {
            status = "failed"
            break
        }
        if seg.Status != string(SegmentStatusCompleted) && 
           seg.Status != string(SegmentStatusSummarized) {
            status = "running"
        }
    }
    
    return &ExecutionPlan{
        InstructionId: instructionId,
        Segments:      execSegments,
        Status:        status,
    }, nil
}
```

---

## 23.6 Configuration Keys

Add to `09-seeding-configuration.md`:

```json
{
    "Key": "segmentation.enabled",
    "Value": "true",
    "Description": "Enable automatic instruction segmentation"
},
{
    "Key": "segmentation.maxTokensPerSegment",
    "Value": "4000",
    "Description": "Maximum tokens per segment"
},
{
    "Key": "segmentation.minTokensPerSegment",
    "Value": "500",
    "Description": "Minimum tokens per segment (smaller merged)"
},
{
    "Key": "segmentation.overlapTokens",
    "Value": "100",
    "Description": "Context overlap between segments"
},
{
    "Key": "segmentation.mergeSmallSections",
    "Value": "true",
    "Description": "Automatically merge small sections"
},
{
    "Key": "segmentation.preserveCodeBlocks",
    "Value": "true",
    "Description": "Avoid splitting code blocks"
},
{
    "Key": "execution.maxRetries",
    "Value": "3",
    "Description": "Maximum retry attempts per segment"
},
{
    "Key": "execution.continueOnFailure",
    "Value": "false",
    "Description": "Continue execution if segment fails"
},
{
    "Key": "execution.summarizeAfterComplete",
    "Value": "true",
    "Description": "Generate summary for next segment context"
},
{
    "Key": "execution.timeoutMinutes",
    "Value": "5",
    "Description": "Timeout per segment execution"
}
```

---

## 23.7 API Endpoints

Add to `03-api-endpoints.md`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/instructions/{id}/segment` | Segment a large instruction |
| GET | `/api/v1/instructions/{id}/segments` | List segments for instruction |
| GET | `/api/v1/instructions/{id}/segments/{segmentId}` | Get segment details |
| POST | `/api/v1/instructions/{id}/segments/execute` | Execute all segments |
| POST | `/api/v1/instructions/{id}/segments/{segmentId}/execute` | Execute single segment |
| POST | `/api/v1/instructions/{id}/segments/{segmentId}/retry` | Retry failed segment |
| POST | `/api/v1/instructions/{id}/segments/{segmentId}/skip` | Skip segment |
| GET | `/api/v1/instructions/{id}/execution-plan` | Get execution plan status |

---

## 23.8 Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 5070 | ERR_SEGMENTATION_PARSE_FAILED | Failed to parse instruction into sections |
| 5071 | ERR_SEGMENTATION_NO_SECTIONS | No logical sections detected |
| 5072 | ERR_DEPENDENCY_CYCLE | Circular dependency detected |
| 5073 | ERR_DEPENDENCY_RESOLUTION_FAILED | Could not resolve dependencies |
| 5074 | ERR_SEGMENT_EXECUTION_FAILED | Segment execution failed |
| 5075 | ERR_SEGMENT_BLOCKED | Segment blocked by failed dependency |
| 5076 | ERR_SEGMENT_TIMEOUT | Segment execution timed out |
| 5077 | ERR_PLAN_NOT_FOUND | Execution plan not found |

---

## 23.9 Unit Test Requirements

| Test Case | Priority |
|-----------|----------|
| Parser detects markdown headers | HIGH |
| Parser merges small sections | HIGH |
| Parser extracts keywords | MEDIUM |
| DependencyResolver detects dependencies | HIGH |
| DependencyResolver breaks cycles | HIGH |
| TopologicalSort returns valid order | HIGH |
| ExecutionEngine executes in order | HIGH |
| ExecutionEngine handles retries | MEDIUM |
| ExecutionEngine respects dependencies | HIGH |
| Summarization passes to next segment | MEDIUM |

---

## 23.10 Acceptance Criteria

### Segmentation Parser (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SP-001 | Parser detects markdown H1/H2/H3 headers | Critical | Header detection test |
| SP-002 | Parser detects numbered sections (1., 2.) | Critical | Numbered section test |
| SP-003 | Parser detects phase/step/stage markers | High | Phase marker test |
| SP-004 | Parser detects horizontal rules (---) | High | Rule detection test |
| SP-005 | Small sections merged below MinTokensPerSegment | High | Merge test |
| SP-006 | Merged sections don't exceed MaxTokensPerSegment | High | Max size test |
| SP-007 | Code blocks preserved intact | Medium | Code preservation test |
| SP-008 | Keywords extracted for dependency detection | High | Keyword extraction test |

### Dependency Resolution (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| DR-001 | Dependency graph built from keyword rules | Critical | Graph construction test |
| DR-002 | Strict dependencies enforced | Critical | Strict dependency test |
| DR-003 | Preferred dependencies respected when possible | High | Preferred dependency test |
| DR-004 | Circular dependencies detected | Critical | Cycle detection test |
| DR-005 | Circular dependencies broken automatically | Critical | Cycle break test |
| DR-006 | Topological sort produces valid execution order | Critical | Topo sort test |
| DR-007 | ERR_DEPENDENCY_CYCLE (5072) for unbreakable cycles | High | Error code test |

### Execution Engine (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EE-001 | Segments execute in topological order | Critical | Execution order test |
| EE-002 | Failed dependencies block dependent segments | Critical | Blocking test |
| EE-003 | Segment status updates to database | High | Status persistence test |
| EE-004 | Retry logic with configurable max retries | High | Retry test |
| EE-005 | Timeout enforced per segment | High | Timeout test |
| EE-006 | Skip allows bypassing stuck segments | Medium | Skip test |
| EE-007 | All execution states persisted | High | State persistence test |

### Memory Handoff (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MH-001 | Summary generated after each segment | Critical | Summary generation test |
| MH-002 | Summary passed to next segment as context | Critical | Context handoff test |
| MH-003 | Combined memory respects token budget | High | Token budget test |
| MH-004 | Previous segment output available to next | High | Output forwarding test |

### Plan Management (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PM-001 | CreatePlan generates execution plan | Critical | Plan creation test |
| PM-002 | GetPlanStatus returns current state | High | Status retrieval test |
| PM-003 | PausePlan stops execution | Medium | Pause test |
| PM-004 | ResumePlan continues from last segment | Medium | Resume test |
| PM-005 | AbortPlan cancels remaining segments | Medium | Abort test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | ERR_SEGMENTATION_FAILED (5070) for parse failure | Critical | Error code test |
| EH-002 | ERR_SEGMENTATION_NO_SECTIONS (5071) for empty parse | Critical | Error code test |
| EH-003 | ERR_SEGMENT_EXECUTION_FAILED (5074) for exec failure | Critical | Error code test |
| EH-004 | ERR_SEGMENT_BLOCKED (5075) for blocked segments | High | Error code test |
| EH-005 | ERR_SEGMENT_TIMEOUT (5076) for timeout | High | Error code test |
| EH-006 | All errors include segment index and title | High | Error context test |

---

## Related Specifications

- [Vector Database Plan](../09-knowledge-memory/04-vector-database-plan.md)
- [Context Window Manager](../09-knowledge-memory/06-context-window-manager.md)
- [Database Schema](../../07-database-design/01-schema.md)
- [Instruction System](./03-instruction-system.md)
- [AI Integration](./01-ai-integration.md)
