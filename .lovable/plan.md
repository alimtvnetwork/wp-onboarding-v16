


## Plan: WP Plugin Publish — Feature Roadmap & Remediation

> Created: 2026-02-12  
> Status: **Features A–E1, F, G, H complete. E2 in progress.**

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
| F8 | **Activity metadata typing** | ✅ Done | Medium | Discriminated union for `ActivityEntry.metadata` |

### Execution Order
1. F1 → F5 (types then methods)
2. F2 + F3 (parallel — quick wins)
3. F4 → F7 (constants then migration)
4. F6 + F8 (parallel)

---

## Feature G: PHP 8.1+ Enum Migration (ACTIVE)

### Status: G1 ✅ Done

The plugin currently uses **class-based fake enums** (`class FooEnum { public const BAR = '...'; }`) and `define()` constants. PHP 8.1+ provides native backed enums with type safety, `tryFrom()` validation, and `cases()` introspection. All enum-like constants must be migrated to real `enum` types under the `RiseupAsia\Enums` namespace in `includes/Enums/`.

### Spec Reference
- **Enum Spec:** `spec/04-php-standards/enums.md` v4.0.0

### Architecture

```
includes/Enums/                         ← PSR-4 naming (NOT class-kebab-case.php)
├── UploadSource.php    ← enum UploadSource: string    (file = definition name)
├── Capability.php      ← enum Capability: string      (file = definition name)
├── HttpMethod.php      ← enum HttpMethod: string      (file = definition name)
├── Hook.php            ← enum Hook: string             (file = definition name)
├── PathConst.php       ← final class PathConst         (file = definition name)
└── ErrorType.php       ← final class ErrorType         (file = definition name)
```

**Namespace:** `RiseupAsia\Enums`  
**Naming:** File name = definition name, PascalCase, no prefix/suffix/hyphens/underscores.  
**Why not `class-kebab-case.php`?** The `Enums/` folder uses PSR-4 because these are namespaced types, not WordPress procedural classes.

### Classification

| Name           | Type         | Reason                                                      |
|----------------|--------------|-------------------------------------------------------------|
| `UploadSource` | `enum`       | Discrete choices — "which upload source?"                   |
| `Capability`   | `enum`       | Discrete WordPress capability strings                       |
| `HttpMethod`   | `enum`       | Discrete HTTP verbs                                         |
| `Hook`         | `enum`       | Discrete WordPress hook names                               |
| `PathConst`    | `final class`| Path fragments composed with directories — not a choice set |
| `ErrorType`    | `final class`| Arrays of E_* constants and label maps                      |

### Phases

