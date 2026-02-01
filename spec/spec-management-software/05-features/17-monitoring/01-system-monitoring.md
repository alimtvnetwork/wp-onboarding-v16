# 17.1 System Monitoring

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Frontend monitoring system for tracking performance metrics, error rates, and user interactions to maintain application health and identify issues.

**Cross-References:**
- [Error UI](../13-error-ui/00-overview.md) - Error reporting
- [LLM Live Logging](../06-ai-integration/06-llm-live-logging.md) - AI monitoring
- [Performance](../19-performance/00-overview.md) - Performance tracking

---

## 17.1.1 Monitoring Categories

| Category | Metrics | Purpose |
|----------|---------|---------|
| Performance | LCP, FID, CLS | Core Web Vitals |
| Errors | Error rate, stack traces | Bug tracking |
| API | Request latency, success rate | Backend health |
| User | Session duration, page views | Usage analytics |

---

## 17.1.2 Performance Metrics

```typescript
// Core Web Vitals tracking
import { onLCP, onFID, onCLS, onTTFB, onINP } from 'web-vitals';

const reportWebVitals = (metric: Metric) => {
  console.log(metric.name, metric.value);
  
  // Send to analytics
  analytics.track('web_vital', {
    name: metric.name,
    value: metric.value,
    rating: metric.rating,
  });
};

onLCP(reportWebVitals);
onFID(reportWebVitals);
onCLS(reportWebVitals);
onTTFB(reportWebVitals);
onINP(reportWebVitals);
```

---

## 17.1.3 Error Tracking

```typescript
// Global error handler
window.onerror = (message, source, lineno, colno, error) => {
  errorTracker.capture({
    type: 'uncaught_error',
    message: String(message),
    source,
    lineno,
    colno,
    stack: error?.stack,
    timestamp: Date.now(),
  });
};

// Promise rejection handler
window.onunhandledrejection = (event) => {
  errorTracker.capture({
    type: 'unhandled_rejection',
    reason: event.reason,
    timestamp: Date.now(),
  });
};

// React Error Boundary integration
const logErrorToService = (error: Error, errorInfo: ErrorInfo) => {
  errorTracker.capture({
    type: 'react_error',
    error: error.message,
    stack: error.stack,
    componentStack: errorInfo.componentStack,
    timestamp: Date.now(),
  });
};
```

---

## 17.1.4 API Monitoring

```typescript
// Request timing interceptor
apiClient.interceptors.request.use((config) => {
  config.metadata = { startTime: performance.now() };
  return config;
});

apiClient.interceptors.response.use(
  (response) => {
    const duration = performance.now() - response.config.metadata.startTime;
    
    metrics.track('api_request', {
      endpoint: response.config.url,
      method: response.config.method,
      status: response.status,
      duration,
    });
    
    return response;
  },
  (error) => {
    const duration = performance.now() - error.config.metadata.startTime;
    
    metrics.track('api_error', {
      endpoint: error.config.url,
      method: error.config.method,
      status: error.response?.status,
      duration,
      error: error.message,
    });
    
    throw error;
  }
);
```

---

## 17.1.5 Health Dashboard

```typescript
interface HealthMetrics {
  api: {
    avgLatency: number;
    errorRate: number;
    requestsPerMinute: number;
  };
  frontend: {
    lcp: number;
    fid: number;
    cls: number;
  };
  errors: {
    last24h: number;
    critical: number;
  };
}

// Health status indicator
const getHealthStatus = (metrics: HealthMetrics): 'healthy' | 'degraded' | 'critical' => {
  if (metrics.api.errorRate > 0.1 || metrics.errors.critical > 0) {
    return 'critical';
  }
  if (metrics.api.errorRate > 0.05 || metrics.api.avgLatency > 2000) {
    return 'degraded';
  }
  return 'healthy';
};
```

---

## 17.1.6 LLM Monitoring Integration

| Metric | Description | Alert Threshold |
|--------|-------------|-----------------|
| Model load time | Time to load LLM model | > 30s |
| Inference latency | Time per token generation | > 500ms |
| Memory usage | GPU/CPU memory consumption | > 90% |
| Error rate | Failed LLM requests | > 5% |

---

## 17.1.7 Network Request Monitoring

