# Automation Pipeline System - Architecture Diagram

**Version:** 1.0.0  
**Status:** Reference  
**Updated:** 2026-01-30  

---

## Complete System Architecture

<presentation-mermaid>
graph TB
    subgraph Phase1["Phase 1: Foundation"]
        DB[(Database Schema)]
        PI[Prompt Import System]
        VR[Variable Registry]
    end

    subgraph Phase2["Phase 2: Stage Engine"]
        SE[Stage Executor]
        VRT[Validation Runtime]
        IOB[I/O Binding]
    end

    subgraph Phase3["Phase 3: Block Orchestration"]
        EB[Execution Blocks]
        PSC[Parallel/Sequential Control]
        BC[Block Chaining]
    end

    subgraph Phase4["Phase 4: Node Canvas UI"]
        RFC[React Flow Canvas]
        SNC[Stage Node Components]
        CW[Connection Wiring]
    end

    subgraph Phase5["Phase 5: Control Flow"]
        CN[Conditional Nodes]
        LC[Loop Constructs]
        EH[Error Handlers]
    end

    subgraph Phase6["Phase 6: Observability"]
        LEV[Live Execution View]
        DI[Debug Inspector]
        TI[Telemetry Integration]
    end

    subgraph Phase7["Phase 7: Templates & Portability"]
        PT[Pipeline Templates]
        IE[Import/Export]
        VC[Version Control]
    end

    subgraph Phase8["Phase 8: Governance"]
        PERM[Permissions]
        SH[Sharing]
        COL[Collaboration]
    end

    %% Phase 1 Internal
    PI --> DB
    VR --> DB

    %% Phase 2 Internal
    SE --> VRT
    SE --> IOB
    VRT --> IOB

    %% Phase 3 Internal
    EB --> PSC
    PSC --> BC

    %% Phase 4 Internal
    RFC --> SNC
    RFC --> CW
    SNC --> CW

    %% Phase 5 Internal
    CN --> LC
    LC --> EH
    CN --> EH

    %% Phase 6 Internal
    LEV --> DI
    DI --> TI
    LEV --> TI

    %% Phase 7 Internal
    PT --> IE
    IE --> VC

    %% Phase 8 Internal
    PERM --> SH
    SH --> COL
    PERM --> COL

    %% Cross-Phase Connections
    DB --> SE
    VR --> SE
    VR --> IOB
    PI --> PT

    SE --> EB
    IOB --> BC
    VRT --> EH

    EB --> RFC
    BC --> CW
    PSC --> LEV

    CN --> RFC
    LC --> RFC
    EH --> RFC

    LEV --> RFC
    DI --> RFC

    PT --> RFC
    VC --> DB
    IE --> DB

    PERM --> RFC
    COL --> RFC
    COL --> LEV
</presentation-mermaid>

---

## Data Flow Architecture

<presentation-mermaid>
flowchart LR
    subgraph Input["Input Layer"]
        ZIP[ZIP Import]
        UI[Canvas UI]
        API[REST API]
        WS[WebSocket]
    end

    subgraph Processing["Processing Layer"]
        PE[Pipeline Engine]
        SE[Stage Executor]
        VR[Variable Resolver]
        OT[OT Engine]
    end

    subgraph Execution["Execution Layer"]
        GO[Golang Runtime]
        PY[Python Runtime]
        TS[TypeScript/Bun]
        HTTP[HTTP Client]
    end

    subgraph Storage["Storage Layer"]
        PDB[(project.db)]
        FS[File System]
        CACHE[Memory Cache]
    end

    subgraph Output["Output Layer"]
        STREAM[Event Stream]
        NOTIFY[Notifications]
        EXPORT[Export Bundle]
    end

    ZIP --> PE
    UI --> PE
    API --> PE
    WS --> OT

    PE --> SE
    PE --> VR
    OT --> PE

    SE --> GO
    SE --> PY
    SE --> TS
    SE --> HTTP

    GO --> PDB
    PY --> PDB
    TS --> PDB
    VR --> CACHE

    PE --> STREAM
    SE --> NOTIFY
    PE --> EXPORT
    PDB --> FS
</presentation-mermaid>

---

## Component Dependency Matrix

