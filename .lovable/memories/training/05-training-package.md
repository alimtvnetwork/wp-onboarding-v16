# AI Training Package Guide

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Purpose:** Exact file lists for training AI models on specific subsystems

---

## Overview

This guide specifies exactly which files to share when training an external AI model to implement specific subsystems. Each package is self-contained and ordered for optimal context building.

---

## 📦 Package 1: AI Bridge (30-ai-bridge)

**Purpose:** External AI adapter for normalizing inputs and routing to LLM providers  
**Files:** 8 | **Error Range:** 9000-9499

### Core Files (Share in Order)

```
spec/spec-management-software/05-features/30-ai-bridge/
├── 00-overview.md              # Architecture overview (START HERE)
├── 01-architecture.md          # BackendAdapter interface, NormalizedRequest
├── 02-input-formats.md         # Markdown, JSON, YAML, CSV parsers
├── 03-startup-modes.md         # Local Binary + Daemon modes
├── 04-api-interface.md         # REST API for daemon mode
├── 05-error-codes.md           # Error codes (9000-9499)
├── 06-configuration.md         # Configuration schema
└── 99-consistency-report.md    # Cross-reference validation
```

### Context Files (Optional)

| File | Purpose |
|------|---------|
| `AI-HANDOFF-GUIDE.md` (Section 6-7) | Minimal handoff + startup modes |
| `06-ai-integration/01-ai-integration.md` | LLM provider abstraction |

### AI Instructions

```markdown
You are implementing the AI Bridge module for Nexus Flow.

### Reading Order
1. Read 00-overview.md for architecture
2. Read 01-architecture.md for interface definitions
3. Read 02-input-formats.md for parser specifications
4. Read 03-startup-modes.md for execution modes
5. Read 04-api-interface.md for REST endpoints
6. Read 05-error-codes.md for error handling

### Key Constraints
- Use Go 1.21+
- Support Markdown, JSON, YAML, CSV input formats
- Support both Local Binary and Background Daemon modes
- Error codes in 9000-9499 range
- Daemon runs on port 8089 by default
- Route to Ollama or llama.cpp via BackendAdapter interface

### Success Criteria
- All four input formats parse correctly
- `nexusflow run prompt.md` works in binary mode
- `nexusflow daemon start` launches REST API
- Streaming responses work via SSE
```

---

## 📦 Package 2: gsearch CLI (22-golang-search-cli)

**Purpose:** Multi-engine web search with caching and RAG export  
**Files:** 26 | **Error Range:** 6000-6999

### Core Files (Share in Order)

```
spec/spec-management-software/05-features/22-golang-search-cli/
├── 00-overview.md               # START HERE
├── 01-cli-framework.md          # Cobra + Viper setup
├── 02-configuration.md          # Config schema
├── 03-database-schema.md        # SQLite/GORM models
├── 13-implementation-guide.md   # Build order (CRITICAL)
├── 15-error-codes.md            # Error registry (6xxx)
└── 99-consistency-report.md     # Health score: 100/100
```

### Extended Files (For Complete Implementation)

```
├── 04-html-parser.md            # goquery HTML extraction
├── 05-google-api.md             # Google search integration
├── 06-duckduckgo.md             # DuckDuckGo integration
├── 07-bing-search.md            # Bing search integration
├── 08-method-switching.md       # Weighted random engine selection
├── 09-nested-search.md          # Recursive search patterns
├── 10-caching-system.md         # 5-6 day result caching
├── 11-rag-export.md             # RAG memory export
├── 12-testing-strategy.md       # Integration test patterns
├── 16-observability.md          # Prometheus, OTEL, logging
├── 17-deployment-guide.md       # Cross-platform distribution
└── 18-full-site-crawler.md      # Full website crawling
```

### AI Instructions

```markdown
You are implementing the `gsearch` CLI tool.

### Reading Order
1. Read 00-overview.md for architecture
2. Read 01-cli-framework.md for project structure
3. Read 13-implementation-guide.md for build order
4. Read 15-error-codes.md for error handling
5. Implement in order from 13-implementation-guide.md

### Key Constraints
- Use Go 1.21+
- Use Cobra for CLI, Viper for config
- Use GORM with SQLite for persistence
- Error codes in 6xxx range
- All output must support --json flag
- Follow 60/30/10 test pyramid (integration-heavy)

### Success Criteria
- `gsearch "query"` returns results
- `gsearch --engine google` works
- `gsearch --export-rag` exports to memory
- Cache deduplication working
- Engine failover working
```

---

## 📦 Package 3: brun CLI (23-build-runner-cli)

**Purpose:** Cross-platform build execution with AI integration  
**Files:** 18 | **Error Range:** 7100-7599

### Core Files (Share in Order)

```
spec/spec-management-software/05-features/23-build-runner-cli/
├── 00-overview.md               # START HERE
├── 01-core-architecture.md      # System design
├── 02-cli-interface.md          # Command structure
├── 03-configuration.md          # Config schema
├── 14-implementation-guide.md   # Build order (CRITICAL)
├── 06-error-handling.md         # Error codes (7xxx)
└── 99-consistency-report.md     # Health score: 100/100
```

### Extended Files (For Complete Implementation)

```
├── 04-runtime-executors.md      # PowerShell, Node.js, Go runners
├── 05-port-management.md        # Port checking, fallback
├── 07-build-profiles.md         # Saved configurations
├── 08-asset-operations.md       # File copy and override modes
├── 09-integration-api.md        # Subprocess communication
├── 10-data-models.md            # GORM entities
├── 11-acceptance-criteria.md    # Validation requirements
├── 12-ai-config-generation.md   # AI-assisted config creation
├── 13-testing-strategy.md       # Integration tests
├── 15-observability.md          # Prometheus, health, logging
└── 16-deployment-guide.md       # Cross-platform installation
```

