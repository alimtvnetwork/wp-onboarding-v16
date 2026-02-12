# Feature: Snapshot ZIP Export & Download

> Created: 2026-02-12

## Summary

Full database snapshots can be exported as ZIP files that bundle the master backup with all associated incremental children. The system supports caching (avoiding redundant rebuilds), automatic invalidation when new incrementals are created, and streaming downloads through a Go proxy.

## User-Facing Features

1. **Download ZIP Button** — Available on full snapshots in both the React dashboard and WordPress admin panel.
2. **Cached/Fresh Badge** — Indicates whether the ZIP was served from cache or freshly built.
3. **ZIP Export Metadata** — Detail dialog shows filename, size, and cache status after download.
4. **Error Reporting** — Failed downloads show a diagnostic modal with HTTP status, PHP stack trace, and backend error details.
5. **Auto-Invalidation** — Cached ZIPs are automatically expired when new incremental backups complete or when the parent snapshot is deleted.

## Constraints

- Only full (master) snapshots support ZIP download; incremental snapshots use the legacy single-file export.
- Manual filesystem edits to snapshot files do not trigger ZIP invalidation.
- Large databases may trigger async ZIP builds via WP-Cron; the UI shows a "building" state.
- The Go proxy streams the ZIP binary without buffering the entire file in memory.

## Implementation Phases (D1–D10)

All phases complete. See `.lovable/plan.md` Feature D for the full task breakdown.
