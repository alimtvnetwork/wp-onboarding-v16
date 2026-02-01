# 44. Audit Logging

## Overview
Comprehensive audit trail for security, compliance, and debugging purposes.

---

## 44.1 Events to Log

### User Actions
- Login/logout events
- Role changes
- Permission modifications
- Password resets

### Exam Operations
- Exam created/updated/deleted
- Status changes
- Content modifications
- Settings changes

### Participant Operations
- Participant added/removed
- Status transitions
- Extension requests and decisions
- Checklist completions

### System Events
- Plugin activation/deactivation
- Settings changes
- Database migrations
- Cron job executions

### Acceptance Criteria:
- [ ] All listed events captured
- [ ] Events logged synchronously
- [ ] Logging failures don't break operations
- [ ] Event categories well-defined

---

## 44.2 Log Entry Structure

### Required Fields
- Timestamp (with timezone)
- Event type/category
- Actor (user ID or "system")
- Action performed
- Target entity type
- Target entity ID
- IP address (hashed for privacy)
- User agent

### Optional Fields
- Previous value (for changes)
- New value (for changes)
- Additional context (JSON)
- Request ID for tracing

### Acceptance Criteria:
- [ ] All required fields always present
- [ ] Sensitive data masked (passwords, keys)
- [ ] JSON context extensible
- [ ] Timestamps in UTC

---

## 44.3 Audit Log Viewer

### Admin Interface
- Searchable log table
- Filters by date range
- Filters by event type
- Filters by actor
- Filters by entity

### Log Entry Detail
- All fields displayed
- Before/after comparison for changes
- Related entries linked
- Copy entry to clipboard

### Acceptance Criteria:
- [ ] Fast search across large datasets
- [ ] Pagination for performance
- [ ] Export filtered results
- [ ] Mobile-responsive view

---

## 44.4 Log Retention

### Retention Policies
- Default: 90 days
- Security events: 1 year
- Configurable per category
- Compliance mode: indefinite

### Cleanup Process
- Cron job for retention enforcement
- Archive before delete option
- Compressed archive storage
- Deletion logged (meta-logging)

### Acceptance Criteria:
- [ ] Retention configurable in settings
- [ ] Cleanup runs automatically
- [ ] Archive format documented
- [ ] Legal hold option available

---

## 44.5 Log Export

### Export Formats
- CSV for spreadsheet analysis
- JSON for programmatic processing
- PDF for formal reports

### Export Options
- Date range selection
- Event type filtering
- Include/exclude specific fields
- Anonymize personal data option

### Acceptance Criteria:
- [ ] Large exports handled asynchronously
- [ ] Export job status trackable
- [ ] Download link expires after 24 hours
- [ ] Export action itself logged

---

## 44.6 Security Considerations

### Data Protection
- IP addresses hashed (SHA-256 with salt)
- Personal data minimized
- Sensitive values never logged in plain text
- Access to logs requires specific permission

### Tamper Prevention
- Logs append-only (no edits)
- Deletions only via retention policy
- Integrity checksums (optional)
- External backup recommended

### Acceptance Criteria:
- [ ] Logs cannot be modified after creation
- [ ] Admin deletion requires confirmation
- [ ] Bulk deletion restricted to retention policy
- [ ] Access to audit log viewer logged

---

## 44.7 Integration Points

### External SIEM
- Webhook for real-time export
- Syslog format support
- Configurable event filtering
- Authentication for webhook

### Alerting
- Define alert rules on event patterns
- Email notification for alerts
- Rate limiting to prevent alert storms
- Alert acknowledgment tracking

### Acceptance Criteria:
- [ ] Webhook configurable in settings
- [ ] Test webhook button available
- [ ] Failed webhooks retried with backoff
- [ ] Alert rules interface available
