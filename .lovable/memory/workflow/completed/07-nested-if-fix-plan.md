# Nested-If Flattening — Phased Fix Plan
Updated: 2026-02-13

## Rule Reference
> Logic must be flattened to avoid nested `if` blocks through combined conditions or early returns.
> Any condition with two or more operators must be extracted into a named boolean variable.

## Violation Categories

### Category A: Guard-inside-guard (flatten via combined condition or early return)
Nested `if` where the inner block is a guard that could be merged with the outer condition.

### Category B: Auth header fallback chains (duplicated code — extract to helper)
Two identical 20-line auth-header resolution blocks in `check_authenticated_only()` and `check_authenticated_capability()`.

### Category C: Deep nesting inside loops/try-catch (flatten via `continue` or early return)
`if` inside `foreach` inside `try` — can often be flattened with `continue` guards.

### Category D: Version detection nesting (flatten via early return)
Nested `if` blocks for OPcache invalidation + file reading.

---

## Phase 1 — Auth Helper Extraction (HIGH IMPACT, eliminates duplication)
**File:** `riseup-asia-uploader.php` (lines ~1250-1470)
**Violations:** 2 identical blocks × 6 nested `if` each = 12 violations
**Fix:** Extract `resolve_auth_header(WP_REST_Request $request): ?string` private method. Both `check_authenticated_only()` and `check_authenticated_capability()` call it, eliminating ~100 lines of duplication and all nested `if` blocks within the fallback chain.

```
Before (nested):
  if (empty($auth_header)) {
      if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
          ...
      } elseif (function_exists('getallheaders')) {
          $headers = getallheaders();
          if (isset($headers['Authorization'])) {  // ← 3 levels deep
              ...

After (flat):
  $auth_header = $this->resolve_auth_header($request);
  if (empty($auth_header)) {
      return new WP_Error(...);
  }
```

---

## Phase 2 — SnapshotManager::restoreSnapshot() (lines 165-185)
**File:** `includes/Snapshot/SnapshotManager.php`
**Violations:** 3 levels: `if (scope === 'incremental')` → `if ($master_dirname)` → `if ($isMasterMissing)`
**Fix:** Flatten with combined early-return guard:
```php
$isIncrementalWithMaster = isset($snapshot['scope'])
    && $snapshot['scope'] === 'incremental'
    && !empty($tables_meta['master'] ?? null);

if ($isIncrementalWithMaster) {
    $master_dir = dirname(dirname($snapshot['filepath']));
    $isMasterMissing = RiseupBooleanHelpers::is_dir_missing($master_dir)
        || RiseupBooleanHelpers::is_file_missing($master_dir . '/a-root.db');
    if ($isMasterMissing) {
        return array(...);
    }
}
```

---

## Phase 3 — Version Detection Blocks
**File:** `riseup-asia-uploader.php`
**Location 1:** `handle_status()` (lines ~1529-1541) — 3 levels
**Location 2:** `handle_upload()` (lines ~2071-2092) — 4 levels
**Fix:** Extract `read_plugin_version_from_disk(string $plugin_file): string` private method that handles OPcache invalidation + file header parsing in a flat sequence with early returns.

---

## Phase 4 — UpdateResolver::resolve_url() (lines 155-167)
**File:** `includes/Update/UpdateResolver.php`
**Violations:** `if (redirect_status)` → `if (empty($location))` + `if (strpos(...))` = 3 levels
**Fix:** Use `continue` guards at the top of the while loop:
```php
if (!in_array($status_code, $redirect_codes)) {
    // Final URL found
    return $current_url;
}

$location = wp_remote_retrieve_header($response, 'location');
if (empty($location)) {
    return new WP_Error(...);
}
```

---

## Phase 5 — Activation Hook Bootstrap (lines ~5160-5220)
**File:** `riseup-asia-uploader.php`
**Violations:** `if (!error)` → `if (is_dir_missing)` → `if (is_file_missing)` and security file block
**Fix:** Early return if `$upload_dir['error']`, then flat sequence of directory creation and file writes.

---

## Phase 6 — UploadIgnore Pattern Loading (lines 73-93)
**File:** `includes/Upload/UploadIgnore.php`
**Violations:** `foreach` → `if (empty/comment)` → `if (negation)` = 3 levels
**Fix:** Already uses `continue` for skip — the negation `if` inside the loop body is a valid flat pattern. **Low priority / borderline — may skip.**

---

## Phase 7 — FileCache::scanDirectory() (lines 298-313)
**File:** `includes/Database/FileCache.php`
**Violations:** `foreach` → `if (dot)` → `if (ignore)` → `if (is_dir)` = 4 levels
**Fix:** Combine dot-check and ignore-check into `continue` guards at loop top, then single `if/else` for dir vs file.

---

## Phase 8 — Remaining scattered nested-ifs across Snapshot subsystem
**Files:** `SnapshotCleaner.php`, `SnapshotOrchestrator.php`, `IncrementalBackup.php`, `SnapshotExporter.php`
**Violations:** Various 2-level nestings inside try-catch/foreach blocks
**Fix:** Case-by-case early returns and continue guards.

---

## Priority Order
| Phase | Impact | Files | Est. Violations Fixed |
|-------|--------|-------|-----------------------|
| 1     | HIGH   | 1     | 12 (+ eliminates code duplication) |
| 2     | MEDIUM | 1     | 3  |
| 3     | MEDIUM | 1     | 7  |
| 4     | MEDIUM | 1     | 3  |
| 5     | LOW    | 1     | 4  |
| 6     | LOW    | 1     | 1 (borderline)  |
| 7     | LOW    | 1     | 4  |
| 8     | LOW    | 4     | ~8 |

**Total estimated violations: ~42**
