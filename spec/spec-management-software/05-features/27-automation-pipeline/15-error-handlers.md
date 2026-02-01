# Error Handlers

**Version:** 2.0.0  
**Status:** Complete  
**Created:** 2026-01-30  
**Updated:** 2026-01-31  

---

## Overview

Error handlers provide structured exception management within pipelines, enabling recovery, compensation, and graceful degradation.

**Cross-References:**
- [Execution Blocks](./07-execution-blocks.md)
- [Stage Executor](./04-stage-executor.md)
- [Resilient Execution System](../../06-ai-integration/12-resilient-execution-system.md)

---

## 1. Error Handler Types

### 1.1 Type Definitions

```typescript
enum ErrorHandlerType {
  TRY_CATCH = 'TRY_CATCH',
  RETRY = 'RETRY',
  FALLBACK = 'FALLBACK',
  CIRCUIT_BREAKER = 'CIRCUIT_BREAKER',
  COMPENSATION = 'COMPENSATION',
  ESCALATION = 'ESCALATION',
}

interface ErrorHandler {
  readonly id: string;
  readonly type: ErrorHandlerType;
  readonly scope: ErrorScope;
  readonly filters: readonly ErrorFilter[];
  readonly actions: readonly ErrorAction[];
  readonly metadata: ErrorHandlerMetadata;
}

interface ErrorScope {
  readonly level: ScopeLevel;
  readonly targetIds: readonly string[];    // Block/stage IDs
  readonly includeNested: boolean;
}

enum ScopeLevel {
  STAGE = 'STAGE',
  BLOCK = 'BLOCK',
  PIPELINE = 'PIPELINE',
  GLOBAL = 'GLOBAL',
}

interface ErrorHandlerMetadata {
  readonly name: string;
  readonly description: string;
  readonly priority: number;
  readonly enabled: boolean;
  readonly logLevel: LogLevel;
}
```

### 1.2 Handler Type Behaviors

| Type | Purpose | Recovery | Compensation |
|------|---------|----------|--------------|
| `TRY_CATCH` | Wrap and handle | Yes | No |
| `RETRY` | Automatic retry | Yes | No |
| `FALLBACK` | Alternative path | Yes | No |
| `CIRCUIT_BREAKER` | Prevent cascade | Yes | No |
| `COMPENSATION` | Undo changes | No | Yes |
| `ESCALATION` | Human handoff | No | No |

---

## 2. Error Filtering

### 2.1 Filter Configuration

```typescript
interface ErrorFilter {
  readonly type: ErrorFilterType;
  readonly config: ErrorFilterConfig;
  readonly invert: boolean;
}

enum ErrorFilterType {
  ERROR_CODE = 'ERROR_CODE',
  ERROR_TYPE = 'ERROR_TYPE',
  ERROR_MESSAGE = 'ERROR_MESSAGE',
  SOURCE_STAGE = 'SOURCE_STAGE',
  SEVERITY = 'SEVERITY',
  CUSTOM = 'CUSTOM',
}

type ErrorFilterConfig = 
  | ErrorCodeFilter
  | ErrorTypeFilter
  | ErrorMessageFilter
  | SourceStageFilter
  | SeverityFilter
  | CustomFilter;

interface ErrorCodeFilter {
  readonly type: 'ERROR_CODE';
  readonly codes: readonly string[];
  readonly matchMode: MatchMode;
}

interface ErrorTypeFilter {
  readonly type: 'ERROR_TYPE';
  readonly errorTypes: readonly ErrorCategory[];
}

enum ErrorCategory {
  VALIDATION = 'VALIDATION',
  NETWORK = 'NETWORK',
  TIMEOUT = 'TIMEOUT',
  AUTHENTICATION = 'AUTHENTICATION',
  AUTHORIZATION = 'AUTHORIZATION',
  RATE_LIMIT = 'RATE_LIMIT',
  RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND',
  CONFLICT = 'CONFLICT',
  INTERNAL = 'INTERNAL',
  EXTERNAL_SERVICE = 'EXTERNAL_SERVICE',
  DATA_CORRUPTION = 'DATA_CORRUPTION',
  BUSINESS_RULE = 'BUSINESS_RULE',
}

interface ErrorMessageFilter {
  readonly type: 'ERROR_MESSAGE';
  readonly patterns: readonly string[];
  readonly regex: boolean;
}

interface SourceStageFilter {
  readonly type: 'SOURCE_STAGE';
  readonly stageIds: readonly string[];
  readonly stageTypes: readonly StageType[];
}

interface SeverityFilter {
  readonly type: 'SEVERITY';
  readonly minSeverity: ErrorSeverity;
  readonly maxSeverity: ErrorSeverity;
}

enum ErrorSeverity {
  LOW = 1,
  MEDIUM = 2,
  HIGH = 3,
  CRITICAL = 4,
}

interface CustomFilter {
  readonly type: 'CUSTOM';
  readonly expression: ConditionExpression;
}

enum MatchMode {
  ANY = 'ANY',
  ALL = 'ALL',
  EXACT = 'EXACT',
  PREFIX = 'PREFIX',
}
```

