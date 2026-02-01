# AI Handoff Guide for Spec Management Software

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Purpose:** Guide for sharing project specifications with other AI models

---

## Quick Reference

| Component | Folder to Share | Description |
|-----------|-----------------|-------------|
| **Full Project** | `spec/spec-management-software/` | Complete spec set (375+ files) |
| **gsearch CLI** | `spec/spec-management-software/05-features/22-golang-search-cli/` | Multi-engine search CLI (26 files) |
| **brun CLI** | `spec/spec-management-software/05-features/23-build-runner-cli/` | Build runner CLI (18 files) |
| **Golang Backend** | See SM-010 section below | Core backend service |

---

## 1. Sharing the gsearch CLI

### What to Share

```
spec/spec-management-software/05-features/22-golang-search-cli/
├── configs/                      # Sample config files
├── 00-overview.md               # Start here
├── 01-cli-framework.md          # Cobra + Viper setup
├── 02-configuration.md          # Config schema
├── 03-database-schema.md        # SQLite/GORM models
├── 04-html-parser.md            # HTML parsing logic
├── 05-google-api.md             # Google search integration
├── 06-duckduckgo.md             # DuckDuckGo integration
├── 07-bing-search.md            # Bing search integration
├── 08-method-switching.md       # Engine failover logic
├── 09-nested-search.md          # Recursive search patterns
├── 10-caching-system.md         # SQLite caching
├── 11-rag-export.md             # Memory export for RAG
├── 12-testing-strategy.md       # Test pyramid
├── 13-implementation-guide.md   # Build order
├── 14-remediation-plan.md       # Known issues
├── 15-error-codes.md            # Error code registry (6xxx)
├── 16-observability.md          # Prometheus + OTEL
├── 17-deployment-guide.md       # Production setup
├── 18-full-site-crawler.md      # Site crawling
├── 19-authority-credibility-scoring.md
├── 20-trend-analysis-engine.md
├── 21-trend-analyzer-implementation.md
├── 22-settings-service-implementation.md
├── 23-settings-ui-page.md
├── 99-consistency-report.md     # Health score: 100/100
└── 99-remediation-summary.md
```

### Handoff Instructions for AI

```markdown
## Instructions for AI

You are implementing the `gsearch` CLI tool. 

### Reading Order
1. Read 00-overview.md first for architecture
2. Read 01-cli-framework.md for project structure
3. Read 13-implementation-guide.md for build order
4. Read 15-error-codes.md for error handling
5. Implement in the order specified in 13-implementation-guide.md

### Key Constraints
- Use Go 1.21+
- Use Cobra for CLI, Viper for config
- Use GORM with SQLite for persistence
- Error codes are in the 6xxx range
- All output must support --json flag
- Follow 60/30/10 test pyramid (integration-heavy)

### Success Criteria
- All commands work: `gsearch "query"`, `gsearch --engine google`, `gsearch --export-rag`
- Cache deduplication working
- Engine failover working
- JSON output parseable
```

---

## 2. Sharing the brun CLI

### What to Share

```
spec/spec-management-software/05-features/23-build-runner-cli/
├── 00-overview.md               # Start here
├── 01-core-architecture.md      # System design
├── 02-cli-interface.md          # Command structure
├── 03-configuration.md          # Config schema
├── 04-runtime-executors.md      # PowerShell, Node.js, Go
├── 05-port-management.md        # Port checking, fallback
├── 06-error-handling.md         # Error codes (7xxx)
├── 07-build-profiles.md         # Saved configurations
├── 08-asset-operations.md       # Clear/copy/override
├── 09-integration-api.md        # Main app integration
├── 10-data-models.md            # TypeScript interfaces
├── 11-acceptance-criteria.md    # Test requirements
├── 12-ai-config-generation.md   # NL to JSON config
├── 13-testing-strategy.md       # Test approach
├── 14-implementation-guide.md   # Build order
├── 15-observability.md          # Metrics + logging
├── 16-deployment-guide.md       # Production setup
└── 99-consistency-report.md     # Health score: 100/100
```

### Handoff Instructions for AI

```markdown
## Instructions for AI

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
- Error codes are in the 7xxx range (7100-7599)
- Support PowerShell, Node.js (npm/yarn/bun), and Go
- All output must support --json flag

### Success Criteria
- Commands work: `brun build`, `brun check`, `brun run`, `brun port`
- Build profiles save/load correctly
- Port availability checking with fallback
- AI config generation from natural language
```

---

## 3. Sharing the Full Backend (SM-010)

### What to Share

For full Golang backend implementation, share these folders:

```
Required Context:
├── spec/spec-management-software/
│   ├── 00-master-index.md                    # Navigation
│   ├── CONTEXT-FOR-AI.md                     # AI-specific context
│   ├── 03-data-models/                       # TypeScript interfaces
│   ├── 04-coding-guidelines/                 # Naming conventions
│   ├── 05-features/
│   │   ├── 00-security-cross-cutting.md      # Security requirements
│   │   ├── 01-authentication/                # Auth system
│   │   ├── 02-file-management/               # File operations
│   │   ├── 06-ai-integration/                # AI system
│   │   ├── 07-history-system/                # Git integration
│   │   ├── 09-knowledge-memory/              # RAG system
│   │   ├── 16-state-management/              # State patterns
│   │   ├── 18-realtime/                      # WebSocket/SSE
│   │   ├── 22-golang-search-cli/             # gsearch CLI
│   │   ├── 23-build-runner-cli/              # brun CLI
│   │   └── 27-automation-pipeline/           # Pipeline system
│   ├── 06-error-management/                  # Error codes
│   ├── 07-database-design/                   # Schema
│   └── api/                                  # OpenAPI spec
├── .lovable/reliability-risk-report.md       # Implementation risks
└── plan.md                                   # Task backlog
```

