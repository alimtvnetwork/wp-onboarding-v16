# WP Plugin Publish - Enhancement Plan v2

**Updated: 2026-02-05**

---

## Previous Plan: Error Handling & Path Management ✅ COMPLETE

See [archive of previous plan](./memory/issues-fixed/00-index.md) for details on the completed audit.

---

## Current Plan: Major Feature Enhancements

This plan addresses multiple feature requests and improvements organized into phases.

---

## Phase 1: Session-Based Logging System (Backend Foundation) ✅ COMPLETE

**Priority: HIGH - Foundation for all other improvements**
**Completed: 2026-02-05**

### 1.1 Backend Session Manager ✅
- [x] Create `backend/internal/services/session/service.go`
- [x] Generate unique session IDs (UUID v4) for each operation
- [x] Session types: `connect`, `publish`, `sync`, `backup`, `bulk_publish`
- [x] Store session logs in `backend/data/sessions/{session_id}.log`
- [x] Auto-cleanup sessions older than 7 days

### 1.2 Session API Endpoints ✅
- [x] `GET /api/sessions` - List recent sessions (last 100)
- [x] `GET /api/sessions/{id}` - Get session details and logs
- [x] `GET /api/sessions/{id}/logs` - Stream session logs (tail mode)
- [x] `DELETE /api/sessions/{id}` - Clear specific session logs

### 1.3 WebSocket Session Integration ✅
- [x] Include `sessionId` in all WebSocket broadcast messages
- [x] Frontend can subscribe to specific session updates
- [x] Session logs persist even after WebSocket disconnection

### 1.4 Publish Service Integration ✅
- [x] Session created at start of publish operation
- [x] All logs written to session file
- [x] SessionID included in PublishResult response

### 1.4 Detailed Logging Enhancement
All logs must include:
- Timestamp (UTC ISO8601)
- Level (DEBUG, INFO, WARN, ERROR)
- Stage (connect, package, upload, activate, etc.)
- Message
- Context object with:
  - `what`: What is being done
  - `why`: Why it's being done  
  - `where`: Target URL/path
  - `result`: Outcome details
  - `innerStatus`: HTTP status, response snippet for API calls

---

## Phase 2: Quick Publish Feature (No Modal) ✅ COMPLETE

**Priority: HIGH - Core UX improvement**
**Status: COMPLETE**

### 2.1 Quick Publish Button ✅
- [x] Add "Quick Publish All" button on plugin cards (⚡ icon)
- [x] Publishes to ALL mapped sites without modal
- [x] Shows inline progress via QuickPublishIndicator
- [x] Status indicator appears on card during/after publish

### 2.2 Global Publish State Store (Zustand) ✅
- [x] Created `src/stores/publishStore.ts`
- [x] Track active operations: `{ pluginId, siteId, sessionId, status, progress, logs }`
- [x] State persists across route navigation
- [x] Auto-cleanup completed operations after 30 minutes

### 2.3 Publish Status Indicator ✅
- [x] Created `GlobalPublishProgress` component in header
- [x] Shows active publish count with progress percentage
- [x] Click to see sheet with all operations and details
- [x] Created `QuickPublishIndicator` for inline card display

### 2.4 Quick Publish Hook ✅
- [x] Created `src/hooks/useQuickPublish.ts`
- [x] `quickPublishAll(plugin)` - publish to all mapped sites
- [x] `quickPublishToSite(plugin, siteId, siteName, siteUrl)` - single site
- [x] WebSocket listener integration for real-time updates

---

## Phase 3: Fix Duplicate Plugin Issue (WordPress Side) ✅ COMPLETE

**Priority: HIGH - Critical Bug Fix**
**Status: COMPLETE**

### 3.1 Root Cause
The duplicate plugin issue occurred when:
1. ZIP extraction created a new folder with the ZIP's internal folder name
2. This name differed from the expected plugin slug
3. WordPress saw two separate plugin directories

**Example**: ZIP contains `Category Generator/` but target is `category-generator/`

### 3.2 Fix Applied
Updated `wp-plugins/riseup-asia-uploader/riseup-asia-uploader.php`:

- [x] Modified `handle_upload()` to extract to temp location first
- [x] Normalize folder name to match slug via rename
- [x] Added `copy_directory()` fallback for cross-device moves
- [x] Cleanup temp directory after extraction

