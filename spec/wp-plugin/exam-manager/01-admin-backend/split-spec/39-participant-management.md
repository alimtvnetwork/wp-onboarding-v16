# 37. Participant Management

## Overview
Interface for managing exam participants, tracking progress, and handling extensions.

---

## 37.1 Participant List View

### Columns
| Column | Sortable | Description |
|--------|----------|-------------|
| Checkbox | No | Bulk selection |
| Name | Yes | Participant full name |
| Email | Yes | Email address |
| Exam | Yes | Associated exam title |
| Status | Yes | Current status badge |
| Progress | Yes | Percentage complete |
| Soft Deadline | Yes | Date display |
| Hard Deadline | Yes | Date display |
| Actions | No | Quick actions |

### Acceptance Criteria:
- [ ] List shows participants across all exams by default
- [ ] Can filter to single exam context
- [ ] Progress shown as visual bar + percentage
- [ ] Overdue deadlines highlighted in red

---

## 37.2 Add Participant Form

### Fields
- **Email** (required): Valid email format
- **Name** (required): Full name
- **Exam** (required): Dropdown selector
- **Soft Deadline** (optional): Date picker
- **Hard Deadline** (optional): Date picker
- **Notes** (optional): Internal notes textarea

### Validation Rules
- Email must be unique within the selected exam
- Hard deadline must be after soft deadline
- Both deadlines must be in the future

### Acceptance Criteria:
- [ ] Form validates on submit
- [ ] Email uniqueness checked via API
- [ ] Date pickers respect deadline order
- [ ] Success redirects to participant detail
- [ ] Welcome email sent automatically

---

## 37.3 Bulk Import

### CSV Format
Required columns: email, name
Optional columns: soft_deadline, hard_deadline, notes

### Import Flow
1. Upload CSV file
2. Preview parsed data with validation
3. Show errors/warnings per row
4. Confirm import
5. Display results summary

### Acceptance Criteria:
- [ ] Sample CSV template downloadable
- [ ] Preview shows first 10 rows
- [ ] Duplicate emails flagged as warnings
- [ ] Invalid rows can be excluded
- [ ] Import creates participants in batch
- [ ] Summary shows success/error counts

---

## 37.4 Participant Detail View

### Sections
1. **Header**: Name, email, status badge, quick actions
2. **Progress**: Visual progress with checklist breakdown
3. **Deadlines**: Soft/hard deadline display with countdown
4. **Activity**: Timeline of status changes and completions
5. **Extensions**: History of extension requests

### Acceptance Criteria:
- [ ] All participant data editable inline
- [ ] Progress updates in real-time
- [ ] Deadline countdown shows days/hours remaining
- [ ] Activity feed shows chronological events

---

## 37.5 Extension Management

### Extension Request Display
- Requested date
- Requested additional days
- Reason provided
- Current status (Pending/Approved/Denied)

### Admin Actions
- **Approve**: Set new deadline, optional note
- **Deny**: Required denial reason
- **Modify**: Approve with different days than requested

### Acceptance Criteria:
- [ ] Pending extensions highlighted in dashboard
- [ ] Approval updates participant deadline immediately
- [ ] Email sent on approval/denial
- [ ] Extension history preserved for audit

---

## 37.6 Status Transitions

### Manual Status Changes
Admin can manually change status with reason:
- ACTIVE → PAUSED (temporary hold)
- PAUSED → ACTIVE (resume)
- Any → COMPLETED (manual completion)
- Any → WITHDRAWN (participant dropped out)

### Automatic Transitions
System handles automatically:
- ACTIVE → LOCKED (hard deadline passed)
- Progress 100% → COMPLETED

### Acceptance Criteria:
- [ ] Status change requires confirmation
- [ ] Reason field for manual changes
- [ ] Email notification configurable per transition
- [ ] All transitions logged with timestamp and actor

---

## 37.7 Export Participants

### Export Formats
- CSV (spreadsheet compatible)
- JSON (full data including progress)
- PDF (formatted report)

### Export Options
- All participants or filtered selection
- Include/exclude specific fields
- Date range filter

### Acceptance Criteria:
- [ ] Export respects current filters
- [ ] Large exports processed in background
- [ ] Download link sent via email for large files
- [ ] Export includes metadata (date, filters used)
