# Memory: features/error-handling-and-debugging

> **Location:** `.lovable/memory/features/error-handling-and-debugging.md`  
> **Updated:** 2026-02-02

---

## Overview

A robust error handling system captures detailed context for every failure. The UI provides interactive modals with copyable debug data, enabling users to easily share full diagnostic information for troubleshooting.

---

## Error Detail Modal

### Location
`src/components/errors/ErrorDetailModal.tsx`

### Features

1. **Tabbed Interface**
   - Overview: Message, details, file location
   - Stack Trace: Full trace with copy button
   - Request/Response: API call data
   - Suggested Fixes: Error code-specific recommendations

2. **Developer Debug Level**
   - Complete stack traces
   - Request/response data
   - Related log entries
   - File and line number location

3. **Copy Functionality**
   - Copy individual sections
   - Copy full Markdown-formatted report
   - Shareable format for support tickets

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

## Suggested Fixes

The modal provides context-aware suggestions based on error code:

```typescript
const fixes: Record<string, string[]> = {
  E2001: ["Verify WordPress REST API is enabled", "Check site URL"],
  E3002: ["Verify the plugin path exists", "Check permissions"],
  E5001: ["Ensure git is installed", "Verify repository URL"],
  // ... more codes
};
```

---

## Integration

- Errors page shows list of errors with expandable details
- E2E test failures open error modal on click
- Copyable reports for AI/support assistance

---

*Full debug info with stack trace, request/response, and suggested fixes.*
