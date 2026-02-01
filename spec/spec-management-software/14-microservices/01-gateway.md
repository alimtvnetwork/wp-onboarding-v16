# Phase 2: Gateway Service Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Phase:** 2 of 9  
**Service:** `gateway`  
**Port:** 8080  

---

## Overview

The Gateway Service is the single entry point for all SpecBuilder Pro API requests. It handles routing, authentication, rate limiting, and delegates requests to appropriate microservices.

**Cross-References:**
- [Shared Packages](../13-shared-packages/00-overview.md)
- [Error Management](../06-error-management/00-overview.md)
- [Security Architecture](../04-coding-guidelines/00-overview.md)

---

## Architecture

```
                              ┌─────────────────────────────────────────┐
                              │              Gateway :8080               │
                              ├─────────────────────────────────────────┤
                              │                                          │
    HTTP Request ────────────►│  ┌──────────────────────────────────┐   │
                              │  │         Middleware Chain          │   │
                              │  │  ┌─────────────────────────────┐  │   │
                              │  │  │ 1. Recovery (panic handler) │  │   │
                              │  │  │ 2. Request ID injection     │  │   │
                              │  │  │ 3. Logging (with source)    │  │   │
                              │  │  │ 4. CORS                     │  │   │
                              │  │  │ 5. Rate Limiting            │  │   │
                              │  │  │ 6. Authentication           │  │   │
                              │  │  │ 7. Request Validation       │  │   │
                              │  │  └─────────────────────────────┘  │   │
                              │  └──────────────────────────────────┘   │
                              │                    │                     │
                              │                    ▼                     │
                              │  ┌──────────────────────────────────┐   │
                              │  │           Router                  │   │
                              │  │  /api/specs/*    → SpecMgr       │   │
                              │  │  /api/history/*  → Chronicle     │   │
                              │  │  /api/ai/*       → AI-Bridge     │   │
                              │  │  /api/search/*   → Scout         │   │
                              │  │  /api/flows/*    → Nexus-Flow    │   │
                              │  │  /health         → Local         │   │
                              │  └──────────────────────────────────┘   │
                              │                    │                     │
                              └────────────────────┼─────────────────────┘
                                                   │
                    ┌──────────────────────────────┼──────────────────────────────┐
                    │              │               │               │              │
                    ▼              ▼               ▼               ▼              ▼
              ┌──────────┐  ┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐
              │ SpecMgr  │  │Chronicle │   │AI-Bridge │   │  Scout   │   │Nexus-Flow│
              │  :8081   │  │  :8083   │   │  :8082   │   │  :8084   │   │  :9000   │
              └──────────┘  └──────────┘   └──────────┘   └──────────┘   └──────────┘
```

---

## Directory Structure

```
cmd/gateway/
├── main.go                 # Entry point
└── config.yaml             # Default config

internal/gateway/
├── server/
│   ├── server.go           # HTTP server setup
│   ├── routes.go           # Route definitions
│   └── health.go           # Health endpoints
├── middleware/
│   ├── chain.go            # Middleware chain builder
│   ├── recovery.go         # Panic recovery
│   ├── requestid.go        # Request ID injection
│   ├── logging.go          # Request/response logging
│   ├── cors.go             # CORS handling
│   ├── ratelimit.go        # Rate limiting
│   ├── auth.go             # Authentication
│   └── validation.go       # Request validation
├── proxy/
│   ├── proxy.go            # Reverse proxy logic
│   ├── circuit.go          # Circuit breaker
│   └── retry.go            # Retry logic
├── auth/
│   ├── jwt.go              # JWT validation
│   ├── apikey.go           # API key validation
│   └── session.go          # Session management
└── config/
    └── config.go           # Gateway-specific config
```

---

## main.go

