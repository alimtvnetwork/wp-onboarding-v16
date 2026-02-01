# Entity Relationship Diagram

**Version:** 2.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Visual representation of all **32 database entities** and their relationships for the Spec Management Software.

**Cross-References:**
- [Schema Definition](../01-schema.md)
- [Relationships](../03-relationships.md)

---

## Complete ERD

```mermaid
erDiagram
    %% ============================================
    %% USER DOMAIN (2 entities: User, Session)
    %% ============================================
    
    User ||--o{ Session : "has"
    User ||--o{ Project : "owns"
    User ||--o{ Snapshot : "creates"
    User ||--o{ Instruction : "creates"
    User ||--o{ PromptPreset : "creates"
    User ||--o{ UserPromptOverride : "has"
    User ||--o{ PromotionEvent : "triggers"
    
    User {
        text Id PK
        text Username UK
        text Email UK
        text PasswordHash
        text DisplayName
        text ThemePreference
        text LastLoginAt
        text CreatedAt
        text UpdatedAt
        text DeletedAt
    }
    
    Session {
        text Id PK
        text UserId FK
        text Token UK
        text ExpiresAt
        text CreatedAt
    }
    
    %% ============================================
    %% PROJECT DOMAIN (3 entities: Project, ProjectMetadata, VectorIndexMetadata)
    %% ============================================
    
    Project ||--o{ Project : "parent"
    Project ||--o{ File : "contains"
    Project ||--o{ Snapshot : "has"
    Project ||--|| ProjectMetadata : "has"
    Project ||--o{ Instruction : "has"
    Project ||--o{ Artifact : "contains"
    Project ||--o{ RetrievalSession : "has"
    Project ||--o{ PromotionEvent : "has"
    Project ||--o{ ConsistencyLoop : "has"
    Project ||--|| VectorIndexMetadata : "has"
    
    Project {
        text Id PK
        text ParentId FK
        text OwnerId FK
        text Name
        text Slug UK
        text Path UK
        text Type
        text Description
        int SortOrder
        text Visibility
        text CreatedAt
        text UpdatedAt
        text DeletedAt
    }
    
    ProjectMetadata {
        text Id PK
        text ProjectId FK_UK
        text Version
        text Summary
        text AuthorName
        text AuthorEmail
        text Language
        text Framework
        json Tags
        json AiSettings
        json GuidelineOverrides
        json CustomMetadata
        text MetadataFileHash
        text LastSyncedAt
        text CreatedAt
        text UpdatedAt
    }
    
    VectorIndexMetadata {
        text Id PK
        text ProjectId FK_UK
        int TotalVectors
        int Dimensions
        text IndexType
        int IndexSizeBytes
        text LastReindexAt
        text CreatedAt
        text UpdatedAt
    }
    
    %% ============================================
    %% FILE DOMAIN (2 entities: File, Snapshot)
    %% ============================================
    
    File ||--o{ File : "parent"
    File ||--o{ FileChange : "tracked_in"
    
    File {
        text Id PK
        text ProjectId FK
        text ParentId FK
        text Name
        text Path UK
        text Type
        text ContentHash
        int SortOrder
        text CreatedAt
        text UpdatedAt
        text DeletedAt
    }
    
    Snapshot {
        text Id PK
        text ProjectId FK
        text CreatedById FK
        text Name UK
        text Description
        text FolderPath
        text CreatedAt
    }
    
    %% ============================================
    %% CONFIGURATION DOMAIN (2 entities: Config, ConfigSeedEvent)
    %% ============================================
    
    User ||--o{ ConfigSeedEvent : "triggers"
    
    Config {
        text Key PK
        text Value
        text Source
        text Description
        text UpdatedAt
    }
    
    ConfigSeedEvent {
        text Id PK
        text EventType
        bool IsFirstSeed
        int KeysSeeded
        json KeysModified
        text SeedSource
        text UserId FK
        json EventData
        text CreatedAt
    }
    
    %% ============================================
    %% LLM MODEL DOMAIN (2 entities: ModelRegistry, ModelSlot)
    %% ============================================
    
    ModelRegistry ||--o{ ModelSlot : "loaded_in"
    ModelRegistry ||--o{ Instruction : "used_by"
    
    ModelRegistry {
        text Id PK
        text DisplayName
        text FileName UK
        text ModelType
        text ModelPath
        int FileSizeBytes
        json Tags
        bool IsEnabled
        int ContextSize
        int GpuLayers
        text LastScannedAt
        text CreatedAt
        text UpdatedAt
    }
    
    ModelSlot {
        text Id PK
        int SlotIndex UK
        int Port UK
        text ModelId FK
        text Status
        int ProcessId
        text StartedAt
        text LastAccessedAt
        text LastHealthCheckAt
        text ErrorMessage
        text CreatedAt
        text UpdatedAt
    }
    
    %% ============================================
    %% PROMPT SYSTEM DOMAIN (3 entities: PromptPreset, PromptPresetVersion, UserPromptOverride)
    %% ============================================
    
    PromptPreset ||--o{ PromptPresetVersion : "has"
    PromptPreset ||--o{ UserPromptOverride : "customized_by"
    
    PromptPreset {
        text Id PK
        text Name
        text ContentType
        bool IsSystemPreset
        text CreatedById FK
        text CreatedAt
        text UpdatedAt
    }
    
    PromptPresetVersion {
        text Id PK
        text PresetId FK
        int VersionNumber
        text PromptTemplate
        text Description
        bool IsActive
        text CreatedAt
    }
    
    UserPromptOverride {
        text Id PK
        text UserId FK
        text PresetId FK
        text CustomTemplate
        bool IsEnabled
        text CreatedAt
        text UpdatedAt
    }
    
    %% ============================================
    %% INSTRUCTION DOMAIN (5 entities: Instruction, InstructionTask, FileChange, InstructionSegment, MemoryEntry)
    %% ============================================
    
    Instruction ||--o{ InstructionTask : "has"
    Instruction ||--o| InconsistencyReport : "analyzed_by"
    Instruction ||--o{ RegenerationEvent : "has"
    Instruction ||--o| Artifact : "promoted_from"
    Instruction ||--o{ InstructionSegment : "segmented_into"
    Instruction ||--o{ MemoryEntry : "remembers"
    
    Instruction {
        text Id PK
        text ProjectId FK
        text CreatedById FK
        text ReasoningModelId FK
        text PromotedFromId FK
        text Scope
        text TargetPath
        text RawInput
        text ProcessedInput
        text Status
        text OutputFormat
        json GeneratedFiles
        text ErrorMessage
        text CreatedAt
        text UpdatedAt
    }
    
    InstructionTask ||--o{ InstructionTask : "parent"
    InstructionTask ||--o{ FileChange : "makes"
    
    InstructionTask {
        text Id PK
        text InstructionId FK
        text ParentTaskId FK
        int SequenceNumber
        text Title
        text Description
        text Status
        json DependsOn
        json OutputContext
        text CompletedAt
        text CreatedAt
        text UpdatedAt
    }
    
    FileChange {
        text Id PK
        text InstructionTaskId FK
        text FileId FK
        text FilePath
        text ChangeType
        text BeforeHash
        text AfterHash
        text DiffContent
        text BeforeSnapshot
        text AfterSnapshot
        int BytesBefore
        int BytesAfter
        text CreatedAt
    }
    
    InstructionSegment {
        text Id PK
        text InstructionId FK
        int SegmentIndex
        text Title
        text Content
        int TokenCount
        text DependsOnSegments
        text Status
        text SummaryForNext
        text ErrorMessage
        text ExecutedAt
        int ExecutionDurationMs
        text CreatedAt
        text UpdatedAt
    }
    
    MemoryEntry {
        text Id PK
        text InstructionId FK
        text SessionId
        int TurnIndex
        int OriginalTokens
        int CompressedTokens
        text Summary
        text KeyDecisions
        text ArtifactsCreated
        text OpenQuestions
        text CreatedAt
        text UpdatedAt
    }
    
    %% ============================================
    %% INCONSISTENCY ANALYSIS DOMAIN (5 entities: InconsistencyReport, InconsistencyIssue, ClarificationQuestion, ClarificationAnswer, RegenerationEvent)
    %% ============================================
    
    InconsistencyReport ||--o{ InconsistencyIssue : "contains"
    InconsistencyIssue ||--o{ ClarificationQuestion : "generates"
    ClarificationQuestion ||--o| ClarificationAnswer : "answered_by"
    
    InconsistencyReport {
        text Id PK
        text InstructionId FK_UK
        int TotalIssues
        int PhaseACritical
        int PhaseBConflict
        int PhaseCAmbiguous
        int PhaseDOptional
        json AnalysisOutput
        text Status
        text ResolvedAt
        text CreatedAt
        text UpdatedAt
    }
    
    InconsistencyIssue {
        text Id PK
        text ReportId FK
        text Phase
        text IssueType
        text Severity
        text Description
        text AffectedField
        text SuggestedFix
        bool IsResolved
        text Resolution
        text CreatedAt
        text UpdatedAt
    }
    
    ClarificationQuestion {
        text Id PK
        text IssueId FK
        int QuestionNumber
        text QuestionText
        text InputType
        json Options
        text DefaultValue
        bool IsRequired
        text CreatedAt
    }
    
    ClarificationAnswer {
        text Id PK
        text QuestionId FK_UK
        text AnswerValue
        json SelectedOptions
        text FreeTextInput
        text AnsweredAt
        text CreatedAt
    }
    
    RegenerationEvent {
        text Id PK
        text InstructionId FK
        int RegenerationNumber
        text TriggerReason
        json ChangedAnswers
        json AffectedFiles
        text PreviousOutput
        text NewOutput
        text Status
        text CompletedAt
        text CreatedAt
    }
    
    %% ============================================
    %% RAG DOMAIN (6 entities: Artifact, Chunk, Embedding, RetrievalSession, RetrievalSessionChunk, PromotionEvent)
    %% ============================================
    
    Artifact ||--o{ Chunk : "split_into"
    Artifact ||--o| Instruction : "promotes_to"
    Artifact ||--o{ PromotionEvent : "source_of"
    Chunk ||--|| Embedding : "has"
    RetrievalSession ||--o{ RetrievalSessionChunk : "uses"
    RetrievalSessionChunk }o--|| Chunk : "references"
    
    Artifact {
        text Id PK
        text ProjectId FK
        text PromotedToId FK
        text ArtifactType
        text Title
        text FilePath UK
        text ContentHash
        text Status
        int TokenCount
        text CreatedAt
        text UpdatedAt
    }
    
    Chunk {
        text Id PK
        text ArtifactId FK
        int SequenceNumber
        text Content
        int TokenCount
        text ContentHash
        json Metadata
        text CreatedAt
    }
    
    Embedding {
        text Id PK
        text ChunkId FK_UK
        text ModelName
        int Dimensions
        blob Vector
        text CreatedAt
    }
    
    RetrievalSession {
        text Id PK
        text ProjectId FK
        text Query
        int TopK
        json Filters
        float AvgScore
        int TotalResults
        int DurationMs
        text CreatedAt
    }
    
    RetrievalSessionChunk {
        text Id PK
        text SessionId FK
        text ChunkId FK
        float Score
        int Rank
        text CreatedAt
    }
    
    PromotionEvent {
        text Id PK
        text ProjectId FK
        text SourceArtifactId FK
        text TargetArtifactId FK
        text TargetInstructionId FK
        text Status
        text PromotedById FK
        text SourcePath
        text TargetPath
        text PromotedAt
        bool ReindexTriggered
        text ReindexCompletedAt
        text ErrorMessage
        text CreatedAt
        text UpdatedAt
    }
    
    %% ============================================
    %% CONSISTENCY CHECKER DOMAIN (2 entities: ConsistencyLoop, ConsistencyLoopIteration)
    %% ============================================
    
    ConsistencyLoop ||--o{ ConsistencyLoopIteration : "contains"
    
    ConsistencyLoop {
        text Id PK
        text ProjectId FK
        int InitialScore
        int TargetScore
        int FinalScore
        bool TargetReached
        int TotalIterations
        int TotalFixesApplied
        text StopReason
        json Config
        text StartedAt
        text CompletedAt
        text CreatedAt
        text UpdatedAt
    }
    
    ConsistencyLoopIteration {
        text Id PK
        text LoopId FK
        int Iteration
        int Score
        int ScoreDelta
        int FindingsCount
        int FixesGenerated
        int FixesApplied
        int DurationMs
        json ReportJson
        text CreatedAt
        text UpdatedAt
    }
```

