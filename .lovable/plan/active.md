# Active & Future Phases

**Updated: 2026-03-22**

---

## Current Sprint: Dashboard UX & Data Pipeline Fix 🔄

### 🔴 Critical — Double Envelope Fix

The Go backend double-wraps PHP envelope responses, causing Health, Logs, and Settings panels to show empty data. See `spec/02-app-issues/34-double-envelope-wrapping-health-logs.md`.

| # | Task | Status |
|---|------|--------|
| 1 | Go: Add PHP envelope unwrapper helper | Todo |
| 2 | Go: Apply to health, settings, logs service methods | Todo |
| 3 | React: Verify Health panel renders real data | Todo |
| 4 | React: Verify Logs panel renders real data | Todo |

### 🟡 High — SiteCard Button Redesign

See `spec/02-app-issues/35-sitecard-button-layout-redesign.md`.

| # | Task | Status |
|---|------|--------|
| 5 | Move Edit button + overflow menu (⋯) to card header | Todo |
| 6 | Move Delete, API, Activity, Snapshots, Users to overflow | Todo |
| 7 | Bottom bar: Plugins, Health, Logs, Settings, Deploy only | Todo |
| 8 | Card body click → edit | Todo |

### 🟢 Medium — PowerShell & Deployment

| # | Task | Spec | Status |
|---|------|------|--------|
| 9 | -d: Skip PHP propagation when no wp-plugins/ changes | `spec/02-app-issues/37` | Todo |
| 10 | Redeploy to fix plugin_slug error (v2.30.0) | `spec/02-app-issues/36` | Blocked (user) |

---

## Previous Status: H-3 User Management 🔄

PHP layer, Go proxy handlers, and React dashboard scaffolded — needs route/sidebar wiring.

---

## Recently Completed

- Site Settings panel (PHP + Go + React)
- Site Health Summary panel (PHP + Go + React) — data pipeline broken (see above)
- Remote Logs panel — data pipeline broken (see above)
- PowerShell `-ss` (site settings) command
- PowerShell `-d` (deploy) command
- Phase 7E: Cloud Storage Providers ✅
- H-2: Publish Analytics ✅

---

## Pending Tasks (from prior sprints)

| # | Task | Priority | Status |
|---|------|----------|--------|
| 1 | ORM PDO Fix — Redeploy | 🔴 Critical | Blocked (deployment) |
| 2 | H-3: User Management wiring | 🟡 Medium | In progress |

---

## Resolved Design Decisions ✅

| # | Question | Decision |
|---|----------|----------|
| 1 | Remote Plugin Backups | WP site only, 5-backup retention |
| 2 | Bulk Quick Publish | Sequential server-side |
| 3 | True Diff Comparison | Remote MD5 hashes, 5-min TTL |
| 4 | Licensing | Custom Go server, HMAC-SHA256 |
| 5 | Cloud Storage | GitHub + GitLab + Google Drive |
| 6 | Site Settings approach | WordPress Options API |

---

*Master plan details in `plan.md` (repo root). Specs in `spec/02-app-issues/`.*
