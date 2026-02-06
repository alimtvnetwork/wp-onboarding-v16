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

## Phase 9: Remote Plugins Caching System (PENDING)

**Priority: MEDIUM - Performance & UX**
**Status: PENDING**

### 9.1 Backend Caching Table
- [ ] Create `remote_plugins_cache` table in SQLite
- [ ] Fields: `id`, `site_id`, `plugins_json`, `cached_at`, `expires_at`
- [ ] Cache TTL: 1 hour (configurable in config.json)

### 9.2 Config.json Settings
- [ ] Add `remotePlugins.cacheEnabled` (default: true)
- [ ] Add `remotePlugins.cacheTTLMinutes` (default: 60)
- [ ] Settings UI toggle for cache enable/disable

### 9.3 Cache API Endpoints
- [ ] `GET /api/v1/sites/{id}/remote-plugins` - returns cached if valid, else fetches fresh
- [ ] `POST /api/v1/sites/{id}/remote-plugins/force-sync` - clears cache, fetches fresh
- [ ] `DELETE /api/v1/sites/{id}/remote-plugins/cache` - clears cache only

### 9.4 Frontend Integration
- [ ] Add "Force Sync" button in RemotePluginsPanel
- [ ] Show cache indicator (cached vs live data)
- [ ] Settings page toggle for caching

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

## Suggestions for Improvement

Based on analyzing the codebase, here are additional improvements to consider:

1. **Retry Mechanism**: Add automatic retry for transient network failures during publish
2. **Batch Publishing**: Publish same plugin to multiple sites in parallel (currently sequential)
3. **Progress Persistence**: Save publish progress to localStorage so refresh doesn't lose state
4. **Publish Queue**: Queue publish operations instead of parallel to prevent WordPress overload
5. **Rollback on Failure**: If activation fails, offer to restore from backup automatically
6. **Diff View**: Before publishing, show diff of what files will change
7. **Scheduled Publishing**: Schedule publish for off-peak hours

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

## Phase 12: Auto-Update with 301 Redirect Support

**Priority: HIGH**
**Status: PENDING**
**Estimated: 4 hours**

### 12.1 Database Schema - Update Settings

Add new table for auto-update configuration in WordPress SQLite:

```sql
CREATE TABLE IF NOT EXISTS update_settings (
    id INTEGER PRIMARY KEY,
    master_url TEXT NOT NULL,           -- Original 301 redirect URL
    resolved_url TEXT,                  -- Cached resolved URL
    resolved_at TEXT,                   -- When URL was resolved
    cache_days INTEGER DEFAULT 7,       -- Days to cache resolved URL
    last_check TEXT,                    -- Last update check timestamp
    last_error TEXT,                    -- Last error message
    enabled INTEGER DEFAULT 0           -- Auto-update enabled
);
```

### 12.2 WordPress Plugin - Update Resolver Class

- [ ] Create `class-update-resolver.php`
- [ ] `resolve_url($master_url)` - Resolve 301 redirect to final URL
- [ ] `get_update_url()` - Get cached or resolve fresh URL
- [ ] `clear_cache()` - Clear cached URL
- [ ] `check_for_update()` - Check for updates using resolved URL
- [ ] `install_update()` - Download and install update

### 12.3 WordPress Plugin - Settings Page Update

Add Auto-Update section:
- [ ] Master Update URL (text input)
- [ ] Cached/Resolved URL (read-only display)
- [ ] Cache Duration (dropdown: 1/7/14/30 days)
- [ ] [Clear Cache] button
- [ ] [Check Now] button
- [ ] Enable Auto-Update (toggle)

### 12.4 WordPress Plugin - Update Hook Integration

- [ ] Hook into `pre_set_site_transient_update_plugins`
- [ ] Hook into `plugins_api` filter

---

## Phase 13: Multi-Site Orchestration (Master-Agent)

**Priority: MEDIUM**
**Status: PENDING**
**Estimated: 6 hours**

### 13.1 Database Schema - Agent Sites Table

```sql
CREATE TABLE IF NOT EXISTS agent_sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    username TEXT NOT NULL,
    app_password_encrypted TEXT NOT NULL,
    redirect_url TEXT,                  -- Optional 301 redirect URL
    redirect_resolved TEXT,             -- Cached resolved URL
    status TEXT DEFAULT 'pending',      -- pending, connected, error
    last_sync TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS agent_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_site_id INTEGER NOT NULL,
    action TEXT NOT NULL,               -- enable, disable, update, sync
    target_plugin TEXT,
    status TEXT NOT NULL,               -- pending, success, failed
    details TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (agent_site_id) REFERENCES agent_sites(id)
);
```

### 13.2 WordPress Plugin - Agent Manager Class

- [ ] Create `class-agent-manager.php`
- [ ] `add_agent($url, $username, $password, $redirect_url)` - Onboard agent
- [ ] `remove_agent($id)` - Remove agent
- [ ] `list_agents()` - List all agents with status
- [ ] `test_connection($id)` - Test connection to agent
- [ ] `execute_action($agent_id, $action, $plugin_slug)` - Execute action on agent
- [ ] `sync_agent($id)` - Sync status from agent
- [ ] `push_update($agent_id, $plugin_slug, $zip_data)` - Push update to agent

### 13.3 WordPress Plugin - Agent REST Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/agents` | List all agent sites |
| POST | `/agents` | Add new agent site |
| DELETE | `/agents/{id}` | Remove agent site |
| POST | `/agents/{id}/test` | Test agent connection |
| POST | `/agents/{id}/sync` | Sync agent status |
| POST | `/agents/{id}/action` | Execute action on agent |

### 13.4 WordPress Admin - Agent Management Page

- [ ] Add new admin submenu "Agent Sites"
- [ ] Table listing all agents (name, URL, status, last sync)
- [ ] Add Agent form (URL, username, app password, redirect URL)
- [ ] Action buttons (Test, Sync, Remove)
- [ ] Remote plugin list per agent
- [ ] Bulk actions (Update All, Enable/Disable)

---

## Phase 14: Enhanced SQLite Logging for Plugin Actions

**Priority: MEDIUM**
**Status: PENDING**
**Estimated: 1 hour**

### 14.1 Enhanced Transaction Details

Add more detail to logged transactions:
- [ ] `plugin_file` - Full plugin file path
- [ ] `was_active` - Previous state before action
- [ ] `triggered_by` - Source: 'api', 'dashboard', 'agent_push'
- [ ] `agent_site_id` - If triggered by master site

---

## Implementation Priority

| Phase | Description | Priority | Est. Hours |
|-------|-------------|----------|------------|
| 11 | Version Tracking | HIGH | 2 |
| 12 | Auto-Update 301 | HIGH | 4 |
| 14 | Enhanced Logging | MEDIUM | 1 |
| 13 | Multi-Site Orchestration | MEDIUM | 6 |

**Total: ~13 hours**

---

*Last Updated: 2026-02-06*
