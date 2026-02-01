# Error Codes: Project Editor Module

**Version:** 1.1.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Parent:** [Project Editor](./00-overview.md)  
**Error Range:** 13000-13999

---

## Overview

Error codes for the Project Editor module covering input state persistence, draft recovery, cross-device sync, and editor state management. All errors use the 13xxx range following the project's centralized error management conventions.

---

## Error Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| 13000-13099 | General | Module-level errors |
| 13100-13199 | Input Persistence | localStorage/IndexedDB errors |
| 13200-13299 | Draft Recovery | Recovery detection and restore errors |
| 13300-13399 | Sync API | Cross-device synchronization errors |
| 13400-13499 | Editor State | Cursor, scroll, undo/redo errors |
| 13500-13599 | Validation | Input validation errors |
| 13900-13999 | Internal | Internal/unexpected errors |

---

## Error Definitions

### General Errors (13000-13099)

| Code | Name | HTTP | Message | Resolution |
|------|------|------|---------|------------|
| 13000 | `PROJECT_EDITOR_UNKNOWN` | 500 | Unknown project editor error | Check logs for details |
| 13001 | `PROJECT_EDITOR_INIT_FAILED` | 500 | Failed to initialize project editor | Reload the page |
| 13002 | `PROJECT_EDITOR_NOT_READY` | 503 | Project editor not initialized | Wait for initialization |
| 13003 | `PROJECT_EDITOR_DISABLED` | 403 | Project editor is disabled | Enable in settings |
| 13004 | `PROJECT_CONTEXT_MISSING` | 400 | Project context not found | Select a project first |
| 13005 | `PROJECT_EDITOR_TIMEOUT` | 504 | Operation timed out | Retry the operation |

---

### Input Persistence Errors (13100-13199)

| Code | Name | HTTP | Message | Resolution |
|------|------|------|---------|------------|
| 13100 | `STORAGE_UNAVAILABLE` | 503 | Browser storage unavailable | Enable cookies/storage |
| 13101 | `LOCALSTORAGE_QUOTA_EXCEEDED` | 507 | localStorage quota exceeded | Clear old drafts |
| 13102 | `INDEXEDDB_OPEN_FAILED` | 500 | Failed to open IndexedDB | Check browser support |
| 13103 | `INDEXEDDB_TRANSACTION_FAILED` | 500 | IndexedDB transaction failed | Retry operation |
| 13104 | `INDEXEDDB_UPGRADE_FAILED` | 500 | IndexedDB upgrade failed | Clear database and retry |
| 13105 | `STORAGE_KEY_INVALID` | 400 | Invalid storage key format | Use valid key format |
| 13106 | `STORAGE_VALUE_TOO_LARGE` | 413 | Value exceeds maximum size (5MB) | Reduce content size |
| 13107 | `STORAGE_SERIALIZATION_FAILED` | 500 | Failed to serialize data | Check data format |
| 13108 | `STORAGE_DESERIALIZATION_FAILED` | 500 | Failed to deserialize data | Data may be corrupted |
| 13109 | `STORAGE_MIGRATION_FAILED` | 500 | Version migration failed | Clear old data |
| 13110 | `STORAGE_ENCRYPTION_FAILED` | 500 | Failed to encrypt data | Check encryption keys |
| 13111 | `STORAGE_DECRYPTION_FAILED` | 500 | Failed to decrypt data | Key may have changed |
| 13112 | `STORAGE_PERMISSION_DENIED` | 403 | Storage access denied | Grant storage permission |

---

### Draft Recovery Errors (13200-13299)

