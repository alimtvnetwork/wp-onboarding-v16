# 13.1 Error Components

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Standardized error display components providing consistent user feedback for validation errors, API failures, and system errors with actionable recovery options.

**Cross-References:**
- [Error Management](../../06-error-management/00-overview.md) - Error codes and handling
- [API Client](../15-api-client/00-overview.md) - Error interceptors
- [Theme System](../10-theme-system/00-overview.md) - Error styling

---

## 13.1.1 Error Component Hierarchy

```
ErrorBoundary (catches React errors)
├── ErrorPage (full-page errors: 404, 500)
├── ErrorAlert (inline contextual errors)
├── ErrorToast (transient notifications)
├── FieldError (form field validation)
└── ErrorBanner (persistent warnings)
```

---

## 13.1.2 ErrorBoundary

```typescript
interface ErrorBoundaryProps {
  children: React.ReactNode;
  fallback?: React.ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
  resetKeys?: unknown[];
}

class ErrorBoundary extends React.Component<ErrorBoundaryProps, State> {
  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Log to monitoring service
    this.props.onError?.(error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return this.props.fallback || <DefaultErrorFallback error={this.state.error} />;
    }
    return this.props.children;
  }
}
```

---

## 13.1.3 ErrorPage Component

```typescript
interface ErrorPageProps {
  code: number;          // HTTP status code
  title: string;         // "Page Not Found"
  description: string;   // User-friendly explanation
  actions?: Action[];    // Recovery actions
  showStack?: boolean;   // Show error stack (dev only)
}

// Predefined error pages
const NotFoundPage = () => (
  <ErrorPage
    code={404}
    title="Page Not Found"
    description="The page you're looking for doesn't exist or has been moved."
    actions={[
      { label: 'Go Home', href: '/', primary: true },
      { label: 'Go Back', onClick: () => history.back() },
    ]}
  />
);

const ServerErrorPage = () => (
  <ErrorPage
    code={500}
    title="Something Went Wrong"
    description="We're experiencing technical difficulties. Please try again later."
    actions={[
      { label: 'Retry', onClick: () => location.reload(), primary: true },
      { label: 'Report Issue', href: '/support' },
    ]}
  />
);
```

---

## 13.1.4 ErrorAlert Component

```typescript
interface ErrorAlertProps {
  variant: 'error' | 'warning' | 'info';
  title: string;
  description?: string;
  code?: string;           // Error code for support reference
  dismissible?: boolean;
  onDismiss?: () => void;
  actions?: Action[];
}

// Usage
<ErrorAlert
  variant="error"
  title="Failed to save file"
  description="The file could not be saved due to a conflict."
  code="FILE_6003"
  actions={[
    { label: 'Retry', onClick: handleRetry },
    { label: 'Force Save', onClick: handleForce },
  ]}
/>
```

---

## 13.1.5 Error Toast Pattern

```typescript
// Using Sonner for toast notifications
import { toast } from 'sonner';

// Error toast
toast.error('Failed to delete project', {
  description: 'You do not have permission to delete this project.',
  action: {
    label: 'Contact Admin',
    onClick: () => navigate('/support'),
  },
});

// Warning toast
toast.warning('Unsaved changes', {
  description: 'You have unsaved changes that will be lost.',
  action: {
    label: 'Save Now',
    onClick: handleSave,
  },
});
```

---

## 13.1.6 Form Field Errors

```typescript
interface FieldErrorProps {
  message: string;
  code?: string;
}

// Integrated with react-hook-form
<FormField
  control={form.control}
  name="email"
  render={({ field, fieldState }) => (
    <FormItem>
      <FormLabel>Email</FormLabel>
      <FormControl>
        <Input {...field} className={fieldState.error ? 'border-destructive' : ''} />
      </FormControl>
      {fieldState.error && (
        <FormMessage className="text-destructive text-sm">
          {fieldState.error.message}
        </FormMessage>
      )}
    </FormItem>
  )}
/>
```

---

## 13.1.7 Error Code Mapping

| Code Range | Category | UI Treatment |
|------------|----------|--------------|
| 1xxx | Authentication | Redirect to login |
| 2xxx | Authorization | Permission denied alert |
| 3xxx | Validation | Field-level errors |
| 4xxx | Project | Project-context banner |
| 5xxx | Spec | Editor error panel |
| 6xxx | File System | File operation alert |
| 7xxx | History | History-specific message |
| 8xxx | AI/LLM | AI panel error state |

