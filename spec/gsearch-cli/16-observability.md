# Golang Search CLI - Observability Specification

> Version: 1.2.0  
> Status: Active  
> Updated: 2026-01-28

## 1. Overview

This specification defines the observability infrastructure for the Golang Search CLI, including Prometheus metrics, health check endpoints, structured logging, and OpenTelemetry distributed tracing.

## 2. Prometheus Metrics

### 2.1 Metrics Registry

```go
package metrics

import (
    "github.com/prometheus/client_golang/prometheus"
    "github.com/prometheus/client_golang/prometheus/promauto"
)

var (
    // Search Operation Metrics
    SearchRequestsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Namespace: "gosearch",
            Subsystem: "search",
            Name:      "requests_total",
            Help:      "Total number of search requests by engine and method",
        },
        []string{"engine", "method", "status"},
    )

    SearchLatencySeconds = promauto.NewHistogramVec(
        prometheus.HistogramOpts{
            Namespace: "gosearch",
            Subsystem: "search",
            Name:      "latency_seconds",
            Help:      "Search request latency in seconds",
            Buckets:   []float64{0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30},
        },
        []string{"engine", "method"},
    )

    SearchResultsCount = promauto.NewHistogramVec(
        prometheus.HistogramOpts{
            Namespace: "gosearch",
            Subsystem: "search",
            Name:      "results_count",
            Help:      "Number of results returned per search",
            Buckets:   []float64{0, 1, 5, 10, 20, 50, 100},
        },
        []string{"engine"},
    )

    // Cache Metrics
    CacheHitsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Namespace: "gosearch",
            Subsystem: "cache",
            Name:      "hits_total",
            Help:      "Total cache hits by engine",
        },
        []string{"engine"},
    )

    CacheMissesTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Namespace: "gosearch",
            Subsystem: "cache",
            Name:      "misses_total",
            Help:      "Total cache misses by engine",
        },
        []string{"engine"},
    )

    CacheSize = promauto.NewGauge(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "cache",
            Name:      "size_bytes",
            Help:      "Current cache size in bytes",
        },
    )

    CacheEntries = promauto.NewGauge(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "cache",
            Name:      "entries_count",
            Help:      "Current number of cache entries",
        },
    )

    // Engine Health Metrics
    EngineBlockedTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Namespace: "gosearch",
            Subsystem: "engine",
            Name:      "blocked_total",
            Help:      "Total times engine was blocked/rate-limited",
        },
        []string{"engine", "reason"},
    )

    EngineHealthStatus = promauto.NewGaugeVec(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "engine",
            Name:      "health_status",
            Help:      "Engine health status (1=healthy, 0=unhealthy, -1=blocked)",
        },
        []string{"engine"},
    )

    EngineCooldownRemaining = promauto.NewGaugeVec(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "engine",
            Name:      "cooldown_remaining_seconds",
            Help:      "Seconds remaining in cooldown period",
        },
        []string{"engine"},
    )

    // API Quota Metrics
    ApiQuotaUsed = promauto.NewGaugeVec(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "api",
            Name:      "quota_used",
            Help:      "API quota used for current period",
        },
        []string{"engine", "period"},
    )

    ApiQuotaLimit = promauto.NewGaugeVec(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "api",
            Name:      "quota_limit",
            Help:      "API quota limit for period",
        },
        []string{"engine", "period"},
    )

    // Database Metrics
    DbQueryDurationSeconds = promauto.NewHistogramVec(
        prometheus.HistogramOpts{
            Namespace: "gosearch",
            Subsystem: "database",
            Name:      "query_duration_seconds",
            Help:      "Database query duration in seconds",
            Buckets:   []float64{0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5},
        },
        []string{"operation", "table"},
    )

    DbConnectionsActive = promauto.NewGauge(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "database",
            Name:      "connections_active",
            Help:      "Number of active database connections",
        },
    )

    DbSizeBytes = promauto.NewGauge(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "database",
            Name:      "size_bytes",
            Help:      "Database file size in bytes",
        },
    )

    // RAG Export Metrics
    RagExportsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Namespace: "gosearch",
            Subsystem: "rag",
            Name:      "exports_total",
            Help:      "Total RAG exports by format",
        },
        []string{"format"},
    )

    RagChunksGenerated = promauto.NewHistogram(
        prometheus.HistogramOpts{
            Namespace: "gosearch",
            Subsystem: "rag",
            Name:      "chunks_generated",
            Help:      "Number of chunks generated per export",
            Buckets:   []float64{10, 50, 100, 250, 500, 1000},
        },
    )

    // Nested Search Metrics
    NestedSearchDepth = promauto.NewHistogram(
        prometheus.HistogramOpts{
            Namespace: "gosearch",
            Subsystem: "nested",
            Name:      "depth_reached",
            Help:      "Maximum depth reached in nested searches",
            Buckets:   []float64{1, 2, 3, 4, 5},
        },
    )

    NestedSearchCyclesDetected = promauto.NewCounter(
        prometheus.CounterOpts{
            Namespace: "gosearch",
            Subsystem: "nested",
            Name:      "cycles_detected_total",
            Help:      "Total cycles detected in nested searches",
        },
    )

    // Process Metrics
    ProcessStartTime = promauto.NewGauge(
        prometheus.GaugeOpts{
            Namespace: "gosearch",
            Subsystem: "process",
            Name:      "start_time_seconds",
            Help:      "Process start time in Unix seconds",
        },
    )
)
```

