# WebSocket Events

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Overview

Real-time WebSocket events for streaming code generation progress, AI token output, build status, and error notifications.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Parallel Executor](./05-parallel-executor.md)
- [Build Verification](./06-build-verification.md)
- [Realtime Communication](../18-realtime/00-overview.md)

---

## Connection

### Endpoint

```
wss://{host}/ws/codegen
```

### Authentication

```json
{
  "type": "auth",
  "token": "Bearer {jwt_token}",
  "session_id": "sess_abc123"
}
```

### Message Envelope

All messages follow the standard WebSocket protocol envelope:

```json
{
  "type": "string",
  "data": "object",
  "requestId": "string",
  "timestamp": "ISO8601"
}
```

---

## Event Categories

| Category | Prefix | Description |
|----------|--------|-------------|
| Session | `session:` | Generation session lifecycle |
| Phase | `phase:` | Workflow phase transitions |
| File | `file:` | Individual file generation |
| AI | `ai:` | Token streaming from LLM |
| Build | `build:` | Verification and fix loop |
| Git | `git:` | Repository operations |
| Credit | `credit:` | Usage consumption |
| Error | `error:` | Error notifications |

---

## Session Events

### session:started

Emitted when a code generation session begins.

```json
{
  "type": "session:started",
  "data": {
    "session_id": "sess_abc123",
    "project_id": "proj_xyz",
    "plan_id": "plan_456",
    "total_files": 24,
    "total_batches": 5,
    "estimated_credits": 150
  },
  "timestamp": "2026-01-29T10:00:00Z"
}
```

### session:paused

```json
{
  "type": "session:paused",
  "data": {
    "session_id": "sess_abc123",
    "reason": "user_request",
    "files_completed": 12,
    "files_remaining": 12
  },
  "timestamp": "2026-01-29T10:05:00Z"
}
```

### session:resumed

```json
{
  "type": "session:resumed",
  "data": {
    "session_id": "sess_abc123",
    "resuming_from_batch": 3
  },
  "timestamp": "2026-01-29T10:10:00Z"
}
```

### session:completed

```json
{
  "type": "session:completed",
  "data": {
    "session_id": "sess_abc123",
    "status": "success",
    "files_generated": 24,
    "total_tokens": 45000,
    "credits_consumed": 142,
    "duration_seconds": 180
  },
  "timestamp": "2026-01-29T10:03:00Z"
}
```

### session:failed

```json
{
  "type": "session:failed",
  "data": {
    "session_id": "sess_abc123",
    "error_code": 12301,
    "error_message": "Maximum retry attempts exceeded",
    "failed_at_file": "src/services/auth.go",
    "files_completed": 18
  },
  "timestamp": "2026-01-29T10:02:30Z"
}
```

---

## Phase Events

### phase:started

```json
{
  "type": "phase:started",
  "data": {
    "session_id": "sess_abc123",
    "phase": "writing",
    "phase_number": 1,
    "total_phases": 3,
    "description": "Code Writing Phase"
  },
  "timestamp": "2026-01-29T10:00:05Z"
}
```

**Phase Values:**
- `writing` - Phase 1: Parallel code generation
- `consistency` - Phase 2: Cross-file validation
- `build` - Phase 3: Build verification

### phase:progress

```json
{
  "type": "phase:progress",
  "data": {
    "session_id": "sess_abc123",
    "phase": "writing",
    "current_batch": 2,
    "total_batches": 5,
    "files_in_batch": 4,
    "batch_progress_percent": 75
  },
  "timestamp": "2026-01-29T10:01:00Z"
}
```

### phase:completed

```json
{
  "type": "phase:completed",
  "data": {
    "session_id": "sess_abc123",
    "phase": "writing",
    "duration_seconds": 120,
    "next_phase": "consistency"
  },
  "timestamp": "2026-01-29T10:02:00Z"
}
```

---

## File Events

### file:queued

```json
{
  "type": "file:queued",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "file_path": "src/models/user.go",
    "batch_number": 1,
    "depends_on": []
  },
  "timestamp": "2026-01-29T10:00:10Z"
}
```

### file:started

```json
{
  "type": "file:started",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "file_path": "src/models/user.go",
    "spec_reference": "05-data-models.md#user",
    "worker_id": "worker_03"
  },
  "timestamp": "2026-01-29T10:00:15Z"
}
```

### file:progress

```json
{
  "type": "file:progress",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "file_path": "src/models/user.go",
    "tokens_generated": 450,
    "estimated_total_tokens": 800,
    "progress_percent": 56
  },
  "timestamp": "2026-01-29T10:00:30Z"
}
```

