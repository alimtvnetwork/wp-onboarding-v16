# Issue #07: Null-Check and Error Source Reporting

## Problem
Multiple UI crashes (blank screens) occurred when opening Add Site / Edit Site dialogs. Errors were not traceable because source function names were missing from error reports.

## Root Causes
1. **Missing null checks** in render paths caused exceptions when data was undefined
2. **Error reports lacked source** (function name / file) making debugging difficult
3. **Radix Select empty value crash**: `SelectItem` with `value=""` crashes the UI

## Solution
1. All async handlers wrapped in try/catch with explicit `source` in `captureException`
2. Every `captureError` / `captureException` call includes `source: "ComponentName.functionName"`
3. Radix Select uses sentinel `"__none__"` instead of empty string for "no category"
4. `AppErrorBoundary` catches render-phase crashes and displays function/component info
5. `GlobalErrorHandler` catches unhandled promise rejections with stack extraction

## Requirements Going Forward
- **Every async handler** MUST wrap logic in try/catch and pass `source` to `captureException`
- **Render paths** must handle undefined/null data gracefully (optional chaining, default values)
- **Never use empty string** as a Radix Select item value
- **Error modals** must display the originating function and endpoint

## Files Modified
- `src/components/sites/AddSiteDialog.tsx`
- `src/components/sites/EditSiteDialog.tsx`
- `src/components/shared/CategorySelect.tsx`
- `src/stores/errorStore.ts`
- `src/components/errors/AppErrorBoundary.tsx`
- `src/App.tsx`
