# Component: Stage Executor

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 2.0.0  
**Status:** Complete  
**Phase:** 2 - Stage Engine  

---

## Summary

Core execution engine for individual pipeline stages. Handles stage lifecycle, type-specific execution strategies, timeout management, retry logic, and integration with the Resilient Execution System (RES).

---

## User Stories

- As a user, I want each stage type to execute its specific logic correctly
- As a user, I want stages to timeout if they take too long
- As a user, I want failed stages to retry automatically with backoff
- As a user, I want to see real-time progress during stage execution
- As a user, I want stages to integrate with the RES for fault tolerance

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Stage Executor                              │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │   Stage     │  │   Type      │  │   Result    │              │
│  │   Queue     │──│   Router    │──│   Handler   │              │
│  └─────────────┘  └──────┬──────┘  └─────────────┘              │
│                          │                                       │
│         ┌────────────────┼────────────────┐                      │
│         ▼                ▼                ▼                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │   PROMPT    │  │   SEARCH    │  │ VALIDATION  │              │
│  │   Handler   │  │   Handler   │  │   Handler   │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
│         │                │                │                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │  CODE_GEN   │  │  TRANSFORM  │  │    HTTP     │              │
│  │   Handler   │  │   Handler   │  │   Handler   │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
│         │                                │                       │
│  ┌─────────────┐                  ┌─────────────┐               │
│  │   FILE_OP   │                  │   CUSTOM    │               │
│  │   Handler   │                  │   Handler   │               │
│  └─────────────┘                  └─────────────┘               │
│                          │                                       │
│                          ▼                                       │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              Retry & Timeout Manager                       │  │
│  └───────────────────────────────────────────────────────────┘  │
│                          │                                       │
│                          ▼                                       │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │           Resilient Execution System (RES)                 │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Stage Lifecycle

```
┌─────────┐     ┌─────────┐     ┌─────────┐     ┌─────────┐
│ PENDING │────▶│ RUNNING │────▶│ SUCCESS │     │  DONE   │
└─────────┘     └────┬────┘     └─────────┘     └─────────┘
                     │                ▲
                     ▼                │
               ┌─────────┐            │
               │ FAILED  │────────────┘
               └────┬────┘     (retry successful)
                    │
                    ▼
               ┌─────────┐
               │RETRYING │
               └────┬────┘
                    │
            ┌───────┴───────┐
            ▼               ▼
       ┌─────────┐    ┌─────────┐
       │ SUCCESS │    │ FAILED  │
       └─────────┘    └─────────┘
                      (max retries)
                           │
                           ▼
                      ┌─────────┐
                      │ SKIPPED │
                      └─────────┘
                      (if configured)
```

---

## Core Interfaces

### Stage Executor

```typescript
interface StageExecutor {
  execute(
    stage: Stage,
    context: ExecutionContext
  ): Promise<StageResult>;
  
  cancel(stageExecutionId: string): Promise<void>;
  
  getStatus(stageExecutionId: string): StageExecutionStatus;
}

interface ExecutionContext {
  pipelineExecutionId: string;
  blockId: string;
  variables: VariableContext;
  previousStageResult?: StageResult;
  config: ExecutorConfig;
  eventEmitter: StageEventEmitter;
}

interface ExecutorConfig {
  defaultTimeout: number;          // Default: 300000 (5 min)
  maxRetries: number;              // Default: 3
  retryBackoffBase: number;        // Default: 1000 (1s)
  retryBackoffMultiplier: number;  // Default: 2
  enableRES: boolean;              // Default: true
}
```

### Stage Result

