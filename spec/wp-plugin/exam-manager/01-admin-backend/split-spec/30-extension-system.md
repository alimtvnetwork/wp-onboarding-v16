# 28. Extension System

## Overview
Workflow for participants to request deadline extensions and administrators to approve/deny them.

---

## 28.1 Extension Request Form

### Participant-Facing Fields
- **Additional Days Requested** (required): Integer, 1-30
  - Note: Extensions >30 days require admin manual override
- **Reason** (required): Textarea, 50-1000 characters
  - Minimum 50 characters enforced (frontend and backend)
- **Supporting Documentation** (optional): File upload
  - **Allowed Formats:** PDF, DOC, DOCX, PNG, JPG
  - **Max File Size:** 5 MB per file
  - **Max Files:** 3
  - **Storage:** Secure uploads directory with randomized filenames

### Validation Rules
- Cannot request extension if already past hard deadline + grace period
- Cannot have more than 2 pending requests simultaneously
- Minimum 50 characters for reason (synchronized with frontend)
- File validation enforced on both client and server

### Acceptance Criteria:
- [ ] Form accessible from participant dashboard
- [ ] Current deadline displayed for context
- [ ] Character counter for reason field (shows 50 min)
- [ ] File type and size validation with clear error messages
- [ ] Confirmation before submission
- [ ] Email confirmation sent to participant

---

## 28.2 Extension Request Data

### ExtensionRequest Object
```
ExtensionRequest
├── id: int
├── participant_id: int
├── exam_id: int
├── requested_days: int
├── reason: text
├── attachments: FileReference[]
├── status: PENDING | APPROVED | DENIED | EXPIRED
├── requested_at: timestamp
├── reviewed_at: timestamp|null
├── reviewed_by: int|null (admin user ID)
├── admin_notes: text|null
├── granted_days: int|null (may differ from requested)
└── denial_reason: text|null
```

### Acceptance Criteria:
- [ ] All fields properly indexed
- [ ] Soft delete for audit preservation
- [ ] Attachments stored securely
- [ ] Status changes logged

---

## 28.3 Admin Review Interface

### Request Queue
- List of all pending requests across exams
- Sortable by: requested date, exam, participant
- Filterable by: exam, date range, urgency

### Review Panel
- Participant details and current progress
- Original and current deadlines
- Request details and attachments
- Previous extensions for this participant
- Quick action buttons

### Acceptance Criteria:
- [ ] Pending count shown in admin dashboard
- [ ] Email notification to admins for new requests
- [ ] Batch review for multiple requests
- [ ] Assignment to specific admin (optional)

---

## 28.4 Approval Workflow

### Approve Action
1. Admin clicks "Approve"
2. Modal shows:
   - Requested days (pre-filled)
   - Granted days (editable)
   - New deadline preview
   - Optional note to participant
3. On confirm:
   - Update participant deadline
   - Mark request as APPROVED
   - Send approval email
   - Log action

### Acceptance Criteria:
- [ ] Can approve with different days than requested
- [ ] New deadline calculated and shown before confirm
- [ ] Approval email includes new deadline
- [ ] Cannot approve if would set deadline in past

---

## 28.5 Denial Workflow

### Deny Action
1. Admin clicks "Deny"
2. Modal shows:
   - Denial reason (required)
   - Suggestion for participant (optional)
3. On confirm:
   - Mark request as DENIED
   - Send denial email with reason
   - Log action

### Acceptance Criteria:
- [ ] Denial reason required (min 20 characters)
- [ ] Denial email is respectful and constructive
- [ ] Participant can submit new request after denial
- [ ] Denial does not affect current deadline

---

## 28.6 Extension History

### Participant View
- List of all their extension requests
- Status of each with dates
- Granted vs requested days comparison

### Admin View
- Full extension history per participant
- Aggregate stats: total extensions granted
- Pattern detection (frequent requesters)

### Acceptance Criteria:
- [ ] History sorted by date descending
- [ ] Visual timeline of extensions
- [ ] Export extension data for reporting
- [ ] Privacy: participants see only their own history

---

## 28.7 Auto-Expiration

### Pending Request Expiration
- Requests auto-expire if not reviewed within configurable period
- Default: 7 days
- Expired requests marked as EXPIRED status

### Cron Job
Runs daily to:
1. Find pending requests older than expiration period
2. Mark as EXPIRED
3. Notify participant of expiration
4. Optionally notify admins of unreviewed requests

### Acceptance Criteria:
- [ ] Expiration period configurable in settings
- [ ] Expired requests can be manually reviewed
- [ ] Warning to admins before expiration
- [ ] Participant can resubmit after expiration

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Deadline Engine** | [29-deadline-engine](29-deadline-engine.md) | Extension calculation (§27.3), applies approved days |
| **Deadline Flow Diagram** | [diagrams/04-deadline-calculation-flow](../diagrams/04-deadline-calculation-flow.md) | Visual extension flow |
| **Participant Service** | [27-participant-service](27-participant-service.md) | Participant status management |
| **Participant Management UI** | [39-participant-management](39-participant-management.md) | Admin review interface |
| **Enums** | [06-enums-constants](06-enums-constants.md) | `ExtensionStatus` enum |
| **Database Schema** | [04-database-schema](04-database-schema.md) | `extensionRequest` table |
| **Shared Constants** | [66-shared-constants](../../66-shared-constants.md) | File upload limits (5MB, PDF/DOC/DOCX) |
| **Email Templates** | [33-email-templates](33-email-templates.md) | Approval/denial notification templates |
| **Notification Service** | [32-notification-service](32-notification-service.md) | Admin alerts for new requests |
| **Cron System** | [34-cron-system](34-cron-system.md) | Auto-expiration of pending requests |
| **Audit Logging** | [46-audit-logging](46-audit-logging.md) | Extension request lifecycle events |

### Key Algorithm References
- **Extension Calculation**: See [29-deadline-engine](29-deadline-engine.md) §27.3 `applyExtension()` - extends from ORIGINAL hard deadline
- **Multiple Extensions**: See [29-deadline-engine](29-deadline-engine.md) §27.3 `applyAdditionalExtension()` - extends from CURRENT extension deadline
- **Status Priority**: See [27-participant-service](27-participant-service.md) §25.8 `higherPriorityStatus()`

---

*Next: `31-email-queue.md`*
