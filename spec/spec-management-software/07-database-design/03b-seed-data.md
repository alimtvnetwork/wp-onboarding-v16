# Seed Data & Test Fixtures

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Seed data scripts for initializing development/test environments with sample projects, prompt presets, and fixtures. All seeding uses GORM with idempotent operations.

---

## Seeding Strategy

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          SEEDING EXECUTION ORDER                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Phase 1: System Configuration                                               │
│  ├── Config (system defaults)                                                │
│  └── LLMServer (default server configs)                                      │
│                                                                              │
│  Phase 2: Prompt Presets (no FK dependencies)                                │
│  ├── PromptPreset (base presets by category)                                 │
│  └── PromptPresetVersion (initial versions)                                  │
│                                                                              │
│  Phase 3: Sample Projects (for dev/test only)                                │
│  ├── Project (sample projects)                                               │
│  ├── File (sample spec files)                                                │
│  └── Snapshot (sample snapshots)                                             │
│                                                                              │
│  Phase 4: RAG Fixtures (for dev/test only)                                   │
│  ├── FileRegistry                                                            │
│  ├── ChunkRegistry                                                           │
│  └── ArtifactRegistry                                                        │
│                                                                              │
│  Phase 5: Test Fixtures (test environment only)                              │
│  ├── InstructionRun (sample runs)                                            │
│  ├── ChatSession (sample chats)                                              │
│  └── InconsistencyReport (sample reports)                                    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: System Configuration

### 1.1 Config Defaults

```go
// internal/seed/config.go
package seed

import (
    "gorm.io/gorm"
    "gorm.io/gorm/clause"
)

type ConfigSeed struct {
    Key         string
    Value       string
    ValueType   string
    Description string
    IsSecret    bool
    Source      string
}

var DefaultConfigs = []ConfigSeed{
    // LLM Server Settings
    {"llama.server.path", "/usr/local/bin/llama-server", "string", "Path to llama.cpp server binary", false, "default"},
    {"llama.server.host", "127.0.0.1", "string", "Default LLM server host", false, "default"},
    {"llama.server.port", "8080", "int", "Default LLM server port", false, "default"},
    {"llama.server.contextSize", "8192", "int", "Default context window size", false, "default"},
    {"llama.server.gpuLayers", "35", "int", "GPU layers to offload", false, "default"},
    
    // Model Paths by Category
    {"llama.model.thinking", "models/deepseek-r1-distill-qwen-32b-Q4_K_M.gguf", "string", "Reasoning model path", false, "default"},
    {"llama.model.writing", "models/qwen2.5-14b-instruct-Q5_K_M.gguf", "string", "Writing model path", false, "default"},
    {"llama.model.voice", "models/whisper-large-v3-turbo.bin", "string", "Voice transcription model", false, "default"},
    {"llama.model.coding", "models/qwen2.5-coder-14b-instruct-Q5_K_M.gguf", "string", "Coding assistant model", false, "default"},
    {"llama.model.embedding", "models/bge-m3-Q8_0.gguf", "string", "Embedding model path", false, "default"},
    
    // RAG Settings
    {"rag.chunk.maxTokens", "512", "int", "Maximum tokens per chunk", false, "default"},
    {"rag.chunk.overlap", "64", "int", "Token overlap between chunks", false, "default"},
    {"rag.retrieval.topK", "10", "int", "Top-K results for retrieval", false, "default"},
    {"rag.retrieval.minScore", "0.7", "string", "Minimum relevance score", false, "default"},
    {"rag.cache.ttlSeconds", "300", "int", "Retrieval cache TTL", false, "default"},
    
    // Task Execution
    {"task.maxParallelism", "4", "int", "Maximum parallel task workers", false, "default"},
    {"task.timeoutSeconds", "300", "int", "Task execution timeout", false, "default"},
    
    // File System
    {"fs.maxFileSizeMB", "10", "int", "Maximum file size in MB", false, "default"},
    {"fs.allowedExtensions", ".md,.yaml,.json,.txt", "string", "Allowed file extensions", false, "default"},
    
    // Git Settings
    {"git.autoCommit", "true", "bool", "Auto-commit on file save", false, "default"},
    {"git.commitMessagePrefix", "[spec]", "string", "Prefix for auto-commit messages", false, "default"},
    
    // Health Check
    {"health.intervalSeconds", "30", "int", "Health check interval", false, "default"},
    {"health.timeoutSeconds", "5", "int", "Health check timeout", false, "default"},
    
    // UI Settings
    {"ui.theme", "system", "string", "Default theme (light/dark/system)", false, "default"},
    {"ui.editorFontSize", "14", "int", "Editor font size", false, "default"},
    {"ui.sidebarWidth", "280", "int", "Sidebar width in pixels", false, "default"},
}

func SeedConfigs(db *gorm.DB) error {
    for _, cfg := range DefaultConfigs {
        config := Config{
            Id:          generateUUID(),
            Key:         cfg.Key,
            Value:       cfg.Value,
            ValueType:   cfg.ValueType,
            Description: cfg.Description,
            IsSecret:    cfg.IsSecret,
            Source:      cfg.Source,
        }
        
        // Upsert: insert or ignore if exists
        err := db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "Key"}},
            DoNothing: true,
        }).Create(&config).Error
        
        if err != nil {
            return fmt.Errorf("failed to seed config %s: %w", cfg.Key, err)
        }
    }
    
    return nil
}
```

