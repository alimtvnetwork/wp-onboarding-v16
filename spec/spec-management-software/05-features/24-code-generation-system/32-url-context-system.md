# URL Context System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

UI system for adding full domain or sitemap-based URL context to AI operations. Integrates with `gsearch` CLI's full-site crawler to cache and index entire websites for RAG retrieval.

**Cross-References:**
- [AI Chat Interface](./25-ai-chat-interface.md) - Parent interface
- [Full-Site Crawler](../../22-golang-search-cli/18-full-site-crawler.md) - CLI backend
- [Search Integration](./30-search-integration.md) - Search context

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    URL Context System                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    AI Panel                               │   │
│  │  ┌────────────────────────────────────────────────────┐  │   │
│  │  │               URL Context Tab                       │  │   │
│  │  │  ┌─────────────────┐  ┌─────────────────────────┐  │  │   │
│  │  │  │ Add Domain Form │  │ Cached Sites List       │  │  │   │
│  │  │  └─────────────────┘  └─────────────────────────┘  │  │   │
│  │  │  ┌─────────────────────────────────────────────┐   │  │   │
│  │  │  │          Crawl Progress Panel               │   │  │   │
│  │  │  └─────────────────────────────────────────────┘   │  │   │
│  │  └────────────────────────────────────────────────────┘  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                            │                                     │
│                            ▼                                     │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                  Backend Service                          │   │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐   │   │
│  │  │ gsearch CLI │  │ WebSocket   │  │ Vector Search   │   │   │
│  │  │ Executor    │  │ Progress    │  │ Interface       │   │   │
│  │  └─────────────┘  └─────────────┘  └─────────────────┘   │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## UI Components

### URL Context Panel

```
┌─────────────────────────────────────────────────────────────────┐
│  📚 URL Context                                          [−] [×] │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Add Website Context                                             │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ https://docs.example.com                              [🔍]│  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ○ Crawl entire domain                                          │
│  ● Use sitemap.xml (faster)                                     │
│  ○ Custom sitemap URL                                           │
│                                                                  │
│  Advanced Options  ▼                                             │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Max pages: [1000]   Depth: [10]   Delay: [250ms]         │  │
│  │ [✓] Generate vectors    [✓] Respect robots.txt           │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  [     Start Crawl     ]                                        │
│                                                                  │
│  ──────────────────────────────────────────────────────────────│
│                                                                  │
│  Cached Sites (3)                                               │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ 📁 docs.stripe.com                                        │  │
│  │    2,340 pages • 45MB • Last updated 2h ago              │  │
│  │    [Search] [Refresh] [×]                                │  │
│  ├───────────────────────────────────────────────────────────┤  │
│  │ 📁 pkg.go.dev                                             │  │
│  │    12,500 pages • 180MB • Last updated 1d ago            │  │
│  │    [Search] [Refresh] [×]                                │  │
│  ├───────────────────────────────────────────────────────────┤  │
│  │ 📁 react.dev                         ⏳ Crawling...      │  │
│  │    450/2,100 pages • 21%                                 │  │
│  │    [Pause] [Cancel]                                      │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Crawl Progress Overlay

```
┌─────────────────────────────────────────────────────────────────┐
│  Crawling react.dev                                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ████████████░░░░░░░░░░░░░░░░░░░░░░░░░  21%                     │
│                                                                  │
│  Pages:     450 / 2,100 (estimated)                             │
│  Skipped:   23 (duplicates)                                     │
│  Failed:    2                                                   │
│  Speed:     3.8 pages/sec                                       │
│  ETA:       ~7 minutes                                          │
│                                                                  │
│  Current: /reference/react/useState                             │
│                                                                  │
│  Recent Activity                                                │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ ✓ /reference/react/useEffect                              │  │
│  │ ✓ /reference/react/useContext                             │  │
│  │ ⊘ /reference/react/legacy (skipped: duplicate)            │  │
│  │ ✓ /reference/react/useMemo                                │  │
│  │ ✗ /reference/react/internal (failed: 403)                 │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  [    Pause    ]    [    Cancel    ]                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Site Search Modal

