# Memory: architecture/coding-standards/go-function-parameters
Updated: 2026-02-26

## Guideline

Go functions should have **2-3 parameters maximum** (excluding `context.Context`). When more parameters are needed, use a **config/options struct** instead. This applies to ALL functions: exported, unexported, and package-level helpers.

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
func RunPowerShellUploadDirect(scriptPath, pluginPath, siteUrl, username, password, slug string, activate bool, onOutput func(line string)) (*PowerShellResult, error)
func broadcastProgress(pluginID, siteID int64, step string, progress int, message string)
func LogProcessError(processName string, cmd string, err error, stdout, stderr string)
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

## Canonical Patterns

These are real patterns from the codebase that demonstrate the struct approach.

### ProgressEvent — WordPress client callbacks

```go
// Groups step/status/message/details into one struct for progress callbacks
type ProgressEvent struct {
    Step    string
    Status  string
    Message string
    Details ProgressDetails
}

func (c *Client) progress(event ProgressEvent) { ... }
```

### OperationLogInput — WebSocket hub broadcasting

```go
// Consolidates pluginID, siteID, sessionID, and log entry into one struct
type OperationLogInput struct {
    PluginID  int64
    SiteID    int64
    SessionID string
    Entry     OperationLogEntry
}

func (h *Hub) BroadcastPublishLog(input OperationLogInput) { ... }
func (h *Hub) BroadcastPublishLogWithSession(input OperationLogInput) { ... }
```

### ConnectionProgressInput — Site connection progress

```go
// Groups site connection progress fields for broadcast
type ConnectionProgressInput struct {
    SiteID  int64
    Step    string
    Status  string
    Message string
    Details json.RawMessage
}

func (a *SiteWSHubAdapter) BroadcastConnectionTestProgress(data ConnectionProgressInput) { ... }
```

### publishContext — Pipeline-scoped context

```go
// Bundles recurring identifiers + dependencies that flow through a multi-stage pipeline
type publishContext struct {
    PluginID  int64
    SiteID    int64
    SessionID string
    WPClient  *wordpress.Client
    Mapping   *models.PluginMapping
    SiteInfo  *models.Site
}

func (s *Service) runPublishPipeline(ctx context.Context, pctx *publishContext, ...) error
func (s *Service) executeUploadStage(ctx context.Context, pctx *publishContext, zipPath string) (bool, Stage)
```

### ProcessErrorInput — Logger

```go
// Groups process execution error fields
type ProcessErrorInput struct {
    ProcessName string
    Command     string
    Err         error
    Stdout      string
    Stderr      string
}

func (l *Logger) LogProcessError(input ProcessErrorInput) { ... }
```

### InitServicesInput — Server bootstrap

```go
// Groups all dependencies needed during service initialization
type InitServicesInput struct {
    DB     *sql.DB
    WSHub  *ws.Hub
    Log    *logger.Logger
    Config *config.Config
}

func initServices(input InitServicesInput) (*services, error)
```

## When to Apply

- **New functions**: Always design with structs if >3 params needed
- **Existing functions**: Refactor when modifying if it exceeds 3 params
- **Interfaces**: Keep lean; complex data goes in struct params
- **Internal/unexported helpers**: Same rule — no exceptions for "just internal" functions
- **Pipeline methods**: Use a context struct (like `publishContext`) when the same 3+ identifiers flow through multiple stages

## Exceptions

- `context.Context` doesn't count toward the limit
- Callbacks/handlers may have more params if following a standard pattern (e.g., `http.HandlerFunc`)
- Generated code may deviate from this guideline

## Cross-References

- Error wrapping formatting: `spec/03-coding-guidelines/code-style.md` Rule 6a
- Boolean naming: `spec/03-coding-guidelines/boolean-principles.md` P1
