# 31. Knowledge Memory System

## 31.1 Overview

The Knowledge Memory System enables the AI to learn from existing specification projects and external URLs, building contextual understanding of coding patterns, logging conventions, architectural decisions, and documentation styles. This learned knowledge enhances AI responses when processing voice/text inputs with minimal instructions.

### 31.1.1 Core Capabilities

| Capability | Description |
|------------|-------------|
| **Spec Learning** | Ingest multiple spec projects to learn documentation patterns, coding standards, and conventions |
| **URL Knowledge** | Crawl and index web pages with configurable depth and scope |
| **Vector Search** | Semantic retrieval via sqlite-vss for context-aware AI responses |
| **Isolated Storage** | Separate vector databases for spec knowledge vs URL knowledge |
| **Full Cleanup** | Complete removal of knowledge bases including all vector data |
| **Worker Isolation** | Golang worker process for heavy lifting (crawling, embedding) |

### 31.1.2 Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        Main Application (Go)                             │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐          │
│  │ Knowledge       │  │ URL Knowledge   │  │ Worker          │          │
│  │ Manager API     │  │ Manager API     │  │ Orchestrator    │          │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘          │
│           │                    │                     │                   │
│           ▼                    ▼                     ▼                   │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                     Worker Status Table                          │    │
│  │  (JobId, ProjectId, Type, Status, Progress, ErrorMsg, UpdatedAt) │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
         ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
         │ Go Worker    │  │ Go Worker    │  │ Go Worker    │
         │ (Spec)       │  │ (URL)        │  │ (URL)        │
         └──────┬───────┘  └──────┬───────┘  └──────┬───────┘
                │                 │                 │
                ▼                 ▼                 ▼
         ┌─────────────────────────────────────────────────┐
         │                SQLite Database                   │
         ├─────────────────────┬───────────────────────────┤
         │  spec_knowledge.db  │  url_knowledge.db         │
         │  (sqlite-vss)       │  (sqlite-vss)             │
         └─────────────────────┴───────────────────────────┘
```

---

## 31.2 Data Models

### 31.2.1 Knowledge Source Tables

```sql
-- Main knowledge source registry
CREATE TABLE KnowledgeSources (
    Id              TEXT PRIMARY KEY,
    ProjectId       TEXT NOT NULL,
    SourceType      TEXT NOT NULL CHECK(SourceType IN ('spec', 'url')),
    Name            TEXT NOT NULL,
    Description     TEXT,
    Status          TEXT NOT NULL DEFAULT 'pending' 
                    CHECK(Status IN ('pending', 'processing', 'ready', 'error', 'removing')),
    TotalChunks     INTEGER DEFAULT 0,
    TotalTokens     INTEGER DEFAULT 0,
    CreatedAt       TEXT NOT NULL,
    UpdatedAt       TEXT NOT NULL,
    RemovedAt       TEXT,
    FOREIGN KEY (ProjectId) REFERENCES Projects(Id) ON DELETE CASCADE
);

CREATE INDEX idx_knowledge_sources_project ON KnowledgeSources(ProjectId);
CREATE INDEX idx_knowledge_sources_type ON KnowledgeSources(SourceType);
CREATE INDEX idx_knowledge_sources_status ON KnowledgeSources(Status);
```

### 31.2.2 Spec Knowledge Source

```sql
-- Spec project knowledge sources
CREATE TABLE SpecKnowledgeSources (
    Id                  TEXT PRIMARY KEY,
    KnowledgeSourceId   TEXT NOT NULL UNIQUE,
    SourceProjectId     TEXT,           -- Reference to another project if internal
    ExternalPath        TEXT,           -- Path to external spec folder
    IncludeFolders      TEXT,           -- JSON array of folder paths to include
    ExcludeFolders      TEXT,           -- JSON array of folder paths to exclude
    FileExtensions      TEXT DEFAULT '["md","json","yaml"]',
    LastSyncAt          TEXT,
    FOREIGN KEY (KnowledgeSourceId) REFERENCES KnowledgeSources(Id) ON DELETE CASCADE,
    FOREIGN KEY (SourceProjectId) REFERENCES Projects(Id) ON DELETE SET NULL
);
```

### 31.2.3 URL Knowledge Source

```sql
-- URL knowledge sources
CREATE TABLE UrlKnowledgeSources (
    Id                  TEXT PRIMARY KEY,
    KnowledgeSourceId   TEXT NOT NULL UNIQUE,
    BaseUrl             TEXT NOT NULL,
    CrawlSubPages       INTEGER DEFAULT 0,      -- Boolean: crawl child pages
    MaxDepth            INTEGER DEFAULT 3,
    MaxPages            INTEGER DEFAULT 500,
    StayWithinDomain    INTEGER DEFAULT 1,      -- Boolean: don't leave domain
    IncludePatterns     TEXT,                   -- JSON array of regex patterns
    ExcludePatterns     TEXT,                   -- JSON array of regex patterns
    RespectRobotsTxt    INTEGER DEFAULT 1,
    CrawlDelayMs        INTEGER DEFAULT 1000,
    LastCrawlAt         TEXT,
    FOREIGN KEY (KnowledgeSourceId) REFERENCES KnowledgeSources(Id) ON DELETE CASCADE
);

