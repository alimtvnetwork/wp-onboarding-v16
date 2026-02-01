# Active Roadmap

> **Location:** `.lovable/plan.md`  
> **Updated:** 2026-02-01

---

## WP Plugin Publish — Implementation Phases

### Phase 1: Foundation ✅ (Spec Complete)
- [x] Overview and architecture design
- [x] Go project structure
- [x] Database schema (SQLite)
- [x] Config system (JSON seed + SQLite runtime)
- [x] Error management system
- [x] Logging system
- [x] Shared constants (SSOT)
- [x] Frontend overview
- [x] Error console UI

### Phase 2: Core Backend ✅ (Spec Complete)
- [x] Site service (CRUD, connection testing)
- [x] Plugin service (directory registration, hash calculation)
- [x] WP REST API client
- [x] REST API endpoints
- [x] WebSocket events

### Phase 3: Sync & Publish ✅ (Spec Complete)
- [x] File watcher (fsnotify integration)
- [x] Sync service (local vs remote comparison)
- [x] Publish service (zip creation, upload)
- [x] Backup service (download, rollback)

### Phase 4: Frontend UI ✅ (Spec Complete)
- [x] Site manager UI
- [x] Plugin manager UI
- [x] Sync dashboard
- [x] Settings page

### Phase 5: Implementation (TODO)
- [ ] Go backend scaffolding
- [ ] Database migrations
- [ ] Core services implementation
- [ ] REST API handlers
- [ ] React UI implementation
- [ ] Integration testing

---

## Spec Documents Status

| Status | Count | Description |
|--------|-------|-------------|
| ✅ Complete | 21 | Ready for implementation |
| 📝 TODO | 0 | All specs complete |

See `spec/wp-plugin-publish/99-consistency-report.md` for full document checklist.

---

## Completed

- Initial spec structure created (2026-02-01)
- Removed old specs (wp-plugin-builder, exam-manager, link-manager, powershell-integration)
- Phase 2-4 specs completed (2026-02-01)

---

*Update when starting/completing tasks.*
