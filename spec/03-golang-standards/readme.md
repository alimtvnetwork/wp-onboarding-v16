# Golang Coding Standards

> **Version:** 3.0.0  
> **Updated:** 2026-02-20  
> **Applies to:** All Go backend code

---

## File Size — Max 300 Lines

Every `.go` file must be **300 lines or fewer**. Split large files using these suffixes:

| Suffix | Purpose |
|--------|---------|
| `entity.go` | Struct + constructors |
| `entity_crud.go` | Database operations |
| `entity_helpers.go` | Private utilities |
| `entity_validation.go` | Validation logic |

---

## Function Size — Max 15 Lines

> **Canonical source:** [Cross-Language Code Style](../01-coding-guidelines/code-style.md) — Rule 6

Every function body must be **15 lines or fewer**. Extract logic into small, well-named helpers.

```go
// ❌ FORBIDDEN: Long function
func ProcessUpload(ctx context.Context, req UploadRequest) error {
    // 20+ lines of validation, upload, logging...
}

// ✅ REQUIRED: Decomposed
func ProcessUpload(ctx context.Context, req UploadRequest) error {
    if err := validateUpload(req); err != nil {
        return err
    }

    result, err := executeUpload(ctx, req)
    if err != nil {
        return apperror.Wrap(err, "E5001", "upload failed")
    }

    return logAndRespond(ctx, result)
}
```

---

## Zero Nested `if` — Absolute Ban

> **Canonical source:** [Cross-Language Code Style](../01-coding-guidelines/code-style.md) — Rule 2 & 7

Nested `if` blocks are **absolutely forbidden** — zero tolerance. Flatten with combined conditions or early returns.

```go
// ❌ FORBIDDEN
if err != nil {
    if resp != nil {
        handleError(resp)
    }
}

// ✅ REQUIRED
if err != nil && resp != nil {
    handleError(resp)
}
```

---

## Type Safety — No `interface{}` or `any`

### Rule: Never use `interface{}` or `any` in exported APIs

```go
// ❌ FORBIDDEN
func ProcessData(data interface{}) interface{} { ... }
func FetchResults() (any, error) { ... }

// ✅ REQUIRED: Use concrete types or generics
func ProcessData(data PluginDetails) (PluginSummary, error) { ... }
func FetchResults[T any]() (T, error) { ... }
```

### Acceptable `any` Usage

1. **SQL query arguments** — `args ...any` in `dbutil` (framework boundary)
2. **Logger variadic parameters** — `map[string]any` for structured log fields (internal only)
3. **Third-party library interfaces** — When a library requires `interface{}`

---

## Error Handling — `apperror` Package

### Rule: Every error carries a mandatory stack trace

All errors created via `apperror.New()` or `apperror.Wrap()` automatically capture a full `StackTrace` at creation — no opt-in needed.

```go
// ❌ FORBIDDEN: loses stack trace
return fmt.Errorf("failed to upload: %w", err)

// ✅ REQUIRED: full stack trace captured automatically
return apperror.Wrap(err, "E5001", "failed to upload plugin")
```

### StackTrace Type

```go
// Captured automatically — structured frames, not raw strings
type StackFrame struct {
    Function string
    File     string
    Line     int
}
type StackTrace []StackFrame

// Display methods
trace.String()      // full formatted multi-line trace
trace.CallerLine()  // "file.go:42" — compact single line
trace.IsEmpty()     // no frames captured
trace.Depth()       // number of frames
```

### AppError Display Methods

```go
err.Error()       // "[E5001] upload failed" — implements error interface
err.FullString()  // code + message + diagnostics + stack + cause chain
err.ToClipboard() // markdown-formatted error report for AI paste
```

### Context Enrichment — Typed Diagnostic Setters

```go
// ✅ Enriched error with diagnostic context
return apperror.Wrap(err, "E5002", "remote site request failed").
    WithURL(requestURL).
    WithSlug(pluginSlug).
    WithStatusCode(resp.StatusCode).
    WithSiteID(siteID)
```

