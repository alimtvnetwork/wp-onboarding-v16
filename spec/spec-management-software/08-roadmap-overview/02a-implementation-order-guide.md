# Implementation Order Guide

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Definitive implementation sequence for the Spec Management Software. Each module includes prerequisites, effort estimates, and acceptance gates.

---

## Effort Scale

| Level | Duration | Complexity |
|-------|----------|------------|
| XS | 0.5 day | Single file, no dependencies |
| S | 1-2 days | Few files, minimal integration |
| M | 3-5 days | Multiple files, some integration |
| L | 1-2 weeks | Cross-cutting, significant integration |
| XL | 2-4 weeks | System-wide, complex integration |

---

## Phase 0: Foundation Infrastructure

**Goal:** Establish core infrastructure before any feature work.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 0.1 | Go Project Scaffold | S | None | `go build` succeeds |
| 0.2 | SQLite + GORM Setup | S | 0.1 | Migrations run, connection pool works |
| 0.3 | Configuration System | S | 0.1 | Dot-notation keys load from YAML/env |
| 0.4 | Error Registry | S | 0.1 | All error codes registered, HTTP mapping works |
| 0.5 | Logging Infrastructure | S | 0.3 | Structured JSON logging with levels |
| 0.6 | React Project Scaffold | S | None | Vite builds, Tailwind configured |
| 0.7 | API Client Setup | S | 0.6 | Axios/fetch wrapper with interceptors |
| 0.8 | Theme System (CSS) | S | 0.6 | Light/dark tokens in index.css |

**Phase 0 Total:** ~2 weeks

```
┌─────────────────────────────────────────────────────────────┐
│                    PHASE 0: FOUNDATION                       │
├─────────────────────────────────────────────────────────────┤
│  Backend Track              │  Frontend Track               │
│  ─────────────              │  ──────────────               │
│  0.1 Go Scaffold ──┐        │  0.6 React Scaffold ──┐       │
│                    │        │                       │       │
│  0.2 SQLite ◄──────┤        │  0.7 API Client ◄─────┤       │
│                    │        │                       │       │
│  0.3 Config ◄──────┤        │  0.8 Theme System ◄───┘       │
│                    │        │                               │
│  0.4 Errors ◄──────┤        │                               │
│                    │        │                               │
│  0.5 Logging ◄─────┘        │                               │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Core File Management

**Goal:** Basic CRUD for projects and files without AI features.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 1.1 | Project Service | M | 0.2, 0.4 | Create/list/delete projects works |
| 1.2 | File Service | M | 1.1 | Read/write/delete files in workDirectory |
| 1.3 | Folder Tree Service | S | 1.2 | Recursive listing with metadata |
| 1.4 | Path Validation | S | 1.2 | Traversal attacks blocked (ERR 1001-1003) |
| 1.5 | Project Dashboard UI | M | 0.7, 0.8 | List projects, create new |
| 1.6 | Folder Tree UI | M | 1.5 | Expandable tree, context menu |
| 1.7 | Markdown Editor UI | L | 1.6 | CodeMirror with preview, save |
| 1.8 | File Operations UI | S | 1.6 | Create/rename/delete with confirmation |

**Phase 1 Total:** ~3 weeks

```
┌─────────────────────────────────────────────────────────────┐
│                  PHASE 1: FILE MANAGEMENT                    │
├─────────────────────────────────────────────────────────────┤
│  Backend                    │  Frontend                      │
│  ───────                    │  ────────                      │
│  1.1 Project Service ──┐    │  1.5 Dashboard UI ──┐          │
│                        │    │                     │          │
│  1.2 File Service ◄────┤    │  1.6 Tree UI ◄──────┤          │
│                        │    │                     │          │
│  1.3 Folder Tree ◄─────┤    │  1.7 Editor UI ◄────┤          │
│                        │    │                     │          │
│  1.4 Path Validation ◄─┘    │  1.8 File Ops UI ◄──┘          │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 2: Git & History System

**Goal:** Version control with snapshots and diff viewing.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 2.1 | Git Integration (go-git) | M | 1.2 | Init repo, commit, branch operations |
| 2.2 | Auto-Commit Service | S | 2.1 | Commits on file save with message |
| 2.3 | Snapshot Service | M | 2.1 | Create/list/restore named snapshots |
| 2.4 | Diff Service | S | 2.1 | Generate unified diff between versions |
| 2.5 | History UI | M | 1.7 | Timeline view, commit list |
| 2.6 | Diff Viewer UI | M | 2.5 | Side-by-side comparison |
| 2.7 | Snapshot Manager UI | S | 2.5 | Create/restore with confirmation |