CREATE INDEX idx_url_knowledge_base_url ON UrlKnowledgeSources(BaseUrl);
```

### 31.2.4 Crawled URLs Registry

```sql
-- Track all discovered and crawled URLs
CREATE TABLE CrawledUrls (
    Id                  TEXT PRIMARY KEY,
    UrlKnowledgeSourceId TEXT NOT NULL,
    NormalizedUrl       TEXT NOT NULL,          -- Canonical URL without trailing slash, etc.
    OriginalUrl         TEXT NOT NULL,
    Depth               INTEGER NOT NULL,
    Status              TEXT NOT NULL DEFAULT 'pending'
                        CHECK(Status IN ('pending', 'crawling', 'completed', 'error', 'skipped')),
    ContentHash         TEXT,                   -- SHA-256 of content for change detection
    ContentType         TEXT,
    LastCrawledAt       TEXT,
    ErrorMessage        TEXT,
    FOREIGN KEY (UrlKnowledgeSourceId) REFERENCES UrlKnowledgeSources(Id) ON DELETE CASCADE,
    UNIQUE(UrlKnowledgeSourceId, NormalizedUrl)
);

CREATE INDEX idx_crawled_urls_source ON CrawledUrls(UrlKnowledgeSourceId);
CREATE INDEX idx_crawled_urls_status ON CrawledUrls(Status);
CREATE INDEX idx_crawled_urls_normalized ON CrawledUrls(NormalizedUrl);
```

### 31.2.5 Knowledge Chunks (Separate Databases)

```sql
-- Stored in spec_knowledge.db
CREATE TABLE SpecChunks (
    Id                  TEXT PRIMARY KEY,
    KnowledgeSourceId   TEXT NOT NULL,
    ProjectId           TEXT NOT NULL,
    FilePath            TEXT NOT NULL,
    ChunkIndex          INTEGER NOT NULL,
    Content             TEXT NOT NULL,
    TokenCount          INTEGER NOT NULL,
    StartLine           INTEGER,
    EndLine             INTEGER,
    Metadata            TEXT,                   -- JSON: headers, section title, etc.
    CreatedAt           TEXT NOT NULL
);

CREATE VIRTUAL TABLE SpecChunksVss USING vss0(
    Embedding(1536)                             -- OpenAI ada-002 dimension
);

CREATE INDEX idx_spec_chunks_source ON SpecChunks(KnowledgeSourceId);
CREATE INDEX idx_spec_chunks_project ON SpecChunks(ProjectId);
```

```sql
-- Stored in url_knowledge.db
CREATE TABLE UrlChunks (
    Id                  TEXT PRIMARY KEY,
    KnowledgeSourceId   TEXT NOT NULL,
    ProjectId           TEXT NOT NULL,
    CrawledUrlId        TEXT NOT NULL,
    ChunkIndex          INTEGER NOT NULL,
    Content             TEXT NOT NULL,
    TokenCount          INTEGER NOT NULL,
    Metadata            TEXT,                   -- JSON: title, headers, etc.
    CreatedAt           TEXT NOT NULL,
    FOREIGN KEY (CrawledUrlId) REFERENCES CrawledUrls(Id) ON DELETE CASCADE
);

CREATE VIRTUAL TABLE UrlChunksVss USING vss0(
    Embedding(1536)
);

CREATE INDEX idx_url_chunks_source ON UrlChunks(KnowledgeSourceId);
CREATE INDEX idx_url_chunks_crawled ON UrlChunks(CrawledUrlId);
```

### 31.2.6 Worker Status Table

```sql
-- Worker job status tracking (main database)
CREATE TABLE KnowledgeWorkerJobs (
    Id              TEXT PRIMARY KEY,
    ProjectId       TEXT NOT NULL,
    KnowledgeSourceId TEXT NOT NULL,
    JobType         TEXT NOT NULL CHECK(JobType IN ('ingest_spec', 'crawl_url', 'remove')),
    Status          TEXT NOT NULL DEFAULT 'queued'
                    CHECK(Status IN ('queued', 'running', 'completed', 'failed', 'cancelled')),
    Progress        INTEGER DEFAULT 0,          -- 0-100 percentage
    TotalItems      INTEGER DEFAULT 0,
    ProcessedItems  INTEGER DEFAULT 0,
    CurrentItem     TEXT,                       -- Current file/URL being processed
    ErrorMessage    TEXT,
    WorkerPid       INTEGER,                    -- OS process ID
    StartedAt       TEXT,
    CompletedAt     TEXT,
    CreatedAt       TEXT NOT NULL,
    UpdatedAt       TEXT NOT NULL,
    FOREIGN KEY (ProjectId) REFERENCES Projects(Id) ON DELETE CASCADE,
    FOREIGN KEY (KnowledgeSourceId) REFERENCES KnowledgeSources(Id) ON DELETE CASCADE
);

CREATE INDEX idx_worker_jobs_status ON KnowledgeWorkerJobs(Status);
CREATE INDEX idx_worker_jobs_project ON KnowledgeWorkerJobs(ProjectId);
```

---

## 31.3 URL Normalization

### 31.3.1 Normalization Rules

URL normalization ensures duplicate URLs are detected regardless of formatting differences:

```go
package knowledge

import (
    "net/url"
    "strings"
    "sort"
)