---

## 13.1.8 Error Recovery Patterns

| Error Type | Recovery Action |
|------------|-----------------|
| Network timeout | Auto-retry with backoff |
| Auth expired | Prompt re-login |
| Conflict (409) | Show diff, offer merge |
| Rate limited | Show countdown timer |
| Server error | Retry button + support link |

---

## 13.1.9 Error Detail Modal

```typescript
interface ErrorDetailModalProps {
  error: CapturedError;
  onDismiss: () => void;
  onCopyStack: () => void;
  onMarkResolved?: () => void;
}

interface CapturedError {
  id: string;
  type: 'uncaught_error' | 'unhandled_rejection' | 'react_error' | 'api_error';
  message: string;
  stack?: string;
  componentStack?: string;
  timestamp: Date;
  context: {
    url: string;
    userAgent: string;
    sessionId: string;
    apiEndpoint?: string;
    httpStatus?: number;
  };
}

// Modal displays full error details with copy functionality
const ErrorDetailModal = ({ error, onDismiss, onCopyStack }: ErrorDetailModalProps) => (
  <Dialog open onOpenChange={onDismiss}>
    <DialogContent className="max-w-2xl max-h-[80vh] overflow-auto">
      <DialogHeader>
        <DialogTitle className="text-destructive">{error.type}</DialogTitle>
      </DialogHeader>
      
      <div className="space-y-4">
        <div>
          <Label>Message</Label>
          <p className="text-sm font-mono bg-muted p-2 rounded">{error.message}</p>
        </div>
        
        <div>
          <Label>Timestamp</Label>
          <p className="text-sm">{error.timestamp.toISOString()}</p>
        </div>
        
        {error.stack && (
          <div>
            <div className="flex justify-between items-center">
              <Label>Stack Trace</Label>
              <Button variant="ghost" size="sm" onClick={onCopyStack}>
                <Copy className="h-4 w-4 mr-1" /> Copy
              </Button>
            </div>
            <pre className="text-xs bg-muted p-2 rounded overflow-auto max-h-48">
              {error.stack}
            </pre>
          </div>
        )}
        
        {error.componentStack && (
          <div>
            <Label>Component Stack</Label>
            <pre className="text-xs bg-muted p-2 rounded overflow-auto max-h-32">
              {error.componentStack}
            </pre>
          </div>
        )}
        
        <div>
          <Label>Context</Label>
          <pre className="text-xs bg-muted p-2 rounded">
            {JSON.stringify(error.context, null, 2)}
          </pre>
        </div>
      </div>
    </DialogContent>
  </Dialog>
);
```

---

## 13.1.10 Acceptance Criteria

### ErrorBoundary Component (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EB-001 | Catches JavaScript errors in child component tree | Critical | Error injection test |
| EB-002 | Renders fallback UI when error caught | Critical | Component test |
| EB-003 | onError callback invoked with error and errorInfo | Critical | Callback test |
| EB-004 | componentStack included in errorInfo | High | Schema test |
| EB-005 | resetKeys prop triggers recovery on key change | High | Reset test |
| EB-006 | Default fallback shows user-friendly message | High | UI test |
| EB-007 | Errors logged to monitoring service | Critical | Integration test |

### ErrorPage Component (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EP-001 | Displays HTTP status code prominently | High | UI test |
| EP-002 | Displays title and description | High | UI test |
| EP-003 | Actions rendered as buttons/links | High | UI test |
| EP-004 | 404 page shows "Go Home" and "Go Back" actions | High | E2E test |
| EP-005 | 500 page shows "Retry" and "Report Issue" actions | High | E2E test |
| EP-006 | showStack=true displays error stack (dev only) | Medium | Conditional test |
| EP-007 | Responsive layout on mobile devices | High | Responsive test |

### ErrorAlert Component (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EA-001 | Variants: error (red), warning (yellow), info (blue) | Critical | Visual test |
| EA-002 | Title and description displayed | High | UI test |
| EA-003 | Error code displayed when provided | High | UI test |
| EA-004 | Dismissible=true shows close button | High | Interaction test |
| EA-005 | onDismiss called when dismissed | High | Callback test |
| EA-006 | Actions rendered as inline buttons | High | UI test |
| EA-007 | Icon matches variant (error/warning/info) | High | Visual test |

