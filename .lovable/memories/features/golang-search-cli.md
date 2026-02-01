# Memory: features/golang-search-cli

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/22-golang-search-cli/`

---

## Overview

Golang-based search CLI (`gsearch`) for full-text and semantic search across specifications and codebase.

---

## Core Capabilities

| Feature | Description |
|---------|-------------|
| Full-text Search | FTS5-based text search |
| Semantic Search | Vector similarity via sqlite-vss |
| Hybrid Scoring | Combined FTS5 + VSS results |
| Full Site Crawler | URL ingestion with SSRF protection |
| Indexing | Markdown chunking with stable IDs |

---

## Scoring & Analysis

| Component | Description |
|-----------|-------------|
| Authority Scores | Domain-based authority (Academic: 0.95, Tech: 0.88) |
| Source Weights | 50% authority + 30% recency + 20% citations |
| Credibility | Low/Medium/High classification (thresholds: 0.4, 0.7) |
| Confidence | Weighted formula with 30% contradiction penalty |
| Trend Analysis | Composite score (30% stars, 40% jobs, 20% SO, 10% downloads) |

---

## TrendAnalyzer Implementation

- **Collectors**: GitHub, StackOverflow, Jobs, NPM, PyPI
- **Settings Integration**: Full SettingsService with seedable config
- **Visualization**: Bar charts, line charts, heatmaps via go-chart
- **CLI Commands**: `gsearch trends analyze|history|compare`

---

## Integration

- Standalone CLI executable
- JSON output format
- Integrates with RAG system for context retrieval
