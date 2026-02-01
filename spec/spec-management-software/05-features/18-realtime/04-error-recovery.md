# Error Recovery

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Overview

Comprehensive error recovery strategies for WebSocket and SSE connections, including automatic reconnection, message queuing, state synchronization, and graceful degradation.

**Cross-References:**
- [WebSocket Integration](./01-websocket-integration.md)
- [Presence System](./03-presence-system.md)
- [API Client](../15-api-client/00-overview.md)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Error Recovery System                         │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐│
│  │ Connection   │  │ Message      │  │ State                  ││
│  │ Monitor      │  │ Queue        │  │ Synchronizer           ││
│  │              │  │              │  │                        ││
│  │ - health     │  │ - pending    │  │ - lastKnownState       ││
│  │ - latency    │  │ - retry      │  │ - conflictResolution   ││
│  │ - reconnect  │  │ - ordering   │  │ - fullSync             ││
│  └──────────────┘  └──────────────┘  └────────────────────────┘│
│         │                │                      │               │
│         └────────────────┼──────────────────────┘               │
│                          │                                      │
│                    ┌─────▼─────┐                                │
│                    │ Recovery  │                                │
│                    │ Manager   │                                │
│                    └───────────┘                                │
└─────────────────────────────────────────────────────────────────┘
```

---

## Error Categories

### Connection Errors

| Code | Name | Description | Recovery Strategy |
|------|------|-------------|-------------------|
| ERR_WS_4001 | CONNECTION_LOST | WebSocket disconnected unexpectedly | Exponential backoff reconnect |
| ERR_WS_4002 | CONNECTION_TIMEOUT | Connection attempt timed out | Retry with increased timeout |
| ERR_WS_4003 | CONNECTION_REFUSED | Server refused connection | Check auth, retry later |
| ERR_WS_4004 | HANDSHAKE_FAILED | WebSocket handshake failed | Verify protocol, retry |
| ERR_WS_4005 | HEARTBEAT_TIMEOUT | No pong received | Force reconnect |
| ERR_WS_4006 | INVALID_MESSAGE | Malformed message received | Log, continue |

### SSE Errors

| Code | Name | Description | Recovery Strategy |
|------|------|-------------|-------------------|
| ERR_SSE_4101 | STREAM_CLOSED | EventSource closed | Auto-reconnect |
| ERR_SSE_4102 | STREAM_ERROR | Stream error event | Reconnect with last event ID |
| ERR_SSE_4103 | PARSE_ERROR | Invalid event data | Log, skip event |

### State Errors

| Code | Name | Description | Recovery Strategy |
|------|------|-------------|-------------------|
| ERR_STATE_4201 | SYNC_CONFLICT | State conflict detected | Request server resolution |
| ERR_STATE_4202 | STALE_STATE | Local state outdated | Full sync |
| ERR_STATE_4203 | INVALID_STATE | State validation failed | Reset to last valid |

---

## Reconnection Strategy

### Exponential Backoff

```typescript
// src/lib/realtime/ReconnectionManager.ts

interface ReconnectionConfig {
  initialDelay: number;      // 1000ms
  maxDelay: number;          // 30000ms
  multiplier: number;        // 2
  jitter: number;            // 0.1 (10%)
  maxAttempts: number;       // 10
}

class ReconnectionManager {
  private config: ReconnectionConfig;
  private attempts: number = 0;
  private timeout: NodeJS.Timeout | null = null;

  constructor(config: Partial<ReconnectionConfig> = {}) {
    this.config = {
      initialDelay: 1000,
      maxDelay: 30000,
      multiplier: 2,
      jitter: 0.1,
      maxAttempts: 10,
      ...config,
    };
  }

  getNextDelay(): number {
    const baseDelay = Math.min(
      this.config.initialDelay * Math.pow(this.config.multiplier, this.attempts),
      this.config.maxDelay
    );

    // Add jitter to prevent thundering herd
    const jitterRange = baseDelay * this.config.jitter;
    const jitter = Math.random() * jitterRange * 2 - jitterRange;

    return Math.floor(baseDelay + jitter);
  }

