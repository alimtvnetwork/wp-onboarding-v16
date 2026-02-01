# 31. Email Templates

## Overview
Define all email templates used throughout the plugin lifecycle with variable substitution support.

---

## Email Template Definitions

### 31.1 Welcome Email
**Trigger:** When a participant is first added to an exam

**Required Variables:**
- `{{participantName}}` - Full name of participant
- `{{examTitle}}` - Name of the exam
- `{{adminEmail}}` - Contact email for support

**Acceptance Criteria:**
- [ ] Template file created in `templates/emails/welcome.php`
- [ ] All variables are replaced correctly
- [ ] Email sends successfully via `wp_mail`
- [ ] HTML formatting renders properly

---

### 31.2 Soft Deadline Reminder
**Trigger:** Configurable days before soft deadline (default: 7, 3, 1 days)

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{softDeadlineDate}}` - Formatted date
- `{{daysRemaining}}` - Integer days left
- `{{progressPercent}}` - Current progress percentage

**Acceptance Criteria:**
- [ ] Reminder intervals are configurable in settings
- [ ] Multiple reminders can be scheduled
- [ ] Progress percentage is calculated correctly
- [ ] Does not send if exam already completed

---

### 31.3 Soft Deadline Passed
**Trigger:** When soft deadline passes and exam is incomplete

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{softDeadlineDate}}`
- `{{hardDeadlineDate}}`
- `{{progressPercent}}`

**Acceptance Criteria:**
- [ ] Sends only once per participant per deadline
- [ ] Includes clear messaging about hard deadline
- [ ] Links to extension request if enabled

---

### 31.4 Hard Deadline Warning
**Trigger:** Configurable days before hard deadline (default: 3, 1 days)

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{hardDeadlineDate}}`
- `{{daysRemaining}}`
- `{{progressPercent}}`

**Acceptance Criteria:**
- [ ] Urgency level increases as deadline approaches
- [ ] Different messaging for 3-day vs 1-day warning
- [ ] Does not send if already completed

---

### 31.5 Hard Deadline Passed (Locked)
**Trigger:** When hard deadline passes and status changes to LOCKED

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{hardDeadlineDate}}`
- `{{finalProgress}}` - Progress at lock time
- `{{adminEmail}}`

**Acceptance Criteria:**
- [ ] Sends immediately upon status change to LOCKED
- [ ] Includes final progress percentage
- [ ] Provides contact information for appeals

---

### 31.6 Extension Requested
**Trigger:** When participant submits extension request

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{requestedDays}}`
- `{{reason}}` - Participant's stated reason
- `{{adminDashboardLink}}`

**Acceptance Criteria:**
- [ ] Sends to all admins with extension approval permission
- [ ] Includes direct link to approve/deny
- [ ] Logs request in extension_request table

---

### 31.7 Extension Approved
**Trigger:** When admin approves extension request

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{newDeadlineDate}}`
- `{{extensionDays}}`
- `{{approverName}}`

**Acceptance Criteria:**
- [ ] Sends to participant only
- [ ] Clearly states new deadline date
- [ ] Updates participant status to EXTENDED

---

### 31.8 Extension Denied
**Trigger:** When admin denies extension request

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{denialReason}}` - Admin's stated reason
- `{{adminEmail}}`

**Acceptance Criteria:**
- [ ] Sends to participant only
- [ ] Includes reason for denial
- [ ] Provides contact for further discussion

---

### 31.9 Exam Completed
**Trigger:** When participant completes all requirements

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{completionDate}}`
- `{{totalScore}}` - If applicable
- `{{certificateLink}}` - If certificates enabled

**Acceptance Criteria:**
- [ ] Sends immediately upon completion
- [ ] Includes completion certificate if configured
- [ ] Updates participant status to COMPLETED

---

### 31.10 Secret Key Generated
**Trigger:** When new secret key is created for an exam

**Required Variables:**
- `{{examTitle}}`
- `{{secretKey}}`
- `{{expiresAt}}` - If expiration set
- `{{maxUses}}` - If usage limit set
- `{{accessLink}}` - Direct exam access URL

