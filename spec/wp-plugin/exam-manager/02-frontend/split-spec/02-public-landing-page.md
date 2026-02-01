# 02. Public Landing Page

## Overview
The public-facing exam landing page that displays exam information and provides authentication/participation options based on user state.

---

## 02.1 Route & URL Structure

| Route | Description |
|-------|-------------|
| `/{exam-slug}` | Main landing page for exam |
| `/{exam-slug}?key={secretKey}` | Secret key access (deprecated, use path-based) |
| `/{exam-slug}/{secretKey}` | Secret key access (preferred) |

---

## 02.2 Page Layout

```
┌─────────────────────────────────────────┐
│  Header: Exam Title                     │
│  Navigation: Home | Login (if not auth) │
│              Home | Participate (if auth but not in exam) │
│              Home | Dashboard (if participating) │
└─────────────────────────────────────────┘
│
├─ Section 1: Exam Overview
│  ├─ Title (H1)
│  ├─ Description
│  ├─ Deadline Info (soft, hard)
│  └─ "Prerequisites: X items to complete"
│
├─ Section 2: Prerequisites (Pre-Checklist)
│  ├─ "Complete these before starting:"
│  ├─ Video 1: [Watch video] [YouTube link]
│  ├─ Link 1: [Open article] (opens in new tab)
│  ├─ Checklist item 1: [ ] Mark as done
│  └─ Progress: "3 of 5 prerequisites completed"
│
├─ Section 3: Authentication / Participation
│  ├─ (Content varies by user state - see 02.3)
│
├─ Section 4: Sub-Exams (if any)
│  ├─ "Sub-Exams" heading
│  ├─ Card 1: "Sub-Exam Title" | Status | Progress
│  └─ Card 2: "Sub-Exam Title" | Status | Progress
│
└─ Footer: Info, Contact
```

---

## 02.3 Dynamic State Display

### State 1: Not Authenticated
- Show signup form
- "Already registered? Login here" link
- Prerequisites visible but not interactive

### State 2: Authenticated, NOT Participating
- Show "You're logged in as [Email]"
- Show "Participate" button (opens confirmation dialog)
- "Logout" link

### State 3: Authenticated AND Participating
- Show "Welcome, [Email]"
- Show "Continue Exam" button → Dashboard
- Progress summary: "3 of 8 sections completed"
- "Logout" link

### UI Elements

| Element | Type | Behavior |
|---------|------|----------|
| Exam Title | H1 | Static display |
| Description | Text | Markdown rendered |
| Deadline Box | Card | Shows soft/hard deadlines with countdown |
| Signup Form | Form | See `03-signup-flow.md` |
| Login Link | Link | Navigates to `/{slug}/login` |
| Participate Button | Primary Button | Opens confirmation dialog |
| Continue Exam | Primary Button | Navigates to dashboard |
| Logout | Secondary Button | Clears session, refreshes page |

---

## 02.4 Exam Overview Display

### Displayed Information
- **Title**: From exam metadata
- **Description**: Full markdown-rendered text
- **Deadline Info**: 
  - Soft deadline: "Soft deadline: Jan 27, 1:00 PM"
  - Hard deadline: "Hard deadline: Jan 31, 1:00 PM"
- **Prerequisites Summary**: "X items to complete"

### Deadline Display Rules
| Condition | Display |
|-----------|---------|
| > 7 days away | Date only (no urgency) |
| 3-7 days away | Date + "in X days" |
| < 3 days away | Date + countdown + yellow warning |
| < 24 hours | Date + hours countdown + red warning |

---

## 02.5 API Dependencies

| Endpoint | Method | Purpose | Backend Spec |
|----------|--------|---------|--------------|
| `/api/exams/{slug}` | GET | Load exam metadata | [12-exam-service](../../01-admin-backend/split-spec/12-exam-service.md) |
| `/api/participants/check` | GET | Check participation status | [27-participant-service](../../01-admin-backend/split-spec/27-participant-service.md) |
| `/api/validate-session` | POST | Validate session cookie | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) |

---

## 02.6 Acceptance Criteria

### Display
- [ ] Exam title and description render correctly
- [ ] Deadline information displays with countdown
- [ ] Prerequisites summary shows count
- [ ] Sub-exams display as cards (if present)

### State Detection
- [ ] Correctly detects unauthenticated state
- [ ] Correctly detects authenticated but not participating
- [ ] Correctly detects participating state
- [ ] Displays appropriate UI for each state

### Navigation
- [ ] Login link navigates to login page
- [ ] Continue Exam navigates to dashboard
- [ ] Logout clears session and refreshes

### Logging
- [ ] `pageView` logged with `page: "landing"`
- [ ] State changes logged appropriately

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Signup Flow** | [03-signup-flow](03-signup-flow.md) | Signup form on landing page |
| **Participate Flow** | [05-participate-flow](05-participate-flow.md) | Participate confirmation dialog |
| **Secret Key Access** | [18-secret-key-access](18-secret-key-access.md) | Auto-signup via secret key |
| **Sub-Exams Display** | [10-sub-exams-display](10-sub-exams-display.md) | Child exam cards |

---

*Next: `03-signup-flow.md`*
