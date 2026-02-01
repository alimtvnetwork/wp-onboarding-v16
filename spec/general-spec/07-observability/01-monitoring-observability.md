# 18. Monitoring & Observability

> **Version**: 1.0.0  
> **Last Updated**: 2026-01-26  
> **Applies To**: PHP, TypeScript, Python

## Overview

Standards for application monitoring, metrics collection, distributed tracing, and alerting to ensure system health visibility and rapid issue detection.

---

## 18.1 Observability Pillars

### Three Pillars Framework

```
┌─────────────────────────────────────────────────────────────┐
│                    OBSERVABILITY                            │
├───────────────────┬───────────────────┬───────────────────┤
│      METRICS      │      LOGS         │      TRACES       │
│   (What happened) │  (Why it happened)│ (How it happened) │
├───────────────────┼───────────────────┼───────────────────┤
│ • Counters        │ • Structured JSON │ • Span context    │
│ • Gauges          │ • Correlation IDs │ • Parent/child    │
│ • Histograms      │ • Log levels      │ • Timing data     │
│ • Summaries       │ • Context fields  │ • Service mesh    │
└───────────────────┴───────────────────┴───────────────────┘
```

### Integration Requirements

| Pillar  | Tool Examples              | Mandatory |
|---------|---------------------------|-----------|
| Metrics | Prometheus, CloudWatch    | ✓         |
| Logs    | ELK Stack, Loki           | ✓         |
| Traces  | Jaeger, Zipkin, X-Ray     | Production only |

---

## 18.2 Metrics Standards

### Naming Conventions

```
# Format: <namespace>_<subsystem>_<name>_<unit>

# Good Examples
app_http_requests_total
app_http_request_duration_seconds
app_database_connections_active
app_queue_messages_pending

# Bad Examples
requests              # Missing namespace
httpRequestCount      # Wrong format (camelCase)
response_time         # Missing unit
```

### Metric Types

```typescript
// TypeScript - Prometheus Client Example
import { Counter, Gauge, Histogram, Summary } from 'prom-client';

// Counter - monotonically increasing
const httpRequestsTotal = new Counter({
  name: 'app_http_requests_total',
  help: 'Total HTTP requests',
  labelNames: ['method', 'path', 'status'],
});

// Gauge - can go up or down
const activeConnections = new Gauge({
  name: 'app_database_connections_active',
  help: 'Active database connections',
});

// Histogram - distribution with buckets
const requestDuration = new Histogram({
  name: 'app_http_request_duration_seconds',
  help: 'HTTP request duration in seconds',
  labelNames: ['method', 'path'],
  buckets: [0.01, 0.05, 0.1, 0.5, 1, 2, 5],
});

// Summary - quantiles (p50, p95, p99)
const responseSize = new Summary({
  name: 'app_http_response_size_bytes',
  help: 'HTTP response size in bytes',
  percentiles: [0.5, 0.9, 0.95, 0.99],
});
```

```php
<?php
// PHP - StatsD Example
class MetricsService
{
    private StatsD $client;
    
    public function incrementCounter(string $name, array $tags = []): void
    {
        $this->client->increment($this->formatName($name), 1, $tags);
    }
    
    public function gauge(string $name, float $value, array $tags = []): void
    {
        $this->client->gauge($this->formatName($name), $value, $tags);
    }
    
    public function timing(string $name, float $milliseconds, array $tags = []): void
    {
        $this->client->timing($this->formatName($name), $milliseconds, $tags);
    }
    
    public function histogram(string $name, float $value, array $tags = []): void
    {
        $this->client->histogram($this->formatName($name), $value, $tags);
    }
    
    private function formatName(string $name): string
    {
        return 'app.' . str_replace('_', '.', $name);
    }
}
```

```python
# Python - Prometheus Example
from prometheus_client import Counter, Gauge, Histogram, Summary

# Define metrics
http_requests_total = Counter(
    'app_http_requests_total',
    'Total HTTP requests',
    ['method', 'path', 'status']
)

active_connections = Gauge(
    'app_database_connections_active',
    'Active database connections'
)

request_duration = Histogram(
    'app_http_request_duration_seconds',
    'HTTP request duration in seconds',
    ['method', 'path'],
    buckets=[0.01, 0.05, 0.1, 0.5, 1, 2, 5]
)

# Usage
http_requests_total.labels(method='GET', path='/api/users', status='200').inc()
active_connections.set(42)
request_duration.labels(method='GET', path='/api/users').observe(0.235)
```

