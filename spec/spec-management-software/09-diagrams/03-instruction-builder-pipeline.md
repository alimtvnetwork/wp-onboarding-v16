# Instruction Builder: Voice-to-Spec Pipeline

> **Version:** 1.0.0  
> **Status:** Active  
> **Last Updated:** 2026-01-28

---

## 1. Overview

This document visualizes the complete Instruction Builder pipeline that transforms voice or text input into structured specification artifacts. The pipeline consists of five stages: Input → Transcription → Proofreading → Planning → Execution.

---

## 2. Pipeline Flowchart

```mermaid
flowchart TD
    subgraph Input["1️⃣ INPUT STAGE"]
        VI[🎤 Voice Input]
        TI[⌨️ Text Input]
        VI --> |MediaRecorder API| AB[Audio Blob]
        TI --> CT
        AB --> TR
    end

    subgraph Transcription["2️⃣ TRANSCRIPTION STAGE"]
        TR[🔊 Transcription Service]
        TR --> |ElevenLabs Scribe v2| RT[Raw Transcript]
        RT --> |Confidence Score| QC{Quality Check}
        QC --> |score ≥ 0.85| CT[Clean Text]
        QC --> |score < 0.85| RE[Request Re-record]
        RE -.-> VI
    end

    subgraph Proofreading["3️⃣ PROOFREADING STAGE"]
        CT --> PP[✏️ Proofread Processor]
        PP --> |LLM: gemini-3-flash| PO[Proofread Output]
        
        subgraph ProofreadTasks["Proofreading Tasks"]
            FG[Fix Grammar]
            FC[Fix Clarity]
            FT[Fix Technical Terms]
            FS[Fix Structure]
        end
        
        PP --> ProofreadTasks
        ProofreadTasks --> PO
        
        PO --> TC{Type Classification}
        TC --> |Auto-detect| CL[Content Type Label]
        
        CL --> |idea| IDEA[💡 Idea]
        CL --> |feature| FEAT[🚀 Feature]
        CL --> |task| TASK[✅ Task]
        CL --> |codingGuideline| GUIDE[📏 Coding Guideline]
        CL --> |instruction| INST[📋 Instruction]
    end

    subgraph Planning["4️⃣ PLANNING STAGE"]
        IDEA & FEAT & TASK & GUIDE & INST --> PS[📝 Planning Service]
        
        PS --> |Load Base Preset| BP[Base Prompt]
        PS --> |Apply User Override| UO[Custom Layer]
        BP --> FP[Final Prompt]
        UO --> FP
        
        FP --> RM[🧠 Reasoning Model]
        RM --> |deepseek-r1 / gemini-2.5-pro| PL[Generated Plan]
        
        PL --> IC{Inconsistency Check}
        IC --> |Issues Found| IQ[❓ Clarification Questions]
        IC --> |No Issues| EX
        
        IQ --> UA[User Answers]
        UA --> |Refine Plan| PL
    end

    subgraph Execution["5️⃣ EXECUTION STAGE"]
        EX[⚡ Execution Engine]
        
        EX --> |Generate Artifacts| GA[Generated Artifacts]
        
        subgraph Outputs["Output Artifacts"]
            MD[📄 Markdown Spec]
            JSON[📋 JSON Structure]
            AC[✅ Acceptance Criteria]
        end
        
        GA --> Outputs
        
        Outputs --> FS2[💾 File System Save]
        FS2 --> |ideas/ or instructions/| PATH[Artifact Path]
        
        PATH --> DB[(SQLite Registry)]
        PATH --> RAG[🔍 RAG Indexer]
        
        RAG --> |Chunk + Embed| VEC[Vector Store]
        
        DB --> HIST[📜 History Entry]
        VEC --> READY[✅ Ready for Retrieval]
    end

    style Input fill:#e3f2fd,stroke:#1976d2
    style Transcription fill:#fff3e0,stroke:#f57c00
    style Proofreading fill:#e8f5e9,stroke:#388e3c
    style Planning fill:#fce4ec,stroke:#c2185b
    style Execution fill:#f3e5f5,stroke:#7b1fa2
```