### 2.2 Filter Evaluation

```typescript
interface ErrorFilterEvaluator {
  evaluate(
    error: PipelineError,
    filter: ErrorFilter
  ): boolean;
  
  matchHandlers(
    error: PipelineError,
    handlers: readonly ErrorHandler[]
  ): readonly ErrorHandler[];
}

interface PipelineError {
  readonly id: string;
  readonly code: string;
  readonly category: ErrorCategory;
  readonly message: string;
  readonly severity: ErrorSeverity;
  readonly sourceStageId: string;
  readonly sourceBlockId: string;
  readonly stack: string;
  readonly context: Record<string, unknown>;
  readonly timestamp: Date;
  readonly retryCount: number;
}
```

---

## 3. Try-Catch Handler

### 3.1 Configuration

```typescript
interface TryCatchHandler extends ErrorHandler {
  readonly type: ErrorHandlerType.TRY_CATCH;
  readonly tryBlock: string;              // Block ID
  readonly catchBlocks: readonly CatchBlock[];
  readonly finallyBlock: string | null;   // Block ID
}

interface CatchBlock {
  readonly id: string;
  readonly filters: readonly ErrorFilter[];
  readonly handlerBlockId: string;
  readonly suppressError: boolean;
  readonly transformError: ErrorTransform | null;
}

interface ErrorTransform {
  readonly newCode: string | null;
  readonly newMessage: string | null;
  readonly addContext: Record<string, unknown>;
  readonly wrapOriginal: boolean;
}

// Usage Example
const apiCallHandler: TryCatchHandler = {
  id: 'api-error-handler',
  type: ErrorHandlerType.TRY_CATCH,
  scope: { level: ScopeLevel.BLOCK, targetIds: ['api-block'], includeNested: true },
  filters: [],
  actions: [],
  tryBlock: 'api-call-block',
  catchBlocks: [
    {
      id: 'network-catch',
      filters: [{ type: ErrorFilterType.ERROR_TYPE, config: { type: 'ERROR_TYPE', errorTypes: [ErrorCategory.NETWORK] }, invert: false }],
      handlerBlockId: 'network-recovery-block',
      suppressError: false,
      transformError: null,
    },
    {
      id: 'auth-catch',
      filters: [{ type: ErrorFilterType.ERROR_TYPE, config: { type: 'ERROR_TYPE', errorTypes: [ErrorCategory.AUTHENTICATION] }, invert: false }],
      handlerBlockId: 'refresh-token-block',
      suppressError: true,
      transformError: null,
    },
  ],
  finallyBlock: 'cleanup-block',
  metadata: { name: 'API Error Handler', description: 'Handles API call failures', priority: 10, enabled: true, logLevel: LogLevel.WARN },
};
```

### 3.2 Execution Flow

