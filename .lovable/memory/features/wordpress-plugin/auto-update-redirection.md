# Memory: features/wordpress-plugin/auto-update-redirection
Updated: 2026-02-06

## Overview

The WordPress plugin implements an auto-update mechanism that resolves package URLs via 301 redirects. The final URL is cached locally to minimize redirection chains, with automatic re-resolution logic if the cached URL fails. Update URLs and cache states are managed directly through the plugin's admin settings.

## Architecture

### 301 Redirect Resolution

1. Master URL is configured in plugin settings (e.g., `https://updates.example.com/plugin`)
2. On update check, the resolver follows 301 redirects to find the final URL
3. Final URL is cached with timestamp in SQLite
4. Cache is valid for configurable duration (default: 7 days)

### Fallback Logic

```
┌─────────────────────────────────────────────────────────┐
│                 Update Check Flow                       │
├─────────────────────────────────────────────────────────┤
│ 1. Check if cached URL exists and is valid            │
│    ├── YES → Use cached URL for update                │
│    │         ├── SUCCESS → Update complete            │
│    │         └── FAIL → Go to step 2                  │
│    └── NO → Go to step 2                              │
│                                                        │
│ 2. Resolve master URL (follow 301 redirects)          │
│    ├── SUCCESS → Cache new URL, use for update        │
│    └── FAIL → Return error, log failure              │
└─────────────────────────────────────────────────────────┘
```

### Database Schema

```sql
CREATE TABLE IF NOT EXISTS update_settings (
    id INTEGER PRIMARY KEY,
    master_url TEXT NOT NULL,
    resolved_url TEXT,
    resolved_at TEXT,
    cache_days INTEGER DEFAULT 7,
    last_check TEXT,
    last_error TEXT,
    enabled INTEGER DEFAULT 0
);
```

## Settings UI

The plugin settings page includes an "Auto-Update" section:

| Field | Type | Description |
|-------|------|-------------|
| Master Update URL | Text | The 301 redirect URL |
| Resolved URL | Read-only | Currently cached URL |
| Cache Duration | Dropdown | 1/7/14/30 days |
| Last Check | Timestamp | Last update check time |
| Last Error | Text | Most recent error |
| [Clear Cache] | Button | Force re-resolution |
| [Check Now] | Button | Trigger immediate check |
| Enable Auto-Update | Toggle | Enable/disable feature |

## WordPress Hooks

```php
// Hook into WordPress update system
add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_plugin_update'));
add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
```

## Related Files

- `wp-plugins/riseup-asia-uploader/includes/class-update-resolver.php`
- `wp-plugins/riseup-asia-uploader/includes/class-database.php`
- `wp-plugins/riseup-asia-uploader/templates/admin-settings.php`
