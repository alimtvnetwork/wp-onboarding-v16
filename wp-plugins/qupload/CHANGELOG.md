# Changelog — Quick Upload (QUpload)

All notable changes to the **QUpload** WordPress plugin are documented here.

QUpload follows lockstep versioning with the main project. This changelog is synchronized with [`public/version.json`](../../public/version.json).

---

## [2.28.7] — 2026-03-21

### HTML Response Detection & Endpoint Mismatch Error Improvement
- 🛡️ Added early HTML response detection in WordPress API call pipeline (`doApiCallRaw` + `decodeApiResponse`)
- 🔍 New `E3013 ErrWPEndpointMismatch` error code replaces cryptic `invalid character '<'` JSON decode errors
- 📋 Clear error message surfaces endpoint path and advises verifying plugin installation and namespace
- 🚀 Added startup namespace validation check (`validateNamespaces`) to prevent silent 404s
- 🔧 Fixed standalone `snapshotEndpoint()` calls in `SnapshotsBackup.go` to use receiver method

## [2.28.6] — 2026-03-21

### Critical Namespace Mismatch Fix
- 🐛 Fixed `RiseupAsiaNamespace` constant using plugin slug instead of API namespace (`riseup-asia-api/v1`)
- 🔧 Resolved 404 errors on remote-plugins, snapshots, and all Riseup Asia API endpoints
- 🛠️ Aligned Go backend `Constants.go` with PHP `PluginConfigType::ApiNamespace`

## [2.28.5] — 2026-03-21

### Compilation & Error Handling Fixes
- 🐛 Fixed missing imports across Go backend (context, apperror, models packages)
- 🔧 Exported `DoApiCall`/`ApiCallInput` from wordpress package for cross-package access
- 🛠️ Replaced undefined `appErr.HttpStatus()` calls with `resolveHttpStatus()` helper in all handlers
- 🔧 Fixed `EmailLogsRequest` type mismatch in `AdapterSite.go`
- 🧹 Removed unused models import from `VerboseCheck.go`

## [2.28.4] — 2026-03-20

### Changelog & Documentation Enhancements
- 📋 Plugin CHANGELOGs created for both Riseup Asia Uploader and QUpload
- 📖 Root README, plugin READMEs, and PowerShell CLI README overhauled
- 🔢 run.ps1 startup banner shows version, release date, and 3 recent changelog entries
- 📊 Local vs remote version comparison in -ps/-pas commands
- 🔍 `-check -v` verbose mode shows detailed endpoint availability info per site

## [2.28.3] — 2026-03-20

### Verbose Mode for Log Clearing — Pre-Clear Retrieval
- 🔍 `-cl -v` / `-cla -v` show current log state before clearing
- 🛡️ Gracefully handles missing `/logs/retrieve` endpoint (v2.18.0+ only)

## [2.28.2] — 2026-03-20

### Verbose Mode for Upload Commands
- 🔍 `-v` (verbose) support on all upload commands: `-u`, `-q`, `-ua`, `-uas`
- 📡 Shows raw JSON request/response for status and upload endpoints
- 🛡️ PHP noise stripping added to `upload-plugin-U-Q.ps1`

## [2.28.1] — 2026-03-20

### Invoke-RestMethod Audit
- 📦 All REST calls migrated to `Invoke-WebRequest` + PHP noise stripping

## [2.28.0] — 2026-03-19

### Fix Status Parsing — PHP Noise Resilience
- 🐛 Fixed status checks failing due to PHP deprecation notices in response
- 📦 Version extracted from envelope `Results[0].Version`

## [2.27.0] — 2026-03-19

### Verbose Mode for REST Commands
- 🔍 `-v` flag on `-am`, `-check`, `-cl`, `-cla`, `-cas`, `-purge`

## [2.26.0] — 2026-03-19

### Clear All Sites & Safety Confirmation
- 💣 `-cas` command for nuking all logs across all sites
- ⏩ `-yes`/`-y` flag to skip confirmation prompt

## [2.25.0] — 2026-03-19

### PHP Notice Resilience & Log Filters
- 🛡️ Strips PHP deprecation notices before JSON parsing
- 🏷️ Status response fields use enum-backed `ResponseKeyType`
- 🔌 `-logplugin` and `-logtype` filters for targeted log clearing

## [2.22.0] — 2026-03-20

### Enum-Backed Response Keys
- 🏷️ `Slug`, `Api`, `SiteUrl`, `DbAvailable`, `ServerTime` use `ResponseKeyType` enum

## [2.20.0] — 2026-03-19

### Cross-Upload Resilience
- 🔄 QUpload uploaded via Riseup Asia API for resilience
- 🐛 Fixed PHP syntax error in `UploadFileSystemTrait.php`

## [2.18.0] — 2026-03-18

### Version Bump & Consistency Checker
- 🔤 Go abbreviation casing rule uses PascalCase config

## [2.17.0] — 2026-03-15

### Machine Approval Source Fix
- 📁 Machine approval reads `approved_machines` from `settings.json` first

## [2.16.0] — 2026-03-15

### Clear Logs Diagnostics
- 🐛 Fixed `-cla` PS 7+ error extraction

## [2.15.0] — 2026-03-15

### REST Conventions
- 🧹 `-cla` clears logs on ALL sites; `-cl` supports targeting flags

## [2.14.0] — 2026-03-14

### User Management System
- 👤 Full user CRUD endpoints with pagination and search
- 📥 Bulk import/export via CSV and SQLite ZIP bundles

## [2.13.0] — 2026-03-14

### Rollback Protection & New Endpoints
- 🛡️ Auto-rollback on failed plugin uploads
- 🔌 `PUT /deactivate` and `GET /plugins` endpoints added

## [2.12.0] — 2026-03-14

### Timezone-Aware Logging & Remote Log Management
- 🕐 Timezone-aware log formatting
- 🗑️ REST endpoints for log status, clear, and confirm

## [2.11.0] — 2026-03-13

### Multi-Site Deployment
- 🌐 Multi-site deployment with Base64-encoded credentials

## [2.10.0] — 2026-03-13

### Multi-User Per Site
- 📋 Multi-user per site credential spec

## [2.9.0] — 2026-03-12

### PHPStan Integration
- 🔬 PHPStan level-6 static analysis integrated

## [2.7.0] — 2026-03-12

### Versioned Log Entries
- 📋 Log entries include plugin version tag `[vX.Y.Z]`

## [2.2.0] — 2026-03-12

### QUpload Admin Menu & Error Log Viewer
- 🖥️ Admin menu with Dashboard and Error Logs pages
- 📋 Tabbed error log viewer with Copy, Download, Clear, Live refresh

## [2.0.0] — 2026-03-09

### Major Version 2.0
- 🎯 Major version bump
- 🛡️ ZIP extraction, directory operations, and activation flows hardened
- ✅ PHP enums standardized with PascalCase values

---

_For older versions (1.x), see [`public/version.json`](../../public/version.json) changelog entries._

---

**Author:** MD ALIM UL KARIM · [rasia.pro](https://rasia.pro/alim-r-profile-v1)
