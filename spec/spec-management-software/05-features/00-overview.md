# Features

**Version:** 1.4.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Overview

Feature-based specifications organized for focused development. Each feature is a self-contained folder with related specs and E2E tests.

**Cross-References:**
- [Instructions](../02-instructions/README.md) — Source of feature requirements
- [Coding Guidelines](../04-coding-guidelines/00-overview.md)
- [Error Management](../06-error-management/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)
- [Feature Dependency Diagram](../09-diagrams/08-feature-dependency-diagram.md)

---

## Summary Statistics

| Metric | Value |
|--------|-------|
| Total Feature Folders | 25 |
| Total Specification Files | 140+ |
| Core Features | 9 |
| Frontend Infrastructure | 12 |
| CLI Tools | 2 |
| AI Systems | 2 |

---

## Folder Structure

```
05-features/
├── 00-overview.md                    # This file
├── 01-authentication/                # User authentication (3 files)
├── 02-file-management/               # File operations (5 files)
├── 03-project-management/            # Project lifecycle (3 files)
├── 04-spec-editor/                   # Markdown editing (4 files)
├── 05-voice-input/                   # Voice recording UI (4 files)
├── 06-ai-integration/                # LLaMA AI features (12 files)
├── 07-history-system/                # Version control (5 files)
├── 08-consistency-checker/           # Spec validation (4 files)
├── 09-knowledge-memory/              # RAG system (12 files + tests/)
├── 10-theme-system/                  # Theming & components (3 files)
├── 11-dashboard/                     # Project dashboard (2 files)
├── 12-routing-navigation/            # Routes & shortcuts (4 files)
├── 13-error-ui/                      # Error boundaries (4 files)
├── 14-mobile-responsive/             # Mobile layouts (2 files)
├── 15-api-client/                    # HTTP & React Query (2 files)
├── 16-state-management/              # State architecture (2 files)
├── 17-monitoring/                    # System monitoring (2 files)
├── 18-realtime/                      # WebSocket & SSE (2 files)
├── 19-performance/                   # Optimization (2 files)
├── 20-testing/                       # Test strategy (2 files)
├── 21-i18n/                          # Internationalization (2 files)
├── 22-golang-search-cli/             # Multi-engine search CLI (20 files)
├── 23-build-runner-cli/              # Build runner CLI (18 files)
├── 24-code-generation-system/        # AI code generation (34 files)
└── 25-ai-enhancements/               # Advanced AI features (33 files)
```

---

## Feature Index

### Core Features (9 folders, 52 files)

| # | Feature | Status | Files | Description |
|---|---------|--------|-------|-------------|
| 01 | [Authentication](./01-authentication/00-overview.md) | Planned | 3 | User auth, JWT, sessions, frontend security |
| 02 | [File Management](./02-file-management/00-overview.md) | Planned | 5 | File CRUD, folder tree, sync |
| 03 | [Project Management](./03-project-management/00-overview.md) | Planned | 3 | Import/export, visibility, lifecycle |
| 04 | [Spec Editor](./04-spec-editor/00-overview.md) | Planned | 4 | Markdown editing, preview, templates |
| 05 | [Voice Input](./05-voice-input/00-overview.md) | Planned | 4 | Voice recording, transcription UI |
| 06 | [AI Integration](./06-ai-integration/00-overview.md) | Planned | 12 | LLaMA server, chat UI, prompt panel |
| 07 | [History System](./07-history-system/00-overview.md) | Planned | 5 | Git integration, history UI, diffs |
| 08 | [Consistency Checker](./08-consistency-checker/00-overview.md) | Planned | 4 | Link validation, dashboard |
| 09 | [Knowledge Memory](./09-knowledge-memory/00-overview.md) | Planned | 12 | RAG, vector search, UI |

### Frontend Infrastructure (12 folders, 27 files)

