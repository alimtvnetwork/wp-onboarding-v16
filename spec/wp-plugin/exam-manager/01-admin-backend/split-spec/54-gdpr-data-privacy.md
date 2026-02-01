# 54. GDPR & Data Privacy

## Overview
Compliance requirements for GDPR, CCPA, and general data privacy regulations including data retention, right to erasure, and consent management.

---

## 54.1 Personal Data Inventory

### Data Categories
| Category | Fields | Retention | Legal Basis |
|----------|--------|-----------|-------------|
| **Identity** | name, email | Until deletion request | Contract fulfillment |
| **Contact** | linkedinUrl, whatsappNumber | Until deletion request | Contract fulfillment |
| **Progress** | sections completed, timestamps | Duration of exam + 1 year | Legitimate interest |
| **Access Logs** | IP (hashed), userAgent | 90 days | Security |
| **Authentication** | password (hashed), reset tokens | Active account | Contract fulfillment |
| **Submissions** | text, files, evidence | Duration of exam + 1 year | Contract fulfillment |

### Sensitive Data Handling
| Data Type | Storage Method | Access Control |
|-----------|---------------|----------------|
| Passwords | bcrypt hash (cost 12) | Never exposed via API |
| IP addresses | SHA-256 hash with salt | Admin only via audit log |
| Files/Evidence | Blob storage (encrypted at rest) | Participant + Admin |

---

## 54.2 Right to Access (Data Export)

### Participant Self-Service Export
**Endpoint:** `GET /api/participants/{id}/export-data`

**Response (JSON):**
```json
{
  "exportDate": "2026-01-25T13:00:00Z",
  "participant": {
    "name": "John Doe",
    "email": "john@example.com",
    "linkedinUrl": "https://linkedin.com/in/johndoe",
    "registeredAt": "2025-12-01T10:00:00Z"
  },
  "exams": [
    {
      "examTitle": "Advanced Exam",
      "status": "ACTIVE",
      "progress": 65,
      "sectionsCompleted": ["Section 1", "Section 3"],
      "extensionRequests": [],
      "submissions": []
    }
  ],
  "activityLog": [
    { "date": "2025-12-15", "action": "Completed Section 1" }
  ]
}
```

### Admin Bulk Export
- All participant data exportable via Admin UI
- Anonymization option for aggregate reports
- Export logged to audit trail

---

## 54.3 Right to Erasure (RTBF)

### Participant Deletion Request

**Endpoint:** `DELETE /api/participants/{id}/account`

**Process Flow:**
```
1. Receive deletion request
2. Verify participant identity (re-authentication)
3. Mark account for deletion (soft delete)
4. 48-hour cooling-off period (reversible)
5. Permanent deletion of all PII
6. Anonymize non-erasable records (audit logs)
7. Notify participant of completion
```

### Data Anonymization Rules
| Field | Anonymization Method |
|-------|---------------------|
| `name` | Replace with "Deleted User #{id}" |
| `email` | Replace with hash "deleted_{hash}@anonymized.local" |
| `linkedinUrl` | NULL |
| `whatsappNumber` | NULL |
| `progress records` | Retain with anonymized participant |
| `submissions` | Delete files, retain metadata |
| `audit logs` | Retain with "Deleted User #{id}" |

### Retention After Deletion
| Data Type | Retention | Reason |
|-----------|-----------|--------|
| Anonymized completion records | 7 years | Legal/audit compliance |
| Anonymized aggregate stats | Indefinite | Reporting |
| Certificate data | 7 years | Verification requests |

---

## 54.4 Consent Management

### Consent Types
| Purpose | Required | Default | Revocable |
|---------|----------|---------|-----------|
| Account creation | Yes | N/A | Account deletion |
| Email notifications | Yes | Opted-in | Settings toggle |
| Progress tracking | Yes | N/A | Account deletion |
| Analytics cookies | No | Opted-out | Cookie banner |
| Marketing emails | No | Opted-out | Unsubscribe link |

### Consent Storage
**Table: `eqm_participant` columns**
```
consentProgressTracking   BOOLEAN     DEFAULT true
consentEmailNotifications BOOLEAN     DEFAULT true
consentAnalytics          BOOLEAN     DEFAULT false
consentMarketingEmails    BOOLEAN     DEFAULT false
consentUpdatedAt          DATETIME    Updated on change
```

