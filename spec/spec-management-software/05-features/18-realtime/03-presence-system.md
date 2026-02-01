# Presence System

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Overview

Real-time user presence tracking for collaborative editing, showing active users, cursor positions, and selection states across shared documents.

**Cross-References:**
- [WebSocket Integration](./01-websocket-integration.md)
- [Error Recovery](./04-error-recovery.md)
- [Collaborative Edit](../06-ai-integration/00-overview.md)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Presence System                          │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │PresenceHub  │  │UserTracker  │  │ CursorBroadcaster   │ │
│  │             │  │             │  │                     │ │
│  │ - channels  │  │ - sessions  │  │ - positions         │ │
│  │ - rooms     │  │ - heartbeat │  │ - selections        │ │
│  │ - sync      │  │ - timeout   │  │ - colors            │ │
│  └─────────────┘  └─────────────┘  └─────────────────────┘ │
│           │              │                   │              │
│           └──────────────┴───────────────────┘              │
│                          │                                  │
│                    ┌─────▼─────┐                           │
│                    │ WebSocket │                           │
│                    │  Manager  │                           │
│                    └───────────┘                           │
└─────────────────────────────────────────────────────────────┘
```

---

## Data Models

### PresenceState

| Field | Type | Description |
|-------|------|-------------|
| userId | string | Unique user identifier |
| username | string | Display name |
| avatar | string | Avatar URL |
| color | string | Assigned cursor color (HSL) |
| status | PresenceStatus | online, away, busy |
| lastSeen | Date | Last activity timestamp |
| currentFile | string | Active file path |
| cursor | CursorPosition | Current cursor location |
| selection | SelectionRange | Active selection |

### CursorPosition

| Field | Type | Description |
|-------|------|-------------|
| line | number | 1-indexed line number |
| column | number | 1-indexed column |
| fileId | string | File identifier |

### SelectionRange

| Field | Type | Description |
|-------|------|-------------|
| start | CursorPosition | Selection start |
| end | CursorPosition | Selection end |
| isReversed | boolean | Selection direction |

---

## TypeScript Interfaces

```typescript
// src/types/presence.ts

export type PresenceStatus = 'online' | 'away' | 'busy' | 'offline';

export interface CursorPosition {
  line: number;
  column: number;
  fileId: string;
}

export interface SelectionRange {
  start: CursorPosition;
  end: CursorPosition;
  isReversed: boolean;
}

export interface PresenceState {
  userId: string;
  username: string;
  avatar?: string;
  color: string;
  status: PresenceStatus;
  lastSeen: Date;
  currentFile?: string;
  cursor?: CursorPosition;
  selection?: SelectionRange;
}

export interface PresenceRoom {
  roomId: string;
  projectId: string;
  users: Map<string, PresenceState>;
  maxUsers: number;
}

export interface PresenceEvent {
  type: 'join' | 'leave' | 'update' | 'sync';
  userId: string;
  state?: Partial<PresenceState>;
  timestamp: Date;
}
```

---

## React Components

### PresenceProvider

```typescript
// src/contexts/PresenceContext.tsx

interface PresenceContextValue {
  users: PresenceState[];
  currentUser: PresenceState | null;
  updatePresence: (state: Partial<PresenceState>) => void;
  updateCursor: (position: CursorPosition) => void;
  updateSelection: (range: SelectionRange | null) => void;
  isConnected: boolean;
}

const PresenceContext = createContext<PresenceContextValue | null>(null);

export function PresenceProvider({ 
  children, 
  roomId, 
  userId 
}: PresenceProviderProps) {
  const [users, setUsers] = useState<PresenceState[]>([]);
  const [isConnected, setIsConnected] = useState(false);
  const { socket, subscribe } = useWebSocket();

  useEffect(() => {
    // Subscribe to presence channel
    const unsubscribe = subscribe(`presence:${roomId}`, (event) => {
      handlePresenceEvent(event);
    });

    // Join room
    socket.send({
      type: 'presence:join',
      roomId,
      userId,
    });

    return () => {
      socket.send({ type: 'presence:leave', roomId, userId });
      unsubscribe();
    };
  }, [roomId, userId]);

  // Throttled cursor updates (50ms)
  const updateCursor = useThrottle((position: CursorPosition) => {
    socket.send({
      type: 'presence:cursor',
      roomId,
      position,
    });
  }, 50);

  return (
    <PresenceContext.Provider value={{ users, updateCursor, ... }}>
      {children}
    </PresenceContext.Provider>
  );
}
```

### UserCursors

```typescript
// src/components/presence/UserCursors.tsx

