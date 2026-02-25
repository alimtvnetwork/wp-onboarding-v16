# PHP Codebase Audit Report ✅ ALL FIXED

> **Date:** 2026-02-25
> **Status:** All 4 phases resolved
> **Scope:** `wp-plugins/riseup-asia-uploader/includes/` — all Enums, Classes, and Traits
> **References:** `spec/01-app/formatting-rules-reference.md`, `.lovable/memory/architecture/php/naming-conventions.md`, `.lovable/memory/architecture/database/pascal-case-naming-convention.md`

---

## Phase 1: Naming Violations

### P1-1 — UploadSourceType enum VALUES use all-caps abbreviations

**File:** `includes/Enums/UploadSourceType.php`
**Severity:** Medium (breaking change — values stored in DB)
**Spec violated:** Abbreviation casing: only first letter capitalized (`Id`, `Url`, `Md5`, not `ID`, `URL`, `MD5`)

| Line | Current Case Name | Current Value | Correct Value |
|------|-------------------|---------------|---------------|
| 18   | `RestApi` ✓       | `'RestAPI'` ✗ | `'RestApi'`   |
| 19   | `AdminUi` ✓       | `'AdminUI'` ✗ | `'AdminUi'`   |
| 20   | `WpCli` ✓         | `'WPCLI'` ✗   | `'WpCli'`     |

**Case names are correct**, but backed enum **values** violate the spec. Since these values are persisted in the `Transactions` table, fixing them requires a data migration (UPDATE SQL on `UploadSource` column).

**Migration required:**
```sql
UPDATE Transactions SET UploadSource = 'RestApi' WHERE UploadSource = 'RestAPI';
UPDATE Transactions SET UploadSource = 'AdminUi' WHERE UploadSource = 'AdminUI';
UPDATE Transactions SET UploadSource = 'WpCli' WHERE UploadSource = 'WPCLI';
```

### P1-2 — No other naming violations found

All other enum case names, method names, property names, and variable names across the codebase correctly follow the spec:
- `Id` not `ID` ✓ (ResponseKeyType, LogColumnType, AgentSite)
- `Url` not `URL` ✓ (AgentFieldType, ResponseKeyType, WpErrorCodeType)
- `Api` not `API` ✓ (SnapshotTriggerType, TriggerSourceType, WpErrorCodeType)
- `Http` not `HTTP` ✓ (WpErrorCodeType)
- `Db` not `DB` ✓ (WpErrorCodeType, ResponseMessageType)
- camelCase properties and methods ✓ (zero remaining snake_case violations per migration status)

---

## Phase 2: Formatting Violations

### P2-1 — R12: Empty line after opening brace

**Spec:** Opening braces for classes must not be followed by an empty line.

| File | Line | Code |
|------|------|------|
| `Database/DbExecResult.php` | 19–20 | `final class DbExecResult {` → blank line → `private function __construct(` |
| `Database/TypedQuery.php` | 23–24 | `final class TypedQuery {` → blank line → `public function __construct(` |

### P2-2 — Missing PHPDoc file headers

**Spec:** All source files must have a `@package` and `@since` PHPDoc block.

| File | Issue |
|------|-------|
| `Snapshot/SnapshotManager.php` | No PHPDoc header (bare `<?php namespace ...`) |
| `Snapshot/SnapshotExporter.php` | No PHPDoc header (bare `<?php namespace ...`) |

### P2-3 — Non-standard ABSPATH guard format

The standard format used across 50+ files is the multi-line block:
```php
if (!defined('ABSPATH')) {
    exit;
}
```

Two files use a compressed single-line format:

| File | Line | Current |
|------|------|---------|
| `Snapshot/SnapshotManager.php` | 4 | `if (!defined('ABSPATH')) { exit; }` |
| `Snapshot/SnapshotExporter.php` | 4 | `if (!defined('ABSPATH')) { exit; }` |

---

## Phase 3: Untyped Properties (PHP 8.2+ Modernization)

The project requires PHP 8.2+ but several classes use old-style `/** @var */` annotations instead of native typed properties.

### P3-1 — Database.php (5 untyped properties)

