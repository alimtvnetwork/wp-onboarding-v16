


## Plan: Go Upload Performance Optimization + Core Plugin Dashboard + Snapshot UX

> Created: 2026-02-12  
> Status: **Feature A complete. Feature B complete. Feature C (Snapshot UX) — audit in progress.**

---

## Feature A: Go Upload Performance Optimization (5 fixes) ✅ COMPLETE

### Phase A1: Multipart upload ✅ DONE
### Phase A2: Remove pre-upload status check ✅ DONE
### Phase A3: Reduce ZIP compression level ✅ DONE
### Phase A4: Reduce verbose broadcasting ✅ DONE
### Phase A5: Update memory ✅ DONE

---

## Feature B: Core Plugin Dashboard ✅ COMPLETE

### Phase B1: Dashboard component ✅ DONE
### Phase B2: Route + navigation + badge link ✅ DONE
### Phase B3: API integration ✅ DONE

---

## Feature C: Snapshot UX — Audit & Fixes

### Problem Statement
The snapshot panel (RemoteSnapshotsPanel) needs UX improvements: users should be able to name snapshots, choose Full vs Incremental with parent selection, see worker pool settings inline, and have a clear "running" status. Errors from PHP endpoint communication need graceful handling.

### What's Already Done ✅

| Feature | Status | Notes |
|---------|--------|-------|
| 3-tab layout (Snapshots, Timeline, Settings) | ✅ Done | Grid 3-col TabsList |
| Create snapshot with scope (all/wordpress/content/custom) | ✅ Done | Scope selector + custom table picker |
| Full Backup & Incremental Backup buttons | ✅ Done | Separate buttons in advanced row |
| Snapshot list with hierarchy (full → incremental nesting) | ✅ Done | Parent-child grouping with `ml-6` indent |
| Delete with cascade warning | ✅ Done | Warns about incremental children count |
| Restore with full/selective mode | ✅ Done | Table picker for selective restore |
| Settings tab (provider, schedules, retention, worker pool, storage mode) | ✅ Done | Full config with sync indicator |
| Detail dialog (metadata, tables, download ZIP) | ✅ Done | Grid layout + table badges |
| Import ZIP | ✅ Done | File input with `.zip` accept |
| Timeline view | ✅ Done | Vertical timeline with dot indicators |
| Error handling with rich capture + PHP stack traces | ✅ Done | SnapshotApiError + error store |
| Auto-polling when snapshots are running (5s) | ✅ Done | `refetchInterval` conditional |
| WebSocket snapshot_complete notifications | ✅ Done | Toast with "View Details" action |
| Snapshot comparison (diff two snapshots) | ✅ Done | Side-by-side table/row diff |
| Worker pool size config (1–10) | ✅ Done | In Settings tab via SnapshotConfigPanel |
| Storage mode (single vs per-table) | ✅ Done | In Settings tab |

### What's Pending 🔧

| # | Task | Priority | Description |
|---|------|----------|-------------|
| C1 | **Snapshot Name Input** | High | Add a name/label field to the create form so users can name their snapshots. Date/time auto-appended. |
| C2 | **Unified Create Form (Type Selector)** | High | Replace the separate "Create" + "Full Backup" + "Incremental" buttons with a single create form that has a Type dropdown (Full / Incremental). When "Incremental" is selected, show a parent snapshot picker (list of completed full snapshots). |
| C3 | **Inline Worker Pool Quick-Set** | ✅ Done | Compact slider in create form area showing current worker count (1–10) with instant save to settings. |
| C4 | **Progress Indicator** | ✅ Done | Real-time WebSocket-driven progress banner with per-table badge status, overall progress bar, worker count, and dismiss on completion. |
| C5 | **Error Suppression on First Load** | ✅ Done | Initial load flag via `useRef` suppresses error state on first fetch, showing empty state instead. |
| C6 | **Tab Layout Stability** | ✅ Done | Added `shrink-0 overflow-hidden` to TabsList to prevent overflow at narrow widths. |

### Execution Order

