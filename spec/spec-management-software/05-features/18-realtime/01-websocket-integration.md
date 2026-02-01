# 18.1 WebSocket Integration

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Real-time communication layer using WebSocket for live updates, collaborative editing signals, and streaming AI responses.

**Cross-References:**
- [LLM Live Logging](../06-ai-integration/06-llm-live-logging.md) - Streaming logs
- [API Client](../15-api-client/00-overview.md) - HTTP fallback
- [State Management](../16-state-management/00-overview.md) - Real-time state

---

## 18.1.1 Connection Management

```typescript
interface WebSocketManager {
  connect(): Promise<void>;
  disconnect(): void;
  subscribe(channel: string, handler: MessageHandler): Unsubscribe;
  send(channel: string, data: unknown): void;
  getState(): 'connecting' | 'connected' | 'disconnected' | 'reconnecting';
}

const useWebSocket = () => {
  const [state, setState] = useState<ConnectionState>('disconnected');
  const wsRef = useRef<WebSocket | null>(null);
  
  const connect = useCallback(async () => {
    setState('connecting');
    
    wsRef.current = new WebSocket(WS_URL);
    
    wsRef.current.onopen = () => setState('connected');
    wsRef.current.onclose = () => {
      setState('disconnected');
      scheduleReconnect();
    };
    wsRef.current.onerror = (error) => {
      console.error('WebSocket error:', error);
    };
  }, []);
  
  // Auto-reconnect with exponential backoff
  const scheduleReconnect = useCallback(() => {
    setState('reconnecting');
    setTimeout(connect, Math.min(1000 * 2 ** reconnectAttempts, 30000));
  }, [connect]);
  
  return { connect, disconnect, state };
};
```

---

## 18.1.2 Message Types

| Type | Direction | Purpose |
|------|-----------|---------|
| `llm:log` | Server → Client | Streaming LLM output |
| `file:updated` | Server → Client | File change notification |
| `project:sync` | Bidirectional | Project state sync |
| `presence:update` | Bidirectional | User presence (collaboration) |
| `job:progress` | Server → Client | Background job status |
| `heartbeat` | Bidirectional | Connection keep-alive |

---

## 18.1.3 Message Protocol

```typescript
interface WebSocketMessage<T = unknown> {
  type: string;
  channel?: string;
  payload: T;
  timestamp: number;
  id: string;
}

// Outgoing message
const sendMessage = <T>(type: string, payload: T) => {
  ws.send(JSON.stringify({
    type,
    payload,
    timestamp: Date.now(),
    id: generateId(),
  }));
};

// Incoming message handler
ws.onmessage = (event) => {
  const message: WebSocketMessage = JSON.parse(event.data);
  
  switch (message.type) {
    case 'llm:log':
      handleLLMLog(message.payload);
      break;
    case 'file:updated':
      handleFileUpdate(message.payload);
      break;
    case 'heartbeat':
      sendMessage('heartbeat:ack', {});
      break;
  }
};
```

---

## 18.1.4 LLM Streaming

```typescript
interface LLMStreamEvent {
  sessionId: string;
  modelId: string;
  type: 'start' | 'token' | 'complete' | 'error';
  content?: string;
  metadata?: {
    tokensGenerated?: number;
    tokensPerSecond?: number;
  };
}

const useLLMStream = (sessionId: string) => {
  const [output, setOutput] = useState('');
  const [status, setStatus] = useState<'idle' | 'streaming' | 'complete'>('idle');
  
  useEffect(() => {
    const unsubscribe = ws.subscribe(`llm:${sessionId}`, (event: LLMStreamEvent) => {
      switch (event.type) {
        case 'start':
          setStatus('streaming');
          setOutput('');
          break;
        case 'token':
          setOutput((prev) => prev + event.content);
          break;
        case 'complete':
          setStatus('complete');
          break;
      }
    });
    
    return unsubscribe;
  }, [sessionId]);
  
  return { output, status };
};
```

---

## 18.1.5 Server-Sent Events (SSE) Fallback

```typescript
// For environments where WebSocket is not available
const useSSE = (endpoint: string) => {
  const [data, setData] = useState<unknown>(null);
  
  useEffect(() => {
    const eventSource = new EventSource(endpoint);
    
    eventSource.onmessage = (event) => {
      setData(JSON.parse(event.data));
    };
    
    eventSource.onerror = () => {
      eventSource.close();
      // Reconnect logic
    };
    
    return () => eventSource.close();
  }, [endpoint]);
  
  return data;
};
```

---

