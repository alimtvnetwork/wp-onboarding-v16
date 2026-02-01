# 12. Extension Request

## Overview
Form for participants to request deadline extensions after hard deadline has passed, including file upload and admin review workflow.

---

## 12.1 Route & Access

| Route | Protection |
|-------|------------|
| `/{slug}/extend-deadline` | Requires session + exam locked |

### Access Conditions
- User must be authenticated
- Exam must be in locked state (hard deadline passed)
- User must not have exceeded max pending requests (default: 2)

---

## 12.2 Page Layout

```
┌─────────────────────────────────────────┐
│  Header: Exam Title                     │
│  ← Back to Dashboard                    │
└─────────────────────────────────────────┘
│
├─ "Request Extension" heading
│
├─ Info Box:
│  ├─ "Your hard deadline has passed."
│  ├─ "Original Hard Deadline: Jan 31, 1:00 PM"
│  └─ "Progress: 5 of 8 sections completed"
│
├─ Form:
│  ├─ Days needed: [3] (1-30)
│  ├─ Reason: [___________________]
│  │           (min 50 characters)
│  ├─ Supporting document: [Choose File]
│  │   (Optional, PDF/DOC/DOCX/PNG/JPG, max 5MB)
│  └─ [Submit Request]
│
├─ Previous Requests (if any):
│  ├─ Jan 28: 5 days - Pending ⏳
│  ├─ Jan 25: 3 days - Rejected ❌
│  └─ (Show status and dates)
│
└─ Footer
```

---

## 12.3 Form Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Days Needed | number | Yes | 1-30 |
| Reason | textarea | Yes | Min 50 chars, max 1000 |
| Supporting Document | file | No | PDF/DOC/DOCX/PNG/JPG, max 5MB, max 3 files |

---

## 12.4 Validation Rules

### Days Needed
- Minimum: 1 day
- Maximum: 30 days
- Extensions > 30 days require admin manual override

### Reason
- Minimum: 50 characters (synchronized with backend)
- Maximum: 1000 characters
- Character counter shown below field

### File Upload
- **Allowed formats**: PDF, DOC, DOCX, PNG, JPG
- **Max size**: 5 MB per file
- **Max files**: 3
- Show file size and type validation errors

---

## 12.5 Submit Behavior

### API Call
```
POST /api/request-extension
Content-Type: multipart/form-data

{
  "examId": 5,
  "requestedDays": 5,
  "reason": "Had unexpected personal emergency...",
  "attachments": [File, File, ...]
}
```

### Response Handling

| Status | Response | Action |
|--------|----------|--------|
| 200 | `{success: true, requestId: 456}` | Show success, display in Previous |
| 400 | `{error: "Too many pending requests"}` | Show limit error |
| 400 | `{error: "Reason too short"}` | Show validation error |
| 500 | Server error | Show generic error |

---

## 12.6 Extension Status Display

### Status Badges

| Status | Color | Text |
|--------|-------|------|
| PENDING | Orange | "Awaiting admin review" |
| APPROVED | Green | "Approved for X days" |
| DENIED | Red | "Rejected" |
| EXPIRED | Gray | "Request expired" |

### Approved Display
```
Jan 28: 5 days requested → Approved for 3 days ✓
New deadline: Feb 3, 1:00 PM
```

### Rejected Display
```
Jan 25: 3 days requested → Rejected ❌
Reason: "Insufficient justification provided"
```

---

## 12.7 User Flow (Step-by-Step)

1. Participant's hard deadline has passed
2. Participant visits dashboard → Sees "Locked" status
3. Clicks "Request Extension" button
4. Navigates to `/{slug}/extend-deadline`
5. Sees info box with deadline and progress info
6. Fills form:
   - Enters days needed (e.g., 5)
   - Writes reason (min 50 chars)
   - Optionally uploads supporting document
7. Clicks "Submit Request"
8. Frontend validates:
   - Days: 1-30 ✓
   - Reason: 50+ chars ✓
   - File: valid format and size ✓
9. POST to `/api/request-extension`
10. On success:
    - Show "Request submitted!"
    - Add to Previous Requests with "Pending"
    - Log event
11. Admin reviews (external to this flow)
12. Admin approves/rejects
13. Participant receives email notification
14. If approved:
    - Status changes to EXTENDED
    - New deadline calculated
    - Participant can resume exam

---

## 12.8 Previous Requests Section

```
┌─────────────────────────────────────────────┐
│  Previous Requests                          │
├─────────────────────────────────────────────┤
│  📅 Jan 28, 2026                            │
│  Requested: 5 days                          │
│  Status: ⏳ Pending                         │
│  Submitted: 2 hours ago                     │
├─────────────────────────────────────────────┤
│  📅 Jan 25, 2026                            │
│  Requested: 3 days                          │
│  Status: ❌ Rejected                        │
│  Reason: "Insufficient justification"       │
└─────────────────────────────────────────────┘
```

---

## 12.9 API Dependencies

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `POST /api/request-extension` | POST | Submit request |
| `GET /api/participants/me/extension-requests` | GET | List previous |
| `GET /api/participants/me/extension-requests/{id}` | GET | Request details |

---

## 12.10 Logged Events

| Event | Trigger | Details |
|-------|---------|---------|
| `extensionPageViewed` | Page load | `{examId}` |
| `extensionRequested` | Form submitted | `{requestedDays, hasAttachment}` |
| `extensionRequestFailed` | Submission failed | `{reason}` |
| `extensionApprovalReceived` | Email notification | `{approvedDays, newDeadline}` |
| `extensionRejectionReceived` | Email notification | `{reason}` |

---

## 12.11 Acceptance Criteria

### Form
- [ ] Days input validates 1-30 range
- [ ] Reason shows character counter (50 min)
- [ ] File upload validates format and size
- [ ] Submit disabled until all required fields valid

### Submission
- [ ] Request creates successfully
- [ ] Shows in Previous Requests with Pending
- [ ] Error messages clear and actionable

### Previous Requests
- [ ] Shows all previous requests
- [ ] Status badges display correctly
- [ ] Approved shows new deadline
- [ ] Rejected shows reason

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Extension System** | [30-extension-system](../../01-admin-backend/split-spec/30-extension-system.md) | Backend workflow |
| **Deadline Engine** | [29-deadline-engine](../../01-admin-backend/split-spec/29-deadline-engine.md) | Extension calculation |
| **Locked State** | [15-locked-state](15-locked-state.md) | Trigger condition |
| **Shared Constants** | [66-shared-constants](../../66-shared-constants.md) | File limits |

---

*Next: `13-session-management.md`*
