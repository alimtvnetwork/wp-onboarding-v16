# 22. SettingsService Implementation Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [GSearch CLI Overview](./00-overview.md)

---

## Purpose

Define the complete Golang implementation specification for the `SettingsService` — a centralized service for managing seedable configuration values with caching, type-safe accessors, version-gated seeding, and runtime modifications.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SETTINGS SERVICE                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                         PUBLIC INTERFACE                                │ │
│  │                                                                         │ │
│  │  Get(category, key) → interface{}                                       │ │
│  │  GetString(category, key) → string                                      │ │
│  │  GetFloat(category, key) → float64                                      │ │
│  │  GetInt(category, key) → int                                            │ │
│  │  GetBool(category, key) → bool                                          │ │
│  │  GetStringSlice(category, key) → []string                               │ │
│  │  GetMap(category, key) → map[string]interface{}                         │ │
│  │  Update(category, key, value) → error                                   │ │
│  │  ResetToDefault(category, key) → error                                  │ │
│  │  SeedFromFile(filepath) → error                                         │ │
│  │  SeedAllFromDirectory(dirpath) → error                                  │ │
│  │  GetByCategory(category) → []Setting                                    │ │
│  │  ExportCategory(category) → SeedFile                                    │ │
│  │                                                                         │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                    │                                         │
│                                    ▼                                         │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                         CACHE LAYER                                     │ │
│  │                                                                         │ │
│  │  sync.Map with TTL-based invalidation                                   │ │
│  │  Key format: "{category}:{key}"                                         │ │
│  │  Automatic cache warming on startup                                     │ │
│  │  Manual invalidation on Update/Reset                                    │ │
│  │                                                                         │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                    │                                         │
│                                    ▼                                         │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                         DATABASE LAYER                                  │ │
│  │                                                                         │ │
│  │  GORM with SQLite                                                       │ │
│  │  Settings table with versioning                                         │ │
│  │  Atomic updates with transactions                                       │ │
│  │                                                                         │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                    │                                         │
│                                    ▼                                         │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                         SEED FILE LOADER                                │ │
│  │                                                                         │ │
│  │  JSON parsing with validation                                           │ │
│  │  Version comparison logic                                               │ │
│  │  Upsert semantics for seeding                                           │ │
│  │                                                                         │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Module Structure

```
gsearch/
├── internal/
│   └── settings/
│       ├── service.go          # SettingsService implementation
│       ├── seeder.go           # ConfigSeeder for seed file processing
│       ├── cache.go            # Cache layer implementation
│       ├── models.go           # Setting model and enums
│       ├── types.go            # ConfigCategory, ValueType enums
│       ├── errors.go           # Custom error types
│       ├── validation.go       # Value validation logic
│       └── service_test.go     # Unit tests
```

---

## Core Interfaces

### SettingsService Interface

```go
// SettingsService provides access to seedable configuration values
type SettingsService interface {
    // Generic accessor - returns raw interface{}
    Get(category ConfigCategory, key string) (interface{}, error)
    
    // Type-safe accessors
    GetString(category ConfigCategory, key string) (string, error)
    GetFloat(category ConfigCategory, key string) (float64, error)
    GetInt(category ConfigCategory, key string) (int, error)
    GetBool(category ConfigCategory, key string) (bool, error)
    GetStringSlice(category ConfigCategory, key string) ([]string, error)
    GetMap(category ConfigCategory, key string) (map[string]interface{}, error)
    
    // Mutation methods
    Update(category ConfigCategory, key string, value interface{}) error
    ResetToDefault(category ConfigCategory, key string) error
    ResetCategoryToDefault(category ConfigCategory) error
    
    // Seeding methods
    SeedFromFile(filepath string) error
    SeedAllFromDirectory(dirpath string) error
    ForceReseed(category ConfigCategory) error
    
    // Query methods
    GetByCategory(category ConfigCategory) ([]Setting, error)
    GetAllCategories() ([]ConfigCategory, error)
    GetCategoryVersion(category ConfigCategory) (string, error)
    
    // Export methods
    ExportCategory(category ConfigCategory) (*SeedFile, error)
    ExportAll() (map[ConfigCategory]*SeedFile, error)
    
    // Cache management
    InvalidateCache() error
    WarmCache() error
}
```

