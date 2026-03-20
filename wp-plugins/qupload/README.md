# Quick Upload (QUpload)

A minimal, focused WordPress plugin for remote plugin upload, activation, and management via REST API. Used as the deployment transport layer when the target plugin's own API isn't available (solves the chicken-and-egg problem for first-time installs).

**Current Version:** `2.28.3` · **Requires PHP:** 8.1+ · **Requires WordPress:** 5.6+

> 📋 See [CHANGELOG.md](./CHANGELOG.md) for the full release history.

---

## Plugin Information

| Property | Value |
|----------|-------|
| **Plugin Name** | Quick Upload |
| **Author** | MD ALIM UL KARIM |
| **License** | GPL v2 or later |
| **REST API Namespace** | `qupload-api/v1` |
| **Authentication** | WordPress Application Passwords (HTTP Basic Auth) |

---

## Why QUpload Exists

When deploying to a fresh WordPress site:
1. You can't use the Riseup Asia Uploader's API to install itself — it's not there yet
2. QUpload acts as the **bootstrap plugin** — installed once, then used to deploy everything else
3. Once Riseup Asia Uploader is deployed, it can cross-upload QUpload updates back

This "cross-upload" strategy ensures both plugins can always be updated remotely.

---

## REST API Endpoints

All endpoints require HTTP Basic Auth using WordPress Application Passwords.

**Base:** `/wp-json/qupload-api/v1`

### System

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/status` | Health check with version, PHP/WP versions, server diagnostics |

### Plugin Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/upload` | Upload plugin ZIP (multipart or base64) + optional activate |
| POST | `/activate` | Activate an installed plugin by slug |
| PUT | `/deactivate` | Deactivate a plugin by slug |
| GET | `/plugins` | List all installed plugins with status |

### Log Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/logs/status` | Log file status (sizes, line counts) |
| GET | `/logs/retrieve` | Retrieve raw log content |
| DELETE | `/logs/clear` | Clear logs (two-step: request token) |
| POST | `/logs/clear/confirm` | Confirm log clearing with token |
| POST | `/logs/email` | Email log report |

### Machine Authorization

| Method | Endpoint | Description |
|--------|----------|-------------|
| PUT | `/machines/approve` | Approve a machine name for API access |

---

## Architecture

| Aspect | Detail |
|--------|--------|
| **Namespace** | `QUpload\` → `includes/` |
| **Autoloader** | `includes/Autoloader.php` (self-contained) |
| **Entry point** | `qupload.php` |
| **Main class** | `QUpload\Core\Plugin` (singleton) |
| **Design** | Minimal — only essential endpoints, no database dependency |

### Key Namespaces

| Namespace | Purpose |
|-----------|---------|
| `QUpload\Core` | Plugin shell |
| `QUpload\Enums` | Backed enums (EndpointType, PluginConfigType, ResponseKeyType) |
| `QUpload\Helpers` | BooleanHelpers, PathHelper, EnvelopeBuilder, DateHelper |
| `QUpload\Logging` | FileLogger |
| `QUpload\Traits` | Endpoint handlers (Upload, Status, Activate, Logs, etc.) |

---

## Upload Examples

### Multipart form upload
```bash
curl -X POST https://your-site.com/wp-json/qupload-api/v1/upload \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -F "plugin_zip=@my-plugin.zip" \
  -F "slug=my-plugin" \
  -F "activate=1"
```

### Activate a plugin
```bash
curl -X POST https://your-site.com/wp-json/qupload-api/v1/activate \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"slug": "my-plugin"}'
```

### List installed plugins
```bash
curl -X GET https://your-site.com/wp-json/qupload-api/v1/plugins \
  -u "admin:xxxx xxxx xxxx xxxx"
```

---

## Storage Layout

```
wp-content/uploads/qupload/
├── logs/
│   ├── info.txt         (general activity)
│   ├── error.txt        (error logs)
│   └── stacktrace.txt   (stack traces)
└── temp/                (temporary files during upload)
```

---

## Admin Page

**Tools → Quick Upload** — displays:
- Plugin status and version
- Recent logs and errors (tabbed: Log / Error / Stack Trace)
- Endpoint reference table
- Authentication guidance

---

## Coding Guidelines

Same standards as Riseup Asia Uploader:
- 500-line file limit with trait decomposition
- 20-line function limit
- Backed enums for all constants (no `define()`)
- PHPStan level 6 static analysis
- `Throwable`-first error handling

> Full standards: [`spec/07-php-standards/`](../../spec/07-php-standards/)

---

## Deployment

```powershell
# Upload QUpload to all sites
.\run.ps1 -uas

# Upload QUpload only via itself
.\run.ps1 -q

# Check remote status
.\run.ps1 -pas

# Version bump
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch
```

> Full CLI reference: [`wp-plugins/scripts/README.md`](../scripts/README.md)

---

## Documentation

| Document | Path |
|----------|------|
| **Changelog** | [`CHANGELOG.md`](./CHANGELOG.md) |
| **CLI Reference** | [`wp-plugins/scripts/README.md`](../scripts/README.md) |
| **PHP Standards** | [`spec/07-php-standards/`](../../spec/07-php-standards/) |
| **PowerShell Integration** | [`spec/13-powershell-integration/`](../../spec/13-powershell-integration/) |

---

## Author

**MD ALIM UL KARIM**

- Profile: [rasia.pro](https://rasia.pro/alim-r-profile-v1)
- Company: Riseup Asia

## License

GPL v2 or later