| Code | Name | HTTP | Message | Resolution |
|------|------|------|---------|------------|
| 13200 | `DRAFT_NOT_FOUND` | 404 | Draft not found | Draft may have expired |
| 13201 | `DRAFT_EXPIRED` | 410 | Draft has expired | Create new content |
| 13202 | `DRAFT_CORRUPTED` | 500 | Draft data is corrupted | Discard and start fresh |
| 13203 | `DRAFT_RESTORE_FAILED` | 500 | Failed to restore draft | Try manual recovery |
| 13204 | `DRAFT_DISCARD_FAILED` | 500 | Failed to discard draft | Retry operation |
| 13205 | `DRAFT_CONFLICT` | 409 | Draft conflicts with current | Choose version to keep |
| 13206 | `DRAFT_VERSION_MISMATCH` | 409 | Draft version incompatible | Migrate or discard |
| 13207 | `DRAFT_PARSE_ERROR` | 400 | Failed to parse draft metadata | Check draft format |
| 13208 | `DRAFT_TOO_OLD` | 410 | Draft is too old to recover | Maximum age exceeded |
| 13209 | `DRAFT_RECOVERY_TIMEOUT` | 504 | Recovery operation timed out | Retry recovery |
| 13210 | `DRAFT_BATCH_LIMIT_EXCEEDED` | 400 | Too many drafts to recover | Recover in batches |
| 13211 | `DRAFT_TYPE_UNKNOWN` | 400 | Unknown draft type | Update to latest version |

---

### Sync API Errors (13300-13399)

| Code | Name | HTTP | Message | Resolution |
|------|------|------|---------|------------|
| 13300 | `SYNC_DISABLED` | 403 | Sync is disabled for this account | Upgrade to premium |
| 13301 | `SYNC_QUOTA_EXCEEDED` | 429 | Sync quota exceeded | Wait or upgrade plan |
| 13302 | `SYNC_KEY_LIMIT_REACHED` | 400 | Maximum synced keys reached | Remove old keys |
| 13303 | `SYNC_VALUE_TOO_LARGE` | 413 | Sync value exceeds limit | Reduce content size |
| 13304 | `SYNC_RATE_LIMITED` | 429 | Too many sync requests | Wait before retrying |
| 13305 | `SYNC_CONFLICT_UNRESOLVED` | 409 | Sync conflict requires resolution | Choose version |
| 13306 | `SYNC_DEVICE_LIMIT_REACHED` | 400 | Maximum devices reached | Remove a device |
| 13307 | `SYNC_DEVICE_NOT_FOUND` | 404 | Device not found | Re-register device |
| 13308 | `SYNC_NETWORK_ERROR` | 503 | Network error during sync | Check connection |
| 13309 | `SYNC_SERVER_ERROR` | 500 | Sync server error | Retry later |
| 13310 | `SYNC_AUTH_REQUIRED` | 401 | Authentication required for sync | Log in again |
| 13311 | `SYNC_AUTH_EXPIRED` | 401 | Sync authentication expired | Refresh token |
| 13312 | `SYNC_BATCH_PARTIAL_FAILURE` | 207 | Some items failed to sync | Check individual errors |
| 13313 | `SYNC_VERSION_CONFLICT` | 409 | Version conflict detected | Resolve conflict |
| 13314 | `SYNC_OFFLINE_QUEUE_FULL` | 507 | Offline queue is full | Sync when online |
| 13315 | `SYNC_STATE_EXPIRED` | 410 | Synced state has expired | Re-sync from server |

---

### Editor State Errors (13400-13499)

