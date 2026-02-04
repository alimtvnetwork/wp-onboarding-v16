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

## 📋 Phase 3: Categories + Filtering (PLANNED)

**Status:** Planned

### Goals
- Add categories to both sites and plugins
- Enable filtering by category, name, and relationships

### Tasks
1. [ ] DB: Add `category` column to Sites table (migration)
2. [ ] DB: Add `category` column to Plugins table (migration)
3. [ ] Backend: Update Site/Plugin services to handle categories
4. [ ] UI: Add category field to Add/Edit Site dialogs
5. [ ] UI: Add category field to Add/Edit Plugin dialogs
6. [ ] UI: Add filter bar on Sites page (by category)
7. [ ] UI: Add filter bar on Plugins page (by site, category, name)
8. [ ] Predefined categories: Production, Staging, Development (sites); Core, Premium, Custom (plugins)
9. [ ] Custom category support: Allow users to add their own categories

---

## 📋 Phase 4: Publish Workflow (PLANNED)

**Status:** Planned

### Goals
- Add Publish button to plugins
- Enable test article publishing
- Dashboard menu for quick actions

### Tasks
1. [ ] UI: Add "Publish" button on plugin cards
2. [ ] UI: Publish confirmation dialog with site selection
3. [ ] UI: Publish progress indicator with real-time updates
4. [ ] Backend: Implement publish workflow (backup → package → upload → activate)
5. [ ] UI: Test article publish feature (create draft post to verify connectivity)
6. [ ] UI: Dashboard quick actions menu
7. [ ] UI: Recent activity feed on Dashboard

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

---

## Version History

| Version | Date | Description |
|---------|------|-------------|
| 1.4.0 | 2026-02-04 | Phase 2 complete: Plugin-Site relationships |
| 1.3.0 | 2026-02-04 | Phase 1 complete: Site UI + Connection persistence |
| 1.2.1 | 2026-02-04 | API connectivity fix + enhanced diagnostics |
| 1.2.0 | 2026-02-04 | Diagnostics & About Panel |
| 1.1.0 | 2026-02-04 | Version tracking + What's New popup |
| 1.0.0 | 2026-02-04 | Initial release |
