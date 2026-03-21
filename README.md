# WP Plugin Publish

A full-stack WordPress plugin deployment system — React dashboard, Go backend orchestrator, PHP WordPress plugins, PowerShell automation, and a standalone licensing server. Designed for managing plugin deployments across multiple WordPress sites with version tracking, delta sync, remote backups, and one-command publishing.

**Current Version:** `2.28.3` · **Release Date:** `2026-03-20`

> 📋 See [`public/version.json`](./public/version.json) for the full changelog with all release notes.

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                           WP Plugin Publish                                  │
├─────────────┬──────────────┬──────────────────┬──────────────┬───────────────┤
│  React UI   │  Go Backend  │  WordPress Sites │  Licensing   │  PowerShell   │
│  (Vite/TS)  │  (REST+WS)   │  (PHP Plugins)   │  Server (Go) │  CLI          │
├─────────────┼──────────────┼──────────────────┼──────────────┼───────────────┤
│ Dashboard   │ Orchestrator │ Riseup Asia      │ License CRUD │ run.ps1       │
│ Plugin mgmt │ SQLite DB    │ Uploader (REST)  │ Activation   │ upload-v2     │
│ Live logs   │ WebSocket    │ QUpload (REST)   │ Validation   │ upload-U-Q    │
│ Diff viewer │ AES-256-GCM  │ Plugins Onboard  │ Domain lock  │ bump-version  │
│ Bulk ops    │ Backup hooks │                  │ SQLite DB    │ ZIP & deploy  │
└─────────────┴──────────────┴──────────────────┴──────────────┴───────────────┘
```

---

## Tech Stack

| Layer | Technology | Directory |
|-------|-----------|-----------|
| **Frontend** | React 18 · TypeScript · Vite · Tailwind CSS · shadcn/ui · Zustand | `src/` |
| **Backend** | Go 1.21+ · REST API · WebSocket · SQLite · AES-256-GCM encryption | `backend/` |
| **Licensing** | Go · REST API · SQLite · HMAC signatures · Rate limiting | `licensing/` |
| **WordPress Plugins** | PHP 8.2+ · PSR-4 · REST API · WordPress Application Passwords | `wp-plugins/` |
| **Automation** | PowerShell 5.1+ · Self-linting · JSON config · Semantic versioning | `run.ps1`, `wp-plugins/scripts/` |
| **Quality Gates** | 15 lint scripts · PHPStan L6 · `go vet` · CI workflows · Pre-commit hook | `scripts/`, `.github/workflows/` |
| **Specifications** | 17 spec directories · Coding standards · Architecture decisions | `spec/` |
| **Tools** | Go-based consistency checker for cross-stack enum/endpoint drift | `tools/` |

---

## Prerequisites

- **Windows** (required for PowerShell deployment scripts)
- [Node.js](https://nodejs.org/) 18+ with [pnpm](https://pnpm.io/)
- [Go](https://go.dev/) 1.21+
- [PowerShell](https://learn.microsoft.com/en-us/powershell/) 5.1+ (ships with Windows)
- PHP 8.2+ with [Composer](https://getcomposer.org/) (for PHPStan static analysis)

---

## Getting Started

### 1. Clone & Install

```powershell
git clone <YOUR_GIT_URL>
cd wp-plugin-publish

# Automated setup: installs Node.js, pnpm, Go dependencies
.\run.ps1 -i
```

### 2. Build & Run (All-in-One)

```powershell
# Full pipeline: git pull → prerequisites → pnpm install → build → start Go server
.\run.ps1

# Deploy mode: git pull → upload all sites → plugin status → build & run
.\run.ps1 -d

# Clean reinstall (fresh dependencies + clean build)
.\run.ps1 -r
```

### 3. Development Mode

```powershell
# React dev server only (hot reload)
pnpm run dev

# Go backend only (skip frontend build)
.\run.ps1 -s
```

### 4. Deploy Plugins

```powershell
# Upload all plugins to all configured sites
.\run.ps1 -uas

# Check plugin status across all sites
.\run.ps1 -pas

