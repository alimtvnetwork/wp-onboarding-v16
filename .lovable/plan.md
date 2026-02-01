# Active Roadmap

> **Location:** `.lovable/plan.md`  
> **Updated:** 2026-02-01  
> **Purpose:** Prioritized backlog for AI implementation handoff

---

## Current Status

| Area | Status |
|------|--------|
| Specification | ✅ Complete (21/21 docs) |
| Go Backend Scaffold | ✅ Complete |
| React Frontend Scaffold | ✅ Complete |
| Full Implementation | 📝 TODO (Phase 5) |

---

## Phase 5: Implementation (Current)

### 5a: Verify Scaffolds ⏳

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| Confirm Go backend compiles | todo | - | Run `go build ./cmd/server` |
| Confirm React frontend builds | todo | - | Run `npm run build` |
| Confirm database migrates | todo | - | Start backend, check app.db created |
| Document scaffold fixes | todo | - | Record any issues found |

**Objective:** Ensure scaffolded code compiles/builds before adding implementation.  
**Dependencies:** None.  
**Expected Output:** Build passes, or list of fixes needed.

---

### 5b: Backend Core Services ⏳

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| Site Service - full CRUD | todo | - | Spec: `04-site-service.md` |
| Site Service - encryption | todo | - | AES-256-GCM for app passwords |
| Plugin Service - full CRUD | todo | - | Spec: `05-plugin-service.md` |
| Plugin Service - path validation | todo | - | Verify directory exists |
| WP REST Client - connection test | todo | - | Spec: `10-wp-rest-client.md` |
| WP REST Client - plugin list | todo | - | List installed plugins |
| Error logging to SQLite | todo | - | Spec: `13-error-management.md` |
| REST API handlers | todo | - | Spec: `11-rest-api-endpoints.md` |

**Objective:** Complete CRUD for sites/plugins, WP connection testing.  
**Dependencies:** 5a complete.  
**Expected Output:** API endpoints work via curl/Postman.

---

### 5c: File Watching & Sync ⏳

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| File Watcher - fsnotify | todo | - | Spec: `06-file-watcher.md` |
| File Watcher - debouncing | todo | - | 500ms default |
| Hash calculation (SHA256) | todo | - | For sync comparison |
| Sync Service - local diff | todo | - | Spec: `07-sync-service.md` |
| Sync Service - remote fetch | todo | - | Via WP REST client |
| WebSocket - file change events | todo | - | Spec: `12-websocket-events.md` |

**Objective:** Detect local file changes, compare with remote.  
**Dependencies:** 5b complete (Plugin Service, WP Client).  
**Expected Output:** WebSocket pushes file_change events.

---

### 5d: Publish & Backup ⏳

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| Backup Service - download | todo | - | Spec: `09-backup-service.md` |
| Backup Service - retention | todo | - | Clean old backups |
| Publish Service - zip creation | todo | - | Spec: `08-publish-service.md` |
| Publish Service - upload | todo | - | Via WP REST |
| Publish Service - activation | todo | - | Auto-activate after upload |
| Rollback functionality | todo | - | Restore from backup |

**Objective:** Complete publish and rollback workflows.  
**Dependencies:** 5c complete (Sync Service for diff).  
**Expected Output:** Full publish cycle works.

---

### 5e: Frontend Implementation ⏳

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| Site Manager UI - list | todo | - | Spec: `21-site-manager-ui.md` |
| Site Manager UI - form | todo | - | Add/Edit site |
| Site Manager UI - test connection | todo | - | Button + status display |
| Plugin Manager UI - list | todo | - | Spec: `22-plugin-manager-ui.md` |
| Plugin Manager UI - form | todo | - | Add/Edit plugin |
| Plugin Manager UI - directory picker | todo | - | May need native bridge |
| Sync Dashboard - file list | todo | - | Spec: `23-sync-dashboard.md` |
| Sync Dashboard - publish actions | todo | - | Single file / full plugin |
| Error Console - list | todo | - | Spec: `24-error-console.md` |
| Error Console - copy button | todo | - | Format for AI paste |
| Settings Page | todo | - | Spec: `25-settings-page.md` |
| WebSocket integration | todo | - | Real-time updates |

**Objective:** Complete React UI for all features.  
**Dependencies:** 5b-5d complete (API endpoints exist).  
**Expected Output:** Full UI functional.

---

### 5f: Integration Testing ⏳

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| E2E site connection test | todo | - | Add site → test → verify |
| E2E publish workflow | todo | - | Change file → publish → verify on WP |
| Error recovery scenarios | todo | - | Network failure, WP errors |
| Cross-platform testing | todo | - | Windows, macOS, Linux |

**Objective:** Verify full workflows work.  
**Dependencies:** 5e complete.  
**Expected Output:** All workflows pass.

---

## Next Task Selection

**Recommended next task (pick one):**

1. `5a: Verify Go backend compiles` - Critical first step
2. `5a: Verify React frontend builds` - Can run in parallel with above
3. `S-006: Verify Go Backend Compiles` (same as #1, from suggestions)

**Ask user:** Which task should be implemented next?

---

## Backlog (Future Phases)

| Phase | Description | Priority |
|-------|-------------|----------|
| 6 | Multi-plugin batch operations | medium |
| 7 | Plugin dependency tracking | low |
| 8 | Scheduled auto-sync | low |
| 9 | Webhook notifications | low |

---

## Suggestions Backlog

See `.lovable/memory/suggestions/01-suggestions-tracker.md` for AI-generated suggestions.

| ID | Title | Priority | Status |
|----|-------|----------|--------|
| S-001 | Add WP API error examples | high | open |
| S-002 | Document fsnotify platform differences | high | open |
| S-003 | Specify hash algorithm | medium | open |
| S-004 | Add error recovery for partial publish | high | open |
| S-005 | Define WS reconnection recovery | medium | open |
| S-006 | Verify Go backend compiles | critical | open |
| S-007 | Verify React frontend builds | critical | open |

---

## Completed

- ✅ Initial spec structure created (2026-02-01)
- ✅ Removed old specs (wp-plugin-builder, exam-manager, link-manager, powershell-integration)
- ✅ Phase 1-4 specs completed (2026-02-01)
- ✅ Go backend scaffolding (2026-02-01)
- ✅ React frontend scaffolding (2026-02-01)
- ✅ Database migrations defined (2026-02-01)
- ✅ API client and WebSocket client (2026-02-01)

---

*Update when starting/completing tasks.*
