# Coding Model Presets

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Coding Model Presets define selectable AI model configurations for code generation. The system supports multiple coding model categories with language-specific and purpose-specific presets.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Coding Guidelines Hierarchy](./02-guideline-hierarchy.md)
- [Model Categories](../06-ai-integration/05-model-categories.md)

---

## Model Categories

### Coding-Specific Categories

```go
const (
    // Primary coding models
    ModelCategoryCoding1 = "coding1"  // Primary/default coding model
    ModelCategoryCoding2 = "coding2"  // Secondary/alternative model
    
    // Language-specific coding models
    ModelCategoryCodingGo     = "coding_go"
    ModelCategoryCodingReact  = "coding_react"
    ModelCategoryCodingPHP    = "coding_php"
    ModelCategoryCodingPython = "coding_python"
    ModelCategoryCodingRust   = "coding_rust"
    
    // Purpose-specific models
    ModelCategoryCodingFix    = "coding_fix"     // For build error fixes
    ModelCategoryCodingRefactor = "coding_refactor"  // For refactoring
)
```

### Category Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                    MODEL SELECTION HIERARCHY                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Request: Generate Go code                                       │
│                                                                  │
│  1. Check: Language-specific model (coding_go)                  │
│     └── If exists → Use coding_go                               │
│                                                                  │
│  2. Check: Primary coding model (coding1)                       │
│     └── If exists → Use coding1                                 │
│                                                                  │
│  3. Check: General writing model (writing)                      │
│     └── If exists → Use writing (fallback)                      │
│                                                                  │
│  4. Error: No suitable model found                              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Data Models

### CodingModelPreset

```go
type CodingModelPreset struct {
    ID              string    `gorm:"primaryKey;type:text"`
    Name            string    `gorm:"type:text;not null"`
    Description     string    `gorm:"type:text"`
    Category        string    `gorm:"type:text;not null;index"`  // coding1, coding_go, etc.
    ModelPath       string    `gorm:"type:text;not null"`        // Path to model or API endpoint
    ModelType       string    `gorm:"type:text;not null"`        // ollama, llama, openai, etc.
    Languages       string    `gorm:"type:text"`                 // JSON array of supported languages
    ContextWindow   int       `gorm:"type:integer;default:8192"`
    MaxOutputTokens int       `gorm:"type:integer;default:4096"`
    Temperature     float64   `gorm:"type:real;default:0.2"`
    TopP            float64   `gorm:"type:real;default:0.9"`
    SystemPrompt    string    `gorm:"type:text"`
    IsDefault       bool      `gorm:"type:boolean;default:false"`
    IsActive        bool      `gorm:"type:boolean;default:true"`
    Priority        int       `gorm:"type:integer;default:0"`     // Higher = preferred
    CreatedAt       time.Time
    UpdatedAt       time.Time
}
```

### UserModelPreference

```go
type UserModelPreference struct {
    ID              string    `gorm:"primaryKey;type:text"`
    UserID          string    `gorm:"type:text;not null;index"`
    Category        string    `gorm:"type:text;not null"`
    PresetID        string    `gorm:"type:text;not null"`
    CreatedAt       time.Time
    UpdatedAt       time.Time
    
    // Relationships
    User            User              `gorm:"foreignKey:UserID"`
    Preset          CodingModelPreset `gorm:"foreignKey:PresetID"`
}
```

### ProjectModelOverride

```go
type ProjectModelOverride struct {
    ID              string    `gorm:"primaryKey;type:text"`
    ProjectID       string    `gorm:"type:text;not null;index"`
    Category        string    `gorm:"type:text;not null"`
    PresetID        string    `gorm:"type:text;not null"`
    CreatedAt       time.Time
    UpdatedAt       time.Time
    
    // Relationships
    Project         Project           `gorm:"foreignKey:ProjectID"`
    Preset          CodingModelPreset `gorm:"foreignKey:PresetID"`
}
```

---

## Model Selector

### Resolution Logic

