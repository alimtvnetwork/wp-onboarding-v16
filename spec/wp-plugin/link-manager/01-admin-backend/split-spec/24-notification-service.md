# 24 - Notification Service

> **Status:** Complete  
> **Priority:** High  
> **Updated:** 2026-01-31  
> **Error Range:** 14860-14879

---

## Purpose

Multi-channel notification delivery for health alerts, system events, and scheduled reports. Supports email digests, webhooks, and admin notices with configurable routing and batching.

---

## Dependencies

- `66-shared-constants.md` - Error codes, enums (SSOT)
- `04-database-schema.md` - Database tables
- `16-cron-system.md` - WP-Cron integration
- `17-rest-api-endpoints.md` - REST API patterns
- `23-link-health-monitor.md` - Health alert triggers

---

## Constants (from SSOT)

```php
// Notification Configuration
const NOTIFICATION_BATCH_SIZE = 50;         // Max notifications per batch
const NOTIFICATION_DIGEST_INTERVAL = 86400; // Daily digest (24 hours)
const NOTIFICATION_RETRY_ATTEMPTS = 3;      // Retry failed deliveries
const NOTIFICATION_RETRY_DELAY = 300;       // 5 minutes between retries
const WEBHOOK_TIMEOUT_SECONDS = 10;         // Webhook request timeout
const WEBHOOK_MAX_PAYLOAD_SIZE = 65536;     // 64KB max payload

// Email Configuration
const EMAIL_FROM_NAME = 'Link Manager';
const EMAIL_BATCH_LIMIT = 100;              // Max emails per batch
const EMAIL_TEMPLATE_CACHE_TTL = 3600;      // Template cache (1 hour)

// Rate Limits
const WEBHOOK_RATE_LIMIT = 60;              // Webhooks per minute per endpoint
const EMAIL_RATE_LIMIT = 100;               // Emails per hour
```

---

## Enums

### NotificationChannel

```php
enum NotificationChannel: string {
    case EMAIL = 'EMAIL';               // WordPress wp_mail or SMTP
    case WEBHOOK = 'WEBHOOK';           // HTTP POST to external URL
    case ADMIN_NOTICE = 'ADMIN_NOTICE'; // WordPress admin dashboard
    case LOG = 'LOG';                   // Internal log only
}
```

### NotificationPriority

```php
enum NotificationPriority: string {
    case IMMEDIATE = 'IMMEDIATE';   // Send now, bypass batching
    case HIGH = 'HIGH';             // Include in next batch
    case NORMAL = 'NORMAL';         // Standard batch processing
    case LOW = 'LOW';               // Digest only
}
```

### NotificationType

```php
enum NotificationType: string {
    // Health Alerts
    case BROKEN_LINK_DETECTED = 'BROKEN_LINK_DETECTED';
    case SLOW_LINK_DETECTED = 'SLOW_LINK_DETECTED';
    case SSL_EXPIRY_WARNING = 'SSL_EXPIRY_WARNING';
    case REDIRECT_CHAIN_WARNING = 'REDIRECT_CHAIN_WARNING';
    
    // Health Reports
    case HEALTH_SCAN_COMPLETE = 'HEALTH_SCAN_COMPLETE';
    case DAILY_HEALTH_DIGEST = 'DAILY_HEALTH_DIGEST';
    case WEEKLY_HEALTH_REPORT = 'WEEKLY_HEALTH_REPORT';
    
    // System Events
    case SCAN_COMPLETE = 'SCAN_COMPLETE';
    case SCAN_FAILED = 'SCAN_FAILED';
    case SNAPSHOT_CREATED = 'SNAPSHOT_CREATED';
    case IMPORT_COMPLETE = 'IMPORT_COMPLETE';
    
    // Threshold Alerts
    case BROKEN_THRESHOLD_EXCEEDED = 'BROKEN_THRESHOLD_EXCEEDED';
    case SLOW_THRESHOLD_EXCEEDED = 'SLOW_THRESHOLD_EXCEEDED';
}
```

### NotificationStatus

```php
enum NotificationStatus: string {
    case PENDING = 'PENDING';       // Queued for delivery
    case SENT = 'SENT';             // Successfully delivered
    case FAILED = 'FAILED';         // Delivery failed
    case RETRYING = 'RETRYING';     // Retry in progress
    case CANCELLED = 'CANCELLED';   // Cancelled by user
}
```

### WebhookAuthType

```php
enum WebhookAuthType: string {
    case NONE = 'NONE';
    case HMAC_SHA256 = 'HMAC_SHA256';
    case BEARER_TOKEN = 'BEARER_TOKEN';
    case BASIC_AUTH = 'BASIC_AUTH';
}
```

