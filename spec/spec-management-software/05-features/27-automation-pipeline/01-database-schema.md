# Component: Database Schema

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 2.0.0  
**Status:** Complete  
**Phase:** 1 - Foundation  

---

## Summary

Core database schema for the Automation Pipeline System, stored in `project.db`. Defines tables for pipelines, execution blocks, stages, variables, validation scripts, connections, and execution history.

---

## Database: project.db

All tables reside in the per-project SQLite database following the split database architecture.

---

## Tables

### PromptTemplate

Stores imported prompt templates from ZIP files.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| ProjectId | TEXT | NOT NULL | Parent project reference |
| FolderPath | TEXT | NOT NULL | Relative path (e.g., `prompts/html-generation`) |
| FileName | TEXT | NOT NULL | File name (e.g., `generate-page.md`) |
| Content | TEXT | NOT NULL | Full prompt content |
| Metadata | TEXT | NULL | JSON: `{category, tags, version, author}` |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_prompt_project_folder` ON (ProjectId, FolderPath)
- `idx_prompt_filename` ON (FileName)

---

### Pipeline

Top-level automation pipeline definition.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| ProjectId | TEXT | NOT NULL | Parent project reference |
| Name | TEXT | NOT NULL | Display name |
| Description | TEXT | NULL | Optional description |
| ExecutionMode | TEXT | NOT NULL | Enum: `SEQUENTIAL`, `PARALLEL`, `HYBRID` |
| IsActive | INTEGER | DEFAULT 1 | Soft delete flag |
| GlobalVariables | TEXT | NULL | JSON: Default variable values |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_pipeline_project` ON (ProjectId)
- `idx_pipeline_active` ON (IsActive)

---

### ExecutionBlock

Container for grouped stages within a pipeline.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| PipelineId | TEXT | FK → Pipeline(Id) | Parent pipeline |
| Name | TEXT | NOT NULL | Display name |
| Description | TEXT | NULL | Optional description |
| ExecutionOrder | INTEGER | NOT NULL | Sequential order (1-based) |
| ParallelGroup | INTEGER | NULL | Same value = parallel execution |
| CanvasX | REAL | NULL | Node X position on canvas |
| CanvasY | REAL | NULL | Node Y position on canvas |
| CanvasWidth | REAL | DEFAULT 200 | Node width |
| CanvasHeight | REAL | DEFAULT 150 | Node height |
| IsCollapsed | INTEGER | DEFAULT 0 | UI collapse state |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_block_pipeline` ON (PipelineId)
- `idx_block_order` ON (PipelineId, ExecutionOrder)
- `idx_block_parallel` ON (ParallelGroup)

---

### Stage

Individual execution stage within a block.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| BlockId | TEXT | FK → ExecutionBlock(Id) | Parent block |
| Name | TEXT | NOT NULL | Display name |
| StageType | TEXT | NOT NULL | Enum: `PROMPT`, `CODE_GEN`, `SEARCH`, `VALIDATION`, `TRANSFORM`, `HTTP`, `FILE_OP` |
| ExecutionOrder | INTEGER | NOT NULL | Order within block (1-based) |
| Config | TEXT | NOT NULL | JSON: Type-specific configuration |
| InputBindings | TEXT | NULL | JSON: `{paramName: "{{var.path}}"}` |
| OutputVariable | TEXT | NULL | Variable name for output |
| TimeoutSeconds | INTEGER | DEFAULT 300 | Execution timeout |
| RetryConfig | TEXT | NULL | JSON: `{maxRetries, backoffMs, retryOn}` |
| IsEnabled | INTEGER | DEFAULT 1 | Skip if disabled |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_stage_block` ON (BlockId)
- `idx_stage_order` ON (BlockId, ExecutionOrder)
- `idx_stage_type` ON (StageType)

---

### Stage Config Schemas

#### PROMPT Stage Config

```json
{
  "promptTemplateId": "uuid",
  "additionalContext": "string (2k-5k chars)",
  "voiceInputEnabled": false,
  "model": "llama-3",
  "temperature": 0.7,
  "maxTokens": 4096,
  "outputFormat": "TEXT | FILE | JSON",
  "outputFilePath": "optional/path.html"
}
```

#### CODE_GEN Stage Config

