# Feature: Trigger Event System

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Summary

A unified event-driven architecture that combines file system events, user actions, AI operations, pipeline executions, and system events into a single observable event bus. Enables reactive workflows, real-time UI updates, audit logging, and cross-service communication.

---

## User Stories

- As a user, I want file changes to automatically trigger RAG re-indexing
- As a user, I want to see real-time notifications when AI tasks complete
- As a user, I want pipeline stages to react to external events
- As a user, I want audit logs of all system events for compliance
- As a user, I want to configure custom triggers for automation workflows
- As a user, I want event-driven webhooks for external integrations

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         TRIGGER EVENT SYSTEM                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          EVENT SOURCES                                 │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐     │  │
│  │  │  File   │  │  User   │  │   AI    │  │Pipeline │  │ System  │     │  │
│  │  │ Events  │  │ Events  │  │ Events  │  │ Events  │  │ Events  │     │  │
│  │  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘     │  │
│  └───────┼───────────┼───────────┼───────────┼───────────┼───────────────┘  │
│          │           │           │           │           │                   │
│          └───────────┴───────────┴─────┬─────┴───────────┘                   │
│                                        │                                      │
│                                        ▼                                      │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          EVENT BUS                                     │  │
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐        │  │
│  │  │  Event Router   │──│  Event Store    │──│  Event Replay   │        │  │
│  │  └────────┬────────┘  └─────────────────┘  └─────────────────┘        │  │
│  └───────────┼───────────────────────────────────────────────────────────┘  │
│              │                                                               │
│              ▼                                                               │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          EVENT HANDLERS                                │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐     │  │
│  │  │   RAG   │  │   UI    │  │ Webhook │  │ Audit   │  │Pipeline │     │  │
│  │  │ Handler │  │ Handler │  │ Handler │  │ Handler │  │ Handler │     │  │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘     │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Specification Index

### Phase 1: Core Infrastructure

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 01 | [Event Types](./01-event-types.md) | Backend | Event taxonomy, payload schemas, versioning |
| 02 | [Event Bus](./02-event-bus.md) | Backend | Pub/sub engine, routing, delivery guarantees |
| 03 | [Event Store](./03-event-store.md) | Backend | Persistence, replay, retention policies |
| 04 | [Database Schema](./04-database-schema.md) | Backend | Event, Subscription, Handler, AuditLog tables |

### Phase 2: Event Sources

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 05 | [File Events](./05-file-events.md) | Backend | Create, update, delete, move file triggers |
| 06 | [User Events](./06-user-events.md) | Backend | Auth, session, action tracking |
| 07 | [AI Events](./07-ai-events.md) | Backend | Completion, streaming, error events |
| 08 | [Pipeline Events](./08-pipeline-events.md) | Backend | Stage start/complete, block transitions |
| 09 | [System Events](./09-system-events.md) | Backend | Health, config changes, scheduled tasks |

### Phase 3: Event Handlers

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 10 | [RAG Handler](./10-rag-handler.md) | Backend | Auto-reindex on file changes |
| 11 | [UI Handler](./11-ui-handler.md) | Frontend | Real-time notifications, state updates |
| 12 | [Webhook Handler](./12-webhook-handler.md) | Backend | External HTTP callbacks, retry logic |
| 13 | [Audit Handler](./13-audit-handler.md) | Backend | Compliance logging, event archival |
| 14 | [Pipeline Trigger Handler](./14-pipeline-trigger-handler.md) | Backend | Event-driven pipeline execution |

### Phase 4: Trigger Configuration

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 15 | [Trigger Rules](./15-trigger-rules.md) | Backend | Condition expressions, filters, rate limiting |
| 16 | [Trigger UI](./16-trigger-ui.md) | Frontend | Visual trigger builder, subscription management |
| 17 | [Event Debugger](./17-event-debugger.md) | Frontend | Live event inspector, replay tools |

### Phase 5: Testing

