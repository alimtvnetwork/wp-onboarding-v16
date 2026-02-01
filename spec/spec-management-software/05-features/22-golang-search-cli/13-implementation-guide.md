# Golang Search CLI - Implementation Guide

**Version:** 1.2.0  
**Updated:** 2026-01-28  

## Overview

---

## Phase 1: Foundation Infrastructure (Week 1)

### 1.1 Project Scaffold

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 1.1.1 | Initialize Go module | `go.mod`, `go.sum` | `go mod init gsearch` succeeds |
| 1.1.2 | Create directory structure | See below | All directories exist |
| 1.1.3 | Add Cobra CLI framework | `cmd/root.go` | `./gsearch --help` works |
| 1.1.4 | Add Viper configuration | `internal/config/config.go` | Config loads from file |

> **Module Path Convention:** The Go module is named `gsearch` (short, idiomatic). The binary/executable is also named `gsearch`. All import paths use `gsearch/...`.

**Directory Structure:**
```
gsearch/
├── cmd/
│   ├── root.go
│   ├── search.go
│   └── export.go
├── internal/
│   ├── config/
│   ├── database/
│   ├── engines/
│   │   ├── google/
│   │   ├── duckduckgo/
│   │   └── bing/
│   ├── parser/
│   ├── cache/
│   ├── switcher/
│   ├── nested/
│   └── rag/
├── pkg/
│   ├── models/
│   ├── selectors/
│   ├── errors/
│   ├── app/
│   └── retry/
├── configs/
│   ├── config.json
│   └── selectors.json
├── testdata/
│   └── fixtures/
├── tests/
│   └── integration/
├── go.mod
├── go.sum
└── main.go
```

**go.mod File:**
```go
module gsearch

go 1.22

require (
    github.com/spf13/cobra v1.8.0
    github.com/spf13/viper v1.18.0
    gorm.io/gorm v1.25.0
    gorm.io/driver/sqlite v1.5.0
    github.com/PuerkitoBio/goquery v1.8.0
    github.com/rs/zerolog v1.31.0
    github.com/google/uuid v1.5.0
    github.com/prometheus/client_golang v1.18.0
    go.opentelemetry.io/otel v1.21.0
    github.com/jarcoal/httpmock v1.3.1
    github.com/stretchr/testify v1.9.0
    gopkg.in/yaml.v3 v3.0.1
    github.com/pelletier/go-toml/v2 v2.1.0
)
```

**Entry Point: `main.go`**
```go
package main

import (
    "os"
    
    "gsearch/cmd"
    "gsearch/pkg/app"
    
    "github.com/rs/zerolog"
    "github.com/rs/zerolog/log"
)

func main() {
    // Initialize logging
    log.Logger = zerolog.New(zerolog.ConsoleWriter{Out: os.Stderr}).
        With().Timestamp().Logger()
    
    // Create shutdown manager
    shutdown := app.NewShutdownManager(app.DefaultShutdownConfig())
    shutdown.Start()
    
    // Execute CLI
    if err := cmd.Execute(shutdown.Context()); err != nil {
        log.Error().Err(err).Msg("Command failed")
        os.Exit(1)
    }
}
```

**Dependencies to Install:**
```bash
go get github.com/spf13/cobra@v1.8.0
go get github.com/spf13/viper@v1.18.0
go get gorm.io/gorm@v1.25.0
go get gorm.io/driver/sqlite@v1.5.0
go get github.com/PuerkitoBio/goquery@v1.8.0
go get gopkg.in/yaml.v3@v3.0.1
go get github.com/pelletier/go-toml/v2@v2.1.0
go get github.com/rs/zerolog@v1.31.0
go get github.com/google/uuid@v1.5.0
go get github.com/prometheus/client_golang@v1.18.0
go get go.opentelemetry.io/otel@v1.21.0
go get github.com/jarcoal/httpmock@v1.3.1
go get github.com/stretchr/testify@v1.9.0
```

