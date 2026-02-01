# 04. Configuration Hierarchy

> **Applies To:** All languages (PHP, TypeScript, Python)  
> **Priority:** HIGH - Consistent configuration prevents environment issues

---

## 1. Core Principles

### 1.1 The Three-Tier Hierarchy

All configuration values must follow a strict resolution order:

```
┌─────────────────────────────────────────────────────────┐
│  TIER 1: Database/Runtime Overrides (Highest Priority) │
│  └── User-configurable settings stored in database     │
├─────────────────────────────────────────────────────────┤
│  TIER 2: Configuration Files                           │
│  └── JSON/YAML files for environment-specific values   │
├─────────────────────────────────────────────────────────┤
│  TIER 3: Code Constants (Lowest Priority / Fallback)   │
│  └── Hardcoded defaults that ship with the code        │
└─────────────────────────────────────────────────────────┘
```

### 1.2 Resolution Flow

```
Request for config value "max_upload_size"
         │
         ▼
┌─────────────────────────────┐
│ Check Database Override     │
│ (settings table)            │
├─────────────────────────────┤
│ Found? ─────────────────────┼──► Return database value
│                             │
│ Not Found? ─────────────────┼──▼
└─────────────────────────────┘
         │
         ▼
┌─────────────────────────────┐
│ Check Config File           │
│ (config/defaults.json)      │
├─────────────────────────────┤
│ Found? ─────────────────────┼──► Return config file value
│                             │
│ Not Found? ─────────────────┼──▼
└─────────────────────────────┘
         │
         ▼
┌─────────────────────────────┐
│ Return Code Constant        │
│ (Consts.php / constants.ts) │
└─────────────────────────────┘
```

---

## 2. Tier Definitions

### 2.1 Tier 1: Database Overrides

**Purpose:** Runtime-configurable settings that admins can change without deployments

**Characteristics:**
- Stored in `settings` table
- Can be changed via admin UI
- Take highest priority
- Must have validation rules

**Examples:**
- Site name
- Email sender address
- Feature toggles
- Rate limits

### 2.2 Tier 2: Configuration Files

**Purpose:** Environment-specific values that differ between dev/staging/production

**Characteristics:**
- JSON, YAML, or ENV files
- Checked into version control (except secrets)
- Set at deployment time
- Used for initial seeding

**Examples:**
- Database connection strings
- API endpoints
- Third-party service URLs
- Default feature flag values

### 2.3 Tier 3: Code Constants

**Purpose:** Safe fallback values that always work

**Characteristics:**
- Hardcoded in source code
- Never change at runtime
- Must be sensible defaults
- Used if all else fails

**Examples:**
- Maximum string lengths
- Pagination limits
- Timeout values
- Enum values

---

## 3. Implementation

### 3.1 PHP Implementation

#### Constants Class

```php
<?php
declare(strict_types=1);

final class Consts {
    // File Uploads
    public const MAX_UPLOAD_SIZE_MB = 5;
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'png', 'jpg'];
    public const MAX_FILES_PER_UPLOAD = 10;
    
    // Pagination
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;
    
    // Sessions
    public const SESSION_DURATION_DAYS = 7;
    public const REMEMBER_ME_DURATION_DAYS = 30;
    
    // Rate Limiting
    public const RATE_LIMIT_REQUESTS = 100;
    public const RATE_LIMIT_WINDOW_SECONDS = 60;
    
    // Timeouts
    public const API_TIMEOUT_SECONDS = 30;
    public const DATABASE_TIMEOUT_SECONDS = 10;
    
    // Cache
    public const CACHE_TTL_SECONDS = 3600;
    
    // Logging
    public const LOG_MAX_SIZE_MB = 10;
    public const LOG_MAX_ARCHIVES = 10;
    
    // Prevent instantiation
    private function __construct() {}
}
```

#### Config File Loader