### Required Application Metrics

| Category    | Metric                           | Type      |
|------------|----------------------------------|-----------|
| HTTP       | `http_requests_total`            | Counter   |
| HTTP       | `http_request_duration_seconds`  | Histogram |
| HTTP       | `http_request_size_bytes`        | Histogram |
| HTTP       | `http_response_size_bytes`       | Histogram |
| Database   | `db_queries_total`               | Counter   |
| Database   | `db_query_duration_seconds`      | Histogram |
| Database   | `db_connections_active`          | Gauge     |
| Cache      | `cache_hits_total`               | Counter   |
| Cache      | `cache_misses_total`             | Counter   |
| Queue      | `queue_messages_total`           | Counter   |
| Queue      | `queue_messages_pending`         | Gauge     |
| Business   | `user_signups_total`             | Counter   |
| Business   | `orders_completed_total`         | Counter   |

---

## 18.3 Distributed Tracing

### Trace Context Propagation

```typescript
// TypeScript - OpenTelemetry Setup
import { trace, context, propagation, SpanKind } from '@opentelemetry/api';
import { W3CTraceContextPropagator } from '@opentelemetry/core';

// Initialize propagator
propagation.setGlobalPropagator(new W3CTraceContextPropagator());

// Create spans
const tracer = trace.getTracer('app-service');

async function handleRequest(req: Request): Promise<Response> {
  // Extract context from incoming request
  const parentContext = propagation.extract(context.active(), req.headers);
  
  return context.with(parentContext, async () => {
    const span = tracer.startSpan('handle-request', {
      kind: SpanKind.SERVER,
      attributes: {
        'http.method': req.method,
        'http.url': req.url,
      },
    });
    
    try {
      const result = await processRequest(req);
      span.setStatus({ code: 0 }); // OK
      return result;
    } catch (error) {
      span.setStatus({ code: 2, message: error.message }); // ERROR
      span.recordException(error);
      throw error;
    } finally {
      span.end();
    }
  });
}

// Child span example
async function queryDatabase(query: string): Promise<any> {
  const span = tracer.startSpan('db-query', {
    kind: SpanKind.CLIENT,
    attributes: {
      'db.system': 'postgresql',
      'db.statement': query.substring(0, 100), // Truncate for safety
    },
  });
  
  try {
    const result = await db.execute(query);
    span.setAttribute('db.rows_affected', result.rowCount);
    return result;
  } finally {
    span.end();
  }
}
```

```php
<?php
// PHP - OpenTelemetry Example
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;

class TracingMiddleware
{
    private TracerInterface $tracer;
    
    public function handle(Request $request, Closure $next): Response
    {
        // Extract parent context from headers
        $parentContext = $this->extractContext($request);
        
        $span = $this->tracer
            ->spanBuilder('http-request')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setParent($parentContext)
            ->setAttribute('http.method', $request->getMethod())
            ->setAttribute('http.url', $request->getUri())
            ->startSpan();
        
        $scope = $span->activate();
        
        try {
            $response = $next($request);
            $span->setAttribute('http.status_code', $response->getStatusCode());
            $span->setStatus(StatusCode::STATUS_OK);
            return $response;
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}
```

### Span Attributes Standards

| Category  | Attribute               | Required |
|-----------|------------------------|----------|
| HTTP      | `http.method`          | ✓        |
| HTTP      | `http.url`             | ✓        |
| HTTP      | `http.status_code`     | ✓        |
| HTTP      | `http.request_content_length` | ○  |
| Database  | `db.system`            | ✓        |
| Database  | `db.name`              | ✓        |
| Database  | `db.statement`         | ○ (truncated) |
| Queue     | `messaging.system`     | ✓        |
| Queue     | `messaging.destination`| ✓        |
| Custom    | `user.id`              | When available |
| Custom    | `tenant.id`            | Multi-tenant apps |

---

## 18.4 Health Checks

### Endpoint Standards