---

## Entity Count by Domain

| Domain | Entities | Count |
|--------|----------|-------|
| **User & Auth** | User, Session | 2 |
| **Project & Organization** | Project, ProjectMetadata, VectorIndexMetadata | 3 |
| **File & Snapshots** | File, Snapshot | 2 |
| **Configuration** | Config, ConfigSeedEvent | 2 |
| **LLM Models** | ModelRegistry, ModelSlot | 2 |
| **Prompt System** | PromptPreset, PromptPresetVersion, UserPromptOverride | 3 |
| **Instructions** | Instruction, InstructionTask, FileChange, InstructionSegment, MemoryEntry | 5 |
| **Inconsistency Analysis** | InconsistencyReport, InconsistencyIssue, ClarificationQuestion, ClarificationAnswer, RegenerationEvent | 5 |
| **RAG** | Artifact, Chunk, Embedding, RetrievalSession, RetrievalSessionChunk, PromotionEvent | 6 |
| **Consistency Checker** | ConsistencyLoop, ConsistencyLoopIteration | 2 |
| **Total** | | **32** |

---

## Domain Summaries

### User Domain (2 entities)

Core user management with sessions.

```
User ─┬─< Session
      ├─< Project
      ├─< PromptPreset
      └─< UserPromptOverride
```

