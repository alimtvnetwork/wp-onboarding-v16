# Realtime Communication

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

WebSocket and SSE connections for real-time features including AI streaming, collaborative editing, and live notifications.

**Cross-References:**
- [AI Integration](../06-ai-integration/00-overview.md)
- [API Client](../15-api-client/00-overview.md)
- [Voice Input](../05-voice-input/00-overview.md)

---

## Components

| # | Component | Description |
|---|-----------|-------------|
| 01 | [WebSocket Integration](./01-websocket-integration.md) | Real-time messaging and streaming |
| 02 | [SSE Streaming](./02-sse-streaming.md) | AI token streaming and notifications |
| 03 | [Presence System](./03-presence-system.md) | User cursors and collaborative presence |
| 04 | [Error Recovery](./04-error-recovery.md) | Reconnection and state synchronization |

---

## Channels

| Channel | Technology | Use Case | Protocol |
|---------|------------|----------|----------|
| AI Streaming | SSE | Token streaming for AI responses | `text/event-stream` |
| Notifications | SSE | Live alerts and updates | `text/event-stream` |
| Collaborative Edit | WebSocket | Real-time document sync | JSON-RPC over WS |
| Audio Streaming | WebSocket | Voice input PCM data | Binary frames |
| Presence | WebSocket | User cursors, online status | JSON messages |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          Frontend (React)                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────────┐  │
│  │   SSEClient     │  │ WebSocketManager│  │    PresenceManager          │  │
│  │                 │  │                 │  │                             │  │
│  │ - AI streaming  │  │ - Collaboration │  │ - Cursor positions          │  │
│  │ - Notifications │  │ - Voice audio   │  │ - Online users              │  │
│  │ - Auto-reconnect│  │ - Heartbeat     │  │ - Typing indicators         │  │
│  └────────┬────────┘  └────────┬────────┘  └─────────────┬───────────────┘  │
│           │                    │                         │                   │
│           └────────────────────┼─────────────────────────┘                   │
│                                │                                             │
│                    ┌───────────┴───────────┐                                 │
│                    │   ConnectionPool      │                                 │
│                    │   - Shared state      │                                 │
│                    │   - Reconnection      │                                 │
│                    │   - Offline queue     │                                 │
│                    └───────────┬───────────┘                                 │
└────────────────────────────────┼─────────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │      Backend (Go)        │
                    │                          │
                    │  ┌────────────────────┐  │
                    │  │ WebSocket Hub      │  │
                    │  │ - Room management  │  │
                    │  │ - Broadcast        │  │
                    │  │ - OT Engine        │  │
                    │  └────────────────────┘  │
                    │                          │
                    │  ┌────────────────────┐  │
                    │  │ SSE Broadcaster    │  │
                    │  │ - AI token stream  │  │
                    │  │ - Event dispatch   │  │
                    │  └────────────────────┘  │
                    └──────────────────────────┘
```

---

## TypeScript Interfaces

### SSE Client

```typescript
interface SSEConfig {
  url: string;
  withCredentials: boolean;
  headers?: Record<string, string>;
  reconnectInterval: number;      // Default: 3000ms
  maxReconnectAttempts: number;   // Default: 10
  heartbeatTimeout: number;       // Default: 30000ms
}

interface SSEEvent<T = unknown> {
  id?: string;
  event: string;
  data: T;
  retry?: number;
}

interface SSEClient {
  connect(): Promise<void>;
  disconnect(): void;
  
  on<T>(event: string, handler: (data: T) => void): () => void;
  off(event: string, handler?: Function): void;
  
  readonly state: ConnectionState;
  readonly lastEventId: string | null;
}

type ConnectionState = 'connecting' | 'connected' | 'disconnected' | 'reconnecting';
```

### WebSocket Manager

```typescript
interface WebSocketConfig {
  url: string;
  protocols?: string[];
  reconnect: boolean;
  reconnectInterval: number;
  maxReconnectAttempts: number;
  heartbeatInterval: number;      // Default: 25000ms
  messageTimeout: number;         // Default: 10000ms
}

interface WebSocketMessage<T = unknown> {
  id: string;
  type: MessageType;
  payload: T;
  timestamp: number;
}

type MessageType = 
  | 'subscribe' 
  | 'unsubscribe' 
  | 'broadcast' 
  | 'presence' 
  | 'sync' 
  | 'ack'
  | 'error';

interface WebSocketManager {
  connect(): Promise<void>;
  disconnect(): void;
  
  send<T>(type: MessageType, payload: T): Promise<void>;
  subscribe(channel: string): Promise<void>;
  unsubscribe(channel: string): Promise<void>;
  
  on<T>(type: MessageType, handler: (msg: WebSocketMessage<T>) => void): () => void;
  
  readonly state: ConnectionState;
  readonly latency: number;
}
```

### Presence System

```typescript
interface PresenceState {
  id: string;
  name: string;
  color: string;
  cursor?: CursorPosition;
  selection?: TextSelection;
  lastActive: number;
  status: 'online' | 'away' | 'busy';
}

interface CursorPosition {
  fileId: string;
  line: number;
  column: number;
}

interface TextSelection {
  fileId: string;
  anchor: { line: number; column: number };
  head: { line: number; column: number };
}

interface PresenceManager {
  track(state: Partial<PresenceState>): void;
  untrack(): void;
  
  getPresences(): Map<string, PresenceState>;
  
