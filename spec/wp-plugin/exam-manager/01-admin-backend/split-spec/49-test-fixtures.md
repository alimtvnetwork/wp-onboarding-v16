# 49. Test Data Fixtures

## Overview
Pre-defined test data fixtures for development, testing, and demonstration purposes.

---

## 49.1 Sample Exam Fixture

### Complete Exam (All Fields)

```json
{
  "id": 1,
  "title": "Advanced JavaScript Certification",
  "slug": "advanced-javascript",
  "description": "Comprehensive exam covering ES6+, async patterns, and best practices",
  "content": "## Module 1: ES6 Fundamentals\n\nLearn about...\n\n## Module 2: Async Programming\n\nUnderstand promises...\n\n## Module 3: Design Patterns\n\nImplement common patterns...",
  "visibility": "AUTHENTICATED",
  "parentId": null,
  "sortOrder": 1,
  "softDeadlineDays": 14,
  "hardDeadlineDays": 21,
  "inheritDeadline": false,
  "requiresPrerequisites": true,
  "allowExtensions": true,
  "maxExtensionDays": 14,
  "isActive": true,
  "sectionCount": 3,
  "createdBy": 1,
  "createdAt": "2026-01-01T00:00:00Z",
  "updatedAt": "2026-01-15T12:30:00Z"
}
```

### Minimal Exam

```json
{
  "title": "Quick Quiz",
  "slug": "quick-quiz",
  "content": "## Question 1\n\nAnswer here...",
  "visibility": "PUBLIC",
  "isActive": true
}
```

### Sub-Exam (Child)

```json
{
  "title": "JavaScript Basics - Part 1",
  "slug": "js-basics-part-1",
  "parentId": 1,
  "sortOrder": 1,
  "inheritDeadline": true,
  "content": "## Introduction\n\nBasics of JS..."
}
```

---

## 49.2 Participant Fixtures

### Participant at Each Status

```json
[
  {
    "id": 1,
    "examId": 1,
    "userId": 10,
    "email": "invited@example.com",
    "status": "INVITED",
    "progressPercent": 0,
    "createdAt": "2026-01-20T10:00:00Z",
    "softDeadline": "2026-02-03T10:00:00Z",
    "hardDeadline": "2026-02-10T10:00:00Z"
  },
  {
    "id": 2,
    "examId": 1,
    "userId": 11,
    "email": "active@example.com",
    "status": "ACTIVE",
    "progressPercent": 35,
    "startedAt": "2026-01-21T09:00:00Z",
    "softDeadline": "2026-02-04T09:00:00Z",
    "hardDeadline": "2026-02-11T09:00:00Z"
  },
  {
    "id": 3,
    "examId": 1,
    "userId": 12,
    "email": "paused@example.com",
    "status": "PAUSED",
    "progressPercent": 50,
    "pausedAt": "2026-01-25T14:00:00Z",
    "pauseReason": "Personal circumstances"
  },
  {
    "id": 4,
    "examId": 1,
    "userId": 13,
    "email": "soft-deadline@example.com",
    "status": "SOFT_DEADLINE_REACHED",
    "progressPercent": 65,
    "softDeadlineReachedAt": "2026-01-28T10:00:00Z"
  },
  {
    "id": 5,
    "examId": 1,
    "userId": 14,
    "email": "hard-deadline@example.com",
    "status": "HARD_DEADLINE_REACHED",
    "progressPercent": 80,
    "hardDeadlineReachedAt": "2026-01-30T10:00:00Z"
  },
  {
    "id": 6,
    "examId": 1,
    "userId": 15,
    "email": "extended@example.com",
    "status": "EXTENDED",
    "progressPercent": 70,
    "extensionGrantedAt": "2026-01-29T16:00:00Z",
    "extensionDays": 7,
    "originalHardDeadline": "2026-02-05T10:00:00Z",
    "hardDeadline": "2026-02-12T10:00:00Z"
  },
  {
    "id": 7,
    "examId": 1,
    "userId": 16,
    "email": "completed@example.com",
    "status": "COMPLETED",
    "progressPercent": 100,
    "completedAt": "2026-01-28T11:30:00Z"
  },
  {
    "id": 8,
    "examId": 1,
    "userId": 17,
    "email": "locked@example.com",
    "status": "LOCKED",
    "progressPercent": 45,
    "lockedAt": "2026-01-30T00:00:00Z",
    "lockReason": "Hard deadline passed"
  },
  {
    "id": 9,
    "examId": 1,
    "userId": 18,
    "email": "withdrawn@example.com",
    "status": "WITHDRAWN",
    "progressPercent": 20,
    "withdrawnAt": "2026-01-22T15:00:00Z",
    "withdrawReason": "Changed career path"
  }
]
```

### Anonymous Participant

```json
{
  "id": 100,
  "examId": 1,
  "userId": null,
  "email": "anon-1706180400-abc123@exam.local",
  "trackingId": "trk_abc123def456",
  "status": "ACTIVE",
  "progressPercent": 25,
  "isAnonymous": true,
  "secretKeyId": 5,
  "createdAt": "2026-01-25T10:00:00Z"
}
```

