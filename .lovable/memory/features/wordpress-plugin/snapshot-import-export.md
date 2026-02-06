# Memory: features/wordpress-plugin/snapshot-import-export
Updated: 2026-02-06

## Overview

Phase 23 implements full import/export/restore functionality for database snapshots. The `RiseupSnapshotManager` class serves as the central coordinator for all snapshot file operations including ZIP handling, manifest validation, and database restoration.

## Architecture

### RiseupSnapshotManager (Singleton)

Located at `wp-plugins/riseup-asia-uploader/includes/class-snapshot-manager.php`:

```php
// Get instance
$manager = RiseupSnapshotManager::getInstance($logger, $db);

// Core operations
$manager->createSnapshot($options);
$manager->restoreSnapshot($snapshot_id, $options);
$manager->exportSnapshot($snapshot_id);
$manager->importSnapshot($uploaded_filepath);
$manager->deleteSnapshot($snapshot_id);

// Settings & listing
$manager->getSettings();
$manager->updateSettings($settings);
$manager->listSnapshots($limit, $offset);
$manager->getProviders();
$manager->getAvailableTables();
```

## Export Process

1. Retrieve snapshot record from database
2. Validate SQLite file exists
3. Create ZIP archive with:
   - SQLite database file
   - `manifest.json` with metadata
4. Return filepath for download

### Manifest Structure

```json
{
  "version": "1.9.0",
  "format_version": "1.0",
  "created_at": "2026-02-06T12:00:00Z",
  "exported_at": "2026-02-06T12:00:00Z",
  "snapshot": {
    "id": 1,
    "sequence": 1,
    "filename": "001_2026-02-06_120000.sqlite",
    "scope": "wordpress",
    "provider": "native",
    "tables": ["wp_posts", "wp_options", ...],
    "total_rows": 15000,
    "file_size": 1048576,
    "created_at": "2026-02-06T12:00:00Z"
  },
  "source": {
    "wp_version": "6.4",
    "php_version": "8.1.0",
    "site_url": "https://example.com",
    "db_prefix": "wp_"
  }
}
```

## Import Process

1. Validate uploaded file is ZIP
2. Extract to temp directory (`temp/import_{uniqid}`)
3. Parse and validate `manifest.json`
4. Validate SQLite database integrity (`PRAGMA integrity_check`)
5. Verify `_snapshot_meta` table exists
6. Copy SQLite file to snapshots directory with new sequence number
7. Create database record with `import_source` metadata
8. Clean up temp directory

### Import Validation

```php
// Manifest validation
- Required fields: version, snapshot
- Snapshot fields: filename, tables, scope
- Format version compatibility check

// SQLite validation
- PRAGMA integrity_check = 'ok'
- _snapshot_meta table exists
```

## Restore Process

1. Verify `confirm=true` option (safety)
2. Create pre-restore backup (optional, default enabled)
3. Open SQLite database file
4. Determine tables to restore (full or selective)
5. For each table:
   - Disable foreign key checks
   - Truncate MySQL table
   - Batch import rows from SQLite
   - Re-enable foreign key checks
6. Return restore summary

### Restore Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `confirm` | bool | required | Must be `true` to proceed |
| `create_backup` | bool | `true` | Create pre-restore snapshot |
| `require_backup` | bool | `false` | Fail if backup fails |
| `mode` | string | `full` | `full` or `selective` |
| `tables` | array | all | Tables for selective restore |
| `strict` | bool | `false` | Stop on first table error |

## Provider Delegation

The native provider (`RiseupSnapshotProviderNative`) delegates restore and import operations to the manager:

```php
public function restoreSnapshot($snapshot_id, $options) {
    $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
    return $manager->restoreSnapshot($snapshot_id, $options);
}

public function importSnapshot($filepath) {
    $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
    return $manager->importSnapshot($filepath);
}
```

## Path Handling

All file operations use `RiseupPathUtils` for:
- Safe path joining
- Directory creation with security files
- Path traversal prevention
- Safe deletion with logging

## Error Codes

| Code | Description |
|------|-------------|
| `SNAPSHOT_NOT_FOUND` | Snapshot record or file not found |
| `RESTORE_NO_CONFIRM` | Missing confirmation flag |
| `SNAPSHOT_CORRUPT` | SQLite integrity check failed |
| `PROVIDER_NOT_AVAILABLE` | No snapshot provider available |

## Related Files

- `wp-plugins/riseup-asia-uploader/includes/class-snapshot-manager.php` - Central manager
- `wp-plugins/riseup-asia-uploader/includes/class-snapshot-provider-native.php` - Provider with delegation
- `wp-plugins/riseup-asia-uploader/includes/class-snapshot-cleaner.php` - Cleanup logic
- `wp-plugins/riseup-asia-uploader/includes/class-path-utils.php` - Path utilities
