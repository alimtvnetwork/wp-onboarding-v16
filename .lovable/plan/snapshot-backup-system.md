# Revolutionary Snapshot Backup System — Detailed Plan

**Created:** 2026-02-07
**Status:** Planning

---

## Pre-requisite Fix: Deleted Files in Sync

### Problem
The current sync flow (GET manifest → compare → POST delta) handles `added` and `modified` files but has no mechanism to detect or propagate **deleted** files.

### Solution: Tombstone Diff Approach
1. **GET remote manifest** → returns `{ path, md5, modifiedAt }[]` for all remote files
2. **Local scan** → returns `{ path, md5, modifiedAt }[]` for all local files
3. **Comparison produces 4 categories:**
   - `added`: exists locally, NOT on remote → upload
   - `modified`: exists both, MD5 differs → upload (winner = newer timestamp or local-priority)
   - `deleted`: exists on remote, NOT locally → send delete list
   - `unchanged`: exists both, MD5 matches → skip
4. **POST sync payload** includes a `deletions[]` array alongside the multipart file uploads
5. **PHP plugin** processes deletions first (remove files), then writes uploads
6. **Safety**: Before deleting, PHP logs each file path to the audit trail with a `sync_delete` action type

### Files to Change
- `backend/internal/services/sync/service.go` — add deletion detection in diff logic
- `wp-plugins/riseup-asia-uploader/includes/class-riseup-sync-handler.php` — handle `deletions[]` param
- Frontend sync status UI — show deleted file count with red indicator

---

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    SNAPSHOT BACKUP SYSTEM                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Config Flow:                                                   │
│  config.json → (seed on first load / version change)            │
│       → settings.sqlite → UI Settings Panel                    │
│       → settings.sqlite is the runtime source of truth          │
│                                                                 │
│  Backup Types:                                                  │
│  ┌──────────┐  ┌──────────────┐                                │
│  │   FULL   │  │ INCREMENTAL  │                                │
│  │ (master) │  │  (delta)     │                                │
│  └────┬─────┘  └──────┬───────┘                                │
│       │               │                                         │
│       ▼               ▼                                         │
│  ┌─────────────────────────────────────┐                       │
│  │     Snapshot Directory Structure     │                       │
│  │  snapshots/                          │                       │
│  │  └── 2026-02-07_full_my-backup/     │                       │
│  │      ├── a-root.db                  │  ← dependency graph   │
│  │      ├── wp_posts.sqlite            │  ← one per table      │
│  │      ├── wp_postmeta.sqlite         │                       │
│  │      ├── wp_options.sqlite          │                       │
│  │      ├── ...                        │                       │
│  │      └── incremental/               │                       │
│  │          ├── 01_2026-02-08/         │                       │
│  │          │   ├── wp_posts.sqlite    │  ← only new/changed   │
│  │          │   └── wp_postmeta.sqlite │    rows since master  │
│  │          └── 02_2026-02-09/         │                       │
│  │              └── wp_options.sqlite  │                       │
│  └─────────────────────────────────────┘                       │
│                                                                 │
│  Plugin Snapshots:                                              │
│  snapshots/                                                     │
│  └── 2026-02-07_full_my-backup/                                │
│      └── plugins/                                               │
│          ├── my-plugin.zip                                      │
│          └── another-plugin.zip                                 │
│                                                                 │
│  Export ZIP Structure:                                           │
│  my-backup.zip                                                  │
│  ├── a-root.db          ← metadata + dependency graph          │
│  ├── wp_posts.sqlite                                           │
│  ├── wp_postmeta.sqlite                                        │
│  ├── ...                                                       │
│  ├── incremental/                                              │
│  │   ├── 01_2026-02-08/                                        │
│  │   │   └── ...                                               │
│  │   └── 02_2026-02-09/                                        │
│  │       └── ...                                               │
│  └── plugins/           ← if plugin snapshots included         │
│      ├── my-plugin.zip                                         │
│      └── another-plugin.zip                                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## a-root.db Schema

The `a-root.db` is the brain of every snapshot. It stores metadata AND the dependency graph.

