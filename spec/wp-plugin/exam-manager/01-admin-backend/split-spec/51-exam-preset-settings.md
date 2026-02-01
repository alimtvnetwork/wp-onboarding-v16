# 49 - Exam Preset Settings

## Overview

Exam presets are reusable configuration templates that can be applied to multiple exams. Presets use **live reference linking** - changes to a preset automatically propagate to all linked exams. This follows the 3-tier configuration hierarchy: JSON Seed → Settings DB → Class Constants.

---

## Dependencies

- `04-database-schema.md` (examPreset table)
- `06-enums-constants.md` (PresetCategory enum)
- `35-plugin-settings.md` (settings service)
- `12-exam-service.md` (exam linking)

---

## 51.1 Database Schema

### examPreset Table

```sql
CREATE TABLE IF NOT EXISTS examPreset (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Identification
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'GENERAL',
    
    -- Access Control Settings
    isInviteOnly BOOLEAN NOT NULL DEFAULT 0,
    isSecretKeyEnabled BOOLEAN NOT NULL DEFAULT 0,
    
    -- Deadline Settings
    softDeadlineDays INTEGER NOT NULL DEFAULT 7,
    hardDeadlineDays INTEGER NOT NULL DEFAULT 14,
    
    -- Extension Settings
    allowExtensions BOOLEAN NOT NULL DEFAULT 1,
    maxExtensionDays INTEGER NOT NULL DEFAULT 30,
    maxExtensionRequests INTEGER NOT NULL DEFAULT 3,
    
    -- Notification Settings
    enableNotifications BOOLEAN NOT NULL DEFAULT 1,
    reminderDaysBefore TEXT DEFAULT '[7, 3, 1]',
    
    -- UI Settings
    showProgressBar BOOLEAN NOT NULL DEFAULT 1,
    showDeadlineCountdown BOOLEAN NOT NULL DEFAULT 1,
    
    -- Advanced Settings (JSON for extensibility)
    advancedSettings TEXT DEFAULT NULL,
    
    -- Seeding Metadata
    isSeeded BOOLEAN NOT NULL DEFAULT 0,
    seedVersion VARCHAR(20) DEFAULT NULL,
    
    -- Timestamps
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    createdBy INTEGER DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_examPreset_slug ON examPreset(slug);
CREATE INDEX IF NOT EXISTS idx_examPreset_category ON examPreset(category);
CREATE INDEX IF NOT EXISTS idx_examPreset_isSeeded ON examPreset(isSeeded);
```

### Exam Table Update

Add `presetId` foreign key to exam table for live linking:

```sql
-- Add to exam table
presetId INTEGER DEFAULT NULL,
FOREIGN KEY (presetId) REFERENCES examPreset(id) ON DELETE SET NULL
```

---

## 51.2 Preset Categories

| Category | Description | Use Case |
|----------|-------------|----------|
| `GENERAL` | Default presets | Standard exam configurations |
| `CERTIFICATION` | Certification exams | Strict deadlines, no extensions |
| `TRAINING` | Training modules | Flexible deadlines, self-paced |
| `ASSESSMENT` | Skill assessments | Time-limited, invite-only |
| `CUSTOM` | User-created | Admin customizations |

---

## 51.3 JSON Seed File

**File:** `config/presets.json`

