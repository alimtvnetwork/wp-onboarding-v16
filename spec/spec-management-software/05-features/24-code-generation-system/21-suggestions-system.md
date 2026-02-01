# Suggestions System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

The Suggestions System automatically generates actionable improvement recommendations during AI interactions. Each task or chat interaction produces 5-10 contextual suggestions that are tracked until resolved. Suggestions are stored in a dedicated folder structure and linked to their originating tasks.

**Cross-References:**
- [Instruction System](../06-ai-integration/03-instruction-system.md)
- [Code Generation System](./00-overview.md)
- [Project Editor UI](./15-project-editor-ui.md)
- [AI Integration](../06-ai-integration/00-overview.md)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          SUGGESTIONS SYSTEM                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐   │
│  │    Task/     │    │  Suggestion  │    │  Suggestion  │    │  Suggestion  │   │
│  │    Chat      │───▶│  Generator   │───▶│   Storage    │───▶│   Tracker    │   │
│  │  Completion  │    │     (AI)     │    │   (Files)    │    │     (DB)     │   │
│  └──────────────┘    └──────────────┘    └──────────────┘    └──────────────┘   │
│                             │                   │                    │           │
│                             │                   │                    │           │
│                             ▼                   ▼                    ▼           │
│                      ┌──────────────────────────────────────────────────────┐   │
│                      │               Suggestion Resolution                   │   │
│                      │                                                       │   │
│                      │  pending/             ───▶           resolved/        │   │
│                      │  suggestion_001.md    (resolve)      suggestion_001.md│   │
│                      │                                                       │   │
│                      └──────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Folder Structure

```
{workDirectory}/
└── data/
    └── projects/
        └── {project_name}/
            └── suggestions/
                ├── pending/                    # Active suggestions
                │   ├── 2026-01-29_001_add-error-handling.md
                │   ├── 2026-01-29_002_improve-api-docs.md
                │   ├── 2026-01-29_003_add-unit-tests.md
                │   └── ...
                │
                └── resolved/                   # Completed suggestions
                    ├── 2026-01-28_001_fix-auth-flow.md
                    ├── 2026-01-28_002_add-validation.md
                    └── ...
```

### File Naming Convention

| Component | Format | Example |
|-----------|--------|---------|
| Date | `YYYY-MM-DD` | `2026-01-29` |
| Sequence | `###` (3-digit daily sequence) | `001`, `002` |
| Slug | Kebab-case summary (max 50 chars) | `add-error-handling` |
| Full Name | `{date}_{seq}_{slug}.md` | `2026-01-29_001_add-error-handling.md` |

---

## Suggestion File Format

```markdown
# Suggestion: Add Error Handling to API Endpoints

**ID:** sugg_a1b2c3d4  
**Status:** pending  
**Priority:** high  
**Created:** 2026-01-29T10:30:00Z  
**Updated:** 2026-01-29T10:30:00Z  

---

## Source Reference

| Field | Value |
|-------|-------|
| Type | task |
| Source ID | task_e5f6g7h8 |
| Source Title | Implement User Authentication API |
| Chat Session | chat_i9j0k1l2 |
| Related Files | `spec/api/auth-endpoints.md`, `spec/api/error-codes.md` |

---

## Suggestion Details

### Summary

Add comprehensive error handling to all authentication API endpoints to improve reliability and debugging.

### Description

The current authentication API implementation lacks proper error handling for edge cases. This could lead to unclear error messages for clients and difficulty in debugging production issues.

### Recommended Actions

1. **Add try-catch blocks** to all endpoint handlers
2. **Create custom error classes** for auth-specific errors
3. **Implement error logging** with request context
4. **Add error response schemas** to API documentation
5. **Create error code mappings** for client consumption

### Affected Files

- `BE/internal/api/auth_handler.go`
- `BE/internal/errors/auth_errors.go`
- `spec/api/error-codes.md`

### Estimated Effort

- **Time:** 2-4 hours
- **Complexity:** Medium
- **Dependencies:** Error code registry must be defined first

---

## Resolution

### Resolution Status

- [ ] Not started
- [ ] In progress
- [ ] Completed
- [ ] Deferred
- [ ] Rejected

### Resolution Notes

_To be filled when resolved_

### Resolution Date

_To be filled when resolved_

### Resolved By

_To be filled when resolved_
```