```typescript
// TypeScript - Health Check Implementation
interface HealthStatus {
  status: 'healthy' | 'degraded' | 'unhealthy';
  version: string;
  timestamp: string;
  checks: Record<string, ComponentHealth>;
}

interface ComponentHealth {
  status: 'healthy' | 'unhealthy';
  latency_ms?: number;
  message?: string;
}

// Liveness - Is the app running?
app.get('/health/live', (req, res) => {
  res.json({ status: 'healthy', timestamp: new Date().toISOString() });
});

// Readiness - Can the app serve traffic?
app.get('/health/ready', async (req, res) => {
  const checks: Record<string, ComponentHealth> = {};
  let overallStatus: 'healthy' | 'degraded' | 'unhealthy' = 'healthy';
  
  // Database check
  const dbStart = Date.now();
  try {
    await db.query('SELECT 1');
    checks.database = { status: 'healthy', latency_ms: Date.now() - dbStart };
  } catch (e) {
    checks.database = { status: 'unhealthy', message: e.message };
    overallStatus = 'unhealthy';
  }
  
  // Cache check
  const cacheStart = Date.now();
  try {
    await cache.ping();
    checks.cache = { status: 'healthy', latency_ms: Date.now() - cacheStart };
  } catch (e) {
    checks.cache = { status: 'unhealthy', message: e.message };
    overallStatus = overallStatus === 'unhealthy' ? 'unhealthy' : 'degraded';
  }
  
  const status: HealthStatus = {
    status: overallStatus,
    version: process.env.APP_VERSION || 'unknown',
    timestamp: new Date().toISOString(),
    checks,
  };
  
  res.status(overallStatus === 'unhealthy' ? 503 : 200).json(status);
});

// Startup - Has initialization completed?
app.get('/health/startup', (req, res) => {
  if (appInitialized) {
    res.json({ status: 'healthy' });
  } else {
    res.status(503).json({ status: 'initializing' });
  }
});
```

### Kubernetes Probe Configuration

```yaml
# Example Kubernetes deployment
spec:
  containers:
    - name: app
      livenessProbe:
        httpGet:
          path: /health/live
          port: 8080
        initialDelaySeconds: 10
        periodSeconds: 10
        failureThreshold: 3
      readinessProbe:
        httpGet:
          path: /health/ready
          port: 8080
        initialDelaySeconds: 5
        periodSeconds: 5
        failureThreshold: 3
      startupProbe:
        httpGet:
          path: /health/startup
          port: 8080
        initialDelaySeconds: 0
        periodSeconds: 2
        failureThreshold: 30
```

---

## 18.5 Alerting Standards

### Alert Severity Levels

| Level    | Response Time | Example                          |
|----------|--------------|----------------------------------|
| Critical | 5 minutes    | Service down, data loss risk     |
| High     | 30 minutes   | Degraded performance, errors >5% |
| Medium   | 4 hours      | Elevated latency, warnings       |
| Low      | 24 hours     | Capacity planning, trends        |

### Alert Rule Template

```yaml
# Prometheus AlertManager Example
groups:
  - name: application-alerts
    rules:
      - alert: HighErrorRate
        expr: |
          sum(rate(app_http_requests_total{status=~"5.."}[5m]))
          / sum(rate(app_http_requests_total[5m])) > 0.05
        for: 2m
        labels:
          severity: high
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value | humanizePercentage }} over the last 5 minutes"
          runbook_url: "https://wiki.example.com/runbooks/high-error-rate"
          
      - alert: SlowResponseTime
        expr: |
          histogram_quantile(0.95, 
            sum(rate(app_http_request_duration_seconds_bucket[5m])) by (le)
          ) > 2
        for: 5m
        labels:
          severity: medium
        annotations:
          summary: "Slow response times"
          description: "95th percentile latency is {{ $value | humanizeDuration }}"
          
      - alert: DatabaseConnectionPoolExhausted
        expr: app_database_connections_active >= app_database_connections_max * 0.9
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Database connection pool nearly exhausted"
          description: "Using {{ $value }} of max connections"
```

### Required Alerts (Minimum Set)

| Alert Name                    | Condition                      | Severity |
|------------------------------|-------------------------------|----------|
| ServiceDown                  | Up == 0 for 1m                | Critical |
| HighErrorRate                | 5xx rate > 5% for 2m          | High     |
| SlowResponseTime             | P95 latency > 2s for 5m       | Medium   |
| HighMemoryUsage              | Memory > 90% for 5m           | High     |
| DiskSpaceLow                 | Disk < 10% for 10m            | High     |
| DatabaseConnectionsHigh      | Connections > 90% max         | Critical |
| CertificateExpiringSoon      | Cert expires < 14 days        | Medium   |
| QueueBacklogGrowing          | Pending messages > threshold  | Medium   |

---

## 18.6 Dashboard Standards

### Required Dashboards

