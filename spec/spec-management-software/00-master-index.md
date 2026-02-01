# Master Index

**Version:** 3.0.0  
**Status:** Active  
**Updated:** 2026-02-01  
**Total Files:** 400+

---

## Quick Navigation

| Section | Description | Files |
|---------|-------------|-------|
| [Ideas](#01-ideas) | Raw concepts and brainstorming | 8 |
| [Instructions](#02-instructions) | Refined action directives | 1 |
| [Project Overview](#03-project-overview) | Architecture and deployment | 2 |
| [Coding Guidelines](#04-coding-guidelines) | Standards and conventions | 3 |
| [Feature Specs](#05-features) | Detailed feature specifications | 200+ |
| [CLI Tools](#cli-tools) | Golang CLI utilities (gsearch, brun) | 39 |
| [External Tools](#15-external-tools) | Standalone spec integrations | 5 |
| [Code Generation](#24-code-generation-system) | AI code generation system | 34 |
| [Error Management](#06-error-management) | Error codes and handling | 5 |
| [Database Design](#07-database-design) | Schema and migrations | 7 |
| [Roadmap](#08-roadmap-overview) | Implementation timeline | 10 |
| [Diagrams](#09-diagrams) | Visual architecture flows | 10 |
| [Research](#10-research) | Technical investigations | 6 |
| [Prompts](#12-prompts) | AI prompt presets | 21 |
| [Microservices](#14-microservices) | Service specifications & OpenAPI | 21 |
| [API](#api) | OpenAPI specification & types | 2 |
| [Reports](#reports) | Quality and consistency tracking | 4 |
| [Changelog](#changelog) | Version history & changes | 1 |
| [Archives](#archives) | Historical audits & planning docs | 2 |

---

## CLI Tools Summary

Two Golang CLI utilities support the Spec Management application with specialized capabilities.

### Comparison Matrix

| Feature | gsearch | brun |
|---------|---------|------|
| **Purpose** | Multi-engine web search | Cross-platform build execution |
| **Binary** | `gsearch` | `brun` |
| **Module** | `github.com/user/gsearch` | `github.com/user/brun` |
| **Language** | Go 1.21+ | Go 1.21+ |
| **Framework** | Cobra + Viper | Cobra + Viper |
| **Database** | SQLite (GORM) | SQLite (GORM) |
| **Spec Files** | 19 documents | 16 documents |
| **Error Codes** | 6xxx range | 7xxx range (7100-7599) |
| **Health Score** | 100/100 (A+) | 100/100 (A+) |

### gsearch CLI

**Multi-engine web search with caching, RAG export, and observability.**

```bash
# Basic search
gsearch "golang concurrency patterns"

# Search with specific engine
gsearch --engine google "API design best practices"

# Export to RAG memory
gsearch --export-rag "machine learning fundamentals"
```

**Key Capabilities:**
- 🔍 **Multi-Engine Search**: Google, DuckDuckGo, Bing with weighted random switching
- 🔄 **Automated Failover**: Circuit breaker pattern with exponential backoff + jitter
- 📦 **Result Caching**: 5-6 day SQLite cache for deduplication
- 🔗 **Nested Search**: Recursive search patterns for deep exploration
- 📤 **RAG Export**: Memory export for AI context injection
- 📊 **Observability**: Prometheus metrics, OTEL tracing, structured JSON logging
- 🔐 **Security**: AES-256-GCM encrypted OAuth tokens
- 🌐 **Proxy Support**: Rotation with health monitoring

**Production Paths:**
- Database: `/var/lib/gsearch/`
- Config: `/etc/gsearch/`
- Logs: `/var/log/gsearch/`

### brun CLI

**Cross-platform build execution for Go, Node.js, and PowerShell with AI integration.**

```bash
# Run PowerShell script
brun -ps "scripts/build.ps1"

# Check Go build for errors
brun check --go ./cmd/app

# Run saved build profile
brun build --profile backend-api

# Check port availability with fallback
brun port --check 8080 --fallback 8081,8082
```

**Key Capabilities:**
- 🔨 **Multi-Runtime Execution**: PowerShell, Node.js (npm/yarn/bun), Golang
- 📋 **Build Profiles**: Saved configurations with asset management (clear/copy/override)
- 🔌 **Port Management**: Availability checking, fallback strategies, firewall rules
- 📝 **Error Capture**: JSON output, structured logging, stack trace capture
- 🤖 **AI Integration Loop**: Recursive check-fix-retry workflow with main application
- ❤️ **Health Checks**: HTTP monitoring for running applications
- 🎯 **AI Config Generation**: Natural language to JSON configuration

**Commands:**
| Command | Description |
|---------|-------------|
| `brun build` | Execute build profile |
| `brun check` | Check for errors without executing |
| `brun run` | Run application |
| `brun port` | Port management utilities |
| `brun config` | Configuration operations |

### Integration Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                    SPEC MANAGEMENT APPLICATION                    │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────┐              ┌─────────────────┐            │
│  │  Search Service │              │  Build Service  │            │
│  │  (Go backend)   │              │  (Go backend)   │            │
│  └────────┬────────┘              └────────┬────────┘            │
│           │                                │                      │
│           ▼                                ▼                      │
│  ┌─────────────────┐              ┌─────────────────┐            │
│  │     gsearch     │              │      brun       │            │
│  │   subprocess    │              │   subprocess    │            │
│  │   --json flag   │              │   --json flag   │            │
│  └────────┬────────┘              └────────┬────────┘            │
│           │                                │                      │
│           ▼                                ▼                      │
│  ┌─────────────────┐              ┌─────────────────┐            │
│  │  Search Results │              │  Build Errors   │            │
│  │  → RAG Context  │              │  → AI Fix Loop  │            │
│  └─────────────────┘              └─────────────────┘            │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

### Shared Patterns

Both CLIs follow consistent architectural patterns:

| Pattern | Implementation |
|---------|----------------|
| **CLI Framework** | Cobra for commands, Viper for configuration |
| **Persistence** | GORM with SQLite |
| **Configuration** | JSON config files with environment overrides |
| **Error Handling** | Structured error codes with HTTP status mapping |
| **Output Formats** | `--json` flag for programmatic consumption |
| **Testing** | 60/30/10 integration-heavy test pyramid |
| **Documentation** | Full spec suite with consistency reports |

### Quick Reference

| Task | gsearch | brun |
|------|---------|------|
| Search web | `gsearch "query"` | - |
| Build project | - | `brun build --profile name` |
| Check errors | - | `brun check --go ./path` |
| Port check | - | `brun port --check 8080` |
| JSON output | `--json` | `--json` |
| Config path | `--config path` | `--config path` |
| Help | `gsearch --help` | `brun --help` |

---

## 01-Ideas

Raw concepts, brainstorming notes, and voice transcripts.

| File | Description | Status |
|------|-------------|--------|
| [README](./01-ideas/README.md) | Ideas folder guide | Active |
| [00-readme-legacy](./01-ideas/00-readme-legacy.md) | Legacy documentation reference | Archive |
| [01-initial-idea](./01-ideas/01-initial-idea.md) | Original project concept | Complete |
| [02-qa-v2](./01-ideas/02-qa-v2.md) | Quality assurance brainstorm | Complete |
| [03-ai-and-ui-ideas](./01-ideas/03-ai-and-ui-ideas.md) | AI/UI feature concepts | Complete |
| [04-spec-update-plan](./01-ideas/04-spec-update-plan.md) | Specification update planning | Complete |
| [05-instruction-builder](./01-ideas/05-instruction-builder.md) | Instruction system concepts | Complete |
| [06-golang-search-cli](./01-ideas/06-golang-search-cli.md) | Golang CLI search tool concept | Draft |

---

## 02-Instructions

Refined directions promoted from ideas.

| File | Description | Status |
|------|-------------|--------|
| [README](./02-instructions/README.md) | Instructions folder guide | Active |

---

## 03-Project-Overview

High-level architecture and deployment documentation.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./03-project-overview/00-overview.md) | Project navigation and scope | Active |
| [02-deployment-architecture](./03-project-overview/02-deployment-architecture.md) | Infrastructure deployment guide | Planned |

---

## 04-Coding-Guidelines

Development standards and conventions.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./04-coding-guidelines/00-overview.md) | Guidelines index | Active |
| [01-helper-naming-guidelines](./04-coding-guidelines/01-helper-naming-guidelines.md) | Function/variable naming | Active |
| [02-configuration-manifest](./04-coding-guidelines/02-configuration-manifest.md) | Config key conventions | Active |

---

## 05-Features

Detailed feature specifications organized by domain.

### Core Features

#### 01-Authentication
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/01-authentication/00-overview.md) | Auth system overview | Planned |
| [01-authentication](./05-features/01-authentication/01-authentication.md) | JWT, sessions, brute-force protection | Planned |
| [02-frontend-security](./05-features/01-authentication/02-frontend-security.md) | CSRF, XSS prevention | Planned |

#### 02-File-Management
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/02-file-management/00-overview.md) | File operations overview | Planned |
| [01-file-operations](./05-features/02-file-management/01-file-operations.md) | CRUD operations for files | Planned |
| [02-path-manager](./05-features/02-file-management/02-path-manager.md) | Path resolution and validation | Planned |
| [03-folder-tree](./05-features/02-file-management/03-folder-tree.md) | Directory tree component | Planned |
| [04-folder-sync](./05-features/02-file-management/04-folder-sync.md) | Filesystem synchronization | Planned |

#### 03-Project-Management
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/03-project-management/00-overview.md) | Project operations overview | Planned |
| [01-import-export-system](./05-features/03-project-management/01-import-export-system.md) | Backup and restore functionality | Planned |
| [02-import-export-ui](./05-features/03-project-management/02-import-export-ui.md) | Import/export interface | Planned |

#### 04-Spec-Editor
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/04-spec-editor/00-overview.md) | Editor system overview | Planned |
| [01-markdown-editor](./05-features/04-spec-editor/01-markdown-editor.md) | Markdown editing with syntax highlighting | Planned |
| [02-preview-renderer](./05-features/04-spec-editor/02-preview-renderer.md) | Live preview rendering | Planned |
| [03-template-manager](./05-features/04-spec-editor/03-template-manager.md) | Spec templates and scaffolding | Planned |

#### 05-Voice-Input
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/05-voice-input/00-overview.md) | Voice capture overview | Planned |
| [01-voice-recorder](./05-features/05-voice-input/01-voice-recorder.md) | Audio recording component | Planned |
| [02-transcription-display](./05-features/05-voice-input/02-transcription-display.md) | Real-time transcription UI | Planned |
| [03-audio-player](./05-features/05-voice-input/03-audio-player.md) | Playback component | Planned |

### AI Integration

#### 06-AI-Integration
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/06-ai-integration/00-overview.md) | AI system overview | Planned |
| [01-ai-integration](./05-features/06-ai-integration/01-ai-integration.md) | LLM provider abstraction | Planned |
| [02-presets-guidelines](./05-features/06-ai-integration/02-presets-guidelines.md) | Prompt preset system | Active |
| [03-instruction-system](./05-features/06-ai-integration/03-instruction-system.md) | Instruction task workflow | Planned |
| [04-instruction-history](./05-features/06-ai-integration/04-instruction-history.md) | Task execution history | Planned |
| [05-instruction-segmentation](./05-features/06-ai-integration/05-instruction-segmentation.md) | Task chunking strategies | Planned |
| [06-llm-live-logging](./05-features/06-ai-integration/06-llm-live-logging.md) | Real-time LLM output streaming | Planned |
| [07-llm-server-management](./05-features/06-ai-integration/07-llm-server-management.md) | Server lifecycle management | Planned |
| [08-ai-chat-ui](./05-features/06-ai-integration/08-ai-chat-ui.md) | Chat interface components | Planned |
| [09-instruction-builder-ui](./05-features/06-ai-integration/09-instruction-builder-ui.md) | Instruction builder interface | Planned |
| [10-ai-prompt-panel](./05-features/06-ai-integration/10-ai-prompt-panel.md) | Prompt editing panel | Planned |
| [11-ai-testing](./05-features/06-ai-integration/11-ai-testing.md) | AI feature test strategies | Planned |

### History & Versioning

#### 07-History-System
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/07-history-system/00-overview.md) | History system overview | Planned |
| [01-git-integration](./05-features/07-history-system/01-git-integration.md) | Git-based version control | Planned |
| [02-history-system](./05-features/07-history-system/02-history-system.md) | Snapshot management | Planned |
| [03-history-ui](./05-features/07-history-system/03-history-ui.md) | Version browser UI | Planned |
| [04-file-history-comparison](./05-features/07-history-system/04-file-history-comparison.md) | Diff viewer component | Planned |

### Quality Assurance

#### 08-Consistency-Checker
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/08-consistency-checker/00-overview.md) | Consistency system overview | Planned |
| [01-consistency-checker](./05-features/08-consistency-checker/01-consistency-checker.md) | Validation rules engine | Planned |
| [02-consistency-checker-implementation](./05-features/08-consistency-checker/02-consistency-checker-implementation.md) | Backend implementation | Planned |
| [03-consistency-dashboard](./05-features/08-consistency-checker/03-consistency-dashboard.md) | Dashboard UI | Planned |

#### 09-Knowledge-Memory
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/09-knowledge-memory/00-overview.md) | RAG system overview | Planned |
| [01-rag-system](./05-features/09-knowledge-memory/01-rag-system.md) | Core RAG architecture | Planned |
| [02-rag-spec-guidelines](./05-features/09-knowledge-memory/02-rag-spec-guidelines.md) | RAG integration patterns | Planned |
| [03-rag-integration-plan](./05-features/09-knowledge-memory/03-rag-integration-plan.md) | Implementation roadmap | Planned |
| [04-vector-database-plan](./05-features/09-knowledge-memory/04-vector-database-plan.md) | Vector store selection | Planned |
| [05-vector-search-service](./05-features/09-knowledge-memory/05-vector-search-service.md) | Search service API | Planned |
| [06-context-window-manager](./05-features/09-knowledge-memory/06-context-window-manager.md) | Context optimization | Planned |
| [07-memory-compression](./05-features/09-knowledge-memory/07-memory-compression.md) | Memory efficiency | Planned |
| [08-vector-db-implementation-guide](./05-features/09-knowledge-memory/08-vector-db-implementation-guide.md) | Implementation details | Planned |
| [09-knowledge-memory-system](./05-features/09-knowledge-memory/09-knowledge-memory-system.md) | System architecture | Planned |
| [10-knowledge-worker-binary](./05-features/09-knowledge-memory/10-knowledge-worker-binary.md) | Worker process specs | Planned |
| [11-knowledge-memory-ui](./05-features/09-knowledge-memory/11-knowledge-memory-ui.md) | Memory browser UI | Planned |

### UI Infrastructure

#### 10-Theme-System
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/10-theme-system/00-overview.md) | Theme system overview | Planned |
| [01-theme-provider](./05-features/10-theme-system/01-theme-provider.md) | Theme context and switching | Planned |
| [02-component-library](./05-features/10-theme-system/02-component-library.md) | Reusable UI components | Planned |

#### 11-Dashboard
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/11-dashboard/00-overview.md) | Dashboard overview | Planned |
| [01-project-dashboard](./05-features/11-dashboard/01-project-dashboard.md) | Main project view | Planned |

#### 12-Routing-Navigation
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/12-routing-navigation/00-overview.md) | Routing overview | Planned |
| [01-route-definitions](./05-features/12-routing-navigation/01-route-definitions.md) | Route constants | Planned |
| [02-route-config](./05-features/12-routing-navigation/02-route-config.md) | Router configuration | Planned |

#### 13-Error-UI
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/13-error-ui/00-overview.md) | Error UI overview | Planned |
| [01-error-components](./05-features/13-error-ui/01-error-components.md) | Error display components | Planned |

#### 14-Mobile-Responsive
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/14-mobile-responsive/00-overview.md) | Responsive design overview | Planned |
| [01-responsive-layouts](./05-features/14-mobile-responsive/01-responsive-layouts.md) | Breakpoint strategies | Planned |

### Technical Infrastructure

#### 15-API-Client
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/15-api-client/00-overview.md) | API client overview | Planned |
| [01-http-client](./05-features/15-api-client/01-http-client.md) | Axios configuration | Planned |
| [02-api-contracts](./05-features/15-api-client/02-api-contracts.md) | API contract definitions | Active |

#### 16-State-Management
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/16-state-management/00-overview.md) | State management overview | Planned |
| [01-state-architecture](./05-features/16-state-management/01-state-architecture.md) | React Query + Context patterns | Planned |

#### 17-Monitoring
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/17-monitoring/00-overview.md) | Monitoring overview | Planned |
| [01-system-monitoring](./05-features/17-monitoring/01-system-monitoring.md) | Metrics and logging | Planned |

