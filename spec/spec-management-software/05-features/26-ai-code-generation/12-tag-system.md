# 12. Tag System

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [Overview](./00-overview.md)

---

## Purpose

Define the tag taxonomy and management system for categorizing generated code tasks, enabling intelligent reuse matching, filtering, and organization.

---

## Tag Taxonomy

### Category Hierarchy

```
tags/
├── operation/
│   ├── create
│   ├── read
│   ├── update
│   ├── delete
│   ├── rename
│   ├── move
│   └── copy
├── scope/
│   ├── single-file
│   ├── multi-file
│   ├── directory
│   └── recursive
├── complexity/
│   ├── simple
│   ├── moderate
│   └── complex
├── data-type/
│   ├── markdown
│   ├── json
│   ├── yaml
│   ├── text
│   └── binary
├── domain/
│   ├── index-management
│   ├── cross-reference
│   ├── dependency-analysis
│   ├── code-generation
│   └── documentation
├── transform/
│   ├── case-transform
│   ├── pattern-matching
│   ├── content-replace
│   └── format-conversion
└── batch/
    ├── batch-operation
    ├── parallel-safe
    └── sequential-required
```

---

## Tag Definitions

### Operation Tags

| Tag | Description | Keywords |
|-----|-------------|----------|
| `create` | Creates new files | create, new, add, generate |
| `read` | Reads file contents | read, get, fetch, load |
| `update` | Modifies existing files | update, modify, change, edit |
| `delete` | Removes files | delete, remove, clean, purge |
| `rename` | Renames files | rename, name |
| `move` | Moves files to new location | move, relocate |
| `copy` | Duplicates files | copy, duplicate, clone |

### Scope Tags

| Tag | Description | Indicators |
|-----|-------------|------------|
| `single-file` | Affects one file | "file", "this file" |
| `multi-file` | Affects multiple files | "files", "all", "every" |
| `directory` | Operates on directory | "folder", "directory" |
| `recursive` | Includes subdirectories | "recursive", "nested", "**" |

### Complexity Tags

| Tag | Complexity Score | Characteristics |
|-----|-----------------|-----------------|
| `simple` | 1-4 | Single operation, no conditions |
| `moderate` | 5-7 | Multiple operations or conditions |
| `complex` | 8+ | Multi-step, dependencies, parsing |

---

## Tag Management

### Tag Entity

```go
type Tag struct {
    Id          uint      `gorm:"primaryKey"`
    Name        string    `gorm:"uniqueIndex;not null"`
    Category    string    `gorm:"index;not null"`
    Description string
    Keywords    []string  `gorm:"serializer:json"`
    UsageCount  int       `gorm:"default:0"`
    CreatedAt   time.Time
    UpdatedAt   time.Time
}

type TaskTagAssociation struct {
    TaskId    uint `gorm:"primaryKey"`
    TagId     uint `gorm:"primaryKey"`
    CreatedAt time.Time
    Source    string // "auto" or "manual"
}
```

### Tag Service

```go
type TagService struct {
    db *gorm.DB
}

func (ts *TagService) GetOrCreate(name string, category string) (*Tag, error) {
    var tag Tag
    
    err := ts.db.Where("name = ?", name).First(&tag).Error
    if err == nil {
        return &tag, nil
    }
    
    if errors.Is(err, gorm.ErrRecordNotFound) {
        tag = Tag{
            Name:     name,
            Category: category,
        }
        if err := ts.db.Create(&tag).Error; err != nil {
            return nil, err
        }
        return &tag, nil
    }
    
    return nil, err
}

func (ts *TagService) AssignToTask(taskId uint, tagNames []string, source string) error {
    for _, name := range tagNames {
        // Find or infer category
        category := ts.inferCategory(name)
        
        tag, err := ts.GetOrCreate(name, category)
        if err != nil {
            return err
        }
        
        // Create association
        assoc := TaskTagAssociation{
            TaskId:    taskId,
            TagId:     tag.Id,
            Source:    source,
            CreatedAt: time.Now(),
        }
        
        if err := ts.db.Create(&assoc).Error; err != nil {
            // Ignore duplicate key errors
            if !strings.Contains(err.Error(), "UNIQUE constraint") {
                return err
            }
        }
        
        // Increment usage count
        ts.db.Model(&Tag{}).Where("id = ?", tag.Id).
            UpdateColumn("usage_count", gorm.Expr("usage_count + 1"))
    }
    
    return nil
}

func (ts *TagService) inferCategory(name string) string {
    categoryKeywords := map[string][]string{
        "operation": {"create", "read", "update", "delete", "rename", "move", "copy"},
        "scope":     {"single-file", "multi-file", "directory", "recursive"},
        "complexity": {"simple", "moderate", "complex"},
        "data-type": {"markdown", "json", "yaml", "text", "binary"},
        "domain":    {"index-management", "cross-reference", "dependency-analysis"},
        "transform": {"case-transform", "pattern-matching", "content-replace"},
        "batch":     {"batch-operation", "parallel-safe", "sequential-required"},
    }
    
    for category, keywords := range categoryKeywords {
        for _, keyword := range keywords {
            if name == keyword {
                return category
            }
        }
    }
    
    return "custom"
}
```

---

## Auto-Tagging

### Tag Extractor

