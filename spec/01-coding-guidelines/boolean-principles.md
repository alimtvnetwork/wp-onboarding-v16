# Cross-Language Boolean Principles

> **Version:** 2.1.0  
> **Updated:** 2026-02-17  
> **Applies to:** PHP, TypeScript, Go, C#, and any delegated language

---

## Overview

Boolean variables, parameters, return values, and method names are the most frequently read tokens in any codebase. Poorly named booleans silently degrade readability, cause logic bugs, and increase cognitive load during code review. This spec defines **six non-negotiable principles** that every programming language in this project must follow.

---

## Principle 1: Always Use `is` or `has` Prefixes

Every boolean identifier — variable, property, parameter, or method — **must** start with `is` or `has`.

```php
// ── PHP ──────────────────────────────────────────────────────

// ❌ FORBIDDEN
$active = true;
$loaded = false;
$blocked = true;

// ✅ REQUIRED
$isActive = true;
$isLoaded = false;
$isBlocked = true;
$hasPermission = true;
```

```typescript
// ── TypeScript ───────────────────────────────────────────────

// ❌ FORBIDDEN
const loading = true;
const valid = false;
const overdue = checkOverdue();

// ✅ REQUIRED
const isLoading = true;
const isValid = false;
const hasOverdue = checkOverdue();
```

```go
// ── Go ───────────────────────────────────────────────────────

// ❌ FORBIDDEN
blocked := true
connected := false

// ✅ REQUIRED
isBlocked := true
isConnected := false
hasItems := len(items) > 0
```

### Method Names Follow the Same Rule

```php
// ❌ FORBIDDEN
$order->overdue();
$user->admin();

// ✅ REQUIRED
$order->hasOverdue();
$user->isAdmin();
```

This mirrors industry best practices. For example, .NET's `char` type exposes `IsLetter`, `IsDigit`, `IsUpper`, `IsLower`, `IsNumber`, `IsPunctuation`, `IsSeparator`, `IsSymbol`, `IsControl`, `IsLetterOrDigit` — all boolean methods with the `Is` prefix.

---

## Principle 2: Never Use Negative Words in Boolean Names

The words **`not`**, **`no`**, and **`non`** are **absolutely banned** from boolean variable names, function names, and method names. These words create cognitive overhead — the reader must mentally invert the meaning. Instead, always use a **positive semantic synonym** that describes what the state actually **is**.

Double negatives (`!isNot...`, `!isNotBlocked`) are the worst form and must never appear.

### Naming Strategy: Describe What It IS, Not What It ISN'T

| ❌ Forbidden Name | ✅ Required Name | Semantic Meaning |
|---|---|---|
| `isNotReady` | `isPending` | The order is waiting |
| `isNotInList` | `isAbsentFromList` | The item is absent |
| `isNoRecentErrors` | `isErrorListClear` | The error list is clean |
| `isNotDirectory` | `isDirAbsent` | The directory doesn't exist |
| `isNotRegularFile` | `isIrregularPath` | The path is irregular |
| `isNotPHP` | `isSkippableEntry` | The entry should be skipped |
| `isNotBlocked` | `isActive` | The entity is active |
| `isClassNotLoaded` | `isClassUnregistered` | The class is unregistered |
| `hasNoPermission` | `isUnauthorized` | The user lacks access |

```typescript
// ❌ FORBIDDEN — "not" in the variable name
const isNotReady = order.status !== 'ready';
if (isNotReady) {
    throw new Error('Order is not ready');
}

// ✅ REQUIRED — Positive semantic synonym
const isPending = order.status !== 'ready';
if (isPending) {
    throw new Error('Order is not ready');
}
```

```php
// ❌ FORBIDDEN — "No" in the variable name
$isNoRecentErrors = empty($errors) || !$hasUnseen;

// ✅ REQUIRED — Describes the positive state
$isErrorListClear = empty($errors) || !$hasUnseen;
```

### Rule: Name booleans for the **positive semantic state**, then negate only once if needed

```typescript
// ❌ AVOID — Raw negation at call site
if (!isBlocked) {
    // active
}

// ✅ BEST — Extract to a positive boolean
const isActive = !isBlocked;

if (isActive) {
    // best to use like this
}
```

---

## Principle 3: Replace Raw Negation With Named Guards

Never use raw `!` on function calls or existence checks at call sites. Instead, wrap every negative check in a **positively named utility function**.

