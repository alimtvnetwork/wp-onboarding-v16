# 35. Admin Dashboard

## Overview
Main admin dashboard showing overview statistics, recent activity, and quick actions.

---

## 35.1 Dashboard Layout

### Structure
- Full-width header with plugin branding
- Stats cards row (4 cards)
- Two-column layout below:
  - Left (60%): Recent activity feed
  - Right (40%): Quick actions + Upcoming deadlines

### Acceptance Criteria:
- [ ] Dashboard is the default landing page for admin
- [ ] Responsive layout adapts to screen size
- [ ] Data loads asynchronously with loading states
- [ ] Refresh button to reload all data

---

## 35.2 Statistics Cards

### Card 1: Total Exams
- Count of all exams
- Breakdown: Active / Draft / Archived
- Trend indicator (vs last month)

### Card 2: Total Participants
- Count of all participants across exams
- Breakdown by status: Active / Completed / Locked
- Trend indicator

### Card 3: Completion Rate
- Percentage of completed participants
- Visual progress ring
- Comparison to previous period

### Card 4: Pending Actions
- Count requiring attention
- Extension requests awaiting approval
- Upcoming deadline warnings

### Acceptance Criteria:
- [ ] Stats calculated from database aggregations
- [ ] Cached for 5 minutes to reduce load
- [ ] Click navigates to relevant list view
- [ ] Trend arrows show increase/decrease

---

## 35.3 Recent Activity Feed

### Activity Types to Display
- Exam created/updated/deleted
- Participant added/status changed
- Checklist item completed
- Extension requested/approved/denied
- Secret key generated/used

### Feed Item Structure
- Icon representing activity type
- Description with linked entities
- Timestamp (relative: "2 hours ago")
- Actor (who performed action)

### Acceptance Criteria:
- [ ] Shows last 20 activities
- [ ] Activities logged via centralized service
- [ ] Filter by activity type available
- [ ] Infinite scroll or "Load more" for history
- [ ] Real-time updates (polling every 30s)

---

## 35.4 Quick Actions Panel

### Actions
1. **Create New Exam** - Opens exam creation modal
2. **Add Participant** - Opens participant form with exam selector
3. **Generate Secret Key** - Quick key generation for selected exam
4. **View Reports** - Navigate to reports section
5. **Plugin Settings** - Navigate to settings

### Acceptance Criteria:
- [ ] Each action has icon and label
- [ ] Hover states indicate interactivity
- [ ] Actions respect RBAC permissions
- [ ] Hidden actions show "Upgrade" for limited roles

---

## 35.5 Upcoming Deadlines Widget

### Display
- List of next 10 upcoming deadlines
- Grouped by: Today / This Week / Later
- Shows: Participant name, Exam title, Deadline type (soft/hard), Date

### Color Coding
- Red: Deadline today or overdue
- Orange: Deadline within 3 days
- Yellow: Deadline within 7 days
- Default: Beyond 7 days

### Acceptance Criteria:
- [ ] Sorted by deadline date ascending
- [ ] Click opens participant detail view
- [ ] "View all" links to filtered participant list
- [ ] Empty state if no upcoming deadlines

---

## 35.6 System Health Panel [OPTIONAL]

### Indicators
- Database connection status
- Cron jobs running status
- Disk space for uploads directory
- Last backup date
- Plugin update available

### Acceptance Criteria:
- [ ] Green/yellow/red status indicators
- [ ] Tooltips explain each status
- [ ] Warning if cron hasn't run in 24 hours
- [ ] Link to settings for each configurable item

---

## 35.7 Welcome Banner (First-Time)

### Display Conditions
- Shown only on first admin visit
- Dismissible with "Don't show again"
- Stored in user meta

### Content
- Brief plugin introduction
- Links to documentation
- Quick setup wizard button
- Video tutorial embed (optional)

### Acceptance Criteria:
- [ ] Only shown to users who haven't dismissed
- [ ] Dismiss persists across sessions
- [ ] Links open in new tab
- [ ] Mobile-friendly layout
