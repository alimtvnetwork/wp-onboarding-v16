# Database Conventions

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Naming conventions, data type standards, and coding patterns for the Spec Management Software database.

**Cross-References:**
- [Schema Definition](./01-schema.md)
- [Coding Guidelines](../04-coding-guidelines/00-overview.md)

---

## Naming Conventions

### Table Names

| Rule | Convention | Example |
|------|------------|---------|
| Case | PascalCase | `UserSession`, `ProjectMetadata` |
| Plurality | Singular | `User` not `Users` |
| Compound words | No separator | `ModelRegistry` not `Model_Registry` |

```go
func (User) TableName() string { return "User" }
func (ProjectMetadata) TableName() string { return "ProjectMetadata" }
```

### Column Names

| Rule | Convention | Example |
|------|------------|---------|
| Case | PascalCase | `CreatedAt`, `UserId` |
| Primary key | `Id` | `Id` |
| Foreign keys | `{ReferencedTable}Id` | `ProjectId`, `UserId` |
| Timestamps | `*At` suffix | `CreatedAt`, `UpdatedAt`, `DeletedAt` |
| Booleans | `Is*` or `Has*` prefix | `IsEnabled`, `HasAccess` |
| Counts | `*Count` suffix | `RetryCount`, `ViewCount` |
| Nullable | Pointer type in Go | `*string`, `*time.Time` |

### Index Names

| Type | Pattern | Example |
|------|---------|---------|
| Primary Key | `PK_{Table}` | `PK_User` (automatic) |
| Unique Index | `IX_{Table}_{Column}` | `IX_User_Email` |
| Foreign Key Index | `IX_{Table}_{FKColumn}` | `IX_Project_OwnerId` |
| Composite Index | `IX_{Table}_{Col1}_{Col2}` | `IX_File_Project_Path` |
| Functional Index | `IX_{Table}_{Purpose}` | `IX_Session_Active` |

---

## Data Types

### Type Mapping

| Concept | SQLite Type | Go Type | GORM Tag |
|---------|-------------|---------|----------|
| Primary Key | TEXT | `string` | `gorm:"type:text;primaryKey"` |
| String | TEXT | `string` | `gorm:"type:text"` |
| Nullable String | TEXT | `*string` | `gorm:"type:text"` |
| Integer | INTEGER | `int` / `int64` | `gorm:"type:integer"` |
| Boolean | INTEGER | `bool` | (automatic 0/1) |
| Timestamp | TEXT | `time.Time` | (ISO8601 format) |
| Nullable Timestamp | TEXT | `*time.Time` | `gorm:"type:text"` |
| JSON | TEXT | `datatypes.JSON` | `gorm:"type:text"` |
| Soft Delete | TEXT | `gorm.DeletedAt` | `gorm:"index"` |

### UUID Format

All IDs use UUID v4 format stored as TEXT:

```go
// Generated via google/uuid package
Id string `gorm:"type:text;primaryKey"` // "550e8400-e29b-41d4-a716-446655440000"
```

### Timestamp Format

ISO8601 format with UTC timezone:

```go
CreatedAt time.Time // Stored as "2026-01-28T14:30:00Z"
```

### JSON Storage

Use `datatypes.JSON` for structured data:

```go
import "gorm.io/datatypes"

Tags           datatypes.JSON `gorm:"type:text"` // ["tag1", "tag2"]
Settings       datatypes.JSON `gorm:"type:text"` // {"key": "value"}
CustomMetadata datatypes.JSON `gorm:"type:text"` // Arbitrary JSON
```

---

## Enum Patterns

### String Enums

Define enums as string constants:

```go
// Define type
type ProjectType string

// Define constants
const (
    ProjectTypeCategory ProjectType = "category"
    ProjectTypeProject  ProjectType = "project"
)

// Use in struct
Type ProjectType `gorm:"type:text;not null"`
```

### Enum Validation

Validate enum values in BeforeCreate/BeforeUpdate hooks:

```go
func (p *Project) BeforeCreate(tx *gorm.DB) error {
    if p.Type != ProjectTypeCategory && p.Type != ProjectTypeProject {
        return errors.New("invalid project type")
    }
    return nil
}
```

### Standard Enums

