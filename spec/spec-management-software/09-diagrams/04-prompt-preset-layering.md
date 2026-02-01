# Prompt Preset Layering System

> **Version:** 1.0.0  
> **Status:** Active  
> **Last Updated:** 2026-01-28

---

## 1. Overview

This document visualizes the prompt preset layering system that composes final prompts from base presets, user overrides, and runtime context. The system enables customization while maintaining consistency through a structured inheritance model.

---

## 2. Layering Architecture

```mermaid
flowchart TB
    subgraph Repository["📁 Repository Layer (Prompts/)"]
        direction TB
        
        subgraph Categories["Content Type Categories"]
            IDEA["idea/"]
            FEAT["feature/"]
            TASK["task/"]
            GUIDE["codingGuideline/"]
            INST["instruction/"]
        end
        
        subgraph PresetFiles["Preset Files"]
            IB["base.md<br/>─────────<br/>isDefault: true"]
            IA["alternative.md<br/>─────────<br/>isDefault: false"]
            FB["base.md"]
            TB["base.md"]
            GB["base.md"]
            INB["base.md"]
        end
        
        IDEA --> IB & IA
        FEAT --> FB
        TASK --> TB
        GUIDE --> GB
        INST --> INB
    end

    subgraph Database["🗄️ Database Layer (SQLite)"]
        direction TB
        
        PP[(PromptPresets)]
        PPV[(PromptPresetVersions)]
        UPO[(UserPromptOverrides)]
        
        PP --> |versions| PPV
        PP --> |customizations| UPO
    end

    subgraph Runtime["⚡ Runtime Layer"]
        direction TB
        
        CTX[Runtime Context]
        UC[User Content]
        
        CTX --> |project, type| LOAD
        UC --> |proofread text| COMPOSE
    end

    subgraph Composition["🔧 Composition Engine"]
        direction TB
        
        LOAD[Load Base Preset]
        MERGE[Merge Override Layer]
        INJECT[Inject Variables]
        COMPOSE[Compose Final Prompt]
        
        LOAD --> MERGE
        MERGE --> INJECT
        INJECT --> COMPOSE
    end

    Repository --> |seed on startup| Database
    Database --> |query by type| Runtime
    Runtime --> Composition

    COMPOSE --> FP[📝 Final Prompt]
    FP --> LLM[🧠 LLM API]

    style Repository fill:#e3f2fd,stroke:#1976d2
    style Database fill:#fff3e0,stroke:#f57c00
    style Runtime fill:#e8f5e9,stroke:#388e3c
    style Composition fill:#fce4ec,stroke:#c2185b
```

---

## 3. Layer Inheritance Model

```mermaid
flowchart LR
    subgraph L1["Layer 1: Base Preset"]
        BP["📄 Base Prompt<br/>──────────────<br/>• System instructions<br/>• Output format rules<br/>• Quality guidelines<br/>• Required sections"]
    end

    subgraph L2["Layer 2: User Override"]
        UO["✏️ User Override<br/>──────────────<br/>• Project-specific rules<br/>• Custom sections<br/>• Tone adjustments<br/>• Additional constraints"]
    end

    subgraph L3["Layer 3: Runtime Variables"]
        RV["🔄 Runtime Variables<br/>──────────────<br/>• {{projectName}}<br/>• {{contentType}}<br/>• {{timestamp}}<br/>• {{userName}}"]
    end

    subgraph L4["Layer 4: User Content"]
        UC["📝 User Content<br/>──────────────<br/>• Proofread input<br/>• Detected type<br/>• Classification tags"]
    end

    L1 --> |"append"| L2
    L2 --> |"interpolate"| L3
    L3 --> |"append"| L4

    L4 --> FP["✅ Final Prompt"]

    style L1 fill:#bbdefb,stroke:#1976d2
    style L2 fill:#c8e6c9,stroke:#388e3c
    style L3 fill:#fff9c4,stroke:#fbc02d
    style L4 fill:#f8bbd9,stroke:#c2185b
```

---

## 4. Detailed Composition Sequence

