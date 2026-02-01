# 33. Plugin Settings

## Overview
Global plugin configuration accessible from the admin panel settings page.

---

## 33.1 General Settings

### Settings Fields

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `plugin_enabled` | Boolean | true | Master on/off switch |
| `default_timezone` | Select | UTC | Timezone for deadline calculations |
| `date_format` | Select | Y-m-d | Display format for dates |
| `time_format` | Select | H:i | Display format for times |

### Acceptance Criteria:
- [ ] Settings saved to WordPress options table
- [ ] Settings retrieved via helper function
- [ ] Timezone select populated with all PHP timezones
- [ ] Date/time format previews shown

---

## 33.2 Email Settings

### Settings Fields

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `email_enabled` | Boolean | true | Enable email notifications |
| `email_from_name` | Text | Site name | Sender name for emails |
| `email_from_address` | Email | Admin email | Sender email address |
| `email_reply_to` | Email | Admin email | Reply-to address |
| `soft_deadline_reminders` | Text | 7,3,1 | Days before soft deadline |
| `hard_deadline_reminders` | Text | 3,1 | Days before hard deadline |

### Acceptance Criteria:
- [ ] Email settings validate email format
- [ ] Reminder days parsed as comma-separated integers
- [ ] Test email button sends sample to admin
- [ ] Email preview available for each template

---

## 33.3 Security Settings

### Settings Fields

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `secret_key_length` | Number | 32 | Length of generated keys |
| `default_key_expiry_days` | Number | 30 | Default expiration for new keys |
| `max_login_attempts` | Number | 5 | Failed attempts before lockout |
| `lockout_duration_minutes` | Number | 15 | Lockout period |
| `session_timeout_hours` | Number | 24 | Auto-logout after inactivity |

### Acceptance Criteria:
- [ ] Key length between 16-64 characters
- [ ] Expiry days minimum 1, maximum 365
- [ ] Lockout settings validated as positive integers
- [ ] Settings applied to new key generation

---

## 33.4 Analytics Settings [OPTIONAL]

### Settings Fields

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `analytics_enabled` | Boolean | false | Enable analytics tracking |
| `track_ip_address` | Boolean | false | Hash and store IP addresses |
| `track_user_agent` | Boolean | true | Store browser information |
| `track_referrer` | Boolean | true | Store referrer URLs |
| `geoip_enabled` | Boolean | false | Enable geographic lookup |
| `analytics_retention_days` | Number | 90 | Days to retain detailed logs |

### Acceptance Criteria:
- [ ] Analytics can be completely disabled
- [ ] Individual tracking options togglable
- [ ] GeoIP only enabled if database available
- [ ] Retention period enforced by cron job

---

## 33.5 Display Settings

### Settings Fields

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `items_per_page` | Number | 20 | Default pagination size |
| `enable_dark_mode` | Boolean | false | Dark mode for admin panel |
| `sidebar_collapsed` | Boolean | false | Default sidebar state |
| `show_welcome_banner` | Boolean | true | Show tips for new users |

### Acceptance Criteria:
- [ ] Pagination applies to all list views
- [ ] Dark mode toggles CSS class on body
- [ ] Settings persist per-user via user meta
- [ ] Welcome banner dismissable

---

## 33.6 Data Management

### Settings Fields

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `delete_data_on_uninstall` | Boolean | false | Remove all data on uninstall |
| `backup_enabled` | Boolean | true | Enable automatic backups |
| `backup_frequency` | Select | weekly | Backup interval |
| `backup_retention_count` | Number | 5 | Number of backups to keep |

### Actions
- Export all data as JSON
- Import data from JSON backup
- Reset plugin to defaults (with confirmation)

### Acceptance Criteria:
- [ ] Uninstall behavior clearly explained
- [ ] Backup creates SQLite database copy
- [ ] Export includes all tables as JSON
- [ ] Import validates data before applying
- [ ] Reset requires typing "RESET" to confirm

---

## 33.7 Settings Page UI

