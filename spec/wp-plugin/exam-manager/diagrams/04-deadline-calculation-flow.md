# Deadline Calculation Flow

## Overview
Flowcharts showing how deadlines are calculated, applied, and enforced throughout the participant lifecycle.

---

## Initial Deadline Calculation (at Signup)

```mermaid
flowchart TB
    subgraph Input["📥 Inputs"]
        A[Participant Signup]
        B[signupDate = now()]
        C[exam.softDeadlineDays]
        D[exam.hardDeadlineDays]
        E[exam.inheritDeadline]
        F[exam.parentId]
    end
    
    subgraph SoftCalc["🟡 Soft Deadline Calculation"]
        A --> G{exam.softDeadlineDays<br/>is set?}
        G -->|Yes| H[softDeadline =<br/>signupDate + softDeadlineDays]
        G -->|No| I{inheritDeadline<br/>= true?}
        I -->|Yes| J[Get parent exam]
        J --> K[softDeadline =<br/>signupDate + parent.softDeadlineDays]
        I -->|No| L[softDeadline = NULL]
    end
    
    subgraph HardCalc["🔴 Hard Deadline Calculation"]
        H --> M{exam.hardDeadlineDays<br/>is set?}
        K --> M
        L --> M
        M -->|Yes| N[hardDeadline =<br/>signupDate + hardDeadlineDays]
        M -->|No| O{inheritDeadline<br/>= true?}
        O -->|Yes| P[hardDeadline =<br/>signupDate + parent.hardDeadlineDays]
        O -->|No| Q[hardDeadline = NULL]
    end
    
    subgraph Validate["✅ Validation"]
        N --> R{softDeadline<br/>< hardDeadline?}
        P --> R
        Q --> R
        R -->|Yes| S[Valid ✓]
        R -->|No| T[Error: Invalid<br/>deadline configuration]
        R -->|Both NULL| U[No deadlines<br/>Valid ✓]
    end
    
    subgraph Store["💾 Store"]
        S --> V[Save to participant:<br/>softDeadlineDate<br/>hardDeadlineDate<br/>originalSoftDeadline<br/>originalHardDeadline]
        U --> W[Save to participant:<br/>all deadlines = NULL]
    end
```

---

## Effective Deadline Determination (Priority Order)

```mermaid
flowchart TB
    subgraph Priority["🎯 Priority Order (Highest First)"]
        A[Get Effective Deadline]
        
        A --> B{deadlineOverride<br/>is set?}
        B -->|Yes| C[Return OVERRIDE<br/>deadlineOverride]
        
        B -->|No| D{extensionDeadlineDate<br/>is set AND > now?}
        D -->|Yes| E[Return EXTENSION<br/>extensionDeadlineDate]
        
        D -->|No| F{hardDeadlineDate<br/>is set?}
        F -->|Yes| G[Return HARD<br/>hardDeadlineDate]
        
        F -->|No| H[Return NONE<br/>No deadline]
    end
    
    subgraph Result["📤 Result"]
        C --> I[type: OVERRIDE<br/>deadline: DateTime<br/>source: Admin Override]
        E --> J[type: EXTENSION<br/>deadline: DateTime<br/>source: Extension Granted]
        G --> K[type: HARD<br/>deadline: DateTime<br/>source: Exam Default]
        H --> L[type: NONE<br/>deadline: NULL<br/>source: No Deadline]
    end
```

---

## Extension Calculation Flow

```mermaid
flowchart TB
    subgraph Request["📝 Extension Request"]
        A[Participant requests<br/>extension]
        A --> B[Admin reviews]
        B --> C{Approved?}
        C -->|No| D[Status: REJECTED<br/>No changes]
    end
    
    subgraph Calculation["🔢 Extension Calculation"]
        C -->|Yes| E[approvedDays = N]
        
        E --> F{First extension?}
        
        F -->|Yes| G[baseDeadline =<br/>originalHardDeadline]
        F -->|No| H[baseDeadline =<br/>current extensionDeadlineDate]
        
        G --> I[extensionDeadlineDate =<br/>baseDeadline + approvedDays × 24h]
        H --> I
    end
    
    subgraph Update["💾 Update Participant"]
        I --> J[Set extensionDeadlineDate]
        J --> K[Set status = EXTENDED]
        K --> L[Log audit event]
        L --> M[Send notification email]
    end
    
    subgraph Example["📋 Example"]
        N["Original hard deadline:<br/>Jan 31, 1:00 PM"]
        O["Approved days: 3"]
        P["Extension deadline:<br/>Feb 3, 1:00 PM"]
        N --> O --> P
    end
```

---

## Deadline Enforcement (Cron Job)

