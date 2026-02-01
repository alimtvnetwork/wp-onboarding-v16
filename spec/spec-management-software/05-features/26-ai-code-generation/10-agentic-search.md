# 10. Agentic Search

**Version:** 2.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [Overview](./00-overview.md)

---

## Purpose

Define the hybrid agentic search system with multi-pass iteration, neural re-ranking, and response synthesis for AI-powered code generation and knowledge retrieval.

---

## Full Pipeline Architecture

```
┌─────────────────────────────────────────────┐
│         USER QUERY                          │
│    "Best practices for ML deployment"       │
└────────────────┬────────────────────────────┘
                 │
        ┌────────▼────────┐
        │ Query Processing │
        │ - Tokenization  │
        │ - Intent parse  │
        │ - Complexity    │
        │   classification│
        └────────┬────────┘
                 │
   ┌─────────────▼──────────────┐
   │  Model Router (Classifier)  │
   │                            │
   │ Simple? → Small model       │
   │ Complex? → GPT-5/Claude     │
   │ Coding? → Code-specialized  │
   └─────────────┬──────────────┘
                 │
    ┌────────────▼──────────────┐
    │ Hybrid Retrieval Engine    │
    │ ┌──────────┬──────────┐    │
    │ │ Sparse   │  Dense   │    │
    │ │(BM25)    │(Vector)  │    │
    │ └──────┬───┴──────┬───┘    │
    │        │          │        │
    │ ┌──────▼──────────▼───┐    │
    │ │ Neural Re-ranker    │    │
    │ │ Scores relevance    │    │
    │ │ Context matching    │    │
    │ └──────────┬──────────┘    │
    └───────────┬────────────────┘
                │
   ┌────────────▼─────────────┐
   │ Multi-Pass Iteration     │
   │                          │
   │ Pass 1: Initial search   │
   │ Pass 2: Refine gaps      │
   │ Pass 3: Verify claims    │
   │ Pass 4: Cross-check      │
   │ Pass 5: Comprehensive    │
   └────────────┬─────────────┘
                │
   ┌────────────▼─────────────┐
   │ Validation & Conflict    │
   │ Resolution               │
   │ - DeBERTa fact-check     │
   │ - 3-model voting         │
   │ - Source reliability     │
   └────────────┬─────────────┘
                │
   ┌────────────▼─────────────────┐
   │ Response Synthesis            │
   │ - MMR algorithm (0.7 weight)  │
   │ - Hierarchical structure      │
   │ - Citation injection          │
   │ - Format adaptation           │
   └────────────┬─────────────────┘
                │
        ┌───────▼────────┐
        │ FINAL ANSWER   │
        │ with citations │
        │ & sources      │
        └────────────────┘
```

---

## Search Strategies

### Hybrid Search Pipeline

```go
type SearchEngine struct {
    lexical   *LexicalSearcher
    semantic  *SemanticSearcher
    reranker  *HybridReranker
    assembler *ContextAssembler
}

type SearchQuery struct {
    Text           string   `json:"text"`
    Tags           []string `json:"tags,omitempty"`
    MaxResults     int      `json:"maxResults"`
    MinScore       float64  `json:"minScore"`
    IncludeCode    bool     `json:"includeCode"`
    IncludeDocs    bool     `json:"includeDocs"`
    TokenBudget    int      `json:"tokenBudget"`
}

type SearchResult struct {
    Id          string            `json:"id"`
    Type        ResultType        `json:"type"`
    Title       string            `json:"title"`
    Content     string            `json:"content"`
    Score       float64           `json:"score"`
    Source      string            `json:"source"`
    Metadata    map[string]string `json:"metadata"`
    Highlights  []Highlight       `json:"highlights"`
}

type ResultType string

const (
    ResultTypeCode    ResultType = "code"
    ResultTypeDoc     ResultType = "documentation"
    ResultTypeTask    ResultType = "task"
    ResultTypeSpec    ResultType = "specification"
)

func (se *SearchEngine) Search(query SearchQuery) ([]SearchResult, error) {
    var wg sync.WaitGroup
    var lexicalResults, semanticResults []SearchResult
    var lexErr, semErr error
    
    // Run lexical and semantic search in parallel
    wg.Add(2)
    
    go func() {
        defer wg.Done()
        lexicalResults, lexErr = se.lexical.Search(query)
    }()
    
    go func() {
        defer wg.Done()
        semanticResults, semErr = se.semantic.Search(query)
    }()
    
    wg.Wait()
    
    if lexErr != nil && semErr != nil {
        return nil, fmt.Errorf("both searches failed: lexical=%v, semantic=%v", lexErr, semErr)
    }
    
    // Hybrid reranking using Reciprocal Rank Fusion
    combined := se.reranker.Merge(lexicalResults, semanticResults)
    
    // Filter by minimum score
    filtered := filterByScore(combined, query.MinScore)
    
    // Limit results
    if len(filtered) > query.MaxResults {
        filtered = filtered[:query.MaxResults]
    }
    
    return filtered, nil
}
```

