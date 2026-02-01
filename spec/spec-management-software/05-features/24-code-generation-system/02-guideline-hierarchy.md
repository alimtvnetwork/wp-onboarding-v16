# Coding Guidelines Hierarchy

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

The Coding Guidelines Hierarchy defines a four-layer system for providing coding instructions to AI models. Later layers override conflicting rules from earlier layers, ensuring project-specific requirements take precedence while maintaining baseline standards.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Coding Model Presets](./11-coding-model-presets.md)
- [Project Coding Guidelines](../../04-coding-guidelines/00-overview.md)

---

## Hierarchy Levels

```
┌────────────────────────────────────────────────────────────┐
│                  PRIORITY ORDER (Low → High)               │
├────────────────────────────────────────────────────────────┤
│                                                            │
│   Level 1: General Guidelines (Lowest Priority)           │
│   ├── Universal coding standards                          │
│   ├── Applied to ALL projects and languages               │
│   └── Location: /guidelines/general/                      │
│                                                            │
│   Level 2: Language Guidelines                            │
│   ├── Language-specific conventions (Go, React, PHP...)   │
│   ├── Applied to projects using that language             │
│   └── Location: /guidelines/languages/{lang}/             │
│                                                            │
│   Level 3: User Preference Guidelines                     │
│   ├── Personal coding style preferences                   │
│   ├── Applied to all user's projects                      │
│   └── Location: /users/{id}/guidelines/                   │
│                                                            │
│   Level 4: Project Guidelines (Highest Priority)          │
│   ├── Project-specific rules and conventions              │
│   ├── Applied only to this project                        │
│   └── Location: /projects/{id}/guidelines/                │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

---

## Data Models

### CodingGuideline Entity

```go
type CodingGuideline struct {
    ID          string          `gorm:"primaryKey;type:text"`
    Level       GuidelineLevel  `gorm:"type:text;not null;index"`
    LanguageCode string         `gorm:"type:text;index"`           // null for general
    UserID      string          `gorm:"type:text;index"`           // null for system-wide
    ProjectID   string          `gorm:"type:text;index"`           // null for non-project
    Name        string          `gorm:"type:text;not null"`
    Description string          `gorm:"type:text"`
    Content     string          `gorm:"type:text;not null"`        // Markdown content
    Priority    int             `gorm:"type:integer;default:0"`    // Within same level
    IsActive    bool            `gorm:"type:boolean;default:true"`
    Version     int             `gorm:"type:integer;default:1"`
    CreatedAt   time.Time
    UpdatedAt   time.Time
}

type GuidelineLevel string

const (
    GuidelineLevelGeneral    GuidelineLevel = "general"
    GuidelineLevelLanguage   GuidelineLevel = "language"
    GuidelineLevelUser       GuidelineLevel = "user"
    GuidelineLevelProject    GuidelineLevel = "project"
)
```

### GuidelineOverride Record

```go
type GuidelineOverride struct {
    ID                string    `gorm:"primaryKey;type:text"`
    ResolutionID      string    `gorm:"type:text;not null;index"`
    OverridingID      string    `gorm:"type:text;not null"`        // Guideline that won
    OverriddenID      string    `gorm:"type:text;not null"`        // Guideline that lost
    Section           string    `gorm:"type:text"`                 // Which section conflicted
    Reason            string    `gorm:"type:text"`
    CreatedAt         time.Time
}
```

---

## Supported Languages

| Code | Language | File Extensions |
|------|----------|-----------------|
| `go` | Golang | `.go` |
| `react` | React/TypeScript | `.tsx`, `.ts`, `.jsx`, `.js` |
| `php` | PHP | `.php` |
| `python` | Python | `.py` |
| `rust` | Rust | `.rs` |
| `java` | Java | `.java` |
| `csharp` | C# | `.cs` |
| `sql` | SQL | `.sql` |
| `css` | CSS/SCSS | `.css`, `.scss`, `.sass` |
| `html` | HTML | `.html`, `.htm` |
| `yaml` | YAML | `.yaml`, `.yml` |
| `json` | JSON | `.json` |
| `markdown` | Markdown | `.md` |

---

## Resolution Algorithm

### Priority-Based Override

```go
type GuidelineResolver struct {
    db *gorm.DB
}