1. **C1 + C2** (together) — Redesign create snapshot area with name input + type selector + parent picker
2. **C3** — Add inline worker count display
3. **C4** — Wire up progress endpoint polling
4. **C5 + C6** — Polish: initial load suppression + tab verification

---

## Feature D: Snapshot ZIP Export & Download System

### Problem Statement

Users need to download snapshot backups as ZIP files. The system should cache ZIPs to avoid redundant re-creation, invalidate automatically when new incremental backups are added, and expose the download through both the React dashboard (via Go proxy) and the WordPress admin UI.

### Architecture

#### User's Choices
- **ZIP Contents**: DB files only (a-root.db + .sqlite files for full + incremental)
- **ZIP Storage**: Separate `exports/` directory under snapshots root
- **Download Method**: WordPress returns a temporary download URL
- **Go Proxy Strategy**: Go downloads from WordPress and streams to React client

#### Storage Layout

```
wp-content/uploads/riseup-asia-uploader/snapshots/
├── exports/
│   ├── full_backup-name_2026-02-12.zip       ← cached ZIP
│   └── ...
├── 2026-02-12_full_backup-name/
│   ├── a-root.db
│   ├── wp_posts.sqlite
│   └── incremental/
│       ├── 01_2026-02-12/
│       └── 02_2026-02-13/
└── ...
```

#### SQLite Schema: `snapshot_exports` Table

```sql
CREATE TABLE IF NOT EXISTS snapshot_exports (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    snapshot_id   INTEGER NOT NULL,           -- FK to snapshots.id (the full snapshot)
    zip_filename  TEXT NOT NULL,              -- e.g. "full_backup-name_2026-02-12.zip"
    zip_path      TEXT NOT NULL,              -- full filesystem path
    zip_size      INTEGER NOT NULL DEFAULT 0, -- bytes
    included_ids  TEXT NOT NULL,              -- JSON array of snapshot IDs in the ZIP [1, 3, 5]
    incremental_count INTEGER NOT NULL DEFAULT 0, -- how many incrementals were included
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at    TEXT,                        -- NULL = valid until invalidated
    status        TEXT NOT NULL DEFAULT 'valid', -- 'valid' | 'expired' | 'building'
    UNIQUE(snapshot_id)
);
```

#### Invalidation Logic

When a new incremental backup completes for a full snapshot:
1. Query `snapshot_exports WHERE snapshot_id = <full_id> AND status = 'valid'`
2. If found: delete the ZIP file from disk, set `status = 'expired'`
3. Next download request triggers fresh ZIP creation

When a full snapshot is deleted (cascade):
1. Delete any matching `snapshot_exports` row + ZIP file

#### ZIP Compression

Use PHP's `ZipArchive` with `ZipArchive::CM_DEFLATE` (highest level). The Go backend already uses `flate.DefaultCompression` (Level 6) — WordPress should use the equivalent for parity.

### Phases

