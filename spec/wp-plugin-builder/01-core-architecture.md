# Core Architecture

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

System design for WP Plugin Builder, a Golang CLI with dual-database architecture, RAG integration, and AI-powered code generation via AI Bridge.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [Database Schema](./04-database-schema.md)
- [AI Bridge Architecture](../ai-bridge/01-architecture.md)

---

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        WP PLUGIN BUILDER ARCHITECTURE                            │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│   ┌────────────────────────────────────────────────────────────────────────┐    │
│   │                          INPUT LAYER                                    │    │
│   │  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐             │    │
│   │  │   CLI Mode   │    │ Server Mode  │    │  Config Seed │             │    │
│   │  │  (Cobra)     │    │  (REST API)  │    │  (First Run) │             │    │
│   │  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘             │    │
│   │         └───────────────────┴───────────────────┘                      │    │
│   │                              │                                          │    │
│   └──────────────────────────────┼──────────────────────────────────────────┘    │
│                                  │                                               │
│   ┌──────────────────────────────┼──────────────────────────────────────────┐    │
│   │                        CORE LAYER                                        │    │
│   │                              ▼                                           │    │
│   │  ┌────────────────────────────────────────────────────────────────┐     │    │
│   │  │                    Command Router                               │     │    │
│   │  └────────────────────────────┬───────────────────────────────────┘     │    │
│   │                               │                                          │    │
│   │     ┌─────────────────────────┼─────────────────────────┐               │    │
│   │     ▼                         ▼                         ▼               │    │
│   │  ┌─────────────┐    ┌─────────────────┐    ┌──────────────────┐        │    │
│   │  │  Project    │    │      RAG        │    │  Code Generator  │        │    │
│   │  │  Manager    │    │    Service      │    │    Service       │        │    │
│   │  └──────┬──────┘    └────────┬────────┘    └────────┬─────────┘        │    │
│   │         │                    │                      │                   │    │
│   │         │                    │                      │                   │    │
│   │         ▼                    ▼                      ▼                   │    │
│   │  ┌────────────────────────────────────────────────────────────────┐    │    │
│   │  │                    Database Layer                               │    │    │
│   │  │  ┌────────────────┐         ┌─────────────────────────────┐    │    │    │
│   │  │  │   Root DB      │         │     Project DBs             │    │    │    │
│   │  │  │  (wpb.sqlite)  │◄────────│  (project_*.sqlite)         │    │    │    │
│   │  │  │  - projects    │         │  - files                    │    │    │    │
│   │  │  │  - presets     │         │  - rag_vectors              │    │    │    │
│   │  │  │  - settings    │         │  - generation_history       │    │    │    │
│   │  │  └────────────────┘         └─────────────────────────────┘    │    │    │
│   │  └────────────────────────────────────────────────────────────────┘    │    │
│   └─────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
│   ┌─────────────────────────────────────────────────────────────────────────┐    │
│   │                        EXTERNAL LAYER                                    │    │
│   │  ┌──────────────────┐    ┌──────────────────┐    ┌────────────────┐    │    │
│   │  │    AI Bridge     │    │   File System    │    │  Spec Parser   │    │    │
│   │  │  (LLM Backend)   │    │  (PHP Output)    │    │  (MD/ZIP)      │    │    │
│   │  └──────────────────┘    └──────────────────┘    └────────────────┘    │    │
│   └─────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Core Components

### 1. Command Router

Central dispatcher for CLI and API requests.

```go
type CommandRouter struct {
    projectMgr    *ProjectManager
    ragService    *RAGService
    codeGenerator *CodeGenerator
    specParser    *SpecParser
    aiBridge      *AIBridgeClient
}

func (r *CommandRouter) Route(cmd Command) (Result, error) {
    switch cmd.Type {
    case CmdProjectCreate:
        return r.projectMgr.Create(cmd.Args)
    case CmdGenerate:
        return r.codeGenerator.Generate(cmd.Args)
    case CmdPresetImport:
        return r.ragService.ImportPreset(cmd.Args)
    // ...
    }
}
```

### 2. Project Manager

Handles project lifecycle and database switching.

