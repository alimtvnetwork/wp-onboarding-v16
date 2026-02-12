# Naming Convention Refactor Plan — Riseup Asia Uploader

> **Version:** 1.0.0  
> **Created:** 2026-02-12  
> **Status:** Planning  
> **Target:** `wp-plugins/riseup-asia-uploader/`

---

## Audit Summary

| Category | Current State | Required State | Violation Count |
|----------|--------------|----------------|-----------------|
| Constants prefix | `RISEUP_*` everywhere | No `RISEUP_` prefix | ~200+ constants |
| Method names | Mixed camelCase/snake_case | snake_case only | ~50+ methods |
| Class names | `Riseup_Underscore_Style` | `PascalCase` (e.g., `RiseupDatabase`) | ~20 classes |
| Enum classes | Most missing as separate files | Dedicated `class-*-enum.php` files | ~5 enums needed |
| Hook usage | String literals in main file | `HookEnum::*` constants | ~10 call sites |
| Path accessors | camelCase (`getRootDb`) | snake_case (`get_root_db`) | ~15 accessors |
| Boolean helpers | Trivial wrappers still referenced | Native PHP or semantic methods | Audit needed |
| Upload source | `define()` constants | `UploadSourceEnum` class | 5 constants |
| Error type mapping | `riseup_error_type_to_string()` | `ErrorChecker::get_type_label()` | 1 function |

---

## Phase 1 — Foundation: Enum Classes (No Breaking Changes)

**Goal:** Create all enum class files that don't exist yet as separate files, using the spec patterns.

### Tasks

1. **Create `class-hook-enum.php`** — Move/verify `HookEnum` class into its own file
2. **Create `class-path-enum.php`** — Move/verify `PathEnum` class into its own file
3. **Create `class-error-type-enum.php`** — Move/verify `ErrorTypeEnum` class into its own file
4. **Create `class-capability-enum.php`** — Extract `RISEUP_CAP_*` constants into `CapabilityEnum`
5. **Create `class-http-method-enum.php`** — Extract HTTP method constants into `HttpMethodEnum`
6. **Create `class-upload-source-enum.php`** — Convert `UPLOAD_SOURCE_*` defines into `UploadSourceEnum`
7. **Create `class-error-checker.php`** — Move/verify `ErrorChecker` into its own file
8. **Register all enum files in `RiseupDependencyLoader::loadManifest()`** as foundation files (loaded via raw `require_once` before the loader)

### Acceptance Criteria

- Each enum class lives in its own `class-kebab-case.php` file
- All enum class names use PascalCase with `Enum` suffix
- All constants use `UPPER_SNAKE_CASE`
- No functional changes to existing code yet

---

## Phase 2 — Constants: Remove `RISEUP_` Prefix

**Goal:** Rename all `define()` constants to remove the `RISEUP_` prefix per spec.

### Strategy

This is the highest-risk phase. Constants are referenced across all files.

### Tasks

1. **Catalog all `RISEUP_*` constants** — Build a full mapping of old → new names
2. **Migrate categorized constants to enum classes** (Phase 1 classes):
   - `RISEUP_CAP_*` → `CapabilityEnum::*`
   - `RISEUP_ENDPOINT_*` → remain as `define()` but without prefix (e.g., `ENDPOINT_STATUS`)
   - `RISEUP_ACTION_*` → remain as `define()` but without prefix (e.g., `ACTION_UPLOAD`)
   - `RISEUP_HTTP_*` → `HttpStatusEnum::*` or keep as defines without prefix
   - `RISEUP_LOG_LEVEL_*` → `LogLevelEnum::*`
   - `RISEUP_SNAPSHOT_STATUS_*` → `SnapshotStatusEnum::*`
   - `RISEUP_SNAPSHOT_PROVIDER_*` → `SnapshotProviderEnum::*`
   - `RISEUP_ERR_*` → `ErrorCodeEnum::*`
   - `RISEUP_TRIGGERED_BY_*` → `TriggerSourceEnum::*`
3. **Update `constants.php`** — Remove prefix from remaining `define()` calls
4. **Find-and-replace across all class files** — Update every reference
5. **Compose named constants** for REST URLs and AJAX hooks per spec (e.g., `REST_URL_UPLOAD`, `HOOK_AJAX_UPLOAD`)

### Acceptance Criteria

- Zero constants with `RISEUP_` prefix remain
- All categorized constants live in enum classes
- Composed constants exist for REST URL + AJAX hook patterns
- Plugin loads and all endpoints respond correctly

---

## Phase 3 — Class Names: Underscore → PascalCase

**Goal:** Rename all classes from `Riseup_Underscore_Style` to `RiseupPascalCase`.

### Mapping (examples)

| Current | New |
|---------|-----|
| `Riseup_Asia` | `RiseupAsia` |
| `Riseup_Database` | `RiseupDatabase` |
| `Riseup_File_Logger` | `RiseupFileLogger` |
| `Riseup_Logger` | `RiseupLogger` |
| `Riseup_Post_Manager` | `RiseupPostManager` |
| `Riseup_Admin` | `RiseupAdmin` |
| `Riseup_ORM` | `RiseupOrm` |
| `Riseup_Update_Resolver` | `RiseupUpdateResolver` |

