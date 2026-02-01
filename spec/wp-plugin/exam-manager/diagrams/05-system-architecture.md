# 05. System Architecture Diagram

## Overview
High-level visual representation of how all specification components connect together.

---

## 5.1 Complete System Architecture

```mermaid
graph TB
    subgraph "Frontend Layer (27 specs)"
        FE_LANDING[Landing Page<br/>02-public-landing]
        FE_AUTH[Authentication<br/>03-signup, 04-login]
        FE_DASH[Dashboard<br/>06-dashboard]
        FE_SECTION[Section View<br/>07-section, 08-markdown]
        FE_DEADLINE[Deadline UI<br/>11-countdown, 12-extension]
        FE_DESIGN[Design System<br/>22-ui-design]
        FE_PERF[Performance<br/>27-performance]
        FE_I18N[i18n<br/>26-internationalization]
    end

    subgraph "API Layer"
        REST[REST API<br/>36-rest-api-endpoints]
        RATE[Rate Limiting<br/>48-rate-limiting]
        VALIDATE[Validation<br/>09-validation-utilities]
    end

    subgraph "Service Layer (Core Business Logic)"
        SVC_EXAM[ExamService<br/>12-exam-service]
        SVC_PART[ParticipantService<br/>27-participant-service]
        SVC_PROG[ProgressService<br/>28-participant-progress]
        SVC_DEAD[DeadlineEngine<br/>29-deadline-engine]
        SVC_EXT[ExtensionService<br/>30-extension-system]
        SVC_KEY[SecretKeyService<br/>24-secret-key-service]
        SVC_WIKI[WikiService<br/>20-wiki-service]
    end

    subgraph "Infrastructure Layer"
        RBAC[RBAC System<br/>10-rbac-system]
        CRON[Cron Jobs<br/>34-cron-system]
        EMAIL[Email Queue<br/>31-email-queue]
        NOTIF[Notifications<br/>32-notification-service]
        AUDIT[Audit Logging<br/>46-audit-logging]
        LOG[Logging System<br/>07-logging-system]
    end

    subgraph "Data Layer"
        ORM[ORM Base Classes<br/>05-orm-base-classes]
        ENTITIES[Entity Models<br/>08-entity-models]
        DB[(Database<br/>04-database-schema<br/>22 Tables)]
        ENUMS[Enums & Constants<br/>06-enums-constants<br/>16 Enums]
    end

    subgraph "Shared Resources"
        CONST[66-shared-constants.md]
        XREF[63-cross-references.md]
        AI_CHECK[60-ai-implementation-checklist.md]
        FIXTURES[Test Fixtures<br/>49-test-fixtures]
    end

    %% Frontend to API
    FE_LANDING --> REST
    FE_AUTH --> REST
    FE_DASH --> REST
    FE_SECTION --> REST
    FE_DEADLINE --> REST
    
    %% API to Services
    REST --> RATE
    RATE --> VALIDATE
    VALIDATE --> SVC_EXAM
    VALIDATE --> SVC_PART
    VALIDATE --> SVC_KEY
    VALIDATE --> SVC_WIKI

    %% Service interconnections
    SVC_EXAM --> SVC_PART
    SVC_PART --> SVC_PROG
    SVC_PART --> SVC_DEAD
    SVC_DEAD --> SVC_EXT
    SVC_KEY --> SVC_PART

    %% Services to Infrastructure
    SVC_EXAM --> RBAC
    SVC_PART --> AUDIT
    SVC_DEAD --> CRON
    SVC_DEAD --> EMAIL
    SVC_EXT --> NOTIF
    SVC_PART --> LOG

    %% Infrastructure to Data
    RBAC --> ORM
    CRON --> ORM
    EMAIL --> ORM
    NOTIF --> ORM
    AUDIT --> ORM

    %% Services to Data
    SVC_EXAM --> ORM
    SVC_PART --> ORM
    SVC_KEY --> ORM
    SVC_WIKI --> ORM

    %% ORM to Database
    ORM --> ENTITIES
    ENTITIES --> DB
    ENTITIES --> ENUMS

    %% Shared Resources connections
    CONST -.-> REST
    CONST -.-> FE_DESIGN
    XREF -.-> SVC_EXAM
    AI_CHECK -.-> SVC_PART
    FIXTURES -.-> DB

    classDef frontend fill:#e0f2fe,stroke:#0284c7,color:#0c4a6e
    classDef api fill:#fef3c7,stroke:#d97706,color:#78350f
    classDef service fill:#dcfce7,stroke:#16a34a,color:#14532d
    classDef infra fill:#f3e8ff,stroke:#9333ea,color:#581c87
    classDef data fill:#fee2e2,stroke:#dc2626,color:#7f1d1d
    classDef shared fill:#f1f5f9,stroke:#64748b,color:#1e293b

    class FE_LANDING,FE_AUTH,FE_DASH,FE_SECTION,FE_DEADLINE,FE_DESIGN,FE_PERF,FE_I18N frontend
    class REST,RATE,VALIDATE api
    class SVC_EXAM,SVC_PART,SVC_PROG,SVC_DEAD,SVC_EXT,SVC_KEY,SVC_WIKI service
    class RBAC,CRON,EMAIL,NOTIF,AUDIT,LOG infra
    class ORM,ENTITIES,DB,ENUMS data
    class CONST,XREF,AI_CHECK,FIXTURES shared
```