### file:completed

```json
{
  "type": "file:completed",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "file_path": "src/models/user.go",
    "tokens_used": 823,
    "lines_of_code": 142,
    "duration_seconds": 25
  },
  "timestamp": "2026-01-29T10:00:40Z"
}
```

### file:failed

```json
{
  "type": "file:failed",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "file_path": "src/models/user.go",
    "error_code": 12302,
    "error_message": "AI generation timeout",
    "retry_count": 2,
    "will_retry": true
  },
  "timestamp": "2026-01-29T10:00:45Z"
}
```

---

## AI Token Events

### ai:token

Real-time token streaming from the LLM.

```json
{
  "type": "ai:token",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "delta": "func NewUser",
    "token_index": 45
  },
  "timestamp": "2026-01-29T10:00:20.123Z"
}
```

### ai:thinking

Emitted during model reasoning phases (if supported).

```json
{
  "type": "ai:thinking",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "status": "analyzing_dependencies"
  },
  "timestamp": "2026-01-29T10:00:18Z"
}
```

### ai:stream_complete

```json
{
  "type": "ai:stream_complete",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "total_tokens": 823,
    "input_tokens": 1200,
    "output_tokens": 823
  },
  "timestamp": "2026-01-29T10:00:40Z"
}
```

---

## Build Events

### build:started

```json
{
  "type": "build:started",
  "data": {
    "session_id": "sess_abc123",
    "build_id": "build_789",
    "command": "brun build",
    "attempt": 1,
    "max_attempts": 3
  },
  "timestamp": "2026-01-29T10:02:05Z"
}
```

### build:output

Streams build CLI output in real-time.

```json
{
  "type": "build:output",
  "data": {
    "session_id": "sess_abc123",
    "build_id": "build_789",
    "stream": "stdout",
    "line": "Compiling src/models/user.go...",
    "line_number": 15
  },
  "timestamp": "2026-01-29T10:02:10Z"
}
```

### build:error_detected

```json
{
  "type": "build:error_detected",
  "data": {
    "session_id": "sess_abc123",
    "build_id": "build_789",
    "error_type": "compilation",
    "file_path": "src/services/auth.go",
    "line": 45,
    "column": 12,
    "message": "undefined: jwt.ParseToken",
    "severity": "error"
  },
  "timestamp": "2026-01-29T10:02:15Z"
}
```

### build:fix_started

Emitted when AI auto-fix loop begins.

```json
{
  "type": "build:fix_started",
  "data": {
    "session_id": "sess_abc123",
    "build_id": "build_789",
    "fix_tier": 1,
    "errors_to_fix": 3,
    "files_affected": ["src/services/auth.go", "src/utils/token.go"]
  },
  "timestamp": "2026-01-29T10:02:20Z"
}
```

**Fix Tiers:**
- Tier 1: Syntax/import fixes
- Tier 2: Logic/type corrections
- Tier 3: Structural refactoring

### build:fix_applied

```json
{
  "type": "build:fix_applied",
  "data": {
    "session_id": "sess_abc123",
    "build_id": "build_789",
    "file_path": "src/services/auth.go",
    "fix_description": "Added missing import for jwt package",
    "lines_changed": 2
  },
  "timestamp": "2026-01-29T10:02:25Z"
}
```

### build:completed

```json
{
  "type": "build:completed",
  "data": {
    "session_id": "sess_abc123",
    "build_id": "build_789",
    "status": "success",
    "attempts_used": 2,
    "fixes_applied": 3,
    "duration_seconds": 45
  },
  "timestamp": "2026-01-29T10:02:50Z"
}
```

### build:failed

```json
{
  "type": "build:failed",
  "data": {
    "session_id": "sess_abc123",
    "build_id": "build_789",
    "status": "failed",
    "attempts_used": 3,
    "remaining_errors": 2,
    "error_code": 12501,
    "requires_manual_intervention": true
  },
  "timestamp": "2026-01-29T10:03:30Z"
}
```

---

## Consistency Events

### consistency:started

```json
{
  "type": "consistency:started",
  "data": {
    "session_id": "sess_abc123",
    "files_to_check": 24,
    "check_types": ["imports", "interfaces", "naming", "types"]
  },
  "timestamp": "2026-01-29T10:01:45Z"
}
```

### consistency:issue_found