**Technical Changes**:
1. Extract ZIP to `temp/extract_{uniqid}/`
2. Find extracted folder with `glob()`
3. Delete existing target folder if updating
4. Rename extracted folder to `WP_PLUGIN_DIR/$slug`
5. Cleanup temp extraction directory

---

## Phase 4: Remote Plugin Viewer (See Plugins on Site) ✅ COMPLETE

**Priority: MEDIUM - New Feature**
**Completed: 2026-02-05**

### 4.1 Site Card Enhancement ✅
- [x] Add "Plugins" button to SiteCard component
- [x] Opens modal/panel showing all plugins on that WordPress site

### 4.2 Remote Plugins Panel Component ✅
- [x] Create `src/components/sites/RemotePluginsPanel.tsx`
- [x] Table view: Plugin Name, Slug, Version, Status, Author
- [x] Actions per plugin:
  - Enable/Disable toggle
  - Delete (with confirmation)

### 4.3 Backend Implementation ✅
- [x] Added `DeletePlugin` method to WordPress client
- [x] Added remote plugin methods to site service:
  - `GetRemotePlugins(siteID)` - List all plugins
  - `EnableRemotePlugin(siteID, slug)` - Activate
  - `DisableRemotePlugin(siteID, slug)` - Deactivate
  - `DeleteRemotePlugin(siteID, slug)` - Remove
- [x] Added `ErrWPPluginDelete` error code

### 4.4 Backend API Endpoints ✅
- [x] `GET /api/v1/sites/{id}/remote-plugins` - Fetch plugins from site
- [x] `POST /api/v1/sites/{id}/remote-plugins/{plugin}/enable`
- [x] `POST /api/v1/sites/{id}/remote-plugins/{plugin}/disable`
- [x] `DELETE /api/v1/sites/{id}/remote-plugins/{plugin}`

---

## Phase 5: Separated Upload/Activate Stage Reporting ✅ COMPLETE

**Priority: MEDIUM - Better Error Visibility**
**Completed: 2026-02-05**

### 5.1 Enhanced Stage Logging ✅
- [x] Created `StageContext` struct with what/why/where/result fields
- [x] Implemented `broadcastStageLog()` for structured context logging
- [x] Added `runStageWithSession()` to integrate with session LogStageStart/End
- [x] Each stage logs clear request/response context

### 5.2 Stage Status Broadcasting ✅
- [x] Implemented `broadcastStageComplete()` for stage_complete events
- [x] Includes sessionId, stage name, status, duration, and details
- [x] Frontend can track individual stage completion

### 5.3 Helper Utilities ✅
- [x] `formatBytes()` - Human-readable file sizes in logs
- [x] `truncateString()` - Limit response body length (2000 chars max)

### 5.4 Clear Error Attribution ✅
- [x] Errors include which stage failed
- [x] Full request/response for failed API calls
- [x] Session logs contain complete diagnostic info with inner HTTP details

---

## Phase 6: Error Modal Integration with Session Logs ✅ COMPLETE

**Priority: MEDIUM - Debugging UX**
**Completed: 2026-02-05**

### 6.1 Session Logs Tab in Error Modal ✅
- [x] Add "Session" tab to GlobalErrorModal (7-tab interface)
- [x] Created `SessionLogsTab` component to fetch logs from backend
- [x] Shows loading state, error handling, and retry functionality
- [x] Downloadable as text file via Download button
- [x] Copy to clipboard functionality

### 6.2 Copy Full Report Enhancement ✅
Include in report:
- [x] Session ID with link to API endpoint
- [x] Session type (publish, sync, connect, etc.)
- [x] Session info section in generated report

### 6.3 API Functions Added ✅
- [x] `api.getSessions(limit)` - List recent sessions
- [x] `api.getSession(sessionId)` - Get session details
- [x] `api.getSessionLogs(sessionId)` - Fetch full logs
- [x] `api.deleteSession(sessionId)` - Remove session

### 6.4 Error Store Enhancement ✅
- [x] Added `sessionId` and `sessionType` to CapturedError interface
- [x] Updated `captureError` and `captureException` to accept session metadata

### 6.5 SessionLogsTab Features ✅
- [x] Syntax highlighting for stage headers, errors, warnings, success lines
- [x] Refresh, Copy, and Download buttons
- [x] Session ID and type badges in header
- [x] Line count and file size stats

---

## Phase 7: Specification & Memory Updates ✅ COMPLETE

**Priority: LOW - Documentation**
**Completed: 2026-02-05**