```mermaid
sequenceDiagram
    autonumber
    
    participant UI as 🖥️ Frontend
    participant API as 🔌 API
    participant PS as 📋 PresetService
    participant DB as 🗄️ SQLite
    participant CE as 🔧 CompositionEngine
    participant LLM as 🧠 LLM

    rect rgb(227, 242, 253)
        Note over UI,API: User Initiates Generation
        UI->>API: POST /api/instructions/generate
        Note right of UI: { projectId, contentType, content }
    end

    rect rgb(255, 243, 224)
        Note over API,DB: Load Base Preset
        API->>PS: loadPreset(contentType, projectId)
        PS->>DB: SELECT * FROM PromptPresets<br/>WHERE contentType = ? AND isDefault = true
        DB-->>PS: BasePreset { id, content, variables }
        
        PS->>DB: SELECT * FROM PromptPresetVersions<br/>WHERE presetId = ? ORDER BY version DESC LIMIT 1
        DB-->>PS: LatestVersion { content, changelog }
    end

    rect rgb(232, 245, 233)
        Note over PS,DB: Load User Override
        PS->>DB: SELECT * FROM UserPromptOverrides<br/>WHERE presetId = ? AND projectId = ?
        
        alt Override Exists
            DB-->>PS: UserOverride { customContent, mode }
        else No Override
            DB-->>PS: null
        end
        
        PS-->>API: { basePreset, userOverride }
    end

    rect rgb(252, 228, 236)
        Note over API,CE: Compose Final Prompt
        API->>CE: compose(basePreset, userOverride, context)
        
        CE->>CE: Step 1: Start with base content
        Note right of CE: baseContent = preset.content
        
        CE->>CE: Step 2: Apply override mode
        Note right of CE: if APPEND: result = base + override<br/>if REPLACE: result = override<br/>if PREPEND: result = override + base
        
        CE->>CE: Step 3: Interpolate variables
        Note right of CE: Replace {{projectName}}, {{timestamp}}, etc.
        
        CE->>CE: Step 4: Append user content
        Note right of CE: result += "\n\n---\n\n" + userContent
        
        CE-->>API: FinalPrompt
    end

    rect rgb(243, 229, 245)
        Note over API,LLM: Execute Generation
        API->>LLM: POST /v1/chat/completions
        Note right of API: { messages: [system: FinalPrompt] }
        LLM-->>API: GeneratedSpec
        API-->>UI: { success: true, artifact: {...} }
    end
```

---

## 5. Override Modes

```mermaid
flowchart TD
    subgraph Modes["Override Application Modes"]
        direction TB
        
        subgraph APPEND["Mode: APPEND (Default)"]
            A1["Base Preset Content"]
            A2["───────────────"]
            A3["User Override Content"]
            A1 --> A2 --> A3
        end
        
        subgraph PREPEND["Mode: PREPEND"]
            P1["User Override Content"]
            P2["───────────────"]
            P3["Base Preset Content"]
            P1 --> P2 --> P3
        end
        
        subgraph REPLACE["Mode: REPLACE"]
            R1["User Override Content<br/>(Base Ignored)"]
        end
        
        subgraph MERGE["Mode: MERGE"]
            M1["Base: Section A"]
            M2["Override: Section A (wins)"]
            M3["Base: Section B"]
            M4["Override: Section C (new)"]
            M1 -.-> |replaced by| M2
            M3 --> M3
            M4 --> M4
        end
    end

    style APPEND fill:#c8e6c9,stroke:#388e3c
    style PREPEND fill:#fff9c4,stroke:#fbc02d
    style REPLACE fill:#ffcdd2,stroke:#d32f2f
    style MERGE fill:#e1bee7,stroke:#7b1fa2
```

---

## 6. Preset File Structure

```mermaid
flowchart TD
    subgraph FileFormat["📄 Preset File Format"]
        direction TB
        
        FM["---<br/>name: Feature Spec Generator<br/>description: Creates feature specifications<br/>isDefault: true<br/>version: 1.0.0<br/>variables:<br/>  - projectName<br/>  - contentType<br/>---"]
        
        CONTENT["# System Instructions<br/><br/>You are a specification writer...<br/><br/>## Output Format<br/><br/>Generate the following sections:<br/>1. Overview<br/>2. User Stories<br/>3. Acceptance Criteria<br/><br/>## Quality Guidelines<br/><br/>- Be specific and actionable<br/>- Include edge cases<br/>- Reference existing specs<br/><br/>## Variables<br/><br/>Project: {{projectName}}<br/>Type: {{contentType}}"]
        
        FM --> CONTENT
    end

    subgraph Parsed["🔍 Parsed Result"]
        META["Metadata<br/>─────────<br/>name: string<br/>description: string<br/>isDefault: boolean<br/>version: string<br/>variables: string[]"]
        
        BODY["Content<br/>─────────<br/>Markdown body<br/>with {{variables}}"]
    end

    FileFormat --> |YAML parser| META
    FileFormat --> |Extract body| BODY

    style FileFormat fill:#e3f2fd,stroke:#1976d2
    style Parsed fill:#e8f5e9,stroke:#388e3c
```

