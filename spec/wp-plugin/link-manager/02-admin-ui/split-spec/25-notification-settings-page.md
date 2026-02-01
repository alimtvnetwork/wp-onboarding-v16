# 25 - Notification Settings Page

> **Status:** Complete  
> **Priority:** High  
> **Updated:** 2026-01-31

---

## Purpose

Admin interface for configuring notification preferences, managing recipients, webhook endpoints, and viewing notification history for the Link Manager plugin.

---

## Navigation

```
Link Manager → Settings → Notifications Tab
URL: /wp-admin/admin.php?page=link-manager-settings&tab=notifications
```

---

## Page Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ Notification Settings                                           │
├─────────────────────────────────────────────────────────────────┤
│ [General] [Recipients] [Webhooks] [History]                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─ Tab Content Area ─────────────────────────────────────────┐ │
│  │                                                             │ │
│  │  (Dynamic content based on selected tab)                   │ │
│  │                                                             │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│  [Save Changes]                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Tab: General Settings

### Alert Thresholds

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| Broken Link Threshold | number | 5 | Alert when broken links exceed count |
| Warning Link Threshold | number | 10 | Alert when warning links exceed count |
| Redirect Chain Threshold | number | 3 | Alert on redirect chains longer than N |
| SSL Error Alert | toggle | ON | Alert on SSL certificate issues |
| Timeout Alert | toggle | ON | Alert on connection timeouts |

### Delivery Settings

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| Email Notifications | toggle | ON | Enable email delivery |
| Admin Notices | toggle | ON | Show WP admin notices |
| Webhook Notifications | toggle | OFF | Enable webhook delivery |
| Batch Size | number | 50 | Max notifications per batch |

### Digest Configuration

| Field | Type | Options | Default |
|-------|------|---------|---------|
| Digest Frequency | select | None, Daily, Weekly | Daily |
| Digest Time | time | 00:00-23:59 | 09:00 |
| Digest Day | select | Mon-Sun | Monday |
| Include Statistics | toggle | ON/OFF | ON |

### Quiet Hours

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| Enable Quiet Hours | toggle | OFF | Suppress non-critical alerts |
| Quiet Start | time | 22:00 | Start suppression |
| Quiet End | time | 08:00 | End suppression |
| Weekend Quiet | toggle | OFF | Suppress on weekends |

---

## Tab: Recipients

### Recipients Table

```
┌──────────────────────────────────────────────────────────────────────┐
│ [+ Add Recipient]                                    [Search...]     │
├────────────────────────────────────────────────────────────────────── │
│ ☑ │ Email              │ Name        │ Digest │ Immediate │ Actions │
├───┼────────────────────┼─────────────┼────────┼───────────┼─────────┤
│ ☑ │ admin@example.com  │ Site Admin  │ Daily  │ Critical  │ ⋮       │
│ ☑ │ dev@example.com    │ Developer   │ Weekly │ All       │ ⋮       │
│ ☐ │ manager@example.com│ Manager     │ None   │ Critical  │ ⋮       │
└───┴────────────────────┴─────────────┴────────┴───────────┴─────────┘
│ Showing 1-3 of 3                              [< Prev] [1] [Next >] │
└──────────────────────────────────────────────────────────────────────┘
```

### Add/Edit Recipient Modal

```
┌─────────────────────────────────────────────────┐
│ Add Recipient                              [×]  │
├─────────────────────────────────────────────────┤
│                                                 │
│  Email *                                        │
│  ┌─────────────────────────────────────────┐   │
│  │ user@example.com                        │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  Display Name                                   │
│  ┌─────────────────────────────────────────┐   │
│  │ John Doe                                │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  Notification Preferences                       │
│  ┌─────────────────────────────────────────┐   │
│  │ Digest Frequency: [Daily ▼]             │   │
│  │                                         │   │
│  │ Immediate Alerts:                       │   │
│  │ ☑ Critical (Broken Links)               │   │
│  │ ☑ SSL Errors                            │   │
│  │ ☐ Warnings                              │   │
│  │ ☐ Info                                  │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  ☑ Active                                       │
│                                                 │
├─────────────────────────────────────────────────┤
│                    [Cancel] [Save Recipient]    │
└─────────────────────────────────────────────────┘
```

### Recipient Actions Menu

| Action | Description |
|--------|-------------|
| Edit | Open edit modal |
| Send Test | Send test notification |
| Disable | Toggle active status |
| Delete | Remove with confirmation |

---

## Tab: Webhooks

### Webhooks Table

```
┌──────────────────────────────────────────────────────────────────────────┐
│ [+ Add Webhook]                                         [Search...]      │
├──────────────────────────────────────────────────────────────────────────┤
│ ☑ │ Name          │ URL                    │ Auth   │ Status  │ Actions │
├───┼───────────────┼────────────────────────┼────────┼─────────┼─────────┤
│ ☑ │ Slack Alerts  │ https://hooks.slack... │ None   │ ● Active│ ⋮       │
│ ☑ │ PagerDuty     │ https://events.pager...│ Bearer │ ● Active│ ⋮       │
│ ☐ │ Custom API    │ https://api.example... │ HMAC   │ ○ Pause │ ⋮       │
└───┴───────────────┴────────────────────────┴────────┴─────────┴─────────┘
│ Showing 1-3 of 3                                [< Prev] [1] [Next >]   │
└──────────────────────────────────────────────────────────────────────────┘
```

