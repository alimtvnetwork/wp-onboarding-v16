# 56. Feature Flags

## Overview
Feature flag system for controlled rollout of new features, A/B testing, and emergency kill switches.

> **Last Updated:** 2026-01-26  
> **Database Naming:** PascalCase (e.g., `FlagKey`, `IsEnabled`, `CreatedAt`)

---

## 56.1 Core Concepts

### Flag Types
| Type | Description | Example |
|------|-------------|---------|
| RELEASE | New feature rollout | `new_editor_ui` |
| EXPERIMENT | A/B testing | `checkout_flow_v2` |
| OPS | Operational toggle | `enable_cache` |
| KILL_SWITCH | Emergency disable | `disable_email_sending` |

### Flag States
- **Enabled (global)**: Flag is on for everyone
- **Disabled (global)**: Flag is off for everyone
- **Rollout %**: Enabled for X% of users
- **Overridden**: Per-user, per-exam, or per-role override

---

## 📊 Database Tables

```sql
-- Table: FeatureFlag
CREATE TABLE FeatureFlag (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    FlagKey VARCHAR(100) NOT NULL UNIQUE,
    DisplayName VARCHAR(255) NOT NULL,
    Description TEXT,
    DefaultValue BOOLEAN NOT NULL DEFAULT 0,
    IsEnabled BOOLEAN NOT NULL DEFAULT 1,
    Category VARCHAR(50) NOT NULL DEFAULT 'general',
    RolloutPercentage INTEGER NOT NULL DEFAULT 100,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: FeatureFlagOverride (per-user or per-exam)
CREATE TABLE FeatureFlagOverride (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    FlagKey VARCHAR(100) NOT NULL,
    OverrideType VARCHAR(20) NOT NULL,  -- 'user', 'exam', 'role'
    TargetId INTEGER NOT NULL,
    IsEnabled BOOLEAN NOT NULL,
    Reason VARCHAR(255),
    CreatedBy INTEGER NOT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ExpiresAt DATETIME,
    UNIQUE(FlagKey, OverrideType, TargetId)
);

-- Indexes for fast lookups
CREATE INDEX IX_FeatureFlag_FlagKey ON FeatureFlag(FlagKey);
CREATE INDEX IX_FeatureFlag_Category ON FeatureFlag(Category);
CREATE INDEX IX_FeatureFlagOverride_Lookup ON FeatureFlagOverride(FlagKey, OverrideType, TargetId);
CREATE INDEX IX_FeatureFlagOverride_ExpiresAt ON FeatureFlagOverride(ExpiresAt);
```

---

## 📝 Seed Configuration

**File:** `config/feature-flags.json`

```json
{
    "$schema": "feature-flags-schema.json",
    "version": "1.0.0",
    "flags": {
        "new_editor_ui": {
            "displayName": "New Editor UI",
            "description": "Enable the redesigned exam editor interface",
            "category": "RELEASE",
            "defaultValue": false,
            "rolloutPercentage": 0
        },
        "enable_email_queue": {
            "displayName": "Email Queue",
            "description": "Use queued email sending instead of synchronous",
            "category": "OPS",
            "defaultValue": true,
            "rolloutPercentage": 100
        },
        "advanced_analytics": {
            "displayName": "Advanced Analytics",
            "description": "Show advanced analytics dashboard",
            "category": "RELEASE",
            "defaultValue": false,
            "rolloutPercentage": 50
        },
        "disable_secret_keys": {
            "displayName": "Disable Secret Keys (Kill Switch)",
            "description": "Emergency disable all secret key access",
            "category": "KILL_SWITCH",
            "defaultValue": false,
            "rolloutPercentage": 0
        }
    }
}
```

---

## 56.2 Feature Flag Service

