# Go Boolean Standards — Positive Logic & Naming

> **Version**: 1.1.0
> **Last updated**: 2026-02-26

## 1. Positive Boolean Naming (Rule P1)

All boolean-returning functions and variables **must** use positive semantic names with `Is` or `Has` prefixes.

```go
// ✅ Positive naming
func IsValid() bool
func HasPermission() bool
func IsActive() bool

// ❌ Negative naming — PROHIBITED
func IsNotValid() bool
func HasNoPermission() bool
func IsDisabled() bool
```

**Exception**: Enum variant checkers where the variant itself has a negative-sounding name are permitted (e.g., `IsNotFound()` for the `NotFound` variant, `IsUnknown()` for the `Unknown` variant).

## 2. Negation Elimination (Rule P2)

### 2.1 — Named Boolean Variables

Replace inline `!` negation with named positive-logic variables:

```go
// ❌ Inline negation
if !user.IsAdmin() && !request.IsInternal() {
    return ErrForbidden
}

// ✅ Named positive logic
isExternalNonAdmin := user.IsRegular() && request.IsExternal()
if isExternalNonAdmin {
    return ErrForbidden
}
```

### 2.2 — Positive Counterpart Methods

When a type has an `IsX()` method and code frequently uses `!IsX()`, add a positive counterpart:

```go
// pathutil package
func IsDirMissing(path string) bool { return !IsDir(path) }

// dbutil.Result[T]
func (r Result[T]) IsEmpty() bool { return !r.defined }  // already exists ✅
```

#### Inventory of Positive Guard Functions

The following counterparts exist in the codebase and **must** be used instead of inline `!` negation:

| Package | Function | Replaces |
|---------|----------|----------|
| `pathutil` | `IsDirMissing(path)` | `!IsDir(path)` |
| `portutil` | `isPortFree(port)` | `!isPortInUse(port)` |
| `wordpress` | `isErrorStatus(code, ok)` | `!isOkStatus(code, ok)` |
| `wordpress` | `IsEnvelopeMissing(data)` | `!IsEnvelope(data)` |
| `publish` | `isPermanentError(err)` | `!isTransientAppError(err)` |
| `plugin` | `ScanResult.IsInvalid()` | `!ScanResult.IsValid` |
| `publishtype` | `Variant.IsUndefined()` | `!Variant.IsDefined()` |
| `e2e` | `apiResponse.isDataMissing(key)` | `!apiResponse.hasDataField(key)` |
| `site` | `isStackTraceMissing(result)` | `!hasStackTraceContent(result)` |

### 2.3 — Enum Comparisons

Use `IsOther(val)` or `IsInvalid()` instead of `!=` or `!IsValid()`:

```go
// ❌ Negated comparison
if !v.IsValid() {
    return variantLabels[Invalid]
}

// ✅ Positive counterpart
if v.IsInvalid() {
    return variantLabels[Invalid]
}
```

## 3. Idiomatic Go Exemptions

The following patterns are **exempt** from negation elimination:

### 3.1 — Comma-ok Pattern

```go
// ✅ Exempt — idiomatic Go
value, ok := someMap[key]
if !ok {
    return ErrNotFound
}
```

### 3.2 — Handler Guard Returns

Early-return guards in HTTP handlers that return false on failure:

```go
// ✅ Exempt — handler guard pattern
if !requireService(w, Services.SyncService, "Sync service") {
    return
}
if !decodeJSON(w, r, &input) {
    return
}
```

### 3.3 — Error-nil Check

```go
// ✅ Exempt — idiomatic Go
if err != nil {
    return err
}
```

### 3.4 — Standard Library Returns

Direct `!` on stdlib function returns where no wrapper exists:

```go
// ✅ Exempt — stdlib call
if !strings.HasPrefix(path, "/api/") {
    return
}
```

However, if the same stdlib negation appears 3+ times, extract a named boolean or helper:

```go
// When repeated, extract:
isNonApiRoute := !strings.HasPrefix(r.URL.Path, "/api/")
if isNonApiRoute {
    next.ServeHTTP(w, r)
    return
}
```

### 3.5 — Auto-Generated Enum ByIndex Bounds Check

The `ByIndex` function in all `enums/*/Variant.go` files uses a raw `||` with `<` and `>=` comparisons for bounds checking. This is exempt because it is an auto-generated, mechanical pattern identical across all 24+ enum packages:

```go
// ✅ Exempt — auto-generated enum bounds check
func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantLabels) {
		return Invalid
	}
	return Variant(i)
}
```

### 3.6 — File-Not-Found Error Guard

The `err != nil && !os.IsNotExist(err)` pattern is exempt because it's an idiomatic Go error-filtering pattern (ignore "file not found", act on real errors):