### Add/Edit Webhook Modal

```
┌─────────────────────────────────────────────────────┐
│ Add Webhook Endpoint                           [×]  │
├─────────────────────────────────────────────────────┤
│                                                     │
│  Name *                                             │
│  ┌─────────────────────────────────────────────┐   │
│  │ Slack Notifications                         │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  Endpoint URL *                                     │
│  ┌─────────────────────────────────────────────┐   │
│  │ https://hooks.slack.com/services/...        │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  Authentication Method                              │
│  ┌─────────────────────────────────────────────┐   │
│  │ [None ▼]                                    │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  ┌─ If HMAC-SHA256 ────────────────────────────┐   │
│  │ Secret Key *                                │   │
│  │ ┌───────────────────────────────────────┐   │   │
│  │ │ ••••••••••••••••••••                  │   │   │
│  │ └───────────────────────────────────────┘   │   │
│  │ Header Name: X-Signature                    │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  ┌─ If Bearer Token ───────────────────────────┐   │
│  │ Token *                                     │   │
│  │ ┌───────────────────────────────────────┐   │   │
│  │ │ ••••••••••••••••••••                  │   │   │
│  │ └───────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  ┌─ If Basic Auth ─────────────────────────────┐   │
│  │ Username *              Password *          │   │
│  │ ┌─────────────────┐     ┌─────────────────┐ │   │
│  │ │ api_user        │     │ ••••••••        │ │   │
│  │ └─────────────────┘     └─────────────────┘ │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  Event Types                                        │
│  ☑ Broken Links    ☑ SSL Errors                    │
│  ☑ Redirects       ☐ Scan Complete                 │
│  ☑ Health Alerts   ☐ All Events                    │
│                                                     │
│  Retry Settings                                     │
│  Max Retries: [3 ▼]    Timeout: [30s ▼]            │
│                                                     │
│  ☑ Active                                           │
│                                                     │
├─────────────────────────────────────────────────────┤
│              [Test Webhook] [Cancel] [Save Webhook] │
└─────────────────────────────────────────────────────┘
```

### Webhook Actions Menu

| Action | Description |
|--------|-------------|
| Edit | Open edit modal |
| Test | Send test payload |
| View Logs | Show delivery history |
| Pause/Resume | Toggle active status |
| Delete | Remove with confirmation |

### Webhook Test Result

```
┌─────────────────────────────────────────────────┐
│ Webhook Test Result                        [×]  │
├─────────────────────────────────────────────────┤
│                                                 │
│  Status: ● Success (200 OK)                     │
│  Response Time: 245ms                           │
│                                                 │
│  Request:                                       │
│  ┌─────────────────────────────────────────┐   │
│  │ POST https://hooks.slack.com/...        │   │
│  │ Content-Type: application/json          │   │
│  │ X-Signature: sha256=abc123...           │   │
│  │                                         │   │
│  │ {                                       │   │
│  │   "event": "test",                      │   │
│  │   "timestamp": "2026-01-31T12:00:00Z",  │   │
│  │   "data": { ... }                       │   │
│  │ }                                       │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  Response:                                      │
│  ┌─────────────────────────────────────────┐   │
│  │ {"ok": true}                            │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
├─────────────────────────────────────────────────┤
│                                       [Close]   │
└─────────────────────────────────────────────────┘
```

---

## Tab: History

### Notification History Table

```
┌────────────────────────────────────────────────────────────────────────────────┐
│ Filters: [All Types ▼] [All Status ▼] [Last 7 Days ▼]        [Search...]       │
├────────────────────────────────────────────────────────────────────────────────┤
│ Type    │ Recipient/Endpoint │ Subject              │ Status    │ Sent At     │
├─────────┼────────────────────┼──────────────────────┼───────────┼─────────────┤
│ 📧 Email│ admin@example.com  │ 5 Broken Links Found │ ● Sent    │ 2 hours ago │
│ 🔔 Webhook│ Slack Alerts     │ Health Alert         │ ● Sent    │ 3 hours ago │
│ 📧 Email│ dev@example.com    │ Daily Digest         │ ● Sent    │ Yesterday   │
│ 🔔 Webhook│ PagerDuty        │ Critical Alert       │ ○ Failed  │ Yesterday   │
│ 📧 Email│ admin@example.com  │ Weekly Summary       │ ◐ Pending │ Scheduled   │
└─────────┴────────────────────┴──────────────────────┴───────────┴─────────────┘
│ Showing 1-5 of 127                                    [< Prev] [1] [Next >]   │
└────────────────────────────────────────────────────────────────────────────────┘
```

### History Detail Modal

