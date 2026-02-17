# Auto-Update with 301 Redirect Resolution

**Version:** 1.0.0  
**Updated:** 2026-02-06  
**Plugin Version:** 1.8.0+

---

## Overview

The Riseup Asia Uploader plugin supports automatic updates via a configurable master URL that resolves through 301 redirects. This enables flexible update distribution where the actual download location can change without requiring plugin modifications.

---

## Architecture

### Components

| Component | File | Purpose |
|-----------|------|---------|
| Update Resolver | `UpdateResolver.php` | URL resolution, caching, WordPress hook integration |
| Admin Class | `Admin.php` | AJAX handlers for settings UI |
| Settings Template | `admin-settings.php` | UI for configuration |
| Constants | `constants.php` | Default values and action constants |

### Flow Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                        AUTO-UPDATE FLOW                                   │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  WordPress                     Plugin                    Update Server   │
│  ─────────                     ──────                    ─────────────   │
│      │                            │                            │         │
│      │ update check trigger       │                            │         │
│      │ ────────────────────────>  │                            │         │
│      │                            │                            │         │
│      │                            │  check cache validity      │         │
│      │                            │ ◄───────────────────┐      │         │
│      │                            │                     │      │         │
│      │                            │  if valid           │      │         │
│      │                            │ ─────────────────>  │      │         │
│      │                            │  use cached URL     │      │         │
│      │                            │                            │         │
│      │                            │  if expired/missing        │         │
│      │                            │ ────────────────────────>  │         │
│      │                            │  HEAD request to master    │         │
│      │                            │                            │         │
│      │                            │  <───────────────────────  │         │
│      │                            │  301 redirect              │         │
│      │                            │                            │         │
│      │                            │  follow redirect chain     │         │
│      │                            │ ────────────────────────>  │         │
│      │                            │                            │         │
│      │                            │  <───────────────────────  │         │
│      │                            │  final URL (200 OK)        │         │
│      │                            │                            │         │
│      │                            │  cache resolved URL        │         │
│      │                            │ ◄───────────────────┐      │         │
│      │                            │                            │         │
│      │                            │  fetch update info         │         │
│      │                            │ ────────────────────────>  │         │
│      │                            │                            │         │
│      │                            │  <───────────────────────  │         │
│      │  <─────────────────────────│  JSON metadata or ZIP      │         │
│      │  update transient          │                            │         │
│      │                            │                            │         │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Configuration

### Settings Storage

Settings are stored in WordPress options table under key `riseup_update_settings`:

```php
array(
    'enabled'        => bool,    // Feature enabled
    'master_url'     => string,  // The 301 redirect URL
    'resolved_url'   => string,  // Cached final URL
    'resolved_at'    => string,  // ISO datetime of resolution
    'cache_days'     => int,     // Cache validity (1-30)
    'last_check'     => string,  // Last update check datetime
    'last_error'     => string,  // Last error message
    'package_url'    => string,  // ZIP download URL
    'new_version'    => string,  // Available version
    'update_info'    => array,   // Full update metadata
)
```

### Constants

```php
// Default cache duration in days
RISEUP_UPDATE_CACHE_DAYS_DEFAULT = 7

// Maximum redirects to follow
RISEUP_UPDATE_MAX_REDIRECTS = 5

// Transaction log action types
RISEUP_ACTION_UPDATE_CHECK    = 'update_check'
RISEUP_ACTION_UPDATE_RESOLVE  = 'update_resolve'
RISEUP_ACTION_UPDATE_DOWNLOAD = 'update_download'
RISEUP_ACTION_UPDATE_INSTALL  = 'update_install'
```

---

## API Reference

### Riseup_Update_Resolver Class

#### get_instance()
Returns singleton instance.

```php
$resolver = Riseup_Update_Resolver::get_instance();
```

#### get_settings()
Returns current settings array with defaults applied.

```php
$settings = $resolver->get_settings();
// array('enabled' => false, 'master_url' => '', ...)
```

