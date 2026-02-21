# J1: constants.php Audit & Categorization

**Date:** 2026-02-14
**Source file:** `includes/constants.php` (915 lines, ~180 define() calls)
**Goal:** Categorize every constant into Enum candidate or Const-class candidate.

---

## Summary

| Target | Type | Constants | Lines Saved |
|--------|------|-----------|-------------|
| **ActionType** (enum) | Enum | 25 | ~78 |
| **StatusType** (enum) | Enum | 5 | ~15 |
| **PostStatusType** (enum) | Enum | 3 | ~9 |
| **SnapshotScopeType** (enum) | Enum | 4 | ~12 |
| **SnapshotStatusType** (enum) | Enum | 5 | ~15 |
| **SnapshotJobStatusType** (enum) | Enum | 4 | ~12 |
| **SnapshotFrequencyType** (enum) | Enum | 4 | ~12 |
| **SnapshotProviderType** (enum) | Enum | 4 | ~12 |
| **SnapshotTriggerType** (enum) | Enum | 3 | ~9 |
| **SnapshotTypeType** (enum) | Enum | 2 | ~6 |
| **SnapshotExportStatusType** (enum) | Enum | 3 | ~9 |
| **RetentionType** (enum) | Enum | 3 | ~9 |
| **AgentStatusType** (enum) | Enum | 3 | ~9 |
| **TriggerSourceType** (enum) | Enum | 5 | ~15 |
| **SyncActionType** (enum) | Enum | 2 | ~6 |
| **EndpointConst** (class) | Const | 40 | ~120 |
| **TableConst** (class) | Const | 8 | ~24 |
| **ErrorCodeConst** (class) | Const | 10 | ~30 |
| **HttpConst** (class) | Const | 7 | ~21 |
| **DefaultConst** (class) | Const | 12 | ~36 |
| **CronConst** (class) | Const | 5 | ~15 |
| **MessageConst** (class) | Const | 14 | ~42 |
| **PluginConst** (class) | Const | 5 | ~15 |
| **PathConst** (class) | Const | 7 | ~21 |
| **ApiConst** (class) | Const | 3 | ~9 |
| **Already migrated** | — | 9 | — (aliases) |
| **TOTAL** | — | **~180** | **~600** |

---

## 1. ENUM CANDIDATES (discrete choice sets)

### 1.1 ActionType (25 constants → enum ActionType: string)

Transaction logging actions — closed set of known action identifiers.

