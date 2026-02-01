# GSearch CLI Reference

> **External Spec:** `spec/gsearch-cli/`  
> **Version:** 2.0.0  
> **Error Range:** 7000-7999

---

## Summary

Golang-based search CLI (`gsearch`) for multi-engine web searching, full-text and semantic search, trend analysis, and RAG memory generation.

---

## Full Specification

📁 **Location:** [`spec/gsearch-cli/`](../../../gsearch-cli/)

---

## Key Components

| File | Description |
|------|-------------|
| `00-overview.md` | Architecture, CLI commands, database schema |
| `01-cli-framework.md` | Command structure, Cobra integration |
| `02-configuration.md` | Config file management |
| `03-database-schema.md` | SQLite tables, GORM models |
| `04-html-parser.md` | Direct HTML scraping |
| `05-google-api.md` | Search Console API integration |
| `06-duckduckgo.md` | DDG search integration |
| `07-bing-search.md` | Bing API integration |
| `08-method-switching.md` | Intelligent fallback logic |
| `09-nested-search.md` | Recursive keyword extraction |
| `10-caching-system.md` | Cache management |
| `11-rag-export.md` | Memory format generation |
| `18-full-site-crawler.md` | Sitemap parsing, vector DB |
| `19-authority-credibility-scoring.md` | Domain authority scoring |
| `20-trend-analysis-engine.md` | Composite trend scoring |
| `configs/` | Environment-specific configurations |

---

## Integration Points

### CLI Invocation

```bash
# Search and index
gsearch search "keyword1,keyword2" --save-db

# Export for RAG
gsearch rag --format json --output ./rag-memory.json

# Trend analysis
gsearch trends analyze --topic "react vs vue"
```

### Daemon API

```bash
# Start daemon
gsearch daemon start --port 8088

# Query via REST
curl http://localhost:8088/api/search?q=keyword
```

---

## Database Integration

GSearch uses SQLite with:
- **FTS5** for full-text search
- **sqlite-vss** for vector similarity

Shared database path: `./search.db.sqlite`

---

## Error Codes

| Range | Category |
|-------|----------|
| 7000-7099 | General/Startup |
| 7100-7199 | Search engine errors |
| 7200-7299 | Parser errors |
| 7300-7399 | Database errors |
| 7400-7499 | Cache errors |
| 7500-7599 | Crawling errors |

See: [`spec/gsearch-cli/15-error-codes.md`](../../../gsearch-cli/15-error-codes.md)

---

*Reference for spec-management-software integration*
