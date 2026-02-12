# PHP Naming Conventions

> **Version:** 1.0.0  
> **Updated:** 2026-02-12  
> **Baseline:** PSR-12 / PSR-1  
> **Applies to:** All PHP code unless overridden by project-specific or framework-specific conventions

---

> **Override Rule:** These are the default conventions for PHP. Any language-specific, framework-specific, or project-specific convention file **takes precedence** when it conflicts with this baseline. For example, a WordPress plugin project may mandate `snake_case` methods — that override wins.

---

## Classes, Interfaces, Traits, Enums

Use **PascalCase**.

```php
class UploadManager {}
interface CacheDriver {}
trait HasTimestamps {}
enum UploadSource {}
```

**Rules:**

- One class per file
- File name matches class name
- Names should be nouns
- Avoid abbreviations unless universal (`HTTP`, `API`, `CLI`)

---

## Methods and Functions

Use **camelCase**.

```php
function processUpload() {}
function getRetryCount() {}
```

**Rules:**

- Verb or verb phrase
- Describe behavior, not implementation
- Avoid prefixes like `do`, `handle`, `run`

```php
// ✅ Good
calculateChecksum()

// ❌ Bad
doChecksumThing()
```

---

## Variables

Use **camelCase**.

```php
$maxRetries = 3;
$uploadSource = UploadSource::Script;
```

**Rules:**

- Clear intent over short names
- Avoid Hungarian notation
- Avoid `snake_case` for variables in modern PHP
- Boolean variables must use `$is_*` or `$has_*` prefix

---

## Constants

Use **UPPER_SNAKE_CASE**.

```php
const MAX_RETRIES = 3;
const DEFAULT_TIMEOUT = 30;
```

For class constants:

```php
class Limits
{
    public const MAX_RETRIES = 3;
}
```

Enums are the exception: enum cases follow **PascalCase**, not uppercase.

```php
UploadSource::RestApi
```

---

## Enum Cases

Use **PascalCase**.

```php
enum UploadSource: string
{
    case Script;
    case RestApi;
    case AdminUi;
    case WpCli;
}
```

**Rules:**

- Match domain naming
- Avoid screaming uppercase
- Treat them like class names

---

## Namespaces

Use **PascalCase**, structured by domain.

```php
namespace App\Domain\Upload;
namespace App\Infrastructure\Http;
```

**Rules:**

- Reflect architecture, not folders alone
- Avoid generic buckets like `Utils` or `Helpers`

---

## Files

Match class or enum name exactly.

```
UploadSource.php
UploadManager.php
CacheDriver.php
```

Autoloaders rely on this.

---

## Summary Table

| Element              | Convention       |
|----------------------|------------------|
| Class / Enum / Interface | PascalCase   |
| Method / Function    | camelCase        |
| Variable             | camelCase        |
| Constant             | UPPER_SNAKE_CASE |
| Enum case            | PascalCase       |
| Namespace            | PascalCase       |
| File name            | PascalCase       |

---

> **Reminder:** This is the PSR-12 baseline. Project-level specs (e.g., WordPress plugin conventions in this same folder's [README.md](./README.md)) may override specific rules — those overrides take precedence.