### 1.2 Configuration System

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 1.2.1 | Define Config struct | `internal/config/types.go` | All fields match spec |
| 1.2.2 | Implement loader | `internal/config/loader.go` | Loads JSON with defaults |
| 1.2.3 | Add validation | `internal/config/validator.go` | Returns errors for invalid config |
| 1.2.4 | Create default config | `configs/config.json` | Valid JSON with all fields |

**Implementation Order:**
1. Create `types.go` with all configuration structs
2. Implement `loader.go` with Viper integration
3. Add `validator.go` for constraint checking
4. Write unit tests for all config scenarios

### 1.3 Database Layer

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 1.3.1 | Define GORM models | `pkg/models/*.go` | All 6 models defined |
| 1.3.2 | Implement connection | `internal/database/connection.go` | SQLite opens successfully |
| 1.3.3 | Add migration logic | `internal/database/migrate.go` | Tables auto-created |
| 1.3.4 | Create repository interfaces | `internal/database/repository.go` | CRUD operations work |

**Model Implementation Order:**
1. `SearchRequest` (base entity)
2. `SearchResult` (depends on SearchRequest)
3. `PageContent` (depends on SearchResult)
4. `NestedSearch` (depends on SearchRequest)
5. `CacheEntry` (standalone)
6. `RagMemory` (depends on SearchRequest)

---

## Phase 2: Core Search Engines (Week 2-3)

### 2.1 HTML Parser Foundation

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 2.1.1 | Create HTTP client | `internal/parser/client.go` | Requests with headers work |
| 2.1.2 | Implement User-Agent rotation | `internal/parser/useragent.go` | Random UA per request |
| 2.1.3 | Add block detection | `internal/parser/detector.go` | Detects CAPTCHA pages |
| 2.1.4 | Create base parser interface | `internal/parser/parser.go` | Interface defined |

**Code Pattern:**
```go
// internal/parser/parser.go
type Parser interface {
    Parse(html string) ([]SearchResult, error)
    IsBlocked(html string) bool
    GetEngineName() string
}

type BaseParser struct {
    client    *http.Client
    userAgent *UserAgentRotator
}
```

### 2.2 Google HTML Parser

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 2.2.1 | Implement search URL builder | `internal/engines/google/url.go` | Correct Google URL format |
| 2.2.2 | Create result parser | `internal/engines/google/parser.go` | Extracts title, URL, snippet |
| 2.2.3 | Add block detection | `internal/engines/google/blocker.go` | Detects consent/CAPTCHA |
| 2.2.4 | Write test fixtures | `testdata/google/*.html` | 3+ sample responses |

**Selectors to Implement:**
```go
var GoogleSelectors = struct {
    ResultContainer string
    Title           string
    URL             string
    Snippet         string
    BlockIndicators []string
}{
    ResultContainer: "div.g",
    Title:           "h3",
    URL:             "a[href]",
    Snippet:         "div.VwiC3b",
    BlockIndicators: []string{"unusual traffic", "captcha", "consent.google"},
}
```

### 2.3 DuckDuckGo Parser

**Duration:** 1.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 2.3.1 | Implement HTML search | `internal/engines/duckduckgo/html.go` | POST-based search works |
| 2.3.2 | Add Instant Answer API | `internal/engines/duckduckgo/api.go` | JSON API integration |
| 2.3.3 | Create result parser | `internal/engines/duckduckgo/parser.go` | Extracts all fields |
| 2.3.4 | Handle rate limiting | `internal/engines/duckduckgo/limiter.go` | Respects rate limits |

### 2.4 Bing Parser

**Duration:** 1.5 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 2.4.1 | Implement HTML parser | `internal/engines/bing/html.go` | Parses Bing results |
| 2.4.2 | Add API integration | `internal/engines/bing/api.go` | API v7 working |
| 2.4.3 | Create rate limiter | `internal/engines/bing/limiter.go` | 3 req/sec enforced |
| 2.4.4 | Write test fixtures | `testdata/bing/*.html` | Sample responses |