| Constant | Value | Notes |
|----------|-------|-------|
| `ACTION_UPLOAD` | `'upload'` | |
| `ACTION_UPLOAD_ACTIVE` | `'upload_active'` | |
| `ACTION_UPLOAD_INITIATED` | `'upload_initiated'` | |
| `ACTION_ENABLE` | `'enable'` | |
| `ACTION_DISABLE` | `'disable'` | |
| `ACTION_DELETE` | `'delete'` | |
| `ACTION_FILE_REPLACE` | `'file_replace'` | |
| `ACTION_FILE_DELETE` | `'file_delete'` | |
| `ACTION_SYNC` | `'sync'` | |
| `ACTION_SYNC_DELETE` | `'sync_delete'` | |
| `ACTION_POST_CREATE` | `'post_create'` | |
| `ACTION_POST_UPDATE` | `'post_update'` | |
| `ACTION_CATEGORY_CREATE` | `'category_create'` | |
| `ACTION_MEDIA_UPLOAD` | `'media_upload'` | |
| `ACTION_AUTH_FAILED` | `'auth_failed'` | |
| `ACTION_EXPORT_SELF` | `'export_self'` | |
| `ACTION_EXPORT_PLUGIN` | `'export_plugin'` | |
| `ACTION_UPDATE_CHECK` | `'update_check'` | |
| `ACTION_UPDATE_RESOLVE` | `'update_resolve'` | |
| `ACTION_UPDATE_DOWNLOAD` | `'update_download'` | |
| `ACTION_UPDATE_INSTALL` | `'update_install'` | |
| `ACTION_AGENT_ADD` | `'agent_add'` | |
| `ACTION_AGENT_REMOVE` | `'agent_remove'` | |
| `ACTION_AGENT_TEST` | `'agent_test'` | |
| `ACTION_AGENT_SYNC` | `'agent_sync'` | |
| `ACTION_AGENT_PLUGIN_ENABLE` | `'agent_plugin_enable'` | |
| `ACTION_AGENT_PLUGIN_DISABLE` | `'agent_plugin_disable'` | |
| `ACTION_AGENT_PLUGIN_DELETE` | `'agent_plugin_delete'` | |
| `ACTION_AGENT_PLUGIN_UPDATE` | `'agent_plugin_update'` | |
| `ACTION_SNAPSHOT_CREATE` | `'snapshot_create'` | |
| `ACTION_SNAPSHOT_RESTORE` | `'snapshot_restore'` | |
| `ACTION_SNAPSHOT_DELETE` | `'snapshot_delete'` | |
| `ACTION_SNAPSHOT_EXPORT` | `'snapshot_export'` | |
| `ACTION_SNAPSHOT_IMPORT` | `'snapshot_import'` | |
| `ACTION_SNAPSHOT_CLEANUP` | `'snapshot_cleanup'` | |
| `ACTION_SNAPSHOT_FULL_BACKUP` | `'snapshot_full_backup'` | |
| `ACTION_SNAPSHOT_INCREMENTAL` | `'snapshot_incremental'` | |
| `ACTION_SNAPSHOT_RESTORE_PERTABLE` | `'snapshot_restore_pertable'` | |
| `ACTION_SNAPSHOT_IMPORT_PERTABLE` | `'snapshot_import_pertable'` | |
| `ACTION_SNAPSHOT_ZIP_BUILD` | `'snapshot_zip_build'` | |
| `ACTION_SNAPSHOT_ZIP_EXPIRE` | `'snapshot_zip_expire'` | |
| `ACTION_SNAPSHOT_ZIP_DOWNLOAD` | `'snapshot_zip_download'` | |

**Note:** This is 42 constants total — much larger than initially estimated. Consider sub-enums or a single large ActionType.

### 1.2 StatusType (5 constants → enum StatusType: string)

| Constant | Value |
|----------|-------|
| `STATUS_SUCCESS` | `'success'` |
| `STATUS_FAILED` | `'failed'` |

Only 2, but adding pending/partial later is expected.

### 1.3 PostStatusType (3 constants → enum PostStatusType: string)

| Constant | Value |
|----------|-------|
| `POST_STATUS_PUBLISH` | `'publish'` |
| `POST_STATUS_DRAFT` | `'draft'` |
| `POST_STATUS_PENDING` | `'pending'` |

### 1.4 SnapshotScopeType (4 constants)

| Constant | Value |
|----------|-------|
| `SNAPSHOT_SCOPE_ALL` | `'all'` |
| `SNAPSHOT_SCOPE_WORDPRESS` | `'wordpress'` |
| `SNAPSHOT_SCOPE_CONTENT` | `'content'` |
| `SNAPSHOT_SCOPE_CUSTOM` | `'custom'` |

### 1.5 SnapshotStatusType (5 constants)

| Constant | Value |
|----------|-------|
| `SNAPSHOT_STATUS_PENDING` | `'pending'` |
| `SNAPSHOT_STATUS_SCHEDULED` | `'scheduled'` |
| `SNAPSHOT_STATUS_RUNNING` | `'running'` |
| `SNAPSHOT_STATUS_COMPLETE` | `'complete'` |
| `SNAPSHOT_STATUS_FAILED` | `'failed'` |

### 1.6 SnapshotJobStatusType (4 constants)

| Constant | Value |
|----------|-------|
| `SNAPSHOT_JOB_STATUS_QUEUED` | `'queued'` |
| `SNAPSHOT_JOB_STATUS_PROCESSING` | `'processing'` |
| `SNAPSHOT_JOB_STATUS_COMPLETE` | `'complete'` |
| `SNAPSHOT_JOB_STATUS_FAILED` | `'failed'` |

### 1.7 SnapshotFrequencyType (4 constants)