### Requirements
- Tabbed interface for setting categories
- Save button persists all settings
- Reset button restores defaults (per tab)
- Validation errors shown inline

### Tabs
1. General
2. Email
3. Security
4. Analytics (if enabled)
5. Display
6. Data Management

### Acceptance Criteria:
- [ ] Tab state persisted in URL hash
- [ ] Settings load current values on page load
- [ ] Save shows success/error toast notification
- [ ] Unsaved changes prompt on navigation away
- [ ] Settings exportable as JSON for backup

---

## 33.8 Configuration Hierarchy (Three-Tier System)

### Overview

The plugin uses a three-tier configuration hierarchy to manage settings. This ensures sensible defaults while allowing runtime customization without code changes.

```
┌─────────────────────────────────────────────────────────────────┐
│                    CONFIGURATION HIERARCHY                       │
│                                                                  │
│   Priority: TIER 1 (lowest) → TIER 2 → TIER 3 (highest)         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  TIER 1: Class Constants (Consts.php)                           │
│  ─────────────────────────────────────                           │
│  • Hardcoded fallback values                                     │
│  • Compile-time constants                                        │
│  • Used ONLY if database lookup fails                            │
│  • Never change at runtime                                       │
│  • Example: Consts::DEFAULT_ITEMS_PER_PAGE = 20                 │
│                                                                  │
│                         ↑ (falls back to)                        │
│                                                                  │
│  TIER 2: JSON Seed (config/defaults.json)                       │
│  ─────────────────────────────────────────                       │
│  • Source of truth for installation/migrations                   │
│  • Read on: First install OR version change                      │
│  • Inserts into Settings DB (does NOT overwrite existing)        │
│  • Contains: Default values, feature flags, limits               │
│  • Version-controlled in repository                              │
│                                                                  │
│                         ↑ (seeds into)                           │
│                                                                  │
│  TIER 3: Settings Database (eqm_settings table)                 │
│  ───────────────────────────────────────────                     │
│  • Runtime configuration storage (HIGHEST PRIORITY)              │
│  • Admin-editable via Settings UI                                │
│  • Overrides all lower tiers                                     │
│  • Cached in memory during request lifecycle                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Read Order (at runtime)

```
1. Check memory cache → if found, return
2. Query Settings DB (eqm_settings table) → if found, cache and return
3. Check Class Constants (Consts.php) → if defined, return
4. Return provided default parameter (or null)
```

### Write Order (at seed time)

```
1. Load config/defaults.json
2. For each key: INSERT if not exists in DB (preserve admin customizations)
3. Keys with _force suffix: ALWAYS update (for critical migrations)
4. Update plugin_version in DB
```

---

## 33.9 JSON Seed File Structure

### File Location
`config/defaults.json`

### Example Structure

```json
{
    "_meta": {
        "version": "1.0.0",
        "description": "Default configuration values for Exam Questions Manager",
        "last_updated": "2026-01-25"
    },
    
    "general": {
        "plugin_enabled": true,
        "default_timezone": "UTC",
        "date_format": "Y-m-d",
        "time_format": "H:i",
        "items_per_page": 20
    },
    
    "email": {
        "email_enabled": true,
        "email_from_name": "{{site_name}}",
        "email_from_address": "{{admin_email}}",
        "soft_deadline_reminders": [7, 3, 1],
        "hard_deadline_reminders": [3, 1],
        "extension_hours_before": [24, 6]
    },
    
    "security": {
        "secret_key_length": 32,
        "default_key_expiry_days": 30,
        "max_login_attempts": 5,
        "lockout_duration_minutes": 15,
        "session_timeout_hours": 24
    },
    
    "rate_limiting": {
        "enabled": true,
        "storage": "database",
        "retention_hours": 24,
        "whitelisted_ips": [],
        "endpoints": {
            "auth_login": { "limit": 5, "window_seconds": 60, "lockout_seconds": 900 },
            "auth_register": { "limit": 3, "window_seconds": 60, "lockout_seconds": 900 },
            "secret_key_validate": { "limit": 10, "window_seconds": 60, "lockout_seconds": 300 },
            "api_general": { "limit": 60, "window_seconds": 60, "lockout_seconds": 0 }
        }
    },
    
    "deadlines": {
        "soft_deadline_buffer_hours": 24,
        "deadline_checker_batch_size": 100,
        "deadline_checker_interval_hours": 1
    },
    
    "analytics": {
        "analytics_enabled": false,
        "track_ip_address": false,
        "track_user_agent": true,
        "analytics_retention_days": 90
    },
    
    "logging": {
        "log_level": "INFO",
        "max_log_file_size_mb": 10,
        "log_retention_days": 30,
        "enable_debug_mode": false
    },
    
    "data_management": {
        "delete_data_on_uninstall": false,
        "backup_enabled": true,
        "backup_frequency": "weekly",
        "backup_retention_count": 5,
        "anonymous_session_expiry_days": 90
    },
    
    "_force_updates": {
        "description": "Keys listed here will ALWAYS be updated on version change",
        "keys": [
            "rate_limiting.endpoints",
            "logging.log_level"
        ]
    }
}
```

### Dynamic Placeholders

| Placeholder | Resolved To | Example |
|-------------|-------------|---------|
| `{{site_name}}` | WordPress site name | "My Exam Site" |
| `{{admin_email}}` | WordPress admin email | "admin@example.com" |
| `{{site_url}}` | WordPress site URL | "https://example.com" |
| `{{plugin_version}}` | Current plugin version | "1.2.0" |

### Acceptance Criteria:
- [ ] JSON file validated on plugin activation
- [ ] Nested keys flattened with dot notation in DB
- [ ] Placeholders resolved at seed time
- [ ] `_meta` section ignored during seeding
- [ ] `_force_updates` section processed for mandatory overwrites

---

## 33.10 Seeding Algorithm

### Trigger Conditions

The seeding algorithm runs when:

| Condition | Trigger | Action |
|-----------|---------|--------|
| **First Install** | `plugin_version` not in DB | Full seed of all keys |
| **Version Change** | DB version ≠ plugin version | Incremental seed (new keys + force updates) |
| **Manual Reset** | Admin clicks "Reset to Defaults" | Full seed with overwrite flag |
| **Plugin Reactivation** | Plugin reactivated after deactivation | Check version, seed if needed |

### Trigger Detection Algorithm

```pseudocode
function shouldRunSeeder(): SeedTrigger
    currentVersion = getPluginVersion()  // From plugin header
    storedVersion = Settings.get('plugin_version')
    
    IF storedVersion IS NULL:
        RETURN SeedTrigger {
            shouldSeed: true,
            reason: 'FIRST_INSTALL',
            isFullSeed: true
        }
    
    IF storedVersion != currentVersion:
        RETURN SeedTrigger {
            shouldSeed: true,
            reason: 'VERSION_CHANGE',
            previousVersion: storedVersion,
            newVersion: currentVersion,
            isFullSeed: false  // Incremental
        }
    
    RETURN SeedTrigger {
        shouldSeed: false,
        reason: 'NO_CHANGE'
    }