```typescript
interface NetworkRequest {
  id: string;
  url: string;
  method: string;
  status: number;
  duration: number;
  requestHeaders: Record<string, string>;
  responseHeaders: Record<string, string>;
  requestBody?: string;
  responseBody?: string;
  timestamp: number;
  error?: string;
}

// Network capture hook
const useNetworkCapture = () => {
  const [requests, setRequests] = useState<NetworkRequest[]>([]);
  
  // Intercept fetch
  useEffect(() => {
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
      const start = performance.now();
      const requestId = generateId();
      
      try {
        const response = await originalFetch(...args);
        const duration = performance.now() - start;
        
        setRequests(prev => [...prev, {
          id: requestId,
          url: args[0].toString(),
          method: args[1]?.method || 'GET',
          status: response.status,
          duration,
          timestamp: Date.now(),
        }]);
        
        return response;
      } catch (error) {
        setRequests(prev => [...prev, {
          id: requestId,
          url: args[0].toString(),
          method: args[1]?.method || 'GET',
          status: 0,
          duration: performance.now() - start,
          timestamp: Date.now(),
          error: error.message,
        }]);
        throw error;
      }
    };
    
    return () => { window.fetch = originalFetch; };
  }, []);
  
  return { requests, clearRequests: () => setRequests([]) };
};
```

---

## 17.1.8 Error Aggregation Dashboard

```typescript
interface AggregatedError {
  fingerprint: string;    // Unique error signature
  message: string;
  count: number;
  firstSeen: Date;
  lastSeen: Date;
  samples: ErrorSample[];
  status: 'new' | 'acknowledged' | 'resolved';
}

interface ErrorSample {
  id: string;
  timestamp: Date;
  stack?: string;
  context: Record<string, unknown>;
  userId?: string;
  sessionId: string;
}

// Error grouping by fingerprint
const groupErrors = (errors: ErrorSample[]): AggregatedError[] => {
  const groups = new Map<string, AggregatedError>();
  
  for (const error of errors) {
    const fingerprint = generateFingerprint(error.message, error.stack);
    
    if (groups.has(fingerprint)) {
      const group = groups.get(fingerprint)!;
      group.count++;
      group.lastSeen = error.timestamp;
      group.samples.push(error);
    } else {
      groups.set(fingerprint, {
        fingerprint,
        message: error.message,
        count: 1,
        firstSeen: error.timestamp,
        lastSeen: error.timestamp,
        samples: [error],
        status: 'new',
      });
    }
  }
  
  return Array.from(groups.values());
};
```

---

## 17.1.9 HAR Export

```typescript
// HTTP Archive (HAR) format export
interface HARLog {
  version: string;
  creator: { name: string; version: string };
  entries: HAREntry[];
}

const exportToHAR = (requests: NetworkRequest[]): HARLog => ({
  version: '1.2',
  creator: { name: 'SpecManager', version: '1.0.0' },
  entries: requests.map(req => ({
    startedDateTime: new Date(req.timestamp).toISOString(),
    time: req.duration,
    request: {
      method: req.method,
      url: req.url,
      headers: Object.entries(req.requestHeaders || {}).map(([name, value]) => ({ name, value })),
    },
    response: {
      status: req.status,
      statusText: getStatusText(req.status),
      headers: Object.entries(req.responseHeaders || {}).map(([name, value]) => ({ name, value })),
    },
    timings: { wait: req.duration, receive: 0 },
  })),
});
```

---

## 17.1.10 Acceptance Criteria

### Core Web Vitals (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| WV-001 | LCP tracked via web-vitals library onLCP() | Critical | Integration test |
| WV-002 | FID tracked via web-vitals library onFID() | Critical | Integration test |
| WV-003 | CLS tracked via web-vitals library onCLS() | Critical | Integration test |
| WV-004 | TTFB tracked via web-vitals library onTTFB() | High | Integration test |
| WV-005 | INP tracked via web-vitals library onINP() | High | Integration test |
| WV-006 | All metrics include rating (good/needs-improvement/poor) | High | Schema test |
| WV-007 | Metrics sent to analytics.track() | High | Integration test |

### Error Tracking (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| ET-001 | window.onerror captures uncaught exceptions | Critical | Error injection test |
| ET-002 | window.onunhandledrejection captures promise rejections | Critical | Error injection test |
| ET-003 | React ErrorBoundary errors captured with componentStack | Critical | Component test |
| ET-004 | All errors include timestamp, message, source, stack | Critical | Schema test |
| ET-005 | Errors deduplicated by fingerprint | High | Aggregation test |
| ET-006 | First 10 samples retained per error fingerprint | Medium | Retention test |
| ET-007 | Error status transitions (new → acknowledged → resolved) | High | State test |

