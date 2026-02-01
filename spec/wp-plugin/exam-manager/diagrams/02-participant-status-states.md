# Participant Status State Machine

## Overview
Visual representation of the 9 participant statuses and their allowed transitions.

---

## Complete State Diagram

```mermaid
stateDiagram-v2
    %% ═══════════════════════════════════════════════════════════════
    %% STATE DEFINITIONS
    %% ═══════════════════════════════════════════════════════════════
    
    [*] --> INVITED: Signup/Import
    
    INVITED: 📧 Invited
    INVITED: Not yet started
    INVITED: Can: Start exam
    
    ACTIVE: ✅ Active
    ACTIVE: Currently participating
    ACTIVE: Can: Mark sections
    
    PAUSED: ⏸️ Paused
    PAUSED: Temporarily on hold
    PAUSED: Can: Resume
    
    SOFT_DEADLINE_REACHED: ⚠️ Soft Deadline
    SOFT_DEADLINE_REACHED: Warning state
    SOFT_DEADLINE_REACHED: Can: Still mark sections
    
    HARD_DEADLINE_REACHED: 🔶 Hard Deadline
    HARD_DEADLINE_REACHED: Pending lock
    HARD_DEADLINE_REACHED: Can: Request extension
    
    EXTENDED: 🔄 Extended
    EXTENDED: Extension granted
    EXTENDED: Can: Mark sections
    
    COMPLETED: 🎉 Completed
    COMPLETED: All requirements done
    COMPLETED: Terminal state
    
    LOCKED: 🔒 Locked
    LOCKED: Access revoked
    LOCKED: Terminal state
    
    WITHDRAWN: 🚪 Withdrawn
    WITHDRAWN: Dropped out
    WITHDRAWN: Terminal state
    
    %% ═══════════════════════════════════════════════════════════════
    %% TRANSITIONS FROM INVITED
    %% ═══════════════════════════════════════════════════════════════
    
    INVITED --> ACTIVE: User starts exam
    INVITED --> WITHDRAWN: User withdraws
    
    %% ═══════════════════════════════════════════════════════════════
    %% TRANSITIONS FROM ACTIVE
    %% ═══════════════════════════════════════════════════════════════
    
    ACTIVE --> PAUSED: Admin pauses
    ACTIVE --> SOFT_DEADLINE_REACHED: Soft deadline passes (cron)
    ACTIVE --> COMPLETED: All items completed
    ACTIVE --> LOCKED: Admin locks
    ACTIVE --> WITHDRAWN: User withdraws
    
    %% ═══════════════════════════════════════════════════════════════
    %% TRANSITIONS FROM PAUSED
    %% ═══════════════════════════════════════════════════════════════
    
    PAUSED --> ACTIVE: Admin resumes
    PAUSED --> WITHDRAWN: User withdraws
    
    %% ═══════════════════════════════════════════════════════════════
    %% TRANSITIONS FROM SOFT_DEADLINE_REACHED
    %% ═══════════════════════════════════════════════════════════════
    
    SOFT_DEADLINE_REACHED --> ACTIVE: Admin resets deadline
    SOFT_DEADLINE_REACHED --> HARD_DEADLINE_REACHED: Hard deadline passes (cron)
    SOFT_DEADLINE_REACHED --> EXTENDED: Extension approved
    SOFT_DEADLINE_REACHED --> COMPLETED: All items completed
    SOFT_DEADLINE_REACHED --> LOCKED: Admin locks
    SOFT_DEADLINE_REACHED --> WITHDRAWN: User withdraws
    
    %% ═══════════════════════════════════════════════════════════════
    %% TRANSITIONS FROM HARD_DEADLINE_REACHED
    %% ═══════════════════════════════════════════════════════════════
    
    HARD_DEADLINE_REACHED --> EXTENDED: Extension approved
    HARD_DEADLINE_REACHED --> LOCKED: Grace period expires (cron)
    
    %% ═══════════════════════════════════════════════════════════════
    %% TRANSITIONS FROM EXTENDED
    %% ═══════════════════════════════════════════════════════════════
    
    EXTENDED --> ACTIVE: Progress continues
    EXTENDED --> SOFT_DEADLINE_REACHED: Soft threshold passes
    EXTENDED --> COMPLETED: All items completed
    EXTENDED --> LOCKED: Extension expires (cron)
    EXTENDED --> WITHDRAWN: User withdraws
    
    %% ═══════════════════════════════════════════════════════════════
    %% TERMINAL STATES (no outgoing transitions)
    %% ═══════════════════════════════════════════════════════════════
    
    COMPLETED --> [*]
    LOCKED --> [*]
    WITHDRAWN --> [*]
```

---

## Transition Matrix

