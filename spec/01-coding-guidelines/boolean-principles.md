# Cross-Language Boolean Principles

> **Version:** 1.0.0  
> **Updated:** 2026-02-17  
> **Applies to:** PHP, TypeScript, Go, C#, and any delegated language

---

## Overview

Boolean variables, parameters, return values, and method names are the most frequently read tokens in any codebase. Poorly named booleans silently degrade readability, cause logic bugs, and increase cognitive load during code review. This spec defines **five non-negotiable principles** that every programming language in this project must follow.

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

## Principle 2: Never Use Double Negatives

Double negatives (`!isNot...`, `!isNotBlocked`) are **extremely hard to process** and must never appear in code. The reader has to mentally invert twice to understand the actual meaning.

```typescript
// ❌ FORBIDDEN — Very hard to process. What does this mean?
if (!isNotBlocked) {
    // active???
}

// ❌ FORBIDDEN — Hard to read, confuses everyone
if (isNotBlocked) {
    // active
}

// ✅ REQUIRED — Always positive, always clear
if (isBlocked) {
    // blocked
}
```

### Rule: Name booleans for the **positive** case, then negate only once if needed

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

## Quick Reference

| ❌ Forbidden | ✅ Required | Principle |
|-------------|------------|-----------|
| `$active` | `$isActive` | P1: `is`/`has` prefix |
| `$loaded` | `$isLoaded` | P1: `is`/`has` prefix |
| `!isNotBlocked` | `isBlocked` | P2: No double negatives |
| `isNotBlocked` | `isActive` (inverted) | P2: No double negatives |
| `!$obj->isValid()` | `$obj->isInvalid()` | P3: Named guards |
| `if (a && b \|\| c)` | `if (isValid(x))` | P4: Extract expressions |
| `fn(true)` | `fnWithOption()` | P5: Explicit params |

---

## Cross-References

- [No Raw Negations](./no-negatives.md) — Full guard function inventory
- [Code Style — Rule 3](./code-style.md) — Complex condition extraction
- [Function Naming](./function-naming.md) — No boolean flag parameters
- [PHP Boolean Guard Inventory](../04-php-standards/readme.md) — PHP-specific helpers
- [Strict Typing](./strict-typing.md) — Type-safe boolean handling

---

*Boolean principles specification v1.0.0 — 2026-02-17*