```typescript
interface StageResult {
  stageId: string;
  status: StageExecutionStatus;
  output: StageOutput;
  metrics: StageMetrics;
  error?: StageError;
}

interface StageOutput {
  type: OutputType;
  text?: string;                   // Text output
  json?: unknown;                  // Structured data
  filePath?: string;               // File output path
  fileContent?: Buffer;            // Raw file content
  metadata?: Record<string, unknown>;
}

enum OutputType {
  TEXT = 'TEXT',
  JSON = 'JSON',
  FILE = 'FILE',
  BINARY = 'BINARY',
  NONE = 'NONE',
}

interface StageMetrics {
  startedAt: string;
  completedAt: string;
  durationMs: number;
  attempts: number;
  tokensUsed?: number;
  model?: string;
  bytesProcessed?: number;
}

interface StageError {
  code: StageErrorCode;
  message: string;
  details?: unknown;
  retryable: boolean;
  stackTrace?: string;
}

enum StageErrorCode {
  TIMEOUT = 'TIMEOUT',
  EXECUTION_FAILED = 'EXECUTION_FAILED',
  VALIDATION_FAILED = 'VALIDATION_FAILED',
  NETWORK_ERROR = 'NETWORK_ERROR',
  AUTH_ERROR = 'AUTH_ERROR',
  RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND',
  RATE_LIMITED = 'RATE_LIMITED',
  INVALID_CONFIG = 'INVALID_CONFIG',
  VARIABLE_RESOLUTION = 'VARIABLE_RESOLUTION',
  CANCELLED = 'CANCELLED',
}
```

---

## Stage Handlers

### Handler Interface

```typescript
interface StageHandler<TConfig = unknown> {
  readonly stageType: StageType;
  
  validate(config: TConfig): ValidationResult;
  
  execute(
    config: TConfig,
    context: ExecutionContext
  ): Promise<StageOutput>;
  
  estimateTokens?(config: TConfig): number;
  
  getRequiredVariables(config: TConfig): string[];
}
```

### PROMPT Handler

Executes prompt templates with AI models.

```typescript
interface PromptStageConfig {
  promptTemplateId: string;
  additionalContext?: string;
  model?: string;
  temperature?: number;
  maxTokens?: number;
  topP?: number;
  systemPrompt?: string;
  outputFormat: OutputFormat;
  outputFilePath?: string;
  voiceInputEnabled?: boolean;
}

enum OutputFormat {
  TEXT = 'TEXT',
  JSON = 'JSON',
  FILE = 'FILE',
  MARKDOWN = 'MARKDOWN',
  CODE = 'CODE',
}

class PromptStageHandler implements StageHandler<PromptStageConfig> {
  readonly stageType = StageType.PROMPT;
  
  async execute(
    config: PromptStageConfig,
    context: ExecutionContext
  ): Promise<StageOutput> {
    // 1. Load prompt template from database
    const template = await this.loadTemplate(config.promptTemplateId);
    
    // 2. Resolve variables in template
    const resolvedPrompt = substituteVariables(
      template.content,
      context.variables
    );
    
    // 3. Append additional context
    const fullPrompt = config.additionalContext
      ? `${resolvedPrompt}\n\n${config.additionalContext}`
      : resolvedPrompt;
    
    // 4. Select model based on config or default
    const model = config.model || this.selectModelForPrompt(template);
    
    // 5. Execute AI call
    const response = await this.aiClient.complete({
      model,
      prompt: fullPrompt,
      systemPrompt: config.systemPrompt,
      temperature: config.temperature ?? 0.7,
      maxTokens: config.maxTokens ?? 4096,
      topP: config.topP,
    });
    
    // 6. Format output based on outputFormat
    return this.formatOutput(response, config);
  }
  
  private selectModelForPrompt(template: PromptTemplate): string {
    // Model selection from template metadata or category default
    const metadata = JSON.parse(template.Metadata || '{}');
    return metadata.model || 'llama-3';
  }
}
```

### CODE_GEN Handler

Generates code using specialized coding models.

