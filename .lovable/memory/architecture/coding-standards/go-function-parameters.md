# Memory: architecture/coding-standards/go-function-parameters
Updated: 2026-02-05

## Guideline

Go functions should have **2-3 parameters maximum**. When more parameters are needed, use a **config/options struct** instead.

## Rationale

- Improves readability and maintainability
- Makes function signatures self-documenting
- Allows optional parameters with zero-value defaults
- Simplifies adding new parameters without breaking existing callers
- Enables IDE autocomplete for parameter names

## Examples

### ❌ Bad: Too many parameters

```go
func StartSession(sessionType SessionType, pluginID, siteID int64, pluginName, siteName string) (string, error)
```

### ✅ Good: Use a struct

```go
type StartSessionInput struct {
    Type       SessionType
    PluginID   int64
    SiteID     int64
    PluginName string
    SiteName   string
}

func StartSession(input StartSessionInput) (string, error)
```

### ✅ Acceptable: 2-3 essential parameters

```go
func GetByID(ctx context.Context, id int64) (*Model, error)
func Create(ctx context.Context, input CreateInput) (*Model, error)
```

## When to Apply

- **New functions**: Always design with structs if >3 params needed
- **Existing functions**: Refactor when modifying if it exceeds 3 params
- **Interfaces**: Keep lean; complex data goes in struct params

## Exceptions

- `context.Context` doesn't count toward the limit
- Callbacks/handlers may have more params if following a standard pattern
- Generated code may deviate from this guideline
