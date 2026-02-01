# 17. Edge Cases

## Overview
Handling of unusual scenarios including session expiry, deadline during session, network issues, concurrent access, and state conflicts.

---

## 17.1 Session Expires During Reading

**Scenario**: User reading section for extended time, session expires

**Handling**:
1. User clicks action (Mark as Done)
2. API returns 401
3. Show modal: "Session expired. Please login again."
4. Store current URL in sessionStorage
5. Redirect to login
6. After login, return to stored URL

**Code Pattern**:
```javascript
function handleApiError(error, currentPath) {
  if (error.status === 401) {
    sessionStorage.setItem('returnUrl', currentPath);
    showModal({
      title: 'Session Expired',
      message: 'Please login again to continue.',
      action: () => redirectToLogin()
    });
  }
}
```

---

## 17.2 Hard Deadline Passes During Session

**Scenario**: User is working, hard deadline passes

**Handling**:
1. Action attempt returns 403 `EXAM_LOCKED`
2. Show modal: "Deadline has passed. Exam is now locked."
3. Offer extension request link
4. Update UI to locked state
5. Disable all progress actions
6. Log `hardDeadlineBlocked` event

---

## 17.3 Network Error During Action

**Scenario**: Network fails during Mark as Done

**Handling**:
1. Catch network error (fetch failure)
2. Show: "Unable to save. Check internet connection."
3. Keep button enabled for retry
4. Add exponential backoff for auto-retry (optional)
5. Log error locally (offline queue)

**Code Pattern**:
```javascript
async function markSectionDone(sectionId) {
  try {
    await api.post(`/sections/${sectionId}/complete`);
  } catch (error) {
    if (!navigator.onLine) {
      showError('No internet connection. Please try again.');
    } else {
      showError('Unable to save. Please try again.');
    }
    // Keep button enabled for manual retry
  }
}
```

---

## 17.4 Already Participating (Duplicate)

**Scenario**: User tries to participate but already enrolled

**Handling**:
1. API returns 400 "Already participating"
2. Show: "You're already enrolled in this exam"
3. Redirect to dashboard
4. Do not create duplicate participant record

---

## 17.5 Concurrent Tab/Window Access

**Scenario**: User has exam open in multiple tabs

**Handling**:
1. Progress updates via polling (every 30s) or storage event
2. Show notification if state changes: "Progress updated in another tab"
3. Sync latest state before actions
4. Prevent conflicting writes

**Code Pattern**:
```javascript
// Listen for storage events from other tabs
window.addEventListener('storage', (event) => {
  if (event.key === `progress_${examId}`) {
    const newProgress = JSON.parse(event.newValue);
    updateProgressUI(newProgress);
    showToast('Progress updated from another tab');
  }
});
```

---

## 17.6 Extension Approved While Viewing Locked State

**Scenario**: Admin approves extension while user views locked dashboard

**Handling**:
1. Periodic status check (every 60s) or WebSocket notification
2. Detect status change from `LOCKED` to `EXTENDED`
3. Show toast: "Extension approved! You can continue."
4. Update UI to unlocked state
5. Re-enable action buttons

---

## 17.7 Exam Deleted/Unpublished

**Scenario**: Admin deletes or unpublishes exam while participant is active

**Handling**:
1. API returns 404 or 410 for exam requests
2. Show: "This exam is no longer available"
3. Offer link to exam list or home
4. Clear local exam state

---

## 17.8 Browser Back/Forward Navigation

**Scenario**: User uses browser navigation during exam

**Handling**:
1. State persisted in URL where appropriate
2. Back button returns to previous section
3. Progress not lost on navigation
4. Unsaved changes prompt (if any)

---

## 17.9 Page Refresh During Action

**Scenario**: User refreshes page while action is processing

**Handling**:
1. Idempotent API design prevents duplicate actions
2. On reload, fetch latest state from server
3. Resume from correct position
4. Show confirmation if action was successful

---

## 17.10 Rate Limiting Hit

**Scenario**: User makes too many requests

**Handling**:
1. API returns 429 with `Retry-After` header
2. Show: "Too many requests. Please wait X seconds."
3. Disable action buttons temporarily
4. Auto-enable after retry period

---

## 17.11 Invalid/Expired Secret Key

**Scenario**: User accesses exam via invalid or expired secret key

**Handling**:
1. API returns 403 with `INVALID_KEY` or `EXPIRED_KEY` code
2. Show appropriate message:
   - Invalid: "This access link is not valid"
   - Expired: "This access link has expired"
3. Do not reveal which scenario (security)
4. Offer public signup if available

---

## 17.12 Partial Page Load (Slow Network)

**Scenario**: Content loads partially on slow connection

**Handling**:
1. Show skeleton loaders for content
2. Load critical UI first (navigation, status)
3. Progressive loading for sections
4. Timeout after 30s with retry option

---

## 17.13 Acceptance Criteria

### Session Management
- [ ] Session expiry shows login prompt with return URL
- [ ] Return URL correctly restores position after login
- [ ] Multiple tab access syncs state correctly
- [ ] Storage events trigger UI updates

### Deadline Handling
- [ ] Deadline passing during session shows lock modal
- [ ] UI transitions to locked state gracefully
- [ ] Extension approval during locked view unlocks UI
- [ ] Periodic deadline checks run (60s interval)

### Network Resilience
- [ ] Network errors show user-friendly message
- [ ] Retry button remains enabled after failure
- [ ] Offline state detected and communicated
- [ ] Rate limiting shows retry countdown

### State Conflicts
- [ ] Duplicate participation prevented
- [ ] Exam deletion handled gracefully
- [ ] Invalid secret keys show generic error
- [ ] Browser navigation preserves state

### Loading States
- [ ] Skeleton loaders shown during fetch
- [ ] Partial content visible during slow load
- [ ] Timeout with retry after 30s
- [ ] Critical UI loads first

### API Error Handling
- [ ] 401 → Login redirect with return URL
- [ ] 403 → Lock state or permission message
- [ ] 404 → "Not found" with navigation options
- [ ] 429 → Rate limit message with countdown
- [ ] 500 → Generic error with retry option

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Session Management** | [13-session-management](13-session-management.md) | Session lifecycle |
| **Locked State** | [15-locked-state](15-locked-state.md) | Lock behavior |
| **Error Handling** | [16-error-handling](16-error-handling.md) | Error display |
| **Loading States** | [20-loading-states](20-loading-states.md) | Loading UI |

---

*Next: `18-secret-key-access.md`*
