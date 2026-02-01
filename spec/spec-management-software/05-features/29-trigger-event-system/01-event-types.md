# Specification: Event Types

**Version:** 1.0.0  
**Status:** Specified  
**Updated:** 2026-01-31  
**Parent:** [Trigger Event System](./00-overview.md)

---

## Purpose

Define the complete event taxonomy, payload schemas, versioning strategy, and validation rules for all events flowing through the Trigger Event System. This specification serves as the authoritative source for event structure across all services.

---

## Event Taxonomy

### Category Hierarchy

```
TriggerEvent
├── file.*          (File System Events)
│   ├── file.created
│   ├── file.updated
│   ├── file.deleted
│   ├── file.moved
│   ├── file.renamed
│   └── file.permission_changed
├── user.*          (User & Auth Events)
│   ├── user.login
│   ├── user.logout
│   ├── user.session.created
│   ├── user.session.expired
│   ├── user.action
│   └── user.preference.changed
├── ai.*            (AI Operation Events)
│   ├── ai.completion.started
│   ├── ai.completion.finished
│   ├── ai.completion.failed
│   ├── ai.stream.started
│   ├── ai.stream.chunk
│   ├── ai.stream.ended
│   ├── ai.stream.error
│   ├── ai.embedding.created
│   └── ai.token.usage
├── pipeline.*      (Pipeline Execution Events)
│   ├── pipeline.created
│   ├── pipeline.started
│   ├── pipeline.completed
│   ├── pipeline.failed
│   ├── pipeline.cancelled
│   └── pipeline.paused
├── stage.*         (Pipeline Stage Events)
│   ├── stage.started
│   ├── stage.completed
│   ├── stage.failed
│   ├── stage.skipped
│   ├── stage.retrying
│   └── stage.timeout
├── knowledge.*     (Knowledge/RAG Events)
│   ├── knowledge.indexed
│   ├── knowledge.reindexed
│   ├── knowledge.deleted
│   ├── knowledge.query.started
│   ├── knowledge.query.completed
│   └── knowledge.sync.completed
├── project.*       (Project Management Events)
│   ├── project.created
│   ├── project.opened
│   ├── project.closed
│   ├── project.exported
│   ├── project.imported
│   ├── project.archived
│   └── project.settings.changed
├── system.*        (System & Health Events)
│   ├── system.startup
│   ├── system.shutdown
│   ├── system.health.check
│   ├── system.config.changed
│   ├── system.backup.completed
│   ├── system.maintenance.started
│   └── system.maintenance.ended
└── webhook.*       (Webhook Lifecycle Events)
    ├── webhook.registered
    ├── webhook.triggered
    ├── webhook.delivered
    ├── webhook.failed
    └── webhook.disabled
```

---

## Core Enums

```typescript
/**
 * Primary event type categories
 */
export enum EventType {
  FILE = 'FILE',
  USER = 'USER',
  AI = 'AI',
  PIPELINE = 'PIPELINE',
  STAGE = 'STAGE',
  KNOWLEDGE = 'KNOWLEDGE',
  PROJECT = 'PROJECT',
  SYSTEM = 'SYSTEM',
  WEBHOOK = 'WEBHOOK',
}

/**
 * Event priority levels for processing order
 */
export enum EventPriority {
  CRITICAL = 'CRITICAL',   // Process immediately (system health)
  HIGH = 'HIGH',           // Process within 100ms (user actions)
  NORMAL = 'NORMAL',       // Standard processing (file changes)
  LOW = 'LOW',             // Background processing (analytics)
  BULK = 'BULK',           // Batch processing (mass imports)
}

/**
 * Event delivery status tracking
 */
export enum EventDeliveryStatus {
  PENDING = 'PENDING',
  DELIVERED = 'DELIVERED',
  FAILED = 'FAILED',
  RETRYING = 'RETRYING',
  DEAD_LETTER = 'DEAD_LETTER',
  CANCELLED = 'CANCELLED',
}

/**
 * Event schema version status
 */
export enum SchemaStatus {
  CURRENT = 'CURRENT',
  DEPRECATED = 'DEPRECATED',
  SUNSET = 'SUNSET',
}
```

---

## Base Event Interface

