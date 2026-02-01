# Frontend-Backend Specification Inconsistency Report

> **Generated:** January 25, 2026  
> **Last Updated:** January 25, 2026  
> **Status:** ✅ ALL 19 RESOLVED | ⚠️ 3 MINOR ISSUES FOUND

---

## Executive Summary

After thorough cross-referencing of the frontend specification (`frontend-full-spec.md`) against the backend split specifications (files 01-47), **19 inconsistencies** were identified and **ALL RESOLVED**. A final verification check found **3 minor cosmetic issues** that need documentation updates (no logic changes required).

---

## Inconsistency #1: Missing `/api/log-event` Endpoint

### Frontend Expectation (Lines 264, 604, 612, etc.)
```
POST /api/log-event
Body: { examId, participantId, sessionId, action, details, timestamp }
Purpose: Fire-and-forget client-side event logging to server
```

### Backend Reality (36-rest-api-endpoints.md)
- **No `/api/log-event` endpoint defined**
- REST API spec covers `/exams`, `/participants`, `/wiki`, `/keys`, `/settings`
- No frontend event logging endpoint exists

### Impact
- Frontend logging architecture non-functional without endpoint
- 30+ user actions documented in frontend spec cannot be tracked

### Resolution
Add to `36-rest-api-endpoints.md`:
```
## 34.x Frontend Logging Endpoint

### POST /log-event
Public endpoint for frontend event logging

**Request Body:**
- examId (int) - Optional
- participantId (int) - Optional  
- sessionId (string) - Optional
- action (string) - Required, max 50 chars
- details (object) - Optional JSON
- timestamp (ISO8601) - Required

**Validation:**
- Rate limit: 100 events per minute per IP
- Action whitelist enforcement
- Details payload max 4KB

**Response:** 200 OK (no body, fire-and-forget)
```

---

## Inconsistency #2: Missing `/api/participate` Endpoint

### Frontend Expectation (Lines 137, 698-707)
```
POST /api/participate
Body: { examId, linkedInUrl }
Purpose: Enroll authenticated user in new exam
Response: { success, participantId, redirectUrl }
```

### Backend Reality (36-rest-api-endpoints.md, 27-participant-service.md)
- **No `/api/participate` endpoint**
- `POST /exams/{examId}/participants` exists but requires `email` and `name`
- No flow for "already authenticated user joins new exam"

### Impact
- "Participate" feature for logged-in users completely unsupported
- User must re-signup for each exam instead of one-click participation

### Resolution
Add to `36-rest-api-endpoints.md`:
```
### POST /participate
Enroll authenticated user in new exam

**Authentication:** Valid session required

**Request Body:**
- examId (int) - Required
- linkedInUrl (string) - Optional

**Behavior:**
1. Get user identity from session
2. Check not already participant in this exam
3. Create participant record with exam deadlines
4. Return participant details and redirect URL

**Response (200):** { success: true, participantId: int, redirectUrl: string }
**Response (400):** { success: false, error: "Already participating" }
```

---

## Inconsistency #3: Session Cookie Naming Convention

### Frontend Expectation (Lines 106, 214, 588)
```
Cookie Name: eqm_session_{examSlug}
Example: eqm_session_javascript-basics
Purpose: Exam-specific session isolation
```

### Backend Reality
- **No cookie naming convention defined**
- `27-participant-service.md` mentions sessions but no cookie format
- `36-rest-api-endpoints.md` mentions "session cookie" without naming

### Impact
- Frontend and backend may use different cookie names
- Session validation will fail if names don't match
- Multi-exam session isolation broken

### Resolution
Add to a new `35-session-management.md` or existing auth spec:
```
## Session Cookie Format

**Cookie Name Pattern:** `eqm_session_{examSlug}`
- examSlug: URL-safe exam slug
- Example: `eqm_session_javascript-basics`

**Cookie Attributes:**
- HttpOnly: true
- Secure: true (production)
- SameSite: Strict
- Path: /
- Expires: 7 days (default) or 30 days (remember me)
```

---

## Inconsistency #4: "Remember Me" Duration

### Frontend Expectation (Lines 69, 220, 638-651)
```
Remember Me: 30 days cookie expiration
Default session: Browser session (closes on browser close)
```

### Backend Reality
- **No "Remember Me" logic defined**
- `27-participant-service.md` has no session duration settings
- No `isRememberMe` field mentioned in any endpoint

