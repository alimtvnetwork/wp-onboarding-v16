# Memory: features/wordpress-plugin/admin-dashboard
Updated: 2026-02-05

The Riseup Asia Uploader WordPress plugin (v1.5.0) includes a WordPress admin dashboard with two main pages:

## Admin Menu

- **Riseup Uploader** (main menu with dashicons-upload icon)
  - **Activity Logs** - View all API activity and operations
  - **Settings** - Configure endpoints and authentication

## Activity Logs Page

Features:
- Filterable table of all transactions (action, user, status, plugin, date range)
- Action badges with color coding (upload=green, delete=red, etc.)
- Status badges (success=green, failed=red)
- Pagination (50 records per page)
- Details modal for viewing JSON details
- Exportable/filterable log history

## Settings Page

Features:
- Plugin information display (version, namespace, REST API base URL)
- **Endpoint Configuration Table**:
  - Toggle switches to enable/disable each endpoint
  - Toggle switches to require/bypass authentication per endpoint
  - Warning message about security implications

## Endpoint Settings

Each endpoint can be individually configured:
- `status` - Status Check
- `upload` - Plugin Upload
- `plugins` - List Plugins
- `plugin_files` - Plugin Files listing
- `plugin_file` - File Content retrieval
- `export_self` - Export Self
- `posts` - Blog Posts
- `categories` - Categories
- `logs` - Logs API
- `logs_stats` - Logs Stats
- `openapi` - OpenAPI Spec

## Settings Storage

Settings are stored in WordPress options table with key `riseup_asia_settings`:
```php
array(
    'endpoints' => array(
        'status' => array('enabled' => true, 'auth_required' => true),
        // ... other endpoints
    ),
)
```

## Related Files

- `wp-plugins/riseup-asia-uploader/includes/class-admin.php` - Admin class
- `wp-plugins/riseup-asia-uploader/templates/admin-logs.php` - Logs page template
- `wp-plugins/riseup-asia-uploader/templates/admin-settings.php` - Settings page template
- `wp-plugins/riseup-asia-uploader/assets/admin.css` - Admin styles
