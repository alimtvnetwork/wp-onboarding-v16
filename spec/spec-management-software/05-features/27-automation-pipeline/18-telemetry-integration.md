# Telemetry Integration

**Version:** 1.0.0  
**Status:** Draft  
**Created:** 2026-01-30  
**Updated:** 2026-01-30  

---

## Overview

Telemetry Integration provides comprehensive metrics collection, analysis, and visualization for pipeline execution performance, enabling data-driven optimization and reliability monitoring.

**Cross-References:**
- [Live Execution View](./16-live-execution-view.md)
- [Resilient Execution System](../../06-ai-integration/12-resilient-execution-system.md)
- [Telemetry Dashboard](../../11-telemetry/00-overview.md)

---

## 1. Telemetry Architecture

### 1.1 Collection Pipeline

```typescript
interface TelemetryCollector {
  readonly pipelineId: string;
  readonly executionId: string;
  
  recordEvent(event: TelemetryEvent): void;
  recordMetric(metric: TelemetryMetric): void;
  recordSpan(span: TelemetrySpan): void;
  flush(): Promise<void>;
}

interface TelemetryEvent {
  readonly type: EventType;
  readonly timestamp: Date;
  readonly data: Record<string, unknown>;
  readonly tags: Record<string, string>;
}

interface TelemetryMetric {
  readonly name: string;
  readonly value: number;
  readonly unit: MetricUnit;
  readonly timestamp: Date;
  readonly dimensions: Record<string, string>;
}

interface TelemetrySpan {
  readonly traceId: string;
  readonly spanId: string;
  readonly parentSpanId: string | null;
  readonly operationName: string;
  readonly startTime: Date;
  readonly endTime: Date;
  readonly status: SpanStatus;
  readonly attributes: Record<string, unknown>;
}

enum MetricUnit {
  MILLISECONDS = 'ms',
  SECONDS = 's',
  BYTES = 'B',
  KILOBYTES = 'KB',
  MEGABYTES = 'MB',
  COUNT = 'count',
  PERCENT = '%',
  REQUESTS_PER_SECOND = 'req/s',
}

enum SpanStatus {
  OK = 'OK',
  ERROR = 'ERROR',
  UNSET = 'UNSET',
}
```

### 1.2 Collection Flow

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ Stage Executor  │────►│ Event Collector │────►│ Buffer Queue    │
└─────────────────┘     └─────────────────┘     └────────┬────────┘
                                                         │
┌─────────────────┐     ┌─────────────────┐              │
│ Error Handler   │────►│ Metric Recorder │──────────────┤
└─────────────────┘     └─────────────────┘              │
                                                         │
┌─────────────────┐     ┌─────────────────┐              │
│ Loop Controller │────►│ Span Tracker    │──────────────┤
└─────────────────┘     └─────────────────┘              │
                                                         ▼
                                                ┌─────────────────┐
                                                │ Batch Processor │
                                                └────────┬────────┘
                                                         │
                              ┌───────────────┬──────────┴──────────┐
                              ▼               ▼                     ▼
                       ┌───────────┐   ┌───────────┐         ┌───────────┐
                       │project.db │   │ Realtime  │         │ Aggregator│
                       │ (persist) │   │ WebSocket │         │ (rollups) │
                       └───────────┘   └───────────┘         └───────────┘
```

---

## 2. Key Performance Indicators

### 2.1 Pipeline KPIs

```typescript
interface PipelineKPIs {
  // Success metrics
  readonly successRate: number;              // Target: ≥98%
  readonly completionRate: number;           // Executions that finish
  readonly averageAttempts: number;          // Target: ≤1.5
  
  // Performance metrics
  readonly averageDurationMs: number;
  readonly p50DurationMs: number;
  readonly p95DurationMs: number;
  readonly p99DurationMs: number;
  
  // Error metrics
  readonly errorRate: number;
  readonly retryRate: number;
  readonly escalationRate: number;           // Target: ≤2%
  
  // Throughput metrics
  readonly executionsPerHour: number;
  readonly stagesPerSecond: number;
  readonly dataProcessedBytes: number;
}

