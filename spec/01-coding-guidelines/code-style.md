# Cross-Language Code Style — Braces, Nesting & Spacing

> **Version:** 1.0.0  
> **Updated:** 2026-02-12  
> **Applies to:** PHP, TypeScript, Go

---

## Overview

These five rules govern control-flow formatting across **all languages** in the project. Language-specific specs (PHP, TypeScript, Go) reference this document as the single source of truth.

---

## Rule 1: Always Use Braces — No Single-Line Statements

Every `if`, `for`, `foreach`/`for...of`, `while` block **must** use curly braces `{}`, even for single-statement bodies.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN
if ($this->initialized) return;
if ($error === null) return false;

// ✅ REQUIRED
if ($this->initialized) {
    return;
}

if ($error === null) {
    return false;
}
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN
if (isLoading) return null;

// ✅ REQUIRED
if (isLoading) {
    return null;
}
```

```go
// ── Go ───────────────────────────────────────────────────────
// Go enforces braces by syntax — this rule is already satisfied.
```

---

## Rule 2: No Nested `if` — Flatten with Combined Checks or Early Returns

Nested `if` blocks reduce readability. Combine conditions into a single `if`, or use early returns to flatten the logic. If a helper function already handles the null/empty check internally, rely on it — don't wrap it in a redundant outer guard.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN: Nested if — redundant null guard
if ($error !== null) {
    if (ErrorChecker::is_fatal_error($error)) {
        $this->logger->fatal($error);
    }
}

// ✅ REQUIRED: Flat — is_fatal_error() handles null internally
if (ErrorChecker::is_fatal_error($error)) {
    $this->logger->fatal($error);
}

// ✅ ALSO OK: Early return to flatten
if ($request === null) {
    return;
}

if ($request->has_param('file')) {
    $this->process($request);
}
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN: Nested if
if (response) {
    if (response.status >= 400) {
        handleError(response);
    }
}

// ✅ REQUIRED: Early return or combined condition
if (!response) {
    return;
}

if (response.status >= 400) {
    handleError(response);
}
```

```go
// ── Go ───────────────────────────────────────────────────────

// ❌ FORBIDDEN: Nested if
if err != nil {
    if resp != nil {
        handleError(resp)
    }
}

// ✅ REQUIRED: Combined condition
if err != nil && resp != nil {
    handleError(resp)
}
```

---

## Rule 3: Extract Complex Conditions — No Inline Multi-Part Checks

When an `if` condition contains **two or more operators** (`&&`, `||`, `!`), it **must** be extracted into one of:

1. **A named boolean variable** (`$is_*` / `$has_*` / `isX` / `hasX`) — for local, one-off checks
2. **A dedicated method/function** — for reusable or domain-meaningful checks
3. **A named constant** — for static flag combinations

The goal: every `if` reads as a **single intent**, not as implementation logic.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN: Inline multi-part condition
if ($error && in_array($error['type'], [E_ERROR, E_PARSE], true)) {
    $this->logger->fatal($error);
}

// ✅ REQUIRED: Extracted into a dedicated method
if (ErrorChecker::is_fatal_error($error)) {
    $this->logger->fatal($error);
}

// ❌ FORBIDDEN: Combinable conditions left inline
if ($request !== null && $request->has_param('file') && $request->get_param('file') !== '') {
    $this->process($request);
}

// ✅ REQUIRED: Named boolean for clarity
$has_file_param = $request !== null
    && $request->has_param('file')
    && $request->get_param('file') !== '';

if ($has_file_param) {
    $this->process($request);
}
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN: Inline multi-part condition
if (response && response.status >= 400 && response.data?.code?.startsWith('E8')) {
    showDelegatedError(response);
}

// ✅ REQUIRED: Named boolean
const isDelegatedError = response != null
    && response.status >= 400
    && response.data?.code?.startsWith('E8');

if (isDelegatedError) {
    showDelegatedError(response);
}

// ✅ ALSO OK: Dedicated type-guard function for reusable checks
function isDelegatedError(res: ApiResponse | null): res is DelegatedErrorResponse {
    return res != null && res.status >= 400 && res.data?.code?.startsWith('E8');
}

if (isDelegatedError(response)) {
    showDelegatedError(response);
}
```

```go
// ── Go ───────────────────────────────────────────────────────