```typescript
interface CodeGenStageConfig {
  language: CodeLanguage;
  taskDescription: string;
  model?: string;
  outputPath?: string;
  executeAfterGeneration: boolean;
  contextFiles?: string[];
  codeStyle?: CodeStyle;
}

enum CodeLanguage {
  GOLANG = 'GOLANG',
  PYTHON = 'PYTHON',
  TYPESCRIPT = 'TYPESCRIPT',
  JAVASCRIPT = 'JAVASCRIPT',
  HTML = 'HTML',
  CSS = 'CSS',
  SQL = 'SQL',
}

interface CodeStyle {
  indentation: 'tabs' | 'spaces';
  indentSize: number;
  lineEnding: 'lf' | 'crlf';
  maxLineLength?: number;
}

class CodeGenStageHandler implements StageHandler<CodeGenStageConfig> {
  readonly stageType = StageType.CODE_GEN;
  
  async execute(
    config: CodeGenStageConfig,
    context: ExecutionContext
  ): Promise<StageOutput> {
    // 1. Gather context from referenced files
    const fileContexts = await this.loadContextFiles(config.contextFiles);
    
    // 2. Build code generation prompt
    const prompt = this.buildCodePrompt(config, fileContexts);
    
    // 3. Use specialized coding model
    const model = config.model || 'codellama';
    
    // 4. Generate code
    const response = await this.aiClient.complete({
      model,
      prompt,
      temperature: 0.2,  // Lower for code
      maxTokens: 8192,
    });
    
    // 5. Extract code from response
    const code = this.extractCode(response.text, config.language);
    
    // 6. Optionally write to file
    if (config.outputPath) {
      await this.writeCodeFile(config.outputPath, code, config.codeStyle);
    }
    
    // 7. Optionally execute (if safe language)
    if (config.executeAfterGeneration) {
      const execResult = await this.executeCode(code, config.language);
      return {
        type: OutputType.JSON,
        json: { code, executionResult: execResult },
        filePath: config.outputPath,
      };
    }
    
    return {
      type: OutputType.TEXT,
      text: code,
      filePath: config.outputPath,
    };
  }
}
```

### SEARCH Handler

Performs web searches with source validation.

```typescript
interface SearchStageConfig {
  query: string;
  maxResults: number;
  minConfidence?: number;
  sources?: string[];
  excludeDomains?: string[];
  searchEngine?: SearchEngine;
  recencyBias?: number;
  includeRawHtml?: boolean;
}

enum SearchEngine {
  GOOGLE = 'GOOGLE',
  DUCKDUCKGO = 'DUCKDUCKGO',
  BING = 'BING',
  MULTI = 'MULTI',  // Query multiple engines
}

interface SearchResult {
  title: string;
  url: string;
  snippet: string;
  content?: string;
  confidence: number;
  source: string;
  publishedDate?: string;
  domain: string;
}

class SearchStageHandler implements StageHandler<SearchStageConfig> {
  readonly stageType = StageType.SEARCH;
  
  async execute(
    config: SearchStageConfig,
    context: ExecutionContext
  ): Promise<StageOutput> {
    // 1. Resolve variables in query
    const resolvedQuery = substituteVariables(config.query, context.variables);
    
    // 2. Execute search
    const rawResults = await this.searchClient.search({
      query: resolvedQuery,
      engine: config.searchEngine || SearchEngine.MULTI,
      maxResults: config.maxResults * 2,  // Over-fetch for filtering
    });
    
    // 3. Filter by domain whitelist/blacklist
    const filteredResults = this.filterByDomain(
      rawResults,
      config.sources,
      config.excludeDomains
    );
    
    // 4. Score and rank results
    const scoredResults = await this.scoreResults(filteredResults);
    
    // 5. Filter by confidence threshold
    const confidentResults = scoredResults
      .filter(r => r.confidence >= (config.minConfidence || 0.5))
      .slice(0, config.maxResults);
    
    // 6. Optionally fetch full content
    if (config.includeRawHtml) {
      await this.enrichWithContent(confidentResults);
    }
    
    return {
      type: OutputType.JSON,
      json: {
        query: resolvedQuery,
        results: confidentResults,
        totalFound: rawResults.length,
        filtered: confidentResults.length,
      },
      metadata: {
        searchEngine: config.searchEngine,
        executedAt: new Date().toISOString(),
      },
    };
  }
}
```

