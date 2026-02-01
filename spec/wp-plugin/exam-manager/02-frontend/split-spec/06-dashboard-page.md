# 06. Dashboard Page

## Overview
The participant's main exam dashboard showing progress, deadlines, section cards, and quick actions.

---

## 06.1 Route & Protection

| Route | Protection |
|-------|------------|
| `/{slug}/dashboard` | Requires valid session cookie |

If session missing/expired → Redirect to `/{slug}/login`

---

## 06.2 Page Layout

```
┌─────────────────────────────────────────┐
│  Header: Exam Title                     │
│  Navigation: Dashboard | Exam | Logout  │
│  User: Welcome, [Email] | Profile icon  │
└─────────────────────────────────────────┘
│
├─ Section 1: Exam Status & Deadlines
│  ├─ Title: "[Exam Title]"
│  ├─ Status badge: ACTIVE / SOFT_DEADLINE_REACHED / LOCKED / COMPLETED
│  ├─ Progress bar: "5 of 8 sections completed (62%)"
│  │
│  ├─ Deadline Info Box:
│  │  ├─ Soft Deadline: "Jan 27, 1:00 PM (in 2 days, 3 hours)"
│  │  ├─ Hard Deadline: "Jan 31, 1:00 PM (in 6 days, 23 hours)"
│  │  └─ (If extended) Extension Deadline: "Feb 3, 1:00 PM"
│  │
│  └─ CTA Button: "Continue Exam" / "View Results"
│
├─ Section 2: Prerequisites Status
│  ├─ "Prerequisites: 4 of 5 completed"
│  ├─ [+] Show / [-] Hide (expandable)
│  └─ List of prerequisites with checkmarks
│
├─ Section 3: Exam Sections (Main Roadmap)
│  ├─ Card grid layout
│  ├─ Section Card 1: Title, preview, status
│  ├─ Section Card 2: Title, preview, status
│  └─ ...
│
├─ Section 4: In-Exam Checklist (optional)
│  ├─ "Checklist: 3 of 5 items"
│  └─ [+] Show / [-] Hide
│
└─ Footer
```

---

## 06.3 Status Badge Styling

| Status | Color | Text | Badge Class |
|--------|-------|------|-------------|
| ACTIVE | Green | "Active" | `badge-success` |
| SOFT_DEADLINE_REACHED | Amber | "Soft deadline reached" | `badge-warning` |
| HARD_DEADLINE_REACHED | Red | "Hard deadline reached" | `badge-danger` |
| LOCKED | Red | "Locked - Extension needed" | `badge-danger` |
| EXTENDED | Blue | "Extended" | `badge-info` |
| COMPLETED | Green | "Completed ✓" | `badge-success` |

---

## 06.4 Section Cards

### Card Layout
```
┌─────────────────────────────────┐
│  ○ / ⟳ / ✓   Section 1         │
│  Introduction to JavaScript     │
│  "Learn the basics of..."       │
│  [Click to read section]        │
└─────────────────────────────────┘
```

### Card States

| State | Icon | Background | Text |
|-------|------|------------|------|
| Not Started | ○ | Light gray | "Not started" |
| In Progress | ⟳ | Light yellow | "In progress" |
| Completed | ✓ | Light green | "Completed" |
| Locked | 🔒 | Light red | "Locked" |

### Card Behavior
- Click card → Navigate to `/{slug}/section/{sectionNumber}`
- Hover shows full preview text
- Locked cards are non-clickable

---

## 06.5 Progress Display

### Progress Bar
- Visual bar showing completion percentage
- Text: "5 of 8 sections completed (62%)"
- Color gradient based on completion

### Progress Calculation
- Uses formula from [28-participant-progress](../../01-admin-backend/split-spec/28-participant-progress.md)
- Always displays floored percentage
- Never shows 100% unless truly complete

---

## 06.6 Deadline Display

### Deadline Box Content

| Deadline Type | Display Format |
|---------------|----------------|
| Soft | "Soft Deadline: Jan 27, 1:00 PM (in 2 days, 3 hours)" |
| Hard | "Hard Deadline: Jan 31, 1:00 PM (in 6 days)" |
| Extension | "Extension Deadline: Feb 3, 1:00 PM (in 9 days)" |

### Countdown Colors
From [66-shared-constants](../../66-shared-constants.md):

| Time Remaining | Color |
|----------------|-------|
| > 7 days | Green |
| 3-7 days | Yellow |
| 1-3 days | Orange |
| < 24 hours | Red |
| Overdue | Black |

---

## 06.7 Conditional Display

### If Status = LOCKED
- Show alert: "Your hard deadline has passed. Exam is locked."
- Show "Request Extension" button
- Section cards disabled

### If Status = COMPLETED
- Show "Congratulations!" message
- Show completion stats
- "View Results" button instead of "Continue Exam"

---

## 06.8 API Dependencies

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `GET /api/participants/me` | GET | Load participant data |
| `GET /api/exams/{id}/sections` | GET | Load section list |
| `GET /api/participants/me/progress` | GET | Load progress data |

---

## 06.9 Acceptance Criteria

### Display
- [ ] Status badge shows correct status with appropriate color
- [ ] Progress bar shows accurate completion percentage
- [ ] Deadlines display with countdown AND absolute date/time
- [ ] Section cards show correct state (not started/in progress/completed)

### Interaction
- [ ] Click section card navigates to section view
- [ ] Locked sections are not clickable
- [ ] Prerequisites expandable/collapsible
- [ ] Continue Exam navigates to first incomplete section

### Conditional
- [ ] Locked status shows extension request option
- [ ] Completed status shows congratulations message

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Section View** | [07-section-view](07-section-view.md) | Card click destination |
| **Deadline Countdown** | [11-deadline-countdown](11-deadline-countdown.md) | Countdown display |
| **Prerequisites** | [09-prerequisites-display](09-prerequisites-display.md) | Prerequisites section |
| **Progress Tracking** | [28-participant-progress](../../01-admin-backend/split-spec/28-participant-progress.md) | Progress calculation |

---

*Next: `07-section-view.md`*