```go
package main

import (
    "context"
    "os"
    "os/signal"
    "syscall"
    
    "github.com/specbuilder/pkg/config"
    "github.com/specbuilder/pkg/database"
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
    
    "github.com/specbuilder/internal/gateway/server"
)

func main() {
    // 1. Load configuration
    cfg := config.MustLoad(config.LoadOptions{
        ConfigName: "gateway",
        EnvPrefix:  "GATEWAY",
    })
    
    // 2. Initialize logger
    // CRITICAL: AddSource MUST be true for function name and line numbers
    logger := logging.New(
        logging.WithLevel(cfg.Logging.Level),
        logging.WithFormat(cfg.Logging.Format),
        logging.WithSource(true), // MANDATORY: Always include function name, file, line
        logging.WithService("gateway", "1.0.0"),
    )
    logging.SetDefault(logger)
    
    logger.Info("starting gateway service",
        "environment", cfg.Environment,
        "port", cfg.Server.Port,
    )
    
    // 3. Initialize settings database (for API keys, sessions)
    db, err := database.Open(cfg.Database.SettingsPath,
        database.WithLogger(logger.With("component", "database")),
        database.WithJournalMode(cfg.Database.JournalMode),
    )
    if err != nil {
        logger.Error("failed to open database", logging.Err(err))
        os.Exit(1)
    }
    defer db.Close()
    
    // 4. Create server
    srv, err := server.New(cfg, logger, db)
    if err != nil {
        logger.Error("failed to create server", logging.Err(err))
        os.Exit(1)
    }
    
    // 5. Start server
    go func() {
        if err := srv.Start(); err != nil {
            logger.Error("server error", logging.Err(err))
        }
    }()
    
    // 6. Graceful shutdown
    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
    <-quit
    
    logger.Info("shutting down gateway")
    
    ctx, cancel := context.WithTimeout(context.Background(), cfg.Server.ShutdownTimeout)
    defer cancel()
    
    if err := srv.Shutdown(ctx); err != nil {
        logger.Error("shutdown error", logging.Err(err))
    }
}
```

---

## Middleware Specifications

### 1. Recovery Middleware

```go
package middleware

import (
    "net/http"
    "runtime/debug"
    
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
)

// Recovery recovers from panics and logs the stack trace
func Recovery(logger logging.Logger) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            defer func() {
                if err := recover(); err != nil {
                    // Capture full stack trace
                    stack := debug.Stack()
                    
                    // Log with full context
                    logger.ErrorContext(r.Context(), "panic recovered",
                        "error", err,
                        "stack_trace", string(stack),
                        "method", r.Method,
                        "path", r.URL.Path,
                        "request_id", logging.GetRequestID(r.Context()),
                    )
                    
                    // Return structured error with stack trace
                    appErr := errors.NewSystem(
                        errors.ErrSystemPanic,
                        "internal server error",
                    )
                    
                    errors.WriteError(w, appErr)
                }
            }()
            
            next.ServeHTTP(w, r)
        })
    }
}
```

### 2. Request ID Middleware

```go
package middleware

import (
    "net/http"
    
    "github.com/google/uuid"
    "github.com/specbuilder/pkg/logging"
)

const (
    RequestIDHeader     = "X-Request-ID"
    CorrelationIDHeader = "X-Correlation-ID"
)

// RequestID injects or propagates request ID
func RequestID() func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            // Get or generate request ID
            requestID := r.Header.Get(RequestIDHeader)
            if requestID == "" {
                requestID = uuid.New().String()
            }
            
            // Get correlation ID (for distributed tracing)
            correlationID := r.Header.Get(CorrelationIDHeader)
            if correlationID == "" {
                correlationID = requestID
            }
            
            // Add to context
            ctx := logging.WithRequestID(r.Context(), requestID)
            ctx = logging.WithCorrelationID(ctx, correlationID)
            
            // Set response headers
            w.Header().Set(RequestIDHeader, requestID)
            w.Header().Set(CorrelationIDHeader, correlationID)
            
            next.ServeHTTP(w, r.WithContext(ctx))
        })
    }
}
```

### 3. Logging Middleware (with Source)

