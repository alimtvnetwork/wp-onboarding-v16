# 35. WordPress Configuration & Seeding

> **Version**: 1.0.0  
> **Last Updated**: 2025-01-26  
> **Status**: PRODUCTION-READY  
> **Applies To**: WordPress Plugin Development

---

## 35.1 Overview

This document establishes standardized patterns for plugin configuration management, including the 3-tier configuration hierarchy, version-triggered seeding, Options API integration, and settings migration. The Single Source of Truth principle ensures consistency across all configuration sources.

---

## 35.2 Configuration Hierarchy (3-Tier)

### Priority Order

```
┌─────────────────────────────────────────────────────────────┐
│                    CONFIGURATION HIERARCHY                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   ┌─────────────────────┐                                   │
│   │  1. DATABASE        │  ◄── Highest Priority             │
│   │  (Options API)      │      Runtime overrides            │
│   └──────────┬──────────┘      User-modified settings       │
│              │                                              │
│              ▼                                              │
│   ┌─────────────────────┐                                   │
│   │  2. JSON SEED FILES │  ◄── Installation defaults        │
│   │  (config/*.json)    │      Version-specific values      │
│   └──────────┬──────────┘      Migration data               │
│              │                                              │
│              ▼                                              │
│   ┌─────────────────────┐                                   │
│   │  3. CLASS CONSTANTS │  ◄── Lowest Priority              │
│   │  (Consts.php)       │      Hardcoded fallbacks          │
│   └─────────────────────┘      Never changes at runtime     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Hierarchy Rules

| Tier | Source | When Used | Can Be Modified |
|------|--------|-----------|-----------------|
| 1 | Database (wp_options) | Runtime reads | Yes, by admin |
| 2 | JSON Seed Files | Installation, migrations | Yes, by developer |
| 3 | Class Constants | Fallback only | No |

---

## 35.3 Configuration Files Structure

### Directory Layout

```
plugin-slug/
├── config/
│   ├── defaults.json          # Main settings defaults
│   ├── emails.json            # Email templates
│   ├── presets.json           # Feature presets
│   ├── themes.json            # UI theme definitions
│   └── roles.json             # RBAC role definitions
├── src/
│   └── Config/
│       ├── Consts.php         # Class constants (fallbacks)
│       ├── Settings.php       # Settings service
│       └── Seeder.php         # Seeding logic
```

### defaults.json Structure

```json
{
    "_meta": {
        "version": "1.0.0",
        "lastUpdated": "2025-01-26",
        "description": "Default plugin settings"
    },
    "general": {
        "enable_feature": {
            "value": true,
            "type": "boolean",
            "description": "Enable main plugin feature"
        },
        "items_per_page": {
            "value": 20,
            "type": "integer",
            "min": 5,
            "max": 100,
            "description": "Items displayed per page"
        },
        "default_status": {
            "value": "draft",
            "type": "enum",
            "allowed": ["draft", "active", "archived"],
            "description": "Default status for new items"
        }
    },
    "email": {
        "from_name": {
            "value": "",
            "type": "string",
            "maxLength": 100,
            "description": "Sender name for emails"
        },
        "from_email": {
            "value": "",
            "type": "email",
            "description": "Sender email address"
        },
        "enable_notifications": {
            "value": true,
            "type": "boolean",
            "description": "Enable email notifications"
        }
    },
    "advanced": {
        "debug_mode": {
            "value": false,
            "type": "boolean",
            "description": "Enable debug logging"
        },
        "cache_ttl": {
            "value": 3600,
            "type": "integer",
            "min": 0,
            "max": 86400,
            "description": "Cache duration in seconds"
        },
        "log_retention_days": {
            "value": 30,
            "type": "integer",
            "min": 7,
            "max": 365,
            "description": "Days to retain log files"
        }
    }
}
```

### Class Constants (Fallbacks)

```php
<?php
namespace PluginNamespace\Config;

/**
 * Hardcoded fallback constants
 * Used ONLY when database and JSON values are unavailable
 */
final class Consts
{
    // Plugin metadata
    public const PLUGIN_NAME = 'Plugin Name';
    public const PLUGIN_SLUG = 'plugin-slug';
    public const PLUGIN_VERSION = '1.0.0';
    public const DB_VERSION = '1.0.0';
    public const MIN_PHP_VERSION = '8.0';
    public const MIN_WP_VERSION = '6.0';
    
