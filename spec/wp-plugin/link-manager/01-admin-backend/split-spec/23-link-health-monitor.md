# 23 - Link Health Monitor Service

> **Status:** Complete  
> **Priority:** High  
> **Updated:** 2026-01-31  
> **Error Range:** 14850-14899

---

## Purpose

Periodic background monitoring of link health (HTTP status, response time, redirects) with configurable alerts for broken links, slow responses, and redirect chains.

---

## Dependencies

- `66-shared-constants.md` - Error codes, enums (SSOT)
- `04-database-schema.md` - Database tables
- `16-cron-system.md` - WP-Cron integration
- `17-rest-api-endpoints.md` - REST API patterns

---

## Constants (from SSOT)

```php
// Health Check Configuration
const HEALTH_CHECK_TIMEOUT = 10;           // seconds per request
const HEALTH_CHECK_BATCH_SIZE = 25;        // links per batch
const HEALTH_CHECK_CRON_INTERVAL = 3600;   // 1 hour default
const HEALTH_CHECK_MAX_REDIRECTS = 5;      // max redirect chain depth
const HEALTH_CHECK_RETRY_ATTEMPTS = 2;     // retry failed checks

// Thresholds
const HEALTH_SLOW_THRESHOLD_MS = 2000;     // slow response warning
const HEALTH_CRITICAL_THRESHOLD_MS = 5000; // critical response time
const HEALTH_STALE_DAYS = 7;               // days before recheck required
```

---

## Enums

### LinkHealthStatus

```php
enum LinkHealthStatus: string {
    case HEALTHY = 'healthy';           // 2xx response
    case REDIRECT = 'redirect';         // 3xx response
    case BROKEN = 'broken';             // 4xx/5xx or timeout
    case SLOW = 'slow';                 // Response > threshold
    case UNKNOWN = 'unknown';           // Not yet checked
    case EXCLUDED = 'excluded';         // Manually excluded
}
```

### HealthAlertType

```php
enum HealthAlertType: string {
    case BROKEN_LINK = 'broken_link';
    case REDIRECT_CHAIN = 'redirect_chain';
    case SLOW_RESPONSE = 'slow_response';
    case SSL_ERROR = 'ssl_error';
    case DNS_ERROR = 'dns_error';
    case TIMEOUT = 'timeout';
}
```

### HealthAlertSeverity

```php
enum HealthAlertSeverity: string {
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
}
```

### HealthCheckPriority

```php
enum HealthCheckPriority: string {
    case HIGH = 'high';         // External links, high-traffic pages
    case NORMAL = 'normal';     // Standard content links
    case LOW = 'low';           // Archive, old content
}
```

---

## Database Schema

### LinkHealthChecks Table

```sql
CREATE TABLE LinkHealthChecks (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    LinkId INTEGER NOT NULL,
    Url TEXT NOT NULL,
    Status TEXT NOT NULL DEFAULT 'unknown',
    HttpCode INTEGER,
    ResponseTimeMs INTEGER,
    RedirectCount INTEGER DEFAULT 0,
    FinalUrl TEXT,
    ErrorMessage TEXT,
    SslValid INTEGER DEFAULT 1,
    SslExpiry TEXT,
    Priority TEXT DEFAULT 'normal',
    LastCheckedAt TEXT,
    NextCheckAt TEXT,
    CheckCount INTEGER DEFAULT 0,
    ConsecutiveFailures INTEGER DEFAULT 0,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (LinkId) REFERENCES Links(Id) ON DELETE CASCADE
);

CREATE INDEX idx_health_status ON LinkHealthChecks(Status);
CREATE INDEX idx_health_next_check ON LinkHealthChecks(NextCheckAt);
CREATE INDEX idx_health_failures ON LinkHealthChecks(ConsecutiveFailures);
```

### HealthAlerts Table

