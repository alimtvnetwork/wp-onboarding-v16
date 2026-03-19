# Memory: coding-standards/powershell-rest-api
Updated: 2026-03-19

## CRITICAL: WordPress REST API Calls in PowerShell

### Rule 1: NEVER use Invoke-RestMethod

WordPress sites with `WP_DEBUG_DISPLAY=On` (or third-party plugins like UpdraftPlus) prepend PHP deprecation notices as HTML before the JSON body. `Invoke-RestMethod` cannot auto-parse this mixed content and returns a raw string instead of a PSObject.

**ALWAYS use `Invoke-WebRequest` with `-UseBasicParsing`**, then manually parse the JSON.

```powershell
# WRONG — will silently fail on PHP noise
$resp = Invoke-RestMethod -Uri $url -Headers $headers

# CORRECT — handles PHP noise
$rawResp = Invoke-WebRequest -Uri $url -Method Get -Headers $headers -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop
$rawBody = $rawResp.Content
```

### Rule 2: ALWAYS strip PHP noise before JSON parsing

```powershell
$jsonBody = $rawBody
$jsonStart = $rawBody.IndexOf('{')
if ($jsonStart -gt 0) { $jsonBody = $rawBody.Substring($jsonStart) }
$parsed = $jsonBody | ConvertFrom-Json -ErrorAction Stop
```

### Rule 3: ALWAYS use envelope-aware property extraction

The status API returns an envelope: `{ Status: {...}, Results: [{Version, Plugin, ...}] }`. Version is at `Results[0].Version`, NOT at the top level.

```powershell
# WRONG
$ver = $resp.version

# CORRECT
$ver = $null
if ($resp.Results -and $resp.Results.Count -gt 0) {
    $ver = $resp.Results[0].Version
}
```

### Rule 4: Verbose mode should show raw response body

When `-v` is active, show the raw string response BEFORE parsing, so the user can see exactly what the server sent (including any PHP noise).

## Incident Reference

See `spec/issues/2027-status-parsing-php-noise.md` for the full root cause analysis.