interface UserCursorsProps {
  fileId: string;
  editorView: EditorView;
}

export function UserCursors({ fileId, editorView }: UserCursorsProps) {
  const { users } = usePresence();
  
  const activeUsers = users.filter(
    user => user.currentFile === fileId && user.cursor
  );

  return (
    <>
      {activeUsers.map(user => (
        <CursorOverlay
          key={user.userId}
          user={user}
          editorView={editorView}
        />
      ))}
    </>
  );
}
```

### CursorOverlay

```typescript
// src/components/presence/CursorOverlay.tsx

interface CursorOverlayProps {
  user: PresenceState;
  editorView: EditorView;
}

export function CursorOverlay({ user, editorView }: CursorOverlayProps) {
  const position = useMemo(() => {
    if (!user.cursor) return null;
    return editorView.coordsAtPos(
      lineColumnToOffset(editorView, user.cursor.line, user.cursor.column)
    );
  }, [user.cursor, editorView]);

  if (!position) return null;

  return (
    <div
      className="absolute pointer-events-none z-50"
      style={{
        left: position.left,
        top: position.top,
        transform: 'translateY(-100%)',
      }}
    >
      {/* Cursor line */}
      <div
        className="w-0.5 h-5"
        style={{ backgroundColor: user.color }}
      />
      {/* Username label */}
      <div
        className="px-1.5 py-0.5 text-xs text-white rounded-sm whitespace-nowrap"
        style={{ backgroundColor: user.color }}
      >
        {user.username}
      </div>
    </div>
  );
}
```

### PresenceAvatars

```typescript
// src/components/presence/PresenceAvatars.tsx

interface PresenceAvatarsProps {
  maxVisible?: number;
  size?: 'sm' | 'md' | 'lg';
}

export function PresenceAvatars({ 
  maxVisible = 5, 
  size = 'md' 
}: PresenceAvatarsProps) {
  const { users } = usePresence();
  
  const visibleUsers = users.slice(0, maxVisible);
  const overflow = users.length - maxVisible;

  return (
    <div className="flex -space-x-2">
      {visibleUsers.map(user => (
        <Tooltip key={user.userId} content={user.username}>
          <Avatar
            src={user.avatar}
            fallback={user.username[0]}
            className="ring-2 ring-background"
            style={{ 
              '--avatar-ring': user.color 
            } as React.CSSProperties}
          />
        </Tooltip>
      ))}
      {overflow > 0 && (
        <div className="flex items-center justify-center w-8 h-8 rounded-full bg-muted text-xs font-medium">
          +{overflow}
        </div>
      )}
    </div>
  );
}
```

---

## WebSocket Events

### Client → Server

| Event | Payload | Description |
|-------|---------|-------------|
| `presence:join` | `{ roomId, userId, state }` | Join presence room |
| `presence:leave` | `{ roomId, userId }` | Leave presence room |
| `presence:cursor` | `{ roomId, position }` | Update cursor position |
| `presence:selection` | `{ roomId, range }` | Update selection range |
| `presence:status` | `{ roomId, status }` | Update user status |

### Server → Client

| Event | Payload | Description |
|-------|---------|-------------|
| `presence:sync` | `{ users: PresenceState[] }` | Full state sync |
| `presence:user_joined` | `{ user: PresenceState }` | User joined room |
| `presence:user_left` | `{ userId }` | User left room |
| `presence:user_updated` | `{ userId, changes }` | User state changed |

---

## Color Assignment

### Algorithm

```typescript
// Assign unique colors to users based on user ID hash
const PRESENCE_COLORS = [
  'hsl(0, 70%, 50%)',    // Red
  'hsl(30, 70%, 50%)',   // Orange
  'hsl(60, 70%, 50%)',   // Yellow
  'hsl(120, 70%, 50%)',  // Green
  'hsl(180, 70%, 50%)',  // Cyan
  'hsl(210, 70%, 50%)',  // Blue
  'hsl(270, 70%, 50%)',  // Purple
  'hsl(330, 70%, 50%)',  // Pink
];

