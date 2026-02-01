# 32. WordPress Cron System

> **Version**: 1.0.0  
> **Last Updated**: 2025-01-26  
> **Status**: PRODUCTION-READY  
> **Applies To**: WordPress Plugin Development

---

## 32.1 Overview

This document establishes standardized patterns for WordPress Cron (WP-Cron) implementation, including job registration, scheduling, error handling, and background task management.

**Important**: WP-Cron is triggered by page visits, not by system time. For time-critical tasks, consider external cron triggers.

---

## 32.2 Hook Naming Convention

### Standard Format

```
{plugin_slug}_{frequency}_{action}
```

### Examples

| Hook Name | Description |
|-----------|-------------|
| `plugin_slug_daily_cleanup` | Daily database cleanup |
| `plugin_slug_hourly_sync` | Hourly data synchronization |
| `plugin_slug_weekly_report` | Weekly report generation |
| `plugin_slug_twice_daily_check` | Twice-daily status check |

### Naming Rules

- ✅ Use plugin slug prefix to avoid conflicts
- ✅ Include frequency indicator (daily, hourly, etc.)
- ✅ End with action description
- ✅ Use snake_case throughout
- ❌ Never use generic names like `my_cron` or `do_task`

---

## 32.3 Cron Job Registration

### Registration on Activation

```php
<?php
namespace PluginNamespace\Core;

use PluginNamespace\Utils\Logger;

class CronManager
{
    /**
     * All plugin cron jobs with their configurations
     */
    private const CRON_JOBS = [
        'plugin_slug_daily_cleanup' => [
            'interval' => 'daily',
            'callback' => [\PluginNamespace\Cron\CleanupJob::class, 'run'],
            'description' => 'Clean up expired data and logs'
        ],
        'plugin_slug_hourly_deadline_check' => [
            'interval' => 'hourly',
            'callback' => [\PluginNamespace\Cron\DeadlineJob::class, 'run'],
            'description' => 'Check and process deadline notifications'
        ],
        'plugin_slug_twice_daily_email_queue' => [
            'interval' => 'twicedaily',
            'callback' => [\PluginNamespace\Cron\EmailQueueJob::class, 'run'],
            'description' => 'Process pending email queue'
        ],
        'plugin_slug_weekly_report' => [
            'interval' => 'weekly',
            'callback' => [\PluginNamespace\Cron\ReportJob::class, 'run'],
            'description' => 'Generate weekly summary reports'
        ],
        'plugin_slug_daily_status_update' => [
            'interval' => 'daily',
            'callback' => [\PluginNamespace\Cron\StatusUpdateJob::class, 'run'],
            'description' => 'Update participant statuses'
        ],
        'plugin_slug_daily_analytics' => [
            'interval' => 'daily',
            'callback' => [\PluginNamespace\Cron\AnalyticsJob::class, 'run'],
            'description' => 'Aggregate analytics data'
        ]
    ];
    
    /**
     * Register all cron jobs on plugin activation
     */
    public static function registerAll(): void
    {
        foreach (self::CRON_JOBS as $hook => $config) {
            self::scheduleJob($hook, $config['interval']);
        }
        
        Logger::info('Cron jobs registered', [
            'count' => count(self::CRON_JOBS)
        ]);
    }
    
    /**
     * Unregister all cron jobs on plugin deactivation
     */
    public static function unregisterAll(): void
    {
        foreach (self::CRON_JOBS as $hook => $config) {
            self::unscheduleJob($hook);
        }
        
        Logger::info('Cron jobs unregistered', [
            'count' => count(self::CRON_JOBS)
        ]);
    }
    
    /**
     * Schedule a single cron job
     */
    private static function scheduleJob(string $hook, string $interval): void
    {
        $existingTimestamp = wp_next_scheduled($hook);
        $isAlreadyScheduled = ($existingTimestamp !== false);
        
        if ($isAlreadyScheduled) {
            Logger::debug('Cron job already scheduled', [
                'hook' => $hook,
                'next_run' => gmdate('c', $existingTimestamp)
            ]);
            return;
        }
        
        $scheduled = wp_schedule_event(time(), $interval, $hook);
        $wasSuccessful = ($scheduled !== false);
        
        if ($wasSuccessful) {
            Logger::info('Cron job scheduled', [
                'hook' => $hook,
                'interval' => $interval
            ]);
        } else {
            Logger::error('Failed to schedule cron job', [
                'hook' => $hook,
                'interval' => $interval
            ]);
        }
    }
    
    /**
     * Unschedule a single cron job
     */
    private static function unscheduleJob(string $hook): void
    {
        $timestamp = wp_next_scheduled($hook);
        $isScheduled = ($timestamp !== false);
        
        if ($isScheduled) {
            wp_clear_scheduled_hook($hook);
            Logger::info('Cron job unscheduled', ['hook' => $hook]);
        }
    }
    
    /**
     * Register cron callbacks on init
     */
    public static function registerCallbacks(): void
    {
        foreach (self::CRON_JOBS as $hook => $config) {
            add_action($hook, $config['callback']);
        }
    }
    
    /**
     * Get status of all cron jobs
     */
    public static function getStatus(): array
    {
        $status = [];
        
        foreach (self::CRON_JOBS as $hook => $config) {
            $nextRun = wp_next_scheduled($hook);
            $isScheduled = ($nextRun !== false);
            
            $status[$hook] = [
                'description' => $config['description'],
                'interval' => $config['interval'],
                'is_scheduled' => $isScheduled,
                'next_run' => $isScheduled ? gmdate('c', $nextRun) : null,
                'next_run_human' => $isScheduled ? human_time_diff($nextRun) : null
            ];
        }
        
        return $status;
    }
}
```

