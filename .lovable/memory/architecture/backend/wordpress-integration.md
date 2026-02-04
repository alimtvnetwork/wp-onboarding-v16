# Memory: architecture/backend/wordpress-integration
Updated: just now

WordPress integration supports two companion plugins with automatic fallback:

1. **Plugin Uploader Helper** (`plugin-uploader/v1`) - Preferred, simpler API with base64 JSON upload, enable/disable/delete plugins, and single-file replace/delete operations.

2. **Onboard Plugin** (`onboard-plugin/v1`) - Legacy companion using mutation tokens and multipart form-data uploads.

The publish service automatically detects which companion is available and uses the appropriate method. If neither is installed, uploads are simulated (logged only). A PowerShell script (`backend/scripts/upload-plugin.ps1`) is also available for Windows-based manual uploads.

Connection validation involves DNS resolution, API availability, authentication, and functional write tests.