### ConfigSeeder Interface

```go
// ConfigSeeder handles seeding from JSON files
type ConfigSeeder interface {
    // SeedIfNeeded seeds values only if version differs
    SeedIfNeeded(filepath string) error
    
    // ForceSeed seeds values regardless of version
    ForceSeed(filepath string) error
    
    // SeedDirectoryIfNeeded seeds all files in directory
    SeedDirectoryIfNeeded(dirpath string) error
    
    // GetSeedFileVersion returns version from seed file without seeding
    GetSeedFileVersion(filepath string) (string, error)
    
    // ValidateSeedFile validates JSON structure
    ValidateSeedFile(filepath string) error
}
```

---

## Data Models

### Setting Model

```go
type Setting struct {
    Id             string         `gorm:"primaryKey;size:36"`
    Key            string         `gorm:"not null;size:255"`
    Value          string         `gorm:"not null;type:text"` // JSON-encoded
    Category       ConfigCategory `gorm:"not null;size:50"`
    Version        string         `gorm:"not null;size:20"`
    ValueType      ValueType      `gorm:"not null;size:20"`
    Description    string         `gorm:"size:500"`
    IsUserModified bool           `gorm:"default:false"`
    DefaultValue   string         `gorm:"type:text"` // Original seed value
    CreatedAt      time.Time
    UpdatedAt      time.Time
}

func (Setting) TableName() string {
    return "Settings"
}

// Indexes for efficient queries
func (Setting) Indexes() []schema.Index {
    return []schema.Index{
        {Fields: []string{"category"}},
        {Fields: []string{"key", "category"}, Unique: true},
    }
}
```

### SeedFile Model

```go
type SeedFile struct {
    Version     string                 `json:"version"`
    Category    ConfigCategory         `json:"category"`
    Description string                 `json:"description,omitempty"`
    Values      map[string]interface{} `json:"values"`
}
```

### Enumerations

```go
type ConfigCategory string

const (
    CategoryModelRouting          ConfigCategory = "model_routing"
    CategoryAuthorityScores       ConfigCategory = "authority_scores"
    CategorySourceWeights         ConfigCategory = "source_weights"
    CategoryCredibilityThresholds ConfigCategory = "credibility_thresholds"
    CategoryConfidenceMetrics     ConfigCategory = "confidence_metrics"
    CategoryTrendAnalysis         ConfigCategory = "trend_analysis"
    CategorySearchSettings        ConfigCategory = "search_settings"
    CategoryCacheSettings         ConfigCategory = "cache_settings"
)

// AllCategories returns all valid categories
func AllCategories() []ConfigCategory {
    return []ConfigCategory{
        CategoryModelRouting,
        CategoryAuthorityScores,
        CategorySourceWeights,
        CategoryCredibilityThresholds,
        CategoryConfidenceMetrics,
        CategoryTrendAnalysis,
        CategorySearchSettings,
        CategoryCacheSettings,
    }
}

type ValueType string

const (
    ValueTypeString  ValueType = "string"
    ValueTypeNumber  ValueType = "number"
    ValueTypeBoolean ValueType = "boolean"
    ValueTypeArray   ValueType = "array"
    ValueTypeObject  ValueType = "object"
)
```

---

## Method Specifications

### Get Method

```go
// Get retrieves a setting value by category and key
// Returns the JSON-decoded value or error if not found
//
// Behavior:
// 1. Check cache for existing value
// 2. If cache miss, query database
// 3. Decode JSON value to interface{}
// 4. Store in cache for future access
// 5. Return decoded value
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist in category
// - ErrCategoryNotFound: Category doesn't exist
// - ErrValueDecodeFailed: JSON decode failed
func (ss *SettingsServiceImpl) Get(category ConfigCategory, key string) (interface{}, error)
```

### GetFloat Method

```go
// GetFloat retrieves a numeric setting as float64
// Handles both integer and float JSON values
//
// Type Coercion:
// - JSON number → float64 (direct)
// - JSON string containing number → parsed float64
// - Other types → ErrTypeMismatch
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist
// - ErrTypeMismatch: Value is not numeric
func (ss *SettingsServiceImpl) GetFloat(category ConfigCategory, key string) (float64, error)
```

