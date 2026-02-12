# Memory: features/wordpress-plugin/database-snapshots
Updated: 2026-02-12

## Overview

The Database Snapshot System provides automated MySQL → SQLite backups for WordPress sites. It supports both **full** and **incremental** snapshot types with a parent-child hierarchy, parallel worker-pool exports, and cron-based execution.

## Key Architecture

### Snapshot Types

- **Full Snapshot**: Self-contained backup stored as a directory with `a-root.db` metadata and per-table `.sqlite` files
- **Incremental Snapshot**: Delta-only backup stored inside the parent full snapshot's `incremental/` subdirectory. Requires the parent full snapshot for restore.

### Cascade Delete Rule

Deleting a full snapshot **cascade-deletes** all its incremental children (both files and DB records). The cleaner checks for `incremental/` subdirectory and removes it recursively, then deletes all DB records whose `filepath LIKE parent_dir/incremental/%`.

### Incremental Restore Guard

Restoring an incremental snapshot is blocked if the parent full snapshot's directory or `a-root.db` is missing. Returns `INCREMENTAL_NO_PARENT` error code.

### Storage Location

```
wp-content/uploads/riseup-asia-uploader/snapshots/
├── 2026-02-12_full_backup-name/
│   ├── a-root.db
│   ├── wp_posts.sqlite
│   ├── wp_options.sqlite
│   ├── plugins/
│   │   └── plugin-name.zip
│   └── incremental/
│       ├── 01_2026-02-12/
│       │   ├── wp_posts.sqlite  (delta only)
│       │   └── ...
│       └── 02_2026-02-13/
│           └── ...
└── ...
```

### Provider Priority

1. User preference (if explicitly set)
2. WP Reset (if installed)
3. Updraft Plus (if installed)
4. Native SQLite engine (fallback)

### Cron-Based Execution

All snapshot operations run via WP-Cron, even "Snapshot Now":
- Prevents request timeouts
- Enables parallel table processing
- Provides resumability for large databases

### PHP Class Naming

All snapshot classes use PascalCase (no underscores):
- `RiseupSnapshotScheduler` - WP-Cron management
- `RiseupSnapshotDetector` - Provider detection
- `RiseupSnapshotCleaner` - Retention, cleanup, **cascade delete**
- `RiseupSnapshotOrchestrator` - Full & incremental backup orchestration
- `RiseupSnapshotWorker` - Per-table MySQL → SQLite export
- `RiseupIncrementalBackup` - Delta row export against a master snapshot
- `RiseupSnapshotProviderInterface` - Abstract base class
- `RiseupSnapshotProviderNative` - Native SQLite export
- `RiseupSnapshotProviderWPReset` - WP Reset integration
- `RiseupSnapshotProviderUpdraft` - UpdraftPlus integration

## Constants (from constants.php)

| Constant | Value |
|----------|-------|
| `RISEUP_SNAPSHOT_TYPE_FULL` | `full` |
| `RISEUP_SNAPSHOT_TYPE_INCREMENTAL` | `incremental` |
| `RISEUP_ERR_INCREMENTAL_NO_PARENT` | `INCREMENTAL_NO_PARENT` |

## React UI: Snapshot Hierarchy Display

The `RemoteSnapshotsPanel` groups snapshots visually:
- Full snapshots at the top level
- Incremental snapshots nested underneath with `ml-6` indent, left accent border, and `GitBranch` icon
- Delete dialog warns about cascade deletion when a full snapshot has incremental children
- `SnapshotRecord` type includes: `snapshot_type`, `parent_id`, `parent_dir`, `incremental_count`

## Related Files

- Spec: `spec/wordpress-plugin/database-snapshots.md`
- Orchestrator: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-orchestrator.php`
- Worker: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-worker.php`
- Incremental: `wp-plugins/riseup-asia-uploader/includes/class-incremental-backup.php`
- Cleaner: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-cleaner.php`
- Manager: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-manager.php`
- Scheduler: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-scheduler.php`
- Constants: `wp-plugins/riseup-asia-uploader/includes/constants.php`
- React Panel: `src/components/sites/RemoteSnapshotsPanel.tsx`
- React Types: `src/lib/api/types.ts`