```json
{
  "language": "GOLANG | PYTHON | TYPESCRIPT",
  "taskDescription": "string",
  "model": "codellama",
  "outputPath": "path/to/file.go",
  "executeAfterGeneration": false
}
```

#### SEARCH Stage Config

```json
{
  "query": "{{input.topic}} best practices 2026",
  "maxResults": 10,
  "minConfidence": 0.7,
  "sources": ["stackoverflow.com", "github.com"],
  "excludeDomains": ["pinterest.com"]
}
```

#### VALIDATION Stage Config

```json
{
  "scriptId": "uuid",
  "targetVariable": "{{prev.output}}",
  "onFailure": "STOP | RETRY | CONTINUE | BRANCH",
  "maxRetries": 3,
  "failureBranchId": "uuid"
}
```

#### TRANSFORM Stage Config

```json
{
  "transformType": "JSON_PARSE | JSON_STRINGIFY | REGEX_EXTRACT | TEMPLATE",
  "expression": "$.data.items[*].name",
  "template": "Result: {{input}}"
}
```

#### HTTP Stage Config

```json
{
  "url": "https://api.example.com/endpoint",
  "method": "GET | POST | PUT | DELETE",
  "headers": {"Authorization": "Bearer {{secrets.api_key}}"},
  "body": "{{prev.output}}",
  "expectedStatus": [200, 201]
}
```

#### FILE_OP Stage Config

```json
{
  "operation": "READ | WRITE | APPEND | DELETE | COPY | MOVE",
  "sourcePath": "input/file.txt",
  "destinationPath": "output/file.txt",
  "encoding": "utf-8"
}
```

---

### PipelineVariable

Variable registry for pipeline scope.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| PipelineId | TEXT | FK → Pipeline(Id) | Parent pipeline |
| Name | TEXT | NOT NULL | Variable name (unique per pipeline) |
| Scope | TEXT | NOT NULL | Enum: `GLOBAL`, `BLOCK`, `STAGE` |
| DataType | TEXT | NOT NULL | Enum: `STRING`, `FILE`, `JSON`, `NUMBER`, `BOOLEAN`, `ARRAY` |
| DefaultValue | TEXT | NULL | Default value |
| Description | TEXT | NULL | Documentation |
| IsRequired | INTEGER | DEFAULT 0 | Must be provided at runtime |
| ValidationPattern | TEXT | NULL | Regex for validation |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Constraints:**
- UNIQUE (PipelineId, Name)

**Indexes:**
- `idx_variable_pipeline` ON (PipelineId)
- `idx_variable_scope` ON (Scope)

---

### ValidationScript

Reusable validation scripts in Golang/Python/TypeScript.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| ProjectId | TEXT | NOT NULL | Parent project |
| Name | TEXT | NOT NULL | Display name |
| Description | TEXT | NULL | What this validates |
| Language | TEXT | NOT NULL | Enum: `GOLANG`, `PYTHON`, `TYPESCRIPT` |
| SourceCode | TEXT | NOT NULL | Full source code |
| EntryFunction | TEXT | DEFAULT 'validate' | Function to call |
| FolderPath | TEXT | NULL | Storage path (e.g., `validators/html-check`) |
| InputSchema | TEXT | NULL | JSON Schema for expected input |
| OutputSchema | TEXT | NULL | JSON Schema for expected output |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_script_project` ON (ProjectId)
- `idx_script_language` ON (Language)

---

### BlockConnection

Connections between execution blocks.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| PipelineId | TEXT | FK → Pipeline(Id) | Parent pipeline |
| SourceBlockId | TEXT | FK → ExecutionBlock(Id) | Output block |
| TargetBlockId | TEXT | FK → ExecutionBlock(Id) | Input block |
| ConnectionType | TEXT | NOT NULL | Enum: `DATA`, `CONTROL`, `CONDITIONAL` |
| Condition | TEXT | NULL | JSON: Condition for CONDITIONAL type |
| OutputMapping | TEXT | NULL | JSON: `{sourceVar: targetVar}` |
| SourceHandle | TEXT | DEFAULT 'bottom' | Canvas connection point |
| TargetHandle | TEXT | DEFAULT 'top' | Canvas connection point |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_connection_pipeline` ON (PipelineId)
- `idx_connection_source` ON (SourceBlockId)
- `idx_connection_target` ON (TargetBlockId)

---

### ConditionalBranch

