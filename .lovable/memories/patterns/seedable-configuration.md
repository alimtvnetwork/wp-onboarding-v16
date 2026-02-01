# Memory: patterns/seedable-configuration

**Updated:** 2026-01-30  
**Purpose:** AI Training Document — Seedable Configuration Pattern  
**Spec Location:** `spec/spec-management-software/04-coding-guidelines/05-seedable-config-pattern.md`

---

## Overview

The **Seedable Configuration Pattern** manages application parameters (weights, thresholds, model pools, defaults) using versioned seed files. Values are seeded into `settings.db` on first app start or version upgrade, but **never overwrite user modifications**.

---

## The Golden Rule (CRITICAL)

```
┌────────────────────────────────────────────────────────────────────────────┐
│                         SEEDING CONDITIONS                                  │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  SEED IF:                                                                    │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │ 1. Key does NOT exist in database               → INSERT (first run)  │ │
│  │ 2. Key exists AND IsUserModified = FALSE                               │ │
│  │    AND seed_version > stored_version            → UPDATE (upgrade)    │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  DO NOT SEED IF:                                                             │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │ IsUserModified = TRUE                           → NEVER OVERWRITE      │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## Pattern Flow

```
App Start → Load Seed Files → Check Each Key → Seed If Needed → Settings DB
                                    ↓
                              Settings UI (user can modify)
                                    ↓
                              IsUserModified = TRUE (on change)
                                    ↓
                              Future seeds skip this key
```

---

## Database Schema (in settings.db)

```sql
CREATE TABLE SeedableConfig (
    Id TEXT PRIMARY KEY,
    Key TEXT NOT NULL UNIQUE,          -- e.g., "search.weights.github_stars"
    Value TEXT NOT NULL,               -- JSON-encoded value
    Category TEXT NOT NULL,            -- weights, thresholds, models, features
    Description TEXT,                  -- Human-readable description
    ValueType TEXT NOT NULL,           -- number, string, boolean, object, array
    ValidationRule TEXT,               -- JSON constraint
    Version TEXT NOT NULL,             -- Seed version: "1.0.0"
    IsUserModified BOOLEAN DEFAULT FALSE,  -- ← CRITICAL FLAG
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);
```

---

## Seed File Format

**Location:** `/seeds/config/{category}.json`

```json
{
  "version": "1.2.0",
  "category": "weights",
  "description": "Search ranking weights",
  "configs": [
    {
      "key": "search.weights.github_stars",
      "value": 0.30,
      "valueType": "number",
      "description": "Weight for GitHub stars",
      "validation": { "min": 0, "max": 1 }
    }
  ],
  "constraints": [
    {
      "type": "sum_equals",
      "keys": ["search.weights.github_stars", "search.weights.job_postings"],
      "value": 1.0
    }
  ]
}
```

---

## Seeding Logic (Go Implementation)

```go
func (s *SeedableConfigService) seedConfig(config SeedConfig, version, category string) error {
    var existing SeedableConfig
    result := s.db.Where("Key = ?", config.Key).First(&existing)
    
    // CONDITION 1: Key does not exist → INSERT
    if result.Error == gorm.ErrRecordNotFound {
        return s.db.Create(&SeedableConfig{
            Key:            config.Key,
            Value:          toJson(config.Value),
            Version:        version,
            IsUserModified: false,  // ← Fresh seed
        }).Error
    }
    
    // CONDITION 2: User modified → DO NOT UPDATE
    if existing.IsUserModified {
        return nil  // ← Preserve user's value
    }
    
    // CONDITION 3: Seed version newer → UPDATE
    if compareVersions(version, existing.Version) > 0 {
        return s.db.Model(&existing).Updates(map[string]interface{}{
            "Value":   toJson(config.Value),
            "Version": version,
        }).Error
    }
    
    return nil  // Same or older version, no action
}
```

---

## User Modifies Value (Settings UI)

```go
func (s *SeedableConfigService) Update(key string, newValue interface{}) error {
    return s.db.Model(&SeedableConfig{}).
        Where("Key = ?", key).
        Updates(map[string]interface{}{
            "Value":          toJson(newValue),
            "IsUserModified": true,  // ← CRITICAL: Prevents future overwrites
            "UpdatedAt":      time.Now(),
        }).Error
}
```

---

## Reset to Default

```go
func (s *SeedableConfigService) ResetToDefault(key string) error {
    // Set IsUserModified = false, then re-seed
    s.db.Model(&SeedableConfig{}).
        Where("Key = ?", key).
        Update("IsUserModified", false)
    
    return s.SeedAll()  // Re-runs seeding, will restore seed value
}
```

---

## Application Startup

```go
func main() {
    db := openSettingsDB()
    
    // ALWAYS run seeding on startup
    configService := NewSeedableConfigService(db, "./seeds/config")
    configService.SeedAll()  // ← Safe: respects IsUserModified
    
    app.Run()
}
```

---

## Seed File Categories

| Category | File | Purpose |
|----------|------|---------|
| weights | search-weights.json | Scoring formula coefficients |
| thresholds | ai-thresholds.json | AI decision thresholds |
| models | model-defaults.json | Default model selections |
| features | feature-flags.json | Feature toggles |

---

## When to Use Seedable Config

✅ **Use for:**
- Weights and scoring factors
- Thresholds and limits  
- Default model selections
- Feature flags
- Timeout values
- Retry counts

❌ **Do NOT use for:**
- User-specific preferences → `UserPreference` table
- Project-specific settings → `ProjectSettings` table
- Secrets/API keys → encrypted storage

---

## Settings UI with Weights Editor

```typescript
// Weights that must sum to 1.0
function WeightsEditor({ configs, onUpdate }: Props): JSX.Element {
  const [values, setValues] = useState(/* ... */);
  const sum = Object.values(values).reduce((a, b) => a + b, 0);
  const isValid = Math.abs(sum - 1.0) < 0.001;

  return (
    <div>
      {configs.map((c) => (
        <Slider key={c.key} value={values[c.key]} onChange={/* ... */} />
      ))}
      <div className={isValid ? "text-green-500" : "text-red-500"}>
        Total: {(sum * 100).toFixed(0)}% {!isValid && "(Must equal 100%)"}
      </div>
      <Button disabled={!isValid} onClick={handleSave}>Save</Button>
    </div>
  );
}
```

---

## Quick Reference for AI

When user says "put this in seedable config":

1. Add to `/seeds/config/{category}.json`
2. Increment version if modifying existing
3. Include: key, value, valueType, description, validation
4. App start will seed if key missing OR version newer AND not user-modified

When user modifies in Settings UI:
1. Update value in `settings.db`
2. Set `IsUserModified = TRUE`
3. Future seeds will skip this key

When user clicks "Reset to Default":
1. Set `IsUserModified = FALSE`
2. Re-run seeding
3. Seed value is restored

---

## Related

- [Split Database System](../architecture/split-database-system.md) — Where settings.db lives
- [Coding Guidelines](../constraints/coding-guidelines.md) — TypeScript/Go standards
