# Inconsistency Detection & Clarification Workflow

> **Version:** 1.0.0  
> **Status:** Active  
> **Last Updated:** 2026-01-28

---

## 1. Overview

This document visualizes the inconsistency detection and clarification workflow that analyzes generated artifacts for ambiguities, conflicts, and missing information. The system groups issues by phase, generates structured clarification questions, collects user answers, and triggers regeneration with refined context.

---

## 2. End-to-End Workflow

```mermaid
flowchart TD
    subgraph Input["📥 INPUT"]
        GA[Generated Artifact]
        CTX[Generation Context]
    end

    subgraph Detection["🔍 DETECTION STAGE"]
        RM[🧠 Reasoning Model]
        
        RM --> |Analyze| DET{Detect Issues}
        
        DET --> AMB[⚠️ Ambiguities]
        DET --> CON[❌ Conflicts]
        DET --> MIS[❓ Missing Info]
        DET --> INC[📋 Incomplete Sections]
        
        AMB & CON & MIS & INC --> IC[Issue Collector]
    end

    subgraph Grouping["📊 GROUPING STAGE"]
        IC --> PG[Phase Grouper]
        
        PG --> P1["🔴 Phase 1: CRITICAL<br/>─────────────<br/>Blocking issues<br/>Missing core requirements<br/>Security concerns"]
        
        PG --> P2["🟠 Phase 2: CONFLICT<br/>─────────────<br/>Contradictory requirements<br/>Mutually exclusive choices<br/>Architecture decisions"]
        
        PG --> P3["🟡 Phase 3: CLARIFICATION<br/>─────────────<br/>Ambiguous language<br/>Undefined terms<br/>Scope questions"]
        
        PG --> P4["🟢 Phase 4: ENHANCEMENT<br/>─────────────<br/>Optional features<br/>Nice-to-haves<br/>Future considerations"]
    end

    subgraph QuestionGen["❓ QUESTION GENERATION"]
        P1 & P2 & P3 & P4 --> QG[Question Generator]
        
        QG --> |Format for UI| QS[Question Set]
        
        subgraph QuestionTypes["Question Types"]
            RB[🔘 Radio (single choice)]
            CB[☑️ Checkbox (multi-select)]
            TXT[📝 Text Input]
            NUM[🔢 Number Input]
        end
        
        QG --> QuestionTypes
    end

    subgraph UserInput["👤 USER INPUT"]
        QS --> UI[Question Wizard UI]
        UI --> |User responds| ANS[Collected Answers]
        
        UI --> |Skip phase| SKIP[Mark as Deferred]
        UI --> |Cancel| CANCEL[Abort Workflow]
    end

    subgraph Regeneration["🔄 REGENERATION"]
        ANS --> RC[Refinement Compiler]
        RC --> |Merge with context| RCTX[Refined Context]
        
        RCTX --> RG[🧠 Regenerate Artifact]
        RG --> NEW[New Artifact Version]
        
        NEW --> VALIDATE{Re-validate}
        
        VALIDATE --> |More issues| Detection
        VALIDATE --> |Clean| DONE[✅ Complete]
    end

    Input --> Detection
    SKIP --> Regeneration
    CANCEL --> ABORT[❌ Workflow Aborted]

    style Input fill:#e3f2fd,stroke:#1976d2
    style Detection fill:#fff3e0,stroke:#f57c00
    style Grouping fill:#e8f5e9,stroke:#388e3c
    style QuestionGen fill:#fce4ec,stroke:#c2185b
    style UserInput fill:#f3e5f5,stroke:#7b1fa2
    style Regeneration fill:#e0f7fa,stroke:#00838f
```

---

## 3. Detection Analysis Sequence

