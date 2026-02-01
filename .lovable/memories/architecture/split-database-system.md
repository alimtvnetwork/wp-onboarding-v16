# Memory: architecture/split-database-system

**Updated:** 2026-01-30  
**Purpose:** AI Training Document — Multi-SQLite Database Architecture

---

## Overview

The application uses a **split SQLite database architecture** where different concerns are isolated into separate database files. This provides data locality, simpler backups, and prevents cross-concern coupling.

---

## Database Files

| Database | File | Scope | Purpose |
|----------|------|-------|---------|
| **Settings DB** | `settings.db` | Global | App-wide configuration, user preferences, seedable configs |
| **Project Route DB** | `projects.db` | Global | Project index, routing, metadata for all projects |
| **Project DB** | `{project-id}/project.db` | Per-Project | Project-specific specs, files, artifacts |
| **Conversation DB** | `{project-id}/conversations/{conv-id}.db` | Per-Conversation | Chat history, AI context, message threads |

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        APPLICATION DATA LAYER                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌───────────────────────────────────────────────────────────────────────┐   │
│  │                         GLOBAL DATABASES                               │   │
│  │                                                                         │   │
│  │   ┌─────────────────────┐      ┌─────────────────────┐                │   │
│  │   │    settings.db      │      │    projects.db      │                │   │
│  │   ├─────────────────────┤      ├─────────────────────┤                │   │
│  │   │ • User preferences  │      │ • Project index     │                │   │
│  │   │ • Seedable configs  │      │ • Project metadata  │                │   │
│  │   │ • Model registry    │      │ • Last opened       │                │   │
│  │   │ • Theme settings    │      │ • Project paths     │                │   │
│  │   │ • API keys (enc)    │      │ • Favorites         │                │   │
│  │   └─────────────────────┘      └─────────────────────┘                │   │
│  │                                                                         │   │
│  └───────────────────────────────────────────────────────────────────────┘   │
│                                                                               │
│  ┌───────────────────────────────────────────────────────────────────────┐   │
│  │                    PER-PROJECT DATABASES                               │   │
│  │                                                                         │   │
│  │   📁 /projects/{project-id}/                                           │   │
│  │   │                                                                     │   │
│  │   ├── project.db                                                        │   │
│  │   │   ├── Specification          (specs for this project)              │   │
│  │   │   ├── Instruction            (AI instructions)                     │   │
│  │   │   ├── InstructionTask        (task breakdowns)                     │   │
│  │   │   ├── Artifact               (generated files)                     │   │
│  │   │   └── ProjectSettings        (project-specific config)             │   │
│  │   │                                                                     │   │
│  │   └── conversations/                                                    │   │
│  │       ├── {conv-id-1}.db         (conversation 1)                       │   │
│  │       │   ├── Message            (chat messages)                        │   │
│  │       │   ├── MessageContext     (RAG context snapshots)                │   │
│  │       │   └── ConversationMeta   (title, created, tokens)               │   │
│  │       │                                                                 │   │
│  │       └── {conv-id-2}.db         (conversation 2)                       │   │
│  │                                                                         │   │
│  └───────────────────────────────────────────────────────────────────────┘   │
│                                                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Database Details

### 1. Settings DB (`settings.db`)

**Location:** `~/.specbuilder/settings.db`  
**Scope:** Global, single instance  
**Contains:** All app-wide configuration that applies across projects

