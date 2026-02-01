# 04. Login Flow

## Overview
Existing participant authentication with email/password, remember me option, and session management.

---

## 04.1 Route

| Route | Description |
|-------|-------------|
| `/{slug}/login` | Login page for existing participants |

---

## 04.2 Page Layout

```
┌─────────────────────────────────────────┐
│  Header: Exam Title | Back to Signup    │
└─────────────────────────────────────────┘
│
├─ "Login" heading
├─ Form:
│  ├─ Email input (with validation)
│  ├─ Password input
│  ├─ Checkbox: "Remember me for 30 days"
│  └─ Buttons: "Login" (primary)
│
├─ Error message area
│
├─ "Forgot password?" link (optional)
│
└─ "Don't have account? Sign up" link
```

---

## 04.3 Form Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Email | email | Yes | Valid email format |
| Password | password | Yes | Not empty |
| Remember Me | checkbox | No | Boolean |

---

## 04.4 Client-Side Validation

| Field | Rule | Error Message |
|-------|------|---------------|
| Email | Regex: `/^[^\s@]+@[^\s@]+\.[^\s@]+$/` | "Invalid email format" |
| Password | Not empty | "Password is required" |

### Validation Behavior
- Validate on blur (when field loses focus)
- Show inline error messages
- Disable submit button while request in progress

---

## 04.5 Submit Behavior

### API Call

```
POST /api/login
Content-Type: application/json

{
  "examId": 5,
  "email": "john@example.com",
  "password": "userpassword",
  "isRememberMe": true
}
```

### Response Handling

| Status | Response | Action |
|--------|----------|--------|
| 200 | `{success: true, redirectUrl: "/{slug}/dashboard"}` | Redirect to dashboard |
| 401 | `{success: false, error: "Invalid email or password"}` | Show error, stay on form |
| 429 | Rate limit exceeded | Show "Too many attempts, wait X minutes" |
| 500 | Server error | Show generic error |

### Cookie Behavior
| Remember Me | Cookie Expiration |
|-------------|-------------------|
| Checked | 30 days |
| Unchecked | Browser session |

---

## 04.6 User Flow (Step-by-Step)

1. Participant visits `/{slug}` (no session cookie)
2. Sees signup form, clicks "Already registered? Login here"
3. Frontend navigates to `/{slug}/login`
4. Participant enters email and password
5. Optionally checks "Remember me for 30 days"
6. Clicks "Login"
7. Frontend validates:
   - Email not empty and valid format
   - Password not empty
8. If validation fails: Show errors, stay on form
9. If validation passes: POST to `/api/login`
10. Show loading spinner: "Logging in..."
11. Backend validates credentials:
    - Success: Set session cookie, return redirect URL
    - Failure: Return error
12. On success:
    - Log `loginSuccess`
    - Redirect to `/{slug}/dashboard`
    - Show "Welcome back, [Name]!"
13. On failure:
    - Log `loginFailed`
    - Show error: "Invalid email or password"
    - Suggest signup link

**Duration**: 1-2 minutes

---

## 04.7 Remember Me Feature

### How It Works
- Checkbox: "Remember me for 30 days"
- When checked: Session cookie expires in 30 days
- When unchecked: Session cookie expires when browser closes

### Backend Implementation
- Cookie: `eqm_session_{examSlug}`
- Cookie attributes: HttpOnly, Secure (prod), SameSite=Lax

---

## 04.8 Logged Events

| Event | Trigger | Details |
|-------|---------|---------|
| `loginAttempted` | Form submitted | `{email}` |
| `loginFailed` | Invalid credentials | `{email, reason: "invalidCredentials"}` |
| `loginSuccess` | Authenticated | `{email, isRememberMe}` |

---

## 04.9 UI States

### Default State
- Email and password fields empty
- Remember me unchecked
- Login button enabled

### Loading State
- Button shows spinner
- Text: "Logging in..."
- Form fields disabled

### Error State
- Error message displayed
- Form fields enabled
- Button re-enabled

---

## 04.10 Password Reset (Optional)

If implemented:
- "Forgot password?" link below form
- Navigates to `/{slug}/forgot-password`
- Separate flow for email-based reset

---

## 04.11 API Dependencies

| Endpoint | Method | Backend Spec |
|----------|--------|--------------|
| `POST /api/login` | POST | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) |
| `POST /api/log-event` | POST | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) |
| `POST /api/forgot-password` | POST | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) |

---

## 04.12 Acceptance Criteria

- [ ] Email and password fields validate on blur
- [ ] Invalid credentials show clear error message
- [ ] Login button shows loading state during submission
- [ ] Remember me sets 30-day cookie expiration
- [ ] Success redirects to dashboard
- [ ] Rate limiting shows appropriate message
- [ ] "Don't have account?" links to signup
- [ ] All events logged

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Signup Flow** | [03-signup-flow](03-signup-flow.md) | "Don't have account?" link |
| **Session Management** | [13-session-management](13-session-management.md) | Cookie handling |
| **Form Validation** | [19-form-validation](19-form-validation.md) | Validation patterns |

---

*Next: `05-participate-flow.md`*
