# Phase 4: Chronicle Service Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Phase:** 4 of 9  
**Service:** `chronicle`  
**Port:** 8083  

---

## Overview

The Chronicle Service manages version control, history tracking, and Git operations for SpecBuilder Pro. It provides a complete audit trail of all specification changes and enables time-travel queries.

**Cross-References:**
- [Shared Packages](../13-shared-packages/00-overview.md)
- [SpecManager Service](./02-specmanager.md)
- [Database Design](../07-database-design/00-overview.md)

---

## Responsibilities

- **Version Control**: Track all changes to specifications
- **Git Operations**: Commit, branch, diff, merge support
- **History Queries**: Time-travel and audit queries
- **Diff Generation**: Content comparison between versions
- **Changelog Generation**: Automated changelog creation
- **Rollback**: Restore previous versions

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                        Chronicle :8083                                │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │                      HTTP Handlers                               │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────────────┐ │ │
│  │  │HistoryHandler│ │  GitHandler  │ │    DiffHandler           │ │ │
│  │  └──────────────┘ └──────────────┘ └──────────────────────────┘ │ │
│  └────────────────────────────┬────────────────────────────────────┘ │
│                               │                                       │
│  ┌────────────────────────────┴────────────────────────────────────┐ │
│  │                       Service Layer                              │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────────────┐ │ │
│  │  │HistoryService│ │  GitService  │ │    DiffService           │ │ │
│  │  └──────────────┘ └──────────────┘ └──────────────────────────┘ │ │
│  └────────────────────────────┬────────────────────────────────────┘ │
│                               │                                       │
│  ┌────────────────────────────┴────────────────────────────────────┐ │
│  │                     Repository Layer                             │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────────────┐ │ │
│  │  │ CommitRepo   │ │ VersionRepo  │ │    ChangelogRepo         │ │ │
│  │  └──────────────┘ └──────────────┘ └──────────────────────────┘ │ │
│  └────────────────────────────┬────────────────────────────────────┘ │
│                               │                                       │
│  ┌────────────────────────────┴────────────────────────────────────┐ │
│  │                          Storage                                 │ │
│  │  ┌──────────────────┐ ┌──────────────────────────────────────┐  │ │
│  │  │{project-id}.db   │ │   Git Repository (optional)          │  │ │
│  │  │(history tables)  │ │   .git/                              │  │ │
│  │  └──────────────────┘ └──────────────────────────────────────┘  │ │
│  └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Directory Structure

```
cmd/chronicle/
├── main.go
└── config.yaml

internal/chronicle/
├── server/
│   ├── server.go
│   └── routes.go
├── handler/
│   ├── history.go          # History HTTP handlers
│   ├── git.go              # Git HTTP handlers
│   ├── diff.go             # Diff HTTP handlers
│   ├── changelog.go        # Changelog handlers
│   └── rollback.go         # Rollback handlers
├── service/
│   ├── history.go          # History business logic
│   ├── git.go              # Git operations
│   ├── diff.go             # Diff generation
│   ├── changelog.go        # Changelog generation
│   └── rollback.go         # Version rollback
├── repository/
│   ├── commit.go           # Commit data access
│   ├── version.go          # Version data access
│   ├── change.go           # Change tracking
│   └── dbmanager.go        # Database management
├── model/
│   ├── commit.go           # Commit domain model
│   ├── version.go          # Version domain model
│   ├── change.go           # Change domain model
│   ├── diff.go             # Diff domain model
│   └── changelog.go        # Changelog domain model
├── git/
│   ├── repository.go       # Git repository wrapper
│   ├── commit.go           # Git commit operations
│   ├── branch.go           # Branch operations
│   ├── diff.go             # Git diff operations
│   └── merge.go            # Merge operations
├── diff/
│   ├── engine.go           # Diff algorithm
│   ├── unified.go          # Unified diff format
│   └── semantic.go         # Semantic diff
└── migrations/
    └── chronicle/          # chronicle tables
```

---

## Domain Models

### Commit Model

