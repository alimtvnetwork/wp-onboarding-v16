# Phase 3: SpecManager Service Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Phase:** 3 of 9  
**Service:** `specmgr`  
**Port:** 8081  

---

## Overview

The SpecManager Service handles all specification and project CRUD operations, file management, validation, and maintains data integrity. It's the primary data management service for SpecBuilder Pro.

**Cross-References:**
- [Shared Packages](../13-shared-packages/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)
- [Gateway Service](./01-gateway.md)

---

## Responsibilities

- **Project Management**: Create, read, update, delete projects
- **Spec Management**: Full CRUD for specifications
- **File Operations**: Safe file read/write with validation
- **Path Safety**: SSRF and path traversal prevention
- **Validation**: Schema and content validation
- **Indexing**: Maintain project/spec index in projects.db

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                        SpecManager :8081                              │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │                      HTTP Handlers                               │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────────────┐ │ │
│  │  │ProjectHandler│ │ SpecHandler  │ │    FileHandler           │ │ │
│  │  └──────────────┘ └──────────────┘ └──────────────────────────┘ │ │
│  └────────────────────────────┬────────────────────────────────────┘ │
│                               │                                       │
│  ┌────────────────────────────┴────────────────────────────────────┐ │
│  │                       Service Layer                              │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────────────┐ │ │
│  │  │ProjectService│ │ SpecService  │ │    FileService           │ │ │
│  │  └──────────────┘ └──────────────┘ └──────────────────────────┘ │ │
│  └────────────────────────────┬────────────────────────────────────┘ │
│                               │                                       │
│  ┌────────────────────────────┴────────────────────────────────────┐ │
│  │                     Repository Layer                             │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────────────┐ │ │
│  │  │ ProjectRepo  │ │  SpecRepo    │ │    PathValidator         │ │ │
│  │  └──────────────┘ └──────────────┘ └──────────────────────────┘ │ │
│  └────────────────────────────┬────────────────────────────────────┘ │
│                               │                                       │
│  ┌────────────────────────────┴────────────────────────────────────┐ │
│  │                      Database Layer                              │ │
│  │  ┌──────────────────┐ ┌──────────────────────────────────────┐  │ │
│  │  │   projects.db    │ │   {project-id}/project.db            │  │ │
│  │  │   (Global)       │ │   (Per-Project)                      │  │ │
│  │  └──────────────────┘ └──────────────────────────────────────┘  │ │
│  └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Directory Structure

```
cmd/specmgr/
├── main.go
└── config.yaml

internal/specmgr/
├── server/
│   ├── server.go
│   └── routes.go
├── handler/
│   ├── project.go          # Project HTTP handlers
│   ├── spec.go             # Spec HTTP handlers
│   ├── file.go             # File HTTP handlers
│   └── validation.go       # Request validation
├── service/
│   ├── project.go          # Project business logic
│   ├── spec.go             # Spec business logic
│   ├── file.go             # File operations
│   └── validation.go       # Content validation
├── repository/
│   ├── project.go          # Project data access
│   ├── spec.go             # Spec data access
│   └── dbmanager.go        # Multi-database management
├── model/
│   ├── project.go          # Project domain model
│   ├── spec.go             # Spec domain model
│   └── file.go             # File domain model
├── security/
│   ├── pathvalidator.go    # Path traversal prevention
│   └── ssrf.go             # SSRF prevention
└── migrations/
    ├── projects/           # projects.db migrations
    └── project/            # project.db migrations
```

---

## Domain Models

### Project Model