```json
{
  "presets": [
    {
      "name": "Standard Exam",
      "slug": "standard-exam",
      "description": "Default settings for standard exams",
      "category": "GENERAL",
      "isInviteOnly": false,
      "isSecretKeyEnabled": false,
      "softDeadlineDays": 7,
      "hardDeadlineDays": 14,
      "allowExtensions": true,
      "maxExtensionDays": 30,
      "maxExtensionRequests": 3,
      "enableNotifications": true,
      "reminderDaysBefore": [7, 3, 1],
      "showProgressBar": true,
      "showDeadlineCountdown": true
    },
    {
      "name": "Certification Strict",
      "slug": "certification-strict",
      "description": "Strict settings for certification exams",
      "category": "CERTIFICATION",
      "isInviteOnly": true,
      "isSecretKeyEnabled": true,
      "softDeadlineDays": 14,
      "hardDeadlineDays": 21,
      "allowExtensions": false,
      "maxExtensionDays": 0,
      "maxExtensionRequests": 0,
      "enableNotifications": true,
      "reminderDaysBefore": [7, 3, 1],
      "showProgressBar": true,
      "showDeadlineCountdown": true
    },
    {
      "name": "Self-Paced Training",
      "slug": "self-paced-training",
      "description": "Flexible settings for self-paced training",
      "category": "TRAINING",
      "isInviteOnly": false,
      "isSecretKeyEnabled": false,
      "softDeadlineDays": 30,
      "hardDeadlineDays": 60,
      "allowExtensions": true,
      "maxExtensionDays": 60,
      "maxExtensionRequests": 5,
      "enableNotifications": true,
      "reminderDaysBefore": [14, 7, 3],
      "showProgressBar": true,
      "showDeadlineCountdown": false
    },
    {
      "name": "Quick Assessment",
      "slug": "quick-assessment",
      "description": "Settings for timed skill assessments",
      "category": "ASSESSMENT",
      "isInviteOnly": true,
      "isSecretKeyEnabled": false,
      "softDeadlineDays": 1,
      "hardDeadlineDays": 2,
      "allowExtensions": false,
      "maxExtensionDays": 0,
      "maxExtensionRequests": 0,
      "enableNotifications": true,
      "reminderDaysBefore": [1],
      "showProgressBar": true,
      "showDeadlineCountdown": true
    }
  ]
}
```

---

## 51.4 Seeding Logic

### Seeder Service

**File:** `src/Services/PresetSeeder.php`

```php
<?php
namespace ExamQuestionsManager\Services;

class PresetSeeder {
    private const SEED_FILE = EQM_CONFIG_PATH . '/presets.json';
    
    /**
     * Check if seeding is needed (first install or version change)
     */
    public static function shouldSeed(): bool {
        $currentVersion = EQM_VERSION;
        $seededVersion = Settings::get('preset_seed_version', null);
        
        return is_null($seededVersion) || version_compare($currentVersion, $seededVersion, '>');
    }
    
    /**
     * Seed presets from JSON file
     * Uses dot-notation merge strategy - new presets added, existing updated
     */
    public static function seed(): void {
        if (!self::shouldSeed()) {
            return;
        }
        
        $seedData = self::loadSeedFile();
        if (is_null($seedData)) {
            return;
        }
        
        foreach ($seedData['presets'] as $presetData) {
            self::upsertPreset($presetData);
        }
        
        // Mark as seeded
        Settings::set('preset_seed_version', EQM_VERSION);
    }
    
    private static function loadSeedFile(): ?array {
        if (!file_exists(self::SEED_FILE)) {
            return null;
        }
        
        $json = file_get_contents(self::SEED_FILE);
        return json_decode($json, true);
    }
    
    private static function upsertPreset(array $data): void {
        $existing = ExamPreset::findBySlug($data['slug']);
        
        if ($existing) {
            // Only update seeded presets, not user-modified ones
            if ($existing->isSeeded) {
                $existing->fill($data);
                $existing->seedVersion = EQM_VERSION;
                $existing->save();
            }
        } else {
            $preset = new ExamPreset();
            $preset->fill($data);
            $preset->isSeeded = true;
            $preset->seedVersion = EQM_VERSION;
            $preset->save();
        }
    }
}
```

---

## 51.5 Live Reference Linking

When an exam is linked to a preset:

1. **Read behavior**: Exam uses preset values unless explicitly overridden
2. **Write behavior**: Changes to preset propagate to all linked exams
3. **Override behavior**: Exam can override specific fields (stored in exam's own columns)

### Value Resolution Order

```
1. Exam-level override (if set)
2. Linked preset value (if presetId set)
3. Default from Consts.php
```

### ExamService Integration

```php
/**
 * Get effective setting for exam
 */
public function getEffectiveSetting(Exam $exam, string $field): mixed {
    // Check exam-level override first
    $examValue = $exam->$field;
    if (!is_null($examValue)) {
        return $examValue;
    }
    
    // Check linked preset
    if ($exam->presetId) {
        $preset = ExamPreset::find($exam->presetId);
        if ($preset && isset($preset->$field)) {
            return $preset->$field;
        }
    }
    
    // Fall back to default
    return Consts::get("exam.defaults.{$field}");
}
```

---

## 51.6 Admin UI

### Preset List View

**Location**: Admin > Settings > Exam Presets

| Column | Description |
|--------|-------------|
| Name | Preset name with category badge |
| Deadlines | Soft/Hard deadline summary |
| Access | Icons for invite-only, secret key |
| Linked Exams | Count of exams using this preset |
| Actions | Edit, Duplicate, Delete |

### Preset Editor

**Fields organized in tabs:**

**Tab 1: Basic Settings**
- Name (required, unique)
- Description (optional)
- Category dropdown

**Tab 2: Access Control**
- Invite Only toggle
- Secret Key Enabled toggle

**Tab 3: Deadlines**
- Soft Deadline Days slider (1-90)
- Hard Deadline Days slider (1-180)
- Deadline preview calendar

**Tab 4: Extensions**
- Allow Extensions toggle
- Max Extension Days (if enabled)
- Max Extension Requests (if enabled)

**Tab 5: Notifications**
- Enable Notifications toggle
- Reminder Days chips input

**Tab 6: UI Settings**
- Show Progress Bar toggle
- Show Deadline Countdown toggle

**Tab 7: Advanced (JSON)**
- JSON editor for custom settings
- Schema validation

### Linking Preset to Exam

In Exam Editor Metadata Tab:
- Preset dropdown selector
- "Unlink" option to remove preset
- Override indicators showing which fields differ from preset
- "Reset to Preset" button per field

---

## 51.7 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/presets` | List all presets |
| GET | `/presets/{id}` | Get preset details |
| POST | `/presets` | Create new preset |
| PUT | `/presets/{id}` | Update preset |
| DELETE | `/presets/{id}` | Delete preset |
| POST | `/presets/{id}/duplicate` | Clone preset |
| GET | `/presets/{id}/linked-exams` | Get exams using preset |
| POST | `/exams/{id}/link-preset` | Link exam to preset |
| POST | `/exams/{id}/unlink-preset` | Remove preset link |

---

## 51.8 Acceptance Criteria

### Preset Management
- [ ] Create preset with all configurable fields
- [ ] Edit preset with live validation
- [ ] Delete preset (only if no linked exams)
- [ ] Duplicate preset with new name
- [ ] View list of linked exams

### Seeding
- [ ] Presets seeded on first install
- [ ] Presets updated on version change
- [ ] User-modified presets preserved
- [ ] Seed file validates against schema

### Live Linking
- [ ] Preset changes propagate to linked exams
- [ ] Exam overrides take precedence
- [ ] Unlink removes preset reference
- [ ] "Reset to Preset" restores default

### UI
- [ ] Category filter in list view
- [ ] Linked exam count accurate
- [ ] Override indicators in exam editor
- [ ] JSON editor with syntax highlighting

---

## 51.9 Error Handling

| Scenario | Behavior |
|----------|----------|
| Delete preset with linked exams | Block with count of affected exams |
| Duplicate name | Append number suffix |
| Invalid seed JSON | Log error, skip invalid entries |
| Circular preset reference | Prevent via validation |
| Missing preset (deleted) | Exam falls back to defaults |

---

## 51.10 Security

- [ ] Only ADMIN role can manage presets
- [ ] Seeded presets marked as system presets
- [ ] Audit log for preset changes
- [ ] Validate JSON schema before save

---

## Notes

- Presets follow the 3-tier configuration hierarchy pattern
- Default presets can be disabled but not deleted
- Custom presets are user-created with `isSeeded = false`
- Version comparison uses semantic versioning
