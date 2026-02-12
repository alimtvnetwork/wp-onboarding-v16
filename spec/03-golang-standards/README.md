# Golang Coding Standards

> **Version:** 1.0.0  
> **Updated:** 2026-02-09  
> **Applies to:** All Go backend code

---

## Type Safety — No `interface{}` or `any`

### Rule: Never use `interface{}` or `any` in exported APIs

Go 1.18+ provides **generics**. There is no excuse for `interface{}` (aliased as `any` in Go 1.18+) in function signatures, struct fields, or return types.

```go
// ❌ FORBIDDEN
func ProcessData(data interface{}) interface{} { ... }
func FetchResults() (any, error) { ... }
type Config struct {
    Settings map[string]interface{}
}

// ✅ REQUIRED: Use concrete types or generics
func ProcessData(data PluginDetails) (PluginSummary, error) { ... }
func FetchResults[T any]() (T, error) { ... }
type Config struct {
    Settings PluginSettings
}
```

### When to Use Generics

Use generics when a function operates on **multiple concrete types** with the same logic:

```go
// ✅ Generic helper for slice operations
func Filter[T any](items []T, predicate func(T) bool) []T {
    result := make([]T, 0)
    for _, item := range items {
        if predicate(item) {
            result = append(result, item)
        }
    }
    return result
}

// ✅ Generic envelope builder
func BuildEnvelope[T any](results []T, attrs Attributes) EnvelopeResponse[T] {
    return EnvelopeResponse[T]{
        Status:     SuccessStatus(),
        Attributes: attrs,
        Results:    results,
    }
}
```

### Acceptable `any` Usage

Only in these specific contexts:

1. **JSON unmarshaling** — When parsing unknown JSON structures (narrow immediately)
2. **Logging context** — `map[string]any` for structured log fields (internal only)
3. **Third-party library interfaces** — When a library requires `interface{}`

```go
// ✅ Acceptable: JSON parsing with immediate narrowing
var raw map[string]any
json.Unmarshal(data, &raw)
version, ok := raw["Version"].(string)
if !ok {
    return apperror.New("E4001", "missing Version field")
}
```

---

## Error Handling — `apperror` Package

### Rule: Never use `fmt.Errorf` for errors leaving a service

All errors that cross service boundaries must use the `apperror` package:

```go
// ❌ FORBIDDEN: loses stack trace
return fmt.Errorf("failed to upload: %w", err)

// ✅ REQUIRED: full stack trace captured
return apperror.Wrap(err, "E5001", "failed to upload plugin")
```

### Context Enrichment

Always attach contextual data for the error modal:

```go
// ✅ Enriched error with diagnostic context
return apperror.Wrap(err, "E5002", "remote site request failed").
    WithContext("url", requestURL).
    WithContext("slug", pluginSlug).
    WithContext("statusCode", resp.StatusCode).
    WithContext("siteId", siteID)
```

### Error Code Convention

| Range | Category |
|-------|----------|
| E4000–E4999 | Client/validation errors |
| E5000–E5999 | Server/infrastructure errors |
| E6000–E6999 | Remote site (WordPress) errors |
| E7000–E7999 | Scheduler/background job errors |

---

## Struct Design

### JSON Tags

All structs used in API responses must have explicit JSON tags:

```go
// ✅ Explicit JSON tags with omitempty for optional fields
type PluginDetails struct {
    ID        int    `json:"Id"`
    Name      string `json:"Name"`
    Slug      string `json:"Slug"`
    Version   string `json:"Version"`
    IsActive  bool   `json:"IsActive"`
    UpdatedAt string `json:"UpdatedAt,omitempty"`
}
```

### PascalCase JSON Convention

All API response JSON uses **PascalCase** keys (matching the Universal Response Envelope convention):

```go
// ✅ PascalCase JSON keys (project convention)
`json:"IsSuccess"`
`json:"TotalRecords"`
`json:"HasAnyErrors"`

// ❌ camelCase (not used in this project)
`json:"isSuccess"`
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

---

## Concurrency Patterns

### `sync.Once` for Lazy Initialization

```go
// ✅ Lazy-load expensive resources
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
// ✅ Context-aware operations
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

---

*Golang standards specification created: 2026-02-09*
