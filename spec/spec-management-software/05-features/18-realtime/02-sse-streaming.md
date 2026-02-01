# SSE Streaming

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Overview

Server-Sent Events (SSE) implementation for unidirectional streaming from server to client, used primarily for AI token streaming and live notifications.

**Cross-References:**
- [WebSocket Integration](./01-websocket-integration.md)
- [Error Recovery](./04-error-recovery.md)
- [AI Integration](../06-ai-integration/00-overview.md)
- [LLM Live Logging](../06-ai-integration/02-llm-integration.md)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      SSE Streaming System                        │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐│
│  │ SSEClient    │  │ TokenStream  │  │ NotificationChannel    ││
│  │              │  │              │  │                        ││
│  │ - connect    │  │ - buffer     │  │ - subscribe            ││
│  │ - reconnect  │  │ - flush      │  │ - broadcast            ││
│  │ - lastEventId│  │ - render     │  │ - filter               ││
│  └──────────────┘  └──────────────┘  └────────────────────────┘│
│         │                │                      │               │
│         └────────────────┼──────────────────────┘               │
│                          │                                      │
│                    ┌─────▼─────┐                                │
│                    │ EventSource│                               │
│                    │  Manager   │                               │
│                    └───────────┘                                │
└─────────────────────────────────────────────────────────────────┘
```

---

## SSE Channels

| Channel | Endpoint | Use Case | Event Types |
|---------|----------|----------|-------------|
| AI Token Stream | `/api/v1/ai/stream/{sessionId}` | LLM response streaming | `token`, `done`, `error` |
| Notifications | `/api/v1/notifications/stream` | Live alerts | `notification`, `read`, `clear` |
| Build Progress | `/api/v1/build/stream/{buildId}` | Build status updates | `progress`, `log`, `complete`, `error` |
| Consistency Check | `/api/v1/consistency/stream/{checkId}` | Validation progress | `phase`, `issue`, `complete` |

---

## Data Models

### SSE Event Format

```
id: <event-id>
event: <event-type>
data: <json-payload>
retry: <reconnect-delay-ms>

```

### Event Payload Structure

```typescript
// Base event structure
interface SSEEvent<T = unknown> {
  id: string;
  type: string;
  data: T;
  timestamp: string;
}

// AI Token event
interface TokenEvent {
  sessionId: string;
  token: string;
  index: number;
  isComplete: boolean;
}

// Notification event
interface NotificationEvent {
  id: string;
  type: 'info' | 'success' | 'warning' | 'error';
  title: string;
  message: string;
  actionUrl?: string;
  expiresAt?: string;
}

// Build progress event
interface BuildProgressEvent {
  buildId: string;
  phase: 'check' | 'fix' | 'verify';
  progress: number;
  message: string;
  errors?: string[];
}
```

---

## SSE Client Implementation

### Core SSEClient Class

```typescript
// src/lib/realtime/SSEClient.ts

interface SSEClientConfig {
  url: string;
  withCredentials?: boolean;
  reconnectDelay?: number;
  maxRetries?: number;
  onOpen?: () => void;
  onError?: (error: Event) => void;
  onMessage?: (event: MessageEvent) => void;
}

export class SSEClient {
  private eventSource: EventSource | null = null;
  private lastEventId: string | null = null;
  private retryCount: number = 0;
  private reconnectTimeout: NodeJS.Timeout | null = null;
  private listeners: Map<string, Set<(data: unknown) => void>> = new Map();

  private config: Required<SSEClientConfig> = {
    url: '',
    withCredentials: true,
    reconnectDelay: 1000,
    maxRetries: 10,
    onOpen: () => {},
    onError: () => {},
    onMessage: () => {},
  };

  constructor(config: SSEClientConfig) {
    this.config = { ...this.config, ...config };
  }

  connect(): void {
    const url = this.buildUrl();
    
    console.log('[SSE] Connecting to:', url);

    this.eventSource = new EventSource(url, {
      withCredentials: this.config.withCredentials,
    });

    this.eventSource.onopen = () => {
      console.log('[SSE] Connection opened');
      this.retryCount = 0;
      this.config.onOpen();
    };

    this.eventSource.onmessage = (event) => {
      this.handleMessage(event);
    };

    this.eventSource.onerror = (error) => {
      console.error('[SSE] Connection error:', error);
      this.config.onError(error);
      this.handleError();
    };

    // Register typed event listeners
    this.registerEventListeners();
  }

