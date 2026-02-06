

# Enhanced Sync System - Phased Implementation Plan

## Overview

The current sync system has a placeholder for remote file comparison -- it scans local files but never actually fetches remote hashes. This plan implements a full bidirectional sync check with SQLite-backed file caching on both the WordPress side (PHP) and the Go backend, plus a tree view UI showing mismatches with conflict resolution based on "highest modification date" priority.

---

## Current State

- **WordPress Plugin**: The `/plugins/{slug}/files` endpoint already scans files and returns MD5 hashes + `modifiedAt` (UTC ISO 8601), but recalculates on every request -- no caching.
- **Go Backend**: `CheckSync()` scans local files for MD5 hashes but uses a hardcoded empty map for remote files (`remoteFiles := make(map[string]string)`). No modification timestamps are tracked.
- **Frontend**: `SyncProgressDialog` shows progress stages. `RemotePluginFileBrowser` already has a working `buildTree()` function for hierarchical file display.

---

## Phase 41: WordPress Plugin - File Hash Cache (SQLite)

**Goal**: Cache file hashes and modification times in the SQLite database so repeated sync requests return instantly for unchanged files.

### What gets built:
- New SQLite migration (v7) adding a `file_cache` table with columns: `plugin_slug`, `relative_path`, `md5_hash`, `modified_at` (UTC), `file_size`, `cached_at`
- New constant definitions in `constants.php` for the table name and endpoint
- A new `RiseupFileCache` class (`includes/class-file-cache.php`) that:
  - Scans a plugin directory, comparing each file's `filemtime()` against the cached `modified_at`
  - If unchanged: returns cached hash (skip `md5_file()`)
  - If changed or new: recalculates `md5_file()`, updates cache
  - Removes cache entries for deleted files
  - Returns a flat array of `{path, hash, modifiedAt, size}`
- Update the existing `/plugins/{slug}/files` endpoint to use the cache class instead of raw scanning
- New endpoint `/plugins/{slug}/sync-manifest` that returns the cached data in a standardized format optimized for sync comparison

### Response format (from sync-manifest endpoint):
```json
{
  "success": true,
  "data": {
    "plugin": "my-plugin",
    "fileCount": 42,
    "generatedAt": "2026-02-06T12:00:00Z",
    "cached": true,
    "files": [
      {
        "path": "includes/class-main.php",
        "hash": "a1b2c3d4e5f6...",
        "modifiedAt": "2026-02-06T10:30:00Z",
        "size": 4096
      }
    ]
  }
}
```

---

## Phase 42: Go Backend - Local File Scanning with Timestamps

**Goal**: Extend the local file scanner to track modification timestamps alongside MD5 hashes, and implement the comparison engine with conflict resolution.

### What gets built:
- New `FileEntry` struct in `sync/service.go` replacing the simple `map[string]string`:
  ```text
  FileEntry {
    Path       string
    Hash       string
    ModifiedAt time.Time
    Size       int64
  }
  ```
- Update `scanLocalFiles()` to return `map[string]FileEntry` (path to hash+modifiedAt+size)
- Update `compareFiles()` to accept `map[string]FileEntry` for both local and remote, implementing:
  - If hashes match: file is in sync (skip)
  - If hashes differ: compare `modifiedAt` -- the newer timestamp wins priority
  - Files only in local: marked as "added" (local only)
  - Files only in remote: marked as "deleted" (remote only)
- Enhance `SyncResult` and `FileChange` models to include `modifiedAt`, `size`, and `direction` (local_newer / remote_newer)

---

## Phase 43: Go Backend - Remote Integration via WordPress API

**Goal**: Wire the Go backend to actually call the WordPress plugin's sync-manifest endpoint and perform the real comparison.

### What gets built:
- New method in `wordpress/remote_files.go`: `GetPluginSyncManifest(slug string)` that calls the `/sync-manifest` endpoint
- Update `CheckSync()` to:
  1. Call `GetPluginSyncManifest()` to get remote file entries (with hash + modifiedAt)
  2. Call `scanLocalFiles()` to get local file entries (with hash + modifiedAt)
  3. Run the enhanced `compareFiles()` with both datasets
  4. Broadcast detailed progress via WebSocket at each stage
- Update the `SyncServiceAdapter` if the interface changes
- Ensure site credentials are properly decrypted and passed to the WP client

---

## Phase 44: React Frontend - Sync Tree View UI

**Goal**: Display the sync comparison results in a hierarchical tree view showing file status, modification dates, and which side has priority.

### What gets built:
- New `SyncTreeView` component (reusing `buildTree()` pattern from `RemotePluginFileBrowser`) showing:
  - Folder/file hierarchy with expand/collapse
  - Status icons per file: green checkmark (in sync), orange arrow (modified), blue plus (local only), red minus (remote only)
  - For modified files: show both local and remote timestamps, highlight which is newer
  - File size display
- Update `SyncProgressDialog` to embed `SyncTreeView` after sync check completes
- Add summary stats at the top: total files, in-sync count, modified count, added/deleted counts

---

## Remaining Phases After This Plan

| Phase | Description | Status |
|-------|-------------|--------|
| **41** | WP Plugin - File Hash Cache (SQLite) | ✅ Complete |
| **42** | Go Backend - Local Scanning with Timestamps | ✅ Complete |
| **43** | Go Backend - Remote Integration | ✅ Complete |
| **44** | React Frontend - Sync Tree View UI | ✅ Complete |

---

## Technical Notes

- **No true parallelism in PHP**: PHP is single-threaded. The "parallel" optimization means batching `md5_file()` calls efficiently and using the cache to skip unchanged files, not actual multi-threading.
- **UTC 0 timestamps**: Both sides use UTC. PHP uses `gmdate('c', filemtime())`, Go uses `file.ModTime().UTC()`.
- **Cache invalidation**: The WP plugin cache is invalidated per-file when `filemtime()` changes. The Go backend always scans fresh (local files are fast to read from disk).
- **Conflict resolution priority**: When hashes differ, the file with the newer `modifiedAt` timestamp is flagged as the authoritative version. The UI shows direction arrows indicating which side should be pushed/pulled.