| Constant | Value |
|----------|-------|
| `SNAPSHOT_FREQ_MANUAL` | `'manual'` |
| `SNAPSHOT_FREQ_DAILY` | `'daily'` |
| `SNAPSHOT_FREQ_WEEKLY` | `'weekly'` |
| `SNAPSHOT_FREQ_MONTHLY` | `'monthly'` |

### 1.8 SnapshotProviderType (4 constants)

| Constant | Value |
|----------|-------|
| `SNAPSHOT_PROVIDER_WP_RESET` | `'wp_reset'` |
| `SNAPSHOT_PROVIDER_UPDRAFT` | `'updraft'` |
| `SNAPSHOT_PROVIDER_NATIVE` | `'native'` |
| `SNAPSHOT_PROVIDER_AUTO` | `'auto'` |

### 1.9 SnapshotTriggerType (3 constants)

| Constant | Value |
|----------|-------|
| `SNAPSHOT_TRIGGER_MANUAL` | `'manual'` |
| `SNAPSHOT_TRIGGER_CRON` | `'cron'` |
| `SNAPSHOT_TRIGGER_API` | `'api'` |

### 1.10 SnapshotExportStatusType (3 constants)

| Constant | Value |
|----------|-------|
| `SNAPSHOT_EXPORT_STATUS_VALID` | `'valid'` |
| `SNAPSHOT_EXPORT_STATUS_EXPIRED` | `'expired'` |
| `SNAPSHOT_EXPORT_STATUS_BUILDING` | `'building'` |

### 1.11 SnapshotTypeType (2 constants) — consider merging into SnapshotScopeType or standalone

| Constant | Value |
|----------|-------|
| `SNAPSHOT_TYPE_FULL` | `'full'` |
| `SNAPSHOT_TYPE_INCREMENTAL` | `'incremental'` |

### 1.12 RetentionType (3 constants)

| Constant | Value |
|----------|-------|
| `RETENTION_TYPE_DAYS` | `'days'` |
| `RETENTION_TYPE_COUNT` | `'count'` |
| `RETENTION_TYPE_NONE` | `'none'` |

### 1.13 AgentStatusType (3 constants)

| Constant | Value |
|----------|-------|
| `AGENT_STATUS_PENDING` | `'pending'` |
| `AGENT_STATUS_CONNECTED` | `'connected'` |
| `AGENT_STATUS_ERROR` | `'error'` |

### 1.14 TriggerSourceType (5 constants)

| Constant | Value |
|----------|-------|
| `TRIGGERED_BY_API` | `'api'` |
| `TRIGGERED_BY_DASHBOARD` | `'dashboard'` |
| `TRIGGERED_BY_AGENT` | `'agent_push'` |
| `TRIGGERED_BY_CRON` | `'cron'` |
| `TRIGGERED_BY_CLI` | `'cli'` |

### 1.15 SyncActionType (2 constants)

| Constant | Value |
|----------|-------|
| `SYNC_ACTION_REPLACE` | `'replace'` |
| `SYNC_ACTION_DELETE` | `'delete'` |

---

## 2. CONST-CLASS CANDIDATES (non-discrete, config/identity values)

### 2.1 EndpointConst (final class — 40 constants)

All `ENDPOINT_*` constants — REST route path segments. Not an enum because they're open-ended config, not a discrete choice set.

- Core: `STATUS`, `UPLOAD`, `PLUGINS`, `EXPORT_SELF`, `POSTS`, `CATEGORIES`, `LOGS`, `LOGS_STATS`, `PLUGIN_FILES`, `PLUGIN_FILE`, `PLUGIN_ENABLE`, `PLUGIN_DISABLE`, `PLUGIN_DELETE`, `PLUGIN_EXISTS`, `PLUGIN_EXPORT`, `OPENAPI`, `OPCACHE_RESET`
- Agent: `AGENTS`, `AGENT_TEST`, `AGENT_SYNC`, `AGENT_ACTION`, `AGENT_HISTORY`, `AGENTS_LIST`, `AGENTS_ADD`, `AGENTS_REMOVE`, `AGENTS_TEST`, `AGENTS_SYNC`, `AGENTS_PLUGINS`
- Snapshot: `SNAPSHOTS`, `SNAPSHOT_LIST`, `SNAPSHOT_SCHEDULE`, `SNAPSHOT_INFO`, `SNAPSHOT_DELETE`, `SNAPSHOT_RESTORE`, `SNAPSHOT_EXPORT`, `SNAPSHOT_IMPORT`, `SNAPSHOT_SETTINGS`, `SNAPSHOT_PROVIDERS`, `SNAPSHOT_TABLES`, `SNAPSHOT_DEPENDENCIES`, `SNAPSHOT_EXPORT_PERTABLE`, `SNAPSHOT_PROGRESS`, `SNAPSHOT_FULL_BACKUP`, `SNAPSHOT_INCREMENTAL`, `SNAPSHOT_CLEANUP`, `SNAPSHOT_DOWNLOAD`, `SNAPSHOT_DOWNLOAD_FILE`
- Sync: `SYNC_MANIFEST`, `SYNC`
- Error: `ERROR_LOGS`, `ERROR_SESSIONS`