```sql
-- Snapshot metadata
CREATE TABLE snapshot_meta (
    id              INTEGER PRIMARY KEY,
    title           TEXT NOT NULL,
    type            TEXT NOT NULL,          -- 'full' | 'incremental'
    created_at      TEXT NOT NULL,          -- ISO 8601
    created_by      TEXT,                   -- hostname/IP
    mysql_version   TEXT,
    wp_version      TEXT,
    plugin_version  TEXT,                   -- riseup-asia-uploader version
    table_count     INTEGER,
    total_rows      INTEGER,
    config_json     TEXT                    -- snapshot of settings at backup time
);

-- Table inventory (what tables are in this snapshot)
CREATE TABLE snapshot_tables (
    id              INTEGER PRIMARY KEY,
    table_name      TEXT NOT NULL UNIQUE,
    row_count       INTEGER NOT NULL,
    sqlite_file     TEXT NOT NULL,          -- relative path: 'wp_posts.sqlite'
    file_size_bytes INTEGER,
    checksum_md5    TEXT,
    exported_at     TEXT NOT NULL
);

-- Dependency graph (auto-detected from INFORMATION_SCHEMA)
CREATE TABLE table_dependencies (
    id              INTEGER PRIMARY KEY,
    parent_table    TEXT NOT NULL,          -- referenced table (seed first)
    child_table     TEXT NOT NULL,          -- dependent table (seed after parent)
    fk_column       TEXT NOT NULL,          -- the FK column name
    ref_column      TEXT NOT NULL,          -- the referenced column
    UNIQUE(child_table, fk_column)
);

-- Incremental backup registry
CREATE TABLE incremental_backups (
    id              INTEGER PRIMARY KEY,
    sequence_num    INTEGER NOT NULL,       -- 01, 02, 03...
    folder_name     TEXT NOT NULL,          -- '01_2026-02-08'
    created_at      TEXT NOT NULL,
    tables_changed  INTEGER,               -- count of tables with changes
    total_new_rows  INTEGER,
    relative_path   TEXT NOT NULL           -- 'incremental/01_2026-02-08/'
);

-- Plugin snapshots included
CREATE TABLE plugin_snapshots (
    id              INTEGER PRIMARY KEY,
    plugin_slug     TEXT NOT NULL,
    plugin_name     TEXT,
    plugin_version  TEXT,
    zip_file        TEXT NOT NULL,          -- relative path: 'plugins/my-plugin.zip'
    file_size_bytes INTEGER,
    checksum_md5    TEXT
);
```

---

## Settings Schema (in settings.sqlite)

```sql
CREATE TABLE IF NOT EXISTS snapshot_settings (
    key     TEXT PRIMARY KEY,
    value   TEXT NOT NULL,
    type    TEXT NOT NULL DEFAULT 'string'  -- 'string' | 'int' | 'bool' | 'json'
);

-- Default settings (seeded from config.json):
-- snapshot.mode              = 'per_table'        -- 'per_table' | 'single_db'
-- snapshot.backup_type       = 'incremental'      -- 'incremental' | 'full'
-- snapshot.worker_count      = 10                  -- concurrent table exports
-- snapshot.storage_path      = 'snapshots/'        -- relative to plugin data dir
-- snapshot.include_plugins   = true                -- include plugin ZIPs
-- snapshot.plugin_selection  = 'all'               -- 'all' | 'selective'
-- snapshot.retention_days    = 30                   -- auto-cleanup after N days
-- snapshot.retention_count   = 10                   -- max snapshots to keep
-- snapshot.compression       = true                -- ZIP compression on export
```

---

## Worker Pattern Design

