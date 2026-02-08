

# Comprehensive Plan: Session-Based Diagnostics, Error Modal Integration, and Spec Update

## Summary

This plan addresses three interconnected problems identified from the verbatim requirements and the current state audit:

1. **The Error Modal does not show session-based logs** -- when a remote plugin action fails, the error modal lacks the session diagnostic data (logs, request, response, stack traces) that exists on disk.
2. **The Request tab does not show inner PHP sub-nodes** -- when Go delegates to PHP, the full delegated endpoint URL and PHP response are not rendered as sub-nodes.
3. **Stack trace depth and debug toggles are not in the CW seedable config** -- they are hardcoded or only in `config.json`, not governed by the seedable config architecture.

Additionally, several specs need updating to reflect the current implementation and the new requirements.

---

## What Already Works (Audit Results)

The following are already implemented and verified:

- Session folder structure: `data/sessions/{uuid}/` with `session.log`, `request.json`, `response.json`, `error.log`
- Session diagnostics endpoint: `GET /api/v1/sessions/{id}/diagnostics` returns structured JSON with request, response, stackTrace, phpStackTraceLog
- Go stack auto-capture via `envelope.ErrorWithStack()` in `respondError()` (since v1.19.7)
- Debug defaults (`includeStackTrace`, `includeMethodsStack`, `includeErrors`) all default to `true`
- `StackTraceDepth` in `LoggingConfig` (default 20) and `MaxStackFrames` in `ResponseDebugConfig` (default 20)
- Endpoint map in `backend/internal/wordpress/endpoint_map.go` for Go-to-PHP routing
- PHP stack trace capture via `extractErrorDetails()` and `fetchAndAttachRemotePHPErrors()`
- `SessionLogsTab` component exists but only shows when `error.sessionId` is set

## What Is Missing

| Gap | Impact |
|-----|--------|
| Error modal does not fetch session diagnostics when sessionId is present | Stack/Execution/Request tabs are empty for remote plugin errors |
| The `sessionId` from WebSocket events is not passed into the error store | Modal never has sessionId for remote plugin action failures |
| Request tab shows only React-to-Go node; no inner Go-to-PHP sub-node with full PHP endpoint URL and response | User cannot trace the full delegation chain |
| `stackTraceDepth` and `responseDebug` settings are not in `config.seed.json` | Not seedable/versioned via CW architecture |
| Spec 17 (session-management) does not document the diagnostics endpoint or the `SessionDiagnostics` struct | Spec is out of date |
| Spec 14 (logging-system) does not document the relationship between session diagnostics and the error modal | Missing integration spec |

---

## Phase 1: CW Seedable Config for Stack Trace and Debug Settings

**Goal:** Make `stackTraceDepth`, `responseDebug.*`, and PHP backtrace depth configurable via the seedable config architecture.

### 1.1 Update `config.json` and `config.seed.json`

- Add `logging.stackTraceDepth` (already in LoggingConfig, just not in seed)
- Add `responseDebug.includeStackTrace`, `responseDebug.includeMethodsStack`, `responseDebug.includeInternalErrors`, `responseDebug.maxStackFrames` to seedable config
- Add `logging.phpStackTraceDepth: 0` (0 = unlimited, as per requirement)

### 1.2 Wire Config to Services

- Pass `stackTraceDepth` from config to `session.CaptureGoStack()` and `envelope.CaptureMethodFrames()`
- Pass `phpStackTraceDepth` to WordPress client for PHP backtrace requests (currently hardcoded to unlimited, document explicitly)

### 1.3 Acceptance Criteria

- [ ] `config.seed.json` contains all stack trace and debug toggle settings
- [ ] Changing `stackTraceDepth` in config limits Go stack frames in envelope
- [ ] `phpStackTraceDepth: 0` is documented as "unlimited" in spec
- [ ] Settings page reflects seedable config defaults

---

## Phase 2: Pass Session ID to Error Store from WebSocket and API Errors

**Goal:** Ensure every remote plugin error in the error store has a `sessionId` so the modal can fetch diagnostics.

### 2.1 WebSocket Session ID Propagation

- The backend already broadcasts `remote_plugin_action_complete` with `sessionId` via `BroadcastWithSession()`
- Frontend WebSocket handler must capture `sessionId` from the event and store it
- When the error is captured via `captureException`, the `sessionId` from the most recent matching WebSocket event should be attached