### VALIDATION Handler

Executes custom validation scripts.

```typescript
interface ValidationStageConfig {
  scriptId: string;
  targetVariable: string;
  onFailure: FailureAction;
  maxRetries?: number;
  failureBranchId?: string;
  customArgs?: Record<string, unknown>;
}

class ValidationStageHandler implements StageHandler<ValidationStageConfig> {
  readonly stageType = StageType.VALIDATION;
  
  async execute(
    config: ValidationStageConfig,
    context: ExecutionContext
  ): Promise<StageOutput> {
    // Delegate to Validation Runtime (see 05-validation-runtime.md)
    const runtime = new ValidationRuntime();
    
    // 1. Load validation script
    const script = await this.loadScript(config.scriptId);
    
    // 2. Resolve target variable
    const targetValue = resolveVariable(config.targetVariable, context.variables);
    
    // 3. Execute validation
    const result = await runtime.execute(script, {
      input: targetValue,
      args: config.customArgs,
      context: context.variables,
    });
    
    return {
      type: OutputType.JSON,
      json: {
        passed: result.passed,
        errors: result.errors,
        warnings: result.warnings,
        score: result.score,
        details: result.details,
      },
      metadata: {
        scriptId: config.scriptId,
        language: script.Language,
        duration: result.durationMs,
      },
    };
  }
}
```

### TRANSFORM Handler

Data transformation operations.

```typescript
interface TransformStageConfig {
  transformType: TransformType;
  inputVariable: string;
  expression?: string;
  template?: string;
  outputType?: OutputType;
  options?: TransformOptions;
}

enum TransformType {
  JSON_PARSE = 'JSON_PARSE',
  JSON_STRINGIFY = 'JSON_STRINGIFY',
  REGEX_EXTRACT = 'REGEX_EXTRACT',
  REGEX_REPLACE = 'REGEX_REPLACE',
  TEMPLATE = 'TEMPLATE',
  JMESPATH = 'JMESPATH',        // JSON query
  XPATH = 'XPATH',              // XML query
  MAP = 'MAP',                  // Array mapping
  FILTER = 'FILTER',            // Array filtering
  REDUCE = 'REDUCE',            // Array reduction
  SPLIT = 'SPLIT',
  JOIN = 'JOIN',
  TRIM = 'TRIM',
  CASE = 'CASE',                // upper/lower/title
}

interface TransformOptions {
  preserveNull: boolean;
  throwOnError: boolean;
  defaultValue?: unknown;
}

class TransformStageHandler implements StageHandler<TransformStageConfig> {
  readonly stageType = StageType.TRANSFORM;
  
  async execute(
    config: TransformStageConfig,
    context: ExecutionContext
  ): Promise<StageOutput> {
    // 1. Resolve input variable
    const input = resolveVariable(config.inputVariable, context.variables);
    
    // 2. Apply transformation
    const transformed = await this.applyTransform(
      input,
      config.transformType,
      config.expression,
      config.template,
      config.options
    );
    
    // 3. Determine output type
    const outputType = config.outputType || this.inferOutputType(transformed);
    
    return {
      type: outputType,
      [outputType === OutputType.JSON ? 'json' : 'text']: transformed,
    };
  }
  
  private applyTransform(
    input: unknown,
    type: TransformType,
    expression?: string,
    template?: string,
    options?: TransformOptions
  ): unknown {
    switch (type) {
      case TransformType.JSON_PARSE:
        return JSON.parse(input as string);
      
      case TransformType.JSON_STRINGIFY:
        return JSON.stringify(input, null, 2);
      
      case TransformType.REGEX_EXTRACT:
        const regex = new RegExp(expression!);
        const match = (input as string).match(regex);
        return match ? (match[1] || match[0]) : null;
      
      case TransformType.TEMPLATE:
        return substituteVariables(template!, { input });
      
      case TransformType.JMESPATH:
        return jmespath.search(input, expression!);
      
      case TransformType.MAP:
        return (input as unknown[]).map(item => 
          this.evaluateExpression(expression!, { item })
        );
      
      // ... other transformations
      
      default:
        throw exhaustiveCheck(type);
    }
  }
}
```

