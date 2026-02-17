# Strict Typing — Cross-Language Type Declaration Rules

> **Version:** 1.0.0  
> **Updated:** 2026-02-14  
> **Applies to:** PHP, TypeScript, Go

---

## Rule

Every function parameter, return value, and class property **must** have an explicit type declaration wherever the language supports it. Untyped signatures are forbidden in new code.

---

## PHP (7.4+ / 8.0+)

### Required Type Locations

| Location | Required | Since |
|----------|----------|-------|
| Function parameters | ✅ | PHP 7.0 |
| Return types | ✅ | PHP 7.0 |
| Class properties | ✅ | PHP 7.4 |
| Constructor promotion | ✅ (preferred) | PHP 8.0 |
| Nullable types (`?Type`) | ✅ | PHP 7.1 |
| Union types (`int\|string`) | ✅ | PHP 8.0 |

### Examples

```php
// ❌ FORBIDDEN: Untyped parameters and return
public function logException($e, $context = '') { ... }

// ✅ REQUIRED: Fully typed
public function logException(Throwable $e, string $context = ''): bool { ... }
```

```php
// ❌ FORBIDDEN: Untyped class property
class Manager {
    private $logger;
    private $initialized;
}

// ✅ REQUIRED: Typed properties
class Manager {
    private RiseupFileLogger $logger;
    private bool $isInitialized;
}
```

```php
// ✅ Constructor promotion (PHP 8.0+)
class User {
    public function __construct(
        public string $name,
        public int $age
    ) {}
}
```

### Limitation

PHP does not support type declarations on local variables. Types apply to parameters, returns, properties, and constants only.

---

## TypeScript

Already enforced by the generics-first rule and `strict: true` in tsconfig. Key reinforcements:

- `any` is **prohibited** everywhere (see [TypeScript Standards](../02-typescript-standards/readme.md))
- `unknown` only at parse boundaries with immediate narrowing
- All function signatures must have explicit parameter and return types

---

## Go

Already statically typed. Key reinforcements:

- `interface{}` / `any` is **prohibited** in exported APIs (see [Go Standards](../03-golang-standards/readme.md))
- Use concrete types or constrained generics (`[T any]` in generic signatures is acceptable)
- All struct fields must use concrete types, not `map[string]interface{}`

---

## Docblock Rules

### Rule: Remove redundant `@param`/`@return` when types are declared

When the function signature already declares types, docblock `@param` and `@return` annotations that merely repeat the type are **redundant** and must be removed.

### When to Keep Docblocks

| Condition | Keep docblock? |
|-----------|---------------|
| Function body > 10 lines | ✅ Keep a summary comment |
| Complex behavior or side effects | ✅ Describe semantics |
| Non-obvious constraints (e.g., "must be positive") | ✅ Document constraint |
| Parameter type is already in signature, no extra semantics | ❌ Remove |
| Return type is already in signature, no extra semantics | ❌ Remove |

### Examples

```php
// ❌ FORBIDDEN: Redundant docblock duplicating types
/**
 * Log exception and return error array.
 *
 * @param RiseupFileLogger $logger  File logger instance.
 * @param Throwable        $e       The caught exception.
 * @param string           $context Context message.
 * @return array Error response array.
 */
public static function logAndReturn(RiseupFileLogger $logger, Throwable $e, string $context = ''): array {

// ✅ REQUIRED: Brief summary only (types are in signature)
/** Log exception and return standardized error array. */
public static function logAndReturn(RiseupFileLogger $logger, Throwable $e, string $context = ''): array {
```

```typescript
// ❌ FORBIDDEN: JSDoc duplicating TypeScript types
/**
 * @param message - The error message
 * @returns The formatted string
 */
function formatError(message: string): string { ... }

// ✅ REQUIRED: No redundant JSDoc
function formatError(message: string): string { ... }
```

---

## Parameter Count Rule

### Rule: Maximum 3 parameters per function

Functions must accept **3 or fewer** parameters. When more are needed, group them into a typed object or class.

### Exception

Utility, framework, or infrastructure functions (e.g., static helpers, middleware wrappers) may exceed 3 parameters when each parameter serves a distinct, well-understood role. Always confirm before adding a 4th parameter.

### Examples

```php
// ❌ FORBIDDEN: Too many parameters
public function createPost(string $title, string $content, string $status, int $authorId, array $meta): int { ... }

// ✅ REQUIRED: Grouped into a typed object
public function createPost(CreatePostParams $params): int { ... }
```

```typescript
// ❌ FORBIDDEN
function createUser(name: string, email: string, role: string, department: string): User { ... }

// ✅ REQUIRED
interface CreateUserParams {
  name: string;
  email: string;
  role: string;
  department: string;
}
function createUser(params: CreateUserParams): User { ... }
```

```go
// ❌ FORBIDDEN
func CreateUser(name string, email string, role string, dept string) (User, error) { ... }

// ✅ REQUIRED
type CreateUserParams struct {
    Name  string
    Email string
    Role  string
    Dept  string
}
func CreateUser(params CreateUserParams) (User, error) { ... }
```

---

## Cross-References

- [PHP Standards](../04-php-standards/readme.md)
- [TypeScript Standards](../02-typescript-standards/readme.md)
- [Go Standards](../03-golang-standards/readme.md)
- [Function Naming](./function-naming.md)
- [Generic Enforce](../12-generic-enforce/readme.md)

---

*Strict typing specification v1.0.0 — 2026-02-14*