### Lexical Search (BM25)

```go
type LexicalSearcher struct {
    db *gorm.DB
}

func (ls *LexicalSearcher) Search(query SearchQuery) ([]SearchResult, error) {
    // SQLite FTS5 for full-text search
    var results []SearchResult
    
    sql := `
        SELECT 
            id, type, title, content, 
            bm25(search_index) as score,
            highlight(search_index, 2, '<mark>', '</mark>') as highlighted
        FROM search_index
        WHERE search_index MATCH ?
        ORDER BY score
        LIMIT ?
    `
    
    // Prepare query terms
    queryTerms := tokenize(query.Text)
    ftsQuery := strings.Join(queryTerms, " OR ")
    
    rows, err := ls.db.Raw(sql, ftsQuery, query.MaxResults*2).Rows()
    if err != nil {
        return nil, err
    }
    defer rows.Close()
    
    for rows.Next() {
        var r SearchResult
        var highlighted string
        rows.Scan(&r.Id, &r.Type, &r.Title, &r.Content, &r.Score, &highlighted)
        r.Highlights = extractHighlights(highlighted)
        results = append(results, r)
    }
    
    return results, nil
}

func tokenize(text string) []string {
    // Simple tokenization - can be enhanced
    text = strings.ToLower(text)
    re := regexp.MustCompile(`[^\w]+`)
    tokens := re.Split(text, -1)
    
    // Remove stopwords
    filtered := []string{}
    stopwords := map[string]bool{"the": true, "a": true, "an": true, "is": true}
    for _, t := range tokens {
        if len(t) > 2 && !stopwords[t] {
            filtered = append(filtered, t)
        }
    }
    
    return filtered
}
```

### Semantic Search (Vector)

```go
type SemanticSearcher struct {
    embedder  Embedder
    vectorDB  VectorStore
}

type Embedder interface {
    Embed(text string) ([]float32, error)
}

type VectorStore interface {
    Search(vector []float32, limit int) ([]VectorResult, error)
    Insert(id string, vector []float32, metadata map[string]string) error
}

func (ss *SemanticSearcher) Search(query SearchQuery) ([]SearchResult, error) {
    // Generate embedding for query
    embedding, err := ss.embedder.Embed(query.Text)
    if err != nil {
        return nil, fmt.Errorf("embedding failed: %w", err)
    }
    
    // Search vector store
    vectorResults, err := ss.vectorDB.Search(embedding, query.MaxResults*2)
    if err != nil {
        return nil, fmt.Errorf("vector search failed: %w", err)
    }
    
    // Convert to SearchResult
    results := make([]SearchResult, len(vectorResults))
    for i, vr := range vectorResults {
        results[i] = SearchResult{
            Id:       vr.Id,
            Type:     ResultType(vr.Metadata["type"]),
            Title:    vr.Metadata["title"],
            Content:  vr.Content,
            Score:    float64(vr.Similarity),
            Source:   vr.Metadata["source"],
            Metadata: vr.Metadata,
        }
    }
    
    return results, nil
}
```

### Hybrid Reranking (RRF)

```go
type HybridReranker struct {
    k float64 // RRF constant, typically 60
}

func (hr *HybridReranker) Merge(lexical, semantic []SearchResult) []SearchResult {
    // Reciprocal Rank Fusion (RRF)
    scores := make(map[string]float64)
    resultsMap := make(map[string]SearchResult)
    
    // Score lexical results
    for i, r := range lexical {
        rank := float64(i + 1)
        scores[r.Id] += 1.0 / (hr.k + rank)
        resultsMap[r.Id] = r
    }
    
    // Score semantic results
    for i, r := range semantic {
        rank := float64(i + 1)
        scores[r.Id] += 1.0 / (hr.k + rank)
        if _, exists := resultsMap[r.Id]; !exists {
            resultsMap[r.Id] = r
        }
    }
    
    // Sort by combined score
    var combined []SearchResult
    for id, result := range resultsMap {
        result.Score = scores[id]
        combined = append(combined, result)
    }
    
    sort.Slice(combined, func(i, j int) bool {
        return combined[i].Score > combined[j].Score
    })
    
    return combined
}
```

