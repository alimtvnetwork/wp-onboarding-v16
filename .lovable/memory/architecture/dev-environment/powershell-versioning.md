# Memory: architecture/dev-environment/powershell-versioning
Updated: 2026-02-04

---

## Overview

The PowerShell runner script (`run.ps1`) and its configuration (`powershell.json`) now include version tracking to ensure changes are documented and traceable.

---

## Version Locations

| File | Field | Purpose |
|------|-------|---------|
| `run.ps1` | `# Version: X.X.X` (line 2) | Script version in header comment |
| `powershell.json` | `"version": "X.X.X"` | Configuration version |
| `spec/powershell-integration/CHANGELOG.md` | Version history | Detailed changelog for script |
| `spec/powershell-integration/00-overview.md` | `Script Version:` header | Spec documentation |

---

## Versioning Rules

1. **When to bump version**: Any functional change to `run.ps1` or significant config schema change
2. **Sync locations**: Update all 4 locations when version changes
3. **Changelog entry**: Add entry to `spec/powershell-integration/CHANGELOG.md`
4. **Spec update**: Update version in `00-overview.md` and relevant spec files

---

## Current Version

- **Script Version**: 1.1.0
- **Spec Version**: 2.1.0

---

*Single source of truth for script version: `run.ps1` header comment (line 2)*
