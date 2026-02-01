# Deployment Guide: brun CLI

**Parent:** [Build Runner CLI](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Overview

Simple local deployment guide for the brun CLI tool. This guide focuses on running the Go binary locally without complex infrastructure.

**Design Philosophy:** Keep it simple — build, configure, run.

**Cross-References:**
- [Configuration](./03-configuration.md) — Config file management
- [Error Handling](./06-error-handling.md) — Error codes reference
- [Implementation Guide](./14-implementation-guide.md) — Build setup
- [Observability](./15-observability.md) — Metrics and health checks
- [gsearch Deployment](../22-golang-search-cli/17-deployment-guide.md) — Reference deployment guide

---

## Quick Start

```bash
# Build
go build -o brun ./main.go

# Configure
cp configs/config.development.json ./config.json

# Run
./brun build --profile backend-api
```

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Build Process](#build-process)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Database Setup](#database-setup)
6. [Running the Application](#running-the-application)
7. [Runtime Dependencies](#runtime-dependencies)
8. [Logging](#logging)
9. [Health Checks](#health-checks)
10. [Backup & Maintenance](#backup--maintenance)
11. [Troubleshooting](#troubleshooting)
12. [Platform-Specific Notes](#platform-specific-notes)

---

## Prerequisites

### System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| OS | Linux, macOS, Windows | Linux or macOS |
| CPU | 2 cores | 4 cores |
| RAM | 512 MB | 2 GB |
| Disk | 500 MB | 5 GB (with logs) |
| Go | 1.21+ | 1.22+ |

### Target Runtime Requirements

brun executes builds for multiple runtimes. Install only those you need:

| Runtime | Version | Purpose |
|---------|---------|---------|
| Go | 1.21+ | Go project builds |
| Node.js | 18+ | Node.js/npm/yarn/bun builds |
| PowerShell | 7+ | PowerShell script execution |

### Install Go (Required for brun itself)

```bash
# macOS (Homebrew)
brew install go

# Linux (Debian/Ubuntu)
sudo apt-get update
sudo apt-get install -y golang-go

# Or download from https://go.dev/dl/
wget https://go.dev/dl/go1.22.0.linux-amd64.tar.gz
sudo tar -C /usr/local -xzf go1.22.0.linux-amd64.tar.gz
export PATH=$PATH:/usr/local/go/bin

# Verify
go version
```

### Install Node.js (Optional)

```bash
# macOS (Homebrew)
brew install node

# Linux (via nvm - recommended)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
nvm install 20
nvm use 20

# Verify
node --version
npm --version
```

### Install PowerShell (Optional)

```bash
# macOS (Homebrew)
brew install --cask powershell

# Linux (Debian/Ubuntu)
sudo apt-get install -y wget apt-transport-https software-properties-common
wget -q "https://packages.microsoft.com/config/ubuntu/$(lsb_release -rs)/packages-microsoft-prod.deb"
sudo dpkg -i packages-microsoft-prod.deb
sudo apt-get update
sudo apt-get install -y powershell

# Verify
pwsh --version
```

---

## Build Process

### Development Build

```bash
# Clone and enter directory
cd brun

# Download dependencies
go mod download
go mod verify

# Build
go build -o brun ./main.go

# Verify
./brun version
```

### Production Build (Optimized)

```bash
# Build with optimizations
CGO_ENABLED=1 go build \
    -ldflags="-s -w -X main.Version=1.0.0" \
    -trimpath \
    -o brun \
    ./main.go

# Binary size will be smaller (~8-12 MB vs ~18 MB)
```

### Cross-Platform Builds

```bash
# Linux (from macOS/Windows)
GOOS=linux GOARCH=amd64 CGO_ENABLED=1 go build -o brun-linux ./main.go

# macOS (from Linux/Windows)
GOOS=darwin GOARCH=amd64 CGO_ENABLED=1 go build -o brun-darwin ./main.go

# Windows (from macOS/Linux)
GOOS=windows GOARCH=amd64 CGO_ENABLED=1 go build -o brun.exe ./main.go
```

**Note:** CGO cross-compilation requires appropriate cross-compilers for SQLite support.

### Build Flags

| Flag | Purpose |
|------|---------|
| `CGO_ENABLED=1` | Required for SQLite |
| `-ldflags="-s -w"` | Strip debug info, smaller binary |
| `-trimpath` | Remove local paths from binary |
| `-X main.Version=1.0.0` | Embed version at build time |

---

## Installation

### Option 1: Local Directory (Recommended for Development)

```bash
# Keep binary in project directory
./brun build --profile my-app
```

### Option 2: User Bin (Personal Use)

```bash
# Copy to user bin
mkdir -p ~/bin
cp brun ~/bin/
export PATH="$HOME/bin:$PATH"

# Add to ~/.bashrc or ~/.zshrc
echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc
```

### Option 3: System-Wide

```bash
# Copy to system bin (requires sudo)
sudo cp brun /usr/local/bin/
sudo chmod 755 /usr/local/bin/brun

# Verify
which brun
brun version
```

### Option 4: Windows Installation

```powershell
# Copy to user directory
Copy-Item brun.exe -Destination "$env:USERPROFILE\bin\"

# Add to PATH (PowerShell)
$env:PATH += ";$env:USERPROFILE\bin"

# Permanent PATH update
[Environment]::SetEnvironmentVariable("PATH", $env:PATH + ";$env:USERPROFILE\bin", "User")

# Verify
brun version
```

---

## Configuration

### Configuration File Location

```bash
# Default search order:
# 1. ./config.json (current directory)
# 2. ~/.config/brun/config.json
# 3. /etc/brun/config.json

# Or specify explicitly:
brun --config /path/to/config.json build --profile my-app

# Or use workdir for project-specific configs:
brun --workdir /path/to/project build --profile my-app
```

### Create Configuration

```bash
# Copy development config
cp configs/config.development.json ./config.json

# Or copy production config for optimized settings
cp configs/config.production.json ./config.json
```

### Minimal Configuration

```json
{
  "environment": "development",
  
  "database": {
    "path": "./brun.db.sqlite"
  },
  
  "profiles": {
    "backend-api": {
      "runtime": "go",
      "command": "go build -o ./bin/api ./cmd/api",
      "workDir": "./",
      "timeout": "300s"
    }
  },
  
  "logging": {
    "level": "info",
    "format": "json",
    "outputPath": "./logs"
  },
  
  "port": {
    "defaultPorts": [8080, 8081, 8082],
    "checkTimeout": "5s"
  }
}
```

### Build Profile Configuration

```json
{
  "profiles": {
    "backend-api": {
      "runtime": "go",
      "command": "go build -o ./bin/api ./cmd/api",
      "workDir": "./",
      "timeout": "300s",
      "preCommands": [
        "go mod tidy"
      ],
      "assets": {
        "source": "./static",
        "target": "./bin/static",
        "mode": "copy"
      },
      "healthCheck": {
        "enabled": true,
        "endpoint": "http://localhost:8080/health",
        "timeout": "30s",
        "retries": 5
      }
    },
    "frontend": {
      "runtime": "node",
      "command": "npm run build",
      "workDir": "./frontend",
      "timeout": "600s",
      "preCommands": [
        "npm install"
      ]
    },
    "scripts": {
      "runtime": "powershell",
      "command": "./scripts/deploy.ps1",
      "timeout": "120s"
    }
  }
}
```

### Environment Variables

```bash
# Override config with environment variables
export BRUN_LOG_LEVEL=debug
export BRUN_DATABASE_PATH=./brun.db.sqlite

# Runtime-specific paths (if not in PATH)
export BRUN_GO_PATH=/usr/local/go/bin/go
export BRUN_NODE_PATH=/usr/local/bin/node
export BRUN_PWSH_PATH=/usr/bin/pwsh
```

### Validate Configuration

```bash
brun config validate
# ✓ Configuration valid
# ✓ Database path writable
# ✓ Profiles defined: 3
# ✓ Runtime 'go' available: go version go1.22.0
# ✓ Runtime 'node' available: v20.10.0
# ⚠ Runtime 'powershell' not found (optional)
```

---

## Database Setup

### Initialize Database

```bash
# Auto-creates database on first run
brun build --profile backend-api

# Or explicitly initialize
brun db init
# ✓ Database created at ./brun.db.sqlite
# ✓ Tables created (5 tables)
```

### Database Location

```bash
# Default: current directory
./brun.db.sqlite

# Custom location (in config.json)
{
  "database": {
    "path": "/path/to/brun.db.sqlite"
  }
}
```

### View Database

```bash
# Using SQLite CLI
sqlite3 brun.db.sqlite

# List tables
.tables

# View schema
.schema Run

# Query recent runs
SELECT * FROM Run ORDER BY StartedAt DESC LIMIT 10;

# View errors for a run
SELECT * FROM BuildError WHERE RunId = 'run-abc123';
```

### Database Tables

| Table | Purpose |
|-------|---------|
| `Run` | Build/check execution history |
| `BuildError` | Captured errors per run |
| `Profile` | Cached profile configurations |
| `PortHistory` | Port usage tracking |
| `HealthCheck` | Application health history |

---

## Running the Application

### Basic Usage

```bash
# Run a build profile
brun build --profile backend-api

# Check for errors without building
brun check --go ./cmd/api

# Run PowerShell script
brun -ps "scripts/build.ps1"

# Run with custom working directory
brun --workdir /path/to/project build --profile my-app
```

### Common Commands

```bash
# Build Commands
brun build --profile backend-api          # Run named profile
brun build --profile frontend --verbose   # Verbose output
brun build --all                          # Run all profiles

# Check Commands
brun check --go ./cmd/api                 # Check Go build
brun check --node ./frontend              # Check Node.js build
brun check --json                         # JSON output for automation

# Port Commands
brun port --check 8080                    # Check port availability
brun port --check 8080 --fallback 8081,8082  # With fallbacks
brun port --list                          # List ports in use

# Config Commands
brun config validate                      # Validate configuration
brun config show                          # Display current config
brun config init                          # Create default config

# Health Commands
brun health --app api-server              # Check app health
brun health --all                         # Check all defined apps
```

### JSON Output (for AI Integration)

```bash
# Check with JSON output
brun check --go ./cmd/api --json

# Output:
{
  "runId": "run-abc123",
  "status": "error",
  "exitCode": 1,
  "errors": [
    {
      "file": "main.go",
      "line": 25,
      "message": "undefined: someFunction",
      "severity": "error",
      "code": 7401
    }
  ],
  "stackTrace": "main.go:25: undefined: someFunction",
  "requiresAIFix": true
}
```

---

## Runtime Dependencies

### Checking Runtime Availability

```bash
brun runtime check
# Runtime Status:
# ✓ go: go version go1.22.0 linux/amd64
# ✓ node: v20.10.0
# ✓ npm: 10.2.0
# ⚠ powershell: not found
```

### Runtime-Specific Configuration

```json
{
  "runtimes": {
    "go": {
      "path": "/usr/local/go/bin/go",
      "env": {
        "GOPROXY": "https://proxy.golang.org",
        "GOPRIVATE": "github.com/myorg/*"
      }
    },
    "node": {
      "path": "/usr/local/bin/node",
      "packageManager": "npm",
      "env": {
        "NODE_ENV": "production"
      }
    },
    "powershell": {
      "path": "/usr/bin/pwsh",
      "executionPolicy": "Bypass"
    }
  }
}
```

---

## Logging

### Console Logging (Default)

```bash
# Info level (default)
brun build --profile backend-api

# Debug level (verbose)
brun build --profile backend-api --log-level debug

# Quiet mode
brun build --profile backend-api --log-level error
```

### File Logging

```bash
# Configure in config.json
{
  "logging": {
    "level": "info",
    "format": "json",
    "outputPath": "./logs/brun.log",
    "errorPath": "./logs/brun-error.log"
  }
}

# Create log directory
mkdir -p ./logs

# View logs
tail -f ./logs/brun.log

# Filter errors
grep '"level":"error"' ./logs/brun.log | jq .
```

### Run-Specific Logs

```bash
# Each run creates dedicated log files
./logs/
├── brun.log              # Main log file
├── brun-error.log        # Error-only log
└── runs/
    ├── run-abc123/
    │   ├── stdout.log    # Command stdout
    │   ├── stderr.log    # Command stderr
    │   └── meta.json     # Run metadata
    └── run-def456/
        └── ...
```

### Log Levels

| Level | Use Case |
|-------|----------|
| `debug` | Development, troubleshooting |
| `info` | Normal operation |
| `warn` | Recoverable issues |
| `error` | Failures |
| `silent` | No logging |

---

## Health Checks

### CLI Health Check

```bash
# Quick check
brun health
# Status: healthy

# Detailed check
brun health --verbose
# Database: healthy (2ms)
# Disk: healthy (5.2 GB available)
# Runtime go: healthy (go1.22.0)
# Runtime node: healthy (v20.10.0)
# Runtime powershell: unavailable
```

### Application Health Checks

```bash
# Check specific application
brun health --app api-server
# api-server: healthy (http://localhost:8080/health responded 200 OK in 15ms)

# Check all defined applications
brun health --all
# api-server: healthy (200 OK, 15ms)
# worker: healthy (200 OK, 22ms)
# frontend: unhealthy (connection refused)
```

### Health Configuration

```json
{
  "applications": {
    "api-server": {
      "host": "localhost",
      "port": 8080,
      "path": "/health",
      "expectedStatus": 200,
      "timeout": "5s",
      "retries": 3,
      "retryDelay": "1s"
    }
  }
}
```

---

## Backup & Maintenance

### Backup Database

```bash
# Simple copy (stop writes first for consistency)
cp brun.db.sqlite brun.db.sqlite.backup

# With timestamp
cp brun.db.sqlite "brun.db.sqlite.$(date +%Y%m%d)"

# Using SQLite backup command (safe for active databases)
sqlite3 brun.db.sqlite ".backup 'brun.db.backup.sqlite'"
```

### Restore Database

```bash
# Stop the application first
cp brun.db.backup.sqlite brun.db.sqlite
```

### Database Maintenance

```bash
# Vacuum (reclaim space after deletes)
brun db vacuum

# Or directly with SQLite
sqlite3 brun.db.sqlite "VACUUM;"

# Analyze (optimize query performance)
sqlite3 brun.db.sqlite "ANALYZE;"

# Clean old run history
brun db clean --older-than 30d
```

### Log Cleanup

```bash
# Remove old run logs
brun logs clean --older-than 7d

# View what would be deleted
brun logs clean --older-than 7d --dry-run

# Archive logs before cleanup
tar -czf logs-archive-$(date +%Y%m%d).tar.gz ./logs/runs/
brun logs clean --older-than 30d
```

---

## Troubleshooting

### Common Issues

#### Issue: "database is locked"

```bash
# Cause: Multiple processes accessing database

# Solution 1: Check for other processes
pgrep brun
lsof brun.db.sqlite

# Solution 2: Kill hanging processes
pkill -9 brun

# Solution 3: Increase busy timeout in config
{
  "database": {
    "busyTimeout": "30s"
  }
}
```

#### Issue: "runtime not found"

```bash
# Cause: Runtime not in PATH

# Solution 1: Check PATH
echo $PATH
which go
which node

# Solution 2: Specify path in config
{
  "runtimes": {
    "go": {
      "path": "/usr/local/go/bin/go"
    }
  }
}

# Solution 3: Check runtime availability
brun runtime check
```

#### Issue: "port already in use"

```bash
# Cause: Another process using the port

# Solution 1: Find and kill the process
lsof -i :8080
kill -9 <PID>

# Solution 2: Use fallback ports
brun port --check 8080 --fallback 8081,8082

# Solution 3: Configure fallbacks in profile
{
  "profiles": {
    "api": {
      "port": {
        "preferred": 8080,
        "fallbacks": [8081, 8082, 8083]
      }
    }
  }
}
```

#### Issue: "permission denied"

```bash
# Cause: Insufficient permissions

# Solution 1: Check file permissions
ls -la ./bin/

# Solution 2: Make binary executable
chmod +x ./bin/api

# Solution 3: Check directory write access
ls -la ./logs/
mkdir -p ./logs && chmod 755 ./logs
```

#### Issue: "build timeout exceeded"

```bash
# Cause: Build taking longer than configured timeout

# Solution: Increase timeout in profile
{
  "profiles": {
    "frontend": {
      "timeout": "600s"
    }
  }
}
```

### Debug Mode

```bash
# Enable debug logging
brun build --profile backend-api --log-level debug

# Trace execution
BRUN_TRACE=1 brun build --profile backend-api

# Dry run (show what would execute)
brun build --profile backend-api --dry-run
```

### Getting Help

```bash
# Command help
brun --help
brun build --help
brun check --help

# Version info
brun version

# Configuration info
brun config show
```

---

## Platform-Specific Notes

### Linux

```bash
# Firewall rules (if managing ports)
# Uses iptables or ufw depending on config
sudo ufw allow 8080/tcp

# Check firewall status
sudo ufw status
```

### macOS

```bash
# Firewall rules (uses pfctl)
# Usually not needed for local development

# Check if port is in use
lsof -i :8080

# Allow through macOS firewall (if prompted)
# System Preferences > Security & Privacy > Firewall > Allow
```

### Windows

```powershell
# Firewall rules (uses netsh)
netsh advfirewall firewall add rule name="brun-8080" dir=in action=allow protocol=TCP localport=8080

# Check if port is in use
netstat -ano | findstr :8080

# PowerShell execution policy
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
```

### WSL (Windows Subsystem for Linux)

```bash
# Build for WSL
go build -o brun ./main.go

# Note: WSL2 has separate network stack
# Ports may need forwarding from Windows host

# Check WSL version
wsl --list --verbose
```

---

## Integration with Main Application

### Subprocess Communication

```bash
# Main application calls brun as subprocess
brun check --go ./cmd/api --json 2>&1

# Parse JSON output for AI fix loop
# See: 09-integration-api.md
```

### AI Fix Loop Integration

```bash
# 1. Run check
brun check --go ./cmd/api --json > errors.json

# 2. AI generates fix patch
# (handled by main application)

# 3. Apply patch and retry
brun build --profile backend-api --json
```

---

## See Also

- [Core Architecture](./01-core-architecture.md)
- [Configuration](./03-configuration.md)
- [Error Handling](./06-error-handling.md)
- [Integration API](./09-integration-api.md)
- [Observability](./15-observability.md)
- [gsearch Deployment Guide](../22-golang-search-cli/17-deployment-guide.md)