## 18.1.6 Presence System

```typescript
interface PresenceState {
  users: Map<string, UserPresence>;
  currentUser: UserPresence;
}

interface UserPresence {
  id: string;
  name: string;
  avatar?: string;
  status: 'online' | 'away' | 'busy';
  currentFile?: string;
  cursor?: CursorPosition;
  lastSeen: Date;
}

// Broadcast presence updates
const updatePresence = (updates: Partial<UserPresence>) => {
  ws.send('presence:update', {
    ...currentPresence,
    ...updates,
    lastSeen: new Date(),
  });
};
```

---

## 18.1.7 Heartbeat & Connection Health

```typescript
const HEARTBEAT_INTERVAL = 30000;  // 30 seconds
const HEARTBEAT_TIMEOUT = 10000;   // 10 seconds

const useHeartbeat = (ws: WebSocket) => {
  const timeoutRef = useRef<NodeJS.Timeout>();
  
  useEffect(() => {
    const interval = setInterval(() => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ type: 'heartbeat', timestamp: Date.now() }));
        
        // Start timeout for response
        timeoutRef.current = setTimeout(() => {
          console.warn('Heartbeat timeout - reconnecting');
          ws.close();
        }, HEARTBEAT_TIMEOUT);
      }
    }, HEARTBEAT_INTERVAL);
    
    // Listen for heartbeat ack
    const handleMessage = (event: MessageEvent) => {
      const msg = JSON.parse(event.data);
      if (msg.type === 'heartbeat:ack') {
        clearTimeout(timeoutRef.current);
      }
    };
    
    ws.addEventListener('message', handleMessage);
    
    return () => {
      clearInterval(interval);
      clearTimeout(timeoutRef.current);
      ws.removeEventListener('message', handleMessage);
    };
  }, [ws]);
};
```

---

## 18.1.8 Offline Queue

```typescript
interface QueuedMessage {
  id: string;
  type: string;
  payload: unknown;
  timestamp: number;
  retries: number;
}

const useOfflineQueue = () => {
  const [queue, setQueue] = useState<QueuedMessage[]>([]);
  const wsRef = useRef<WebSocket | null>(null);
  
  const enqueue = (type: string, payload: unknown) => {
    setQueue(prev => [...prev, {
      id: generateId(),
      type,
      payload,
      timestamp: Date.now(),
      retries: 0,
    }]);
  };
  
  const flush = async () => {
    if (!wsRef.current || wsRef.current.readyState !== WebSocket.OPEN) return;
    
    for (const msg of queue) {
      try {
        wsRef.current.send(JSON.stringify(msg));
        setQueue(prev => prev.filter(m => m.id !== msg.id));
      } catch {
        setQueue(prev => prev.map(m => 
          m.id === msg.id ? { ...m, retries: m.retries + 1 } : m
        ));
      }
    }
  };
  
  // Flush when connection restored
  useEffect(() => {
    if (wsRef.current?.readyState === WebSocket.OPEN && queue.length > 0) {
      flush();
    }
  }, [wsRef.current?.readyState, queue.length]);
  
  return { enqueue, queue, flush };
};
```

---

## 18.1.9 Acceptance Criteria

### Connection Management (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CM-001 | connect() establishes WebSocket connection | Critical | Integration test |
| CM-002 | Connection state transitions: connecting → connected | Critical | State test |
| CM-003 | disconnect() closes connection cleanly | Critical | Cleanup test |
| CM-004 | Auto-reconnect on unexpected close | Critical | Reconnection test |
| CM-005 | Exponential backoff: 1s, 2s, 4s, ..., max 30s | High | Timing test |
| CM-006 | getState() returns current connection state | High | State test |
| CM-007 | Connection errors logged to console | High | Error test |
| CM-008 | Max reconnect attempts configurable (default: unlimited) | Medium | Config test |

### Message Protocol (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MP-001 | Messages follow {type, payload, timestamp, id} schema | Critical | Schema test |
| MP-002 | All outgoing messages include unique id | High | ID generation test |
| MP-003 | Timestamp set to current time on send | High | Timing test |
| MP-004 | JSON serialization/deserialization works correctly | Critical | Parse test |
| MP-005 | Invalid JSON messages logged and ignored | High | Error handling test |

### Message Types (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| MT-001 | llm:log messages handled by LLM log handler | Critical | Handler test |
| MT-002 | file:updated messages trigger file refresh | High | Handler test |
| MT-003 | project:sync messages update project state | High | Handler test |
| MT-004 | presence:update messages update user presence | High | Handler test |
| MT-005 | job:progress messages update job status UI | High | Handler test |
| MT-006 | heartbeat messages trigger heartbeat:ack response | Critical | Ping/pong test |
| MT-007 | Unknown message types logged but not crash | High | Fallback test |

