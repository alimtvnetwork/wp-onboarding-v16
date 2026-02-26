# Memory: architecture/coding-standards/result-error-type-invariant
Updated: 2026-02-26

---

Result wrappers across Go and PHP must maintain a cross-language invariant: the `.AppError()` (Go) and `.error()` (PHP) methods must return the framework's structured error type (`*apperror.AppError` or `Throwable`) rather than raw strings or `error` interfaces.

In Go, **all** result types — both `dbutil` (`Result[T]`, `ResultSet[T]`, `ExecResult`) and `apperror` (`Result[T]`, `ResultSlice[T]`, `ResultMap[K, V]`) — store and return `*apperror.AppError` from their `.AppError()` method. This ensures:

1. Stack traces, error codes, and diagnostic context are preserved during propagation
2. Type-safe error forwarding to `apperror.Fail[T]()` / `apperror.FailSlice[T]()` without interface casts
3. Bridge methods (`ToAppResult()`, `ToAppResultSlice()`) can convert between `dbutil` and `apperror` wrappers without type assertions

## Serializability Invariant

`*apperror.AppError` MUST be fully serializable (JSON round-trip) in every language:

- **Go**: Custom `MarshalJSON()` / `UnmarshalJSON()` in `error_json.go` handle the `Cause` field (which is `error` interface for `errors.Unwrap()` compat) by converting it to/from a plain string. All other fields (`Code`, `Message`, `Details`, `Values`, `Diagnostic`, `Stack`) serialize natively.
- **PHP**: The structured error type must implement `JsonSerializable` and support reconstruction from decoded JSON.

This means `*apperror.AppError` can be:
1. Serialized to JSON/YAML and stored (logs, queues, databases)
2. Transmitted across network boundaries (HTTP responses, RPC)
3. Deserialized back into a fully functional `*apperror.AppError` with all diagnostic context intact

**Struct fields that hold errors MUST use `*apperror.AppError`** (not raw `error`) so the error is serializable alongside the parent struct. The only exception is `AppError.Cause` itself, which uses the `error` interface for Go stdlib `errors.Unwrap()` compatibility — but this is handled by custom JSON marshaling.

The `.AppError()` name (not `.Error()`) avoids collision with Go's native `error` interface method `.Error() string`.