### API Monitoring (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AM-001 | Request interceptor records startTime | Critical | Integration test |
| AM-002 | Response interceptor calculates duration | Critical | Integration test |
| AM-003 | Successful requests tracked with endpoint, method, status | High | Schema test |
| AM-004 | Failed requests tracked with error message | Critical | Error test |
| AM-005 | Average latency calculated per endpoint | High | Aggregation test |
| AM-006 | Error rate calculated (failures / total) | High | Aggregation test |
| AM-007 | Requests per minute calculated | Medium | Rate test |

### Network Request Capture (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| NR-001 | fetch() intercepted and logged | Critical | Integration test |
| NR-002 | Request URL, method, status captured | Critical | Schema test |
| NR-003 | Request/response headers captured | High | Schema test |
| NR-004 | Request duration measured accurately (±5ms) | High | Timing test |
| NR-005 | Failed requests include error message | Critical | Error test |
| NR-006 | Original fetch behavior preserved | Critical | Regression test |
| NR-007 | Cleanup restores original fetch on unmount | High | Resource test |

### Health Dashboard (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| HD-001 | Health status computed: healthy/degraded/critical | Critical | Unit test |
| HD-002 | Critical when errorRate > 10% OR criticalErrors > 0 | Critical | Threshold test |
| HD-003 | Degraded when errorRate > 5% OR avgLatency > 2000ms | High | Threshold test |
| HD-004 | Healthy when below degraded thresholds | High | Threshold test |
| HD-005 | Dashboard displays API metrics (latency, errorRate, rpm) | High | E2E test |
| HD-006 | Dashboard displays Core Web Vitals (LCP, FID, CLS) | High | E2E test |
| HD-007 | Dashboard displays error counts (24h, critical) | High | E2E test |

### LLM Monitoring Integration (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| LM-001 | Model load time tracked with > 30s alert threshold | High | Integration test |
| LM-002 | Inference latency tracked with > 500ms alert threshold | High | Integration test |
| LM-003 | Memory usage tracked with > 90% alert threshold | High | Integration test |
| LM-004 | LLM error rate tracked with > 5% alert threshold | High | Integration test |
| LM-005 | Alerts trigger notification when threshold exceeded | High | Alert test |

### HAR Export (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| HAR-001 | Export produces valid HAR 1.2 format | Critical | Schema validation |
| HAR-002 | All captured requests included in export | High | Completeness test |
| HAR-003 | Timestamps formatted as ISO8601 | High | Format test |
| HAR-004 | Request/response headers included | High | Schema test |
| HAR-005 | Timing data accurately reflects duration | High | Accuracy test |
| HAR-006 | Export downloadable as .har file | High | E2E test |

### Error Aggregation Dashboard (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EA-001 | Errors grouped by fingerprint (message + stack signature) | Critical | Aggregation test |
| EA-002 | Count, firstSeen, lastSeen tracked per group | High | Schema test |
| EA-003 | Status filter (new/acknowledged/resolved) | High | E2E test |
| EA-004 | Sort by count, lastSeen, firstSeen | High | E2E test |
| EA-005 | Expand to view sample errors with full stack | High | E2E test |
| EA-006 | Mark as acknowledged/resolved updates status | High | State test |
| EA-007 | Notification badge shows unread error count | High | E2E test |

### Monitoring Dashboard UI (UI Requirements)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| UI-001 | Tabbed interface: Errors, LLM Logs, System Health, Network | Critical | E2E test |
| UI-002 | Errors tab shows aggregated errors with expand/collapse | High | E2E test |
| UI-003 | LLM Logs tab streams real-time with pause/resume | High | E2E test |
| UI-004 | System Health tab shows Core Web Vitals gauges | High | E2E test |
| UI-005 | Network tab shows request list with HAR export | High | E2E test |
| UI-006 | Sidebar notification badge for unread errors | High | E2E test |
| UI-007 | Time range filter (1h, 24h, 7d) for all tabs | Medium | E2E test |
| UI-008 | Search/filter by message, endpoint, status | High | E2E test |
| UI-009 | Auto-refresh toggle for real-time data | Medium | E2E test |

### Performance (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PF-001 | Monitoring overhead < 2% of page load time | Critical | Performance test |
| PF-002 | Request capture adds < 1ms per request | High | Benchmark test |
| PF-003 | Error aggregation handles 10,000 errors efficiently | High | Load test |
| PF-004 | Dashboard renders smoothly with 1000+ entries | High | UI performance test |
| PF-005 | Memory usage stable under continuous monitoring | Critical | Memory test |

---

## Related Specs

- [Error Management](../../06-error-management/00-overview.md)
- [LLM Live Logging](../06-ai-integration/06-llm-live-logging.md)
- [Performance](../19-performance/00-overview.md)