### 2.2 Metrics Endpoint

| Configuration Key | Type | Default | Description |
|-------------------|------|---------|-------------|
| `metrics.enabled` | bool | `true` | Enable Prometheus metrics |
| `metrics.endpoint` | string | `/metrics` | Metrics HTTP endpoint |
| `metrics.port` | int | `9090` | Metrics server port |
| `metrics.basicAuth.enabled` | bool | `false` | Enable basic auth for metrics |
| `metrics.basicAuth.username` | string | `""` | Basic auth username |
| `metrics.basicAuth.passwordHash` | string | `""` | Bcrypt hash of password |

### 2.3 Metrics Collection Points

| Metric | Collection Point | Labels |
|--------|------------------|--------|
| `search_requests_total` | SearchEngine.Execute() | engine, method, status |
| `search_latency_seconds` | SearchEngine.Execute() | engine, method |
| `cache_hits_total` | CacheManager.Get() | engine |
| `cache_misses_total` | CacheManager.Get() | engine |
| `engine_blocked_total` | BlockDetector.Check() | engine, reason |
| `db_query_duration_seconds` | GORM callbacks | operation, table |

## 3. Health Check Endpoints

### 3.1 Health Check Interface

```go
package health

import (
    "context"
    "time"
)

// ComponentStatus represents health of a system component
type ComponentStatus struct {
    Name      string                 `json:"name"`
    Status    HealthStatus           `json:"status"`
    Message   string                 `json:"message,omitempty"`
    Latency   time.Duration          `json:"latency_ms"`
    Metadata  map[string]interface{} `json:"metadata,omitempty"`
    CheckedAt time.Time              `json:"checked_at"`
}

// HealthStatus enum
type HealthStatus string

const (
    StatusHealthy   HealthStatus = "healthy"
    StatusDegraded  HealthStatus = "degraded"
    StatusUnhealthy HealthStatus = "unhealthy"
)

// HealthResponse is the full health check response
type HealthResponse struct {
    Status     HealthStatus       `json:"status"`
    Version    string             `json:"version"`
    Uptime     time.Duration      `json:"uptime_seconds"`
    Components []ComponentStatus  `json:"components"`
    CheckedAt  time.Time          `json:"checked_at"`
}

// HealthChecker interface for component health checks
type HealthChecker interface {
    Name() string
    Check(ctx context.Context) ComponentStatus
}
```

### 3.2 Component Health Checkers

