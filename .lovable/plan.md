# Master Roadmap & Backlog

> Updated: 2026-03-17

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

### Phase B: Backend Integration (🟡 Medium)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| B-1 | Wire `cloud_upload` stage into Go pipeline | 🟡 Medium | None | ✅ Done |
| B-2 | Bump plugin versions to 2.15.0+ | 🟢 Low | B-1 | ✅ Done |

**Expected outputs:** `ServicePublishPipeline.go` emits WS events for cloud upload; version constants updated
**Acceptance criteria:** Cloud upload progress visible in React dashboard when cloud storage accounts selected

---

### ✅ Phase C: All-Layer HTTP Method Fix
- QUpload Activate → PUT across PHP, Go, Frontend, specs, and memory docs
- 6 error-context strings fixed in RemotePluginsPanel.tsx

---

### Phase D: UI & UX (🟡 Medium)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| D-1 | QUpload Admin UI Uplift | 🟡 Medium | None | ✅ Done |
| D-2 | Backup History Visualization (Phase 5E) | 🟡 Medium | None | ✅ Done |

**D-1 completed:** Version badge, admin-shared.css animations (8 keyframes), gradient buttons, method badges, card animations — all already implemented
**D-2 scope:** Timeline UI in Cloud Storage dashboard

**Expected outputs:** Backup history timeline component
**Acceptance criteria:** Timeline shows backup history

---

### Phase E: Git Backup Strategy (🟡 Medium — 6 sub-phases)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| E-1 | Phase 5A: Repo selection + branch management | 🟡 Medium | None | ✅ Done |
| E-2 | Phase 5B: Full + incremental backup engine | 🟡 Medium | E-1 | ✅ Done |
| E-3 | Phase 5C: Automated scheduling (WP-Cron) | 🟡 Medium | E-2 | ✅ Done |
| E-4 | Phase 5D: Git clone restore | 🟡 Medium | E-2 | ✅ Done |
| E-5 | Phase 5E: Backup history visualization | 🟡 Medium | E-2 | ✅ Done |
| E-6 | Phase 5F: Google Drive folder adaptation | 🟢 Low | E-2 | ✅ Done |

**Spec files:** `spec/17-cloud-storage-providers/09-git-backup-strategy.md`, `10-git-backup-workflow-v2.md`
**Expected outputs:** DB migrations v19-v20; BackupHistory table; PHP traits; React timeline UI; WP-Cron jobs
**Acceptance criteria:** Automated backups to Git providers on schedule; restore from any backup point

---

### Phase F: Configuration & Monitoring (🟢 Low)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| F-1 | Create `settings.json` for QUpload | 🟢 Low | None | ✅ Done |
| F-2 | Add `/logs/rotation-status` endpoint | 🟢 Low | F-1 | ✅ Done |
| F-3 | Verbose `-check` mode (HEAD requests) | 🟢 Low | None | ✅ Done |
| F-4 | Auto-invalidate cached ZIP on source change | 🟡 Medium | None | ✅ Done |

**Expected outputs:** `settings.json` with logging config; rotation status API; enhanced diagnostics
**Acceptance criteria:** Configurable without code changes; remote monitoring possible

---

### Phase G: Type Safety (🟢 Low)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| G-1 | Audit remaining `interface{}` scope | 🟢 Low | None | ✅ Done |

**Expected outputs:** List of files/counts; migration to `any` or typed generics
**Acceptance criteria:** Zero `interface{}` outside test files

---

### Phase H: Future Features (Not Yet Scoped)

| # | Task | Priority | Dependencies | Status |
|---|------|----------|------------|--------|
| H-1 | Admin dashboard for licensing server | 🟢 Low | Spec needed | 🔲 Not scoped |
| H-2 | Publish analytics / history reporting | 🟢 Low | Spec needed | 🔲 Not scoped |
| H-3 | User Management implementation | 🟢 Low | `spec/16-user-management/` exists | 🔲 Not scoped |

---

## Next Task Selection

> **For handoff to other AI models:** Pick the next task based on priority and unblocked status.

**Recommended implementation order:**

1. **Phase C: QUpload Activate → PUT** — well-specified all-layer fix, no blockers
2. **Phase B-1: Cloud Storage Go pipeline** — wire `cloud_upload` stage
3. **Phase D-1: QUpload UI Uplift** — visual improvement, no blockers
4. **Phase E-1: Git Backup Phase 5A** — repo selection + branch management
5. **Phase F-4: Auto-invalidate cached ZIP** — developer productivity improvement
6. **Phase D-2: Backup History Visualization** — timeline UI
7. **Phase F-1–F-3: Configuration & Monitoring** — low priority quality-of-life

**Blocked (needs user action):**
- Phase A: All deployment tasks — user must run PowerShell scripts

---

## Open Suggestions Cross-Reference

| Suggestion | Maps To |
|------------|---------|
| S-046 | Phase E-6 (Google Drive folder rotation) |
| S-047 | Phase D-2 / E-5 (Backup history visualization) |
| S-048 | Phase B-1 (Go backend validation) |
| S-049 | Phase F-1 (QUpload settings.json) |
| S-050 | Phase F-2 (Rotation status endpoint) |
| S-051 | Phase F-3 (Verbose `-check`) |
| S-052 | Phase F-4 (Auto-invalidate cached ZIP) |
| S-053 | Phase H-1 (Licensing dashboard) |
| S-054 | Phase H-2 (Publish analytics) |

---

*Master plan for AI handoff. Pick from "Next Task Selection" above. Read specs before implementing.*