```typescript
/**
 * Base interface for all trigger events
 * All events MUST conform to this structure
 */
export interface TriggerEvent<T extends EventPayload = EventPayload> {
  /** UUID v7 - time-sortable unique identifier */
  readonly id: string;
  
  /** Event type category */
  readonly type: EventType;
  
  /** Fully qualified event name (e.g., "file.created") */
  readonly name: string;
  
  /** Schema version following semver (e.g., "1.0.0") */
  readonly version: string;
  
  /** ISO 8601 timestamp with timezone */
  readonly timestamp: string;
  
  /** Event priority for processing */
  readonly priority: EventPriority;
  
  /** Source service identification */
  readonly source: EventSource;
  
  /** Correlation ID for request tracing */
  readonly correlationId: string;
  
  /** Causation ID - event that caused this event */
  readonly causationId?: string;
  
  /** Type-specific payload data */
  readonly payload: T;
  
  /** Common metadata fields */
  readonly metadata: EventMetadata;
}

/**
 * Event source identification
 */
export interface EventSource {
  /** Service name (e.g., "file-service", "ai-bridge") */
  readonly service: string;
  
  /** Instance identifier for horizontal scaling */
  readonly instance: string;
  
  /** Service semantic version */
  readonly version: string;
  
  /** Environment (development, staging, production) */
  readonly environment: string;
}

/**
 * Common metadata for all events
 */
export interface EventMetadata {
  /** User who triggered the event (if applicable) */
  readonly userId?: string;
  
  /** Project context identifier */
  readonly projectId?: string;
  
  /** Session identifier */
  readonly sessionId?: string;
  
  /** Distributed tracing ID (OpenTelemetry compatible) */
  readonly traceId?: string;
  
  /** Span ID for distributed tracing */
  readonly spanId?: string;
  
  /** Parent span ID */
  readonly parentSpanId?: string;
  
  /** Custom tags for filtering and grouping */
  readonly tags?: Readonly<Record<string, string>>;
  
  /** Event retention policy override */
  readonly retentionDays?: number;
  
  /** Whether event should be persisted */
  readonly persist?: boolean;
}

/**
 * Base payload interface - extended by specific event types
 */
export interface EventPayload {
  [key: string]: unknown;
}
```

---

## File Event Payloads

```typescript
/**
 * File event payload types
 */
export interface FileCreatedPayload extends EventPayload {
  readonly path: string;
  readonly name: string;
  readonly extension: string;
  readonly mimeType: string;
  readonly size: number;
  readonly checksum: string;
  readonly parentPath: string;
  readonly createdBy: string;
  readonly isDirectory: boolean;
}

export interface FileUpdatedPayload extends EventPayload {
  readonly path: string;
  readonly name: string;
  readonly previousChecksum: string;
  readonly newChecksum: string;
  readonly previousSize: number;
  readonly newSize: number;
  readonly changeType: FileChangeType;
  readonly modifiedBy: string;
  readonly diff?: FileDiff;
}

export interface FileDeletedPayload extends EventPayload {
  readonly path: string;
  readonly name: string;
  readonly deletedBy: string;
  readonly permanent: boolean;
  readonly previousChecksum: string;
}

export interface FileMovedPayload extends EventPayload {
  readonly previousPath: string;
  readonly newPath: string;
  readonly name: string;
  readonly movedBy: string;
}

export interface FileRenamedPayload extends EventPayload {
  readonly path: string;
  readonly previousName: string;
  readonly newName: string;
  readonly renamedBy: string;
}

export enum FileChangeType {
  CONTENT = 'CONTENT',
  METADATA = 'METADATA',
  PERMISSIONS = 'PERMISSIONS',
  BOTH = 'BOTH',
}

export interface FileDiff {
  readonly additions: number;
  readonly deletions: number;
  readonly hunks: number;
}
```

---

## AI Event Payloads

```typescript
/**
 * AI completion event payloads
 */
export interface AiCompletionStartedPayload extends EventPayload {
  readonly requestId: string;
  readonly provider: string;
  readonly model: string;
  readonly promptTokens: number;
  readonly maxTokens: number;
  readonly temperature: number;
  readonly streaming: boolean;
}

export interface AiCompletionFinishedPayload extends EventPayload {
  readonly requestId: string;
  readonly provider: string;
  readonly model: string;
  readonly promptTokens: number;
  readonly completionTokens: number;
  readonly totalTokens: number;
  readonly durationMs: number;
  readonly finishReason: AiFinishReason;
  readonly cached: boolean;
}

export interface AiCompletionFailedPayload extends EventPayload {
  readonly requestId: string;
  readonly provider: string;
  readonly model: string;
  readonly errorCode: string;
  readonly errorMessage: string;
  readonly retryable: boolean;
  readonly durationMs: number;
}

export interface AiStreamChunkPayload extends EventPayload {
  readonly requestId: string;
  readonly chunkIndex: number;
  readonly tokenCount: number;
  readonly content: string;
  readonly finishReason?: AiFinishReason;
}

export interface AiEmbeddingCreatedPayload extends EventPayload {
  readonly requestId: string;
  readonly provider: string;
  readonly model: string;
  readonly inputTokens: number;
  readonly dimensions: number;
  readonly documentCount: number;
  readonly durationMs: number;
}

export enum AiFinishReason {
  STOP = 'STOP',
  LENGTH = 'LENGTH',
  CONTENT_FILTER = 'CONTENT_FILTER',
  TOOL_CALLS = 'TOOL_CALLS',
  ERROR = 'ERROR',
}
```