```
┌──────────────────────────────────────────────────┐
│              SNAPSHOT WORKER POOL                  │
│                                                    │
│  Main Thread                                       │
│  ├── 1. Query INFORMATION_SCHEMA → table list     │
│  ├── 2. Build dependency graph → a-root.db        │
│  ├── 3. Topological sort → determine seed order   │
│  ├── 4. Create snapshot directory                  │
│  ├── 5. Dispatch to worker pool:                  │
│  │                                                 │
│  │   ┌────────┐ ┌────────┐ ┌────────┐            │
│  │   │Worker 1│ │Worker 2│ │Worker 3│ ... (N)     │
│  │   │wp_posts│ │wp_users│ │wp_terms│             │
│  │   └────────┘ └────────┘ └────────┘             │
│  │                                                 │
│  │   Each worker:                                  │
│  │   a) Opens new SQLite file for its table        │
│  │   b) CREATE TABLE mirroring MySQL schema        │
│  │   c) SELECT * FROM mysql_table (batched 1000)   │
│  │   d) INSERT INTO sqlite_table (transaction)     │
│  │   e) Report progress via callback               │
│  │                                                 │
│  ├── 6. Wait for all workers                       │
│  ├── 7. Update a-root.db with stats               │
│  ├── 8. Optional: ZIP all files for export         │
│  └── 9. Report completion via WS progress          │
└──────────────────────────────────────────────────┘
```

---

## Incremental Backup Logic

1. **Master (full) backup** is taken first — this is never deleted
2. **Incremental backups** track changes since the master:
   - For each table, query: `SELECT * FROM {table} WHERE id > {last_max_id}`
   - `last_max_id` is stored in `a-root.db → snapshot_tables.row_count` from master
   - Only tables with new rows get a `.sqlite` file in the incremental folder
3. **Restore order** for incremental:
   - Restore master first (using dependency graph order)
   - Apply incrementals in sequence (01, 02, 03...)
   - Each incremental INSERT uses `INSERT OR REPLACE` to handle updates
4. **Limitation**: Row deletions in MySQL between incrementals are NOT captured (documented trade-off)
   - Full backups should be scheduled periodically to reset the baseline

---

## Phases

### Phase 1: Deleted Files in Sync ✦ FOUNDATION
- Add deletion detection to Go sync comparison logic
- Add `deletions[]` parameter to PHP sync endpoint
- PHP processes deletions with audit logging
- Frontend shows deleted file count in sync status

### Phase 2: Snapshot Settings Infrastructure
- Add snapshot config keys to `config.json` schema
- Create `snapshot_settings` table in `settings.sqlite` (PHP)
- Implement seed-from-config logic (first load / version change)
- Add settings REST endpoints: `GET/POST /snapshots/settings`
- Go backend proxy for settings endpoints
- Frontend settings panel in Snapshots tab

### Phase 3: a-root.db and Dependency Graph
- PHP class `RiseupDependencyAnalyzer`
  - Query `INFORMATION_SCHEMA.KEY_COLUMN_USAGE` for FK relationships
  - Build directed graph of table dependencies
  - Topological sort for seed order
- Create `a-root.db` with schema above
- Unit tests for cycle detection and ordering

### Phase 4: Per-Table SQLite Export (Worker Pattern)
- PHP class `RiseupSnapshotWorker`
  - Worker pool manager with configurable concurrency
  - Each worker: MySQL table → SQLite file
  - Schema mirroring (column types, indexes)
  - Batched SELECT/INSERT (1000 rows per batch)
  - Progress reporting via `snapshot_progress` table
- Integrate with `a-root.db` metadata
- REST endpoint: `POST /snapshots/create` (enhanced)

### Phase 5: Full Backup Flow (End-to-End)
- Orchestrate: settings → dependency graph → worker pool → a-root.db finalization
- Plugin snapshot support (selective or all)
- ZIP export with a-root.db + all .sqlite files + plugins/
- WP-Cron scheduling for automated full backups
- Go backend proxy + frontend create/list/delete UI

### Phase 6: Incremental Backup ✅ DONE
- Track `last_max_id` per table from master SQLite files
- Incremental worker: only export rows with `id > last_max_id`
- Sequential folder naming: `01_YYYY-MM-DD/`, `02_YYYY-MM-DD/`
- Register in `a-root.db → incremental_backups` table
- ZIP export includes `incremental/` folder hierarchy
- Go backend proxy + REST endpoint registered