  onSync(handler: (presences: Map<string, PresenceState>) => void): () => void;
  onJoin(handler: (id: string, state: PresenceState) => void): () => void;
  onLeave(handler: (id: string, state: PresenceState) => void): () => void;
}
```

---

## SSE Protocol

### Event Format

```
id: <event-id>
event: <event-type>
data: <json-payload>
retry: <reconnect-ms>

```

### Event Types

| Event | Payload | Description |
|-------|---------|-------------|
| `ai:token` | `{ text: string }` | Single AI token |
| `ai:done` | `{ id: string, usage: TokenUsage }` | AI response complete |
| `ai:error` | `{ code: number, message: string }` | AI error |
| `notification` | `NotificationPayload` | User notification |
| `heartbeat` | `{ ts: number }` | Keep-alive |

### Example AI Streaming

```typescript
// Frontend SSE connection
const sseClient = createSSEClient({
  url: '/api/v1/ai/stream',
  withCredentials: true,
  reconnectInterval: 3000,
  maxReconnectAttempts: 10,
});

sseClient.on<{ text: string }>('ai:token', (data) => {
  appendToResponse(data.text);
});

sseClient.on<{ id: string; usage: TokenUsage }>('ai:done', (data) => {
  completeResponse(data);
});

sseClient.on<{ code: number; message: string }>('ai:error', (data) => {
  handleError(data);
});

await sseClient.connect();
```

---

## WebSocket Protocol

### Connection Handshake

```
1. Client → Server: WebSocket upgrade request
2. Server → Client: 101 Switching Protocols
3. Server → Client: { type: 'welcome', payload: { sessionId: string } }
4. Client → Server: { type: 'auth', payload: { token: string } }
5. Server → Client: { type: 'auth:success', payload: { user: User } }
```

### Message Format

```typescript
interface WSMessage {
  id: string;           // UUID for request/response correlation
  type: string;         // Message type
  payload: unknown;     // Type-specific payload
  ts: number;           // Timestamp
}
```

### Heartbeat Protocol

```
Client → Server: { type: 'ping', ts: 1706745600000 }
Server → Client: { type: 'pong', ts: 1706745600050 }
```

Heartbeat interval: 25 seconds  
Timeout: 60 seconds (2 missed heartbeats = disconnect)

---

## Operational Transform (OT)

### Operation Types

```typescript
type Operation = 
  | { type: 'insert'; pos: number; text: string }
  | { type: 'delete'; pos: number; length: number }
  | { type: 'retain'; count: number };

interface OperationMessage {
  type: 'operation';
  payload: {
    fileId: string;
    revision: number;
    operations: Operation[];
    userId: string;
  };
}
```

### Transform Rules

```
transform(op1, op2) → [op1', op2']

Where:
- apply(apply(doc, op1), op2') = apply(apply(doc, op2), op1')
- Convergence guaranteed for all concurrent operations
```

### Conflict Resolution

```typescript
interface ConflictResolver {
  transform(
    localOp: Operation[],
    remoteOp: Operation[],
    priority: 'local' | 'remote'
  ): [Operation[], Operation[]];
  
  compose(op1: Operation[], op2: Operation[]): Operation[];
  
  invert(op: Operation[], document: string): Operation[];
}
```

---

## Error Recovery

### Reconnection Strategy

| Attempt | Delay | Backoff |
|---------|-------|---------|
| 1 | 1s | - |
| 2 | 2s | ×2 |
| 3 | 4s | ×2 |
| 4 | 8s | ×2 |
| 5+ | 30s | max |

### Offline Queue

```typescript
interface OfflineQueue {
  enqueue(message: WSMessage): void;
  flush(): Promise<void>;
  clear(): void;
  
  readonly pending: number;
  readonly maxSize: number;  // Default: 100
}
```

### State Synchronization

```typescript
interface SyncManager {
  // Get local changes since last sync
  getLocalChanges(): Operation[];
  
  // Apply remote changes
  applyRemoteChanges(ops: Operation[]): void;
  
  // Full resync on reconnection
  resync(serverState: DocumentState): void;
  
  // Conflict detection
  hasConflicts(): boolean;
  resolveConflicts(): void;
}
```

---

## Features

| Feature | Description | Priority | Status |
|---------|-------------|----------|--------|
| SSE Client | EventSource with auto-reconnection | High | ✅ |
| WebSocket Manager | Connection pooling, heartbeat | High | ✅ |
| Presence System | User cursors, online status | High | ✅ |
| Operational Transform | Collaborative editing sync | Medium | ✅ |
| Offline Queue | Message queuing during disconnect | Medium | ✅ |
| Binary Frames | Audio streaming over WebSocket | Medium | ✅ |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 12001 | `ERR_WS_CONNECTION_FAILED` | WebSocket connection failed |
| 12002 | `ERR_WS_AUTH_FAILED` | WebSocket authentication failed |
| 12003 | `ERR_WS_MESSAGE_TIMEOUT` | Message timeout |
| 12004 | `ERR_WS_INVALID_MESSAGE` | Invalid message format |
| 12010 | `ERR_SSE_CONNECTION_FAILED` | SSE connection failed |
| 12011 | `ERR_SSE_EVENT_PARSE` | Failed to parse SSE event |
| 12020 | `ERR_PRESENCE_SYNC_FAILED` | Presence sync failed |
| 12030 | `ERR_OT_TRANSFORM_FAILED` | OT transform failed |
| 12031 | `ERR_OT_CONFLICT` | Unresolvable OT conflict |

---

## Related Specs

- [AI Integration](../06-ai-integration/00-overview.md)
- [Voice Input](../05-voice-input/00-overview.md)
- [Automation Pipeline](../27-automation-pipeline/00-overview.md)