| # | Task | Status | Effort | Description |
|---|------|--------|--------|-------------|
| G1 | **Create `includes/Enums/` folder + all 6 files** | ✅ Done | Medium | Created `UploadSource.php`, `Capability.php`, `HttpMethod.php`, `Hook.php`, `PathConst.php`, `ErrorType.php` with `RiseupAsia\Enums` namespace. PSR-4 file naming (file = definition name). |
| G2 | **Update ErrorChecker** | ✅ Done | Small | Added `use RiseupAsia\Enums\ErrorType;` and changed all `ErrorTypeEnum::` → `ErrorType::`. Registered all 6 new enum files in bootstrap before legacy classes. |
| G3 | **Update constants.php** | ✅ Done | Medium | Replaced `CapabilityEnum::` → `\RiseupAsia\Enums\Capability::*->value` and `UploadSourceEnum::` → `\RiseupAsia\Enums\UploadSource::*->value` in backward-compat aliases. |
| G4 | **Update class-file-logger.php** | ✅ Done | Small | Created `LogLevel` enum (`includes/Enums/LogLevel.php`). Replaced all `LOG_LEVEL_*` define constants with `LogLevel::*->value`. Added `use RiseupAsia\Enums\LogLevel;` import. Registered in bootstrap. |
| G5 | **Update class-database.php** | ✅ Done | Small | No old `*Enum` class references to replace — class already uses internal `self::` constants. Remaining `define()` migration (ACTION_*, STATUS_*) deferred to G8/G9 when new enums are created. |
| G6 | **Update class-admin.php** | ✅ Done | Medium | Added `use RiseupAsia\Enums\{Capability, Hook};`. Replaced 10x `'manage_options'` → `Capability::ManageOptions->value`, 4x `'admin_*'` hook strings → `Hook::*->value`, 10x `'wp_ajax_*'` → `Hook::ajax(...)`. |
| G7 | **Update class-logger.php** | ✅ Done | Small | Uses `define()` constants (`ACTION_*`, `STATUS_*`) which haven't been enumified yet. No old `*Enum` class references to replace. Full migration deferred to G8/G9. |
| G8 | **Update REST route registration** | ✅ Done | Large | Added `use RiseupAsia\Enums\{HttpMethod, Hook};` to bootstrap. Replaced 40+ `'GET'`/`'POST'` → `HttpMethod::*->value`, 5x lifecycle hooks → `Hook::*->value`, catch-all array → enum values. Zero raw method strings remain in `register_routes()`. |
| G9 | **Update all remaining files** | ✅ Done | Medium | Added `use Hook;` to `class-update-resolver.php` (2x filter hooks) and `class-snapshot-scheduler.php` (1x filter hook). Updated bootstrap `add_action('plugins_loaded', ...)` → `Hook::PluginsLoaded->value`. Remaining `'hook_source'` strings in log metadata are data values, not hook registrations. |
| G10 | **Delete old class-*-enum.php files** | ✅ Done | Small | Deleted 6 files: `class-hook-enum.php`, `class-path-enum.php`, `class-error-type-enum.php`, `class-capability-enum.php`, `class-http-method-enum.php`, `class-upload-source-enum.php`. Removed 6 `require_once` lines from bootstrap, kept `class-error-checker.php` require. |
| G11 | **Update spec & memory** | ✅ Done | Medium | Updated `spec/04-php-standards/README.md` (10 replacements: `HookEnum::*` → `Hook::*->value`, `PathEnum` → `PathConst`, `CapabilityEnum::MANAGE_OPTIONS` → `Capability::ManageOptions->value`, `HttpMethodEnum::POST` → `HttpMethod::Post->value`, `ErrorTypeEnum` → `ErrorType`). Updated `forbidden-patterns.md` (all 51 rows). Updated `02-naming-convention-refactor-plan.md` to v2.0.0 reflecting Phase 1 complete. `enums.md` already at v4.0.0 — no changes needed. |

### Execution Order

1. **G1** — Create all enum files (foundation)
2. **G2** — Update ErrorChecker (first consumer)
3. **G3** — Clean up constants.php (remove migrated defines)
4. **G4 + G5 + G6 + G7** — Update core classes (parallel)
5. **G8 + G9** — Sweep all files (parallel, batch by file group)
6. **G10** — Delete old files
7. **G11** — Documentation update

### Migration Pattern

For each file being updated:

```php
// BEFORE (old pattern):
class Riseup_Admin {
    public function enqueue_admin_assets($hook) {
        wp_enqueue_style('...', '...', array(), RISEUP_VERSION);
    }
    public function ajax_test() {
        if (!current_user_can('manage_options')) { ... }
    }
}

// AFTER (new pattern):
use RiseupAsia\Enums\Capability;

class Riseup_Admin {
    public function enqueue_admin_assets($hook) {
        wp_enqueue_style('...', '...', array(), PLUGIN_VERSION);
    }
    public function ajax_test() {
        if (!current_user_can(Capability::ManageOptions->value)) { ... }
    }
}
```

---

## Feature H: PHP Coding Standards Remediation ✅ COMPLETE

### Status: All phases complete

Enforced project-wide PHP coding standards: 200-line file limit, 15-line function logic limit, zero raw function negations, and zero magic strings for HTTP methods and log levels.

### Phases

