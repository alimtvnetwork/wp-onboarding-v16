# Component: Bing Search

**Parent:** [Golang Search CLI](./00-overview.md)  
**Version:** 1.2.0  
**Updated:** 2026-01-28  

---

## Summary

Bing Web Search API integration with structured JSON responses and generous free tier.

---

## API Details

| Feature | Value |
|---------|-------|
| Endpoint | `https://api.bing.microsoft.com/v7.0/search` |
| Free Tier | 1,000 calls/month |
| Paid Tier | $3 per 1,000 calls |
| Rate Limit | 3 calls/second |

---

## Dependencies

- `net/http` — HTTP client
- `encoding/json` — Response parsing

---

## Implementation

### Search Method

```go
package search

import (
    "context"
    "encoding/json"
    "fmt"
    "net/http"
    "net/url"
    "time"
)

type BingSearch struct {
    client   *http.Client
    apiKey   string
    endpoint string
    quota    *QuotaTracker
}

func NewBingSearch(apiKey string, cfg *config.BingAPIConfig) *BingSearch {
    endpoint := cfg.Endpoint
    if endpoint == "" {
        endpoint = "https://api.bing.microsoft.com/v7.0/search"
    }
    
    return &BingSearch{
        client: &http.Client{
            Timeout: 30 * time.Second,
        },
        apiKey:   apiKey,
        endpoint: endpoint,
        quota:    NewQuotaTracker(1000), // Monthly quota
    }
}

func (b *BingSearch) ID() string        { return "bing" }
func (b *BingSearch) Name() string      { return "Bing Search API" }
func (b *BingSearch) RequiresAPI() bool { return true }

func (b *BingSearch) IsAvailable() bool {
    return b.apiKey != "" && !b.quota.IsExhausted()
}
```

### Search Execution

```go
func (b *BingSearch) Search(ctx context.Context, query string, opts SearchOptions) ([]Result, error) {
    if !b.quota.CanMakeRequest() {
        return nil, &QuotaExhaustedError{API: "Bing Search"}
    }
    
    params := url.Values{}
    params.Set("q", query)
    params.Set("count", fmt.Sprintf("%d", opts.MaxResults))
    params.Set("mkt", "en-US")
    params.Set("responseFilter", "Webpages")
    params.Set("textDecorations", "false")
    params.Set("textFormat", "Raw")
    
    reqURL := b.endpoint + "?" + params.Encode()
    
    req, err := http.NewRequestWithContext(ctx, "GET", reqURL, nil)
    if err != nil {
        return nil, fmt.Errorf("create request: %w", err)
    }
    
    req.Header.Set("Ocp-Apim-Subscription-Key", b.apiKey)
    
    resp, err := b.client.Do(req)
    if err != nil {
        return nil, &NetworkError{Err: err}
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != http.StatusOK {
        return nil, b.handleError(resp)
    }
    
    b.quota.RecordRequest()
    
    var bingResp BingSearchResponse
    if err := json.NewDecoder(resp.Body).Decode(&bingResp); err != nil {
        return nil, fmt.Errorf("decode response: %w", err)
    }
    
    return b.parseResults(bingResp), nil
}
```

### Response Types

```go
type BingSearchResponse struct {
    Type         string `json:"_type"`
    QueryContext struct {
        OriginalQuery string `json:"originalQuery"`
    } `json:"queryContext"`
    WebPages struct {
        TotalEstimatedMatches int64           `json:"totalEstimatedMatches"`
        Value                 []BingWebResult `json:"value"`
    } `json:"webPages"`
    RankingResponse struct {
        Mainline struct {
            Items []struct {
                ResultIndex int    `json:"resultIndex"`
                Value       struct {
                    ID string `json:"id"`
                } `json:"value"`
            } `json:"items"`
        } `json:"mainline"`
    } `json:"rankingResponse"`
}

type BingWebResult struct {
    ID                string    `json:"id"`
    Name              string    `json:"name"`
    URL               string    `json:"url"`
    DisplayURL        string    `json:"displayUrl"`
    Snippet           string    `json:"snippet"`
    DateLastCrawled   string    `json:"dateLastCrawled"`
    Language          string    `json:"language"`
    IsNavigational    bool      `json:"isNavigational"`
    IsFamilyFriendly  bool      `json:"isFamilyFriendly"`
}
```

