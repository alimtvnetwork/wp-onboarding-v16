# Phase 6.3: RAG Context Integration

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Cross-Project Memory](./06-cross-project-memory.md)

---

## Overview

Integration of shared memories into AI context using Retrieval-Augmented Generation (RAG) with vector embeddings, semantic search, and intelligent context assembly.

---

## 1. RAG Architecture

### 1.1 Context Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        RAG CONTEXT INTEGRATION                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  USER QUERY                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  "Create an API endpoint for user authentication"                   │   │
│  └──────────────────────────────┬──────────────────────────────────────┘   │
│                                 │                                           │
│                                 ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                    QUERY EMBEDDING                                   │   │
│  │  [0.123, -0.456, 0.789, ...]  ← Embed query text                    │   │
│  └──────────────────────────────┬──────────────────────────────────────┘   │
│                                 │                                           │
│                                 ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                    VECTOR SEARCH                                     │   │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                  │   │
│  │  │ Local Docs  │  │Shared Specs │  │  Memories   │                  │   │
│  │  │  (project)  │  │ (cross-ref) │  │ (knowledge) │                  │   │
│  │  └─────────────┘  └─────────────┘  └─────────────┘                  │   │
│  │        │               │                 │                          │   │
│  │        └───────────────┴─────────────────┘                          │   │
│  │                        │                                             │   │
│  │               Top-K Similar Chunks                                   │   │
│  └──────────────────────────────┬──────────────────────────────────────┘   │
│                                 │                                           │
│                                 ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                    CONTEXT ASSEMBLY                                  │   │
│  │  • Rank by relevance score                                          │   │
│  │  • Apply token budget                                                │   │
│  │  • Add source attribution                                           │   │
│  │  • Include shared memory metadata                                    │   │
│  └──────────────────────────────┬──────────────────────────────────────┘   │
│                                 │                                           │
│                                 ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                    AI PROMPT                                         │   │
│  │  System: You are a spec writing assistant...                        │   │
│  │  Context: [Assembled relevant documents]                            │   │
│  │  User: Create an API endpoint for user authentication               │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Data Models

```typescript
// types/rag.ts

/**
 * Embedded document chunk for vector storage
 */
export interface EmbeddedChunk {
  id: string;
  projectId: string;
  sourceId: string; // File path or memory ID
  sourceType: 'local' | 'shared';
  
  // Content
  content: string;
  chunkIndex: number;
  totalChunks: number;
  
  // Metadata
  title?: string;
  path?: string;
  shareId?: string; // If from shared memory
  sourceProjectId?: string;
  sourceProjectName?: string;
  
  // Embedding
  embedding: number[];
  embeddingModel: string;
  
  // Timestamps
  createdAt: Date;
  updatedAt: Date;
}

/**
 * Search result with relevance score
 */
export interface SearchResult {
  chunk: EmbeddedChunk;
  score: number; // Cosine similarity
  highlights?: string[]; // Matching text snippets
}

/**
 * Assembled context for AI prompt
 */
export interface AssembledContext {
  items: ContextItem[];
  totalTokens: number;
  truncated: boolean;
  sources: ContextSource[];
}

export interface ContextItem {
  content: string;
  source: ContextSource;
  relevanceScore: number;
  tokenCount: number;
}

export interface ContextSource {
  type: 'local' | 'shared';
  path: string;
  name: string;
  projectId?: string;
  projectName?: string;
  shareId?: string;
}

/**
 * RAG configuration options
 */
export interface RAGConfig {
  // Search settings
  topK: number;
  minScore: number;
  maxTokens: number;
  
  // Source weighting
  localWeight: number;
  sharedWeight: number;
  memoryWeight: number;
  
  // Chunking settings
  chunkSize: number;
  chunkOverlap: number;
  
  // Embedding model
  embeddingModel: 'text-embedding-3-small' | 'text-embedding-3-large';
}

export const DEFAULT_RAG_CONFIG: RAGConfig = {
  topK: 10,
  minScore: 0.3,
  maxTokens: 4000,
  localWeight: 1.0,
  sharedWeight: 0.9,
  memoryWeight: 0.8,
  chunkSize: 512,
  chunkOverlap: 50,
  embeddingModel: 'text-embedding-3-small',
};
```