```go
// DatabaseHealthChecker validates SQLite connectivity
type DatabaseHealthChecker struct {
    db *gorm.DB
}

func (d *DatabaseHealthChecker) Name() string { return "database" }

func (d *DatabaseHealthChecker) Check(ctx context.Context) ComponentStatus {
    start := time.Now()
    
    sqlDB, err := d.db.DB()
    if err != nil {
        return ComponentStatus{
            Name:      d.Name(),
            Status:    StatusUnhealthy,
            Message:   fmt.Sprintf("failed to get db: %v", err),
            Latency:   time.Since(start),
            CheckedAt: time.Now(),
        }
    }
    
    if err := sqlDB.PingContext(ctx); err != nil {
        return ComponentStatus{
            Name:      d.Name(),
            Status:    StatusUnhealthy,
            Message:   fmt.Sprintf("ping failed: %v", err),
            Latency:   time.Since(start),
            CheckedAt: time.Now(),
        }
    }
    
    stats := sqlDB.Stats()
    return ComponentStatus{
        Name:    d.Name(),
        Status:  StatusHealthy,
        Latency: time.Since(start),
        Metadata: map[string]interface{}{
            "open_connections": stats.OpenConnections,
            "in_use":           stats.InUse,
            "idle":             stats.Idle,
        },
        CheckedAt: time.Now(),
    }
}

// EngineHealthChecker validates search engine availability
type EngineHealthChecker struct {
    engine     string
    manager    *EngineManager
    blockState *BlockStateManager
}

func (e *EngineHealthChecker) Check(ctx context.Context) ComponentStatus {
    start := time.Now()
    
    blocked, reason := e.blockState.IsBlocked(e.engine)
    if blocked {
        return ComponentStatus{
            Name:    fmt.Sprintf("engine_%s", e.engine),
            Status:  StatusDegraded,
            Message: fmt.Sprintf("blocked: %s", reason),
            Latency: time.Since(start),
            Metadata: map[string]interface{}{
                "cooldown_remaining": e.blockState.CooldownRemaining(e.engine).Seconds(),
            },
            CheckedAt: time.Now(),
        }
    }
    
    return ComponentStatus{
        Name:      fmt.Sprintf("engine_%s", e.engine),
        Status:    StatusHealthy,
        Latency:   time.Since(start),
        CheckedAt: time.Now(),
    }
}

// CacheHealthChecker validates cache subsystem
type CacheHealthChecker struct {
    cache *CacheManager
}

func (c *CacheHealthChecker) Check(ctx context.Context) ComponentStatus {
    start := time.Now()
    stats := c.cache.Stats()
    
    status := StatusHealthy
    if stats.HitRate < 0.1 && stats.TotalRequests > 100 {
        status = StatusDegraded
    }
    
    return ComponentStatus{
        Name:    "cache",
        Status:  status,
        Latency: time.Since(start),
        Metadata: map[string]interface{}{
            "entries":   stats.Entries,
            "size_mb":   float64(stats.SizeBytes) / 1024 / 1024,
            "hit_rate":  stats.HitRate,
        },
        CheckedAt: time.Now(),
    }
}

// DiskHealthChecker validates disk space availability
type DiskHealthChecker struct {
    paths []string
    minFreeMB int64
}

func (d *DiskHealthChecker) Check(ctx context.Context) ComponentStatus {
    start := time.Now()
    
    for _, path := range d.paths {
        var stat syscall.Statfs_t
        if err := syscall.Statfs(path, &stat); err != nil {
            return ComponentStatus{
                Name:      "disk",
                Status:    StatusUnhealthy,
                Message:   fmt.Sprintf("statfs failed for %s: %v", path, err),
                Latency:   time.Since(start),
                CheckedAt: time.Now(),
            }
        }
        
        freeMB := int64(stat.Bavail * uint64(stat.Bsize)) / 1024 / 1024
        if freeMB < d.minFreeMB {
            return ComponentStatus{
                Name:    "disk",
                Status:  StatusDegraded,
                Message: fmt.Sprintf("low disk space: %dMB free", freeMB),
                Latency: time.Since(start),
                Metadata: map[string]interface{}{
                    "path":       path,
                    "free_mb":    freeMB,
                    "min_req_mb": d.minFreeMB,
                },
                CheckedAt: time.Now(),
            }
        }
    }
    
    return ComponentStatus{
        Name:      "disk",
        Status:    StatusHealthy,
        Latency:   time.Since(start),
        CheckedAt: time.Now(),
    }
}
```

