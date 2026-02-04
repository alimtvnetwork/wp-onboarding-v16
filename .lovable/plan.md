

# Enhanced Error Reporting System

## Summary

The user requests a significant improvement to the error reporting system to make errors more detailed, traceable, and actionable. The current implementation captures basic stack traces but lacks:

1. **Call chain visibility** - No clear hierarchy showing which function called which
2. **File paths in stack traces** - Often missing or unparsed
3. **Frontend invocation context** - What UI action triggered the error
4. **Persistent documentation** - Guidelines for future development

---

## Current State Analysis

### What Exists

- `errorStore.ts` captures errors with basic stack trace parsing
- `GlobalErrorModal.tsx` displays errors in a 5-tab layout
- `captureException` accepts a `source` parameter for manual annotation
- Stack traces are captured but parsing is minimal (only extracts first meaningful line)

### What's Missing

1. **Invocation Chain** - When an error occurs in `api.updateSiteMappings`, we don't see:
   - UI component that triggered it (e.g., "EditSiteDialog")
   - Handler function (e.g., "handleSave")
   - Intermediate calls (e.g., "saveMappings")

2. **File Path Extraction** - Current regex only parses first stack frame; fails on minified bundles

3. **Mandatory Source Context** - No enforcement that `source` is always provided

---

## Technical Implementation Plan

### Phase 1: Enhance Error Store (Core Changes)

**File: `src/stores/errorStore.ts`**

```text
Changes:
1. Add new CapturedError fields:
   - `invocationChain: string[]` - Array of function calls leading to error
   - `triggerComponent?: string` - UI component that initiated the action
   - `triggerAction?: string` - User action (click, submit, etc.)

2. Enhance stack trace parsing:
   - Parse ALL stack frames, not just first
   - Handle both dev (full paths) and prod (minified) formats
   - Extract file:line:column for each frame

3. Add `createErrorContext` helper:
   - Generates consistent context object
   - Captures component name, function name, action type
   - Builds invocation chain from call sites
```

**New Interface:**

```typescript
interface ErrorContext {
  source: string;           // REQUIRED: "ComponentName.functionName"
  triggerComponent?: string; // UI component (EditSiteDialog)
  triggerAction?: string;    // User action (save_clicked)
  parentSource?: string;     // Caller function for chain building
  context?: Record<string, unknown>;
}
```

### Phase 2: Enhanced Stack Trace Parsing

**File: `src/stores/errorStore.ts`**

```text
New function: parseFullStackTrace(stack: string)

Returns:
{
  frames: Array<{
    function: string;
    file: string;
    line: number;
    column: number;
    isInternal: boolean; // true if from node_modules
  }>;
  primaryFrame: { function, file, line } | null;
  invocationChain: string[];
}

Features:
- Parses all lines matching "at X (file:line:col)"
- Filters out internal/library frames
- Builds human-readable invocation chain
- Handles async/Promise frames
```

### Phase 3: Update Error Report Generation

**File: `src/components/errors/GlobalErrorModal.tsx`**

```text
Update generateErrorReport() to include:

### Invocation Chain
EditSiteDialog.handleSave
  → saveMappings
    → api.updateSiteMappings
      → request (src/lib/api.ts:96)
        → Error: JSON parse failed

### Trigger Context
- Component: EditSiteDialog
- Action: save_clicked
- Source: EditSiteDialog.handleSave

### Full Stack Trace (Parsed)
| # | Function           | File                  | Line |
|---|--------------------|-----------------------|------|
| 1 | handleSave         | EditSiteDialog.tsx    | 142  |
| 2 | saveMappings       | EditSiteDialog.tsx    | 98   |
| 3 | updateSiteMappings | api.ts                | 391  |
| 4 | request            | api.ts                | 96   |
```

### Phase 4: UI Enhancements

**File: `src/components/errors/GlobalErrorModal.tsx`**