### 2.2 TableConst (final class — 8 constants)

| Constant | Value |
|----------|-------|
| `TABLE_TRANSACTIONS` | `'transactions'` |
| `TABLE_AGENT_SITES` | `'agent_sites'` |
| `TABLE_AGENT_ACTIONS` | `'agent_actions'` |
| `TABLE_SNAPSHOTS` | `'snapshots'` |
| `TABLE_SNAPSHOT_PROGRESS` | `'snapshot_progress'` |
| `TABLE_SNAPSHOT_JOBS` | `'snapshot_jobs'` |
| `TABLE_SNAPSHOT_SETTINGS` | `'snapshot_settings'` |
| `TABLE_SNAPSHOT_EXPORTS` | `'snapshot_exports'` |
| `TABLE_FILE_CACHE` | `'file_cache'` |

### 2.3 ErrorCodeConst (final class — 10 constants)

All `ERR_*` constants:
`ERR_SNAPSHOT_LOCK_EXISTS`, `ERR_SNAPSHOT_NOT_FOUND`, `ERR_SNAPSHOT_CORRUPT`, `ERR_SNAPSHOT_TOO_LARGE`, `ERR_RESTORE_FAILED`, `ERR_RESTORE_NO_CONFIRM`, `ERR_PROVIDER_NOT_AVAILABLE`, `ERR_INCREMENTAL_NO_PARENT`, `ERR_EXPORT_NOT_FOUND`, `ERR_EXPORT_BUILD_FAILED`, `ERR_EXPORT_TOKEN_INVALID`

### 2.4 HttpConst (final class — 7 constants)

`HTTP_OK`, `HTTP_CREATED`, `HTTP_BAD_REQUEST`, `HTTP_UNAUTHORIZED`, `HTTP_FORBIDDEN`, `HTTP_NOT_FOUND`, `HTTP_SERVER_ERROR`

### 2.5 DefaultConst (final class — 12 constants)

Config defaults and limits:
`DEFAULT_LIMIT`, `MAX_LIMIT`, `SNAPSHOT_BATCH_SIZE`, `SNAPSHOT_MAX_SIZE_MB`, `SNAPSHOT_RETENTION_DAYS_DEFAULT`, `SNAPSHOT_RETENTION_COUNT_DEFAULT`, `SNAPSHOT_WORKER_POOL_MIN`, `SNAPSHOT_WORKER_POOL_MAX`, `SNAPSHOT_WORKER_POOL_DEFAULT`, `SNAPSHOT_STUCK_HOURS`, `UPDATE_CACHE_DAYS_DEFAULT`, `UPDATE_MAX_REDIRECTS`, `LOG_RETRIEVAL_MAX_LINES`

### 2.6 CronConst (final class — 5 constants)

`CRON_SNAPSHOT_SCHEDULED`, `CRON_SNAPSHOT_IMMEDIATE`, `CRON_SNAPSHOT_CLEANUP`, `CRON_SNAPSHOT_RESTORE`, `CRON_SNAPSHOT_INCREMENTAL`, `CRON_SNAPSHOT_WORKER_BATCH`

### 2.7 MessageConst (final class — 14 constants)