```go
package model

import (
    "github.com/specbuilder/pkg/types"
)

// Project represents a specification project
type Project struct {
    ID          types.ProjectID   `json:"id"`
    Name        string            `json:"name"`
    Description string            `json:"description"`
    Path        string            `json:"path"`        // Root directory path
    Status      types.Status      `json:"status"`
    Tags        types.Tags        `json:"tags"`
    Metadata    types.Metadata    `json:"metadata"`
    SpecCount   int               `json:"specCount"`   // Denormalized count
    Timestamps  types.Timestamps  `json:"timestamps"`
    Version     types.Versioned   `json:"version"`
}

// CreateProjectRequest for creating a project
type CreateProjectRequest struct {
    Name        string         `json:"name" validate:"required,min=1,max=255"`
    Description string         `json:"description" validate:"max=2000"`
    Path        string         `json:"path" validate:"required,safepath"`
    Tags        []string       `json:"tags" validate:"max=20,dive,max=50"`
    Metadata    map[string]any `json:"metadata"`
}

// UpdateProjectRequest for updating a project
type UpdateProjectRequest struct {
    Name        *string        `json:"name" validate:"omitempty,min=1,max=255"`
    Description *string        `json:"description" validate:"omitempty,max=2000"`
    Status      *types.Status  `json:"status" validate:"omitempty,oneof=DRAFT ACTIVE ARCHIVED"`
    Tags        []string       `json:"tags" validate:"omitempty,max=20,dive,max=50"`
    Metadata    map[string]any `json:"metadata"`
}
```

### Spec Model

```go
package model

import (
    "github.com/specbuilder/pkg/types"
)

// SpecType represents the type of specification
type SpecType string

const (
    SpecTypeFeature     SpecType = "FEATURE"
    SpecTypeAPI         SpecType = "API"
    SpecTypeDatabase    SpecType = "DATABASE"
    SpecTypeDiagram     SpecType = "DIAGRAM"
    SpecTypeGuideline   SpecType = "GUIDELINE"
    SpecTypeOverview    SpecType = "OVERVIEW"
    SpecTypePrompt      SpecType = "PROMPT"
    SpecTypeResearch    SpecType = "RESEARCH"
)

// Spec represents a specification document
type Spec struct {
    ID          types.SpecID      `json:"id"`
    ProjectID   types.ProjectID   `json:"projectId"`
    Name        string            `json:"name"`
    Title       string            `json:"title"`
    Path        string            `json:"path"`        // Relative to project root
    Type        SpecType          `json:"type"`
    Status      types.Status      `json:"status"`
    Priority    types.Priority    `json:"priority"`
    Content     string            `json:"content"`     // Markdown content
    ContentHash string            `json:"contentHash"` // SHA-256 of content
    WordCount   int               `json:"wordCount"`
    Tags        types.Tags        `json:"tags"`
    Metadata    types.Metadata    `json:"metadata"`
    
    // Cross-references
    References  []types.SpecID    `json:"references"`  // Specs this references
    ReferencedBy []types.SpecID   `json:"referencedBy"` // Specs referencing this
    
    Timestamps  types.Timestamps  `json:"timestamps"`
    Version     types.Versioned   `json:"version"`
}

// CreateSpecRequest for creating a spec
type CreateSpecRequest struct {
    ProjectID   types.ProjectID `json:"projectId" validate:"required"`
    Name        string          `json:"name" validate:"required,min=1,max=255"`
    Title       string          `json:"title" validate:"required,min=1,max=500"`
    Path        string          `json:"path" validate:"required,safepath,endswith=.md"`
    Type        SpecType        `json:"type" validate:"required"`
    Priority    types.Priority  `json:"priority"`
    Content     string          `json:"content" validate:"required"`
    Tags        []string        `json:"tags" validate:"max=20,dive,max=50"`
    Metadata    map[string]any  `json:"metadata"`
}

// UpdateSpecRequest for updating a spec
type UpdateSpecRequest struct {
    Title      *string         `json:"title" validate:"omitempty,min=1,max=500"`
    Content    *string         `json:"content"`
    Type       *SpecType       `json:"type"`
    Status     *types.Status   `json:"status"`
    Priority   *types.Priority `json:"priority"`
    Tags       []string        `json:"tags" validate:"omitempty,max=20,dive,max=50"`
    Metadata   map[string]any  `json:"metadata"`
}

// SpecVersion represents a version snapshot
type SpecVersion struct {
    ID          types.SpecID    `json:"id"`
    SpecID      types.SpecID    `json:"specId"`
    Version     int             `json:"version"`
    Content     string          `json:"content"`
    ContentHash string          `json:"contentHash"`
    ChangedBy   *types.UserID   `json:"changedBy"`
    ChangeNote  string          `json:"changeNote"`
    CreatedAt   types.Timestamp `json:"createdAt"`
}
```

