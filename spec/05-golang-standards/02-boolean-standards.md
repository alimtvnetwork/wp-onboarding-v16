# Go Boolean Standards — Positive Logic & Naming

> **Version**: 1.0.0
> **Last updated**: 2026-02-23

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

### 3.5 — File-Not-Found Error Guard

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

## 5. Enforcement

- **Automated**: `scripts/lint-negative.sh` flags `IsNot*`, `HasNo*` function declarations
- **Manual review**: Inline `!` negation in compound boolean expressions
- **Enum exemption**: Variant checkers matching their constant name (e.g., `IsNotFound` for `NotFound` variant) are auto-excluded

## 6. Cross-Language Alignment

This standard mirrors the PHP Boolean Guard System (P1–P6) with Go-specific exemptions for idiomatic patterns (comma-ok, handler guards, error-nil checks). See `spec/06-php-standards/naming-conventions.md` for the PHP counterpart.
