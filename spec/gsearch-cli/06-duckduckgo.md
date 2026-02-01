# Component: DuckDuckGo Search

**Parent:** [Golang Search CLI](./00-overview.md)  
**Version:** 1.2.0  
**Updated:** 2026-01-28  

---

## Summary

DuckDuckGo search integration using HTML parsing (no official API) with privacy-focused, non-tracking results.

---

## Advantages

| Feature | Benefit |
|---------|---------|
| No API key required | Zero setup cost |
| Privacy-focused | No tracking parameters |
| Less aggressive blocking | More lenient than Google |
| Instant Answers | Rich snippets for common queries |

---

## Implementation

### Search Method

```go
package search

import (
    "context"
    "fmt"
    "net/http"
    "net/url"
    "strings"
    "time"
    
    "github.com/PuerkitoBio/goquery"
)

type DuckDuckGoSearch struct {
    client    *http.Client
    userAgent string
    endpoint  string
}

func NewDuckDuckGoSearch(cfg *config.DDGConfig, userAgent string) *DuckDuckGoSearch {
    endpoint := cfg.Endpoint
    if endpoint == "" {
        endpoint = "https://html.duckduckgo.com/html/"
    }
    
    return &DuckDuckGoSearch{
        client: &http.Client{
            Timeout: 30 * time.Second,
        },
        userAgent: userAgent,
        endpoint:  endpoint,
    }
}

func (d *DuckDuckGoSearch) ID() string        { return "duckduckgo" }
func (d *DuckDuckGoSearch) Name() string      { return "DuckDuckGo" }
func (d *DuckDuckGoSearch) IsAvailable() bool { return true }
func (d *DuckDuckGoSearch) RequiresAPI() bool { return false }
```

### Search Execution

```go
func (d *DuckDuckGoSearch) Search(ctx context.Context, query string, opts SearchOptions) ([]Result, error) {
    // Build form data (DDG uses POST)
    formData := url.Values{}
    formData.Set("q", query)
    formData.Set("b", "") // No pagination offset
    formData.Set("kl", "us-en") // Region
    
    req, err := http.NewRequestWithContext(ctx, "POST", d.endpoint, strings.NewReader(formData.Encode()))
    if err != nil {
        return nil, fmt.Errorf("create request: %w", err)
    }
    
    req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
    req.Header.Set("User-Agent", d.userAgent)
    req.Header.Set("Accept", "text/html")
    req.Header.Set("Accept-Language", "en-US,en;q=0.9")
    
    resp, err := d.client.Do(req)
    if err != nil {
        return nil, &NetworkError{Err: err}
    }
    defer resp.Body.Close()
    
    if resp.StatusCode != http.StatusOK {
        return nil, fmt.Errorf("unexpected status: %d", resp.StatusCode)
    }
    
    doc, err := goquery.NewDocumentFromReader(resp.Body)
    if err != nil {
        return nil, fmt.Errorf("parse HTML: %w", err)
    }
    
    return d.parseResults(doc, opts.MaxResults), nil
}
```

### Result Parsing

```go
func (d *DuckDuckGoSearch) parseResults(doc *goquery.Document, maxResults int) []Result {
    var results []Result
    position := 0
    
    // Parse organic results
    doc.Find("div.result").Each(func(i int, s *goquery.Selection) {
        if position >= maxResults {
            return
        }
        
        // Skip ads
        if s.HasClass("result--ad") {
            return
        }
        
        result, ok := d.parseResultItem(s)
        if !ok {
            return
        }
        
        position++
        result.Position = position
        results = append(results, result)
    })
    
    return results
}

func (d *DuckDuckGoSearch) parseResultItem(s *goquery.Selection) (Result, bool) {
    // Title and URL
    titleLink := s.Find("a.result__a").First()
    title := strings.TrimSpace(titleLink.Text())
    href, exists := titleLink.Attr("href")
    if !exists || title == "" {
        return Result{}, false
    }
    
    // Extract actual URL from DDG redirect
    actualURL := d.extractActualURL(href)
    
    // Description/Snippet
    snippet := s.Find("a.result__snippet").First()
    description := strings.TrimSpace(snippet.Text())
    
    return Result{
        Title:       title,
        Description: description,
        URL:         actualURL,
    }, true
}

func (d *DuckDuckGoSearch) extractActualURL(ddgURL string) string {
    // DuckDuckGo wraps URLs in a redirect: //duckduckgo.com/l/?uddg=...
    if strings.HasPrefix(ddgURL, "//duckduckgo.com/l/") {
        parsed, err := url.Parse("https:" + ddgURL)
        if err == nil {
            if uddg := parsed.Query().Get("uddg"); uddg != "" {
                decoded, err := url.QueryUnescape(uddg)
                if err == nil {
                    return decoded
                }
            }
        }
    }
    
    // Handle relative URLs
    if strings.HasPrefix(ddgURL, "//") {
        return "https:" + ddgURL
    }
    
    return ddgURL
}
```

### Instant Answers

```go
// DuckDuckGo Instant Answer API (JSON, free to use)
type InstantAnswer struct {
    Abstract     string `json:"Abstract"`
    AbstractURL  string `json:"AbstractURL"`
    Answer       string `json:"Answer"`
    AnswerType   string `json:"AnswerType"`
    Definition   string `json:"Definition"`
    Heading      string `json:"Heading"`
    Image        string `json:"Image"`
    RelatedTopics []struct {
        Text     string `json:"Text"`
        FirstURL string `json:"FirstURL"`
    } `json:"RelatedTopics"`
}

func (d *DuckDuckGoSearch) GetInstantAnswer(ctx context.Context, query string) (*InstantAnswer, error) {
    params := url.Values{}
    params.Set("q", query)
    params.Set("format", "json")
    params.Set("no_html", "1")
    params.Set("skip_disambig", "1")
    
    reqURL := "https://api.duckduckgo.com/?" + params.Encode()
    
    req, err := http.NewRequestWithContext(ctx, "GET", reqURL, nil)
    if err != nil {
        return nil, err
    }
    
    req.Header.Set("User-Agent", d.userAgent)
    
    resp, err := d.client.Do(req)
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()
    
    var answer InstantAnswer
    if err := json.NewDecoder(resp.Body).Decode(&answer); err != nil {
        return nil, err
    }
    
    return &answer, nil
}
```

---

## Region Support

```go
var ddgRegions = map[string]string{
    "us":    "us-en",
    "uk":    "uk-en",
    "de":    "de-de",
    "fr":    "fr-fr",
    "es":    "es-es",
    "jp":    "jp-jp",
    "global": "wt-wt", // No region preference
}

func (d *DuckDuckGoSearch) SearchWithRegion(ctx context.Context, query, region string, opts SearchOptions) ([]Result, error) {
    kl, ok := ddgRegions[region]
    if !ok {
        kl = "us-en"
    }
    
    formData := url.Values{}
    formData.Set("q", query)
    formData.Set("kl", kl)
    
    // ... rest of search logic
}
```

---

## Configuration

```json
{
  "apis": {
    "duckduckgo": {
      "enabled": true,
      "endpoint": "https://html.duckduckgo.com/html/",
      "region": "us",
      "safeSearch": "moderate"
    }
  }
}
```

---

## Limitations

| Limitation | Workaround |
|------------|------------|
| No official API | Use HTML parsing |
| Limited results per page | Parse multiple pages |
| No date filtering | Filter results client-side |
| Rate limiting on abuse | Respectful request delays |

---

## Related Specs

- [HTML Parser](./04-html-parser.md) — Shared parsing logic
- [Method Switching](./08-method-switching.md) — Fallback handling