### Error Code Convention

| Range | Category |
|-------|----------|
| E1xxx | Configuration errors |
| E2xxx | Database errors |
| E3xxx | WordPress API errors |
| E4xxx | File system errors |
| E5xxx | Sync errors |
| E6xxx | Backup errors |
| E7xxx | Git errors |
| E8xxx | Build errors |
| E9xxx | General errors |
| E10xxx | E2E test errors |
| E11xxx | Publish errors |
| E12xxx | Version errors |

---

## Generic Result Types — `apperror` Package

Three generic result types for all service returns. Replaces raw `(T, error)` tuples.

### `Result[T]` — Single Value

For operations that return one item or nothing.

```go
// Construction
result := apperror.Ok(plugin)             // success
result := apperror.Fail[Plugin](appErr)   // from AppError
result := apperror.FailWrap[Plugin](err, "E5001", "load failed")  // wrap raw error
result := apperror.FailNew[Plugin]("E4004", "not found")          // new error

// Query methods
result.HasError()    // true if operation failed
result.IsSafe()      // true if value exists AND no error
result.IsDefined()   // true if value was set
result.IsEmpty()     // true if no value was set

// Access methods
result.Value()             // returns T; panics if HasError
result.ValueOr(fallback)   // returns T or fallback if empty
result.Error()             // returns *AppError or nil
result.Unwrap()            // bridges to (T, error) pattern
```

### `ResultSlice[T]` — Collection (Array)

For operations that return lists of items.

```go
// Construction
set := apperror.OkSlice(plugins)
set := apperror.FailSlice[Plugin](appErr)
set := apperror.FailSliceWrap[Plugin](err, "E5011", "query failed")

// Query methods
set.HasError()     // true if operation failed
set.IsSafe()       // true if no error (items may be empty)
set.HasItems()     // true if at least one item
set.IsEmpty()      // true if zero items
set.Count()        // number of items

// Access methods
set.Items()        // returns []T (nil if error)
set.First()        // Result[T] for first item
set.Last()         // Result[T] for last item
set.GetAt(index)   // Result[T] at index; empty if out of bounds
set.Error()        // returns *AppError or nil

// Mutation methods
set.Append(items...)  // adds items; no-op if in error state
```

### `ResultMap[K, V]` — Associative Map

For operations that return key-value data.

```go
// Construction
m := apperror.OkMap(pluginsBySlug)
m := apperror.FailMap[string, Plugin](appErr)
m := apperror.FailMapWrap[string, Plugin](err, "E5012", "index failed")

// Query methods
m.HasError()     // true if operation failed
m.IsSafe()       // true if no error (map may be empty)
m.HasItems()     // true if at least one entry
m.IsEmpty()      // true if zero entries
m.Count()        // number of entries
m.Has(key)       // true if key exists

// Access methods
m.Items()        // returns map[K]V (nil if error)
m.Get(key)       // Result[V] for key; empty if not found
m.Keys()         // returns []K
m.Values()       // returns []V
m.Error()        // returns *AppError or nil

// Mutation methods
m.Set(key, value)   // adds/updates; no-op if error state
m.Remove(key)       // deletes key; no-op if error state
```

### Service Usage Pattern