```go
package model

import (
    "github.com/specbuilder/pkg/types"
)

// Commit represents a version control commit
type Commit struct {
    ID          types.CommitID    `json:"id"`
    ProjectID   types.ProjectID   `json:"projectId"`
    ParentID    *types.CommitID   `json:"parentId,omitempty"`
    Message     string            `json:"message"`
    Author      CommitAuthor      `json:"author"`
    Changes     []Change          `json:"changes"`
    Metadata    types.Metadata    `json:"metadata"`
    CreatedAt   types.Timestamp   `json:"createdAt"`
    
    // Git integration (optional)
    GitHash     string            `json:"gitHash,omitempty"`
    GitBranch   string            `json:"gitBranch,omitempty"`
}

// CommitAuthor represents commit author information
type CommitAuthor struct {
    UserID   types.UserID `json:"userId"`
    Name     string       `json:"name"`
    Email    string       `json:"email"`
}

// CommitID is a typed identifier for commits
type CommitID struct {
    value uuid.UUID
}

func NewCommitID() CommitID { return CommitID{value: uuid.New()} }
func ParseCommitID(s string) (CommitID, error) { /* similar to other IDs */ }
func (id CommitID) String() string { return id.value.String() }

// CreateCommitRequest for creating a commit
type CreateCommitRequest struct {
    ProjectID types.ProjectID `json:"projectId" validate:"required"`
    Message   string          `json:"message" validate:"required,min=1,max=1000"`
    Changes   []ChangeInput   `json:"changes" validate:"required,min=1,max=100"`
}

// ChangeInput represents a single change in a commit request
type ChangeInput struct {
    SpecID      types.SpecID  `json:"specId" validate:"required"`
    Type        ChangeType    `json:"type" validate:"required"`
    ContentDiff string        `json:"contentDiff,omitempty"`
}
```

### Change Model

```go
package model

import (
    "github.com/specbuilder/pkg/types"
)

// ChangeType represents the type of change
type ChangeType string

const (
    ChangeTypeCreate ChangeType = "CREATE"
    ChangeTypeUpdate ChangeType = "UPDATE"
    ChangeTypeDelete ChangeType = "DELETE"
    ChangeTypeRename ChangeType = "RENAME"
    ChangeTypeMove   ChangeType = "MOVE"
)

// Change represents a single change in a commit
type Change struct {
    ID          types.ChangeID    `json:"id"`
    CommitID    types.CommitID    `json:"commitId"`
    SpecID      types.SpecID      `json:"specId"`
    Type        ChangeType        `json:"type"`
    
    // Content changes
    OldContent    string          `json:"oldContent,omitempty"`
    NewContent    string          `json:"newContent,omitempty"`
    OldHash       string          `json:"oldHash,omitempty"`
    NewHash       string          `json:"newHash,omitempty"`
    
    // Path changes (for rename/move)
    OldPath       string          `json:"oldPath,omitempty"`
    NewPath       string          `json:"newPath,omitempty"`
    
    // Statistics
    Additions     int             `json:"additions"`
    Deletions     int             `json:"deletions"`
    
    CreatedAt     types.Timestamp `json:"createdAt"`
}

// ChangeID is a typed identifier for changes
type ChangeID struct {
    value uuid.UUID
}
```

### Version Model

```go
package model

import (
    "github.com/specbuilder/pkg/types"
)

// SpecVersion represents a point-in-time version of a spec
type SpecVersion struct {
    ID          types.VersionID   `json:"id"`
    SpecID      types.SpecID      `json:"specId"`
    CommitID    types.CommitID    `json:"commitId"`
    Version     int               `json:"version"`     // Sequential version number
    Content     string            `json:"content"`
    ContentHash string            `json:"contentHash"`
    Path        string            `json:"path"`
    Metadata    types.Metadata    `json:"metadata"`
    CreatedAt   types.Timestamp   `json:"createdAt"`
    CreatedBy   *types.UserID     `json:"createdBy,omitempty"`
}

// VersionID is a typed identifier for versions
type VersionID struct {
    value uuid.UUID
}
```

### Diff Model

```go
package model

// DiffResult represents the difference between two versions
type DiffResult struct {
    SpecID      types.SpecID    `json:"specId"`
    OldVersion  int             `json:"oldVersion"`
    NewVersion  int             `json:"newVersion"`
    Changes     []DiffHunk      `json:"changes"`
    Statistics  DiffStats       `json:"statistics"`
    
    // Unified diff format
    UnifiedDiff string          `json:"unifiedDiff,omitempty"`
}

// DiffHunk represents a single change region
type DiffHunk struct {
    OldStart    int           `json:"oldStart"`
    OldLines    int           `json:"oldLines"`
    NewStart    int           `json:"newStart"`
    NewLines    int           `json:"newLines"`
    Lines       []DiffLine    `json:"lines"`
}

// DiffLine represents a single line in a diff
type DiffLine struct {
    Type    DiffLineType `json:"type"`
    Content string       `json:"content"`
    OldNum  int          `json:"oldNum,omitempty"`
    NewNum  int          `json:"newNum,omitempty"`
}

// DiffLineType represents the type of diff line
type DiffLineType string

const (
    DiffLineContext   DiffLineType = "context"
    DiffLineAddition  DiffLineType = "addition"
    DiffLineDeletion  DiffLineType = "deletion"
)

// DiffStats contains diff statistics
type DiffStats struct {
    Additions   int `json:"additions"`
    Deletions   int `json:"deletions"`
    Changes     int `json:"changes"`
}
```

