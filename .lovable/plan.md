# WP Plugin Publish - Development Roadmap

This document tracks the phased implementation of features for the WP Plugin Publish application.

---

## ✅ Phase 1: Site UI + Connection Persistence (COMPLETED)

**Status:** Done (v1.3.0)

### Completed Tasks
1. ✅ Tabbed Site dialog (Basic + Connection tabs) to reduce vertical scrolling
2. ✅ Save button always visible (test optional)
3. ✅ Connection status persisted to database after successful test
4. ✅ Green "Connected" badge on sites that passed testing
5. ✅ Retest button for manual re-verification
6. ✅ Extracted AddSiteDialog and EditSiteDialog as reusable components

---

## ✅ Phase 2: Plugin-Site Relationships (COMPLETED)

**Status:** Done (v1.4.0)

### Completed Tasks
1. ✅ Backend: Added `PUT /plugins/{id}/mappings` for bulk mapping updates
2. ✅ Backend: Added `GET /sites/{id}/mappings` to fetch plugins linked to a site
3. ✅ UI: Plugin mapping dialog with remote slug configuration
4. ✅ UI: Plugin cards show linked sites as badges
5. ✅ UI: Site cards show linked plugins as badges
6. ✅ Extracted SiteCard component with self-contained connection testing

---

## ✅ Phase 2.5: Plugin Sync & Publish UI (COMPLETED)

**Status:** Done (v1.5.0)

### Completed Tasks
1. ✅ Fixed API endpoint mismatch (scan, git pull endpoints)
2. ✅ EditSiteDialog now has 3 tabs: Basic, Connection, Plugins
3. ✅ Retest Connection button in EditSiteDialog
4. ✅ Plugin cards show Sync button (for mapped plugins)
5. ✅ Plugin cards show Publish button with site selection dialog
6. ✅ Can manage plugin-site relationships from EditSiteDialog

---

## ✅ Phase 3: Categories + Filtering + Publish Progress (COMPLETED)

**Status:** Done (v1.6.0)

### Completed Tasks
1. ✅ Real-time publish progress dialog with WebSocket updates (backup, package, upload, activate stages)
2. ✅ Category system for sites and plugins (Production, Staging, Development + custom categories)
3. ✅ Category selector with add custom category popover
4. ✅ Category filter bar on Sites page
5. ✅ Category filter bar on Plugins page
6. ✅ Category badges displayed on site and plugin cards
7. ✅ Consolidated Sync page into Plugins page (removed standalone Sync tab)
8. ✅ Updated API types to include `category` field for Site and Plugin

---

## ✅ Phase 4: Dashboard & Category Persistence (COMPLETED)

**Status:** Done (v1.7.0)

### Completed Tasks
1. ✅ Dashboard quick actions menu with shortcuts to Add Site, Register Plugin, Logs, Settings
2. ✅ Recent activity section on Dashboard showing latest sites and plugins
3. ✅ Stats cards on Dashboard now link to relevant pages
4. ✅ Backend: Added `category` column to Sites and Plugins tables (migration v3)
5. ✅ Backend: Updated Site/Plugin services to persist categories (SQL queries updated)
6. ✅ Backend: WebSocket emit for publish progress stages (already implemented in publish service)

---

## 📋 Phase 5: Advanced Features (FUTURE)

**Status:** Future

### Potential Features
- Bulk plugin operations (enable/disable/sync multiple)
- Git integration (commit and push from UI)
- Scheduled sync/publish jobs
- Plugin version history
- Rollback functionality
- Email notifications for publish events
- Test article publish feature (create draft post to verify connectivity)

---

## Version History

| Version | Date | Description |
|---------|------|-------------|
| 1.7.0 | 2026-02-04 | Phase 4: Dashboard quick actions, category persistence |
| 1.6.0 | 2026-02-04 | Phase 3: Categories, filtering, publish progress, consolidated Sync page |
| 1.5.0 | 2026-02-04 | Phase 2.5: Sync/Publish UI, EditSiteDialog plugins tab, API fixes |
| 1.4.0 | 2026-02-04 | Phase 2 complete: Plugin-Site relationships |
| 1.3.0 | 2026-02-04 | Phase 1 complete: Site UI + Connection persistence |
| 1.2.1 | 2026-02-04 | API connectivity fix + enhanced diagnostics |
| 1.2.0 | 2026-02-04 | Diagnostics & About Panel |
| 1.1.0 | 2026-02-04 | Version tracking + What's New popup |
| 1.0.0 | 2026-02-04 | Initial release |