```
┌─────────────────────┐
│     TRY Block       │
│   (api-call-block)  │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐ ┌─────────────────────┐
│ Success │ │       Error         │
└────┬────┘ └──────────┬──────────┘
     │                 │
     │           ┌─────┴─────┐
     │           ▼           ▼
     │    ┌───────────┐ ┌───────────┐
     │    │ Network?  │ │  Auth?    │
     │    └─────┬─────┘ └─────┬─────┘
     │          │             │
     │          ▼             ▼
     │    ┌───────────┐ ┌───────────┐
     │    │ Recovery  │ │ Refresh   │
     │    │ Block     │ │ Token     │
     │    └─────┬─────┘ └─────┬─────┘
     │          │             │
     └──────────┼─────────────┘
                │
                ▼
         ┌─────────────┐
         │   FINALLY   │
         │ (cleanup)   │
         └─────────────┘
```

---

## 4. Retry Handler

### 4.1 Configuration

```typescript
interface RetryHandler extends ErrorHandler {
  readonly type: ErrorHandlerType.RETRY;
  readonly maxAttempts: number;
  readonly strategy: RetryStrategy;
  readonly delays: RetryDelayConfig;
  readonly jitter: JitterConfig;
  readonly resetCondition: ConditionExpression | null;
}

enum RetryStrategy {
  FIXED = 'FIXED',
  LINEAR = 'LINEAR',
  EXPONENTIAL = 'EXPONENTIAL',
  FIBONACCI = 'FIBONACCI',
  CUSTOM = 'CUSTOM',
}

interface RetryDelayConfig {
  readonly initialDelayMs: number;
  readonly maxDelayMs: number;
  readonly multiplier: number;        // For exponential
  readonly customDelays: readonly number[] | null;
}

interface JitterConfig {
  readonly enabled: boolean;
  readonly type: JitterType;
  readonly factor: number;            // 0-1
}

enum JitterType {
  FULL = 'FULL',                      // Random 0 to delay
  EQUAL = 'EQUAL',                    // delay/2 + random(delay/2)
  DECORRELATED = 'DECORRELATED',      // min(cap, random(base, prev*3))
}

// Usage Example
const retryConfig: RetryHandler = {
  id: 'api-retry',
  type: ErrorHandlerType.RETRY,
  scope: { level: ScopeLevel.STAGE, targetIds: ['http-request'], includeNested: false },
  filters: [
    { 
      type: ErrorFilterType.ERROR_TYPE, 
      config: { type: 'ERROR_TYPE', errorTypes: [ErrorCategory.NETWORK, ErrorCategory.TIMEOUT, ErrorCategory.RATE_LIMIT] },
      invert: false,
    },
  ],
  actions: [],
  maxAttempts: 5,
  strategy: RetryStrategy.EXPONENTIAL,
  delays: {
    initialDelayMs: 1000,
    maxDelayMs: 30000,
    multiplier: 2,
    customDelays: null,
  },
  jitter: { enabled: true, type: JitterType.EQUAL, factor: 0.5 },
  resetCondition: null,
  metadata: { name: 'API Retry', description: 'Retry transient failures', priority: 20, enabled: true, logLevel: LogLevel.INFO },
};
```

### 4.2 Retry Delay Calculation

```typescript
interface RetryDelayCalculator {
  calculate(
    attempt: number,
    config: RetryDelayConfig,
    strategy: RetryStrategy,
    jitter: JitterConfig
  ): number;
}

// Delay patterns
// FIXED:       [1000, 1000, 1000, 1000, 1000]
// LINEAR:      [1000, 2000, 3000, 4000, 5000]
// EXPONENTIAL: [1000, 2000, 4000, 8000, 16000]
// FIBONACCI:   [1000, 1000, 2000, 3000, 5000]
```

### 4.3 Retry State Machine