**Acceptance Criteria:**
- [ ] Sends to exam creator/admin only
- [ ] Key is displayed prominently
- [ ] Includes copy-paste friendly format

---

### 31.11 Admin Notification (Configurable)
**Trigger:** Various admin-configurable events

**Configurable Triggers:**
- New participant registered
- Exam completed
- Extension requested
- Hard deadline passed

**Acceptance Criteria:**
- [ ] Admin can enable/disable each trigger
- [ ] Can configure recipient list per trigger
- [ ] Summary digest option (daily/weekly)

---

### 31.12 Exam Invite Created
**Trigger:** When admin creates invite for invite-only exam

**Required Variables:**
- `{{name}}` - Invitee name (with "there" fallback)
- `{{email}}` - Invitee email address
- `{{phone}}` - Masked phone number
- `{{examTitle}}` - Exam name
- `{{signupUrl}}` - Direct signup link with invite token
- `{{expiresAt}}` - Expiration date (if set)
- `{{siteName}}` - Site name

**Acceptance Criteria:**
- [ ] Template file created in `templates/emails/invite-created.php`
- [ ] Signup URL includes invite token
- [ ] Conditional expiration display
- [ ] Clear instructions about email/phone matching

---

### 31.13 Exam Invite Reminder
**Trigger:** Manual resend or automatic reminder

**Required Variables:**
- Same as 31.12

**Acceptance Criteria:**
- [ ] Different subject line than initial invite
- [ ] Urgency messaging if approaching expiration
- [ ] Tracks that reminder was sent

---

### 31.14 Exam Invite Expiring
**Trigger:** Automatic - 3 days before invite expiration

**Required Variables:**
- Same as 31.12

**Acceptance Criteria:**
- [ ] Sent via daily cron job
- [ ] Only sent once (tracked in database)
- [ ] Clear deadline urgency

---

### 31.15 Submission Approved
**Trigger:** When admin approves a flagged submission

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{checklistItemTitle}}` - Name of the checklist item
- `{{adminNote}}` - Optional approval note
- `{{dashboardUrl}}` - Link to participant dashboard

**Acceptance Criteria:**
- [ ] Template file created in `templates/emails/submission-approved.php`
- [ ] Celebratory tone
- [ ] Admin note displayed if provided

---

### 31.16 Submission Rejected
**Trigger:** When admin rejects a flagged submission

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{checklistItemTitle}}`
- `{{rejectionReason}}` - Required reason from admin
- `{{supportEmail}}`

**Acceptance Criteria:**
- [ ] Clear explanation of rejection
- [ ] Contact information for questions
- [ ] Professional, non-punitive tone

---

### 31.17 Submission Resubmit Requested
**Trigger:** When admin requests resubmission

**Required Variables:**
- `{{participantName}}`
- `{{examTitle}}`
- `{{checklistItemTitle}}`
- `{{adminFeedback}}` - Required feedback from admin
- `{{resubmitUrl}}` - Direct link to resubmit

**Acceptance Criteria:**
- [ ] Clear actionable feedback
- [ ] Direct link to the specific item
- [ ] Constructive, helpful tone

---

## Variable Substitution Engine

### Requirements
- Parse templates for `{{variableName}}` patterns
- Replace with corresponding data values
- Handle missing variables gracefully (empty string or placeholder)
- Support nested variables for complex data

### Acceptance Criteria:
- [ ] All double-brace variables are replaced
- [ ] Missing variables don't cause errors
- [ ] Special characters in values are escaped for HTML
- [ ] Plain text alternative is generated

---

## Email Queue System

### Requirements
- Queue emails for batch sending (avoid timeout issues)
- Retry failed sends up to 3 times
- Log all send attempts with status

### Acceptance Criteria:
- [ ] Emails are queued in database table
- [ ] Cron job processes queue every 5 minutes
- [ ] Failed emails are retried with exponential backoff
- [ ] Success/failure logged for debugging