### 7.1 New Spec Files ✅
- [x] `spec/wp-plugin-publish/01-backend/17-session-management.md`
- [x] `spec/wp-plugin-publish/02-frontend/27-quick-publish.md`
- [x] `spec/wp-plugin-publish/02-frontend/28-remote-plugins.md`

### 7.2 Updated Spec Files ✅
- [x] `spec/wp-plugin-publish/01-backend/13-error-management.md` - Added session logging section
- [x] `spec/wp-plugin-publish/01-backend/14-logging-system.md` - Added detailed log format & stage context

### 7.3 New Memory Files ✅
- [x] `.lovable/memory/architecture/backend/session-logging.md`
- [x] `.lovable/memory/architecture/frontend/publish-state-management.md`
- [x] `.lovable/memory/features/remote-plugin-management.md`

---

## Phase 8: Publish Diff Preview ✅ COMPLETE

**Priority: MEDIUM - UX Improvement**
**Completed: 2026-02-05**

### 8.1 Backend Preview Endpoint ✅
- [x] `PreviewPublish(pluginID, siteID)` method in publish service
- [x] Returns file list with paths, sizes, and hashes
- [x] `GET /api/v1/plugins/{id}/sites/{siteId}/preview` endpoint

### 8.2 Frontend DiffPreviewDialog ✅
- [x] `src/components/plugins/DiffPreviewDialog.tsx`
- [x] File list with search and filter by change type
- [x] Summary stats (total files, size, added/modified/deleted)
- [x] Groups files by directory for readability

### 8.3 Integration ✅
- [x] Added preview button in publish dialog for each site
- [x] Preview flows into publish on confirmation
- [x] Created memory file: `.lovable/memory/features/publish/diff-preview.md`

---

## Implementation Order

| Order | Phase | Description | Dependencies | Est. Hours |
|-------|-------|-------------|--------------|------------|
| 1 | Phase 1 | Session-Based Logging | None | 4-6 |
| 2 | Phase 3 | Duplicate Plugin Fix | None | 1-2 |
| 3 | Phase 2 | Quick Publish | Phase 1 | 3-4 |
| 4 | Phase 5 | Stage Reporting | Phase 1 | 2-3 |
| 5 | Phase 6 | Error Modal Enhancement | Phase 1 | 2-3 |
| 6 | Phase 4 | Remote Plugin Viewer | None | 4-5 |
| 7 | Phase 7 | Documentation | All | 1-2 |
| 8 | Phase 8 | Publish Diff Preview | None | 2-3 |
| 9 | Phase 9 | Remote Plugins Caching | None | 3-4 |
| 10 | Phase 10 | Remote File Browser | Phase 9 | 4-6 |

**Total Estimated: 26-38 hours**

---

## Phase 9: Remote Plugins Caching System ✅ COMPLETE

**Priority: MEDIUM - Performance & UX**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 9.1 Backend Caching Table ✅
- [x] Created `RemotePluginsCache` table in SQLite (migration v6)
- [x] Fields: `id`, `SiteId`, `PluginsJSON`, `CachedAt`, `ExpiresAt`
- [x] Cache TTL: 60 minutes (configurable in config.json)

### 9.2 Config.json Settings ✅
- [x] Added `remotePlugins.cacheEnabled` (default: true)
- [x] Added `remotePlugins.cacheTTLMinutes` (default: 60)
- [x] Site service uses config values for cache behavior

### 9.3 Cache API Endpoints ✅
- [x] `GET /api/v1/sites/{id}/remote-plugins` - Returns cached if valid, else fetches fresh
- [x] `POST /api/v1/sites/{id}/remote-plugins/force-sync` - Clears cache, fetches fresh
- [x] `DELETE /api/v1/sites/{id}/remote-plugins/cache` - Clears cache only

### 9.4 Frontend Integration ✅
- [x] Added "Force Sync" button in RemotePluginsPanel
- [x] Shows last fetched timestamp with relative time
- [x] Force sync mutation bypasses cache and fetches fresh data
- [x] Visual feedback during sync operations

---

## Phase 10: Remote Plugin File Browser (TODO - Future)

**Priority: LOW - Advanced Feature**
**Status: TODO**

### 10.1 Backend
- [ ] `GET /api/v1/sites/{id}/remote-plugins/{slug}/files` - List plugin files
- [ ] `GET /api/v1/sites/{id}/remote-plugins/{slug}/file` - Get file content

### 10.2 WordPress Plugin Endpoint
- [ ] Uses existing `/plugins/{slug}/files` endpoint from Riseup Asia Uploader

