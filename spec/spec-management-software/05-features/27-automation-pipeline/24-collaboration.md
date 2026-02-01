# Collaboration System

**Version:** 1.0.0  
**Status:** Specified  
**Updated:** 2026-01-30  
**Parent:** [Automation Pipeline](./00-overview.md)

---

## Overview

Real-time collaborative editing system for pipelines, enabling multiple users to work simultaneously on the same pipeline canvas. Features presence awareness, cursor tracking, conflict resolution, and synchronized editing with operational transformation.

---

## Architecture

### Collaboration Stack

```
┌─────────────────────────────────────────────────────────────┐
│                     React Flow Canvas                        │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│  │  Presence   │  │   Cursor    │  │  Selection  │          │
│  │  Awareness  │  │  Tracking   │  │    Sync     │          │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘          │
│         │                │                │                  │
│         └────────────────┼────────────────┘                  │
│                          ▼                                   │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              Collaboration Engine                      │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │  │
│  │  │   CRDT      │  │  Operation  │  │  Conflict   │    │  │
│  │  │   State     │  │  Transform  │  │  Resolver   │    │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘    │  │
│  └───────────────────────────────────────────────────────┘  │
│                          │                                   │
│                          ▼                                   │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              WebSocket Transport                       │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### CollaborationSession Table

```sql
CREATE TABLE CollaborationSession (
  Id              TEXT PRIMARY KEY,
  PipelineId      TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  -- Session state
  IsActive        INTEGER NOT NULL DEFAULT 1,
  StartedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  LastActivityAt  TEXT NOT NULL DEFAULT (datetime('now')),
  
  -- Session metadata
  InitiatedBy     TEXT NOT NULL,
  
  UNIQUE(PipelineId, IsActive)               -- One active session per pipeline
);

CREATE INDEX idx_collab_pipeline ON CollaborationSession(PipelineId);
CREATE INDEX idx_collab_active ON CollaborationSession(IsActive);
```

### SessionParticipant Table

```sql
CREATE TABLE SessionParticipant (
  Id              TEXT PRIMARY KEY,
  SessionId       TEXT NOT NULL REFERENCES CollaborationSession(Id) ON DELETE CASCADE,
  UserId          TEXT NOT NULL,
  
  -- Participant state
  Status          TEXT NOT NULL DEFAULT 'ACTIVE', -- 'ACTIVE', 'IDLE', 'DISCONNECTED'
  
  -- Cursor/selection state (ephemeral, for recovery)
  CursorPosition  TEXT,                      -- JSON: { x, y }
  SelectedNodes   TEXT,                      -- JSON: array of node IDs
  ViewportState   TEXT,                      -- JSON: { x, y, zoom }
  
  -- Color assignment
  ColorIndex      INTEGER NOT NULL,
  
  -- Timestamps
  JoinedAt        TEXT NOT NULL DEFAULT (datetime('now')),
  LastSeenAt      TEXT NOT NULL DEFAULT (datetime('now')),
  LeftAt          TEXT,
  
  UNIQUE(SessionId, UserId)
);

CREATE INDEX idx_participant_session ON SessionParticipant(SessionId);
CREATE INDEX idx_participant_user ON SessionParticipant(UserId);
```

### CollaborationOperation Table

```sql
CREATE TABLE CollaborationOperation (
  Id              TEXT PRIMARY KEY,
  SessionId       TEXT NOT NULL REFERENCES CollaborationSession(Id) ON DELETE CASCADE,
  
  -- Operation details
  OperationType   TEXT NOT NULL,             -- 'INSERT', 'UPDATE', 'DELETE', 'MOVE'
  TargetType      TEXT NOT NULL,             -- 'NODE', 'EDGE', 'STAGE', 'CONFIG'
  TargetId        TEXT NOT NULL,
  
  -- Operation data
  OperationData   TEXT NOT NULL,             -- JSON: operation payload
  ParentOpId      TEXT,                      -- For OT: parent operation
  
  -- Authoring
  AuthorId        TEXT NOT NULL,
  ClientSeq       INTEGER NOT NULL,          -- Client sequence number
  ServerSeq       INTEGER,                   -- Server-assigned sequence
  
  -- Timestamps
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  AppliedAt       TEXT
);