| Code | Name | HTTP | Message | Resolution |
|------|------|------|---------|------------|
| 13400 | `CURSOR_POSITION_INVALID` | 400 | Invalid cursor position | Reset cursor |
| 13401 | `CURSOR_OUT_OF_BOUNDS` | 400 | Cursor position out of bounds | Adjust position |
| 13402 | `SELECTION_INVALID` | 400 | Invalid selection range | Clear selection |
| 13403 | `SELECTION_COLLAPSED` | 400 | Selection has no range | Expand selection |
| 13404 | `SCROLL_POSITION_INVALID` | 400 | Invalid scroll position | Reset scroll |
| 13405 | `SCROLL_TARGET_NOT_FOUND` | 404 | Scroll target not found | Check element exists |
| 13406 | `UNDO_STACK_EMPTY` | 400 | Nothing to undo | No history available |
| 13407 | `REDO_STACK_EMPTY` | 400 | Nothing to redo | No forward history |
| 13408 | `UNDO_HISTORY_CORRUPTED` | 500 | Undo history corrupted | Clear history |
| 13409 | `UNDO_HISTORY_LIMIT_REACHED` | 400 | Undo history limit reached | Oldest entries removed |
| 13410 | `EDITOR_STATE_INVALID` | 500 | Editor state is invalid | Reload editor |
| 13411 | `EDITOR_REF_NOT_FOUND` | 404 | Editor reference not found | Re-mount component |
| 13412 | `EDITOR_CONTENT_MISMATCH` | 409 | Editor content mismatch | Sync content |
| 13413 | `EDITOR_LOCK_FAILED` | 423 | Failed to acquire editor lock | Wait for unlock |
| 13414 | `EDITOR_READONLY` | 403 | Editor is in read-only mode | Exit read-only |

---

### Validation Errors (13500-13599)

| Code | Name | HTTP | Message | Resolution |
|------|------|------|---------|------------|
| 13500 | `INPUT_KEY_REQUIRED` | 400 | Input key is required | Provide a valid key |
| 13501 | `INPUT_KEY_TOO_LONG` | 400 | Input key exceeds 500 chars | Shorten the key |
| 13502 | `INPUT_KEY_INVALID_CHARS` | 400 | Input key contains invalid chars | Use alphanumeric/underscore |
| 13503 | `INPUT_VALUE_REQUIRED` | 400 | Input value is required | Provide a value |
| 13504 | `INPUT_VALUE_TOO_LONG` | 400 | Input value exceeds 5MB | Reduce content size |
| 13505 | `PROJECT_ID_REQUIRED` | 400 | Project ID is required | Select a project |
| 13506 | `PROJECT_ID_INVALID` | 400 | Invalid project ID format | Use valid UUID |
| 13507 | `CONTEXT_ID_REQUIRED` | 400 | Context ID is required | Provide context |
| 13508 | `CONTEXT_ID_INVALID` | 400 | Invalid context ID | Use valid context |
| 13509 | `FIELD_ID_REQUIRED` | 400 | Field ID is required | Provide field ID |
| 13510 | `DEVICE_ID_REQUIRED` | 400 | Device ID is required | Generate device ID |
| 13511 | `DEVICE_ID_INVALID` | 400 | Invalid device ID format | Use valid UUID |
| 13512 | `TIMESTAMP_INVALID` | 400 | Invalid timestamp format | Use Unix timestamp |
| 13513 | `VERSION_INVALID` | 400 | Invalid version number | Use positive integer |

---

### Internal Errors (13900-13999)

| Code | Name | HTTP | Message | Resolution |
|------|------|------|---------|------------|
| 13900 | `INTERNAL_ERROR` | 500 | Internal project editor error | Contact support |
| 13901 | `STATE_MANAGER_ERROR` | 500 | State manager internal error | Reload application |
| 13902 | `HOOK_INITIALIZATION_ERROR` | 500 | Hook initialization failed | Re-mount component |
| 13903 | `EVENT_DISPATCH_ERROR` | 500 | Failed to dispatch event | Check event handlers |
| 13904 | `CALLBACK_ERROR` | 500 | Callback execution failed | Check callback function |
| 13999 | `UNEXPECTED_ERROR` | 500 | Unexpected error occurred | Check logs |

---

## Error Type Definition