### Handoff Instructions for AI

```markdown
## Instructions for AI

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

### Error Code Ranges
- 1xxx: General errors
- 2xxx: Authentication errors
- 3xxx: File management errors
- 4xxx: AI integration errors
- 5xxx: RAG/Knowledge errors
- 6xxx: gsearch CLI errors
- 7xxx: brun CLI errors
- 8xxx: Automation pipeline errors
```

---

## 4. AI Understanding Verification

### Potential Confusion Points

| Area | Potential Confusion | Clarification |
|------|---------------------|---------------|
| **Backend type** | "Ollama" vs "llama.cpp" | Both are supported; Ollama is managed, llama.cpp is manual |
| **Model categories** | What models to use for what | thinking → reasoning, writing → content, voice → STT, coding → code |
| **Path handling** | Absolute vs relative | ALWAYS use relative paths from workDirectory |
| **History snapshots** | .history folder purpose | Local diff storage, NOT Git replacement |
| **Error codes** | Which range to use | Each module has its own range (see list above) |

### Validation Questions

Ask the AI these questions to verify understanding:

1. "What password hashing algorithm should be used for new passwords?" → Argon2id
2. "What error code range is used for gsearch?" → 6xxx
3. "What is the JWT access token TTL?" → 15 minutes
4. "What CLI framework is used?" → Cobra + Viper
5. "Where are model files stored?" → Configurable via `ai.models.rootPaths`

---

## 5. Folder Structure Summary

```
.
├── .lovable/
│   ├── reliability-risk-report.md   # 95% success probability
│   └── memory/suggestions/          # Active suggestions
├── plan.md                          # Master task backlog
├── spec/
│   └── spec-management-software/
│       ├── 00-master-index.md       # ⭐ START HERE
│       ├── CONTEXT-FOR-AI.md        # ⭐ AI-SPECIFIC CONTEXT
│       ├── AI-HANDOFF-GUIDE.md      # ⭐ THIS FILE
│       ├── 01-ideas/                # Concept documents
│       ├── 02-instructions/         # Refined directions
│       ├── 03-data-models/          # TypeScript interfaces
│       ├── 04-coding-guidelines/    # Standards
│       ├── 05-features/             # Feature specs (29 modules)
│       │   ├── 00-security-cross-cutting.md  # ⭐ SECURITY
│       │   ├── 22-golang-search-cli/         # gsearch CLI
│       │   └── 23-build-runner-cli/          # brun CLI
│       ├── 06-error-management/     # Error codes
│       ├── 07-database-design/      # Schema
│       ├── 08-roadmap-overview/     # Timeline
│       ├── 09-diagrams/             # Visual flows
│       ├── 14-microservices/        # Service specs
│       └── api/                     # OpenAPI
└── link-manager/                    # SEPARATE PROJECT (extracted)
```

---

## 6. Minimal Handoff Package

For quick handoffs, share only these files:

| Purpose | Files |
|---------|-------|
| **gsearch only** | `22-golang-search-cli/00-overview.md`, `13-implementation-guide.md`, `15-error-codes.md` |
| **brun only** | `23-build-runner-cli/00-overview.md`, `14-implementation-guide.md`, `06-error-handling.md` |
| **AI Bridge only** | `30-ai-bridge/00-overview.md`, `02-input-formats.md`, `03-startup-modes.md` |
| **Full backend** | `00-master-index.md`, `CONTEXT-FOR-AI.md`, `00-security-cross-cutting.md`, `plan.md` |

---

## 7. Startup Modes

AI Bridge and the full Nexus Flow application support two execution modes:

### Local Binary Mode

Single-execution mode for scripts, CI/CD, and one-off operations:

```bash
# Process a single prompt file
nexusflow run prompt.md

# Process with output file
nexusflow run prompt.json --output result.md

# Batch processing from CSV
nexusflow run data.csv --config data.config.yaml

# Streaming to stdout
nexusflow run prompt.md --stream

# Dry run (validate only)
nexusflow run prompt.md --dry-run
```

**Exit Codes:**
| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | General error |
| 2 | Input parsing error |
| 3 | Backend connection error |
| 4 | Request timeout |
| 5 | Invalid configuration |

### Background Daemon Mode

Long-running service with REST API and WebSocket:

```bash
# Start daemon (foreground)
nexusflow daemon start

# Start daemon (background)
nexusflow daemon start --detach

# Check status
nexusflow daemon status

# Stop daemon
nexusflow daemon stop

# View logs
nexusflow daemon logs --tail 100
```

**Default Port:** 8089

**API Endpoints:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/generate` | Synchronous generation |
| POST | `/api/v1/generate/stream` | SSE streaming |
| POST | `/api/v1/batch` | Batch processing |
| GET | `/api/v1/models` | List models |
| GET | `/health` | Health check |

### Hybrid Usage

Run both modes simultaneously:

```bash
# Start daemon
nexusflow daemon start --detach

# CLI uses running daemon
nexusflow run prompt.md --use-daemon
```

### Systemd Service

```ini
[Unit]
Description=Nexus Flow Daemon
After=network.target

[Service]
Type=simple
User=nexusflow
ExecStart=/usr/local/bin/nexusflow daemon start
ExecStop=/usr/local/bin/nexusflow daemon stop
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

---

*This guide ensures consistent AI handoffs with minimal confusion.*