### Project Domain (3 entities)

Hierarchical project organization with metadata and vector indexing.

```
Project ─┬─< Project (children)
         ├── ProjectMetadata
         ├── VectorIndexMetadata
         ├─< File
         ├─< Snapshot
         ├─< Artifact
         └─< ConsistencyLoop
```

### Instruction Domain (5 entities)

AI instruction processing with task breakdown, file tracking, segmentation, and memory.

```
Instruction ─┬─< InstructionTask ─< FileChange
             ├── InconsistencyReport
             ├─< InstructionSegment
             ├─< MemoryEntry
             └─< RegenerationEvent
```

### Inconsistency Analysis Domain (5 entities)

Quality analysis with clarification workflow.

```
InconsistencyReport ─< InconsistencyIssue ─< ClarificationQuestion ── ClarificationAnswer
```

### RAG Domain (6 entities)

Retrieval-Augmented Generation with artifact promotion.

```
Artifact ─┬─< Chunk ── Embedding
          └─< PromotionEvent

RetrievalSession ─< RetrievalSessionChunk ─── Chunk
```

### Consistency Checker Domain (2 entities)

Iterative consistency loop with scoring.

```
ConsistencyLoop ─< ConsistencyLoopIteration
```

---

## Key Relationships Summary

| From | To | Type | Constraint |
|------|-----|------|------------|
| User | Session | 1:N | CASCADE |
| User | Project | 1:N | CASCADE |
| Project | Project | 1:N (self) | CASCADE |
| Project | File | 1:N | CASCADE |
| Project | VectorIndexMetadata | 1:1 | CASCADE |
| Instruction | InstructionTask | 1:N | CASCADE |
| InstructionTask | FileChange | 1:N | CASCADE |
| Instruction | InstructionSegment | 1:N | CASCADE |
| Instruction | MemoryEntry | 1:N | CASCADE |
| Artifact | Chunk | 1:N | CASCADE |
| Chunk | Embedding | 1:1 | CASCADE |
| ConsistencyLoop | ConsistencyLoopIteration | 1:N | CASCADE |

---

## Related Specs

- [Schema Definition](../01-schema.md) — Complete GORM models (32 entities)
- [Relationships](../03-relationships.md) — FK constraints detail
- [System Architecture](./02-system-architecture.md) — Overall system design
