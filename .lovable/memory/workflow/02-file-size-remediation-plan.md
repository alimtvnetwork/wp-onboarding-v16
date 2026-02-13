# File Size Remediation Plan — Max 200 Lines Per File

Updated: 2026-02-13

## Overview

**Goal**: No PHP file exceeds 200 lines of code. After file splitting is complete, scan for remaining 15-line method violations.

## Current File Size Audit

| File | Lines | Over by | Priority |
|------|-------|---------|----------|
| `riseup-asia-uploader.php` | 5,604 | 5,404 | 🔴 Critical |
| `templates/admin-errors.php` | ~1,200+ | ~1,000+ | 🔴 Critical |
| `templates/admin-settings.php` | ~1,200+ | ~1,000+ | 🔴 Critical |
| `templates/admin-snapshots.php` | ~1,000+ | ~800+ | 🔴 Critical |
| `templates/admin-logs.php` | ~800+ | ~600+ | 🔴 Critical |
| `templates/admin-agents.php` | ~800+ | ~600+ | 🔴 Critical |
| `Admin/Admin.php` | 1,017 | 817 | 🔴 Critical |
| `Agent/AgentManager.php` | 807 | 607 | 🟠 High |
| `Update/UpdateResolver.php` | 599 | 399 | 🟠 High |
| `Post/PostManager.php` | 487 | 287 | 🟠 High |
| `Logging/Logger.php` | ~550 | ~350 | 🟠 High |
| `Logging/FileLogger.php` | ~580 | ~380 | 🟠 High |
| `Database/Database.php` | ~1,200+ | ~1,000+ | 🔴 Critical |
| `Database/Orm.php` | ~500+ | ~300+ | 🟠 High |
| `Database/FileCache.php` | ~400+ | ~200+ | 🟡 Medium |
| `Database/RootDb.php` | ~400+ | ~200+ | 🟡 Medium |
| `Snapshot/IncrementalBackup.php` | ~600+ | ~400+ | 🟠 High |
| `Snapshot/SnapshotOrchestrator.php` | ~600+ | ~400+ | 🟠 High |
| `Snapshot/SnapshotWorker.php` | ~600+ | ~400+ | 🟠 High |
| `Snapshot/RestoreEngine.php` | ~500+ | ~300+ | 🟠 High |
| `Snapshot/SnapshotManager.php` | ~500+ | ~300+ | 🟠 High |
| `Snapshot/SnapshotExporter.php` | ~400+ | ~200+ | 🟡 Medium |
| `Snapshot/SnapshotImport.php` | ~400+ | ~200+ | 🟡 Medium |
| `Helpers/PathUtils.php` | ~400+ | ~200+ | 🟡 Medium |

Files already ≤200 lines (no action needed):
- `Enums/*.php` (all small enum files)
- `Helpers/BooleanHelpers.php`, `ErrorChecker.php`, `EnvelopeBuilder.php`, `InitHelpers.php`
- `Upload/UploadIgnore.php`
- `Snapshot/SqliteSchemaConverter.php`, `SnapshotCleaner.php`, `SnapshotDetector.php`, `SnapshotFactory.php`
- `Snapshot/SnapshotProviderInterface.php`, `SnapshotProviderNative.php`, `SnapshotProviderUpdraft.php`, `SnapshotProviderWpReset.php`
- `Snapshot/SnapshotScheduler.php`, `DependencyAnalyzer.php`
- `constants.php`

---

## Phase 1: Split `riseup-asia-uploader.php` (5,604 → ~28 files × ≤200)

The main plugin file is the largest offender. It contains REST route registration, request handlers, upload logic, plugin management, OpenAPI spec, and fatal error handling — all in one monolith.

### Proposed extraction targets:

| New File | Domain | Methods to Extract |
|----------|--------|--------------------|
| `includes/Routes/UtilityRoutes.php` | Route registration | `register_utility_routes()` |
| `includes/Routes/PluginRoutes.php` | Route registration | `register_plugin_routes()` |
| `includes/Routes/PostRoutes.php` | Route registration | `register_post_routes()` |
| `includes/Routes/LogRoutes.php` | Route registration | `register_log_routes()` |
| `includes/Routes/AgentRoutes.php` | Route registration | `register_agent_routes()` |
| `includes/Routes/SnapshotRoutes.php` | Route registration | `register_snapshot_routes()` |
| `includes/Routes/CatchAllRoute.php` | Route registration | `register_catch_all_route()` |
| `includes/Handlers/StatusHandler.php` | Request handlers | `handle_status()`, status helpers |
| `includes/Handlers/UploadHandler.php` | Request handlers | `handle_upload()`, upload pipeline helpers |
| `includes/Handlers/PluginHandler.php` | Request handlers | `handle_list_plugins()`, `handle_plugin_files()`, `handle_single_plugin_file()` |
| `includes/Handlers/ExportHandler.php` | Request handlers | `handle_export_self()`, zip helpers |
| `includes/Handlers/OpenApiHandler.php` | Request handlers | `handle_openapi()` |
| `includes/Handlers/OpcacheHandler.php` | Request handlers | `handle_opcache_reset()` |
| `includes/Handlers/SnapshotDownloadHandler.php` | Request handlers | `handle_snapshot_download()` |
| `includes/Handlers/ErrorLogHandler.php` | Request handlers | error log endpoint handlers |
| `includes/Handlers/LogHandler.php` | Request handlers | log retrieval handlers |
| `includes/Plugin/PluginDiscovery.php` | Plugin utilities | `find_plugin_file()`, `get_plugin_base_dir()`, extraction helpers |
| `includes/Plugin/PluginExtractor.php` | Plugin utilities | `extract_to_plugins_dir()`, extraction helpers |
| `includes/Auth/AuthMiddleware.php` | Authentication | `authenticate_request()`, auth helpers |
| `includes/ErrorHandling/FatalErrorHandler.php` | Fatal errors | `riseup_fatal_error_handler()`, related helpers |
| `includes/ErrorHandling/ShutdownHandler.php` | Shutdown | shutdown registration, error capture |
| `includes/Init/PluginBootstrap.php` | Initialization | constructor logic, dependency loading, init sequence |

After extraction, `riseup-asia-uploader.php` should contain ONLY:
- Plugin header comment
- Constants definition
- `require_once` calls to load extracted files
- Plugin activation/deactivation hooks
- The `RiseupAsiaUploader` class shell (delegating to extracted classes)

### Status: ⬜ NOT STARTED

---

## Phase 2: Split `Admin/Admin.php` (1,017 → ~5 files × ≤200)

| New File | Methods to Extract |
|----------|--------------------|
| `Admin/AdminMenu.php` | `add_admin_menu()`, `registerMainMenu()`, `registerSubmenus()`, `registerErrorSubmenu()` |
| `Admin/AdminSettings.php` | `register_settings()`, `get_settings()`, defaults, AJAX settings handlers |
| `Admin/AdminAssets.php` | `enqueue_admin_assets()` |
| `Admin/AdminAjax.php` | All `ajax_*` methods (snapshot settings, cleanup, storage stats, error flash, log file ops) |
| `Admin/AdminRenderers.php` | `render_logs_page()`, `render_errors_page()`, `render_global_error_notice()`, query helpers |

### Status: ⬜ NOT STARTED

---

## Phase 3: Split `Agent/AgentManager.php` (807 → ~4 files × ≤200)

| New File | Methods to Extract |
|----------|--------------------|
| `Agent/AgentEncryption.php` | `encrypt()`, `decrypt()` |
| `Agent/AgentCrud.php` | `addAgent()`, `updateAgent()`, `deleteAgent()`, `getAgent()`, `listAgents()` |
| `Agent/AgentApi.php` | `apiRequest()`, `resolveAgentBaseUrl()`, `parseAgentResponse()`, HTTP helpers |
| `Agent/AgentPluginOps.php` | `getRemotePlugins()`, `uploadToAgent()`, remote plugin operations |

### Status: ⬜ NOT STARTED

---

## Phase 4: Split `Database/Database.php` (~1,200 → ~6 files × ≤200)

| New File | Methods to Extract |
|----------|--------------------|
| `Database/DatabaseConnection.php` | `get_pdo()`, `is_ready()`, connection management |
| `Database/DatabaseMigrations.php` | All `migrate_v*` methods, schema versioning |
| `Database/DatabaseTransactions.php` | `log_transaction()`, `query_transactions()`, `apply_filters()` |
| `Database/DatabaseErrors.php` | Error session queries, error log methods |
| `Database/DatabaseSnapshots.php` | Snapshot-related table operations |
| `Database/DatabaseCleanup.php` | Cleanup, pruning, maintenance methods |

### Status: ⬜ NOT STARTED

---

## Phase 5: Split remaining oversized files