```

### Main Seeding Algorithm

```pseudocode
function runSeeder(trigger: SeedTrigger): SeedResult
    // Step 1: Load and validate JSON seed file
    seedFilePath = PLUGIN_DIR + '/config/defaults.json'
    
    IF !file_exists(seedFilePath):
        logError("Seed file not found", { path: seedFilePath })
        THROW SeedingException("Configuration seed file missing")
    
    seedData = json_decode(file_get_contents(seedFilePath), true)
    
    IF json_last_error() != JSON_ERROR_NONE:
        logError("Invalid JSON in seed file", { error: json_last_error_msg() })
        THROW SeedingException("Invalid configuration seed file")
    
    // Step 2: Extract force update keys
    forceUpdateKeys = seedData['_force_updates']['keys'] ?? []
    
    // Step 3: Flatten nested structure to dot notation
    flattenedSettings = flattenSettings(seedData)
    
    // Step 4: Resolve dynamic placeholders
    resolvedSettings = resolvePlaceholders(flattenedSettings)
    
    // Step 5: Process each setting
    stats = { inserted: 0, skipped: 0, updated: 0 }
    
    db.beginTransaction()
    
    TRY:
        FOR EACH key, value IN resolvedSettings:
            // Skip meta sections
            IF key.startsWith('_'):
                CONTINUE
            
            existingValue = db.query(
                "SELECT value FROM eqm_settings WHERE key = ?",
                [key]
            )
            
            shouldForceUpdate = isForceUpdateKey(key, forceUpdateKeys)
            
            IF existingValue IS NULL:
                // Key doesn't exist: INSERT
                db.insert('eqm_settings', {
                    key: key,
                    value: serializeValue(value),
                    valueType: detectValueType(value),
                    createdAt: now(),
                    updatedAt: now(),
                    source: 'seed'
                })
                stats.inserted++
                
            ELSE IF shouldForceUpdate:
                // Force update key: UPDATE regardless
                db.update('eqm_settings', 
                    { key: key },
                    {
                        value: serializeValue(value),
                        updatedAt: now(),
                        source: 'seed_force'
                    }
                )
                stats.updated++
                logInfo("Force updated setting", { key: key })
                
            ELSE IF trigger.isFullSeed:
                // Full seed (reset): UPDATE all
                db.update('eqm_settings',
                    { key: key },
                    {
                        value: serializeValue(value),
                        updatedAt: now(),
                        source: 'seed_reset'
                    }
                )
                stats.updated++
                
            ELSE:
                // Incremental seed: SKIP (preserve admin value)
                stats.skipped++
        
        // Step 6: Update version tracker
        db.upsert('eqm_settings', {
            key: 'plugin_version',
            value: getPluginVersion(),
            updatedAt: now(),
            source: 'system'
        })
        
        db.commit()
        
        // Step 7: Clear settings cache
        SettingsCache.flush()
        
        logInfo("Seeding completed", stats)
        
        RETURN SeedResult {
            success: true,
            trigger: trigger.reason,
            stats: stats
        }
        
    CATCH error:
        db.rollback()
        logError("Seeding failed", { error: error.getMessage() })
        THROW error
