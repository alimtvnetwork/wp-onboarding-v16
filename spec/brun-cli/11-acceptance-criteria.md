# Acceptance Criteria

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Validation requirements and acceptance criteria for the Build Runner CLI.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [Error Handling](./06-error-handling.md)
- [Integration API](./09-integration-api.md)

---

## Core Functionality

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| BR-01 | CLI binary compiles without errors on Windows, Linux, macOS | Critical | Build test on each OS |
| BR-02 | `--help` displays all commands and flags | Critical | Manual verification |
| BR-03 | `--version` shows correct version number | Critical | Version check |
| BR-04 | Exit codes match documented values | Critical | Automated test |
| BR-05 | JSON output is valid and parseable | Critical | JSON schema validation |

---

## Configuration

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CF-01 | Loads config.json from default paths | Critical | Path resolution test |
| CF-02 | `--config` flag overrides default path | Critical | Flag test |
| CF-03 | Environment variables override config values | High | Env var test |
| CF-04 | Invalid config produces clear error message | Critical | Error handling test |
| CF-05 | `brun config validate` reports config issues | High | Validation test |
| CF-06 | `brun config init` creates valid default config | Medium | Init test |

---

## Runtime Executors

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| RT-01 | PowerShell executor runs scripts on Windows | Critical | Integration test |
| RT-02 | PowerShell executor runs via pwsh on Linux/macOS | High | Integration test |
| RT-03 | Node.js executor supports npm, yarn, bun | Critical | Package manager test |
| RT-04 | Go executor runs go build with flags | Critical | Build test |
| RT-05 | Go executor handles go mod tidy (skip/run/force) | Critical | Tidy option test |
| RT-06 | Executor timeout terminates long-running processes | Critical | Timeout test |
| RT-07 | Runtime not found produces clear error | Critical | Missing runtime test |
| RT-08 | Runtime version detection works | Medium | Version check test |

---

## Error Handling

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-01 | Go compile errors parsed with file, line, column | Critical | Parser test |
| EH-02 | TypeScript errors parsed correctly | High | Parser test |
| EH-03 | PowerShell errors captured | High | Parser test |
| EH-04 | Stack traces captured when available | Medium | Stack trace test |
| EH-05 | JSON error output matches schema | Critical | Schema validation |
| EH-06 | Log files created in configured directory | High | File system test |
| EH-07 | Run folders use unique dynamic IDs | High | ID uniqueness test |
| EH-08 | Old log runs cleaned up based on keepRuns | Medium | Cleanup test |

---

## Port Management

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PM-01 | Port availability check works on all platforms | Critical | Platform test |
| PM-02 | Fallback to alternative port when primary busy | Critical | Fallback test |
| PM-03 | Process name/PID identified for busy port | High | Process detection test |
| PM-04 | `brun port --check` returns JSON status | High | JSON output test |
| PM-05 | Firewall enable works on Windows (netsh) | Medium | Windows firewall test |
| PM-06 | Firewall enable works on Linux (ufw/iptables) | Medium | Linux firewall test |
| PM-07 | `brun port --list` shows managed rules | Low | List test |

---

## Build Profiles

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| BP-01 | `brun build --profile <name>` executes profile | Critical | Profile execution test |
| BP-02 | Profile not found produces clear error | Critical | Error test |
| BP-03 | Profile environment variables applied | High | Env var test |
| BP-04 | Pre-commands run before main command | High | Order test |
| BP-05 | Post-commands run on success only | Medium | Conditional test |
| BP-06 | `brun config add-profile` saves to config | High | Add profile test |
| BP-07 | `brun config remove-profile` removes from config | Medium | Remove profile test |

---

## Asset Operations

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AO-01 | Mode `copy` fails if destination exists | High | Copy mode test |
| AO-02 | Mode `clear-copy` deletes then copies | High | Clear-copy test |
| AO-03 | Mode `override` overwrites existing | High | Override test |
| AO-04 | Mode `skip-existing` skips existing files | High | Skip test |
| AO-05 | Pattern filtering works (e.g., *.js) | Medium | Pattern test |
| AO-06 | Exclusion patterns work | Medium | Exclusion test |
| AO-07 | Flatten option copies to single directory | Low | Flatten test |

---

## Integration API

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| IA-01 | JSON output parseable by parent process | Critical | Integration test |
| IA-02 | Exit code reflects success/failure accurately | Critical | Exit code test |
| IA-03 | Errors array suitable for AI consumption | Critical | AI format test |
| IA-04 | RunID uniquely identifies each execution | High | Uniqueness test |
| IA-05 | Duration reported in consistent format | Medium | Duration test |
| IA-06 | `--health` returns runtime availability | Medium | Health check test |

---

## Performance

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PF-01 | CLI startup < 100ms (no build) | High | Startup benchmark |
| PF-02 | Config loading < 50ms | Medium | Config benchmark |
| PF-03 | Error parsing doesn't block output stream | High | Streaming test |
| PF-04 | Memory usage < 50MB for typical build | Medium | Memory profiling |

---

## Cross-Platform

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| XP-01 | Binary builds for Windows AMD64 | Critical | Build matrix |
| XP-02 | Binary builds for Linux AMD64 | Critical | Build matrix |
| XP-03 | Binary builds for macOS AMD64/ARM64 | High | Build matrix |
| XP-04 | Path separators handled correctly | Critical | Path test |
| XP-05 | Line endings handled correctly | High | Line ending test |

---

## Database (Optional)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| DB-01 | Database creates on first run | Medium | Init test |
| DB-02 | Build runs persisted with errors | Medium | Persistence test |
| DB-03 | Old runs cleaned up per configuration | Low | Cleanup test |
| DB-04 | Statistics query returns accurate data | Low | Query test |

---

## Validation Checklist

### Pre-Release

- [ ] All Critical criteria pass
- [ ] All High criteria pass
- [ ] Integration test with main application passes
- [ ] AI error fixing loop works end-to-end
- [ ] Documentation matches implementation

### Release Candidate

- [ ] All Medium criteria pass
- [ ] Performance benchmarks within targets
- [ ] Cross-platform builds verified
- [ ] No known critical bugs

### General Availability

- [ ] All criteria pass
- [ ] User documentation complete
- [ ] Example configurations provided
- [ ] Error messages user-friendly

---

## See Also

- [CLI Interface](./02-cli-interface.md)
- [Error Handling](./06-error-handling.md)
- [Integration API](./09-integration-api.md)