### Impact
- Login API won't accept `isRememberMe` parameter
- Cookie duration cannot be controlled by user preference

### Resolution
Update `36-rest-api-endpoints.md` Login endpoint:
```
### POST /login
**Request Body:**
- examId (int)
- email (string)
- password (string)
- isRememberMe (boolean) - Optional, default false

**Session Duration:**
- isRememberMe=false: 7 days
- isRememberMe=true: 30 days
```

---

## Inconsistency #5: Extension Request File Upload Validation

### Frontend Expectation (Lines 560, 827)
```
File Upload Constraints:
- Formats: PDF, DOC, DOCX
- Max Size: 5 MB
```

### Backend Reality (30-extension-system.md)
```
- Supporting Documentation: File upload (optional)
- No format restrictions defined
- No size limit defined
```

### Impact
- Frontend rejects valid files backend would accept
- Or backend accepts files frontend already rejected
- Inconsistent user experience

### Resolution
Update `30-extension-system.md` Section 28.1:
```
### Supporting Documentation (optional)
- **Allowed Formats:** PDF, DOC, DOCX, PNG, JPG
- **Max File Size:** 5 MB
- **Max Files:** 3
- **Storage:** Secure uploads directory with randomized names
```

---

## Inconsistency #6: Extension Request Reason Length

### Frontend Expectation (Lines 559, 827)
```
Reason: Required, min 20 characters
```

### Backend Reality (30-extension-system.md, Line 15)
```
Reason: Required, 50-1000 characters
```

### Impact
- Frontend allows 20-49 character reasons
- Backend rejects them with 400 error
- User confusion and frustration

### Resolution
Synchronize: Use backend's stricter rule (50 chars minimum)
Update frontend spec Line 559:
```
Reason: Required, min 50 characters, max 1000 characters
```

---

## Inconsistency #7: Extension Request Days Range

### Frontend Expectation (Lines 558, 543, 825)
```
Days: 1-30 (number input with min/max)
Default: 3 days
```

### Backend Reality (30-extension-system.md, Line 13)
```
Additional Days Requested: Integer, 1-90
```

### Impact
- Frontend limits users to 30 days maximum
- Backend allows up to 90 days
- Users cannot request legitimate longer extensions

### Resolution
Align on 1-30 days (frontend preference) for UX consistency
Update `30-extension-system.md`:
```
Additional Days Requested: Integer, 1-30
(Note: Extensions >30 days require admin manual override)
```

---

## Inconsistency #8: Secret Key URL Structure

### Frontend Expectation (Line 327, 945-968)
```
URL: /{slug}?key={secretKey}
Example: /javascript-basics?key=abc123secret
Behavior: Auto-signup with generated email
```

### Backend Reality (24-secret-key-service.md, Lines 127-135)
```
URL: /{exam-slug}/{secret-key}
Example: /advanced-mathematics/EQM-a7b3c9d2e1f4g5h6i7j8k9l0m1n2o3p4-x9y2
Path-based, not query parameter
```

### Impact
- Complete URL format mismatch
- Links won't work between frontend and backend
- Secret key routing will fail

### Resolution
Standardize on backend's path-based format (cleaner URLs, better caching)
Update frontend spec:
```
URL: /{exam-slug}/{secret-key}
Example: /javascript-basics/EQM-abc123...
```

---

## Inconsistency #9: Secret Key Auto-Signup Behavior

### Frontend Expectation (Lines 956-965)
```
Auto-signup with:
- Email: generated (e.g., secret-user-{timestamp}@exam.local)
- Password: auto-generated
- WhatsApp, LinkedIn: empty
Creates participant automatically
```

### Backend Reality (24-secret-key-service.md)
- **No auto-signup behavior defined**
- Secret key validation returns exam data only
- No participant creation on key access
- Tracking cookie set, but no account created

### Impact
- Frontend expects account creation, backend doesn't do it
- Anonymous access with progress tracking may not persist properly

### Resolution
Add to `24-secret-key-service.md`:
```
### 22.x Anonymous Participant Creation

When valid secret key accessed:
1. Check if tracking cookie exists
2. If new visitor:
   - Create anonymous participant record
   - Generate email: `anon-{timestamp}-{random}@exam.local`
   - Generate secure random password (stored hashed)
   - Status: ACTIVE
   - Set tracking cookie linking to participant
3. If returning visitor (cookie exists):
   - Validate tracking cookie
   - Resume existing participant session
```

---