```

### Flatten Settings Algorithm

```pseudocode
function flattenSettings(data, prefix = ''): array
    result = []
    
    FOR EACH key, value IN data:
        fullKey = prefix ? prefix + '.' + key : key
        
        IF is_array(value) AND !is_numeric_array(value):
            // Nested object: recurse
            nested = flattenSettings(value, fullKey)
            result = array_merge(result, nested)
        ELSE:
            // Leaf value or numeric array
            result[fullKey] = value
    
    RETURN result

// Examples:
// { "general": { "timezone": "UTC" } } → { "general.timezone": "UTC" }
// { "reminders": [7, 3, 1] } → { "reminders": [7, 3, 1] } (arrays preserved)
```

### Placeholder Resolution Algorithm

```pseudocode
function resolvePlaceholders(settings): array
    placeholders = {
        '{{site_name}}': get_bloginfo('name'),
        '{{admin_email}}': get_option('admin_email'),
        '{{site_url}}': get_site_url(),
        '{{plugin_version}}': getPluginVersion()
    }
    
    resolved = []
    
    FOR EACH key, value IN settings:
        IF is_string(value):
            FOR EACH placeholder, replacement IN placeholders:
                value = str_replace(placeholder, replacement, value)
        
        resolved[key] = value
    
    RETURN resolved
```

### Force Update Key Matching

```pseudocode
function isForceUpdateKey(key, forceUpdateKeys): bool
    FOR EACH pattern IN forceUpdateKeys:
        // Exact match
        IF key == pattern:
            RETURN true
        
        // Prefix match (for nested keys)
        // Pattern "rate_limiting.endpoints" matches "rate_limiting.endpoints.auth_login"
        IF key.startsWith(pattern + '.'):
            RETURN true
    
    RETURN false
