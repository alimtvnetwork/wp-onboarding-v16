# Deployment Architecture

**Version:** 1.0.0  
**Status:** Pending Decision  
**Updated:** 2026-01-28

---

## Current Status: ❓ To Be Decided Later

This document is a placeholder for deployment architecture decisions that will be made in a future planning session.

---

## Known Architecture Facts

| Component | Technology | Location |
|-----------|------------|----------|
| Backend | Go + GORM | Local |
| Database | SQLite | Local |
| LLM Server | llama.cpp / Ollama | Local |
| Frontend | React | Local (served by Go) |
| Models | GGUF files | Local filesystem |

---

## Open Questions

- [ ] How is the app distributed? (Binary, installer, Docker?)
- [ ] Cross-platform support? (Windows, macOS, Linux?)
- [ ] Auto-update mechanism?
- [ ] First-run setup wizard?
- [ ] Model download/management?

---

## Notes

_This section will be filled in during deployment planning session._

```
// TODO: Deployment decisions pending
// - Distribution method
// - Platform targets
// - Installation flow
```

---

## Related Specs

- [LLM Server Management](../05-features/06-ai-integration/07-llm-server-management.md)
- [Configuration System](../04-coding-guidelines/02-configuration-manifest.md)