CREATE INDEX idx_op_session ON CollaborationOperation(SessionId);
CREATE INDEX idx_op_server_seq ON CollaborationOperation(SessionId, ServerSeq);
CREATE INDEX idx_op_target ON CollaborationOperation(TargetType, TargetId);
```

---

## TypeScript Interfaces

### Presence Types

```typescript
interface Participant {
  readonly userId: string;
  readonly name: string;
  readonly avatar: string | null;
  readonly color: ParticipantColor;
  readonly status: ParticipantStatus;
  readonly cursor: CursorPosition | null;
  readonly selectedNodes: readonly string[];
  readonly viewport: ViewportState | null;
  readonly joinedAt: Date;
  readonly lastSeenAt: Date;
}

enum ParticipantStatus {
  ACTIVE = 'ACTIVE',
  IDLE = 'IDLE',
  DISCONNECTED = 'DISCONNECTED'
}

interface CursorPosition {
  readonly x: number;
  readonly y: number;
  readonly isOnCanvas: boolean;
}

interface ViewportState {
  readonly x: number;
  readonly y: number;
  readonly zoom: number;
}

interface ParticipantColor {
  readonly primary: string;      // Cursor/selection color
  readonly background: string;   // 10% opacity version
  readonly index: number;
}

// Predefined color palette for participants
const PARTICIPANT_COLORS: readonly ParticipantColor[] = [
  { primary: 'hsl(var(--chart-1))', background: 'hsl(var(--chart-1) / 0.1)', index: 0 },
  { primary: 'hsl(var(--chart-2))', background: 'hsl(var(--chart-2) / 0.1)', index: 1 },
  { primary: 'hsl(var(--chart-3))', background: 'hsl(var(--chart-3) / 0.1)', index: 2 },
  { primary: 'hsl(var(--chart-4))', background: 'hsl(var(--chart-4) / 0.1)', index: 3 },
  { primary: 'hsl(var(--chart-5))', background: 'hsl(var(--chart-5) / 0.1)', index: 4 },
  // ... up to 8-10 colors
];
```

### Operation Types

```typescript
enum OperationType {
  INSERT = 'INSERT',
  UPDATE = 'UPDATE',
  DELETE = 'DELETE',
  MOVE = 'MOVE'
}

enum TargetType {
  NODE = 'NODE',
  EDGE = 'EDGE',
  STAGE = 'STAGE',
  CONFIG = 'CONFIG',
  VARIABLE = 'VARIABLE'
}

interface CollaborationOperation {
  readonly id: string;
  readonly sessionId: string;
  readonly operationType: OperationType;
  readonly targetType: TargetType;
  readonly targetId: string;
  readonly operationData: OperationPayload;
  readonly authorId: string;
  readonly clientSeq: number;
  readonly serverSeq: number | null;
  readonly parentOpId: string | null;
  readonly createdAt: Date;
}

type OperationPayload =
  | InsertPayload
  | UpdatePayload
  | DeletePayload
  | MovePayload;

interface InsertPayload {
  readonly type: 'INSERT';
  readonly data: unknown;            // Full entity data
  readonly position?: Position;
}

interface UpdatePayload {
  readonly type: 'UPDATE';
  readonly path: string;             // JSONPath to updated field
  readonly oldValue: unknown;
  readonly newValue: unknown;
}

interface DeletePayload {
  readonly type: 'DELETE';
  readonly previousData: unknown;    // For undo
}

interface MovePayload {
  readonly type: 'MOVE';
  readonly oldPosition: Position;
  readonly newPosition: Position;
}
```

### WebSocket Messages

```typescript
// Client → Server
type ClientMessage =
  | { type: 'JOIN'; pipelineId: string }
  | { type: 'LEAVE' }
  | { type: 'CURSOR_MOVE'; position: CursorPosition }
  | { type: 'SELECTION_CHANGE'; nodeIds: readonly string[] }
  | { type: 'VIEWPORT_CHANGE'; viewport: ViewportState }
  | { type: 'OPERATION'; operation: ClientOperation }
  | { type: 'PING' };

interface ClientOperation {
  readonly clientId: string;
  readonly clientSeq: number;
  readonly operationType: OperationType;
  readonly targetType: TargetType;
  readonly targetId: string;
  readonly payload: OperationPayload;
}

