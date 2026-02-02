# Project Context

> **Location:** `.lovable/memory/02-project-context.md`  
> **Updated:** 2026-02-02

---

## Repository Overview

This repository contains **three projects**:

| Project | Location | Status | Technology |
|---------|----------|--------|------------|
| WP Plugin Publish | `backend/`, `src/`, `spec/wp-plugin-publish/` | 🔄 Active | Go + React |
| Plugins Onboard | `plugins-onboard/` | ✅ Complete | WordPress PHP |
| Spec Builder v3 | (Referenced only) | 📝 Dormant | Mentioned in CONTEXT-FOR-AI.md |

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
| Spec | 28 spec documents | 2026-02-01 | Complete with E2E test spec |
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

| Phase | Feature | Priority | Dependencies | Est. Time |
|-------|---------|----------|--------------|-----------|
| Phase 3 | Site Service | **HIGH** | None | 2 hours |
| Phase 4 | Publish Service | **HIGH** | Site Service | 3 hours |
| Phase 6 | Backup Service | MEDIUM | Publish Service | 2 hours |
| Phase 7 | WebSocket real-time | MEDIUM | None | 2 hours |
| Phase 8 | Integration testing | LOW | All phases | 2-3 hours |

---

## Key Files

| Type | Location |
|------|----------|
| Master Spec | `spec/wp-plugin-publish/00-overview.md` |
| Backend Specs | `spec/wp-plugin-publish/01-backend/` (16 files) |
| Frontend Specs | `spec/wp-plugin-publish/02-frontend/` (6 files) |
| Implementation Specs | `spec/wp-plugin-publish/03-implementation/` (6 files) |
| E2E Test Spec | `spec/wp-plugin-publish/04-testing/40-e2e-test-spec.md` |
| Shared Constants | `spec/wp-plugin-publish/66-shared-constants.md` |
| Go Backend | `backend/` |
| React Frontend | `src/` |
| E2E Service | `backend/internal/services/e2e/` |
| Plan | `.lovable/plan.md` |
| Suggestions | `.lovable/memory/suggestions/01-suggestions-tracker.md` |
| Risk Report | `.lovable/memory/03-reliability-risk-report.md` |

---

## Backend Services Structure

```
backend/internal/services/
├── backup/     # Backup and restore (pending)
├── e2e/        # End-to-end testing ✅
├── git/        # Git pull and build ✅
├── plugin/     # Plugin CRUD and scanning ✅
├── publish/    # Publish pipeline (pending)
├── site/       # Site CRUD (pending)
├── sync/       # Sync comparison ✅
└── watcher/    # File change detection ✅
```

---

## Recent Changes (2026-02-02)

1. **E2E Testing Framework** - 20 test cases, 4 categories, Split DB integration
2. **Error Detail Modal** - Developer debug with stack trace and copy report
3. **Hybrid File Watcher** - Event-driven instead of polling
4. **Tests page** - Tab-based UI for running E2E tests

---

## Known Issues / Blockers

| Issue | Impact | Resolution |
|-------|--------|------------|
| Go Backend Not Verified | May have compile errors | Run `go build ./cmd/server` |
| React Build Not Verified | May have type errors | Run `npm run build` |
| Site Service Missing | Blocks Publish Service | Implement Phase 3 |
| Publish Service Missing | Core functionality incomplete | Implement Phase 4 |

---

## Next Steps for New AI

1. **Read `.lovable/plan.md`** - Prioritized tasks with phase details
2. **Read `.lovable/memory/03-reliability-risk-report.md`** - Risk assessment
3. **Check suggestions** in `01-suggestions-tracker.md`
4. **Start with Phase 3: Site Service** (or ask user for preference)
5. **Verify Go backend compiles** before adding more code

---

## Secondary Project: Plugins Onboard

**Location:** `plugins-onboard/`  
**Status:** Complete (v1.0.5)  
**Purpose:** WordPress plugin providing REST API for remote plugin management

Features:
- OAuth 2.0 authentication with JWT
- Ephemeral mutation tokens
- Automatic backups and snapshots
- Audit logging
- Admin dashboard with test runner

See `memory/PRD.md` for full specification.

---

*Update when implementation progresses.*