// NormalizeUrl converts URL to canonical form for deduplication
func NormalizeUrl(rawUrl string) (string, error) {
    u, err := url.Parse(strings.TrimSpace(rawUrl))
    if err != nil {
        return "", err
    }
    
    // 1. Lowercase scheme and host
    u.Scheme = strings.ToLower(u.Scheme)
    u.Host = strings.ToLower(u.Host)
    
    // 2. Remove default ports
    if (u.Scheme == "http" && u.Port() == "80") ||
       (u.Scheme == "https" && u.Port() == "443") {
        u.Host = u.Hostname()
    }
    
    // 3. Remove trailing slash from path (except root)
    if u.Path != "/" && strings.HasSuffix(u.Path, "/") {
        u.Path = strings.TrimSuffix(u.Path, "/")
    }
    
    // 4. Empty path becomes "/"
    if u.Path == "" {
        u.Path = "/"
    }
    
    // 5. Sort query parameters alphabetically
    if u.RawQuery != "" {
        params := u.Query()
        var keys []string
        for k := range params {
            keys = append(keys, k)
        }
        sort.Strings(keys)
        
        var sortedQuery []string
        for _, k := range keys {
            for _, v := range params[k] {
                sortedQuery = append(sortedQuery, 
                    url.QueryEscape(k)+"="+url.QueryEscape(v))
            }
        }
        u.RawQuery = strings.Join(sortedQuery, "&")
    }
    
    // 6. Remove fragment
    u.Fragment = ""
    
    // 7. Remove common tracking parameters
    u = removeTrackingParams(u)
    
    // 8. Handle www prefix consistently (remove it)
    if strings.HasPrefix(u.Host, "www.") {
        u.Host = strings.TrimPrefix(u.Host, "www.")
    }
    
    return u.String(), nil
}

var trackingParams = map[string]bool{
    "utm_source": true, "utm_medium": true, "utm_campaign": true,
    "utm_term": true, "utm_content": true, "fbclid": true,
    "gclid": true, "ref": true, "source": true,
}

func removeTrackingParams(u *url.URL) *url.URL {
    params := u.Query()
    for param := range trackingParams {
        params.Del(param)
    }
    u.RawQuery = params.Encode()
    return u
}
```

### 31.3.2 Deduplication Examples

| Original URL | Normalized URL |
|--------------|----------------|
| `https://Example.COM/Page/` | `https://example.com/page` |
| `http://example.com:80/path` | `http://example.com/path` |
| `https://www.example.com/a` | `https://example.com/a` |
| `https://example.com/a?b=2&a=1` | `https://example.com/a?a=1&b=2` |
| `https://example.com/page#section` | `https://example.com/page` |
| `https://example.com/page?utm_source=x` | `https://example.com/page` |

---

## 31.4 Go Worker Process

### 31.4.1 Worker Architecture

The worker is a standalone Go binary that performs heavy operations outside the main application:

```
knowledge-worker
├── cmd/
│   └── knowledge-worker/
│       └── main.go
├── internal/
│   ├── crawler/
│   │   ├── crawler.go
│   │   ├── normalizer.go
│   │   └── robots.go
│   ├── embedder/
│   │   ├── embedder.go
│   │   └── chunker.go
│   ├── ingester/
│   │   ├── spec_ingester.go
│   │   └── url_ingester.go
│   └── status/
│       └── reporter.go
└── config/
    └── config.go
```

### 31.4.2 Worker Configuration

```go
// WorkerConfig passed as JSON file argument
type WorkerConfig struct {
    // Database connections
    MainDbPath       string `json:"mainDbPath"`
    SpecKnowledgeDb  string `json:"specKnowledgeDb"`
    UrlKnowledgeDb   string `json:"urlKnowledgeDb"`
    
    // Job information
    JobId            string `json:"jobId"`
    JobType          string `json:"jobType"`       // "ingest_spec", "crawl_url", "remove"
    ProjectId        string `json:"projectId"`
    KnowledgeSourceId string `json:"knowledgeSourceId"`
    
    // Embedding configuration
    EmbeddingModel   string `json:"embeddingModel"`
    EmbeddingApiUrl  string `json:"embeddingApiUrl"`
    EmbeddingApiKey  string `json:"embeddingApiKey,omitempty"`
    
    // Spec ingestion settings
    SpecPath         string   `json:"specPath,omitempty"`
    IncludeFolders   []string `json:"includeFolders,omitempty"`
    ExcludeFolders   []string `json:"excludeFolders,omitempty"`
    FileExtensions   []string `json:"fileExtensions,omitempty"`
    
    // URL crawling settings
    BaseUrl          string   `json:"baseUrl,omitempty"`
    CrawlSubPages    bool     `json:"crawlSubPages,omitempty"`
    MaxDepth         int      `json:"maxDepth,omitempty"`
    MaxPages         int      `json:"maxPages,omitempty"`
    StayWithinDomain bool     `json:"stayWithinDomain,omitempty"`
    IncludePatterns  []string `json:"includePatterns,omitempty"`
    ExcludePatterns  []string `json:"excludePatterns,omitempty"`
    CrawlDelayMs     int      `json:"crawlDelayMs,omitempty"`
    RespectRobotsTxt bool     `json:"respectRobotsTxt,omitempty"`
    
    // Concurrency
    MaxConcurrent    int      `json:"maxConcurrent"`
    
    // Logging
    LogLevel         string   `json:"logLevel"`
    LogFile          string   `json:"logFile,omitempty"`
}
```