```php
<?php
namespace ExamQuestionsManager\Services;

class FeatureFlagService {
    
    /**
     * Check if a feature is enabled for context
     */
    public function isEnabled(
        string $flagKey,
        ?int $userId = null,
        ?int $examId = null,
        ?string $role = null
    ): bool {
        // 1. Get the flag
        $flag = $this->flagRepo->findByKey($flagKey);
        
        if ($flag === null) {
            Logger::warning("Unknown feature flag: {$flagKey}");
            return false;
        }
        
        // 2. Check for overrides (highest priority)
        $override = $this->getActiveOverride($flagKey, $userId, $examId, $role);
        
        if ($override !== null) {
            return $override->IsEnabled;
        }
        
        // 3. Global disabled = off for everyone
        if ($flag->IsEnabled === false) {
            return false;
        }
        
        // 4. Check rollout percentage
        if ($flag->RolloutPercentage < 100) {
            return $this->isInRollout($flagKey, $userId, $flag->RolloutPercentage);
        }
        
        // 5. Default to flag's default value
        return $flag->DefaultValue;
    }
    
    /**
     * Get active override for context
     */
    private function getActiveOverride(
        string $flagKey,
        ?int $userId,
        ?int $examId,
        ?string $role
    ): ?FeatureFlagOverride {
        // Priority: User > Exam > Role
        $overrides = $this->overrideRepo->findActiveByFlagKey($flagKey);
        
        foreach ($overrides as $override) {
            // Check expiration
            if ($override->ExpiresAt && $override->ExpiresAt < new DateTime()) {
                continue;
            }
            
            // Match by type
            if ($override->OverrideType === 'user' && $override->TargetId === $userId) {
                return $override;
            }
            if ($override->OverrideType === 'exam' && $override->TargetId === $examId) {
                return $override;
            }
            if ($override->OverrideType === 'role' && $role && $override->TargetId === $this->getRoleId($role)) {
                return $override;
            }
        }
        
        return null;
    }
    
    /**
     * Deterministic rollout check
     */
    private function isInRollout(string $flagKey, ?int $userId, int $percentage): bool {
        if ($userId === null) {
            // Anonymous users: random each time
            return random_int(1, 100) <= $percentage;
        }
        
        // Deterministic hash for consistent experience
        $hash = crc32($flagKey . ':' . $userId);
        $bucket = abs($hash) % 100;
        
        return $bucket < $percentage;
    }
    
    /**
     * Create or update an override
     */
    public function setOverride(
        string $flagKey,
        string $overrideType,
        int $targetId,
        bool $isEnabled,
        int $createdBy,
        ?string $reason = null,
        ?DateTime $expiresAt = null
    ): FeatureFlagOverride {
        return $this->overrideRepo->upsert([
            'FlagKey' => $flagKey,
            'OverrideType' => $overrideType,
            'TargetId' => $targetId,
            'IsEnabled' => $isEnabled,
            'Reason' => $reason,
            'CreatedBy' => $createdBy,
            'ExpiresAt' => $expiresAt,
        ]);
    }
    
    /**
     * Seed flags from config file
     */
    public function seedFromConfig(): int {
        $config = json_decode(
            file_get_contents(EQM_CONFIG_PATH . '/feature-flags.json'),
            true
        );
        
        $seeded = 0;
        
        foreach ($config['flags'] as $key => $data) {
            $existing = $this->flagRepo->findByKey($key);
            
            if ($existing === null) {
                $this->flagRepo->create([
                    'FlagKey' => $key,
                    'DisplayName' => $data['displayName'],
                    'Description' => $data['description'] ?? null,
                    'Category' => $data['category'] ?? 'general',
                    'DefaultValue' => $data['defaultValue'] ?? false,
                    'IsEnabled' => true,
                    'RolloutPercentage' => $data['rolloutPercentage'] ?? 100,
                ]);
                $seeded++;
            }
        }
        
        return $seeded;
    }
}
```

---

## 56.3 Usage Examples

### In PHP Code
```php
// Simple check
if ($featureFlags->isEnabled('new_editor_ui', $userId)) {
    return $this->renderNewEditor();
}

// With exam context
if ($featureFlags->isEnabled('advanced_analytics', $userId, $examId)) {
    $this->includeAnalytics();
}

// Kill switch pattern
if ($featureFlags->isEnabled('disable_secret_keys')) {
    throw new FeatureDisabledException('Secret key access is temporarily disabled');
}
```

### In Templates
```php
<?php if ($features->isEnabled('new_header')): ?>
    <?php include 'partials/header-new.php'; ?>
<?php else: ?>
    <?php include 'partials/header.php'; ?>
<?php endif; ?>
```

### In JavaScript (via API)
```javascript
// Fetch flags for current user
const flags = await fetch('/api/feature-flags').then(r => r.json());

if (flags['new_editor_ui']) {
    loadNewEditor();
}
```