### HTTP Handler

External API calls.

```typescript
interface HttpStageConfig {
  url: string;
  method: HttpMethod;
  headers?: Record<string, string>;
  body?: string;
  bodyType?: BodyType;
  queryParams?: Record<string, string>;
  expectedStatus?: number[];
  timeout?: number;
  retryOn?: number[];
  auth?: HttpAuth;
  followRedirects?: boolean;
  validateSsl?: boolean;
}

enum HttpMethod {
  GET = 'GET',
  POST = 'POST',
  PUT = 'PUT',
  PATCH = 'PATCH',
  DELETE = 'DELETE',
  HEAD = 'HEAD',
  OPTIONS = 'OPTIONS',
}

enum BodyType {
  JSON = 'JSON',
  FORM = 'FORM',
  TEXT = 'TEXT',
  BINARY = 'BINARY',
  MULTIPART = 'MULTIPART',
}

interface HttpAuth {
  type: 'basic' | 'bearer' | 'apiKey' | 'oauth2';
  credentials: Record<string, string>;
}

class HttpStageHandler implements StageHandler<HttpStageConfig> {
  readonly stageType = StageType.HTTP;
  
  async execute(
    config: HttpStageConfig,
    context: ExecutionContext
  ): Promise<StageOutput> {
    // 1. Resolve variables in URL, headers, body
    const url = substituteVariables(config.url, context.variables);
    const headers = this.resolveHeaders(config.headers, context.variables);
    const body = config.body 
      ? substituteVariables(config.body, context.variables)
      : undefined;
    
    // 2. Add auth headers
    if (config.auth) {
      Object.assign(headers, this.buildAuthHeaders(config.auth));
    }
    
    // 3. Execute request
    const response = await this.httpClient.request({
      url,
      method: config.method,
      headers,
      body,
      timeout: config.timeout || 30000,
      followRedirects: config.followRedirects ?? true,
      validateSsl: config.validateSsl ?? true,
    });
    
    // 4. Validate status
    const expectedStatus = config.expectedStatus || [200, 201, 204];
    if (!expectedStatus.includes(response.status)) {
      throw new StageExecutionError({
        code: StageErrorCode.EXECUTION_FAILED,
        message: `Unexpected status: ${response.status}`,
        retryable: config.retryOn?.includes(response.status) ?? false,
      });
    }
    
    // 5. Parse response
    const contentType = response.headers['content-type'] || '';
    const isJson = contentType.includes('application/json');
    
    return {
      type: isJson ? OutputType.JSON : OutputType.TEXT,
      [isJson ? 'json' : 'text']: isJson 
        ? JSON.parse(response.body)
        : response.body,
      metadata: {
        status: response.status,
        headers: response.headers,
        timing: response.timing,
      },
    };
  }
}
```

### FILE_OP Handler

File system operations.

