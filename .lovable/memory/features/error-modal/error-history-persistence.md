# Memory: features/error-modal/error-history-persistence
Updated: 2026-02-06

## Overview

Error history persistence ensures all captured errors are saved to SQLite database for later retrieval, multi-selection, and bulk export.

## Requirements (Pending Implementation)

### 1. SQLite ErrorHistory Table
- Store all CapturedError fields in database
- Unique error ID
- Full context JSON
- Stack traces (frontend, backend, PHP)
- UI click path leading to error
- Timestamps and filters

### 2. Backend Endpoints
- `POST /api/v1/errors` - Save error
- `GET /api/v1/errors` - List with pagination/filters
- `DELETE /api/v1/errors` - Clear history
- `POST /api/v1/errors/bulk-copy` - Export multiple as markdown

### 3. Frontend Integration
- Auto-save on captureError/captureException
- Load history on app mount
- ErrorHistoryDrawer for multi-select
- Error queue badge in header

### 4. Multi-Error UI
- Navigate between queued errors (1 of 3)
- Select multiple for bulk copy
- "Copy All" button for combined report

## Related Files
- Plan: `.lovable/plan-error-diagnostics-v3.md`
- Error Store: `src/stores/errorStore.ts`
- Error Modal: `src/components/errors/GlobalErrorModal.tsx`

## Status
- Plan created: 2026-02-06
- Phase 1 (DeactivatePlugin fix): ✅ COMPLETE
- Phase 2-6: PENDING