```php
<?php
declare(strict_types=1);

class ConfigLoader {
    private static ?array $config = null;
    private const CONFIG_PATH = __DIR__ . '/../config/defaults.json';
    
    public static function get(string $key, mixed $default = null): mixed {
        $config = self::load();
        
        return self::getNestedValue($config, $key, $default);
    }
    
    private static function load(): array {
        if (isNotNull(self::$config)) {
            return self::$config;
        }
        
        if (isFalse(file_exists(self::CONFIG_PATH))) {
            self::$config = [];
            return self::$config;
        }
        
        $content = file_get_contents(self::CONFIG_PATH);
        self::$config = json_decode($content, true) ?? [];
        
        return self::$config;
    }
    
    /**
     * Get nested value using dot notation
     * Example: getNestedValue($arr, 'email.sender.name')
     */
    private static function getNestedValue(array $array, string $key, mixed $default): mixed {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (isFalse(is_array($value)) || hasNoKey($value, $k)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}
```

#### Settings Service (Database Layer)

```php
<?php
declare(strict_types=1);

class Settings {
    private static array $cache = [];
    
    /**
     * Get a setting value using the 3-tier hierarchy
     */
    public static function get(string $key, mixed $fallback = null): mixed {
        // Tier 1: Check database override
        $dbValue = self::getFromDatabase($key);
        if (isNotNull($dbValue)) {
            return $dbValue;
        }
        
        // Tier 2: Check config file
        $configValue = ConfigLoader::get($key);
        if (isNotNull($configValue)) {
            return $configValue;
        }
        
        // Tier 3: Return constant or provided fallback
        $constValue = self::getConstant($key);
        if (isNotNull($constValue)) {
            return $constValue;
        }
        
        return $fallback;
    }
    
    /**
     * Set a database override
     */
    public static function set(string $key, mixed $value): void {
        $db = Database::getInstance();
        
        $existing = $db->query(
            "SELECT id FROM settings WHERE setting_key = ?",
            [$key]
        )->fetch();
        
        if (isNotNull($existing)) {
            $db->execute(
                "UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?",
                [json_encode($value), date('c'), $key]
            );
        } else {
            $db->execute(
                "INSERT INTO settings (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, ?, ?)",
                [$key, json_encode($value), date('c'), date('c')]
            );
        }
        
        // Invalidate cache
        unset(self::$cache[$key]);
    }
    
    private static function getFromDatabase(string $key): mixed {
        if (hasKey(self::$cache, $key)) {
            return self::$cache[$key];
        }
        
        $db = Database::getInstance();
        $row = $db->query(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        )->fetch();
        
        if (isNull($row)) {
            return null;
        }
        
        $value = json_decode($row['setting_value'], true);
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    private static function getConstant(string $key): mixed {
        // Convert dot notation to constant name
        // 'upload.max_size' -> 'MAX_UPLOAD_SIZE_MB'
        $constName = strtoupper(str_replace('.', '_', $key));
        
        if (defined("Consts::{$constName}")) {
            return constant("Consts::{$constName}");
        }
        
        return null;
    }
}
```

### 3.2 TypeScript Implementation

