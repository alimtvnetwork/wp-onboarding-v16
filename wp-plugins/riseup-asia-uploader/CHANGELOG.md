# Changelog — Riseup Asia Uploader

All notable changes to the **Riseup Asia Uploader** WordPress plugin are documented here.

This changelog is synchronized with [`public/version.json`](../../public/version.json).

---

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
- 🔍 `-cl -v` and `-cla -v` now call `GET /logs/retrieve` before clearing to show current log state
- 📊 Displays `info.txt`, `error.txt`, `stacktrace.txt` line counts and sizes before deletion
- 🛡️ Gracefully handles missing `/logs/retrieve` endpoint (v2.18.0+ only)

## [2.28.2] — 2026-03-20

### Verbose Mode for Upload Commands (-u, -q, -ua, -uas)
- 🔍 Added `-v` (verbose) support to all upload commands: `-u`, `-q`, `-ua`, `-uas`, `-u -as`
- 📡 Shows raw JSON request/response for status check and upload POST endpoints
- 🔗 Verbose flag wired through `upload-plugin-U-Q.ps1`, `upload-single.ps1`, `upload-parallel.ps1`, and all mode scripts
- 🛡️ Also added PHP noise stripping to `upload-plugin-U-Q.ps1` status and upload responses
- 📋 Updated help text with `-v` examples for upload commands

## [2.28.1] — 2026-03-20

### Invoke-RestMethod Audit — Full Codebase Migration to Invoke-WebRequest
- 🔍 Audited all PowerShell modules for remaining `Invoke-RestMethod` usage
- 🛡️ Replaced 8 calls across `upload-plugin.ps1`, `upload-plugin-v3.ps1`, `mode-clear-logs.ps1`, and `mode-approve-machine.ps1`
- 📦 All REST calls now use `Invoke-WebRequest` + PHP noise stripping for resilience against WordPress deprecation notices

## [2.28.0] — 2026-03-19

### Fix Status Parsing — PHP Noise Resilience & Envelope-Aware Extraction
- 🐛 Fixed `-am` and `-check` showing "NOT READY (no version in response)" on all sites despite valid API responses
- 🛡️ Replaced `Invoke-RestMethod` with `Invoke-WebRequest` in `mode-approve-machine.ps1` and `mode-check.ps1` to handle PHP deprecation notices prepended to JSON
- 📦 Version now correctly extracted from envelope `Results[0].Version` instead of non-existent top-level `.version` property
- 📋 Root cause issue documented in `spec/issues/2027-status-parsing-php-noise.md`
- 🧠 Memory rule added: never use `Invoke-RestMethod` for WordPress REST calls

## [2.27.0] — 2026-03-19

### Verbose Mode for All REST Commands
- 🔍 `-v` (verbose) flag now supported on `-am`, `-check`, `-cl`, `-cla`, `-cas`, `-purge` commands
- 📡 Verbose mode shows full target URL, request body (JSON), and raw response body for all REST calls
- 🐛 Aids debugging "NOT READY" and connectivity issues by exposing exact HTTP payloads

## [2.26.0] — 2026-03-19

### Clear All Sites Command & Safety Confirmation
- 💣 `-cas` (Clear All Sites) command: nuke ALL file logs, stacktraces, errors, and audit/activity logs across both plugins and all sites
- ⚠️ Destructive action confirmation prompt before `-cas`/`-purge` executes (shows site count and names)
- ⏩ `-yes`/`-y` flag to skip confirmation prompt for scripted/automated usage

## [2.25.0] — 2026-03-19

### PHP Notice Resilience in Status Checks & Version Consolidation
- 🛡️ `-pas`/`-ps` now strips PHP deprecation notices (e.g. UpdraftPlus) before JSON parsing — no more "version unknown"
- 🔍 `-v` (verbose) flag shows raw JSON response body for status and log retrieval in both sequential and parallel modes
- 🏷️ QUpload status response fields now use enum-backed `ResponseKeyType` for type-safe parsing
- 📊 Parallel status checks resilient to `WP_DEBUG_DISPLAY` noise from third-party plugins
- 🔌 `-logplugin` filter: clear logs for a specific plugin only (q|qupload|r|riseup)
- 📂 `-logtype` filter: clear specific log types (log|err|stack|files|db|all)
- 🗑️ `-audit` flag: clear plugins-onboard audit/activity logs from database via REST API
- 💣 `-purge` command: clear ALL file logs + audit logs in one command with site targeting