  private buildUrl(): string {
    const url = new URL(this.config.url, window.location.origin);
    
    if (this.lastEventId) {
      url.searchParams.set('lastEventId', this.lastEventId);
    }
    
    return url.toString();
  }

  private handleMessage(event: MessageEvent): void {
    // Track last event ID for reconnection
    if (event.lastEventId) {
      this.lastEventId = event.lastEventId;
    }

    try {
      const data = JSON.parse(event.data);
      this.config.onMessage(event);
      this.emit('message', data);
    } catch (error) {
      console.error('[SSE] Failed to parse message:', error);
    }
  }

  private registerEventListeners(): void {
    if (!this.eventSource) return;

    // Register listeners for each event type
    for (const [eventType, callbacks] of this.listeners) {
      this.eventSource.addEventListener(eventType, (event: MessageEvent) => {
        if (event.lastEventId) {
          this.lastEventId = event.lastEventId;
        }

        try {
          const data = JSON.parse(event.data);
          callbacks.forEach(callback => callback(data));
        } catch (error) {
          console.error(`[SSE] Failed to parse ${eventType} event:`, error);
        }
      });
    }
  }

  private handleError(): void {
    this.close();

    if (this.retryCount >= this.config.maxRetries) {
      console.error('[SSE] Max retries exceeded');
      this.emit('maxRetriesExceeded', null);
      return;
    }

    const delay = this.calculateBackoff();
    this.retryCount++;

    console.log(`[SSE] Reconnecting in ${delay}ms (attempt ${this.retryCount})`);

    this.reconnectTimeout = setTimeout(() => {
      this.connect();
    }, delay);
  }

  private calculateBackoff(): number {
    const baseDelay = this.config.reconnectDelay;
    const exponentialDelay = baseDelay * Math.pow(2, this.retryCount);
    const maxDelay = 30000;
    const jitter = Math.random() * 1000;
    
    return Math.min(exponentialDelay, maxDelay) + jitter;
  }

  on<T>(eventType: string, callback: (data: T) => void): () => void {
    if (!this.listeners.has(eventType)) {
      this.listeners.set(eventType, new Set());
    }
    
    this.listeners.get(eventType)!.add(callback as (data: unknown) => void);

    // If already connected, add listener to EventSource
    if (this.eventSource) {
      this.eventSource.addEventListener(eventType, (event: MessageEvent) => {
        try {
          const data = JSON.parse(event.data);
          callback(data);
        } catch (error) {
          console.error(`[SSE] Failed to parse ${eventType} event:`, error);
        }
      });
    }

    // Return unsubscribe function
    return () => {
      this.listeners.get(eventType)?.delete(callback as (data: unknown) => void);
    };
  }

  private emit(eventType: string, data: unknown): void {
    this.listeners.get(eventType)?.forEach(callback => callback(data));
  }

  close(): void {
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
      this.reconnectTimeout = null;
    }

    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
  }

  get isConnected(): boolean {
    return this.eventSource?.readyState === EventSource.OPEN;
  }

  get connectionState(): 'connecting' | 'open' | 'closed' {
    if (!this.eventSource) return 'closed';
    
    switch (this.eventSource.readyState) {
      case EventSource.CONNECTING: return 'connecting';
      case EventSource.OPEN: return 'open';
      case EventSource.CLOSED: return 'closed';
      default: return 'closed';
    }
  }
}
```

---

## AI Token Streaming

### TokenStreamManager

```typescript
// src/lib/realtime/TokenStreamManager.ts

interface TokenStreamConfig {
  sessionId: string;
  onToken: (token: string, index: number) => void;
  onComplete: (fullText: string) => void;
  onError: (error: Error) => void;
}

export class TokenStreamManager {
  private client: SSEClient;
  private buffer: string[] = [];
  private isComplete: boolean = false;

  constructor(private config: TokenStreamConfig) {
    this.client = new SSEClient({
      url: `/api/v1/ai/stream/${config.sessionId}`,
      onError: () => {
        config.onError(new Error('Stream connection failed'));
      },
    });

    this.setupListeners();
  }

