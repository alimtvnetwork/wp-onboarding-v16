# Memory: architecture/coding-standards/result-error-type-invariant
Updated: 2026-02-23

---

Result wrappers across Go and PHP must maintain a cross-language invariant: the `.AppError()` (Go) and `.error()` (PHP) methods must return the framework's structured error type (`*apperror.AppError` or `Throwable`) rather than raw strings or `error` interfaces.

In Go, **all** result types — both `dbutil` (`Result[T]`, `ResultSet[T]`, `ExecResult`) and `apperror` (`Result[T]`, `ResultSlice[T]`, `ResultMap[K, V]`) — store and return `*apperror.AppError` from their `.AppError()` method. This ensures:

1. Stack traces, error codes, and diagnostic context are preserved during propagation
2. Type-safe error forwarding to `apperror.Fail[T]()` / `apperror.FailSlice[T]()` without interface casts
3. Bridge methods (`ToAppResult()`, `ToAppResultSlice()`) can convert between `dbutil` and `apperror` wrappers without type assertions

The `.AppError()` name (not `.Error()`) avoids collision with Go's native `error` interface method `.Error() string`.
