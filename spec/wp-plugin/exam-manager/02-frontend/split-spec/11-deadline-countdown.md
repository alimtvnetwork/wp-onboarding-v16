# 11. Deadline Countdown

## Overview
Real-time countdown display for soft, hard, and extension deadlines with color-coded urgency indicators.

---

## 11.1 Deadline Types

| Type | Purpose | Blocks Access? |
|------|---------|----------------|
| **Soft Deadline** | Warning threshold | No |
| **Hard Deadline** | Absolute cutoff | Yes |
| **Extension Deadline** | Admin-granted extra time | Yes (when expires) |

---

## 11.2 Countdown Display Format

### Standard Format
Show BOTH relative time AND absolute date:
```
Soft Deadline: 2 days, 3 hours | Jan 27, 1:00 PM
Hard Deadline: 6 days, 23 hours | Jan 31, 1:00 PM
```

### Time-Based Format

| Time Remaining | Format |
|----------------|--------|
| > 1 day | "X days, Y hours" |
| < 1 day | "X hours, Y minutes" |
| < 1 hour | "X minutes, Y seconds" |
| Overdue | "Overdue by X days" |

---

## 11.3 Color Scheme

From [SHARED-CONSTANTS.md](../../SHARED-CONSTANTS.md):

### Soft Deadline Colors
| Time Remaining | Color | CSS Class | Hex |
|----------------|-------|-----------|-----|
| > 7 days | Green | `deadline-safe` | `#22c55e` |
| 3-7 days | Yellow | `deadline-warning` | `#eab308` |
| 1-3 days | Orange | `deadline-urgent` | `#f97316` |
| < 24 hours | Light Red | `deadline-critical` | `#ef4444` |

### Hard Deadline Colors
| Time Remaining | Color | CSS Class | Hex |
|----------------|-------|-----------|-----|
| > 7 days | Green | `deadline-safe` | `#22c55e` |
| 3-7 days | Yellow | `deadline-warning` | `#eab308` |
| 1-3 days | Orange | `deadline-urgent` | `#f97316` |
| < 24 hours | Dark Red | `deadline-critical-hard` | `#dc2626` |
| Overdue | Black | `deadline-overdue` | `#1f2937` |

---

## 11.4 Dashboard Display

### Deadline Info Box
```
┌─────────────────────────────────────────────┐
│  ⏰ Deadlines                               │
├─────────────────────────────────────────────┤
│  Soft Deadline                              │
│  🟡 2 days, 3 hours remaining               │
│  Jan 27, 2026 at 1:00 PM                    │
├─────────────────────────────────────────────┤
│  Hard Deadline                              │
│  🟢 6 days, 23 hours remaining              │
│  Jan 31, 2026 at 1:00 PM                    │
├─────────────────────────────────────────────┤
│  (If extended)                              │
│  Extension Deadline                         │
│  🔵 9 days remaining                        │
│  Feb 3, 2026 at 1:00 PM                     │
└─────────────────────────────────────────────┘
```

---

## 11.5 Section View Display

When viewing a section, show deadline status in header:

| Condition | Display |
|-----------|---------|
| Normal | No special indicator |
| Past soft deadline | "⚠ You're past the soft deadline" (yellow) |
| Approaching hard | "⚠ Hard deadline in X hours" (orange) |
| Past hard | "🔒 Exam locked. Request extension." (red) |
| Extended | "Extension: X days remaining" (blue) |

---

## 11.6 Live Updates

### Update Frequency

| Time Remaining | Update Interval |
|----------------|-----------------|
| > 1 hour | Every 60 seconds |
| < 1 hour | Every 10 seconds |
| < 5 minutes | Every 1 second |

### Implementation
```javascript
// Example: Live countdown update
setInterval(() => {
  updateCountdown();
}, getIntervalBasedOnTimeRemaining());
```

---

## 11.7 Warning Messages

### Soft Deadline Approaching
- When: < 24 hours to soft deadline
- Display: Yellow banner at top of dashboard
- Message: "⚠ Soft deadline approaching in X hours"

### Hard Deadline Approaching
- When: < 24 hours to hard deadline
- Display: Red banner at top of dashboard/section
- Message: "⚠ Hard deadline in X hours. Complete your work!"

### Extension Approaching
- When: < 24 hours to extension deadline
- Display: Orange banner
- Message: "⚠ Extension expires in X hours"

---

## 11.8 Post-Deadline States

### Soft Deadline Passed
- Status changes to `SOFT_DEADLINE_REACHED`
- Banner: "You've passed the soft deadline. Hard deadline: X days remaining."
- Can still mark sections

### Hard Deadline Passed
- Status changes to `LOCKED`
- Banner: "Your hard deadline has passed. Exam is locked."
- Cannot mark sections
- Show extension request button

### Extension Expired
- Status changes to `LOCKED`
- Banner: "Your extension has expired. Exam is locked."
- Show extension request button (if allowed)

---

## 11.9 Timezone Handling

### Display Rules
- All times displayed in user's local timezone
- Backend stores UTC timestamps
- Frontend converts for display

### Implementation
```javascript
const deadline = new Date(deadlineUtc);
const formatted = deadline.toLocaleString('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
  timeZoneName: 'short'
});
// Output: "Jan 27, 2026, 1:00 PM EST"
```

---

## 11.10 API Data Structure

```json
{
  "softDeadlineDate": "2026-01-27T18:00:00Z",
  "hardDeadlineDate": "2026-01-31T18:00:00Z",
  "extensionDeadlineDate": null,
  "originalHardDeadline": "2026-01-31T18:00:00Z",
  "status": "ACTIVE"
}
```

---

## 11.11 Acceptance Criteria

### Display
- [ ] Countdown shows relative AND absolute time
- [ ] Colors match urgency level
- [ ] Updates in real-time

### Warnings
- [ ] Banner appears at 24 hours
- [ ] Different styling for soft vs hard
- [ ] Extension deadline displays when applicable

### Post-Deadline
- [ ] Locked state shows clearly
- [ ] Extension request button visible
- [ ] Cannot mark sections when locked

### Timezone
- [ ] Times display in user's local timezone
- [ ] Timezone abbreviation shown

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Deadline Engine** | [29-deadline-engine](../../01-admin-backend/split-spec/29-deadline-engine.md) | Backend calculation |
| **Deadline Flow Diagram** | [04-deadline-calculation-flow](../../diagrams/04-deadline-calculation-flow.md) | Visual flow |
| **Locked State** | [15-locked-state](15-locked-state.md) | Post-deadline behavior |
| **Extension Request** | [12-extension-request](12-extension-request.md) | Request form |

---

*Next: `12-extension-request.md`*
