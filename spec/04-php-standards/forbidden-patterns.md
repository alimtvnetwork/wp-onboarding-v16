# PHP Forbidden Patterns — Quick Reference Checklist

> **Version:** 3.0.0  
> **Updated:** 2026-02-14  
> **Consolidates:** [README.md](./README.md), [enums.md](./enums.md), [WP Error Handling](../07-wordpress-plugin-development/07-error-handling.md)

---

## How to Use

Every pattern below is **forbidden** in production code. The ✅ column shows the required replacement. Use this as a pre-commit or code-review checklist.

---

## 1. Error Handling

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 1.1 | `catch (Exception $e)` | `catch (Throwable $e)` | Misses PHP 7+ `Error`, `TypeError`, `ParseError` |
| 1.2 | `$error && in_array($error['type'], [E_ERROR, ...])` | `ErrorChecker::isFatalError($error)` | Duplicated logic; central list in `ErrorType::FATAL_TYPES` |
| 1.3 | Inline `E_*` → string mapping arrays | `ErrorChecker::getTypeLabel($type)` | Uses `ErrorType::TYPE_LABELS`; one place to update |
| 1.4 | `wp_die()` in REST handlers | `wp_send_json_error()` or `$this->envelope->error()` | `wp_die()` breaks JSON response format |
| 1.5 | `error_log()` for diagnostics | `RiseupLogger` / `$this->fileLogger` | No structure, no stack trace, no audit trail |
| 1.6 | `!class_exists('PDO') \|\| !extension_loaded(...)` inline | `ErrorChecker::isInvalidPdoExtension()` | Centralized; self-documenting |
| 1.7 | Unchecked `new PDO()` without any guard | `ErrorChecker::isInvalidPdoExtension()` check first | Fatal error if extension missing |
| 1.7 | REST handler without `safeExecute` wrapper | Wrap in `$this->safeExecute(fn() => ...)` | Unhandled exceptions crash the endpoint |

---

## 2. Magic Strings — Hooks

| # | ❌ Forbidden | ✅ Required | Source |
|---|-------------|------------|--------|
| 2.1 | `add_action('init', ...)` | `add_action(HookType::Init->value, ...)` | `HookType::Init` |
| 2.2 | `add_action('plugins_loaded', ...)` | `add_action(HookType::PluginsLoaded->value, ...)` | `HookType::PluginsLoaded` |
| 2.3 | `add_action('rest_api_init', ...)` | `add_action(HookType::RestApiInit->value, ...)` | `HookType::RestApiInit` |
| 2.4 | `add_action('admin_init', ...)` | `add_action(HookType::AdminInit->value, ...)` | `HookType::AdminInit` |
| 2.5 | `add_action('admin_menu', ...)` | `add_action(HookType::AdminMenu->value, ...)` | `HookType::AdminMenu` |
| 2.6 | `add_action('admin_notices', ...)` | `add_action(HookType::AdminNotices->value, ...)` | `HookType::AdminNotices` |
| 2.7 | `add_action('admin_enqueue_scripts', ...)` | `add_action(HookType::AdminEnqueue->value, ...)` | `HookType::AdminEnqueue` |
| 2.8 | `add_action('activated_plugin', ...)` | `add_action(HookType::ActivatedPlugin->value, ...)` | `HookType::ActivatedPlugin` |
| 2.9 | `add_action('deactivated_plugin', ...)` | `add_action(HookType::DeactivatedPlugin->value, ...)` | `HookType::DeactivatedPlugin` |
| 2.10 | `add_action('deleted_plugin', ...)` | `add_action(HookType::DeletedPlugin->value, ...)` | `HookType::DeletedPlugin` |
| 2.11 | `add_filter('rest_post_dispatch', ...)` | `add_filter(HookType::RestPostDispatch->value, ...)` | `HookType::RestPostDispatch` |
| 2.12 | `add_filter('cron_schedules', ...)` | `add_filter(HookType::CronSchedules->value, ...)` | `HookType::CronSchedules` |
| 2.13 | `add_action('wp_ajax_my_action', ...)` | `define('HOOK_AJAX_MY_ACTION', HookType::ajax(ACTION_MY_ACTION));` then `add_action(HOOK_AJAX_MY_ACTION, ...)` | Named composed constant |
| 2.14 | `add_action(HookType::ajax(ACTION_X), ...)` inline | Compose a named constant first, then use it | No inline concatenation at call site |
| 2.15 | `rest_url(REST_NAMESPACE . '/' . ACTION_X)` | `define('REST_URL_X', REST_NAMESPACE . '/' . ACTION_X);` then `rest_url(REST_URL_X)` | No inline concatenation at call site |
| 2.16 | `current_user_can('manage_options')` | `current_user_can(CapabilityType::ManageOptions->value)` | `CapabilityType` enum |
| 2.17 | `'POST'` or `WP_REST_Server::CREATABLE` in routes | `HttpMethodType::Post->value` | `HttpMethodType` enum |