```go
package middleware

import (
    "net/http"
    "time"
    
    "github.com/specbuilder/pkg/logging"
)

// Logging logs all requests with full context
// CRITICAL: Logger must have AddSource=true for function name and line numbers
func Logging(logger logging.Logger) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            start := time.Now()
            
            // Wrap response writer to capture status
            wrapped := &responseWriter{ResponseWriter: w, status: 200}
            
            // Log request start with full source info
            logger.InfoContext(r.Context(), "request_started",
                "method", r.Method,
                "path", r.URL.Path,
                "query", r.URL.RawQuery,
                "remote_addr", r.RemoteAddr,
                "user_agent", r.UserAgent(),
                "content_length", r.ContentLength,
            )
            
            // Process request
            next.ServeHTTP(wrapped, r)
            
            // Log request completion
            duration := time.Since(start)
            
            logLevel := logging.LevelInfo
            if wrapped.status >= 500 {
                logLevel = logging.LevelError
            } else if wrapped.status >= 400 {
                logLevel = logging.LevelWarn
            }
            
            logger.Log(r.Context(), logLevel, "request_completed",
                "method", r.Method,
                "path", r.URL.Path,
                "status", wrapped.status,
                "bytes", wrapped.bytes,
                "duration_ms", duration.Milliseconds(),
                "duration_human", duration.String(),
            )
        })
    }
}
```

### 4. Rate Limiting Middleware

```go
package middleware

import (
    "net/http"
    "sync"
    "time"
    
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
)

// RateLimiter implements sliding window rate limiting
type RateLimiter struct {
    mu        sync.RWMutex
    windows   map[string]*window
    limit     int
    window    time.Duration
    logger    logging.Logger
}

type window struct {
    count    int
    start    time.Time
}

// NewRateLimiter creates a rate limiter
func NewRateLimiter(limit int, windowDuration time.Duration, logger logging.Logger) *RateLimiter {
    rl := &RateLimiter{
        windows: make(map[string]*window),
        limit:   limit,
        window:  windowDuration,
        logger:  logger,
    }
    
    // Cleanup goroutine
    go rl.cleanup()
    
    return rl
}

// RateLimit middleware
func (rl *RateLimiter) RateLimit() func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            // Get client identifier (IP or API key)
            clientID := rl.getClientID(r)
            
            if !rl.allow(clientID) {
                rl.logger.WarnContext(r.Context(), "rate limit exceeded",
                    "client_id", clientID,
                    "limit", rl.limit,
                    "window", rl.window,
                )
                
                appErr := errors.New(
                    errors.ErrSecurityRateLimit,
                    "rate limit exceeded, please slow down",
                )
                
                w.Header().Set("Retry-After", "60")
                errors.WriteErrorWithStatus(w, appErr, 429)
                return
            }
            
            next.ServeHTTP(w, r)
        })
    }
}

func (rl *RateLimiter) allow(clientID string) bool {
    rl.mu.Lock()
    defer rl.mu.Unlock()
    
    now := time.Now()
    w, exists := rl.windows[clientID]
    
    if !exists || now.Sub(w.start) > rl.window {
        rl.windows[clientID] = &window{count: 1, start: now}
        return true
    }
    
    if w.count >= rl.limit {
        return false
    }
    
    w.count++
    return true
}

func (rl *RateLimiter) getClientID(r *http.Request) string {
    // Prefer API key if present
    if apiKey := r.Header.Get("X-API-Key"); apiKey != "" {
        return "key:" + apiKey[:8] // Use prefix for logging
    }
    
    // Fall back to IP
    return "ip:" + r.RemoteAddr
}

func (rl *RateLimiter) cleanup() {
    ticker := time.NewTicker(rl.window)
    for range ticker.C {
        rl.mu.Lock()
        now := time.Now()
        for id, w := range rl.windows {
            if now.Sub(w.start) > rl.window*2 {
                delete(rl.windows, id)
            }
        }
        rl.mu.Unlock()
    }
}
```

### 5. Authentication Middleware