#### save_settings(array $settings)
Saves settings, merging with existing values.

```php
$resolver->save_settings(array('enabled' => true));
```

#### resolve_url(string $url, int $max_redirects = 5)
Follows redirects to find final URL.

```php
$final = $resolver->resolve_url('https://updates.example.com/plugin');
// Returns: 'https://cdn.example.com/plugin-1.8.0.json' or WP_Error
```

#### get_update_url(bool $force_resolve = false)
Returns cached URL or resolves fresh.

```php
$url = $resolver->get_update_url();
// Uses cache if valid

$url = $resolver->get_update_url(true);
// Forces fresh resolution
```

#### clear_cache()
Clears cached resolved URL.

```php
$resolver->clear_cache();
```

#### fetch_update_info(bool $force_check = false)
Fetches update metadata from server.

```php
$info = $resolver->fetch_update_info();
// array('version' => '1.9.0', 'package' => 'https://...', ...)
```

#### test_connection()
Tests connection and returns result.

```php
$result = $resolver->test_connection();
// array('success' => true, 'message' => '...', 'resolved_url' => '...')
```

---

## WordPress Integration

### Update Check Hook

```php
add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_plugin_update'));
```

The `check_for_plugin_update()` method:
1. Checks if auto-update is enabled
2. Fetches update info from server
3. Compares versions
4. Adds plugin to update transient if newer version available

### Plugin Info Hook

```php
add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
```

Provides detailed information for WordPress "View Details" modal.

---

## Update Server Response

### JSON Metadata Format (Recommended)

```json
{
    "version": "1.9.0",
    "package": "https://cdn.example.com/plugin-1.9.0.zip",
    "tested": "6.4",
    "requires": "5.6",
    "requires_php": "7.4",
    "changelog": "## 1.9.0\n- New feature X\n- Bug fix Y"
}
```

### Direct ZIP Response

If the resolved URL returns a ZIP file (content-type not JSON), it's used directly as the package URL. Version detection in this case requires manual configuration.

---

## Admin UI

### Settings Section

Located in WordPress Admin → Riseup Uploader → Settings

| Control | Description |
|---------|-------------|
| Enable Auto-Update | Toggle to enable/disable feature |
| Master Update URL | URL that will be resolved through redirects |
| Cache Duration | How long to cache resolved URL (1/7/14/30 days) |
| Resolved URL | Display of currently cached URL |
| Last Check | When update was last checked |
| Last Error | Most recent error (if any) |
| Available Version | Shows version if update available |
| Test Connection | Button to test URL resolution |
| Clear Cache | Button to force re-resolution |
| Check Now | Button to check for updates immediately |

### AJAX Actions

| Action | Handler | Description |
|--------|---------|-------------|
| `riseup_test_update_connection` | `ajax_test_update_connection` | Tests URL resolution |
| `riseup_clear_update_cache` | `ajax_clear_update_cache` | Clears cached URL |
| `riseup_check_for_updates` | `ajax_check_for_updates` | Forces update check |

All AJAX actions require `riseup_admin_nonce` and `manage_options` capability.

---

## Error Handling

### Fallback Logic

1. If cached URL returns error → clear cache and re-resolve
2. If resolution fails → log error and continue with master URL
3. If master URL fails → update `last_error` and return error

### Error Storage

Errors are stored in settings:
```php
$resolver->save_settings(array(
    'last_error' => 'Connection timeout',
    'last_check' => current_time('mysql', true),
));
```

---

## Security Considerations

1. **SSL Verification**: All HTTP requests use SSL verification
2. **Capability Check**: AJAX handlers require `manage_options`
3. **Nonce Verification**: All AJAX actions verify nonce
4. **URL Sanitization**: Master URL is sanitized with `esc_url_raw()`

---

## Related Documentation

- [WordPress Plugin Update API](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Memory: Auto-Update Redirection](/.lovable/memory/features/wordpress-plugin/auto-update-redirection.md)