---

## Context Assembly

```go
type ContextAssembler struct {
    tokenCounter TokenCounter
}

type AssembledContext struct {
    Sections    []ContextSection `json:"sections"`
    TotalTokens int              `json:"totalTokens"`
    Citations   []Citation       `json:"citations"`
}

type ContextSection struct {
    Type    ResultType `json:"type"`
    Title   string     `json:"title"`
    Content string     `json:"content"`
    Tokens  int        `json:"tokens"`
    Source  string     `json:"source"`
}

type Citation struct {
    Index  int    `json:"index"`
    Source string `json:"source"`
    Title  string `json:"title"`
}

func (ca *ContextAssembler) Assemble(results []SearchResult, tokenBudget int) *AssembledContext {
    context := &AssembledContext{
        Sections:  []ContextSection{},
        Citations: []Citation{},
    }
    
    usedTokens := 0
    
    for i, r := range results {
        // Count tokens in content
        contentTokens := ca.tokenCounter.Count(r.Content)
        
        // Check if we have budget
        if usedTokens+contentTokens > tokenBudget {
            // Try to truncate
            remaining := tokenBudget - usedTokens
            if remaining > 100 { // Minimum useful content
                truncated := ca.tokenCounter.Truncate(r.Content, remaining)
                contentTokens = remaining
                r.Content = truncated + "..."
            } else {
                break
            }
        }
        
        section := ContextSection{
            Type:    r.Type,
            Title:   r.Title,
            Content: r.Content,
            Tokens:  contentTokens,
            Source:  r.Source,
        }
        
        context.Sections = append(context.Sections, section)
        context.Citations = append(context.Citations, Citation{
            Index:  i + 1,
            Source: r.Source,
            Title:  r.Title,
        })
        
        usedTokens += contentTokens
    }
    
    context.TotalTokens = usedTokens
    return context
}
```

---

## Indexing

### Code Indexing

```go
type CodeIndexer struct {
    db        *gorm.DB
    embedder  Embedder
    vectorDB  VectorStore
}

func (ci *CodeIndexer) IndexTask(task TempCodingTask) error {
    // Extract searchable content
    content := fmt.Sprintf("%s\n\n%s", task.Description, task.GolangCode)
    
    // Generate embedding
    embedding, err := ci.embedder.Embed(content)
    if err != nil {
        return err
    }
    
    // Store in vector DB
    metadata := map[string]string{
        "type":   "code",
        "title":  task.TaskName,
        "source": task.FilePath,
    }
    
    if err := ci.vectorDB.Insert(fmt.Sprintf("task-%d", task.Id), embedding, metadata); err != nil {
        return err
    }
    
    // Index in FTS
    ftsContent := fmt.Sprintf("%s %s %s",
        task.TaskName,
        task.Description,
        strings.Join(getTaskTags(task), " "))
    
    return ci.db.Exec(`
        INSERT INTO search_index (id, type, title, content)
        VALUES (?, 'code', ?, ?)
    `, task.Id, task.TaskName, ftsContent).Error
}
```

---

## TypeScript Types

