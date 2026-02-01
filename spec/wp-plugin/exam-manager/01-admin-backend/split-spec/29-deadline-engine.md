# 27. Deadline Engine

## Overview
Two-tier deadline system (soft and hard) with automated status transitions and notifications.

---

## 27.1 Deadline Types

### Three Deadline Categories

| Type | Database Field | Purpose | Blocks Access? |
|------|----------------|---------|----------------|
| **Soft Deadline** | `softDeadlineDate` | Warning threshold, encouragement only | ❌ No |
| **Hard Deadline** | `hardDeadlineDate` | Absolute cutoff for exam access | ✅ Yes |
| **Extension Deadline** | `extensionDeadlineDate` | Admin-granted extra time | ✅ Yes (when expires) |

### Soft Deadline
- **Purpose**: Warning threshold, no access restrictions
- **Behavior**: Triggers reminder notifications, status changes to `SOFT_DEADLINE_REACHED`
- **UI Indicator**: Yellow/orange warning styling
- **Can Mark Sections**: ✅ YES

### Hard Deadline
- **Purpose**: Absolute cutoff for exam access
- **Behavior**: Automatically locks participant, status changes to `LOCKED`
- **UI Indicator**: Red/urgent styling
- **Can Mark Sections**: ❌ NO

### Extension Deadline
- **Purpose**: Admin-granted additional time after hard deadline
- **Behavior**: Temporarily unlocks participant until extension expires
- **UI Indicator**: Blue/extended styling
- **Can Mark Sections**: ✅ YES (until expiry)

### Acceptance Criteria:
- [ ] All deadlines stored as UTC timestamps (DATETIME)
- [ ] Soft deadline must be before hard deadline (validated on save)
- [ ] Extension deadline calculated from hard deadline, not soft
- [ ] Timezone displayed based on user preference in UI

---

## 27.2 Database Fields (per participant)

### Participant Table Deadline Columns

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `softDeadlineDate` | DATETIME | Yes | Calculated at signup: `signupDate + exam.softDeadlineDays` |
| `hardDeadlineDate` | DATETIME | Yes | Calculated at signup: `signupDate + exam.hardDeadlineDays` |
| `extensionDeadlineDate` | DATETIME | Yes | Only set when admin approves extension |
| `originalSoftDeadline` | DATETIME | Yes | Preserved original before any override |
| `originalHardDeadline` | DATETIME | Yes | Preserved original before any override |
| `deadlineOverride` | DATETIME | Yes | Admin manual override (overrides all calculations) |
| `deadlineOverrideReason` | VARCHAR(500) | Yes | Required reason for audit trail |

### Extension Request Table Fields

| Field | Type | Description |
|-------|------|-------------|
| `requestedDays` | INTEGER | Days requested by participant |
| `approvedDays` | INTEGER | Days approved by admin (can be less than requested) |
| `approvalDate` | DATETIME | When admin approved the request |

---

## 27.3 Deadline Calculation Algorithm

### Initial Calculation (at signup)

```pseudocode
function calculateInitialDeadlines(participant, exam):
    signupDate = participant.createdAt
    
    // Soft deadline calculation
    IF exam.softDeadlineDays IS NOT NULL:
        participant.softDeadlineDate = signupDate + (exam.softDeadlineDays * 24 * 60 * 60)
        participant.originalSoftDeadline = participant.softDeadlineDate
    ELSE IF exam.inheritDeadline = true AND exam.parentId IS NOT NULL:
        parentExam = getExam(exam.parentId)
        participant.softDeadlineDate = signupDate + (parentExam.softDeadlineDays * 24 * 60 * 60)
    ELSE:
        participant.softDeadlineDate = NULL  // No soft deadline
    
    // Hard deadline calculation
    IF exam.hardDeadlineDays IS NOT NULL:
        participant.hardDeadlineDate = signupDate + (exam.hardDeadlineDays * 24 * 60 * 60)
        participant.originalHardDeadline = participant.hardDeadlineDate
    ELSE IF exam.inheritDeadline = true AND exam.parentId IS NOT NULL:
        parentExam = getExam(exam.parentId)
        participant.hardDeadlineDate = signupDate + (parentExam.hardDeadlineDays * 24 * 60 * 60)
    ELSE:
        participant.hardDeadlineDate = NULL  // No hard deadline
    
    // Extension deadline always starts as NULL
    participant.extensionDeadlineDate = NULL
    
    // Validate: soft must be before hard
    IF participant.softDeadlineDate IS NOT NULL 
       AND participant.hardDeadlineDate IS NOT NULL
       AND participant.softDeadlineDate >= participant.hardDeadlineDate:
        THROW ValidationError("Soft deadline must be before hard deadline")
    
    RETURN participant
```