---

## Service Layer

### ProjectService

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
    
    "github.com/specbuilder/internal/specmgr/model"
    "github.com/specbuilder/internal/specmgr/repository"
    "github.com/specbuilder/internal/specmgr/security"
)

// ProjectService handles project business logic
type ProjectService struct {
    projectRepo  *repository.ProjectRepository
    pathValidator *security.PathValidator
    dbManager    *repository.DBManager
    logger       logging.Logger
}

// NewProjectService creates a project service
func NewProjectService(
    projectRepo *repository.ProjectRepository,
    pathValidator *security.PathValidator,
    dbManager *repository.DBManager,
    logger logging.Logger,
) *ProjectService {
    return &ProjectService{
        projectRepo:   projectRepo,
        pathValidator: pathValidator,
        dbManager:     dbManager,
        logger:        logger,
    }
}

// CreateProject creates a new project
func (s *ProjectService) CreateProject(ctx context.Context, req model.CreateProjectRequest) (*model.Project, error) {
    // CRITICAL: Validate path is safe (no traversal, allowed directory)
    if err := s.pathValidator.ValidatePath(req.Path); err != nil {
        s.logger.WarnContext(ctx, "invalid project path",
            logging.Err(err),
            "path", req.Path,
        )
        return nil, errors.NewSecurity(
            errors.ErrSecurityPathTraversal,
            "invalid project path",
            map[string]any{"path": req.Path},
        ).WithCause(err)
    }
    
    // Check for duplicate name
    existing, err := s.projectRepo.GetByName(ctx, req.Name)
    if err == nil && existing != nil {
        return nil, errors.NewDatabaseDuplicate("Project", "name", req.Name)
    }
    
    // Create project
    project := &model.Project{
        ID:          types.NewProjectID(),
        Name:        req.Name,
        Description: req.Description,
        Path:        req.Path,
        Status:      types.StatusDraft,
        Tags:        req.Tags,
        Metadata:    req.Metadata,
        SpecCount:   0,
        Timestamps:  types.NewTimestamps(),
        Version:     types.NewVersioned(),
    }
    
    // Create project in global DB
    if err := s.projectRepo.Create(ctx, project); err != nil {
        s.logger.ErrorContext(ctx, "failed to create project",
            logging.Err(err),
            "project_name", req.Name,
        )
        return nil, err
    }
    
    // Initialize project-specific database
    if err := s.dbManager.InitProjectDB(ctx, project.ID, project.Path); err != nil {
        s.logger.ErrorContext(ctx, "failed to init project database",
            logging.Err(err),
            "project_id", project.ID,
        )
        // Rollback project creation
        s.projectRepo.Delete(ctx, project.ID)
        return nil, err
    }
    
    s.logger.InfoContext(ctx, "project created",
        "project_id", project.ID,
        "project_name", project.Name,
        "path", project.Path,
    )
    
    return project, nil
}

// GetProject retrieves a project by ID
func (s *ProjectService) GetProject(ctx context.Context, id types.ProjectID) (*model.Project, error) {
    project, err := s.projectRepo.GetByID(ctx, id)
    if err != nil {
        s.logger.DebugContext(ctx, "project not found",
            "project_id", id,
        )
        return nil, err
    }
    
    return project, nil
}

// ListProjects lists all projects with pagination
func (s *ProjectService) ListProjects(ctx context.Context, req types.PageRequest) (*types.PageResponse[model.Project], error) {
    projects, total, err := s.projectRepo.List(ctx, req)
    if err != nil {
        return nil, err
    }
    
    response := types.NewPageResponse(projects, req, total)
    return &response, nil
}