---

## 56.4 Admin UI

### Flag List View
```
┌─────────────────────────────────────────────────────────────┐
│ Feature Flags                                    [+ Add New]│
├─────────────────────────────────────────────────────────────┤
│ ⚡ new_editor_ui              RELEASE    50% rollout    [🔧]│
│   New Editor UI - Enable the redesigned exam editor         │
├─────────────────────────────────────────────────────────────┤
│ ✓ enable_email_queue          OPS        100% ✓         [🔧]│
│   Email Queue - Use queued email sending                    │
├─────────────────────────────────────────────────────────────┤
│ ⚠ disable_secret_keys        KILL       OFF ✗          [🔧]│
│   Emergency disable all secret key access                   │
└─────────────────────────────────────────────────────────────┘
```

### Flag Edit Modal
- Toggle global enable/disable
- Adjust rollout percentage slider
- View/manage overrides
- Audit history

### Override Management
```
┌─────────────────────────────────────────────────────────────┐
│ Overrides for: new_editor_ui                     [+ Add]    │
├─────────────────────────────────────────────────────────────┤
│ User #42 (john@example.com)    ✓ Enabled    Beta tester    │
│ Exam #15 (JavaScript Basics)   ✗ Disabled   Bug reported   │
│ Role: ADMIN                    ✓ Enabled    Testing        │
└─────────────────────────────────────────────────────────────┘
```

### Acceptance Criteria:
- [ ] All flags visible in admin
- [ ] Rollout slider with preview
- [ ] Override CRUD functional
- [ ] Audit trail visible

---

## 56.5 API Endpoints

### GET /api/feature-flags
Get all flags and their states for current user context.

**Response:**
```json
{
    "success": true,
    "data": {
        "new_editor_ui": true,
        "enable_email_queue": true,
        "advanced_analytics": false
    }
}
```

### POST /api/admin/feature-flags/{flagKey}/toggle
Toggle a flag's global state.

### POST /api/admin/feature-flags/{flagKey}/rollout
Update rollout percentage.

**Request:**
```json
{
    "percentage": 50
}
```

### POST /api/admin/feature-flags/{flagKey}/override
Create/update an override.

**Request:**
```json
{
    "overrideType": "user",
    "targetId": 42,
    "isEnabled": true,
    "reason": "Beta tester",
    "expiresAt": "2026-02-01T00:00:00Z"
}
```

---

## 56.6 Testing

### Test Cases

```php
// Test: Basic flag check
function testBasicFlagCheck(): void {
    $this->seedFlag('test_flag', defaultValue: true);
    
    $this->assertTrue($this->service->isEnabled('test_flag'));
}

// Test: Rollout percentage
function testRolloutPercentage(): void {
    $this->seedFlag('rollout_flag', rolloutPercentage: 50);
    
    $enabledCount = 0;
    for ($userId = 1; $userId <= 1000; $userId++) {
        if ($this->service->isEnabled('rollout_flag', $userId)) {
            $enabledCount++;
        }
    }
    
    // Should be roughly 50% (within margin)
    $this->assertGreaterThan(400, $enabledCount);
    $this->assertLessThan(600, $enabledCount);
}

// Test: User override
function testUserOverride(): void {
    $this->seedFlag('override_test', defaultValue: false);
    
    // Without override: off
    $this->assertFalse($this->service->isEnabled('override_test', userId: 42));
    
    // Add override
    $this->service->setOverride('override_test', 'user', 42, true, createdBy: 1);
    
    // With override: on
    $this->assertTrue($this->service->isEnabled('override_test', userId: 42));
    
    // Other users still off
    $this->assertFalse($this->service->isEnabled('override_test', userId: 43));
}

// Test: Override expiration
function testOverrideExpiration(): void {
    $this->seedFlag('expiry_test', defaultValue: false);
    
    // Add expired override
    $this->service->setOverride(
        'expiry_test', 'user', 42, true, 
        createdBy: 1,
        expiresAt: new DateTime('-1 day')
    );
    
    // Should fall through to default (false)
    $this->assertFalse($this->service->isEnabled('expiry_test', userId: 42));
}
```

### Acceptance Criteria:
- [ ] Basic checks work
- [ ] Rollout is deterministic per user
- [ ] Overrides take priority
- [ ] Expiration respected
