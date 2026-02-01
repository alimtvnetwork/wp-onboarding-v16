# Component: Full-Site Crawler

**Parent:** [GSearch CLI](./00-overview.md)  
**Version:** 1.0.0  
**Updated:** 2026-01-29  

---

## Summary

Full-site crawling and caching system for `gsearch` CLI that parses sitemaps, crawls entire domains, normalizes URLs, detects duplicates/redirects, and generates vector embeddings for RAG retrieval.

**Cross-References:**
- [Configuration](./02-configuration.md) - Crawler config keys
- [Database Schema](./03-database-schema.md) - Site cache models
- [RAG Export](./11-rag-export.md) - Memory generation
- [URL Context System](../../24-code-generation-system/32-url-context-system.md) - UI integration

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Full-Site Crawler                           │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐       │
│  │   Sitemap    │    │    URL       │    │   Redirect   │       │
│  │   Parser     │───▶│  Normalizer  │───▶│   Handler    │       │
│  └──────────────┘    └──────────────┘    └──────────────┘       │
│         │                   │                   │                │
│         ▼                   ▼                   ▼                │
│  ┌────────────────────────────────────────────────────────┐     │
│  │               Deduplication Engine                      │     │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │     │
│  │  │  URL Hash    │  │  Visited     │  │  Root DB     │  │     │
│  │  │  Comparison  │  │  Tracker     │  │  Cross-Check │  │     │
│  │  └──────────────┘  └──────────────┘  └──────────────┘  │     │
│  └────────────────────────────────────────────────────────┘     │
│                            │                                     │
│         ┌──────────────────┼──────────────────┐                 │
│         ▼                  ▼                  ▼                 │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐        │
│  │  Rate        │   │  Content     │   │  Vector      │        │
│  │  Limiter     │   │  Extractor   │   │  Embedder    │        │
│  └──────────────┘   └──────────────┘   └──────────────┘        │
│                            │                                     │
│                   ┌────────▼────────┐                           │
│                   │   Site Cache DB │                           │
│                   │  (per-domain)   │                           │
│                   └─────────────────┘                           │
└─────────────────────────────────────────────────────────────────┘
```

---

## Storage Layout

```
data/
└── full-site-cache/
    ├── example.com.db          # SQLite DB for example.com
    ├── docs.example.io.db      # SQLite DB for docs.example.io
    └── github.com.db           # SQLite DB for github.com
```

### Naming Convention

| Domain | DB File Name |
|--------|--------------|
| `https://example.com` | `example.com.db` |
| `https://www.example.com` | `example.com.db` (www stripped) |
| `https://docs.example.io` | `docs.example.io.db` |
| `http://sub.domain.org:8080` | `sub.domain.org_8080.db` |

---

## CLI Commands

```bash
# Crawl entire site from root URL
gsearch crawl https://example.com

# Crawl from sitemap URL
gsearch crawl https://example.com/sitemap.xml --sitemap

# With options
gsearch crawl https://docs.example.com \
  --delay 250ms \
  --max-pages 1000 \
  --depth 5 \
  --workers 4 \
  --vectors

# Check crawl status
gsearch crawl status --domain example.com

# List cached sites
gsearch crawl list

# Clear site cache
gsearch crawl clear --domain example.com
gsearch crawl clear --all --older-than 30d
```

### CLI Help Documentation