### Consent UI
- During signup: Clear checkboxes with explanation
- Settings page: Toggle switches with save confirmation
- Email footer: Unsubscribe link for marketing

---

## 54.5 Data Retention Automation

### Retention Periods
| Data Type | Retention | Trigger | Action |
|-----------|-----------|---------|--------|
| Active sessions | 30 days | Last activity | Delete session |
| Password reset tokens | 1 hour | Creation time | Delete token |
| Failed login attempts | 24 hours | Attempt time | Delete record |
| Audit logs (security) | 1 year | Log date | Archive then delete |
| Audit logs (general) | 90 days | Log date | Delete |
| Deleted accounts | 48 hours | Deletion request | Permanent delete |
| Inactive participants | 3 years | Last activity | Send reminder, then archive |

### Cron Job: Data Cleanup
```
Job: data_retention_cleanup
Frequency: Daily at 3:00 AM UTC
Actions:
  1. Delete expired sessions
  2. Delete expired tokens
  3. Delete old login attempts
  4. Archive old audit logs
  5. Finalize pending deletions past cooling-off
  6. Log cleanup summary
```

---

## 54.6 Data Processing Agreements

### Third-Party Services
| Service | Data Shared | DPA Required | Purpose |
|---------|-------------|--------------|---------|
| SMTP Provider | Email address, name | Yes | Transactional emails |
| File Storage | Uploaded files | Yes | Evidence storage |
| Analytics (if enabled) | Anonymized usage | Yes | Usage insights |

### Sub-Processor List
Maintain at: `/privacy/sub-processors.md`
- Update when adding new services
- Notify users of changes (if material)

---

## 54.7 Privacy Settings Admin UI

### Admin Panel: Privacy Settings
```
┌─────────────────────────────────────────────────────────────┐
│  Privacy & Data Retention Settings                          │
│  ────────────────────────────────────────────────────────── │
│  Default Retention Periods:                                 │
│  • Audit logs (security): [1 year ▼]                       │
│  • Audit logs (general):  [90 days ▼]                      │
│  • Inactive participants: [3 years ▼]                      │
│  ────────────────────────────────────────────────────────── │
│  Deletion Request Handling:                                 │
│  • Cooling-off period:    [48 hours ▼]                     │
│  • Auto-process after cooling-off: [✓]                     │
│  ────────────────────────────────────────────────────────── │
│  [Save Settings]                                            │
└─────────────────────────────────────────────────────────────┘
```

---

## 54.8 Common Pitfalls

### ❌ Anti-Patterns
- Storing plain IP addresses (must hash)
- Retaining data without defined period
- Hard-deleting without cooling-off period
- Missing deletion in related tables (orphan data)
- Not anonymizing audit logs on deletion
- Sending emails after consent withdrawal
- Exporting hashed data in user export (useless to them)

### ✅ Best Practices
- Use cascading deletes or explicit cleanup jobs
- Test deletion flow end-to-end
- Log all GDPR actions to separate audit trail
- Provide downloadable data in readable format (JSON/CSV)
- Include deletion confirmation email
- Document data flows for DPO review
- Use soft-delete with anonymization, not hard-delete

---

## 54.9 Acceptance Criteria

### Data Export
- [ ] Participants can export their data as JSON
- [ ] Export includes all personal data
- [ ] Export excludes hashed passwords/tokens
- [ ] Export action logged

### Data Deletion
- [ ] Deletion request creates soft-delete record
- [ ] 48-hour cooling-off before permanent delete
- [ ] All PII removed after cooling-off
- [ ] Audit logs anonymized (not deleted)
- [ ] Related files deleted from storage
- [ ] Confirmation email sent on completion

### Consent
- [ ] Consent collected at signup
- [ ] Consent modifiable in settings
- [ ] Marketing consent defaults to off
- [ ] Consent changes logged

### Retention
- [ ] Automated cleanup runs daily
- [ ] Retention periods configurable
- [ ] Cleanup logs summary of actions
- [ ] No data exceeds retention limits

---

## Related Specifications

| Topic | Spec |
|-------|------|
| Audit Logging | [46-audit-logging.md](46-audit-logging.md) |
| Participant Service | [27-participant-service.md](27-participant-service.md) |
| Database Schema | [04-database-schema.md](04-database-schema.md) |
| Settings | [35-plugin-settings.md](35-plugin-settings.md) |
