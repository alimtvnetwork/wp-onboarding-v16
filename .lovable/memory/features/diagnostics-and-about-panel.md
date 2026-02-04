# Memory: features/diagnostics-and-about-panel
Updated: 2026-02-04

---

## Overview

The application provides robust diagnostics and transparency features to help users debug issues and share information with support.

---

## Components

### Copy Diagnostics Button
**Location**: `src/components/shared/CopyDiagnosticsButton.tsx`

A small button that copies:
- App name + version
- Git commit hash (short)
- Build time
- Script version
- VITE_API_URL setting
- API Base (relative + absolute)
- WebSocket URL
- User agent
- Timestamp

Use in: Settings About panel, Error modal footer, Backend status banner.

### About Panel
**Location**: `src/components/settings/AboutPanel.tsx`

Displays in Settings page:
- App name + version badge
- Git commit + build time (if available)
- PowerShell script version
- Links to App Changelog and Script Changelog
- Copy Diagnostics button

### Diagnostics Utility
**Location**: `src/lib/diagnostics.ts`

Pure utility functions:
- `getDiagnostics(versionInfo)` - Collects all diagnostic info
- `formatDiagnosticsForCopy(info)` - Formats for clipboard

---

## version.json Schema (v1.2.0+)

```json
{
  "appName": "WP Plugin Publish",
  "version": "1.2.0",
  "releaseDate": "2026-02-04",
  "gitCommit": "abc1234...",      // Optional: full or short commit hash
  "buildTime": "2026-02-04T12:00:00Z", // Optional: ISO timestamp
  "scriptVersion": "1.1.0",       // Optional: PowerShell script version
  "changelog": [...],
  "roadmap": [...]
}
```

---

## Backend Version Logging

**Location**: `backend/internal/version/version.go`

- Loads `version.json` from `frontend/dist/` or `public/`
- Falls back to defaults if not found
- Provides `Info.String()` for formatted logging

**Logger Prefix**: `backend/internal/logger/logger.go`

Logs prefixed with `[App Name vX.X.X]` when `AppName` and `AppVersion` are configured.

Example log output:
```
[WP Plugin Publish v1.2.0] [2026-02-04 12:00:00 PM] INFO main.go:60 - Starting application version=WP Plugin Publish v1.2.0 (abc1234)
```

---

*Build metadata should be injected by CI/CD pipeline during builds.*
