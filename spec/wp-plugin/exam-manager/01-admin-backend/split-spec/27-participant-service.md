# 25. Participant Service

## Overview
Backend PHP service handling participant lifecycle, enrollment, and data management.

---

## 25.1 Service Interface

### Core Methods
```
ParticipantService
├── create(data: ParticipantData): Participant
├── update(id: int, data: Partial<ParticipantData>): Participant
├── delete(id: int, soft: bool = true): void
├── findById(id: int): Participant|null
├── findByEmail(email: string, examId: int): Participant|null
├── findByExam(examId: int, filters: FilterOptions): PaginatedResult
├── bulkCreate(participants: ParticipantData[]): BulkResult
├── updateStatus(id: int, status: ParticipantStatus, reason: string): void
└── getProgress(id: int): ProgressData
```

### Acceptance Criteria:
- [ ] All methods use ORM, no raw SQL
- [ ] Proper exception handling with typed exceptions
- [ ] Audit logging for create/update/delete operations
- [ ] Email uniqueness enforced per exam

---

## 25.2 Enrollment Logic

### Status Creation Rules (IMPORTANT!)
| Enrollment Method | Initial Status | Reason |
|-------------------|----------------|--------|
| **Self-Signup** (via `/signup`) | `ACTIVE` | User chose to join, immediate access |
| **Admin-Added** (via admin panel) | `INVITED` | Awaiting user's first login |
| **Secret Key Access** | `ACTIVE` | Anonymous user with valid access |

### Single Enrollment (Admin-Added)
1. Validate email format and uniqueness within exam
2. Validate deadline constraints (soft < hard, both future)
3. Create participant record with **INVITED** status
4. Store originalSoftDeadline and originalHardDeadline
5. Queue welcome email with access link
6. Return created participant with access URL

### Batch Enrollment (Admin-Added)
1. Parse and validate all rows first
2. Return validation report before committing
3. On confirmation, create all valid rows with **INVITED** status
4. Queue batch of welcome emails
5. Return summary with success/failure counts

### Acceptance Criteria:
- [ ] Duplicate emails within batch flagged
- [ ] Partial success supported (valid rows created)
- [ ] Transaction rollback on critical failures
- [ ] Rate limiting on batch operations

---

## 25.3 Status Management

### Status Enum (defined in Spec 04)
| Status | Description | Allowed Transitions |
|--------|-------------|---------------------|
| INVITED | Initial state, not yet started | ACTIVE, WITHDRAWN |
| ACTIVE | Currently participating | PAUSED, SOFT_DEADLINE_REACHED, COMPLETED, LOCKED, WITHDRAWN |
| PAUSED | Temporarily on hold | ACTIVE, WITHDRAWN |
| SOFT_DEADLINE_REACHED | Soft deadline passed, still editable | ACTIVE, HARD_DEADLINE_REACHED, EXTENDED, COMPLETED, LOCKED, WITHDRAWN |
| HARD_DEADLINE_REACHED | Hard deadline passed, pending lock | EXTENDED, LOCKED |
| EXTENDED | Extension granted, back to active state | ACTIVE, SOFT_DEADLINE_REACHED, COMPLETED, LOCKED, WITHDRAWN |
| COMPLETED | Finished all requirements | (terminal) |
| LOCKED | Access revoked after deadline | (terminal) |
| WITHDRAWN | Dropped out voluntarily | (terminal) |

### Automatic Transitions (via Cron)
- `ACTIVE` → `SOFT_DEADLINE_REACHED`: When soft deadline date passes
- `SOFT_DEADLINE_REACHED` → `HARD_DEADLINE_REACHED`: When hard deadline date passes
- `HARD_DEADLINE_REACHED` → `LOCKED`: Configurable grace period (default: 24 hours)
- `EXTENDED` → `SOFT_DEADLINE_REACHED` / `HARD_DEADLINE_REACHED`: When extension deadline passes

