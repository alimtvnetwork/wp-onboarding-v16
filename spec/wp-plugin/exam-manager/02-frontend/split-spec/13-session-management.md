# 13. Session Management

## Overview
Cookie-based session handling including authentication, sliding expiration, remember me feature, and exam-scoped isolation.

---

## 13.1 Cookie Naming Convention

All cookies are exam-scoped using the pattern from [SHARED-CONSTANTS.md](../../SHARED-CONSTANTS.md):

| Cookie | Pattern | Purpose |
|--------|---------|---------|
| Session | `eqm_session_{examSlug}` | Authenticated user session |
| Anonymous | `eqm_anon_{examSlug}` | Anonymous participant tracking |
| Tracking | `eqm_track_{examSlug}` | Analytics tracking |

### Example
```
eqm_session_javascript-fundamentals
eqm_anon_advanced-react
eqm_track_python-basics
```

---

## 13.2 Session Cookie Properties

| Property | Value | Notes |
|----------|-------|-------|
| Name | `eqm_session_{examSlug}` | Exam-specific |
| HttpOnly | `true` | Not accessible via JavaScript |
| Secure | `true` (production) | HTTPS only |
| SameSite | `Lax` | CSRF protection |
| Path | `/` | Site-wide |
| Domain | Auto (current domain) | No subdomain |

---

## 13.3 Session Expiration

### Without Remember Me
- Cookie expires when browser closes
- No explicit expiration time
- Backend session: 7 days max

### With Remember Me
- Cookie expires in 30 days
- Explicit `expires` attribute set
- Backend session: 30 days

### Sliding Expiration
- On each authenticated request, session expiry resets
- Prevents active users from being logged out
- Backend updates session timestamp

---

## 13.4 Session Lifecycle

### Creation (Login/Signup)
1. User submits credentials
2. Backend validates
3. Backend creates session, sets cookie
4. Frontend receives response (cookie already set)
5. Frontend redirects to dashboard

### Validation (Page Load)
1. Frontend checks for cookie existence
2. If exists: Call `/api/validate-session`
3. Backend validates session token
4. If valid: Allow access
5. If invalid: Clear cookie, redirect to login

### Destruction (Logout)
1. User clicks logout
2. Frontend calls `/api/logout`
3. Backend invalidates session
4. Backend clears cookie
5. Frontend redirects to landing page

---

## 13.5 Session Validation Flow

```
┌─────────────────────────────────────────┐
│  Page Load                              │
└─────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────┐
│  Check for eqm_session_{slug} cookie    │
└─────────────────────────────────────────┘
        │
   ┌────┴────┐
   │         │
 Exists   Missing
   │         │
   ▼         ▼
┌────────┐  ┌────────────────┐
│ POST   │  │ Show login/    │
│ /api/  │  │ signup form    │
│ validate│  └────────────────┘
│ -session│
└────────┘
   │
   ├── Valid → Show authenticated UI
   │
   └── Invalid → Clear cookie, show login
```

---

## 13.6 Cross-Exam Isolation

Sessions are exam-specific:
- Cookie `eqm_session_exam-a` only valid for Exam A
- Logging into Exam B creates separate cookie
- Multiple exam sessions can coexist
- Logout from one doesn't affect others

### Benefits
- Privacy: Progress isolated per exam
- Security: Compromise of one doesn't affect others
- Flexibility: Different deadlines per exam

---

## 13.7 Session Expiry Handling

### During User Action
1. User clicks "Mark as Done"
2. Backend returns 401 (session expired)
3. Frontend shows modal: "Session expired. Please login again."
4. Clear cookie
5. Redirect to login (or show login form)
6. Preserve current URL for redirect after login

### On Page Load
1. Validation fails
2. Show message: "Your session has expired."
3. Redirect to login page
4. Store return URL in localStorage

---

## 13.8 Remember Me Feature

### Checkbox Behavior
- Default: Unchecked
- When checked: 30-day session
- When unchecked: Browser session only

### Implementation

**Frontend:**
```javascript
const payload = {
  email: email,
  password: password,
  isRememberMe: rememberCheckbox.checked
};
```

**Backend Response:**
```
Set-Cookie: eqm_session_exam-slug=abc123; 
  HttpOnly; 
  Secure; 
  SameSite=Lax; 
  Expires=Sat, 24 Feb 2026 00:00:00 GMT  // 30 days if remember me
```

---

## 13.9 Security Considerations

### Token Storage
- Session token stored in HttpOnly cookie (not accessible to JS)
- No sensitive data in localStorage/sessionStorage

### CSRF Protection
- SameSite=Lax prevents most CSRF attacks
- Additional CSRF token for sensitive operations (optional)

### XSS Mitigation
- HttpOnly cookies can't be stolen via XSS
- Still sanitize all user input

---

## 13.10 API Dependencies

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `POST /api/login` | POST | Create session |
| `POST /api/signup` | POST | Create session |
| `POST /api/validate-session` | POST | Validate session |
| `POST /api/logout` | POST | Destroy session |

---

## 13.11 Acceptance Criteria

### Cookie Management
- [ ] Cookies use correct naming pattern
- [ ] HttpOnly and Secure flags set
- [ ] SameSite=Lax for CSRF protection

### Session Lifecycle
- [ ] Login creates session cookie
- [ ] Logout clears session cookie
- [ ] Session validates on page load

### Remember Me
- [ ] Unchecked: Session cookie (browser close)
- [ ] Checked: 30-day cookie expiration

### Sliding Expiration
- [ ] Active users don't timeout
- [ ] Session extends on each request

### Cross-Exam
- [ ] Sessions isolated per exam
- [ ] Multiple exam sessions supported

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Login Flow** | [04-login-flow](04-login-flow.md) | Session creation |
| **Shared Constants** | [66-shared-constants](../../66-shared-constants.md) | Cookie naming |
| **Edge Cases** | [17-edge-cases](17-edge-cases.md) | Expiry handling |
| **Secret Key Access** | [18-secret-key-access](18-secret-key-access.md) | Anonymous sessions |

---

*Next: `14-exam-completion-flow.md`*