# Clear all logs everywhere
.\run.ps1 -cas -yes
```

### 5. Install Pre-commit Hooks

```bash
bash scripts/install-hooks.sh
```

---

## Project Structure

```
wp-plugin-publish/
├── src/                              # React frontend (Vite + TypeScript)
│   ├── components/                   # UI components (shadcn/ui based)
│   ├── pages/                        # Route pages
│   ├── lib/                          # API client, utilities, constants
│   ├── stores/                       # Zustand state management
│   ├── hooks/                        # Custom React hooks
│   └── types/                        # TypeScript type definitions
│
├── backend/                          # Go backend (orchestrator)
│   ├── cmd/server/                   # Entry point (main.go)
│   ├── internal/
│   │   ├── api/                      # REST API handlers
│   │   ├── config/                   # Configuration loading
│   │   ├── database/                 # SQLite database layer
│   │   ├── models/                   # Domain models
│   │   ├── services/                 # Business logic (publish, sync, diff, backup)
│   │   ├── wordpress/                # WordPress REST client
│   │   ├── ws/                       # WebSocket hub for live logs
│   │   ├── crypto/                   # AES-256-GCM credential encryption
│   │   └── envelope/                 # Response envelope (universal JSON schema)
│   └── pkg/                          # Shared packages (apperror, pathutil, dbops)
│
├── licensing/                        # Licensing server (standalone Go module)
│   ├── cmd/                          # Entry point
│   ├── internal/                     # Handlers, services, database
│   └── pkg/                          # Shared packages
│
├── wp-plugins/                       # WordPress plugins
│   ├── riseup-asia-uploader/         # Main deployment plugin (PHP 8.2+)
│   │   ├── CHANGELOG.md              # Plugin-specific changelog
│   │   └── README.md                 # Full endpoint & architecture docs
│   ├── qupload/                      # Lightweight upload-only plugin (PHP 8.1+)
│   │   ├── CHANGELOG.md              # Plugin-specific changelog
│   │   └── README.md                 # Setup & usage docs
│   ├── plugins-onboard/              # Enterprise plugin manager (OAuth 2.0)
│   └── scripts/                      # PowerShell upload & versioning scripts
│       ├── README.md                 # ★ Full CLI command reference
│       ├── modules/                  # Modular PowerShell functions
│       ├── bump-version.ps1          # Semantic version automation
│       └── upload-plugin-U-Q.ps1     # Upload via QUpload API
│
├── tools/                            # Developer tooling
│   └── consistency-checker/          # Cross-stack enum/endpoint drift detector (Go)
│
├── scripts/                          # Quality gate scripts (Bash)
│   ├── pre-commit.sh                 # Unified pre-commit hook (all checks)
│   ├── install-hooks.sh              # One-command hook installation
│   └── lint-*.sh                     # Individual lint rules (15 scripts)
│
├── spec/                             # Technical specifications
├── run.ps1                           # ★ Main CLI entry point (all operations)
├── powershell.json                   # Runner configuration
└── public/version.json               # ★ Synchronized versions + full changelog
```

---

## WordPress Plugins

### Riseup Asia Uploader

The main deployment plugin. Provides 80+ REST API endpoints for remote plugin management, delta file sync, user CRUD, database snapshots, cloud storage backups, blog post publishing, and audit logging.

| Property | Value |
|----------|-------|
| **Namespace** | `RiseupAsia\` |
| **REST API** | `/wp-json/riseup-asia-api/v1/` |
| **Auth** | WordPress Application Passwords |
| **Min PHP** | 8.2 |
| **Endpoints** | 80+ across 9 categories |

> 📖 See [`wp-plugins/riseup-asia-uploader/README.md`](wp-plugins/riseup-asia-uploader/README.md) for full endpoint reference.
> 📋 See [`wp-plugins/riseup-asia-uploader/CHANGELOG.md`](wp-plugins/riseup-asia-uploader/CHANGELOG.md) for release history.

### QUpload (Quick Upload)

A minimal, focused plugin for ZIP-based deployments. Used as the transport layer when the target plugin's own API isn't available (chicken-and-egg problem for first-time installs).

| Property | Value |
|----------|-------|
| **Namespace** | `QUpload\` |
| **REST API** | `/wp-json/qupload-api/v1/` |
| **Auth** | WordPress Application Passwords |
| **Min PHP** | 8.1 |

> 📖 See [`wp-plugins/qupload/README.md`](wp-plugins/qupload/README.md) for full details.
> 📋 See [`wp-plugins/qupload/CHANGELOG.md`](wp-plugins/qupload/CHANGELOG.md) for release history.

### Plugins Onboard

Enterprise-grade plugin manager with OAuth 2.0, ephemeral mutation tokens, automatic backups, and IP whitelist.

| Property | Value |
|----------|-------|
| **REST API** | `/wp-json/onboard-plugin/v1/` |
| **Auth** | OAuth 2.0 + JWT |

---

## Licensing Server

A standalone Go module (`licensing/`) providing license key management for the plugin ecosystem.

| Feature | Detail |
|---------|--------|
| **Endpoints** | `POST /licenses`, `GET /licenses/{key}/validate`, `POST /licenses/{key}/activate`, `POST /licenses/{key}/deactivate` |
| **Storage** | SQLite (portable, no external DB) |
| **Security** | HMAC signature verification, rate limiting |
| **Model** | Key, Email, Product, MaxActivations, Activations[], ExpiresAt, Status |

---

## PowerShell CLI (`run.ps1`)

The `run.ps1` script is the **single entry point** for all operations. Every invocation runs `git pull` first (skip with `-p`).

> 📖 **Full CLI Reference:** [`wp-plugins/scripts/README.md`](wp-plugins/scripts/README.md) — detailed docs for every flag with examples.

### Quick Reference

```powershell
# ── Build & Run ──
.\run.ps1                  # Full pipeline
.\run.ps1 -r               # Clean reinstall + build + run
.\run.ps1 -s               # Backend only
.\run.ps1 -t               # Run Go tests