// Server → Client
type ServerMessage =
  | { type: 'SESSION_STATE'; state: SessionState }
  | { type: 'PARTICIPANT_JOINED'; participant: Participant }
  | { type: 'PARTICIPANT_LEFT'; userId: string }
  | { type: 'PARTICIPANT_UPDATE'; userId: string; update: ParticipantUpdate }
  | { type: 'CURSOR_UPDATE'; userId: string; position: CursorPosition }
  | { type: 'SELECTION_UPDATE'; userId: string; nodeIds: readonly string[] }
  | { type: 'OPERATION_ACK'; clientSeq: number; serverSeq: number }
  | { type: 'OPERATION_BROADCAST'; operation: CollaborationOperation }
  | { type: 'CONFLICT'; conflict: OperationConflict }
  | { type: 'PONG' };

interface SessionState {
  readonly sessionId: string;
  readonly participants: readonly Participant[];
  readonly operations: readonly CollaborationOperation[];
  readonly lastServerSeq: number;
}
```

---

## Collaboration Engine

### CollaborationClient

```typescript
interface CollaborationClient {
  // Connection
  connect(pipelineId: string): Promise<SessionState>;
  disconnect(): void;
  reconnect(): Promise<void>;
  
  // Presence
  updateCursor(position: CursorPosition): void;
  updateSelection(nodeIds: readonly string[]): void;
  updateViewport(viewport: ViewportState): void;
  
  // Operations
  applyOperation(operation: ClientOperation): Promise<OperationResult>;
  
  // State
  getParticipants(): readonly Participant[];
  getSessionState(): SessionState;
  
  // Events
  on<E extends CollabEvent>(event: E, handler: CollabEventHandler<E>): () => void;
}

type CollabEvent =
  | 'participant:join'
  | 'participant:leave'
  | 'participant:update'
  | 'cursor:update'
  | 'selection:update'
  | 'operation:received'
  | 'conflict:detected'
  | 'connection:lost'
  | 'connection:restored';

interface OperationResult {
  readonly success: boolean;
  readonly serverSeq: number | null;
  readonly conflict: OperationConflict | null;
}
```

### OperationalTransform

```typescript
interface OperationalTransform {
  // Transform operations for consistency
  transform(
    op1: CollaborationOperation,
    op2: CollaborationOperation,
    priority: 'LEFT' | 'RIGHT'
  ): TransformResult;
  
  // Compose multiple operations
  compose(
    ops: readonly CollaborationOperation[]
  ): CollaborationOperation;
  
  // Check if operations conflict
  detectConflict(
    op1: CollaborationOperation,
    op2: CollaborationOperation
  ): OperationConflict | null;
}

interface TransformResult {
  readonly op1Prime: CollaborationOperation;  // op1 transformed against op2
  readonly op2Prime: CollaborationOperation;  // op2 transformed against op1
}

interface OperationConflict {
  readonly id: string;
  readonly type: ConflictType;
  readonly localOp: CollaborationOperation;
  readonly remoteOp: CollaborationOperation;
  readonly suggestedResolution: ResolutionStrategy;
}

enum ConflictType {
  CONCURRENT_EDIT = 'CONCURRENT_EDIT',     // Same field edited
  DELETE_UPDATE = 'DELETE_UPDATE',          // One deleted what other updated
  MOVE_MOVE = 'MOVE_MOVE'                   // Same node moved by two users
}

enum ResolutionStrategy {
  KEEP_LOCAL = 'KEEP_LOCAL',
  KEEP_REMOTE = 'KEEP_REMOTE',
  MERGE = 'MERGE',
  PROMPT_USER = 'PROMPT_USER'
}
```

### ConflictResolver

```typescript
interface ConflictResolver {
  // Auto-resolve simple conflicts
  autoResolve(conflict: OperationConflict): ResolvedOperation | null;
  
  // Present conflict to user
  promptUser(conflict: OperationConflict): Promise<ResolvedOperation>;
  
  // Apply resolution
  applyResolution(
    conflict: OperationConflict,
    resolution: ConflictResolution
  ): CollaborationOperation;
}

interface ConflictResolution {
  readonly strategy: ResolutionStrategy;
  readonly chosenValue?: unknown;
  readonly mergedValue?: unknown;
}