```
gsearch crawl - Crawl and cache entire websites for local search

USAGE:
    gsearch crawl <url> [options]
    gsearch crawl status [options]
    gsearch crawl list
    gsearch crawl clear [options]

ARGUMENTS:
    <url>       Root URL or sitemap URL to crawl (required for crawl)

OPTIONS:
    --sitemap, -s
        Treat URL as sitemap.xml (auto-detected if URL ends with .xml)

    --delay, -d <duration>
        Delay between requests (default: 250ms)
        Examples: 100ms, 250ms, 500ms, 1s

    --max-pages, -m <number>
        Maximum pages to crawl (default: 10000)

    --depth <number>
        Maximum link depth from root (default: 10)
        Set to 0 for unlimited depth

    --workers, -w <number>
        Concurrent crawler workers (default: 4)

    --vectors, -v
        Generate vector embeddings for RAG (default: false)
        Requires embedding model configured

    --respect-robots
        Respect robots.txt directives (default: true)

    --follow-redirects
        Follow HTTP redirects (default: true)

    --include <patterns>
        Comma-separated URL patterns to include
        Example: --include "/docs/*,/api/*"

    --exclude <patterns>
        Comma-separated URL patterns to exclude
        Example: --exclude "/admin/*,/login"

    --resume
        Resume interrupted crawl for domain

    --force
        Force re-crawl even if cached

STATUS OPTIONS:
    --domain <domain>
        Show status for specific domain

    --all
        Show all crawl statuses

CLEAR OPTIONS:
    --domain <domain>
        Clear cache for specific domain

    --all
        Clear all site caches

    --older-than <duration>
        Clear caches older than duration (e.g., 7d, 30d)

EXAMPLES:
    # Basic site crawl
    gsearch crawl https://docs.example.com

    # Crawl from sitemap with vector generation
    gsearch crawl https://example.com/sitemap.xml --sitemap --vectors

    # Careful crawl with delay
    gsearch crawl https://api.example.com --delay 500ms --max-pages 500

    # Resume interrupted crawl
    gsearch crawl https://docs.example.com --resume

    # Check progress
    gsearch crawl status --domain docs.example.com

SEE ALSO:
    gsearch search --help     Search commands
    gsearch cache --help      Cache management
    gsearch rag --help        RAG memory export
```

---

## Configuration

### Crawler Config Section

```json
{
  "crawler": {
    "enabled": true,
    "storagePath": "./data/full-site-cache",
    "defaultDelay": "250ms",
    "maxPagesPerSite": 10000,
    "maxDepth": 10,
    "workers": 4,
    "timeout": "30s",
    "respectRobotsTxt": true,
    "followRedirects": true,
    "maxRedirects": 5,
    
    "urlNormalization": {
      "trailingSlash": "add",
      "stripWww": true,
      "lowercasePath": true,
      "sortQueryParams": true,
      "removeTrackingParams": true,
      "trackingParams": [
        "utm_source", "utm_medium", "utm_campaign",
        "utm_term", "utm_content", "fbclid", "gclid",
        "ref", "source", "mc_cid", "mc_eid"
      ]
    },
    
    "deduplication": {
      "enabled": true,
      "checkRootDB": true,
      "hashAlgorithm": "sha256",
      "contentHashEnabled": true
    },
    
    "contentExtraction": {
      "extractTitle": true,
      "extractMetaDescription": true,
      "extractHeadings": true,
      "extractMainContent": true,
      "removeScripts": true,
      "removeStyles": true,
      "minContentLength": 100
    },
    
    "vectors": {
      "enabled": false,
      "model": "all-MiniLM-L6-v2",
      "dimensions": 384,
      "chunkSize": 512,
      "chunkOverlap": 50
    },
    
    "rateLimit": {
      "requestsPerSecond": 4,
      "burstSize": 8,
      "perDomainLimits": {}
    }
  }
}
```

### Go Configuration Struct

