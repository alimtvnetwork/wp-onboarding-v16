# Memory: features/error-modal/error-history-persistence
Updated: 2026-02-06

## Overview

Error history persistence ensures all captured errors are saved to SQLite database for later retrieval, multi-selection, and bulk export.

## Status: Phase 2 COMPLETE

### 1. SQLite ErrorHistory Table ✅

Migration version 7 creates the table:

```sql
CREATE TABLE IF NOT EXISTS ErrorHistory (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    ErrorId TEXT NOT NULL UNIQUE,
    Code TEXT NOT NULL,
    Level TEXT NOT NULL DEFAULT 'error',
    Message TEXT NOT NULL,
    Details TEXT,
    ContextJson TEXT,
    StackTrace TEXT,
    Endpoint TEXT,
    Method TEXT,
    RequestBodyJson TEXT,
    ResponseStatus INTEGER,
    SessionId TEXT,
    SessionType TEXT,
    PhpStackFramesJson TEXT,
    BackendLogsJson TEXT,
    BackendStackTrace TEXT,
    SiteUrl TEXT,
    TriggerComponent TEXT,
    TriggerAction TEXT,
    InvocationChainJson TEXT,
    UiClickPath TEXT,
    MarkdownReport TEXT,
    CreatedAt TEXT DEFAULT (datetime('now'))
);
```

### 2. Backend Endpoints ✅

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/error-history` | Save error to history |
| GET | `/api/v1/error-history` | List with pagination/filters |
| GET | `/api/v1/error-history/{id}` | Get single error |
| DELETE | `/api/v1/error-history/{id}` | Delete single error |
| DELETE | `/api/v1/error-history` | Clear all history |
| POST | `/api/v1/error-history/bulk-export` | Export multiple as markdown |
| GET | `/api/v1/error-history/stats` | Get error statistics |

### 3. ErrorHistory Service ✅

Located at `backend/internal/services/errorhistory/service.go`:
- `Save(input)` - Persist error with all context
- `List(limit, offset, filters)` - Paginated query with filters
- `GetByID(id)` - Fetch by database ID
- `GetByErrorID(errorID)` - Fetch by frontend-generated ID
- `Delete(id)` - Remove single error
- `Clear()` - Remove all errors
- `BulkExport(ids)` - Generate combined markdown report
- `GetStats()` - Error statistics by level/code

## Related Files

- Model: `backend/internal/models/error_history.go`
- Service: `backend/internal/services/errorhistory/service.go`
- Handlers: `backend/internal/api/handlers/error_history_handlers.go`
- Migration: `backend/internal/database/migrations.go` (version 7)

## Phase 3: Frontend Integration ✅ COMPLETE

### API Client Functions

Added to `src/lib/api.ts`:
- `saveErrorHistory(input)` - POST to persist error
- `listErrorHistory(opts)` - GET with pagination/filters
- `getErrorHistoryById(id)` - GET single error
- `deleteErrorHistory(id)` - DELETE single
- `clearErrorHistory()` - DELETE all
- `bulkExportErrorHistory(ids)` - POST for markdown export
- `getErrorHistoryStats()` - GET statistics

### useErrorHistory Hook

Located at `src/hooks/useErrorHistory.ts`:
- Fetches error history from backend
- Provides CRUD operations via React Query mutations
- `useErrorHistorySync()` - Auto-syncs pending errors to backend

### ErrorStore Updates

Updated `src/stores/errorStore.ts`:
- Added `pendingSync: Set<string>` to track unsaved errors
- `markErrorSynced(errorId)` - Mark error as persisted
- `getPendingSyncErrors()` - Get errors awaiting sync

### UI Components

- `ErrorHistoryDrawer` (`src/components/errors/ErrorHistoryDrawer.tsx`)
  - Multi-select with checkboxes
  - Search filter
  - Bulk copy to clipboard
  - Click to open in GlobalErrorModal
  
- `ErrorQueueBadge` (`src/components/errors/ErrorQueueBadge.tsx`)
  - Shows error count in header
  - Click to open ErrorHistoryDrawer
  - Red badge styling

### App Integration

`src/App.tsx` now includes:
- `ErrorHistorySyncProvider` wrapping the app
- Auto-persists errors as they're captured

## Phase 4: Backend Tab Auto-Fetch ✅ COMPLETE

The Backend tab in GlobalErrorModal now:
1. Auto-fetches `error.log.txt` on hover/focus (same as Stack tab)
2. Displays error log content with copy/download/refresh buttons
3. Shows loading and error states with retry
4. Cached for duration of modal open

Tab content order:
- Target Site URL (if available)
- Backend Error Log (error.log.txt) - auto-fetched
- Execution Logs (from CapturedError.backendLogs)
- Go Stack Trace (from CapturedError.backendStackTrace)

## Next Steps (Phase 5-6)

- Phase 5: UI click path tracking
- Phase 6: Multi-error queue UI with navigation and bulk copy
