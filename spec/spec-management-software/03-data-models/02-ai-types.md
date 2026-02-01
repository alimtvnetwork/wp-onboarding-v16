# AI Types

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Model Configuration

```typescript
interface ModelInfo {
  id: string;
  name: string;
  provider: ModelProvider;
  category: ModelCategory;
  contextWindow: number;
  maxOutputTokens: number;
  inputCostPer1k: number;
  outputCostPer1k: number;
  capabilities: ModelCapability[];
  isDefault: boolean;
}

type ModelProvider = 'ollama' | 'llama.cpp' | 'openai' | 'anthropic';

type ModelCategory = 'thinking' | 'writing' | 'voice' | 'coding' | 'embedding';

type ModelCapability = 
  | 'streaming'
  | 'function-calling'
  | 'vision'
  | 'audio'
  | 'json-mode';
```

---

## AI Request/Response

```typescript
interface AIRequest {
  model: string;
  messages: ChatMessage[];
  temperature?: number;
  maxTokens?: number;
  topP?: number;
  stream?: boolean;
  tools?: ToolDefinition[];
  toolChoice?: ToolChoice;
  responseFormat?: ResponseFormat;
}

interface ChatMessage {
  role: 'system' | 'user' | 'assistant' | 'tool';
  content: string | ContentPart[];
  name?: string;
  toolCallId?: string;
}

type ContentPart = 
  | { type: 'text'; text: string }
  | { type: 'image_url'; image_url: { url: string } };

interface AIResponse {
  id: string;
  model: string;
  message: ChatMessage;
  usage: TokenUsage;
  finishReason: FinishReason;
  toolCalls?: ToolCall[];
}

interface TokenUsage {
  promptTokens: number;
  completionTokens: number;
  totalTokens: number;
}

type FinishReason = 'stop' | 'length' | 'tool_calls' | 'content_filter';
```

---

## Tool Calling

```typescript
interface ToolDefinition {
  type: 'function';
  function: FunctionDefinition;
}

interface FunctionDefinition {
  name: string;
  description: string;
  parameters: JSONSchema;
  strict?: boolean;
}

interface JSONSchema {
  type: 'object';
  properties: Record<string, JSONSchemaProperty>;
  required?: string[];
  additionalProperties?: boolean;
}

interface JSONSchemaProperty {
  type: 'string' | 'number' | 'boolean' | 'array' | 'object';
  description?: string;
  enum?: string[];
  items?: JSONSchemaProperty;
}

type ToolChoice = 
  | 'auto'
  | 'none'
  | 'required'
  | { type: 'function'; function: { name: string } };

interface ToolCall {
  id: string;
  type: 'function';
  function: {
    name: string;
    arguments: string;  // JSON string
  };
}

interface ToolResult {
  toolCallId: string;
  result: string;  // JSON string or text
}
```

---

## Streaming

```typescript
interface StreamChunk {
  id: string;
  delta: DeltaContent;
  finishReason: FinishReason | null;
}

interface DeltaContent {
  role?: 'assistant';
  content?: string;
  toolCalls?: ToolCallDelta[];
}

interface ToolCallDelta {
  index: number;
  id?: string;
  function?: {
    name?: string;
    arguments?: string;
  };
}

// SSE Event format
interface AIStreamEvent {
  event: 'token' | 'tool_call' | 'done' | 'error';
  data: StreamChunk | AIError;
}

interface AIError {
  code: number;
  message: string;
  type: AIErrorType;
}

type AIErrorType = 
  | 'rate_limit'
  | 'context_length'
  | 'invalid_request'
  | 'model_not_found'
  | 'server_error';
```

---

## Prompt Templates

```typescript
interface PromptTemplate {
  id: string;
  name: string;
  category: string;
  description: string;
  systemPrompt: string;
  userPromptTemplate: string;
  variables: TemplateVariable[];
  defaultModel: string;
  defaultTemperature: number;
  outputFormat: OutputFormat;
  metadata: TemplateMetadata;
}

interface TemplateVariable {
  name: string;
  description: string;
  type: 'string' | 'number' | 'boolean' | 'array';
  required: boolean;
  default?: unknown;
}

type OutputFormat = 
  | 'text'
  | 'json'
  | 'markdown'
  | 'code';

interface TemplateMetadata {
  version: string;
  author: string;
  tags: string[];
  createdAt: string;
  updatedAt: string;
}
```

---

## Chain Execution

```typescript
interface AIChainRequest {
  chainId: string;
  steps: ChainStep[];
  context: Record<string, unknown>;
  options: ChainOptions;
}

interface ChainStep {
  id: string;
  name: string;
  templateId: string;
  inputs: Record<string, string>;  // Variable -> source mapping
  condition?: ChainCondition;
  onError: 'fail' | 'skip' | 'retry';
}

interface ChainCondition {
  type: 'expression';
  expression: string;  // e.g., "steps.step1.success == true"
}

interface ChainOptions {
  parallel: boolean;
  maxRetries: number;
  timeout: number;
}

interface ChainResult {
  chainId: string;
  success: boolean;
  steps: StepResult[];
  totalTokens: number;
  totalCost: number;
  duration: number;
}

interface StepResult {
  stepId: string;
  success: boolean;
  output: unknown;
  tokens: TokenUsage;
  duration: number;
  error?: AIError;
}
```

---

## Response Format

```typescript
type ResponseFormat = 
  | { type: 'text' }
  | { type: 'json_object' }
  | { type: 'json_schema'; json_schema: JSONSchemaFormat };

interface JSONSchemaFormat {
  name: string;
  description?: string;
  schema: JSONSchema;
  strict?: boolean;
}
```
