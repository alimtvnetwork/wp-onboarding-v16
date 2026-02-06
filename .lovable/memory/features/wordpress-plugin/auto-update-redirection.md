# Memory: features/wordpress-plugin/auto-update-redirection
Updated: 2026-02-06

## Overview

The WordPress plugin implements an auto-update mechanism that resolves package URLs via 301 redirects. The final URL is cached locally to minimize redirection chains, with automatic re-resolution logic if the cached URL fails. Update URLs and cache states are managed directly through the plugin's admin settings.

## Architecture

### 301 Redirect Resolution

1. Master URL is configured in plugin settings (e.g., `https://updates.example.com/plugin`)
2. On update check, the resolver follows 301/302/307/308 redirects to find the final URL
3. Final URL is cached with timestamp in WordPress options
4. Cache is valid for configurable duration (default: 7 days)

### Fallback Logic

```
┌─────────────────────────────────────────────────────────────┐
│                 Update Check Flow                           │
├─────────────────────────────────────────────────────────────┤
│ 1. Check if cached URL exists and is valid                 │
│    ├── YES → Use cached URL for update                     │
│    │         ├── SUCCESS → Update complete                 │
│    │         └── FAIL → Go to step 2                       │
│    └── NO → Go to step 2                                   │
│                                                             │
│ 2. Resolve master URL (follow 301 redirects)               │
│    ├── SUCCESS → Cache new URL, use for update             │
│    └── FAIL → Return error, log failure                    │
└─────────────────────────────────────────────────────────────┘
```

### Storage

Settings are stored in WordPress options table under `riseup_update_settings`:

```php
array(
    'enabled'        => bool,    // Auto-update enabled
    'master_url'     => string,  // The 301 redirect URL
    'resolved_url'   => string,  // Currently cached URL
    'resolved_at'    => string,  // When URL was resolved
    'cache_days'     => int,     // Days to cache (1-30)
    'last_check'     => string,  // Last update check time
    'last_error'     => string,  // Most recent error
    'package_url'    => string,  // Actual ZIP download URL
    'new_version'    => string,  // Version from update server
    'update_info'    => array,   // Additional update metadata
)
```

## Settings UI

The plugin settings page includes an "Auto-Update" section:

| Field | Type | Description |
|-------|------|-------------|
| Enable Auto-Update | Toggle | Enable/disable feature |
| Master Update URL | Text | The 301 redirect URL |
| Cache Duration | Dropdown | 1/7/14/30 days |
| Resolved URL | Read-only | Currently cached URL |
| Last Check | Timestamp | Last update check time |
| Last Error | Text | Most recent error |
| Available Version | Badge | Shows if update available |
| [Test Connection] | Button | Resolves URL and tests |
| [Clear Cache] | Button | Force re-resolution |
| [Check Now] | Button | Trigger immediate check |

## WordPress Hooks

```php
// Hook into WordPress update system
add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_plugin_update'));
add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
```

## Update Server Response Format

The update server can return either:

1. **JSON metadata** (recommended):
```json
{
    "version": "1.9.0",
    "package": "https://cdn.example.com/plugin-1.9.0.zip",
    "tested": "6.4",
    "requires": "5.6",
    "requires_php": "7.4",
    "changelog": "- Feature X\n- Bug fix Y"
}
```

2. **Direct ZIP URL**: If the resolved URL returns a ZIP file directly, it's used as the package URL.

## Related Files

- `wp-plugins/riseup-asia-uploader/includes/class-update-resolver.php` - Main resolver class
- `wp-plugins/riseup-asia-uploader/includes/class-admin.php` - AJAX handlers
- `wp-plugins/riseup-asia-uploader/templates/admin-settings.php` - Settings UI
- `wp-plugins/riseup-asia-uploader/includes/constants.php` - Update constants