## [2.22.0] — 2026-03-20

### PHP Notice Resilience, Verbose Status & Enum-Backed QUpload Keys
- 🛡️ Status and log responses now strip PHP warnings/notices before JSON parsing
- 🔍 Added `-v` (verbose) flag to `-pas`/`-ps` for raw JSON response output
- 🏷️ QUpload status fields use `ResponseKeyType` enum

## [2.20.0] — 2026-03-19

### Cross-Upload Resilience
- 🔄 Cross-upload: QUpload is now uploaded via Riseup Asia API for resilience (fallback to self-upload if unavailable)
- 🛡️ Upload pipeline detects cross-upload partner availability via `/status` probe before choosing API
- ⚙️ `upload-plugin-U-Q.ps1` now supports `-ApiNamespace` parameter for flexible endpoint targeting
- 🐛 Fixed PHP syntax error in QUpload `UploadFileSystemTrait.php`

## [2.18.0] — 2026-03-18

### Version Bump & Consistency Checker Enhancements
- 🔤 Go abbreviation casing rule now uses PascalCase config with dynamic ALL-CAPS derivation
- 📋 Added `go-struct-field-casing` rule to consistency checker spec

## [2.17.0] — 2026-03-15

### CLA Root Cause Fix — Endpoint Gating & Machine Approval Source
- 🛠️ Fixed Riseup endpoint gating defaults so remote log routes are no longer implicitly disabled on older saved settings
- 🔒 Endpoint auth/enable checks now deep-merge settings and default missing keys to secure enabled behavior
- ⚙️ Added remote log endpoint controls to the Riseup Settings endpoint configuration UI
- 📁 QUpload machine approval now reads `approved_machines` from `settings.json` first
- 🧭 Improved `-cla` troubleshooting text for `rest_disabled` vs `machine_not_approved` causes

## [2.16.0] — 2026-03-15

### Clear Logs Diagnostics & PS 7+ Error Extraction Fix
- 🐛 Fixed `-cla` showing bare "403" with no response body (PS 7+ compatible error extraction)
- 📋 Clear logs now shows full WordPress REST error details (code, message, status)
- 🔍 Response body preview shown on failure for immediate diagnostics
- 💡 Troubleshooting guide printed on failures (403, 404, 401 causes)

## [2.15.0] — 2026-03-15

### Settings UI Fix, Clear Logs Targeting & REST Conventions
- 🐛 Fixed "Undefined array key" warnings in Snapshot Settings UI (PascalCase key migration)
- 🧹 `-cla` flag clears logs on ALL sites; `-cl` now supports `-site`, `-i`, `-xs` targeting

## [2.14.0] — 2026-03-14

### User Management System — CRUD, App Passwords, Yoast SEO & Bulk Import/Export
- 👤 Full user CRUD endpoints (GET, POST, PUT, DELETE) with pagination and search
- 🔑 Application password creation for users via REST API
- 📱 11 social platform fields (Facebook, Instagram, LinkedIn, X, Mastodon, etc.)
- 🎓 12 Yoast SEO metadata fields (Honorifics, Expertise, Employer, Job Title, etc.)
- 📥 Bulk import/export via CSV and SQLite ZIP bundles with password hash preservation

## [2.13.0] — 2026-03-14

### Rollback Protection, New QUpload Endpoints & Parallel Architecture
- 🛡️ Auto-rollback on failed plugin uploads for both QUpload and Riseup Asia Uploader
- 🔌 `PUT /deactivate` endpoint for remote plugin deactivation via QUpload API
- 📋 `GET /plugins` endpoint for remote plugin inventory via QUpload API
- ⚡ New `-u -as` flag for uploading default plugin to all sites (parallel)
- 🔓 QUpload now included in `-uas` bulk uploads

## [2.12.0] — 2026-03-14

### Timezone-Aware Logging, Log Clearing Endpoints & Remote Log Management
- 🕐 Timezone-aware log formatting using WordPress site timezone
- 🗑️ REST API endpoints for log status, clear, and confirm across both plugins
- 📋 Admin dashboard endpoint reference tables
- 🧹 PowerShell `-cl` flag for remote log clearing

