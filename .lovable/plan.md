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

## Phase 4: Remote Plugin Viewer (See Plugins on Site)

**Priority: MEDIUM - New Feature**
**Estimated: 4-5 hours**

### 4.1 Site Card Enhancement
- [ ] Add "See Plugins" button to SiteCard component
- [ ] Opens modal/panel showing all plugins on that WordPress site

### 4.2 Remote Plugins Panel Component
- [ ] Create `src/components/sites/RemotePluginsPanel.tsx`
- [ ] Table view: Plugin Name, Slug, Version, Status, Author
- [ ] Actions per plugin:
  - Enable/Disable toggle
  - Delete (with confirmation)
  - Backup (download ZIP)
  - Restore (from backup)

### 4.3 WordPress Plugin Endpoints (New)
Add to `riseup-asia-uploader.php`:
- [ ] `POST /plugins/{slug}/enable` - Activate a plugin
- [ ] `POST /plugins/{slug}/disable` - Deactivate a plugin
- [ ] `DELETE /plugins/{slug}` - Delete a plugin
- [ ] `GET /plugins/{slug}/export` - Download plugin as ZIP

### 4.4 Backend Proxy Endpoints
- [ ] `GET /api/sites/{id}/remote-plugins` - Fetch plugins from site
- [ ] `POST /api/sites/{id}/remote-plugins/{slug}/enable`
- [ ] `POST /api/sites/{id}/remote-plugins/{slug}/disable`
- [ ] `DELETE /api/sites/{id}/remote-plugins/{slug}`

---

## Phase 5: Separated Upload/Activate Stage Reporting

**Priority: MEDIUM - Better Error Visibility**
**Estimated: 2-3 hours**
**Depends on: Phase 1**

### 5.1 Enhanced Stage Logging
Each stage logs with clear separation:
```
═══════════════════════════════════════════════════
 STAGE: UPLOAD
═══════════════════════════════════════════════════
[INFO] Starting upload to https://example.com
[INFO] Sending ZIP (143KB) to /riseup-asia-uploader/v1/upload
[DEBUG] Request: { method: POST, contentType: multipart/form-data }
[DEBUG] Payload: { slug: category-generator, activate: true }
[INFO] Response received in 4.2s
[DEBUG] Response: { success: true, is_update: true, activated: true }
[SUCCESS] ✓ Upload completed (plugin activated during upload)

═══════════════════════════════════════════════════
 STAGE: ACTIVATE (skipped - already activated)
═══════════════════════════════════════════════════
```

### 5.2 Stage Status Broadcasting
```json
{
  "type": "stage_complete",
  "sessionId": "abc-123",
  "stage": "upload",
  "status": "success",
  "duration": 4200,
  "details": { 
    "zipSize": 143386, 
    "overwritten": true,
    "activated": true 
  }
}
```

### 5.3 Clear Error Attribution
- [ ] Errors clearly show which stage failed
- [ ] Include full request/response for failed API calls
- [ ] Session logs contain complete diagnostic info

---

## Phase 6: Error Modal Integration with Session Logs

**Priority: MEDIUM - Debugging UX**
**Estimated: 2-3 hours**
**Depends on: Phase 1**

### 6.1 Session Logs Tab in Error Modal
- [ ] Add "Session Logs" tab to GlobalErrorModal
- [ ] Fetch full logs from session API
- [ ] Downloadable as text file

### 6.2 Copy Full Report Enhancement
Include in report:
- Session ID
- Session type
- Complete session logs
- All stages with status
- Request/response details for failures

### 6.3 Error Context Enrichment
```typescript
{
  sessionId: "abc-123",
  sessionType: "publish",
  failedStage: "activate",
  stages: [
    { name: "backup", status: "success" },
    { name: "package", status: "success" },
    { name: "upload", status: "success" },
    { name: "activate", status: "error", error: "..." }
  ],
  fullLogs: "..." // From session API
}
```

---

## Phase 7: Specification & Memory Updates

**Priority: LOW - Documentation**
**Estimated: 1-2 hours**
**Do after implementation**

### 7.1 New Spec Files
- [ ] `spec/wp-plugin-publish/01-backend/17-session-management.md`
- [ ] `spec/wp-plugin-publish/02-frontend/27-quick-publish.md`
- [ ] `spec/wp-plugin-publish/02-frontend/28-remote-plugins.md`

### 7.2 Updated Spec Files
- [ ] `spec/wp-plugin-publish/01-backend/13-error-management.md` - Add session logging
- [ ] `spec/wp-plugin-publish/01-backend/14-logging-system.md` - Add detailed log format

### 7.3 New Memory Files
- [ ] `.lovable/memory/architecture/backend/session-logging.md`
- [ ] `.lovable/memory/architecture/frontend/publish-state-management.md`
- [ ] `.lovable/memory/features/remote-plugin-management.md`

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

**Total Estimated: 17-25 hours**

---

## Open Questions

1. **Session Retention**: How long to keep session logs? (Suggested: 7 days)
2. **Quick Publish Scope**: Publish to all mapped sites, or allow selecting subset?
3. **Remote Plugin Backups**: Store on WP site or download locally?
4. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?

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

*Last Updated: 2026-02-05*