---

## Database Schema

### NotificationQueue Table

```sql
CREATE TABLE NotificationQueue (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Type TEXT NOT NULL,
    Channel TEXT NOT NULL,
    Priority TEXT NOT NULL DEFAULT 'NORMAL',
    Status TEXT NOT NULL DEFAULT 'PENDING',
    Recipient TEXT NOT NULL,              -- Email address or webhook URL
    Subject TEXT,
    Payload TEXT NOT NULL,                -- JSON: notification data
    Attempts INTEGER DEFAULT 0,
    LastAttemptAt TEXT,
    LastError TEXT,
    ScheduledFor TEXT,                    -- Future delivery time
    SentAt TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT chk_channel CHECK (Channel IN ('EMAIL', 'WEBHOOK', 'ADMIN_NOTICE', 'LOG'))
);

CREATE INDEX idx_notification_status ON NotificationQueue(Status);
CREATE INDEX idx_notification_scheduled ON NotificationQueue(ScheduledFor);
CREATE INDEX idx_notification_channel ON NotificationQueue(Channel);
```

### NotificationRecipients Table

```sql
CREATE TABLE NotificationRecipients (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Email TEXT NOT NULL,
    Name TEXT,
    IsActive INTEGER DEFAULT 1,
    NotificationTypes TEXT,               -- JSON: enabled notification types
    Channels TEXT,                        -- JSON: preferred channels
    DigestPreference TEXT DEFAULT 'DAILY', -- IMMEDIATE, DAILY, WEEKLY
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT uq_recipient_email UNIQUE (Email)
);
```

### WebhookEndpoints Table

```sql
CREATE TABLE WebhookEndpoints (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Name TEXT NOT NULL,
    Url TEXT NOT NULL,
    AuthType TEXT DEFAULT 'NONE',
    AuthSecret TEXT,                      -- Encrypted secret/token
    IsActive INTEGER DEFAULT 1,
    NotificationTypes TEXT,               -- JSON: subscribed notification types
    Headers TEXT,                         -- JSON: custom headers
    RetryEnabled INTEGER DEFAULT 1,
    LastSuccessAt TEXT,
    LastFailureAt TEXT,
    ConsecutiveFailures INTEGER DEFAULT 0,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_webhook_url ON WebhookEndpoints(Url);
```

### NotificationLog Table

```sql
CREATE TABLE NotificationLog (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    NotificationId INTEGER,
    Channel TEXT NOT NULL,
    Recipient TEXT NOT NULL,
    Type TEXT NOT NULL,
    Status TEXT NOT NULL,
    ResponseCode INTEGER,
    ResponseBody TEXT,
    DurationMs INTEGER,
    ErrorMessage TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (NotificationId) REFERENCES NotificationQueue(Id) ON DELETE SET NULL
);

CREATE INDEX idx_notification_log_created ON NotificationLog(CreatedAt);
CREATE INDEX idx_notification_log_status ON NotificationLog(Status);
```

### NotificationSettings Table

```sql
CREATE TABLE NotificationSettings (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    SettingKey TEXT NOT NULL UNIQUE,
    SettingValue TEXT NOT NULL,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Default settings
INSERT INTO NotificationSettings (SettingKey, SettingValue) VALUES
    ('email_enabled', 'true'),
    ('webhook_enabled', 'true'),
    ('admin_notice_enabled', 'true'),
    ('digest_enabled', 'true'),
    ('digest_time', '09:00'),
    ('broken_threshold', '5'),
    ('slow_threshold', '10'),
    ('ssl_warning_days', '30');
```

---

## Core Interfaces

### Notification

```typescript
interface Notification {
  id: number;
  type: NotificationType;
  channel: NotificationChannel;
  priority: NotificationPriority;
  status: NotificationStatus;
  recipient: string;
  subject: string | null;
  payload: NotificationPayload;
  attempts: number;
  lastAttemptAt: string | null;
  lastError: string | null;
  scheduledFor: string | null;
  sentAt: string | null;
  createdAt: string;
}
```

### NotificationPayload

```typescript
interface NotificationPayload {
  // Common fields
  type: NotificationType;
  timestamp: string;
  siteUrl: string;
  siteName: string;
  
  // Health alert specific
  alert?: {
    id: number;
    severity: HealthAlertSeverity;
    url: string;
    httpCode?: number;
    responseTimeMs?: number;
    errorMessage?: string;
    contentTitle?: string;
    contentUrl?: string;
  };
  
  // Digest/Report specific
  summary?: {
    totalLinks: number;
    brokenLinks: number;
    slowLinks: number;
    newAlerts: number;
    resolvedAlerts: number;
    period: string;
  };
  
  // Event specific
  event?: {
    action: string;
    details: Record<string, unknown>;
  };
}
```