```mermaid
sequenceDiagram
    autonumber
    
    participant API as 🔌 API
    participant DET as 🔍 Detector
    participant LLM as 🧠 Reasoning Model
    participant DB as 🗄️ SQLite

    rect rgb(255, 243, 224)
        Note over API,LLM: Issue Detection
        API->>DET: analyzeArtifact(artifact, context)
        
        DET->>LLM: POST /analyze
        Note right of DET: System prompt:<br/>"Analyze for ambiguities,<br/>conflicts, missing info..."
        
        LLM->>LLM: Chain-of-thought analysis
        LLM-->>DET: AnalysisResult { issues[] }
    end

    rect rgb(232, 245, 233)
        Note over DET: Issue Classification
        loop For each issue
            DET->>DET: Classify severity
            DET->>DET: Assign phase
            DET->>DET: Generate question template
        end
    end

    rect rgb(252, 228, 236)
        Note over DET,DB: Persist Report
        DET->>DB: INSERT INTO InconsistencyReports
        DB-->>DET: reportId
        
        loop For each issue
            DET->>DB: INSERT INTO ClarificationQuestions
            DB-->>DET: questionId
        end
    end

    DET-->>API: InconsistencyReport { phases[], questions[] }
```

---

## 4. Phase Classification Matrix

```mermaid
flowchart LR
    subgraph Classification["Issue Classification"]
        direction TB
        
        subgraph Critical["🔴 CRITICAL (Phase 1)"]
            C1["Missing authentication requirements"]
            C2["Undefined data ownership"]
            C3["Security vulnerability detected"]
            C4["No error handling specified"]
            C5["Missing required field definition"]
        end
        
        subgraph Conflict["🟠 CONFLICT (Phase 2)"]
            CO1["REST vs GraphQL mentioned"]
            CO2["Sync vs async processing"]
            CO3["Single-tenant vs multi-tenant"]
            CO4["SQL vs NoSQL references"]
            CO5["Monolith vs microservices"]
        end
        
        subgraph Clarification["🟡 CLARIFICATION (Phase 3)"]
            CL1["Ambiguous 'user' term"]
            CL2["Undefined 'large' threshold"]
            CL3["Unclear permission scope"]
            CL4["Vague timeline reference"]
            CL5["Unspecified format"]
        end
        
        subgraph Enhancement["🟢 ENHANCEMENT (Phase 4)"]
            E1["Optional analytics"]
            E2["Future i18n support"]
            E3["Potential caching layer"]
            E4["Nice-to-have notifications"]
            E5["Suggested optimizations"]
        end
    end

    Critical --> |Priority: 1| BLOCK[⛔ Blocks generation]
    Conflict --> |Priority: 2| DECIDE[⚖️ Requires decision]
    Clarification --> |Priority: 3| DEFINE[📖 Needs definition]
    Enhancement --> |Priority: 4| DEFER[📅 Can defer]

    style Critical fill:#ffcdd2,stroke:#d32f2f
    style Conflict fill:#ffe0b2,stroke:#f57c00
    style Clarification fill:#fff9c4,stroke:#fbc02d
    style Enhancement fill:#c8e6c9,stroke:#388e3c
```

---

## 5. Question Generation Flow

```mermaid
flowchart TD
    subgraph IssueInput["Issue Input"]
        ISS["Issue: 'Both REST and GraphQL<br/>mentioned for API layer'"]
        META["Metadata:<br/>phase: conflict<br/>severity: high<br/>location: Section 3.2"]
    end

    subgraph TemplateSelection["Template Selection"]
        ISS --> TS{Issue Type?}
        
        TS --> |binary choice| T1["Radio Template"]
        TS --> |multiple options| T2["Multi-select Template"]
        TS --> |open-ended| T3["Text Input Template"]
        TS --> |numeric| T4["Number Input Template"]
        TS --> |confirmation| T5["Yes/No Template"]
    end

    subgraph QuestionComposition["Question Composition"]
        T1 --> QC[Question Composer]
        T2 --> QC
        T3 --> QC
        T4 --> QC
        T5 --> QC
        
        QC --> |Add context| CTX["Include original text excerpt"]
        QC --> |Add options| OPT["Generate choice options"]
        QC --> |Add help| HELP["Add explanation tooltip"]
    end

    subgraph Output["Generated Question"]
        CTX & OPT & HELP --> FINAL["
        ┌─────────────────────────────────────────┐
        │ 🟠 CONFLICT                              │
        ├─────────────────────────────────────────┤
        │ You mentioned both REST and GraphQL     │
        │ for the API layer. Which should be      │
        │ the primary approach?                   │
        │                                         │
        │ ○ REST API (simpler, widely supported)  │
        │ ○ GraphQL (flexible queries, typed)     │
        │ ○ Both (REST primary, GraphQL optional) │
        │                                         │
        │ ℹ️ This affects endpoint design and     │
        │    client SDK generation.               │
        └─────────────────────────────────────────┘
        "]
    end

    IssueInput --> TemplateSelection
    TemplateSelection --> QuestionComposition
    QuestionComposition --> Output

    style IssueInput fill:#e3f2fd,stroke:#1976d2
    style TemplateSelection fill:#fff3e0,stroke:#f57c00
    style QuestionComposition fill:#e8f5e9,stroke:#388e3c
    style Output fill:#fce4ec,stroke:#c2185b
```

