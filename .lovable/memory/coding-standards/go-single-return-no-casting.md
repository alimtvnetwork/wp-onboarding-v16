# Memory: coding-standards/go-single-return-no-casting
Updated: 2026-02-26 (expanded with comprehensive examples)

---

## Rule 1: Single Return Value

Go functions MUST return at most **one value**. If a function needs to return data + error, use `apperror.Result[T]`. If a function needs to return multiple data fields, create a **named struct** and return that single struct (or wrap it in `apperror.Result[T]`).

### Why

Multiple return values create fragile call sites, make refactoring harder, and obscure the semantic meaning of each positional value. A single typed struct is self-documenting and extensible.

### Patterns

```go
// ❌ WRONG — multiple return values
func Upload(zip string) (bool, *UploadResult, bool, *apperror.AppError) { ... }
func GetCredentials(id int64) (*Site, string, error) { ... }

// ✅ CORRECT — single typed struct inside Result wrapper
type UploadOutcome struct {
    IsPerformed  bool
    Result       *UploadResult
    IsActivated  bool
}
func Upload(zip string) apperror.Result[UploadOutcome] { ... }

// ✅ CORRECT — single typed struct with plain error
type SiteCredentials struct {
    Site     *Site
    Password string
}
func GetCredentials(id int64) (*SiteCredentials, error) { ... }
```

### Allowed exceptions

1. **`(T, error)` tuple** — the standard Go convention for a single value + error is allowed (but prefer `apperror.Result[T]` in service layers).
2. **`(T, bool)` tuple** — the standard Go map/channel/type-assertion "comma ok" idiom is allowed for lookup methods like `Get(key) (T, bool)`.

### Private helper functions

Even private/unexported functions must follow this rule. If a helper returns `(string, int, error)`, wrap those in a struct.

---

## Rule 2: No Type Assertions (No Casting)

Code MUST NOT use inline Go type assertions (`value.(ConcreteType)`) to inspect error types or extract concrete implementations. Use centralized extraction functions instead.

### Why

Scattered type assertions couple callers to concrete types, are easy to forget, and bypass the type system. Centralized extractors provide a single place to handle type narrowing.

### Patterns

```go
// ❌ WRONG — inline type assertion
if appErr, ok := err.(*apperror.AppError); ok {
    sessionId = appErr.Diagnostic.SessionId
}

// ✅ CORRECT — centralized extractor
if appErr := apperror.Extract(err); appErr != nil {
    sessionId = appErr.Diagnostic.SessionId
}

// ❌ WRONG — inline assertion on APIError
if apiErr, ok := err.(*wordpress.APIError); ok {
    status = apiErr.StatusCode
}

// ✅ CORRECT — centralized extractor
if apiErr := wordpress.ExtractAPIError(err); apiErr != nil {
    status = apiErr.StatusCode
}
```

### Allowed exceptions

1. **Go stdlib interface compliance** — `rw.ResponseWriter.(http.Flusher)`, `rw.ResponseWriter.(http.Hijacker)`, etc. These are required by the Go HTTP spec and cannot be avoided.
2. **`context.Value()` extraction** — `ctx.Value(key).(string)` is the standard Go context pattern.
3. **`net.Error` check** — `cause.(net.Error)` for network error detection is standard Go.
4. **Inside the `apperror` and `wordpress` packages themselves** — the `Extract()`, `Is()`, `Recover()`, and `ExtractAPIError()` functions are the designated centralized extraction points. They internally use type assertions, which is their purpose.
5. **`errors.As()` / `errors.Is()`** — standard Go error unwrapping is allowed.
