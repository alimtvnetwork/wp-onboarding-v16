# Go `apperror` Package Specification

> **Version:** 1.1.0  
> **Updated:** 2026-02-20  
> **Package:** `backend/pkg/apperror`

---

## Overview

The `apperror` package provides **structured application errors with mandatory stack traces** and **generic result wrappers** for all service-level returns. Every error created through this package automatically captures a stack trace at the point of creation. No error is ever swallowed or lost.

### Package Files

| File | Purpose | Target Lines |
|------|---------|--------------|
| `stack_trace.go` | StackFrame, StackTrace capture & formatting | ≤300 |
| `error.go` | AppError struct & constructors (New, Wrap) | ≤300 |
| `error_diagnostic.go` | ErrorDiagnostic struct & typed setters | ≤400 |
| `error_values.go` | Values map & WithValue/WithValues setters | ≤150 |
| `clipboard.go` | AI-friendly error report formatting | ≤200 |
| `match.go` | Error matching utilities | ≤50 |
| `codes.go` | Error code constants | ≤200 |
| `result.go` | Result[T] — single value wrapper | ≤150 |
| `result_slice.go` | ResultSlice[T] — collection wrapper | ≤150 |
| `result_map.go` | ResultMap[K, V] — associative map wrapper | ≤150 |

---

## 1. StackTrace

### 1.1 StackFrame

```go
type StackFrame struct {
    Function string `json:"function"`
    File     string `json:"file"`
    Line     int    `json:"line"`
}
```

**Methods:**
- `String() string` — formats as `"function\n      file:line"`

### 1.2 StackTrace Type

```go
type StackTrace struct {
    Frames        []StackFrame `json:"frames"`
    PreviousTrace string       `json:"previousTrace,omitempty"`
}
```

**Fields:**
- `Frames` — ordered list of captured stack frames
- `PreviousTrace` — when two stack traces are merged (e.g., re-wrapping an error), the original trace is stored here as a formatted string

### 1.3 Capture Functions

```go
// CaptureStack captures a stack trace, skipping `skip` caller frames.
// Maximum 18 frames are captured by default.
func CaptureStack(skip int) StackTrace

// CaptureStackN captures a stack trace with a custom max frame depth.
func CaptureStackN(skip int, maxFrames int) StackTrace
```

**Rules:**
- Default max frames: **18** (sufficient for most call chains)
- `skip` parameter: number of frames to skip from the top
  - `New()` and `Wrap()` use `skip=2` (skip `runtime.Callers` + constructor)
  - `FailWrap()`, `FailSliceWrap()`, `FailMapWrap()` use `skip=3` (skip wrapper + `Wrap` + `runtime.Callers`)
- Runtime frame filtering uses `strings.HasPrefix(fn, "runtime.")` (NOT `Contains`) to avoid false positives with domain functions

### 1.4 StackTrace Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `String()` | `string` | Full formatted multi-line trace including `PreviousTrace` |
| `CallerLine()` | `string` | Top frame as `"file:line"` — compact single line |
| `FinalLine()` | `string` | Bottom frame as `"file:line"` — deepest origin point |
| `IsEmpty()` | `bool` | True if no frames captured |
| `Depth()` | `int` | Number of captured frames |
| `HasPrevious()` | `bool` | True if a previous trace exists from merging |

### 1.5 Merging Behavior

When an `AppError` is re-wrapped (wrapping an error that already has a `StackTrace`), the original trace is preserved:

```go
// Original error with trace at line 42
original := apperror.New("E5001", "file not found")

// Re-wrapping preserves the original trace in PreviousTrace
wrapped := apperror.Wrap(original, "E5002", "upload failed")
// wrapped.Stack.HasPrevious() == true
// wrapped.Stack.PreviousTrace contains the original trace
```

---

## 2. AppError

### 2.1 Struct

```go
type AppError struct {
    Code       string            `json:"code"`
    Message    string            `json:"message"`
    Details    string            `json:"details,omitempty"`
    Values     map[string]string `json:"values,omitempty"`
    Diagnostic ErrorDiagnostic   `json:"diagnostic,omitempty"`
    Stack      StackTrace        `json:"stack"`
    Cause      error             `json:"-"`
}
```