```typescript
// src/types/project-editor-errors.ts

export enum ProjectEditorErrorCode {
  // General (13000-13099)
  UNKNOWN = 13000,
  INIT_FAILED = 13001,
  NOT_READY = 13002,
  DISABLED = 13003,
  CONTEXT_MISSING = 13004,
  TIMEOUT = 13005,
  
  // Input Persistence (13100-13199)
  STORAGE_UNAVAILABLE = 13100,
  LOCALSTORAGE_QUOTA_EXCEEDED = 13101,
  INDEXEDDB_OPEN_FAILED = 13102,
  INDEXEDDB_TRANSACTION_FAILED = 13103,
  INDEXEDDB_UPGRADE_FAILED = 13104,
  STORAGE_KEY_INVALID = 13105,
  STORAGE_VALUE_TOO_LARGE = 13106,
  STORAGE_SERIALIZATION_FAILED = 13107,
  STORAGE_DESERIALIZATION_FAILED = 13108,
  STORAGE_MIGRATION_FAILED = 13109,
  STORAGE_ENCRYPTION_FAILED = 13110,
  STORAGE_DECRYPTION_FAILED = 13111,
  STORAGE_PERMISSION_DENIED = 13112,
  
  // Draft Recovery (13200-13299)
  DRAFT_NOT_FOUND = 13200,
  DRAFT_EXPIRED = 13201,
  DRAFT_CORRUPTED = 13202,
  DRAFT_RESTORE_FAILED = 13203,
  DRAFT_DISCARD_FAILED = 13204,
  DRAFT_CONFLICT = 13205,
  DRAFT_VERSION_MISMATCH = 13206,
  DRAFT_PARSE_ERROR = 13207,
  DRAFT_TOO_OLD = 13208,
  DRAFT_RECOVERY_TIMEOUT = 13209,
  DRAFT_BATCH_LIMIT_EXCEEDED = 13210,
  DRAFT_TYPE_UNKNOWN = 13211,
  
  // Sync API (13300-13399)
  SYNC_DISABLED = 13300,
  SYNC_QUOTA_EXCEEDED = 13301,
  SYNC_KEY_LIMIT_REACHED = 13302,
  SYNC_VALUE_TOO_LARGE = 13303,
  SYNC_RATE_LIMITED = 13304,
  SYNC_CONFLICT_UNRESOLVED = 13305,
  SYNC_DEVICE_LIMIT_REACHED = 13306,
  SYNC_DEVICE_NOT_FOUND = 13307,
  SYNC_NETWORK_ERROR = 13308,
  SYNC_SERVER_ERROR = 13309,
  SYNC_AUTH_REQUIRED = 13310,
  SYNC_AUTH_EXPIRED = 13311,
  SYNC_BATCH_PARTIAL_FAILURE = 13312,
  SYNC_VERSION_CONFLICT = 13313,
  SYNC_OFFLINE_QUEUE_FULL = 13314,
  SYNC_STATE_EXPIRED = 13315,
  
  // Editor State (13400-13499)
  CURSOR_POSITION_INVALID = 13400,
  CURSOR_OUT_OF_BOUNDS = 13401,
  SELECTION_INVALID = 13402,
  SELECTION_COLLAPSED = 13403,
  SCROLL_POSITION_INVALID = 13404,
  SCROLL_TARGET_NOT_FOUND = 13405,
  UNDO_STACK_EMPTY = 13406,
  REDO_STACK_EMPTY = 13407,
  UNDO_HISTORY_CORRUPTED = 13408,
  UNDO_HISTORY_LIMIT_REACHED = 13409,
  EDITOR_STATE_INVALID = 13410,
  EDITOR_REF_NOT_FOUND = 13411,
  EDITOR_CONTENT_MISMATCH = 13412,
  EDITOR_LOCK_FAILED = 13413,
  EDITOR_READONLY = 13414,
  
  // Validation (13500-13599)
  INPUT_KEY_REQUIRED = 13500,
  INPUT_KEY_TOO_LONG = 13501,
  INPUT_KEY_INVALID_CHARS = 13502,
  INPUT_VALUE_REQUIRED = 13503,
  INPUT_VALUE_TOO_LONG = 13504,
  PROJECT_ID_REQUIRED = 13505,
  PROJECT_ID_INVALID = 13506,
  CONTEXT_ID_REQUIRED = 13507,
  CONTEXT_ID_INVALID = 13508,
  FIELD_ID_REQUIRED = 13509,
  DEVICE_ID_REQUIRED = 13510,
  DEVICE_ID_INVALID = 13511,
  TIMESTAMP_INVALID = 13512,
  VERSION_INVALID = 13513,
  
  // Internal (13900-13999)
  INTERNAL_ERROR = 13900,
  STATE_MANAGER_ERROR = 13901,
  HOOK_INITIALIZATION_ERROR = 13902,
  EVENT_DISPATCH_ERROR = 13903,
  CALLBACK_ERROR = 13904,
  UNEXPECTED_ERROR = 13999,
}

export interface ProjectEditorError {
  readonly code: ProjectEditorErrorCode;
  readonly message: string;
  readonly details?: string;
  readonly context?: Record<string, unknown>;
  readonly timestamp: Date;
  readonly recoverable: boolean;
}

export function createProjectEditorError(
  code: ProjectEditorErrorCode,
  details?: string,
  context?: Record<string, unknown>
): ProjectEditorError {
  const errorInfo = ERROR_MESSAGES[code];
  
  return {
    code,
    message: errorInfo?.message ?? 'Unknown error',
    details,
    context,
    timestamp: new Date(),
    recoverable: errorInfo?.recoverable ?? false,
  };
}

const ERROR_MESSAGES: Record<ProjectEditorErrorCode, { message: string; recoverable: boolean }> = {
  [ProjectEditorErrorCode.UNKNOWN]: { message: 'Unknown project editor error', recoverable: false },
  [ProjectEditorErrorCode.STORAGE_UNAVAILABLE]: { message: 'Browser storage unavailable', recoverable: false },
  [ProjectEditorErrorCode.LOCALSTORAGE_QUOTA_EXCEEDED]: { message: 'Storage quota exceeded', recoverable: true },
  [ProjectEditorErrorCode.DRAFT_NOT_FOUND]: { message: 'Draft not found', recoverable: true },
  [ProjectEditorErrorCode.SYNC_RATE_LIMITED]: { message: 'Too many sync requests', recoverable: true },
  // ... additional mappings
} as Record<ProjectEditorErrorCode, { message: string; recoverable: boolean }>;
```