```sql
CREATE TABLE HealthAlerts (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    HealthCheckId INTEGER NOT NULL,
    AlertType TEXT NOT NULL,
    Severity TEXT NOT NULL,
    Message TEXT NOT NULL,
    Details TEXT,                          -- JSON: additional context
    ContentId INTEGER,
    ContentType TEXT,
    Acknowledged INTEGER DEFAULT 0,
    AcknowledgedBy TEXT,
    AcknowledgedAt TEXT,
    ResolvedAt TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (HealthCheckId) REFERENCES LinkHealthChecks(Id) ON DELETE CASCADE
);

CREATE INDEX idx_alerts_severity ON HealthAlerts(Severity);
CREATE INDEX idx_alerts_acknowledged ON HealthAlerts(Acknowledged);
CREATE INDEX idx_alerts_created ON HealthAlerts(CreatedAt);
```

### HealthCheckJobs Table

```sql
CREATE TABLE HealthCheckJobs (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Status TEXT NOT NULL DEFAULT 'pending',
    TotalLinks INTEGER DEFAULT 0,
    ProcessedLinks INTEGER DEFAULT 0,
    HealthyCount INTEGER DEFAULT 0,
    BrokenCount INTEGER DEFAULT 0,
    SlowCount INTEGER DEFAULT 0,
    RedirectCount INTEGER DEFAULT 0,
    StartedAt TEXT,
    CompletedAt TEXT,
    ErrorMessage TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);
```

### HealthExclusions Table

```sql
CREATE TABLE HealthExclusions (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Pattern TEXT NOT NULL,                 -- URL pattern or domain
    PatternType TEXT NOT NULL,             -- 'domain', 'url', 'regex'
    Reason TEXT,
    CreatedBy TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_exclusions_pattern ON HealthExclusions(Pattern, PatternType);
```

---

## Core Interfaces

### HealthCheckResult

```typescript
interface HealthCheckResult {
  linkId: number;
  url: string;
  status: LinkHealthStatus;
  httpCode: number | null;
  responseTimeMs: number | null;
  redirectCount: number;
  finalUrl: string | null;
  redirectChain: string[];
  errorMessage: string | null;
  sslValid: boolean;
  sslExpiry: string | null;
  checkedAt: string;
}
```

### HealthAlert

```typescript
interface HealthAlert {
  id: number;
  healthCheckId: number;
  alertType: HealthAlertType;
  severity: HealthAlertSeverity;
  message: string;
  details: {
    url: string;
    httpCode?: number;
    responseTimeMs?: number;
    redirectChain?: string[];
    errorType?: string;
  };
  contentId: number | null;
  contentType: string | null;
  acknowledged: boolean;
  acknowledgedBy: string | null;
  acknowledgedAt: string | null;
  resolvedAt: string | null;
  createdAt: string;
}
```

### HealthSummary

```typescript
interface HealthSummary {
  totalLinks: number;
  checkedLinks: number;
  healthy: number;
  broken: number;
  slow: number;
  redirects: number;
  excluded: number;
  unknown: number;
  averageResponseMs: number;
  lastFullScan: string | null;
  activeAlerts: number;
  criticalAlerts: number;
}
```

---

## Service Interface

```php
interface LinkHealthMonitorInterface {
    // Single link check
    public function checkLink(int $linkId): HealthCheckResult;
    public function checkUrl(string $url): HealthCheckResult;
    
    // Batch operations
    public function startHealthScan(): int; // Returns job ID
    public function processHealthBatch(int $jobId): BatchResult;
    public function getJobProgress(int $jobId): JobProgress;
    
    // Scheduling
    public function scheduleCheck(int $linkId, string $priority): void;
    public function getStaleLinks(int $limit): array;
    
    // Alerts
    public function getActiveAlerts(array $filters): array;
    public function acknowledgeAlert(int $alertId, string $user): void;
    public function resolveAlert(int $alertId): void;
    public function getAlertStats(): array;
    
    // Exclusions
    public function addExclusion(string $pattern, string $type, string $reason): int;
    public function removeExclusion(int $id): void;
    public function getExclusions(): array;
    public function isExcluded(string $url): bool;
    
    // Reports
    public function getSummary(): HealthSummary;
    public function getBrokenLinks(int $page, int $perPage): PaginatedResult;
    public function getSlowLinks(int $page, int $perPage): PaginatedResult;
    public function getRedirectChains(int $minDepth): array;
}
```