```go
type CrawlerConfig struct {
    Enabled          bool                  `mapstructure:"enabled"`
    StoragePath      string                `mapstructure:"storagePath"`
    DefaultDelay     Duration              `mapstructure:"defaultDelay"`
    MaxPagesPerSite  int                   `mapstructure:"maxPagesPerSite"`
    MaxDepth         int                   `mapstructure:"maxDepth"`
    Workers          int                   `mapstructure:"workers"`
    Timeout          Duration              `mapstructure:"timeout"`
    RespectRobotsTxt bool                  `mapstructure:"respectRobotsTxt"`
    FollowRedirects  bool                  `mapstructure:"followRedirects"`
    MaxRedirects     int                   `mapstructure:"maxRedirects"`
    URLNormalization URLNormalizationConfig `mapstructure:"urlNormalization"`
    Deduplication    DeduplicationConfig   `mapstructure:"deduplication"`
    ContentExtraction ContentExtractionConfig `mapstructure:"contentExtraction"`
    Vectors          VectorConfig          `mapstructure:"vectors"`
    RateLimit        RateLimitConfig       `mapstructure:"rateLimit"`
}

type URLNormalizationConfig struct {
    TrailingSlash       string   `mapstructure:"trailingSlash"`       // "add", "remove", "preserve"
    StripWww            bool     `mapstructure:"stripWww"`
    LowercasePath       bool     `mapstructure:"lowercasePath"`
    SortQueryParams     bool     `mapstructure:"sortQueryParams"`
    RemoveTrackingParams bool    `mapstructure:"removeTrackingParams"`
    TrackingParams      []string `mapstructure:"trackingParams"`
}

type DeduplicationConfig struct {
    Enabled            bool   `mapstructure:"enabled"`
    CheckRootDB        bool   `mapstructure:"checkRootDB"`
    HashAlgorithm      string `mapstructure:"hashAlgorithm"`
    ContentHashEnabled bool   `mapstructure:"contentHashEnabled"`
}

type ContentExtractionConfig struct {
    ExtractTitle           bool `mapstructure:"extractTitle"`
    ExtractMetaDescription bool `mapstructure:"extractMetaDescription"`
    ExtractHeadings        bool `mapstructure:"extractHeadings"`
    ExtractMainContent     bool `mapstructure:"extractMainContent"`
    RemoveScripts          bool `mapstructure:"removeScripts"`
    RemoveStyles           bool `mapstructure:"removeStyles"`
    MinContentLength       int  `mapstructure:"minContentLength"`
}

type VectorConfig struct {
    Enabled      bool   `mapstructure:"enabled"`
    Model        string `mapstructure:"model"`
    Dimensions   int    `mapstructure:"dimensions"`
    ChunkSize    int    `mapstructure:"chunkSize"`
    ChunkOverlap int    `mapstructure:"chunkOverlap"`
}

type RateLimitConfig struct {
    RequestsPerSecond float64           `mapstructure:"requestsPerSecond"`
    BurstSize         int               `mapstructure:"burstSize"`
    PerDomainLimits   map[string]float64 `mapstructure:"perDomainLimits"`
}
```

---

## URL Normalization

### Normalization Rules

The URL normalizer ensures consistent URL representation for accurate deduplication.

```go
// pkg/crawler/normalizer.go

package crawler

import (
    "net/url"
    "sort"
    "strings"
)

type URLNormalizer struct {
    config URLNormalizationConfig
}

func NewURLNormalizer(cfg URLNormalizationConfig) *URLNormalizer {
    return &URLNormalizer{config: cfg}
}

// Normalize applies all normalization rules to a URL
func (n *URLNormalizer) Normalize(rawURL string) (string, error) {
    // Parse URL
    u, err := url.Parse(rawURL)
    if err != nil {
        return "", err
    }
    
    // 1. Lowercase scheme
    u.Scheme = strings.ToLower(u.Scheme)
    
    // 2. Strip www prefix if configured
    if n.config.StripWww {
        u.Host = strings.TrimPrefix(u.Host, "www.")
    }
    
    // 3. Lowercase host
    u.Host = strings.ToLower(u.Host)
    
    // 4. Lowercase path if configured
    if n.config.LowercasePath {
        u.Path = strings.ToLower(u.Path)
    }
    
    // 5. Handle trailing slash
    u.Path = n.normalizeTrailingSlash(u.Path)
    
    // 6. Remove tracking parameters
    if n.config.RemoveTrackingParams {
        u.RawQuery = n.removeTrackingParams(u.Query()).Encode()
    }
    
    // 7. Sort query parameters
    if n.config.SortQueryParams {
        u.RawQuery = n.sortQueryParams(u.Query()).Encode()
    }
    
    // 8. Remove fragment
    u.Fragment = ""
    
    // 9. Remove default ports
    u.Host = n.removeDefaultPort(u.Host, u.Scheme)
    
    return u.String(), nil
}

func (n *URLNormalizer) normalizeTrailingSlash(path string) string {
    if path == "" || path == "/" {
        return "/"
    }
    
    switch n.config.TrailingSlash {
    case "add":
        if !strings.HasSuffix(path, "/") && !hasFileExtension(path) {
            return path + "/"
        }
    case "remove":
        return strings.TrimSuffix(path, "/")
    }
    return path
}

func (n *URLNormalizer) removeTrackingParams(params url.Values) url.Values {
    trackingSet := make(map[string]bool)
    for _, p := range n.config.TrackingParams {
        trackingSet[strings.ToLower(p)] = true
    }
    
    filtered := make(url.Values)
    for key, values := range params {
        if !trackingSet[strings.ToLower(key)] {
            filtered[key] = values
        }
    }
    return filtered
}

func (n *URLNormalizer) sortQueryParams(params url.Values) url.Values {
    sorted := make(url.Values)
    keys := make([]string, 0, len(params))
    for k := range params {
        keys = append(keys, k)
    }
    sort.Strings(keys)
    for _, k := range keys {
        sorted[k] = params[k]
    }
    return sorted
}

func (n *URLNormalizer) removeDefaultPort(host, scheme string) string {
    if strings.HasSuffix(host, ":80") && scheme == "http" {
        return strings.TrimSuffix(host, ":80")
    }
    if strings.HasSuffix(host, ":443") && scheme == "https" {
        return strings.TrimSuffix(host, ":443")
    }
    return host
}

func hasFileExtension(path string) bool {
    extensions := []string{".html", ".htm", ".php", ".aspx", ".jsp", ".pdf", ".xml", ".json"}
    for _, ext := range extensions {
        if strings.HasSuffix(strings.ToLower(path), ext) {
            return true
        }
    }
    return false
}
```

