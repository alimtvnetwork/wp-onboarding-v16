# Changelog

All notable changes to **WP Plugin Publish** (frontend dashboard) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-02-04

### Added
- **Site Management**: Add, edit, delete WordPress sites with connection testing
- **Plugin Manager**: Scan local plugins, map to remote sites, track sync status
- **Real-time Sync Dashboard**: WebSocket-powered live updates during sync operations
- **Global Error Handling**: Tabbed error modal with stack traces, request info, and suggested fixes
- **Backend Status Banner**: Detects HTML-instead-of-JSON responses with "View Details" button
- **Configurable Logging**: 12-hour timestamp format from single source of truth (`config.json`)
- **PowerShell Runner**: `-r` flag for complete clean rebuild (clean → install → build → run)
- **What's New Popup**: Version-based changelog notification on app updates

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

[1.0.0]: https://github.com/riseup-asia/wp-onboarding-v5/releases/tag/v1.0.0
[Unreleased]: https://github.com/riseup-asia/wp-onboarding-v5/compare/v1.0.0...HEAD
