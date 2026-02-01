# Database Schema (ORM-Based)

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28

---

## ORM-Only Policy

> **CRITICAL**: All database operations MUST use GORM ORM. Raw SQL is forbidden.

This policy exists to:
- **Prevent SQL injection** vulnerabilities
- Ensure **consistent query patterns** across the codebase
- Enable **proper migration tracking** via AutoMigrate
- Maintain **type safety** through Go struct definitions

### Required ORM Features

| Feature | Implementation |
|---------|---------------|
| Parameterized Queries | GORM's built-in query builder |
| Migrations | `db.AutoMigrate()` |
| Transactional Writes | `db.Transaction()` |
| Relationship Mapping | GORM associations (HasMany, BelongsTo) |
| Query Building | GORM methods, never string concatenation |
| Soft Deletes | `gorm.DeletedAt` field |

### Only Exception: FTS5 Virtual Tables

Full-Text Search (FTS5) virtual tables require `db.Exec()` or `db.Raw()` as GORM doesn't natively support SQLite virtual tables. This is the ONLY acceptable use of raw SQL.

---

## Summary

SQLite database schema for the Spec Management Software using **GORM ORM models** in Go. All entities are defined as Go structs with GORM tags. The ORM handles table creation, migrations, indexes, and query building automatically.

---

## Entity Relationship Diagram

```mermaid
erDiagram
    User ||--o{ Project : "owns"
    User ||--o{ Snapshot : "creates"
    User ||--o{ Session : "has"
    User ||--o{ Instruction : "creates"
    User ||--o{ PromptPreset : "creates"
    User ||--o{ UserPromptOverride : "has"
    
    Project ||--o{ Project : "parent"
    Project ||--o{ File : "contains"
    Project ||--o{ Snapshot : "has"
    Project ||--o{ Instruction : "has"
    Project ||--|| ProjectMetadata : "has"
    Project ||--o{ Artifact : "contains"
    Project ||--o{ RetrievalSession : "has"
    
    File ||--o{ File : "parent"
    
    PromptPreset ||--o{ PromptPresetVersion : "has"
    PromptPreset ||--o{ UserPromptOverride : "customizes"
    
    Instruction ||--o{ InstructionTask : "has"
    Instruction ||--o| InconsistencyReport : "analyzed_by"
    Instruction ||--o| Artifact : "promoted_from"
    
    InconsistencyReport ||--o{ InconsistencyIssue : "contains"
    InconsistencyIssue ||--o{ ClarificationQuestion : "generates"
    ClarificationQuestion ||--o| ClarificationAnswer : "answered_by"
    
    Artifact ||--o{ Chunk : "split_into"
    Artifact ||--o| Instruction : "promotes_to"
    
    Chunk ||--|| Embedding : "has"
    
    RetrievalSession ||--o{ RetrievalSessionChunk : "uses"
    RetrievalSessionChunk }o--|| Chunk : "references"
```

---

## Base Models

All entities inherit from common base models for consistent ID generation and timestamps.

```go
package models

import (
    "time"
    
    "github.com/google/uuid"
    "gorm.io/datatypes"
    "gorm.io/gorm"
)

// BaseModel provides common fields for all entities with soft delete
type BaseModel struct {
    Id        string         `gorm:"type:text;primaryKey" json:"id"`
    CreatedAt time.Time      `gorm:"not null" json:"createdAt"`
    UpdatedAt time.Time      `gorm:"not null" json:"updatedAt"`
    DeletedAt gorm.DeletedAt `gorm:"index" json:"-"`
}

// BeforeCreate generates UUID if not set
func (b *BaseModel) BeforeCreate(tx *gorm.DB) error {
    if b.Id == "" {
        b.Id = uuid.New().String()
    }
    return nil
}

// TimestampModel for entities that only need CreatedAt (no updates)
type TimestampModel struct {
    Id        string    `gorm:"type:text;primaryKey" json:"id"`
    CreatedAt time.Time `gorm:"not null" json:"createdAt"`
}

func (t *TimestampModel) BeforeCreate(tx *gorm.DB) error {
    if t.Id == "" {
        t.Id = uuid.New().String()
    }
    return nil
}
```

---

## Core Tables

### User

```go
// User represents an authenticated user account
type User struct {
    BaseModel
    Username        string     `gorm:"type:text;not null;uniqueIndex:IX_User_Username" json:"username"`
    Email           string     `gorm:"type:text;not null;uniqueIndex:IX_User_Email" json:"email"`
    PasswordHash    string     `gorm:"type:text;not null" json:"-"`
    DisplayName     *string    `gorm:"type:text" json:"displayName"`
    ThemePreference string     `gorm:"type:text;default:'light'" json:"themePreference"`
    LastLoginAt     *time.Time `gorm:"type:text" json:"lastLoginAt"`
    
    // Relations
    Sessions        []Session           `gorm:"foreignKey:UserId;constraint:OnDelete:CASCADE" json:"-"`
    Projects        []Project           `gorm:"foreignKey:OwnerId;constraint:OnDelete:CASCADE" json:"-"`
    Snapshots       []Snapshot          `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL" json:"-"`
    Instructions    []Instruction       `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL" json:"-"`
    PromptPresets   []PromptPreset      `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL" json:"-"`
    PromptOverrides []UserPromptOverride `gorm:"foreignKey:UserId;constraint:OnDelete:CASCADE" json:"-"`
}

func (User) TableName() string { return "User" }
```

---

### Session

```go
// Session represents an active user session
type Session struct {
    TimestampModel
    UserId    string    `gorm:"type:text;not null;index:IX_Session_UserId" json:"userId"`
    Token     string    `gorm:"type:text;not null;uniqueIndex:IX_Session_Token" json:"-"`
    ExpiresAt time.Time `gorm:"type:text;not null;index:IX_Session_ExpiresAt" json:"expiresAt"`
    
    // Relations
    User User `gorm:"foreignKey:UserId;constraint:OnDelete:CASCADE" json:"-"`
}

func (Session) TableName() string { return "Session" }

func (s *Session) IsExpired() bool {
    return time.Now().After(s.ExpiresAt)
}
```

---

### Project

```go
// ProjectType enum
type ProjectType string

const (
    ProjectTypeCategory ProjectType = "category"
    ProjectTypeProject  ProjectType = "project"
)

// Visibility enum - controls project access scope
type Visibility string

const (
    // VisibilityUser - Project visible only to owner
    VisibilityUser Visibility = "user"
    // VisibilityGlobal - Project visible to all authenticated users (read-only for non-owners)
    VisibilityGlobal Visibility = "global"
)

// Project represents a spec project or category
type Project struct {
    BaseModel
    ParentId    *string     `gorm:"type:text;index:IX_Project_ParentId" json:"parentId"`
    OwnerId     string      `gorm:"type:text;not null;index:IX_Project_OwnerId" json:"ownerId"`
    Name        string      `gorm:"type:text;not null" json:"name"`
    Slug        string      `gorm:"type:text;not null;uniqueIndex:IX_Project_Slug" json:"slug"`
    Path        string      `gorm:"type:text;not null;uniqueIndex:IX_Project_Path" json:"path"`
    Type        ProjectType `gorm:"type:text;not null;index:IX_Project_Type" json:"type"`
    Description *string     `gorm:"type:text" json:"description"`
    SortOrder   int         `gorm:"default:0" json:"sortOrder"`
    
    // Visibility controls who can see this project
    // "user" = only owner, "global" = all authenticated users
    Visibility  Visibility  `gorm:"type:text;not null;default:'user';index:IX_Project_Visibility" json:"visibility"`
    
    // Relations
    Parent       *Project         `gorm:"foreignKey:ParentId;constraint:OnDelete:CASCADE" json:"-"`
    Children     []Project        `gorm:"foreignKey:ParentId" json:"children,omitempty"`
    Owner        User             `gorm:"foreignKey:OwnerId;constraint:OnDelete:CASCADE" json:"-"`
    Files        []File           `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    Snapshots    []Snapshot       `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    Metadata     *ProjectMetadata `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"metadata,omitempty"`
    Instructions []Instruction    `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
}

func (Project) TableName() string { return "Project" }

// IsOwner checks if the given user owns this project
func (p *Project) IsOwner(userId string) bool {
    return p.OwnerId == userId
}

// CanView checks if the given user can view this project
func (p *Project) CanView(userId string) bool {
    return p.OwnerId == userId || p.Visibility == VisibilityGlobal
}

// CanEdit checks if the given user can edit this project
func (p *Project) CanEdit(userId string) bool {
    // Only owner can edit, regardless of visibility
    return p.OwnerId == userId
}
```

---

### ProjectMetadata

```go
// ProjectMetadata stores extended project information
type ProjectMetadata struct {
    BaseModel
    ProjectId              string         `gorm:"type:text;not null;uniqueIndex:IX_ProjectMetadata_ProjectId" json:"projectId"`
    Version                string         `gorm:"type:text;default:'1.0.0'" json:"version"`
    Summary                *string        `gorm:"type:text" json:"summary"`
    AuthorName             *string        `gorm:"type:text" json:"authorName"`
    AuthorEmail            *string        `gorm:"type:text" json:"authorEmail"`
    DesignerName           *string        `gorm:"type:text" json:"designerName"`
    ResponsiblePersonName  *string        `gorm:"type:text" json:"responsiblePersonName"`
    ResponsiblePersonEmail *string        `gorm:"type:text" json:"responsiblePersonEmail"`
    Language               *string        `gorm:"type:text;index:IX_ProjectMetadata_Language" json:"language"`
    Framework              *string        `gorm:"type:text;index:IX_ProjectMetadata_Framework" json:"framework"`
    Tags                   datatypes.JSON `gorm:"type:text" json:"tags"`
    GuidelineOverrides     datatypes.JSON `gorm:"type:text" json:"guidelineOverrides"`
    AiSettings             datatypes.JSON `gorm:"type:text" json:"aiSettings"`
    CustomMetadata         datatypes.JSON `gorm:"type:text" json:"customMetadata"`
    MetadataFileHash       *string        `gorm:"type:text" json:"metadataFileHash"`
    LastSyncedAt           *time.Time     `gorm:"type:text" json:"lastSyncedAt"`
    
    // Relations
    Project Project `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
}

