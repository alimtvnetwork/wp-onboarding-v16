# 41. Public Exam View

## Overview
Frontend interface for participants accessing exams via secret key URLs.

---

## 41.1 Access Flow

### URL Structure
`/{exam-slug}/{secret-key}`

### Access Validation
1. Validate secret key exists and is active
2. Check key has not expired
3. Check usage limit not exceeded
4. Log access attempt (IP hash, user agent, referrer)

### Acceptance Criteria:
- [ ] Invalid key shows friendly error message
- [ ] Expired key shows expiration message
- [ ] Exhausted key shows limit reached message
- [ ] Valid key grants immediate access
- [ ] No login or signup required

---

## 41.2 Exam Landing Page

### Header Section
- Exam title (H1)
- Exam description (rendered Markdown)
- Progress indicator (if returning participant)
- Last accessed timestamp

### Content Navigation
- Table of contents (generated from H2 headings)
- Expandable/collapsible sections
- Scroll position remembered

### Acceptance Criteria:
- [ ] Markdown rendered with proper styling
- [ ] Code blocks syntax highlighted
- [ ] Images lazy loaded
- [ ] Mobile-responsive layout
- [ ] Print-friendly styles available

---

## 41.3 Progress Tracking

### Checklist Display
- Grouped by category (PRE, MAIN, POST)
- Checkbox for each item
- Completion timestamp shown
- Progress percentage updated in real-time

### Progress Persistence
- Save progress to database on each completion
- Sync across devices using tracking cookie
- Offline queue for poor connectivity

### Acceptance Criteria:
- [ ] Checkbox state persists on page refresh
- [ ] Progress bar updates immediately
- [ ] Completion triggers confetti animation (optional)
- [ ] Undo available for 5 seconds after check

---

## 41.4 Prerequisites Section

### Display Requirements
- List all prerequisite items
- Show type (Video, Link, Rubric, Note)
- Mark as viewed/completed
- Block main content until prerequisites done (configurable)

### Video Prerequisites
- Embedded video player
- Track watch progress
- Mark complete when 90% watched

### Acceptance Criteria:
- [ ] Prerequisites clearly separated from main content
- [ ] Video progress tracked accurately
- [ ] External links open in new tab
- [ ] Blocking behavior configurable per exam

---

## 41.5 Deadline Display

### Countdown Timer
- Days, hours, minutes remaining
- Different styling for soft vs hard deadline
- Warning colors as deadline approaches

### Deadline States
- **Green**: More than 7 days remaining
- **Yellow**: 3-7 days remaining
- **Orange**: 1-3 days remaining
- **Red**: Less than 24 hours or overdue

### Acceptance Criteria:
- [ ] Timer updates every minute
- [ ] Timezone clearly displayed
- [ ] Overdue state shows locked message
- [ ] Extension request button visible when allowed

---

## 41.6 Extension Request Form

### Form Fields
- Days requested (dropdown: 1-14)
- Reason for extension (required textarea)
- Acknowledgment checkbox

### Submission Flow
1. Validate form
2. Submit request to API
3. Show confirmation message
4. Disable form until decision made

### Acceptance Criteria:
- [ ] Form only visible when extensions enabled
- [ ] Previous requests shown with status
- [ ] Pending request prevents new submission
- [ ] Email notification sent to admins

---

## 41.7 Mobile Experience

### Responsive Considerations
- Single column layout on mobile
- Collapsible navigation
- Touch-friendly checkboxes
- Swipe gestures for navigation

### Offline Support
- Service worker for basic offline access
- Queue checklist changes when offline
- Sync when connection restored
- Clear offline indicator

### Acceptance Criteria:
- [ ] Usable on 320px width screens
- [ ] No horizontal scrolling required
- [ ] Touch targets minimum 44px
- [ ] Offline mode clearly indicated
