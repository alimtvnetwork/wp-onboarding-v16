# Idea Promotion Workflow Diagram

**Version:** 1.0.0  
**Updated:** 2026-01-28  

---

## Overview

This diagram illustrates the complete lifecycle of an idea from initial voice/text input through promotion to a refined instruction, including RAG indexing at each stage.

---

## Complete Workflow Diagram

```mermaid
flowchart TD
    subgraph Input["📥 Input Layer"]
        VI[🎤 Voice Input]
        TI[⌨️ Text Input]
        VI --> TR[Transcribe via Voice Model]
        TR --> PR[Proofread & Clean]
        TI --> PR
        PR --> CT{Classify Content Type}
    end

    subgraph IdeaCreation["💡 Idea Creation"]
        CT -->|idea| GS[Generate Slug]
        CT -->|feature/task| GS
        GS --> NP[Get Next Prefix Number]
        NP --> FN["Create Filename<br/>01-idea-{slug}.md"]
        FN --> WF["Write to ideas/ folder"]
        WF --> DB1[(Create Artifact Record)]
    end

    subgraph RAGIndex1["🔍 Initial RAG Indexing"]
        DB1 --> SP1[Split into Chunks]
        SP1 --> SID[Generate Stable IDs]
        SID --> EMB1[Generate Embeddings]
        EMB1 --> IDX1[(Store in SQLite)]
        IDX1 --> AV1[✅ Idea Available for Retrieval]
    end

    subgraph Refinement["✨ Refinement Phase"]
        AV1 --> RV[User Reviews Idea]
        RV --> ED{Edit Needed?}
        ED -->|Yes| UPD[Update Idea Content]
        UPD --> REHASH[Recalculate Hash]
        REHASH --> REINX[Re-index Changed Chunks]
        REINX --> RV
        ED -->|No| RDY{Ready for Promotion?}
        RDY -->|No| RV
        RDY -->|Yes| PROM[Initiate Promotion]
    end

    subgraph Promotion["🚀 Promotion Process"]
        PROM --> PE1[(Create PromotionEvent<br/>status: pending)]
        PE1 --> RC[Read Idea Content]
        RC --> ENH[Enhance via Reasoning Model]
        ENH --> RAG[Inject RAG Context<br/>top-K related artifacts]
        RAG --> GEN[Generate Structured Instruction]
        GEN --> VAL{Validation Passed?}
        VAL -->|No| ERR[Mark PromotionEvent Failed]
        VAL -->|Yes| INP[Get Instruction Prefix Number]
    end

    subgraph InstructionCreation["📋 Instruction Creation"]
        INP --> IFN["Create Filename<br/>01-instruction-{slug}.md"]
        IFN --> WIF["Write to instructions/ folder"]
        WIF --> DB2[(Create Instruction Entity)]
        DB2 --> LINK[Link PromotionEvent<br/>targetInstructionId]
        LINK --> UPS[Update Source Artifact<br/>promotedToId]
    end

    subgraph RAGIndex2["🔄 Post-Promotion Reindexing"]
        UPS --> TRI[Trigger Reindex]
        TRI --> SP2[Split Instruction into Chunks]
        SP2 --> EMB2[Generate Embeddings]
        EMB2 --> IDX2[(Store in SQLite)]
        IDX2 --> PE2[(Update PromotionEvent<br/>reindexCompletedAt)]
        PE2 --> PE3[(Update PromotionEvent<br/>status: completed)]
    end

    subgraph Available["✅ Ready for Use"]
        PE3 --> AV2[Instruction Available for Retrieval]
        AV2 --> USE1[🤖 AI Context Injection]
        AV2 --> USE2[📂 File Browser Display]
        AV2 --> USE3[🔎 Search Results]
    end

    style Input fill:#e1f5fe
    style IdeaCreation fill:#fff3e0
    style RAGIndex1 fill:#f3e5f5
    style Refinement fill:#e8f5e9
    style Promotion fill:#fff8e1
    style InstructionCreation fill:#fce4ec
    style RAGIndex2 fill:#f3e5f5
    style Available fill:#e8f5e9
```

---

## Sequence Diagram: Promotion API Flow

