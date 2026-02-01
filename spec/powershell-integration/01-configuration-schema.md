# PowerShell Runner Configuration Schema

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Status:** Active

---

## Overview

The `powershell.json` configuration file defines project-specific paths and settings for the PowerShell build runner. This allows a single generic script to work across multiple projects.

---

## Schema Definition

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "PowerShell Runner Configuration",
  "type": "object",
  "required": ["projectName", "backendDir"],
  "properties": {
    "projectName": {
      "type": "string",
      "description": "Display name for the project",
      "examples": ["LLM Runner", "Spec Management", "My App"]
    },
    "rootDir": {
      "type": "string",
      "default": ".",
      "description": "Root directory of the project (relative to script location)"
    },
    "backendDir": {
      "type": "string",
      "description": "Path to Go backend directory",
      "examples": ["go-backend", "backend", "server"]
    },
    "frontendDir": {
      "type": "string",
      "default": ".",
      "description": "Path to React frontend directory (where package.json lives)"
    },
    "distDir": {
      "type": "string",
      "default": "dist",
      "description": "Frontend build output directory (relative to frontendDir)"
    },
    "targetDir": {
      "type": "string",
      "description": "Where to copy built frontend for serving by backend",
      "examples": ["go-backend/frontend/dist", "server/static"]
    },
    "dataDir": {
      "type": "string",
      "description": "Data directory for databases and storage",
      "examples": ["go-backend/data", "data"]
    },
    "ports": {
      "type": "array",
      "items": {"type": "integer"},
      "default": [8080],
      "description": "Ports to open in Windows Firewall"
    },
    "prerequisites": {
      "type": "object",
      "properties": {
        "go": {"type": "boolean", "default": true},
        "node": {"type": "boolean", "default": true},
        "npm": {"type": "boolean", "default": true}
      },
      "description": "Which prerequisites to check/install"
    },
    "cleanPaths": {
      "type": "array",
      "items": {"type": "string"},
      "description": "Paths to remove on -Force clean build",
      "examples": [["node_modules", "dist", ".vite", "go-backend/data/*.db"]]
    },
    "buildCommand": {
      "type": "string",
      "default": "npm run build",
      "description": "Command to build frontend"
    },
    "runCommand": {
      "type": "string",
      "default": "go run main.go",
      "description": "Command to start backend"
    },
    "seedingDir": {
      "type": "string",
      "description": "Directory for seed data files"
    },
    "configFile": {
      "type": "string",
      "default": "config.json",
      "description": "Backend config file name"
    },
    "configExampleFile": {
      "type": "string",
      "default": "config.example.json",
      "description": "Template config file to copy if config missing"
    }
  }
}
```

---

## Example Configurations

### Minimal Configuration

```json
{
  "projectName": "My App",
  "backendDir": "go-backend"
}
```

Uses all defaults for a standard Go + React setup.

### Full Configuration

```json
{
  "projectName": "LLM Runner",
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
    "go-backend/data/*.db",
    "go-backend/data/*.db-shm",
    "go-backend/data/*.db-wal"
  ],
  "buildCommand": "npm run build",
  "runCommand": "go run main.go",
  "seedingDir": "go-backend/data/seeding",
  "configFile": "config.json",
  "configExampleFile": "config.example.json"
}
```

### Monorepo Configuration

```json
{
  "projectName": "Monorepo App",
  "rootDir": ".",
  "backendDir": "packages/server",
  "frontendDir": "packages/web",
  "distDir": "build",
  "targetDir": "packages/server/public",
  "dataDir": "packages/server/data",
  "ports": [3000, 3001],
  "buildCommand": "npm run build:web",
  "runCommand": "go run cmd/server/main.go"
}
```

### Bun Configuration

```json
{
  "projectName": "Bun App",
  "backendDir": "go-backend",
  "frontendDir": ".",
  "prerequisites": {
    "go": true,
    "node": false,
    "bun": true
  },
  "buildCommand": "bun run build"
}
```

---

## Path Resolution

All paths are resolved relative to the script location (`$MyInvocation.MyCommand.Path`).

```
project-root/
├── run.ps1              ← Script location (RootDir base)
├── powershell.json      ← Config file
├── package.json         ← Frontend (frontendDir: ".")
├── dist/                ← Build output (distDir: "dist")
└── go-backend/          ← Backend (backendDir: "go-backend")
    ├── main.go
    ├── config.json
    ├── frontend/
    │   └── dist/        ← Target (targetDir)
    └── data/            ← Data (dataDir)
        └── *.db
```

---

## Environment Variables

The config can use environment variable expansion:

```json
{
  "projectName": "{{PROJECT_NAME}}",
  "dataDir": "{{DATA_PATH}}/data"
}
```

Script resolves with:
```powershell
$config.dataDir = $config.dataDir -replace '\{\{(\w+)\}\}', { [System.Environment]::GetEnvironmentVariable($matches[1]) }
```

---

## Validation

The script validates config on load:

```powershell
function Validate-Config($config) {
    if (-not $config.projectName) {
        throw "powershell.json: projectName is required"
    }
    if (-not $config.backendDir) {
        throw "powershell.json: backendDir is required"
    }
    if (-not (Test-Path (Join-Path $RootDir $config.backendDir))) {
        throw "powershell.json: backendDir '$($config.backendDir)' not found"
    }
}
```

---

## Cross-References

- [Script Reference](./02-script-reference.md) - How the script uses this config
- [Integration Guide](./03-integration-guide.md) - Setup instructions