```go
// ✅ Service method returning Result[T]
func (s *PluginService) GetByID(ctx context.Context, id int64) apperror.Result[Plugin] {
    dbResult := dbutil.QueryOne[Plugin](ctx, s.db, query, scanPlugin, id)
    if dbResult.HasError() {
        return apperror.FailWrap[Plugin](dbResult.Error(), ErrPluginGet, "get plugin by ID")
    }
    if dbResult.IsEmpty() {
        return apperror.FailNew[Plugin](ErrNotFound, "plugin not found")
    }

    return apperror.Ok(dbResult.Value())
}

// ✅ Service method returning ResultSlice[T]
func (s *SiteService) ListAll(ctx context.Context) apperror.ResultSlice[Site] {
    dbResult := dbutil.QueryMany[Site](ctx, s.db, query, scanSite)
    if dbResult.HasError() {
        return apperror.FailSliceWrap[Site](dbResult.Error(), ErrSiteList, "list sites")
    }

    return apperror.OkSlice(dbResult.Items())
}

// ✅ Handler consuming Result[T]
func (h *Handler) GetPlugin(w http.ResponseWriter, r *http.Request) {
    result := h.plugins.GetByID(r.Context(), pluginID)
    if result.HasError() {
        writeError(w, result.Error())
        return
    }

    writeJSON(w, result.Value())
}
```

---

## Database Wrapper — `pkg/dbutil`

All database queries MUST use the generic `dbutil` package. Returns typed result envelopes with automatic `apperror` stack traces.

### Result Types

| Type | Purpose | Key Methods |
|------|---------|-------------|
| `Result[T]` | Single-row query | `IsDefined()`, `IsEmpty()`, `HasError()`, `IsSafe()`, `Value()`, `Error()`, `StackTrace()` |
| `ResultSet[T]` | Multi-row query | `HasAny()`, `IsEmpty()`, `Count()`, `HasError()`, `IsSafe()`, `Items()`, `First()`, `Error()`, `StackTrace()` |
| `ExecResult` | INSERT/UPDATE/DELETE | `IsEmpty()`, `HasError()`, `IsSafe()`, `AffectedRows`, `LastInsertID`, `Error()`, `StackTrace()` |

### Generic Query Functions

```go
// Single row — returns Result[T]
result := dbutil.QueryOne[Plugin](ctx, db, query, scanPlugin, pluginID)

// Multiple rows — returns ResultSet[T]
set := dbutil.QueryMany[Site](ctx, db, query, scanSite)

// Exec — returns ExecResult
res := dbutil.Exec(ctx, db, query, args...)
```

---

## Struct Design

### JSON Tags — PascalCase Convention

All structs used in API responses must have explicit JSON tags with PascalCase keys:

```go
type PluginDetails struct {
    ID        int    `json:"Id"`
    Name      string `json:"Name"`
    Slug      string `json:"Slug"`
    Version   string `json:"Version"`
    IsActive  bool   `json:"IsActive"`
    UpdatedAt string `json:"UpdatedAt,omitempty"`
}
```

### Function Parameters — Max 2-3

Functions should have **2-3 parameters maximum**. Use config/options structs for more:

```go
// ❌ Bad: Too many parameters
func StartSession(sessionType SessionType, pluginID, siteID int64, pluginName, siteName string) (string, error)

// ✅ Good: Use a struct
type StartSessionInput struct {
    Type       SessionType
    PluginID   int64
    SiteID     int64
    PluginName string
    SiteName   string
}
func StartSession(input StartSessionInput) (string, error)

// ✅ Acceptable: 2-3 essential parameters (context doesn't count)
func GetByID(ctx context.Context, id int64) (*Model, error)
```

---

## Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Package names | Lowercase, single word | `wordpress`, `publish`, `apperror` |
| Exported functions | PascalCase, verb-led | `EnablePlugin`, `FetchStatus` |
| Unexported functions | camelCase, verb-led | `resolveNamespace`, `parseStackTrace` |
| Interfaces | PascalCase, `-er` suffix for single-method | `Publisher`, `PluginStore` |
| Constants | PascalCase | `MaxRetryAttempts`, `DefaultTimeout` |
| Error variables | `Err` prefix | `ErrPluginNotFound`, `ErrUploadFailed` |
| Boolean functions | Positive naming only | `IsValid()`, `HasPermission()` |

---

## No Raw Negations — Use Positive Guard Functions

> **Canonical source:** [No Raw Negations](../01-coding-guidelines/no-negatives.md)