| # | Feature | Status | Files | Description |
|---|---------|--------|-------|-------------|
| 10 | [Theme System](./10-theme-system/00-overview.md) | Planned | 3 | Light/dark mode, design tokens |
| 11 | [Dashboard](./11-dashboard/00-overview.md) | Planned | 2 | Project dashboard, onboarding |
| 12 | [Routing & Navigation](./12-routing-navigation/00-overview.md) | Planned | 4 | Routes, shortcuts, command palette |
| 13 | [Error UI](./13-error-ui/00-overview.md) | Planned | 4 | Error boundaries, loading states |
| 14 | [Mobile Responsive](./14-mobile-responsive/00-overview.md) | Planned | 2 | Responsive layouts |
| 15 | [API Client](./15-api-client/00-overview.md) | Planned | 2 | HTTP client, React Query |
| 16 | [State Management](./16-state-management/00-overview.md) | Planned | 2 | State architecture |
| 17 | [Monitoring](./17-monitoring/00-overview.md) | Planned | 2 | System monitoring |
| 18 | [Realtime](./18-realtime/00-overview.md) | Planned | 2 | WebSocket, SSE |
| 19 | [Performance](./19-performance/00-overview.md) | Planned | 2 | Optimization strategies |
| 20 | [Testing](./20-testing/00-overview.md) | Planned | 2 | Test strategy |
| 21 | [i18n](./21-i18n/00-overview.md) | Planned | 2 | Internationalization |

### External CLI Tools (2 folders, 38 files)

| # | Feature | Status | Files | Description |
|---|---------|--------|-------|-------------|
| 22 | [Golang Search CLI](./22-golang-search-cli/00-overview.md) | Planned | 20 | Multi-engine search, caching, RAG export |
| 23 | [Build Runner CLI](./23-build-runner-cli/00-overview.md) | Planned | 18 | Build execution, error capture, AI loop |

### AI Systems (2 folders, 67 files)

| # | Feature | Status | Files | Description |
|---|---------|--------|-------|-------------|
| 24 | [Code Generation System](./24-code-generation-system/00-overview.md) | Draft | 34 | AI-powered code gen, parallel execution, Git |
| 25 | [AI Enhancements](./25-ai-enhancements/00-overview.md) | Draft | 33 | Plan Mode, voice resilience, offline-first, cross-project memory |

---

## File Distribution by Category

| Category | Folders | Files | Percentage |
|----------|---------|-------|------------|
| Core Features | 9 | 52 | 37% |
| Frontend Infrastructure | 12 | 27 | 19% |
| CLI Tools | 2 | 38 | 27% |
| AI Systems | 2 | 67 | 48% |
| **Total** | **25** | **140+** | 100% |

---

## Implementation Priority

Based on the [Feature Dependency Diagram](../09-diagrams/08-feature-dependency-diagram.md):

### Phase 1: Foundation
- 01-authentication
- 10-theme-system
- 12-routing-navigation

### Phase 2: Core Infrastructure
- 02-file-management
- 15-api-client
- 16-state-management

### Phase 3: Project Core
- 03-project-management
- 04-spec-editor
- 07-history-system

### Phase 4: AI Foundation
- 05-voice-input
- 06-ai-integration
- 09-knowledge-memory

### Phase 5: Advanced AI
- 24-code-generation-system
- 25-ai-enhancements
- 08-consistency-checker

### Phase 6: UI Polish
- 11-dashboard
- 13-error-ui
- 14-mobile-responsive
- 21-i18n

### Phase 7: Performance & Quality
- 17-monitoring
- 18-realtime
- 19-performance
- 20-testing

### Phase 8: CLI Tools
- 22-golang-search-cli
- 23-build-runner-cli

---

## Creating a New Feature

1. Create folder `{nn}-{feature-name}/`
2. Add `00-overview.md` with feature summary
3. Add component specs (`01-{component}.md`, etc.)
4. Create `tests/` folder for E2E tests
5. Update this index
6. Add to master index

---

## Related Specs

- [Master Index](../00-master-index.md)
- [Roadmap Overview](../08-roadmap-overview/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)
- [Error Management](../06-error-management/00-overview.md)
- [Feature Dependency Diagram](../09-diagrams/08-feature-dependency-diagram.md)
- [Skipped Features](../11-skipped-features/00-overview.md)