### Integration with Plugin Lifecycle

```php
<?php
// In Activator.php
public static function activate(): void
{
    // ... other activation logic
    
    // Register cron jobs
    CronManager::registerAll();
}

// In Deactivator.php
public static function deactivate(): void
{
    // Unregister cron jobs
    CronManager::unregisterAll();
    
    // ... other deactivation logic
}

// In Plugin.php init()
public function init(): void
{
    // Register cron callbacks
    CronManager::registerCallbacks();
    
    // ... other initialization
}
```

---

## 32.4 Custom Cron Intervals

### Registering Custom Intervals

```php
<?php
namespace PluginNamespace\Core;

class CronIntervals
{
    /**
     * Custom intervals for plugin cron jobs
     */
    private const CUSTOM_INTERVALS = [
        'every_five_minutes' => [
            'interval' => 300,        // 5 * 60 seconds
            'display' => 'Every 5 Minutes'
        ],
        'every_fifteen_minutes' => [
            'interval' => 900,        // 15 * 60 seconds
            'display' => 'Every 15 Minutes'
        ],
        'every_thirty_minutes' => [
            'interval' => 1800,       // 30 * 60 seconds
            'display' => 'Every 30 Minutes'
        ],
        'weekly' => [
            'interval' => 604800,     // 7 * 24 * 60 * 60 seconds
            'display' => 'Once Weekly'
        ],
        'monthly' => [
            'interval' => 2592000,    // 30 * 24 * 60 * 60 seconds
            'display' => 'Once Monthly'
        ]
    ];
    
    /**
     * Register custom intervals
     */
    public static function register(): void
    {
        add_filter('cron_schedules', [self::class, 'addIntervals']);
    }
    
    /**
     * Add custom intervals to WordPress
     */
    public static function addIntervals(array $schedules): array
    {
        foreach (self::CUSTOM_INTERVALS as $name => $config) {
            $alreadyExists = isset($schedules[$name]);
            
            if (!$alreadyExists) {
                $schedules[$name] = $config;
            }
        }
        
        return $schedules;
    }
    
    /**
     * Get all available intervals (built-in + custom)
     */
    public static function getAvailable(): array
    {
        return wp_get_schedules();
    }
}
```

### Built-in WordPress Intervals

