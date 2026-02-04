# Memory: architecture/backend/wordpress-integration
Updated: 2026-02-04

---

## Overview

WordPress integration prioritizes the **Riseup Asia Uploader** plugin (namespace `riseup-asia-uploader/v1`) which supports:
- Base64-based ZIP plugin uploads
- Delta file sync for multi-file updates
- Single-file replacement and deletion
- Plugin lifecycle management (enable/disable/delete)
- Export-self capability for bootstrapping to new sites
- Transaction logging to SQLite

### Fallback Namespaces

The system falls back to legacy namespaces for backward compatibility:
1. `riseup-asia-uploader/v1` (current)
2. `riseup-uploader/v1` (legacy)
3. `plugin-uploader/v1` (deprecated)

---

## Key Features

### Delta Sync

The `/plugins/{slug}/sync` endpoint enables efficient multi-file updates:
- Accepts array of files with `action: "replace"` or `action: "delete"`
- Content is base64 encoded for `replace` actions
- Respects `.uploadignore` patterns
- Returns detailed results including ignored files

### Export Self

The `/export-self` endpoint allows the plugin to export itself:
- Creates a base64-encoded ZIP of the plugin
- Respects `.uploadignore` patterns
- Returns checksum for integrity verification
- Used by the Bootstrap Uploader feature

### Bootstrap Uploader

The backend provides `POST /api/v1/sites/{id}/bootstrap-uploader` to:
1. Create a ZIP of the local `plugins-uploader-helper` directory
2. Upload to the target WordPress site
3. Activate the plugin automatically

---

## Connection Validation

Connection tests include:
- DNS resolution
- API availability (`/wp-json/`)
- Authentication with application passwords
- Functional write capability tests

---

## Files

| Component | File |
|-----------|------|
| Go Constants | `backend/internal/wordpress/constants.go` |
| Go Client | `backend/internal/wordpress/uploader.go` |
| PHP Plugin | `plugins-uploader-helper/riseup-asia.php` |
| PHP Constants | `plugins-uploader-helper/includes/constants.php` |
| Upload Ignore (PHP) | `plugins-uploader-helper/includes/class-upload-ignore.php` |
| Upload Ignore (Go) | `backend/internal/services/plugin/ignore.go` |
| Site Service | `backend/internal/services/site/service.go` |

---

## Seed Configuration

The Riseup Asia Uploader is included in the seed configuration (`backend/config.json`) under the `Core` category, automatically mapping to all configured sites.

---

*See also: `.lovable/memory/features/seeding/automatic-mappings.md`*
