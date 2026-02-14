# Plan: PathConst Reorganization, Spec Alignment & Remaining Migrations

> **Created:** 2026-02-14  
> **Status:** Planning — no code changes until specs are updated

---

## Audit Findings

### 1. PathConst Is Defined but Never Used

`PathConst.php` defines 14 constants but **zero callers exist**. All code references legacy `define()` constants from `constants.php`:

| PathConst constant | Legacy `define()` constant | Used by |
|-|-|-|
| `PathConst::LOGS_SUBDIR` → `/logs` | `LOGS_SUBDIR` → `logs` | PathUtilsCoreTrait, ActivationHandler |
| `PathConst::TEMP_SUBDIR` → `/temp` | `TEMP_SUBDIR` → `temp` | PathUtilsCoreTrait |
| `PathConst::SNAPSHOTS_SUBDIR` → `/snapshots` | `SNAPSHOTS_SUBDIR` → `snapshots` | PathUtilsCoreTrait |
| `PathConst::EXPORTS_SUBDIR` → `/exports` | `SNAPSHOT_EXPORTS_SUBDIR` → `exports` | ExporterBuildTrait |
| `PathConst::PLUGIN_DB` → `/riseup-asia-uploader.db` | `DB_FILENAME` → `riseup-asia-uploader.db` | PathUtilsCoreTrait |
| `PathConst::LOG_FILE` → `/log.txt` | `LOG_FILENAME` → `log.txt` | ActivationHandler |
| `PathConst::ERROR_FILE` → `/error.txt` | `ERROR_LOG_FILENAME` → `error.txt` | ActivationHandler |
| `PathConst::STACKTRACE_FILE` → `/stacktrace.txt` | `STACKTRACE_FILENAME` → `stacktrace.txt` | ActivationHandler |
| `PathConst::FATAL_ERROR_LOG` | _(hardcoded string)_ | FatalErrorHandler |
| `PathConst::ROOT_DB`, `ACTIVITY_DB`, `SNAPSHOT_DB` | _(no legacy equivalent)_ | None |
| `PathConst::DETECTION_FILE` | _(no legacy equivalent)_ | None |

**Value format mismatch:** PathConst prefixes with `/` (e.g., `/logs`), legacy constants don't (e.g., `logs`).

### 2. PathConst Is a Class in the Enums Folder

PathConst is a `final class` (not an enum) living in `includes/Enums/`. The user wants it reorganized to follow the naming convention and provide more granularity.

**Proposal:** Split PathConst into domain-specific backed enums where feasible, keeping it as `final class` only for values that genuinely cannot be enum cases (composable fragments). The user suggests a `PathType` naming pattern.

#### Candidate Decomposition

| New Type | Cases | Rationale |
|----------|-------|-----------|
| `PathSubdirType` (enum) | `Logs`, `Temp`, `Snapshots`, `Exports` | Answers "which subdirectory?" — discrete set |
| `PathDatabaseType` (enum) | `Root`, `Activity`, `Snapshot`, `Plugin` | Answers "which database?" — discrete set |
| `PathLogFileType` (enum) | `Log`, `FatalError`, `Stacktrace`, `Error` | Answers "which log file?" — discrete set |
| `PathConfigType` (enum) | `Detection` | Answers "which config file?" — discrete set |

All four answer "which one?" → they qualify as backed enums with the `Type` suffix.

**Alternative (simpler):** Keep a single `PathType` enum with grouped cases using section comments, similar to `ActionType` and `HookType`. This avoids over-fragmentation for only 14 values.

### 3. Spec Inconsistencies Found

| File | Line | Issue |
|------|------|-------|
| `spec/04-php-standards/README.md` | L17 | Says `Method names → snake_case` but project is migrating to camelCase |
| `spec/04-php-standards/README.md` | L22 | Says `no Enum suffix → UploadSource` but actual enums use `Type` suffix (`UploadSourceType`) |
| `spec/04-php-standards/README.md` | L20 | File naming example says `UploadSource.php` but actual file is `UploadSourceType.php` |
| `spec/04-php-standards/enums.md` | L84,89 | UploadSourceType example uses `valid_values()`, `is_valid()` — snake_case, should be camelCase |
| `spec/04-php-standards/enums.md` | L233-235 | HookType example uses `ajax_nopriv()` — snake_case static method |
| `spec/04-php-standards/enums.md` | L363-371 | ErrorChecker example uses `is_fatal_error()`, `get_type_label()` — snake_case |
| `spec/04-php-standards/README.md` | L48,74,139,246 | Various code examples still use snake_case method names |

### 4. PathUtils Traits — snake_case Methods

| Trait | snake_case methods |
|-------|-------------------|
| `PathUtilsFileTrait` | `file_exists`, `dir_exists`, `is_writable`, `get_relative_path`, `delete_file`, `delete_dir`, `get_free_space`, `format_bytes` |
| `PathUtilsCoreTrait` | `safe_log` reference in FileTrait (actual method is `safeLog` — mismatch!) |
| `PathUtilsDirTrait` | (needs reading to confirm) |

