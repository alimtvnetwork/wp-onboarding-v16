# Issue: API Errors Not Captured for Debugging

> **Category:** Frontend/UX  
> **Severity:** Debugging-blocking  
> **Fixed:** 2026-02-02

---

## Symptoms

- API calls fail silently or show generic toast messages
- No way to see full error details (status codes, request data, stack traces)
- Users can't provide useful debug info when reporting issues
- "Site service not available" with no actionable information

---

## Root Cause

1. **Toast-only errors**: Errors shown as brief toasts that disappear
2. **No error capture**: Errors not stored for later inspection
3. **Missing context**: No request/response metadata captured
4. **No copy functionality**: Users can't easily share error details

---

## Solution

### 1. Create Error Store (Zustand)

```typescript
// src/stores/errorStore.ts
export interface CapturedError {
  id: string;
  code: string;
  level: 'error' | 'warn' | 'info';
  message: string;
  details?: string;
  context?: Record<string, unknown>;
  stackTrace?: string;
  endpoint?: string;
  method?: string;
  requestBody?: unknown;
  responseStatus?: number;
  createdAt: string;
}

export const useErrorStore = create<ErrorStore>((set) => ({
  selectedError: null,
  isModalOpen: false,
  recentErrors: [],
  
  captureError: (error, meta) => {
    const captured = { /* build CapturedError */ };
    set((state) => ({
      recentErrors: [captured, ...state.recentErrors].slice(0, 50),
    }));
    return captured;
  },
  
  openErrorModal: (error) => set({ selectedError: error, isModalOpen: true }),
  closeErrorModal: () => set({ isModalOpen: false }),
}));
```

### 2. Create Global Error Modal

Tabbed interface with:
- **Overview**: Error code, message, timestamp
- **Request Info**: Endpoint, method, status, masked request body
- **Full Context**: JSON dump of all error metadata
- **Suggested Fixes**: Common solutions based on error code

### 3. Integrate with API Calls

```typescript
const showErrorWithModal = (apiError: ApiError, meta?: ErrorMeta) => {
  const captured = captureError(apiError, meta);
  toast.error(apiError.message, {
    description: "Click for details",
    action: {
      label: "View Details",
      onClick: () => openErrorModal(captured),
    },
  });
};
```

### 4. Copy Full Report

```typescript
const copyFullReport = () => {
  const report = `# Error Report
Code: ${error.code}
Message: ${error.message}
Endpoint: ${error.endpoint}
Status: ${error.responseStatus}
Time: ${error.createdAt}

## Request Data
${JSON.stringify(error.requestBody, null, 2)}

## Stack Trace
${error.stackTrace}
`;
  navigator.clipboard.writeText(report);
};
```

---

## Key Design Decisions

1. **Zustand over Context**: Simpler API, no provider nesting, works outside React
2. **50 error limit**: Prevent memory leaks from error floods
3. **Masked sensitive data**: Don't expose passwords/tokens in request body
4. **Tabbed modal**: Don't overwhelm users, progressive disclosure

---

## Verification

1. Stop the backend
2. Try to add a site in the UI
3. Toast should appear with "View Details" button
4. Click "View Details" → Modal opens with full error info
5. Click "Copy Full Report" → Markdown copied to clipboard

---

## Related Files

- `src/stores/errorStore.ts` - Zustand store for errors
- `src/components/errors/GlobalErrorModal.tsx` - Modal component
- `src/pages/Sites.tsx` - Integration example
- `src/App.tsx` - Global modal registration
