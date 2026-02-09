# Error Handling — Cross-Stack Specification

> **Version:** 1.0.0  
> **Updated:** 2026-02-09  
> **Status:** Active  
> **Applies to:** Go backend, React/TypeScript frontend, PHP WordPress plugin

---

## Overview

The project implements a **three-tier error handling architecture** spanning the full React → Go → PHP request chain. Every error is captured with structured diagnostics, stack traces, and contextual metadata to enable deep debugging from the frontend Global Error Modal.

---

## Error Flow Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Frontend (React/TypeScript)                   │
│  ┌─────────────────┐    ┌──────────────────┐    ┌───────────────┐   │
│  │ API Client       │───▸│ Error Store       │───▸│ Global Error  │   │
│  │ (parseEnvelope)  │    │ (captureError)    │    │ Modal (tabs)  │   │
│  └─────────────────┘    └──────────────────┘    └───────────────┘   │
│         │                       │                                    │
│         │ Envelope.Errors       │ executionChain                    │
│         │ Envelope.MethodsStack │ clickPath                         │
│         │ Envelope.SessionId    │ componentContext                  │
└─────────────────────────────────────────────────────────────────────┘
                              ▲
                              │ Universal Response Envelope
┌─────────────────────────────────────────────────────────────────────┐
│                        Backend (Go)                                  │
│  ┌─────────────────┐    ┌──────────────────┐    ┌───────────────┐   │
│  │ apperror.Wrap() │───▸│ Session Logger    │───▸│ error.log.txt │   │
│  │ + .WithContext() │    │ (per-request ID)  │    │ (deduped)     │   │
│  └─────────────────┘    └──────────────────┘    └───────────────┘   │
│         │                       │                                    │
│         │ stack trace           │ fetchAndAttachRemotePHPErrors     │
│         │ error code            │                                    │
└─────────────────────────────────────────────────────────────────────┘
                              ▲
                              │ REST API (JSON)
┌─────────────────────────────────────────────────────────────────────┐
│                     WordPress Plugin (PHP)                           │
│  ┌─────────────────┐    ┌──────────────────┐    ┌───────────────┐   │
│  │ safe_execute()  │───▸│ RiseupLogger      │───▸│ stacktrace.txt│   │
│  │ catch Throwable │    │ (6-frame backtrace)│   │ fatal-errors  │   │
│  └─────────────────┘    └──────────────────┘    │ error.txt     │   │
│                                                  └───────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Tier 1: PHP Error Handling (WordPress Plugin)

### Safe Execution Pattern

Every REST endpoint handler is wrapped in `safe_execute`:

```php
public function handle_request($request) {
    return $this->safe_execute(function() use ($request) {
        // Business logic
        return $this->envelope->success($result);
    });
}
```

The wrapper catches `\Throwable` (not just `Exception`) to capture PHP 7+ Errors like missing classes.

### Structured Error Response

```json
{
  "message": "Class 'PDO' not found",
  "stackTrace": "#0 /path/file.php(42): PluginManager->connect()\n#1 {main}",
  "stackTraceFrames": [
    { "file": "/path/file.php", "line": 42, "function": "connect", "class": "PluginManager" }
  ]
}
```

### Logging Outputs

| File | Content | Depth |
|------|---------|-------|
| `error.txt` | Structured error entries with context metadata | Last N entries |
| `log.txt` | General diagnostic log | All operations |
| `stacktrace.txt` | Raw PHP backtraces (`debug_backtrace(0, 0)`) | Unlimited |
| `fatal-errors.log` | Fatal errors caught by shutdown handler | With memory usage |

### Global Shutdown Handler

```php
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log to fatal-errors.log with memory_get_peak_usage()
        // Send JSON response before process terminates
    }
});
```

### Context Enrichment

Every `error()` and `log_exception()` call automatically captures:
- 6-frame backtrace
- HTTP method and endpoint
- User-agent and IP
- Memory usage
- Request body (truncated)

---

## Tier 2: Go Backend Error Handling

### `apperror` Package

All errors crossing service boundaries must use `apperror`:

```go
// Wrap existing errors with code and context
return apperror.Wrap(err, "E5001", "failed to upload plugin").
    WithContext("url", requestURL).
    WithContext("slug", pluginSlug).
    WithContext("statusCode", resp.StatusCode)

// Create new errors
return apperror.New("E4001", "invalid plugin slug")
```

**Forbidden:** `fmt.Errorf` for errors leaving a service (no stack trace).