**Phase 2 Total:** ~2.5 weeks

```
┌─────────────────────────────────────────────────────────────┐
│                  PHASE 2: GIT & HISTORY                      │
├─────────────────────────────────────────────────────────────┤
│  2.1 Git Integration                                         │
│         │                                                    │
│         ├──► 2.2 Auto-Commit                                 │
│         │                                                    │
│         ├──► 2.3 Snapshot Service ──► 2.7 Snapshot UI        │
│         │                                                    │
│         └──► 2.4 Diff Service ──► 2.5 History UI             │
│                                        │                     │
│                                        └──► 2.6 Diff Viewer  │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 3: LLM Server Management

**Goal:** Multi-backend LLM server control with health monitoring.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 3.1 | LLM Process Manager | L | 0.3, 0.5 | Start/stop/restart Ollama/llama.cpp |
| 3.2 | Model Seeding Service | M | 3.1 | Load models by category from config |
| 3.3 | Health Check Service | S | 3.1 | Periodic health probes, status enum |
| 3.4 | llama-swap Integration | M | 3.1 | YAML generation, proxy management |
| 3.5 | Router Mode Service | M | 3.1 | Dynamic model switching |
| 3.6 | LLM Dashboard UI | M | 0.7 | Server status cards, controls |
| 3.7 | Model Selector UI | S | 3.6 | Category-based model picker |
| 3.8 | Live Logging UI | M | 3.6 | Ring buffer display, WebSocket feed |

**Phase 3 Total:** ~3 weeks

```
┌─────────────────────────────────────────────────────────────┐
│                PHASE 3: LLM SERVER MANAGEMENT                │
├─────────────────────────────────────────────────────────────┤
│                     3.1 Process Manager                      │
│                            │                                 │
│         ┌──────────────────┼──────────────────┐              │
│         │                  │                  │              │
│         ▼                  ▼                  ▼              │
│  3.2 Model Seeding   3.3 Health Check  3.4 llama-swap        │
│                            │                                 │
│                            │           3.5 Router Mode       │
│                            │                  │              │
│                            ▼                  ▼              │
│                     3.6 LLM Dashboard UI                     │
│                            │                                 │
│              ┌─────────────┼─────────────┐                   │
│              ▼             ▼             ▼                   │
│       3.7 Model UI   3.8 Live Logging                        │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 4: Voice & Transcription Pipeline

**Goal:** Voice input to text with proofreading.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 4.1 | Audio Capture Service | S | 0.3 | WAV/WebM recording, format validation |
| 4.2 | Whisper Integration | M | 3.1, 4.1 | Transcribe audio via voice model |
| 4.3 | Proofread Service | S | 4.2 | Clean transcription, fix errors |
| 4.4 | Voice Input UI | M | 0.7 | Record button, waveform, status |
| 4.5 | Transcription Review UI | S | 4.4 | Edit before saving |

**Phase 4 Total:** ~1.5 weeks

```
┌─────────────────────────────────────────────────────────────┐
│              PHASE 4: VOICE & TRANSCRIPTION                  │
├─────────────────────────────────────────────────────────────┤
│  4.1 Audio Capture ──► 4.2 Whisper ──► 4.3 Proofread         │
│         │                                     │              │
│         ▼                                     ▼              │
│  4.4 Voice Input UI ─────────────► 4.5 Transcription UI      │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 5: RAG System

**Goal:** Vector search with context retrieval for AI operations.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 5.1 | sqlite-vss Setup | M | 0.2 | Vector tables created, HNSW index |
| 5.2 | Embedding Service | M | 3.1, 5.1 | Generate embeddings via model |
| 5.3 | Chunk Splitter | S | 1.2 | Markdown → chunks with stable IDs |
| 5.4 | Ingestion Pipeline | M | 5.2, 5.3 | Watch files, index on change |
| 5.5 | Retrieval Service | M | 5.1, 5.4 | Top-K retrieval with RRF scoring |
| 5.6 | Context Window Manager | M | 5.5 | Token budgeting, hierarchical assembly |
| 5.7 | Cache Layer | S | 5.5 | TTL cache for retrieval results |

**Phase 5 Total:** ~3 weeks

```
┌─────────────────────────────────────────────────────────────┐
│                    PHASE 5: RAG SYSTEM                       │
├─────────────────────────────────────────────────────────────┤
│  5.1 sqlite-vss ◄───┐                                        │
│         │           │                                        │
│         ▼           │                                        │
│  5.2 Embedding ◄────┴── 5.3 Chunk Splitter                   │
│         │                      │                             │
│         └──────────┬───────────┘                             │
│                    ▼                                         │
│             5.4 Ingestion Pipeline                           │
│                    │                                         │
│                    ▼                                         │
│             5.5 Retrieval Service                            │
│                    │                                         │
│         ┌──────────┴──────────┐                              │
│         ▼                     ▼                              │
│  5.6 Context Manager    5.7 Cache Layer                      │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 6: Instruction Builder & Prompt System