---

## 3. Magic Strings — File Paths

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 3.1 | `WP_CONTENT_DIR . '/uploads/.../file.db'` | `PathHelper::getRootDb()` | Manual concatenation; magic string |
| 3.2 | `PathHelper::getDataDir() . '/file.db'` | `PathHelper::getRootDb()` | Partial accessor; magic filename at call site |
| 3.3 | `PathHelper::getDataDir() . PathDatabaseType::Root->value` | `PathHelper::getRootDb()` | Leaks internal composition to caller |
| 3.4 | Any path without a typed accessor | Create accessor in `PathHelper` first | Every path must have a single-call accessor |

---

## 4. Boolean Logic & No Raw Negations

> **Canonical source:** [No Raw Negations](../01-coding-guidelines/no-negatives.md)

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 4.1 | `RiseupBooleanHelpers::isFalsy(...)` | `$plugin->isDisabled()` | Generic helper obscures intent |
| 4.2 | `RiseupBooleanHelpers::isTruthy(...)` | `$isValue` | Unnecessary indirection |
| 4.3 | `!$plugin->isActive()` | `$plugin->isDisabled()` | Negation is easy to miss; use semantic inverse |
| 4.4 | `$value` for boolean variables | `$isValue`, `$hasPermission` | Ambiguous naming; must use `$is*` / `$has*` prefix |
| 4.5 | `!file_exists($path)` | `PathHelper::isFileMissing($path)` | Raw negation; use positive guard |
| 4.6 | `!is_dir($path)` | `PathHelper::isDirMissing($path)` | Raw negation; use positive guard |
| 4.7 | `!class_exists('X')` | `BooleanHelpers::isClassMissing('X')` | Raw negation; use positive guard |
| 4.8 | `!function_exists('f')` | `BooleanHelpers::isFuncMissing('f')` | Raw negation; use positive guard |
| 4.9 | `!extension_loaded('e')` | `BooleanHelpers::isExtensionMissing('e')` | Raw negation; use positive guard |

---

## 5. Initialization & Architecture

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 5.1 | WordPress calls in `__construct()` | Lazy `initialize()` method with guard | Load order issues; WP may not be ready |
| 5.2 | Raw `require_once` for non-foundation files | `OnboardIncludeFiles` loader utility | Loader logs failures with stack trace |
| 5.3 | `define()` for categorized constants | Native backed enum (`HookType`, `CapabilityType`, `HttpMethodType`, etc.) | Enums group related constants with PHPDoc |

> **Exception for 5.3:** Plugin-specific custom hooks (e.g., `CRON_SNAPSHOT_*`) may use `define()` in `constants.php` when they are plugin-scoped and not WordPress core hooks.

---