### Changelog Model

```go
package model

import (
    "github.com/specbuilder/pkg/types"
)

// Changelog represents a generated changelog
type Changelog struct {
    ID          types.ChangelogID   `json:"id"`
    ProjectID   types.ProjectID     `json:"projectId"`
    FromCommit  types.CommitID      `json:"fromCommit"`
    ToCommit    types.CommitID      `json:"toCommit"`
    Title       string              `json:"title"`
    Content     string              `json:"content"`     // Markdown format
    Sections    []ChangelogSection  `json:"sections"`
    CreatedAt   types.Timestamp     `json:"createdAt"`
}

// ChangelogSection represents a section in a changelog
type ChangelogSection struct {
    Title   string           `json:"title"`    // e.g., "Added", "Changed", "Fixed"
    Items   []ChangelogItem  `json:"items"`
}

// ChangelogItem represents a single changelog entry
type ChangelogItem struct {
    SpecID      types.SpecID  `json:"specId"`
    SpecName    string        `json:"specName"`
    ChangeType  ChangeType    `json:"changeType"`
    Description string        `json:"description"`
}
```

---

## Service Layer

### HistoryService

```go
package service

import (
    "context"
    "crypto/sha256"
    "encoding/hex"
    
    "github.com/specbuilder/pkg/database"
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
    "github.com/specbuilder/pkg/types"
    
    "github.com/specbuilder/internal/chronicle/model"
    "github.com/specbuilder/internal/chronicle/repository"
)

// HistoryService manages version history
type HistoryService struct {
    commitRepo  *repository.CommitRepository
    versionRepo *repository.VersionRepository
    changeRepo  *repository.ChangeRepository
    dbManager   *repository.DBManager
    logger      logging.Logger
}

// NewHistoryService creates a history service
func NewHistoryService(
    commitRepo *repository.CommitRepository,
    versionRepo *repository.VersionRepository,
    changeRepo *repository.ChangeRepository,
    dbManager *repository.DBManager,
    logger logging.Logger,
) *HistoryService {
    return &HistoryService{
        commitRepo:  commitRepo,
        versionRepo: versionRepo,
        changeRepo:  changeRepo,
        dbManager:   dbManager,
        logger:      logger,
    }
}

// CreateCommit records a new commit with changes
func (s *HistoryService) CreateCommit(ctx context.Context, req model.CreateCommitRequest, author model.CommitAuthor) (*model.Commit, error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, req.ProjectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    // Get latest commit as parent
    latestCommit, _ := s.commitRepo.GetLatest(ctx, projectDB, req.ProjectID)
    var parentID *model.CommitID
    if latestCommit != nil {
        parentID = &latestCommit.ID
    }
    
    // Create commit
    commit := &model.Commit{
        ID:        model.NewCommitID(),
        ProjectID: req.ProjectID,
        ParentID:  parentID,
        Message:   req.Message,
        Author:    author,
        Changes:   make([]model.Change, 0, len(req.Changes)),
        CreatedAt: types.Now(),
    }
    
    // Process changes in transaction
    err = projectDB.WithTx(ctx, func(tx *database.Tx) error {
        // Create commit record
        if err := s.commitRepo.CreateWithTx(ctx, tx, commit); err != nil {
            return err
        }
        
        // Process each change
        for _, changeInput := range req.Changes {
            change, err := s.processChange(ctx, tx, commit.ID, changeInput)
            if err != nil {
                return err
            }
            commit.Changes = append(commit.Changes, *change)
        }
        
        return nil
    })
    
    if err != nil {
        s.logger.ErrorContext(ctx, "failed to create commit",
            logging.Err(err),
            "project_id", req.ProjectID,
            "message", req.Message,
        )
        return nil, err
    }
    
    s.logger.InfoContext(ctx, "commit created",
        "commit_id", commit.ID,
        "project_id", req.ProjectID,
        "changes_count", len(commit.Changes),
        "message", req.Message,
    )
    
    return commit, nil
}

// processChange creates a change record and version snapshot
func (s *HistoryService) processChange(ctx context.Context, tx *database.Tx, commitID model.CommitID, input model.ChangeInput) (*model.Change, error) {
    // Get current spec version
    currentVersion, _ := s.versionRepo.GetLatestForSpec(ctx, tx, input.SpecID)
    
    var oldContent, oldHash, oldPath string
    var newVersion int
    
    if currentVersion != nil {
        oldContent = currentVersion.Content
        oldHash = currentVersion.ContentHash
        oldPath = currentVersion.Path
        newVersion = currentVersion.Version + 1
    } else {
        newVersion = 1
    }
    
    // Calculate diff statistics
    additions, deletions := calculateDiffStats(oldContent, input.ContentDiff)
    
    change := &model.Change{
        ID:        model.NewChangeID(),
        CommitID:  commitID,
        SpecID:    input.SpecID,
        Type:      input.Type,
        OldContent: oldContent,
        OldHash:    oldHash,
        Additions:  additions,
        Deletions:  deletions,
        CreatedAt:  types.Now(),
    }
    
    // Create new version snapshot
    if input.Type != model.ChangeTypeDelete {
        newContent := input.ContentDiff // Simplified - would apply patch in real impl
        hash := sha256.Sum256([]byte(newContent))
        
        version := &model.SpecVersion{
            ID:          model.NewVersionID(),
            SpecID:      input.SpecID,
            CommitID:    commitID,
            Version:     newVersion,
            Content:     newContent,
            ContentHash: hex.EncodeToString(hash[:]),
            CreatedAt:   types.Now(),
        }
        
        if err := s.versionRepo.CreateWithTx(ctx, tx, version); err != nil {
            return nil, err
        }
        
        change.NewContent = newContent
        change.NewHash = version.ContentHash
    }
    
    // Save change record
    if err := s.changeRepo.CreateWithTx(ctx, tx, change); err != nil {
        return nil, err
    }
    
    return change, nil
}

// GetCommitHistory returns commit history for a project
func (s *HistoryService) GetCommitHistory(ctx context.Context, projectID types.ProjectID, req types.PageRequest) (*types.PageResponse[model.Commit], error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    commits, total, err := s.commitRepo.List(ctx, projectDB, projectID, req)
    if err != nil {
        return nil, err
    }
    
    response := types.NewPageResponse(commits, req, total)
    return &response, nil
}

// GetSpecHistory returns version history for a specific spec
func (s *HistoryService) GetSpecHistory(ctx context.Context, projectID types.ProjectID, specID types.SpecID, req types.PageRequest) (*types.PageResponse[model.SpecVersion], error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    versions, total, err := s.versionRepo.ListForSpec(ctx, projectDB, specID, req)
    if err != nil {
        return nil, err
    }
    
    response := types.NewPageResponse(versions, req, total)
    return &response, nil
}

// GetVersionAtTime returns the spec version at a specific point in time
func (s *HistoryService) GetVersionAtTime(ctx context.Context, projectID types.ProjectID, specID types.SpecID, timestamp types.Timestamp) (*model.SpecVersion, error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    version, err := s.versionRepo.GetAtTime(ctx, projectDB, specID, timestamp)
    if err != nil {
        s.logger.DebugContext(ctx, "no version found at time",
            "spec_id", specID,
            "timestamp", timestamp,
        )
        return nil, errors.NewDatabaseNotFound("SpecVersion", specID.String())
    }
    
    return version, nil
}

func calculateDiffStats(oldContent, newContent string) (additions, deletions int) {
    // Simplified - real implementation would use proper diff algorithm
    oldLines := strings.Split(oldContent, "\n")
    newLines := strings.Split(newContent, "\n")
    
    additions = len(newLines) - len(oldLines)
    if additions < 0 {
        deletions = -additions
        additions = 0
    }
    
    return
}
```

