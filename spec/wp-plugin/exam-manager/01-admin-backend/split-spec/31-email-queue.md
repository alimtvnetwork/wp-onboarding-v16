# 29. Email Queue

## Overview
Background email processing system with retry logic, rate limiting, and delivery tracking.

---

## 29.1 Queue Data Structure

### EmailQueueItem Object
```
EmailQueueItem
├── id: int
├── recipient_email: string
├── recipient_name: string
├── template_id: string
├── template_vars: JSON
├── priority: LOW | NORMAL | HIGH | URGENT
├── status: PENDING | PROCESSING | SENT | FAILED | CANCELLED
├── attempts: int
├── max_attempts: int (default: 3)
├── last_attempt_at: timestamp|null
├── last_error: text|null
├── scheduled_at: timestamp
├── sent_at: timestamp|null
├── created_at: timestamp
└── metadata: JSON (tracking data)
```

### Acceptance Criteria:
- [ ] Indexed by status and scheduled_at
- [ ] Template vars encrypted at rest
- [ ] Metadata includes source context (exam_id, participant_id)

---

## 29.2 Queue Operations

### Enqueue
```
EmailQueue::enqueue(
  recipient: EmailRecipient,
  template: string,
  vars: array,
  options: {
    priority: Priority,
    scheduled_at: timestamp,
    metadata: array
  }
): int // returns queue item ID
```

### Batch Enqueue
```
EmailQueue::enqueueBatch(
  items: QueueItem[]
): BatchResult // returns success/failure counts
```

### Acceptance Criteria:
- [ ] Duplicate detection (same recipient + template within 1 hour)
- [ ] Scheduled emails for future delivery
- [ ] Priority affects processing order
- [ ] Batch insert uses single transaction

---

## 29.3 Processing Cron Job

### Schedule
Runs every 5 minutes via WP-Cron.

### Process
1. Lock queue to prevent concurrent processing
2. Fetch batch of pending items (priority order, oldest first)
3. For each item:
   - Mark as PROCESSING
   - Render template with variables
   - Send via wp_mail()
   - Update status based on result
4. Release lock

### Batch Configuration
- Default batch size: 20 emails
- Rate limit: configurable (default: 100/hour)
- Processing timeout: 30 seconds per email

### Acceptance Criteria:
- [ ] Concurrent processing prevented
- [ ] Rate limiting respects host limits
- [ ] Timeout prevents hung processes
- [ ] Failed items returned to PENDING with incremented attempts

---

## 29.4 Retry Logic

### Retry Schedule
| Attempt | Delay |
|---------|-------|
| 1st retry | 5 minutes |
| 2nd retry | 30 minutes |
| 3rd retry | 2 hours |
| Final | Mark as FAILED |

### Failure Handling
- Temporary failures (timeout, rate limit): Retry
- Permanent failures (invalid email, template error): Mark FAILED immediately
- Partial failures (some recipients failed): Log and continue

### Acceptance Criteria:
- [ ] Exponential backoff implemented
- [ ] Error type classification
- [ ] Failed emails surfaced in admin dashboard
- [ ] Manual retry option for failed items

---

## 29.5 Delivery Tracking

### Tracked Events
| Event | Source | Data |
|-------|--------|------|
| QUEUED | System | timestamp, template |
| SENT | wp_mail | timestamp |
| OPENED | Tracking pixel | timestamp, IP (hashed), user agent |
| CLICKED | Link tracking | timestamp, link URL, IP (hashed) |
| BOUNCED | Webhook (if configured) | bounce type, timestamp |
| UNSUBSCRIBED | Unsubscribe link | timestamp |

### Acceptance Criteria:
- [ ] Tracking pixel optional (privacy setting)
- [ ] Link tracking via redirect URL
- [ ] IP addresses hashed for privacy
- [ ] Aggregate stats available (open rate, click rate)

---

## 29.6 Admin Interface

### Queue Dashboard
- Current queue depth by status
- Processing rate (emails/hour)
- Failure rate (last 24h)
- Top failing templates

### Queue Browser
- List all queue items with filters
- Search by recipient email
- View individual email details
- Actions: retry, cancel, delete

### Acceptance Criteria:
- [ ] Real-time queue stats
- [ ] Bulk actions (retry all failed, clear old)
- [ ] Email preview without sending
- [ ] Export queue for debugging

---

## 29.7 Template Rendering

### Variable Substitution
Templates use `{{variable}}` syntax:
- `{{participant.name}}` - Participant's full name
- `{{exam.title}}` - Exam title
- `{{deadline.soft}}` - Formatted soft deadline
- `{{links.access_url}}` - Direct exam access URL

### Rendering Process
1. Load template from database
2. Validate all required variables present
3. Substitute variables with HTML escaping
4. Wrap in email layout template
5. Generate plain-text version

### Acceptance Criteria:
- [ ] Missing variables cause render failure
- [ ] HTML escaping prevents XSS
- [ ] Plain-text fallback for all emails
- [ ] Preview shows rendered output