## [2.11.0] — 2026-03-13

### Multi-Site Deployment, QUpload Fallback & Default Credential Flow
- 🌐 Multi-site deployment (`-uas`) with Base64-encoded credentials in `powershell.json`
- 🔄 QUpload fallback in Go publish pipeline (upload + activation)
- 🔑 Connection test & `GetDecryptedPassword` now use default SiteCredential

## [2.10.0] — 2026-03-13

### Multi-User Per Site Spec & Version Bump
- 📋 Multi-user per site credential spec created
- 🌱 Test V1 & Test V2 sites added to seed config with credentials

## [2.9.0] — 2026-03-12

### Scheduler Config Safety & PHPStan Integration
- 🐛 Fixed "Undefined array key schedule_enabled" warning in `SchedulerConfigTrait`
- 🛡️ All scheduler settings keys now use null-coalescing with safe defaults
- 🔬 PHPStan level-6 static analysis integrated into pre-upload and pre-commit pipelines

## [2.7.0] — 2026-03-12

### Versioned Log Entries & Minor Version Bump
- 📋 Log entries now include plugin version tag `[vX.Y.Z]`
- 📅 Log display dates use DD-MMM-YY HH:MM AM/PM format
- 🛡️ Backed enum duplicate lint added to upload scripts

## [2.6.0] — 2026-03-12

### Report/Feedback Feature & Modern UI Overhaul
- 📬 New Report/Feedback page — submit bug reports with subject, body, up to 3 screenshot attachments via `wp_mail()`
- ⚙️ Support Settings — configurable support email and fallback ticket URL
- 🎨 Complete admin UI modernization — CSS variables, gradient buttons, ripple effects, card hover animations

## [2.5.0] — 2026-03-12

### Clear Button Disk Delete & Upgrade Hook Log Reset
- 🗑️ Clear Log button now deletes files from disk instead of truncating
- 🔄 Added `upgrader_process_complete` hook for forced log reset after plugin update

## [2.4.0] — 2026-03-12

### Version-Based Log Clearing on Plugin Update
- 🧹 Both plugins now clear all log files automatically on version update (not every load)
- 📦 Added `clearAllLogFiles()` to FileLogger via `LoggerPathTrait`

## [2.3.0] — 2026-03-12

### ORM Case-Sensitivity & Type-Hint Fixes
- 🐛 Fixed ORM class case-sensitivity issue (`ORM` → `Orm`) preventing database initialization on Linux
- 🐛 Fixed 4 broken `RiseupORM` type hints in `DatabaseQuerySearchTrait`
- 🐛 Fixed asset path depth so admin CSS/JS loads correctly

## [2.2.0] — 2026-03-12

### QUpload Admin Menu & Error Log Viewer
- 🖥️ QUpload admin menu system with Dashboard and Error Logs pages
- 📋 Tabbed error log viewer (Log, Error, Stack Trace) with Copy, Download, Clear, and Live auto-refresh
- 🔧 Fixed template include paths across both plugins

## [2.1.0] — 2026-03-12

### PHP Syntax Fixes & Go Spacing Audit
- 🔧 Fixed duplicate `<?php` tag in `admin-settings.php`
- 🛡️ Verified `ManagerRestoreTrait.php` syntax for PHP 8.2 compatibility
- 📏 Full Go source audit: enforced blank-line-before-return and blank-line-before-if spacing rules

## [2.0.0] — 2026-03-09

### Major Version 2.0 — Full Codebase Audit & Hardening
- 🎯 Major version bump across all components
- 🛡️ Full PHP reliability audit: guarded all filesystem operations
- 🔒 Eliminated silent catch blocks; ensured Throwable-first logging with stack traces
- 📏 Enforced 500-line PHP file limit across all plugins with trait decomposition
- 📦 QUpload plugin hardened: ZIP extraction, directory operations, and activation flows
- ✅ All PHP enums standardized with PascalCase values and typed backing

---

_For older versions (1.x), see [`public/version.json`](../../public/version.json) changelog entries._

---

**Author:** MD ALIM UL KARIM · [rasia.pro](https://rasia.pro/alim-r-profile-v1)