---

## Database Schema

### Suggestion Table

```sql
CREATE TABLE Suggestion (
    Id TEXT PRIMARY KEY,              -- UUID
    ProjectId TEXT NOT NULL,          -- Reference to project
    
    -- File Information
    FilePath TEXT NOT NULL,           -- Relative path to suggestion file
    FileName TEXT NOT NULL,           -- File name
    
    -- Content
    Title TEXT NOT NULL,              -- Suggestion title
    Summary TEXT NOT NULL,            -- Brief summary (max 500 chars)
    Priority TEXT NOT NULL CHECK (Priority IN ('low', 'medium', 'high', 'critical')),
    
    -- Source Reference
    SourceType TEXT NOT NULL CHECK (SourceType IN ('task', 'chat', 'validation', 'build')),
    SourceId TEXT NOT NULL,           -- ID of originating task/chat
    SourceTitle TEXT,                 -- Title of source for display
    ChatSessionId TEXT,               -- Optional chat session
    
    -- Related Files
    RelatedFiles TEXT,                -- JSON array of file paths
    AffectedFiles TEXT,               -- JSON array of files to modify
    
    -- Effort Estimation
    EstimatedHours REAL,              -- Estimated time
    Complexity TEXT CHECK (Complexity IN ('low', 'medium', 'high')),
    
    -- Status
    Status TEXT NOT NULL CHECK (Status IN (
        'pending',      -- New suggestion
        'in_progress',  -- Being worked on
        'completed',    -- Done
        'deferred',     -- Postponed
        'rejected'      -- Not applicable
    )) DEFAULT 'pending',
    
    -- Resolution
    ResolvedAt TEXT,
    ResolvedById TEXT,
    ResolutionNotes TEXT,
    
    -- Auto-generated
    GeneratedByModel TEXT,            -- Which AI model generated
    GenerationContext TEXT,           -- Context used for generation
    
    -- Timestamps
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    
    -- Sequence (daily)
    DailySequence INTEGER NOT NULL,
    
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE,
    FOREIGN KEY (ResolvedById) REFERENCES User(Id) ON DELETE SET NULL
);

CREATE INDEX IX_Suggestion_ProjectId ON Suggestion(ProjectId);
CREATE INDEX IX_Suggestion_Status ON Suggestion(Status);
CREATE INDEX IX_Suggestion_SourceId ON Suggestion(SourceId);
CREATE INDEX IX_Suggestion_Priority ON Suggestion(Priority);
CREATE INDEX IX_Suggestion_CreatedAt ON Suggestion(CreatedAt DESC);
```

### SuggestionTag Table

```sql
CREATE TABLE SuggestionTag (
    Id TEXT PRIMARY KEY,
    SuggestionId TEXT NOT NULL,
    Tag TEXT NOT NULL,              -- e.g., 'api', 'performance', 'security'
    
    FOREIGN KEY (SuggestionId) REFERENCES Suggestion(Id) ON DELETE CASCADE
);

CREATE INDEX IX_SuggestionTag_SuggestionId ON SuggestionTag(SuggestionId);
CREATE INDEX IX_SuggestionTag_Tag ON SuggestionTag(Tag);
CREATE UNIQUE INDEX UX_SuggestionTag_Unique ON SuggestionTag(SuggestionId, Tag);
```

---

## GORM Models

