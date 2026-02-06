# Memory: features/wordpress-plugin/database-snapshots
Updated: 2026-02-06

## Overview

The Database Snapshot System provides automated MySQL → SQLite backups for WordPress sites. It integrates with WP Reset/Updraft when available, or uses a native SQLite export engine as fallback.

## Key Architecture

### Storage Location

```
wp-content/uploads/riseup-asia-uploader/snapshots/
├── manifest.json
├── 001_2026-02-06_143022.sqlite
├── 001_2026-02-06_143022.zip
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

All new snapshot classes use PascalCase (no underscores):
- `RiseupSnapshotScheduler` - WP-Cron management
- `RiseupSnapshotDetector` - Provider detection
- `RiseupSnapshotProviderInterface` - Abstract base class
- `RiseupSnapshotProviderNative` - Native SQLite export
- `RiseupSnapshotProviderWPReset` - WP Reset integration
- `RiseupSnapshotProviderUpdraft` - UpdraftPlus integration

## WP-Cron Hooks

| Hook | Description |
|------|-------------|
| `riseup_snapshot_scheduled` | Runs scheduled snapshots (daily/weekly/monthly) |
| `riseup_snapshot_immediate` | Runs "Snapshot Now" operations |
| `riseup_snapshot_cleanup` | Daily cleanup of old snapshots |

## Table Scope Options

| Scope | Description |
|-------|-------------|
| `all` | All database tables |
| `wordpress` | Core WP tables only |
| `content` | Posts, terms, comments |
| `custom` | User-selected tables |

## Scheduling Options

- Daily at configurable time
- Weekly on configurable day
- Monthly on configurable date
- Manual only (no automatic)

## Retention Policies

- Days-based: Keep snapshots for N days
- Count-based: Keep last N snapshots
- None: Manual cleanup only

## REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/snapshots` | List all |
| POST | `/snapshots/schedule` | Create snapshot |
| GET | `/snapshots/{id}` | Get details |
| DELETE | `/snapshots/{id}` | Remove |
| POST | `/snapshots/{id}/restore` | Restore DB |
| GET | `/snapshots/{id}/export` | Download ZIP |
| POST | `/snapshots/import` | Upload ZIP |
| GET | `/snapshots/settings` | Get settings |
| PUT | `/snapshots/settings` | Update settings |
| GET | `/snapshots/providers` | List providers |

## Safety Features

- Pre-restore backup (automatic)
- Transaction-wrapped table restores
- Integrity validation on import
- Confirmation required for restore

## Related Files

- Spec: `spec/wordpress-plugin/database-snapshots.md`
- Scheduler: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-scheduler.php`
- Detector: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-detector.php`
- Interface: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-provider-interface.php`
- Native: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-provider-native.php`