```go
// ❌ FORBIDDEN
if !fileExists(path) { ... }
if !strings.Contains(s, substr) { ... }

// ✅ REQUIRED
if IsFileMissing(path) { ... }
if IsMissingSubstring(s, substr) { ... }
```

---

## Typed Constants & Enums

### String-Backed Types

```go
type StatusType string

const (
    StatusActive   StatusType = "active"
    StatusInactive StatusType = "inactive"
    StatusPending  StatusType = "pending"
)

func (s StatusType) String() string { return string(s) }
func (s StatusType) IsValid() bool  { /* lookup map */ }
func (s StatusType) IsOtherThan(other StatusType) bool { return s != other }
```

### Iota Enums

```go
type LogLevel int

const (
    LogDebug LogLevel = iota
    LogInfo
    LogWarn
    LogError
)

func (l LogLevel) String() string {
    return [...]string{"debug", "info", "warn", "error"}[l]
}
```

### Zero Magic Strings/Numbers

- All HTTP status codes → typed constants
- All error codes → `apperror` code constants
- All config keys → typed const block
- All status/event strings → typed `StringType` constants

---

## DRY Enforcement

| Pattern | Solution |
|---------|----------|
| Repeated error handling | `apperror.Result[T]` or helper functions |
| Repeated JSON key access | Typed response structs |
| Repeated validation | `Validate()` method on input structs |
| Repeated DB patterns | `dbutil` generic wrappers |
| Repeated string constants | Typed const blocks with `Type` suffix |

---

## Concurrency Patterns

### `sync.Once` for Lazy Initialization

```go
var (
    openAPISpec     []byte
    openAPISpecOnce sync.Once
)

func GetOpenAPISpec() []byte {
    openAPISpecOnce.Do(func() {
        openAPISpec, _ = os.ReadFile("api/openapi.json")
    })
    return openAPISpec
}
```

### Context Propagation

All long-running operations must accept `context.Context`:

```go
func (s *PublishService) Upload(ctx context.Context, req UploadRequest) error { ... }
```

---

## Forbidden Patterns

| Pattern | Why | Alternative |
|---------|-----|-------------|
| `interface{}` / `any` in exported APIs | Untyped | Concrete types or generics |
| `fmt.Errorf` for service errors | No stack trace | `apperror.Wrap` |
| Panic in handlers | Crashes server | Return error |
| `init()` functions | Hidden side effects | Explicit initialization |
| Global mutable state | Race conditions | Dependency injection |
| `map[string]interface{}` in APIs | Untyped | Defined structs |
| Raw `(T, error)` from services | No semantic methods | `apperror.Result[T]` |
| `!fn()` raw negation | Easy to miss `!` | Positive guard function |
| Nested `if` (any depth) | **Zero tolerance** | Flatten with early returns |
| Functions > 15 lines | Hard to read | Extract small helpers |
| Files > 300 lines | Hard to navigate | Split with suffix convention |
| Magic strings/numbers | Brittle | Typed constants |
| Boolean flag parameters | Unclear intent | Separate named methods |

---

## Import Organization — 3 Groups

```go
import (
    // stdlib
    "context"
    "fmt"

    // internal packages
    "project/pkg/apperror"
    "project/internal/domain"

    // third-party
    "github.com/lib/pq"
)
```

---

## Cross-References

- [No Raw Negations](../01-coding-guidelines/no-negatives.md) — Positive guard functions (all languages)
- [Cross-Language Code Style](../01-coding-guidelines/code-style.md) — Braces, nesting & spacing rules
- [Function Naming](../01-coding-guidelines/function-naming.md) — No boolean flag parameters
- [Strict Typing](../01-coding-guidelines/strict-typing.md) — Type declarations & docblock rules
- [DRY Principles](../01-coding-guidelines/dry-principles.md)
- [Go Function Parameters](.lovable/memory/architecture/coding-standards/go-function-parameters.md)

---

*Golang standards specification v3.0.0 — 2026-02-20*