```go
package middleware

import (
    "context"
    "net/http"
    "strings"
    
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
    "github.com/specbuilder/pkg/types"
    
    "github.com/specbuilder/internal/gateway/auth"
)

type contextKey string

const (
    UserIDKey    contextKey = "user_id"
    SessionIDKey contextKey = "session_id"
    RolesKey     contextKey = "roles"
)

// Auth handles authentication
type Auth struct {
    jwtValidator    *auth.JWTValidator
    apiKeyValidator *auth.APIKeyValidator
    logger          logging.Logger
}

// NewAuth creates auth middleware
func NewAuth(jwtSecret string, db *database.DB, logger logging.Logger) *Auth {
    return &Auth{
        jwtValidator:    auth.NewJWTValidator(jwtSecret),
        apiKeyValidator: auth.NewAPIKeyValidator(db),
        logger:          logger,
    }
}

// Authenticate validates requests
func (a *Auth) Authenticate() func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            // Check Authorization header
            authHeader := r.Header.Get("Authorization")
            apiKey := r.Header.Get("X-API-Key")
            
            var userID types.UserID
            var roles []string
            var err error
            
            if strings.HasPrefix(authHeader, "Bearer ") {
                token := strings.TrimPrefix(authHeader, "Bearer ")
                userID, roles, err = a.jwtValidator.Validate(r.Context(), token)
            } else if apiKey != "" {
                userID, roles, err = a.apiKeyValidator.Validate(r.Context(), apiKey)
            } else {
                a.logger.WarnContext(r.Context(), "authentication required",
                    "path", r.URL.Path,
                    "remote_addr", r.RemoteAddr,
                )
                
                appErr := errors.New(
                    errors.ErrAuthRequired,
                    "authentication required",
                )
                errors.WriteError(w, appErr)
                return
            }
            
            if err != nil {
                a.logger.WarnContext(r.Context(), "authentication failed",
                    "error", err,
                    "path", r.URL.Path,
                )
                
                appErr := errors.New(
                    errors.ErrAuthInvalidToken,
                    "invalid or expired credentials",
                ).WithCause(err)
                errors.WriteError(w, appErr)
                return
            }
            
            // Add to context
            ctx := context.WithValue(r.Context(), UserIDKey, userID)
            ctx = context.WithValue(ctx, RolesKey, roles)
            ctx = logging.WithUserID(ctx, userID.String())
            
            a.logger.DebugContext(ctx, "authenticated",
                "user_id", userID,
                "roles", roles,
            )
            
            next.ServeHTTP(w, r.WithContext(ctx))
        })
    }
}

// RequireRole ensures user has required role
func (a *Auth) RequireRole(role string) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            roles, ok := r.Context().Value(RolesKey).([]string)
            if !ok {
                errors.WriteError(w, errors.New(
                    errors.ErrAuthRequired,
                    "authentication required",
                ))
                return
            }
            
            hasRole := false
            for _, r := range roles {
                if r == role || r == "admin" {
                    hasRole = true
                    break
                }
            }
            
            if !hasRole {
                a.logger.WarnContext(r.Context(), "insufficient permissions",
                    "required_role", role,
                    "user_roles", roles,
                )
                
                errors.WriteError(w, errors.New(
                    errors.ErrAuthInsufficientPerms,
                    "insufficient permissions for this operation",
                ))
                return
            }
            
            next.ServeHTTP(w, r)
        })
    }
}
```

---

## Proxy / Service Delegation

