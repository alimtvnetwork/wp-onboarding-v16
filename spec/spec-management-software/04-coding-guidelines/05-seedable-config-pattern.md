# 05. Seedable Configuration Pattern

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  
**Parent:** [Coding Guidelines Overview](./00-overview.md)

---

## Purpose

Define the **Seedable Configuration Pattern** — a standardized approach for managing configuration values that:
1. Start with base values from JSON seed files
2. Are seeded to the Settings database on first run or version change
3. Can be modified through the Settings UI at runtime
4. Are accessible throughout the application via the Settings API

---

## Pattern Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                SEEDABLE CONFIGURATION PATTERN                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                 SEED JSON FILES                           │   │
│  │  • config/seeding-models.json                             │   │
│  │  • config/seeding-authority-scores.json                   │   │
│  │  • config/seeding-source-weights.json                     │   │
│  │  • config/seeding-credibility.json                        │   │
│  └──────────────────────────────────────────────────────────┘   │
│                           │                                      │
│                           ▼                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                 VERSION CHECK                             │   │
│  │  • Compare seed file version vs Settings version          │   │
│  │  • If different → Re-seed values                          │   │
│  │  • If same → Skip seeding (preserve user changes)         │   │
│  └──────────────────────────────────────────────────────────┘   │
│                           │                                      │
│                           ▼                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                 SETTINGS DATABASE                         │   │
│  │  • Table: Settings (Key, Value, Category, Version)        │   │
│  │  • Stores runtime-editable values                         │   │
│  │  • Persists user modifications                            │   │
│  └──────────────────────────────────────────────────────────┘   │
│                           │                                      │
│                           ▼                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                 SETTINGS UI                               │   │
│  │  • Admin panel for editing values                         │   │
│  │  • Category-based organization                            │   │
│  │  • Reset to default option                                │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Principles

### 1. Version-Gated Seeding

Seed files include a version identifier. Values are only re-seeded when:
- The seed file version changes
- The category doesn't exist in Settings
- A "force reseed" flag is set

```json
{
  "version": "1.0.0",
  "category": "model_routing",
  "values": {
    "complexity_threshold": 0.7,
    "model_pool": {
      "simple": "Sonar-Small",
      "complex": "GPT-5",
      "code": "Code-Llama-34B",
      "creative": "Claude-Opus"
    }
  }
}
```

### 2. Preserve User Changes

User modifications through the Settings UI are preserved unless:
- Seed file version changes (explicit update)
- User clicks "Reset to Default"
- Migration requires value changes

### 3. Typed Configuration

All configuration values must have explicit types:

```typescript
enum ConfigCategory {
  ModelRouting = "model_routing",
  AuthorityScores = "authority_scores",
  SourceWeights = "source_weights",
  CredibilityThresholds = "credibility_thresholds",
  SearchSettings = "search_settings",
}

interface SeedFile {
  readonly version: string;
  readonly category: ConfigCategory;
  readonly values: Record<string, ConfigValue>;
}

type ConfigValue = 
  | string 
  | number 
  | boolean 
  | readonly string[] 
  | Record<string, string | number>;
```

---

## Seed File Specifications

### Location

All seed files reside in `config/` directory:

```
config/
├── seeding-models.json
├── seeding-authority-scores.json
├── seeding-source-weights.json
├── seeding-credibility.json
├── seeding-search-settings.json
└── seeding-weight-formula.json
```

### File: `seeding-models.json`

```json
{
  "version": "1.0.0",
  "category": "model_routing",
  "values": {
    "complexity_threshold": 0.7,
    "model_pool": {
      "simple": "Sonar-Small",
      "complex": "GPT-5",
      "code": "Code-Llama-34B",
      "creative": "Claude-Opus"
    },
    "category_models": {
      "thinking": ["r1", "o1", "qwen-thinking"],
      "coding": ["codellama", "deepseek", "starcoder"],
      "writing": ["llama-3", "mistral", "claude"],
      "fast": ["llama-3-8b", "mistral-7b"]
    },
    "timeout_seconds": {
      "simple": 10,
      "complex": 60,
      "code": 30,
      "creative": 45
    }
  }
}
```

### File: `seeding-authority-scores.json`

```json
{
  "version": "1.0.0",
  "category": "authority_scores",
  "values": {
    "domain_scores": {
      "scholar.google.com": 0.95,
      "arxiv.org": 0.92,
      "researchgate.net": 0.88,
      ".edu": 0.90,
      "reuters.com": 0.85,
      "bbc.com": 0.82,
      "theguardian.com": 0.80,
      "github.com": 0.88,
      "stackoverflow.com": 0.75,
      "medium.com": 0.40,
      "default": 0.50
    },
    "official_docs_score": 0.95,
    "decay_factor": 0.95
  }
}
```

### File: `seeding-source-weights.json`

