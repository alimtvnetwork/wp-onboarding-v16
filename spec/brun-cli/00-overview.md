# Build Runner CLI (brun)

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

A lightweight Golang CLI tool for running builds, executing commands, and detecting errors across multiple runtimes (PowerShell, Node.js, Go). Designed to integrate with the main Spec Management application for AI-assisted error fixing loops.

**Binary Name:** `brun`  
**Module Path:** `github.com/user/brun`

---

## Design Philosophy

**Keep it simple:** Single binary, JSON configuration, cross-platform support. No external dependencies beyond the target runtimes.

---

## Cross-References

- [Main Project Overview](../../00-overview.md)
- [gsearch CLI](../22-golang-search-cli/00-overview.md)
- [AI Integration](../06-ai-integration/00-overview.md)
- [Skipped Features](../../11-skipped-features/00-overview.md)

---

## Document Index

| # | Document | Description | Status |
|---|----------|-------------|--------|
| 00 | [Overview](./00-overview.md) | This file | ✅ Complete |
| 01 | [Core Architecture](./01-core-architecture.md) | System design and components | ✅ Complete |
| 02 | [CLI Interface](./02-cli-interface.md) | Commands and parameters | ✅ Complete |
| 03 | [Configuration](./03-configuration.md) | config.json schema and options | ✅ Complete |
| 04 | [Runtime Executors](./04-runtime-executors.md) | PowerShell, Node.js, Go runners | ✅ Complete |
| 05 | [Port Management](./05-port-management.md) | Port checking, fallback, firewall | ✅ Complete |
| 06 | [Error Handling](./06-error-handling.md) | Error capture, JSON output, logging | ✅ Complete |
| 07 | [Build Profiles](./07-build-profiles.md) | Saved build configurations | ✅ Complete |
| 08 | [Asset Operations](./08-asset-operations.md) | File copy, clear, override modes | ✅ Complete |
| 09 | [Integration API](./09-integration-api.md) | Subprocess communication protocol | ✅ Complete |
| 10 | [Data Models](./10-data-models.md) | GORM entities and schemas | ✅ Complete |
| 11 | [Acceptance Criteria](./11-acceptance-criteria.md) | Validation requirements | ✅ Complete |
| 12 | [AI Config Generation](./12-ai-config-generation.md) | AI-assisted config creation and UI | ✅ Complete |
| 13 | [Testing Strategy](./13-testing-strategy.md) | Integration tests, mocks, CI/CD | ✅ Complete |
| 14 | [Implementation Guide](./14-implementation-guide.md) | Build order and dependency graph | ✅ Complete |
| 15 | [Observability](./15-observability.md) | Prometheus, health checks, logging | ✅ Complete |
| 16 | [Deployment Guide](./16-deployment-guide.md) | Cross-platform installation | ✅ Complete |
| 99 | [Consistency Report](./99-consistency-report.md) | Cross-reference validation | ✅ Complete |

---

## Component Summary

| Component | Count | Purpose |
|-----------|-------|---------|
| Specifications | 12 | Complete feature documentation |
| CLI Commands | 5 | build, check, run, port, config |
| Runtime Executors | 3 | PowerShell, Node.js, Go |
| Config Options | 50+ | Comprehensive configuration |

---

## Key Features

### 1. Multi-Runtime Execution
- **PowerShell**: Direct script execution via `-ps` flag
- **Node.js**: npm/yarn/bun build commands
- **Golang**: go build, go mod tidy, go run

### 2. Build Profile Management
- Save build configurations to config.json
- Specify source paths, output directories, asset copying
- Clear/override/skip modes for file operations

### 3. Port Management
- Check port availability before running
- Automatic fallback to alternative ports
- Firewall rule management (Windows/Linux)

### 4. Error Capture & Reporting
- JSON output for programmatic consumption
- File-based logging (log.txt, error.txt)
- Dynamic run ID with dedicated log folders
- Stack trace capture for debugging

### 5. AI Integration Loop
- Designed for recursive error-fixing workflow
- Main app triggers build → captures errors → feeds to AI → retries

### 6. Application Health Checks
- Monitor localhost:port for running applications
- Named application definitions in config
- Configurable health check endpoints and timeouts
- Wait for app startup before proceeding

### 7. AI-Assisted Configuration
- JSON schema with AI-readable descriptions
- Automatic config generation from natural language
- UI-based config editing and validation
- Flexible working directory for any project path

---

## Quick Start

```bash
# Run PowerShell script
brun -ps "scripts/build.ps1"

# Check Go build for errors
brun check --go ./cmd/app

# Run saved build profile
brun build --profile backend-api

# Check port availability
brun port --check 8080 --fallback 8081,8082
```

---

## Technology Stack

| Component | Technology |
|-----------|------------|
| Language | Go 1.21+ |
| CLI Framework | Cobra + Viper |
| Config Format | JSON |
| Database | SQLite (optional, for run history) |
| ORM | GORM |

---

## Project Status

| Phase | Name | Status |
|-------|------|--------|
| 1 | Core CLI & Config | Planned |
| 2 | Runtime Executors | Planned |
| 3 | Port Management | Planned |
| 4 | Error Capture | Planned |
| 5 | AI Integration | Planned |

---

## See Also

- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)
- [Error Management](../../06-error-management/00-overview.md)
- [Database Design](../../07-database-design/00-overview.md)