| Name | Interval | Description |
|------|----------|-------------|
| `hourly` | 3600s | Once per hour |
| `twicedaily` | 43200s | Twice per day |
| `daily` | 86400s | Once per day |

---

## 32.5 Cron Job Implementation

### Base Job Class

```php
<?php
namespace PluginNamespace\Cron;

use PluginNamespace\Utils\Logger;

abstract class BaseCronJob
{
    /**
     * Job name for logging
     */
    protected static string $jobName = 'base_job';
    
    /**
     * Maximum execution time in seconds
     */
    protected static int $maxExecutionTime = 300; // 5 minutes
    
    /**
     * Run the cron job with error handling
     */
    public static function run(): void
    {
        $startTime = microtime(true);
        
        Logger::info('Cron job started', [
            'job' => static::$jobName,
            'memory_start' => memory_get_usage(true)
        ]);
        
        try {
            // Set execution time limit
            set_time_limit(static::$maxExecutionTime);
            
            // Execute job logic
            static::execute();
            
            $duration = round(microtime(true) - $startTime, 2);
            
            Logger::info('Cron job completed', [
                'job' => static::$jobName,
                'duration_seconds' => $duration,
                'memory_peak' => memory_get_peak_usage(true)
            ]);
            
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            
            Logger::error('Cron job failed', [
                'job' => static::$jobName,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'duration_seconds' => $duration
            ]);
            
            // Optionally notify admin
            static::notifyAdminOnFailure($e);
        }
    }
    
    /**
     * Execute the job logic - implemented by child classes
     */
    abstract protected static function execute(): void;
    
    /**
     * Notify admin on job failure
     */
    protected static function notifyAdminOnFailure(\Throwable $e): void
    {
        $shouldNotify = get_option('plugin_slug_notify_cron_failures', true);
        
        if (!$shouldNotify) {
            return;
        }
        
        $adminEmail = get_option('admin_email');
        $subject = sprintf('[%s] Cron Job Failed: %s', get_bloginfo('name'), static::$jobName);
        $message = sprintf(
            "The cron job '%s' failed with the following error:\n\n%s\n\nStack Trace:\n%s",
            static::$jobName,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        
        wp_mail($adminEmail, $subject, $message);
    }
}
```

### Example: Cleanup Job

```php
<?php
namespace PluginNamespace\Cron;

use PluginNamespace\Utils\Logger;
use PluginNamespace\Database\Models\AuditLog;
use PluginNamespace\Database\Models\RateLimit;

class CleanupJob extends BaseCronJob
{
    protected static string $jobName = 'daily_cleanup';
    
    /**
     * Execute cleanup tasks
     */
    protected static function execute(): void
    {
        $results = [
            'audit_logs' => self::cleanupAuditLogs(),
            'rate_limits' => self::cleanupRateLimits(),
            'transients' => self::cleanupExpiredTransients(),
            'log_files' => self::rotateLogFiles()
        ];
        
        Logger::info('Cleanup results', $results);
    }
    
    /**
     * Clean up old audit logs
     */
    private static function cleanupAuditLogs(): int
    {
        try {
            $retentionDays = get_option('plugin_slug_audit_retention_days', 90);
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            
            $deleted = AuditLog::deleteWhere('CreatedAt', '<', $cutoffDate);
            
            Logger::debug('Audit logs cleaned', [
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoffDate
            ]);
            
            return $deleted;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to cleanup audit logs', [
                'file' => __FILE__,
                'action' => 'cleanupAuditLogs',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
    
    /**
     * Clean up expired rate limit records
     */
    private static function cleanupRateLimits(): int
    {
        try {
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime('-1 day'));
            
            $deleted = RateLimit::deleteWhere('ExpiresAt', '<', $cutoffDate);
            
            return $deleted;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to cleanup rate limits', [
                'file' => __FILE__,
                'action' => 'cleanupRateLimits',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
    
    /**
     * Clean up expired transients
     */
    private static function cleanupExpiredTransients(): int
    {
        global $wpdb;
        
        try {
            $result = $wpdb->query(
                "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
                WHERE a.option_name LIKE '_transient_plugin_slug_%'
                AND a.option_name NOT LIKE '_transient_timeout_%'
                AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
                AND b.option_value < UNIX_TIMESTAMP()"
            );
            
            return $result !== false ? $result : 0;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to cleanup transients', [
                'file' => __FILE__,
                'action' => 'cleanupExpiredTransients',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
    
    /**
     * Rotate log files
     */
    private static function rotateLogFiles(): bool
    {
        try {
            return Logger::rotateIfNeeded();
        } catch (\Throwable $e) {
            Logger::error('Failed to rotate logs', [
                'file' => __FILE__,
                'action' => 'rotateLogFiles',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
```