func (ProjectMetadata) TableName() string { return "ProjectMetadata" }
```

---

### File

```go
// FileType enum
type FileType string

const (
    FileTypeFolder FileType = "folder"
    FileTypeFile   FileType = "file"
)

// File represents a file or folder in a project
type File struct {
    BaseModel
    ProjectId   string   `gorm:"type:text;not null;index:IX_File_ProjectId" json:"projectId"`
    ParentId    *string  `gorm:"type:text;index:IX_File_ParentId" json:"parentId"`
    Name        string   `gorm:"type:text;not null" json:"name"`
    Path        string   `gorm:"type:text;not null;uniqueIndex:IX_File_Path" json:"path"`
    Type        FileType `gorm:"type:text;not null;index:IX_File_Type" json:"type"`
    ContentHash *string  `gorm:"type:text" json:"contentHash"`
    SortOrder   int      `gorm:"default:0" json:"sortOrder"`
    
    // Relations
    Project  Project `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    Parent   *File   `gorm:"foreignKey:ParentId;constraint:OnDelete:CASCADE" json:"-"`
    Children []File  `gorm:"foreignKey:ParentId" json:"children,omitempty"`
}

func (File) TableName() string { return "File" }
```

---

### Snapshot

```go
// Snapshot represents a version snapshot of a project
type Snapshot struct {
    TimestampModel
    ProjectId   string  `gorm:"type:text;not null;index:IX_Snapshot_ProjectId" json:"projectId"`
    CreatedById string  `gorm:"type:text;not null;index:IX_Snapshot_CreatedById" json:"createdById"`
    Name        string  `gorm:"type:text;not null;uniqueIndex:IX_Snapshot_Name" json:"name"`
    Description *string `gorm:"type:text" json:"description"`
    FolderPath  string  `gorm:"type:text;not null" json:"folderPath"`
    
    // Relations
    Project   Project `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    CreatedBy User    `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL" json:"-"`
}

func (Snapshot) TableName() string { return "Snapshot" }
```

---

## Configuration Tables

### Config

```go
// ConfigSource enum
type ConfigSource string

const (
    ConfigSourceSeed ConfigSource = "seed"
    ConfigSourceUser ConfigSource = "user"
)

// Config represents a configuration key-value pair
type Config struct {
    Key         string       `gorm:"type:text;primaryKey" json:"key"`
    Value       string       `gorm:"type:text;not null" json:"value"`
    Source      ConfigSource `gorm:"type:text;not null" json:"source"`
    Description *string      `gorm:"type:text" json:"description"`
    UpdatedAt   time.Time    `gorm:"not null" json:"updatedAt"`
}

func (Config) TableName() string { return "Config" }
```

---

### ConfigSeedEvent

```go
// SeedEventType enum
type SeedEventType string

const (
    SeedEventTypeSeed       SeedEventType = "seed"
    SeedEventTypeReseed     SeedEventType = "reseed"
    SeedEventTypeReset      SeedEventType = "reset"
    SeedEventTypeUpdate     SeedEventType = "update"
    SeedEventTypePresetSeed SeedEventType = "preset_seed"
)

// ConfigSeedEvent records seeding and config change events
type ConfigSeedEvent struct {
    TimestampModel
    EventType    SeedEventType  `gorm:"type:text;not null;index:IX_ConfigSeedEvent_EventType" json:"eventType"`
    IsFirstSeed  bool           `gorm:"default:false" json:"isFirstSeed"`
    KeysSeeded   int            `gorm:"default:0" json:"keysSeeded"`
    KeysModified datatypes.JSON `gorm:"type:text" json:"keysModified"`
    SeedSource   *string        `gorm:"type:text" json:"seedSource"`
    UserId       *string        `gorm:"type:text" json:"userId"`
    EventData    datatypes.JSON `gorm:"type:text" json:"eventData"`
    
    // Relations
    User *User `gorm:"foreignKey:UserId;constraint:OnDelete:SET NULL" json:"-"`
}

func (ConfigSeedEvent) TableName() string { return "ConfigSeedEvent" }
```

---

## LLM Model Tables

### ModelRegistry

```go
// ModelType enum
type ModelType string

const (
    ModelTypeReasoning ModelType = "reasoning"
    ModelTypeVoice     ModelType = "voice"
)

// ModelRegistry stores available AI models
type ModelRegistry struct {
    BaseModel
    DisplayName    string         `gorm:"type:text;not null" json:"displayName"`
    FileName       string         `gorm:"type:text;not null;uniqueIndex:IX_ModelRegistry_FileName" json:"fileName"`
    ModelType      ModelType      `gorm:"type:text;not null;index:IX_ModelRegistry_ModelType" json:"modelType"`
    ModelPath      string         `gorm:"type:text;not null" json:"modelPath"`
    FileSizeBytes  int64          `gorm:"not null" json:"fileSizeBytes"`
    Tags           datatypes.JSON `gorm:"type:text" json:"tags"`
    IsEnabled      bool           `gorm:"default:true;index:IX_ModelRegistry_IsEnabled" json:"isEnabled"`
    ContextSize    *int           `gorm:"type:integer" json:"contextSize"`
    GpuLayers      *int           `gorm:"type:integer" json:"gpuLayers"`
    LastScannedAt  time.Time      `gorm:"not null" json:"lastScannedAt"`
    
    // Relations
    ModelSlots   []ModelSlot   `gorm:"foreignKey:ModelId;constraint:OnDelete:SET NULL" json:"-"`
    Instructions []Instruction `gorm:"foreignKey:ReasoningModelId;constraint:OnDelete:SET NULL" json:"-"`
}

func (ModelRegistry) TableName() string { return "ModelRegistry" }
```

---

### ModelSlot

```go
// SlotStatus enum
type SlotStatus string

const (
    SlotStatusIdle      SlotStatus = "idle"
    SlotStatusLoading   SlotStatus = "loading"
    SlotStatusActive    SlotStatus = "active"
    SlotStatusError     SlotStatus = "error"
    SlotStatusUnloading SlotStatus = "unloading"
)

// ModelSlot tracks active LLM model slots
type ModelSlot struct {
    BaseModel
    SlotIndex         int        `gorm:"not null;uniqueIndex:IX_ModelSlot_SlotIndex" json:"slotIndex"`
    Port              int        `gorm:"not null;uniqueIndex:IX_ModelSlot_Port" json:"port"`
    ModelId           *string    `gorm:"type:text;index:IX_ModelSlot_ModelId" json:"modelId"`
    Status            SlotStatus `gorm:"type:text;not null;index:IX_ModelSlot_Status" json:"status"`
    ProcessId         *int       `gorm:"type:integer" json:"processId"`
    StartedAt         *time.Time `gorm:"type:text" json:"startedAt"`
    LastAccessedAt    *time.Time `gorm:"type:text;index:IX_ModelSlot_LastAccessedAt" json:"lastAccessedAt"`
    LastHealthCheckAt *time.Time `gorm:"type:text" json:"lastHealthCheckAt"`
    ErrorMessage      *string    `gorm:"type:text" json:"errorMessage"`
    
    // Relations
    Model *ModelRegistry `gorm:"foreignKey:ModelId;constraint:OnDelete:SET NULL" json:"-"`
}

