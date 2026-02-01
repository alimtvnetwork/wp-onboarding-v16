# System Architecture Diagram

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

High-level system architecture showing how database components integrate with the overall Spec Management Software.

**Cross-References:**
- [Schema Definition](../01-schema.md)
- [System Architecture Overview](../../09-diagrams/00-system-architecture-overview.md)

---

## Full System Architecture

```mermaid
graph TB
    subgraph "Client Layer"
        UI[React Frontend]
        Voice[Voice Input]
    end
    
    subgraph "API Layer"
        REST[REST API]
        SSE[SSE Streaming]
        WS[WebSocket]
    end
    
    subgraph "Service Layer"
        Auth[Auth Service]
        Project[Project Service]
        File[File Service]
        History[History Service]
        AI[AI Chain Service]
        RAG[RAG Service]
        Consistency[Consistency Checker]
    end
    
    subgraph "Data Layer"
        subgraph "SQLite Database"
            UserDB[(User Tables)]
            ProjectDB[(Project Tables)]
            InstructionDB[(Instruction Tables)]
            RAGDB[(RAG Tables)]
            ConfigDB[(Config Tables)]
        end
        
        subgraph "Extensions"
            FTS5[FTS5 Full-Text]
            VSS[sqlite-vss Vectors]
        end
        
        FS[File System]
        Git[Git Repository]
    end
    
    subgraph "AI Layer"
        LLaMA[LLaMA Server]
        Whisper[Whisper Model]
        Embedding[Embedding Model]
    end
    
    %% Client to API
    UI --> REST
    UI --> SSE
    UI --> WS
    Voice --> REST
    
    %% API to Services
    REST --> Auth
    REST --> Project
    REST --> File
    REST --> History
    REST --> AI
    REST --> RAG
    REST --> Consistency
    SSE --> AI
    WS --> History
    
    %% Services to Data
    Auth --> UserDB
    Project --> ProjectDB
    File --> ProjectDB
    File --> FS
    History --> ProjectDB
    History --> Git
    AI --> InstructionDB
    AI --> LLaMA
    AI --> Whisper
    RAG --> RAGDB
    RAG --> VSS
    RAG --> Embedding
    Consistency --> ProjectDB
    Consistency --> FTS5
    
    %% Config
    Auth --> ConfigDB
    Project --> ConfigDB
    AI --> ConfigDB
```

---

## Database Component Architecture

```mermaid
graph LR
    subgraph "Application"
        App[Go Backend]
    end
    
    subgraph "ORM Layer"
        GORM[GORM ORM]
    end
    
    subgraph "SQLite Core"
        SQLite[(SQLite 3)]
    end
    
    subgraph "Extensions"
        FTS5[FTS5]
        VSS[sqlite-vss]
    end
    
    subgraph "Storage"
        DB[spec-manager.db]
        WAL[WAL Journal]
    end
    
    App --> GORM
    GORM --> SQLite
    SQLite --> FTS5
    SQLite --> VSS
    SQLite --> DB
    SQLite --> WAL
```

---

## Data Flow: User Request

```mermaid
sequenceDiagram
    participant Client
    participant API
    participant Service
    participant GORM
    participant SQLite
    
    Client->>API: HTTP Request
    API->>Service: Call Service Method
    Service->>GORM: Query/Mutation
    GORM->>SQLite: Generated SQL
    SQLite-->>GORM: Result Set
    GORM-->>Service: Go Structs
    Service-->>API: Response Data
    API-->>Client: JSON Response
```

---

## Data Flow: AI Instruction

```mermaid
sequenceDiagram
    participant User
    participant VoiceUI
    participant API
    participant AIService
    participant GORM
    participant LLaMA
    
    User->>VoiceUI: Voice Input
    VoiceUI->>API: POST /instructions
    API->>AIService: ProcessInstruction()
    
    AIService->>GORM: Create Instruction (draft)
    GORM-->>AIService: Instruction ID
    
    AIService->>LLaMA: Analyze Input
    LLaMA-->>AIService: Tasks & Questions
    
    AIService->>GORM: Update Instruction (analyzing)
    AIService->>GORM: Create InstructionTasks
    AIService->>GORM: Create InconsistencyReport
    
    AIService-->>API: SSE: Progress Updates
    API-->>VoiceUI: Stream Updates
    VoiceUI-->>User: Show Progress
```

