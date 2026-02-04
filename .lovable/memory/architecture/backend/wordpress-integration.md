# Memory: architecture/backend/wordpress-integration
Updated: just now

WordPress integration supports multiple companion plugins with automatic fallback:

1. **Rise Up Asia** (`riseup-asia/v1`) - Primary companion plugin with:
   - Base64 JSON upload for reliability
   - Plugin lifecycle management (enable/disable/delete)
   - Single-file replace/delete operations
   - Delta file sync with `.uploadignore` support
   - Blog post and category publishing with media uploads
   - SQLite-based transaction logging via micro-ORM
   - Query endpoint for logs with filtering and pagination
   - Authentication via WordPress Application Passwords
   - Self-export capability for bootstrapping to new sites

2. **Rise Up Uploader** (`riseup-uploader/v1`) - Legacy namespace, deprecated in favor of Rise Up Asia.

3. **Onboard Plugin** (`onboard-plugin/v1`) - Legacy companion using mutation tokens and multipart form-data uploads.

4. **Plugin Uploader Helper** (`plugin-uploader/v1`) - Deprecated, replaced by Rise Up Asia. Backward compatibility is maintained.

The publish service automatically detects which companion is available (checking Rise Up Asia first, then legacy namespaces) and uses the appropriate method. If neither is installed, uploads are simulated (logged only). A PowerShell script (`backend/scripts/upload-plugin.ps1`) is also available for Windows-based manual uploads.

Connection validation involves DNS resolution, API availability, authentication, and functional write tests.
