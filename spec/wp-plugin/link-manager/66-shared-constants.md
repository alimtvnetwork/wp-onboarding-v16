# Link Manager - Shared Constants

> **Version:** 1.0.0  
> **Last Updated:** 2026-01-31  
> **Purpose:** Single Source of Truth for all cross-cutting values

---

## 🔤 Plugin Identity

```php
const PLUGIN_NAME = 'Link Manager';
const PLUGIN_SLUG = 'link-manager';
const PLUGIN_VERSION = '1.0.0';
const DB_VERSION = '1.0.0';
const MIN_PHP_VERSION = '8.0';
const MIN_WP_VERSION = '6.0';
const TEXT_DOMAIN = 'link-manager';
```

---

## 🗄️ Database Constants

### Option Prefixes
```php
const OPTION_PREFIX = 'lm_';
const TRANSIENT_PREFIX = 'lm_cache_';
```

### Database Paths
```php
// Main database (relative to data folder)
const MAIN_DB_NAME = 'link-manager.db';

// History database folder structure
const HISTORY_FOLDER = 'history-manage';
const HISTORY_POSTS_FOLDER = 'posts';
const HISTORY_PAGES_FOLDER = 'pages';
const HISTORY_CATEGORIES_FOLDER = 'categories';

// Snapshot folder
const SNAPSHOT_FOLDER = 'snapshots';
```

### Table Names (PascalCase)
```php
const TABLE_POST = 'Post';
const TABLE_PAGE = 'Page';
const TABLE_CATEGORY = 'Category';
const TABLE_LINK = 'Link';
const TABLE_SCAN_HISTORY = 'ScanHistory';
const TABLE_SNAPSHOT = 'Snapshot';
const TABLE_SETTINGS = 'Settings';
const TABLE_SCAN_JOBS = 'ScanJobs';     // Cron job records
const TABLE_JOB_QUEUE = 'JobQueue';     // Cron batch queue

// Internal Linking tables
const TABLE_LINK_TARGET = 'LinkTarget';
const TABLE_LINK_TEMPLATE = 'LinkTemplate';
const TABLE_LINK_VARIABLE = 'LinkVariable';
const TABLE_INTERNAL_LINK = 'InternalLink';

// Auto-Link Cron tables
const TABLE_AUTO_LINK_JOBS = 'AutoLinkJobs';
const TABLE_AUTO_LINK_QUEUE = 'AutoLinkQueue';
const TABLE_AUTO_LINK_SCHEDULES = 'AutoLinkSchedules';

// Health Monitor tables
const TABLE_LINK_HEALTH_CHECKS = 'LinkHealthChecks';
const TABLE_HEALTH_ALERTS = 'HealthAlerts';
const TABLE_HEALTH_CHECK_JOBS = 'HealthCheckJobs';
const TABLE_HEALTH_EXCLUSIONS = 'HealthExclusions';

// Notification tables
const TABLE_NOTIFICATION_QUEUE = 'NotificationQueue';
const TABLE_NOTIFICATION_RECIPIENTS = 'NotificationRecipients';
const TABLE_WEBHOOK_ENDPOINTS = 'WebhookEndpoints';
const TABLE_NOTIFICATION_LOG = 'NotificationLog';
const TABLE_NOTIFICATION_SETTINGS = 'NotificationSettings';

// Yoast SEO Integration tables
const TABLE_YOAST_SETTINGS = 'YoastSettings';
const TABLE_YOAST_AUDIT_LOG = 'YoastAuditLog';
const TABLE_YOAST_OPTIMIZATION_QUEUE = 'YoastOptimizationQueue';

// History DB tables
const TABLE_CONTENT_VERSION = 'ContentVersion';
const TABLE_MODIFICATION_LOG = 'ModificationLog';
```

---

## 🌐 API Constants

### REST API
```php
const API_NAMESPACE = 'lm/v1';
const API_RATE_LIMIT = 100;           // requests per window
const API_RATE_WINDOW = 60;           // seconds
```