```
┌─────────────────────────────────────────────────────────────────┐
│  Search in docs.stripe.com                               [×]    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ 🔍 payment intents authentication                         │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Results (8 matches)                                            │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Authentication and Payment Intents                        │  │
│  │ /docs/payments/payment-intents/authentication             │  │
│  │ Learn how to handle 3D Secure authentication with...     │  │
│  │ Relevance: 0.94                                   [Add ↗]│  │
│  ├───────────────────────────────────────────────────────────┤  │
│  │ Payment Intents API                                       │  │
│  │ /docs/api/payment_intents                                 │  │
│  │ The PaymentIntent object tracks the lifecycle of...      │  │
│  │ Relevance: 0.87                                   [Add ↗]│  │
│  ├───────────────────────────────────────────────────────────┤  │
│  │ Strong Customer Authentication (SCA)                      │  │
│  │ /docs/strong-customer-authentication                      │  │
│  │ Comply with European regulations using...                │  │
│  │ Relevance: 0.82                                   [Add ↗]│  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Selected (2):  [Add to Context]                                │
│  • /docs/payments/payment-intents/authentication                │
│  • /docs/api/payment_intents                                    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## TypeScript Interfaces

```typescript
// URL Context Configuration
interface URLContextConfig {
  enabled: boolean;
  autoIncludeInContext: boolean;
  maxPagesPerSite: number;
  vectorSearchEnabled: boolean;
  maxContextPages: number;
}

// Cached Site Information
interface CachedSite {
  id: string;
  domain: string;
  pageCount: number;
  sizeBytes: number;
  hasVectors: boolean;
  status: 'ready' | 'crawling' | 'paused' | 'failed';
  lastUpdatedAt: string;
  createdAt: string;
}

// Crawl Job
interface CrawlJob {
  id: string;
  domain: string;
  sitemapURL?: string;
  status: 'pending' | 'running' | 'paused' | 'completed' | 'failed';
  config: CrawlConfig;
  progress: CrawlProgress;
  startedAt?: string;
  completedAt?: string;
}

interface CrawlConfig {
  useSitemap: boolean;
  customSitemapURL?: string;
  maxPages: number;
  maxDepth: number;
  delayMs: number;
  generateVectors: boolean;
  respectRobotsTxt: boolean;
}

interface CrawlProgress {
  totalURLs: number;
  crawledURLs: number;
  skippedURLs: number;
  failedURLs: number;
  currentURL?: string;
  pagesPerSecond: number;
  estimatedSecondsRemaining?: number;
  recentActivity: CrawlActivity[];
}

interface CrawlActivity {
  url: string;
  status: 'success' | 'skipped' | 'failed';
  reason?: string;
  timestamp: string;
}

// Site Search
interface SiteSearchResult {
  id: string;
  url: string;
  title: string;
  snippet: string;
  relevanceScore: number;
  pageContent?: string;
}

interface SiteSearchRequest {
  domain: string;
  query: string;
  limit?: number;
  useVectors?: boolean;
}
```

---

## Backend API

### REST Endpoints

```
POST   /api/url-context/crawl              Start crawl job
GET    /api/url-context/crawl/:id          Get crawl status
POST   /api/url-context/crawl/:id/pause    Pause crawl
POST   /api/url-context/crawl/:id/resume   Resume crawl
DELETE /api/url-context/crawl/:id          Cancel crawl

GET    /api/url-context/sites              List cached sites
GET    /api/url-context/sites/:domain      Get site details
DELETE /api/url-context/sites/:domain      Delete site cache
POST   /api/url-context/sites/:domain/refresh  Re-crawl site

POST   /api/url-context/search             Search site content
POST   /api/url-context/add-to-context     Add pages to AI context
```

### WebSocket Events

```typescript
// Client -> Server
interface WSCrawlSubscribe {
  type: 'crawl:subscribe';
  payload: {
    jobId: string;
  };
}

interface WSCrawlUnsubscribe {
  type: 'crawl:unsubscribe';
  payload: {
    jobId: string;
  };
}

// Server -> Client
interface WSCrawlProgress {
  type: 'crawl:progress';
  payload: {
    jobId: string;
    progress: CrawlProgress;
  };
}

interface WSCrawlPageComplete {
  type: 'crawl:page:complete';
  payload: {
    jobId: string;
    url: string;
    status: 'success' | 'skipped' | 'failed';
    reason?: string;
  };
}

interface WSCrawlComplete {
  type: 'crawl:complete';
  payload: {
    jobId: string;
    site: CachedSite;
  };
}

interface WSCrawlError {
  type: 'crawl:error';
  payload: {
    jobId: string;
    error: string;
    code: number;
  };
}
```

---

## Backend Service

### URL Context Service

```go
type URLContextService struct {
    config       *URLContextConfig
    gsearchPath  string
    db           *gorm.DB
    wsHub        *websocket.Hub
}