### Extension Calculation (when approved)

---

## ⚠️ CRITICAL IMPLEMENTATION WARNING - HIGH RISK AREA #1

> **AI IMPLEMENTATION ALERT**: This is the #1 cause of deadline bugs.
> 
> **THE WRONG WAY** (causes deadline drift):
> ```php
> // ❌ WRONG: Extending from current hard deadline
> $extensionDeadline = $participant->hardDeadlineDate + ($approvedDays * 86400);
> ```
> 
> **THE CORRECT WAY**:
> ```php
> // ✅ CORRECT: First extension uses ORIGINAL hard deadline
> $baseDeadline = $participant->originalHardDeadline ?? $participant->hardDeadlineDate;
> $extensionDeadline = $baseDeadline + ($approvedDays * 86400);
> ```
> 
> **WHY THIS MATTERS**: If you extend from the current hard deadline, each extension compounds.
> A 7-day extension followed by a 3-day extension should give 10 extra days total, not 10 + 3 = 13.

---

### Pre-Implementation Checklist (MANDATORY)

Before implementing `applyExtension()`, verify these are true:

- [ ] ✅ You are reading `originalHardDeadline` as the base for FIRST extension
- [ ] ✅ You are reading `extensionDeadlineDate` as the base for ADDITIONAL extensions
- [ ] ✅ You preserved `originalHardDeadline` BEFORE any deadline modification
- [ ] ❌ You are NOT reading `hardDeadlineDate` for extension calculation
- [ ] ❌ You are NOT reading `softDeadlineDate` for extension calculation

---

```pseudocode
function applyExtension(participant, approvedDays):
    // ============================================================
    // CRITICAL: Extension is calculated from HARD deadline, not soft
    // CRITICAL: First extension uses ORIGINAL hard deadline
    // CRITICAL: Subsequent extensions use CURRENT extension deadline
    // ============================================================
    
    // Step 1: Determine the base deadline
    // FIRST EXTENSION: Use original hard deadline (preserved at signup)
    baseDeadline = participant.originalHardDeadline ?? participant.hardDeadlineDate
    
    IF baseDeadline IS NULL:
        THROW ValidationError("Cannot extend exam with no hard deadline")
    
    // Step 2: Calculate extension deadline
    // approvedDays is INTEGER representing calendar days
    extensionSeconds = approvedDays * 24 * 60 * 60
    participant.extensionDeadlineDate = baseDeadline + extensionSeconds
    
    // Step 3: Update status to EXTENDED
    participant.status = ParticipantStatus.EXTENDED
    
    // Step 4: Audit log (REQUIRED)
    auditLog.record(
        action: AuditAction.EXTENSION_APPROVED,
        participantId: participant.id,
        data: {
            // Log the base used for audit trail
            baseDeadlineUsed: baseDeadline,
            wasOriginalHardDeadline: (participant.originalHardDeadline IS NOT NULL),
            approvedDays: approvedDays,
            newExtensionDeadline: participant.extensionDeadlineDate
        }
    )
    
    RETURN participant
```

### Multiple Extensions (Additional Extensions)

