# Project Context

> **Location:** `.lovable/memory/02-project-context.md`  
> **Updated:** 2026-02-02

---

## Active Project: WP Plugin Publish

**Purpose:** Local development tool for WordPress plugin developers.

**Architecture:**
- **Frontend:** React + TypeScript + Tailwind (localhost:3000)
- **Backend:** Go 1.21+ (localhost:8080)
- **Database:** SQLite with Split DB Architecture
- **Config:** JSON seed + SQLite runtime

---

## Implementation Status

### ✅ Completed

| Phase | Feature | Date | Notes |
|-------|---------|------|-------|
| Spec | 28 spec documents | 2026-02-01 | Includes E2E test spec |
| Scaffold | Go backend | 2026-02-01 | main.go, services, API, WebSocket |
| Scaffold | React frontend | 2026-02-01 | Routing, layout, pages, hooks |
| Phase 1 | Plugin Service | 2026-02-02 | CRUD, scanning, mappings |
| Phase 2 | Sync Service | 2026-02-02 | Local/remote comparison, MD5 hashing |
| Phase 5 | Git & Build Service | 2026-02-02 | Git pull, build commands |
| - | Hybrid File Watcher | 2026-02-02 | Event-driven, not polling |
| - | E2E Test Framework | 2026-02-02 | 20 test cases, Go runner, React UI |
| - | Error Detail Modal | 2026-02-02 | Developer debug, copy report |
| - | Tests page in nav | 2026-02-02 | Tab-based test suite UI |

### 📋 Pending

| Phase | Feature | Priority | Dependencies |
|-------|---------|----------|--------------|
| Phase 3 | Site Service | HIGH | None |
| Phase 4 | Publish Service | HIGH | Site Service |
| Phase 6 | Backup Service | MEDIUM | Publish Service |
| Phase 7 | WebSocket real-time | MEDIUM | None |
| Phase 8 | Integration testing | LOW | All phases |

---

## Key Files

| Type | Location |
|------|----------|
| Spec docs | `spec/wp-plugin-publish/` |
| Go backend | `backend/` |
| React frontend | `src/` |
| E2E Test Spec | `spec/wp-plugin-publish/04-testing/40-e2e-test-spec.md` |
| E2E Service | `backend/internal/services/e2e/` |
| Plan | `.lovable/plan.md` |
| Suggestions | `.lovable/memory/suggestions/01-suggestions-tracker.md` |

---

## Recent Changes (2026-02-02)

1. **E2E Testing Framework**
   - Created spec document with 20 test cases across 4 categories
   - Implemented Go test runner service with Split DB integration
   - Added Tests page to frontend with suite selection and run history
   - WebSocket events for real-time test progress

2. **Error Detail Modal**
   - Developer-level debug information
   - Tabs: Overview, Stack Trace, Request/Response, Suggested Fixes
   - One-click copy of full error report for sharing
   - Error code-specific fix suggestions

3. **UI/UX Improvements**
   - Unified header with route-based breadcrumbs
   - Test cases displayed as checkable cards with category tabs
   - Select All toggle per category

---

## Known Issues / Inconsistencies

1. **Go Backend Not Verified** - Need to run `go build ./cmd/server` locally
2. **React Build Not Verified** - Need to run `npm run build`
3. **Site Service Not Implemented** - CRUD handlers pending
4. **Publish Service Not Implemented** - ZIP upload pending

---

## Next Steps for New AI

1. Read `.lovable/plan.md` for prioritized tasks
2. Start with **Phase 3: Site Service** implementation
3. Verify Go backend compiles before adding more code
4. Check `01-suggestions-tracker.md` for open suggestions

---

*Update when implementation progresses.*
