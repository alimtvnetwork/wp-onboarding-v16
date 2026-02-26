# Memory: architecture/coding-standards/boolean-logic-principles
Updated: 2026-02-26

Strict boolean logic standards apply across all languages (PHP, TS, Go). Six principles are enforced (spec: `spec/03-coding-guidelines/boolean-principles.md` v2.1.0):

1. **P1 — `is`/`has` prefix**: All boolean identifiers must use `is` or `has` prefixes.
2. **P2 — No negative words in names**: The words `not`, `no`, `non` are absolutely banned from boolean variable/function/method names. Always use a positive semantic synonym instead (e.g., `isPending` not `isNotReady`, `isAbsentFromList` not `isNotInList`, `isErrorListClear` not `isNoRecentErrors`). Double negatives (`!isNot...`) are the worst form.
3. **P3 — Named guards over raw negation**: Never use `!` on function calls at call sites; use positively named guard functions (e.g., `isFileMissing()` not `!file_exists()`).
4. **P4 — Extract complex expressions**: Conditions with 2+ operators must be extracted into named boolean variables.
5. **P5 — Explicit boolean parameters**: No bare `true`/`false` at call sites; use separate named methods or options objects.
6. **P6 — No mixed polarity**: Never combine positive + negative booleans in a single `if` condition (e.g., `isX && !isY`). Always extract to a single named boolean capturing intent (e.g., `isConflict`, `isAccessDenied`, `isPending`).

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

// ✅ CORRECT — extract to named boolean
isCacheUsable := isEnabled && !isForceRefresh  // ← extraction OK at assignment
if isCacheUsable {
    return cached
}

// ❌ WRONG — negation in assignment without positive name
IsValid: !isExpired

// ✅ CORRECT — compute the positive form directly
isFresh := !isExpired  // extracted with positive name
// or better: compute isFresh directly without isExpired
isFresh := expiresAt.After(time.Now())
IsValid: isFresh
```

### P2+P3 combined — Function naming

```go
// ❌ WRONG — function name contains "Not"
func isNotNil(v any) bool { return v != nil }

// ✅ CORRECT — positive synonym
func isDefined(v any) bool { return v != nil }

// ❌ WRONG — function name is negative
func isNotTransient(err error) bool { ... }

// ✅ CORRECT — positive synonym
func isPermanentError(err error) bool { ... }

// ❌ WRONG — function name is negative
func hasNoContent(s string) bool { ... }

// ✅ CORRECT — positive synonym
func isBlank(s string) bool { ... }
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

---

## Go-Specific Exemptions

These patterns are permitted because they are Go idioms:
- `if !ok` (comma-ok pattern)
- `if err != nil` (error check idiom)
- `if err != nil && !os.IsNotExist(err)` (file-not-found guard — stdlib naming)
- Handler guard returns (early return for disabled features)
- Stdlib calls like `os.IsNotExist` — extract into a positive named variable if used 3+ times

---

## Cross-References
- **Spec**: `spec/03-coding-guidelines/boolean-principles.md` (v2.1.0)
- **Guard inventories**: `spec/03-coding-guidelines/no-negatives.md`
- **Go boolean standards**: Memory `coding-standards/go-boolean-standards`
