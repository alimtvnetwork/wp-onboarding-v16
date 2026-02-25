# R10 Audit Report — Missing Blank Lines Before Control Structures

> **Date:** 2026-02-25
> **Rule:** R10 — A blank line is mandatory before control structures (`if`, `foreach`, `switch`, `match`) when preceded by any statements.
> **Scope:** All PHP trait files in `wp-plugins/riseup-asia-uploader/includes/`

---

## Summary

**Estimated violations: 80–120+ across ~40 trait files.**

Two dominant violation patterns were identified:

### Pattern A: Boolean guard + immediate `if` (~50%)

```php
$isPluginFileMissing = ($pluginFile === null);
if ($isPluginFileMissing) {           // ← R10: missing blank line
```

This is a deliberate, consistent codebase pattern. The boolean variable names the condition for readability.

### Pattern B: Result + immediate check (~50%)

```php
$result = activate_plugin($pluginFile);
if (is_wp_error($result)) {           // ← R10: missing blank line
```

---

## Violations by File (Sampled)

### Upload Traits

| File | Lines | Pattern |
|------|-------|---------|
| `UploadParserTrait.php` | 56–57, 82–83 | B (result → check) |
| `UploadInstallActivateTrait.php` | 33–34, 38–39, 50–51, 55–57, 77–78, 130–131 | A+B mixed |
| `UploadInstallExtractTrait.php` | 97–98, 102–103, 107–108, 134–135, 139–140, 155–156 | B (result → instanceof) |
| `UploadZipTrait.php` | 29–30, 34–35, 65–66, 77–78 | A+B mixed |

### Sync Traits

| File | Lines | Pattern |
|------|-------|---------|
| `SyncPushTrait.php` | 44–45, 50–51, 98–99, 121–122, 161–162, 167–170 | A+B mixed |
| `SyncManifestTrait.php` | 31–32, 48–49 | B (result → check) |

### Auth Traits

| File | Lines | Pattern |
|------|-------|---------|
| `AuthCredentialTrait.php` | 57–58, 64–65, 119–120, 131–132 | A+B mixed |
| `AuthPermissionTrait.php` | 37–38, 42–43 | A (boolean guard) |

### Plugin Traits

| File | Lines | Pattern |
|------|-------|---------|
| `PluginLifecycleEnableTrait.php` | 31–32, 36–37, 50–51, 55–56, 60–61, 87–88 | B (result → check) |
| `PluginLifecycleDeleteTrait.php` | 30–31, 35–36, 40–41, 61–63 | B (result → check) |
| `PluginListTrait.php` | 68–69, 145–146, 171–172 | B (result → check) |

### Agent Traits

| File | Lines | Pattern |
|------|-------|---------|
| `AgentRemoteCoreTrait.php` | (mostly compliant — uses blank lines) | — |
| `AgentRemoteActionTrait.php` | (mostly compliant — uses blank lines) | — |
| `AgentHandlerCrudTrait.php` | 92–94 | A (boolean guard) |

### Notable Compliant Files

These agent traits already follow R10 correctly with blank lines before `if`:
- `AgentRemoteActionTrait.php` — uses blank lines before `$isNotRedirect`/`$hasNoLocation` checks
- `AgentCrudWriteTrait.php` — uses blank lines before validation checks

---

## Unscanned Files (Estimated ~60 More Traits)

The following directories contain additional trait files not yet scanned:

- `Snapshot/Traits/` — 65 files (highest likely violation count)
- `Database/Traits/` — 21 files
- `Traits/Error/` — 3 files
- `Traits/Core/` — 3 files
- `Traits/Status/` — 3 files
- `Traits/FileSystem/` — 3 files
- `Traits/Log/` — 1 file
- `Update/Traits/` — 5 files
- `Upload/Traits/` — 1 file

---

## Recommendation

**Batch fix in 3 waves** (adding a blank line before each `if`/`foreach`/`switch`/`match` preceded by a statement):

| Wave | Scope | Estimated Violations | Risk |
|------|-------|---------------------|------|
| 1 | `Traits/Upload/`, `Traits/Sync/`, `Traits/Auth/` | ~25 | Low |
| 2 | `Traits/Plugin/`, `Traits/Agent/`, `Traits/Core/`, `Traits/Error/`, `Traits/Status/` | ~20 | Low |
| 3 | `Snapshot/Traits/`, `Database/Traits/`, `Agent/Traits/`, `Update/Traits/` | ~60+ | Low |

Each fix is purely additive (inserting blank lines) — zero behavioral change.