```

### Acceptance Criteria:
- [ ] First install seeds all values from defaults.json
- [ ] Version change only adds new keys (preserves admin customizations)
- [ ] Force update keys always overwritten on version change
- [ ] Transaction rollback on any error
- [ ] Settings cache cleared after seeding
- [ ] Detailed logging of seed operations

---

## 33.11 Settings Service (Runtime Read)

### Service Class

```php
<?php
// File: src/Services/SettingsService.php

namespace ExamQuestionsManager\Services;

class SettingsService {
    
    private static array $cache = [];
    private static bool $cacheLoaded = false;
    
    /**
     * Get a setting value
     * 
     * @param string $key Dot-notation key (e.g., 'general.timezone')
     * @param mixed $default Default if not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed;
    
    /**
     * Set a setting value (runtime, persists to DB)
     */
    public static function set(string $key, mixed $value): void;
    
    /**
     * Check if a setting exists
     */
    public static function has(string $key): bool;
    
    /**
     * Get all settings in a category
     */
    public static function getCategory(string $category): array;
    
    /**
     * Clear the settings cache
     */
    public static function flushCache(): void;
}
```

### Runtime Read Algorithm

```pseudocode
function get(key, default = null): mixed
    // Step 1: Check memory cache
    IF isset(self::cache[key]):
        RETURN self::cache[key]
    
    // Step 2: Query Settings Database
    row = db.query(
        "SELECT value, valueType FROM eqm_settings WHERE key = ?",
        [key]
    )
    
    IF row IS NOT NULL:
        value = deserializeValue(row.value, row.valueType)
        self::cache[key] = value
        RETURN value
    
    // Step 3: Check Class Constants (fallback)
    constName = keyToConstName(key)  // "general.timezone" → "DEFAULT_TIMEZONE"
    
    IF defined('Consts::' + constName):
        value = constant('Consts::' + constName)
        self::cache[key] = value
        RETURN value
    
    // Step 4: Return provided default
    RETURN default


function keyToConstName(key): string
    // Convert dot notation to constant name
    // "general.items_per_page" → "DEFAULT_ITEMS_PER_PAGE"
    // "rate_limiting.enabled" → "RATE_LIMITING_ENABLED"
    
    parts = explode('.', key)
    
    IF count(parts) == 2 AND parts[0] == 'general':
        // General settings use DEFAULT_ prefix
        RETURN 'DEFAULT_' + strtoupper(parts[1])
    
    // Other settings use full path
    RETURN strtoupper(str_replace('.', '_', key))
```

### Value Serialization

```pseudocode
function serializeValue(value): string
    IF is_array(value) OR is_object(value):
        RETURN json_encode(value)
    
    IF is_bool(value):
        RETURN value ? '1' : '0'
    
    RETURN (string) value


function deserializeValue(value, valueType): mixed
    SWITCH valueType:
        CASE 'json':
            RETURN json_decode(value, true)
        CASE 'bool':
            RETURN value === '1' OR value === 'true'
        CASE 'int':
            RETURN (int) value
        CASE 'float':
            RETURN (float) value
        DEFAULT:
            RETURN value


function detectValueType(value): string
    IF is_array(value) OR is_object(value):
        RETURN 'json'
    IF is_bool(value):
        RETURN 'bool'
    IF is_int(value):
        RETURN 'int'
    IF is_float(value):
        RETURN 'float'
    RETURN 'string'