**Fields:**
- `Code` — error code from constants (e.g., `ErrNotFound`, `ErrDatabaseQuery`)
- `Message` — human-readable error description
- `Details` — additional context (auto-set from cause on `Wrap`)
- `Values` — key-value map for injecting variables relevant to the error context (paths, IDs, names, etc.)
- `Diagnostic` — typed diagnostic fields for structured reporting
- `Stack` — mandatory stack trace captured at creation
- `Cause` — wrapped underlying error (implements `Unwrap()`)

### 2.2 Constructors

Every constructor captures a stack trace automatically. **Three things are always required: cause (or nil), code, and message.**

```go
// New creates a new AppError with code + message. Stack captured at caller.
func New(code, message string) *AppError

// NewWithSkip creates a new AppError with explicit skip for stack capture.
func NewWithSkip(code, message string, skip int) *AppError

// Wrap wraps an existing error with code + message. Stack captured at caller.
// If cause is an *AppError, its stack is preserved in PreviousTrace.
func Wrap(cause error, code, message string) *AppError

// WrapWithSkip wraps with explicit skip for stack capture.
func WrapWithSkip(cause error, code, message string, skip int) *AppError
```

### 2.3 Display Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `Error()` | `string` | `"[CODE] message"` — implements `error` interface |
| `FullString()` | `string` | Code + message + details + values + diagnostics + stack + cause chain |
| `String()` | `string` | Alias for `FullString()` — complete error representation |
| `ToClipboard()` | `string` | Markdown-formatted error report for AI paste |

### 2.4 Values — Variable Injection

Anytime an error occurs while working with a variable (path, ID, name, URL), that variable **must** be injected into the error's `Values` map so no context is lost.

```go
// WithValue adds a single key-value pair.
func (e *AppError) WithValue(key, value string) *AppError

// WithValues merges multiple key-value pairs.
func (e *AppError) WithValues(values map[string]string) *AppError
```

**Usage:**
```go
return apperror.Wrap(err, ErrFSRead, "failed to read plugin file").
    WithValue("path", filePath).
    WithValue("plugin", pluginSlug)
```

The `Values` map is included in `FullString()`, `String()`, and `ToClipboard()` output, compiling into a readable error message.

### 2.5 Flow Control Methods

```go
// Panic logs the full error and panics with the formatted message.
// Use ONLY for unrecoverable initialization failures.
func (e *AppError) Panic(message string)

// Throw panics with the AppError itself (recoverable via recover).
// The AppError can be extracted from the panic value.
func (e *AppError) Throw()
```

**Rules:**
- `Panic()` is reserved for startup/initialization failures only
- `Throw()` enables structured panic/recover patterns where the `AppError` is preserved
- Neither should be used in request handlers — return errors instead

### 2.6 Query Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `Unwrap()` | `error` | Returns cause for `errors.Is/As` |
| `Is(target)` | `bool` | True if error codes match |
| `HasCause()` | `bool` | True if a wrapped cause exists |
| `HasValues()` | `bool` | True if Values map is populated |
| `HasDiagnostic()` | `bool` | True if any diagnostic field is set |

### 2.7 Diagnostic Setters (Fluent)

All typed diagnostic setters return `*AppError` for chaining:

```go
err.WithPath(p string)
err.WithFile(f string)
err.WithFilePath(p string)
err.WithURL(u string)
err.WithSlug(s string)
err.WithSiteID(id int64)
err.WithPluginID(id int64)
err.WithStatusCode(code int)
err.WithMethod(m string)
err.WithEndpoint(ep string)
err.WithUsername(u string)
// ... etc (see error_diagnostic.go for full list)
```

---

## 3. Result[T] — Single Value Wrapper

For service methods that return one item or nothing.

### 3.1 Struct

```go
type Result[T any] struct {
    value   T
    err     *AppError
    defined bool
}
```

### 3.2 Constructors

```go
// Ok creates a successful Result containing the given value.
func Ok[T any](value T) Result[T]

// Fail creates a failed Result from an AppError.
func Fail[T any](err *AppError) Result[T]

// FailWrap creates a failed Result by wrapping a raw error.
// Uses skip=3 to point stack trace at caller, not this wrapper.
func FailWrap[T any](cause error, code, message string) Result[T]

// FailNew creates a failed Result from a new error (no cause).
func FailNew[T any](code, message string) Result[T]
```

### 3.3 Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `HasError()` | `bool` | True if operation failed |
| `IsSafe()` | `bool` | True if value exists AND no error |
| `IsDefined()` | `bool` | True if value was set (regardless of error) |
| `IsEmpty()` | `bool` | True if no value was set |
| `Value()` | `T` | Returns value; panics if `HasError()` |
| `ValueOr(fallback)` | `T` | Returns value if defined, else fallback |
| `Error()` | `*AppError` | Returns the AppError, or nil |
| `Unwrap()` | `(T, error)` | Bridges to standard `(T, error)` pattern |

