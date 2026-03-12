# Memory: architecture/php/naming-conventions
Updated: 2026-02-23

PHP naming follows a strict pattern: Classes/Interfaces/Traits use PascalCase; methods and variables use camelCase (e.g., $isActive, processUpload()), intentionally overriding WordPress snake_case for internal consistency. This camelCase convention applies to all REST route callback strings and WordPress hook references (e.g., array($this, 'registerRoutes')). However, WordPress-registered identifiers like hook names, settings groups, and AJAX action slugs remain snake_case to match core API contracts.

## Migration Status

Batches A–E are **complete** as of 2026-02-23. All instance/static properties, local variables, method parameters, and internal array keys across the entire `includes/` directory have been converted to camelCase. A codebase-wide audit confirmed zero remaining actionable snake_case identifiers.

## Confirmed Exemptions

The following categories are **exempt** from camelCase and must retain their original casing:

- **WordPress globals:** `$wp_reset`, `$wpdb`, and other core globals remain snake_case; must be converted to camelCase immediately upon assignment to a local/property (e.g., `$this->wpReset = $wp_reset`).
- **WordPress core hooks/filters:** Hook names registered with `add_action`/`add_filter` (e.g., `'admin_notices'`, `'wp_mail'`).
- **WP-Cron argument keys:** Keys like `'snapshot_type'`, `'master_snapshot_id'` passed through `wp_schedule_single_event`.
- **wp_options settings keys:** Internal settings storage keys (e.g., `'retention_type'`).
- **WordPress transients:** Core transient names.
- **PHP superglobals:** `$_GET`, `$_POST`, `$_SERVER`, etc.
- **HTML form `name` attributes** and **URL query parameters.**
- **JSON manifest keys:** External-facing keys (e.g., `'format_version'`).
- **SQLite internal metadata keys.**
- **Autoloader.php** and **PHPDoc `@package`/`@since` headers:** Exempt as they must remain self-contained or serve static documentation.

## Boolean Property Naming

All boolean properties must use 'is', 'has', or 'should' prefixes (e.g., `$isInitialized`, `$hasLoaded`, `$isActive`). Raw boolean names like `$initialized` or `$loaded` are prohibited.

### No Mixed Polarity in Boolean Assignments

Never combine a negated variable with a positive condition (or vice versa) in the same assignment. Build from positive primitives, then derive negatives only at the final decision point.

```php
// ❌ WRONG — mixed polarity: negated bool guards a positive check
$isPageMissing = !isset($_GET['page']);
$isOtherPage = !$isPageMissing && strpos($_GET['page'], 'plugins-onboard') === false;
$isNotOnboardPage = $isPageMissing || $isOtherPage;

// ❌ WRONG — negated variable in compound condition
$isCredentialsMissing = ($credentials === false);
$isColonMissing = (!$isCredentialsMissing && strpos($credentials, ':') === false);

// ✅ CORRECT — all positive primitives, negate only at decision
$isPageDefined = isset($_GET['page']);
$isOnboardPage = $isPageDefined && strpos($_GET['page'], 'plugins-onboard') !== false;
$isDifferentPage = !$isOnboardPage;

// ✅ CORRECT — positive primitives
$isCredentialsDecoded = ($credentials !== false);
$isColonPresent = $isCredentialsDecoded && strpos($credentials, ':') !== false;
$isFormatValid = $isCredentialsDecoded && $isColonPresent;
```

### Avoid Redundant Negation Variables

Don't create a `$isLiveRun = !$isDryRun` variable. Instead, use the original variable directly at the decision point:

```php
// ❌ WRONG — redundant negation variable
$isLiveRun = !$isDryRun;
$isLiveRunWithDeletions = $isLiveRun && $hasDeletions;

// ✅ CORRECT — use original directly
$shouldAudit = !$isDryRun && $hasDeletions;
```

## Property & Parameter Naming

All properties and parameters must use camelCase — no underscores. Examples:
- `$fileLogger` not `$file_logger`
- `$dedupHashes` not `$dedup_hashes`
- `$baseDir` not `$base_dir`
- `$stackTrace` not `$stack_trace`
- `$pluginSlug` not `$plugin_slug`

## Singleton Pattern

Singleton accessors use `getInstance()` not `get_instance()`. The method body has a blank line before the final return:

```php
public static function getInstance() {
    if (self::$instance === null) {
        self::$instance = new self();
    }

    return self::$instance;
}
```

## Enum Encapsulation

Native PHP 8.1 backed enums should encapsulate related helper/check methods within the enum body itself (e.g., `LogLevelType` contains `isError()`, `isWarn()`, `isErrorOrWarn()`). This keeps type-checking logic co-located with the enum definition.

## Cross-References
- **Persistence naming exemptions:** `.lovable/memory/architecture/coding-standards/persistence-naming-exemptions.md`
- **Plugin identity standard:** `.lovable/memory/architecture/php/plugin-identity-standard.md`
- **Enum standards:** `spec/06-php-standards/enums.md`
- **Global type syntax:** `.lovable/memory/architecture/php/global-type-syntax-standard.md`
