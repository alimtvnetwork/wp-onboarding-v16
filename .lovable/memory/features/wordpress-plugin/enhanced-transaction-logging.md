# Memory: features/wordpress-plugin/enhanced-transaction-logging
Updated: 2026-02-06

## Overview

Plugin v1.8.0+ includes enhanced transaction logging with additional fields for richer audit trails and debugging context.

## New Transaction Fields

| Field | Type | Description |
|-------|------|-------------|
| `plugin_file` | TEXT | Full plugin file path (e.g., "akismet/akismet.php") |
| `was_active` | INTEGER | Previous active state (0=inactive, 1=active) |
| `triggered_by` | TEXT | Source of the action |
| `agent_site_id` | INTEGER | FK to agent_sites if action was agent-pushed |

## Triggered By Values

Constants defined in `includes/constants.php`:

| Constant | Value | Description |
|----------|-------|-------------|
| `RISEUP_TRIGGERED_BY_API` | `api` | REST API request |
| `RISEUP_TRIGGERED_BY_DASHBOARD` | `dashboard` | WordPress admin UI |
| `RISEUP_TRIGGERED_BY_AGENT` | `agent_push` | Master site pushed action |
| `RISEUP_TRIGGERED_BY_CRON` | `cron` | Scheduled task |
| `RISEUP_TRIGGERED_BY_CLI` | `cli` | WP-CLI command |

## Database Migration

Migration v3 in `includes/class-database.php` adds the new columns:

```php
$columns = array(
    'plugin_file'    => 'TEXT',
    'was_active'     => 'INTEGER',
    'triggered_by'   => 'TEXT',
    'agent_site_id'  => 'INTEGER',
);

foreach ($columns as $column => $type) {
    $this->pdo->exec("ALTER TABLE transactions ADD COLUMN {$column} {$type}");
}
```

## API Usage

### Standard Method (Backward Compatible)

```php
$db->log_transaction(
    RISEUP_ACTION_ENABLE,
    'my-plugin',
    null,
    $user->user_login,
    $user->ID,
    $_SERVER['REMOTE_ADDR'],
    array('version' => '1.0.0'),
    RISEUP_STATUS_SUCCESS,
    null,
    array(  // Enhanced fields (optional)
        'plugin_file'   => 'my-plugin/my-plugin.php',
        'was_active'    => false,
        'triggered_by'  => RISEUP_TRIGGERED_BY_API,
        'agent_site_id' => null,
    )
);
```

### Convenience Wrapper

```php
$db->log_enhanced_transaction(array(
    'action'        => RISEUP_ACTION_ENABLE,
    'plugin_slug'   => 'my-plugin',
    'plugin_file'   => 'my-plugin/my-plugin.php',
    'was_active'    => false,
    'triggered_by'  => RISEUP_TRIGGERED_BY_API,
    'user_login'    => $user->user_login,
    'user_id'       => $user->ID,
    'ip_address'    => $_SERVER['REMOTE_ADDR'],
    'status'        => RISEUP_STATUS_SUCCESS,
));
```

## Query Examples

```sql
-- Find all API-triggered actions
SELECT * FROM transactions WHERE triggered_by = 'api' ORDER BY created_at DESC;

-- Find plugins that were active before being disabled
SELECT plugin_slug, plugin_file FROM transactions 
WHERE action = 'disable' AND was_active = 1;

-- Find all actions pushed from agent sites
SELECT t.*, a.name as agent_name 
FROM transactions t
JOIN agent_sites a ON t.agent_site_id = a.id
WHERE t.triggered_by = 'agent_push';
```

## Related Files

- `wp-plugins/riseup-asia-uploader/includes/class-database.php` - Migration v3 & methods
- `wp-plugins/riseup-asia-uploader/includes/constants.php` - Triggered by constants