interface StageKPIs {
  readonly stageId: string;
  readonly stageName: string;
  readonly stageType: StageType;
  readonly successRate: number;
  readonly averageDurationMs: number;
  readonly p95DurationMs: number;
  readonly errorRate: number;
  readonly retryRate: number;
  readonly invocationCount: number;
}
```

### 2.2 KPI Thresholds

```typescript
interface KPIThreshold {
  readonly metric: string;
  readonly warningThreshold: number;
  readonly criticalThreshold: number;
  readonly direction: ThresholdDirection;
}

enum ThresholdDirection {
  ABOVE = 'ABOVE',              // Alert when value exceeds threshold
  BELOW = 'BELOW',              // Alert when value falls below threshold
}

const DEFAULT_THRESHOLDS: readonly KPIThreshold[] = [
  { metric: 'successRate', warningThreshold: 95, criticalThreshold: 90, direction: ThresholdDirection.BELOW },
  { metric: 'averageAttempts', warningThreshold: 2, criticalThreshold: 3, direction: ThresholdDirection.ABOVE },
  { metric: 'escalationRate', warningThreshold: 3, criticalThreshold: 5, direction: ThresholdDirection.ABOVE },
  { metric: 'p95DurationMs', warningThreshold: 10000, criticalThreshold: 30000, direction: ThresholdDirection.ABOVE },
  { metric: 'errorRate', warningThreshold: 5, criticalThreshold: 10, direction: ThresholdDirection.ABOVE },
];
```

---

## 3. Metrics Collection

### 3.1 Execution Metrics

```typescript
interface ExecutionMetrics {
  // Timing
  readonly totalDurationMs: number;
  readonly stageDurations: Map<string, number>;
  readonly blockDurations: Map<string, number>;
  readonly waitTimeMs: number;
  readonly retryDelayMs: number;
  
  // Counts
  readonly stagesExecuted: number;
  readonly stagesSucceeded: number;
  readonly stagesFailed: number;
  readonly stagesSkipped: number;
  readonly retryAttempts: number;
  readonly fallbacksUsed: number;
  
  // Resource usage
  readonly peakMemoryBytes: number;
  readonly networkRequestCount: number;
  readonly networkBytesTransferred: number;
  readonly databaseQueries: number;
  readonly databaseRowsAffected: number;
  
  // AI-specific (if applicable)
  readonly tokensConsumed: number;
  readonly modelInvocations: number;
  readonly consensusRounds: number;
}
```

### 3.2 Metric Recording

```typescript
interface MetricRecorder {
  // Counters (cumulative)
  incrementCounter(name: string, value?: number, dimensions?: Record<string, string>): void;
  
  // Gauges (point-in-time)
  setGauge(name: string, value: number, dimensions?: Record<string, string>): void;
  
  // Histograms (distributions)
  recordHistogram(name: string, value: number, dimensions?: Record<string, string>): void;
  
  // Timers
  startTimer(name: string): TimerHandle;
}

interface TimerHandle {
  stop(): number;  // Returns duration in ms
  cancel(): void;
}

// Usage example
const timer = recorder.startTimer('stage.execution');
try {
  await executeStage(stage);
  recorder.incrementCounter('stage.success', 1, { stageType: stage.type });
} catch (error) {
  recorder.incrementCounter('stage.failure', 1, { stageType: stage.type, errorCode: error.code });
  throw error;
} finally {
  const duration = timer.stop();
  recorder.recordHistogram('stage.duration', duration, { stageType: stage.type });
}
```

---

## 4. Distributed Tracing

### 4.1 Trace Context

```typescript
interface TraceContext {
  readonly traceId: string;
  readonly spanId: string;
  readonly parentSpanId: string | null;
  readonly sampled: boolean;
  readonly baggage: Map<string, string>;
}

interface SpanBuilder {
  setName(name: string): SpanBuilder;
  setParent(parentContext: TraceContext): SpanBuilder;
  setAttribute(key: string, value: unknown): SpanBuilder;
  setStatus(status: SpanStatus, message?: string): SpanBuilder;
  addEvent(name: string, attributes?: Record<string, unknown>): SpanBuilder;
  start(): Span;
}

