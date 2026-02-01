# Feature Dependency Graph

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

This diagram visualizes dependencies between all feature specifications, showing which features must be implemented before others and how they interconnect.

---

## Master Dependency Graph

```mermaid
graph TB
    subgraph Foundation["🏗️ Foundation Layer"]
        AUTH[01-Authentication]
        THEME[10-Theme System]
        ROUTE[12-Routing]
        STATE[16-State Management]
        API[15-API Client]
        ERROR[13-Error UI]
    end

    subgraph Core["📁 Core Features"]
        FILE[02-File Management]
        PROJ[03-Project Management]
        DASH[11-Dashboard]
        EDIT[04-Spec Editor]
    end

    subgraph AI["🤖 AI Integration"]
        VOICE[05-Voice Input]
        AICORE[06-AI Integration]
        RAG[09-Knowledge Memory]
        CONSIST[08-Consistency Checker]
    end

    subgraph History["📜 History & Versioning"]
        HIST[07-History System]
    end

    subgraph Infrastructure["⚙️ Infrastructure"]
        MOBILE[14-Mobile Responsive]
        MONITOR[17-Monitoring]
        REALTIME[18-Realtime]
        PERF[19-Performance]
        TEST[20-Testing]
        I18N[21-i18n]
    end

    %% Foundation Dependencies
    AUTH --> STATE
    AUTH --> API
    AUTH --> ERROR
    THEME --> STATE
    ROUTE --> AUTH
    ERROR --> THEME

    %% Core Dependencies
    FILE --> AUTH
    FILE --> API
    FILE --> STATE
    PROJ --> FILE
    PROJ --> AUTH
    DASH --> PROJ
    DASH --> FILE
    DASH --> ROUTE
    EDIT --> FILE
    EDIT --> THEME

    %% AI Dependencies
    VOICE --> AUTH
    VOICE --> API
    AICORE --> VOICE
    AICORE --> API
    AICORE --> STATE
    RAG --> FILE
    RAG --> AICORE
    CONSIST --> FILE
    CONSIST --> RAG
    CONSIST --> AICORE

    %% History Dependencies
    HIST --> FILE
    HIST --> PROJ
    HIST --> STATE

    %% Infrastructure Dependencies
    MOBILE --> THEME
    MOBILE --> DASH
    MONITOR --> API
    MONITOR --> AUTH
    REALTIME --> API
    REALTIME --> STATE
    PERF --> STATE
    PERF --> API
    TEST --> AUTH
    TEST --> FILE
    I18N --> THEME
    I18N --> STATE

    %% Cross-cutting
    EDIT --> HIST
    AICORE --> HIST
    RAG --> HIST

    classDef foundation fill:#e1f5fe,stroke:#01579b,stroke-width:2px
    classDef core fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px
    classDef ai fill:#fff3e0,stroke:#e65100,stroke-width:2px
    classDef history fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px
    classDef infra fill:#fce4ec,stroke:#c2185b,stroke-width:2px

    class AUTH,THEME,ROUTE,STATE,API,ERROR foundation
    class FILE,PROJ,DASH,EDIT core
    class VOICE,AICORE,RAG,CONSIST ai
    class HIST history
    class MOBILE,MONITOR,REALTIME,PERF,TEST,I18N infra
```

---

## Implementation Order (Topological Sort)

```mermaid
graph LR
    subgraph Phase1["Phase 1: Foundation"]
        P1A[Theme System]
        P1B[State Management]
        P1C[API Client]
        P1D[Error UI]
    end

    subgraph Phase2["Phase 2: Auth & Routing"]
        P2A[Authentication]
        P2B[Routing]
    end

    subgraph Phase3["Phase 3: Core Management"]
        P3A[File Management]
        P3B[Project Management]
        P3C[Dashboard]
        P3D[Spec Editor]
    end

    subgraph Phase4["Phase 4: History"]
        P4A[History System]
    end

    subgraph Phase5["Phase 5: AI Pipeline"]
        P5A[Voice Input]
        P5B[AI Integration]
        P5C[Knowledge Memory]
        P5D[Consistency Checker]
    end

    subgraph Phase6["Phase 6: Infrastructure"]
        P6A[Mobile Responsive]
        P6B[Monitoring]
        P6C[Realtime]
        P6D[Performance]
        P6E[Testing]
        P6F[i18n]
    end

    Phase1 --> Phase2 --> Phase3 --> Phase4 --> Phase5 --> Phase6

    style Phase1 fill:#e3f2fd
    style Phase2 fill:#e8f5e9
    style Phase3 fill:#fff8e1
    style Phase4 fill:#f3e5f5
    style Phase5 fill:#fff3e0
    style Phase6 fill:#fce4ec
```

