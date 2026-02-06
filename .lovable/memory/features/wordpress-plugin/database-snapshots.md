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

## Safety Features

- Pre-restore backup (automatic)
- Transaction-wrapped table restores
- Integrity validation on import
- Confirmation required for restore

## Related Files

- Spec: `spec/wordpress-plugin/database-snapshots.md`
- Implementation: `wp-plugins/riseup-asia-uploader/includes/class-snapshot-*.php`
