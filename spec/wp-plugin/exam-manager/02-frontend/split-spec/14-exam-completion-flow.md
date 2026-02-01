# 14. Exam Completion Flow

## Overview
The process of marking sections as complete, tracking progress, and handling exam completion state.

---

## 14.1 Section Completion

### Mark as Done Button

| State | Button Text | Enabled | Action |
|-------|-------------|---------|--------|
| Not done | "Mark as Done" | Yes | POST completion |
| Loading | "Marking..." | No | Show spinner |
| Done | "✓ Completed" | No | Badge display |
| Locked | "Mark as Done (locked)" | No | Grayed out |

---

## 14.2 Completion API

### Request
```
POST /api/participants/me/sections/{sectionNumber}/complete
Content-Type: application/json

{
  "examId": 5,
  "sectionNumber": 3
}
```

### Response
```json
{
  "success": true,
  "isCompleted": true,
  "totalCompleted": 3,
  "totalSections": 8,
  "progressPercent": 37,
  "examCompleted": false
}
```

### Error Response
```json
{
  "success": false,
  "error": "Exam is locked",
  "code": "EXAM_LOCKED"
}
```

---

## 14.3 Completion Flow (Step-by-Step)

1. User reads section content
2. User clicks "Mark as Done"
3. Button changes to loading state
4. Frontend validates:
   - Session valid
   - Exam not locked
5. POST to completion endpoint
6. On success:
   - Button changes to "✓ Completed" badge
   - Progress counter updates
   - Show success toast
   - Log `sectionMarkedDone` event
7. Optional: Prompt to navigate to next section
8. If final section completed → Exam completion flow

---

## 14.4 Progress Update

### After Marking Section

| Element | Update |
|---------|--------|
| Section card | Changes to "Completed" state |
| Progress bar | Fills to new percentage |
| Progress text | "4 of 8 sections completed" |
| Section badge | Shows ✓ checkmark |

### Real-Time Updates
- Progress updates immediately in UI
- No page reload required
- Sync with backend in background

---

## 14.5 Undo Completion

### Optional Feature
- "Undo" button appears after marking done
- Available for limited time (e.g., 5 minutes)
- Or always available if admin allows

### Undo API
```
DELETE /api/participants/me/sections/{sectionNumber}/complete
```

### Response
```json
{
  "success": true,
  "totalCompleted": 2,
  "progressPercent": 25
}
```

---

## 14.6 Exam Completion

### Trigger
- Final section marked as done
- All required sections completed

### Backend Response (Final Section)
```json
{
  "success": true,
  "examCompleted": true,
  "completionTime": "2 days, 5 hours",
  "totalSections": 8,
  "redirectUrl": "/{slug}/completed"
}
```

### Frontend Behavior
1. Show celebration animation/message
2. Update status to COMPLETED
3. Redirect to completion page or show modal
4. Log `examCompleted` event

---

## 14.7 Completion Page

### Route
`/{slug}/completed` or modal overlay

### Content
```
┌─────────────────────────────────────────────┐
│                 🎉                          │
│        Congratulations!                     │
│                                             │
│  You've completed [Exam Title]              │
│                                             │
│  ┌───────────────────────────────────┐      │
│  │ Time to complete: 2 days, 5 hours │      │
│  │ Sections completed: 8 of 8        │      │
│  │ Completed on: Jan 27, 2026        │      │
│  └───────────────────────────────────┘      │
│                                             │
│  [View Certificate]    [Back to Dashboard]  │
└─────────────────────────────────────────────┘
```

---

## 14.8 Post-Completion State

### Dashboard Changes
- Status badge: "Completed ✓"
- Progress bar: 100% (green)
- CTA button: "View Results" instead of "Continue Exam"

### Section Access
- Sections remain readable
- "Mark as Done" buttons become badges
- Navigation still works

---

## 14.9 Logged Events

| Event | Trigger | Details |
|-------|---------|---------|
| `sectionMarkedDone` | Section completed | `{sectionNumber, duration}` |
| `sectionUndone` | Undo clicked | `{sectionNumber}` |
| `examCompleted` | All sections done | `{totalSections, timeToComplete}` |
| `completionPageViewed` | Completion page load | `{examId}` |

---

## 14.10 Error Handling

### Session Expired
- Error: 401 Unauthorized
- Action: Show login modal, preserve state

### Exam Locked
- Error: 403 Forbidden with `EXAM_LOCKED`
- Action: Show locked message, offer extension

### Network Error
- Error: No response
- Action: Show retry button, keep button enabled

### Already Completed
- Error: 400 Bad Request
- Action: Update UI to completed state (sync issue)

---

## 14.11 Acceptance Criteria

### Marking Complete
- [ ] Button shows loading state during request
- [ ] Success updates UI immediately
- [ ] Progress counter updates
- [ ] Section card state changes

### Undo
- [ ] Undo available (if feature enabled)
- [ ] Reverts section state
- [ ] Updates progress counter

### Exam Completion
- [ ] Triggers when final section done
- [ ] Shows celebration message
- [ ] Status updates to COMPLETED
- [ ] Stats display correctly

### Error Handling
- [ ] Session expiry handled gracefully
- [ ] Locked state shows appropriate message
- [ ] Network errors allow retry

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Section View** | [07-section-view](07-section-view.md) | Contains Mark as Done |
| **Progress Tracking** | [28-participant-progress](../../01-admin-backend/split-spec/28-participant-progress.md) | Backend calculation |
| **Dashboard** | [06-dashboard-page](06-dashboard-page.md) | Shows completion state |
| **Certificate** | [44-certificate-generation](../../01-admin-backend/split-spec/44-certificate-generation.md) | Post-completion |

---

*Next: `15-locked-state.md`*
