# Data Models

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

This document consolidates all database entities for the Code Generation System. All models use GORM tags and follow the project's PascalCase naming convention.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Database Design](../../07-database-design/00-overview.md)
- [Unified Schema](../../07-database-design/02b-unified-schema.md)

---

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    CODE GENERATION ENTITY RELATIONSHIPS                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────┐         ┌─────────────────┐         ┌──────────────┐  │
│  │    User     │────────▶│  UserCredits    │         │CodingGuideline│  │
│  └─────────────┘         └─────────────────┘         └──────────────┘  │
│        │                         │                          │           │
│        │                         │                          │           │
│        ▼                         ▼                          ▼           │
│  ┌─────────────┐         ┌─────────────────┐    ┌────────────────────┐ │
│  │   Project   │◀────────│ GenerationRun   │───▶│ GuidelineResolution│ │
│  └─────────────┘         └─────────────────┘    └────────────────────┘ │
│        │                         │                                      │
│        │                         │                                      │
│        ▼                         ▼                                      │
│  ┌─────────────┐         ┌─────────────────┐                           │
│  │ RepoConnection│       │ GeneratedFile   │                           │
│  └─────────────┘         └─────────────────┘                           │
│        │                         │                                      │
│        │                         ▼                                      │
│        │                 ┌─────────────────┐                           │
│        │                 │  CommitRecord   │                           │
│        │                 └─────────────────┘                           │
│        │                                                                │
│        ▼                                                                │
│  ┌─────────────┐         ┌─────────────────┐                           │
│  │OAuthConnection│◀──────│  CreditTransaction│                         │
│  └─────────────┘         └─────────────────┘                           │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Entities

### GenerationRun

Tracks a complete code generation session.

```go
type GenerationRun struct {
    ID                string           `gorm:"primaryKey;type:text"`
    ProjectID         string           `gorm:"type:text;not null;index"`
    UserID            string           `gorm:"type:text;not null;index"`
    Status            GenerationStatus `gorm:"type:text;not null;index"`
    
    // Plan data
    PlanJSON          string           `gorm:"type:text"`        // Serialized GenerationPlan
    SpecReferences    string           `gorm:"type:text"`        // JSON array of spec paths
    
    // Progress tracking
    FilesTotal        int              `gorm:"type:integer;default:0"`
    FilesComplete     int              `gorm:"type:integer;default:0"`
    FilesFailed       int              `gorm:"type:integer;default:0"`
    CurrentPhase      string           `gorm:"type:text"`        // writing, consistency, build
    CurrentBatch      int              `gorm:"type:integer;default:0"`
    
    // Resource usage
    TokensUsed        int              `gorm:"type:integer;default:0"`
    CreditsUsed       float64          `gorm:"type:real;default:0"`
    
    // Build verification
    BuildAttempts     int              `gorm:"type:integer;default:0"`
    BuildSuccess      bool             `gorm:"type:boolean;default:false"`
    
    // Error handling
    ErrorCode         string           `gorm:"type:text"`
    ErrorMessage      string           `gorm:"type:text"`
    
    // Timestamps
    StartedAt         time.Time
    CompletedAt       *time.Time
    CreatedAt         time.Time
    UpdatedAt         time.Time
    
    // Relationships
    Project           Project          `gorm:"foreignKey:ProjectID"`
    User              User             `gorm:"foreignKey:UserID"`
    GeneratedFiles    []GeneratedFile  `gorm:"foreignKey:GenerationRunID"`
    CommitRecords     []CommitRecord   `gorm:"foreignKey:GenerationRunID"`
}

type GenerationStatus string

const (
    GenerationStatusPending     GenerationStatus = "pending"
    GenerationStatusResolving   GenerationStatus = "resolving"
    GenerationStatusPlanning    GenerationStatus = "planning"
    GenerationStatusGenerating  GenerationStatus = "generating"
    GenerationStatusChecking    GenerationStatus = "checking"
    GenerationStatusVerifying   GenerationStatus = "verifying"
    GenerationStatusCommitting  GenerationStatus = "committing"
    GenerationStatusCompleted   GenerationStatus = "completed"
    GenerationStatusFailed      GenerationStatus = "failed"
    GenerationStatusCancelled   GenerationStatus = "cancelled"
)
```

