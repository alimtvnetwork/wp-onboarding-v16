# Memory: ai-integration/knowledge-memory-system

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/06-ai-integration/`

---

## Overview

Knowledge Memory System for AI learning from local specs and external URLs via RAG.

---

## Architecture

| Component | Description |
|-----------|-------------|
| Worker Binary | External Go CLI (process, validate, cleanup) |
| Job Queue | KnowledgeWorkerJobs in SQLite for async IPC |
| Retriever | Hybrid semantic/keyword search |
| Storage | Isolated DBs (spec_knowledge.db, url_knowledge.db) |

---

## Security

- SSRF protection for private networks
- ReDoS detection for URL patterns
- URL deduplication

---

## Configuration

80+ seeded config keys:
- Worker paths
- Crawler limits (depth, page, domain)
- Embedding settings

---

## Dashboard

- Project-level management
- Wizards for spec/URL sources
- Real-time progress tracking
