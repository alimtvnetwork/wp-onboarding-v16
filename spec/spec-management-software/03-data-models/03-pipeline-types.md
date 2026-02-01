# Pipeline Types

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Pipeline

```typescript
interface Pipeline {
  readonly id: string;
  projectId: string;
  name: string;
  description: string | null;
  status: PipelineStatus;
  blocks: Block[];
  connections: BlockConnection[];
  variables: PipelineVariable[];
  settings: PipelineSettings;
  metadata: PipelineMetadata;
  createdAt: string;
  updatedAt: string;
  createdBy: string;
}

type PipelineStatus = 
  | 'draft'
  | 'active'
  | 'archived'
  | 'disabled';

interface PipelineSettings {
  maxParallel: number;
  defaultTimeout: number;
  retryPolicy: RetryPolicy;
  enableRES: boolean;  // Resilient Execution System
}

interface PipelineMetadata {
  version: string;
  tags: string[];
  lastExecutedAt: string | null;
  executionCount: number;
  averageDuration: number;
}
```

---

## Block

```typescript
interface Block {
  readonly id: string;
  pipelineId: string;
  name: string;
  type: BlockType;
  stages: Stage[];
  position: Position;
  executionMode: ExecutionMode;
  settings: BlockSettings;
}

type BlockType = 
  | 'standard'
  | 'parallel'
  | 'conditional'
  | 'loop'
  | 'error_handler';

interface Position {
  x: number;
  y: number;
}

type ExecutionMode = 'sequential' | 'parallel';

interface BlockSettings {
  timeout: number;
  retryCount: number;
  onError: ErrorAction;
  condition?: ConditionExpression;
}

type ErrorAction = 'fail' | 'skip' | 'retry' | 'fallback';
```

---

## Stage

```typescript
interface Stage {
  readonly id: string;
  blockId: string;
  name: string;
  type: StageType;
  order: number;
  config: StageConfig;
  inputs: StageInput[];
  outputs: StageOutput[];
  status: StageStatus;
}

type StageType = 
  | 'PROMPT'
  | 'CODE_GEN'
  | 'SEARCH'
  | 'VALIDATION'
  | 'TRANSFORM'
  | 'HTTP'
  | 'FILE_OP';

type StageStatus = 
  | 'pending'
  | 'running'
  | 'success'
  | 'failed'
  | 'skipped'
  | 'cancelled';

interface StageInput {
  name: string;
  source: InputSource;
  required: boolean;
  default?: unknown;
}

type InputSource = 
  | { type: 'literal'; value: unknown }
  | { type: 'variable'; path: string }
  | { type: 'stage_output'; stageId: string; outputName: string }
  | { type: 'block_output'; blockId: string; stageName: string; outputName: string };

interface StageOutput {
  name: string;
  type: OutputType;
  schema?: JSONSchema;
}

type OutputType = 'text' | 'json' | 'file' | 'binary' | 'none';
```

---

## Stage Configurations

```typescript
type StageConfig = 
  | PromptStageConfig
  | CodeGenStageConfig
  | SearchStageConfig
  | ValidationStageConfig
  | TransformStageConfig
  | HttpStageConfig
  | FileOpStageConfig;

interface PromptStageConfig {
  type: 'PROMPT';
  templateId: string;
  additionalContext?: string;
  model?: string;
  temperature?: number;
  maxTokens?: number;
  outputFormat: 'text' | 'json' | 'markdown';
}

interface CodeGenStageConfig {
  type: 'CODE_GEN';
  language: CodeLanguage;
  taskDescription: string;
  model?: string;
  outputPath?: string;
  executeAfterGeneration: boolean;
}

type CodeLanguage = 
  | 'golang'
  | 'python'
  | 'typescript'
  | 'javascript'
  | 'html'
  | 'css'
  | 'sql';

interface SearchStageConfig {
  type: 'SEARCH';
  query: string;
  maxResults: number;
  minConfidence?: number;
  sources?: string[];
  excludeDomains?: string[];
}

interface ValidationStageConfig {
  type: 'VALIDATION';
  scriptId: string;
  targetVariable: string;
  onFailure: FailureAction;
}

type FailureAction = 'fail' | 'warn' | 'skip' | 'branch';

interface TransformStageConfig {
  type: 'TRANSFORM';
  expression: string;  // JSONata or similar
  inputSchema?: JSONSchema;
  outputSchema?: JSONSchema;
}

interface HttpStageConfig {
  type: 'HTTP';
  method: HttpMethod;
  url: string;
  headers?: Record<string, string>;
  body?: string;
  timeout: number;
  validateStatus?: number[];
}

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface FileOpStageConfig {
  type: 'FILE_OP';
  operation: FileOperation;
  path: string;
  content?: string;
  encoding?: string;
}

type FileOperation = 'read' | 'write' | 'append' | 'delete' | 'copy' | 'move';
```

---

## Connections

```typescript
interface BlockConnection {
  id: string;
  sourceBlockId: string;
  targetBlockId: string;
  type: ConnectionType;
  condition?: ConditionExpression;
  dataMapping?: DataMapping[];
}

type ConnectionType = 
  | 'data'
  | 'control'
  | 'conditional'
  | 'error';

interface DataMapping {
  sourceOutput: string;
  targetInput: string;
  transform?: string;
}

interface ConditionExpression {
  type: 'simple' | 'complex';
  expression: string;
  variables: string[];
}
```

---

## Variables

```typescript
interface PipelineVariable {
  id: string;
  name: string;
  scope: VariableScope;
  type: VariableType;
  value: unknown;
  selectionMode?: VariableSelectionMode;
  values?: VariableValue[];
}

type VariableScope = 'pipeline' | 'block' | 'stage';

type VariableType = 'string' | 'number' | 'boolean' | 'array' | 'object';

type VariableSelectionMode = 'sequential' | 'random' | 'weighted';

interface VariableValue {
  value: unknown;
  weight?: number;
}
```

---

## Execution

```typescript
interface PipelineExecution {
  id: string;
  pipelineId: string;
  status: ExecutionStatus;
  startedAt: string;
  completedAt: string | null;
  duration: number | null;
  blockExecutions: BlockExecution[];
  variables: Record<string, unknown>;
  error?: ExecutionError;
  triggeredBy: string;
}

type ExecutionStatus = 
  | 'queued'
  | 'running'
  | 'completed'
  | 'failed'
  | 'cancelled'
  | 'paused';

interface BlockExecution {
  id: string;
  blockId: string;
  status: ExecutionStatus;
  stageExecutions: StageExecution[];
  startedAt: string;
  completedAt: string | null;
  duration: number | null;
}

interface StageExecution {
  id: string;
  stageId: string;
  status: StageStatus;
  input: Record<string, unknown>;
  output: Record<string, unknown>;
  startedAt: string;
  completedAt: string | null;
  duration: number | null;
  attempts: number;
  error?: ExecutionError;
  metrics: StageMetrics;
}

interface StageMetrics {
  tokensUsed?: number;
  model?: string;
  bytesProcessed?: number;
  httpStatus?: number;
}

interface ExecutionError {
  code: string;
  message: string;
  details?: Record<string, unknown>;
  retryable: boolean;
  stack?: string;
}
```

---

## Retry Policy

```typescript
interface RetryPolicy {
  maxAttempts: number;
  strategy: RetryStrategy;
  initialDelay: number;
  maxDelay: number;
  multiplier: number;
  jitter: boolean;
}

type RetryStrategy = 
  | 'fixed'
  | 'linear'
  | 'exponential'
  | 'fibonacci';
```
