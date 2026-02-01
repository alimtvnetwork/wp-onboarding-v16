# Feature: Automation Pipeline System

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Summary

A node-based visual automation system for creating multi-stage AI pipelines with prompt chaining, validation scripts, and extensible variable templating. Enables ETL-like workflows combining Google Search, AI processing, code generation, and custom validation with full branching logic.

---

## User Stories

- As a user, I want to import prompt templates from zip files into organized folders
- As a user, I want to create multi-stage automation pipelines with visual node editor
- As a user, I want stages to pass data to subsequent stages via variable templating
- As a user, I want to validate AI outputs using custom Golang/Python/TypeScript scripts
- As a user, I want to chain execution blocks with conditional branching logic
- As a user, I want to run blocks in parallel or sequential order
- As a user, I want to monitor pipeline execution in real-time with debugging

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Automation Pipeline System                    │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │   Prompt    │  │  Variable   │  │ Validation  │              │
│  │   Library   │  │  Registry   │  │  Scripts    │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
│         │                │                │                      │
│         └────────────────┼────────────────┘                      │
│                          ▼                                       │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                    Pipeline Engine                         │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐       │  │
│  │  │ Block 1 │──│ Block 2 │──│ Block 3 │──│ Block N │       │  │
│  │  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘       │  │
│  │       │            │            │            │             │  │
│  │  ┌────┴────┐  ┌────┴────┐  ┌────┴────┐  ┌────┴────┐       │  │
│  │  │ Stage 1 │  │ Stage 1 │  │ Stage 1 │  │ Stage 1 │       │  │
│  │  │ Stage 2 │  │ Stage 2 │  │   ...   │  │   ...   │       │  │
│  │  │   ...   │  │   ...   │  └─────────┘  └─────────┘       │  │
│  │  └─────────┘  └─────────┘                                  │  │
│  └───────────────────────────────────────────────────────────┘  │
│                          │                                       │
│                          ▼                                       │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              Execution & Monitoring Layer                  │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Specification Index

### Phase 1: Foundation (Backend Core)

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 01 | [Database Schema](./01-database-schema.md) | Backend | Core tables: Pipeline, ExecutionBlock, Stage, ValidationScript, BlockConnection |
| 02 | [Prompt Import System](./02-prompt-import-system.md) | Backend | ZIP/MD ingestion with YAML frontmatter, conflict resolution, folder organization |
| 03 | [Variable Registry](./03-variable-registry.md) | Backend | Scoped templating `{{block.stage.output}}`, resolution engine, type coercion |

### Phase 2: Stage Engine (Execution Core)

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 04 | [Stage Executor](./04-stage-executor.md) | Backend | Stage lifecycle, StageHandler interface for 7 stage types, timeout/retry logic |
| 05 | [Validation Runtime](./05-validation-runtime.md) | Backend | Direct execution for Golang, Python, TypeScript/Bun with standardized JSON I/O |
| 06 | [Input Output Binding](./06-io-binding.md) | Backend | BindingResolver, JSONPath extraction, schema validation, type transformers |

### Phase 3: Block Orchestration (Pipeline Engine)

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 07 | [Execution Blocks](./07-execution-blocks.md) | Backend | Block containers, stage ordering, BlockExecutor with state machine |
| 08 | [Parallel Sequential Control](./08-parallel-control.md) | Backend | ExecutionScheduler, worker pools, dependency graph, result aggregation |
| 09 | [Block Chaining](./09-block-chaining.md) | Backend | BlockConnection wiring, ChainResolver, cross-block variable flow |

### Phase 4: Node Canvas UI (Visual Editor)

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 10 | [React Flow Canvas](./10-react-flow-canvas.md) | Frontend | PipelineCanvas component, BlockPalette, CanvasToolbar, undo/redo system |
| 11 | [Stage Node Components](./11-stage-nodes.md) | Frontend | Custom nodes for 7 stage types, BlockNode container, StageConfigPanel |
| 12 | [Connection Wiring](./12-connection-wiring.md) | Frontend | Custom edge types (DATA/CONTROL/CONDITIONAL), ConnectionValidator, MappingEditor |

### Phase 5: Control Flow (Branching & Iteration)

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 13 | [Conditional Nodes](./13-conditional-nodes.md) | Full-Stack | IF_ELSE, SWITCH, GUARD, GATE nodes; ConditionExpression system, BranchRouter |
| 14 | [Loop Constructs](./14-loop-constructs.md) | Backend | FOR_EACH, WHILE, DO_UNTIL; parallel iteration, concurrency throttling, aggregation |
| 15 | [Error Handlers](./15-error-handlers.md) | Backend | TRY_CATCH, RETRY (exponential backoff), CIRCUIT_BREAKER, COMPENSATION (Saga), ESCALATION |

### Phase 6: Execution Monitoring (Observability)

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 16 | [Live Execution View](./16-live-execution-view.md) | Frontend | WebSocket event stream, node status overlays, DataFlowParticles, execution controls |
| 17 | [Debug Inspector](./17-debug-inspector.md) | Frontend | Variable tree, breakpoint management, call stack view, debug console REPL |
| 18 | [Telemetry Integration](./18-telemetry-integration.md) | Backend | Distributed tracing (Spans), KPI tracking, alerting system, automated actions |

### Phase 7: Templates & Portability

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 19 | [Pipeline Templates](./19-pipeline-templates.md) | Full-Stack | TemplateGallery, parameterized templates, InstantiationEngine, built-in template library |
| 20 | [Import Export](./20-import-export.md) | Full-Stack | JSON/YAML/ZIP export, ImportWizard, conflict resolution, rollback support |
| 21 | [Version Control](./21-version-control.md) | Full-Stack | Git-inspired branching, VersionHistoryPanel, MergeRequest, auto-versioning, diff viewer |

