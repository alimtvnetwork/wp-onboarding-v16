# 17-06 — Plugin Status Command

## Purpose

Check the health and status of deployed plugins across one or more WordPress sites, and optionally retrieve error logs and stack traces for diagnostics. Results are displayed in the console and saved to `logs/plugin-status/`.

---

## CLI Flags

| Flag | Alias | Description |
|------|-------|-------------|
| `-ps` | `-pluginstatus` | Check plugin status on the default site |
| `-pas` | `-pluginstatusall` | Check plugin status on **all** configured sites |
| `-error` | — | Include error logs and stack traces in output |
| `-site` | — | Filter to a specific site by name (reuses existing flag) |
| `-index` / `-i` | — | Filter to a specific site by index (reuses existing flag) |
| `-xs` | — | Exclude sites by name (reuses existing flag) |
| `-sync` | — | Run sequentially instead of parallel |

### Example Commands

```powershell
./run.ps1 -ps                    # Status of all plugins on default site
./run.ps1 -pas                   # Status of all plugins on ALL sites
./run.ps1 -pas -i 1              # Status on site index 1 only
./run.ps1 -pas -site "Test V1"   # Status on named site only
./run.ps1 -pas -error            # Status + error logs + stack traces for all sites
./run.ps1 -ps -error             # Status + error logs for default site
```

---

## Module

### File: `wp-plugins/scripts/modules/mode-plugin-status.ps1`

### Function Signature

```powershell
function Invoke-PluginStatusMode {
    # Expects: $site, $index, $exclude, $sync, $ScriptDir, $Config
    # Also expects: $error (switch — include error/stacktrace logs)
    # Also expects: $pluginstatus, $pluginstatusall (to determine scope)
}
```

---

## Behavior

### 1. Log Folder Management

At the start of every run:

1. Target folder: `logs/plugin-status/`
2. If the folder exists, **delete all contents** (clear previous session)
3. If it doesn't exist, create it
4. All output files from this run are written here

### 2. Site Resolution

Reuse existing `Resolve-TargetSites` helper:

- `-ps` → default site (index 0 or first configured site)
- `-pas` → all configured sites (respects `-site`, `-i`, `-xs` filters)

### 3. Plugin Discovery

Use `Get-UploadablePlugins` to discover plugins (same as `-uas` mode). This gives us the list of plugin slugs to check.

### 4. Status Check (Per Site × Per Plugin)

For each site × plugin combination, hit the status endpoint:

| Plugin | Endpoint |
|--------|----------|
| QUpload | `GET /wp-json/qupload-api/v1/status` |
| Riseup Asia Uploader | `GET /wp-json/riseup-asia-uploader/v1/status` |

Authentication: Basic auth using the site's decoded credentials (same as upload mode).

**Status Result:**

```powershell
@{
    Site       = "Test V1"
    SiteUrl    = "https://testv1.developers-organism.com"
    Plugin     = "qupload"
    Version    = "2.18.0"         # from status response
    Status     = "OK"             # OK | UNREACHABLE | NOT_INSTALLED | AUTH_FAILED | ERROR
    HttpStatus = 200
    Message    = ""               # error detail if not OK
    Duration   = 1.2              # seconds
}
```

**Status classification:**

| HTTP | Condition | Status |
|------|-----------|--------|
| 200 | Success response | `OK` |
| 404 | Route not found | `NOT_INSTALLED` |
| 401/403 | Auth rejected | `AUTH_FAILED` |
| 0 / timeout | No response | `UNREACHABLE` |
| Other | Any other error | `ERROR` |

### 5. Error Log Retrieval (When `-error` Is Set)

For each site × plugin where status is `OK`, fetch logs via:

| Plugin | Endpoint |
|--------|----------|
| QUpload | `GET /wp-json/qupload-api/v1/logs/retrieve?include_info_log=false&include_error_log=true&include_stacktrace=true&max_lines=100` |
| Riseup Asia Uploader | `GET /wp-json/riseup-asia-uploader/v1/logs/retrieve?include_info_log=false&include_error_log=true&include_stacktrace=true&max_lines=100` |