### GeneratedFile

Tracks individual generated files.

```go
type GeneratedFile struct {
    ID              string    `gorm:"primaryKey;type:text"`
    GenerationRunID string    `gorm:"type:text;not null;index"`
    
    // File info
    Path            string    `gorm:"type:text;not null"`
    Language        string    `gorm:"type:text"`
    Purpose         string    `gorm:"type:text"`
    
    // Content
    Content         string    `gorm:"type:text"`
    ContentHash     string    `gorm:"type:text"`       // SHA-256
    LineCount       int       `gorm:"type:integer"`
    
    // Generation details
    Status          string    `gorm:"type:text;not null;index"`  // pending, generated, failed
    TokensUsed      int       `gorm:"type:integer;default:0"`
    ModelUsed       string    `gorm:"type:text"`
    BatchNumber     int       `gorm:"type:integer"`
    
    // Spec references
    SpecReferences  string    `gorm:"type:text"`       // JSON array
    
    // Error info
    ErrorMessage    string    `gorm:"type:text"`
    
    // Timestamps
    GeneratedAt     *time.Time
    CreatedAt       time.Time
    
    // Relationships
    GenerationRun   GenerationRun `gorm:"foreignKey:GenerationRunID"`
}
```

---

## Guideline Entities

### CodingGuideline

Stores coding guidelines at all hierarchy levels.

```go
type CodingGuideline struct {
    ID           string          `gorm:"primaryKey;type:text"`
    Level        GuidelineLevel  `gorm:"type:text;not null;index"`
    LanguageCode string          `gorm:"type:text;index"`
    UserID       string          `gorm:"type:text;index"`
    ProjectID    string          `gorm:"type:text;index"`
    
    // Content
    Name         string          `gorm:"type:text;not null"`
    Description  string          `gorm:"type:text"`
    Content      string          `gorm:"type:text;not null"`
    
    // Metadata
    Priority     int             `gorm:"type:integer;default:0"`
    IsActive     bool            `gorm:"type:boolean;default:true"`
    Version      int             `gorm:"type:integer;default:1"`
    
    // Timestamps
    CreatedAt    time.Time
    UpdatedAt    time.Time
    
    // Relationships (optional based on level)
    User         *User           `gorm:"foreignKey:UserID"`
    Project      *Project        `gorm:"foreignKey:ProjectID"`
}

type GuidelineLevel string

const (
    GuidelineLevelGeneral  GuidelineLevel = "general"
    GuidelineLevelLanguage GuidelineLevel = "language"
    GuidelineLevelUser     GuidelineLevel = "user"
    GuidelineLevelProject  GuidelineLevel = "project"
)
```

### GuidelineResolution

Tracks resolved guidelines for a generation run.

```go
type GuidelineResolution struct {
    ID              string    `gorm:"primaryKey;type:text"`
    GenerationRunID string    `gorm:"type:text;not null;index"`
    LanguageCode    string    `gorm:"type:text;not null"`
    
    // Merged content
    MergedContent   string    `gorm:"type:text;not null"`
    
    // Source tracking
    SourcesJSON     string    `gorm:"type:text"`       // JSON array of source IDs
    OverridesJSON   string    `gorm:"type:text"`       // JSON array of override records
    
    // Timestamps
    ResolvedAt      time.Time
    CreatedAt       time.Time
    
    // Relationships
    GenerationRun   GenerationRun `gorm:"foreignKey:GenerationRunID"`
}
```

---

## Model Preset Entities

### CodingModelPreset

Defines AI model configurations for code generation.