```pseudocode
function applyAdditionalExtension(participant, additionalDays):
    // ============================================================
    // For SUBSEQUENT extensions: extend from CURRENT extension deadline
    // This is intentionally different from first extension!
    // ============================================================
    
    IF participant.extensionDeadlineDate IS NULL:
        THROW ValidationError("No existing extension to extend from. Use applyExtension() for first extension.")
    
    // ADDITIONAL extensions: Use current extension deadline as base
    baseDeadline = participant.extensionDeadlineDate
    extensionSeconds = additionalDays * 24 * 60 * 60
    participant.extensionDeadlineDate = baseDeadline + extensionSeconds
    
    // Re-unlock if currently locked
    IF participant.status = ParticipantStatus.LOCKED:
        participant.status = ParticipantStatus.EXTENDED
    
    // Audit log
    auditLog.record(
        action: AuditAction.ADDITIONAL_EXTENSION_APPROVED,
        participantId: participant.id,
        data: {
            previousExtensionDeadline: baseDeadline,
            additionalDays: additionalDays,
            newExtensionDeadline: participant.extensionDeadlineDate
        }
    )
    
    RETURN participant
```

### Decision Tree: Which Function to Call?

```
Is participant.extensionDeadlineDate NULL?
├── YES → Call applyExtension() 
│         (uses originalHardDeadline as base)
│
└── NO  → Call applyAdditionalExtension()
          (uses current extensionDeadlineDate as base)
```

### Effective Deadline Calculation (for display)

```pseudocode
function getEffectiveDeadline(participant):
    // Priority order: Override > Extension > Hard > Inherited
    
    IF participant.deadlineOverride IS NOT NULL:
        RETURN {
            type: 'OVERRIDE',
            deadline: participant.deadlineOverride,
            source: 'Admin Override',
            reason: participant.deadlineOverrideReason
        }
    
    IF participant.extensionDeadlineDate IS NOT NULL 
       AND participant.extensionDeadlineDate > now():
        RETURN {
            type: 'EXTENSION',
            deadline: participant.extensionDeadlineDate,
            source: 'Extension Granted',
            originalHard: participant.originalHardDeadline
        }
    
    IF participant.hardDeadlineDate IS NOT NULL:
        RETURN {
            type: 'HARD',
            deadline: participant.hardDeadlineDate,
            source: 'Exam Default'
        }
    
    // No deadline
    RETURN {
        type: 'NONE',
        deadline: NULL,
        source: 'No Deadline Set'
    }
```

---

## 27.4 Example Scenarios

### Scenario 1: Standard Flow (No Extension)

```
Exam Config:
  softDeadlineDays = 3
  hardDeadlineDays = 7

Participant signs up: Jan 24, 2026 @ 1:00 PM

Calculated Deadlines:
  softDeadlineDate  = Jan 27, 2026 @ 1:00 PM (signup + 3 days)
  hardDeadlineDate  = Jan 31, 2026 @ 1:00 PM (signup + 7 days)
  extensionDeadlineDate = NULL

Timeline:
  Jan 24-26: Status = ACTIVE, can mark sections ✅
  Jan 27 1PM: Status → SOFT_DEADLINE_REACHED, can mark sections ✅
  Jan 31 1PM: Status → LOCKED, cannot mark sections ❌
```

### Scenario 2: Extension Granted

```
Continuing from Scenario 1...

Jan 31 @ 3:00 PM: Participant requests 3-day extension
Feb 1 @ 10:00 AM: Admin approves 2 days (less than requested)

Extension Calculation:
  baseDeadline = originalHardDeadline = Jan 31 @ 1:00 PM
  approvedDays = 2
  extensionDeadlineDate = Jan 31 + 2 days = Feb 2 @ 1:00 PM

Updated State:
  status = EXTENDED
  extensionDeadlineDate = Feb 2 @ 1:00 PM

Timeline:
  Feb 1-2: Status = EXTENDED, can mark sections ✅
  Feb 2 1PM: Status → LOCKED (re-locked), cannot mark sections ❌
```

### Scenario 3: Admin Override

```
Admin manually overrides deadline:
  deadlineOverride = Feb 15, 2026 @ 5:00 PM
  deadlineOverrideReason = "Special accommodation for medical leave"

Effective deadline ignores all other calculations:
  getEffectiveDeadline() returns Feb 15 @ 5:00 PM

Original deadlines preserved in:
  originalSoftDeadline = Jan 27 @ 1:00 PM
  originalHardDeadline = Jan 31 @ 1:00 PM
```