```typescript
interface FileOpStageConfig {
  operation: FileOperation;
  sourcePath?: string;
  destinationPath?: string;
  content?: string;
  encoding?: BufferEncoding;
  createDirectories?: boolean;
  overwrite?: boolean;
}

enum FileOperation {
  READ = 'READ',
  WRITE = 'WRITE',
  APPEND = 'APPEND',
  DELETE = 'DELETE',
  COPY = 'COPY',
  MOVE = 'MOVE',
  EXISTS = 'EXISTS',
  LIST = 'LIST',
  MKDIR = 'MKDIR',
}

class FileOpStageHandler implements StageHandler<FileOpStageConfig> {
  readonly stageType = StageType.FILE_OP;
  
  async execute(
    config: FileOpStageConfig,
    context: ExecutionContext
  ): Promise<StageOutput> {
    // Resolve paths
    const sourcePath = config.sourcePath
      ? substituteVariables(config.sourcePath, context.variables)
      : undefined;
    const destPath = config.destinationPath
      ? substituteVariables(config.destinationPath, context.variables)
      : undefined;
    
    switch (config.operation) {
      case FileOperation.READ:
        const content = await fs.readFile(sourcePath!, config.encoding || 'utf-8');
        return { type: OutputType.TEXT, text: content };
      
      case FileOperation.WRITE:
        const writeContent = config.content
          ? substituteVariables(config.content, context.variables)
          : context.variables.prev?.output;
        
        if (config.createDirectories) {
          await fs.mkdir(path.dirname(destPath!), { recursive: true });
        }
        
        await fs.writeFile(destPath!, writeContent, config.encoding);
        return { type: OutputType.FILE, filePath: destPath };
      
      case FileOperation.LIST:
        const files = await fs.readdir(sourcePath!);
        return { type: OutputType.JSON, json: files };
      
      // ... other operations
      
      default:
        throw exhaustiveCheck(config.operation);
    }
  }
}
```

---

## Timeout Management

```typescript
interface TimeoutManager {
  execute<T>(
    operation: () => Promise<T>,
    timeoutMs: number,
    onTimeout?: () => void
  ): Promise<T>;
}

class StageTimeoutManager implements TimeoutManager {
  async execute<T>(
    operation: () => Promise<T>,
    timeoutMs: number,
    onTimeout?: () => void
  ): Promise<T> {
    const timeoutPromise = new Promise<never>((_, reject) => {
      const timer = setTimeout(() => {
        onTimeout?.();
        reject(new StageExecutionError({
          code: StageErrorCode.TIMEOUT,
          message: `Stage execution timed out after ${timeoutMs}ms`,
          retryable: true,
        }));
      }, timeoutMs);
      
      // Clean up timer if operation completes
      operation.finally(() => clearTimeout(timer));
    });
    
    return Promise.race([operation(), timeoutPromise]);
  }
}
```

---

## Retry Logic

```typescript
interface RetryConfig {
  maxRetries: number;
  backoffBase: number;       // Base delay in ms
  backoffMultiplier: number; // Exponential factor
  maxBackoff: number;        // Maximum delay
  retryableErrors: StageErrorCode[];
  jitter: boolean;           // Add randomness to prevent thundering herd
}

class RetryManager {
  private readonly defaultConfig: RetryConfig = {
    maxRetries: 3,
    backoffBase: 1000,
    backoffMultiplier: 2,
    maxBackoff: 30000,
    retryableErrors: [
      StageErrorCode.TIMEOUT,
      StageErrorCode.NETWORK_ERROR,
      StageErrorCode.RATE_LIMITED,
    ],
    jitter: true,
  };
  
  async executeWithRetry<T>(
    operation: () => Promise<T>,
    config: Partial<RetryConfig> = {},
    onRetry?: (attempt: number, error: StageError) => void
  ): Promise<T> {
    const cfg = { ...this.defaultConfig, ...config };
    let lastError: StageError | undefined;
    
    for (let attempt = 1; attempt <= cfg.maxRetries + 1; attempt++) {
      try {
        return await operation();
      } catch (error) {
        lastError = this.toStageError(error);
        
        // Check if error is retryable
        if (!lastError.retryable || 
            !cfg.retryableErrors.includes(lastError.code) ||
            attempt > cfg.maxRetries) {
          throw error;
        }
        
        // Calculate backoff delay
        const delay = this.calculateBackoff(attempt, cfg);
        
        // Notify retry callback
        onRetry?.(attempt, lastError);
        
        // Wait before retry
        await this.sleep(delay);
      }
    }
    
    throw lastError;
  }
  
  private calculateBackoff(attempt: number, config: RetryConfig): number {
    let delay = config.backoffBase * Math.pow(config.backoffMultiplier, attempt - 1);
    delay = Math.min(delay, config.maxBackoff);
    
    if (config.jitter) {
      // Add ±25% jitter
      delay = delay * (0.75 + Math.random() * 0.5);
    }
    
    return Math.floor(delay);
  }
}
```