---

## 4. ResultSlice[T] — Collection Wrapper

For service methods that return lists of items.

### 4.1 Struct

```go
type ResultSlice[T any] struct {
    items []T
    err   *AppError
}
```

### 4.2 Constructors

```go
func OkSlice[T any](items []T) ResultSlice[T]
func FailSlice[T any](err *AppError) ResultSlice[T]

// Uses skip=3 for correct stack trace attribution.
func FailSliceWrap[T any](cause error, code, message string) ResultSlice[T]
func FailSliceNew[T any](code, message string) ResultSlice[T]
```

### 4.3 Methods

| Category | Method | Returns | Description |
|----------|--------|---------|-------------|
| Query | `HasError()` | `bool` | True if operation failed |
| Query | `IsSafe()` | `bool` | True if no error (items may be empty) |
| Query | `HasItems()` | `bool` | True if at least one item |
| Query | `IsEmpty()` | `bool` | True if zero items |
| Query | `Count()` | `int` | Number of items |
| Access | `Items()` | `[]T` | Returns the slice (nil if error) |
| Access | `First()` | `Result[T]` | Result for first item; empty if none |
| Access | `Last()` | `Result[T]` | Result for last item; empty if none |
| Access | `GetAt(index)` | `Result[T]` | Result at index; empty if out of bounds |
| Access | `Error()` | `*AppError` | Returns the AppError, or nil |
| Mutate | `Append(items...)` | — | Adds items; no-op if in error state |

---

## 5. ResultMap[K, V] — Associative Map Wrapper

For service methods that return key-value data.

### 5.1 Struct

```go
type ResultMap[K comparable, V any] struct {
    items map[K]V
    err   *AppError
}
```

### 5.2 Constructors

```go
func OkMap[K comparable, V any](items map[K]V) ResultMap[K, V]
func FailMap[K comparable, V any](err *AppError) ResultMap[K, V]

// Uses skip=3 for correct stack trace attribution.
func FailMapWrap[K comparable, V any](cause error, code, message string) ResultMap[K, V]
func FailMapNew[K comparable, V any](code, message string) ResultMap[K, V]
```

### 5.3 Methods

| Category | Method | Returns | Description |
|----------|--------|---------|-------------|
| Query | `HasError()` | `bool` | True if operation failed |
| Query | `IsSafe()` | `bool` | True if no error (map may be empty) |
| Query | `HasItems()` | `bool` | True if at least one entry |
| Query | `IsEmpty()` | `bool` | True if zero entries |
| Query | `Count()` | `int` | Number of entries |
| Query | `Has(key)` | `bool` | True if key exists |
| Access | `Items()` | `map[K]V` | Returns the map (nil if error) |
| Access | `Get(key)` | `Result[V]` | Result for key; empty if not found |
| Access | `Keys()` | `[]K` | All keys as slice |
| Access | `Values()` | `[]V` | All values as slice |
| Access | `Error()` | `*AppError` | Returns the AppError, or nil |
| Mutate | `Set(key, value)` | — | Adds/updates entry; no-op if error state |
| Mutate | `Remove(key)` | — | Deletes key; no-op if error state |

---

## 6. Error Code Convention

Error codes are defined as string constants in `codes.go`. **No magic strings.**

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

## 7. Stack Trace Skip Rules

Understanding skip values is critical for accurate error attribution.

The table below shows what each constructor passes to its underlying `CaptureStack` call. `WrapWithSkip` has a base of `3` and `NewWithSkip` has a base of `2` because `Wrap` delegates through one extra internal frame.