**Goal:** Voice-to-spec pipeline with prompt presets.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 6.1 | Prompt Preset Repository | M | 0.2 | CRUD for presets by category |
| 6.2 | Preset Layering Service | S | 6.1 | Base + custom + override merge |
| 6.3 | Instruction Classifier | M | 3.1 | Classify input type (idea/feature/task) |
| 6.4 | Enhancement Service | M | 6.1, 6.3 | Enhance raw input per type |
| 6.5 | Instruction Generator | L | 5.6, 6.4 | Generate structured instructions |
| 6.6 | Task Decomposition | M | 6.5 | Split instruction into tasks |
| 6.7 | Artifact Storage | S | 1.2 | Save MD + JSON to filesystem |
| 6.8 | Prompt Preset UI | M | 0.7 | Browse/edit/create presets |
| 6.9 | Instruction Builder UI | L | 4.4, 6.8 | Full pipeline wizard |
| 6.10 | Task Viewer UI | M | 6.9 | Task list with dependencies |

**Phase 6 Total:** ~4 weeks

```
┌─────────────────────────────────────────────────────────────┐
│           PHASE 6: INSTRUCTION BUILDER & PROMPTS             │
├─────────────────────────────────────────────────────────────┤
│  6.1 Preset Repository ──► 6.2 Layering Service              │
│         │                                                    │
│         └──────────────┐                                     │
│                        ▼                                     │
│  Voice Input ──► 6.3 Classifier ──► 6.4 Enhancement          │
│                                           │                  │
│                        ┌──────────────────┘                  │
│                        ▼                                     │
│  RAG Context ──► 6.5 Instruction Generator                   │
│                        │                                     │
│                        ├──► 6.6 Task Decomposition           │
│                        │                                     │
│                        └──► 6.7 Artifact Storage             │
│                                                              │
│  ─────────────────── Frontend ──────────────────────         │
│                                                              │
│  6.8 Preset UI ──► 6.9 Builder UI ──► 6.10 Task Viewer       │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 7: Task Execution Engine

**Goal:** Execute instruction tasks with parallel/sequential support.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 7.1 | Task Executor Service | L | 6.6 | Worker pool, dependency resolution |
| 7.2 | Topological Sorter | S | 7.1 | Order tasks by DependsOn |
| 7.3 | Context Passing | M | 7.1 | Output flows to dependent tasks |
| 7.4 | Parallel Scheduler | M | 7.1, 7.2 | Independent tasks run concurrently |
| 7.5 | Execution Monitor | S | 7.1 | Track state, emit progress events |
| 7.6 | Memory Compression | M | 5.6, 7.3 | Summarize for multi-turn chains |
| 7.7 | Execution Dashboard UI | M | 6.10 | Progress visualization, cancel |
| 7.8 | Execution Log UI | S | 7.7 | Per-task output viewer |

**Phase 7 Total:** ~3 weeks

```
┌─────────────────────────────────────────────────────────────┐
│              PHASE 7: TASK EXECUTION ENGINE                  │
├─────────────────────────────────────────────────────────────┤
│                  7.1 Task Executor                           │
│                        │                                     │
│         ┌──────────────┼──────────────┐                      │
│         ▼              ▼              ▼                      │
│  7.2 Topo Sort   7.3 Context    7.4 Parallel                 │
│         │              │         Scheduler                   │
│         │              │              │                      │
│         │              ▼              │                      │
│         │       7.6 Memory           │                      │
│         │       Compression          │                      │
│         │              │              │                      │
│         └──────────────┼──────────────┘                      │
│                        ▼                                     │
│                 7.5 Execution Monitor                        │
│                        │                                     │
│         ┌──────────────┴──────────────┐                      │
│         ▼                             ▼                      │
│  7.7 Execution Dashboard        7.8 Log UI                   │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 8: Consistency & Quality Loop

