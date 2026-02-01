# AI Bridge: Error Codes

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

AI Bridge uses error codes in the **9000-9999** range, following the project's standardized error code system.

---

## Error Code Registry

### 9000-9099: General/Startup Errors

| Code | Name | Description | Resolution |
|------|------|-------------|------------|
| 9000 | `ErrConfigNotFound` | Configuration file not found | Create config.yaml or specify --config path |
| 9001 | `ErrConfigInvalid` | Configuration file is invalid | Check YAML syntax and required fields |
| 9002 | `ErrConfigMissingRequired` | Required configuration field missing | Add required field to config |
| 9010 | `ErrDaemonAlreadyRunning` | Daemon is already running | Use `aibridge daemon stop` first |
| 9011 | `ErrDaemonNotRunning` | Daemon is not running | Use `aibridge daemon start` |
| 9012 | `ErrDaemonStartFailed` | Failed to start daemon | Check port availability and permissions |
| 9013 | `ErrDaemonStopFailed` | Failed to stop daemon | Check PID file and process state |
| 9020 | `ErrPortInUse` | Daemon port is already in use | Change port in config or stop conflicting process |
| 9021 | `ErrPermissionDenied` | Insufficient permissions | Run with elevated privileges or change paths |

### 9100-9199: Input Parsing Errors

| Code | Name | Description | Resolution |
|------|------|-------------|------------|
| 9100 | `ErrUnsupportedFormat` | Input format not supported | Use .md, .json, .yaml, or .csv |
| 9101 | `ErrJSONParseFailed` | Failed to parse JSON | Check JSON syntax |
| 9102 | `ErrJSONValidationFailed` | JSON schema validation failed | Check required fields and types |
| 9110 | `ErrYAMLParseFailed` | Failed to parse YAML | Check YAML syntax and indentation |
| 9111 | `ErrYAMLEmpty` | YAML document is empty | Add content to YAML file |
| 9120 | `ErrMarkdownParseFailed` | Failed to parse Markdown | Check frontmatter syntax |
| 9121 | `ErrMarkdownMissingFrontmatter` | Markdown missing frontmatter | Add YAML frontmatter with --- delimiters |
| 9130 | `ErrCSVParseFailed` | Failed to parse CSV | Check CSV format and encoding |
| 9131 | `ErrCSVRequiresConfig` | CSV requires companion config | Create .config.yaml file with prompt template |
| 9132 | `ErrCSVEmpty` | CSV has no data rows | Add data rows to CSV |
| 9140 | `ErrVariableNotFound` | Template variable not resolved | Define missing variable in variables map |
| 9141 | `ErrVariableTypeMismatch` | Variable type mismatch | Check variable type in template |

### 9200-9299: Backend Connection Errors

| Code | Name | Description | Resolution |
|------|------|-------------|------------|
| 9200 | `ErrNoBackendAvailable` | No LLM backend is available | Start Ollama or llama.cpp server |
| 9201 | `ErrBackendConnectionFailed` | Failed to connect to backend | Check backend URL and network |
| 9202 | `ErrBackendTimeout` | Backend connection timed out | Increase timeout or check backend health |
| 9210 | `ErrOllamaNotRunning` | Ollama server not running | Run `ollama serve` |
| 9211 | `ErrOllamaModelNotFound` | Model not found in Ollama | Run `ollama pull <model>` |
| 9212 | `ErrOllamaModelLoadFailed` | Failed to load Ollama model | Check GPU memory and model compatibility |
| 9220 | `ErrLlamaCppNotRunning` | llama.cpp server not running | Start llama-server |
| 9221 | `ErrLlamaCppModelNotFound` | Model file not found | Check model path in config |
| 9222 | `ErrLlamaCppModelLoadFailed` | Failed to load llama.cpp model | Check GPU memory and model format |
| 9230 | `ErrBackendHealthCheckFailed` | Backend health check failed | Restart backend service |
| 9231 | `ErrBackendOverloaded` | Backend is overloaded | Wait or reduce request rate |