---

## Pipeline Event Payloads

```typescript
/**
 * Pipeline lifecycle event payloads
 */
export interface PipelineStartedPayload extends EventPayload {
  readonly pipelineId: string;
  readonly pipelineName: string;
  readonly executionId: string;
  readonly triggerType: PipelineTriggerType;
  readonly triggeredBy: string;
  readonly stageCount: number;
  readonly variables: Readonly<Record<string, unknown>>;
}

export interface PipelineCompletedPayload extends EventPayload {
  readonly pipelineId: string;
  readonly executionId: string;
  readonly status: PipelineStatus;
  readonly stagesCompleted: number;
  readonly stagesFailed: number;
  readonly stagesSkipped: number;
  readonly durationMs: number;
  readonly outputs: Readonly<Record<string, unknown>>;
}

export interface PipelineFailedPayload extends EventPayload {
  readonly pipelineId: string;
  readonly executionId: string;
  readonly failedStageId: string;
  readonly failedStageName: string;
  readonly errorCode: string;
  readonly errorMessage: string;
  readonly durationMs: number;
  readonly retryable: boolean;
}

export interface StageStartedPayload extends EventPayload {
  readonly pipelineId: string;
  readonly executionId: string;
  readonly stageId: string;
  readonly stageName: string;
  readonly stageIndex: number;
  readonly blockCount: number;
  readonly inputs: Readonly<Record<string, unknown>>;
}

export interface StageCompletedPayload extends EventPayload {
  readonly pipelineId: string;
  readonly executionId: string;
  readonly stageId: string;
  readonly stageName: string;
  readonly status: StageStatus;
  readonly blocksCompleted: number;
  readonly durationMs: number;
  readonly outputs: Readonly<Record<string, unknown>>;
}

export enum PipelineTriggerType {
  MANUAL = 'MANUAL',
  SCHEDULED = 'SCHEDULED',
  EVENT = 'EVENT',
  API = 'API',
  WEBHOOK = 'WEBHOOK',
}

export enum PipelineStatus {
  COMPLETED = 'COMPLETED',
  FAILED = 'FAILED',
  CANCELLED = 'CANCELLED',
  TIMEOUT = 'TIMEOUT',
}

export enum StageStatus {
  COMPLETED = 'COMPLETED',
  FAILED = 'FAILED',
  SKIPPED = 'SKIPPED',
  TIMEOUT = 'TIMEOUT',
}
```

---

## Knowledge Event Payloads

```typescript
/**
 * Knowledge/RAG event payloads
 */
export interface KnowledgeIndexedPayload extends EventPayload {
  readonly documentId: string;
  readonly filePath: string;
  readonly chunkCount: number;
  readonly embeddingModel: string;
  readonly dimensions: number;
  readonly processingMs: number;
  readonly vectorStoreId: string;
}

export interface KnowledgeQueryCompletedPayload extends EventPayload {
  readonly queryId: string;
  readonly query: string;
  readonly resultsCount: number;
  readonly topScore: number;
  readonly searchType: KnowledgeSearchType;
  readonly processingMs: number;
  readonly tokensUsed: number;
}

export interface KnowledgeSyncCompletedPayload extends EventPayload {
  readonly syncId: string;
  readonly documentsProcessed: number;
  readonly documentsAdded: number;
  readonly documentsUpdated: number;
  readonly documentsDeleted: number;
  readonly durationMs: number;
}

export enum KnowledgeSearchType {
  SEMANTIC = 'SEMANTIC',
  KEYWORD = 'KEYWORD',
  HYBRID = 'HYBRID',
}
```

---

## System Event Payloads

