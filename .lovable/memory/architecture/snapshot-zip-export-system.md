# Architecture: Snapshot ZIP Export & Download System

> Created: 2026-02-12

## Overview

The snapshot ZIP export system enables users to download full database snapshots (with all child incrementals bundled) as a single ZIP file. The architecture spans three layers: PHP (WordPress plugin), Go (backend proxy), and React (frontend UI).

## Data Flow

```
React UI → Go Proxy → WordPress REST API → PHP Exporter → SQLite + Filesystem
                                                              ↓
React UI ← Go Proxy (streams binary) ← WordPress (signed URL) ← ZIP file on disk
```

### Request Flow

1. **React** calls `POST /sites/{siteId}/snapshots/download` with `{ snapshot_id }`.
2. **Go proxy** forwards to WordPress `POST /snapshots/download` with authenticated request.
3. **PHP** checks `snapshot_exports` table for a cached ZIP:
   - **Cached**: Returns metadata with signed download URL immediately.
   - **Not cached**: Builds ZIP (full + all incrementals), stores in `exports/` subdir, inserts DB record, returns metadata.
4. **Go proxy** receives metadata, then calls `rawGet(downloadUrl)` to stream the ZIP binary back to React.
5. **React** receives the `Blob`, extracts metadata from custom headers (`X-Snapshot-Cached`, `X-Snapshot-Size`), and triggers a browser download.

## PHP Layer (WordPress Plugin)

### SQLite Schema: `snapshot_exports`

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment |
| snapshot_id | INTEGER | FK to full snapshot |
| zip_filename | TEXT | Human-readable filename |
| zip_path | TEXT | Full filesystem path |
| zip_size | INTEGER | Bytes |
| included_ids | TEXT | JSON array of snapshot IDs |
| incremental_count | INTEGER | Number of incrementals bundled |
| created_at | TEXT | ISO datetime |
| expires_at | TEXT | NULL = valid until invalidated |
| status | TEXT | 'valid' / 'expired' / 'building' |

### Key Classes

- **`RiseupSnapshotExporter`**: Singleton via factory. Methods: `getOrBuildZip()`, `invalidateZip()`, `removeExports()`, `getDownloadUrl()`, `validateDownloadToken()`, `getExportStatus()`.
- **`RiseupSnapshotFactory`**: Provides `getExporter()` accessor.

### REST Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/snapshots/download` | Get or build ZIP, returns metadata envelope |
| GET | `/snapshots/download-file?token=&id=` | Serve ZIP binary (nonce-validated, 8KB chunked streaming) |

### Auto-Invalidation

- **New incremental backup**: `invalidateParentZipExport()` expires the parent's cached ZIP.
- **Full snapshot deletion**: `removeExports()` cascade-deletes ZIP files and DB records.
- Both wrapped in try-catch for fault tolerance.

### ZIP Compression

Uses `ZipArchive::CM_DEFLATE` at maximum compression level, matching Go's `flate.DefaultCompression` (Level 6).

## Go Layer (Backend Proxy)

### Endpoint

`POST /sites/{id}/snapshots/download` → handler in `snapshot_handlers.go`

### Key Components

- **`rawGet(fullURL)`** on WordPress client: Authenticated GET to arbitrary URLs (for streaming signed download URLs).
- **`StreamSnapshotZip()`** in site service: Orchestrates the two-step flow (metadata request → binary stream).
- **Custom Response Headers**: `X-Snapshot-Cached` (bool), `X-Snapshot-Size` (bytes).

### Constants

- `EndpointSnapshotsDownload = "/snapshots/download"`
- `EndpointSnapshotsDownloadFile = "/snapshots/download-file"`

## React Layer (Frontend UI)

### API Method

`api.downloadSnapshotZip(siteId, snapshotId)` → returns `{ blob, filename, cached, size }`.

### UI Components

- **`SnapshotRow`**: "Download ZIP" button (full snapshots only). Shows `Loader2` spinner during build, success/error toasts.
- **`SnapshotDetailContent`**: "ZIP Export" section in detail dialog. Displays filename, size, cached/fresh-build badge. Re-download option after first use.

### WordPress Admin (PHP)

- **`btn-download-zip`**: Replaces legacy `btn-export` for full snapshots. Spinner during build, cached/built badge on success.
- **Error modal**: HTTP status, plugin version, timestamp, PHP stack trace (orange-themed), backend details (amber-themed), Copy Report button.

## Activity Log Color Coding

| Action | Color |
|--------|-------|
| ZIP Build | Teal |
| ZIP Expire | Slate |
| ZIP Download | Cyan |