### Example: Email Queue Job

```php
<?php
namespace PluginNamespace\Cron;

use PluginNamespace\Utils\Logger;
use PluginNamespace\Services\EmailQueueService;

class EmailQueueJob extends BaseCronJob
{
    protected static string $jobName = 'email_queue_processor';
    protected static int $maxExecutionTime = 600; // 10 minutes
    
    /**
     * Batch size per run
     */
    private const BATCH_SIZE = 50;
    
    /**
     * Maximum retry attempts
     */
    private const MAX_RETRIES = 3;
    
    /**
     * Execute email queue processing
     */
    protected static function execute(): void
    {
        $processed = 0;
        $failed = 0;
        $skipped = 0;
        
        $emails = EmailQueueService::getPending(self::BATCH_SIZE);
        $hasEmails = !empty($emails);
        
        if (!$hasEmails) {
            Logger::debug('No pending emails in queue');
            return;
        }
        
        foreach ($emails as $email) {
            $result = self::processEmail($email);
            
            match ($result) {
                'sent' => $processed++,
                'failed' => $failed++,
                'skipped' => $skipped++
            };
        }
        
        Logger::info('Email queue processed', [
            'processed' => $processed,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => count($emails)
        ]);
    }
    
    /**
     * Process a single email
     */
    private static function processEmail(object $email): string
    {
        try {
            // Check retry limit
            $hasExceededRetries = ($email->RetryCount >= self::MAX_RETRIES);
            
            if ($hasExceededRetries) {
                EmailQueueService::markFailed($email->Id, 'Max retries exceeded');
                return 'skipped';
            }
            
            // Attempt to send
            $sent = wp_mail(
                $email->ToEmail,
                $email->Subject,
                $email->Body,
                $email->Headers
            );
            
            if ($sent) {
                EmailQueueService::markSent($email->Id);
                return 'sent';
            }
            
            // Increment retry count
            EmailQueueService::incrementRetry($email->Id);
            return 'failed';
            
        } catch (\Throwable $e) {
            Logger::error('Email send failed', [
                'email_id' => $email->Id,
                'to' => $email->ToEmail,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            EmailQueueService::incrementRetry($email->Id, $e->getMessage());
            return 'failed';
        }
    }
}
```

---

## 32.6 One-Time Scheduled Events

### Scheduling Single Events

