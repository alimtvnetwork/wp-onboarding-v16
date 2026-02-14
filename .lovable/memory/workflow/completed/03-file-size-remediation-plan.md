# File Size Remediation Plan — Phase 1 Detail: Main Plugin Split

Updated: 2026-02-13

## Strategy: PHP Traits

Class methods stay callable via `array($this, 'method')` route callbacks. Standalone pre-class functions move to separate files directly.

## Extraction Map

### Standalone Function Files

| File | Source Lines | Functions | ~Lines |
|------|-------------|-----------|--------|
| `ErrorHandling/FrameBuilder.php` | 25-191 | `riseup_build_single_frame`, `riseup_exception_to_frames`, `riseup_backtrace_to_frames`, `riseup_build_trace_lines`, `riseup_build_structured_frames`, `riseup_build_fatal_frames`, `riseup_build_fatal_details` | ~175 |
| `ErrorHandling/FatalErrorHandler.php` | 192-359 | `riseup_build_fatal_response`, `riseup_log_fatal_to_file`, `riseup_clean_output_buffers`, `riseup_emit_fatal_json_response`, `riseup_build_fatal_fallback`, `riseup_fatal_error_handler`, `riseup_error_type_to_string` + `register_shutdown_function` | ~180 |

### Trait Files (class methods)

| File | Source Lines | Methods | ~Lines |
|------|-------------|---------|--------|
| `Traits/LifecycleHooksTrait.php` | 550-739 | `on_plugin_activated`, `on_plugin_deactivated`, `on_plugin_deleted`, `detect_trigger_source`, `is_rest_request`, `extract_plugin_slug` | ~195 |
| `Traits/RouteRegistrationTrait.php` | 747-955 | `register_routes`, `register_utility_routes`, `register_post_routes`, `register_log_routes`, `register_catch_all_route` | ~195 |
| `Traits/PluginRoutesTrait.php` | 806-1090 | `register_plugin_routes`, `register_agent_routes`, `register_snapshot_routes` | ~200 |
| `Traits/InvalidRouteTrait.php` | 1092-1231 | `handle_invalid_route`, `buildInvalidRouteTrace`, `formatBacktraceLines`, `formatFramesSummary`, `enrich_error_response`, `injectErrorMetadata`, `logRestApiError` | ~145 |
| `Traits/AuthTrait.php` | 1233-1550 | `is_endpoint_enabled`, `is_auth_required`, `build_permission_callback`, `check_plugin_permission`, `check_post_permission`, `check_logs_permission`, `check_status_permission`, `resolve_auth_header`, `authenticate_user`, `build_missing_auth_error`, `check_authenticated_only`, `check_authenticated_capability` | ~200 |
| `Traits/StatusHandlerTrait.php` | 1552-1820 | `handle_status`, `detectLiveVersion`, `collectRegisteredRoutes`, `loadEndpointsReference`, `buildStatusPayload`, `buildFeatureFlags`, `handle_openapi`, `loadOpenApiSpec`, `handle_opcache_reset`, `invalidatePluginFiles` | ~200 |
| `Traits/UploadPipelineTrait.php` | 1822-2091 | `handle_upload`, `executeUploadPipeline`, `logUploadInitiated`, `processUploadExtraction`, `buildUploadResponse`, `parse_upload_input`, `parse_multipart_input`, `parse_base64_input`, `build_upload_params` | ~200 |
| `Traits/UploadExtractionTrait.php` | 2093-2476 | `validate_and_write_zip`, `remove_duplicate_plugins`, `pre_log_self_update`, `deactivate_if_updating`, `extract_to_plugins_dir`, `reset_opcache_and_find_plugin`, `activate_if_needed`, `detect_installed_version` | ~200 |
| `Traits/PluginListTrait.php` | 2478-2593 + 2940-3003 | `handle_list_plugins`, `collectPluginList`, `handle_plugin_files`, `scanPluginFilesWithCache`, `handle_plugin_file_content` | ~195 |
| `Traits/PluginExportTrait.php` | 3005-3131 | `handle_export_self`, `handle_export_plugin` | ~135 |
| `Traits/PostHandlerTrait.php` | 3133-3285 | `handle_list_posts`, `handle_create_post`, `handle_list_categories`, `handle_create_category`, `handle_query_logs`, `buildLogQueryFilters`, `handle_logs_stats` | ~160 |
| `Traits/PluginLifecycleTrait.php` | 3287-3601 | `handle_plugin_exists`, `load_plugin_functions`, `resolve_plugin_from_request`, `handle_enable_plugin`, `handle_disable_plugin`, `handle_delete_plugin`, `log_plugin_lifecycle` | ~200 |
| `Traits/SyncHandlerTrait.php` | 2594-2930 | `handle_sync_manifest`, `generateSyncManifest`, `handle_sync_push`, `executeSyncPush`, `processSyncFile`, `isSyncPathTraversal`, `syncReplaceFile`, `syncDeleteFile`, `cleanEmptyParentDirs`, `updateSyncCounters`, `logSyncCompletion`, `scan_directory_for_files` | ~200 |
| `Traits/ResponseTrait.php` | 3607-4046 | `safe_execute`, `error_response`, `logErrorWithBacktrace`, `get_exception_code` | ~115 |
| `Traits/ErrorLogTrait.php` | 3641-3976 | `handle_error_logs`, `resolveLogSettings`, `handle_error_sessions`, `isTableExists`, `buildErrorSessionQuery`, `countErrorSessions`, `fetchErrorSessions`, `enrichErrorEntries`, `parseContextJson`, `count_unseen_errors`, `parse_stack_trace_string`, `parseTraceFrame`, `read_log_tail` | ~200 |
| `Traits/AgentHandlerTrait.php` | 4306-4530 | `handle_list_agents`, `handle_add_agent`, `handle_get_agent`, `handle_remove_agent`, `handle_test_agent`, `handle_sync_agent`, `handle_agent_action`, `handle_agent_history` | ~200 |
| `Traits/SnapshotCrudTrait.php` | 4532-4827 | `handle_list_snapshots`, `handle_schedule_snapshot`, `handle_create_snapshot`, `executePerTableSnapshot`, `executeLegacySnapshot`, `logSnapshotResult`, `handle_get_snapshot`, `handle_snapshot_info` (alias), `handle_delete_snapshot`, `isPerTableSnapshot`, `resolveSnapshotDir` | ~200 |
| `Traits/SnapshotExportTrait.php` | 4828-5107 | `handle_export_snapshot`, `handle_snapshot_download`, `buildDownloadResponse`, `handle_snapshot_download_file`, `validateAndResolveExport`, `sendZipHeaders`, `streamZipFile`, `handle_import_snapshot` | ~200 |
| `Traits/SnapshotBackupTrait.php` | 5108-5499 | `handle_restore_snapshot`, `parseRestoreOptions`, `routeRestoreToEngine`, `handle_get/update_snapshot_settings`, `handle_list_snapshot_providers`, `handle_list_snapshot_tables`, `handle_analyze_dependencies`, `handle_export_pertable`, `handle_full_backup`, `handle_incremental_backup`, `handle_snapshot_cleanup`, `handle_snapshot_progress` | ~200 |
| `Traits/FileSystemTrait.php` | 4047-4304 | `get_temp_dir`, `find_plugin_file`, `find_plugin_file_from_filesystem`, `detect_plugin_slug_from_zip`, `delete_directory`, `copy_directory`, `add_dir_to_zip` | ~200 |