### Normalization Examples

| Input URL | Normalized Output |
|-----------|-------------------|
| `https://www.Example.COM/page` | `https://example.com/page/` |
| `https://example.com/page/` | `https://example.com/page/` |
| `https://example.com/page` | `https://example.com/page/` |
| `http://example.com:80/path` | `http://example.com/path/` |
| `https://example.com/Path?b=2&a=1` | `https://example.com/path/?a=1&b=2` |
| `https://example.com?utm_source=google` | `https://example.com/` |
| `https://example.com/file.pdf` | `https://example.com/file.pdf` |

---

## Deduplication Engine

### Deduplication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    Deduplication Check                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  URL Input                                                       │
│      │                                                           │
│      ▼                                                           │
│  ┌──────────────────┐                                           │
│  │ Normalize URL    │                                           │
│  └────────┬─────────┘                                           │
│           │                                                      │
│           ▼                                                      │
│  ┌──────────────────┐     ┌──────────────────┐                  │
│  │ Check Site-Local │ NO  │ Check Root       │                  │
│  │ VisitedURLs      │────▶│ SearchResult DB  │                  │
│  └────────┬─────────┘     └────────┬─────────┘                  │
│           │ YES                    │                             │
│           ▼                        ▼                             │
│  ┌──────────────────┐     ┌──────────────────┐                  │
│  │ SKIP: Already    │     │ Check: URL Hash  │                  │
│  │ Visited          │     │ in PageContent   │                  │
│  └──────────────────┘     └────────┬─────────┘                  │
│                                    │                             │
│                           ┌────────┴────────┐                   │
│                           │ YES             │ NO                │
│                           ▼                 ▼                   │
│              ┌──────────────────┐  ┌──────────────────┐         │
│              │ SKIP: Exists in  │  │ PROCEED: Fetch   │         │
│              │ Root DB          │  │ and Store        │         │
│              └──────────────────┘  └──────────────────┘         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Deduplication Service

