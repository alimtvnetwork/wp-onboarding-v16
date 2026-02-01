# Memory: features/build-runner-cli

**Updated:** 2026-02-01  
**Spec Location:** `spec/brun-cli/` (standalone, extracted from spec-management-software)

---

## Overview

Golang-based Build Runner CLI (`brun`) for cross-platform command execution and error detection. Stateless executor controlled by main application via subprocess JSON protocol.

---

## Core Capabilities

| Feature | Description |
|---------|-------------|
| Build Profiles | Asset management (clear-copy, override, skip-existing) |
| Port Management | Fallback strategies, firewall handling |
| Health Monitoring | Application health checks with retries |
| External Workdir | `--workdir` flag for external directories |
| Config Generation | AI auto-generates configs from schema |
| Fix Loop | Recursive check-fix-retry with AI |

---

## Error Code Range

**71xx-75xx** for build runner errors

---

## Integration

- Main app controls via subprocess protocol
- JSON output for structured parsing
- AI generates configurations from metadata
- Run history persisted in SQLite via GORM
- Full parity with `gsearch` documentation structure

---

## Specification Files

Located at `spec/brun-cli/`:
- 00-overview.md through 16-deployment-guide.md
- 99-consistency-report.md

---

## Reference

Integration reference at: `spec/spec-management-software/15-external-tools/04-brun-reference.md`
