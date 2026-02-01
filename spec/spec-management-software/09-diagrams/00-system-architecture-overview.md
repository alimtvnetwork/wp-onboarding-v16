# System Architecture Overview

> **Version:** 1.0.0  
> **Status:** Active  
> **Last Updated:** 2026-01-28

---

## 1. Overview

This document provides a comprehensive system architecture view showing how all workflow components interconnect. It serves as a map linking to the 5 detailed workflow diagrams and illustrates the complete data flow from user input to generated specifications.

---

## 2. High-Level System Architecture

```mermaid
flowchart TB
    subgraph UserLayer["👤 USER LAYER"]
        direction LR
        VOICE[🎤 Voice Input]
        TEXT[⌨️ Text Input]
        UI[🖥️ React Frontend]
    end

    subgraph APILayer["🔌 API GATEWAY"]
        REST[REST API]
        WS[WebSocket/SSE]
    end

    subgraph CoreServices["⚙️ CORE SERVICES"]
        direction TB
        
        subgraph InstructionPipeline["📋 Instruction Pipeline"]
            TRANS[Transcription]
            PROOF[Proofreading]
            PLAN[Planning]
            EXEC[Execution]
        end
        
        subgraph PromptSystem["📝 Prompt System"]
            PRESET[Preset Manager]
            COMPOSE[Prompt Composer]
        end
        
        subgraph QualityLoop["✅ Quality Loop"]
            DETECT[Issue Detector]
            QUESTION[Question Generator]
            REGEN[Regenerator]
        end
    end

    subgraph AILayer["🧠 AI LAYER"]
        direction LR
        STT[Speech-to-Text<br/>ElevenLabs]
        LLM[Reasoning Model<br/>LLaMA/Gemini]
        EMB[Embedding Model]
    end

    subgraph DataLayer["💾 DATA LAYER"]
        direction TB
        
        subgraph Storage["Storage"]
            DB[(SQLite)]
            FS[📁 Filesystem<br/>ideas/ instructions/]
            VEC[(Vector Store)]
        end
        
        subgraph RAGSystem["🔍 RAG System"]
            INDEX[Indexer]
            RETRIEVE[Retriever]
            CHUNK[Chunker]
        end
    end

    subgraph OutputLayer["📄 OUTPUT LAYER"]
        SPEC[Generated Specs]
        HIST[History/Versions]
        GIT[Git Commits]
    end

    %% User to API
    VOICE --> UI
    TEXT --> UI
    UI --> REST
    UI --> WS

    %% API to Services
    REST --> InstructionPipeline
    REST --> PromptSystem
    REST --> QualityLoop
    WS --> InstructionPipeline

    %% Services to AI
    TRANS --> STT
    PROOF --> LLM
    PLAN --> LLM
    EXEC --> LLM
    DETECT --> LLM
    COMPOSE --> PRESET

    %% Services interconnections
    PRESET --> COMPOSE
    COMPOSE --> PLAN
    DETECT --> QUESTION
    QUESTION --> UI
    REGEN --> EXEC

    %% AI to Data
    STT --> TRANS
    LLM --> EXEC
    EMB --> INDEX

    %% Data Layer internal
    INDEX --> CHUNK
    CHUNK --> VEC
    RETRIEVE --> VEC
    RETRIEVE --> LLM

    %% Data to Output
    EXEC --> FS
    FS --> DB
    FS --> GIT
    DB --> HIST
    FS --> SPEC

    %% RAG feedback
    FS --> INDEX

    style UserLayer fill:#e3f2fd,stroke:#1976d2
    style APILayer fill:#fff3e0,stroke:#f57c00
    style CoreServices fill:#e8f5e9,stroke:#388e3c
    style AILayer fill:#fce4ec,stroke:#c2185b
    style DataLayer fill:#f3e5f5,stroke:#7b1fa2
    style OutputLayer fill:#e0f7fa,stroke:#00838f
```

---

## 3. Workflow Integration Map

