# Master Roadmap & Backlog

> Updated: 2026-03-18

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

### ✅ Phase G: Type Safety
- Zero `interface{}` outside test files; all migrated to `any` or typed generics

### ✅ Phase H-1: Licensing Admin Dashboard
- React dashboard with license CRUD, audit log viewer, health badge
- Standalone API client targeting licensing server via `VITE_LICENSING_URL`

---

## Active Issues

### Phase A: Deployment & Verification (🔴 Blocked — needs user)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| A-1 | Deploy v2.17.0+ to all remote sites | 🔴 Critical | User runs `.\run.ps1 -uas` | Blocked |
| A-2 | Verify EnvelopeBuilder fallback fix | 🔴 Critical | A-1 | Blocked |
| A-3 | Verify preflight check works on remote | 🟡 Medium | A-1 | Blocked |

**Expected outputs:** Confirmed working uploads; no 500 errors; `-check` passes all sites
**Acceptance criteria:** All remote sites on v2.17.0+; `-check` returns green for all endpoints

---

### Phase H: Future Features

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| H-2 | Publish analytics / history reporting | 🟢 Low | Spec needed | 🔲 Not scoped |
| H-3 | User Management implementation | 🟢 Low | `spec/16-user-management/` exists | 🔲 Not scoped |

---

## Next Task Selection

> **For handoff to other AI models:** Pick the next task based on priority and unblocked status.

**Recommended implementation order:**

1. **Phase A: Deploy & Verify** — 🔴 Critical but blocked on user running `.\run.ps1 -uas`
2. **H-2: Publish Analytics** — build charts/trends on existing publish history data; needs spec scoping first
3. **H-3: User Management** — largest scope; spec exists at `spec/16-user-management/`; adds auth + roles to Go backend + React

**Blocked (needs user action):**
- Phase A: All deployment tasks — user must run PowerShell scripts

---

*Master plan for AI handoff. Pick from "Next Task Selection" above. Read specs before implementing.*
