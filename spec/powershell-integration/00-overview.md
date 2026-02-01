# PowerShell Integration for Project Runner

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Status:** Active  
> **Location:** `spec/powershell-integration/`  
> **Purpose:** Reusable PowerShell runner for Go backend + React frontend projects

---

## Summary

This specification defines a **cross-project reusable** PowerShell integration pattern for building and running fullstack applications with Go backend and React frontend. The system uses a JSON configuration file to define project-specific paths and settings.

**This spec is NOT project-specific** — it can be used by:
- Spec Management Software
- Link Manager WP Plugin (dev environment)
- Any Go + React fullstack project

---

## User Stories

- As a developer, I want to run a single command to build and start my fullstack app
- As a developer, I want clean build options to reset everything when needed
- As a developer, I want the script to auto-install missing dependencies (Go, Node.js)
- As a developer, I want to configure paths via JSON instead of editing the script
- As a developer, I want firewall rules configured automatically for development

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PowerShell Runner Architecture                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                  │
│   │  run.ps1     │───▶│ powershell.  │───▶│   Project    │                  │
│   │  (Script)    │    │ json config  │    │   Folders    │                  │
│   └──────────────┘    └──────────────┘    └──────────────┘                  │
│          │                   │                    │                          │
│          ▼                   ▼                    ▼                          │
│   ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                  │
│   │  5-Step      │    │  Project-    │    │  Go Backend  │                  │
│   │  Pipeline    │    │  Specific    │    │  + React FE  │                  │
│   │              │    │  Paths       │    │  Running     │                  │
│   └──────────────┘    └──────────────┘    └──────────────┘                  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Pipeline Steps

| Step | Name | Description | Flags |
|------|------|-------------|-------|
| 1 | Git Pull | Sync latest changes | `-SkipPull` to skip |
| 2 | Prerequisites | Check/install Go & npm | Auto-install via winget |
| 3 | Frontend Build | Build React with npm | `-SkipBuild` to skip |
| 4 | Copy Build | Copy dist to backend | Automatic |
| 5 | Start Backend | Run Go server | `-BuildOnly` to skip |

---

## Folder Structure

```
spec/powershell-integration/
├── 00-overview.md               ← This file
├── 01-configuration-schema.md   ← JSON config format
├── 02-script-reference.md       ← CLI flags and functions
├── 03-integration-guide.md      ← How to add to any project
├── 04-error-codes.md            ← Exit codes (9500-9599)
├── 05-firewall-rules.md         ← Windows firewall setup
├── schemas/
│   └── powershell.schema.json   ← JSON Schema for validation
├── templates/
│   ├── run.ps1                  ← Main script template
│   └── powershell.json          ← Example config
└── examples/
    └── server-client-project.json  ← Sample for server/client layout
```

---

## Quick Start

```powershell
# Full build and run
.\run.ps1

# Clean rebuild everything
.\run.ps1 -Force

# Just start backend (skip frontend build)
.\run.ps1 -SkipBuild

# Build only (don't start server)
.\run.ps1 -BuildOnly

# Skip git pull + clean build
.\run.ps1 -SkipPull -Force

# Configure firewall (requires Admin)
.\run.ps1 -OpenFirewall
```

---

## Configuration File

Create `powershell.json` in project root:

```json
{
  "projectName": "my-project",
  "rootDir": ".",
  "backendDir": "go-backend",
  "frontendDir": ".",
  "distDir": "dist",
  "targetDir": "go-backend/frontend/dist",
  "dataDir": "go-backend/data",
  "ports": [8080, 8081],
  "prerequisites": {
    "go": true,
    "node": true,
    "npm": true
  },
  "cleanPaths": [
    "node_modules",
    "dist",
    ".vite",
    "go-backend/data/*.db"
  ]
}
```

---

## Features

### Auto-Install Dependencies

- **Go**: Installs via `winget install GoLang.Go` if missing
- **Node.js**: Installs via `winget install OpenJS.NodeJS.LTS` if missing
- **npm**: Included with Node.js

### Force Clean Build

The `-Force` flag removes:
- `node_modules/` directory
- `dist/` directory
- `.vite/` cache
- SQLite databases (`*.db`, `*.db-shm`, `*.db-wal`)

### Firewall Configuration

The `-OpenFirewall` flag (requires Administrator):
- Creates inbound rules for configured ports
- Sets profile to Private and Domain
- Names rules consistently for easy management

---

## Using in Projects

### For Spec Management Software

Reference this spec from the main project:
```markdown
See [PowerShell Integration](../../powershell-integration/00-overview.md) for build/run scripts.
```

### For Link Manager

Reference from Link Manager project:
```markdown
See [PowerShell Integration](../../../spec/powershell-integration/00-overview.md) for build scripts.
```

### For New Projects

1. Copy `templates/run.ps1` to project root
2. Create `powershell.json` with project-specific paths
3. Run `.\run.ps1 -Help` to verify

---

## AI Handoff Instructions

To integrate this PowerShell runner into any project, share:

```
spec/powershell-integration/
```

Tell the AI:
> "Follow the spec at `spec/powershell-integration/` to add the PowerShell build runner. Create a `powershell.json` config for my project structure."

---

## Cross-References

| Project | Reference |
|---------|-----------|
| Spec Management | `spec/spec-management-software/` uses this for dev builds |
| Link Manager | `link-manager/` can use this for WordPress dev |
| General Spec | `spec/general-spec/` patterns followed here |

---

*This spec enables consistent, reproducible builds across all fullstack projects.*
