# Sync API

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Offline-First Storage](./01-offline-first-storage.md)

---

## Overview

Backend API endpoints for handling sync operations from the frontend queue. Includes batch sync endpoint for efficiency and individual entity endpoints for granular control.

---

## API Endpoints

### POST /api/v1/sync/batch

Batch sync multiple operations in a single request for efficiency.

#### Request

```typescript
interface BatchSyncRequest {
  operations: SyncOperation[];
}

interface SyncOperation {
  id: string;                     // Client-generated operation ID
  operation: 'create' | 'update' | 'delete';
  entityType: string;             // message, audio, file, plan, memory, settings
  entityId: string;               // Entity identifier
  payload?: unknown;              // Data (not required for delete)
  clientTimestamp: number;        // When client made the change
}
```

#### Response

```typescript
interface BatchSyncResponse {
  results: SyncOperationResult[];
  serverTimestamp: number;
}

interface SyncOperationResult {
  id: string;                     // Matches request operation ID
  success: boolean;
  entityId?: string;              // Server-assigned ID for creates
  error?: string;
  errorCode?: string;
  retryable?: boolean;
}
```

#### Example

```json
// Request
POST /api/v1/sync/batch
{
  "operations": [
    {
      "id": "op_abc123",
      "operation": "create",
      "entityType": "message",
      "entityId": "msg_temp_1",
      "payload": {
        "sessionId": "sess_xyz",
        "content": "Hello world",
        "role": "user"
      },
      "clientTimestamp": 1706540400000
    },
    {
      "id": "op_def456",
      "operation": "update",
      "entityType": "settings",
      "entityId": "user_prefs",
      "payload": {
        "theme": "dark"
      },
      "clientTimestamp": 1706540401000
    }
  ]
}

// Response
{
  "results": [
    {
      "id": "op_abc123",
      "success": true,
      "entityId": "msg_server_789"
    },
    {
      "id": "op_def456",
      "success": true
    }
  ],
  "serverTimestamp": 1706540402000
}
```

---

## Go Backend Implementation

### Handler

```go
// internal/api/handlers/sync.go

package handlers

import (
	"encoding/json"
	"net/http"
	"time"
	
	"specmgmt/internal/sync"
)

type SyncHandler struct {
	service *sync.Service
}

func NewSyncHandler(s *sync.Service) *SyncHandler {
	return &SyncHandler{service: s}
}

type BatchSyncRequest struct {
	Operations []SyncOperation `json:"operations"`
}

type SyncOperation struct {
	ID              string      `json:"id"`
	Operation       string      `json:"operation"`
	EntityType      string      `json:"entityType"`
	EntityID        string      `json:"entityId"`
	Payload         interface{} `json:"payload,omitempty"`
	ClientTimestamp int64       `json:"clientTimestamp"`
}

type BatchSyncResponse struct {
	Results         []SyncResult `json:"results"`
	ServerTimestamp int64        `json:"serverTimestamp"`
}

type SyncResult struct {
	ID        string  `json:"id"`
	Success   bool    `json:"success"`
	EntityID  *string `json:"entityId,omitempty"`
	Error     *string `json:"error,omitempty"`
	ErrorCode *string `json:"errorCode,omitempty"`
	Retryable *bool   `json:"retryable,omitempty"`
}

func (h *SyncHandler) BatchSync(w http.ResponseWriter, r *http.Request) {
	var req BatchSyncRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request body", http.StatusBadRequest)
		return
	}
	
	// Get user from context
	userID := r.Context().Value("user_id").(string)
	
	results := make([]SyncResult, len(req.Operations))
	
	for i, op := range req.Operations {
		result := h.processOperation(r.Context(), userID, op)
		results[i] = result
	}
	
	response := BatchSyncResponse{
		Results:         results,
		ServerTimestamp: time.Now().UnixMilli(),
	}
	
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func (h *SyncHandler) processOperation(ctx context.Context, userID string, op SyncOperation) SyncResult {
	var result SyncResult
	result.ID = op.ID
	
	switch op.EntityType {
	case "message":
		return h.processMessage(ctx, userID, op)
	case "audio":
		return h.processAudio(ctx, userID, op)
	case "file":
		return h.processFile(ctx, userID, op)
	case "plan":
		return h.processPlan(ctx, userID, op)
	case "memory":
		return h.processMemory(ctx, userID, op)
	case "settings":
		return h.processSettings(ctx, userID, op)
	default:
		errMsg := "Unknown entity type"
		errCode := "UNKNOWN_ENTITY"
		retryable := false
		return SyncResult{
			ID:        op.ID,
			Success:   false,
			Error:     &errMsg,
			ErrorCode: &errCode,
			Retryable: &retryable,
		}
	}
}

func (h *SyncHandler) processMessage(ctx context.Context, userID string, op SyncOperation) SyncResult {
	switch op.Operation {
	case "create":
		payload, ok := op.Payload.(map[string]interface{})
		if !ok {
			errMsg := "Invalid payload"
			return SyncResult{ID: op.ID, Success: false, Error: &errMsg}
		}
		
		msg, err := h.service.CreateMessage(ctx, userID, payload)
		if err != nil {
			errMsg := err.Error()
			retryable := isRetryable(err)
			return SyncResult{ID: op.ID, Success: false, Error: &errMsg, Retryable: &retryable}
		}
		
		return SyncResult{ID: op.ID, Success: true, EntityID: &msg.ID}
		
	case "update":
		payload, ok := op.Payload.(map[string]interface{})
		if !ok {
			errMsg := "Invalid payload"
			return SyncResult{ID: op.ID, Success: false, Error: &errMsg}
		}
		
		err := h.service.UpdateMessage(ctx, userID, op.EntityID, payload)
		if err != nil {
			errMsg := err.Error()
			retryable := isRetryable(err)
			return SyncResult{ID: op.ID, Success: false, Error: &errMsg, Retryable: &retryable}
		}
		
		return SyncResult{ID: op.ID, Success: true}
		
	case "delete":
		err := h.service.DeleteMessage(ctx, userID, op.EntityID)
		if err != nil {
			errMsg := err.Error()
			retryable := isRetryable(err)
			return SyncResult{ID: op.ID, Success: false, Error: &errMsg, Retryable: &retryable}
		}
		
		return SyncResult{ID: op.ID, Success: true}
	}
	
	errMsg := "Unknown operation"
	return SyncResult{ID: op.ID, Success: false, Error: &errMsg}
}

// Similar implementations for processAudio, processFile, etc.
```

