# Master Architecture Diagram

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  

---

## Overview

This document provides comprehensive architectural visualizations of the SpecBuilder Pro microservices system, including service topology, communication patterns, database architecture, and data flows.

**Cross-References:**
- [Microservices Overview](../14-microservices/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)
- [System Architecture Overview](./00-system-architecture-overview.md)

---

## 1. System Overview Diagram

High-level view of all system components and their relationships.

```mermaid
graph TB
    subgraph "Client Layer"
        WEB["🌐 Web Browser<br/>React + Vite<br/>localhost:5173"]
        CLI["⌨️ CLI Tools<br/>gsearch, brun"]
        DESKTOP["🖥️ Desktop App<br/>Wails (optional)"]
    end

    subgraph "API Gateway Layer"
        GW["🚪 Gateway<br/>:8080<br/>━━━━━━━━━━<br/>• JWT/API Key Auth<br/>• Rate Limiting<br/>• Circuit Breaker<br/>• Request Routing"]
    end

    subgraph "Core Services"
        SM["📋 SpecManager<br/>:8081<br/>━━━━━━━━━━<br/>• Project CRUD<br/>• Spec CRUD<br/>• File Operations<br/>• Templates"]
        
        CH["📜 Chronicle<br/>:8083<br/>━━━━━━━━━━<br/>• Version Control<br/>• Commit History<br/>• Diff Generation<br/>• Rollback"]
        
        SC["🔍 Scout<br/>:8093<br/>━━━━━━━━━━<br/>• FTS5 Search<br/>• Vector Search<br/>• RAG Context<br/>• Embeddings"]
    end

    subgraph "AI Services"
        AB["🤖 AI-Bridge<br/>:8082<br/>━━━━━━━━━━<br/>• LLM Abstraction<br/>• SSE Streaming<br/>• Provider Fallback<br/>• 8 Categories"]
        
        VC["🎤 Voice-CLI<br/>:8084<br/>━━━━━━━━━━<br/>• Audio Streaming<br/>• Transcription<br/>• VAD Detection<br/>• Commands"]
    end

    subgraph "Automation Services"
        NF["⚡ Nexus-Flow<br/>:9000<br/>━━━━━━━━━━<br/>• Pipeline Engine<br/>• Stage Execution<br/>• WebSocket Events<br/>• Human-in-Loop"]
    end

    subgraph "Data Layer"
        SETTINGS[("⚙️ settings.db<br/>Global Config")]
        PROJECTS[("📁 projects.db<br/>Project Index")]
        PROJDB[("📂 {project}.db<br/>Per-Project Data")]
        CONVDB[("💬 {conv}.db<br/>AI Conversations")]
    end

    WEB --> GW
    CLI --> GW
    DESKTOP --> GW
    
    GW --> SM
    GW --> CH
    GW --> SC
    GW --> AB
    GW --> VC
    GW --> NF
    
    SM --> CH
    SM --> SC
    AB --> SC
    NF --> AB
    NF --> SM
    VC --> NF
    
    SM --> PROJECTS
    SM --> PROJDB
    CH --> PROJDB
    SC --> PROJDB
    AB --> SETTINGS
    AB --> CONVDB
    NF --> PROJDB
    VC --> CONVDB

    classDef gateway fill:#4a9eff,stroke:#2563eb,color:#fff
    classDef core fill:#10b981,stroke:#059669,color:#fff
    classDef ai fill:#8b5cf6,stroke:#7c3aed,color:#fff
    classDef auto fill:#f59e0b,stroke:#d97706,color:#fff
    classDef db fill:#6b7280,stroke:#4b5563,color:#fff
    classDef client fill:#ec4899,stroke:#db2777,color:#fff
    
    class GW gateway
    class SM,CH,SC core
    class AB,VC ai
    class NF auto
    class SETTINGS,PROJECTS,PROJDB,CONVDB db
    class WEB,CLI,DESKTOP client
```

---

## 2. Service Communication Matrix

Detailed view of inter-service communication patterns.

```mermaid
flowchart LR
    subgraph "Protocol Legend"
        L1["━━━ HTTP/REST"]
        L2["─ ─ WebSocket"]
        L3["··· SSE Stream"]
    end
```

