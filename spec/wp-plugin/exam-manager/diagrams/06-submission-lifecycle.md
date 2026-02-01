# 06 - Submission Lifecycle Diagram

## Overview

Complete flow diagram showing participant submissions from initial input through validation, admin review, and final resolution.

---

## 6.1 Submission Lifecycle State Machine

```mermaid
stateDiagram-v2
    [*] --> NotStarted: Checklist item assigned

    NotStarted --> Draft: User starts typing
    Draft --> Draft: Auto-save (30s)
    Draft --> Validating: User clicks Submit

    Validating --> Submitted: Validation passes
    Validating --> Flagged: Validation fails (FLAG_FOR_REVIEW mode)
    Validating --> ValidationError: Validation fails (ALLOW_RESUBMIT mode)
    Validating --> Submitted: AUTO_ACCEPT mode (skip validation)

    ValidationError --> Draft: User corrects input
    
    Submitted --> Completed: No review required
    Flagged --> PendingReview: Added to admin queue

    PendingReview --> Approved: Admin approves
    PendingReview --> Rejected: Admin rejects
    PendingReview --> NeedsResubmit: Admin requests changes

    Approved --> Completed: ✓ Final state
    Rejected --> Completed: ✗ Final state (blocked)
    NeedsResubmit --> Draft: User notified, can edit

    Completed --> [*]

    note right of Draft
        Stored in localStorage
        24-hour expiry
    end note

    note right of Flagged
        Participant can proceed
        Item marked for review
    end note

    note right of PendingReview
        Visible in Admin Queue
        Priority by age
    end note
```

---

## 6.2 Complete Submission Flow

```mermaid
flowchart TB
    subgraph Participant["👤 Participant Actions"]
        A[View Checklist Item] --> B{Submission Type?}
        
        B -->|CHECKBOX| C1[Toggle Checkbox]
        B -->|TEXT_SHORT| C2[Enter Short Text]
        B -->|TEXT_LONG| C3[Enter Long Text]
        B -->|URL| C4[Enter URL]
        B -->|VIDEO_LINK| C5[Enter Video URL]
        B -->|FILE_UPLOAD| C6[Upload File]
        B -->|SELECT| C7[Choose Option]
        B -->|RADIO| C8[Select One]
        B -->|MULTISELECT| C9[Select Multiple]
        
        C1 & C2 & C3 & C4 & C5 & C6 & C7 & C8 & C9 --> D[Click Submit]
    end

    subgraph Validation["⚙️ Validation Layer"]
        D --> E{Client-Side Valid?}
        E -->|No| F[Show Error Message]
        F --> G[User Corrects Input]
        G --> D
        
        E -->|Yes| H[Send to Server]
        H --> I{Server Validation}
        
        I -->|Pass| J[Save Submission]
        I -->|Fail| K{Validation Mode?}
        
        K -->|AUTO_ACCEPT| J
        K -->|ALLOW_RESUBMIT| L[Return Error]
        K -->|FLAG_FOR_REVIEW| M[Save + Flag]
        
        L --> F
    end

    subgraph Storage["💾 Data Layer"]
        J --> N[participantChecklist Record]
        M --> O[participantChecklist + reviewStatus=PENDING]
        
        N --> P{Review Required?}
        P -->|No| Q[Status: COMPLETED]
        P -->|Yes| R[Status: PENDING]
        
        O --> R
    end

    subgraph AdminReview["👑 Admin Review Queue"]
        R --> S[Appears in Queue]
        S --> T{Admin Action}
        
        T -->|Approve| U[reviewStatus: APPROVED]
        T -->|Reject| V[reviewStatus: REJECTED]
        T -->|Request Resubmit| W[reviewStatus: NEEDS_RESUBMIT]
        
        U --> X[Send Approval Email]
        V --> Y[Send Rejection Email]
        W --> Z[Send Resubmit Email]
        
        X --> Q
        Y --> AA[Status: BLOCKED]
        Z --> AB[Unlock for Editing]
        AB --> A
    end

    subgraph Final["✅ Final States"]
        Q --> AC[Progress Updated]
        AA --> AD[Item Marked Failed]
        AC --> AE[Certificate Eligible]
    end

    style Participant fill:#e1f5fe
    style Validation fill:#fff3e0
    style Storage fill:#f3e5f5
    style AdminReview fill:#e8f5e9
    style Final fill:#fce4ec
```

---

## 6.3 Validation Mode Decision Tree