```mermaid
sequenceDiagram
    autonumber
    participant U as User
    participant UI as Frontend
    participant API as Backend API
    participant PS as PromotionService
    participant FS as FileService
    participant RAG as RAGService
    participant DB as SQLite

    U->>UI: Click "Promote to Instruction"
    UI->>API: POST /projects/{id}/ideas/{ideaId}/promote
    API->>DB: Create PromotionEvent (pending)
    API->>PS: PromoteIdea(ideaId, userId)
    
    PS->>DB: Get Artifact by ideaId
    PS->>FS: ReadFile(artifact.relativePath)
    FS-->>PS: ideaContent
    
    PS->>RAG: RetrieveContext(projectId, ideaContent)
    RAG->>DB: Query top-K related chunks
    DB-->>RAG: relatedChunks[]
    RAG-->>PS: ragContext
    
    PS->>PS: EnhanceWithReasoningModel(ideaContent, ragContext)
    PS->>PS: GenerateStructuredInstruction()
    PS->>PS: ValidateInstruction()
    
    PS->>FS: GetNextPrefix("instructions/")
    FS-->>PS: "02"
    PS->>PS: GenerateSlug(instruction.title)
    PS->>FS: WriteFile("instructions/02-instruction-{slug}.md")
    
    PS->>DB: Create Instruction entity
    PS->>DB: Create new Artifact (type: instruction)
    PS->>DB: Update source Artifact.promotedToId
    PS->>DB: Update PromotionEvent.targetInstructionId
    
    PS->>RAG: TriggerReindex(newArtifactId)
    RAG->>RAG: SplitIntoChunks()
    RAG->>RAG: GenerateEmbeddings()
    RAG->>DB: Store Chunks + Embeddings
    RAG-->>PS: reindexComplete
    
    PS->>DB: Update PromotionEvent (completed, reindexCompletedAt)
    PS-->>API: PromotionResult
    API-->>UI: 200 OK { instruction, promotionEvent }
    UI-->>U: Show success, navigate to instruction
```

---

## State Diagram: Artifact Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Draft: Voice/Text Input

    state Draft {
        [*] --> Transcribed
        Transcribed --> Proofread
        Proofread --> Saved
    }

    Draft --> Indexed: RAG Indexer runs
    
    state Indexed {
        [*] --> Chunked
        Chunked --> Embedded
        Embedded --> Available
    }

    Indexed --> Editing: User edits
    Editing --> Indexed: Save & Reindex

    Indexed --> Promoting: User promotes

    state Promoting {
        [*] --> Pending
        Pending --> Processing
        Processing --> Validating
        Validating --> Failed: Validation error
        Validating --> Creating: Passed
        Creating --> Reindexing
        Reindexing --> Completed
        Failed --> [*]
    }

    Promoting --> Promoted: Success
    
    state Promoted {
        [*] --> InstructionCreated
        InstructionCreated --> LinkedToSource
        LinkedToSource --> AvailableForUse
    }

    Promoted --> [*]

    note right of Draft
        Stored in ideas/ folder
        Type: idea
    end note

    note right of Promoted
        Stored in instructions/ folder
        Type: instruction
    end note
```

---

## Data Flow: PromotionEvent Lifecycle

```mermaid
flowchart LR
    subgraph PromotionEvent
        direction TB
        P1["status: pending<br/>sourceArtifactId: ✓<br/>targetArtifactId: null<br/>reindexTriggered: false"]
        P2["status: pending<br/>targetArtifactId: ✓<br/>targetInstructionId: ✓<br/>reindexTriggered: true"]
        P3["status: completed<br/>reindexCompletedAt: ✓<br/>promotedAt: ✓"]
        PF["status: failed<br/>errorMessage: ✓"]
        
        P1 -->|"Instruction created"| P2
        P2 -->|"Reindex complete"| P3
        P1 -->|"Error during promotion"| PF
        P2 -->|"Reindex failed"| PF
    end
```

---

## Cross-References

- [Instruction System](../05-features/06-ai-integration/03-instruction-system.md) - Promotion service implementation
- [RAG System](../05-features/09-knowledge-memory/01-rag-system.md) - Indexing and retrieval
- [Database Schema](../07-database-design/01-schema.md) - PromotionEvent and Artifact tables
- [File Operations](../05-features/02-file-management/01-file-operations.md) - ideas/instructions folder structure
- [Path Manager](../05-features/02-file-management/02-path-manager.md) - Path handling