```go
// pkg/crawler/dedup.go

package crawler

import (
    "crypto/sha256"
    "encoding/hex"
    "sync"
    
    "gorm.io/gorm"
)

type DeduplicationService struct {
    config     DeduplicationConfig
    siteDB     *gorm.DB    // Per-site database
    rootDB     *gorm.DB    // Main search.db
    normalizer *URLNormalizer
    
    // In-memory visited set for current crawl session
    visitedMu  sync.RWMutex
    visited    map[string]bool
}

type URLCheckResult struct {
    IsDuplicate bool
    Reason      string
    ExistingURL string
}

func NewDeduplicationService(
    cfg DeduplicationConfig,
    siteDB, rootDB *gorm.DB,
    normalizer *URLNormalizer,
) *DeduplicationService {
    return &DeduplicationService{
        config:     cfg,
        siteDB:     siteDB,
        rootDB:     rootDB,
        normalizer: normalizer,
        visited:    make(map[string]bool),
    }
}

// CheckURL determines if URL should be crawled
func (d *DeduplicationService) CheckURL(rawURL string) (*URLCheckResult, error) {
    // 1. Normalize URL
    normalizedURL, err := d.normalizer.Normalize(rawURL)
    if err != nil {
        return nil, err
    }
    
    urlHash := d.hashURL(normalizedURL)
    
    // 2. Check in-memory visited set (current session)
    d.visitedMu.RLock()
    if d.visited[urlHash] {
        d.visitedMu.RUnlock()
        return &URLCheckResult{
            IsDuplicate: true,
            Reason:      "visited_this_session",
            ExistingURL: normalizedURL,
        }, nil
    }
    d.visitedMu.RUnlock()
    
    // 3. Check site-local database
    var siteVisit SiteCrawlURL
    if err := d.siteDB.Where("url_hash = ?", urlHash).First(&siteVisit).Error; err == nil {
        return &URLCheckResult{
            IsDuplicate: true,
            Reason:      "exists_in_site_cache",
            ExistingURL: siteVisit.URL,
        }, nil
    }
    
    // 4. Check root database (search results from other searches)
    if d.config.CheckRootDB {
        var searchResult SearchResult
        if err := d.rootDB.Where("url = ?", normalizedURL).First(&searchResult).Error; err == nil {
            // Check if we already have the content
            var pageContent PageContent
            if err := d.rootDB.Where("search_result_id = ?", searchResult.Id).First(&pageContent).Error; err == nil {
                return &URLCheckResult{
                    IsDuplicate: true,
                    Reason:      "exists_in_root_db",
                    ExistingURL: normalizedURL,
                }, nil
            }
        }
    }
    
    // 5. Mark as visited
    d.visitedMu.Lock()
    d.visited[urlHash] = true
    d.visitedMu.Unlock()
    
    return &URLCheckResult{
        IsDuplicate: false,
    }, nil
}

// MarkVisited explicitly marks a URL as visited
func (d *DeduplicationService) MarkVisited(rawURL string) error {
    normalizedURL, err := d.normalizer.Normalize(rawURL)
    if err != nil {
        return err
    }
    
    urlHash := d.hashURL(normalizedURL)
    
    d.visitedMu.Lock()
    d.visited[urlHash] = true
    d.visitedMu.Unlock()
    
    return nil
}

// hashURL creates a hash of the normalized URL
func (d *DeduplicationService) hashURL(url string) string {
    hash := sha256.Sum256([]byte(url))
    return hex.EncodeToString(hash[:])
}

// ContentHash generates a hash of page content for content-based dedup
func (d *DeduplicationService) ContentHash(content string) string {
    hash := sha256.Sum256([]byte(content))
    return hex.EncodeToString(hash[:])
}
```

---

## Redirect Handling

```go
// pkg/crawler/redirect.go

package crawler

import (
    "net/http"
    "net/url"
)

type RedirectHandler struct {
    normalizer *URLNormalizer
    dedup      *DeduplicationService
    maxHops    int
}

type RedirectResult struct {
    FinalURL       string
    HopCount       int
    WasRedirected  bool
    SkipReason     string
    ShouldSkip     bool
}

func NewRedirectHandler(
    normalizer *URLNormalizer,
    dedup *DeduplicationService,
    maxHops int,
) *RedirectHandler {
    return &RedirectHandler{
        normalizer: normalizer,
        dedup:      dedup,
        maxHops:    maxHops,
    }
}

// FollowRedirects follows redirects and checks each hop for duplicates
func (r *RedirectHandler) FollowRedirects(initialURL string) (*RedirectResult, error) {
    client := &http.Client{
        CheckRedirect: func(req *http.Request, via []*http.Request) error {
            if len(via) >= r.maxHops {
                return http.ErrUseLastResponse
            }
            return nil
        },
    }
    
    resp, err := client.Head(initialURL)
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()
    
    finalURL := resp.Request.URL.String()
    normalizedFinal, err := r.normalizer.Normalize(finalURL)
    if err != nil {
        return nil, err
    }
    
    // Check if final URL is a duplicate
    dupCheck, err := r.dedup.CheckURL(normalizedFinal)
    if err != nil {
        return nil, err
    }
    
    if dupCheck.IsDuplicate {
        return &RedirectResult{
            FinalURL:      normalizedFinal,
            HopCount:      len(resp.Request.Response.Request.URL.String()),
            WasRedirected: initialURL != finalURL,
            ShouldSkip:    true,
            SkipReason:    dupCheck.Reason,
        }, nil
    }
    
    return &RedirectResult{
        FinalURL:      normalizedFinal,
        WasRedirected: initialURL != finalURL,
        ShouldSkip:    false,
    }, nil
}
```

