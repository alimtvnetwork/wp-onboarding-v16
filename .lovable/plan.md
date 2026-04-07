# Master Roadmap & Backlog

> Updated: 2026-03-22

---

## Completed Phases

### ✅ Go Phase 4: Positive Logic & Boolean Standards
- Renamed 12 negative booleans across 11 files; zero violations remain

### ✅ Magic Strings Elimination in Template JS
- `ActionType` enum in `admin-agents.php`; `SNAP_RETENTION`/`SNAP_AJAX` constants

### ✅ Phase 7A: Remote Plugin Backups (PHP + Go endpoints)
- `BackupType`, `BackupStatusType`, `BackupConfigType` enums
- 4 endpoints: backup, restore, list, delete
- Auto-retention max 5 backups per plugin
- Pre-publish hook integration complete

### ✅ 7B: Bulk Quick Publish — Go + React complete

### ✅ 7C: True Diff (Remote File Hash Comparison) — Go + React complete

### ✅ 7D: Licensing System — Architecture + scaffold + handlers complete

### ✅ Go Phase 5: Code Organization Standards
- All 247 Go files within 300-line limit; all functions within 15-line body limit

### ✅ Go Phase 6: CI Lint Scripts & Integration
- All 7 lint scripts enforced in CI; pre-commit hooks active

### ✅ Phase 7E: Cloud Storage Providers (3 Phases)
- GitHub (PAT/Git Data API), GitLab (Private-Token, self-hosted), Google Drive (OAuth2, resumable uploads)
- Full React dashboard, publish integration, quick publish support

### ✅ Phase B: Backend Integration
- B-1: `cloud_upload` stage wired into Go pipeline
- B-2: Plugin versions bumped to 2.17.0

### ✅ Phase C: All-Layer HTTP Method Fix
- QUpload Activate → PUT across PHP, Go, Frontend, specs, and memory docs
- 6 error-context strings fixed in RemotePluginsPanel.tsx

### ✅ Phase D: UI & UX
- D-1: QUpload Admin UI Uplift (version badge, admin-shared.css animations, gradient buttons)
- D-2: Backup History Visualization (timeline UI in Cloud Storage dashboard)

### ✅ Phase E: Git Backup Strategy (6 sub-phases)
- E-1 → E-6 all complete: repo selection, backup engine, WP-Cron scheduling, Git clone restore, history visualization, Google Drive folder adaptation

### ✅ Phase F: Configuration & Monitoring
- F-1: `settings.json` for QUpload
- F-2: `/logs/rotation-status` endpoint
- F-3: Verbose `-check` mode (HEAD requests)
- F-4: Auto-invalidate cached ZIP on source change

### ✅ Phase G: Go Type Safety — `any` Elimination (Complete)
- **Standard:** `any` prohibited in production Go except justified exceptions
- **All 6 sub-phases complete** (G-1 through G-6)
- Spec: `spec/05-golang-standards/04-type-safety-no-any.md`

### ✅ Phase H-1: Licensing Admin Dashboard
- React dashboard with license CRUD, audit log viewer, health badge
- Go Stats/BatchRevoke/BatchExtend/ExportCSV endpoints + routes
- Analytics tab with distribution charts, creation timeline, expiration alerts
- Batch operations with confirmation dialogs and CSV export

### ✅ S-046: Google Drive Folder Rotation
- Go endpoint/operation enums, WP client, handlers, service proxy
- PHP CloudStorageRotationTrait with 3 rotation policies (delete_oldest, archive_oldest, keep_full_delete_incremental)
- Route registration and ResponseKeyType entries
- React UI already complete (GoogleDriveRotationStatus component)

---

## Active Issues

### Phase A: Deployment & Verification (🔴 Blocked — needs user)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| A-1 | Deploy v2.17.0+ to all remote sites | 🔴 Critical | User runs `.\run.ps1 -uas` | Blocked |
| A-2 | Verify EnvelopeBuilder fallback fix | 🔴 Critical | A-1 | Blocked |
| A-3 | Verify preflight check works on remote | 🟡 Medium | A-1 | Blocked |

---

### 🔴 Phase J: Bootstrap Deploy Pipeline Rewrite (2026-03-22)

**Issue:** `spec/02-app-issues/40-bootstrap-deploy-pipeline-rewrite.md`

**Problem:** Go bootstrap deployer has fundamental architectural flaws: self-update causes 500 errors, ZIP created per-site, sequential processing, no cross-upload strategy, no pre-flight, no progress visualization, missing delegated error logs.

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| J-1 | Rewrite Go bootstrap — ZIP once, pass to all sites | 🔴 Critical | — | ✅ Done |
| J-2 | Implement cross-upload strategy (QUpload→Riseup, Riseup→QUpload) | 🔴 Critical | J-1 | ✅ Done |
| J-3 | Parallel site uploads with goroutines | 🟡 High | J-1, J-2 | ✅ Done |
| J-4 | Pre-flight endpoint check UI (like PowerShell -pas) | 🟡 High | — | ✅ Done |
| J-5 | Phased progress UI in deploy dialog (ZIP→Upload→Summary) | 🟡 High | J-1, J-3 | ✅ Done |
| J-6 | Delegated error logs in error modal (remote 500 response bodies) | 🟡 High | — | ✅ Done |
| J-7 | Retry limiting — max 1 attempt per plugin per site | 🟢 Medium | J-2 | ✅ Done |

**Acceptance criteria:**
- Deploy uses cross-upload: QUpload endpoint for Riseup Asia, Riseup Asia endpoint for QUpload
- ZIP created once, reused for all sites
- Sites uploaded in parallel
- Pre-flight shows version comparison before deploy
- 500 errors include remote response body in error modal delegated tab
- No infinite retry loops

---

### Phase I: Dashboard UX & Data Pipeline Fix (2026-03-22)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| I-1 | Fix double-envelope wrapping (Health/Logs/Settings) | 🔴 Critical | — | ✅ Done |
| I-2 | Redesign SiteCard button layout | 🟡 High | — | ✅ Done |
| I-3 | PowerShell -d skip PHP propagation if no changes | 🟢 Medium | — | ✅ Done |
| I-4 | Redeploy to fix plugin_slug error (v2.30.0) | 🟡 High | User runs deploy | Blocked |

---

### ✅ Phase H: Future Features (All Complete)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| H-2 | Publish analytics / history reporting (S-054) | 🟢 Low | — | ✅ Complete |
| H-3 | User Management implementation | 🟢 Low | `spec/16-user-management/` exists | ✅ Complete |
| S-046 | Google Drive folder rotation | 🟢 Low | — | ✅ Complete |
| S-053 | Licensing admin dashboard (Go backend) | 🟢 Low | H-1 | ✅ Complete |

---

## Next Task Selection

> **For handoff to other AI models:** Pick the next task based on priority and unblocked status.

**All planned phases and suggestions are complete.** Only deployment verification remains blocked.

**Blocked (needs user action):**
- Phase A (A-1, A-2, A-3): Deploy v2.17.0+ via `.\run.ps1 -uas`
- I-4: Redeploy for plugin_slug fix (v2.30.0)

**Potential future features (Phase K — not yet specced):**
1. **K-1: Scheduled Publishing** — queue publishes for future time
2. **K-3: Webhook Notifications** — Slack/Discord/custom URL on publish events
3. **K-4: Multi-user Access Control** — role-based dashboard access
4. **K-5: Plugin Dependency Graph** — visualize shared library dependencies

---

*Master plan for AI handoff. All phases complete. Deploy to unblock Phase A.*