### 1.2 LLM Server Defaults

```go
// internal/seed/llm_servers.go
package seed

var DefaultLLMServers = []LLMServer{
    {
        Id:          "default-ollama",
        Name:        "Ollama (Default)",
        BackendType: "ollama",
        Host:        "127.0.0.1",
        Port:        11434,
        Status:      "stopped",
        Configuration: `{
            "keepAlive": "5m",
            "numCtx": 8192
        }`,
    },
    {
        Id:          "default-llamacpp",
        Name:        "llama.cpp Server",
        BackendType: "llamacpp",
        Host:        "127.0.0.1",
        Port:        8080,
        Status:      "stopped",
        Configuration: `{
            "contextSize": 8192,
            "gpuLayers": 35,
            "threads": 8,
            "batchSize": 512
        }`,
    },
    {
        Id:          "default-llamaswap",
        Name:        "llama-swap Proxy",
        BackendType: "llamaswap",
        Host:        "127.0.0.1",
        Port:        8081,
        Status:      "stopped",
        Configuration: `{
            "configPath": "config/llama-swap.yaml",
            "healthCheckInterval": 30
        }`,
    },
}

func SeedLLMServers(db *gorm.DB) error {
    for _, server := range DefaultLLMServers {
        server.CreatedAt = time.Now()
        server.UpdatedAt = time.Now()
        
        err := db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "Id"}},
            DoNothing: true,
        }).Create(&server).Error
        
        if err != nil {
            return fmt.Errorf("failed to seed LLM server %s: %w", server.Name, err)
        }
    }
    
    return nil
}
```

---

## Phase 2: Prompt Presets

### 2.1 Prompt Preset Structure

Presets are loaded from filesystem and seeded to database:

```
Prompts/
├── idea/
│   ├── brainstorm.md
│   ├── quick-capture.md
│   └── voice-note.md
├── feature/
│   ├── full-feature.md
│   ├── enhancement.md
│   └── bugfix.md
├── task/
│   ├── implementation.md
│   ├── refactor.md
│   └── documentation.md
├── codingGuideline/
│   ├── backend-go.md
│   ├── frontend-react.md
│   └── database-sql.md
└── instruction/
    ├── spec-generation.md
    ├── api-design.md
    └── component-design.md
```

### 2.2 Preset File Format

```markdown
---
name: Brainstorm Mode
description: Free-form idea capture with minimal structure
isDefault: true
version: 1
---

You are an AI assistant helping capture and organize ideas for software specifications.

## Guidelines

1. Accept ideas in any format - bullet points, paragraphs, or stream of consciousness
2. Identify key themes and concepts
3. Note any technical requirements mentioned
4. Flag potential dependencies or blockers
5. Suggest related areas to explore

## Output Format

Produce a structured idea document with:
- **Title**: Concise summary
- **Core Concept**: 2-3 sentence description
- **Key Points**: Bulleted list of main ideas
- **Technical Notes**: Any implementation hints
- **Questions**: Areas needing clarification
- **Related Topics**: Connections to explore

Keep the original voice and intent while adding structure.
```

### 2.3 Preset Seeding Logic

```go
// internal/seed/prompt_presets.go
package seed

import (
    "os"
    "path/filepath"
    "strings"
    
    "gopkg.in/yaml.v3"
)

type PresetFrontmatter struct {
    Name        string `yaml:"name"`
    Description string `yaml:"description"`
    IsDefault   bool   `yaml:"isDefault"`
    Version     int    `yaml:"version"`
}

func SeedPromptPresets(db *gorm.DB, promptsDir string) error {
    categories := []string{"idea", "feature", "task", "codingGuideline", "instruction"}
    
    for _, category := range categories {
        categoryPath := filepath.Join(promptsDir, category)
        
        entries, err := os.ReadDir(categoryPath)
        if err != nil {
            if os.IsNotExist(err) {
                continue // Skip missing categories
            }
            return fmt.Errorf("failed to read category %s: %w", category, err)
        }
        
        for _, entry := range entries {
            if !strings.HasSuffix(entry.Name(), ".md") {
                continue
            }
            
            filePath := filepath.Join(categoryPath, entry.Name())
            if err := seedPresetFromFile(db, category, filePath); err != nil {
                return err
            }
        }
    }
    
    return nil
}

func seedPresetFromFile(db *gorm.DB, category, filePath string) error {
    content, err := os.ReadFile(filePath)
    if err != nil {
        return fmt.Errorf("failed to read preset file %s: %w", filePath, err)
    }
    
    // Parse frontmatter
    parts := strings.SplitN(string(content), "---", 3)
    if len(parts) < 3 {
        return fmt.Errorf("invalid preset format in %s: missing frontmatter", filePath)
    }
    
    var frontmatter PresetFrontmatter
    if err := yaml.Unmarshal([]byte(parts[1]), &frontmatter); err != nil {
        return fmt.Errorf("failed to parse frontmatter in %s: %w", filePath, err)
    }
    
    promptContent := strings.TrimSpace(parts[2])
    
    // Create preset
    preset := PromptPreset{
        Id:          generateUUID(),
        Category:    category,
        Name:        frontmatter.Name,
        Description: frontmatter.Description,
        BasePrompt:  promptContent,
        IsSystem:    true, // Seeded presets are system presets
        Version:     frontmatter.Version,
        CreatedAt:   time.Now(),
        UpdatedAt:   time.Now(),
    }
    
    // Upsert by category + name
    err = db.Clauses(clause.OnConflict{
        Columns:   []clause.Column{{Name: "Category"}, {Name: "Name"}},
        DoUpdates: clause.AssignmentColumns([]string{"BasePrompt", "Description", "Version", "UpdatedAt"}),
    }).Create(&preset).Error
    
    if err != nil {
        return fmt.Errorf("failed to seed preset %s: %w", frontmatter.Name, err)
    }
    
    // Create version record
    version := PromptPresetVersion{
        Id:         generateUUID(),
        PresetId:   preset.Id,
        Version:    frontmatter.Version,
        BasePrompt: promptContent,
        ChangeNote: "Initial seeded version",
        CreatedAt:  time.Now(),
    }
    
    err = db.Clauses(clause.OnConflict{
        Columns:   []clause.Column{{Name: "PresetId"}, {Name: "Version"}},
        DoNothing: true,
    }).Create(&version).Error
    
    return err
}
```