### 3.3 Health Endpoints

| Endpoint | Method | Description | Response |
|----------|--------|-------------|----------|
| `/health` | GET | Basic liveness check | `200 OK` or `503 Service Unavailable` |
| `/health/ready` | GET | Readiness with components | Full `HealthResponse` JSON |
| `/health/live` | GET | Kubernetes liveness probe | `200 OK` if process running |

### 3.4 Health Configuration

| Configuration Key | Type | Default | Description |
|-------------------|------|---------|-------------|
| `health.enabled` | bool | `true` | Enable health endpoints |
| `health.port` | int | `8080` | Health server port |
| `health.timeout` | duration | `5s` | Health check timeout |
| `health.disk.minFreeMB` | int64 | `100` | Minimum free disk space |
| `health.disk.paths` | []string | `["./data"]` | Paths to check for disk space |

## 4. OpenTelemetry Tracing

### 4.1 Tracer Configuration

```go
package tracing

import (
    "context"
    
    "go.opentelemetry.io/otel"
    "go.opentelemetry.io/otel/attribute"
    "go.opentelemetry.io/otel/exporters/otlp/otlptrace"
    "go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc"
    "go.opentelemetry.io/otel/sdk/resource"
    sdktrace "go.opentelemetry.io/otel/sdk/trace"
    semconv "go.opentelemetry.io/otel/semconv/v1.21.0"
    "go.opentelemetry.io/otel/trace"
)

// TracerConfig holds tracing configuration
type TracerConfig struct {
    Enabled       bool    `json:"enabled"`
    ServiceName   string  `json:"serviceName"`
    Environment   string  `json:"environment"`
    OTLPEndpoint  string  `json:"otlpEndpoint"`
    SamplingRate  float64 `json:"samplingRate"`
    BatchTimeout  string  `json:"batchTimeout"`
    ExportTimeout string  `json:"exportTimeout"`
}

// InitTracer initializes OpenTelemetry tracing
func InitTracer(cfg TracerConfig) (func(context.Context) error, error) {
    if !cfg.Enabled {
        return func(ctx context.Context) error { return nil }, nil
    }
    
    ctx := context.Background()
    
    exporter, err := otlptrace.New(
        ctx,
        otlptracegrpc.NewClient(
            otlptracegrpc.WithEndpoint(cfg.OTLPEndpoint),
            otlptracegrpc.WithInsecure(),
        ),
    )
    if err != nil {
        return nil, fmt.Errorf("failed to create OTLP exporter: %w", err)
    }
    
    res, err := resource.New(ctx,
        resource.WithAttributes(
            semconv.ServiceName(cfg.ServiceName),
            semconv.DeploymentEnvironment(cfg.Environment),
            attribute.String("service.version", Version),
        ),
    )
    if err != nil {
        return nil, fmt.Errorf("failed to create resource: %w", err)
    }
    
    sampler := sdktrace.ParentBased(
        sdktrace.TraceIDRatioBased(cfg.SamplingRate),
    )
    
    tp := sdktrace.NewTracerProvider(
        sdktrace.WithBatcher(exporter),
        sdktrace.WithResource(res),
        sdktrace.WithSampler(sampler),
    )
    
    otel.SetTracerProvider(tp)
    
    return tp.Shutdown, nil
}
```

### 4.2 Span Conventions

| Span Name | Attributes | Description |
|-----------|------------|-------------|
| `search.execute` | `search.query`, `search.engine`, `search.method` | Root search operation |
| `search.engine.request` | `http.url`, `http.method`, `http.status_code` | HTTP request to engine |
| `search.parse.results` | `search.results_count` | Result parsing |
| `cache.get` | `cache.key`, `cache.hit` | Cache lookup |
| `cache.set` | `cache.key`, `cache.ttl_seconds` | Cache write |
| `db.query` | `db.operation`, `db.table`, `db.rows_affected` | Database operation |
| `nested.search` | `nested.depth`, `nested.keywords` | Nested search execution |
| `rag.export` | `rag.format`, `rag.chunks_count` | RAG export operation |
| `page.fetch` | `http.url`, `page.content_size` | Page content fetch |

