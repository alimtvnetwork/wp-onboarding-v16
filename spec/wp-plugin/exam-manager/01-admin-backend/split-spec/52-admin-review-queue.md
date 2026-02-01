# 52 - Admin Review Queue

## Overview

Admin dashboard component for reviewing flagged participant submissions. Provides filtering, bulk actions, and detailed review workflows for submissions that triggered the `FLAG_FOR_REVIEW` validation mode.

---

## Dependencies

- `19-exam-checklists-tab.md` (submission types, validation modes)
- `06-enums-constants.md` (SubmissionReviewStatus, SubmissionType)
- `04-database-schema.md` (participantChecklist table)
- `37-admin-dashboard.md` (parent navigation)
- `46-audit-logging.md` (review actions logged)

---

## 52.1 Queue Overview Dashboard

### Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ 📋 Submission Review Queue                           [↻ Refresh] [⚙️]  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐        │
│ │   Pending   │ │   Today     │ │  Avg Time   │ │   Oldest    │        │
│ │     47      │ │     12      │ │   4.2 hrs   │ │   3 days    │        │
│ │   ▲ 8%      │ │   ▼ 15%     │ │   ▼ 12%     │ │             │        │
│ └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘        │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│ Filters:                                                                │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐    │
│ │ All Exams  ▼ │ │ All Types  ▼ │ │ All Status ▼ │ │ Date Range ▼ │    │
│ └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘    │
│                                                                         │
│ ┌──────────────────────────────────────────────────┐                    │
│ │ 🔍 Search participant name or email...           │                    │
│ └──────────────────────────────────────────────────┘                    │
│                                                                         │
│ ☑ Select All (3 selected)    [✓ Approve] [✗ Reject] [⟳ Request Resubmit]│
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ ┌───────────────────────────────────────────────────────────────────┐   │
│ │ ☑ │ Participant        │ Exam           │ Item        │ Type  │ ⏱ │   │
│ ├───┼───────────────────┼────────────────┼─────────────┼───────┼───┤   │
│ │ ☑ │ john@example.com   │ React Basics   │ GitHub URL  │ URL   │ 2h│   │
│ │ ☐ │ jane@example.com   │ React Basics   │ Project Doc │ FILE  │ 4h│   │
│ │ ☑ │ bob@example.com    │ Node Advanced  │ Description │ TEXT  │ 1d│   │
│ │ ☐ │ alice@example.com  │ Python Intro   │ Video Demo  │ VIDEO │ 1d│   │
│ │ ☑ │ mike@example.com   │ React Basics   │ GitHub URL  │ URL   │ 2d│   │
│ └───────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│ Showing 1-20 of 47                              [◀ Prev] [1] [2] [3] [▶]│
└─────────────────────────────────────────────────────────────────────────┘
```

### Stats Cards

| Metric | Description | Calculation |
|--------|-------------|-------------|
| Pending | Total awaiting review | `COUNT WHERE reviewStatus = 'PENDING'` |
| Today | Submitted today | Submitted in last 24 hours |
| Avg Time | Average review time | Avg(reviewedAt - submittedAt) |
| Oldest | Oldest pending item | Max age of pending items |

### Filters

| Filter | Options | Default |
|--------|---------|---------|
| Exam | All exams + dropdown list | All Exams |
| Submission Type | All + each SubmissionType | All Types |
| Review Status | PENDING, APPROVED, REJECTED, NEEDS_RESUBMIT | PENDING |
| Date Range | Today, Last 7 days, Last 30 days, Custom | Last 7 days |
| Search | Free text search | Empty |

---

## 52.2 Queue Table Columns

### Column Definitions

| Column | Width | Sortable | Description |
|--------|-------|----------|-------------|
| Checkbox | 40px | No | Bulk selection |
| Participant | 200px | Yes | Name + email |
| Exam | 150px | Yes | Exam title |
| Item | 180px | Yes | Checklist item title |
| Type | 80px | Yes | Submission type icon + label |
| Value Preview | 200px | No | Truncated submission value |
| Status | 100px | Yes | Review status badge |
| Age | 60px | Yes | Time since submission |
| Actions | 100px | No | Quick action buttons |

### Row States

| State | Visual |
|-------|--------|
| Selected | Light primary background |
| Hover | Light muted background |
| Urgent (>48h) | Left border warning color |
| Critical (>7d) | Left border error color |

### Value Preview by Type

| Type | Preview Format |
|------|----------------|
| TEXT_SHORT | First 50 chars + ellipsis |
| TEXT_LONG | First 100 chars + ellipsis |
| URL | Domain name only |
| VIDEO_LINK | Platform icon + video title |
| FILE_UPLOAD | File icon + filename |
| SELECT/RADIO | Selected option label |
| MULTISELECT | "3 of 6 selected" format |

---

## 52.3 Submission Detail View

### Modal Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Review Submission                                              [✕]     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ Participant: John Doe (john@example.com)                               │
│ Exam: React Basics                                                      │
│ Checklist Item: Submit your GitHub repository URL                      │
│ Submitted: Jan 25, 2026 at 10:30 AM (2 hours ago)                      │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│ Submission Type: URL                                                    │
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ https://github.com/johndoe/react-project                            │ │
│ │                                                            [↗ Open] │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ Validation Result:                                                      │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ ⚠️ Flagged: URL does not match required pattern                      │ │
│ │    Expected: github.com/username/repository                         │ │
│ │    Received: github.com/johndoe/react-project ✓                     │ │
│ │                                                                      │ │
│ │ Note: Pattern matched but repository appears to be private          │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│ Previous Submissions (if any):                                          │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ #1 - Jan 24, 2026 - REJECTED                                        │ │
│ │     https://github.com/johndoe/old-repo                             │ │
│ │     Admin note: "Repository was deleted"                            │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│ Your Review:                                                            │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ Add a note for the participant (optional):                          │ │
│ │                                                                      │ │
│ │ Repository looks good. Well structured code!                        │ │
│ │                                                                      │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│        [✗ Reject]    [⟳ Request Resubmit]    [✓ Approve]              │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Detail Sections

| Section | Content |
|---------|---------|
| Header | Participant info, exam, item title, timestamp |
| Submission | Full value display with type-specific rendering |
| Validation | Why it was flagged, expected vs received |
| History | Previous submissions and their outcomes |
| Review | Admin note input and action buttons |

### Type-Specific Rendering

| Type | Rendering |
|------|-----------|
| TEXT_SHORT | Full text with validation highlights |
| TEXT_LONG | Scrollable text area, word count |
| URL | Clickable link, preview card, accessibility check |
| VIDEO_LINK | Embedded video player |
| FILE_UPLOAD | Download button, preview (if image/PDF) |
| SELECT/RADIO | Selected option with context |
| MULTISELECT | All selected options listed |

---

## 52.4 Review Actions

### Individual Actions

| Action | Effect | Confirmation |
|--------|--------|--------------|
| Approve | Sets `reviewStatus = 'APPROVED'` | No |
| Reject | Sets `reviewStatus = 'REJECTED'` | Yes, with reason required |
| Request Resubmit | Sets `reviewStatus = 'NEEDS_RESUBMIT'` | Yes, with feedback required |
| Skip | Move to next without action | No |

### Bulk Actions

| Action | Available When | Effect |
|--------|----------------|--------|
| Bulk Approve | 1+ selected | Approve all selected |
| Bulk Reject | 1+ selected | Reject all with shared note |
| Bulk Resubmit | 1+ selected | Request resubmit with shared note |
| Export | 1+ selected | Download CSV of submissions |

### Confirmation Dialogs

**Reject Confirmation:**
```
┌─────────────────────────────────────────────────────┐
│ Reject 3 Submissions?                               │
├─────────────────────────────────────────────────────┤
│ Rejection reason (required):                        │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Submission does not meet requirements...        │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ ☐ Notify participants via email                    │
│                                                     │
│                    [Cancel]    [Reject Selected]    │
└─────────────────────────────────────────────────────┘
```

**Request Resubmit Confirmation:**
```
┌─────────────────────────────────────────────────────┐
│ Request Resubmission for 3 Submissions?             │
├─────────────────────────────────────────────────────┤
│ Feedback for participants (required):               │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Please correct the following issues...          │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ ☑ Notify participants via email                    │
│                                                     │
│               [Cancel]    [Request Resubmit]        │
└─────────────────────────────────────────────────────┘
```

---

## 52.5 Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `j` / `↓` | Move to next row |
| `k` / `↑` | Move to previous row |
| `x` | Toggle row selection |
| `Enter` | Open detail modal |
| `a` | Approve (in detail modal) |
| `r` | Reject (in detail modal) |
| `s` | Request resubmit (in detail modal) |
| `Escape` | Close modal |
| `Shift + A` | Bulk approve selected |
| `?` | Show keyboard shortcuts |

---

## 52.6 Queue Priority & Sorting

### Default Sort

1. Age (oldest first) - prioritize overdue reviews
2. Then by exam (group related items)
3. Then by participant (group by person)

### Priority Indicators

| Priority | Criteria | Visual |
|----------|----------|--------|
| Normal | < 24 hours old | No indicator |
| High | 24-48 hours old | Yellow left border |
| Urgent | 48-168 hours old | Orange left border |
| Critical | > 7 days old | Red left border, badge |

### Sort Options

- Age (oldest/newest)
- Participant (A-Z / Z-A)
- Exam (A-Z / Z-A)
- Submission Type
- Status

---

## 52.7 Review Analytics

### Reviewer Performance Widget

```
┌─────────────────────────────────────────────────────┐
│ Your Review Stats (This Week)                       │
├─────────────────────────────────────────────────────┤
│ ✓ Approved: 45    ✗ Rejected: 12    ⟳ Resubmit: 8  │
│                                                     │
│ Avg. Review Time: 3.2 minutes                       │
│ Reviews Today: 18                                   │
└─────────────────────────────────────────────────────┘
```

### Queue Health Metrics

| Metric | Target | Alert Threshold |
|--------|--------|-----------------|
| Pending items | < 20 | > 50 |
| Avg review time | < 24h | > 48h |
| Oldest pending | < 3 days | > 7 days |
| Daily throughput | > 20 | < 10 |

---

## 52.8 Notification Integration

### Email Notifications to Participants

| Event | Email Template | Content |
|-------|----------------|---------|
| Approved | `submission_approved` | "Your submission has been approved" |
| Rejected | `submission_rejected` | Reason + how to contact support |
| Needs Resubmit | `submission_resubmit` | Feedback + link to resubmit |

### Admin Notifications

| Event | Notification | Severity |
|-------|--------------|----------|
| Queue > 50 items | "Review queue needs attention" | WARNING |
| Item > 7 days old | "Critical: Overdue review" | URGENT |
| Daily digest | "X items reviewed, Y pending" | INFO |

---

## 52.9 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/review-queue` | List pending submissions |
| GET | `/api/admin/review-queue/stats` | Queue statistics |
| GET | `/api/admin/review-queue/{id}` | Submission detail |
| PUT | `/api/admin/submissions/{id}/approve` | Approve submission |
| PUT | `/api/admin/submissions/{id}/reject` | Reject with reason |
| PUT | `/api/admin/submissions/{id}/request-resubmit` | Request resubmit |
| POST | `/api/admin/submissions/bulk-approve` | Bulk approve |
| POST | `/api/admin/submissions/bulk-reject` | Bulk reject |
| POST | `/api/admin/submissions/bulk-resubmit` | Bulk resubmit request |
| GET | `/api/admin/submissions/{id}/history` | Submission history |
| GET | `/api/admin/review-queue/export` | Export to CSV |

