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
- **Companion WP Plugin:** `wp-plugins/riseup-asia-uploader/` (PHP, current: v1.36.1)

---

## Implementation Status

### ✅ All Core Features & Refactoring Complete

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
| **DRY Refactoring** | 10/10 phases complete (Go, React, PHP, cross-stack) |
| **Pre-flight Guard** | S-010: Plugin exists check (PHP API + Go proxy + React guard) |
| **PHP Resilience** | Circular dependency fix, bootstrapping guards, native fallbacks |

### ✅ DRY Refactoring (10/10 phases complete)

| Phase | Status | Description |
|-------|--------|-------------|
| 1–3 | ✅ | Go backend: uploader dedup, envelope parsing, diagnostic context |
| 4 | ✅ | Frontend: `buildCapturedError` + `commitErrorToStore` in errorStore |
| 5 | ✅ | Frontend: `src/lib/api/` modular split (types, envelope, client, methods, barrel) |
| 6 | ✅ | Frontend: `useApiQuery` / `useApiQueryPaginated` factory hooks |
| 7 | ✅ | PHP: `RiseupSnapshotFactory` centralized snapshot class management |
| 8 | ✅ | PHP: Logger context consolidation (`prepare_context`) |
| 9 | ✅ | Frontend: GlobalErrorModal decomposition into 7 sub-files |
| 10 | ✅ | Cross-stack: Envelope JSON schema (v1.0.0) alignment |

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
| **PHP Bootstrapping Guards** | Static `$bootstrapping` flags prevent circular dependencies during init |

---

## Key Files

| Type | Location |
|------|----------|
| Master Spec | `spec/wp-plugin-publish/00-overview.md` |
| DRY Plan | `.lovable/plan.md` |
| Active Phases | `.lovable/plan/active.md` |
| Completed Phases | `.lovable/plan/completed-phases-1-14.md`, `completed-phases-33-40.md`, `completed/` |
| Suggestions | `.lovable/memory/suggestions/01-suggestions-tracker.md` |
| API Client | `src/lib/api/` (types, envelope, client, methods, index) |
| Error Store | `src/stores/errorStore.ts` |
| API Query Factory | `src/hooks/useApiQuery.ts` |
| Go Backend | `backend/` |
| WP Plugin | `wp-plugins/riseup-asia-uploader/` |

---

## Next Steps for New AI

1. **Read `.lovable/plan.md`** — All phases complete
2. **Read `.lovable/plan/active.md`** — Open suggestions and questions
3. **Check suggestions** in `01-suggestions-tracker.md` — 2 open items (S-001, S-004)
4. **Ask user** what to implement next

---

## Secondary Project: Plugins Onboard

**Location:** `plugins-onboard/`  
**Status:** Complete (v1.0.5)  
**Purpose:** WordPress plugin providing REST API for remote plugin management (OAuth 2.0, JWT, ephemeral tokens, backups, audit logging)

See `memory/PRD.md` for full specification.

---

*Update when implementation progresses.*