  async scheduleReconnect(connect: () => Promise<boolean>): Promise<void> {
    if (this.attempts >= this.config.maxAttempts) {
      throw new Error('Max reconnection attempts exceeded');
    }

    const delay = this.getNextDelay();
    this.attempts++;

    console.log(`[Reconnect] Attempt ${this.attempts} in ${delay}ms`);

    await new Promise(resolve => {
      this.timeout = setTimeout(resolve, delay);
    });

    const success = await connect();
    if (!success) {
      return this.scheduleReconnect(connect);
    }

    this.reset();
  }

  reset() {
    this.attempts = 0;
    if (this.timeout) {
      clearTimeout(this.timeout);
      this.timeout = null;
    }
  }

  cancel() {
    this.reset();
  }
}
```

### Backoff Timeline

```
Attempt 1: 1000ms  (1s)
Attempt 2: 2000ms  (2s)
Attempt 3: 4000ms  (4s)
Attempt 4: 8000ms  (8s)
Attempt 5: 16000ms (16s)
Attempt 6: 30000ms (30s) [capped]
...
```

---

## Message Queue

### Offline Queue Implementation

```typescript
// src/lib/realtime/MessageQueue.ts

interface QueuedMessage {
  id: string;
  type: string;
  payload: unknown;
  timestamp: Date;
  retries: number;
  priority: 'high' | 'normal' | 'low';
}

class MessageQueue {
  private queue: QueuedMessage[] = [];
  private maxSize: number = 100;
  private maxRetries: number = 3;

  enqueue(message: Omit<QueuedMessage, 'id' | 'timestamp' | 'retries'>) {
    if (this.queue.length >= this.maxSize) {
      // Remove oldest low-priority message
      const lowPriorityIndex = this.queue.findIndex(m => m.priority === 'low');
      if (lowPriorityIndex !== -1) {
        this.queue.splice(lowPriorityIndex, 1);
      } else {
        console.warn('[Queue] Queue full, dropping message');
        return;
      }
    }

    this.queue.push({
      ...message,
      id: crypto.randomUUID(),
      timestamp: new Date(),
      retries: 0,
    });

    // Sort by priority
    this.queue.sort((a, b) => {
      const priorityOrder = { high: 0, normal: 1, low: 2 };
      return priorityOrder[a.priority] - priorityOrder[b.priority];
    });
  }

  async flush(send: (msg: QueuedMessage) => Promise<boolean>): Promise<void> {
    const pending = [...this.queue];
    this.queue = [];

    for (const message of pending) {
      const success = await send(message);
      if (!success) {
        message.retries++;
        if (message.retries < this.maxRetries) {
          this.queue.push(message);
        } else {
          console.error('[Queue] Message dropped after max retries:', message);
        }
      }
    }
  }

  clear() {
    this.queue = [];
  }

  get size(): number {
    return this.queue.length;
  }

  get pendingMessages(): QueuedMessage[] {
    return [...this.queue];
  }
}
```

### Priority Levels

| Priority | Use Case | Queue Behavior |
|----------|----------|----------------|
| High | User actions, critical updates | Never dropped |
| Normal | State sync, presence | Dropped if queue full |
| Low | Analytics, telemetry | First to be dropped |

---

## State Synchronization

### Sync Protocol

```typescript
// src/lib/realtime/StateSynchronizer.ts

interface SyncState {
  version: number;
  checksum: string;
  timestamp: Date;
}

class StateSynchronizer {
  private localState: SyncState | null = null;
  private pendingChanges: Map<string, unknown> = new Map();

  async onReconnect(socket: WebSocket): Promise<void> {
    // Request server state
    socket.send(JSON.stringify({
      type: 'sync:request',
      lastKnownVersion: this.localState?.version ?? 0,
      lastKnownChecksum: this.localState?.checksum,
    }));
  }