| # | Task | Status | Description |
|---|------|--------|-------------|
| H1 | **File-size remediation (200-line limit)** | ✅ Done | Decomposed all oversized PHP files into focused trait-based classes. Every file under 200 lines (excluding `riseup-asia-uploader.php` shell and `constants.php` definitions). |
| H2 | **Function-size remediation (15-line limit)** | ✅ Done | Refactored all functions exceeding 15 logic lines via early returns, helper extraction, and combined conditions. 16 phases completed. |
| H3 | **Raw function negation cleanup** | ✅ Done | Replaced all `!file_exists()`, `!function_exists()`, `!class_exists()`, `!in_array()`, `!is_file()`, `!is_dir()`, `!is_readable()`, `!copy()`, `!extension_loaded()` with semantic `BooleanHelpers` guards (`is_file_missing`, `is_func_missing`, `is_class_missing`, `is_not_in_list`, `is_not_regular_file`, `is_not_directory`, `is_file_unreadable`, `is_copy_failed`, `is_extension_missing`, `is_class_not_loaded`). Zero violations remaining. |
| H4 | **HTTP method magic string cleanup** | ✅ Done | Replaced 5 raw `'GET'`, `'POST'`, `'PUT'`, `'PATCH'` strings in Agent traits (`AgentRemoteCoreTrait.php`, `AgentRemoteActionTrait.php`) with `HttpMethodType::*->value` enum references. Zero violations remaining. |
| H5 | **Log level magic string cleanup** | ✅ Done | Replaced ~643 raw `'INFO'`, `'ERROR'`, `'WARN'`, `'DEBUG'` strings across 40 files with `LogLevelType::*->value` enum references. Updated all `$this->log()` calls, `safe_log()` calls, and switch-case dispatchers. Updated `safe_log` in `PathUtilsCoreTrait` to normalize uppercase enum values via `strtolower()`. Zero violations remaining. |

### References
- **Trait decomposition map:** `.lovable/memory/architecture/php/trait-decomposition-map.md`
- **Boolean guard implementation:** `.lovable/memory/architecture/php/boolean-guard-implementation`
- **Remediation strategy:** `.lovable/memory/workflow/remediation-strategy-priority`

---

## Feature I: PHP camelCase & Encapsulation Remediation 🔄 IN PROGRESS

### Status: Phase I1–I5 complete, I6 audit complete — implementation pending

Enforce camelCase naming for all PHP properties, methods, and parameters. Add encapsulated helper methods to enums. Eliminate all remaining snake_case identifiers in internal code.

### Phases

| # | Task | Status | Description |
|---|------|--------|-------------|
| I1 | **Update memory & specs** | ✅ Done | Updated `naming-conventions.md` with boolean prefix rules, property/param camelCase, singleton pattern, and enum encapsulation standards. |
| I2 | **Enhance LogLevelType enum** | ✅ Done | Added `isError()`, `isWarn()`, `isInfo()`, `isDebug()`, `isErrorOrWarn()` helper methods to the enum body. |
| I3 | **Logging domain camelCase** | ✅ Done | Refactored FileLogger (shell + 7 traits) + Logger (shell + 2 traits) — all properties, methods, and params to camelCase. |
| I4 | **Caller updates (batch 1)** | ✅ Done | Updated Database, UpdateResolver, AgentManager, SnapshotFactory, SnapshotScheduler, UploadIgnore, AdminErrorAjaxTrait, ErrorLogHandlerTrait, PathUtilsCoreTrait, DatabaseConnectionTrait — all renamed to camelCase APIs. |
| I5 | **LOG_LEVEL_* constant cleanup** | ✅ Done | Replaced all `LOG_LEVEL_*` constant usages across 16 Snapshot/Manager trait files with `LogLevelType::*->value` enum references. Removed legacy constant definitions from `constants.php`. Fixed `ManagerCoreTrait` incorrect `LogLevel` import → `LogLevelType`. Zero violations remaining. |
| I6 | **Remaining caller updates** | 🔄 Batch 1 Done | Full audit identified **~2,000+ renames across ~70 files**. Batch 1 complete: ~20 files (main shell, Admin domain, UpdateResolver domain, ResponseTrait, LifecycleHooksTrait, RouteRegistrationTrait, PostManager). Remaining: ~50 files (Plugin/Snapshot/Agent/Auth/Sync/Error/Upload traits + PluginRoutesTrait, SnapshotRouteRegistrationTrait, PostHandlerTrait, remaining callers). |
| I7 | **Full codebase audit** | ⬚ Pending | Final grep to confirm zero snake_case methods/properties remain. |

### I6 Sub-phases (Audit Results)

