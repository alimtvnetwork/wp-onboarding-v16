# Memory: issues-fixed/retry-debounce-dedup-anti-patterns
Updated: 2026-02-12

## NEVER DO — Retry & Deduplication Anti-Patterns

### 1. React Query Defaults
NEVER use default QueryClient settings. Always set `retry: false` and `refetchOnWindowFocus: false` globally. Data refreshes must be explicit user actions only.

### 2. WebSocket useEffect Dependencies
NEVER put callbacks, computed strings, or dynamic labels in `useEffect` dependency arrays for WebSocket listeners. Move them into `useRef` to prevent re-subscription on every render, which causes duplicate/triple event processing and progress jumps.

### 3. Mutation Deduplication
NEVER rely only on UI-level button `disabled` state to prevent duplicate mutations. Always implement an API-method-level dedup lock using an IIFE closure with an `inFlight` Set. Return a local `E_DEDUP` error without hitting the network. For publish-like operations, add a post-success cooldown (30s) to prevent auto-re-triggering.

### 4. Toast Notifications
NEVER use raw `toast()` from sonner in components that receive WebSocket events. Always use `dedupToast` (from `src/lib/dedupToast.ts`) which has a 3-second dedup window to prevent duplicate notifications from overlapping WS + local state sources.

### 5. WebSocket Completion Lock
NEVER process WebSocket lifecycle events (publish_complete, restore_complete) without a `useRef` completion lock. Once a lifecycle is done, set `completedRef.current = true` and gate all subsequent event handlers on `!completedRef.current`. This prevents late-arriving events from previous sessions leaking through.

### 6. Background/Speculative Queries
NEVER fire polling queries for speculative targets (e.g., siteId=0) without `meta: { suppressGlobalError: true }`. These queries fail by design and must not trigger the global error modal.

### 7. Circuit Breaker
Always wrap polling and health-check calls in the circuit breaker (`withCircuitBreaker`) to prevent error storms against persistently failing endpoints.

## Related
- Full retrospective: `spec/error-resolution/02-retry-debounce-dedup-fixes.md`