### Endpoints
| Method | Path | Description |
|--------|------|-------------|
| POST | `/scan/start` | Start link scan |
| GET | `/scan/status` | Get scan status |
| POST | `/scan/cancel` | Cancel running scan |
| GET | `/posts` | List scanned posts |
| GET | `/posts/{id}` | Get post details |
| GET | `/posts/{id}/links` | Get links for post |
| GET | `/pages` | List scanned pages |
| GET | `/pages/{id}` | Get page details |
| GET | `/pages/{id}/links` | Get links for page |
| POST | `/links/{id}/modify` | Modify single link |
| POST | `/links/bulk-modify` | Bulk modify links |
| GET | `/history/{type}/{id}` | Get history for content |
| POST | `/history/{type}/{id}/rollback` | Rollback to version |
| GET | `/snapshots` | List snapshots |
| POST | `/snapshots/create` | Create snapshot |
| POST | `/snapshots/{id}/restore` | Restore snapshot |
| POST | `/import/csv` | Import from CSV |
| GET | `/settings` | Get plugin settings |
| POST | `/settings` | Update settings |

---

## 🏷️ Enums

### LinkStatus
```php
enum LinkStatus: string {
    case WORKING = 'WORKING';
    case BROKEN = 'BROKEN';
    case UNKNOWN = 'UNKNOWN';
    case TIMEOUT = 'TIMEOUT';
    case REDIRECT = 'REDIRECT';
}
```

### LinkWordCount
```php
enum LinkWordCount: string {
    case ONE_WORD = 'ONE_WORD';
    case TWO_WORDS = 'TWO_WORDS';
    case THREE_PLUS = 'THREE_PLUS';
}
```

### LinkWrapper
```php
enum LinkWrapper: string {
    case NONE = 'NONE';
    case H1 = 'H1';
    case H2 = 'H2';
    case H3 = 'H3';
    case H4 = 'H4';
    case H5 = 'H5';
    case H6 = 'H6';
    case STRONG = 'STRONG';
    case EM = 'EM';
    case B = 'B';
    case I = 'I';
}
```

### ContentType
```php
enum ContentType: string {
    case POST = 'POST';
    case PAGE = 'PAGE';
    case CATEGORY = 'CATEGORY';
}
```

### ScanMode
```php
enum ScanMode: string {
    case ALL_LINKS = 'ALL_LINKS';
    case BROKEN_ONLY = 'BROKEN_ONLY';
    case CSV_IMPORT = 'CSV_IMPORT';
}
```

### ScanStatus
```php
enum ScanStatus: string {
    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
```

### ModificationType
```php
enum ModificationType: string {
    case REMOVE_LINK_KEEP_TEXT = 'REMOVE_LINK_KEEP_TEXT';
    case REMOVE_HREF_ONLY = 'REMOVE_HREF_ONLY';
    case REMOVE_TITLE_ATTR = 'REMOVE_TITLE_ATTR';
    case CHANGE_URL = 'CHANGE_URL';
    case REMOVE_WRAPPER_TAG = 'REMOVE_WRAPPER_TAG';
    case ADD_TITLE_ATTR = 'ADD_TITLE_ATTR';
    case ADD_INTERNAL_LINK = 'ADD_INTERNAL_LINK';          // Internal linking
    case REMOVE_INTERNAL_LINK = 'REMOVE_INTERNAL_LINK';    // Internal link removal
}
```

### InternalLinkSource
```php
enum InternalLinkSource: string {
    case AUTO_TITLE_MATCH = 'AUTO_TITLE_MATCH';      // Matched from post/page title
    case AUTO_CATEGORY = 'AUTO_CATEGORY';             // Related by category
    case MANUAL_IMPORT = 'MANUAL_IMPORT';             // From CSV/JSON import
    case MANUAL_CREATE = 'MANUAL_CREATE';             // Created manually in UI
}
```