```go
type ModelSelector struct {
    presetRepo     *CodingModelPresetRepository
    userPrefRepo   *UserModelPreferenceRepository
    projectOverride *ProjectModelOverrideRepository
}

type ModelSelectionContext struct {
    UserID      string
    ProjectID   string
    Language    string
    Purpose     string  // "generate", "fix", "refactor"
}

// Resolution order (first match wins):
// 1. Project override for specific language
// 2. Project override for category
// 3. User preference for specific language
// 4. User preference for category
// 5. System default for specific language
// 6. System default for category (coding1)
// 7. Fallback to writing model

func (s *ModelSelector) SelectModel(ctx ModelSelectionContext) (*CodingModelPreset, error) {
    // Build category list to check
    categories := s.buildCategoryList(ctx)
    
    for _, category := range categories {
        // Check project override
        if ctx.ProjectID != "" {
            if preset := s.projectOverride.Get(ctx.ProjectID, category); preset != nil {
                return preset, nil
            }
        }
        
        // Check user preference
        if ctx.UserID != "" {
            if preset := s.userPrefRepo.Get(ctx.UserID, category); preset != nil {
                return preset, nil
            }
        }
        
        // Check system default
        if preset := s.presetRepo.GetDefault(category); preset != nil {
            return preset, nil
        }
    }
    
    return nil, ErrNoModelFound
}

func (s *ModelSelector) buildCategoryList(ctx ModelSelectionContext) []string {
    categories := []string{}
    
    // Language-specific category first
    if ctx.Language != "" {
        langCategory := fmt.Sprintf("coding_%s", ctx.Language)
        categories = append(categories, langCategory)
    }
    
    // Purpose-specific category
    if ctx.Purpose == "fix" {
        categories = append(categories, ModelCategoryCodingFix)
    } else if ctx.Purpose == "refactor" {
        categories = append(categories, ModelCategoryCodingRefactor)
    }
    
    // Primary coding categories
    categories = append(categories, ModelCategoryCoding1, ModelCategoryCoding2)
    
    // Fallback
    categories = append(categories, "writing")
    
    return categories
}
```

---

## Default Presets (Seeded)

### Primary Coding Models

```go
var defaultCodingPresets = []CodingModelPreset{
    {
        ID:              "preset_coding1_default",
        Name:            "DeepSeek Coder V2",
        Description:     "Primary coding model - excellent for general code generation",
        Category:        ModelCategoryCoding1,
        ModelPath:       "deepseek-coder-v2:16b",
        ModelType:       "ollama",
        Languages:       `["go", "typescript", "python", "rust", "java"]`,
        ContextWindow:   32768,
        MaxOutputTokens: 8192,
        Temperature:     0.2,
        SystemPrompt:    "You are an expert software engineer...",
        IsDefault:       true,
        Priority:        100,
    },
    {
        ID:              "preset_coding2_default",
        Name:            "CodeLlama 34B",
        Description:     "Alternative coding model - good for complex logic",
        Category:        ModelCategoryCoding2,
        ModelPath:       "codellama:34b",
        ModelType:       "ollama",
        Languages:       `["go", "typescript", "python", "c", "cpp"]`,
        ContextWindow:   16384,
        MaxOutputTokens: 4096,
        Temperature:     0.3,
        IsDefault:       true,
        Priority:        90,
    },
}
```

### Language-Specific Models

```go
var languageSpecificPresets = []CodingModelPreset{
    {
        ID:              "preset_coding_go_default",
        Name:            "Go Specialist",
        Description:     "Optimized for Go code generation",
        Category:        ModelCategoryCodingGo,
        ModelPath:       "deepseek-coder-v2:16b",
        ModelType:       "ollama",
        Languages:       `["go"]`,
        ContextWindow:   32768,
        MaxOutputTokens: 8192,
        Temperature:     0.1,
        SystemPrompt: `You are an expert Go developer following idiomatic Go patterns.
Always:
- Use proper error handling with wrapped errors
- Follow Go naming conventions
- Write table-driven tests
- Use interfaces for dependencies`,
        IsDefault:       true,
        Priority:        100,
    },
    {
        ID:              "preset_coding_react_default",
        Name:            "React/TypeScript Specialist",
        Description:     "Optimized for React and TypeScript",
        Category:        ModelCategoryCodingReact,
        ModelPath:       "deepseek-coder-v2:16b",
        ModelType:       "ollama",
        Languages:       `["typescript", "javascript", "tsx", "jsx"]`,
        ContextWindow:   32768,
        MaxOutputTokens: 8192,
        Temperature:     0.2,
        SystemPrompt: `You are an expert React developer using TypeScript.
Always:
- Use functional components with hooks
- Define proper TypeScript interfaces
- Follow React best practices
- Use TailwindCSS for styling`,
        IsDefault:       true,
        Priority:        100,
    },
}
```

### Purpose-Specific Models

```go
var purposeSpecificPresets = []CodingModelPreset{
    {
        ID:              "preset_coding_fix_default",
        Name:            "Error Fixer",
        Description:     "Specialized for fixing build errors",
        Category:        ModelCategoryCodingFix,
        ModelPath:       "deepseek-coder-v2:16b",
        ModelType:       "ollama",
        ContextWindow:   16384,
        MaxOutputTokens: 4096,
        Temperature:     0.1,  // Lower temperature for precise fixes
        SystemPrompt: `You are a code error specialist.