```php
<?php
namespace PluginNamespace\Cron;

use PluginNamespace\Utils\Logger;

class ScheduledEvents
{
    /**
     * Schedule a one-time event
     */
    public static function scheduleOnce(
        string $hook,
        int $timestamp,
        array $args = []
    ): bool {
        // Validate timestamp is in the future
        $isInFuture = ($timestamp > time());
        
        if (!$isInFuture) {
            Logger::warning('Cannot schedule event in the past', [
                'hook' => $hook,
                'timestamp' => $timestamp
            ]);
            return false;
        }
        
        // Check if already scheduled with same args
        $existing = wp_next_scheduled($hook, $args);
        $isAlreadyScheduled = ($existing !== false);
        
        if ($isAlreadyScheduled) {
            Logger::debug('Event already scheduled', [
                'hook' => $hook,
                'existing_time' => gmdate('c', $existing)
            ]);
            return true;
        }
        
        $result = wp_schedule_single_event($timestamp, $hook, $args);
        $wasSuccessful = ($result !== false);
        
        if ($wasSuccessful) {
            Logger::info('One-time event scheduled', [
                'hook' => $hook,
                'scheduled_for' => gmdate('c', $timestamp),
                'args' => $args
            ]);
        }
        
        return $wasSuccessful;
    }
    
    /**
     * Schedule deadline notification
     */
    public static function scheduleDeadlineNotification(
        int $participantId,
        string $deadlineType,
        int $notifyAt
    ): bool {
        $hook = 'plugin_slug_deadline_notification';
        $args = [$participantId, $deadlineType];
        
        return self::scheduleOnce($hook, $notifyAt, $args);
    }
    
    /**
     * Schedule exam completion follow-up
     */
    public static function scheduleCompletionFollowUp(
        int $participantId,
        int $delayHours = 24
    ): bool {
        $hook = 'plugin_slug_completion_followup';
        $timestamp = time() + ($delayHours * 3600);
        
        return self::scheduleOnce($hook, $timestamp, [$participantId]);
    }
    
    /**
     * Cancel a scheduled one-time event
     */
    public static function cancelScheduled(string $hook, array $args = []): bool
    {
        $timestamp = wp_next_scheduled($hook, $args);
        $isScheduled = ($timestamp !== false);
        
        if (!$isScheduled) {
            return false;
        }
        
        $result = wp_unschedule_event($timestamp, $hook, $args);
        
        Logger::info('Scheduled event cancelled', [
            'hook' => $hook,
            'was_scheduled_for' => gmdate('c', $timestamp)
        ]);
        
        return $result !== false;
    }
}
```

---

## 32.7 Cron Lock Pattern (Preventing Overlap)

### Lock Implementation

```php
<?php
namespace PluginNamespace\Cron;

use PluginNamespace\Utils\Logger;

trait CronLock
{
    /**
     * Acquire lock for job execution
     */
    protected static function acquireLock(string $jobName, int $ttlSeconds = 300): bool
    {
        $lockKey = "cron_lock_{$jobName}";
        $existingLock = get_transient($lockKey);
        $isLocked = ($existingLock !== false);
        
        if ($isLocked) {
            Logger::warning('Cron job already running, skipping', [
                'job' => $jobName,
                'locked_since' => $existingLock
            ]);
            return false;
        }
        
        // Set lock with TTL
        $lockValue = gmdate('c');
        set_transient($lockKey, $lockValue, $ttlSeconds);
        
        Logger::debug('Cron lock acquired', [
            'job' => $jobName,
            'ttl_seconds' => $ttlSeconds
        ]);
        
        return true;
    }
    
    /**
     * Release lock after job completion
     */
    protected static function releaseLock(string $jobName): void
    {
        $lockKey = "cron_lock_{$jobName}";
        delete_transient($lockKey);
        
        Logger::debug('Cron lock released', ['job' => $jobName]);
    }
    
    /**
     * Execute with lock protection
     */
    protected static function executeWithLock(callable $callback): void
    {
        $lockAcquired = self::acquireLock(static::$jobName);
        
        if (!$lockAcquired) {
            return;
        }
        
        try {
            $callback();
        } finally {
            self::releaseLock(static::$jobName);
        }
    }
}
```

### Usage in Job Class

```php
<?php
class LongRunningJob extends BaseCronJob
{
    use CronLock;
    
    protected static string $jobName = 'long_running_job';
    
    protected static function execute(): void
    {
        self::executeWithLock(function () {
            // Long-running task that should not overlap
            self::processLargeDataset();
        });
    }
}
```

---

## 32.8 External Cron Trigger

### Disable WP-Cron and Use System Cron

```php
// In wp-config.php
define('DISABLE_WP_CRON', true);
```

### System Crontab Entry

```bash
# Run WordPress cron every minute
* * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1

# Or using curl
* * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1

# Or using WP-CLI
* * * * * cd /path/to/wordpress && wp cron event run --due-now >/dev/null 2>&1
```

### Custom Cron Trigger Endpoint