```text
New sections in modal:

1. "Call Chain" section in Overview tab:
   - Visual tree/list of function calls
   - Clickable to expand each frame

2. Enhanced Stack Trace tab:
   - Parsed table view (function | file | line)
   - Raw view toggle
   - "App code only" filter toggle

3. Trigger Context badge:
   - Shows component + action that caused error
   - e.g., "EditSiteDialog → save_clicked"
```

### Phase 5: Enforce Source Annotation

**Update all components to use consistent pattern:**

```typescript
// EVERY async handler MUST follow this pattern
const handleSave = async () => {
  try {
    // ... logic
  } catch (error) {
    captureException(error, {
      source: "EditSiteDialog.handleSave",
      triggerComponent: "EditSiteDialog",
      triggerAction: "save_clicked",
      context: { siteId, pluginIds }
    });
    throw error; // Re-throw if needed
  }
};
```

### Phase 6: Update Memory Documentation

**New File: `.lovable/memory/architecture/frontend/error-reporting-standards.md`**

```markdown
# Error Reporting Standards

## Mandatory Requirements

1. **Every `captureException` call MUST include:**
   - `source`: Format "ComponentName.functionName"
   - `triggerComponent`: UI component name (if user-triggered)
   - `triggerAction`: User action description (if applicable)

2. **Never swallow errors without logging:**
   - Always call captureException before catching
   - Include full context (IDs, relevant state)

3. **Stack trace must show:**
   - File path (not just function name)
   - Line number
   - Full call chain (1-2 levels up)

## Error Report Must Include

- Invocation chain (which function called which)
- Trigger context (UI component + action)
- Parsed stack frames with file:line
- Full raw stack trace
- Request/response details (for API errors)
```

**Update: `.lovable/memory/issues-fixed/07-null-check-error-source.md`**

Add section on enhanced requirements:
- Call chain must be visible
- File paths must be extracted
- No credit for swallowed errors

---

## Files to Modify

| File | Changes |
|------|---------|
| `src/stores/errorStore.ts` | Add invocation chain, enhanced parsing, new fields |
| `src/components/errors/GlobalErrorModal.tsx` | Add call chain display, parsed stack table |
| `src/lib/api.ts` | Add source context to all error paths |
| `src/components/sites/EditSiteDialog.tsx` | Add full error context |
| `src/components/sites/AddSiteDialog.tsx` | Add full error context |
| `src/pages/Plugins.tsx` | Add full error context to publish/mapping handlers |
| `src/App.tsx` | Enhance GlobalErrorHandler with better chain extraction |
| `.lovable/memory/architecture/frontend/error-reporting-standards.md` | NEW: Standards doc |
| `.lovable/memory/issues-fixed/07-null-check-error-source.md` | Update with new requirements |

---

## Example Output (After Implementation)

```markdown
## Error Report

**App:** WP Plugin Publish v1.14.0
**ID:** 1770222691715-xyz123
**Code:** E9003
**Level:** error
**Timestamp:** 2026-02-04T16:31:31.715Z

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
| # | Function           | File                     | Line |
|---|--------------------| -------------------------|------|
| 1 | handleSave         | EditSiteDialog.tsx       | 142  |
| 2 | updateSiteMappings | api.ts                   | 391  |
| 3 | request            | api.ts                   | 96   |

### Full Stack Trace
```
SyntaxError: Expected ',' or ']' after array element in JSON at position 834
    at JSON.parse (<anonymous>)
    at request (http://localhost:5173/src/lib/api.ts:96:23)
    at updateSiteMappings (http://localhost:5173/src/lib/api.ts:391:3)
    at handleSave (http://localhost:5173/src/components/sites/EditSiteDialog.tsx:142:5)
```
```

---

## Summary of Improvements

| Before | After |
|--------|-------|
| "SyntaxError at position 834" | Full call chain showing EditSiteDialog → api → JSON.parse |
| No file path | File path with line number for each frame |
| Missing trigger context | Shows "EditSiteDialog → save_clicked" |
| Basic stack dump | Parsed table + visual call chain |
| Optional source param | Mandatory source with component/action |

This ensures every error is immediately traceable to its origin in the UI and codebase.