```typescript
// constants.ts
export const Consts = {
  // File Uploads
  MAX_UPLOAD_SIZE_MB: 5,
  ALLOWED_EXTENSIONS: ['pdf', 'doc', 'docx', 'png', 'jpg'],
  MAX_FILES_PER_UPLOAD: 10,
  
  // Pagination
  DEFAULT_PAGE_SIZE: 20,
  MAX_PAGE_SIZE: 100,
  
  // Sessions
  SESSION_DURATION_DAYS: 7,
  REMEMBER_ME_DURATION_DAYS: 30,
  
  // Rate Limiting
  RATE_LIMIT_REQUESTS: 100,
  RATE_LIMIT_WINDOW_SECONDS: 60,
  
  // Timeouts
  API_TIMEOUT_SECONDS: 30,
  DATABASE_TIMEOUT_SECONDS: 10,
  
  // Cache
  CACHE_TTL_SECONDS: 3600,
} as const;

// config-loader.ts
import configFile from '../config/defaults.json';

export class ConfigLoader {
  static get<T>(key: string, defaultValue?: T): T | undefined {
    const keys = key.split('.');
    let value: unknown = configFile;
    
    for (const k of keys) {
      if (value === null || typeof value !== 'object' || !(k in value)) {
        return defaultValue;
      }
      value = (value as Record<string, unknown>)[k];
    }
    
    return value as T;
  }
}

// settings.ts
import { Consts } from './constants';
import { ConfigLoader } from './config-loader';
import { supabase } from './supabase';

export class Settings {
  private static cache: Map<string, unknown> = new Map();
  
  static async get<T>(key: string, fallback?: T): Promise<T> {
    // Tier 1: Check database
    const dbValue = await this.getFromDatabase<T>(key);
    if (dbValue !== null) {
      return dbValue;
    }
    
    // Tier 2: Check config file
    const configValue = ConfigLoader.get<T>(key);
    if (configValue !== undefined) {
      return configValue;
    }
    
    // Tier 3: Check constants
    const constKey = key.toUpperCase().replace(/\./g, '_');
    if (constKey in Consts) {
      return (Consts as Record<string, unknown>)[constKey] as T;
    }
    
    return fallback as T;
  }
  
  static async set(key: string, value: unknown): Promise<void> {
    const { error } = await supabase
      .from('settings')
      .upsert({
        setting_key: key,
        setting_value: JSON.stringify(value),
        updated_at: new Date().toISOString(),
      });
    
    if (error) throw error;
    
    this.cache.delete(key);
  }
  
  private static async getFromDatabase<T>(key: string): Promise<T | null> {
    if (this.cache.has(key)) {
      return this.cache.get(key) as T;
    }
    
    const { data } = await supabase
      .from('settings')
      .select('setting_value')
      .eq('setting_key', key)
      .single();
    
    if (!data) return null;
    
    const value = JSON.parse(data.setting_value);
    this.cache.set(key, value);
    
    return value;
  }
}
```

### 3.3 Python Implementation

```python
# constants.py
from dataclasses import dataclass
from typing import Final

@dataclass(frozen=True)
class Consts:
    # File Uploads
    MAX_UPLOAD_SIZE_MB: Final[int] = 5
    ALLOWED_EXTENSIONS: Final[tuple] = ('pdf', 'doc', 'docx', 'png', 'jpg')
    MAX_FILES_PER_UPLOAD: Final[int] = 10
    
    # Pagination
    DEFAULT_PAGE_SIZE: Final[int] = 20
    MAX_PAGE_SIZE: Final[int] = 100
    
    # Sessions
    SESSION_DURATION_DAYS: Final[int] = 7
    REMEMBER_ME_DURATION_DAYS: Final[int] = 30
    
    # Rate Limiting
    RATE_LIMIT_REQUESTS: Final[int] = 100
    RATE_LIMIT_WINDOW_SECONDS: Final[int] = 60
    
    # Timeouts
    API_TIMEOUT_SECONDS: Final[int] = 30
    DATABASE_TIMEOUT_SECONDS: Final[int] = 10
    
    # Cache
    CACHE_TTL_SECONDS: Final[int] = 3600


# config_loader.py
import json
from pathlib import Path
from typing import Any, Optional

class ConfigLoader:
    _config: Optional[dict] = None
    CONFIG_PATH = Path(__file__).parent.parent / 'config' / 'defaults.json'
    
    @classmethod
    def get(cls, key: str, default: Any = None) -> Any:
        config = cls._load()
        return cls._get_nested(config, key, default)
    
    @classmethod
    def _load(cls) -> dict:
        if cls._config is not None:
            return cls._config
        
        if not cls.CONFIG_PATH.exists():
            cls._config = {}
            return cls._config
        
        with open(cls.CONFIG_PATH) as f:
            cls._config = json.load(f)
        
        return cls._config
    
    @classmethod
    def _get_nested(cls, data: dict, key: str, default: Any) -> Any:
        keys = key.split('.')
        value = data
        
        for k in keys:
            if not isinstance(value, dict) or k not in value:
                return default
            value = value[k]
        
        return value


# settings.py
from typing import Any, Optional
from .constants import Consts
from .config_loader import ConfigLoader
from .database import db

class Settings:
    _cache: dict[str, Any] = {}
    
    @classmethod
    def get(cls, key: str, fallback: Any = None) -> Any:
        # Tier 1: Database
        db_value = cls._get_from_database(key)
        if db_value is not None:
            return db_value
        
        # Tier 2: Config file
        config_value = ConfigLoader.get(key)
        if config_value is not None:
            return config_value
        
        # Tier 3: Constants
        const_key = key.upper().replace('.', '_')
        if hasattr(Consts, const_key):
            return getattr(Consts, const_key)
        
        return fallback
    
    @classmethod
    def set(cls, key: str, value: Any) -> None:
        db.execute(
            """
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, datetime('now'))
            ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = excluded.setting_value,
                updated_at = excluded.updated_at
            """,
            (key, json.dumps(value))
        )
        cls._cache.pop(key, None)
    
    @classmethod
    def _get_from_database(cls, key: str) -> Optional[Any]:
        if key in cls._cache:
            return cls._cache[key]
        
        row = db.fetch_one(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            (key,)
        )
        
        if row is None:
            return None
        
        value = json.loads(row['setting_value'])
        cls._cache[key] = value
        
        return value
```

