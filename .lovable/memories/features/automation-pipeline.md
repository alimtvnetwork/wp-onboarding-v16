# Memory: features/automation-pipeline

Updated: 2026-01-30

The Agentic Automation Pipeline is a high-extensibility orchestration system for complex, multi-stage AI workflows, managed via a node-based React Flow canvas with full branching logic. It targets a 98% success rate for complex tasks through a combination of automated retry strategies and human escalation.

## Architecture Overview

The system consists of 8 phases with 34 specification files (24 core + 9 E2E test specs + 1 integration spec):

### Phase 1-3: Backend Core
1. **Database Schema**: Core tables (Pipeline, ExecutionBlock, Stage, ValidationScript, BlockConnection) stored in per-project `project.db`
2. **Prompt Import System**: ZIP/MD ingestion with YAML frontmatter extraction and configurable conflict resolution
3. **Variable Registry**: Scoped templating system (`{{block.stage.output}}`) with type coercion
4. **Stage Executor**: Lifecycle management for 7 stage types (Prompt, Search, CodeGen, Validation, Transform, HTTP, FileOp)
5. **Validation Runtime**: Direct execution (non-sandboxed) for Golang, Python, and TypeScript/Bun with standardized JSON I/O
6. **I/O Binding**: BindingResolver with JSONPath extraction and schema validation
7. **Execution Blocks**: Block containers with stage ordering and state machine
8. **Parallel Control**: ExecutionScheduler with worker pools, dependency graphs, and result aggregation
9. **Block Chaining**: Cross-block variable flow via ChainResolver

### Phase 4: Visual Editor
10. **React Flow Canvas**: PipelineCanvas component with BlockPalette, CanvasToolbar, and undo/redo system (HistoryState management)
11. **Stage Nodes**: Custom node components for all 7 stage types, BlockNode container, StageConfigPanel for editing
12. **Connection Wiring**: Custom edge types (DATA, CONTROL, CONDITIONAL) with animated DataFlowParticles, ConnectionValidator with DFS cycle detection, MappingEditor for variable transformations

### Phase 5: Control Flow
13. **Conditional Nodes**: IF_ELSE, SWITCH, GUARD, GATE nodes with ConditionExpression system (comparisons, logical operators, existence checks) and BranchRouter (PRIORITY, PARALLEL strategies)
14. **Loop Constructs**: FOR_EACH, WHILE, DO_UNTIL patterns with parallel iteration, concurrency throttling, and result aggregation (DEEP_MERGE). Loop variables: `{{loop.item}}`, `{{loop.index}}`
15. **Error Handlers**: Multi-layered resilience - TRY_CATCH, RETRY (exponential backoff with jitter), FALLBACK, CIRCUIT_BREAKER, COMPENSATION (Saga pattern), ESCALATION for human intervention

### Phase 6: Observability
16. **Live Execution View**: WebSocket event stream (ExecutionEvent) with node status overlays (Pulse, Spin, Glow), DataFlowParticles animation, and execution controls (Pause, Resume, Step Mode)
17. **Debug Inspector**: Variable tree with type-specific formatting, breakpoint management (Stage, Conditional, Error, Data breakpoints), hierarchical call stack view, debug console REPL for expression evaluation
18. **Telemetry Integration**: Distributed tracing (Spans/Traces), KPI tracking (success rates, P95 durations), alerting system with automated actions (trigger fallbacks, pause execution)

### Phase 7: Templates & Portability
19. **Pipeline Templates**: Reusable template system with TemplateGallery, parameterized templates (TemplateParameter), InstantiationEngine, and built-in template library (Content, Data, Code, Integration categories)
20. **Import/Export**: Multi-format support (JSON, YAML, ZIP bundles) with ExportManifest, ImportWizard, conflict resolution strategies (Skip, Replace, Rename, Merge), and rollback support
21. **Version Control**: Git-inspired system with PipelineVersion snapshots, PipelineBranch management, MergeRequest workflow, VisualDiffViewer (split/unified views), auto-versioning with pruning strategies

### Phase 8: Permissions & Collaboration
22. **Permissions**: RBAC system with PipelineRole enum (VIEWER, EXECUTOR, EDITOR, ADMIN, OWNER), Team support, SECURITY DEFINER SQL functions to prevent RLS recursion, PermissionManager UI, audit logging
23. **Sharing**: ShareLink with secure tokens, email invitations, visibility settings (PRIVATE, UNLISTED, PUBLIC), password protection, usage limits, expiration, and public gallery discovery
24. **Collaboration**: Real-time multi-user editing via WebSocket, CollaborationProvider context, cursor tracking (CollaborativeCursors), selection sync (SelectionOverlay), operational transformation for consistency, ConflictDialog for conflict resolution

## Database Tables

All stored in per-project `project.db`:
- Core: Pipeline, ExecutionBlock, Stage, ValidationScript, BlockConnection, ConditionalBranch
- Prompts: PromptTemplate, PipelineVariable
- Execution: PipelineExecution, StageExecution, ExecutionEvent, ExecutionSnapshot, ExecutionLog
- Debug: DebugBreakpoint, DebugWatch
- Telemetry: TelemetryMetric, TelemetrySpan
- Templates: PipelineTemplate, TemplateParameter, TemplateInstance
- Import: ImportHistory, ImportItem
- Versioning: PipelineVersion, PipelineBranch, VersionChange, MergeRequest
- Permissions: PipelinePermission, Team, TeamMember, PermissionAuditLog
- Sharing: ShareLink, ShareInvitation, ShareLinkAccess, PipelineVisibility
- Collaboration: CollaborationSession, SessionParticipant, CollaborationOperation

## Key Constraints

- Cross-database JOINs prohibited; aggregation at application layer
- Validation scripts execute directly (non-sandboxed) - security via user trust model
- Canvas performance target: 60fps with 50+ nodes
- Variable resolution target: <10ms per variable
- Stage execution success target: ≥98% with retries

## Related Features

- [Resilient Execution System](./resilient-execution-system.md) — Fault tolerance mechanisms
- [Telemetry Dashboard](./telemetry-dashboard.md) — KPI monitoring
- [Escalation Notifications](./escalation-notifications.md) — Human oversight routing
- [Code Generation System](./code-generation-system-architecture.md) — Script generation