  private setupListeners(): void {
    // Token event
    this.client.on<TokenEvent>('token', (data) => {
      this.buffer.push(data.token);
      this.config.onToken(data.token, data.index);
    });

    // Stream complete
    this.client.on<{ fullText: string }>('done', (data) => {
      this.isComplete = true;
      this.config.onComplete(data.fullText || this.buffer.join(''));
      this.close();
    });

    // Error event
    this.client.on<{ message: string; code: string }>('error', (data) => {
      this.config.onError(new Error(`${data.code}: ${data.message}`));
      this.close();
    });
  }

  start(): void {
    this.buffer = [];
    this.isComplete = false;
    this.client.connect();
  }

  close(): void {
    this.client.close();
  }

  get currentText(): string {
    return this.buffer.join('');
  }

  get completed(): boolean {
    return this.isComplete;
  }
}
```

### useAITokenStream Hook

```typescript
// src/hooks/useAITokenStream.ts

interface UseAITokenStreamOptions {
  sessionId: string;
  autoStart?: boolean;
}

interface UseAITokenStreamReturn {
  text: string;
  isStreaming: boolean;
  isComplete: boolean;
  error: Error | null;
  start: () => void;
  stop: () => void;
}

export function useAITokenStream({
  sessionId,
  autoStart = false,
}: UseAITokenStreamOptions): UseAITokenStreamReturn {
  const [text, setText] = useState('');
  const [isStreaming, setIsStreaming] = useState(false);
  const [isComplete, setIsComplete] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  
  const managerRef = useRef<TokenStreamManager | null>(null);

  const start = useCallback(() => {
    setText('');
    setIsStreaming(true);
    setIsComplete(false);
    setError(null);

    managerRef.current = new TokenStreamManager({
      sessionId,
      onToken: (token) => {
        setText(prev => prev + token);
      },
      onComplete: (fullText) => {
        setText(fullText);
        setIsStreaming(false);
        setIsComplete(true);
      },
      onError: (err) => {
        setError(err);
        setIsStreaming(false);
      },
    });

    managerRef.current.start();
  }, [sessionId]);

  const stop = useCallback(() => {
    managerRef.current?.close();
    setIsStreaming(false);
  }, []);

  useEffect(() => {
    if (autoStart) {
      start();
    }

    return () => {
      managerRef.current?.close();
    };
  }, [autoStart, start]);

  return { text, isStreaming, isComplete, error, start, stop };
}
```

### StreamingTextDisplay Component

```typescript
// src/components/ai/StreamingTextDisplay.tsx

interface StreamingTextDisplayProps {
  sessionId: string;
  className?: string;
  onComplete?: (text: string) => void;
}

export function StreamingTextDisplay({
  sessionId,
  className,
  onComplete,
}: StreamingTextDisplayProps) {
  const { text, isStreaming, isComplete, error } = useAITokenStream({
    sessionId,
    autoStart: true,
  });

  useEffect(() => {
    if (isComplete && onComplete) {
      onComplete(text);
    }
  }, [isComplete, text, onComplete]);

  if (error) {
    return (
      <div className="text-destructive p-4 rounded-md bg-destructive/10">
        <p className="font-medium">Stream Error</p>
        <p className="text-sm">{error.message}</p>
      </div>
    );
  }

  return (
    <div className={cn('prose prose-sm max-w-none', className)}>
      <ReactMarkdown>{text}</ReactMarkdown>
      {isStreaming && (
        <span className="inline-block w-2 h-4 bg-primary animate-pulse ml-0.5" />
      )}
    </div>
  );
}
```

---

## Notification Channel

### NotificationStreamClient

```typescript
// src/lib/realtime/NotificationStreamClient.ts

type NotificationType = 'info' | 'success' | 'warning' | 'error';

interface Notification {
  id: string;
  type: NotificationType;
  title: string;
  message: string;
  actionUrl?: string;
  createdAt: Date;
  read: boolean;
}

export class NotificationStreamClient {
  private client: SSEClient;
  private notifications: Map<string, Notification> = new Map();
  private listeners: Set<(notifications: Notification[]) => void> = new Set();

  constructor() {
    this.client = new SSEClient({
      url: '/api/v1/notifications/stream',
    });

    this.setupListeners();
  }

