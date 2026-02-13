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

## Phases 1–12: ✅ ALL DONE (see git history for details)

**Summary:** ~60 functions refactored, ~100 focused helpers extracted across all subsystem files.

---

## Phase 13 — `riseup-asia-uploader.php`: Remaining long handlers (🟠🟡 ~15 functions)

### 13A — `handle_upload()` (~105 lines, L1719–1825)
**Current:** Orchestrator calling ~8 extracted helpers, but the try block itself is still ~100 lines of sequential calls + response building.
**Fix:** Extract:
- `executeUploadPipeline($request): array` — Steps 1–8 (parse, validate, extract, activate, detect version) returning a result array
- `buildUploadResponse(array $result): WP_REST_Response` — final envelope construction
- Main handler becomes ~12 lines: try → pipeline → response, catch → error

### 13B — `handle_export_self()` (~50 lines, L2833–2884)
**Fix:** Extract:
- `createExportZip(string $plugin_dir): string` — ZIP creation + encoding
- Main drops to ~12 lines

### 13C — `handle_export_plugin()` (~60 lines, L2890–2952)
**Fix:** Extract:
- `buildPluginExportZip(string $slug): array` — validate + ZIP + encode
- Main drops to ~10 lines

### 13D — `handle_openapi()` (~37 lines, L1613–1649)
**Fix:** Extract:
- `loadOpenApiSpec(): array|WP_REST_Response` — file read + parse + validate
- Main drops to ~10 lines

### 13E — `handle_opcache_reset()` (~43 lines, L1664–1706)
**Fix:** Extract:
- `invalidatePluginFiles(): int` — per-file opcache invalidation loop
- Main drops to ~15 lines

### 13F — `handle_snapshot_download()` (~63 lines, L4693–4756)
**Fix:** Extract:
- `buildDownloadResponse(int $snapshotId): array` — exporter call + audit logging
- Main drops to ~10 lines

### 13G — `handle_snapshot_download_file()` (~20 lines, L4766–4786) + `streamZipFile()` (~30 lines, L4794–4824)
**Fix:** Combine token validation and streaming into:
- `validateAndResolveExport(int $id, string $token): array|WP_REST_Response`
- `streamZipFile` is already extracted; refactor to ≤15 lines by extracting `sendZipHeaders()`

### 13H — `handle_import_snapshot()` (~40 lines, L4832–4872)
**Fix:** Extract:
- `executeSnapshotImport(array $files): array` — validation + import + audit log
- Main drops to ~10 lines

### 13I — `enrich_error_response()` (~38 lines, L1114–1152)
**Fix:** Extract:
- `injectErrorMetadata(array $data): array` — metadata injection
- `logRestApiError(string $route, int $status, array $data)` — audit logging
- Main drops to ~15 lines

### 13J — `error_response()` (~27 lines, L3788–3814)
**Fix:** Extract:
- `logErrorWithBacktrace(string $message, int $status, ?Throwable $e)` — logging logic
- Main drops to ~8 lines

### 13K — `riseup_build_fatal_frames()` (~53 lines, L115–168)
**Fix:** Extract:
- `buildTraceLines(array $error, ?array $backtrace): array` — trace line formatting
- `buildStructuredFrames(array $error, ?array $backtrace): array` — frame objects
- Main drops to ~10 lines

### 13L — `riseup_build_fatal_response()` (~27 lines, L178–205)
**Fix:** Extract `buildFatalDetailsArray()` — the big details array, main drops to ~10 lines

### 13M — `riseup_fatal_error_handler()` (~55 lines, L215–270)
**Fix:** Extract:
- `logFatalToFile(array $error)` — file logging
- `cleanOutputBuffers()` — output cleanup
- `emitFatalJsonResponse(array $error)` — response building + encoding + fallback
- Main drops to ~12 lines

### 13N — `riseup_exception_to_frames()` (~24 lines, L35–59) + `riseup_backtrace_to_frames()` (~12 lines, L67–79)
**Fix:** Extract shared `buildSingleFrame(array $frame): array` used by both, each drops to ≤12 lines

### 13O — `parse_stack_trace_string()` (~32 lines, L3696–3728)
**Fix:** Extract `parseTraceFrame(string $line): ?array` — regex match + class/function parsing, main drops to ~10 lines loop

