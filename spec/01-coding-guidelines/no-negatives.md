# Cross-Language Rule: No Raw Negations — Use Positive Guard Functions

> **Version:** 1.0.0  
> **Updated:** 2026-02-13  
> **Applies to:** PHP, TypeScript, Go

---

## Principle

**Never use raw negation operators (`!`, `not`) on function calls or existence checks in conditions.** Instead, wrap every negative check in a **positively named utility function** that reads as a single intent.

Raw negations are easy to miss during code review, cause cognitive overhead, and scatter low-level logic across call sites. A named guard function centralizes the check, is self-documenting, and eliminates the visual noise of `!`.

---

## The Rule

| ❌ Forbidden | ✅ Required | Why |
|-------------|------------|-----|
| `!file_exists($path)` | `isFileMissing($path)` | Positive name; no `!` to overlook |
| `!is_dir($path)` | `isDirMissing($path)` | Self-documenting intent |
| `!class_exists('X')` | `isClassMissing('X')` | Centralized, testable |
| `!function_exists('f')` | `isFuncMissing('f')` | Same principle |
| `!extension_loaded('e')` | `isExtensionMissing('e')` | Same principle |
| `!$obj->isActive()` | `$obj->isDisabled()` | Semantic inverse on object |
| `!arr.includes(x)` | `isMissing(arr, x)` | Named guard |
| `!strings.Contains(s, x)` | `IsMissing(s, x)` | Named guard |

### Key: Every negative check becomes a **positively named function**

The function name must express the **positive assertion** of what is being checked:
- "is missing" not "is not existing"
- "is disabled" not "is not active"  
- "is empty" not "is not filled"
- "is disconnected" not "is not connected"

---

## Language-Specific Examples

### PHP (snake_case methods)

```php
// ❌ FORBIDDEN: Raw negation on function call
if (!file_exists($path)) {
    return false;
}

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

if (!class_exists('PDO')) {
    throw new \RuntimeException('PDO not available');
}

// ✅ REQUIRED: Positive guard function from RiseupPathUtils / RiseupBooleanHelpers
if (RiseupBooleanHelpers::is_file_missing($path)) {
    return false;
}

if (RiseupBooleanHelpers::is_dir_missing($dir)) {
    mkdir($dir, 0755, true);
}

if (RiseupBooleanHelpers::is_class_missing('PDO')) {
    throw new \RuntimeException('PDO not available');
}
```

**Utility class:** `RiseupBooleanHelpers` (in `includes/Helpers/`)

| Guard Method | Replaces |
|-------------|----------|
| `is_file_missing($path)` | `!file_exists($path)` |
| `is_file_exists($path)` | `file_exists($path)` (with null guard) |
| `is_dir_missing($path)` | `!is_dir($path)` |
| `is_dir_exists($path)` | `is_dir($path)` (with null guard) |
| `is_dir_writable($path)` | `is_dir($path) && is_writable($path)` |
| `is_dir_readonly($path)` | `!is_dir($path) \|\| !is_writable($path)` |
| `is_class_missing($name)` | `!class_exists($name)` |
| `is_class_exists($name)` | `class_exists($name)` |
| `is_func_missing($name)` | `!function_exists($name)` |
| `is_func_exists($name)` | `function_exists($name)` |
| `is_extension_missing($name)` | `!extension_loaded($name)` |
| `is_extension_loaded($name)` | `extension_loaded($name)` |
| `is_db_connected($db)` | `$db !== null && $db->is_connected()` |
| `is_db_disconnected($db)` | `$db === null \|\| !$db->is_connected()` |

### TypeScript (camelCase functions)

```typescript
// ❌ FORBIDDEN: Raw negation
if (!fs.existsSync(path)) {
    throw new Error('File not found');
}

if (!response.ok) {
    handleError(response);
}

if (!array.includes(item)) {
    array.push(item);
}

// ✅ REQUIRED: Positive guard function
if (isFileMissing(path)) {
    throw new Error('File not found');
}

if (isResponseFailed(response)) {
    handleError(response);
}

if (isItemMissing(array, item)) {
    array.push(item);
}
```

**Utility location:** `src/utils/guards.ts` or domain-specific guard files

| Guard Function | Replaces |
|---------------|----------|
| `isFileMissing(path)` | `!fs.existsSync(path)` |
| `isFileExists(path)` | `fs.existsSync(path)` |
| `isResponseFailed(res)` | `!res.ok` |
| `isResponseSuccess(res)` | `res.ok` |
| `isArrayEmpty(arr)` | `!arr.length` or `arr.length === 0` |
| `hasItems(arr)` | `arr.length > 0` |
| `isNullish(val)` | `val == null` |
| `isPresent(val)` | `val != null` |
| `isStringEmpty(str)` | `!str` or `str === ''` |
| `hasContent(str)` | `!!str` or `str.length > 0` |