// UpdateProject updates a project
func (s *ProjectService) UpdateProject(ctx context.Context, id types.ProjectID, req model.UpdateProjectRequest) (*model.Project, error) {
    project, err := s.projectRepo.GetByID(ctx, id)
    if err != nil {
        return nil, err
    }
    
    // Apply updates
    if req.Name != nil {
        project.Name = *req.Name
    }
    if req.Description != nil {
        project.Description = *req.Description
    }
    if req.Status != nil {
        if err := req.Status.Validate(); err != nil {
            return nil, errors.NewValidation(
                errors.ErrValidationEnum,
                "invalid status",
                map[string]any{"status": *req.Status},
            )
        }
        project.Status = *req.Status
    }
    if req.Tags != nil {
        project.Tags = req.Tags
    }
    if req.Metadata != nil {
        project.Metadata = req.Metadata
    }
    
    project.Timestamps.Touch()
    project.Version.Increment(nil) // TODO: Get user ID from context
    
    if err := s.projectRepo.Update(ctx, project); err != nil {
        return nil, err
    }
    
    s.logger.InfoContext(ctx, "project updated",
        "project_id", id,
        "version", project.Version.Version,
    )
    
    return project, nil
}

// DeleteProject deletes a project
func (s *ProjectService) DeleteProject(ctx context.Context, id types.ProjectID) error {
    project, err := s.projectRepo.GetByID(ctx, id)
    if err != nil {
        return err
    }
    
    // Soft delete
    project.Timestamps.SoftDelete()
    project.Status = types.StatusDeleted
    
    if err := s.projectRepo.Update(ctx, project); err != nil {
        return err
    }
    
    s.logger.InfoContext(ctx, "project deleted",
        "project_id", id,
    )
    
    return nil
}
```

### SpecService

```go
package service

import (
    "context"
    "crypto/sha256"
    "encoding/hex"
    "strings"
    "unicode/utf8"
    
    "github.com/specbuilder/pkg/database"
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
    "github.com/specbuilder/pkg/types"
    
    "github.com/specbuilder/internal/specmgr/model"
    "github.com/specbuilder/internal/specmgr/repository"
    "github.com/specbuilder/internal/specmgr/security"
)

// SpecService handles spec business logic
type SpecService struct {
    specRepo      *repository.SpecRepository
    projectRepo   *repository.ProjectRepository
    fileService   *FileService
    pathValidator *security.PathValidator
    dbManager     *repository.DBManager
    logger        logging.Logger
}

// NewSpecService creates a spec service
func NewSpecService(
    specRepo *repository.SpecRepository,
    projectRepo *repository.ProjectRepository,
    fileService *FileService,
    pathValidator *security.PathValidator,
    dbManager *repository.DBManager,
    logger logging.Logger,
) *SpecService {
    return &SpecService{
        specRepo:      specRepo,
        projectRepo:   projectRepo,
        fileService:   fileService,
        pathValidator: pathValidator,
        dbManager:     dbManager,
        logger:        logger,
    }
}

