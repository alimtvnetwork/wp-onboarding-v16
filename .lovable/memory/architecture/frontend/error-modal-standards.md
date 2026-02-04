# Frontend Error Modal Standards

## Modal Layout

The Global Error Modal uses a **6-tab interface**:
1. **Overview** - Summary, trigger context, message
2. **Backend** - Execution logs and Go stack trace
3. **Request** - Endpoint, method, URL
4. **Stack** - Frontend parsed stack frames
5. **Context** - Full JSON context with syntax highlighting
6. **Fixes** - Suggested fixes based on error code

## Critical Requirements

### Visibility
- All sections MUST be visible without requiring user to zoom out
- Use `ScrollArea` for any content that might overflow
- Error location section must NEVER be hidden

### Tabs for Complex Dialogs
Any dialog with more than 3 sections MUST use tabs:
- `PublishProgressDialog`: Progress | Logs | Settings
- `BackupProgressDialog`: Progress | Logs
- `SyncProgressDialog`: Progress | Logs

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

---

*Last Updated: 2026-02-04*
