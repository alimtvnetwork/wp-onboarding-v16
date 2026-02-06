# Memory: features/error-modal/multi-error-queue
Updated: 2026-02-06

## Overview

Phase 6 implements a multi-error queue UI that allows users to navigate between multiple errors and perform bulk copy operations.

## Error Store Enhancements

Added to `src/stores/errorStore.ts`:

```typescript
interface ErrorStore {
  // Queue state
  errorQueue: CapturedError[];
  currentQueueIndex: number;
  
  // New actions
  openErrorQueue: (errors: CapturedError[], startIndex?: number) => void;
  navigateQueue: (direction: 'prev' | 'next') => void;
  getQueuedErrorsMarkdown: () => string;
}
```

## Modal Navigation UI

When multiple errors are in the queue, the GlobalErrorModal header displays:

1. **Previous/Next buttons** - Navigate between errors (wraps around)
2. **Position indicator** - Shows "1 / 3" format
3. **Copy All button** - Copies all queued errors as markdown

## ErrorHistoryDrawer Integration

The drawer now supports:

1. **View Selected** button - Opens all selected errors in the queue modal
2. Multi-select persists when opening queue view

## Usage Examples

### Open single error (backward compatible)
```typescript
openErrorModal(capturedError);
// Sets queue to [capturedError], index 0
```

### Open multiple errors
```typescript
openErrorQueue([error1, error2, error3], 0);
// Opens error1, can navigate to error2, error3
```

### Copy all errors
```typescript
const markdown = getQueuedErrorsMarkdown();
// Returns combined markdown report
```

## Related Files

- `src/stores/errorStore.ts` - Queue state and actions
- `src/components/errors/GlobalErrorModal.tsx` - Navigation UI
- `src/components/errors/ErrorHistoryDrawer.tsx` - View Selected button
