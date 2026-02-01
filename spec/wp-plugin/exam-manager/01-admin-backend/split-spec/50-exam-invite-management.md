# 50. Exam Invite Management

## Overview
Admin UI for managing exam invitations in invite-only exams. Includes bulk import, invitation emails, status tracking, and revocation.

---

## 50.1 Invite Management Tab

### Location
Added as a new tab in the Exam Editor when `isInviteOnly = true`.

### Tab Structure
```
┌─────────────────────────────────────────────────────────────────┐
│  Exam Editor: "JavaScript Certification"                        │
├─────────┬──────────┬───────────┬──────────┬────────────┬───────┤
│ Content │ Metadata │ Sub-Exams │ Prereqs  │ Checklists │Invites│
└─────────┴──────────┴───────────┴──────────┴────────────┴───────┘
                                                          ▲ NEW
```

### Tab Visibility Rules
- Tab only visible when exam has `isInviteOnly = true`
- If toggled off in Metadata, tab disappears (with confirmation if invites exist)
- Badge shows count of pending invites: `Invites (12)`

---

## 50.2 Invite List View

### Layout
```
┌─────────────────────────────────────────────────────────────────┐
│  [+ Add Invite]  [📥 Bulk Import]  [📤 Export]    🔍 [Search...] │
├─────────────────────────────────────────────────────────────────┤
│  Filter: [All ▼]  [Pending ○]  [Accepted ○]  [Expired ○]        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ☑  Email                    Phone           Status    Actions  │
│  ─────────────────────────────────────────────────────────────  │
│  ☐  john@example.com        +1234567890      🟡 Pending  ⋮      │
│  ☐  jane@example.com        +0987654321      🟢 Accepted ⋮      │
│  ☐  bob@example.com         +1122334455      🔴 Expired  ⋮      │
│  ☐  alice@example.com       +5566778899      🟡 Pending  ⋮      │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  Showing 1-4 of 4 invites                    [< Prev] [Next >]  │
└─────────────────────────────────────────────────────────────────┘
```

### Columns
| Column | Sortable | Description |
|--------|----------|-------------|
| Checkbox | No | Bulk selection |
| Email | Yes | Invitee email address |
| Phone | Yes | WhatsApp number |
| Name | Yes | Optional display name |
| Status | Yes | Invite status badge |
| Invited At | Yes | Date/time of invitation |
| Actions | No | Quick action menu |

### Status Badges
| Status | Color | Icon | Description |
|--------|-------|------|-------------|
| PENDING | Yellow | 🟡 | Awaiting signup |
| ACCEPTED | Green | 🟢 | User has signed up |
| EXPIRED | Gray | ⚫ | Past expiration date |
| REVOKED | Red | 🔴 | Manually revoked |

### Quick Actions (⋮ Menu)
- **Resend Invite Email** (PENDING only)
- **View Details** 
- **Edit Invite** (PENDING only)
- **Revoke Invite** (PENDING only)
- **Copy Signup Link**

---

## 50.3 Add Single Invite

### Modal Dialog
```
┌─────────────────────────────────────────┐
│  Add New Invite                      ✕  │
├─────────────────────────────────────────┤
│                                         │
│  Email *                                │
│  [_________________________________]    │
│                                         │
│  Phone (WhatsApp) *                     │
│  [_________________________________]    │
│  Include country code (e.g., +1...)     │
│                                         │
│  Name                                   │
│  [_________________________________]    │
│  Optional - for personalization         │
│                                         │
│  Expires At                             │
│  [📅 Select date________________]       │
│  Leave empty for no expiration          │
│                                         │
│  Notes (Internal)                       │
│  [_________________________________]    │
│  [_________________________________]    │
│                                         │
│  ☑ Send invitation email immediately    │
│                                         │
├─────────────────────────────────────────┤
│  [Cancel]              [Add Invite]     │
└─────────────────────────────────────────┘
```

