# Memory: features/api-explorer
Updated: 2026-02-05

The API Explorer provides an interactive Swagger UI interface for browsing and testing the Riseup Asia Uploader WordPress REST API endpoints with automatic credential management.

## Access

Navigate to `/api-explorer` from the sidebar.

## Features

| Feature | Description |
|---------|-------------|
| Auto-Authentication | Credentials loaded from database automatically |
| Interactive Docs | Full Swagger UI with "Try it out" functionality |
| Request History | Real-time panel showing all API calls with timing/status |
| Dark Mode | Styled to match app theme |

## Security Model

- All API endpoints (including `/status` and `/openapi`) require authentication
- Credentials are stored encrypted in the database
- Backend provides on-demand decryption via `GET /sites/{id}/credentials`
- Frontend never stores decrypted passwords - only session-lived

## API Endpoints

### Backend (Go)
- `GET /api/v1/sites/{id}/credentials` - Returns decrypted credentials for API Explorer

### WordPress Plugin
- `GET /status` - Plugin status (authenticated)
- `GET /openapi` - OpenAPI 3.0 spec (authenticated)
- `GET /plugins` - List all installed plugins
- `POST /upload` - Upload plugin as Base64 ZIP
- `GET /plugins/{slug}/files` - List plugin files with MD5 hashes
- `POST /plugins/{slug}/file` - Get file content
- `GET /export-self` - Export plugin itself
- `GET /posts`, `POST /posts` - Blog post management
- `GET /categories`, `POST /categories` - Category management
- `GET /logs`, `GET /logs/stats` - Transaction logs

## Request History Panel

The right sidebar shows a scrollable list of recent API requests with:
- HTTP method (color-coded)
- Status code
- Response time (ms)
- URL path
- Timestamp

## Related Files

- `src/pages/ApiExplorer.tsx` - Main page component
- `src/lib/api.ts` - `getSiteCredentials` method
- `backend/internal/api/handlers/handlers.go` - `GetSiteCredentials` handler
- `backend/internal/services/site/service.go` - `GetCredentials` method
- `wp-plugins/riseup-asia-uploader/data/openapi.json` - OpenAPI spec