### WebhookPayload

```typescript
interface WebhookPayload {
  event: NotificationType;
  timestamp: string;
  signature?: string;           // HMAC signature if configured
  data: NotificationPayload;
}
```

### NotificationRecipient

```typescript
interface NotificationRecipient {
  id: number;
  email: string;
  name: string | null;
  isActive: boolean;
  notificationTypes: NotificationType[];
  channels: NotificationChannel[];
  digestPreference: 'IMMEDIATE' | 'DAILY' | 'WEEKLY';
  createdAt: string;
  updatedAt: string;
}
```

### WebhookEndpoint

```typescript
interface WebhookEndpoint {
  id: number;
  name: string;
  url: string;
  authType: WebhookAuthType;
  isActive: boolean;
  notificationTypes: NotificationType[];
  headers: Record<string, string>;
  retryEnabled: boolean;
  lastSuccessAt: string | null;
  lastFailureAt: string | null;
  consecutiveFailures: number;
  createdAt: string;
  updatedAt: string;
}
```

---

## Service Interface

```php
interface NotificationServiceInterface {
    // Queue operations
    public function queue(NotificationType $type, array $data, NotificationPriority $priority = NotificationPriority::NORMAL): int;
    public function queueForRecipient(int $recipientId, NotificationType $type, array $data): int;
    public function queueWebhook(int $endpointId, NotificationType $type, array $data): int;
    public function cancelNotification(int $notificationId): void;
    
    // Delivery
    public function processBatch(): BatchResult;
    public function sendImmediate(int $notificationId): bool;
    public function retryFailed(): int;
    
    // Email operations
    public function sendEmail(string $to, string $subject, string $template, array $data): bool;
    public function sendDigest(int $recipientId): bool;
    public function scheduleDigest(string $time): void;
    
    // Webhook operations
    public function sendWebhook(int $endpointId, array $payload): WebhookResult;
    public function testWebhook(int $endpointId): WebhookResult;
    public function signPayload(string $payload, string $secret): string;
    
    // Recipients
    public function addRecipient(string $email, string $name, array $preferences): int;
    public function updateRecipient(int $id, array $preferences): void;
    public function removeRecipient(int $id): void;
    public function getRecipients(): array;
    public function getRecipientsByType(NotificationType $type): array;
    
    // Webhooks
    public function addWebhookEndpoint(string $name, string $url, array $config): int;
    public function updateWebhookEndpoint(int $id, array $config): void;
    public function removeWebhookEndpoint(int $id): void;
    public function getWebhookEndpoints(): array;
    
    // Settings
    public function getSetting(string $key): mixed;
    public function updateSetting(string $key, mixed $value): void;
    public function getNotificationStats(): NotificationStats;
    
    // Log
    public function getDeliveryLog(array $filters, int $page, int $perPage): PaginatedResult;
    public function cleanupOldLogs(int $daysToKeep): int;
}
```

---

## Notification Flow

### Queue and Delivery Pipeline

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Notification Pipeline                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  1. Event Trigger                                                     │
│     ├─ Health Monitor detects broken link                            │
│     ├─ Scan completes                                                 │
│     ├─ SSL certificate expiring                                       │
│     └─ Threshold exceeded                                             │
│                                                                       │
│  2. Notification Creation                                             │
│     ├─ Determine notification type                                    │
│     ├─ Build payload with context                                     │
│     ├─ Look up recipients for type                                    │
│     └─ Create queue entries per channel                               │
│                                                                       │
│  3. Priority Routing                                                  │
│     ├─ IMMEDIATE → Send now                                           │
│     ├─ HIGH → Next batch (5 min)                                      │
│     ├─ NORMAL → Standard batch (15 min)                               │
│     └─ LOW → Digest only                                              │
│                                                                       │
│  4. Channel Dispatch                                                  │
│     ├─ EMAIL → Render template → wp_mail()                            │
│     ├─ WEBHOOK → Sign payload → HTTP POST                             │
│     ├─ ADMIN_NOTICE → Store for dashboard display                     │
│     └─ LOG → Write to notification log                                │
│                                                                       │
│  5. Delivery Tracking                                                 │
│     ├─ Record status (SENT/FAILED)                                    │
│     ├─ Log response details                                           │
│     ├─ Schedule retry if failed                                       │
│     └─ Update stats                                                   │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Webhook Signature Flow