```go
package models

import (
    "time"
    "gorm.io/datatypes"
)

// Suggestion represents an AI-generated improvement recommendation
type Suggestion struct {
    Id        string `gorm:"primaryKey;type:TEXT"`
    ProjectId string `gorm:"type:TEXT;not null;index"`
    Project   *Project `gorm:"foreignKey:ProjectId"`
    
    // File Information
    FilePath string `gorm:"type:TEXT;not null"`
    FileName string `gorm:"type:TEXT;not null"`
    
    // Content
    Title    string `gorm:"type:TEXT;not null"`
    Summary  string `gorm:"type:TEXT;not null"`
    Priority string `gorm:"type:TEXT;not null;default:'medium'"`
    
    // Source Reference
    SourceType    string  `gorm:"type:TEXT;not null"`
    SourceId      string  `gorm:"type:TEXT;not null;index"`
    SourceTitle   *string `gorm:"type:TEXT"`
    ChatSessionId *string `gorm:"type:TEXT"`
    
    // Related Files (JSON)
    RelatedFiles  datatypes.JSON `gorm:"type:TEXT"`
    AffectedFiles datatypes.JSON `gorm:"type:TEXT"`
    
    // Effort
    EstimatedHours *float64 `gorm:"type:REAL"`
    Complexity     *string  `gorm:"type:TEXT"`
    
    // Status
    Status string `gorm:"type:TEXT;not null;default:'pending';index"`
    
    // Resolution
    ResolvedAt      *time.Time
    ResolvedById    *string `gorm:"type:TEXT"`
    ResolvedBy      *User   `gorm:"foreignKey:ResolvedById"`
    ResolutionNotes *string `gorm:"type:TEXT"`
    
    // AI Generation
    GeneratedByModel  *string `gorm:"type:TEXT"`
    GenerationContext *string `gorm:"type:TEXT"`
    
    // Timestamps
    CreatedAt time.Time `gorm:"not null;index"`
    UpdatedAt time.Time `gorm:"not null"`
    
    // Sequence
    DailySequence int `gorm:"not null"`
    
    // Relations
    Tags []SuggestionTag `gorm:"foreignKey:SuggestionId;constraint:OnDelete:CASCADE"`
}

// SuggestionTag represents a tag on a suggestion
type SuggestionTag struct {
    Id           string `gorm:"primaryKey;type:TEXT"`
    SuggestionId string `gorm:"type:TEXT;not null;index"`
    Suggestion   *Suggestion `gorm:"foreignKey:SuggestionId"`
    Tag          string `gorm:"type:TEXT;not null;index"`
}
```

---

## Suggestion Generator Service

```go
package suggestions

import (
    "context"
    "encoding/json"
    "fmt"
    "time"
    
    "github.com/google/uuid"
)

// GeneratorConfig configures suggestion generation
type GeneratorConfig struct {
    MinSuggestions      int     // Minimum per task (default: 1)
    MaxSuggestions      int     // Maximum per task (default: 10)
    SuggestionsPerTask  int     // Target per small task (default: 2)
    SuggestionsPerBigTask int   // Target per complex task (default: 6)
    TaskSizeThreshold   int     // Words to consider "big" (default: 500)
}

// DefaultGeneratorConfig returns sensible defaults
func DefaultGeneratorConfig() GeneratorConfig {
    return GeneratorConfig{
        MinSuggestions:      1,
        MaxSuggestions:      10,
        SuggestionsPerTask:  2,
        SuggestionsPerBigTask: 6,
        TaskSizeThreshold:   500,
    }
}

// SuggestionGenerator creates suggestions from AI interactions
type SuggestionGenerator struct {
    config        GeneratorConfig
    aiService     AIService
    suggestionRepo SuggestionRepository
    fileService   FileService
    pathMgr       PathManager
    eventBus      EventBus
}

// GeneratePrompt is the system prompt for suggestion generation
const generatePrompt = `You are an expert software architect reviewing completed work.
Based on the task that was just completed, generate improvement suggestions.

Guidelines:
- Each suggestion should be actionable and specific
- Focus on quality, maintainability, performance, and security
- Consider edge cases and error handling
- Think about documentation and testing
- Suggest architectural improvements when relevant
- Be practical - suggestions should be implementable

