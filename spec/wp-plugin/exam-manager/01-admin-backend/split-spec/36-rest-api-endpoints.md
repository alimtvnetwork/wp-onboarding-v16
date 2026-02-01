# 34. REST API Endpoints

## Overview
WordPress REST API endpoints for admin panel AJAX operations and potential external integrations.

---

## 34.1 API Namespace and Authentication

### Namespace
All endpoints use: `/wp-json/eqm/v1/`

### Authentication
- WordPress nonce verification for admin requests
- Secret key authentication for public exam access
- Capability checks based on RBAC system

### Acceptance Criteria:
- [ ] All endpoints registered under `eqm/v1` namespace
- [ ] Nonce verified for all authenticated endpoints
- [ ] Proper HTTP status codes returned
- [ ] JSON response format for all endpoints

---

## 34.2 Session Management

### Session Cookie Format
**Cookie Name Pattern:** `eqm_session_{examSlug}`
- examSlug: URL-safe exam slug (lowercase, hyphens only)
- Example: `eqm_session_javascript-basics`

### Cookie Attributes
| Attribute | Value |
|-----------|-------|
| HttpOnly | true |
| Secure | true (production) |
| SameSite | Strict |
| Path | / |
| Expires | 7 days (default) or 30 days (remember me) |

### Session Data Structure
```
Session
├── sessionId: string (UUID)
├── participantId: int
├── examId: int
├── email: string
├── createdAt: timestamp
├── expiresAt: timestamp
├── isRememberMe: boolean
└── lastActivityAt: timestamp
```

### Sliding Expiration
- On each authenticated request, `lastActivityAt` updated
- Session extends if activity within last 24 hours
- Maximum absolute lifetime: 30 days (remember me) or 7 days (default)

### Acceptance Criteria:
- [ ] Cookie name follows `eqm_session_{examSlug}` pattern
- [ ] HttpOnly and Secure flags set in production
- [ ] Session validation on every protected request
- [ ] Sliding expiration extends active sessions
- [ ] Remember me extends to 30 days

---

## 34.3 Exam Endpoints

### GET /exams
List all exams with pagination

**Query Parameters:**
- `page` (int) - Page number, default 1
- `per_page` (int) - Items per page, default 20
- `status` (string) - Filter by status
- `search` (string) - Search in title

**Response:** Array of exam objects with pagination headers

### GET /exams/{id}
Get single exam with all related data

### POST /exams
Create new exam

**Required Fields:**
- `title` (string)
- `status` (enum)

### PUT /exams/{id}
Update existing exam

### DELETE /exams/{id}
Delete exam (soft delete or hard delete based on setting)

### Acceptance Criteria:
- [ ] Pagination headers included (X-WP-Total, X-WP-TotalPages)
- [ ] Search works on title and description
- [ ] Status filter validates against enum values
- [ ] Create validates required fields
- [ ] Update allows partial updates
- [ ] Delete checks for dependent participants

---

## 34.4 Participant Endpoints

### GET /exams/{examId}/participants
List participants for an exam

### POST /exams/{examId}/participants
Add participant to exam (admin action)

**Required Fields:**
- `email` (string)
- `name` (string)

### GET /participants/{id}
Get participant details with progress

### PUT /participants/{id}
Update participant information

### DELETE /participants/{id}
Remove participant from exam

### POST /participants/{id}/extend
Request or grant extension (admin action)

### POST /participants/{id}/extensions
Request deadline extension (participant action, alias for frontend)

**Request Body:**
- `days` (int) - Required, 1-30
- `reason` (string) - Required, 50-1000 chars
- `attachment` (file) - Optional, PDF/DOC/DOCX, max 5MB

**Response (200):**
```json
{
  "success": true,
  "requestId": 456,
  "status": "PENDING"
}
```

**Response (400):**
```json
{
  "success": false,
  "error": "Reason must be at least 50 characters"
}
```

### POST /request-extension
Alias for `/participants/{id}/extensions` (frontend-friendly)

**Authentication:** Valid session required (participant ID from session)

**Request Body:** Same as `/participants/{id}/extensions`

**Behavior:**
1. Get participant ID from session
2. Delegate to `/participants/{id}/extensions` logic

### Acceptance Criteria:
- [ ] Participant email validated and unique per exam
- [ ] Progress calculated from checklist completions
- [ ] Extension creates extension_request record
- [ ] Delete removes all related checklist progress

---

## 34.5 Authentication Endpoints (Public)

### POST /signup
Create new participant account

**Request Body:**
- `examId` (int) - Required
- `email` (string) - Required, valid email format
- `password` (string) - Required, min 8 chars
- `name` (string) - Optional, max 100 chars
- `whatsapp` (string) - Required for invite-only exams, optional otherwise
- `linkedInUrl` (string) - Optional