---

## 5.2 Data Flow Architecture

```mermaid
flowchart LR
    subgraph User["User Actions"]
        U1[Visit Landing]
        U2[Login/Signup]
        U3[Access via Secret Key]
        U4[View Dashboard]
        U5[Read Sections]
        U6[Mark Complete]
        U7[Request Extension]
    end

    subgraph Frontend["Frontend Processing"]
        F1[Route Handler]
        F2[Form Validation]
        F3[API Client]
        F4[State Management]
        F5[UI Rendering]
    end

    subgraph API["REST API /wp-json/eqm/v1/"]
        A1[Authentication]
        A2[Authorization]
        A3[Rate Limiting]
        A4[Request Validation]
        A5[Response Formatting]
    end

    subgraph Services["Business Logic"]
        S1[ExamService]
        S2[ParticipantService]
        S3[ProgressService]
        S4[DeadlineEngine]
        S5[SecretKeyService]
    end

    subgraph Data["Persistence"]
        D1[(SQLite DB)]
        D2[File Storage]
        D3[Session/Cookies]
    end

    U1 --> F1
    U2 --> F2 --> F3
    U3 --> F3
    U4 --> F3
    U5 --> F3
    U6 --> F2 --> F3
    U7 --> F2 --> F3

    F3 --> A1
    A1 --> A2
    A2 --> A3
    A3 --> A4
    A4 --> S1
    A4 --> S2
    A4 --> S5

    S1 --> D1
    S2 --> S3
    S2 --> S4
    S3 --> D1
    S4 --> D1
    S5 --> D1
    S5 --> D3

    S1 --> A5
    S2 --> A5
    A5 --> F4
    F4 --> F5
```

---

## 5.3 Service Dependency Graph