### 4.3 Instrumentation Examples

```go
package search

import (
    "context"
    
    "go.opentelemetry.io/otel"
    "go.opentelemetry.io/otel/attribute"
    "go.opentelemetry.io/otel/codes"
)

var tracer = otel.Tracer("gosearch/search")

// Execute performs a search with tracing
func (e *SearchEngine) Execute(ctx context.Context, query string) ([]Result, error) {
    ctx, span := tracer.Start(ctx, "search.execute",
        trace.WithAttributes(
            attribute.String("search.query", query),
            attribute.String("search.engine", e.Name()),
        ),
    )
    defer span.End()
    
    // Check cache first
    cacheCtx, cacheSpan := tracer.Start(ctx, "cache.get")
    cached, hit := e.cache.Get(cacheCtx, query)
    cacheSpan.SetAttributes(attribute.Bool("cache.hit", hit))
    cacheSpan.End()
    
    if hit {
        span.SetAttributes(attribute.Bool("search.cache_hit", true))
        return cached, nil
    }
    
    // Execute search
    results, err := e.executeSearch(ctx, query)
    if err != nil {
        span.RecordError(err)
        span.SetStatus(codes.Error, err.Error())
        return nil, err
    }
    
    span.SetAttributes(
        attribute.Int("search.results_count", len(results)),
        attribute.Bool("search.cache_hit", false),
    )
    
    return results, nil
}

// executeSearch performs the actual search request
func (e *SearchEngine) executeSearch(ctx context.Context, query string) ([]Result, error) {
    ctx, span := tracer.Start(ctx, "search.engine.request",
        trace.WithAttributes(
            attribute.String("search.engine", e.Name()),
            attribute.String("search.method", e.method),
        ),
    )
    defer span.End()
    
    // Build request
    req, err := e.buildRequest(ctx, query)
    if err != nil {
        span.RecordError(err)
        return nil, err
    }
    
    span.SetAttributes(
        attribute.String("http.url", req.URL.String()),
        attribute.String("http.method", req.Method),
    )
    
    // Execute
    resp, err := e.client.Do(req)
    if err != nil {
        span.RecordError(err)
        span.SetStatus(codes.Error, "request failed")
        return nil, err
    }
    defer resp.Body.Close()
    
    span.SetAttributes(attribute.Int("http.status_code", resp.StatusCode))
    
    // Parse results
    return e.parseResults(ctx, resp.Body)
}
```

### 4.4 Tracing Configuration

| Configuration Key | Type | Default | Description |
|-------------------|------|---------|-------------|
| `tracing.enabled` | bool | `false` | Enable OpenTelemetry tracing |
| `tracing.serviceName` | string | `"gosearch"` | Service name for traces |
| `tracing.environment` | string | `"development"` | Deployment environment |
| `tracing.otlpEndpoint` | string | `"localhost:4317"` | OTLP gRPC endpoint |
| `tracing.samplingRate` | float64 | `1.0` | Trace sampling rate (0.0-1.0) |
| `tracing.batchTimeout` | duration | `5s` | Batch export timeout |
| `tracing.exportTimeout` | duration | `30s` | Export timeout |

## 5. Structured Logging

### 5.1 Log Format Specification