// StartCrawl initiates a new crawl job
func (s *URLContextService) StartCrawl(
    ctx context.Context,
    domain string,
    config CrawlConfig,
) (*CrawlJob, error) {
    // Build gsearch command
    args := []string{"crawl", domain}
    
    if config.UseSitemap {
        if config.CustomSitemapURL != "" {
            args = append(args, config.CustomSitemapURL, "--sitemap")
        } else {
            args = append(args, "--sitemap")
        }
    }
    
    args = append(args,
        "--delay", fmt.Sprintf("%dms", config.DelayMs),
        "--max-pages", strconv.Itoa(config.MaxPages),
        "--depth", strconv.Itoa(config.MaxDepth),
    )
    
    if config.GenerateVectors {
        args = append(args, "--vectors")
    }
    
    if config.RespectRobotsTxt {
        args = append(args, "--respect-robots")
    }
    
    // Create job record
    job := &CrawlJob{
        ID:       uuid.NewString(),
        Domain:   domain,
        Status:   "running",
        Config:   config,
        Progress: CrawlProgress{},
    }
    
    if err := s.db.Create(job).Error; err != nil {
        return nil, err
    }
    
    // Start crawl in background
    go s.runCrawl(ctx, job, args)
    
    return job, nil
}

// runCrawl executes gsearch crawl and streams progress
func (s *URLContextService) runCrawl(
    ctx context.Context,
    job *CrawlJob,
    args []string,
) {
    args = append(args, "--output", "json", "--progress-stream")
    
    cmd := exec.CommandContext(ctx, s.gsearchPath, args...)
    stdout, _ := cmd.StdoutPipe()
    
    if err := cmd.Start(); err != nil {
        s.updateJobStatus(job.ID, "failed", err.Error())
        return
    }
    
    // Stream progress updates
    decoder := json.NewDecoder(stdout)
    for {
        var event CrawlProgressEvent
        if err := decoder.Decode(&event); err != nil {
            break
        }
        
        // Update job progress
        s.updateJobProgress(job.ID, event.Progress)
        
        // Broadcast via WebSocket
        s.wsHub.BroadcastToJob(job.ID, WSCrawlProgress{
            Type: "crawl:progress",
            Payload: CrawlProgressPayload{
                JobID:    job.ID,
                Progress: event.Progress,
            },
        })
    }
    
    if err := cmd.Wait(); err != nil {
        s.updateJobStatus(job.ID, "failed", err.Error())
        return
    }
    
    s.updateJobStatus(job.ID, "completed", "")
    
    // Notify completion
    site := s.getCachedSite(job.Domain)
    s.wsHub.BroadcastToJob(job.ID, WSCrawlComplete{
        Type: "crawl:complete",
        Payload: CrawlCompletePayload{
            JobID: job.ID,
            Site:  site,
        },
    })
}

// SearchSite performs vector/keyword search on cached site
func (s *URLContextService) SearchSite(
    ctx context.Context,
    req SiteSearchRequest,
) ([]SiteSearchResult, error) {
    // Open site-specific database
    siteDB, err := s.openSiteDB(req.Domain)
    if err != nil {
        return nil, err
    }
    
    if req.UseVectors {
        return s.vectorSearch(ctx, siteDB, req.Query, req.Limit)
    }
    
    return s.keywordSearch(ctx, siteDB, req.Query, req.Limit)
}

func (s *URLContextService) vectorSearch(
    ctx context.Context,
    db *gorm.DB,
    query string,
    limit int,
) ([]SiteSearchResult, error) {
    // Generate query embedding
    embedding, err := s.embedder.Embed(query)
    if err != nil {
        return nil, err
    }
    
    // Vector similarity search using sqlite-vss
    var vectors []SiteContentVector
    err = db.Raw(`
        SELECT v.*, vss_distance(v.vector, ?) as distance
        FROM SiteContentVector v
        ORDER BY distance ASC
        LIMIT ?
    `, embedding, limit).Scan(&vectors).Error
    
    if err != nil {
        return nil, err
    }
    
    // Build results
    results := make([]SiteSearchResult, 0, len(vectors))
    for _, v := range vectors {
        var content SitePageContent
        db.First(&content, "id = ?", v.ContentId)
        
        var crawlURL SiteCrawlURL
        db.First(&crawlURL, "id = ?", content.CrawlURLId)
        
        results = append(results, SiteSearchResult{
            ID:             v.Id,
            URL:            crawlURL.URL,
            Title:          content.Title,
            Snippet:        v.ChunkText[:min(200, len(v.ChunkText))],
            RelevanceScore: 1.0 - v.Distance,
        })
    }
    
    return results, nil
}
```

---

## React Components

### URLContextPanel

```tsx
import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Progress } from '@/components/ui/progress';
import { urlContextApi } from '@/lib/api/url-context';
import { useWebSocket } from '@/hooks/useWebSocket';