```typescript
/**
 * System lifecycle event payloads
 */
export interface SystemStartupPayload extends EventPayload {
  readonly serviceId: string;
  readonly serviceName: string;
  readonly version: string;
  readonly environment: string;
  readonly startupDurationMs: number;
  readonly configHash: string;
}

export interface SystemShutdownPayload extends EventPayload {
  readonly serviceId: string;
  readonly serviceName: string;
  readonly reason: ShutdownReason;
  readonly graceful: boolean;
  readonly uptimeSeconds: number;
}

export interface SystemHealthCheckPayload extends EventPayload {
  readonly serviceId: string;
  readonly status: HealthStatus;
  readonly checks: Readonly<Record<string, HealthCheckResult>>;
  readonly responseTimeMs: number;
}

export interface SystemConfigChangedPayload extends EventPayload {
  readonly configKey: string;
  readonly previousValue?: string;
  readonly newValue: string;
  readonly changedBy: string;
  readonly requiresRestart: boolean;
}

export enum ShutdownReason {
  GRACEFUL = 'GRACEFUL',
  SIGTERM = 'SIGTERM',
  SIGINT = 'SIGINT',
  ERROR = 'ERROR',
  MAINTENANCE = 'MAINTENANCE',
}

export enum HealthStatus {
  HEALTHY = 'HEALTHY',
  DEGRADED = 'DEGRADED',
  UNHEALTHY = 'UNHEALTHY',
}

export interface HealthCheckResult {
  readonly name: string;
  readonly status: HealthStatus;
  readonly message?: string;
  readonly durationMs: number;
}
```

---

## User Event Payloads

```typescript
/**
 * User authentication and action event payloads
 */
export interface UserLoginPayload extends EventPayload {
  readonly userId: string;
  readonly method: AuthMethod;
  readonly ipAddress: string;
  readonly userAgent: string;
  readonly sessionId: string;
  readonly mfaUsed: boolean;
}

export interface UserLogoutPayload extends EventPayload {
  readonly userId: string;
  readonly sessionId: string;
  readonly reason: LogoutReason;
  readonly sessionDurationSeconds: number;
}

export interface UserActionPayload extends EventPayload {
  readonly userId: string;
  readonly action: string;
  readonly resource: string;
  readonly resourceId?: string;
  readonly details?: Readonly<Record<string, unknown>>;
}

export enum AuthMethod {
  PASSWORD = 'PASSWORD',
  SSO = 'SSO',
  API_KEY = 'API_KEY',
  OAUTH = 'OAUTH',
  TOKEN_REFRESH = 'TOKEN_REFRESH',
}

export enum LogoutReason {
  USER_INITIATED = 'USER_INITIATED',
  SESSION_EXPIRED = 'SESSION_EXPIRED',
  TOKEN_REVOKED = 'TOKEN_REVOKED',
  FORCED = 'FORCED',
  IDLE_TIMEOUT = 'IDLE_TIMEOUT',
}
```

---

## Project Event Payloads

```typescript
/**
 * Project management event payloads
 */
export interface ProjectCreatedPayload extends EventPayload {
  readonly projectId: string;
  readonly projectName: string;
  readonly createdBy: string;
  readonly template?: string;
  readonly initialFileCount: number;
}

export interface ProjectOpenedPayload extends EventPayload {
  readonly projectId: string;
  readonly projectName: string;
  readonly openedBy: string;
  readonly lastOpenedAt?: string;
}

export interface ProjectExportedPayload extends EventPayload {
  readonly projectId: string;
  readonly projectName: string;
  readonly exportedBy: string;
  readonly format: ExportFormat;
  readonly fileCount: number;
  readonly totalSize: number;
  readonly durationMs: number;
}

export enum ExportFormat {
  ZIP = 'ZIP',
  TAR_GZ = 'TAR_GZ',
  JSON = 'JSON',
  MARKDOWN = 'MARKDOWN',
}
```

---

## Webhook Event Payloads

```typescript
/**
 * Webhook lifecycle event payloads
 */
export interface WebhookTriggeredPayload extends EventPayload {
  readonly webhookId: string;
  readonly endpointUrl: string;
  readonly triggerEventId: string;
  readonly triggerEventName: string;
  readonly attempt: number;
}

export interface WebhookDeliveredPayload extends EventPayload {
  readonly webhookId: string;
  readonly endpointUrl: string;
  readonly triggerEventId: string;
  readonly statusCode: number;
  readonly responseTimeMs: number;
  readonly attempt: number;
}

export interface WebhookFailedPayload extends EventPayload {
  readonly webhookId: string;
  readonly endpointUrl: string;
  readonly triggerEventId: string;
  readonly statusCode?: number;
  readonly errorMessage: string;
  readonly attempt: number;
  readonly maxAttempts: number;
  readonly nextRetryAt?: string;
}
```