```mermaid
graph TD
    subgraph "Tier 1: Foundation"
        T1_DB[Database Schema]
        T1_ORM[ORM Base Classes]
        T1_ENUM[Enums & Constants]
        T1_LOG[Logging System]
        T1_ERR[Error Management]
    end

    subgraph "Tier 2: Core Services"
        T2_RBAC[RBAC System]
        T2_EXAM[Exam Service]
        T2_PART[Participant Service]
        T2_KEY[Secret Key Service]
        T2_WIKI[Wiki Service]
    end

    subgraph "Tier 3: Extended Services"
        T3_PROG[Progress Tracking]
        T3_DEAD[Deadline Engine]
        T3_EXT[Extension System]
        T3_HIER[Exam Hierarchy]
    end

    subgraph "Tier 4: Communication"
        T4_EMAIL[Email Queue]
        T4_NOTIF[Notification Service]
        T4_TEMPL[Email Templates]
        T4_CRON[Cron System]
    end

    subgraph "Tier 5: Integration"
        T5_REST[REST API]
        T5_RATE[Rate Limiting]
        T5_AUDIT[Audit Logging]
        T5_SETTINGS[Plugin Settings]
    end

    subgraph "Tier 6: Admin UI"
        T6_DASH[Admin Dashboard]
        T6_EDITOR[Exam Editor]
        T6_MGMT[Participant Mgmt]
        T6_REPORT[Reporting]
    end

    subgraph "Tier 7: Public UI"
        T7_LAND[Landing Page]
        T7_AUTH[Auth Pages]
        T7_EXAM[Exam View]
        T7_EXTN[Extension Form]
    end

    %% Tier 1 -> Tier 2
    T1_DB --> T2_EXAM
    T1_ORM --> T2_EXAM
    T1_ENUM --> T2_PART
    T1_LOG --> T2_RBAC

    %% Tier 2 -> Tier 3
    T2_EXAM --> T3_HIER
    T2_PART --> T3_PROG
    T2_PART --> T3_DEAD
    T3_DEAD --> T3_EXT

    %% Tier 3 -> Tier 4
    T3_DEAD --> T4_CRON
    T3_EXT --> T4_EMAIL
    T3_PROG --> T4_NOTIF
    T4_EMAIL --> T4_TEMPL

    %% Tier 4 -> Tier 5
    T2_EXAM --> T5_REST
    T2_PART --> T5_REST
    T2_KEY --> T5_REST
    T5_REST --> T5_RATE
    T5_REST --> T5_AUDIT
    T1_LOG --> T5_SETTINGS

    %% Tier 5 -> Tier 6
    T5_REST --> T6_DASH
    T5_REST --> T6_EDITOR
    T5_REST --> T6_MGMT
    T5_REST --> T6_REPORT

    %% Tier 5 -> Tier 7
    T5_REST --> T7_LAND
    T5_REST --> T7_AUTH
    T5_REST --> T7_EXAM
    T5_REST --> T7_EXTN

    classDef tier1 fill:#fef2f2,stroke:#dc2626
    classDef tier2 fill:#fef3c7,stroke:#d97706
    classDef tier3 fill:#dcfce7,stroke:#16a34a
    classDef tier4 fill:#e0f2fe,stroke:#0284c7
    classDef tier5 fill:#f3e8ff,stroke:#9333ea
    classDef tier6 fill:#fce7f3,stroke:#db2777
    classDef tier7 fill:#f0fdf4,stroke:#22c55e

    class T1_DB,T1_ORM,T1_ENUM,T1_LOG,T1_ERR tier1
    class T2_RBAC,T2_EXAM,T2_PART,T2_KEY,T2_WIKI tier2
    class T3_PROG,T3_DEAD,T3_EXT,T3_HIER tier3
    class T4_EMAIL,T4_NOTIF,T4_TEMPL,T4_CRON tier4
    class T5_REST,T5_RATE,T5_AUDIT,T5_SETTINGS tier5
    class T6_DASH,T6_EDITOR,T6_MGMT,T6_REPORT tier6
    class T7_LAND,T7_AUTH,T7_EXAM,T7_EXTN tier7
```

---

## 5.4 Specification File Map

```mermaid
mindmap
  root((Exam Questions<br/>Manager))
    Backend["Backend (49 specs)"]
      Foundation
        01-coding-spec
        02-error-management
        03-plugin-structure
        04-database-schema
        05-orm-base-classes
        06-enums-constants
        07-logging-system
        08-entity-models
        09-validation-utilities
      Core Services
        10-rbac-system
        11-rbac-admin-ui
        12-exam-service
        13-exam-hierarchy
        24-secret-key-service
        27-participant-service
        28-participant-progress
        29-deadline-engine
        30-extension-system
      Exam Editor
        14-exam-editor-ui
        15-exam-content-tab
        16-exam-metadata-tab
        17-exam-subexams-tab
        18-exam-prerequisites-tab
        19-exam-checklists-tab
      Wiki System
        20-wiki-service
        21-wiki-categories
        22-wiki-editor-ui
        23-wiki-revisions
      Communication
        31-email-queue
        32-notification-service
        33-email-templates
        34-cron-system
      Infrastructure
        35-plugin-settings
        36-rest-api-endpoints
        46-audit-logging
        48-rate-limiting
        49-test-fixtures
      Admin UI
        37-admin-dashboard
        38-exam-list-view
        39-participant-management
        40-import-export-system
        43-public-exam-view
        44-certificate-generation
        45-notifications-panel
        47-reporting-dashboard
    Frontend["Frontend (27 specs)"]
      Foundation
        01-frontend-overview
        22-ui-design-system
        23-responsive-design
        24-tech-stack
      Auth Flow
        02-public-landing-page
        03-signup-flow
        04-login-flow
        05-participate-flow
        18-secret-key-access
      Dashboard
        06-dashboard-page
        07-section-view
        08-markdown-rendering
        09-prerequisites-display
        10-sub-exams-display
      Deadlines
        11-deadline-countdown
        12-extension-request
        14-exam-completion-flow
        15-locked-state
      System
        13-session-management
        16-error-handling
        17-edge-cases
        19-form-validation
        20-loading-states
        21-frontend-logging
      Polish
        25-acceptance-criteria
        26-internationalization
        27-performance-targets
    Shared
      66-shared-constants.md
      63-cross-references.md
      60-ai-implementation-checklist.md
    Diagrams
      01-database-er-diagram
      02-participant-status-states
      03-secret-key-auth-flow
      04-deadline-calculation-flow
      05-system-architecture
```

