# Changelog

All notable changes to **WP Plugin Publish** (frontend dashboard) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.2.1] - 2026-02-04

### Fixed
- **Backend Health Response**: Now returns standard `{success:true, data:{status:"ok"}}` envelope
- **BackendStatus Detection**: Fixed logic that incorrectly flagged healthy JSON responses as "disconnected"
  - 2xx JSON response = connected (previously required `success:true` or `status:"ok"`)
  - HTML response = E9005 (correctly identifies misrouting)
  - Network error = E9003

### Added
- **API Index Endpoint**: `GET /api/v1` now returns API metadata (prevents 404 on base URL)
- **Enhanced Diagnostics**: Copy Diagnostics now shows:
  - Raw environment variables (`VITE_API_URL`, `VITE_WS_URL`)
  - UI origin (with port)
  - Resolved API origin
  - API base (relative and absolute)
- **Error Modal Improvements**: Request Info tab now displays:
  - Raw vs resolved environment values
  - UI origin
  - Absolute API base URL

### Documentation
- Created `spec/error-resolution/` folder for AI/developer retrospectives
- Updated `11-rest-api-endpoints.md` with health and index endpoint specs
- Updated `26-ui-patterns.md` with improved BackendStatus detection rules

---

## [1.2.0] - 2026-02-04

### Added
- **Copy Diagnostics Button**: Small button to copy API base, WS URL, and app version for support/debugging
- **About Panel in Settings**: Shows app name/version, script version, and links to changelogs
- **Build Metadata**: `version.json` now includes `gitCommit`, `buildTime`, and `scriptVersion`
- **Backend Version Logging**: Server logs now prefixed with app name + version at startup
- **What's New Build Info**: Modal now shows git commit and build date

### Changed
- Updated `VersionInfo` interface to support build metadata fields

---

## [1.1.0] - 2026-02-04

### Added
- **What's New Popup**: Version-based changelog notification with Latest/Roadmap/History tabs
- **View Details Button**: Backend Disconnected banner now opens Global Error Modal
- **PowerShell Versioning**: Script now has version tracking (v1.1.0) with dedicated changelog
- **Environment Config**: `.env` file with `VITE_API_URL` and `VITE_WS_URL` for local development

### Changed
- **Rebuild Flow**: `-r` flag now correctly sequences clean → install → build
- **Install Detection**: Respects pnpm node-linker mode (PnP vs isolated)

### Fixed
- "vite is not recognized" error when using `-r` rebuild flag
- PnP artifacts (`.pnp.cjs`, `.pnp.loader.mjs`) now cleaned in force mode

---

## [1.0.0] - 2026-02-04

### Added
- **Site Management**: Add, edit, delete WordPress sites with connection testing
- **Plugin Manager**: Scan local plugins, map to remote sites, track sync status
- **Real-time Sync Dashboard**: WebSocket-powered live updates during sync operations
- **Global Error Handling**: Tabbed error modal with stack traces, request info, and suggested fixes
- **Backend Status Banner**: Detects HTML-instead-of-JSON responses
- **Configurable Logging**: 12-hour timestamp format from single source of truth (`config.json`)
- **PowerShell Runner**: `-r` flag for complete clean rebuild

### Technical
- React 18 + TypeScript + Vite
- Tailwind CSS + shadcn/ui components
- Zustand for state management
- TanStack Query for data fetching
- WebSocket for real-time events

---

## [Unreleased]

### Planned
- E2E testing suite
- Bulk plugin operations
- Git integration for plugin versioning
- Multi-site sync operations

---

[1.2.1]: https://github.com/riseup-asia/wp-onboarding-v5/releases/tag/v1.2.1
[1.2.0]: https://github.com/riseup-asia/wp-onboarding-v5/releases/tag/v1.2.0
[1.1.0]: https://github.com/riseup-asia/wp-onboarding-v5/releases/tag/v1.1.0
[1.0.0]: https://github.com/riseup-asia/wp-onboarding-v5/releases/tag/v1.0.0
[Unreleased]: https://github.com/riseup-asia/wp-onboarding-v5/compare/v1.2.1...HEAD