```json
{
  "version": "1.0.0",
  "category": "source_weights",
  "values": {
    "weight_formula": {
      "authority_weight": 0.5,
      "recency_weight": 0.3,
      "citations_weight": 0.2
    },
    "recency_decay": {
      "days_half_life": 180,
      "min_score": 0.1
    },
    "citation_normalization": {
      "max_citations": 100,
      "cap_at": 1.0
    }
  }
}
```

### File: `seeding-credibility.json`

```json
{
  "version": "1.0.0",
  "category": "credibility_thresholds",
  "values": {
    "thresholds": {
      "low_max": 0.4,
      "medium_max": 0.7,
      "high_min": 0.7
    },
    "check_weights": {
      "https_enabled": 0.15,
      "domain_authority": 0.25,
      "content_quality": 0.20,
      "citation_count": 0.15,
      "recency": 0.15,
      "author_verified": 0.10
    }
  }
}
```

---

## Database Schema

### Settings Table

```sql
CREATE TABLE Settings (
    Id              TEXT PRIMARY KEY,
    Key             TEXT NOT NULL,
    Value           TEXT NOT NULL,  -- JSON-encoded value
    Category        TEXT NOT NULL,
    Version         TEXT NOT NULL,
    ValueType       TEXT NOT NULL,  -- string, number, boolean, array, object
    Description     TEXT,
    IsUserModified  INTEGER DEFAULT 0,
    CreatedAt       TEXT NOT NULL,
    UpdatedAt       TEXT NOT NULL,
    
    UNIQUE(Key, Category)
);

CREATE INDEX idx_settings_category ON Settings(Category);
CREATE INDEX idx_settings_key ON Settings(Key);
```

### Golang Model

```go
type Setting struct {
    Id             string    `gorm:"primaryKey"`
    Key            string    `gorm:"not null"`
    Value          string    `gorm:"not null"` // JSON-encoded
    Category       string    `gorm:"not null"`
    Version        string    `gorm:"not null"`
    ValueType      string    `gorm:"not null"`
    Description    string
    IsUserModified bool      `gorm:"default:false"`
    CreatedAt      time.Time
    UpdatedAt      time.Time
}

func (Setting) TableName() string {
    return "Settings"
}
```

---

## Seeding Implementation

### Seeder Service

```go
type ConfigSeeder struct {
    db         *gorm.DB
    seedDir    string
}

type SeedFile struct {
    Version  string                 `json:"version"`
    Category string                 `json:"category"`
    Values   map[string]interface{} `json:"values"`
}

func (cs *ConfigSeeder) SeedIfNeeded(filename string) error {
    // Read seed file
    data, err := os.ReadFile(filepath.Join(cs.seedDir, filename))
    if err != nil {
        return fmt.Errorf("failed to read seed file: %w", err)
    }
    
    var seedFile SeedFile
    if err := json.Unmarshal(data, &seedFile); err != nil {
        return fmt.Errorf("failed to parse seed file: %w", err)
    }
    
    // Check existing version
    var existing Setting
    result := cs.db.Where("category = ?", seedFile.Category).
        Order("updated_at DESC").
        First(&existing)
    
    // Skip if version matches
    if result.Error == nil && existing.Version == seedFile.Version {
        log.Printf("Category %s already at version %s, skipping seed", 
            seedFile.Category, seedFile.Version)
        return nil
    }
    
    // Seed values
    return cs.seedValues(seedFile)
}

func (cs *ConfigSeeder) seedValues(seedFile SeedFile) error {
    now := time.Now()
    
    for key, value := range seedFile.Values {
        valueJSON, err := json.Marshal(value)
        if err != nil {
            return err
        }
        
        setting := Setting{
            Id:        uuid.New().String(),
            Key:       key,
            Value:     string(valueJSON),
            Category:  seedFile.Category,
            Version:   seedFile.Version,
            ValueType: detectType(value),
            CreatedAt: now,
            UpdatedAt: now,
        }
        
        // Upsert: insert or update on conflict
        result := cs.db.Clauses(clause.OnConflict{
            Columns:   []clause.Column{{Name: "key"}, {Name: "category"}},
            DoUpdates: clause.AssignmentColumns([]string{"value", "version", "updated_at"}),
        }).Create(&setting)
        
        if result.Error != nil {
            return result.Error
        }
    }
    
    return nil
}

func detectType(value interface{}) string {
    switch value.(type) {
    case string:
        return "string"
    case float64, int:
        return "number"
    case bool:
        return "boolean"
    case []interface{}:
        return "array"
    case map[string]interface{}:
        return "object"
    default:
        return "unknown"
    }
}
```

### Settings Service