interface Span {
  readonly context: TraceContext;
  setAttribute(key: string, value: unknown): void;
  addEvent(name: string, attributes?: Record<string, unknown>): void;
  setStatus(status: SpanStatus, message?: string): void;
  end(): void;
}
```

### 4.2 Trace Hierarchy

```
Trace: exec-12345
│
├── Span: Pipeline Execution
│   ├── Span: Block 1 - Data Fetch
│   │   ├── Span: Stage - HTTP Request
│   │   │   └── Span: Network Call
│   │   └── Span: Stage - Transform
│   │
│   ├── Span: Block 2 - Processing (parallel)
│   │   ├── Span: Loop Iteration 1
│   │   │   ├── Span: Stage - Validate
│   │   │   └── Span: Stage - Enrich
│   │   ├── Span: Loop Iteration 2
│   │   │   └── ...
│   │   └── Span: Loop Iteration N
│   │
│   └── Span: Block 3 - Save
│       ├── Span: Stage - DB Insert
│       │   └── Span: Database Query
│       └── Span: Stage - Notify
│           └── Span: HTTP Request
```

### 4.3 Span Attributes

```typescript
// Standard span attributes for pipeline execution
interface PipelineSpanAttributes {
  // Identification
  'pipeline.id': string;
  'pipeline.name': string;
  'execution.id': string;
  'block.id'?: string;
  'block.name'?: string;
  'stage.id'?: string;
  'stage.name'?: string;
  'stage.type'?: StageType;
  
  // Loop context
  'loop.id'?: string;
  'loop.iteration'?: number;
  'loop.total_iterations'?: number;
  
  // Error context
  'error.type'?: string;
  'error.code'?: string;
  'error.message'?: string;
  'error.retry_count'?: number;
  
  // Performance
  'duration_ms'?: number;
  'input_size_bytes'?: number;
  'output_size_bytes'?: number;
}
```

---

## 5. Real-Time Streaming

### 5.1 WebSocket Stream

```typescript
interface TelemetryStreamConfig {
  readonly executionId: string;
  readonly metrics: readonly string[];
  readonly updateIntervalMs: number;
  readonly aggregationWindow: AggregationWindow;
}

enum AggregationWindow {
  NONE = 'NONE',               // Raw events
  SECOND = 'SECOND',
  MINUTE = 'MINUTE',
}

interface TelemetryStreamMessage {
  readonly type: StreamMessageType;
  readonly timestamp: Date;
  readonly data: TelemetryData;
}

enum StreamMessageType {
  METRIC_UPDATE = 'METRIC_UPDATE',
  KPI_UPDATE = 'KPI_UPDATE',
  ALERT = 'ALERT',
  SPAN_COMPLETED = 'SPAN_COMPLETED',
  EXECUTION_COMPLETED = 'EXECUTION_COMPLETED',
}

type TelemetryData = 
  | MetricUpdateData
  | KPIUpdateData
  | AlertData
  | SpanCompletedData;
```

### 5.2 Stream Hook

```typescript
interface UseTelemetryStreamOptions {
  readonly executionId: string;
  readonly metrics: readonly string[];
  readonly updateIntervalMs: number;
}

interface UseTelemetryStreamResult {
  readonly isConnected: boolean;
  readonly currentMetrics: ExecutionMetrics;
  readonly kpis: PipelineKPIs;
  readonly alerts: readonly TelemetryAlert[];
  readonly error: Error | null;
}

function useTelemetryStream(
  options: UseTelemetryStreamOptions
): UseTelemetryStreamResult;

// Usage
const { currentMetrics, kpis, alerts } = useTelemetryStream({
  executionId: 'exec-123',
  metrics: ['duration', 'successRate', 'throughput'],
  updateIntervalMs: 1000,
});
```

---

## 6. Alerting System

### 6.1 Alert Configuration

```typescript
interface AlertRule {
  readonly id: string;
  readonly name: string;
  readonly description: string;
  readonly metric: string;
  readonly condition: AlertCondition;
  readonly severity: AlertSeverity;
  readonly cooldownMs: number;
  readonly actions: readonly AlertAction[];
}

interface AlertCondition {
  readonly operator: ConditionOperator;
  readonly threshold: number;
  readonly windowMs: number;
  readonly aggregation: AggregationType;
}