```go
type CodingModelPreset struct {
    ID              string    `gorm:"primaryKey;type:text"`
    Name            string    `gorm:"type:text;not null"`
    Description     string    `gorm:"type:text"`
    Category        string    `gorm:"type:text;not null;index"`
    
    // Model configuration
    ModelPath       string    `gorm:"type:text;not null"`
    ModelType       string    `gorm:"type:text;not null"`       // ollama, llama, openai
    Languages       string    `gorm:"type:text"`                // JSON array
    
    // Generation parameters
    ContextWindow   int       `gorm:"type:integer;default:8192"`
    MaxOutputTokens int       `gorm:"type:integer;default:4096"`
    Temperature     float64   `gorm:"type:real;default:0.2"`
    TopP            float64   `gorm:"type:real;default:0.9"`
    
    // Prompts
    SystemPrompt    string    `gorm:"type:text"`
    
    // Metadata
    IsDefault       bool      `gorm:"type:boolean;default:false"`
    IsActive        bool      `gorm:"type:boolean;default:true"`
    Priority        int       `gorm:"type:integer;default:0"`
    
    // Timestamps
    CreatedAt       time.Time
    UpdatedAt       time.Time
}
```

### UserModelPreference

User-level model preferences.

```go
type UserModelPreference struct {
    ID        string    `gorm:"primaryKey;type:text"`
    UserID    string    `gorm:"type:text;not null;index"`
    Category  string    `gorm:"type:text;not null"`
    PresetID  string    `gorm:"type:text;not null"`
    
    CreatedAt time.Time
    UpdatedAt time.Time
    
    // Relationships
    User      User              `gorm:"foreignKey:UserID"`
    Preset    CodingModelPreset `gorm:"foreignKey:PresetID"`
}
```

### ProjectModelOverride

Project-level model overrides.

```go
type ProjectModelOverride struct {
    ID        string    `gorm:"primaryKey;type:text"`
    ProjectID string    `gorm:"type:text;not null;index"`
    Category  string    `gorm:"type:text;not null"`
    PresetID  string    `gorm:"type:text;not null"`
    
    CreatedAt time.Time
    UpdatedAt time.Time
    
    // Relationships
    Project   Project           `gorm:"foreignKey:ProjectID"`
    Preset    CodingModelPreset `gorm:"foreignKey:PresetID"`
}
```

---

## Git Entities

### RepositoryConnection

Tracks remote repository connections.

```go
type RepositoryConnection struct {
    ID            string    `gorm:"primaryKey;type:text"`
    ProjectID     string    `gorm:"type:text;not null;uniqueIndex"`
    
    // Remote info
    Provider      string    `gorm:"type:text;not null"`   // github, gitlab
    RemoteURL     string    `gorm:"type:text"`
    DefaultBranch string    `gorm:"type:text;default:main"`
    RepoName      string    `gorm:"type:text"`
    RepoOwner     string    `gorm:"type:text"`
    
    // Status
    IsConnected   bool      `gorm:"type:boolean;default:false"`
    LastSyncAt    *time.Time
    LastSyncError string    `gorm:"type:text"`
    
    // Timestamps
    CreatedAt     time.Time
    UpdatedAt     time.Time
    
    // Relationships
    Project       Project   `gorm:"foreignKey:ProjectID"`
}
```

### OAuthConnection

Stores encrypted OAuth tokens.

```go
type OAuthConnection struct {
    ID           string    `gorm:"primaryKey;type:text"`
    UserID       string    `gorm:"type:text;not null;index"`
    Provider     string    `gorm:"type:text;not null"`   // github, gitlab
    
    // Tokens (encrypted)
    AccessToken  string    `gorm:"type:text;not null"`
    RefreshToken string    `gorm:"type:text"`
    ExpiresAt    *time.Time
    
    // User info from provider
    ProviderUserID string  `gorm:"type:text"`
    Username       string  `gorm:"type:text"`
    Email          string  `gorm:"type:text"`
    AvatarURL      string  `gorm:"type:text"`
    
    // Scopes
    Scopes       string    `gorm:"type:text"`            // Comma-separated
    
    // Timestamps
    CreatedAt    time.Time
    UpdatedAt    time.Time
    
    // Relationships
    User         User      `gorm:"foreignKey:UserID"`
}
```