interface ResolvedOperation extends CollaborationOperation {
  readonly conflictId: string;
  readonly resolution: ConflictResolution;
}
```

---

## React Components

### CollaborationProvider

```typescript
interface CollaborationProviderProps {
  readonly pipelineId: string;
  readonly children: React.ReactNode;
}

const CollaborationContext = createContext<CollaborationContextValue | null>(null);

const CollaborationProvider: React.FC<CollaborationProviderProps> = ({
  pipelineId,
  children
}) => {
  const [client, setClient] = useState<CollaborationClient | null>(null);
  const [participants, setParticipants] = useState<readonly Participant[]>([]);
  const [isConnected, setIsConnected] = useState(false);
  const [connectionError, setConnectionError] = useState<Error | null>(null);
  
  // Initialize client
  useEffect(() => {
    const collaborationClient = createCollaborationClient();
    
    collaborationClient.connect(pipelineId)
      .then((state) => {
        setParticipants(state.participants);
        setIsConnected(true);
      })
      .catch(setConnectionError);
    
    setClient(collaborationClient);
    
    return () => {
      collaborationClient.disconnect();
    };
  }, [pipelineId]);
  
  // Subscribe to events
  useEffect(() => {
    if (!client) return;
    
    const unsubJoin = client.on('participant:join', (p) => {
      setParticipants(prev => [...prev, p]);
    });
    
    const unsubLeave = client.on('participant:leave', (userId) => {
      setParticipants(prev => prev.filter(p => p.userId !== userId));
    });
    
    const unsubUpdate = client.on('participant:update', ({ userId, update }) => {
      setParticipants(prev => prev.map(p =>
        p.userId === userId ? { ...p, ...update } : p
      ));
    });
    
    return () => {
      unsubJoin();
      unsubLeave();
      unsubUpdate();
    };
  }, [client]);
  
  const value: CollaborationContextValue = {
    client,
    participants,
    isConnected,
    connectionError,
    currentUser: participants.find(p => p.userId === currentUserId) ?? null
  };
  
  return (
    <CollaborationContext.Provider value={value}>
      {children}
    </CollaborationContext.Provider>
  );
};

function useCollaboration(): CollaborationContextValue {
  const context = useContext(CollaborationContext);
  if (!context) {
    throw new Error('useCollaboration must be used within CollaborationProvider');
  }
  return context;
}
```

### ParticipantAvatars

```typescript
interface ParticipantAvatarsProps {
  readonly maxVisible?: number;
}

const ParticipantAvatars: React.FC<ParticipantAvatarsProps> = ({
  maxVisible = 5
}) => {
  const { participants, currentUser } = useCollaboration();
  
  // Filter out current user, show others
  const others = participants.filter(p => p.userId !== currentUser?.userId);
  const visible = others.slice(0, maxVisible);
  const overflow = others.length - maxVisible;
  
  return (
    <div className="flex items-center -space-x-2">
      {visible.map((participant) => (
        <TooltipProvider key={participant.userId}>
          <Tooltip>
            <TooltipTrigger asChild>
              <div
                className="relative"
                style={{
                  '--participant-color': participant.color.primary
                } as React.CSSProperties}
              >
                <Avatar
                  className="h-8 w-8 border-2"
                  style={{ borderColor: participant.color.primary }}
                >
                  <AvatarImage src={participant.avatar ?? undefined} />
                  <AvatarFallback
                    style={{ backgroundColor: participant.color.background }}
                  >
                    {participant.name.charAt(0).toUpperCase()}
                  </AvatarFallback>
                </Avatar>
                {/* Status indicator */}
                <span
                  className={cn(
                    "absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-background",
                    participant.status === 'ACTIVE' && "bg-success",
                    participant.status === 'IDLE' && "bg-warning",
                    participant.status === 'DISCONNECTED' && "bg-muted"
                  )}
                />
              </div>
            </TooltipTrigger>
            <TooltipContent>
              <p>{participant.name}</p>
              <p className="text-xs text-muted-foreground">
                {participant.status === 'ACTIVE' ? 'Active now' :
                 participant.status === 'IDLE' ? 'Idle' : 'Disconnected'}
              </p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>
      ))}
      
      {overflow > 0 && (
        <div className="flex items-center justify-center h-8 w-8 rounded-full bg-muted border-2 border-background text-xs font-medium">
          +{overflow}
        </div>
      )}
    </div>
  );
};
```

### CollaborativeCursors

```typescript
interface CollaborativeCursorsProps {
  readonly containerRef: React.RefObject<HTMLDivElement>;
}