### VariableSelectionMode
```php
enum VariableSelectionMode: string {
    case SEQUENTIAL = 'SEQUENTIAL';     // Cycle through in order, loop at end
    case RANDOM = 'RANDOM';             // Random selection each time
    case WEIGHTED = 'WEIGHTED';         // Based on weight field
}
```

### LinkInsertionMode
```php
enum LinkInsertionMode: string {
    case FIRST_MATCH = 'FIRST_MATCH';   // Link first occurrence only
    case ALL_MATCHES = 'ALL_MATCHES';   // Link all occurrences
    case DISTRIBUTED = 'DISTRIBUTED';    // Spread evenly through content
}
```

### SnapshotType
```php
enum SnapshotType: string {
    case MANUAL = 'MANUAL';
    case AUTO_BEFORE_MODIFY = 'AUTO_BEFORE_MODIFY';
    case SCHEDULED = 'SCHEDULED';
}
```

### AutoLinkJobType
```php
enum AutoLinkJobType: string {
    case ORPHAN_CONTENT = 'ORPHAN_CONTENT';
    case CATEGORY_LINKING = 'CATEGORY_LINKING';
    case REPROCESS_ALL = 'REPROCESS_ALL';
}
```

### AutoLinkSchedule
```php
enum AutoLinkSchedule: string {
    case DAILY = 'DAILY';
    case WEEKLY = 'WEEKLY';
    case BIWEEKLY = 'BIWEEKLY';
    case MONTHLY = 'MONTHLY';
}
```

### LinkHealthStatus
```php
enum LinkHealthStatus: string {
    case HEALTHY = 'HEALTHY';
    case REDIRECT = 'REDIRECT';
    case BROKEN = 'BROKEN';
    case SLOW = 'SLOW';
    case UNKNOWN = 'UNKNOWN';
    case EXCLUDED = 'EXCLUDED';
}
```

### HealthAlertType
```php
enum HealthAlertType: string {
    case BROKEN_LINK = 'BROKEN_LINK';
    case REDIRECT_CHAIN = 'REDIRECT_CHAIN';
    case SLOW_RESPONSE = 'SLOW_RESPONSE';
    case SSL_ERROR = 'SSL_ERROR';
    case DNS_ERROR = 'DNS_ERROR';
    case TIMEOUT = 'TIMEOUT';
}
```

### HealthAlertSeverity
```php
enum HealthAlertSeverity: string {
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case CRITICAL = 'CRITICAL';
}
```

### HealthCheckPriority
```php
enum HealthCheckPriority: string {
    case HIGH = 'HIGH';
    case NORMAL = 'NORMAL';
    case LOW = 'LOW';
}
```

### NotificationChannel
```php
enum NotificationChannel: string {
    case EMAIL = 'EMAIL';
    case WEBHOOK = 'WEBHOOK';
    case ADMIN_NOTICE = 'ADMIN_NOTICE';
    case LOG = 'LOG';
}
```

### NotificationPriority
```php
enum NotificationPriority: string {
    case IMMEDIATE = 'IMMEDIATE';
    case HIGH = 'HIGH';
    case NORMAL = 'NORMAL';
    case LOW = 'LOW';
}
```

### NotificationType
```php
enum NotificationType: string {
    case BROKEN_LINK_DETECTED = 'BROKEN_LINK_DETECTED';
    case SLOW_LINK_DETECTED = 'SLOW_LINK_DETECTED';
    case SSL_EXPIRY_WARNING = 'SSL_EXPIRY_WARNING';
    case REDIRECT_CHAIN_WARNING = 'REDIRECT_CHAIN_WARNING';
    case HEALTH_SCAN_COMPLETE = 'HEALTH_SCAN_COMPLETE';
    case DAILY_HEALTH_DIGEST = 'DAILY_HEALTH_DIGEST';
    case WEEKLY_HEALTH_REPORT = 'WEEKLY_HEALTH_REPORT';
    case SCAN_COMPLETE = 'SCAN_COMPLETE';
    case SCAN_FAILED = 'SCAN_FAILED';
    case SNAPSHOT_CREATED = 'SNAPSHOT_CREATED';
    case IMPORT_COMPLETE = 'IMPORT_COMPLETE';
    case BROKEN_THRESHOLD_EXCEEDED = 'BROKEN_THRESHOLD_EXCEEDED';
    case SLOW_THRESHOLD_EXCEEDED = 'SLOW_THRESHOLD_EXCEEDED';
}
```