enum ConditionOperator {
  GREATER_THAN = 'GREATER_THAN',
  LESS_THAN = 'LESS_THAN',
  EQUALS = 'EQUALS',
  NOT_EQUALS = 'NOT_EQUALS',
  RATE_OF_CHANGE = 'RATE_OF_CHANGE',
}

enum AggregationType {
  LAST = 'LAST',
  AVERAGE = 'AVERAGE',
  SUM = 'SUM',
  MIN = 'MIN',
  MAX = 'MAX',
  COUNT = 'COUNT',
  PERCENTILE_95 = 'PERCENTILE_95',
}

enum AlertSeverity {
  INFO = 'INFO',
  WARNING = 'WARNING',
  ERROR = 'ERROR',
  CRITICAL = 'CRITICAL',
}

interface AlertAction {
  readonly type: AlertActionType;
  readonly config: Record<string, unknown>;
}

enum AlertActionType {
  LOG = 'LOG',
  NOTIFICATION = 'NOTIFICATION',
  WEBHOOK = 'WEBHOOK',
  PAUSE_EXECUTION = 'PAUSE_EXECUTION',
  TRIGGER_FALLBACK = 'TRIGGER_FALLBACK',
}
```

### 6.2 Alert Examples

```typescript
const EXAMPLE_ALERTS: readonly AlertRule[] = [
  {
    id: 'high-error-rate',
    name: 'High Error Rate',
    description: 'Error rate exceeds 10% over 5 minutes',
    metric: 'errorRate',
    condition: {
      operator: ConditionOperator.GREATER_THAN,
      threshold: 10,
      windowMs: 300000,
      aggregation: AggregationType.AVERAGE,
    },
    severity: AlertSeverity.ERROR,
    cooldownMs: 600000,
    actions: [
      { type: AlertActionType.NOTIFICATION, config: { channel: 'in-app' } },
      { type: AlertActionType.WEBHOOK, config: { url: '/api/alerts' } },
    ],
  },
  {
    id: 'slow-execution',
    name: 'Slow Execution',
    description: 'P95 duration exceeds 30 seconds',
    metric: 'p95DurationMs',
    condition: {
      operator: ConditionOperator.GREATER_THAN,
      threshold: 30000,
      windowMs: 60000,
      aggregation: AggregationType.LAST,
    },
    severity: AlertSeverity.WARNING,
    cooldownMs: 300000,
    actions: [
      { type: AlertActionType.LOG, config: { level: 'warn' } },
    ],
  },
];
```

### 6.3 Alert State

```typescript
interface TelemetryAlert {
  readonly id: string;
  readonly ruleId: string;
  readonly ruleName: string;
  readonly severity: AlertSeverity;
  readonly message: string;
  readonly currentValue: number;
  readonly threshold: number;
  readonly triggeredAt: Date;
  readonly resolvedAt: Date | null;
  readonly acknowledged: boolean;
  readonly acknowledgedBy: string | null;
}

interface AlertManager {
  getActiveAlerts(): readonly TelemetryAlert[];
  acknowledgeAlert(alertId: string): void;
  resolveAlert(alertId: string): void;
  snoozeAlert(alertId: string, durationMs: number): void;
}
```

---

## 7. Dashboard Components

### 7.1 Metrics Overview Panel

```typescript
interface MetricsOverviewProps {
  readonly executionId: string;
  readonly kpis: PipelineKPIs;
  readonly previousKpis: PipelineKPIs | null;
  readonly timeRange: TimeRange;
}

interface MetricCard {
  readonly label: string;
  readonly value: number;
  readonly unit: string;
  readonly change: number | null;
  readonly changeDirection: 'up' | 'down' | 'neutral';
  readonly status: 'success' | 'warning' | 'error' | 'neutral';
}

