# Memory: issues-fixed/retry-debounce-dedup-anti-patterns
Updated: 2026-02-12

## ⛔ ABSOLUTE BAN — Never Suggest or Write This Code

**NEVER suggest adding `retry: true`, `retry: 1`, `retry: 2`, `retry: 3`, or any positive retry value to ANY React Query configuration in this project.** This was the ROOT CAUSE of cascading failures: React Query's default `retry: 3` caused triple error toasts, triple API calls on failure, and invisible network storms. The fix was setting `retry: false` globally and it must NEVER be reverted or overridden.

**NEVER suggest adding `refetchOnWindowFocus: true` to ANY query.** This caused 10-20+ simultaneous API requests every time the user switched browser tabs, triggering rate limiting and stale error modals reappearing. The fix was `refetchOnWindowFocus: false` globally.

**These settings are INTENTIONAL and PERMANENT. Do not "improve" them. Do not add retry logic "for reliability." The project explicitly chose manual-refresh-only as its data fetching strategy.**

### Root Cause Summary
The original bugs were introduced by AI assistants who added `retry` and `refetchOnWindowFocus` settings thinking they would improve reliability. Instead, they caused:
- **Triple error toasts** from a single API failure (retry 3× = 3 error callbacks)
- **Tab-switch refetch storms** (10-20 queries firing simultaneously on focus)
- **Auto-publish infinite loops** (publish success → file watcher detects changes → auto-publish → repeat)
- **Duplicate WebSocket events** (useEffect re-subscribing every render due to unstable deps)
- **Ghost progress jumps** (late events from previous sessions processed by new dialog instances)

## NEVER DO — Retry & Deduplication Anti-Patterns

### 1. React Query Defaults
NEVER use default QueryClient settings. Always set `retry: false` and `refetchOnWindowFocus: false` globally. Data refreshes must be explicit user actions only. NEVER suggest reverting this.

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
- Full retrospective with code examples: `spec/07-error-manage/03-error-resolution/02-retry-debounce-dedup-fixes.md`