---

## Versioning Strategy

### Semantic Versioning Rules

All event schemas follow semantic versioning (`MAJOR.MINOR.PATCH`):

| Change Type | Version Bump | Backward Compatible |
|-------------|--------------|---------------------|
| New optional field | MINOR | ✅ Yes |
| Field deprecation | MINOR | ✅ Yes (with warning) |
| Bug fix in docs | PATCH | ✅ Yes |
| Remove field | MAJOR | ❌ No |
| Change field type | MAJOR | ❌ No |
| Rename field | MAJOR | ❌ No |
| New required field | MAJOR | ❌ No |

### Version Lifecycle

```typescript
/**
 * Schema version metadata
 */
export interface SchemaVersion {
  readonly version: string;
  readonly status: SchemaStatus;
  readonly releasedAt: string;
  readonly deprecatedAt?: string;
  readonly sunsetAt?: string;
  readonly changelog: string;
}

/**
 * Version registry for event schemas
 */
export interface EventSchemaRegistry {
  readonly eventName: string;
  readonly currentVersion: string;
  readonly supportedVersions: readonly SchemaVersion[];
  readonly migrationPath: Readonly<Record<string, string>>;
}
```

### Version Support Timeline

| Status | Meaning | Duration |
|--------|---------|----------|
| CURRENT | Actively supported | Until next MAJOR |
| DEPRECATED | Supported but discouraged | 6 months |
| SUNSET | No longer processed | After sunset date |

### Schema Migration

```typescript
/**
 * Event schema migration interface
 */
export interface SchemaMigration {
  readonly fromVersion: string;
  readonly toVersion: string;
  readonly eventName: string;
  
  /** Transform payload from old to new schema */
  migrate(payload: EventPayload): EventPayload;
  
  /** Validate migrated payload */
  validate(payload: EventPayload): ValidationResult;
}
```

---

## Validation Rules

### Required Field Validation

```typescript
/**
 * Event validation result
 */
export interface ValidationResult {
  readonly valid: boolean;
  readonly errors: readonly ValidationError[];
  readonly warnings: readonly ValidationWarning[];
}

export interface ValidationError {
  readonly field: string;
  readonly code: string;
  readonly message: string;
}

export interface ValidationWarning {
  readonly field: string;
  readonly code: string;
  readonly message: string;
  readonly deprecatedIn?: string;
  readonly removedIn?: string;
}

/**
 * Validation error codes
 */
export enum ValidationErrorCode {
  REQUIRED_FIELD_MISSING = 'REQUIRED_FIELD_MISSING',
  INVALID_TYPE = 'INVALID_TYPE',
  INVALID_FORMAT = 'INVALID_FORMAT',
  INVALID_ENUM_VALUE = 'INVALID_ENUM_VALUE',
  INVALID_UUID = 'INVALID_UUID',
  INVALID_TIMESTAMP = 'INVALID_TIMESTAMP',
  INVALID_SEMVER = 'INVALID_SEMVER',
  UNKNOWN_EVENT_TYPE = 'UNKNOWN_EVENT_TYPE',
  UNSUPPORTED_VERSION = 'UNSUPPORTED_VERSION',
  PAYLOAD_TOO_LARGE = 'PAYLOAD_TOO_LARGE',
}
```

### Field Format Rules

| Field | Format | Validation |
|-------|--------|------------|
| `id` | UUID v7 | Must be valid UUID v7 (time-sortable) |
| `timestamp` | ISO 8601 | Must include timezone |
| `version` | Semver | Must match `X.Y.Z` pattern |
| `correlationId` | UUID | Must be valid UUID |
| `name` | Dot notation | Must match `category.action` pattern |
| `tags` | Key-value | Keys alphanumeric, max 64 chars |

### Payload Size Limits

| Category | Max Payload Size | Notes |
|----------|------------------|-------|
| FILE | 10 KB | Excludes file content |
| AI | 50 KB | Stream chunks limited |
| PIPELINE | 100 KB | Variables included |
| SYSTEM | 5 KB | Minimal footprint |
| WEBHOOK | 1 MB | Full request/response |
| DEFAULT | 10 KB | Standard limit |

---

## Event Factory

