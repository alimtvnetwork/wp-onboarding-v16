# Error Reporting Standards

## Overview

This document defines mandatory requirements for error reporting across the WP Plugin Publish frontend application. All developers and AI agents must follow these standards to ensure errors are traceable, debuggable, and actionable.

## Mandatory Requirements

### 1. Every `captureException` Call MUST Include:

```typescript
captureException(error, {
  source: "ComponentName.functionName",     // REQUIRED: Always include
  triggerComponent: "ComponentName",         // REQUIRED for user-triggered actions
  triggerAction: "action_description",       // REQUIRED for user-triggered actions
  context: { relevantData }                  // Optional: Include IDs, state info
});
```

#### Source Format
- Always use format: `"ComponentName.functionName"`
- Examples:
  - `"EditSiteDialog.handleSave"`
  - `"Plugins.handlePublish"`
  - `"App.GlobalErrorHandler.unhandledrejection"`

#### Trigger Actions
Common action types:
- `save_clicked` - Save/update button clicked
- `delete_clicked` - Delete action initiated
- `form_submit` - Form submission
- `test_connection` - Connection test triggered
- `publish_initiated` - Publish workflow started
- `dialog_open` - Dialog/modal opened

### 2. Never Swallow Errors Without Logging

❌ **Wrong:**
```typescript
try {
  await api.updateSite(id, data);
} catch (error) {
  // Silently fails - NO CREDIT!
}
```

✅ **Correct:**
```typescript
try {
  await api.updateSite(id, data);
} catch (error) {
  captureException(error, {
    source: "EditSiteDialog.handleSave",
    triggerComponent: "EditSiteDialog",
    triggerAction: "save_clicked",
    context: { siteId: id }
  });
  throw error; // Re-throw if needed by caller
}
```

### 3. Stack Trace Requirements

Every error report must show:
- **File path** (not just function name)
- **Line number**
- **Full call chain** (at least 2-3 levels up)
- **Trigger context** (component + action)

### 4. API Error Handling Pattern

```typescript
const handleAction = async () => {
  try {
    const response = await api.someEndpoint(data);
    if (!response.success && response.error) {
      const captured = captureError(response.error, {
        endpoint: "/endpoint",
        method: "POST",
        requestBody: data,
        context: {
          source: "Component.handleAction",
          triggerComponent: "Component",
          triggerAction: "action_triggered"
        }
      });
      toast.error(response.error.message, {
        action: { label: "Details", onClick: () => openErrorModal(captured) }
      });
    }
  } catch (error) {
    const captured = captureException(error, {
      source: "Component.handleAction",
      triggerComponent: "Component",
      triggerAction: "action_triggered",
      endpoint: "/endpoint",
      method: "POST"
    });
    toast.error("Action failed", {
      action: { label: "Details", onClick: () => openErrorModal(captured) }
    });
  }
};
```

## Error Report Contents

A properly formatted error report includes:

### Required Sections
1. **App Info** - Version, build, commit
2. **Error ID** - Unique identifier
3. **Trigger Context** - Component, action, source
4. **Invocation Chain** - Visual tree of function calls
5. **Message** - Error description
6. **Stack Trace** - Parsed frames with file:line

### Optional Sections (when applicable)
- Request details (endpoint, method, body)
- Response status
- Additional context
- Suggested fixes
- **Backend execution logs** (for operations like publish, sync, backup)
- **Backend stack trace** (Go stack trace when available)
- **Target site URL** (for WordPress operations)

## Error Modal Tabs (Required)

The Global Error Modal MUST include these 6 tabs:

1. **Overview**: Error code, message, timestamp, trigger context, invocation chain
2. **Backend**: Backend execution logs, Go stack trace, target site URL
3. **Request**: API endpoint, method, status, request body
4. **Stack**: Parsed frontend stack frames with file/line, raw stack trace toggle
5. **Context**: Full JSON context with syntax highlighting (via `JsonHighlighter`)
6. **Fixes**: Suggested fixes based on error code

## Backend Logs Capture

All long-running operations MUST stream logs via WebSocket:
- Logs stored as `BackendLogEntry[]`: `{ timestamp, level, message, step?, details? }`
- Backend stack traces (Go) captured separately from frontend traces
- "Copy Full Report" includes BOTH frontend and backend logs/traces

### Capturing Backend Logs

```typescript
captureError(error, {
  endpoint: '/api/...',
  method: 'POST',
  context: {
    source: 'ComponentName.functionName',
    triggerComponent: 'ComponentName',
    triggerAction: 'action_name',
  },
  backendLogs: [...],        // Array of backend log entries
  backendStackTrace: '...',  // Go stack trace if available
  siteUrl: 'https://...',    // Target site URL if applicable
});
```

## UI Requirements

### ScrollArea for Code Sections
All code sections (stack trace, logs, context) MUST use `ScrollArea` with fixed height to prevent modal overflow.

### JSON Syntax Highlighting
All JSON context displays use the `JsonHighlighter` component:
- Keys: blue
- Strings: green
- Numbers: amber
- Booleans: purple
- Null: muted italic

### Live Log Streaming
Operations show real-time logs in collapsible panel:
- Auto-scroll to bottom as new entries arrive
- Color-coded by level (error=destructive, warn=warning, info=primary, debug=muted)

## Example Error Report

```markdown
## Error Report

**App:** WP Plugin Publish v1.19.1
**ID:** 1770222691715-xyz123
**Code:** E9003

### Trigger Context
**Component:** PublishProgressDialog
**Action:** publish_failed
**Source:** PublishProgressDialog.onComplete

### Target Site
https://example.com/wp-admin

### Backend Execution Logs
```
[2026-02-04T18:49:58.000Z] [INFO] [init] Starting publish for Plugin to Site
[2026-02-04T18:49:58.100Z] [INFO] [backup] Creating backup...
[2026-02-04T18:49:58.500Z] [ERROR] [upload] Failed to upload: connection refused
```

### Backend Stack Trace (Go)
```
goroutine 1 [running]:
main.publishHandler(...)
  /app/handlers/publish.go:142
```

### Parsed Stack Frames
| # | Function | File | Line |
|---|----------|------|------|
| 1 | onComplete | PublishProgressDialog.tsx | 245 |
| 2 | handlePublish | Plugins.tsx | 89 |
```

## Enforcement

- Code reviews must verify error handling compliance
- AI agents must include proper error context in all generated code
- Missing `source` parameter should trigger linting warnings

## Related Files

- `src/stores/errorStore.ts` - Error store with `BackendLogEntry` type
- `src/components/errors/GlobalErrorModal.tsx` - Modal with 6 tabs
- `src/components/shared/JsonHighlighter.tsx` - JSON syntax highlighting
- `src/components/plugins/PublishProgressDialog.tsx` - Live log streaming example
- `.lovable/memory/issues-fixed/04-global-error-reporting.md` - Original issue documentation
