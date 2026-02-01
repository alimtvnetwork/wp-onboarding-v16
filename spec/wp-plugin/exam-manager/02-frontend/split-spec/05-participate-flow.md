# 05. Participate Flow

## Overview
When an authenticated user visits a new exam they haven't joined yet, they see a "Participate" button instead of signup/login forms. This flow handles joining a new exam while already logged in.

---

## 05.1 Trigger Conditions

User sees "Participate" button when:
1. User has valid session cookie (from another exam)
2. User is NOT already a participant in THIS exam
3. Exam allows new participants

---

## 05.2 Landing Page State (Authenticated, Not Participating)

```
┌─────────────────────────────────────────┐
│  Header: [New Exam Title]               │
│  "You're logged in as john@example.com" │
└─────────────────────────────────────────┘
│
├─ Exam Overview (title, description, deadlines)
│
├─ Prerequisites Overview (read-only)
│
├─ Action Section:
│  ├─ "You're logged in as john@example.com"
│  ├─ [    Participate (Primary Button)   ]
│  └─ [Logout] link
│
└─ Footer
```

---

## 05.3 Confirmation Dialog

When user clicks "Participate":

```
┌─────────────────────────────────────────────┐
│  Join "[Exam Title]"                        │
│                                             │
│  You are about to participate in this exam. │
│  Please confirm your details below.         │
│                                             │
│  Email: john@example.com (read-only)        │
│  LinkedIn: [___________________________]    │
│            (editable, may be pre-filled)    │
│                                             │
│  [✓] I confirm I want to participate        │
│                                             │
│  [Cancel]              [Confirm & Join]     │
└─────────────────────────────────────────────┘
```

### Dialog Elements

| Element | Type | Behavior |
|---------|------|----------|
| Email | Text (read-only) | Pre-filled from session |
| LinkedIn URL | Input (editable) | May be pre-filled from previous exam |
| Confirmation Checkbox | Checkbox | Required to proceed |
| Cancel Button | Secondary | Closes dialog |
| Confirm & Join Button | Primary | Submits participation |

---

## 05.4 Validation Rules

| Field | Validation | Error |
|-------|------------|-------|
| LinkedIn URL | Valid URL format (if provided) | "Enter a valid LinkedIn URL" |
| Confirmation Checkbox | Must be checked | "Please confirm to proceed" |

---

## 05.5 Submit Behavior

### API Call

```
POST /api/participate
Content-Type: application/json

{
  "examId": 12,
  "linkedInUrl": "https://linkedin.com/in/johndoe"
}
```

Note: `email` is taken from session on backend, not sent from frontend.

### Response Handling

| Status | Response | Action |
|--------|----------|--------|
| 200 | `{success: true, participantId: 456, redirectUrl: "/{slug}/dashboard"}` | Redirect to new exam dashboard |
| 400 | `{success: false, error: "Already participating"}` | Show error in dialog |
| 401 | Session invalid | Redirect to login |
| 500 | Server error | Show generic error |

---

## 05.6 User Flow (Step-by-Step)

1. Authenticated user visits `domain.com/{new-slug}`
2. Frontend checks session cookie → Valid
3. API call to check participation → NOT participating
4. Display landing page with "Participate" button
5. User clicks "Participate"
6. **Confirmation Dialog opens**
7. User sees pre-filled email (read-only)
8. User enters/confirms LinkedIn URL
9. User checks confirmation checkbox
10. User clicks "Confirm & Join"
11. Frontend validates:
    - Checkbox checked ✓
    - LinkedIn URL valid (if provided) ✓
12. If validation fails: Show inline errors
13. If validation passes: POST to `/api/participate`
14. Show loading: "Joining exam..."
15. Backend:
    - Creates participant record
    - Links to existing user identity
    - Calculates deadlines from exam settings
    - Sets status to ACTIVE
16. On success:
    - Log `participateConfirmed`
    - Close dialog
    - Redirect to `/{new-slug}/dashboard`
    - Show: "Welcome to [Exam Title]!"
17. On failure:
    - Show error in dialog
    - Allow retry or close

**Duration**: 1-2 minutes

---

## 05.7 Backend Creates

When participation is confirmed, backend creates:

| Field | Value |
|-------|-------|
| `userId` | From authenticated session |
| `examId` | Target exam ID |
| `email` | From session |
| `linkedInUrl` | From dialog input |
| `status` | `ACTIVE` |
| `progressPercent` | `0` |
| `softDeadlineDate` | Calculated from exam settings |
| `hardDeadlineDate` | Calculated from exam settings |
| `signupDate` | Current timestamp |

---

## 05.8 Logged Events

| Event | Trigger | Details |
|-------|---------|---------|
| `participateLandingViewed` | Page loads with Participate state | `{examId}` |
| `participateDialogOpened` | User clicks Participate button | `{examId}` |
| `participateConfirmed` | Successful enrollment | `{examId, linkedInUrl}` |
| `participateFailed` | Enrollment failed | `{examId, reason}` |

---

## 05.9 UI States

### Landing State
- "Participate" button enabled
- Email displayed (from session)

### Dialog Open State
- Modal overlay visible
- Email read-only
- LinkedIn editable
- Confirm button disabled until checkbox checked

### Loading State
- "Confirm & Join" shows spinner
- Text: "Joining exam..."
- All inputs disabled

### Success State
- Dialog closes
- Redirect to new dashboard
- Toast: "Welcome to [Exam Title]!"

### Error State
- Error message in dialog
- Inputs re-enabled
- Can retry or cancel

---

## 05.10 API Dependencies

| Endpoint | Method | Backend Spec |
|----------|--------|--------------|
| `GET /api/participants/check` | GET | [27-participant-service](../../01-admin-backend/split-spec/27-participant-service.md) |
| `POST /api/participate` | POST | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) |
| `POST /api/log-event` | POST | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) |

---

## 05.11 Acceptance Criteria

- [ ] Authenticated users see "Participate" button (not signup form)
- [ ] Email is pre-filled and read-only in confirmation dialog
- [ ] LinkedIn URL is editable (may be pre-filled)
- [ ] Confirmation checkbox is required
- [ ] Submit creates new participant record
- [ ] New deadlines are calculated from exam settings
- [ ] User redirected to new exam's dashboard
- [ ] All events logged correctly

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Landing Page** | [02-public-landing-page](02-public-landing-page.md) | State detection |
| **Dashboard** | [06-dashboard-page](06-dashboard-page.md) | Redirect target |
| **Backend Participant** | [27-participant-service](../../01-admin-backend/split-spec/27-participant-service.md) | Creates record |
| **Deadline Calculation** | [29-deadline-engine](../../01-admin-backend/split-spec/29-deadline-engine.md) | Deadline setup |

---

*Next: `06-dashboard-page.md`*
