# 32. Cron System

## Overview
WordPress cron jobs for automated background tasks including deadline checks, email sending, and cleanup operations.

---

## 32.1 Cron Job Registration

### Requirements
- Register all cron jobs on plugin activation
- Unregister all cron jobs on plugin deactivation
- Use WordPress cron API (`wp_schedule_event`, `wp_clear_scheduled_hook`)

### Cron Jobs to Register

| Hook Name | Interval | Description |
|-----------|----------|-------------|
| `eqm_check_deadlines` | Hourly | Check for deadline transitions |
| `eqm_process_email_queue` | Every 5 min | Send queued emails |
| `eqm_cleanup_expired_keys` | Daily | Remove expired secret keys |
| `eqm_generate_analytics` | Daily | Aggregate analytics data |
| `eqm_log_rotation` | Weekly | Rotate and archive log files |

### Acceptance Criteria:
- [ ] All cron jobs registered on activation
- [ ] All cron jobs cleared on deactivation
- [ ] Custom intervals defined for non-standard schedules
- [ ] Cron status visible in admin settings

---

## 32.2 Deadline Checker

### Requirements
- Run hourly to check all active participants
- Compare current time against soft and hard deadlines
- Trigger status transitions and email notifications

### Status Transitions

| Current Status | Condition | New Status | Action |
|----------------|-----------|------------|--------|
| ACTIVE | Past soft deadline | ACTIVE | Send soft deadline email |
| ACTIVE | Past hard deadline | LOCKED | Send locked email |
| EXTENDED | Past new deadline | LOCKED | Send locked email |

### Acceptance Criteria:
- [ ] Only processes participants with ACTIVE or EXTENDED status
- [ ] Correctly calculates deadline with timezone consideration
- [ ] Triggers appropriate email for each transition
- [ ] Updates `statusChangedAt` timestamp
- [ ] Logs all transitions for audit trail

---

## 32.3 Email Queue Processor

### Requirements
- Process queued emails in batches (max 20 per run)
- Track send attempts and failures
- Implement exponential backoff for retries

### Processing Logic
1. Fetch oldest 20 unsent emails from queue
2. Attempt to send each via `wp_mail`
3. Mark as sent or increment retry count
4. Move to failed after 3 attempts

### Acceptance Criteria:
- [ ] Processes max 20 emails per cron run
- [ ] Updates `sentAt` timestamp on success
- [ ] Increments `retryCount` on failure
- [ ] Marks as permanently failed after 3 retries
- [ ] Logs send errors with wp_mail error message

---

## 32.4 Expired Key Cleanup

### Requirements
- Run daily to remove expired secret keys
- Only delete keys past their `expiresAt` date
- Preserve access analytics before deletion

### Acceptance Criteria:
- [ ] Identifies keys where `expiresAt < NOW()`
- [ ] Archives access logs before deletion (optional)
- [ ] Deletes expired keys from database
- [ ] Logs number of keys cleaned up

---

## 32.5 Analytics Aggregation [OPTIONAL]

### Requirements
- Run daily to aggregate raw analytics into summary tables
- Calculate daily/weekly/monthly statistics
- Reduce storage by summarizing old detailed logs

### Metrics to Aggregate
- Total visits per exam per day
- Unique visitors per exam per day
- Geographic distribution summary
- Referrer source breakdown

### Acceptance Criteria:
- [ ] Creates daily summary records
- [ ] Purges detailed logs older than 30 days
- [ ] Maintains aggregated data indefinitely
- [ ] Handles large datasets efficiently

---

## 32.6 Log Rotation

### Requirements
- Run weekly to manage log file sizes
- Archive logs older than configured retention period
- Compress archived logs to save space

### Log Files to Manage
- `plugin.log` - General plugin logs
- `error.txt` - Error logs with stack traces
- `email.log` - Email send logs

### Retention Policy
- Keep current log files
- Archive files older than 30 days
- Delete archives older than 90 days

### Acceptance Criteria:
- [ ] Rotates logs when size exceeds 10MB
- [ ] Creates dated archive files
- [ ] Compresses archives with gzip
- [ ] Deletes old archives per retention policy
- [ ] Handles missing log files gracefully

---

## 32.7 Admin Cron Status Display

### Requirements
- Show cron job status in plugin settings
- Display next scheduled run time for each job
- Allow manual trigger for testing

### Acceptance Criteria:
- [ ] Lists all registered cron jobs
- [ ] Shows next run timestamp for each
- [ ] Shows last run timestamp and result
- [ ] Provides "Run Now" button for admins
- [ ] Displays warning if cron is disabled