#### 18-Realtime
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/18-realtime/00-overview.md) | Realtime overview | Planned |
| [01-websocket-integration](./05-features/18-realtime/01-websocket-integration.md) | WebSocket/SSE implementation | Planned |

#### 19-Performance
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/19-performance/00-overview.md) | Performance overview | Planned |
| [01-optimization-strategies](./05-features/19-performance/01-optimization-strategies.md) | Optimization techniques | Planned |

#### 20-Testing
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/20-testing/00-overview.md) | Testing overview | Planned |
| [01-test-strategy](./05-features/20-testing/01-test-strategy.md) | Test pyramid and coverage | Planned |

#### 21-i18n
| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/21-i18n/00-overview.md) | i18n overview | Planned |
| [01-internationalization](./05-features/21-i18n/01-internationalization.md) | Localization strategy | Planned |

### CLI Tools

#### 22-golang-search-cli (gsearch)

Multi-engine web search CLI with caching, RAG export, and observability.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/22-golang-search-cli/00-overview.md) | CLI overview and architecture | Active |
| [01-cli-framework](./05-features/22-golang-search-cli/01-cli-framework.md) | Cobra/Viper CLI structure | Active |
| [02-configuration](./05-features/22-golang-search-cli/02-configuration.md) | JSON config and environment | Active |
| [03-database-schema](./05-features/22-golang-search-cli/03-database-schema.md) | GORM/SQLite persistence | Active |
| [04-html-parser](./05-features/22-golang-search-cli/04-html-parser.md) | goquery HTML extraction | Active |
| [05-google-api](./05-features/22-golang-search-cli/05-google-api.md) | Google search integration | Active |
| [06-duckduckgo](./05-features/22-golang-search-cli/06-duckduckgo.md) | DuckDuckGo search | Active |
| [07-bing-search](./05-features/22-golang-search-cli/07-bing-search.md) | Bing search integration | Active |
| [08-method-switching](./05-features/22-golang-search-cli/08-method-switching.md) | Weighted random engine selection | Active |
| [09-nested-search](./05-features/22-golang-search-cli/09-nested-search.md) | Recursive search patterns | Active |
| [10-caching-system](./05-features/22-golang-search-cli/10-caching-system.md) | 5-6 day result caching | Active |
| [11-rag-export](./05-features/22-golang-search-cli/11-rag-export.md) | RAG memory export | Active |
| [12-testing-strategy](./05-features/22-golang-search-cli/12-testing-strategy.md) | Integration test patterns | Active |
| [13-implementation-guide](./05-features/22-golang-search-cli/13-implementation-guide.md) | Build order and dependencies | Active |
| [14-remediation-plan](./05-features/22-golang-search-cli/14-remediation-plan.md) | Quality improvement plan | Complete |
| [15-error-codes](./05-features/22-golang-search-cli/15-error-codes.md) | Error code registry | Active |
| [16-observability](./05-features/22-golang-search-cli/16-observability.md) | Prometheus, OTEL, logging | Active |
| [17-deployment-guide](./05-features/22-golang-search-cli/17-deployment-guide.md) | Cross-platform distribution | Active |
| [18-full-site-crawler](./05-features/22-golang-search-cli/18-full-site-crawler.md) | Full website crawling | Active |
| [99-remediation-summary](./05-features/22-golang-search-cli/99-remediation-summary.md) | Quality journey summary | Complete |
| [99-consistency-report](./05-features/22-golang-search-cli/99-consistency-report.md) | Cross-reference validation | Active |

