# Long Function (>15 lines) — Phased Fix Plan
Updated: 2026-02-13

## Rule Reference
> Functions should not exceed 15 lines of logic. Extract named helpers to keep each function focused on a single responsibility.

## Severity Legend
- 🔴 CRITICAL: >100 lines — must be split into multiple focused methods
- 🟠 HIGH: 40–100 lines — extract 2–4 helpers
- 🟡 MEDIUM: 20–39 lines — extract 1–2 helpers
- 🟢 LOW: 16–19 lines — borderline, refactor when touched

---

## Phase 1 — `riseup-asia-uploader.php`: `register_routes()` (🔴 ~385 lines)
**Lines:** 655–1041
**Current:** Monolithic method registering 30+ REST routes with inline try-catch wrappers.
**Fix:** Extract route groups into named methods:
- `registerPluginRoutes()` — upload, list, enable, disable, delete, export, exists, files, file-content
- `registerPostRoutes()` — posts, categories
- `registerLogRoutes()` — logs, logs-stats, error-logs, error-sessions
- `registerSnapshotRoutes()` — list, create, delete, restore, export, download, import, settings, providers, tables
- `registerAgentRoutes()` — list, add, remove, test, sync, plugins, action, history
- `registerAdminRoutes()` — admin settings, nonce
- `registerCatchAllRoute()` — invalid path handler

Each sub-method receives `$safe_register` closure as a parameter. Result: `register_routes()` becomes ~20 lines orchestrating 7 sub-calls.

**Est. impact:** 1 file, eliminates 1 × 385-line function → 7 × ~40-line methods + 1 × ~20-line orchestrator.

---

## Phase 2 — `riseup-asia-uploader.php`: `handle_upload()` (🔴 ~460 lines)
**Lines:** 1695–2154
**Current:** Single method handling dual-format upload (multipart + base64), ZIP validation, duplicate scanning, extraction, activation, version detection, and logging.
**Fix:** Extract into focused stages:
- `parseUploadInput(WP_REST_Request): UploadInput` — multipart vs base64 parsing
- `validateAndExtractZip(string $zip_content, string $slug): ExtractResult` — ZIP validation, slug detection, temp file handling
- `removeDuplicatePlugins(string $slug): int` — duplicate plugin scanner
- `extractToPluginsDir(string $temp_file, string $slug): ExtractResult` — unzip + opcache reset
- `detectInstalledVersion(string $plugin_file, string $slug, bool $is_self_update, string $client_version): string` — version detection logic
- `activateIfNeeded(string $plugin_file, bool $activate, bool $was_active): ActivateResult`

**Est. impact:** 1 file, eliminates 1 × 460-line function → 6 × ~40-line methods + 1 × ~30-line orchestrator.

---

## Phase 3 — `riseup-asia-uploader.php`: Auth duplication (🔴 ~200 lines combined)
**Functions:** `check_authenticated_only()` (~97 lines, L1251–1348) + `check_authenticated_capability()` (~100 lines, L1358–1470)
**Current:** Two near-identical methods — the second adds a capability check at the end.
**Fix:** Already planned in nested-if-fix-plan Phase 1. Extract:
- `resolveAuthHeader(WP_REST_Request): ?string` — shared auth header fallback chain (~15 lines)
- `authenticateUser(string $auth_header): WP_User|WP_Error` — parse Basic auth, validate credentials (~15 lines)
- Both methods become ~10 lines each calling the shared helpers.

**Est. impact:** 1 file, eliminates ~200 lines of duplication.

---

## Phase 4 — `riseup-asia-uploader.php`: Plugin lifecycle handlers (🟠 ~350 lines combined)
**Functions:**
- `handle_enable_plugin()` — ~114 lines (L2920–3034)
- `handle_disable_plugin()` — ~118 lines (L3044–3162)
- `handle_delete_plugin()` — ~122 lines (L3172–3295)
**Current:** Each has 5–6 granular try-catch steps that are structurally identical.
**Fix:** Extract shared step pattern:
- `loadPluginFunctions(): void` — shared step 1
- `resolvePluginFile(string $slug): string` — shared step 2 (find + validate)
- Each handler becomes ~20 lines: load → resolve → action-specific logic → log → response.