### DiffService

```go
package service

import (
    "context"
    "strings"
    
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
    "github.com/specbuilder/pkg/types"
    
    "github.com/specbuilder/internal/chronicle/diff"
    "github.com/specbuilder/internal/chronicle/model"
    "github.com/specbuilder/internal/chronicle/repository"
)

// DiffService generates diffs between versions
type DiffService struct {
    versionRepo *repository.VersionRepository
    diffEngine  *diff.Engine
    dbManager   *repository.DBManager
    logger      logging.Logger
}

// NewDiffService creates a diff service
func NewDiffService(
    versionRepo *repository.VersionRepository,
    dbManager *repository.DBManager,
    logger logging.Logger,
) *DiffService {
    return &DiffService{
        versionRepo: versionRepo,
        diffEngine:  diff.NewEngine(),
        dbManager:   dbManager,
        logger:      logger,
    }
}

// CompareVersions generates a diff between two versions
func (s *DiffService) CompareVersions(ctx context.Context, projectID types.ProjectID, specID types.SpecID, oldVersion, newVersion int) (*model.DiffResult, error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    // Get both versions
    oldVer, err := s.versionRepo.GetByVersion(ctx, projectDB, specID, oldVersion)
    if err != nil {
        s.logger.DebugContext(ctx, "old version not found",
            "spec_id", specID,
            "version", oldVersion,
        )
        return nil, errors.NewDatabaseNotFound("SpecVersion", fmt.Sprintf("%s@v%d", specID, oldVersion))
    }
    
    newVer, err := s.versionRepo.GetByVersion(ctx, projectDB, specID, newVersion)
    if err != nil {
        s.logger.DebugContext(ctx, "new version not found",
            "spec_id", specID,
            "version", newVersion,
        )
        return nil, errors.NewDatabaseNotFound("SpecVersion", fmt.Sprintf("%s@v%d", specID, newVersion))
    }
    
    // Generate diff
    hunks := s.diffEngine.Diff(oldVer.Content, newVer.Content)
    
    // Calculate statistics
    stats := calculateStats(hunks)
    
    // Generate unified diff
    unifiedDiff := s.diffEngine.UnifiedDiff(
        oldVer.Content, newVer.Content,
        fmt.Sprintf("v%d", oldVersion),
        fmt.Sprintf("v%d", newVersion),
    )
    
    result := &model.DiffResult{
        SpecID:      specID,
        OldVersion:  oldVersion,
        NewVersion:  newVersion,
        Changes:     hunks,
        Statistics:  stats,
        UnifiedDiff: unifiedDiff,
    }
    
    s.logger.DebugContext(ctx, "diff generated",
        "spec_id", specID,
        "old_version", oldVersion,
        "new_version", newVersion,
        "additions", stats.Additions,
        "deletions", stats.Deletions,
    )
    
    return result, nil
}

// CompareWithCurrent generates a diff between a version and current
func (s *DiffService) CompareWithCurrent(ctx context.Context, projectID types.ProjectID, specID types.SpecID, version int) (*model.DiffResult, error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    // Get latest version
    latestVer, err := s.versionRepo.GetLatestForSpec(ctx, projectDB, specID)
    if err != nil {
        return nil, err
    }
    
    return s.CompareVersions(ctx, projectID, specID, version, latestVer.Version)
}

func calculateStats(hunks []model.DiffHunk) model.DiffStats {
    var stats model.DiffStats
    
    for _, hunk := range hunks {
        for _, line := range hunk.Lines {
            switch line.Type {
            case model.DiffLineAddition:
                stats.Additions++
            case model.DiffLineDeletion:
                stats.Deletions++
            }
        }
        stats.Changes++
    }
    
    return stats
}
```

