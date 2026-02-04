# Memory: architecture/frontend/logging-and-resilience
Updated: 2026-02-04

---

## Overview

The frontend implements a comprehensive logging and resilience system for debugging and fault tolerance:

1. **Structured Logger** (`src/lib/logger.ts`) - Function-level tracing with file paths and line numbers
2. **Retry Logic** (`src/lib/retry.ts`) - Exponential backoff for failed operations
3. **Circuit Breaker** (`src/lib/circuitBreaker.ts`) - Prevents repeated calls to failing endpoints

---

## Logger API

```typescript
import { logger } from '@/lib/logger';

// Function tracing
logger.trace('fetchSites', 'enter', { userId: 123 });
// ... function body ...
logger.trace('fetchSites', 'exit', { count: 5 });

// Log levels
logger.debug('Verbose debugging info');
logger.info('Operation completed');
logger.warn('Potential issue detected');
logger.error('Operation failed', error, { context: 'extra data' });

// Export for diagnostics
const logText = logger.exportLogs();
```

**Configuration:**
```typescript
logger.configure({
  enabled: true,
  minLevel: 'trace',  // trace|debug|info|warn|error
  maxEntries: 500,    // In-memory buffer size
  consoleOutput: true
});
```

---

## Retry Logic

```typescript
import { withRetry } from '@/lib/retry';

const result = await withRetry(
  () => api.getSites(),
  { maxAttempts: 3, initialDelayMs: 1000 },
  { functionName: 'getSites', component: 'SitesPage' }
);
```

**Configuration:**
| Setting | Default | Description |
|---------|---------|-------------|
| `maxAttempts` | 3 | Total attempts including first try |
| `initialDelayMs` | 1000 | Base delay before first retry |
| `maxDelayMs` | 30000 | Maximum delay cap |
| `backoffMultiplier` | 2 | Exponential factor |
| `jitterFactor` | 0.1 | Randomization to prevent thundering herd |

Delay formula: `min(initialDelay × (multiplier ^ attempt), maxDelay) ± jitter`

---

## Circuit Breaker

```typescript
import { withCircuitBreaker, circuitBreaker } from '@/lib/circuitBreaker';

// Wrap operations
const result = await withCircuitBreaker('api.getSites', () => api.getSites());

// Check status
if (!circuitBreaker.canExecute('api.getSites')) {
  // Circuit is open, operation blocked
}

// Manual reset
circuitBreaker.reset('api.getSites');
circuitBreaker.resetAll();
```

**States:**
- **Closed**: Normal operation, failures counted
- **Open**: After threshold failures, calls blocked
- **Half-Open**: After cooldown, allows one test call

**Configuration:**
| Setting | Default | Description |
|---------|---------|-------------|
| `failureThreshold` | 5 | Failures before circuit opens |
| `cooldownMs` | 60000 | Time before retry allowed |
| `failureWindowMs` | 60000 | Window for counting failures |

---

## Settings Integration

All settings are configurable via:

1. **Backend config.json** - Seeds defaults:
```json
{
  "logging": {
    "frontendDebugMode": false,
    "retryMaxAttempts": 3,
    "retryInitialDelayMs": 1000,
    "circuitBreakerThreshold": 5,
    "circuitBreakerCooldownMs": 60000
  }
}
```

2. **Settings UI** - "Developer & Debugging" section with:
   - Frontend Debug Mode toggle
   - Retry attempts and delay inputs
   - Circuit breaker threshold and cooldown inputs
   - "Reset All Circuits" button

---

## Files

| File | Purpose |
|------|---------|
| `src/lib/logger.ts` | Structured logging with stack trace extraction |
| `src/lib/retry.ts` | Retry wrapper with exponential backoff |
| `src/lib/circuitBreaker.ts` | Circuit breaker pattern implementation |
| `src/pages/Settings.tsx` | UI for configuring resilience settings |
| `src/lib/api.ts` | Settings interface extended with logging fields |

---

## Best Practices

1. **Use logger.trace for function boundaries** - Enables duration tracking
2. **Wrap critical API calls with retry** - Network operations, auth flows
3. **Apply circuit breaker to external services** - WordPress API, third-party integrations
4. **Enable debug mode for troubleshooting** - Logs all function calls with file:line
5. **Export logs for support requests** - `logger.exportLogs()` generates diagnostic text

---

*Added in v1.17.0*
