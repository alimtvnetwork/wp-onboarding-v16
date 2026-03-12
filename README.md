# WP Plugin Publish

A full-stack WordPress plugin deployment system — React dashboard, Go backend orchestrator, PHP WordPress plugins, PowerShell automation, and a standalone licensing server. Designed for managing plugin deployments across multiple WordPress sites with version tracking, delta sync, remote backups, and one-command publishing.

**Current Version:** `2.8.0` · **Script Version:** `2.4.0`

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
| **WordPress Plugins** | PHP 8.1+ · PSR-4 · REST API · WordPress Application Passwords | `wp-plugins/` |
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
- PHP 8.1+ with [Composer](https://getcomposer.org/) (for PHPStan static analysis)

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

### 4. Install Pre-commit Hooks

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
│   │   ├── wordpress/                # PowerShell integration (Go ↔ PS1)
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
│   ├── riseup-asia-uploader/         # Main deployment plugin (PHP 8.1+)
│   ├── qupload/                      # Lightweight upload-only plugin (PHP 8.1+)
│   ├── plugins-onboard/              # Enterprise plugin manager (OAuth 2.0)
│   └── scripts/                      # PowerShell upload & versioning scripts
│       ├── upload-plugin-v2.ps1      # Upload via Riseup Asia Uploader API
│       ├── upload-plugin-U-Q.ps1     # Upload via QUpload API
│       ├── bump-version.ps1          # Semantic version automation
│       ├── wp-plugin-config.json     # Riseup Asia site credentials
│       └── qupload-config.json       # QUpload site credentials
│
├── tools/                            # Developer tooling
│   └── consistency-checker/          # Cross-stack enum/endpoint drift detector (Go)
│
├── scripts/                          # Quality gate scripts (Bash)
│   ├── pre-commit.sh                 # Unified pre-commit hook (all checks)
│   ├── install-hooks.sh              # One-command hook installation
│   ├── lint-file-size.sh             # Go file ≤300 lines
│   ├── lint-func-size.sh             # Go function ≤15 lines
│   ├── lint-negative.sh              # Positive boolean naming
│   ├── lint-imports.sh               # Go import grouping
│   ├── lint-ge.sh                    # Generic enforce (GE-5)
│   ├── lint-json-tags.sh             # JSON struct tag check
│   ├── lint-inline-if.sh             # No inline if statements
│   ├── lint-typed-nil.sh             # Typed-nil prevention
│   ├── lint-php-file-size.sh         # PHP file ≤500 lines
│   ├── lint-php-func-size.sh         # PHP function ≤20 lines
│   ├── lint-php-import-groups.sh     # PHP use-statement grouping
│   ├── lint-php-global-imports.sh    # PHP global class imports
│   └── lint-php-phpstan.sh           # PHPStan level-6 static analysis
│
├── .github/workflows/               # CI pipelines
│   ├── go-lint.yml                   # All Go lint checks (backend + licensing)
│   └── consistency-checker.yml       # Cross-stack drift detection
│
├── spec/                             # Technical specifications (17 directories)
├── run.ps1                           # Main PowerShell runner (all-in-one CLI)
├── powershell.json                   # Runner configuration
└── public/version.json               # Synchronized version tracking
```

---

## WordPress Plugins

### Riseup Asia Uploader

The main deployment plugin. Provides a full REST API for remote plugin management, delta file sync, blog post publishing, remote backups, and audit logging.

| Property | Value |
|----------|-------|
| **Namespace** | `RiseupAsia\` |
| **REST API** | `/wp-json/riseup-asia-uploader/v1/` |
| **Auth** | WordPress Application Passwords |
| **Min PHP** | 8.1 |

**Key endpoints:** `/upload`, `/status`, `/activate`, `/sync`, `/posts`, `/categories`, `/plugins/backup`, `/plugins/backup-restore`, `/plugins/backup-list`

### QUpload (Quick Upload)

A minimal, focused plugin for ZIP-based deployments. Used as the transport layer when the target plugin's own API isn't available (chicken-and-egg problem for first-time installs).

| Property | Value |
|----------|-------|
| **Namespace** | `QUpload\` |
| **REST API** | `/wp-json/qupload-api/v1/` |
| **Auth** | WordPress Application Passwords |
| **Min PHP** | 8.1 |

**Key endpoints:** `/upload`, `/status`, `/activate`

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

## PowerShell CLI Reference (`run.ps1`)

The `run.ps1` script is the single entry point for all operations. **`git pull` always runs first** before any command (use `-p` to skip).

### Build & Run Flags

| Flag | Description |
|------|-------------|
| _(none)_ | Full pipeline: git pull → prerequisites → build → run |
| `-b` / `-buildonly` | Build frontend only, don't start backend |
| `-s` / `-skipbuild` | Start backend only, skip frontend build |
| `-p` / `-skippull` | Skip git pull step |
| `-f` / `-force` | Clean build: remove caches, deps, databases |
| `-i` / `-install` | Install/update all dependencies then exit |
| `-r` / `-rebuild` | Full reset: clean + install + build + run |
| `-fw` / `-openfirewall` | Add Windows Firewall rules (requires Admin) |
| `-t` / `-test` | Run Go backend tests and exit |
| `-v` / `-verbose` | Show detailed debug output |

### Upload Flags

| Flag | Description |
|------|-------------|
| `-u` / `-upload` | Upload default plugin via Riseup Asia Uploader API |
| `-q` / `-qupload` | Upload default plugin via QUpload API |
| `-u -q` | Upload Riseup Asia Uploader itself via QUpload API |
| `-ua` / `-uploadall` | ZIP + upload **all** plugins (except QUpload and skip list) via QUpload API |
| `-d` / `-debug` | Enable debug logging for uploads |
| `-pp <path>` | Override plugin folder path |

### ZIP Flags

| Flag | Description |
|------|-------------|
| `-z` / `-zip` | ZIP default plugin (auto-cleans old ZIPs) |
| `-za` | ZIP **all** plugins in `wp-plugins/` (auto-cleans old ZIPs) |
| `-zq` / `-zipqupload` | ZIP QUpload plugin |
| `-c` / `-clear` | Explicit ZIP cleanup (redundant — all ZIP ops auto-clean) |

### Examples

```powershell
# ── Build & Run ──
.\run.ps1                              # Full build and run
.\run.ps1 -r                           # Clean reinstall + build + run
.\run.ps1 -s                           # Backend only (skip build)
.\run.ps1 -b                           # Build only (no server)
.\run.ps1 -t                           # Run Go tests

# ── Upload Single Plugin ──
.\run.ps1 -u                           # Upload default plugin (Riseup Asia API)
.\run.ps1 -q                           # Upload default plugin (QUpload API)
.\run.ps1 -u -q                        # Upload Riseup Asia Uploader via QUpload
.\run.ps1 -q -pp 'wp-plugins/qupload'  # Upload specific plugin via QUpload
.\run.ps1 -u -d                        # Upload with debug logging

# ── Upload All Plugins ──
.\run.ps1 -ua                          # ZIP + upload all plugins via QUpload

# ── ZIP Only ──
.\run.ps1 -z                           # ZIP default plugin
.\run.ps1 -za                          # ZIP all plugins
.\run.ps1 -zq                          # ZIP QUpload plugin

# ── Version Bump ──
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch
.\wp-plugins\scripts\bump-version.ps1 -Target plugin -Bump minor
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch -DryRun
```

---

## Upload Workflow

### How `-ua` (Upload All) Works

1. Scans `wp-plugins/` for directories with valid WordPress plugin headers
2. **Excludes QUpload** (it's the upload transport) and plugins in the `skipPlugins` list
3. For each plugin:
   - Runs PHPStan static analysis (blocks on failure)
   - Creates a versioned ZIP archive (best compression)
   - Uploads via QUpload's `POST /wp-json/qupload-api/v1/upload`
   - Activates the plugin after upload
4. Displays a summary table with success/failure for each plugin

### When to Use Each Upload Mode

| Scenario | Command |
|----------|---------|
| Plugin's own API is active on target site | `.\run.ps1 -u` |
| First-time install (plugin API not available) | `.\run.ps1 -u -q` |
| Deploy all plugins at once | `.\run.ps1 -ua` |
| Deploy specific plugin via QUpload | `.\run.ps1 -q -pp 'wp-plugins/my-plugin'` |

### Pre-flight Checks

The upload scripts include automatic pre-flight checks:

1. **PHP syntax check** — validates all PHP files before packaging
2. **Backed enum lint** — detects duplicate enum values
3. **PHPStan analysis** — level-6 static analysis catches return type mismatches, undefined methods, and incorrect argument types
4. **Namespace detection** — checks if the target API namespace is registered on the site
5. **QUpload fallback** — if the primary API is missing but QUpload is available, suggests `.\run.ps1 -u -q`
6. **Auth pre-check** — hits `GET /status` to verify credentials and plugin activation

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

### PHPStan Static Analysis

Both PHP plugins include PHPStan level-6 configuration:

- `phpstan.neon` — analysis config with WordPress function ignores
- `phpstan-bootstrap.php` — stubs for `WP_User`, `WP_Error`, `WP_REST_Request`, `WP_REST_Response`
- Catches: return type mismatches, undefined method/property access, incorrect argument types
- Graceful degradation: skips if PHPStan or PHP CLI not installed

---

## Configuration

### `powershell.json`

Main configuration for `run.ps1`. Defines paths, build commands, prerequisites, and the plugin registry.

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
    "plugins": { ... }
  }
}
```

### Site Credential Files

| File | Purpose |
|------|---------|
| `wp-plugins/scripts/wp-plugin-config.json` | Riseup Asia Uploader API credentials |
| `wp-plugins/scripts/qupload-config.json` | QUpload API credentials |

```json
{
  "pluginFolderPath": "wp-plugins/riseup-asia-uploader",
  "wordPressSiteURL": "https://your-site.com",
  "username": "admin",
  "appPassword": "xxxx xxxx xxxx xxxx xxxx xxxx",
  "activateAfterInstall": true,
  "deleteZipAfterUpload": true
}
```

---

## Version Management

All versions are synchronized via `bump-version.ps1`:

| Component | File(s) |
|-----------|---------|
| **App** | `public/version.json` |
| **Script** | `run.ps1` header, `powershell.json`, `public/version.json` |
| **Plugin** | `PluginConfigType.php`, `public/version.json` |
| **QUpload** | `qupload/PluginConfigType.php`, `qupload.php` header, `public/version.json` |

```powershell
# Bump all components (patch)
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch

# Bump only plugin (minor)
.\wp-plugins\scripts\bump-version.ps1 -Target plugin -Bump minor

# Set exact version
.\wp-plugins\scripts\bump-version.ps1 -Target app -Set "3.0.0"

# Preview changes without writing
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch -DryRun
```

---

## Key Architecture Decisions

- **Zero `any`/`interface{}`** — All stacks enforce strict typing with no type erasure
- **Backed enums everywhere** — PHP, Go, and TypeScript use typed enums for all constants
- **PSR-4 namespacing** — WordPress plugins use full PSR-4 autoloading with zero global classes
- **Response envelope** — Universal JSON schema shared across all stacks
- **Self-linting scripts** — All PowerShell scripts validate their own syntax before execution
- **Pre-flight checks** — Upload scripts verify API availability, auth, and static analysis before transfers
- **UTF-8 no BOM** — All PowerShell scripts must use UTF-8 encoding with straight ASCII quotes
- **`git pull` first** — Every `run.ps1` invocation pulls latest before any operation
- **Auto-clean ZIPs** — All ZIP operations implicitly remove old archives before creating new ones
- **PHPStan L6 mandatory** — Static analysis blocks upload on return type mismatches and other errors

---

## Specifications

All coding standards and architecture decisions are documented in [`spec/`](./spec/readme.md):

| Spec | Description |
|------|-------------|
| [App](./spec/01-app/) | Application overview and features |
| [App Issues](./spec/02-app-issues/) | Bug reports and RCA write-ups |
| [Coding Guidelines](./spec/03-coding-guidelines/) | DRY principles, strict typing, naming rules |
| [TypeScript Standards](./spec/04-typescript-standards/) | Zero-`any` policy, catch narrowing, generic envelopes |
| [Go Standards](./spec/05-golang-standards/) | No `interface{}`, typed structs, error diagnostics |
| [PHP Standards](./spec/06-php-standards/) | PSR-4, backed enums, `Throwable`, forbidden patterns |
| [Error System](./spec/07-error-manage/) | Cross-stack error handling, modal UI, response envelope |
| [WordPress Plugin](./spec/08-wordpress-plugin/) | Plugin architecture and REST API design |
| [Plugin Development](./spec/09-wordpress-plugin-development/) | Development workflow and testing |
| [Feedback/Report](./spec/10-feedback-report-feature/) | Bug report submission feature |
| [WP Plugin Publish](./spec/10-wp-plugin-publish/) | Dashboard and publish pipeline |
| [Upload Scripts](./spec/11-upload-scripts/) | PowerShell upload script specs |
| [PowerShell Integration](./spec/12-powershell-integration/) | Runner spec, config schema, script reference |
| [Activity Feed](./spec/13-e2-activity-feed/) | Enterprise activity feed feature |
| [Generic Enforce](./spec/14-generic-enforce/) | GE-5 type-safety enforcement rules |
| [QUpload Plugin](./spec/15-qupload-plugin/) | QUpload endpoints, script, and design |

---

## Author

**MD ALIM UL KARIM**

- Profile: [rasia.pro](https://rasia.pro/alim-r-profile-v1)
- Company: Riseup Asia

## License

GPL v2 or later
