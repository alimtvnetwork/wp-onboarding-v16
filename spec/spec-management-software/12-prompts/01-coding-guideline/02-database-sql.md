---
name: Database SQL
description: Database design and query standards for SQLite with GORM
isDefault: false
version: 1
---

You are an AI assistant that generates database design and SQL coding guidelines. These guidelines ensure consistent, performant, and maintainable database schemas.

## Database Philosophy

- Schema is an API - design for stability
- Normalization by default, denormalize for performance
- Indexes are critical for query performance
- Data integrity at the database level

---

## Naming Conventions

### Tables
```sql
-- PascalCase for table names
CREATE TABLE User (...);
CREATE TABLE InstructionRun (...);
CREATE TABLE FileRegistry (...);

-- Junction tables: combine both entity names
CREATE TABLE UserRole (...);
CREATE TABLE ProjectFile (...);
```

### Columns
```sql
-- PascalCase for column names
CREATE TABLE User (
    Id TEXT PRIMARY KEY,           -- Always 'Id' for primary key
    Email TEXT NOT NULL,
    PasswordHash TEXT NOT NULL,
    CreatedAt TEXT NOT NULL,       -- ISO8601 format
    UpdatedAt TEXT NOT NULL,
    DeletedAt TEXT                 -- Soft delete (nullable)
);

-- Foreign keys: EntityName + Id
CREATE TABLE Post (
    Id TEXT PRIMARY KEY,
    UserId TEXT NOT NULL,          -- FK to User.Id
    CategoryId TEXT,               -- FK to Category.Id (nullable)
    FOREIGN KEY (UserId) REFERENCES User(Id)
);
```

### Indexes
```sql
-- Pattern: idx_{table}_{columns}
CREATE INDEX idx_user_email ON User(Email);
CREATE INDEX idx_post_user_created ON Post(UserId, CreatedAt);
CREATE UNIQUE INDEX idx_user_email_unique ON User(Email);
```

---

## Data Types (SQLite)

### Type Mapping
| Go Type | SQLite Type | Notes |
|---------|-------------|-------|
| string | TEXT | Default for strings |
| int, int64 | INTEGER | Numeric values |
| float64 | REAL | Floating point |
| bool | INTEGER | 0 or 1 |
| time.Time | TEXT | ISO8601 format |
| []byte | BLOB | Binary data |
| UUID | TEXT | Store as string |

### Common Patterns
```sql
-- UUID primary key
Id TEXT PRIMARY KEY  -- e.g., "550e8400-e29b-41d4-a716-446655440000"

-- Timestamps (ISO8601)
CreatedAt TEXT NOT NULL DEFAULT (datetime('now'))
UpdatedAt TEXT NOT NULL

-- Enum-like columns
Status TEXT NOT NULL CHECK(Status IN ('pending', 'active', 'completed', 'failed'))

-- JSON storage
Configuration TEXT  -- Store as JSON string, parse in application

-- Boolean
IsActive INTEGER NOT NULL DEFAULT 1  -- 0 = false, 1 = true
```

---

## Schema Design

### Table Template
```sql
CREATE TABLE EntityName (
    -- Primary Key
    Id TEXT PRIMARY KEY,
    
    -- Foreign Keys (grouped)
    ParentId TEXT NOT NULL,
    RelatedId TEXT,
    
    -- Required fields
    Name TEXT NOT NULL,
    Status TEXT NOT NULL DEFAULT 'active',
    
    -- Optional fields
    Description TEXT,
    Configuration TEXT,
    
    -- Metadata (always last)
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    DeletedAt TEXT,
    
    -- Constraints
    FOREIGN KEY (ParentId) REFERENCES Parent(Id) ON DELETE CASCADE,
    FOREIGN KEY (RelatedId) REFERENCES Related(Id) ON DELETE SET NULL
);

-- Indexes (create after table)
CREATE INDEX idx_entityname_parent ON EntityName(ParentId);
CREATE INDEX idx_entityname_status ON EntityName(Status) WHERE DeletedAt IS NULL;
```

### Relationship Patterns

#### One-to-Many
```sql
-- Parent table
CREATE TABLE Project (
    Id TEXT PRIMARY KEY,
    Name TEXT NOT NULL
);

-- Child table with foreign key
CREATE TABLE File (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL,
    FileName TEXT NOT NULL,
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE
);

CREATE INDEX idx_file_project ON File(ProjectId);
```

#### Many-to-Many
```sql
-- Entity A
CREATE TABLE User (
    Id TEXT PRIMARY KEY,
    Name TEXT NOT NULL
);

-- Entity B
CREATE TABLE Role (
    Id TEXT PRIMARY KEY,
    Name TEXT NOT NULL
);

-- Junction table
CREATE TABLE UserRole (
    UserId TEXT NOT NULL,
    RoleId TEXT NOT NULL,
    AssignedAt TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (UserId, RoleId),
    FOREIGN KEY (UserId) REFERENCES User(Id) ON DELETE CASCADE,
    FOREIGN KEY (RoleId) REFERENCES Role(Id) ON DELETE CASCADE
);
```

#### Self-Referential
```sql
CREATE TABLE Category (
    Id TEXT PRIMARY KEY,
    Name TEXT NOT NULL,
    ParentId TEXT,  -- Self-reference
    FOREIGN KEY (ParentId) REFERENCES Category(Id) ON DELETE SET NULL
);

CREATE INDEX idx_category_parent ON Category(ParentId);
```

---

## GORM Model Mapping

### Basic Model
```go
type User struct {
    Id           string         `gorm:"primaryKey;type:text"`
    Email        string         `gorm:"not null;uniqueIndex"`
    PasswordHash string         `gorm:"not null"`
    Name         string         `gorm:"not null"`
    Status       string         `gorm:"not null;default:'active'"`
    CreatedAt    time.Time
    UpdatedAt    time.Time
    DeletedAt    gorm.DeletedAt `gorm:"index"`
}
```