<presentation-mermaid>
graph TD
    subgraph Core["Core Services"]
        direction TB
        DB[(SQLite Database)]
        VarReg[Variable Registry]
        StageExec[Stage Executor]
    end

    subgraph UI["Frontend Components"]
        direction TB
        Canvas[Pipeline Canvas]
        Palette[Block Palette]
        ConfigPanel[Config Panel]
        Toolbar[Canvas Toolbar]
    end

    subgraph Runtime["Execution Runtime"]
        direction TB
        BlockOrch[Block Orchestrator]
        Scheduler[Execution Scheduler]
        Workers[Worker Pool]
    end

    subgraph Validation["Validation Layer"]
        direction TB
        GoRunner[Go Runner]
        PyRunner[Python Runner]
        TSRunner[TS/Bun Runner]
    end

    subgraph Control["Control Flow"]
        direction TB
        Branching[Branch Router]
        Looping[Loop Controller]
        ErrorMgr[Error Manager]
    end

    subgraph Collab["Collaboration"]
        direction TB
        Presence[Presence Service]
        OTEngine[OT Engine]
        Sessions[Session Manager]
    end

    DB --> VarReg
    DB --> StageExec
    VarReg --> StageExec

    Canvas --> Palette
    Canvas --> ConfigPanel
    Canvas --> Toolbar

    BlockOrch --> Scheduler
    Scheduler --> Workers
    Workers --> StageExec

    StageExec --> GoRunner
    StageExec --> PyRunner
    StageExec --> TSRunner

    Branching --> BlockOrch
    Looping --> BlockOrch
    ErrorMgr --> BlockOrch

    Presence --> Sessions
    OTEngine --> Sessions
    Sessions --> Canvas
</presentation-mermaid>

---

## Execution Flow Sequence

<presentation-mermaid>
sequenceDiagram
    autonumber
    participant User
    participant Canvas as Pipeline Canvas
    participant Engine as Pipeline Engine
    participant Scheduler as Execution Scheduler
    participant Block as Block Executor
    participant Stage as Stage Handler
    participant Runtime as Validation Runtime
    participant DB as project.db
    participant WS as WebSocket

    User->>Canvas: Click "Run Pipeline"
    Canvas->>Engine: executePipeline(pipelineId)
    Engine->>DB: Load pipeline config
    DB-->>Engine: Pipeline + Blocks + Stages
    
    Engine->>Scheduler: Schedule execution
    Scheduler->>WS: Broadcast EXECUTION_STARTED
    WS-->>Canvas: Update status overlay

    loop For each Block
        Scheduler->>Block: Execute block
        Block->>WS: Broadcast BLOCK_STARTED
        
        loop For each Stage
            Block->>Stage: Execute stage
            Stage->>WS: Broadcast STAGE_STARTED
            
            alt Validation Stage
                Stage->>Runtime: Run validation script
                Runtime-->>Stage: Validation result
            else Prompt Stage
                Stage->>Engine: Resolve variables
                Engine-->>Stage: Rendered prompt
            end
            
            Stage->>DB: Save stage result
            Stage->>WS: Broadcast STAGE_COMPLETED
        end
        
        Block->>WS: Broadcast BLOCK_COMPLETED
    end

    Engine->>WS: Broadcast EXECUTION_COMPLETED
    WS-->>Canvas: Show success state
    Canvas-->>User: Display results
</presentation-mermaid>

---

## Database Entity Relationships

