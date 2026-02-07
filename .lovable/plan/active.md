# Active & Future Phases

**Updated: 2026-02-07**

---

## All 10 Phases Complete ✅

### Phase 1: Semantic Boolean Helpers (PHP)
Replaced raw negations with `RiseupBooleanHelpers` for readable conditional logic.

### Phase 2: Idempotent Initialization (PHP)
Introduced `RiseupInitHelpers` for safe directory/database setup with retry logic.

### Phase 3: Structured Path Handling (PHP)
Created `RiseupPathUtils` centralizing all filesystem paths as method-based accessors.

### Phase 4: Manifest-Based Dependency Loading (PHP)
Built `RiseupDependencyLoader` for declarative file inclusion with error trapping.

### Phase 5: CODING-GUIDELINES.md (PHP)
Codified 11 mandatory standards into a formal reference document.

### Phase 6: Go Backend Handler Modularization
Split 2116-line `handlers.go` into 7 domain-specific files with shared DRY helpers (`requireService`, `decodeJSON`, `parseID`).

### Phase 7: Generic CRUD Handler Factory
Created `handler_factory.go` with 7 reusable factory functions and lazy service resolution, eliminating ~300 lines of boilerplate across ~30 handlers.

### Phase 8: Unify ServiceRegistry Definitions
Co-located all 11 service interfaces with their adapter implementations in domain-specific adapter files. Reduced `handlers.go` to a lean ~45-line registry-only file.

### Phase 9: Standardize Base Service Configs
Added `Config` structs to `publishhistory` and `sitehealth` services. All 10+ services now follow the uniform `New(Config{...})` constructor pattern.

### Phase 10: Real HTTP-based E2E Tests
Replaced stub test implementations with real HTTP-based test logic. Created `http_client.go` (API client wrapper), `test_implementations.go` (13 real test functions covering Plugin CRUD, Site Connections, Sync Operations, and Publish Flow). Added `E2EConfig` to `config.go` and wired service into `main.go` with adapter pattern. Tests make actual HTTP requests to verify complete API workflows.

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