### Acceptance Criteria:
- [ ] Status transitions validated against allowed map
- [ ] Reason required for manual transitions
- [ ] Email notification configurable per transition type
- [ ] Transition history preserved with actor and timestamp

---

## 25.4 Data Validation

### Required Fields
- `email`: Valid email format, max 255 chars
- `name`: Non-empty string, max 100 chars
- `exam_id`: Valid existing exam ID

### Optional Fields
- `soft_deadline`: ISO date, must be future
- `hard_deadline`: ISO date, must be after soft_deadline
- `notes`: Text, max 2000 chars

### Acceptance Criteria:
- [ ] Validation errors include field name and message
- [ ] Date validation considers timezone
- [ ] Email normalized to lowercase
- [ ] Name trimmed of whitespace

---

## 25.5 Search and Filtering

### Filter Options
- `exam_id`: Filter to single exam
- `status`: Single status or array
- `search`: Full-text on name/email
- `deadline_before`: Deadline approaching filter
- `deadline_after`: Recent additions filter
- `is_overdue`: Boolean for past hard deadline

### Sort Options
- `name`, `email`, `status`, `progress`
- `soft_deadline`, `hard_deadline`, `created_at`
- Direction: ASC or DESC

### Acceptance Criteria:
- [ ] Pagination with configurable page size (max 100)
- [ ] Total count returned for pagination UI
- [ ] Efficient indexing for common queries
- [ ] Search is case-insensitive

---

## 25.6 Export Functionality

### CSV Export
Columns: name, email, exam_title, status, progress_percent, soft_deadline, hard_deadline, created_at

### JSON Export
Full participant object including:
- All fields from CSV
- Progress breakdown by checklist
- Status history
- Extension history

### Acceptance Criteria:
- [ ] Export respects current filters
- [ ] Large exports (>1000) processed async
- [ ] Download link emailed for async exports
- [ ] Export metadata includes generation timestamp

---

## 25.7 Anonymous Participant Tracking

### Overview
Participants who access exams via secret key URLs without logging in are tracked as "anonymous" using cookies. These anonymous records can later be migrated to a registered user account.

### Anonymous Participant Identification

| Identifier | Storage | Purpose |
|------------|---------|---------|
| `trackingId` | Cookie + DB | Unique identifier for anonymous participant |
| `examSlug` | Cookie name | Exam-specific isolation (`eqm_anon_{examSlug}`) |
| `userId` | DB field | NULL for anonymous, set after migration |
| `ipHash` | DB field | Hashed IP for analytics (not identification) |

### Cookie Structure

```
Cookie Name: eqm_anon_{examSlug}
Value: {trackingId}_{timestamp}
Expires: 30 days (configurable in config/defaults.json)
HttpOnly: true
Secure: true (in production)
SameSite: Lax
```

### Anonymous Participant Record

```sql
-- Anonymous participant has these characteristics:
userId = NULL
trackingId = 'anon_abc123def456'  -- Generated UUID
accessMethod = 'SECRET_KEY'
secretKeyId = 15  -- Which secret key was used
status = 'ACTIVE'
```

### Acceptance Criteria:
- [ ] Anonymous participants fully functional (progress, deadlines, etc.)
- [ ] Tracking cookie scoped to specific exam (no cross-exam leakage)
- [ ] Anonymous records expire after configurable period (default: 90 days)
- [ ] IP addresses stored as SHA-256 hash only

---

## 25.8 Anonymous-to-Registered Migration

### Overview
When an anonymous user creates an account or logs in, their anonymous participation record should be migrated to their new user account, preserving all progress.

### Trigger Conditions

Migration is triggered when:
1. User **registers** while having an active anonymous session cookie
2. User **logs in** while having an active anonymous session cookie
3. User clicks **"Claim Progress"** button (explicit migration UI)

### Migration Detection Algorithm

