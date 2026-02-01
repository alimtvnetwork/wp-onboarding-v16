# 30. Notification Service

## Overview
In-app notification system for real-time alerts and activity updates across the admin interface.

---

## 30.1 Notification Data Structure

### Notification Object (Database: PascalCase, ORM: camelCase)
```
Notification (Database)          ORM Property
├── Id: int                      id
├── UserId: int                  userId
├── Type: NotificationType       type
├── Title: string                title
├── Message: string              message
├── Icon: string (Lucide)        icon
├── Severity: INFO|WARNING|...   severity
├── ActionUrl: string|null       actionUrl
├── ActionLabel: string|null     actionLabel
├── IsRead: boolean              isRead
├── ReadAt: timestamp|null       readAt
├── CreatedAt: timestamp         createdAt
├── ExpiresAt: timestamp|null    expiresAt
└── Metadata: JSON               metadata
```

### NotificationType Enum
```
PARTICIPANT_ENROLLED
PARTICIPANT_COMPLETED
EXTENSION_REQUESTED
EXTENSION_APPROVED
EXTENSION_DENIED
DEADLINE_APPROACHING
DEADLINE_PASSED
EXAM_PUBLISHED
WIKI_UPDATED
SECRET_KEY_EXHAUSTED
SYSTEM_ALERT
```

### Acceptance Criteria:
- [ ] Notifications scoped to user
- [ ] Type determines icon and default styling
- [ ] Expired notifications auto-cleaned

---

## 30.2 Notification Creation

### Service Methods
```
NotificationService
├── create(userId: int, data: NotificationData): Notification
├── createForRole(role: string, data: NotificationData): int[]
├── createForAll(data: NotificationData): int[]
├── markRead(id: int): void
├── markAllRead(userId: int): void
├── delete(id: int): void
├── getUnreadCount(userId: int): int
├── getRecent(userId: int, limit: int = 20): Notification[]
└── cleanExpired(): int
```

### NotificationData Structure
```php
class NotificationData {
    public NotificationType $type;
    public string $title;
    public string $message;
    public Severity $severity = Severity::INFO;
    public ?string $actionUrl = null;
    public ?string $actionLabel = null;
    public ?DateTimeInterface $expiresAt = null;
    public array $metadata = [];
}
```

### Acceptance Criteria:
- [ ] Bulk creation for role-based notifications
- [ ] Expired notifications not returned in queries
- [ ] Metadata supports arbitrary JSON

---

## 30.3 Notification Types

### Type Configuration
| Type | Icon | Default Severity | Expires After |
|------|------|------------------|---------------|
| PARTICIPANT_ENROLLED | user-plus | INFO | 7 days |
| PARTICIPANT_COMPLETED | check-circle | SUCCESS | 7 days |
| EXTENSION_REQUESTED | clock | WARNING | Never |
| EXTENSION_APPROVED | check | SUCCESS | 7 days |
| EXTENSION_DENIED | x | ERROR | 7 days |
| DEADLINE_APPROACHING | alert-triangle | WARNING | After deadline |
| DEADLINE_PASSED | alert-circle | ERROR | 14 days |
| EXAM_PUBLISHED | globe | INFO | 7 days |
| WIKI_UPDATED | file-text | INFO | 3 days |
| SECRET_KEY_EXHAUSTED | key | WARNING | Never |
| SYSTEM_ALERT | bell | varies | Never |

### Acceptance Criteria:
- [ ] Each type has default icon
- [ ] Expiration configurable per type
- [ ] Severity overridable at creation

---

## 30.4 Notification Display

### Bell Icon Badge
- Show red badge with unread count
- Cap display at "99+"
- Update on new notification (SSE/polling)

### Dropdown Panel
```
┌─────────────────────────────────────┐
│ Notifications (3 unread) [Mark all] │
├─────────────────────────────────────┤
│ 🔔 New participant enrolled         │ ← Unread (bold)
│    John Doe - JavaScript Exam       │
│    2 minutes ago            [View →]│
├─────────────────────────────────────┤
│ ⚠️ Extension request pending        │ ← Unread (bold)
│    Jane Smith requests 7 days       │
│    1 hour ago              [Review→]│
├─────────────────────────────────────┤
│ ✓ Participant completed exam        │ ← Read (dimmed)
│    Alex Johnson - React Exam        │
│    3 hours ago                      │
├─────────────────────────────────────┤
│           [View All Notifications]  │
└─────────────────────────────────────┘
```

### Acceptance Criteria:
- [ ] Unread notifications visually distinct
- [ ] Action buttons link to relevant pages
- [ ] Relative timestamps update dynamically
- [ ] "Mark all as read" clears badge

---

## 30.5 Notification Preferences