**Est. impact:** 1 file, ~15 functions → ~15 helpers extracted, ~400 excess lines removed.

---

## Phase 14 — `riseup-asia-uploader.php`: Data-heavy handlers & utilities (🟡 ~12 functions)

### 14A — `render_logs_page()` in Admin.php (~45 lines, L307–352)
**Fix:** Extract:
- `buildLogFilters(array $get): array` — filter parsing from $_GET
- `getActionLabels(): array` — labels map
- Main drops to ~12 lines

### 14B — `render_settings_page()` in Admin.php (~66 lines, L357–423)
**Fix:** Extract:
- `buildEndpointGroups(): array` — the large endpoint metadata definition
- `flattenEndpointGroups(array $groups): array` — backward compat flattener
- Main drops to ~12 lines

### 14C — `render_errors_page()` in Admin.php (~78 lines, L643–722)
**Fix:** Extract:
- `fetchErrorsForPage(): array` — DB query + flash state + pagination
- Main drops to ~10 lines (safe defaults → try fetch → include template)

### 14D — `ajax_save_snapshot_settings()` in Admin.php (~94 lines, L487–581)
**Fix:** Extract:
- `parseSnapshotSettingsFromPost(): array` — all the isset/sanitize blocks
- Main drops to ~15 lines

### 14E — `ajax_read_log_file()` in Admin.php (~38 lines, L867–906)
**Fix:** Extract:
- `readLogFileContent(string $path): array` — file read + truncation logic
- Main drops to ~12 lines

### 14F — `sanitize_update_settings()` in Admin.php (~24 lines, L243–267)
**Fix:** Extract:
- `buildSanitizedUpdateFields(array $input, array $current): array`
- Main drops to ~10 lines

### 14G — `add_admin_menu()` in Admin.php (~67 lines, L100–167)
**Fix:** Extract:
- `registerSubmenus()` — the 5 submenu registrations
- `buildErrorBubble(): string` — error count badge
- Main drops to ~10 lines

### 14H — `buildStatusPayload()` (~28 lines, L1576–1604) — main plugin file
**Fix:** Already borderline; extract `buildHostInfo(): array` and `buildCapabilities(): array` sub-sections

### 14I — `handle_list_plugins()` (~39 lines, L2330–2369) — main plugin file
**Fix:** Extract `collectPluginList(): array` — the foreach loop building plugin array

### 14J — `handle_plugin_files()` (~44 lines, L2379–2423) — main plugin file
**Fix:** Extract `scanPluginFilesWithCache(string $slug): array` — dir check + cache + manifest

### 14K — `handle_sync_manifest()` (~43 lines, L2432–2476) — main plugin file
**Fix:** Extract `generateSyncManifest(string $slug): array` — reuses same pattern as plugin_files

### 14L — `handle_query_logs()` (~34 lines, L3040–3073) — main plugin file
**Fix:** Extract `buildLogQueryFilters(WP_REST_Request $request): array`

**Est. impact:** 2 files, ~12 functions → ~15 helpers, ~350 excess lines removed.

---

## Phase 15 — Snapshot subsystem files (🟠🟡 ~12 functions)

### 15A — `executeSyncBackup()` in SnapshotOrchestrator.php (~100 lines, L206–305)
**Fix:** Extract:
- `executePluginSnapshots(string $dir, array $resolved): array` — plugin snapshot phase
- `executeZipExportPhase(string $dir, array $resolved): array` — ZIP creation phase  
- `buildSyncResult(array $workerResult, array $pluginStats, ?string $zipPath, int $snapshotId, float $duration): array`
- Main drops to ~15 lines

### 15B — `registerSnapshot()` in SnapshotOrchestrator.php (~53 lines, L614–667)
**Fix:** Extract:
- `buildSnapshotTablesJson(array $workerResult, array $pluginStats): string`
- Main drops to ~15 lines

### 15C — `execute()` in IncrementalBackup.php (~167 lines, L84–251)
**Fix:** Extract:
- `prepareIncrementalDir(string $masterDir, PDO $rootPdo): array` — root open + inventory + dir creation
- `exportChangedTables(array $masterTables, string $incDir, PDO $rootPdo, int $sequence): array` — the per-table loop
- `finalizeIncremental(PDO $rootPdo, string $masterDir, ...): array` — register + invalidate + return
- Main drops to ~15 lines