### 2.4 Default Prompt Presets

#### Idea Category

```go
var IdeaPresets = []PromptPreset{
    {
        Category:    "idea",
        Name:        "Brainstorm Mode",
        Description: "Free-form idea capture with minimal structure",
        IsSystem:    true,
        BasePrompt: `You are an AI assistant helping capture and organize ideas for software specifications.

## Guidelines
1. Accept ideas in any format
2. Identify key themes and concepts
3. Note technical requirements
4. Flag potential dependencies
5. Suggest related areas

## Output Format
- **Title**: Concise summary
- **Core Concept**: 2-3 sentences
- **Key Points**: Bulleted list
- **Technical Notes**: Implementation hints
- **Questions**: Areas needing clarification
- **Related Topics**: Connections to explore`,
    },
    {
        Category:    "idea",
        Name:        "Quick Capture",
        Description: "Minimal processing for rapid idea logging",
        IsSystem:    true,
        BasePrompt: `Capture this idea with minimal processing.

## Tasks
1. Fix obvious typos and grammar
2. Add a one-line title
3. Preserve original wording
4. Add timestamp

## Format
# {Generated Title}
*Captured: {timestamp}*

{Original content with minimal edits}`,
    },
    {
        Category:    "idea",
        Name:        "Voice Note",
        Description: "Optimized for transcribed voice input",
        IsSystem:    true,
        BasePrompt: `Process this voice transcription into a structured idea.

## Guidelines
1. Fix transcription errors (homophones, run-ons)
2. Add punctuation and formatting
3. Preserve the speaker's intent and tone
4. Identify action items mentioned
5. Note any questions raised

## Output
- **Summary**: One paragraph
- **Key Points**: Bulleted
- **Action Items**: If any
- **Open Questions**: If any`,
    },
}
```

#### Feature Category

```go
var FeaturePresets = []PromptPreset{
    {
        Category:    "feature",
        Name:        "Full Feature Spec",
        Description: "Comprehensive feature specification with all sections",
        IsSystem:    true,
        BasePrompt: `Generate a complete feature specification.

## Required Sections

### 1. Overview
- Feature name and version
- One-paragraph description
- Business justification

### 2. User Stories
- As a [role], I want [goal], so that [benefit]
- Include 3-5 user stories minimum

### 3. Functional Requirements
- Numbered requirements (FR-001, FR-002...)
- Each with description and acceptance criteria

### 4. Non-Functional Requirements
- Performance, security, accessibility
- Use NFR-001 numbering

### 5. UI/UX Considerations
- Key screens or components
- User flow description

### 6. Technical Design
- Architecture considerations
- API endpoints needed
- Database changes

### 7. Dependencies
- External systems
- Other features required

### 8. Acceptance Criteria
- Testable conditions for completion

### 9. Out of Scope
- Explicitly excluded items`,
    },
    {
        Category:    "feature",
        Name:        "Enhancement",
        Description: "Improvement to existing functionality",
        IsSystem:    true,
        BasePrompt: `Document an enhancement to existing functionality.

## Sections

### Current Behavior
- How it works now
- Limitations or issues

### Proposed Enhancement
- What changes
- Expected benefits

### Impact Analysis
- Affected components
- Backward compatibility
- Migration needs

### Implementation Notes
- Suggested approach
- Estimated effort`,
    },
    {
        Category:    "feature",
        Name:        "Bug Fix Spec",
        Description: "Structured bug report with fix specification",
        IsSystem:    true,
        BasePrompt: `Document a bug and its fix specification.

## Bug Report
- **ID**: BUG-{number}
- **Severity**: Critical/High/Medium/Low
- **Component**: Affected area

### Steps to Reproduce
1. Step one
2. Step two
3. ...

### Expected Behavior
What should happen

### Actual Behavior
What happens instead

### Root Cause Analysis
Technical explanation

### Fix Specification
- Proposed solution
- Files to modify
- Test cases to add`,
    },
}
```

#### Task Category

```go
var TaskPresets = []PromptPreset{
    {
        Category:    "task",
        Name:        "Implementation Task",
        Description: "Detailed implementation instructions",
        IsSystem:    true,
        BasePrompt: `Generate implementation task instructions.

## Task Structure

