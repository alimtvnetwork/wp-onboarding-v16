# WP Plugin Publish

A full-stack application for managing WordPress plugin deployments across multiple sites — featuring a React dashboard, Go backend orchestrator, and WordPress companion plugin.

## Tech Stack

| Layer | Technology | Directory |
|-------|-----------|-----------|
| **Frontend** | React 18 · TypeScript · Vite · Tailwind CSS · shadcn/ui | `src/` |
| **Backend** | Go (orchestrator) · REST API · WebSocket · SQLite | `backend/` |
| **WordPress Plugin** | PHP 8.2+ · PSR-4 · REST API · SQLite audit logs | `wp-plugins/riseup-asia-uploader/` |
| **Specifications** | Markdown specs for all coding standards and architecture | `spec/` |
| **Scripts** | PowerShell deployment and upload automation | `scripts/`, `run.ps1` |

---

## Getting Started

### Prerequisites

- [Node.js](https://nodejs.org/) 18+ (or [Bun](https://bun.sh/))
- [Go](https://go.dev/) 1.21+
- [PowerShell](https://learn.microsoft.com/en-us/powershell/) 7+ (for deployment scripts)

### Clone & Install

```sh
# Clone the repository
git clone <YOUR_GIT_URL>
cd <YOUR_PROJECT_NAME>

# Install frontend dependencies
npm install

# Start the React development server
npm run dev
```

### Start the Go Backend

```sh
cd backend
go run ./cmd/server
```

### PowerShell Runner (All-in-One)

```powershell
# Start both Go backend and React frontend
./run.ps1 -r

# Start with debug mode
./run.ps1 -r -DebugMode
```

---

## Project Structure

```
├── src/                          # React frontend
│   ├── components/               # UI components (shadcn/ui based)
│   ├── pages/                    # Route pages
│   ├── lib/                      # API client, utilities, constants
│   ├── stores/                   # Zustand state management
│   └── hooks/                    # Custom React hooks
├── backend/                      # Go backend
│   ├── cmd/server/               # Entry point
│   └── internal/                 # Domain packages
├── wp-plugins/
│   └── riseup-asia-uploader/     # WordPress companion plugin
│       ├── riseup-asia-uploader.php  # Entry point (autoloader only)
│       └── includes/             # PSR-4 root (RiseupAsia\ namespace)
├── spec/                         # Technical specifications
│   ├── 01-coding-guidelines/     # DRY, strict typing, naming rules
│   ├── 02-typescript-standards/  # TypeScript-specific standards
│   ├── 03-golang-standards/      # Go-specific standards
│   ├── 04-php-standards/         # PHP standards and forbidden patterns
│   ├── 05-error-manage/          # Error handling, modal, logging, envelope
│   ├── 06-wordpress-plugin/      # WP plugin features (snapshots, updates)
│   ├── 07-wordpress-plugin-development/  # Plugin dev workflow and coding guidelines
│   ├── 08-wp-plugin-publish/     # Publishing pipeline spec
│   ├── 09-upload-scripts/        # PowerShell upload scripts (V1–V3)
│   ├── 10-powershell-integration/# PowerShell runner spec
│   ├── 11-e2-activity-feed/      # Activity audit log spec
│   └── 12-generic-enforce/       # Cross-language type enforcement
├── scripts/                      # Linting and automation scripts
└── run.ps1                       # PowerShell project runner
```

---

## Specifications

All coding standards, architecture decisions, and feature specifications are maintained in [`spec/readme.md`](./spec/readme.md).

### Key Specs

| Spec | Description |
|------|-------------|
| [Coding Guidelines](./spec/01-coding-guidelines/) | DRY principles, strict typing, no raw negations, function naming |
| [TypeScript Standards](./spec/02-typescript-standards/readme.md) | Zero-`any` policy, catch block narrowing, generic envelopes |
| [Go Standards](./spec/03-golang-standards/readme.md) | No `interface{}`, typed structs, error diagnostic pattern |
| [PHP Standards](./spec/04-php-standards/readme.md) | PSR-4, backed enums, `Throwable`, forbidden patterns |
| [Error System](./spec/05-error-manage/) | Cross-stack error handling, modal UI, response envelope |
| [WP Plugin Dev](./spec/07-wordpress-plugin-development/00-overview.md) | Plugin development workflow, coding guidelines, Phase 7 report |

---

## Development Workflow

### Frontend

```sh
npm run dev       # Start Vite dev server
npm run build     # Production build
npm run lint      # ESLint check
```

### WordPress Plugin Deployment

```powershell
# Upload plugin to a site
./run.ps1 -u -SiteName "my-site"

# Publish with version bump
./run.ps1 -p -SiteName "my-site"
```

### Lovable

This project is also editable via [Lovable](https://lovable.dev). Changes made in Lovable auto-sync with GitHub and vice versa.

---

## Key Architecture Decisions

- **Zero `any`/`interface{}`** — All three stacks enforce strict typing with no type erasure
- **Backed enums everywhere** — PHP, Go, and TypeScript use typed enums for all constants
- **PSR-4 namespacing** — WordPress plugin has 252 files under `RiseupAsia\`, zero global classes
- **Response envelope** — Universal JSON Schema (v1.0.0) shared across all stacks
- **Circuit breakers** — All polling/health checks wrapped to prevent cascade failures
- **No auto-retry** — React Query retries and window-focus refetching are banned

---

## Author

**MD ALIM UL KARIM**

- Profile: https://rasia.pro/alim-r-profile-v1
- Company: Riseup Asia

## License

GPL v2 or later
