# 53. Monitoring & Alerting

## Overview
System health monitoring, alerting rules, and observability requirements for production reliability.

---

## 53.1 Health Check Endpoints

### Public Health Endpoint
```
GET /wp-json/eqm/v1/health
```

**Response (200 OK):**
```json
{
  "status": "healthy",
  "timestamp": "2026-01-25T13:00:00Z",
  "version": "1.0.0"
}
```

### Admin Health Dashboard Endpoint
```
GET /wp-json/eqm/v1/admin/health (requires admin role)
```

**Response (200 OK):**
```json
{
  "status": "healthy",
  "components": {
    "database": { "status": "healthy", "latencyMs": 5 },
    "emailQueue": { "status": "healthy", "pending": 12 },
    "cron": { "status": "healthy", "lastRun": "2026-01-25T12:00:00Z" },
    "storage": { "status": "healthy", "usedMB": 450, "quotaMB": 5000 }
  },
  "uptime": "14d 6h 32m"
}
```

### Component Status Values
| Status | Description |
|--------|-------------|
| `healthy` | Operating normally |
| `degraded` | Functional but with issues |
| `unhealthy` | Component failure |

---

## 53.2 Alerting Rules

### Critical Alerts (Immediate Notification)
| Condition | Threshold | Action |
|-----------|-----------|--------|
| Database unreachable | 3 consecutive failures | Email admin + log |
| Cron job missed | > 2 hours since last run | Email admin |
| Email queue backlog | > 100 pending emails | Log warning |
| Rate limit breaches | > 50/hour from single IP | Block IP + log |
| Authentication failures | > 20/hour from single IP | Block IP + log |

### Warning Alerts (Dashboard + Log)
| Condition | Threshold | Action |
|-----------|-----------|--------|
| Storage usage | > 80% of quota | Dashboard warning |
| Slow API response | > 2s average (5 min window) | Log + dashboard |
| Failed email sends | > 10% failure rate (1 hour) | Dashboard warning |
| Extension request queue | > 20 pending reviews | Dashboard badge |

### Informational (Log Only)
| Event | Description |
|-------|-------------|
| Plugin activation/deactivation | Track lifecycle events |
| Settings changes | Audit configuration changes |
| Bulk operations | Import/export actions |

---

## 53.3 Admin Dashboard Health Panel

### Location
Top of Admin Dashboard (first widget)

### Display Elements
```
┌─────────────────────────────────────────────────────────────┐
│  🟢 System Status: All systems operational                  │
│  ────────────────────────────────────────────────────────── │
│  Database: ✓ Healthy (5ms)    Email Queue: ✓ 12 pending    │
│  Cron Job: ✓ Last run 23m ago  Storage: ✓ 450/5000 MB     │
│  ────────────────────────────────────────────────────────── │
│  Pending Reviews: 8           Active Participants: 234      │
└─────────────────────────────────────────────────────────────┘
```

### Status Indicators
| Icon | Color | Meaning |
|------|-------|---------|
| 🟢 | Green | All healthy |
| 🟡 | Yellow | Some degradation |
| 🔴 | Red | Critical issues |

---

## 53.4 Cron Job Monitoring

### Health Check Table
**Table: `eqm_cron_health`**

| Field | Type | Description |
|-------|------|-------------|
| `id` | INT | Primary key |
| `jobName` | VARCHAR(100) | Cron job identifier |
| `lastRunAt` | DATETIME | Last successful execution |
| `lastDurationMs` | INT | Execution time in milliseconds |
| `lastStatus` | ENUM | success, warning, error |
| `lastError` | TEXT | Error message if failed |
| `consecutiveFailures` | INT | Count of consecutive failures |

### Monitored Jobs
| Job Name | Expected Interval | Alert After |
|----------|-------------------|-------------|
| `deadline_check` | Hourly | 2 hours |
| `email_queue_process` | Every 5 min | 15 min |
| `log_rotation` | Daily | 48 hours |
| `backup_database` | Daily | 48 hours |
| `cleanup_expired_tokens` | Daily | 48 hours |

---

## 53.5 Performance Metrics

### API Response Time Tracking
| Endpoint Category | Target | Warning | Critical |
|-------------------|--------|---------|----------|
| Public (read) | < 200ms | > 500ms | > 2s |
| Public (write) | < 500ms | > 1s | > 3s |
| Admin (read) | < 300ms | > 800ms | > 2s |
| Admin (write) | < 800ms | > 2s | > 5s |

### Database Query Metrics
| Metric | Target | Alert Threshold |
|--------|--------|-----------------|
| Average query time | < 50ms | > 200ms |
| Slow queries/hour | 0 | > 10 |
| Connection pool usage | < 50% | > 80% |

---

## 53.6 Alert Notification Channels

### Email Notifications
- Primary admin email (from settings)
- Optional secondary admin emails
- Rate limit: Max 10 emails/hour per alert type

### In-App Notifications
- Admin dashboard banner for active alerts
- Notification bell with count badge
- Dismissable after acknowledgment

### Log Persistence
All alerts logged to:
- `/logs/alerts.log` (dedicated alert log)
- Standard plugin log with `[ALERT]` prefix

---

## 53.7 Self-Healing Actions

### Automatic Recovery
| Condition | Auto-Recovery Action |
|-----------|----------------------|
| Email queue stuck | Restart queue processor |
| Cron job failed once | Immediate retry (max 3) |
| Rate limit IP | Auto-unblock after 1 hour |
| Session expired mid-operation | Graceful logout + redirect |

### Manual Intervention Required
| Condition | Admin Action Needed |
|-----------|---------------------|
| Database corruption | Restore from backup |
| Persistent email failures | Check SMTP configuration |
| Consecutive cron failures (>3) | Check server resources |

---

## 53.8 Common Pitfalls

### ❌ Anti-Patterns
- Not checking health before bulk operations
- Ignoring degraded status warnings
- Missing cron job monitoring
- No alert rate limiting (email flood)
- Blocking main thread for health checks

### ✅ Best Practices
- Cache health status for 30 seconds (avoid hammering)
- Use async logging for alerts
- Implement circuit breaker for external services
- Separate health check endpoint from main application logic
- Include version number in health response

---

## 53.9 Acceptance Criteria

### Health Endpoints
- [ ] Public health endpoint returns within 100ms
- [ ] Admin health shows all component statuses
- [ ] Unhealthy components correctly identified
- [ ] Health check doesn't impact main app performance

### Alerting
- [ ] Critical alerts trigger within 5 minutes
- [ ] Alert emails include actionable information
- [ ] Alert rate limiting prevents email flood
- [ ] Alerts logged with full context

### Dashboard
- [ ] Status panel visible on admin dashboard
- [ ] Status updates without page refresh (30s poll)
- [ ] Historical uptime shown
- [ ] Pending action counts accurate

---

## Related Specifications

| Topic | Spec |
|-------|------|
| Logging System | [07-logging-system.md](07-logging-system.md) |
| Cron System | [34-cron-system.md](34-cron-system.md) |
| Email Queue | [31-email-queue.md](31-email-queue.md) |
| Admin Dashboard | [37-admin-dashboard.md](37-admin-dashboard.md) |