### Objective
Clear statement of what to build

### Prerequisites
- Required knowledge
- Dependencies to install
- Prior tasks to complete

### Implementation Steps
1. Detailed step with code hints
2. Next step...
   - Sub-steps if needed

### Validation
- How to verify completion
- Test commands to run

### Deliverables
- Files to create/modify
- Expected output`,
    },
    {
        Category:    "task",
        Name:        "Refactor Task",
        Description: "Code refactoring instructions",
        IsSystem:    true,
        BasePrompt: `Generate refactoring task instructions.

## Refactor Specification

### Current State
- Code location
- Current structure
- Problems to address

### Target State
- Desired structure
- Design patterns to apply
- Performance improvements

### Refactoring Steps
1. Step with safety checks
2. Incremental changes...

### Testing Strategy
- Tests to run before
- Tests to add
- Regression checks`,
    },
    {
        Category:    "task",
        Name:        "Documentation Task",
        Description: "Documentation writing instructions",
        IsSystem:    true,
        BasePrompt: `Generate documentation task instructions.

## Documentation Scope

### Target Audience
Who will read this

### Content Requirements
- Topics to cover
- Code examples needed
- Diagrams required

### Format
- Document structure
- Style guidelines
- Naming conventions

### Review Checklist
- Technical accuracy
- Completeness
- Clarity`,
    },
}
```

#### Coding Guideline Category

```go
var CodingGuidelinePresets = []PromptPreset{
    {
        Category:    "codingGuideline",
        Name:        "Backend Go",
        Description: "Go backend coding standards",
        IsSystem:    true,
        BasePrompt: `Generate Go backend coding guidelines.

## Go Coding Standards

### Project Structure
- cmd/ for entry points
- internal/ for private packages
- pkg/ for public packages

### Naming Conventions
- PascalCase for exports
- camelCase for private
- Acronyms: URL, HTTP, ID

### Error Handling
- Always check errors
- Wrap with context: fmt.Errorf("operation: %w", err)
- Define domain errors

### GORM Guidelines
- Use migrations
- Define indexes
- Soft delete where appropriate

### Testing
- Table-driven tests
- Mock interfaces
- 80%+ coverage target`,
    },
    {
        Category:    "codingGuideline",
        Name:        "Frontend React",
        Description: "React/TypeScript frontend standards",
        IsSystem:    true,
        BasePrompt: `Generate React frontend coding guidelines.

## React Coding Standards

### Component Structure
- Functional components only
- Custom hooks for logic
- Props interface defined

### Naming
- PascalCase components
- camelCase functions
- kebab-case files optional

### State Management
- useState for local
- React Query for server
- Context sparingly

### Styling
- Tailwind utilities
- Semantic design tokens
- No inline styles

### Testing
- React Testing Library
- Test behavior, not implementation
- Mock API calls`,
    },
    {
        Category:    "codingGuideline",
        Name:        "Database SQL",
        Description: "Database design and query standards",
        IsSystem:    true,
        BasePrompt: `Generate database coding guidelines.

## Database Standards

### Schema Design
- PascalCase tables/columns
- UUID primary keys
- ISO8601 timestamps

### Indexing
- Index foreign keys
- Composite indexes for common queries
- Partial indexes where beneficial

### Query Patterns
- Use ORM constructs
- No raw SQL in application code
- Parameterized queries only

### Migrations
- One change per migration
- Reversible when possible
- Test rollback`,
    },
}
```

#### Instruction Category

```go
var InstructionPresets = []PromptPreset{
    {
        Category:    "instruction",
        Name:        "Spec Generation",
        Description: "Generate structured specification documents",
        IsSystem:    true,
        BasePrompt: `Generate a structured specification document.

## Specification Format

### Header
- Title, version, status, date
- Horizontal rule separator

### Overview
- Purpose and scope
- Key stakeholders

### Detailed Sections
- Use ## for major sections
- Use ### for subsections
- Number requirements

### Tables
- Use for structured data
- Column headers required

### Code Blocks
- Language-tagged
- Realistic examples

### Cross-References
- Relative paths
- Section anchors

### Footer
- Related documents
- Version history`,
    },
    {
        Category:    "instruction",
        Name:        "API Design",
        Description: "REST API endpoint specification",
        IsSystem:    true,
        BasePrompt: `Generate API endpoint specification.

## API Spec Format

### Endpoint Definition
- Method + Path
- Description
- Authentication required

### Request
- Headers
- Path parameters
- Query parameters
- Body schema (JSON)

### Response
- Success (2xx)
- Error responses (4xx, 5xx)
- Example payloads

### Error Codes
- Domain-specific codes
- HTTP status mapping

### Rate Limiting
- Limits per endpoint
- Headers returned`,
    },
    {
        Category:    "instruction",
        Name:        "Component Design",
        Description: "React component specification",
        IsSystem:    true,
        BasePrompt: `Generate React component specification.

## Component Spec

### Purpose
- What it does
- When to use

### Props Interface
\`\`\`typescript
interface ComponentProps {
  // Define all props
}
\`\`\`

### State
- Internal state needs
- External state dependencies

### Variants
- Visual variants
- Behavioral modes

### Accessibility
- ARIA attributes
- Keyboard navigation
- Screen reader support

### Usage Examples
\`\`\`tsx
<Component prop="value" />
\`\`\``,
    },
}
```

---

## Phase 3: Sample Projects

### 3.1 Development Sample Projects

```go
// internal/seed/sample_projects.go
package seed

