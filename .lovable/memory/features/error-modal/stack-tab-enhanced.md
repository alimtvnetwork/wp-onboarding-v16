# Memory: features/error-modal/stack-tab-enhanced
Updated: 2026-02-05

## Overview

The Stack tab in the Global Error Modal has been enhanced to display:
1. **PHP Stack Trace Frames** - Structured frames from WordPress plugin errors
2. **Backend Error Log** - Content of `error.log.txt` auto-fetched from backend
3. **Parsed JS/TS Frames** - Existing functionality preserved

## PHP Stack Trace Display

When an error contains `stackTraceFrames` or `phpStackFrames` in the context (from WordPress plugin errors), the Stack tab displays a dedicated table:

- Orange-themed styling to distinguish from JS/Go traces
- Shows: `#`, `Class::Function()`, `File`, `Line`
- Extracted from:
  - `error.phpStackFrames` (new field on CapturedError)
  - `error.context.stackTraceFrames`
  - `error.context.errorDetails.stackTraceFrames`

### Frame Structure

```typescript
export interface PHPStackFrame {
  file?: string;
  fileBase?: string;
  line?: number;
  function?: string;
  class?: string;
}
```

## Backend Error Log

The Stack tab auto-fetches `error.log.txt` on hover/focus via `api.getBackendErrorLog()`.

Features:
- Lazy loading (only fetches when tab is viewed)
- Refresh, Copy, and Download buttons
- ScrollArea with monospace formatting
- Error state handling with retry option

## Integration with Copy/Download

- **Copy with Backend Logs** - Includes `error.log.txt` content in copied report
- **Download error.log.txt** - Direct file download from dropdown

## WebSocket Integration

Remote plugin actions (enable/disable/delete) now broadcast events:
- `remote_plugin_action_started` - When action begins
- `remote_plugin_action_complete` - With success/error and PHP stack frames

The `useRemotePluginEvents` hook automatically:
1. Subscribes to these events for a specific site
2. Captures errors with full PHP stack trace frames
3. Shows toast notifications with "View Details" action
4. Invalidates the remote plugins query cache

## Related Files

- `src/components/errors/GlobalErrorModal.tsx` - Main implementation
- `src/stores/errorStore.ts` - CapturedError with PHPStackFrame support
- `src/hooks/useRemotePluginEvents.ts` - WebSocket event handler
- `src/lib/api.ts` - `getBackendErrorLog()` API method
- `backend/internal/api/handlers/handlers.go` - `/errors/log` endpoint