```go
// ✅ Exempt — idiomatic file cleanup guard
if err := os.Remove(path); err != nil && !os.IsNotExist(err) {
    log.Warn("Failed to remove file", "error", err)
}
```

## 4. Variable Naming Rules

| Pattern | Example | Status |
|---------|---------|--------|
| `is` + PositiveAdjective | `isValid`, `isActive`, `isReady` | ✅ Required |
| `has` + PositiveNoun | `hasPermission`, `hasRows`, `hasError` | ✅ Required |
| `is` + NegativeResult | `isDirMissing`, `isMkdirFailed` | ✅ Permitted |
| `not` prefix | `notFound`, `notReady` | ❌ Prohibited |
| `no` prefix | `noResults`, `noPermission` | ❌ Prohibited |

## 5. Mixed-Polarity Conditions (Rule P6)

Compound boolean expressions that mix positive and negative checks (`a && !b`, `!a || b`) are **prohibited**. They must be refactored into named variables with clear positive semantics.

### 5.1 — What Counts as Mixed-Polarity

Any `if` or assignment that combines `!` negation with non-negated terms in a single expression:

```go
// ❌ PROHIBITED — mixed polarity (positive + negative in one expression)
if user.IsAdmin() && !request.IsInternal() { ... }
if !isDryRun && totalDeleted > 0 { ... }
if config.IsEnabled() && !cache.HasEntry(key) { ... }
if resp.StatusCode < 400 && !resp.Success { ... }
```

### 5.2 — Fix: Extract Named Boolean

Replace with a single named boolean that captures the combined meaning:

```go
// ✅ CORRECT — named variable with positive semantics
isExternalAdmin := user.IsAdmin() && request.IsExternal()

if isExternalAdmin { ... }

isLiveRun    := !isDryRun
hasDeletedItems := totalDeleted > 0
isLiveRunWithDeletions := isLiveRun && hasDeletedItems

if isLiveRunWithDeletions { ... }

isEnabledButUncached := config.IsEnabled() && cache.IsMissing(key)

if isEnabledButUncached { ... }

hasPartialFailure := resp.IsSuccessStatus() && resp.IsUnsuccessful()

if hasPartialFailure { ... }
```

### 5.3 — Fix: Use Positive Counterpart Method

When a positive counterpart exists (see §2.2 inventory), use it to eliminate the `!`:

```go
// ❌ Mixed polarity with negated method
if config.IsEnabled() && !pathutil.IsDir(exportDir) {
    createDir(exportDir)
}

// ✅ Use positive counterpart
isEnabledWithMissingDir := config.IsEnabled() && pathutil.IsDirMissing(exportDir)
if isEnabledWithMissingDir {
    createDir(exportDir)
}
```

### 5.4 — Exemptions

Mixed-polarity is **permitted** in these idiomatic patterns (already covered in §3) and auto-generated code (§3.5):

```go
// ✅ Exempt — error-nil + stdlib negation (§3.5)
if err != nil && !os.IsNotExist(err) { ... }

// ✅ Exempt — comma-ok pattern (§3.1)
val, ok := m[key]
if !ok { ... }
```

### 5.5 — Real-World Examples from Codebase

**Before (SnapshotCleaner.php → Go port):**
```go
// ❌ Mixed polarity
if !isDryRun && totalDeleted > 0 {
    s.logCleanupAudit(results)
}
```

**After:**
```go
// ✅ Named booleans — no raw comparisons in compound expression
isLiveRun       := !isDryRun
hasDeletedItems := totalDeleted > 0
isLiveRunWithDeletions := isLiveRun && hasDeletedItems

if isLiveRunWithDeletions {
    s.logCleanupAudit(results)
}
```

**Before (publish handler):**
```go
// ❌ Mixed polarity
if parseErr != nil || !mode.IsDefined() {
    mode = publishtype.Full
}
```

**After:**
```go
// ✅ Positive counterpart + named var
isInvalidMode := parseErr != nil || mode.IsUndefined()
if isInvalidMode {
    mode = publishtype.Full
}
```

## 6. Enforcement

- **Automated**: `scripts/lint-negative.sh` flags `IsNot*`, `HasNo*` function declarations
- **Manual review**: Inline `!` negation in compound boolean expressions; mixed-polarity conditions (P6)
- **Enum exemption**: Variant checkers matching their constant name (e.g., `IsNotFound` for `NotFound` variant) are auto-excluded

## 7. Cross-Language Alignment

This standard mirrors the PHP Boolean Guard System (P1–P6) with Go-specific exemptions for idiomatic patterns (comma-ok, handler guards, error-nil checks). See `spec/06-php-standards/naming-conventions.md` for the PHP counterpart.