  private setupListeners(): void {
    // New notification
    this.client.on<NotificationEvent>('notification', (data) => {
      const notification: Notification = {
        ...data,
        createdAt: new Date(data.timestamp || Date.now()),
        read: false,
      };
      
      this.notifications.set(data.id, notification);
      this.notifyListeners();
    });

    // Mark as read
    this.client.on<{ id: string }>('read', (data) => {
      const notification = this.notifications.get(data.id);
      if (notification) {
        notification.read = true;
        this.notifyListeners();
      }
    });

    // Clear notification
    this.client.on<{ id: string }>('clear', (data) => {
      this.notifications.delete(data.id);
      this.notifyListeners();
    });

    // Clear all
    this.client.on('clearAll', () => {
      this.notifications.clear();
      this.notifyListeners();
    });
  }

  connect(): void {
    this.client.connect();
  }

  disconnect(): void {
    this.client.close();
  }

  subscribe(callback: (notifications: Notification[]) => void): () => void {
    this.listeners.add(callback);
    
    // Immediately call with current state
    callback(this.getNotifications());

    return () => {
      this.listeners.delete(callback);
    };
  }

  private notifyListeners(): void {
    const notifications = this.getNotifications();
    this.listeners.forEach(callback => callback(notifications));
  }

  getNotifications(): Notification[] {
    return Array.from(this.notifications.values())
      .sort((a, b) => b.createdAt.getTime() - a.createdAt.getTime());
  }

  getUnreadCount(): number {
    return Array.from(this.notifications.values())
      .filter(n => !n.read).length;
  }
}
```

### useNotifications Hook

```typescript
// src/hooks/useNotifications.ts

const notificationClient = new NotificationStreamClient();

export function useNotifications() {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [isConnected, setIsConnected] = useState(false);

  useEffect(() => {
    notificationClient.connect();
    setIsConnected(true);

    const unsubscribe = notificationClient.subscribe(setNotifications);

    return () => {
      unsubscribe();
      notificationClient.disconnect();
    };
  }, []);

  const unreadCount = useMemo(
    () => notifications.filter(n => !n.read).length,
    [notifications]
  );

  return {
    notifications,
    unreadCount,
    isConnected,
  };
}
```

### NotificationBell Component

```typescript
// src/components/notifications/NotificationBell.tsx

