# External Tools Integration

> **Version:** 1.0.0  
> **Updated:** 2026-02-01  
> **Status:** Active

---

## Overview

This folder contains reference files for standalone specifications that integrate with the Spec Management Software. Each tool has been extracted to its own root-level specification for independent development and AI training.

---

## Integrated External Tools

| # | Tool | Location | Error Range | Description |
|---|------|----------|-------------|-------------|
| 01 | [GSearch CLI](./01-gsearch-reference.md) | `spec/gsearch-cli/` | 7000-7999 | Search, indexing, trend analysis |
| 02 | [AI Bridge](./02-ai-bridge-reference.md) | `spec/ai-bridge/` | 9000-9999 | LLM adapter, multi-format input |
| 03 | [Nexus Flow](./03-nexus-flow-reference.md) | `spec/nexus-flow/` | 8000-8399 | Visual workflow orchestration |
| 04 | [BRun CLI](./04-brun-reference.md) | `spec/brun-cli/` | 7100-7599 | Build runner and task executor |

---

## Integration Pattern

```
┌─────────────────────────────────────────────────────────────┐
│                 SPEC MANAGEMENT SOFTWARE                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   ┌─────────────────────────────────────────────────────┐   │
│   │              15-external-tools/                      │   │
│   │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐   │   │
│   │  │ GSearch │ │AI Bridge│ │Nexus    │ │ BRun    │   │   │
│   │  │Reference│ │Reference│ │Reference│ │Reference│   │   │
│   │  └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘   │   │
│   └───────┼──────────┼──────────┼──────────┼───────────┘   │
│           │          │          │          │                │
└───────────┼──────────┼──────────┼──────────┼────────────────┘
            │          │          │          │
            ▼          ▼          ▼          ▼
    ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────┐
    │  spec/    │ │  spec/    │ │  spec/    │ │  spec/    │
    │ gsearch-  │ │ ai-bridge/│ │nexus-flow/│ │ brun-cli/ │
    │   cli/    │ │           │ │           │ │           │
    └───────────┘ └───────────┘ └───────────┘ └───────────┘
```

---

## Extraction Status

| Phase | Tool | Status | Date |
|-------|------|--------|------|
| 1 | GSearch CLI | ✅ Complete | 2026-02-01 |
| 2 | AI Bridge | ✅ Complete | 2026-02-01 |
| 3 | Nexus Flow | ✅ Complete | 2026-02-01 |
| 4 | BRun CLI | ✅ Complete | 2026-02-01 |

---

## Usage Guidelines

1. **Do not duplicate content** — Reference files point to external specs
2. **Error code registry** — Each tool has reserved error ranges in `spec/error-code-registry/`
3. **Updates** — When external specs change, update reference files accordingly
4. **AI Training** — Each standalone spec is designed for independent AI training

---

*Created 2026-02-01 during spec extraction*