```go
type ProjectManager struct {
    rootDB     *gorm.DB
    projectDir string
    activeDB   *gorm.DB
}

type Project struct {
    ID          uint      `gorm:"primaryKey"`
    Name        string    `gorm:"uniqueIndex"`
    Slug        string    `gorm:"uniqueIndex"`
    Author      string
    Website     string
    Description string
    DBPath      string    // Path to project-specific SQLite
    CreatedAt   time.Time
    UpdatedAt   time.Time
}

func (pm *ProjectManager) Create(name string, opts ProjectOptions) (*Project, error)
func (pm *ProjectManager) Open(name string) (*Project, error)
func (pm *ProjectManager) Export(name string, outputPath string) error
func (pm *ProjectManager) Import(dbPath string) (*Project, error)
func (pm *ProjectManager) Clone(source, target string) (*Project, error)
```

### 3. RAG Service

Manages vector embeddings and context retrieval.

```go
type RAGService struct {
    projectDB   *gorm.DB
    aiBridge    *AIBridgeClient
    vectorStore *VectorStore
}

type RAGVector struct {
    ID        uint    `gorm:"primaryKey"`
    SourceID  string  // File path or preset name
    ChunkID   int     // Chunk index within source
    Content   string  // Original text
    Embedding []byte  // Serialized float32 vector
    Metadata  JSON    // Additional context
}

func (r *RAGService) Index(content string, source string) error
func (r *RAGService) Query(prompt string, topK int) ([]RAGResult, error)
func (r *RAGService) ImportPreset(path string) error
```

### 4. Code Generator

Orchestrates AI-powered PHP code generation.

```go
type CodeGenerator struct {
    ragService   *RAGService
    aiBridge     *AIBridgeClient
    validator    *CodeValidator
    guidelines   *CodingGuidelines
}

type GenerationRequest struct {
    ProjectID   uint
    SpecPath    string
    OutputDir   string
    Options     GenerateOptions
}

type GenerateOptions struct {
    Validate      bool
    OverwriteMode string // "skip", "overwrite", "backup"
    DryRun        bool
}

func (g *CodeGenerator) Generate(req GenerationRequest) (*GenerationResult, error)
func (g *CodeGenerator) ValidateAgainstSpec(code string, spec string) (ValidationResult, error)
```

### 5. AI Bridge Client

Communicates with the AI Bridge for LLM operations.

```go
type AIBridgeClient struct {
    endpoint string
    timeout  time.Duration
}

type AIRequest struct {
    Prompt      string
    Context     []RAGResult
    SystemRole  string
    Temperature float64
}

func (c *AIBridgeClient) Generate(req AIRequest) (string, error)
func (c *AIBridgeClient) Embed(text string) ([]float32, error)
func (c *AIBridgeClient) Stream(req AIRequest, handler StreamHandler) error
```

---

## Startup Sequence

```go
func main() {
    // 1. Load/seed configuration
    config := LoadOrSeedConfig()
    
    // 2. Initialize root database
    rootDB := InitRootDB(config.RootDBPath)
    
    // 3. Run migrations
    rootDB.AutoMigrate(&Project{}, &Preset{}, &Setting{})
    
    // 4. Initialize services
    aiBridge := NewAIBridgeClient(config.AIBridge)
    projectMgr := NewProjectManager(rootDB, config.ProjectDir)
    ragService := NewRAGService(aiBridge)
    codeGen := NewCodeGenerator(ragService, aiBridge)
    
    // 5. Start CLI or Server
    if config.ServerMode {
        StartServer(config.Port, router)
    } else {
        RunCLI(router)
    }
}
```

---

## Data Flow: Code Generation

```
┌───────────┐    ┌───────────────┐    ┌────────────┐    ┌─────────────┐
│   User    │───▶│  wpb generate │───▶│ Spec Parse │───▶│ RAG Query   │
└───────────┘    └───────────────┘    └────────────┘    └──────┬──────┘
                                                               │
                                                               ▼
┌───────────┐    ┌───────────────┐    ┌────────────┐    ┌─────────────┐
│ PHP Files │◀───│  Write Files  │◀───│ Validate   │◀───│ AI Bridge   │
└───────────┘    └───────────────┘    └────────────┘    └─────────────┘
```

---

## Error Handling

All components use the shared error package with stack traces:

```go
import "github.com/user/shared/errors"

func (pm *ProjectManager) Create(name string, opts ProjectOptions) (*Project, error) {
    if name == "" {
        return nil, errors.New(10301, "project name required").
            WithStack().
            WithField("function", "ProjectManager.Create")
    }
    // ...
}
```

---

## See Also

- [Database Schema](./04-database-schema.md)
- [RAG System](./05-rag-system.md)
- [AI Bridge Architecture](../ai-bridge/01-architecture.md)