const CollaborativeCursors: React.FC<CollaborativeCursorsProps> = ({
  containerRef
}) => {
  const { participants, currentUser } = useCollaboration();
  
  // Filter to participants with visible cursors
  const cursors = participants.filter(p =>
    p.userId !== currentUser?.userId &&
    p.cursor?.isOnCanvas &&
    p.status === 'ACTIVE'
  );
  
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden">
      {cursors.map((participant) => (
        <CollaboratorCursor
          key={participant.userId}
          participant={participant}
        />
      ))}
    </div>
  );
};

interface CollaboratorCursorProps {
  readonly participant: Participant;
}

const CollaboratorCursor: React.FC<CollaboratorCursorProps> = ({
  participant
}) => {
  const { cursor, color, name } = participant;
  
  if (!cursor) return null;
  
  return (
    <motion.div
      className="absolute"
      initial={{ opacity: 0, scale: 0.8 }}
      animate={{
        opacity: 1,
        scale: 1,
        x: cursor.x,
        y: cursor.y
      }}
      exit={{ opacity: 0, scale: 0.8 }}
      transition={{ type: 'spring', damping: 30, stiffness: 500 }}
    >
      {/* Cursor pointer */}
      <svg
        className="h-5 w-5"
        viewBox="0 0 24 24"
        fill={color.primary}
        style={{ transform: 'rotate(-10deg)' }}
      >
        <path d="M5.65 3.15l14 14-5.65 0.7-3.35 5.15-5-20z" />
      </svg>
      
      {/* Name label */}
      <div
        className="absolute left-4 top-4 px-2 py-0.5 rounded text-xs font-medium text-white whitespace-nowrap"
        style={{ backgroundColor: color.primary }}
      >
        {name}
      </div>
    </motion.div>
  );
};
```

### SelectionOverlay

```typescript
interface SelectionOverlayProps {
  readonly nodeId: string;
}

const SelectionOverlay: React.FC<SelectionOverlayProps> = ({ nodeId }) => {
  const { participants, currentUser } = useCollaboration();
  
  // Find participants who have this node selected (excluding current user)
  const selectors = participants.filter(p =>
    p.userId !== currentUser?.userId &&
    p.selectedNodes.includes(nodeId)
  );
  
  if (selectors.length === 0) return null;
  
  // Use first selector's color (or blend if multiple)
  const primarySelector = selectors[0];
  
  return (
    <div
      className="absolute inset-0 rounded-lg pointer-events-none"
      style={{
        boxShadow: `0 0 0 2px ${primarySelector.color.primary}`,
        backgroundColor: primarySelector.color.background
      }}
    >
      {/* Selector indicator */}
      <div
        className="absolute -top-5 left-0 flex items-center gap-1 px-1.5 py-0.5 rounded text-xs text-white"
        style={{ backgroundColor: primarySelector.color.primary }}
      >
        {selectors.length === 1 ? (
          primarySelector.name
        ) : (
          `${primarySelector.name} +${selectors.length - 1}`
        )}
      </div>
    </div>
  );
};
```

### ConflictDialog

```typescript
interface ConflictDialogProps {
  readonly conflict: OperationConflict;
  readonly open: boolean;
  readonly onResolve: (resolution: ConflictResolution) => void;
}