var SampleProjects = []Project{
    {
        Id:            "sample-spec-mgmt",
        Name:          "Spec Management Software",
        Slug:          "spec-management-software",
        Description:   "Sample project for developing the spec management system itself",
        WorkDirectory: "/home/user/projects/spec-management",
        Status:        "active",
    },
    {
        Id:            "sample-ecommerce",
        Name:          "E-Commerce Platform",
        Slug:          "ecommerce-platform",
        Description:   "Sample e-commerce specification project",
        WorkDirectory: "/home/user/projects/ecommerce",
        Status:        "active",
    },
    {
        Id:            "sample-api",
        Name:          "API Gateway Service",
        Slug:          "api-gateway",
        Description:   "Sample API gateway specification",
        WorkDirectory: "/home/user/projects/api-gateway",
        Status:        "active",
    },
}

func SeedSampleProjects(db *gorm.DB) error {
    for _, project := range SampleProjects {
        project.CreatedAt = time.Now()
        project.UpdatedAt = time.Now()
        
        err := db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "Id"}},
            DoNothing: true,
        }).Create(&project).Error
        
        if err != nil {
            return fmt.Errorf("failed to seed project %s: %w", project.Name, err)
        }
        
        // Seed sample files for each project
        if err := seedProjectFiles(db, project.Id); err != nil {
            return err
        }
    }
    
    return nil
}
```

### 3.2 Sample Files

```go
var SampleFiles = map[string][]File{
    "sample-spec-mgmt": {
        {
            RelativePath: "03-project-overview/00-overview.md",
            FileName:     "00-overview.md",
            FileType:     "md",
        },
        {
            RelativePath: "05-features/01-authentication/00-overview.md",
            FileName:     "00-overview.md",
            FileType:     "md",
        },
        {
            RelativePath: "05-features/02-file-management/01-file-operations.md",
            FileName:     "01-file-operations.md",
            FileType:     "md",
        },
        {
            RelativePath: "07-database-design/00-overview.md",
            FileName:     "00-overview.md",
            FileType:     "md",
        },
        {
            RelativePath: "ideas/01-idea-voice-input.md",
            FileName:     "01-idea-voice-input.md",
            FileType:     "md",
        },
        {
            RelativePath: "instructions/01-instruction-setup-project.md",
            FileName:     "01-instruction-setup-project.md",
            FileType:     "md",
        },
    },
}

func seedProjectFiles(db *gorm.DB, projectId string) error {
    files, exists := SampleFiles[projectId]
    if !exists {
        return nil
    }
    
    for _, file := range files {
        file.Id = generateUUID()
        file.ProjectId = projectId
        file.ContentHash = generateHash(file.RelativePath) // Placeholder hash
        file.SizeBytes = 1024 // Placeholder size
        file.CreatedAt = time.Now()
        file.UpdatedAt = time.Now()
        
        err := db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "ProjectId"}, {Name: "RelativePath"}},
            DoNothing: true,
        }).Create(&file).Error
        
        if err != nil {
            return fmt.Errorf("failed to seed file %s: %w", file.RelativePath, err)
        }
    }
    
    return nil
}
```

### 3.3 Sample Snapshots

```go
var SampleSnapshots = map[string][]Snapshot{
    "sample-spec-mgmt": {
        {
            Name:          "V01-2026-01-15",
            Description:   "Initial project structure",
            GitCommitHash: "abc123def456",
            FileCount:     12,
        },
        {
            Name:          "V02-2026-01-22",
            Description:   "Added authentication spec",
            GitCommitHash: "def456ghi789",
            FileCount:     18,
        },
        {
            Name:          "V03-2026-01-28",
            Description:   "Database design complete",
            GitCommitHash: "ghi789jkl012",
            FileCount:     26,
        },
    },
}