```mermaid
flowchart TB
    subgraph Cron["⏰ Hourly Cron Job"]
        A[Start Deadline Checker]
        A --> B[now = currentTime]
    end
    
    subgraph Phase1["Phase 1: Soft Approaching"]
        B --> C[Query: status = ACTIVE<br/>softDeadline between now and now+24h<br/>softDeadlineNotifiedAt = NULL]
        C --> D{Results?}
        D -->|Yes| E[Send SOFT_DEADLINE_APPROACHING email<br/>Set softDeadlineNotifiedAt = now]
        D -->|No| F[Continue]
    end
    
    subgraph Phase2["Phase 2: Soft Passed"]
        E --> G[Query: status = ACTIVE<br/>softDeadline < now]
        F --> G
        G --> H{Results?}
        H -->|Yes| I[Set status = SOFT_DEADLINE_REACHED<br/>Send SOFT_DEADLINE_PASSED email<br/>Log audit event]
        H -->|No| J[Continue]
    end
    
    subgraph Phase3["Phase 3: Hard Passed"]
        I --> K[Query: status IN ACTIVE, SOFT_DEADLINE_REACHED<br/>hardDeadline < now]
        J --> K
        K --> L{Results?}
        L -->|Yes| M[Set status = LOCKED<br/>Send EXAM_LOCKED email<br/>Log audit event]
        L -->|No| N[Continue]
    end
    
    subgraph Phase4["Phase 4: Extension Expired"]
        M --> O[Query: status = EXTENDED<br/>extensionDeadline < now]
        N --> O
        O --> P{Results?}
        P -->|Yes| Q[Set status = LOCKED<br/>Send EXTENSION_EXPIRED email<br/>Log audit event]
        P -->|No| R[Done]
    end
    
    subgraph Summary["📊 Log Summary"]
        Q --> S[Log: processed, errors, duration]
        R --> S
    end
```

---

## Deadline Color Scheme

```mermaid
flowchart LR
    subgraph Soft["🟡 Soft Deadline Colors"]
        S1["> 7 days"] --> S1C["🟢 Green<br/>#22c55e"]
        S2["3-7 days"] --> S2C["🟡 Yellow<br/>#eab308"]
        S3["1-3 days"] --> S3C["🟠 Orange<br/>#f97316"]
        S4["< 24 hours"] --> S4C["🔴 Light Red<br/>#ef4444"]
    end
    
    subgraph Hard["🔴 Hard Deadline Colors"]
        H1["> 7 days"] --> H1C["🟢 Green<br/>#22c55e"]
        H2["3-7 days"] --> H2C["🟡 Yellow<br/>#eab308"]
        H3["1-3 days"] --> H3C["🟠 Orange<br/>#f97316"]
        H4["< 24 hours"] --> H4C["🔴 Dark Red<br/>#dc2626"]
        H5["Overdue"] --> H5C["⬛ Black<br/>#1f2937"]
    end
```

---

## Complete Timeline Example

```mermaid
gantt
    title Participant Deadline Timeline
    dateFormat  YYYY-MM-DD
    
    section Exam Period
    Signup                      :milestone, m1, 2026-01-24, 0d
    Active Period               :active, 2026-01-24, 7d
    
    section Deadlines
    Soft Deadline (Day 3)       :milestone, m2, 2026-01-27, 0d
    Hard Deadline (Day 7)       :milestone, m3, 2026-01-31, 0d
    
    section Status Changes
    ACTIVE                      :done, s1, 2026-01-24, 3d
    SOFT_DEADLINE_REACHED       :crit, s2, 2026-01-27, 4d
    LOCKED                      :crit, s3, 2026-01-31, 1d
    
    section Extension (if approved)
    Extension Request           :milestone, m4, 2026-01-31, 0d
    EXTENDED Period             :active, e1, 2026-02-01, 2d
    Extension Deadline          :milestone, m5, 2026-02-02, 0d
```

---

## Countdown Display Format

```mermaid
flowchart TB
    subgraph Input["⏱️ Time Remaining"]
        A[remaining = deadline - now]
    end
    
    subgraph Format["📝 Format Selection"]
        A --> B{remaining<br/>≤ 0?}
        B -->|Yes| C["Overdue"]
        
        B -->|No| D{remaining<br/>< 1 hour?}
        D -->|Yes| E["Xm Ys remaining"]
        
        D -->|No| F{remaining<br/>< 24 hours?}
        F -->|Yes| G["Xh Ym remaining"]
        
        F -->|No| H["Xd Yh remaining"]
    end
    
    subgraph Examples["📋 Examples"]
        I["45 seconds → '0m 45s remaining'"]
        J["2h 30m → '2h 30m remaining'"]
        K["3d 5h → '3d 5h remaining'"]
        L["Past deadline → 'Overdue'"]
    end
```

---

## Database Fields Summary

```mermaid
classDiagram
    class Participant {
        +DateTime softDeadlineDate
        +DateTime hardDeadlineDate
        +DateTime extensionDeadlineDate
        +DateTime originalSoftDeadline
        +DateTime originalHardDeadline
        +DateTime deadlineOverride
        +String deadlineOverrideReason
        +Int extensionDays
        +DateTime softDeadlineNotifiedAt
        +DateTime hardDeadlineNotifiedAt
    }
    
    class DeadlineCalculation {
        +calculateInitial(signupDate, exam)
        +applyExtension(participant, approvedDays)
        +getEffectiveDeadline(participant)
        +formatCountdown(deadline)
    }
    
    class DeadlineChecker {
        +checkSoftApproaching()
        +checkSoftPassed()
        +checkHardPassed()
        +checkExtensionExpired()
    }
    
    Participant --> DeadlineCalculation : uses
    DeadlineChecker --> Participant : updates
```