### 2.2 API Response Session ID

- When a remote plugin action fails, the Go handler receives the error from `executeRemotePluginAction` which has a session
- Add `sessionId` to the envelope response `Attributes` block so the frontend API client can extract it
- Update `envelope.go` to accept optional `sessionId` in error responses
- Update `parseEnvelope` in `src/lib/api.ts` to extract `sessionId` from `Attributes`

### 2.3 Acceptance Criteria

- [ ] Error store has `sessionId` for remote plugin action failures
- [ ] Session tab appears in error modal for remote plugin errors
- [ ] `Attributes.SessionId` is present in failed envelope responses when a session exists

---

## Phase 3: Session Diagnostics in Error Modal

**Goal:** When the error modal has a `sessionId`, automatically fetch and display session diagnostics in the Stack, Execution, and Request tabs.

### 3.1 Auto-fetch Session Diagnostics

- When `BackendSection` mounts and `error.sessionId` exists, call `GET /api/v1/sessions/{id}/diagnostics`
- Parse the `SessionDiagnostics` response into local state
- Populate: Stack tab (Go + PHP frames), Execution tab (session logs), Request tab (request.json + response.json)

### 3.2 Merge Envelope and Session Data

- Stack tab: Show envelope `Errors.Backend` first, then session `stackTrace.golang` and `stackTrace.php`
- Execution tab: Show envelope `MethodsStack.Backend` first, then session execution logs
- Request tab: Build nested chain from session `request` and `response` data

### 3.3 Acceptance Criteria

- [ ] Stack tab shows Go and PHP stack traces from session diagnostics
- [ ] Execution tab shows both envelope methods stack and session logs
- [ ] Session tab shows full session.log content
- [ ] Data loads in parallel (diagnostics + logs endpoints)

---

## Phase 4: Request Tab Inner PHP Sub-Nodes

**Goal:** Show the full delegation chain in the Request tab: React --> Go Backend (Blue) --> PHP WordPress (Orange) with actual endpoint URLs and response data.

### 4.1 Enrich Request Chain Data

From session diagnostics `response.json`:
- `requestUrl`: The full PHP endpoint URL that Go called (e.g., `https://example.com/wp-json/riseup-asia-uploader/v1/plugins/disable`)
- `responseUrl`: The site URL
- `statusCode`: HTTP status from PHP
- `body`: Full PHP response body

From endpoint map:
- Use `WPEndpointMap` to display the mapped WordPress endpoint alongside the Go endpoint

### 4.2 Update RequestDetails Component

- **Node 1 (Blue)**: React --> Go
  - Method + Go endpoint (e.g., `POST /api/v1/sites/1/remote-plugins/disable`)
  - Request body from React
  - Timestamp: `requestedAt`

- **Node 2 (Orange)**: Go --> PHP (sub-node)
  - Full PHP endpoint URL from session `response.requestUrl`
  - Request body sent to PHP
  - Timestamp: `requestDelegatedAt`
  - PHP response status code
  - PHP response body (collapsible)
  - PHP error stack trace (if error)

### 4.3 Acceptance Criteria

- [ ] Request tab shows two nested nodes for delegated requests
- [ ] PHP node shows full URL (e.g., `https://example.com/wp-json/riseup-asia-uploader/v1/plugins/disable`)
- [ ] PHP response body is visible and collapsible
- [ ] PHP stack trace frames appear in the PHP node when errors occur
- [ ] Non-delegated errors show only the React --> Go node

---

## Phase 5: Spec and Memory Updates

### 5.1 Update Spec 17 (Session Management)

- Add `GET /api/v1/sessions/{id}/diagnostics` endpoint documentation
- Document `SessionDiagnostics` struct with `request`, `response`, `stackTrace`, `phpStackTraceLog`
- Add folder structure: `sessions/{uuid}/session.log`, `request.json`, `response.json`, `error.log`

### 5.2 Update Spec 14 (Logging System)

- Document the relationship between error.log.txt (middleware-level) and session-level error.log
- Document how session diagnostics feed the error modal
- Document CW config keys for stack trace depth

### 5.3 Update Spec 15 (Seedable Config)