```mermaid
flowchart LR
    subgraph Workflows["📊 DETAILED WORKFLOW DIAGRAMS"]
        direction TB
        
        W1["📋 01: Idea Promotion<br/>─────────────────<br/>Voice → Idea → Instruction<br/>Artifact lifecycle states<br/>RAG re-indexing triggers"]
        
        W2["🔍 02: RAG Retrieval<br/>─────────────────<br/>Query → Embedding → Search<br/>Reranking → Context assembly<br/>Citation extraction"]
        
        W3["🎤 03: Instruction Builder<br/>─────────────────<br/>Voice → Transcription<br/>Proofreading → Planning<br/>Execution → Artifacts"]
        
        W4["📝 04: Prompt Layering<br/>─────────────────<br/>Base preset → User override<br/>Variable interpolation<br/>Final prompt composition"]
        
        W5["❓ 05: Clarification Loop<br/>─────────────────<br/>Issue detection → Grouping<br/>Question generation<br/>Answer → Regeneration"]
    end

    subgraph Integration["🔗 INTEGRATION POINTS"]
        I1((1))
        I2((2))
        I3((3))
        I4((4))
        I5((5))
        I6((6))
    end

    W1 --> |"Idea created"| I1
    I1 --> |"Triggers indexing"| W2
    
    W3 --> |"Needs context"| I2
    I2 --> |"RAG retrieval"| W2
    
    W3 --> |"Needs prompt"| I3
    I3 --> |"Compose prompt"| W4
    
    W3 --> |"Artifact generated"| I4
    I4 --> |"Validate quality"| W5
    
    W5 --> |"Refinement needed"| I5
    I5 --> |"Re-execute"| W3
    
    W5 --> |"Answers collected"| I6
    I6 --> |"Update context"| W4

    style Workflows fill:#f5f5f5,stroke:#9e9e9e
    style Integration fill:#fff9c4,stroke:#fbc02d
```

---

## 4. Complete Data Flow Sequence

```mermaid
sequenceDiagram
    autonumber
    box rgb(227, 242, 253) User Interface
        participant U as 👤 User
        participant UI as 🖥️ Frontend
    end
    
    box rgb(255, 243, 224) API & Services
        participant API as 🔌 API
        participant IB as 📋 InstructionBuilder
        participant PS as 📝 PromptSystem
        participant QC as ✅ QualityChecker
    end
    
    box rgb(252, 228, 236) AI Models
        participant STT as 🔊 Speech-to-Text
        participant LLM as 🧠 LLM
        participant EMB as 📊 Embeddings
    end
    
    box rgb(243, 229, 245) Data Layer
        participant RAG as 🔍 RAG
        participant DB as 🗄️ SQLite
        participant FS as 📁 Filesystem
    end

    rect rgb(227, 242, 253)
        Note over U,UI: 1️⃣ INPUT (Diagram 03: Instruction Builder)
        U->>UI: Voice/Text input
        UI->>API: Submit content
        API->>IB: Process input
        IB->>STT: Transcribe audio
        STT-->>IB: Clean text
    end

    rect rgb(232, 245, 233)
        Note over IB,RAG: 2️⃣ CONTEXT (Diagram 02: RAG Retrieval)
        IB->>RAG: Request relevant context
        RAG->>EMB: Embed query
        EMB-->>RAG: Query vector
        RAG->>RAG: Vector search + rerank
        RAG-->>IB: Top-K chunks
    end

    rect rgb(255, 243, 224)
        Note over IB,PS: 3️⃣ PROMPT (Diagram 04: Prompt Layering)
        IB->>PS: Request prompt for type
        PS->>DB: Load base preset
        DB-->>PS: Preset template
        PS->>DB: Load user override
        DB-->>PS: Custom layer
        PS->>PS: Compose + interpolate
        PS-->>IB: Final prompt
    end

    rect rgb(252, 228, 236)
        Note over IB,LLM: 4️⃣ GENERATION
        IB->>LLM: Generate with prompt + context
        LLM->>LLM: Reasoning chain
        LLM-->>IB: Generated artifact
    end

    rect rgb(255, 224, 178)
        Note over IB,QC: 5️⃣ QUALITY (Diagram 05: Clarification Loop)
        IB->>QC: Validate artifact
        QC->>LLM: Analyze for issues
        LLM-->>QC: Issue report
        
        alt Issues found
            QC-->>UI: Clarification questions
            U->>UI: Provide answers
            UI->>QC: Submit answers
            QC->>IB: Regenerate with refinements
            IB->>LLM: Re-generate
            LLM-->>IB: Refined artifact
        end
    end

    rect rgb(225, 190, 231)
        Note over IB,FS: 6️⃣ PERSIST (Diagram 01: Idea Promotion)
        IB->>FS: Save artifact
        FS-->>IB: File path
        IB->>DB: Register artifact
        DB-->>IB: Artifact ID
        IB->>RAG: Index new artifact
        RAG->>EMB: Generate embeddings
        EMB-->>RAG: Chunk vectors
        RAG->>DB: Store chunk metadata
    end

    rect rgb(178, 223, 219)
        Note over IB,UI: 7️⃣ COMPLETE
        IB-->>API: Success + artifact
        API-->>UI: Display result
        UI-->>U: Show generated spec
    end
```

