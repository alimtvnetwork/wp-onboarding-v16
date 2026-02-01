# 15. Locked State

## Overview
UI behavior and restrictions when participant's exam is locked due to hard deadline passing or extension expiration.

---

## 15.1 Lock Trigger Conditions

Exam becomes locked when:
1. **Hard deadline passed** - Status → `LOCKED`
2. **Extension expired** - Status → `LOCKED` (re-locked)
3. **Admin manual lock** - Status → `LOCKED`

---

## 15.2 Locked Status Detection

### From API
```json
{
  "participantId": 123,
  "status": "LOCKED",
  "hardDeadlineDate": "2026-01-31T18:00:00Z",
  "extensionDeadlineDate": null,
  "canRequestExtension": true
}
```

### Frontend Check
```javascript
if (participant.status === 'LOCKED') {
  showLockedUI();
}
```

---

## 15.3 Dashboard Locked Display

```
┌─────────────────────────────────────────────┐
│  🔒 Exam Locked                             │
│                                             │
│  Your hard deadline has passed.             │
│  Hard Deadline: Jan 31, 2026 at 1:00 PM     │
│                                             │
│  Progress: 5 of 8 sections completed (62%)  │
│                                             │
│  [Request Extension] (Primary button)       │
│                                             │
│  ⚠ You cannot mark sections while locked   │
└─────────────────────────────────────────────┘
│
├─ Section Cards (all show 🔒 lock icon)
│  ├─ Section 1: ✓ Completed (viewable)
│  ├─ Section 2: ✓ Completed (viewable)
│  ├─ Section 3: 🔒 Locked (viewable, not markable)
│  └─ ...
```

---

## 15.4 Section View Locked Display

When viewing a section in locked state:

```
┌─────────────────────────────────────────────┐
│  🔒 Exam Locked                             │
│  You cannot mark this section as done.      │
│  [Request Extension]                        │
└─────────────────────────────────────────────┘
│
├─ Section Content (still visible)
│
├─ Action Area:
│  └─ [Mark as Done] ← Disabled (grayed out)
│     "Exam locked - request extension to continue"
```

---

## 15.5 Restricted Actions

### Cannot Do When Locked

| Action | Behavior |
|--------|----------|
| Mark section done | Button disabled |
| Mark prerequisite done | Button disabled |
| In-exam checklist items | Checkboxes disabled |
| Submit any progress | API returns 403 |

### Can Still Do When Locked

| Action | Behavior |
|--------|----------|
| View section content | Read-only access |
| Navigate between sections | Works normally |
| View prerequisites | Read-only |
| Request extension | Available |
| Logout | Works normally |

---

## 15.6 Lock Banner

Show persistent banner on all pages when locked:

```
┌─────────────────────────────────────────────┐
│ 🔒 Your exam is locked. Request extension   │
│    to continue. [Request Extension →]       │
└─────────────────────────────────────────────┘
```

### Banner Styling
- Background: Red (`#fee2e2`)
- Icon: Lock emoji or icon
- Link: Goes to extension request page
- Position: Top of content area (below header)
- Dismissible: No (persistent while locked)

---

## 15.7 Extension Unlocking

When extension is approved:

1. Status changes from `LOCKED` to `EXTENDED`
2. Banner updates: "Extension granted! X days remaining"
3. Section marking re-enabled
4. New deadline countdown displayed
5. Progress continues from where left off

### Transition Animation (Optional)
- Lock icon → Unlock animation
- Banner color: Red → Blue
- Toast: "Extension approved! You can continue."

---

## 15.8 Re-Lock After Extension

If extension expires:

1. Status changes from `EXTENDED` to `LOCKED`
2. Same locked UI as initial lock
3. Can request additional extension (if allowed)
4. Message: "Your extension has expired"

---

## 15.9 Action Blocking Logic

### Frontend
```javascript
function canMarkSection(participant) {
  const allowedStatuses = ['ACTIVE', 'SOFT_DEADLINE_REACHED', 'EXTENDED'];
  return allowedStatuses.includes(participant.status);
}

function handleMarkAsDone() {
  if (!canMarkSection(participant)) {
    showLockedMessage();
    return;
  }
  // proceed with marking
}
```

### Backend Validation
- Always validates status before accepting progress
- Returns 403 with `EXAM_LOCKED` code if locked

---

## 15.10 Edge Case: Lock During Session

Scenario: User is reading section, deadline passes

### Detection
- Periodic check for deadline (every 60s)
- Or on any action attempt

### Behavior
1. User clicks "Mark as Done"
2. Backend returns 403 `EXAM_LOCKED`
3. Frontend shows modal:
   ```
   ┌─────────────────────────────────────────┐
   │  ⚠ Deadline Passed                     │
   │                                         │
   │  Your hard deadline has just passed.    │
   │  The exam is now locked.                │
   │                                         │
   │  [Request Extension]   [View Dashboard] │
   └─────────────────────────────────────────┘
   ```
4. Update UI to locked state
5. Log `hardDeadlineBlocked` event

---

## 15.11 Logged Events

| Event | Trigger | Details |
|-------|---------|---------|
| `lockedStateDisplayed` | Lock UI shown | `{examId, reason}` |
| `hardDeadlineBlocked` | Action blocked by lock | `{attemptedAction}` |
| `extensionUnlocked` | Extension approved | `{newDeadline}` |
| `extensionExpired` | Extension expired, re-locked | `{examId}` |

---

## 15.12 Acceptance Criteria

### Display
- [ ] Lock banner shows on all pages
- [ ] Status badge shows "Locked"
- [ ] Lock icon on section cards
- [ ] Extension request button prominent

### Restrictions
- [ ] Mark as Done disabled
- [ ] Prerequisites marking disabled
- [ ] API returns 403 for progress actions

### Content Access
- [ ] Sections still viewable
- [ ] Navigation works
- [ ] Read-only mode functions

### Transition
- [ ] Extension approval unlocks correctly
- [ ] UI updates reflect new state
- [ ] Extension expiry re-locks correctly

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Deadline Countdown** | [11-deadline-countdown](11-deadline-countdown.md) | Trigger timing |
| **Extension Request** | [12-extension-request](12-extension-request.md) | Unlock path |
| **Deadline Engine** | [29-deadline-engine](../../01-admin-backend/split-spec/29-deadline-engine.md) | Lock logic |
| **Status States** | [02-participant-status-states](../../diagrams/02-participant-status-states.md) | State machine |

---

*Next: `16-error-handling.md`*