```pseudocode
function detectMigrationOpportunity(userId, examSlug):
    // Step 1: Check for anonymous tracking cookie
    anonCookieName = 'eqm_anon_' + examSlug
    anonCookieValue = getCookie(anonCookieName)
    
    IF anonCookieValue IS NULL:
        RETURN { hasMigration: false, reason: 'NO_ANONYMOUS_SESSION' }
    
    // Step 2: Parse tracking ID from cookie
    trackingId = parseTrackingId(anonCookieValue)  // Format: {trackingId}_{timestamp}
    
    IF trackingId IS NULL:
        RETURN { hasMigration: false, reason: 'INVALID_COOKIE_FORMAT' }
    
    // Step 3: Find anonymous participant record
    anonParticipant = db.query(
        "SELECT * FROM participants 
         WHERE trackingId = ? 
         AND examSlug = ? 
         AND userId IS NULL",
        [trackingId, examSlug]
    )
    
    IF anonParticipant IS NULL:
        RETURN { hasMigration: false, reason: 'NO_ANONYMOUS_RECORD' }
    
    // Step 4: Check if user already has a record for this exam
    existingParticipant = db.query(
        "SELECT * FROM participants 
         WHERE userId = ? 
         AND examSlug = ?",
        [userId, examSlug]
    )
    
    IF existingParticipant IS NOT NULL:
        RETURN { 
            hasMigration: true, 
            type: 'MERGE_REQUIRED',
            anonParticipant: anonParticipant,
            existingParticipant: existingParticipant
        }
    ELSE:
        RETURN { 
            hasMigration: true, 
            type: 'CLAIM_AVAILABLE',
            anonParticipant: anonParticipant
        }
```

### Migration Algorithm (Claim - No Existing Record)

---

## ⚠️ CRITICAL IMPLEMENTATION WARNING - HIGH RISK AREA #3

> **AI IMPLEMENTATION ALERT**: This is the #3 cause of data corruption.
> 
> **THE WRONG WAY** (creates duplicates):
> ```php
> // ❌ WRONG: Directly assigning user ID without checking for existing record
> $anonParticipant->userId = $userId;
> $anonParticipant->save();
> // If user already has a participant record for this exam → DUPLICATE DATA!
> ```
> 
> **THE CORRECT WAY**:
> ```php
> // ✅ CORRECT: Check for existing participant FIRST
> $existingParticipant = $db->findOne('participants', [
>     'userId' => $userId,
>     'examId' => $anonParticipant->examId
> ]);
> 
> if ($existingParticipant !== null) {
>     // MERGE required - don't just claim
>     return ['type' => 'MERGE_REQUIRED', 'existing' => $existingParticipant];
> }
> 
> // Safe to claim
> $anonParticipant->userId = $userId;
> $anonParticipant->save();
> ```
> 
> **WHY THIS MATTERS**: Creates duplicate participant records per exam, violates unique constraints, causes data inconsistency, and corrupts progress tracking.

---

### Pre-Implementation Checklist (MANDATORY)

Before implementing anonymous migration, verify:

- [ ] ✅ You CHECK for existing participant record BEFORE claiming
- [ ] ✅ You DELETE the anonymous cookie AFTER successful migration
- [ ] ✅ You PRESERVE the tracking ID in `migratedFromTrackingId` for audit
- [ ] ✅ You use a DATABASE TRANSACTION (rollback on any failure)
- [ ] ✅ You TRANSFER related records (extension requests, submissions)
- [ ] ❌ You do NOT directly assign userId without checking for duplicates
- [ ] ❌ You do NOT leave the anonymous cookie after migration

---

