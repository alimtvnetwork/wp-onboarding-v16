# WP Plugin Publish

A full-stack WordPress plugin deployment system — React dashboard, Go backend orchestrator, PHP WordPress plugins, and PowerShell automation scripts. Designed for managing plugin deployments across multiple WordPress sites with version tracking, delta sync, and one-command publishing.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        WP Plugin Publish                            │
├─────────────┬──────────────┬──────────────────┬─────────────────────┤
│  React UI   │  Go Backend  │  WordPress Sites │  PowerShell CLI     │
│  (Vite/TS)  │  (REST+WS)   │  (PHP Plugins)   │  (Automation)       │
├─────────────┼──────────────┼──────────────────┼─────────────────────┤
│ Dashboard   │ Orchestrator │ Riseup Asia      │ run.ps1             │
│ Plugin mgmt │ SQLite DB    │ Uploader (REST)  │ upload-plugin-v2    │
│ Live logs   │ WebSocket    │ QUpload (REST)   │ upload-plugin-U-Q   │
│ Version     │ Crypto       │ Plugins Onboard  │ bump-version        │
│ history     │ Publishing   │                  │                     │
└─────────────┴──────────────┴──────────────────┴─────────────────────┘
```

---

## Tech Stack

| Layer | Technology | Directory |
|-------|-----------|-----------|
| **Frontend** | React 18 · TypeScript · Vite · Tailwind CSS · shadcn/ui · Zustand | `src/` |
| **Backend** | Go 1.21+ · REST API · WebSocket · SQLite · AES-256 encryption | `backend/` |
| **WordPress Plugins** | PHP 8.1+ · PSR-4 · REST API · WordPress Application Passwords | `wp-plugins/` |
| **Automation** | PowerShell 5.1+ · Self-linting · JSON config · Semantic versioning | `run.ps1`, `wp-plugins/scripts/` |
| **Specifications** | Markdown specs for all coding standards and architecture | `spec/` |

---

## Prerequisites

- **Windows** (required for PowerShell deployment scripts)
- [Node.js](https://nodejs.org/) 18+ with [pnpm](https://pnpm.io/)
- [Go](https://go.dev/) 1.21+
- [PowerShell](https://learn.microsoft.com/en-us/powershell/) 5.1+ (ships with Windows)

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
│   │   ├── services/                 # Business logic (publish, sync, etc.)
│   │   ├── wordpress/                # PowerShell integration (Go ↔ PS1)
│   │   ├── ws/                       # WebSocket hub for live logs
│   │   ├── crypto/                   # AES-256 credential encryption
│   │   └── envelope/                 # Response envelope (universal JSON schema)
│   └── pkg/                          # Shared packages (apperror, pathutil)
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
├── spec/                             # Technical specifications (30+ docs)
│   ├── 03-coding-guidelines/         # DRY, strict typing, naming rules
│   ├── 04-typescript-standards/      # Zero-any policy, catch narrowing
│   ├── 05-golang-standards/          # No interface{}, typed structs
│   ├── 06-php-standards/             # PSR-4, backed enums, Throwable
│   ├── 07-error-manage/              # Cross-stack error handling
│   ├── 12-powershell-integration/    # PowerShell runner spec
│   └── 15-qupload-plugin/           # QUpload plugin spec
│
├── run.ps1                           # Main PowerShell runner (all-in-one CLI)
├── powershell.json                   # Runner configuration
└── public/version.json               # Synchronized version tracking
```

---

## WordPress Plugins

### Riseup Asia Uploader

The main deployment plugin. Provides a full REST API for remote plugin management, delta file sync, blog post publishing, and audit logging.

| Property | Value |
|----------|-------|
| **Namespace** | `RiseupAsia\` |
| **REST API** | `/wp-json/riseup-asia-uploader/v1/` |
| **Auth** | WordPress Application Passwords |
| **Min PHP** | 8.1 |

**Key endpoints:** `/upload`, `/status`, `/activate`, `/sync`, `/posts`, `/categories`

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

## PowerShell CLI Reference (`run.ps1`)

The `run.ps1` script is the single entry point for all operations: building, running, deploying, zipping, and testing.

### Build & Run Flags

| Flag | Description |
|------|-------------|
| `(none)` | Full pipeline: git pull → prerequisites → build → run |
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
| `-ua` / `-uploadall` | ZIP + upload **all** plugins (except QUpload) via QUpload API |
| `-d` / `-debug` | Enable debug logging for uploads |
| `-pp <path>` | Override plugin folder path |

### ZIP Flags

| Flag | Description |
|------|-------------|
| `-z` / `-zip` | ZIP default Riseup Asia plugin (or `-pp` specific plugin) |
| `-za` | ZIP **all** plugins in `wp-plugins/` |
| `-zq` / `-zipqupload` | ZIP QUpload plugin |
| `-c` / `-clear` | Remove existing ZIPs before creating new ones |

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
.\run.ps1 -ua -c                       # Clear old ZIPs first, then ZIP + upload all

# ── ZIP Only ──
.\run.ps1 -z                           # ZIP default plugin
.\run.ps1 -za                          # ZIP all plugins
.\run.ps1 -zq                          # ZIP QUpload plugin
.\run.ps1 -z -c                        # Clear old ZIPs, then ZIP default
.\run.ps1 -za -c                       # Clear old ZIPs, then ZIP all

# ── Version Bump ──
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch
.\wp-plugins\scripts\bump-version.ps1 -Target plugin -Bump minor
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch -DryRun
```