```mermaid
graph LR
    subgraph Gateway["Gateway :8080"]
        GW_AUTH["Auth Middleware"]
        GW_ROUTE["Router"]
        GW_CB["Circuit Breaker"]
    end

    subgraph Services["Backend Services"]
        SM["SpecManager<br/>:8081"]
        CH["Chronicle<br/>:8083"]
        SC["Scout<br/>:8093"]
        AB["AI-Bridge<br/>:8082"]
        VC["Voice-CLI<br/>:8084"]
        NF["Nexus-Flow<br/>:9000"]
    end

    GW_AUTH --> GW_ROUTE
    GW_ROUTE --> GW_CB
    
    GW_CB -->|"REST"| SM
    GW_CB -->|"REST"| CH
    GW_CB -->|"REST"| SC
    GW_CB -->|"REST + SSE"| AB
    GW_CB -->|"REST + WS"| VC
    GW_CB -->|"REST + WS"| NF

    SM -->|"REST"| CH
    SM -->|"REST"| SC
    AB -->|"REST"| SC
    NF -->|"REST"| AB
    NF -->|"REST"| SM
    VC -->|"REST"| NF

    classDef gw fill:#4a9eff,stroke:#2563eb,color:#fff
    classDef svc fill:#10b981,stroke:#059669,color:#fff
    
    class GW_AUTH,GW_ROUTE,GW_CB gw
    class SM,CH,SC,AB,VC,NF svc
```

---

## 3. Port & Protocol Reference

```mermaid
graph TB
    subgraph "Port Allocation"
        direction LR
        P8080["8080<br/>Gateway"]
        P8081["8081<br/>SpecManager"]
        P8082["8082<br/>AI-Bridge"]
        P8083["8083<br/>Chronicle"]
        P8084["8084<br/>Voice-CLI"]
        P8093["8093<br/>Scout"]
        P9000["9000<br/>Nexus-Flow"]
    end

    subgraph "Protocols"
        REST["REST/JSON<br/>All Services"]
        SSE["SSE Streaming<br/>AI-Bridge"]
        WS["WebSocket<br/>Voice-CLI<br/>Nexus-Flow"]
    end

    subgraph "Error Code Ranges"
        E2["2xxx Gateway"]
        E3["3xxx SpecManager"]
        E4["4xxx Chronicle"]
        E5["5xxx Scout"]
        E6["6xxx AI-Bridge"]
        E10["10xxx Nexus-Flow"]
        E11["11xxx Voice-CLI"]
    end

    classDef port fill:#6366f1,stroke:#4f46e5,color:#fff
    classDef proto fill:#14b8a6,stroke:#0d9488,color:#fff
    classDef err fill:#f43f5e,stroke:#e11d48,color:#fff
    
    class P8080,P8081,P8082,P8083,P8084,P8093,P9000 port
    class REST,SSE,WS proto
    class E2,E3,E4,E5,E6,E10,E11 err
```

---

## 4. Database Architecture

Four-tier SQLite database architecture.

```mermaid
graph TB
    subgraph "Tier 1: Global Configuration"
        SETTINGS[("settings.db<br/>━━━━━━━━━━━━━━<br/>• Global Settings<br/>• API Keys (encrypted)<br/>• Theme Preferences<br/>• Provider Configs")]
    end

    subgraph "Tier 2: Global Registries"
        PROJECTS[("projects.db<br/>━━━━━━━━━━━━━━<br/>• Project Index<br/>• Project Metadata<br/>• Statistics")]
        
        CHRONICLE[("chronicle.db<br/>━━━━━━━━━━━━━━<br/>• Commit Index<br/>• Global History")]
        
        SCOUT[("scout.db<br/>━━━━━━━━━━━━━━<br/>• Embedding Models<br/>• Search Config")]
    end

    subgraph "Tier 3: Per-Project Data"
        PROJ1[("{project-id}/project.db<br/>━━━━━━━━━━━━━━<br/>• Specs Table<br/>• Folders Table<br/>• Templates<br/>• Trash")]
        
        HIST1[("{project-id}/history.db<br/>━━━━━━━━━━━━━━<br/>• Commits<br/>• CommitFiles<br/>• FileContents<br/>• Snapshots")]
        
        SEARCH1[("{project-id}/search.db<br/>━━━━━━━━━━━━━━<br/>• Chunks<br/>• Embeddings<br/>• FTS Index")]
    end

    subgraph "Tier 4: Conversation Context"
        CONV1[("{conv-id}.db<br/>━━━━━━━━━━━━━━<br/>• Messages<br/>• Context Chunks<br/>• Session State")]
    end

    SETTINGS --> PROJECTS
    PROJECTS --> PROJ1
    CHRONICLE --> HIST1
    SCOUT --> SEARCH1
    PROJ1 --> CONV1

    classDef t1 fill:#ef4444,stroke:#dc2626,color:#fff
    classDef t2 fill:#f97316,stroke:#ea580c,color:#fff
    classDef t3 fill:#eab308,stroke:#ca8a04,color:#fff
    classDef t4 fill:#22c55e,stroke:#16a34a,color:#fff
    
    class SETTINGS t1
    class PROJECTS,CHRONICLE,SCOUT t2
    class PROJ1,HIST1,SEARCH1 t3
    class CONV1 t4
```