interface URLContextPanelProps {
  projectId: string;
  onContextChange?: (pages: string[]) => void;
}

export const URLContextPanel: React.FC<URLContextPanelProps> = ({
  projectId,
  onContextChange,
}) => {
  const [url, setUrl] = useState('');
  const [crawlMode, setCrawlMode] = useState<'domain' | 'sitemap' | 'custom'>('sitemap');
  const [cachedSites, setCachedSites] = useState<CachedSite[]>([]);
  const [activeCrawl, setActiveCrawl] = useState<CrawlJob | null>(null);
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [config, setConfig] = useState<CrawlConfig>({
    useSitemap: true,
    maxPages: 1000,
    maxDepth: 10,
    delayMs: 250,
    generateVectors: true,
    respectRobotsTxt: true,
  });

  // Load cached sites
  useEffect(() => {
    urlContextApi.listSites().then(setCachedSites);
  }, []);

  // WebSocket for crawl progress
  const { subscribe, unsubscribe } = useWebSocket();

  useEffect(() => {
    if (activeCrawl) {
      const unsub = subscribe(`crawl:${activeCrawl.id}`, (event) => {
        if (event.type === 'crawl:progress') {
          setActiveCrawl((prev) => prev && { ...prev, progress: event.payload.progress });
        } else if (event.type === 'crawl:complete') {
          setActiveCrawl(null);
          setCachedSites((prev) => [...prev, event.payload.site]);
        }
      });
      return unsub;
    }
  }, [activeCrawl?.id]);

  const handleStartCrawl = async () => {
    const job = await urlContextApi.startCrawl(url, {
      ...config,
      useSitemap: crawlMode !== 'domain',
      customSitemapURL: crawlMode === 'custom' ? url : undefined,
    });
    setActiveCrawl(job);
  };

  return (
    <Card className="h-full">
      <CardHeader className="pb-3">
        <CardTitle className="text-sm flex items-center gap-2">
          📚 URL Context
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Add URL Form */}
        <div className="space-y-3">
          <Input
            placeholder="https://docs.example.com"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
          />
          
          <RadioGroup value={crawlMode} onValueChange={(v) => setCrawlMode(v as any)}>
            <div className="flex items-center space-x-2">
              <RadioGroupItem value="domain" id="domain" />
              <label htmlFor="domain" className="text-sm">Crawl entire domain</label>
            </div>
            <div className="flex items-center space-x-2">
              <RadioGroupItem value="sitemap" id="sitemap" />
              <label htmlFor="sitemap" className="text-sm">Use sitemap.xml (faster)</label>
            </div>
            <div className="flex items-center space-x-2">
              <RadioGroupItem value="custom" id="custom" />
              <label htmlFor="custom" className="text-sm">Custom sitemap URL</label>
            </div>
          </RadioGroup>

          {showAdvanced && (
            <AdvancedOptions config={config} onChange={setConfig} />
          )}

          <Button
            onClick={handleStartCrawl}
            disabled={!url || !!activeCrawl}
            className="w-full"
          >
            Start Crawl
          </Button>
        </div>

        {/* Active Crawl Progress */}
        {activeCrawl && (
          <CrawlProgressCard job={activeCrawl} />
        )}

        {/* Cached Sites List */}
        <div className="space-y-2">
          <h4 className="text-sm font-medium">Cached Sites ({cachedSites.length})</h4>
          {cachedSites.map((site) => (
            <CachedSiteCard
              key={site.id}
              site={site}
              onSearch={() => openSearchModal(site.domain)}
              onRefresh={() => refreshSite(site.domain)}
              onDelete={() => deleteSite(site.domain)}
            />
          ))}
        </div>
      </CardContent>
    </Card>
  );
};
```

### CrawlProgressCard

```tsx
interface CrawlProgressCardProps {
  job: CrawlJob;
  onPause?: () => void;
  onCancel?: () => void;
}