```

### Acceptance Criteria:
- [ ] Settings cached in memory for request duration
- [ ] Cache cleared on settings update
- [ ] Fallback to Consts.php if DB empty
- [ ] Type preservation for bool, int, array values
- [ ] Dot notation supports unlimited nesting

---

## 33.12 Database Schema

### Settings Table

```sql
CREATE TABLE eqm_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key VARCHAR(100) NOT NULL UNIQUE,
    value TEXT NOT NULL,
    valueType VARCHAR(20) NOT NULL DEFAULT 'string',
    source VARCHAR(20) NOT NULL DEFAULT 'admin',
    description VARCHAR(500) NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_settings_key (key)
);
```

### Value Types

| Type | Storage | Example Key | Example Value |
|------|---------|-------------|---------------|
| `string` | Raw text | `general.timezone` | `"UTC"` |
| `int` | Numeric string | `general.items_per_page` | `"20"` |
| `bool` | "0" or "1" | `email.enabled` | `"1"` |
| `float` | Decimal string | `analytics.sample_rate` | `"0.5"` |
| `json` | JSON string | `rate_limiting.endpoints` | `"{...}"` |

### Source Values

| Source | Meaning |
|--------|---------|
| `seed` | Inserted by seeder on first install |
| `seed_force` | Updated by seeder (force update key) |
| `seed_reset` | Updated by seeder during full reset |
| `admin` | Changed by admin via Settings UI |
| `api` | Changed via REST API |
| `system` | Internal system value (e.g., plugin_version) |

### Acceptance Criteria:
- [ ] Key is unique (no duplicates)
- [ ] Value type stored for proper deserialization
- [ ] Source tracked for audit purposes
- [ ] Timestamps updated on every change

---

## 33.13 Class Constants (Fallback Values)

### Location
`src/Consts.php`

### Example Constants

```php
<?php
// File: src/Consts.php (partial - settings-related constants)

namespace ExamQuestionsManager;

class Consts {
    // ═══════════════════════════════════════════════════════════════════
    // DEFAULT SETTINGS (Fallback if DB empty)
    // These are ONLY used if:
    //   1. Settings DB query fails, AND
    //   2. Seeder has not run yet
    // ═══════════════════════════════════════════════════════════════════
    
    // General
    public const DEFAULT_TIMEZONE = 'UTC';
    public const DEFAULT_DATE_FORMAT = 'Y-m-d';
    public const DEFAULT_TIME_FORMAT = 'H:i';
    public const DEFAULT_ITEMS_PER_PAGE = 20;
    
    // Email
    public const EMAIL_ENABLED = true;
    public const SOFT_DEADLINE_REMINDERS = [7, 3, 1];
    public const HARD_DEADLINE_REMINDERS = [3, 1];
    
    // Security
    public const SECRET_KEY_LENGTH = 32;
    public const DEFAULT_KEY_EXPIRY_DAYS = 30;
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOCKOUT_DURATION_MINUTES = 15;
    
    // Rate Limiting
    public const RATE_LIMITING_ENABLED = true;
    public const RATE_LIMIT_RETENTION_HOURS = 24;
    
    // Logging
    public const LOG_LEVEL = 'INFO';
    public const MAX_LOG_FILE_SIZE_MB = 10;
    public const LOG_RETENTION_DAYS = 30;
    
    // Data Management
    public const DELETE_DATA_ON_UNINSTALL = false;
    public const ANONYMOUS_SESSION_EXPIRY_DAYS = 90;
    
    // ═══════════════════════════════════════════════════════════════════
    // SYSTEM CONSTANTS (Not configurable - hardcoded)
    // ═══════════════════════════════════════════════════════════════════
    
