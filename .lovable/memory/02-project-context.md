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

### Completed
- ✅ All 21+ spec documents (Phases 1-4)
- ✅ Go backend scaffold (main.go, services, API, WebSocket)
- ✅ React frontend scaffold (routing, layout, pages, hooks)
- ✅ Database migrations
- ✅ API client and WebSocket client
- ✅ Plugin Service (CRUD, scanning, mappings) - Phase 1
- ✅ Sync Service (local/remote comparison, change detection) - Phase 2
- ✅ Git & Build Service (git pull, build commands) - Phase 5
- ✅ Hybrid File Watcher (event-driven, not polling)
- ✅ E2E Test Framework (spec, backend runner, frontend UI)
- ✅ Error Detail Modal (full debug info, suggested fixes)
- ✅ Tests page in navigation

### Pending
- 📝 Site Service full implementation
- 📝 Publish Service (ZIP upload, single-file patches) - Phase 4
- 📝 Backup Service
- 📝 WebSocket real-time updates for sync progress
- 📝 Full E2E test case implementations
- 📝 Integration testing with real WordPress sites

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

3. **Navigation Update**
   - Added "E2E Tests" menu item to sidebar

---

*Update when implementation progresses.*