```typescript
/**
 * Factory for creating validated events
 */
export interface EventFactory {
  /**
   * Create a file event
   */
  createFileEvent<T extends FileEventPayload>(
    name: FileEventName,
    payload: T,
    metadata?: Partial<EventMetadata>
  ): TriggerEvent<T>;

  /**
   * Create an AI event
   */
  createAiEvent<T extends AiEventPayload>(
    name: AiEventName,
    payload: T,
    metadata?: Partial<EventMetadata>
  ): TriggerEvent<T>;

  /**
   * Create a pipeline event
   */
  createPipelineEvent<T extends PipelineEventPayload>(
    name: PipelineEventName,
    payload: T,
    metadata?: Partial<EventMetadata>
  ): TriggerEvent<T>;

  /**
   * Create a system event
   */
  createSystemEvent<T extends SystemEventPayload>(
    name: SystemEventName,
    payload: T,
    metadata?: Partial<EventMetadata>
  ): TriggerEvent<T>;

  /**
   * Create a generic event
   */
  createEvent<T extends EventPayload>(
    type: EventType,
    name: string,
    payload: T,
    metadata?: Partial<EventMetadata>
  ): TriggerEvent<T>;
}

/**
 * Event name type literals
 */
export type FileEventName = 
  | 'file.created'
  | 'file.updated'
  | 'file.deleted'
  | 'file.moved'
  | 'file.renamed'
  | 'file.permission_changed';

export type AiEventName =
  | 'ai.completion.started'
  | 'ai.completion.finished'
  | 'ai.completion.failed'
  | 'ai.stream.started'
  | 'ai.stream.chunk'
  | 'ai.stream.ended'
  | 'ai.stream.error'
  | 'ai.embedding.created'
  | 'ai.token.usage';

export type PipelineEventName =
  | 'pipeline.created'
  | 'pipeline.started'
  | 'pipeline.completed'
  | 'pipeline.failed'
  | 'pipeline.cancelled'
  | 'pipeline.paused';

export type SystemEventName =
  | 'system.startup'
  | 'system.shutdown'
  | 'system.health.check'
  | 'system.config.changed'
  | 'system.backup.completed'
  | 'system.maintenance.started'
  | 'system.maintenance.ended';
```

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 11011 | `ERR_EVENT_SCHEMA_INVALID` | Event schema validation failed |
| 11012 | `ERR_EVENT_VERSION_UNSUPPORTED` | Event version no longer supported |
| 11013 | `ERR_EVENT_VERSION_DEPRECATED` | Event version deprecated (warning) |
| 11014 | `ERR_EVENT_PAYLOAD_TOO_LARGE` | Payload exceeds size limit |
| 11015 | `ERR_EVENT_MIGRATION_FAILED` | Schema migration failed |
| 11016 | `ERR_EVENT_UUID_INVALID` | Invalid UUID v7 format |
| 11017 | `ERR_EVENT_TIMESTAMP_INVALID` | Invalid ISO 8601 timestamp |
| 11018 | `ERR_EVENT_NAME_INVALID` | Invalid event name format |
| 11019 | `ERR_EVENT_TYPE_MISMATCH` | Event type doesn't match name prefix |
| 11020 | `ERR_EVENT_CORRELATION_MISSING` | Missing correlation ID |

---

## Usage Examples

### Creating a File Event

```typescript
const factory = new EventFactoryImpl();

const event = factory.createFileEvent(
  'file.created',
  {
    path: '/projects/demo/src/main.ts',
    name: 'main.ts',
    extension: '.ts',
    mimeType: 'text/typescript',
    size: 1024,
    checksum: 'sha256:abc123...',
    parentPath: '/projects/demo/src',
    createdBy: 'user-123',
    isDirectory: false,
  },
  {
    projectId: 'project-456',
    userId: 'user-123',
    tags: { source: 'editor' },
  }
);
```

### Subscribing to Events

```typescript
// Subscribe to all file events
eventBus.subscribe('file.*', async (event) => {
  console.log(`File event: ${event.name}`);
});

// Subscribe to specific AI events
eventBus.subscribe('ai.completion.*', async (event) => {
  console.log(`AI completion: ${event.payload.requestId}`);
});
```

---

## Related Specifications

- [Event Bus](./02-event-bus.md) - Publishing and routing
- [Event Store](./03-event-store.md) - Persistence and replay
- [Database Schema](./04-database-schema.md) - Storage tables
- [File Events](./05-file-events.md) - Detailed file event specs