### 10.3 Frontend
- [ ] `PluginFileBrowser` component with tree view
- [ ] File content viewer with syntax highlighting
- [ ] Download individual files or entire plugin as ZIP

---

## Open Questions

1. **Session Retention**: How long to keep session logs? (Suggested: 7 days) ✅ Implemented: 7 days
2. **Quick Publish Scope**: Publish to all mapped sites, or allow selecting subset? ✅ All mapped sites
3. **Remote Plugin Backups**: Store on WP site or download locally?
4. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
5. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?

---

## Technical Notes

### Duplicate Plugin Root Cause (Detailed)

Looking at `riseup-asia-uploader.php` lines 481-509:

```php
$target_dir = $plugins_dir . '/' . $slug;  // wp-content/plugins/category-generator
$is_update = is_dir($target_dir);

if ($is_update) {
    $this->delete_directory($target_dir);  // Removes old version
}

$zip->extractTo($plugins_dir);  // ← PROBLEM: Extracts ZIP's folder name
```

The `extractTo()` uses whatever folder name is in the ZIP archive. If the ZIP has `Category Generator/` but we expect `category-generator/`, both folders exist after extraction.

**Solution**: 
```php
// 1. Extract to temp
$temp_extract = $temp_dir . '/extract_' . uniqid();
$zip->extractTo($temp_extract);

// 2. Find extracted folder (whatever it's named)
$extracted_dirs = glob($temp_extract . '/*', GLOB_ONLYDIR);
if (empty($extracted_dirs)) {
    return $this->error_response('No folder in ZIP', 400);
}
$extracted_folder = $extracted_dirs[0];

// 3. Move to correct slug path
$target_dir = $plugins_dir . '/' . $slug;
if (is_dir($target_dir)) {
    $this->delete_directory($target_dir);
}
rename($extracted_folder, $target_dir);

// 4. Cleanup temp
$this->delete_directory($temp_extract);
```

---

## Suggestions for Improvement ✅ ALL IMPLEMENTED

All originally suggested improvements have been implemented:

1. ~~Retry Mechanism~~ → Phase 33 ✅
2. ~~Batch Publishing~~ → Phase 34 ✅
3. **Progress Persistence**: Save publish progress to localStorage (considered low priority - Zustand store persists across navigation)
4. ~~Publish Queue~~ → Phase 35 ✅
5. ~~Rollback on Failure~~ → Phase 38 ✅
6. ~~Diff View~~ → Phase 8 ✅
7. ~~Scheduled Publishing~~ → Phase 36 ✅

---

## Phase 11: WordPress Plugin Version Tracking ✅ COMPLETE

**Priority: HIGH**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 11.1 WordPress Plugin - Version Endpoint ✅

The existing `/plugins` endpoint already returns version info for all plugins.
No new endpoint needed - existing API is sufficient.

### 11.2 React - Display Versions in Publish Dialog ✅

- [x] Created `SiteVersionBadge` component for inline version display
- [x] Shows remote version → local version with arrow indicator
- [x] Displays "Install", "Upgrade", or "Downgrade" badges based on comparison
- [x] Integrated into Publish Dialog site list
- [x] Lazy loading with skeleton placeholders
- [x] Error handling with graceful fallback

**Files Created:**
- `src/components/publish/SiteVersionBadge.tsx`

**Files Modified:**
- `src/pages/Plugins.tsx` - Added SiteVersionBadge to publish dialog

---

## Phase 12: Auto-Update with 301 Redirect Support ✅ COMPLETE

**Priority: HIGH**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 12.1 Update Resolver Class ✅

Created `wp-plugins/riseup-asia-uploader/includes/class-update-resolver.php`:
- `resolve_url($url)` - Follows 301/302/307/308 redirects to find final URL
- `get_update_url($force)` - Returns cached or freshly resolved URL
- `clear_cache()` - Clears cached resolved URL
- `fetch_update_info($force)` - Fetches update metadata from server
- `test_connection()` - Tests connection and resolves URL
- Fallback logic: if cached URL fails, automatically re-resolves from master

### 12.2 WordPress Update System Integration ✅

- `check_for_plugin_update()` - Hooks into `pre_set_site_transient_update_plugins`
- `plugin_info()` - Hooks into `plugins_api` for "View Details" modal
- Compares versions and registers update in WordPress transient

### 12.3 Settings Page Update ✅