The full-codebase scan identified the following snake_case violation categories:

| Sub | Category | Matches | Files | Rename Pattern |
|-----|----------|---------|-------|----------------|
| I6a | **`$file_logger` property** | 988 | 51 | `$file_logger` → `$fileLogger` (declaration + all `$this->file_logger` refs) |
| I6b | **`$post_manager` property** | 25 | 2 | `$post_manager` → `$postManager` |
| I6c | **`get_instance()` singletons** | 99 | 10 | `get_instance()` → `getInstance()` (definition + all callers: RiseupAsia, RiseupAdmin, RiseupDatabase, RiseupLogger, RiseupUpdateResolver) |
| I6d | **`error_response()` method** | 338 | 21 | `error_response()` → `errorResponse()` |
| I6e | **`safe_execute()` method** | 150 | 14 | `safe_execute()` → `safeExecute()` |
| I6f | **`log_exception()` callers** | 135 | 14 | `log_exception()` → `logException()` (definition already camelCase in FileLogger; callers still use old name) |
| I6g | **`log_plugin_action()` callers** | 125 | 11 | `log_plugin_action()` → `logPluginAction()` |
| I6h | **`log_post_action()` callers** | 10 | 2 | `log_post_action()` → `logPostAction()` |
| I6i | **`handle_*` REST handlers** | 265 | 24 | `handle_list_plugins()` → `handleListPlugins()`, etc. + callback strings |
| I6j | **`on_plugin_*` lifecycle hooks** | 15 | 1 | `on_plugin_activated()` → `onPluginActivated()`, etc. + callback strings |
| I6k | **`register_routes` + auth methods** | 15+10 | 2 | `register_routes()` → `registerRoutes()`, `check_plugin_permission()` → `checkPluginPermission()`, `build_permission_callback()` → `buildPermissionCallback()` |
| I6l | **Remaining snake_case methods** | ~100 | ~15 | `find_plugin_file()`, `log_plugin_lifecycle()`, `load_plugin_functions()`, `validate_and_write_zip()`, `remove_duplicate_plugins()`, `pre_log_self_update()`, ORM methods (`where_operator`, `where_equal`, `generate_param_name`), Admin methods (`render_*`, `ajax_*`, `get_settings`, `get_unseen_error_count`), etc. |
| I6m | **Remaining snake_case properties** | ~200 | ~30 | `$provider_id`, `$provider_name`, `$param_counter`, `$where_clauses`, `$per_page`, `$agent_id`, local variables like `$zip_content`, `$error_msg`, etc. |

### Execution Order (I6)

1. **I6a** — `$file_logger` → `$fileLogger` (highest impact, 51 files)
2. **I6c** — `get_instance()` → `getInstance()` (10 files, affects bootstrap)
3. **I6d + I6e** — `error_response` + `safe_execute` (35 files, trait definitions + callers)
4. **I6f + I6g + I6h** — Logger method callers (27 files)
5. **I6i + I6j + I6k** — REST handler names + callback strings (26 files)
6. **I6l + I6m** — Remaining methods + properties (45 files)
7. **I6b** — `$post_manager` (2 files, trivial)

### References
- **Naming conventions:** `.lovable/memory/architecture/php/naming-conventions.md`

---

## Feature J: constants.php Enum & Domain Decomposition

### Status: ⬚ Pending

### Problem Statement

`constants.php` is 927 lines of raw `define()` calls — the largest file in the project and exempt from the 200-line limit only because it has no logic. However, many constants represent discrete choice sets (action names, status values, snapshot scopes, trigger types) that should be native PHP 8.1+ backed enums for type safety and IDE autocompletion. The remaining constants (table names, error codes, HTTP status codes, config defaults) should be grouped into focused `final class` constant holders under `includes/Constants/`.

### Architecture