func (ModelSlot) TableName() string { return "ModelSlot" }
```

---

## Prompt Preset Tables

### PromptPreset

```go
// ContentType enum for prompt presets
type ContentType string

const (
    ContentTypeIdea            ContentType = "idea"
    ContentTypeFeature         ContentType = "feature"
    ContentTypeTask            ContentType = "task"
    ContentTypeCodingGuideline ContentType = "codingGuideline"
    ContentTypeInstruction     ContentType = "instruction"
)

// PromptPreset stores base prompt templates
type PromptPreset struct {
    BaseModel
    Name           string      `gorm:"type:text;not null" json:"name"`
    ContentType    ContentType `gorm:"type:text;not null;index:IX_PromptPreset_ContentType" json:"contentType"`
    PromptText     string      `gorm:"type:text;not null" json:"promptText"`
    Description    *string     `gorm:"type:text" json:"description"`
    SourceFilePath *string     `gorm:"type:text" json:"sourceFilePath"`
    IsSystemPreset bool        `gorm:"default:false" json:"isSystemPreset"`
    IsDefault      bool        `gorm:"default:false;index:IX_PromptPreset_IsDefault" json:"isDefault"`
    CreatedById    *string     `gorm:"type:text" json:"createdById"`
    
    // Relations
    CreatedBy *User                  `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL" json:"-"`
    Versions  []PromptPresetVersion  `gorm:"foreignKey:PresetId;constraint:OnDelete:CASCADE" json:"-"`
    Overrides []UserPromptOverride   `gorm:"foreignKey:PresetId;constraint:OnDelete:CASCADE" json:"-"`
}

func (PromptPreset) TableName() string { return "PromptPreset" }
```

---

### PromptPresetVersion

```go
// PromptPresetVersion tracks version history for presets
type PromptPresetVersion struct {
    TimestampModel
    PresetId      string  `gorm:"type:text;not null;index:IX_PromptPresetVersion_Preset" json:"presetId"`
    VersionNumber int     `gorm:"not null" json:"versionNumber"`
    PromptText    string  `gorm:"type:text;not null" json:"promptText"`
    ChangeNote    *string `gorm:"type:text" json:"changeNote"`
    CreatedById   *string `gorm:"type:text" json:"createdById"`
    
    // Relations
    Preset    PromptPreset `gorm:"foreignKey:PresetId;constraint:OnDelete:CASCADE" json:"-"`
    CreatedBy *User        `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL" json:"-"`
}

func (PromptPresetVersion) TableName() string { return "PromptPresetVersion" }
```

---

### UserPromptOverride

```go
// OverrideMode enum
type OverrideMode string

const (
    OverrideModeAppend  OverrideMode = "append"
    OverrideModeReplace OverrideMode = "replace"
)

// UserPromptOverride stores user customizations on presets
type UserPromptOverride struct {
    BaseModel
    UserId           string       `gorm:"type:text;not null;index:IX_UserPromptOverride_User" json:"userId"`
    PresetId         string       `gorm:"type:text;not null" json:"presetId"`
    ProjectId        *string      `gorm:"type:text;index:IX_UserPromptOverride_Project" json:"projectId"`
    OverrideMode     OverrideMode `gorm:"type:text;not null" json:"overrideMode"`
    CustomPromptText string       `gorm:"type:text;not null" json:"customPromptText"`
    IsActive         bool         `gorm:"default:true" json:"isActive"`
    
    // Relations
    User    User          `gorm:"foreignKey:UserId;constraint:OnDelete:CASCADE" json:"-"`
    Preset  PromptPreset  `gorm:"foreignKey:PresetId;constraint:OnDelete:CASCADE" json:"-"`
    Project *Project      `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
}

func (UserPromptOverride) TableName() string { return "UserPromptOverride" }
```

---

## Instruction System Tables

### Instruction

```go
// InstructionStatus enum
type InstructionStatus string

const (
    InstructionStatusTranscribed  InstructionStatus = "transcribed"
    InstructionStatusProofreading InstructionStatus = "proofreading"
    InstructionStatusProofread    InstructionStatus = "proofread"
    InstructionStatusPlanning     InstructionStatus = "planning"
    InstructionStatusPlanned      InstructionStatus = "planned"
    InstructionStatusReviewing    InstructionStatus = "reviewing"
    InstructionStatusReady        InstructionStatus = "ready"
    InstructionStatusExecuting    InstructionStatus = "executing"
    InstructionStatusCompleted    InstructionStatus = "completed"
    InstructionStatusFailed       InstructionStatus = "failed"
    InstructionStatusCancelled    InstructionStatus = "cancelled"
)

// InstructionScope enum
type InstructionScope string

const (
    InstructionScopeGlobal   InstructionScope = "global"
    InstructionScopeBackend  InstructionScope = "backend"
    InstructionScopeFrontend InstructionScope = "frontend"
    InstructionScopeFile     InstructionScope = "file"
)

// ExecutionMode enum
type ExecutionMode string

const (
    ExecutionModeAutomatic ExecutionMode = "automatic"
    ExecutionModeApproval  ExecutionMode = "approval"
)

// InputType enum
type InputType string

const (
    InputTypeVoice InputType = "voice"
    InputTypeText  InputType = "text"
)

// Instruction stores voice/text instructions
type Instruction struct {
    BaseModel
    ProjectId          string            `gorm:"type:text;not null;index:IX_Instruction_ProjectId" json:"projectId"`
    CreatedById        string            `gorm:"type:text;not null" json:"createdById"`
    ContentType        *ContentType      `gorm:"type:text;index:IX_Instruction_ContentType" json:"contentType"`
    InputType          InputType         `gorm:"type:text;not null" json:"inputType"`
    RawInput           string            `gorm:"type:text;not null" json:"rawInput"`
    TranscribedText    *string           `gorm:"type:text" json:"transcribedText"`
    ProofreadText      *string           `gorm:"type:text" json:"proofreadText"`
    EnhancedText       *string           `gorm:"type:text" json:"enhancedText"`
    InstructionText    *string           `gorm:"type:text" json:"instructionText"`
    Scope              InstructionScope  `gorm:"type:text;not null" json:"scope"`
    TargetFilePath     *string           `gorm:"type:text" json:"targetFilePath"`
    Status             InstructionStatus `gorm:"type:text;not null;index:IX_Instruction_Status" json:"status"`
    ExecutionMode      ExecutionMode     `gorm:"type:text;not null" json:"executionMode"`
    PresetId           *string           `gorm:"type:text" json:"presetId"`
    OverrideId         *string           `gorm:"type:text" json:"overrideId"`
    CustomPromptLayer  *string           `gorm:"type:text" json:"customPromptLayer"`
    FinalPrompt        *string           `gorm:"type:text" json:"finalPrompt"`
    ApprovedAt         *time.Time        `gorm:"type:text" json:"approvedAt"`
    ApprovedById       *string           `gorm:"type:text" json:"approvedById"`
    ReasoningModelId   *string           `gorm:"type:text" json:"reasoningModelId"`
    PlanningTokensUsed *int              `gorm:"type:integer" json:"planningTokensUsed"`
    PlanningDurationMs *int              `gorm:"type:integer" json:"planningDurationMs"`
    PlanMarkdown       *string           `gorm:"type:text" json:"planMarkdown"`
    PlanJson           datatypes.JSON    `gorm:"type:text" json:"planJson"`
    CompletedAt        *time.Time        `gorm:"type:text" json:"completedAt"`
    ErrorMessage       *string           `gorm:"type:text" json:"errorMessage"`
    RegeneratedFromId  *string           `gorm:"type:text" json:"regeneratedFromId"`
    
    // Relations
    Project           Project              `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    CreatedBy         User                 `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL" json:"-"`
    ApprovedBy        *User                `gorm:"foreignKey:ApprovedById;constraint:OnDelete:SET NULL" json:"-"`
    ReasoningModel    *ModelRegistry       `gorm:"foreignKey:ReasoningModelId;constraint:OnDelete:SET NULL" json:"-"`
    Preset            *PromptPreset        `gorm:"foreignKey:PresetId;constraint:OnDelete:SET NULL" json:"-"`
    Override          *UserPromptOverride  `gorm:"foreignKey:OverrideId;constraint:OnDelete:SET NULL" json:"-"`
    RegeneratedFrom   *Instruction         `gorm:"foreignKey:RegeneratedFromId;constraint:OnDelete:SET NULL" json:"-"`
    Tasks             []InstructionTask    `gorm:"foreignKey:InstructionId;constraint:OnDelete:CASCADE" json:"-"`
    Report            *InconsistencyReport `gorm:"foreignKey:InstructionId;constraint:OnDelete:CASCADE" json:"-"`
}