#### 23-build-runner-cli (brun)

Cross-platform build execution CLI for Go, Node.js, and PowerShell with AI integration.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/23-build-runner-cli/00-overview.md) | CLI overview and features | Active |
| [01-core-architecture](./05-features/23-build-runner-cli/01-core-architecture.md) | System design and components | Active |
| [02-cli-interface](./05-features/23-build-runner-cli/02-cli-interface.md) | Commands and parameters | Active |
| [03-configuration](./05-features/23-build-runner-cli/03-configuration.md) | config.json schema | Active |
| [04-runtime-executors](./05-features/23-build-runner-cli/04-runtime-executors.md) | PowerShell, Node.js, Go runners | Active |
| [05-port-management](./05-features/23-build-runner-cli/05-port-management.md) | Port checking and firewall | Active |
| [06-error-handling](./05-features/23-build-runner-cli/06-error-handling.md) | Error capture and JSON output | Active |
| [07-build-profiles](./05-features/23-build-runner-cli/07-build-profiles.md) | Saved build configurations | Active |
| [08-asset-operations](./05-features/23-build-runner-cli/08-asset-operations.md) | File copy and override modes | Active |
| [09-integration-api](./05-features/23-build-runner-cli/09-integration-api.md) | Subprocess communication | Active |
| [10-data-models](./05-features/23-build-runner-cli/10-data-models.md) | GORM entities and schemas | Active |
| [11-acceptance-criteria](./05-features/23-build-runner-cli/11-acceptance-criteria.md) | Validation requirements | Active |
| [12-ai-config-generation](./05-features/23-build-runner-cli/12-ai-config-generation.md) | AI-assisted config creation | Active |
| [13-testing-strategy](./05-features/23-build-runner-cli/13-testing-strategy.md) | Integration tests and CI/CD | Active |
| [14-implementation-guide](./05-features/23-build-runner-cli/14-implementation-guide.md) | Build order and dependencies | Active |
| [15-observability](./05-features/23-build-runner-cli/15-observability.md) | Prometheus, health, logging | Active |
| [16-deployment-guide](./05-features/23-build-runner-cli/16-deployment-guide.md) | Cross-platform installation | Active |
| [99-consistency-report](./05-features/23-build-runner-cli/99-consistency-report.md) | Cross-reference validation | Active |