### List Query Parameters

```
GET /api/admin/review-queue?
  examId=123&
  submissionType=URL&
  reviewStatus=PENDING&
  dateFrom=2026-01-20&
  dateTo=2026-01-25&
  search=john&
  sortBy=age&
  sortOrder=desc&
  page=1&
  perPage=20
```

### Approve Request

```json
// PUT /api/admin/submissions/{id}/approve
{
  "note": "Looks great, well done!",
  "sendNotification": true
}
```

### Reject Request

```json
// PUT /api/admin/submissions/{id}/reject
{
  "reason": "Repository is private and cannot be accessed.",
  "sendNotification": true
}
```

### Bulk Action Request

```json
// POST /api/admin/submissions/bulk-approve
{
  "submissionIds": [1, 2, 3, 5, 8],
  "note": "Batch approved - all meet requirements",
  "sendNotification": false
}
```

---

## 52.10 Database Queries

### Pending Queue Query

```sql
SELECT 
    pc.id,
    pc.submissionValue,
    pc.submittedAt,
    pc.reviewStatus,
    p.email AS participantEmail,
    p.name AS participantName,
    e.title AS examTitle,
    ec.title AS checklistItemTitle,
    ec.submissionType,
    ec.validationConfig
FROM participantChecklist pc
JOIN participant p ON pc.participantId = p.id
JOIN examChecklist ec ON pc.checklistId = ec.id
JOIN exam e ON ec.examId = e.id
WHERE pc.reviewStatus = 'PENDING'
ORDER BY pc.submittedAt ASC
LIMIT 20 OFFSET 0;
```