  handleSyncResponse(response: SyncResponse): void {
    if (response.type === 'sync:full') {
      // Full state replacement
      this.applyFullSync(response.state);
    } else if (response.type === 'sync:delta') {
      // Apply incremental changes
      this.applyDeltaSync(response.changes);
    } else if (response.type === 'sync:conflict') {
      // Handle conflicts
      this.resolveConflicts(response.conflicts);
    }

    // Replay pending changes
    this.replayPendingChanges();
  }

  private applyFullSync(state: unknown): void {
    console.log('[Sync] Applying full state sync');
    // Replace local state entirely
    this.localState = {
      version: state.version,
      checksum: this.computeChecksum(state),
      timestamp: new Date(),
    };
    this.pendingChanges.clear();
  }

  private applyDeltaSync(changes: Change[]): void {
    console.log(`[Sync] Applying ${changes.length} delta changes`);
    for (const change of changes) {
      this.applyChange(change);
    }
  }

  private resolveConflicts(conflicts: Conflict[]): void {
    for (const conflict of conflicts) {
      // Server wins by default, but preserve local changes if newer
      if (conflict.localTimestamp > conflict.serverTimestamp) {
        this.pendingChanges.set(conflict.key, conflict.localValue);
      }
    }
  }

  private replayPendingChanges(): void {
    if (this.pendingChanges.size === 0) return;

    console.log(`[Sync] Replaying ${this.pendingChanges.size} pending changes`);
    for (const [key, value] of this.pendingChanges) {
      this.sendChange(key, value);
    }
  }
}
```

### Conflict Resolution

| Strategy | Description | Use Case |
|----------|-------------|----------|
| Server Wins | Server state always takes precedence | Critical data |
| Last Write Wins | Most recent timestamp wins | General updates |
| Merge | Combine changes where possible | Collaborative edits |
| Manual | Prompt user to resolve | Conflicting user edits |

---

## React Integration

### useRealtimeRecovery Hook

```typescript
// src/hooks/useRealtimeRecovery.ts

interface RealtimeRecoveryState {
  isConnected: boolean;
  isReconnecting: boolean;
  reconnectAttempt: number;
  lastError: Error | null;
  queuedMessages: number;
}

export function useRealtimeRecovery() {
  const [state, setState] = useState<RealtimeRecoveryState>({
    isConnected: false,
    isReconnecting: false,
    reconnectAttempt: 0,
    lastError: null,
    queuedMessages: 0,
  });

  const reconnectionManager = useRef(new ReconnectionManager());
  const messageQueue = useRef(new MessageQueue());
  const stateSynchronizer = useRef(new StateSynchronizer());

  const connect = useCallback(async () => {
    try {
      const socket = await createWebSocket();
      
      socket.onopen = () => {
        setState(s => ({ ...s, isConnected: true, isReconnecting: false }));
        reconnectionManager.current.reset();
        
        // Sync state after reconnection
        stateSynchronizer.current.onReconnect(socket);
        
        // Flush queued messages
        messageQueue.current.flush(msg => sendMessage(socket, msg));
      };

      socket.onclose = (event) => {
        if (!event.wasClean) {
          handleDisconnect();
        }
      };

      socket.onerror = (error) => {
        setState(s => ({ ...s, lastError: error }));
      };

      return true;
    } catch (error) {
      setState(s => ({ ...s, lastError: error as Error }));
      return false;
    }
  }, []);

  const handleDisconnect = useCallback(() => {
    setState(s => ({ 
      ...s, 
      isConnected: false, 
      isReconnecting: true 
    }));

    reconnectionManager.current.scheduleReconnect(connect)
      .catch(error => {
        setState(s => ({ 
          ...s, 
          isReconnecting: false, 
          lastError: error 
        }));
      });
  }, [connect]);

  const sendMessage = useCallback((type: string, payload: unknown, priority: Priority = 'normal') => {
    if (state.isConnected) {
      // Send immediately
      socket.send(JSON.stringify({ type, payload }));
    } else {
      // Queue for later
      messageQueue.current.enqueue({ type, payload, priority });
      setState(s => ({ ...s, queuedMessages: messageQueue.current.size }));
    }
  }, [state.isConnected]);

  return {
    ...state,
    connect,
    sendMessage,
    forceReconnect: () => {
      reconnectionManager.current.reset();
      handleDisconnect();
    },
  };
}
```

### ConnectionStatusBanner

```typescript
// src/components/realtime/ConnectionStatusBanner.tsx