#### 24-code-generation-system

AI-powered code generation from specifications with parallel execution, Git integration, and build verification.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/24-code-generation-system/00-overview.md) | System overview and features | Active |
| [01-architecture](./05-features/24-code-generation-system/01-architecture.md) | Component design and data flow | Active |
| [02-guideline-hierarchy](./05-features/24-code-generation-system/02-guideline-hierarchy.md) | 4-layer merge/extend system | Active |
| [03-parallel-code-generation](./05-features/24-code-generation-system/03-parallel-code-generation.md) | Three-phase workflow | Active |
| [04-plan-generator](./05-features/24-code-generation-system/04-plan-generator.md) | Topological sort & batching | Active |
| [05-parallel-executor](./05-features/24-code-generation-system/05-parallel-executor.md) | Worker pools & concurrent execution | Active |
| [06-build-verification](./05-features/24-code-generation-system/06-build-verification.md) | brun CLI integration, AI fix loop | Active |
| [07-git-integration](./05-features/24-code-generation-system/07-git-integration.md) | Local/remote repository + OAuth | Active |
| [08-configuration](./05-features/24-code-generation-system/08-configuration.md) | System configuration | Active |
| [09-credit-system](./05-features/24-code-generation-system/09-credit-system.md) | Per-request token tracking | Active |
| [10-repository-structure](./05-features/24-code-generation-system/10-repository-structure.md) | Generated repo layout (spec/BE/FE) | Active |
| [11-coding-model-presets](./05-features/24-code-generation-system/11-coding-model-presets.md) | Model presets and prompts | Active |
| [12-spec-folder-guideline](./05-features/24-code-generation-system/12-spec-folder-guideline.md) | AI training guide for folder structure | Active |
| [13-api-endpoints](./05-features/24-code-generation-system/13-api-endpoints.md) | REST API specification | Active |
| [14-data-models](./05-features/24-code-generation-system/14-data-models.md) | GORM entities (21 tables) | Active |
| [15-websocket-events](./05-features/24-code-generation-system/15-websocket-events.md) | Real-time progress streaming | Active |
| [16-error-codes](./05-features/24-code-generation-system/16-error-codes.md) | Error codes (12xxx range) | Active |
| [17-testing-strategy](./05-features/24-code-generation-system/17-testing-strategy.md) | Testing approach | Active |
| [18-deployment-guide](./05-features/24-code-generation-system/18-deployment-guide.md) | Deployment procedures | Active |
| [19-implementation-guide](./05-features/24-code-generation-system/19-implementation-guide.md) | 8-phase, 20-day roadmap | Active |
| [20-project-editor-ui](./05-features/24-code-generation-system/20-project-editor-ui.md) | Editor UI specification | Active |
| [21-suggestions-system](./05-features/24-code-generation-system/21-suggestions-system.md) | Code suggestions | Active |
| [22-loop-validation](./05-features/24-code-generation-system/22-loop-validation.md) | Validation loops | Active |
| [23-settings-system](./05-features/24-code-generation-system/23-settings-system.md) | Settings UI | Active |
| [24-error-handling-system](./05-features/24-code-generation-system/24-error-handling-system.md) | Error handling | Active |
| [25-ai-chat-interface](./05-features/24-code-generation-system/25-ai-chat-interface.md) | Chat interface | Active |
| [26-questioning-system](./05-features/24-code-generation-system/26-questioning-system.md) | Clarification questions | Active |
| [27-suggestions-ui](./05-features/24-code-generation-system/27-suggestions-ui.md) | Suggestions UI | Active |
| [28-file-modification-display](./05-features/24-code-generation-system/28-file-modification-display.md) | File change display | Active |
| [29-long-chain-events](./05-features/24-code-generation-system/29-long-chain-events.md) | Long-chain event handling | Active |
| [30-search-integration](./05-features/24-code-generation-system/30-search-integration.md) | Search integration | Active |
| [31-chat-history-branching](./05-features/24-code-generation-system/31-chat-history-branching.md) | Chat branching | Active |
| [32-url-context-system](./05-features/24-code-generation-system/32-url-context-system.md) | URL context | Active |
| [99-consistency-report](./05-features/24-code-generation-system/99-consistency-report.md) | Document health tracking | Draft |

