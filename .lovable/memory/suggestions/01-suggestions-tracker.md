# Suggestions Tracker

> **Location:** `.lovable/memory/suggestions/01-suggestions-tracker.md`  
> **Purpose:** Track AI suggestions for improvements (consolidated single file)  
> **Updated:** 2026-02-09

---

## Active Suggestions (Open)

### S-001: Add WordPress API Error Examples
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Project | wp-plugin-publish |
| Priority | high |
| Status | **open** |
| Description | Add concrete WordPress REST API error response examples to `10-wp-rest-client.md` |
| Acceptance Criteria | Spec includes ≥4 error response examples with codes |

### S-004: Add Error Recovery for Partial Publish
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Priority | high |
| Status | **open** |
| Description | Document what happens if publish fails mid-way (rollback trigger, backup restore, status reporting) |
| Notes | Phase 38 (Rollback on Failure) implemented the mechanism; spec documentation still pending |

### S-005: Define WebSocket Reconnection State Recovery
| Field | Value |
|-------|-------|
| Created | 2026-02-01 |
| Source | Lovable (Risk Report) |
| Priority | medium |
| Status | **open** |
| Description | Define how frontend recovers missed events after WS disconnect |

### S-013: DRY Phase 7 — PHP Snapshot Factory
| Field | Value |
|-------|-------|
| Created | 2026-02-09 |
| Source | DRY Refactoring Plan |
| Priority | medium |
| Status | **open** |
| Description | Create `RiseupSnapshotFactory` with lazy singletons to replace 5+ duplicated require+instantiate blocks |

### S-014: DRY Phase 8 — PHP Logger Consolidation
| Field | Value |
|-------|-------|
| Created | 2026-02-09 |
| Source | DRY Refactoring Plan |
| Priority | medium |
| Status | **open** |
| Description | Consolidate 4 logger methods' context enrichment into single `prepare_context()` |

### S-015: DRY Phase 9 — GlobalErrorModal Decomposition
| Field | Value |
|-------|-------|
| Created | 2026-02-09 |
| Source | DRY Refactoring Plan |
| Priority | high |
| Status | **open** |
| Description | Split 2,164-line `GlobalErrorModal.tsx` into focused sub-components |

### S-016: DRY Phase 10 — Envelope Schema Alignment
| Field | Value |
|-------|-------|
| Created | 2026-02-09 |
| Source | DRY Refactoring Plan |
| Priority | low |
| Status | **open** |
| Description | Create `envelope.schema.json` as single source of truth for Go/TS/PHP envelope types |

---

## Completed Suggestions

| ID | Title | Completed | Notes |
|----|-------|-----------|-------|
| S-002 | fsnotify Platform Differences | 2026-02-02 | Replaced with hybrid watcher mode |
| S-003 | Specify Hash Algorithm | 2026-02-02 | MD5 implemented in scanner.go |
| S-006 | Verify Go Backend Compiles | 2026-02-05 | Confirmed working |
| S-007 | Verify React Frontend Builds | 2026-02-05 | Confirmed working |
| S-008 | Implement Site Service | 2026-02-02 | Full CRUD handlers |
| S-009 | Implement Publish Service | 2026-02-02 | Full pipeline |
| S-010 | WebSocket Real-time Sync | 2026-02-02 | Broadcasting helpers added |
| S-011 | E2E Testing Framework | 2026-02-02 | 20 test cases, Go runner, React UI |
| S-012 | Error Detail Modal | 2026-02-02 | Developer debug info with copy feature |

→ Details in `.lovable/memory/suggestions/completed/01-completed-suggestions.md`

---

## Rejected Suggestions

*None.*

---

## Statistics

| Metric | Count |
|--------|-------|
| Open | 7 |
| Completed | 9 |
| Rejected | 0 |
| **Total** | **16** |

---

*Update this file when suggestions are added, started, completed, or rejected.*