func seedSnapshots(db *gorm.DB, projectId string) error {
    snapshots, exists := SampleSnapshots[projectId]
    if !exists {
        return nil
    }
    
    for i, snapshot := range snapshots {
        snapshot.Id = generateUUID()
        snapshot.ProjectId = projectId
        snapshot.CreatedAt = time.Now().AddDate(0, 0, -7*(len(snapshots)-i))
        
        err := db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "ProjectId"}, {Name: "Name"}},
            DoNothing: true,
        }).Create(&snapshot).Error
        
        if err != nil {
            return fmt.Errorf("failed to seed snapshot %s: %w", snapshot.Name, err)
        }
    }
    
    return nil
}
```

---

## Phase 4: RAG Fixtures

### 4.1 File Registry Entries

```go
func SeedFileRegistry(db *gorm.DB, projectId string) error {
    files, exists := SampleFiles[projectId]
    if !exists {
        return nil
    }
    
    for _, file := range files {
        registry := FileRegistry{
            Id:           generateUUID(),
            ProjectId:    projectId,
            RelativePath: file.RelativePath,
            FileType:     file.FileType,
            ContentHash:  generateHash(file.RelativePath),
            Status:       "indexed",
            ChunkCount:   3, // Sample chunk count
            LastIndexedAt: ptr(time.Now()),
            CreatedAt:    time.Now(),
            UpdatedAt:    time.Now(),
        }
        
        err := db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "ProjectId"}, {Name: "RelativePath"}},
            DoNothing: true,
        }).Create(&registry).Error
        
        if err != nil {
            return err
        }
        
        // Seed chunks for this file
        if err := seedChunksForFile(db, registry.Id); err != nil {
            return err
        }
    }
    
    return nil
}
```

### 4.2 Sample Chunks

```go
func seedChunksForFile(db *gorm.DB, fileRegistryId string) error {
    sampleChunks := []ChunkRegistry{
        {
            ChunkIndex:  0,
            Content:     "# Overview\n\nThis document describes the feature...",
            StartLine:   1,
            EndLine:     20,
            HeadingPath: "Overview",
            TokenCount:  128,
        },
        {
            ChunkIndex:  1,
            Content:     "## Requirements\n\n### Functional Requirements\n\n1. The system shall...",
            StartLine:   21,
            EndLine:     50,
            HeadingPath: "Overview > Requirements > Functional Requirements",
            TokenCount:  256,
        },
        {
            ChunkIndex:  2,
            Content:     "## Implementation\n\nThe implementation follows...",
            StartLine:   51,
            EndLine:     80,
            HeadingPath: "Overview > Implementation",
            TokenCount:  192,
        },
    }
    
    for _, chunk := range sampleChunks {
        chunk.Id = generateUUID()
        chunk.FileRegistryId = fileRegistryId
        chunk.ContentHash = generateHash(chunk.Content)
        chunk.CreatedAt = time.Now()
        chunk.UpdatedAt = time.Now()
        
        err := db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "FileRegistryId"}, {Name: "ChunkIndex"}},
            DoNothing: true,
        }).Create(&chunk).Error
        
        if err != nil {
            return err
        }
    }
    
    return nil
}
```

### 4.3 Artifact Registry

```go
var SampleArtifacts = []ArtifactRegistry{
    {
        ArtifactType: "idea",
        RelativePath: "ideas/01-idea-voice-input.md",
        Title:        "Voice Input Feature",
        Summary:      "Implement voice-to-text for spec creation",
        Tags:         `["voice", "ai", "input"]`,
        IsPinned:     true,
        PinPriority:  1,
    },
    {
        ArtifactType: "idea",
        RelativePath: "ideas/02-idea-rag-search.md",
        Title:        "RAG-Based Search",
        Summary:      "Add semantic search for spec retrieval",
        Tags:         `["rag", "search", "ai"]`,
        IsPinned:     true,
        PinPriority:  2,
    },
    {
        ArtifactType: "instruction",
        RelativePath: "instructions/01-instruction-setup-project.md",
        Title:        "Project Setup Instructions",
        Summary:      "Step-by-step project initialization",
        Tags:         `["setup", "onboarding"]`,
        IsPinned:     false,
        PinPriority:  0,
    },
}

func SeedArtifactRegistry(db *gorm.DB, projectId string) error {
    for _, artifact := range SampleArtifacts {
        artifact.Id = generateUUID()
        artifact.ProjectId = projectId
        artifact.CreatedAt = time.Now()
        artifact.UpdatedAt = time.Now()
        
        err := db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "ProjectId"}, {Name: "RelativePath"}},
            DoNothing: true,
        }).Create(&artifact).Error
        
        if err != nil {
            return err
        }
    }
    
    return nil
}
```

---

## Phase 5: Test Fixtures

### 5.1 Test Fixture Factory

```go
// internal/testutil/fixtures.go
package testutil

import (
    "fmt"
    "time"
    
    "github.com/google/uuid"
)

// Factory for generating test data
type FixtureFactory struct {
    db     *gorm.DB
    prefix string
}

func NewFixtureFactory(db *gorm.DB, prefix string) *FixtureFactory {
    return &FixtureFactory{db: db, prefix: prefix}
}

// Project fixtures
func (f *FixtureFactory) CreateProject(opts ...ProjectOption) *Project {
    project := &Project{
        Id:            uuid.New().String(),
        Name:          fmt.Sprintf("%s Test Project %d", f.prefix, time.Now().UnixNano()),
        Slug:          fmt.Sprintf("%s-test-%d", f.prefix, time.Now().UnixNano()),
        Description:   "Test project created by fixture factory",
        WorkDirectory: fmt.Sprintf("/tmp/test-projects/%s", uuid.New().String()),
        Status:        "active",
        CreatedAt:     time.Now(),
        UpdatedAt:     time.Now(),
    }
    
    for _, opt := range opts {
        opt(project)
    }
    
    f.db.Create(project)
    return project
}

type ProjectOption func(*Project)

func WithProjectName(name string) ProjectOption {
    return func(p *Project) { p.Name = name }
}

func WithProjectStatus(status string) ProjectOption {
    return func(p *Project) { p.Status = status }
}

// InstructionRun fixtures
func (f *FixtureFactory) CreateInstructionRun(projectId string, opts ...RunOption) *InstructionRun {
    run := &InstructionRun{
        Id:            uuid.New().String(),
        ProjectId:     projectId,
        InputType:     "text",
        InputCategory: "feature",
        RawInput:      "Test input for fixture",
        Status:        "pending",
        CreatedAt:     time.Now(),
    }
    
    for _, opt := range opts {
        opt(run)
    }
    
    f.db.Create(run)
    return run
}

type RunOption func(*InstructionRun)