---

## 6. Question UI State Machine

```mermaid
stateDiagram-v2
    [*] --> Loading: startWizard()

    state "Loading" as Loading
    state "DisplayPhase" as DisplayPhase
    state "AwaitingAnswer" as AwaitingAnswer
    state "ValidatingAnswer" as ValidatingAnswer
    state "PhaseComplete" as PhaseComplete
    state "AllPhasesComplete" as AllPhasesComplete
    state "Submitting" as Submitting
    state "Complete" as Complete
    state "Error" as Error

    Loading --> DisplayPhase: questionsLoaded
    Loading --> Error: loadFailed

    DisplayPhase --> AwaitingAnswer: showQuestion(n)
    
    AwaitingAnswer --> ValidatingAnswer: userResponds
    AwaitingAnswer --> DisplayPhase: skipQuestion
    AwaitingAnswer --> [*]: cancelWizard
    
    ValidatingAnswer --> AwaitingAnswer: validationFailed
    ValidatingAnswer --> AwaitingAnswer: hasMoreQuestions
    ValidatingAnswer --> PhaseComplete: phaseQuestionsComplete
    
    PhaseComplete --> DisplayPhase: hasMorePhases
    PhaseComplete --> AllPhasesComplete: allPhasesComplete
    PhaseComplete --> Submitting: skipRemainingPhases
    
    AllPhasesComplete --> Submitting: submit()
    
    Submitting --> Complete: success
    Submitting --> Error: submitFailed
    
    Complete --> [*]: close()
    Error --> Loading: retry()
    Error --> [*]: dismiss()

    note right of DisplayPhase
        Shows phase header with
        progress indicator
        (Phase 2 of 4)
    end note

    note right of AwaitingAnswer
        Question displayed with
        appropriate input control
        (radio, checkbox, text)
    end note

    note right of PhaseComplete
        "Phase complete" animation
        Option to review answers
        before proceeding
    end note
```

---

## 7. Answer Collection & Validation

```mermaid
sequenceDiagram
    autonumber
    
    participant UI as 🖥️ Question Wizard
    participant VAL as ✅ Validator
    participant API as 🔌 API
    participant DB as 🗄️ SQLite

    rect rgb(243, 229, 245)
        Note over UI: User Interaction Loop
        
        loop For each question
            UI->>UI: Display question
            UI->>UI: User selects/enters answer
            
            UI->>VAL: validate(answer, question.constraints)
            
            alt Validation fails
                VAL-->>UI: { valid: false, error: "..." }
                UI->>UI: Show inline error
            else Validation passes
                VAL-->>UI: { valid: true }
                UI->>UI: Mark question complete
                UI->>UI: Advance to next question
            end
        end
    end

    rect rgb(232, 245, 233)
        Note over UI,DB: Submit Answers
        UI->>API: POST /api/clarifications/submit
        Note right of UI: { reportId, answers[] }
        
        API->>DB: BEGIN TRANSACTION
        
        loop For each answer
            API->>DB: INSERT INTO ClarificationAnswers
        end
        
        API->>DB: UPDATE InconsistencyReports<br/>SET status = 'answered'
        
        API->>DB: COMMIT
        
        API-->>UI: { success: true, answerId: "..." }
    end
```

**Validation Rules:**

| Question Type | Validation |
|---------------|------------|
| Radio | Exactly one option selected |
| Checkbox | At least one option (if required) |
| Text | Min/max length, pattern match |
| Number | Min/max range, integer/float |
| Yes/No | Boolean value |

---

## 8. Regeneration Flow