| Constructor | Delegates To | `skip` Passed | Effective `CaptureStack` | Reason |
|-------------|-------------|---------------|--------------------------|--------|
| `New()` | `CaptureStack(2)` | — | 2 | Skips `CaptureStackN` + `CaptureStack` + `New` |
| `Wrap()` | `WrapWithSkip(…, 0)` | 0 | 3 | Skips through `Wrap` → `WrapWithSkip` → `CaptureStack` chain |
| `NewWithSkip()` | `CaptureStack(2+skip)` | caller-provided | 2 + skip | Additional skip on top of `New` base |
| `WrapWithSkip()` | `CaptureStack(3+skip)` | caller-provided | 3 + skip | Additional skip on top of `Wrap` base |
| `FailWrap()` | `WrapWithSkip(…, 0)` | 0 | 3 | Same depth as `Wrap` — replaces it, doesn't nest |
| `FailSliceWrap()` | `WrapWithSkip(…, 0)` | 0 | 3 | Same depth as `Wrap` — replaces it, doesn't nest |
| `FailMapWrap()` | `WrapWithSkip(…, 0)` | 0 | 3 | Same depth as `Wrap` — replaces it, doesn't nest |
| `FailNew()` | `NewWithSkip(…, 1)` | 1 | 3 | One frame deeper than `New` (FailNew → NewWithSkip) |
| `FailSliceNew()` | `NewWithSkip(…, 1)` | 1 | 3 | One frame deeper than `New` (FailSliceNew → NewWithSkip) |
| `FailMapNew()` | `NewWithSkip(…, 1)` | 1 | 3 | One frame deeper than `New` (FailMapNew → NewWithSkip) |

> **Key insight:** `FailWrap` calls `WrapWithSkip` directly (same as `Wrap` does), so it sits at the **same depth** and passes `skip=0`. `FailNew` calls `NewWithSkip` directly (one frame deeper than `New`), so it passes `skip=1`.

---

## 8. File Size Policy

| Target | Soft Limit | Description |
|--------|-----------|-------------|
| 300 lines | 400 lines | All files target 300 lines. Up to 400 is acceptable but marked `// NOTE: Needs refactor — exceeds 300-line target` at the top. |

---

## 9. Usage Examples

### Service Method Returning Result[T]

```go
func (s *PluginService) GetByID(ctx context.Context, id int64) apperror.Result[Plugin] {
    plugin, err := s.repo.FindByID(ctx, id)
    if err != nil {
        return apperror.FailWrap[Plugin](err, apperror.ErrDatabaseQuery, "get plugin by ID").
            WithValue("pluginId", fmt.Sprintf("%d", id))
    }
    if plugin == nil {
        return apperror.FailNew[Plugin](apperror.ErrNotFound, "plugin not found")
    }

    return apperror.Ok(*plugin)
}
```

### Handler Consuming Result[T]

```go
func (h *Handler) GetPlugin(w http.ResponseWriter, r *http.Request) {
    result := h.plugins.GetByID(r.Context(), pluginID)
    if result.HasError() {
        writeError(w, result.Error())
        return
    }

    writeJSON(w, result.Value())
}
```

### Error with Values

```go
return apperror.Wrap(err, apperror.ErrFSRead, "failed to read config").
    WithValue("path", configPath).
    WithValue("format", "yaml")
```

---

## 10. Service Adapter Unwrap Pattern

### 10.1 Architectural Boundary

Services return `Result[T]`, `ResultSlice[T]`, and `ResultMap[K, V]` to preserve rich error context and type safety within the domain layer. HTTP handlers consume **adapter interfaces** that expose standard `(T, error)` tuples. A dedicated **Service Adapter** sits between them, acting as the single unwrap boundary.

```
┌─────────────┐    Result[T]    ┌──────────────────┐   (T, error)   ┌──────────┐
│   Service    │ ─────────────► │  ServiceAdapter   │ ─────────────► │  Handler │
│  (domain)    │                │  (unwrap layer)   │                │  (HTTP)  │
└─────────────┘                └──────────────────┘                └──────────┘
```

**Rules:**
- Services **never** return raw `(T, error)` for data-fetching operations — use `Result[T]` or `ResultSlice[T]`
- Void operations (`Delete`, `MarkSynced`, etc.) may return plain `error`
- Adapters are the **only** place that calls `.Value()`, `.Items()`, or `.Error()` to convert back to tuples
- Handlers and other transport-layer code **never** import `apperror.Result` types directly

### 10.2 Adapter Implementation

Each service gets a dedicated adapter file (e.g., `adapter_plugin.go`, `adapter_site.go`, `adapter_sync.go`) in the `handlers` package:

```go
// SiteServiceAdapter wraps *site.Service to implement SiteServiceInterface
type SiteServiceAdapter struct {
    *site.Service
}

// Result[T] → (*T, error) unwrap for single-value returns
func (a *SiteServiceAdapter) GetByID(ctx context.Context, id int64) (*models.Site, error) {
    result := a.Service.GetByID(ctx, id)  // returns apperror.Result[models.Site]
    if result.HasError() {
        return nil, result.Error()
    }
    v := result.Value()
    return &v, nil
}

// ResultSlice[T] → ([]T, error) unwrap for collection returns
func (a *SiteServiceAdapter) List(ctx context.Context) ([]models.Site, error) {
    result := a.Service.List(ctx)  // returns apperror.ResultSlice[models.Site]
    if result.HasError() {
        return nil, result.Error()
    }
    return result.Items(), nil
}
```

