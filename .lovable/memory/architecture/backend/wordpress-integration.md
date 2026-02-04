# Memory: architecture/backend/wordpress-integration
Updated: just now

WordPress integration supports two companion plugins with automatic fallback:

1. **Rise Up Uploader** (`riseup-uploader/v1`) - Primary companion plugin with:
   - Base64 JSON upload for reliability
   - Plugin lifecycle management (enable/disable/delete)
   - Single-file replace/delete operations
   - Blog post and category publishing
   - SQLite-based transaction logging for full audit trail
   - Query endpoint for logs with filtering and pagination
   - Authentication via WordPress Application Passwords

2. **Onboard Plugin** (`onboard-plugin/v1`) - Legacy companion using mutation tokens and multipart form-data uploads.

3. **Plugin Uploader Helper** (`plugin-uploader/v1`) - Deprecated, replaced by Rise Up Uploader. Backward compatibility is maintained.

The publish service automatically detects which companion is available (checking Rise Up Uploader first, then legacy namespaces) and uses the appropriate method. If neither is installed, uploads are simulated (logged only). A PowerShell script (`backend/scripts/upload-plugin.ps1`) is also available for Windows-based manual uploads.

Connection validation involves DNS resolution, API availability, authentication, and functional write tests.