```mermaid
flowchart TD
    subgraph AnswerInput["Collected Answers"]
        A1["Q1: REST API (primary)"]
        A2["Q2: Yes, require authentication"]
        A3["Q3: 100 items per page"]
        A4["Q4: admin, editor, viewer roles"]
    end

    subgraph Compilation["Answer Compilation"]
        A1 & A2 & A3 & A4 --> RC[Refinement Compiler]
        
        RC --> |Extract decisions| DEC["Decisions:<br/>- API: REST<br/>- Auth: Required<br/>- Pagination: 100<br/>- Roles: 3-tier"]
        
        RC --> |Generate constraints| CON["Constraints:<br/>- No GraphQL endpoints<br/>- All routes protected<br/>- Max page size 100"]
        
        RC --> |Update context| CTX["Updated Context:<br/>Original + Decisions + Constraints"]
    end

    subgraph PromptEnhancement["Prompt Enhancement"]
        CTX --> PE[Prompt Enhancer]
        
        PE --> |Inject clarifications| INJ["
        [CLARIFICATIONS]
        The following decisions were made:
        1. Use REST API (not GraphQL)
        2. Require authentication for all endpoints
        3. Paginate with 100 items max
        4. Implement 3 roles: admin, editor, viewer
        
        [CONSTRAINTS]
        - Do NOT generate GraphQL schemas
        - All endpoints require auth middleware
        - Include role-based access control
        "]
    end

    subgraph Regeneration["Artifact Regeneration"]
        INJ --> RG[🧠 Reasoning Model]
        RG --> |Generate with constraints| NEW[New Artifact v2]
        
        NEW --> DIFF[📊 Diff View]
        DIFF --> |Show changes| UI[User Review]
        
        UI --> |Approve| SAVE[💾 Save as New Version]
        UI --> |Request changes| EDIT[✏️ Manual Edit]
        UI --> |Reject| DISCARD[🗑️ Discard]
    end

    AnswerInput --> Compilation
    Compilation --> PromptEnhancement
    PromptEnhancement --> Regeneration

    style AnswerInput fill:#e3f2fd,stroke:#1976d2
    style Compilation fill:#fff3e0,stroke:#f57c00
    style PromptEnhancement fill:#e8f5e9,stroke:#388e3c
    style Regeneration fill:#fce4ec,stroke:#c2185b
```

---

## 9. Database Schema

```mermaid
erDiagram
    InstructionRuns ||--o{ InconsistencyReports : "generates"
    InconsistencyReports ||--o{ ClarificationQuestions : "contains"
    ClarificationQuestions ||--o| ClarificationAnswers : "answered by"
    InconsistencyReports ||--o{ RegenerationEvents : "triggers"
    Users ||--o{ ClarificationAnswers : "provides"

    InstructionRuns {
        string id PK "UUID"
        string projectId FK
        string status "pending|analyzing|clarifying|generating|complete"
        text originalContent "User input"
        text generatedArtifact "First generation"
        datetime createdAt
    }

    InconsistencyReports {
        string id PK "UUID"
        string runId FK "→ InstructionRuns.id"
        string status "pending|answered|regenerated|dismissed"
        integer totalIssues "Count of detected issues"
        integer criticalCount "Phase 1 issues"
        integer conflictCount "Phase 2 issues"
        integer clarificationCount "Phase 3 issues"
        integer enhancementCount "Phase 4 issues"
        datetime createdAt
        datetime answeredAt
    }

    ClarificationQuestions {
        string id PK "UUID"
        string reportId FK "→ InconsistencyReports.id"
        string phase "critical|conflict|clarification|enhancement"
        integer phaseOrder "Order within phase"
        string questionType "radio|checkbox|text|number|yesno"
        text questionText "The question"
        text options "JSON array of options"
        text constraints "JSON validation rules"
        text excerpt "Original text reference"
        text helpText "Tooltip explanation"
        boolean required "Must answer"
        boolean skipped "User skipped"
        datetime createdAt
    }

    ClarificationAnswers {
        string id PK "UUID"
        string questionId FK "→ ClarificationQuestions.id"
        string userId FK "→ Users.id"
        text answerValue "Selected option(s) or input"
        text answerNormalized "Processed for prompt"
        datetime answeredAt
    }

    RegenerationEvents {
        string id PK "UUID"
        string reportId FK "→ InconsistencyReports.id"
        string previousArtifactId "Before regeneration"
        string newArtifactId "After regeneration"
        text refinementPrompt "Injected clarifications"
        string status "pending|success|failed"
        text errorMessage "If failed"
        datetime triggeredAt
        datetime completedAt
    }

    Users {
        string id PK
        string email
    }
```