type ResolvedGuidelines struct {
    MergedContent  string              // Final merged content
    Sources        []GuidelineSource   // Contributing guidelines
    Overrides      []OverrideRecord    // What was overridden
    LanguageCode   string
    ProjectID      string
    ResolvedAt     time.Time
}

type GuidelineSource struct {
    GuidelineID string
    Level       GuidelineLevel
    Name        string
    Priority    int
    Sections    []string  // Which sections were included
}

func (r *GuidelineResolver) Resolve(
    projectID string,
    userID string,
    languageCode string,
) (*ResolvedGuidelines, error) {
    
    // 1. Load all applicable guidelines in priority order
    guidelines := r.loadGuidelines(projectID, userID, languageCode)
    
    // 2. Parse into sections
    sectionMap := make(map[string]*SectionContent)
    overrides := []OverrideRecord{}
    sources := []GuidelineSource{}
    
    // 3. Process from lowest to highest priority
    for _, g := range guidelines {
        sections := r.parseSections(g.Content)
        
        for sectionName, content := range sections {
            if existing, ok := sectionMap[sectionName]; ok {
                // Record override
                overrides = append(overrides, OverrideRecord{
                    OverridingID: g.ID,
                    OverriddenID: existing.SourceID,
                    Section:      sectionName,
                    Reason:       fmt.Sprintf("%s overrides %s", g.Level, existing.Level),
                })
            }
            sectionMap[sectionName] = &SectionContent{
                Content:  content,
                SourceID: g.ID,
                Level:    g.Level,
            }
        }
        
        sources = append(sources, GuidelineSource{
            GuidelineID: g.ID,
            Level:       g.Level,
            Name:        g.Name,
            Priority:    g.Priority,
        })
    }
    
    // 4. Merge sections into final content
    merged := r.mergeSections(sectionMap)
    
    return &ResolvedGuidelines{
        MergedContent: merged,
        Sources:       sources,
        Overrides:     overrides,
        LanguageCode:  languageCode,
        ProjectID:     projectID,
        ResolvedAt:    time.Now(),
    }, nil
}

func (r *GuidelineResolver) loadGuidelines(
    projectID, userID, languageCode string,
) []CodingGuideline {
    
    var guidelines []CodingGuideline
    
    // Level 1: General (always loaded)
    r.db.Where("level = ? AND is_active = true", GuidelineLevelGeneral).
        Order("priority ASC").
        Find(&guidelines)
    
    // Level 2: Language-specific
    var langGuidelines []CodingGuideline
    r.db.Where("level = ? AND language_code = ? AND is_active = true", 
        GuidelineLevelLanguage, languageCode).
        Order("priority ASC").
        Find(&langGuidelines)
    guidelines = append(guidelines, langGuidelines...)
    
    // Level 3: User preferences
    var userGuidelines []CodingGuideline
    r.db.Where("level = ? AND user_id = ? AND is_active = true",
        GuidelineLevelUser, userID).
        Order("priority ASC").
        Find(&userGuidelines)
    guidelines = append(guidelines, userGuidelines...)
    
    // Level 4: Project-specific (highest priority)
    var projectGuidelines []CodingGuideline
    r.db.Where("level = ? AND project_id = ? AND is_active = true",
        GuidelineLevelProject, projectID).
        Order("priority ASC").
        Find(&projectGuidelines)
    guidelines = append(guidelines, projectGuidelines...)
    
    return guidelines
}
```

---

## Section Format

Guidelines use Markdown with H2 headers as section identifiers:

```markdown
# Coding Guidelines - Go

## Naming Conventions
- Use camelCase for variables
- Use PascalCase for exported functions
- Use snake_case for file names

## Error Handling
- Always check errors explicitly
- Use wrapped errors with context
- Return early on error

## Testing
- Write table-driven tests
- Use testify for assertions
- Mock external dependencies
```

### Section Matching Rules

1. **Exact Match**: Section names must match exactly (case-insensitive)
2. **Override Behavior**: Later sections completely replace earlier ones
3. **Non-Conflicting Merge**: Sections not present in higher levels are preserved

---

## Default Guidelines (Seeded)

### General Guidelines (Level 1)

```markdown
# General Coding Guidelines

