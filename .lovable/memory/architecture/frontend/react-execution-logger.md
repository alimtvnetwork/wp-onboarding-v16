# Memory: architecture/frontend/react-execution-logger
Updated: 2026-02-06

## Overview

A custom React-level execution logger that tracks the complete function call-chain leading up to an error. This provides deep visibility into React component lifecycles, effects, handlers, and API calls for debugging.

---

## Purpose

When an error occurs, knowing "what called what" is critical. This logger:

1. Tracks all function executions with parent-child relationships
2. Captures component renders and re-renders
3. Logs useEffect triggers with dependencies
4. Records event handler invocations
5. Traces API calls with method/endpoint

---

## Configuration

Controlled by `debugMode` setting. When disabled, all logging methods are no-ops for zero performance impact.

```typescript
// Enable/disable at runtime
useExecutionLogger.getState().setEnabled(true);
```

---

## Store Implementation

**File:** `src/hooks/useExecutionLogger.ts`

### State Shape

```typescript
interface ExecutionLogEntry {
  id: string;           // Unique entry ID
  timestamp: number;    // Date.now()
  type: 'function' | 'component' | 'effect' | 'handler' | 'api';
  name: string;         // Function/component name
  context?: string;     // Additional context (component name for handlers)
  args?: unknown[];     // Function arguments
  result?: unknown;     // Return value (if captured)
  error?: string;       // Error message if threw
  parentId?: string;    // Parent entry ID (for call chain)
  duration?: number;    // Execution time in ms
}

interface ExecutionLoggerState {
  entries: ExecutionLogEntry[];
  enabled: boolean;
  callStack: string[];  // Current call stack for parent tracking
  maxEntries: number;   // Rolling buffer size (default 100)
}
```

---

## Logger Methods

### logFunction

Track any function execution:

```typescript
const { logFunction } = useExecutionLogger();

function fetchUserData(userId: string) {
  logFunction('fetchUserData', [userId]);
  // ... implementation
}
```

### logComponent

Track component renders:

```typescript
function UserCard({ user }: Props) {
  const { logComponent } = useExecutionLogger();
  logComponent('UserCard', { userId: user.id });
  
  return <div>...</div>;
}
```

### logEffect

Track useEffect executions:

```typescript
useEffect(() => {
  logEffect('loadUserData', [userId]);
  loadUserData();
}, [userId]);
```

### logHandler

Track event handlers:

```typescript
const handleClick = () => {
  logHandler('onClick', 'DeleteButton');
  // ... handler logic
};
```

### logApiCall

Track API requests:

```typescript
async function getPlugins() {
  logApiCall('GET', '/api/v1/plugins');
  return await fetch('/api/v1/plugins');
}
```

---

## Call Chain Building

The logger maintains a call stack to track parent-child relationships:

```typescript
logFunction: (name, args) => {
  const parentId = state.callStack[state.callStack.length - 1];
  const entry = {
    id: generateId(),
    parentId,
    type: 'function',
    name,
    args,
    timestamp: Date.now()
  };
  
  // Push to entries and call stack
  set(state => ({
    entries: [...state.entries.slice(-maxEntries), entry],
    callStack: [...state.callStack, entry.id]
  }));
}
```

### Pop from Stack

After function completes:

```typescript
endFunction: (id, result, error) => {
  set(state => ({
    callStack: state.callStack.filter(i => i !== id),
    entries: state.entries.map(e => 
      e.id === id ? { ...e, result, error, duration: Date.now() - e.timestamp } : e
    )
  }));
}
```

---

## Formatted Output

### getFormattedChain()

Returns a human-readable call chain:

```typescript
const chain = useExecutionLogger.getState().getFormattedChain();
// Output:
// 1. [component] PluginList rendered
// 2. [effect] loadPlugins triggered (deps: [siteId])
// 3. [api] GET /api/v1/plugins
// 4. [function] handleApiResponse called with [Response]
// 5. [handler] onClick in RefreshButton
// 6. [function] refreshData called
```

### getRecentEntries(count)

Get the N most recent entries as objects:

```typescript
const recent = useExecutionLogger.getState().getRecentEntries(10);
```

---

## Integration with Error Store

**File:** `src/stores/errorStore.ts`

When capturing an error, include the execution chain:

```typescript
captureError: (input) => {
  const executionChain = useExecutionLogger.getState().getFormattedChain();
  
  const error: CapturedError = {
    ...input,
    executionChain,
    executionEntries: useExecutionLogger.getState().getRecentEntries(20),
  };
  
  set(state => ({
    currentError: error,
    recentErrors: [...state.recentErrors, error].slice(-50)
  }));
}
```

---

## UI Display

**File:** `src/components/errors/GlobalErrorModal.tsx`

### Stack Tab > Frontend Sub-tab

When debug mode is enabled:

```tsx
{error.executionChain && (
  <div className="space-y-2">
    <div className="flex items-center justify-between">
      <h4>React Execution Chain</h4>
      <Button size="sm" onClick={() => copyToClipboard(error.executionChain)}>
        <Copy className="h-3 w-3 mr-1" /> Copy
      </Button>
    </div>
    <pre className="text-xs bg-muted p-3 rounded whitespace-pre-wrap">
      {error.executionChain}
    </pre>
  </div>
)}
```

When debug mode is off:

```tsx
<div className="text-muted-foreground text-sm">
  <AlertCircle className="h-4 w-4 inline mr-2" />
  Enable Debug Mode in settings to capture React execution chain.
</div>
```

---

## Performance Considerations

1. **No-op when disabled**: All log methods return immediately if `enabled` is false
2. **Rolling buffer**: Only keeps last N entries (default 100)
3. **Lazy formatting**: `getFormattedChain()` only computed on demand
4. **No deep cloning**: Args are stored by reference

---

## Best Practices

### DO

```typescript
// Log at function entry
function processData(items: Item[]) {
  logFunction('processData', [items.length]); // Log count, not full array
}

// Log handlers with context
<Button onClick={() => {
  logHandler('onClick', 'SaveButton');
  handleSave();
}}>
```

### DON'T

```typescript
// Don't log inside tight loops
items.forEach(item => {
  logFunction('processItem', [item]); // BAD - too many entries
});

// Don't log sensitive data
logFunction('authenticate', [password]); // BAD - logs password
```

---

## Related Files

- `src/hooks/useExecutionLogger.ts` - Logger implementation
- `src/stores/errorStore.ts` - Error capture integration
- `src/components/errors/GlobalErrorModal.tsx` - UI display
- `backend/config.json` - `logging.frontendDebugMode` setting
