# Frontend Error Modal Standards

## Modal Layout

The Global Error Modal uses a **7-tab interface**:
1. **Overview** - Summary, trigger context, message
2. **Session** - Full session logs fetched from backend (if sessionId available)
3. **Backend** - Execution logs and Go stack trace
4. **Request** - Endpoint, method, URL
5. **Stack** - Frontend parsed stack frames
6. **Context** - Full JSON context with syntax highlighting
7. **Fixes** - Suggested fixes based on error code

## Critical Requirements

### Visibility
- All sections MUST be visible without requiring user to zoom out
- Use `ScrollArea` with `max-h-[XYZpx]` for any content that might overflow
- Error location section must NEVER be hidden
- All modals must support vertical scrolling with `max-h-[90vh]` and `overflow-y-auto`

### Tabs for Complex Dialogs
Any dialog with more than 3 sections MUST use tabs:
- `PublishProgressDialog`: Progress | Logs | Settings
- `BackupProgressDialog`: Progress | Logs
- `SyncProgressDialog`: Progress | Logs
- `GlobalErrorModal`: Overview | Session | Backend | Request | Stack | Context | Fixes

### Maximum Heights
```tsx
<DialogContent className="max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">
```

### Log Display
Use `LogViewer` component with:
- Dedicated tab (not inline)
- Badge showing log count
- Copy button for all logs
- Auto-scroll to latest

### Multiline Content
- If log content includes embedded `\\n` sequences (common in stack traces/response bodies), the UI MUST render them as real line breaks (`whitespace-pre-wrap` / `<br/>`).
- Clipboard copy MUST normalize newlines to real line breaks (CRLF) so pasted logs are readable in editors.

### Stack Trace Section
- Must use ScrollArea with explicit max height
- Error location visible at top, not hidden below fold
- File paths should include full relative paths from `internal/` or `pkg/`

### Session Logs Tab
The Session tab uses the `SessionLogsTab` component which:
- Fetches logs from `/api/v1/sessions/{id}/logs` endpoint
- Provides syntax highlighting for stage headers, errors, warnings
- Includes Copy, Download, and Refresh buttons
- Shows loading/error states with retry functionality
- Displays session ID badge and type

---

*Last Updated: 2026-02-05*
