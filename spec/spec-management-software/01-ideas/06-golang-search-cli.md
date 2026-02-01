# Idea: Golang Search CLI

**Status:** Draft  
**Priority:** High  
**Complexity:** Complex  
**Created:** 2026-01-28  

---

## Summary

A standalone Golang CLI tool for multi-engine web searching with concurrent execution, anti-blocking strategies, nested search capabilities, caching, and RAG memory generation. Operates independently with its own SQLite database (`search.db.sqlite`) readable by the main application.

---

## Problem Statement

The main application needs web search capabilities for:
- Gathering external knowledge for AI context (RAG)
- Research automation during spec generation
- Collecting reference materials from multiple sources

Direct integration would couple the main app to external APIs and blocking risks. A separate CLI tool provides isolation, flexibility, and reusability.

---

## Proposed Solution

### Core Features

1. **Multi-Parameter Concurrent Search**
   - Accept comma-separated keywords
   - Execute searches concurrently with configurable delays
   - Anti-blocking wait times between requests

2. **Four Search Methods**
   - Direct HTML parsing (native Golang HTTP)
   - Google Search Console API
   - DuckDuckGo search
   - Bing search API

3. **Intelligent Method Switching**
   - Random selection based on config percentages
   - Automatic fallback when blocked
   - Block detection and recovery

4. **Three-Level Data Collection**
   - Level 1: Search results (titles, descriptions, URLs)
   - Level 2: Page content fetching
   - Level 3: Nested keyword extraction and recursive search

5. **Output Formats**
   - Structured JSON to stdout
   - YAML format
   - TOML format
   - SQLite database persistence

6. **Caching System**
   - Configurable cache duration (default: 5-6 days)
   - Cache-first retrieval for repeated keywords
   - Cache invalidation settings

7. **RAG Memory Generation**
   - Export search knowledge as RAG-compatible format
   - Multiple output formats (JSON, YAML, TOML)

---

## Acceptance Criteria

- [ ] CLI accepts multiple comma-separated search keywords
- [ ] Concurrent execution with configurable delay
- [ ] Supports Google, DuckDuckGo, Bing search engines
- [ ] HTML parsing works without external dependencies
- [ ] Google Search Console API integration
- [ ] Automatic method switching on block detection
- [ ] Config file controls method percentages
- [ ] Results saved to SQLite with proper schema
- [ ] Nested search triggered from page keywords
- [ ] Caching prevents redundant searches
- [ ] RAG memory exportable in JSON/YAML/TOML
- [ ] Main application can read search.db.sqlite
- [ ] Status tracking (requested, in-progress, completed)

---

## Technical Considerations

### Dependencies
- Native Golang HTTP client (no cURL)
- GORM for SQLite ORM
- cobra/viper for CLI framework
- goquery for HTML parsing

### Database: `search.db.sqlite`

| Table | Purpose |
|-------|---------|
| SearchRequest | Track search jobs and status |
| SearchResult | Store result metadata |
| PageContent | Store fetched page content |
| NestedSearch | Track recursive searches |
| CacheMetadata | Manage cache expiration |
| RagMemory | Store RAG-formatted knowledge |

### Config File: `config.json`

```json
{
  "search": {
    "defaultDelay": 2000,
    "maxConcurrent": 5,
    "cacheExpireDays": 5,
    "methodWeights": {
      "htmlParsing": 40,
      "googleApi": 30,
      "duckduckgo": 20,
      "bing": 10
    }
  },
  "output": {
    "defaultFormat": "json",
    "saveToDb": true
  }
}
```

### Security Implications
- API keys stored securely (not in config)
- Rate limiting to prevent abuse
- User-agent rotation for parsing

---

## Related Specs

- [AI Integration](../05-features/06-ai-integration/00-overview.md) — RAG context consumption
- [Knowledge Memory](../05-features/09-knowledge-memory/00-overview.md) — Knowledge storage

---

## CLI Specification Reference

See full technical specification: [22-golang-search-cli](../05-features/22-golang-search-cli/00-overview.md)

---

## Notes

### Clarifications from User

1. **LLM File System Access** — Confirmed needed in specs (file read/write/history)
2. **Four Model Categories** — Confirmed: Reasoning, Voice, Writing, Coding
3. **Runner Switching** — OLMA ↔ LLAMA runner seamless switching
4. **Multi-folder Models** — Models can exist in multiple folders

### Implementation Phases

| Phase | Scope |
|-------|-------|
| 1 | Core CLI structure, config, database schema |
| 2 | HTML parsing search method |
| 3 | Google Search Console API integration |
| 4 | DuckDuckGo & Bing support |
| 5 | Nested search & caching |
| 6 | RAG memory export |