**Est. impact:** 1 file, eliminates 3 × ~115-line functions → 3 × ~20-line handlers + 2 shared helpers.

---

## Phase 5 — `Database.php`: `create_tables()` (🔴 ~300+ lines)
**Lines:** 201–500+
**Current:** All 11 migrations (v1–v11) in a single method.
**Fix:** Extract each migration into a named method:
- `migrateV1_TransactionsTable(int $current): void`
- `migrateV2_AgentTables(int $current): void`
- …through `migrateV11_SnapshotExports(int $current): void`
- `create_tables()` becomes a ~20-line loop calling each migration sequentially.

**Est. impact:** 1 file, eliminates 1 × 300-line function → 11 × ~25-line migration methods + 1 × ~20-line orchestrator.

---

## Phase 6 — `riseup-asia-uploader.php`: Fatal error handler (🔴 ~145 lines)
**Function:** `riseup_fatal_error_handler()` (L95–241)
**Current:** Global function handling error detection, logging, output cleanup, trace generation, frame building, response assembly, and JSON encoding.
**Fix:** Extract into focused helpers (all standalone functions since this runs pre-class):
- `riseup_is_fatal_rest_error(array $error): bool` — type + URL check
- `riseup_build_fatal_frames(array $error): array` — trace + frames generation
- `riseup_build_fatal_response(array $error, array $frames): array` — response assembly
- Main function becomes ~20 lines: detect → log → clean output → build → encode → exit.

**Est. impact:** 1 file, eliminates 1 × 145-line function → 3 × ~30-line helpers + 1 × ~20-line main.

---

## Phase 7 — `SnapshotOrchestrator.php`: `executeFullBackup()` (🔴 ~163 lines)
**Lines:** 95–258
**Current:** Full backup pipeline in one method covering settings, async routing, plugin snapshots, registration, ZIP export.
**Fix:** Already somewhat decomposed. Further extract:
- `executeAsyncBackup(array $options, array $settings, array $worker_result): array` — async early return path
- `executeSyncBackup(array $options, array $settings, array $worker_result): array` — sync completion path
- Main method becomes ~25 lines: settings → worker → async/sync branch → return.

**Est. impact:** 1 file, eliminates 1 × 163-line function → 2 × ~50-line methods + 1 × ~25-line orchestrator.

---

## Phase 8 — `SnapshotOrchestrator.php`: `snapshotPlugins()` (🔴 ~118 lines)
**Lines:** 267–386
**Current:** Plugin filtering, ZIP creation, root DB registration, all in one method.
**Fix:** Extract:
- `collectPluginsToSnapshot(string $selection): array` — filter active/all plugins
- `archiveSinglePlugin(array $info, string $plugins_dir, ?PDO $rootPdo): ?array` — ZIP + register one plugin
- Main method becomes ~20 lines: collect → loop archive → return stats.

**Est. impact:** 1 file, eliminates 1 × 118-line function → 2 × ~30-line helpers + 1 × ~20-line main.

---

## Phase 9 — `SnapshotManager.php`: `restoreSnapshot()` + `executeRestore()` + `restoreTable()` (🟠 ~260 lines combined)
**Functions:**
- `restoreSnapshot()` — ~100 lines (L135–235)
- `executeRestore()` — ~84 lines (L244–329)
- `restoreTable()` — ~77 lines (L338–416)
**Fix:**
- `restoreSnapshot()`: Extract `validateIncrementalParent()` guard (~15 lines) and `createPreRestoreBackup()` already exists. Main drops to ~20 lines.
- `executeRestore()`: Already well-structured, extract `getRestoreTables()` filter logic. Main drops to ~20 lines.
- `restoreTable()`: Extract `insertBatchToMySQL(array $rows, array $columns, string $table): int`. Main drops to ~15 lines.

**Est. impact:** 1 file, 3 functions flattened.

---

## Phase 10 — `SnapshotScheduler.php`: Cron executors (🟠 ~260 lines combined) ✅ DONE
**Functions:**
- `executeScheduledSnapshot()` — ~49 lines (L340–390)
- `executeImmediateSnapshot()` — ~59 lines (L400–460)
- `executeCronRestore()` — ~56 lines (L467–523)
- `executeCronIncremental()` — ~52 lines (L526–578)
- `executeCleanup()` — ~44 lines (L581–625)
**Result:** Extracted shared infrastructure (`executeCronJob`, `logCronResult`, `buildCronResult`, `createOrchestrator`, `invokeBackup`). Each public executor is now a 3-line delegate. Five private `run*()` work methods each ≤15 lines.