<presentation-mermaid>
erDiagram
    Pipeline ||--o{ ExecutionBlock : contains
    Pipeline ||--o{ PipelineVariable : defines
    Pipeline ||--o{ PipelineVersion : versions
    Pipeline ||--o{ PipelinePermission : grants
    Pipeline ||--o{ ShareLink : shares
    
    ExecutionBlock ||--o{ Stage : contains
    ExecutionBlock ||--o{ BlockConnection : connects
    
    Stage ||--o{ StageExecution : logs
    Stage }o--|| ValidationScript : uses
    
    PipelineTemplate ||--o{ TemplateParameter : has
    PipelineTemplate ||--o{ Pipeline : instantiates
    
    Team ||--o{ TeamMember : has
    Team ||--o{ PipelinePermission : grants
    
    CollaborationSession ||--o{ SessionParticipant : has
    CollaborationSession ||--o{ CollaborationOperation : records
    
    PipelineBranch ||--o{ PipelineVersion : contains
    MergeRequest }o--|| PipelineBranch : source
    MergeRequest }o--|| PipelineBranch : target

    Pipeline {
        uuid id PK
        string name
        enum execution_mode
        timestamp created_at
    }

    ExecutionBlock {
        uuid id PK
        uuid pipeline_id FK
        int order_index
        enum mode
    }

    Stage {
        uuid id PK
        uuid block_id FK
        enum type
        json config
    }

    PipelinePermission {
        uuid id PK
        uuid pipeline_id FK
        uuid user_id FK
        enum role
    }

    CollaborationSession {
        uuid id PK
        uuid pipeline_id FK
        timestamp started_at
        boolean is_active
    }
</presentation-mermaid>

---

## Real-Time Collaboration Architecture

<presentation-mermaid>
flowchart TB
    subgraph Clients["Client Sessions"]
        C1[Editor 1]
        C2[Editor 2]
        C3[Viewer]
    end

    subgraph Gateway["WebSocket Gateway"]
        WS[WebSocket Server]
        AUTH[Auth Middleware]
        RATE[Rate Limiter]
    end

    subgraph Sync["Synchronization Layer"]
        OT[OT Engine]
        PRES[Presence Manager]
        CURSOR[Cursor Tracker]
    end

    subgraph State["State Management"]
        DOC[Document State]
        HISTORY[Operation History]
        BUFFER[Pending Buffer]
    end

    subgraph Persist["Persistence"]
        DB[(project.db)]
        SNAP[Snapshots]
    end

    C1 <--> WS
    C2 <--> WS
    C3 <--> WS

    WS --> AUTH
    AUTH --> RATE

    RATE --> OT
    RATE --> PRES
    RATE --> CURSOR

    OT --> DOC
    OT --> HISTORY
    PRES --> BUFFER

    DOC --> DB
    HISTORY --> SNAP
</presentation-mermaid>

---

## Control Flow Decision Tree

<presentation-mermaid>
flowchart TD
    START([Stage Execution]) --> TYPE{Stage Type?}
    
    TYPE -->|PROMPT| PROMPT[Execute AI Prompt]
    TYPE -->|VALIDATION| VALID[Run Validation Script]
    TYPE -->|SEARCH| SEARCH[Execute Search]
    TYPE -->|HTTP| HTTP[Make HTTP Request]
    TYPE -->|TRANSFORM| TRANSFORM[Transform Data]
    TYPE -->|CODE_GEN| CODEGEN[Generate Code]
    TYPE -->|FILE_OP| FILEOP[File Operation]
    
    PROMPT --> RESULT{Success?}
    VALID --> RESULT
    SEARCH --> RESULT
    HTTP --> RESULT
    TRANSFORM --> RESULT
    CODEGEN --> RESULT
    FILEOP --> RESULT
    
    RESULT -->|Yes| COND{Conditional?}
    RESULT -->|No| ERROR{Error Handler?}
    
    COND -->|IF_ELSE| IFELSE[Evaluate Condition]
    COND -->|SWITCH| SWITCH[Match Case]
    COND -->|No| NEXT[Next Stage]
    
    IFELSE --> BRANCH[Route to Branch]
    SWITCH --> BRANCH
    
    ERROR -->|TRY_CATCH| CATCH[Execute Catch Block]
    ERROR -->|RETRY| RETRY[Retry with Backoff]
    ERROR -->|CIRCUIT_BREAKER| CIRCUIT[Check Circuit State]
    ERROR -->|COMPENSATION| COMP[Execute Rollback]
    ERROR -->|ESCALATION| ESC[Human Escalation]
    ERROR -->|None| FAIL([Execution Failed])
    
    CATCH --> NEXT
    RETRY --> TYPE
    CIRCUIT --> NEXT
    COMP --> NEXT
    ESC --> WAIT[Await Human Decision]
    WAIT --> NEXT
    
    BRANCH --> NEXT
    NEXT --> LOOP{More Stages?}
    LOOP -->|Yes| TYPE
    LOOP -->|No| END([Block Complete])
</presentation-mermaid>

---

## Phase Integration Summary

| Phase | Components | Depends On | Provides To |
|-------|------------|------------|-------------|
| **1: Foundation** | DB Schema, Prompt Import, Variable Registry | — | All phases |
| **2: Stage Engine** | Stage Executor, Validation Runtime, I/O Binding | Phase 1 | Phases 3, 5, 6 |
| **3: Block Orchestration** | Execution Blocks, Parallel Control, Block Chaining | Phases 1, 2 | Phases 4, 5, 6 |
| **4: Node Canvas** | React Flow Canvas, Stage Nodes, Connection Wiring | Phases 1, 3 | Phases 5, 6, 7, 8 |
| **5: Control Flow** | Conditionals, Loops, Error Handlers | Phases 2, 3, 4 | Phase 6 |
| **6: Observability** | Live View, Debug Inspector, Telemetry | Phases 2, 3, 4, 5 | Phase 8 |
| **7: Portability** | Templates, Import/Export, Version Control | Phases 1, 4 | Phase 8 |
| **8: Governance** | Permissions, Sharing, Collaboration | Phases 4, 6, 7 | — |

---

## Technology Stack

| Layer | Technology | Purpose |
|-------|------------|---------|
| Frontend | React + TypeScript | UI Framework |
| Canvas | React Flow | Node-based editor |
| State | Zustand | Canvas state management |
| Styling | Tailwind CSS + shadcn/ui | Design system |
| Backend | Golang | API + Orchestration |
| Database | SQLite (project.db) | Per-project storage |
| Validation | Go/Python/TS Runtimes | Script execution |
| Real-time | WebSocket | Collaboration + Live updates |
| Sync | Operational Transformation | Conflict resolution |

---

## Related Specifications

- [Overview](./00-overview.md)
- [Database Schema](./01-database-schema.md)
- [Collaboration System](./24-collaboration.md)
- [Feature Memory](/.lovable/memories/features/automation-pipeline.md)