### GetInt Method

```go
// GetInt retrieves a numeric setting as int
// Truncates decimal values
//
// Type Coercion:
// - JSON number → int (truncated)
// - JSON string containing integer → parsed int
// - Other types → ErrTypeMismatch
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist
// - ErrTypeMismatch: Value is not numeric
// - ErrIntegerOverflow: Value exceeds int range
func (ss *SettingsServiceImpl) GetInt(category ConfigCategory, key string) (int, error)
```

### GetString Method

```go
// GetString retrieves a string setting
//
// Type Coercion:
// - JSON string → string (direct)
// - JSON number/bool → formatted string
// - Other types → ErrTypeMismatch
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist
// - ErrTypeMismatch: Value cannot be represented as string
func (ss *SettingsServiceImpl) GetString(category ConfigCategory, key string) (string, error)
```

### GetBool Method

```go
// GetBool retrieves a boolean setting
//
// Type Coercion:
// - JSON bool → bool (direct)
// - JSON string "true"/"false" → parsed bool
// - JSON number 0/1 → false/true
// - Other values → ErrTypeMismatch
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist
// - ErrTypeMismatch: Value is not boolean-coercible
func (ss *SettingsServiceImpl) GetBool(category ConfigCategory, key string) (bool, error)
```

### GetStringSlice Method

```go
// GetStringSlice retrieves an array setting as []string
//
// Type Coercion:
// - JSON array of strings → []string (direct)
// - JSON array of mixed types → each element stringified
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist
// - ErrTypeMismatch: Value is not an array
func (ss *SettingsServiceImpl) GetStringSlice(category ConfigCategory, key string) ([]string, error)
```

### GetMap Method

```go
// GetMap retrieves an object setting as map[string]interface{}
//
// Type Handling:
// - JSON object → map[string]interface{} (direct)
// - Nested objects preserved as map[string]interface{}
// - Arrays preserved as []interface{}
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist
// - ErrTypeMismatch: Value is not an object
func (ss *SettingsServiceImpl) GetMap(category ConfigCategory, key string) (map[string]interface{}, error)
```

### Update Method

```go
// Update modifies a setting value at runtime
//
// Behavior:
// 1. Validate value type matches existing ValueType
// 2. JSON-encode the new value
// 3. Update database with transaction
// 4. Set IsUserModified = true
// 5. Invalidate cache entry
// 6. Return nil on success
//
// Validation:
// - Value must be JSON-serializable
// - Value type must match original ValueType (or be coercible)
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist
// - ErrTypeMismatch: Value type doesn't match setting type
// - ErrValidationFailed: Value fails validation rules
// - ErrDatabaseError: Database update failed
func (ss *SettingsServiceImpl) Update(category ConfigCategory, key string, value interface{}) error
```

### ResetToDefault Method

```go
// ResetToDefault restores a setting to its original seed value
//
// Behavior:
// 1. Retrieve DefaultValue from database
// 2. Update Value = DefaultValue
// 3. Set IsUserModified = false
// 4. Invalidate cache entry
// 5. Return nil on success
//
// Errors:
// - ErrSettingNotFound: Key doesn't exist
// - ErrNoDefaultValue: DefaultValue is empty (shouldn't happen)
// - ErrDatabaseError: Database update failed
func (ss *SettingsServiceImpl) ResetToDefault(category ConfigCategory, key string) error
```

### ResetCategoryToDefault Method

```go
// ResetCategoryToDefault restores all settings in a category
//
// Behavior:
// 1. Query all settings in category
// 2. For each setting, restore Value = DefaultValue
// 3. Set IsUserModified = false for all
// 4. Invalidate all cache entries for category
// 5. Return nil on success
//
// Errors:
// - ErrCategoryNotFound: Category doesn't exist
// - ErrDatabaseError: Database update failed
func (ss *SettingsServiceImpl) ResetCategoryToDefault(category ConfigCategory) error
```

### SeedFromFile Method

