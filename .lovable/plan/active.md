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

### Service Registry Cleanup (TODO - Future)
**Priority: LOW**
Refactor `adapters.go` (630+ lines) into per-service adapter files.

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
