

# Plan: Doc Updates + Three New Code Style Rules + PHP Enforcement

## Part A: Spec/Doc Files — Rename PathUtils to PathHelper, CleanerUtilsTrait to CleanerHelperTrait, RestoreUtilsTrait to RestoreHelperTrait

### Files to update (12 total)

| # | File | Changes |
|---|------|---------|
| 1 | `spec/04-php-standards/README.md` | `RiseupPathUtils` -> `PathHelper`, update namespace refs to `RiseupAsia\Helpers\PathHelper` |
| 2 | `spec/04-php-standards/enums.md` | `RiseupPathUtils` -> `PathHelper` in usage examples and checklist |
| 3 | `spec/04-php-standards/forbidden-patterns.md` | `RiseupPathUtils::getRootDb()` -> `PathHelper::getRootDb()` |
| 4 | `spec/07-wordpress-plugin-development/07-error-handling.md` | `RiseupPathUtils` -> `PathHelper` in all code examples |
| 5 | `spec/07-wordpress-plugin-development/08-path-handling.md` | `RiseupPathUtils` -> `PathHelper`, update isDirMissing examples |
| 6 | `spec/01-coding-guidelines/no-negatives.md` | Update file/dir guard examples to use `PathHelper` instead of `RiseupBooleanHelpers` |
| 7 | `.lovable/memory/architecture/php/coding-standards-semantic-and-safety.md` | `RiseupPathUtils` -> `PathHelper`, note file/dir guards moved from `BooleanHelpers` to `PathHelper` |
| 8 | `.lovable/memory/architecture/php/trait-decomposition-map.md` | `PathUtils` -> `PathHelper`, trait names updated, `BooleanDomainTrait` guard list updated (file/dir guards removed) |
| 9 | `wp-plugins/riseup-asia-uploader/CODING-GUIDELINES.md` | All `PathUtils` refs -> `PathHelper` |
| 10 | `wp-plugins/riseup-asia-uploader/PHASE-7-COMPLETION-REPORT.md` | `PathUtils` -> `PathHelper` in tree and migration table |
| 11 | `.lovable/plan.md` | `PathUtils` -> `PathHelper` |
| 12 | `.lovable/memory/issues-fixed/12-php-circular-dependency-bootstrap.md` | If references exist, update |

---

## Part B: Three New/Updated Code Style Rules

### B1: Blank line before `return` AND `throw` (update existing Rule 4)

**File:** `spec/01-coding-guidelines/code-style.md` (Rule 4, line ~213)

Current Rule 4 title: "Blank Line Before `return` When Preceded by Other Statements"

Updated to: "Blank Line Before `return` or `throw` When Preceded by Other Statements"

Add `throw` examples in all three languages alongside the existing `return` examples. The rule: if the block has any other statement before `return` or `throw`, insert one blank line before it. If `return`/`throw` is the sole statement, no blank line needed.

### B2: No backslash prefix on `Throwable` (new Rule 8)

**File:** `spec/01-coding-guidelines/code-style.md` — add new Rule 8

```
## Rule 8: No Leading Backslash on Global Types

In catch blocks and type hints, use `Throwable` without the leading backslash,
even in namespaced files. The same applies to other global types used in
catch blocks or parameter hints.

// FORBIDDEN
catch (\Throwable $e)
function foo(\Throwable $e): array

// REQUIRED
catch (Throwable $e)
function foo(Throwable $e): array
```

Also update `spec/04-php-standards/README.md` which currently shows `catch (\Throwable $e)` in its own examples.

### B3: Multi-line parameters when function has more than 2 params (new Rule 9)

**File:** `spec/01-coding-guidelines/code-style.md` — add new Rule 9

```
## Rule 9: Multi-Line Parameters — More Than Two Parameters

When a function/method signature has more than two parameters, each parameter
must be on its own line with consistent indentation and a trailing comma
after the last parameter.

// FORBIDDEN (>2 params on one line)
function buildRecord(string $label, string $path, bool $success, ?string $error): void {

// REQUIRED
function buildRecord(
    string $label,
    string $path,
    bool $success,
    ?string $error,
): void {
```

Trailing comma rule applies across PHP, TypeScript, and Go (where syntax permits).

---

## Part C: PHP Code Enforcement

### C1: Remove `\` from `Throwable` — 19 PHP files, ~155 occurrences

All `\Throwable` -> `Throwable` in:
- `catch (\Throwable $e)` -> `catch (Throwable $e)`
- Parameter type hints: `\Throwable $e` -> `Throwable $e`
- Return type hints if any

**Files (19):**
`ErrorResponse.php`, `FrameBuilder.php`, `LoggerLevelMethodsTrait.php`, `LoggerWriteTrait.php`, `CategoryTrait.php`, `PostCrudTrait.php`, `PostQueryTrait.php`, `SnapshotImport.php`, `SnapshotScheduler.php`, `SnapshotCleaner.php`, `RestoreEngine.php`, `UploadIgnore.php`, `InitStartupTrait.php`, `SnapshotProviderWpReset.php`, `DependencyLoader.php`, and remaining files from the search results.

### C2: Multi-line params for functions with >2 params — ~26 PHP files

Reformat all function signatures with 3+ parameters to use one-param-per-line with trailing comma. Key files include:

- `ImportExecutionTrait.php` — `registerImportedSnapshot()`, `buildSnapshotRecord()`
- `RestoreHelperTrait.php` — `buildRestoreResult()`
- `AgentLoggingTrait.php` — `logAction()`, `insertActionRecord()`
- `LoggerLevelMethodsTrait.php` — `logAt()`
- `LoggerWriteTrait.php` — `persistToErrorSessions()`, `insertErrorSession()`
- `DatabaseQueryLogTrait.php` — `buildTransactionRecord()`
- `WorkerJobLifecycleTrait.php` — `updateJobBatchProgress()`
- `NativeSnapshotExecTrait.php` — `buildExportResult()`
- `OrchestratorRegistrationTrait.php` — `registerSnapshot()`, `insertSnapshotRecord()`
- `ErrorResponse.php` — all 5 static methods (3-5 params each)
- And ~16 more files with >2-param signatures

### C3: Blank line before `return`/`throw` enforcement in PHP

Audit and fix all `catch` blocks and multi-statement `if` blocks where `return`/`throw` is not preceded by a blank line. This overlaps heavily with C1 files since every `catch` block that logs then returns needs checking.

---

## Execution Order

1. Update `spec/01-coding-guidelines/code-style.md` — add `throw` to Rule 4, add Rules 8 and 9
2. Update all 12 doc/spec files (Part A) — PathUtils -> PathHelper rename
3. Fix all 19 PHP files — remove `\` from Throwable (Part C1)
4. Reformat all ~26 PHP files — multi-line params with trailing commas (Part C2)
5. Audit and fix blank line before return/throw across all PHP files (Part C3)

Steps 1-2 can run in parallel. Steps 3-5 are sequential due to overlapping files.

**Estimated scope:** ~45 files total (12 docs + ~30 unique PHP files with some overlap across C1/C2/C3).