```go
package proxy

import (
    "context"
    "io"
    "net/http"
    "time"
    
    "github.com/specbuilder/pkg/config"
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
)

// ServiceProxy handles proxying to backend services
type ServiceProxy struct {
    services map[string]config.ServiceEndpoint
    client   *http.Client
    logger   logging.Logger
    circuit  *CircuitBreaker
}

// NewServiceProxy creates a service proxy
func NewServiceProxy(services config.ServicesConfig, logger logging.Logger) *ServiceProxy {
    return &ServiceProxy{
        services: map[string]config.ServiceEndpoint{
            "specmgr":   services.SpecMgr,
            "chronicle": services.Chronicle,
            "aibridge":  services.AIBridge,
            "scout":     services.Scout,
            "nexusflow": services.NexusFlow,
        },
        client: &http.Client{
            Timeout: 30 * time.Second,
            Transport: &http.Transport{
                MaxIdleConns:        100,
                MaxIdleConnsPerHost: 10,
                IdleConnTimeout:     90 * time.Second,
            },
        },
        logger:  logger,
        circuit: NewCircuitBreaker(5, 30*time.Second),
    }
}

// Proxy forwards a request to a backend service
func (p *ServiceProxy) Proxy(serviceName string) http.HandlerFunc {
    return func(w http.ResponseWriter, r *http.Request) {
        ctx := r.Context()
        
        endpoint, ok := p.services[serviceName]
        if !ok {
            p.logger.ErrorContext(ctx, "unknown service",
                "service", serviceName,
            )
            errors.WriteError(w, errors.New(
                errors.ErrConfigMissing,
                "service not configured",
            ))
            return
        }
        
        // Check circuit breaker
        if !p.circuit.Allow(serviceName) {
            p.logger.WarnContext(ctx, "circuit breaker open",
                "service", serviceName,
            )
            errors.WriteError(w, errors.New(
                errors.ErrExternalUnavailable,
                "service temporarily unavailable",
            ))
            return
        }
        
        // Build target URL
        targetURL := endpoint.URL() + r.URL.Path
        if r.URL.RawQuery != "" {
            targetURL += "?" + r.URL.RawQuery
        }
        
        // Create forwarded request
        proxyReq, err := http.NewRequestWithContext(ctx, r.Method, targetURL, r.Body)
        if err != nil {
            p.logger.ErrorContext(ctx, "failed to create proxy request",
                logging.Err(err),
                "service", serviceName,
            )
            errors.WriteError(w, errors.NewExternal(
                errors.ErrExternalConnection,
                serviceName,
                "failed to create request",
            ).WithCause(err))
            return
        }
        
        // Copy headers
        copyHeaders(r.Header, proxyReq.Header)
        
        // Add forwarding headers
        proxyReq.Header.Set("X-Forwarded-For", r.RemoteAddr)
        proxyReq.Header.Set("X-Forwarded-Host", r.Host)
        proxyReq.Header.Set("X-Request-ID", logging.GetRequestID(ctx))
        proxyReq.Header.Set("X-Correlation-ID", logging.GetCorrelationID(ctx))
        
        // Execute with timeout
        start := time.Now()
        
        resp, err := p.client.Do(proxyReq)
        if err != nil {
            p.circuit.RecordFailure(serviceName)
            
            p.logger.ErrorContext(ctx, "proxy request failed",
                logging.Err(err),
                "service", serviceName,
                "target", targetURL,
                "duration_ms", time.Since(start).Milliseconds(),
            )
            
            errors.WriteError(w, errors.NewExternal(
                errors.ErrExternalConnection,
                serviceName,
                "failed to connect to service",
            ).WithCause(err))
            return
        }
        defer resp.Body.Close()
        
        p.circuit.RecordSuccess(serviceName)
        
        p.logger.InfoContext(ctx, "proxy request completed",
            "service", serviceName,
            "target", targetURL,
            "status", resp.StatusCode,
            "duration_ms", time.Since(start).Milliseconds(),
        )
        
        // Copy response headers
        copyHeaders(resp.Header, w.Header())
        
        // Write response
        w.WriteHeader(resp.StatusCode)
        io.Copy(w, resp.Body)
    }
}

func copyHeaders(src, dst http.Header) {
    for key, values := range src {
        for _, value := range values {
            dst.Add(key, value)
        }
    }
}
```

---

## Circuit Breaker

```go
package proxy

import (
    "sync"
    "time"
)

// CircuitState represents circuit breaker state
type CircuitState int

const (
    CircuitClosed CircuitState = iota
    CircuitOpen
    CircuitHalfOpen
)

// CircuitBreaker implements the circuit breaker pattern
type CircuitBreaker struct {
    mu           sync.RWMutex
    states       map[string]*circuitState
    threshold    int
    resetTimeout time.Duration
}

type circuitState struct {
    state       CircuitState
    failures    int
    lastFailure time.Time
}

// NewCircuitBreaker creates a circuit breaker
func NewCircuitBreaker(threshold int, resetTimeout time.Duration) *CircuitBreaker {
    return &CircuitBreaker{
        states:       make(map[string]*circuitState),
        threshold:    threshold,
        resetTimeout: resetTimeout,
    }
}

// Allow checks if a request should be allowed
func (cb *CircuitBreaker) Allow(service string) bool {
    cb.mu.Lock()
    defer cb.mu.Unlock()
    
    s, exists := cb.states[service]
    if !exists {
        cb.states[service] = &circuitState{state: CircuitClosed}
        return true
    }
    
    switch s.state {
    case CircuitClosed:
        return true
        
    case CircuitOpen:
        // Check if reset timeout has passed
        if time.Since(s.lastFailure) > cb.resetTimeout {
            s.state = CircuitHalfOpen
            return true
        }
        return false
        
    case CircuitHalfOpen:
        return true
    }
    
    return true
}

// RecordSuccess records a successful request
func (cb *CircuitBreaker) RecordSuccess(service string) {
    cb.mu.Lock()
    defer cb.mu.Unlock()
    
    if s, exists := cb.states[service]; exists {
        s.failures = 0
        s.state = CircuitClosed
    }
}

// RecordFailure records a failed request
func (cb *CircuitBreaker) RecordFailure(service string) {
    cb.mu.Lock()
    defer cb.mu.Unlock()
    
    s, exists := cb.states[service]
    if !exists {
        s = &circuitState{}
        cb.states[service] = s
    }
    
    s.failures++
    s.lastFailure = time.Now()
    
    if s.failures >= cb.threshold {
        s.state = CircuitOpen
    }
}
```