#### 25-ai-enhancements

AI-powered enhancement features including offline storage, voice resilience, plan mode, and cross-project memory.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/25-ai-enhancements/00-overview.md) | AI enhancements overview | Active |
| [01-offline-first-storage](./05-features/25-ai-enhancements/01-offline-first-storage.md) | Offline-first architecture | Active |
| [01-01-versioned-storage](./05-features/25-ai-enhancements/01-01-versioned-storage.md) | Versioned storage system | Active |
| [01-02-sync-queue](./05-features/25-ai-enhancements/01-02-sync-queue.md) | Sync queue management | Active |
| [01-03-storage-hooks](./05-features/25-ai-enhancements/01-03-storage-hooks.md) | React storage hooks | Active |
| [01-04-sync-api](./05-features/25-ai-enhancements/01-04-sync-api.md) | Sync API specification | Active |
| [02-voice-resilience](./05-features/25-ai-enhancements/02-voice-resilience.md) | Voice input resilience | Active |
| [02-01-audio-capture](./05-features/25-ai-enhancements/02-01-audio-capture.md) | Audio capture system | Active |
| [02-02-transcription-service](./05-features/25-ai-enhancements/02-02-transcription-service.md) | Transcription service | Active |
| [02-03-audio-sync](./05-features/25-ai-enhancements/02-03-audio-sync.md) | Audio synchronization | Active |
| [02-04-voice-ui-components](./05-features/25-ai-enhancements/02-04-voice-ui-components.md) | Voice UI components | Active |
| [03-plan-mode](./05-features/25-ai-enhancements/03-plan-mode.md) | AI plan mode | Active |
| [03-01-plan-generation](./05-features/25-ai-enhancements/03-01-plan-generation.md) | Plan generation | Active |
| [03-02-plan-execution](./05-features/25-ai-enhancements/03-02-plan-execution.md) | Plan execution | Active |
| [03-03-approval-workflow](./05-features/25-ai-enhancements/03-03-approval-workflow.md) | Approval workflow | Active |
| [03-04-mermaid-integration](./05-features/25-ai-enhancements/03-04-mermaid-integration.md) | Mermaid integration | Active |
| [04-mermaid-diagrams](./05-features/25-ai-enhancements/04-mermaid-diagrams.md) | Mermaid diagrams | Active |
| [04-01-model-categorization](./05-features/25-ai-enhancements/04-01-model-categorization.md) | Model categorization | Active |
| [04-02-diagram-prompts](./05-features/25-ai-enhancements/04-02-diagram-prompts.md) | Diagram prompts | Active |
| [04-03-diagram-service](./05-features/25-ai-enhancements/04-03-diagram-service.md) | Diagram service | Active |
| [04-04-diagram-ui](./05-features/25-ai-enhancements/04-04-diagram-ui.md) | Diagram UI | Active |
| [05-chat-ui-redesign](./05-features/25-ai-enhancements/05-chat-ui-redesign.md) | Chat UI redesign | Active |
| [05-01-chat-layout](./05-features/25-ai-enhancements/05-01-chat-layout.md) | Chat layout | Active |
| [05-02-chat-input](./05-features/25-ai-enhancements/05-02-chat-input.md) | Chat input | Active |
| [05-03-message-display](./05-features/25-ai-enhancements/05-03-message-display.md) | Message display | Active |
| [05-04-mode-selector](./05-features/25-ai-enhancements/05-04-mode-selector.md) | Mode selector | Active |
| [06-cross-project-memory](./05-features/25-ai-enhancements/06-cross-project-memory.md) | Cross-project memory | Active |
| [06-01-sharing-architecture](./05-features/25-ai-enhancements/06-01-sharing-architecture.md) | Sharing architecture | Active |
| [06-02-sync-mechanism](./05-features/25-ai-enhancements/06-02-sync-mechanism.md) | Sync mechanism | Active |
| [06-03-rag-integration](./05-features/25-ai-enhancements/06-03-rag-integration.md) | RAG integration | Active |
| [06-04-sharing-ui](./05-features/25-ai-enhancements/06-04-sharing-ui.md) | Sharing UI | Active |
| [98-test-plan](./05-features/25-ai-enhancements/98-test-plan.md) | Test plan | Active |
| [99-consistency-report](./05-features/25-ai-enhancements/99-consistency-report.md) | Consistency report | Active |

#### 27-automation-pipeline

