# Plan: Strict Typing, Method Naming Spec, and ErrorResponse Enhancement

## Part 1 — New Specification Documents

### 1a. Create `spec/01-coding-guidelines/function-naming.md`

Cross-language spec (PHP, TypeScript, Go) establishing the  pattern (can have booleans for exception cases need to confirm):

- **Rule**: When a parameter changes the *meaning* of an operation, split it into separate explicitly named methods
- **Anti-pattern**: `log("msg", false)` -- call site is unreadable
- **Pattern**: `info()` vs `infoWithTrace()`, `logAndReturn()` vs `logAndReturnWithTrace()`
- Examples in all three languages (PHP, TypeScript, Go)
  - But use properly lang. Naming convention always not like PHP
- Add cross-references from `spec/04-php-standards/README.md`, `spec/02-typescript-standards/README.md`, `spec/03-golang-standards/README.md`

### 1b. Create `spec/01-coding-guidelines/strict-typing.md`

Cross-language spec for enforcing type declarations everywhere the language supports them:

- **PHP**: typed parameters, return types, typed class properties (7.4+), nullable `?Type`, union types (8.0+), constructor promotion (8.0+) -- no untyped parameters or returns allowed
- **TypeScript**: already covered by generics-first rule; reinforce no `any`
- **Go**: already statically typed; reinforce no `interface{} /any (use appropriate type or at wrost use Generic<T>)`
- Rule: **remove docblock** `@param`**/**`@return` **annotations when the signature already (where it is not necessary but do need to put if the function is more than 10 lines or doing something complex)** 
  - **declares types** -- comments must not duplicate what the type system already enforces it depedns, if PHP we can skip it for now, ask questions if confused
- Rule: keep docblocks only for genuinely explanatory context (e.g., describing domain semantics, side effects, or constraints not expressible in types)

### 1c. Update existing specs with cross-references

- `spec/04-php-standards/README.md` -- add to forbidden patterns table: untyped parameters, untyped return, redundant `@param` on typed signatures. Add cross-ref to both new docs
- `spec/02-typescript-standards/README.md` -- add cross-ref to `function-naming.md`
- `spec/03-golang-standards/README.md` -- add cross-ref to `function-naming.md`
- `spec/01-coding-guidelines/code-style.md` -- add cross-ref to both new docs

---

## Part 2 — ErrorResponse Enhancement

### 2a. Refactor `ErrorResponse.php` with strict typing and method variants

The current `logAndReturn` method logs the exception and returns `['success' => false, 'error' => ...]`. Add a second variant with stack trace skip capability:

```text
ErrorResponse (static helper class)
  |
  +-- logAndReturn(logger, exception, context)
  |     Uses exception's native getFile()/getLine()
  |     For standard catch blocks
  |
  +-- logAndReturnWithTrace(logger, exception, context, skipFrames)
        Uses debug_backtrace() with frame skipping
        For wrappers/middleware where the throw site is buried
```

- Apply strict PHP typing: all parameters typed, return type `array` (or a typed alias)
- Remove redundant docblock comments that duplicate type information
- Keep the class minimal (under 15 lines per method)

### 2b. Apply strict typing to ErrorResponse.php

Learn to have less params in func, if the param is more than 3 use class objects or other best practices to insert the param. Important notes. Some exceptional cases like the util or framework based funcs can have more than 3 param, ask to confirm always.

Before (current):

```php
public static function logAndReturn(RiseupFileLogger $logger, Throwable $e, string $context = ''): array {
```

After (with typing already present, remove redundant docblock):

```php
/** Log exception and return standardized error array. */
public static function logAndReturn(RiseupFileLogger $logger, Throwable $e, string $context = ''): array {
```

New method:

```php
/** Log with manual backtrace (skipping wrapper frames) and return error array. */
public static function logAndReturnWithTrace(RiseupFileLogger $logger, Throwable $e, string $context = '', int $skipFrames = 1): array {
```

---

## Part 3 — Codebase Typing Remediation (Scope Assessment)

The existing PHP codebase has many untyped parameters and returns (e.g., `logException($e, $context = '')` has no types). A full remediation pass will be planned as a separate feature after specs are approved. Key areas:

- **LoggerLevelMethodsTrait**: `debug($message, $context = array())` -- needs `string $message, array $context`
- **ResponseTrait**: `errorResponse($message, $status, $exception = null)` -- partially typed (`logErrorWithBacktrace` has types, `errorResponse` does not)
- **All trait methods**: ~37 files with catch blocks likely have untyped method signatures

This will be tracked as a follow-up batch (similar to the camelCase migration I6).

---

## Execution Order

1. Create `spec/01-coding-guidelines/function-naming.md`
2. Create `spec/01-coding-guidelines/strict-typing.md`
3. Update cross-references in `spec/04-php-standards/README.md`, `spec/02-typescript-standards/README.md`, `spec/03-golang-standards/README.md`, `spec/01-coding-guidelines/code-style.md`
4. Refactor `ErrorResponse.php` -- add `logAndReturnWithTrace`, apply strict typing, trim comments
5. Update memory files for the new conventions

---

## Side Effects

- **None for existing callers**: current `logAndReturn` signature is unchanged
- **New method** `logAndReturnWithTrace` is additive only
- Spec changes set expectations for future code; no existing code breaks
- The typing remediation (Part 3) is deferred -- no immediate changes to existing untyped methods in this pass