### Validation Rules
| Field | Validation | Error Message |
|-------|------------|---------------|
| Email | Required, valid format, unique in exam | "Email already invited" |
| Phone | Required, valid format, unique in exam | "Phone already invited" |
| Name | Optional, max 100 chars | "Name too long" |
| Expires At | Optional, must be future date | "Expiration must be in the future" |

### Acceptance Criteria
- [ ] Email validated for format and uniqueness within exam
- [ ] Phone validated for format and uniqueness within exam
- [ ] Duplicate check happens on blur (before submit)
- [ ] Success shows toast and adds row to list
- [ ] Optionally sends email immediately on create

---

## 50.4 Bulk Import from CSV

### Import Button Action
Opens multi-step import wizard modal.

### Step 1: File Upload
```
┌─────────────────────────────────────────────────────────────────┐
│  Bulk Import Invites                                         ✕  │
├─────────────────────────────────────────────────────────────────┤
│  Step 1 of 3: Upload CSV                                        │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                                                             ││
│  │       📄 Drag & drop CSV file here                         ││
│  │           or click to browse                                ││
│  │                                                             ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  📥 Download sample CSV template                                │
│                                                                  │
│  Required columns: email, phone                                  │
│  Optional columns: name, expires_at, notes                       │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                              [Cancel]  [Next →] │
└─────────────────────────────────────────────────────────────────┘
```

### CSV Template Format
```csv
email,phone,name,expires_at,notes
john@example.com,+1234567890,John Doe,2026-03-01,VIP candidate
jane@example.com,+0987654321,Jane Smith,,Referred by partner
bob@example.com,+1122334455,,,
```

### Step 2: Preview & Validation
```
┌─────────────────────────────────────────────────────────────────┐
│  Bulk Import Invites                                         ✕  │
├─────────────────────────────────────────────────────────────────┤
│  Step 2 of 3: Preview & Validate                                │
│                                                                  │
│  📊 File: invites.csv (15 rows)                                 │
│                                                                  │
│  ✅ 12 valid rows                                               │
│  ⚠️  2 warnings (duplicates in file)                           │
│  ❌  1 error (invalid email format)                             │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Row │ Email              │ Phone        │ Status           ││
│  │─────┼────────────────────┼──────────────┼──────────────────││
│  │ 1   │ john@example.com   │ +123456789   │ ✅ Valid         ││
│  │ 2   │ jane@example.com   │ +098765432   │ ✅ Valid         ││
│  │ 3   │ invalid-email      │ +111222333   │ ❌ Invalid email ││
│  │ 4   │ john@example.com   │ +444555666   │ ⚠️ Duplicate     ││
│  │ ... │ ...                │ ...          │ ...              ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ☑ Skip rows with errors (import valid rows only)              │
│  ☐ Include rows with warnings                                   │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  [← Back]                              [Cancel]  [Import 12 →] │
└─────────────────────────────────────────────────────────────────┘
```

### Validation Checks (Per Row)
| Check | Type | Message |
|-------|------|---------|
| Email format | Error | "Invalid email format" |
| Phone format | Error | "Invalid phone format" |
| Email already invited (in DB) | Error | "Already invited" |
| Phone already invited (in DB) | Error | "Phone already invited" |
| Duplicate email (in file) | Warning | "Duplicate in file" |
| Duplicate phone (in file) | Warning | "Duplicate in file" |
| Invalid date format | Error | "Invalid date format" |
| Past expiration date | Warning | "Expiration in past" |

### Step 3: Confirmation & Results
```
┌─────────────────────────────────────────────────────────────────┐
│  Bulk Import Invites                                         ✕  │
├─────────────────────────────────────────────────────────────────┤
│  Step 3 of 3: Import Complete                                   │
│                                                                  │
│                    ✅                                           │
│              Import Successful                                   │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  ✅ 12 invites created successfully                        ││
│  │  ⚠️  2 rows skipped (warnings)                             ││
│  │  ❌  1 row failed (errors)                                 ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ☑ Send invitation emails to all imported invites              │
│                                                                  │
│  📥 Download error report (CSV)                                 │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                    [Close]  [Send Emails →]    │
└─────────────────────────────────────────────────────────────────┘
```