---

## Health Check Logic

### Check Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    Health Check Pipeline                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  1. URL Validation                                                │
│     ├─ Check exclusion list                                      │
│     ├─ Validate URL format                                       │
│     └─ Normalize URL (remove tracking params)                    │
│                                                                   │
│  2. HTTP Request                                                  │
│     ├─ HEAD request first (faster)                               │
│     ├─ Fallback to GET if HEAD fails                             │
│     ├─ Follow redirects (up to MAX_REDIRECTS)                    │
│     └─ Capture timing, SSL info                                  │
│                                                                   │
│  3. Result Classification                                         │
│     ├─ 2xx → HEALTHY                                             │
│     ├─ 3xx → REDIRECT (capture chain)                            │
│     ├─ 4xx/5xx → BROKEN                                          │
│     ├─ Timeout → BROKEN (timeout error)                          │
│     └─ Response > threshold → SLOW                               │
│                                                                   │
│  4. Alert Generation                                              │
│     ├─ New broken link → Create alert                            │
│     ├─ Redirect chain > 2 → Warning alert                        │
│     ├─ SSL expiring < 30 days → Warning alert                    │
│     └─ Previously broken now healthy → Resolve alert             │
│                                                                   │
│  5. Schedule Next Check                                           │
│     ├─ Healthy → +7 days                                         │
│     ├─ Broken → +1 day (retry sooner)                            │
│     ├─ Slow → +3 days                                            │
│     └─ High priority → More frequent                             │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Priority-Based Scheduling

```php
function calculateNextCheck(
    LinkHealthStatus $status,
    HealthCheckPriority $priority,
    int $consecutiveFailures
): DateTimeImmutable {
    $baseIntervals = [
        'healthy' => ['high' => 3, 'normal' => 7, 'low' => 14],
        'broken' => ['high' => 1, 'normal' => 1, 'low' => 3],
        'slow' => ['high' => 2, 'normal' => 3, 'low' => 7],
        'redirect' => ['high' => 5, 'normal' => 7, 'low' => 14],
    ];
    
    $days = $baseIntervals[$status->value][$priority->value] ?? 7;
    
    // Exponential backoff for repeated failures (max 30 days)
    if ($consecutiveFailures > 0) {
        $days = min(30, $days * pow(2, $consecutiveFailures - 1));
    }
    
    return new DateTimeImmutable("+{$days} days");
}
```

---

## REST API Endpoints

### Health Monitoring

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/lm/v1/health/summary` | Get health summary stats |
| `GET` | `/lm/v1/health/checks` | List all health check records |
| `GET` | `/lm/v1/health/checks/{id}` | Get single health check |
| `POST` | `/lm/v1/health/check` | Check single URL immediately |
| `POST` | `/lm/v1/health/check/{linkId}` | Check specific link |

### Batch Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/lm/v1/health/scan/start` | Start full health scan |
| `GET` | `/lm/v1/health/scan/{jobId}` | Get scan progress |
| `POST` | `/lm/v1/health/scan/{jobId}/cancel` | Cancel running scan |
| `GET` | `/lm/v1/health/jobs` | List scan job history |

### Alerts

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/lm/v1/health/alerts` | List alerts with filters |
| `GET` | `/lm/v1/health/alerts/stats` | Get alert statistics |
| `PUT` | `/lm/v1/health/alerts/{id}/acknowledge` | Acknowledge alert |
| `PUT` | `/lm/v1/health/alerts/{id}/resolve` | Mark alert resolved |
| `DELETE` | `/lm/v1/health/alerts/{id}` | Delete alert |

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/lm/v1/health/broken` | List broken links |
| `GET` | `/lm/v1/health/slow` | List slow links |
| `GET` | `/lm/v1/health/redirects` | List redirect chains |
| `GET` | `/lm/v1/health/export` | Export health report (CSV) |