---

## 4. Configuration File Structure

### 4.1 Default Config (config/defaults.json)

```json
{
  "app": {
    "name": "My Application",
    "version": "1.0.0",
    "environment": "production"
  },
  "email": {
    "sender": {
      "name": "My App",
      "address": "noreply@example.com"
    },
    "smtp": {
      "host": "smtp.example.com",
      "port": 587
    }
  },
  "upload": {
    "max_size_mb": 10,
    "allowed_types": ["pdf", "doc", "docx", "png", "jpg", "jpeg"]
  },
  "features": {
    "enable_registration": true,
    "enable_2fa": false,
    "maintenance_mode": false
  },
  "rate_limiting": {
    "enabled": true,
    "requests_per_minute": 60
  }
}
```

### 4.2 Environment-Specific Overrides

```
config/
├── defaults.json       # Base configuration
├── development.json    # Dev overrides (merged with defaults)
├── staging.json        # Staging overrides
└── production.json     # Production overrides
```

### 4.3 Merge Strategy

Use dot-notation deep merge for overrides:

```php
class ConfigMerger {
    public static function merge(array $base, array $override): array {
        $result = $base;
        
        foreach ($override as $key => $value) {
            if (is_array($value) && hasKey($result, $key) && is_array($result[$key])) {
                $result[$key] = self::merge($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
}
```

---

## 5. Seeding System

### 5.1 When to Seed

Seeds run automatically when:
1. **First install** - No settings exist
2. **Version upgrade** - App version changed
3. **Manual trigger** - Admin resets to defaults

### 5.2 Seed Implementation

```php
class ConfigSeeder {
    private const VERSION_KEY = 'app.version';
    
    public function seedIfNeeded(): void {
        $currentVersion = Settings::get(self::VERSION_KEY);
        $newVersion = ConfigLoader::get('app.version');
        
        if (isNull($currentVersion)) {
            $this->performFullSeed();
            return;
        }
        
        if (isNotEqual($currentVersion, $newVersion)) {
            $this->performMigration($currentVersion, $newVersion);
        }
    }
    
    private function performFullSeed(): void {
        Logger::info("Performing initial configuration seed");
        
        $defaults = ConfigLoader::getAll();
        
        foreach ($this->flatten($defaults) as $key => $value) {
            Settings::set($key, $value);
        }
        
        Logger::info("Configuration seed complete", [
            'keys_seeded' => count($this->flatten($defaults)),
        ]);
    }
    
    private function performMigration(string $from, string $to): void {
        Logger::info("Migrating configuration", [
            'from_version' => $from,
            'to_version' => $to,
        ]);
        
        // Version-specific migrations
        $migrations = $this->getMigrations($from, $to);
        
        foreach ($migrations as $migration) {
            $migration->run();
        }
        
        Settings::set(self::VERSION_KEY, $to);
    }
    
    private function flatten(array $array, string $prefix = ''): array {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = isNotEmpty($prefix) ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value) && isFalse(array_is_list($value))) {
                $result = array_merge($result, $this->flatten($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
}
```

---

## 6. Validation

### 6.1 Setting Schemas

Every setting should have a validation schema:

