# 19. Form Validation

## Overview
Client-side validation patterns for all forms with real-time feedback.

---

## 19.1 Validation Rules

| Field | Pattern/Rule | Error Message |
|-------|--------------|---------------|
| Email | `/^[^\s@]+@[^\s@]+\.[^\s@]+$/` | "Invalid email format" |
| Password | Min 8 characters | "Password must be at least 8 characters" |
| Confirm Password | Match password field | "Passwords do not match" |
| LinkedIn URL | Contains "linkedin.com" | "Enter valid LinkedIn URL" |
| Extension Reason | Min 50 chars | "Minimum 50 characters required" |
| Extension Days | 1-30 | "Enter 1-30 days" |

---

## 19.2 Validation Timing

- **On blur**: Validate when field loses focus
- **On change**: Update validity indicator
- **On submit**: Full form validation

---

## 19.3 Visual Feedback

- ✓ Green checkmark for valid
- ✗ Red X for invalid
- Character counter for text areas
- Password strength indicator

---

## 19.4 Acceptance Criteria

- [ ] All fields validate on blur
- [ ] Visual feedback is immediate
- [ ] Error messages are specific
- [ ] Submit disabled until all valid

---

*Related: [03-signup-flow](03-signup-flow.md), [04-login-flow](04-login-flow.md), [12-extension-request](12-extension-request.md)*