```mermaid
flowchart TD
    A[Submission Received] --> B{Has Validation Config?}
    
    B -->|No| C[Accept Immediately]
    B -->|Yes| D[Run Validation Rules]
    
    D --> E{Validation Result}
    
    E -->|Pass| C
    E -->|Fail| F{Validation Mode Setting}
    
    F -->|AUTO_ACCEPT| G[Accept Anyway]
    F -->|FLAG_FOR_REVIEW| H[Accept but Flag]
    F -->|ALLOW_RESUBMIT| I[Reject with Error]
    
    G --> J[No Review Needed]
    H --> K[Add to Review Queue]
    I --> L[Return to Participant]
    
    K --> M{Admin Decision}
    M -->|Approve| N[Mark Completed]
    M -->|Reject| O[Mark Failed]
    M -->|Resubmit| P[Return for Edit]
    
    L --> Q[Show Error UI]
    Q --> R[Participant Edits]
    R --> A
    
    P --> R

    style C fill:#c8e6c9
    style G fill:#c8e6c9
    style N fill:#c8e6c9
    style O fill:#ffcdd2
    style I fill:#fff9c4
    style H fill:#fff9c4
```

---

## 6.4 Review Queue Priority Flow

```mermaid
flowchart LR
    subgraph Queue["Review Queue"]
        direction TB
        A[New Submission] --> B{Age?}
        
        B -->|< 24h| C[Normal Priority]
        B -->|24-48h| D[High Priority 🟡]
        B -->|48h-7d| E[Urgent Priority 🟠]
        B -->|> 7d| F[Critical Priority 🔴]
        
        C & D & E & F --> G[Sorted Queue]
    end

    subgraph Actions["Admin Actions"]
        G --> H{Select Submission}
        H --> I[Open Detail View]
        I --> J{Review}
        
        J -->|Approve| K[✓ Approved]
        J -->|Reject| L[✗ Rejected]
        J -->|Resubmit| M[⟳ Needs Resubmit]
        J -->|Skip| N[→ Next Item]
    end

    subgraph Bulk["Bulk Actions"]
        O[Select Multiple] --> P{Action}
        P -->|Bulk Approve| Q[All Approved]
        P -->|Bulk Reject| R[All Rejected + Note]
        P -->|Bulk Resubmit| S[All Need Resubmit + Feedback]
    end

    style C fill:#e8f5e9
    style D fill:#fff9c4
    style E fill:#ffe0b2
    style F fill:#ffcdd2
```

---

## 6.5 File Upload Submission Flow

```mermaid
sequenceDiagram
    participant P as Participant
    participant UI as Frontend
    participant API as Backend API
    participant S as Storage (Blob)
    participant DB as Database

    P->>UI: Drag & drop file
    UI->>UI: Validate file type & size
    
    alt Invalid file
        UI-->>P: Show error (type/size)
    else Valid file
        UI->>API: POST /api/uploads/submission-file
        API->>API: Validate server-side
        API->>S: Upload to blob storage
        S-->>API: Return file URL
        API->>DB: Create upload record
        API-->>UI: Return fileId + URL
        UI->>UI: Show upload complete ✓
        
        P->>UI: Click Submit
        UI->>API: POST /api/submissions/{id}
        Note over API: Request includes fileIds[]
        API->>DB: Create participantChecklist
        API->>DB: Link uploaded files
        API-->>UI: Submission confirmed
        UI-->>P: Show success state
    end
```

---

## 6.6 Resubmission Cycle

```mermaid
sequenceDiagram
    participant P as Participant
    participant E as Email
    participant UI as Frontend
    participant A as Admin
    participant DB as Database

    Note over A,DB: Initial submission flagged for review
    
    A->>DB: Set reviewStatus = NEEDS_RESUBMIT
    A->>DB: Add reviewNote with feedback
    DB->>E: Queue resubmit email
    E->>P: "Please update your submission"
    
    P->>UI: Click link in email
    UI->>DB: Load submission + feedback
    UI-->>P: Show previous value + admin note
    
    P->>UI: Enter new value
    UI->>DB: Update submissionValue
    UI->>DB: Set reviewStatus = PENDING
    UI->>DB: Reset reviewedAt, reviewedBy
    
    Note over A: Submission reappears in queue
    
    A->>DB: Review updated submission
    
    alt Approved
        A->>DB: Set reviewStatus = APPROVED
        DB->>E: Queue approval email
        E->>P: "Your submission was approved!"
    else Still needs work
        A->>DB: Set reviewStatus = NEEDS_RESUBMIT
        Note over P,A: Cycle repeats
    end
```

---

## 6.7 Submission Type Validation Matrix