## 6. Condition Complexity & Function Size (All Languages)

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 6.1 | Inline `if` with 2+ operators (`&&`, `\|\|`, `!`) | Extract to named `$is*`/`$has*` variable or method | Reads as intent, not implementation |
| 6.2 | `$error && in_array($error['type'], [...])` | `ErrorChecker::isFatalError($error)` | Reusable, self-documenting |
| 6.3 | `!class_exists('PDO') \|\| !extension_loaded(...)` | `ErrorChecker::isInvalidPdoExtension()` | Centralized check |
| 6.4 | Nested `if` (any depth) | **Zero tolerance** — flatten with early returns or combined conditions | Absolute ban |
| 6.5 | Functions > 15 lines | Extract helpers; each function does one thing | Max 15 lines per function body |

---

## 7. Error Type Constants

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 7.1 | `[E_ERROR, E_PARSE, E_CORE_ERROR, ...]` inline | `ErrorType::FATAL_TYPES` | Centralized; update one place for new PHP versions |
| 7.2 | `[E_WARNING, E_NOTICE, ...]` inline | `ErrorType::WARNING_TYPES` | Same principle |
| 7.3 | Custom `errorTypeToString()` functions | `ErrorChecker::getTypeLabel($type)` | Uses `ErrorType::TYPE_LABELS` map |

---

## Checklist Summary (Copy for PRs)

```
[ ] No `catch (Exception $e)` — all use `catch (Throwable $e)`
[ ] No inline `in_array($error['type'], [...])` — use `ErrorChecker`
[ ] No inline E_* → string maps — use `ErrorChecker::getTypeLabel()`
[ ] No `wp_die()` in REST handlers
[ ] No `error_log()` — use structured logger
[ ] No string literals in add_action/add_filter — use `HookType::*->value`
[ ] No inline concatenation at call sites — compose named constants first
[ ] No manual path concatenation — use `PathHelper` accessors
[ ] No `BooleanHelpers` trivial wrappers — use semantic methods
[ ] No `!$obj->isActive()` — use `$obj->isDisabled()`
[ ] No `!file_exists()` — use `PathHelper::isFileMissing()`
[ ] No `!is_dir()` — use `PathHelper::isDirMissing()`
[ ] No `!class_exists()` — use `BooleanHelpers::isClassMissing()`
[ ] No `!function_exists()` — use `BooleanHelpers::isFuncMissing()`
[ ] No `!extension_loaded()` — use `BooleanHelpers::isExtensionMissing()`
[ ] No raw `!` on any function call — use positive guard function
[ ] No boolean vars without `$is*` / `$has*` prefix
[ ] No WordPress calls in constructors
[ ] No inline `!class_exists('PDO')` — use `ErrorChecker::isInvalidPdoExtension()`
[ ] Blank line before `return` or `throw` when preceded by other statements
[ ] No single-line `if (...) return;` — always use braces
[ ] Blank line after closing `}` when followed by more code
[ ] No nested `if` — ZERO TOLERANCE — absolute ban
[ ] No inline multi-part `if` (2+ operators) — extract to `$is*` variable or method
[ ] Functions max 15 lines — extract helpers for longer logic
[ ] No leading backslash on `Throwable` — use `catch (Throwable $e)`
[ ] Functions with >2 params — one param per line with trailing comma
```

---

## Cross-References

- [PHP Coding Standards](./README.md) — Full spec with examples
- [PHP Enum Classes](./enums.md) — `HookType`, `CapabilityType`, `HttpMethodType`, Path enums, `ErrorType`, `ErrorChecker`
- [Cross-Language Code Style](../01-coding-guidelines/code-style.md) — Rules 1-9 (braces, nesting, spacing, function size, Throwable, multi-line params)
- [WordPress Error Handling](../07-wordpress-plugin-development/07-error-handling.md) — Complete error handling patterns
- [WordPress Initialization](../07-wordpress-plugin-development/01-initialization-patterns.md) — Bootstrap patterns
- [WordPress API Design](../07-wordpress-plugin-development/04-api-design.md) — REST endpoint patterns

---

*Forbidden patterns checklist v3.0.0 — 2026-02-14*
