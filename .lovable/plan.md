# Master Roadmap & Backlog

> Updated: 2026-03-10

---

## Completed Phases

### ✅ Go Phase 4: Positive Logic & Boolean Standards
- Renamed 12 negative booleans (`isNot*`, `hasNo*`, `isNon*`) to positive polarity across 11 files
- Extracted 15 `!positiveVar` patterns to named positive intermediate variables
- Zero violations remain in non-test Go files

### ✅ Magic Strings Elimination in Template JS
- Replaced hardcoded action strings in `admin-agents.php` with `ActionType` enum
- Added `SNAP_RETENTION` and `SNAP_AJAX` JS constant blocks in `admin-snapshots.php`
- Replaced 5 inline PHP enum calls with JS constants

### ✅ Phase 7A: Remote Plugin Backups (PHP + Go endpoints)
- Created `BackupType`, `BackupStatusType`, `BackupConfigType` enums
- Added 4 new `EndpointType` cases: `PluginBackup`, `PluginBackupRestore`, `PluginBackupList`, `PluginBackupDelete`
- Added `Backups` to `PathSubdirType` and `getBackupsDir()` to `PathHelperCoreTrait`
- Implemented `PluginBackupHandlerTrait` with full CRUD (create zip, restore, list, delete)
- Registered 4 routes in `PluginRouteRegistrationTrait`
- Wired trait into `Plugin.php`
- Added 4 matching Go `Variant` constants to keep drift test in sync
- Auto-retention enforces max 5 backups per plugin
- **Remaining:** Go pre-publish hook integration (calls backup before upload)

---

## Remaining Work

### ✅ 7A Remaining: Pre-Publish Backup Hook (Go)
**Priority:** High  
**Dependencies:** 7A PHP endpoints (complete)  
**Status:** Complete

Wire the remote backup endpoint into the Go publish pipeline so a backup is automatically created before each upload.

**Implementation:**
1. In `ServicePublishPipeline.go` → `runPublishPipeline`, call backup endpoint via `wpClient` before `runUploadAndActivate`
2. Add `createRemoteBackup(ctx, pctx)` method that POSTs to `EndpointType::PluginBackup`
3. On backup failure: log warning but don't block publish (configurable via `Options.IsRequireBackup`)
4. On publish failure: log "rollback available" message with backup metadata
5. Store backup ID in `PublishResult` for UI rollback button

**Acceptance criteria:**
1. Pre-publish backup is created on remote site before upload begins
2. Backup failure logs a warning but doesn't block publish (default behavior)
3. Failed publishes include rollback-available info in result
4. Backup step appears in publish session logs

---

### 🔲 Type-Safety Migration: `interface{}` → `any`
**Priority:** Medium  
**Dependencies:** None  
**Status:** Partially complete (see `.lovable/memory/architecture/backend/go-type-remediation-progress`)

Migrate remaining ~2,680 `interface{}` instances across ~58 Go files to typed alternatives (`any` alias or named types).

**Approach:**
1. Work package-by-package, starting with highest-count packages
2. Replace `interface{}` → `any` for simple cases
3. Create named type aliases for `map[string]any` patterns
4. Create typed structs for `map[string]interface{}` literals with known keys
5. Skip test files (acceptable per GE-5 standard)

**Packages by priority (estimated instance count):**
- `handlers` (~999) — ✅ Complete
- `services` (~942) — ✅ Complete  
- `ws` (~30) — ✅ Complete
- `envelope`, `models`, `config`, `database`, `dbops`, `logger`, `apperror` — ✅ Complete
- `wordpress/client` — ✅ Complete
- `wordpress` — ✅ Complete
- `cmd/server/main` — ✅ Complete
- Remaining packages — 🔲 Audit needed to confirm scope

---

## Phase 7: Feature Implementation

> Architecture decisions resolved. Implementation details below.

### ✅ 7A: Remote Plugin Backups (WP-site storage) — PHP + Go endpoints complete

**Decision:** Backups stored on the WordPress site only (no local copies).  
**Priority:** High  
**Dependencies:** Snapshot infrastructure (already built)

#### Objective
Before any plugin update/publish, automatically create a backup of the current plugin version on the remote WP site, enabling one-click rollback if the update fails.

#### Implementation Plan

**PHP (Riseup Asia Uploader):**
1. New `BackupType` enum: `PreUpdate`, `PrePublish`, `Manual`, `Scheduled`
2. New `BackupStatusType` enum: `Pending`, `InProgress`, `Complete`, `Failed`, `Restored`
3. New endpoint `EndpointType::PluginBackup` → `'plugins/backup'`
4. New endpoint `EndpointType::PluginBackupRestore` → `'plugins/backup-restore'`
5. New endpoint `EndpointType::PluginBackupList` → `'plugins/backup-list'`
6. New endpoint `EndpointType::PluginBackupDelete` → `'plugins/backup-delete'`
7. Handler trait `PluginBackupTrait` — creates zip of current plugin dir, stores in `wp-content/riseup-backups/{slug}/{timestamp}.zip`
8. Restore handler — extracts backup zip over current plugin dir, re-activates
9. List handler — returns available backups with metadata (size, date, type, version)
10. Auto-cleanup: retain last N backups per plugin (configurable, default 5)