---

## 7. Database Schema

```mermaid
erDiagram
    PromptPresets ||--o{ PromptPresetVersions : "has versions"
    PromptPresets ||--o{ UserPromptOverrides : "has overrides"
    Projects ||--o{ UserPromptOverrides : "scopes"
    Users ||--o{ UserPromptOverrides : "creates"

    PromptPresets {
        string id PK "UUID"
        string contentType "idea|feature|task|codingGuideline|instruction"
        string name "Display name"
        string description "Purpose description"
        boolean isDefault "Only one per type"
        string sourceFilePath "Prompts/feature/base.md"
        string currentVersion "1.0.0"
        datetime createdAt
        datetime updatedAt
    }

    PromptPresetVersions {
        string id PK "UUID"
        string presetId FK "→ PromptPresets.id"
        string version "Semver: 1.0.0"
        text content "Full prompt content"
        text variables "JSON array of variable names"
        text changelog "What changed"
        datetime createdAt
    }

    UserPromptOverrides {
        string id PK "UUID"
        string presetId FK "→ PromptPresets.id"
        string projectId FK "→ Projects.id (nullable for global)"
        string userId FK "→ Users.id"
        string mode "APPEND|PREPEND|REPLACE|MERGE"
        text customContent "User's additional/replacement content"
        boolean isActive "Can be toggled off"
        datetime createdAt
        datetime updatedAt
    }

    Projects {
        string id PK
        string name
    }

    Users {
        string id PK
        string email
    }
```

---

## 8. Seeding Flow

```mermaid
flowchart TD
    subgraph Startup["🚀 Application Startup"]
        INIT[Initialize Seeder]
    end

    subgraph Scan["📂 File System Scan"]
        SCAN[Scan Prompts/ directory]
        PARSE[Parse each .md file]
        EXTRACT[Extract frontmatter + content]
    end

    subgraph Validate["✅ Validation"]
        V1{Has required fields?}
        V2{Valid contentType?}
        V3{Single default per type?}
        
        V1 --> |No| SKIP[Skip with warning]
        V1 --> |Yes| V2
        V2 --> |No| SKIP
        V2 --> |Yes| V3
        V3 --> |Conflict| RESOLVE[Use first found]
        V3 --> |OK| UPSERT
    end

    subgraph Persist["💾 Database Persist"]
        UPSERT[INSERT OR IGNORE<br/>into PromptPresets]
        VERSION[Create initial version<br/>in PromptPresetVersions]
        
        UPSERT --> VERSION
    end

    Startup --> Scan
    INIT --> SCAN
    SCAN --> PARSE
    PARSE --> EXTRACT
    EXTRACT --> Validate
    RESOLVE --> UPSERT

    VERSION --> DONE[✅ Seeding Complete]

    style Startup fill:#e3f2fd,stroke:#1976d2
    style Scan fill:#fff3e0,stroke:#f57c00
    style Validate fill:#e8f5e9,stroke:#388e3c
    style Persist fill:#fce4ec,stroke:#c2185b
```

---

## 9. Variable Interpolation

```mermaid
flowchart LR
    subgraph Input["Input Template"]
        TPL["You are writing specs for {{projectName}}.<br/><br/>Content type: {{contentType}}<br/>Generated: {{timestamp}}<br/>Author: {{userName}}"]
    end

    subgraph Context["Runtime Context"]
        CTX["projectName: 'Exam Manager'<br/>contentType: 'feature'<br/>timestamp: '2026-01-28T10:30:00Z'<br/>userName: 'alice@example.com'"]
    end

    subgraph Engine["Interpolation Engine"]
        RE["Regex: /\\{\\{(\\w+)\\}\\}/g"]
        REPLACE["Replace with context values"]
    end

    subgraph Output["Final Output"]
        OUT["You are writing specs for Exam Manager.<br/><br/>Content type: feature<br/>Generated: 2026-01-28T10:30:00Z<br/>Author: alice@example.com"]
    end

    Input --> Engine
    Context --> Engine
    Engine --> Output

    style Input fill:#ffecb3,stroke:#ff8f00
    style Context fill:#c8e6c9,stroke:#388e3c
    style Engine fill:#e1bee7,stroke:#7b1fa2
    style Output fill:#b3e5fc,stroke:#0288d1
```