```go
package logging

import (
    "go.uber.org/zap"
    "go.uber.org/zap/zapcore"
)

// LogConfig holds logging configuration
type LogConfig struct {
    Level       string `json:"level"`
    Format      string `json:"format"`      // "json" or "console"
    OutputPath  string `json:"outputPath"`
    MaxSizeMB   int    `json:"maxSizeMb"`
    MaxBackups  int    `json:"maxBackups"`
    MaxAgeDays  int    `json:"maxAgeDays"`
    Compress    bool   `json:"compress"`
    AddCaller   bool   `json:"addCaller"`
    Development bool   `json:"development"`
}

// StandardFields defines required log fields
type StandardFields struct {
    Timestamp   string `json:"timestamp"`
    Level       string `json:"level"`
    Logger      string `json:"logger"`
    Message     string `json:"message"`
    TraceID     string `json:"trace_id,omitempty"`
    SpanID      string `json:"span_id,omitempty"`
    Caller      string `json:"caller,omitempty"`
    Error       string `json:"error,omitempty"`
}

// NewLogger creates a configured zap logger
func NewLogger(cfg LogConfig) (*zap.Logger, error) {
    var zapCfg zap.Config
    
    if cfg.Development {
        zapCfg = zap.NewDevelopmentConfig()
    } else {
        zapCfg = zap.NewProductionConfig()
    }
    
    // Set level
    level, err := zapcore.ParseLevel(cfg.Level)
    if err != nil {
        return nil, fmt.Errorf("invalid log level: %w", err)
    }
    zapCfg.Level = zap.NewAtomicLevelAt(level)
    
    // Set format
    if cfg.Format == "console" {
        zapCfg.Encoding = "console"
    } else {
        zapCfg.Encoding = "json"
    }
    
    // Configure output
    zapCfg.OutputPaths = []string{cfg.OutputPath}
    
    // Add caller info
    zapCfg.DisableCaller = !cfg.AddCaller
    
    return zapCfg.Build()
}
```

### 5.2 Log Levels and Usage

| Level | Usage | Example |
|-------|-------|---------|
| `debug` | Detailed debugging info | Request/response bodies, cache keys |
| `info` | Normal operations | Search completed, cache hit/miss |
| `warn` | Recoverable issues | Rate limit approaching, retry attempt |
| `error` | Failures requiring attention | Engine blocked, database error |
| `fatal` | Unrecoverable errors | Config invalid, database unreachable |

### 5.3 Contextual Logging

```go
// LogContext adds trace context to logger
func LogContext(ctx context.Context, logger *zap.Logger) *zap.Logger {
    span := trace.SpanFromContext(ctx)
    if !span.SpanContext().IsValid() {
        return logger
    }
    
    return logger.With(
        zap.String("trace_id", span.SpanContext().TraceID().String()),
        zap.String("span_id", span.SpanContext().SpanID().String()),
    )
}

// Usage example
func (e *SearchEngine) Execute(ctx context.Context, query string) {
    log := LogContext(ctx, e.logger)
    log.Info("executing search",
        zap.String("engine", e.Name()),
        zap.String("query", query),
    )
}
```

## 6. Alert Rules (Prometheus)

### 6.1 Alert Definitions

```yaml
groups:
  - name: gosearch-alerts
    interval: 30s
    rules:
      # High error rate
      - alert: HighSearchErrorRate
        expr: |
          sum(rate(gosearch_search_requests_total{status="error"}[5m])) /
          sum(rate(gosearch_search_requests_total[5m])) > 0.1
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High search error rate"
          description: "Error rate is {{ $value | humanizePercentage }} over last 5 minutes"

      # All engines blocked
      - alert: AllEnginesBlocked
        expr: |
          sum(gosearch_engine_health_status) == 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "All search engines blocked"
          description: "No healthy search engines available"

      # Low cache hit rate
      - alert: LowCacheHitRate
        expr: |
          sum(rate(gosearch_cache_hits_total[1h])) /
          (sum(rate(gosearch_cache_hits_total[1h])) + sum(rate(gosearch_cache_misses_total[1h]))) < 0.3
        for: 30m
        labels:
          severity: warning
        annotations:
          summary: "Low cache hit rate"
          description: "Cache hit rate is {{ $value | humanizePercentage }}"

      # API quota exhausted
      - alert: ApiQuotaExhausted
        expr: |
          gosearch_api_quota_used / gosearch_api_quota_limit > 0.9
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "API quota nearly exhausted"
          description: "{{ $labels.engine }} quota at {{ $value | humanizePercentage }}"

      # High search latency
      - alert: HighSearchLatency
        expr: |
          histogram_quantile(0.95, rate(gosearch_search_latency_seconds_bucket[5m])) > 10
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High search latency"
          description: "P95 latency is {{ $value | humanizeDuration }}"

      # Database connection issues
      - alert: DatabaseConnectionIssues
        expr: |
          gosearch_database_connections_active > 
          (gosearch_database_connections_max * 0.9)
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Database connection pool near exhaustion"
          description: "{{ $value }} active connections"
```