**Go Backend:**
1. Add `PluginBackup`, `PluginBackupRestore`, `PluginBackupList`, `PluginBackupDelete` to `endpointtype.Variant`
2. Update drift test known asymmetries if needed
3. Pre-publish hook in `ServicePublish` → call backup endpoint before uploading
4. Rollback integration: if publish fails, offer restore via backup endpoint

**Acceptance criteria:**
1. `POST /plugins/backup` creates a zip backup of a specified plugin on the WP site
2. `POST /plugins/backup-restore` restores a plugin from a backup zip
3. `GET /plugins/backup-list` returns backup metadata for a plugin
4. Pre-publish automatically creates a backup before uploading
5. Failed publishes log a "rollback available" message with backup ID
6. Old backups auto-cleaned beyond retention limit

---

### ✅ 7B: Bulk Quick Publish (Multi-select) — Go + React complete

**Decision:** "Quick Publish Selected" enabled for bulk updates.  
**Priority:** Medium  
**Dependencies:** Existing publish pipeline, backup system (7A recommended first)

#### Objective
Allow selecting multiple plugins from the dashboard and publishing them to one or more sites in a single batch operation with progress tracking.

#### Implementation Plan

**Go Backend:**
1. New handler `BulkPublishHandler` accepting `{pluginSlugs: []string, siteIds: []int64}`
2. Sequential publish per plugin-site pair (not parallel — avoids overwhelming remote)
3. Progress tracking via existing session system — one session per bulk operation
4. Per-plugin result: `{slug, siteId, status, error?, backupId?}`
5. Pre-publish backup for each plugin (calls 7A)

**React Frontend:**
1. Multi-select checkboxes on plugin list
2. "Quick Publish Selected" button (disabled when 0 selected)
3. Site selector modal (if multiple sites configured)
4. Progress dialog showing per-plugin status with live updates
5. Summary dialog: success count, failure count, rollback options for failures

**Acceptance criteria:**
1. User can select 2+ plugins and publish to a site in one action
2. Progress updates appear in real-time per plugin
3. Failures don't block remaining plugins
4. Each published plugin has a pre-publish backup
5. Summary shows actionable rollback links for any failures

---

### ✅ 7C: True Diff (Remote File Hash Comparison) — Go + React complete

**Decision:** Use remote file hashes for accurate change detection.  
**Priority:** Medium  
**Dependencies:** Existing sync-manifest endpoint

#### Objective
Replace the current local-only file change detection with remote hash comparison, providing accurate "X files changed" counts before publish.

#### Implementation Summary

**Go Backend:**
1. `ManifestCache.go` — in-memory TTL cache (default 5 min) for remote manifests per plugin+site
2. `ServiceDiff.go` — standalone `ComputeDiff()` method with fresh manifest comparison
3. `ServicePreviewDiff.go` — `FileDiffSummary` extended with `Unchanged` count; `classifyLocalFiles` now tracks unchanged files
4. `ServicePreview.go` — `fetchRemoteFileMap` now uses sync-manifest endpoint first (cached on both PHP and Go sides), falls back to files endpoint
5. `Service.go` — `PublishPreviewResult` and `FilePreview` extended with `Unchanged` field
6. Route: `GET /plugins/{id}/sites/{siteId}/diff` → `ComputeDiff` handler

**React Frontend:**
1. `FilePreview` and `PublishPreview` types include `unchanged` field
2. `DiffResult` type added for standalone diff endpoint
3. `api.computeDiff()` method added
4. `DiffPreviewDialog` — "Changed" tab as default, "Unchanged" tab added, selection excludes unchanged files by default

---

### ✅ 7D: Licensing System (Custom Go Server) — Architecture + scaffold + handlers complete

**Decision:** In-house custom Go licensing server.  
**Priority:** Low (handlers/services complete — integration next)  
**Dependencies:** None (standalone service)  
**Architecture doc:** `.lovable/memory/features/licensing/architecture.md`  
**Module:** `licensing/` at repo root

#### Objective
Build a self-hosted licensing server in Go that issues, validates, and manages license keys for the plugin ecosystem.

#### Architecture Plan

**Licensing Server (new Go service):**
1. Separate Go module: `licensing/` at repo root
2. SQLite database for license storage (portable, no external DB dependency)
3. REST API endpoints:
   - `POST /licenses` — create license (admin)
   - `GET /licenses/{key}/validate` — validate license key
   - `POST /licenses/{key}/activate` — activate on a domain
   - `POST /licenses/{key}/deactivate` — deactivate from a domain
   - `GET /licenses/{key}/status` — full license details
4. License model: `{Key, Email, Product, MaxActivations, Activations[], ExpiresAt, Status}`
5. Rate limiting and HMAC signature verification for API calls
6. Admin dashboard (simple HTML/Go templates or React SPA)