1. **Service Overview** - Key metrics at a glance
2. **Request Analysis** - Latency, throughput, errors
3. **Infrastructure** - CPU, memory, disk, network
4. **Business Metrics** - Domain-specific KPIs
5. **On-Call** - Critical alerts and recent incidents

### Dashboard Layout Guidelines

```
┌────────────────────────────────────────────────────────────┐
│  SERVICE OVERVIEW DASHBOARD                                │
├────────────────────────────────────────────────────────────┤
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐      │
│  │ Requests │ │  Errors  │ │ Latency  │ │  Uptime  │      │
│  │  1.2k/s  │ │   0.1%   │ │  45ms    │ │  99.9%   │      │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘      │
├────────────────────────────────────────────────────────────┤
│  Request Rate (24h)                 Error Rate (24h)       │
│  ████████████████████               ████████████████████   │
├────────────────────────────────────────────────────────────┤
│  Latency Distribution               Top Endpoints          │
│  ████████████████████               1. /api/users (40%)    │
│  P50: 20ms P95: 100ms               2. /api/orders (25%)   │
│  P99: 250ms                         3. /api/products (15%) │
└────────────────────────────────────────────────────────────┘
```

---

## 18.7 Log Correlation

### Correlation ID Implementation

```typescript
// TypeScript - Correlation ID Middleware
import { v4 as uuidv4 } from 'uuid';
import { AsyncLocalStorage } from 'async_hooks';

const correlationStorage = new AsyncLocalStorage<string>();

// Middleware to set correlation ID
function correlationMiddleware(req: Request, res: Response, next: NextFunction) {
  const correlationId = req.headers['x-correlation-id'] as string || uuidv4();
  res.setHeader('x-correlation-id', correlationId);
  
  correlationStorage.run(correlationId, () => {
    next();
  });
}

// Logger that includes correlation ID
function createLogger() {
  return {
    info: (message: string, context?: object) => {
      console.log(JSON.stringify({
        timestamp: new Date().toISOString(),
        level: 'info',
        correlation_id: correlationStorage.getStore(),
        message,
        ...context,
      }));
    },
    error: (message: string, error?: Error, context?: object) => {
      console.error(JSON.stringify({
        timestamp: new Date().toISOString(),
        level: 'error',
        correlation_id: correlationStorage.getStore(),
        message,
        error: error ? { message: error.message, stack: error.stack } : undefined,
        ...context,
      }));
    },
  };
}

// Propagate to downstream services
async function callDownstreamService(url: string): Promise<Response> {
  const correlationId = correlationStorage.getStore();
  return fetch(url, {
    headers: {
      'x-correlation-id': correlationId || '',
    },
  });
}
```

---

## 18.8 SLO/SLI Definitions

### Service Level Indicators (SLIs)

| SLI              | Definition                                  | Target |
|------------------|---------------------------------------------|--------|
| Availability     | Successful requests / Total requests        | 99.9%  |
| Latency          | % requests < threshold                      | 95% < 200ms |
| Error Rate       | Failed requests / Total requests            | < 0.1% |
| Throughput       | Requests per second sustained               | > 1000 RPS |

### Error Budget Calculation

```typescript
// Error Budget = 1 - SLO
// Example: 99.9% availability = 0.1% error budget

interface ErrorBudget {
  slo_target: number;           // 0.999
  period_days: number;          // 30
  total_minutes: number;        // 43200
  allowed_downtime_minutes: number; // 43.2
  consumed_minutes: number;     // Current downtime
  remaining_percent: number;    // Budget remaining
}

function calculateErrorBudget(
  sloTarget: number,
  periodDays: number,
  consumedDowntimeMinutes: number
): ErrorBudget {
  const totalMinutes = periodDays * 24 * 60;
  const allowedDowntime = totalMinutes * (1 - sloTarget);
  const remainingPercent = ((allowedDowntime - consumedDowntimeMinutes) / allowedDowntime) * 100;
  
  return {
    slo_target: sloTarget,
    period_days: periodDays,
    total_minutes: totalMinutes,
    allowed_downtime_minutes: allowedDowntime,
    consumed_minutes: consumedDowntimeMinutes,
    remaining_percent: Math.max(0, remainingPercent),
  };
}
```

---

## Related Specifications

- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Structured logging standards
- [03-performance-optimization-ux.md](../05-ux/03-performance-optimization-ux.md) - Performance metrics
- [03-deployment-cicd-devops.md](../06-devops/03-deployment-cicd-devops.md) - Deployment verification
- [02-incident-management-observability.md](./02-incident-management-observability.md) - Incident response