| Line | Current | Correct |
|------|---------|---------|
| 39 | `private $pdo = null;` | `private ?PDO $pdo = null;` |
| 42 | `private $dbPath;` | `private string $dbPath;` |
| 45 | `private $fileLogger;` | `private FileLogger $fileLogger;` |
| 48 | `private static $instance = null;` | `private static ?self $instance = null;` |
| 51 | `private $isInitAttempted = false;` | `private bool $isInitAttempted = false;` |

### P3-2 — Orm.php (12 untyped properties)

| Line | Current | Correct |
|------|---------|---------|
| 35 | `private static $pdo = null;` | `private static ?PDO $pdo = null;` |
| 38 | `private $tableName;` | `private string $tableName;` |
| 41 | `private $data = array();` | `private array $data = array();` |
| 44 | `private $whereClauses = array();` | `private array $whereClauses = array();` |
| 47 | `private $whereParams = array();` | `private array $whereParams = array();` |
| 50 | `private $orderBy = array();` | `private array $orderBy = array();` |
| 53 | `private $limitValue = null;` | `private ?int $limitValue = null;` |
| 56 | `private $offsetValue = null;` | `private ?int $offsetValue = null;` |
| 59 | `private $selectColumns = array('*');` | `private array $selectColumns = array('*');` |
| 62 | `private $groupBy = array();` | `private array $groupBy = array();` |
| 65 | `private $isNew = false;` | `private bool $isNew = false;` |
| 68 | `private $id = null;` | `private int\|string\|null $id = null;` |

### P3-3 — EnvelopeBuilder.php (14 untyped properties)

Lines 25–38: All properties use `private $name = value;` without type hints.

---

## Phase 4: Robustness & Failure Analysis

### P4-1 — Orm::rawExecute() silently swallows exceptions

**File:** `Database/Orm.php` lines 100–113
**Risk:** High — SQL errors return empty array, indistinguishable from "no results"

```php
} catch (PDOException $e) {
    return array(); // Caller has no way to know this failed
}
```

**Fix:** Return a result type (e.g., `DbResultSet`) or log the error.

### P4-2 — RootDb::log() contains dead code

**File:** `Database/RootDb.php` lines 86–90
**Risk:** Low — unnecessary check, no runtime failure

```php
$isLoggerAbsent = BooleanHelpers::isValueEmpty($this->logger);
if ($isLoggerAbsent) { return; }
```

`$this->logger` is typed as `FileLogger` (non-nullable) and always set in the constructor. This null check is unreachable dead code.

### P4-3 — ActivationHandler uses error-suppressed file writes

**File:** `Activation/ActivationHandler.php` lines 121, 133, 143
**Risk:** Medium — `@file_put_contents()` silently fails, plugin appears activated but log files may not exist

The `@` suppression operator hides filesystem permission errors. If the logs directory isn't writable, no log files are created and no error is reported.

**Fix:** Check return value and use `InitHelpers::errorLogWithPrefix()` on failure.

### P4-4 — SnapshotExporter::getInstance() returns `static` instead of `self`

**File:** `Snapshot/SnapshotExporter.php` line 22
**Risk:** Low — could cause type confusion if subclassed (class is not `final`)

```php
public static function getInstance(...): static { // should be `self`
```

All other singleton `getInstance()` methods return `self`. This one returns `static`, which is inconsistent and could cause issues if the class is extended.

---

## Summary & Priority

| Phase | Issues | Effort | Breaking? |
|-------|--------|--------|-----------|
| P1 — Naming (UploadSourceType values) | 1 enum, 3 values | Small + migration | Yes (DB) |
| P2 — Formatting (R12, PHPDoc, guard) | 4 files, 6 spots | Small | No |
| P3 — Untyped properties | 3 files, ~31 properties | Medium | No |
| P4 — Robustness | 4 issues | Small–Medium | No |

### Recommended Fix Order

1. **P2 — Formatting** (no risk, pure style compliance)
2. **P4 — Robustness** (code quality, low risk)
3. **P3 — Typed properties** (modernization, low risk but more lines)
4. **P1 — Naming values** (last because it requires a DB migration)