export const CrawlProgressCard: React.FC<CrawlProgressCardProps> = ({
  job,
  onPause,
  onCancel,
}) => {
  const { progress } = job;
  const percentage = progress.totalURLs > 0 
    ? Math.round((progress.crawledURLs / progress.totalURLs) * 100) 
    : 0;

  return (
    <Card className="border-primary/50">
      <CardContent className="pt-4 space-y-3">
        <div className="flex justify-between text-sm">
          <span>Crawling {job.domain}</span>
          <span>{percentage}%</span>
        </div>
        
        <Progress value={percentage} />
        
        <div className="grid grid-cols-2 gap-2 text-xs text-muted-foreground">
          <div>Pages: {progress.crawledURLs} / {progress.totalURLs}</div>
          <div>Skipped: {progress.skippedURLs}</div>
          <div>Failed: {progress.failedURLs}</div>
          <div>Speed: {progress.pagesPerSecond.toFixed(1)} pages/sec</div>
        </div>

        {progress.currentURL && (
          <div className="text-xs truncate text-muted-foreground">
            Current: {progress.currentURL}
          </div>
        )}

        {/* Recent Activity */}
        <div className="max-h-24 overflow-y-auto text-xs space-y-1">
          {progress.recentActivity.slice(0, 5).map((activity, i) => (
            <div key={i} className="flex items-center gap-1">
              {activity.status === 'success' && <span className="text-green-500">✓</span>}
              {activity.status === 'skipped' && <span className="text-yellow-500">⊘</span>}
              {activity.status === 'failed' && <span className="text-red-500">✗</span>}
              <span className="truncate">{activity.url}</span>
            </div>
          ))}
        </div>

        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={onPause}>Pause</Button>
          <Button variant="destructive" size="sm" onClick={onCancel}>Cancel</Button>
        </div>
      </CardContent>
    </Card>
  );
};
```

---

## Integration with AI Context

When user adds pages from cached sites to context, they are included in the AI prompt:

```go
// BuildContextWithURLs assembles context including URL-cached content
func (b *ContextBuilder) BuildContextWithURLs(
    baseContext string,
    selectedURLs []string,
    tokenBudget int,
) string {
    var urlContextParts []string
    remainingBudget := tokenBudget - countTokens(baseContext)
    
    for _, url := range selectedURLs {
        content, err := b.urlContextService.GetPageContent(url)
        if err != nil {
            continue
        }
        
        contentTokens := countTokens(content)
        if contentTokens > remainingBudget {
            // Truncate to fit
            content = truncateToTokens(content, remainingBudget)
        }
        
        urlContextParts = append(urlContextParts, fmt.Sprintf(
            "<url-context source=\"%s\">\n%s\n</url-context>",
            url,
            content,
        ))
        
        remainingBudget -= countTokens(content)
        if remainingBudget <= 0 {
            break
        }
    }
    
    return baseContext + "\n\n" + strings.Join(urlContextParts, "\n\n")
}
```

---

## Error Codes

| Code | Name | Description |
|------|------|-------------|
| 12850 | ERR_URL_CONTEXT_DISABLED | URL context feature disabled |
| 12851 | ERR_CRAWL_ALREADY_RUNNING | Crawl already in progress for domain |
| 12852 | ERR_SITE_NOT_CACHED | Requested site not in cache |
| 12853 | ERR_SITE_SEARCH_FAILED | Site search query failed |
| 12854 | ERR_VECTORS_NOT_AVAILABLE | Vector search unavailable for site |
| 12855 | ERR_CONTEXT_TOKEN_LIMIT | Selected pages exceed token limit |

---

## Database Models

```go
// URLContextPage tracks pages added to AI context
type URLContextPage struct {
    Id           string    `gorm:"primaryKey;type:TEXT"`
    SessionId    string    `gorm:"type:TEXT;not null;index"`
    Domain       string    `gorm:"type:TEXT;not null"`
    URL          string    `gorm:"type:TEXT;not null"`
    Title        string    `gorm:"type:TEXT"`
    TokenCount   int       `gorm:"type:INTEGER"`
    AddedAt      time.Time `gorm:"type:TEXT"`
}

// CrawlJobRecord persists crawl job state
type CrawlJobRecord struct {
    Id            string     `gorm:"primaryKey;type:TEXT"`
    Domain        string     `gorm:"type:TEXT;not null;index"`
    SitemapURL    string     `gorm:"type:TEXT"`
    Status        string     `gorm:"type:TEXT;default:pending"`
    ConfigJSON    string     `gorm:"type:TEXT"` // Serialized CrawlConfig
    ProgressJSON  string     `gorm:"type:TEXT"` // Serialized CrawlProgress
    ErrorMessage  string     `gorm:"type:TEXT"`
    StartedAt     *time.Time `gorm:"type:TEXT"`
    CompletedAt   *time.Time `gorm:"type:TEXT"`
    CreatedAt     time.Time  `gorm:"type:TEXT"`
    UpdatedAt     time.Time  `gorm:"type:TEXT"`
}
```