    // Option prefixes
    public const OPTION_PREFIX = 'plugin_slug_';
    public const TRANSIENT_PREFIX = 'plugin_slug_';
    
    // Default values (fallbacks)
    public const DEFAULT_ITEMS_PER_PAGE = 20;
    public const DEFAULT_CACHE_TTL = 3600;
    public const DEFAULT_LOG_RETENTION_DAYS = 30;
    public const DEFAULT_STATUS = 'draft';
    
    // Limits
    public const MAX_TITLE_LENGTH = 200;
    public const MAX_DESCRIPTION_LENGTH = 5000;
    public const MAX_FILE_SIZE = 5242880; // 5MB
    public const MAX_UPLOAD_FILES = 5;
    
    // Allowed file types
    public const ALLOWED_FILE_TYPES = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
    
    // Cron intervals
    public const CRON_CLEANUP_INTERVAL = 'daily';
    public const CRON_EMAIL_INTERVAL = 'twicedaily';
    
    // API settings
    public const API_NAMESPACE = 'plugin-slug/v1';
    public const API_RATE_LIMIT = 100;
    public const API_RATE_WINDOW = 60;
    
    // Private constructor - cannot instantiate
    private function __construct() {}
}
```

---

## 35.4 Settings Service

### Settings Manager Class

```php
<?php
namespace PluginNamespace\Config;

use PluginNamespace\Utils\Logger;
use PluginNamespace\Utils\Sanitizer;

class Settings
{
    /**
     * Cached settings to avoid repeated database queries
     */
    private static array $cache = [];
    
    /**
     * JSON defaults cache
     */
    private static ?array $jsonDefaults = null;
    
    /**
     * Get setting value with 3-tier hierarchy
     * 
     * Priority: Database > JSON Seed > Class Constant
     */
    public static function get(string $key, $fallback = null)
    {
        // Check cache first
        $isCached = isset(self::$cache[$key]);
        if ($isCached) {
            return self::$cache[$key];
        }
        
        // Tier 1: Database (Options API)
        $optionName = Consts::OPTION_PREFIX . $key;
        $dbValue = get_option($optionName, null);
        
        $hasDbValue = ($dbValue !== null);
        if ($hasDbValue) {
            self::$cache[$key] = $dbValue;
            return $dbValue;
        }
        
        // Tier 2: JSON Seed Files
        $jsonValue = self::getJsonDefault($key);
        
        $hasJsonValue = ($jsonValue !== null);
        if ($hasJsonValue) {
            self::$cache[$key] = $jsonValue;
            return $jsonValue;
        }
        
        // Tier 3: Class Constants (via fallback parameter)
        self::$cache[$key] = $fallback;
        return $fallback;
    }
    
    /**
     * Get value from JSON defaults
     */
    private static function getJsonDefault(string $key): mixed
    {
        // Load JSON defaults if not cached
        $needsLoad = (self::$jsonDefaults === null);
        if ($needsLoad) {
            self::loadJsonDefaults();
        }
        
        // Parse dot notation key (e.g., "general.items_per_page")
        $parts = explode('.', $key);
        $value = self::$jsonDefaults;
        
        foreach ($parts as $part) {
            $hasKey = isset($value[$part]);
            
            if (!$hasKey) {
                return null;
            }
            
            $value = $value[$part];
        }
        
        // Extract 'value' from config object if present
        $isConfigObject = is_array($value) && isset($value['value']);
        
        return $isConfigObject ? $value['value'] : $value;
    }
    
    /**
     * Load all JSON default files
     */
    private static function loadJsonDefaults(): void
    {
        self::$jsonDefaults = [];
        
        $configDir = PLUGIN_SLUG_PATH . 'config/';
        $configFiles = ['defaults.json', 'emails.json', 'presets.json'];
        
        foreach ($configFiles as $file) {
            $filePath = $configDir . $file;
            $fileExists = file_exists($filePath);
            
            if (!$fileExists) {
                continue;
            }
            
            try {
                $content = file_get_contents($filePath);
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                
                // Merge into defaults, excluding _meta
                unset($decoded['_meta']);
                self::$jsonDefaults = array_merge(self::$jsonDefaults, $decoded);
                
            } catch (\Throwable $e) {
                Logger::error('Failed to load config file', [
                    'file' => $filePath,
                    'action' => 'loadJsonDefaults',
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString()
                ]);
            }
        }
    }
    
