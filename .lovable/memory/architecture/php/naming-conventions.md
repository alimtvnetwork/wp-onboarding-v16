# Memory: architecture/php/naming-conventions
Updated: 2026-02-13

PHP naming follows a strict pattern: Classes/Interfaces/Traits use PascalCase; methods and variables use camelCase (e.g., $isActive, processUpload()), intentionally overriding WordPress snake_case for internal consistency. This camelCase convention applies to all REST route callback strings and WordPress hook references (e.g., array($this, 'registerRoutes')). However, WordPress-registered identifiers like hook names, settings groups, and AJAX action slugs remain snake_case to match core API contracts.

## Boolean Property Naming

All boolean properties must use 'is' or 'has' prefixes (e.g., `$isInitialized`, `$hasLoaded`, `$isActive`). Raw boolean names like `$initialized` or `$loaded` are prohibited.

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
