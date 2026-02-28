# Memory: architecture/coding-standards/strict-no-raw-error

Updated: 2026-02-28

---

## STRICT RULE: No Raw `error` in Go Application Code

**Priority: HIGHEST — This rule MUST NOT be violated under any circumstances.**

### The Rule

The Go `error` interface MUST NOT appear anywhere in application code — not as a return type, not as a parameter type, not as a struct field, not as a variable type. The ONLY exception is at the outermost framework boundary where Go's standard library or a third-party framework literally forces you to use the `error` interface (e.g., `http.Handler`, `json.Unmarshaler`, `io.Reader`).

### What This Means

1. **Function return types**: MUST be `*apperror.AppError`, never `error`
2. **Interface method signatures**: MUST use `*apperror.AppError`, never `error`
3. **Struct fields**: MUST use `*apperror.AppError`, never `error` (see result-error-type-invariant)
4. **Variable declarations**: MUST use `*apperror.AppError`, never `error`
5. **Service layer**: ALL service methods return `*apperror.AppError` or `apperror.Result[T]`
6. **Adapter interfaces**: ALL adapter/handler interfaces return `*apperror.AppError`

### The Only Exception: Framework Boundary Compilation

When a framework literally requires `error` (e.g., handler factory callbacks that must return `error` to satisfy Go's type system), you MUST:

1. Use `appErr.CompiledError()` to cross the boundary — this produces a plain `error` containing the **full diagnostic string** (stack trace, error codes, values, cause chain)
2. NEVER return a typed nil `*apperror.AppError` through an `error` interface (nil-interface trap)
3. Always use explicit nil checking before the boundary:

```go
// CORRECT: Compilation at framework boundary
func(ctx context.Context, id int64) error {
    appErr := service.Delete(ctx, id) // returns *apperror.AppError
    if appErr != nil {
        return appErr.CompiledError() // Full diagnostics preserved
    }
    return nil // Untyped nil — safe
}
```

```go
// WRONG: Raw error return (NEVER DO THIS)
func(ctx context.Context, id int64) error {
    return service.Delete(ctx, id) // Nil-interface trap!
}
```

### Why This Matters

- Raw `error` loses stack traces, error codes, and diagnostic context
- The nil-interface trap causes `if err != nil` to be `true` even when there's no actual error
- `*apperror.AppError` is fully serializable (JSON round-trip) — raw `error` is not
- Error propagation without `*apperror.AppError` is silent data loss

### Violation Examples (ALL PROHIBITED)

```go
// ❌ WRONG: Service method returning error
func (s *Service) Delete(ctx context.Context, id int64) error { ... }

// ❌ WRONG: Interface with error return
type MyInterface interface {
    DoWork() error
}

// ❌ WRONG: Passing *apperror.AppError through error interface
func wrapper() error {
    return s.internalMethod() // returns *apperror.AppError — NIL TRAP!
}
```

### Correct Examples

```go
// ✅ CORRECT: Service method
func (s *Service) Delete(ctx context.Context, id int64) *apperror.AppError { ... }

// ✅ CORRECT: Interface
type MyInterface interface {
    DoWork() *apperror.AppError
}

// ✅ CORRECT: Framework boundary with compilation
func frameworkCallback() error {
    appErr := s.internalMethod()
    if appErr != nil {
        return appErr.CompiledError()
    }
    return nil
}
```

### Immediate Wrapping Rule

When calling stdlib/framework functions that return `error`, wrap immediately:

```go
file, err := os.Open(path)
if err != nil {
    return apperror.Wrap(err, apperror.ErrFSRead, "open file").WithPath(path)
}
```

Never propagate the raw `error` further up the call chain.