### Error Toast Pattern (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| ET-001 | toast.error() displays error notification | Critical | E2E test |
| ET-002 | toast.warning() displays warning notification | Critical | E2E test |
| ET-003 | Description displayed below title | High | UI test |
| ET-004 | Action button clickable and functional | High | Interaction test |
| ET-005 | Toast auto-dismisses after timeout | High | Timing test |
| ET-006 | Multiple toasts stack correctly | High | UI test |
| ET-007 | Toast dismissible via close button or swipe | High | Interaction test |

### Form Field Errors (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| FF-001 | Error message displayed below field | Critical | UI test |
| FF-002 | Field border turns destructive color on error | Critical | Visual test |
| FF-003 | Error clears when field becomes valid | Critical | Validation test |
| FF-004 | Error code displayed when provided | Medium | UI test |
| FF-005 | react-hook-form integration functional | High | Integration test |
| FF-006 | FormMessage component uses text-destructive | High | Visual test |

### Error Code Mapping (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CM-001 | 1xxx (Auth) errors redirect to login | Critical | E2E test |
| CM-002 | 2xxx (Authorization) shows permission denied alert | Critical | E2E test |
| CM-003 | 3xxx (Validation) shows field-level errors | Critical | E2E test |
| CM-004 | 6xxx (File System) shows file operation alert | High | E2E test |
| CM-005 | 8xxx (AI/LLM) shows AI panel error state | High | E2E test |
| CM-006 | Unknown codes show generic error message | High | Fallback test |

### Error Recovery Patterns (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| ER-001 | Network timeout triggers auto-retry with backoff | High | Retry test |
| ER-002 | Auth expired prompts re-login | Critical | E2E test |
| ER-003 | Conflict (409) shows diff and merge option | High | E2E test |
| ER-004 | Rate limited (429) shows countdown timer | High | E2E test |
| ER-005 | Server error shows retry button + support link | High | E2E test |
| ER-006 | Retry uses exponential backoff (1s, 2s, 4s, ...) | High | Timing test |

### Error Detail Modal (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| ED-001 | Modal displays error type, message, timestamp | Critical | UI test |
| ED-002 | Stack trace displayed in scrollable pre block | High | UI test |
| ED-003 | Component stack displayed for React errors | High | Conditional test |
| ED-004 | Context (URL, userAgent, sessionId) displayed | High | UI test |
| ED-005 | Copy stack trace button copies to clipboard | High | Clipboard test |
| ED-006 | Modal dismissible via close button or overlay | High | Interaction test |
| ED-007 | API endpoint and HTTP status shown for API errors | High | Conditional test |

### Error Aggregation Display (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AG-001 | Errors grouped by fingerprint in list | High | E2E test |
| AG-002 | Each group shows count, message, lastSeen | High | UI test |
| AG-003 | "Go Detail" button opens ErrorDetailModal | High | Interaction test |
| AG-004 | Expand shows sample errors from group | High | Interaction test |
| AG-005 | Status badge (new/acknowledged/resolved) displayed | High | Visual test |
| AG-006 | Filter by status functional | High | Filter test |

### Accessibility (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| A11Y-001 | Error alerts have role="alert" | Critical | ARIA test |
| A11Y-002 | Focus trapped in error modal when open | High | Focus test |
| A11Y-003 | Escape key dismisses modal | High | Keyboard test |
| A11Y-004 | Error messages readable by screen readers | Critical | Screen reader test |
| A11Y-005 | Color contrast meets WCAG AA for error text | High | Contrast test |

### Performance (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PF-001 | ErrorBoundary adds < 1ms render overhead | High | Benchmark test |
| PF-002 | Error list renders 1000 items smoothly | High | Performance test |
| PF-003 | Modal opens in < 100ms | High | Timing test |
| PF-004 | Stack trace copy completes in < 50ms | Medium | Timing test |

---

## Related Specs

- [Error Management](../../06-error-management/00-overview.md)
- [API Client](../15-api-client/00-overview.md)
- [Toast System](../10-theme-system/02-component-library.md)
- [Monitoring Dashboard](../17-monitoring/01-system-monitoring.md)