---

## Route Definitions

```go
package server

import (
    "net/http"
    
    "github.com/go-chi/chi/v5"
    
    "github.com/specbuilder/internal/gateway/middleware"
    "github.com/specbuilder/internal/gateway/proxy"
)

// SetupRoutes configures all routes
func (s *Server) SetupRoutes() http.Handler {
    r := chi.NewRouter()
    
    // Global middleware (applied to all routes)
    r.Use(middleware.Recovery(s.logger))
    r.Use(middleware.RequestID())
    r.Use(middleware.Logging(s.logger))
    r.Use(middleware.CORS(s.cfg.Security))
    r.Use(s.rateLimiter.RateLimit())
    
    // Health endpoints (no auth)
    r.Get("/health", s.handleHealth)
    r.Get("/health/ready", s.handleReady)
    r.Get("/health/live", s.handleLive)
    
    // Metrics (no auth, but should be protected in production)
    r.Get("/metrics", s.handleMetrics)
    
    // API routes (with auth)
    r.Route("/api", func(r chi.Router) {
        r.Use(s.auth.Authenticate())
        
        // Spec Management
        r.Route("/specs", func(r chi.Router) {
            r.HandleFunc("/*", s.proxy.Proxy("specmgr"))
        })
        
        // Projects
        r.Route("/projects", func(r chi.Router) {
            r.HandleFunc("/*", s.proxy.Proxy("specmgr"))
        })
        
        // History / Version Control
        r.Route("/history", func(r chi.Router) {
            r.HandleFunc("/*", s.proxy.Proxy("chronicle"))
        })
        
        // Git operations
        r.Route("/git", func(r chi.Router) {
            r.HandleFunc("/*", s.proxy.Proxy("chronicle"))
        })
        
        // AI operations
        r.Route("/ai", func(r chi.Router) {
            r.HandleFunc("/*", s.proxy.Proxy("aibridge"))
        })
        
        // Search
        r.Route("/search", func(r chi.Router) {
            r.HandleFunc("/*", s.proxy.Proxy("scout"))
        })
        
        // Nexus-Flow (execution engine)
        r.Route("/flows", func(r chi.Router) {
            r.HandleFunc("/*", s.proxy.Proxy("nexusflow"))
        })
        
        // Executions
        r.Route("/executions", func(r chi.Router) {
            r.HandleFunc("/*", s.proxy.Proxy("nexusflow"))
        })
    })
    
    // Admin routes (require admin role)
    r.Route("/admin", func(r chi.Router) {
        r.Use(s.auth.Authenticate())
        r.Use(s.auth.RequireRole("admin"))
        
        r.Get("/services", s.handleServicesStatus)
        r.Post("/cache/clear", s.handleClearCache)
    })
    
    return r
}
```

---

## Health Endpoints

