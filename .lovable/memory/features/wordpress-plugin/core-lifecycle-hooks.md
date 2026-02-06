# Memory: features/wordpress-plugin/core-lifecycle-hooks
Updated: 2026-02-06

## Overview

The WordPress companion plugin hooks into WordPress core plugin lifecycle events to capture ALL plugin actions, not just those triggered via the Riseup API. This ensures a complete audit trail regardless of whether actions come from the WordPress dashboard, WP-CLI, cron jobs, or other plugins.

## Hooks Registered

| Hook | WordPress Action | Description |
|------|-----------------|-------------|
| `activated_plugin` | Plugin activated | Fires after a plugin is activated |
| `deactivated_plugin` | Plugin deactivated | Fires after a plugin is deactivated |
| `deleted_plugin` | Plugin deleted | Fires after a plugin is deleted |

## Hook Registration

Located in `riseup-asia-uploader.php` constructor:

```php
add_action('activated_plugin', array($this, 'on_plugin_activated'), 10, 2);
add_action('deactivated_plugin', array($this, 'on_plugin_deactivated'), 10, 2);
add_action('deleted_plugin', array($this, 'on_plugin_deleted'), 10, 2);
```

## Trigger Source Detection

The `detect_trigger_source()` method determines how the action was triggered:

```php
private function detect_trigger_source() {
    if (defined('WP_CLI') && WP_CLI) {
        return RISEUP_TRIGGERED_BY_CLI;        // 'cli'
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return RISEUP_TRIGGERED_BY_CRON;       // 'cron'
    }
    if ($this->is_rest_request()) {
        return RISEUP_TRIGGERED_BY_API;        // 'api'
    }
    return RISEUP_TRIGGERED_BY_DASHBOARD;      // 'dashboard'
}
```

## Duplicate Prevention

REST API actions (via our plugin endpoints) are NOT logged via hooks to prevent duplicates:

```php
public function on_plugin_activated($plugin, $network_wide = false) {
    // Skip if this is our own API action
    if ($this->is_rest_request()) {
        return;
    }
    // ... log the action
}
```

## Transaction Details Logged

Each hook logs:
- `plugin_file`: Full path (e.g., "akismet/akismet.php")
- `triggered_by`: Source detection result
- `hook_source`: Which WordPress hook fired (for debugging)
- `network_wide` / `network_deactivating`: Multisite context

## Example Transaction Record

```json
{
  "action": "enable",
  "plugin_slug": "akismet",
  "status": "success",
  "triggered_by": "dashboard",
  "details": {
    "plugin_file": "akismet/akismet.php",
    "network_wide": false,
    "hook_source": "activated_plugin"
  },
  "source_machine": null,
  "ip_address": "192.168.1.100",
  "user_login": "admin"
}
```

## Related Files

- `wp-plugins/riseup-asia-uploader/riseup-asia-uploader.php` - Hook registration and handlers
- `wp-plugins/riseup-asia-uploader/includes/constants.php` - TRIGGERED_BY constants
- `wp-plugins/riseup-asia-uploader/includes/class-logger.php` - Transaction logging