---

## 5.5 Implementation Phase Timeline

```mermaid
gantt
    title Implementation Roadmap (6 Weeks)
    dateFormat  YYYY-MM-DD
    
    section Phase 1: Foundation
    Database Schema           :p1_db, 2026-02-03, 2d
    ORM Base Classes          :p1_orm, after p1_db, 2d
    Enums & Constants         :p1_enum, after p1_orm, 1d
    Logging System            :p1_log, after p1_enum, 1d
    Error Management          :p1_err, after p1_log, 1d
    
    section Phase 2: Core Services
    RBAC System               :p2_rbac, 2026-02-10, 2d
    Exam Service              :p2_exam, after p2_rbac, 3d
    Participant Service       :p2_part, after p2_exam, 3d
    Secret Key Service        :p2_key, after p2_part, 2d
    
    section Phase 3: Deadline & Progress
    Progress Tracking         :p3_prog, 2026-02-17, 2d
    Deadline Engine           :p3_dead, after p3_prog, 3d
    Extension System          :p3_ext, after p3_dead, 2d
    Cron Jobs                 :p3_cron, after p3_ext, 2d
    
    section Phase 4: Communication
    Email Queue               :p4_email, 2026-02-24, 2d
    Email Templates           :p4_templ, after p4_email, 1d
    Notification Service      :p4_notif, after p4_templ, 2d
    Rate Limiting             :p4_rate, after p4_notif, 2d
    
    section Phase 5: Admin UI
    Exam Editor Tabs          :p5_tabs, 2026-03-03, 5d
    Admin Dashboard           :p5_dash, after p5_tabs, 2d
    Participant Management    :p5_mgmt, after p5_dash, 3d
    
    section Phase 6: Frontend
    Auth Flow                 :p6_auth, 2026-03-10, 3d
    Dashboard & Sections      :p6_dash, after p6_auth, 3d
    Deadline UI               :p6_dead, after p6_dash, 2d
    Polish & Testing          :p6_test, after p6_dead, 2d
```

---

## 5.6 Component Interaction Matrix

| Component | Depends On | Depended By | Spec Files |
|-----------|------------|-------------|------------|
| **Database** | - | All Services | 04, 05 |
| **Enums** | - | All Code | 06 |
| **ORM** | Database | All Services | 05, 08 |
| **RBAC** | ORM, Enums | All Admin Features | 10, 11 |
| **ExamService** | ORM, RBAC | Hierarchy, Editor, API | 12, 13 |
| **ParticipantService** | ORM, Exam | Progress, Deadline, API | 27 |
| **ProgressService** | Participant | Dashboard, Reports | 28 |
| **DeadlineEngine** | Participant | Extension, Cron, UI | 29 |
| **ExtensionSystem** | Deadline | Email, Notifications | 30 |
| **SecretKeyService** | ORM | Anonymous Access | 24, 25, 26 |
| **EmailQueue** | ORM | All Notifications | 31, 33 |
| **CronSystem** | Deadline, Email | Background Jobs | 34 |
| **REST API** | All Services | All Frontend | 36 |
| **RateLimiting** | Redis/DB | API Protection | 48 |
| **AuditLog** | ORM | Security, Reports | 46 |

---

## 5.7 Legend

| Color | Layer | Specs Count |
|-------|-------|-------------|
| 🔵 Blue | Frontend | 27 |
| 🟡 Yellow | API Layer | 3 |
| 🟢 Green | Service Layer | 10 |
| 🟣 Purple | Infrastructure | 8 |
| 🔴 Red | Data Layer | 5 |
| ⚫ Gray | Shared Resources | 4 |

---

## Related Specifications

| Diagram | File |
|---------|------|
| Database ERD | [01-database-er-diagram](01-database-er-diagram.md) |
| Participant States | [02-participant-status-states](02-participant-status-states.md) |
| Secret Key Auth | [03-secret-key-auth-flow](03-secret-key-auth-flow.md) |
| Deadline Flow | [04-deadline-calculation-flow](04-deadline-calculation-flow.md) |
| Cross-References | [CROSS-REFERENCES](../CROSS-REFERENCES.md) |

---

*This diagram provides the complete system overview for the Exam Questions Manager plugin.*