```
includes/Enums/
├── ActionType.php         ← enum ActionType: string (enable, disable, delete, upload, ...)
├── StatusType.php         ← enum StatusType: string (success, failed, pending, ...)
├── SnapshotScopeType.php  ← enum SnapshotScopeType: string (wordpress, content, custom, all)
├── SnapshotStatusType.php ← enum SnapshotStatusType: string (scheduled, running, complete, failed)
├── TriggerType.php        ← enum TriggerType: string (manual, cron, api)
├── SyncActionType.php     ← enum SyncActionType: string (create, update, delete)

includes/Constants/
├── TableConst.php         ← final class (TABLE_SNAPSHOTS, TABLE_ACTIVITY_LOGS, ...)
├── ErrorCodeConst.php     ← final class (ERR_SNAPSHOT_NOT_FOUND, ERR_PROVIDER_NOT_AVAILABLE, ...)
├── HttpConst.php          ← final class (HTTP_OK, HTTP_CREATED, HTTP_BAD_REQUEST, ...)
├── DefaultConst.php       ← final class (DEFAULT_LIMIT, MAX_LIMIT, SNAPSHOT_BATCH_SIZE, ...)
├── EndpointConst.php      ← final class (REST route path segments)
└── CronConst.php          ← final class (CRON_SNAPSHOT_*, schedule names)
```

### Phases

| # | Task | Status | Description |
|---|------|--------|-------------|
| J1 | **Audit constants.php** | ✅ Done | Full audit: 180 constants categorized into 15 enum candidates (75 constants) + 10 const-class candidates (96 constants) + 9 already-migrated aliases. See `memory/workflow/03-j1-constants-audit.md`. |
| J2 | **Create ActionType enum** | ⬚ Pending | Migrate 42 ACTION_* constants to `ActionType` enum. Update all callers. Largest single enum. |
| J3 | **Create StatusType + PostStatusType enums** | ⬚ Pending | Migrate STATUS_* (2) + POST_STATUS_* (3) = 5 constants. |
| J4a | **Create SnapshotStatusType + SnapshotJobStatusType** | ⬚ Pending | 9 constants covering snapshot lifecycle states. |
| J4b | **Create SnapshotScopeType + SnapshotFrequencyType + SnapshotTypeType** | ⬚ Pending | 10 constants covering snapshot configuration choices. |
| J4c | **Create SnapshotProviderType + SnapshotTriggerType + SnapshotExportStatusType + RetentionType** | ⬚ Pending | 13 constants covering snapshot infrastructure. |
| J5 | **Create AgentStatusType + TriggerSourceType + SyncActionType** | ⬚ Pending | 10 constants covering agent/sync/trigger domains. |
| J6a | **Create EndpointConst class** | ⬚ Pending | 40 ENDPOINT_* constants into `final class EndpointConst`. |
| J6b | **Create TableConst + ErrorCodeConst classes** | ⬚ Pending | 9 TABLE_* + 10 ERR_* = 19 constants. |
| J6c | **Create HttpConst + MessageConst classes** | ⬚ Pending | 7 HTTP_* + 14 MSG_* = 21 constants. |
| J6d | **Create DefaultConst + CronConst + PathConst + PluginConst + ApiConst + OptionConst** | ⬚ Pending | 30 remaining config/identity constants. |
| J7 | **Shrink constants.php** | ⬚ Pending | Remove migrated defines + 9 backward-compat aliases. Target: <200 lines or elimination. |
| J8 | **Update memory & specs** | ⬚ Pending | Document new enum/const architecture in memory and specs. |

### Execution Order

1. **J1** — Audit and categorize
2. **J2 + J3** — Core enums (action + status — highest usage)
3. **J4 + J5** — Domain enums (snapshot + sync)
4. **J6** — Const classes for non-enum values
5. **J7** — Shrink/remove constants.php
6. **J8** — Documentation

### Dependencies

- Feature I (camelCase) should complete I6/I7 first to avoid conflicting renames
- Feature G (enum migration) provides the pattern and namespace (`RiseupAsia\Enums`)

### Acceptance Criteria

- Zero raw `define()` calls for values that represent discrete choice sets
- `constants.php` either eliminated or reduced to <200 lines of true config constants
- All new enums under `RiseupAsia\Enums` namespace with `Type` suffix
- All new const classes under `includes/Constants/` with `Const` suffix
- Zero callers reference old `define()` names directly (backward-compat aliases acceptable during transition)

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
| PHP 8.1 minimum version bump | Verify all deployment targets run PHP 8.1+; update `MIN_PHP_VERSION` constant |