---

## Database Schema (Per-Site)

### Site Cache Models

```go
// pkg/crawler/models.go

package crawler

import (
    "time"
    
    "github.com/google/uuid"
    "gorm.io/gorm"
)

type CrawlStatus string

const (
    CrawlPending     CrawlStatus = "pending"
    CrawlInProgress  CrawlStatus = "in_progress"
    CrawlCompleted   CrawlStatus = "completed"
    CrawlFailed      CrawlStatus = "failed"
    CrawlPaused      CrawlStatus = "paused"
)

// SiteCrawlSession tracks a crawl job for a domain
type SiteCrawlSession struct {
    Id            string      `gorm:"primaryKey;type:TEXT"`
    Domain        string      `gorm:"type:TEXT;not null;index"`
    SitemapURL    string      `gorm:"type:TEXT"`
    Status        CrawlStatus `gorm:"type:TEXT;default:pending"`
    TotalURLs     int         `gorm:"type:INTEGER;default:0"`
    CrawledURLs   int         `gorm:"type:INTEGER;default:0"`
    FailedURLs    int         `gorm:"type:INTEGER;default:0"`
    SkippedURLs   int         `gorm:"type:INTEGER;default:0"`
    StartedAt     *time.Time  `gorm:"type:TEXT"`
    CompletedAt   *time.Time  `gorm:"type:TEXT"`
    LastResumedAt *time.Time  `gorm:"type:TEXT"`
    CreatedAt     time.Time   `gorm:"type:TEXT"`
    UpdatedAt     time.Time   `gorm:"type:TEXT"`
    
    URLs []SiteCrawlURL `gorm:"foreignKey:SessionId;constraint:OnDelete:CASCADE"`
}

func (s *SiteCrawlSession) BeforeCreate(tx *gorm.DB) error {
    if s.Id == "" {
        s.Id = uuid.New().String()
    }
    return nil
}

// SiteCrawlURL tracks individual URL crawl status
type SiteCrawlURL struct {
    Id            string    `gorm:"primaryKey;type:TEXT"`
    SessionId     string    `gorm:"type:TEXT;not null;index"`
    URL           string    `gorm:"type:TEXT;not null"`
    URLHash       string    `gorm:"type:TEXT;not null;uniqueIndex"`
    NormalizedURL string    `gorm:"type:TEXT"`
    Status        string    `gorm:"type:TEXT;default:pending"` // pending, crawled, failed, skipped
    Depth         int       `gorm:"type:INTEGER;default:0"`
    StatusCode    int       `gorm:"type:INTEGER"`
    RedirectURL   string    `gorm:"type:TEXT"`
    ErrorMessage  string    `gorm:"type:TEXT"`
    CrawledAt     *time.Time `gorm:"type:TEXT"`
    CreatedAt     time.Time  `gorm:"type:TEXT"`
    
    Session SiteCrawlSession `gorm:"foreignKey:SessionId"`
    Content *SitePageContent `gorm:"foreignKey:CrawlURLId;constraint:OnDelete:CASCADE"`
}

func (u *SiteCrawlURL) BeforeCreate(tx *gorm.DB) error {
    if u.Id == "" {
        u.Id = uuid.New().String()
    }
    return nil
}

// SitePageContent stores extracted content
type SitePageContent struct {
    Id              string    `gorm:"primaryKey;type:TEXT"`
    CrawlURLId      string    `gorm:"type:TEXT;uniqueIndex"`
    Title           string    `gorm:"type:TEXT"`
    MetaDescription string    `gorm:"type:TEXT"`
    Headings        string    `gorm:"type:TEXT"` // JSON array
    MainContent     string    `gorm:"type:TEXT"`
    ContentHash     string    `gorm:"type:TEXT;index"`
    WordCount       int       `gorm:"type:INTEGER"`
    ExtractedAt     time.Time `gorm:"type:TEXT"`
    
    CrawlURL SiteCrawlURL `gorm:"foreignKey:CrawlURLId"`
    Vectors  []SiteContentVector `gorm:"foreignKey:ContentId;constraint:OnDelete:CASCADE"`
}

func (c *SitePageContent) BeforeCreate(tx *gorm.DB) error {
    if c.Id == "" {
        c.Id = uuid.New().String()
    }
    return nil
}

// SiteContentVector stores embeddings for RAG
type SiteContentVector struct {
    Id         string    `gorm:"primaryKey;type:TEXT"`
    ContentId  string    `gorm:"type:TEXT;not null;index"`
    ChunkIndex int       `gorm:"type:INTEGER"`
    ChunkText  string    `gorm:"type:TEXT"`
    Vector     []byte    `gorm:"type:BLOB"` // Float32 array serialized
    CreatedAt  time.Time `gorm:"type:TEXT"`
    
    Content SitePageContent `gorm:"foreignKey:ContentId"`
}

func (v *SiteContentVector) BeforeCreate(tx *gorm.DB) error {
    if v.Id == "" {
        v.Id = uuid.New().String()
    }
    return nil
}
```