### Service Layer

```go
// internal/sync/service.go

package sync

import (
	"context"
	"errors"
	
	"specmgmt/internal/db"
)

var (
	ErrNotFound     = errors.New("entity not found")
	ErrUnauthorized = errors.New("unauthorized")
	ErrConflict     = errors.New("conflict detected")
)

type Service struct {
	db *db.DB
}

func NewService(db *db.DB) *Service {
	return &Service{db: db}
}

type Message struct {
	ID        string `json:"id"`
	SessionID string `json:"sessionId"`
	Content   string `json:"content"`
	Role      string `json:"role"`
	CreatedAt int64  `json:"createdAt"`
}

func (s *Service) CreateMessage(ctx context.Context, userID string, payload map[string]interface{}) (*Message, error) {
	// Validate session belongs to user
	sessionID, _ := payload["sessionId"].(string)
	if !s.userOwnsSession(ctx, userID, sessionID) {
		return nil, ErrUnauthorized
	}
	
	msg := &Message{
		ID:        generateID(),
		SessionID: sessionID,
		Content:   payload["content"].(string),
		Role:      payload["role"].(string),
		CreatedAt: time.Now().UnixMilli(),
	}
	
	_, err := s.db.ExecContext(ctx, `
		INSERT INTO messages (id, session_id, content, role, created_at)
		VALUES (?, ?, ?, ?, ?)
	`, msg.ID, msg.SessionID, msg.Content, msg.Role, msg.CreatedAt)
	
	if err != nil {
		return nil, err
	}
	
	return msg, nil
}

func (s *Service) UpdateMessage(ctx context.Context, userID, msgID string, payload map[string]interface{}) error {
	// Validate ownership
	if !s.userOwnsMessage(ctx, userID, msgID) {
		return ErrUnauthorized
	}
	
	content, _ := payload["content"].(string)
	
	result, err := s.db.ExecContext(ctx, `
		UPDATE messages SET content = ?, updated_at = ? WHERE id = ?
	`, content, time.Now().UnixMilli(), msgID)
	
	if err != nil {
		return err
	}
	
	rows, _ := result.RowsAffected()
	if rows == 0 {
		return ErrNotFound
	}
	
	return nil
}

func (s *Service) DeleteMessage(ctx context.Context, userID, msgID string) error {
	// Validate ownership
	if !s.userOwnsMessage(ctx, userID, msgID) {
		return ErrUnauthorized
	}
	
	result, err := s.db.ExecContext(ctx, `
		DELETE FROM messages WHERE id = ?
	`, msgID)
	
	if err != nil {
		return err
	}
	
	rows, _ := result.RowsAffected()
	if rows == 0 {
		return ErrNotFound
	}
	
	return nil
}

func isRetryable(err error) bool {
	// Network errors, timeouts, and 5xx are retryable
	// Authorization and validation errors are not
	return !errors.Is(err, ErrUnauthorized) && !errors.Is(err, ErrNotFound)
}
```