### Phase 8: Permissions & Collaboration

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 22 | [Permissions](./22-permissions.md) | Full-Stack | RBAC with PipelineRole enum, Team support, SECURITY DEFINER functions, PermissionManager UI |
| 23 | [Sharing](./23-sharing.md) | Full-Stack | ShareLink with tokens, invitations, visibility settings (Private/Unlisted/Public), email notifications |
| 24 | [Collaboration](./24-collaboration.md) | Full-Stack | Real-time editing via WebSocket, cursor tracking, operational transformation, conflict resolution |

### Integration Specifications

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 28 | [RES Integration](./28-res-integration.md) | Full-Stack | Integration with Resilient Execution System for fault tolerance, self-correction, consensus, escalation |

---

## Key Features

- **Node-Based Canvas:** Drag-drop visual pipeline builder (React Flow)
- **Multi-Language Validation:** Golang, Python, TypeScript direct execution
- **Variable Templating:** Extensible `{{block.stage.output}}` syntax
- **Full Branching:** Conditional paths, loops, error handlers
- **Parallel Execution:** Blocks can run concurrently or sequentially
- **Block Chaining:** Output of one block feeds into another

---

## Stage Types

| Type | Purpose | Example Use |
|------|---------|-------------|
| PROMPT | Execute prompt template with AI | Generate HTML from spec |
| CODE_GEN | Generate code via AI models | Create validation script |
| SEARCH | Google/web search integration | Research topic |
| VALIDATION | Run custom validation script | Verify HTML structure |
| TRANSFORM | Data transformation | Parse JSON, extract fields |
| HTTP | External API calls | Webhook, third-party APIs |
| FILE_OP | File read/write operations | Save output, load input |

---

## Execution Modes

| Mode | Description |
|------|-------------|
| SEQUENTIAL | Blocks execute one after another |
| PARALLEL | Blocks in same group execute concurrently |
| HYBRID | Mix of sequential and parallel blocks |

---

## Dependencies

- [AI Integration](../06-ai-integration/00-overview.md) — AI model execution
- [Resilient Execution System](../06-ai-integration/12-resilient-execution-system.md) — Fault tolerance
- [Knowledge Memory](../09-knowledge-memory/00-overview.md) — RAG context
- [Code Generation System](../24-code-generation-system/00-overview.md) — Script generation

---

## Database Location

All automation pipeline data stored in **project.db** (per-project database):

- `PromptTemplate` — Imported prompts
- `Pipeline` — Pipeline definitions
- `ExecutionBlock` — Block containers
- `Stage` — Individual stages
- `PipelineVariable` — Variable registry
- `ValidationScript` — Validation code
- `BlockConnection` — Block wiring
- `ConditionalBranch` — Branching logic
- `PipelineExecution` — Execution history
- `StageExecution` — Stage-level logs
- `PipelineTemplate` — Reusable templates
- `TemplateParameter` — Template parameters
- `ImportHistory` — Import audit trail
- `PipelineVersion` — Version snapshots
- `PipelineBranch` — Branch management
- `MergeRequest` — Merge requests
- `PipelinePermission` — Access grants
- `Team` — Team definitions
- `TeamMember` — Team membership
- `ShareLink` — Share links
- `ShareInvitation` — Invitations
- `CollaborationSession` — Active sessions
- `SessionParticipant` — Session participants
- `CollaborationOperation` — OT operations

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Pipeline Creation Time | < 5 min for 10-stage pipeline |
| Stage Execution Success | ≥ 98% with retries |
| Variable Resolution | < 10ms per variable |
| Canvas Render Performance | 60fps with 50+ nodes |

---

## E2E Tests

### Phase 1-6 Tests

| # | Test | Priority | Coverage |
|---|------|----------|----------|
| 29 | [Prompt Import Tests](./29-prompt-import-tests.md) | Critical | ZIP upload, YAML parsing, conflict resolution |
| 30 | [Pipeline Creation Tests](./30-pipeline-creation-tests.md) | Critical | Canvas, blocks, stages, connections |
| 31 | [Variable Resolution Tests](./31-variable-resolution-tests.md) | Critical | Template syntax, scoping, type coercion |
| 32 | [Stage Execution Tests](./32-stage-execution-tests.md) | Critical | All 7 stage types, timeout, retry |
| 33 | [Branching Tests](./33-branching-tests.md) | High | IF_ELSE, SWITCH, GUARD, GATE |
| 34 | [Parallel Execution Tests](./34-parallel-execution-tests.md) | High | Concurrency, worker pools, aggregation |

### Phase 8 Tests

| # | Test | Priority | Coverage |
|---|------|----------|----------|
| 25 | [Permissions Tests](./25-permissions-tests.md) | Critical | RBAC, role hierarchy, privilege escalation |
| 26 | [Sharing Tests](./26-sharing-tests.md) | High | Share links, invitations, visibility |
| 27 | [Collaboration Tests](./27-collaboration-tests.md) | High | Real-time editing, OT, presence |

---

## Architecture Reference

- [System Architecture Diagram](./99-architecture-diagram.md) — Complete visual architecture with Mermaid diagrams

---

## Related Specs

- [AI Integration](../06-ai-integration/00-overview.md)
- [Resilient Execution System](../06-ai-integration/12-resilient-execution-system.md)
- [Code Generation System](../24-code-generation-system/00-overview.md)