### 31.4.3 Worker Invocation

```go
// Main application spawns workers
func (m *WorkerManager) SpawnWorker(config WorkerConfig) error {
    configPath := filepath.Join(m.tempDir, config.JobId+".json")
    
    // Write config to temp file
    configData, err := json.Marshal(config)
    if err != nil {
        return fmt.Errorf("marshal config: %w", err)
    }
    if err := os.WriteFile(configPath, configData, 0600); err != nil {
        return fmt.Errorf("write config: %w", err)
    }
    
    // Spawn worker process
    cmd := exec.Command(m.workerBinaryPath, "--config", configPath)
    cmd.Stdout = m.getLogWriter(config.JobId)
    cmd.Stderr = m.getLogWriter(config.JobId)
    
    if err := cmd.Start(); err != nil {
        return fmt.Errorf("start worker: %w", err)
    }
    
    // Update job with PID
    if err := m.db.UpdateWorkerPid(config.JobId, cmd.Process.Pid); err != nil {
        return fmt.Errorf("update pid: %w", err)
    }
    
    // Monitor in background
    go m.monitorWorker(config.JobId, cmd)
    
    return nil
}
```

### 31.4.4 Status Reporting

Worker updates the SQLite status table periodically:

```go
// StatusReporter writes progress to SQLite
type StatusReporter struct {
    db        *sql.DB
    jobId     string
    interval  time.Duration
    lastWrite time.Time
    mu        sync.Mutex
}

func (r *StatusReporter) UpdateProgress(processed, total int, currentItem string) error {
    r.mu.Lock()
    defer r.mu.Unlock()
    
    // Rate limit writes to avoid lock contention
    if time.Since(r.lastWrite) < r.interval {
        return nil
    }
    
    progress := 0
    if total > 0 {
        progress = (processed * 100) / total
    }
    
    _, err := r.db.Exec(`
        UPDATE KnowledgeWorkerJobs 
        SET Progress = ?, ProcessedItems = ?, TotalItems = ?, 
            CurrentItem = ?, UpdatedAt = ?
        WHERE Id = ?
    `, progress, processed, total, currentItem, time.Now().Format(time.RFC3339), r.jobId)
    
    if err == nil {
        r.lastWrite = time.Now()
    }
    return err
}

func (r *StatusReporter) Complete(err error) error {
    status := "completed"
    var errMsg *string
    if err != nil {
        status = "failed"
        msg := err.Error()
        errMsg = &msg
    }
    
    _, dbErr := r.db.Exec(`
        UPDATE KnowledgeWorkerJobs 
        SET Status = ?, Progress = 100, ErrorMessage = ?, 
            CompletedAt = ?, UpdatedAt = ?
        WHERE Id = ?
    `, status, errMsg, time.Now().Format(time.RFC3339), 
       time.Now().Format(time.RFC3339), r.jobId)
    
    return dbErr
}
```

---

## 31.5 URL Crawler Implementation

### 31.5.1 Crawler Core

```go
package crawler

import (
    "context"
    "net/http"
    "sync"
    "time"
    "golang.org/x/net/html"
)

type Crawler struct {
    config       CrawlerConfig
    httpClient   *http.Client
    visited      sync.Map              // NormalizedUrl -> bool
    queue        chan CrawlTask
    results      chan CrawlResult
    wg           sync.WaitGroup
    statusReport *StatusReporter
    robotsTxt    *RobotsChecker
}

type CrawlTask struct {
    Url       string
    Depth     int
    ParentUrl string
}

type CrawlResult struct {
    NormalizedUrl string
    OriginalUrl   string
    Depth         int
    Content       string
    ContentType   string
    Links         []string
    Error         error
}

func (c *Crawler) Start(ctx context.Context) error {
    // Start worker goroutines
    for i := 0; i < c.config.MaxConcurrent; i++ {
        c.wg.Add(1)
        go c.worker(ctx)
    }
    
    // Seed with base URL
    normalizedBase, _ := NormalizeUrl(c.config.BaseUrl)
    c.queue <- CrawlTask{
        Url:   c.config.BaseUrl,
        Depth: 0,
    }
    c.visited.Store(normalizedBase, true)
    
    // Process results
    go c.processResults(ctx)
    
    // Wait for completion
    c.wg.Wait()
    close(c.results)
    
    return nil
}

func (c *Crawler) worker(ctx context.Context) {
    defer c.wg.Done()
    
    for {
        select {
        case <-ctx.Done():
            return
        case task, ok := <-c.queue:
            if !ok {
                return
            }
            
            // Check depth limit
            if task.Depth > c.config.MaxDepth {
                continue
            }
            
            // Check robots.txt
            if c.config.RespectRobotsTxt && !c.robotsTxt.IsAllowed(task.Url) {
                continue
            }
            
            // Apply crawl delay
            time.Sleep(time.Duration(c.config.CrawlDelayMs) * time.Millisecond)
            
            // Fetch and parse
            result := c.fetch(ctx, task)
            c.results <- result
            
            // Queue discovered links
            if result.Error == nil && c.config.CrawlSubPages {
                c.queueLinks(result.Links, task.Depth+1)
            }
        }
    }
}

func (c *Crawler) queueLinks(links []string, depth int) {
    for _, link := range links {
        normalized, err := NormalizeUrl(link)
        if err != nil {
            continue
        }
        
        // Check if already visited
        if _, exists := c.visited.LoadOrStore(normalized, true); exists {
            continue
        }
        
        // Check domain constraint
        if c.config.StayWithinDomain && !c.isSameDomain(link) {
            continue
        }
        
        // Check include/exclude patterns
        if !c.matchesPatterns(normalized) {
            continue
        }
        
        // Check page limit
        visitedCount := c.getVisitedCount()
        if visitedCount >= c.config.MaxPages {
            return
        }
        
        // Queue for crawling
        select {
        case c.queue <- CrawlTask{Url: link, Depth: depth}:
        default:
            // Queue full, skip
        }
    }
}

func (c *Crawler) matchesPatterns(url string) bool {
    // If include patterns specified, URL must match at least one
    if len(c.config.IncludePatterns) > 0 {
        matched := false
        for _, pattern := range c.config.IncludePatterns {
            if regexp.MustCompile(pattern).MatchString(url) {
                matched = true
                break
            }
        }
        if !matched {
            return false
        }
    }
    
    // URL must not match any exclude pattern
    for _, pattern := range c.config.ExcludePatterns {
        if regexp.MustCompile(pattern).MatchString(url) {
            return false
        }
    }
    
    return true
}
```

