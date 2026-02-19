# PHP–Go Consistency Audit & Fix Plan

## Part 1: Repeated Constants in PHP (Riseup Asia Uploader)

### Issue 1.1 — `Database::DEFAULT_LIMIT` / `MAX_LIMIT` duplicates `PaginationConfigType`
- **File:** `includes/Database/Database.php` (lines 40–41)
- `public const DEFAULT_LIMIT = 50;` duplicates `PaginationConfigType::DefaultLimit->value` (50)
- `public const MAX_LIMIT = 1000;` conflicts with `PaginationConfigType::MaxLimit->value` (500) — **VALUE MISMATCH**
- **Consumers:** `DatabaseQuerySearchTrait` references `self::DEFAULT_LIMIT` / `self::MAX_LIMIT`
- **Fix:** Remove class constants, replace with `PaginationConfigType::DefaultLimit->value` and `PaginationConfigType::MaxLimit->value`. Decide canonical MaxLimit (500 vs 1000).

### Issue 1.2 — Hardcoded `$perPage = 50` in admin traits
- **Files:**
  - `includes/Admin/Traits/AdminPagesTrait.php` (line 27): `$perPage = 50;`
  - `includes/Admin/Traits/AdminErrorRenderTrait.php` (line 84): `$perPage = 50;`
- **Fix:** Replace with `PaginationConfigType::DefaultLimit->value`.

### Issue 1.3 — Hardcoded `$limit = 50` in snapshot/agent traits
- **Files:**
  - `includes/Agent/Traits/AgentLoggingTrait.php` (line 67): `int $limit = 50`
  - `includes/Snapshot/SnapshotProviderInterface.php` (line 36): `int $limit = 50`
  - `includes/Snapshot/SnapshotProviderWpReset.php` (line 56): `int $limit = 50`
  - `includes/Snapshot/Traits/ManagerCoreTrait.php` (line 69): `int $limit = 50`
  - `includes/Snapshot/Traits/NativeSnapshotCrudTrait.php` (line 111): `int $limit = 50`
- **Fix:** Replace all with `PaginationConfigType::DefaultLimit->value`.

### Issue 1.4 — Hardcoded HTTP status codes (non-enum)
- **Files:**
  - `includes/ErrorHandling/ErrorResponse.php` (lines 59, 78): `int $status = 500`
  - `includes/Helpers/Traits/EnvelopeFactoryTrait.php` (lines 20, 31): `int $code = 200`, `int $code = 500`
  - `includes/Traits/Snapshot/SnapshotBackupExecTrait.php`: `201`, `500`, `400`
- **Fix:** Replace with `HttpStatusType::ServerError->value`, `HttpStatusType::Ok->value`, etc.