### With Relationships
```go
type Project struct {
    Id          string `gorm:"primaryKey;type:text"`
    Name        string `gorm:"not null"`
    Description string
    CreatedAt   time.Time
    UpdatedAt   time.Time
    
    // Has Many
    Files []File `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
}

type File struct {
    Id        string `gorm:"primaryKey;type:text"`
    ProjectId string `gorm:"not null;index"`
    FileName  string `gorm:"not null"`
    
    // Belongs To
    Project Project `gorm:"foreignKey:ProjectId"`
}
```

### Composite Keys and Indexes
```go
type UserRole struct {
    UserId     string    `gorm:"primaryKey;type:text"`
    RoleId     string    `gorm:"primaryKey;type:text"`
    AssignedAt time.Time `gorm:"not null"`
    
    User User `gorm:"foreignKey:UserId"`
    Role Role `gorm:"foreignKey:RoleId"`
}

// Composite index via GORM
type File struct {
    // ...
    ProjectId    string `gorm:"index:idx_file_project_path,priority:1"`
    RelativePath string `gorm:"index:idx_file_project_path,priority:2,unique"`
}
```

---

## Query Patterns

### ORM-Only Rule
```go
// GOOD: Use GORM constructs
var users []User
db.Where("Status = ?", "active").
   Order("CreatedAt DESC").
   Limit(10).
   Find(&users)

// BAD: Raw SQL
db.Raw("SELECT * FROM User WHERE Status = 'active'").Scan(&users)
```

### Common Queries
```go
// Find by ID
var user User
db.First(&user, "Id = ?", userId)

// Find with conditions
var files []File
db.Where("ProjectId = ? AND FileType = ?", projectId, "md").Find(&files)

// Preload relationships
var project Project
db.Preload("Files").First(&project, "Id = ?", projectId)

// Selective preload
db.Preload("Files", "FileType = ?", "md").First(&project, "Id = ?", projectId)

// Count
var count int64
db.Model(&File{}).Where("ProjectId = ?", projectId).Count(&count)

// Exists
var exists bool
db.Model(&User{}).
   Select("1").
   Where("Email = ?", email).
   Limit(1).
   Find(&exists)
```

### Pagination
```go
type Pagination struct {
    Page     int
    PageSize int
}

func (p *Pagination) Offset() int {
    return (p.Page - 1) * p.PageSize
}

// Usage
var files []File
var total int64

db.Model(&File{}).Where("ProjectId = ?", projectId).Count(&total)
db.Where("ProjectId = ?", projectId).
   Order("CreatedAt DESC").
   Offset(pagination.Offset()).
   Limit(pagination.PageSize).
   Find(&files)
```

---

## Indexing Strategy

### When to Create Indexes
```sql
-- 1. Foreign keys (always)
CREATE INDEX idx_file_project ON File(ProjectId);

-- 2. Frequently queried columns
CREATE INDEX idx_user_email ON User(Email);
CREATE INDEX idx_file_type ON File(FileType);

-- 3. Columns used in WHERE clauses
CREATE INDEX idx_run_status ON InstructionRun(Status);

-- 4. Columns used in ORDER BY
CREATE INDEX idx_file_created ON File(CreatedAt DESC);

-- 5. Composite for common query patterns
CREATE INDEX idx_file_project_type ON File(ProjectId, FileType);
```

### Partial Indexes (SQLite)
```sql
-- Index only active records
CREATE INDEX idx_user_active_email 
ON User(Email) 
WHERE DeletedAt IS NULL;

-- Index only specific status
CREATE INDEX idx_run_pending 
ON InstructionRun(CreatedAt) 
WHERE Status = 'pending';
```

### Index Anti-Patterns
```sql
-- DON'T: Index every column
-- DON'T: Index low-cardinality columns (Status with 3 values)
-- DON'T: Create redundant indexes
-- DON'T: Forget to index foreign keys
```

---

## Migrations

### Migration Best Practices
```go
// One change per migration
// migrations/001_create_users.go
func Up(db *gorm.DB) error {
    return db.AutoMigrate(&User{})
}

func Down(db *gorm.DB) error {
    return db.Migrator().DropTable("users")
}

// Separate migration for indexes
// migrations/002_add_user_indexes.go
func Up(db *gorm.DB) error {
    return db.Exec("CREATE INDEX idx_user_email ON User(Email)").Error
}
```

### Safe Migrations
```go
// Adding a column (safe)
db.Migrator().AddColumn(&User{}, "Bio")

// Renaming a column (use raw for SQLite)
db.Exec("ALTER TABLE User RENAME COLUMN OldName TO NewName")

// Adding NOT NULL column
// 1. Add as nullable
// 2. Backfill data
// 3. Add NOT NULL constraint (requires table rebuild in SQLite)
```

---

## Performance Tips

### Query Optimization
```go
// Select only needed columns
db.Select("Id", "Name", "Email").Find(&users)

// Avoid N+1 queries - use Preload
db.Preload("Files").Find(&projects)

// Use Joins for filtering on related tables
db.Joins("JOIN File ON File.ProjectId = Project.Id").
   Where("File.FileType = ?", "md").
   Find(&projects)

// Batch operations
db.CreateInBatches(records, 100)
```

### SQLite-Specific
```sql
-- Enable WAL mode for better concurrency
PRAGMA journal_mode=WAL;

-- Optimize for read-heavy workloads
PRAGMA cache_size=-64000;  -- 64MB cache

-- Periodic maintenance
VACUUM;
ANALYZE;
```