**PHP Integration:**
1. New `LicenseType` enum for license status
2. License check on plugin activation — call licensing server
3. Periodic re-validation (daily cron via `wp_schedule_event`)
4. Graceful degradation: plugin works but shows admin notice if license invalid

**Go Backend Integration:**
1. License middleware for premium API endpoints
2. License status cached locally with TTL

**Acceptance criteria (architecture only — implementation in future phase):**
1. Architecture document approved
2. Database schema defined
3. API contract documented (OpenAPI spec)
4. Integration points identified in PHP and Go codebases

---

## Remaining Queued Phases

### ✅ Go Phase 5: Code Organization Standards
- Renamed 5 files to PascalCase: `crypto.go`, `envelope.go`, `envelope_test.go`, `logger.go`, `version.go`
- Split `envelope.go` (345→259 lines) → extracted fluent modifiers to `EnvelopeModifiers.go` (108 lines)
- Split `logger.go` (304→139 lines) → extracted formatting helpers to `LoggerFormat.go` (173 lines)
- Refactored `Crypto.go`: extracted shared `deriveGCM` and `decryptWithGCM` helpers (Encrypt/Decrypt now ≤15 lines)
- Refactored `Version.go`: extracted `resolveVersionFile`, `resolveVersionFileFallback`, `parseVersionFile` (Load now ≤5 lines)
- Fixed `Client.go` import grouping (removed blank line splitting stdlib group)
- All 247 Go files within 300-line limit; all functions within 15-line body limit
- All package directories use correct naming conventions

### ✅ Go Phase 6: CI Lint Scripts & Integration
- Added `json-tags` and `inline-if` lints to CI workflow (previously missing)
- Added separate `lint-licensing` CI job covering all lint scripts for `licensing/` module
- Created `scripts/pre-commit.sh` — runs all quality gates locally across backend, licensing, and tools
- Created `scripts/install-hooks.sh` — one-command hook installation via symlink
- All 7 lint scripts now enforced in CI for both backend and licensing modules

---

## Active Issues (March 2026)

### 1. 🔴 ORM PDO Fix — Redeploy Riseup Asia Uploader
- **Issue:** `spec/02-app-issues/25-orm-pdo-class-not-found.md`
- **Action:** Redeploy plugin to remote site; verify `use PDO;` is present in deployed `OrmQueryTrait.php`
- **Scope:** Deployment only (code already fixed locally)

### 2. 🟡 QUpload Activate → PUT (All Layers)
- **Issue:** `spec/02-app-issues/26-qupload-activate-should-use-put.md`
- **Action:** Change HTTP method from POST to PUT across:
  - [ ] PHP: `QUpload/RouteRegistrationTrait.php` — `HttpMethodType::Put`
  - [ ] PHP: `QUpload/Enums/HttpMethodType.php` — add `Put` case if missing
  - [ ] PHP: `plugins-onboard/api/Api.php` — enable endpoint → PUT
  - [ ] Go: `QUploader.go` line 76 → `httpmethod.Put`
  - [ ] Go: `EndpointMap.go` EPEnablePlugin → `httpmethod.Put`
  - [ ] Frontend: API client enable/activate calls → PUT
  - [ ] PowerShell: Any direct activate calls → `-Method PUT`
  - [ ] Specs: Update endpoint documentation

### 3. 🟡 QUpload Admin UI Uplift
- **Issue:** `spec/02-app-issues/27-qupload-ui-uplift-version-header.md`
- **Action:**
  - [ ] Add version number badge to QUpload admin header
  - [ ] Apply shared `admin-shared.css` animations (fadeInUp, shimmer)
  - [ ] Match Riseup Asia Uploader's gradient button + high-contrast design
  - [ ] Visual QA against Riseup Asia reference

### 4. 🟡 Log Rotation for Both Plugins
- **Issue:** `spec/02-app-issues/28-log-rotation-both-plugins.md`
- **Action:**
  - [ ] Add `settings.json` with `logging.maxLogSizeBytes: 524288` to both plugins
  - [ ] Implement rotation in `FileLogger.php` (both plugins): check size before write, move to `archive/{NNN}/`
  - [ ] Rotate `log.txt`, `error.txt`, `stacktrace.txt` independently
  - [ ] Test with synthetic large writes
  - [ ] Verify admin log viewer still works post-rotation

---

## Next Task Selection

> For handoff to other AI models: pick the next unchecked (🔲) task from "Remaining Work" above.

**Recommended order:**
1. **ORM PDO Fix** — redeploy Riseup Asia Uploader (critical, admin broken)
2. **QUpload Activate → PUT** — all-layer HTTP method fix
3. **QUpload UI Uplift** — version header + design parity
4. **Log Rotation** — both plugins
5. **Type-safety audit** — confirm remaining `interface{}` scope
6. **Future considerations** (not yet scoped):
   - Admin dashboard for licensing server (React SPA or Go templates)
   - Publish analytics / history reporting
   - Plugin dependency graph visualization
