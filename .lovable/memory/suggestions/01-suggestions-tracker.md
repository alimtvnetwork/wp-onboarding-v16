# Suggestions Tracker

> **Location:** `.lovable/memory/suggestions/01-suggestions-tracker.md`  
> **Purpose:** Track AI suggestions for improvements (consolidated single file)  
> **Updated:** 2026-02-02

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
| Priority | medium |
| Status | **done** |
| Description | Document Windows vs macOS vs Linux differences for fsnotify |
| Notes | Hybrid watcher mode implemented instead of fsnotify polling - uses Git + manual trigger |

---

### S-003: Specify Hash Algorithm for Sync
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | medium |
| Status | **done** |
| Description | Explicitly define SHA256 or MD5 for file hashing |
| Notes | MD5 implemented in scanner.go and sync service |

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

### S-008: Implement Site Service
| Field | Value |
|-------|-------|
| Created | 2026-02-02 |
| Source | User |
| Project | wp-plugin-publish |
| Priority | high |
| Status | **open** |
| Description | Complete Site Service with CRUD and connection testing |
| Proposed Change | Implement CreateSite, UpdateSite, DeleteSite, TestConnection handlers |
| Acceptance Criteria | All site endpoints functional |

---

### S-009: Implement Publish Service
| Field | Value |
|-------|-------|
| Created | 2026-02-02 |
| Source | User |
| Project | wp-plugin-publish |
| Priority | high |
| Status | **open** |
| Description | Create publish service for ZIP upload and file patches |
| Proposed Change | Implement Phase 4 from implementation plan |
| Acceptance Criteria | Full and selective file publishing works |

---

### S-010: WebSocket Real-time Sync Updates
| Field | Value |
|-------|-------|
| Created | 2026-02-02 |
| Source | User |
| Project | wp-plugin-publish |
| Priority | medium |
| Status | **open** |
| Description | Add WebSocket events for sync status and file scan progress |
| Proposed Change | Broadcast sync:progress, scan:progress events |
| Acceptance Criteria | Plugins page shows real-time progress |

---

## Completed Suggestions

| ID | Title | Completed | Notes |
|----|-------|-----------|-------|
| S-002 | fsnotify Platform Differences | 2026-02-02 | Replaced with hybrid watcher mode |
| S-003 | Specify Hash Algorithm | 2026-02-02 | MD5 implemented |

---

## Rejected Suggestions

| ID | Title | Rejected | Reason |
|----|-------|----------|--------|
| - | No rejected suggestions yet | - | - |

---

*Update this file when suggestions are added, started, completed, or rejected.*