All `MSG_*` constants:
`MSG_SUCCESS`, `MSG_UNAUTHORIZED`, `MSG_FORBIDDEN`, `MSG_INVALID_REQUEST`, `MSG_PLUGIN_NOT_FOUND`, `MSG_UPLOAD_FAILED`, `MSG_ACTIVATION_FAILED`, `MSG_DEACTIVATION_FAILED`, `MSG_DELETE_FAILED`, `MSG_POST_CREATE_FAILED`, `MSG_POST_UPDATE_FAILED`, `MSG_CATEGORY_CREATE_FAILED`, `MSG_MEDIA_UPLOAD_FAILED`, `MSG_DB_ERROR`, `MSG_FILE_IGNORED`

### 2.8 PluginConst (final class — 5 constants)

`PLUGIN_VERSION`, `PLUGIN_SLUG`, `PLUGIN_NAME`, `MIN_WP_VERSION`, `MIN_PHP_VERSION`

### 2.9 PathConst (final class — 7 constants)

`UPLOADS_SUBDIR`, `LOGS_SUBDIR`, `LOG_FILENAME`, `ERROR_LOG_FILENAME`, `STACKTRACE_FILENAME`, `DB_FILENAME`, `TEMP_SUBDIR`, `SNAPSHOTS_SUBDIR`, `SNAPSHOT_EXPORTS_SUBDIR`, `IGNORE_FILENAME`

### 2.10 ApiConst (final class — 3+2 constants)

`API_NAMESPACE`, `API_VERSION`, `API_FULL_NAMESPACE`, `LEGACY_NAMESPACE`, `LOG_PREFIX`

### 2.11 OptionConst (final class — 2 constants)

`OPTION_SNAPSHOT_SETTINGS`, `OPTION_LOG_RETRIEVAL`

---

## 3. ALREADY MIGRATED (backward-compat aliases — remove in J7)

| Constant | Aliased From |
|----------|-------------|
| `CAP_MANAGE_PLUGINS` | `CapabilityType::ActivatePlugins->value` |
| `CAP_MANAGE_POSTS` | `CapabilityType::PublishPosts->value` |
| `CAP_UPLOAD_MEDIA` | `CapabilityType::UploadFiles->value` |
| `CAP_VIEW_LOGS` | `CapabilityType::ManageOptions->value` |
| `UPLOAD_SOURCE_SCRIPT` | `UploadSourceType::Script->value` |
| `UPLOAD_SOURCE_REST_API` | `UploadSourceType::RestApi->value` |
| `UPLOAD_SOURCE_ADMIN_UI` | `UploadSourceType::AdminUi->value` |
| `UPLOAD_SOURCE_WP_CLI` | `UploadSourceType::WpCli->value` |
| `UPLOAD_SOURCES_VALID` | `UploadSourceType::valid_values()` |

---

## 4. DB_WAL_MODE (1 boolean constant — keep in DefaultConst or PathConst)

`DB_WAL_MODE` → `DefaultConst::DB_WAL_MODE` (boolean, not an enum)

---

## 5. REVISED PHASE PLAN

Based on this audit, the original J2-J6 plan should be revised:

| Phase | Target | Constants | Priority |
|-------|--------|-----------|----------|
| J2 | **ActionType** enum | 42 | HIGH — most callers |
| J3 | **StatusType + PostStatusType** enums | 5 | HIGH — universal |
| J4a | **SnapshotStatusType + SnapshotJobStatusType** | 9 | MED |
| J4b | **SnapshotScopeType + SnapshotFrequencyType + SnapshotTypeType** | 10 | MED |
| J4c | **SnapshotProviderType + SnapshotTriggerType + SnapshotExportStatusType + RetentionType** | 13 | MED |
| J5a | **AgentStatusType + TriggerSourceType + SyncActionType** | 10 | MED |
| J6a | **EndpointConst** class | 40 | LOW (still usable as-is) |
| J6b | **TableConst + ErrorCodeConst** classes | 19 | LOW |
| J6c | **HttpConst + MessageConst** classes | 21 | LOW |
| J6d | **DefaultConst + CronConst + PathConst + PluginConst + ApiConst + OptionConst** | 30 | LOW |
| J7 | Remove migrated defines + backward-compat aliases | 9+ | FINAL |