```php
function signWebhookPayload(string $payload, string $secret): string {
    $timestamp = time();
    $signaturePayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signaturePayload, $secret);
    
    return "t={$timestamp},v1={$signature}";
}

function verifyWebhookSignature(
    string $payload,
    string $signature,
    string $secret,
    int $tolerance = 300  // 5 minutes
): bool {
    // Parse signature header
    preg_match('/t=(\d+),v1=([a-f0-9]+)/', $signature, $matches);
    $timestamp = (int) $matches[1];
    $providedSig = $matches[2];
    
    // Check timestamp tolerance
    if (abs(time() - $timestamp) > $tolerance) {
        return false;
    }
    
    // Verify signature
    $expectedSig = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    return hash_equals($expectedSig, $providedSig);
}
```

---

## Email Templates

### Template Structure

```php
// templates/email/base.php
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= esc_html($subject) ?></title>
    <style>
        /* Inline CSS for email clients */
        .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
        .header { background: #1e3a5f; color: white; padding: 20px; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 15px; font-size: 12px; color: #666; }
        .alert-critical { border-left: 4px solid #dc3545; }
        .alert-warning { border-left: 4px solid #ffc107; }
        .alert-info { border-left: 4px solid #17a2b8; }
        .btn { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Link Manager Alert</h1>
        </div>
        <div class="content">
            <?= $content ?>
        </div>
        <div class="footer">
            <p>This is an automated message from Link Manager on <?= esc_html($siteName) ?></p>
            <p><a href="<?= esc_url($unsubscribeUrl) ?>">Unsubscribe</a> from these notifications</p>
        </div>
    </div>
</body>
</html>
```

### Available Templates

| Template | Description | Variables |
|----------|-------------|-----------|
| `broken-link.php` | Single broken link alert | `url`, `httpCode`, `contentTitle`, `contentUrl` |
| `health-digest.php` | Daily/weekly health summary | `summary`, `topIssues[]`, `period` |
| `ssl-warning.php` | SSL expiry warning | `domain`, `expiryDate`, `daysRemaining` |
| `scan-complete.php` | Scan completion report | `totalLinks`, `brokenCount`, `duration` |
| `threshold-alert.php` | Threshold exceeded alert | `thresholdType`, `currentValue`, `threshold` |

---

## REST API Endpoints

### Notification Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/lm/v1/notifications` | List notification queue |
| `GET` | `/lm/v1/notifications/{id}` | Get notification details |
| `POST` | `/lm/v1/notifications/{id}/retry` | Retry failed notification |
| `DELETE` | `/lm/v1/notifications/{id}` | Cancel/delete notification |

### Recipients

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/lm/v1/notifications/recipients` | List recipients |
| `POST` | `/lm/v1/notifications/recipients` | Add recipient |
| `PUT` | `/lm/v1/notifications/recipients/{id}` | Update recipient |
| `DELETE` | `/lm/v1/notifications/recipients/{id}` | Remove recipient |

### Webhooks

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/lm/v1/notifications/webhooks` | List webhook endpoints |
| `POST` | `/lm/v1/notifications/webhooks` | Add webhook endpoint |
| `PUT` | `/lm/v1/notifications/webhooks/{id}` | Update webhook |
| `DELETE` | `/lm/v1/notifications/webhooks/{id}` | Remove webhook |
| `POST` | `/lm/v1/notifications/webhooks/{id}/test` | Test webhook delivery |

### Settings & Stats

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/lm/v1/notifications/settings` | Get notification settings |
| `PUT` | `/lm/v1/notifications/settings` | Update settings |
| `GET` | `/lm/v1/notifications/stats` | Get delivery statistics |
| `GET` | `/lm/v1/notifications/log` | Get delivery log |
| `DELETE` | `/lm/v1/notifications/log` | Clear old log entries |

### Digest Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/lm/v1/notifications/digest/send` | Send digest now |
| `GET` | `/lm/v1/notifications/digest/preview` | Preview digest content |
| `PUT` | `/lm/v1/notifications/digest/schedule` | Update digest schedule |

---

## WP-Cron Integration

### Scheduled Events

```php
// Register cron hooks
add_action('lm_notification_batch_cron', [$this, 'processPendingNotifications']);
add_action('lm_notification_digest_cron', [$this, 'sendScheduledDigests']);
add_action('lm_notification_cleanup_cron', [$this, 'cleanupOldNotifications']);

// Schedule on activation
public function scheduleNotificationCrons(): void {
    if (!wp_next_scheduled('lm_notification_batch_cron')) {
        wp_schedule_event(time(), 'every_5_minutes', 'lm_notification_batch_cron');
    }
    
    if (!wp_next_scheduled('lm_notification_digest_cron')) {
        $digestTime = $this->getSetting('digest_time') ?? '09:00';
        $nextRun = $this->calculateNextDigestTime($digestTime);
        wp_schedule_event($nextRun, 'daily', 'lm_notification_digest_cron');
    }
    
    if (!wp_next_scheduled('lm_notification_cleanup_cron')) {
        wp_schedule_event(time(), 'weekly', 'lm_notification_cleanup_cron');
    }
}
```

