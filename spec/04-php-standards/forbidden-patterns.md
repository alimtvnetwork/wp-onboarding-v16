# PHP Forbidden Patterns — Quick Reference Checklist

> **Version:** 1.0.0  
> **Updated:** 2026-02-12  
> **Consolidates:** [README.md](./README.md), [enums.md](./enums.md), [WP Error Handling](../07-wordpress-plugin-development/07-error-handling.md)

---

## How to Use

Every pattern below is **forbidden** in production code. The ✅ column shows the required replacement. Use this as a pre-commit or code-review checklist.

---

## 1. Error Handling

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 1.1 | `catch (Exception $e)` | `catch (\Throwable $e)` | Misses PHP 7+ `Error`, `TypeError`, `ParseError` |
| 1.2 | `$error && in_array($error['type'], [E_ERROR, ...])` | `ErrorChecker::is_fatal_error($error)` | Duplicated logic; central list in `ErrorTypeEnum::FATAL_TYPES` |
| 1.3 | Inline `E_*` → string mapping arrays | `ErrorChecker::get_type_label($type)` | Uses `ErrorTypeEnum::TYPE_LABELS`; one place to update |
| 1.4 | `wp_die()` in REST handlers | `wp_send_json_error()` or `$this->envelope->error()` | `wp_die()` breaks JSON response format |
| 1.5 | `error_log()` for diagnostics | `RiseupLogger` / `$this->file_logger` | No structure, no stack trace, no audit trail |
| 1.6 | `!class_exists('PDO') \|\| !extension_loaded(...)` inline | `ErrorChecker::is_invalid_pdo_extension()` | Centralized; self-documenting |
| 1.7 | Unchecked `new PDO()` without any guard | `ErrorChecker::is_invalid_pdo_extension()` check first | Fatal error if extension missing |
| 1.7 | REST handler without `safe_execute` wrapper | Wrap in `$this->safe_execute(fn() => ...)` | Unhandled exceptions crash the endpoint |

---

## 2. Magic Strings — Hooks

| # | ❌ Forbidden | ✅ Required | Source |
|---|-------------|------------|--------|
| 2.1 | `add_action('init', ...)` | `add_action(HookEnum::INIT, ...)` | `HookEnum::INIT` |
| 2.2 | `add_action('plugins_loaded', ...)` | `add_action(HookEnum::PLUGINS_LOADED, ...)` | `HookEnum::PLUGINS_LOADED` |
| 2.3 | `add_action('rest_api_init', ...)` | `add_action(HookEnum::REST_API_INIT, ...)` | `HookEnum::REST_API_INIT` |
| 2.4 | `add_action('admin_init', ...)` | `add_action(HookEnum::ADMIN_INIT, ...)` | `HookEnum::ADMIN_INIT` |
| 2.5 | `add_action('admin_menu', ...)` | `add_action(HookEnum::ADMIN_MENU, ...)` | `HookEnum::ADMIN_MENU` |
| 2.6 | `add_action('admin_notices', ...)` | `add_action(HookEnum::ADMIN_NOTICES, ...)` | `HookEnum::ADMIN_NOTICES` |
| 2.7 | `add_action('admin_enqueue_scripts', ...)` | `add_action(HookEnum::ADMIN_ENQUEUE, ...)` | `HookEnum::ADMIN_ENQUEUE` |
| 2.8 | `add_action('activated_plugin', ...)` | `add_action(HookEnum::ACTIVATED_PLUGIN, ...)` | `HookEnum::ACTIVATED_PLUGIN` |
| 2.9 | `add_action('deactivated_plugin', ...)` | `add_action(HookEnum::DEACTIVATED_PLUGIN, ...)` | `HookEnum::DEACTIVATED_PLUGIN` |
| 2.10 | `add_action('deleted_plugin', ...)` | `add_action(HookEnum::DELETED_PLUGIN, ...)` | `HookEnum::DELETED_PLUGIN` |
| 2.11 | `add_filter('rest_post_dispatch', ...)` | `add_filter(HookEnum::REST_POST_DISPATCH, ...)` | `HookEnum::REST_POST_DISPATCH` |
| 2.12 | `add_filter('cron_schedules', ...)` | `add_filter(HookEnum::CRON_SCHEDULES, ...)` | `HookEnum::CRON_SCHEDULES` |
| 2.13 | `add_action('wp_ajax_my_action', ...)` | `define('HOOK_AJAX_MY_ACTION', HookEnum::WP_AJAX_PREFIX . ACTION_MY_ACTION);` then `add_action(HOOK_AJAX_MY_ACTION, ...)` | Named composed constant |
| 2.14 | `add_action(HookEnum::WP_AJAX_PREFIX . ACTION_X, ...)` | Compose a named constant first, then use it | No inline concatenation at call site |
| 2.15 | `rest_url(REST_NAMESPACE . '/' . ACTION_X)` | `define('REST_URL_X', REST_NAMESPACE . '/' . ACTION_X);` then `rest_url(REST_URL_X)` | No inline concatenation at call site |
| 2.16 | `current_user_can('manage_options')` | `current_user_can(CapabilityEnum::MANAGE_OPTIONS)` | `CapabilityEnum` |
| 2.17 | `'POST'` or `WP_REST_Server::CREATABLE` in routes | `HttpMethodEnum::POST` | `HttpMethodEnum` |