```pseudocode
function claimAnonymousProgress(userId, examSlug):
    // ============================================================
    // CRITICAL: Always call detectMigrationOpportunity() first
    // This checks for existing participant records
    // ============================================================
    
    detection = detectMigrationOpportunity(userId, examSlug)
    
    IF detection.hasMigration = false:
        RETURN { success: false, reason: detection.reason }
    
    // ============================================================
    // CRITICAL: If MERGE_REQUIRED, do NOT proceed with claim
    // Redirect to merge endpoint which handles conflict resolution
    // ============================================================
    IF detection.type != 'CLAIM_AVAILABLE':
        RETURN { success: false, reason: 'MERGE_REQUIRED_USE_MERGE_ENDPOINT' }
    
    anonParticipant = detection.anonParticipant
    
    // Transaction start - REQUIRED for atomicity
    db.beginTransaction()
    
    TRY:
        // Step 1: DOUBLE-CHECK no existing record (race condition protection)
        existingCheck = db.findOne('participants', {
            userId: userId,
            examId: anonParticipant.examId,
            deletedAt: NULL
        })
        
        IF existingCheck IS NOT NULL:
            db.rollback()
            RETURN { success: false, reason: 'RACE_CONDITION_EXISTING_FOUND' }
        
        // Step 2: Assign user ID to anonymous record
        anonParticipant.userId = userId
        
        // Step 3: Update access method
        anonParticipant.accessMethod = 'MIGRATED_FROM_ANONYMOUS'
        
        // Step 4: Preserve migration metadata (CRITICAL for audit)
        anonParticipant.migratedAt = now()
        anonParticipant.migratedFromTrackingId = anonParticipant.trackingId
        // Keep trackingId for audit trail - do NOT null it
        
        // Step 5: Update status if needed
        IF anonParticipant.status = 'INVITED':
            anonParticipant.status = 'ACTIVE'
        
        db.save(anonParticipant)
        
        // Step 6: Transfer related records (CRITICAL - don't orphan data)
        db.update('extension_requests', 
            { participantId: anonParticipant.id },
            { participantId: anonParticipant.id }  // No change needed, but verify
        )
        db.update('submissions',
            { participantId: anonParticipant.id },
            { participantId: anonParticipant.id }
        )
        
        // Step 7: Audit log
        auditLog.record(
            action: AuditAction.ANONYMOUS_MIGRATED,
            participantId: anonParticipant.id,
            userId: userId,
            data: {
                originalTrackingId: anonParticipant.migratedFromTrackingId,
                progressPreserved: getProgressPercentage(anonParticipant)
            }
        )
        
        db.commit()
        
        // Step 8: Clear anonymous cookie (CRITICAL - prevents re-migration attempts)
        deleteCookie('eqm_anon_' + examSlug)
        
        // Step 9: Set authenticated session cookie
        setAuthenticatedSessionCookie(userId, examSlug)
        
        RETURN { 
            success: true, 
            participant: anonParticipant,
            progressPreserved: true
        }
        
    CATCH error:
        db.rollback()
        logError(error, { context: 'anonymous_migration', userId, examSlug })
        THROW MigrationException(error)
```

### Merge Algorithm (Existing Record Exists)

```pseudocode
function mergeAnonymousProgress(userId, examSlug, mergeStrategy: 'KEEP_REGISTERED' | 'KEEP_ANONYMOUS' | 'MERGE_PROGRESS'):
    detection = detectMigrationOpportunity(userId, examSlug)
    
    IF detection.type != 'MERGE_REQUIRED':
        RETURN { success: false, reason: 'NO_MERGE_NEEDED' }
    
    anonParticipant = detection.anonParticipant
    existingParticipant = detection.existingParticipant
    
    db.beginTransaction()
    
    TRY:
        SWITCH mergeStrategy:
            CASE 'KEEP_REGISTERED':
                // Discard anonymous progress, keep registered record
                result = discardAnonymousRecord(anonParticipant)
                finalParticipant = existingParticipant
                
            CASE 'KEEP_ANONYMOUS':
                // Replace registered with anonymous (rare, user choice)
                result = replaceWithAnonymous(existingParticipant, anonParticipant, userId)
                finalParticipant = anonParticipant
                
            CASE 'MERGE_PROGRESS':
                // Combine progress from both records (default)
                result = mergeProgressRecords(existingParticipant, anonParticipant)
                finalParticipant = existingParticipant
        
        // Audit log
        auditLog.record(
            action: AuditAction.PROGRESS_MERGED,
            participantId: finalParticipant.id,
            userId: userId,
            data: {
                mergeStrategy: mergeStrategy,
                anonProgress: getProgressPercentage(anonParticipant),
                existingProgress: getProgressPercentage(existingParticipant),
                finalProgress: getProgressPercentage(finalParticipant)
            }
        )
        
        db.commit()
        
        // Clear anonymous cookie
        deleteCookie('eqm_anon_' + examSlug)
        
        RETURN { 
            success: true, 
            participant: finalParticipant,
            mergeStrategy: mergeStrategy
        }
        
    CATCH error:
        db.rollback()
        THROW MergeException(error)
```

