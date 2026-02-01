# GSearch CLI - External Specification Reference

> **Status:** Extracted to standalone spec  
> **Location:** `spec/gsearch-cli/`  
> **Version:** 2.0.0

---

## Specification Location

This tool has been extracted to a standalone specification for independent development and AI training.

**Full Spec:** [`spec/gsearch-cli/`](../../../gsearch-cli/)

---

## Quick Reference

| Aspect | Value |
|--------|-------|
| Error Range | 7000-7999 |
| Language | Golang |
| Database | SQLite (FTS5 + VSS) |
| CLI Framework | Cobra |

---

## Core Capabilities

| Feature | Description |
|---------|-------------|
| Full-text Search | FTS5-based text search |
| Semantic Search | Vector similarity via sqlite-vss |
| Hybrid Scoring | Combined FTS5 + VSS results |
| Full Site Crawler | URL ingestion with SSRF protection |
| Trend Analysis | Composite scoring with multiple collectors |

---

## Usage in Spec Management Software

GSearch CLI provides:
- Search indexing for specification content
- RAG memory generation for AI context
- Trend analysis for technology adoption metrics
- Full-site crawling for external documentation

The spec-management-software integrates with GSearch via:
1. CLI invocation for batch indexing
2. REST API for real-time queries (daemon mode)
3. Shared SQLite database for result storage

---

*Reference created 2026-02-01 during spec extraction*
