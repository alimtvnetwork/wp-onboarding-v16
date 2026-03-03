# Quick Upload (QUpload)

Minimal WordPress plugin for remote plugin upload and activation via REST API.

## Requirements

- PHP 8.2+
- WordPress 5.6+

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/wp-json/qupload/v1/status` | Health check |
| `POST` | `/wp-json/qupload/v1/upload` | Upload plugin ZIP |
| `POST` | `/wp-json/qupload/v1/activate` | Activate installed plugin |

## Authentication

All endpoints require WordPress Application Password via HTTP Basic Auth.

## Upload

Send a multipart form with `plugin_zip` file, or JSON with base64-encoded `plugin_zip`.

```bash
curl -X POST https://your-site.com/wp-json/qupload/v1/upload \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -F "plugin_zip=@my-plugin.zip" \
  -F "slug=my-plugin" \
  -F "activate=1"
```

## Activate

```bash
curl -X POST https://your-site.com/wp-json/qupload/v1/activate \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"slug": "my-plugin"}'
```

## Logs

- `wp-content/uploads/qupload/logs/log.txt` — General log
- `wp-content/uploads/qupload/logs/error.txt` — Error-only log
- `wp-content/uploads/qupload/logs/stacktrace.txt` — Stack traces

## Author

**MD ALIM UL KARIM**
https://rasia.pro/alim-r-profile-v1
