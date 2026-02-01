# BRun CLI Reference

> **External Spec:** `spec/brun-cli/`  
> **Version:** 1.0.0  
> **Error Range:** 7100-7599  
> **Status:** ✅ Extracted

---

## Summary

Golang-based Build Runner CLI for cross-platform command execution with error detection. Stateless executor controlled by main application via subprocess JSON protocol.

---

## Full Specification

📁 **Location:** [`spec/brun-cli/`](../../brun-cli/00-overview.md)

---

## Specification Files

| File | Description |
|------|-------------|
| `00-overview.md` | Module overview |
| `01-core-architecture.md` | Core design |
| `02-cli-interface.md` | Command interface |
| `03-configuration.md` | Config format |
| `04-runtime-executors.md` | Execution engines |
| `05-port-management.md` | Port handling |
| `06-error-handling.md` | Error management |
| `07-build-profiles.md` | Profile system |
| `08-asset-operations.md` | Asset management |
| `09-integration-api.md` | Integration API |
| `10-data-models.md` | Data structures |
| `11-acceptance-criteria.md` | Test criteria |
| `12-ai-config-generation.md` | AI config gen |
| `13-testing-strategy.md` | Testing approach |
| `14-implementation-guide.md` | Implementation |
| `15-observability.md` | Monitoring |
| `16-deployment-guide.md` | Deployment |

---

## Error Code Range

| Range | Category |
|-------|----------|
| 71xx | CLI/argument errors |
| 72xx | Configuration errors |
| 73xx | Execution errors |
| 74xx | Port management errors |
| 75xx | Health check errors |

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

## Integration Points

### Subprocess Execution

```go
cmd := exec.Command("brun", "run", "--config", configPath, "--json")
stdout, _ := cmd.StdoutPipe()
// Parse JSON output for structured results
```

### CLI Commands

```bash
brun run --profile dev --workdir /path/to/project
brun port check 3000
brun health --url http://localhost:3000 --retries 5
brun config generate --schema ./schema.json
```

---

## Configuration Example

```json
{
  "profiles": {
    "dev": {
      "command": "npm run dev",
      "port": 3000,
      "healthCheck": "/api/health",
      "assets": {
        "mode": "clear-copy",
        "source": "./dist",
        "target": "./public"
      }
    }
  }
}
```

---

## Dependencies

- Used by **Nexus Flow** for build/task nodes
- Integrates with **AI Bridge** for config generation

---

## See Also

- [Full Specification](../../brun-cli/00-overview.md)
- [Nexus Flow Reference](./03-nexus-flow-reference.md)
- [External Tools Overview](./00-overview.md)