```sql
-- Example tables in settings.db

CREATE TABLE UserPreference (
    Id TEXT PRIMARY KEY,
    UserId TEXT NOT NULL,
    Key TEXT NOT NULL,
    Value TEXT NOT NULL,
    IsUserModified BOOLEAN DEFAULT FALSE,  -- For seedable config
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL,
    UNIQUE(UserId, Key)
);

CREATE TABLE ModelRegistry (
    Id TEXT PRIMARY KEY,
    Name TEXT NOT NULL,
    Category TEXT NOT NULL,  -- thinking, writing, voice, coding
    Endpoint TEXT NOT NULL,
    IsDefault BOOLEAN DEFAULT FALSE,
    IsEnabled BOOLEAN DEFAULT TRUE,
    CreatedAt TEXT NOT NULL
);

CREATE TABLE SeedableConfig (
    Id TEXT PRIMARY KEY,
    Key TEXT NOT NULL UNIQUE,
    Value TEXT NOT NULL,           -- JSON value
    Category TEXT NOT NULL,        -- weights, thresholds, models
    Version TEXT NOT NULL,         -- Seed version
    IsUserModified BOOLEAN DEFAULT FALSE,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE TABLE NotificationPreference (
    Id TEXT PRIMARY KEY,
    UserId TEXT NOT NULL UNIQUE,
    InAppEnabled BOOLEAN DEFAULT TRUE,
    EmailEnabled BOOLEAN DEFAULT TRUE,
    EmailMinPriority TEXT DEFAULT 'medium',
    -- ... other preferences
    UpdatedAt TEXT NOT NULL
);
```

**Access Pattern:**
```go
// Global singleton - opened once at app start
settingsDB, err := gorm.Open(sqlite.Open("~/.specbuilder/settings.db"), &gorm.Config{})
```

---

### 2. Project Route DB (`projects.db`)

**Location:** `~/.specbuilder/projects.db`  
**Scope:** Global, indexes all projects  
**Contains:** Project metadata for routing and discovery

```sql
-- Example tables in projects.db

CREATE TABLE ProjectIndex (
    Id TEXT PRIMARY KEY,
    Name TEXT NOT NULL,
    Path TEXT NOT NULL UNIQUE,         -- Filesystem path to project
    Description TEXT,
    LastOpenedAt TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL,
    IsFavorite BOOLEAN DEFAULT FALSE,
    IsArchived BOOLEAN DEFAULT FALSE
);

CREATE TABLE RecentProject (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL,
    OpenedAt TEXT NOT NULL,
    FOREIGN KEY (ProjectId) REFERENCES ProjectIndex(Id) ON DELETE CASCADE
);

CREATE TABLE ProjectTag (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL,
    Tag TEXT NOT NULL,
    FOREIGN KEY (ProjectId) REFERENCES ProjectIndex(Id) ON DELETE CASCADE
);
```

**Access Pattern:**
```go
// Global singleton - for project discovery
projectsDB, err := gorm.Open(sqlite.Open("~/.specbuilder/projects.db"), &gorm.Config{})

// Query all projects
var projects []ProjectIndex
projectsDB.Where("IsArchived = ?", false).Order("LastOpenedAt DESC").Find(&projects)
```

---

### 3. Project DB (`{project-id}/project.db`)

**Location:** `{project-path}/.specbuilder/project.db`  
**Scope:** Per-project, one per project  
**Contains:** All data specific to a single project

```sql
-- Example tables in project.db

CREATE TABLE Specification (
    Id TEXT PRIMARY KEY,
    FilePath TEXT NOT NULL UNIQUE,
    Title TEXT NOT NULL,
    Content TEXT,
    Status TEXT DEFAULT 'draft',
    Version TEXT DEFAULT '1.0.0',
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE TABLE Instruction (
    Id TEXT PRIMARY KEY,
    RawTranscription TEXT,
    ProofreadText TEXT,
    InstructionText TEXT NOT NULL,
    Scope TEXT NOT NULL,
    Status TEXT NOT NULL,
    ExecutionMode TEXT DEFAULT 'approval',
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE TABLE InstructionTask (
    Id TEXT PRIMARY KEY,
    InstructionId TEXT NOT NULL,
    Title TEXT NOT NULL,
    Description TEXT,
    TaskType TEXT NOT NULL,
    Status TEXT DEFAULT 'pending',
    SortOrder INTEGER DEFAULT 0,
    FOREIGN KEY (InstructionId) REFERENCES Instruction(Id) ON DELETE CASCADE
);

CREATE TABLE Artifact (
    Id TEXT PRIMARY KEY,
    InstructionId TEXT,
    Type TEXT NOT NULL,          -- spec, diagram, code
    FilePath TEXT NOT NULL,
    Content TEXT,
    CreatedAt TEXT NOT NULL,
    FOREIGN KEY (InstructionId) REFERENCES Instruction(Id) ON DELETE SET NULL
);

CREATE TABLE ProjectSettings (
    Id TEXT PRIMARY KEY,
    Key TEXT NOT NULL UNIQUE,
    Value TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);
```