// Visual:
// ┌────────────────┬────────────────┬────────────────┬────────────────┐
// │ Success Rate   │ Avg Duration   │ Avg Attempts   │ Escalations    │
// │    98.2%       │    4.2s        │     1.3        │    0.8%        │
// │  ↑ +0.5%       │  ↓ -1.1s       │  ↓ -0.2        │  ↓ -0.3%       │
// │  ✓ Healthy     │  ✓ Healthy     │  ✓ Healthy     │  ✓ Healthy     │
// └────────────────┴────────────────┴────────────────┴────────────────┘
```

### 7.2 Time Series Charts

```typescript
interface TimeSeriesChartProps {
  readonly metric: string;
  readonly data: readonly TimeSeriesDataPoint[];
  readonly timeRange: TimeRange;
  readonly granularity: Granularity;
  readonly thresholds: readonly ChartThreshold[];
}

interface TimeSeriesDataPoint {
  readonly timestamp: Date;
  readonly value: number;
  readonly metadata: Record<string, unknown>;
}

interface ChartThreshold {
  readonly value: number;
  readonly label: string;
  readonly color: string;
  readonly style: 'solid' | 'dashed';
}

enum Granularity {
  SECOND = 'SECOND',
  MINUTE = 'MINUTE',
  HOUR = 'HOUR',
  DAY = 'DAY',
}
```

### 7.3 Stage Heatmap

```typescript
interface StageHeatmapProps {
  readonly stages: readonly StageKPIs[];
  readonly metric: 'successRate' | 'duration' | 'errorRate';
  readonly timeRange: TimeRange;
  readonly onStageClick: (stageId: string) => void;
}

// Visual heatmap showing performance by stage
// Darker = worse performance
// Click to drill down into specific stage
```

### 7.4 Error Distribution

```typescript
interface ErrorDistributionProps {
  readonly errors: readonly ErrorSummary[];
  readonly viewMode: 'pie' | 'bar' | 'treemap';
  readonly groupBy: 'category' | 'code' | 'stage';
}

interface ErrorSummary {
  readonly key: string;
  readonly label: string;
  readonly count: number;
  readonly percentage: number;
  readonly trend: number;
}
```

---

## 8. Historical Analysis

### 8.1 Trend Analysis

```typescript
interface TrendAnalysis {
  readonly metric: string;
  readonly timeRange: TimeRange;
  readonly trend: TrendDirection;
  readonly trendStrength: number;        // 0-1
  readonly forecast: readonly ForecastPoint[];
  readonly anomalies: readonly AnomalyPoint[];
}

enum TrendDirection {
  IMPROVING = 'IMPROVING',
  DEGRADING = 'DEGRADING',
  STABLE = 'STABLE',
}

interface ForecastPoint {
  readonly timestamp: Date;
  readonly predictedValue: number;
  readonly confidenceInterval: ConfidenceInterval;
}

interface ConfidenceInterval {
  readonly lower: number;
  readonly upper: number;
  readonly confidence: number;          // 0-1 (e.g., 0.95 for 95%)
}

interface AnomalyPoint {
  readonly timestamp: Date;
  readonly actualValue: number;
  readonly expectedValue: number;
  readonly deviation: number;
  readonly severity: 'low' | 'medium' | 'high';
}
```

### 8.2 Comparison Reports

```typescript
interface ComparisonReportProps {
  readonly pipelineId: string;
  readonly periodA: TimeRange;
  readonly periodB: TimeRange;
  readonly metrics: readonly string[];
}

interface ComparisonResult {
  readonly metric: string;
  readonly periodAValue: number;
  readonly periodBValue: number;
  readonly absoluteChange: number;
  readonly percentageChange: number;
  readonly significance: 'significant' | 'not-significant';
}
```

### 8.3 Regression Detection

```typescript
interface RegressionDetector {
  detect(
    pipelineId: string,
    metric: string,
    baselineRange: TimeRange,
    currentRange: TimeRange
  ): Promise<RegressionResult>;
}

interface RegressionResult {
  readonly detected: boolean;
  readonly baselineValue: number;
  readonly currentValue: number;
  readonly degradation: number;
  readonly confidence: number;
  readonly probableCauses: readonly ProbableCause[];
}

interface ProbableCause {
  readonly type: CauseType;
  readonly description: string;
  readonly evidence: Record<string, unknown>;
  readonly confidence: number;
}

