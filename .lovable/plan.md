# Active Roadmap

> **Location:** `.lovable/plan.md`  
> **Updated:** 2026-02-02  
> **Purpose:** Prioritized backlog for AI implementation handoff

---

## Current Status

| Area | Status |
|------|--------|
| Specification | ✅ Complete (28 docs including E2E spec) |
| Implementation Specs | ✅ Complete (6 docs) |
| Go Backend Scaffold | ✅ Complete |
| React Frontend Scaffold | ✅ Complete |
| Plugin Service | ✅ Complete (Phase 1) |
| Sync Service | ✅ Complete (Phase 2) |
| Git & Build Service | ✅ Complete (Phase 5) |
| Hybrid File Watcher | ✅ Complete |
| E2E Testing Framework | ✅ Complete |
| Error Detail Modal | ✅ Complete |
| Full Implementation | 🔄 Phases 3-4 Pending |

---

## ✅ Completed Phases

### Phase 1: Plugin Service ✅
- **Completed:** 2026-02-02
- Types, CRUD, scanner, mappings all implemented
- Spec: `03-implementation/30-plugin-service-impl.md`

### Phase 2: Sync Service ✅
- **Completed:** 2026-02-02
- Local/remote comparison, change detection, MD5 hashing
- Spec: `03-implementation/31-sync-service-impl.md`

### Phase 5: Git & Build Service ✅
- **Completed:** 2026-02-02
- Git pull, build commands, per-plugin config
- Spec: `03-implementation/34-git-service-impl.md`

### Hybrid File Watcher ✅
- **Completed:** 2026-02-02
- Event-driven (not polling), triggers after Git pull or manual refresh
- Spec: `03-implementation/33-watcher-service-impl.md`

### E2E Testing Framework ✅
- **Completed:** 2026-02-02
- 20 test cases across 4 categories (Plugin CRUD, Site Connections, Sync Operations, Publish Flow)
- Go test runner with Split DB integration
- Frontend UI with category tabs and checkable test cards
- Spec: `04-testing/40-e2e-test-spec.md`

### Error Detail Modal ✅
- **Completed:** 2026-02-02
- Developer-level debug info with Stack Trace, Request/Response tabs
- Error code-specific suggested fixes
- Copy full report functionality

---

## 📋 Pending Phases

### Phase 3: Site Service (Next - Priority: HIGH)

**Duration:** 2 hours  
**Spec:** `01-backend/04-site-service.md`

| Task | Status | Notes |
|------|--------|-------|
| CreateSite handler | todo | With encryption for tokens |
| UpdateSite handler | todo | Partial updates |
| DeleteSite handler | todo | With cascade logic |
| TestConnection handler | todo | Validate WP REST API access |
| Site validation | todo | URL format, required fields |

---

### Phase 4: Publish Service (Priority: HIGH)

**Duration:** 3 hours  
**Spec:** `03-implementation/32-publish-service-impl.md`  
**Dependencies:** Site Service

| Task | Status | Notes |
|------|--------|-------|
| Implement types.go | todo | PublishOptions, PublishResult |
| Implement pipeline.go | todo | Orchestrate full pipeline |
| Implement packager.go | todo | Build ZIP with structure |
| Implement uploader.go | todo | Send to WP REST API |
| Update WordPress client | todo | UploadPlugin, ActivatePlugin |
| Backup before publish | todo | Integration with backup service |

---

### Phase 6: Backup Service

**Duration:** 2 hours  
**Spec:** `01-backend/09-backup-service.md`

| Task | Status | Notes |
|------|--------|-------|
| Backup creation | todo | Before publish operations |
| Backup restoration | todo | Rollback capability |
| Backup cleanup | todo | Retention policy |

---

### Phase 7: WebSocket Real-time Updates

**Duration:** 2 hours

| Task | Status | Notes |
|------|--------|-------|
| sync:progress events | todo | Real-time sync status |
| scan:progress events | todo | File scan progress |
| Frontend integration | todo | Update Plugins page |

---

### Phase 8: Integration Testing

**Duration:** 2-3 hours  
**Dependencies:** All previous phases

| Task | Status | Notes |
|------|--------|-------|
| Run E2E test cases | todo | Against real WordPress site |
| Verify Go backend compiles | todo | `go build ./cmd/server` |
| Frontend production build | todo | `npm run build` |
| Full workflow testing | todo | Create → Map → Sync → Publish |

---

## Next Task Selection

**Recommended:** Start Phase 3 - Site Service Implementation

1. Implement CreateSite with token encryption
2. Implement TestConnection to validate WP REST API
3. Implement UpdateSite and DeleteSite
4. Wire up API handlers

**Command to test:** `cd backend && go build ./cmd/server`

---

*Phase 1, 2, 5 + E2E Testing complete. Continue with Phase 3: Site Service.*