### 2.5 Google Search Console API

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 2.5.1 | Set up OAuth2 client | `internal/engines/google/oauth.go` | Token refresh works |
| 2.5.2 | Implement Custom Search | `internal/engines/google/customsearch.go` | API queries succeed |
| 2.5.3 | Add Search Console | `internal/engines/google/console.go` | Analytics data fetched |
| 2.5.4 | Create quota tracker | `internal/engines/google/quota.go` | Tracks daily usage |

---

## Phase 3: Method Switching & Orchestration (Week 4)

### 3.1 Method Switcher

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 3.1.1 | Implement weighted selection | `internal/switcher/weighted.go` | Respects weight config |
| 3.1.2 | Add block tracking | `internal/switcher/blocker.go` | Cooldowns enforced |
| 3.1.3 | Create fallback chain | `internal/switcher/fallback.go` | Auto-switches on block |
| 3.1.4 | Add method registry | `internal/switcher/registry.go` | All engines registered |

**Algorithm Implementation:**
```go
// internal/switcher/weighted.go
func (s *Switcher) SelectMethod() (SearchMethod, error) {
    // 1. Filter out blocked methods
    available := s.getAvailableMethods()
    if len(available) == 0 {
        return nil, ErrAllMethodsBlocked
    }
    
    // 2. Calculate total weight
    totalWeight := 0.0
    for _, m := range available {
        totalWeight += m.Weight
    }
    
    // 3. Random weighted selection
    r := rand.Float64() * totalWeight
    cumulative := 0.0
    for _, m := range available {
        cumulative += m.Weight
        if r <= cumulative {
            return m, nil
        }
    }
    return available[len(available)-1], nil
}
```

### 3.2 Search Orchestrator

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 3.2.1 | Create orchestrator service | `internal/search/orchestrator.go` | Coordinates all engines |
| 3.2.2 | Implement concurrent search | `internal/search/concurrent.go` | Parallel keyword search |
| 3.2.3 | Add result aggregator | `internal/search/aggregator.go` | Merges results correctly |
| 3.2.4 | Create progress tracker | `internal/search/progress.go` | Status updates work |

**Concurrency Pattern:**
```go
func (o *Orchestrator) SearchConcurrent(keywords []string) ([]SearchResult, error) {
    resultsChan := make(chan KeywordResult, len(keywords))
    sem := make(chan struct{}, o.config.MaxConcurrency)
    
    var wg sync.WaitGroup
    for _, keyword := range keywords {
        wg.Add(1)
        go func(kw string) {
            defer wg.Done()
            sem <- struct{}{}
            defer func() { <-sem }()
            
            result := o.searchSingleKeyword(kw)
            resultsChan <- result
        }(keyword)
    }
    
    go func() {
        wg.Wait()
        close(resultsChan)
    }()
    
    return o.collectResults(resultsChan)
}
```

### 3.3 Page Content Fetcher

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 3.3.1 | Implement content fetcher | `internal/search/fetcher.go` | Fetches page HTML |
| 3.3.2 | Add text extraction | `internal/search/extractor.go` | Clean text extracted |
| 3.3.3 | Create content storage | `internal/search/storage.go` | Saves to PageContent |

---

## Phase 4: Caching & Nested Search (Week 5)

### 4.1 Caching System

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 4.1.1 | Implement cache key generation | `internal/cache/keygen.go` | Consistent hashing |
| 4.1.2 | Create cache service | `internal/cache/service.go` | Get/Set operations |
| 4.1.3 | Add TTL expiration | `internal/cache/expiry.go` | Auto-expires entries |
| 4.1.4 | Implement cleanup job | `internal/cache/cleanup.go` | Background cleanup |
| 4.1.5 | Add hit-rate tracking | `internal/cache/stats.go` | Metrics collected |

**Cache Flow:**
```
┌─────────────────┐
│ Search Request  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌─────────────────┐
│  Cache Lookup   │────▶│   Cache Hit?    │
└─────────────────┘     └────────┬────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
                    ▼                         ▼
           ┌───────────────┐         ┌───────────────┐
           │  Return Cache │         │ Execute Search│
           └───────────────┘         └───────┬───────┘
                                             │
                                             ▼
                                    ┌───────────────┐
                                    │ Store in Cache│
                                    └───────────────┘
```