# ── Deploy ──
.\run.ps1 -uas             # All plugins → all sites (parallel)
.\run.ps1 -u               # Default plugin → default site
.\run.ps1 -uas -v           # Deploy with verbose JSON output

# ── Status & Diagnostics ──
.\run.ps1 -pas             # Plugin status on all sites
.\run.ps1 -pas -err        # Status + error logs
.\run.ps1 -check           # Preflight readiness check
.\run.ps1 -check -v        # Detailed endpoint availability

# ── Log Management ──
.\run.ps1 -cla             # Clear logs on all sites
.\run.ps1 -cas -yes        # Nuke ALL logs everywhere (skip confirmation)

# ── Machine Auth ──
.\run.ps1 -am              # Approve current machine on all sites

# ── Version ──
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch
```

### All Flags

| Category | Key Flags |
|----------|-----------|
| **Build** | `-b`, `-s`, `-f`, `-r`, `-i`, `-t`, `-v` |
| **Upload** | `-u`, `-q`, `-ua`, `-uas`, `-u -as`, `-sync`, `-pp`, `-d` |
| **Status** | `-ps`, `-pas`, `-err`, `-check` |
| **Logs** | `-cl`, `-cla`, `-cas`, `-purge`, `-logplugin`, `-logtype`, `-audit` |
| **Machine** | `-am`, `-machine` |
| **ZIP** | `-z`, `-za`, `-zas`, `-zq` |
| **Targeting** | `-site`, `-i`, `-xs`, `-ls` |
| **Safety** | `-yes`/`-y` (skip confirmation), `-v` (verbose JSON) |

---

## Coding Guidelines

### PHP (WordPress Plugins)

| Rule | Detail |
|------|--------|
| Max file size | 500 lines → decompose into traits |
| Max function size | 20 lines |
| Enums | Backed enums only (`Type` suffix, PascalCase) |
| Imports | PSR-4, grouped by namespace origin |
| Error handling | `Throwable`-first, no silent catches |
| Static analysis | PHPStan level 6 (blocks deployment) |
| Booleans | Positive naming (`$isValid`, not `$isNotValid`) |

> Full spec: [`spec/07-php-standards/`](./spec/07-php-standards/)

### Go (Backend & Licensing)

| Rule | Detail |
|------|--------|
| Max file size | 300 lines |
| Max function size | 15 lines |
| Types | No `interface{}` or `any` |
| Naming | PascalCase abbreviations (`Api`, `Url`, `Http`) |
| Error handling | Typed error results with stack traces |
| Spacing | Blank line before `return`, `if`, and block statements |

> Full spec: [`spec/06-golang-standards/`](./spec/06-golang-standards/)

### TypeScript (Frontend)

| Rule | Detail |
|------|--------|
| Types | Zero `any` policy |
| State | Zustand stores |
| Components | shadcn/ui + Tailwind CSS |
| Data fetching | TanStack Query |

> Full spec: [`spec/05-typescript-standards/`](./spec/05-typescript-standards/)

### PowerShell (Automation)

| Rule | Detail |
|------|--------|
| REST calls | `Invoke-WebRequest` only (never `Invoke-RestMethod`) |
| JSON parsing | Strip PHP noise before `ConvertFrom-Json` |
| Encoding | UTF-8 no BOM, straight ASCII quotes |
| Self-lint | Scripts validate own syntax before execution |
| Versioning | `[version]` type casting for semver comparison |

> Full spec: [`spec/13-powershell-integration/`](./spec/13-powershell-integration/)

---

## Upload Pipeline

### How `-uas` (Upload All Sites) Works

1. **Phase 0** — PHP syntax check + PHPStan L6 (runs in parallel with Phase 1)
2. **Phase 1** — Versioned ZIP creation (best compression)
3. **Phase 2** — Plugin-sequential, site-parallel upload:
   - Each plugin is fully deployed across all sites before the next plugin starts
   - Within each plugin, sites are uploaded concurrently via background jobs
   - Cross-upload: QUpload uploads Riseup Asia, Riseup Asia uploads QUpload

### Pre-flight Checks

1. **PHP syntax check** — validates all PHP files before packaging
2. **PHPStan L6** — catches return type mismatches, undefined methods, incorrect arguments
3. **Backed enum lint** — detects duplicate enum values
4. **Namespace detection** — checks if target API namespace is registered on the site
5. **QUpload fallback** — suggests `.\run.ps1 -u -q` if primary API missing but QUpload available
6. **Auth pre-check** — hits `GET /status` to verify credentials and activation

---

## Quality Gates

### Pre-commit Hook

The pre-commit hook (`scripts/pre-commit.sh`) runs **all quality gates** across all modules:

| Module | Checks |
|--------|--------|
| **Backend** (Go) | File size ≤300 lines, function size ≤15 lines, positive naming, import grouping, generic enforce, JSON tags, inline-if, typed-nil, `go vet` |
| **Licensing** (Go) | Same as Backend |
| **Consistency Checker** (Go) | File size, function size, positive naming, inline-if |
| **PHP** (wp-plugins) | File size ≤500 lines, function size ≤20 lines, import grouping, global imports, PHPStan L6 |

### CI Pipelines

| Workflow | Scope |
|----------|-------|
| `go-lint.yml` | All Go lint scripts for `backend/` and `licensing/` |
| `consistency-checker.yml` | Cross-stack enum/endpoint drift detection |

---

## Version Management

All versions are synchronized via `bump-version.ps1`. Current version: **`2.28.3`**

| Component | File(s) |
|-----------|---------|
| **App** | `public/version.json` |
| **Script** | `run.ps1` header, `powershell.json`, `public/version.json` |
| **Plugin** | `PluginConfigType.php`, plugin header, `public/version.json` |
| **QUpload** | `PluginConfigType.php`, `qupload.php` header, `public/version.json` |

```powershell
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch
.\wp-plugins\scripts\bump-version.ps1 -Target plugin -Bump minor
.\wp-plugins\scripts\bump-version.ps1 -Target app -Set "3.0.0"
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch -DryRun
```

> Full versioning docs: [`.lovable/memory/architecture/dev-environment/powershell-versioning.md`](.lovable/memory/architecture/dev-environment/powershell-versioning.md)

---

## Configuration

### `powershell.json`

Main configuration for `run.ps1`. Defines paths, build commands, prerequisites, and the multi-site plugin registry.

```json
{
  "projectName": "WP Publish",
  "rootDir": ".",
  "backendDir": "backend",
  "frontendDir": ".",
  "buildCommand": "pnpm run build",
  "runCommand": "go run ./cmd/server",
  "wpPlugins": {
    "defaultUploader": "riseup-asia-uploader",
    "defaultQUploader": "qupload",
    "pluginsDir": "wp-plugins",
    "skipPlugins": ["plugins-onboard"],
    "plugins": { ... },
    "sites": [
      {
        "name": "Site Name",
        "url": "https://example.com",
        "enabled": true,
        "credentials": [{ "appName": "admin", "usernameBase64": "...", "passwordBase64": "...", "isDefault": true }]
      }
    ]
  }
}
```

---

## Key Architecture Decisions

- **Zero `any`/`interface{}`** — All stacks enforce strict typing with no type erasure
- **Backed enums everywhere** — PHP, Go, and TypeScript use typed enums for all constants
- **PSR-4 namespacing** — WordPress plugins use full PSR-4 autoloading with zero global classes
- **Response envelope** — Universal JSON schema shared across all stacks
- **`Invoke-WebRequest` only** — Never `Invoke-RestMethod` (WordPress prepends PHP notices to JSON)
- **PHP noise stripping** — All PowerShell REST calls strip non-JSON content before parsing
- **Self-linting scripts** — All PowerShell scripts validate their own syntax before execution
- **Pre-flight checks** — Upload scripts verify API availability, auth, and static analysis before transfers
- **UTF-8 no BOM** — All PowerShell scripts must use UTF-8 encoding with straight ASCII quotes
- **`git pull` first** — Every `run.ps1` invocation pulls latest before any operation
- **PHPStan L6 mandatory** — Static analysis blocks upload on return type mismatches and other errors
- **Cross-upload resilience** — QUpload and Riseup Asia can deploy each other

---

## Specifications

All coding standards and architecture decisions are documented in [`spec/`](./spec/readme.md):

| Spec | Directory | Description |
|------|-----------|-------------|
| App | [`spec/01-app/`](./spec/01-app/) | Application overview and features |
| App Issues | [`spec/02-app-issues/`](./spec/02-app-issues/) | Bug reports and RCA write-ups |
| Coding Guidelines | [`spec/04-coding-guidelines/`](./spec/04-coding-guidelines/) | DRY principles, strict typing, naming rules |
| TypeScript | [`spec/05-typescript-standards/`](./spec/05-typescript-standards/) | Zero-`any`, catch narrowing, generic envelopes |
| Go Standards | [`spec/06-golang-standards/`](./spec/06-golang-standards/) | No `interface{}`, typed structs, error diagnostics |
| PHP Standards | [`spec/07-php-standards/`](./spec/07-php-standards/) | PSR-4, backed enums, `Throwable`, forbidden patterns |
| Error System | [`spec/08-error-manage/`](./spec/08-error-manage/) | Cross-stack error handling, response envelope |
| WordPress | [`spec/09-wordpress/`](./spec/09-wordpress/) | Plugin architecture and REST API design |
| Features | [`spec/10-features/`](./spec/10-features/) | Feature specifications |
| Audits | [`spec/11-audits/`](./spec/11-audits/) | Code audit reports |
| Feedback | [`spec/12-feedback-report-feature/`](./spec/12-feedback-report-feature/) | Bug report submission feature |
| PowerShell | [`spec/13-powershell-integration/`](./spec/13-powershell-integration/) | Runner spec, config schema, script reference |
| User Mgmt | [`spec/16-user-management/`](./spec/16-user-management/) | User CRUD, app passwords, bulk ops |
| Parallel PS | [`spec/17-parallel-powershell-scripts/`](./spec/17-parallel-powershell-scripts/) | Parallel deployment architecture |
| Issues | [`spec/issues/`](./spec/issues/) | Root cause analysis write-ups |

---

## Documentation Index

| Document | Path | Description |
|----------|------|-------------|
| **Root README** | [`README.md`](./README.md) | This file — project overview |
| **Root Changelog** | [`CHANGELOG.md`](./CHANGELOG.md) | Frontend dashboard changelog |
| **Version History** | [`public/version.json`](./public/version.json) | Full changelog for all components |
| **Riseup Asia README** | [`wp-plugins/riseup-asia-uploader/README.md`](./wp-plugins/riseup-asia-uploader/README.md) | Plugin architecture & endpoints |
| **Riseup Asia Changelog** | [`wp-plugins/riseup-asia-uploader/CHANGELOG.md`](./wp-plugins/riseup-asia-uploader/CHANGELOG.md) | Plugin release history |
| **QUpload README** | [`wp-plugins/qupload/README.md`](./wp-plugins/qupload/README.md) | QUpload setup & usage |
| **QUpload Changelog** | [`wp-plugins/qupload/CHANGELOG.md`](./wp-plugins/qupload/CHANGELOG.md) | QUpload release history |
| **PowerShell CLI** | [`wp-plugins/scripts/README.md`](./wp-plugins/scripts/README.md) | ★ Detailed CLI command reference |
| **Spec Index** | [`spec/readme.md`](./spec/readme.md) | All specifications |

---

## Author

**MD ALIM UL KARIM**

- Profile: [rasia.pro](https://rasia.pro/alim-r-profile-v1)
- Company: Riseup Asia

## License

GPL v2 or later