---

## Sitemap Parser

```go
// pkg/crawler/sitemap.go

package crawler

import (
    "encoding/xml"
    "io"
    "net/http"
    "strings"
    "time"
)

type SitemapIndex struct {
    XMLName  xml.Name      `xml:"sitemapindex"`
    Sitemaps []SitemapLoc  `xml:"sitemap"`
}

type SitemapLoc struct {
    Loc     string `xml:"loc"`
    LastMod string `xml:"lastmod,omitempty"`
}

type URLSet struct {
    XMLName xml.Name      `xml:"urlset"`
    URLs    []SitemapURL  `xml:"url"`
}

type SitemapURL struct {
    Loc        string  `xml:"loc"`
    LastMod    string  `xml:"lastmod,omitempty"`
    ChangeFreq string  `xml:"changefreq,omitempty"`
    Priority   float64 `xml:"priority,omitempty"`
}

type SitemapParser struct {
    client     *http.Client
    normalizer *URLNormalizer
}

func NewSitemapParser(normalizer *URLNormalizer) *SitemapParser {
    return &SitemapParser{
        client: &http.Client{
            Timeout: 30 * time.Second,
        },
        normalizer: normalizer,
    }
}

// Parse fetches and parses a sitemap, handling both index and urlset formats
func (p *SitemapParser) Parse(sitemapURL string) ([]string, error) {
    resp, err := p.client.Get(sitemapURL)
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()
    
    data, err := io.ReadAll(resp.Body)
    if err != nil {
        return nil, err
    }
    
    // Try parsing as sitemap index first
    var sitemapIndex SitemapIndex
    if err := xml.Unmarshal(data, &sitemapIndex); err == nil && len(sitemapIndex.Sitemaps) > 0 {
        return p.parseIndex(sitemapIndex)
    }
    
    // Parse as regular urlset
    var urlSet URLSet
    if err := xml.Unmarshal(data, &urlSet); err != nil {
        return nil, err
    }
    
    urls := make([]string, 0, len(urlSet.URLs))
    for _, u := range urlSet.URLs {
        normalized, err := p.normalizer.Normalize(u.Loc)
        if err != nil {
            continue
        }
        urls = append(urls, normalized)
    }
    
    return urls, nil
}

func (p *SitemapParser) parseIndex(index SitemapIndex) ([]string, error) {
    var allURLs []string
    
    for _, sitemap := range index.Sitemaps {
        urls, err := p.Parse(sitemap.Loc)
        if err != nil {
            continue // Skip failed sitemaps
        }
        allURLs = append(allURLs, urls...)
    }
    
    return allURLs, nil
}

// DiscoverSitemap attempts to find sitemap.xml for a domain
func (p *SitemapParser) DiscoverSitemap(domain string) (string, error) {
    candidates := []string{
        "https://" + domain + "/sitemap.xml",
        "https://" + domain + "/sitemap_index.xml",
        "https://" + domain + "/sitemaps/sitemap.xml",
    }
    
    for _, url := range candidates {
        resp, err := p.client.Head(url)
        if err != nil {
            continue
        }
        resp.Body.Close()
        
        if resp.StatusCode == 200 {
            return url, nil
        }
    }
    
    return "", ErrSitemapNotFound
}
```

---

## Error Codes