### 15D — `exportDeltaRows()` in IncrementalBackup.php (~79 lines, L476–555)
**Fix:** Extract:
- `createIncrementalSqliteTable(string $filepath, string $table): PDO` — open + schema conversion
- `batchExportDelta(PDO $sqlite, string $table, string $pk, int $lastMaxId): int` — row export loop
- Main drops to ~15 lines

### 15E — `convertCreateStatement()` in IncrementalBackup.php + SnapshotWorker.php (~63 lines each, DUPLICATED)
**Fix:**
- Extract shared static utility `SqliteSchemaConverter::convert(string $mysql, string $table): string`
- Both classes call the shared method → eliminates ~63 lines of duplication

### 15F — `getLastMaxId()` in IncrementalBackup.php (~56 lines, L358–414)
**Fix:** Extract:
- `getMaxIdFromMasterSqlite(PDO $rootPdo, string $table, string $pk): ?int` — sequence=1 path
- `getMaxIdFromPreviousIncremental(PDO $rootPdo, string $table, string $pk, int $prevSeq): ?int`
- Main drops to ~12 lines

### 15G — `registerIncrementalSnapshot()` in IncrementalBackup.php (~62 lines, L642–704)
**Fix:** Extract:
- `calculateDirectorySize(string $dir): int` — the iterator loop
- `buildIncrementalMetaJson(...)` — JSON payload construction
- Main drops to ~15 lines

### 15H — `findLatestMasterSnapshot()` in IncrementalBackup.php (~46 lines, L711–756)
**Fix:** Extract:
- `findMasterFromDb(PDO $pdo): ?string` — DB lookup path
- `findMasterFromFilesystem(): ?string` — glob fallback
- Main drops to ~10 lines

### 15I — `execute()` in RestoreEngine.php (~208 lines, L87–295)
**Fix:** Extract:
- `validateRestorePrereqs(array $options): ?array` — confirm + root check
- `prepareRestoreOrder(PDO $rootPdo, array $options): array` — inventory + filter
- `createSafetyBackup(array $options): ?int` — pre-restore backup
- `restoreMasterTables(array $order, array $inventory, string $dir): array` — table restore loop
- `applyIncrementalsPhase(PDO $rootPdo, string $dir, array $order, string $mode): array`
- Main drops to ~15 lines

### 15J — `restoreTableFromFile()` in RestoreEngine.php (~82 lines, L305–387)
**Fix:** Extract:
- `openAndValidateSqliteTable(string $path, string $table): array` — open + verify + get columns
- `batchInsertToMysql(PDO $sqlite, string $table, array $columns, string $strategy, int $count): int`
- Main drops to ~12 lines

### 15K — `getRestoreOrder()` in RestoreEngine.php (~67 lines, L506–572)
**Fix:** Extract:
- `buildDependencyGraph(PDO $rootPdo, array $tables): array` — adjacency list construction
- `topologicalSort(array $graph, array $inDegree, array $allTables): array` — Kahn's algorithm
- Main drops to ~10 lines

### 15L — `execute()` + `executeSynchronous()` in SnapshotWorker.php (~92 + ~92 lines)
**Fix:** Extract shared:
- `prepareSnapshotDir(array $config): array` — dir creation + a-root.db + dependencies
- `buildSnapshotResult(...)` — result array construction
- Each drops to ~20 lines

### 15M — `processWorkerBatch()` in SnapshotWorker.php (~113 lines, L330–443)
**Fix:** Extract:
- `exportBatchTables(array $tables, string $dir, ?PDO $rootPdo): array` — the per-table export loop
- Main drops to ~15 lines

### 15N — `exportTableToFile()` in SnapshotWorker.php (~88 lines, L694–781)
**Fix:** Extract:
- `createSqliteAndSchema(string $filepath, string $table): PDO` — open + schema convert
- `batchExportRows(PDO $sqlite, string $table, int $count): int` — row export loop
- Main drops to ~15 lines