### Go (PascalCase exported functions)

```go
// ❌ FORBIDDEN: Raw negation
if !fileExists(path) {
    return fmt.Errorf("file not found: %s", path)
}

if !strings.Contains(s, substr) {
    return apperror.New("E4010", "missing required field")
}

// ✅ REQUIRED: Positive guard function
if IsFileMissing(path) {
    return apperror.New("E4010", "file not found: "+path)
}

if IsMissingSubstring(s, substr) {
    return apperror.New("E4010", "missing required field")
}
```

**Utility package:** `pkg/guards/` or `internal/guards/`

| Guard Function | Replaces |
|---------------|----------|
| `IsFileMissing(path)` | `!fileExists(path)` |
| `IsFileExists(path)` | `fileExists(path)` |
| `IsDirMissing(path)` | `!dirExists(path)` |
| `IsDirExists(path)` | `dirExists(path)` |
| `IsStringEmpty(s)` | `s == ""` or `len(s) == 0` |
| `HasContent(s)` | `s != ""` or `len(s) > 0` |
| `IsSliceEmpty(s)` | `len(s) == 0` |
| `HasItems(s)` | `len(s) > 0` |
| `IsMissingSubstring(s, sub)` | `!strings.Contains(s, sub)` |
| `ContainsSubstring(s, sub)` | `strings.Contains(s, sub)` |

---

## Object-Level Semantic Inverses

Every boolean method on an object **must have a semantic inverse** — never negate a method call with `!`.

```php
// ❌ FORBIDDEN
if (!$plugin->is_active()) { ... }
if (!$user->has_permission('admin')) { ... }

// ✅ REQUIRED
if ($plugin->is_disabled()) { ... }
if ($user->lacks_permission('admin')) { ... }
```

```typescript
// ❌ FORBIDDEN
if (!plugin.isActive()) { ... }
if (!user.hasPermission('admin')) { ... }

// ✅ REQUIRED
if (plugin.isDisabled()) { ... }
if (user.lacksPermission('admin')) { ... }
```

```go
// ❌ FORBIDDEN
if !plugin.IsActive() { ... }
if !user.HasPermission("admin") { ... }

// ✅ REQUIRED
if plugin.IsDisabled() { ... }
if user.LacksPermission("admin") { ... }
```

---

## When Raw `!` Is Still Acceptable

Raw negation is **only** acceptable for:

1. **Simple boolean variable checks** where the variable is already a positively named `is_*`/`has_*` boolean:
   ```php
   if (!$isInitialized) { ... }  // ✅ OK — variable is already semantic
   ```

2. **Logical operators in extracted named booleans** (inside the variable/method definition, not at the call site):
   ```php
   $isInvalid = !$isValid && !$hasOverride;  // ✅ OK — inside named boolean
   if ($isInvalid) { ... }                    // ✅ Call site is clean
   ```

3. **Native type coercion** where no function exists:
   ```php
   if (!$value) { ... }  // ✅ OK — simple falsy check on primitive
   ```

---

## Checklist Summary (Copy for PRs)

```
[ ] No `!file_exists()` — use `is_file_missing()` / `isFileMissing()` / `IsFileMissing()`
[ ] No `!is_dir()` — use `is_dir_missing()` / `isDirMissing()` / `IsDirMissing()`
[ ] No `!class_exists()` — use `is_class_missing()` / guard function
[ ] No `!function_exists()` — use `is_func_missing()` / guard function
[ ] No `!extension_loaded()` — use `is_extension_missing()` / guard function
[ ] No `!$obj->is_active()` — use `$obj->is_disabled()` / semantic inverse
[ ] No `!array.includes()` — use `isItemMissing()` / guard function
[ ] No `!strings.Contains()` — use `IsMissingSubstring()` / guard function
[ ] Guard functions live in dedicated utility classes/packages
[ ] Every boolean method on objects has a semantic inverse
```

---

## Cross-References

- [PHP Boolean Logic](../04-php-standards/README.md#boolean-logic) — PHP-specific helpers
- [PHP Forbidden Patterns](../04-php-standards/forbidden-patterns.md) — Pattern 4.x
- [Cross-Language Code Style](./code-style.md) — Braces, nesting, spacing
- [TypeScript Standards](../02-typescript-standards/README.md)
- [Golang Standards](../03-golang-standards/README.md)

---

*No-negatives specification v1.0.0 — 2026-02-13*
