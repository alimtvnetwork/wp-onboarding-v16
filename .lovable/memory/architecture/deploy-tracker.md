# Memory: Deploy Tracker — Per-Plugin SHA-Based Skip Logic

> **Updated:** 2026-03-22

## Overview

The `-d` (Deploy) flag in `run.ps1` uses a **persistent per-plugin SHA tracker** stored in `.deployed/` (committed to git) to determine which plugins need PHP upload/status propagation.

## How It Works

1. On `-d`, after `git pull`, the tracker reads each plugin's `.deployed/{slug}-latest.json`
2. Compares `lastCommitSha` against current `HEAD` using `git diff --name-only {sha} HEAD -- {pluginPath}/`
3. Only counts **`.php` file changes** — non-PHP changes (docs, assets) are ignored
4. Plugins with no PHP changes since their last deploy are **skipped entirely**
5. After successful upload, `Save-PluginDeployState` records the new SHA + version + timestamp

## File Structure

```
.deployed/
├── latest.json                        # Aggregated state for all plugins
├── riseup-asia-uploader-latest.json   # Per-plugin last deploy info
├── riseup-asia-uploader-versions.json # Per-plugin deploy history
├── qupload-latest.json
└── qupload-versions.json
```

## JSON Schema (per-plugin latest)

```json
{
  "pluginSlug": "riseup-asia-uploader",
  "lastCommitSha": "abc1234...",
  "version": "2.30.0",
  "deployedAt": "2026-03-22T10:00:00Z"
}
```

## Key Module

`wp-plugins/scripts/modules/deploy-tracker.ps1` provides:
- `Test-PluginPhpChanged` — checks if a plugin has PHP changes since last deploy
- `Save-PluginDeployState` — records a successful deploy
- `Get-PluginsNeedingDeploy` — returns list of slugs needing upload

## Previous Approach (replaced)

Previously used `git diff HEAD@{1} HEAD -- wp-plugins/` which only compared against the reflog entry, not the actual last deploy point. This was unreliable when multiple pulls happened without deploys.