    public const RATE_LIMIT_SALT = 'eqm_rate_limit_v1';
    public const SETTINGS_CACHE_KEY = 'eqm_settings_cache';
    public const SEED_FILE_PATH = '/config/defaults.json';
}
```

### Acceptance Criteria:
- [ ] All configurable settings have a fallback constant
- [ ] Constants match defaults.json values (source of truth)
- [ ] System constants clearly separated (not configurable)
- [ ] Constants documented with usage context

---

## 33.14 Migration Support

### Adding New Settings in Version Updates

When adding new settings in a plugin update:

1. **Add to defaults.json** - New key with default value
2. **Add to Consts.php** - Fallback constant
3. **Increment plugin version** - Triggers seeder
4. **Seeder automatically adds** - New key inserted (existing preserved)

### Example: Adding a New Setting

```json
// In defaults.json (new in v1.2.0)
{
    "notifications": {
        "slack_webhook_url": "",
        "slack_enabled": false
    }
}
```

```php
// In Consts.php
public const NOTIFICATIONS_SLACK_ENABLED = false;
```

On plugin update to v1.2.0:
- Seeder detects version change
- Finds new keys `notifications.slack_webhook_url`, `notifications.slack_enabled`
- Inserts with default values
- Existing admin settings untouched

### Removing Deprecated Settings

Settings are NOT automatically removed. To clean up:

1. **Mark as deprecated** in `_meta` section of defaults.json
2. **Run cleanup migration** (separate from seeding)
3. **Remove from Consts.php** only after migration

```json
{
    "_meta": {
        "deprecated": {
            "old_setting_key": "Removed in v1.3.0, use new_setting_key instead"
        }
    }
}
```

### Acceptance Criteria:
- [ ] New settings automatically added on version update
- [ ] Existing settings never overwritten (except force keys)
- [ ] Deprecated settings documented for cleanup
- [ ] Rollback safe (old settings still work)

---

## 33.15 Testing

### Test Cases

```php
// Test Case 1: First install seeding
function testFirstInstallSeeding(): void {
    $this->assertNull(Settings::get('plugin_version'));
    
    $seeder = new SettingsSeeder();
    $result = $seeder->run();
    
    $this->assertTrue($result->success);
    $this->assertEquals('FIRST_INSTALL', $result->trigger);
    $this->assertGreaterThan(0, $result->stats['inserted']);
    $this->assertEquals(0, $result->stats['skipped']);
    
    // Verify settings populated
    $this->assertEquals('UTC', Settings::get('general.timezone'));
    $this->assertEquals(20, Settings::get('general.items_per_page'));
}

// Test Case 2: Version change preserves admin settings
function testVersionChangePreservesAdminSettings(): void {
    // Admin changed a setting
    Settings::set('general.timezone', 'America/New_York');
    Settings::set('plugin_version', '1.0.0');
    
    // Simulate version update
    $this->mockPluginVersion('1.1.0');
    
    $seeder = new SettingsSeeder();
    $result = $seeder->run();
    
    // Admin setting preserved
    $this->assertEquals('America/New_York', Settings::get('general.timezone'));
}

// Test Case 3: Force update keys overwrite
function testForceUpdateKeysOverwrite(): void {
    Settings::set('rate_limiting.endpoints.auth_login.limit', 999);
    Settings::set('plugin_version', '1.0.0');
    
    // Simulate version update with rate_limiting.endpoints as force key
    $this->mockPluginVersion('1.1.0');
    
    $seeder = new SettingsSeeder();
    $result = $seeder->run();
    
    // Force key was updated back to default
    $this->assertEquals(5, Settings::get('rate_limiting.endpoints.auth_login.limit'));
}

// Test Case 4: Fallback to constants
function testFallbackToConstants(): void {
    // Clear all settings
    db.execute("DELETE FROM eqm_settings");
    SettingsCache::flush();
    
    // Should fall back to Consts.php
    $this->assertEquals('UTC', Settings::get('general.timezone'));
    $this->assertEquals(20, Settings::get('general.items_per_page'));
}

// Test Case 5: Cache efficiency
function testCacheEfficiency(): void {
    // First call: DB query
    $value1 = Settings::get('general.timezone');
    
    // Track DB queries
    $queryCount = getQueryCount();
    
    // Second call: should use cache
    $value2 = Settings::get('general.timezone');
    
    $this->assertEquals($value1, $value2);
    $this->assertEquals($queryCount, getQueryCount()); // No additional queries
}
```

### Acceptance Criteria:
- [ ] All test cases pass
- [ ] First install populates all settings
- [ ] Version upgrade preserves admin changes
- [ ] Force keys correctly identified and updated
- [ ] Cache prevents redundant DB queries
