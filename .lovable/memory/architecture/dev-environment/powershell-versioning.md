# Memory: architecture/dev-environment/powershell-versioning
Updated: 2026-03-20

---

## Overview

Version numbers are tracked across multiple files. The `bump-version.ps1` script automates syncing them.

**IMPORTANT**: Always bump the minor version when any functional changes are made (new flags, new commands, bug fixes, etc.). Never skip version bumps.

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

1. **When to bump**: Any functional change to a target area — new commands, flags, bug fixes, refactors
2. **ALWAYS bump on changes**: Never commit functional changes without a version bump. If you add a flag, command, or fix a bug, bump the minor version
3. **Use the script**: Always use `bump-version.ps1` instead of manual edits (or update all files manually if script unavailable)
4. **DryRun first**: Use `-DryRun` to preview before applying
5. **Changelog**: Manually add entry to `spec/12-powershell-integration/changelog.md` after script bumps
6. **Don't touch .release folder**: The `.release/` directory is managed separately and should NOT be updated during version bumps

---

## Files to Update on Version Bump

| File | Field/Line |
|------|------------|
| `public/version.json` | `version`, `scriptVersion`, `wpPluginVersion`, `quploadVersion`, `releaseDate`, new changelog entry |
| `run.ps1` | Line 2 comment `# Version: X.Y.Z` |
| `wp-plugins/riseup-asia-uploader/riseup-asia-uploader.php` | Plugin header `Version:` |
| `wp-plugins/riseup-asia-uploader/includes/Enums/PluginConfigType.php` | `case Version = 'X.Y.Z'` |
| `wp-plugins/qupload/qupload.php` | Plugin header `Version:` |
| `wp-plugins/qupload/includes/Enums/PluginConfigType.php` | `case Version = 'X.Y.Z'` |

---

## Current Versions

- **App Version**: 2.28.3
- **Plugin Version (QUpload)**: 2.28.3
- **Plugin Version (Riseup)**: 2.28.3
- **Script Version**: 2.28.3
- **Spec Version**: 2.28.3

---

*Automation script: `wp-plugins/scripts/bump-version.ps1`*
