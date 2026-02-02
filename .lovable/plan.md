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
| Full Implementation | 🔄 Phases 3-4, 6-8 Pending |

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
**Suggestion:** S-008

| Task | Status | Notes |
|------|--------|-------|
| CreateSite handler | todo | With AES-256 encryption for tokens |
| UpdateSite handler | todo | Partial updates, re-encrypt if password changes |
| DeleteSite handler | todo | With cascade delete of mappings |
| TestConnection handler | todo | Validate WP REST API access |
| ListSites handler | todo | With last sync timestamps |
| GetSite handler | todo | Single site with mappings |
| Site validation | todo | URL format, required fields |

**Files to create/modify:**
- `backend/internal/services/site/types.go`
- `backend/internal/services/site/service.go`
- `backend/internal/services/site/encryption.go`
- `backend/internal/api/sites.go`

---

### Phase 4: Publish Service (Priority: HIGH)

**Duration:** 3 hours  
**Spec:** `03-implementation/32-publish-service-impl.md`  
**Dependencies:** Site Service  
**Suggestion:** S-009

| Task | Status | Notes |
|------|--------|-------|
| Implement types.go | todo | PublishOptions, PublishResult, StageResult |
| Implement pipeline.go | todo | Orchestrate full publish pipeline |
| Implement packager.go | todo | Build ZIP with correct structure |
| Implement uploader.go | todo | Send to WP REST API |
| Update WordPress client | todo | UploadPlugin, ActivatePlugin methods |
| Backup before publish | todo | Integration with backup service |
| Stage progress events | todo | WebSocket notifications |

**Files to create/modify:**
- `backend/internal/services/publish/types.go`
- `backend/internal/services/publish/pipeline.go`
- `backend/internal/services/publish/packager.go`
- `backend/internal/services/publish/uploader.go`
- `backend/internal/wordpress/client.go` (update)
- `backend/internal/api/publish.go`

---

### Phase 6: Backup Service (Priority: MEDIUM)

**Duration:** 2 hours  
**Spec:** `01-backend/09-backup-service.md`

| Task | Status | Notes |
|------|--------|-------|
| Backup creation | todo | Before publish operations |
| Backup restoration | todo | Rollback capability |
| Backup listing | todo | With metadata and size |
| Backup cleanup | todo | Retention policy (30 days, 10 per plugin) |
| Backup download | todo | Serve ZIP file |

---

### Phase 7: WebSocket Real-time Updates (Priority: MEDIUM)

**Duration:** 2 hours  
**Suggestion:** S-010

| Task | Status | Notes |
|------|--------|-------|
| sync:progress events | todo | Real-time sync status |
| scan:progress events | todo | File scan progress |
| publish:progress events | todo | Stage-by-stage updates |
| Frontend integration | todo | Update Plugins page |
| Reconnection handling | todo | State recovery after disconnect |

---

### Phase 8: Integration Testing (Priority: LOW)

**Duration:** 2-3 hours  
**Dependencies:** All previous phases

| Task | Status | Notes |
|------|--------|-------|
| Verify Go backend compiles | todo | `go build ./cmd/server` (S-006) |
| Verify React frontend builds | todo | `npm run build` (S-007) |
| Run E2E test cases | todo | Against real WordPress site |
| Full workflow testing | todo | Create → Map → Sync → Publish |
| Cross-platform testing | todo | Windows/Mac/Linux |

---

## Next Task Selection

**Recommended Order:**

1. **Verify scaffolds compile** (S-006, S-007) - 15 min
   - `cd backend && go build ./cmd/server`
   - `npm run build`

2. **Phase 3: Site Service** (S-008) - 2 hours
   - Implement CreateSite with token encryption
   - Implement TestConnection to validate WP REST API
   - Implement UpdateSite and DeleteSite
   - Wire up API handlers

3. **Phase 4: Publish Service** (S-009) - 3 hours
   - Implement publish pipeline
   - Create ZIP packager
   - Add WP upload/activation

4. **Phase 6: Backup Service** - 2 hours
5. **Phase 7: WebSocket Updates** - 2 hours
6. **Phase 8: Integration Testing** - 2-3 hours

---

## Open Suggestions Summary

| ID | Title | Priority | Phase |
|----|-------|----------|-------|
| S-006 | Verify Go Backend Compiles | critical | Pre-Phase 3 |
| S-007 | Verify React Frontend Builds | critical | Pre-Phase 3 |
| S-008 | Implement Site Service | high | Phase 3 |
| S-009 | Implement Publish Service | high | Phase 4 |
| S-010 | WebSocket Real-time Updates | medium | Phase 7 |
| S-001 | Add WP API Error Examples | high | Spec update |
| S-004 | Error Recovery for Partial Publish | high | Spec update |
| S-005 | WebSocket Reconnection | medium | Spec update |

See `.lovable/memory/suggestions/01-suggestions-tracker.md` for full details.

---

## Quick Reference

| Resource | Location |
|----------|----------|
| Risk Report | `.lovable/memory/03-reliability-risk-report.md` |
| Project Context | `.lovable/memory/02-project-context.md` |
| Suggestions | `.lovable/memory/suggestions/01-suggestions-tracker.md` |
| Master Spec | `spec/wp-plugin-publish/00-overview.md` |
| Constants SSOT | `spec/wp-plugin-publish/66-shared-constants.md` |
| Implementation Plan | `spec/wp-plugin-publish/03-implementation/35-implementation-plan.md` |

---

*Phases 1, 2, 5 + E2E Testing complete. Continue with Phase 3: Site Service.*