### Batch Processing

```php
public function processPendingNotifications(): void {
    $pending = $this->repository->getPendingNotifications(NOTIFICATION_BATCH_SIZE);
    
    if (empty($pending)) {
        return;
    }
    
    $this->logger->info('Processing notification batch', [
        'function' => __FUNCTION__,
        'file' => __FILE__,
        'count' => count($pending)
    ]);
    
    foreach ($pending as $notification) {
        try {
            $this->deliver($notification);
        } catch (Exception $e) {
            $this->handleDeliveryFailure($notification, $e);
        }
    }
}

private function deliver(Notification $notification): void {
    $result = match ($notification->channel) {
        NotificationChannel::EMAIL => $this->deliverEmail($notification),
        NotificationChannel::WEBHOOK => $this->deliverWebhook($notification),
        NotificationChannel::ADMIN_NOTICE => $this->storeAdminNotice($notification),
        NotificationChannel::LOG => $this->logNotification($notification),
    };
    
    $this->repository->updateStatus(
        $notification->id,
        $result->success ? NotificationStatus::SENT : NotificationStatus::FAILED,
        $result->error
    );
    
    $this->logDelivery($notification, $result);
}
```

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 14860 | `ERR_NOTIFICATION_QUEUE_FAILED` | Failed to queue notification |
| 14861 | `ERR_NOTIFICATION_DELIVERY_FAILED` | Delivery failed |
| 14862 | `ERR_NOTIFICATION_TEMPLATE_NOT_FOUND` | Email template not found |
| 14863 | `ERR_NOTIFICATION_INVALID_RECIPIENT` | Invalid recipient email |
| 14864 | `ERR_NOTIFICATION_RATE_LIMITED` | Rate limit exceeded |
| 14865 | `ERR_WEBHOOK_TIMEOUT` | Webhook request timed out |
| 14866 | `ERR_WEBHOOK_INVALID_URL` | Invalid webhook URL |
| 14867 | `ERR_WEBHOOK_AUTH_FAILED` | Webhook authentication failed |
| 14868 | `ERR_WEBHOOK_PAYLOAD_TOO_LARGE` | Payload exceeds size limit |
| 14869 | `ERR_WEBHOOK_ENDPOINT_NOT_FOUND` | Webhook endpoint not found |
| 14870 | `ERR_DIGEST_BUILD_FAILED` | Failed to build digest |
| 14871 | `ERR_RECIPIENT_NOT_FOUND` | Recipient not found |
| 14872 | `ERR_RECIPIENT_DUPLICATE` | Duplicate recipient email |
| 14873 | `ERR_NOTIFICATION_CANCELLED` | Notification was cancelled |
| 14874 | `ERR_EMAIL_SEND_FAILED` | wp_mail() failed |

---

## Acceptance Criteria

- [ ] Notifications queue within 100ms of trigger event
- [ ] Email delivery via wp_mail with proper headers
- [ ] Webhook signatures verify correctly with HMAC-SHA256
- [ ] Failed notifications retry up to NOTIFICATION_RETRY_ATTEMPTS times
- [ ] Digest emails aggregate by configured time period
- [ ] Rate limiting prevents abuse (EMAIL_RATE_LIMIT, WEBHOOK_RATE_LIMIT)
- [ ] Admin notices display on WordPress dashboard
- [ ] Delivery log retains entries for configurable period
- [ ] All errors logged with function name, file path, and stack trace
- [ ] Webhook endpoints support custom headers and auth types
- [ ] Unsubscribe links work for email recipients

---

## Security

- **Authentication:** All endpoints require `manage_options` capability
- **Webhook Secrets:** Stored encrypted in database
- **Rate Limiting:** Per-channel rate limits enforced
- **Input Validation:** Email addresses validated, URLs sanitized
- **Payload Signing:** HMAC-SHA256 for webhook authenticity
- **Log Sanitization:** Sensitive data (secrets, tokens) not logged

---

## Performance Considerations

- **Async Delivery:** All notifications queued, not sent synchronously
- **Batch Processing:** Limits prevent memory exhaustion
- **Connection Pooling:** Reuse HTTP connections for webhook batches
- **Template Caching:** Email templates cached for TTL
- **Index Optimization:** Indexes on Status, ScheduledFor, Channel columns