```
    ┌─────────────────┐
    │     INITIAL     │
    └────────┬────────┘
             │
             ▼
    ┌─────────────────┐
    │    EXECUTING    │◄─────────────┐
    └────────┬────────┘              │
             │                       │
       ┌─────┴─────┐                 │
       │           │                 │
       ▼           ▼                 │
┌───────────┐ ┌───────────┐          │
│  SUCCESS  │ │  FAILED   │          │
└───────────┘ └─────┬─────┘          │
                    │                │
              ┌─────┴─────┐          │
              │           │          │
              ▼           ▼          │
       ┌───────────┐ ┌───────────┐   │
       │ Retryable │ │ Non-Retry │   │
       └─────┬─────┘ └───────────┘   │
             │                       │
             ▼                       │
       ┌───────────┐                 │
       │ attempts  │                 │
       │ < max?    │                 │
       └─────┬─────┘                 │
       ┌─────┴─────┐                 │
       │           │                 │
       ▼           ▼                 │
┌───────────┐ ┌───────────┐          │
│ EXHAUSTED │ │  WAITING  │──────────┘
└───────────┘ └───────────┘
```

---

## 5. Fallback Handler

### 5.1 Configuration

```typescript
interface FallbackHandler extends ErrorHandler {
  readonly type: ErrorHandlerType.FALLBACK;
  readonly fallbacks: readonly FallbackOption[];
  readonly defaultValue: unknown | null;
  readonly cacheSuccessful: boolean;
}

interface FallbackOption {
  readonly id: string;
  readonly name: string;
  readonly condition: ConditionExpression | null;
  readonly blockId: string;
  readonly timeout: number;
  readonly priority: number;
}

// Usage Example
const dataFallback: FallbackHandler = {
  id: 'data-fallback',
  type: ErrorHandlerType.FALLBACK,
  scope: { level: ScopeLevel.BLOCK, targetIds: ['fetch-data'], includeNested: true },
  filters: [],
  actions: [],
  fallbacks: [
    { id: 'cache', name: 'Use Cached Data', condition: null, blockId: 'read-cache', timeout: 5000, priority: 1 },
    { id: 'backup', name: 'Use Backup API', condition: null, blockId: 'backup-api', timeout: 10000, priority: 2 },
    { id: 'stale', name: 'Use Stale Data', condition: null, blockId: 'stale-data', timeout: 2000, priority: 3 },
  ],
  defaultValue: { data: [], fromFallback: true },
  cacheSuccessful: true,
  metadata: { name: 'Data Fallback', description: 'Fallback chain for data fetch', priority: 15, enabled: true, logLevel: LogLevel.WARN },
};
```

### 5.2 Fallback Execution

```
┌─────────────────┐
│ Primary Block   │
│ (fetch-data)    │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌───────┐ ┌───────────────────┐
│Success│ │      Failed       │
└───────┘ └─────────┬─────────┘
                    │
                    ▼
           ┌─────────────────┐
           │ Fallback 1:     │
           │ Read Cache      │
           └────────┬────────┘
                    │
              ┌─────┴─────┐
              │           │
              ▼           ▼
         ┌───────┐ ┌───────────────┐
         │Success│ │    Failed     │
         └───────┘ └───────┬───────┘
                           │
                           ▼
                  ┌─────────────────┐
                  │ Fallback 2:     │
                  │ Backup API      │
                  └────────┬────────┘
                           │
                     ┌─────┴─────┐
                     │           │
                     ▼           ▼
                ┌───────┐ ┌───────────────┐
                │Success│ │ Use Default   │
                └───────┘ └───────────────┘
```

---

## 6. Circuit Breaker

### 6.1 Configuration