**Goal:** Detect inconsistencies, ask questions, regenerate until 99%.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 8.1 | Inconsistency Detector | L | 5.5, 6.5 | Scan specs, identify conflicts |
| 8.2 | Clarification Generator | M | 8.1 | Generate targeted questions |
| 8.3 | Answer Processor | M | 8.2 | Parse answers, update context |
| 8.4 | Regeneration Service | M | 6.5, 8.3 | Regenerate with new context |
| 8.5 | Quality Scorer | S | 8.1 | Calculate consistency percentage |
| 8.6 | Consistency Report Generator | S | 8.5 | Output 99-consistency-report.md |
| 8.7 | Clarification UI | M | 0.7 | Question/answer interface |
| 8.8 | Quality Dashboard UI | M | 8.7 | Score display, trend chart |

**Phase 8 Total:** ~3.5 weeks

```
┌─────────────────────────────────────────────────────────────┐
│            PHASE 8: CONSISTENCY & QUALITY LOOP               │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Specs ──► 8.1 Inconsistency Detector                        │
│                     │                                        │
│                     ├──► 8.5 Quality Scorer                  │
│                     │           │                            │
│                     │           ▼                            │
│                     │    8.6 Report Generator                │
│                     │                                        │
│                     ▼                                        │
│              8.2 Clarification Generator                     │
│                     │                                        │
│                     ▼                                        │
│              8.7 Clarification UI ◄──► User                  │
│                     │                                        │
│                     ▼                                        │
│              8.3 Answer Processor                            │
│                     │                                        │
│                     ▼                                        │
│              8.4 Regeneration Service ──► Updated Specs      │
│                                                              │
│              8.8 Quality Dashboard UI                        │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 9: Idea Management & Promotion

**Goal:** Capture ideas and promote to instructions.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 9.1 | Idea Service | M | 1.2, 4.3 | Create/list/update ideas |
| 9.2 | Idea Indexer | S | 5.4, 9.1 | Index ideas for RAG |
| 9.3 | Promotion Service | M | 6.5, 9.1 | Idea → Instruction conversion |
| 9.4 | Top-K Pinning | S | 9.1 | Pin important artifacts |
| 9.5 | Idea Capture UI | M | 4.4 | Quick capture with tags |
| 9.6 | Idea Browser UI | M | 9.5 | List, filter, search ideas |
| 9.7 | Promotion Wizard UI | M | 9.6 | Guide idea → instruction |

**Phase 9 Total:** ~2.5 weeks

```
┌─────────────────────────────────────────────────────────────┐
│            PHASE 9: IDEA MANAGEMENT & PROMOTION              │
├─────────────────────────────────────────────────────────────┤
│  Voice Input                                                 │
│      │                                                       │
│      ▼                                                       │
│  9.1 Idea Service ──► 9.2 Idea Indexer (RAG)                 │
│      │                                                       │
│      ├──► 9.4 Top-K Pinning                                  │
│      │                                                       │
│      └──► 9.3 Promotion Service ──► Instructions             │
│                                                              │
│  ─────────────────── Frontend ──────────────────────         │
│                                                              │
│  9.5 Capture UI ──► 9.6 Browser UI ──► 9.7 Promotion Wizard  │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 10: Real-time & Monitoring

**Goal:** WebSocket streaming, system health, error tracking.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 10.1 | WebSocket Server | M | 0.1 | Connection management, heartbeat |
| 10.2 | SSE Fallback | S | 10.1 | Server-sent events for degraded mode |
| 10.3 | Event Bus | M | 10.1 | Pub/sub for internal events |
| 10.4 | System Health Service | S | 0.3 | CPU, memory, disk metrics |
| 10.5 | Error Aggregator | M | 0.4 | Collect, dedupe, fingerprint errors |
| 10.6 | Network Capture Service | S | 10.1 | Request/response logging |
| 10.7 | WebSocket Hook | S | 0.7, 10.1 | useWebSocket with reconnect |
| 10.8 | Offline Queue Hook | S | 10.7 | Queue messages when disconnected |
| 10.9 | Health Dashboard UI | M | 10.7 | Live system metrics |
| 10.10 | Error Dashboard UI | M | 10.9 | Error list, detail modal |

**Phase 10 Total:** ~3 weeks

---

## Phase 11: AI Chat & Interactive Features

**Goal:** Conversational AI interface with context awareness.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 11.1 | Chat Session Service | M | 3.1, 5.5 | Manage conversation history |
| 11.2 | Context Injector | M | 5.6, 11.1 | Inject RAG context into prompts |
| 11.3 | Streaming Response Handler | M | 10.1, 11.1 | SSE/WebSocket streaming |
| 11.4 | Chat Panel UI | L | 10.7 | Message list, input, streaming display |
| 11.5 | Context Preview UI | S | 11.4 | Show injected context |
| 11.6 | Quick Actions UI | S | 11.4 | Suggested prompts |

