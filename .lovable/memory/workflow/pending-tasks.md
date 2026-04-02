# Pending Tasks for Future AI Sessions

> **Updated:** 2026-04-02

---

## High Priority

### 1. Deploy v2.30.0+ to All Remote Sites
- **Status:** Blocked — needs user to run `.\run.ps1 -uas` from their local machine
- **Context:** Remote sites running older versions. Latest code includes bootstrap deploy rewrite, double-envelope fix, SiteCard redesign, and plugin_slug fix
- **After deploy:** Run `.\run.ps1 -am` then `.\run.ps1 -cla` then `.\run.ps1 -check` to verify

### 2. Verify EnvelopeBuilder Fallback Fix
- **Status:** Code fix applied, not yet deployed
- **File:** `wp-plugins/qupload/includes/Traits/Core/ResponseTrait.php`
- **Test:** After deploying, upload any plugin via QUpload and confirm no 500 errors

### 3. Redeploy to Fix plugin_slug Error (v2.30.0)
- **Status:** Blocked — needs user to deploy
- **Spec:** `spec/02-app-issues/36-plugin-slug-still-missing-v2.30.md`

---

## Medium Priority (Implementable)

| # | Task | Suggestion | Status |
|---|------|-----------|--------|
| 4 | Backup History Visualization UI | S-047 | ✅ Done — timeline integrated into CloudStorage page |
| 5 | Chunk reassembly manifest validation | S-048 | ✅ Done |
| 6 | Verbose -check mode (HEAD probing) | S-051 | ✅ Done |
| 7 | Auto-invalidate cached ZIP on source change | S-052 | ✅ Done |
| 8 | Google Drive folder rotation (Phase 5F) | S-046 | Spec written — `spec/10-features/S-046-google-drive-folder-rotation.md` |

---

## Low Priority

| # | Task | Suggestion | Status |
|---|------|-----------|--------|
| 9 | QUpload settings.json | S-049 | ✅ Done — settings.json exists with logging config |
| 10 | /logs/rotation-status endpoint | S-050 | ✅ Done — PHP + Go + verbose check all implemented |
| 11 | Licensing admin dashboard | S-053 | Spec written — `spec/10-features/S-053-licensing-admin-dashboard.md` |
| 12 | Publish analytics dashboard | S-054 | Spec written — `spec/10-features/S-054-publish-analytics-dashboard.md` |
| 13 | User Management — route/sidebar wiring | — | ✅ Done — route + sidebar + header + page all wired |

---

## Recently Completed (2026-03-22 — 2026-04-02)

| Task | Date | Reference |
|------|------|-----------|
| Backup History timeline integration | 2026-04-02 | CloudStorage page + CloudStorageBackupTimeline |
| Specs for S-046, S-053, S-054 | 2026-04-02 | `spec/10-features/` |
| Items #5-7, #9-10, #13 confirmed done | 2026-04-02 | Audit |
| Progress Bar Normalization | 2026-04-01 | ProgressRanges.go |
| Phase J: Bootstrap Deploy Pipeline Rewrite (7 tasks) | 2026-03-22 | `plan.md` Phase J |
| Phase I: Dashboard UX & Data Pipeline Fix | 2026-03-22 | Double-envelope, SiteCard redesign |
| Phase H-2: Publish Analytics | 2026-03-22 | Complete |
| Phase H-3: User Management (scaffold) | 2026-03-22 | PHP+Go+React scaffolded |
| PowerShell -d skip PHP propagation | 2026-03-22 | spec/02-app-issues/37 |

---

*Update this file when task status changes. Cross-reference with `plan.md` (repo root).*
