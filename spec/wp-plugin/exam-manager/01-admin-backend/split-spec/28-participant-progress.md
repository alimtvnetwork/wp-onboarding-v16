# 26. Participant Progress

## Overview
Comprehensive progress tracking for exam participants, including checklist completion, phase-based progress, and milestone notifications.

> **Last Updated:** 2026-01-26  
> **Database Naming:** PascalCase (e.g., `ParticipantId`, `CreatedAt`)

---

## 26.1 Progress Calculation

### Core Formula
```
progressPercent = floor((completedRequiredItems / totalRequiredItems) * 100)
```

### Weighting by Phase
| Phase | Weight | Description |
|-------|--------|-------------|
| PRE | 20% | Prerequisites (videos, reading) |
| IN_EXAM | 60% | Main exam sections |
| POST | 20% | Post-exam tasks |

### Weighted Formula
```
progressPercent = floor(
    (preCompleted / preTotal * 0.20) +
    (inExamCompleted / inExamTotal * 0.60) +
    (postCompleted / postTotal * 0.20)
) * 100
```

### Edge Cases
- If a phase has no items, its weight redistributes equally
- Floor rounding ensures 100% only on true completion
- Optional items excluded from percentage

### Acceptance Criteria:
- [ ] Progress always 0-100 integer
- [ ] Floor rounding used (not round)
- [ ] Empty phases handled gracefully
- [ ] Optional items don't affect percentage

---

## 26.2 Database Schema

### ParticipantChecklist Table (Primary Progress Storage)

```sql
CREATE TABLE ParticipantChecklist (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    ParticipantId INTEGER NOT NULL,
    ChecklistId INTEGER NOT NULL,
    IsCompleted BOOLEAN NOT NULL DEFAULT 0,
    CompletedAt DATETIME DEFAULT NULL,
    
    -- Submission Data
    SubmissionValue TEXT DEFAULT NULL,
    SubmissionFilePath VARCHAR(255) DEFAULT NULL,
    SubmittedAt DATETIME DEFAULT NULL,
    
    -- Review Status
    ReviewStatus VARCHAR(30) DEFAULT NULL,
    ReviewedAt DATETIME DEFAULT NULL,
    ReviewedBy INTEGER DEFAULT NULL,
    ReviewNote TEXT DEFAULT NULL,
    
    UNIQUE(ParticipantId, ChecklistId),
    FOREIGN KEY (ParticipantId) REFERENCES Participant(Id) ON DELETE CASCADE,
    FOREIGN KEY (ChecklistId) REFERENCES ExamChecklist(Id) ON DELETE CASCADE
);

CREATE INDEX IX_ParticipantChecklist_ParticipantId ON ParticipantChecklist(ParticipantId);
CREATE INDEX IX_ParticipantChecklist_ReviewStatus ON ParticipantChecklist(ReviewStatus);
```

### Acceptance Criteria:
- [ ] One record per participant per checklist item
- [ ] Cascade delete on participant removal
- [ ] Review fields support admin workflow

---

## 26.3 Progress Service

### Service Methods
```php
class ProgressService {
    /**
     * Calculate and update participant progress
     */
    public function calculateProgress(int $participantId): int {
        $participant = $this->participantRepo->findById($participantId);
        $examId = $participant->ExamId;
        
        // Get all required checklist items for this exam
        $checklistItems = $this->checklistRepo->findRequiredByExamId($examId);
        
        if (count($checklistItems) === 0) {
            return 100; // No items = complete
        }
        
        // Count completed items
        $completedCount = $this->participantChecklistRepo
            ->countCompletedByParticipant($participantId, $checklistItems);
        
        // Calculate percentage (floor rounding)
        $percent = (int) floor(($completedCount / count($checklistItems)) * 100);
        
        // Update participant record
        $participant->ProgressPercent = $percent;
        $participant->save();
        
        return $percent;
    }
    
    /**
     * Mark a checklist item as complete
     */
    public function markComplete(
        int $participantId, 
        int $checklistId, 
        ?string $submissionValue = null,
        ?string $filePath = null
    ): ParticipantChecklist {
        $record = $this->participantChecklistRepo->findOrCreate(
            $participantId, 
            $checklistId
        );
        
        $record->IsCompleted = true;
        $record->CompletedAt = new DateTime();
        $record->SubmissionValue = $submissionValue;
        $record->SubmissionFilePath = $filePath;
        $record->SubmittedAt = new DateTime();
        $record->save();
        
        // Recalculate progress
        $this->calculateProgress($participantId);
        
        return $record;
    }
    
    /**
     * Get detailed progress breakdown
     */
    public function getProgressBreakdown(int $participantId): array {
        $participant = $this->participantRepo->findById($participantId);
        
        return [
            'overall' => $participant->ProgressPercent,
            'phases' => [
                'PRE' => $this->getPhaseProgress($participantId, 'PRE'),
                'IN_EXAM' => $this->getPhaseProgress($participantId, 'IN_EXAM'),
                'POST' => $this->getPhaseProgress($participantId, 'POST'),
            ],
            'items' => $this->getItemizedProgress($participantId),
        ];
    }
}
```