### Acceptance Criteria
- [ ] CSV parsing handles quoted fields and escapes
- [ ] Preview shows first 100 rows with scroll
- [ ] Validation runs client-side for format, server-side for uniqueness
- [ ] Transaction rollback on critical errors
- [ ] Error report CSV downloadable with row numbers and messages
- [ ] Batch email sending queued (not blocking)

---

## 50.5 Invitation Emails

### Email Trigger Points
| Action | Email Sent | Template |
|--------|------------|----------|
| Single invite created (checkbox checked) | Yes | `invite_created` |
| Bulk import completed (checkbox checked) | Yes | `invite_created` |
| "Resend Invite Email" action | Yes | `invite_reminder` |
| Invite about to expire (3 days) | Automatic | `invite_expiring` |

### Email Template: invite_created
```
Subject: You're invited to {{exam_title}}

Hi {{name|"there"}},

You've been invited to participate in "{{exam_title}}".

To get started, click the link below and complete your registration:

{{signup_url}}

Important: When signing up, please use this email address ({{email}}) 
and your registered phone number ({{phone}}).

{{#if expires_at}}
This invitation expires on {{expires_at}}.
{{/if}}

Questions? Reply to this email for assistance.

Best regards,
{{site_name}} Team
```

### Email Template: invite_reminder
```
Subject: Reminder: Complete your {{exam_title}} registration

Hi {{name|"there"}},

This is a friendly reminder that you have a pending invitation 
to participate in "{{exam_title}}".

Click here to register: {{signup_url}}

{{#if expires_at}}
⚠️ This invitation expires on {{expires_at}}.
{{/if}}

Best regards,
{{site_name}} Team
```

### Email Template: invite_expiring
```
Subject: ⏰ Your {{exam_title}} invitation expires soon

Hi {{name|"there"}},

Your invitation to "{{exam_title}}" will expire in 3 days 
on {{expires_at}}.

Don't miss out! Register now: {{signup_url}}

Best regards,
{{site_name}} Team
```

### Email Variables
| Variable | Source | Description |
|----------|--------|-------------|
| `{{exam_title}}` | exam.title | Exam display name |
| `{{name}}` | examInvite.name | Invitee name (with fallback) |
| `{{email}}` | examInvite.email | Invitee email |
| `{{phone}}` | examInvite.phone | Masked phone (last 4 digits) |
| `{{signup_url}}` | Generated | `https://site.com/{slug}?invite={token}` |
| `{{expires_at}}` | examInvite.expiresAt | Formatted date |
| `{{site_name}}` | Settings | Site/plugin name |

### Acceptance Criteria
- [ ] Emails queued in emailQueue table
- [ ] Rate limiting: max 50 emails per batch, 5-second delay between batches
- [ ] Unsubscribe link included in footer
- [ ] Template variables properly escaped
- [ ] Failed emails logged with retry

---

## 50.6 Bulk Actions

### Available Bulk Actions
```
┌─────────────────────────────────────────┐
│  With selected (3):  [Choose action ▼]  │
│                      ──────────────────  │
│                      📧 Send Emails      │
│                      🔄 Resend Emails    │
│                      ❌ Revoke Invites   │
│                      📤 Export Selected  │
└─────────────────────────────────────────┘
```

### Send/Resend Emails
- Queues invitation emails for all selected PENDING invites
- Shows confirmation: "Send emails to X invites?"
- Progress indicator during queue processing
- Summary on completion: "X emails queued successfully"