```json
{
  "type": "consistency:issue_found",
  "data": {
    "session_id": "sess_abc123",
    "issue_id": "issue_001",
    "issue_type": "interface_mismatch",
    "severity": "error",
    "file_a": "src/services/user.go",
    "file_b": "src/handlers/user.go",
    "description": "Method signature mismatch: GetUser expects (string) but called with (int)",
    "auto_fixable": true
  },
  "timestamp": "2026-01-29T10:01:50Z"
}
```

### consistency:fix_applied

```json
{
  "type": "consistency:fix_applied",
  "data": {
    "session_id": "sess_abc123",
    "issue_id": "issue_001",
    "files_modified": ["src/handlers/user.go"],
    "fix_description": "Updated GetUser call to pass string ID"
  },
  "timestamp": "2026-01-29T10:01:55Z"
}
```

### consistency:completed

```json
{
  "type": "consistency:completed",
  "data": {
    "session_id": "sess_abc123",
    "issues_found": 5,
    "issues_fixed": 4,
    "issues_manual": 1,
    "duration_seconds": 15
  },
  "timestamp": "2026-01-29T10:02:00Z"
}
```

---

## Git Events

### git:commit_started

```json
{
  "type": "git:commit_started",
  "data": {
    "session_id": "sess_abc123",
    "files_to_commit": 24,
    "commit_message": "feat(codegen): Generate user management module\n\nSpec: 05-data-models.md, 06-user-service.md"
  },
  "timestamp": "2026-01-29T10:03:05Z"
}
```

### git:commit_completed

```json
{
  "type": "git:commit_completed",
  "data": {
    "session_id": "sess_abc123",
    "commit_hash": "a1b2c3d4",
    "branch": "feature/user-module",
    "files_committed": 24
  },
  "timestamp": "2026-01-29T10:03:10Z"
}
```

### git:push_started

```json
{
  "type": "git:push_started",
  "data": {
    "session_id": "sess_abc123",
    "remote": "origin",
    "branch": "feature/user-module",
    "provider": "github"
  },
  "timestamp": "2026-01-29T10:03:12Z"
}
```

### git:push_completed

```json
{
  "type": "git:push_completed",
  "data": {
    "session_id": "sess_abc123",
    "remote_url": "https://github.com/org/repo",
    "branch": "feature/user-module",
    "commits_pushed": 1
  },
  "timestamp": "2026-01-29T10:03:18Z"
}
```

### git:push_failed

```json
{
  "type": "git:push_failed",
  "data": {
    "session_id": "sess_abc123",
    "error_code": 12401,
    "error_message": "Authentication failed: token expired",
    "requires_reauth": true
  },
  "timestamp": "2026-01-29T10:03:15Z"
}
```

---

## Credit Events

### credit:consumed

```json
{
  "type": "credit:consumed",
  "data": {
    "session_id": "sess_abc123",
    "file_id": "file_001",
    "credits_used": 6,
    "input_tokens": 1200,
    "output_tokens": 823,
    "running_total": 48
  },
  "timestamp": "2026-01-29T10:00:40Z"
}
```

### credit:warning

```json
{
  "type": "credit:warning",
  "data": {
    "session_id": "sess_abc123",
    "warning_type": "low_balance",
    "current_balance": 25,
    "estimated_remaining_cost": 50,
    "message": "Credit balance may be insufficient to complete generation"
  },
  "timestamp": "2026-01-29T10:01:30Z"
}
```

### credit:exhausted

```json
{
  "type": "credit:exhausted",
  "data": {
    "session_id": "sess_abc123",
    "final_balance": 0,
    "files_completed": 18,
    "files_remaining": 6,
    "session_paused": true
  },
  "timestamp": "2026-01-29T10:01:45Z"
}
```

---

## Error Events

### error:recoverable

```json
{
  "type": "error:recoverable",
  "data": {
    "session_id": "sess_abc123",
    "error_code": 12303,
    "error_message": "Rate limit exceeded",
    "retry_after_seconds": 30,
    "auto_retry": true
  },
  "timestamp": "2026-01-29T10:01:00Z"
}
```

### error:fatal

```json
{
  "type": "error:fatal",
  "data": {
    "session_id": "sess_abc123",
    "error_code": 12304,
    "error_message": "AI provider unavailable",
    "session_terminated": true,
    "recoverable_state": true,
    "resume_available": true
  },
  "timestamp": "2026-01-29T10:01:30Z"
}
```

---

## Client Events

Events sent from client to server.

### client:subscribe

```json
{
  "type": "client:subscribe",
  "data": {
    "session_id": "sess_abc123",
    "event_filters": ["file:*", "build:*", "error:*"]
  },
  "requestId": "req_001"
}
```