| Code | Name | Description |
|------|------|-------------|
| 6700 | ERR_CRAWLER_INIT_FAILED | Crawler initialization failed |
| 6701 | ERR_SITEMAP_FETCH_FAILED | Failed to fetch sitemap |
| 6702 | ERR_SITEMAP_PARSE_FAILED | Failed to parse sitemap XML |
| 6703 | ERR_SITEMAP_NOT_FOUND | No sitemap found for domain |
| 6710 | ERR_URL_NORMALIZE_FAILED | URL normalization failed |
| 6711 | ERR_URL_INVALID | Invalid URL format |
| 6712 | ERR_URL_BLOCKED_DOMAIN | Domain blocked by configuration |
| 6720 | ERR_REDIRECT_LOOP | Redirect loop detected |
| 6721 | ERR_REDIRECT_MAX_HOPS | Max redirect hops exceeded |
| 6722 | ERR_REDIRECT_EXTERNAL | Redirect to external domain |
| 6730 | ERR_CONTENT_FETCH_FAILED | Failed to fetch page content |
| 6731 | ERR_CONTENT_TOO_LARGE | Content exceeds size limit |
| 6732 | ERR_CONTENT_PARSE_FAILED | Failed to parse HTML content |
| 6740 | ERR_VECTOR_EMBED_FAILED | Vector embedding generation failed |
| 6741 | ERR_VECTOR_MODEL_UNAVAILABLE | Embedding model not available |
| 6750 | ERR_SITE_DB_OPEN_FAILED | Failed to open site database |
| 6751 | ERR_SITE_DB_MIGRATE_FAILED | Site database migration failed |
| 6760 | ERR_RATE_LIMIT_EXCEEDED | Internal rate limit exceeded |
| 6761 | ERR_ROBOTS_DISALLOWED | URL disallowed by robots.txt |

---

## Integration with Main App

### UI URL Context Panel

The main application's AI panel includes a "URL Context" section where users can:

1. **Add full domain** - Triggers `gsearch crawl https://domain.com`
2. **Add sitemap URL** - Triggers `gsearch crawl https://domain.com/sitemap.xml --sitemap`
3. **View cached sites** - Shows list from `gsearch crawl list`
4. **Check progress** - Real-time updates from `gsearch crawl status`
5. **Search cached content** - Query the site-specific vector database

See [URL Context System](../../24-code-generation-system/32-url-context-system.md) for UI specification.

---

## SSRF Protection

```go
// pkg/crawler/security.go

package crawler

import (
    "net"
    "net/url"
    "strings"
)

var privateNetworks = []string{
    "10.0.0.0/8",
    "172.16.0.0/12",
    "192.168.0.0/16",
    "127.0.0.0/8",
    "169.254.0.0/16",  // Link-local / AWS metadata
    "::1/128",
    "fc00::/7",
    "fe80::/10",
}

type SSRFProtector struct {
    privateCIDRs []*net.IPNet
    blockedHosts []string
}

func NewSSRFProtector() *SSRFProtector {
    cidrs := make([]*net.IPNet, 0, len(privateNetworks))
    for _, cidr := range privateNetworks {
        _, network, _ := net.ParseCIDR(cidr)
        cidrs = append(cidrs, network)
    }
    
    return &SSRFProtector{
        privateCIDRs: cidrs,
        blockedHosts: []string{
            "metadata.google.internal",
            "169.254.169.254",
        },
    }
}

// IsURLSafe checks if URL is safe to fetch (not internal/private)
func (s *SSRFProtector) IsURLSafe(rawURL string) (bool, error) {
    u, err := url.Parse(rawURL)
    if err != nil {
        return false, err
    }
    
    host := u.Hostname()
    
    // Check blocked hosts
    for _, blocked := range s.blockedHosts {
        if strings.EqualFold(host, blocked) {
            return false, nil
        }
    }
    
    // Resolve hostname
    ips, err := net.LookupIP(host)
    if err != nil {
        return false, err
    }
    
    // Check all IPs against private networks
    for _, ip := range ips {
        for _, cidr := range s.privateCIDRs {
            if cidr.Contains(ip) {
                return false, nil
            }
        }
    }
    
    return true, nil
}
```

---

## Performance Considerations

| Metric | Target | Configuration |
|--------|--------|---------------|
| Pages/second | 4 | `rateLimit.requestsPerSecond` |
| Memory per 10K pages | < 500MB | Worker pool size |
| DB write batch size | 100 | Buffered writes |
| Vector generation | Background | Async after crawl |
| Resume capability | Yes | Checkpoint every 100 pages |