## Inconsistency #10: Deadline Countdown Color Scheme

### Frontend Expectation (Lines 227-232)
```
Soft Deadline Colors:
- Blue (neutral): Far away
- Yellow: Within 24 hours

Hard Deadline Colors:
- Orange: Within 3 days
- Red: Within 24 hours
```

### Backend Reality (29-deadline-engine.md, Lines 99-104)
```
Days Remaining Badge:
- Green: > 7 days
- Yellow: 3-7 days
- Orange: 1-3 days
- Red: < 24 hours
- Black: Overdue
```

### Impact
- Different color schemes confuse design implementation
- Frontend uses blue/yellow, backend uses green/yellow/orange/red/black
- No distinction between soft and hard deadline colors in backend

### Resolution
Adopt backend's more granular color scheme in frontend, with soft/hard distinction:
```
### Unified Deadline Color Scheme

Soft Deadline:
- Green: > 7 days
- Yellow: 3-7 days  
- Orange: 1-3 days
- Light Red: < 24 hours

Hard Deadline:
- Green: > 7 days
- Yellow: 3-7 days
- Orange: 1-3 days
- Dark Red: < 24 hours
- Black: Overdue (locked)
```

---

## ✅ RESOLVED - Summary Table (Issues 1-10)

| # | Issue | Status | Resolution |
|---|-------|--------|------------|
| 1 | Missing `/api/log-event` | ✅ Fixed | Added to 36-rest-api-endpoints.md |
| 2 | Missing `/api/participate` | ✅ Fixed | Added to 36-rest-api-endpoints.md |
| 3 | Session cookie naming | ✅ Fixed | Added to 36-rest-api-endpoints.md |
| 4 | Remember Me duration | ✅ Fixed | Added to 36-rest-api-endpoints.md |
| 5 | Extension file validation | ✅ Fixed | Updated 30-extension-system.md |
| 6 | Extension reason length | ✅ Fixed | Updated 30-extension-system.md |
| 7 | Extension days range | ✅ Fixed | Updated 30-extension-system.md |
| 8 | Secret key URL format | ✅ Fixed | Updated 24-secret-key-service.md |
| 9 | Secret key auto-signup | ✅ Fixed | Updated 24-secret-key-service.md |
| 10 | Deadline color scheme | ✅ Fixed | Updated 29-deadline-engine.md |

---

## ✅ RESOLVED - Issues 11-15 (Phase B.2)

---

## Inconsistency #11: Progress Tracking Cookie Naming ✅ FIXED

### Original Issue
- Frontend: `eqm_session_{examSlug}` for sessions only
- Backend: `eqm_progress_{exam_id}_{key_hash}` using IDs not slugs

### Resolution Applied
Updated `28-participant-progress.md` with unified cookie naming:
```
eqm_session_{examSlug} - Session identification
eqm_anon_{examSlug}    - Anonymous user progress  
eqm_track_{examSlug}   - Secret key visitor tracking
```

---

## Inconsistency #12: Missing Password Reset Endpoints ✅ FIXED

### Original Issue
- No `/forgot-password` or `/reset-password` endpoints

### Resolution Applied
Added to `36-rest-api-endpoints.md`:
- `POST /forgot-password` - Request password reset email
- `POST /reset-password` - Complete password reset with token
- Includes token expiration (1 hour), session invalidation, and email enumeration prevention

---

## Inconsistency #13: Section vs Item API Mismatch ✅ FIXED

### Original Issue
- Frontend uses "sections" (Markdown H2)
- Backend uses "items" (checklist)
- No mapping defined

### Resolution Applied
1. Added section-to-checklist mapping in `28-participant-progress.md` (Section 26.6)
2. Added `POST /participants/{id}/sections/{sectionNumber}/complete` endpoint in `36-rest-api-endpoints.md`
3. Documented that sections are IN_EXAM phase items with `metadata.sectionNumber`

---

## Inconsistency #14: Prerequisite Completion API ✅ FIXED

### Original Issue
- No explicit prerequisite completion endpoint
- Confusion between prerequisites and checklists

### Resolution Applied
Added to `36-rest-api-endpoints.md`:
- `POST /participants/{id}/prerequisites/{prerequisiteId}/complete`
- `DELETE /participants/{id}/prerequisites/{prerequisiteId}/complete` (undo)
- Documented that prerequisites map to PRE phase checklist items

---

## Inconsistency #15: Validate Secret Key Endpoint ✅ FIXED

