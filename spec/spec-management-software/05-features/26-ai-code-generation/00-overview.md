# Feature: AI Code Generation System

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Summary

On-the-fly Golang code generation system that replaces direct AI file manipulation with compiled CLI tools. AI writes custom Golang programs to execute complex tasks, ensuring predictability, reusability, full audit trails, and approval-based execution.

---

## User Stories

- As a user, I want AI to generate Golang code for complex file operations instead of directly modifying files
- As a user, I want to review and approve generated code before execution
- As a user, I want reusable code patterns stored and tagged for future similar tasks
- As a user, I want complete history of all filesystem operations performed
- As a user, I want AI to use existing code when similar tasks are requested
- As a user, I want parallel multi-model execution for long-chain commands

---

## Architecture

### Core Philosophy

Instead of AI directly modifying files, AI becomes a **code generator**:

| Benefit | Description |
|---------|-------------|
| Predictability | Code can be reviewed before execution |
| Reusability | Similar tasks reuse existing code |
| Auditability | Complete record of what code ran when |
| Testability | Generated code can be unit tested |
| Debugging | Clear error messages from compiled code |
| Approval | User reviews before any execution |

### Directory Structure

```
project-folder/
├── .tmp/
│   └── golang/
│       ├── 01-lowercase-filenames/
│       │   ├── main.go
│       │   ├── go.mod
│       │   └── execution.log
│       ├── 02-rename-by-pattern/
│       │   ├── main.go
│       │   ├── go.mod
│       │   └── execution.log
│       └── index.json
```

### Naming Convention

- Format: `NN-descriptive-task-name` (kebab-case)
- NN: Zero-padded sequence number (01, 02, 03...)
- Each directory is self-contained with `go.mod`

---

## Components

### Backend

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 01 | [System Overview](./01-system-overview.md) | Spec | Architecture and philosophy |
| 02 | [Complexity Decision](./02-complexity-decision.md) | Spec | When to generate code vs direct execution |
| 03 | [Code Generator](./03-code-generator.md) | Spec | LLM-based code generation engine |
| 04 | [Code Templates](./04-code-templates.md) | Spec | Standard Golang code templates |
| 05 | [Task Matcher](./05-task-matcher.md) | Spec | Reusability matching algorithm |
| 06 | [Execution Engine](./06-execution-engine.md) | Spec | Compilation and execution |
| 07 | [Approval Workflow](./07-approval-workflow.md) | Spec | User approval before execution |
| 08 | [History Logger](./08-history-logger.md) | Spec | Filesystem operation tracking |
| 09 | [Multi-Model Executor](./09-multi-model-executor.md) | Spec | Parallel model execution |
| 10 | [Agentic Search](./10-agentic-search.md) | Spec | Hybrid search for context retrieval |

### Database

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 11 | [Database Schema](./11-database-schema.md) | Spec | SQLite tables for code storage |
| 12 | [Tag System](./12-tag-system.md) | Spec | Tag taxonomy for categorization |

### Frontend

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 13 | [Code Review UI](./13-code-review-ui.md) | Spec | Code preview and approval interface |
| 14 | [Execution Monitor](./14-execution-monitor.md) | Spec | Real-time execution status |
| 15 | [History Browser](./15-history-browser.md) | Spec | Task and operation history viewer |

### Quality

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 99 | [Consistency Report](./99-consistency-report.md) | Report | Spec health tracking |

---

## Key Features

- **Approval-First Execution:** All generated code requires user approval before running
- **Multi-Model Architecture:** Thinking, writing, coding models switched per task
- **Parallel Execution:** Long-chain commands execute across multiple models simultaneously
- **Reusability Engine:** Generated code stored with tags for intelligent retrieval
- **Complete History:** Every filesystem operation logged with SHA-256 checksums
- **Dry-Run Mode:** Preview changes without execution
- **AI Self-Fix:** Retry with AI-corrected code on compilation/runtime errors

---

## Process Flow

```
User Request
    ↓
[Agent Layer] Analyze intent and complexity
    ↓
Decision: Simple OR Complex?
    ↓
SIMPLE → Direct execution (brun, existing tools)
    ↓
COMPLEX → Golang code generation path
    ↓
Query database for reusable code
    ↓
Found? → Present for approval → Execute
    ↓
Not Found? → Generate new Golang code
    ↓
Store in .tmp/golang/NN-task-name/main.go
    ↓
Present for user approval (dry-run preview)
    ↓
Approved? → Compile and execute
    ↓
Log operation to filesystem history
    ↓
Store code metadata in TempCodingTasks table
    ↓
Return results to user
```

---

## Technology Stack

| Component | Technology |
|-----------|------------|
| Code Generation | Golang (all generated code) |
| Database | SQLite (local-first, GORM) |
| LLM Models | Multi-model (thinking, coding, writing) |
| Execution | Go compiler with sandbox |
| History | SHA-256 checksums, structured logging |
| CLI Framework | Cobra + Viper (consistent with gsearch/brun) |

---

## Database Tables

| Table | Description |
|-------|-------------|
| TempCodingTasks | Generated code storage and metadata |
| FilesystemHistory | Operation audit trail |
| TaskExecutionLog | Detailed execution logs |
| TaskTags | Tag associations for reusability |

---

## Dependencies

- [AI Integration](../06-ai-integration/00-overview.md) — LLM provider abstraction
- [History System](../07-history-system/00-overview.md) — Version control integration
- [Build Runner CLI](../23-build-runner-cli/00-overview.md) — Execution patterns
- [Database Design](../../07-database-design/00-overview.md) — Schema conventions

---

## Error Codes

| Code | Name | Description |
|------|------|-------------|
| ERR_CODEGEN_COMPLEXITY | Complexity Analysis | Failed to determine task complexity |
| ERR_CODEGEN_GENERATE | Code Generation | LLM failed to generate valid code |
| ERR_CODEGEN_COMPILE | Compilation | Generated code failed to compile |
| ERR_CODEGEN_EXECUTE | Execution | Runtime error during execution |
| ERR_CODEGEN_REUSE | Reuse Matching | Failed to find reusable code |
| ERR_CODEGEN_APPROVAL | Approval | User rejected code execution |
| ERR_CODEGEN_HISTORY | History Logging | Failed to log operation |

---

## E2E Tests

| # | Test | Priority |
|---|------|----------|
| 01 | Complexity detection for file operations | Critical |
| 02 | Code generation for batch rename | Critical |
| 03 | Reusability matching by tags | High |
| 04 | Approval workflow end-to-end | Critical |
| 05 | History logging with checksums | High |
| 06 | Dry-run mode verification | High |
| 07 | Multi-model parallel execution | Medium |

---

## Related Specs

- [AI Integration](../06-ai-integration/00-overview.md)
- [History System](../07-history-system/00-overview.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
- [Knowledge Memory](../09-knowledge-memory/00-overview.md)