### Main File After Extraction (~200 lines)

Contents:
- Plugin header (20 lines)
- `use` imports (5 lines)
- `require_once` for ErrorHandling files (5 lines)
- Dependency loading section with DependencyLoader manifest (55 lines)
- `require_once` for all Trait files (25 lines)
- Class `RiseupAsia` with `use` trait statements (20 lines)
- Properties + constructor (70 lines)
- `riseup_asia_activate()` + `riseup_asia_init()` (extracted to Init/PluginActivation.php if needed)

## Execution Waves

### Wave 1 (8 files)
ErrorHandling/FrameBuilder.php, ErrorHandling/FatalErrorHandler.php, Traits/LifecycleHooksTrait.php, Traits/RouteRegistrationTrait.php, Traits/PluginRoutesTrait.php, Traits/InvalidRouteTrait.php, Traits/AuthTrait.php, Traits/StatusHandlerTrait.php

### Wave 2 (7 files) ✅ COMPLETE
Traits/UploadPipelineTrait.php, Traits/UploadExtractionTrait.php, Traits/PluginListTrait.php, Traits/PluginExportTrait.php, Traits/PostHandlerTrait.php, Traits/PluginLifecycleTrait.php, Traits/SyncHandlerTrait.php

Next: Wave 3 — ResponseTrait, ErrorLogTrait, AgentHandlerTrait, SnapshotCrudTrait, SnapshotExportTrait, SnapshotBackupTrait, FileSystemTrait

### Wave 3 (7 files) ✅ COMPLETE
Traits/ResponseTrait.php, Traits/ErrorLogTrait.php, Traits/AgentHandlerTrait.php, Traits/SnapshotCrudTrait.php, Traits/SnapshotExportTrait.php, Traits/SnapshotBackupTrait.php, Traits/FileSystemTrait.php

### Wave 4 (1 file) ✅ COMPLETE
Rewrite riseup-asia-uploader.php as shell (~270 lines)

## Status: ✅ ALL WAVES COMPLETE — Phase 1 finished

Main plugin file reduced from 5,604 lines to ~270 lines. All logic extracted into 20 trait files + 2 standalone error handling files.
