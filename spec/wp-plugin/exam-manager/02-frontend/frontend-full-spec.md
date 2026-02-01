# ITERATION 2 (FRONTEND SPECIFICATION): COMPREHENSIVE FRONTEND SPEC

> **Version:** 2.0.1 FINAL  
> **Status:** Frontend/Public-Facing Requirements (COMPLETE)  
> **Target Platform:** WordPress 6.0+ with PHP 8.0+  
> **Date Compiled:** January 25, 2026

---

## 📋 Table of Contents

1. [API Path Convention](#api-path-convention)
2. [Frontend Logging Approach](#frontend-logging-approach)
3. [Original Input](#original-input)
4. [Proofread Prompt](#proofread-prompt)
5. [Core Frontend Functionality](#core-frontend-functionality)
6. [Frontend Requirements Breakdown](#frontend-requirements-breakdown)
7. [Page Structure & Navigation](#page-structure--navigation)
8. [User Flows (Step-by-Step)](#user-flows-step-by-step)
9. [Edge Cases & Error States](#edge-cases--error-states)
10. [Frontend Logging (API-Based)](#frontend-logging-api-based)
11. [UI/UX Principles](#uiux-principles)
12. [Frontend Dependencies & Tech Stack](#frontend-dependencies--tech-stack)

---

## API Path Convention

> **IMPORTANT:** Throughout this document, API endpoints use the shorthand `/api/` prefix for readability.
>
> **Actual WordPress REST API base path:** `/wp-json/eqm/v1/`
>
> **Example mappings:**
> | This Document | Actual Endpoint |
> |---------------|-----------------|
> | `POST /api/login` | `POST /wp-json/eqm/v1/login` |
> | `POST /api/signup` | `POST /wp-json/eqm/v1/signup` |
> | `POST /api/log-event` | `POST /wp-json/eqm/v1/log-event` |
> | `POST /api/request-extension` | `POST /wp-json/eqm/v1/request-extension` |
>
> See `SHARED-CONSTANTS.md` and `36-rest-api-endpoints.md` for the complete API reference.

---

## Frontend Logging Approach

**Question**: When you say "use logging in front to log for steps since php logging possible from frontend" — do you mean:

1. Frontend JavaScript should make API calls to the backend to trigger PHP logging? (i.e., frontend logs actions by sending them to backend, which logs to the server log files)
2. Or do you want frontend to log to browser console AND also send those logs to the backend for server-side logging?

**Assumption (proceeding with this)**: Frontend will make lightweight API calls (e.g., `POST /api/log-event`) to backend, which logs to `/wp-content/uploads/exam-questions-manager/logs/plugin.log`. This tracks user journey client-side but persists it server-side.

---

## Original Input (Verbatim)

Front end doesn't require table design but most details about the steps of the front end and most details for how the UI should behave and how the it should display prereq, login, public view, markdown display, card way to display markdown section, and sub exams under the main exam, youtube videos prereq.

Also use logging in front to log for steps since php logging possible from front end.

---

## Proofread Prompt

You are building the **Frontend (Public-Facing) Interface** for the "Exam Questions Manager" plugin. The frontend is a single-page application (SPA) or multi-page application serving unauthenticated visitors, new participants (signup), existing participants (login & exam dashboard), and authenticated exam-takers.

---

## Core Frontend Functionality

### 1. Public Landing Page (`/{slug}`)

- Display exam metadata (title, description, soft/hard deadlines)
- Display prerequisite overview (videos, links, checklist items count)
- Display signup form OR login prompt (if user already has account)
- If visitor has valid session cookie: Show different state (logged in)
- Responsive design (mobile, tablet, desktop)
- All actions logged to backend via API calls

### 2. Authentication Pages

- **Signup Page** (`/{slug}` by default, or explicit `/signup`):
    - Form: Email, WhatsApp, LinkedIn, Password, Confirm Password
    - Clear, step-by-step validation (client-side and server-side)
    - Success redirects to dashboard; failure shows specific error
- **Login Page** (`/{slug}/login`):
    - Form: Email, Password, "Remember me for 30 days" checkbox
    - Error handling for invalid credentials
    - Link back to signup form
    - Success redirects to dashboard; failure stays on login
- **Logout** (`/{slug}/logout`):
    - Clear session, show confirmation message
    - Redirect to landing page

### 3. Participate in New Exam (Authenticated User)

**NEW FEATURE**: When a user is already logged in/signed up and navigates to a **new exam/assignment URL** they haven't participated in yet:

- **Do NOT show login/signup form** (user is already authenticated)
- **Show "Participate" button** instead
- When user clicks "Participate":
    1. Display **confirmation dialog** with:
        - Exam title and description
        - "You are about to join this exam. Please confirm your details."
        - Pre-filled Email (from current session, read-only)
        - LinkedIn URL field (editable, may be pre-filled from previous exam)
        - Checkbox: "I confirm I want to participate in this exam"
    2. User confirms by clicking "Confirm Participation"
    3. Backend creates new participant record for this exam
    4. User is enrolled with:
        - Same deadline calculation (soft/hard deadlines from exam settings)
        - New progress tracking for this specific exam
        - Same user identity across exams
    5. User is redirected to the new exam's dashboard
- **Logging**: Log `participateConfirmed` action with examId, email, linkedInUrl

#### Participate Flow (Step-by-Step)

**Starting Point**: Authenticated user visits `domain.com/{new-slug}` for an exam they haven't joined

**Steps**:

1. Frontend loads landing page for `{new-slug}`
2. Check for session cookie `eqm_session_{examSlug}` → Cookie exists (from another exam)
3. Check if user is already a participant in THIS exam via API call
4. User is NOT a participant in this exam → Show "Participate" button
5. Display:
    - Exam title, description, prerequisites overview
    - "Participate" button (NOT signup/login form)
    - Deadline info
6. User clicks "Participate"
7. Frontend shows **Confirmation Dialog**:
    ```
    ┌─────────────────────────────────────────────┐
    │  Join "[Exam Title]"                        │
    │                                             │
    │  You are about to participate in this exam. │
    │  Please confirm your details below.         │
    │                                             │
    │  Email: john@example.com (read-only)        │
    │  LinkedIn: [___________________________]    │
    │                                             │
    │  [ ] I confirm I want to participate        │
    │                                             │
    │  [Cancel]              [Confirm & Join]     │
    └─────────────────────────────────────────────┘
    ```
8. User enters/confirms LinkedIn URL
9. User checks confirmation checkbox
10. User clicks "Confirm & Join"
11. Frontend validates:
    - Confirmation checkbox is checked
    - LinkedIn URL format is valid (if provided)
12. If validation fails: Show inline errors, stay on dialog
13. If validation passes: POST to `/api/participate` with `{examId, email, linkedInUrl}`
14. Show loading: "Joining exam..."
15. Backend creates participant record with:
    - Same user identity (userId from session)
    - Exam-specific deadlines calculated
    - Status: ACTIVE
    - Progress: 0%
16. Backend response:
    - Success (200): `{success: true, redirectUrl: "/{new-slug}/dashboard"}`
    - Failure (400): `{success: false, error: "Already participating"}`
17. On success:
    - Log to backend: `{action: "participateConfirmed", examId: 12, email: "john@example.com"}`
    - Redirect to `/{new-slug}/dashboard`
    - Show welcome message: "Welcome to [Exam Title]!"
18. On failure:
    - Show error in dialog
    - Allow retry or close

**Duration**: 1-2 minutes
**User Actions Logged**: participateViewed, participateDialogOpened, participateConfirmed, participateFailed

### 4. Prerequisite Display (Pre-Checklist)

- List all prerequisites in order: videos, links, checklist items
- Each video shows: title, description, YouTube/Vimeo embed or clickable link
- Each link shows: title, description, clickable "Open in new tab" button
- Each checklist item shows: title, description, checkbox (participant checks off manually)
- If prerequisite is `isRequired=true`: Show "Required" badge
- Participant can mark checklist items as done; this persists to `participantChecklist` table
- Cannot proceed to exam sections until all required prerequisites marked done
- Visual indicator: "X of Y prerequisites completed"

### 5. Markdown Display (Main Exam Content)

- Parse markdown from exam file
- Display H1 as title, H2 as section headers
- Render markdown syntax: paragraphs, lists, code blocks, emphasis, links
- **Section Cards Layout**:
    - Each H2 header becomes a "section card"
    - Card displays: section title, preview text (first 100 chars), completion checkbox
    - Click card to expand and see full section content
    - Card states: not-started, in-progress, completed
    - Color coding: incomplete (neutral), in-progress (yellow), completed (green)
- **Expandable Section View**:
    - When expanded: show full markdown content for that section
    - Show "Mark as Done" button
    - Show progress indicator: "Section 3 of 8"
    - Show navigation: Previous/Next section buttons
- **In-Exam Checklist** (optional):
    - Display alongside section content or in sidebar
    - Show checkpoints/rubric items participant should track while reading

### 6. Sub-Exams Display (Hierarchical)

- If parent exam has children (sub-exams):
    - Display "Sub-Exams" section on landing page and dashboard
    - List each child exam as clickable card: title, status, participant progress
    - Click to enter child exam
- If participant is viewing child exam:
    - Show breadcrumb: "Parent Exam > Child Exam" (clickable)
    - Click breadcrumb to navigate back to parent
- Display sub-exams as independent progress trackers
- Example: "Advanced JavaScript" (parent) contains:
    - "ES6 Fundamentals" (child) — 5 of 8 sections done
    - "Async Programming" (child) — 3 of 6 sections done

### 7. YouTube Video Prerequisites

- Display video title and description
- Embed video using iframe: `<iframe src="https://www.youtube.com/embed/{videoId}"></iframe>`
- For Vimeo: `<iframe src="https://player.vimeo.com/{videoId}"></iframe>`
- Show "Watch video" or "Open in YouTube" link (opens in new tab)
- Participant clicks checkbox "I've watched this" to mark prerequisite done
- No tracking of actual video watch time (honor system)

### 8. Session & Cookie Persistence

- **On Load**: Check if session cookie `eqm_session_{examSlug}` exists
- **If Cookie Exists**: Validate session via backend API call
    - If valid: Show authenticated state (dashboard, exam content, logout button)
    - If expired: Clear cookie, redirect to login page with message "Session expired. Please login again."
- **If Cookie Missing**: Show public/unauthenticated state (landing page, signup/login forms)
- **Sliding Expiration**: On each authenticated request, session expiry extends
- **Remember Me**: If session has `isRememberMe=true`, keep cookie for 30 days

### 9. Deadline Countdown Display

- **Soft Deadline Countdown**:
    - Show on dashboard: "Soft deadline in 2 days, 3 hours" or "Soft deadline: Jan 27, 1:00 PM"
    - On exam view: "You have 2 days to soft deadline. After that, you'll have a hard deadline."
    - Display color: neutral (blue) if deadline far away, yellow if within 24 hours
- **Hard Deadline Countdown**:
    - Show on dashboard: "Hard deadline in 5 days, 23 hours" or "Hard deadline: Jan 31, 1:00 PM"
    - After soft deadline reached: "Past soft deadline. Hard deadline in X days."
    - On exam view: "You have X days to hard deadline. After that, exam is locked."
    - Display color: red if within 24 hours, orange if within 3 days
- **Hard Deadline Reached**:
    - Exam locked. Cannot mark sections.
    - Show: "Your hard deadline has passed. Exam is now locked. Request an extension."
    - Display extension request form
- **Extension Deadline** (if approved):
    - Show on dashboard: "Extension deadline: Feb 3, 1:00 PM (3 days remaining)"
    - After extension expires: Exam re-locked

### 10. Client-Side Validation & Error Handling

- **Form Validation**:
    - Email format validation (regex or HTML5)
    - Password requirements display (min 8 chars, etc.)
    - Confirm password match validation
    - Show validation errors inline as user types
- **API Error Handling**:
    - Network error: "Unable to connect. Please check your internet."
    - 400 Bad Request: Show specific error message from backend
    - 401 Unauthorized: Redirect to login (session invalid/expired)
    - 500 Server Error: "An error occurred. Please try again later."
    - All errors logged to backend via API

### 11. Frontend-to-Backend API Logging

- Every significant user action logged via API call to backend
- Actions logged:
    - Page views: landing page, dashboard, section expanded, etc.
    - Forms: signup attempted, login attempted, extension request submitted
    - Exam actions: section marked done, prerequisite checked off, etc.
    - Errors: form validation errors, API failures, etc.
    - Navigation: clicked previous/next section, clicked breadcrumb, etc.
- API endpoint: `POST /api/log-event` (backend creates log entry in `/logs/plugin.log`)
- Log format: `[TIMESTAMP] USER_ACTION participantId={id} examId={examId} action="{actionName}" details={json}`
- Logging is fire-and-forget (doesn't block user interactions)
- Errors in logging don't interrupt user experience

---

## Frontend Requirements Breakdown

### Page Structure & Navigation

#### Landing Page (`/{slug}`)

**Layout**:

```
┌─────────────────────────────────────────┐
│  Header: Exam Title                     │
│  Navigation: Home | Login (if not auth) │
│              Home | Participate (if auth but not in exam) │
│              Home | Dashboard (if participating) │
└─────────────────────────────────────────┘
│
├─ Section 1: Exam Overview
│  ├─ Title (H1)
│  ├─ Description
│  ├─ Deadline Info (soft, hard)
│  └─ "Prerequisites: X items to complete"
│
├─ Section 2: Prerequisites (Pre-Checklist)
│  ├─ "Complete these before starting:"
│  ├─ Video 1: [Watch video] [YouTube link]
│  ├─ Link 1: [Open article] (opens in new tab)
│  ├─ Checklist item 1: [ ] Mark as done
│  ├─ Progress: "3 of 5 prerequisites completed"
│  └─ (Only show if NOT authenticated OR prerequisite list empty)
│
├─ Section 3: Authentication / Participation
│  ├─ If NOT logged in:
│  │  ├─ Signup form OR Login form
│  │  └─ Toggle: "Don't have account? Sign up" / "Already registered? Login"
│  ├─ If logged in BUT not participating in this exam:
│  │  ├─ "You're logged in as [Email]"
│  │  ├─ "Participate" button → Opens confirmation dialog
│  │  └─ "Logout" button
│  └─ If logged in AND participating:
│     ├─ "Welcome, [Email]"
│     ├─ "Continue Exam" button → Dashboard
│     └─ "Logout" button
│
├─ Section 4: Sub-Exams (if any)
│  ├─ "Sub-Exams" heading
│  ├─ Card 1: "Sub-Exam Title" | Status | Progress
│  └─ Card 2: "Sub-Exam Title" | Status | Progress
│
└─ Footer: Info, Contact
```

**Behavior**:

- If participant has session cookie and it's valid AND is participating in THIS exam: show "Continue Exam" button
- If participant has session cookie and it's valid BUT NOT participating in THIS exam: show "Participate" button
- If participant doesn't have cookie: show signup form (with "Already registered? Login" link)
- If participant visits via secret key (`/{slug}?key={secretKey}`): auto-signup, create session, redirect to dashboard
- All section visibility depends on authentication state and participation status

---

#### Login Page (`/{slug}/login`)

**Layout**:

```
┌─────────────────────────────────────────┐
│  Header: Exam Title | Back to Signup    │
└─────────────────────────────────────────┘
│
├─ "Login" heading
├─ Form:
│  ├─ Email input (with validation)
│  ├─ Password input (with "forgot password?" link, optional)
│  ├─ Checkbox: "Remember me for 30 days"
│  └─ Buttons: "Login" (primary), "Back to Signup" (secondary)
│
├─ Error message area (shows validation or auth errors)
│
└─ Footer
```

**Form Validation**:

- Email: must match regex `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`
- Password: cannot be empty
- Show error messages inline: "Invalid email format" or "Password is required"
- Disable "Login" button while request in progress
- Show spinner/loading indicator during submit

**Submit Behavior**:

1. Frontend validates client-side (email format, password not empty)
2. If validation fails: show error, stay on form, log to backend
3. If validation passes: send POST to `/api/login` with `{examId, email, password, isRememberMe}`
4. Backend returns:
    - Success (200): `{success: true, redirectUrl: "/{slug}/dashboard"}`
    - Failure (401): `{success: false, error: "Invalid email or password"}`
5. On success: set session cookie (backend already did), redirect to dashboard
6. On failure: show error message, stay on login form, allow retry

---

#### Dashboard (`/{slug}/dashboard`)

**Protected Route**: Requires valid session cookie. If missing/expired, redirect to login.

**Layout**:

```
┌─────────────────────────────────────────┐
│  Header: Exam Title                     │
│  Navigation: Dashboard | Exam | Logout  │
│  User: Welcome, [Email] | Profile icon  │
└─────────────────────────────────────────┘
│
├─ Section 1: Exam Status & Deadlines
│  ├─ Title: "[Exam Title]"
│  ├─ Status badge: "Active" / "Soft Deadline Reached" / "Hard Deadline Reached" / "Locked" / "Completed"
│  ├─ Progress bar: "5 of 8 sections completed (62%)"
│  │
│  ├─ Deadline Info Box:
│  │  ├─ Soft Deadline: "Jan 27, 1:00 PM (in 2 days, 3 hours)"
│  │  ├─ Hard Deadline: "Jan 31, 1:00 PM (in 6 days, 23 hours)"
│  │  └─ (If extended) Extension Deadline: "Feb 3, 1:00 PM (in 9 days, 23 hours)"
│  │
│  └─ CTA Button: "Continue Exam" (if not completed), "View Results" (if completed)
│
├─ Section 2: Prerequisites Status (Pre-Checklist)
│  ├─ "Prerequisites: 4 of 5 completed"
│  ├─ Expandable list of prerequisites (collapsed by default)
│  └─ [+] Show / [-] Hide prerequisites
│
├─ Section 3: Exam Sections (Main Roadmap)
│  ├─ Card layout (grid or list)
│  ├─ Section Card 1:
│  │  ├─ Title: "Section 1: Introduction"
│  │  ├─ Preview text (first 80 chars)
│  │  ├─ Status indicator: ✓ Completed / ⟳ In Progress / ○ Not Started
│  │  ├─ Click to expand section
│  │  └─ [Click to read section]
│  │
│  ├─ Section Card 2: (similar)
│  ├─ Section Card 3: (similar)
│  │
│  └─ (All sections visible, showing individual progress)
│
├─ Section 4: In-Exam Checklist (if any)
│  ├─ "Checklist: 3 of 5 items" (optional, if exam has in-exam checklist)
│  ├─ Expandable list
│  └─ [+] Show / [-] Hide checklist
│
└─ Footer
```

**Section Cards Styling**:

- **Not Started**: Light gray background, ○ icon, "Not started" text
- **In Progress**: Light yellow/amber background, ⟳ icon, "In progress" text
- **Completed**: Light green background, ✓ checkmark icon, "Completed" text
- **Locked** (past hard deadline): Light red background, 🔒 icon, "Locked" text

**Exam Status Badge Styling**:

- **Active**: Green, "Active"
- **Soft Deadline Reached**: Amber/orange, "Soft deadline reached"
- **Hard Deadline Reached**: Red, "Hard deadline reached"
- **Locked**: Red, "Locked - Extension needed"
- **Completed**: Green, "Completed ✓"

**Behavior**:

- Dashboard loads participant data: name, email, exam status, progress, deadlines
- All deadlines display as countdown AND absolute date/time (e.g., "2 days, 3 hours" + "Jan 27, 1:00 PM")
- Click section card to navigate to section view
- If participant's status is LOCKED: show message "Exam is locked. Request extension below."
- If participant completed exam: show "Congratulations!" message and summary stats

---

#### Exam Section View (`/{slug}/section/{sectionNumber}`)

**Protected Route**: Requires valid session + exam not locked (unless extended).

**Layout**:

```
┌─────────────────────────────────────────┐
│  Header: Exam Title                     │
│  Breadcrumb: Dashboard > Section 3      │
│  Navigation: [←] Previous | [→] Next    │
└─────────────────────────────────────────┘
│
├─ Section Header
│  ├─ Progress indicator: "Section 3 of 8"
│  ├─ Title: (H2 header from markdown)
│  ├─ Deadline status (if applicable):
│  │  ├─ If past soft deadline: "⚠ You're past soft deadline"
│  │  ├─ If past hard deadline: "🔒 Exam locked. Cannot mark as done."
│  │  └─ If extended and approaching extension deadline: "⚠ Extension deadline: X days"
│  │
│  └─ Completion badge: (not shown yet, appears after marked done)
│
├─ Content Area
│  ├─ Full markdown content for section (paragraphs, lists, code blocks, etc.)
│  ├─ All links open in new tabs
│  ├─ Code blocks with syntax highlighting (if markdown has code)
│  │
│  └─ (If exam has in-exam checklist) Sidebar:
│     ├─ "In-Exam Checklist"
│     ├─ List of checklist items for this section
│     ├─ Checkboxes for participant to track
│     └─ (These are NOT required; just for guidance)
│
├─ Action Buttons
│  ├─ If section not marked done:
│  │  └─ "Mark as Done" button (primary)
│  ├─ If section marked done:
│  │  ├─ "✓ Completed" badge (read-only)
│  │  └─ "Undo" button (secondary, optional)
│  │
│  └─ (If locked) Disabled button: "Mark as Done (exam locked)"
│
├─ Navigation
│  ├─ [← Previous Section] button (if not first section)
│  ├─ Progress: "3 of 8 completed"
│  └─ [Next Section →] button (if not last section)
│
├─ Timeline View (optional sidebar)
│  ├─ Visual timeline of all sections
│  ├─ Current section highlighted
│  ├─ Completed sections marked with ✓
│  └─ Click timeline items to jump to section
│
└─ Footer
```

**Content Rendering**:

- Parse and render markdown:
    - Headings (H1-H6)
    - Paragraphs
    - Lists (ordered, unordered, nested)
    - Code blocks (inline and fenced with syntax highlighting)
    - Links (open in new tab with `target="_blank"`)
    - Emphasis (bold, italic, strikethrough)
    - Blockquotes
    - Tables (GFM support)
- Preserve markdown formatting exactly as authored
- Display section dividers between sections

---

#### Extension Request Page (`/{slug}/extend-deadline`)

**Protected Route**: Requires session + exam is locked (hard deadline passed)

**Layout**:

```
┌─────────────────────────────────────────┐
│  Header: Exam Title                     │
│  Back to Dashboard link                 │
└─────────────────────────────────────────┘
│
├─ "Request Extension" heading
├─ Info Box:
│  ├─ "Your hard deadline has passed."
│  ├─ "Original Hard Deadline: Jan 31, 1:00 PM"
│  └─ "Progress: 5 of 8 sections completed"
│
├─ Form:
│  ├─ "How many days do you need?" input (1-30, default 3)
│  ├─ "Why do you need an extension?" textarea (required, min 20 chars)
│  ├─ "Supporting document (optional)" file upload
│  └─ Submit button: "Submit Request"
│
├─ Previous Requests (if any):
│  ├─ Request 1: "Jan 28 - 5 days requested - Pending"
│  ├─ Request 2: "Jan 25 - 3 days requested - Rejected (reason: ...)"
│  └─ (Show status badges)
│
└─ Footer
```

**Form Validation**:

- Days: 1-30 (number input with min/max)
- Reason: Required, min 50 characters (synchronized with backend)
- File: Optional, but if provided: PDF/DOC/DOCX/PNG/JPG, max 5 MB, max 3 files

**Submit Behavior**:

1. Validate client-side
2. If valid: POST to `/api/request-extension` with FormData
3. Show loading spinner
4. On success: Show success message, display request in "Previous Requests" with "Pending" status
5. On failure: Show error, allow retry

**Extension Status Display**:

- Status indicators:
    - **Pending**: Orange, "Awaiting admin review"
    - **Approved**: Green, "Approved for X days" + new deadline
    - **Rejected**: Red, "Rejected" + rejection reason
- Allow participant to submit new request (even if previous rejected)

---

## User Flows (Step-by-Step)

### Flow 1: New Participant Signup

**Starting Point**: Participant visits `domain.com/{slug}` for first time

**Steps**:

1. Frontend loads landing page
2. Check for session cookie `eqm_session_{examSlug}`
3. Cookie missing → Show signup form
4. Display: Exam title, description, prerequisite overview, deadline info
5. Participant enters: Email, WhatsApp, LinkedIn, Password, Confirm Password
6. Frontend validates on blur/change:
    - Email format: Show ✓ or ✗ icon
    - Password requirements: Display checklist (min 8 chars, etc.)
    - Confirm password match: Show ✓ or ✗ when both filled
7. Participant clicks "Sign Up"
8. Frontend validates client-side:
    - All fields not empty
    - Email format valid
    - Password min 8 chars
    - Passwords match
9. If validation fails: Show specific errors, stay on form
    - Log to backend: `POST /api/log-event {action: "signupFailed", reason: "validationError", details: {...}}`
10. If validation passes: POST to `/api/signup` with form data
11. Show loading spinner: "Creating account..."
12. Backend response:
    - Success (200): `{success: true, redirectUrl: "/{slug}/dashboard"}`
        - Session cookie set by backend
    - Failure (400): `{success: false, error: "Email already registered for this exam"}`
13. On success:
    - Log to backend: `POST /api/log-event {action: "signupSuccess", email: "john@example.com"}`
    - Session cookie already in browser (set by backend)
    - Redirect to `/{slug}/dashboard`
    - Show welcome message: "Welcome to [Exam Title]!"
14. On failure:
    - Show error: "Email already registered. Please login instead."
    - Provide "Go to Login" link
    - Log error to backend

**Duration**: 2-5 minutes
**User Actions Logged**: signup attempted, validation errors, signup success

---

### Flow 2: Existing Participant Login

**Starting Point**: Participant visits `domain.com/{slug}`, already has account

**Steps**:

1. Frontend loads landing page
2. Check for session cookie
3. Cookie missing → Show signup form
4. Participant clicks "Already registered? Login here"
5. Frontend navigates to `/{slug}/login`
6. Display: Email and password input, "Remember me" checkbox
7. Participant enters: Email, Password
8. Participant (optional) checks "Remember me for 30 days"
9. Participant clicks "Login"
10. Frontend validates:
    - Email not empty
    - Password not empty
    - Email format valid
11. If validation fails: Show errors, stay on login form
12. If validation passes: POST to `/api/login` with `{email, password, isRememberMe}`
13. Show loading spinner: "Logging in..."
14. Backend response:
    - Success (200): `{success: true, redirectUrl: "/{slug}/dashboard"}`
        - Session cookie set by backend (HttpOnly, Secure)
        - Cookie expiry: 30 days if isRememberMe=true, else browser session
    - Failure (401): `{success: false, error: "Invalid email or password"}`
15. On success:
    - Log to backend: `POST /api/log-event {action: "loginSuccess", email: "john@example.com", isRememberMe: true}`
    - Redirect to `/{slug}/dashboard`
    - Session cookie present in browser
    - Show message: "Welcome back, John!"
16. On failure:
    - Show error: "Invalid email or password. Please try again."
    - Log to backend: `{action: "loginFailed", email: "john@example.com", reason: "invalidCredentials"}`
    - Suggest: "Don't have account? Sign up" link
    - Stay on login form, allow retry

**Duration**: 1-2 minutes
**User Actions Logged**: login attempted, login success/failure, remember me selection

---

### Flow 3: Authenticated User Participates in New Exam

**Starting Point**: User is logged in from another exam, visits `domain.com/{new-slug}` for a different exam

**Steps**:

1. Frontend loads landing page for `{new-slug}`
2. Check for session cookie → Cookie exists (from previous exam session)
3. Validate session via backend API call → Session valid
4. Check if user is participant in THIS exam via API call → NOT a participant
5. Display:
    - Exam title, description, prerequisites overview
    - Message: "You're logged in as john@example.com"
    - **"Participate" button** (NOT signup/login form)
    - "Logout" link
6. User clicks "Participate"
7. Frontend opens **Confirmation Dialog**:
    - Title: "Join [Exam Title]"
    - Pre-filled email (read-only from session)
    - LinkedIn URL input (editable)
    - Confirmation checkbox: "I confirm I want to participate"
    - Buttons: "Cancel" and "Confirm & Join"
8. User reviews/enters LinkedIn URL
9. User checks confirmation checkbox
10. User clicks "Confirm & Join"
11. Frontend validates:
    - Checkbox is checked
    - LinkedIn URL is valid format (if provided)
12. If validation fails: Show inline errors in dialog
13. If validation passes: POST to `/api/participate` with `{examId, linkedInUrl}`
14. Show loading: "Joining exam..."
15. Backend:
    - Creates new participant record for this exam
    - Links to existing user identity
    - Calculates soft/hard deadlines from exam settings
    - Sets status to ACTIVE
16. Backend response:
    - Success (200): `{success: true, participantId: 456, redirectUrl: "/{new-slug}/dashboard"}`
    - Failure (400): `{success: false, error: "Already participating in this exam"}`
17. On success:
    - Log to backend: `{action: "participateConfirmed", examId: 12, email: "john@example.com"}`
    - Close dialog
    - Redirect to `/{new-slug}/dashboard`
    - Show welcome message: "Welcome to [Exam Title]! Your deadlines have been set."
18. On failure:
    - Show error in dialog
    - Allow close or retry

**Duration**: 1-2 minutes
**User Actions Logged**: participateLandingViewed, participateDialogOpened, participateConfirmed, participateFailed

---

### Flow 4: Browse Prerequisites (Before Exam)

**Starting Point**: Participant on landing page or dashboard, prerequisites not yet completed

**Steps**:

1. Participant sees "Prerequisites: 4 of 5 completed" section
2. Participant clicks "[+] Show prerequisites"
3. Expand to show all prerequisite items in order:
    - "Video 1: JavaScript Basics" with YouTube embed and description
    - "Link 1: MDN JavaScript Guide" with clickable link
    - "Checklist Item: Set up Node.js" with unchecked checkbox
4. Participant watches video (embedded YouTube player)
5. When done watching (honor system), participant clicks "Mark as watched" or checkbox next to video
6. Frontend POSTs to `/api/mark-prerequisite-done` with `{examId, prerequisiteId}`
7. Backend creates/updates `participantChecklist` record
8. Frontend:
    - Show ✓ checkmark next to completed item
    - Update progress: "5 of 5 completed"
    - Log to backend: `{action: "prerequisiteCompleted", type: "video", title: "JavaScript Basics"}`
9. Participant clicks "Link 1: MDN JavaScript Guide" → Opens in new tab
10. Participant reads article, returns to exam page
11. Participant clicks checkbox next to "Link 1"
12. Frontend marks as done (same process as video)
13. Participant manually checks off "Checklist Item: Set up Node.js"
14. When all required prerequisites checked:
    - Show "All prerequisites completed! ✓"
    - Unlock "Start Exam" button
    - Log to backend: `{action: "allPrerequisitesCompleted"}`
15. Participant clicks "Start Exam" → Redirected to `/dashboard` or first section

**Duration**: Variable (depends on video length + reading)
**User Actions Logged**: prerequisite viewed, video clicked, link clicked, prerequisite marked done, all prerequisites completed

---

### Flow 5: Complete Exam (Mark Sections)

**Starting Point**: Participant on exam dashboard, authenticated, exam not locked

**Steps**:

1. Participant sees section cards: "Section 1: Intro", "Section 2: Advanced Topics", etc.
2. Each card shows status: ○ (not started), ⟳ (in progress), ✓ (completed)
3. Participant clicks "Section 1: Intro" card
4. Frontend navigates to `/{slug}/section/1`
5. Page displays:
    - Breadcrumb: "Dashboard > Section 1"
    - Title: "Introduction to JavaScript"
    - Markdown content (paragraphs, lists, code blocks)
    - Progress: "Section 1 of 8"
    - "Mark as Done" button
6. Participant reads section content
7. When done, participant clicks "Mark as Done"
8. Frontend validates:
    - Session valid (not expired)
    - Exam not locked (check deadlines)
9. If validation fails: Show error "Exam is locked" or "Session expired. Login again."
10. If validation passes: POST to `/api/mark-section-done` with `{examId, sectionNumber: 1}`
11. Show loading indicator
12. Backend response: `{success: true, isCompleted: true, totalCompleted: 1}`
13. Frontend:
    - Show success: "✓ Section completed!"
    - Display ✓ badge on section title
    - Disable "Mark as Done" button, show "Undo" button (optional)
    - Update progress: "1 of 8 sections completed (12%)"
    - Log to backend: `{action: "sectionMarkedDone", sectionNumber: 1, examId: 5}`
    - Optionally, auto-navigate to next section with prompt: "Move to next section?" [Yes] [No]
14. Participant clicks "Next Section" button → Navigate to section 2
15. Repeat steps 3-14 for each remaining section
16. After marking final section:
    - Update status to COMPLETED
    - Redirect to `/post-exam` view
    - Show "Congratulations!" message and stats
    - Log to backend: `{action: "examCompleted", totalSections: 8, timeToComplete: "2 days, 5 hours"}`

**Duration**: Varies (depends on exam length)
**User Actions Logged**: section viewed, section marked done, exam completed, navigation between sections

---

### Flow 6: Hard Deadline Reached, Request Extension

**Starting Point**: Participant's hard deadline has passed, exam is locked, participant not yet extended

**Steps**:

1. Participant tries to access `/{slug}/dashboard` or `/section/1`
2. Frontend checks session, examines deadline dates from dashboard state
3. Status shows: "Locked - Extension needed"
4. Show alert: "Your hard deadline has passed (Jan 31, 1:00 PM). Exam is locked."
5. Participant clicks "Request Extension" button
6. Frontend navigates to `/{slug}/extend-deadline`
7. Display extension request form:
    - "How many days?" input (default 3)
    - "Why do you need extension?" textarea
    - "Attach document (optional)" file upload
8. Participant fills form:
    - Requests 5 days
    - Reason: "Had unexpected personal emergency"
    - (Optional) Attaches PDF supporting document
9. Participant clicks "Submit Request"
10. Frontend validates:
    - Days: 1-30 ✓
    - Reason: min 50 characters ✓
    - File: optional, but if present, must be PDF/DOC/DOCX/PNG/JPG and < 5 MB ✓
11. If validation fails: Show specific error, stay on form
12. If validation passes: POST to `/api/request-extension` with form data + file
    - POST uses FormData for file upload
13. Show loading: "Submitting request..."
14. Backend response: `{success: true, requestId: 456}`
15. Frontend:
    - Show success message: "Extension request submitted! Admin will review and notify you within 24 hours."
    - Log to backend: `{action: "extensionRequested", requestedDays: 5, examId: 5, hasAttachment: true}`
    - (Optionally) Redirect to dashboard
    - Show "Previous Requests" section with new request status: "Pending"
16. Participant logs out or navigates back to dashboard
17. Admin (via WordPress admin) reviews extension request
18. Admin approves for 3 days (less than requested)
19. Backend creates session (sends email to participant):
    - "Your extension request has been approved!"
    - "New deadline: Feb 3, 1:00 PM (3 days from now)"
    - "Click here to continue exam: [link]"
20. Participant receives email, clicks link
21. Frontend loads dashboard
22. Status updates: "Extended (3 days remaining)"
23. New deadline displayed: "Extension Deadline: Feb 3, 1:00 PM (in 3 days, 2 hours)"
24. Participant can now mark sections again
25. Log: `{action: "extensionApprovalNotificationReceived", approvedDays: 3}`

**Duration**: Several hours (depends on admin response time)
**User Actions Logged**: extension request submitted, submitted with attachment, request status checked, extension approved notification received

---

## Edge Cases & Error States

### Edge Case 1: Session Expires While Participant Reading

**Scenario**: Participant has been reading section for > 7 days (session expiry)

**Behavior**:

1. Participant tries to click "Mark as Done" button
2. Frontend POSTs to `/api/mark-section-done`
3. Backend validates session → Not found or expired
4. Backend returns 401: `{error: "Session expired"}`
5. Frontend:
    - Clear session cookie
    - Show error modal: "Your session has expired. Please login again."
    - Provide login link: "Go to Login"
    - Log to backend: `{action: "sessionExpired", attemptedAction: "markSectionDone"}`
6. Participant clicks "Go to Login"
7. Frontend redirects to `/{slug}/login`
8. Participant logs in again (new session created)
9. Frontend redirects to dashboard
10. Participant can resume

**User Experience**: Minimal friction. Participant doesn't lose progress (progress was saved when they marked previous sections). Only current section (which wasn't marked) needs to be marked again.

---

### Edge Case 2: Hard Deadline Passes During Exam Session

**Scenario**: Participant is reading section. Hard deadline is in 5 minutes. Participant doesn't notice, keeps reading, hard deadline passes.

**Behavior**:

1. Participant tries to click "Mark as Done" at hard deadline + 2 minutes
2. Frontend POSTs to `/api/mark-section-done`
3. Backend checks deadline: NOW() >= hardDeadlineDate
4. Backend returns error: `{success: false, error: "Hard deadline passed. Exam locked."}`
5. Frontend:
    - Show error: "Your hard deadline has passed. Exam is now locked. Request extension."
    - Disable "Mark as Done" button
    - Show countdown for extension request: "Request extension now"
    - Log to backend: `{action: "hardDeadlineBlocked", sectionNumber: 5}`
6. Participant can click extension request link or navigate to extension page
7. Participant fills extension form
8. (Continue with Extension flow above)

**Mitigation**: Dashboard shows countdown timer. Email reminder sent 24 hours before hard deadline. In-section display shows "Hard deadline in X hours."

---

### Edge Case 3: Network Error During Section Mark

**Scenario**: Participant clicks "Mark as Done", network hiccup, request fails

**Behavior**:

1. Participant clicks "Mark as Done"
2. Frontend shows loading spinner
3. Network error occurs (timeout, connection lost)
4. Frontend catches error, shows: "Unable to save. Please check your internet connection and try again."
5. "Mark as Done" button remains enabled (can retry)
6. Log to backend: `{action: "markSectionFailed", reason: "networkError"}` (if possible; may fail too)
7. Participant checks internet, clicks "Mark as Done" again
8. Request succeeds
9. Frontend shows success

**User Experience**: Button state remains actionable. Participant can retry without confusion.

---

### Edge Case 4: Participant Already Completed, Revisits Exam

**Scenario**: Participant completed all sections, sees post-exam view. Participant navigates back (browser back button) to section view.

**Behavior**:

1. Participant is on post-exam view, clicks browser back button
2. Frontend navigates to `/{slug}/section/8` (last section)
3. Page displays section content with ✓ "Completed" badge
4. "Mark as Done" button is disabled (grayed out)
5. "Undo" button available (optional, admin feature to allow re-opening section)
6. Participant can click "Back to Dashboard"
7. Dashboard shows status "Completed"

**User Experience**: Participant can browse completed sections read-only. Cannot accidentally unmark completed sections.

---

### Edge Case 5: Secret Key Auto-Signup

**Scenario**: Exam has secretKey set by admin. Participant visits `/{slug}?key={secretKey}`

**Behavior**:

1. Frontend loads landing page
2. Detects URL param: `key=abc123secret`
3. Check if key is valid: POST to `/api/validate-secret-key` with `{examId, key}`
4. Backend response:
    - Valid: `{success: true, auto: true}`
    - Invalid: `{success: false}`
5. If valid:
    - Frontend auto-submits signup with:
        - Email: generated (e.g., `secret-user-{timestamp}@exam.local`)
        - Password: auto-generated
        - WhatsApp, LinkedIn: empty
    - Backend creates participant, session, returns redirect
    - Frontend automatically redirects to dashboard
    - No signup form shown
    - Log: `{action: "autoSignupViaSecretKey", examId: 5}`
6. If invalid:
    - Show error: "Invalid signup link. Please request a new link from admin."
    - Log: `{action: "invalidSecretKey", key: "...truncated..."}`

**User Experience**: Frictionless one-click signup via secret key. Useful for closed-group exams.

---

### Edge Case 6: Authenticated User Already Participating

**Scenario**: User is logged in and visits an exam they're already enrolled in

**Behavior**:

1. Frontend loads landing page
2. Session cookie exists and is valid
3. API check: User IS already a participant in this exam
4. Show:
    - "Welcome back, [Email]"
    - "Continue Exam" button (NOT "Participate")
    - Progress summary: "3 of 8 sections completed"
5. User clicks "Continue Exam"
6. Redirect to dashboard

**User Experience**: Seamless return to in-progress exam.

---

## Frontend Logging (API-Based)

### Logging Architecture

- **Endpoint**: `POST /api/log-event`
- **Request Body**:

```json
{
  "examId": 5,
  "participantId": 12,
  "sessionId": "abc123...",
  "action": "sectionMarkedDone",
  "details": {
    "sectionNumber": 3,
    "duration": 45,
    "userAgent": "Mozilla/5.0..."
  },
  "timestamp": "2026-01-25T13:24:00Z"
}
```

- **Backend**: Parses request, logs to `/wp-content/uploads/exam-questions-manager/logs/plugin.log`
    - Format: `[2026-01-25 13:24:00] USER_ACTION participantId=12 examId=5 sessionId=abc123 action="sectionMarkedDone" details={json}`

### Events Logged

| Action | Trigger | Details |
|:--|:--|:--|
| `pageView` | User navigates to page | `page: "landing" / "login" / "dashboard" / "section"` |
| `signupAttempted` | User submits signup form | `email: {email}` |
| `signupFailed` | Signup validation/API fails | `reason: "validationError" / "emailExists" / "networkError"` |
| `signupSuccess` | Signup succeeds | `email: {email}` |
| `loginAttempted` | User submits login form | `email: {email}` |
| `loginFailed` | Login validation/auth fails | `reason: "invalidCredentials" / "networkError"` |
| `loginSuccess` | Login succeeds | `email: {email}, isRememberMe: true/false` |
| `logoutInitiated` | User clicks logout | `reason: "userInitiated" / "sessionExpired"` |
| `logoutSuccess` | Logout completes | (no additional details) |
| `participateLandingViewed` | Authenticated user views new exam | `examId: {id}` |
| `participateDialogOpened` | User clicks Participate button | `examId: {id}` |
| `participateConfirmed` | User confirms participation | `examId: {id}, linkedInUrl: {url}` |
| `participateFailed` | Participation fails | `reason: "alreadyParticipating" / "validationError"` |
| `prerequisiteViewed` | User views prerequisite | `type: "video" / "link" / "checklist", title: "{title}"` |
| `prerequisiteCompleted` | User marks prerequisite done | `type: "video" / "link" / "checklist", prerequisiteId: {id}` |
| `allPrerequisitesCompleted` | All required prerequisites done | (no additional details) |
| `sectionViewed` | User opens section | `sectionNumber: 3, sectionTitle: "{title}"` |
| `sectionMarkedDone` | User marks section completed | `sectionNumber: 3, duration: {seconds}` |
| `sectionUndone` | User undoes section mark | `sectionNumber: 3` |
| `examCompleted` | All sections marked done | `totalSections: 8, timeToComplete: {seconds}` |
| `extensionRequested` | User submits extension request | `requestedDays: 5, hasAttachment: true/false` |
| `extensionApprovalReceived` | User receives extension approval email | `approvedDays: 3, newDeadline: "{date}"` |
| `extensionRejectionReceived` | User receives extension rejection email | `reason: "{admin message}"` |
| `sessionExpired` | Session expires during interaction | `attemptedAction: "markSectionDone" / "requestExtension"` |
| `hardDeadlineApproaching` | Deadline within 24 hours | `hoursRemaining: {hours}` |
| `hardDeadlinePassed` | Hard deadline reached, exam locked | `timeOverdue: {minutes}` |
| `validationError` | Form validation fails | `field: "email" / "password", reason: "{error}"` |
| `networkError` | API request fails (network issue) | `endpoint: "/api/mark-section-done", statusCode: 0` |
| `apiError` | API request fails (server error) | `endpoint: "/api/mark-section-done", statusCode: 500, error: "{message}"` |

### Logging Implementation (Frontend Code Pseudocode)

```javascript
async function logEvent(action, details = {}) {
  try {
    const payload = {
      examId: getCurrentExamId(),
      participantId: getCurrentParticipantId() || null,
      sessionId: getSessionCookieValue(),
      action: action,
      details: details,
      timestamp: new Date().toISOString()
    };
    
    // Fire-and-forget: don't wait for response, don't block UI
    fetch('/api/log-event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).catch(err => {
      // Silently fail. Don't disrupt user experience.
      console.warn('Logging failed:', err);
    });
  } catch (err) {
    // Fallback: don't throw error
    console.warn('Log event error:', err);
  }
}

// Usage examples:
logEvent('pageView', { page: 'dashboard' });
logEvent('sectionMarkedDone', { sectionNumber: 3, duration: 45 });
logEvent('loginFailed', { reason: 'invalidCredentials' });
logEvent('participateConfirmed', { examId: 12, linkedInUrl: 'https://linkedin.com/in/john' });
```

---

## UI/UX Principles

### Design Consistency

- **Color Palette**:
    - Primary: Teal (e.g., #0284c7 for light theme)
    - Success: Green (e.g., #22c55e)
    - Warning: Amber/Orange (e.g., #f59e0b)
    - Error: Red (e.g., #ef4444)
    - Neutral: Gray (e.g., #6b7280)
- **Typography**: Sans-serif (Arial, Helvetica, system fonts)
- **Spacing**: Consistent padding/margins (8px, 16px, 24px increments)
- **Buttons**: Primary (filled), Secondary (outline), Tertiary (text-only)

### Accessibility

- **Color Contrast**: All text meets WCAG AA standard (4.5:1 for normal text)
- **Focus States**: All interactive elements have visible focus indicator
- **ARIA Labels**: All form inputs, buttons, and landmarks properly labeled
- **Keyboard Navigation**: Tab through all form fields and buttons; Enter to submit
- **Mobile-Friendly**: Responsive layout, touch-friendly buttons (min 48px), readable text (16px+)

### Responsive Design

- **Mobile** (< 640px):
    - Single column layout
    - Full-width cards
    - Stacked form inputs
    - Hamburger menu for navigation
- **Tablet** (640px - 1024px):
    - Two-column layout where applicable
    - Wider cards, but still stacked on smaller tablets
    - Accessible navigation
- **Desktop** (> 1024px):
    - Multi-column layouts
    - Sidebar for additional info (timeline, checklist)
    - Optimized whitespace

### Loading States

- **Button Loading**: Change button text to "Loading..." and disable, show spinner
- **Page Loading**: Show skeleton loaders or loading bars for content sections
- **Form Submission**: Disable form inputs while request in progress, show feedback

### Error Handling

- **Toast Notifications**: Small popups for quick feedback (success, error, warning)
- **Inline Validation**: Show errors next to form fields as user types
- **Error Pages**: Clear error messages with actionable next steps
- **Retry Logic**: Buttons remain enabled for retry on failure

### Progress Indication

- **Progress Bars**: Show completion percentage (e.g., "5 of 8 sections")
- **Countdowns**: Show days/hours remaining for deadlines (e.g., "2 days, 3 hours")
- **Visual Badges**: Checkmarks for completed items, icons for status
- **Timeline**: Optional sidebar showing position in exam

---

## Frontend Dependencies & Tech Stack (Recommendations)

- **HTML5**: Semantic markup
- **CSS3**: Flexbox/Grid for layout, media queries for responsive design
- **JavaScript (Vanilla or minimal framework)**:
    - **Markdown Parser**: `marked.js` or `markdown-it` (client-side rendering)
    - **Syntax Highlighting**: `highlight.js` (for code blocks)
    - **Form Validation**: HTML5 validation + custom JS
    - **HTTP Client**: `fetch` API (native, no jQuery needed)
    - **State Management**: Minimal (localStorage for UI state, not sensitive data)
- **Optional Framework**: Vue.js or Svelte for SPA behavior (if you want reactivity)
- **Video Embeds**: YouTube iframe API (native)
- **File Upload**: HTML5 File API, no plugins

---

## Acceptance Criteria Checklist

### Authentication & Participation
- [ ] Signup form validates email, password, confirms password match
- [ ] Login form authenticates and sets session cookie
- [ ] Logout clears session and redirects to landing
- [ ] **Authenticated user visiting new exam sees "Participate" button**
- [ ] **Participate confirmation dialog pre-fills email (read-only)**
- [ ] **Participate confirmation requires checkbox confirmation**
- [ ] **Successful participation creates new participant record with deadlines**
- [ ] Session cookie persists across page reloads
- [ ] "Remember me" extends cookie to 30 days

### Prerequisites
- [ ] Prerequisites display in order (videos, links, checklists)
- [ ] YouTube videos embed correctly
- [ ] Prerequisite completion persists to backend
- [ ] Progress indicator updates in real-time
- [ ] Required prerequisites block exam start

### Exam Content
- [ ] Markdown renders correctly (headings, lists, code, links)
- [ ] Section cards show correct status (not started, in progress, completed)
- [ ] "Mark as Done" updates progress
- [ ] Navigation between sections works
- [ ] Breadcrumbs navigate correctly

### Deadlines & Extensions
- [ ] Countdown displays for soft and hard deadlines
- [ ] Hard deadline locks exam
- [ ] Extension request form submits correctly
- [ ] Extension approval unlocks exam

### Logging
- [ ] All user actions log to backend
- [ ] Logging doesn't block UI
- [ ] Error logging captures failures

### Responsive Design
- [ ] Mobile layout works correctly
- [ ] Tablet layout adapts properly
- [ ] Desktop layout uses full width