### Scenario 4: Multiple Extensions

```
First Extension:
  baseDeadline = Jan 31 @ 1:00 PM (original hard)
  approvedDays = 3
  extensionDeadlineDate = Feb 3 @ 1:00 PM

Second Extension (additional):
  baseDeadline = Feb 3 @ 1:00 PM (current extension)
  approvedDays = 2
  extensionDeadlineDate = Feb 5 @ 1:00 PM

Total extension: 5 days from original hard deadline
```

### Scenario 5: No Deadline Exam

```
Exam Config:
  softDeadlineDays = NULL
  hardDeadlineDays = NULL

Calculated Deadlines:
  softDeadlineDate = NULL
  hardDeadlineDate = NULL
  
Status: Participant never locked automatically
Extension: Cannot be applied (no hard deadline to extend from)
```

---

## 27.5 Deadline Sources (Priority Order)

### Priority Order (highest first)
1. **Participant Override**: `deadlineOverride` - Admin-set absolute deadline
2. **Extension Granted**: `extensionDeadlineDate` - Calculated from hard deadline
3. **Exam Default**: `hardDeadlineDate` - Calculated at signup from exam config
4. **Parent Exam**: Inherited if child uses `inheritDeadline = true` mode

### Acceptance Criteria:
- [ ] Effective deadline calculated dynamically via `getEffectiveDeadline()`
- [ ] Override clearly indicated in UI with reason displayed
- [ ] Extension history preserved in `extension_requests` table
- [ ] Inheritance mode toggleable per child exam via `inheritDeadline` boolean

---

## 27.6 Cron Job: Deadline Checker

### Schedule
Runs every hour via WP-Cron (configurable in `config/defaults.json`).

### Process Algorithm

```pseudocode
function runDeadlineChecker():
    now = currentTimestamp()
    batchSize = Settings.get('deadline_checker_batch_size', 100)
    processedCount = 0
    errorCount = 0
    
    // Phase 1: Soft deadline approaching (24h before)
    softApproaching = db.query(
        "SELECT * FROM participants 
         WHERE status = 'ACTIVE'
         AND softDeadlineDate BETWEEN ? AND ?
         AND softDeadlineNotifiedAt IS NULL
         LIMIT ?",
        [now, now + 24h, batchSize]
    )
    
    FOR EACH participant IN softApproaching:
        TRY:
            sendEmail(participant, 'SOFT_DEADLINE_APPROACHING')
            participant.softDeadlineNotifiedAt = now
            db.save(participant)
            processedCount++
        CATCH error:
            logError(error, { participantId: participant.id })
            errorCount++
            // Continue processing - don't stop batch
    
    // Phase 2: Soft deadline passed
    softPassed = db.query(
        "SELECT * FROM participants 
         WHERE status = 'ACTIVE'
         AND softDeadlineDate < ?
         LIMIT ?",
        [now, batchSize]
    )
    
    FOR EACH participant IN softPassed:
        TRY:
            participant.status = ParticipantStatus.SOFT_DEADLINE_REACHED
            sendEmail(participant, 'SOFT_DEADLINE_PASSED')
            db.save(participant)
            auditLog.record(AuditAction.STATUS_CHANGED, participant.id)
            processedCount++
        CATCH error:
            logError(error, { participantId: participant.id })
            errorCount++
    
    // Phase 3: Hard deadline passed (LOCK participants)
    hardPassed = db.query(
        "SELECT * FROM participants 
         WHERE status IN ('ACTIVE', 'SOFT_DEADLINE_REACHED')
         AND hardDeadlineDate < ?
         LIMIT ?",
        [now, batchSize]
    )
    
    FOR EACH participant IN hardPassed:
        TRY:
            participant.status = ParticipantStatus.LOCKED
            sendEmail(participant, 'EXAM_LOCKED')
            db.save(participant)
            auditLog.record(AuditAction.STATUS_CHANGED, participant.id)
            processedCount++
        CATCH error:
            logError(error, { participantId: participant.id })
            errorCount++
    
    // Phase 4: Extension deadline passed (RE-LOCK participants)
    extensionExpired = db.query(
        "SELECT * FROM participants 
         WHERE status = 'EXTENDED'
         AND extensionDeadlineDate < ?
         LIMIT ?",
        [now, batchSize]
    )
    
    FOR EACH participant IN extensionExpired:
        TRY:
            participant.status = ParticipantStatus.LOCKED
            sendEmail(participant, 'EXTENSION_EXPIRED')
            db.save(participant)
            auditLog.record(AuditAction.EXTENSION_EXPIRED, participant.id)
            processedCount++
        CATCH error:
            logError(error, { participantId: participant.id })
            errorCount++
    
    // Log summary
    log.info("Deadline checker completed", {
        processed: processedCount,
        errors: errorCount,
        duration: now - startTime
    })
```