| # | Task | Priority | Description |
|---|------|----------|-------------|
| D1 | **PHP: `snapshot_exports` table + migration** | ✅ Done | Migration v11 in `class-database.php`. Constants added: `RISEUP_TABLE_SNAPSHOT_EXPORTS`, `RISEUP_ENDPOINT_SNAPSHOT_DOWNLOAD`, `RISEUP_ENDPOINT_SNAPSHOT_DOWNLOAD_FILE`, `RISEUP_ACTION_SNAPSHOT_ZIP_BUILD`, `RISEUP_ACTION_SNAPSHOT_ZIP_EXPIRE`, `RISEUP_ACTION_SNAPSHOT_ZIP_DOWNLOAD`, status constants, error codes, exports subdir. Version bumped to 1.57.0. |
| D2 | **PHP: `RiseupSnapshotExporter` class** | High | New class `class-snapshot-exporter.php`. Methods: `getOrBuildZip($fullSnapshotId)` — checks `snapshot_exports` for valid cached ZIP; if none, builds ZIP with all DB files (full + incremental children), inserts record, returns path. `invalidateZip($fullSnapshotId)` — deletes ZIP + marks expired. `getDownloadUrl($fullSnapshotId)` — returns temporary URL (wp-nonce signed, 1h expiry). Uses `ZipArchive::CM_DEFLATE`. |
| D3 | **PHP: REST endpoint `POST /snapshots/download`** | High | Accepts `{ snapshot_id }`. Calls `getOrBuildZip()`. Returns JSON `{ url, filename, size, cached, included_ids }`. If ZIP is being built, returns `{ status: 'building', progress }`. Errors return structured response with stack trace. |
| D4 | **PHP: Download file serve endpoint** | High | `GET /snapshots/download-file?token=<nonce>&id=<export_id>`. Validates nonce, streams ZIP file with `Content-Disposition: attachment`. Falls back to `readfile()` for small files, `fpassthru()` for large. |
| D5 | **PHP: Auto-invalidation hook** | Medium | After incremental backup completes (in orchestrator/incremental-backup), call `$exporter->invalidateZip($parentId)`. After full snapshot delete (in cleaner), delete matching export record + file. |
| D6 | **PHP: WordPress admin download button** | Medium | Add "Download ZIP" button to `admin-snapshots.php` dashboard for each full snapshot. Shows cached/building/expired badge. Uses AJAX to call the download endpoint and opens the URL in a new tab. Error responses show a modal with stack trace. |
| D7 | **Go: Proxy download endpoint** | High | `POST /sites/{siteId}/snapshots/download` — proxies to WordPress. Returns `{ url }` or streams the ZIP binary back (since user chose "Proxy through Go"). Go fetches the WordPress download URL and pipes the response body to the client with `Content-Disposition`. |
| D8 | **React: Download ZIP button in SnapshotRow** | High | Replace the current per-snapshot download link with a "Download ZIP" button on **full snapshots only**. Shows: cached (green, instant download) / building (amber, spinner) / expired (gray, "will rebuild"). Calls `POST /sites/{siteId}/snapshots/download` with `{ snapshot_id }`. On success, triggers browser download via blob URL. |
| D9 | **React: Download status in detail dialog** | Low | In the snapshot detail dialog, show ZIP export metadata: last exported at, size, included snapshot IDs, cached vs expired status. |
| D10 | **Memory + constants update** | Low | Add new constants to `constants.php`, update memory files for the export system architecture. |

### Execution Order

1. **D1** — Schema + constants
2. **D2 + D3 + D4** — PHP exporter + endpoints (parallel dev)
3. **D5** — Auto-invalidation hooks
4. **D6** — WordPress admin UI
5. **D7** — Go proxy
6. **D8 + D9** — React UI
7. **D10** — Documentation

### Hierarchy Confirmation

The snapshot list **already** correctly renders the hierarchy:
- Full snapshots at top level with `FileText` icon
- Incrementals nested underneath with `ml-6` indent, `border-l-2 border-l-primary/20`, and `GitBranch` icon
- Incremental badge (`text-[10px]`) on each child
- Cascade delete warning shows incremental count
- Parent-child grouping uses `parent_dir` → `filename` mapping

### Error Handling

All WordPress PHP endpoints already use:
- `rest_post_dispatch` filter for structured 4xx/5xx responses
- Injected `plugin_version`, `timestamp`, `log_hint` metadata
- Full stack trace in PHP error logs
- Frontend error modal with purple-themed PHP Stack Trace section
- `SnapshotApiError` capture in React with `useErrorStore`

The new download endpoints will follow the same pattern.

---

## Dependencies & Risks

| Risk | Mitigation |
|------|-----------|
| PHP endpoint errors on first load | Use initial-load flag to suppress; show clean empty state |
| Progress endpoint may not be deployed on all sites | Graceful fallback to "Running…" badge if progress call fails |
| Incremental parent picker needs completed full snapshots | Filter snapshot list to `status === "complete"` and `snapshot_type !== "incremental"` |
| ZIP build timeout for large databases | Build asynchronously via WP-Cron if > threshold; return `building` status |
| Stale ZIP cache after manual file edits | Invalidation only via incremental/delete hooks; manual edits are unsupported |
| Go proxy memory for large ZIPs | Stream response body, don't buffer entire ZIP in memory |