```typescript
interface SearchQuery {
  readonly text: string;
  readonly tags: readonly string[];
  readonly maxResults: number;
  readonly minScore: number;
  readonly includeCode: boolean;
  readonly includeDocs: boolean;
  readonly tokenBudget: number;
}

enum ResultType {
  Code = "code",
  Documentation = "documentation",
  Task = "task",
  Specification = "specification",
}

interface SearchResult {
  readonly id: string;
  readonly type: ResultType;
  readonly title: string;
  readonly content: string;
  readonly score: number;
  readonly source: string;
  readonly metadata: Record<string, string>;
  readonly highlights: readonly Highlight[];
}

interface Highlight {
  readonly text: string;
  readonly start: number;
  readonly end: number;
}

interface AssembledContext {
  readonly sections: readonly ContextSection[];
  readonly totalTokens: number;
  readonly citations: readonly Citation[];
}

interface ContextSection {
  readonly type: ResultType;
  readonly title: string;
  readonly content: string;
  readonly tokens: number;
  readonly source: string;
}

interface Citation {
  readonly index: number;
  readonly source: string;
  readonly title: string;
}

enum QueryComplexity {
  Simple = "simple",
  Moderate = "moderate",
  Complex = "complex",
}

enum ValidationStatus {
  Verified = "verified",
  Unverified = "unverified",
  Conflicting = "conflicting",
}

interface QueryAnalysis {
  readonly originalQuery: string;
  readonly expandedTerms: readonly string[];
  readonly intent: string;
  readonly complexity: QueryComplexity;
  readonly suggestedModel: string;
}

interface MultiPassResult {
  readonly passNumber: number;
  readonly results: readonly SearchResult[];
  readonly gaps: readonly string[];
  readonly refinedQuery: string;
}

interface ValidationResult {
  readonly claimId: string;
  readonly status: ValidationStatus;
  readonly confidence: number;
  readonly supportingSources: readonly string[];
  readonly conflictingSources: readonly string[];
}

interface SynthesizedResponse {
  readonly content: string;
  readonly structure: ResponseStructure;
  readonly citations: readonly InlineCitation[];
  readonly confidence: number;
}

interface ResponseStructure {
  readonly sections: readonly ResponseSection[];
  readonly format: ResponseFormat;
}

enum ResponseFormat {
  Markdown = "markdown",
  JSON = "json",
  Plain = "plain",
}

interface ResponseSection {
  readonly heading: string;
  readonly content: string;
  readonly depth: number;
}

interface InlineCitation {
  readonly index: number;
  readonly source: string;
  readonly title: string;
  readonly authority: number;
  readonly position: CitationPosition;
}

interface CitationPosition {
  readonly start: number;
  readonly end: number;
}
```

---

## Query Processing

### Complexity Classification

```go
type QueryComplexity string

const (
    ComplexitySimple   QueryComplexity = "simple"
    ComplexityModerate QueryComplexity = "moderate"
    ComplexityComplex  QueryComplexity = "complex"
)

type QueryProcessor struct {
    tokenizer   Tokenizer
    classifier  ComplexityClassifier
    expander    QueryExpander
    settings    *SettingsService
}

type QueryAnalysis struct {
    OriginalQuery   string          `json:"originalQuery"`
    Tokens          []string        `json:"tokens"`
    ExpandedTerms   []string        `json:"expandedTerms"`
    Intent          string          `json:"intent"`
    Complexity      QueryComplexity `json:"complexity"`
    SuggestedModel  string          `json:"suggestedModel"`
}

func (qp *QueryProcessor) Analyze(query string) (*QueryAnalysis, error) {
    // Tokenize
    tokens := qp.tokenizer.Tokenize(query)
    
    // Classify complexity
    complexity := qp.classifier.Classify(query, tokens)
    
    // Expand query
    expandedTerms := qp.expander.Expand(query, tokens)
    
    // Determine intent
    intent := qp.classifier.DetectIntent(query)
    
    // Get complexity threshold from settings
    threshold, _ := qp.settings.GetFloat("model_routing", "complexity_threshold")
    
    // Select model based on complexity
    modelPool, _ := qp.settings.GetMap("model_routing", "model_pool")
    suggestedModel := selectModel(complexity, intent, modelPool, threshold)
    
    return &QueryAnalysis{
        OriginalQuery:  query,
        Tokens:         tokens,
        ExpandedTerms:  expandedTerms,
        Intent:         intent,
        Complexity:     complexity,
        SuggestedModel: suggestedModel,
    }, nil
}

func selectModel(complexity QueryComplexity, intent string, modelPool map[string]interface{}, threshold float64) string {
    switch intent {
    case "coding":
        return modelPool["code"].(string)
    case "creative":
        return modelPool["creative"].(string)
    }
    
    switch complexity {
    case ComplexitySimple:
        return modelPool["simple"].(string)
    case ComplexityComplex:
        return modelPool["complex"].(string)
    default:
        return modelPool["simple"].(string)
    }
}
```

---

## Multi-Pass Iteration

### Pass Strategy