---

## Event Emission

```typescript
interface StageEventEmitter {
  emit(event: StageEvent): void;
  on(eventType: StageEventType, handler: StageEventHandler): void;
  off(eventType: StageEventType, handler: StageEventHandler): void;
}

enum StageEventType {
  STARTED = 'STARTED',
  PROGRESS = 'PROGRESS',
  COMPLETED = 'COMPLETED',
  FAILED = 'FAILED',
  RETRYING = 'RETRYING',
  CANCELLED = 'CANCELLED',
  LOG = 'LOG',
}

interface StageEvent {
  type: StageEventType;
  stageId: string;
  stageExecutionId: string;
  timestamp: string;
  data: StageEventData;
}

interface StageEventData {
  message?: string;
  progress?: number;        // 0-100
  attempt?: number;
  output?: Partial<StageOutput>;
  error?: StageError;
  metrics?: Partial<StageMetrics>;
}

// Usage in handlers
class PromptStageHandler {
  async execute(config: PromptStageConfig, context: ExecutionContext) {
    context.eventEmitter.emit({
      type: StageEventType.STARTED,
      stageId: context.stageId,
      stageExecutionId: context.stageExecutionId,
      timestamp: new Date().toISOString(),
      data: { message: 'Loading prompt template' },
    });
    
    // ... execution logic
    
    context.eventEmitter.emit({
      type: StageEventType.PROGRESS,
      stageId: context.stageId,
      stageExecutionId: context.stageExecutionId,
      timestamp: new Date().toISOString(),
      data: { progress: 50, message: 'Waiting for AI response' },
    });
  }
}
```

---

## RES Integration

Integration with Resilient Execution System for fault tolerance:

```typescript
interface RESIntegration {
  wrapExecution<T>(
    operation: () => Promise<T>,
    config: RESConfig
  ): Promise<T>;
}

interface RESConfig {
  enableSelfCorrection: boolean;
  enableMultiModelConsensus: boolean;
  consensusThreshold: number;
  enableCheckpointing: boolean;
  riskLevel: 'low' | 'medium' | 'high' | 'critical';
}

class StageRESIntegration implements RESIntegration {
  async wrapExecution<T>(
    operation: () => Promise<T>,
    config: RESConfig
  ): Promise<T> {
    // 1. Create checkpoint before execution
    if (config.enableCheckpointing) {
      await this.createCheckpoint();
    }
    
    try {
      // 2. Execute operation
      let result = await operation();
      
      // 3. Apply self-correction if enabled
      if (config.enableSelfCorrection) {
        result = await this.applySelfCorrection(result);
      }
      
      // 4. Validate with multi-model consensus for critical operations
      if (config.enableMultiModelConsensus && 
          config.riskLevel === 'critical') {
        await this.validateWithConsensus(result, config.consensusThreshold);
      }
      
      return result;
      
    } catch (error) {
      // 5. Attempt recovery
      if (config.enableCheckpointing) {
        await this.rollbackToCheckpoint();
      }
      throw error;
    }
  }
}
```

---

## Execution Flow