function assignUserColor(userId: string): string {
  const hash = hashString(userId);
  return PRESENCE_COLORS[hash % PRESENCE_COLORS.length];
}

function hashString(str: string): number {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = ((hash << 5) - hash) + str.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}
```

---

## Heartbeat & Timeout

### Configuration

| Setting | Value | Description |
|---------|-------|-------------|
| Heartbeat Interval | 30s | Ping frequency |
| Away Timeout | 60s | Time until away status |
| Disconnect Timeout | 90s | Time until removal |

### Implementation

```typescript
// Heartbeat manager
class PresenceHeartbeat {
  private interval: NodeJS.Timeout | null = null;
  private lastActivity: Date = new Date();

  start(onHeartbeat: () => void) {
    this.interval = setInterval(() => {
      onHeartbeat();
      this.checkAwayStatus();
    }, 30000);
  }

  recordActivity() {
    this.lastActivity = new Date();
    // Reset away status if was away
  }

  private checkAwayStatus() {
    const idleTime = Date.now() - this.lastActivity.getTime();
    if (idleTime > 60000) {
      // Emit away status
    }
  }

  stop() {
    if (this.interval) {
      clearInterval(this.interval);
    }
  }
}
```

---

## Performance Optimizations

### Throttling

| Update Type | Throttle | Reason |
|-------------|----------|--------|
| Cursor Position | 50ms | High frequency updates |
| Selection Range | 100ms | Medium frequency |
| Status Change | 0ms | Immediate |
| File Switch | 0ms | Immediate |

### Batching

```typescript
// Batch presence updates for efficiency
class PresenceBatcher {
  private pending: Map<string, Partial<PresenceState>> = new Map();
  private timeout: NodeJS.Timeout | null = null;

  queue(userId: string, update: Partial<PresenceState>) {
    const existing = this.pending.get(userId) || {};
    this.pending.set(userId, { ...existing, ...update });

    if (!this.timeout) {
      this.timeout = setTimeout(() => this.flush(), 16);
    }
  }

  private flush() {
    const updates = Array.from(this.pending.entries());
    this.pending.clear();
    this.timeout = null;

    // Send batched updates
    this.emit('batch', updates);
  }
}
```

---

## Error Handling

| Error | Code | Recovery |
|-------|------|----------|
| Room Full | ERR_PRESENCE_4001 | Show capacity message |
| Invalid State | ERR_PRESENCE_4002 | Reset to default |
| Sync Failed | ERR_PRESENCE_4003 | Request full sync |
| Connection Lost | ERR_WS_4001 | See [Error Recovery](./04-error-recovery.md) |

---

## Testing

### Unit Tests

```typescript
describe('PresenceSystem', () => {
  it('assigns unique colors to users', () => {
    const color1 = assignUserColor('user-1');
    const color2 = assignUserColor('user-2');
    expect(color1).not.toBe(color2);
  });

  it('throttles cursor updates', async () => {
    const { result } = renderHook(() => usePresence());
    const sendSpy = vi.spyOn(socket, 'send');

    // Rapid cursor updates
    for (let i = 0; i < 10; i++) {
      result.current.updateCursor({ line: i, column: 0, fileId: 'test' });
    }

    await waitFor(() => {
      // Should be throttled to fewer calls
      expect(sendSpy).toHaveBeenCalledTimes(2);
    });
  });

  it('removes user on timeout', async () => {
    vi.useFakeTimers();
    const { result } = renderHook(() => usePresence());

    // Simulate user going inactive
    vi.advanceTimersByTime(90000);

    expect(result.current.users).not.toContainEqual(
      expect.objectContaining({ userId: 'inactive-user' })
    );
  });
});
```

---

## Related Specs

- [WebSocket Integration](./01-websocket-integration.md)
- [Error Recovery](./04-error-recovery.md)
- [Collaborative Editing](../06-ai-integration/00-overview.md)

---

## Implementation Checklist

- [ ] PresenceContext and Provider
- [ ] WebSocket presence channel integration
- [ ] CursorOverlay component with CodeMirror
- [ ] SelectionHighlight component
- [ ] PresenceAvatars component
- [ ] Heartbeat manager
- [ ] Color assignment system
- [ ] Throttling and batching
- [ ] Away/busy status detection
- [ ] Unit and integration tests