---

## 3. Sequence Diagram

```mermaid
sequenceDiagram
    autonumber
    
    participant U as 👤 User
    participant UI as 🖥️ Frontend
    participant API as 🔌 API Gateway
    participant TS as 🔊 Transcription
    participant PP as ✏️ Proofreader
    participant PL as 🧠 Planner
    participant EX as ⚡ Executor
    participant FS as 💾 FileSystem
    participant DB as 🗄️ SQLite
    participant RAG as 🔍 RAG Index

    rect rgb(227, 242, 253)
        Note over U,UI: Input Stage
        U->>UI: Start voice recording
        UI->>UI: MediaRecorder capture
        U->>UI: Stop recording
        UI->>API: POST /api/instructions/transcribe
    end

    rect rgb(255, 243, 224)
        Note over API,TS: Transcription Stage
        API->>TS: Send audio blob
        TS->>TS: ElevenLabs Scribe v2
        TS-->>API: Raw transcript + confidence
        
        alt Confidence < 0.85
            API-->>UI: Request re-record
            UI-->>U: Show retry prompt
        else Confidence ≥ 0.85
            API->>PP: Send clean text
        end
    end

    rect rgb(232, 245, 233)
        Note over PP: Proofreading Stage
        PP->>PP: Fix grammar/clarity
        PP->>PP: Normalize technical terms
        PP->>PP: Classify content type
        PP-->>API: Proofread output + type
    end

    rect rgb(252, 228, 236)
        Note over API,PL: Planning Stage
        API->>DB: Load base preset for type
        DB-->>API: Base prompt template
        API->>DB: Load user overrides
        DB-->>API: Custom prompt layer
        API->>PL: Combined prompt + content
        
        PL->>PL: Reasoning model generates plan
        PL->>PL: Detect inconsistencies
        
        alt Inconsistencies found
            PL-->>API: Clarification questions
            API-->>UI: Display question wizard
            U->>UI: Answer questions
            UI->>API: Submit answers
            API->>PL: Refine with answers
        end
        
        PL-->>API: Final plan + tasks
    end

    rect rgb(243, 229, 245)
        Note over API,RAG: Execution Stage
        API->>EX: Execute plan
        
        loop For each artifact
            EX->>EX: Generate Markdown
            EX->>EX: Generate JSON structure
            EX->>EX: Generate acceptance criteria
        end
        
        EX->>FS: Save to ideas/ or instructions/
        FS-->>EX: File path confirmed
        
        par Database & RAG
            EX->>DB: Register artifact
            DB-->>EX: Artifact ID
        and
            EX->>RAG: Index for retrieval
            RAG->>RAG: Chunk content
            RAG->>RAG: Generate embeddings
            RAG-->>EX: Indexed
        end
        
        EX-->>API: Execution complete
        API-->>UI: Success + artifact paths
        UI-->>U: Display results
    end
```

---

## 4. State Diagram