**Est. impact:** 4 files, ~12 functions → ~25 helpers, ~800 excess lines removed. Plus ~63 lines of deduplication.

---

## Phase 16 — Remaining scattered files (🟡🟢 ~10 functions)

### 16A — `updateAgent()` in AgentManager.php (~64 lines, L189–253)
**Fix:** Extract:
- `buildUpdateSets(array $data): array` — the large if-chain building SET clauses
- Main drops to ~15 lines

### 16B — `apiRequest()` in AgentManager.php (~55 lines, L417–472)
**Fix:** Extract:
- `buildAgentRequestArgs(array $agent, string $method, array $body): array`
- `parseAgentResponse(array $response): array|WP_Error`
- Main drops to ~12 lines

### 16C — `resolveRedirectUrl()` in AgentManager.php (~47 lines, L480–526)
**Fix:** Extract:
- `followRedirectChain(string $url, int $maxRedirects): string|WP_Error`
- Main drops to ~12 lines (cache check → resolve → cache store)

### 16D — `listAgents()` in AgentManager.php (~40 lines, L326–366)
**Fix:** Extract:
- `buildAgentListQuery(array $filters): array` — WHERE + params
- Main drops to ~12 lines

### 16E — `fetch_update_info()` in UpdateResolver.php (~102 lines, L278–380)
**Fix:** Extract:
- `fetchUpdateResponse(string $url): array|WP_Error` — HTTP request + status check
- `parseUpdateResponseBody(string $body, string $contentType, string $url): array` — JSON vs ZIP parsing
- `handleFetchFailure(array $settings, bool $forceCheck, string $error): WP_Error|array` — retry logic
- Main drops to ~15 lines

### 16F — `check_for_plugin_update()` in UpdateResolver.php (~63 lines, L388–451)
**Fix:** Extract:
- `buildUpdateTransientEntry(array $updateInfo): object` — transient object construction
- `buildNoUpdateTransientEntry(): object`
- Main drops to ~15 lines

### 16G — `plugin_info()` in UpdateResolver.php (~31 lines, L461–492)
**Fix:** Extract `buildPluginInfoObject(array $updateInfo): object` — the big return object

### 16H — `apply_filters()` in Database.php (~49 lines, L860–909)
**Fix:** Extract:
- `applyEqualityFilter($query, string $column, string $value)` — handles comma-separated values
- `applyDateRangeFilters($query, array $filters)` — from/to logic
- Main drops to ~15 lines of delegation calls

### 16I — `query_transactions()` in Database.php (~50 lines, L800–850)
**Fix:** Extract:
- `decodeLogDetails(array &$logs)` — the JSON decode loop
- Main drops to ~15 lines

### 16J — `getActionHistory()` in AgentManager.php (~33 lines, L679–712)
**Fix:** Extract `decodeActionDetails(array &$actions)` — JSON decode loop, share pattern with 16I

**Est. impact:** 4 files, ~10 functions → ~15 helpers, ~300 excess lines removed.

---

## Priority Order (Phases 13–16)

| Phase | Severity | File(s) | Functions | Est. Lines Saved |
|-------|----------|---------|-----------|-----------------|
| 13    | 🟠 HIGH | main plugin file | ~15 handlers + utilities | ~400 |
| 14    | 🟡 MEDIUM | Admin.php + main | ~12 data-heavy handlers | ~350 |
| 15    | 🟠 HIGH | Orchestrator, IncrementalBackup, RestoreEngine, Worker | ~14 snapshot functions | ~800 |
| 16    | 🟡 MEDIUM | AgentManager, UpdateResolver, Database | ~10 scattered | ~300 |

**Total remaining functions exceeding 15 lines: ~50+**
**Total excess lines: ~1,850+**
**Estimated helpers to extract: ~70**

---

## Execution Notes

- **Phase 15E** (convertCreateStatement deduplication) is the highest-value single refactor — eliminates 63 lines of copy-paste code across 2 files.
- **Phase 15I** (RestoreEngine::execute) is the single largest remaining function at ~208 lines and should be prioritized.
- **Phase 13A** (handle_upload) at ~105 lines is the largest in the main plugin file.
- Phases can be executed in any order, but 15 → 13 → 14 → 16 is recommended by impact.
