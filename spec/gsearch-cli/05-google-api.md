# Component: Google API

**Parent:** [Golang Search CLI](./00-overview.md)  
**Version:** 1.2.0  
**Updated:** 2026-01-28  

---

## Summary

Google Custom Search API and Search Console API integration for reliable, structured search results.

---

## API Options

| API | Purpose | Quota | Cost |
|-----|---------|-------|------|
| Custom Search JSON API | Web search | 100/day free | $5/1000 queries |
| Search Console API | Site performance | High | Free |

---

## Dependencies

- `google.golang.org/api/customsearch/v1` — Custom Search API
- `google.golang.org/api/searchconsole/v1` — Search Console API
- `golang.org/x/oauth2/google` — Authentication

---

## Custom Search API

### Setup

```go
package search

import (
    "context"
    "fmt"
    
    "google.golang.org/api/customsearch/v1"
    "google.golang.org/api/option"
)

type GoogleCustomSearch struct {
    service *customsearch.Service
    apiKey  string
    cx      string // Custom Search Engine ID
    quota   *QuotaTracker
}

func NewGoogleCustomSearch(apiKey, cx string, dailyQuota int) (*GoogleCustomSearch, error) {
    ctx := context.Background()
    
    service, err := customsearch.NewService(ctx, option.WithAPIKey(apiKey))
    if err != nil {
        return nil, fmt.Errorf("create service: %w", err)
    }
    
    return &GoogleCustomSearch{
        service: service,
        apiKey:  apiKey,
        cx:      cx,
        quota:   NewQuotaTracker(dailyQuota),
    }, nil
}

func (g *GoogleCustomSearch) ID() string        { return "google_api" }
func (g *GoogleCustomSearch) Name() string      { return "Google Custom Search API" }
func (g *GoogleCustomSearch) RequiresAPI() bool { return true }

func (g *GoogleCustomSearch) IsAvailable() bool {
    return g.apiKey != "" && g.cx != "" && !g.quota.IsExhausted()
}
```

### Search Implementation

```go
func (g *GoogleCustomSearch) Search(ctx context.Context, query string, opts SearchOptions) ([]Result, error) {
    if !g.quota.CanMakeRequest() {
        return nil, &QuotaExhaustedError{API: "Google Custom Search"}
    }
    
    call := g.service.Cse.List()
    call.Cx(g.cx)
    call.Q(query)
    call.Num(int64(opts.MaxResults))
    
    resp, err := call.Context(ctx).Do()
    if err != nil {
        return nil, g.handleError(err)
    }
    
    g.quota.RecordRequest()
    
    return g.parseResults(resp), nil
}

func (g *GoogleCustomSearch) parseResults(resp *customsearch.Search) []Result {
    var results []Result
    
    for i, item := range resp.Items {
        results = append(results, Result{
            Title:       item.Title,
            Description: item.Snippet,
            URL:         item.Link,
            Position:    i + 1,
        })
    }
    
    return results
}

func (g *GoogleCustomSearch) handleError(err error) error {
    errStr := err.Error()
    
    if strings.Contains(errStr, "403") || strings.Contains(errStr, "quota") {
        g.quota.MarkExhausted()
        return &QuotaExhaustedError{API: "Google Custom Search"}
    }
    
    if strings.Contains(errStr, "429") {
        return &BlockedError{StatusCode: 429, Message: "rate limited"}
    }
    
    return &NetworkError{Err: err}
}
```

---

## Search Console API

### Setup