func (Instruction) TableName() string { return "Instruction" }
```

---

### InstructionTask

```go
// TaskType enum
type TaskType string

const (
    TaskTypeCreate   TaskType = "create"
    TaskTypeUpdate   TaskType = "update"
    TaskTypeDelete   TaskType = "delete"
    TaskTypeRefactor TaskType = "refactor"
    TaskTypeReview   TaskType = "review"
    TaskTypeVerify   TaskType = "verify"
)

// TaskStatus enum
type TaskStatus string

const (
    TaskStatusPending    TaskStatus = "pending"
    TaskStatusInProgress TaskStatus = "in_progress"
    TaskStatusCompleted  TaskStatus = "completed"
    TaskStatusFailed     TaskStatus = "failed"
    TaskStatusSkipped    TaskStatus = "skipped"
)

// InstructionTask stores individual tasks from instruction planning
type InstructionTask struct {
    BaseModel
    InstructionId  string         `gorm:"type:text;not null;index:IX_InstructionTask_InstructionId" json:"instructionId"`
    ParentTaskId   *string        `gorm:"type:text;index:IX_InstructionTask_ParentTaskId" json:"parentTaskId"`
    Title          string         `gorm:"type:text;not null" json:"title"`
    Description    *string        `gorm:"type:text" json:"description"`
    TaskType       TaskType       `gorm:"type:text;not null" json:"taskType"`
    TargetFilePath *string        `gorm:"type:text" json:"targetFilePath"`
    TargetSection  *string        `gorm:"type:text" json:"targetSection"`
    SortOrder      int            `gorm:"default:0" json:"sortOrder"`
    Status         TaskStatus     `gorm:"type:text;not null;index:IX_InstructionTask_Status" json:"status"`
    ResultMarkdown *string        `gorm:"type:text" json:"resultMarkdown"`
    ResultJson     datatypes.JSON `gorm:"type:text" json:"resultJson"`
    ErrorMessage   *string        `gorm:"type:text" json:"errorMessage"`
    StartedAt      *time.Time     `gorm:"type:text" json:"startedAt"`
    CompletedAt    *time.Time     `gorm:"type:text" json:"completedAt"`
    
    // Relations
    Instruction Instruction       `gorm:"foreignKey:InstructionId;constraint:OnDelete:CASCADE" json:"-"`
    ParentTask  *InstructionTask  `gorm:"foreignKey:ParentTaskId;constraint:OnDelete:CASCADE" json:"-"`
    SubTasks    []InstructionTask `gorm:"foreignKey:ParentTaskId" json:"subTasks,omitempty"`
    FileChanges []FileChange      `gorm:"foreignKey:InstructionTaskId;constraint:OnDelete:CASCADE" json:"-"`
}

func (InstructionTask) TableName() string { return "InstructionTask" }
```

---

### FileChange

```go
// ChangeType enum
type ChangeType string

const (
    ChangeTypeCreated ChangeType = "created"
    ChangeTypeUpdated ChangeType = "updated"
    ChangeTypeDeleted ChangeType = "deleted"
    ChangeTypeRenamed ChangeType = "renamed"
)

// FileChange tracks individual file changes made by tasks
type FileChange struct {
    TimestampModel
    InstructionTaskId string     `gorm:"type:text;not null;index:IX_FileChange_InstructionTaskId" json:"instructionTaskId"`
    FileId            *string    `gorm:"type:text;index:IX_FileChange_FileId" json:"fileId"`
    FilePath          string     `gorm:"type:text;not null;index:IX_FileChange_FilePath" json:"filePath"`
    ChangeType        ChangeType `gorm:"type:text;not null" json:"changeType"`
    BeforeHash        *string    `gorm:"type:text" json:"beforeHash"`
    AfterHash         *string    `gorm:"type:text" json:"afterHash"`
    DiffContent       *string    `gorm:"type:text" json:"diffContent"`
    BeforeSnapshot    *string    `gorm:"type:text" json:"beforeSnapshot"`
    AfterSnapshot     *string    `gorm:"type:text" json:"afterSnapshot"`
    BytesBefore       int        `gorm:"default:0" json:"bytesBefore"`
    BytesAfter        int        `gorm:"default:0" json:"bytesAfter"`
    
    // Relations
    InstructionTask InstructionTask `gorm:"foreignKey:InstructionTaskId;constraint:OnDelete:CASCADE" json:"-"`
    File            *File           `gorm:"foreignKey:FileId;constraint:OnDelete:SET NULL" json:"-"`
}

func (FileChange) TableName() string { return "FileChange" }
```

---

## Inconsistency Detection Tables

### InconsistencyReport

```go
// ReportStatus enum
type ReportStatus string

const (
    ReportStatusPending  ReportStatus = "pending"
    ReportStatusOpen     ReportStatus = "open"
    ReportStatusResolved ReportStatus = "resolved"
    ReportStatusIgnored  ReportStatus = "ignored"
)

// InconsistencyReport stores analysis results
type InconsistencyReport struct {
    BaseModel
    InstructionId   string         `gorm:"type:text;not null;uniqueIndex:IX_InconsistencyReport_Instruction" json:"instructionId"`
    TotalIssues     int            `gorm:"default:0" json:"totalIssues"`
    PhaseACritical  int            `gorm:"default:0" json:"phaseACritical"`
    PhaseBConflict  int            `gorm:"default:0" json:"phaseBConflict"`
    PhaseCAmbiguous int            `gorm:"default:0" json:"phaseCAmbiguous"`
    PhaseDOptional  int            `gorm:"default:0" json:"phaseDOptional"`
    AnalysisOutput  datatypes.JSON `gorm:"type:text" json:"analysisOutput"`
    Status          ReportStatus   `gorm:"type:text;not null;index:IX_InconsistencyReport_Status" json:"status"`
    ResolvedAt      *time.Time     `gorm:"type:text" json:"resolvedAt"`
    
    // Relations
    Instruction Instruction           `gorm:"foreignKey:InstructionId;constraint:OnDelete:CASCADE" json:"-"`
    Issues      []InconsistencyIssue  `gorm:"foreignKey:ReportId;constraint:OnDelete:CASCADE" json:"-"`
}

func (InconsistencyReport) TableName() string { return "InconsistencyReport" }
```

---

### InconsistencyIssue

```go
// IssuePhase enum
type IssuePhase string

const (
    IssuePhaseA IssuePhase = "A"
    IssuePhaseB IssuePhase = "B"
    IssuePhaseC IssuePhase = "C"
    IssuePhaseD IssuePhase = "D"
)

// IssueCategory enum
type IssueCategory string

const (
    IssueCategoryMissingData  IssueCategory = "missing_data"
    IssueCategoryConflict     IssueCategory = "conflict"
    IssueCategoryAmbiguity    IssueCategory = "ambiguity"
    IssueCategoryEnhancement  IssueCategory = "enhancement"
)

// IssueSeverity enum
type IssueSeverity string

const (
    IssueSeverityCritical IssueSeverity = "critical"
    IssueSeverityHigh     IssueSeverity = "high"
    IssueSeverityMedium   IssueSeverity = "medium"
    IssueSeverityLow      IssueSeverity = "low"
)

// IssueStatus enum
type IssueStatus string

const (
    IssueStatusOpen     IssueStatus = "open"
    IssueStatusResolved IssueStatus = "resolved"
    IssueStatusIgnored  IssueStatus = "ignored"
)

// InconsistencyIssue stores individual issues
type InconsistencyIssue struct {
    BaseModel
    ReportId    string        `gorm:"type:text;not null;index:IX_InconsistencyIssue_Report" json:"reportId"`
    Phase       IssuePhase    `gorm:"type:text;not null;index:IX_InconsistencyIssue_Phase" json:"phase"`
    Category    IssueCategory `gorm:"type:text;not null" json:"category"`
    Title       string        `gorm:"type:text;not null" json:"title"`
    Description string        `gorm:"type:text;not null" json:"description"`
    Location    *string       `gorm:"type:text" json:"location"`
    Severity    IssueSeverity `gorm:"type:text;not null" json:"severity"`
    Status      IssueStatus   `gorm:"type:text;not null;default:'open';index:IX_InconsistencyIssue_Status" json:"status"`
    ResolvedAt  *time.Time    `gorm:"type:text" json:"resolvedAt"`
    
    // Relations
    Report    InconsistencyReport     `gorm:"foreignKey:ReportId;constraint:OnDelete:CASCADE" json:"-"`
    Questions []ClarificationQuestion `gorm:"foreignKey:IssueId;constraint:OnDelete:CASCADE" json:"-"`
}

