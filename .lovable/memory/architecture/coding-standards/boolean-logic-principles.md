# Memory: architecture/coding-standards/boolean-logic-principles
Updated: 2026-02-28

Strict boolean logic standards apply across all languages (PHP, TS, Go). Eight principles are enforced (spec: `spec/03-coding-guidelines/boolean-principles.md` v3.0.0):

1. **P1 — `is`/`has` prefix**: All boolean identifiers must use `is` or `has` prefixes.
2. **P2 — No negative words in names**: The words `not`, `no`, `non` are absolutely banned from boolean variable/function/method names. Always use a positive semantic synonym instead (e.g., `isPending` not `isNotReady`, `isAbsentFromList` not `isNotInList`, `isErrorListClear` not `isNoRecentErrors`). Double negatives (`!isNot...`) are the worst form.
3. **P3 — Named guards over raw negation**: Never use `!` on function calls at call sites; use positively named guard functions (e.g., `isFileMissing()` not `!file_exists()`).
4. **P4 — Extract complex expressions**: Conditions with 2+ operators must be extracted into named boolean variables.
5. **P5 — Explicit boolean parameters**: No bare `true`/`false` at call sites; use separate named methods or options objects.
6. **P6 — No mixed polarity**: Never combine positive + negative booleans in a single `if` condition (e.g., `isX && !isY`). Always extract to a single named boolean capturing intent (e.g., `isConflict`, `isAccessDenied`, `isPending`).
7. **P7 — Extract negations to positive counterpart variables**: When a boolean must be negated, ALWAYS extract the negation into a new named variable with a positive semantic name on the preceding line. Then use only the positive variable in conditions. `== false` is NOT acceptable either — it's just a verbose negation with the same readability problem.
8. **P8 — Extract numeric/comparison expressions**: Raw numeric comparisons (e.g., `statusCode < 400`, `totalDeleted > 0`) must be extracted into named boolean variables that describe intent (e.g., `isSuccessStatus`, `hasDeletions`).

---

## P7 — Extract negations to positive counterpart (CRITICAL)

This is the most important refinement. Negation (`!` or `== false`) must NEVER appear directly in conditions. Instead, extract to a positively-named variable first.

```go
// ❌ WRONG — negation in condition
isLiveRunWithDeletions := !isDryRun && totalDeleted > 0

// ❌ ALSO WRONG — explicit comparison is just verbose negation
isLiveRunWithDeletions := isDryRun == false && totalDeleted > 0

// ✅ CORRECT — extract positive counterpart, then compose
isLiveRun := !isDryRun           // positive counterpart on its own line
hasDeletions := totalDeleted > 0  // P8: numeric comparison extracted
isLiveRunWithDeletions := isLiveRun && hasDeletions
if isLiveRunWithDeletions {
    ...
}
```

The key question when negating is: **"What is the positive meaning?"**
- `isDryRun` negated → `isLiveRun`
- `isPending` negated → `isComplete` or `isDone`
- `isDisabled` negated → `isEnabled`
- `isExpired` negated → `isFresh`

### P8 — Extract numeric comparisons

```go
// ❌ WRONG — raw comparison in condition
if statusCode < 400 {
    return body
}

// ✅ CORRECT — named boolean
isSuccessStatus := statusCode < 400
if isSuccessStatus {
    return body
}

// ❌ WRONG — raw comparison mixed into compound condition
if isDone && count > 0 {
    flush()
}

// ✅ CORRECT
hasItems := count > 0
isReadyToFlush := isDone && hasItems
if isReadyToFlush {
    flush()
}
```

---

## Concrete Examples

### P2 — Always use positive synonyms, never negative words

| ❌ Wrong | ✅ Correct | Rationale |
|----------|-----------|-----------|
| `isNotNil(fn)` | `isDefined(fn)` | "Defined" is the positive form of "not nil" |
| `isNotEmpty(list)` | `hasItems(list)` | "has items" is positive |
| `isNotReady` | `isPending` | Positive synonym for "not ready" |
| `isNotValid` | `isInvalid` or `isMalformed` | Domain-specific positive term |
| `isNotFound` | `isMissing` or `isAbsent` | Positive synonym for "not found" |
| `hasNoErrors` | `isErrorFree` or `isClean` | Positive framing |
| `isNotConnected` | `isDisconnected` | Single positive adjective |
| `isNotEnabled` | `isDisabled` | Single positive adjective |
| `isNotInList` | `isAbsentFromList` | Positive phrasing |
| `hasNotChanged` | `isUnchanged` | Single positive adjective |
| `isNotComplete` | `isPending` or `isIncomplete` | Positive synonym |
| `isNotAuthorized` | `isForbidden` or `isUnauthorized` | Domain term |

### P3 — Named guards over raw negation

```go
// ❌ WRONG — raw negation at call site
if !isOkStatus(resp.StatusCode, okStatuses) {
    return err
}

// ✅ CORRECT — positive guard function
if isErrorStatus(resp.StatusCode, okStatuses) {
    return err
}

// ❌ WRONG — negating a positive check
if !isPortInUse(port) {
    return nil
}

// ✅ CORRECT — dedicated positive-name function
if isPortFree(port) {
    return nil
}

// ❌ WRONG — negating a boolean check
if !IsEnvelope(data) {
    return nil
}

// ✅ CORRECT — use a positive guard or invert logic
if isRawPayload(data) {
    return nil
}
```

### P6 — No mixed polarity (extract to named boolean)