### Subscription System (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SS-001 | subscribe(channel, handler) registers handler | Critical | Registration test |
| SS-002 | Handler invoked for matching channel messages | Critical | Dispatch test |
| SS-003 | Unsubscribe function removes handler | Critical | Cleanup test |
| SS-004 | Multiple handlers per channel supported | High | Multi-handler test |
| SS-005 | Handlers isolated (one failure doesn't affect others) | High | Isolation test |

### LLM Streaming (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| LS-001 | useLLMStream hook subscribes to llm:{sessionId} channel | Critical | Hook test |
| LS-002 | 'start' event sets status to 'streaming', clears output | Critical | Event test |
| LS-003 | 'token' events append content to output | Critical | Event test |
| LS-004 | 'complete' event sets status to 'complete' | Critical | Event test |
| LS-005 | 'error' event sets status to 'error' with message | High | Error test |
| LS-006 | tokensGenerated and tokensPerSecond metadata exposed | High | Metadata test |
| LS-007 | Cleanup unsubscribes on unmount | Critical | Cleanup test |

### SSE Fallback (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SSE-001 | useSSE hook creates EventSource connection | High | Integration test |
| SSE-002 | onmessage parses JSON and updates state | High | Parse test |
| SSE-003 | onerror closes connection and triggers reconnect | High | Error test |
| SSE-004 | Cleanup closes EventSource on unmount | Critical | Cleanup test |
| SSE-005 | Works as fallback when WebSocket unavailable | High | Fallback test |

### Presence System (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PS-001 | User presence includes id, name, status, currentFile | High | Schema test |
| PS-002 | updatePresence broadcasts presence:update message | High | Broadcast test |
| PS-003 | lastSeen updated on every presence update | High | Timestamp test |
| PS-004 | Cursor position tracked when in editor | Medium | Cursor test |
| PS-005 | Users map tracks all connected users | High | State test |
| PS-006 | Stale presence (> 60s without update) marked 'away' | Medium | Timeout test |

### Heartbeat & Health (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| HB-001 | Heartbeat sent every 30 seconds | Critical | Timing test |
| HB-002 | heartbeat:ack expected within 10 seconds | Critical | Timeout test |
| HB-003 | Missing ack triggers reconnection | Critical | Reconnect test |
| HB-004 | Heartbeat interval configurable | Medium | Config test |
| HB-005 | Heartbeat timeout configurable | Medium | Config test |

### Offline Queue (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| OQ-001 | Messages queued when disconnected | High | Queue test |
| OQ-002 | Queue flushed on reconnection | High | Flush test |
| OQ-003 | Failed sends increment retry counter | High | Retry test |
| OQ-004 | Messages with max retries dropped | Medium | Drop test |
| OQ-005 | Queue persisted to localStorage (optional) | Low | Persistence test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | Connection errors don't crash application | Critical | Error test |
| EH-002 | Parse errors for invalid JSON logged | High | Parse error test |
| EH-003 | Handler errors caught and logged | High | Handler error test |
| EH-004 | Error codes follow ERR_WS_4xxx range | High | Error code test |
| EH-005 | Connection close reason logged | High | Close test |

### Performance (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PF-001 | Message dispatch < 1ms per handler | High | Benchmark test |
| PF-002 | 1000 messages/second sustainable | High | Throughput test |
| PF-003 | Memory stable under continuous streaming | Critical | Memory test |
| PF-004 | Reconnection completes within 5s | High | Timing test |
| PF-005 | Offline queue max size: 1000 messages | High | Limit test |

### Security (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SEC-001 | WSS (secure WebSocket) used in production | Critical | Protocol test |
| SEC-002 | Auth token included in connection handshake | Critical | Auth test |
| SEC-003 | Invalid auth rejected with 4001 close code | Critical | Auth error test |
| SEC-004 | Message size limited to 1MB | High | Size limit test |
| SEC-005 | Rate limiting on send (100 msg/s max) | High | Rate test |

---

## Related Specs

- [LLM Live Logging](../06-ai-integration/06-llm-live-logging.md)
- [API Client](../15-api-client/01-http-client.md)
- [Consistency Checker](../08-consistency-checker/00-overview.md) - SSE progress
- [System Monitoring](../17-monitoring/01-system-monitoring.md)