Branching logic definitions.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| BlockId | TEXT | FK → ExecutionBlock(Id) | Parent block |
| Name | TEXT | NULL | Branch name |
| Condition | TEXT | NOT NULL | Expression: `{{output.status}} == 'success'` |
| ConditionType | TEXT | NOT NULL | Enum: `EQUALS`, `NOT_EQUALS`, `CONTAINS`, `REGEX`, `EXPRESSION` |
| TrueTargetBlockId | TEXT | FK → ExecutionBlock(Id) | Block if true |
| FalseTargetBlockId | TEXT | FK → ExecutionBlock(Id) | Block if false |
| Priority | INTEGER | DEFAULT 0 | Evaluation order |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_branch_block` ON (BlockId)
- `idx_branch_priority` ON (BlockId, Priority)

---

### LoopConstruct

Loop definitions for iteration.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| BlockId | TEXT | FK → ExecutionBlock(Id) | Block containing loop |
| LoopType | TEXT | NOT NULL | Enum: `FOR_EACH`, `WHILE`, `FOR_COUNT` |
| IteratorVariable | TEXT | NOT NULL | Variable for current item |
| IndexVariable | TEXT | NULL | Variable for current index |
| SourceVariable | TEXT | NULL | Array to iterate (FOR_EACH) |
| Condition | TEXT | NULL | Continue condition (WHILE) |
| MaxIterations | INTEGER | DEFAULT 100 | Safety limit |
| LoopBodyBlockId | TEXT | FK → ExecutionBlock(Id) | Block to execute per iteration |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_loop_block` ON (BlockId)

---

### ErrorHandler

Error handling configuration.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| BlockId | TEXT | FK → ExecutionBlock(Id) | Block to handle errors for |
| ErrorType | TEXT | NOT NULL | Enum: `ALL`, `TIMEOUT`, `VALIDATION`, `EXECUTION`, `NETWORK` |
| HandlerType | TEXT | NOT NULL | Enum: `RETRY`, `FALLBACK`, `SKIP`, `STOP`, `BRANCH` |
| HandlerConfig | TEXT | NULL | JSON: Type-specific config |
| FallbackBlockId | TEXT | NULL | FK → ExecutionBlock(Id) |
| MaxRetries | INTEGER | DEFAULT 3 | Retry limit |
| NotifyOnError | INTEGER | DEFAULT 0 | Send notification |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_error_block` ON (BlockId)
- `idx_error_type` ON (ErrorType)

---

### PipelineExecution

Execution history for pipelines.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| PipelineId | TEXT | FK → Pipeline(Id) | Executed pipeline |
| Status | TEXT | NOT NULL | Enum: `PENDING`, `RUNNING`, `SUCCESS`, `FAILED`, `CANCELLED`, `PAUSED` |
| StartedAt | TEXT | NULL | Execution start time |
| CompletedAt | TEXT | NULL | Execution end time |
| DurationMs | INTEGER | NULL | Total duration |
| TriggerType | TEXT | NOT NULL | Enum: `MANUAL`, `SCHEDULED`, `WEBHOOK`, `API` |
| TriggeredBy | TEXT | NULL | User ID or system identifier |
| InputVariables | TEXT | NULL | JSON: Initial variable values |
| OutputVariables | TEXT | NULL | JSON: Final variable values |
| ErrorMessage | TEXT | NULL | Error details if failed |
| ErrorStackTrace | TEXT | NULL | Full stack trace |
| TotalStages | INTEGER | NULL | Count of stages |
| CompletedStages | INTEGER | DEFAULT 0 | Completed stage count |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_execution_pipeline` ON (PipelineId)
- `idx_execution_status` ON (Status)
- `idx_execution_date` ON (StartedAt DESC)

---

### StageExecution