| Pass | Purpose | Action |
|------|---------|--------|
| 1 | Initial search | Broad retrieval with base query |
| 2 | Refine gaps | Identify missing information, targeted queries |
| 3 | Verify claims | Cross-reference facts from multiple sources |
| 4 | Cross-check | Resolve conflicts between sources |
| 5 | Comprehensive | Final synthesis with complete context |

```go
type MultiPassSearcher struct {
    engine    *SearchEngine
    maxPasses int
    settings  *SettingsService
}

type PassResult struct {
    PassNumber   int            `json:"passNumber"`
    Results      []SearchResult `json:"results"`
    Gaps         []string       `json:"gaps"`
    RefinedQuery string         `json:"refinedQuery"`
    Coverage     float64        `json:"coverage"`
}

func (mps *MultiPassSearcher) Search(query SearchQuery) ([]PassResult, error) {
    passes := make([]PassResult, 0, mps.maxPasses)
    allResults := make(map[string]SearchResult)
    currentQuery := query.Text
    
    for i := 1; i <= mps.maxPasses; i++ {
        // Execute search
        results, err := mps.engine.Search(SearchQuery{
            Text:       currentQuery,
            Tags:       query.Tags,
            MaxResults: query.MaxResults,
            MinScore:   query.MinScore,
        })
        if err != nil {
            return nil, err
        }
        
        // Merge with existing results
        for _, r := range results {
            if _, exists := allResults[r.Id]; !exists {
                allResults[r.Id] = r
            }
        }
        
        // Analyze gaps
        gaps := mps.identifyGaps(query.Text, allResults)
        
        // Calculate coverage
        coverage := mps.calculateCoverage(query.Text, allResults)
        
        pass := PassResult{
            PassNumber:   i,
            Results:      results,
            Gaps:         gaps,
            RefinedQuery: currentQuery,
            Coverage:     coverage,
        }
        passes = append(passes, pass)
        
        // Stop if coverage is sufficient
        if coverage >= 0.9 || len(gaps) == 0 {
            break
        }
        
        // Refine query for next pass
        currentQuery = mps.refineQuery(currentQuery, gaps)
    }
    
    return passes, nil
}

func (mps *MultiPassSearcher) identifyGaps(query string, results map[string]SearchResult) []string {
    // Analyze what aspects of the query are not covered
    gaps := []string{}
    
    // Extract key concepts from query
    concepts := extractConcepts(query)
    
    // Check coverage for each concept
    for _, concept := range concepts {
        found := false
        for _, r := range results {
            if strings.Contains(strings.ToLower(r.Content), strings.ToLower(concept)) {
                found = true
                break
            }
        }
        if !found {
            gaps = append(gaps, concept)
        }
    }
    
    return gaps
}

func (mps *MultiPassSearcher) refineQuery(original string, gaps []string) string {
    if len(gaps) == 0 {
        return original
    }
    
    // Add gap terms to query
    return original + " " + strings.Join(gaps[:min(3, len(gaps))], " ")
}
```

---

## Validation & Conflict Resolution

### Fact Checking Pipeline

