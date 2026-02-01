# Unified Database Schema

**Version:** 1.0.0  
**Status:** Authoritative  
**Updated:** 2026-01-28  

---

## Overview

Single source of truth for all database entities in the Spec Management Software. All models use GORM with PascalCase naming conventions. UUIDs stored as TEXT, dates as ISO8601.

---

## Schema Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           CORE DOMAIN                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐               │
│  │   Project    │──1:N─│     File     │──1:N─│   Snapshot   │               │
│  └──────────────┘      └──────────────┘      └──────────────┘               │
│         │                     │                                              │
│         │                     │                                              │
│        1:N                   1:N                                             │
│         │                     │                                              │
│         ▼                     ▼                                              │
│  ┌──────────────┐      ┌──────────────┐                                     │
│  │ FileRegistry │      │ ChunkRegistry│                                     │
│  └──────────────┘      └──────────────┘                                     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                         AI & INSTRUCTION DOMAIN                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐               │
│  │ PromptPreset │──1:N─│PresetVersion │      │ UserOverride │               │
│  └──────────────┘      └──────────────┘      └──────────────┘               │
│                                                                              │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐               │
│  │InstructionRun│──1:N─│  RunArtifact │      │   RunTask    │               │
│  └──────────────┘      └──────────────┘      └──────────────┘               │
│         │                                                                    │
│        1:1                                                                   │
│         ▼                                                                    │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐               │
│  │Inconsistency │──1:N─│ Clarification│──1:1─│   Answer     │               │
│  │    Report    │      │   Question   │      │              │               │
│  └──────────────┘      └──────────────┘      └──────────────┘               │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                            RAG & VECTOR DOMAIN                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐               │
│  │  Embedding   │      │  Retrieval   │──M:N─│RetrievalChunk│               │
│  │  Metadata    │      │   Session    │      │              │               │
│  └──────────────┘      └──────────────┘      └──────────────┘               │
│                                                                              │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐               │
│  │  Knowledge   │──1:N─│  Knowledge   │      │ MemoryEntry  │               │
│  │   Source     │      │  WorkerJob   │      │              │               │
│  └──────────────┘      └──────────────┘      └──────────────┘               │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                         LLM & CONFIGURATION DOMAIN                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐               │
│  │  LLMServer   │──1:N─│  ModelConfig │      │   Config     │               │
│  └──────────────┘      └──────────────┘      └──────────────┘               │
│                                                                              │
│  ┌──────────────┐      ┌──────────────┐                                     │
│  │  ChatSession │──1:N─│ ChatMessage  │                                     │
│  └──────────────┘      └──────────────┘                                     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Domain 1: Core Project Management

### 1.1 Project