Output Format (JSON array):
[
  {
    "title": "Short descriptive title",
    "summary": "One paragraph explaining the suggestion",
    "priority": "low|medium|high|critical",
    "recommendedActions": ["Action 1", "Action 2", ...],
    "affectedFiles": ["path/to/file1", "path/to/file2"],
    "estimatedHours": 2.5,
    "complexity": "low|medium|high",
    "tags": ["tag1", "tag2"]
  }
]`

// SuggestionInput represents a suggestion request
type SuggestionInput struct {
    Title    string `json:"title"`
    Summary  string `json:"summary"`
    Priority string `json:"priority"`
    RecommendedActions []string `json:"recommendedActions"`
    AffectedFiles []string `json:"affectedFiles"`
    EstimatedHours float64 `json:"estimatedHours"`
    Complexity string `json:"complexity"`
    Tags []string `json:"tags"`
}

// GenerateFromTask creates suggestions after task completion
func (g *SuggestionGenerator) GenerateFromTask(
    ctx context.Context,
    projectId string,
    task TaskInfo,
) ([]Suggestion, error) {
    // Determine target count based on task size
    targetCount := g.config.SuggestionsPerTask
    if len(task.Description) > g.config.TaskSizeThreshold {
        targetCount = g.config.SuggestionsPerBigTask
    }
    
    // Build context for AI
    contextPrompt := fmt.Sprintf(`Task Completed:
Title: %s
Description: %s
Files Modified: %v
Result: %s

Generate %d improvement suggestions based on this completed work.`,
        task.Title,
        task.Description,
        task.ModifiedFiles,
        task.Result,
        targetCount,
    )
    
    // Call AI
    response, err := g.aiService.GenerateStructured(ctx, GenerateRequest{
        SystemPrompt: generatePrompt,
        UserPrompt:   contextPrompt,
        OutputSchema: SuggestionArraySchema,
    })
    if err != nil {
        return nil, fmt.Errorf("AI generation failed: %w", err)
    }
    
    // Parse response
    var inputs []SuggestionInput
    if err := json.Unmarshal([]byte(response.Json), &inputs); err != nil {
        return nil, fmt.Errorf("parse suggestions: %w", err)
    }
    
    // Create suggestions
    var suggestions []Suggestion
    for i, input := range inputs {
        suggestion, err := g.createSuggestion(ctx, projectId, task, input, i+1)
        if err != nil {
            continue // Log and skip failed ones
        }
        suggestions = append(suggestions, *suggestion)
    }
    
    return suggestions, nil
}

// createSuggestion creates a single suggestion
func (g *SuggestionGenerator) createSuggestion(
    ctx context.Context,
    projectId string,
    task TaskInfo,
    input SuggestionInput,
    index int,
) (*Suggestion, error) {
    // Get daily sequence
    today := time.Now().Format("2006-01-02")
    sequence := g.suggestionRepo.GetNextDailySequence(ctx, projectId, today)
    
    // Build file path
    slug := slugify(input.Title, 50)
    fileName := fmt.Sprintf("%s_%03d_%s.md", today, sequence, slug)
    relativePath := fmt.Sprintf("data/projects/%s/suggestions/pending/%s",
        g.getProjectName(ctx, projectId), fileName)
    
    // Create suggestion record
    suggestion := &Suggestion{
        Id:           uuid.New().String(),
        ProjectId:    projectId,
        FilePath:     relativePath,
        FileName:     fileName,
        Title:        input.Title,
        Summary:      input.Summary,
        Priority:     input.Priority,
        SourceType:   "task",
        SourceId:     task.Id,
        SourceTitle:  &task.Title,
        AffectedFiles: toJSON(input.AffectedFiles),
        EstimatedHours: &input.EstimatedHours,
        Complexity:   &input.Complexity,
        Status:       "pending",
        DailySequence: sequence,
        CreatedAt:    time.Now(),
        UpdatedAt:    time.Now(),
    }
    
    // Save to database
    if err := g.suggestionRepo.Create(ctx, suggestion); err != nil {
        return nil, err
    }
    
    // Create tags
    for _, tag := range input.Tags {
        g.suggestionRepo.AddTag(ctx, suggestion.Id, tag)
    }
    
    // Write suggestion file
    content := g.formatSuggestionFile(suggestion, input)
    if err := g.fileService.WriteFile(ctx, relativePath, content); err != nil {
        return nil, err
    }
    
    g.eventBus.Publish("suggestion:created", map[string]interface{}{
        "suggestionId": suggestion.Id,
        "projectId":    projectId,
        "title":        input.Title,
        "priority":     input.Priority,
    })
    
    return suggestion, nil
}

// formatSuggestionFile creates the markdown content
func (g *SuggestionGenerator) formatSuggestionFile(
    suggestion *Suggestion,
    input SuggestionInput,
) string {
    return fmt.Sprintf(`# Suggestion: %s