```go
package server

import (
    "encoding/json"
    "net/http"
    "time"
    
    "github.com/specbuilder/pkg/database"
    "github.com/specbuilder/pkg/types"
)

// HealthResponse represents health check response
type HealthResponse struct {
    Status    string                 `json:"status"`
    Timestamp time.Time              `json:"timestamp"`
    Version   string                 `json:"version"`
    Services  map[string]ServiceHealth `json:"services,omitempty"`
    Database  *database.Health       `json:"database,omitempty"`
}

// ServiceHealth represents a downstream service health
type ServiceHealth struct {
    Status  string        `json:"status"`
    Latency time.Duration `json:"latency"`
    Error   string        `json:"error,omitempty"`
}

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
    ctx := r.Context()
    
    // Check database
    dbHealth := s.db.HealthCheck(ctx)
    
    // Check downstream services
    services := make(map[string]ServiceHealth)
    for name, endpoint := range s.proxy.Services() {
        health := s.checkService(ctx, name, endpoint)
        services[name] = health
    }
    
    // Determine overall status
    status := "healthy"
    httpStatus := http.StatusOK
    
    if dbHealth.Status != database.HealthStatusUp {
        status = "degraded"
        httpStatus = http.StatusServiceUnavailable
    }
    
    for _, svc := range services {
        if svc.Status == "down" {
            status = "degraded"
        }
    }
    
    response := HealthResponse{
        Status:    status,
        Timestamp: time.Now().UTC(),
        Version:   s.cfg.Version,
        Services:  services,
        Database:  &dbHealth,
    }
    
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(httpStatus)
    json.NewEncoder(w).Encode(response)
}

func (s *Server) handleReady(w http.ResponseWriter, r *http.Request) {
    // Check if service can accept traffic
    if err := s.db.PingContext(r.Context()); err != nil {
        http.Error(w, "not ready", http.StatusServiceUnavailable)
        return
    }
    
    w.WriteHeader(http.StatusOK)
    w.Write([]byte("ready"))
}

func (s *Server) handleLive(w http.ResponseWriter, r *http.Request) {
    // Simple liveness - just respond
    w.WriteHeader(http.StatusOK)
    w.Write([]byte("alive"))
}
```

---

## Configuration

```yaml
# gateway/config.yaml
environment: development

server:
  host: "0.0.0.0"
  port: 8080
  read_timeout: 30s
  write_timeout: 30s
  shutdown_timeout: 10s

database:
  settings_path: "./data/settings.db"

logging:
  level: debug
  format: json
  add_source: true  # MANDATORY: Always include function name, file, line

security:
  allowed_origins:
    - "http://localhost:3000"
    - "http://localhost:5173"
  rate_limit_enabled: true
  rate_limit_window: 1m
  rate_limit_max: 100
  jwt_secret: "${GATEWAY_JWT_SECRET}"

services:
  specmgr:
    host: localhost
    port: 8081
    timeout: 30s
    retries: 3
  chronicle:
    host: localhost
    port: 8083
    timeout: 30s
    retries: 3
  aibridge:
    host: localhost
    port: 8082
    timeout: 120s
    retries: 2
  scout:
    host: localhost
    port: 8084
    timeout: 30s
    retries: 3
  nexusflow:
    host: localhost
    port: 9000
    timeout: 300s
    retries: 1
```

---

## Testing

```go
func TestRecoveryMiddleware_PanicRecovery(t *testing.T) {
    logger := logging.NewNoop()
    
    handler := middleware.Recovery(logger)(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        panic("test panic")
    }))
    
    req := httptest.NewRequest("GET", "/test", nil)
    rec := httptest.NewRecorder()
    
    handler.ServeHTTP(rec, req)
    
    assert.Equal(t, 503, rec.Code)
    
    var errResp errors.ErrorResponse
    json.Unmarshal(rec.Body.Bytes(), &errResp)
    
    assert.Equal(t, errors.ErrSystemPanic, errResp.Error.Code)
    assert.NotEmpty(t, errResp.Error.StackTrace)
}

func TestRateLimiter_ExceedsLimit(t *testing.T) {
    logger := logging.NewNoop()
    rl := middleware.NewRateLimiter(2, time.Minute, logger)
    
    handler := rl.RateLimit()(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.WriteHeader(http.StatusOK)
    }))
    
    for i := 0; i < 3; i++ {
        req := httptest.NewRequest("GET", "/test", nil)
        req.RemoteAddr = "127.0.0.1:1234"
        rec := httptest.NewRecorder()
        
        handler.ServeHTTP(rec, req)
        
        if i < 2 {
            assert.Equal(t, 200, rec.Code)
        } else {
            assert.Equal(t, 429, rec.Code)
        }
    }
}
```

---

## Related Specifications

- [Phase 1: Shared Packages](../13-shared-packages/00-overview.md)
- [Phase 3: SpecManager](./02-specmanager.md)
- [Phase 4: Chronicle](./03-chronicle.md)
