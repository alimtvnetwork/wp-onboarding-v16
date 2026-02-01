# 08 – Publish Service

> **Location:** `spec/wp-plugin-publish/01-backend/08-publish-service.md`  
> **Updated:** 2026-02-01

---

## Overview

The Publish Service manages the complete plugin publishing workflow, from preparation through activation on remote WordPress sites. It coordinates with Sync, Backup, and Validation services.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Publish Service                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌────────────┐   ┌────────────┐   ┌────────────┐   ┌────────────┐ │
│  │  Validate  │──▶│   Build    │──▶│  Transfer  │──▶│  Activate  │ │
│  └────────────┘   └────────────┘   └────────────┘   └────────────┘ │
│        │                │                │                │         │
│        ▼                ▼                ▼                ▼         │
│  ┌────────────┐   ┌────────────┐   ┌────────────┐   ┌────────────┐ │
│  │   Linter   │   │  Packager  │   │   Upload   │   │  Verify    │ │
│  └────────────┘   └────────────┘   └────────────┘   └────────────┘ │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Class Definition

```php
<?php
namespace PluginsOnboard\Services;

class Publish_Service {
    
    /** @var Sync_Service */
    private Sync_Service $sync;
    
    /** @var Backup_Service */
    private Backup_Service $backup;
    
    /** @var Plugin_Validator */
    private Plugin_Validator $validator;
    
    /**
     * Publish a plugin to a specific site
     */
    public function publish(
        string $plugin_slug,
        int $site_id,
        array $options = []
    ): Publish_Result;
    
    /**
     * Publish to multiple sites
     */
    public function publish_to_all(
        string $plugin_slug,
        array $site_ids = [],
        array $options = []
    ): array;
    
    /**
     * Create a release package
     */
    public function package(
        string $plugin_slug,
        string $version
    ): Package_Result;
    
    /**
     * Validate plugin before publish
     */
    public function validate(string $plugin_slug): Validation_Result;
    
    /**
     * Rollback to previous version
     */
    public function rollback(
        string $plugin_slug,
        int $site_id,
        ?string $version = null
    ): Rollback_Result;
    
    /**
     * Get publish history
     */
    public function get_history(
        string $plugin_slug,
        ?int $site_id = null
    ): array;
}
```

---

## Publishing Pipeline

### Stage 1: Validation

```php
$validation = $this->validate($plugin_slug);

// Checks performed:
// - Plugin header validity
// - PHP syntax check
// - WordPress coding standards (optional)
// - Security scan (optional)
// - Version increment check
```

### Stage 2: Build

```php
// Optional build steps
$build_steps = [
    'compile_assets' => true,    // Sass, TypeScript, etc.
    'minify_js' => true,
    'minify_css' => true,
    'generate_pot' => true,      // Translation template
    'update_readme' => true
];
```

### Stage 3: Package

```php
$package = $this->package($plugin_slug, $version);

// Creates ZIP with:
// - All plugin files
// - Manifest file
// - Checksums file
// - Version metadata
```

### Stage 4: Transfer

```php
// Upload package to remote site
$transfer = $this->transfer($package, $site_id);

// Options:
// - Chunked upload for large files
// - Resume support
// - Integrity verification
```

### Stage 5: Activate

```php
// Remote operations:
// 1. Extract package
// 2. Run pre-activation hooks
// 3. Activate plugin
// 4. Run post-activation hooks
// 5. Verify activation
```

---

## Publish Options

```php
[
    // Pre-publish
    'validate' => true,
    'backup_first' => true,
    'dry_run' => false,
    
    // Build
    'build_assets' => true,
    'minify' => true,
    'source_maps' => false,
    
    // Transfer
    'force_full_sync' => false,
    'verify_checksums' => true,
    
    // Activation
    'activate_after_publish' => true,
    'clear_cache' => true,
    'run_migrations' => true,
    
    // Rollback
    'rollback_on_failure' => true,
    'keep_backup_days' => 7
]
```

---

## Publish State Machine