**ID:** %s  
**Status:** %s  
**Priority:** %s  
**Created:** %s  
**Updated:** %s  

---

## Source Reference

| Field | Value |
|-------|-------|
| Type | %s |
| Source ID | %s |
| Source Title | %s |

---

## Suggestion Details

### Summary

%s

### Recommended Actions

%s

### Affected Files

%s

### Estimated Effort

- **Time:** %.1f hours
- **Complexity:** %s

---

## Resolution

### Resolution Status

- [ ] Not started
- [ ] In progress
- [ ] Completed
- [ ] Deferred
- [ ] Rejected

### Resolution Notes

_To be filled when resolved_
`,
        suggestion.Title,
        suggestion.Id,
        suggestion.Status,
        suggestion.Priority,
        suggestion.CreatedAt.Format(time.RFC3339),
        suggestion.UpdatedAt.Format(time.RFC3339),
        suggestion.SourceType,
        suggestion.SourceId,
        *suggestion.SourceTitle,
        input.Summary,
        formatActions(input.RecommendedActions),
        formatFiles(input.AffectedFiles),
        input.EstimatedHours,
        input.Complexity,
    )
}
```

---

## Suggestion Resolution Service

```go
package suggestions

import (
    "context"
    "fmt"
    "os"
    "path/filepath"
    "time"
)

// ResolutionStatus represents suggestion resolution states
type ResolutionStatus string

const (
    StatusPending    ResolutionStatus = "pending"
    StatusInProgress ResolutionStatus = "in_progress"
    StatusCompleted  ResolutionStatus = "completed"
    StatusDeferred   ResolutionStatus = "deferred"
    StatusRejected   ResolutionStatus = "rejected"
)

// ResolutionService handles suggestion lifecycle
type ResolutionService struct {
    suggestionRepo SuggestionRepository
    fileService    FileService
    pathMgr        PathManager
    eventBus       EventBus
}

// ResolveRequest contains resolution details
type ResolveRequest struct {
    SuggestionId    string
    Status          ResolutionStatus
    Notes           string
    ResolvedById    string
}

// ResolveSuggestion marks a suggestion as resolved
func (s *ResolutionService) ResolveSuggestion(
    ctx context.Context,
    req ResolveRequest,
) error {
    // Get suggestion
    suggestion, err := s.suggestionRepo.GetById(ctx, req.SuggestionId)
    if err != nil {
        return fmt.Errorf("suggestion not found: %w", err)
    }
    
    // Validate transition
    if suggestion.Status == string(StatusCompleted) {
        return fmt.Errorf("suggestion already completed")
    }
    
    // Update status
    now := time.Now()
    suggestion.Status = string(req.Status)
    suggestion.ResolvedAt = &now
    suggestion.ResolvedById = &req.ResolvedById
    suggestion.ResolutionNotes = &req.Notes
    suggestion.UpdatedAt = now
    
    if err := s.suggestionRepo.Update(ctx, suggestion); err != nil {
        return err
    }
    
    // Move file if completed/rejected
    if req.Status == StatusCompleted || req.Status == StatusRejected {
        if err := s.moveSuggestionFile(ctx, suggestion); err != nil {
            // Log but don't fail - DB is source of truth
            fmt.Printf("Warning: failed to move suggestion file: %v\n", err)
        }
    }
    
    // Update markdown file content
    if err := s.updateSuggestionFile(ctx, suggestion, req); err != nil {
        fmt.Printf("Warning: failed to update suggestion file: %v\n", err)
    }
    
    s.eventBus.Publish("suggestion:resolved", map[string]interface{}{
        "suggestionId": suggestion.Id,
        "status":       req.Status,
        "resolvedBy":   req.ResolvedById,
    })
    
    return nil
}

// moveSuggestionFile moves from pending to resolved folder
func (s *ResolutionService) moveSuggestionFile(
    ctx context.Context,
    suggestion *Suggestion,
) error {
    oldPath := s.pathMgr.GetAbsolutePath(suggestion.FilePath)
    
    // Build new path (replace /pending/ with /resolved/)
    newRelativePath := filepath.Join(
        filepath.Dir(filepath.Dir(suggestion.FilePath)),
        "resolved",
        suggestion.FileName,
    )
    newPath := s.pathMgr.GetAbsolutePath(newRelativePath)
    
    // Ensure resolved directory exists
    if err := os.MkdirAll(filepath.Dir(newPath), 0755); err != nil {
        return err
    }
    
    // Move file
    if err := os.Rename(oldPath, newPath); err != nil {
        return err
    }
    
    // Update file path in DB
    suggestion.FilePath = newRelativePath
    return s.suggestionRepo.Update(ctx, suggestion)
}

// updateSuggestionFile updates the markdown with resolution info
func (s *ResolutionService) updateSuggestionFile(
    ctx context.Context,
    suggestion *Suggestion,
    req ResolveRequest,
) error {
    // Read current content
    content, err := s.fileService.ReadFile(ctx, suggestion.FilePath)
    if err != nil {
        return err
    }
    
    // Update resolution section
    resolutionSection := fmt.Sprintf(`## Resolution