### client:unsubscribe

```json
{
  "type": "client:unsubscribe",
  "data": {
    "session_id": "sess_abc123"
  },
  "requestId": "req_002"
}
```

### client:ping

```json
{
  "type": "client:ping",
  "data": {},
  "requestId": "req_003"
}
```

**Server Response:**

```json
{
  "type": "server:pong",
  "data": {
    "server_time": "2026-01-29T10:00:00Z"
  },
  "requestId": "req_003"
}
```

---

## Error Codes (WebSocket-Specific)

| Code | Constant | Description |
|------|----------|-------------|
| 12700 | `ERR_WS_CONNECTION_FAILED` | WebSocket connection failed |
| 12701 | `ERR_WS_AUTH_FAILED` | Authentication rejected |
| 12702 | `ERR_WS_SESSION_NOT_FOUND` | Session ID not found |
| 12703 | `ERR_WS_INVALID_MESSAGE` | Malformed message format |
| 12704 | `ERR_WS_SUBSCRIPTION_FAILED` | Event subscription failed |
| 12705 | `ERR_WS_RATE_LIMITED` | Too many messages |
| 12706 | `ERR_WS_SESSION_EXPIRED` | Session timed out |

---

## Connection Management

### Heartbeat

- Client sends `client:ping` every 30 seconds
- Server responds with `server:pong`
- Connection closed after 90 seconds of inactivity

### Reconnection

```json
{
  "type": "client:reconnect",
  "data": {
    "session_id": "sess_abc123",
    "last_event_id": "evt_12345",
    "reconnect_token": "recon_abc"
  }
}
```

**Server Response:**

```json
{
  "type": "server:reconnect_ack",
  "data": {
    "session_id": "sess_abc123",
    "missed_events": 5,
    "replay_from": "evt_12340"
  }
}
```

### Event Replay

Missed events are replayed in order after reconnection:

```json
{
  "type": "server:replay",
  "data": {
    "events": [
      { "type": "file:completed", "data": {...}, "event_id": "evt_12341" },
      { "type": "file:started", "data": {...}, "event_id": "evt_12342" }
    ],
    "replay_complete": true
  }
}
```

---

## Rate Limits

| Event Type | Max Frequency |
|------------|---------------|
| `ai:token` | 100/second per session |
| `build:output` | 50/second per build |
| `file:progress` | 5/second per file |
| `client:ping` | 1/30 seconds |

---

## Frontend Integration

### TypeScript Types

```typescript
interface WSMessage<T = unknown> {
  type: string;
  data: T;
  requestId?: string;
  timestamp: string;
}

interface SessionStartedData {
  session_id: string;
  project_id: string;
  plan_id: string;
  total_files: number;
  total_batches: number;
  estimated_credits: number;
}

interface FileProgressData {
  session_id: string;
  file_id: string;
  file_path: string;
  tokens_generated: number;
  estimated_total_tokens: number;
  progress_percent: number;
}

interface AITokenData {
  session_id: string;
  file_id: string;
  delta: string;
  token_index: number;
}

// Event handler types
type WSEventHandler<T> = (data: T, message: WSMessage<T>) => void;
```

### React Hook Example

```typescript
const useCodeGenStream = (sessionId: string) => {
  const [status, setStatus] = useState<SessionStatus>('idle');
  const [files, setFiles] = useState<FileProgress[]>([]);
  const [tokens, setTokens] = useState<Map<string, string>>(new Map());

  useEffect(() => {
    const ws = new WebSocket(`wss://${host}/ws/codegen`);
    
    ws.onopen = () => {
      ws.send(JSON.stringify({
        type: 'client:subscribe',
        data: { session_id: sessionId }
      }));
    };

    ws.onmessage = (event) => {
      const msg: WSMessage = JSON.parse(event.data);
      
      switch (msg.type) {
        case 'session:started':
          setStatus('running');
          break;
        case 'file:progress':
          updateFileProgress(msg.data);
          break;
        case 'ai:token':
          appendToken(msg.data.file_id, msg.data.delta);
          break;
        // ... handle other events
      }
    };

    return () => ws.close();
  }, [sessionId]);

  return { status, files, tokens };
};
```

---

## Related Specs

- [API Endpoints](./10-api-endpoints.md)
- [Parallel Executor](./04-parallel-executor.md)
- [Realtime Overview](../18-realtime/00-overview.md)
- [WebSocket Integration](../18-realtime/01-websocket-integration.md)

---

## Source Reference

New specification for Code Generation System WebSocket events.
