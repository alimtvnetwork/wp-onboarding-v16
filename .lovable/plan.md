

# Enhanced Frontend Logging, Retry Logic & Error Recovery System (v1.17.0)

## Critical Issues Identified

### 1. **IMMEDIATE: Malformed version.json**
Lines 25-26 in `public/version.json` have a **syntax error** (missing closing array bracket):
```json
"changes": [
"date": "2026-02-04",  // ← Missing ] before this line
```

This causes the JSON parse error `E9003` at position 900. **Fix first before any other work.**

### 2. **Missing Comprehensive Frontend Logging**
No standardized function-level logging exists. Errors lack full invocation context, making debugging difficult.

### 3. **No Retry/Backoff Logic**
API failures are not retried. No exponential backoff or failure throttling exists.

---

## Solution Architecture

### Phase 1: Fix version.json (CRITICAL)

**File: `public/version.json`**

Replace lines 22-34 with:
```json
{
  "version": "1.15.0",
  "date": "2026-02-04",
  "title": "Enhanced Error Reporting System",
  "changes": [
    "📊 Full stack trace parsing with invocation chain visualization",
    "🔍 Parsed stack frames table view in Error Modal",
    "🏷️ Trigger context badges show Component → Action",
    "📋 Enhanced error reports with call chain and parsed frames",
    "✅ Mandatory source/triggerComponent/triggerAction in handlers",
    "📚 New error-reporting-standards.md documentation"
  ]
},
```

### Phase 2: Create Frontend Logger Utility

**New File: `src/lib/logger.ts`**

Implement structured logging with:
- Automatic file path + line number extraction via `Error().stack`
- Function entry/exit tracking with duration measurement
- Log level filtering (debug/info/warn/error)
- Configurable via settings (`logging.frontendDebugMode`)
- Buffers logs in memory (last 500 entries) for diagnostics export

**API:**
```typescript
export const logger = {
  trace(functionName: string, action: 'enter' | 'exit', context?: Record<string, unknown>): void
  debug(message: string, context?: Record<string, unknown>): void
  info(message: string, context?: Record<string, unknown>): void
  warn(message: string, context?: Record<string, unknown>): void
  error(message: string, error?: unknown, context?: Record<string, unknown>): void
  getLogs(filter?: { level?: string; search?: string }): LogEntry[]
  clearLogs(): void
}
```

### Phase 3: Retry Logic with Exponential Backoff

**New File: `src/lib/retry.ts`**

Implement configurable retry wrapper:
```typescript
interface RetryConfig {
  maxAttempts: number;        // Default: 3
  initialDelayMs: number;     // Default: 1000
  maxDelayMs: number;         // Default: 30000
  backoffMultiplier: number;  // Default: 2 (exponential)
  shouldRetry: (error: unknown, attempt: number) => boolean;
}

async function withRetry<T>(
  operation: () => Promise<T>,
  config: Partial<RetryConfig>,
  context: { functionName: string; component?: string }
): Promise<T>
```

**Features:**
- Exponential backoff: delay = `min(initialDelay * (backoffMultiplier ^ attempt), maxDelay)`
- Jitter to prevent thundering herd
- Logs each retry attempt with `logger.warn`
- Captures final failure with full context

### Phase 4: Failure Throttling (Circuit Breaker)

**New File: `src/lib/circuitBreaker.ts`**

Prevent repeated calls to failing functions:
```typescript
class CircuitBreaker {
  private failureCounts = new Map<string, number>();
  private lastFailureTime = new Map<string, number>();
  
  canExecute(functionKey: string): boolean {
    const failures = this.failureCounts.get(functionKey) || 0;
    const threshold = getSettings().logging.circuitBreakerThreshold || 5;
    
    if (failures >= threshold) {
      const cooldown = getSettings().logging.circuitBreakerCooldownMs || 60000;
      const elapsed = Date.now() - (this.lastFailureTime.get(functionKey) || 0);
      return elapsed > cooldown;
    }
    return true;
  }
  
  recordSuccess(functionKey: string): void
  recordFailure(functionKey: string): void
  reset(functionKey: string): void
}
```

### Phase 5: Settings Integration

**Backend Changes:**