enum CauseType {
  CODE_CHANGE = 'CODE_CHANGE',
  CONFIG_CHANGE = 'CONFIG_CHANGE',
  DEPENDENCY_CHANGE = 'DEPENDENCY_CHANGE',
  EXTERNAL_SERVICE = 'EXTERNAL_SERVICE',
  DATA_VOLUME = 'DATA_VOLUME',
  UNKNOWN = 'UNKNOWN',
}
```

---

## 9. Data Aggregation

### 9.1 Rollup Configuration

```typescript
interface RollupConfig {
  readonly granularity: Granularity;
  readonly retentionDays: number;
  readonly aggregations: readonly AggregationConfig[];
}

interface AggregationConfig {
  readonly metric: string;
  readonly functions: readonly AggregationType[];
  readonly dimensions: readonly string[];
}

const ROLLUP_CONFIG: readonly RollupConfig[] = [
  {
    granularity: Granularity.MINUTE,
    retentionDays: 7,
    aggregations: [
      { metric: 'duration', functions: [AggregationType.AVERAGE, AggregationType.PERCENTILE_95], dimensions: ['stageType'] },
      { metric: 'successRate', functions: [AggregationType.AVERAGE], dimensions: ['pipelineId'] },
    ],
  },
  {
    granularity: Granularity.HOUR,
    retentionDays: 30,
    aggregations: [
      { metric: 'duration', functions: [AggregationType.AVERAGE, AggregationType.PERCENTILE_95], dimensions: ['stageType'] },
      { metric: 'successRate', functions: [AggregationType.AVERAGE], dimensions: ['pipelineId'] },
      { metric: 'errorRate', functions: [AggregationType.AVERAGE, AggregationType.MAX], dimensions: ['errorCategory'] },
    ],
  },
  {
    granularity: Granularity.DAY,
    retentionDays: 365,
    aggregations: [
      { metric: 'duration', functions: [AggregationType.AVERAGE, AggregationType.PERCENTILE_95], dimensions: ['stageType'] },
      { metric: 'successRate', functions: [AggregationType.AVERAGE], dimensions: ['pipelineId'] },
      { metric: 'executionCount', functions: [AggregationType.SUM], dimensions: ['pipelineId'] },
    ],
  },
];
```

### 9.2 Query Interface

```typescript
interface TelemetryQuery {
  readonly metric: string;
  readonly timeRange: TimeRange;
  readonly granularity: Granularity;
  readonly aggregation: AggregationType;
  readonly dimensions: readonly DimensionFilter[];
  readonly groupBy: readonly string[];
}

interface DimensionFilter {
  readonly dimension: string;
  readonly operator: FilterOperator;
  readonly value: string | readonly string[];
}

enum FilterOperator {
  EQUALS = 'EQUALS',
  NOT_EQUALS = 'NOT_EQUALS',
  IN = 'IN',
  NOT_IN = 'NOT_IN',
  CONTAINS = 'CONTAINS',
  STARTS_WITH = 'STARTS_WITH',
}

interface TelemetryQueryResult {
  readonly data: readonly TelemetryRow[];
  readonly metadata: QueryMetadata;
}

interface TelemetryRow {
  readonly timestamp: Date;
  readonly value: number;
  readonly dimensions: Record<string, string>;
}

