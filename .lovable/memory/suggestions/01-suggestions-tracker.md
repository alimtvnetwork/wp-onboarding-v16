# Suggestions Tracker

> **Location:** `.lovable/memory/suggestions/01-suggestions-tracker.md`  
> **Purpose:** Track AI suggestions for improvements (consolidated single file)  
> **Updated:** 2026-02-01

---

## File Convention

This file consolidates all suggestions to keep the folder small. Each suggestion has:

- **ID**: `S-{number}` (sequential)
- **Created**: Date
- **Source**: Who suggested (Lovable, User, External AI)
- **Project**: Which project it affects
- **Status**: `open` | `in-progress` | `done` | `rejected`
- **Priority**: `critical` | `high` | `medium` | `low`

---

## Active Suggestions

### S-001: Add WordPress API Error Examples
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | high |
| Status | **open** |
| Description | Add concrete WordPress REST API error response examples to `10-wp-rest-client.md` |
| Rationale | AI needs to know exact error shapes for proper error handling |
| Proposed Change | Add JSON examples for 401, 403, 404, 500 responses from WP |
| Acceptance Criteria | Spec includes ≥4 error response examples with codes |

---

### S-002: Document fsnotify Platform Differences
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | high |
| Status | **open** |
| Description | Document Windows vs macOS vs Linux differences for fsnotify |
| Rationale | File watching behaves differently per OS |
| Proposed Change | Add platform notes section to `06-file-watcher.md` |
| Acceptance Criteria | Spec covers: event types per platform, recursive watch limits, symlink behavior |

---

### S-003: Specify Hash Algorithm for Sync
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | medium |
| Status | **open** |
| Description | Explicitly define SHA256 or MD5 for file hashing |
| Rationale | Consistency between Go backend and potential WP-side comparison |
| Proposed Change | Add `HASH_ALGORITHM = "sha256"` to `66-shared-constants.md` |
| Acceptance Criteria | Constant defined, Go and spec aligned |

---

### S-004: Add Error Recovery for Partial Publish
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | high |
| Status | **open** |
| Description | Document what happens if publish fails mid-way |
| Rationale | User needs to know if plugin is in broken state |
| Proposed Change | Add error recovery section to `08-publish-service.md` |
| Acceptance Criteria | Spec covers: rollback trigger, backup restore, status reporting |

---

### S-005: Define WebSocket Reconnection State Recovery
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | medium |
| Status | **open** |
| Description | Define how frontend recovers missed events after WS disconnect |
| Rationale | UI can desync from backend during network issues |
| Proposed Change | Add state sync protocol to `12-websocket-events.md` |
| Acceptance Criteria | Spec defines: reconnect event, full state refresh trigger |

---

### S-006: Verify Go Backend Compiles
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | critical |
| Status | **open** |
| Description | Run `go build` to confirm backend compiles without errors |
| Rationale | Scaffolded code may have import or type errors |
| Proposed Change | Fix any compilation errors |
| Acceptance Criteria | `go build ./cmd/server` succeeds |

---

### S-007: Verify React Frontend Builds
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | critical |
| Status | **open** |
| Description | Run `npm run build` to confirm frontend builds |
| Rationale | Scaffolded code may have type or import errors |
| Proposed Change | Fix any build errors |
| Acceptance Criteria | `npm run build` succeeds |

---

## Completed Suggestions

| ID | Title | Completed | Notes |
|----|-------|-----------|-------|
| - | No completed suggestions yet | - | - |

---

## Rejected Suggestions

| ID | Title | Rejected | Reason |
|----|-------|----------|--------|
| - | No rejected suggestions yet | - | - |

---

*Update this file when suggestions are added, started, completed, or rejected.*