### Revoke Invites
- Only available for PENDING status invites
- Confirmation dialog: "Revoke X invites? This cannot be undone."
- Sets status to REVOKED
- No email sent on revocation

### Export Selected
- Exports selected rows to CSV
- Includes all fields: email, phone, name, status, invited_at, expires_at, notes

---

## 50.7 Invite Detail View

### Slide-out Panel
```
┌─────────────────────────────────────────┐
│  Invite Details                      ✕  │
├─────────────────────────────────────────┤
│                                         │
│  Status: 🟢 Accepted                    │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  Email                                  │
│  john@example.com                       │
│                                         │
│  Phone                                  │
│  +1234567890                            │
│                                         │
│  Name                                   │
│  John Doe                               │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  Invited At                             │
│  January 20, 2026 at 3:45 PM            │
│                                         │
│  Invited By                             │
│  Admin User (admin@example.com)         │
│                                         │
│  Expires At                             │
│  February 20, 2026                      │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  📝 Notes                               │
│  VIP candidate, referred by partner.    │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  📊 Activity                            │
│  ──────────────────────────────         │
│  Jan 20  Invite created by Admin        │
│  Jan 21  Email sent (delivered)         │
│  Jan 22  User signed up                 │
│  Jan 22  Status → ACCEPTED              │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  Linked Participant                     │
│  [View Participant →]                   │
│                                         │
├─────────────────────────────────────────┤
│  [Close]                                │
└─────────────────────────────────────────┘
```

### Activity Timeline Events
| Event | Description |
|-------|-------------|
| Invite created | By {user} at {timestamp} |
| Email sent | Status: delivered/bounced/pending |
| Email opened | If tracking enabled |
| Reminder sent | Automatic or manual |
| User signed up | Linked to participant |
| Invite expired | Automatic status change |
| Invite revoked | By {user} at {timestamp} |

---

## 50.8 REST API Endpoints

### Invite Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/exams/{examId}/invites` | List all invites for exam |
| POST | `/exams/{examId}/invites` | Create single invite |
| POST | `/exams/{examId}/invites/bulk` | Bulk import invites |
| GET | `/exams/{examId}/invites/{id}` | Get invite details |
| PUT | `/exams/{examId}/invites/{id}` | Update invite |
| DELETE | `/exams/{examId}/invites/{id}` | Delete invite (hard delete) |
| POST | `/exams/{examId}/invites/{id}/revoke` | Revoke invite |
| POST | `/exams/{examId}/invites/{id}/resend` | Resend invite email |
| POST | `/exams/{examId}/invites/bulk-action` | Bulk actions |

### GET /exams/{examId}/invites
**Query Parameters:**
- `status` - Filter by status (PENDING, ACCEPTED, EXPIRED, REVOKED)
- `search` - Search email, phone, or name
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 20, max: 100)
- `sort` - Sort field (email, phone, status, invited_at)
- `order` - Sort order (asc, desc)

**Response:**
```json
{
  "invites": [
    {
      "id": 1,
      "examId": 5,
      "email": "john@example.com",
      "phone": "+1234567890",
      "name": "John Doe",
      "status": "PENDING",
      "invitedAt": "2026-01-20T15:45:00Z",
      "invitedBy": { "id": 1, "name": "Admin" },
      "expiresAt": "2026-02-20T00:00:00Z",
      "acceptedAt": null,
      "participantId": null
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 20,
    "total": 45,
    "totalPages": 3
  }
}
```

### POST /exams/{examId}/invites
**Request Body:**
```json
{
  "email": "john@example.com",
  "phone": "+1234567890",
  "name": "John Doe",
  "expiresAt": "2026-02-20",
  "notes": "VIP candidate",
  "sendEmail": true
}
```

**Response (201):**
```json
{
  "success": true,
  "invite": { ... },
  "emailQueued": true
}
```