### Exclusions

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/lm/v1/health/exclusions` | List exclusion patterns |
| `POST` | `/lm/v1/health/exclusions` | Add exclusion |
| `DELETE` | `/lm/v1/health/exclusions/{id}` | Remove exclusion |

---

## WP-Cron Integration

### Scheduled Events

```php
// Register cron hooks
add_action('lm_health_check_cron', [$this, 'runScheduledHealthCheck']);

// Schedule on activation
if (!wp_next_scheduled('lm_health_check_cron')) {
    wp_schedule_event(time(), 'hourly', 'lm_health_check_cron');
}
```

### Batch Processing

```php
public function runScheduledHealthCheck(): void {
    $staleLinks = $this->getStaleLinks(HEALTH_CHECK_BATCH_SIZE);
    
    if (empty($staleLinks)) {
        $this->logger->info('No stale links to check', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
        return;
    }
    
    foreach ($staleLinks as $link) {
        try {
            $result = $this->checkLink($link['Id']);
            $this->updateHealthRecord($result);
            $this->processAlerts($result);
        } catch (Exception $e) {
            $this->logger->error('Health check failed', [
                'function' => __FUNCTION__,
                'file' => __FILE__,
                'linkId' => $link['Id'],
                'error' => $e->getMessage(),
                'stackTrace' => $this->captureStackTrace()
            ]);
        }
    }
}
```

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 14850 | `ERR_HEALTH_CHECK_FAILED` | HTTP request failed |
| 14851 | `ERR_HEALTH_TIMEOUT` | Request timed out |
| 14852 | `ERR_HEALTH_SSL_ERROR` | SSL certificate error |
| 14853 | `ERR_HEALTH_DNS_ERROR` | DNS resolution failed |
| 14854 | `ERR_HEALTH_TOO_MANY_REDIRECTS` | Exceeded redirect limit |
| 14855 | `ERR_HEALTH_INVALID_URL` | Malformed URL |
| 14856 | `ERR_HEALTH_EXCLUSION_EXISTS` | Exclusion pattern exists |
| 14857 | `ERR_HEALTH_JOB_NOT_FOUND` | Health check job not found |
| 14858 | `ERR_HEALTH_ALERT_NOT_FOUND` | Alert not found |
| 14859 | `ERR_HEALTH_SCAN_IN_PROGRESS` | Another scan already running |

---

## Acceptance Criteria

- [ ] Health checks complete within HEALTH_CHECK_TIMEOUT seconds
- [ ] Batch processing respects HEALTH_CHECK_BATCH_SIZE limit
- [ ] Redirect chains captured up to MAX_REDIRECTS depth
- [ ] Alerts created for new broken links automatically
- [ ] Alerts auto-resolve when links become healthy
- [ ] SSL expiry warnings generated 30 days before expiry
- [ ] Exclusion patterns prevent repeated failed checks
- [ ] All errors logged with function name, file path, and stack trace
- [ ] WP-Cron runs health checks at configured interval
- [ ] Export produces valid CSV with all health data

---

## Security

- **Authentication:** All endpoints require `manage_options` capability
- **Rate Limiting:** Max 100 immediate checks per hour
- **Timeout Protection:** Individual checks cannot exceed 10s
- **External Requests:** Use WordPress HTTP API with user-agent header
- **Data Sanitization:** All URLs validated before checking

---

## Related Specs

- `16-cron-system.md` - WP-Cron integration patterns
- `17-rest-api-endpoints.md` - REST API conventions
- `22-cron-auto-linking.md` - Similar batch processing patterns
- `../66-shared-constants.md` - Error codes and constants