Agentic automation pipeline for complex, multi-stage AI workflows with node-based React Flow canvas, fault tolerance, and real-time monitoring.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/27-automation-pipeline/00-overview.md) | Pipeline system overview | Active |
| [01-database-schema](./05-features/27-automation-pipeline/01-database-schema.md) | SQLite schema definitions | Active |
| [02-prompt-import-system](./05-features/27-automation-pipeline/02-prompt-import-system.md) | ZIP/Markdown ingestion | Active |
| [03-variable-registry](./05-features/27-automation-pipeline/03-variable-registry.md) | Scoped templating system | Active |
| [04-stage-executor](./05-features/27-automation-pipeline/04-stage-executor.md) | Stage execution engine | Active |
| [05-validation-runtime](./05-features/27-automation-pipeline/05-validation-runtime.md) | Script execution runtime | Active |
| [06-io-binding](./05-features/27-automation-pipeline/06-io-binding.md) | Input/output bindings | Active |
| [07-execution-blocks](./05-features/27-automation-pipeline/07-execution-blocks.md) | Block execution model | Active |
| [08-parallel-control](./05-features/27-automation-pipeline/08-parallel-control.md) | Parallel execution control | Active |
| [09-block-chaining](./05-features/27-automation-pipeline/09-block-chaining.md) | Block dependency chains | Active |
| [10-react-flow-canvas](./05-features/27-automation-pipeline/10-react-flow-canvas.md) | Node-based UI | Active |
| [11-stage-nodes](./05-features/27-automation-pipeline/11-stage-nodes.md) | Stage node components | Active |
| [12-connection-wiring](./05-features/27-automation-pipeline/12-connection-wiring.md) | Node connection logic | Active |
| [13-conditional-nodes](./05-features/27-automation-pipeline/13-conditional-nodes.md) | If-Else, Switch, Guard, Gate | Active |
| [14-loop-constructs](./05-features/27-automation-pipeline/14-loop-constructs.md) | For-Each, While patterns | Active |
| [15-error-handlers](./05-features/27-automation-pipeline/15-error-handlers.md) | Try-Catch, Retry, Circuit Breaker | Active |
| [16-live-execution-view](./05-features/27-automation-pipeline/16-live-execution-view.md) | Real-time execution monitoring | Active |
| [17-debug-inspector](./05-features/27-automation-pipeline/17-debug-inspector.md) | Breakpoints and variable trees | Active |
| [18-telemetry-integration](./05-features/27-automation-pipeline/18-telemetry-integration.md) | Distributed tracing | Active |
| [19-pipeline-templates](./05-features/27-automation-pipeline/19-pipeline-templates.md) | Reusable pipeline patterns | Active |
| [20-import-export](./05-features/27-automation-pipeline/20-import-export.md) | ZIP-based portability | Active |
| [21-version-control](./05-features/27-automation-pipeline/21-version-control.md) | Git-inspired versioning | Active |
| [22-permissions](./05-features/27-automation-pipeline/22-permissions.md) | RBAC access control | Active |
| [23-sharing](./05-features/27-automation-pipeline/23-sharing.md) | Invitation and link sharing | Active |
| [24-collaboration](./05-features/27-automation-pipeline/24-collaboration.md) | Real-time multi-user editing | Active |
| [25-permissions-tests](./05-features/27-automation-pipeline/25-permissions-tests.md) | E2E: Permission tests | Active |
| [26-sharing-tests](./05-features/27-automation-pipeline/26-sharing-tests.md) | E2E: Sharing tests | Active |
| [27-collaboration-tests](./05-features/27-automation-pipeline/27-collaboration-tests.md) | E2E: Collaboration tests | Active |
| [28-res-integration](./05-features/27-automation-pipeline/28-res-integration.md) | Resilient Execution System | Active |
| [29-prompt-import-tests](./05-features/27-automation-pipeline/29-prompt-import-tests.md) | E2E: Prompt import (12 cases) | Active |
| [30-pipeline-creation-tests](./05-features/27-automation-pipeline/30-pipeline-creation-tests.md) | E2E: Pipeline creation (18 cases) | Active |
| [31-variable-resolution-tests](./05-features/27-automation-pipeline/31-variable-resolution-tests.md) | E2E: Variable resolution (16 cases) | Active |
| [32-stage-execution-tests](./05-features/27-automation-pipeline/32-stage-execution-tests.md) | E2E: Stage execution (20 cases) | Active |
| [33-branching-tests](./05-features/27-automation-pipeline/33-branching-tests.md) | E2E: Branching logic (18 cases) | Active |
| [34-parallel-execution-tests](./05-features/27-automation-pipeline/34-parallel-execution-tests.md) | E2E: Parallel execution (16 cases) | Active |
| [99-architecture-diagram](./05-features/27-automation-pipeline/99-architecture-diagram.md) | Architecture visualization | Active |

---

#### 26-AI-Code-Generation

On-the-fly Golang code generation for complex AI-driven file operations with approval workflow, reusability, and complete history tracking.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/26-ai-code-generation/00-overview.md) | AI code generation overview | Active |
| [01-system-overview](./05-features/26-ai-code-generation/01-system-overview.md) | Architecture and philosophy | Active |
| [02-complexity-decision](./05-features/26-ai-code-generation/02-complexity-decision.md) | Task complexity scoring | Active |
| [03-code-generator](./05-features/26-ai-code-generation/03-code-generator.md) | LLM code generation engine | Active |
| 04-code-templates | Standard Golang templates | Planned |
| 05-task-matcher | Reusability matching | Planned |
| 06-execution-engine | Compilation and execution | Planned |
| 07-approval-workflow | User approval before execution | Planned |
| 08-history-logger | Filesystem operation tracking | Planned |
| 09-multi-model-executor | Parallel model execution | Planned |
| 10-agentic-search | Hybrid search for context | Planned |
| [11-database-schema](./05-features/26-ai-code-generation/11-database-schema.md) | SQLite tables (TempCodingTasks) | Active |
| 12-tag-system | Tag taxonomy for reuse | Planned |
| 13-code-review-ui | Code preview and approval UI | Planned |
| 14-execution-monitor | Real-time execution status | Planned |
| 15-history-browser | Task and operation history | Planned |
| [99-consistency-report](./05-features/26-ai-code-generation/99-consistency-report.md) | Consistency report | Active |

#### 28-Project-Editor

Project editor infrastructure with input state persistence, draft recovery, cross-device sync, and editor state management.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/28-project-editor/00-overview.md) | Project editor overview | Active |
| [01-draft-recovery-ui](./05-features/28-project-editor/01-draft-recovery-ui.md) | Recovery banner and dialog components | Active |
| [02-sync-api](./05-features/28-project-editor/02-sync-api.md) | Cross-device sync API | Active |
| [03-editor-state-hooks](./05-features/28-project-editor/03-editor-state-hooks.md) | Cursor, scroll, undo/redo hooks | Active |
| [04-integration-tests](./05-features/28-project-editor/04-integration-tests.md) | E2E test suite (40 cases) | Active |
| [05-error-codes](./05-features/28-project-editor/05-error-codes.md) | Error codes (13xxx range) | Active |
| [06-input-state-persistence](./05-features/28-project-editor/06-input-state-persistence.md) | localStorage/IndexedDB persistence | Active |

#### 29-Trigger-Event-System

Unified event-driven architecture combining file system events, user actions, AI operations, and pipeline executions into a single observable event bus with reactive workflows and audit logging.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/29-trigger-event-system/00-overview.md) | Trigger event system overview | Active |
| 01-event-types | Event taxonomy and payload schemas | Planned |
| 02-event-bus | Pub/sub engine with routing | Planned |
| 03-event-store | Persistence and replay | Planned |
| 04-database-schema | Event, Subscription, AuditLog tables | Planned |
| 05-file-events | File CRUD triggers | Planned |
| 06-user-events | Auth and action tracking | Planned |
| 07-ai-events | AI completion and streaming events | Planned |
| 08-pipeline-events | Stage and block transitions | Planned |
| 09-system-events | Health, config, scheduled tasks | Planned |
| 10-rag-handler | Auto-reindex on file changes | Planned |
| 11-ui-handler | Real-time notifications | Planned |
| 12-webhook-handler | External HTTP callbacks | Planned |
| 13-audit-handler | Compliance logging | Planned |
| 14-pipeline-trigger-handler | Event-driven pipeline execution | Planned |
| 15-trigger-rules | Condition expressions and filters | Planned |
| 16-trigger-ui | Visual trigger builder | Planned |
| 17-event-debugger | Live event inspector | Planned |
| 18-event-integration-tests | E2E event flow validation | Planned |

