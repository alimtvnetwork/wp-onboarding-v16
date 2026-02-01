# 28. Invite Signup Flow

## Overview
Public-facing signup page for invite-only exams. Validates that the user's email AND phone number match an existing invitation before allowing account creation.

---

## 28.1 Entry Points

### URL Patterns
| Pattern | Description |
|---------|-------------|
| `/{examSlug}` | Standard exam URL (detects invite-only) |
| `/{examSlug}?invite={token}` | Direct invite link (pre-fills email) |

### Detection Logic
```pseudocode
function determineSignupMode(examSlug, queryParams):
    exam = fetchExam(examSlug)
    
    IF exam.isInviteOnly = false:
        RETURN { mode: 'PUBLIC', showInviteForm: false }
    
    IF queryParams.invite EXISTS:
        inviteData = validateInviteToken(queryParams.invite)
        IF inviteData.valid:
            RETURN { 
                mode: 'INVITE_PREFILLED', 
                email: inviteData.email,
                phone: inviteData.phoneMasked,
                showInviteForm: true 
            }
    
    RETURN { mode: 'INVITE_MANUAL', showInviteForm: true }
```

---

## 28.2 Invite-Only Landing Page

### Layout (Invite-Only Exam)
```
┌─────────────────────────────────────────────────────────────────┐
│                     EXAM LOGO / HEADER                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│                    JavaScript Certification                      │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  🔒 This is an invite-only exam                           │  │
│  │                                                            │  │
│  │  You can only sign up if you have been invited.          │  │
│  │  Your email and phone number must match your invitation. │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│                      [Sign Up] [Login]                          │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  📋 About This Exam                                             │
│  {exam description displayed here}                              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Visual Indicators
- Lock icon (🔒) prominently displayed
- Yellow/amber info box explaining invite-only access
- Clear messaging about email + phone requirement

---

## 28.3 Invite Signup Form

### Form Layout (Standard)
```
┌─────────────────────────────────────────────────────────────────┐
│                    Create Your Account                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  ℹ️ Invite-Only Exam                                      │  │
│  │  Enter the email and phone number from your invitation.  │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Email *                                                        │
│  [john@example.com_______________________________]              │
│  ✓ Must match your invitation                                  │
│                                                                  │
│  Phone (WhatsApp) *                                             │
│  [+1234567890________________________________]                  │
│  Include country code · Must match your invitation              │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  Name                                                           │
│  [John Doe___________________________________]                  │
│  Optional                                                       │
│                                                                  │
│  LinkedIn URL                                                   │
│  [https://linkedin.com/in/johndoe____________]                  │
│  Optional                                                       │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  Password *                                                     │
│  [••••••••••_________________________________]                  │
│  ○ Min 8 characters  ○ At least one number                     │
│                                                                  │
│  Confirm Password *                                             │
│  [••••••••••_________________________________]                  │
│  ✓ Passwords match                                              │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  [            Create Account            ]                       │
│                                                                  │
│  Already have an account? [Login]                               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Form Layout (Pre-filled from Invite Link)
```
┌─────────────────────────────────────────────────────────────────┐
│                    Create Your Account                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  ✅ Invitation Verified                                   │  │
│  │  We found your invitation. Complete your account below.  │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Email                                                          │
│  john@example.com                                    [🔒 Locked] │
│  Pre-filled from your invitation                                │
│                                                                  │
│  Phone (WhatsApp) *                                             │
│  [________________________________]                             │
│  Enter the phone number from your invitation                    │
│  Hint: ends in ****7890                                         │
│                                                                  │
│  {... rest of form ...}                                         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 28.4 Field Specifications

### Required Fields (Invite-Only)

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Email | email | Yes | Valid format, must match invite |
| Phone | tel | Yes | Valid format, must match invite |
| Password | password | Yes | Min 8 chars, 1 number |
| Confirm Password | password | Yes | Must match password |

### Optional Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Name | text | No | Max 100 chars |
| LinkedIn URL | url | No | Valid LinkedIn URL if provided |

### Field States

| State | Email Field | Phone Field |
|-------|-------------|-------------|
| Manual Entry | Editable, empty | Editable, empty |
| Pre-filled (invite token) | Read-only, filled | Editable, with hint |
| Validated | Green border, ✓ | Green border, ✓ |
| Error | Red border, ✗ | Red border, ✗ |

---

## 28.5 Validation Flow

### Client-Side Validation (on blur)

```pseudocode
function validateField(field, value):
    SWITCH field:
        CASE 'email':
            IF isEmpty(value):
                RETURN { valid: false, error: 'Email is required' }
            IF NOT isValidEmail(value):
                RETURN { valid: false, error: 'Invalid email format' }
            RETURN { valid: true }
            
        CASE 'phone':
            IF isEmpty(value):
                RETURN { valid: false, error: 'Phone is required' }
            IF NOT isValidPhone(value):
                RETURN { valid: false, error: 'Invalid phone format' }
            RETURN { valid: true }
            
        CASE 'password':
            IF length(value) < 8:
                RETURN { valid: false, error: 'Minimum 8 characters' }
            IF NOT hasNumber(value):
                RETURN { valid: false, error: 'Must contain a number' }
            RETURN { valid: true }
            
        CASE 'confirmPassword':
            IF value != formState.password:
                RETURN { valid: false, error: 'Passwords do not match' }
            RETURN { valid: true }
```

### Server-Side Invite Validation (on submit)

```pseudocode
function handleSignupSubmit(formData):
    // Step 1: Client validation
    IF NOT allFieldsValid(formData):
        showValidationErrors()
        RETURN
    
    // Step 2: Submit to server
    setLoading(true)
    
    response = POST('/api/signup', {
        examId: exam.id,
        email: formData.email,
        phone: formData.phone,  // Called 'whatsapp' in API
        password: formData.password,
        name: formData.name,
        linkedInUrl: formData.linkedInUrl
    })
    
    // Step 3: Handle response
    IF response.success:
        logEvent('signupSuccess', { email: formData.email })
        redirect(response.redirectUrl)
        
    ELSE IF response.code == 'ERR_ACC_3008':
        // Not invited
        showError('email', 'This email has not been invited to this exam')
        logEvent('signupFailed', { reason: 'not_invited' })
        
    ELSE IF response.code == 'ERR_ACC_3009':
        // Phone mismatch
        showError('phone', 'Phone number does not match the invitation')
        logEvent('signupFailed', { reason: 'phone_mismatch' })
        
    ELSE IF response.code == 'ERR_ACC_3010':
        // Invite expired
        showExpiredInviteModal()
        logEvent('signupFailed', { reason: 'invite_expired' })
        
    ELSE:
        showGenericError(response.error)
        logEvent('signupFailed', { reason: response.code })
    
    setLoading(false)
```

---

## 28.6 Error States

### Error: Email Not Invited
```
┌───────────────────────────────────────────────────────────────┐
│  Email *                                                      │
│  [john@wrong.com_____________________________]  ❌            │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ ❌ This email has not been invited to this exam.        │  │
│  │    Please check your email or contact the administrator.│  │
│  └─────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────┘
```

### Error: Phone Mismatch
```
┌───────────────────────────────────────────────────────────────┐
│  Phone (WhatsApp) *                                           │
│  [+1999888777____________________________]  ❌                │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ ❌ Phone number does not match the invitation.          │  │
│  │    Please enter the phone number that was invited.      │  │
│  └─────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────┘
```

### Error: Invite Expired (Modal)
```
┌─────────────────────────────────────────────────────────────────┐
│                                                              ✕  │
│                                                                  │
│                         ⏰                                       │
│                 Invitation Expired                               │
│                                                                  │
│  Your invitation to "JavaScript Certification" has expired.    │
│                                                                  │
│  The invitation was valid until January 15, 2026.               │
│                                                                  │
│  Please contact the exam administrator to request a new         │
│  invitation.                                                     │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  📧 Contact: admin@example.com                            │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│                          [Close]                                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Error: Invite Already Used
```
┌─────────────────────────────────────────────────────────────────┐
│                                                              ✕  │
│                                                                  │
│                         ✅                                       │
│               Invitation Already Accepted                        │
│                                                                  │
│  This invitation has already been used to create an account.   │
│                                                                  │
│  If this was you, please log in instead.                        │
│                                                                  │
│              [Go to Login]    [Contact Admin]                   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 28.7 Loading States

### Submit Loading
```
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│  [     ⟳ Verifying invitation...     ]                         │
│                                                                  │
│  Checking your email and phone against the invitation.          │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Loading Stages
| Stage | Duration | Message |
|-------|----------|---------|
| Validating | 0-1s | "Verifying invitation..." |
| Creating | 1-2s | "Creating your account..." |
| Redirecting | 2-3s | "Success! Redirecting to dashboard..." |

---

## 28.8 Success Flow

### Success State
```
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│                         ✅                                       │
│                 Account Created!                                 │
│                                                                  │
│  Welcome to "JavaScript Certification"                          │
│                                                                  │
│  Redirecting to your dashboard...                               │
│                                                                  │
│  [     ▓▓▓▓▓▓▓▓▓░░░░░░░░     ]                                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Post-Signup Actions
1. Session cookie set automatically by backend
2. Log `signupSuccess` event
3. Redirect to `/{examSlug}/dashboard`
4. Show welcome toast on dashboard

---

## 28.9 Phone Number Hints

### Hint Display (Pre-filled Mode)
When user arrives via invite link, show masked phone hint:

```
Phone (WhatsApp) *
[________________________________]
Enter the phone number from your invitation
Hint: ends in ****7890
```

### Hint Generation
```pseudocode
function maskPhoneForHint(phone):
    // Show last 4 digits only
    // e.g., "+1234567890" → "****7890"
    lastFour = phone.slice(-4)
    RETURN "****" + lastFour
```

---

## 28.10 Accessibility

### ARIA Labels
```html
<form aria-labelledby="signup-heading" aria-describedby="invite-notice">
  <h1 id="signup-heading">Create Your Account</h1>
  
  <div id="invite-notice" role="alert" class="info-box">
    This is an invite-only exam. Your email and phone must match.
  </div>
  
  <label for="email">
    Email <span aria-label="required">*</span>
  </label>
  <input 
    id="email" 
    type="email" 
    required
    aria-invalid="false"
    aria-describedby="email-hint email-error"
  />
  <span id="email-hint">Must match your invitation</span>
  <span id="email-error" role="alert" hidden>Error message</span>
</form>
```

### Keyboard Navigation
- Tab order: Email → Phone → Name → LinkedIn → Password → Confirm → Submit
- Enter key submits form
- Escape closes modals
- Focus moves to first error field on validation failure

### Screen Reader Announcements
| Event | Announcement |
|-------|--------------|
| Form loads (invite mode) | "Invite-only signup form. Email and phone must match invitation." |
| Validation error | "Error: {field name}: {error message}" |
| Submit loading | "Verifying invitation, please wait" |
| Success | "Account created successfully. Redirecting to dashboard." |
| Invite expired | "Alert: Your invitation has expired" |

---

## 28.11 Responsive Design

### Mobile Layout (< 640px)
```
┌─────────────────────────────┐
│  🔒 Invite-Only Exam        │
│  Email and phone must match │
├─────────────────────────────┤
│                             │
│  Email *                    │
│  [_______________________]  │
│                             │
│  Phone *                    │
│  [_______________________]  │
│                             │
│  Name                       │
│  [_______________________]  │
│                             │
│  Password *                 │
│  [_______________________]  │
│                             │
│  Confirm *                  │
│  [_______________________]  │
│                             │
│  [   Create Account     ]   │
│                             │
│  Already registered? Login  │
│                             │
└─────────────────────────────┘
```

### Breakpoints
| Breakpoint | Layout Changes |
|------------|----------------|
| < 640px | Single column, full-width inputs |
| 640-1024px | Centered card, max-width 500px |
| > 1024px | Centered card with exam info sidebar |

---

## 28.12 Logged Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `inviteSignupViewed` | Page load (invite-only) | `{ examId, hasInviteToken }` |
| `inviteTokenValidated` | Token validation success | `{ examId, email }` |
| `inviteTokenInvalid` | Token validation failed | `{ examId, reason }` |
| `signupAttempted` | Form submitted | `{ examId, email }` |
| `signupFailed` | Server returned error | `{ examId, reason, code }` |
| `signupSuccess` | Account created | `{ examId, email }` |
| `inviteExpiredViewed` | Expired modal shown | `{ examId, email }` |

---

## 28.13 API Dependencies

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `GET /exams/{slug}` | GET | Fetch exam details (isInviteOnly) |
| `POST /api/validate-invite-token` | POST | Validate invite token from URL |
| `POST /api/signup` | POST | Create account with invite validation |
| `POST /api/log-event` | POST | Log frontend events |

### Validate Invite Token Endpoint
```
POST /api/validate-invite-token
Content-Type: application/json

{
  "token": "abc123xyz",
  "examId": 5
}

Response (200):
{
  "valid": true,
  "email": "john@example.com",
  "phoneMasked": "****7890",
  "expiresAt": "2026-02-20T00:00:00Z"
}

Response (400):
{
  "valid": false,
  "reason": "expired" | "invalid" | "used"
}
```

---

## 28.14 State Management

### Form State
```typescript
interface InviteSignupState {
  mode: 'PUBLIC' | 'INVITE_MANUAL' | 'INVITE_PREFILLED';
  
  // Form values
  email: string;
  phone: string;
  name: string;
  linkedInUrl: string;
  password: string;
  confirmPassword: string;
  
  // UI state
  isEmailLocked: boolean;  // true when pre-filled from token
  phoneHint: string | null;  // "****7890" when available
  
  // Validation
  errors: Record<string, string>;
  touched: Record<string, boolean>;
  
  // Submission
  isSubmitting: boolean;
  submitError: string | null;
  
  // Modals
  showExpiredModal: boolean;
  showAlreadyUsedModal: boolean;
}
```

### Initial State Logic
```typescript
function initializeState(exam, inviteToken): InviteSignupState {
  if (!exam.isInviteOnly) {
    return { mode: 'PUBLIC', /* ... */ };
  }
  
  if (inviteToken && inviteToken.valid) {
    return {
      mode: 'INVITE_PREFILLED',
      email: inviteToken.email,
      isEmailLocked: true,
      phoneHint: inviteToken.phoneMasked,
      /* ... */
    };
  }
  
  return { mode: 'INVITE_MANUAL', /* ... */ };
}
```

---

## 28.15 Acceptance Criteria

### Form Display
- [ ] Invite-only notice displayed prominently
- [ ] All required fields marked with asterisk
- [ ] Email field locked when pre-filled from token
- [ ] Phone hint displayed when available
- [ ] Optional fields clearly marked

### Validation
- [ ] Client-side validation on blur
- [ ] Server-side invite validation on submit
- [ ] Specific error messages for invite failures
- [ ] Error focus moves to first invalid field

### Error Handling
- [ ] "Not invited" error shows on email field
- [ ] "Phone mismatch" error shows on phone field
- [ ] Expired invite shows modal with contact info
- [ ] Already used invite offers login link

### Accessibility
- [ ] All inputs have associated labels
- [ ] Errors announced to screen readers
- [ ] Keyboard navigation works correctly
- [ ] Focus management on errors and modals

### Mobile
- [ ] Form fully usable on mobile devices
- [ ] Virtual keyboard doesn't obscure inputs
- [ ] Touch targets minimum 44x44px

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| Public Signup | [03-signup-flow](03-signup-flow.md) | Base signup form (extended for invites) |
| Form Validation | [19-form-validation](19-form-validation.md) | Validation patterns |
| Error Handling | [16-error-handling](16-error-handling.md) | Error display patterns |
| Loading States | [20-loading-states](20-loading-states.md) | Loading UI patterns |
| UI Design System | [22-ui-design-system](22-ui-design-system.md) | Color tokens, spacing |
| Backend Invites | [50-exam-invite-management](../../01-admin-backend/split-spec/50-exam-invite-management.md) | Admin invite management |
| REST API | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) | Signup endpoint with invite validation |
| Error Codes | [66-shared-constants](../../66-shared-constants.md) | ERR_ACC_3008, 3009, 3010 |

---

*Next: `29-invite-token-handling.md`*