// CreateSpec creates a new specification
func (s *SpecService) CreateSpec(ctx context.Context, req model.CreateSpecRequest) (*model.Spec, error) {
    // Verify project exists
    project, err := s.projectRepo.GetByID(ctx, req.ProjectID)
    if err != nil {
        return nil, err
    }
    
    // CRITICAL: Validate path is safe
    fullPath := filepath.Join(project.Path, req.Path)
    if err := s.pathValidator.ValidatePath(fullPath); err != nil {
        s.logger.WarnContext(ctx, "invalid spec path",
            logging.Err(err),
            "path", req.Path,
            "project_id", req.ProjectID,
        )
        return nil, errors.NewSecurity(
            errors.ErrSecurityPathTraversal,
            "invalid spec path",
            map[string]any{"path": req.Path},
        ).WithCause(err)
    }
    
    // Validate spec type
    if err := req.Type.Validate(); err != nil {
        return nil, errors.NewValidation(
            errors.ErrValidationEnum,
            "invalid spec type",
            map[string]any{"type": req.Type},
        )
    }
    
    // Compute content hash
    hash := sha256.Sum256([]byte(req.Content))
    contentHash := hex.EncodeToString(hash[:])
    
    // Count words
    wordCount := countWords(req.Content)
    
    // Set default priority
    priority := req.Priority
    if priority == "" {
        priority = types.PriorityMedium
    }
    
    // Create spec
    spec := &model.Spec{
        ID:          types.NewSpecID(),
        ProjectID:   req.ProjectID,
        Name:        req.Name,
        Title:       req.Title,
        Path:        req.Path,
        Type:        req.Type,
        Status:      types.StatusDraft,
        Priority:    priority,
        Content:     req.Content,
        ContentHash: contentHash,
        WordCount:   wordCount,
        Tags:        req.Tags,
        Metadata:    req.Metadata,
        References:  []types.SpecID{},
        ReferencedBy: []types.SpecID{},
        Timestamps:  types.NewTimestamps(),
        Version:     types.NewVersioned(),
    }
    
    // Get project database
    projectDB, err := s.dbManager.GetProjectDB(ctx, req.ProjectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    // Use transaction for consistency
    err = projectDB.WithTx(ctx, func(tx *database.Tx) error {
        // Create spec in database
        if err := s.specRepo.CreateWithTx(ctx, tx, spec); err != nil {
            return err
        }
        
        // Write file to disk
        if err := s.fileService.WriteSpec(ctx, project.Path, spec); err != nil {
            return err // Transaction will rollback
        }
        
        return nil
    })
    
    if err != nil {
        s.logger.ErrorContext(ctx, "failed to create spec",
            logging.Err(err),
            "project_id", req.ProjectID,
            "spec_name", req.Name,
        )
        return nil, err
    }
    
    // Update project spec count
    s.projectRepo.IncrementSpecCount(ctx, req.ProjectID, 1)
    
    s.logger.InfoContext(ctx, "spec created",
        "spec_id", spec.ID,
        "project_id", req.ProjectID,
        "path", req.Path,
        "word_count", wordCount,
    )
    
    return spec, nil
}

// GetSpec retrieves a spec by ID
func (s *SpecService) GetSpec(ctx context.Context, projectID types.ProjectID, specID types.SpecID) (*model.Spec, error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    spec, err := s.specRepo.GetByIDWithDB(ctx, projectDB, specID)
    if err != nil {
        s.logger.DebugContext(ctx, "spec not found",
            "project_id", projectID,
            "spec_id", specID,
        )
        return nil, err
    }
    
    return spec, nil
}

// UpdateSpec updates a spec
func (s *SpecService) UpdateSpec(ctx context.Context, projectID types.ProjectID, specID types.SpecID, req model.UpdateSpecRequest) (*model.Spec, error) {
    project, err := s.projectRepo.GetByID(ctx, projectID)
    if err != nil {
        return nil, err
    }
    
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    spec, err := s.specRepo.GetByIDWithDB(ctx, projectDB, specID)
    if err != nil {
        return nil, err
    }
    
    // Apply updates
    contentChanged := false
    
    if req.Title != nil {
        spec.Title = *req.Title
    }
    if req.Content != nil {
        spec.Content = *req.Content
        hash := sha256.Sum256([]byte(*req.Content))
        spec.ContentHash = hex.EncodeToString(hash[:])
        spec.WordCount = countWords(*req.Content)
        contentChanged = true
    }
    if req.Type != nil {
        if err := req.Type.Validate(); err != nil {
            return nil, errors.NewValidation(
                errors.ErrValidationEnum,
                "invalid spec type",
                map[string]any{"type": *req.Type},
            )
        }
        spec.Type = *req.Type
    }
    if req.Status != nil {
        if err := req.Status.Validate(); err != nil {
            return nil, errors.NewValidation(
                errors.ErrValidationEnum,
                "invalid status",
                map[string]any{"status": *req.Status},
            )
        }
        spec.Status = *req.Status
    }
    if req.Priority != nil {
        if err := req.Priority.Validate(); err != nil {
            return nil, errors.NewValidation(
                errors.ErrValidationEnum,
                "invalid priority",
                map[string]any{"priority": *req.Priority},
            )
        }
        spec.Priority = *req.Priority
    }
    if req.Tags != nil {
        spec.Tags = req.Tags
    }
    if req.Metadata != nil {
        spec.Metadata = req.Metadata
    }
    
    spec.Timestamps.Touch()
    spec.Version.Increment(nil)
    
    // Use transaction
    err = projectDB.WithTx(ctx, func(tx *database.Tx) error {
        // Save version before updating (for history)
        if contentChanged {
            if err := s.specRepo.SaveVersionWithTx(ctx, tx, spec); err != nil {
                return err
            }
        }
        
        // Update in database
        if err := s.specRepo.UpdateWithTx(ctx, tx, spec); err != nil {
            return err
        }
        
        // Update file on disk
        if contentChanged {
            if err := s.fileService.WriteSpec(ctx, project.Path, spec); err != nil {
                return err
            }
        }
        
        return nil
    })
    
    if err != nil {
        s.logger.ErrorContext(ctx, "failed to update spec",
            logging.Err(err),
            "spec_id", specID,
        )
        return nil, err
    }
    
    s.logger.InfoContext(ctx, "spec updated",
        "spec_id", specID,
        "version", spec.Version.Version,
        "content_changed", contentChanged,
    )
    
    return spec, nil
}

// DeleteSpec soft-deletes a spec
func (s *SpecService) DeleteSpec(ctx context.Context, projectID types.ProjectID, specID types.SpecID) error {
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return err
    }
    defer projectDB.Close()
    
    spec, err := s.specRepo.GetByIDWithDB(ctx, projectDB, specID)
    if err != nil {
        return err
    }
    
    spec.Timestamps.SoftDelete()
    spec.Status = types.StatusDeleted
    
    if err := s.specRepo.UpdateWithDB(ctx, projectDB, spec); err != nil {
        return err
    }
    
    // Decrement project spec count
    s.projectRepo.IncrementSpecCount(ctx, projectID, -1)
    
    s.logger.InfoContext(ctx, "spec deleted",
        "spec_id", specID,
        "project_id", projectID,
    )
    
    return nil
}