```php
class SettingSchemas {
    public static function getSchema(string $key): array {
        return match ($key) {
            'upload.max_size_mb' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 100,
            ],
            'email.sender.address' => [
                'type' => 'email',
            ],
            'features.maintenance_mode' => [
                'type' => 'boolean',
            ],
            'rate_limiting.requests_per_minute' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 1000,
            ],
            default => ['type' => 'any'],
        };
    }
    
    public static function validate(string $key, mixed $value): bool {
        $schema = self::getSchema($key);
        
        return match ($schema['type']) {
            'integer' => is_int($value) 
                && (hasNoKey($schema, 'min') || $value >= $schema['min'])
                && (hasNoKey($schema, 'max') || $value <= $schema['max']),
            'boolean' => is_bool($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'string' => is_string($value),
            default => true,
        };
    }
}
```

---

## 7. Anti-Patterns

### 7.1 Hardcoded Values

```php
// ❌ INCORRECT - Hardcoded, can't be changed
$maxSize = 5 * 1024 * 1024;

// ✅ CORRECT - Uses hierarchy
$maxSize = Settings::get('upload.max_size_mb', Consts::MAX_UPLOAD_SIZE_MB) * 1024 * 1024;
```

### 7.2 Direct Database Access

```php
// ❌ INCORRECT - Bypasses hierarchy
$value = $db->query("SELECT setting_value FROM settings WHERE key = 'max_size'")->fetch();

// ✅ CORRECT - Uses Settings service
$value = Settings::get('upload.max_size_mb');
```

### 7.3 Environment Variables for Everything

```php
// ❌ INCORRECT - User-configurable setting in env
$siteName = getenv('SITE_NAME'); // Requires deployment to change!

// ✅ CORRECT - Database allows admin changes
$siteName = Settings::get('app.name');
```

### 7.4 Missing Fallbacks

```php
// ❌ INCORRECT - No fallback, could return null
$timeout = Settings::get('api.timeout');

// ✅ CORRECT - Always has a safe fallback
$timeout = Settings::get('api.timeout', Consts::API_TIMEOUT_SECONDS);
```

---

## 8. Version/Changelog/Seeding Trigger (CRITICAL)

> ⚠️ **MANDATORY SEQUENCE**: Every schema or configuration change MUST follow this exact workflow.

### 8.1 The Unified Update Sequence

```
┌─────────────────────────────────────────────────────────────────────┐
│                    SCHEMA/CONFIG CHANGE WORKFLOW                     │
│                                                                      │
│  Step 1: UPDATE VERSION                                             │
│  ───────────────────────────────────────────────────────────────── │
│  • Increment version in config/defaults.json (app.version)         │
│  • Use semantic versioning: MAJOR.MINOR.PATCH                      │
│  • Schema changes = MINOR bump, config changes = PATCH bump        │
│                                                                      │
│  Step 2: UPDATE CHANGELOG                                           │
│  ───────────────────────────────────────────────────────────────── │
│  • Add entry to CHANGELOG.md with date and version                 │
│  • Document: what changed, why, and migration notes                │
│  • Include "BREAKING" prefix for breaking changes                  │
│                                                                      │
│  Step 3: TRIGGER SEEDER                                             │
│  ───────────────────────────────────────────────────────────────── │
│  • Seeder automatically runs on version mismatch                   │
│  • Seeds new defaults from config files to database                │
│  • Runs version-specific migrations if defined                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 8.2 When This Sequence Applies

| Change Type | Version Bump | Changelog | Seeder Trigger |
|-------------|--------------|-----------|----------------|
| New database table | MINOR | ✅ Required | ✅ Auto |
| New column added | MINOR | ✅ Required | ✅ Auto |
| Column renamed/removed | MAJOR | ✅ Required + Migration | ✅ Auto |
| New configuration key | PATCH | ✅ Required | ✅ Auto |
| Default value changed | PATCH | ✅ Required | ✅ Auto |
| Bug fix (no schema) | PATCH | ✅ Required | ❌ Not needed |
| Feature flag added | PATCH | ✅ Required | ✅ Auto |
| Email template added | PATCH | ✅ Required | ✅ Auto |

### 8.3 Implementation

```php
<?php
/**
 * This method MUST be called on every application bootstrap/activation.
 * It ensures the version/changelog/seeding sequence is enforced.
 */