| # | Specification | Type | Description |
|---|---------------|------|-------------|
| 18 | [Event Integration Tests](./18-event-integration-tests.md) | Testing | End-to-end event flow validation |

---

## Event Categories

| Category | Prefix | Examples |
|----------|--------|----------|
| File | `file.*` | `file.created`, `file.updated`, `file.deleted`, `file.moved` |
| User | `user.*` | `user.login`, `user.logout`, `user.action` |
| AI | `ai.*` | `ai.completion`, `ai.stream.start`, `ai.stream.end`, `ai.error` |
| Pipeline | `pipeline.*` | `pipeline.started`, `pipeline.completed`, `pipeline.failed` |
| Stage | `stage.*` | `stage.started`, `stage.completed`, `stage.failed`, `stage.skipped` |
| System | `system.*` | `system.startup`, `system.shutdown`, `system.health`, `system.config` |
| Knowledge | `knowledge.*` | `knowledge.indexed`, `knowledge.query`, `knowledge.updated` |
| Project | `project.*` | `project.created`, `project.opened`, `project.exported` |

---

## Core Interfaces

```typescript
// Event base interface
interface TriggerEvent {
  readonly id: string;           // UUID v7 (time-sortable)
  readonly type: EventType;      // Enum: FILE, USER, AI, PIPELINE, SYSTEM
  readonly name: string;         // e.g., "file.created"
  readonly version: string;      // Schema version (semver)
  readonly timestamp: string;    // ISO 8601
  readonly source: EventSource;  // Originating service
  readonly correlationId: string; // Request/trace ID
  readonly payload: EventPayload; // Type-specific data
  readonly metadata: EventMetadata;
}

// Event source identification
interface EventSource {
  readonly service: string;      // e.g., "file-service", "ai-bridge"
  readonly instance: string;     // Instance ID
  readonly version: string;      // Service version
}

// Common metadata
interface EventMetadata {
  readonly userId?: string;      // Acting user (if applicable)
  readonly projectId?: string;   // Project context
  readonly sessionId?: string;   // Session context
  readonly traceId?: string;     // Distributed tracing ID
  readonly spanId?: string;      // Span ID
  readonly tags?: Record<string, string>;
}

// Subscription configuration
interface EventSubscription {
  readonly id: string;
  readonly subscriberId: string;
  readonly eventPattern: string;  // Glob pattern: "file.*", "ai.completion"
  readonly handler: HandlerType;  // RAG, UI, WEBHOOK, AUDIT, PIPELINE
  readonly config: HandlerConfig;
  readonly filters?: EventFilter[];
  readonly enabled: boolean;
  readonly createdAt: string;
}

// Event filter for conditional triggering
interface EventFilter {
  readonly field: string;         // JSONPath to payload field
  readonly operator: FilterOperator;
  readonly value: unknown;
}

enum FilterOperator {
  EQUALS = "EQUALS",
  NOT_EQUALS = "NOT_EQUALS",
  CONTAINS = "CONTAINS",
  STARTS_WITH = "STARTS_WITH",
  ENDS_WITH = "ENDS_WITH",
  MATCHES = "MATCHES",           // Regex
  GT = "GT",
  GTE = "GTE",
  LT = "LT",
  LTE = "LTE",
  IN = "IN",
  NOT_IN = "NOT_IN",
  EXISTS = "EXISTS",
  NOT_EXISTS = "NOT_EXISTS"
}

enum HandlerType {
  RAG = "RAG",                   // Knowledge indexing
  UI = "UI",                     // Real-time notifications
  WEBHOOK = "WEBHOOK",           // External HTTP callbacks
  AUDIT = "AUDIT",               // Compliance logging
  PIPELINE = "PIPELINE",         // Pipeline trigger
  CUSTOM = "CUSTOM"              // User-defined handler
}

// Handler configuration by type
type HandlerConfig = 
  | RagHandlerConfig
  | UiHandlerConfig
  | WebhookHandlerConfig
  | AuditHandlerConfig
  | PipelineHandlerConfig
  | CustomHandlerConfig;

interface WebhookHandlerConfig {
  readonly url: string;
  readonly method: "POST" | "PUT";
  readonly headers?: Record<string, string>;
  readonly secret?: string;       // HMAC signing key
  readonly timeout: number;       // ms
  readonly retries: number;
  readonly retryDelay: number;    // ms
}

interface PipelineHandlerConfig {
  readonly pipelineId: string;
  readonly inputMapping: Record<string, string>; // Event field -> Pipeline variable
  readonly waitForCompletion: boolean;
}
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `Event` | Event log with payload and metadata |
| `EventSubscription` | Handler registrations and patterns |
| `EventDelivery` | Delivery tracking and retry state |
| `TriggerRule` | Conditional trigger configurations |
| `WebhookEndpoint` | Registered webhook URLs |
| `AuditLog` | Compliance-ready event archive |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 11001 | `ERR_EVENT_INVALID_TYPE` | Unknown event type |
| 11002 | `ERR_EVENT_INVALID_PAYLOAD` | Payload schema validation failed |
| 11003 | `ERR_EVENT_DELIVERY_FAILED` | Handler delivery failed after retries |
| 11004 | `ERR_EVENT_SUBSCRIPTION_NOT_FOUND` | Subscription not found |
| 11005 | `ERR_EVENT_FILTER_INVALID` | Invalid filter expression |
| 11006 | `ERR_EVENT_HANDLER_TIMEOUT` | Handler execution timeout |
| 11007 | `ERR_EVENT_REPLAY_FAILED` | Event replay failed |
| 11008 | `ERR_EVENT_STORE_FULL` | Event store capacity exceeded |
| 11009 | `ERR_WEBHOOK_UNREACHABLE` | Webhook endpoint unreachable |
| 11010 | `ERR_TRIGGER_RULE_INVALID` | Invalid trigger rule configuration |

---

## Key Features

- **Unified Event Bus:** Single pub/sub system for all event types
- **Pattern Matching:** Glob patterns for flexible subscriptions (`file.*`, `ai.completion`)
- **Guaranteed Delivery:** At-least-once delivery with retry logic
- **Event Replay:** Re-process historical events for debugging or recovery
- **Conditional Triggers:** Filter events with JSONPath expressions
- **Distributed Tracing:** Correlation IDs for end-to-end request tracking
- **Audit Compliance:** Immutable event log with configurable retention

---

## Integration Points

| System | Events Produced | Events Consumed |
|--------|-----------------|-----------------|
| File Service | `file.*` | — |
| AI Bridge | `ai.*` | — |
| Pipeline Engine | `pipeline.*`, `stage.*` | `file.*`, `ai.*`, `system.*` |
| Knowledge Memory | `knowledge.*` | `file.*` |
| Authentication | `user.*` | — |
| Project Manager | `project.*` | — |
| UI Layer | — | All (via SSE/WebSocket) |

---

## Dependencies

- [Realtime Communication](../18-realtime/00-overview.md) — SSE/WebSocket delivery
- [Automation Pipeline](../27-automation-pipeline/00-overview.md) — Pipeline triggers
- [Knowledge Memory](../09-knowledge-memory/00-overview.md) — RAG handler
- [File Management](../02-file-management/00-overview.md) — File events
- [AI Integration](../06-ai-integration/00-overview.md) — AI events

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Event Publish Latency | < 5ms p99 |
| Handler Delivery Latency | < 50ms p99 |
| Delivery Success Rate | ≥ 99.9% |
| Event Store Query Time | < 10ms for 10K events |
| Webhook Retry Success | ≥ 95% within 3 retries |

---

## Related Specs

- [Realtime Communication](../18-realtime/00-overview.md)
- [Automation Pipeline](../27-automation-pipeline/00-overview.md)
- [Knowledge Memory](../09-knowledge-memory/00-overview.md)
- [Monitoring](../17-monitoring/00-overview.md)