### User Preferences
```
NotificationPreferences
├── Email Notifications
│   ├── Extension Requests: ON/OFF
│   ├── Deadline Alerts: ON/OFF
│   └── System Updates: ON/OFF
├── In-App Notifications
│   ├── Show for: ALL / IMPORTANT / NONE
│   └── Sound: ON/OFF
└── Digest Settings
    ├── Frequency: IMMEDIATE / DAILY / WEEKLY
    └── Time: 09:00 AM
```

### Acceptance Criteria:
- [ ] Per-user preference storage
- [ ] Email notifications respect preferences
- [ ] Digest batches notifications

---

## 30.6 Real-Time Updates

### Polling Strategy (Default)
- Poll every 30 seconds when tab active
- Pause polling when tab hidden
- Resume on tab focus

### SSE (Server-Sent Events) Alternative
```
GET /api/notifications/stream

event: notification
data: {"Id": 123, "Type": "EXTENSION_REQUESTED", ...}

event: count
data: {"unread": 5}
```

### Acceptance Criteria:
- [ ] Polling configurable interval
- [ ] SSE graceful fallback
- [ ] Connection auto-reconnect

---

## 30.7 Notification Actions

### Action Types
| Action | Effect |
|--------|--------|
| Click notification | Navigate to ActionUrl, mark read |
| Click X | Dismiss (delete) |
| Click "Mark all read" | Mark all as read |
| Hover | Show full message (if truncated) |

### Keyboard Navigation
- `Escape` - Close dropdown
- `Arrow Down/Up` - Navigate items
- `Enter` - Activate current item
- `Delete` - Dismiss current item

### Acceptance Criteria:
- [ ] Actions logged to audit
- [ ] Keyboard fully functional
- [ ] Touch-friendly on mobile

---

## 30.8 Database Schema

```sql
CREATE TABLE Notification (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    UserId INTEGER NOT NULL,
    Type VARCHAR(50) NOT NULL,
    Title VARCHAR(255) NOT NULL,
    Message TEXT NOT NULL,
    Severity VARCHAR(20) NOT NULL DEFAULT 'INFO',
    EntityType VARCHAR(50) DEFAULT NULL,
    EntityId INTEGER DEFAULT NULL,
    ActionUrl VARCHAR(500) DEFAULT NULL,
    IsRead BOOLEAN NOT NULL DEFAULT 0,
    ReadAt DATETIME DEFAULT NULL,
    ExpiresAt DATETIME DEFAULT NULL,
    Metadata TEXT DEFAULT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IX_Notification_UserId ON Notification(UserId);
CREATE INDEX IX_Notification_IsRead ON Notification(IsRead);
CREATE INDEX IX_Notification_Type ON Notification(Type);
CREATE INDEX IX_Notification_CreatedAt ON Notification(CreatedAt);
CREATE INDEX IX_Notification_ExpiresAt ON Notification(ExpiresAt);
```

### Acceptance Criteria:
- [ ] Indexes optimize common queries
- [ ] Expired cleanup via cron
- [ ] EntityType + EntityId for linking

---

## 30.9 API Endpoints

### GET /api/notifications
Query parameters:
- `unreadOnly`: boolean (default: false)
- `limit`: int (default: 20, max: 100)
- `offset`: int (default: 0)

Response:
```json
{
    "success": true,
    "data": {
        "notifications": [...],
        "unreadCount": 3,
        "total": 45
    }
}
```

### POST /api/notifications/{id}/read
Mark single notification as read.

### POST /api/notifications/read-all
Mark all notifications as read.

### DELETE /api/notifications/{id}
Delete (dismiss) a notification.

### Acceptance Criteria:
- [ ] Pagination support
- [ ] Unread count in all responses
- [ ] Rate limiting on endpoints

---

## 30.10 Testing

### Test Cases
```php
function testNotificationCreation(): void {
    $service = new NotificationService();
    
    $notification = $service->create(1, new NotificationData(
        type: NotificationType::EXTENSION_REQUESTED,
        title: 'Extension Request',
        message: 'John Doe requests 7 days extension'
    ));
    
    $this->assertNotNull($notification->Id);
    $this->assertEquals(false, $notification->IsRead);
}

function testMarkAllRead(): void {
    // Create 3 unread notifications
    $service->create(1, ...);
    $service->create(1, ...);
    $service->create(1, ...);
    
    $this->assertEquals(3, $service->getUnreadCount(1));
    
    $service->markAllRead(1);
    
    $this->assertEquals(0, $service->getUnreadCount(1));
}

function testExpiredNotificationsExcluded(): void {
    $service->create(1, new NotificationData(
        type: NotificationType::SYSTEM_ALERT,
        title: 'Expired',
        message: 'This expired',
        expiresAt: new DateTime('-1 day')
    ));
    
    $recent = $service->getRecent(1);
    $this->assertCount(0, $recent);
}
```

### Acceptance Criteria:
- [ ] Creation, read, delete tested
- [ ] Expiration logic verified
- [ ] Bulk operations tested