Stage-level execution logs.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| PipelineExecutionId | TEXT | FK → PipelineExecution(Id) | Parent execution |
| StageId | TEXT | FK → Stage(Id) | Executed stage |
| BlockId | TEXT | FK → ExecutionBlock(Id) | Parent block |
| Status | TEXT | NOT NULL | Enum: `PENDING`, `RUNNING`, `SUCCESS`, `FAILED`, `SKIPPED`, `RETRYING` |
| AttemptNumber | INTEGER | DEFAULT 1 | Current attempt |
| Input | TEXT | NULL | JSON: Resolved input values |
| Output | TEXT | NULL | JSON: Stage output |
| OutputFilePath | TEXT | NULL | Path if output is file |
| StartedAt | TEXT | NULL | Stage start time |
| CompletedAt | TEXT | NULL | Stage end time |
| DurationMs | INTEGER | NULL | Execution duration |
| TokensUsed | INTEGER | NULL | AI tokens consumed |
| Model | TEXT | NULL | AI model used |
| ErrorMessage | TEXT | NULL | Error details |
| ErrorCode | TEXT | NULL | Error classification |
| ValidationResult | TEXT | NULL | JSON: `{passed, errors, warnings}` |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_stage_exec_pipeline` ON (PipelineExecutionId)
- `idx_stage_exec_stage` ON (StageId)
- `idx_stage_exec_status` ON (Status)

---

### ExecutionCheckpoint

Checkpoints for rollback capability.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| Id | TEXT | PRIMARY KEY | UUID identifier |
| PipelineExecutionId | TEXT | FK → PipelineExecution(Id) | Parent execution |
| BlockId | TEXT | FK → ExecutionBlock(Id) | Checkpoint block |
| StageId | TEXT | FK → Stage(Id) | Checkpoint stage |
| CheckpointType | TEXT | NOT NULL | Enum: `BLOCK_START`, `BLOCK_END`, `STAGE_COMPLETE` |
| VariableSnapshot | TEXT | NOT NULL | JSON: All variable values |
| FileManifest | TEXT | NULL | JSON: List of created files |
| CanRollback | INTEGER | DEFAULT 1 | Rollback possible |
| CreatedAt | TEXT | NOT NULL | ISO 8601 timestamp |

**Indexes:**
- `idx_checkpoint_execution` ON (PipelineExecutionId)
- `idx_checkpoint_block` ON (BlockId)

---

## Enums (TypeScript)

```typescript
export enum ExecutionMode {
  SEQUENTIAL = 'SEQUENTIAL',
  PARALLEL = 'PARALLEL',
  HYBRID = 'HYBRID',
}

export enum StageType {
  PROMPT = 'PROMPT',
  CODE_GEN = 'CODE_GEN',
  SEARCH = 'SEARCH',
  VALIDATION = 'VALIDATION',
  TRANSFORM = 'TRANSFORM',
  HTTP = 'HTTP',
  FILE_OP = 'FILE_OP',
}

export enum VariableScope {
  GLOBAL = 'GLOBAL',
  BLOCK = 'BLOCK',
  STAGE = 'STAGE',
}

export enum VariableDataType {
  STRING = 'STRING',
  FILE = 'FILE',
  JSON = 'JSON',
  NUMBER = 'NUMBER',
  BOOLEAN = 'BOOLEAN',
  ARRAY = 'ARRAY',
}

export enum ValidationLanguage {
  GOLANG = 'GOLANG',
  PYTHON = 'PYTHON',
  TYPESCRIPT = 'TYPESCRIPT',
}

export enum ConnectionType {
  DATA = 'DATA',
  CONTROL = 'CONTROL',
  CONDITIONAL = 'CONDITIONAL',
}

export enum ConditionType {
  EQUALS = 'EQUALS',
  NOT_EQUALS = 'NOT_EQUALS',
  CONTAINS = 'CONTAINS',
  REGEX = 'REGEX',
  EXPRESSION = 'EXPRESSION',
}

export enum LoopType {
  FOR_EACH = 'FOR_EACH',
  WHILE = 'WHILE',
  FOR_COUNT = 'FOR_COUNT',
}

export enum ErrorType {
  ALL = 'ALL',
  TIMEOUT = 'TIMEOUT',
  VALIDATION = 'VALIDATION',
  EXECUTION = 'EXECUTION',
  NETWORK = 'NETWORK',
}

export enum HandlerType {
  RETRY = 'RETRY',
  FALLBACK = 'FALLBACK',
  SKIP = 'SKIP',
  STOP = 'STOP',
  BRANCH = 'BRANCH',
}

export enum ExecutionStatus {
  PENDING = 'PENDING',
  RUNNING = 'RUNNING',
  SUCCESS = 'SUCCESS',
  FAILED = 'FAILED',
  CANCELLED = 'CANCELLED',
  PAUSED = 'PAUSED',
}