### 31.5.2 Link Extraction

```go
func (c *Crawler) extractLinks(doc *html.Node, baseUrl *url.URL) []string {
    var links []string
    
    var traverse func(*html.Node)
    traverse = func(n *html.Node) {
        if n.Type == html.ElementNode && n.Data == "a" {
            for _, attr := range n.Attr {
                if attr.Key == "href" {
                    link, err := baseUrl.Parse(attr.Val)
                    if err != nil {
                        continue
                    }
                    
                    // Only HTTP(S) links
                    if link.Scheme == "http" || link.Scheme == "https" {
                        links = append(links, link.String())
                    }
                }
            }
        }
        for child := n.FirstChild; child != nil; child = child.NextSibling {
            traverse(child)
        }
    }
    traverse(doc)
    
    return links
}
```

---

## 31.6 Spec Ingester Implementation

### 31.6.1 File Discovery

```go
package ingester

type SpecIngester struct {
    config       IngesterConfig
    statusReport *StatusReporter
    chunker      *Chunker
    embedder     *Embedder
    db           *sql.DB
}

func (i *SpecIngester) IngestSpec(ctx context.Context) error {
    // Discover files
    files, err := i.discoverFiles()
    if err != nil {
        return fmt.Errorf("discover files: %w", err)
    }
    
    i.statusReport.UpdateProgress(0, len(files), "Discovered files")
    
    // Process each file
    for idx, filePath := range files {
        select {
        case <-ctx.Done():
            return ctx.Err()
        default:
        }
        
        if err := i.processFile(ctx, filePath); err != nil {
            // Log error but continue with other files
            log.Printf("Error processing %s: %v", filePath, err)
        }
        
        i.statusReport.UpdateProgress(idx+1, len(files), filePath)
    }
    
    return nil
}

func (i *SpecIngester) discoverFiles() ([]string, error) {
    var files []string
    
    err := filepath.WalkDir(i.config.SpecPath, func(path string, d fs.DirEntry, err error) error {
        if err != nil {
            return err
        }
        
        // Skip directories in exclude list
        if d.IsDir() {
            relPath, _ := filepath.Rel(i.config.SpecPath, path)
            for _, exclude := range i.config.ExcludeFolders {
                if strings.HasPrefix(relPath, exclude) {
                    return filepath.SkipDir
                }
            }
            
            // Check include list if specified
            if len(i.config.IncludeFolders) > 0 {
                included := false
                for _, include := range i.config.IncludeFolders {
                    if strings.HasPrefix(relPath, include) || strings.HasPrefix(include, relPath) {
                        included = true
                        break
                    }
                }
                if !included && relPath != "." {
                    return filepath.SkipDir
                }
            }
            return nil
        }
        
        // Check file extension
        ext := strings.ToLower(filepath.Ext(path))
        for _, allowedExt := range i.config.FileExtensions {
            if ext == "."+strings.TrimPrefix(allowedExt, ".") {
                files = append(files, path)
                break
            }
        }
        
        return nil
    })
    
    return files, err
}

func (i *SpecIngester) processFile(ctx context.Context, filePath string) error {
    content, err := os.ReadFile(filePath)
    if err != nil {
        return fmt.Errorf("read file: %w", err)
    }
    
    // Chunk the content
    chunks := i.chunker.ChunkContent(string(content), filePath)
    
    // Generate embeddings and store
    for _, chunk := range chunks {
        embedding, err := i.embedder.Embed(ctx, chunk.Content)
        if err != nil {
            return fmt.Errorf("embed chunk: %w", err)
        }
        
        // Insert into spec_knowledge.db
        if err := i.storeChunk(chunk, embedding); err != nil {
            return fmt.Errorf("store chunk: %w", err)
        }
    }
    
    return nil
}
```

---

## 31.7 API Endpoints

### 31.7.1 Knowledge Source Management

```
POST   /api/projects/{projectId}/knowledge/sources
GET    /api/projects/{projectId}/knowledge/sources
GET    /api/projects/{projectId}/knowledge/sources/{sourceId}
DELETE /api/projects/{projectId}/knowledge/sources/{sourceId}
POST   /api/projects/{projectId}/knowledge/sources/{sourceId}/refresh
```