### Remote PHP Error Injection

When a remote WordPress operation fails, the Go backend automatically:

1. Calls `fetchAndAttachRemotePHPErrors` on the target site
2. Retrieves the 10 most recent PHP errors from remote SQLite database
3. Retrieves `stacktrace.txt` content
4. Injects this data into Go session logs and the envelope's `Errors` block

### Error Log Deduplication

The backend uses MD5 hashing to suppress identical error log entries:

```
Hash = MD5(action + siteID + plugin + endpoint + statusCode + responseBody)
```

A "Clear Dedup Hashes" button in Settings resets the in-memory hash map.

### Redefined Log Format

Every failure log entry follows this structure:

1. **Site Request URL** — Full compiled endpoint on the target WordPress site
2. **Site Identification** — Site name and URL
3. **Backend Endpoint** — The Go endpoint hit by the frontend
4. **Delegated Request** — Method, PHP endpoint, full JSON request body
5. **Delegated Response** — Status code and body
6. **Error Summary** — Concise error description
7. **Guard Rail** — Blocks unauthorized direct mutations to `/wp/v2/plugins/*`

### Session-Based Logging

Every HTTP request gets a unique session ID. Full request/response data is captured:
- Headers (with Authorization redacted)
- Bodies (truncated at 50KB)
- Timing
- Error extraction for status ≥ 400

Storage: `backend/data/request-sessions/{date}/{hour}/{uuid}.json`

---

## Tier 3: Frontend Error Handling

### Error Store (`errorStore.ts`)

Centralized Zustand store that captures:

```typescript
interface CapturedError {
  message: string;
  component: string;          // React component name
  action: string;             // User action that triggered it
  trigger: string;            // Click/handler context
  
  // Diagnostic data from envelope
  backendMessage?: string;
  delegatedServiceErrorStack?: string[];
  methodsStack?: StackFrame[];
  sessionId?: string;
  
  // Frontend diagnostics
  executionChain?: string;    // From React Execution Logger
  clickPath?: string[];       // User interaction history
  stackFrames?: ParsedFrame[];
}
```

### Envelope Parsing

The API client's `parseEnvelope` detects failed responses and extracts:
- `Errors.BackendMessage` — Primary error text
- `Errors.DelegatedServiceErrorStack` — PHP stack trace lines
- `Errors.Backend` — Go stack trace lines
- `MethodsStack.Backend` — Go call chain with file:line
- `Attributes.SessionId` — Links to session-level diagnostics

### Global Error Modal Tabs

| Tab | Content |
|-----|---------|
| **Overview** | Error message, component context, suggested fixes |
| **Stack** | Backend (Go) + Frontend (React) stack traces |
| **Request** | HTTP request/response details |
| **Traversal** | React → Go → PHP request chain visualization |
| **Execution** | React Execution Logger chain + click path |

### Session Diagnostics Auto-Fetch

When `sessionId` is present, the modal automatically fetches session-level diagnostics from `GET /api/v1/request-sessions/{id}`, merging deep Go/PHP stack traces into the Stack and Execution tabs.

### Error Reporting Bundle

The "Download Bundle" button exports:
- All diagnostic data as JSON
- Syntax-highlighted error report
- Execution chain and click path
- Full request/response data

---

## Error Code Ranges

| Range | Category | Example |
|-------|----------|---------|
| E4000–E4999 | Client/validation errors | E4001: Invalid plugin slug |
| E5000–E5999 | Server/infrastructure errors | E5001: Upload failed |
| E6000–E6999 | Remote site (WordPress) errors | E6001: Plugin not found on site |
| E7000–E7999 | Scheduler/background job errors | E7001: Scheduled publish timeout |

---

## Fallback Visibility

The Errors page implements a 3-tier fallback:

1. **Live Backend API** — Primary source
2. **Global Error Store** — Session-captured errors
3. **Error-level Notifications** — Local errors with Eye icon for modal access

---

## Cross-References

- [Error Resolution Retrospectives](../error-resolution/00-overview.md)
- [Session-Based Logging](../logging-and-diagnostics/session-based-logging.md)
- [React Execution Logger](../logging-and-diagnostics/react-execution-logger.md)
- [PHP Error Trapping Strategy](../../.lovable/memory/architecture/wordpress-plugin/error-trapping-strategy)
- [PHP Standards](../php-standards/README.md)
- [Golang Standards](../golang-standards/README.md)

---

*Error handling specification created: 2026-02-09*