---

## Upload Workflow

### How `-ua` (Upload All) Works

1. Scans `wp-plugins/` for directories with valid WordPress plugin headers
2. **Excludes QUpload** (it's the upload transport, not a target)
3. For each plugin:
   - Creates a versioned ZIP archive (best compression)
   - Uploads via QUpload's `POST /wp-json/qupload-api/v1/upload`
   - Activates the plugin after upload
4. Displays a summary with success/failure counts

### When to Use Each Upload Mode

| Scenario | Command |
|----------|---------|
| Plugin's own API is active on target site | `.\run.ps1 -u` |
| First-time install (plugin API not available) | `.\run.ps1 -u -q` |
| Deploy all plugins at once | `.\run.ps1 -ua` |
| Deploy specific plugin via QUpload | `.\run.ps1 -q -pp 'wp-plugins/my-plugin'` |

### Pre-flight Checks

The upload scripts include automatic pre-flight checks:

- **Namespace detection** — Step 6 of `upload-plugin-v2.ps1` checks if the target API namespace is registered on the site
- **QUpload fallback suggestion** — If `riseup-asia-uploader/v1` is missing but `qupload-api/v1` is available, the script aborts with a suggestion to use `.\run.ps1 -u -q`
- **Auth pre-check** — `upload-plugin-U-Q.ps1` hits `GET /status` before uploading to verify credentials and plugin activation

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
    "plugins": {
      "riseup-asia-uploader": {
        "name": "Riseup Asia Uploader",
        "path": "wp-plugins/riseup-asia-uploader",
        "mainFile": "riseup-asia-uploader.php",
        "autoUpload": true
      },
      "qupload": {
        "name": "Quick Upload",
        "path": "wp-plugins/qupload",
        "mainFile": "qupload.php",
        "autoUpload": false
      }
    }
  }
}
```

### `wp-plugins/scripts/wp-plugin-config.json`

Site credentials for Riseup Asia Uploader API uploads:

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

### `wp-plugins/scripts/qupload-config.json`

Site credentials for QUpload API uploads:

```json
{
  "pluginFolderPath": "wp-plugins/qupload",
  "wordPressSiteURL": "https://your-site.com",
  "username": "admin",
  "appPassword": "xxxx xxxx xxxx xxxx xxxx xxxx",
  "activateAfterInstall": true,
  "deleteZipAfterUpload": false
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
- **Response envelope** — Universal JSON Schema shared across all stacks
- **Self-linting scripts** — All PowerShell scripts validate their own syntax before execution
- **Pre-flight checks** — Upload scripts verify API availability and auth before attempting transfers
- **UTF-8 no BOM** — All PowerShell scripts must use UTF-8 encoding with straight ASCII quotes

---

## Specifications

All coding standards and architecture decisions are documented in [`spec/`](./spec/readme.md):

| Spec | Description |
|------|-------------|
| [Coding Guidelines](./spec/03-coding-guidelines/) | DRY principles, strict typing, naming rules |
| [TypeScript Standards](./spec/04-typescript-standards/) | Zero-`any` policy, catch narrowing, generic envelopes |
| [Go Standards](./spec/05-golang-standards/) | No `interface{}`, typed structs, error diagnostics |
| [PHP Standards](./spec/06-php-standards/) | PSR-4, backed enums, `Throwable`, forbidden patterns |
| [Error System](./spec/07-error-manage/) | Cross-stack error handling, modal UI, response envelope |
| [PowerShell Integration](./spec/12-powershell-integration/) | Runner spec, config schema, script reference |
| [QUpload Plugin](./spec/15-qupload-plugin/) | QUpload endpoints, script, and design |

---

## Author

**MD ALIM UL KARIM**

- Profile: [rasia.pro](https://rasia.pro/alim-r-profile-v1)
- Company: Riseup Asia

## License

GPL v2 or later