### CommitRecord

Tracks git commits from generation runs.

```go
type CommitRecord struct {
    ID              string    `gorm:"primaryKey;type:text"`
    ProjectID       string    `gorm:"type:text;not null;index"`
    GenerationRunID string    `gorm:"type:text;index"`
    
    // Commit info
    CommitHash      string    `gorm:"type:text;not null"`
    Message         string    `gorm:"type:text;not null"`
    Author          string    `gorm:"type:text"`
    
    // Stats
    FilesChanged    int       `gorm:"type:integer;default:0"`
    Insertions      int       `gorm:"type:integer;default:0"`
    Deletions       int       `gorm:"type:integer;default:0"`
    
    // References
    SpecReferences  string    `gorm:"type:text"`           // JSON array
    FilePaths       string    `gorm:"type:text"`           // JSON array
    
    // Push status
    PushedAt        *time.Time
    PushError       string    `gorm:"type:text"`
    
    // Timestamps
    CommittedAt     time.Time
    CreatedAt       time.Time
    
    // Relationships
    Project         Project       `gorm:"foreignKey:ProjectID"`
    GenerationRun   *GenerationRun `gorm:"foreignKey:GenerationRunID"`
}
```

---

## Credit Entities

### UserCredits

User credit balance and history.

```go
type UserCredits struct {
    ID             string    `gorm:"primaryKey;type:text"`
    UserID         string    `gorm:"type:text;not null;uniqueIndex"`
    
    // Balance
    Balance        float64   `gorm:"type:real;default:0"`
    TotalPurchased float64   `gorm:"type:real;default:0"`
    TotalConsumed  float64   `gorm:"type:real;default:0"`
    
    // Free credits
    FreeCredits    float64   `gorm:"type:real;default:0"`
    FreeResetAt    time.Time
    
    // Plan
    PlanID         string    `gorm:"type:text"`
    
    // Timestamps
    CreatedAt      time.Time
    UpdatedAt      time.Time
    
    // Relationships
    User           User           `gorm:"foreignKey:UserID"`
    Plan           *CreditPlan    `gorm:"foreignKey:PlanID"`
}
```

### CreditTransaction

Individual credit transactions.

```go
type CreditTransaction struct {
    ID              string          `gorm:"primaryKey;type:text"`
    UserID          string          `gorm:"type:text;not null;index"`
    ProjectID       string          `gorm:"type:text;index"`
    
    // Transaction details
    Type            TransactionType `gorm:"type:text;not null"`
    CreditType      CreditType      `gorm:"type:text"`
    Amount          float64         `gorm:"type:real;not null"`
    BalanceAfter    float64         `gorm:"type:real;not null"`
    
    // Metadata
    Description     string          `gorm:"type:text"`
    Metadata        string          `gorm:"type:text"`          // JSON
    GenerationRunID string          `gorm:"type:text;index"`
    
    // Timestamps
    CreatedAt       time.Time       `gorm:"index"`
    
    // Relationships
    User            User            `gorm:"foreignKey:UserID"`
    Project         *Project        `gorm:"foreignKey:ProjectID"`
    GenerationRun   *GenerationRun  `gorm:"foreignKey:GenerationRunID"`
}

type TransactionType string

const (
    TransactionTypePurchase    TransactionType = "purchase"
    TransactionTypeConsumption TransactionType = "consumption"
    TransactionTypeRefund      TransactionType = "refund"
    TransactionTypeFreeGrant   TransactionType = "free_grant"
    TransactionTypeExpiry      TransactionType = "expiry"
)

type CreditType string

const (
    CreditTypeAIRequest     CreditType = "ai_request"
    CreditTypeFileGenerated CreditType = "file_generated"
    CreditTypeBuildCycle    CreditType = "build_cycle"
)
```

