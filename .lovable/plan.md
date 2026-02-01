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

### Phase 2: Core Backend (TODO)
- [ ] Site service (CRUD, connection testing)
- [ ] Plugin service (directory registration, hash calculation)
- [ ] WP REST API client
- [ ] REST API endpoints
- [ ] WebSocket events

### Phase 3: Sync & Publish (TODO)
- [ ] File watcher (fsnotify integration)
- [ ] Sync service (local vs remote comparison)
- [ ] Publish service (zip creation, upload)
- [ ] Backup service (download, rollback)

### Phase 4: Frontend UI (TODO)
- [ ] Site manager UI
- [ ] Plugin manager UI
- [ ] Sync dashboard
- [ ] Settings page

---

## Spec Documents Status

| Status | Count | Description |
|--------|-------|-------------|
| ✅ Complete | 10 | Ready for implementation |
| 📝 TODO | 11 | Needs detailed spec |

See `spec/wp-plugin-publish/99-consistency-report.md` for full document checklist.

---

## Completed

- Initial spec structure created (2026-02-01)
- Removed old specs (wp-plugin-builder, exam-manager, link-manager, powershell-integration)

---

*Update when starting/completing tasks.*