> **Status Clarification**: 
> - Self-signup creates participant with `ACTIVE` status (immediate access)
> - Admin-added participants start with `INVITED` status (see 27-participant-service.md)

**Invite-Only Validation:**
If `exam.isInviteOnly = true`:
1. Look up `examInvite` record where `email` matches (case-insensitive)
2. If no invite found: Return 403 "You have not been invited to this exam"
3. If invite found, verify `phone` matches `whatsapp` field
4. If phone mismatch: Return 403 "Phone number doesn't match invitation"
5. If both match: Proceed with signup, update invite status to 'ACCEPTED'

**Response (200):**
```json
{
  "success": true,
  "participantId": 123,
  "redirectUrl": "/{slug}/dashboard"
}
```

**Response (400):**
```json
{
  "success": false,
  "error": "Email already registered for this exam"
}
```

**Response (403) - Invite-Only:**
```json
{
  "success": false,
  "error": "You have not been invited to this exam",
  "code": "ERR_ACC_3008"
}
```

**Behavior:**
1. Check if exam is invite-only → validate invite (see above)
2. Validate email uniqueness within exam
3. Hash password with bcrypt
4. Create participant with **ACTIVE** status (self-signup = immediate access)
5. Store both originalSoftDeadline/originalHardDeadline and effective deadlines
6. If invite-only: Update `examInvite.status` to 'ACCEPTED' and link `participantId`
7. Create session, set cookie
8. Queue welcome email

### POST /login
Authenticate existing participant

**Request Body:**
- `examId` (int) - Required
- `email` (string) - Required
- `password` (string) - Required
- `isRememberMe` (boolean) - Optional, default false

**Response (200):**
```json
{
  "success": true,
  "redirectUrl": "/{slug}/dashboard"
}
```

**Response (401):**
```json
{
  "success": false,
  "error": "Invalid email or password"
}
```

**Session Duration:**
- `isRememberMe=false`: 7 days
- `isRememberMe=true`: 30 days

### POST /logout
End participant session

**Response (200):**
```json
{
  "success": true,
  "redirectUrl": "/{slug}"
}
```

### POST /participate
Enroll authenticated user in new exam

**Authentication:** Valid session required (from another exam)

**Request Body:**
- `examId` (int) - Required
- `linkedInUrl` (string) - Optional

**Behavior:**
1. Get user identity from existing session
2. Verify user not already participant in target exam
3. Create new participant record with exam deadlines
4. Create exam-specific session
5. Return redirect URL

**Response (200):**
```json
{
  "success": true,
  "participantId": 456,
  "redirectUrl": "/{new-slug}/dashboard"
}
```

**Response (400):**
```json
{
  "success": false,
  "error": "Already participating in this exam"
}
```

### POST /forgot-password
Request password reset email

**Request Body:**
- `examId` (int) - Required
- `email` (string) - Required

**Behavior:**
1. Look up participant by email + examId
2. If not found, return success anyway (prevent email enumeration)
3. Generate secure reset token (expires in 1 hour)
4. Store token hash in database
5. Queue password reset email with link

**Response (200):**
```json
{
  "success": true,
  "message": "If an account exists, a reset email has been sent"
}
```

### POST /reset-password
Complete password reset with token

**Request Body:**
- `token` (string) - Required, from email link
- `password` (string) - Required, min 8 chars
- `confirmPassword` (string) - Required, must match

**Behavior:**
1. Validate token exists and not expired
2. Validate passwords match
3. Hash new password
4. Update participant password
5. Invalidate all existing sessions for this participant
6. Delete used reset token

**Response (200):**
```json
{
  "success": true,
  "redirectUrl": "/{slug}/login"
}
```

**Response (400):**
```json
{
  "success": false,
  "error": "Invalid or expired reset token"
}
```

### POST /validate-secret-key
Validate secret key and get exam data (alias for /access)

**Request Body:**
- `key` (string) - Required, the secret key
- `examSlug` (string) - Optional, for additional validation

**Behavior:**
1. Look up key by hash
2. Validate: active, not expired, under usage limit
3. If valid, return exam data and auto-signup flag
4. Increment usage count
5. Log access

**Response (200):**
```json
{
  "success": true,
  "auto": true,
  "exam": {
    "id": 5,
    "title": "JavaScript Basics",
    "slug": "javascript-basics"
  }
}
```

**Response (403):**
```json
{
  "success": false,
  "error": "Invalid or expired key"
}
```