func (InconsistencyIssue) TableName() string { return "InconsistencyIssue" }
```

---

### ClarificationQuestion

```go
// AnswerType enum
type AnswerType string

const (
    AnswerTypeRadio       AnswerType = "radio"
    AnswerTypeCheckbox    AnswerType = "checkbox"
    AnswerTypeText        AnswerType = "text"
    AnswerTypeDropdown    AnswerType = "dropdown"
    AnswerTypeMultiSelect AnswerType = "multiSelect"
)

// ClarificationQuestion stores questions generated from issues
type ClarificationQuestion struct {
    TimestampModel
    IssueId           string         `gorm:"type:text;not null;index:IX_ClarificationQuestion_Issue" json:"issueId"`
    ReportId          string         `gorm:"type:text;not null;index:IX_ClarificationQuestion_Report" json:"reportId"`
    Phase             IssuePhase     `gorm:"type:text;not null" json:"phase"`
    QuestionText      string         `gorm:"type:text;not null" json:"questionText"`
    WhyItMatters      string         `gorm:"type:text;not null" json:"whyItMatters"`
    RecommendedAnswer *string        `gorm:"type:text" json:"recommendedAnswer"`
    AnswerType        AnswerType     `gorm:"type:text;not null" json:"answerType"`
    AnswerOptions     datatypes.JSON `gorm:"type:text" json:"answerOptions"`
    IsRequired        bool           `gorm:"default:true" json:"isRequired"`
    DisplayOrder      int            `gorm:"not null" json:"displayOrder"`
    
    // Relations
    Issue  InconsistencyIssue     `gorm:"foreignKey:IssueId;constraint:OnDelete:CASCADE" json:"-"`
    Report InconsistencyReport    `gorm:"foreignKey:ReportId;constraint:OnDelete:CASCADE" json:"-"`
    Answer *ClarificationAnswer   `gorm:"foreignKey:QuestionId;constraint:OnDelete:CASCADE" json:"answer,omitempty"`
}

func (ClarificationQuestion) TableName() string { return "ClarificationQuestion" }
```

---

### ClarificationAnswer

```go
// ClarificationAnswer stores user responses to questions
type ClarificationAnswer struct {
    TimestampModel
    QuestionId  string         `gorm:"type:text;not null;uniqueIndex:IX_ClarificationAnswer_Question" json:"questionId"`
    UserId      string         `gorm:"type:text;not null;index:IX_ClarificationAnswer_User" json:"userId"`
    AnswerValue datatypes.JSON `gorm:"type:text;not null" json:"answerValue"`
    AnswerText  *string        `gorm:"type:text" json:"answerText"`
    WasSkipped  bool           `gorm:"default:false" json:"wasSkipped"`
    
    // Relations
    Question ClarificationQuestion `gorm:"foreignKey:QuestionId;constraint:OnDelete:CASCADE" json:"-"`
    User     User                  `gorm:"foreignKey:UserId;constraint:OnDelete:SET NULL" json:"-"`
}

func (ClarificationAnswer) TableName() string { return "ClarificationAnswer" }
```

---

### RegenerationEvent

```go
// TriggerType enum
type TriggerType string

const (
    TriggerTypeManual    TriggerType = "manual"
    TriggerTypeAutomatic TriggerType = "automatic"
)

// RegenerationEvent tracks regeneration after answers
type RegenerationEvent struct {
    TimestampModel
    OriginalInstructionId string         `gorm:"type:text;not null;index:IX_RegenerationEvent_Original" json:"originalInstructionId"`
    NewInstructionId      string         `gorm:"type:text;not null;index:IX_RegenerationEvent_New" json:"newInstructionId"`
    ReportId              string         `gorm:"type:text;not null" json:"reportId"`
    AnswerCount           int            `gorm:"not null" json:"answerCount"`
    TriggerType           TriggerType    `gorm:"type:text;not null" json:"triggerType"`
    AdditionalContext     *string        `gorm:"type:text" json:"additionalContext"`
    CreatedById           *string        `gorm:"type:text" json:"createdById"`
    
    // Relations
    OriginalInstruction Instruction         `gorm:"foreignKey:OriginalInstructionId;constraint:OnDelete:CASCADE" json:"-"`
    NewInstruction      Instruction         `gorm:"foreignKey:NewInstructionId;constraint:OnDelete:CASCADE" json:"-"`
    Report              InconsistencyReport `gorm:"foreignKey:ReportId;constraint:OnDelete:CASCADE" json:"-"`
    CreatedBy           *User               `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL" json:"-"`
}

func (RegenerationEvent) TableName() string { return "RegenerationEvent" }
```

---

## RAG (Retrieval-Augmented Generation) Tables

### Artifact

```go
// ArtifactType enum
type ArtifactType string

const (
    ArtifactTypeIdea        ArtifactType = "idea"
    ArtifactTypeInstruction ArtifactType = "instruction"
)

// ArtifactStatus enum
type ArtifactStatus string

const (
    ArtifactStatusDraft     ArtifactStatus = "draft"
    ArtifactStatusActive    ArtifactStatus = "active"
    ArtifactStatusPromoted  ArtifactStatus = "promoted"
    ArtifactStatusArchived  ArtifactStatus = "archived"
)

