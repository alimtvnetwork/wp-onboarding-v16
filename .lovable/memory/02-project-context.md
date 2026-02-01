# Project Context

> **Location:** `.lovable/memory/02-project-context.md`  
> **Updated:** 2026-02-01

---

## Active Project: WP Plugin Publish

**Purpose:** Local development tool for WordPress plugin developers.

**Architecture:**
- **Frontend:** React + TypeScript + Tailwind (localhost:3000)
- **Backend:** Go 1.21+ (localhost:8080)
- **Database:** SQLite
- **Config:** JSON seed + SQLite runtime

---

## Implementation Status

### Completed
- ✅ All 21 spec documents (Phases 1-4)
- ✅ Go backend scaffold (main.go, services, API, WebSocket)
- ✅ React frontend scaffold (routing, layout, pages, hooks)
- ✅ Database migrations
- ✅ API client and WebSocket client

### Pending (Phase 5)
- 📝 Full service implementations (CRUD, file watching, sync, publish)
- 📝 Form components (SiteForm, PluginForm)
- 📝 Integration testing
- 📝 End-to-end testing

---

## Key Files

| Type | Location |
|------|----------|
| Spec docs | `spec/wp-plugin-publish/` |
| Go backend | `backend/` |
| React frontend | `src/` |
| Plan | `.lovable/plan.md` |

---

*Update when implementation progresses.*