```
┌─────────────────────────────────────────────────────┐
│ Notification Details                           [×]  │
├─────────────────────────────────────────────────────┤
│                                                     │
│  Type: Email                                        │
│  Status: ● Sent                                     │
│  Sent At: 2026-01-31 10:30:45                      │
│                                                     │
│  Recipient: admin@example.com                       │
│  Subject: 5 Broken Links Found on Your Site        │
│                                                     │
│  ┌─ Content Preview ───────────────────────────┐   │
│  │                                             │   │
│  │  Link Manager Alert                         │   │
│  │  ─────────────────                          │   │
│  │  5 broken links were detected during        │   │
│  │  the latest scan:                           │   │
│  │                                             │   │
│  │  • /about-us → 404 Not Found               │   │
│  │  • /contact → Connection Timeout           │   │
│  │  • /products/old → 410 Gone                │   │
│  │  ...                                        │   │
│  │                                             │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  Delivery Attempts: 1                               │
│  Response: 250 OK                                   │
│                                                     │
├─────────────────────────────────────────────────────┤
│                          [Resend] [Close]           │
└─────────────────────────────────────────────────────┘
```

### Failed Notification Actions

| Action | Description |
|--------|-------------|
| View Details | Show error information |
| Retry | Attempt redelivery |
| Cancel | Remove from queue |

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/lm/v1/notifications/settings` | Get all settings |
| PUT | `/lm/v1/notifications/settings` | Update settings |
| GET | `/lm/v1/notifications/recipients` | List recipients |
| POST | `/lm/v1/notifications/recipients` | Add recipient |
| PUT | `/lm/v1/notifications/recipients/{id}` | Update recipient |
| DELETE | `/lm/v1/notifications/recipients/{id}` | Delete recipient |
| POST | `/lm/v1/notifications/recipients/{id}/test` | Send test email |
| GET | `/lm/v1/notifications/webhooks` | List webhooks |
| POST | `/lm/v1/notifications/webhooks` | Add webhook |
| PUT | `/lm/v1/notifications/webhooks/{id}` | Update webhook |
| DELETE | `/lm/v1/notifications/webhooks/{id}` | Delete webhook |
| POST | `/lm/v1/notifications/webhooks/{id}/test` | Test webhook |
| GET | `/lm/v1/notifications/webhooks/{id}/logs` | Webhook delivery logs |
| GET | `/lm/v1/notifications/history` | Notification history |
| GET | `/lm/v1/notifications/history/{id}` | Single notification detail |
| POST | `/lm/v1/notifications/history/{id}/retry` | Retry failed notification |

---

## Validation Rules

### Recipient Validation

| Field | Rules |
|-------|-------|
| Email | Required, valid email format, max 255 chars |
| Name | Optional, max 100 chars |
| Digest Frequency | Enum: none, daily, weekly |
| Active | Boolean |

### Webhook Validation

| Field | Rules |
|-------|-------|
| Name | Required, max 100 chars |
| URL | Required, valid HTTPS URL, max 500 chars |
| Auth Method | Enum: none, hmac_sha256, bearer, basic |
| Secret/Token | Required if auth != none, max 500 chars |
| Max Retries | 0-10 |
| Timeout | 5-120 seconds |

### Settings Validation

| Field | Rules |
|-------|-------|
| Broken Threshold | 1-1000 |
| Warning Threshold | 1-1000 |
| Redirect Threshold | 1-20 |
| Batch Size | 10-500 |
| Digest Time | Valid HH:MM format |

---

## Error States

| Error Code | Message | UI Display |
|------------|---------|------------|
| 14850 | Invalid recipient email | Inline field error |
| 14851 | Webhook URL unreachable | Toast + modal warning |
| 14852 | Authentication failed | Inline field error |
| 14853 | Duplicate recipient | Toast notification |
| 14854 | Invalid webhook signature | Modal error detail |
| 14855 | Rate limit exceeded | Toast with retry info |

---

## Success States

| Action | Feedback |
|--------|----------|
| Settings saved | Toast: "Notification settings saved" |
| Recipient added | Toast: "Recipient added successfully" |
| Test email sent | Toast: "Test email sent to {email}" |
| Webhook tested | Modal: Test result with details |
| Notification retried | Toast: "Notification queued for retry" |

---

## Accessibility

| Element | Requirement |
|---------|-------------|
| Form fields | Associated labels, error announcements |
| Tables | Proper headers, row selection announced |
| Modals | Focus trap, escape to close |
| Status indicators | Color + icon + text |
| Actions | Keyboard accessible |

---

## Related Specs

- `24-notification-service.md` - Backend notification service
- `23-link-health-monitor.md` - Health monitoring integration
- `20-settings-page.md` - Parent settings page
- `04-database-schema.md` - Notification tables

---

## Acceptance Criteria

- [ ] General settings form saves and loads correctly
- [ ] Recipients can be added, edited, and deleted
- [ ] Test emails are sent and confirmed
- [ ] Webhooks can be configured with all auth methods
- [ ] Webhook tests show request/response details
- [ ] History displays with filtering and pagination
- [ ] Failed notifications can be retried
- [ ] All forms validate input correctly
- [ ] Success/error states display appropriately
- [ ] Accessible via keyboard navigation