---

## Conflict Resolution

### Last-Write-Wins (Default)

```go
func (s *Service) UpdateWithConflictCheck(
	ctx context.Context,
	entityID string,
	payload map[string]interface{},
	clientTimestamp int64,
) error {
	// Get current server timestamp
	var serverTimestamp int64
	err := s.db.QueryRowContext(ctx, `
		SELECT updated_at FROM entities WHERE id = ?
	`, entityID).Scan(&serverTimestamp)
	
	if err != nil {
		return err
	}
	
	// If server version is newer, reject update (or merge)
	if serverTimestamp > clientTimestamp {
		// Option 1: Reject
		return ErrConflict
		
		// Option 2: Last-write-wins (client wins)
		// Continue with update
		
		// Option 3: Store conflict for user resolution
		// return s.storeConflict(ctx, entityID, payload, clientTimestamp)
	}
	
	// Apply update
	return s.applyUpdate(ctx, entityID, payload)
}
```

### Conflict Storage (For User Resolution)

```sql
CREATE TABLE sync_conflicts (
  id TEXT PRIMARY KEY,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  client_version TEXT NOT NULL,   -- JSON
  server_version TEXT NOT NULL,   -- JSON
  client_timestamp INTEGER NOT NULL,
  server_timestamp INTEGER NOT NULL,
  resolved_at INTEGER,
  resolution TEXT,                -- 'client', 'server', 'merge'
  created_at INTEGER DEFAULT (strftime('%s', 'now') * 1000)
);
```

---

## Error Codes

| Code | HTTP Status | Retryable | Description |
|------|-------------|-----------|-------------|
| `UNKNOWN_ENTITY` | 400 | No | Unknown entity type |
| `INVALID_PAYLOAD` | 400 | No | Malformed request data |
| `NOT_FOUND` | 404 | No | Entity doesn't exist |
| `UNAUTHORIZED` | 403 | No | User doesn't own entity |
| `CONFLICT` | 409 | No | Version conflict detected |
| `RATE_LIMITED` | 429 | Yes | Too many requests |
| `INTERNAL_ERROR` | 500 | Yes | Server error |
| `SERVICE_UNAVAILABLE` | 503 | Yes | Temporary outage |

---

## Rate Limiting

```go
// Middleware for rate limiting sync requests
func RateLimitMiddleware(next http.Handler) http.Handler {
	limiter := rate.NewLimiter(rate.Every(100*time.Millisecond), 10) // 10 req/sec burst
	
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if !limiter.Allow() {
			w.Header().Set("Retry-After", "1")
			http.Error(w, "Rate limit exceeded", http.StatusTooManyRequests)
			return
		}
		next.ServeHTTP(w, r)
	})
}
```

---

## Database Schema

```sql
-- Sync metadata for tracking last sync state
CREATE TABLE sync_state (
  user_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  last_sync_at INTEGER NOT NULL,
  last_server_timestamp INTEGER NOT NULL,
  PRIMARY KEY (user_id, entity_type)
);

-- Audit log for sync operations
CREATE TABLE sync_audit (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL,
  operation TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  success BOOLEAN NOT NULL,
  error_message TEXT,
  client_timestamp INTEGER NOT NULL,
  server_timestamp INTEGER NOT NULL,
  created_at INTEGER DEFAULT (strftime('%s', 'now') * 1000)
);

CREATE INDEX idx_sync_audit_user ON sync_audit(user_id);
CREATE INDEX idx_sync_audit_entity ON sync_audit(entity_type, entity_id);
```

---

## Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| Batch create | Multiple creates succeed | Critical |
| Batch mixed | Create, update, delete in one request | Critical |
| Partial failure | Some ops succeed, some fail | Critical |
| Authorization | User can only sync own entities | Critical |
| Not found | Update/delete non-existent returns 404 | High |
| Conflict detection | Stale update detected | High |
| Rate limiting | 429 returned when exceeded | Medium |
| Large batch | 100+ operations handled | Medium |

---

## Related Specs

- [Offline-First Storage](./01-offline-first-storage.md)
- [Sync Queue](./01-02-sync-queue.md)
- [API Client](../15-api-client/00-overview.md)