---

## 5. Request Flow Diagrams

### 5.1 Create Spec Flow

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant GW as Gateway<br/>:8080
    participant SM as SpecManager<br/>:8081
    participant CH as Chronicle<br/>:8083
    participant SC as Scout<br/>:8093

    C->>GW: POST /api/v1/projects/{id}/specs
    GW->>GW: Validate JWT
    GW->>SM: Forward request
    
    SM->>SM: Validate spec data
    SM->>SM: Write file to disk
    SM->>SM: Insert into Specs table
    
    SM->>CH: Create commit
    CH->>CH: Generate diff
    CH->>CH: Store commit + files
    CH-->>SM: Commit ID
    
    SM->>SC: Index spec content
    SC->>SC: Chunk content
    SC->>SC: Generate embeddings
    SC->>SC: Update FTS index
    SC-->>SM: Index complete
    
    SM-->>GW: Spec response
    GW-->>C: 201 Created
```

### 5.2 Search with RAG Flow

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant GW as Gateway<br/>:8080
    participant AB as AI-Bridge<br/>:8082
    participant SC as Scout<br/>:8093
    participant LLM as LLM Provider

    C->>GW: POST /api/v1/ai/chat
    GW->>AB: Forward chat request
    
    AB->>SC: GET /search/rag
    SC->>SC: Hybrid search (FTS + Vector)
    SC->>SC: MMR reranking
    SC-->>AB: Context chunks
    
    AB->>AB: Build prompt with context
    AB->>LLM: Stream completion
    
    loop SSE Streaming
        LLM-->>AB: Token
        AB-->>GW: SSE event
        GW-->>C: SSE event
    end
    
    AB->>AB: Store conversation
    AB-->>GW: Final response
    GW-->>C: Stream complete
```

### 5.3 Voice Recording Flow

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant GW as Gateway<br/>:8080
    participant VC as Voice-CLI<br/>:8084
    participant NF as Nexus-Flow<br/>:9000
    participant SM as SpecManager<br/>:8081

    C->>GW: WS /api/v1/voice/stream
    GW->>VC: WebSocket upgrade
    
    loop Audio Streaming
        C->>VC: Binary audio chunk (PCM16)
        VC->>VC: VAD processing
        VC->>VC: Buffer audio
    end
    
    C->>VC: {"type": "end_stream"}
    VC->>VC: Transcribe audio (Whisper)
    VC->>VC: Parse commands
    
    alt Command Detected
        VC->>NF: Execute command flow
        NF->>SM: Perform action
        SM-->>NF: Result
        NF-->>VC: Flow complete
    end
    
    VC-->>C: Transcription result
```

### 5.4 Pipeline Execution Flow

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant GW as Gateway<br/>:8080
    participant NF as Nexus-Flow<br/>:9000
    participant AB as AI-Bridge<br/>:8082
    participant SM as SpecManager<br/>:8081

    C->>GW: WS /api/v1/flows/{id}/events
    GW->>NF: WebSocket upgrade
    
    C->>GW: POST /api/v1/flows/{id}/execute
    GW->>NF: Start execution
    
    NF-->>C: {"type": "execution_started"}
    
    loop For each stage
        NF-->>C: {"type": "stage_started"}
        
        alt AI Stage
            NF->>AB: Generate content
            AB-->>NF: AI response
        else File Stage
            NF->>SM: Read/Write file
            SM-->>NF: File content
        end
        
        NF-->>C: {"type": "stage_completed"}
    end
    
    NF-->>C: {"type": "execution_completed"}
```