| Enum | Values |
|------|--------|
| `ProjectType` | `category`, `project` |
| `Visibility` | `user`, `global` |
| `FileType` | `folder`, `file` |
| `ConfigSource` | `seed`, `user` |
| `ModelType` | `reasoning`, `voice` |
| `SlotStatus` | `idle`, `loading`, `active`, `error`, `unloading` |
| `InstructionStatus` | `draft`, `analyzing`, `pending_clarification`, `generating`, `completed`, `failed` |
| `ArtifactType` | `idea`, `instruction` |
| `TaskStatus` | `pending`, `in_progress`, `completed`, `failed`, `skipped` |

---

## GORM Tag Patterns

### Required Field

```go
Name string `gorm:"type:text;not null"`
```

### Unique Constraint

```go
Email string `gorm:"type:text;not null;uniqueIndex:IX_User_Email"`
```

### Default Value

```go
ThemePreference string `gorm:"type:text;default:'light'"`
IsEnabled       bool   `gorm:"default:true"`
SortOrder       int    `gorm:"default:0"`
```

### Index

```go
// Single column index
Status string `gorm:"type:text;index:IX_Instruction_Status"`

// Composite index
ProjectId string `gorm:"type:text;index:idx_file_project_path"`
Path      string `gorm:"type:text;index:idx_file_project_path"`
```

### Foreign Key with Constraint

```go
// Cascade delete
Sessions []Session `gorm:"foreignKey:UserId;constraint:OnDelete:CASCADE"`

// Set null on delete
CreatedBy User `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL"`
```

---

## Base Model Pattern

### Standard Base Model

For entities needing full CRUD with soft delete:

```go
type BaseModel struct {
    Id        string         `gorm:"type:text;primaryKey" json:"id"`
    CreatedAt time.Time      `gorm:"not null" json:"createdAt"`
    UpdatedAt time.Time      `gorm:"not null" json:"updatedAt"`
    DeletedAt gorm.DeletedAt `gorm:"index" json:"-"`
}

func (b *BaseModel) BeforeCreate(tx *gorm.DB) error {
    if b.Id == "" {
        b.Id = uuid.New().String()
    }
    return nil
}
```

### Timestamp-Only Model

For immutable entities (logs, events):

```go
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

## Query Patterns

### Find by ID

```go
var user User
db.First(&user, "id = ?", userId)
```

### Find with Preload

```go
var project Project
db.Preload("Files").Preload("Metadata").First(&project, "id = ?", projectId)
```

### Find with Conditions

```go
var projects []Project
db.Where("owner_id = ? AND visibility = ?", userId, VisibilityGlobal).Find(&projects)
```

### Create

```go
user := User{
    Username: "johndoe",
    Email:    "john@example.com",
}
db.Create(&user) // Id auto-generated
```

### Update

```go
db.Model(&project).Updates(map[string]interface{}{
    "name":        newName,
    "description": newDescription,
})
```

### Soft Delete

```go
db.Delete(&project) // Sets DeletedAt, doesn't remove row
```

### Hard Delete

```go
db.Unscoped().Delete(&project) // Permanently removes row
```

---

## Transaction Pattern

```go
err := db.Transaction(func(tx *gorm.DB) error {
    // Create project
    if err := tx.Create(&project).Error; err != nil {
        return err // Rollback
    }
    
    // Create metadata
    metadata := ProjectMetadata{ProjectId: project.Id}
    if err := tx.Create(&metadata).Error; err != nil {
        return err // Rollback
    }
    
    return nil // Commit
})
```

---

## Configuration Keys

Use dot.notation for hierarchical config keys:

| Category | Key Pattern | Example |
|----------|-------------|---------|
| LLaMA Server | `llama.server.*` | `llama.server.path`, `llama.server.port` |
| Models | `llama.model.*` | `llama.model.default.reasoning` |
| App Settings | `app.*` | `app.theme.default`, `app.language` |
| Feature Flags | `feature.*` | `feature.voice.enabled` |
| Paths | `path.*` | `path.work.directory`, `path.models` |

---

## JSON Field Conventions

### Tags Array

```json
["backend", "api", "authentication"]
```

### Settings Object

```json
{
  "autoSave": true,
  "autoSaveInterval": 30,
  "theme": "dark"
}
```

### Metadata Object

```json
{
  "version": "1.0.0",
  "author": "John Doe",
  "customFields": {
    "department": "Engineering"
  }
}
```

---

## Related Specs

- [Schema Definition](./01-schema.md) — Complete GORM models
- [Migrations](./02-migrations.md) — Migration patterns
- [Relationships](./03-relationships.md) — FK constraints