**Supported Variables:**

| Variable | Source | Example |
|----------|--------|---------|
| `{{projectName}}` | Project.name | "Exam Manager" |
| `{{contentType}}` | Classification result | "feature" |
| `{{timestamp}}` | Current ISO8601 | "2026-01-28T10:30:00Z" |
| `{{userName}}` | Current user email | "alice@example.com" |
| `{{userId}}` | Current user ID | "usr_abc123" |
| `{{date}}` | Current date | "2026-01-28" |
| `{{specVersion}}` | From config | "1.0.0" |

---

## 10. Final Prompt Structure

```mermaid
flowchart TD
    subgraph FinalPrompt["📝 Final Prompt Assembly"]
        direction TB
        
        S1["┌─────────────────────────────────────┐<br/>│ SYSTEM INSTRUCTIONS (from base)     │<br/>│ ─────────────────────────────────── │<br/>│ You are a specification writer...   │<br/>│ Follow these quality guidelines...  │<br/>└─────────────────────────────────────┘"]
        
        S2["┌─────────────────────────────────────┐<br/>│ PROJECT CUSTOMIZATIONS (override)   │<br/>│ ─────────────────────────────────── │<br/>│ For this project, also include:     │<br/>│ - Database schema changes           │<br/>│ - API endpoint specifications       │<br/>└─────────────────────────────────────┘"]
        
        S3["┌─────────────────────────────────────┐<br/>│ RUNTIME CONTEXT (interpolated)      │<br/>│ ─────────────────────────────────── │<br/>│ Project: Exam Manager                │<br/>│ Type: feature                        │<br/>│ Date: 2026-01-28                     │<br/>└─────────────────────────────────────┘"]
        
        S4["┌─────────────────────────────────────┐<br/>│ USER CONTENT (proofread input)      │<br/>│ ─────────────────────────────────── │<br/>│ Create a feature for bulk importing │<br/>│ exam questions from CSV files with  │<br/>│ validation and error reporting.     │<br/>└─────────────────────────────────────┘"]
        
        S1 --> S2 --> S3 --> S4
    end

    S4 --> LLM["🧠 Send to LLM"]
    LLM --> SPEC["📄 Generated Specification"]

    style FinalPrompt fill:#e8f5e9,stroke:#388e3c
```

---

## 11. API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/presets` | GET | List all presets (optionally filter by contentType) |
| `/api/presets/:id` | GET | Get preset with versions |
| `/api/presets/:id/versions` | GET | List all versions of a preset |
| `/api/presets/:id/override` | POST | Create/update user override for a preset |
| `/api/presets/:id/override` | DELETE | Remove user override |
| `/api/presets/compose` | POST | Preview composed prompt without execution |
| `/api/presets/seed` | POST | Trigger re-seeding from filesystem (admin) |

---

## 12. Error Handling

| Error | Code | Resolution |
|-------|------|------------|
| Preset not found | 4001 | Fall back to system default |
| Invalid content type | 4002 | Reject with supported types list |
| Variable not in context | 4003 | Leave placeholder or use empty string |
| Override syntax error | 4004 | Reject with parse error details |
| Circular override reference | 4005 | Detect and reject |
| Version conflict | 4006 | Use latest version with warning |

---

## 13. Cross-References

- **Instruction System:** [03-instruction-system.md](../05-features/06-ai-integration/03-instruction-system.md)
- **Presets Guidelines:** [02-presets-guidelines.md](../05-features/06-ai-integration/02-presets-guidelines.md)
- **AI Integration:** [01-ai-integration.md](../05-features/06-ai-integration/01-ai-integration.md)
- **Instruction Builder Pipeline:** [03-instruction-builder-pipeline.md](./03-instruction-builder-pipeline.md)
- **Instruction Builder UI:** [09-instruction-builder-ui.md](../05-features/06-ai-integration/09-instruction-builder-ui.md)