```go
// SeedFromFile loads and seeds configuration from a JSON file
//
// Behavior:
// 1. Read and parse JSON file
// 2. Validate seed file structure
// 3. Check version against existing category version
// 4. If version differs OR category empty → seed all values
// 5. If version matches → skip (preserve user changes)
// 6. Return nil on success
//
// Seeding Logic:
// - Uses UPSERT semantics (insert or update on conflict)
// - Stores original value in DefaultValue field
// - Sets IsUserModified = false for new seeds
//
// Errors:
// - ErrFileNotFound: File doesn't exist
// - ErrInvalidSeedFile: JSON parse or validation failed
// - ErrDatabaseError: Database operation failed
func (ss *SettingsServiceImpl) SeedFromFile(filepath string) error
```

### SeedAllFromDirectory Method

```go
// SeedAllFromDirectory seeds all JSON files in a directory
//
// Behavior:
// 1. List all *.json files matching "seeding-*.json" pattern
// 2. For each file, call SeedFromFile
// 3. Continue on individual file errors (log and track)
// 4. Return aggregate error if any files failed
//
// File Pattern: seeding-*.json
//
// Errors:
// - ErrDirectoryNotFound: Directory doesn't exist
// - ErrPartialSeedFailure: Some files failed (details in error)
func (ss *SettingsServiceImpl) SeedAllFromDirectory(dirpath string) error
```

### ForceReseed Method

```go
// ForceReseed re-seeds a category regardless of version
//
// Behavior:
// 1. Find seed file for category
// 2. Parse seed file
// 3. Delete all existing settings in category
// 4. Seed all values from file
// 5. Invalidate all cache entries for category
//
// Use Cases:
// - Recovery from corrupted settings
// - Admin-initiated reset to defaults
//
// Errors:
// - ErrCategoryNotFound: Category not valid
// - ErrSeedFileNotFound: No seed file for category
// - ErrDatabaseError: Database operation failed
func (ss *SettingsServiceImpl) ForceReseed(category ConfigCategory) error
```

### GetByCategory Method

```go
// GetByCategory returns all settings in a category
//
// Returns:
// - Slice of Setting structs
// - Settings are ordered by Key alphabetically
//
// Errors:
// - ErrCategoryNotFound: Category doesn't exist
func (ss *SettingsServiceImpl) GetByCategory(category ConfigCategory) ([]Setting, error)
```

### ExportCategory Method

```go
// ExportCategory generates a SeedFile from current category values
//
// Use Cases:
// - Backup current configuration
// - Generate updated seed file after UI modifications
// - Migrate settings between environments
//
// Returns:
// - SeedFile with current version, category, and all values
//
// Errors:
// - ErrCategoryNotFound: Category doesn't exist
func (ss *SettingsServiceImpl) ExportCategory(category ConfigCategory) (*SeedFile, error)
```

---

## Cache Implementation

### Cache Structure

```go
type SettingsCache struct {
    data       sync.Map
    ttl        time.Duration
    timestamps sync.Map // Tracks entry creation time
}

type cacheEntry struct {
    value     interface{}
    expiresAt time.Time
}
```

### Cache Methods

```go
// NewSettingsCache creates a cache with specified TTL
func NewSettingsCache(ttl time.Duration) *SettingsCache

// Get retrieves value from cache, returns nil if expired or missing
func (c *SettingsCache) Get(category ConfigCategory, key string) (interface{}, bool)

// Set stores value in cache with TTL
func (c *SettingsCache) Set(category ConfigCategory, key string, value interface{})

// Delete removes a specific entry
func (c *SettingsCache) Delete(category ConfigCategory, key string)

// DeleteCategory removes all entries for a category
func (c *SettingsCache) DeleteCategory(category ConfigCategory)

// Clear removes all entries
func (c *SettingsCache) Clear()

// WarmFromDB loads all settings into cache
func (c *SettingsCache) WarmFromDB(db *gorm.DB) error
```

### Cache Key Format

```
{category}:{key}

Examples:
- model_routing:complexity_threshold
- confidence_metrics:weights
- trend_analysis:composite_score_weights
```

---

## Error Types