Primary entity for spec management projects.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| Name | TEXT | NOT NULL, UNIQUE | Project display name |
| Slug | TEXT | NOT NULL, UNIQUE | URL-safe identifier |
| Description | TEXT | | Optional description |
| WorkDirectory | TEXT | NOT NULL | Absolute path to project root |
| Status | TEXT | NOT NULL, DEFAULT 'active' | active, archived, deleted |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type Project struct {
    Id            string `gorm:"primaryKey;type:text"`
    Name          string `gorm:"not null;uniqueIndex"`
    Slug          string `gorm:"not null;uniqueIndex"`
    Description   string
    WorkDirectory string `gorm:"not null"`
    Status        string `gorm:"not null;default:'active'"`
    CreatedAt     time.Time
    UpdatedAt     time.Time
    
    // Relationships
    Files           []File           `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
    Snapshots       []Snapshot       `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
    FileRegistries  []FileRegistry   `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
    InstructionRuns []InstructionRun `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
    KnowledgeSources []KnowledgeSource `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
}
```

**Indexes:**
- `idx_project_slug` on `Slug`
- `idx_project_status` on `Status`

---

### 1.2 File

Tracks individual files within a project.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| RelativePath | TEXT | NOT NULL | Path relative to WorkDirectory |
| FileName | TEXT | NOT NULL | File name with extension |
| FileType | TEXT | NOT NULL | md, yaml, json, etc. |
| ContentHash | TEXT | | SHA256 of content |
| SizeBytes | INTEGER | | File size |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| DeletedAt | TEXT | | Soft delete timestamp |

```go
type File struct {
    Id           string `gorm:"primaryKey;type:text"`
    ProjectId    string `gorm:"not null;index"`
    RelativePath string `gorm:"not null"`
    FileName     string `gorm:"not null"`
    FileType     string `gorm:"not null"`
    ContentHash  string
    SizeBytes    int64
    CreatedAt    time.Time
    UpdatedAt    time.Time
    DeletedAt    gorm.DeletedAt `gorm:"index"`
    
    // Relationships
    Project Project `gorm:"foreignKey:ProjectId"`
    Chunks  []ChunkRegistry `gorm:"foreignKey:FileId;constraint:OnDelete:CASCADE"`
}
```

**Indexes:**
- `idx_file_project_path` on `(ProjectId, RelativePath)` UNIQUE
- `idx_file_type` on `FileType`

---

### 1.3 Snapshot

Named point-in-time captures for version control.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| Name | TEXT | NOT NULL | Format: V{nn}-{YYYY-MM-DD} |
| Description | TEXT | | Optional notes |
| GitCommitHash | TEXT | | Associated git commit |
| FileCount | INTEGER | NOT NULL | Number of files captured |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type Snapshot struct {
    Id            string `gorm:"primaryKey;type:text"`
    ProjectId     string `gorm:"not null;index"`
    Name          string `gorm:"not null"`
    Description   string
    GitCommitHash string
    FileCount     int
    CreatedAt     time.Time
    
    // Relationships
    Project Project `gorm:"foreignKey:ProjectId"`
}
```

**Indexes:**
- `idx_snapshot_project_name` on `(ProjectId, Name)` UNIQUE

---

## Domain 2: Prompt & Instruction System

### 2.1 PromptPreset

Base prompt templates by category.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| Category | TEXT | NOT NULL | idea, feature, task, codingGuideline, instruction |
| Name | TEXT | NOT NULL | Display name |
| Description | TEXT | | Purpose description |
| BasePrompt | TEXT | NOT NULL | Template content |
| IsSystem | INTEGER | NOT NULL, DEFAULT 0 | 1 = system-seeded, cannot delete |
| Version | INTEGER | NOT NULL, DEFAULT 1 | Current version number |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type PromptPreset struct {
    Id          string `gorm:"primaryKey;type:text"`
    Category    string `gorm:"not null;index"`
    Name        string `gorm:"not null"`
    Description string
    BasePrompt  string `gorm:"not null"`
    IsSystem    bool   `gorm:"not null;default:false"`
    Version     int    `gorm:"not null;default:1"`
    CreatedAt   time.Time
    UpdatedAt   time.Time
    
    // Relationships
    Versions  []PromptPresetVersion `gorm:"foreignKey:PresetId;constraint:OnDelete:CASCADE"`
    Overrides []UserPromptOverride  `gorm:"foreignKey:PresetId;constraint:OnDelete:CASCADE"`
}
```

**Indexes:**
- `idx_preset_category` on `Category`
- `idx_preset_category_name` on `(Category, Name)` UNIQUE

---

### 2.2 PromptPresetVersion

Version history for prompt presets.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| PresetId | TEXT | FK → PromptPreset.Id, NOT NULL | Parent preset |
| Version | INTEGER | NOT NULL | Version number |
| BasePrompt | TEXT | NOT NULL | Prompt content at this version |
| ChangeNote | TEXT | | Description of changes |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type PromptPresetVersion struct {
    Id         string `gorm:"primaryKey;type:text"`
    PresetId   string `gorm:"not null;index"`
    Version    int    `gorm:"not null"`
    BasePrompt string `gorm:"not null"`
    ChangeNote string
    CreatedAt  time.Time
    
    // Relationships
    Preset PromptPreset `gorm:"foreignKey:PresetId"`
}
```

**Indexes:**
- `idx_preset_version` on `(PresetId, Version)` UNIQUE

---

### 2.3 UserPromptOverride

User customizations layered on base presets.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| PresetId | TEXT | FK → PromptPreset.Id, NOT NULL | Base preset |
| ProjectId | TEXT | FK → Project.Id | Project-specific (NULL = global) |
| OverridePrompt | TEXT | NOT NULL | Additional/replacement content |
| OverrideMode | TEXT | NOT NULL, DEFAULT 'append' | append, prepend, replace |
| Priority | INTEGER | NOT NULL, DEFAULT 0 | Higher = applied later |
| IsActive | INTEGER | NOT NULL, DEFAULT 1 | Enable/disable |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type UserPromptOverride struct {
    Id             string `gorm:"primaryKey;type:text"`
    PresetId       string `gorm:"not null;index"`
    ProjectId      *string `gorm:"index"`
    OverridePrompt string `gorm:"not null"`
    OverrideMode   string `gorm:"not null;default:'append'"`
    Priority       int    `gorm:"not null;default:0"`
    IsActive       bool   `gorm:"not null;default:true"`
    CreatedAt      time.Time
    UpdatedAt      time.Time
    
    // Relationships
    Preset  PromptPreset `gorm:"foreignKey:PresetId"`
    Project *Project     `gorm:"foreignKey:ProjectId"`
}
```

---

### 2.4 InstructionRun

Tracks instruction generation executions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| InputType | TEXT | NOT NULL | voice, text |
| InputCategory | TEXT | NOT NULL | idea, feature, task, etc. |
| RawInput | TEXT | NOT NULL | Original transcribed/typed input |
| ProofreadInput | TEXT | | Cleaned input |
| EnhancedInput | TEXT | | AI-enhanced input |
| Status | TEXT | NOT NULL | pending, processing, completed, failed |
| ErrorCode | INTEGER | | Error code if failed |
| ErrorMessage | TEXT | | Error description |
| ModelUsed | TEXT | | LLM model identifier |
| TokensUsed | INTEGER | | Total tokens consumed |
| DurationMs | INTEGER | | Processing time |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| CompletedAt | TEXT | | Completion timestamp |

```go
type InstructionRun struct {
    Id             string `gorm:"primaryKey;type:text"`
    ProjectId      string `gorm:"not null;index"`
    InputType      string `gorm:"not null"`
    InputCategory  string `gorm:"not null"`
    RawInput       string `gorm:"not null"`
    ProofreadInput string
    EnhancedInput  string
    Status         string `gorm:"not null;default:'pending';index"`
    ErrorCode      *int
    ErrorMessage   string
    ModelUsed      string
    TokensUsed     int
    DurationMs     int64
    CreatedAt      time.Time
    CompletedAt    *time.Time
    
    // Relationships
    Project             Project              `gorm:"foreignKey:ProjectId"`
    Artifacts           []RunArtifact        `gorm:"foreignKey:RunId;constraint:OnDelete:CASCADE"`
    Tasks               []InstructionTask    `gorm:"foreignKey:RunId;constraint:OnDelete:CASCADE"`
    InconsistencyReport *InconsistencyReport `gorm:"foreignKey:RunId;constraint:OnDelete:CASCADE"`
}
```

**Indexes:**
- `idx_run_project_status` on `(ProjectId, Status)`
- `idx_run_created` on `CreatedAt`

---

### 2.5 RunArtifact

Output artifacts from instruction runs.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| RunId | TEXT | FK → InstructionRun.Id, NOT NULL | Parent run |
| ArtifactType | TEXT | NOT NULL | markdown, json, spec |
| RelativePath | TEXT | NOT NULL | Output file path |
| ContentHash | TEXT | NOT NULL | SHA256 of content |
| SizeBytes | INTEGER | NOT NULL | File size |
| Scope | TEXT | NOT NULL | global, backend, frontend, file |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type RunArtifact struct {
    Id           string `gorm:"primaryKey;type:text"`
    RunId        string `gorm:"not null;index"`
    ArtifactType string `gorm:"not null"`
    RelativePath string `gorm:"not null"`
    ContentHash  string `gorm:"not null"`
    SizeBytes    int64  `gorm:"not null"`
    Scope        string `gorm:"not null"`
    CreatedAt    time.Time
    
    // Relationships
    Run InstructionRun `gorm:"foreignKey:RunId"`
}
```

---

### 2.6 InstructionTask

Decomposed tasks from instructions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| RunId | TEXT | FK → InstructionRun.Id, NOT NULL | Parent run |
| TaskOrder | INTEGER | NOT NULL | Execution order |
| Title | TEXT | NOT NULL | Task summary |
| Description | TEXT | NOT NULL | Detailed instructions |
| Status | TEXT | NOT NULL | pending, running, completed, failed, skipped |
| DependsOn | TEXT | | JSON array of task IDs |
| OutputContext | TEXT | | JSON output for dependent tasks |
| StartedAt | TEXT | | Execution start |
| CompletedAt | TEXT | | Execution end |
| ErrorCode | INTEGER | | Error if failed |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type InstructionTask struct {
    Id            string `gorm:"primaryKey;type:text"`
    RunId         string `gorm:"not null;index"`
    TaskOrder     int    `gorm:"not null"`
    Title         string `gorm:"not null"`
    Description   string `gorm:"not null"`
    Status        string `gorm:"not null;default:'pending';index"`
    DependsOn     string `gorm:"type:text"` // JSON array
    OutputContext string `gorm:"type:text"` // JSON object
    StartedAt     *time.Time
    CompletedAt   *time.Time
    ErrorCode     *int
    CreatedAt     time.Time
    
    // Relationships
    Run InstructionRun `gorm:"foreignKey:RunId"`
}
```

**Indexes:**
- `idx_task_run_order` on `(RunId, TaskOrder)`
- `idx_task_status` on `Status`

---

## Domain 3: Consistency & Quality

### 3.1 InconsistencyReport

Generated reports identifying spec conflicts.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| RunId | TEXT | FK → InstructionRun.Id, UNIQUE | Associated run |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| ConsistencyScore | REAL | NOT NULL | 0.0 - 100.0 percentage |
| TotalIssues | INTEGER | NOT NULL | Count of detected issues |
| CriticalIssues | INTEGER | NOT NULL | High severity count |
| ReportContent | TEXT | NOT NULL | Full markdown report |
| Status | TEXT | NOT NULL | draft, published, resolved |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| ResolvedAt | TEXT | | Resolution timestamp |

```go
type InconsistencyReport struct {
    Id               string `gorm:"primaryKey;type:text"`
    RunId            string `gorm:"uniqueIndex"`
    ProjectId        string `gorm:"not null;index"`
    ConsistencyScore float64 `gorm:"not null"`
    TotalIssues      int    `gorm:"not null"`
    CriticalIssues   int    `gorm:"not null"`
    ReportContent    string `gorm:"not null"`
    Status           string `gorm:"not null;default:'draft'"`
    CreatedAt        time.Time
    ResolvedAt       *time.Time
    
    // Relationships
    Run       InstructionRun          `gorm:"foreignKey:RunId"`
    Project   Project                 `gorm:"foreignKey:ProjectId"`
    Questions []ClarificationQuestion `gorm:"foreignKey:ReportId;constraint:OnDelete:CASCADE"`
}
```

---

### 3.2 ClarificationQuestion

Questions generated to resolve inconsistencies.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ReportId | TEXT | FK → InconsistencyReport.Id, NOT NULL | Parent report |
| QuestionOrder | INTEGER | NOT NULL | Display order |
| QuestionText | TEXT | NOT NULL | The question |
| Context | TEXT | | Relevant spec excerpts |
| Category | TEXT | NOT NULL | conflict, ambiguity, missing, validation |
| Priority | TEXT | NOT NULL | critical, high, medium, low |
| Status | TEXT | NOT NULL | pending, answered, skipped |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type ClarificationQuestion struct {
    Id            string `gorm:"primaryKey;type:text"`
    ReportId      string `gorm:"not null;index"`
    QuestionOrder int    `gorm:"not null"`
    QuestionText  string `gorm:"not null"`
    Context       string
    Category      string `gorm:"not null"`
    Priority      string `gorm:"not null"`
    Status        string `gorm:"not null;default:'pending'"`
    CreatedAt     time.Time
    
    // Relationships
    Report InconsistencyReport  `gorm:"foreignKey:ReportId"`
    Answer *ClarificationAnswer `gorm:"foreignKey:QuestionId;constraint:OnDelete:CASCADE"`
}
```

---

### 3.3 ClarificationAnswer

User responses to clarification questions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| QuestionId | TEXT | FK → ClarificationQuestion.Id, UNIQUE, NOT NULL | Parent question |
| AnswerText | TEXT | NOT NULL | User's response |
| Confidence | TEXT | NOT NULL | definite, likely, unsure |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type ClarificationAnswer struct {
    Id         string `gorm:"primaryKey;type:text"`
    QuestionId string `gorm:"not null;uniqueIndex"`
    AnswerText string `gorm:"not null"`
    Confidence string `gorm:"not null;default:'definite'"`
    CreatedAt  time.Time
    
    // Relationships
    Question ClarificationQuestion `gorm:"foreignKey:QuestionId"`
}
```

---

### 3.4 RegenerationEvent

Tracks spec regeneration after clarifications.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ReportId | TEXT | FK → InconsistencyReport.Id, NOT NULL | Triggering report |
| TriggerType | TEXT | NOT NULL | manual, auto, scheduled |
| QuestionsAnswered | INTEGER | NOT NULL | Count of answers used |
| PreviousScore | REAL | NOT NULL | Score before regeneration |
| NewScore | REAL | | Score after (NULL if in progress) |
| FilesModified | INTEGER | | Count of changed files |
| Status | TEXT | NOT NULL | pending, processing, completed, failed |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| CompletedAt | TEXT | | Completion timestamp |

```go
type RegenerationEvent struct {
    Id                 string `gorm:"primaryKey;type:text"`
    ReportId           string `gorm:"not null;index"`
    TriggerType        string `gorm:"not null"`
    QuestionsAnswered  int    `gorm:"not null"`
    PreviousScore      float64 `gorm:"not null"`
    NewScore           *float64
    FilesModified      *int
    Status             string `gorm:"not null;default:'pending'"`
    CreatedAt          time.Time
    CompletedAt        *time.Time
    
    // Relationships
    Report InconsistencyReport `gorm:"foreignKey:ReportId"`
}
```

---

## Domain 4: RAG & Vector Storage

### 4.1 FileRegistry

Tracks files for RAG indexing.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| RelativePath | TEXT | NOT NULL | Path relative to WorkDirectory |
| FileType | TEXT | NOT NULL | md, yaml, json |
| ContentHash | TEXT | NOT NULL | SHA256 for change detection |
| LastIndexedAt | TEXT | | Last successful index time |
| ChunkCount | INTEGER | NOT NULL, DEFAULT 0 | Number of chunks generated |
| Status | TEXT | NOT NULL | pending, indexed, error, stale |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type FileRegistry struct {
    Id            string `gorm:"primaryKey;type:text"`
    ProjectId     string `gorm:"not null;index"`
    RelativePath  string `gorm:"not null"`
    FileType      string `gorm:"not null"`
    ContentHash   string `gorm:"not null"`
    LastIndexedAt *time.Time
    ChunkCount    int    `gorm:"not null;default:0"`
    Status        string `gorm:"not null;default:'pending'"`
    CreatedAt     time.Time
    UpdatedAt     time.Time
    
    // Relationships
    Project Project       `gorm:"foreignKey:ProjectId"`
    Chunks  []ChunkRegistry `gorm:"foreignKey:FileRegistryId;constraint:OnDelete:CASCADE"`
}
```

**Indexes:**
- `idx_filereg_project_path` on `(ProjectId, RelativePath)` UNIQUE
- `idx_filereg_status` on `Status`

---

### 4.2 ChunkRegistry

Individual chunks for vector search.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Stable chunk identifier |
| FileRegistryId | TEXT | FK → FileRegistry.Id, NOT NULL | Parent file |
| FileId | TEXT | FK → File.Id | Optional link to File entity |
| ChunkIndex | INTEGER | NOT NULL | Order within file |
| Content | TEXT | NOT NULL | Chunk text content |
| ContentHash | TEXT | NOT NULL | SHA256 for dedup |
| StartLine | INTEGER | NOT NULL | Starting line in source |
| EndLine | INTEGER | NOT NULL | Ending line in source |
| HeadingPath | TEXT | | Markdown heading hierarchy |
| TokenCount | INTEGER | NOT NULL | Estimated tokens |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type ChunkRegistry struct {
    Id             string `gorm:"primaryKey;type:text"`
    FileRegistryId string `gorm:"not null;index"`
    FileId         *string `gorm:"index"`
    ChunkIndex     int    `gorm:"not null"`
    Content        string `gorm:"not null"`
    ContentHash    string `gorm:"not null"`
    StartLine      int    `gorm:"not null"`
    EndLine        int    `gorm:"not null"`
    HeadingPath    string
    TokenCount     int    `gorm:"not null"`
    CreatedAt      time.Time
    UpdatedAt      time.Time
    
    // Relationships
    FileRegistry FileRegistry       `gorm:"foreignKey:FileRegistryId"`
    File         *File              `gorm:"foreignKey:FileId"`
    Embedding    *EmbeddingMetadata `gorm:"foreignKey:ChunkId;constraint:OnDelete:CASCADE"`
}
```

**Indexes:**
- `idx_chunk_file_index` on `(FileRegistryId, ChunkIndex)` UNIQUE
- `idx_chunk_hash` on `ContentHash`

---

### 4.3 EmbeddingMetadata

Metadata for vector embeddings (vectors stored in sqlite-vss).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ChunkId | TEXT | FK → ChunkRegistry.Id, UNIQUE, NOT NULL | Source chunk |
| ModelName | TEXT | NOT NULL | Embedding model used |
| Dimensions | INTEGER | NOT NULL | Vector dimensions |
| VectorId | INTEGER | NOT NULL | Row ID in vss table |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type EmbeddingMetadata struct {
    Id         string `gorm:"primaryKey;type:text"`
    ChunkId    string `gorm:"not null;uniqueIndex"`
    ModelName  string `gorm:"not null"`
    Dimensions int    `gorm:"not null"`
    VectorId   int64  `gorm:"not null"`
    CreatedAt  time.Time
    
    // Relationships
    Chunk ChunkRegistry `gorm:"foreignKey:ChunkId"`
}
```

---

### 4.4 ArtifactRegistry

Tracks ideas and instructions for RAG.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| ArtifactType | TEXT | NOT NULL | idea, instruction |
| RelativePath | TEXT | NOT NULL | Path in ideas/ or instructions/ |
| Title | TEXT | NOT NULL | Extracted title |
| Summary | TEXT | | AI-generated summary |
| Tags | TEXT | | JSON array of tags |
| IsPinned | INTEGER | NOT NULL, DEFAULT 0 | Include in top-K |
| PinPriority | INTEGER | NOT NULL, DEFAULT 0 | Order for pinned items |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type ArtifactRegistry struct {
    Id           string `gorm:"primaryKey;type:text"`
    ProjectId    string `gorm:"not null;index"`
    ArtifactType string `gorm:"not null;index"`
    RelativePath string `gorm:"not null"`
    Title        string `gorm:"not null"`
    Summary      string
    Tags         string `gorm:"type:text"` // JSON array
    IsPinned     bool   `gorm:"not null;default:false"`
    PinPriority  int    `gorm:"not null;default:0"`
    CreatedAt    time.Time
    UpdatedAt    time.Time
    
    // Relationships
    Project Project `gorm:"foreignKey:ProjectId"`
}
```

**Indexes:**
- `idx_artifact_project_type` on `(ProjectId, ArtifactType)`
- `idx_artifact_pinned` on `(ProjectId, IsPinned, PinPriority)`

---

### 4.5 RetrievalSession

Tracks RAG retrieval operations.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| Query | TEXT | NOT NULL | User query |
| QueryEmbeddingId | TEXT | | Embedding of query |
| TopK | INTEGER | NOT NULL | Requested results |
| RetrievalMethod | TEXT | NOT NULL | semantic, hybrid, keyword |
| TotalTokens | INTEGER | NOT NULL | Tokens in result context |
| DurationMs | INTEGER | NOT NULL | Query time |
| CachedResult | INTEGER | NOT NULL, DEFAULT 0 | Was cache hit |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type RetrievalSession struct {
    Id               string `gorm:"primaryKey;type:text"`
    ProjectId        string `gorm:"not null;index"`
    Query            string `gorm:"not null"`
    QueryEmbeddingId string
    TopK             int    `gorm:"not null"`
    RetrievalMethod  string `gorm:"not null"`
    TotalTokens      int    `gorm:"not null"`
    DurationMs       int64  `gorm:"not null"`
    CachedResult     bool   `gorm:"not null;default:false"`
    CreatedAt        time.Time
    
    // Relationships
    Project Project                  `gorm:"foreignKey:ProjectId"`
    Chunks  []RetrievalSessionChunk `gorm:"foreignKey:SessionId;constraint:OnDelete:CASCADE"`
}
```

---

### 4.6 RetrievalSessionChunk

Junction table for retrieval results.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| SessionId | TEXT | FK → RetrievalSession.Id, NOT NULL | Parent session |
| ChunkId | TEXT | FK → ChunkRegistry.Id, NOT NULL | Retrieved chunk |
| Rank | INTEGER | NOT NULL | Result ranking |
| Score | REAL | NOT NULL | Relevance score (RRF) |
| SemanticScore | REAL | | Vector similarity |
| KeywordScore | REAL | | FTS5 score |

```go
type RetrievalSessionChunk struct {
    Id            string  `gorm:"primaryKey;type:text"`
    SessionId     string  `gorm:"not null;index"`
    ChunkId       string  `gorm:"not null;index"`
    Rank          int     `gorm:"not null"`
    Score         float64 `gorm:"not null"`
    SemanticScore *float64
    KeywordScore  *float64
    
    // Relationships
    Session RetrievalSession `gorm:"foreignKey:SessionId"`
    Chunk   ChunkRegistry    `gorm:"foreignKey:ChunkId"`
}
```

---

## Domain 5: Knowledge Sources

### 5.1 KnowledgeSource

External knowledge bases for RAG.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| SourceType | TEXT | NOT NULL | spec, url |
| Name | TEXT | NOT NULL | Display name |
| Status | TEXT | NOT NULL | pending, processing, ready, error, removing |
| TotalChunks | INTEGER | NOT NULL, DEFAULT 0 | Indexed chunk count |
| Configuration | TEXT | NOT NULL | JSON config (type-specific) |
| ErrorMessage | TEXT | | Last error if any |
| LastSyncAt | TEXT | | Last successful sync |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type KnowledgeSource struct {
    Id            string `gorm:"primaryKey;type:text"`
    ProjectId     string `gorm:"not null;index"`
    SourceType    string `gorm:"not null"`
    Name          string `gorm:"not null"`
    Status        string `gorm:"not null;default:'pending'"`
    TotalChunks   int    `gorm:"not null;default:0"`
    Configuration string `gorm:"not null;type:text"` // JSON
    ErrorMessage  string
    LastSyncAt    *time.Time
    CreatedAt     time.Time
    UpdatedAt     time.Time
    
    // Relationships
    Project Project              `gorm:"foreignKey:ProjectId"`
    Jobs    []KnowledgeWorkerJob `gorm:"foreignKey:SourceId;constraint:OnDelete:CASCADE"`
}
```

**Configuration JSON Schemas:**

```json
// SourceType: "spec"
{
  "externalPaths": ["/path/to/specs"],
  "folderFilters": ["*.md", "*.yaml"],
  "recursive": true
}

// SourceType: "url"
{
  "baseUrl": "https://docs.example.com",
  "maxDepth": 3,
  "includePatterns": ["/api/*", "/guide/*"],
  "domainScope": "same-domain",
  "respectRobotsTxt": true
}
```

---

### 5.2 KnowledgeWorkerJob

Background job tracking for knowledge indexing.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| SourceId | TEXT | FK → KnowledgeSource.Id, NOT NULL | Parent source |
| JobType | TEXT | NOT NULL | index, reindex, remove |
| Status | TEXT | NOT NULL | queued, running, completed, failed |
| Progress | INTEGER | NOT NULL, DEFAULT 0 | 0-100 percentage |
| ItemsTotal | INTEGER | NOT NULL, DEFAULT 0 | Total items to process |
| ItemsProcessed | INTEGER | NOT NULL, DEFAULT 0 | Items completed |
| ErrorMessage | TEXT | | Error if failed |
| StartedAt | TEXT | | Job start time |
| CompletedAt | TEXT | | Job completion time |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type KnowledgeWorkerJob struct {
    Id             string `gorm:"primaryKey;type:text"`
    SourceId       string `gorm:"not null;index"`
    JobType        string `gorm:"not null"`
    Status         string `gorm:"not null;default:'queued'"`
    Progress       int    `gorm:"not null;default:0"`
    ItemsTotal     int    `gorm:"not null;default:0"`
    ItemsProcessed int    `gorm:"not null;default:0"`
    ErrorMessage   string
    StartedAt      *time.Time
    CompletedAt    *time.Time
    CreatedAt      time.Time
    
    // Relationships
    Source KnowledgeSource `gorm:"foreignKey:SourceId"`
}
```

---

## Domain 6: Memory & Context Management

### 6.1 MemoryEntry

LLM-generated summaries for context compression.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| SessionId | TEXT | | Associated chat/run session |
| EntryType | TEXT | NOT NULL | summary, fact, decision, context |
| Content | TEXT | NOT NULL | Compressed content |
| SourceRefs | TEXT | | JSON array of source chunk IDs |
| TokenCount | INTEGER | NOT NULL | Tokens in content |
| Importance | REAL | NOT NULL, DEFAULT 0.5 | 0.0-1.0 priority score |
| ExpiresAt | TEXT | | TTL for temporary entries |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type MemoryEntry struct {
    Id         string `gorm:"primaryKey;type:text"`
    ProjectId  string `gorm:"not null;index"`
    SessionId  string `gorm:"index"`
    EntryType  string `gorm:"not null"`
    Content    string `gorm:"not null"`
    SourceRefs string `gorm:"type:text"` // JSON array
    TokenCount int    `gorm:"not null"`
    Importance float64 `gorm:"not null;default:0.5"`
    ExpiresAt  *time.Time
    CreatedAt  time.Time
    
    // Relationships
    Project Project `gorm:"foreignKey:ProjectId"`
}
```

**Indexes:**
- `idx_memory_project_type` on `(ProjectId, EntryType)`
- `idx_memory_importance` on `(ProjectId, Importance DESC)`
- `idx_memory_expires` on `ExpiresAt`

---

### 6.2 InstructionSegment

Segmented instructions for long-chain execution.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| RunId | TEXT | FK → InstructionRun.Id, NOT NULL | Parent run |
| SegmentOrder | INTEGER | NOT NULL | Execution order |
| SegmentType | TEXT | NOT NULL | planning, execution, validation, summary |
| Content | TEXT | NOT NULL | Segment content |
| DependsOn | TEXT | | JSON array of segment IDs |
| TokenBudget | INTEGER | NOT NULL | Allocated tokens |
| Status | TEXT | NOT NULL | pending, active, completed |
| OutputSummary | TEXT | | Compressed output for next segment |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type InstructionSegment struct {
    Id            string `gorm:"primaryKey;type:text"`
    RunId         string `gorm:"not null;index"`
    SegmentOrder  int    `gorm:"not null"`
    SegmentType   string `gorm:"not null"`
    Content       string `gorm:"not null"`
    DependsOn     string `gorm:"type:text"` // JSON array
    TokenBudget   int    `gorm:"not null"`
    Status        string `gorm:"not null;default:'pending'"`
    OutputSummary string
    CreatedAt     time.Time
    
    // Relationships
    Run InstructionRun `gorm:"foreignKey:RunId"`
}
```

---

## Domain 7: LLM Configuration

### 7.1 LLMServer

Managed LLM server instances.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| Name | TEXT | NOT NULL, UNIQUE | Server display name |
| BackendType | TEXT | NOT NULL | ollama, llamacpp, llamaswap |
| Host | TEXT | NOT NULL | Hostname |
| Port | INTEGER | NOT NULL | Port number |
| Status | TEXT | NOT NULL | stopped, starting, running, error |
| LastHealthCheck | TEXT | | Last health probe time |
| HealthStatus | TEXT | | healthy, degraded, unhealthy |
| ProcessPid | INTEGER | | OS process ID if managed |
| Configuration | TEXT | | JSON backend-specific config |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type LLMServer struct {
    Id              string `gorm:"primaryKey;type:text"`
    Name            string `gorm:"not null;uniqueIndex"`
    BackendType     string `gorm:"not null"`
    Host            string `gorm:"not null"`
    Port            int    `gorm:"not null"`
    Status          string `gorm:"not null;default:'stopped'"`
    LastHealthCheck *time.Time
    HealthStatus    string
    ProcessPid      *int
    Configuration   string `gorm:"type:text"` // JSON
    CreatedAt       time.Time
    UpdatedAt       time.Time
    
    // Relationships
    Models []ModelConfig `gorm:"foreignKey:ServerId;constraint:OnDelete:CASCADE"`
}
```

---

### 7.2 ModelConfig

Model configurations per category.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ServerId | TEXT | FK → LLMServer.Id, NOT NULL | Parent server |
| Category | TEXT | NOT NULL | thinking, writing, voice, coding, embedding |
| ModelPath | TEXT | NOT NULL | Path or model identifier |
| ModelName | TEXT | NOT NULL | Display name |
| IsDefault | INTEGER | NOT NULL, DEFAULT 0 | Default for category |
| ContextSize | INTEGER | NOT NULL | Max context tokens |
| GpuLayers | INTEGER | | GPU offload layers |
| Parameters | TEXT | | JSON additional params |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type ModelConfig struct {
    Id          string `gorm:"primaryKey;type:text"`
    ServerId    string `gorm:"not null;index"`
    Category    string `gorm:"not null"`
    ModelPath   string `gorm:"not null"`
    ModelName   string `gorm:"not null"`
    IsDefault   bool   `gorm:"not null;default:false"`
    ContextSize int    `gorm:"not null"`
    GpuLayers   *int
    Parameters  string `gorm:"type:text"` // JSON
    CreatedAt   time.Time
    
    // Relationships
    Server LLMServer `gorm:"foreignKey:ServerId"`
}
```

**Indexes:**
- `idx_model_server_category` on `(ServerId, Category)`
- `idx_model_default` on `(Category, IsDefault)` WHERE IsDefault = 1

---

### 7.3 Config

Application configuration key-value store.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| Key | TEXT | NOT NULL, UNIQUE | Dot-notation key |
| Value | TEXT | NOT NULL | Configuration value |
| ValueType | TEXT | NOT NULL | string, int, bool, json |
| Description | TEXT | | Key description |
| IsSecret | INTEGER | NOT NULL, DEFAULT 0 | Sensitive value |
| Source | TEXT | NOT NULL | default, file, env, user |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |
| UpdatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type Config struct {
    Id          string `gorm:"primaryKey;type:text"`
    Key         string `gorm:"not null;uniqueIndex"`
    Value       string `gorm:"not null"`
    ValueType   string `gorm:"not null;default:'string'"`
    Description string
    IsSecret    bool   `gorm:"not null;default:false"`
    Source      string `gorm:"not null;default:'default'"`
    CreatedAt   time.Time
    UpdatedAt   time.Time
}
```

---

## Domain 8: Chat & Interaction

### 8.1 ChatSession

AI chat conversation sessions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Parent project |
| Title | TEXT | | Session title |
| SystemPrompt | TEXT | | Custom system prompt |
| ModelUsed | TEXT | | LLM model identifier |
| MessageCount | INTEGER | NOT NULL, DEFAULT 0 | Total messages |
| TotalTokens | INTEGER | NOT NULL, DEFAULT 0 | Cumulative tokens |
| LastMessageAt | TEXT | | Most recent message time |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type ChatSession struct {
    Id            string `gorm:"primaryKey;type:text"`
    ProjectId     string `gorm:"not null;index"`
    Title         string
    SystemPrompt  string
    ModelUsed     string
    MessageCount  int    `gorm:"not null;default:0"`
    TotalTokens   int    `gorm:"not null;default:0"`
    LastMessageAt *time.Time
    CreatedAt     time.Time
    
    // Relationships
    Project  Project       `gorm:"foreignKey:ProjectId"`
    Messages []ChatMessage `gorm:"foreignKey:SessionId;constraint:OnDelete:CASCADE"`
}
```

---

### 8.2 ChatMessage

Individual chat messages.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK, UUID | Unique identifier |
| SessionId | TEXT | FK → ChatSession.Id, NOT NULL | Parent session |
| Role | TEXT | NOT NULL | user, assistant, system |
| Content | TEXT | NOT NULL | Message content |
| TokenCount | INTEGER | NOT NULL | Tokens in message |
| ContextChunks | TEXT | | JSON array of injected chunk IDs |
| GenerationMs | INTEGER | | Time to generate (assistant only) |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

```go
type ChatMessage struct {
    Id            string `gorm:"primaryKey;type:text"`
    SessionId     string `gorm:"not null;index"`
    Role          string `gorm:"not null"`
    Content       string `gorm:"not null"`
    TokenCount    int    `gorm:"not null"`
    ContextChunks string `gorm:"type:text"` // JSON array
    GenerationMs  *int64
    CreatedAt     time.Time
    
    // Relationships
    Session ChatSession `gorm:"foreignKey:SessionId"`
}
```

**Indexes:**
- `idx_message_session_created` on `(SessionId, CreatedAt)`

---

## Migration Order

Execute migrations in dependency order:

```
Phase 1: Core Tables (no FK dependencies)
├── Config
├── LLMServer
└── Project

Phase 2: First-level Dependencies
├── File (→ Project)
├── Snapshot (→ Project)
├── FileRegistry (→ Project)
├── ModelConfig (→ LLMServer)
├── PromptPreset
├── KnowledgeSource (→ Project)
├── ChatSession (→ Project)
├── ArtifactRegistry (→ Project)
└── MemoryEntry (→ Project)

Phase 3: Second-level Dependencies
├── ChunkRegistry (→ FileRegistry, File)
├── PromptPresetVersion (→ PromptPreset)
├── UserPromptOverride (→ PromptPreset, Project)
├── InstructionRun (→ Project)
├── KnowledgeWorkerJob (→ KnowledgeSource)
├── ChatMessage (→ ChatSession)
└── RetrievalSession (→ Project)

Phase 4: Third-level Dependencies
├── EmbeddingMetadata (→ ChunkRegistry)
├── RunArtifact (→ InstructionRun)
├── InstructionTask (→ InstructionRun)
├── InstructionSegment (→ InstructionRun)
├── InconsistencyReport (→ InstructionRun, Project)
└── RetrievalSessionChunk (→ RetrievalSession, ChunkRegistry)

Phase 5: Fourth-level Dependencies
├── ClarificationQuestion (→ InconsistencyReport)
└── RegenerationEvent (→ InconsistencyReport)

Phase 6: Final Dependencies
└── ClarificationAnswer (→ ClarificationQuestion)
```

---

## Vector Tables (sqlite-vss)

Separate from GORM-managed tables. Created via raw SQL for vss extension:

```sql
-- Vector storage for chunk embeddings
CREATE VIRTUAL TABLE IF NOT EXISTS vss_chunks USING vss0(
    embedding(384)  -- Dimension matches embedding model
);

-- Vector storage for query embeddings (optional caching)
CREATE VIRTUAL TABLE IF NOT EXISTS vss_queries USING vss0(
    embedding(384)
);
```

**Note:** Vector tables are managed separately and linked via `EmbeddingMetadata.VectorId` and `RetrievalSession.QueryEmbeddingId`.

---

## Index Summary

| Table | Index | Columns | Type |
|-------|-------|---------|------|
| Project | idx_project_slug | Slug | UNIQUE |
| Project | idx_project_status | Status | |
| File | idx_file_project_path | ProjectId, RelativePath | UNIQUE |
| Snapshot | idx_snapshot_project_name | ProjectId, Name | UNIQUE |
| PromptPreset | idx_preset_category_name | Category, Name | UNIQUE |
| InstructionRun | idx_run_project_status | ProjectId, Status | |
| FileRegistry | idx_filereg_project_path | ProjectId, RelativePath | UNIQUE |
| ChunkRegistry | idx_chunk_file_index | FileRegistryId, ChunkIndex | UNIQUE |
| ArtifactRegistry | idx_artifact_pinned | ProjectId, IsPinned, PinPriority | |
| MemoryEntry | idx_memory_importance | ProjectId, Importance DESC | |
| ChatMessage | idx_message_session_created | SessionId, CreatedAt | |

---

## Foreign Key Constraints & Cascade Behaviors

### Constraint Strategy Overview

All foreign keys use explicit cascade behaviors to prevent orphaned records and maintain referential integrity. GORM tags define constraints at the application level, with corresponding database-level enforcement.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      CASCADE BEHAVIOR DECISION TREE                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Is child record meaningful without parent?                                  │
│         │                                                                    │
│    ┌────┴────┐                                                               │
│    │         │                                                               │
│   NO        YES                                                              │
│    │         │                                                               │
│    ▼         ▼                                                               │
│  CASCADE   SET NULL (if optional)                                            │
│  DELETE    RESTRICT (if required)                                            │
│                                                                              │
│  Examples:                                                                   │
│  • File → Project: CASCADE (file meaningless without project)                │
│  • ChunkRegistry → File: SET NULL (chunks can exist for deleted files)       │
│  • UserOverride → Project: SET NULL (override can become global)             │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

### Complete FK Reference Table

| Parent Table | Child Table | FK Column | On Delete | On Update | Nullable | Rationale |
|--------------|-------------|-----------|-----------|-----------|----------|-----------|
| **Domain 1: Core** |
| Project | File | ProjectId | CASCADE | CASCADE | NO | Files cannot exist without project |
| Project | Snapshot | ProjectId | CASCADE | CASCADE | NO | Snapshots belong to project lifecycle |
| Project | FileRegistry | ProjectId | CASCADE | CASCADE | NO | Index entries tied to project |
| Project | InstructionRun | ProjectId | CASCADE | CASCADE | NO | Runs are project-specific |
| Project | KnowledgeSource | ProjectId | CASCADE | CASCADE | NO | Knowledge sources are project-scoped |
| Project | ChatSession | ProjectId | CASCADE | CASCADE | NO | Sessions are project-scoped |
| Project | ArtifactRegistry | ProjectId | CASCADE | CASCADE | NO | Artifacts tracked per project |
| Project | MemoryEntry | ProjectId | CASCADE | CASCADE | NO | Memory context is project-specific |
| Project | InconsistencyReport | ProjectId | CASCADE | CASCADE | NO | Reports analyze project specs |
| Project | UserPromptOverride | ProjectId | SET NULL | CASCADE | YES | Override becomes global if project deleted |
| **Domain 2: Files & Chunks** |
| FileRegistry | ChunkRegistry | FileRegistryId | CASCADE | CASCADE | NO | Chunks meaningless without file |
| File | ChunkRegistry | FileId | SET NULL | CASCADE | YES | Optional link; chunk may outlive file record |
| ChunkRegistry | EmbeddingMetadata | ChunkId | CASCADE | CASCADE | NO | Embedding only for existing chunks |
| ChunkRegistry | RetrievalSessionChunk | ChunkId | CASCADE | CASCADE | NO | Results reference existing chunks |
| **Domain 3: Prompt System** |
| PromptPreset | PromptPresetVersion | PresetId | CASCADE | CASCADE | NO | Versions are preset history |
| PromptPreset | UserPromptOverride | PresetId | CASCADE | CASCADE | NO | Overrides require base preset |
| **Domain 4: Instruction System** |
| InstructionRun | RunArtifact | RunId | CASCADE | CASCADE | NO | Artifacts belong to run |
| InstructionRun | InstructionTask | RunId | CASCADE | CASCADE | NO | Tasks decomposed from run |
| InstructionRun | InstructionSegment | RunId | CASCADE | CASCADE | NO | Segments belong to run |
| InstructionRun | InconsistencyReport | RunId | CASCADE | CASCADE | NO | Report generated per run |
| **Domain 5: Consistency** |
| InconsistencyReport | ClarificationQuestion | ReportId | CASCADE | CASCADE | NO | Questions from report |
| InconsistencyReport | RegenerationEvent | ReportId | CASCADE | CASCADE | NO | Regenerations triggered by report |
| ClarificationQuestion | ClarificationAnswer | QuestionId | CASCADE | CASCADE | NO | Answer for specific question |
| **Domain 6: Knowledge** |
| KnowledgeSource | KnowledgeWorkerJob | SourceId | CASCADE | CASCADE | NO | Jobs process source |
| **Domain 7: RAG** |
| RetrievalSession | RetrievalSessionChunk | SessionId | CASCADE | CASCADE | NO | Results belong to session |
| Project | RetrievalSession | ProjectId | CASCADE | CASCADE | NO | Sessions are project-scoped |
| **Domain 8: LLM** |
| LLMServer | ModelConfig | ServerId | CASCADE | CASCADE | NO | Models configured per server |
| **Domain 9: Chat** |
| ChatSession | ChatMessage | SessionId | CASCADE | CASCADE | NO | Messages belong to session |

---

### Cascade Chains

When deleting parent entities, cascades propagate through relationship chains. Understanding these chains prevents unexpected data loss.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    PROJECT DELETION CASCADE CHAIN                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  DELETE Project                                                              │
│      │                                                                       │
│      ├──► File ──► (soft delete, ChunkRegistry.FileId → NULL)                │
│      │                                                                       │
│      ├──► Snapshot                                                           │
│      │                                                                       │
│      ├──► FileRegistry ──► ChunkRegistry ──► EmbeddingMetadata               │
│      │                          │                                            │
│      │                          └──► RetrievalSessionChunk                   │
│      │                                                                       │
│      ├──► InstructionRun ──► RunArtifact                                     │
│      │         │                                                             │
│      │         ├──► InstructionTask                                          │
│      │         │                                                             │
│      │         ├──► InstructionSegment                                       │
│      │         │                                                             │
│      │         └──► InconsistencyReport ──► ClarificationQuestion            │
│      │                      │                      │                         │
│      │                      │                      └──► ClarificationAnswer  │
│      │                      │                                                │
│      │                      └──► RegenerationEvent                           │
│      │                                                                       │
│      ├──► KnowledgeSource ──► KnowledgeWorkerJob                             │
│      │                                                                       │
│      ├──► ChatSession ──► ChatMessage                                        │
│      │                                                                       │
│      ├──► ArtifactRegistry                                                   │
│      │                                                                       │
│      ├──► MemoryEntry                                                        │
│      │                                                                       │
│      ├──► RetrievalSession ──► RetrievalSessionChunk                         │
│      │                                                                       │
│      └──► UserPromptOverride.ProjectId → NULL (becomes global)               │
│                                                                              │
│  TOTAL AFFECTED TABLES: 20                                                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                  INSTRUCTION RUN DELETION CASCADE CHAIN                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  DELETE InstructionRun                                                       │
│      │                                                                       │
│      ├──► RunArtifact                                                        │
│      │                                                                       │
│      ├──► InstructionTask                                                    │
│      │                                                                       │
│      ├──► InstructionSegment                                                 │
│      │                                                                       │
│      └──► InconsistencyReport ──► ClarificationQuestion ──► ClarificationAnswer
│                   │                                                          │
│                   └──► RegenerationEvent                                     │
│                                                                              │
│  TOTAL AFFECTED TABLES: 6                                                    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    PROMPT PRESET DELETION CASCADE CHAIN                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  DELETE PromptPreset (only if IsSystem = false)                              │
│      │                                                                       │
│      ├──► PromptPresetVersion (all history)                                  │
│      │                                                                       │
│      └──► UserPromptOverride (all customizations)                            │
│                                                                              │
│  TOTAL AFFECTED TABLES: 2                                                    │
│                                                                              │
│  ⚠️  GUARD: System presets (IsSystem=true) cannot be deleted                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

### Orphan Prevention Strategies

#### Strategy 1: Application-Level Guards

Prevent deletion of entities with critical dependents:

```go
// ProjectService.Delete - Guard against active runs
func (s *ProjectService) Delete(projectId string) error {
    var activeRuns int64
    s.db.Model(&InstructionRun{}).
        Where("ProjectId = ? AND Status IN ?", projectId, []string{"pending", "processing"}).
        Count(&activeRuns)
    
    if activeRuns > 0 {
        return NewError(ERR_PROJECT_HAS_ACTIVE_RUNS, 
            "Cannot delete project with %d active instruction runs", activeRuns)
    }
    
    // Proceed with cascade delete
    return s.db.Delete(&Project{Id: projectId}).Error
}
```

```go
// PromptPresetService.Delete - Guard system presets
func (s *PromptPresetService) Delete(presetId string) error {
    var preset PromptPreset
    if err := s.db.First(&preset, "Id = ?", presetId).Error; err != nil {
        return err
    }
    
    if preset.IsSystem {
        return NewError(ERR_CANNOT_DELETE_SYSTEM_PRESET,
            "System preset '%s' cannot be deleted", preset.Name)
    }
    
    return s.db.Delete(&preset).Error
}
```

#### Strategy 2: Soft Deletes for Audit Trail

Entities requiring audit history use soft deletes:

```go
// File uses soft delete for recovery
type File struct {
    // ... other fields
    DeletedAt gorm.DeletedAt `gorm:"index"`
}

// Query excludes soft-deleted by default
db.Find(&files) // Only active files

// Include soft-deleted
db.Unscoped().Find(&files) // All files

// Hard delete
db.Unscoped().Delete(&file) // Permanent removal
```

**Soft Delete Entities:**
- `File` - Allows recovery, maintains git history reference
- `Project` - Can be archived before permanent deletion

**Hard Delete Entities:**
- All other entities - Cascade with parent

#### Strategy 3: SET NULL for Optional References

When child can exist independently:

```go
// ChunkRegistry optionally links to File
type ChunkRegistry struct {
    FileId *string `gorm:"index"` // Nullable
    // ...
}

// UserPromptOverride can be global (no project)
type UserPromptOverride struct {
    ProjectId *string `gorm:"index"` // Nullable, SET NULL on project delete
    // ...
}
```

#### Strategy 4: Pre-Delete Cleanup Hooks

GORM hooks ensure cleanup before cascade:

```go
func (p *Project) BeforeDelete(tx *gorm.DB) error {
    // Cancel any pending jobs
    tx.Model(&KnowledgeWorkerJob{}).
        Where("SourceId IN (SELECT Id FROM KnowledgeSources WHERE ProjectId = ?)", p.Id).
        Where("Status IN ?", []string{"queued", "running"}).
        Update("Status", "cancelled")
    
    // Archive important data if needed
    if err := archiveProjectData(tx, p.Id); err != nil {
        return err
    }
    
    return nil
}

func (ir *InstructionRun) BeforeDelete(tx *gorm.DB) error {
    // Cleanup any temporary files
    var artifacts []RunArtifact
    tx.Where("RunId = ?", ir.Id).Find(&artifacts)
    
    for _, a := range artifacts {
        _ = os.Remove(filepath.Join(getWorkDir(), a.RelativePath))
    }
    
    return nil
}
```

---

### Constraint Definitions (SQL)

For explicit database-level constraints (generated from GORM or added manually):

```sql
-- Domain 1: Core Project Management
ALTER TABLE File ADD CONSTRAINT fk_file_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE Snapshot ADD CONSTRAINT fk_snapshot_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE FileRegistry ADD CONSTRAINT fk_filereg_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Domain 2: Files & Chunks
ALTER TABLE ChunkRegistry ADD CONSTRAINT fk_chunk_filereg 
    FOREIGN KEY (FileRegistryId) REFERENCES FileRegistry(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ChunkRegistry ADD CONSTRAINT fk_chunk_file 
    FOREIGN KEY (FileId) REFERENCES File(Id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE EmbeddingMetadata ADD CONSTRAINT fk_embedding_chunk 
    FOREIGN KEY (ChunkId) REFERENCES ChunkRegistry(Id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Domain 3: Prompt System
ALTER TABLE PromptPresetVersion ADD CONSTRAINT fk_version_preset 
    FOREIGN KEY (PresetId) REFERENCES PromptPreset(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE UserPromptOverride ADD CONSTRAINT fk_override_preset 
    FOREIGN KEY (PresetId) REFERENCES PromptPreset(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE UserPromptOverride ADD CONSTRAINT fk_override_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Domain 4: Instruction System
ALTER TABLE RunArtifact ADD CONSTRAINT fk_artifact_run 
    FOREIGN KEY (RunId) REFERENCES InstructionRun(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE InstructionTask ADD CONSTRAINT fk_task_run 
    FOREIGN KEY (RunId) REFERENCES InstructionRun(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE InstructionSegment ADD CONSTRAINT fk_segment_run 
    FOREIGN KEY (RunId) REFERENCES InstructionRun(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE InconsistencyReport ADD CONSTRAINT fk_report_run 
    FOREIGN KEY (RunId) REFERENCES InstructionRun(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE InconsistencyReport ADD CONSTRAINT fk_report_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Domain 5: Consistency
ALTER TABLE ClarificationQuestion ADD CONSTRAINT fk_question_report 
    FOREIGN KEY (ReportId) REFERENCES InconsistencyReport(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ClarificationAnswer ADD CONSTRAINT fk_answer_question 
    FOREIGN KEY (QuestionId) REFERENCES ClarificationQuestion(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE RegenerationEvent ADD CONSTRAINT fk_regen_report 
    FOREIGN KEY (ReportId) REFERENCES InconsistencyReport(Id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Domain 6: Knowledge
ALTER TABLE KnowledgeSource ADD CONSTRAINT fk_knowledge_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE KnowledgeWorkerJob ADD CONSTRAINT fk_job_source 
    FOREIGN KEY (SourceId) REFERENCES KnowledgeSource(Id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Domain 7: RAG
ALTER TABLE RetrievalSession ADD CONSTRAINT fk_retrieval_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE RetrievalSessionChunk ADD CONSTRAINT fk_rschunk_session 
    FOREIGN KEY (SessionId) REFERENCES RetrievalSession(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE RetrievalSessionChunk ADD CONSTRAINT fk_rschunk_chunk 
    FOREIGN KEY (ChunkId) REFERENCES ChunkRegistry(Id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Domain 8: LLM
ALTER TABLE ModelConfig ADD CONSTRAINT fk_model_server 
    FOREIGN KEY (ServerId) REFERENCES LLMServer(Id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Domain 9: Chat
ALTER TABLE ChatSession ADD CONSTRAINT fk_session_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ChatMessage ADD CONSTRAINT fk_message_session 
    FOREIGN KEY (SessionId) REFERENCES ChatSession(Id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Domain 10: Memory & Context
ALTER TABLE MemoryEntry ADD CONSTRAINT fk_memory_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ArtifactRegistry ADD CONSTRAINT fk_artifactreg_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE InstructionRun ADD CONSTRAINT fk_run_project 
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE ON UPDATE CASCADE;
```

---

### Error Codes for Constraint Violations

| Error Code | Constant | Description | HTTP Status |
|------------|----------|-------------|-------------|
| 3010 | ERR_FK_VIOLATION | Foreign key constraint violated | 409 |
| 3011 | ERR_CASCADE_BLOCKED | Cannot delete: active dependents exist | 409 |
| 3012 | ERR_ORPHAN_DETECTED | Orphaned record found during cleanup | 500 |
| 3013 | ERR_CIRCULAR_REFERENCE | Circular dependency detected | 400 |
| 3014 | ERR_DELETE_SYSTEM_ENTITY | Cannot delete system-protected entity | 403 |
| 3015 | ERR_PARENT_NOT_FOUND | Referenced parent entity does not exist | 404 |

```go
// Error handling example
func handleDBError(err error) error {
    if errors.Is(err, gorm.ErrForeignKeyViolated) {
        return NewError(ERR_FK_VIOLATION, "Referenced entity does not exist")
    }
    // ... other cases
    return err
}
```

---

### Orphan Cleanup Job

Scheduled job to detect and clean orphaned records:

```go
// Run weekly or on-demand
func CleanupOrphans(db *gorm.DB) error {
    orphanQueries := []struct {
        Table  string
        Query  string
    }{
        // ChunkRegistry with deleted FileRegistry
        {
            "ChunkRegistry",
            `DELETE FROM ChunkRegistry 
             WHERE FileRegistryId NOT IN (SELECT Id FROM FileRegistry)`,
        },
        // EmbeddingMetadata with deleted ChunkRegistry
        {
            "EmbeddingMetadata",
            `DELETE FROM EmbeddingMetadata 
             WHERE ChunkId NOT IN (SELECT Id FROM ChunkRegistry)`,
        },
        // RetrievalSessionChunk with deleted sessions or chunks
        {
            "RetrievalSessionChunk",
            `DELETE FROM RetrievalSessionChunk 
             WHERE SessionId NOT IN (SELECT Id FROM RetrievalSession)
             OR ChunkId NOT IN (SELECT Id FROM ChunkRegistry)`,
        },
    }
    
    for _, oq := range orphanQueries {
        result := db.Exec(oq.Query)
        if result.Error != nil {
            return fmt.Errorf("orphan cleanup failed for %s: %w", oq.Table, result.Error)
        }
        if result.RowsAffected > 0 {
            log.Warn().
                Str("table", oq.Table).
                Int64("deleted", result.RowsAffected).
                Msg("Orphaned records cleaned")
        }
    }
    
    // Vacuum to reclaim space
    return db.Exec("VACUUM").Error
}
```

---

### Testing FK Constraints

Unit tests verify constraint behavior:

```go
func TestProjectDeleteCascade(t *testing.T) {
    db := setupTestDB(t)
    
    // Create project with children
    project := createTestProject(db)
    file := createTestFile(db, project.Id)
    run := createTestRun(db, project.Id)
    
    // Delete project
    err := db.Delete(&project).Error
    require.NoError(t, err)
    
    // Verify cascades
    var fileCount, runCount int64
    db.Model(&File{}).Where("ProjectId = ?", project.Id).Count(&fileCount)
    db.Model(&InstructionRun{}).Where("ProjectId = ?", project.Id).Count(&runCount)
    
    assert.Equal(t, int64(0), fileCount, "Files should cascade delete")
    assert.Equal(t, int64(0), runCount, "Runs should cascade delete")
}

func TestSetNullBehavior(t *testing.T) {
    db := setupTestDB(t)
    
    // Create project and override
    project := createTestProject(db)
    override := createTestOverride(db, project.Id)
    
    // Delete project
    err := db.Delete(&project).Error
    require.NoError(t, err)
    
    // Override should still exist with NULL ProjectId
    var existing UserPromptOverride
    err = db.First(&existing, "Id = ?", override.Id).Error
    require.NoError(t, err)
    assert.Nil(t, existing.ProjectId, "ProjectId should be NULL")
}

func TestDeleteBlockedByActiveRuns(t *testing.T) {
    db := setupTestDB(t)
    svc := NewProjectService(db)
    
    project := createTestProject(db)
    createTestRun(db, project.Id, WithStatus("processing"))
    
    err := svc.Delete(project.Id)
    
    assert.ErrorIs(t, err, ERR_CASCADE_BLOCKED)
}
```

---

## Related Documents

- [Database Design Overview](./00-overview.md)
- [Error Code Registry](../06-error-management/error-code-registry.md)
- [Implementation Order Guide](../08-roadmap-overview/02-implementation-order-guide.md)
- [RAG System Spec](../05-features/06-ai-integration/08-rag-system.md)
