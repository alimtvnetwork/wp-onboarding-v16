# Realtime Types

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Connection State

```typescript
type ConnectionState = 
  | 'connecting'
  | 'connected'
  | 'disconnected'
  | 'reconnecting'
  | 'failed';

interface ConnectionInfo {
  state: ConnectionState;
  connectedAt: string | null;
  latency: number | null;
  reconnectAttempts: number;
  lastError: ConnectionError | null;
}

interface ConnectionError {
  code: number;
  message: string;
  timestamp: string;
  recoverable: boolean;
}
```

---

## SSE Types

```typescript
interface SSEConfig {
  url: string;
  withCredentials: boolean;
  headers?: Record<string, string>;
  reconnectInterval: number;
  maxReconnectAttempts: number;
  heartbeatTimeout: number;
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
```

---

## WebSocket Types

```typescript
interface WebSocketConfig {
  url: string;
  protocols?: string[];
  reconnect: boolean;
  reconnectInterval: number;
  maxReconnectAttempts: number;
  heartbeatInterval: number;
  messageTimeout: number;
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
  | 'operation'
  | 'ack'
  | 'error'
  | 'ping'
  | 'pong';

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

---

## Presence

```typescript
interface PresenceState {
  userId: string;
  name: string;
  color: string;
  cursor?: CursorPosition;
  selection?: TextSelection;
  lastActive: number;
  status: PresenceStatus;
  metadata?: Record<string, unknown>;
}

type PresenceStatus = 'online' | 'away' | 'busy' | 'offline';

interface CursorPosition {
  fileId: string;
  line: number;
  column: number;
}

interface TextSelection {
  fileId: string;
  anchor: Position;
  head: Position;
}

interface Position {
  line: number;
  column: number;
}

interface PresenceManager {
  track(state: Partial<PresenceState>): void;
  untrack(): void;
  getPresences(): Map<string, PresenceState>;
  onSync(handler: (presences: Map<string, PresenceState>) => void): () => void;
  onJoin(handler: (userId: string, state: PresenceState) => void): () => void;
  onLeave(handler: (userId: string, state: PresenceState) => void): () => void;
}
```

---

## Operational Transform

```typescript
type Operation = 
  | InsertOperation
  | DeleteOperation
  | RetainOperation;

interface InsertOperation {
  type: 'insert';
  position: number;
  text: string;
}

interface DeleteOperation {
  type: 'delete';
  position: number;
  length: number;
}

interface RetainOperation {
  type: 'retain';
  count: number;
}

interface OperationMessage {
  type: 'operation';
  payload: {
    fileId: string;
    revision: number;
    operations: Operation[];
    userId: string;
    timestamp: number;
  };
}

interface TransformResult {
  operations: Operation[];
  serverOps: Operation[];
}

interface OTClient {
  applyLocal(ops: Operation[]): void;
  applyRemote(msg: OperationMessage): void;
  getDocument(): string;
  getRevision(): number;
  onOperation(handler: (ops: Operation[]) => void): () => void;
}
```

---

## Channels

```typescript
interface Channel {
  name: string;
  type: ChannelType;
  subscribers: string[];
  metadata?: Record<string, unknown>;
}

type ChannelType = 
  | 'project'
  | 'file'
  | 'presence'
  | 'notification'
  | 'ai';

interface ChannelMessage<T = unknown> {
  channelName: string;
  type: string;
  payload: T;
  senderId: string;
  timestamp: number;
}

interface ChannelSubscription {
  channel: string;
  onMessage<T>(handler: (msg: ChannelMessage<T>) => void): () => void;
  broadcast<T>(type: string, payload: T): Promise<void>;
  unsubscribe(): void;
}
```

---

## AI Streaming

```typescript
interface AIStreamConfig {
  endpoint: string;
  requestId: string;
  onToken: (token: string) => void;
  onComplete: (result: AIStreamResult) => void;
  onError: (error: AIStreamError) => void;
}

interface AIStreamResult {
  requestId: string;
  fullText: string;
  usage: TokenUsage;
  finishReason: FinishReason;
}

interface TokenUsage {
  promptTokens: number;
  completionTokens: number;
  totalTokens: number;
}

type FinishReason = 'stop' | 'length' | 'content_filter' | 'tool_calls';

interface AIStreamError {
  code: number;
  message: string;
  type: 'rate_limit' | 'context_length' | 'server_error' | 'network';
  retryable: boolean;
}

interface AIStreamEvent {
  event: AIEventType;
  data: string;  // JSON string
}

type AIEventType = 
  | 'ai:token'
  | 'ai:tool_call'
  | 'ai:done'
  | 'ai:error';
```

---

## Offline Queue

```typescript
interface OfflineQueue {
  enqueue(message: QueuedMessage): void;
  dequeue(): QueuedMessage | null;
  peek(): QueuedMessage | null;
  flush(): Promise<FlushResult>;
  clear(): void;
  readonly pending: number;
  readonly maxSize: number;
}

interface QueuedMessage {
  id: string;
  type: MessageType;
  payload: unknown;
  priority: QueuePriority;
  createdAt: number;
  retryCount: number;
  maxRetries: number;
}

type QueuePriority = 'high' | 'normal' | 'low';

interface FlushResult {
  sent: number;
  failed: number;
  remaining: number;
  errors: QueueError[];
}

interface QueueError {
  messageId: string;
  error: string;
  retryable: boolean;
}
```

---

## Heartbeat

```typescript
interface HeartbeatConfig {
  interval: number;           // Default: 25000ms
  timeout: number;            // Default: 60000ms
  onMissed: () => void;
  onRestored: () => void;
}

interface HeartbeatState {
  lastSent: number | null;
  lastReceived: number | null;
  missedCount: number;
  latency: number | null;
}
```
