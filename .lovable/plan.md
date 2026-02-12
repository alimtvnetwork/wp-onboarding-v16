


## Plan: WP Plugin Publish — Feature Roadmap & Type Safety Remediation

> Created: 2026-02-12  
> Status: **Features A–E1 complete. E2 in progress. F (Type Safety Remediation) planned.**

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
| C1 | **Snapshot Name Input** | ✅ Done | Name input field in create form. Optional label, passed as `opts.name` to backup endpoints. |
| C2 | **Unified Create Form (Type Selector)** | ✅ Done | Single create form with Type dropdown (Full / Incremental), scope selector, and conditional parent snapshot picker for incrementals. Replaced separate buttons. |
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
| D2 | **PHP: `RiseupSnapshotExporter` class** | ✅ Done | `class-snapshot-exporter.php` with `getOrBuildZip()`, `invalidateZip()`, `removeExports()`, `getDownloadUrl()`, `validateDownloadToken()`, `getExportStatus()`. Factory accessor added. Uses `ZipArchive::CM_DEFLATE` max compression. |
| D3 | **PHP: REST endpoint `POST /snapshots/download`** | ✅ Done | Registered in `register_routes()`. Handler calls `getOrBuildZip()`, returns envelope with `{ url, filename, size, cached, included_ids, incremental_count }`. |
| D4 | **PHP: Download file serve endpoint** | ✅ Done | `GET /snapshots/download-file?token=&id=`. Nonce-validated, streams ZIP via `fread()` with 8KB chunks. Public permission_callback with nonce guard. |
| D5 | **PHP: Auto-invalidation hooks** | ✅ Done | `invalidateParentZipExport()` added to `RiseupIncrementalBackup` — called after successful incremental backup, looks up parent by filepath and expires cached ZIP. `removeExports()` added to `RiseupSnapshotCleaner::deleteSnapshot()` — removes ZIP files and DB records during cascade delete. Both wrapped in try-catch for fault tolerance. |
| D6 | **PHP: WordPress admin download button** | ✅ Done | Replaced old `btn-export` with `btn-download-zip` (full snapshots only). Uses `POST /snapshots/download` endpoint. Shows spinner during build, cached/built badge on success. Error modal with HTTP status, plugin version, timestamp, PHP stack trace (purple-themed), and backend details (amber-themed). Copy Report button. |
| D7 | **Go: Proxy download endpoint** | ✅ Done | `POST /sites/{id}/snapshots/download` — calls WP `POST /snapshots/download` for cached ZIP metadata, then streams ZIP via `StreamSnapshotZip()`. New `rawGet()` on WP client. Exposes `X-Snapshot-Cached` and `X-Snapshot-Size` headers. Service, adapter, handler, and route added. |
| D8 | **React: Download ZIP button in SnapshotRow** | ✅ Done | Replaced old `<a>` download link with a `Download ZIP` button on full snapshots only. Uses `api.downloadSnapshotZip()` which POSTs to Go proxy, receives blob, triggers browser download. Shows spinner while building, toast with cached/size info on success, error toast on failure. |
| D9 | **React: Download status in detail dialog** | ✅ Done | Extracted `SnapshotDetailContent` component. Full snapshots show a "ZIP Export" section with filename, size, and cached/fresh-build badge after download. Incremental snapshots fall back to legacy export. Download button shows spinner while building, re-download option after first use. |
| D10 | **Memory + constants update** | ✅ Done | Created `.lovable/memory/architecture/snapshot-zip-export-system.md` (full architecture doc) and `.lovable/memory/features/snapshot-zip-export.md` (feature summary). Go constants already documented in `constants.go`. |

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

## Feature E1: Scheduled Auto-Snapshots

### Status: ✅ COMPLETE (infrastructure) + UI badge added

### What Already Existed
- Cron job API endpoints: GET/sync/trigger/pause/resume (`/sites/{id}/snapshots/cron/*`)
- `SnapshotCronJob` and `SnapshotCronSyncResult` types
- `CronJobsPanel` UI in SnapshotSettingsTab with sync, trigger, pause/resume controls
- Multi-schedule configuration (`SnapshotSchedule[]`) with hourly/3h/6h/12h/daily/weekly/monthly/yearly intervals
- Calendar view with future schedule dot indicators
- Retention policy settings (retention_type, retention_days, retention_max)
- Cleanup endpoint (`POST /sites/{id}/snapshots/cleanup`) for retention pruning
- Auto-sync of cron jobs after settings update

### What Was Added (E1)

| # | Task | Status | Description |
|---|------|--------|-------------|
| E1.1 | **Last Backup Badge on SiteCard** | ✅ Done | Shows "Last backup X ago" with Clock icon on each connected site card. Queries latest completed snapshot per site with 60s stale time. |
| E1.2 | **Next Scheduled Badge on SiteCard** | ✅ Done | Shows "Next: in X" with Calendar icon from the earliest active cron job's `nextRunAt`. Only visible for connected sites with active schedules. |