// ListSpecs lists specs in a project
func (s *SpecService) ListSpecs(ctx context.Context, projectID types.ProjectID, req types.PageRequest) (*types.PageResponse[model.Spec], error) {
    projectDB, err := s.dbManager.GetProjectDB(ctx, projectID)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    specs, total, err := s.specRepo.ListWithDB(ctx, projectDB, req)
    if err != nil {
        return nil, err
    }
    
    response := types.NewPageResponse(specs, req, total)
    return &response, nil
}

func countWords(content string) int {
    return len(strings.Fields(content))
}
```

---

## Security: Path Validation

```go
package security

import (
    "net"
    "net/url"
    "os"
    "path/filepath"
    "strings"
    
    "github.com/specbuilder/pkg/errors"
)

// PathValidator validates file paths for security
type PathValidator struct {
    allowedRoots   []string
    blockedPatterns []string
}

// NewPathValidator creates a path validator
func NewPathValidator(allowedRoots []string) *PathValidator {
    return &PathValidator{
        allowedRoots: allowedRoots,
        blockedPatterns: []string{
            "..",
            "~",
            "$",
            "`",
            "|",
            ";",
            "&",
            "\\",
        },
    }
}

// ValidatePath ensures path is safe
func (v *PathValidator) ValidatePath(path string) error {
    // Check for blocked patterns
    for _, pattern := range v.blockedPatterns {
        if strings.Contains(path, pattern) {
            return errors.NewSecurity(
                errors.ErrSecurityPathTraversal,
                "path contains blocked pattern",
                map[string]any{"pattern": pattern, "path": path},
            )
        }
    }
    
    // Clean and normalize the path
    cleanPath := filepath.Clean(path)
    
    // Ensure path is absolute
    absPath, err := filepath.Abs(cleanPath)
    if err != nil {
        return errors.NewSecurity(
            errors.ErrSecurityPathTraversal,
            "invalid path",
            map[string]any{"path": path},
        ).WithCause(err)
    }
    
    // Check path is within allowed roots
    allowed := false
    for _, root := range v.allowedRoots {
        absRoot, err := filepath.Abs(root)
        if err != nil {
            continue
        }
        if strings.HasPrefix(absPath, absRoot) {
            allowed = true
            break
        }
    }
    
    if !allowed {
        return errors.NewSecurity(
            errors.ErrSecurityPathTraversal,
            "path outside allowed directories",
            map[string]any{
                "path":         path,
                "allowedRoots": v.allowedRoots,
            },
        )
    }
    
    return nil
}

