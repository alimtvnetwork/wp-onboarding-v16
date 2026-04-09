# Frontend Error Modal Standards

## Modal Layout

The Global Error Modal uses a **3-section architecture** with conditional tabs:

### Top-Level Sections (Section Toggle)
1. **Backend** — Primary diagnostic view (default active)
2. **Frontend** — JS/React error details
3. **Delegated Logs** — Remote WordPress server diagnostics (orange-themed)

### Backend Section Tabs
- **Overview** — Summary, error message, request info, timing, badges (ALWAYS visible)
- **Log** — `error.log.txt` viewer with auto-fetch (ALWAYS visible)
- **Execution** — Go call chain table + session execution logs (ALWAYS visible)
- **Stack** — Go/PHP/Delegated stack traces (ALWAYS visible)
- **Session** — Full session diagnostics with 4 sub-tabs (CONDITIONAL: only when `sessionId` exists)
- **Request** — 3-hop request chain visualization (ALWAYS visible)
- **Traversal** — Endpoint flow + methods stack (CONDITIONAL: only when `envelopeErrors || envelopeMethodsStack || requestedAt` exists)

### Frontend Section Tabs
- **Overview** — Trigger context, click path, call chain
- **Stack** — Parsed/raw JS stack frames + execution chain
- **Context** — Full JSON context with syntax highlighting
- **Fixes** — Suggested fixes by error code

### Error Source Badge (Header)
| Badge | Condition | Color |
|-------|-----------|-------|
| `Delegated Remote` | Has delegated data (requestDelegatedAt, DelegatedRequestServer, phpStackFrames, etc.) | Orange |
| `Frontend` | No endpoint + no envelope + has parsed JS frames | Blue |
| `Local Backend` | Default | Green |

## Critical Requirements

### Visibility
- All sections MUST be visible without requiring user to zoom out
- Use `ScrollArea` with `max-h-[XYZpx]` for any content that might overflow
- Error location section must NEVER be hidden
- All modals must support vertical scrolling with `max-h-[90vh]` and `overflow-y-auto`

### Maximum Heights
```tsx
<DialogContent className="max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">
```

### Multiline Content
- If log content includes embedded `\\n` sequences (common in stack traces/response bodies), the UI MUST render them as real line breaks (`whitespace-pre-wrap` / `<br/>`).
- Clipboard copy MUST normalize newlines to real line breaks (CRLF) so pasted logs are readable in editors.

### Stack Trace Section
- Must use ScrollArea with explicit max height
- Error location visible at top, not hidden below fold
- File paths should include full relative paths from `internal/` or `pkg/`

## Visual Reference

See `spec/08-error-manage/02-error-modal/screenshots/error-modal-overview-e9005.png` for the canonical screenshot.

---

*Last Updated: 2026-04-09*