### 9300-9399: Request Processing Errors

| Code | Name | Description | Resolution |
|------|------|-------------|------------|
| 9300 | `ErrRequestInvalid` | Request is invalid | Check request format and required fields |
| 9301 | `ErrRequestTooLarge` | Request exceeds size limit | Reduce prompt size |
| 9302 | `ErrContextTooLong` | Context exceeds model limit | Reduce context or use larger model |
| 9310 | `ErrModelCategoryInvalid` | Invalid model category | Use: thinking, writing, coding, or voice |
| 9311 | `ErrModelNotFound` | Specified model not found | Check model ID or use category |
| 9312 | `ErrModelNotLoaded` | Model not currently loaded | Load model or wait for auto-load |
| 9320 | `ErrGenerationFailed` | Text generation failed | Check backend logs |
| 9321 | `ErrGenerationTimeout` | Generation timed out | Increase timeout or reduce maxTokens |
| 9322 | `ErrGenerationCancelled` | Generation was cancelled | Request was cancelled by user |
| 9330 | `ErrBatchItemFailed` | Batch item processing failed | Check individual item for errors |
| 9331 | `ErrBatchPartialFailure` | Some batch items failed | Review failed items in response |

### 9400-9499: Response Handling Errors

| Code | Name | Description | Resolution |
|------|------|-------------|------------|
| 9400 | `ErrResponseInvalid` | Invalid response from backend | Report as bug if persistent |
| 9401 | `ErrResponseParseFailed` | Failed to parse backend response | Check backend compatibility |
| 9410 | `ErrStreamInterrupted` | Streaming was interrupted | Retry request |
| 9411 | `ErrStreamTimeout` | Streaming timed out | Increase timeout |
| 9420 | `ErrOutputFormatFailed` | Failed to format output | Check output format setting |
| 9421 | `ErrOutputWriteFailed` | Failed to write output file | Check file permissions and path |

---

## Error Structure

```go
type BridgeError struct {
    Code       int               `json:"code"`
    Message    string            `json:"message"`
    Details    string            `json:"details,omitempty"`
    Cause      error             `json:"-"`
    Context    map[string]any    `json:"context,omitempty"`
    Retryable  bool              `json:"retryable"`
    Timestamp  time.Time         `json:"timestamp"`
}

func NewError(code int, message string, args ...any) *BridgeError {
    return &BridgeError{
        Code:      code,
        Message:   fmt.Sprintf(message, args...),
        Timestamp: time.Now(),
        Retryable: isRetryable(code),
    }
}

func isRetryable(code int) bool {
    retryableCodes := map[int]bool{
        9200: true, // No backend available
        9201: true, // Connection failed
        9202: true, // Timeout
        9230: true, // Health check failed
        9231: true, // Overloaded
        9321: true, // Generation timeout
        9410: true, // Stream interrupted
        9411: true, // Stream timeout
    }
    return retryableCodes[code]
}
```

---

## Error Response Format

### CLI Output

```
Error [9101]: Invalid JSON in request body
Details: unexpected end of JSON input at position 45
File: prompt.json

Run with --verbose for more details.
```

### API Response

```json
{
  "error": {
    "code": 9101,
    "message": "Invalid JSON in request body",
    "details": "unexpected end of JSON input at position 45",
    "retryable": false,
    "timestamp": "2026-01-31T10:30:00Z"
  }
}
```

---

## Logging

All errors are logged with context:

```go
log.Error().
    Int("code", err.Code).
    Str("message", err.Message).
    Str("file", sourceFile).
    Int("line", lineNumber).
    Err(err.Cause).
    Msg("AI Bridge error")
```

---

## See Also

- [Architecture](./01-architecture.md)
- [Error Management Spec](../../06-error-management/00-overview.md)
- [Error Code Registry](../../../../error-code-registry/01-registry.md)