### Progress Merge Rules

```pseudocode
function mergeProgressRecords(registered, anonymous):
    // Rule 1: Checklist items - UNION (keep all completed from both)
    registeredItems = getCompletedChecklistItems(registered.id)
    anonItems = getCompletedChecklistItems(anonymous.id)
    
    FOR EACH item IN anonItems:
        IF item NOT IN registeredItems:
            copyChecklistCompletion(item, registered.id)
    
    // Rule 2: Deadline - Keep registered user's deadline
    // (Anonymous deadline was temporary, registered is intentional)
    // No change needed
    
    // Rule 3: Extensions - Sum both extension days
    IF anonymous.extensionDays > 0:
        registered.extensionDays = registered.extensionDays + anonymous.extensionDays
        // Recalculate extension deadline
        registered.extensionDeadlineDate = calculateExtensionDeadline(registered)
    
    // Rule 4: Status - Keep higher priority status
    registered.status = higherPriorityStatus(registered.status, anonymous.status)
    
    // Rule 5: Created date - Keep earlier date (for accurate participation duration)
    IF anonymous.createdAt < registered.createdAt:
        registered.createdAt = anonymous.createdAt
    
    // Rule 6: Delete anonymous record (soft delete)
    anonymous.deletedAt = now()
    anonymous.deletedReason = 'MERGED_TO_USER_' + registered.userId
    db.save(anonymous)
    
    db.save(registered)
    RETURN registered
```

### Status Priority Order (for merge)

```pseudocode
function higherPriorityStatus(status1, status2):
    priorityOrder = [
        'COMPLETED',           // Highest - don't downgrade
        'EXTENDED',
        'ACTIVE',
        'SOFT_DEADLINE_REACHED',
        'PAUSED',
        'INVITED',
        'HARD_DEADLINE_REACHED',
        'LOCKED',              // Lower - can upgrade from
        'WITHDRAWN'            // Lowest
    ]
    
    priority1 = priorityOrder.indexOf(status1)
    priority2 = priorityOrder.indexOf(status2)
    
    // Lower index = higher priority
    RETURN priority1 < priority2 ? status1 : status2
```

### Edge Cases

| Scenario | Handling |
|----------|----------|
| Anonymous record already expired | Migration fails with `ANONYMOUS_EXPIRED` error |
| Anonymous on different exam | No migration - cookies are exam-scoped |
| Multiple anonymous sessions | Only current exam's cookie processed |
| Anonymous completed exam | Allow claim, status becomes `COMPLETED` |
| Anonymous was locked | Allow claim, status stays `LOCKED` |
| Registered user is admin | Admin users can claim, no special rules |
| Anonymous has pending extension request | Transfer request to registered participant |

### API Endpoints

```
POST /api/participants/claim-anonymous
Body: { examSlug: string }
Response: { success: boolean, participant: Participant, progressPreserved: boolean }

POST /api/participants/merge-anonymous
Body: { examSlug: string, strategy: 'KEEP_REGISTERED' | 'KEEP_ANONYMOUS' | 'MERGE_PROGRESS' }
Response: { success: boolean, participant: Participant, mergeStrategy: string }

GET /api/participants/migration-status
Query: { examSlug: string }
Response: { hasMigration: boolean, type: string, anonProgress?: number, existingProgress?: number }
```