### Acceptance Criteria:
- [ ] Progress recalculated on every item change
- [ ] Breakdown available by phase
- [ ] Submission data properly stored

---

## 26.4 Section Completion Endpoint

### POST /participants/{participantId}/sections/{sectionNumber}/complete

Maps legacy section numbers to checklist items.

```php
public function completeSection(int $participantId, int $sectionNumber): JsonResponse {
    $participant = $this->participantRepo->findById($participantId);
    
    // Find checklist item for this section
    $checklistItem = $this->checklistRepo->findBySectionNumber(
        $participant->ExamId, 
        $sectionNumber
    );
    
    if ($checklistItem === null) {
        return Response::error('Section not found', 404);
    }
    
    // Mark complete
    $this->progressService->markComplete($participantId, $checklistItem->Id);
    
    // Get updated progress
    $progress = $this->progressService->calculateProgress($participantId);
    
    return Response::success([
        'sectionNumber' => $sectionNumber,
        'completed' => true,
        'progress' => $progress,
    ]);
}
```

### Response
```json
{
    "success": true,
    "data": {
        "sectionNumber": 3,
        "completed": true,
        "progress": 45
    }
}
```

### Acceptance Criteria:
- [ ] Section numbers map to checklist items
- [ ] Progress returned in response
- [ ] Invalid sections return 404

---

## 26.5 Progress Bar Display

### Frontend Component Props
```typescript
interface ProgressBarProps {
    percent: number;           // 0-100
    showLabel?: boolean;       // Show "45%" label
    size?: 'sm' | 'md' | 'lg'; // Height variant
    animated?: boolean;        // Pulse animation when near 100%
    color?: 'default' | 'success' | 'warning' | 'danger';
}
```

### Color Thresholds
| Range | Color | Meaning |
|-------|-------|---------|
| 0-25% | Red | Just started |
| 26-50% | Orange | Making progress |
| 51-75% | Yellow | Halfway there |
| 76-99% | Blue | Almost done |
| 100% | Green | Complete |

### Acceptance Criteria:
- [ ] Smooth transitions on update
- [ ] Accessible (aria-valuenow)
- [ ] Color respects color-blind users

---

## 26.6 Progress Timeline

### Timeline Events
```
Timeline Entry
├── Timestamp: DateTime
├── EventType: STARTED | SECTION_COMPLETE | PHASE_COMPLETE | FINISHED
├── Description: string
├── Progress: int (0-100)
└── Metadata: JSON
```

### Display
```
┌─────────────────────────────────────────────┐
│ Progress Timeline                           │
├─────────────────────────────────────────────┤
│ ✓ Started exam                    Jan 15    │
│   └── 0%                                    │
│ ✓ Completed Prerequisites         Jan 16    │
│   └── 20%                                   │
│ ✓ Section 1: Introduction         Jan 17    │
│   └── 35%                                   │
│ ○ Section 2: Advanced Topics      In Progress│
│   └── 45%                                   │
│ ○ Section 3: Final Project        Pending   │
│ ○ Post-Exam Reflection            Pending   │
└─────────────────────────────────────────────┘
```

### Acceptance Criteria:
- [ ] Shows all completed items with timestamps
- [ ] Current item highlighted
- [ ] Pending items grayed out

---

## 26.7 Bulk Progress Operations

### Admin Bulk Update
```php
public function bulkUpdateProgress(array $participantIds): array {
    $results = [];
    
    foreach ($participantIds as $id) {
        try {
            $results[$id] = [
                'success' => true,
                'progress' => $this->calculateProgress($id),
            ];
        } catch (Exception $e) {
            $results[$id] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    return $results;
}
```

### Use Cases
- Recalculate all progress after checklist changes
- Fix progress inconsistencies
- Migration from legacy progress table

### Acceptance Criteria:
- [ ] Handles errors per-participant
- [ ] Returns detailed results
- [ ] Logs all updates to audit

---

## 26.8 Progress Milestones

### Milestone Types
| Milestone | Trigger | Notification |
|-----------|---------|--------------|
| STARTED | First item completed | Email: "You've begun!" |
| QUARTER | 25% reached | None |
| HALFWAY | 50% reached | Email: "Halfway there!" |
| THREE_QUARTERS | 75% reached | None |
| ALMOST_DONE | 90% reached | Email: "Almost done!" |
| COMPLETED | 100% reached | Email: "Congratulations!" |
| PHASE_COMPLETE | Any phase at 100% | In-app notification |