### RollbackService

```go
package service

import (
    "context"
    
    "github.com/specbuilder/pkg/database"
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
    "github.com/specbuilder/pkg/types"
    
    "github.com/specbuilder/internal/chronicle/model"
    "github.com/specbuilder/internal/chronicle/repository"
)

// RollbackService handles version rollback
type RollbackService struct {
    historyService *HistoryService
    versionRepo    *repository.VersionRepository
    specClient     SpecManagerClient // HTTP client to SpecManager
    dbManager      *repository.DBManager
    logger         logging.Logger
}

// RollbackRequest for rolling back a spec
type RollbackRequest struct {
    ProjectID types.ProjectID `json:"projectId" validate:"required"`
    SpecID    types.SpecID    `json:"specId" validate:"required"`
    Version   int             `json:"version" validate:"required,min=1"`
    Message   string          `json:"message" validate:"required,min=1,max=1000"`
}

// Rollback restores a spec to a previous version
func (s *RollbackService) Rollback(ctx context.Context, req RollbackRequest, author model.CommitAuthor) (*model.Commit, error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, req.ProjectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    // Get the target version
    targetVersion, err := s.versionRepo.GetByVersion(ctx, projectDB, req.SpecID, req.Version)
    if err != nil {
        s.logger.WarnContext(ctx, "rollback target version not found",
            "spec_id", req.SpecID,
            "version", req.Version,
        )
        return nil, errors.NewDatabaseNotFound("SpecVersion", fmt.Sprintf("%s@v%d", req.SpecID, req.Version))
    }
    
    // Get current version for comparison
    currentVersion, err := s.versionRepo.GetLatestForSpec(ctx, projectDB, req.SpecID)
    if err != nil {
        return nil, err
    }
    
    // Prevent rollback to current version
    if targetVersion.Version == currentVersion.Version {
        return nil, errors.NewBusiness(
            errors.ErrBusinessInvalidState,
            "cannot rollback to current version",
            map[string]any{"version": req.Version},
        )
    }
    
    s.logger.InfoContext(ctx, "starting rollback",
        "spec_id", req.SpecID,
        "from_version", currentVersion.Version,
        "to_version", targetVersion.Version,
    )
    
    // Create rollback commit
    commitReq := model.CreateCommitRequest{
        ProjectID: req.ProjectID,
        Message:   fmt.Sprintf("Rollback to v%d: %s", req.Version, req.Message),
        Changes: []model.ChangeInput{
            {
                SpecID:      req.SpecID,
                Type:        model.ChangeTypeUpdate,
                ContentDiff: targetVersion.Content,
            },
        },
    }
    
    commit, err := s.historyService.CreateCommit(ctx, commitReq, author)
    if err != nil {
        return nil, err
    }
    
    // Update spec in SpecManager
    if err := s.specClient.UpdateSpecContent(ctx, req.ProjectID, req.SpecID, targetVersion.Content); err != nil {
        s.logger.ErrorContext(ctx, "failed to update spec after rollback",
            logging.Err(err),
            "spec_id", req.SpecID,
        )
        // Note: Commit is already recorded, spec update failed
        // This is logged but we still return success for the commit
    }
    
    s.logger.InfoContext(ctx, "rollback completed",
        "spec_id", req.SpecID,
        "commit_id", commit.ID,
        "to_version", req.Version,
    )
    
    return commit, nil
}
```

