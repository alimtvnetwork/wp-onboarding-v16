# Cross-Language Code Style — Braces, Nesting, Spacing & Function Size

> **Version:** 3.1.0  
> **Updated:** 2026-02-21  
> **Applies to:** PHP, TypeScript, Go

---

## Overview

These ten rules govern control-flow formatting and function design across **all languages** in the project. Language-specific specs (PHP, TypeScript, Go) reference this document as the single source of truth.

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

## Rule 2: Zero Nested `if` — Absolute Ban

Nested `if` blocks are **absolutely forbidden** — zero tolerance, no exceptions. Every nested `if` must be flattened using one of: (a) combined conditions, (b) early returns, (c) extracted helper functions. If a helper function already handles the null/empty check internally, rely on it — don't wrap it in a redundant outer guard.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN: Nested if — redundant null guard
if ($error !== null) {
    if (ErrorChecker::isFatalError($error)) {
        $this->logger->fatal($error);
    }
}

// ✅ REQUIRED: Flat — isFatalError() handles null internally
if (ErrorChecker::isFatalError($error)) {
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
if (ErrorChecker::isFatalError($error)) {
    $this->logger->fatal($error);
}

// ❌ FORBIDDEN: Combinable conditions left inline
if ($request !== null && $request->has_param('file') && $request->get_param('file') !== '') {
    $this->process($request);
}

// ✅ REQUIRED: Named boolean for clarity
$hasFileParam = $request !== null
    && $request->hasParam('file')
    && $request->getParam('file') !== '';

if ($hasFileParam) {
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
| 2 conditions, used once | Named `$is_*` / `isX` variable | `$hasFile = $req !== null && $req->hasParam('file');` |
| 2+ conditions, used in multiple places | Dedicated method/function | `ErrorChecker::isFatalError($error)` |
| Static flag combination | Named constant | `const EDITABLE = 'PUT, PATCH';` |

---

## Rule 4: Blank Line Before `return` or `throw` When Preceded by Other Statements

If a block contains statements before `return` or `throw`, insert **one blank line** before the `return`/`throw`. If `return`/`throw` is the **only statement** in the block, no blank line is needed.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN: No blank line before return
if (ErrorChecker::isInvalidPdoExtension()) {
    $this->logger->error('PDO/SQLite not available');
    return $this->envelope->error('SQLite support not available', 500);
}

// ✅ REQUIRED: Blank line separates logic from exit
if (ErrorChecker::isInvalidPdoExtension()) {
    $this->logger->error('PDO/SQLite not available');

    return $this->envelope->error('SQLite support not available', 500);
}

// ❌ FORBIDDEN: No blank line before throw
if (PathHelper::isFileMissing($path)) {
    $this->logger->error('File not found: ' . $path);
    throw new RuntimeException('File not found: ' . $path);
}

// ✅ REQUIRED: Blank line before throw
if (PathHelper::isFileMissing($path)) {
    $this->logger->error('File not found: ' . $path);

    throw new RuntimeException('File not found: ' . $path);
}

// ✅ OK: Return is the only statement — no blank line needed
if ($error === null) {
    return false;
}

// ✅ OK: Throw is the only statement — no blank line needed
if ($error === null) {
    throw new InvalidArgumentException('Error required');
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

// ❌ FORBIDDEN: No blank line before throw
const validate = (input: string) => {
    const trimmed = input.trim();
    throw new Error(`Invalid input: ${trimmed}`);
};

// ✅ REQUIRED
const validate = (input: string) => {
    const trimmed = input.trim();

    throw new Error(`Invalid input: ${trimmed}`);
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
add_action(HookType::Init->value, [$this, 'setup']);

// ✅ REQUIRED: Blank line after block when code follows
if ($this->initialized) {
    return;
}

$this->initialized = true;
add_action(HookType::Init->value, [$this, 'setup']);
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
if (ErrorChecker::isFatalError($error)) {
    $this->logger->fatal($error);
}
// ✅ No blank line — next line is another closing brace
```

---

## Rule 6: Maximum 15 Lines Per Function — Extract Small Helpers

Every function/method body must be **15 lines or fewer** (excluding blank lines, comments, and the signature). If a function exceeds this limit, extract logic into small, well-named helper functions.

### Why

- Short functions are easier to read, test, and debug
- Named helpers act as documentation — the function name describes intent
- Reduces cognitive load — each function does exactly one thing
- Makes code review faster — reviewers can understand each piece in isolation

### How to Flatten

| Problem | Solution |
|---------|----------|
| Long setup + logic + cleanup | Extract each phase into a helper |
| Multiple validation checks | Extract `validateRequest()` helper |
| Complex data transformation | Extract `transformPayload()` helper |
| Repeated patterns | Extract shared utility function |

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN: 25+ line function
public function handleUpload($request) {
    $file = $request->get_param('file');
    $source = $request->get_param('source');
    // ... validation ...
    // ... processing ...
    // ... logging ...
    // ... response building ...
}

// ✅ REQUIRED: Short top-level, helpers do the work
public function handleUpload(WP_REST_Request $request): WP_REST_Response {
    $params = $this->extractUploadParams($request);
    $this->validateUpload($params);
    $result = $this->processUpload($params);
    $this->logUpload($result);

    return $this->envelope->success($result);
}
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN: Long function
const handleSubmit = async (data: FormData) => {
    // 20+ lines of validation, API call, state updates, toasts...
};

// ✅ REQUIRED: Decomposed
const handleSubmit = async (data: FormData) => {
    const validated = validateFormData(data);
    const result = await submitToApi(validated);
    updateLocalState(result);
    showSuccessToast(result.message);
};
```

```go
// ── Go ───────────────────────────────────────────────────────

// ❌ FORBIDDEN: Long function
func ProcessUpload(ctx context.Context, req UploadRequest) error {
    // 20+ lines...
}

// ✅ REQUIRED: Decomposed
func ProcessUpload(ctx context.Context, req UploadRequest) error {
    if err := validateUpload(req); err != nil {
        return err
    }

    result, err := executeUpload(ctx, req)
    if err != nil {
        return apperror.Wrap(err, apperror.ErrSyncCheck, "upload failed")
    }

    return logAndRespond(ctx, result)
}
```

---

## Rule 7: Zero Nested `if` — Absolute Ban (Reinforced)

This is a **reinforcement of Rule 2** with stricter language. Nested `if` blocks are the single biggest readability killer. There is **zero tolerance** — any code review finding a nested `if` is an automatic rejection.

### Flattening Techniques

| Nesting Pattern | Flattening Technique |
|----------------|---------------------|
| Null guard → logic | Early return for null |
| Permission → action | Early return for no permission |
| Multiple conditions | Combined `&&` (extract if 2+ operators) |
| If-inside-loop | Extract loop body to helper function |
| If-inside-if-inside-if | Extract to dedicated method |

```php
// ❌ FORBIDDEN: Triple nesting
if ($request !== null) {
    if ($request->hasParam('file')) {
        if ($this->isValidFile($request->getParam('file'))) {
            $this->process($request);
        }
    }
}

// ✅ REQUIRED: Flat with early returns
if ($request === null) {
    return;
}

$hasValidFile = $request->hasParam('file')
    && $this->isValidFile($request->getParam('file'));

if ($hasValidFile) {
    $this->process($request);
}
```

---

## Rule 8: No Leading Backslash on Global Types

In catch blocks and type hints, use `Throwable` without the leading backslash, even in namespaced files. The same applies to other global types used in catch blocks or parameter hints.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN
catch (\Throwable $e)
function foo(\Throwable $e): array

// ✅ REQUIRED
catch (Throwable $e)
function foo(Throwable $e): array
```

```typescript
// ── TypeScript / Go ─────────────────────────────────────────
// Not applicable — these languages don't have leading-backslash syntax.
```

---

## Rule 9: Multi-Line Parameters — More Than Two Parameters

When a function/method signature has **more than two parameters**, each parameter must be on its own line with consistent indentation and a **trailing comma** after the last parameter.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN (>2 params on one line)
function buildRecord(string $label, string $path, bool $success, ?string $error): void {

// ✅ REQUIRED
function buildRecord(
    string $label,
    string $path,
    bool $success,
    ?string $error,
): void {

// ✅ OK: 2 params — single line is fine
function loadFile(string $label, string $path): bool {
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN (>2 params on one line)
function buildRecord(label: string, path: string, success: boolean, error?: string): void {

// ✅ REQUIRED
function buildRecord(
    label: string,
    path: string,
    success: boolean,
    error?: string,
): void {
```

```go
// ── Go ───────────────────────────────────────────────────────

// ❌ FORBIDDEN (>2 params on one line)
func BuildRecord(label string, path string, success bool, errMsg string) {

// ✅ REQUIRED
func BuildRecord(
	label string,
	path string,
	success bool,
	errMsg string,
) {
```

---

## Rule 10: Blank Line Before Control Structures When Preceded by Statements

When an `if`, `for`, `foreach`/`for...of`, or `while` block is preceded by **one or more non-brace statements** (assignments, function calls, etc.), insert **one blank line** before the control structure. This visually separates "setup" from "decision" logic.

**Exception:** No blank line is needed when the control structure is the first statement in a block or immediately follows another closing `}` (already covered by Rule 5).

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN: No blank line between statement and if
$result = $this->apiRequest($agentId, HttpMethodType::Post->value, $endpoint);
if (is_wp_error($result)) {
    return $result;
}

// ✅ REQUIRED: Blank line before if when preceded by a statement
$result = $this->apiRequest($agentId, HttpMethodType::Post->value, $endpoint);

if (is_wp_error($result)) {
    return $result;
}

// ❌ FORBIDDEN: No blank line between statement and foreach
$items = $this->fetchItems();
foreach ($items as $item) {
    $this->process($item);
}

// ✅ REQUIRED
$items = $this->fetchItems();

foreach ($items as $item) {
    $this->process($item);
}

// ✅ OK: if is the first statement — no blank line needed
public function handle(): void {
    if ($this->isDone()) {
        return;
    }
}

// ✅ OK: if follows a closing brace — Rule 5 applies instead
if ($guardA) {
    return;
}

if ($guardB) {
    return;
}
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN
const data = await fetchData(url);
if (!data) {
    return null;
}

// ✅ REQUIRED
const data = await fetchData(url);

if (!data) {
    return null;
}
```

```go
// ── Go ───────────────────────────────────────────────────────

// ❌ FORBIDDEN
result, err := doWork(ctx)
if err != nil {
    return err
}

// ✅ REQUIRED
result, err := doWork(ctx)

if err != nil {
    return err
}
```

---

## Checklist Summary (Copy for PRs)

```
[ ] No single-line `if (...) return;` — always use braces
[ ] No nested `if` — ZERO TOLERANCE — flatten with early returns or combined conditions
[ ] No inline multi-part `if` (2+ operators) — extract to named variable or method
[ ] Blank line before `return` or `throw` when preceded by other statements
[ ] Blank line after closing `}` when followed by more code
[ ] Functions max 15 lines — extract helpers for longer logic
[ ] No deeply nested control flow — extract loop/condition bodies to helpers
[ ] No leading backslash on `Throwable` or other global types in catch/type hints
[ ] Functions with >2 params — one param per line with trailing comma
[ ] Blank line before control structures (`if`/`for`/`foreach`/`while`) when preceded by statements
```

---

## Cross-References

- [No Raw Negations](./no-negatives.md) — Positive guard functions instead of `!` (all languages)
- [Function Naming](./function-naming.md) — No boolean flag parameters (all languages)
- [Strict Typing](./strict-typing.md) — Type declarations & docblock rules (all languages)
- [PHP Coding Standards](../04-php-standards/readme.md) — PHP-specific rules that reference this spec
- [PHP Forbidden Patterns](../04-php-standards/forbidden-patterns.md) — PHP checklist
- [PHP Enum Classes](../04-php-standards/enums.md) — `ErrorChecker` examples

---

*Cross-language code style specification v3.1.0 — 2026-02-21*