// ValidateSpecPath validates a spec file path
func (v *PathValidator) ValidateSpecPath(projectRoot, specPath string) error {
    // Spec path must be relative
    if filepath.IsAbs(specPath) {
        return errors.NewValidation(
            errors.ErrValidationFormat,
            "spec path must be relative",
            map[string]any{"path": specPath},
        )
    }
    
    // Must end with .md
    if !strings.HasSuffix(strings.ToLower(specPath), ".md") {
        return errors.NewValidation(
            errors.ErrValidationFormat,
            "spec path must end with .md",
            map[string]any{"path": specPath},
        )
    }
    
    // Full path validation
    fullPath := filepath.Join(projectRoot, specPath)
    return v.ValidatePath(fullPath)
}
```

---

## API Endpoints

### Project Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/projects` | Create project |
| GET | `/api/projects` | List projects |
| GET | `/api/projects/{id}` | Get project |
| PUT | `/api/projects/{id}` | Update project |
| DELETE | `/api/projects/{id}` | Delete project |

### Spec Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/projects/{projectId}/specs` | Create spec |
| GET | `/api/projects/{projectId}/specs` | List specs |
| GET | `/api/projects/{projectId}/specs/{specId}` | Get spec |
| PUT | `/api/projects/{projectId}/specs/{specId}` | Update spec |
| DELETE | `/api/projects/{projectId}/specs/{specId}` | Delete spec |
| GET | `/api/projects/{projectId}/specs/{specId}/versions` | List versions |

---

## Database Migrations

### projects.db Migration

```sql
-- 001_create_projects.up.sql
CREATE TABLE Projects (
    ID          TEXT PRIMARY KEY,
    Name        TEXT NOT NULL UNIQUE,
    Description TEXT,
    Path        TEXT NOT NULL,
    Status      TEXT NOT NULL DEFAULT 'DRAFT',
    Tags        TEXT DEFAULT '[]',      -- JSON array
    Metadata    TEXT DEFAULT '{}',      -- JSON object
    SpecCount   INTEGER DEFAULT 0,
    CreatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    DeletedAt   DATETIME,
    Version     INTEGER DEFAULT 1,
    UpdatedBy   TEXT
);

CREATE INDEX idx_projects_name ON Projects(Name);
CREATE INDEX idx_projects_status ON Projects(Status) WHERE DeletedAt IS NULL;
CREATE INDEX idx_projects_deleted ON Projects(DeletedAt);
```

### project.db Migration

