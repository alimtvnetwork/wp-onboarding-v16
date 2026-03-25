# Plugins Onboard

[![WordPress](https://img.shields.io/badge/WordPress-5.9%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.5-orange.svg)]()

**Secure, auditable remote plugin management for WordPress.**

Plugins Onboard provides enterprise-grade REST API access to manage WordPress plugins remotely with OAuth 2.0 authentication, ephemeral mutation tokens, automatic backups, and comprehensive audit logging.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [API Reference](#api-reference)
- [Security Model](#security-model)
- [Admin Panel](#admin-panel)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)
- [Author](#author)

---

## Features

### 🔐 Enterprise Security
- **OAuth 2.0 Authentication** - Industry-standard authorization with JWT tokens
- **Ephemeral Mutation Tokens** - One-time, IP-bound, action-specific tokens for destructive operations
- **IP Whitelist & Approval** - Admin approval workflow for new IP addresses
- **Rate Limiting** - Configurable request throttling per endpoint type
- **Token Encryption** - AES-256-CBC encryption for all stored tokens

### 📦 Plugin Management
- **Remote Operations** - Enable, disable, delete, and upload plugins via API
- **Automatic Backups** - Pre-action snapshots before any mutation
- **One-Click Restore** - Rollback to any previous plugin version
- **Upload Validation** - Malicious code scanning and ZIP verification

### 📊 Audit & Compliance
- **Comprehensive Logging** - Every action logged with full context
- **Separate Audit Database** - Isolated SQLite database for audit trail
- **Configurable Retention** - Automatic log rotation based on policy
- **Export Capabilities** - Download logs and databases for compliance

### ⚙️ Flexible Configuration
- **Three-Tier Hierarchy** - Constants → Database → Environment Variables
- **Runtime Overrides** - ENV variables take highest priority
- **Admin UI Settings** - Configure via WordPress admin panel
- **Zero-Config Defaults** - Works out of the box

---

## Requirements

| Requirement | Version |
|-------------|----------|
| WordPress | 5.9 or higher |
| PHP | 7.4 or higher |
| PDO SQLite | Required |
| ZipArchive | Required |

---

## Installation

### Via WordPress Admin

1. Download `plugins-onboard.zip`
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Via FTP/SFTP

1. Extract `plugins-onboard.zip`
2. Upload the `plugins-onboard` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find "Plugins Onboard" and click **Activate**

### Via WP-CLI

```bash
wp plugin install plugins-onboard.zip --activate
```

---

## Quick Start

### 1. Create an Application

1. Go to **Plugins Onboard → Applications**
2. Fill in the form:
   - **Application Name**: Your app identifier
   - **Redirect URI**: Your OAuth callback URL
3. Click **Create Application**
4. **Save the Client ID and Client Secret** (secret shown only once!)

### 2. Authenticate

```bash
# Step 1: Get authorization code
curl "https://your-site.com/wp-json/onboard-plugin/v1/auth/request?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI"

# Step 2: Exchange code for tokens
curl -X POST "https://your-site.com/wp-json/onboard-plugin/v1/auth/callback" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "AUTHORIZATION_CODE",
    "client_id": "YOUR_CLIENT_ID",
    "client_secret": "YOUR_CLIENT_SECRET"
  }'
```

**Response:**
```json
{
  "access_token": "eyJhbGc...",
  "refresh_token": "abc123...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### 3. List Plugins

```bash
curl "https://your-site.com/wp-json/onboard-plugin/v1/plugins/list" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 4. Perform Mutations

```bash
# Step 1: Request mutation token
curl "https://your-site.com/wp-json/onboard-plugin/v1/request-mutation?action=disable" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

# Response includes mutation_token and mutation_endpoint

# Step 2: Execute mutation
curl -X POST "https://your-site.com/wp-json/onboard-plugin/v1/mutations/MUTATION_TOKEN/plugins/akismet/disable"
```

---

## Configuration

### Configuration Hierarchy

Values are resolved in this order (highest priority first):

1. **Environment Variables** - `ONBOARD_*` prefixed variables
2. **Database Settings** - Stored via admin panel
3. **PHP Constants** - Defined in `constants.php`

### Environment Variables

Override any setting via environment variables:

```bash
# Token TTLs
export ONBOARD_ACCESS_TOKEN_TTL=7200
export ONBOARD_MUTATION_TOKEN_TTL=600

# Security
export ONBOARD_REQUIRE_HTTPS=true
export ONBOARD_IP_WHITELIST_ENABLED=true

# Rate Limiting
export ONBOARD_RATE_LIMIT_AUTH_REQUESTS=10
export ONBOARD_RATE_LIMIT_MUTATION_REQUESTS=20
```

### Available Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `access_token_ttl` | 3600 | Access token lifetime (seconds) |
| `refresh_token_ttl` | 2592000 | Refresh token lifetime (seconds) |
| `mutation_token_ttl` | 1200 | Mutation token lifetime (seconds) |
| `rate_limit_auth_requests` | 5 | Auth requests per hour |
| `rate_limit_mutation_requests` | 10 | Mutation requests per hour |
| `snapshot_retention_count` | 5 | Snapshots to keep per plugin |
| `require_https` | false | Require HTTPS for API |
| `ip_whitelist_enabled` | true | Enable IP whitelist |
| `ip_auto_approve` | false | Auto-approve new IPs |
| `audit_log_retention_days` | 365 | Days to keep audit logs |

### Using Configuration in Code

```php
// Get a config value
$ttl = onboard_config('access_token_ttl', 3600);

// Or via plugin instance
$plugin = plugins_onboard();
$value = $plugin->get_config('ip_whitelist_enabled', true);
```

### Changing Storage Location

All plugin data (databases, logs, snapshots, temp files) can be relocated by changing **ONE LINE** in the codebase:

**File:** `includes/class-paths.php`
**Method:** `get_base_path()`

```php
// CONFIGURATION: Change this path to relocate ALL plugin storage.
// Default: wp-content/uploads/plugins-onboard/
$base_path = WP_CONTENT_DIR . '/uploads/plugins-onboard/';
```

**Examples of alternative locations:**

```php
// Store in wp-includes directory
$base_path = ABSPATH . 'wp-includes/plugins-onboard/';

// Store in custom directory
$base_path = '/var/www/custom-storage/plugins-onboard/';

// Store in wp-content root
$base_path = WP_CONTENT_DIR . '/plugins-onboard-data/';
```

**After changing the path:**

1. Deactivate the plugin
2. Delete the old directory
3. Reactivate the plugin (directories will be auto-created)

**Directory structure (relative to base path):**

```
plugins-onboard/
├── db/                  # SQLite databases
├── logs/                # Debug and error logs
├── snapshots/           # Plugin backup snapshots
└── temp/                # Temporary upload files
```

---

## API Reference

### Base URL

```
https://your-site.com/wp-json/onboard-plugin/v1/
```

### Authentication Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/auth/request` | None | Initiate OAuth flow |
| POST | `/auth/callback` | None | Exchange code for tokens |
| POST | `/refresh-token` | None | Refresh access token |
| GET | `/request-mutation` | Bearer | Request mutation token |

### Plugin Management Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/plugins/list` | Bearer | List all plugins |
| POST | `/mutations/{token}/plugins/{slug}/enable` | Mutation | Enable plugin |
| POST | `/mutations/{token}/plugins/{slug}/disable` | Mutation | Disable plugin |
| POST | `/mutations/{token}/plugins/{slug}/delete` | Mutation | Delete plugin |
| POST | `/mutations/{token}/plugins/upload` | Mutation | Upload plugin ZIP |
| POST | `/mutations/{token}/plugins/{slug}/restore` | Mutation | Restore from snapshot |

### Backup Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/plugins/{slug}/backups` | Bearer | List plugin snapshots |
| GET | `/plugins/backups/download-all` | Bearer | Download all plugins |
| GET | `/plugins/backups/download-active` | Bearer | Download active plugins |
| GET | `/plugins/backups/download-snapshots` | Bearer | Download all snapshots |

### System Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/database/info` | Bearer | Database information |
| GET | `/database/download` | Bearer | Download databases |
| GET | `/temp-info` | Bearer | Temp directory info |
| GET | `/audit-logs` | Bearer | Query audit logs |

### Mutation Actions

Valid actions for `/request-mutation`:

- `enable` - Enable a plugin
- `disable` - Disable a plugin
- `delete` - Delete a plugin
- `upload` - Upload new plugin
- `restore` - Restore from snapshot
- `backup_manual` - Create manual backup
- `debug_enable` / `debug_disable` - Toggle debug mode
- `maintenance_enable` / `maintenance_disable` - Toggle maintenance

### Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| `invalid_credentials` | 401 | Invalid client credentials |
| `invalid_token` | 401 | Invalid or expired access token |
| `invalid_mutation_token` | 401 | Invalid or expired mutation token |
| `ip_pending_approval` | 403 | IP requires admin approval |
| `ip_mismatch` | 403 | Request IP doesn't match token |
| `action_mismatch` | 403 | Token action doesn't match request |
| `rate_limit_exceeded` | 429 | Too many requests |
| `plugin_not_found` | 404 | Plugin doesn't exist |

---

## Security Model

### Authentication Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Client    │────▶│  Auth Code  │────▶│   Tokens    │
│ Application │     │  (10 min)   │     │ (1hr/30day) │
└─────────────┘     └─────────────┘     └─────────────┘
```

### Mutation Token Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Access    │────▶│  Mutation   │────▶│   Execute   │
│   Token     │     │   Token     │     │   Action    │
│             │     │ (20 min,    │     │             │
│             │     │  1-use,     │     │             │
│             │     │  IP-bound)  │     │             │
└─────────────┘     └─────────────┘     └─────────────┘
```

### Security Features

- **Token Binding**: Mutation tokens are bound to the IP that requested them
- **Single Use**: Mutation tokens are invalidated after first use
- **Action Specific**: Each mutation token is valid for only one action type
- **Time Limited**: All tokens have configurable expiration
- **Encrypted Storage**: Tokens encrypted at rest with AES-256-CBC
- **Rate Limited**: All endpoints have request throttling
- **IP Whitelist**: Optional approval workflow for new IPs

---

## Admin Panel

### Menu Structure

```
Plugins Onboard
├── Dashboard        - Overview and quick actions
├── Plugins          - Manage installed plugins
├── Backups & Restore - Snapshot management
├── Database         - Database and temp file management
├── Settings         - Plugin configuration
├── Applications     - OAuth app management
├── Audit Logs       - Activity history
├── Test Runner      - Run built-in tests
└── Help & Docs      - Documentation
```

### Screenshots

**Dashboard** - System overview with status cards and recent activity

**Applications** - Create and manage OAuth applications, approve IPs

**Backups** - Browse snapshots, restore with one click, bulk downloads

**Audit Logs** - Searchable activity log with filters

---

## Testing

### Built-in Test Runner

1. Go to **Plugins Onboard → Test Runner**
2. Click **Run All Tests**
3. View results with pass/fail indicators

### Test Suites

- **Unit Tests**: Token encryption, rate limiting, UUID generation
- **Integration Tests**: Database operations, OAuth flow, mutation tokens

### Command Line (with PHPUnit)

```bash
cd wp-content/plugins/plugins-onboard
composer install
./vendor/bin/phpunit
```

---

## Troubleshooting

### Plugin Won't Activate

**Check PHP extensions:**
```bash
php -m | grep -E 'pdo_sqlite|zip'
```

**Check error logs:**
```bash
tail -f wp-content/debug.log
```

### Database Connection Failed

1. Ensure `wp-content/uploads/plugins-onboard/` is writable
2. Check if PDO SQLite extension is loaded
3. Verify directory permissions (755 for dirs, 644 for files)
4. Check logs at `wp-content/uploads/plugins-onboard/logs/error.log`

### API Returns 401

1. Verify access token hasn't expired
2. Check Authorization header format: `Bearer YOUR_TOKEN`
3. Ensure application is still active

### Mutation Token Fails

1. Verify IP address matches (check behind proxies)
2. Ensure action matches token action
3. Token may have already been used (single-use)
4. Token may have expired (default: 20 minutes)

### IP Pending Approval

1. Check **Applications** page for pending approvals
2. Approve the IP or disable IP whitelist
3. Or set `ONBOARD_IP_AUTO_APPROVE=true`

---

## File Structure

```
plugins-onboard/
├── plugins-onboard.php      # Main plugin file
├── includes/
│   ├── constants.php        # Default configuration values
│   ├── class-config.php     # Configuration manager
│   ├── class-database.php   # SQLite database handler
│   ├── class-oauth.php      # OAuth 2.0 implementation
│   ├── class-mutation-token.php
│   ├── class-token-encryption.php
│   ├── class-rate-limiter.php
│   ├── class-audit-logger.php
│   ├── class-ip-whitelist.php
│   ├── class-snapshot.php
│   ├── class-backup-manager.php
│   ├── class-plugin-manager.php
│   ├── class-upload-validator.php
│   ├── class-debug-maintenance.php
│   ├── class-cleanup.php
│   └── security-utils.php
├── api/
│   ├── class-api.php        # REST API endpoints
│   └── class-permissions.php
├── admin/
│   ├── class-admin-ui.php   # Admin panel
│   ├── class-test-runner.php
│   └── views/               # Admin page templates
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── tests/
│   ├── bootstrap.php
│   ├── unit/
│   └── integration/
├── data/                    # SQLite databases (auto-created)
├── CHANGELOG.md
├── README.md
├── composer.json
└── phpunit.xml
```

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Submit a pull request

---

## License

This project is licensed under the **GNU General Public License v2.0** or later.

See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

## Author

**MD ALIM UL KARIM**  

- Profile: [https://rasia.pro/alim-r-profile-v1](https://rasia.pro/alim-r-profile-v1)
- Company: Riseup Asia

---

## Support

For support, please:

1. Check the [Troubleshooting](#troubleshooting) section
2. Review the [Help & Docs](#admin-panel) in the admin panel
3. Open an issue on GitHub
4. Contact support@riseup-asia.com

---

<p align="center">
  <strong>Built with ❤️ for the WordPress community</strong>
</p>