### Milestone Detection
```php
public function checkMilestones(int $participantId, int $previousProgress, int $newProgress): array {
    $milestones = [];
    
    // Check each threshold
    $thresholds = [
        ['percent' => 25, 'type' => 'QUARTER'],
        ['percent' => 50, 'type' => 'HALFWAY'],
        ['percent' => 75, 'type' => 'THREE_QUARTERS'],
        ['percent' => 90, 'type' => 'ALMOST_DONE'],
        ['percent' => 100, 'type' => 'COMPLETED'],
    ];
    
    foreach ($thresholds as $threshold) {
        $isCrossed = $previousProgress < $threshold['percent'] 
                  && $newProgress >= $threshold['percent'];
        
        if ($isCrossed) {
            $milestones[] = $threshold['type'];
        }
    }
    
    return $milestones;
}
```

### Milestone Persistence

```sql
CREATE TABLE ParticipantMilestone (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    ParticipantId INTEGER NOT NULL,
    MilestoneType VARCHAR(50) NOT NULL,
    MilestoneData TEXT NULL,  -- JSON for phase name, etc.
    ReachedAt DATETIME NOT NULL,
    NotifiedAt DATETIME NULL,
    
    UNIQUE(ParticipantId, MilestoneType),
    FOREIGN KEY (ParticipantId) REFERENCES Participant(Id) ON DELETE CASCADE
);

CREATE INDEX IX_ParticipantMilestone_Lookup ON ParticipantMilestone(ParticipantId, MilestoneType);
```

### Acceptance Criteria:
- [ ] Milestones only triggered once per participant
- [ ] Milestone emails respect notification preferences
- [ ] Phase completion tracked separately from overall
- [ ] Milestones visible in participant activity log

---

## 26.9 Edge Cases

| Scenario | Handling |
|----------|----------|
| Exam has no checklist items | Progress = 100% (nothing to complete) |
| All items are optional | Progress = 100% (no required items) |
| All items skipped | Progress = 100% |
| New items added after start | Added to total, may decrease percentage |
| Items removed after start | Removed from total, may increase percentage |
| Negative completion (bug) | Clamp to 0% |
| Over 100% (bug) | Clamp to 100% |
| Phase weights don't sum to 1 | Normalize automatically |
| Concurrent item completions | Last write wins, recalculate from source |

---

## 26.10 Testing

### Test Cases

```php
// Test Case 1: Simple progress calculation
function testSimpleProgress(): void {
    $exam = createExamWithChecklists(10); // 10 required items
    $participant = createParticipant($exam);
    
    // Complete 5 items
    for ($i = 0; $i < 5; $i++) {
        $this->progressService->markComplete($participant->Id, $exam->checklists[$i]->Id);
    }
    
    $progress = $this->progressService->calculateProgress($participant->Id);
    $this->assertEquals(50, $progress); // 5/10 = 50%
}

// Test Case 2: Floor rounding
function testFloorRounding(): void {
    $exam = createExamWithChecklists(3); // 3 required items
    $participant = createParticipant($exam);
    
    // Complete 1 item
    $this->progressService->markComplete($participant->Id, $exam->checklists[0]->Id);
    
    $progress = $this->progressService->calculateProgress($participant->Id);
    $this->assertEquals(33, $progress); // floor(1/3 * 100) = 33, not 34
}

// Test Case 3: Optional items excluded
function testOptionalItemsExcluded(): void {
    $exam = createExam();
    $required1 = createChecklist($exam, isRequired: true);
    $required2 = createChecklist($exam, isRequired: true);
    $optional = createChecklist($exam, isRequired: false);
    
    $participant = createParticipant($exam);
    
    // Complete only 1 required item
    $this->progressService->markComplete($participant->Id, $required1->Id);
    
    $progress = $this->progressService->calculateProgress($participant->Id);
    $this->assertEquals(50, $progress); // 1/2 required = 50%
}

// Test Case 4: Empty exam = 100%
function testEmptyExamProgress(): void {
    $exam = createExamWithChecklists(0); // No items
    $participant = createParticipant($exam);
    
    $progress = $this->progressService->calculateProgress($participant->Id);
    $this->assertEquals(100, $progress);
}

// Test Case 5: Milestone detection
function testMilestoneDetection(): void {
    $milestones = $this->progressService->checkMilestones(
        participantId: 1,
        previousProgress: 45,
        newProgress: 55
    );
    
    $this->assertContains('HALFWAY', $milestones);
}
```

### Acceptance Criteria:
- [ ] All calculation edge cases tested
- [ ] Milestone triggers verified
- [ ] Concurrent access handled