```sql
-- 001_create_specs.up.sql
CREATE TABLE Specs (
    ID          TEXT PRIMARY KEY,
    ProjectID   TEXT NOT NULL,
    Name        TEXT NOT NULL,
    Title       TEXT NOT NULL,
    Path        TEXT NOT NULL,
    Type        TEXT NOT NULL,
    Status      TEXT NOT NULL DEFAULT 'DRAFT',
    Priority    TEXT NOT NULL DEFAULT 'MEDIUM',
    Content     TEXT NOT NULL,
    ContentHash TEXT NOT NULL,
    WordCount   INTEGER DEFAULT 0,
    Tags        TEXT DEFAULT '[]',
    Metadata    TEXT DEFAULT '{}',
    References  TEXT DEFAULT '[]',
    ReferencedBy TEXT DEFAULT '[]',
    CreatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    DeletedAt   DATETIME,
    Version     INTEGER DEFAULT 1,
    UpdatedBy   TEXT
);

CREATE UNIQUE INDEX idx_specs_path ON Specs(ProjectID, Path) WHERE DeletedAt IS NULL;
CREATE INDEX idx_specs_type ON Specs(Type) WHERE DeletedAt IS NULL;
CREATE INDEX idx_specs_status ON Specs(Status) WHERE DeletedAt IS NULL;
CREATE INDEX idx_specs_deleted ON Specs(DeletedAt);

-- FTS for content search
CREATE VIRTUAL TABLE SpecsFTS USING fts5(
    ID,
    Name,
    Title,
    Content,
    Tags,
    content='Specs',
    content_rowid='rowid'
);

CREATE TRIGGER specs_ai AFTER INSERT ON Specs BEGIN
    INSERT INTO SpecsFTS(rowid, ID, Name, Title, Content, Tags)
    VALUES (new.rowid, new.ID, new.Name, new.Title, new.Content, new.Tags);
END;

CREATE TRIGGER specs_ad AFTER DELETE ON Specs BEGIN
    INSERT INTO SpecsFTS(SpecsFTS, rowid, ID, Name, Title, Content, Tags)
    VALUES ('delete', old.rowid, old.ID, old.Name, old.Title, old.Content, old.Tags);
END;

CREATE TRIGGER specs_au AFTER UPDATE ON Specs BEGIN
    INSERT INTO SpecsFTS(SpecsFTS, rowid, ID, Name, Title, Content, Tags)
    VALUES ('delete', old.rowid, old.ID, old.Name, old.Title, old.Content, old.Tags);
    INSERT INTO SpecsFTS(rowid, ID, Name, Title, Content, Tags)
    VALUES (new.rowid, new.ID, new.Name, new.Title, new.Content, new.Tags);
END;

-- Version history
CREATE TABLE SpecVersions (
    ID          TEXT PRIMARY KEY,
    SpecID      TEXT NOT NULL,
    Version     INTEGER NOT NULL,
    Content     TEXT NOT NULL,
    ContentHash TEXT NOT NULL,
    ChangedBy   TEXT,
    ChangeNote  TEXT,
    CreatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (SpecID) REFERENCES Specs(ID)
);

CREATE INDEX idx_spec_versions ON SpecVersions(SpecID, Version);
```

---

## Configuration

```yaml
# specmgr/config.yaml
environment: development

server:
  host: "0.0.0.0"
  port: 8081
  read_timeout: 30s
  write_timeout: 30s

database:
  projects_path: "./data/projects.db"
  project_data_dir: "./data/projects"
  auto_migrate: true

logging:
  level: debug
  format: json
  add_source: true  # MANDATORY

security:
  allowed_roots:
    - "./data/projects"
    - "/home/specbuilder/projects"
  max_file_size: 10485760  # 10MB
  allowed_extensions:
    - ".md"
    - ".yaml"
    - ".json"

validation:
  max_name_length: 255
  max_title_length: 500
  max_content_length: 1048576  # 1MB
  max_tags: 20
  max_tag_length: 50
```

---

## Related Specifications

- [Phase 1: Shared Packages](../13-shared-packages/00-overview.md)
- [Phase 2: Gateway](./01-gateway.md)
- [Phase 4: Chronicle](./03-chronicle.md)