---

## 49.3 Secret Key Fixtures

### Active Secret Key

```json
{
  "id": 5,
  "examId": 1,
  "keyHash": "$argon2id$v=19$m=65536,t=3,p=4$...",
  "label": "Team Alpha Access",
  "isActive": true,
  "usageLimit": 50,
  "usageCount": 12,
  "expiresAt": "2026-03-01T00:00:00Z",
  "lastUsedAt": "2026-01-25T14:30:00Z",
  "ipPattern": null,
  "createdBy": 1,
  "createdAt": "2026-01-01T00:00:00Z"
}
```

### Expired Secret Key

```json
{
  "id": 6,
  "examId": 1,
  "keyHash": "$argon2id$v=19$...",
  "label": "Q4 2025 Access",
  "isActive": true,
  "usageLimit": 100,
  "usageCount": 87,
  "expiresAt": "2025-12-31T23:59:59Z",
  "createdAt": "2025-10-01T00:00:00Z"
}
```

### Limit Reached Key

```json
{
  "id": 7,
  "examId": 1,
  "keyHash": "$argon2id$v=19$...",
  "label": "Limited Access",
  "isActive": true,
  "usageLimit": 10,
  "usageCount": 10,
  "expiresAt": null,
  "createdAt": "2026-01-15T00:00:00Z"
}
```

---

## 49.4 Extension Request Fixtures

### Pending Request

```json
{
  "id": 1,
  "participantId": 5,
  "requestedDays": 7,
  "reason": "I experienced unexpected family circumstances that prevented me from completing the exam on time. My father was hospitalized and I needed to take care of family matters.",
  "attachmentPath": "/uploads/extensions/medical_note_123.pdf",
  "status": "PENDING",
  "requestedAt": "2026-01-30T09:00:00Z",
  "reviewedBy": null,
  "reviewedAt": null,
  "grantedDays": null,
  "denialReason": null
}
```

### Approved Request

```json
{
  "id": 2,
  "participantId": 6,
  "requestedDays": 14,
  "reason": "Work project deadline conflict requiring full attention for two weeks.",
  "status": "APPROVED",
  "requestedAt": "2026-01-28T10:00:00Z",
  "reviewedBy": 1,
  "reviewedAt": "2026-01-29T16:00:00Z",
  "grantedDays": 7,
  "denialReason": null
}
```

### Denied Request

```json
{
  "id": 3,
  "participantId": 8,
  "requestedDays": 30,
  "reason": "Just need more time.",
  "status": "DENIED",
  "requestedAt": "2026-01-29T11:00:00Z",
  "reviewedBy": 1,
  "reviewedAt": "2026-01-29T14:00:00Z",
  "grantedDays": null,
  "denialReason": "Reason does not meet our extension criteria. Please provide specific circumstances that prevented completion."
}
```

---

## 49.5 Checklist & Progress Fixtures

### Exam Checklist Items

```json
[
  {
    "id": 1,
    "examId": 1,
    "phase": "PRE",
    "label": "Watch introduction video",
    "description": "15-minute overview of the certification",
    "sortOrder": 1,
    "isRequired": true,
    "videoUrl": "https://youtube.com/watch?v=example1"
  },
  {
    "id": 2,
    "examId": 1,
    "phase": "PRE",
    "label": "Read coding guidelines",
    "sortOrder": 2,
    "isRequired": true,
    "linkUrl": "https://example.com/guidelines"
  },
  {
    "id": 3,
    "examId": 1,
    "phase": "IN_EXAM",
    "label": "Module 1: ES6 Fundamentals",
    "sectionNumber": 1,
    "sortOrder": 1,
    "isRequired": true
  },
  {
    "id": 4,
    "examId": 1,
    "phase": "IN_EXAM",
    "label": "Module 2: Async Programming",
    "sectionNumber": 2,
    "sortOrder": 2,
    "isRequired": true
  },
  {
    "id": 5,
    "examId": 1,
    "phase": "IN_EXAM",
    "label": "Module 3: Design Patterns",
    "sectionNumber": 3,
    "sortOrder": 3,
    "isRequired": true
  },
  {
    "id": 6,
    "examId": 1,
    "phase": "POST",
    "label": "Submit LinkedIn profile",
    "sortOrder": 1,
    "isRequired": false,
    "requiresEvidence": true,
    "evidenceType": "URL"
  }
]
```

### Participant Progress

```json
[
  {
    "participantId": 2,
    "itemId": 1,
    "completedAt": "2026-01-21T09:30:00Z"
  },
  {
    "participantId": 2,
    "itemId": 2,
    "completedAt": "2026-01-21T10:00:00Z"
  },
  {
    "participantId": 2,
    "itemId": 3,
    "completedAt": "2026-01-22T14:00:00Z"
  }
]
```

---

## 49.6 Edge Case Fixtures

### Exam with No Deadline

