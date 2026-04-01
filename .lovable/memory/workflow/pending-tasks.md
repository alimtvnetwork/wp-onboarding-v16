# Pending Tasks for Future AI Sessions

> **Updated:** 2026-04-01

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

## Medium Priority (Implementable — Phase K)

| # | Task | Suggestion | Status |
|---|------|-----------|--------|
| 4 | Backup History Visualization UI | S-047 | Open |
| 5 | Chunk reassembly manifest validation | S-048 | Done |
| 6 | Verbose -check mode (HEAD probing) | S-051 | Open |
| 7 | Auto-invalidate cached ZIP on source change | S-052 | Done |

---

## Low Priority

| # | Task | Suggestion | Status |
|---|------|-----------|--------|
| 8 | Google Drive folder rotation (Phase 5F) | S-046 | Open |
| 9 | QUpload settings.json | S-049 | Open |
| 10 | /logs/rotation-status endpoint | S-050 | Open |
| 11 | Licensing admin dashboard (needs spec) | S-053 | Open — **write spec first** |
| 12 | Publish analytics dashboard (needs spec) | S-054 | Open — **write spec first** |
| 13 | User Management — finish route/sidebar wiring | — | Scaffolded, needs integration |

---

## Recently Completed (2026-03-22 — 2026-04-01)

| Task | Date | Reference |
|------|------|-----------|
| Phase J: Bootstrap Deploy Pipeline Rewrite (7 tasks) | 2026-03-22 | `plan.md` Phase J |
| Phase I: Dashboard UX & Data Pipeline Fix | 2026-03-22 | Double-envelope, SiteCard redesign |
| Phase H-2: Publish Analytics | 2026-03-22 | Complete |
| Phase H-3: User Management (scaffold) | 2026-03-22 | PHP+Go+React scaffolded |
| PowerShell -d skip PHP propagation | 2026-03-22 | spec/02-app-issues/37 |

---

*Update this file when task status changes. Cross-reference with `plan.md` (repo root).*