Your task is to fix build errors while:
- Making minimal changes to fix the error
- Not changing any functionality
- Preserving code style and formatting
- Explaining what you fixed in comments if needed`,
        IsDefault:       true,
        Priority:        100,
    },
}
```

---

## Preset Editor

### API Endpoints

```
GET /api/v1/codegen/presets
Query: ?category={category}
Response: { presets: CodingModelPreset[] }

GET /api/v1/codegen/presets/{id}
Response: { preset: CodingModelPreset }

POST /api/v1/codegen/presets
Body: CodingModelPreset
Response: { preset: CodingModelPreset }

PUT /api/v1/codegen/presets/{id}
Body: Partial<CodingModelPreset>
Response: { preset: CodingModelPreset }

DELETE /api/v1/codegen/presets/{id}
Response: { success: true }

POST /api/v1/codegen/presets/{id}/test
Body: { prompt: string }
Response: { output: string, tokens: int, duration: float }
```

### User Preference Endpoints

```
GET /api/v1/users/{userId}/model-preferences
Response: { preferences: UserModelPreference[] }

PUT /api/v1/users/{userId}/model-preferences/{category}
Body: { preset_id: string }
Response: { preference: UserModelPreference }

DELETE /api/v1/users/{userId}/model-preferences/{category}
Response: { success: true }
```

### Project Override Endpoints

```
GET /api/v1/projects/{projectId}/model-overrides
Response: { overrides: ProjectModelOverride[] }

PUT /api/v1/projects/{projectId}/model-overrides/{category}
Body: { preset_id: string }
Response: { override: ProjectModelOverride }

DELETE /api/v1/projects/{projectId}/model-overrides/{category}
Response: { success: true }
```

---

## Frontend Components

### PresetSelector

```typescript
interface PresetSelectorProps {
    category: string;
    projectId?: string;
    value?: string;
    onChange: (presetId: string) => void;
}

// Features:
// - Dropdown with preset options
// - Shows current selection source (project, user, system)
// - Quick preview of preset settings
// - Link to preset editor
```

### PresetEditor

```typescript
interface PresetEditorProps {
    presetId?: string;  // null for new preset
    onSave: (preset: CodingModelPreset) => void;
}

// Features:
// - Form for all preset fields
// - System prompt editor with syntax highlighting
// - Test panel to try the preset
// - Language multi-select
// - Temperature/TopP sliders
```

### ModelPreferencesPanel

```typescript
interface ModelPreferencesPanelProps {
    level: 'user' | 'project';
    entityId: string;  // userId or projectId
}

// Features:
// - List of all categories
// - Current selection per category
// - Override/reset buttons
// - Preview of resolved model per language
```

---

## System Prompt Templates

### Base System Prompt

```markdown
You are an expert software engineer generating production-quality code.

## Core Principles
1. Write clean, readable, and maintainable code
2. Follow the coding guidelines provided
3. Use proper error handling
4. Include necessary imports
5. Add documentation for public APIs

## Output Format
- Return ONLY code wrapped in a code block
- Do not include explanations unless asked
- Use the appropriate language syntax highlighting
```

### Language-Specific Additions

#### Go

```markdown
## Go-Specific Guidelines
- Use idiomatic Go patterns
- Handle errors explicitly with wrapped context
- Use interfaces for dependencies
- Follow standard project layout
- Write table-driven tests
```

#### React/TypeScript

```markdown
## React/TypeScript Guidelines
- Use functional components with hooks
- Define TypeScript interfaces for all props
- Use proper dependency arrays in hooks
- Follow component composition patterns
- Use TailwindCSS utility classes
```

---

## Configuration

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `codegen.model.defaultCategory` | string | `coding1` | Default model category |
| `codegen.model.fallbackToWriting` | bool | true | Fall back to writing model if no coding model |
| `codegen.model.maxTokensMultiplier` | float | 1.5 | Multiplier for max output tokens |
| `codegen.model.temperatureRange.min` | float | 0.0 | Minimum allowed temperature |
| `codegen.model.temperatureRange.max` | float | 1.0 | Maximum allowed temperature |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 8150 | ERR_MODEL_PRESET_NOT_FOUND | Model preset not found |
| 8151 | ERR_MODEL_CATEGORY_INVALID | Invalid model category |
| 8152 | ERR_MODEL_NO_SUITABLE | No suitable model found |
| 8153 | ERR_MODEL_PRESET_DUPLICATE | Duplicate preset name |
| 8154 | ERR_MODEL_TEST_FAILED | Preset test failed |

---

## Related Specs

- [Architecture](./01-architecture.md)
- [Coding Guidelines Hierarchy](./02-coding-guidelines-hierarchy.md)
- [Model Categories](../06-ai-integration/05-model-categories.md)
