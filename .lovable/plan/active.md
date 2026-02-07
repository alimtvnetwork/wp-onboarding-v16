# Active & Future Phases

**Updated: 2026-02-07**

---

## Recently Completed

- **Phase 8: Unify ServiceRegistry Definitions**: Co-located all 11 service interfaces with their adapter implementations in domain-specific adapter files (`adapter_site.go`, `adapter_plugin.go`, `adapter_sync.go`, `adapter_publish.go`, `adapter_session.go`, `adapter_history.go`). Reduced `handlers.go` to a lean registry-only file (~45 lines) containing just `ServiceRegistry` struct, global `Services` var, and Health/APIIndex handlers. Removed duplicate interface declarations from handler files (`sessions.go`, `error_history_handlers.go`, `publish_history_handlers.go`, `site_health_handlers.go`).
- **Phase 7: Generic CRUD Handler Factory**: Created `handler_factory.go` with 7 reusable factory functions (`handleActionByID`, `handleDeleteByID`, `handleListNilSafe`, `handleSiteActionByID`, `handleSiteActionByIDWithOpts`, `handleNoArgs`, `handleTwoIDs`) + nil-safe lazy service getters. Refactored ~30 handlers across 7 files to use factories, eliminating ~300 lines of boilerplate.
- **Publish History Integration**: Wired `publishHistoryService.Record()` into publish pipeline; wired SiteHealth + PublishHistory into main.go and service registry.
- **Phase 10: Remote Plugin File Browser**: Browse files button in RemotePluginsPanel, tree view with syntax highlighting.

---

## Backlog

### Phase 9: Standardize Base Service Configs
Normalize service initialization patterns and configuration across all services.

### Phase 10: Real HTTP-based E2E Tests
Replace stub tests with actual HTTP request-based end-to-end test logic.

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