export enum StageExecutionStatus {
  PENDING = 'PENDING',
  RUNNING = 'RUNNING',
  SUCCESS = 'SUCCESS',
  FAILED = 'FAILED',
  SKIPPED = 'SKIPPED',
  RETRYING = 'RETRYING',
}

export enum TriggerType {
  MANUAL = 'MANUAL',
  SCHEDULED = 'SCHEDULED',
  WEBHOOK = 'WEBHOOK',
  API = 'API',
}

export enum CheckpointType {
  BLOCK_START = 'BLOCK_START',
  BLOCK_END = 'BLOCK_END',
  STAGE_COMPLETE = 'STAGE_COMPLETE',
}

export enum FailureAction {
  STOP = 'STOP',
  RETRY = 'RETRY',
  CONTINUE = 'CONTINUE',
  BRANCH = 'BRANCH',
}

export enum TransformType {
  JSON_PARSE = 'JSON_PARSE',
  JSON_STRINGIFY = 'JSON_STRINGIFY',
  REGEX_EXTRACT = 'REGEX_EXTRACT',
  TEMPLATE = 'TEMPLATE',
}

export enum FileOperation {
  READ = 'READ',
  WRITE = 'WRITE',
  APPEND = 'APPEND',
  DELETE = 'DELETE',
  COPY = 'COPY',
  MOVE = 'MOVE',
}
```

---

## Foreign Key Relationships

```
Pipeline
    ├── ExecutionBlock (1:N)
    │       ├── Stage (1:N)
    │       ├── ConditionalBranch (1:N)
    │       ├── LoopConstruct (1:N)
    │       └── ErrorHandler (1:N)
    ├── BlockConnection (1:N)
    ├── PipelineVariable (1:N)
    └── PipelineExecution (1:N)
            ├── StageExecution (1:N)
            └── ExecutionCheckpoint (1:N)

PromptTemplate (standalone, linked by ProjectId)
ValidationScript (standalone, linked by ProjectId)
```

---

## Migration Script

```sql
-- Migration: 001_automation_pipeline_schema.sql
-- Created: 2026-01-30

PRAGMA foreign_keys = ON;