```typescript
interface CircuitBreakerHandler extends ErrorHandler {
  readonly type: ErrorHandlerType.CIRCUIT_BREAKER;
  readonly thresholds: CircuitThresholds;
  readonly timing: CircuitTiming;
  readonly fallback: FallbackOption | null;
  readonly healthCheck: HealthCheckConfig | null;
}

interface CircuitThresholds {
  readonly failureThreshold: number;        // Failures to open
  readonly failureRateThreshold: number;    // 0-1
  readonly successThreshold: number;        // Successes to close
  readonly minimumCalls: number;            // Before rate calculation
}

interface CircuitTiming {
  readonly openDurationMs: number;
  readonly halfOpenDurationMs: number;
  readonly samplingWindowMs: number;
}

interface HealthCheckConfig {
  readonly enabled: boolean;
  readonly intervalMs: number;
  readonly endpoint: string;
  readonly timeout: number;
}

enum CircuitState {
  CLOSED = 'CLOSED',
  OPEN = 'OPEN',
  HALF_OPEN = 'HALF_OPEN',
}

// Usage Example
const circuitBreaker: CircuitBreakerHandler = {
  id: 'api-circuit-breaker',
  type: ErrorHandlerType.CIRCUIT_BREAKER,
  scope: { level: ScopeLevel.STAGE, targetIds: ['external-api'], includeNested: false },
  filters: [],
  actions: [],
  thresholds: {
    failureThreshold: 5,
    failureRateThreshold: 0.5,
    successThreshold: 3,
    minimumCalls: 10,
  },
  timing: {
    openDurationMs: 30000,
    halfOpenDurationMs: 10000,
    samplingWindowMs: 60000,
  },
  fallback: { id: 'cb-fallback', name: 'Circuit Open Fallback', condition: null, blockId: 'cached-response', timeout: 5000, priority: 1 },
  healthCheck: { enabled: true, intervalMs: 10000, endpoint: '/health', timeout: 5000 },
  metadata: { name: 'API Circuit Breaker', description: 'Prevent cascade failures', priority: 5, enabled: true, logLevel: LogLevel.WARN },
};
```

### 6.2 Circuit State Machine

```
                    ┌─────────────────┐
                    │     CLOSED      │
                    │ (normal flow)   │
                    └────────┬────────┘
                             │
                    failures > threshold
                             │
                             ▼
                    ┌─────────────────┐
         ┌─────────│      OPEN       │
         │         │ (fast fail)     │
         │         └────────┬────────┘
         │                  │
   health check          timeout
    success                 │
         │                  ▼
         │         ┌─────────────────┐
         │         │   HALF_OPEN     │
         │         │ (probe mode)    │
         └────────►└────────┬────────┘
                            │
                   ┌────────┴────────┐
                   │                 │
             success > N        failure
                   │                 │
                   ▼                 ▼
          ┌─────────────┐   ┌─────────────┐
          │   CLOSED    │   │    OPEN     │
          └─────────────┘   └─────────────┘
```

### 6.3 Circuit Metrics

```typescript
interface CircuitMetrics {
  readonly state: CircuitState;
  readonly totalCalls: number;
  readonly successCount: number;
  readonly failureCount: number;
  readonly failureRate: number;
  readonly lastStateChange: Date;
  readonly consecutiveSuccesses: number;
  readonly consecutiveFailures: number;
}

interface CircuitBreakerMonitor {
  getMetrics(breakerId: string): CircuitMetrics;
  
  forceOpen(breakerId: string): void;
  forceClose(breakerId: string): void;
  reset(breakerId: string): void;
}
```

---

## 7. Compensation Handler

### 7.1 Configuration

```typescript
interface CompensationHandler extends ErrorHandler {
  readonly type: ErrorHandlerType.COMPENSATION;
  readonly compensationMode: CompensationMode;
  readonly compensationSteps: readonly CompensationStep[];
  readonly timeout: number;
  readonly continueOnCompensationError: boolean;
}

enum CompensationMode {
  BACKWARD = 'BACKWARD',           // Reverse order
  FORWARD = 'FORWARD',             // Original order
  PARALLEL = 'PARALLEL',           // All at once
  SELECTIVE = 'SELECTIVE',         // Only affected
}

interface CompensationStep {
  readonly id: string;
  readonly originalStageId: string;
  readonly compensationBlockId: string;
  readonly condition: ConditionExpression | null;
  readonly timeout: number;
  readonly required: boolean;
}

// Usage Example: Saga compensation
const orderCompensation: CompensationHandler = {
  id: 'order-saga-compensation',
  type: ErrorHandlerType.COMPENSATION,
  scope: { level: ScopeLevel.BLOCK, targetIds: ['order-saga'], includeNested: true },
  filters: [],
  actions: [],
  compensationMode: CompensationMode.BACKWARD,
  compensationSteps: [
    { id: 'undo-payment', originalStageId: 'charge-payment', compensationBlockId: 'refund-payment', condition: null, timeout: 30000, required: true },
    { id: 'undo-inventory', originalStageId: 'reserve-inventory', compensationBlockId: 'release-inventory', condition: null, timeout: 10000, required: true },
    { id: 'undo-order', originalStageId: 'create-order', compensationBlockId: 'cancel-order', condition: null, timeout: 10000, required: true },
  ],
  timeout: 60000,
  continueOnCompensationError: false,
  metadata: { name: 'Order Saga Compensation', description: 'Undo order on failure', priority: 1, enabled: true, logLevel: LogLevel.ERROR },
};
```