### Acceptance Criteria:
- [ ] Signup validates all required fields
- [ ] Password hashed with bcrypt (cost 12)
- [ ] Login accepts `isRememberMe` parameter
- [ ] Session cookie set with correct expiration
- [ ] Participate endpoint requires valid session
- [ ] Participate creates new participant with calculated deadlines
- [ ] Forgot password prevents email enumeration
- [ ] Reset token expires after 1 hour
- [ ] Reset invalidates existing sessions
- [ ] validate-secret-key aliases /access endpoint
- [ ] All auth endpoints rate-limited (10 attempts per IP per minute)

---

## 34.6 Frontend Logging Endpoint (Public)

### POST /log-event
Fire-and-forget client-side event logging

**Request Body:**
- `examId` (int) - Optional
- `participantId` (int) - Optional
- `sessionId` (string) - Optional
- `action` (string) - Required, max 50 chars
- `details` (object) - Optional, max 4KB
- `timestamp` (ISO8601) - Required

**Allowed Actions (whitelist):**
```
pageView, signupAttempted, signupFailed, signupSuccess,
loginAttempted, loginFailed, loginSuccess, logoutInitiated, logoutSuccess,
participateLandingViewed, participateDialogOpened, participateConfirmed, participateFailed,
prerequisiteViewed, prerequisiteCompleted, allPrerequisitesCompleted,
sectionViewed, sectionMarkedDone, sectionUndone, examCompleted,
extensionRequested, extensionApprovalReceived, extensionRejectionReceived,
sessionExpired, hardDeadlineApproaching, hardDeadlinePassed,
validationError, networkError, apiError
```

**Response:** `200 OK` (empty body, fire-and-forget)

**Rate Limiting:** 100 events per minute per IP

**Logging Format:**
```
[TIMESTAMP] USER_ACTION participantId={id} examId={id} sessionId={id} action="{action}" details={json}
```

### Acceptance Criteria:
- [ ] No authentication required
- [ ] Action validated against whitelist
- [ ] Details payload capped at 4KB
- [ ] Rate limiting enforced
- [ ] Logged to plugin.log file
- [ ] Errors don't affect response (always 200)

---

## 34.7 Checklist & Section Endpoints

### GET /exams/{examId}/checklists
Get all checklists for exam

### POST /checklists
Create new checklist item

### PUT /checklists/{id}
Update checklist item

### DELETE /checklists/{id}
Delete checklist item

### POST /participants/{id}/checklists/{checklistId}/complete
Mark checklist item as complete

### DELETE /participants/{id}/checklists/{checklistId}/complete
Unmark checklist item

### POST /participants/{id}/sections/{sectionNumber}/complete
Mark exam section as complete (frontend-friendly)

**Request Body:**
- `examId` (int) - Required

**Behavior:**
1. Look up checklist item where `phase='IN_EXAM'` AND `metadata.sectionNumber={sectionNumber}`
2. If not found, return 404 "Section not found"
3. Create `participantChecklist` record
4. Recalculate progress percentage
5. Check if exam completed (all sections done)

**Response (200):**
```json
{
  "success": true,
  "isCompleted": true,
  "totalCompleted": 5,
  "totalSections": 8,
  "progressPercent": 62.5
}
```

### DELETE /participants/{id}/sections/{sectionNumber}/complete
Undo section completion

### POST /participants/{id}/prerequisites/{prerequisiteId}/complete
Mark prerequisite as completed

**Behavior:**
1. Verify prerequisite belongs to participant's exam
2. Find corresponding PRE phase checklist item
3. Create `participantChecklist` record
4. Update prerequisite progress count

**Response (200):**
```json
{
  "success": true,
  "totalPrerequisitesCompleted": 4,
  "totalPrerequisites": 5,
  "allPrerequisitesComplete": false
}
```

### DELETE /participants/{id}/prerequisites/{prerequisiteId}/complete
Undo prerequisite completion

### Acceptance Criteria:
- [ ] Checklist order maintained via `sortOrder` field
- [ ] Completing item creates `participantChecklist` record
- [ ] Uncompleting removes the record
- [ ] Progress recalculated on each completion change
- [ ] Section endpoint maps sectionNumber to checklist item
- [ ] Prerequisite endpoint maps prerequisiteId to PRE checklist item
- [ ] 404 returned if section/prerequisite not found

---

## 34.8 Wiki Endpoints

### GET /wiki
List all wiki pages

### GET /wiki/{id}
Get wiki page with content

### POST /wiki
Create wiki page

### PUT /wiki/{id}
Update wiki page (creates revision)

### DELETE /wiki/{id}
Delete wiki page

### GET /wiki/{id}/revisions
Get revision history

### POST /wiki/{id}/revert/{revisionId}
Revert to previous revision