### AI Instructions

```markdown
You are implementing the `brun` CLI tool.

### Reading Order
1. Read 00-overview.md first
2. Read 01-core-architecture.md for system design
3. Read 14-implementation-guide.md for build order
4. Read 06-error-handling.md for error codes

### Key Constraints
- Use Go 1.21+
- Use Cobra for CLI, Viper for config
- Use GORM with SQLite for persistence
- Error codes in 7xxx range (7100-7599)
- Support PowerShell, Node.js (npm/yarn/bun), and Go
- All output must support --json flag

### Success Criteria
- `brun build --profile backend-api` works
- `brun check --go ./cmd/app` works
- `brun port --check 8080 --fallback 8081` works
- Build profiles save/load correctly
- AI config generation from natural language
```

---

## 📦 Package 4: Full Backend (SM-010)

**Purpose:** Complete Golang backend for Spec Management Software  
**Files:** 375+ | **Error Ranges:** All (1xxx-12xxx)

### Minimum Required Files

```
spec/spec-management-software/
├── 00-master-index.md                    # Navigation (START HERE)
├── CONTEXT-FOR-AI.md                     # AI-specific context
├── AI-HANDOFF-GUIDE.md                   # Handoff instructions
└── 05-features/
    └── 00-security-cross-cutting.md      # Security requirements

Root files:
├── plan.md                               # Task backlog
└── .lovable/reliability-risk-report.md   # Implementation risks
```

### Feature Folders (Share by Dependency)

| Phase | Folders | Purpose |
|-------|---------|---------|
| 1 | `01-authentication/` | Auth system (JWT, sessions) |
| 2 | `02-file-management/` | File CRUD operations |
| 3 | `07-history-system/` | Git integration |
| 4 | `06-ai-integration/` | LLM providers |
| 5 | `09-knowledge-memory/` | RAG system |
| 6 | `27-automation-pipeline/` | Pipeline executor |
| 7 | `22-golang-search-cli/` | gsearch CLI |
| 8 | `23-build-runner-cli/` | brun CLI |
| 9 | `30-ai-bridge/` | AI Bridge adapter |

### Supporting Folders

```
├── 03-data-models/              # TypeScript interfaces (port to Go)
├── 04-coding-guidelines/        # Naming conventions
├── 06-error-management/         # Error codes
├── 07-database-design/          # Schema
└── api/                         # OpenAPI specification
```

### AI Instructions

```markdown
You are implementing the Golang backend for Spec Management Software.

### Reading Order
1. Read 00-master-index.md for full navigation
2. Read CONTEXT-FOR-AI.md for AI-specific context
3. Read 00-security-cross-cutting.md for security requirements
4. Read each feature spec in dependency order (see plan.md)

### Technology Stack
- Language: Go 1.21+
- Web framework: Chi or Echo
- Database: SQLite with GORM
- Auth: Argon2id + JWT (RS256)
- AI backends: Ollama or llama.cpp

### Implementation Order
1. Project structure and config
2. Database schema and migrations
3. Authentication system
4. File management
5. History system (Git integration)
6. AI integration
7. RAG system
8. Automation pipeline
9. CLI tools (gsearch, brun)
10. AI Bridge

### Error Code Ranges
| Range | Module |
|-------|--------|
| 1xxx | General errors |
| 2xxx | Authentication |
| 3xxx | File management |
| 4xxx | AI integration |
| 5xxx | RAG/Knowledge |
| 6xxx | gsearch CLI |
| 7xxx | brun CLI |
| 8xxx | Automation pipeline |
| 9xxx | AI Bridge |
| 10xxx | Nexus Flow |
| 11xxx | Voice CLI |
| 12xxx | Code generation |
```

---

## 📦 Package 5: Automation Pipeline (27-automation-pipeline)

**Purpose:** Agentic automation with React Flow canvas  
**Files:** 34 | **Error Range:** 8000-8999

### Core Files

```
spec/spec-management-software/05-features/27-automation-pipeline/
├── 00-overview.md               # START HERE
├── 01-database-schema.md        # SQLite schema
├── 04-stage-executor.md         # Execution engine
├── 10-react-flow-canvas.md      # Node-based UI
└── 28-res-integration.md        # Resilient Execution System
```

---

## Quick Reference Matrix

| Package | Core Files | Error Range | Port |
|---------|------------|-------------|------|
| AI Bridge | 8 | 9000-9499 | 8089 |
| gsearch | 26 | 6000-6999 | N/A (CLI) |
| brun | 18 | 7100-7599 | N/A (CLI) |
| Full Backend | 375+ | 1xxx-12xxx | 8080-8093 |
| Automation | 34 | 8000-8999 | N/A |

---

## Validation Questions

After training, verify AI understanding:

| Question | Expected Answer |
|----------|-----------------|
| What password hashing algorithm? | Argon2id |
| What error range for gsearch? | 6xxx |
| What error range for AI Bridge? | 9xxx |
| JWT access token TTL? | 15 minutes |
| What CLI framework? | Cobra + Viper |
| AI Bridge daemon port? | 8089 |
| What input formats does AI Bridge support? | Markdown, JSON, YAML, CSV |

---

*This training package was created on 2026-01-31 for AI model handoff.*