### 7.2 Compensation Flow (Saga Pattern)

```
Forward Execution:
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Create   │───►│ Reserve  │───►│ Charge   │───►│ Ship     │
│ Order    │    │ Inventory│    │ Payment  │    │ Order    │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
     │               │               │               │
     ▼               ▼               ▼               ▼
   [Save]         [Save]          [Save]           ✗ FAIL


Backward Compensation:
┌──────────┐    ┌──────────┐    ┌──────────┐
│ Cancel   │◄───│ Release  │◄───│ Refund   │◄─── Error
│ Order    │    │ Inventory│    │ Payment  │
└──────────┘    └──────────┘    └──────────┘
```

---

## 8. Escalation Handler

### 8.1 Configuration

```typescript
interface EscalationHandler extends ErrorHandler {
  readonly type: ErrorHandlerType.ESCALATION;
  readonly escalationLevel: EscalationLevel;
  readonly channels: readonly EscalationChannel[];
  readonly waitForResolution: boolean;
  readonly resolutionTimeout: number;
  readonly autoResolveAfter: number | null;
}

enum EscalationLevel {
  INFO = 'INFO',
  WARNING = 'WARNING',
  CRITICAL = 'CRITICAL',
  EMERGENCY = 'EMERGENCY',
}

interface EscalationChannel {
  readonly type: ChannelType;
  readonly config: ChannelConfig;
  readonly conditions: readonly ConditionExpression[];
}

enum ChannelType {
  IN_APP = 'IN_APP',
  EMAIL = 'EMAIL',
  SLACK = 'SLACK',
  PAGERDUTY = 'PAGERDUTY',
  WEBHOOK = 'WEBHOOK',
}

// Usage Example
const criticalEscalation: EscalationHandler = {
  id: 'critical-error-escalation',
  type: ErrorHandlerType.ESCALATION,
  scope: { level: ScopeLevel.PIPELINE, targetIds: [], includeNested: true },
  filters: [
    { type: ErrorFilterType.SEVERITY, config: { type: 'SEVERITY', minSeverity: ErrorSeverity.CRITICAL, maxSeverity: ErrorSeverity.CRITICAL }, invert: false },
  ],
  actions: [],
  escalationLevel: EscalationLevel.CRITICAL,
  channels: [
    { type: ChannelType.IN_APP, config: { priority: 'high' }, conditions: [] },
    { type: ChannelType.EMAIL, config: { template: 'critical-error' }, conditions: [] },
    { type: ChannelType.SLACK, config: { channel: '#alerts' }, conditions: [] },
  ],
  waitForResolution: true,
  resolutionTimeout: 3600000,      // 1 hour
  autoResolveAfter: null,
  metadata: { name: 'Critical Error Escalation', description: 'Escalate critical errors', priority: 1, enabled: true, logLevel: LogLevel.ERROR },
};
```

### 8.2 Escalation Flow

```
┌─────────────────────┐
│   Error Detected    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Create Escalation   │
│ Request             │
└──────────┬──────────┘
           │
    ┌──────┴──────┐
    ▼             ▼
┌───────┐   ┌───────────┐
│In-App │   │  Notify   │
│ Alert │   │ Channels  │
└───────┘   └───────────┘
           │
           ▼
┌─────────────────────┐
│  Wait for Human     │
│  Resolution         │
└──────────┬──────────┘
           │
    ┌──────┴──────┐
    │             │
    ▼             ▼
┌───────────┐ ┌───────────┐
│ Resolved  │ │ Timeout   │
│           │ │           │
└─────┬─────┘ └─────┬─────┘
      │             │
      ▼             ▼
┌───────────┐ ┌───────────┐
│ Continue  │ │ Abort or  │
│ Pipeline  │ │ Escalate  │
└───────────┘ └───────────┘
```