### NotificationStatus
```php
enum NotificationStatus: string {
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    case RETRYING = 'RETRYING';
    case CANCELLED = 'CANCELLED';
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

### YoastOptimizationType
```php
enum YoastOptimizationType: string {
    case FOCUS_KEYWORD = 'FOCUS_KEYWORD';
    case MULTIPLE_KEYWORDS = 'MULTIPLE_KEYWORDS';
    case META_DESCRIPTION = 'META_DESCRIPTION';
}
```

### YoastOptimizationStatus
```php
enum YoastOptimizationStatus: string {
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
```

### YoastTrimMode
```php
enum YoastTrimMode: string {
    case HARD_CUT = 'HARD_CUT';
    case REMOVE_LAST_WORD = 'REMOVE_LAST_WORD';
    case SENTENCE_BOUNDARY = 'SENTENCE_BOUNDARY';
    case SMART_TRIM = 'SMART_TRIM';
}
```

### YoastKeywordSource
```php
enum YoastKeywordSource: string {
    case TITLE = 'TITLE';
    case CONTENT = 'CONTENT';
    case HEADING = 'HEADING';
    case MANUAL = 'MANUAL';
}
```

---

## 📏 Validation Limits

### Scan Limits
```php
const MAX_CONCURRENT_REQUESTS = 10;      // parallel HTTP requests
const HTTP_TIMEOUT_SECONDS = 30;         // per-request timeout
const MAX_REDIRECTS = 5;                 // redirect follow limit
const SCAN_BATCH_SIZE = 50;              // items per scan batch
const CRON_BATCH_SIZE = 20;              // items per cron batch (smaller for timeouts)
```

### Internal Linking Limits
```php
const MIN_ANCHOR_WORDS = 2;              // Minimum words for anchor text
const MAX_ANCHOR_WORDS = 5;              // Maximum words for anchor text
const DEFAULT_LINKS_PER_CONTENT = 5;     // Default number of links to add
const MAX_LINKS_PER_CONTENT = 20;        // Maximum links allowed per content
const INTERNAL_LINK_BATCH_SIZE = 10;     // Items per batch for auto-linking

// Auto-Link Cron Limits
const AUTO_LINK_BATCH_SIZE = 10;         // Items per auto-link batch
const AUTO_LINK_CRON_INTERVAL = 120;     // Seconds between batches (2 min)
```

### Health Monitor Limits
```php
const HEALTH_CHECK_TIMEOUT = 10;           // Seconds per request
const HEALTH_CHECK_BATCH_SIZE = 25;        // Links per batch
const HEALTH_CHECK_CRON_INTERVAL = 3600;   // 1 hour default
const HEALTH_CHECK_MAX_REDIRECTS = 5;      // Max redirect chain depth
const HEALTH_CHECK_RETRY_ATTEMPTS = 2;     // Retry failed checks
const HEALTH_SLOW_THRESHOLD_MS = 2000;     // Slow response warning
const HEALTH_CRITICAL_THRESHOLD_MS = 5000; // Critical response time
const HEALTH_STALE_DAYS = 7;               // Days before recheck required
```

### Yoast SEO Limits
```php
const YOAST_MAX_FOCUS_KEYWORD_LENGTH = 60;   // Max focus keyword length
const YOAST_MAX_FOCUS_KEYWORD_WORDS = 5;     // Max words in focus keyword
const YOAST_MAX_MULTIPLE_KEYWORDS = 5;       // Max additional keywords (Premium)
const YOAST_MIN_KEYWORD_WORD_LENGTH = 3;     // Min word length for extraction
const YOAST_META_DESC_MAX_LENGTH = 140;      // Default max description length
const YOAST_META_DESC_MIN_LENGTH = 50;       // Min description length
const YOAST_BATCH_SIZE = 25;                 // Items per optimization batch
const YOAST_BATCH_DELAY_MS = 500;            // Delay between batches
```

### Input Limits
```php
const MAX_URL_LENGTH = 2048;
const MAX_ANCHOR_TEXT_LENGTH = 500;
const MAX_TITLE_ATTR_LENGTH = 255;
const MAX_SNAPSHOT_NAME_LENGTH = 100;
const MAX_CSV_ROWS = 10000;
```

### Pagination
```php
const DEFAULT_ITEMS_PER_PAGE = 20;
const MAX_ITEMS_PER_PAGE = 100;
const PAGINATION_OPTIONS = [20, 30, 50, 100];
```

---

## 🎨 Link Source Types

```php
const SOURCE_POST_CONTENT = 'POST_CONTENT';
const SOURCE_PAGE_CONTENT = 'PAGE_CONTENT';
const SOURCE_JSON_LD = 'JSON_LD';
const SOURCE_SCHEMA_MARKUP = 'SCHEMA_MARKUP';
const SOURCE_ELEMENTOR = 'ELEMENTOR';
```

---

## 🗂️ Data Folder Structure

```
wp-content/uploads/link-manager/
├── link-manager.db                      # Main SQLite database
├── logs/
│   ├── plugin.log                       # General events
│   └── error.log                        # Errors with stack traces
├── history-manage/
│   ├── posts/
│   │   ├── 123-my-first-post.db         # History for post ID 123
│   │   └── 456-another-post.db
│   ├── pages/
│   │   ├── 10-about-us.db
│   │   └── 20-contact.db
│   └── categories/
│       └── 5-uncategorized.db
├── snapshots/
│   ├── 001-initial-backup-2026-01-31.db
│   ├── 002-before-bulk-remove-2026-01-31.db
│   └── snapshot-registry.json
├── imports/
│   └── (temporary CSV files during import)
└── exports/
    └── (generated export files)
```

---

## 🔢 Error Codes

### 14000-14099: General
| Code | Constant | Description |
|------|----------|-------------|
| 14000 | ERR_GENERAL | Unspecified error |
| 14001 | ERR_INIT_FAILED | Plugin initialization failed |
| 14002 | ERR_DB_CONNECTION | Database connection failed |
| 14003 | ERR_PERMISSION_DENIED | Insufficient permissions |

### 14100-14199: Scanning
| Code | Constant | Description |
|------|----------|-------------|
| 14100 | ERR_SCAN_ALREADY_RUNNING | Scan already in progress |
| 14101 | ERR_SCAN_NO_CONTENT | No content to scan |
| 14102 | ERR_SCAN_TIMEOUT | Scan operation timed out |
| 14103 | ERR_SCAN_HTTP_FAILED | HTTP request failed |

### 14200-14299: Parsing
| Code | Constant | Description |
|------|----------|-------------|
| 14200 | ERR_PARSE_INVALID_HTML | Invalid HTML structure |
| 14201 | ERR_PARSE_JSON_LD | JSON-LD parsing failed |
| 14202 | ERR_PARSE_ELEMENTOR | Elementor content parsing failed |
| 14203 | ERR_PARSE_BROKEN_HTML | Broken HTML detected |
| 14204 | ERR_PARSE_ENCODING | Content encoding detection failed |
| 14205 | ERR_PARSE_DEPTH_EXCEEDED | Maximum nesting depth exceeded |

### 14300-14399: Modification
| Code | Constant | Description |
|------|----------|-------------|
| 14300 | ERR_MODIFY_NOT_FOUND | Link not found |
| 14301 | ERR_MODIFY_CONTENT_CHANGED | Content changed since scan |
| 14302 | ERR_MODIFY_SAVE_FAILED | Failed to save modification |
| 14303 | ERR_MODIFY_ELEMENTOR | Elementor update failed |

### 14400-14499: History/Rollback
| Code | Constant | Description |
|------|----------|-------------|
| 14400 | ERR_HISTORY_NOT_FOUND | History record not found |
| 14401 | ERR_HISTORY_DB_CORRUPT | History database corrupted |
| 14402 | ERR_ROLLBACK_FAILED | Rollback operation failed |
| 14403 | ERR_ROLLBACK_VERSION_MISSING | Target version not found |

### 14500-14599: Snapshot
| Code | Constant | Description |
|------|----------|-------------|
| 14500 | ERR_SNAPSHOT_CREATE_FAILED | Snapshot creation failed |
| 14501 | ERR_SNAPSHOT_NOT_FOUND | Snapshot not found |
| 14502 | ERR_SNAPSHOT_RESTORE_FAILED | Snapshot restoration failed |
| 14503 | ERR_SNAPSHOT_DISK_FULL | Insufficient disk space |

### 14600-14699: CSV Import
| Code | Constant | Description |
|------|----------|-------------|
| 14600 | ERR_CSV_INVALID_FORMAT | Invalid CSV format |
| 14601 | ERR_CSV_MISSING_COLUMNS | Required columns missing |
| 14602 | ERR_CSV_TOO_LARGE | CSV file exceeds limit |
| 14603 | ERR_CSV_PARSE_ROW | Row parsing failed |

### 14700-14799: Cron
| Code | Constant | Description |
|------|----------|-------------|
| 14700 | ERR_CRON_REGISTER_FAILED | Cron job registration failed |
| 14701 | ERR_CRON_ALREADY_RUNNING | Job already running |
| 14702 | ERR_CRON_LOCK_FAILED | Failed to acquire lock |
| 14703 | ERR_CRON_AUTO_LINK_FAILED | Auto-linking batch processing failed |
| 14704 | ERR_CRON_SCHEDULE_INVALID | Invalid schedule configuration |

### 14800-14849: API
| Code | Constant | Description |
|------|----------|-------------|
| 14800 | ERR_API_INVALID_REQUEST | Invalid API request |
| 14801 | ERR_API_RATE_LIMITED | Rate limit exceeded |
| 14802 | ERR_API_AUTH_FAILED | Authentication failed |

### 14850-14859: Health Monitor
| Code | Constant | Description |
|------|----------|-------------|
| 14850 | ERR_HEALTH_CHECK_FAILED | HTTP request failed |
| 14851 | ERR_HEALTH_TIMEOUT | Request timed out |
| 14852 | ERR_HEALTH_SSL_ERROR | SSL certificate error |
| 14853 | ERR_HEALTH_DNS_ERROR | DNS resolution failed |
| 14854 | ERR_HEALTH_TOO_MANY_REDIRECTS | Exceeded redirect limit |
| 14855 | ERR_HEALTH_INVALID_URL | Malformed URL |
| 14856 | ERR_HEALTH_EXCLUSION_EXISTS | Exclusion pattern exists |
| 14857 | ERR_HEALTH_JOB_NOT_FOUND | Health check job not found |
| 14858 | ERR_HEALTH_ALERT_NOT_FOUND | Alert not found |
| 14859 | ERR_HEALTH_SCAN_IN_PROGRESS | Another scan already running |

### 14860-14879: Notifications
| Code | Constant | Description |
|------|----------|-------------|
| 14860 | ERR_NOTIFICATION_QUEUE_FAILED | Failed to queue notification |
| 14861 | ERR_NOTIFICATION_DELIVERY_FAILED | Delivery failed |
| 14862 | ERR_NOTIFICATION_TEMPLATE_NOT_FOUND | Email template not found |
| 14863 | ERR_NOTIFICATION_INVALID_RECIPIENT | Invalid recipient email |
| 14864 | ERR_NOTIFICATION_RATE_LIMITED | Rate limit exceeded |
| 14865 | ERR_WEBHOOK_TIMEOUT | Webhook request timed out |
| 14866 | ERR_WEBHOOK_INVALID_URL | Invalid webhook URL |
| 14867 | ERR_WEBHOOK_AUTH_FAILED | Webhook authentication failed |
| 14868 | ERR_WEBHOOK_PAYLOAD_TOO_LARGE | Payload exceeds size limit |
| 14869 | ERR_WEBHOOK_ENDPOINT_NOT_FOUND | Webhook endpoint not found |
| 14870 | ERR_DIGEST_BUILD_FAILED | Failed to build digest |
| 14871 | ERR_RECIPIENT_NOT_FOUND | Recipient not found |
| 14872 | ERR_RECIPIENT_DUPLICATE | Duplicate recipient email |
| 14873 | ERR_NOTIFICATION_CANCELLED | Notification was cancelled |
| 14874 | ERR_EMAIL_SEND_FAILED | wp_mail() failed |

### 14900-14949: Internal Linking
| Code | Constant | Description |
|------|----------|-------------|
| 14900 | ERR_INTERNAL_LINK_GENERAL | Unspecified internal linking error |
| 14901 | ERR_TEMPLATE_INVALID | Template missing required placeholders |
| 14902 | ERR_TEMPLATE_NOT_FOUND | Template not found |
| 14903 | ERR_VARIABLE_NOT_FOUND | Variable not found |
| 14904 | ERR_VARIABLE_NO_VALUES | Variable has no values |
| 14905 | ERR_TARGET_NOT_FOUND | Link target not found |
| 14906 | ERR_TARGET_DUPLICATE | Duplicate target URL |
| 14907 | ERR_NO_MATCHING_PHRASE | No matching phrase found in content |
| 14908 | ERR_CONTENT_NOT_FOUND | Content not found |
| 14909 | ERR_JSON_INVALID_FORMAT | Invalid JSON format |
| 14910 | ERR_IMPORT_FAILED | Import operation failed |
| 14911 | ERR_MAX_LINKS_EXCEEDED | Maximum links per content exceeded |

### 14950-14999: Yoast SEO Integration
| Code | Constant | Description |
|------|----------|-------------|
| 14950 | ERR_YOAST_NOT_INSTALLED | Yoast SEO plugin not active |
| 14951 | ERR_YOAST_PREMIUM_REQUIRED | Feature requires Yoast Premium |
| 14952 | ERR_YOAST_INVALID_SETTING | Invalid setting key or value |
| 14953 | ERR_YOAST_CONTENT_NOT_FOUND | Post/page not found |
| 14954 | ERR_YOAST_OPTIMIZATION_FAILED | Optimization operation failed |
| 14955 | ERR_YOAST_QUEUE_ERROR | Queue processing error |
| 14956 | ERR_YOAST_REVERT_FAILED | Failed to revert change |
| 14957 | ERR_YOAST_INVALID_TRIM_MODE | Unknown trim mode specified |
| 14958 | ERR_YOAST_BATCH_LIMIT_EXCEEDED | Batch size exceeds maximum |
| 14959 | ERR_YOAST_CONFIG_LOAD_FAILED | Failed to load config JSON |

---

## 📋 Logging Requirements

> **CRITICAL:** All log entries MUST follow these patterns

### Info Logs
```php
Logger::info('Message describing action', [
    'function' => __FUNCTION__,    // Function name (REQUIRED)
    'file' => __FILE__,            // File path (REQUIRED)
    // Additional context...
]);
```

### Error Logs
```php
Logger::error('Error description', [
    'function' => __FUNCTION__,    // Function name (REQUIRED)
    'file' => __FILE__,            // File path (REQUIRED)
    'error' => $e->getMessage(),   // Error message (REQUIRED)
    'stack_trace' => $e->getTraceAsString(),  // Full stack trace (REQUIRED)
    // Additional context...
]);
```

### Logger Implementation

**File:** `src/Utils/Logger.php`

```php
<?php
namespace LinkManager\Utils;

class Logger
{
    private const LOG_PATH_INFO = LM_LOG_PATH . 'plugin.log';
    private const LOG_PATH_ERROR = LM_LOG_PATH . 'error.log';
    private const MAX_LOG_SIZE = 10 * 1024 * 1024;  // 10MB
    