---

## 2. Embedding Service

### 2.1 Backend Embedding Service

```go
// internal/rag/embedding_service.go

package rag

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"

	"specmgmt/internal/db"
)

type EmbeddingService struct {
	db           *db.DB
	apiKey       string
	model        string
	chunkSize    int
	chunkOverlap int
}

func NewEmbeddingService(db *db.DB, apiKey string) *EmbeddingService {
	return &EmbeddingService{
		db:           db,
		apiKey:       apiKey,
		model:        "text-embedding-3-small",
		chunkSize:    512,
		chunkOverlap: 50,
	}
}

// EmbedDocument chunks and embeds a document
func (s *EmbeddingService) EmbedDocument(ctx context.Context, doc Document) ([]EmbeddedChunk, error) {
	// Chunk the content
	chunks := s.chunkText(doc.Content)
	
	// Generate embeddings for all chunks
	embeddings, err := s.generateEmbeddings(ctx, chunks)
	if err != nil {
		return nil, fmt.Errorf("failed to generate embeddings: %w", err)
	}
	
	// Create chunk records
	result := make([]EmbeddedChunk, len(chunks))
	for i, chunk := range chunks {
		result[i] = EmbeddedChunk{
			ID:           generateID(),
			ProjectID:    doc.ProjectID,
			SourceID:     doc.SourceID,
			SourceType:   doc.SourceType,
			Content:      chunk,
			ChunkIndex:   i,
			TotalChunks:  len(chunks),
			Title:        doc.Title,
			Path:         doc.Path,
			ShareID:      doc.ShareID,
			Embedding:    embeddings[i],
			EmbeddingModel: s.model,
			CreatedAt:    time.Now(),
			UpdatedAt:    time.Now(),
		}
	}
	
	// Store in database
	if err := s.storeChunks(ctx, result); err != nil {
		return nil, fmt.Errorf("failed to store chunks: %w", err)
	}
	
	return result, nil
}

// chunkText splits text into overlapping chunks
func (s *EmbeddingService) chunkText(text string) []string {
	words := strings.Fields(text)
	if len(words) <= s.chunkSize {
		return []string{text}
	}
	
	var chunks []string
	for i := 0; i < len(words); i += s.chunkSize - s.chunkOverlap {
		end := i + s.chunkSize
		if end > len(words) {
			end = len(words)
		}
		
		chunk := strings.Join(words[i:end], " ")
		chunks = append(chunks, chunk)
		
		if end == len(words) {
			break
		}
	}
	
	return chunks
}

// generateEmbeddings calls OpenAI API for embeddings
func (s *EmbeddingService) generateEmbeddings(ctx context.Context, texts []string) ([][]float64, error) {
	reqBody, _ := json.Marshal(map[string]interface{}{
		"model": s.model,
		"input": texts,
	})
	
	req, _ := http.NewRequestWithContext(ctx, "POST", 
		"https://api.openai.com/v1/embeddings", bytes.NewReader(reqBody))
	req.Header.Set("Authorization", "Bearer "+s.apiKey)
	req.Header.Set("Content-Type", "application/json")
	
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	
	body, _ := io.ReadAll(resp.Body)
	
	var result struct {
		Data []struct {
			Embedding []float64 `json:"embedding"`
		} `json:"data"`
	}
	
	if err := json.Unmarshal(body, &result); err != nil {
		return nil, err
	}
	
	embeddings := make([][]float64, len(result.Data))
	for i, d := range result.Data {
		embeddings[i] = d.Embedding
	}
	
	return embeddings, nil
}

// EmbedQuery embeds a single query for search
func (s *EmbeddingService) EmbedQuery(ctx context.Context, query string) ([]float64, error) {
	embeddings, err := s.generateEmbeddings(ctx, []string{query})
	if err != nil {
		return nil, err
	}
	if len(embeddings) == 0 {
		return nil, fmt.Errorf("no embedding returned")
	}
	return embeddings[0], nil
}

func (s *EmbeddingService) storeChunks(ctx context.Context, chunks []EmbeddedChunk) error {
	for _, chunk := range chunks {
		embeddingJSON, _ := json.Marshal(chunk.Embedding)
		
		_, err := s.db.ExecContext(ctx, `
			INSERT OR REPLACE INTO embedded_chunks 
			(id, project_id, source_id, source_type, content, chunk_index, total_chunks,
			 title, path, share_id, embedding, embedding_model, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		`, chunk.ID, chunk.ProjectID, chunk.SourceID, chunk.SourceType, chunk.Content,
			chunk.ChunkIndex, chunk.TotalChunks, chunk.Title, chunk.Path, chunk.ShareID,
			string(embeddingJSON), chunk.EmbeddingModel, chunk.CreatedAt, chunk.UpdatedAt)
		
		if err != nil {
			return err
		}
	}
	return nil
}
```

### 2.2 Vector Search Service

```go
// internal/rag/search_service.go

package rag

import (
	"context"
	"encoding/json"
	"math"
	"sort"
)

type SearchService struct {
	db        *db.DB
	embedding *EmbeddingService
}

func NewSearchService(db *db.DB, embedding *EmbeddingService) *SearchService {
	return &SearchService{db: db, embedding: embedding}
}

type SearchOptions struct {
	ProjectID   string
	TopK        int
	MinScore    float64
	IncludeShared bool
	SourceTypes   []string
}

// Search finds relevant chunks for a query
func (s *SearchService) Search(ctx context.Context, query string, opts SearchOptions) ([]SearchResult, error) {
	// Embed the query
	queryEmbedding, err := s.embedding.EmbedQuery(ctx, query)
	if err != nil {
		return nil, err
	}
	
	// Get all candidate chunks
	chunks, err := s.getCandidateChunks(ctx, opts)
	if err != nil {
		return nil, err
	}
	
	// Calculate similarity scores
	results := make([]SearchResult, 0)
	for _, chunk := range chunks {
		score := s.cosineSimilarity(queryEmbedding, chunk.Embedding)
		
		if score >= opts.MinScore {
			results = append(results, SearchResult{
				Chunk: chunk,
				Score: score,
			})
		}
	}
	
	// Sort by score descending
	sort.Slice(results, func(i, j int) bool {
		return results[i].Score > results[j].Score
	})
	
	// Return top K
	if len(results) > opts.TopK {
		results = results[:opts.TopK]
	}
	
	return results, nil
}

func (s *SearchService) getCandidateChunks(ctx context.Context, opts SearchOptions) ([]EmbeddedChunk, error) {
	query := `
		SELECT id, project_id, source_id, source_type, content, chunk_index, total_chunks,
			   title, path, share_id, embedding, embedding_model, created_at, updated_at
		FROM embedded_chunks
		WHERE project_id = ?
	`
	args := []interface{}{opts.ProjectID}
	
	// Include shared chunks from active shares
	if opts.IncludeShared {
		query = `
			SELECT ec.id, ec.project_id, ec.source_id, ec.source_type, ec.content, 
				   ec.chunk_index, ec.total_chunks, ec.title, ec.path, ec.share_id,
				   ec.embedding, ec.embedding_model, ec.created_at, ec.updated_at
			FROM embedded_chunks ec
			WHERE ec.project_id = ?
			
			UNION ALL
			
			SELECT ec.id, ec.project_id, ec.source_id, 'shared' as source_type, ec.content,
				   ec.chunk_index, ec.total_chunks, ec.title, ec.path, ms.id as share_id,
				   ec.embedding, ec.embedding_model, ec.created_at, ec.updated_at
			FROM embedded_chunks ec
			JOIN memory_shares ms ON ec.project_id = ms.source_project_id 
				AND ec.source_id = ms.resource_path
			WHERE ms.target_project_id = ? 
				AND ms.status = 'active'
		`
		args = append(args, opts.ProjectID)
	}
	
	rows, err := s.db.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	
	var chunks []EmbeddedChunk
	for rows.Next() {
		var chunk EmbeddedChunk
		var embeddingJSON string
		
		err := rows.Scan(
			&chunk.ID, &chunk.ProjectID, &chunk.SourceID, &chunk.SourceType,
			&chunk.Content, &chunk.ChunkIndex, &chunk.TotalChunks,
			&chunk.Title, &chunk.Path, &chunk.ShareID,
			&embeddingJSON, &chunk.EmbeddingModel, &chunk.CreatedAt, &chunk.UpdatedAt,
		)
		if err != nil {
			continue
		}
		
		json.Unmarshal([]byte(embeddingJSON), &chunk.Embedding)
		chunks = append(chunks, chunk)
	}
	
	return chunks, nil
}

func (s *SearchService) cosineSimilarity(a, b []float64) float64 {
	if len(a) != len(b) {
		return 0
	}
	
	var dotProduct, normA, normB float64
	for i := range a {
		dotProduct += a[i] * b[i]
		normA += a[i] * a[i]
		normB += b[i] * b[i]
	}
	
	if normA == 0 || normB == 0 {
		return 0
	}
	
	return dotProduct / (math.Sqrt(normA) * math.Sqrt(normB))
}
```

---

## 3. Context Assembly

### 3.1 Context Assembler

```go
// internal/rag/context_assembler.go

package rag

import (
	"context"
	"fmt"
	"strings"
)

type ContextAssembler struct {
	search *SearchService
	config RAGConfig
}

func NewContextAssembler(search *SearchService, config RAGConfig) *ContextAssembler {
	return &ContextAssembler{search: search, config: config}
}

// AssembleContext builds the context for an AI prompt
func (a *ContextAssembler) AssembleContext(
	ctx context.Context,
	projectID string,
	query string,
) (*AssembledContext, error) {
	// Search for relevant chunks
	results, err := a.search.Search(ctx, query, SearchOptions{
		ProjectID:     projectID,
		TopK:          a.config.TopK * 2, // Get extra for filtering
		MinScore:      a.config.MinScore,
		IncludeShared: true,
	})
	if err != nil {
		return nil, fmt.Errorf("search failed: %w", err)
	}
	
	// Apply source weighting
	for i := range results {
		weight := a.getSourceWeight(results[i].Chunk.SourceType)
		results[i].Score *= weight
	}
	
	// Re-sort by weighted score
	sort.Slice(results, func(i, j int) bool {
		return results[i].Score > results[j].Score
	})
	
	// Assemble within token budget
	assembled := &AssembledContext{
		Items:   make([]ContextItem, 0),
		Sources: make([]ContextSource, 0),
	}
	
	seenSources := make(map[string]bool)
	
	for _, result := range results {
		tokens := a.estimateTokens(result.Chunk.Content)
		
		if assembled.TotalTokens+tokens > a.config.MaxTokens {
			assembled.Truncated = true
			break
		}
		
		source := ContextSource{
			Type:        result.Chunk.SourceType,
			Path:        result.Chunk.Path,
			Name:        result.Chunk.Title,
			ProjectID:   result.Chunk.ProjectID,
			ShareID:     result.Chunk.ShareID,
		}
		
		// Add source attribution
		sourceKey := fmt.Sprintf("%s:%s", result.Chunk.SourceType, result.Chunk.SourceID)
		if !seenSources[sourceKey] {
			seenSources[sourceKey] = true
			assembled.Sources = append(assembled.Sources, source)
		}
		
		assembled.Items = append(assembled.Items, ContextItem{
			Content:       result.Chunk.Content,
			Source:        source,
			RelevanceScore: result.Score,
			TokenCount:    tokens,
		})
		
		assembled.TotalTokens += tokens
	}
	
	return assembled, nil
}

// FormatForPrompt formats assembled context for inclusion in prompt
func (a *ContextAssembler) FormatForPrompt(assembled *AssembledContext) string {
	var sb strings.Builder
	
	sb.WriteString("## Relevant Context\n\n")
	
	for _, item := range assembled.Items {
		// Source attribution
		sourceLabel := item.Source.Name
		if item.Source.Type == "shared" {
			sourceLabel += fmt.Sprintf(" (from %s)", item.Source.ProjectName)
		}
		
		sb.WriteString(fmt.Sprintf("### %s\n", sourceLabel))
		sb.WriteString(fmt.Sprintf("*Path: %s*\n\n", item.Source.Path))
		sb.WriteString(item.Content)
		sb.WriteString("\n\n---\n\n")
	}
	
	if assembled.Truncated {
		sb.WriteString("*Note: Additional relevant content was truncated due to context limits.*\n")
	}
	
	return sb.String()
}

func (a *ContextAssembler) getSourceWeight(sourceType string) float64 {
	switch sourceType {
	case "local":
		return a.config.LocalWeight
	case "shared":
		return a.config.SharedWeight
	case "memory":
		return a.config.MemoryWeight
	default:
		return 1.0
	}
}

func (a *ContextAssembler) estimateTokens(text string) int {
	// Rough estimate: ~4 chars per token
	return len(text) / 4
}
```

### 3.2 Frontend Integration

```typescript
// hooks/useRAGContext.ts

import { useQuery } from '@tanstack/react-query';
import type { AssembledContext, RAGConfig } from '@/types/rag';

interface UseRAGContextOptions {
  projectId: string;
  query: string;
  enabled?: boolean;
  config?: Partial<RAGConfig>;
}

export function useRAGContext({
  projectId,
  query,
  enabled = true,
  config,
}: UseRAGContextOptions) {
  return useQuery<AssembledContext>({
    queryKey: ['rag-context', projectId, query, config],
    queryFn: async () => {
      const params = new URLSearchParams({
        query,
        ...config,
      });
      
      const res = await fetch(
        `/api/v1/projects/${projectId}/rag/context?${params}`
      );
      
      if (!res.ok) throw new Error('Failed to fetch context');
      return res.json();
    },
    enabled: enabled && query.length > 3,
    staleTime: 30000, // 30 seconds
  });
}
```

### 3.3 Context Preview Component

```typescript
// components/ai/ContextPreview.tsx

import { useState } from 'react';
import { ChevronDown, ChevronUp, FileText, Share2, Brain, ExternalLink } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import type { AssembledContext, ContextItem } from '@/types/rag';

interface ContextPreviewProps {
  context: AssembledContext;
  maxTokens: number;
  className?: string;
}

export function ContextPreview({
  context,
  maxTokens,
  className,
}: ContextPreviewProps) {
  const [expanded, setExpanded] = useState(false);
  
  const tokenUsage = (context.totalTokens / maxTokens) * 100;
  
  const sourceIcons = {
    local: FileText,
    shared: Share2,
    memory: Brain,
  };
  
  return (
    <div className={cn('border rounded-lg bg-muted/30', className)}>
      <Collapsible open={expanded} onOpenChange={setExpanded}>
        <CollapsibleTrigger asChild>
          <Button
            variant="ghost"
            className="w-full justify-between px-4 py-3 h-auto"
          >
            <div className="flex items-center gap-3">
              <span className="font-medium">Context</span>
              <Badge variant="secondary">
                {context.items.length} sources
              </Badge>
              {context.truncated && (
                <Badge variant="outline" className="text-yellow-600">
                  Truncated
                </Badge>
              )}
            </div>
            
            <div className="flex items-center gap-4">
              <div className="w-32">
                <Progress value={tokenUsage} className="h-2" />
                <p className="text-xs text-muted-foreground mt-1">
                  {context.totalTokens} / {maxTokens} tokens
                </p>
              </div>
              {expanded ? (
                <ChevronUp className="h-4 w-4" />
              ) : (
                <ChevronDown className="h-4 w-4" />
              )}
            </div>
          </Button>
        </CollapsibleTrigger>
        
        <CollapsibleContent>
          <div className="border-t">
            {/* Source summary */}
            <div className="flex flex-wrap gap-2 p-3 border-b">
              {context.sources.map((source, i) => {
                const Icon = sourceIcons[source.type] || FileText;
                return (
                  <Badge key={i} variant="outline" className="gap-1.5">
                    <Icon className="h-3 w-3" />
                    <span className="truncate max-w-32">{source.name}</span>
                    {source.type === 'shared' && (
                      <ExternalLink className="h-3 w-3 opacity-50" />
                    )}
                  </Badge>
                );
              })}
            </div>
            
            {/* Content preview */}
            <ScrollArea className="h-64">
              <div className="p-4 space-y-4">
                {context.items.map((item, i) => (
                  <ContextItemCard key={i} item={item} />
                ))}
              </div>
            </ScrollArea>
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>
  );
}

function ContextItemCard({ item }: { item: ContextItem }) {
  const [expanded, setExpanded] = useState(false);
  
  return (
    <div className="border rounded-lg p-3">
      <div className="flex items-start justify-between mb-2">
        <div>
          <p className="font-medium text-sm">{item.source.name}</p>
          <p className="text-xs text-muted-foreground">{item.source.path}</p>
        </div>
        <div className="text-right">
          <Badge
            variant={item.source.type === 'shared' ? 'secondary' : 'outline'}
          >
            {item.source.type}
          </Badge>
          <p className="text-xs text-muted-foreground mt-1">
            {(item.relevanceScore * 100).toFixed(0)}% match
          </p>
        </div>
      </div>
      
      <div className={cn(
        'text-sm text-muted-foreground',
        !expanded && 'line-clamp-3'
      )}>
        {item.content}
      </div>
      
      {item.content.length > 200 && (
        <Button
          variant="ghost"
          size="sm"
          className="mt-2 h-6 text-xs"
          onClick={() => setExpanded(!expanded)}
        >
          {expanded ? 'Show less' : 'Show more'}
        </Button>
      )}
    </div>
  );
}
```

---

## 4. Embedding Pipeline

### 4.1 Background Indexing Worker

```go
// internal/rag/indexing_worker.go

package rag

import (
	"context"
	"fmt"
	"time"
)

type IndexingWorker struct {
	embedding *EmbeddingService
	files     *files.Service
	shares    *ShareService
	db        *db.DB
	interval  time.Duration
}

func NewIndexingWorker(
	embedding *EmbeddingService,
	files *files.Service,
	shares *ShareService,
	db *db.DB,
) *IndexingWorker {
	return &IndexingWorker{
		embedding: embedding,
		files:     files,
		shares:    shares,
		db:        db,
		interval:  5 * time.Minute,
	}
}

// Start begins the indexing worker
func (w *IndexingWorker) Start(ctx context.Context) {
	ticker := time.NewTicker(w.interval)
	defer ticker.Stop()
	
	// Initial indexing
	w.runIndexingCycle(ctx)
	
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			w.runIndexingCycle(ctx)
		}
	}
}

func (w *IndexingWorker) runIndexingCycle(ctx context.Context) {
	// Get files that need indexing
	files, err := w.getFilesNeedingIndexing(ctx)
	if err != nil {
		fmt.Printf("Failed to get files for indexing: %v\n", err)
		return
	}
	
	for _, file := range files {
		if err := w.indexFile(ctx, file); err != nil {
			fmt.Printf("Failed to index %s: %v\n", file.Path, err)
		}
	}
	
	// Also index shared memories
	w.indexSharedMemories(ctx)
}

func (w *IndexingWorker) indexFile(ctx context.Context, file FileInfo) error {
	content, err := w.files.GetContent(ctx, file.ProjectID, file.Path)
	if err != nil {
		return err
	}
	
	doc := Document{
		ProjectID:  file.ProjectID,
		SourceID:   file.Path,
		SourceType: "local",
		Title:      file.Name,
		Path:       file.Path,
		Content:    content,
	}
	
	_, err = w.embedding.EmbedDocument(ctx, doc)
	if err != nil {
		return err
	}
	
	// Mark as indexed
	return w.markIndexed(ctx, file.ProjectID, file.Path, file.Hash)
}

func (w *IndexingWorker) indexSharedMemories(ctx context.Context) {
	// Get all active shares with content that needs indexing
	shares, err := w.shares.GetActiveSharesNeedingIndexing(ctx)
	if err != nil {
		return
	}
	
	for _, share := range shares {
		content, err := w.shares.GetShareContent(ctx, share.ID, "system")
		if err != nil {
			continue
		}
		
		doc := Document{
			ProjectID:   share.TargetProjectID,
			SourceID:    share.ResourcePath,
			SourceType:  "shared",
			Title:       share.ResourceName,
			Path:        share.ResourcePath,
			Content:     content,
			ShareID:     share.ID,
		}
		
		w.embedding.EmbedDocument(ctx, doc)
	}
}

func (w *IndexingWorker) getFilesNeedingIndexing(ctx context.Context) ([]FileInfo, error) {
	// Find files where hash changed or not indexed
	rows, err := w.db.QueryContext(ctx, `
		SELECT f.project_id, f.path, f.name, f.hash
		FROM project_files f
		LEFT JOIN indexed_files idx ON f.project_id = idx.project_id AND f.path = idx.path
		WHERE f.type = 'spec'
		AND (idx.hash IS NULL OR idx.hash != f.hash)
		LIMIT 100
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	
	var files []FileInfo
	for rows.Next() {
		var f FileInfo
		rows.Scan(&f.ProjectID, &f.Path, &f.Name, &f.Hash)
		files = append(files, f)
	}
	
	return files, nil
}
```

---

## 5. Database Schema

```sql
-- Embedded chunks table with vector storage
CREATE TABLE IF NOT EXISTS embedded_chunks (
  id TEXT PRIMARY KEY,
  project_id TEXT NOT NULL,
  source_id TEXT NOT NULL,
  source_type TEXT NOT NULL CHECK (source_type IN ('local', 'shared', 'memory')),
  
  -- Content
  content TEXT NOT NULL,
  chunk_index INTEGER NOT NULL,
  total_chunks INTEGER NOT NULL,
  
  -- Metadata
  title TEXT,
  path TEXT,
  share_id TEXT,
  
  -- Embedding (stored as JSON array)
  embedding TEXT NOT NULL,
  embedding_model TEXT NOT NULL,
  
  -- Timestamps
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  
  -- Indexes
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_chunks_project ON embedded_chunks(project_id);
CREATE INDEX idx_chunks_source ON embedded_chunks(source_id);
CREATE INDEX idx_chunks_type ON embedded_chunks(source_type);
CREATE INDEX idx_chunks_share ON embedded_chunks(share_id);

-- Track indexed files to detect changes
CREATE TABLE IF NOT EXISTS indexed_files (
  project_id TEXT NOT NULL,
  path TEXT NOT NULL,
  hash TEXT NOT NULL,
  indexed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  chunk_count INTEGER NOT NULL,
  PRIMARY KEY (project_id, path),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

---

## 6. Testing Requirements

| Test | Description | Priority |
|------|-------------|----------|
| Embedding generation | Text correctly embedded | Critical |
| Vector search | Similar content found | Critical |
| Shared content search | Cross-project content included | Critical |
| Context assembly | Token budget respected | High |
| Chunk overlap | Content continuity preserved | Medium |
| Source attribution | Sources correctly labeled | Medium |
| Background indexing | Files indexed automatically | Medium |

---

## Related Specs

- [Sharing Architecture](./06-01-sharing-architecture.md)
- [Sync Mechanism](./06-02-sync-mechanism.md)
- [UI Components](./06-04-sharing-ui.md)