### Original Issue
- Frontend expects `/api/validate-secret-key`
- Backend had `/access`

### Resolution Applied
Added `POST /validate-secret-key` endpoint in `36-rest-api-endpoints.md`:
- Accepts `{ key, examSlug }` 
- Returns `{ success, auto, exam }` matching frontend expectation
- Acts as alias/wrapper for existing `/access` logic

---

## ✅ ALL ISSUES RESOLVED - Final Summary

| # | Issue | Status | Spec Updated |
|---|-------|--------|--------------|
| 1 | Missing `/api/log-event` | ✅ Fixed | 36-rest-api-endpoints.md |
| 2 | Missing `/api/participate` | ✅ Fixed | 36-rest-api-endpoints.md |
| 3 | Session cookie naming | ✅ Fixed | 36-rest-api-endpoints.md |
| 4 | Remember Me duration | ✅ Fixed | 36-rest-api-endpoints.md |
| 5 | Extension file validation | ✅ Fixed | 30-extension-system.md |
| 6 | Extension reason length | ✅ Fixed | 30-extension-system.md |
| 7 | Extension days range | ✅ Fixed | 30-extension-system.md |
| 8 | Secret key URL format | ✅ Fixed | 24-secret-key-service.md |
| 9 | Secret key auto-signup | ✅ Fixed | 24-secret-key-service.md |
| 10 | Deadline color scheme | ✅ Fixed | 29-deadline-engine.md |
| 11 | Progress cookie naming | ✅ Fixed | 28-participant-progress.md |
| 12 | Forgot password flow | ✅ Fixed | 36-rest-api-endpoints.md |
| 13 | Section vs Item API | ✅ Fixed | 28-participant-progress.md, 36-rest-api-endpoints.md |
| 14 | Prerequisite completion | ✅ Fixed | 36-rest-api-endpoints.md |
| 15 | Validate secret key naming | ✅ Fixed | 36-rest-api-endpoints.md |

---

## ✅ RESOLVED - Issues 16-19 (Phase B.3)

---

## Inconsistency #16: Participant Status Mismatch ✅ FIXED

### Original Issue
- SHARED-CONSTANTS.md used `PENDING`, `IN_PROGRESS`, `SUSPENDED`, `ARCHIVED`
- Backend enums used `INVITED`, `PAUSED`, `WITHDRAWN`, `EXTENDED`
- Status names completely different

### Resolution Applied
Updated `SHARED-CONSTANTS.md` to align with backend `ParticipantStatus` enum:
- `PENDING` → `INVITED` (initial state)
- `IN_PROGRESS` → removed (use `ACTIVE`)
- `SUSPENDED` → `PAUSED` (temporary hold)
- `ARCHIVED` → `WITHDRAWN` (voluntary dropout)
- Added `EXTENDED` status for extension grants
- Added full status transition rules table

---

## Inconsistency #17: Extension Request Endpoint ✅ FIXED

### Original Issue
- Frontend uses `POST /api/request-extension`
- Backend uses `POST /participants/{id}/extensions`
- Different URL patterns

### Resolution Applied
Added to `36-rest-api-endpoints.md`:
- `POST /participants/{id}/extensions` - Full endpoint with request body
- `POST /request-extension` - Frontend-friendly alias (gets participant ID from session)
- Both use same logic, different access patterns

---

## Inconsistency #18: File Upload Limits Conflict ✅ FIXED

### Original Issue
- SHARED-CONSTANTS: 5MB, PDF/DOC/DOCX only, 3 files
- EvidenceType enum: 10MB, includes XLS/PPT/TXT/ZIP

### Resolution Applied
Updated `SHARED-CONSTANTS.md` with context-specific limits:
- Extension requests: 5MB, PDF/DOC/DOCX, 3 files (stricter)
- General evidence FILE: 10MB, full format list, 5 files
- Evidence IMAGE: 5MB, JPG/PNG/GIF/WEBP, 10 files

Added cross-reference notes in `06-enums-constants.md` pointing to SHARED-CONSTANTS for context-specific overrides.

---

## Inconsistency #19: Signup Status (PENDING vs INVITED) ✅ FIXED

### Original Issue
- Frontend expected `PENDING` for email verification
- Backend uses `INVITED` as initial state

### Resolution Applied
Aligned on backend terminology:
- `INVITED` = Initial state (not yet started, awaiting first login)
- Email verification is optional (configurable in admin)
- Status transitions documented in SHARED-CONSTANTS.md