---

## 10. Iteration Loop

```mermaid
flowchart TD
    START[🚀 Initial Generation] --> GEN1[Artifact v1]
    
    GEN1 --> DET1{Detect Issues}
    
    DET1 --> |Issues found| Q1[❓ Questions Round 1]
    DET1 --> |No issues| DONE[✅ Complete]
    
    Q1 --> |User answers| RG1[🔄 Regenerate]
    Q1 --> |User skips all| ACCEPT1[Accept with warnings]
    
    RG1 --> GEN2[Artifact v2]
    
    GEN2 --> DET2{Re-detect Issues}
    
    DET2 --> |New issues| Q2[❓ Questions Round 2]
    DET2 --> |Same issues| STUCK[⚠️ Stuck - Manual intervention]
    DET2 --> |No issues| DONE
    
    Q2 --> |User answers| RG2[🔄 Regenerate]
    Q2 --> |Max iterations| FORCE[Force complete with notes]
    
    RG2 --> GEN3[Artifact v3]
    
    GEN3 --> DET3{Final check}
    DET3 --> |Issues| MANUAL[📝 Manual review required]
    DET3 --> |Clean| DONE

    ACCEPT1 --> DONE
    FORCE --> DONE
    MANUAL --> DONE
    STUCK --> MANUAL

    style START fill:#e3f2fd,stroke:#1976d2
    style DONE fill:#c8e6c9,stroke:#388e3c
    style STUCK fill:#ffcdd2,stroke:#d32f2f
    style MANUAL fill:#fff9c4,stroke:#fbc02d
```

**Iteration Limits:**

| Metric | Limit | Action When Exceeded |
|--------|-------|---------------------|
| Max iterations | 3 | Force complete with warnings |
| Questions per phase | 10 | Group remaining as "Other" |
| Total questions | 25 | Prioritize critical only |
| Time per question | 5 min | Auto-skip with default |
| Session timeout | 30 min | Save progress, allow resume |

---

## 11. API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/inconsistencies/analyze` | POST | Analyze artifact for issues |
| `/api/inconsistencies/:reportId` | GET | Get report with questions |
| `/api/inconsistencies/:reportId/questions` | GET | List questions by phase |
| `/api/clarifications/submit` | POST | Submit answers for a report |
| `/api/clarifications/:reportId/answers` | GET | Get submitted answers |
| `/api/regeneration/trigger` | POST | Trigger regeneration with answers |
| `/api/regeneration/:eventId/status` | GET | Check regeneration status |
| `/api/regeneration/:eventId/diff` | GET | Get before/after diff |

---

## 12. Error Handling

| Error | Code | Resolution |
|-------|------|------------|
| Analysis timeout | 5001 | Retry with smaller context |
| No issues detected | 5002 | Proceed to completion |
| Question generation failed | 5003 | Fall back to generic questions |
| Invalid answer format | 5004 | Show validation error |
| Regeneration failed | 5005 | Offer manual edit option |
| Max iterations exceeded | 5006 | Force complete with notes |
| Session expired | 5007 | Allow answer resume |

---

## 13. Cross-References

- **Instruction System:** [03-instruction-system.md](../05-features/06-ai-integration/03-instruction-system.md)
- **Instruction History:** [04-instruction-history.md](../05-features/06-ai-integration/04-instruction-history.md)
- **AI Integration:** [01-ai-integration.md](../05-features/06-ai-integration/01-ai-integration.md)
- **Instruction Builder Pipeline:** [03-instruction-builder-pipeline.md](./03-instruction-builder-pipeline.md)
- **Prompt Preset Layering:** [04-prompt-preset-layering.md](./04-prompt-preset-layering.md)
- **Instruction Builder UI:** [09-instruction-builder-ui.md](../05-features/06-ai-integration/09-instruction-builder-ui.md)
- **Error UI:** [00-overview.md](../05-features/13-error-ui/00-overview.md)