```go
var (
    ErrSettingNotFound     = errors.New("setting not found")
    ErrCategoryNotFound    = errors.New("category not found")
    ErrTypeMismatch        = errors.New("value type mismatch")
    ErrValueDecodeFailed   = errors.New("failed to decode value")
    ErrValidationFailed    = errors.New("validation failed")
    ErrDatabaseError       = errors.New("database operation failed")
    ErrFileNotFound        = errors.New("file not found")
    ErrInvalidSeedFile     = errors.New("invalid seed file format")
    ErrSeedFileNotFound    = errors.New("seed file not found for category")
    ErrPartialSeedFailure  = errors.New("some seed files failed")
    ErrNoDefaultValue      = errors.New("no default value stored")
    ErrIntegerOverflow     = errors.New("integer value overflow")
    ErrCacheWarmFailed     = errors.New("cache warming failed")
)

// SettingsError wraps errors with additional context
type SettingsError struct {
    Op       string         // Operation that failed
    Category ConfigCategory // Category involved
    Key      string         // Key involved (optional)
    Err      error          // Underlying error
}

func (e *SettingsError) Error() string {
    if e.Key != "" {
        return fmt.Sprintf("%s failed for %s:%s: %v", e.Op, e.Category, e.Key, e.Err)
    }
    return fmt.Sprintf("%s failed for category %s: %v", e.Op, e.Category, e.Err)
}

func (e *SettingsError) Unwrap() error {
    return e.Err
}
```

---

## Initialization Flow

```go
// InitializeSettings sets up the settings service with seeding
func InitializeSettings(db *gorm.DB, seedDir string) (*SettingsServiceImpl, error) {
    // 1. Auto-migrate Settings table
    if err := db.AutoMigrate(&Setting{}); err != nil {
        return nil, fmt.Errorf("failed to migrate Settings table: %w", err)
    }
    
    // 2. Create service with cache
    cache := NewSettingsCache(5 * time.Minute)
    service := &SettingsServiceImpl{
        db:      db,
        cache:   cache,
        seedDir: seedDir,
    }
    
    // 3. Seed from all seed files
    if err := service.SeedAllFromDirectory(seedDir); err != nil {
        // Log warning but don't fail - partial seeding is acceptable
        log.Printf("Warning: partial seed failure: %v", err)
    }
    
    // 4. Warm cache
    if err := cache.WarmFromDB(db); err != nil {
        log.Printf("Warning: cache warming failed: %v", err)
    }
    
    return service, nil
}
```

---

## Configuration Categories Mapping

| Category | Seed File | Description |
|----------|-----------|-------------|
| `model_routing` | `seeding-models.json` | LLM thresholds, model pools, timeouts |
| `authority_scores` | `seeding-authority-scores.json` | Domain authority values |
| `source_weights` | `seeding-source-weights.json` | Weight formula coefficients |
| `credibility_thresholds` | `seeding-credibility.json` | Classification thresholds |
| `confidence_metrics` | `seeding-confidence-metrics.json` | Confidence analysis weights |
| `trend_analysis` | `seeding-trend-analysis.json` | Trend composite scoring |
| `search_settings` | `seeding-search-settings.json` | Search behavior settings |
| `cache_settings` | `seeding-cache-settings.json` | Cache TTL and limits |

---

## Usage Examples

### Basic Usage

```go
// Initialize service
service, err := InitializeSettings(db, "./config")
if err != nil {
    log.Fatal(err)
}

// Get a float value
threshold, err := service.GetFloat(CategoryModelRouting, "complexity_threshold")
if err != nil {
    log.Printf("Error: %v", err)
}

// Get a map value
weights, err := service.GetMap(CategoryConfidenceMetrics, "weights")
if err != nil {
    log.Printf("Error: %v", err)
}
sourceAgreement := weights["source_agreement"].(float64)

// Update a value at runtime
err = service.Update(CategoryTrendAnalysis, "top_n_results", 15)
if err != nil {
    log.Printf("Update failed: %v", err)
}

// Reset to seed default
err = service.ResetToDefault(CategoryTrendAnalysis, "top_n_results")
```

### Integration with TrendAnalyzer