func WithRunStatus(status string) RunOption {
    return func(r *InstructionRun) { r.Status = status }
}

func WithRunCategory(category string) RunOption {
    return func(r *InstructionRun) { r.InputCategory = category }
}

// ChatSession fixtures
func (f *FixtureFactory) CreateChatSession(projectId string) *ChatSession {
    session := &ChatSession{
        Id:           uuid.New().String(),
        ProjectId:    projectId,
        Title:        "Test Chat Session",
        MessageCount: 0,
        TotalTokens:  0,
        CreatedAt:    time.Now(),
    }
    
    f.db.Create(session)
    return session
}

func (f *FixtureFactory) CreateChatMessage(sessionId string, role string, content string) *ChatMessage {
    msg := &ChatMessage{
        Id:         uuid.New().String(),
        SessionId:  sessionId,
        Role:       role,
        Content:    content,
        TokenCount: len(content) / 4, // Rough estimate
        CreatedAt:  time.Now(),
    }
    
    f.db.Create(msg)
    
    // Update session counts
    f.db.Model(&ChatSession{}).Where("Id = ?", sessionId).
        UpdateColumns(map[string]interface{}{
            "MessageCount":  gorm.Expr("MessageCount + 1"),
            "TotalTokens":   gorm.Expr("TotalTokens + ?", msg.TokenCount),
            "LastMessageAt": time.Now(),
        })
    
    return msg
}

// InconsistencyReport fixtures
func (f *FixtureFactory) CreateInconsistencyReport(runId, projectId string, score float64) *InconsistencyReport {
    report := &InconsistencyReport{
        Id:               uuid.New().String(),
        RunId:            runId,
        ProjectId:        projectId,
        ConsistencyScore: score,
        TotalIssues:      int((100 - score) / 10),
        CriticalIssues:   int((100 - score) / 30),
        ReportContent:    "# Consistency Report\n\nSample report content...",
        Status:           "draft",
        CreatedAt:        time.Now(),
    }
    
    f.db.Create(report)
    return report
}

// Cleanup
func (f *FixtureFactory) Cleanup() {
    // Delete in reverse dependency order
    f.db.Exec("DELETE FROM ClarificationAnswer WHERE QuestionId IN (SELECT Id FROM ClarificationQuestion WHERE ReportId IN (SELECT Id FROM InconsistencyReport WHERE ProjectId IN (SELECT Id FROM Project WHERE Name LIKE ?)))", f.prefix+"%")
    f.db.Exec("DELETE FROM ClarificationQuestion WHERE ReportId IN (SELECT Id FROM InconsistencyReport WHERE ProjectId IN (SELECT Id FROM Project WHERE Name LIKE ?)))", f.prefix+"%")
    f.db.Exec("DELETE FROM InconsistencyReport WHERE ProjectId IN (SELECT Id FROM Project WHERE Name LIKE ?)", f.prefix+"%")
    f.db.Exec("DELETE FROM ChatMessage WHERE SessionId IN (SELECT Id FROM ChatSession WHERE ProjectId IN (SELECT Id FROM Project WHERE Name LIKE ?)))", f.prefix+"%")
    f.db.Exec("DELETE FROM ChatSession WHERE ProjectId IN (SELECT Id FROM Project WHERE Name LIKE ?)", f.prefix+"%")
    f.db.Exec("DELETE FROM InstructionRun WHERE ProjectId IN (SELECT Id FROM Project WHERE Name LIKE ?)", f.prefix+"%")
    f.db.Exec("DELETE FROM Project WHERE Name LIKE ?", f.prefix+"%")
}
```

### 5.2 Test Data Builders

```go
// internal/testutil/builders.go
package testutil

// Fluent builder for complex test scenarios
type ScenarioBuilder struct {
    factory *FixtureFactory
    project *Project
    runs    []*InstructionRun
    chats   []*ChatSession
}

func NewScenario(factory *FixtureFactory) *ScenarioBuilder {
    return &ScenarioBuilder{factory: factory}
}

func (s *ScenarioBuilder) WithProject(opts ...ProjectOption) *ScenarioBuilder {
    s.project = s.factory.CreateProject(opts...)
    return s
}

func (s *ScenarioBuilder) WithRun(opts ...RunOption) *ScenarioBuilder {
    if s.project == nil {
        s.WithProject()
    }
    run := s.factory.CreateInstructionRun(s.project.Id, opts...)
    s.runs = append(s.runs, run)
    return s
}

func (s *ScenarioBuilder) WithChat(messages ...string) *ScenarioBuilder {
    if s.project == nil {
        s.WithProject()
    }
    chat := s.factory.CreateChatSession(s.project.Id)
    for i, msg := range messages {
        role := "user"
        if i%2 == 1 {
            role = "assistant"
        }
        s.factory.CreateChatMessage(chat.Id, role, msg)
    }
    s.chats = append(s.chats, chat)
    return s
}

func (s *ScenarioBuilder) WithConsistencyReport(score float64) *ScenarioBuilder {
    if len(s.runs) == 0 {
        s.WithRun()
    }
    run := s.runs[len(s.runs)-1]
    s.factory.CreateInconsistencyReport(run.Id, s.project.Id, score)
    return s
}

func (s *ScenarioBuilder) Build() *Scenario {
    return &Scenario{
        Project: s.project,
        Runs:    s.runs,
        Chats:   s.chats,
    }
}