**Critical bug found:** `PathUtilsFileTrait` calls `self::safe_log()` (snake_case) but `PathUtilsCoreTrait` defines it as `safeLog()` (camelCase). This would cause a fatal error if those code paths execute.

---

## Phased Execution Plan

### Phase K1: Spec Updates (no code changes)

1. **Update `spec/04-php-standards/README.md`**
   - Fix method naming row: `snake_case` → `camelCase` (reflect current migration)
   - Fix enum naming row: add `Type` suffix, update examples
   - Fix file naming examples to match actual files
   - Update all code examples to use camelCase methods

2. **Update `spec/04-php-standards/enums.md`**
   - Fix UploadSourceType example: `valid_values()` → `validValues()`, `is_valid()` → `isValid()`
   - Fix HookType: `ajax_nopriv()` → `ajaxNopriv()`
   - Update PathConst section to reflect new architecture (PathType enum or split enums)
   - Update ErrorChecker examples to camelCase
   - Update classification table

3. **Decision required:** Single `PathType` enum vs. split into 4 domain enums (`PathSubdirType`, `PathDatabaseType`, `PathLogFileType`, `PathConfigType`)

### Phase K2: PathConst → PathType Migration

1. Create new enum(s) in `includes/Enums/`
2. Add typed accessors to `RiseupPathUtils` for any missing paths
3. Migrate all callers from legacy `define()` constants → new enum values
4. Remove legacy path constants from `constants.php`
5. Remove `PathConst.php`
6. Update `require_once` in `riseup-asia-uploader.php`

### Phase K3: Fix PathUtils snake_case → camelCase

1. Fix `safe_log` → `safeLog` call mismatch in `PathUtilsFileTrait`
2. Rename all `PathUtilsFileTrait` methods to camelCase
3. Rename `PathUtilsDirTrait` methods to camelCase
4. Update all callers across the codebase

### Phase K4: EndpointType route() Helper & Caller Migration

1. Add `route(): string` method to `EndpointType` enum — returns `'/' . $this->value`
2. Update `RouteRegistrationTrait` — replace all `EndpointType::X->value` with `EndpointType::X->route()` in `$safeRegister()` calls
3. Update `PluginRouteRegistrationTrait` — same replacement in `registerPluginRoutes()` and `registerAgentRoutes()`
4. Update `SnapshotRouteRegistrationTrait` — same replacement
5. Update `$safeRegister` closure in `registerRoutes()` — remove the `'/' .` prefix since `route()` now handles it
6. Verify: no remaining `EndpointType::X->value` in route registration contexts (grep for `->value` near `$safeRegister`)

**Files affected:**
- `includes/Enums/EndpointType.php` — add `route()` method
- `includes/Traits/Route/RouteRegistrationTrait.php` — update `$safeRegister` closure + all calls
- `includes/Traits/Plugin/PluginRouteRegistrationTrait.php` — update all calls
- `includes/Traits/Snapshot/SnapshotRouteRegistrationTrait.php` — update all calls

**Note:** `->value` remains valid for non-routing contexts (logging, building remote URLs, domain checks).

### Phase K5: Fix Enum Internal Methods → camelCase

1. `UploadSourceType`: `valid_values()` → `validValues()`, `is_valid()` → `isValid()`
2. `HookType`: `ajax_nopriv()` → `ajaxNopriv()`
3. Update all callers

---

## Remaining Items from Previous Discussions

### camelCase Migration (Feature I) — Outstanding Batches

| Batch | Scope | Status |
|-------|-------|--------|
| Database migration traits V1-V11 | `migrate_v1()` through `migrate_v11()` + `runAllMigrations` callers | Pending |
| DatabaseConnectionTrait | Connection/init methods | Pending |
| DatabaseQueryLogTrait | `log_transaction()` and related | Pending |
| DatabaseQuerySearchTrait | Search/filter methods | Pending |
| ORM traits (OrmQueryTrait, OrmMutationTrait) | `select_column()`, `do_insert()`, `$table_name`, `$where_clauses` | Pending |
| BooleanValueTrait | `is_truthy()`, `starts_with()` etc. | Pending |
| Snapshot traits | `$provider_id` and related properties | Pending |
| ErrorChecker | `is_fatal_error()`, `get_type_label()` | Pending |
| BooleanHelpers | All `is_*` domain helpers | Pending |

### Spec & Architecture (from current plan.md)

| Item | Status |
|------|--------|
| Create `spec/01-coding-guidelines/function-naming.md` | Pending |
| Create `spec/01-coding-guidelines/strict-typing.md` | Pending |
| Update cross-references in spec READMEs | Pending |
| Part 3 — Codebase typing remediation (add types to all params/returns) | Pending |

---

## Next Task Selection

→ **Phase K1** — Update specs to reflect current naming conventions and PathType architecture decision. Requires user input on single vs. split enum approach.
