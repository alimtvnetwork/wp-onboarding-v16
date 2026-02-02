# Issue: WebSocket Upgrade Failures in Middleware

> **Category:** Backend/WebSocket  
> **Severity:** Feature-breaking  
> **Fixed:** 2026-02-02

---

## Symptoms

- WebSocket connections fail with 500 errors
- Browser console shows: `WebSocket connection to 'ws://localhost:8080/ws' failed`
- Go logs show: `websocket: response does not implement http.Hijacker`
- Works without logging middleware, breaks with it

---

## Root Cause

Custom `responseWriter` wrapper in logging middleware **hides the underlying interfaces**:

```go
// BROKEN: This wrapper doesn't expose Hijacker interface
type responseWriter struct {
    http.ResponseWriter
    status int
    size   int
}
```

WebSocket upgrade requires `http.Hijacker` interface to take over the TCP connection. When the middleware wraps the response, `gorilla/websocket` can't find the interface.

---

## Solution

### Implement Interface Delegation

Add methods to expose underlying interfaces:

```go
// Unwrap returns the underlying ResponseWriter (Go 1.20+ pattern)
func (rw *responseWriter) Unwrap() http.ResponseWriter {
    return rw.ResponseWriter
}

// Flush implements http.Flusher for streaming responses
func (rw *responseWriter) Flush() {
    if flusher, ok := rw.ResponseWriter.(http.Flusher); ok {
        flusher.Flush()
    }
}

// Push implements http.Pusher for HTTP/2 server push
func (rw *responseWriter) Push(target string, opts *http.PushOptions) error {
    if pusher, ok := rw.ResponseWriter.(http.Pusher); ok {
        return pusher.Push(target, opts)
    }
    return http.ErrNotSupported
}
```

### Why This Works

Go's `http.ResponseController` (1.20+) uses `Unwrap()` to find hidden interfaces. The WebSocket upgrader can now access `Hijacker` through the unwrap chain.

---

## Alternative: Skip Middleware for WebSocket

If unwrap doesn't work, skip logging middleware for WS routes:

```go
// In router setup
wsHandler := hub.ServeWS()  // No middleware wrapper
mux.Handle("/ws", wsHandler)

// API routes still get middleware
apiHandler := loggingMiddleware(apiRouter)
mux.Handle("/api/", apiHandler)
```

---

## Verification

1. Start backend: `.\run.ps1`
2. Open browser to `http://localhost:8080`
3. Check DevTools Network → WS tab
4. Should see WebSocket connected (101 Switching Protocols)
5. No 500 errors in Go console

---

## Prevention

When creating middleware that wraps `http.ResponseWriter`:
1. **Always implement `Unwrap()`** - Required for interface discovery
2. **Implement `Flush()`** - Required for SSE and streaming
3. **Consider `Hijacker`** - Required for WebSockets
4. **Test WebSocket routes** - They're often forgotten in middleware testing

---

## Related Files

- `backend/internal/api/middleware/middleware.go` - Logging middleware
- `backend/internal/ws/hub.go` - WebSocket hub
- `backend/internal/api/router.go` - Route registration
