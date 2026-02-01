# Feature Dependency Diagram

**Version:** 1.0.0  
**Updated:** 2026-01-29

---

## Complete Feature Dependency Graph

This diagram shows all 25 feature folders and their interdependencies.

```mermaid
flowchart TB
    subgraph "Foundation Layer"
        AUTH[01-authentication]
        FILE[02-file-management]
        PROJ[03-project-management]
        ROUTE[12-routing-navigation]
        THEME[10-theme-system]
    end
    
    subgraph "Core Editor Layer"
        EDIT[04-spec-editor]
        VOICE[05-voice-input]
        HIST[07-history-system]
    end
    
    subgraph "AI Layer"
        AI[06-ai-integration]
        KNOW[09-knowledge-memory]
        CODEGEN[24-code-generation-system]
        ENHANCE[25-ai-enhancements]
    end
    
    subgraph "Quality Layer"
        CONSIST[08-consistency-checker]
        TEST[20-testing]
        MONITOR[17-monitoring]
    end
    
    subgraph "UI Layer"
        DASH[11-dashboard]
        ERR[13-error-ui]
        MOBILE[14-mobile-responsive]
        I18N[21-i18n]
    end
    
    subgraph "Infrastructure Layer"
        API[15-api-client]
        STATE[16-state-management]
        REAL[18-realtime]
        PERF[19-performance]
    end
    
    subgraph "CLI Tools Layer"
        CLI[22-golang-search-cli]
        BRUN[23-brun-cli]
    end
    
    %% Foundation dependencies
    AUTH --> FILE
    AUTH --> ROUTE
    FILE --> PROJ
    THEME --> DASH
    THEME --> MOBILE
    
    %% Core Editor dependencies
    PROJ --> EDIT
    EDIT --> VOICE
    EDIT --> HIST
    FILE --> HIST
    
    %% AI Layer dependencies
    EDIT --> AI
    VOICE --> AI
    AI --> KNOW
    AI --> CODEGEN
    AI --> ENHANCE
    KNOW --> CODEGEN
    KNOW --> ENHANCE
    
    %% Quality dependencies
    EDIT --> CONSIST
    AI --> CONSIST
    HIST --> CONSIST
    MONITOR --> TEST
    
    %% UI dependencies
    PROJ --> DASH
    AUTH --> DASH
    ERR --> DASH
    ROUTE --> DASH
    
    %% Infrastructure dependencies
    API --> STATE
    STATE --> REAL
    PERF --> REAL
    API --> AUTH
    
    %% CLI dependencies
    CLI --> AI
    CLI --> KNOW
    BRUN --> CODEGEN
    
    %% Cross-layer dependencies
    ENHANCE --> VOICE
    ENHANCE --> HIST
    I18N --> ERR
    MOBILE --> EDIT
    
    %% Styling
    style AUTH fill:#4ade80,stroke:#16a34a
    style AI fill:#60a5fa,stroke:#2563eb
    style CODEGEN fill:#f472b6,stroke:#db2777
    style ENHANCE fill:#c084fc,stroke:#9333ea
    style KNOW fill:#fbbf24,stroke:#d97706
    style CLI fill:#2dd4bf,stroke:#0d9488
```

---

## Dependency Matrix

### Layer Dependencies

| Layer | Depends On | Depended By |
|-------|------------|-------------|
| **Foundation** | - | All layers |
| **Core Editor** | Foundation | AI, Quality, UI |
| **AI** | Foundation, Core Editor | Quality, CLI Tools |
| **Quality** | Core Editor, AI | UI |
| **UI** | Foundation, Quality | - |
| **Infrastructure** | Foundation | AI, Quality |
| **CLI Tools** | AI | - |

---

## Feature-Level Dependencies

### 01-Authentication
```mermaid
graph LR
    AUTH[01-authentication]
    AUTH --> FILE[02-file-management]
    AUTH --> ROUTE[12-routing-navigation]
    AUTH --> API[15-api-client]
    AUTH --> DASH[11-dashboard]
```

### 06-AI-Integration
```mermaid
graph LR
    EDIT[04-spec-editor] --> AI[06-ai-integration]
    VOICE[05-voice-input] --> AI
    AI --> KNOW[09-knowledge-memory]
    AI --> CODEGEN[24-code-generation-system]
    AI --> ENHANCE[25-ai-enhancements]
    AI --> CONSIST[08-consistency-checker]
```

### 09-Knowledge-Memory
```mermaid
graph LR
    AI[06-ai-integration] --> KNOW[09-knowledge-memory]
    KNOW --> CODEGEN[24-code-generation-system]
    KNOW --> ENHANCE[25-ai-enhancements]
    CLI[22-golang-search-cli] --> KNOW
```

### 24-Code-Generation-System
```mermaid
graph LR
    AI[06-ai-integration] --> CODEGEN[24-code-generation-system]
    KNOW[09-knowledge-memory] --> CODEGEN
    BRUN[23-brun-cli] --> CODEGEN
```

### 25-AI-Enhancements
```mermaid
graph LR
    AI[06-ai-integration] --> ENHANCE[25-ai-enhancements]
    KNOW[09-knowledge-memory] --> ENHANCE
    ENHANCE --> VOICE[05-voice-input]
    ENHANCE --> HIST[07-history-system]
```

---

## Implementation Order

Based on the dependency graph, the recommended implementation order is:

### Phase 1: Foundation (No Dependencies)
1. `10-theme-system` - No upstream dependencies
2. `12-routing-navigation` - No upstream dependencies
3. `01-authentication` - Core security layer

### Phase 2: Core Infrastructure
4. `15-api-client` - Depends on auth
5. `16-state-management` - Depends on API
6. `02-file-management` - Depends on auth

### Phase 3: Project Core
7. `03-project-management` - Depends on file management
8. `04-spec-editor` - Depends on project
9. `07-history-system` - Depends on editor, files

### Phase 4: AI Foundation
10. `05-voice-input` - Depends on editor
11. `06-ai-integration` - Depends on editor, voice
12. `09-knowledge-memory` - Depends on AI

### Phase 5: Advanced AI
13. `24-code-generation-system` - Depends on AI, knowledge
14. `25-ai-enhancements` - Depends on AI, knowledge
15. `08-consistency-checker` - Depends on AI, history

### Phase 6: UI Polish
16. `11-dashboard` - Depends on auth, project, theme
17. `13-error-ui` - Depends on routing
18. `14-mobile-responsive` - Depends on theme, editor
19. `21-i18n` - Depends on error UI

### Phase 7: Performance & Quality
20. `17-monitoring` - Infrastructure ready
21. `18-realtime` - Depends on state, perf
22. `19-performance` - Optimization layer
23. `20-testing` - Depends on monitoring

### Phase 8: CLI Tools
24. `22-golang-search-cli` - Depends on AI, knowledge
25. `23-brun-cli` - Depends on code generation

---

## Critical Paths

### Shortest Path to MVP
```
auth → file → project → editor → AI
```

### AI Feature Critical Path
```
editor → AI → knowledge → code-generation → enhancements
```

### Full-Stack Critical Path
```
auth → API → state → realtime → dashboard
```

---

## See Also

- [Master Index](../00-master-index.md)
- [Folder Structure Diagram](./07-folder-structure-diagram.md)
- [Roadmap Overview](../08-roadmap-overview/00-overview.md)