### 10.3 Compile-Time Verification

All adapters include compile-time interface checks in `adapters.go`:

```go
var _ SiteServiceInterface = (*SiteServiceAdapter)(nil)
var _ PluginServiceInterface = (*PluginServiceAdapter)(nil)
var _ SyncServiceInterface = (*SyncServiceAdapter)(nil)
```

### 10.4 Cross-Service Consumption

When **Service A** holds a direct reference to **Service B** (not through the adapter), Service A must consume Result types directly using `.HasError()` / `.Value()` / `.IsSafe()`:

```go
// sync service calls plugin service directly (not through adapter)
plugResult := s.pluginService.GetByID(ctx, pluginID)
if plugResult.HasError() {
    return apperror.FailWrap[PushSyncResult](plugResult.Error(), apperror.ErrDatabaseQuery, "failed to get plugin")
}
plug := plugResult.Value()
```

**Cross-service audit checklist** — when migrating a service to Result types, verify:
1. All cross-service callers that hold a direct `*service.Service` reference
2. All `main.go` initialization code that calls service methods
3. All adapter methods are updated to unwrap the new return types

### 10.5 Zero Raw Error Rule

**No service method may return a bare `error` from the standard library.** Every error returned from a service function must be an `*apperror.AppError` (created via `apperror.New`, `apperror.Wrap`, or contained within a `Result[T]`). This guarantees every error carries a stack trace for diagnostics.

**Forbidden patterns:**
```go
// ❌ NEVER — no stack trace captured
return err
return fmt.Errorf("something failed: %w", err)
return errors.New("something failed")
```

**Required patterns:**
```go
// ✅ Wraps with stack trace + error code
return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update config")

// ✅ New error with stack trace + error code
return apperror.New(apperror.ErrNotFound, "entry not found")
```

**Exemptions:**
- `filepath.Walk` callbacks (framework requires `error` interface)
- E2E test harness (`e2e/` package) — test assertion errors, not production

### 10.6 Migrated Services

| Service | Result Types | Adapter File |
|---------|-------------|--------------|
| Plugin | `List`, `GetByID`, `Create`, `Update`, `ScanDirectory`, `GetMappings`, `GetMappingsBySite`, `CreateMapping` | `adapter_plugin.go` |
| Site | `List`, `GetByID`, `GetByURL`, `Create`, `Update` | `adapter_site.go` |
| Sync | `CheckSync`, `CheckAllSites`, `CheckAllPlugins`, `PushSync`, `GetFileChanges` | `adapter_sync.go` |
| Publish | `Publish`, `PublishFiles`, `PreviewPublish`, `GetFileDiff` | `adapter_publish.go` |
| Git | `Pull`, `PullAll`, `Build`, `PullAndBuild`, `GetConfig`, `Status`, `Commit`, `Push` | `adapter_git.go` |
| Watcher | `TriggerScan`, `ScanAfterGitPull`, `ScanAll` | `adapter_sync.go` |
| Backup | `Create`, `List`, `GetByID`, `Restore`, `ExportToZip`, `ImportFromZip` | `adapter_publish.go` |
| Session | `GetSession`, `GetSessionLogs`, `GetSessionDiagnostics`, `ListSessions` | `adapter_session.go` |
| ErrorHistory | `Save`, `List`, `GetByID`, `GetByErrorID`, `Clear`, `BulkExport`, `GetStats` | `adapter_session.go` |
| SiteHealth | `CheckSite`, `CheckAllSites`, `GetHistory`, `GetSummaries`, `GetStats`, `ClearHistory` | `adapter_history.go` |
| PublishHistory | `Record`, `List`, `GetByID`, `GetStats`, `Clear` | `adapter_history.go` |

---

## 11. JSON Serialization

### 11.1 JSON Tag Convention

**Rule:** Only add `json:"..."` tags when the JSON key **differs** from the Go field name OR when `omitempty` is needed. PascalCase field names serialize as PascalCase by default — redundant tags must be removed.