```go
type GoogleSearchConsole struct {
    service *searchconsole.Service
    siteURL string
}

func NewGoogleSearchConsole(credentialsPath, siteURL string) (*GoogleSearchConsole, error) {
    ctx := context.Background()
    
    // Read credentials file
    creds, err := os.ReadFile(credentialsPath)
    if err != nil {
        return nil, fmt.Errorf("read credentials: %w", err)
    }
    
    // Create JWT config
    config, err := google.JWTConfigFromJSON(creds, searchconsole.WebmastersReadonlyScope)
    if err != nil {
        return nil, fmt.Errorf("create config: %w", err)
    }
    
    client := config.Client(ctx)
    
    service, err := searchconsole.NewService(ctx, option.WithHTTPClient(client))
    if err != nil {
        return nil, fmt.Errorf("create service: %w", err)
    }
    
    return &GoogleSearchConsole{
        service: service,
        siteURL: siteURL,
    }, nil
}

func (g *GoogleSearchConsole) ID() string        { return "search_console" }
func (g *GoogleSearchConsole) Name() string      { return "Google Search Console" }
func (g *GoogleSearchConsole) RequiresAPI() bool { return true }
func (g *GoogleSearchConsole) IsAvailable() bool { return g.service != nil }
```

### Query Analytics

```go
// Search Console provides analytics, not direct search results
// Use for keyword research and performance data

type SearchAnalytics struct {
    Query       string
    Clicks      int64
    Impressions int64
    CTR         float64
    Position    float64
}

func (g *GoogleSearchConsole) GetKeywordAnalytics(ctx context.Context, startDate, endDate string) ([]SearchAnalytics, error) {
    req := &searchconsole.SearchAnalyticsQueryRequest{
        StartDate:  startDate,
        EndDate:    endDate,
        Dimensions: []string{"query"},
        RowLimit:   1000,
    }
    
    resp, err := g.service.Searchanalytics.Query(g.siteURL, req).Context(ctx).Do()
    if err != nil {
        return nil, fmt.Errorf("query analytics: %w", err)
    }
    
    var analytics []SearchAnalytics
    for _, row := range resp.Rows {
        analytics = append(analytics, SearchAnalytics{
            Query:       row.Keys[0],
            Clicks:      int64(row.Clicks),
            Impressions: int64(row.Impressions),
            CTR:         row.Ctr,
            Position:    row.Position,
        })
    }
    
    return analytics, nil
}
```

---

## Quota Tracking

```go
type QuotaTracker struct {
    dailyLimit int
    used       int
    resetTime  time.Time
    mu         sync.Mutex
}

func NewQuotaTracker(dailyLimit int) *QuotaTracker {
    return &QuotaTracker{
        dailyLimit: dailyLimit,
        resetTime:  getNextMidnightUTC(),
    }
}

func (q *QuotaTracker) CanMakeRequest() bool {
    q.mu.Lock()
    defer q.mu.Unlock()
    
    q.checkReset()
    return q.used < q.dailyLimit
}

func (q *QuotaTracker) RecordRequest() {
    q.mu.Lock()
    defer q.mu.Unlock()
    
    q.checkReset()
    q.used++
}

func (q *QuotaTracker) IsExhausted() bool {
    q.mu.Lock()
    defer q.mu.Unlock()
    
    q.checkReset()
    return q.used >= q.dailyLimit
}

func (q *QuotaTracker) MarkExhausted() {
    q.mu.Lock()
    defer q.mu.Unlock()
    
    q.used = q.dailyLimit
}

func (q *QuotaTracker) checkReset() {
    if time.Now().After(q.resetTime) {
        q.used = 0
        q.resetTime = getNextMidnightUTC()
    }
}

func getNextMidnightUTC() time.Time {
    now := time.Now().UTC()
    return time.Date(now.Year(), now.Month(), now.Day()+1, 0, 0, 0, 0, time.UTC)
}
```

---

## Configuration

```json
{
  "apis": {
    "googleCustomSearch": {
      "enabled": true,
      "apiKeyEnv": "GOOGLE_CSE_API_KEY",
      "engineId": "your-search-engine-id",
      "dailyQuota": 100
    },
    "googleSearchConsole": {
      "enabled": false,
      "credentialsPath": "./google-credentials.json",
      "siteUrl": "https://your-site.com"
    }
  }
}
```

---

## Error Types

```go
type QuotaExhaustedError struct {
    API string
}

func (e *QuotaExhaustedError) Error() string {
    return fmt.Sprintf("%s quota exhausted", e.API)
}
```

---

## Related Specs

- [Configuration](./02-configuration.md) — API settings
- [Method Switching](./08-method-switching.md) — Fallback handling