// ❌ FORBIDDEN: Inline multi-part condition
if err != nil && resp != nil && resp.StatusCode >= 400 {
    handleUpstreamError(resp)
}

// ✅ REQUIRED: Named boolean
isUpstreamError := err != nil && resp != nil && resp.StatusCode >= 400

if isUpstreamError {
    handleUpstreamError(resp)
}
```

### When to Use Which Extraction

| Complexity | Extraction | Example |
|------------|-----------|---------|
| 2 conditions, used once | Named `$is_*` / `isX` variable | `$has_file = $req !== null && $req->has_param('file');` |
| 2+ conditions, used in multiple places | Dedicated method/function | `ErrorChecker::is_fatal_error($error)` |
| Static flag combination | Named constant | `const EDITABLE = 'PUT, PATCH';` |

---

## Rule 4: Blank Line Before `return` When Preceded by Other Statements

If a block contains statements before `return`, insert **one blank line** before the `return`. If `return` is the **only statement** in the block, no blank line is needed.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN: No blank line before return
if (ErrorChecker::is_invalid_pdo_extension()) {
    $this->logger->error('PDO/SQLite not available');
    return $this->envelope->error('SQLite support not available', 500);
}

// ✅ REQUIRED: Blank line separates logic from exit
if (ErrorChecker::is_invalid_pdo_extension()) {
    $this->logger->error('PDO/SQLite not available');

    return $this->envelope->error('SQLite support not available', 500);
}

// ✅ OK: Return is the only statement — no blank line needed
if ($error === null) {
    return false;
}
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN
const processData = (data: unknown[]) => {
    const filtered = data.filter(isValid);
    return filtered.map(transform);
};

// ✅ REQUIRED
const processData = (data: unknown[]) => {
    const filtered = data.filter(isValid);

    return filtered.map(transform);
};

// ✅ OK: Return is the only statement
if (!data) {
    return null;
}
```

```go
// ── Go ───────────────────────────────────────────────────────

// ❌ FORBIDDEN
func process(data []Item) ([]Item, error) {
    filtered := filter(data)
    return filtered, nil
}

// ✅ REQUIRED
func process(data []Item) ([]Item, error) {
    filtered := filter(data)

    return filtered, nil
}
```

---

## Rule 5: Blank Line After Closing `}` When Followed by More Code

If code continues after a closing `}` (i.e., not followed by another `}`, `else`, `catch`, or end of function), insert **one blank line** after it.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN: No blank line after block when code follows
if ($this->initialized) {
    return;
}
$this->initialized = true;
add_action(HookEnum::INIT, [$this, 'setup']);

// ✅ REQUIRED: Blank line after block when code follows
if ($this->initialized) {
    return;
}

$this->initialized = true;
add_action(HookEnum::INIT, [$this, 'setup']);
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN
if (!user) {
    return;
}
const profile = await fetchProfile(user.id);

// ✅ REQUIRED
if (!user) {
    return;
}

const profile = await fetchProfile(user.id);
```

```go
// ── Go ───────────────────────────────────────────────────────

// ❌ FORBIDDEN
if err != nil {
    return err
}
result := compute()

// ✅ REQUIRED
if err != nil {
    return err
}

result := compute()
```

### Exception: Consecutive Closing Braces

No blank line is needed when a `}` is immediately followed by another `}`, `else`, or `catch`:

```php
if (ErrorChecker::is_fatal_error($error)) {
    $this->logger->fatal($error);
}
// ✅ No blank line — next line is another closing brace
```

---

## Checklist Summary (Copy for PRs)

```
[ ] No single-line `if (...) return;` — always use braces
[ ] No nested `if` — flatten with combined conditions or early returns
[ ] No inline multi-part `if` (2+ operators) — extract to named variable or method
[ ] Blank line before `return` when preceded by other statements
[ ] Blank line after closing `}` when followed by more code
```

---

## Cross-References

- [No Raw Negations](./no-negatives.md) — Positive guard functions instead of `!` (all languages)
- [PHP Coding Standards](../04-php-standards/README.md) — PHP-specific rules that reference this spec
- [PHP Forbidden Patterns](../04-php-standards/forbidden-patterns.md) — PHP checklist
- [PHP Enum Classes](../04-php-standards/enums.md) — `ErrorChecker` examples

---

*Cross-language code style specification v1.0.0 — 2026-02-12*