## 7. Grafana Dashboard

### 7.1 Dashboard Panels

| Panel | Type | Metrics | Description |
|-------|------|---------|-------------|
| Search Rate | Graph | `rate(search_requests_total[1m])` | Searches per second by engine |
| Error Rate | Graph | `rate(search_requests_total{status="error"}[1m])` | Errors per second |
| P95 Latency | Graph | `histogram_quantile(0.95, ...)` | 95th percentile latency |
| Cache Hit Rate | Gauge | `hits / (hits + misses)` | Current cache efficiency |
| Engine Status | Status | `engine_health_status` | Per-engine health indicators |
| API Quota | Bar | `quota_used / quota_limit` | Quota utilization per engine |
| Database Connections | Graph | `connections_active` | Active DB connections over time |
| Results Distribution | Histogram | `search_results_count` | Results count distribution |

### 7.2 Dashboard JSON Template

```json
{
  "dashboard": {
    "title": "Golang Search CLI",
    "uid": "gosearch-main",
    "tags": ["gosearch", "search", "cli"],
    "timezone": "browser",
    "refresh": "30s",
    "panels": [
      {
        "title": "Search Rate by Engine",
        "type": "timeseries",
        "gridPos": { "x": 0, "y": 0, "w": 12, "h": 8 },
        "targets": [{
          "expr": "sum(rate(gosearch_search_requests_total[1m])) by (engine)",
          "legendFormat": "{{ engine }}"
        }]
      },
      {
        "title": "Engine Health",
        "type": "stat",
        "gridPos": { "x": 12, "y": 0, "w": 12, "h": 8 },
        "targets": [{
          "expr": "gosearch_engine_health_status",
          "legendFormat": "{{ engine }}"
        }],
        "fieldConfig": {
          "defaults": {
            "mappings": [
              { "type": "value", "options": { "1": { "text": "Healthy", "color": "green" }}},
              { "type": "value", "options": { "0": { "text": "Unhealthy", "color": "red" }}},
              { "type": "value", "options": { "-1": { "text": "Blocked", "color": "orange" }}}
            ]
          }
        }
      }
    ]
  }
}
```

## 8. Acceptance Criteria

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| OBS-01 | Prometheus metrics exposed on configurable port | High | Curl `/metrics` endpoint |
| OBS-02 | All 20+ metric types registered and collecting | High | Metrics registry inspection |
| OBS-03 | Health endpoints return correct status codes | High | HTTP response validation |
| OBS-04 | Component health checks complete within timeout | Medium | Timing verification |
| OBS-05 | OpenTelemetry traces exported to OTLP endpoint | Medium | Trace collector verification |
| OBS-06 | Trace context propagated across all operations | Medium | Span parent-child validation |
| OBS-07 | Structured JSON logs include trace correlation | Medium | Log parsing verification |
| OBS-08 | Alert rules fire correctly on threshold breach | Medium | Alert testing |
| OBS-09 | Grafana dashboard displays all key metrics | Low | Visual inspection |
| OBS-10 | Metrics do not significantly impact performance | High | Benchmark comparison |

## 9. Configuration Summary

### 9.1 Environment Configuration

```json
{
  "observability": {
    "metrics": {
      "enabled": true,
      "port": 9090,
      "endpoint": "/metrics"
    },
    "health": {
      "enabled": true,
      "port": 8080,
      "timeout": "5s"
    },
    "tracing": {
      "enabled": false,
      "serviceName": "gosearch",
      "otlpEndpoint": "localhost:4317",
      "samplingRate": 1.0
    },
    "logging": {
      "level": "info",
      "format": "json",
      "outputPath": "./logs/gosearch.log"
    }
  }
}
```

---

*Document Version: 1.2.0 | Last Updated: 2025-01-28*