### 31.7.2 Request/Response Schemas

**Create Spec Knowledge Source**
```json
POST /api/projects/{projectId}/knowledge/sources
{
    "type": "spec",
    "name": "WordPress Plugin Spec",
    "description": "Learning from exam-manager plugin specification",
    "spec": {
        "sourceProjectId": "uuid-of-project",
        "externalPath": null,
        "includeFolders": ["01-admin-backend", "02-frontend"],
        "excludeFolders": ["diagrams", "ideas"],
        "fileExtensions": ["md", "json"]
    }
}

Response: 201 Created
{
    "id": "ks-uuid",
    "projectId": "project-uuid",
    "sourceType": "spec",
    "name": "WordPress Plugin Spec",
    "status": "pending",
    "jobId": "job-uuid",
    "createdAt": "2025-01-28T10:00:00Z"
}
```

**Create URL Knowledge Source**
```json
POST /api/projects/{projectId}/knowledge/sources
{
    "type": "url",
    "name": "Lovable Documentation",
    "description": "Learning from Lovable docs website",
    "url": {
        "baseUrl": "https://docs.lovable.dev",
        "crawlSubPages": true,
        "maxDepth": 3,
        "maxPages": 500,
        "stayWithinDomain": true,
        "includePatterns": ["/features/.*", "/guides/.*"],
        "excludePatterns": ["/api-reference/.*"],
        "crawlDelayMs": 1000,
        "respectRobotsTxt": true
    }
}

Response: 201 Created
{
    "id": "ks-uuid",
    "projectId": "project-uuid",
    "sourceType": "url",
    "name": "Lovable Documentation",
    "status": "pending",
    "jobId": "job-uuid",
    "createdAt": "2025-01-28T10:00:00Z"
}
```

**List Knowledge Sources**
```json
GET /api/projects/{projectId}/knowledge/sources

Response: 200 OK
{
    "sources": [
        {
            "id": "ks-1",
            "sourceType": "spec",
            "name": "WordPress Plugin Spec",
            "status": "ready",
            "totalChunks": 1250,
            "totalTokens": 450000,
            "createdAt": "2025-01-27T10:00:00Z",
            "spec": {
                "sourceProjectId": "proj-uuid",
                "includeFolders": ["01-admin-backend"]
            }
        },
        {
            "id": "ks-2",
            "sourceType": "url",
            "name": "Lovable Docs",
            "status": "processing",
            "totalChunks": 320,
            "progress": 45,
            "createdAt": "2025-01-28T09:00:00Z",
            "url": {
                "baseUrl": "https://docs.lovable.dev",
                "crawledPages": 120,
                "maxPages": 500
            }
        }
    ]
}
```

**Delete Knowledge Source (Full Cleanup)**
```json
DELETE /api/projects/{projectId}/knowledge/sources/{sourceId}

Response: 202 Accepted
{
    "message": "Deletion in progress",
    "jobId": "delete-job-uuid"
}
```

### 31.7.3 Job Status Endpoint

```json
GET /api/knowledge/jobs/{jobId}

Response: 200 OK
{
    "id": "job-uuid",
    "jobType": "crawl_url",
    "status": "running",
    "progress": 45,
    "processedItems": 120,
    "totalItems": 267,
    "currentItem": "https://docs.lovable.dev/features/auth",
    "startedAt": "2025-01-28T10:00:00Z"
}
```

---

## 31.8 Knowledge Retrieval

### 31.8.1 Semantic Search Interface

```go
type KnowledgeRetriever struct {
    specDb    *sql.DB    // spec_knowledge.db
    urlDb     *sql.DB    // url_knowledge.db
    embedder  *Embedder
}

type RetrievalRequest struct {
    Query         string   `json:"query"`
    ProjectId     string   `json:"projectId"`
    SourceTypes   []string `json:"sourceTypes"`   // ["spec", "url"] or one
    SourceIds     []string `json:"sourceIds"`     // Optional: specific sources
    TopK          int      `json:"topK"`
    MinScore      float32  `json:"minScore"`
}

type RetrievalResult struct {
    Chunks []ChunkResult `json:"chunks"`
}

type ChunkResult struct {
    SourceType      string  `json:"sourceType"`
    SourceName      string  `json:"sourceName"`
    Content         string  `json:"content"`
    FilePath        string  `json:"filePath,omitempty"`    // For spec
    Url             string  `json:"url,omitempty"`         // For url
    Score           float32 `json:"score"`
    Metadata        any     `json:"metadata"`
}

func (r *KnowledgeRetriever) Retrieve(ctx context.Context, req RetrievalRequest) (*RetrievalResult, error) {
    // Generate query embedding
    queryEmbedding, err := r.embedder.Embed(ctx, req.Query)
    if err != nil {
        return nil, err
    }
    
    var allChunks []ChunkResult
    
    // Search spec knowledge if requested
    if slices.Contains(req.SourceTypes, "spec") || len(req.SourceTypes) == 0 {
        specChunks, err := r.searchSpecKnowledge(queryEmbedding, req)
        if err != nil {
            return nil, err
        }
        allChunks = append(allChunks, specChunks...)
    }
    
    // Search URL knowledge if requested
    if slices.Contains(req.SourceTypes, "url") || len(req.SourceTypes) == 0 {
        urlChunks, err := r.searchUrlKnowledge(queryEmbedding, req)
        if err != nil {
            return nil, err
        }
        allChunks = append(allChunks, urlChunks...)
    }
    
    // Sort by score and limit to TopK
    sort.Slice(allChunks, func(i, j int) bool {
        return allChunks[i].Score > allChunks[j].Score
    })
    
    if len(allChunks) > req.TopK {
        allChunks = allChunks[:req.TopK]
    }
    
    return &RetrievalResult{Chunks: allChunks}, nil
}
```