    /**
     * Log info message with function and file context
     */
    public static function info(string $message, array $context = []): void
    {
        // Validate required fields
        if (!isset($context['function']) || !isset($context['file'])) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $context['function'] = $context['function'] ?? $trace[1]['function'] ?? 'unknown';
            $context['file'] = $context['file'] ?? $trace[0]['file'] ?? 'unknown';
        }
        
        $entry = self::formatLogEntry('INFO', $message, $context);
        self::writeLog(self::LOG_PATH_INFO, $entry);
    }
    
    /**
     * Log error with full stack trace
     */
    public static function error(string $message, array $context = []): void
    {
        // Validate required fields
        if (!isset($context['function']) || !isset($context['file'])) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $context['function'] = $context['function'] ?? $trace[1]['function'] ?? 'unknown';
            $context['file'] = $context['file'] ?? $trace[0]['file'] ?? 'unknown';
        }
        
        // Ensure stack trace is present for errors
        if (!isset($context['stack_trace'])) {
            $context['stack_trace'] = self::captureStackTrace();
        }
        
        $entry = self::formatLogEntry('ERROR', $message, $context);
        self::writeLog(self::LOG_PATH_ERROR, $entry);
    }
    
    /**
     * Log warning
     */
    public static function warning(string $message, array $context = []): void
    {
        if (!isset($context['function']) || !isset($context['file'])) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $context['function'] = $context['function'] ?? $trace[1]['function'] ?? 'unknown';
            $context['file'] = $context['file'] ?? $trace[0]['file'] ?? 'unknown';
        }
        
        $entry = self::formatLogEntry('WARN', $message, $context);
        self::writeLog(self::LOG_PATH_INFO, $entry);
    }
    
    /**
     * Format log entry
     */
    private static function formatLogEntry(string $level, string $message, array $context): string
    {
        $timestamp = gmdate('Y-m-d H:i:s');
        $function = $context['function'] ?? 'unknown';
        $file = basename($context['file'] ?? 'unknown');
        
        unset($context['function'], $context['file']);
        
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        
        return "[{$timestamp}] [{$level}] [{$file}::{$function}] {$message}{$contextStr}" . PHP_EOL;
    }
    
    /**
     * Capture full stack trace (40 frames)
     */
    private static function captureStackTrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40);
        
        $lines = [];
        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';
            
            $lines[] = "#{$i} {$file}({$line}): {$class}{$type}{$function}()";
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Write to log file with rotation
     */
    private static function writeLog(string $path, string $entry): void
    {
        // Create log directory if needed
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Rotate if needed
        if (file_exists($path) && filesize($path) > self::MAX_LOG_SIZE) {
            $rotated = $path . '.' . date('Y-m-d-His');
            rename($path, $rotated);
        }
        
        file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }
}
```

---

## 🔧 Settings Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `auto_snapshot` | boolean | `false` | Auto-snapshot before modifications |
| `items_per_page` | integer | `20` | Default pagination size |
| `http_timeout` | integer | `30` | HTTP request timeout (seconds) |
| `max_concurrent` | integer | `10` | Max parallel HTTP requests |
| `scan_json_ld` | boolean | `true` | Include JSON-LD in scans |
| `scan_schema` | boolean | `true` | Include schema markup in scans |
| `log_retention_days` | integer | `30` | Days to retain logs |
| `notify_on_scan_complete` | boolean | `true` | Email notification on scan complete |

---

## 📝 Cross-References

- Plugin structure: `01-admin-backend/split-spec/03-plugin-structure.md`
- Database schema: `01-admin-backend/split-spec/04-database-schema.md`
- REST API: `01-admin-backend/split-spec/17-rest-api-endpoints.md`
- Cron system: `01-admin-backend/split-spec/16-cron-system.md`

---

*This file is the Single Source of Truth for all shared values. Update this file when adding new constants.*