---

## 6. Error Propagation Flow

```mermaid
flowchart TD
    subgraph "Error Origin"
        E_SM["SpecManager Error<br/>Code: 3xxx"]
        E_CH["Chronicle Error<br/>Code: 4xxx"]
        E_SC["Scout Error<br/>Code: 5xxx"]
        E_AB["AI-Bridge Error<br/>Code: 6xxx"]
        E_NF["Nexus-Flow Error<br/>Code: 10xxx"]
        E_VC["Voice-CLI Error<br/>Code: 11xxx"]
    end

    subgraph "Gateway Processing"
        GW_RECV["Receive Error"]
        GW_LOG["Log with Request ID"]
        GW_WRAP["Wrap with Gateway Context"]
        GW_CB["Update Circuit Breaker"]
    end

    subgraph "Client Response"
        RESP["JSON Error Response<br/>━━━━━━━━━━━━━━<br/>• code: number<br/>• constant: string<br/>• message: string<br/>• details: object<br/>• retryable: boolean<br/>• stackTrace: array"]
    end

    E_SM --> GW_RECV
    E_CH --> GW_RECV
    E_SC --> GW_RECV
    E_AB --> GW_RECV
    E_NF --> GW_RECV
    E_VC --> GW_RECV

    GW_RECV --> GW_LOG
    GW_LOG --> GW_WRAP
    GW_WRAP --> GW_CB
    GW_CB --> RESP

    classDef error fill:#ef4444,stroke:#dc2626,color:#fff
    classDef gw fill:#4a9eff,stroke:#2563eb,color:#fff
    classDef resp fill:#22c55e,stroke:#16a34a,color:#fff
    
    class E_SM,E_CH,E_SC,E_AB,E_NF,E_VC error
    class GW_RECV,GW_LOG,GW_WRAP,GW_CB gw
    class RESP resp
```

---

## 7. Resilience Patterns

```mermaid
graph TB
    subgraph "Circuit Breaker States"
        CB_CLOSED["🟢 CLOSED<br/>Normal operation"]
        CB_OPEN["🔴 OPEN<br/>Requests blocked"]
        CB_HALF["🟡 HALF-OPEN<br/>Testing recovery"]
    end

    CB_CLOSED -->|"5 failures in 10s"| CB_OPEN
    CB_OPEN -->|"30s timeout"| CB_HALF
    CB_HALF -->|"1 success"| CB_CLOSED
    CB_HALF -->|"1 failure"| CB_OPEN

    subgraph "Retry Strategy"
        R1["Attempt 1<br/>Immediate"]
        R2["Attempt 2<br/>+100ms"]
        R3["Attempt 3<br/>+200ms + jitter"]
        R4["Attempt 4<br/>+400ms + jitter"]
        FAIL["Return Error"]
    end

    R1 -->|"fail"| R2
    R2 -->|"fail"| R3
    R3 -->|"fail"| R4
    R4 -->|"fail"| FAIL

    subgraph "Bulkhead Isolation"
        BH_SM["SpecManager Pool<br/>Max: 50 connections"]
        BH_CH["Chronicle Pool<br/>Max: 30 connections"]
        BH_SC["Scout Pool<br/>Max: 40 connections"]
        BH_AB["AI-Bridge Pool<br/>Max: 20 connections"]
    end

    classDef closed fill:#22c55e,stroke:#16a34a,color:#fff
    classDef open fill:#ef4444,stroke:#dc2626,color:#fff
    classDef half fill:#eab308,stroke:#ca8a04,color:#000
    
    class CB_CLOSED closed
    class CB_OPEN open
    class CB_HALF half
```

---

## 8. Deployment Architecture