### 4.2 Nested Search Engine

**Duration:** 3 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 4.2.1 | Implement keyword extractor | `internal/nested/extractor.go` | TF-IDF extraction |
| 4.2.2 | Create depth controller | `internal/nested/depth.go` | Respects max depth |
| 4.2.3 | Add recursive executor | `internal/nested/executor.go` | Spawns child searches |
| 4.2.4 | Implement cycle detection | `internal/nested/cycles.go` | Prevents infinite loops |
| 4.2.5 | Create tree builder | `internal/nested/tree.go` | Builds search tree |

**Nested Search Algorithm:**
```go
func (n *NestedSearcher) Execute(parentID uint, depth int) error {
    if depth >= n.config.MaxDepth {
        return nil
    }
    
    // 1. Get parent results
    results := n.repo.GetResultsByRequestID(parentID)
    
    // 2. Fetch page content concurrently
    contents := n.fetchContents(results)
    
    // 3. Extract keywords from content
    keywords := n.extractor.ExtractTopN(contents, n.config.KeywordsPerPage)
    
    // 4. Filter already-searched keywords
    newKeywords := n.filterSearched(keywords)
    
    // 5. Create child searches concurrently
    for _, kw := range newKeywords {
        childRequest := n.createChildSearch(parentID, kw, depth+1)
        go n.Execute(childRequest.ID, depth+1)
    }
    
    return nil
}
```

---

## Phase 5: RAG Export & CLI Commands (Week 6)

### 5.1 RAG Memory Export

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.1.1 | Create text chunker | `internal/rag/chunker.go` | Splits text correctly |
| 5.1.2 | Build memory transformer | `internal/rag/transformer.go` | Creates RAG format |
| 5.1.3 | Implement JSON export | `internal/rag/json.go` | Valid JSON output |
| 5.1.4 | Implement YAML export | `internal/rag/yaml.go` | Valid YAML output |
| 5.1.5 | Implement TOML export | `internal/rag/toml.go` | Valid TOML output |

**RAG Memory Structure:**
```go
type RAGMemory struct {
    ID            uint      `json:"id"`
    SearchID      uint      `json:"search_id"`
    ChunkIndex    int       `json:"chunk_index"`
    Content       string    `json:"content"`
    SourceURL     string    `json:"source_url"`
    Relevance     float64   `json:"relevance"`
    Keywords      []string  `json:"keywords"`
    CreatedAt     time.Time `json:"created_at"`
}
```

### 5.2 CLI Commands

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.2.1 | Implement search command | `cmd/search.go` | Full flag support |
| 5.2.2 | Implement export command | `cmd/export.go` | All formats work |
| 5.2.3 | Add status command | `cmd/status.go` | Shows search progress |
| 5.2.4 | Create config command | `cmd/config.go` | View/update config |
| 5.2.5 | Add cache command | `cmd/cache.go` | Clear/stats operations |

**Command Examples:**
```bash
# Basic search
./gsearch search "golang tutorials" "react hooks"

# Multi-engine search
./gsearch search --engines=google,duckduckgo "machine learning"

# With nested search
./gsearch search --nested --depth=2 "kubernetes deployment"

# Database output
./gsearch search --output=db "API design patterns"

# Export RAG memory
./gsearch export --format=json --request-id=123 > rag_memory.json
./gsearch export --format=yaml --all > all_memories.yaml

# Cache management
./gsearch cache stats
./gsearch cache clear --older-than=7d
```

### 5.3 Output Formatters

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 5.3.1 | Create JSON formatter | `internal/output/json.go` | Pretty-printed JSON |
| 5.3.2 | Create table formatter | `internal/output/table.go` | Console table output |
| 5.3.3 | Add progress reporter | `internal/output/progress.go` | Real-time updates |

---

## Phase 6: Testing & Quality (Week 7)

### 6.1 Integration Tests

**Duration:** 4 days

