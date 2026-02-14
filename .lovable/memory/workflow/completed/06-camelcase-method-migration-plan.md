# camelCase Method Migration Plan
Updated: 2026-02-13

## Summary

Migrate all PHP method names from `snake_case` to `camelCase` across the entire `riseup-asia-uploader` plugin. ~200+ method definitions across 14+ files, plus all call sites in the main plugin file (5333 lines) and inter-class references.

## Severity Legend
- 🔴 HIGH: Many methods + many external call sites
- 🟠 MEDIUM: Several methods, mostly internal calls
- 🟡 LOW: Few methods or self-contained

---

## Phase 1 — `PostManager.php` (🟡 ~8 methods)

| Old (snake_case) | New (camelCase) |
|------------------|-----------------|
| `create_post` | `createPost` |
| `update_post` | `updatePost` |
| `list_posts` | `listPosts` |
| `create_category` | `createCategory` |
| `list_categories` | `listCategories` |
| `validate_post_status` | `validatePostStatus` |
| `set_featured_image` | `setFeaturedImage` |
| `assign_categories` | `assignCategories` |

**Call sites:** Main plugin file handler methods (`handle_list_posts`, `handle_create_post`, etc.)

---

## Phase 2 — `AgentManager.php` (🟠 ~12 methods)

| Old | New |
|-----|-----|
| `get_instance` | `getInstance` |
| `add_agent` | `addAgent` |
| `update_agent` | `updateAgent` |
| `remove_agent` | `removeAgent` |
| `get_agent` | `getAgent` |
| `list_agents` | `listAgents` |
| `normalize_url` | `normalizeUrl` |
| `build_auth_header` | `buildAuthHeader` |
| `api_request` | `apiRequest` |
| `resolve_redirect_url` | `resolveRedirectUrl` |
| `test_connection` | `testConnection` |
| `sync_plugins` | `syncPlugins` |
| `execute_plugin_action` | `executePluginAction` |

---

## Phase 3 — `UploadIgnore.php` (🟡 ~6 methods)

| Old | New |
|-----|-----|
| `should_ignore` | `shouldIgnore` |
| `get_patterns` | `getPatterns` |
| `get_negations` | `getNegations` |
| `is_loaded` | `isLoaded` |
| `compile_pattern` | `compilePattern` |
| `match_pattern` | `matchPattern` |
| `from_directory` | `fromDirectory` |

---

## Phase 4 — `Database.php` (🔴 ~20+ methods)

All DB access methods: `create_tables`, `query_single`, `query_all`, `get_pdo`, `log_transaction`, `query_transactions`, `get_stats`, etc.

**Impact:** Called from nearly every other class.

---

## Phase 5 — `FileLogger.php` + `Logger.php` (🟠 ~10 methods each)

Logger methods called everywhere: `log_post_action`, `log_upload`, etc.

---

## Phase 6 — Snapshot subsystem (🔴 ~60+ methods across 6 files)

- `SnapshotManager.php`
- `SnapshotOrchestrator.php`
- `SnapshotScheduler.php`
- `SnapshotCleaner.php`
- `SnapshotExporter.php`
- `IncrementalBackup.php`

---

## Phase 7 — `Admin.php` + `UpdateResolver.php` + `ErrorChecker.php` (🟠)

WordPress hook callbacks — must update `add_action`/`add_filter` references.

---

## Phase 8 — Main plugin file `riseup-asia-uploader.php` (🔴 ~80+ methods)

All handler methods (`handle_upload`, `handle_status`, `handle_list_plugins`, etc.), permission checks, utility helpers. Plus ALL `register_rest_route` callback arrays.

---

## Phase 9 — Enum static methods (🟡)

- `UploadSourceType::valid_values` → `validValues`
- `UploadSourceType::is_valid` → `isValid`

---

## Execution Order

1. Phase 1 (PostManager) + update call sites in main file
2. Phase 2 (AgentManager) + update call sites in main file
3. Phase 3 (UploadIgnore) + update call sites
4. Phase 4 (Database) + update ALL consumers
5. Phase 5 (Loggers) + update ALL consumers
6. Phase 6 (Snapshot subsystem) + update call sites
7. Phase 7 (Admin/UpdateResolver/ErrorChecker) + hooks
8. Phase 8 (Main plugin file) — methods + route callbacks
9. Phase 9 (Enum statics)

## Notes
- WordPress hook callbacks (e.g., `add_action('init', array($this, 'method_name'))`) must be updated together with the method rename
- `__construct` is already camelCase — no change needed
- Static factory methods (`get_instance`) become `getInstance`
