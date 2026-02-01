# CLI Interface

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Command-line interface specification for the Build Runner CLI (`brun`). Defines all commands, flags, and usage patterns.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [Configuration](./03-configuration.md)
- [Runtime Executors](./04-runtime-executors.md)

---

## Global Flags

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--config` | `-c` | string | `./config.json` | Path to configuration file |
| `--json` | `-j` | bool | false | Output results as JSON |
| `--verbose` | `-v` | bool | false | Enable verbose output |
| `--quiet` | `-q` | bool | false | Suppress non-error output |
| `--log-dir` | | string | `./logs` | Directory for log files |
| `--no-color` | | bool | false | Disable colored output |

---

## Commands

### 1. `brun` (Root)

Display help and version information.

```bash
brun --help
brun --version
```

---

### 2. `brun build`

Execute a build using a saved profile or inline parameters.

```bash
# Run saved build profile
brun build --profile backend-api

# Run with inline parameters
brun build --go ./cmd/app --output ./bin/app

# Run with asset copying
brun build --profile frontend --copy-assets
```

**Flags:**

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--profile` | `-p` | string | | Build profile name from config |
| `--go` | | string | | Go source path |
| `--node` | `-n` | string | | Node.js project path |
| `--ps` | | string | | PowerShell script path |
| `--output` | `-o` | string | `./build` | Output directory |
| `--copy-assets` | | bool | false | Copy assets after build |
| `--clean` | | bool | false | Clean output directory first |
| `--timeout` | `-t` | duration | `5m` | Build timeout |

---

### 3. `brun check`

Check for build errors without producing output artifacts.

```bash
# Check Go build
brun check --go ./cmd/app

# Check with go mod tidy
brun check --go ./cmd/app --tidy

# Check Node.js build
brun check --node ./frontend

# Check and output JSON
brun check --go ./cmd/app --json
```

**Flags:**

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--go` | | string | | Go source path to check |
| `--node` | `-n` | string | | Node.js project path |
| `--ps` | | string | | PowerShell script to validate |
| `--tidy` | | string | `skip` | go mod tidy: skip, run, force |
| `--concurrent` | | bool | true | Run check concurrently |
| `--port` | | int | | Port to check availability |
| `--timeout` | `-t` | duration | `2m` | Check timeout |

**Tidy Options:**
- `skip`: Do not run go mod tidy
- `run`: Run go mod tidy if go.mod exists
- `force`: Always run go mod tidy, fail if errors

---

### 4. `brun run`

Execute a command or script directly.

```bash
# Run PowerShell script
brun run -ps "scripts/deploy.ps1"

# Run PowerShell inline command
brun run -ps "Get-Process | Where-Object {$_.CPU -gt 100}"

# Run Node.js script
brun run --node "npm run build"

# Run Go application
brun run --go "./cmd/server" --port 8080

# Run with health check (wait for localhost:8080 to respond)
brun run --go "./cmd/server" --health-check "localhost:8080/health" --health-timeout 60s

# Run named application from config
brun run --app backend-api

# Run in external directory
brun run --go "./cmd/app" --workdir "/other/project/path"
```

**Flags:**

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--ps` | | string | | PowerShell script or command |
| `--node` | `-n` | string | | Node.js command (npm, yarn, bun) |
| `--go` | | string | | Go application path |
| `--port` | | int | | Port to use (with fallback) |
| `--env` | `-e` | stringArray | | Environment variables (KEY=VALUE) |
| `--workdir` | `-w` | string | `.` | Working directory |
| `--timeout` | `-t` | duration | `0` | Timeout (0 = no timeout) |
| `--detach` | `-d` | bool | false | Run in background |
| `--health-check` | | string | | Health check URL (e.g., localhost:8080) |
| `--health-timeout` | | duration | `30s` | Wait for health check to pass |
| `--app` | `-a` | string | | Named application from config |

---

### 5. `brun port`

Manage port availability and firewall rules.

```bash
# Check if port is available
brun port --check 8080

# Check with fallback ports
brun port --check 8080 --fallback 8081,8082,8083

# Enable port in firewall
brun port --enable 8080 --name "My App"

# Disable port in firewall
brun port --disable 8080

# List firewall rules
brun port --list
```

**Flags:**

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--check` | | int | | Port to check availability |
| `--fallback` | `-f` | string | | Comma-separated fallback ports |
| `--enable` | | int | | Port to enable in firewall |
| `--disable` | | int | | Port to disable in firewall |
| `--name` | | string | `brun` | Firewall rule name |
| `--list` | `-l` | bool | false | List firewall rules |
| `--protocol` | | string | `tcp` | Protocol: tcp, udp, both |

---

### 6. `brun config`

Manage configuration and build profiles.

```bash
# Show current configuration
brun config show

# Validate configuration
brun config validate

# Add build profile
brun config add-profile --name backend-api --go ./cmd/api

# Remove build profile
brun config remove-profile --name old-profile

# Set configuration value
brun config set logging.enabled true

# Initialize default config
brun config init
```

**Flags:**

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--show` | `-s` | bool | false | Display current config |
| `--validate` | | bool | false | Validate config file |
| `--add-profile` | | bool | false | Add new build profile |
| `--remove-profile` | | bool | false | Remove build profile |
| `--name` | | string | | Profile name |
| `--go` | | string | | Go source for profile |
| `--node` | | string | | Node.js path for profile |
| `--set` | | stringArray | | Set config value (key=value) |
| `--init` | | bool | false | Create default config |

---

## Usage Examples

### AI-Assisted Error Fixing Loop

```bash
# Main application calls brun to check build
brun check --go ./generated/app --json --tidy run

# Output (JSON):
{
  "runId": "run_20260129_143052",
  "success": false,
  "exitCode": 1,
  "errors": [
    {
      "file": "main.go",
      "line": 15,
      "column": 10,
      "message": "undefined: SomeFunction",
      "severity": "error"
    }
  ]
}

# Main app reads JSON, feeds to AI, AI fixes code, then retries
brun check --go ./generated/app --json --tidy run
# Repeat until success
```

### Multi-Port Fallback

```bash
# Check port and get available one
brun port --check 8080 --fallback 8081,8082,8083 --json

# Output:
{
  "requestedPort": 8080,
  "availablePort": 8081,
  "checkedPorts": [
    {"port": 8080, "available": false, "reason": "in use by process 1234"},
    {"port": 8081, "available": true}
  ]
}
```

### Complete Build with Assets

```bash
# Full build process
brun build \
  --profile production \
  --clean \
  --copy-assets \
  --timeout 10m \
  --json
```

---

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Build/check failed with errors |
| 2 | Configuration error |
| 3 | Runtime not found |
| 4 | Port unavailable (no fallback) |
| 5 | Timeout exceeded |
| 6 | Permission denied |
| 7 | File/path not found |
| 126 | Command not executable |
| 127 | Command not found |
| 130 | Interrupted (SIGINT) |
| 143 | Terminated (SIGTERM) |

---

## See Also

- [Configuration](./03-configuration.md)
- [Runtime Executors](./04-runtime-executors.md)
- [Error Handling](./06-error-handling.md)