export function ConnectionStatusBanner() {
  const { isConnected, isReconnecting, reconnectAttempt, queuedMessages } = useRealtimeRecovery();

  if (isConnected) return null;

  return (
    <div className="fixed top-0 left-0 right-0 z-50 bg-warning text-warning-foreground px-4 py-2">
      <div className="container mx-auto flex items-center justify-between">
        <div className="flex items-center gap-2">
          {isReconnecting ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" />
              <span>Reconnecting... (Attempt {reconnectAttempt})</span>
            </>
          ) : (
            <>
              <WifiOff className="h-4 w-4" />
              <span>Connection lost</span>
            </>
          )}
        </div>
        {queuedMessages > 0 && (
          <span className="text-sm opacity-80">
            {queuedMessages} message{queuedMessages > 1 ? 's' : ''} queued
          </span>
        )}
      </div>
    </div>
  );
}
```

---

## SSE Recovery

### EventSource Wrapper

```typescript
// src/lib/realtime/SSEClient.ts

interface SSEClientConfig {
  url: string;
  onMessage: (event: MessageEvent) => void;
  onError?: (error: Event) => void;
  reconnectDelay?: number;
}

class SSEClient {
  private eventSource: EventSource | null = null;
  private lastEventId: string | null = null;
  private reconnectionManager = new ReconnectionManager();

  constructor(private config: SSEClientConfig) {}

  connect(): void {
    const url = new URL(this.config.url);
    if (this.lastEventId) {
      url.searchParams.set('lastEventId', this.lastEventId);
    }

    this.eventSource = new EventSource(url.toString());

    this.eventSource.onmessage = (event) => {
      this.lastEventId = event.lastEventId;
      this.config.onMessage(event);
    };

    this.eventSource.onerror = (error) => {
      console.error('[SSE] Connection error:', error);
      this.config.onError?.(error);
      this.handleError();
    };

    this.eventSource.onopen = () => {
      console.log('[SSE] Connection opened');
      this.reconnectionManager.reset();
    };
  }

  private async handleError(): Promise<void> {
    this.close();
    
    try {
      await this.reconnectionManager.scheduleReconnect(async () => {
        this.connect();
        return true;
      });
    } catch (error) {
      console.error('[SSE] Max reconnection attempts exceeded');
    }
  }

  close(): void {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
  }
}
```

---

## Graceful Degradation

### Feature Fallbacks

| Feature | Online Mode | Offline Mode |
|---------|-------------|--------------|
| AI Streaming | Real-time tokens | Show loading, retry on reconnect |
| Presence | Live cursors | Hide other users |
| Notifications | Instant push | Poll on reconnect |
| Collaborative Edit | Real-time sync | Local-only, sync on reconnect |

### Degradation Levels

```typescript
// src/lib/realtime/DegradationManager.ts

type DegradationLevel = 'full' | 'limited' | 'offline';

class DegradationManager {
  private level: DegradationLevel = 'full';
  private listeners: Set<(level: DegradationLevel) => void> = new Set();

  setLevel(level: DegradationLevel): void {
    if (this.level !== level) {
      this.level = level;
      this.notify();
    }
  }

  getFeatureAvailability(feature: string): boolean {
    const featureMatrix: Record<string, DegradationLevel[]> = {
      'ai-streaming': ['full'],
      'presence': ['full', 'limited'],
      'notifications': ['full', 'limited'],
      'local-editing': ['full', 'limited', 'offline'],
      'auto-save': ['full', 'limited', 'offline'],
    };

    return featureMatrix[feature]?.includes(this.level) ?? false;
  }