---

## Detailed Feature Dependencies

### Foundation Layer

```mermaid
graph TD
    subgraph Foundation["Foundation Layer Details"]
        THEME[10-Theme System]
        STATE[16-State Management]
        API[15-API Client]
        ERROR[13-Error UI]
        AUTH[01-Authentication]
        ROUTE[12-Routing]
    end

    THEME --> |"provides tokens"| ERROR
    STATE --> |"React Query"| API
    API --> |"interceptors"| AUTH
    AUTH --> |"guards"| ROUTE
    ERROR --> |"error boundaries"| ROUTE

    subgraph Components["Key Components"]
        TC[ThemeProvider]
        QC[QueryClient]
        AC[AuthContext]
        RC[RouterProvider]
        EB[ErrorBoundary]
    end

    THEME -.-> TC
    STATE -.-> QC
    AUTH -.-> AC
    ROUTE -.-> RC
    ERROR -.-> EB
```

### Core Features

```mermaid
graph TD
    subgraph Core["Core Features Details"]
        FILE[02-File Management]
        PROJ[03-Project Management]
        DASH[11-Dashboard]
        EDIT[04-Spec Editor]
    end

    subgraph FileComponents["File Management"]
        FO[File Operations]
        PM[Path Manager]
        FT[Folder Tree]
        FS[Folder Sync]
    end

    subgraph ProjectComponents["Project Management"]
        IE[Import/Export System]
        IU[Import/Export UI]
    end

    subgraph EditorComponents["Spec Editor"]
        ME[Markdown Editor]
        PR[Preview Renderer]
        TM[Template Manager]
    end

    FILE --> FO
    FILE --> PM
    FILE --> FT
    FILE --> FS
    
    PROJ --> IE
    PROJ --> IU
    PROJ --> FILE

    EDIT --> ME
    EDIT --> PR
    EDIT --> TM
    EDIT --> FILE

    DASH --> PROJ
    DASH --> FILE
```

### AI Integration Pipeline

```mermaid
graph TD
    subgraph AI["AI Integration Pipeline"]
        VOICE[05-Voice Input]
        AICORE[06-AI Integration]
        RAG[09-Knowledge Memory]
        CONSIST[08-Consistency Checker]
    end

    subgraph VoiceComponents["Voice Input"]
        VR[Voice Recorder]
        TD[Transcription Display]
        AP[Audio Player]
    end

    subgraph AIComponents["AI Core"]
        AI1[LLM Integration]
        AI2[Preset System]
        AI3[Instruction System]
        AI4[Instruction History]
        AI5[Task Segmentation]
        AI6[Live Logging]
        AI7[Server Management]
        AI8[Chat UI]
        AI9[Instruction Builder]
        AI10[Prompt Panel]
    end

    subgraph RAGComponents["Knowledge Memory"]
        R1[RAG System]
        R2[Vector Database]
        R3[Vector Search]
        R4[Context Manager]
        R5[Memory Compression]
        R6[Knowledge Worker]
        R7[Memory UI]
    end

    subgraph ConsistComponents["Consistency"]
        C1[Checker Engine]
        C2[Implementation]
        C3[Dashboard]
    end

    VOICE --> VR
    VOICE --> TD
    VOICE --> AP

    AICORE --> AI1
    AICORE --> AI2
    AICORE --> AI3
    AICORE --> AI4
    AICORE --> AI5
    AICORE --> AI6
    AICORE --> AI7
    AICORE --> AI8
    AICORE --> AI9
    AICORE --> AI10

    RAG --> R1
    RAG --> R2
    RAG --> R3
    RAG --> R4
    RAG --> R5
    RAG --> R6
    RAG --> R7

    CONSIST --> C1
    CONSIST --> C2
    CONSIST --> C3

    VOICE --> AICORE
    AICORE --> RAG
    RAG --> CONSIST
```

---

## Cross-Feature Data Flows

