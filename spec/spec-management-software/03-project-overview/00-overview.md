# Project Overview & Navigation

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Summary

A full-stack specification management application with a Golang backend and React frontend, featuring AI-powered voice-to-spec generation via LLaMA integration.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      React Frontend                              │
│  (Themes, Folder Tree, Markdown Editor, Voice Input)            │
└───────────────────────────┬─────────────────────────────────────┘
                            │ REST API
┌───────────────────────────▼─────────────────────────────────────┐
│                      Golang Backend                              │
│  (File Ops, Git, History, Auth, LLaMA Integration)              │
└───────────────────────────┬─────────────────────────────────────┘
                            │
          ┌─────────────────┼─────────────────┐
          ▼                 ▼                 ▼
     ┌─────────┐      ┌──────────┐      ┌──────────┐
     │ SQLite  │      │ Spec FS  │      │ LLaMA    │
     │ Database│      │ (.history)│      │ Server   │
     └─────────┘      └──────────┘      └──────────┘
```

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Go 1.21+, SQLite3, go-git |
| Frontend | React 18+, TypeScript, TailwindCSS |
| AI | llama.cpp server |
| Version Control | Git (system) |

---

## Folder Structure

```
spec-management-software/
├── 00-overview.md                 # Root overview (minimal)
├── 01-ideas/                      # Raw ideas, verbatim transcriptions
├── 02-instructions/               # Refined instructions
├── 03-project-overview/           # THIS FOLDER - Project overview & navigation
├── 04-coding-guidelines/          # Project coding standards
├── 05-features/                   # Feature-based specifications
├── 06-error-management/           # Error handling (frontend/backend)
├── 07-database-design/            # Schema, migrations, ERD
├── 08-roadmap-overview/           # Roadmap, glossary, guidelines
├── 09-diagrams/                   # Workflow diagrams
├── 10-research/                   # Research notes
└── 99-consistency-report.md       # Auto-generated report
```

---

## Navigation Index

### Pipeline Folders (Input → Output)

| # | Folder | Purpose |
|---|--------|---------|
| 01 | [ideas/](../01-ideas/README.md) | Raw voice transcriptions, brainstorming |
| 02 | [instructions/](../02-instructions/README.md) | Refined, actionable instructions |
| 03 | [project-overview/](./00-overview.md) | **You are here** |
| 04 | [coding-guidelines/](../04-coding-guidelines/00-overview.md) | Coding standards, naming conventions |
| 05 | [features/](../05-features/00-overview.md) | Feature specs with E2E tests |
| 06 | [error-management/](../06-error-management/00-overview.md) | Error codes, boundaries, recovery |
| 07 | [database-design/](../07-database-design/00-overview.md) | Schema, migrations, ERD |
| 08 | [roadmap-overview/](../08-roadmap-overview/00-overview.md) | Roadmap, glossary, guidelines |
| 09 | [diagrams/](../09-diagrams/00-overview.md) | Workflow diagrams |
| 10 | [research/](../10-research/00-overview.md) | Research notes |

### Feature Index (in 05-features/)

| # | Feature | Status |
|---|---------|--------|
| 01 | [Authentication](../05-features/01-authentication/00-overview.md) | Planned |
| 02 | [File Management](../05-features/02-file-management/00-overview.md) | Planned |
| 03 | [Project Management](../05-features/03-project-management/00-overview.md) | Planned |
| 04 | [Spec Editor](../05-features/04-spec-editor/00-overview.md) | Planned |
| 05 | [Voice Input](../05-features/05-voice-input/00-overview.md) | Planned |
| 06 | [AI Integration](../05-features/06-ai-integration/00-overview.md) | Planned |
| 07 | [History System](../05-features/07-history-system/00-overview.md) | Planned |
| 08 | [Consistency Checker](../05-features/08-consistency-checker/00-overview.md) | Planned |
| 09 | [Knowledge Memory](../05-features/09-knowledge-memory/00-overview.md) | Planned |
| 10 | [Theme System](../05-features/10-theme-system/00-overview.md) | Planned |
| 11 | [Dashboard](../05-features/11-dashboard/00-overview.md) | Planned |
| 12 | [Routing & Navigation](../05-features/12-routing-navigation/00-overview.md) | Planned |
| 13 | [Error UI](../05-features/13-error-ui/00-overview.md) | Planned |
| 14 | [Mobile Responsive](../05-features/14-mobile-responsive/00-overview.md) | Planned |
| 15 | [API Client](../05-features/15-api-client/00-overview.md) | Planned |
| 16 | [State Management](../05-features/16-state-management/00-overview.md) | Planned |
| 17 | [Monitoring](../05-features/17-monitoring/00-overview.md) | Planned |
| 18 | [Realtime](../05-features/18-realtime/00-overview.md) | Planned |
| 19 | [Performance](../05-features/19-performance/00-overview.md) | Planned |
| 20 | [Testing](../05-features/20-testing/00-overview.md) | Planned |
| 21 | [i18n](../05-features/21-i18n/00-overview.md) | Planned |

---

## Pipeline Flow

```
Voice/Text Input
     ↓
┌─────────────────┐
│  01-ideas/      │  ← Capture raw ideas
└────────┬────────┘
         ↓
┌─────────────────┐
│ 02-instructions/│  ← Refine to instructions
└────────┬────────┘
         ↓
┌─────────────────┐
│04-coding-guide/ │  ← Apply coding standards
└────────┬────────┘
         ↓
┌─────────────────┐
│06-error-mgmt/   │  ← Define error handling
└────────┬────────┘
         ↓
┌─────────────────┐
│ 05-features/    │  ← Generate feature specs with E2E tests
└────────┬────────┘
         ↓
┌─────────────────┐
│07-database-design│ ← Design schema, ERD
└────────┬────────┘
         ↓
┌─────────────────┐
│08-roadmap-over/ │  ← Plan roadmap, document glossary
└─────────────────┘
```

---

## Quick Links

- **Start Here:** [Ideas README](../01-ideas/README.md)
- **Coding Standards:** [Coding Guidelines](../04-coding-guidelines/00-overview.md)
- **Feature Specs:** [Features Index](../05-features/00-overview.md)
- **Database:** [Schema](../07-database-design/01-schema.md)
- **Roadmap:** [Implementation Phases](../08-roadmap-overview/01-roadmap.md)

---

## Cross-References

- [Folder Structure Guideline](../../00-folder-structure-guideline.md) — Master organization guide
- [General Spec Overview](../../general-spec/00-overview.md) — Universal standards
