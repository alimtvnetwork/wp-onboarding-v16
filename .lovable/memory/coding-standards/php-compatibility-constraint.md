# Memory: coding-standards/php-compatibility-constraint
Updated: 2026-03-12

## QUpload Remote Syntax Validator Constraints

The QUpload plugin validates uploaded PHP files using `token_get_all($content, TOKEN_PARSE)` before activation. This catches syntax errors pre-deployment but introduces compatibility constraints:

### Known Blocked Patterns

| Pattern | Error | Fix |
|---------|-------|-----|
| `is_array($var)` | `unexpected token "array"` | Use `gettype($var) === PhpNativeType::PhpArray->value` |
| `array()` long syntax | `unexpected token "array"` | Use `[]` short array syntax |
| `= array()` as default | `unexpected token "array"` | Use `= []` |

### Rules for Legacy-Safe Traits

Critical deployment traits (`ManagerRestoreTrait`, `ManagerRestoreValidationTrait`) must:

1. **Use `[]` instead of `array()`** — short array syntax everywhere
2. **Use `PhpNativeType` enum** for `gettype()` comparisons — never raw magic strings like `'array'`
3. **Avoid `is_array()`** — use `gettype($var) === PhpNativeType::PhpArray->value` instead
4. **No parameter/return type hints** — function signatures must be untyped for PHP 7.0 compatibility
5. **No trailing commas** in function parameter lists
6. **No nullable types** (`?string`, `?int`)

### PhpNativeType Enum

Located at `includes/Enums/PhpNativeType.php` — provides typed constants for all `gettype()` return values (`PhpArray`, `PhpString`, `PhpInteger`, `PhpDouble`, `PhpBoolean`, `PhpObject`, `PhpNull`).