### Acceptance Criteria:
- [ ] Idempotent (safe to run multiple times)
- [ ] Batch processing (configurable, default: 100 participants per run)
- [ ] Error handling doesn't stop batch
- [ ] Execution logged with summary stats
- [ ] Each phase runs independently (Phase 3 failure doesn't block Phase 4)

---

## 27.7 Deadline Notifications

### Notification Schedule
| Trigger | Email Template | Timing |
|---------|---------------|--------|
| Soft approaching | `SOFT_DEADLINE_APPROACHING` | 24 hours before |
| Soft passed | `SOFT_DEADLINE_PASSED` | Immediately |
| Hard approaching | `HARD_DEADLINE_APPROACHING` | 24 hours before |
| Hard passed | `EXAM_LOCKED` | Immediately |
| Extension expiring | `EXTENSION_EXPIRING` | 24 hours before |
| Extension expired | `EXTENSION_EXPIRED` | Immediately |

### Configurable Reminder Schedule (from config/defaults.json)

```json
{
    "deadline_reminders": {
        "soft_deadline_days_before": [7, 3, 1],
        "hard_deadline_days_before": [3, 1],
        "extension_hours_before": [24, 6]
    }
}
```

### Acceptance Criteria:
- [ ] Notification timing configurable per exam (overrides defaults)
- [ ] Duplicate prevention via `*NotifiedAt` timestamp columns
- [ ] Unsubscribe option in emails respects `participant.emailOptOut`
- [ ] In-app notification created alongside email

---

## 27.8 Countdown Display

### UI Components

#### Days Remaining Badge (Unified Color Scheme)

**Soft Deadline Colors:**
| Time Remaining | Color | CSS Class | Hex Value |
|----------------|-------|-----------|-----------|
| > 7 days | Green | `deadline-safe` | `#22c55e` |
| 3-7 days | Yellow | `deadline-warning` | `#eab308` |
| 1-3 days | Orange | `deadline-urgent` | `#f97316` |
| < 24 hours | Light Red | `deadline-critical` | `#ef4444` |

**Hard Deadline Colors:**
| Time Remaining | Color | CSS Class | Hex Value |
|----------------|-------|-----------|-----------|
| > 7 days | Green | `deadline-safe` | `#22c55e` |
| 3-7 days | Yellow | `deadline-warning` | `#eab308` |
| 1-3 days | Orange | `deadline-urgent` | `#f97316` |
| < 24 hours | Dark Red | `deadline-critical-hard` | `#dc2626` |
| Overdue | Black | `deadline-overdue` | `#1f2937` |

#### Countdown Timer Format

```pseudocode
function formatCountdown(deadline):
    remaining = deadline - now()
    
    IF remaining <= 0:
        RETURN "Overdue"
    
    IF remaining < 1 hour:
        RETURN "{minutes}m {seconds}s remaining"
    
    IF remaining < 24 hours:
        RETURN "{hours}h {minutes}m remaining"
    
    RETURN "{days}d {hours}h remaining"
```

#### Display Format
- Always show BOTH countdown AND absolute date/time
- Example: "2 days, 3 hours" + "Jan 27, 1:00 PM"
- Live updates every 60 seconds via frontend JavaScript
- Switches to every 10 seconds when < 1 hour remaining

### Acceptance Criteria:
- [ ] Countdown uses participant's timezone preference
- [ ] Visual distinction between soft and hard deadlines (light vs dark red)
- [ ] Accessible color contrast maintained (WCAG AA: 4.5:1 ratio)
- [ ] Screen reader announces countdown updates (aria-live="polite")
- [ ] Both relative and absolute time displayed

---

## 27.9 Manual Deadline Override

### Admin Capabilities
- Set custom soft/hard deadline per participant
- Clear deadline (remove restriction)
- Apply deadline to multiple participants at once (bulk override)

### Override Algorithm

```pseudocode
function overrideDeadline(participantId, newDeadline, reason, adminId):
    participant = db.getParticipant(participantId)
    
    // Validation
    IF newDeadline <= now():
        THROW ValidationError("Override deadline must be in the future")
    
    IF reason IS NULL OR reason.length < 10:
        THROW ValidationError("Reason required (minimum 10 characters)")
    
    // Preserve originals if not already preserved
    IF participant.originalHardDeadline IS NULL:
        participant.originalHardDeadline = participant.hardDeadlineDate
    IF participant.originalSoftDeadline IS NULL:
        participant.originalSoftDeadline = participant.softDeadlineDate
    
    // Apply override
    participant.deadlineOverride = newDeadline
    participant.deadlineOverrideReason = reason
    
    // If currently locked and new deadline is future, unlock
    IF participant.status = ParticipantStatus.LOCKED AND newDeadline > now():
        participant.status = ParticipantStatus.ACTIVE
    
    db.save(participant)
    
    // Audit log
    auditLog.record(
        action: AuditAction.DEADLINE_OVERRIDE,
        participantId: participant.id,
        adminId: adminId,
        data: {
            previousDeadline: participant.hardDeadlineDate,
            newDeadline: newDeadline,
            reason: reason
        }
    )
    
    // Notify participant
    sendEmail(participant, 'DEADLINE_CHANGED', {
        newDeadline: newDeadline,
        reason: reason
    })
```

### Acceptance Criteria:
- [ ] Override logged in audit trail with admin ID
- [ ] Participant notified of deadline change via email
- [ ] Original deadline preserved in `originalHardDeadline` / `originalSoftDeadline`
- [ ] Bulk override with preview before apply (shows affected count)
- [ ] Reason is required and stored for compliance

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Extension System** | [30-extension-system](30-extension-system.md) | Extension request workflow and approval |
| **Participant Service** | [27-participant-service](27-participant-service.md) | Participant deadline fields, status transitions |
| **Deadline Flow Diagram** | [diagrams/04-deadline-calculation-flow](../diagrams/04-deadline-calculation-flow.md) | Visual calculation and cron flow |
| **Status State Machine** | [diagrams/02-participant-status-states](../diagrams/02-participant-status-states.md) | Status transitions triggered by deadlines |
| **Cron System** | [34-cron-system](34-cron-system.md) | Hourly deadline checker scheduling |
| **Email Templates** | [33-email-templates](33-email-templates.md) | Deadline notification templates |
| **Notification Service** | [32-notification-service](32-notification-service.md) | In-app deadline alerts |
| **Database Schema** | [04-database-schema](04-database-schema.md) | `participant` deadline columns |
| **Enums** | [06-enums-constants](06-enums-constants.md) | `DeadlineType`, `ParticipantStatus` |
| **Shared Constants** | [66-shared-constants](../../66-shared-constants.md) | Deadline color schemes |
| **Exam Hierarchy** | [13-exam-hierarchy](13-exam-hierarchy.md) | `inheritDeadline` from parent exams |
| **Audit Logging** | [46-audit-logging](46-audit-logging.md) | Deadline change audit events |

### Key Algorithm References
- **Initial Calculation**: Section 27.3 `calculateInitialDeadlines()`
- **Extension Application**: Section 27.3 `applyExtension()` and `applyAdditionalExtension()`
- **Effective Deadline**: Section 27.3 `getEffectiveDeadline()` priority order
- **Cron Enforcement**: Section 27.6 4-phase `runDeadlineChecker()`

---

*Next: `30-extension-system.md`*