---

## ✅ ALL 19 ISSUES RESOLVED - Final Summary

| # | Issue | Status | Spec Updated |
|---|-------|--------|--------------|
| 1 | Missing `/api/log-event` | ✅ Fixed | 36-rest-api-endpoints.md |
| 2 | Missing `/api/participate` | ✅ Fixed | 36-rest-api-endpoints.md |
| 3 | Session cookie naming | ✅ Fixed | 36-rest-api-endpoints.md |
| 4 | Remember Me duration | ✅ Fixed | 36-rest-api-endpoints.md |
| 5 | Extension file validation | ✅ Fixed | 30-extension-system.md |
| 6 | Extension reason length | ✅ Fixed | 30-extension-system.md |
| 7 | Extension days range | ✅ Fixed | 30-extension-system.md |
| 8 | Secret key URL format | ✅ Fixed | 24-secret-key-service.md |
| 9 | Secret key auto-signup | ✅ Fixed | 24-secret-key-service.md |
| 10 | Deadline color scheme | ✅ Fixed | 29-deadline-engine.md |
| 11 | Progress cookie naming | ✅ Fixed | 28-participant-progress.md |
| 12 | Forgot password flow | ✅ Fixed | 36-rest-api-endpoints.md |
| 13 | Section vs Item API | ✅ Fixed | 28-participant-progress.md, 36-rest-api-endpoints.md |
| 14 | Prerequisite completion | ✅ Fixed | 36-rest-api-endpoints.md |
| 15 | Validate secret key naming | ✅ Fixed | 36-rest-api-endpoints.md |
| 16 | Participant status mismatch | ✅ Fixed | SHARED-CONSTANTS.md |
| 17 | Extension request endpoint | ✅ Fixed | 36-rest-api-endpoints.md |
| 18 | File upload limits conflict | ✅ Fixed | SHARED-CONSTANTS.md, 06-enums-constants.md |
| 19 | Signup status naming | ✅ Fixed | SHARED-CONSTANTS.md |

---

## Completed Phases

1. ~~**Phase B**: Apply fixes to backend specs (1-10)~~ ✅ COMPLETE
2. ~~**Phase B.2**: Fix remaining inconsistencies (11-15)~~ ✅ COMPLETE
3. ~~**Phase B.3**: Fix final inconsistencies (16-19)~~ ✅ COMPLETE
4. **Next**: Start Frontend Split Batch 1 or archive full backend spec

---

## ✅ MINOR ISSUES (Phase B.4) - ALL FIXED

These cosmetic/documentation inconsistencies have been resolved:

---

### Minor Issue #20: Extension Request File Formats ✅ FIXED

**Original Issue:**
- Frontend spec: PDF/DOC/DOCX only
- Backend spec: PDF, DOC, DOCX, PNG, JPG

**Resolution Applied:**
Updated `frontend-full-spec.md` Lines 560 and 827 to include PNG/JPG:
```
File: Optional, PDF/DOC/DOCX/PNG/JPG, max 5 MB, max 3 files
```

---

### Minor Issue #21: API Path Prefix Clarification ✅ FIXED

**Original Issue:**
- Frontend spec uses `/api/` prefix
- Backend uses `/wp-json/eqm/v1/`

**Resolution Applied:**
Added "API Path Convention" section at top of `frontend-full-spec.md`:
```
> **IMPORTANT:** Throughout this document, API endpoints use the shorthand `/api/` prefix.
> **Actual WordPress REST API base path:** `/wp-json/eqm/v1/`
```
With example mapping table for clarity.

---

### Minor Issue #22: Report Typo ✅ FIXED

**Original Issue:** Report said "15" instead of "19"

**Resolution Applied:** Fixed in previous update.

---

## Summary of Minor Issues

| # | Issue | Status |
|---|-------|--------|
| 20 | PNG/JPG in extension uploads | ✅ Fixed |
| 21 | `/api/` path prefix clarification | ✅ Fixed |
| 22 | "15" vs "19" typo | ✅ Fixed |

---

## Final Verification Status

**Major Inconsistencies:** 0 remaining (all 19 fixed)
**Minor Issues:** 0 remaining (all 3 fixed)
**Logic Conflicts:** None

The specifications are now **fully synchronized and production-ready** for implementation.

---

*Last updated: January 25, 2026 - All 19 major inconsistencies + 3 minor issues resolved*