```go
type AutoTagger struct {
    patterns map[string]TagPattern
}

type TagPattern struct {
    Tag      string
    Category string
    Regex    *regexp.Regexp
    Keywords []string
}

func NewAutoTagger() *AutoTagger {
    at := &AutoTagger{
        patterns: make(map[string]TagPattern),
    }
    
    // Operation patterns
    at.addPattern("rename", "operation", `rename|renaming`, []string{"rename"})
    at.addPattern("delete", "operation", `delete|remove|clean`, []string{"delete", "remove"})
    at.addPattern("create", "operation", `create|new|add|generate`, []string{"create", "new"})
    at.addPattern("update", "operation", `update|modify|change|edit`, []string{"update", "modify"})
    at.addPattern("copy", "operation", `copy|duplicate|clone`, []string{"copy"})
    at.addPattern("move", "operation", `move|relocate`, []string{"move"})
    
    // Scope patterns
    at.addPattern("multi-file", "scope", `all files|every file|files in|multiple`, []string{"all", "every"})
    at.addPattern("directory", "scope", `directory|folder`, []string{"directory", "folder"})
    at.addPattern("recursive", "scope", `recursive|nested|\*\*`, []string{"recursive"})
    
    // Data type patterns
    at.addPattern("markdown", "data-type", `\.md|markdown`, []string{".md", "markdown"})
    at.addPattern("json", "data-type", `\.json|json`, []string{".json", "json"})
    at.addPattern("yaml", "data-type", `\.ya?ml|yaml`, []string{".yaml", ".yml"})
    
    // Domain patterns
    at.addPattern("index-management", "domain", `index|overview|00-`, []string{"index", "overview"})
    at.addPattern("cross-reference", "domain", `cross.?ref|link`, []string{"cross-reference", "link"})
    
    // Transform patterns
    at.addPattern("case-transform", "transform", `lowercase|uppercase|capitalize`, []string{"lowercase", "uppercase"})
    at.addPattern("pattern-matching", "transform", `pattern|regex|match`, []string{"pattern", "regex"})
    
    // Batch patterns
    at.addPattern("batch-operation", "batch", `batch|bulk|mass`, []string{"batch", "all"})
    
    return at
}

func (at *AutoTagger) addPattern(tag, category, regex string, keywords []string) {
    at.patterns[tag] = TagPattern{
        Tag:      tag,
        Category: category,
        Regex:    regexp.MustCompile(`(?i)` + regex),
        Keywords: keywords,
    }
}

func (at *AutoTagger) ExtractTags(description string) []string {
    tags := make(map[string]bool)
    
    for tag, pattern := range at.patterns {
        if pattern.Regex.MatchString(description) {
            tags[tag] = true
        }
    }
    
    // Infer complexity from tag count and description length
    complexity := at.inferComplexity(description, len(tags))
    tags[complexity] = true
    
    result := make([]string, 0, len(tags))
    for tag := range tags {
        result = append(result, tag)
    }
    
    sort.Strings(result)
    return result
}

func (at *AutoTagger) inferComplexity(description string, tagCount int) string {
    wordCount := len(strings.Fields(description))
    
    if tagCount <= 2 && wordCount < 20 {
        return "simple"
    } else if tagCount <= 4 && wordCount < 50 {
        return "moderate"
    }
    return "complex"
}
```

---

## Tag Queries

### Find Tasks by Tags

```sql
-- Tasks with ALL specified tags
SELECT t.* 
FROM TempCodingTasks t
WHERE t.Id IN (
    SELECT TaskId 
    FROM TaskTags 
    WHERE TagName IN ('rename', 'multi-file', 'markdown')
    GROUP BY TaskId
    HAVING COUNT(DISTINCT TagName) = 3
);

-- Tasks with ANY specified tags (ranked by overlap)
SELECT t.*, COUNT(tt.TagName) as tag_overlap
FROM TempCodingTasks t
JOIN TaskTags tt ON t.Id = tt.TaskId
WHERE tt.TagName IN ('rename', 'batch-operation')
GROUP BY t.Id
ORDER BY tag_overlap DESC;
```

### Popular Tags

```sql
SELECT TagName, COUNT(*) as usage_count
FROM TaskTags
GROUP BY TagName
ORDER BY usage_count DESC
LIMIT 20;
```

---

## TypeScript Types

```typescript
interface Tag {
  readonly id: number;
  readonly name: string;
  readonly category: TagCategory;
  readonly description: string | null;
  readonly keywords: readonly string[];
  readonly usageCount: number;
  readonly createdAt: Date;
}

enum TagCategory {
  Operation = "operation",
  Scope = "scope",
  Complexity = "complexity",
  DataType = "data-type",
  Domain = "domain",
  Transform = "transform",
  Batch = "batch",
  Custom = "custom",
}

interface TaskTagAssociation {
  readonly taskId: number;
  readonly tagId: number;
  readonly createdAt: Date;
  readonly source: "auto" | "manual";
}

interface TagSearchRequest {
  readonly tags: readonly string[];
  readonly matchAll: boolean;
  readonly limit: number;
}

interface TagSuggestion {
  readonly tag: string;
  readonly category: TagCategory;
  readonly confidence: number;
  readonly reason: string;
}
```

---

## UI Integration

### Tag Input Component

```
┌─────────────────────────────────────────────────────────────┐
│  Tags: [rename] [multi-file] [markdown] [+]                 │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Suggested tags:                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ [batch-operation] - "all files" detected             │   │
│  │ [index-management] - "overview" detected             │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
│  Popular in this category:                                   │
│  [case-transform] [recursive] [simple]                       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Related Specs

- [05-task-matcher.md](./05-task-matcher.md) — Uses tags for matching
- [11-database-schema.md](./11-database-schema.md) — Tag storage schema
- [10-agentic-search.md](./10-agentic-search.md) — Tag-based filtering