---

## 9. Error Actions

### 9.1 Action Types

```typescript
enum ErrorActionType {
  LOG = 'LOG',
  NOTIFY = 'NOTIFY',
  STORE = 'STORE',
  TRANSFORM = 'TRANSFORM',
  EXECUTE_BLOCK = 'EXECUTE_BLOCK',
  SET_VARIABLE = 'SET_VARIABLE',
  ABORT = 'ABORT',
  CONTINUE = 'CONTINUE',
  SKIP = 'SKIP',
}

interface ErrorAction {
  readonly type: ErrorActionType;
  readonly config: ErrorActionConfig;
  readonly condition: ConditionExpression | null;
}

type ErrorActionConfig = 
  | LogActionConfig
  | NotifyActionConfig
  | StoreActionConfig
  | TransformActionConfig
  | ExecuteBlockConfig
  | SetVariableConfig
  | ControlFlowConfig;

interface LogActionConfig {
  readonly type: 'LOG';
  readonly level: LogLevel;
  readonly message: string;
  readonly includeStack: boolean;
  readonly includeContext: boolean;
}

interface NotifyActionConfig {
  readonly type: 'NOTIFY';
  readonly channels: readonly ChannelType[];
  readonly template: string;
  readonly recipients: readonly string[];
}

interface StoreActionConfig {
  readonly type: 'STORE';
  readonly table: string;
  readonly fields: Record<string, ValueReference>;
}

interface TransformActionConfig {
  readonly type: 'TRANSFORM';
  readonly operations: readonly TransformOperation[];
}

interface ExecuteBlockConfig {
  readonly type: 'EXECUTE_BLOCK';
  readonly blockId: string;
  readonly passError: boolean;
}

interface SetVariableConfig {
  readonly type: 'SET_VARIABLE';
  readonly variableName: string;
  readonly value: ValueReference;
}

interface ControlFlowConfig {
  readonly type: 'CONTROL_FLOW';
  readonly action: 'ABORT' | 'CONTINUE' | 'SKIP';
  readonly skipCount: number;             // For SKIP
  readonly returnValue: unknown | null;   // For ABORT
}
```

---

## 10. Visual Components

### 10.1 Error Handler Node

```typescript
interface ErrorHandlerNodeProps {
  readonly handler: ErrorHandler;
  readonly executionState: ErrorHandlerExecutionState;
  readonly onConfigEdit: (config: ErrorHandler) => void;
  readonly onFilterAdd: () => void;
  readonly onActionAdd: () => void;
}

interface ErrorHandlerExecutionState {
  readonly status: NodeExecutionStatus;
  readonly errorsHandled: number;
  readonly currentError: PipelineError | null;
  readonly retryAttempt: number;
  readonly circuitState: CircuitState | null;
}
```

### 10.2 Handler Styling

| Handler Type | Icon | Primary Color |
|--------------|------|---------------|
| `TRY_CATCH` | Shield | `--accent` |
| `RETRY` | RefreshCw | `--primary` |
| `FALLBACK` | GitBranch | `--secondary` |
| `CIRCUIT_BREAKER` | Power | `--warning` |
| `COMPENSATION` | RotateCcw | `--muted` |
| `ESCALATION` | AlertTriangle | `--destructive` |

### 10.3 Error Visualization

```typescript
interface ErrorOverlayProps {
  readonly error: PipelineError;
  readonly handler: ErrorHandler | null;
  readonly position: Position;
  readonly onDismiss: () => void;
  readonly onRetry: () => void;
  readonly onEscalate: () => void;
}
```

---

## 11. Database Schema

### 11.1 Error Handler Tables