- Add `logging.stackTraceDepth`, `logging.phpStackTraceDepth`, and `responseDebug.*` settings to the seed schema

### 5.4 Update Spec 13 (Error Management)

- Document the session-to-modal data flow
- Add the Request tab delegation chain visualization spec

### 5.5 Update Memory Files

- Update `architecture/backend/session-management` with diagnostics endpoint
- Update `architecture/frontend/error-modal-session-diagnostics` with new auto-fetch behavior
- Update `architecture/frontend/error-modal-request-chain` with PHP sub-node spec
- Create `architecture/configuration/seedable-debug-config` for CW stack trace settings

### 5.6 Acceptance Criteria

- [ ] All four specs (13, 14, 15, 17) are updated
- [ ] Memory files reflect current implementation
- [ ] Spec samples include session diagnostics JSON

---

## Technical Details

### Files to Modify

**Backend (Go):**
- `backend/internal/config/config.go` -- Add `PhpStackTraceDepth` to `LoggingConfig`
- `backend/internal/envelope/envelope.go` -- Add optional `SessionId` to `Attributes`
- `backend/internal/api/handlers/site_handlers.go` -- Pass sessionId through error chain to respondError
- `backend/internal/api/handlers/response.go` -- Support sessionId in error envelope

**Frontend (React/TypeScript):**
- `src/lib/api.ts` -- Extract `SessionId` from envelope attributes
- `src/stores/errorStore.ts` -- Map sessionId from API error context
- `src/components/errors/GlobalErrorModal.tsx` -- Auto-fetch diagnostics, render PHP sub-nodes in Request tab

**Specs:**
- `spec/wp-plugin-publish/01-backend/13-error-management.md`
- `spec/wp-plugin-publish/01-backend/14-logging-system.md`
- `spec/wp-plugin-publish/01-backend/15-seedable-config.md`
- `spec/wp-plugin-publish/01-backend/17-session-management.md`

**Memory:**
- `.lovable/memory/architecture/backend/session-management`
- `.lovable/memory/architecture/frontend/error-modal-session-diagnostics`
- `.lovable/memory/architecture/frontend/error-modal-request-chain`
- `.lovable/memory/architecture/configuration/seedable-debug-config` (new)

### Data Flow Diagram

```text
User clicks "Disable Plugin"
        |
        v
React sends POST /sites/1/remote-plugins/disable { plugin: "akismet/akismet.php" }
        |
        v
Go Handler (DisableRemotePlugin)
  |-- Validates input
  |-- Calls executeRemotePluginAction()
  |     |-- StartSession(remote_plugin_disable, ...)
  |     |-- SaveRequest(request.json)
  |     |-- Decrypt credentials
  |     |-- Create WordPress client
  |     |-- Execute disable via PHP endpoint
  |     |     |-- POST https://site.com/wp-json/riseup-asia-uploader/v1/plugins/disable
  |     |     |-- PHP returns envelope with status + stackTrace
  |     |-- SaveResponse(response.json) -- includes requestUrl + responseUrl
  |     |-- SaveError(error.log) -- Go frames + PHP frames
  |     |-- EndSession(error/success)
  |-- respondError() with ErrorWithStack() + SessionId in Attributes
        |
        v
React receives envelope
  |-- parseEnvelope extracts Errors, MethodsStack, SessionId
  |-- captureException stores sessionId + envelope data
        |
        v
Error Modal opens
  |-- Backend Section mounts
  |-- Auto-fetches GET /sessions/{id}/diagnostics (parallel)
  |-- Auto-fetches GET /sessions/{id}/logs (parallel)
  |-- Populates:
  |     Overview: error message + code + timing
  |     Stack: Go frames (envelope) + PHP frames (session diagnostics)
  |     Execution: MethodsStack (envelope) + session logs
  |     Request: React->Go node (blue) + Go->PHP node (orange, from diagnostics)
  |     Session: Full session.log content
```

### Execution Order

1. Phase 1 (config) -- no frontend changes, backend only
2. Phase 2 (sessionId propagation) -- backend envelope + frontend API client + error store
3. Phase 3 (diagnostics in modal) -- frontend only, depends on Phase 2
4. Phase 4 (PHP sub-nodes) -- frontend only, depends on Phase 3
5. Phase 5 (specs/memory) -- documentation, can run in parallel with Phase 4

