# Memory: features/api-explorer
Updated: 2026-02-05

The API Explorer feature provides an interactive Swagger UI interface for browsing and testing the Riseup Asia Uploader WordPress REST API endpoints.

## Access

Navigate to `/api-explorer` from the sidebar.

## Features

| Feature | Description |
|---------|-------------|
| Site Selection | Choose from configured WordPress sites |
| Authentication | Enter application password for API access |
| Interactive Docs | Full Swagger UI with "Try it out" functionality |
| Auto-injection | Auth headers automatically added to all test requests |
| Dark Mode | Styled to match app theme |

## Security

- The `/status` and `/openapi` endpoints now require authentication
- All API endpoints use Basic Auth with WordPress Application Passwords
- Application passwords are entered per-session (not stored)

## WordPress Plugin Endpoints

### System
- `GET /status` - Plugin status and version info (authenticated)
- `GET /openapi` - OpenAPI 3.0 specification (authenticated)

### Plugins
- `GET /plugins` - List all installed plugins
- `POST /upload` - Upload plugin as Base64 ZIP
- `GET /plugins/{slug}/files` - List plugin files with MD5 hashes
- `POST /plugins/{slug}/file` - Get file content
- `GET /export-self` - Export Riseup Asia Uploader plugin

### Posts
- `GET /posts` - List blog posts
- `POST /posts` - Create new post
- `GET /categories` - List categories
- `POST /categories` - Create category

### Logs
- `GET /logs` - Query transaction logs
- `GET /logs/stats` - Get log statistics

## Related Files

- `src/pages/ApiExplorer.tsx` - Main page component
- `wp-plugins/riseup-asia-uploader/data/openapi.json` - OpenAPI spec
- `wp-plugins/riseup-asia-uploader/riseup-asia-uploader.php` - Plugin endpoints