// Artifact represents an idea or instruction file in the RAG system
type Artifact struct {
    BaseModel
    ProjectId        string         `gorm:"type:text;not null;index:IX_Artifact_ProjectId" json:"projectId"`
    ArtifactType     ArtifactType   `gorm:"type:text;not null;index:IX_Artifact_Type" json:"artifactType"`
    Status           ArtifactStatus `gorm:"type:text;not null;index:IX_Artifact_Status" json:"status"`
    SequenceNumber   int            `gorm:"not null" json:"sequenceNumber"`
    Slug             string         `gorm:"type:text;not null" json:"slug"`
    Title            string         `gorm:"type:text;not null" json:"title"`
    RelativePath     string         `gorm:"type:text;not null;uniqueIndex:IX_Artifact_Path" json:"relativePath"`
    ContentHash      string         `gorm:"type:text;not null" json:"contentHash"`
    WordCount        int            `gorm:"default:0" json:"wordCount"`
    ChunkCount       int            `gorm:"default:0" json:"chunkCount"`
    IsPinned         bool           `gorm:"default:false;index:IX_Artifact_IsPinned" json:"isPinned"`
    PinnedOrder      *int           `gorm:"type:integer" json:"pinnedOrder"`
    SourceIdeaId     *string        `gorm:"type:text;index:IX_Artifact_SourceIdea" json:"sourceIdeaId"`
    PromotedToId     *string        `gorm:"type:text;index:IX_Artifact_PromotedTo" json:"promotedToId"`
    InstructionId    *string        `gorm:"type:text;index:IX_Artifact_Instruction" json:"instructionId"`
    LastIndexedAt    *time.Time     `gorm:"type:text" json:"lastIndexedAt"`
    IndexVersion     int            `gorm:"default:1" json:"indexVersion"`
    
    // Relations
    Project     Project      `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    SourceIdea  *Artifact    `gorm:"foreignKey:SourceIdeaId;constraint:OnDelete:SET NULL" json:"-"`
    PromotedTo  *Artifact    `gorm:"foreignKey:PromotedToId;constraint:OnDelete:SET NULL" json:"-"`
    Instruction *Instruction `gorm:"foreignKey:InstructionId;constraint:OnDelete:SET NULL" json:"-"`
    Chunks      []Chunk      `gorm:"foreignKey:ArtifactId;constraint:OnDelete:CASCADE" json:"-"`
}

func (Artifact) TableName() string { return "Artifact" }
```

---

### Chunk

```go
// ChunkType enum
type ChunkType string

const (
    ChunkTypeHeader    ChunkType = "header"
    ChunkTypeParagraph ChunkType = "paragraph"
    ChunkTypeCode      ChunkType = "code"
    ChunkTypeList      ChunkType = "list"
    ChunkTypeTable     ChunkType = "table"
)

// Chunk represents a content segment from an artifact
type Chunk struct {
    BaseModel
    ArtifactId     string    `gorm:"type:text;not null;index:IX_Chunk_ArtifactId" json:"artifactId"`
    ChunkIndex     int       `gorm:"not null" json:"chunkIndex"`
    StableId       string    `gorm:"type:text;not null;uniqueIndex:IX_Chunk_StableId" json:"stableId"`
    ChunkType      ChunkType `gorm:"type:text;not null" json:"chunkType"`
    HeadingPath    *string   `gorm:"type:text" json:"headingPath"`
    HeadingLevel   *int      `gorm:"type:integer" json:"headingLevel"`
    Content        string    `gorm:"type:text;not null" json:"content"`
    ContentHash    string    `gorm:"type:text;not null" json:"contentHash"`
    TokenCount     int       `gorm:"default:0" json:"tokenCount"`
    CharCount      int       `gorm:"default:0" json:"charCount"`
    StartLine      int       `gorm:"not null" json:"startLine"`
    EndLine        int       `gorm:"not null" json:"endLine"`
    SectionAnchor  *string   `gorm:"type:text" json:"sectionAnchor"`
    
    // Relations
    Artifact  Artifact   `gorm:"foreignKey:ArtifactId;constraint:OnDelete:CASCADE" json:"-"`
    Embedding *Embedding `gorm:"foreignKey:ChunkId;constraint:OnDelete:CASCADE" json:"embedding,omitempty"`
}

func (Chunk) TableName() string { return "Chunk" }
```

---

### Embedding

```go
// Embedding stores vector embeddings for chunks
type Embedding struct {
    TimestampModel
    ChunkId         string  `gorm:"type:text;not null;uniqueIndex:IX_Embedding_ChunkId" json:"chunkId"`
    ModelName       string  `gorm:"type:text;not null;index:IX_Embedding_Model" json:"modelName"`
    ModelVersion    string  `gorm:"type:text;not null" json:"modelVersion"`
    Dimensions      int     `gorm:"not null" json:"dimensions"`
    EmbeddingVector []byte  `gorm:"type:blob;not null" json:"-"`
    Magnitude       float64 `gorm:"not null" json:"magnitude"`
    
    // Relations
    Chunk Chunk `gorm:"foreignKey:ChunkId;constraint:OnDelete:CASCADE" json:"-"`
}

func (Embedding) TableName() string { return "Embedding" }

// GetVector deserializes the embedding vector from bytes
func (e *Embedding) GetVector() ([]float32, error) {
    if len(e.EmbeddingVector) == 0 {
        return nil, nil
    }
    vector := make([]float32, e.Dimensions)
    buf := bytes.NewReader(e.EmbeddingVector)
    for i := range vector {
        if err := binary.Read(buf, binary.LittleEndian, &vector[i]); err != nil {
            return nil, err
        }
    }
    return vector, nil
}

// SetVector serializes the embedding vector to bytes
func (e *Embedding) SetVector(vector []float32) error {
    e.Dimensions = len(vector)
    buf := new(bytes.Buffer)
    for _, v := range vector {
        if err := binary.Write(buf, binary.LittleEndian, v); err != nil {
            return err
        }
    }
    e.EmbeddingVector = buf.Bytes()
    
    // Calculate magnitude for normalization
    var sum float64
    for _, v := range vector {
        sum += float64(v * v)
    }
    e.Magnitude = math.Sqrt(sum)
    return nil
}
```

---

### RetrievalSession

```go
// RetrievalSession tracks a RAG retrieval operation
type RetrievalSession struct {
    TimestampModel
    ProjectId        string         `gorm:"type:text;not null;index:IX_RetrievalSession_Project" json:"projectId"`
    UserId           *string        `gorm:"type:text;index:IX_RetrievalSession_User" json:"userId"`
    QueryText        string         `gorm:"type:text;not null" json:"queryText"`
    QueryHash        string         `gorm:"type:text;not null;index:IX_RetrievalSession_QueryHash" json:"queryHash"`
    TopK             int            `gorm:"not null" json:"topK"`
    SemanticWeight   float64        `gorm:"default:0.7" json:"semanticWeight"`
    KeywordWeight    float64        `gorm:"default:0.3" json:"keywordWeight"`
    TotalChunksFound int            `gorm:"default:0" json:"totalChunksFound"`
    PinnedIncluded   int            `gorm:"default:0" json:"pinnedIncluded"`
    DurationMs       int            `gorm:"default:0" json:"durationMs"`
    CacheHit         bool           `gorm:"default:false" json:"cacheHit"`
    ResultContext    datatypes.JSON `gorm:"type:text" json:"resultContext"`
    
    // Relations
    Project       Project                  `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    User          *User                    `gorm:"foreignKey:UserId;constraint:OnDelete:SET NULL" json:"-"`
    SessionChunks []RetrievalSessionChunk  `gorm:"foreignKey:SessionId;constraint:OnDelete:CASCADE" json:"-"`
}

func (RetrievalSession) TableName() string { return "RetrievalSession" }
```

---

### RetrievalSessionChunk

```go
// MatchSource enum
type MatchSource string

const (
    MatchSourceSemantic MatchSource = "semantic"
    MatchSourceKeyword  MatchSource = "keyword"
    MatchSourcePinned   MatchSource = "pinned"
    MatchSourceRecent   MatchSource = "recent"
)

// RetrievalSessionChunk links retrieved chunks to sessions
type RetrievalSessionChunk struct {
    TimestampModel
    SessionId       string      `gorm:"type:text;not null;index:IX_RetrievalSessionChunk_Session" json:"sessionId"`
    ChunkId         string      `gorm:"type:text;not null;index:IX_RetrievalSessionChunk_Chunk" json:"chunkId"`
    Rank            int         `gorm:"not null" json:"rank"`
    SemanticScore   float64     `gorm:"default:0" json:"semanticScore"`
    KeywordScore    float64     `gorm:"default:0" json:"keywordScore"`
    CombinedScore   float64     `gorm:"default:0;index:IX_RetrievalSessionChunk_Score" json:"combinedScore"`
    MatchSource     MatchSource `gorm:"type:text;not null" json:"matchSource"`
    WasUsedInPrompt bool        `gorm:"default:false" json:"wasUsedInPrompt"`
    
    // Relations
    Session RetrievalSession `gorm:"foreignKey:SessionId;constraint:OnDelete:CASCADE" json:"-"`
    Chunk   Chunk            `gorm:"foreignKey:ChunkId;constraint:OnDelete:CASCADE" json:"-"`
}

func (RetrievalSessionChunk) TableName() string { return "RetrievalSessionChunk" }
```

---

### PromotionEvent

```go
// PromotionEventStatus enum
type PromotionEventStatus string

const (
    PromotionEventStatusPending   PromotionEventStatus = "pending"
    PromotionEventStatusCompleted PromotionEventStatus = "completed"
    PromotionEventStatusFailed    PromotionEventStatus = "failed"
)