  private notify(): void {
    this.listeners.forEach(listener => listener(this.level));
  }

  subscribe(listener: (level: DegradationLevel) => void): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }
}
```

---

## Monitoring & Logging

### Connection Metrics

```typescript
interface ConnectionMetrics {
  totalConnections: number;
  successfulReconnects: number;
  failedReconnects: number;
  averageReconnectTime: number;
  messagesQueued: number;
  messagesDropped: number;
  lastError: string | null;
  lastErrorTime: Date | null;
}
```

### Logging Format

```typescript
// Structured logging for debugging
console.log('[WS] Connected', { 
  timestamp: new Date().toISOString(),
  latency: connectionLatency,
  attempt: reconnectAttempts,
});

console.warn('[WS] Reconnecting', {
  timestamp: new Date().toISOString(),
  attempt: attempt,
  delay: delay,
  queueSize: messageQueue.size,
});

console.error('[WS] Error', {
  timestamp: new Date().toISOString(),
  code: error.code,
  message: error.message,
  stack: error.stack,
});
```

---

## Testing

### Unit Tests

```typescript
describe('ReconnectionManager', () => {
  it('applies exponential backoff', () => {
    const manager = new ReconnectionManager();
    
    expect(manager.getNextDelay()).toBeLessThanOrEqual(1100);
    manager.attempts = 1;
    expect(manager.getNextDelay()).toBeLessThanOrEqual(2200);
    manager.attempts = 5;
    expect(manager.getNextDelay()).toBeLessThanOrEqual(30000);
  });

  it('caps delay at maxDelay', () => {
    const manager = new ReconnectionManager({ maxDelay: 5000 });
    manager.attempts = 10;
    expect(manager.getNextDelay()).toBeLessThanOrEqual(5500);
  });

  it('resets after successful connection', async () => {
    const manager = new ReconnectionManager();
    manager.attempts = 5;
    
    await manager.scheduleReconnect(async () => true);
    
    expect(manager.attempts).toBe(0);
  });
});

describe('MessageQueue', () => {
  it('maintains priority order', () => {
    const queue = new MessageQueue();
    
    queue.enqueue({ type: 'low', payload: {}, priority: 'low' });
    queue.enqueue({ type: 'high', payload: {}, priority: 'high' });
    queue.enqueue({ type: 'normal', payload: {}, priority: 'normal' });
    
    const messages = queue.pendingMessages;
    expect(messages[0].priority).toBe('high');
    expect(messages[1].priority).toBe('normal');
    expect(messages[2].priority).toBe('low');
  });

  it('drops low priority when full', () => {
    const queue = new MessageQueue();
    queue.maxSize = 2;
    
    queue.enqueue({ type: 'high1', payload: {}, priority: 'high' });
    queue.enqueue({ type: 'low1', payload: {}, priority: 'low' });
    queue.enqueue({ type: 'high2', payload: {}, priority: 'high' });
    
    expect(queue.size).toBe(2);
    expect(queue.pendingMessages.every(m => m.priority === 'high')).toBe(true);
  });
});
```

---

## Related Specs

- [WebSocket Integration](./01-websocket-integration.md)
- [Presence System](./03-presence-system.md)
- [API Client](../15-api-client/00-overview.md)

---

## Implementation Checklist

- [ ] ReconnectionManager with exponential backoff
- [ ] MessageQueue with priority and retry logic
- [ ] StateSynchronizer with conflict resolution
- [ ] useRealtimeRecovery hook
- [ ] ConnectionStatusBanner component
- [ ] SSEClient with lastEventId tracking
- [ ] DegradationManager for graceful fallbacks
- [ ] Connection metrics and structured logging
- [ ] Comprehensive test coverage
- [ ] Integration with existing WebSocket manager