---

## 5. Component Dependency Matrix

```mermaid
flowchart TD
    subgraph Legend["Legend"]
        direction LR
        L1[Component] --> |"depends on"| L2[Dependency]
    end

    subgraph DiagramDependencies["Diagram Dependencies"]
        D01["01: Idea Promotion"]
        D02["02: RAG Retrieval"]
        D03["03: Instruction Builder"]
        D04["04: Prompt Layering"]
        D05["05: Clarification Loop"]
        
        D03 --> |"uses for context"| D02
        D03 --> |"uses for prompts"| D04
        D03 --> |"validates with"| D05
        D03 --> |"creates artifacts for"| D01
        
        D05 --> |"triggers re-run of"| D03
        D05 --> |"updates"| D04
        
        D01 --> |"feeds into"| D02
        
        D02 --> |"enriches"| D03
        D02 --> |"provides context to"| D05
    end

    subgraph ServiceDependencies["Service Dependencies"]
        SVC_IB[InstructionBuilder]
        SVC_PS[PromptService]
        SVC_QC[QualityChecker]
        SVC_RAG[RAGService]
        SVC_FS[FileService]
        SVC_DB[DatabaseService]
        
        SVC_IB --> SVC_PS
        SVC_IB --> SVC_QC
        SVC_IB --> SVC_RAG
        SVC_IB --> SVC_FS
        
        SVC_PS --> SVC_DB
        SVC_QC --> SVC_DB
        SVC_RAG --> SVC_DB
        SVC_FS --> SVC_DB
    end

    style Legend fill:#f5f5f5,stroke:#9e9e9e
    style DiagramDependencies fill:#e3f2fd,stroke:#1976d2
    style ServiceDependencies fill:#e8f5e9,stroke:#388e3c
```

---

## 6. Layer Responsibility Matrix

| Layer | Components | Responsibilities | Diagrams Referenced |
|-------|------------|------------------|---------------------|
| **User Interface** | React Frontend, Voice Recorder, Question Wizard | User input capture, result display, clarification collection | 03, 05 |
| **API Gateway** | REST endpoints, WebSocket streams | Request routing, auth, streaming responses | All |
| **Instruction Pipeline** | Transcription, Proofreading, Planning, Execution | Voice-to-spec transformation | 03 |
| **Prompt System** | Preset Manager, Composer, Variable Interpolator | Prompt assembly and customization | 04 |
| **Quality Loop** | Issue Detector, Question Generator, Regenerator | Artifact validation and refinement | 05 |
| **RAG System** | Indexer, Chunker, Embedder, Retriever | Context retrieval for generation | 01, 02 |
| **AI Models** | STT (ElevenLabs), LLM (LLaMA/Gemini), Embeddings | Transcription, reasoning, vectorization | 02, 03 |
| **Data Storage** | SQLite, Filesystem, Vector Store | Persistence of artifacts, metadata, vectors | 01, 02 |
| **Version Control** | Git Integration, History Snapshots | Change tracking and rollback | 01 |

---

## 7. Request Flow Patterns

### Pattern A: New Idea from Voice

```mermaid
flowchart LR
    A1[🎤 Voice] --> A2[📋 Transcribe]
    A2 --> A3[✏️ Proofread]
    A3 --> A4[💡 Save as Idea]
    A4 --> A5[🔍 Index in RAG]
    
    style A1 fill:#e3f2fd
    style A5 fill:#c8e6c9
```
**Diagrams:** 03 → 01 → 02

---

### Pattern B: Promote Idea to Instruction