1. **Add to seedable config** (`backend/config.json`):
```json
"logging": {
  "level": "info",
  "debugMode": false,
  "frontendDebugMode": false,
  "retryMaxAttempts": 3,
  "retryInitialDelayMs": 1000,
  "circuitBreakerThreshold": 5,
  "circuitBreakerCooldownMs": 60000
}
```

2. **Seed new settings** (`backend/internal/config/config.go`):
```go
"logging.frontendDebugMode": cfg.Logging.FrontendDebugMode,
"logging.retryMaxAttempts": 3,
"logging.retryInitialDelayMs": 1000,
"logging.circuitBreakerThreshold": 5,
"logging.circuitBreakerCooldownMs": 60000,
```

3. **Update GetSettings handler** to return actual DB values instead of hardcoded

**Frontend Changes:**

**File: `src/pages/Settings.tsx`**

Add new "Developer" section:
```tsx
<Card>
  <CardHeader>
    <CardTitle>Developer & Debugging</CardTitle>
  </CardHeader>
  <CardContent>
    <Switch 
      checked={settings.logging.frontendDebugMode}
      label="Frontend Debug Mode"
      description="Log all function calls with file paths and line numbers"
    />
    <Input 
      label="Retry Max Attempts" 
      type="number" 
      value={settings.logging.retryMaxAttempts}
    />
    <Input 
      label="Circuit Breaker Threshold" 
      type="number"
      value={settings.logging.circuitBreakerThreshold}
    />
  </CardContent>
</Card>
```

### Phase 6: Apply Logging & Retry to Critical Paths

**Wrap all async handlers:**

**Example: `src/lib/api.ts`**
```typescript
async function request<T>(endpoint: string, options?: RequestInit): Promise<ApiResponse<T>> {
  const functionName = 'api.request';
  logger.trace(functionName, 'enter', { endpoint, method: options?.method });
  
  try {
    const result = await withRetry(
      () => fetchWithTimeout(endpoint, options),
      { 
        maxAttempts: getRetryConfig().maxAttempts,
        initialDelayMs: getRetryConfig().initialDelayMs
      },
      { functionName, component: 'ApiClient' }
    );
    
    logger.trace(functionName, 'exit', { endpoint, success: result.success });
    return result;
  } catch (error) {
    logger.error(`Request failed for ${endpoint}`, error, { endpoint });
    throw error;
  }
}
```

Apply to:
- All API client methods (`src/lib/api.ts`)
- Query hooks (`usePlugins`, `useSites`, `useSettings`)
- WebSocket reconnection logic (`src/lib/ws.ts`)
- Form submission handlers

### Phase 7: Documentation Updates

**New File: `.lovable/memory/architecture/frontend/logging-and-resilience.md`**

Document:
- Frontend logger API and usage patterns
- Retry configuration and best practices
- Circuit breaker behavior and tuning
- How to enable/disable via Settings UI
- Diagnostic log export for support

**Update: `backend/config.json` → v1.17.0**

**Update: `public/version.json` → v1.17.0**

Changelog:
- "🐛 Fixed malformed JSON in version changelog causing parse errors"
- "📊 Frontend function-level logging with file paths and line numbers"
- "🔄 Automatic retry with exponential backoff for failed operations"
- "⚡ Circuit breaker prevents repeated calls to failing functions"
- "⚙️ Configurable retry and throttling via Settings UI and seed config"
- "📚 New logging-and-resilience.md architecture documentation"

---

## Implementation Order

1. **Fix version.json syntax error** (lines 22-34)
2. Create `src/lib/logger.ts` with structured logging
3. Create `src/lib/retry.ts` with exponential backoff
4. Create `src/lib/circuitBreaker.ts` with failure throttling
5. Update backend config schema + seeding
6. Implement GetSettings/UpdateSettings handlers to use AppConfig DB
7. Add Developer section to Settings UI
8. Apply logging + retry to api.ts and critical handlers
9. Update documentation and version to 1.17.0

---

## Testing Strategy

1. **Verify version.json loads** without JSON parse errors
2. **Enable frontend debug mode** → verify console shows function traces
3. **Simulate network failure** → verify retry attempts logged
4. **Trigger 5+ failures** → verify circuit breaker opens
5. **Wait cooldown period** → verify circuit breaker resets
6. **Export diagnostics** → verify full log history included

