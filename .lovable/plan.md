# Master Roadmap & Backlog

> Updated: 2026-03-03

---

## Completed Phases

### ✅ Go Phase 4: Positive Logic & Boolean Standards
- Renamed 12 negative booleans (`isNot*`, `hasNo*`, `isNon*`) to positive polarity across 11 files
- Extracted 15 `!positiveVar` patterns to named positive intermediate variables
- Zero violations remain in non-test Go files

---

## Active / Queued Work

### 🔲 Task: Eliminate Magic Strings in Template JS

**Status:** Ready to implement  
**Scope:** 2 files, ~45 replacements  
**Priority:** High (code quality debt)

Replace hardcoded REST paths, status strings, response keys, and UI labels in `admin-agents.php` and `admin-snapshots.php` inline `<script>` blocks with PHP enum-driven JS constant blocks.

**Files:**
- `admin-agents.php` — Add `ENDPOINTS`, `AGENT_STATUS`, `STATUS`, `LABELS` JS blocks; replace ~30 magic strings
- `admin-snapshots.php` — Add `use EndpointType;`, `SNAP_ENDPOINTS`, `SNAP_LABELS` JS blocks; replace ~26 magic strings

**Key rule:** The text domain `'riseup-asia-uploader'` must also come from `PluginConfigType::TextDomain->value` (or equivalent constant), not as a magic string.

**Acceptance criteria:**
1. Zero hardcoded REST paths in JS — all use PHP-echoed `EndpointType` values
2. Zero hardcoded status strings — all use PHP-echoed `StatusType`/`AgentStatusType` values
3. Zero hardcoded UI labels — all use `__()` localized strings via PHP-echoed JS constants
4. Zero hardcoded response keys — all use `ResponseKeyType` values
5. Existing functionality unchanged (manual test: agents CRUD, snapshot operations)

---

## Phase 7: Feature Implementation

> Architecture decisions resolved. Implementation details below.

### 7A: Remote Plugin Backups (WP-site storage)

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

### 7B: Bulk Quick Publish (Multi-select)

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

### 7C: True Diff (Remote File Hash Comparison)

**Decision:** Use remote file hashes for accurate change detection.  
**Priority:** Medium  
**Dependencies:** Existing sync-manifest endpoint

#### Objective
Replace the current local-only file change detection with remote hash comparison, providing accurate "X files changed" counts before publish.

#### Implementation Plan

**PHP (Riseup Asia Uploader):**
1. Ensure `sync-manifest` endpoint returns MD5/SHA256 hashes for all plugin files
2. Add `fileCount` and `totalSize` summary fields to manifest response

**Go Backend:**
1. New `DiffService` in `backend/internal/services/diff/`
2. `ComputeDiff(localDir, remoteManifest)` → returns `DiffResult{Added, Modified, Deleted, Unchanged []FileDiff}`
3. Each `FileDiff`: `{Path, LocalHash, RemoteHash, LocalSize, RemoteSize, ChangeType}`
4. Cache remote manifest per site+plugin with TTL (avoid repeated API calls)
5. Pre-publish step: compute diff → show summary → confirm → publish only changed files

**React Frontend:**
1. "Show Changes" button on plugin card → calls diff endpoint
2. Diff summary panel: added (green), modified (yellow), deleted (red), unchanged (gray)
3. File-level expandable list with size delta
4. Pre-publish confirmation dialog shows diff summary instead of generic "publish?"

**Acceptance criteria:**
1. Diff accurately identifies added, modified, deleted, and unchanged files
2. Pre-publish shows exact change count matching actual file differences
3. Manifest is cached to avoid redundant API calls within TTL
4. User can review individual file changes before confirming publish

---

### 7D: Licensing System (Custom Go Server)

**Decision:** In-house custom Go licensing server.  
**Priority:** Low (architecture phase — implementation deferred)  
**Dependencies:** None (standalone service)

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

### 🔲 Go Phase 5: Code Organization Standards
- Enforce package directory snake_case
- Validate import grouping (stdlib / third-party / internal)
- Monitor function and file size limits
- Run `lint-all` and fix violations

### 🔲 Go Phase 6: CI Lint Scripts & Integration
- Integrate all lint scripts into CI pipeline
- Add pre-commit hooks for import ordering
- Automated quality gates for PRs

---

## Next Task Selection

> For handoff to other AI models: pick the next task from the top of the "Active / Queued Work" section. The magic strings task is the highest priority and has no dependencies. Phase 7A (backups) should follow, then 7B (bulk publish) and 7C (true diff) can proceed in parallel. Phase 7D (licensing) is architecture-only for now.

**Recommended order:**
1. Eliminate magic strings in template JS (ready now)
2. Phase 7A: Remote plugin backups
3. Phase 7B: Bulk quick publish + Phase 7C: True diff (parallel)
4. Go Phase 5: Code organization
5. Go Phase 6: CI lint integration
6. Phase 7D: Licensing (architecture doc first, implementation later)