```mermaid
graph TB
    subgraph "Development Environment"
        DEV_FE["Frontend<br/>Vite Dev Server<br/>:5173"]
        DEV_BE["Backend Services<br/>go run ./cmd/*"]
        DEV_DB["SQLite Files<br/>./data/"]
    end

    subgraph "Production Environment"
        subgraph "Application Layer"
            PROD_FE["Static Assets<br/>Built React App"]
            PROD_GW["Gateway Binary<br/>./bin/gateway"]
            PROD_SVC["Service Binaries<br/>./bin/*"]
        end
        
        subgraph "Data Layer"
            PROD_DB["SQLite Databases<br/>/var/lib/specbuilder/"]
            PROD_LOG["Log Files<br/>/var/log/specbuilder/"]
            PROD_CFG["Config Files<br/>/etc/specbuilder/"]
        end
    end

    subgraph "Optional: Docker Deployment"
        D_NET["Docker Network<br/>specbuilder-net"]
        D_VOL["Docker Volumes<br/>specbuilder-data"]
    end

    DEV_FE --> DEV_BE
    DEV_BE --> DEV_DB

    PROD_FE --> PROD_GW
    PROD_GW --> PROD_SVC
    PROD_SVC --> PROD_DB
    PROD_SVC --> PROD_LOG

    classDef dev fill:#8b5cf6,stroke:#7c3aed,color:#fff
    classDef prod fill:#10b981,stroke:#059669,color:#fff
    classDef docker fill:#2563eb,stroke:#1d4ed8,color:#fff
    
    class DEV_FE,DEV_BE,DEV_DB dev
    class PROD_FE,PROD_GW,PROD_SVC,PROD_DB,PROD_LOG,PROD_CFG prod
    class D_NET,D_VOL docker
```

---

## 9. Service Dependency Graph

```mermaid
graph TD
    subgraph "Dependency Layers"
        L1["Layer 1: Foundation"]
        L2["Layer 2: Core Services"]
        L3["Layer 3: AI Services"]
        L4["Layer 4: Orchestration"]
        L5["Layer 5: Gateway"]
    end

    subgraph "Layer 1"
        PKG["pkg/*<br/>Shared Packages"]
        DB["SQLite<br/>Databases"]
    end

    subgraph "Layer 2"
        SM["SpecManager"]
        CH["Chronicle"]
        SC["Scout"]
    end

    subgraph "Layer 3"
        AB["AI-Bridge"]
        VC["Voice-CLI"]
    end

    subgraph "Layer 4"
        NF["Nexus-Flow"]
    end

    subgraph "Layer 5"
        GW["Gateway"]
    end

    PKG --> SM
    PKG --> CH
    PKG --> SC
    PKG --> AB
    PKG --> VC
    PKG --> NF
    PKG --> GW

    DB --> SM
    DB --> CH
    DB --> SC
    DB --> AB
    DB --> NF

    SM --> CH
    SM --> SC
    SC --> AB
    
    AB --> NF
    SM --> NF
    VC --> NF

    SM --> GW
    CH --> GW
    SC --> GW
    AB --> GW
    VC --> GW
    NF --> GW

    classDef l1 fill:#6b7280,stroke:#4b5563,color:#fff
    classDef l2 fill:#10b981,stroke:#059669,color:#fff
    classDef l3 fill:#8b5cf6,stroke:#7c3aed,color:#fff
    classDef l4 fill:#f59e0b,stroke:#d97706,color:#fff
    classDef l5 fill:#4a9eff,stroke:#2563eb,color:#fff
    
    class PKG,DB l1
    class SM,CH,SC l2
    class AB,VC l3
    class NF l4
    class GW l5
```

---

## 10. Quick Reference Table

| Service | Port | Protocol | Database Tier | Error Range | Key Dependencies |
|---------|------|----------|---------------|-------------|------------------|
| Gateway | 8080 | REST | - | 2xxx | All services |
| SpecManager | 8081 | REST | T2, T3 | 3xxx | Chronicle, Scout |
| AI-Bridge | 8082 | REST + SSE | T1, T4 | 6xxx | Scout, LLM Providers |
| Chronicle | 8083 | REST | T3 | 4xxx | - |
| Voice-CLI | 8084 | REST + WS | T4 | 11xxx | Nexus-Flow |
| Scout | 8093 | REST | T2, T3 | 5xxx | - |
| Nexus-Flow | 9000 | REST + WS | T3 | 10xxx | AI-Bridge, SpecManager |

---

## See Also

- [Microservices Overview](../14-microservices/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)
- [Error Management](../06-error-management/00-overview.md)
- [Integration Tests](../14-microservices/21-integration-tests.md)