---

## 31.9 Full Cleanup Process

### 31.9.1 Deletion Workflow

When a knowledge source is deleted, ALL associated data must be removed:

```go
func (m *KnowledgeManager) DeleteKnowledgeSource(ctx context.Context, sourceId string) error {
    source, err := m.db.GetKnowledgeSource(sourceId)
    if err != nil {
        return err
    }
    
    // Mark as removing
    if err := m.db.UpdateKnowledgeSourceStatus(sourceId, "removing"); err != nil {
        return err
    }
    
    // Create removal job
    job := KnowledgeWorkerJob{
        Id:                uuid.New().String(),
        ProjectId:         source.ProjectId,
        KnowledgeSourceId: sourceId,
        JobType:           "remove",
        Status:            "queued",
    }
    if err := m.db.CreateWorkerJob(job); err != nil {
        return err
    }
    
    // Spawn worker for cleanup
    config := WorkerConfig{
        MainDbPath:        m.mainDbPath,
        SpecKnowledgeDb:   m.specKnowledgeDb,
        UrlKnowledgeDb:    m.urlKnowledgeDb,
        JobId:             job.Id,
        JobType:           "remove",
        KnowledgeSourceId: sourceId,
    }
    
    return m.workerManager.SpawnWorker(config)
}
```

### 31.9.2 Worker Cleanup Implementation

```go
func (w *Worker) executeRemoval() error {
    // 1. Delete from vector tables (spec_knowledge.db or url_knowledge.db)
    if w.config.SourceType == "spec" {
        // Delete VSS entries
        _, err := w.specDb.Exec(`
            DELETE FROM SpecChunksVss 
            WHERE rowid IN (
                SELECT rowid FROM SpecChunks 
                WHERE KnowledgeSourceId = ?
            )
        `, w.config.KnowledgeSourceId)
        if err != nil {
            return err
        }
        
        // Delete chunks
        _, err = w.specDb.Exec(`
            DELETE FROM SpecChunks WHERE KnowledgeSourceId = ?
        `, w.config.KnowledgeSourceId)
        if err != nil {
            return err
        }
    } else {
        // Delete URL chunks and VSS entries
        _, err := w.urlDb.Exec(`
            DELETE FROM UrlChunksVss 
            WHERE rowid IN (
                SELECT rowid FROM UrlChunks 
                WHERE KnowledgeSourceId = ?
            )
        `, w.config.KnowledgeSourceId)
        if err != nil {
            return err
        }
        
        _, err = w.urlDb.Exec(`
            DELETE FROM UrlChunks WHERE KnowledgeSourceId = ?
        `, w.config.KnowledgeSourceId)
        if err != nil {
            return err
        }
    }
    
    // 2. Delete from main database
    _, err := w.mainDb.Exec(`
        DELETE FROM CrawledUrls 
        WHERE UrlKnowledgeSourceId IN (
            SELECT Id FROM UrlKnowledgeSources 
            WHERE KnowledgeSourceId = ?
        )
    `, w.config.KnowledgeSourceId)
    if err != nil {
        return err
    }
    
    _, err = w.mainDb.Exec(`
        DELETE FROM UrlKnowledgeSources WHERE KnowledgeSourceId = ?
    `, w.config.KnowledgeSourceId)
    if err != nil {
        return err
    }
    
    _, err = w.mainDb.Exec(`
        DELETE FROM SpecKnowledgeSources WHERE KnowledgeSourceId = ?
    `, w.config.KnowledgeSourceId)
    if err != nil {
        return err
    }
    
    _, err = w.mainDb.Exec(`
        DELETE FROM KnowledgeSources WHERE Id = ?
    `, w.config.KnowledgeSourceId)
    if err != nil {
        return err
    }
    
    // 3. VACUUM databases to reclaim space
    _, _ = w.specDb.Exec("VACUUM")
    _, _ = w.urlDb.Exec("VACUUM")
    
    return nil
}
```

---

## 31.10 Seeding Configuration

Add to `09-seeding-configuration.md`:

```json
{
    "knowledge.specKnowledgeDbPath": {
        "description": "Path to spec knowledge vector database",
        "value": "data/spec_knowledge.db"
    },
    "knowledge.urlKnowledgeDbPath": {
        "description": "Path to URL knowledge vector database",
        "value": "data/url_knowledge.db"
    },
    "knowledge.workerBinaryPath": {
        "description": "Path to knowledge worker binary",
        "value": "bin/knowledge-worker"
    },
    "knowledge.maxConcurrentWorkers": {
        "description": "Maximum concurrent worker processes",
        "value": 3
    },
    "knowledge.crawler.defaultMaxDepth": {
        "description": "Default crawl depth limit",
        "value": 3
    },
    "knowledge.crawler.defaultMaxPages": {
        "description": "Default maximum pages to crawl",
        "value": 500
    },
    "knowledge.crawler.defaultDelayMs": {
        "description": "Default delay between requests in ms",
        "value": 1000
    },
    "knowledge.crawler.userAgent": {
        "description": "User agent for web requests",
        "value": "SpecManager-KnowledgeBot/1.0"
    },
    "knowledge.chunker.maxChunkTokens": {
        "description": "Maximum tokens per chunk",
        "value": 512
    },
    "knowledge.chunker.overlapTokens": {
        "description": "Token overlap between chunks",
        "value": 64
    },
    "knowledge.embedding.model": {
        "description": "Embedding model identifier",
        "value": "text-embedding-ada-002"
    },
    "knowledge.embedding.dimension": {
        "description": "Embedding vector dimension",
        "value": 1536
    },
    "knowledge.retrieval.defaultTopK": {
        "description": "Default number of results to retrieve",
        "value": 10
    },
    "knowledge.retrieval.minScore": {
        "description": "Minimum similarity score threshold",
        "value": 0.7
    },
    "knowledge.statusUpdateIntervalMs": {
        "description": "Worker status update interval in ms",
        "value": 2000
    }
}
```

---

## 31.11 Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 8001 | `ERR_KNOWLEDGE_SOURCE_NOT_FOUND` | Knowledge source does not exist |
| 8002 | `ERR_KNOWLEDGE_SOURCE_BUSY` | Source is currently being processed |
| 8003 | `ERR_KNOWLEDGE_INVALID_TYPE` | Invalid source type (must be spec or url) |
| 8004 | `ERR_KNOWLEDGE_INVALID_URL` | URL format is invalid |
| 8005 | `ERR_KNOWLEDGE_PATH_NOT_FOUND` | Spec path does not exist |
| 8006 | `ERR_KNOWLEDGE_CRAWL_FAILED` | URL crawling failed |
| 8007 | `ERR_KNOWLEDGE_EMBED_FAILED` | Embedding generation failed |
| 8008 | `ERR_KNOWLEDGE_WORKER_SPAWN` | Failed to spawn worker process |
| 8009 | `ERR_KNOWLEDGE_WORKER_TIMEOUT` | Worker process timed out |
| 8010 | `ERR_KNOWLEDGE_DB_ERROR` | Knowledge database operation failed |
| 8011 | `ERR_KNOWLEDGE_PATTERN_INVALID` | Invalid regex pattern in include/exclude |
| 8012 | `ERR_KNOWLEDGE_DUPLICATE_URL` | URL already exists in knowledge base |
| 8013 | `ERR_KNOWLEDGE_ROBOTS_BLOCKED` | URL blocked by robots.txt |
| 8014 | `ERR_KNOWLEDGE_DEPTH_EXCEEDED` | Maximum crawl depth exceeded |
| 8015 | `ERR_KNOWLEDGE_PAGE_LIMIT` | Maximum page limit reached |

---

## 31.12 Integration with AI Pipeline

The Knowledge Memory System integrates with the existing instruction pipeline from spec 11:

```go
// In instruction processing
func (p *InstructionProcessor) ProcessWithKnowledge(ctx context.Context, input string) (*ProcessedInstruction, error) {
    // 1. Retrieve relevant knowledge
    knowledge, err := p.knowledgeRetriever.Retrieve(ctx, RetrievalRequest{
        Query:       input,
        ProjectId:   p.projectId,
        SourceTypes: []string{"spec", "url"},
        TopK:        10,
    })
    if err != nil {
        return nil, err
    }
    
    // 2. Build context with knowledge
    contextParts := []string{
        "# Learned Knowledge Context",
    }
    for _, chunk := range knowledge.Chunks {
        source := fmt.Sprintf("[%s: %s]", chunk.SourceType, chunk.SourceName)
        contextParts = append(contextParts, fmt.Sprintf("%s\n%s", source, chunk.Content))
    }
    
    // 3. Include in prompt
    enhancedPrompt := fmt.Sprintf("%s\n\n# User Input\n%s", 
        strings.Join(contextParts, "\n\n"), input)
    
    // 4. Process with AI
    return p.aiProcessor.Process(ctx, enhancedPrompt)
}
```

---

## 31.13 Cross-References

| Specification | Relationship |
|---------------|--------------|
| 02-database-schema.md | Base Projects table reference |
| 09-seeding-configuration.md | Configuration keys for knowledge system |
| 11-instruction-system.md | Integration with instruction processing |
| 16-rag-system.md | Shared RAG infrastructure |
| 21-vector-search-service.md | sqlite-vss implementation details |
| 22-context-window-manager.md | Token budgeting for retrieved chunks |

---

## 31.14 Summary

The Knowledge Memory System provides:

1. **Dual-source learning**: Ingest spec projects and web URLs into separate vector databases
2. **URL normalization**: Consistent deduplication with trailing slash handling, www normalization, etc.
3. **Pattern-based crawling**: Include/exclude regex patterns with depth and page limits
4. **Worker isolation**: Heavy processing in separate Go processes with SQLite status reporting
5. **Full cleanup**: Complete removal of vector data when sources are deleted
6. **AI integration**: Learned knowledge enhances instruction processing with semantic retrieval