### Backend Requirements (PHP/Go — outside this React project)
- E1.3: Auto-prune after scheduled backup completion (trigger cleanup endpoint after cron-driven backup finishes)
- E1.4: Per-site cron job scoping (currently uses siteId=0 for global; per-site scoping needed for multi-site differentiation)

---

## Feature E2: Centralized Activity Audit Log

### Problem Statement

Activity data is currently fragmented: publish history lives on the Publish History page (filterable by site), snapshot events appear in the snapshot timeline tab, and WordPress admin logs are only visible server-side. Users need a single, unified view of **all actions across their entire fleet** — publishes, snapshots, plugin changes, config updates — with powerful filtering and search.

### What Already Exists

| Component | Location | Data Source |
|-----------|----------|-------------|
| Publish History page | `/publish-history` | Go backend session history |
| Per-site activity link | SiteCard → Activity button | Filters publish history by `siteId` |
| Snapshot Timeline tab | RemoteSnapshotsPanel → Timeline | WordPress REST API per-site |
| WordPress admin logs | WP Admin only | PHP activity_logs table |
| Request Sessions page | `/request-sessions` | Go backend session store |

### Architecture

#### Unified API Approach

The Go backend will aggregate activity from multiple sources into a single paginated endpoint:

```
GET /api/v1/activity?page=1&limit=50&siteId=&type=&from=&to=&search=
```

Returns a normalized `ActivityEntry[]`:
```ts
interface ActivityEntry {
  id: string;
  timestamp: string;
  siteId: number;
  siteName: string;
  type: "publish" | "snapshot" | "plugin" | "config" | "connection";
  action: string;           // e.g. "create", "restore", "upload", "delete"
  title: string;            // Human-readable summary
  metadata: Record<string, unknown>; // Action-specific details
  source: "go" | "wordpress";
  machineName?: string;
  version?: string;
}
```

### Phases

| # | Task | Priority | Description |
|---|------|----------|-------------|
| E2.1 | **Go: Unified activity endpoint** | High | Spec complete — see `spec/e2-activity-feed/e2.1-go-endpoint-spec.md`. Aggregates publish sessions + snapshot events + plugin actions into `/api/v1/activity`. |
| E2.2 | **React: ActivityEntry type + API method** | ✅ Done | `ActivityEntry`, `ActivityFeedResponse`, `ActivityFeedParams` in `types.ts`. `api.getActivityFeed()` in `methods.ts`. Barrel exported. |
| E2.3 | **React: Activity Feed page** | ✅ Done | `/activity` route with mock data. Filters: search, type selector, site selector. Color-coded action badges per convention. Expandable detail rows. Stats bar. Pagination. |
| E2.4 | **React: Navigation link** | ✅ Done | "Activity Feed" with `Activity` icon added to sidebar between Publish History and E2E Tests. Route registered in `App.tsx`. |
| E2.5 | **React: Fleet summary header** | ✅ Done | Integrated into Activity Feed page — stats cards showing Actions Today, Total Events, Active Sites. |
| E2.6 | **React: Real-time updates via WebSocket** | Low | Listen to existing WS events (publish_complete, snapshot_complete) to prepend new entries to the feed without polling. |
| E2.7 | **React: Export activity log** | Low | Download filtered results as CSV/JSON for compliance or reporting. |

### Execution Order

1. **E2.1** — Go backend endpoint (outside this React project)
2. **E2.2 + E2.3 + E2.4** — React types, page, and navigation (parallel)
3. **E2.5** — Summary header
4. **E2.6** — WebSocket live updates
5. **E2.7** — Export feature

### Action Badge Color Coding (from existing conventions)

| Type | Color | Examples |
|------|-------|----------|
| Publish | Primary (teal) | Deploy, Upload, Self-Update |
| Snapshot Create | Teal | Full Backup, Incremental Backup |
| Snapshot Restore | Amber | Restore Full, Restore Selective |
| Snapshot Delete | Rose | Delete, Cascade Delete |
| Snapshot Export | Cyan | ZIP Build, Download |
| Plugin | Indigo | Install, Activate, Deactivate, Delete |
| Config | Slate | Settings Change, Schedule Update |
| Connection | Primary | Test, Connect, Disconnect |

### Dependencies

- E2.1 (Go endpoint) must be built before E2.3 can fetch real data
- E2.3 can scaffold with mock data initially, then swap to real API
- E2.6 leverages existing WebSocket infrastructure (no new WS events needed)

---

## Feature F: Type Safety Remediation (CRITICAL PRIORITY)

### Status: F1 ✅ Done, F2 ✅ Done, F3 ✅ Done, F4 ✅ Done, F5 ✅ Done, F6 ✅ Done, F7 ✅ Done, F8 ✅ Done — ALL COMPLETE

### Spec & Plan References
- **Coding Standards:** `spec/02-typescript-standards/README.md` v2.0.0
- **Remediation Plan:** `spec/02-typescript-standards/type-safety-remediation-plan.md`
- **Memory:** `.lovable/memory/architecture/coding-standards/type-safety-rules`