### ChangelogService

```go
package service

import (
    "context"
    "fmt"
    "strings"
    
    "github.com/specbuilder/pkg/logging"
    "github.com/specbuilder/pkg/types"
    
    "github.com/specbuilder/internal/chronicle/model"
    "github.com/specbuilder/internal/chronicle/repository"
)

// ChangelogService generates changelogs
type ChangelogService struct {
    commitRepo *repository.CommitRepository
    changeRepo *repository.ChangeRepository
    dbManager  *repository.DBManager
    logger     logging.Logger
}

// GenerateChangelogRequest for generating a changelog
type GenerateChangelogRequest struct {
    ProjectID  types.ProjectID `json:"projectId" validate:"required"`
    FromCommit model.CommitID  `json:"fromCommit" validate:"required"`
    ToCommit   model.CommitID  `json:"toCommit" validate:"required"`
    Title      string          `json:"title" validate:"required"`
}

// GenerateChangelog creates a changelog between two commits
func (s *ChangelogService) GenerateChangelog(ctx context.Context, req GenerateChangelogRequest) (*model.Changelog, error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, req.ProjectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    // Get all commits between from and to
    commits, err := s.commitRepo.GetRange(ctx, projectDB, req.FromCommit, req.ToCommit)
    if err != nil {
        return nil, err
    }
    
    // Group changes by type
    sections := make(map[string][]model.ChangelogItem)
    sectionOrder := []string{"Added", "Changed", "Removed", "Fixed"}
    
    for _, commit := range commits {
        for _, change := range commit.Changes {
            section := changeTypeToSection(change.Type)
            item := model.ChangelogItem{
                SpecID:      change.SpecID,
                ChangeType:  change.Type,
                Description: generateDescription(change),
            }
            sections[section] = append(sections[section], item)
        }
    }
    
    // Build sections in order
    var changelogSections []model.ChangelogSection
    for _, title := range sectionOrder {
        if items, ok := sections[title]; ok && len(items) > 0 {
            changelogSections = append(changelogSections, model.ChangelogSection{
                Title: title,
                Items: items,
            })
        }
    }
    
    // Generate markdown content
    content := s.generateMarkdown(req.Title, changelogSections)
    
    changelog := &model.Changelog{
        ID:         model.NewChangelogID(),
        ProjectID:  req.ProjectID,
        FromCommit: req.FromCommit,
        ToCommit:   req.ToCommit,
        Title:      req.Title,
        Content:    content,
        Sections:   changelogSections,
        CreatedAt:  types.Now(),
    }
    
    s.logger.InfoContext(ctx, "changelog generated",
        "project_id", req.ProjectID,
        "from_commit", req.FromCommit,
        "to_commit", req.ToCommit,
        "sections", len(changelogSections),
    )
    
    return changelog, nil
}

func (s *ChangelogService) generateMarkdown(title string, sections []model.ChangelogSection) string {
    var sb strings.Builder
    
    sb.WriteString(fmt.Sprintf("# %s\n\n", title))
    
    for _, section := range sections {
        sb.WriteString(fmt.Sprintf("## %s\n\n", section.Title))
        
        for _, item := range section.Items {
            sb.WriteString(fmt.Sprintf("- %s\n", item.Description))
        }
        sb.WriteString("\n")
    }
    
    return sb.String()
}

func changeTypeToSection(changeType model.ChangeType) string {
    switch changeType {
    case model.ChangeTypeCreate:
        return "Added"
    case model.ChangeTypeUpdate:
        return "Changed"
    case model.ChangeTypeDelete:
        return "Removed"
    default:
        return "Changed"
    }
}

func generateDescription(change model.Change) string {
    switch change.Type {
    case model.ChangeTypeCreate:
        return fmt.Sprintf("Added `%s`", change.NewPath)
    case model.ChangeTypeDelete:
        return fmt.Sprintf("Removed `%s`", change.OldPath)
    case model.ChangeTypeRename:
        return fmt.Sprintf("Renamed `%s` to `%s`", change.OldPath, change.NewPath)
    case model.ChangeTypeUpdate:
        return fmt.Sprintf("Updated `%s` (+%d/-%d)", change.NewPath, change.Additions, change.Deletions)
    default:
        return fmt.Sprintf("Modified `%s`", change.NewPath)
    }
}
```

