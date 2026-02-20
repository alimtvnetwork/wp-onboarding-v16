# ResponseKeyType — Case Inventory & Usage Map

> **Enum**: `RiseupAsia\Enums\ResponseKeyType`  
> **File**: `includes/Enums/ResponseKeyType.php`  
> **As of**: v1.62.0  
> **Total cases**: 39  
> **Total usages**: ~2,100+ across 75 files

---

## Envelope Keys

Used in nearly every REST response and internal result array.

| Case | Value | Usages | Primary Locations |
|------|-------|--------|-------------------|
| `Success` | `success` | ~56 files | All REST handlers, snapshot providers, helpers, cleaner, orchestrator |
| `Error` | `error` | ~60 files | All error payloads, log contexts, snapshot CRUD, sync, agents |
| `Message` | `message` | ~15 files | REST error responses, envelope builders, route traits |
| `Data` | `data` | ~10 files | SyncManifestTrait, REST response wrappers, status payloads |
| `Code` | `code` | ~12 files | Error responses with typed codes (SnapshotErrorType, WpErrorCodeType) |
| `Valid` | `valid` | ~8 files | Import validation, manifest validation, agent validation |
| `Errors` | `errors` | ~25 files | Batch results, cleaner phases, worker jobs, restore engine |
| `Cached` | `cached` | ~5 files | FileCache manifest, sync manifest, export handler |
| `Phase` | `phase` | ~10 files | Snapshot lifecycle logging (initiated, streaming, complete) |
| `Reason` | `reason` | ~5 files | Retention deletion details, cleaner audit |

---

## Domain Collection Keys

| Case | Value | Usages | Primary Locations |
|------|-------|--------|-------------------|
| `Total` | `total` | ~8 files | Pagination (agents, snapshots, logs), manifest stats |
| `Agents` | `agents` | ~4 files | AgentCrudReadTrait, agent list responses |
| `Actions` | `actions` | ~3 files | Action log list responses |
| `Logs` | `logs` | ~4 files | Log list responses, diagnostics |
| `Snapshots` | `snapshots` | ~4 files | Snapshot list responses, UpdraftCrudTrait |
| `Sql` | `sql` | ~3 files | Query builder results, database search |
| `Params` | `params` | ~3 files | Query builder parameter arrays |
| `Sets` | `sets` | ~2 files | Batch set operations |
| `Plugins` | `plugins` | ~5 files | Orchestrator plugin archiving, import execution |
| `Tables` | `tables` | ~15 files | Snapshot CRUD, restore, export, worker batches |

---

## File & Size Keys

| Case | Value | Usages | Primary Locations |
|------|-------|--------|-------------------|
| `Rows` | `rows` | ~12 files | Table info, restore results, batch progress, worker exports |
| `Bytes` | `bytes` | ~4 files | Storage calculations, sync payloads |
| `Size` | `size` | ~15 files | ZIP sizes, file entries, plugin archiving, export results |
| `FileSize` | `file_size` | ~8 files | Snapshot records, incremental exports, manifest entries |
| `Path` | `path` | ~12 files | Log contexts, file manifests, REST responses |
| `Filename` | `filename` | ~15 files | Snapshot CRUD, export/import, cleaner, manifest |
| `Checksum` | `checksum` | ~4 files | Incremental exports, file integrity, sync |
| `Duration` | `duration` | ~12 files | All timed operations (backup, restore, cleanup, sync) |
| `Count` | `count` | ~5 files | Plugin archiving, orchestrator results |
| `Files` | `files` | ~8 files | FileCache manifest, sync manifest, plugin list |
| `Directory` | `directory` | ~6 files | Snapshot creation results, backup responses |
| `Scope` | `scope` | ~8 files | Snapshot CRUD, export manifest, import records |
| `Exported` | `exported` | ~4 files | Worker batch progress, export results |
| `Entry` | `entry` | ~3 files | Plugin archive entries, orchestrator |
| `Computed` | `computed` | ~3 files | FileCache manifest stats, sync cache stats |
| `Removed` | `removed` | ~5 files | FileCache pruning, cleaner orphan results |

---

## Snapshot-Domain Keys

| Case | Value | Usages | Primary Locations |
|------|-------|--------|-------------------|
| `SnapshotId` | `snapshot_id` | ~12 files | All snapshot operations, export, import, restore, audit |
| `Sequence` | `sequence` | ~6 files | Incremental backups, export manifests, registration |
| `FolderName` | `folder_name` | ~5 files | Incremental backup directories, registration |
| `TablesChanged` | `tables_changed` | ~4 files | Incremental registration, export results |
| `TotalRows` | `total_rows` | ~15 files | Snapshot records, worker progress, restore, import |
| `TotalNewRows` | `total_new_rows` | ~4 files | Incremental registration, export results |
| `ZipSize` | `zip_size` | ~4 files | Backup exec responses, export results |
| `BackupId` | `backup_id` | ~3 files | Pre-restore backup references |
| `ZipFailed` | `zip_failed` | ~3 files | Snapshot creation error flags |
| `SkipAudit` | `skip_audit` | ~4 files | Scheduler cron results, no-op cleanup |
| `TablesRestored` | `tables_restored` | ~4 files | Restore engine results, audit logging |

---

## Permitted Magic String Exceptions

The following array key patterns intentionally remain as literal strings:

| Pattern | Reason |
|---------|--------|
| `$snapshot['filename']`, `$snapshot['filepath']` | Database column reads (schema contract) |
| `$error['message']`, `$error['type']` | PHP native `error_get_last()` structure |
| `$upload['error']` | PHP `$_FILES` superglobal structure |
| `$uploadDir['error']` | WordPress `wp_upload_dir()` return structure |
| `$body['scope']`, `$body['tables']` | Incoming REST request body keys |
| `$row['count']`, `$total['count']` | SQL alias reads (`COUNT(*) AS count`) |
| `$info['Name']`, `$info['Rows']` | MySQL `SHOW TABLE STATUS` column names |
| `$cached['modified_at']`, `$cached['md5_hash']` | SQLite cache table column reads |
| `$where['sql']`, `$filter['params']` | Internal query builder structures |
| `$r['label']`, `$r['file']` | Diagnostic/boot loader internal arrays |
| `$envelope['Errors']` | PascalCase Go-compatible envelope keys |
| i18n translation domain `'riseup-asia-uploader'` | WordPress gettext extraction requirement |

---

## Helper Methods

```php
// Type-safe comparison (preferred over === with ->value)
$key->isEqual(ResponseKeyType::Success);

// Positive boolean logic (P3 — eliminates raw negation)
$key->isOtherThan(ResponseKeyType::Success);
```

---

## Cross-Language Sync

| Language | Location | Sync Status |
|----------|----------|-------------|
| PHP | `includes/Enums/ResponseKeyType.php` | **Source of truth** |
| Go | `backend/internal/wordpress/response_key_type.go` | Must mirror PHP cases |
| TypeScript | `src/lib/constants.ts` | Must mirror PHP values |