### Phase 7: Restore Engine ✅ DONE
- `RiseupRestoreEngine` class with full, incremental, and selective restore modes
- Dependency-aware restore order via topological sort from `a-root.db`
- Pre-restore safety backup (auto) via orchestrator
- Transactional MySQL restoration with FK checks disabled
- Incremental replay: master first, then incrementals in sequence using REPLACE
- Selective table restore: pick specific tables from snapshot
- Smart routing: `handle_restore_snapshot` auto-detects per-table vs legacy snapshots
- Progress reporting via audit trail logging
- REST endpoint: `POST /snapshots/restore` (enhanced to route through engine)
- PHP plugin version bumped to 1.15.0

### Phase 8: Import/Export ZIP Protocol ✅ DONE
- `RiseupSnapshotImport` class with dual-format detection (per-table vs legacy)
- Per-table import: validates a-root.db, checksums all .sqlite files, verifies incrementals & plugins
- `PRAGMA integrity_check` on every .sqlite file during import
- Go backend streaming proxy: multipart upload → temp file → forward to WordPress plugin
- `requestMultipart` method added to WordPress client for file uploads
- REST endpoint: `POST /sites/{id}/snapshots/import`
- PHP plugin version bumped to 1.16.0

### Phase 9: Frontend Visualization
- Snapshot history timeline with metadata cards
- Incremental chain visualization (master → inc01 → inc02)
- Table-level detail view (row counts, sizes, checksums)
- Dependency graph visualization (optional)
- Restore wizard with table selection

### Phase 10: Cleanup and Retention
- Auto-cleanup based on `retention_days` and `retention_count`
- Orphan file detection and removal
- Stuck snapshot cleanup (in-progress > 1 hour)
- Manual cleanup trigger from UI

---

## Config.json Addition

```json
{
  "snapshot": {
    "mode": "per_table",
    "backupType": "incremental",
    "workerCount": 10,
    "storagePath": "snapshots/",
    "includePlugins": true,
    "pluginSelection": "all",
    "retentionDays": 30,
    "retentionCount": 10,
    "compression": true,
    "batchSize": 1000
  }
}
```

---

## Files Changed Per Phase (Estimated)

| Phase | Go Backend | PHP Plugin | Frontend | New Files |
|-------|-----------|------------|----------|-----------|
| 1     | sync/service.go | class-riseup-sync-handler.php | SyncTreeView.tsx | 0 |
| 2     | config.go, proxy endpoints | class-riseup-settings.php | SnapshotSettings.tsx | 2-3 |
| 3     | — | class-riseup-dependency-analyzer.php | — | 1 |
| 4     | — | class-riseup-snapshot-worker.php | — | 1 |
| 5     | publish/snapshot proxy | class-riseup-snapshot-manager.php | SnapshotPanel.tsx | 2-3 |
| 6     | — | class-riseup-incremental-backup.php | — | 1 |
| 7     | restore proxy | class-riseup-restore-engine.php | RestoreWizard.tsx | 2 |
| 8     | streaming proxy | class-riseup-snapshot-import.php | SnapshotImport.tsx | 2 |
| 9     | — | — | Multiple visualization components | 3-5 |
| 10    | — | class-riseup-snapshot-cleanup.php | CleanupSettings.tsx | 1-2 |

---

## Verification Checklist (Per Phase)

After each phase, verify:
1. ✅ **Logic correctness** — does the code do what the spec says?
2. ✅ **Consistency** — naming conventions, error handling patterns, constant usage
3. ✅ **Error handling** — all errors wrapped with `apperror` or PHP `safe_execute`
4. ✅ **Logging** — follows logging-standards.md (names, not just IDs)
5. ✅ **Constants** — no hardcoded endpoints or magic strings
6. ✅ **Config seeding** — settings flow from config.json → settings.sqlite
7. ✅ **Worker safety** — proper mutex/locking, no race conditions
8. ✅ **Audit trail** — all operations logged with source attribution
9. ✅ **Version bump** — PHP plugin version incremented
10. ✅ **Backwards compatibility** — old snapshots still readable