```go
// ❌ WRONG — mixed polarity in condition
if isEnabled && !isForceRefresh {
    return cached
}

// ✅ CORRECT — extract negation to positive variable first (P7), then compose
isUsingCache := !isForceRefresh   // P7: positive counterpart
isCacheUsable := isEnabled && isUsingCache
if isCacheUsable {
    return cached
}
```

### Positive synonym reference table

| Negative concept | Positive synonym |
|-----------------|-----------------|
| not nil | `isDefined`, `isPresent`, `isSet` |
| not empty | `hasItems`, `isPopulated`, `hasContent` |
| not found | `isMissing`, `isAbsent` |
| not ready | `isPending`, `isInitializing` |
| not valid | `isMalformed`, `isInvalid` |
| not connected | `isDisconnected`, `isOffline` |
| not enabled | `isDisabled` |
| not expired | `isFresh`, `isValid` |
| not ok status | `isErrorStatus`, `isFailedStatus` |
| not in use | `isFree`, `isAvailable` |
| not changed | `isUnchanged`, `isStale` |
| not deleted | `isRetained`, `isPresent` |
| not transient | `isPermanent` |
| not success | `isFailed` |
| not dry run | `isLiveRun` |

---

## Go-Specific Rules

### Comma-ok pattern: Rename `ok` to semantic name

Never use bare `ok` from comma-ok patterns. Rename to a semantic `is`/`has` name. If you need the negation, extract to a positive counterpart on the next line.

```go
// ❌ WRONG — bare ok, negation at call site
val, ok := cache[key]
if !ok {
    return defaultVal
}

// ✅ CORRECT — semantic name + positive counterpart for negation
val, isFound := cache[key]
isMissing := !isFound
if isMissing {
    return defaultVal
}

// ✅ ALSO CORRECT — if you only need the positive case
val, isFound := cache[key]
if isFound {
    return val
}
```

### Error checking: Use AppError methods, not raw `err != nil`

When using the `apperror` package or Result types, NEVER check raw `err != nil`. Use the structured methods.

```go
// ❌ WRONG — raw err != nil
result, err := doSomething()
if err != nil {
    return err
}

// ✅ CORRECT — use Result type with HasError()
result := doSomething()
if result.HasError() {
    return result.AppError()
}
```

### NEVER combine multiple `err != nil` checks

Combining multiple error-nil checks in one condition is absolutely forbidden. If you need to check multiple errors, use AppError methods that compose them.

```go
// ❌ WRONG — two err != nil checks combined
if err1 != nil && err2 != nil {
    return combinedError
}

// ❌ WRONG — error check with negated function
if err != nil && !os.IsNotExist(err) {
    return err
}

// ✅ CORRECT — use AppError methods or extract to named variables
// Option 1: AppError with HasError()
if result.HasError() {
    return result.AppError()
}

// Option 2: If raw error is unavoidable (stdlib), extract to named booleans
isErrorPresent := err != nil
isFileError := os.IsNotExist(err)     // stdlib naming — acceptable
isPermanentError := isErrorPresent && !isFileError  // ← extraction OK at assignment
if isPermanentError {
    return err
}
```

### Permitted Go idioms (minimal exemptions)

These patterns are permitted ONLY in isolation (never combined with other conditions):
- `if err != nil` — single error check idiom (ALONE, never combined with another check)
- `if !ok` — ONLY permitted as `if !ok` alone after type assertion in a simple early return. For all other uses, rename to semantic name and extract counterpart.

**NOT permitted** (previously listed as exemptions, now removed):
- ~~`if err != nil && !os.IsNotExist(err)`~~ — MUST be extracted to named booleans
- ~~Mixed polarity with error checks~~ — MUST use AppError methods or extract

### AppError should provide composition methods

When multiple error conditions must be checked together, AppError should provide methods that encapsulate the logic rather than forcing callers to write compound conditions:

```go
// ❌ WRONG — caller writes compound condition
if configErr != nil || dbErr != nil {
    // handle
}

// ✅ CORRECT — AppError provides a composition method
combinedErr := apperror.FirstError(configErr, dbErr)
if combinedErr.HasError() {
    return combinedErr
}

// ✅ CORRECT — Result type checks
if configResult.HasError() {
    return apperror.Fail[Config](configResult.AppError())
}
```

---

## Cross-Language Application

All principles (P1–P8) apply equally to Go, PHP, and TypeScript. Language-specific syntax differs but the naming and extraction rules are identical:

```php
// PHP — same principles
$isLiveRun = !$isDryRun;
$hasDeletions = $totalDeleted > 0;
$isLiveRunWithDeletions = $isLiveRun && $hasDeletions;

if ($isLiveRunWithDeletions) {
    $this->logCleanupAudit($results);
}
```

```typescript
// TypeScript — same principles
const isLiveRun = !isDryRun;
const hasDeletions = totalDeleted > 0;
const isLiveRunWithDeletions = isLiveRun && hasDeletions;

if (isLiveRunWithDeletions) {
    logCleanupAudit(results);
}
```

---

## Cross-References
- **Spec**: `spec/03-coding-guidelines/boolean-principles.md` (v3.0.0)
- **Guard inventories**: `spec/03-coding-guidelines/no-negatives.md`
- **Go boolean standards**: Memory `coding-standards/go-boolean-standards`
- **Result/Error type invariant**: Memory `architecture/coding-standards/result-error-type-invariant`