```mermaid
stateDiagram-v2
    [*] --> Idle: Initialize

    state "Idle" as Idle
    state "Recording" as Recording
    state "Transcribing" as Transcribing
    state "Proofreading" as Proofreading
    state "AwaitingType" as AwaitingType
    state "Planning" as Planning
    state "AwaitingClarification" as AwaitingClarification
    state "Executing" as Executing
    state "Saving" as Saving
    state "Complete" as Complete
    state "Error" as Error

    Idle --> Recording: startRecording()
    Idle --> Proofreading: submitText()
    
    Recording --> Transcribing: stopRecording()
    Recording --> Idle: cancelRecording()
    
    Transcribing --> Proofreading: transcriptReady
    Transcribing --> Error: transcriptionFailed
    Transcribing --> Recording: lowConfidence
    
    Proofreading --> AwaitingType: typeDetected (manual mode)
    Proofreading --> Planning: typeDetected (auto mode)
    Proofreading --> Error: proofreadFailed
    
    AwaitingType --> Planning: typeConfirmed()
    AwaitingType --> Idle: cancel()
    
    Planning --> AwaitingClarification: inconsistenciesFound
    Planning --> Executing: planReady
    Planning --> Error: planningFailed
    
    AwaitingClarification --> Planning: answersSubmitted
    AwaitingClarification --> Idle: cancel()
    
    Executing --> Saving: artifactsGenerated
    Executing --> Error: executionFailed
    
    Saving --> Complete: saved
    Saving --> Error: saveFailed
    
    Complete --> Idle: reset()
    Error --> Idle: retry()
    
    note right of Recording
        MediaRecorder active
        Audio chunks buffered
    end note
    
    note right of AwaitingClarification
        UI displays question wizard
        User provides answers
    end note
    
    note right of Complete
        Artifacts saved to filesystem
        Indexed in RAG system
        History entry created
    end note
```

---

## 5. Stage Details

### 5.1 Input Stage

| Component | Technology | Description |
|-----------|------------|-------------|
| Voice Capture | MediaRecorder API | Browser-native audio recording |
| Audio Format | WebM/Opus or WAV | Configurable based on browser |
| Text Input | React Textarea | Direct text entry alternative |
| Chunk Size | 1-5 seconds | Configurable for streaming |

**API Endpoint:**
```
POST /api/instructions/transcribe
Content-Type: multipart/form-data

Body:
  - audio: Blob (audio file)
  - projectId: string
  - language?: string (default: auto-detect)
```

---

### 5.2 Transcription Stage

| Component | Technology | Description |
|-----------|------------|-------------|
| STT Model | ElevenLabs Scribe v2 | High-accuracy transcription |
| Streaming | WebSocket (optional) | Real-time partial transcripts |
| Quality Threshold | 0.85 confidence | Minimum for acceptance |
| Retry Logic | 3 attempts max | Before manual fallback |

**Quality Check Criteria:**
- Word confidence scores averaged
- Silence detection for incomplete audio
- Language detection confidence
- Speaker clarity assessment

---

### 5.3 Proofreading Stage

| Task | Description | Priority |
|------|-------------|----------|
| Grammar Fix | Correct spelling, punctuation, syntax | High |
| Clarity Enhancement | Simplify complex sentences | Medium |
| Technical Normalization | Standardize technical terms | High |
| Structure Improvement | Add paragraph breaks, lists | Low |

**Content Type Classification:**

| Type | Indicators | Output Location |
|------|------------|-----------------|
| `idea` | Exploratory, "what if", brainstorming | `ideas/` |
| `feature` | User story format, acceptance criteria | `instructions/` |
| `task` | Action-oriented, specific deliverable | `instructions/` |
| `codingGuideline` | Standards, patterns, conventions | `instructions/` |
| `instruction` | Step-by-step, imperative | `instructions/` |

---

### 5.4 Planning Stage

| Component | Model | Purpose |
|-----------|-------|---------|
| Reasoning Engine | deepseek-r1:14b / gemini-2.5-pro | Long-chain task decomposition |
| Preset Loader | SQLite query | Load base + user prompts |
| Inconsistency Detector | Same reasoning model | Detect ambiguities, conflicts |
| Question Generator | Structured output | UI-friendly question format |

**Prompt Composition:**
```
Final Prompt = Base Preset + User Custom Layer + Proofread Content

Example:
┌─────────────────────────────────────────────────────┐
│ BASE PRESET (from Prompts/feature/base.md)          │
│ ─────────────────────────────────────────────       │
│ You are a specification writer. Generate a feature │
│ spec with user stories, acceptance criteria...      │
├─────────────────────────────────────────────────────┤
│ USER CUSTOM LAYER (from user override)              │
│ ─────────────────────────────────────────────       │
│ Always include database schema changes.             │
│ Use Mermaid diagrams for complex flows.             │
├─────────────────────────────────────────────────────┤
│ PROOFREAD CONTENT                                   │
│ ─────────────────────────────────────────────       │
│ [User's cleaned and classified input]               │
└─────────────────────────────────────────────────────┘
```

