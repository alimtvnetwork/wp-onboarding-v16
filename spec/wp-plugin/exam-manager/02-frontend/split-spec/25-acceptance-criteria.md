# 25. Acceptance Criteria

## Overview
Master checklist for complete frontend implementation.

---

## 25.1 Authentication & Participation

- [ ] Signup form validates and creates account
- [ ] Login authenticates and sets session
- [ ] Logout clears session
- [ ] Participate flow for authenticated users
- [ ] Session persists across reloads
- [ ] Remember me extends cookie to 30 days
- [ ] **Invite-only signup validates email + phone against invitation**
- [ ] **Invite token pre-fills email when provided**
- [ ] **Expired/used invite shows appropriate modal**

---

## 25.2 Prerequisites

- [ ] Display in order (videos, links, checklists)
- [ ] YouTube/Vimeo embeds work
- [ ] Completion persists to backend
- [ ] Required items block exam start

---

## 25.3 Submissions

- [ ] All 9 submission types render correctly
- [ ] Client-side validation works per type
- [ ] Server-side validation responses handled
- [ ] File upload with drag-drop and progress
- [ ] Draft auto-save to localStorage
- [ ] Draft restoration on page load
- [ ] Status badge reflects review status
- [ ] Resubmission flow for rejected items
- [ ] Video embeds lazy-loaded
- [ ] Options shuffle consistently per user

---

## 25.4 Exam Content

- [ ] Markdown renders correctly
- [ ] Section cards show status
- [ ] Mark as Done updates progress
- [ ] Navigation between sections works

---

## 25.4 Deadlines & Extensions

- [ ] Countdown displays for all deadline types
- [ ] Hard deadline locks exam
- [ ] Extension request form works
- [ ] Extension approval unlocks

---

## 25.5 Logging & Errors

- [ ] All actions logged to backend
- [ ] Logging doesn't block UI
- [ ] Errors show user-friendly messages

---

## 25.6 Responsive Design

- [ ] Mobile layout correct
- [ ] Tablet adapts properly
- [ ] Desktop uses full width

---

## 25.7 Accessibility

- [ ] All text passes WCAG AA contrast
- [ ] Focus indicators visible
- [ ] Keyboard navigation complete
- [ ] Screen reader tested
- [ ] Reduced motion supported

---

## 25.8 Internationalization

- [ ] All strings use translation keys
- [ ] Date/time formatting localized
- [ ] Number formatting localized
- [ ] RTL layout basics functional

---

## 25.9 Performance

- [ ] LCP < 2.5s on all pages
- [ ] FID < 100ms
- [ ] CLS < 0.1
- [ ] JS bundle < 200KB gzipped
- [ ] API responses < 200ms p95

---

*This completes the frontend specification split.*

## Related Specifications

| Topic | Spec |
|-------|------|
| UI Design System | [22-ui-design-system](22-ui-design-system.md) |
| Internationalization | [26-internationalization](26-internationalization.md) |
| Performance Targets | [27-performance-targets](27-performance-targets.md) |
| Invite Signup Flow | [28-invite-signup-flow](28-invite-signup-flow.md) |
| Participant Submissions | [29-participant-submission-ui](29-participant-submission-ui.md) |
| Test Fixtures | [49-test-fixtures](../../01-admin-backend/split-spec/49-test-fixtures.md) |
