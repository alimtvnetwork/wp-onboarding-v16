# Double Envelope Wrapping — Health Summary & Remote Logs

> **Created:** 2026-03-22  
> **Status:** 🔴 Open

---

## Root Cause

When the Go backend proxies requests to PHP endpoints that return a Universal Response Envelope, the Go service layer uses `DoApiCall[map[string]any]` which unmarshals the **entire PHP envelope** (including `Status`, `Results`, `Attributes`) into a `map[string]any`. The Go handler then wraps this in **another** Go-level envelope via `respondSuccess()`.

The React frontend receives a double-wrapped response:

```
Go Envelope → Results[0] = PHP Envelope → Results[0] = actual data
```

The `parseEnvelope()` function in React only unwraps one level (the Go envelope), so `response.data` ends up being the PHP envelope object instead of the actual health/logs data.

---

## Affected Endpoints

| Endpoint | Go Handler | PHP Handler |
|----------|-----------|-------------|
| `GET /sites/{id}/site-health-summary` | `GetRemoteSiteHealthSummary` | `handleSiteHealthSummary` |
| `GET /sites/{id}/site-settings` | `GetRemoteSiteSettings` | `handleGetSiteSettings` |
| `GET /sites/{id}/remote-logs` | `GetRemoteLogs` | `/logs/status` |

---

## Symptoms

1. **Health panel shows nothing** — `health.system` is `undefined` because `health` is the PHP envelope, not the health data
2. **Remote Logs panel empty** — `status.Files` is `undefined` for the same reason
3. **Settings panel may have same issue** — depends on how response is consumed

---

## Fix Options

### Option A: Unwrap in Go service layer (recommended)

In `RemoteSiteSettings.go` and similar service methods, after `DoApiCall` returns, check if the result is a PHP envelope and extract `Results[0]`:

```go
func unwrapPhpEnvelope(data map[string]any) map[string]any {
    results, ok := data["Results"].([]any)
    if ok && len(results) > 0 {
        if inner, ok := results[0].(map[string]any); ok {
            return inner
        }
    }
    return data
}
```

### Option B: Unwrap in React

After `parseEnvelope` returns, check if data still looks like an envelope and unwrap again. Less clean but works.

---

## Validation

- Open Health panel → should show system info, plugins, integrations
- Open Logs panel → should show file list with sizes
- Open Settings panel → should show toggle states

---

## References

- `backend/internal/services/site/RemoteSiteSettings.go`
- `src/components/sites/SiteHealthSummaryPanel.tsx`
- `src/components/plugins/RemoteLogsPanel.tsx`
- `src/lib/api/envelope.ts` — `parseEnvelope()`