export function NotificationBell() {
  const { notifications, unreadCount } = useNotifications();
  const [isOpen, setIsOpen] = useState(false);

  return (
    <Popover open={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-destructive text-destructive-foreground text-xs flex items-center justify-center">
              {unreadCount > 9 ? '9+' : unreadCount}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-80 p-0" align="end">
        <div className="p-3 border-b">
          <h4 className="font-semibold">Notifications</h4>
        </div>
        <ScrollArea className="h-80">
          {notifications.length === 0 ? (
            <div className="p-4 text-center text-muted-foreground">
              No notifications
            </div>
          ) : (
            <div className="divide-y">
              {notifications.map(notification => (
                <NotificationItem
                  key={notification.id}
                  notification={notification}
                />
              ))}
            </div>
          )}
        </ScrollArea>
      </PopoverContent>
    </Popover>
  );
}
```

---

## Build Progress Streaming

### useBuildProgress Hook

```typescript
// src/hooks/useBuildProgress.ts

interface BuildProgress {
  phase: 'check' | 'fix' | 'verify';
  progress: number;
  message: string;
  errors: string[];
  isComplete: boolean;
}

export function useBuildProgress(buildId: string) {
  const [progress, setProgress] = useState<BuildProgress>({
    phase: 'check',
    progress: 0,
    message: 'Initializing...',
    errors: [],
    isComplete: false,
  });

  const clientRef = useRef<SSEClient | null>(null);

  useEffect(() => {
    const client = new SSEClient({
      url: `/api/v1/build/stream/${buildId}`,
    });

    client.on<BuildProgressEvent>('progress', (data) => {
      setProgress(prev => ({
        ...prev,
        phase: data.phase,
        progress: data.progress,
        message: data.message,
      }));
    });

    client.on<{ line: string }>('log', (data) => {
      console.log('[Build]', data.line);
    });

    client.on<{ success: boolean; errors?: string[] }>('complete', (data) => {
      setProgress(prev => ({
        ...prev,
        progress: 100,
        isComplete: true,
        errors: data.errors || [],
        message: data.success ? 'Build complete' : 'Build failed',
      }));
    });

    client.on<{ message: string }>('error', (data) => {
      setProgress(prev => ({
        ...prev,
        errors: [...prev.errors, data.message],
      }));
    });

    client.connect();
    clientRef.current = client;

    return () => {
      client.close();
    };
  }, [buildId]);

  return progress;
}
```

---

## Backend Implementation (Go)

### SSE Handler

```go
// internal/api/handlers/sse_handler.go

package handlers

import (
    "encoding/json"
    "fmt"
    "net/http"
    "time"
)

type SSEEvent struct {
    ID    string      `json:"id"`
    Event string      `json:"event"`
    Data  interface{} `json:"data"`
}

type SSEHandler struct {
    // dependencies
}

func (h *SSEHandler) StreamTokens(w http.ResponseWriter, r *http.Request) {
    // Set SSE headers
    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")
    w.Header().Set("Connection", "keep-alive")
    w.Header().Set("X-Accel-Buffering", "no") // Disable nginx buffering

    flusher, ok := w.(http.Flusher)
    if !ok {
        http.Error(w, "Streaming not supported", http.StatusInternalServerError)
        return
    }

    sessionID := chi.URLParam(r, "sessionId")
    lastEventID := r.URL.Query().Get("lastEventId")

    // Get token channel for this session
    tokenChan := h.aiService.GetTokenChannel(sessionID, lastEventID)
    
    ctx := r.Context()

    for {
        select {
        case <-ctx.Done():
            return
        case token, ok := <-tokenChan:
            if !ok {
                // Channel closed, stream complete
                h.sendEvent(w, flusher, SSEEvent{
                    ID:    fmt.Sprintf("%d", time.Now().UnixNano()),
                    Event: "done",
                    Data:  map[string]interface{}{"fullText": token.FullText},
                })
                return
            }

            h.sendEvent(w, flusher, SSEEvent{
                ID:    token.ID,
                Event: "token",
                Data: map[string]interface{}{
                    "sessionId": sessionID,
                    "token":     token.Text,
                    "index":     token.Index,
                },
            })
        }
    }
}

func (h *SSEHandler) sendEvent(w http.ResponseWriter, flusher http.Flusher, event SSEEvent) {
    data, _ := json.Marshal(event.Data)

    fmt.Fprintf(w, "id: %s\n", event.ID)
    fmt.Fprintf(w, "event: %s\n", event.Event)
    fmt.Fprintf(w, "data: %s\n\n", data)
    
    flusher.Flush()
}

// Heartbeat to keep connection alive
func (h *SSEHandler) startHeartbeat(w http.ResponseWriter, flusher http.Flusher, done <-chan struct{}) {
    ticker := time.NewTicker(30 * time.Second)
    defer ticker.Stop()

    for {
        select {
        case <-done:
            return
        case <-ticker.C:
            fmt.Fprintf(w, ": heartbeat\n\n")
            flusher.Flush()
        }
    }
}
```

---

## Reconnection with lastEventId

### Event ID Generation

```go
// Backend: Generate monotonic event IDs
func generateEventID() string {
    return fmt.Sprintf("%d-%s", time.Now().UnixNano(), uuid.New().String()[:8])
}
```

### Server-Side Event Replay

```go
// Replay missed events on reconnection
func (h *SSEHandler) getEventsAfter(sessionID, lastEventID string) []SSEEvent {
    // Fetch events from buffer/database after lastEventID
    events, err := h.eventStore.GetEventsAfter(sessionID, lastEventID)
    if err != nil {
        return nil
    }
    return events
}
```

### Client-Side Reconnection

```typescript
// Automatic reconnection with state recovery
private buildUrl(): string {
    const url = new URL(this.config.url, window.location.origin);
    
    // Include last event ID for replay
    if (this.lastEventId) {
        url.searchParams.set('lastEventId', this.lastEventId);
    }
    
    return url.toString();
}
```

---

## Error Handling

### Error Codes

| Code | Name | Description | Recovery |
|------|------|-------------|----------|
| ERR_SSE_4101 | STREAM_CLOSED | EventSource closed unexpectedly | Auto-reconnect |
| ERR_SSE_4102 | STREAM_ERROR | Stream error event | Reconnect with lastEventId |
| ERR_SSE_4103 | PARSE_ERROR | Invalid event data | Log, skip event |
| ERR_SSE_4104 | AUTH_EXPIRED | Authentication expired | Refresh token, reconnect |
| ERR_SSE_4105 | RATE_LIMITED | Too many connections | Back off, retry |

### Error Event Format

```typescript
interface SSEErrorEvent {
  code: string;
  message: string;
  retryable: boolean;
  retryAfter?: number; // seconds
}
```

---

## Performance Considerations

### Token Batching

```typescript
// Batch tokens for smoother rendering
class TokenBatcher {
  private buffer: string[] = [];
  private flushTimeout: NodeJS.Timeout | null = null;
  private readonly flushInterval = 16; // ~60fps

  constructor(private onFlush: (tokens: string) => void) {}

  add(token: string): void {
    this.buffer.push(token);
    
    if (!this.flushTimeout) {
      this.flushTimeout = setTimeout(() => this.flush(), this.flushInterval);
    }
  }

  private flush(): void {
    if (this.buffer.length > 0) {
      this.onFlush(this.buffer.join(''));
      this.buffer = [];
    }
    this.flushTimeout = null;
  }
}
```

### Connection Limits

| Limit | Value | Reason |
|-------|-------|--------|
| Max concurrent SSE per user | 5 | Browser connection limits |
| Heartbeat interval | 30s | Keep-alive through proxies |
| Event buffer size | 1000 | Memory management |
| Reconnect max retries | 10 | Prevent infinite loops |

---

## Testing

### Unit Tests

```typescript
describe('SSEClient', () => {
  it('connects and receives events', async () => {
    const mockEventSource = createMockEventSource();
    const client = new SSEClient({ url: '/test/stream' });
    
    const receivedEvents: unknown[] = [];
    client.on('message', (data) => receivedEvents.push(data));
    
    client.connect();
    
    mockEventSource.emit('message', { data: JSON.stringify({ test: true }) });
    
    expect(receivedEvents).toContainEqual({ test: true });
  });

  it('tracks lastEventId for reconnection', async () => {
    const client = new SSEClient({ url: '/test/stream' });
    client.connect();
    
    // Simulate event with ID
    mockEventSource.emit('message', { 
      data: '{}', 
      lastEventId: 'event-123' 
    });
    
    // Verify lastEventId is included in reconnect URL
    client.close();
    client.connect();
    
    expect(mockEventSource.url).toContain('lastEventId=event-123');
  });

  it('applies exponential backoff on errors', async () => {
    vi.useFakeTimers();
    const client = new SSEClient({ 
      url: '/test/stream',
      reconnectDelay: 1000,
    });
    
    client.connect();
    
    // Trigger multiple errors
    mockEventSource.emit('error', new Event('error'));
    vi.advanceTimersByTime(1000);
    
    mockEventSource.emit('error', new Event('error'));
    vi.advanceTimersByTime(2000);
    
    // Verify increasing delays
    expect(mockEventSource.connectCount).toBe(3);
  });
});

describe('TokenStreamManager', () => {
  it('buffers and concatenates tokens', () => {
    const tokens: string[] = [];
    const manager = new TokenStreamManager({
      sessionId: 'test',
      onToken: (token) => tokens.push(token),
      onComplete: () => {},
      onError: () => {},
    });

    manager.start();
    
    // Simulate token events
    simulateTokenEvent('Hello');
    simulateTokenEvent(' world');
    
    expect(manager.currentText).toBe('Hello world');
    expect(tokens).toEqual(['Hello', ' world']);
  });
});
```

---

## Related Specs

- [WebSocket Integration](./01-websocket-integration.md)
- [Presence System](./03-presence-system.md)
- [Error Recovery](./04-error-recovery.md)
- [AI Integration](../06-ai-integration/00-overview.md)

---

## Implementation Checklist

- [ ] SSEClient core class with EventSource wrapper
- [ ] Reconnection with lastEventId tracking
- [ ] Exponential backoff for error recovery
- [ ] TokenStreamManager for AI streaming
- [ ] useAITokenStream React hook
- [ ] StreamingTextDisplay component
- [ ] NotificationStreamClient singleton
- [ ] useNotifications hook
- [ ] NotificationBell component
- [ ] Build progress streaming
- [ ] Go SSE handler with proper headers
- [ ] Event replay on reconnection
- [ ] Token batching for performance
- [ ] Comprehensive test coverage