const ConflictDialog: React.FC<ConflictDialogProps> = ({
  conflict,
  open,
  onResolve
}) => {
  return (
    <AlertDialog open={open}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5 text-warning" />
            Edit Conflict Detected
          </AlertDialogTitle>
          <AlertDialogDescription>
            Another user made a conflicting change. Choose how to resolve:
          </AlertDialogDescription>
        </AlertDialogHeader>
        
        <div className="grid grid-cols-2 gap-4 py-4">
          {/* Local version */}
          <div className="p-3 rounded-lg border">
            <div className="flex items-center gap-2 mb-2">
              <User className="h-4 w-4" />
              <span className="text-sm font-medium">Your Change</span>
            </div>
            <ConflictPreview operation={conflict.localOp} />
          </div>
          
          {/* Remote version */}
          <div className="p-3 rounded-lg border">
            <div className="flex items-center gap-2 mb-2">
              <Users className="h-4 w-4" />
              <span className="text-sm font-medium">Their Change</span>
            </div>
            <ConflictPreview operation={conflict.remoteOp} />
          </div>
        </div>
        
        <AlertDialogFooter className="flex-col sm:flex-row gap-2">
          <Button
            variant="outline"
            onClick={() => onResolve({ strategy: ResolutionStrategy.KEEP_LOCAL })}
          >
            Keep Mine
          </Button>
          <Button
            variant="outline"
            onClick={() => onResolve({ strategy: ResolutionStrategy.KEEP_REMOTE })}
          >
            Keep Theirs
          </Button>
          {conflict.suggestedResolution === ResolutionStrategy.MERGE && (
            <Button
              onClick={() => onResolve({ strategy: ResolutionStrategy.MERGE })}
            >
              Merge Both
            </Button>
          )}
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
};
```

---

## Hooks

### useCursorTracking

```typescript
function useCursorTracking(canvasRef: React.RefObject<HTMLDivElement>): void {
  const { client, isConnected } = useCollaboration();
  
  useEffect(() => {
    if (!client || !isConnected || !canvasRef.current) return;
    
    const canvas = canvasRef.current;
    
    const handleMouseMove = throttle((e: MouseEvent) => {
      const rect = canvas.getBoundingClientRect();
      client.updateCursor({
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
        isOnCanvas: true
      });
    }, 50);
    
    const handleMouseLeave = () => {
      client.updateCursor({
        x: 0,
        y: 0,
        isOnCanvas: false
      });
    };
    
    canvas.addEventListener('mousemove', handleMouseMove);
    canvas.addEventListener('mouseleave', handleMouseLeave);
    
    return () => {
      canvas.removeEventListener('mousemove', handleMouseMove);
      canvas.removeEventListener('mouseleave', handleMouseLeave);
    };
  }, [client, isConnected, canvasRef]);
}
```

### useCollaborativeOperation

```typescript
interface UseCollaborativeOperationResult {
  readonly apply: (operation: Omit<ClientOperation, 'clientId' | 'clientSeq'>) => Promise<void>;
  readonly isPending: boolean;
  readonly lastConflict: OperationConflict | null;
}

function useCollaborativeOperation(): UseCollaborativeOperationResult {
  const { client } = useCollaboration();
  const [isPending, setIsPending] = useState(false);
  const [lastConflict, setLastConflict] = useState<OperationConflict | null>(null);
  const clientSeqRef = useRef(0);
  
  const apply = useCallback(async (
    operation: Omit<ClientOperation, 'clientId' | 'clientSeq'>
  ) => {
    if (!client) return;
    
    setIsPending(true);
    const seq = ++clientSeqRef.current;
    
    try {
      const result = await client.applyOperation({
        ...operation,
        clientId: client.getClientId(),
        clientSeq: seq
      });
      
      if (result.conflict) {
        setLastConflict(result.conflict);
      }
    } finally {
      setIsPending(false);
    }
  }, [client]);
  
  return { apply, isPending, lastConflict };
}
```

---

## API Endpoints

```typescript
// WebSocket
WS     /ws/collaborate/:pipelineId       // Real-time collaboration

// REST (fallback/admin)
GET    /api/pipelines/:id/session        // Get active session
POST   /api/pipelines/:id/session        // Create/join session
DELETE /api/pipelines/:id/session        // End session (admin only)

GET    /api/sessions/:id/participants    // List participants
GET    /api/sessions/:id/operations      // Get operation history

// Presence (polling fallback)
POST   /api/sessions/:id/presence        // Update presence
GET    /api/sessions/:id/cursors         // Get all cursors
```

---

## See Also

- [Permissions](./22-permissions.md) — Access control for sessions
- [Sharing](./23-sharing.md) — Invite collaborators
- [Version Control](./21-version-control.md) — Collaborative branching
- [React Flow Canvas](./10-react-flow-canvas.md) — Canvas integration