### Acceptance Criteria:
- [ ] Visibility filtering based on user role
- [ ] Update creates new revision record
- [ ] Revision includes diff from previous
- [ ] Revert creates new revision (not destructive)

---

## 34.9 Secret Key Endpoints

### GET /exams/{examId}/keys
List secret keys for exam

### POST /exams/{examId}/keys
Generate new secret key

**Optional Fields:**
- `expiresAt` (datetime)
- `maxUses` (int)
- `note` (string)

### DELETE /keys/{id}
Revoke secret key

### GET /keys/{id}/analytics [OPTIONAL]
Get access analytics for key

### Acceptance Criteria:
- [ ] Key generated using cryptographic random
- [ ] Expiration and usage limits enforced
- [ ] Revoked keys return 403 on access attempt
- [ ] Analytics grouped by day/referrer/location

---

## 34.10 User/Role Endpoints

### GET /users
List users with roles

### POST /users
Create new user with role

### PUT /users/{id}/role
Change user role

### DELETE /users/{id}
Remove user (reassign content)

### Acceptance Criteria:
- [ ] Role changes logged for audit
- [ ] Cannot remove last admin
- [ ] Content reassignment prompt on delete
- [ ] WordPress user integration maintained

---

## 34.11 Settings Endpoints

### GET /settings
Get all plugin settings

### PUT /settings
Update plugin settings

### POST /settings/reset
Reset to defaults

### POST /settings/export
Export settings as JSON

### POST /settings/import
Import settings from JSON

### Acceptance Criteria:
- [ ] Settings validated before save
- [ ] Sensitive settings masked in response
- [ ] Export includes version for compatibility
- [ ] Import validates version compatibility

---

## 34.12 Public Access Endpoints

### POST /access
Validate secret key and get exam

**Request Body:**
- `key` (string) - Secret access key

**Response:**
- Exam data if valid
- 403 if invalid/expired/exhausted

### Acceptance Criteria:
- [ ] No authentication required
- [ ] Rate limiting applied (10 attempts per IP per hour)
- [ ] Access logged to analytics table
- [ ] Invalid key attempts logged for security

---

## 34.13 Standard Response Format

### Success Response Envelope
All successful responses use this consistent structure:

```json
{
  "success": true,
  "data": { /* endpoint-specific payload */ },
  "meta": {
    "requestId": "req_abc123xyz",
    "timestamp": "2026-01-26T14:30:00.000Z",
    "version": "1.0.0"
  }
}
```

### Paginated Response Envelope
For list endpoints with pagination:

```json
{
  "success": true,
  "data": [ /* array of items */ ],
  "meta": {
    "requestId": "req_def456uvw",
    "timestamp": "2026-01-26T14:30:00.000Z",
    "version": "1.0.0",
    "pagination": {
      "page": 1,
      "perPage": 20,
      "total": 150,
      "totalPages": 8,
      "hasNextPage": true,
      "hasPrevPage": false
    }
  }
}
```

### Meta Object Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `requestId` | string | ✅ | Unique request identifier (format: `req_{nanoid}`) |
| `timestamp` | ISO8601 | ✅ | Server timestamp when response generated |
| `version` | string | ✅ | API version (semver format) |
| `pagination` | object | Conditional | Present only for paginated list endpoints |

### Pagination Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `page` | int | Current page number (1-indexed) |
| `perPage` | int | Items per page |
| `total` | int | Total number of items |
| `totalPages` | int | Total number of pages |
| `hasNextPage` | boolean | Whether next page exists |
| `hasPrevPage` | boolean | Whether previous page exists |

---

## 34.14 Error Response Format

### Standard Error Response
All errors return consistent JSON structure:

```json
{
  "success": false,
  "error": {
    "code": "ERR_VAL_1001",
    "message": "Validation failed",
    "details": {
      "email": "Invalid email format",
      "password": "Must be at least 8 characters"
    }
  },
  "meta": {
    "requestId": "req_err789abc",
    "timestamp": "2026-01-26T14:30:00.000Z",
    "version": "1.0.0"
  }
}
```

### Error Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `code` | string | Error code from ERR_xxxx registry |
| `message` | string | Human-readable error message |
| `details` | object | Field-specific errors (validation) or additional context |

### HTTP Status Codes
| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 429 | Too Many Requests (rate limited) |
| 500 | Internal Server Error |

### Acceptance Criteria:
- [ ] All responses include `meta` object with `requestId`, `timestamp`, `version`
- [ ] Paginated endpoints include `meta.pagination` object
- [ ] All errors use standard format with `error` object
- [ ] Error codes follow ERR_xxxx registry format
- [ ] 500 errors logged but details hidden from response
- [ ] Validation errors include field-specific messages in `error.details`
- [ ] `requestId` format: `req_{12-char-nanoid}`