```mermaid
flowchart TB
    subgraph Types["Submission Types"]
        T1[CHECKBOX]
        T2[TEXT_SHORT]
        T3[TEXT_LONG]
        T4[URL]
        T5[VIDEO_LINK]
        T6[FILE_UPLOAD]
        T7[SELECT]
        T8[RADIO]
        T9[MULTISELECT]
    end

    subgraph Validations["Validation Rules"]
        V1[No validation]
        V2[maxLength: 255]
        V3[minLength, maxLength, requiredWords]
        V4[URL format, allowedDomains, regex]
        V5[Platform whitelist, oEmbed check]
        V6[File type, size, count limits]
        V7[Option exists, isCorrect check]
        V8[Single selection, option valid]
        V9[min/max selections, options valid]
    end

    T1 --> V1
    T2 --> V2
    T3 --> V3
    T4 --> V4
    T5 --> V5
    T6 --> V6
    T7 --> V7
    T8 --> V8
    T9 --> V9

    subgraph Outcomes["Possible Outcomes"]
        O1[✓ Pass]
        O2[⚠ Flag for Review]
        O3[✗ Validation Error]
    end

    V1 --> O1
    V2 & V3 & V4 & V5 & V6 & V7 & V8 & V9 --> O1
    V2 & V3 & V4 & V5 & V6 & V7 & V8 & V9 --> O2
    V2 & V3 & V4 & V5 & V6 & V7 & V8 & V9 --> O3

    style O1 fill:#c8e6c9
    style O2 fill:#fff9c4
    style O3 fill:#ffcdd2
```

---

## 6.8 Status Transitions Table

| From Status | To Status | Trigger | Actor |
|-------------|-----------|---------|-------|
| NOT_STARTED | DRAFT | User starts input | Participant |
| DRAFT | VALIDATING | Submit clicked | Participant |
| VALIDATING | SUBMITTED | Validation passes | System |
| VALIDATING | FLAGGED | Validation fails (FLAG mode) | System |
| VALIDATING | VALIDATION_ERROR | Validation fails (RESUBMIT mode) | System |
| VALIDATION_ERROR | DRAFT | User corrects | Participant |
| FLAGGED | PENDING_REVIEW | Added to queue | System |
| SUBMITTED | COMPLETED | No review needed | System |
| PENDING_REVIEW | APPROVED | Admin approves | Admin |
| PENDING_REVIEW | REJECTED | Admin rejects | Admin |
| PENDING_REVIEW | NEEDS_RESUBMIT | Admin requests changes | Admin |
| NEEDS_RESUBMIT | DRAFT | User reopens | Participant |
| APPROVED | COMPLETED | Final state | System |
| REJECTED | BLOCKED | Final state | System |

---

## 6.9 Database State Changes

```mermaid
erDiagram
    participantChecklist {
        int id PK
        int participantId FK
        int checklistId FK
        boolean isCompleted
        datetime completedAt
        text submissionValue
        varchar submissionFilePath
        datetime submittedAt
        varchar reviewStatus
        datetime reviewedAt
        int reviewedBy
        text reviewNote
    }

    examChecklist {
        int id PK
        int examId FK
        varchar phase
        varchar submissionType
        varchar validationMode
        text validationConfig
    }

    participant {
        int id PK
        int examId FK
        varchar email
        int progressPercent
    }

    examChecklist ||--o{ participantChecklist : "has submissions"
    participant ||--o{ participantChecklist : "submits"
```

---

## 6.10 Notification Triggers

```mermaid
flowchart LR
    subgraph Events["Submission Events"]
        E1[Submitted]
        E2[Approved]
        E3[Rejected]
        E4[Needs Resubmit]
        E5[Queue > 50 items]
        E6[Item > 7 days old]
    end

    subgraph Recipients["Notification Recipients"]
        R1[Participant Email]
        R2[Admin Dashboard]
        R3[Admin Email]
    end

    E1 -->|If review required| R2
    E2 --> R1
    E3 --> R1
    E4 --> R1
    E5 --> R2
    E5 --> R3
    E6 --> R2
    E6 --> R3

    style E2 fill:#c8e6c9
    style E3 fill:#ffcdd2
    style E4 fill:#fff9c4
    style E5 fill:#ffe0b2
    style E6 fill:#ffcdd2
```

---

## Related Specifications

| Topic | Spec |
|-------|------|
| Submission Types & Validation | [19-exam-checklists-tab](../01-admin-backend/split-spec/19-exam-checklists-tab.md) |
| Participant Submission UI | [29-participant-submission-ui](../02-frontend/split-spec/29-participant-submission-ui.md) |
| Admin Review Queue | [52-admin-review-queue](../01-admin-backend/split-spec/52-admin-review-queue.md) |
| Submission Enums | [06-enums-constants](../01-admin-backend/split-spec/06-enums-constants.md) |
| Email Templates | [33-email-templates](../01-admin-backend/split-spec/33-email-templates.md) |
| Database Schema | [04-database-schema](../01-admin-backend/split-spec/04-database-schema.md) |