### Core Rules
1. **Generics First** — all reusable code uses generics, never `any`/`unknown`/loose `Record`
2. **Zero `any`** — prohibited everywhere, no exceptions
3. **No Magic Strings** — `const enum` or named constants only
4. **No Magic Numbers** — named `as const` constants only
5. **Specific Types** — domain interfaces over `Record<string, unknown>`

### Phases

| # | Task | Priority | Effort | Description |
|---|------|----------|--------|-------------|
| F1 | **API type definitions** | ✅ Done | Medium | Created `CreateSnapshotOptions`, `SnapshotOperationResult`, `RestoreSnapshotOptions`, `CleanupSnapshotOptions`, `CleanupSnapshotResult`, `SnapshotImportResult`, `SnapshotScope`, `SnapshotType`, `SiteHealthCheckResult`, `E2ESuite`, `E2ECase`, `E2ERun`, `E2ERunSummary`, `E2ETestResult` in `types.ts`. Updated all method signatures in `methods.ts`. |
| F2 | **Catch block fixes** | ✅ Done | Small | Replaced all 11x `catch (err: any)` with `catch (err: unknown)` + `instanceof Error` narrowing across 6 files. Zero `catch (err: any)` remaining. |
| F3 | **`as any` elimination** | ✅ Done | Small | Fixed `as any` casts: `ThemeSelector` (FontSize/BorderRadius), `useTheme` (added `sidebarTheme` to Settings.appearance type), `useDashboardStats` (typed Site/Plugin/ErrorLog, removed `.entries as any`). Zero `as any` remaining. |
| F4 | **Constants file** | ✅ Done | Small | Created `src/lib/constants.ts` with const object + type pattern for `ConnectionStatus`, `PublishStatus`, `SnapshotRunStatus`, `CronJobStatus`, `RemotePluginStatus`, `SessionStatus`, `FileChangeStatus`, `FileDirection`, plus timing constants (`POLL_INTERVAL_DASHBOARD_MS`, `STALE_TIME_DEFAULT_MS`, etc.). |
| F5 | **Update methods.ts** | ✅ Done | Medium | All `Record<string, unknown>` and `request<unknown>` replaced with specific types |
| F6 | **Generic envelope** | ✅ Done | Small | Made `RawEnvelope<T = unknown>` generic with typed `Results: T[]` |
| F7 | **Magic string migration** | ✅ Done | Large | Migrated 50+ inline magic string comparisons across 12 files to use const references from `src/lib/constants.ts`. Added new const objects: `PublishOperationStatus`, `PublishStageName`, `PublishStageStatus`, `SyncStatus`, `DeployStatus`, `ConnectionTestStep`, `ConnectionTestStatus`, `PublishActionType`, `LogLevel`, `SessionType`. Updated: `publishStore.ts`, `GlobalPublishProgress.tsx`, `Sessions.tsx`, `PublishHistory.tsx`, `CorePluginDashboard.tsx`, `TestResultRow.tsx`, `LiveTestProgress.tsx`, `DeployUploaderDialog.tsx`, `useConnectionTestLogs.ts`, `RecentPublishes.tsx`, `publishHistoryUtils.ts`. |
| F8 | **Activity metadata typing** | 🟡 High | Medium | Discriminated union for `ActivityEntry.metadata` |

### Execution Order
1. F1 → F5 (types then methods)
2. F2 + F3 (parallel — quick wins)
3. F4 → F7 (constants then migration)
4. F6 + F8 (parallel)

---

## Pending / Backlog

| Item | Feature | Status | Description |
|------|---------|--------|-------------|
| E2.1 | Activity Feed | Spec complete | Go unified activity endpoint — needs backend implementation |
| E2.6 | Activity Feed | Pending | WebSocket real-time updates for activity feed |
| E2.7 | Activity Feed | Pending | Export activity log as CSV/JSON |
| E3 | Cloud Offload | Proposed | S3/R2/GCS integration for snapshot long-term retention |
| E4 | Audit Log | Superseded by E2 | Centralized audit log (merged into E2) |
| E5 | Site Sync | Proposed | Side-by-side site comparison with one-click sync |

---

| Risk | Mitigation |
|------|-----------|
| PHP endpoint errors on first load | Use initial-load flag to suppress; show clean empty state |
| Progress endpoint may not be deployed on all sites | Graceful fallback to "Running…" badge if progress call fails |
| Incremental parent picker needs completed full snapshots | Filter snapshot list to `status === "complete"` and `snapshot_type !== "incremental"` |
| ZIP build timeout for large databases | Build asynchronously via WP-Cron if > threshold; return `building` status |
| Stale ZIP cache after manual file edits | Invalidation only via incremental/delete hooks; manual edits are unsupported |
| Go proxy memory for large ZIPs | Stream response body, don't buffer entire ZIP in memory |
| Activity feed performance with many sites | Paginated API with cursor-based pagination; frontend virtualizes long lists |
| Cross-source timestamp alignment | Go normalizes all timestamps to UTC ISO-8601 before returning |
| Type remediation regression | Run `tsc --noEmit` + grep audit after each phase |