### Resolution Status

%s

### Resolution Notes

%s

### Resolution Date

%s

### Resolved By

%s`,
        formatStatus(req.Status),
        req.Notes,
        time.Now().Format(time.RFC3339),
        req.ResolvedById,
    )
    
    // Replace resolution section in content
    updatedContent := replaceSection(content, "## Resolution", resolutionSection)
    
    return s.fileService.WriteFile(ctx, suggestion.FilePath, updatedContent)
}

// formatStatus formats status as checkbox list
func formatStatus(status ResolutionStatus) string {
    statuses := []struct {
        status ResolutionStatus
        label  string
    }{
        {StatusPending, "Not started"},
        {StatusInProgress, "In progress"},
        {StatusCompleted, "Completed"},
        {StatusDeferred, "Deferred"},
        {StatusRejected, "Rejected"},
    }
    
    var result string
    for _, s := range statuses {
        check := " "
        if s.status == status {
            check = "x"
        }
        result += fmt.Sprintf("- [%s] %s\n", check, s.label)
    }
    return result
}
```

---

## Suggestion Query Service

```go
package suggestions

import (
    "context"
)

// QueryOptions for filtering suggestions
type QueryOptions struct {
    ProjectId    string
    Status       []string    // Filter by status
    Priority     []string    // Filter by priority
    SourceType   string      // Filter by source type
    SourceId     string      // Filter by source
    Tags         []string    // Filter by tags
    Search       string      // Full-text search
    SortBy       string      // Field to sort by
    SortOrder    string      // asc or desc
    Limit        int
    Offset       int
}

// SuggestionStats provides aggregate statistics
type SuggestionStats struct {
    TotalPending    int
    TotalInProgress int
    TotalCompleted  int
    TotalDeferred   int
    TotalRejected   int
    ByPriority      map[string]int
    ByTag           map[string]int
    AvgResolutionHours float64
}

// QueryService handles suggestion queries
type QueryService struct {
    suggestionRepo SuggestionRepository
}

// ListSuggestions returns filtered suggestions
func (q *QueryService) ListSuggestions(
    ctx context.Context,
    opts QueryOptions,
) ([]Suggestion, int, error) {
    return q.suggestionRepo.Query(ctx, opts)
}

// GetStats returns aggregate statistics
func (q *QueryService) GetStats(
    ctx context.Context,
    projectId string,
) (*SuggestionStats, error) {
    return q.suggestionRepo.GetStats(ctx, projectId)
}

// GetBySource returns suggestions for a specific task/chat
func (q *QueryService) GetBySource(
    ctx context.Context,
    sourceType string,
    sourceId string,
) ([]Suggestion, error) {
    return q.suggestionRepo.Query(ctx, QueryOptions{
        SourceType: sourceType,
        SourceId:   sourceId,
    })
}
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/projects/{projectId}/suggestions` | List suggestions with filters |
| GET | `/api/v1/projects/{projectId}/suggestions/stats` | Get suggestion statistics |
| GET | `/api/v1/suggestions/{id}` | Get suggestion details |
| PUT | `/api/v1/suggestions/{id}` | Update suggestion |
| POST | `/api/v1/suggestions/{id}/resolve` | Resolve suggestion |
| DELETE | `/api/v1/suggestions/{id}` | Delete suggestion |
| GET | `/api/v1/tasks/{taskId}/suggestions` | Get suggestions for task |
| GET | `/api/v1/chats/{chatId}/suggestions` | Get suggestions for chat |

### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string[] | Filter by status (pending, completed, etc.) |
| `priority` | string[] | Filter by priority (low, medium, high, critical) |
| `tags` | string[] | Filter by tags |
| `sourceType` | string | Filter by source type (task, chat, validation) |
| `search` | string | Full-text search |
| `sortBy` | string | Sort field (createdAt, priority, etc.) |
| `sortOrder` | string | asc or desc |
| `limit` | int | Page size (default: 20) |
| `offset` | int | Page offset |

---

## WebSocket Events

| Event | Direction | Payload |
|-------|-----------|---------|
| `suggestion:created` | Server→Client | `{suggestionId, projectId, title, priority}` |
| `suggestion:updated` | Server→Client | `{suggestionId, status}` |
| `suggestion:resolved` | Server→Client | `{suggestionId, status, resolvedBy}` |
| `suggestion:deleted` | Server→Client | `{suggestionId}` |

---

## Frontend Components

### Suggestions Panel

```typescript
interface SuggestionsPanelProps {
  projectId: string;
  sourceType?: 'task' | 'chat' | 'validation' | 'build';
  sourceId?: string;
  showStats?: boolean;
}

// Component structure
const SuggestionsPanel = ({ projectId, sourceType, sourceId }) => {
  return (
    <div className="suggestions-panel">
      <SuggestionsHeader stats={stats} />
      <SuggestionsFilters 
        onFilterChange={handleFilterChange}
        availableTags={tags}
      />
      <SuggestionsList 
        suggestions={suggestions}
        onResolve={handleResolve}
        onSelect={handleSelect}
      />
      <SuggestionDetail 
        suggestion={selectedSuggestion}
        onUpdate={handleUpdate}
      />
    </div>
  );
};
```

### Suggestion Card

```typescript
interface SuggestionCardProps {
  suggestion: Suggestion;
  onResolve: (id: string, status: ResolutionStatus) => void;
  onClick: () => void;
}

// Visual indicators
const priorityColors = {
  critical: 'bg-red-500',
  high: 'bg-orange-500',
  medium: 'bg-yellow-500',
  low: 'bg-gray-500',
};
```

---

## Configuration Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `suggestions.generator.minPerTask` | int | 1 | Minimum suggestions per task |
| `suggestions.generator.maxPerTask` | int | 10 | Maximum suggestions per task |
| `suggestions.generator.targetSmall` | int | 2 | Target for small tasks |
| `suggestions.generator.targetLarge` | int | 6 | Target for large tasks |
| `suggestions.storage.basePath` | string | "suggestions" | Folder name |
| `suggestions.cleanup.resolvedRetentionDays` | int | 90 | Days to keep resolved |
| `suggestions.ai.modelCategory` | string | "thinking" | Model for generation |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 13000 | ERR_SUGGESTION_NOT_FOUND | Suggestion not found |
| 13001 | ERR_SUGGESTION_ALREADY_RESOLVED | Already resolved |
| 13002 | ERR_SUGGESTION_INVALID_STATUS | Invalid status transition |
| 13003 | ERR_SUGGESTION_FILE_WRITE_FAILED | Failed to write file |
| 13004 | ERR_SUGGESTION_FILE_MOVE_FAILED | Failed to move file |
| 13005 | ERR_SUGGESTION_GENERATION_FAILED | AI generation failed |

---

## Related Specifications

- [Instruction System](../06-ai-integration/03-instruction-system.md)
- [Code Generation System](./00-overview.md)
- [Project Editor UI](./15-project-editor-ui.md)
- [AI Integration](../06-ai-integration/00-overview.md)
- [Error Code Registry](../../06-error-management/error-code-registry.md)