---

## Diff Engine

```go
package diff

import (
    "strings"
    
    "github.com/specbuilder/internal/chronicle/model"
)

// Engine implements diff algorithms
type Engine struct{}

// NewEngine creates a diff engine
func NewEngine() *Engine {
    return &Engine{}
}

// Diff generates diff hunks between two texts
func (e *Engine) Diff(oldText, newText string) []model.DiffHunk {
    oldLines := strings.Split(oldText, "\n")
    newLines := strings.Split(newText, "\n")
    
    // Use Myers diff algorithm (simplified)
    hunks := e.myersDiff(oldLines, newLines)
    
    return hunks
}

// UnifiedDiff generates a unified diff format
func (e *Engine) UnifiedDiff(oldText, newText, oldLabel, newLabel string) string {
    hunks := e.Diff(oldText, newText)
    
    var sb strings.Builder
    sb.WriteString(fmt.Sprintf("--- %s\n", oldLabel))
    sb.WriteString(fmt.Sprintf("+++ %s\n", newLabel))
    
    for _, hunk := range hunks {
        sb.WriteString(fmt.Sprintf("@@ -%d,%d +%d,%d @@\n",
            hunk.OldStart, hunk.OldLines,
            hunk.NewStart, hunk.NewLines,
        ))
        
        for _, line := range hunk.Lines {
            switch line.Type {
            case model.DiffLineContext:
                sb.WriteString(" " + line.Content + "\n")
            case model.DiffLineAddition:
                sb.WriteString("+" + line.Content + "\n")
            case model.DiffLineDeletion:
                sb.WriteString("-" + line.Content + "\n")
            }
        }
    }
    
    return sb.String()
}

// myersDiff implements Myers diff algorithm
func (e *Engine) myersDiff(oldLines, newLines []string) []model.DiffHunk {
    // Simplified implementation
    // Real implementation would use proper Myers algorithm
    
    var hunks []model.DiffHunk
    var currentHunk *model.DiffHunk
    
    maxLen := len(oldLines)
    if len(newLines) > maxLen {
        maxLen = len(newLines)
    }
    
    for i := 0; i < maxLen; i++ {
        var oldLine, newLine string
        hasOld := i < len(oldLines)
        hasNew := i < len(newLines)
        
        if hasOld {
            oldLine = oldLines[i]
        }
        if hasNew {
            newLine = newLines[i]
        }
        
        if hasOld && hasNew && oldLine == newLine {
            // Context line
            if currentHunk != nil {
                currentHunk.Lines = append(currentHunk.Lines, model.DiffLine{
                    Type:    model.DiffLineContext,
                    Content: oldLine,
                    OldNum:  i + 1,
                    NewNum:  i + 1,
                })
            }
        } else {
            // Start new hunk if needed
            if currentHunk == nil {
                currentHunk = &model.DiffHunk{
                    OldStart: i + 1,
                    NewStart: i + 1,
                }
                hunks = append(hunks, *currentHunk)
            }
            
            if hasOld && (!hasNew || oldLine != newLine) {
                currentHunk.Lines = append(currentHunk.Lines, model.DiffLine{
                    Type:    model.DiffLineDeletion,
                    Content: oldLine,
                    OldNum:  i + 1,
                })
                currentHunk.OldLines++
            }
            
            if hasNew && (!hasOld || oldLine != newLine) {
                currentHunk.Lines = append(currentHunk.Lines, model.DiffLine{
                    Type:    model.DiffLineAddition,
                    Content: newLine,
                    NewNum:  i + 1,
                })
                currentHunk.NewLines++
            }
        }
    }
    
    return hunks
}
```

---

## API Endpoints

### History Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/projects/{projectId}/history` | List commit history |
| GET | `/api/projects/{projectId}/commits/{commitId}` | Get commit details |
| POST | `/api/projects/{projectId}/commits` | Create commit |

### Spec Version Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/projects/{projectId}/specs/{specId}/history` | List spec versions |
| GET | `/api/projects/{projectId}/specs/{specId}/versions/{version}` | Get specific version |
| GET | `/api/projects/{projectId}/specs/{specId}/at?timestamp=` | Get version at time |

