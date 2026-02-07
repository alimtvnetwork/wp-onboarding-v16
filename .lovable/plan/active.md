# Active & Future Phases

**Updated: 2026-02-07**

---

## Recently Completed

- **Phase 9: Standardize Base Service Configs**: Added `Config` structs to `publishhistory` and `sitehealth` services (the only two that used raw parameters). Renamed `sitehealth.NewService()` → `sitehealth.New()` for consistency. All 10 services now follow the uniform `New(Config{...})` constructor pattern.
- **Phase 8: Unify ServiceRegistry Definitions**: Co-located all 11 service interfaces with their adapter implementations in domain-specific adapter files. Reduced `handlers.go` to a lean registry-only file (~45 lines).
- **Phase 7: Generic CRUD Handler Factory**: Created `handler_factory.go` with 7 reusable factory functions + nil-safe lazy service getters. Refactored ~30 handlers, eliminating ~300 lines of boilerplate.

---

## Backlog

### Phase 10: Real HTTP-based E2E Tests
Replace stub tests with actual HTTP request-based end-to-end test logic.

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
