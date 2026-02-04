# Memory: features/error-handling-and-debugging

> **Location:** `.lovable/memory/features/error-handling-and-debugging.md`  
> **Updated:** 2026-02-04

---

## Overview

A robust error handling system captures detailed context for every failure, including **client-side stack traces**. The UI provides interactive modals with copyable debug data, enabling users to easily share full diagnostic information for troubleshooting.

---

## Error Store (`src/stores/errorStore.ts`)

### Key Functions

1. **`captureError(apiError, meta?)`**
   - Captures API errors from backend responses
   - Automatically generates client-side stack traces
   - Parses stack traces to extract file/line/function info

2. **`captureException(error, context?)`**
   - Captures any JavaScript exception with full stack trace
   - Used in catch blocks for unexpected errors
   - Extracts structured info from Error objects

### Stack Trace Capture

```typescript
// Always captures client stack even for API errors
const clientStack = captureStackTrace();
const combinedStack = error.stackTrace 
  ? `${error.stackTrace}\n\n--- Client Stack ---\n${clientStack}`
  : clientStack;
```

---

## Error Detail Modal

### Location
`src/components/errors/GlobalErrorModal.tsx`

### Features

1. **Tabbed Interface**
   - Overview: Message, details, file location, stack trace
   - Request Info: API endpoint, method, request body, **resolved URL**
   - Full Context: Complete JSON context data
   - Suggested Fixes: Error code-specific recommendations

2. **API Connectivity Display** *(Mandatory)*
   - **Request URL:** Fully resolved URL that was actually called
   - **API Base:** Configured base from `resolveApiBase()`
   - **VITE_API_URL:** Environment variable state (set or not)

3. **Stack Trace Display**
   - Shows combined backend + client stack traces
   - Copy button for each section
   - Syntax-highlighted pre-formatted display

4. **Copy Functionality**
   - Copy individual sections
   - Copy full Markdown-formatted report
   - Shareable format for support tickets

### Connectivity Error Detection

The API client detects HTML-instead-of-JSON responses (e.g., when backend is down and dev server serves the SPA fallback):

| Code | Meaning |
|------|---------|
| `E9005` | Received HTML instead of JSON—likely port/URL mismatch |
| `E9006` | Network/fetch failure—backend unreachable |

**Rule:** Always show the resolved request URL in error modals so users can verify the correct endpoint.

---

## Error Codes

| Range | Category |
|-------|----------|
| E1xxx | Request validation |
| E2xxx | Site/WordPress errors |
| E3xxx | Plugin service errors |
| E4xxx | Sync service errors |
| E5xxx | Git service errors |
| E6xxx | Watcher service errors |
| E7xxx | E2E test errors |
| E9xxx | System/network errors |

---

## Usage Pattern

```typescript
// In component
const { captureError, captureException, openErrorModal } = useErrorStore();

// For API errors (from response.error)
if (response.error) {
  showErrorWithModal(response.error, { endpoint, method, requestBody });
}

// For caught exceptions
catch (error) {
  const captured = captureException(error, { endpoint, method });
  toast.error("Operation failed", {
    action: { label: "View Details", onClick: () => openErrorModal(captured) },
  });
}
```

---

## Live Connection Test Logging

When testing WordPress site connections, the backend streams real-time progress via WebSocket.

### WebSocket Events

| Event | Description |
|-------|-------------|
| `connection_test_started` | Test begins |
| `connection_test_progress` | Step-by-step updates (step, status, message, details) |
| `connection_test_complete` | Test finished |

### Components

1. **`useConnectionTestLogs` Hook** (`src/hooks/useConnectionTestLogs.ts`)
   - Subscribes to WebSocket connection test events
   - Maintains state: `siteId`, `isActive`, `steps[]`

2. **`ConnectionTestLogs` Component** (`src/components/sites/ConnectionTestLogs.tsx`)
   - Displays live log stream with timestamps and status icons
   - Collapsible panel with copy-to-clipboard functionality

---

## URL Normalization

The backend automatically normalizes site URLs (`backend/internal/services/site/service.go`):

- Strips `/wp-admin`, `/wp-login.php`, `/wp-json` paths
- Removes query params and fragments
- Ensures HTTPS protocol
- Removes trailing slashes

Example: `https://example.com/wp-admin/` → `https://example.com`

---

## Integration

- GlobalErrorModal rendered in App.tsx
- Errors page shows list of errors with expandable details
- E2E test failures open error modal on click
- Toast notifications with "View Details" action
- Copyable reports for AI/support assistance
- Sites page shows live connection test logs

---

*Full debug info with stack trace, request/response, live logs, and suggested fixes.*
