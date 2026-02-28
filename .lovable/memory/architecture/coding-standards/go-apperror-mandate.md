# Memory: architecture/coding-standards/go-apperror-mandate
Updated: 2026-02-28

---

All Go application code MUST use `*apperror.AppError` instead of the raw `error` interface. The only place where `error` is acceptable is at the boundary with framework/stdlib methods that return `error` — but those MUST be immediately wrapped into `*apperror.AppError` at the call site using `apperror.Wrap()` or equivalent.

Key rules:

1. **No raw `error` propagation**: Functions must return `*apperror.AppError`, not `error`. The only exception is interface implementations required by Go stdlib (e.g., `json.Unmarshaler`).
2. **Immediate wrapping**: When calling framework/external functions that return `error`, wrap the result into `*apperror.AppError` on the very next line — never pass raw `error` further up the call chain.
3. **Positive guard checks**: Use `IsDefined()` (not `!IsInvalid()`) and `IsDefinedAndValid()` for enum/result validation. Never negate a negative method.
4. **Result types**: All result wrappers (`dbutil.Result[T]`, `apperror.Result[T]`, etc.) store and return `*apperror.AppError` from `.AppError()`.
5. **CompiledError()**: When `*apperror.AppError` must cross into the `error` interface boundary (e.g., final HTTP response, CLI output), use `appErr.CompiledError()` which returns a plain `error` containing the full diagnostic string (stack trace, values, cause chain). This is the ONLY sanctioned `AppError→error` conversion.
6. **NormalizePluginSlug()**: Use `apperror.NormalizePluginSlug(slug)` for slug validation/normalization. Returns `(string, *apperror.AppError)`.
7. **Serializability**: `*apperror.AppError` is fully JSON-serializable via custom `MarshalJSON()`/`UnmarshalJSON()`. It can be marshaled, stored, transmitted, and deserialized back with all diagnostic context intact. **Struct fields that hold errors MUST use `*apperror.AppError`** (not raw `error`) so the error serializes alongside the parent struct. The sole exception is `AppError.Cause` which uses `error` interface for `errors.Unwrap()` compat, handled by custom JSON marshaling.

This ensures stack traces, error codes, and diagnostic context are always preserved and transportable across process/network boundaries.

## Path and URL Constants (§13)

All URL paths, API route patterns, and path fragments MUST use typed constants or builder functions from the `wordpress` package. Hardcoded strings like `"/wp-json"`, `"/api/v1/sites"`, or `"/mutations/%s/plugins/upload"` in business logic are forbidden.

Builder functions: `BuildWPJSONURL()`, `BuildWPPluginURL()`, `BuildWPProbeURL()`, `BuildNamespacedEndpoint()`, `OnboardMutationUploadEndpoint()`, `GoAPISitePluginRoute()`.

When path construction results in an error (HTTP 404, connection refused, etc.), the error MUST include the resolved URL, all interpolated variable values, and the endpoint constant used.