### Result Parsing

```go
func (b *BingSearch) parseResults(resp BingSearchResponse) []Result {
    var results []Result
    
    for i, item := range resp.WebPages.Value {
        results = append(results, Result{
            Title:       item.Name,
            Description: item.Snippet,
            URL:         item.URL,
            Position:    i + 1,
        })
    }
    
    return results
}
```

### Error Handling

```go
func (b *BingSearch) handleError(resp *http.Response) error {
    switch resp.StatusCode {
    case 401:
        return fmt.Errorf("invalid API key")
    case 403:
        b.quota.MarkExhausted()
        return &QuotaExhaustedError{API: "Bing Search"}
    case 429:
        return &BlockedError{StatusCode: 429, Message: "rate limited"}
    default:
        return fmt.Errorf("API error: status %d", resp.StatusCode)
    }
}
```

---

## Advanced Search Options

```go
type BingSearchOptions struct {
    SearchOptions
    
    Market     string // Market code (e.g., "en-US")
    SafeSearch string // "Off", "Moderate", "Strict"
    Freshness  string // "Day", "Week", "Month"
    Site       string // Limit to specific site
}

func (b *BingSearch) SearchAdvanced(ctx context.Context, query string, opts BingSearchOptions) ([]Result, error) {
    params := url.Values{}
    params.Set("q", b.buildAdvancedQuery(query, opts))
    params.Set("count", fmt.Sprintf("%d", opts.MaxResults))
    
    if opts.Market != "" {
        params.Set("mkt", opts.Market)
    }
    if opts.SafeSearch != "" {
        params.Set("safeSearch", opts.SafeSearch)
    }
    if opts.Freshness != "" {
        params.Set("freshness", opts.Freshness)
    }
    
    // ... rest of search logic
}

func (b *BingSearch) buildAdvancedQuery(query string, opts BingSearchOptions) string {
    if opts.Site != "" {
        return fmt.Sprintf("site:%s %s", opts.Site, query)
    }
    return query
}
```

---

## Rate Limiting

```go
type RateLimiter struct {
    requests  int
    window    time.Duration
    lastReset time.Time
    mu        sync.Mutex
}

func NewRateLimiter(maxRequests int, window time.Duration) *RateLimiter {
    return &RateLimiter{
        window:    window,
        lastReset: time.Now(),
    }
}

func (r *RateLimiter) Allow() bool {
    r.mu.Lock()
    defer r.mu.Unlock()
    
    if time.Since(r.lastReset) > r.window {
        r.requests = 0
        r.lastReset = time.Now()
    }
    
    if r.requests >= 3 { // Bing limit: 3/second
        return false
    }
    
    r.requests++
    return true
}

func (r *RateLimiter) Wait(ctx context.Context) error {
    for !r.Allow() {
        select {
        case <-ctx.Done():
            return ctx.Err()
        case <-time.After(100 * time.Millisecond):
        }
    }
    return nil
}
```

---

## Configuration

```json
{
  "apis": {
    "bing": {
      "enabled": true,
      "apiKeyEnv": "BING_API_KEY",
      "endpoint": "https://api.bing.microsoft.com/v7.0/search",
      "monthlyQuota": 1000,
      "market": "en-US",
      "safeSearch": "Moderate"
    }
  }
}
```

---

## Related Specs

- [Configuration](./02-configuration.md) — API key management
- [Method Switching](./08-method-switching.md) — Fallback handling
- [Google API](./05-google-api.md) — Alternative API method