```json
{
  "title": "Self-Paced Course",
  "slug": "self-paced",
  "softDeadlineDays": null,
  "hardDeadlineDays": null,
  "allowExtensions": false
}
```

### Participant with Multiple Extensions

```json
{
  "id": 50,
  "examId": 1,
  "status": "EXTENDED",
  "extensionDays": 21,
  "originalHardDeadline": "2026-01-15T00:00:00Z",
  "hardDeadline": "2026-02-05T00:00:00Z",
  "extensionHistory": [
    { "grantedDays": 7, "grantedAt": "2026-01-14T10:00:00Z" },
    { "grantedDays": 7, "grantedAt": "2026-01-21T14:00:00Z" },
    { "grantedDays": 7, "grantedAt": "2026-01-28T09:00:00Z" }
  ]
}
```

### Locked Exam (Inactive)

```json
{
  "id": 99,
  "title": "Archived Exam 2024",
  "slug": "archived-2024",
  "isActive": false,
  "visibility": "PRIVATE"
}
```

---

## 49.7 Performance Test Data

### Volume Specifications

| Scenario | Exams | Participants | Progress Records |
|----------|-------|--------------|------------------|
| Small | 5 | 50 | 500 |
| Medium | 20 | 500 | 10,000 |
| Large | 50 | 5,000 | 150,000 |
| Stress | 100 | 50,000 | 2,000,000 |

### Seeder Commands

```bash
# Development seed (small)
wp eqm seed --size=small

# Demo seed (medium with realistic distribution)
wp eqm seed --size=medium --demo

# Performance test seed (large)
wp eqm seed --size=large

# Stress test seed
wp eqm seed --size=stress --skip-events
```

### Distribution Rules

For realistic test data:
- 10% INVITED (not started)
- 35% ACTIVE (in progress)
- 5% PAUSED
- 15% SOFT_DEADLINE_REACHED
- 10% HARD_DEADLINE_REACHED
- 5% EXTENDED
- 15% COMPLETED
- 3% LOCKED
- 2% WITHDRAWN

---

## 49.8 Seed Script Pseudocode

```php
class TestDataSeeder {
    public function seed(string $size): void {
        $config = $this->getConfig($size);
        
        // Phase 1: Create users
        $users = $this->createUsers($config['users']);
        
        // Phase 2: Create exams with hierarchy
        $exams = $this->createExams($config['exams']);
        foreach ($exams as $exam) {
            $this->createChecklists($exam);
            $this->createSecretKeys($exam, $config['keysPerExam']);
        }
        
        // Phase 3: Create participants with distribution
        foreach ($exams as $exam) {
            $this->createParticipants(
                $exam,
                $users,
                $config['participantsPerExam'],
                $config['statusDistribution']
            );
        }
        
        // Phase 4: Create progress records
        $this->createProgressRecords($config['progressDensity']);
        
        // Phase 5: Create extension requests
        $this->createExtensionRequests($config['extensionRate']);
        
        // Phase 6: Recalculate cached values
        $this->recalculateProgress();
    }
    
    private function getConfig(string $size): array {
        return match($size) {
            'small' => [
                'users' => 50,
                'exams' => 5,
                'participantsPerExam' => 10,
                'keysPerExam' => 2,
                'progressDensity' => 0.5,
                'extensionRate' => 0.1,
            ],
            'medium' => [
                'users' => 500,
                'exams' => 20,
                'participantsPerExam' => 25,
                'keysPerExam' => 5,
                'progressDensity' => 0.6,
                'extensionRate' => 0.15,
            ],
            'large' => [
                'users' => 5000,
                'exams' => 50,
                'participantsPerExam' => 100,
                'keysPerExam' => 10,
                'progressDensity' => 0.7,
                'extensionRate' => 0.2,
            ],
            'stress' => [
                'users' => 50000,
                'exams' => 100,
                'participantsPerExam' => 500,
                'keysPerExam' => 20,
                'progressDensity' => 0.8,
                'extensionRate' => 0.25,
            ],
        };
    }
}
```

---

## 49.9 Cleanup Commands

```bash
# Clear all test data (preserves schema)
wp eqm seed --clear

# Clear specific entity
wp eqm seed --clear=participants

# Reset to initial state
wp eqm seed --reset
```

---

## Acceptance Criteria

- [ ] All fixtures validate against schema
- [ ] Seeder creates realistic data relationships
- [ ] Status distribution matches specification
- [ ] Progress percentages correctly calculated
- [ ] Large seed completes in < 5 minutes
- [ ] Stress seed completes in < 30 minutes
- [ ] Cleanup removes all test data
- [ ] No test data markers in production

---

## Related Specifications

| Topic | Spec |
|-------|------|
| Testing Requirements | [41-testing-requirements](41-testing-requirements.md) |
| Database Schema | [04-database-schema](04-database-schema.md) |
| Participant Service | [27-participant-service](27-participant-service.md) |
| Enums | [06-enums-constants](06-enums-constants.md) |

---

*This concludes the test fixtures specification.*