### Queue Stats Query

```sql
SELECT
    COUNT(*) FILTER (WHERE reviewStatus = 'PENDING') AS pending,
    COUNT(*) FILTER (WHERE submittedAt > NOW() - INTERVAL '24 hours') AS today,
    AVG(EXTRACT(EPOCH FROM (reviewedAt - submittedAt))) AS avgReviewTime,
    MAX(NOW() - submittedAt) FILTER (WHERE reviewStatus = 'PENDING') AS oldestPending
FROM participantChecklist
WHERE reviewStatus IS NOT NULL;
```

---

## 52.11 Audit Logging

All review actions are logged to the audit system:

| Action | AuditAction Enum | Context |
|--------|------------------|---------|
| Approve | `SUBMISSION_APPROVED` | submissionId, note |
| Reject | `SUBMISSION_REJECTED` | submissionId, reason |
| Request Resubmit | `SUBMISSION_RESUBMIT_REQUESTED` | submissionId, feedback |
| Bulk Approve | `SUBMISSION_BULK_APPROVED` | submissionIds[], note |
| Bulk Reject | `SUBMISSION_BULK_REJECTED` | submissionIds[], reason |
| View Detail | `SUBMISSION_VIEWED` | submissionId |

---

## 52.12 Role Permissions

| Role | Queue Access | Actions |
|------|--------------|---------|
| ADMIN | Full access | All actions |
| EXAM_EDITOR | Own exams only | Approve, Reject, Resubmit |
| EXAMINEE | No access | — |

