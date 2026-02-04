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

## Example Error Report

```markdown
## Error Report

**App:** WP Plugin Publish v1.14.0
**ID:** 1770222691715-xyz123
**Code:** E9003

### Trigger Context
**Component:** EditSiteDialog
**Action:** save_clicked
**Source:** EditSiteDialog.handleSave

### Invocation Chain
```
EditSiteDialog.handleSave (EditSiteDialog.tsx:142)
  └─ api.updateSiteMappings (api.ts:391)
       └─ request (api.ts:96)
            └─ JSON.parse [native]
```

### Message
Expected ',' or ']' after array element in JSON at position 834

### Parsed Stack Frames
| # | Function | File | Line |
|---|----------|------|------|
| 1 | handleSave | EditSiteDialog.tsx | 142 |
| 2 | updateSiteMappings | api.ts | 391 |
| 3 | request | api.ts | 96 |
```

## Enforcement

- Code reviews must verify error handling compliance
- AI agents must include proper error context in all generated code
- Missing `source` parameter should trigger linting warnings

## Related Documentation

- `.lovable/memory/issues-fixed/07-null-check-error-source.md`
- `src/stores/errorStore.ts` - Error store implementation
- `src/components/errors/GlobalErrorModal.tsx` - Error display UI