### Acceptance Criteria:
- [ ] Progress fully preserved during claim (no data loss)
- [ ] Merge strategy choice presented to user when conflict exists
- [ ] Anonymous cookie cleared after successful migration
- [ ] Audit trail records all migration details
- [ ] Transaction rollback on any failure
- [ ] Extension requests transferred during migration
- [ ] Duplicate detection prevents creating second participant record

---

## 25.9 Duplicate Detection

### Purpose
Prevent creating duplicate participant records for the same user+exam combination.

### Detection Algorithm

```pseudocode
function checkForDuplicate(email, examId, userId = null):
    // Check 1: Email-based duplicate (regardless of user account)
    emailMatch = db.query(
        "SELECT * FROM participants 
         WHERE LOWER(email) = LOWER(?) 
         AND examId = ? 
         AND deletedAt IS NULL",
        [email, examId]
    )
    
    IF emailMatch IS NOT NULL:
        RETURN { 
            isDuplicate: true, 
            type: 'EMAIL_EXISTS',
            existingParticipant: emailMatch
        }
    
    // Check 2: User ID-based duplicate (if logged in)
    IF userId IS NOT NULL:
        userMatch = db.query(
            "SELECT * FROM participants 
             WHERE userId = ? 
             AND examId = ? 
             AND deletedAt IS NULL",
            [userId, examId]
        )
        
        IF userMatch IS NOT NULL:
            RETURN { 
                isDuplicate: true, 
                type: 'USER_EXISTS',
                existingParticipant: userMatch
            }
    
    RETURN { isDuplicate: false }
```

### Duplicate Resolution Options

| Situation | Options Presented |
|-----------|-------------------|
| Email exists, no user account | "This email is already registered. Login or use different email." |
| User already participating | Redirect to existing progress page |
| Anonymous exists, now logging in | Trigger migration flow (see 25.8) |

### Acceptance Criteria:
- [ ] Email comparison is case-insensitive
- [ ] Soft-deleted records not considered duplicates
- [ ] Clear error messages for each duplicate type
- [ ] Redirect to existing participation when appropriate

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Progress Tracking** | [28-participant-progress](28-participant-progress.md) | Progress calculation formulas |
| **Deadline Engine** | [29-deadline-engine](29-deadline-engine.md) | Deadline calculation and enforcement |
| **Extension System** | [30-extension-system](30-extension-system.md) | Extension request workflow |
| **Secret Key Access** | [24-secret-key-service](24-secret-key-service.md) | Anonymous participant creation |
| **Participant Management UI** | [39-participant-management](39-participant-management.md) | Admin interface for participants |
| **Status Diagram** | [diagrams/02-participant-status-states](../diagrams/02-participant-status-states.md) | Visual state machine |
| **Database Schema** | [04-database-schema](04-database-schema.md) | `participant` table definition |
| **Enums** | [06-enums-constants](06-enums-constants.md) | `ParticipantStatus` enum |
| **Shared Constants** | [66-shared-constants](../../66-shared-constants.md) | Cookie naming patterns |
| **Cron System** | [34-cron-system](34-cron-system.md) | Status transition triggers |
| **Email Templates** | [33-email-templates](33-email-templates.md) | Enrollment notifications |
| **Audit Logging** | [46-audit-logging](46-audit-logging.md) | Participant lifecycle events |

### Key Algorithm References
- **Anonymous Migration**: Section 25.8 `claimAnonymousProgress()` and `mergeAnonymousProgress()`
- **Status Priority Order**: Section 25.8 `higherPriorityStatus()`
- **Duplicate Detection**: Section 25.9 `checkForDuplicate()`

---

*Next: `28-participant-progress.md`*