Added Auto-Update section to `templates/admin-settings.php`:
- Enable Auto-Update toggle
- Master Update URL input
- Cache Duration dropdown (1/7/14/30 days)
- Resolved URL (Cached) display with timestamp
- Last Check timestamp
- Last Error display (if any)
- Available Version with upgrade indicator
- Action buttons: Test Connection, Clear Cache, Check Now

### 12.4 AJAX Handlers ✅

Added to `class-admin.php`:
- `ajax_test_update_connection` - Tests and resolves URL
- `ajax_clear_update_cache` - Clears cached URL
- `ajax_check_for_updates` - Forces update check

### 12.5 Constants Added ✅

Added to `includes/constants.php`:
- `RISEUP_UPDATE_CACHE_DAYS_DEFAULT` (7)
- `RISEUP_UPDATE_MAX_REDIRECTS` (5)
- `RISEUP_ACTION_UPDATE_CHECK/RESOLVE/DOWNLOAD/INSTALL`

### 12.6 Version Bump ✅

Updated plugin version to 1.8.0

---

## Phase 13: Multi-Site Orchestration (Master-Agent) ✅ COMPLETE

**Priority: MEDIUM**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 13.1 Database Schema - Agent Sites Tables ✅

Added migration v2 to `class-database.php`:
- `agent_sites` table: id, name, url, username, app_password_encrypted, redirect_url, redirect_resolved, status, last_sync, last_error, created_at
- `agent_actions` table: id, agent_site_id, action, target_plugin, status, details, error_msg, created_at

### 13.2 Agent Manager Class ✅

Created `wp-plugins/riseup-asia-uploader/includes/class-agent-manager.php`:
- AES-256-GCM encryption for application passwords
- `add_agent()`, `update_agent()`, `remove_agent()`, `get_agent()`, `list_agents()`
- `api_request()` - Make authenticated requests to agent sites
- `test_connection()` - Verify agent connectivity
- `sync_plugins()` - Fetch plugin list from agent
- `execute_plugin_action()` - Enable/disable/delete plugins remotely
- `log_action()`, `get_action_history()` - Audit trail