| From → To | INVITED | ACTIVE | PAUSED | SOFT_DL | HARD_DL | EXTENDED | COMPLETED | LOCKED | WITHDRAWN |
|-----------|---------|--------|--------|---------|---------|----------|-----------|--------|-----------|
| **INVITED** | - | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **ACTIVE** | ❌ | - | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ |
| **PAUSED** | ❌ | ✅ | - | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **SOFT_DL** | ❌ | ✅ | ❌ | - | ✅ | ✅ | ✅ | ✅ | ✅ |
| **HARD_DL** | ❌ | ❌ | ❌ | ❌ | - | ✅ | ❌ | ✅ | ❌ |
| **EXTENDED** | ❌ | ✅ | ❌ | ✅ | ❌ | - | ✅ | ✅ | ✅ |
| **COMPLETED** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | - | ❌ | ❌ |
| **LOCKED** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | - | ❌ |
| **WITHDRAWN** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | - |

---

## Transition Triggers

### Automatic (Cron Job)

```mermaid
flowchart LR
    subgraph Cron["⏰ Deadline Checker Cron (Hourly)"]
        A[ACTIVE] -->|soft deadline passes| B[SOFT_DEADLINE_REACHED]
        B -->|hard deadline passes| C[HARD_DEADLINE_REACHED]
        C -->|grace period expires| D[LOCKED]
        E[EXTENDED] -->|extension expires| D
    end
```

### Manual (User Action)

```mermaid
flowchart LR
    subgraph User["👤 User Actions"]
        A[INVITED] -->|starts exam| B[ACTIVE]
        B -->|completes all| C[COMPLETED]
        D[Any non-terminal] -->|withdraws| E[WITHDRAWN]
    end
```

### Manual (Admin Action)

```mermaid
flowchart LR
    subgraph Admin["👑 Admin Actions"]
        A[ACTIVE] -->|pauses| B[PAUSED]
        B -->|resumes| A
        C[SOFT_DEADLINE_REACHED] -->|approves extension| D[EXTENDED]
        E[HARD_DEADLINE_REACHED] -->|approves extension| D
        F[Any non-terminal] -->|locks| G[LOCKED]
    end
```

---

## Can Mark Sections?

```mermaid
flowchart TB
    subgraph CanMark["✅ Can Mark Sections"]
        ACTIVE
        SOFT_DEADLINE_REACHED
        EXTENDED
    end
    
    subgraph CannotMark["❌ Cannot Mark Sections"]
        INVITED
        PAUSED
        HARD_DEADLINE_REACHED
        LOCKED
        COMPLETED
        WITHDRAWN
    end
```

---

## Can Request Extension?

```mermaid
flowchart TB
    subgraph CanRequest["✅ Can Request Extension"]
        SOFT_DEADLINE_REACHED
        HARD_DEADLINE_REACHED
        LOCKED
    end
    
    subgraph CannotRequest["❌ Cannot Request Extension"]
        INVITED
        ACTIVE
        PAUSED
        EXTENDED
        COMPLETED
        WITHDRAWN
    end
```

---

## Status Badge Colors

| Status | Color | CSS Class | Hex |
|--------|-------|-----------|-----|
| INVITED | Gray | `badge-secondary` | `#6b7280` |
| ACTIVE | Green | `badge-success` | `#22c55e` |
| PAUSED | Gray | `badge-secondary` | `#6b7280` |
| SOFT_DEADLINE_REACHED | Yellow | `badge-warning` | `#eab308` |
| HARD_DEADLINE_REACHED | Orange | `badge-danger` | `#f97316` |
| EXTENDED | Blue | `badge-info` | `#3b82f6` |
| COMPLETED | Purple | `badge-primary` | `#8b5cf6` |
| LOCKED | Red | `badge-danger` | `#ef4444` |
| WITHDRAWN | Gray | `badge-muted` | `#9ca3af` |

---

## Typical Lifecycle Flows

### Happy Path (Completes on Time)

```mermaid
flowchart LR
    A[INVITED] --> B[ACTIVE] --> C[COMPLETED]
    style C fill:#22c55e
```

### Extension Path

```mermaid
flowchart LR
    A[ACTIVE] --> B[SOFT_DEADLINE_REACHED] --> C[HARD_DEADLINE_REACHED] --> D[EXTENDED] --> E[COMPLETED]
    style E fill:#22c55e
```

### Locked Path

```mermaid
flowchart LR
    A[ACTIVE] --> B[SOFT_DEADLINE_REACHED] --> C[HARD_DEADLINE_REACHED] --> D[LOCKED]
    style D fill:#ef4444
```

### Withdrawal Path

```mermaid
flowchart LR
    A[ACTIVE] --> B[WITHDRAWN]
    style B fill:#9ca3af
```