### Diff Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/projects/{projectId}/specs/{specId}/diff?from=&to=` | Compare versions |
| GET | `/api/projects/{projectId}/commits/{commitId}/diff` | Get commit diff |

### Rollback Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/projects/{projectId}/specs/{specId}/rollback` | Rollback to version |

### Changelog Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/projects/{projectId}/changelog/generate` | Generate changelog |
| GET | `/api/projects/{projectId}/changelogs` | List changelogs |

---

## Database Migrations

```sql
-- 001_create_chronicle.up.sql

-- Commits table
CREATE TABLE Commits (
    ID          TEXT PRIMARY KEY,
    ProjectID   TEXT NOT NULL,
    ParentID    TEXT,
    Message     TEXT NOT NULL,
    AuthorID    TEXT,
    AuthorName  TEXT NOT NULL,
    AuthorEmail TEXT,
    GitHash     TEXT,
    GitBranch   TEXT,
    Metadata    TEXT DEFAULT '{}',
    CreatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (ParentID) REFERENCES Commits(ID)
);

CREATE INDEX idx_commits_project ON Commits(ProjectID);
CREATE INDEX idx_commits_parent ON Commits(ParentID);
CREATE INDEX idx_commits_created ON Commits(CreatedAt DESC);
CREATE INDEX idx_commits_git ON Commits(GitHash) WHERE GitHash IS NOT NULL;

-- Changes table
CREATE TABLE Changes (
    ID          TEXT PRIMARY KEY,
    CommitID    TEXT NOT NULL,
    SpecID      TEXT NOT NULL,
    Type        TEXT NOT NULL,
    OldContent  TEXT,
    NewContent  TEXT,
    OldHash     TEXT,
    NewHash     TEXT,
    OldPath     TEXT,
    NewPath     TEXT,
    Additions   INTEGER DEFAULT 0,
    Deletions   INTEGER DEFAULT 0,
    CreatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (CommitID) REFERENCES Commits(ID)
);

CREATE INDEX idx_changes_commit ON Changes(CommitID);
CREATE INDEX idx_changes_spec ON Changes(SpecID);
CREATE INDEX idx_changes_type ON Changes(Type);

-- Spec versions table
CREATE TABLE SpecVersions (
    ID          TEXT PRIMARY KEY,
    SpecID      TEXT NOT NULL,
    CommitID    TEXT NOT NULL,
    Version     INTEGER NOT NULL,
    Content     TEXT NOT NULL,
    ContentHash TEXT NOT NULL,
    Path        TEXT NOT NULL,
    Metadata    TEXT DEFAULT '{}',
    CreatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy   TEXT,
    
    FOREIGN KEY (CommitID) REFERENCES Commits(ID)
);

CREATE UNIQUE INDEX idx_spec_versions_unique ON SpecVersions(SpecID, Version);
CREATE INDEX idx_spec_versions_spec ON SpecVersions(SpecID);
CREATE INDEX idx_spec_versions_commit ON SpecVersions(CommitID);
CREATE INDEX idx_spec_versions_created ON SpecVersions(CreatedAt);

-- Changelogs table
CREATE TABLE Changelogs (
    ID          TEXT PRIMARY KEY,
    ProjectID   TEXT NOT NULL,
    FromCommit  TEXT NOT NULL,
    ToCommit    TEXT NOT NULL,
    Title       TEXT NOT NULL,
    Content     TEXT NOT NULL,
    Sections    TEXT DEFAULT '[]',  -- JSON array
    CreatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (FromCommit) REFERENCES Commits(ID),
    FOREIGN KEY (ToCommit) REFERENCES Commits(ID)
);

CREATE INDEX idx_changelogs_project ON Changelogs(ProjectID);
CREATE INDEX idx_changelogs_range ON Changelogs(FromCommit, ToCommit);
```

---

## Configuration

```yaml
# chronicle/config.yaml
environment: development

server:
  host: "0.0.0.0"
  port: 8083
  read_timeout: 30s
  write_timeout: 30s

database:
  project_data_dir: "./data/projects"

logging:
  level: debug
  format: json
  add_source: true  # MANDATORY

services:
  specmgr:
    host: localhost
    port: 8081
    timeout: 30s

history:
  max_versions_per_spec: 1000
  prune_after_days: 365

diff:
  context_lines: 3
  max_file_size: 1048576  # 1MB

changelog:
  default_format: "markdown"
```

---

## Related Specifications

- [Phase 1: Shared Packages](../13-shared-packages/00-overview.md)
- [Phase 2: Gateway](./01-gateway.md)
- [Phase 3: SpecManager](./02-specmanager.md)
- [Phase 5: AI-Bridge](./04-aibridge.md)