```php
// ❌ FORBIDDEN — Raw negation on function call
if (!$order->isValid()) {
    return;
}

// ✅ REQUIRED — Semantic inverse method on the object
if ($order->isInvalid()) {
    return;
}
```

```typescript
// ❌ FORBIDDEN
if (!isDefined(value)) {
    return;
}

// ✅ REQUIRED — Use a positive guard
if (isUndefined(value)) {
    return;
}
```

```go
// ❌ FORBIDDEN
if !IsFileExists(path) {
    return apperror.New("E4010", "file not found")
}

// ✅ REQUIRED
if IsFileMissing(path) {
    return apperror.New("E4010", "file not found")
}
```

For the full guard function inventory, see [no-negatives.md](./no-negatives.md).

---

## Principle 4: Extract Complex Boolean Expressions

When a boolean expression contains **2+ operators** (`&&`, `||`, `!`), it **must** be extracted into a named boolean variable or a dedicated method. The `if` statement should read as a single intent.

```csharp
// ❌ BAD CODE — Inline complex condition
public void ProcessData(int value)
{
    if (value > 0 && value % 2 == 0 || value < -10)
    {
        // ...
    }
}

// ✅ GOOD CODE — Extracted to a named method
public void ProcessData(int value)
{
    if (IsValueValid(value))
    {
        // ...
    }
}

private bool IsValueValid(int value)
{
    return (value > 0 && value % 2 == 0 || value < -10);
}
```

```php
// ❌ FORBIDDEN
if ($request !== null && $request->hasParam('file') && $request->getParam('file') !== '') {
    $this->process($request);
}

// ✅ REQUIRED
$hasFileParam = $request !== null
    && $request->hasParam('file')
    && $request->getParam('file') !== '';

if ($hasFileParam) {
    $this->process($request);
}
```

```go
// ❌ FORBIDDEN
if err != nil && resp != nil && resp.StatusCode >= 400 {
    handleUpstreamError(resp)
}

// ✅ REQUIRED
isUpstreamError := err != nil && resp != nil && resp.StatusCode >= 400

if isUpstreamError {
    handleUpstreamError(resp)
}
```