**Access Pattern:**
```go
// Opened when project is loaded, closed when project is closed
func OpenProjectDB(projectPath string) (*gorm.DB, error) {
    dbPath := filepath.Join(projectPath, ".specbuilder", "project.db")
    return gorm.Open(sqlite.Open(dbPath), &gorm.Config{})
}

// Usage
projectDB, _ := OpenProjectDB("/home/user/my-project")
defer projectDB.Close()

var specs []Specification
projectDB.Find(&specs)
```

---

### 4. Conversation DB (`{project-id}/conversations/{conv-id}.db`)

**Location:** `{project-path}/.specbuilder/conversations/{conversation-id}.db`  
**Scope:** Per-conversation, one per AI chat session  
**Contains:** Complete conversation history and context

```sql
-- Example tables in conversation DB

CREATE TABLE ConversationMeta (
    Id TEXT PRIMARY KEY,
    Title TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL,
    TotalTokens INTEGER DEFAULT 0,
    MessageCount INTEGER DEFAULT 0
);

CREATE TABLE Message (
    Id TEXT PRIMARY KEY,
    Role TEXT NOT NULL,          -- user, assistant, system
    Content TEXT NOT NULL,
    TokenCount INTEGER,
    CreatedAt TEXT NOT NULL,
    ParentId TEXT,               -- For branching conversations
    FOREIGN KEY (ParentId) REFERENCES Message(Id) ON DELETE SET NULL
);

CREATE TABLE MessageContext (
    Id TEXT PRIMARY KEY,
    MessageId TEXT NOT NULL,
    ContextType TEXT NOT NULL,   -- rag, file, memory
    Content TEXT NOT NULL,
    Source TEXT,
    FOREIGN KEY (MessageId) REFERENCES Message(Id) ON DELETE CASCADE
);

CREATE TABLE ConversationArtifact (
    Id TEXT PRIMARY KEY,
    MessageId TEXT NOT NULL,
    ArtifactType TEXT NOT NULL,  -- code, diagram, file
    Content TEXT NOT NULL,
    FilePath TEXT,
    FOREIGN KEY (MessageId) REFERENCES Message(Id) ON DELETE CASCADE
);
```

**Access Pattern:**
```go
// Opened when conversation is active, closed when switching
func OpenConversationDB(projectPath, conversationId string) (*gorm.DB, error) {
    dbPath := filepath.Join(projectPath, ".specbuilder", "conversations", conversationId+".db")
    return gorm.Open(sqlite.Open(dbPath), &gorm.Config{})
}

// Usage - manage multiple open conversations
type ConversationManager struct {
    activeDBs map[string]*gorm.DB
    mu        sync.RWMutex
}

func (m *ConversationManager) GetConversation(projectPath, convId string) (*gorm.DB, error) {
    m.mu.Lock()
    defer m.mu.Unlock()
    
    key := projectPath + "/" + convId
    if db, ok := m.activeDBs[key]; ok {
        return db, nil
    }
    
    db, err := OpenConversationDB(projectPath, convId)
    if err != nil {
        return nil, err
    }
    
    m.activeDBs[key] = db
    return db, nil
}
```

---

## Database Connection Management

### Connection Lifecycle

