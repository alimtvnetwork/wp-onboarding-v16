# Project Context

> **Location:** `.lovable/memory/02-project-context.md`  
> **Updated:** 2026-02-01

---

## Active Project

### WP Plugin Publish

**Purpose:** Local development tool for WordPress plugin developers to manage and publish plugins from local directories to remote WordPress sites.

**Architecture:**
- **Frontend:** React + TypeScript + Tailwind (localhost:3000)
- **Backend:** Go 1.21+ (localhost:8080)
- **Database:** SQLite (runtime data store)
- **Config:** JSON (seeding only, versioned)

**Core Features:**
1. **Multi-Site Management** — Connect to multiple WordPress installations via Application Password
2. **Multi-Plugin Management** — Register and manage multiple local plugin directories
3. **File Watcher** — Real-time detection of local file changes (fsnotify)
4. **Sync Detection** — Compare local vs remote plugin files
5. **Publishing Modes:**
   - Single file updates (faster for dev)
   - Full plugin zip upload with auto-activation
6. **Backup & Rollback** — Download remote plugin before updating
7. **Error Console** — Structured errors with copy-to-clipboard for AI debugging

**Spec Location:** `spec/wp-plugin-publish/`

**Key Documents:**
| # | Document | Description |
|---|----------|-------------|
| 00 | Overview | Architecture, features, document index |
| 01-14 | Backend | Go project structure, database, services, error handling |
| 20-25 | Frontend | React UI components and pages |
| 66 | Shared Constants | SSOT for error codes, enums, status values |
| 99 | Consistency Report | Cross-reference validation |

---

## Technical Decisions

### Config Strategy
- JSON file used for **seeding only** (version-controlled)
- SQLite is the **runtime source of truth**
- Version-based seeding: if `config.version > db.seed_version`, seed new data
- Existing records not overwritten (matched by unique keys)

### Error Management
- Structured errors with file:line:function
- Error codes categorized (E1xxx config, E2xxx db, E3xxx WP API, etc.)
- Stack traces captured for debugging
- Errors persisted to SQLite for UI display
- Copy-to-clipboard in AI-friendly format

### Authentication
- WordPress Application Password only (built-in since WP 5.6)
- Passwords encrypted with AES-256-GCM in SQLite
- Backend binds to localhost only (no external access)

---

*Update this file when project scope changes.*
