# AI Handoff — Complete Project Context & Memory

> **Generated:** 2026-04-01
> **Purpose:** Single-file knowledge transfer for any AI editor picking up this repository. Read this entire document before making changes.
> **Repository:** WP Plugin Publish + Companion WordPress Plugins

---

## Table of Contents

1. [Repository Overview](#1-repository-overview)
2. [Architecture & Technology Stack](#2-architecture--technology-stack)
3. [Project Status & Roadmap](#3-project-status--roadmap)
4. [Coding Standards (All Languages)](#4-coding-standards-all-languages)
5. [PHP-Specific Rules (Critical)](#5-php-specific-rules-critical)
6. [Go Backend Standards](#6-go-backend-standards)
7. [TypeScript/React Standards](#7-typescriptreact-standards)
8. [Known Issues (Active)](#8-known-issues-active)
9. [Issues Fixed (Learning History)](#9-issues-fixed-learning-history)
10. [Key Architectural Patterns](#10-key-architectural-patterns)
11. [WordPress Plugin Architecture](#11-wordpress-plugin-architecture)
12. [PowerShell Automation](#12-powershell-automation)
13. [Recent Conversation & Changes (April 2026)](#13-recent-conversation--changes-april-2026)
14. [Pending Tasks](#14-pending-tasks)
15. [Suggestions Tracker](#15-suggestions-tracker)
16. [Reliability Risk Report](#16-reliability-risk-report)
17. [File & Folder Map](#17-file--folder-map)
18. [Session Handoff Checklist](#18-session-handoff-checklist)
19. [Anti-Patterns — Do NOT Repeat](#19-anti-patterns--do-not-repeat)

---

## 1. Repository Overview

This repository contains **three projects**:

| Project | Location | Status | Technology |
|---------|----------|--------|------------|
| **WP Plugin Publish** | `backend/`, `src/` | 🔄 Active | Go 1.21+ + React 18 + TypeScript |
| **Plugins Onboard** | `plugins-onboard/` | ✅ Complete (v1.0.5) | WordPress PHP |
| **Companion WP Plugins** | `wp-plugins/` | 🔄 Active | PHP 8.1+ |

### WP Plugin Publish (Main Project)

A **local development tool** for WordPress plugin developers that manages plugin publishing, syncing, monitoring, and diagnostics across multiple WordPress sites.

- **Frontend:** React + TypeScript + Tailwind CSS (Vite, localhost:3000)
- **Backend:** Go 1.21+ (localhost:8080)
- **Database:** SQLite with Split DB Architecture
- **Config:** JSON seed + SQLite runtime
- **Companion WP Plugins:**
  - `wp-plugins/riseup-asia-uploader/` — Main deployment receiver (v1.36.1+)
  - `wp-plugins/qupload/` — Quick Upload plugin for simple deployments

### Plugins Onboard (Complete)

WordPress plugin providing REST API for remote plugin management (OAuth 2.0, JWT, ephemeral tokens, backups, audit logging). Located at `plugins-onboard/`. v1.0.5. No active development.

---

## 2. Architecture & Technology Stack

### Universal Response Envelope

All API responses (Go → React, PHP → Go) use a standardized envelope format:

```json
{
  "Status": "success|error",
  "Attributes": {},
  "Results": [],
  "Navigation": {},
  "Errors": [],
  "MethodsStack": []
}
```

- **PascalCase keys** — Go/PHP compatibility
- Schema: `envelope.schema.json` (v1.0.0)
- Go: `apperror.Wrap(err, code, message)` with `.WithContext()` — **never** `fmt.Errorf`
- React: `buildCapturedError()` factory + `commitErrorToStore()` helper

### API Client Architecture (React)

```
src/lib/api/
├── types.ts      # Type definitions
├── envelope.ts   # Envelope parsing
├── client.ts     # HTTP client
├── methods.ts    # API method implementations
└── index.ts      # Barrel export
```

- `useApiQuery` / `useApiQueryPaginated` — React Query factory hooks
- All URLs resolved via `resolveApiUrl()` from `src/lib/endpoints.ts`
- **Never hardcode** `localhost:8080`

### Error Handling (React)

- Global Error Modal with 8+ tabs
- Decomposed into 7 sub-files
- Every error must display: resolved request URL, API base, `VITE_API_URL` state
- Zustand error store with factory pattern

### Database (Go)

- SQLite with GORM
- Split DB architecture
- PascalCase SQL columns (`UserId`, `CreatedAt`)
- camelCase ORM properties
- Sequential migrations with schema versioning

---

## 3. Project Status & Roadmap

### ✅ All Core Features Complete

| Category | Features |
|----------|----------|
| Plugin Management | CRUD, scanning, mappings, remote viewer, file browser |
| Publishing | Quick publish, batch parallel, queue, scheduled, rollback, retry, diff preview |
| Site Management | CRUD, connection testing, health monitoring, multi-site orchestration |
| Sync | Local/remote comparison, MD5 hashing, hybrid file watcher |
| Logging & Diagnostics | Session-based logging, global error modal (8+ tabs), execution logger |
| Version Tracking | Remote version badges, auto-update with 301 redirect support |
| DRY Refactoring | 10/10 phases complete |
| Cloud Storage | GitHub + GitLab + Google Drive (3 phases complete) |
| Git Backup | 6 sub-phases (E-1 through E-6) complete |
| Bootstrap Deploy | Rewritten (Phase J) — ZIP once, parallel uploads, cross-upload |
| User Management | PHP + Go + React scaffolded |
| Publish Analytics | Complete |

### Completed Plan Phases

- Go Phase 4: Positive Logic & Boolean Standards
- Go Phase 5: Code Organization (all 247 files within 300-line limit)
- Go Phase 6: CI Lint Scripts
- Go Phase G: `any` Elimination (all 6 sub-phases)
- Phase 7A–7E: Remote backups, bulk publish, true diff, licensing, cloud storage
- Phase B: Backend integration (cloud_upload stage)
- Phase C: HTTP method fix (QUpload Activate → PUT)
- Phase D: UI/UX uplift
- Phase E: Git backup strategy (6 phases)
- Phase F: Configuration & monitoring
- Phase H: Licensing dashboard + analytics + user management
- Phase I: Dashboard UX & data pipeline fix
- Phase J: Bootstrap deploy pipeline rewrite (7 tasks, all done)

### Active / Blocked

| Task | Priority | Status |
|------|----------|--------|
| Deploy v2.17.0+ to all remote sites | 🔴 Critical | Blocked (user must run PowerShell) |
| Verify EnvelopeBuilder fallback fix | 🔴 Critical | Blocked (needs deploy) |
| Verify preflight check on remote | 🟡 Medium | Blocked (needs deploy) |
| Redeploy for plugin_slug fix (v2.30.0) | 🟡 High | Blocked (user must deploy) |
| ORM PDO Fix | 🔴 Critical | Open (pending fix) |

---

## 4. Coding Standards (All Languages)

### File Naming (ALL Languages — PascalCase)

| Language | Convention | Example |
|----------|-----------|---------|
| Go | `PascalCase.go` | `ClientApiCall.go` |
| PHP (namespaced) | `PascalCase.php` | `SnapshotManager.php` |
| PHP (WP non-namespaced) | `class-{kebab-case}.php` | `class-exam-manager.php` |
| TypeScript | `PascalCase.ts(x)` | `RemotePluginFileBrowser.tsx` |

> **Go package directories** remain `snake_case`. Go enum packages use lowercase `type` suffix (`httpmethodtype/`).

### Numbered File Prefix Pattern

All memory/spec files: `01-name-of-file.md` (two-digit prefix, hyphen-separated, lowercase).

### Security Requirements (WordPress)

- Always use nonces for forms
- Always check capabilities
- Always sanitize input, escape output
- Always use prepared statements with `$wpdb`

---

## 5. PHP-Specific Rules (Critical)

### 5.1 QUpload Syntax Validator Compatibility

**THE MOST IMPORTANT PHP RULE.** The QUpload plugin validates uploaded PHP files using `token_get_all($content, TOKEN_PARSE)` before activation. Certain valid PHP patterns cause false-positive syntax errors that **block plugin activation**.

#### Blocked Patterns

| Pattern | Error | Fix |
|---------|-------|-----|
| `is_array($var)` | `unexpected token "array"` | `gettype($var) === PhpNativeType::PhpArray->value` |
| `is_string($var)` | Similar token issue | `gettype($var) === PhpNativeType::PhpString->value` |
| `is_int($var)` | Similar token issue | `gettype($var) === PhpNativeType::PhpInteger->value` |
| `is_float($var)` | Similar token issue | `gettype($var) === PhpNativeType::PhpDouble->value` |
| `is_bool($var)` | Similar token issue | `gettype($var) === PhpNativeType::PhpBoolean->value` |
| `is_object($var)` | Similar token issue | `gettype($var) === PhpNativeType::PhpObject->value` |
| `is_null($var)` | Similar token issue | `gettype($var) === PhpNativeType::PhpNull->value` |
| `array()` constructor | `unexpected token "array"` | Use `[]` short array syntax |
| `= array()` default | `unexpected token "array"` | Use `= []` |

#### PhpNativeType Enum

Located at `includes/Enums/PhpNativeType.php` in the Riseup Asia Uploader plugin:

```php
namespace RiseupAsia\Enums;

enum PhpNativeType: string
{
    case PhpArray   = 'array';
    case PhpString  = 'string';
    case PhpInteger = 'integer';
    case PhpDouble  = 'double';
    case PhpBoolean = 'boolean';
    case PhpObject  = 'object';
    case PhpNull    = 'NULL';
}
```

#### Usage

```php
use RiseupAsia\Enums\PhpNativeType;

// WRONG — blocked by validator
$isBackupError = is_array($backupId);

// WRONG — magic string
$isBackupError = gettype($backupId) === 'array';

// CORRECT — typed constant
$isBackupError = gettype($backupId) === PhpNativeType::PhpArray->value;
```

#### Additional Legacy-Safe Rules (for critical deployment traits)

1. No parameter/return type hints (PHP 7.0 compatibility)
2. No trailing commas in function parameter lists
3. No nullable types (`?string`, `?int`)

### 5.2 Exception Handling

**Every catch block with `$e` MUST use one of these authorized patterns:**

| Context | Pattern |
|---------|---------|
| FileLogger available (plugin classes) | `$this->fileLogger->logException($e, 'Context')` (non-throwing) |
| Boot/routes/migrations (must re-throw) | `$this->fileLogger->logCriticalException($e, 'Context')` (throws internally) |
| No FileLogger (Riseup Asia) | `InitHelpers::errorLog($e, 'Context:')` or `::errorLogAndThrow($e, ...)` |
| No FileLogger (QUpload) | `ErrorLogHelper::log($e, 'Context:')` or `::logAndThrow($e, ...)` |
| No FileLogger (Plugins Onboard) | `OnboardErrorLog::log($e, 'Context:')` or `::logAndThrow($e, ...)` |
| Autoloaders only | Raw `error_log()` + manual `throw $e` (only 2 files in entire codebase) |
| Snapshot traits | `$this->logError($e, 'Context')` / `$this->logWarn($e, 'Context')` |
| Handler boundaries | `safeExecute()` handles everything internally |

**Rules:**
- Stack trace is the MOST important output — never omit it
- Throw from the helper, not the catch block
- Never manually write `error_log('msg: ' . $e->getMessage() . "\n" . $e->getTraceAsString())` (except autoloaders)
- Never log `$e->getMessage()` alone without the trace
- Reducing or omitting stack traces is a **critical defect**

### 5.3 Positive Polarity Ternaries

**No raw comparisons in ternary conditions.** Extract to a named boolean:

```php
// WRONG
return ($val !== false) ? $val : $default;

// CORRECT
$isFound = ($val !== false);
return $isFound ? $val : $default;
```

- Positive polarity only: `$isFound`, `$isReadSuccess`, `$hasResult`
- Never: `$isNotFound`, `$isMissing`

### 5.4 PHP Bootstrapping

- **Never call WordPress functions in constructors** — use lazy initialization
- Static `$bootstrapping` flags prevent circular dependencies during init
- Two-tier logging: file logger (always works) + DB logger (when available)
- `DateHelper` for all date formatting — never raw `date()`/`gmdate()`
- `ResponseKeyType` for all structured array keys — never raw strings

### 5.5 Boolean Functions

- **ALWAYS** use positive boolean functions with `is_` or `has_` prefix
- **NEVER** use negations (`!`) in if statements
- Use `BooleanHelpers` classes for all boolean checks
- Maximum 15 lines per function

---

## 6. Go Backend Standards

### Error Handling

- `apperror.Wrap(err, code, message)` with `.WithContext(key, value)` — **never** `fmt.Errorf` for errors leaving a service boundary

### Code Organization

- All Go files within **300-line limit**
- All functions within **15-line body limit**
- 247 files validated

### Type Safety

- `any` (previously `interface{}`) is **prohibited** in production Go except justified exceptions
- All 6 sub-phases of type safety migration complete

### Boolean Standards

- Positive logic only — 12 negative booleans renamed across 11 files, zero violations remain

### CI Lint Scripts

7 lint scripts enforced in CI + pre-commit hooks:
- Line length, function length, `any` usage, boolean polarity, etc.

### Naming

- `PascalCase.go` for file names
- `snake_case/` for package directories
- `lowercasetype/` for enum package directories
- WordPress endpoint mapping centralized in `endpoint_map.go` with `WPEndpointName` enum

---

## 7. TypeScript/React Standards

- Functional components with hooks
- Named exports preferred
- Semantic Tailwind tokens from design system — no hardcoded colors
- Components < 300 lines
- CSS variables for all colors
- All URLs via `resolveApiUrl()` / `resolveWsUrl()` — never hardcode

### File Organization

```
src/
├── components/    # Reusable UI components
├── pages/         # Route pages
├── hooks/         # Custom React hooks (useApiQuery, etc.)
├── lib/           # Utilities (api/, endpoints.ts)
├── stores/        # Zustand stores (errorStore.ts)
└── types/         # TypeScript type definitions
```

---

## 8. Known Issues (Active)

| # | Issue | Category | Status |
|---|-------|----------|--------|
| 003 | ORM PDO class not found | Database | Open (pending fix) |
| 004 | QUpload activate PUT not POST | API Design | ✅ Resolved |
| 005 | Log rotation missing | Logging | ✅ Resolved (implemented in FileLogger) |
| 006 | EnvelopeBuilder class not found on self-update | Self-Update | ✅ Fixed (`class_exists()` + fallback) |
| 007 | Upload 404 rest_no_route | Deployment | Root cause: version mismatch → deploy v2.17.0+ |
| 008 | `-check` command | Enhancement | ✅ Implemented |

---

## 9. Issues Fixed (Learning History)

15 documented issue write-ups in `.lovable/memory/issues-fixed/`:

| # | Issue | Category |
|---|-------|----------|
| 01 | pnpm PnP module resolution failures | Build/Dependencies |
| 02 | SPA static file 404 errors | Backend/Routing |
| 03 | WebSocket upgrade failures in middleware | Backend/WebSocket |
| 04 | Global error modal not capturing API failures | Frontend/UX |
| 05 | pnpm v10 ignored build scripts / PnP ESM | Build/Dependencies |
| 06 | SQLite datetime scanning issues | Backend/Database |
| 07 | Null-check, error source reporting | Frontend/UX |
| 08 | Malformed version.json causes app crash | Tooling/Config |
| 09 | NULL datetime crash in publish service | Backend/Publish |
| 10 | ZIP finalization race condition | Backend/Publish |
| 11 | Activation endpoint 404 mismatch | Backend/Publish |
| 12 | PHP circular dependency during bootstrap | WordPress/PHP |
| 13 | Go `buildWPClient` undefined method | Backend/Go |
| 14 | Retry/debounce/dedup anti-patterns | Frontend/Reliability |
| 15 | Deactivate plugin 404 | Backend/WordPress |

Additional issues documented in `spec/02-app-issues/` (31 write-ups with root cause + prevention).

### Missing Stack Traces Fix (Issue #001 — 8 Phases)

The most extensive fix in the project: **every** catch block across all 3 WordPress plugins was audited across 8 phases to ensure stack traces are never lost. ~700+ catch blocks in Riseup Asia alone. See `.lovable/memory/issues/001-missing-stack-traces-in-error-log.md` for the full 8-phase breakdown.

---

## 10. Key Architectural Patterns

| Pattern | Details |
|---------|---------|
| Universal Response Envelope | PascalCase keys, Status/Attributes/Results/Navigation/Errors/MethodsStack |
| Modular API Client | `src/lib/api/` — types, envelope, client, methods, barrel |
| Error Store | `buildCapturedError()` + `commitErrorToStore()` (Zustand) |
| API Query Factory | `useApiQuery` / `useApiQueryPaginated` wrapping React Query |
| WordPress Endpoint Mapping | Centralized `endpoint_map.go` with `WPEndpointName` enum |
| apperror Package | `apperror.Wrap(err, code, message)` with `.WithContext()` |
| PHP Bootstrapping Guards | Static `$bootstrapping` flags prevent circular deps |
| Cross-Upload Resilience | QUpload uploads via Riseup API, Riseup uploads via QUpload API |
| `safeExecute()` | Top-level REST handler boundary — catches Throwable, emits to PHP error_log, logs via FileLogger, returns structured envelope |
| `class_exists()` Guard | For any class that could be missing during self-update |

---

## 11. WordPress Plugin Architecture

### Riseup Asia Uploader (`wp-plugins/riseup-asia-uploader/`)

- **Namespace:** `RiseupAsia\`
- **Purpose:** Full-featured deployment receiver with snapshot backups, diagnostics, agent management
- **Current version:** v1.36.1+
- **PHP requirement:** 8.1+
- **Key components:**
  - FileLogger with rotation (`settings.json` configurable)
  - Snapshot system (full + incremental backups, ZIP export)
  - Agent management (multi-agent orchestration)
  - Database with ORM and migrations (SQLite in `wp-content/uploads/`)

### QUpload (`wp-plugins/qupload/`)

- **Namespace:** `QUpload\`
- **Purpose:** Minimal remote deployment — ZIP upload + activation
- **REST API:** `qupload-api/v1`
- **Endpoints:** status, upload (POST), activate (PUT), deactivate, plugins list, logs, machine approval
- **Key feature:** `token_get_all()` syntax validation before activation (source of the compatibility constraints above)

### Cross-Upload Strategy

To prevent self-update failures:
- Uploading QUpload → uses Riseup Asia API (fallback to QUpload own endpoint)
- Uploading Riseup Asia → uses QUpload API

PowerShell `upload-single.ps1` probes the cross-upload partner's `/status` endpoint before choosing.

---

## 12. PowerShell Automation

PowerShell scripts manage deployment from the developer's local machine:

| Command | Purpose |
|---------|---------|
| `.\run.ps1 -uas` | Upload All Sites — deploy plugin to all configured sites |
| `.\run.ps1 -am [NAME]` | Approve Machine — register for restricted operations |
| `.\run.ps1 -cla` | Clear Logs All — clear logs on all sites |
| `.\run.ps1 -check` | Diagnostic check — HEAD requests per endpoint |
| `.\run.ps1 -d` | Deploy — with smart PHP propagation (skips if no changes) |
| `.\run.ps1 -ss` | Site settings |
| `.\run.ps1 -pas` | Pre-flight check (version comparison before deploy) |

### Version Bumping

Automated via `wp-plugins/scripts/bump-version.ps1`.

---

## 13. Recent Conversation & Changes (April 2026)

### User Prompt 1: "Fix the PHP code"

The user identified that PHP files contained patterns blocked by the QUpload remote syntax validator (`is_array()`, `array()` constructor). These needed to be replaced with QUpload-safe alternatives.

### Changes Made (April 1, 2026)

#### File 1: `wp-plugins/riseup-asia-uploader/includes/Logging/FileLogger.php`

**Change 1a:** JSON decode check in `loadLoggingSettings()` (line 118)
```php
// BEFORE:
$isDecodeFailed = !is_array($settings);

// AFTER:
$isDecodeFailed = gettype($settings) !== PhpNativeType::PhpArray->value;
```

**Change 1b:** Logging key check (line 124)
```php
// BEFORE:
$hasLogging = isset($settings['logging']) && is_array($settings['logging']);

// AFTER:
$hasLogging = isset($settings['logging']) && gettype($settings['logging']) === PhpNativeType::PhpArray->value;
```

**Change 1c:** Property default (line 73)
```php
// BEFORE:
private array $dedupHashes = array();

// AFTER:
private array $dedupHashes = [];
```

The file already had `use RiseupAsia\Enums\PhpNativeType;` imported.

#### File 2: `wp-plugins/riseup-asia-uploader/includes/Snapshot/SnapshotDetector.php`

**Change 2a:** Property default (line 31)
```php
// BEFORE:
private array $providerInstances = array();

// AFTER:
private array $providerInstances = [];
```

### User Prompt 2: "List the changes for an AI in MD file"

Created initial `php-compatibility-changes-2026-04-01.md` documenting the above changes.

### User Prompt 3: "Include my prompts and make it detailed so any AI can integrate"

Expanded the documentation into a comprehensive file with conversation history, before/after diffs, regex patterns for scanning, and step-by-step checklist.

### User Prompt 4: "Write a complete handoff file with all memories"

This document — the complete project knowledge transfer file you're reading now.

---

## 14. Pending Tasks

### High Priority (Blocked on User)

1. **Deploy v2.17.0+ to all remote sites** — run `.\run.ps1 -uas`
2. **Verify EnvelopeBuilder fallback fix** — after deploy, test plugin upload via QUpload
3. **Redeploy for plugin_slug fix (v2.30.0)** — blocked on user

### Medium Priority

4. **ORM PDO Fix** — database connectivity for multi-site deployments
5. **User Management wiring** — PHP + Go + React scaffolded, needs route/sidebar integration
6. **Go Backend UserClient** — bridge with PHP user management endpoints

### Low Priority

7. Auto-invalidate cached ZIP on source change (S-052)
8. Create `settings.json` for QUpload (S-049)
9. Add `/logs/rotation-status` endpoint (S-050)
10. Verbose `-check` mode with HEAD requests (S-051)

---

## 15. Suggestions Tracker

**Location:** `.lovable/memory/suggestions/01-suggestions-tracker.md`

**Stats:** 9 open, 57 completed, 1 N/A, 1 rejected (68 total)

Next suggestion ID: S-055.

Completed details: `.lovable/memory/suggestions/completed/01-completed-suggestions.md`

---

## 16. Reliability Risk Report

**Overall Score: 92/100** (Very Good)

### Risk Areas

| Area | Risk | Why |
|------|------|-----|
| Deployment to remote sites | 🔴 High | v2.17.0 not deployed; code fixes unverified |
| Git Backup Strategy | 🟡 Medium-High | 6 greenfield phases; multi-provider complexity |
| Cloud Storage Go pipeline | 🟡 Medium | Backend `cloud_upload` stage not yet wired |
| WordPress API Variability | 🟡 Medium | Different WP versions/configs |
| SQLite Concurrency | 🟢 Low | Concurrent writes during bulk publish |

### Known Cross-File Inconsistencies

- `pending-tasks.md` says log rotation is done but issue #28 still lists it as pending
- Workflow doc has stale suggestion count
- Plan file has minor sync drift with `active.md`

---

## 17. File & Folder Map

```
.lovable/
├── README.md                          # Entry point for AI
├── plan.md                            # Master roadmap (Phases A–J)
├── plan/
│   ├── active.md                      # Current sprint status
│   ├── technical-notes.md             # Architecture decisions
│   └── completed/                     # 7 archived plan files
├── memory/
│   ├── 01-conventions.md              # Coding conventions (all languages)
│   ├── 01-workflow.md                 # Workflow guidelines + session handoff
│   ├── 02-project-context.md          # Project overview
│   ├── 03-reliability-risk-report.md  # Reliability: 92/100
│   ├── PRD.md                         # Plugins Onboard PRD
│   ├── architecture/                  # 11 subdirs of established patterns
│   ├── coding-standards/              # Standards (Go, PHP, TypeScript, PowerShell)
│   │   ├── php-compatibility-constraint.md   # QUpload validator rules
│   │   ├── php-exception-handling.md         # Exception handling patterns
│   │   ├── php-positive-polarity-ternaries.md # Ternary extraction rule
│   │   ├── go-*.md                           # 9 Go standard files
│   │   └── ...
│   ├── features/                      # 17 feature docs
│   │   ├── qupload-plugin.md
│   │   ├── remote-plugin-management.md
│   │   ├── snapshot-zip-export.md
│   │   └── ...
│   ├── issues-fixed/                  # 15 documented bug fixes
│   ├── issues/                        # 8 active/resolved structural issues
│   ├── suggestions/                   # Tracker + completed suggestions
│   └── workflow/
│       └── pending-tasks.md           # Deployment blockers + pending items

spec/
├── readme.md                          # Spec index (start here)
├── 01-app/                            # App specs
├── 02-app-issues/                     # 31 issue write-ups
├── 04-coding-guidelines/              # DRY principles
├── 05-golang-standards/               # Go standards (4+ files)
├── 07-php-standards/                  # PHP standards
├── 09-wordpress/                      # WordPress plugin specs
├── 13-powershell-integration/         # PowerShell + PHP known issues
└── ...                                # 17 spec folders total

wp-plugins/
├── riseup-asia-uploader/              # Main companion plugin
│   ├── includes/
│   │   ├── Enums/PhpNativeType.php    # Type checking enum (critical)
│   │   ├── Logging/FileLogger.php     # File-based logging with rotation
│   │   ├── Snapshot/                  # Backup/snapshot system
│   │   └── ...
│   └── settings.json                  # Logging configuration
├── qupload/                           # Quick Upload plugin
└── scripts/                           # bump-version.ps1, lint scripts

backend/                               # Go backend
src/                                   # React frontend
```

---

## 18. Session Handoff Checklist

When starting a new AI session on this repo:

1. ✅ **Read this file** — you're doing it now
2. **Read `.lovable/plan.md`** — master roadmap with next task selection
3. **Read `.lovable/plan/active.md`** — current sprint status
4. **Check `.lovable/memory/suggestions/01-suggestions-tracker.md`** — 9 open suggestions
5. **Read `spec/readme.md`** — spec index for any feature you'll implement
6. **Check `.lovable/memory/workflow/pending-tasks.md`** — deployment blockers
7. **Ask the user** what to implement next

### Before Implementing Any Feature

1. Read the specific spec file for that feature
2. Check `.lovable/memory/architecture/` for established patterns
3. Review language-specific standards in `.lovable/memory/coding-standards/`
4. Review related specs via cross-references

### After Implementing

1. Update `.lovable/plan.md` with status changes
2. Update suggestions tracker if suggestions changed
3. Note any blockers or decisions made
4. Update `02-project-context.md` if major features added
5. Follow the post-fix issue writeup workflow for every bug fix

---

## 19. Anti-Patterns — Do NOT Repeat

These mistakes have been made before. Do not repeat them:

1. **Never use `is_array()`, `is_string()`, etc. in PHP** — blocked by QUpload validator. Use `gettype($var) === PhpNativeType::*->value`
2. **Never use `array()` constructor** — use `[]` short syntax
3. **Never use magic strings** like `=== 'array'` with `gettype()` — use PhpNativeType enum
4. **Never hard-depend on helper classes in response paths** — use `class_exists()` guard
5. **Never log `$e->getMessage()` alone** — stack trace is the most important part
6. **Never use `fmt.Errorf` in Go** for errors leaving a service boundary — use `apperror.Wrap()`
7. **Never hardcode `localhost:8080`** in React — use `resolveApiUrl()`
8. **Never call WordPress functions in PHP constructors** — use lazy initialization
9. **Never use raw comparisons in ternary conditions** — extract to named boolean
10. **Never use negations (`!`) in PHP if statements** — use positive boolean helpers
11. **Never deploy without checking remote site versions first** — use `-check` or `-am` preflight
12. **Never create silent catch blocks** — every catch must log with trace (except logger recursion guards)
13. **Never use `any`/`interface{}` in production Go** — use typed alternatives
14. **Boot-time catch blocks must re-throw** — use `logCriticalException()` or `errorLogAndThrow()`

---

*This document was generated on 2026-04-01 as a comprehensive AI handoff file. It consolidates all project memories, coding standards, recent changes, known issues, and architectural decisions into a single reference. Keep it updated when major changes occur.*
