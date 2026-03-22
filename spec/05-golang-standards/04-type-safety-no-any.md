# Go Type Safety: No `any` or `interface{}` Policy

> **Spec ID:** GO-TYPE-SAFETY-001  
> **Created:** 2026-03-22  
> **Status:** Active

---

## Rule

**`any` and `interface{}` are prohibited in production Go code.** Every variable, parameter, return type, and struct field must use a specific type or a bounded generic (`T comparable`, `T fmt.Stringer`, etc.).

### Exceptions

| Exception | Allowed Pattern | Rationale |
|-----------|----------------|-----------|
| File I/O / JSON unmarshalling | `any` as initial decode target | Unknown structure from external sources |
| Test files (`_test.go`) | Unrestricted | Test flexibility |
| Third-party library boundaries | Match library signatures | Can't control external APIs |

### Prohibited Patterns

```go
// ❌ BAD: Untyped return
func GetData() (any, error) { ... }

// ❌ BAD: Untyped map
func Process(data map[string]any) { ... }

// ❌ BAD: Untyped slice
func Collect() []any { ... }

// ❌ BAD: Untyped struct field
type Response struct {
    Data any `json:"data"`
}

// ❌ BAD: Untyped function parameter
func Broadcast(event string, data any) { ... }
```

### Required Patterns

```go
// ✅ GOOD: Typed return
func GetSettings(ctx context.Context, siteId int64) (*SiteSettings, *apperror.AppError) { ... }

// ✅ GOOD: Typed struct
type SiteHealthResponse struct {
    WpVersion  string `json:"wpVersion"`
    PhpVersion string `json:"phpVersion"`
    DbStatus   bool   `json:"dbAvailable"`
}

// ✅ GOOD: Generic when type varies legitimately
func WrapResult[T any](data T) Result[T] { ... }

// ✅ GOOD: Bounded generic
func FindById[T comparable](items []T, id T) (T, bool) { ... }

// ✅ GOOD: Interface with methods (not empty interface)
type Broadcaster interface {
    Send(event string, payload EventPayload)
}
```

### Generic Result Pattern

Replace `(any, *apperror.AppError)` returns with:

```go
// Typed result — eliminates any from handler signatures
type Result[T any] struct {
    Value T
    Err   *apperror.AppError
}

// For handler factories
type HandlerFunc[T any] func(ctx context.Context) (T, *apperror.AppError)
type SiteHandlerFunc[T any] func(ctx context.Context, siteId int64) (T, *apperror.AppError)
```

---

## Current Violations (2026-03-22)

| Category | Count | Files | Priority |
|----------|-------|-------|----------|
| Handler factory generics (`func() any`, `(any, *AppError)`) | ~22 | HandlerFactory.go, HandlerFactoryGetters.go | 🔴 High |
| Adapter interface returns (`(any, *AppError)`) | ~27 | AdapterSite.go | 🔴 High |
| Response/envelope types (`Data any`) | ~22 | Response.go, ResponseTypes.go, Envelope.go | 🔴 High |
| `map[string]any` (JSON payloads) | ~28 | Various services | 🟡 Medium |
| `[]any` (slices) | ~36 | Various | 🟡 Medium |
| `pkg/apperror` Result types | ~22 | Result.go, ResultMap.go, ResultSlice.go | 🟡 Medium |
| `pkg/dbutil` query helpers | ~16 | Query.go, Result.go, ResultSet.go | 🟡 Medium |
| WebSocket broadcast | ~8 | Hub.go, EventTypes.go | 🟢 Low |
| Logger/config | ~5 | Logger.go, ConfigHelpers.go | 🟢 Low |
| WordPress client | ~10 | Client.go, ClientApiCall.go | 🟢 Low |

**Total: ~259 occurrences across 88 files**

---

## Refactoring Strategy

### Phase 1: Core Infrastructure (pkg/)
Define typed generics in `apperror.Result[T]`, `dbutil.TypedResult[T]` so downstream code can adopt them.

### Phase 2: Response & Envelope Layer
Replace `Data any` in response structs with generic `Data T`.

### Phase 3: Handler Factory
Convert `HandlerFactory.go` to use generic handler functions.

### Phase 4: Adapter Interfaces
Replace all `(any, *AppError)` returns with typed returns per domain.

### Phase 5: Service Layer
Replace `map[string]any` and `[]any` with typed structs.

### Phase 6: WordPress Client & WebSocket
Type the remaining infrastructure code.

---

*Reference: `.lovable/plan.md` Phase G (Type Safety)*