See also: [code-style.md — Rule 3](./code-style.md#rule-3-extract-complex-conditions--no-inline-multi-part-checks)

---

## Principle 5: Boolean Parameters Must Be Explicit

Never use bare `true`/`false` at call sites. If a function accepts a boolean parameter, either:
1. Use separate, explicitly named methods
2. Use an enum or options object

```typescript
// ❌ FORBIDDEN — What does `true` mean here?
fetchData(userId, true);

// ✅ REQUIRED — Option A: Named methods
fetchDataWithCache(userId);
fetchDataWithoutCache(userId);

// ✅ REQUIRED — Option B: Options object
fetchData(userId, { isUseCache: true });
```

```php
// ❌ FORBIDDEN
$this->log($message, true);

// ✅ REQUIRED — Separate methods
$this->logWithTrace($message);
$this->log($message);
```

See also: [function-naming.md](./function-naming.md)

---

## Principle 6: Never Mix Positive and Negative Booleans in a Single Condition

Combining a positive boolean with a negated boolean in the same `if` condition (e.g., `isX && !y`, `IsReady && !overwrite`) is a **code smell**. It forces the reader to mentally switch polarity mid-expression, creating cognitive load and hiding intent.

**The fix:** Extract the combined condition into a single, positively named boolean that captures the **actual intent**.

```go
// ❌ FORBIDDEN — Mixed polarity: positive + negative
if isProjectExists && !isOverwrite {
    return fmt.Errorf("conflict")
}

// ✅ REQUIRED — Extract to single-intent named boolean
isConflict := isProjectExists && !isOverwrite

if isConflict {
    return fmt.Errorf("conflict")
}
```

```php
// ❌ FORBIDDEN — Mixed polarity
if ($isAuthenticated && !$isAuthorized) {
    throw new ForbiddenException();
}

// ✅ REQUIRED — Single intent
$isAccessDenied = $isAuthenticated && !$isAuthorized;

if ($isAccessDenied) {
    throw new ForbiddenException();
}
```

```typescript
// ❌ FORBIDDEN — Mixed polarity
if (isLoggedIn && !hasPermission) {
    redirect('/unauthorized');
}

// ✅ REQUIRED — Single intent
const isUnauthorized = isLoggedIn && !hasPermission;

if (isUnauthorized) {
    redirect('/unauthorized');
}
```

### Why This Matters

| Pattern | Problem | Fix |
|---|---|---|
| `isX && !y` | Reader must switch polarity mid-expression | Extract to `isConflict` / `isUnauthorized` / `isDenied` |
| `isReady && !isOverwrite` | `!isOverwrite` lacks semantic meaning | Use `isFreshImport` or extract full condition |
| `hasData && !isProcessed` | Two separate concerns crammed together | Extract to `isPendingProcessing` |

### Rule Summary

1. **Never combine `isX` with `!isY`** in the same `if` condition
2. **Always extract** the combined condition into a named boolean with a positive semantic name
3. The named boolean should express the **intent** (e.g., `isConflict`, `isAccessDenied`, `isPending`) — not just restate the logic

---

## Quick Reference

| ❌ Forbidden | ✅ Required | Principle |
|-------------|------------|-----------|
| `$active` | `$isActive` | P1: `is`/`has` prefix |
| `$loaded` | `$isLoaded` | P1: `is`/`has` prefix |
| `!isNotBlocked` | `isBlocked` | P2: No negative words |
| `isNotBlocked` | `isActive` (synonym) | P2: No negative words |
| `isNotReady` | `isPending` (synonym) | P2: No negative words |
| `!$obj->isValid()` | `$obj->isInvalid()` | P3: Named guards |
| `if (a && b \|\| c)` | `if (isValid(x))` | P4: Extract expressions |
| `fn(true)` | `fnWithOption()` | P5: Explicit params |
| `isX && !isY` | `isConflict` (extracted) | P6: No mixed polarity |

---

## Common Mistakes — Boolean Logic

### Mistake 1: Missing `is`/`has` Prefix (P1)

```php
// ❌ WRONG
$active = true;        // What is active? No semantic meaning.
$loaded = false;       // Ambiguous.

// ✅ CORRECT
$isActive = true;
$isLoaded = false;
```

### Mistake 2: Negative Word in Name (P2)

```go
// ❌ WRONG — "not" in the name
isNotReady := order.Status != "ready"
hasNoPermission := !user.HasPermission("admin")

// ✅ CORRECT — positive semantic synonym
isPending := order.Status != "ready"
isUnauthorized := !user.HasPermission("admin")
```

### Mistake 3: Raw `!` on Function Call (P3)

```php
// ❌ WRONG
if (!$order->isValid()) { return; }
if (!file_exists($path)) { return; }

// ✅ CORRECT
if ($order->isInvalid()) { return; }
if (PathHelper::isFileMissing($path)) { return; }
```

```go
// ❌ WRONG
if !v.IsValid() { return variantLabels[Invalid] }
if !pathutil.IsDir(gitDir) { return err }

// ✅ CORRECT
if v.IsInvalid() { return variantLabels[Invalid] }
if pathutil.IsDirMissing(gitDir) { return err }
```

### Mistake 4: Mixed Polarity in Condition (P6)

```typescript
// ❌ WRONG — positive + negative in same if
if (isLoggedIn && !hasPermission) {
    redirect('/unauthorized');
}

// ✅ CORRECT — extract to single-intent name
const isUnauthorized = isLoggedIn && !hasPermission;
if (isUnauthorized) {
    redirect('/unauthorized');
}
```

### Go-Specific Exemptions

These patterns are **exempt** from the no-negation rule in Go:
- `if !ok` — idiomatic comma-ok pattern
- `if !requireService(w, svc, "name")` — handler guard returns
- `if err != nil` — idiomatic error check
- `if !strings.HasPrefix(...)` — stdlib calls (extract if repeated 3+ times)

---

## Cross-References

- [No Raw Negations](./no-negatives.md) — Full guard function inventory
- [Code Style — Rule 3](./code-style.md) — Complex condition extraction
- [Function Naming](./function-naming.md) — No boolean flag parameters
- [PHP Boolean Guard Inventory](../04-php-standards/readme.md) — PHP-specific helpers
- [Go Boolean Standards](../03-golang-standards/02-boolean-standards.md) — Go-specific rules and exemptions
- [Master Coding Guidelines](./00-master-coding-guidelines.md) — Consolidated reference
- [Issues & Fixes Log](./01-issues-and-fixes-log.md) — Historical fixes

---

*Boolean principles specification v2.2.0 — 2026-02-23*