---

## Usage Examples

### Throwing Errors

```typescript
import { createProjectEditorError, ProjectEditorErrorCode } from '@/types/project-editor-errors';

// Storage quota exceeded
if (isQuotaExceeded(error)) {
  throw createProjectEditorError(
    ProjectEditorErrorCode.LOCALSTORAGE_QUOTA_EXCEEDED,
    'Cannot save draft: storage is full',
    { attemptedSize: value.length, key }
  );
}

// Draft not found
if (!draft) {
  throw createProjectEditorError(
    ProjectEditorErrorCode.DRAFT_NOT_FOUND,
    `Draft with key ${key} not found`,
    { key, projectId }
  );
}

// Sync rate limited
if (response.status === 429) {
  throw createProjectEditorError(
    ProjectEditorErrorCode.SYNC_RATE_LIMITED,
    'Please wait before syncing again',
    { retryAfter: response.headers.get('Retry-After') }
  );
}
```

### Error Handling

```typescript
try {
  await inputStateManager.set(key, value);
} catch (error) {
  if (error instanceof ProjectEditorError) {
    switch (error.code) {
      case ProjectEditorErrorCode.LOCALSTORAGE_QUOTA_EXCEEDED:
        await cleanupOldDrafts();
        await inputStateManager.set(key, value); // Retry
        break;
      case ProjectEditorErrorCode.STORAGE_UNAVAILABLE:
        showToast('Storage unavailable. Changes won\'t be saved.');
        break;
      default:
        console.error('Project editor error:', error);
    }
  }
}
```

---

## Related Specs

- [Error Code Registry](../../06-error-management/error-code-registry.md) — Master error list (13xxx range)
- [Input State Persistence](./06-input-state-persistence.md) — Storage implementation
- [Draft Recovery UI](./01-draft-recovery-ui.md) — Error display
- [Sync API](./02-sync-api.md) — Sync error handling