**Phase 11 Total:** ~2.5 weeks

---

## Phase 12: Polish & Production

**Goal:** Performance, accessibility, deployment readiness.

| # | Module | Effort | Prerequisites | Acceptance Gate |
|---|--------|--------|---------------|-----------------|
| 12.1 | Performance Optimization | M | All UI | Lighthouse score > 90 |
| 12.2 | Accessibility Audit | M | All UI | WCAG 2.1 AA compliance |
| 12.3 | E2E Test Suite | L | All | 6 critical paths pass |
| 12.4 | Docker Configuration | M | All backend | `docker compose up` works |
| 12.5 | CI/CD Pipeline | M | 12.3, 12.4 | GitHub Actions green |
| 12.6 | Documentation | M | All | README, API docs, user guide |
| 12.7 | Security Hardening | M | All | Rate limiting, input sanitization |

**Phase 12 Total:** ~3 weeks

---

## Master Timeline

```
┌─────────────────────────────────────────────────────────────────────┐
│                        IMPLEMENTATION TIMELINE                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Week  1-2   ████████ Phase 0: Foundation Infrastructure             │
│                                                                      │
│  Week  3-5   ████████████ Phase 1: Core File Management              │
│                                                                      │
│  Week  6-8   ██████████ Phase 2: Git & History                       │
│                                                                      │
│  Week  9-11  ████████████ Phase 3: LLM Server Management             │
│                                                                      │
│  Week 12-13  ██████ Phase 4: Voice & Transcription                   │
│                                                                      │
│  Week 14-16  ████████████ Phase 5: RAG System                        │
│                                                                      │
│  Week 17-20  ████████████████ Phase 6: Instruction Builder           │
│                                                                      │
│  Week 21-23  ████████████ Phase 7: Task Execution                    │
│                                                                      │
│  Week 24-27  ██████████████ Phase 8: Consistency Loop                │
│                                                                      │
│  Week 28-30  ██████████ Phase 9: Idea Management                     │
│                                                                      │
│  Week 31-33  ████████████ Phase 10: Real-time & Monitoring           │
│                                                                      │
│  Week 34-36  ██████████ Phase 11: AI Chat                            │
│                                                                      │
│  Week 37-39  ████████████ Phase 12: Polish & Production              │
│                                                                      │
├─────────────────────────────────────────────────────────────────────┤
│  TOTAL: ~39 weeks (9-10 months) for full implementation              │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Critical Path

The following modules are on the critical path and block multiple downstream features:

1. **0.2 SQLite + GORM** → Blocks all data persistence
2. **1.2 File Service** → Blocks all file operations
3. **3.1 LLM Process Manager** → Blocks all AI features
4. **5.5 Retrieval Service** → Blocks context-aware features
5. **6.5 Instruction Generator** → Blocks spec generation
6. **7.1 Task Executor** → Blocks automated workflows

**Recommendation:** Assign senior engineers to critical path modules.

---

## Parallel Tracks

These tracks can proceed independently after Phase 0:

| Track A (Backend Core) | Track B (Frontend Core) | Track C (AI/ML) |
|------------------------|-------------------------|-----------------|
| 1.1-1.4 File Services | 1.5-1.8 File UI | 3.1-3.5 LLM Services |
| 2.1-2.4 Git Services | 2.5-2.7 History UI | 4.1-4.3 Voice Pipeline |
| 5.1-5.7 RAG System | 6.8-6.10 Builder UI | 6.1-6.7 Instruction Services |

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| LLM server instability | Implement health checks early (3.3), failover in Phase 3 |
| RAG accuracy issues | Tune RRF scoring (5.5), add reranking in Phase 5 |
| WebSocket reliability | SSE fallback (10.2), offline queue (10.8) |
| Performance degradation | Early Lighthouse testing, lazy loading from Phase 1 |

---

## Acceptance Criteria Per Phase

Each phase is complete when:
- [ ] All module acceptance gates pass
- [ ] Unit test coverage > 80%
- [ ] No critical/high severity bugs
- [ ] Integration tests pass
- [ ] Code review approved
- [ ] Documentation updated

---

## Related Documents

- [Roadmap Overview](./01-roadmap-overview.md)
- [Error Code Registry](../06-error-management/error-code-registry.md)
- [Database Design](../07-database-design/00-overview.md)
- [Implementation Guidelines](./03-implementation-guidelines.md)