```
┌─────────┐
│ PENDING │
└────┬────┘
     │ start
     ▼
┌──────────────┐
│  VALIDATING  │──────────▶ FAILED
└──────┬───────┘
       │ valid
       ▼
┌──────────────┐
│   BUILDING   │──────────▶ FAILED
└──────┬───────┘
       │ built
       ▼
┌──────────────┐
│  PACKAGING   │──────────▶ FAILED
└──────┬───────┘
       │ packaged
       ▼
┌──────────────┐
│ TRANSFERRING │──────────▶ FAILED
└──────┬───────┘
       │ transferred
       ▼
┌──────────────┐
│  ACTIVATING  │──────────▶ FAILED ──▶ ROLLING_BACK
└──────┬───────┘
       │ activated
       ▼
┌──────────────┐
│  VERIFYING   │──────────▶ FAILED ──▶ ROLLING_BACK
└──────┬───────┘
       │ verified
       ▼
┌──────────────┐
│   COMPLETE   │
└──────────────┘
```

---

## Package Structure

```
my-plugin-1.2.0.zip
├── my-plugin/
│   ├── my-plugin.php
│   ├── includes/
│   ├── assets/
│   ├── languages/
│   └── readme.txt
├── manifest.json
├── checksums.json
└── metadata.json
```

### Manifest Format

```json
{
  "plugin_slug": "my-plugin",
  "version": "1.2.0",
  "wp_requires": "5.8",
  "wp_tested": "6.4",
  "php_requires": "7.4",
  "created_at": "2024-01-31T12:00:00Z",
  "files_count": 45,
  "total_size": 524288,
  "checksum": "sha256:abc123..."
}
```

---

## Publish Result Structure

```php
class Publish_Result {
    public string $publish_id;
    public string $status;          // 'success' | 'failed' | 'rolled_back'
    public string $plugin_slug;
    public string $version;
    public int $site_id;
    public array $stages;           // Status per stage
    public float $duration_seconds;
    public ?string $error;
    public ?string $rollback_id;
    public array $warnings;
}
```

### Stage Results

```php
'stages' => [
    'validate' => ['status' => 'success', 'duration' => 1.2],
    'build' => ['status' => 'success', 'duration' => 5.4],
    'package' => ['status' => 'success', 'duration' => 2.1],
    'transfer' => ['status' => 'success', 'duration' => 8.7],
    'activate' => ['status' => 'success', 'duration' => 1.5],
    'verify' => ['status' => 'success', 'duration' => 0.8]
]
```

---

## Event Emissions

```php
// Publish lifecycle
'publish:started'       => ['publish_id', 'plugin_slug', 'site_id', 'version']
'publish:stage_start'   => ['publish_id', 'stage']
'publish:stage_complete'=> ['publish_id', 'stage', 'result']
'publish:progress'      => ['publish_id', 'stage', 'progress']
'publish:complete'      => ['publish_id', 'result']
'publish:failed'        => ['publish_id', 'stage', 'error']
'publish:rollback'      => ['publish_id', 'reason']
```

---

## Validation Rules

| Rule | Severity | Description |
|------|----------|-------------|
| Valid plugin header | Error | Must have Name, Version |
| PHP syntax | Error | All PHP files must parse |
| Version increment | Warning | Should be > current |
| Readme exists | Warning | readme.txt recommended |
| No debug code | Warning | No var_dump, error_log |
| Security headers | Warning | Prevent direct access |

---

## Error Handling

| Error | Code | Recovery |
|-------|------|----------|
| Validation failed | `PUB_VALIDATION_FAILED` | Fix issues, retry |
| Build failed | `PUB_BUILD_FAILED` | Check build config |
| Package failed | `PUB_PACKAGE_FAILED` | Check disk space |
| Transfer failed | `PUB_TRANSFER_FAILED` | Retry, check network |
| Activation failed | `PUB_ACTIVATION_FAILED` | Rollback, check logs |
| Remote error | `PUB_REMOTE_ERROR` | Check site status |

---

## Version History

```php
// Get publish history
$history = $publish->get_history('my-plugin', $site_id);

// Returns:
[
    [
        'version' => '1.2.0',
        'published_at' => '2024-01-31T12:00:00Z',
        'status' => 'success',
        'can_rollback' => true
    ],
    [
        'version' => '1.1.0',
        'published_at' => '2024-01-15T10:00:00Z',
        'status' => 'success',
        'can_rollback' => true
    ]
]
```

---

*See also: [07-sync-service.md](07-sync-service.md), [09-backup-service.md](09-backup-service.md)*
