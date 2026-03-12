# Quick Upload (QUpload)

Minimal WordPress plugin for remote plugin upload and activation via REST API.

## Requirements

- PHP 8.1+
- WordPress 5.6+

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/wp-json/qupload-api/v1/status` | Health check |
| `POST` | `/wp-json/qupload-api/v1/upload` | Upload plugin ZIP |
| `POST` | `/wp-json/qupload-api/v1/activate` | Activate installed plugin |

## Authentication

All endpoints require WordPress Application Password via HTTP Basic Auth.

## Upload

Send a multipart form with `plugin_zip` file, or JSON with base64-encoded `plugin_zip`.

```bash
curl -X POST https://your-site.com/wp-json/qupload-api/v1/upload \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -F "plugin_zip=@my-plugin.zip" \
  -F "slug=my-plugin" \
  -F "activate=1"
```

## Activate

```bash
curl -X POST https://your-site.com/wp-json/qupload-api/v1/activate \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"slug": "my-plugin"}'
```

## Admin Page

**Tools → Quick Upload** — displays plugin status, recent logs/errors, endpoint reference, and authentication guidance.

## Logs

- `wp-content/uploads/qupload/logs/log.txt` — General log
- `wp-content/uploads/qupload/logs/error.txt` — Error-only log
- `wp-content/uploads/qupload/logs/stacktrace.txt` — Stack traces

## Version Management

```powershell
# Bump QUpload version
.\wp-plugins\scripts\bump-version.ps1 -Target qupload -Bump patch

# Set exact version
.\wp-plugins\scripts\bump-version.ps1 -Target qupload -Set "2.0.0"

# Bump everything (app, script, plugin, qupload)
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump minor
```

## Author

**MD ALIM UL KARIM**
https://rasia.pro/alim-r-profile-v1