#### 30-AI-Bridge

External AI adapter for normalizing inputs from Markdown, JSON, YAML, and CSV formats, routing to LLM providers via a unified BackendAdapter interface. Supports both local binary and background daemon execution modes.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./05-features/30-ai-bridge/00-overview.md) | Architecture overview | Active |
| [01-architecture](./05-features/30-ai-bridge/01-architecture.md) | BackendAdapter interface, NormalizedRequest | Active |
| [02-input-formats](./05-features/30-ai-bridge/02-input-formats.md) | Markdown, JSON, YAML, CSV parsers | Active |
| [03-startup-modes](./05-features/30-ai-bridge/03-startup-modes.md) | Local Binary + Daemon modes | Active |
| [04-api-interface](./05-features/30-ai-bridge/04-api-interface.md) | REST API for daemon mode | Active |
| [05-error-codes](./05-features/30-ai-bridge/05-error-codes.md) | Error codes (9000-9499 range) | Active |
| [06-configuration](./05-features/30-ai-bridge/06-configuration.md) | Configuration schema | Active |
| [99-consistency-report](./05-features/30-ai-bridge/99-consistency-report.md) | Cross-reference validation | Active |

---

## 15-External-Tools

Standalone specifications extracted for independent development and AI training.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./15-external-tools/00-overview.md) | Integration hub overview | Active |
| [01-gsearch-reference](./15-external-tools/01-gsearch-reference.md) | GSearch CLI integration | Active |
| [02-ai-bridge-reference](./15-external-tools/02-ai-bridge-reference.md) | AI Bridge integration | Active |
| [03-nexus-flow-reference](./15-external-tools/03-nexus-flow-reference.md) | Nexus Flow integration | Active |
| [04-brun-reference](./15-external-tools/04-brun-reference.md) | BRun CLI integration | Active |
| [99-consistency-report](./15-external-tools/99-consistency-report.md) | Cross-reference validation | Active |

### Standalone Specifications

| Spec | Location | Files | Error Range |
|------|----------|-------|-------------|
| GSearch CLI | `spec/gsearch-cli/` | 27 | 7000-7999 |
| AI Bridge | `spec/ai-bridge/` | 8 | 9000-9999 |
| Nexus Flow | `spec/nexus-flow/` | 7 | 8000-8399 |
| BRun CLI | `spec/brun-cli/` | 18 | 7100-7599 |

---

## 06-Error-Management

Unified error handling across frontend and backend.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./06-error-management/00-overview.md) | Error management overview | Active |
| [error-code-registry](./06-error-management/error-code-registry.md) | Master error code list | Active |

### Backend Errors
| File | Description | Status |
|------|-------------|--------|
| [01-error-codes](./06-error-management/backend/01-error-codes.md) | Go error definitions | Active |

### Frontend Errors
| File | Description | Status |
|------|-------------|--------|
| [01-error-codes](./06-error-management/frontend/01-error-codes.md) | React error handling | Active |

### Shared Constants
| File | Description | Status |
|------|-------------|--------|
| [01-error-constants](./06-error-management/shared/01-error-constants.md) | Cross-platform constants | Active |

---

## 14-Microservices

Microservices architecture specifications and OpenAPI documentation for all backend services.

### Service Registry

| Service | Port | Spec | Error Range |
|---------|------|------|-------------|
| Gateway | 8080 | [01-gateway.md](./14-microservices/01-gateway.md) | 2xxx |
| SpecManager | 8081 | [02-specmanager.md](./14-microservices/02-specmanager.md) | 3xxx |
| AI-Bridge | 8082 | [04-ai-bridge.md](./14-microservices/04-ai-bridge.md) | 6xxx |
| Chronicle | 8083 | [03-chronicle.md](./14-microservices/03-chronicle.md) | 4xxx |
| Voice-CLI | 8084 | [10-voice-cli.md](./14-microservices/10-voice-cli.md) | 11xxx |
| Scout | 8093 | [05-scout.md](./14-microservices/05-scout.md) | 5xxx |
| Nexus-Flow | 9000 | [06-nexus-flow.md](./14-microservices/06-nexus-flow.md) | 10xxx |

### OpenAPI Specifications

Complete REST API and WebSocket protocol documentation for each service.

| Service | Port | OpenAPI Spec | Protocol |
|---------|------|--------------|----------|
| Gateway | 8080 | [07-gateway-openapi.md](./14-microservices/07-gateway-openapi.md) | REST |
| SpecManager | 8081 | [20-specmanager-openapi.md](./14-microservices/20-specmanager-openapi.md) | REST |
| AI-Bridge | 8082 | [13-ai-bridge-openapi.md](./14-microservices/13-ai-bridge-openapi.md) | REST + SSE |
| Chronicle | 8083 | [19-chronicle-openapi.md](./14-microservices/19-chronicle-openapi.md) | REST |
| Voice-CLI | 8084 | [16-voice-cli-openapi.md](./14-microservices/16-voice-cli-openapi.md) | REST + WebSocket |
| Scout | 8093 | [18-scout-openapi.md](./14-microservices/18-scout-openapi.md) | REST |
| Nexus-Flow | 9000 | [17-nexus-flow-openapi.md](./14-microservices/17-nexus-flow-openapi.md) | REST + WebSocket |

### Supporting Documents

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./14-microservices/00-overview.md) | Architecture overview | Active |
| [02-shared-pkg-modules](./14-microservices/02-shared-pkg-modules.md) | Shared Go packages | Active |
| [06-database-migrations](./14-microservices/06-database-migrations.md) | Migration strategy | Active |
| [09-nexus-flow-standalone-architecture](./14-microservices/09-nexus-flow-standalone-architecture.md) | Standalone deployment | Active |
| [12-ai-bridge-cli](./14-microservices/12-ai-bridge-cli.md) | AI-Bridge CLI interface | Active |
| [21-integration-tests](./14-microservices/21-integration-tests.md) | E2E microservice tests (70+ cases) | Active |
| [99-consistency-report](./14-microservices/99-consistency-report.md) | Health report | Active |

---

## 07-Database-Design

SQLite schema and data architecture.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./07-database-design/00-overview.md) | Database design overview | Active |
| [01-schema](./07-database-design/01-schema.md) | Core table definitions | Active |
| [02a-migrations](./07-database-design/02a-migrations.md) | Migration strategy | Active |
| [02b-unified-schema](./07-database-design/02b-unified-schema.md) | Consolidated schema DDL | Active |
| [03a-relationships](./07-database-design/03a-relationships.md) | Foreign key relationships | Active |
| [03b-seed-data](./07-database-design/03b-seed-data.md) | Initial data population | Active |
| [04-conventions](./07-database-design/04-conventions.md) | Naming and type conventions | Active |