### Issue 1.5 — `MESSAGE_TRUNCATE_LENGTH = 500` in FatalErrorHandler
- **File:** `includes/ErrorHandling/FatalErrorHandler.php` (line 23)
- Not in any enum. Could be added to a config enum or left as a private class constant (acceptable per coding standards since it's used only internally).
- **Fix:** Leave as private class constant (compliant with `internal-class-constants` standard).

---

## Part 2: PHP ↔ Go Endpoint Inconsistencies

### Issue 2.1 — Go has `EndpointUploadActive` (`/upload-active`) — PHP has NO equivalent
- **Go:** `endpoint_type.go` line 25: `EndpointUploadActive = "/upload-active"`
- **PHP:** Not in `EndpointType.php`, not registered in any route trait
- **Impact:** Go can call `/upload-active` on a WordPress site but the endpoint doesn't exist
- **Fix:** Either add `UploadActive` to PHP `EndpointType` and register the route, or remove from Go if unused.

### Issue 2.2 — Go has `EndpointPluginInfo` (`/plugins/info`) — PHP has NO equivalent
- **Go:** `endpoint_type.go` line 31: `EndpointPluginInfo = "/plugins/info"`
- **PHP:** Not in `EndpointType.php`, not registered
- **Fix:** Same as 2.1 — add to PHP or remove from Go.

### Issue 2.3 — PHP `ErrorLogs` and `ErrorSessions` endpoints exist in `EndpointType` but are NEVER REGISTERED
- **PHP EndpointType:** `ErrorLogs = 'error-logs'`, `ErrorSessions = 'error-sessions'`
- **Handlers exist:** `ErrorLogHandlerTrait::handleErrorLogs()`, `ErrorSessionHandlerTrait::handleErrorSessions()`
- **Route registration:** MISSING — neither `RouteRegistrationTrait` nor any sub-trait registers these
- **Go:** Has corresponding `EndpointErrorLogs` and `EndpointErrorSessions` in `endpoint_type.go`
- **Fix:** Add route registration for both in `RouteRegistrationTrait::registerLogRoutes()`.

### Issue 2.4 — PHP `AgentAction` and `AgentHistory` endpoints exist in `EndpointType` but are NOT registered
- **PHP EndpointType:** `AgentAction = 'agents/action'`, `AgentHistory = 'agents/history'`
- **Handlers exist:** `AgentHandlerActionTrait::handleAgentAction()`, `AgentHandlerActionTrait::handleAgentHistory()`
- **Route registration:** MISSING — `registerAgentRoutes()` only registers: Agents, AgentsAdd, AgentsRemove, AgentsTest, AgentsSync, AgentsPlugins
- **Fix:** Add `AgentAction` and `AgentHistory` to `registerAgentRoutes()`.

### Issue 2.5 — Go `endpoint_map.go` is missing several snapshot operations
- **Go GoEndpointMap** only has 13 entries (core + plugin operations)
- **Missing from GoEndpointMap:** All snapshot proxy operations (list, schedule, info, delete, restore, export, settings, providers, tables, full-backup, incremental, import, cleanup, download, progress, download-file, dependencies, export-pertable)
- **Impact:** Low — Go uses direct service calls for remote snapshots, not the endpoint map. But the map should be complete for diagnostics/logging.
- **Fix:** Phase 2 — extend `GoEndpointMap` and `WPEndpointMap` with snapshot entries.

### Issue 2.6 — Go snapshot routes use URL path params (`{snapshotId}`) but PHP uses JSON body params
- **Go router:** `/sites/{id}/snapshots/{snapshotId}` (GET, DELETE), `/sites/{id}/snapshots/{snapshotId}/restore`, `/sites/{id}/snapshots/{snapshotId}/export`
- **PHP:** All snapshot endpoints use POST with `snapshot_id` in JSON body (no URL params)
- **Impact:** When Go proxies to PHP, it must translate URL path params to JSON body. Verify the site service does this correctly.
- **Fix:** Audit the Go site service snapshot proxy methods to ensure proper param translation.

### Issue 2.7 — Go snapshot settings uses `PUT` but PHP uses `POST`
- **Go router:** `PUT /sites/{id}/snapshots/settings` → `UpdateRemoteSnapshotSettings`
- **PHP:** `SnapshotSettings` registered with both GET and POST (not PUT)
- **Impact:** If Go forwards as PUT, PHP will reject it (method not allowed)
- **Fix:** Either add PUT to PHP's SnapshotSettings registration, or change Go to use POST.

### Issue 2.8 — Go `DefaultLimit = 50` and `MaxLimit = 500` in `constants.go` vs PHP
- **Go:** `DefaultLimit = 50`, `MaxLimit = 500`
- **PHP PaginationConfigType:** `DefaultLimit = 50`, `MaxLimit = 500`
- **PHP Database class:** `DEFAULT_LIMIT = 50`, `MAX_LIMIT = 1000` ← **MISMATCH**
- **Fix:** Part of Issue 1.1 — resolve to 500 (match Go and enum).

---

## Implementation Phases

### Phase 1: Constant Deduplication (Low Risk)
1. Remove `Database::DEFAULT_LIMIT` and `Database::MAX_LIMIT`, replace with `PaginationConfigType` enum values
2. Replace all hardcoded `$limit = 50` defaults with `PaginationConfigType::DefaultLimit->value`
3. Replace hardcoded `$perPage = 50` in admin traits

### Phase 2: Missing Route Registration (Medium Risk) ✅ COMPLETED
1. ✅ Register `ErrorLogs` and `ErrorSessions` in `registerLogRoutes()`
2. ✅ Register `AgentAction` and `AgentHistory` in `registerAgentRoutes()`
3. ✅ Fix `AgentsPlugins` handler mismatch (was mapped to `handleAgentAction`, corrected to `handleSyncAgent`)

### Phase 3: Endpoint Parity (Medium Risk) ✅ COMPLETED
1. ✅ Added `UploadActive` to PHP `EndpointType` + route registration + `handleUploadActive` handler
2. ✅ Added `PluginInfo` to PHP `EndpointType` + route registration + `handlePluginInfo` handler
3. ✅ Fixed snapshot settings to accept both POST and PUT methods

### Phase 4: Endpoint Map Completeness (Low Priority) ✅ COMPLETED
1. ✅ Added all snapshot entries (15 operations) to both `GoEndpointMap` and `WPEndpointMap`
2. ✅ Added `UploadActive`, `PluginInfo`, `ErrorLogs`, `ErrorSessions` to both maps
3. ✅ Audited Go snapshot proxy param translation — confirmed correct: Go router extracts `{snapshotId}` from URL path, service passes it as `int64` to WP client, which sends it in JSON body to PHP fixed endpoints. No translation bug found.

### Phase 5: HTTP Status Code Enum Migration (Low Risk)
1. Replace hardcoded status codes in ErrorResponse, EnvelopeFactoryTrait, SnapshotBackupExecTrait