**Clarification Question Format:**
```json
{
  "questions": [
    {
      "id": "q1",
      "phase": "critical",
      "text": "Should this feature require authentication?",
      "type": "radio",
      "options": [
        { "value": "yes", "label": "Yes, require login" },
        { "value": "no", "label": "No, public access" }
      ]
    },
    {
      "id": "q2",
      "phase": "conflict",
      "text": "You mentioned both REST and GraphQL. Which should be primary?",
      "type": "radio",
      "options": [
        { "value": "rest", "label": "REST API" },
        { "value": "graphql", "label": "GraphQL" },
        { "value": "both", "label": "Both (REST primary)" }
      ]
    }
  ]
}
```

---

### 5.5 Execution Stage

| Component | Description | Output |
|-----------|-------------|--------|
| Markdown Generator | Structured spec document | `*.md` file |
| JSON Exporter | Machine-readable structure | `*.json` file |
| Acceptance Criteria | Testable requirements | Embedded in spec |
| File Saver | Atomic write with backup | Filesystem path |
| DB Registrar | Artifact metadata | SQLite entry |
| RAG Indexer | Chunk + embed for retrieval | Vector store |

**Artifact Naming Convention:**
```
ideas/
  └── 01-idea-{slug}.md
  └── 02-idea-{slug}.md

instructions/
  └── 01-instruction-{slug}.md
  └── 02-instruction-{slug}.md
```

**Dual-Format Storage:**
```
ideas/01-idea-user-auth/
  ├── 01-idea-user-auth.md      # Human-readable
  └── 01-idea-user-auth.json    # Machine-readable
```

---

## 6. Error Handling

| Stage | Error Type | Recovery Action |
|-------|------------|-----------------|
| Input | Microphone access denied | Show permission guide |
| Input | Audio too short (<1s) | Prompt to re-record |
| Transcription | Low confidence | Offer re-record or manual edit |
| Transcription | Service timeout | Retry with exponential backoff |
| Proofreading | LLM rate limit | Queue and retry |
| Planning | Inconsistencies unresolvable | Escalate to manual review |
| Execution | File write failed | Rollback and retry |
| Execution | RAG indexing failed | Log warning, continue (non-blocking) |

---

## 7. Performance Targets

| Stage | Target Latency | Notes |
|-------|----------------|-------|
| Voice Recording | Real-time | No added latency |
| Transcription | < 2s per 10s audio | Batch mode |
| Transcription (streaming) | < 200ms | Partial results |
| Proofreading | < 3s | Single LLM call |
| Planning | < 10s | Reasoning model |
| Clarification UI | Instant | Frontend only |
| Execution | < 5s | File + DB + RAG |
| **Total (no clarification)** | **< 20s** | End-to-end |

---

## 8. Cross-References

- **Instruction System Spec:** [03-instruction-system.md](../05-features/06-ai-integration/03-instruction-system.md)
- **Instruction History:** [04-instruction-history.md](../05-features/06-ai-integration/04-instruction-history.md)
- **RAG System:** [01-rag-system.md](../05-features/09-knowledge-memory/01-rag-system.md)
- **Voice Input:** [00-overview.md](../05-features/05-voice-input/00-overview.md)
- **Instruction Builder UI:** [09-instruction-builder-ui.md](../05-features/06-ai-integration/09-instruction-builder-ui.md)
- **AI Prompt Panel:** [10-ai-prompt-panel.md](../05-features/06-ai-integration/10-ai-prompt-panel.md)
