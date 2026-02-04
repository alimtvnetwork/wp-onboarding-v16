# Plugin Uploader Helper

A lightweight WordPress plugin providing a secure REST API for remote plugin management.

## Features

- **Upload plugins** via ZIP file (multipart or base64-encoded)
- **Enable/Disable plugins** remotely
- **Delete plugins** via REST API
- **Replace single files** within a plugin directory
- **List files** with hashes for sync comparison

## Installation

1. Copy the `plugins-uploader-helper` folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin in WordPress Admin → Plugins
3. Configure an Application Password for your WordPress user (Users → Profile → Application Passwords)

## REST API Endpoints

All endpoints require Basic Authentication using WordPress Application Passwords.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/plugin-uploader/v1/status` | Check helper availability |
| POST | `/wp-json/plugin-uploader/v1/upload` | Upload and install plugin |
| GET | `/wp-json/plugin-uploader/v1/plugins` | List all installed plugins |
| GET | `/wp-json/plugin-uploader/v1/plugins/{slug}` | Get single plugin info |
| POST | `/wp-json/plugin-uploader/v1/plugins/{slug}/enable` | Activate a plugin |
| POST | `/wp-json/plugin-uploader/v1/plugins/{slug}/disable` | Deactivate a plugin |
| DELETE | `/wp-json/plugin-uploader/v1/plugins/{slug}/delete` | Delete a plugin |
| GET | `/wp-json/plugin-uploader/v1/plugins/{slug}/files` | List plugin files |
| PUT | `/wp-json/plugin-uploader/v1/plugins/{slug}/files` | Replace single file |
| DELETE | `/wp-json/plugin-uploader/v1/plugins/{slug}/files` | Delete single file |

## Upload Methods

### Method 1: Base64-encoded JSON (Recommended)

```json
POST /wp-json/plugin-uploader/v1/upload
Content-Type: application/json
Authorization: Basic <base64(username:app_password)>

{
  "plugin_name": "my-plugin.zip",
  "plugin_data": "<base64_encoded_zip>",
  "activate": true
}
```

### Method 2: Multipart Form Data

```http
POST /wp-json/plugin-uploader/v1/upload
Content-Type: multipart/form-data
Authorization: Basic <base64(username:app_password)>

plugin=<zip_file>
activate=true
```

## Single File Operations

### Replace a file

```json
PUT /wp-json/plugin-uploader/v1/plugins/my-plugin/files
Content-Type: application/json

{
  "path": "includes/class-helper.php",
  "content": "<?php // new content",
  "encoding": "plain"  // or "base64"
}
```

### Delete a file

```json
DELETE /wp-json/plugin-uploader/v1/plugins/my-plugin/files
Content-Type: application/json

{
  "path": "includes/deprecated.php"
}
```

## Required Capabilities

- `read` - Status and list endpoints
- `install_plugins` - Upload, file operations
- `activate_plugins` - Enable/disable
- `delete_plugins` - Delete operations

## Security

- All endpoints require authentication via WordPress Application Passwords
- Path traversal attacks are blocked (files must stay within plugin directory)
- File type validation for uploads (only .zip allowed)
- Proper WordPress capability checks on all endpoints

## Author

Riseup Asia / Alim Ul Karim  
https://riseup-asia.com/

## License

GPL v2 or later