interface QueryMetadata {
  readonly executionTimeMs: number;
  readonly rowCount: number;
  readonly fromCache: boolean;
}
```

---

## 10. Database Schema

### 10.1 Telemetry Tables

```sql
-- Raw Metrics (stored in project.db, short retention)
CREATE TABLE TelemetryMetric (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  MetricName TEXT NOT NULL,
  Value REAL NOT NULL,
  Unit TEXT NOT NULL,
  DimensionsJson TEXT NOT NULL DEFAULT '{}',
  Timestamp TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Spans (stored in project.db)
CREATE TABLE TelemetrySpan (
  Id TEXT PRIMARY KEY,
  TraceId TEXT NOT NULL,
  SpanId TEXT NOT NULL,
  ParentSpanId TEXT,
  OperationName TEXT NOT NULL,
  Status TEXT NOT NULL,
  AttributesJson TEXT NOT NULL DEFAULT '{}',
  StartTime TEXT NOT NULL,
  EndTime TEXT NOT NULL,
  DurationMs INTEGER NOT NULL
);

-- Hourly Aggregates
CREATE TABLE TelemetryHourly (
  Id TEXT PRIMARY KEY,
  PipelineId TEXT NOT NULL REFERENCES Pipeline(Id),
  MetricName TEXT NOT NULL,
  AggregationType TEXT NOT NULL,
  Value REAL NOT NULL,
  DimensionsJson TEXT NOT NULL DEFAULT '{}',
  HourTimestamp TEXT NOT NULL,
  SampleCount INTEGER NOT NULL,
  CreatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Daily Aggregates
CREATE TABLE TelemetryDaily (
  Id TEXT PRIMARY KEY,
  PipelineId TEXT NOT NULL REFERENCES Pipeline(Id),
  MetricName TEXT NOT NULL,
  AggregationType TEXT NOT NULL,
  Value REAL NOT NULL,
  DimensionsJson TEXT NOT NULL DEFAULT '{}',
  DateTimestamp TEXT NOT NULL,
  SampleCount INTEGER NOT NULL,
  CreatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Alerts
CREATE TABLE TelemetryAlert (
  Id TEXT PRIMARY KEY,
  RuleId TEXT NOT NULL,
  RuleName TEXT NOT NULL,
  Severity TEXT NOT NULL,
  Message TEXT NOT NULL,
  CurrentValue REAL NOT NULL,
  Threshold REAL NOT NULL,
  ExecutionId TEXT REFERENCES PipelineExecution(Id),
  TriggeredAt TEXT NOT NULL DEFAULT (datetime('now')),
  ResolvedAt TEXT,
  Acknowledged INTEGER NOT NULL DEFAULT 0,
  AcknowledgedBy TEXT
);

CREATE INDEX idx_metric_execution ON TelemetryMetric(ExecutionId);
CREATE INDEX idx_metric_name ON TelemetryMetric(MetricName);
CREATE INDEX idx_metric_timestamp ON TelemetryMetric(Timestamp);
CREATE INDEX idx_span_trace ON TelemetrySpan(TraceId);
CREATE INDEX idx_hourly_pipeline ON TelemetryHourly(PipelineId, HourTimestamp);
CREATE INDEX idx_daily_pipeline ON TelemetryDaily(PipelineId, DateTimestamp);
CREATE INDEX idx_alert_triggered ON TelemetryAlert(TriggeredAt);
```

---

## 11. Export and Integration

### 11.1 Export Formats

```typescript
interface TelemetryExporter {
  exportCSV(query: TelemetryQuery): Promise<string>;
  exportJSON(query: TelemetryQuery): Promise<string>;
  exportPrometheus(metrics: readonly string[]): Promise<string>;
  exportOpenTelemetry(spans: readonly TelemetrySpan[]): Promise<Uint8Array>;
}
```

### 11.2 Webhook Integration

```typescript
interface TelemetryWebhook {
  readonly id: string;
  readonly url: string;
  readonly events: readonly TelemetryEventType[];
  readonly filters: readonly WebhookFilter[];
  readonly headers: Record<string, string>;
  readonly enabled: boolean;
}

enum TelemetryEventType {
  EXECUTION_STARTED = 'EXECUTION_STARTED',
  EXECUTION_COMPLETED = 'EXECUTION_COMPLETED',
  EXECUTION_FAILED = 'EXECUTION_FAILED',
  ALERT_TRIGGERED = 'ALERT_TRIGGERED',
  ALERT_RESOLVED = 'ALERT_RESOLVED',
  THRESHOLD_BREACHED = 'THRESHOLD_BREACHED',
}

interface WebhookPayload {
  readonly event: TelemetryEventType;
  readonly timestamp: string;
  readonly pipelineId: string;
  readonly executionId: string | null;
  readonly data: Record<string, unknown>;
  readonly signature: string;            // HMAC-SHA256
}
```

---

## Related Specs

- [Live Execution View](./16-live-execution-view.md)
- [Debug Inspector](./17-debug-inspector.md)
- [Telemetry Dashboard](../../11-telemetry/00-overview.md)
- [Resilient Execution System](../../06-ai-integration/12-resilient-execution-system.md)