// PromotionEvent tracks idea-to-instruction promotions
type PromotionEvent struct {
    BaseModel
    ProjectId          string               `gorm:"type:text;not null;index:IX_PromotionEvent_Project" json:"projectId"`
    SourceArtifactId   string               `gorm:"type:text;not null;index:IX_PromotionEvent_Source" json:"sourceArtifactId"`
    TargetArtifactId   *string              `gorm:"type:text;index:IX_PromotionEvent_Target" json:"targetArtifactId"`
    TargetInstructionId *string             `gorm:"type:text;index:IX_PromotionEvent_Instruction" json:"targetInstructionId"`
    Status             PromotionEventStatus `gorm:"type:text;not null;default:'pending';index:IX_PromotionEvent_Status" json:"status"`
    PromotedById       *string              `gorm:"type:text;index:IX_PromotionEvent_User" json:"promotedById"`
    SourcePath         string               `gorm:"type:text;not null" json:"sourcePath"`
    TargetPath         *string              `gorm:"type:text" json:"targetPath"`
    PromotedAt         *time.Time           `gorm:"type:text" json:"promotedAt"`
    ReindexTriggered   bool                 `gorm:"default:false" json:"reindexTriggered"`
    ReindexCompletedAt *time.Time           `gorm:"type:text" json:"reindexCompletedAt"`
    ErrorMessage       *string              `gorm:"type:text" json:"errorMessage"`
    
    // Relations
    Project           Project      `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    SourceArtifact    Artifact     `gorm:"foreignKey:SourceArtifactId;constraint:OnDelete:CASCADE" json:"-"`
    TargetArtifact    *Artifact    `gorm:"foreignKey:TargetArtifactId;constraint:OnDelete:SET NULL" json:"-"`
    TargetInstruction *Instruction `gorm:"foreignKey:TargetInstructionId;constraint:OnDelete:SET NULL" json:"-"`
    PromotedBy        *User        `gorm:"foreignKey:PromotedById;constraint:OnDelete:SET NULL" json:"-"`
}

func (PromotionEvent) TableName() string { return "PromotionEvent" }
```

---

## Consistency Checker Tables

### ConsistencyLoop

```go
// LoopStopReason enum
type LoopStopReason string

const (
    LoopStopReasonTargetReached LoopStopReason = "target_reached"
    LoopStopReasonMaxIterations LoopStopReason = "max_iterations"
    LoopStopReasonStalled       LoopStopReason = "stalled"
    LoopStopReasonManualStop    LoopStopReason = "manual_stop"
    LoopStopReasonError         LoopStopReason = "error"
)

// ConsistencyLoop tracks iterative consistency check executions
type ConsistencyLoop struct {
    BaseModel
    ProjectId         string         `gorm:"type:text;not null;index:IX_ConsistencyLoop_Project" json:"projectId"`
    InitialScore      int            `gorm:"not null" json:"initialScore"`
    TargetScore       int            `gorm:"not null;default:99" json:"targetScore"`
    FinalScore        int            `gorm:"default:0" json:"finalScore"`
    TargetReached     bool           `gorm:"default:false;index:IX_ConsistencyLoop_TargetReached" json:"targetReached"`
    TotalIterations   int            `gorm:"default:0" json:"totalIterations"`
    TotalFixesApplied int            `gorm:"default:0" json:"totalFixesApplied"`
    StopReason        LoopStopReason `gorm:"type:text" json:"stopReason"`
    Config            datatypes.JSON `gorm:"type:text" json:"config"`
    StartedAt         time.Time      `gorm:"not null" json:"startedAt"`
    CompletedAt       *time.Time     `gorm:"type:text" json:"completedAt"`
    
    // Relations
    Project    Project                    `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
    Iterations []ConsistencyLoopIteration `gorm:"foreignKey:LoopId;constraint:OnDelete:CASCADE" json:"-"`
}

func (ConsistencyLoop) TableName() string { return "ConsistencyLoop" }
```

---

### ConsistencyLoopIteration

```go
// ConsistencyLoopIteration tracks each iteration in a loop
type ConsistencyLoopIteration struct {
    BaseModel
    LoopId         string         `gorm:"type:text;not null;index:IX_ConsistencyLoopIteration_Loop" json:"loopId"`
    Iteration      int            `gorm:"not null" json:"iteration"`
    Score          int            `gorm:"not null" json:"score"`
    ScoreDelta     int            `gorm:"default:0" json:"scoreDelta"`
    FindingsCount  int            `gorm:"default:0" json:"findingsCount"`
    FixesGenerated int            `gorm:"default:0" json:"fixesGenerated"`
    FixesApplied   int            `gorm:"default:0" json:"fixesApplied"`
    DurationMs     int            `gorm:"default:0" json:"durationMs"`
    ReportJson     datatypes.JSON `gorm:"type:text" json:"reportJson"`
    
    // Relations
    Loop ConsistencyLoop `gorm:"foreignKey:LoopId;constraint:OnDelete:CASCADE" json:"-"`
}

func (ConsistencyLoopIteration) TableName() string { return "ConsistencyLoopIteration" }
```

---

## Database Initialization

### Auto-Migration

```go
package database

import (
    "gorm.io/driver/sqlite"
    "gorm.io/gorm"
    "gorm.io/gorm/logger"
)

// AllModels returns all models for auto-migration
func AllModels() []interface{} {
    return []interface{}{
        // Core
        &User{},
        &Session{},
        &Project{},
        &ProjectMetadata{},
        &File{},
        &Snapshot{},
        
        // Configuration
        &Config{},
        &ConfigSeedEvent{},
        
        // LLM Models
        &ModelRegistry{},
        &ModelSlot{},
        
        // Prompt Presets
        &PromptPreset{},
        &PromptPresetVersion{},
        &UserPromptOverride{},
        
        // Instructions
        &Instruction{},
        &InstructionTask{},
        &FileChange{},
        
        // Inconsistency Detection
        &InconsistencyReport{},
        &InconsistencyIssue{},
        &ClarificationQuestion{},
        &ClarificationAnswer{},
        &RegenerationEvent{},
        
        // RAG System
        &Artifact{},
        &Chunk{},
        &Embedding{},
        &RetrievalSession{},
        &RetrievalSessionChunk{},
        &PromotionEvent{},
        
        // Vector Search (Phase 1)
        &VectorIndexMetadata{},
        
        // Context Management (Phase 2-4)
        &InstructionSegment{},
        &MemoryEntry{},
        
        // Consistency Checker
        &ConsistencyLoop{},
        &ConsistencyLoopIteration{},
    }
}

// InitDatabase initializes the database with auto-migration
func InitDatabase(dbPath string) (*gorm.DB, error) {
    db, err := gorm.Open(sqlite.Open(dbPath), &gorm.Config{
        Logger: logger.Default.LogMode(logger.Info),
    })
    if err != nil {
        return nil, err
    }
    
    // Auto-migrate all models
    if err := db.AutoMigrate(AllModels()...); err != nil {
        return nil, err
    }
    
    return db, nil
}
```

---

## Query Patterns (GORM)

### Get Project Tree

```go
func (r *ProjectRepository) GetProjectTree(ctx context.Context) ([]Project, error) {
    var projects []Project
    err := r.db.WithContext(ctx).
        Preload("Children").
        Where("parent_id IS NULL").
        Order("sort_order, name").
        Find(&projects).Error
    return projects, err
}
```

### Get File Tree for Project

```go
func (r *FileRepository) GetFileTree(ctx context.Context, projectId string) ([]File, error) {
    var files []File
    err := r.db.WithContext(ctx).
        Preload("Children").
        Where("project_id = ? AND parent_id IS NULL", projectId).
        Order("type DESC, sort_order, name").
        Find(&files).Error
    return files, err
}
```

### Get Questions with Answers

```go
func (r *QuestionRepository) GetQuestionsWithAnswers(ctx context.Context, reportId string) ([]ClarificationQuestion, error) {
    var questions []ClarificationQuestion
    err := r.db.WithContext(ctx).
        Preload("Answer").
        Where("report_id = ?", reportId).
        Order("phase, display_order").
        Find(&questions).Error
    return questions, err
}
```

---

### RAG Query Patterns

```go
// GetActiveArtifacts retrieves active artifacts for a project
func (r *ArtifactRepository) GetActiveArtifacts(ctx context.Context, projectId string, artifactType ArtifactType) ([]Artifact, error) {
    var artifacts []Artifact
    err := r.db.WithContext(ctx).
        Where("project_id = ? AND artifact_type = ? AND status = ?", projectId, artifactType, ArtifactStatusActive).
        Order("sequence_number DESC").
        Find(&artifacts).Error
    return artifacts, err
}

// GetPinnedArtifactsWithChunks retrieves pinned artifacts with their chunks for top-K memory
func (r *ArtifactRepository) GetPinnedArtifactsWithChunks(ctx context.Context, projectId string, limit int) ([]Artifact, error) {
    var artifacts []Artifact
    err := r.db.WithContext(ctx).
        Preload("Chunks", func(db *gorm.DB) *gorm.DB {
            return db.Order("chunk_index ASC")
        }).
        Where("project_id = ? AND is_pinned = true AND status = ?", projectId, ArtifactStatusActive).
        Order("pinned_order ASC").
        Limit(limit).
        Find(&artifacts).Error
    return artifacts, err
}

// GetChunksWithEmbeddings retrieves chunks with their embeddings for similarity search
func (r *ChunkRepository) GetChunksWithEmbeddings(ctx context.Context, artifactIds []string) ([]Chunk, error) {
    var chunks []Chunk
    err := r.db.WithContext(ctx).
        Preload("Embedding").
        Where("artifact_id IN ?", artifactIds).
        Order("artifact_id, chunk_index").
        Find(&chunks).Error
    return chunks, err
}

// FindSimilarChunks performs vector similarity search (requires application-level calculation)
func (r *ChunkRepository) FindSimilarChunks(ctx context.Context, projectId string, limit int) ([]Chunk, error) {
    var chunks []Chunk
    err := r.db.WithContext(ctx).
        Preload("Embedding").
        Preload("Artifact").
        Joins("JOIN Artifact ON Artifact.id = Chunk.artifact_id").
        Where("Artifact.project_id = ? AND Artifact.status = ?", projectId, ArtifactStatusActive).
        Find(&chunks).Error
    // Note: Actual similarity calculation must be done in application code
    // as SQLite doesn't natively support vector operations
    return chunks, err
}

