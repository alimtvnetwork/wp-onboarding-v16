# Memory: architecture/dev-environment/powershell-versioning
Updated: 2026-03-12

---

## Overview

Version numbers are tracked across multiple files. The `bump-version.ps1` script automates syncing them.

---

## Version Targets

| Target | Files Updated |
|--------|---------------|
| `app` | `public/version.json` → `version`, `releaseDate` |
| `script` | `run.ps1` header, `powershell.json`, `public/version.json` → `scriptVersion`, `spec/12-powershell-integration/00-overview.md` |
| `plugin` | `PluginConfigType.php` → `Version` case, `public/version.json` → `wpPluginVersion` |
| `all` | All of the above |

---

## Bump Script Usage

```powershell
.\wp-plugins\scripts\bump-version.ps1 -Target app -Bump patch
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump minor
.\wp-plugins\scripts\bump-version.ps1 -Target plugin -Set "2.0.0"
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch -DryRun
```

---

## Versioning Rules

1. **When to bump**: Any functional change to a target area
2. **Use the script**: Always use `bump-version.ps1` instead of manual edits
3. **DryRun first**: Use `-DryRun` to preview before applying
4. **Changelog**: Manually add entry to `spec/12-powershell-integration/changelog.md` after script bumps

---

## Current Versions

- **App Version**: 2.1.0
- **Plugin Version (QUpload)**: 2.1.0
- **Plugin Version (Riseup)**: 2.1.0
- **Spec Version**: 2.1.0

---

*Automation script: `wp-plugins/scripts/bump-version.ps1`*