### CreditPlan

Credit plan definitions.

```go
type CreditPlan struct {
    ID              string    `gorm:"primaryKey;type:text"`
    Name            string    `gorm:"type:text;not null"`
    Description     string    `gorm:"type:text"`
    
    // Pricing
    CreditsIncluded float64   `gorm:"type:real;not null"`
    PriceUSD        float64   `gorm:"type:real;not null"`
    MonthlyFree     float64   `gorm:"type:real;default:0"`
    
    // Features
    Features        string    `gorm:"type:text"`            // JSON array
    
    // Status
    IsActive        bool      `gorm:"type:boolean;default:true"`
    
    // Timestamps
    CreatedAt       time.Time
    UpdatedAt       time.Time
}
```

---

## Project Settings Entity

### ProjectCodeSettings

Project-specific code generation settings.

```go
type ProjectCodeSettings struct {
    ID                  string    `gorm:"primaryKey;type:text"`
    ProjectID           string    `gorm:"type:text;not null;uniqueIndex"`
    
    // Repository settings
    CodeRepoRootDir     string    `gorm:"type:text"`
    UseDefaultStructure bool      `gorm:"type:boolean;default:true"`
    CustomStructure     string    `gorm:"type:text"`           // JSON
    
    // Generation settings
    DefaultLanguages    string    `gorm:"type:text"`           // JSON array
    MaxParallelWorkers  int       `gorm:"type:integer;default:4"`
    AutoCommit          bool      `gorm:"type:boolean;default:true"`
    AutoPush            bool      `gorm:"type:boolean;default:false"`
    VerifyAfterGenerate bool      `gorm:"type:boolean;default:true"`
    
    // Timestamps
    CreatedAt           time.Time
    UpdatedAt           time.Time
    
    // Relationships
    Project             Project   `gorm:"foreignKey:ProjectID"`
}
```

---

## Indexes

### Recommended Indexes

```sql
-- GenerationRun indexes
CREATE INDEX idx_generation_run_project_status ON GenerationRun(ProjectID, Status);
CREATE INDEX idx_generation_run_user_created ON GenerationRun(UserID, CreatedAt DESC);

-- GeneratedFile indexes
CREATE INDEX idx_generated_file_run_status ON GeneratedFile(GenerationRunID, Status);

-- CodingGuideline indexes
CREATE INDEX idx_coding_guideline_level_lang ON CodingGuideline(Level, LanguageCode);
CREATE INDEX idx_coding_guideline_project ON CodingGuideline(ProjectID) WHERE ProjectID IS NOT NULL;

-- CreditTransaction indexes
CREATE INDEX idx_credit_transaction_user_created ON CreditTransaction(UserID, CreatedAt DESC);
CREATE INDEX idx_credit_transaction_run ON CreditTransaction(GenerationRunID) WHERE GenerationRunID IS NOT NULL;

-- CommitRecord indexes
CREATE INDEX idx_commit_record_project_date ON CommitRecord(ProjectID, CommittedAt DESC);
```

---

## Migration

### GORM AutoMigrate

```go
func Migrate(db *gorm.DB) error {
    return db.AutoMigrate(
        // Core entities
        &GenerationRun{},
        &GeneratedFile{},
        
        // Guidelines
        &CodingGuideline{},
        &GuidelineResolution{},
        
        // Model presets
        &CodingModelPreset{},
        &UserModelPreference{},
        &ProjectModelOverride{},
        
        // Git
        &RepositoryConnection{},
        &OAuthConnection{},
        &CommitRecord{},
        
        // Credits
        &UserCredits{},
        &CreditTransaction{},
        &CreditPlan{},
        
        // Settings
        &ProjectCodeSettings{},
    )
}
```

---

## Related Specs

- [Architecture](./01-architecture.md)
- [Database Design](../../07-database-design/00-overview.md)
- [Unified Schema](../../07-database-design/02b-unified-schema.md)