```go
type ValidationEngine struct {
    factChecker  FactChecker
    votingSystem *ModelVotingSystem
    settings     *SettingsService
}

type ValidationStatus string

const (
    StatusVerified    ValidationStatus = "verified"
    StatusUnverified  ValidationStatus = "unverified"
    StatusConflicting ValidationStatus = "conflicting"
)

type ValidationResult struct {
    ClaimId           string           `json:"claimId"`
    Claim             string           `json:"claim"`
    Status            ValidationStatus `json:"status"`
    Confidence        float64          `json:"confidence"`
    SupportingSources []string         `json:"supportingSources"`
    ConflictingSources []string        `json:"conflictingSources"`
}

func (ve *ValidationEngine) Validate(claims []string, sources []SearchResult) ([]ValidationResult, error) {
    results := make([]ValidationResult, len(claims))
    
    for i, claim := range claims {
        // Gather source evidence
        supporting := []string{}
        conflicting := []string{}
        
        for _, source := range sources {
            evidence := ve.factChecker.CheckClaim(claim, source.Content)
            if evidence.Supports {
                supporting = append(supporting, source.Source)
            } else if evidence.Contradicts {
                conflicting = append(conflicting, source.Source)
            }
        }
        
        // 3-model voting for controversial claims
        var status ValidationStatus
        var confidence float64
        
        if len(conflicting) > 0 {
            // Use 3-model voting
            voteResult := ve.votingSystem.Vote(claim, sources)
            status = voteResult.Status
            confidence = voteResult.Confidence
        } else if len(supporting) >= 2 {
            status = StatusVerified
            confidence = float64(len(supporting)) / float64(len(sources))
        } else {
            status = StatusUnverified
            confidence = 0.5
        }
        
        results[i] = ValidationResult{
            ClaimId:           fmt.Sprintf("claim-%d", i),
            Claim:             claim,
            Status:            status,
            Confidence:        confidence,
            SupportingSources: supporting,
            ConflictingSources: conflicting,
        }
    }
    
    return results, nil
}

type ModelVotingSystem struct {
    models []string // 3 models for voting
}

type VoteResult struct {
    Status     ValidationStatus
    Confidence float64
    Votes      map[string]bool
}

func (mvs *ModelVotingSystem) Vote(claim string, sources []SearchResult) VoteResult {
    votes := make(map[string]bool)
    supporting := 0
    
    // Each model votes independently
    for _, model := range mvs.models {
        vote := mvs.getModelVote(model, claim, sources)
        votes[model] = vote
        if vote {
            supporting++
        }
    }
    
    // Majority wins
    status := StatusUnverified
    confidence := float64(supporting) / float64(len(mvs.models))
    
    if supporting >= 2 {
        status = StatusVerified
    } else if supporting == 1 {
        status = StatusConflicting
    }
    
    return VoteResult{
        Status:     status,
        Confidence: confidence,
        Votes:      votes,
    }
}
```

---

## Response Synthesis

### MMR Algorithm

Maximal Marginal Relevance (MMR) balances relevance with diversity:

```go
type ResponseSynthesizer struct {
    mmrWeight   float64 // Default 0.7 from settings
    settings    *SettingsService
}

type SynthesizedResponse struct {
    Content    string            `json:"content"`
    Structure  ResponseStructure `json:"structure"`
    Citations  []InlineCitation  `json:"citations"`
    Confidence float64           `json:"confidence"`
}

type ResponseStructure struct {
    Sections []ResponseSection `json:"sections"`
    Format   string            `json:"format"`
}

type ResponseSection struct {
    Heading string `json:"heading"`
    Content string `json:"content"`
    Depth   int    `json:"depth"`
}

type InlineCitation struct {
    Index     int     `json:"index"`
    Source    string  `json:"source"`
    Title     string  `json:"title"`
    Authority float64 `json:"authority"`
    StartPos  int     `json:"startPos"`
    EndPos    int     `json:"endPos"`
}

func (rs *ResponseSynthesizer) Synthesize(
    results []SearchResult,
    validations []ValidationResult,
) (*SynthesizedResponse, error) {
    // Get MMR weight from settings
    mmrWeight, _ := rs.settings.GetFloat("model_routing", "mmr_weight")
    if mmrWeight == 0 {
        mmrWeight = 0.7
    }
    
    // Apply MMR to select diverse, relevant content
    selected := rs.applyMMR(results, mmrWeight)
    
    // Build hierarchical structure
    structure := rs.buildStructure(selected)
    
    // Inject citations
    content, citations := rs.injectCitations(structure, selected)
    
    // Calculate overall confidence
    confidence := rs.calculateConfidence(validations)
    
    return &SynthesizedResponse{
        Content:    content,
        Structure:  structure,
        Citations:  citations,
        Confidence: confidence,
    }, nil
}

func (rs *ResponseSynthesizer) applyMMR(results []SearchResult, lambda float64) []SearchResult {
    if len(results) == 0 {
        return results
    }
    
    selected := []SearchResult{results[0]}
    remaining := results[1:]
    
    for len(selected) < len(results) && len(remaining) > 0 {
        bestScore := -1.0
        bestIdx := 0
        
        for i, candidate := range remaining {
            // Relevance score (original score)
            relevance := candidate.Score
            
            // Max similarity to already selected
            maxSim := 0.0
            for _, s := range selected {
                sim := cosineSimilarity(candidate.Content, s.Content)
                if sim > maxSim {
                    maxSim = sim
                }
            }
            
            // MMR score
            mmrScore := lambda*relevance - (1-lambda)*maxSim
            
            if mmrScore > bestScore {
                bestScore = mmrScore
                bestIdx = i
            }
        }
        
        selected = append(selected, remaining[bestIdx])
        remaining = append(remaining[:bestIdx], remaining[bestIdx+1:]...)
    }
    
    return selected
}

func (rs *ResponseSynthesizer) buildStructure(results []SearchResult) ResponseStructure {
    sections := []ResponseSection{}
    
    // Group by topic/type
    grouped := groupByTopic(results)
    
    for topic, items := range grouped {
        content := ""
        for _, item := range items {
            content += item.Content + "\n\n"
        }
        
        sections = append(sections, ResponseSection{
            Heading: topic,
            Content: strings.TrimSpace(content),
            Depth:   1,
        })
    }
    
    return ResponseStructure{
        Sections: sections,
        Format:   "markdown",
    }
}
```

