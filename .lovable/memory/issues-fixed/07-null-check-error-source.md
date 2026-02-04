# Issue #07: Null-Check, Error Source Reporting, and Enhanced Call Chain

## Problem (Original)
Multiple UI crashes (blank screens) occurred when opening Add Site / Edit Site dialogs. Errors were not traceable because source function names were missing from error reports.

## Problem (Enhanced - v1.14.0)
Error reports lacked sufficient detail for debugging:
1. No call chain visibility - couldn't see which function called which
2. File paths missing or unparsed in stack traces
3. No trigger context - unknown what UI action caused the error
4. No enforcement that `source` is always provided

## Root Causes
1. **Missing null checks** in render paths caused exceptions when data was undefined
2. **Error reports lacked source** (function name / file) making debugging difficult
3. **Radix Select empty value crash**: `SelectItem` with `value=""` crashes the UI
4. **Stack trace parsing** only extracted first frame, missing the full call chain
5. **No structured invocation chain** to show function relationships

## Solution (v1.14.0 Enhanced)

### 1. Enhanced Error Store (`errorStore.ts`)
- Added new `CapturedError` fields:
  - `invocationChain: string[]` - Array of function calls leading to error
  - `parsedFrames: StackFrame[]` - All parsed stack frames with file/line/column
  - `triggerComponent?: string` - UI component that initiated action
  - `triggerAction?: string` - User action description
- New `parseFullStackTrace()` function:
  - Parses ALL stack frames (not just first)
  - Handles dev (full paths) and prod (minified) formats
  - Filters internal/library frames vs app code
  - Builds human-readable invocation chain
- New `ErrorContext` interface enforcing proper error metadata

### 2. Enhanced Error Modal (`GlobalErrorModal.tsx`)
- **Overview Tab**: Added "Call Chain" section with visual tree display
- **Trigger Context Badge**: Shows component + action that caused error
- **Stack Trace Tab**: 
  - Toggle between "Parsed" table view and "Raw" view
  - Option to show/hide internal frames
  - Sortable table with function, file, line columns
- **Enhanced Report**: `generateErrorReport()` now includes:
  - Trigger context section
  - Invocation chain section
  - Parsed stack frames table

### 3. Mandatory Error Context Pattern
All async handlers MUST follow this pattern:
```typescript
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
    throw error;
  }
};
```

## Requirements Going Forward

### Mandatory for ALL Error Handlers
1. **Every `captureException` call MUST include `source`**: Format "ComponentName.functionName"
2. **User-triggered actions MUST include**:
   - `triggerComponent`: UI component name
   - `triggerAction`: Action description (e.g., "save_clicked", "delete_initiated")
3. **Never swallow errors**: Always call `captureException` before catching

### Render Path Safety
- Handle undefined/null data with optional chaining and default values
- Never use empty string as Radix Select item value (use `"__none__"` sentinel)

### Error Modal Requirements
- Display invocation chain (which function called which)
- Show file paths with line numbers for each frame
- Provide parsed table view AND raw stack trace
- Include trigger context (component + action)

## Files Modified

### Core Error System
- `src/stores/errorStore.ts` - Enhanced with full stack parsing and new fields
- `src/components/errors/GlobalErrorModal.tsx` - Enhanced UI with call chain display

### Components Updated with Error Context
- `src/components/sites/AddSiteDialog.tsx`
- `src/components/sites/EditSiteDialog.tsx`
- `src/pages/Plugins.tsx`
- `src/App.tsx`

### Documentation
- `.lovable/memory/architecture/frontend/error-reporting-standards.md` (NEW)
- `.lovable/memory/issues-fixed/07-null-check-error-source.md` (this file)

## Example: Before vs After

### Before (Insufficient)
```
SyntaxError: Expected ',' or ']' at position 834
```

### After (Full Context)
```
## Error Report

### Trigger Context
**Component:** EditSiteDialog
**Action:** save_clicked
**Source:** EditSiteDialog.handleSave

### Invocation Chain
EditSiteDialog.handleSave (EditSiteDialog.tsx:142)
  └─ api.updateSiteMappings (api.ts:391)
       └─ request (api.ts:96)

### Parsed Stack Frames
| # | Function | File | Line |
|---|----------|------|------|
| 1 | handleSave | EditSiteDialog.tsx | 142 |
| 2 | updateSiteMappings | api.ts | 391 |
| 3 | request | api.ts | 96 |
```

## Verification Checklist
- [ ] Error modal shows invocation chain
- [ ] Stack trace tab has parsed table view
- [ ] Trigger context badge appears for user-triggered errors
- [ ] Copy Full Report includes all new sections
- [ ] All async handlers include proper `source` in error context