class VersionedSeeder {
    private const VERSION_KEY = 'app.version';
    
    public function onActivation(): void {
        $currentVersion = Settings::get(self::VERSION_KEY);
        $newVersion = ConfigLoader::get('app.version');
        
        // First install
        if (isNull($currentVersion)) {
            Logger::info("First install detected, running full seed");
            $this->runFullSeed();
            $this->logToChangelog("Initial installation", $newVersion);
            return;
        }
        
        // Version changed = run migrations + seed
        if (version_compare($newVersion, $currentVersion, '>')) {
            Logger::info("Version upgrade detected", [
                'from' => $currentVersion,
                'to' => $newVersion,
            ]);
            
            $this->runMigrations($currentVersion, $newVersion);
            $this->runIncrementalSeed($currentVersion, $newVersion);
            Settings::set(self::VERSION_KEY, $newVersion);
            
            return;
        }
        
        // Same version = no action needed
        Logger::debug("Version unchanged, skipping seed");
    }
    
    private function runFullSeed(): void {
        // Seed all configuration from config files
        $this->seedFromFile('config/defaults.json');
        $this->seedFromFile('config/email-templates.json');
        $this->seedFromFile('config/feature-flags.json');
        $this->seedFromFile('config/presets.json');
    }
    
    private function runMigrations(string $from, string $to): void {
        $migrations = $this->getMigrationsBetween($from, $to);
        
        foreach ($migrations as $migration) {
            Logger::info("Running migration: {$migration->getName()}");
            $migration->up();
        }
    }
}
```

### 8.4 Changelog Format

**File:** `CHANGELOG.md`

```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2026-01-26

### Added
- New `ParticipantMilestone` table for tracking progress milestones
- Feature flag: `advanced_analytics`

### Changed
- Database columns now use PascalCase naming convention

### Migration Notes
- Run `php artisan migrate` after update
- Seeder will automatically populate new feature flags

## [1.1.5] - 2026-01-20

### Fixed
- Extension deadline calculation now uses original hard deadline

### Changed  
- Default `maxExtensionDays` increased from 14 to 30
```

### 8.5 Verification Checklist

After any schema or configuration change:

```
□ 1. config/defaults.json → app.version incremented
□ 2. CHANGELOG.md → new entry added with today's date
□ 3. Migration file created (if schema change)
□ 4. Seeder tested locally (fresh install + upgrade)
□ 5. Version comparison test passes
```

### 8.6 Anti-Patterns

```php
// ❌ WRONG: Changing schema without version bump
Schema::addColumn('Participant', 'NewField', 'VARCHAR(100)');
// Seeder won't know to run!

// ❌ WRONG: Updating config without changelog
// config/defaults.json: "maxExtensionDays": 30 → 45
// No record of why this changed!

// ❌ WRONG: Manual database updates in production
$db->exec("INSERT INTO settings ...");
// Bypasses seeding system, won't replicate to other environments!

// ✅ CORRECT: Full sequence
// 1. Update config/defaults.json with version bump
// 2. Add CHANGELOG.md entry
// 3. Create migration if needed
// 4. Seeder handles the rest automatically
```

---

## 9. Mandatory Implementation Checklist

Before considering any implementation complete, verify:

### Configuration Hierarchy
- [ ] Settings service implements 3-tier hierarchy
- [ ] All configuration values have code constant fallbacks
- [ ] Config files use dot-notation structure
- [ ] Settings are validated before storage
- [ ] Cache invalidated on setting updates
- [ ] No hardcoded values in business logic
- [ ] Environment-specific configs are separated
- [ ] Sensitive settings (API keys) excluded from config files

### Version/Changelog/Seeding (CRITICAL)
- [ ] Version bumped in `config/defaults.json`
- [ ] Changelog entry added with date and description
- [ ] Seeder runs on first install
- [ ] Seeder runs on version mismatch
- [ ] Migrations defined for breaking changes
- [ ] Seeder tested for both fresh install and upgrade paths

---

*This document establishes configuration patterns. See [03-conditional-helpers-systems.md](./03-conditional-helpers-systems.md) for control flow helpers.*
