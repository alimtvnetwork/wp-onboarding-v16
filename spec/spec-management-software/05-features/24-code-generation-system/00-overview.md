# Code Generation System

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

The AI-Powered Code Generation System enables automated, specification-driven code creation with parallel execution, hierarchical coding guidelines, Git integration, and build verification. This system transforms specifications into production-ready code repositories.

**Cross-References:**
- [AI Integration](../06-ai-integration/00-overview.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
- [Instruction System](../06-ai-integration/07-instruction-system.md)
- [Model Categories](../06-ai-integration/05-model-categories.md)

---

## Document Index

| # | Document | Description | Status |
|---|----------|-------------|--------|
| 00 | [Overview](./00-overview.md) | This file | Active |
| 01 | [Architecture](./01-architecture.md) | System architecture and data flow | Active |
| 02 | [Guideline Hierarchy](./02-guideline-hierarchy.md) | 4-layer merge/extend system | Active |
| 03 | [Parallel Code Generation](./03-parallel-code-generation.md) | Three-phase workflow | Active |
| 04 | [Plan Generator](./04-plan-generator.md) | Topological sort & batching | Active |
| 05 | [Parallel Executor](./05-parallel-executor.md) | Worker pools & concurrent execution | Active |
| 06 | [Build Verification](./06-build-verification.md) | brun CLI integration & AI fix loop | Active |
| 07 | [Git Integration](./07-git-integration.md) | Local/remote repository management | Active |
| 08 | [Configuration](./08-configuration.md) | Configuration manifest | Active |
| 09 | [Credit System](./09-credit-system.md) | Per-request token tracking | Active |
| 10 | [Repository Structure](./10-repository-structure.md) | Generated repo folder conventions | Active |
| 11 | [Coding Model Presets](./11-coding-model-presets.md) | Model selection and presets | Active |
| 12 | [Spec Folder Guideline](./12-spec-folder-guideline.md) | AI training guide for folder structure | Active |
| 13 | [API Endpoints](./13-api-endpoints.md) | REST API specification | Active |
| 14 | [Data Models](./14-data-models.md) | Database entities (GORM) | Active |
| 15 | [WebSocket Events](./15-websocket-events.md) | Real-time progress streaming | Active |
| 16 | [Error Codes](./16-error-codes.md) | Error code registry (12xxx range) | Active |
| 17 | [Testing Strategy](./17-testing-strategy.md) | 60/30/10 test pyramid | Active |
| 18 | [Deployment Guide](./18-deployment-guide.md) | Production deployment | Active |
| 19 | [Implementation Guide](./19-implementation-guide.md) | 8-phase, 20-day roadmap | Active |
| 20 | [Project Editor UI](./20-project-editor-ui.md) | VS Code-style editor interface | Active |
| 21 | [Suggestions System](./21-suggestions-system.md) | AI suggestion generation & tracking | Active |
| 22 | [Loop Validation](./22-loop-validation.md) | Iterative spec/code validation | Active |
| 23 | [Settings System](./23-settings-system.md) | Hover dropdown, shortcuts, health | Active |
| 24 | [Error Handling System](./24-error-handling-system.md) | localStorage error persistence | Active |
| 25 | [AI Chat Interface](./25-ai-chat-interface.md) | ChatGPT-style AI interface | Active |
| 26 | [Questioning System](./26-questioning-system.md) | Clarification question flow | Active |
| 27 | [Suggestions UI](./27-suggestions-ui.md) | Suggestion display components | Active |
| 28 | [File Modification Display](./28-file-modification-display.md) | Bolt/Lovable-style file changes | Active |
| 29 | [Long Chain Events](./29-long-chain-events.md) | Reasoning step visualization | Active |
| 30 | [Search Integration](./30-search-integration.md) | gsearch CLI integration | Active |
| 31 | [Chat History Branching](./31-chat-history-branching.md) | Multi-path conversation history | Active |
| 32 | [URL Context System](./32-url-context-system.md) | Full-site crawling UI integration | Active |
| 99 | [Consistency Report](./99-consistency-report.md) | Document health tracking | Active |

---

## Key Features

### 1. Hierarchical Coding Guidelines
Four-layer guideline system with **merge/extend semantics**:
1. **General Guidelines** - Universal coding standards (base layer)
2. **Language Guidelines** - Language-specific conventions (Go, React, PHP) - extends Layer 1
3. **User Preference Guidelines** - Personal coding style - extends Layer 2
4. **Project Guidelines** - Project-specific rules - extends Layer 3

Later layers **extend** earlier layers and can selectively override specific settings.

### 2. Parallel Code Generation
- **Topological dependency analysis** before generation
- **Parallel batch execution** for independent files
- **Backend/Frontend simultaneous generation**
- **Worker pool** with configurable concurrency per project

### 3. Three-Phase Workflow
```
Phase 1: Code Writing
├── Read specifications
├── Create file plan with dependency graph
├── Generate code in parallel batches
└── Track progress per file

Phase 2: Consistency Check
├── Cross-file reference validation
├── Import/dependency verification
└── Naming convention compliance

Phase 3: Build Verification
├── Execute brun check for each language
├── Collect errors for AI fix loop
└── Commit on successful build
```

### 4. Git Integration
- **Local repository creation** per project in configurable root directory
- **GitHub/GitLab OAuth** connection with token refresh
- **Automatic git pull** before any code generation
- **Descriptive commit messages** with spec references
- **Automatic push** after local commits (when connected and enabled)
- **Conflict detection** with notification for manual resolution

### 5. Credit System
Credits consumed based on **Per AI Request** only:
- **Input Tokens** - Cost per input token sent to LLM
- **Output Tokens** - Cost per output token generated
- Credit calculation: `(input × inputRate) + (output × outputRate)`
- Balance checking before generation starts
- Usage history and transaction tracking

### 6. Multi-Project Concurrency
- **Per-project parallel** generation sessions
- Multiple projects can generate code simultaneously
- Resource isolation between projects

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    Code Generation System                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐       │
│  │   Guideline  │    │     Plan     │    │    Code      │       │
│  │   Resolver   │───▶│   Generator  │───▶│   Generator  │       │
│  └──────────────┘    └──────────────┘    └──────────────┘       │
│         │                   │                   │                │
│         ▼                   ▼                   ▼                │
│  ┌──────────────────────────────────────────────────────┐       │
│  │              Parallel Execution Engine                │       │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  │       │
│  │  │ Worker1 │  │ Worker2 │  │ Worker3 │  │ Worker4 │  │       │
│  │  │   (BE)  │  │   (BE)  │  │   (FE)  │  │   (FE)  │  │       │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘  │       │
│  └──────────────────────────────────────────────────────┘       │
│                            │                                     │
│         ┌──────────────────┼──────────────────┐                 │
│         ▼                  ▼                  ▼                 │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐        │
│  │  Consistency │   │    Build     │   │     Git      │        │
│  │    Checker   │   │   Verifier   │   │   Manager    │        │
│  └──────────────┘   └──────────────┘   └──────────────┘        │
│                            │                                     │
│                            ▼                                     │
│                     ┌──────────────┐                            │
│                     │  brun CLI    │                            │
│                     └──────────────┘                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Phases

| Phase | Name | Duration | Dependencies |
|-------|------|----------|--------------|
| 1 | Foundation & Data Models | 3 days | None |
| 2 | Coding Guidelines Hierarchy | 2 days | Phase 1 |
| 3 | Plan Generation | 2 days | Phase 2 |
| 4 | Parallel Execution Engine | 3 days | Phase 3 |
| 5 | Git Integration | 3 days | Phase 1 |
| 6 | Build Verification | 2 days | Phase 4, brun CLI |
| 7 | Credit System | 2 days | Phase 4 |
| 8 | API & Frontend | 3 days | All previous |

**Total Estimated Duration:** 20 days

---

## Configuration Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `codegen.repo.rootDirectory` | string | `./code-repos` | Root directory for generated repositories |
| `codegen.parallel.maxWorkers` | int | 4 | Maximum parallel workers per project |
| `codegen.parallel.batchSize` | int | 10 | Files per parallel batch |
| `codegen.git.autoCommit` | bool | true | Auto-commit after generation |
| `codegen.git.autoPush` | bool | false | Auto-push when remote connected |
| `codegen.build.verifyAfterGeneration` | bool | true | Run brun after generation |
| `codegen.credits.perRequest` | float | 0.01 | Credits per AI request |
| `codegen.credits.perFile` | float | 0.05 | Credits per file generated |
| `codegen.credits.perBuildCycle` | float | 0.10 | Credits per build verification |

---

## Error Code Range

The Code Generation System uses the **12xxx** error code range:

| Range | Category |
|-------|----------|
| 12000-12099 | General Code Generation |
| 12100-12199 | Guideline Resolution |
| 12200-12299 | Plan Generation |
| 12300-12399 | Parallel Execution |
| 12400-12499 | Git Operations |
| 12500-12599 | Build Verification |
| 12600-12699 | Credit System |
| 12700-12799 | Repository Structure |

---

## Related Specs

- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
- [AI Model Categories](../06-ai-integration/05-model-categories.md)
- [Instruction System](../06-ai-integration/07-instruction-system.md)
- [Error Code Registry](../../06-error-management/error-code-registry.md)