---

## Data Flow: RAG Retrieval

```mermaid
sequenceDiagram
    participant Service
    participant RAG
    participant GORM
    participant FTS5
    participant VSS
    participant Embedding
    
    Service->>RAG: Query("authentication flow")
    
    RAG->>Embedding: Generate Query Vector
    Embedding-->>RAG: Vector[384]
    
    par Parallel Search
        RAG->>FTS5: Full-Text Search
        FTS5-->>RAG: FTS Results
    and
        RAG->>VSS: Vector Similarity
        VSS-->>RAG: VSS Results
    end
    
    RAG->>RAG: RRF Score Fusion
    
    RAG->>GORM: Load Chunk Details
    GORM-->>RAG: Chunk Entities
    
    RAG->>GORM: Create RetrievalSession
    RAG->>GORM: Create RetrievalSessionChunks
    
    RAG-->>Service: Ranked Chunks
```

---

## Table Distribution by Domain

```mermaid
pie title Tables by Domain (32 Total)
    "User & Auth" : 2
    "Project & Organization" : 3
    "File & Snapshots" : 2
    "Configuration" : 2
    "LLM Models" : 2
    "Prompt System" : 3
    "Instructions" : 5
    "Inconsistency Analysis" : 5
    "RAG" : 6
    "Consistency Checker" : 2
```

---

## Database Tables Summary

| Domain | Tables | Purpose |
|--------|--------|---------|
| **User & Auth** | User, Session | Authentication, sessions |
| **Project & Organization** | Project, ProjectMetadata, VectorIndexMetadata | Project hierarchy, metadata, vector indexing |
| **File & Snapshots** | File, Snapshot | Content organization, versioning |
| **Configuration** | Config, ConfigSeedEvent | System settings, seeding audit |
| **LLM Models** | ModelRegistry, ModelSlot | AI model management |
| **Prompt System** | PromptPreset, PromptPresetVersion, UserPromptOverride | Prompt templates |
| **Instructions** | Instruction, InstructionTask, FileChange, InstructionSegment, MemoryEntry | AI task processing, file tracking, segmentation |
| **Inconsistency Analysis** | InconsistencyReport, InconsistencyIssue, ClarificationQuestion, ClarificationAnswer, RegenerationEvent | Quality analysis, clarifications |
| **RAG** | Artifact, Chunk, Embedding, RetrievalSession, RetrievalSessionChunk, PromotionEvent | Knowledge retrieval, promotion tracking |
| **Consistency Checker** | ConsistencyLoop, ConsistencyLoopIteration | Iterative validation with scoring |

**Total: 32 tables**

---

## Connection Pooling

```mermaid
graph TD
    subgraph "Go Application"
        W1[Worker 1]
        W2[Worker 2]
        W3[Worker 3]
        W4[Worker N]
    end
    
    subgraph "GORM Connection Pool"
        Pool[Connection Pool<br/>MaxOpenConns: 25<br/>MaxIdleConns: 10]
    end
    
    subgraph "SQLite"
        DB[(spec-manager.db)]
    end
    
    W1 --> Pool
    W2 --> Pool
    W3 --> Pool
    W4 --> Pool
    Pool --> DB
```

**Pool Configuration:**

```go
sqlDB, _ := db.DB()
sqlDB.SetMaxOpenConns(25)
sqlDB.SetMaxIdleConns(10)
sqlDB.SetConnMaxLifetime(time.Hour)
```

---

## Backup & Recovery

```mermaid
graph LR
    subgraph "Production"
        DB[(spec-manager.db)]
        WAL[WAL Journal]
    end
    
    subgraph "Backup Strategy"
        Daily[Daily Backup]
        Snapshot[Pre-Migration]
    end
    
    subgraph "Storage"
        Local[Local Backups]
        Remote[Remote Storage]
    end
    
    DB --> Daily
    DB --> Snapshot
    Daily --> Local
    Daily --> Remote
    Snapshot --> Local
```

---

## Related Specs

- [ERD Diagram](./01-erd.md) — Entity relationships
- [Schema Definition](../01-schema.md) — Complete GORM models
- [RAG System](../../05-features/09-knowledge-memory/01-rag-system.md) — Retrieval architecture
