# Memory: architecture/coding-standards/php-spacing-and-imports
Updated: 2026-02-26

## Blank Line Rules (PHP-Specific)

Three critical spacing rules documented in `spec/06-php-standards/php-spacing-and-imports.md`:

1. **Blank line before `if`** when preceded by one or more statements (assignments, calls). NO blank line when `if` is the first statement in a function or immediately follows a closing `}`.

2. **Blank line before `throw`** when preceded by other statements in the same block. Same rule as `return`. No blank line if `throw` is the sole statement.

3. **No leading backslash** on global types (`\RuntimeException`, `\Throwable`, `\PDO`, `\WP_Query`). Always add `use RuntimeException;` etc. at the file top. Exemptions: Autoloader.php and main plugin bootstrap file.

## Log Context Key Rule

- **One-off** log context keys: raw camelCase strings are OK.
- **Reusable** keys (3+ occurrences across different files): must use `ResponseKeyType` enum case or named constant.

## Cross-References
- Canonical spec: `spec/06-php-standards/php-spacing-and-imports.md`
- Code style rules 4, 8, 10: `spec/03-coding-guidelines/code-style.md`