```go
func NewTrendAnalyzer(settings SettingsService) *TrendAnalyzer {
    // Load configuration from settings
    weights, _ := settings.GetMap(CategoryTrendAnalysis, "composite_score_weights")
    normalization, _ := settings.GetMap(CategoryTrendAnalysis, "signal_normalization")
    
    return &TrendAnalyzer{
        settings: settings,
        weights: TrendWeights{
            GitHubStars:          weights["github_stars"].(float64),
            JobPostings:          weights["job_postings"].(float64),
            StackOverflowQuestions: weights["stackoverflow_questions"].(float64),
            PackageDownloads:     weights["package_downloads"].(float64),
        },
        normalization: normalization,
    }
}
```

### Integration with ConfidenceAnalyzer

```go
func NewConfidenceAnalyzer(settings SettingsService) *ConfidenceAnalyzer {
    weights, _ := settings.GetMap(CategoryConfidenceMetrics, "weights")
    thresholds, _ := settings.GetMap(CategoryConfidenceMetrics, "thresholds")
    
    return &ConfidenceAnalyzer{
        settings: settings,
        weights: ConfidenceWeights{
            SourceAgreement:       weights["source_agreement"].(float64),
            DataFreshness:         weights["data_freshness"].(float64),
            SourceCountConfidence: weights["source_count_confidence"].(float64),
            AuthorityDiversity:    weights["authority_diversity"].(float64),
        },
        thresholds: thresholds,
    }
}
```

---

## Testing Strategy

### Unit Tests

```go
func TestSettingsService_GetFloat(t *testing.T) {
    // Setup test DB and seed
    db := setupTestDB(t)
    service := setupService(t, db)
    
    // Test valid float
    value, err := service.GetFloat(CategoryModelRouting, "complexity_threshold")
    assert.NoError(t, err)
    assert.Equal(t, 0.7, value)
    
    // Test missing key
    _, err = service.GetFloat(CategoryModelRouting, "nonexistent")
    assert.ErrorIs(t, err, ErrSettingNotFound)
    
    // Test type mismatch
    _, err = service.GetFloat(CategoryModelRouting, "model_pool")
    assert.ErrorIs(t, err, ErrTypeMismatch)
}

func TestSettingsService_Update(t *testing.T) {
    db := setupTestDB(t)
    service := setupService(t, db)
    
    // Update value
    err := service.Update(CategoryModelRouting, "complexity_threshold", 0.8)
    assert.NoError(t, err)
    
    // Verify update
    value, _ := service.GetFloat(CategoryModelRouting, "complexity_threshold")
    assert.Equal(t, 0.8, value)
    
    // Verify IsUserModified flag
    settings, _ := service.GetByCategory(CategoryModelRouting)
    for _, s := range settings {
        if s.Key == "complexity_threshold" {
            assert.True(t, s.IsUserModified)
        }
    }
}

func TestSettingsService_ResetToDefault(t *testing.T) {
    db := setupTestDB(t)
    service := setupService(t, db)
    
    // Modify then reset
    service.Update(CategoryModelRouting, "complexity_threshold", 0.9)
    err := service.ResetToDefault(CategoryModelRouting, "complexity_threshold")
    assert.NoError(t, err)
    
    // Verify reset
    value, _ := service.GetFloat(CategoryModelRouting, "complexity_threshold")
    assert.Equal(t, 0.7, value) // Original seed value
}

func TestSettingsService_SeedFromFile(t *testing.T) {
    db := setupTestDB(t)
    service := &SettingsServiceImpl{db: db, cache: NewSettingsCache(5*time.Minute)}
    
    // Seed from file
    err := service.SeedFromFile("./testdata/seeding-test.json")
    assert.NoError(t, err)
    
    // Verify seeded values
    value, _ := service.GetFloat(CategoryModelRouting, "complexity_threshold")
    assert.Equal(t, 0.7, value)
}
```

---

## Related Specifications

- [Seedable Config Pattern](../../04-coding-guidelines/05-seedable-config-pattern.md)
- [TrendAnalyzer Implementation](./21-trend-analyzer-implementation.md)
- [Authority & Credibility Scoring](./19-authority-credibility-scoring.md)
- [Settings UI Page](./23-settings-ui-page.md)