```sql
-- Error Handlers
CREATE TABLE ErrorHandler (
  Id TEXT PRIMARY KEY,
  PipelineId TEXT NOT NULL REFERENCES Pipeline(Id),
  Type TEXT NOT NULL CHECK (Type IN ('TRY_CATCH', 'RETRY', 'FALLBACK', 'CIRCUIT_BREAKER', 'COMPENSATION', 'ESCALATION')),
  Name TEXT NOT NULL,
  Description TEXT,
  ScopeJson TEXT NOT NULL,
  FiltersJson TEXT NOT NULL DEFAULT '[]',
  ActionsJson TEXT NOT NULL DEFAULT '[]',
  ConfigJson TEXT NOT NULL,
  Priority INTEGER NOT NULL DEFAULT 0,
  Enabled INTEGER NOT NULL DEFAULT 1,
  CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Error Occurrences
CREATE TABLE PipelineError (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  Code TEXT NOT NULL,
  Category TEXT NOT NULL,
  Message TEXT NOT NULL,
  Severity INTEGER NOT NULL,
  SourceStageId TEXT,
  SourceBlockId TEXT,
  StackTrace TEXT,
  ContextJson TEXT,
  HandlerId TEXT REFERENCES ErrorHandler(Id),
  HandlingStatus TEXT NOT NULL DEFAULT 'UNHANDLED',
  RetryCount INTEGER NOT NULL DEFAULT 0,
  OccurredAt TEXT NOT NULL DEFAULT (datetime('now')),
  ResolvedAt TEXT
);

-- Circuit Breaker State
CREATE TABLE CircuitBreakerState (
  Id TEXT PRIMARY KEY,
  HandlerId TEXT NOT NULL REFERENCES ErrorHandler(Id),
  State TEXT NOT NULL DEFAULT 'CLOSED',
  FailureCount INTEGER NOT NULL DEFAULT 0,
  SuccessCount INTEGER NOT NULL DEFAULT 0,
  LastFailureAt TEXT,
  LastSuccessAt TEXT,
  StateChangedAt TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Compensation Log
CREATE TABLE CompensationLog (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  HandlerId TEXT NOT NULL REFERENCES ErrorHandler(Id),
  StepId TEXT NOT NULL,
  OriginalStageId TEXT NOT NULL,
  Status TEXT NOT NULL,
  ErrorMessage TEXT,
  DurationMs INTEGER NOT NULL,
  ExecutedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_error_execution ON PipelineError(ExecutionId);
CREATE INDEX idx_error_category ON PipelineError(Category);
CREATE INDEX idx_circuit_handler ON CircuitBreakerState(HandlerId);
```

---

## 12. Error Handler Chain

### 12.1 Handler Resolution

```typescript
interface ErrorHandlerChain {
  resolve(
    error: PipelineError,
    availableHandlers: readonly ErrorHandler[]
  ): readonly ErrorHandler[];
  
  execute(
    error: PipelineError,
    handlers: readonly ErrorHandler[],
    context: ExecutionContext
  ): Promise<ErrorHandlingResult>;
}

interface ErrorHandlingResult {
  readonly handled: boolean;
  readonly handlerUsed: ErrorHandler | null;
  readonly action: ErrorActionType;
  readonly modifiedError: PipelineError | null;
  readonly continueExecution: boolean;
  readonly output: unknown;
}
```

### 12.2 Handler Priority Resolution

```
Error Occurs
     │
     ▼
┌─────────────────────┐
│ Collect Handlers    │
│ in scope            │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Filter by error     │
│ type/code/severity  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Sort by priority    │
│ (lower = higher)    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Execute first       │
│ matching handler    │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐ ┌─────────┐
│ Handled │ │ Unhand- │
│         │ │  led    │
└─────────┘ └────┬────┘
                 │
                 ▼
          ┌─────────────┐
          │ Propagate   │
          │ to parent   │
          └─────────────┘
```

---

## Related Specs

- [Conditional Nodes](./13-conditional-nodes.md)
- [Loop Constructs](./14-loop-constructs.md)
- [Stage Executor](./04-stage-executor.md)
- [Resilient Execution System](../../06-ai-integration/12-resilient-execution-system.md)
