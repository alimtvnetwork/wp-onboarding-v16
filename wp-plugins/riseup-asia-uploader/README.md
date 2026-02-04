# Riseup Asia Uploader

A lightweight WordPress plugin providing a secure REST API for remote plugin management, delta file synchronization, blog post publishing, and comprehensive audit logging.

## Plugin Information

| Property | Value |
|----------|-------|
| **Plugin Name** | Riseup Asia Uploader |
| **Plugin URI** | https://rasia.pro/alim-r-profile-v1 |
| **Version** | 1.3.0 |
| **Author** | MD ALIM UL KARIM |
| **Author URI** | https://rasia.pro/alim-r-profile-v1 |
| **License** | GPL v2 or later |
| **Requires PHP** | 7.4+ |
| **Requires WordPress** | 5.6+ |

## Features

- **Upload plugins** via ZIP file (multipart or base64-encoded)
- **Enable/Disable plugins** remotely
- **Delete plugins** via REST API
- **Replace single files** within a plugin directory
- **List files** with hashes for sync comparison
- **Delta sync** with `.uploadignore` support
- **Blog post management** - Create and update posts
- **Category management** - Create categories remotely
- **Media uploads** - Upload to WordPress Media Library
- **Transaction logging** - SQLite-based audit trail

## Installation

1. Copy the `riseup-asia-uploader` folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin in WordPress Admin → Plugins
3. Configure an Application Password for your WordPress user (Users → Profile → Application Passwords)

## REST API Endpoints

All endpoints require Basic Authentication using WordPress Application Passwords.

**Base Namespace:** `riseup-asia-uploader/v1`

### Plugin Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/status` | Check plugin availability (public) |
| POST | `/upload` | Upload and install plugin |
| GET | `/plugins` | List all installed plugins |
| GET | `/plugins/{slug}` | Get single plugin info |
| POST | `/plugins/{slug}/enable` | Activate a plugin |
| POST | `/plugins/{slug}/disable` | Deactivate a plugin |
| DELETE | `/plugins/{slug}/delete` | Delete a plugin |
| GET/POST/DELETE | `/plugins/{slug}/files` | File operations |
| POST | `/plugins/{slug}/sync` | Delta synchronization |
| GET | `/export-self` | Export this plugin as ZIP |

### Blog Post Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/posts` | List posts |
| POST | `/posts` | Create new post |
| GET | `/posts/{id}` | Get single post |
| PUT | `/posts/{id}` | Update post |

### Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/categories` | List categories |
| POST | `/categories` | Create category |

### Transaction Logs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/logs` | Query transaction logs |
| GET | `/logs/stats` | Get log statistics |
| GET | `/logs/{id}` | Get single log entry |

## Upload Methods

### Method 1: Base64-encoded JSON (Recommended)

```json
POST /wp-json/riseup-asia-uploader/v1/upload
Content-Type: application/json
Authorization: Basic <base64(username:app_password)>

{
  "plugin_zip": "<base64_encoded_zip>",
  "slug": "my-plugin",
  "activate": true
}
```

### Method 2: Multipart Form Data

```http
POST /wp-json/riseup-asia-uploader/v1/upload
Content-Type: multipart/form-data
Authorization: Basic <base64(username:app_password)>

plugin=<zip_file>
activate=true
```

## Delta Sync

The plugin supports `.uploadignore` files (gitignore-style syntax) to exclude files from synchronization:

```
# .uploadignore example
node_modules/
*.log
.git/
vendor/
```

## Required Capabilities

- `activate_plugins` - Plugin management endpoints
- `publish_posts` - Post management endpoints
- `upload_files` - Media upload endpoints
- `manage_options` - Log viewing endpoints

## Security

- All endpoints require authentication via WordPress Application Passwords
- Path traversal attacks are blocked (files must stay within plugin directory)
- File type validation for uploads (only .zip allowed)
- Proper WordPress capability checks on all endpoints
- Transaction logging for all operations

## Author

**MD ALIM UL KARIM**

- Profile: https://rasia.pro/alim-r-profile-v1
- Company: Riseup Asia

## License

GPL v2 or later
