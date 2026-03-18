# Diagnostic Error Reporting — Planned Feature Spec

> **Status:** Planned (not yet implemented)  
> **Plugin:** Riseup Asia Uploader  
> **Created:** 2026-03-14  
> **Priority:** Medium  
> **Depends on:** Migration v16 (PluginVersion on ErrorSessions)

---

## Summary

An opt-in diagnostic reporting system that periodically sends error log summaries to a developer endpoint, enabling proactive bug detection and resolution across deployed sites.

---

## User Consent Flow

### First-Time Opt-In Dialog

When a user accesses the Riseup Asia Uploader admin page **for the first time** (no prior consent recorded), a modal dialog appears:

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                    │
│   🔧 Help Us Improve Riseup Asia Uploader                        │
│                                                                    │
│   Would you like to opt in to anonymous diagnostic reporting?     │
│   This allows us to automatically detect and fix errors           │
│   from your error logs.                                           │
│                                                                    │
│   ☑ Send diagnostic error reports to the developer (weekly)       │
│   ☑ I agree to the Terms and Conditions                           │
│      (link: {termsAndConditionsUrl})                               │
│                                                                    │
│   Both checkboxes are checked by default.                          │
│                                                                    │
│   [ Agree & Enable ]    [ Skip for Now ]                           │
│                                                                    │
└──────────────────────────────────────────────────────────────────┘
```

### Consent Storage

- Consent state stored in SQLite `Settings` table via CW Config seeding
- Key: `DiagnosticReporting.Enabled` (boolean)
- Key: `DiagnosticReporting.ConsentedAt` (ISO 8601 UTC timestamp)
- Key: `DiagnosticReporting.ConsentedByUserId` (WP user ID)

### Settings Page Toggle

After initial consent, the setting appears in the **Riseup Asia Uploader > Settings** page:

```
☑ Send diagnostic error reports to the developer
  Automatically sends anonymized error summaries weekly.
  Last sent: 14-Mar-26 09:00 AM
```

The user can toggle this on/off at any time.

---

## Data Payload

When diagnostic reporting is enabled, the following data is collected and sent weekly:

### Required Fields

| Field | Source | Description |
|-------|--------|-------------|
| `licenseKey` | CW Config / Settings DB | Plugin license key for this installation |
| `siteUrl` | `get_site_url()` | WordPress site URL |
| `username` | `wp_get_current_user()->user_login` | Username of the admin who consented |
| `userRole` | `wp_get_current_user()->roles[0]` | Primary role of the consenting user |
| `pluginVersion` | `PluginConfigType::Version` | Current plugin version |
| `wpVersion` | `get_bloginfo('version')` | WordPress version |
| `phpVersion` | `PHP_VERSION` | PHP version |
| `errorSummary` | ErrorSessions table | Aggregated error data (see below) |

### Error Summary Structure

```json
{
  "licenseKey": "RA-XXXX-XXXX-XXXX",
  "siteUrl": "https://example.com",
  "username": "admin",
  "userRole": "administrator",
  "pluginVersion": "2.12.0",
  "wpVersion": "6.9.4",
  "phpVersion": "8.2.15",
  "reportPeriod": {
    "from": "2026-03-07T00:00:00Z",
    "to": "2026-03-14T00:00:00Z"
  },
  "errorSummary": {
    "totalErrors": 42,
    "byLevel": {
      "ERROR": 30,
      "WARNING": 10,
      "CRITICAL": 2
    },
    "byVersion": {
      "2.11.0": 15,
      "2.12.0": 27
    },
    "topMessages": [
      {
        "message": "Migration v12 failed — rolled back: Missing padding character",
        "count": 12,
        "lastSeen": "2026-03-14T09:04:00Z",
        "pluginVersion": "2.12.0",
        "source": "DatabaseMigrationsV12Trait.php:205"
      }
    ],
    "recentErrors": [
      {
        "id": 386,
        "level": "ERROR",
        "message": "...",
        "file": "...",
        "line": 205,
        "pluginVersion": "2.12.0",
        "createdAt": "2026-03-14T09:04:00Z"
      }
    ]
  },
  "sentAt": "2026-03-14T09:30:00Z"
}
```

### Data NOT Sent

- No user passwords or credentials
- No site content or database data
- No personally identifiable user data beyond username and role
- No error context JSON (may contain sensitive data)
- No full stack traces (only source file:line references)

---

## CW Config Seeding

The following keys are seeded from `config.seed.json` on first run:

```json
{
  "diagnosticReporting": {
    "enabled": false,
    "sendingUrl": "",
    "termsAndConditionsUrl": "",
    "reportIntervalDays": 7,
    "maxErrorsPerReport": 50,
    "maxTopMessages": 10
  }
}
```

### Implementation Notes

- `sendingUrl` — **ASK the developer** for the POST endpoint URL at implementation time
- `termsAndConditionsUrl` — **ASK the developer** for the T&C page URL at implementation time
- Both URLs come from CW Config and are seeded into the SQLite Settings table on first read
- If `sendingUrl` is empty, reporting is silently disabled regardless of the user toggle

---

## Submission Mechanism

### Schedule

- WordPress cron job: `riseup_asia_diagnostic_report` 
- Frequency: Weekly (configurable via `reportIntervalDays`)
- Runs on the next page load after the interval has elapsed

### Endpoint

```
POST {sendingUrl}
Content-Type: application/json
Authorization: Bearer {licenseKey}

Body: (see Data Payload above)
```

### Response Handling

| HTTP Status | Action |
|-------------|--------|
| 200-299 | Log success, update `DiagnosticReporting.LastSentAt` |
| 400-499 | Log warning, do not retry until next interval |
| 500+ | Log error, retry on next cron run |

### Failure Resilience

- Never block plugin operation on reporting failure
- Maximum 3 consecutive failures before auto-disabling with admin notice
- Re-enable requires manual toggle in settings

---

## Database Changes

### New Settings Keys (via CW Config seed)

| Key | Type | Default |
|-----|------|---------|
| `DiagnosticReporting.Enabled` | boolean | `false` |
| `DiagnosticReporting.SendingUrl` | string | `""` |
| `DiagnosticReporting.TermsUrl` | string | `""` |
| `DiagnosticReporting.ConsentedAt` | string | `null` |
| `DiagnosticReporting.ConsentedByUserId` | int | `null` |
| `DiagnosticReporting.LastSentAt` | string | `null` |
| `DiagnosticReporting.ConsecutiveFailures` | int | `0` |
| `DiagnosticReporting.ReportIntervalDays` | int | `7` |

---

## Implementation Checklist (for when implementing)

1. [ ] **ASK:** What is the `sendingUrl` endpoint?
2. [ ] **ASK:** What is the `termsAndConditionsUrl`?
3. [ ] Add CW Config seed keys for diagnostic reporting
4. [ ] Create consent modal component (first-time popup)
5. [ ] Add settings toggle in admin settings page
6. [ ] Implement WP cron job for weekly submission
7. [ ] Build error summary aggregation query
8. [ ] Implement POST submission with Bearer auth
9. [ ] Add failure tracking and auto-disable logic
10. [ ] Add "Last sent" display in settings
11. [ ] Write migration for any new DB columns if needed

---

## Security Considerations

1. **Opt-in only** — Never send data without explicit user consent
2. **License-based auth** — Reports are tied to a valid license key
3. **Minimal data** — Only error metadata, no content or credentials
4. **User control** — Can be disabled at any time from settings
5. **Transparent** — Settings page shows what data is sent and when
6. **Auto-disable** — Consecutive failures stop reporting automatically