| Scenario | Tag Required? | Example |
|----------|--------------|---------|
| Field name matches JSON key | ❌ No | `Version string` (serializes as `"Version"`) |
| JSON key differs (camelCase) | ✅ Yes | `SiteID int64 \`json:"siteId"\`` |
| Field needs omitempty | ✅ Yes | `Details string \`json:"details,omitempty"\`` |
| Both differ + omitempty | ✅ Yes | `PluginSlug string \`json:"pluginSlug,omitempty"\`` |
| Excluded from JSON | ✅ Yes | `Cause error \`json:"-"\`` |

### 11.2 Existing JSON Tags

All core structs have JSON tags where needed:

- `AppError` — `code`, `message`, `details`, `values`, `diagnostic`, `stack` (camelCase, required) ✅
- `StackTrace` — `frames`, `previousTrace` (camelCase, required) ✅
- `StackFrame` — `function`, `file`, `line` (lowercase, required) ✅
- `ErrorDiagnostic` — all 15+ fields tagged with camelCase + omitempty ✅
- `Cause` — uses `json:"-"` (excluded from default marshaling) ⚠️

### 11.2 Custom MarshalJSON

The `Cause` field is excluded from default JSON marshaling (`json:"-"`) because it's a Go `error` interface. A custom `MarshalJSON` must serialize the cause message as a string:

**File:** `backend/pkg/apperror/error_json.go`

```go
func (e *AppError) MarshalJSON() ([]byte, error) {
    type alias AppError

    return json.Marshal(&struct {
        *alias
        CauseMessage string `json:"cause,omitempty"`
    }{
        alias:        (*alias)(e),
        CauseMessage: causeMessage(e),
    })
}

func causeMessage(e *AppError) string {
    if e.Cause == nil {
        return ""
    }

    return e.Cause.Error()
}
```

**Rules:**
- Uses type alias to prevent infinite recursion
- `Cause` serialized as `"cause"` string field (not nested error object)
- Empty cause omitted via `omitempty`

### 11.3 Custom UnmarshalJSON

Reconstructs `Cause` from the serialized string:

```go
func (e *AppError) UnmarshalJSON(data []byte) error {
    var alias appErrorJSON
    if err := json.Unmarshal(data, &alias); err != nil {
        return fmt.Errorf("apperror.UnmarshalJSON: failed to decode AppError (received %d bytes: %s): %w",
            len(data), truncateData(data, 200), err)
    }

    e.Code = alias.Code
    e.Message = alias.Message
    e.Details = alias.Details
    e.Values = alias.Values
    e.Diagnostic = alias.Diagnostic
    e.Stack = alias.Stack

    if alias.CauseMessage != "" {
        e.Cause = &plainError{msg: alias.CauseMessage}
    }

    return nil
}

// truncateData returns a string preview of raw JSON data, capped at maxLen bytes.
func truncateData(data []byte, maxLen int) string {
    if len(data) <= maxLen {
        return string(data)
    }
    return string(data[:maxLen]) + "..."
}
```

**Rules:**
- Reconstructed `Cause` is a `plainError` struct — the original type is lost (acceptable for deserialization)
- Stack trace, values, and diagnostics are fully preserved
- **Error messages include the raw received data** (truncated to 200 bytes) for debugging malformed payloads

### 11.4 Serialization Output Example

```json
{
    "code": "E3001",
    "message": "failed to connect to WordPress",
    "details": "dial tcp 192.168.1.100:443: connect: connection refused",
    "values": {
        "url": "https://example.com",
        "plugin": "my-plugin"
    },
    "diagnostic": {
        "url": "https://example.com/wp-json/wp/v2/plugins",
        "statusCode": 0,
        "method": "GET"
    },
    "stack": {
        "frames": [
            {"function": "wordpress.(*Client).ListPlugins", "file": "client.go", "line": 42},
            {"function": "sync.(*Service).CheckSync", "file": "service.go", "line": 128}
        ],
        "previousTrace": ""
    },
    "cause": "dial tcp 192.168.1.100:443: connect: connection refused"
}
```

---

## Cross-References

- [Golang Coding Standards](../../03-golang-standards/readme.md) — File size, function size, type safety, file naming
- [Cross-Language Code Style](../../01-coding-guidelines/code-style.md) — Braces, nesting, spacing
- [Enum Specification](../../03-golang-standards/01-enum-specification/00-overview.md) — Byte-based enum pattern with mandatory JSON marshal

---

*apperror package specification v1.3.0 — 2026-02-21*