**Note:** Per project requirements, unit tests are explicitly excluded. All testing is integration-focused.

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 6.1.1 | Database integration tests | `tests/integration/db_test.go` | All CRUD works |
| 6.1.2 | Search flow tests | `tests/integration/search_test.go` | Full flow passes |
| 6.1.3 | Cache integration tests | `tests/integration/cache_test.go` | TTL works correctly |
| 6.1.4 | Mock HTTP tests | `tests/integration/http_test.go` | Mock responses work |
| 6.1.5 | Parser integration tests | `tests/integration/parser_test.go` | HTML parsing validated |
| 6.1.6 | Engine integration tests | `tests/integration/engine_test.go` | All engines tested |
| 6.1.7 | Switcher integration tests | `tests/integration/switcher_test.go` | Fallback logic works |
| 6.1.8 | RAG export tests | `tests/integration/rag_test.go` | All formats valid |

### 6.2 E2E Tests

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 6.2.1 | CLI command tests | `tests/e2e/cli_test.go` | All commands work |
| 6.2.2 | Output format tests | `tests/e2e/output_test.go` | JSON/YAML/TOML valid |
| 6.2.3 | Error handling tests | `tests/e2e/errors_test.go` | Graceful failures |

---

## Phase 7: Documentation & Polish (Week 8)

### 7.1 Documentation

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 7.1.1 | Write README | `README.md` | Complete usage guide |
| 7.1.2 | API documentation | `docs/api.md` | All exports documented |
| 7.1.3 | Configuration guide | `docs/configuration.md` | All options explained |
| 7.1.4 | Examples directory | `examples/*.go` | Working examples |

### 7.2 Performance Optimization

**Duration:** 2 days

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 7.2.1 | Add benchmarks | `benchmarks/*_test.go` | All critical paths |
| 7.2.2 | Optimize hot paths | Various | <100ms per search |
| 7.2.3 | Memory profiling | N/A | No memory leaks |
| 7.2.4 | Connection pooling | `internal/parser/pool.go` | Reuses connections |

### 7.3 Release Preparation

**Duration:** 1 day

**Tasks:**
| ID | Task | Files | Acceptance Criteria |
|----|------|-------|---------------------|
| 7.3.1 | Create Makefile | `Makefile` | Build/test/release targets |
| 7.3.2 | Add CI/CD workflow | `.github/workflows/ci.yml` | Automated testing |
| 7.3.3 | Create release script | `scripts/release.sh` | Multi-platform builds |
| 7.3.4 | Version tagging | N/A | Semantic versioning |

---

## Dependency Graph

```
Phase 1 (Foundation)
    │
    ├──▶ Phase 2 (Search Engines)
    │         │
    │         └──▶ Phase 3 (Orchestration)
    │                   │
    │                   ├──▶ Phase 4 (Caching/Nested)
    │                   │         │
    │                   │         └──▶ Phase 5 (RAG/CLI)
    │                   │                   │
    └───────────────────┴───────────────────┴──▶ Phase 6 (Testing)
                                                      │
                                                      └──▶ Phase 7 (Docs/Polish)
```

---

## Critical Path Items

These items block multiple downstream phases and should be prioritized:

| Item | Blocks | Priority |
|------|--------|----------|
| GORM models | All DB operations | P0 |
| HTTP client with UA rotation | All parsers | P0 |
| Method switcher | Orchestrator | P0 |
| Cache service | Nested search | P1 |
| Text chunker | RAG export | P1 |

---

## Rollback Strategy

Each phase includes migration rollback capabilities:

```go
// internal/database/migrate.go
func Rollback(db *gorm.DB, steps int) error {
    // Implement using GORM migrator
    m := db.Migrator()
    // Track migration versions
    // Rollback specified number of steps
}
```

---

## Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Test Coverage | ≥80% | `go test -cover` |
| Search Latency | <2s per keyword | Benchmark tests |
| Cache Hit Rate | ≥60% after warmup | Stats command |
| Memory Usage | <100MB baseline | `pprof` |
| Block Rate | <5% per engine | Monitoring logs |