```go
type DatabaseManager struct {
    settingsDB   *gorm.DB          // Always open
    projectsDB   *gorm.DB          // Always open
    projectDB    *gorm.DB          // Open when project loaded
    convManager  *ConversationManager
}

func NewDatabaseManager() (*DatabaseManager, error) {
    // 1. Open global databases at app start
    settingsDB, err := openSettingsDB()
    if err != nil {
        return nil, err
    }
    
    projectsDB, err := openProjectsDB()
    if err != nil {
        return nil, err
    }
    
    return &DatabaseManager{
        settingsDB:  settingsDB,
        projectsDB:  projectsDB,
        convManager: NewConversationManager(),
    }, nil
}

func (m *DatabaseManager) LoadProject(projectPath string) error {
    // Close previous project if open
    if m.projectDB != nil {
        m.projectDB.Close()
    }
    
    // Open new project DB
    db, err := OpenProjectDB(projectPath)
    if err != nil {
        return err
    }
    
    m.projectDB = db
    return nil
}

func (m *DatabaseManager) Close() {
    m.settingsDB.Close()
    m.projectsDB.Close()
    if m.projectDB != nil {
        m.projectDB.Close()
    }
    m.convManager.CloseAll()
}
```

---

## Why Split Databases?

| Benefit | Explanation |
|---------|-------------|
| **Data Locality** | Project data travels with project folder |
| **Simple Backups** | Copy project folder = complete backup |
| **No Cross-Pollution** | Deleting project removes all its data |
| **Performance** | Smaller DBs = faster queries |
| **Concurrency** | Separate files = no lock contention |
| **Portability** | Move project folder to new machine |

---

## Migration Strategy

Each database has its own migration version:

```go
type MigrationManager struct {
    settingsVersion string  // "1.0.3"
    projectVersion  string  // "2.1.0"
    convVersion     string  // "1.0.0"
}

func (m *MigrationManager) MigrateSettingsDB(db *gorm.DB) error {
    // Run migrations for settings.db
    return db.AutoMigrate(
        &UserPreference{},
        &ModelRegistry{},
        &SeedableConfig{},
        &NotificationPreference{},
    )
}

func (m *MigrationManager) MigrateProjectDB(db *gorm.DB) error {
    // Run migrations for project.db
    return db.AutoMigrate(
        &Specification{},
        &Instruction{},
        &InstructionTask{},
        &Artifact{},
        &ProjectSettings{},
    )
}
```

---

## Common Patterns

### Cross-Database Queries (Avoid)

```go
// ❌ WRONG - Never join across databases
// This is impossible with SQLite split architecture

// ✅ CORRECT - Fetch from each DB separately
func GetProjectWithSettings(projectId string) (*ProjectWithSettings, error) {
    // 1. Get project from projects.db
    var project ProjectIndex
    projectsDB.First(&project, "Id = ?", projectId)
    
    // 2. Get settings from settings.db
    var prefs []UserPreference
    settingsDB.Where("UserId = ?", userId).Find(&prefs)
    
    // 3. Combine in application code
    return &ProjectWithSettings{
        Project:     project,
        Preferences: prefs,
    }, nil
}
```

### Transaction Boundaries

```go
// Each database has its own transaction scope
func CreateInstructionWithTasks(projectDB *gorm.DB, instruction *Instruction, tasks []InstructionTask) error {
    return projectDB.Transaction(func(tx *gorm.DB) error {
        if err := tx.Create(instruction).Error; err != nil {
            return err
        }
        
        for _, task := range tasks {
            task.InstructionId = instruction.Id
            if err := tx.Create(&task).Error; err != nil {
                return err
            }
        }
        
        return nil
    })
}
```

---

## For AI Training

When implementing features:

1. **Identify which database** the data belongs to
2. **Use correct connection** from DatabaseManager
3. **Never assume** databases can be joined
4. **Keep transactions** within single database
5. **Close project/conversation DBs** when not in use