```mermaid
flowchart LR
    B1[💡 Idea] --> B2[📝 Load Preset]
    B2 --> B3[🔍 Get Context]
    B3 --> B4[🧠 Generate]
    B4 --> B5[❓ Validate]
    B5 --> B6[📋 Instruction]
    B6 --> B7[🔍 Re-index]
    
    style B1 fill:#fff9c4
    style B6 fill:#c8e6c9
```
**Diagrams:** 01 → 04 → 02 → 03 → 05 → 01

---

### Pattern C: Clarification Refinement

```mermaid
flowchart LR
    C1[📄 Artifact] --> C2[🔍 Detect Issues]
    C2 --> C3[❓ Questions]
    C3 --> C4[✅ Answers]
    C4 --> C5[📝 Update Prompt]
    C5 --> C6[🔄 Regenerate]
    C6 --> C7[📄 Refined Artifact]
    
    style C1 fill:#ffcdd2
    style C7 fill:#c8e6c9
```
**Diagrams:** 05 → 04 → 03

---

### Pattern D: RAG-Enhanced Generation

```mermaid
flowchart LR
    D1[📝 User Input] --> D2[🔍 Query RAG]
    D2 --> D3[📊 Top-K Chunks]
    D3 --> D4[📝 Compose Prompt]
    D4 --> D5[🧠 Generate]
    D5 --> D6[📄 Context-Rich Output]
    
    style D1 fill:#e3f2fd
    style D6 fill:#c8e6c9
```
**Diagrams:** 02 → 04 → 03

---

## 8. Technology Stack Summary

```mermaid
flowchart TB
    subgraph Frontend["🖥️ Frontend (React)"]
        direction LR
        REACT[React 18+]
        TS[TypeScript]
        TW[TailwindCSS]
        SHADCN[shadcn/ui]
        RQ[React Query]
    end

    subgraph Backend["⚙️ Backend (Go)"]
        direction LR
        GO[Go 1.21+]
        GORM[GORM]
        GIN[Gin/Echo]
        GOGIT[go-git]
    end

    subgraph AI["🧠 AI Services"]
        direction LR
        LLAMA[llama.cpp]
        ELEVEN[ElevenLabs STT]
        GEMINI[Gemini API]
    end

    subgraph Data["💾 Data"]
        direction LR
        SQLITE[(SQLite)]
        VECDB[(Vector Store)]
        GITFS[Git + .history/]
    end

    Frontend --> |REST/SSE| Backend
    Backend --> |API| AI
    Backend --> |ORM| Data
    AI --> |Embeddings| Data

    style Frontend fill:#e3f2fd,stroke:#1976d2
    style Backend fill:#e8f5e9,stroke:#388e3c
    style AI fill:#fce4ec,stroke:#c2185b
    style Data fill:#f3e5f5,stroke:#7b1fa2
```

---

## 9. Diagram Quick Reference

| # | Diagram | Primary Focus | Key Entities | Entry Points |
|---|---------|---------------|--------------|--------------|
| 01 | [Idea Promotion](./01-idea-promotion-workflow.md) | Artifact lifecycle | Idea, Instruction, PromotionEvent | Voice input, Manual create |
| 02 | [RAG Retrieval](./02-rag-retrieval-flow.md) | Context retrieval | Query, Chunk, Embedding, Citation | Generation request |
| 03 | [Instruction Builder](./03-instruction-builder-pipeline.md) | Voice-to-spec | Transcript, Plan, Artifact | Voice/Text input |
| 04 | [Prompt Layering](./04-prompt-preset-layering.md) | Prompt composition | Preset, Override, Variables | Generation request |
| 05 | [Clarification Loop](./05-inconsistency-clarification-workflow.md) | Quality assurance | Issue, Question, Answer, Regeneration | Post-generation |

---

## 10. Cross-References

- **Overview:** [00-overview.md](../00-overview.md)
- **Features Index:** [05-features/00-overview.md](../05-features/00-overview.md)
- **RAG System:** [01-rag-system.md](../05-features/09-knowledge-memory/01-rag-system.md)
- **Instruction System:** [03-instruction-system.md](../05-features/06-ai-integration/03-instruction-system.md)
- **Consistency Report:** [99-consistency-report.md](../99-consistency-report.md)

---

## 11. Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-28 | Initial system architecture overview integrating all 5 workflow diagrams |