    /**
     * Set setting value in database
     */
    public static function set(string $key, $value): bool
    {
        $optionName = Consts::OPTION_PREFIX . $key;
        
        try {
            $result = update_option($optionName, $value);
            
            // Update cache
            self::$cache[$key] = $value;
            
            Logger::debug('Setting updated', [
                'key' => $key,
                'value' => is_scalar($value) ? $value : '[complex]'
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to update setting', [
                'key' => $key,
                'file' => __FILE__,
                'action' => 'set',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Delete setting from database
     */
    public static function delete(string $key): bool
    {
        $optionName = Consts::OPTION_PREFIX . $key;
        
        try {
            $result = delete_option($optionName);
            
            // Clear from cache
            unset(self::$cache[$key]);
            
            return $result;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to delete setting', [
                'key' => $key,
                'file' => __FILE__,
                'action' => 'delete',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Get all settings for a group
     */
    public static function getGroup(string $group): array
    {
        $needsLoad = (self::$jsonDefaults === null);
        if ($needsLoad) {
            self::loadJsonDefaults();
        }
        
        $groupConfig = self::$jsonDefaults[$group] ?? [];
        $settings = [];
        
        foreach ($groupConfig as $key => $config) {
            $fullKey = "{$group}.{$key}";
            $settings[$key] = self::get($fullKey, $config['value'] ?? null);
        }
        
        return $settings;
    }
    
    /**
     * Check if setting exists in database
     */
    public static function exists(string $key): bool
    {
        $optionName = Consts::OPTION_PREFIX . $key;
        $value = get_option($optionName, null);
        
        return $value !== null;
    }
    
    /**
     * Clear settings cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$jsonDefaults = null;
    }
    
    /**
     * Get stored plugin version
     */
    public static function getVersion(): string
    {
        return get_option(Consts::OPTION_PREFIX . 'version', '0.0.0');
    }
    
    /**
     * Update stored plugin version
     */
    public static function setVersion(string $version): bool
    {
        return update_option(Consts::OPTION_PREFIX . 'version', $version);
    }
}
```

---

## 35.5 Seeding System

### Seeder Class

```php
<?php
namespace PluginNamespace\Config;

use PluginNamespace\Utils\Logger;

class Seeder
{
    /**
     * Seed trigger conditions
     */
    public const TRIGGER_FIRST_INSTALL = 'first_install';
    public const TRIGGER_VERSION_CHANGE = 'version_change';
    public const TRIGGER_MANUAL_RESET = 'manual_reset';
    public const TRIGGER_REACTIVATION = 'reactivation';
    
    /**
     * Run seeding based on trigger
     */
    public static function seed(string $trigger = self::TRIGGER_VERSION_CHANGE): array
    {
        $startTime = microtime(true);
        $results = [
            'trigger' => $trigger,
            'settings' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        
        Logger::info('Seeding started', ['trigger' => $trigger]);
        
        try {
            // Load JSON defaults
            $defaults = self::loadAllDefaults();
            
            // Determine seeding mode
            $forceOverwrite = in_array($trigger, [
                self::TRIGGER_FIRST_INSTALL,
                self::TRIGGER_MANUAL_RESET
            ], true);
            
            // Seed each setting
            foreach ($defaults as $group => $settings) {
                foreach ($settings as $key => $config) {
                    $result = self::seedSetting(
                        "{$group}.{$key}",
                        $config,
                        $forceOverwrite
                    );
                    
                    match ($result) {
                        'seeded' => $results['settings']++,
                        'skipped' => $results['skipped']++,
                        'error' => $results['errors']++
                    };
                }
            }
            
            // Seed additional data
            self::seedEmailTemplates($trigger);
            self::seedRoles($trigger);
            self::seedPresets($trigger);
            
            $duration = round(microtime(true) - $startTime, 3);
            
            Logger::info('Seeding completed', [
                'trigger' => $trigger,
                'duration_seconds' => $duration,
                'results' => $results
            ]);
            
        } catch (\Throwable $e) {
            Logger::error('Seeding failed', [
                'trigger' => $trigger,
                'file' => __FILE__,
                'action' => 'seed',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            $results['errors']++;
        }
        
        return $results;
    }
    
    /**
     * Load all default configurations
     */
    private static function loadAllDefaults(): array
    {
        $configDir = PLUGIN_SLUG_PATH . 'config/';
        $defaultsPath = $configDir . 'defaults.json';
        
        $fileExists = file_exists($defaultsPath);
        
        if (!$fileExists) {
            Logger::warning('defaults.json not found', ['path' => $defaultsPath]);
            return [];
        }
        
        try {
            $content = file_get_contents($defaultsPath);
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            // Remove metadata
            unset($decoded['_meta']);
            
            return $decoded;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to load defaults.json', [
                'file' => $defaultsPath,
                'action' => 'loadAllDefaults',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Seed individual setting
     */
    private static function seedSetting(
        string $key,
        array $config,
        bool $forceOverwrite
    ): string {
        try {
            // Check if already exists
            $exists = Settings::exists($key);
            $shouldSkip = $exists && !$forceOverwrite;
            
            if ($shouldSkip) {
                return 'skipped';
            }
            
            // Get value from config
            $value = $config['value'] ?? null;
            
            // Set in database
            $success = Settings::set($key, $value);
            
            return $success ? 'seeded' : 'error';
            
        } catch (\Throwable $e) {
            Logger::error('Failed to seed setting', [
                'key' => $key,
                'file' => __FILE__,
                'action' => 'seedSetting',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return 'error';
        }
    }
    
    /**
     * Seed email templates
     */
    private static function seedEmailTemplates(string $trigger): void
    {
        $configPath = PLUGIN_SLUG_PATH . 'config/emails.json';
        $fileExists = file_exists($configPath);
        
        if (!$fileExists) {
            return;
        }
        
        try {
            $content = file_get_contents($configPath);
            $templates = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            unset($templates['_meta']);
            
            foreach ($templates as $templateKey => $template) {
                $key = "email_template.{$templateKey}";
                $exists = Settings::exists($key);
                $isForceMode = in_array($trigger, [
                    self::TRIGGER_FIRST_INSTALL,
                    self::TRIGGER_MANUAL_RESET
                ], true);
                
                $shouldSeed = !$exists || $isForceMode;
                
                if ($shouldSeed) {
                    Settings::set($key, $template);
                }
            }
            
            Logger::debug('Email templates seeded', [
                'count' => count($templates)
            ]);
            
        } catch (\Throwable $e) {
            Logger::error('Failed to seed email templates', [
                'file' => $configPath,
                'action' => 'seedEmailTemplates',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Seed RBAC roles
     */
    private static function seedRoles(string $trigger): void
    {
        $configPath = PLUGIN_SLUG_PATH . 'config/roles.json';
        $fileExists = file_exists($configPath);
        
        if (!$fileExists) {
            return;
        }
        
        try {
            $content = file_get_contents($configPath);
            $roles = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            unset($roles['_meta']);
            
            // Store roles configuration
            Settings::set('roles', $roles);
            
            Logger::debug('Roles seeded', ['count' => count($roles)]);
            
        } catch (\Throwable $e) {
            Logger::error('Failed to seed roles', [
                'file' => $configPath,
                'action' => 'seedRoles',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Seed presets
     */
    private static function seedPresets(string $trigger): void
    {
        $configPath = PLUGIN_SLUG_PATH . 'config/presets.json';
        $fileExists = file_exists($configPath);
        
        if (!$fileExists) {
            return;
        }
        
        try {
            $content = file_get_contents($configPath);
            $presets = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            unset($presets['_meta']);
            
            Settings::set('presets', $presets);
            
            Logger::debug('Presets seeded', ['count' => count($presets)]);
            
        } catch (\Throwable $e) {
            Logger::error('Failed to seed presets', [
                'file' => $configPath,
                'action' => 'seedPresets',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Reset all settings to defaults
     */
    public static function reset(): array
    {
        Logger::info('Settings reset initiated');
        
        // Clear all plugin options
        global $wpdb;
        
        try {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    Consts::OPTION_PREFIX . '%'
                )
            );
            
            // Clear cache
            Settings::clearCache();
            
            // Re-seed with defaults
            return self::seed(self::TRIGGER_MANUAL_RESET);
            
        } catch (\Throwable $e) {
            Logger::error('Settings reset failed', [
                'file' => __FILE__,
                'action' => 'reset',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            return ['errors' => 1];
        }
    }
}
```

---

## 35.6 Version-Triggered Seeding

### Version Change Detection

```php
<?php
namespace PluginNamespace\Core;

use PluginNamespace\Config\Settings;
use PluginNamespace\Config\Seeder;
use PluginNamespace\Config\Consts;
use PluginNamespace\Utils\Logger;

class VersionManager
{
    /**
     * Check and handle version changes
     * 
     * WORKFLOW:
     * 1. Update Version (config/defaults.json)
     * 2. Update CHANGELOG.md
     * 3. Trigger Seeder (automatic on activation)
     */
    public static function checkVersion(): void
    {
        $storedVersion = Settings::getVersion();
        $currentVersion = Consts::PLUGIN_VERSION;
        
        $isFirstInstall = ($storedVersion === '0.0.0');
        $isUpgrade = version_compare($storedVersion, $currentVersion, '<');
        $isDowngrade = version_compare($storedVersion, $currentVersion, '>');
        
        if ($isFirstInstall) {
            self::handleFirstInstall();
            return;
        }
        
        if ($isUpgrade) {
            self::handleUpgrade($storedVersion, $currentVersion);
            return;
        }
        
        if ($isDowngrade) {
            self::handleDowngrade($storedVersion, $currentVersion);
            return;
        }
        
        // Same version - no action needed
        Logger::debug('Version check: no change', [
            'version' => $currentVersion
        ]);
    }
    
    /**
     * Handle first installation
     */
    private static function handleFirstInstall(): void
    {
        Logger::info('First installation detected', [
            'version' => Consts::PLUGIN_VERSION
        ]);
        
        // Run full seeding
        Seeder::seed(Seeder::TRIGGER_FIRST_INSTALL);
        
        // Store version
        Settings::setVersion(Consts::PLUGIN_VERSION);
        
        // Run installation hooks
        do_action('plugin_slug_installed', Consts::PLUGIN_VERSION);
    }
    
    /**
     * Handle version upgrade
     */
    private static function handleUpgrade(string $from, string $to): void
    {
        Logger::info('Version upgrade detected', [
            'from' => $from,
            'to' => $to
        ]);
        
        // Run migrations for specific versions
        self::runMigrations($from, $to);
        
        // Run incremental seeding (new settings only)
        Seeder::seed(Seeder::TRIGGER_VERSION_CHANGE);
        
        // Update stored version
        Settings::setVersion($to);
        
        // Run upgrade hooks
        do_action('plugin_slug_upgraded', $from, $to);
    }
    
    /**
     * Handle version downgrade (rare)
     */
    private static function handleDowngrade(string $from, string $to): void
    {
        Logger::warning('Version downgrade detected', [
            'from' => $from,
            'to' => $to
        ]);
        
        // Update stored version without seeding
        Settings::setVersion($to);
        
        // Run downgrade hooks
        do_action('plugin_slug_downgraded', $from, $to);
    }
    
    /**
     * Run version-specific migrations
     */
    private static function runMigrations(string $from, string $to): void
    {
        $migrations = [
            '1.1.0' => 'migrate_1_1_0',
            '1.2.0' => 'migrate_1_2_0',
            '2.0.0' => 'migrate_2_0_0'
        ];
        
        foreach ($migrations as $version => $method) {
            // Check if this migration applies
            $needsMigration = version_compare($from, $version, '<') 
                           && version_compare($to, $version, '>=');
            
            if ($needsMigration && method_exists(self::class, $method)) {
                try {
                    Logger::info('Running migration', ['version' => $version]);
                    self::$method();
                } catch (\Throwable $e) {
                    Logger::error('Migration failed', [
                        'version' => $version,
                        'file' => __FILE__,
                        'action' => $method,
                        'error' => $e->getMessage(),
                        'stack_trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }
    }
    
    /**
     * Example migration for version 1.1.0
     */
    private static function migrate_1_1_0(): void
    {
        // Rename old setting key
        $oldValue = Settings::get('old_setting_name');
        $hasOldValue = ($oldValue !== null);
        
        if ($hasOldValue) {
            Settings::set('new_setting_name', $oldValue);
            Settings::delete('old_setting_name');
        }
        
        Logger::info('Migration 1.1.0 completed');
    }
    
    /**
     * Example migration for version 1.2.0
     */
    private static function migrate_1_2_0(): void
    {
        // Add new required setting with default
        $exists = Settings::exists('new_feature_enabled');
        
        if (!$exists) {
            Settings::set('new_feature_enabled', true);
        }
        
        Logger::info('Migration 1.2.0 completed');
    }
    
    /**
     * Example migration for version 2.0.0
     */
    private static function migrate_2_0_0(): void
    {
        // Major version migration - restructure settings
        $oldSettings = Settings::getGroup('general');
        
        // Transform and save in new structure
        Settings::set('v2_settings', [
            'migrated_from' => '1.x',
            'migrated_at' => gmdate('c'),
            'data' => $oldSettings
        ]);
        
        Logger::info('Migration 2.0.0 completed');
    }
}
```

### Integration with Activation

```php
<?php
// In Activator.php
public static function activate(): void
{
    // ... other activation logic
    
    // Check version and seed if needed
    VersionManager::checkVersion();
    
    // ... rest of activation
}

// In Plugin.php init()
public function init(): void
{
    // Check for version changes on every admin load
    if (is_admin()) {
        add_action('admin_init', [VersionManager::class, 'checkVersion']);
    }
}
```

---

## 35.7 Transient Caching

### Transient Manager

```php
<?php
namespace PluginNamespace\Config;

use PluginNamespace\Utils\Logger;

class TransientCache
{
    /**
     * Get cached value or compute it
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        $transientKey = Consts::TRANSIENT_PREFIX . $key;
        $cached = get_transient($transientKey);
        
        $hasCached = ($cached !== false);
        if ($hasCached) {
            return $cached;
        }
        
        try {
            $value = $callback();
            set_transient($transientKey, $value, $ttl);
            return $value;
            
        } catch (\Throwable $e) {
            Logger::error('Transient callback failed', [
                'key' => $key,
                'file' => __FILE__,
                'action' => 'remember',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Get cached value
     */
    public static function get(string $key)
    {
        $transientKey = Consts::TRANSIENT_PREFIX . $key;
        $value = get_transient($transientKey);
        
        return $value !== false ? $value : null;
    }
    
    /**
     * Set cached value
     */
    public static function set(string $key, $value, int $ttl = 3600): bool
    {
        $transientKey = Consts::TRANSIENT_PREFIX . $key;
        return set_transient($transientKey, $value, $ttl);
    }
    
    /**
     * Delete cached value
     */
    public static function delete(string $key): bool
    {
        $transientKey = Consts::TRANSIENT_PREFIX . $key;
        return delete_transient($transientKey);
    }
    
    /**
     * Delete all plugin transients
     */
    public static function flush(): int
    {
        global $wpdb;
        
        try {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE %s 
                     OR option_name LIKE %s",
                    '_transient_' . Consts::TRANSIENT_PREFIX . '%',
                    '_transient_timeout_' . Consts::TRANSIENT_PREFIX . '%'
                )
            );
            
            Logger::info('Transient cache flushed', ['deleted' => $deleted]);
            return $deleted;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to flush transients', [
                'file' => __FILE__,
                'action' => 'flush',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
    
    /**
     * Tag-based cache invalidation
     */
    public static function invalidateTag(string $tag): void
    {
        // Store tag version to invalidate related caches
        $tagKey = "cache_tag_{$tag}";
        $currentVersion = (int) self::get($tagKey);
        self::set($tagKey, $currentVersion + 1, YEAR_IN_SECONDS);
    }
    
    /**
     * Get tagged cache key
     */
    public static function taggedKey(string $key, string $tag): string
    {
        $tagKey = "cache_tag_{$tag}";
        $tagVersion = (int) self::get($tagKey);
        
        return "{$key}_v{$tagVersion}";
    }
}
```

---

## 35.8 Settings Export/Import

### Export/Import Manager

```php
<?php
namespace PluginNamespace\Config;

use PluginNamespace\Utils\Logger;

class SettingsPortability
{
    /**
     * Export all settings to JSON
     */
    public static function export(): array
    {
        global $wpdb;
        
        try {
            $options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value 
                     FROM {$wpdb->options} 
                     WHERE option_name LIKE %s",
                    Consts::OPTION_PREFIX . '%'
                ),
                ARRAY_A
            );
            
            $settings = [];
            foreach ($options as $option) {
                $key = str_replace(Consts::OPTION_PREFIX, '', $option['option_name']);
                $settings[$key] = maybe_unserialize($option['option_value']);
            }
            
            return [
                '_meta' => [
                    'plugin_version' => Consts::PLUGIN_VERSION,
                    'exported_at' => gmdate('c'),
                    'site_url' => get_site_url()
                ],
                'settings' => $settings
            ];
            
        } catch (\Throwable $e) {
            Logger::error('Settings export failed', [
                'file' => __FILE__,
                'action' => 'export',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Import settings from JSON
     */
    public static function import(array $data, bool $overwrite = false): array
    {
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        
        $hasSettings = isset($data['settings']) && is_array($data['settings']);
        
        if (!$hasSettings) {
            Logger::warning('Invalid import data structure');
            return $results;
        }
        
        try {
            foreach ($data['settings'] as $key => $value) {
                $exists = Settings::exists($key);
                $shouldSkip = $exists && !$overwrite;
                
                if ($shouldSkip) {
                    $results['skipped']++;
                    continue;
                }
                
                $success = Settings::set($key, $value);
                
                if ($success) {
                    $results['imported']++;
                } else {
                    $results['errors']++;
                }
            }
            
            Logger::info('Settings imported', $results);
            
        } catch (\Throwable $e) {
            Logger::error('Settings import failed', [
                'file' => __FILE__,
                'action' => 'import',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            $results['errors']++;
        }
        
        return $results;
    }
}
```

---

## 35.9 Checklist

### Configuration Hierarchy
- [ ] 3-tier hierarchy implemented (Database > JSON > Constants)
- [ ] Settings service reads from all tiers in order
- [ ] Fallback values defined in class constants
- [ ] JSON defaults structured with metadata

### Seeding System
- [ ] Seeder runs on first install
- [ ] Seeder runs on version change
- [ ] Incremental seeding for upgrades (new settings only)
- [ ] Full seeding for first install and reset
- [ ] All seed operations wrapped in try-catch

### Version Management
- [ ] Version stored in database
- [ ] Version compared on activation and admin_init
- [ ] Version-specific migrations defined
- [ ] CHANGELOG.md updated with each version

### Error Handling
- [ ] All file reads wrapped in try-catch
- [ ] All database operations wrapped in try-catch
- [ ] Errors logged with file, action, message, stack trace
- [ ] JSON parse errors handled gracefully

### Caching
- [ ] Settings cached to avoid repeated queries
- [ ] Cache invalidation on setting update
- [ ] Transient cache for expensive operations
- [ ] Tag-based cache invalidation supported

### Portability
- [ ] Settings export to JSON available
- [ ] Settings import from JSON available
- [ ] Export includes metadata (version, date)
- [ ] Import supports overwrite mode

---

## Workflow Summary

```
┌─────────────────────────────────────────────────────────────┐
│              VERSION CHANGE WORKFLOW                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   1. UPDATE VERSION                                         │
│      └── Edit config/defaults.json → _meta.version          │
│      └── Edit Consts.php → PLUGIN_VERSION                   │
│                                                             │
│   2. UPDATE CHANGELOG                                       │
│      └── Add entry to CHANGELOG.md                          │
│      └── Document new settings, migrations                  │
│                                                             │
│   3. TRIGGER SEEDER (Automatic)                             │
│      └── On activation: VersionManager::checkVersion()      │
│      └── Detects version mismatch                           │
│      └── Runs migrations for skipped versions               │
│      └── Seeds new settings (incremental)                   │
│      └── Updates stored version                             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - Naming conventions
- [02-error-management-foundation.md](../01-foundation/02-error-management-foundation.md) - Error handling
- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Logging standards
- [02-configuration-hierarchy-systems.md](../02-systems/02-configuration-hierarchy-systems.md) - General config patterns
- [01-plugin-structure-wordpress.md](./01-plugin-structure-wordpress.md) - Activation hooks
- [04-admin-ui-wordpress.md](./04-admin-ui-wordpress.md) - Settings pages