```typescript
class StageExecutorImpl implements StageExecutor {
  private readonly handlers: Map<StageType, StageHandler>;
  private readonly timeoutManager: TimeoutManager;
  private readonly retryManager: RetryManager;
  private readonly resIntegration: RESIntegration;
  
  async execute(
    stage: Stage,
    context: ExecutionContext
  ): Promise<StageResult> {
    const startTime = Date.now();
    const handler = this.handlers.get(stage.StageType);
    
    if (!handler) {
      throw new Error(`No handler for stage type: ${stage.StageType}`);
    }
    
    // 1. Validate configuration
    const config = JSON.parse(stage.Config);
    const validation = handler.validate(config);
    if (!validation.valid) {
      throw new StageExecutionError({
        code: StageErrorCode.INVALID_CONFIG,
        message: `Invalid config: ${validation.errors.join(', ')}`,
        retryable: false,
      });
    }
    
    // 2. Resolve input bindings
    const resolvedContext = await this.resolveInputBindings(
      stage.InputBindings,
      context
    );
    
    // 3. Emit started event
    context.eventEmitter.emit({
      type: StageEventType.STARTED,
      stageId: stage.Id,
      stageExecutionId: context.stageExecutionId,
      timestamp: new Date().toISOString(),
      data: { message: `Executing ${stage.StageType} stage: ${stage.Name}` },
    });
    
    try {
      // 4. Execute with timeout, retry, and RES
      const output = await this.resIntegration.wrapExecution(
        () => this.retryManager.executeWithRetry(
          () => this.timeoutManager.execute(
            () => handler.execute(config, resolvedContext),
            stage.TimeoutSeconds * 1000
          ),
          JSON.parse(stage.RetryConfig || '{}'),
          (attempt, error) => {
            context.eventEmitter.emit({
              type: StageEventType.RETRYING,
              stageId: stage.Id,
              stageExecutionId: context.stageExecutionId,
              timestamp: new Date().toISOString(),
              data: { attempt, error, message: `Retry ${attempt}` },
            });
          }
        ),
        context.config.resConfig
      );
      
      // 5. Store output in variable registry
      if (stage.OutputVariable) {
        context.variables.set(stage.OutputVariable, output);
      }
      
      // 6. Emit completed event
      const metrics: StageMetrics = {
        startedAt: new Date(startTime).toISOString(),
        completedAt: new Date().toISOString(),
        durationMs: Date.now() - startTime,
        attempts: 1,  // Updated by retry manager
        tokensUsed: output.metadata?.tokensUsed,
        model: output.metadata?.model,
      };
      
      context.eventEmitter.emit({
        type: StageEventType.COMPLETED,
        stageId: stage.Id,
        stageExecutionId: context.stageExecutionId,
        timestamp: new Date().toISOString(),
        data: { output, metrics },
      });
      
      return {
        stageId: stage.Id,
        status: StageExecutionStatus.SUCCESS,
        output,
        metrics,
      };
      
    } catch (error) {
      const stageError = this.toStageError(error);
      
      context.eventEmitter.emit({
        type: StageEventType.FAILED,
        stageId: stage.Id,
        stageExecutionId: context.stageExecutionId,
        timestamp: new Date().toISOString(),
        data: { error: stageError },
      });
      
      return {
        stageId: stage.Id,
        status: StageExecutionStatus.FAILED,
        output: { type: OutputType.NONE },
        metrics: {
          startedAt: new Date(startTime).toISOString(),
          completedAt: new Date().toISOString(),
          durationMs: Date.now() - startTime,
          attempts: 1,
        },
        error: stageError,
      };
    }
  }
}
```

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Handler initialization | < 10ms |
| Variable resolution | < 5ms per variable |
| Stage overhead (non-execution) | < 50ms |
| Event emission | < 1ms |
| Checkpoint creation | < 100ms |

---

## Related Specs

- [Database Schema](./01-database-schema.md)
- [Variable Registry](./03-variable-registry.md)
- [Validation Runtime](./05-validation-runtime.md)
- [Input Output Binding](./06-io-binding.md)
- [Resilient Execution System](../06-ai-integration/12-resilient-execution-system.md)