### POST /exams/{examId}/invites/bulk
**Request Body:**
```json
{
  "invites": [
    { "email": "a@example.com", "phone": "+111", "name": "A" },
    { "email": "b@example.com", "phone": "+222", "name": "B" }
  ],
  "skipErrors": true,
  "sendEmails": true
}
```

**Response (200):**
```json
{
  "success": true,
  "created": 10,
  "skipped": 2,
  "errors": [
    { "row": 3, "email": "invalid", "error": "Invalid email format" }
  ],
  "emailsQueued": 10
}
```

### POST /exams/{examId}/invites/bulk-action
**Request Body:**
```json
{
  "action": "revoke",
  "inviteIds": [1, 2, 3]
}
```

**Actions:** `send_email`, `resend_email`, `revoke`, `delete`

---

## 50.9 Cron Jobs

### Invite Expiration Check
**Schedule:** Hourly

**Logic:**
```pseudocode
function checkExpiredInvites():
    pendingInvites = db.query(
        "SELECT * FROM examInvite 
         WHERE status = 'PENDING' 
         AND expiresAt IS NOT NULL 
         AND expiresAt < NOW()"
    )
    
    FOR EACH invite IN pendingInvites:
        invite.status = 'EXPIRED'
        db.save(invite)
        
        auditLog.record(
            action: 'INVITE_EXPIRED',
            entityType: 'examInvite',
            entityId: invite.id
        )
```

### Expiring Soon Notification
**Schedule:** Daily at 9 AM

**Logic:**
```pseudocode
function sendExpiringNotifications():
    expiringIn3Days = db.query(
        "SELECT * FROM examInvite 
         WHERE status = 'PENDING' 
         AND expiresAt BETWEEN NOW() AND NOW() + INTERVAL 3 DAY
         AND expiringNotificationSentAt IS NULL"
    )
    
    FOR EACH invite IN expiringIn3Days:
        queueEmail('invite_expiring', invite)
        invite.expiringNotificationSentAt = now()
        db.save(invite)
```

---

## 50.10 Acceptance Criteria

### Invite List
- [ ] List displays all invites for current exam
- [ ] Pagination works correctly (20 per page default)
- [ ] Sorting works on all sortable columns
- [ ] Search filters by email, phone, and name
- [ ] Status filter works correctly
- [ ] Quick actions appear in dropdown menu

### Single Invite
- [ ] Form validates all required fields
- [ ] Duplicate detection shows error before submit
- [ ] Email checkbox controls immediate sending
- [ ] Success adds row to list without refresh

### Bulk Import
- [ ] CSV parsing handles edge cases (quotes, commas)
- [ ] Validation preview shows all issues
- [ ] Import only processes valid rows when "skip errors" checked
- [ ] Error report downloadable as CSV
- [ ] Transaction rolls back on critical failure

### Emails
- [ ] Emails queued in background (non-blocking)
- [ ] Template variables substituted correctly
- [ ] Rate limiting prevents overload (50/batch)
- [ ] Failed emails logged with retry logic

### Bulk Actions
- [ ] Checkbox selection works correctly
- [ ] Actions only available for valid statuses
- [ ] Confirmation required for destructive actions
- [ ] Progress shown during bulk operations

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| Database Schema | [04-database-schema](04-database-schema.md) | examInvite table definition |
| Enums | [06-enums-constants](06-enums-constants.md) | InviteStatus enum |
| Participant Service | [27-participant-service](27-participant-service.md) | Invite validation on signup |
| REST API | [36-rest-api-endpoints](36-rest-api-endpoints.md) | Signup invite validation |
| Email Queue | [31-email-queue](31-email-queue.md) | Email sending infrastructure |
| Email Templates | [33-email-templates](33-email-templates.md) | Template system |
| Cron System | [34-cron-system](34-cron-system.md) | Scheduled jobs |
| Audit Logging | [46-audit-logging](46-audit-logging.md) | Activity tracking |

---

*Next: `51-certificate-templates.md`*