### Tasks

1. **Rename class declarations** in each file
2. **Update all `new ClassName()` and `ClassName::method()` references** across all files
3. **Update `RiseupDependencyLoader::loadManifest()` labels**
4. **Update PHPDoc `@var` type hints**
5. **File names stay as `class-kebab-case.php`** — no file rename needed

### Acceptance Criteria

- All class names are PascalCase (no underscores)
- All references updated across the codebase
- Plugin loads successfully

---

## Phase 4 — Method Names: camelCase → snake_case

**Goal:** Convert all method names from camelCase to snake_case.

### High-Impact Methods

| Current | New |
|---------|-----|
| `loadManifest()` | `load_manifest()` |
| `logSummary()` | `log_summary()` |
| `initComponent()` | `init_component()` |
| `getDataDir()` | `get_data_dir()` |
| `getRootDb()` | `get_root_db()` |
| `getLogsDir()` | `get_logs_dir()` |
| `getTempDir()` | `get_temp_dir()` |
| `getActivityDb()` | `get_activity_db()` |
| `getSnapshotDb()` | `get_snapshot_db()` |
| `getPluginDb()` | `get_plugin_db()` |
| `getLogFile()` | `get_log_file()` |
| `getFatalErrorLog()` | `get_fatal_error_log()` |
| `getStacktraceFile()` | `get_stacktrace_file()` |
| `getErrorFile()` | `get_error_file()` |
| `getDetectionFile()` | `get_detection_file()` |
| `isDirMissing()` | `is_dir_missing()` |
| `getInstance()` | `get_instance()` |
| `initSqliteConnection()` | `init_sqlite_connection()` |
| `ensureDir()` | `ensure_dir()` |
| `formatStackFrames()` | `format_stack_frames()` |
| `registerCronSchedules()` | `register_cron_schedules()` |

### Tasks

1. **Rename method declarations** in each class file
2. **Update all call sites** across all files
3. **Update PHPDoc** if method names appear in comments
4. **Special attention to `RiseupPathUtils`** — all accessors must become snake_case
5. **Special attention to `RiseupDependencyLoader`** — loader methods

### Acceptance Criteria

- Zero camelCase methods remain (except PHP magic methods like `__construct`)
- All call sites updated
- Plugin loads and all endpoints respond correctly

---

## Phase 5 — Hook & Path Compliance

**Goal:** Replace all remaining string literals and inline concatenation with enum constants and typed accessors.

### Tasks

1. **Replace string literals in `add_action()`/`add_filter()` calls** with `HookEnum::*` constants
2. **Replace `current_user_can('manage_options')` etc.** with `CapabilityEnum::*`
3. **Remove `riseup_error_type_to_string()` function** — replace with `ErrorChecker::get_type_label()`
4. **Remove inline `$fatal_types` array** in `riseup_fatal_error_handler()` — use `ErrorChecker::is_fatal_error()`
5. **Audit all manual path concatenation** — ensure all go through `RiseupPathUtils` typed accessors
6. **Remove deprecated `RiseupBooleanHelpers` trivial methods** if still present
7. **Compose named constants** for any remaining inline concatenation at call sites

### Acceptance Criteria

- Zero magic strings in hook registrations
- Zero inline `E_*` type arrays
- Zero manual path concatenation in business logic
- All capability checks use `CapabilityEnum`

---

## Phase 6 — Cleanup & Validation

**Goal:** Final sweep and validation.

### Tasks

1. **Remove legacy backward-compat aliases** if any were added during migration
2. **Update `CODING-GUIDELINES.md`** to reflect new conventions
3. **Update spec `enums.md`** if new enum classes were added
4. **Full grep audit** for any remaining violations:
   - `RISEUP_` prefix constants
   - camelCase method declarations
   - Underscore-style class names
   - String literals in `add_action`/`add_filter`
   - Manual path concatenation
5. **Test all REST endpoints** via the dashboard/API

### Acceptance Criteria

- Zero convention violations
- All endpoints functional
- Documentation updated

---

## Execution Order & Dependencies

```
Phase 1 (Enum classes)
  └── Phase 2 (Constants prefix removal) — depends on Phase 1 enum files existing
       └── Phase 3 (Class names) — can run after Phase 2
            └── Phase 4 (Method names) — can run after Phase 3
                 └── Phase 5 (Hook & path compliance) — can run after Phase 4
                      └── Phase 6 (Cleanup) — final
```

**Phases 3 and 4 could theoretically run in parallel**, but sequential is safer to avoid merge conflicts across 30+ files.

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Missed reference after rename | High | Medium | Full grep after each phase |
| Runtime fatal from typo | Medium | High | Test each endpoint after each phase |
| Constants.php load order issue | Low | High | Enum files loaded as foundation (raw require_once) |
| WP-Cron breaking after method rename | Medium | High | Test scheduled snapshot jobs specifically |
| External API consumers breaking | Low | Low | REST endpoints stay the same (routes unchanged) |
