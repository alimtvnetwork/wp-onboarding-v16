# Error Diagnostics Enhancement Plan v3

**Created: 2026-02-06**
**Priority: HIGH - Critical for debugging and user experience**

---

## Issue Summary

Multiple issues identified from error report `E9003`:

1. **404 on plugin deactivation** - `DeactivatePlugin` doesn't resolve plugin identifier
2. **"Not implemented" toast** - Download dropdown items showing not implemented
3. **No error history persistence** - Errors lost on page refresh
4. **Missing backend context** - Backend tab doesn't auto-fetch error logs
5. **No multi-error selection** - Can't copy/view multiple errors at once
6. **Missing UI context** - No tracking of user click path leading to error

---

## Phase 1: Critical Bug Fix - DeactivatePlugin ✅

**File:** `backend/internal/wordpress/client.go`

The `DeactivatePlugin` function doesn't call `ResolvePluginIdentifier` like `ActivatePlugin` does:

```go
// Current (broken):
func (c *Client) DeactivatePlugin(slug string) error {
    endpoint := "/wp/v2/plugins/" + escapePathSegmentPreservingPercent(slug)
    // ...
}

// Fixed:
func (c *Client) DeactivatePlugin(slug string) error {
    resolvedID, resolveErr := c.ResolvePluginIdentifier(slug)
    if resolveErr != nil {
        resolvedID = slug
    }
    endpoint := "/wp/v2/plugins/" + escapePathSegmentPreservingPercent(resolvedID)
    // ...
}
```

---

## Phase 2: Error History Database (Backend)

**Priority: HIGH**

### 2.1 SQLite Schema

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
    CreatedAt TEXT DEFAULT (datetime('now')),
    UNIQUE(ErrorId)
);

CREATE INDEX idx_error_history_created ON ErrorHistory(CreatedAt DESC);
CREATE INDEX idx_error_history_code ON ErrorHistory(Code);
```

### 2.2 Backend Endpoints

- `POST /api/v1/errors` - Save error to history
- `GET /api/v1/errors` - List errors (paginated, with filters)
- `GET /api/v1/errors/{id}` - Get single error with full details
- `DELETE /api/v1/errors` - Clear all errors
- `DELETE /api/v1/errors/{id}` - Delete single error
- `POST /api/v1/errors/bulk-copy` - Get multiple errors as markdown

### 2.3 Backend Service

```go
type ErrorHistoryService interface {
    Save(error *ErrorRecord) error
    List(limit, offset int, filters ErrorFilters) ([]ErrorRecord, error)
    GetByID(id string) (*ErrorRecord, error)
    Delete(id string) error
    Clear() error
    BulkExport(ids []string) (string, error)
}
```

---

## Phase 3: Frontend Error Persistence

**Priority: HIGH**

### 3.1 Auto-Save to Backend

Modify `errorStore.ts` to:
1. On `captureError`/`captureException`, POST to `/api/v1/errors`
2. Load initial history from backend on app mount
3. Sync recentErrors with backend

### 3.2 Error History Drawer

New component: `ErrorHistoryDrawer.tsx`

Features:
- List all historical errors
- Multi-select with checkboxes
- "Copy Selected" button
- "Copy All" button
- Filter by code, level, date range
- Click to open in GlobalErrorModal

### 3.3 Error Queue Badge

Show count of errors in session (in header/status bar):
- Click opens ErrorHistoryDrawer
- Badge shows error count

---

## Phase 4: Backend Tab Auto-Fetch

**Priority: MEDIUM**

### 4.1 Auto-fetch on Tab Focus

In `GlobalErrorModal.tsx`, the Backend tab should:
1. Auto-fetch `error.log.txt` content on tab focus
2. Show loading state
3. Cache content for duration of modal open
4. Add refresh button

### 4.2 Backend/Frontend Sub-Tabs in Overview

Split Overview tab into:
- **Frontend** - Current content (parsed frames, invocation chain)
- **Backend** - Backend logs, Go stack trace, session info

---

## Phase 5: UI Click Path Tracking

**Priority: MEDIUM**

### 5.1 Click Tracker Hook

```tsx
// useClickTracker.ts
const useClickTracker = () => {
  // Track last N user interactions
  // Store in localStorage or Zustand
  // Format: [{ element, timestamp, path, action }]
};
```

### 5.2 Integration with Error Capture

When error is captured:
1. Get recent click path from tracker
2. Include in error context
3. Display in Overview tab

---

## Phase 6: Multi-Error Queue UI

**Priority: MEDIUM**

### 6.1 Error Queue State

```typescript
interface ErrorQueueState {
  errors: CapturedError[];
  currentIndex: number;
  selectedIds: Set<string>;
}
```

### 6.2 Modal Navigation

- Show "1 of 3" indicator when multiple errors
- Previous/Next buttons to navigate
- "View All" to open drawer

### 6.3 Bulk Copy

- "Copy All Errors" button in modal footer
- Generates combined markdown report

---

## Implementation Order

| Order | Phase | Description | Priority | Est. Hours |
|-------|-------|-------------|----------|------------|
| 1 | Phase 1 | Fix DeactivatePlugin | CRITICAL | 0.5 |
| 2 | Phase 4 | Backend tab auto-fetch | HIGH | 1 |
| 3 | Phase 2 | Error history database | HIGH | 3 |
| 4 | Phase 3 | Frontend persistence | HIGH | 2 |
| 5 | Phase 6 | Multi-error queue | MEDIUM | 2 |
| 6 | Phase 5 | Click path tracking | LOW | 1 |

**Total: ~9.5 hours**

---

## Files to Create/Modify

### Backend
- `backend/internal/models/error_history.go` (new)
- `backend/internal/database/migrations.go` (add table)
- `backend/internal/services/errorhistory/service.go` (new)
- `backend/internal/api/handlers/error_handlers.go` (extend)
- `backend/internal/wordpress/client.go` (fix DeactivatePlugin)

### Frontend
- `src/stores/errorStore.ts` (add persistence)
- `src/components/errors/GlobalErrorModal.tsx` (enhance Backend tab)
- `src/components/errors/ErrorHistoryDrawer.tsx` (new)
- `src/components/errors/ErrorQueueBadge.tsx` (new)
- `src/hooks/useClickTracker.ts` (new)
- `src/hooks/useErrorHistory.ts` (new)

---

## Memory Updates Required

- `.lovable/memory/features/error-history/persistence.md`
- `.lovable/memory/features/error-history/multi-error-ui.md`
- `.lovable/memory/architecture/frontend/click-tracking.md`

---

*Plan created: 2026-02-06*
