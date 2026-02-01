# 03. Signup Flow

## Overview
New participant registration form with validation, API submission, and redirect to dashboard on success.

---

## 03.1 Form Fields

> **IMPORTANT**: Field requirements are aligned with backend API (36-rest-api-endpoints.md).
> Name, WhatsApp, and LinkedIn are OPTIONAL to reduce signup friction.

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Email | email | Yes | Valid email format |
| Name | text | No | Max 100 characters |
| WhatsApp | tel | No | Valid phone format if provided |
| LinkedIn URL | url | No | Valid LinkedIn URL format if provided |
| Password | password | Yes | Min 8 characters |
| Confirm Password | password | Yes | Must match password |

---

## 03.2 Form Layout

```
┌─────────────────────────────────────────┐
│  "Create Your Account" heading          │
├─────────────────────────────────────────┤
│  Email*: [___________________________]  │
│          ✓ Valid email format           │
│                                         │
│  Name: [_____________________________]  │
│        (optional)                       │
│                                         │
│  WhatsApp: [__________________________] │
│            Enter with country code      │
│            (optional)                   │
│                                         │
│  LinkedIn: [__________________________] │
│            https://linkedin.com/in/...  │
│            (optional)                   │
│                                         │
│  Password*: [_________________________] │
│             ○ Min 8 characters          │
│             ○ At least one number       │
│                                         │
│  Confirm*: [__________________________] │
│            ✓ Passwords match            │
│                                         │
│  * Required fields                      │
│                                         │
│  [         Sign Up (Primary)          ] │
│                                         │
│  Already registered? Login here (link)  │
└─────────────────────────────────────────┘
```

---

## 03.3 Client-Side Validation

### Real-Time Validation (on blur/change)

| Field | Validation | Error Message |
|-------|------------|---------------|
| Email | Regex: `/^[^\s@]+@[^\s@]+\.[^\s@]+$/` | "Invalid email format" |
| Name | Max 100 chars (if provided) | "Name must be under 100 characters" |
| WhatsApp | Valid phone format (if provided) | "Enter valid phone number" |
| LinkedIn | Valid URL containing "linkedin.com" (if provided) | "Enter valid LinkedIn URL" |
| Password | Min 8 chars | "Password must be at least 8 characters" |
| Confirm | Match password field | "Passwords do not match" |

> **Note**: Optional fields (Name, WhatsApp, LinkedIn) only validate FORMAT if user enters a value. Empty is valid.

### Visual Indicators
- ✓ Green checkmark for valid field
- ✗ Red X for invalid field
- Show validation feedback as user types

---

## 03.4 Submit Behavior

### Pre-Submit Validation
1. All required fields not empty (email, password)
2. Email format valid
3. Password min 8 chars
4. Passwords match

### Invite-Only Exam Validation

> **IMPORTANT**: If exam has `isInviteOnly=true`, additional validation applies.

For invite-only exams, the signup form will:
1. Require both Email AND WhatsApp fields
2. Backend validates both match an existing `examInvite` record
3. If no matching invite: Show error "You have not been invited to this exam"
4. If invite found but phone doesn't match: Show error "Phone number doesn't match invitation"

```
┌─────────────────────────────────────────┐
│  ⚠️ This is an invite-only exam         │
│                                         │
│  You can only sign up if you have been  │
│  invited. Your email AND phone number   │
│  must match your invitation.            │
└─────────────────────────────────────────┘
```

### API Call

```
POST /api/signup
Content-Type: application/json

{
  "examId": 5,
  "email": "john@example.com",
  "password": "securepassword123",
  "name": "John Doe",
  "whatsapp": "+1234567890",
  "linkedinUrl": "https://linkedin.com/in/johndoe"
}
```

> **Note**: For public exams, only `examId`, `email`, and `password` are required.
> For invite-only exams, `whatsapp` is also required and must match the invitation.

### Response Handling

| Status | Response | Action |
|--------|----------|--------|
| 200 | `{success: true, redirectUrl: "/{slug}/dashboard"}` | Set cookie (via backend), redirect |
| 400 | `{success: false, error: "Email already registered"}` | Show error, stay on form |
| 429 | Rate limit exceeded | Show "Too many attempts, try later" |
| 500 | Server error | Show "Something went wrong, try again" |

---

## 03.5 User Flow (Step-by-Step)

1. Participant visits `/{slug}` for first time
2. No session cookie → Show signup form
3. Participant fills fields (validation on blur)
4. Participant clicks "Sign Up"
5. Frontend validates all fields
6. If validation fails: Show errors, log `signupFailed`
7. If validation passes: POST to `/api/signup`
8. Show loading spinner: "Creating account..."
9. On success:
   - Log `signupSuccess`
   - Redirect to `/{slug}/dashboard`
   - Show welcome message
10. On failure:
    - Log `signupFailed` with reason
    - Show error message
    - Provide "Go to Login" link if email exists

**Duration**: 2-5 minutes

---

## 03.6 Logged Events

| Event | Trigger | Details |
|-------|---------|---------|
| `signupAttempted` | Form submitted | `{email}` |
| `signupFailed` | Validation or API error | `{reason, email}` |
| `signupSuccess` | Account created | `{email}` |
| `validationError` | Field validation fails | `{field, reason}` |

---

## 03.7 UI States

### Default State
- All fields empty
- Submit button enabled
- No validation messages

### Validating State
- Show checkmarks/X for validated fields
- Password requirements checklist updates
- Submit button enabled if all valid

### Loading State
- Submit button disabled
- Show spinner
- Text: "Creating account..."
- Form fields disabled

### Error State
- Submit button re-enabled
- Error message displayed at top
- Form fields enabled for retry

---

## 03.8 API Dependencies

| Endpoint | Method | Backend Spec |
|----------|--------|--------------|
| `POST /api/signup` | POST | [27-participant-service](../../01-admin-backend/split-spec/27-participant-service.md) |
| `POST /api/log-event` | POST | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) |

---

## 03.9 Acceptance Criteria

- [ ] All fields validate on blur with visual feedback
- [ ] Password requirements displayed and update in real-time
- [ ] Confirm password shows match/mismatch indicator
- [ ] Duplicate email shows clear error with login link
- [ ] Loading state disables form during submission
- [ ] Success redirects to dashboard
- [ ] Failure stays on form with error message
- [ ] All events logged to backend

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Login Flow** | [04-login-flow](04-login-flow.md) | "Already registered?" link |
| **Landing Page** | [02-public-landing-page](02-public-landing-page.md) | Form displayed on landing |
| **Form Validation** | [19-form-validation](19-form-validation.md) | Validation patterns |
| **Backend Participant** | [27-participant-service](../../01-admin-backend/split-spec/27-participant-service.md) | Creates participant record |

---

*Next: `04-login-flow.md`*