type Scenario struct {
    Project *Project
    Runs    []*InstructionRun
    Chats   []*ChatSession
}
```

### 5.3 Example Test Usage

```go
// internal/service/project_service_test.go
package service_test

import (
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestProjectService_Delete_WithActiveRuns(t *testing.T) {
    db := setupTestDB(t)
    factory := testutil.NewFixtureFactory(db, "TestDelete")
    defer factory.Cleanup()
    
    // Build scenario with active run
    scenario := testutil.NewScenario(factory).
        WithProject(testutil.WithProjectName("Delete Test Project")).
        WithRun(testutil.WithRunStatus("processing")).
        Build()
    
    svc := NewProjectService(db)
    
    // Attempt delete should fail
    err := svc.Delete(scenario.Project.Id)
    
    assert.Error(t, err)
    assert.Contains(t, err.Error(), "active instruction runs")
}

func TestProjectService_Delete_CascadesCorrectly(t *testing.T) {
    db := setupTestDB(t)
    factory := testutil.NewFixtureFactory(db, "TestCascade")
    defer factory.Cleanup()
    
    // Build complex scenario
    scenario := testutil.NewScenario(factory).
        WithProject().
        WithRun(testutil.WithRunStatus("completed")).
        WithChat("Hello", "Hi there!", "Help me with specs").
        WithConsistencyReport(85.5).
        Build()
    
    svc := NewProjectService(db)
    
    // Delete should succeed
    err := svc.Delete(scenario.Project.Id)
    require.NoError(t, err)
    
    // Verify cascades
    var runCount, chatCount int64
    db.Model(&InstructionRun{}).Where("ProjectId = ?", scenario.Project.Id).Count(&runCount)
    db.Model(&ChatSession{}).Where("ProjectId = ?", scenario.Project.Id).Count(&chatCount)
    
    assert.Equal(t, int64(0), runCount)
    assert.Equal(t, int64(0), chatCount)
}
```

---

## Seed Execution

### Main Seeder

```go
// internal/seed/seeder.go
package seed

type Seeder struct {
    db         *gorm.DB
    promptsDir string
    env        string // "development", "test", "production"
}

func NewSeeder(db *gorm.DB, promptsDir, env string) *Seeder {
    return &Seeder{
        db:         db,
        promptsDir: promptsDir,
        env:        env,
    }
}

func (s *Seeder) Run() error {
    log.Info().Str("env", s.env).Msg("Starting database seeding")
    
    // Phase 1: Always run - System configuration
    if err := SeedConfigs(s.db); err != nil {
        return fmt.Errorf("config seeding failed: %w", err)
    }
    log.Info().Msg("✓ Configs seeded")
    
    if err := SeedLLMServers(s.db); err != nil {
        return fmt.Errorf("LLM server seeding failed: %w", err)
    }
    log.Info().Msg("✓ LLM servers seeded")
    
    // Phase 2: Always run - Prompt presets
    if err := SeedPromptPresets(s.db, s.promptsDir); err != nil {
        return fmt.Errorf("prompt preset seeding failed: %w", err)
    }
    log.Info().Msg("✓ Prompt presets seeded")
    
    // Phase 3-5: Only in development/test
    if s.env != "production" {
        if err := SeedSampleProjects(s.db); err != nil {
            return fmt.Errorf("sample project seeding failed: %w", err)
        }
        log.Info().Msg("✓ Sample projects seeded")
        
        for _, projectId := range []string{"sample-spec-mgmt", "sample-ecommerce", "sample-api"} {
            if err := SeedFileRegistry(s.db, projectId); err != nil {
                return fmt.Errorf("file registry seeding failed: %w", err)
            }
            if err := SeedArtifactRegistry(s.db, projectId); err != nil {
                return fmt.Errorf("artifact registry seeding failed: %w", err)
            }
            if err := seedSnapshots(s.db, projectId); err != nil {
                return fmt.Errorf("snapshot seeding failed: %w", err)
            }
        }
        log.Info().Msg("✓ RAG fixtures seeded")
    }
    
    log.Info().Msg("Database seeding complete")
    return nil
}
```

### CLI Command

```go
// cmd/seed/main.go
package main

import (
    "flag"
    "log"
    "os"
)

func main() {
    env := flag.String("env", "development", "Environment (development, test, production)")
    promptsDir := flag.String("prompts", "Prompts", "Path to prompts directory")
    flag.Parse()
    
    db, err := database.Connect(os.Getenv("DATABASE_URL"))
    if err != nil {
        log.Fatalf("Failed to connect to database: %v", err)
    }
    
    seeder := seed.NewSeeder(db, *promptsDir, *env)
    if err := seeder.Run(); err != nil {
        log.Fatalf("Seeding failed: %v", err)
    }
    
    log.Println("Seeding completed successfully")
}
```

```bash
# Usage
go run cmd/seed/main.go --env=development --prompts=./Prompts

# Or via Makefile
make seed ENV=development
make seed-test ENV=test
```

---

## Related Documents

- [Unified Schema](./02-unified-schema.md)
- [Database Design Overview](./00-overview.md)
- [Prompt Preset System](../05-features/06-ai-integration/04-prompt-preset-system.md)
- [Implementation Order Guide](../08-roadmap-overview/02-implementation-order-guide.md)
