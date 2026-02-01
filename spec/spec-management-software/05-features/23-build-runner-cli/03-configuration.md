# Configuration

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Configuration schema and options for the Build Runner CLI (`brun`). Uses JSON format for consistency with the gsearch CLI.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [Build Profiles](./07-build-profiles.md)
- [Asset Operations](./08-asset-operations.md)

---

## Configuration File Location

Default search order:
1. `--config` flag value
2. `./config.json` (current directory)
3. `~/.brun/config.json` (user home)
4. `/etc/brun/config.json` (system-wide, Linux/macOS)

---

## Complete Schema

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "brun-config-schema",
  "title": "Build Runner CLI Configuration",
  "type": "object",
  "properties": {
    "version": {
      "type": "string",
      "description": "Configuration schema version",
      "default": "1.0.0"
    },
    "runtimes": {
      "type": "object",
      "description": "Runtime executable paths",
      "properties": {
        "powershell": {
          "type": "object",
          "properties": {
            "path": { "type": "string", "default": "pwsh" },
            "args": { "type": "array", "items": { "type": "string" }, "default": ["-NoProfile", "-NonInteractive"] }
          }
        },
        "nodejs": {
          "type": "object",
          "properties": {
            "path": { "type": "string", "default": "node" },
            "packageManager": { "type": "string", "enum": ["npm", "yarn", "bun"], "default": "npm" }
          }
        },
        "golang": {
          "type": "object",
          "properties": {
            "path": { "type": "string", "default": "go" },
            "modTidy": { "type": "string", "enum": ["skip", "run", "force"], "default": "run" },
            "buildFlags": { "type": "array", "items": { "type": "string" }, "default": ["-v"] }
          }
        }
      }
    },
    "ports": {
      "type": "object",
      "description": "Port management configuration",
      "properties": {
        "default": { "type": "integer", "default": 8080 },
        "fallback": {
          "type": "array",
          "items": { "type": "integer" },
          "default": [8081, 8082, 8083, 8084, 8085]
        },
        "checkTimeout": { "type": "string", "default": "5s" },
        "firewall": {
          "type": "object",
          "properties": {
            "enabled": { "type": "boolean", "default": false },
            "autoEnable": { "type": "boolean", "default": false },
            "ruleName": { "type": "string", "default": "brun" }
          }
        }
      }
    },
    "logging": {
      "type": "object",
      "description": "Logging configuration",
      "properties": {
        "enabled": { "type": "boolean", "default": true },
        "directory": { "type": "string", "default": "./logs" },
        "createRunFolders": { "type": "boolean", "default": true },
        "keepRuns": { "type": "integer", "default": 50 },
        "files": {
          "type": "object",
          "properties": {
            "stdout": { "type": "string", "default": "log.txt" },
            "stderr": { "type": "string", "default": "error.txt" },
            "combined": { "type": "string", "default": "combined.txt" }
          }
        },
        "includeStackTrace": { "type": "boolean", "default": true }
      }
    },
    "output": {
      "type": "object",
      "description": "Output configuration",
      "properties": {
        "format": { "type": "string", "enum": ["text", "json"], "default": "text" },
        "colorEnabled": { "type": "boolean", "default": true },
        "timestamps": { "type": "boolean", "default": true },
        "jsonPretty": { "type": "boolean", "default": true }
      }
    },
    "workDirectory": {
      "type": "string",
      "description": "Base working directory for all operations. Can be overridden to work on any directory.",
      "default": "."
    },
    "allowExternalDirs": {
      "type": "boolean",
      "description": "Allow working on directories outside the default workDirectory",
      "default": false
    },
    "execution": {
      "type": "object",
      "description": "Execution defaults",
      "properties": {
        "timeout": { "type": "string", "default": "5m" },
        "concurrent": { "type": "boolean", "default": true },
        "maxRetries": { "type": "integer", "default": 0 },
        "retryDelay": { "type": "string", "default": "1s" }
      }
    },
    "applications": {
      "type": "array",
      "description": "Named application definitions with health check configuration",
      "items": { "$ref": "#/$defs/applicationDef" }
    },
    "profiles": {
      "type": "array",
      "description": "Saved build profiles",
      "items": { "$ref": "#/$defs/buildProfile" }
    }
  },
  "$defs": {
    "buildProfile": {
      "type": "object",
      "required": ["name"],
      "properties": {
        "name": { "type": "string" },
        "description": { "type": "string" },
        "runtime": { "type": "string", "enum": ["powershell", "nodejs", "golang"] },
        "source": { "type": "string" },
        "output": { "type": "string" },
        "command": { "type": "string" },
        "args": { "type": "array", "items": { "type": "string" } },
        "env": { "type": "object", "additionalProperties": { "type": "string" } },
        "workdir": { "type": "string" },
        "timeout": { "type": "string" },
        "preCommands": { "type": "array", "items": { "type": "string" } },
        "postCommands": { "type": "array", "items": { "type": "string" } },
        "assets": { "$ref": "#/$defs/assetConfig" },
        "port": { "type": "integer" }
      }
    },
    "assetConfig": {
      "type": "object",
      "properties": {
        "enabled": { "type": "boolean", "default": false },
        "operations": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "source": { "type": "string" },
              "destination": { "type": "string" },
              "mode": { "type": "string", "enum": ["copy", "clear-copy", "override", "skip-existing"] },
              "pattern": { "type": "string" },
              "exclude": { "type": "array", "items": { "type": "string" } }
            }
          }
        }
      }
    },
    "applicationDef": {
      "type": "object",
      "required": ["name"],
      "description": "Named application definition for health checks and monitoring",
      "properties": {
        "name": { "type": "string", "description": "Unique application identifier" },
        "description": { "type": "string" },
        "healthCheck": {
          "type": "object",
          "properties": {
            "enabled": { "type": "boolean", "default": true },
            "url": { "type": "string", "description": "Health check URL (e.g., http://localhost:8080/health)" },
            "host": { "type": "string", "default": "localhost" },
            "port": { "type": "integer" },
            "path": { "type": "string", "default": "/" },
            "method": { "type": "string", "enum": ["GET", "HEAD"], "default": "GET" },
            "timeout": { "type": "string", "default": "5s" },
            "interval": { "type": "string", "default": "1s" },
            "retries": { "type": "integer", "default": 30 },
            "expectedStatus": { "type": "array", "items": { "type": "integer" }, "default": [200] },
            "expectedBody": { "type": "string", "description": "Expected response body substring" }
          }
        },
        "profile": { "type": "string", "description": "Associated build profile name" },
        "ports": {
          "type": "object",
          "properties": {
            "primary": { "type": "integer" },
            "fallback": { "type": "array", "items": { "type": "integer" } }
          }
        },
        "workdir": { "type": "string", "description": "Custom working directory for this application" }
      }
    }
  }
}
```

---

## Example Configuration

```json
{
  "version": "1.0.0",
  "runtimes": {
    "powershell": {
      "path": "pwsh",
      "args": ["-NoProfile", "-NonInteractive"]
    },
    "nodejs": {
      "path": "node",
      "packageManager": "bun"
    },
    "golang": {
      "path": "go",
      "modTidy": "run",
      "buildFlags": ["-v", "-ldflags", "-s -w"]
    }
  },
  "ports": {
    "default": 8080,
    "fallback": [8081, 8082, 8083],
    "checkTimeout": "5s",
    "firewall": {
      "enabled": true,
      "autoEnable": false,
      "ruleName": "brun-app"
    }
  },
  "logging": {
    "enabled": true,
    "directory": "./logs",
    "createRunFolders": true,
    "keepRuns": 50,
    "files": {
      "stdout": "log.txt",
      "stderr": "error.txt",
      "combined": "combined.txt"
    },
    "includeStackTrace": true
  },
  "output": {
    "format": "text",
    "colorEnabled": true,
    "timestamps": true,
    "jsonPretty": true
  },
  "execution": {
    "timeout": "5m",
    "concurrent": true,
    "maxRetries": 0,
    "retryDelay": "1s"
  },
  "workDirectory": ".",
  "allowExternalDirs": false,
  "applications": [
    {
      "name": "backend_api",
      "description": "Go backend API server",
      "healthCheck": {
        "enabled": true,
        "host": "localhost",
        "port": 8080,
        "path": "/health",
        "method": "GET",
        "timeout": "5s",
        "interval": "1s",
        "retries": 30,
        "expectedStatus": [200]
      },
      "profile": "backend-api",
      "ports": {
        "primary": 8080,
        "fallback": [8081, 8082]
      }
    },
    {
      "name": "frontend_dev",
      "description": "React frontend development server",
      "healthCheck": {
        "enabled": true,
        "host": "localhost",
        "port": 3000,
        "path": "/",
        "timeout": "10s",
        "retries": 60,
        "expectedStatus": [200, 304]
      },
      "profile": "frontend",
      "ports": {
        "primary": 3000,
        "fallback": [3001, 3002]
      }
    }
  ],
  "profiles": [
    {
      "name": "backend-api",
      "description": "Build the Go backend API",
      "runtime": "golang",
      "source": "./cmd/api",
      "output": "./bin/api",
      "preCommands": ["go mod tidy"],
      "env": {
        "CGO_ENABLED": "1",
        "GOOS": "linux"
      },
      "port": 8080
    },
    {
      "name": "frontend",
      "description": "Build React frontend",
      "runtime": "nodejs",
      "source": "./frontend",
      "command": "build",
      "assets": {
        "enabled": true,
        "operations": [
          {
            "source": "./frontend/dist",
            "destination": "./public",
            "mode": "clear-copy"
          }
        ]
      }
    },
    {
      "name": "deploy-script",
      "description": "Run deployment PowerShell script",
      "runtime": "powershell",
      "source": "./scripts/deploy.ps1",
      "args": ["-Environment", "production"]
    }
  ]
}
```

---

## Environment Variable Overrides

All configuration values can be overridden via environment variables with the `BRUN_` prefix:

| Config Key | Environment Variable |
|------------|---------------------|
| `runtimes.golang.path` | `BRUN_RUNTIMES_GOLANG_PATH` |
| `ports.default` | `BRUN_PORTS_DEFAULT` |
| `logging.directory` | `BRUN_LOGGING_DIRECTORY` |
| `output.format` | `BRUN_OUTPUT_FORMAT` |

---

## Configuration Keys Summary

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `version` | string | `1.0.0` | Schema version |
| `runtimes.powershell.path` | string | `pwsh` | PowerShell executable |
| `runtimes.powershell.args` | array | `["-NoProfile", "-NonInteractive"]` | Default PS args |
| `runtimes.nodejs.path` | string | `node` | Node.js executable |
| `runtimes.nodejs.packageManager` | string | `npm` | npm, yarn, or bun |
| `runtimes.golang.path` | string | `go` | Go executable |
| `runtimes.golang.modTidy` | string | `run` | skip, run, force |
| `runtimes.golang.buildFlags` | array | `["-v"]` | Go build flags |
| `ports.default` | int | `8080` | Default port |
| `ports.fallback` | array | `[8081-8085]` | Fallback ports |
| `ports.checkTimeout` | duration | `5s` | Port check timeout |
| `ports.firewall.enabled` | bool | `false` | Enable firewall ops |
| `ports.firewall.autoEnable` | bool | `false` | Auto-add rules |
| `logging.enabled` | bool | `true` | Enable file logging |
| `logging.directory` | string | `./logs` | Log directory |
| `logging.createRunFolders` | bool | `true` | Create per-run folders |
| `logging.keepRuns` | int | `50` | Max runs to keep |
| `logging.includeStackTrace` | bool | `true` | Include stack traces |
| `output.format` | string | `text` | text or json |
| `output.colorEnabled` | bool | `true` | Color output |
| `execution.timeout` | duration | `5m` | Default timeout |
| `execution.concurrent` | bool | `true` | Concurrent checks |

---

## See Also

- [Build Profiles](./07-build-profiles.md)
- [Asset Operations](./08-asset-operations.md)
- [Port Management](./05-port-management.md)