```mermaid
graph LR
    subgraph Input["User Input"]
        UI1[Voice Recording]
        UI2[Text Input]
        UI3[File Upload]
    end

    subgraph Processing["Processing"]
        P1[Transcription]
        P2[Proofreading]
        P3[Planning]
        P4[Task Generation]
    end

    subgraph Storage["Storage"]
        S1[File System]
        S2[SQLite DB]
        S3[Vector Store]
        S4[Git Repository]
    end

    subgraph Output["Output"]
        O1[Generated Specs]
        O2[Snapshots]
        O3[Consistency Reports]
    end

    UI1 --> P1
    UI2 --> P2
    UI3 --> S1

    P1 --> P2
    P2 --> P3
    P3 --> P4

    P4 --> S1
    P4 --> S2
    S1 --> S3
    S1 --> S4

    S1 --> O1
    S4 --> O2
    S3 --> O3
```

---

## Feature Coupling Matrix

| Feature | High Coupling | Medium Coupling | Low Coupling |
|---------|---------------|-----------------|--------------|
| **01-Authentication** | API Client, State | Routing, Error UI | All features |
| **02-File Management** | Project Mgmt, Editor | History, RAG | Dashboard |
| **03-Project Management** | File Mgmt | Dashboard, Import/Export | History |
| **04-Spec Editor** | File Mgmt, Theme | History, Preview | Templates |
| **05-Voice Input** | AI Integration | Transcription | Audio Player |
| **06-AI Integration** | Voice, RAG, History | Presets, Tasks | UI components |
| **07-History System** | File Mgmt, Project | Git, Snapshots | Diff viewer |
| **08-Consistency Checker** | RAG, AI, Files | Dashboard | Reports |
| **09-Knowledge Memory** | AI, Files, Vector DB | Context Mgmt | Memory UI |
| **10-Theme System** | State Mgmt | All UI components | i18n |
| **11-Dashboard** | Project, Files, Route | Quick actions | Stats |
| **12-Routing** | Auth, Dashboard | Protected routes | Navigation |
| **13-Error UI** | Theme, API | Error boundaries | Toast |
| **14-Mobile Responsive** | Theme, Dashboard | Layouts | Breakpoints |
| **15-API Client** | Auth, State | Interceptors | React Query |
| **16-State Management** | Theme, Auth, API | Context, Query | Stores |
| **17-Monitoring** | API, Auth | Metrics, Logging | Alerts |
| **18-Realtime** | API, State | WebSocket, SSE | Events |
| **19-Performance** | State, API | Caching, Lazy load | Optimization |
| **20-Testing** | Auth, Files | Unit, E2E | Coverage |
| **21-i18n** | Theme, State | Translations | Locale |

---

## Critical Path Analysis

```mermaid
graph LR
    subgraph Critical["🔴 Critical Path"]
        CP1[Theme] --> CP2[State] --> CP3[API] --> CP4[Auth] --> CP5[Files] --> CP6[AI] --> CP7[RAG]
    end

    subgraph Parallel1["🟡 Parallel Track A"]
        PA1[Error UI] --> PA2[Routing] --> PA3[Dashboard]
    end

    subgraph Parallel2["🟢 Parallel Track B"]
        PB1[Editor] --> PB2[History] --> PB3[Consistency]
    end

    subgraph Parallel3["🔵 Parallel Track C"]
        PC1[Voice] --> PC2[Mobile] --> PC3[i18n]
    end

    CP2 --> PA1
    CP4 --> PA2
    CP5 --> PB1
    CP6 --> PC1
    CP5 --> PB2

    style CP1 fill:#ffcdd2
    style CP2 fill:#ffcdd2
    style CP3 fill:#ffcdd2
    style CP4 fill:#ffcdd2
    style CP5 fill:#ffcdd2
    style CP6 fill:#ffcdd2
    style CP7 fill:#ffcdd2
```

---

## Legend

| Symbol | Meaning |
|--------|---------|
| 🏗️ | Foundation infrastructure |
| 📁 | Core file/project features |
| 🤖 | AI-powered features |
| 📜 | History and versioning |
| ⚙️ | Infrastructure and tooling |
| → | Direct dependency |
| ⟶ | Data flow |
| -.-> | Component relationship |

---

## Related Documents

- [System Architecture](./00-system-architecture-overview.md)
- [Implementation Order Guide](../08-roadmap-overview/02a-implementation-order-guide.md)
- [Master Index](../00-master-index.md)