---

## Neural Re-ranker

```go
type NeuralReranker struct {
    model     string // e.g., "cross-encoder/ms-marco-MiniLM-L-6-v2"
    batchSize int
}

type RerankScore struct {
    ResultId string  `json:"resultId"`
    Score    float64 `json:"score"`
    Rank     int     `json:"rank"`
}

func (nr *NeuralReranker) Rerank(query string, results []SearchResult) []SearchResult {
    if len(results) == 0 {
        return results
    }
    
    // Score each result against query using cross-encoder
    scores := make([]RerankScore, len(results))
    
    for i, result := range results {
        // Cross-encoder scoring
        score := nr.scoreWithCrossEncoder(query, result.Content)
        scores[i] = RerankScore{
            ResultId: result.Id,
            Score:    score,
            Rank:     0,
        }
    }
    
    // Sort by score
    sort.Slice(scores, func(i, j int) bool {
        return scores[i].Score > scores[j].Score
    })
    
    // Assign ranks
    for i := range scores {
        scores[i].Rank = i + 1
    }
    
    // Reorder results
    scoreMap := make(map[string]float64)
    for _, s := range scores {
        scoreMap[s.ResultId] = s.Score
    }
    
    sort.Slice(results, func(i, j int) bool {
        return scoreMap[results[i].Id] > scoreMap[results[j].Id]
    })
    
    // Update scores
    for i := range results {
        results[i].Score = scoreMap[results[i].Id]
    }
    
    return results
}
```

---

## Configuration (Seedable)

Uses the [Seedable Configuration Pattern](../../04-coding-guidelines/05-seedable-config-pattern.md).

### File: `config/seeding-agentic-search.json`

```json
{
  "version": "1.0.0",
  "category": "agentic_search",
  "values": {
    "lexical": {
      "enabled": true,
      "weight": 0.4
    },
    "semantic": {
      "enabled": true,
      "weight": 0.6,
      "embeddingModel": "text-embedding-3-small",
      "dimensions": 1536
    },
    "reranking": {
      "method": "rrf",
      "k": 60,
      "neuralModel": "cross-encoder/ms-marco-MiniLM-L-6-v2"
    },
    "multiPass": {
      "maxPasses": 5,
      "coverageThreshold": 0.9
    },
    "validation": {
      "votingModels": ["gpt-5", "claude-opus", "llama-3-70b"],
      "minConfidence": 0.7
    },
    "synthesis": {
      "mmrWeight": 0.7,
      "format": "markdown"
    },
    "tokenBudget": 4000,
    "maxResults": 10,
    "minScore": 0.3
  }
}
```

---

## Related

- [Multi-Model Executor](./09-multi-model-executor.md) - Model routing
- [Seedable Config Pattern](../../04-coding-guidelines/05-seedable-config-pattern.md) - Configuration management
- [GSearch CLI](../22-golang-search-cli/00-overview.md) - Web search with authority scoring

---

## Configuration

```json
{
  "agenticSearch": {
    "lexical": {
      "enabled": true,
      "weight": 0.4
    },
    "semantic": {
      "enabled": true,
      "weight": 0.6,
      "embeddingModel": "text-embedding-3-small",
      "dimensions": 1536
    },
    "reranking": {
      "method": "rrf",
      "k": 60
    },
    "tokenBudget": 4000,
    "maxResults": 10,
    "minScore": 0.3
  }
}
```

---

## Related Specs

- [05-task-matcher.md](./05-task-matcher.md) — Uses search for reuse matching
- [09-knowledge-memory](../09-knowledge-memory/00-overview.md) — RAG system integration
- [03-code-generator.md](./03-code-generator.md) — Context for generation