-- Prompt templates
CREATE TABLE IF NOT EXISTS PromptTemplate (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL,
    FolderPath TEXT NOT NULL,
    FileName TEXT NOT NULL,
    Content TEXT NOT NULL,
    Metadata TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_prompt_project_folder 
    ON PromptTemplate(ProjectId, FolderPath);
CREATE INDEX IF NOT EXISTS idx_prompt_filename 
    ON PromptTemplate(FileName);

-- Pipeline definition
CREATE TABLE IF NOT EXISTS Pipeline (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL,
    Name TEXT NOT NULL,
    Description TEXT,
    ExecutionMode TEXT NOT NULL CHECK(ExecutionMode IN ('SEQUENTIAL', 'PARALLEL', 'HYBRID')),
    IsActive INTEGER DEFAULT 1,
    GlobalVariables TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_pipeline_project ON Pipeline(ProjectId);
CREATE INDEX IF NOT EXISTS idx_pipeline_active ON Pipeline(IsActive);

-- Execution blocks
CREATE TABLE IF NOT EXISTS ExecutionBlock (
    Id TEXT PRIMARY KEY,
    PipelineId TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
    Name TEXT NOT NULL,
    Description TEXT,
    ExecutionOrder INTEGER NOT NULL,
    ParallelGroup INTEGER,
    CanvasX REAL,
    CanvasY REAL,
    CanvasWidth REAL DEFAULT 200,
    CanvasHeight REAL DEFAULT 150,
    IsCollapsed INTEGER DEFAULT 0,
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_block_pipeline ON ExecutionBlock(PipelineId);
CREATE INDEX IF NOT EXISTS idx_block_order ON ExecutionBlock(PipelineId, ExecutionOrder);
CREATE INDEX IF NOT EXISTS idx_block_parallel ON ExecutionBlock(ParallelGroup);

-- Stages within blocks
CREATE TABLE IF NOT EXISTS Stage (
    Id TEXT PRIMARY KEY,
    BlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id) ON DELETE CASCADE,
    Name TEXT NOT NULL,
    StageType TEXT NOT NULL CHECK(StageType IN ('PROMPT', 'CODE_GEN', 'SEARCH', 'VALIDATION', 'TRANSFORM', 'HTTP', 'FILE_OP')),
    ExecutionOrder INTEGER NOT NULL,
    Config TEXT NOT NULL,
    InputBindings TEXT,
    OutputVariable TEXT,
    TimeoutSeconds INTEGER DEFAULT 300,
    RetryConfig TEXT,
    IsEnabled INTEGER DEFAULT 1,
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_stage_block ON Stage(BlockId);
CREATE INDEX IF NOT EXISTS idx_stage_order ON Stage(BlockId, ExecutionOrder);
CREATE INDEX IF NOT EXISTS idx_stage_type ON Stage(StageType);

-- Pipeline variables
CREATE TABLE IF NOT EXISTS PipelineVariable (
    Id TEXT PRIMARY KEY,
    PipelineId TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
    Name TEXT NOT NULL,
    Scope TEXT NOT NULL CHECK(Scope IN ('GLOBAL', 'BLOCK', 'STAGE')),
    DataType TEXT NOT NULL CHECK(DataType IN ('STRING', 'FILE', 'JSON', 'NUMBER', 'BOOLEAN', 'ARRAY')),
    DefaultValue TEXT,
    Description TEXT,
    IsRequired INTEGER DEFAULT 0,
    ValidationPattern TEXT,
    CreatedAt TEXT NOT NULL,
    UNIQUE(PipelineId, Name)
);

CREATE INDEX IF NOT EXISTS idx_variable_pipeline ON PipelineVariable(PipelineId);
CREATE INDEX IF NOT EXISTS idx_variable_scope ON PipelineVariable(Scope);

-- Validation scripts
CREATE TABLE IF NOT EXISTS ValidationScript (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL,
    Name TEXT NOT NULL,
    Description TEXT,
    Language TEXT NOT NULL CHECK(Language IN ('GOLANG', 'PYTHON', 'TYPESCRIPT')),
    SourceCode TEXT NOT NULL,
    EntryFunction TEXT DEFAULT 'validate',
    FolderPath TEXT,
    InputSchema TEXT,
    OutputSchema TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_script_project ON ValidationScript(ProjectId);
CREATE INDEX IF NOT EXISTS idx_script_language ON ValidationScript(Language);

-- Block connections
CREATE TABLE IF NOT EXISTS BlockConnection (
    Id TEXT PRIMARY KEY,
    PipelineId TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
    SourceBlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id) ON DELETE CASCADE,
    TargetBlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id) ON DELETE CASCADE,
    ConnectionType TEXT NOT NULL CHECK(ConnectionType IN ('DATA', 'CONTROL', 'CONDITIONAL')),
    Condition TEXT,
    OutputMapping TEXT,
    SourceHandle TEXT DEFAULT 'bottom',
    TargetHandle TEXT DEFAULT 'top',
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_connection_pipeline ON BlockConnection(PipelineId);
CREATE INDEX IF NOT EXISTS idx_connection_source ON BlockConnection(SourceBlockId);
CREATE INDEX IF NOT EXISTS idx_connection_target ON BlockConnection(TargetBlockId);

-- Conditional branching
CREATE TABLE IF NOT EXISTS ConditionalBranch (
    Id TEXT PRIMARY KEY,
    BlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id) ON DELETE CASCADE,
    Name TEXT,
    Condition TEXT NOT NULL,
    ConditionType TEXT NOT NULL CHECK(ConditionType IN ('EQUALS', 'NOT_EQUALS', 'CONTAINS', 'REGEX', 'EXPRESSION')),
    TrueTargetBlockId TEXT REFERENCES ExecutionBlock(Id),
    FalseTargetBlockId TEXT REFERENCES ExecutionBlock(Id),
    Priority INTEGER DEFAULT 0,
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_branch_block ON ConditionalBranch(BlockId);
CREATE INDEX IF NOT EXISTS idx_branch_priority ON ConditionalBranch(BlockId, Priority);

-- Loop constructs
CREATE TABLE IF NOT EXISTS LoopConstruct (
    Id TEXT PRIMARY KEY,
    BlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id) ON DELETE CASCADE,
    LoopType TEXT NOT NULL CHECK(LoopType IN ('FOR_EACH', 'WHILE', 'FOR_COUNT')),
    IteratorVariable TEXT NOT NULL,
    IndexVariable TEXT,
    SourceVariable TEXT,
    Condition TEXT,
    MaxIterations INTEGER DEFAULT 100,
    LoopBodyBlockId TEXT REFERENCES ExecutionBlock(Id),
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_loop_block ON LoopConstruct(BlockId);

-- Error handlers
CREATE TABLE IF NOT EXISTS ErrorHandler (
    Id TEXT PRIMARY KEY,
    BlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id) ON DELETE CASCADE,
    ErrorType TEXT NOT NULL CHECK(ErrorType IN ('ALL', 'TIMEOUT', 'VALIDATION', 'EXECUTION', 'NETWORK')),
    HandlerType TEXT NOT NULL CHECK(HandlerType IN ('RETRY', 'FALLBACK', 'SKIP', 'STOP', 'BRANCH')),
    HandlerConfig TEXT,
    FallbackBlockId TEXT REFERENCES ExecutionBlock(Id),
    MaxRetries INTEGER DEFAULT 3,
    NotifyOnError INTEGER DEFAULT 0,
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_error_block ON ErrorHandler(BlockId);
CREATE INDEX IF NOT EXISTS idx_error_type ON ErrorHandler(ErrorType);

-- Pipeline execution history
CREATE TABLE IF NOT EXISTS PipelineExecution (
    Id TEXT PRIMARY KEY,
    PipelineId TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
    Status TEXT NOT NULL CHECK(Status IN ('PENDING', 'RUNNING', 'SUCCESS', 'FAILED', 'CANCELLED', 'PAUSED')),
    StartedAt TEXT,
    CompletedAt TEXT,
    DurationMs INTEGER,
    TriggerType TEXT NOT NULL CHECK(TriggerType IN ('MANUAL', 'SCHEDULED', 'WEBHOOK', 'API')),
    TriggeredBy TEXT,
    InputVariables TEXT,
    OutputVariables TEXT,
    ErrorMessage TEXT,
    ErrorStackTrace TEXT,
    TotalStages INTEGER,
    CompletedStages INTEGER DEFAULT 0,
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_execution_pipeline ON PipelineExecution(PipelineId);
CREATE INDEX IF NOT EXISTS idx_execution_status ON PipelineExecution(Status);
CREATE INDEX IF NOT EXISTS idx_execution_date ON PipelineExecution(StartedAt DESC);

-- Stage execution logs
CREATE TABLE IF NOT EXISTS StageExecution (
    Id TEXT PRIMARY KEY,
    PipelineExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id) ON DELETE CASCADE,
    StageId TEXT NOT NULL REFERENCES Stage(Id),
    BlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id),
    Status TEXT NOT NULL CHECK(Status IN ('PENDING', 'RUNNING', 'SUCCESS', 'FAILED', 'SKIPPED', 'RETRYING')),
    AttemptNumber INTEGER DEFAULT 1,
    Input TEXT,
    Output TEXT,
    OutputFilePath TEXT,
    StartedAt TEXT,
    CompletedAt TEXT,
    DurationMs INTEGER,
    TokensUsed INTEGER,
    Model TEXT,
    ErrorMessage TEXT,
    ErrorCode TEXT,
    ValidationResult TEXT,
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_stage_exec_pipeline ON StageExecution(PipelineExecutionId);
CREATE INDEX IF NOT EXISTS idx_stage_exec_stage ON StageExecution(StageId);
CREATE INDEX IF NOT EXISTS idx_stage_exec_status ON StageExecution(Status);

-- Execution checkpoints
CREATE TABLE IF NOT EXISTS ExecutionCheckpoint (
    Id TEXT PRIMARY KEY,
    PipelineExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id) ON DELETE CASCADE,
    BlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id),
    StageId TEXT REFERENCES Stage(Id),
    CheckpointType TEXT NOT NULL CHECK(CheckpointType IN ('BLOCK_START', 'BLOCK_END', 'STAGE_COMPLETE')),
    VariableSnapshot TEXT NOT NULL,
    FileManifest TEXT,
    CanRollback INTEGER DEFAULT 1,
    CreatedAt TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_checkpoint_execution ON ExecutionCheckpoint(PipelineExecutionId);
CREATE INDEX IF NOT EXISTS idx_checkpoint_block ON ExecutionCheckpoint(BlockId);
```

---

## Related Specs

- [Prompt Import System](./02-prompt-import-system.md)
- [Variable Registry](./03-variable-registry.md)
- [Stage Executor](./04-stage-executor.md)
