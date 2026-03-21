# Delegated Error Logs (PHP) — Specification

## Overview

When the Go backend proxies a request to a WordPress PHP server (the "delegated server"), the PHP response may include structured error diagnostics — stack traces, error messages, status codes, and response bodies. These must be surfaced clearly in the Error Modal under a dedicated **"Delegated"** tab, formatted consistently with how Go backend traces are displayed.

## Architecture Flow

```
[Browser] → [Go Backend] → [WordPress PHP Server]
                ↓                    ↓
          Backend Trace        PHP Stack Trace
          (Go stack)           (in JSON response)
                ↓                    ↓
          ┌─────────────────────────────────────┐
          │         Error Envelope               │
          │  Errors.DelegatedRequestServer: {    │
          │    DelegatedEndpoint, Method,        │
          │    StatusCode, StackTrace[],         │
          │    RequestBody, Response,            │
          │    AdditionalMessages                │
          │  }                                   │
          │  Errors.DelegatedServiceErrorStack[] │
          └─────────────────────────────────────┘
```

The Go backend already extracts and attaches this data to the response envelope. The frontend already stores it in `CapturedError.envelopeErrors`. What's missing is a **dedicated, well-structured UI tab** to present it.

## Data Sources

### 1. `DelegatedRequestServer` (from `Errors.DelegatedRequestServer`)

| Field               | Type       | Description                                      |
|---------------------|------------|--------------------------------------------------|
| `DelegatedEndpoint` | `string`   | The PHP REST endpoint that was called             |
| `Method`            | `string`   | HTTP method (GET, POST, DELETE, etc.)             |
| `StatusCode`        | `number`   | HTTP status code from PHP server                  |
| `StackTrace`        | `string[]` | PHP stack trace lines from the error response     |
| `RequestBody`       | `unknown`  | Request body sent to PHP server                   |
| `Response`          | `unknown`  | Raw response body from PHP server                 |
| `AdditionalMessages`| `string`   | Extra diagnostic message from the Go proxy        |

### 2. `DelegatedServiceErrorStack` (from `Errors.DelegatedServiceErrorStack`)

A flat `string[]` of PHP error stack lines — an older/simpler format that may appear alongside or instead of `DelegatedRequestServer`.

### 3. `phpStackFrames` (from `CapturedError.phpStackFrames`)

Structured PHP stack frames parsed from WordPress error responses (via `PhpStackTraceFrame` in the Go backend). These contain `file`, `fileBase`, `line`, `function`, `class`.

## UI Design

### Tab Placement

Add a **"Delegated"** tab (with `Globe` icon) to the `BackendSection` tabs, positioned after "Request" and before "Traversal". The tab only appears when delegated data exists.

### Tab Content — Sections (in order)

#### 1. Delegated Request Info
- Shows the PHP endpoint, method, and status code
- Badge-styled status (destructive for 4xx/5xx)
- Additional messages if present

#### 2. PHP Stack Trace (Structured — from `phpStackFrames`)
- Table format: `#`, `Function`, `File`, `Line`
- Orange-themed header (`bg-orange-500/10`)
- First frame highlighted
- Copy button

#### 3. PHP Stack Trace (from `DelegatedRequestServer.StackTrace`)
- Pre-formatted text in orange-themed scroll area
- Copy button
- Shows endpoint badge

#### 4. PHP Delegated Error Stack (from `DelegatedServiceErrorStack`)
- Pre-formatted text in orange-themed scroll area
- Copy button

#### 5. PHP Response Body
- Collapsible `<details>` section
- JSON-formatted if object, raw text if string
- Copy button

#### 6. Request Body (sent to PHP)
- Collapsible `<details>` section
- JSON-formatted
- Copy button

### Empty State
When no delegated data exists (tab shouldn't appear, but as fallback):
> "No delegated server data available. Delegated logs appear when the Go backend proxies requests to WordPress PHP endpoints."

## Display Order in Stack Tab (cleanup)

After this change, the **Stack** tab should NO LONGER show delegated content. All PHP/delegated traces move to the new **Delegated** tab:

**Stack tab shows:**
1. Go Backend Stack (envelope)
2. Go Stack Trace (raw)
3. Go Stack (Session)

**Delegated tab shows:**
1. Delegated Request Info
2. PHP Stack Trace (structured frames)
3. PHP Stack Trace (DelegatedRequestServer.StackTrace)
4. PHP Delegated Error Stack
5. PHP Response Body
6. PHP Log (stacktrace.txt from session)
7. PHP Stack (Session frames)

## Error Handling

- All delegated fields are optional — graceful fallback for missing data
- Malformed JSON in `Response`/`RequestBody` → render as raw string
- Empty `StackTrace[]` → section hidden
- Safe JSON.stringify with try/catch for display

## Acceptance Criteria

1. ✅ Dedicated "Delegated" tab appears only when delegated data exists
2. ✅ PHP stack traces shown in structured table (when `phpStackFrames` available)
3. ✅ Raw stack traces shown in formatted pre block
4. ✅ Request/response bodies are collapsible and copyable
5. ✅ Stack tab no longer shows PHP/delegated content (moved to Delegated tab)
6. ✅ Copy support for all sections
7. ✅ No crashes on malformed or missing data
8. ✅ Orange-themed styling consistent with existing PHP trace rendering
