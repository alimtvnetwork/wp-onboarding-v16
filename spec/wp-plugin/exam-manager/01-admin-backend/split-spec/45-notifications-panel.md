# 43. Notifications Panel

## Overview
In-app notification system for admins and participants.

---

## 43.1 Notification Types

### Admin Notifications
- New participant registered
- Extension request submitted
- Participant completed exam
- Hard deadline approaching (batch)
- System alerts (errors, maintenance)

### Participant Notifications
- Deadline reminders
- Extension request decision
- New content added to exam
- Certificate ready

### Acceptance Criteria:
- [ ] Notification types configurable
- [ ] Can enable/disable per type
- [ ] Frequency limits to prevent spam
- [ ] Priority levels (info, warning, urgent)

---

## 43.2 Notification Bell UI

### Bell Icon
- Located in admin header
- Badge shows unread count
- Animated on new notification
- Click opens dropdown panel

### Dropdown Panel
- List of recent notifications
- Unread highlighted
- Mark as read on click
- "Mark all as read" action
- "View all" link to full page

### Acceptance Criteria:
- [ ] Bell visible on all admin pages
- [ ] Badge updates in real-time
- [ ] Panel shows last 10 notifications
- [ ] Smooth open/close animation

---

## 43.3 Notification List Page

### Full List View
- Paginated list of all notifications
- Filter by type
- Filter by read/unread
- Date range filter
- Bulk actions (mark read, delete)

### Notification Item Display
- Icon by type
- Title and message
- Timestamp (relative)
- Action button if applicable
- Read/unread indicator

### Acceptance Criteria:
- [ ] Infinite scroll or pagination
- [ ] Filters persist in URL
- [ ] Bulk selection with checkboxes
- [ ] Click navigates to related item

---

## 43.4 Real-Time Updates

### Update Mechanism
- Polling every 30 seconds (fallback)
- WebSocket connection if available
- Push notification support (future)

### New Notification Behavior
- Sound (optional, configurable)
- Browser notification (with permission)
- Bell badge updates immediately
- Toast notification for urgent items

### Acceptance Criteria:
- [ ] New notifications appear without refresh
- [ ] Sound can be disabled
- [ ] Browser permission requested appropriately
- [ ] Works with browser tab in background

---

## 43.5 Notification Preferences

### User Settings
- Enable/disable in-app notifications
- Enable/disable email notifications
- Enable/disable browser notifications
- Per-type preferences

### Quiet Hours
- Define hours to suppress notifications
- Still delivered, just not alerted
- Respect user timezone

### Acceptance Criteria:
- [ ] Preferences stored per user
- [ ] Default preferences configurable
- [ ] Quiet hours respected for alerts
- [ ] Email digest option (daily/weekly)

---

## 43.6 Notification Database

### Storage
- Notification table with:
  - Type
  - Title
  - Message
  - Related entity (polymorphic)
  - Read status
  - Created timestamp
  - Read timestamp

### Cleanup
- Archive read notifications older than 30 days
- Delete archived after 90 days
- Bulk delete by type available

### Acceptance Criteria:
- [ ] Notifications queryable by user
- [ ] Read status toggleable
- [ ] Related entity clickable
- [ ] Cleanup runs via cron

---

## 43.7 Notification Templates

### Template Structure
- Title template with variables
- Body template with variables
- Icon/color by type
- Action URL template

### Variables Available
- `{{participantName}}`
- `{{examTitle}}`
- `{{deadline}}`
- `{{adminName}}`
- `{{count}}` (for batch notifications)

### Acceptance Criteria:
- [ ] Templates editable by admin
- [ ] Variable substitution works correctly
- [ ] Preview available before save
- [ ] Default templates restorable
