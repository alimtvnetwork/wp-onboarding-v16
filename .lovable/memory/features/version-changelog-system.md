# Memory: features/version-changelog-system
Updated: 2026-02-04
Updated: 2026-02-04

---

## Overview

The application implements a version-based "What's New" notification system that:
1. Tracks the current app version via `public/version.json`
2. Maintains human-readable changelog in `CHANGELOG.md`
3. Auto-shows a popup modal when the user opens the app after a version update
4. Stores "last seen version" in localStorage to prevent repeat notifications

---

## Files

| File | Purpose |
|------|---------|
| `public/version.json` | Machine-readable version, changelog entries, roadmap items |
| `CHANGELOG.md` | Human-readable Keep a Changelog format |
| `src/hooks/useWhatsNew.ts` | Hook for version tracking, localStorage management |
| `src/components/settings/WhatsNewModal.tsx` | Tabbed modal (Latest / Roadmap / History) |
| `src/components/settings/VersionBadge.tsx` | Clickable badge that opens What's New modal |

---

## Behavior

1. **Auto-popup**: When `version.json` version differs from `localStorage["wp-plugin-publish-last-seen-version"]`, the modal auto-opens
2. **First-time users**: Modal does NOT auto-open on first visit (no stored version)
3. **Manual access**: Clicking the version badge in the header opens the modal
4. **Mark as seen**: Closing the modal stores the current version in localStorage

---

## Updating the Version

When making changes:
1. Update `public/version.json` with new version number and changelog entry
2. Update `CHANGELOG.md` with the same information
3. The UI will automatically show the "What's New" popup on next load

---

*Single source of truth for version info: `public/version.json`*