---

## 3. Magic Strings — File Paths

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 3.1 | `WP_CONTENT_DIR . '/uploads/.../file.db'` | `RiseupPathUtils::getRootDb()` | Manual concatenation; magic string |
| 3.2 | `RiseupPathUtils::getDataDir() . '/file.db'` | `RiseupPathUtils::getRootDb()` | Partial accessor; magic filename at call site |
| 3.3 | `RiseupPathUtils::getDataDir() . PathEnum::ROOT_DB` | `RiseupPathUtils::getRootDb()` | Leaks internal composition to caller |
| 3.4 | Any path without a typed accessor | Create accessor in `RiseupPathUtils` first | Every path must have a single-call accessor |

---

## 4. Boolean Logic

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 4.1 | `RiseupBooleanHelpers::isFalsy(...)` | `$plugin->is_disabled()` | Generic helper obscures intent |
| 4.2 | `RiseupBooleanHelpers::isTruthy(...)` | `$is_value` | Unnecessary indirection |
| 4.3 | `!$plugin->is_active()` | `$plugin->is_disabled()` | Negation is easy to miss; use semantic inverse |
| 4.4 | `$value` for boolean variables | `$is_value`, `$has_permission` | Ambiguous naming; must use `$is_*` / `$has_*` prefix |

---

## 5. Initialization & Architecture

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 5.1 | WordPress calls in `__construct()` | Lazy `initialize()` method with guard | Load order issues; WP may not be ready |
| 5.2 | Raw `require_once` for non-foundation files | `OnboardIncludeFiles` loader utility | Loader logs failures with stack trace |
| 5.3 | `define()` for categorized constants | Enum class (`HookEnum`, `PathEnum`, `ErrorTypeEnum`) | Enums group related constants with PHPDoc |

> **Exception for 5.3:** Plugin-specific custom hooks (e.g., `RISEUP_CRON_SNAPSHOT_*`) may use `define()` in `constants.php` when they are plugin-scoped and not WordPress core hooks.

---

## 6. Condition Complexity (All Languages)

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 6.1 | Inline `if` with 2+ operators (`&&`, `\|\|`, `!`) | Extract to named `$is_*`/`$has_*` variable or method | Reads as intent, not implementation |
| 6.2 | `$error && in_array($error['type'], [...])` | `ErrorChecker::is_fatal_error($error)` | Reusable, self-documenting |
| 6.3 | `!class_exists('PDO') \|\| !extension_loaded(...)` | `ErrorChecker::is_invalid_pdo_extension()` | Centralized check |
| 6.4 | Nested `if` when outer check is handled by inner function | Use the function directly (it handles null) | Redundant guard |

---

## 7. Error Type Constants

| # | ❌ Forbidden | ✅ Required | Why |
|---|-------------|------------|-----|
| 7.1 | `[E_ERROR, E_PARSE, E_CORE_ERROR, ...]` inline | `ErrorTypeEnum::FATAL_TYPES` | Centralized; update one place for new PHP versions |
| 7.2 | `[E_WARNING, E_NOTICE, ...]` inline | `ErrorTypeEnum::WARNING_TYPES` | Same principle |
| 7.3 | Custom `error_type_to_string()` functions | `ErrorChecker::get_type_label($type)` | Uses `ErrorTypeEnum::TYPE_LABELS` map |

---

## Checklist Summary (Copy for PRs)

```
[ ] No `catch (Exception $e)` — all use `catch (\Throwable $e)`
[ ] No inline `in_array($error['type'], [...])` — use `ErrorChecker`
[ ] No inline E_* → string maps — use `ErrorChecker::get_type_label()`
[ ] No `wp_die()` in REST handlers
[ ] No `error_log()` — use structured logger
[ ] No string literals in add_action/add_filter — use `HookEnum`
[ ] No inline concatenation at call sites — compose named constants first
[ ] No manual path concatenation — use `RiseupPathUtils` accessors
[ ] No `RiseupBooleanHelpers` — use semantic methods
[ ] No `!$obj->is_active()` — use `$obj->is_disabled()`
[ ] No boolean vars without `$is_*` / `$has_*` prefix
[ ] No WordPress calls in constructors
[ ] No inline `!class_exists('PDO')` — use `ErrorChecker::is_invalid_pdo_extension()`
[ ] Blank line before `return` when preceded by other statements
[ ] No single-line `if (...) return;` — always use braces
[ ] Blank line after closing `}` when followed by more code
[ ] No nested `if` — flatten with combined conditions or early returns
[ ] No inline multi-part `if` (2+ operators) — extract to `$is_*` variable or method
```

---

## Cross-References

- [PHP Coding Standards](./README.md) — Full spec with examples
- [PHP Enum Classes](./enums.md) — `HookEnum`, `PathEnum`, `ErrorTypeEnum`, `ErrorChecker`
- [WordPress Error Handling](../07-wordpress-plugin-development/07-error-handling.md) — Complete error handling patterns
- [WordPress Initialization](../07-wordpress-plugin-development/01-initialization-patterns.md) — Bootstrap patterns
- [WordPress API Design](../07-wordpress-plugin-development/04-api-design.md) — REST endpoint patterns

---

*Forbidden patterns checklist v1.0.0 — 2026-02-12*