---

## Phase 11 — `riseup-asia-uploader.php`: Remaining handlers (🟡 20–40 lines each) ✅ DONE
**Refactored 8 of 12 handlers** (the 8 largest, accounting for ~90% of line savings):
- `handle_status()` → extracted `detectLiveVersion()`, `collectRegisteredRoutes()`, `loadEndpointsReference()`, `buildStatusPayload()`
- `handle_invalid_route()` → extracted `buildInvalidRouteTrace()`, `formatBacktraceLines()`, `formatFramesSummary()`
- `handle_error_logs()` → extracted `resolveLogSettings()`
- `handle_error_sessions()` → extracted `isTableExists()`, `buildErrorSessionQuery()`, `countErrorSessions()`, `fetchErrorSessions()`, `enrichErrorEntries()`, `parseContextJson()`
- `handle_sync_push()` → extracted `executeSyncPush()`, `processSyncFile()`, `isSyncPathTraversal()`, `syncReplaceFile()`, `syncDeleteFile()`, `cleanEmptyParentDirs()`, `updateSyncCounters()`, `logSyncCompletion()`
- `handle_create_snapshot()` → extracted `executePerTableSnapshot()`, `executeLegacySnapshot()`, `logSnapshotResult()`
- `handle_restore_snapshot()` → extracted `parseRestoreOptions()`, `routeRestoreToEngine()`
- `handle_snapshot_download_file()` → extracted `streamZipFile()`

**Remaining (borderline, deferred to Phase 12):** `enrich_error_response()`, `handle_snapshot_download()`, `handle_export_snapshot()`, `handle_full_backup()`

---

## Phase 12 — Remaining subsystem files (🟡 scattered) ✅ DONE
**Refactored 8 functions across 6 files:**
- `Database.php`: `log_transaction()` → extracted `applyEnhancedFields()`; `get_stats()` → extracted `countByColumn()`
- `SnapshotScheduler.php`: `calculateNextRunTime()` → extracted `nextDailyRun()`, `nextWeeklyRun()`, `nextMonthlyRun()`
- `SnapshotManager.php`: `exportSnapshot()` → extracted `createSnapshotZip()`
- `SnapshotOrchestrator.php`: `createZipExport()` → extracted `addDirectoryToZip()`, `validateZipExport()`; `executeIncrementalBackup()` → extracted `resolveMasterDir()`
- `FileCache.php`: `getManifest()` → extracted `reconcileManifest()`, `resolveFileEntry()`, `pruneStaleEntries()`
- `UpdateResolver.php`: `resolve_url()` → extracted `followSingleRedirect()`, `logResolvedUrl()`

**Remaining (borderline/data-only, skipped):** `apply_filters()` (flat filter chain), `render_settings_page()` (data definition), `getSettings()` (defaults array), `getStatus()` (17 lines)

---

## Priority Order

| Phase | Severity | File(s) | Functions | Est. Lines Saved |
|-------|----------|---------|-----------|-----------------|
| 1     | 🔴 CRITICAL | main | `register_routes` | ~350 |
| 2     | 🔴 CRITICAL | main | `handle_upload` | ~400 |
| 3     | 🔴 CRITICAL | main | auth pair | ~170 |
| 4     | 🟠 HIGH | main | lifecycle handlers | ~280 |
| 5     | 🔴 CRITICAL | Database | `create_tables` | ~250 |
| 6     | 🔴 CRITICAL | main | fatal handler | ~110 |
| 7     | 🟠 HIGH | Orchestrator | `executeFullBackup` | ~100 |
| 8     | 🟠 HIGH | Orchestrator | `snapshotPlugins` | ~80 |
| 9     | 🟠 HIGH | Manager | restore trio | ~180 |
| 10    | 🟠 HIGH | Scheduler | cron executors | ~180 |
| 11    | 🟡 MEDIUM | main | remaining handlers | ~400 |
| 12    | 🟡 MEDIUM | various | scattered | ~300 |

**Total functions exceeding 15 lines: ~60+**
**Total excess lines: ~2,800+**