### 13.3 REST Endpoints ✅

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/agents` | List all agent sites |
| POST | `/agents` | Add new agent site |
| GET | `/agents/{id}` | Get single agent |
| DELETE | `/agents/{id}` | Remove agent site |
| POST | `/agents/{id}/test` | Test connection |
| POST | `/agents/{id}/sync` | Sync plugins |
| POST | `/agents/{id}/action` | Execute plugin action |
| GET | `/agents/{id}/history` | Get action history |

### 13.4 Admin Dashboard ✅

Created `templates/admin-agents.php`:
- Add Agent form with name, URL, username, app password, redirect URL
- Agent sites table with status indicators
- Action buttons: Test, Sync, View Plugins, History, Remove
- Plugins modal for remote plugin management
- Action history modal

### 13.5 Constants Added ✅

- `RISEUP_TABLE_AGENT_SITES`, `RISEUP_TABLE_AGENT_ACTIONS`
- Agent action types and status values
- Agent REST endpoint constants

---

## Phase 14: Enhanced SQLite Logging for Plugin Actions ✅ COMPLETE

**Priority: MEDIUM**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 14.1 Enhanced Transaction Details ✅
- [x] Added `plugin_file` - Full plugin file path (e.g., "akismet/akismet.php")
- [x] Added `was_active` - Previous state before action (0/1 boolean)
- [x] Added `triggered_by` - Source: 'api', 'dashboard', 'agent_push', 'cron', 'cli'
- [x] Added `agent_site_id` - If triggered by master site

### 14.2 Constants Added ✅
- [x] `RISEUP_TRIGGERED_BY_API`, `RISEUP_TRIGGERED_BY_DASHBOARD`
- [x] `RISEUP_TRIGGERED_BY_AGENT`, `RISEUP_TRIGGERED_BY_CRON`, `RISEUP_TRIGGERED_BY_CLI`

### 14.3 Database Migration ✅
- [x] Migration v3 adds new columns to transactions table
- [x] Index on `triggered_by` for efficient queries
- [x] Backward compatible - existing code continues to work

### 14.4 Enhanced API ✅
- [x] Updated `log_transaction()` with optional `$enhanced` parameter
- [x] Added `log_enhanced_transaction()` convenience wrapper

---

## Phase 33: Publish Retry Mechanism ✅ COMPLETE

**Priority: HIGH**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 33.1 Retry Utility ✅
- [x] Created `backend/internal/services/publish/retry.go`
- [x] Generic `withRetry[T]()` function with exponential backoff
- [x] `isTransientError()` detects network timeouts, 5xx, 429, connection resets
- [x] Configurable: MaxAttempts (3), InitialDelay (2s), MaxDelay (30s), BackoffFactor (2.0)
- [x] Context-aware cancellation support

### 33.2 Upload Stage Integration ✅
- [x] Upload stage now uses `withRetry` wrapper
- [x] Retry attempts broadcast via WebSocket for real-time visibility
- [x] Retry summary logged to session with attempt count and total delay

---

## Phase 34: Batch Parallel Publishing ✅ COMPLETE

**Priority: MEDIUM**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 34.1 Frontend Concurrency-Limited Publishing ✅
- [x] Replaced sequential bulk deploy with `useBulkQuickPublish` hook
- [x] Configurable concurrency limit (default: 2 simultaneous publishes)
- [x] Uses `Promise.race` pattern for efficient task scheduling
- [x] Integrates with global publish store for real-time tracking

---

## Phase 35: Publish Queue System ✅ COMPLETE

**Priority: MEDIUM**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 35.1 Backend Queue ✅
- [x] Created `backend/internal/services/publish/queue.go`
- [x] `PublishQueue` with configurable max concurrency and queue size
- [x] Semaphore-based concurrency control
- [x] Priority-based processing (higher priority items processed first)
- [x] Queue status broadcast via WebSocket
- [x] Batch enqueue support
- [x] Graceful shutdown with WaitGroup

---

## Phase 36: Scheduled Publishing ✅ COMPLETE

**Priority: LOW**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 36.1 Backend Scheduler ✅
- [x] Created `backend/internal/services/publish/scheduler.go`
- [x] `PublishScheduler` with timer-based job execution
- [x] Schedule formats: `daily:HH:MM`, `weekly:DAY:HH:MM`, `interval:MINUTES`
- [x] Timezone support via `time.LoadLocation`
- [x] Job CRUD: Add, Remove, Toggle enable/disable, List, Get
- [x] Integrates with PublishQueue for rate-limited execution
- [x] WebSocket notifications for job start/complete/update
- [x] Graceful shutdown

---

## Phase 37: Bulk Quick Publish ✅ COMPLETE

**Priority: MEDIUM**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 37.1 Frontend Hook ✅
- [x] Created `src/hooks/useBulkQuickPublish.ts`
- [x] `bulkQuickPublish(plugins, { concurrency })` - deploy multiple plugins
- [x] Filters out plugins without mappings or already publishing
- [x] Registers all operations in global publish store
- [x] Summary toast with success/failure counts

### 37.2 Plugins Page Integration ✅
- [x] Updated `handleBulkDeploy` to use `useBulkQuickPublish` hook
- [x] Replaced sequential publish loop with concurrency-controlled bulk publish
- [x] "Deploy All" button now uses efficient parallel execution

---

## Phase 38: Rollback on Failure ✅ COMPLETE

**Priority: HIGH**
**Status: COMPLETE**
**Completed: 2026-02-06**

### 38.1 WordPress Plugin - Export Plugin Endpoint ✅
- [x] Added `RISEUP_ENDPOINT_PLUGIN_EXPORT` constant (`plugins/{slug}/export`)
- [x] Added `RISEUP_ACTION_EXPORT_PLUGIN` constant
- [x] Created `handle_export_plugin()` handler - exports any plugin as base64 ZIP
- [x] Route registered with slug validation and permission checks
- [x] Uses `RiseupPathUtils` for safe path handling
- [x] Version bumped to 1.10.0

### 38.2 Go Backend - ExportPlugin Client Method ✅
- [x] Added `EndpointExportPlugin` and `ActionExportPlugin` constants
- [x] Created `ExportPluginResult` struct
- [x] Created `ExportPlugin(slug)` method on WordPress client

### 38.3 Publish Pipeline Rollback Logic ✅
- [x] Added `RollbackOnFailure` option to `PublishOptions` (default: true)
- [x] Added `RollbackStatus` and `RollbackMessage` to `PublishResult`
- [x] Pre-upload: exports remote plugin as backup ZIP before overwriting
- [x] On activation failure: deactivates broken plugin, re-uploads backup ZIP
- [x] Full session logging with structured stage context for rollback operations
- [x] Graceful degradation if export unavailable (rollback skipped with warning)

---

*Last Updated: 2026-02-06*
