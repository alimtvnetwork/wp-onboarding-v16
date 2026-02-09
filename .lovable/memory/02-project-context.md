# Project Context

> **Location:** `.lovable/memory/02-project-context.md`  
> **Updated:** 2026-02-09

---

## Repository Overview

This repository contains **three projects**:

| Project | Location | Status | Technology |
|---------|----------|--------|------------|
| WP Plugin Publish | `backend/`, `src/`, `spec/wp-plugin-publish/` | 🔄 Active | Go + React |
| Plugins Onboard | `plugins-onboard/` | ✅ Complete | WordPress PHP |
| Spec Builder v3 | (Referenced only) | 📝 Dormant | Mentioned in CONTEXT-FOR-AI.md |

---

## Active Project: WP Plugin Publish

**Purpose:** Local development tool for WordPress plugin developers — manages plugin publishing, syncing, monitoring, and diagnostics across multiple WordPress sites.

**Architecture:**
- **Frontend:** React + TypeScript + Tailwind (localhost:3000)
- **Backend:** Go 1.21+ (localhost:8080)
- **Database:** SQLite with Split DB Architecture
- **Config:** JSON seed + SQLite runtime
- **Companion WP Plugin:** `wp-plugins/riseup-asia-uploader/` (PHP)

---

## Implementation Status

### ✅ All Core Features Complete

| Category | Features Implemented |
|----------|---------------------|
| **Plugin Management** | CRUD, scanning, mappings, remote viewer, file browser |
| **Publishing** | Quick publish, batch parallel, queue system, scheduled, rollback, retry, diff preview |
| **Site Management** | CRUD, connection testing, health monitoring, multi-site orchestration |
| **Sync** | Local/remote comparison, MD5 hashing, hybrid file watcher |
| **Logging & Diagnostics** | Session-based logging, global error modal (8+ tabs), execution logger, stack traces |
| **Version Tracking** | Remote version badges, auto-update with 301 redirect support |
| **History** | Publish history dashboard, site health dashboard |
| **E2E Testing** | 20 test cases, Go runner, React UI, real HTTP-based tests |
| **Git & Build** | Git pull, build commands |

### 🔄 Current: DRY Refactoring (6/10 phases done)

| Phase | Status | Description |
|-------|--------|-------------|
| 1–3 | ✅ | Go backend: uploader dedup, envelope parsing, diagnostic context |
| 4 | ✅ | Frontend: `buildCapturedError` + `commitErrorToStore` in errorStore |
| 5 | ✅ | Frontend: `src/lib/api/` modular split (types, envelope, client, methods, barrel) |
| 6 | ✅ | Frontend: `useApiQuery` / `useApiQueryPaginated` factory hooks |
| 7–8 | 📋 | PHP: Snapshot factory, logger context consolidation |
| 9 | 📋 | Frontend: GlobalErrorModal decomposition (~2,164 lines) |
| 10 | 📋 | Cross-stack: Envelope JSON schema alignment |

---

## Key Architecture Patterns

| Pattern | Details |
|---------|---------|
| **Universal Response Envelope** | PascalCase keys (Go/PHP compat), Status/Attributes/Results/Navigation/Errors/MethodsStack |
| **Modular API Client** | `src/lib/api/` — types, envelope, client, methods, barrel index |
| **Error Store** | `buildCapturedError()` factory + `commitErrorToStore()` helper |
| **API Query Factory** | `useApiQuery` / `useApiQueryPaginated` wrapping React Query |
| **WordPress Endpoint Mapping** | Centralized `endpoint_map.go` with `WPEndpointName` enum |
| **apperror Package** | `apperror.Wrap(err, code, message)` with `.WithContext()` — no `fmt.Errorf` |
| **PHP Standards** | `CODING-GUIDELINES.md` — 11 mandatory rules, `RiseupPathUtils`, `RiseupBooleanHelpers`, `RiseupDependencyLoader` |

---

## Key Files

| Type | Location |
|------|----------|
| Master Spec | `spec/wp-plugin-publish/00-overview.md` |
| DRY Plan | `.lovable/plan.md` |
| Active Phases | `.lovable/plan/active.md` |
| Completed Phases | `.lovable/plan/completed-phases-1-14.md`, `completed-phases-33-40.md`, `completed/01-dry-refactoring-phases-1-6.md` |
| Suggestions | `.lovable/memory/suggestions/01-suggestions-tracker.md` |
| API Client | `src/lib/api/` (types, envelope, client, methods, index) |
| Error Store | `src/stores/errorStore.ts` |
| API Query Factory | `src/hooks/useApiQuery.ts` |
| Go Backend | `backend/` |
| WP Plugin | `wp-plugins/riseup-asia-uploader/` |

---

## Next Steps for New AI

1. **Read `.lovable/plan.md`** — DRY refactoring phases 7–10 pending
2. **Read `.lovable/plan/active.md`** — Current status overview
3. **Check suggestions** in `01-suggestions-tracker.md` — 7 open items
4. **Ask user** which phase to implement next (recommended: Phase 7 or 9)

---

## Secondary Project: Plugins Onboard

**Location:** `plugins-onboard/`  
**Status:** Complete (v1.0.5)  
**Purpose:** WordPress plugin providing REST API for remote plugin management (OAuth 2.0, JWT, ephemeral tokens, backups, audit logging)

See `memory/PRD.md` for full specification.

---

*Update when implementation progresses.*