## Code Quality
- Write clean, readable, and maintainable code
- Follow the DRY (Don't Repeat Yourself) principle
- Keep functions focused and single-purpose
- Use meaningful names for variables and functions

## Documentation
- Document all public APIs
- Include usage examples in documentation
- Keep comments up-to-date with code changes

## Error Handling
- Handle all errors explicitly
- Provide meaningful error messages
- Log errors with context for debugging

## Security
- Never commit secrets or credentials
- Validate all user input
- Use parameterized queries for databases
- Follow principle of least privilege
```

### Go Guidelines (Level 2)

```markdown
# Go Coding Guidelines

## Naming Conventions
- Use camelCase for unexported identifiers
- Use PascalCase for exported identifiers
- Use short, descriptive names (prefer `r` over `reader` in small scopes)
- Acronyms should be all caps (HTTPServer, not HttpServer)

## Error Handling
- Check errors immediately after function calls
- Wrap errors with context using fmt.Errorf or errors package
- Define custom error types for domain errors
- Use errors.Is() and errors.As() for error checking

## Struct Design
- Use GORM tags for database mapping
- Group related fields together
- Use pointer receivers for methods that modify state

## Testing
- Use table-driven tests
- Name test functions as Test{FunctionName}_{Scenario}
- Use testify/assert for assertions
- Mock external dependencies with interfaces
```

### React/TypeScript Guidelines (Level 2)

```markdown
# React/TypeScript Coding Guidelines

## Component Structure
- Use functional components with hooks
- Keep components under 200 lines
- Extract reusable logic into custom hooks
- Use named exports for components

## TypeScript
- Enable strict mode
- Define interfaces for all props
- Avoid `any` type - use `unknown` if necessary
- Use generics for reusable components

## State Management
- Use useState for local state
- Use useReducer for complex state
- Lift state only when necessary
- Consider React Query for server state

## Styling
- Use TailwindCSS utility classes
- Create reusable components for common patterns
- Use CSS variables for theming
- Prefer composition over inheritance

## Testing
- Use React Testing Library
- Test behavior, not implementation
- Write integration tests for user flows
- Mock API calls with MSW
```

---

## API Endpoints

### List Guidelines

```
GET /api/v1/guidelines
Query: ?level={level}&language={code}&project_id={id}
Response: { guidelines: CodingGuideline[] }
```

### Get Resolved Guidelines

```
GET /api/v1/guidelines/resolved
Query: ?project_id={id}&language={code}
Response: { resolved: ResolvedGuidelines }
```

### Create/Update Guideline

```
POST /api/v1/guidelines
PUT /api/v1/guidelines/{id}
Body: {
    level: GuidelineLevel,
    language_code?: string,
    project_id?: string,
    name: string,
    content: string,
    priority?: number
}
```

---

## Frontend Components

### GuidelineEditor

```typescript
interface GuidelineEditorProps {
    guideline?: CodingGuideline;
    level: GuidelineLevel;
    projectId?: string;
    languageCode?: string;
    onSave: (guideline: CodingGuideline) => void;
}

// Features:
// - Markdown editor with preview
// - Section highlighting
// - Override preview (shows what this will override)
// - Version history
```

### GuidelinePreview

```typescript
interface GuidelinePreviewProps {
    projectId: string;
    languageCode: string;
}

// Features:
// - Shows merged result
// - Highlights sources (which guideline each section came from)
// - Shows override history
// - Expandable sections
```

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 8100 | ERR_GUIDELINE_NOT_FOUND | Guideline not found |
| 8101 | ERR_GUIDELINE_INVALID_LEVEL | Invalid guideline level |
| 8102 | ERR_GUIDELINE_PARSE_FAILED | Failed to parse guideline sections |
| 8103 | ERR_GUIDELINE_RESOLUTION_FAILED | Failed to resolve guidelines |
| 8104 | ERR_GUIDELINE_CIRCULAR_REF | Circular reference detected |
| 8105 | ERR_GUIDELINE_LANGUAGE_UNSUPPORTED | Language not supported |

---

## Related Specs

- [Architecture](./01-architecture.md)
- [Coding Model Presets](./11-coding-model-presets.md)
- [Data Models](./14-data-models.md)