```php
<?php
namespace PluginNamespace\API;

class CronTriggerEndpoint extends RestController
{
    protected string $restBase = 'cron';
    
    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/' . $this->restBase . '/trigger', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'triggerCron'],
            'permission_callback' => [$this, 'verifyCronSecret']
        ]);
    }
    
    /**
     * Verify cron secret token
     */
    public function verifyCronSecret(WP_REST_Request $request): bool
    {
        $providedSecret = $request->get_header('X-Cron-Secret');
        $storedSecret = get_option('plugin_slug_cron_secret');
        
        $hasSecret = !empty($providedSecret) && !empty($storedSecret);
        
        if (!$hasSecret) {
            return false;
        }
        
        return hash_equals($storedSecret, $providedSecret);
    }
    
    /**
     * Trigger specific cron job
     */
    public function triggerCron(WP_REST_Request $request): WP_REST_Response
    {
        $jobName = $request->get_param('job');
        $allowedJobs = array_keys(CronManager::CRON_JOBS);
        $isValidJob = in_array($jobName, $allowedJobs, true);
        
        if (!$isValidJob) {
            return $this->error('ERR_4001', 'Invalid job name', 404);
        }
        
        try {
            do_action($jobName);
            return $this->success(['job' => $jobName, 'status' => 'triggered']);
        } catch (\Throwable $e) {
            Logger::error('Manual cron trigger failed', [
                'job' => $jobName,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return $this->error('ERR_9001', 'Job execution failed', 500);
        }
    }
}
```

---

## 32.9 Admin UI for Cron Status

### Cron Status Dashboard Widget

```php
<?php
namespace PluginNamespace\Admin;

use PluginNamespace\Core\CronManager;

class CronStatusWidget
{
    /**
     * Render cron status table
     */
    public static function render(): void
    {
        $cronStatus = CronManager::getStatus();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Interval</th>
                    <th>Status</th>
                    <th>Next Run</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cronStatus as $hook => $status): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($status['description']); ?></strong>
                        <br><code><?php echo esc_html($hook); ?></code>
                    </td>
                    <td><?php echo esc_html($status['interval']); ?></td>
                    <td>
                        <?php if ($status['is_scheduled']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                            Scheduled
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color: orange;"></span>
                            Not Scheduled
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($status['next_run']): ?>
                            <?php echo esc_html($status['next_run_human']); ?>
                            <br><small><?php echo esc_html($status['next_run']); ?></small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <button 
                            type="button" 
                            class="button button-small run-cron-job"
                            data-hook="<?php echo esc_attr($hook); ?>"
                        >
                            Run Now
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
```

---

## 32.10 Checklist

### Registration
- [ ] All cron jobs defined in centralized `CRON_JOBS` constant
- [ ] Jobs registered on plugin activation
- [ ] Jobs unregistered on plugin deactivation
- [ ] Callbacks registered on `init` hook
- [ ] Hook names follow `{plugin_slug}_{frequency}_{action}` pattern

### Implementation
- [ ] All jobs extend `BaseCronJob` class
- [ ] Jobs wrapped in try-catch with full stack trace logging
- [ ] Execution time and memory logged
- [ ] Admin notification on failure (configurable)
- [ ] Lock pattern used for long-running jobs

### Custom Intervals
- [ ] Custom intervals registered via `cron_schedules` filter
- [ ] Intervals defined with seconds and display name

### Error Handling
- [ ] All errors logged with file, action, message, stack trace
- [ ] Failed jobs do not break subsequent runs
- [ ] Retry logic implemented where appropriate

### Monitoring
- [ ] Admin UI shows cron job status
- [ ] Next run time displayed
- [ ] Manual trigger available for admins

---

## Cross-References

- [02-error-management-foundation.md](../01-foundation/02-error-management-foundation.md) - Error handling patterns
- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Logging standards
- [01-plugin-structure-wordpress.md](./01-plugin-structure-wordpress.md) - Activation/deactivation hooks
- [02-rest-api-wordpress.md](./02-rest-api-wordpress.md) - Cron trigger endpoint