Parameters:
- `include_info_log=false` — skip info log (not needed for error diagnosis)
- `include_error_log=true` — fetch error.txt
- `include_stacktrace=true` — fetch stacktrace.txt
- `max_lines=100` — last 100 lines (configurable later)

**Save retrieved logs to files:**

```
logs/plugin-status/{siteName}-{pluginSlug}-error.txt
logs/plugin-status/{siteName}-{pluginSlug}-stacktrace.txt
```

Site names are sanitized (spaces → underscores, special chars removed).

### 6. Parallel Execution

Same pattern as `-uas`:
- Default: parallel via `Start-Job` (one job per site×plugin)
- `-sync` flag: sequential execution
- Each job dot-sources the module and runs independently

---

## Console Output

### Status-Only Mode (no `-error`)

```
========================================
  Plugin Status Check (-pas)
  Mode: PARALLEL
========================================

  Checking 2 plugin(s) on 3 site(s) (6 checks)...

  ── Status Results ─────────────────────────────────────────

  Atto Property Demo (https://demoat.attoproperty.com.au)
    ✓ qupload                  v2.18.0    OK           0.8s
    ✓ riseup-asia-uploader     v2.18.0    OK           1.1s

  Test V1 (https://testv1.developers-organism.com)
    ✓ qupload                  v2.18.0    OK           0.6s
    x riseup-asia-uploader     —          NOT_INSTALLED 0.3s

  Test V2 (https://testv2.developers-organism.com)
    ✓ qupload                  v2.18.0    OK           0.7s
    ✓ riseup-asia-uploader     v2.18.0    OK           0.9s

  ────────────────────────────────────────
  Sites: 3 | Plugins: 2 | Total: 6
  OK: 5 | Failed: 1
========================================
```

### Error Mode (`-error`)

After the status table, append:

```
  ── Error Logs ─────────────────────────────────────────────

  [Test V1 / qupload]
    Error log: 12 lines (saved)
    Stacktrace: empty

  [Test V2 / riseup-asia-uploader]
    Error log: empty
    Stacktrace: 3 entries (saved)

  Logs saved to: logs/plugin-status/
========================================
```

---

## Saved Files

### Status Summary (always saved)

```
logs/plugin-status/status-summary-{timestamp}.txt
```

Contains the full console output as plain text.

### Error Logs (only with `-error`)

```
logs/plugin-status/{SiteName}-{pluginSlug}-error.txt
logs/plugin-status/{SiteName}-{pluginSlug}-stacktrace.txt
```

Only created when the log has content (skip empty logs).

---

## Help Text

Add to the existing help output in `run.ps1`:

```
  Plugin Status:
    -ps                Check plugin status on default site
    -pas               Check plugin status on all sites
    -ps -error         Include error logs and stack traces
    -pas -i 2          Status for site index 2 only
    -pas -site "Name"  Status for named site only
```

---

## Error Handling

- If a site is unreachable, log `UNREACHABLE` and continue
- If auth fails, log `AUTH_FAILED` and continue
- Never abort — collect all results, report at the end
- Exit code: `0` if all OK, `1` if any failures

---

## Relationship to Existing Code

- Reuses: `Resolve-TargetSites`, `Get-UploadablePlugins`, `Decode-Base64`, `Show-ConfiguredSites`
- New module: `mode-plugin-status.ps1` (dot-sourced by `run.ps1`)
- New params in `run.ps1`: `-ps` (alias `pluginstatus`), `-pas` (alias `pluginstatusall`), `-error`
- Log folder: `logs/plugin-status/` (independent from `logs/uas-upload/`)

---

## REST API Namespace Reference

| Plugin | Namespace | Status | Logs Retrieve |
|--------|-----------|--------|---------------|
| QUpload | `qupload-api/v1` | `GET /status` | `GET /logs/retrieve` |
| Riseup Asia Uploader | `riseup-asia-uploader/v1` | `GET /status` | `GET /logs/retrieve` |