---

## 08-Roadmap-Overview

Implementation timeline and phases.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./08-roadmap-overview/00-overview.md) | Roadmap index | Active |
| [01-roadmap](./08-roadmap-overview/01-roadmap.md) | Phase timeline | Active |
| [02a-implementation-order-guide](./08-roadmap-overview/02a-implementation-order-guide.md) | Dependency ordering | Active |
| [02b-summary](./08-roadmap-overview/02b-summary.md) | Executive summary | Active |
| [03-glossary](./08-roadmap-overview/03-glossary.md) | Technical term definitions | Active |
| [04-implementation-guidelines](./08-roadmap-overview/04-implementation-guidelines.md) | Coding patterns | Active |
| [05-gap-analysis](./08-roadmap-overview/05-gap-analysis.md) | Missing feature analysis | Active |
| [06-testing-deployment](./08-roadmap-overview/06-testing-deployment.md) | CI/CD pipeline specs | Planned |
| [07-integration-tests-pipeline](./08-roadmap-overview/07-integration-tests-pipeline.md) | E2E test configuration | Planned |
| [08-config-validator-tests](./08-roadmap-overview/08-config-validator-tests.md) | Config validation tests | Planned |

---

## 09-Diagrams

Visual architecture and workflow documentation.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./09-diagrams/00-overview.md) | Diagrams index | Active |
| [00-system-architecture-overview](./09-diagrams/00-system-architecture-overview.md) | Master system diagram | Active |
| [01-idea-promotion-workflow](./09-diagrams/01-idea-promotion-workflow.md) | Voice → Idea → Instruction | Active |
| [02-rag-retrieval-flow](./09-diagrams/02-rag-retrieval-flow.md) | Query → Context injection | Active |
| [03-instruction-builder-pipeline](./09-diagrams/03-instruction-builder-pipeline.md) | Voice → Spec generation | Active |
| [04-prompt-preset-layering](./09-diagrams/04-prompt-preset-layering.md) | Base → Override → Final | Active |
| [05-inconsistency-clarification-workflow](./09-diagrams/05-inconsistency-clarification-workflow.md) | Detect → Question → Regenerate | Active |
| [06-feature-dependency-graph](./09-diagrams/06-feature-dependency-graph.md) | Feature relationships & order | Active |
| [07-master-architecture-diagram](./09-diagrams/07-master-architecture-diagram.md) | Microservices, ports, databases, flows | Active |

---

## 10-Research

Technical investigations and exploration.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./10-research/00-overview.md) | Research index | Active |
| [01a-e2e-integration-tests](./10-research/01a-e2e-integration-tests.md) | E2E testing approaches | Complete |
| [01b-llm-server-multi-model](./10-research/01b-llm-server-multi-model.md) | Multi-model support research | Complete |
| [02-llm-server-config-update-plan](./10-research/02-llm-server-config-update-plan.md) | Config update strategy | Complete |
| [03-model-category-inconsistency-report](./10-research/03-model-category-inconsistency-report.md) | Model categorization analysis | Complete |
| [04-import-export-global-visibility-report](./10-research/04-import-export-global-visibility-report.md) | Import/export scope analysis | Complete |

---

## 12-Prompts

AI prompt presets organized by category.

| Folder | Description | Files |
|--------|-------------|-------|
| [00-overview](./12-prompts/00-overview.md) | Prompts index | 1 |
| [01-coding-guideline](./12-prompts/01-coding-guideline/00-overview.md) | Language-specific standards | 4 |
| [02-feature](./12-prompts/02-feature/00-overview.md) | Feature specification | 4 |
| [03-idea](./12-prompts/03-idea/00-overview.md) | Idea capture | 4 |
| [04-instruction](./12-prompts/04-instruction/00-overview.md) | Instruction generation | 4 |
| [05-task](./12-prompts/05-task/00-overview.md) | Task execution | 4 |

---

## API

OpenAPI specification and TypeScript types for code generation.

| File | Description | Status |
|------|-------------|--------|
| [openapi.yaml](./api/openapi.yaml) | OpenAPI 3.0 specification | Active |
| [types.ts](./api/types.ts) | TypeScript API client types (918 lines) | Active |

---

## Reports

Quality tracking and consistency reports.

| File | Description | Status |
|------|-------------|--------|
| [00-overview](./00-overview.md) | Root project overview | Active |
| [99-consistency-report](./99-consistency-report.md) | Spec health dashboard | Active |
| [99-cross-reference-validation-report](./99-cross-reference-validation-report.md) | Link validation | Active |
| [99-quality-improvement-plan](./99-quality-improvement-plan.md) | Improvement roadmap | Active |
| [99-session-changelog-2026-01-28](./99-session-changelog-2026-01-28.md) | Session changes log | Active |

---

## Status Legend

| Status | Description |
|--------|-------------|
| **Active** | Currently maintained and accurate |
| **Planned** | Specification complete, awaiting implementation |
| **In Progress** | Currently being implemented |
| **Complete** | Research/investigation finished |
| **Archive** | Historical reference only |

---

## Statistics

| Metric | Count |
|--------|-------|
| Total Specification Files | 345+ |
| Feature Domains | 25 |
| CLI Tool Specs | 39 (gsearch: 19, brun: 20) |
| Microservice Specs | 31 (7 services + 7 OpenAPI + tests) |
| Prompt Presets | 21 |
| Diagrams | 11 |
| Research Documents | 6 |
| API Endpoints | 200+ |
| Integration Test Cases | 70+ |
| Error Code Ranges | 2xxx-11xxx (7 services)

---

## Changelog

| File | Description |
|------|-------------|
| [CHANGELOG.md](./CHANGELOG.md) | Version history and structural changes |

---

## Archives

Historical audit reports and planning documents consolidated for reference.

| File | Description |
|------|-------------|
| [Audit History](/.lovable/audit-history.md) | 6 consistency reports (2026-01-26 to 2026-01-29) |
| [Standards Archive](/.lovable/standards-archive.md) | Improvement plans, reviewer feedback, standardization docs |

---

## Related Documents

- [Consistency Report](./99-consistency-report.md) - Health score and validation
- [Glossary](./08-roadmap-overview/03-glossary.md) - Technical terms
- [Implementation Guide](./08-roadmap-overview/04-implementation-guidelines.md) - Coding patterns
- [Folder Structure Diagram](./09-diagrams/07-folder-structure-diagram.md) - Visual folder overview
- [Feature Dependency Diagram](./09-diagrams/08-feature-dependency-diagram.md) - All 25 features mapped