// GetRecentRetrievalSessions for cache checking
func (r *RetrievalSessionRepository) GetRecentByQueryHash(ctx context.Context, projectId, queryHash string, maxAge time.Duration) (*RetrievalSession, error) {
    var session RetrievalSession
    cutoff := time.Now().Add(-maxAge)
    err := r.db.WithContext(ctx).
        Preload("SessionChunks", func(db *gorm.DB) *gorm.DB {
            return db.Order("rank ASC")
        }).
        Where("project_id = ? AND query_hash = ? AND created_at > ?", projectId, queryHash, cutoff).
        Order("created_at DESC").
        First(&session).Error
    if err == gorm.ErrRecordNotFound {
        return nil, nil
    }
    return &session, err
}
```

---

## Vector Search Tables

### VectorIndexMetadata

```go
// VectorIndexMetadata tracks vector index state per project
type VectorIndexMetadata struct {
    Id             string     `gorm:"type:text;primaryKey" json:"id"`
    ProjectId      string     `gorm:"type:text;not null;uniqueIndex:IX_VectorIndexMetadata_Project" json:"projectId"`
    TotalVectors   int        `gorm:"default:0" json:"totalVectors"`
    Dimensions     int        `gorm:"not null" json:"dimensions"`
    IndexType      string     `gorm:"type:text;not null" json:"indexType"` // "vss" | "hnsw" | "ivf" | "fts5_only"
    IndexSizeBytes int64      `gorm:"default:0" json:"indexSizeBytes"`
    LastReindexAt  *time.Time `gorm:"type:text" json:"lastReindexAt"`
    CreatedAt      time.Time  `gorm:"not null" json:"createdAt"`
    UpdatedAt      time.Time  `gorm:"not null" json:"updatedAt"`
    
    // Relations
    Project Project `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE" json:"-"`
}

func (VectorIndexMetadata) TableName() string { return "VectorIndexMetadata" }
```

---

### InstructionSegment

```go
// SegmentStatus enum
type SegmentStatus string

const (
    SegmentStatusPending   SegmentStatus = "pending"
    SegmentStatusExecuting SegmentStatus = "executing"
    SegmentStatusCompleted SegmentStatus = "completed"
    SegmentStatusFailed    SegmentStatus = "failed"
    SegmentStatusSkipped   SegmentStatus = "skipped"
)

// InstructionSegment stores a segment of a large instruction for multi-turn execution
type InstructionSegment struct {
    BaseModel
    InstructionId     string        `gorm:"type:text;not null;index:IX_InstructionSegment_Instruction" json:"instructionId"`
    SegmentIndex      int           `gorm:"not null" json:"segmentIndex"`
    Title             string        `gorm:"type:text;not null" json:"title"`
    Content           string        `gorm:"type:text;not null" json:"content"`
    TokenCount        int           `gorm:"not null" json:"tokenCount"`
    DependsOnSegments string        `gorm:"type:text" json:"dependsOnSegments"` // JSON array of segment IDs
    Status            SegmentStatus `gorm:"type:text;not null;default:'pending';index:IX_InstructionSegment_Status" json:"status"`
    SummaryForNext    *string       `gorm:"type:text" json:"summaryForNext"` // Compressed output for next segment
    ErrorMessage      *string       `gorm:"type:text" json:"errorMessage"`
    ExecutedAt        *time.Time    `gorm:"type:text" json:"executedAt"`
    ExecutionDurationMs *int        `gorm:"type:integer" json:"executionDurationMs"`
    
    // Relations
    Instruction Instruction `gorm:"foreignKey:InstructionId;constraint:OnDelete:CASCADE" json:"-"`
}

func (InstructionSegment) TableName() string { return "InstructionSegment" }

// GetDependencies parses the DependsOnSegments JSON array
func (s *InstructionSegment) GetDependencies() ([]string, error) {
    if s.DependsOnSegments == "" {
        return []string{}, nil
    }
    var deps []string
    err := json.Unmarshal([]byte(s.DependsOnSegments), &deps)
    return deps, err
}

// SetDependencies serializes dependencies to JSON
func (s *InstructionSegment) SetDependencies(deps []string) error {
    data, err := json.Marshal(deps)
    if err != nil {
        return err
    }
    s.DependsOnSegments = string(data)
    return nil
}
```

---

### MemoryEntry

```go
// MemoryEntry stores compressed context for multi-turn instruction execution
type MemoryEntry struct {
    BaseModel
    InstructionId    string `gorm:"type:text;not null;index:IX_MemoryEntry_Instruction" json:"instructionId"`
    SessionId        string `gorm:"type:text;not null;index:IX_MemoryEntry_Session" json:"sessionId"`
    TurnIndex        int    `gorm:"not null" json:"turnIndex"`
    OriginalTokens   int    `gorm:"not null" json:"originalTokens"`
    CompressedTokens int    `gorm:"not null" json:"compressedTokens"`
    Summary          string `gorm:"type:text;not null" json:"summary"`
    KeyDecisions     string `gorm:"type:text" json:"keyDecisions"`     // JSON array
    ArtifactsCreated string `gorm:"type:text" json:"artifactsCreated"` // JSON array of file paths
    OpenQuestions    string `gorm:"type:text" json:"openQuestions"`    // JSON array of pending questions
    
    // Relations
    Instruction Instruction `gorm:"foreignKey:InstructionId;constraint:OnDelete:CASCADE" json:"-"`
}

func (MemoryEntry) TableName() string { return "MemoryEntry" }

// GetKeyDecisions parses the KeyDecisions JSON array
func (m *MemoryEntry) GetKeyDecisions() ([]string, error) {
    if m.KeyDecisions == "" {
        return []string{}, nil
    }
    var decisions []string
    err := json.Unmarshal([]byte(m.KeyDecisions), &decisions)
    return decisions, err
}

// GetArtifactsCreated parses the ArtifactsCreated JSON array
func (m *MemoryEntry) GetArtifactsCreated() ([]string, error) {
    if m.ArtifactsCreated == "" {
        return []string{}, nil
    }
    var artifacts []string
    err := json.Unmarshal([]byte(m.ArtifactsCreated), &artifacts)
    return artifacts, err
}

// CompressionRatio returns the compression efficiency
func (m *MemoryEntry) CompressionRatio() float64 {
    if m.OriginalTokens == 0 {
        return 0
    }
    return 1.0 - (float64(m.CompressedTokens) / float64(m.OriginalTokens))
}
```

---

## Updated ER Diagram (Vector Search)

```mermaid
erDiagram
    Instruction ||--o{ InstructionSegment : "split_into"
    Instruction ||--o{ MemoryEntry : "has_memory"
    
    InstructionSegment ||--o{ InstructionSegment : "depends_on"
    
    Project ||--o| VectorIndexMetadata : "has_index"
    
    Chunk ||--|| Embedding : "has_vector"
    
    VectorIndexMetadata {
        string Id PK
        string ProjectId FK
        int TotalVectors
        int Dimensions
        string IndexType
        int64 IndexSizeBytes
        datetime LastReindexAt
    }
    
    InstructionSegment {
        string Id PK
        string InstructionId FK
        int SegmentIndex
        string Title
        string Content
        int TokenCount
        string DependsOnSegments
        string Status
        string SummaryForNext
        datetime ExecutedAt
    }
    
    MemoryEntry {
        string Id PK
        string InstructionId FK
        string SessionId
        int TurnIndex
        int OriginalTokens
        int CompressedTokens
        string Summary
        string KeyDecisions
        string ArtifactsCreated
    }
```

---

## Related Specs

### Database Design Documents
- [Overview](./00-overview.md) — Database design index
- [Migrations](./02-migrations.md) — Migration patterns
- [Relationships](./03-relationships.md) — FK constraints and indexes
- [Conventions](./04-conventions.md) — Naming standards
- [ERD](./diagrams/01-erd.md) — Entity relationship diagram

### Feature Specifications
- [AI Integration](../05-features/06-ai-integration/01-ai-integration.md) — LLM model management
- [Presets & Guidelines](../05-features/06-ai-integration/02-presets-guidelines.md) — Prompt preset system
- [Instruction System](../05-features/06-ai-integration/03-instruction-system.md) — Instruction pipeline
- [RAG System](../05-features/09-knowledge-memory/01-rag-system.md) — Retrieval-Augmented Generation
- [Vector Database Plan](../05-features/09-knowledge-memory/04-vector-database-plan.md) — Vector search architecture
- [Consistency Checker](../05-features/08-consistency-checker/00-overview.md) — Spec validation