### EXAM_EDITOR Filtering

```sql
-- Add to WHERE clause for EXAM_EDITOR role
AND e.createdBy = :currentUserId
```

---

## 52.13 Mobile Responsive Layout

### Mobile View (< 768px)

```
┌─────────────────────────────────────┐
│ 📋 Review Queue           [≡] [↻]  │
├─────────────────────────────────────┤
│ Pending: 47 │ Today: 12            │
├─────────────────────────────────────┤
│ [All Exams ▼] [All Types ▼]        │
│ [🔍 Search...]                      │
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ john@example.com          2h ⚠️ │ │
│ │ React Basics • GitHub URL       │ │
│ │ [Approve] [Reject] [Resubmit]   │ │
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │ jane@example.com          4h    │ │
│ │ React Basics • Project Doc      │ │
│ │ [Approve] [Reject] [Resubmit]   │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘
```

### Touch Optimizations

- Swipe right to approve
- Swipe left for reject/resubmit options
- Pull to refresh
- Bottom sheet for detail view

---

## 52.14 Acceptance Criteria

### Queue Display
- [ ] Stats cards show accurate counts
- [ ] Filters work independently and combined
- [ ] Search searches name and email
- [ ] Pagination works correctly
- [ ] Age-based priority colors display

### Review Actions
- [ ] Approve updates status immediately
- [ ] Reject requires reason
- [ ] Resubmit requires feedback
- [ ] Bulk actions work on selected items
- [ ] Confirmation dialogs for destructive actions

### Detail View
- [ ] All submission types render correctly
- [ ] Validation results displayed
- [ ] Previous submissions shown
- [ ] Admin note saves with action
- [ ] Keyboard shortcuts work

### Notifications
- [ ] Email sent when enabled
- [ ] Admin notified of queue health issues
- [ ] Daily digest works

### Permissions
- [ ] EXAM_EDITOR sees only own exams
- [ ] EXAMINEE cannot access
- [ ] ADMIN sees all

### Performance
- [ ] Queue loads < 500ms
- [ ] Bulk actions complete < 2s
- [ ] Search debounced (300ms)
- [ ] Optimistic UI updates

---

## 52.15 Error Handling

| Scenario | Behavior |
|----------|----------|
| Network error on action | Retry button, error toast |
| Submission already reviewed | Refresh queue, info toast |
| Permission denied | Redirect to dashboard |
| Invalid submission ID | 404 message, back button |
| Bulk action partial failure | Show success/fail counts |

---

## Notes

- Review queue is a critical admin workflow - optimize for speed
- Keyboard shortcuts improve reviewer efficiency
- Priority indicators prevent old items from being forgotten
- Audit logging ensures accountability
- Mobile support enables on-the-go reviews
- EXAM_EDITOR filter prevents cross-exam data leakage
