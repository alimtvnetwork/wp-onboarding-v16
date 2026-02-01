# Active Roadmap

> **Location:** `.lovable/plan.md`  
> **Updated:** 2026-02-01  
> **Purpose:** Prioritized backlog for AI implementation handoff

---

## Current Status

| Area | Status |
|------|--------|
| Specification | ✅ Complete (27/27 docs) |
| Implementation Specs | ✅ Complete (6 new docs) |
| Go Backend Scaffold | ✅ Complete |
| React Frontend Scaffold | ✅ Complete |
| Full Implementation | 🔄 Phase 1 Ready |

---

## Implementation Specs Created

| Doc | Title | Location |
|-----|-------|----------|
| 30 | Plugin Service Impl | `03-implementation/30-plugin-service-impl.md` |
| 31 | Sync Service Impl | `03-implementation/31-sync-service-impl.md` |
| 32 | Publish Service Impl | `03-implementation/32-publish-service-impl.md` |
| 33 | Watcher Service Impl | `03-implementation/33-watcher-service-impl.md` |
| 34 | Git & Build Impl | `03-implementation/34-git-service-impl.md` |
| 35 | Implementation Plan | `03-implementation/35-implementation-plan.md` |

---

## Phase 1: Plugin Service (Current - Priority: HIGH)

**Duration:** 2-3 hours  
**Spec:** `03-implementation/30-plugin-service-impl.md`

| Task | Status | Notes |
|------|--------|-------|
| Create types.go | todo | CreateInput, UpdateInput, ScanResult |
| Implement crud.go | todo | List, GetByID, Create, Update, Delete |
| Implement scanner.go | todo | ScanDirectory, ValidatePath, findMainPluginFile |
| Implement mappings.go | todo | GetMappings, CreateMapping, DeleteMapping |
| Update database migrations | todo | Plugins, PluginMappings tables |
| Update API handlers | todo | Wire up /api/plugins endpoints |

**Deliverables:**
- Complete CRUD for plugins
- Directory scanning working
- Plugin-site mappings functional

---

## Phase 2: Sync Service (Next)

**Duration:** 2-3 hours  
**Spec:** `03-implementation/31-sync-service-impl.md`  
**Dependencies:** Phase 1

| Task | Status | Notes |
|------|--------|-------|
| Create types.go | todo | SyncResult, FileChange |
| Implement check.go | todo | CheckSync, CheckAllSites |
| Implement compare.go | todo | compareFiles (local vs remote) |
| Implement changes.go | todo | RecordFileChange, MarkSynced |
| Update database migrations | todo | FileChanges table |
| Update API handlers | todo | Wire up /api/sync endpoints |

---

## Phase 3: File Watcher Service

**Duration:** 2 hours  
**Spec:** `03-implementation/33-watcher-service-impl.md`  
**Dependencies:** Phase 1, Phase 2

| Task | Status | Notes |
|------|--------|-------|
| Implement scanner.go | todo | Full directory scan with hash |
| Implement watcher.go | todo | Main polling loop, debouncing |
| Implement service.go | todo | StartAll, StopPlugin, TriggerScan |
| Integration with Sync | todo | Call RecordFileChange on detect |

---

## Phase 4: Publish Service

**Duration:** 3 hours  
**Spec:** `03-implementation/32-publish-service-impl.md`  
**Dependencies:** Phase 1, Phase 2, Backup Service

| Task | Status | Notes |
|------|--------|-------|
| Implement types.go | todo | PublishOptions, PublishResult |
| Implement pipeline.go | todo | Orchestrate full pipeline |
| Implement packager.go | todo | Build ZIP with structure |
| Implement uploader.go | todo | Send to WP REST API |
| Update WordPress client | todo | UploadPlugin, ActivatePlugin |

---

## Phase 5: Git & Build Service

**Duration:** 2-3 hours  
**Spec:** `03-implementation/34-git-service-impl.md`  
**Dependencies:** Phase 1

| Task | Status | Notes |
|------|--------|-------|
| Implement pull.go | todo | Git pull single/batch |
| Implement build.go | todo | PowerShell/bash execution |
| Add PluginGitConfig table | todo | Per-plugin settings |
| Update API handlers | todo | /api/git endpoints |

---

## Phase 6: Integration & Testing

**Duration:** 2-3 hours  
**Dependencies:** All previous phases

| Task | Status | Notes |
|------|--------|-------|
| E2E workflow testing | todo | Create → Map → Sync → Publish |
| WebSocket verification | todo | All events broadcasting |
| Frontend integration | todo | Update hooks for real API |
| Documentation update | todo | README, API docs |

---

## Next Task Selection

**Recommended:** Start Phase 1 - Plugin Service Implementation

1. Begin with `types.go` - Define all input/output types
2. Then `crud.go` - CRUD operations
3. Then `scanner.go` - Directory scanning
4. Then `mappings.go` - Site mappings

**Command to test:** `cd backend && go build ./cmd/server`

---

## Completed

- ✅ Initial spec structure created (2026-02-01)
- ✅ Phase 1-4 specs completed (2026-02-01)
- ✅ Go backend scaffolding (2026-02-01)
- ✅ React frontend scaffolding (2026-02-01)
- ✅ Database migrations defined (2026-02-01)
- ✅ API client and WebSocket client (2026-02-01)
- ✅ Implementation specs created (2026-02-01)
  - 30-plugin-service-impl.md
  - 31-sync-service-impl.md
  - 32-publish-service-impl.md
  - 33-watcher-service-impl.md
  - 34-git-service-impl.md
  - 35-implementation-plan.md

---

*Start with Phase 1: Plugin Service Implementation*