```go
type SettingsService struct {
    db    *gorm.DB
    cache sync.Map
}

func (ss *SettingsService) Get(category, key string) (interface{}, error) {
    // Check cache first
    cacheKey := category + ":" + key
    if cached, ok := ss.cache.Load(cacheKey); ok {
        return cached, nil
    }
    
    var setting Setting
    result := ss.db.Where("category = ? AND key = ?", category, key).First(&setting)
    if result.Error != nil {
        return nil, result.Error
    }
    
    var value interface{}
    if err := json.Unmarshal([]byte(setting.Value), &value); err != nil {
        return nil, err
    }
    
    ss.cache.Store(cacheKey, value)
    return value, nil
}

func (ss *SettingsService) GetFloat(category, key string) (float64, error) {
    value, err := ss.Get(category, key)
    if err != nil {
        return 0, err
    }
    
    f, ok := value.(float64)
    if !ok {
        return 0, fmt.Errorf("value is not a float64")
    }
    return f, nil
}

func (ss *SettingsService) GetMap(category, key string) (map[string]interface{}, error) {
    value, err := ss.Get(category, key)
    if err != nil {
        return nil, err
    }
    
    m, ok := value.(map[string]interface{})
    if !ok {
        return nil, fmt.Errorf("value is not a map")
    }
    return m, nil
}

func (ss *SettingsService) Update(category, key string, value interface{}) error {
    valueJSON, err := json.Marshal(value)
    if err != nil {
        return err
    }
    
    result := ss.db.Model(&Setting{}).
        Where("category = ? AND key = ?", category, key).
        Updates(map[string]interface{}{
            "value":            string(valueJSON),
            "is_user_modified": true,
            "updated_at":       time.Now(),
        })
    
    if result.Error != nil {
        return result.Error
    }
    
    // Invalidate cache
    ss.cache.Delete(category + ":" + key)
    return nil
}

func (ss *SettingsService) ResetToDefault(category, key string) error {
    // This would re-read from seed file and update
    // Implementation depends on having access to seed files
    ss.cache.Delete(category + ":" + key)
    return nil
}
```

---

## TypeScript Types

```typescript
enum ConfigCategory {
  ModelRouting = "model_routing",
  AuthorityScores = "authority_scores",
  SourceWeights = "source_weights",
  CredibilityThresholds = "credibility_thresholds",
  SearchSettings = "search_settings",
}

enum ValueType {
  String = "string",
  Number = "number",
  Boolean = "boolean",
  Array = "array",
  Object = "object",
}

interface Setting {
  readonly id: string;
  readonly key: string;
  readonly value: string; // JSON-encoded
  readonly category: ConfigCategory;
  readonly version: string;
  readonly valueType: ValueType;
  readonly description: string | null;
  readonly isUserModified: boolean;
  readonly createdAt: string;
  readonly updatedAt: string;
}

interface SeedFile {
  readonly version: string;
  readonly category: ConfigCategory;
  readonly values: Record<string, ConfigValue>;
}

type ConfigValue =
  | string
  | number
  | boolean
  | readonly string[]
  | Record<string, string | number>;

interface ModelPoolConfig {
  readonly simple: string;
  readonly complex: string;
  readonly code: string;
  readonly creative: string;
}

interface WeightFormula {
  readonly authority_weight: number;
  readonly recency_weight: number;
  readonly citations_weight: number;
}

interface CredibilityThresholds {
  readonly low_max: number;
  readonly medium_max: number;
  readonly high_min: number;
}
```

---

## Usage Examples

### Reading Configuration

```go
// Get complexity threshold
threshold, err := settings.GetFloat("model_routing", "complexity_threshold")
if err != nil {
    return err
}

// Get model pool
modelPool, err := settings.GetMap("model_routing", "model_pool")
if err != nil {
    return err
}

simpleModel := modelPool["simple"].(string) // "Sonar-Small"

// Get authority score for domain
scores, err := settings.GetMap("authority_scores", "domain_scores")
if err != nil {
    return err
}

score := scores["arxiv.org"].(float64) // 0.92
```

### Updating via UI

```typescript
// Update complexity threshold
await settingsService.update(
  ConfigCategory.ModelRouting,
  "complexity_threshold",
  0.8
);

// Update model pool
await settingsService.update(
  ConfigCategory.ModelRouting,
  "model_pool",
  {
    simple: "Llama-3-8B",
    complex: "GPT-5",
    code: "DeepSeek-Coder",
    creative: "Claude-Opus"
  }
);
```

---

## When to Use This Pattern

### Use Seedable Config Pattern For:

| Use Case | Example |
|----------|---------|
| Model selection thresholds | `complexity_threshold: 0.7` |
| Domain authority scores | `arxiv.org: 0.92` |
| Weight formulas | `authority_weight: 0.5` |
| Classification thresholds | `low_max: 0.4` |
| API configurations | `timeout_seconds: 30` |
| Feature flags | `nested_search_enabled: true` |

### Do NOT Use For:

| Avoid For | Reason |
|-----------|--------|
| Secrets/API keys | Use secure secrets management |
| User preferences | Use user-specific settings |
| Session data | Use session storage |
| Transient state | Use in-memory state |

---

## Related

- [TypeScript Guidelines](./03-typescript-guidelines.md) - Type enforcement for configs
- [Go Guidelines](./04-go-guidelines.md) - Golang implementation patterns
- [Database Conventions](./02-database-sql.md) - Settings table design
