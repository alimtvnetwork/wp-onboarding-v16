# Master Roadmap & Backlog

> Updated: 2026-03-15

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
- ✅ Pre-publish hook integration complete (Go calls backup before upload)

### ✅ 7A Remaining: Pre-Publish Backup Hook (Go)
Wire the remote backup endpoint into the Go publish pipeline so a backup is automatically created before each upload. — **Complete**

### ✅ 7B: Bulk Quick Publish (Multi-select) — Go + React complete

### ✅ 7C: True Diff (Remote File Hash Comparison) — Go + React complete

### ✅ 7D: Licensing System (Custom Go Server) — Architecture + scaffold + handlers complete

### ✅ Go Phase 5: Code Organization Standards
- Renamed 5 files to PascalCase
- Split oversized files, refactored helpers
- All 247 Go files within 300-line limit; all functions within 15-line body limit

### ✅ Go Phase 6: CI Lint Scripts & Integration
- All 7 lint scripts enforced in CI for both backend and licensing modules
- Pre-commit hooks and dedicated CI jobs

### ✅ Phase 7E: Cloud Storage Providers (All 3 Phases Complete — 2026-03-15)

**Phase 1 — GitHub (PAT/Git Data API):**
- `CloudStorageGitHubTrait.php` — Git Data API for file operations
- AES-256-CBC encrypted credential storage
- Provider-agnostic CRUD interface

**Phase 2 — GitLab (Private-Token, self-hosted support):**
- `CloudStorageGitLabTrait.php` — REST API with self-hosted BaseUrl support
- Shared upload/file/account traits

**Phase 3 — Google Drive (OAuth2, resumable uploads):**
- `CloudStorageGoogleDriveTrait.php` — Drive v3 API, chunked streaming (262KB chunks)
- `CloudStorageOAuthTrait.php` — OAuth2 flow with CSRF state via WP transients
- Token auto-refresh with 60s buffer

**React Dashboard:**
- `CloudStorageSettingsPage` with provider tabs (GitHub, GitLab, Google Drive)
- `CloudStorageAccountCard` with masked tokens, test connection, action dropdown
- `CloudStorageAccountDialog` with dynamic fields per provider
- `CloudStorageProviderSettings` — auto-backup toggle, retention slider, backup prefix
- `CloudStorageBackupSelector` — collapsible selector in publish dialog with localStorage persistence
- 8 API methods in `src/lib/api/methods.ts`
- Route: `/cloud-storage`

**Publish Integration:**
- `cloudStorageAccountIds` passed through `useQuickPublish`, `useBulkQuickPublish`, and publish dialog
- `cloud_upload` stage added to `PublishProgressDialog` (between backup and package)
- `bulkPublish` API accepts `cloudStorageAccountIds`

---

## Remaining Work

### 🔲 Type-Safety Migration: `interface{}` → `any`
**Priority:** Medium  
**Dependencies:** None  
**Status:** Partially complete (see `.lovable/memory/architecture/backend/go-type-remediation-progress`)

Migrate remaining ~2,680 `interface{}` instances across ~58 Go files to typed alternatives.

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

### 5. 🟡 Cloud Storage — Pending Backend Integration
- **Action:**
  - [ ] Add `cloud_upload` stage to `ServicePublishPipeline.go` — emit WS events for cloud upload progress (S-044)
  - [ ] Add Google OAuth settings to admin Settings page (S-042)
  - [ ] Conditionally show `cloud_upload` stage in PublishProgressDialog only when accounts selected (S-041)
  - [ ] Bump plugin versions to 2.15.0 (S-043)

---

## Next Task Selection

> For handoff to other AI models: pick the next unchecked (🔲) task from "Active Issues" above.

**Recommended order:**
1. **ORM PDO Fix** — redeploy Riseup Asia Uploader (critical, admin broken)
2. **Cloud Storage backend integration** — wire `cloud_upload` stage into Go pipeline
3. **QUpload Activate → PUT** — all-layer HTTP method fix
4. **QUpload UI Uplift** — version header + design parity
5. **Log Rotation** — both plugins
6. **Type-safety audit** — confirm remaining `interface{}` scope
7. **Future considerations** (not yet scoped):
   - Admin dashboard for licensing server (React SPA or Go templates)
   - Publish analytics / history reporting
   - Plugin dependency graph visualization