### 5a: `Update/UpdateResolver.php` (599 → ~3 files)
| New File | Methods |
|----------|---------|
| `Update/UpdateSettings.php` | `get_settings()`, `save_settings()`, defaults |
| `Update/UpdateUrlResolver.php` | `resolve_url()`, redirect-following logic |
| `Update/UpdateChecker.php` | `check_for_plugin_update()`, `plugin_info()`, `fetch_update_info()` |

### 5b: `Post/PostManager.php` (487 → ~3 files)
| New File | Methods |
|----------|---------|
| `Post/PostCreator.php` | `createPost()`, validation, category assignment |
| `Post/PostUpdater.php` | `updatePost()`, field mapping |
| `Post/CategoryManager.php` | `createCategory()`, `listCategories()` |

### 5c: `Logging/Logger.php` (~550 → ~3 files)
| New File | Methods |
|----------|---------|
| `Logging/TransactionLogger.php` | `log_transaction()`, `log_upload()`, `log_post_action()` |
| `Logging/LogContext.php` | `get_client_ip()`, `get_source_machine()`, `get_user_info()` |

### 5d: `Logging/FileLogger.php` (~580 → ~3 files)
| New File | Methods |
|----------|---------|
| `Logging/FileLogWriter.php` | File writing, rotation, formatting |
| `Logging/FileLogReader.php` | File reading, tail, search |

### Status: ⬜ NOT STARTED

---

## Phase 6: Split oversized Snapshot files

Target files (each → 2-3 smaller files):
- `SnapshotOrchestrator.php` → `SnapshotOrchestrator.php` + `SnapshotOrchestratorHelpers.php`
- `IncrementalBackup.php` → `IncrementalBackup.php` + `IncrementalExporter.php`
- `SnapshotWorker.php` → `SnapshotWorker.php` + `SnapshotTableProcessor.php`
- `RestoreEngine.php` → `RestoreEngine.php` + `RestoreTableHandler.php`
- `SnapshotManager.php` → `SnapshotManager.php` + `SnapshotQueries.php`
- `SnapshotExporter.php` → `SnapshotExporter.php` + `SnapshotZipBuilder.php`
- `SnapshotImport.php` → `SnapshotImport.php` + `SnapshotImportParser.php`

### Status: ⬜ NOT STARTED

---

## Phase 7: Split oversized template files

Templates are HTML-heavy with embedded PHP. Split by tab/section:
- `admin-errors.php` → `admin-errors-sessions.php`, `admin-errors-log.php`, `admin-errors-styles.php`, `admin-errors-scripts.php`
- `admin-settings.php` → `admin-settings-endpoints.php`, `admin-settings-update.php`, `admin-settings-scripts.php`
- `admin-snapshots.php` → `admin-snapshots-list.php`, `admin-snapshots-actions.php`, `admin-snapshots-scripts.php`
- `admin-logs.php` → `admin-logs-table.php`, `admin-logs-filters.php`, `admin-logs-scripts.php`
- `admin-agents.php` → `admin-agents-list.php`, `admin-agents-form.php`, `admin-agents-scripts.php`

### Status: ⬜ NOT STARTED

---

## Phase 8: Split remaining Helpers and Database files

- `Helpers/PathUtils.php` → may need splitting if >200
- `Database/Orm.php` → `Orm.php` + `OrmQueryBuilder.php`
- `Database/FileCache.php` → likely close to 200, evaluate
- `Database/RootDb.php` → likely close to 200, evaluate

### Status: ⬜ NOT STARTED

---

## Phase 9: Final 15-line method body scan

After all file splits are complete:
1. Scan every PHP file for functions exceeding 15 lines
2. Extract helpers to bring all methods into compliance
3. Verify zero violations remain

### Status: ⬜ NOT STARTED

---

## Execution Order

1. **Phase 1** (Critical) — Main plugin file split
2. **Phase 2** (Critical) — Admin split
3. **Phase 4** (Critical) — Database split
4. **Phase 3** (High) — AgentManager split
5. **Phase 5** (High) — UpdateResolver, PostManager, Logging splits
6. **Phase 6** (High) — Snapshot splits
7. **Phase 7** (Medium) — Template splits
8. **Phase 8** (Medium) — Remaining helpers/DB files
9. **Phase 9** (Final) — 15-line method scan and fix

## Notes

- Each phase must update `require_once` / `use` statements and the DependencyLoader manifest
- Singleton patterns must remain in the primary class file; extracted files contain trait-like helper methods or standalone classes
- Templates use `include` / `require` for partials
- After each phase, verify no broken references
