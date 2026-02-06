# Active & Future Phases

**Updated: 2026-02-06**

---

## Recently Completed

- **Publish History Integration**: Wired `publishHistoryService.Record()` into publish pipeline; wired SiteHealth + PublishHistory into main.go and service registry.
- **Phase 10: Remote Plugin File Browser**: Browse files button in RemotePluginsPanel, tree view with syntax highlighting.

---

## Backlog

### Plan Refactoring ✅ COMPLETE
Break 748-line plan.md into smaller focused files.

### Service Registry Cleanup ✅ COMPLETE
Refactored 630-line `adapters.go` into 8 focused files:
- `adapter_site.go` - Site service adapter
- `adapter_plugin.go` - Plugin service adapter
- `adapter_sync.go` - Sync + Watcher adapters
- `adapter_publish.go` - Publish + Backup adapters
- `adapter_session.go` - Session + ErrorHistory adapters
- `adapter_history.go` - PublishHistory + SiteHealth adapters
- `adapter_helpers.go` - Input conversion helpers
- `adapter_registry.go` - NewServiceRegistry factory
- `adapters.go` - Compile-time interface checks only

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
