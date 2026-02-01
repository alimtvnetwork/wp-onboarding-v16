# External Tool: WP Plugin Builder

**Version:** 1.0.0  
**Updated:** 2026-02-01  

---

## Overview

AI-assisted WordPress plugin development CLI using RAG and the AI Bridge.

---

## Specification Location

`spec/wp-plugin-builder/`

---

## Quick Reference

| Attribute | Value |
|-----------|-------|
| Binary Name | `wpb` |
| Language | Go 1.21+ |
| Error Code Range | 10000-10999 |
| Database | SQLite (root + per-project) |
| AI Backend | AI Bridge |

---

## Key Features

- Dual-database architecture (root + per-project)
- RAG-powered code generation
- Preset learning from markdown
- Spec-driven PHP generation
- CLI + Server modes

---

## Integration Points

- **AI Bridge:** LLM communication for embeddings and generation
- **Shared Error Package:** Consistent error handling with stack traces
- **Configuration Seeding:** Auto-seed on first run or version change

---

## Related Documents

- [WP Plugin Builder Overview](../../wp-plugin-builder/00-overview.md)
- [AI Bridge](./04-ai-bridge.md)
- [Error Code Registry](../../error-code-registry/01-registry.md)
